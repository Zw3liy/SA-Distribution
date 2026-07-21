<?php
declare(strict_types=1);

namespace App\Domains\Identity\Models;

class Role
{
    /** @var int */
    public $id;

    /** @var string */
    public $name;

    /** @var string|null */
    public $description;

    public function __construct(array $data)
    {
        $this->id = (int) $data['id'];
        $this->name = (string) $data['name'];
        $this->description = $data['description'] ?? null;
    }
}
