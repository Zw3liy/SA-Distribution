<?php
declare(strict_types=1);

namespace Tests\Unit\Identity;

use App\Domains\Identity\Models\ApiCredential;
use App\Domains\Identity\Models\User;
use App\Domains\Identity\Repositories\ApiCredentialRepositoryInterface;
use App\Domains\Identity\Repositories\UserRepositoryInterface;
use App\Domains\Identity\Services\ApiCredentialService;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class ApiCredentialServiceTest extends TestCase
{
    private function makeUser(string $accountKind): User
    {
        return new User([
            'id' => 7,
            'first_name' => 'Staff',
            'last_name' => 'Member',
            'company_name' => null,
            'email' => 'staff@example.co.za',
            'phone' => '0123456789',
            'password_hash' => 'irrelevant',
            'is_active' => 1,
            'is_verified' => 1,
            'account_kind' => $accountKind,
            'created_at' => '2026-01-01 00:00:00',
            'updated_at' => '2026-01-01 00:00:00',
        ]);
    }

    public function testIssueRejectsNonStaffIssuer(): void
    {
        $credentialRepo = $this->createMock(ApiCredentialRepositoryInterface::class);
        $userRepo = $this->createMock(UserRepositoryInterface::class);
        $service = new ApiCredentialService($credentialRepo, $userRepo);

        $this->expectException(InvalidArgumentException::class);
        $service->issue($this->makeUser('customer'), 'test token', ['catalog.product.view']);
    }

    public function testIssueRejectsEmptyScopes(): void
    {
        $credentialRepo = $this->createMock(ApiCredentialRepositoryInterface::class);
        $userRepo = $this->createMock(UserRepositoryInterface::class);
        $service = new ApiCredentialService($credentialRepo, $userRepo);

        $this->expectException(InvalidArgumentException::class);
        $service->issue($this->makeUser('staff'), 'test token', []);
    }

    public function testIssueReturnsRawTokenAndStoresOnlyItsHash(): void
    {
        $credentialRepo = $this->createMock(ApiCredentialRepositoryInterface::class);
        $userRepo = $this->createMock(UserRepositoryInterface::class);

        $capturedHash = null;
        $credentialRepo->expects($this->once())
            ->method('create')
            ->with($this->equalTo(7), $this->equalTo('test token'), $this->isType('string'), $this->equalTo(['catalog.product.view']), $this->isNull())
            ->willReturnCallback(function (int $userId, string $name, string $hash, array $scopes) use (&$capturedHash) {
                $capturedHash = $hash;
                return new ApiCredential([
                    'id' => 1, 'user_id' => $userId, 'name' => $name, 'token_hash' => $hash,
                    'scopes' => $scopes, 'last_used_at' => null, 'expires_at' => null,
                    'revoked_at' => null, 'created_at' => '2026-01-01 00:00:00',
                ]);
            });

        $service = new ApiCredentialService($credentialRepo, $userRepo);
        $rawToken = $service->issue($this->makeUser('staff'), 'test token', ['catalog.product.view']);

        $this->assertNotEmpty($rawToken);
        $this->assertSame(hash('sha256', $rawToken), $capturedHash);
        $this->assertNotSame($rawToken, $capturedHash);
    }

    public function testAuthenticateReturnsNullForRevokedCredential(): void
    {
        $credentialRepo = $this->createMock(ApiCredentialRepositoryInterface::class);
        $userRepo = $this->createMock(UserRepositoryInterface::class);

        $revoked = new ApiCredential([
            'id' => 1, 'user_id' => 7, 'name' => 'x', 'token_hash' => hash('sha256', 'raw'),
            'scopes' => [], 'last_used_at' => null, 'expires_at' => null,
            'revoked_at' => '2026-01-01 00:00:00', 'created_at' => '2026-01-01 00:00:00',
        ]);
        $credentialRepo->method('findByTokenHash')->willReturn($revoked);

        $service = new ApiCredentialService($credentialRepo, $userRepo);
        $this->assertNull($service->authenticate('raw'));
    }

    public function testAuthenticateReturnsNullForExpiredCredential(): void
    {
        $credentialRepo = $this->createMock(ApiCredentialRepositoryInterface::class);
        $userRepo = $this->createMock(UserRepositoryInterface::class);

        $expired = new ApiCredential([
            'id' => 1, 'user_id' => 7, 'name' => 'x', 'token_hash' => hash('sha256', 'raw'),
            'scopes' => [], 'last_used_at' => null, 'expires_at' => '2020-01-01 00:00:00',
            'revoked_at' => null, 'created_at' => '2026-01-01 00:00:00',
        ]);
        $credentialRepo->method('findByTokenHash')->willReturn($expired);

        $service = new ApiCredentialService($credentialRepo, $userRepo);
        $this->assertNull($service->authenticate('raw'));
    }

    public function testAuthenticateReturnsUserForValidCredential(): void
    {
        $credentialRepo = $this->createMock(ApiCredentialRepositoryInterface::class);
        $userRepo = $this->createMock(UserRepositoryInterface::class);

        $valid = new ApiCredential([
            'id' => 1, 'user_id' => 7, 'name' => 'x', 'token_hash' => hash('sha256', 'raw'),
            'scopes' => [], 'last_used_at' => null, 'expires_at' => null,
            'revoked_at' => null, 'created_at' => '2026-01-01 00:00:00',
        ]);
        $credentialRepo->method('findByTokenHash')->willReturn($valid);
        $credentialRepo->expects($this->once())->method('touchLastUsed')->with(1);
        $userRepo->method('findById')->with(7)->willReturn($this->makeUser('staff'));

        $service = new ApiCredentialService($credentialRepo, $userRepo);
        $user = $service->authenticate('raw');

        $this->assertNotNull($user);
        $this->assertSame(7, $user->id);
    }
}
