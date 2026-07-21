<?php
declare(strict_types=1);

namespace App\Domains\Administration\Models;

class SystemSetting
{
    /** @var int */
    public $id;

    /** @var string */
    public $key;

    /** @var mixed */
    public $value;

    /** @var string */
    public $valueType;

    /** @var bool */
    public $isEditable;

    /** @var int|null */
    public $updatedBy;

    /** @var string */
    public $updatedAt;

    public function __construct(array $data)
    {
        $this->id = (int) $data['id'];
        $this->key = (string) $data['key'];
        $this->valueType = (string) $data['value_type'];
        $this->value = self::cast($data['value'], $this->valueType);
        $this->isEditable = (bool) $data['is_editable'];
        $this->updatedBy = isset($data['updated_by']) ? (int) $data['updated_by'] : null;
        $this->updatedAt = (string) $data['updated_at'];
    }

    /**
     * @return mixed
     */
    public static function cast(string $rawValue, string $valueType)
    {
        switch ($valueType) {
            case 'int':
                return (int) $rawValue;
            case 'bool':
                return $rawValue === '1' || $rawValue === 'true';
            case 'json':
                return json_decode($rawValue, true) ?? [];
            default:
                return $rawValue;
        }
    }
}
