<?php
declare(strict_types=1);

namespace App\Domains\Orders\Services;

use App\Domains\Customers\Services\AddressServiceInterface;
use App\Domains\Customers\Services\CustomerServiceInterface;
use App\Domains\Inventory\Exceptions\InsufficientStockException;
use App\Domains\Inventory\Repositories\WarehouseRepositoryInterface;
use App\Domains\Inventory\Services\InventoryServiceInterface;
use App\Domains\Orders\Exceptions\InvalidAddressException;
use App\Domains\Orders\Models\Order;
use App\Domains\Orders\Models\Payment;
use App\Domains\Orders\Repositories\OrderRepositoryInterface;
use App\Domains\Orders\Repositories\PaymentRepositoryInterface;
use InvalidArgumentException;
use RuntimeException;
use Throwable;

/**
 * Orchestrates checkout end-to-end, per docs/specs/06-orders.md §2/§7:
 * never touches Inventory/Customers repositories directly, only their
 * service interfaces (Inventory's WarehouseRepositoryInterface is the
 * one deliberate exception, mirroring the already-established precedent
 * of CartService/InventoryService depending directly on Catalog's
 * ProductRepositoryInterface when no suitable service-level method
 * exists for a narrow, mechanical lookup).
 */
class CheckoutService implements CheckoutServiceInterface
{
    /** @var CartServiceInterface */
    private $cartService;

    /** @var CustomerServiceInterface */
    private $customerService;

    /** @var AddressServiceInterface */
    private $addressService;

    /** @var InventoryServiceInterface */
    private $inventoryService;

    /** @var WarehouseRepositoryInterface */
    private $warehouseRepository;

    /** @var TaxCalculatorInterface */
    private $taxCalculator;

    /** @var OrderRepositoryInterface */
    private $orderRepository;

    /** @var PaymentRepositoryInterface */
    private $paymentRepository;

    public function __construct(
        CartServiceInterface $cartService,
        CustomerServiceInterface $customerService,
        AddressServiceInterface $addressService,
        InventoryServiceInterface $inventoryService,
        WarehouseRepositoryInterface $warehouseRepository,
        TaxCalculatorInterface $taxCalculator,
        OrderRepositoryInterface $orderRepository,
        PaymentRepositoryInterface $paymentRepository
    ) {
        $this->cartService = $cartService;
        $this->customerService = $customerService;
        $this->addressService = $addressService;
        $this->inventoryService = $inventoryService;
        $this->warehouseRepository = $warehouseRepository;
        $this->taxCalculator = $taxCalculator;
        $this->orderRepository = $orderRepository;
        $this->paymentRepository = $paymentRepository;
    }

    public function checkout(string $sessionId, int $customerId, int $userId, int $shippingAddressId, int $billingAddressId): Order
    {
        $cartItems = $this->cartService->getCartItems($sessionId);
        if (empty($cartItems)) {
            throw new InvalidArgumentException('Your cart is empty.');
        }

        $customer = $this->customerService->getById($customerId);
        if ($customer === null) {
            throw new InvalidArgumentException('Customer account not found.');
        }

        // Ownership re-check (docs/specs/06-orders.md §8) -- defense in
        // depth on top of Customers domain's own ownership rules, not a
        // replacement for them.
        $ownedAddressIds = array_map(static function ($address) {
            return $address->id;
        }, $this->addressService->listFor($customer));

        if (!in_array($shippingAddressId, $ownedAddressIds, true)) {
            throw new InvalidAddressException('Shipping address does not belong to this customer.');
        }
        if (!in_array($billingAddressId, $ownedAddressIds, true)) {
            throw new InvalidAddressException('Billing address does not belong to this customer.');
        }

        // Re-check availability at the moment of checkout, not just when
        // items were added to cart -- stock may have changed since (§8).
        foreach ($cartItems as $item) {
            if ($this->inventoryService->availableQuantity($item->productId) < $item->quantity) {
                throw new InsufficientStockException(sprintf(
                    'Insufficient stock for "%s" (requested %d).',
                    $item->name,
                    $item->quantity
                ));
            }
        }

        $warehouse = $this->warehouseRepository->findDefault();
        if ($warehouse === null) {
            throw new RuntimeException('No active warehouse is configured; checkout cannot reserve stock.');
        }

        $orderNumber = $this->generateOrderNumber();

        // Reserve stock per line item. If any reservation fails partway
        // through, every already-made reservation in this attempt is
        // released before rethrowing -- docs/specs/06-orders.md §14
        // explicitly calls this out as "the single easiest correctness
        // bug to introduce in a checkout flow": no code path may leave
        // stock reserved with no order ever created and no way for the
        // customer to retry.
        $reservations = [];
        try {
            foreach ($cartItems as $item) {
                $reservations[] = $this->inventoryService->reserve($item->productId, $warehouse->id, $item->quantity, $orderNumber);
            }
        } catch (Throwable $exception) {
            foreach ($reservations as $reservation) {
                $this->inventoryService->releaseReservation($reservation->id);
            }

            throw $exception;
        }

        $taxLineItems = array_map(static function ($item) {
            return ['unit_price' => $item->getUnitPrice(), 'quantity' => $item->quantity];
        }, $cartItems);
        $taxResult = $this->taxCalculator->calculate($taxLineItems);

        $orderItems = [];
        foreach ($cartItems as $index => $item) {
            $orderItems[] = [
                'product_id' => $item->productId,
                'sku' => $item->sku,
                'name_snapshot' => $item->name,
                'quantity' => $item->quantity,
                'unit_price_snapshot' => $item->getUnitPrice(),
                'line_total' => $item->getLineTotal(),
                'inventory_reservation_id' => $reservations[$index]->id,
            ];
        }

        try {
            $order = $this->orderRepository->create([
                'order_number' => $orderNumber,
                'customer_id' => $customerId,
                'user_id' => $userId,
                'status' => Order::STATUS_PENDING_PAYMENT,
                'subtotal' => $taxResult->subtotal,
                'tax_total' => $taxResult->taxTotal,
                'grand_total' => $taxResult->grandTotal,
                'shipping_address_id' => $shippingAddressId,
                'billing_address_id' => $billingAddressId,
            ], $orderItems);
        } catch (Throwable $exception) {
            foreach ($reservations as $reservation) {
                $this->inventoryService->releaseReservation($reservation->id);
            }

            throw $exception;
        }

        // Orchestration record only (docs/specs/06-orders.md §16) -- no
        // gateway is integrated yet (explicitly out of scope, §19/§20),
        // so this is created 'pending' and the order starts (and stays,
        // until staff or a future gateway integration transitions it)
        // in 'pending_payment'. method is a neutral placeholder, not a
        // real gateway identifier, until vendor selection happens.
        $this->paymentRepository->create([
            'order_id' => $order->id,
            'method' => 'unassigned',
            'status' => Payment::STATUS_PENDING,
            'amount' => $taxResult->grandTotal,
            'gateway_reference' => null,
        ]);

        // Cart is not deleted, only marked converted (§2) -- preserved
        // as the customer's order-history-adjacent reference. A future
        // add-to-cart for this session gets a brand new cart row, since
        // CartRepository::getCartIdBySession() excludes converted carts.
        $this->cartService->markCartConverted($sessionId, $order->id);

        return $order;
    }

    private function generateOrderNumber(): string
    {
        return sprintf('SDO-%s-%s', date('YmdHis'), random_int(100, 999));
    }
}
