<?php

declare(strict_types=1);

namespace App\Platform\Events;

use App\Logging\Logger;
use Throwable;

/**
 * The lightweight in-process event dispatcher the spec index calls for
 * (docs/specs/00-index.md, "Cross-domain conventions"): "a lightweight
 * in-process event dispatcher lives in Platform (net-new in this phase
 * -- does not exist yet)... Events are synchronous, in-process,
 * best-effort in this phase -- a durable queue is a Future Enhancement."
 *
 * Warehouse (domain #7) is the first real bidirectional user of this
 * bus: Orders publishes OrderStatusChanged on every transition, a
 * Warehouse subscriber generates the pick list on paid->fulfilling, and
 * ShipmentService publishes OrderShipped, which an Orders subscriber
 * consumes to transition the order to 'shipped' -- completing the loop
 * per docs/specs/07-warehouse.md §9/§10.
 *
 * "Best-effort" is implemented as: a failing subscriber is logged and
 * skipped; it never breaks the caller that published the event.
 */
final class EventDispatcher
{
    /** @var array<string, list<callable>> */
    private array $listeners = [];

    /** @var Logger */
    private $logger;

    public function __construct(Logger $logger)
    {
        $this->logger = $logger;
    }

    public function subscribe(string $eventClass, callable $listener): void
    {
        $this->listeners[$eventClass][] = $listener;
    }

    /**
     * Synchronously invokes every subscriber registered for the event's
     * class, in subscription order. A subscriber exception is logged
     * with the event class and processing continues with the next
     * subscriber -- the publisher never sees subscriber failures.
     */
    public function dispatch(object $event): void
    {
        $class = get_class($event);

        foreach ($this->listeners[$class] ?? [] as $listener) {
            try {
                $listener($event);
            } catch (Throwable $exception) {
                $this->logger->error(sprintf(
                    'Event subscriber failed for %s: %s',
                    $class,
                    $exception->getMessage()
                ), [
                    'event' => $class,
                    'error' => $exception->getMessage(),
                ]);
            }
        }
    }
}
