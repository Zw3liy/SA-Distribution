<?php
declare(strict_types=1);

namespace App\Domains\Catalog\Models;

class TaxClass
{
    /** @var int */
    public $id;

    /** @var string */
    public $name;

    /** @var float */
    public $defaultRate;

    public function __construct(array $data)
    {
        $this->id = (int) $data['id'];
        $this->name = (string) $data['name'];
        $this->defaultRate = (float) $data['default_rate'];
    }
}
