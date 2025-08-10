<?php

declare(strict_types=1);

namespace PrepMeal\Core\Services;

use PrepMeal\Core\Database\UserRepository;
use PrepMeal\Core\Database\DatabaseConnection;

class UserService
{
    private UserRepository $userRepository;
    private const PASSWORD_MIN_LENGTH = 8;

    public function __construct(UserRepository $userRepository)
    {
        $this->userRepository = $userRepository;
    }

    /**
     * Authenticate a user with email and password
     */
    public function authenticate(string $email, string $password): ?array
    {
        $user = $this->userRepository->findByEmail($email);
        
        if (!$user || !$this->verifyPassword($password, $user['password'])) {
            return null;
        }

        // Remove password from user data before returning
        unset($user['password']);
        return $user;
    }

    /**
     * Register a new user
     */
    public function register(array $userData): array
    {
        // Validate required fields
        if (empty($userData['email']) || empty($userData['password']) || empty($userData['name'])) {
            throw new \InvalidArgumentException('Email, password, and name are required');
        }

        // Check if email already exists
        if ($this->userRepository->findByEmail($userData['email'])) {
            throw new \InvalidArgumentException('Email already exists');
        }

        // Validate password strength
        if (strlen($userData['password']) < self::PASSWORD_MIN_LENGTH) {
            throw new \InvalidArgumentException('Password must be at least ' . self::PASSWORD_MIN_LENGTH . ' characters long');
        }

        // Hash password
        $userData['password'] = $this->hashPassword($userData['password']);

        // Create user
        $userId = $this->userRepository->create($userData);
        
        // Return user data without password
        $user = $this->userRepository->findById($userId);
        unset($user['password']);
        
        return $user;
    }

    /**
     * Hash a password using PHP's password_hash
     */
    public function hashPassword(string $password): string
    {
        return password_hash($password, PASSWORD_DEFAULT);
    }

    /**
     * Verify a password against its hash
     */
    public function verifyPassword(string $password, string $hash): bool
    {
        return password_verify($password, $hash);
    }

    /**
     * Check if a password needs to be rehashed
     */
    public function passwordNeedsRehash(string $hash): bool
    {
        return password_needs_rehash($hash, PASSWORD_DEFAULT);
    }

    /**
     * Update user password
     */
    public function updatePassword(int $userId, string $newPassword): bool
    {
        if (strlen($newPassword) < self::PASSWORD_MIN_LENGTH) {
            throw new \InvalidArgumentException('Password must be at least ' . self::PASSWORD_MIN_LENGTH . ' characters long');
        }

        $hashedPassword = $this->hashPassword($newPassword);
        
        return $this->userRepository->updatePassword($userId, $hashedPassword);
    }

    /**
     * Get user by ID
     */
    public function getUserById(int $id): ?array
    {
        $user = $this->userRepository->findById($id);
        if ($user) {
            unset($user['password']);
        }
        return $user;
    }

    /**
     * Get user by email
     */
    public function getUserByEmail(string $email): ?array
    {
        $user = $this->userRepository->findByEmail($email);
        if ($user) {
            unset($user['password']);
        }
        return $user;
    }

    /**
     * Update user profile
     */
    public function updateProfile(int $userId, array $userData): bool
    {
        // Don't allow password updates through this method
        unset($userData['password']);
        
        return $this->userRepository->update($userId, $userData);
    }

    /**
     * Delete user account
     */
    public function deleteUser(int $userId): bool
    {
        return $this->userRepository->delete($userId);
    }
}
