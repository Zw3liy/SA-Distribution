<?php

declare(strict_types=1);

namespace Tests\Unit\Orders;

use App\Domains\Customers\Models\Address;
use App\Domains\Customers\Models\Customer;
use App\Domains\Customers\Services\AddressServiceInterface;
use App\Domains\Customers\Services\CustomerServiceInterface;
use App\Domains\Inventory\Exceptions\InsufficientStockException;
use App\Domains\Inventory\Models\StockReservation;
use App\Domains\Inventory\Models\Warehouse;
use App\Domains\Inventory\Repositories\WarehouseRepositoryInterface;
use App\Domains\Inventory\Services\InventoryServiceInterface;
use App\Domains\Orders\Exceptions\InvalidAddressException;
use App\Domains\Orders\Models\CartItem;
use App\Domains\Orders\Models\Order;
use App\Domains\Orders\Models\Payment;
use App\Domains\Orders\Repositories\OrderRepositoryInterface;
use App\Domains\Orders\Repositories\PaymentRepositoryInterface;
use App\Domains\Orders\Services\CartServiceInterface;
use App\Domains\Orders\Services\CheckoutService;
use App\Domains\Orders\Services\TaxCalculationResult;
use App\Domains\Orders\Services\TaxCalculatorInterface;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use RuntimeException;

/**
 * CheckoutService orchestrates the full cart -> order flow
 * (docs/specs/06-orders.md §2/§7/§14): ownership re-checks on both
 * addresses, availability re-check at checkout time, per-line stock
 * reservation with full rollback on any later failure, price snapshot
 * into order items, a pending payment orchestration record, and the
 * converted-cart handoff. The "no reservation may be left dangling
 * without an order" rule (§14) is the single most important invariant.
 */
final class CheckoutServiceTest extends TestCase
{
    private function makeCustomer(int $id = 10): Customer
    {
        return new Customer([
            'id' => $id,
            'account_type' => 'b2c',
            'company_name' => null,
            'parent_customer_id' => null,
            'credit_terms' => null,
            'created_at' => '2026-07-21 09:00:00',
            'updated_at' => '2026-07-21 09:00:00',
        ]);
    }

    private function makeAddress(int $id): Address
    {
        return new Address([
            'id' => $id,
            'customer_id' => 10,
            'user_id' => null,
            'label' => 'Repo Test Address',
            'is_default' => 0,
            'address_line_1' => '1 Main Road',
            'address_line_2' => null,
            'city' => 'Johannesburg',
            'region' => 'Gauteng',
            'postal_code' => '2196',
            'country' => 'South Africa',
            'type' => 'both',
            'created_at' => '2026-07-21 09:00:00',
            'updated_at' => '2026-07-21 09:00:00',
        ]);
    }

    private function makeCartItem(array $overrides = []): CartItem
    {
        return new CartItem(array_merge([
            'product_id' => 1,
            'slug' => 'test-widget',
            'name' => 'Test Widget',
            'sku' => 'TEST-WIDGET',
            'price' => 100.0,
            'sale_price' => null,
            'quantity' => 2,
            'stock' => 10,
        ], $overrides));
    }

    private function makeReservation(int $id = 1): StockReservation
    {
        return new StockReservation([
            'id' => $id,
            'inventory_item_id' => 1,
            'order_reference' => 'SDO-TEST',
            'quantity' => 2,
            'expires_at' => '2026-07-21 09:30:00',
            'status' => 'active',
            'created_at' => '2026-07-21 09:00:00',
        ]);
    }

    private function makeOrder(array $overrides = []): Order
    {
        return new Order(array_merge([
            'id' => 55,
            'order_number' => 'SDO-20260721120000-123',
            'customer_id' => 10,
            'user_id' => 20,
            'status' => Order::STATUS_PENDING_PAYMENT,
            'subtotal' => 200.0,
            'tax_total' => 30.0,
            'grand_total' => 230.0,
            'shipping_address_id' => 31,
            'billing_address_id' => 32,
            'placed_at' => '2026-07-21 12:00:00',
            'updated_at' => '2026-07-21 12:00:00',
        ], $overrides));
    }

    private function makeService(array $deps): CheckoutService
    {
        return new CheckoutService(
            $deps['cartService'] ?? $this->createMock(CartServiceInterface::class),
            $deps['customerService'] ?? $this->createMock(CustomerServiceInterface::class),
            $deps['addressService'] ?? $this->createMock(AddressServiceInterface::class),
            $deps['inventoryService'] ?? $this->createMock(InventoryServiceInterface::class),
            $deps['warehouseRepository'] ?? $this->createMock(WarehouseRepositoryInterface::class),
            $deps['taxCalculator'] ?? $this->createMock(TaxCalculatorInterface::class),
            $deps['orderRepository'] ?? $this->createMock(OrderRepositoryInterface::class),
            $deps['paymentRepository'] ?? $this->createMock(PaymentRepositoryInterface::class)
        );
    }

    /**
     * Happy-path deps: one cart line, owned addresses, sufficient stock,
     * a default warehouse, tax and an order/payment that persist.
     */
    private function happyPathDeps(): array
    {
        $cartService = $this->createMock(CartServiceInterface::class);
        $cartService->method('getCartItems')->willReturn([$this->makeCartItem()]);

        $customerService = $this->createMock(CustomerServiceInterface::class);
        $customerService->method('getById')->with(10)->willReturn($this->makeCustomer());

        $addressService = $this->createMock(AddressServiceInterface::class);
        $addressService->method('listFor')->willReturn([$this->makeAddress(31), $this->makeAddress(32)]);

        $inventoryService = $this->createMock(InventoryServiceInterface::class);
        $inventoryService->method('availableQuantity')->willReturn(10);
        $inventoryService->method('reserve')->willReturn($this->makeReservation());

        $warehouseRepository = $this->createMock(WarehouseRepositoryInterface::class);
        $warehouseRepository->method('findDefault')->willReturn(
            new Warehouse(['id' => 1, 'name' => 'Main Warehouse', 'code' => 'MAIN', 'is_active' => 1])
        );

        $taxCalculator = $this->createMock(TaxCalculatorInterface::class);
        $taxCalculator->method('calculate')->willReturn(new TaxCalculationResult(200.0, 30.0, 230.0));

        $orderRepository = $this->createMock(OrderRepositoryInterface::class);
        $orderRepository->method('create')->willReturn($this->makeOrder());

        $paymentRepository = $this->createMock(PaymentRepositoryInterface::class);
        $paymentRepository->method('create')->willReturn(new Payment([
            'id' => 1, 'order_id' => 55, 'method' => 'unassigned', 'status' => Payment::STATUS_PENDING,
            'amount' => 230.0, 'gateway_reference' => null, 'created_at' => '2026-07-21 12:00:00',
        ]));

        return [
            'cartService' => $cartService,
            'customerService' => $customerService,
            'addressService' => $addressService,
            'inventoryService' => $inventoryService,
            'warehouseRepository' => $warehouseRepository,
            'taxCalculator' => $taxCalculator,
            'orderRepository' => $orderRepository,
            'paymentRepository' => $paymentRepository,
        ];
    }

    public function testCheckoutWithEmptyCartThrowsInvalidArgumentException(): void
    {
        $cartService = $this->createMock(CartServiceInterface::class);
        $cartService->method('getCartItems')->willReturn([]);

        $service = $this->makeService(['cartService' => $cartService] + $this->happyPathDeps());

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('cart is empty');

        $service->checkout('session-1', 10, 20, 31, 32);
    }

    public function testCheckoutWithUnknownCustomerThrowsInvalidArgumentException(): void
    {
        $customerService = $this->createMock(CustomerServiceInterface::class);
        $customerService->method('getById')->willReturn(null);

        $deps = $this->happyPathDeps();
        $deps['customerService'] = $customerService;

        $service = $this->makeService($deps);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Customer account not found');

        $service->checkout('session-1', 10, 20, 31, 32);
    }

    /**
     * docs/specs/06-orders.md §8: both addresses must belong to the
     * checking-out customer -- a defense-in-depth re-check of Customers
     * domain ownership, not a replacement for it.
     */
    public function testCheckoutRejectsShippingAddressNotOwnedByCustomer(): void
    {
        $addressService = $this->createMock(AddressServiceInterface::class);
        $addressService->method('listFor')->willReturn([$this->makeAddress(32)]); // 31 not owned

        $deps = $this->happyPathDeps();
        $deps['addressService'] = $addressService;

        $service = $this->makeService($deps);

        $this->expectException(InvalidAddressException::class);
        $this->expectExceptionMessage('Shipping address does not belong');

        $service->checkout('session-1', 10, 20, 31, 32);
    }

    public function testCheckoutRejectsBillingAddressNotOwnedByCustomer(): void
    {
        $addressService = $this->createMock(AddressServiceInterface::class);
        $addressService->method('listFor')->willReturn([$this->makeAddress(31)]); // 32 not owned

        $deps = $this->happyPathDeps();
        $deps['addressService'] = $addressService;

        $service = $this->makeService($deps);

        $this->expectException(InvalidAddressException::class);
        $this->expectExceptionMessage('Billing address does not belong');

        $service->checkout('session-1', 10, 20, 31, 32);
    }

    /**
     * docs/specs/06-orders.md §8: availability is re-checked at the
     * moment of checkout, not just at add-to-cart time.
     */
    public function testCheckoutThrowsInsufficientStockWhenAvailabilityDropped(): void
    {
        $inventoryService = $this->createMock(InventoryServiceInterface::class);
        $inventoryService->method('availableQuantity')->willReturn(1); // cart wants 2
        $inventoryService->expects($this->never())->method('reserve');

        $deps = $this->happyPathDeps();
        $deps['inventoryService'] = $inventoryService;

        $service = $this->makeService($deps);

        $this->expectException(InsufficientStockException::class);

        $service->checkout('session-1', 10, 20, 31, 32);
    }

    public function testCheckoutThrowsWhenNoDefaultWarehouseConfigured(): void
    {
        $warehouseRepository = $this->createMock(WarehouseRepositoryInterface::class);
        $warehouseRepository->method('findDefault')->willReturn(null);

        $deps = $this->happyPathDeps();
        $deps['warehouseRepository'] = $warehouseRepository;

        $service = $this->makeService($deps);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('No active warehouse');

        $service->checkout('session-1', 10, 20, 31, 32);
    }

    /**
     * The full happy path: order persists with the tax-derived totals,
     * item rows carry the per-line reservation ids, the order is created
     * in pending_payment, a pending orchestration payment is recorded
     * (method 'unassigned' until a gateway vendor is chosen -- §16), and
     * the cart is marked converted, not deleted (§2).
     */
    public function testCheckoutHappyPathPersistsOrderItemsPaymentAndCartConversion(): void
    {
        $cartService = $this->createMock(CartServiceInterface::class);
        $cartService->method('getCartItems')->willReturn([
            $this->makeCartItem(['product_id' => 1, 'sku' => 'A-1', 'quantity' => 2]),
            $this->makeCartItem(['product_id' => 2, 'slug' => 'other-widget', 'name' => 'Other Widget',
                                 'sku' => 'B-2', 'price' => 50.0, 'quantity' => 1]),
        ]);

        $inventoryService = $this->createMock(InventoryServiceInterface::class);
        $inventoryService->method('availableQuantity')->willReturn(99);
        $inventoryService->expects($this->exactly(2))->method('reserve')->willReturnOnConsecutiveCalls(
            $this->makeReservation(501),
            $this->makeReservation(502)
        );
        $inventoryService->expects($this->never())->method('releaseReservation');

        $orderRepository = $this->createMock(OrderRepositoryInterface::class);
        $orderRepository->expects($this->once())
            ->method('create')
            ->with(
                $this->callback(function (array $data): bool {
                    return preg_match('/^SDO-\d{14}-\d{3}$/', $data['order_number']) === 1
                        && $data['customer_id'] === 10
                        && $data['user_id'] === 20
                        && $data['status'] === Order::STATUS_PENDING_PAYMENT
                        && $data['subtotal'] === 200.0
                        && $data['tax_total'] === 30.0
                        && $data['grand_total'] === 230.0
                        && $data['shipping_address_id'] === 31
                        && $data['billing_address_id'] === 32;
                }),
                $this->callback(function (array $items): bool {
                    return count($items) === 2
                        && $items[0]['product_id'] === 1
                        && $items[0]['name_snapshot'] === 'Test Widget'
                        && $items[0]['inventory_reservation_id'] === 501
                        && $items[1]['inventory_reservation_id'] === 502;
                })
            )
            ->willReturn($this->makeOrder());

        $paymentRepository = $this->createMock(PaymentRepositoryInterface::class);
        $paymentRepository->expects($this->once())
            ->method('create')
            ->with($this->callback(function (array $data): bool {
                return $data['order_id'] === 55
                    && $data['method'] === 'unassigned'
                    && $data['status'] === Payment::STATUS_PENDING
                    && $data['amount'] === 230.0
                    && $data['gateway_reference'] === null;
            }))
            ->willReturn(new Payment([
                'id' => 1, 'order_id' => 55, 'method' => 'unassigned', 'status' => Payment::STATUS_PENDING,
                'amount' => 230.0, 'gateway_reference' => null, 'created_at' => '2026-07-21 12:00:00',
            ]));

        $cartService->expects($this->once())->method('markCartConverted')->with('session-1', 55);

        $deps = $this->happyPathDeps();
        $deps['cartService'] = $cartService;
        $deps['inventoryService'] = $inventoryService;
        $deps['orderRepository'] = $orderRepository;
        $deps['paymentRepository'] = $paymentRepository;

        $service = $this->makeService($deps);
        $order = $service->checkout('session-1', 10, 20, 31, 32);

        $this->assertSame(55, $order->id);
        $this->assertSame(Order::STATUS_PENDING_PAYMENT, $order->status);
    }

    /**
     * docs/specs/06-orders.md §14: "the single easiest correctness bug
     * to introduce in a checkout flow" -- if reserving line N fails, the
     * reservations already made for lines 1..N-1 must be released before
     * rethrowing, so no stock is left reserved with no order behind it.
     */
    public function testCheckoutReleasesPriorReservationsWhenReserveFailsMidway(): void
    {
        $inventoryService = $this->createMock(InventoryServiceInterface::class);
        $inventoryService->method('availableQuantity')->willReturn(99);
        $inventoryService->method('reserve')->willReturnOnConsecutiveCalls(
            $this->makeReservation(501),
            $this->throwException(new InsufficientStockException('Out of stock for line 2.'))
        );
        $inventoryService->expects($this->once())->method('releaseReservation')->with(501);

        $orderRepository = $this->createMock(OrderRepositoryInterface::class);
        $orderRepository->expects($this->never())->method('create');

        $cartService = $this->createMock(CartServiceInterface::class);
        $cartService->method('getCartItems')->willReturn([
            $this->makeCartItem(['product_id' => 1, 'quantity' => 1]),
            $this->makeCartItem(['product_id' => 2, 'quantity' => 1]),
        ]);

        $deps = $this->happyPathDeps();
        $deps['cartService'] = $cartService;
        $deps['inventoryService'] = $inventoryService;
        $deps['orderRepository'] = $orderRepository;

        $service = $this->makeService($deps);

        $this->expectException(InsufficientStockException::class);

        $service->checkout('session-1', 10, 20, 31, 32);
    }

    /**
     * docs/specs/06-orders.md §14, second half: if order persistence
     * fails (e.g. DB error after reservations succeeded), every
     * reservation from this attempt must be released before rethrowing.
     */
    public function testCheckoutReleasesAllReservationsWhenOrderCreateFails(): void
    {
        $cartService = $this->createMock(CartServiceInterface::class);
        $cartService->method('getCartItems')->willReturn([
            $this->makeCartItem(['product_id' => 1, 'quantity' => 1]),
            $this->makeCartItem(['product_id' => 2, 'quantity' => 1]),
        ]);

        $inventoryService = $this->createMock(InventoryServiceInterface::class);
        $inventoryService->method('availableQuantity')->willReturn(99);
        $inventoryService->method('reserve')->willReturnOnConsecutiveCalls(
            $this->makeReservation(501),
            $this->makeReservation(502)
        );
        $inventoryService->expects($this->exactly(2))->method('releaseReservation');

        $orderRepository = $this->createMock(OrderRepositoryInterface::class);
        $orderRepository->method('create')->willThrowException(new RuntimeException('DB down'));

        $deps = $this->happyPathDeps();
        $deps['cartService'] = $cartService;
        $deps['inventoryService'] = $inventoryService;
        $deps['orderRepository'] = $orderRepository;

        $service = $this->makeService($deps);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('DB down');

        $service->checkout('session-1', 10, 20, 31, 32);
    }

    /**
     * The tax calculator is fed exactly one line per cart item, using
     * the effective unit price (sale price wins) and quantity -- the
     * same input shape Finance's TaxCalculatorInterface will use (§5).
     */
    public function testCheckoutFeedsTaxCalculatorTheEffectiveUnitPrices(): void
    {
        $cartService = $this->createMock(CartServiceInterface::class);
        $cartService->method('getCartItems')->willReturn([
            $this->makeCartItem(['product_id' => 1, 'price' => 100.0, 'sale_price' => 80.0, 'quantity' => 2]),
        ]);

        $taxCalculator = $this->createMock(TaxCalculatorInterface::class);
        $taxCalculator->expects($this->once())
            ->method('calculate')
            ->with($this->callback(function (array $lineItems): bool {
                return $lineItems === [['unit_price' => 80.0, 'quantity' => 2]];
            }))
            ->willReturn(new TaxCalculationResult(160.0, 24.0, 184.0));

        $orderRepository = $this->createMock(OrderRepositoryInterface::class);
        $orderRepository->method('create')->willReturn($this->makeOrder());

        $deps = $this->happyPathDeps();
        $deps['cartService'] = $cartService;
        $deps['taxCalculator'] = $taxCalculator;
        $deps['orderRepository'] = $orderRepository;

        $service = $this->makeService($deps);
        $service->checkout('session-1', 10, 20, 31, 32);

        $this->assertTrue(true);
    }

    /**
     * Order numbers are generated as SDO-{timestamp}-{random 3 digits}
     * -- unique enough for a single process and greppable in the admin
     * UI, while the real uniqueness guarantee comes from the
     * uq_orders_order_number index (§3).
     */
    public function testGeneratedOrderNumberMatchesExpectedFormat(): void
    {
        $deps = $this->happyPathDeps();

        $orderRepository = $this->createMock(OrderRepositoryInterface::class);
        $orderRepository->method('create')->willReturnCallback(function (array $data) {
            return $this->makeOrder(['order_number' => $data['order_number']]);
        });
        $deps['orderRepository'] = $orderRepository;

        $service = $this->makeService($deps);
        $order = $service->checkout('session-1', 10, 20, 31, 32);

        $this->assertMatchesRegularExpression('/^SDO-\d{14}-\d{3}$/', $order->orderNumber);
    }
}
