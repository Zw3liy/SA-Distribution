<?php
declare(strict_types=1);

namespace Tests\Unit\Identity;

use App\Domains\Identity\Exceptions\AccountInactiveException;
use App\Domains\Identity\Exceptions\AccountLockedException;
use App\Domains\Identity\Exceptions\DuplicateEmailException;
use App\Domains\Identity\Exceptions\InvalidCredentialsException;
use App\Domains\Identity\Models\User;
use App\Domains\Identity\Repositories\UserRepositoryInterface;
use App\Domains\Identity\Services\AuthService;
use PHPUnit\Framework\TestCase;

final class AuthServiceTest extends TestCase
{
    private function makeUser(array $overrides = []): User
    {
        return new User(array_merge([
            'id' => 1,
            'first_name' => 'Test',
            'last_name' => 'User',
            'company_name' => null,
            'email' => 'test@example.co.za',
            'phone' => '0123456789',
            'password_hash' => password_hash('Password123!', PASSWORD_DEFAULT),
            'is_active' => 1,
            'is_verified' => 0,
            'account_kind' => 'customer',
            'created_at' => '2026-01-01 00:00:00',
            'updated_at' => '2026-01-01 00:00:00',
        ], $overrides));
    }

    public function testAuthenticateSucceedsWithCorrectCredentials(): void
    {
        $user = $this->makeUser();
        $repo = $this->createMock(UserRepositoryInterface::class);
        $repo->method('recentFailedAttempts')->willReturn(0);
        $repo->method('findByEmail')->willReturn($user);
        $repo->expects($this->once())->method('recordLoginAttempt')->with('test@example.co.za', '127.0.0.1', true);
        $repo->expects($this->once())->method('updateLastLogin')->with(1);

        $service = new AuthService($repo);
        $result = $service->authenticate('test@example.co.za', 'Password123!', '127.0.0.1');

        $this->assertSame($user, $result);
    }

    public function testAuthenticateThrowsInvalidCredentialsOnWrongPassword(): void
    {
        $user = $this->makeUser();
        $repo = $this->createMock(UserRepositoryInterface::class);
        $repo->method('recentFailedAttempts')->willReturn(0);
        $repo->method('findByEmail')->willReturn($user);
        $repo->expects($this->once())->method('recordLoginAttempt')->with('test@example.co.za', '127.0.0.1', false);

        $service = new AuthService($repo);

        $this->expectException(InvalidCredentialsException::class);
        $service->authenticate('test@example.co.za', 'wrong-password', '127.0.0.1');
    }

    public function testAuthenticateThrowsInvalidCredentialsWhenUserNotFound(): void
    {
        $repo = $this->createMock(UserRepositoryInterface::class);
        $repo->method('recentFailedAttempts')->willReturn(0);
        $repo->method('findByEmail')->willReturn(null);

        $service = new AuthService($repo);

        $this->expectException(InvalidCredentialsException::class);
        $service->authenticate('nobody@example.co.za', 'whatever', '127.0.0.1');
    }

    public function testAuthenticateThrowsAccountInactiveForDeactivatedAccount(): void
    {
        $user = $this->makeUser(['is_active' => 0]);
        $repo = $this->createMock(UserRepositoryInterface::class);
        $repo->method('recentFailedAttempts')->willReturn(0);
        $repo->method('findByEmail')->willReturn($user);

        $service = new AuthService($repo);

        $this->expectException(AccountInactiveException::class);
        $service->authenticate('test@example.co.za', 'Password123!', '127.0.0.1');
    }

    public function testAuthenticateThrowsAccountLockedAfterTooManyFailedAttempts(): void
    {
        $repo = $this->createMock(UserRepositoryInterface::class);
        $repo->method('recentFailedAttempts')->willReturn(5);
        // findByEmail must never be reached once locked out -- the rate
        // limit check runs first (docs/specs/01-identity.md §8).
        $repo->expects($this->never())->method('findByEmail');

        $service = new AuthService($repo);

        $this->expectException(AccountLockedException::class);
        $service->authenticate('test@example.co.za', 'Password123!', '127.0.0.1');
    }

    public function testRegisterThrowsDuplicateEmailForExistingEmail(): void
    {
        $repo = $this->createMock(UserRepositoryInterface::class);
        $repo->method('findByEmail')->willReturn($this->makeUser());

        $service = new AuthService($repo);

        $this->expectException(DuplicateEmailException::class);
        $service->register([
            'first_name' => 'New',
            'last_name' => 'User',
            'email' => 'test@example.co.za',
            'phone' => '0123456789',
            'password' => 'Password123!',
        ]);
    }

    public function testRegisterCreatesUserWithHashedPassword(): void
    {
        $repo = $this->createMock(UserRepositoryInterface::class);
        $repo->method('findByEmail')->willReturn(null);
        $repo->expects($this->once())
            ->method('create')
            ->with($this->callback(function (array $data) {
                return $data['email'] === 'new@example.co.za'
                    && $data['account_kind'] === 'customer'
                    && password_verify('Password123!', $data['password_hash']);
            }))
            ->willReturn(42);

        $service = new AuthService($repo);
        $id = $service->register([
            'first_name' => 'New',
            'last_name' => 'User',
            'email' => 'new@example.co.za',
            'phone' => '0123456789',
            'password' => 'Password123!',
        ]);

        $this->assertSame(42, $id);
    }
}
