<?php
declare(strict_types=1);

namespace Tests\Unit\Administration;

use App\Domains\Administration\Exceptions\InvalidSettingTypeException;
use App\Domains\Administration\Models\SystemSetting;
use App\Domains\Administration\Repositories\SystemSettingRepositoryInterface;
use App\Domains\Administration\Services\SettingsService;
use PHPUnit\Framework\TestCase;

final class SettingsServiceTest extends TestCase
{
    public function testGetReturnsDefaultWhenSettingMissing(): void
    {
        $repo = $this->createMock(SystemSettingRepositoryInterface::class);
        $repo->method('get')->willReturn(null);

        $service = new SettingsService($repo);

        $this->assertSame('fallback', $service->get('nonexistent.key', 'fallback'));
    }

    public function testGetReturnsCastValueWhenSettingExists(): void
    {
        $setting = new SystemSetting([
            'id' => 1,
            'key' => 'catalog.page_size',
            'value' => '25',
            'value_type' => 'int',
            'is_editable' => 1,
            'updated_by' => null,
            'updated_at' => '2026-01-01 00:00:00',
        ]);

        $repo = $this->createMock(SystemSettingRepositoryInterface::class);
        $repo->method('get')->willReturn($setting);

        $service = new SettingsService($repo);

        $this->assertSame(25, $service->get('catalog.page_size'));
    }

    public function testSetInfersBoolTypeAndEncodesAsOneOrZero(): void
    {
        $repo = $this->createMock(SystemSettingRepositoryInterface::class);
        $repo->expects($this->once())->method('upsert')->with('feature.x', '1', 'bool', 7);

        $service = new SettingsService($repo);
        $service->set('feature.x', true, 7);
    }

    public function testSetInfersJsonTypeAndEncodesArray(): void
    {
        $repo = $this->createMock(SystemSettingRepositoryInterface::class);
        $repo->expects($this->once())->method('upsert')->with('checkout.allowed_countries', '["ZA","NA"]', 'json', 3);

        $service = new SettingsService($repo);
        $service->set('checkout.allowed_countries', ['ZA', 'NA'], 3);
    }

    public function testSetThrowsForUnsupportedType(): void
    {
        $repo = $this->createMock(SystemSettingRepositoryInterface::class);
        $repo->expects($this->never())->method('upsert');

        $service = new SettingsService($repo);

        $this->expectException(InvalidSettingTypeException::class);
        $service->set('bad.key', new \stdClass(), 1);
    }
}
