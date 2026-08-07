<?php
declare(strict_types=1);

namespace App\Domains\Orders\Models;

class OrderStatusHistory
{
    /** @var int */
    public $id;

    /** @var int */
    public $orderId;

    /** @var string */
    public $fromStatus;

    /** @var string */
    public $toStatus;

    /** @var int|null */
    public $actorUserId;

    /** @var string|null */
    public $note;

    /** @var string */
    public $createdAt;

    public function __construct(array $data)
    {
        $this->id = (int) $data['id'];
        $this->orderId = (int) $data['order_id'];
        $this->fromStatus = (string) $data['from_status'];
        $this->toStatus = (string) $data['to_status'];
        $this->actorUserId = isset($data['actor_user_id']) ? (int) $data['actor_user_id'] : null;
        $this->note = $data['note'] ?? null;
        $this->createdAt = (string) $data['created_at'];
    }
}
