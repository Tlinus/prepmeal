<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use PHPUnit\Framework\TestCase;
use PrepMeal\Core\Services\UserService;
use PrepMeal\Core\Database\UserRepository;
use Mockery;

class UserServiceTest extends TestCase
{
    private UserService $userService;
    private UserRepository $mockUserRepository;

    protected function setUp(): void
    {
        $this->mockUserRepository = Mockery::mock(UserRepository::class);
        $this->userService = new UserService($this->mockUserRepository);
    }

    protected function tearDown(): void
    {
        Mockery::close();
    }

    public function testHashPasswordReturnsValidHash(): void
    {
        $password = 'testpassword123';
        $hash = $this->userService->hashPassword($password);

        $this->assertIsString($hash);
        $this->assertNotEquals($password, $hash);
        $this->assertTrue(password_verify($password, $hash));
    }

    public function testVerifyPasswordWithValidPassword(): void
    {
        $password = 'testpassword123';
        $hash = password_hash($password, PASSWORD_DEFAULT);

        $this->assertTrue($this->userService->verifyPassword($password, $hash));
    }

    public function testVerifyPasswordWithInvalidPassword(): void
    {
        $password = 'testpassword123';
        $wrongPassword = 'wrongpassword';
        $hash = password_hash($password, PASSWORD_DEFAULT);

        $this->assertFalse($this->userService->verifyPassword($wrongPassword, $hash));
    }

    public function testAuthenticateWithValidCredentials(): void
    {
        $email = 'test@example.com';
        $password = 'testpassword123';
        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

        $userData = [
            'id' => 1,
            'email' => $email,
            'name' => 'Test User',
            'password' => $hashedPassword
        ];

        $this->mockUserRepository
            ->shouldReceive('findByEmail')
            ->with($email)
            ->once()
            ->andReturn($userData);

        $result = $this->userService->authenticate($email, $password);

        $this->assertNotNull($result);
        $this->assertEquals($userData['id'], $result['id']);
        $this->assertEquals($userData['email'], $result['email']);
        $this->assertEquals($userData['name'], $result['name']);
        $this->assertArrayNotHasKey('password', $result);
    }

    public function testAuthenticateWithInvalidEmail(): void
    {
        $email = 'nonexistent@example.com';
        $password = 'testpassword123';

        $this->mockUserRepository
            ->shouldReceive('findByEmail')
            ->with($email)
            ->once()
            ->andReturn(null);

        $result = $this->userService->authenticate($email, $password);

        $this->assertNull($result);
    }

    public function testAuthenticateWithInvalidPassword(): void
    {
        $email = 'test@example.com';
        $password = 'testpassword123';
        $wrongPassword = 'wrongpassword';
        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

        $userData = [
            'id' => 1,
            'email' => $email,
            'name' => 'Test User',
            'password' => $hashedPassword
        ];

        $this->mockUserRepository
            ->shouldReceive('findByEmail')
            ->with($email)
            ->once()
            ->andReturn($userData);

        $result = $this->userService->authenticate($email, $wrongPassword);

        $this->assertNull($result);
    }

    public function testRegisterWithValidData(): void
    {
        $userData = [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'testpassword123'
        ];

        $this->mockUserRepository
            ->shouldReceive('findByEmail')
            ->with($userData['email'])
            ->once()
            ->andReturn(null);

        $this->mockUserRepository
            ->shouldReceive('create')
            ->with(Mockery::on(function ($data) use ($userData) {
                return $data['name'] === $userData['name'] &&
                       $data['email'] === $userData['email'] &&
                       $data['password'] !== $userData['password'] && // Password should be hashed
                       password_verify($userData['password'], $data['password']); // Hash should be valid
            }))
            ->once()
            ->andReturn(1);

        $createdUser = [
            'id' => 1,
            'name' => $userData['name'],
            'email' => $userData['email'],
            'password' => 'hashed_password_here'
        ];

        $this->mockUserRepository
            ->shouldReceive('findById')
            ->with(1)
            ->once()
            ->andReturn($createdUser);

        $result = $this->userService->register($userData);

        $this->assertNotNull($result);
        $this->assertEquals($userData['name'], $result['name']);
        $this->assertEquals($userData['email'], $result['email']);
        $this->assertArrayNotHasKey('password', $result);
    }

    public function testRegisterWithExistingEmail(): void
    {
        $userData = [
            'name' => 'Test User',
            'email' => 'existing@example.com',
            'password' => 'testpassword123'
        ];

        $existingUser = [
            'id' => 1,
            'email' => 'existing@example.com',
            'name' => 'Existing User'
        ];

        $this->mockUserRepository
            ->shouldReceive('findByEmail')
            ->with($userData['email'])
            ->once()
            ->andReturn($existingUser);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Email already exists');

        $this->userService->register($userData);
    }

    public function testRegisterWithShortPassword(): void
    {
        $userData = [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'short'
        ];

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Password must be at least 8 characters long');

        $this->userService->register($userData);
    }

    public function testPasswordNeedsRehash(): void
    {
        $oldHash = password_hash('testpassword', PASSWORD_BCRYPT, ['cost' => 10]);
        
        $this->assertTrue($this->userService->passwordNeedsRehash($oldHash));
    }

    public function testPasswordDoesNotNeedRehash(): void
    {
        $currentHash = password_hash('testpassword', PASSWORD_DEFAULT);
        
        $this->assertFalse($this->userService->passwordNeedsRehash($currentHash));
    }
}
