<?php
declare(strict_types=1);

namespace App\Domains\Administration\Models;

class FeatureFlag
{
    /** @var int */
    public $id;

    /** @var string */
    public $key;

    /** @var bool */
    public $isEnabled;

    /** @var array */
    public $rolloutRules;

    /** @var int|null */
    public $updatedBy;

    /** @var string */
    public $updatedAt;

    public function __construct(array $data)
    {
        $this->id = (int) $data['id'];
        $this->key = (string) $data['key'];
        $this->isEnabled = (bool) $data['is_enabled'];
        $rules = $data['rollout_rules_json'] ?? null;
        $this->rolloutRules = $rules ? (is_string($rules) ? (json_decode($rules, true) ?: []) : (array) $rules) : [];
        $this->updatedBy = isset($data['updated_by']) ? (int) $data['updated_by'] : null;
        $this->updatedAt = (string) $data['updated_at'];
    }
}
