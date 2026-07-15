<?php
declare(strict_types=1);

class User
{
    /** @var int */
    public $id;

    /** @var string */
    public $firstName;

    /** @var string */
    public $lastName;

    /** @var string|null */
    public $companyName;

    /** @var string */
    public $email;

    /** @var string */
    public $phone;

    /** @var string */
    public $passwordHash;

    /** @var bool */
    public $isActive;

    /** @var bool */
    public $isVerified;

    /** @var bool */
    public $notificationsMarketing;

    /** @var bool */
    public $notificationsUpdates;

    /** @var string|null */
    public $lastLoginAt;

    /** @var string */
    public $createdAt;

    /** @var string */
    public $updatedAt;

    public function __construct(array $data)
    {
        $this->id = (int) $data['id'];
        $this->firstName = (string) $data['first_name'];
        $this->lastName = (string) $data['last_name'];
        $this->companyName = $data['company_name'] ?? null;
        $this->email = (string) $data['email'];
        $this->phone = (string) $data['phone'];
        $this->passwordHash = (string) $data['password_hash'];
        $this->isActive = (bool) $data['is_active'];
        $this->isVerified = (bool) $data['is_verified'];
        $this->notificationsMarketing = (bool) ($data['notifications_marketing'] ?? 0);
        $this->notificationsUpdates = (bool) ($data['notifications_updates'] ?? 1);
        $this->lastLoginAt = $data['last_login_at'] ?? null;
        $this->createdAt = (string) $data['created_at'];
        $this->updatedAt = (string) $data['updated_at'];
    }

    public function getFullName(): string
    {
        return sprintf('%s %s', $this->firstName, $this->lastName);
    }
}
