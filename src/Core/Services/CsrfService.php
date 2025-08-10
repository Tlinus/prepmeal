<?php

declare(strict_types=1);

namespace PrepMeal\Core\Services;

class CsrfService
{
    private const TOKEN_LENGTH = 32;
    private const SESSION_KEY = 'csrf_tokens';

    /**
     * Generate a new CSRF token
     */
    public function generateToken(): string
    {
        if (!isset($_SESSION[self::SESSION_KEY])) {
            $_SESSION[self::SESSION_KEY] = [];
        }

        $token = bin2hex(random_bytes(self::TOKEN_LENGTH));
        $_SESSION[self::SESSION_KEY][$token] = time();

        // Clean up old tokens (older than 1 hour)
        $this->cleanupOldTokens();

        return $token;
    }

    /**
     * Verify a CSRF token
     */
    public function verifyToken(string $token): bool
    {
        if (!isset($_SESSION[self::SESSION_KEY][$token])) {
            return false;
        }

        // Check if token is not expired (1 hour)
        if (time() - $_SESSION[self::SESSION_KEY][$token] > 3600) {
            unset($_SESSION[self::SESSION_KEY][$token]);
            return false;
        }

        // Remove used token
        unset($_SESSION[self::SESSION_KEY][$token]);
        return true;
    }

    /**
     * Get the current CSRF token (generate if doesn't exist)
     */
    public function getToken(): string
    {
        if (empty($_SESSION[self::SESSION_KEY])) {
            return $this->generateToken();
        }

        // Return the first available token
        $tokens = array_keys($_SESSION[self::SESSION_KEY]);
        return reset($tokens);
    }

    /**
     * Clean up expired tokens
     */
    private function cleanupOldTokens(): void
    {
        if (!isset($_SESSION[self::SESSION_KEY])) {
            return;
        }

        $currentTime = time();
        foreach ($_SESSION[self::SESSION_KEY] as $token => $timestamp) {
            if ($currentTime - $timestamp > 3600) {
                unset($_SESSION[self::SESSION_KEY][$token]);
            }
        }
    }

    /**
     * Get HTML input field for CSRF token
     */
    public function getTokenField(): string
    {
        $token = $this->getToken();
        return sprintf('<input type="hidden" name="_token" value="%s">', htmlspecialchars($token));
    }

    /**
     * Verify token from POST data
     */
    public function verifyPostToken(): bool
    {
        $token = $_POST['_token'] ?? '';
        return $this->verifyToken($token);
    }
}
