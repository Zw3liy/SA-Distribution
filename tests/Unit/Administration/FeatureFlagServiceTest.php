<?php
declare(strict_types=1);

namespace Tests\Unit\Administration;

use App\Domains\Administration\Models\FeatureFlag;
use App\Domains\Administration\Repositories\FeatureFlagRepositoryInterface;
use App\Domains\Administration\Services\FeatureFlagService;
use App\Domains\Identity\Models\User;
use PHPUnit\Framework\TestCase;

final class FeatureFlagServiceTest extends TestCase
{
    private function makeUser(string $accountKind): User
    {
        return new User([
            'id' => 1,
            'first_name' => 'Test',
            'last_name' => 'User',
            'company_name' => null,
            'email' => 'test@example.co.za',
            'phone' => '0123456789',
            'password_hash' => 'x',
            'is_active' => 1,
            'is_verified' => 0,
            'account_kind' => $accountKind,
            'created_at' => '2026-01-01 00:00:00',
            'updated_at' => '2026-01-01 00:00:00',
        ]);
    }

    public function testIsEnabledReturnsFalseWhenFlagMissing(): void
    {
        $repo = $this->createMock(FeatureFlagRepositoryInterface::class);
        $repo->method('get')->willReturn(null);

        $service = new FeatureFlagService($repo);

        $this->assertFalse($service->isEnabled('unknown.flag'));
    }

    public function testIsEnabledReturnsFalseWhenFlagDisabled(): void
    {
        $flag = new FeatureFlag(['id' => 1, 'key' => 'x', 'is_enabled' => 0, 'rollout_rules_json' => null, 'updated_by' => null, 'updated_at' => '2026-01-01 00:00:00']);
        $repo = $this->createMock(FeatureFlagRepositoryInterface::class);
        $repo->method('get')->willReturn($flag);

        $service = new FeatureFlagService($repo);

        $this->assertFalse($service->isEnabled('x'));
    }

    public function testIsEnabledReturnsTrueForEnabledFlagWithNoRolloutRules(): void
    {
        $flag = new FeatureFlag(['id' => 1, 'key' => 'x', 'is_enabled' => 1, 'rollout_rules_json' => null, 'updated_by' => null, 'updated_at' => '2026-01-01 00:00:00']);
        $repo = $this->createMock(FeatureFlagRepositoryInterface::class);
        $repo->method('get')->willReturn($flag);

        $service = new FeatureFlagService($repo);

        $this->assertTrue($service->isEnabled('x'));
    }

    public function testIsEnabledRespectsStaffOnlyRolloutRule(): void
    {
        $flag = new FeatureFlag([
            'id' => 1,
            'key' => 'admin.beta_widget',
            'is_enabled' => 1,
            'rollout_rules_json' => json_encode(['staff_only' => true]),
            'updated_by' => null,
            'updated_at' => '2026-01-01 00:00:00',
        ]);
        $repo = $this->createMock(FeatureFlagRepositoryInterface::class);
        $repo->method('get')->willReturn($flag);

        $service = new FeatureFlagService($repo);

        $this->assertFalse($service->isEnabled('admin.beta_widget', $this->makeUser('customer')));
        $this->assertFalse($service->isEnabled('admin.beta_widget', null));
        $this->assertTrue($service->isEnabled('admin.beta_widget', $this->makeUser('staff')));
    }

    public function testSetDelegatesToRepositoryUpsert(): void
    {
        $repo = $this->createMock(FeatureFlagRepositoryInterface::class);
        $repo->expects($this->once())->method('upsert')->with('x', true, ['staff_only' => false], 9);

        $service = new FeatureFlagService($repo);
        $service->set('x', true, ['staff_only' => false], 9);
    }
}
