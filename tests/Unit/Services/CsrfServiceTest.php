<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use PHPUnit\Framework\TestCase;
use PrepMeal\Core\Services\CsrfService;

class CsrfServiceTest extends TestCase
{
    private CsrfService $csrfService;

    protected function setUp(): void
    {
        // Start session for testing
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        $this->csrfService = new CsrfService();
        
        // Clear any existing CSRF tokens
        unset($_SESSION['csrf_tokens']);
    }

    protected function tearDown(): void
    {
        // Clean up session
        unset($_SESSION['csrf_tokens']);
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_destroy();
        }
    }

    public function testGenerateTokenCreatesValidToken(): void
    {
        $token = $this->csrfService->generateToken();

        $this->assertIsString($token);
        $this->assertEquals(64, strlen($token)); // 32 bytes = 64 hex characters
        $this->assertArrayHasKey($token, $_SESSION['csrf_tokens']);
    }

    public function testVerifyTokenWithValidToken(): void
    {
        $token = $this->csrfService->generateToken();
        
        $this->assertTrue($this->csrfService->verifyToken($token));
        $this->assertArrayNotHasKey($token, $_SESSION['csrf_tokens']); // Token should be consumed
    }

    public function testVerifyTokenWithInvalidToken(): void
    {
        $this->assertFalse($this->csrfService->verifyToken('invalid_token'));
    }

    public function testVerifyTokenWithExpiredToken(): void
    {
        $token = $this->csrfService->generateToken();
        
        // Simulate expired token by setting timestamp to 2 hours ago
        $_SESSION['csrf_tokens'][$token] = time() - 7200;
        
        $this->assertFalse($this->csrfService->verifyToken($token));
        $this->assertArrayNotHasKey($token, $_SESSION['csrf_tokens']); // Expired token should be removed
    }

    public function testGetTokenReturnsExistingToken(): void
    {
        $token1 = $this->csrfService->generateToken();
        $token2 = $this->csrfService->getToken();
        
        $this->assertEquals($token1, $token2);
    }

    public function testGetTokenGeneratesNewTokenWhenNoneExists(): void
    {
        $token = $this->csrfService->getToken();
        
        $this->assertIsString($token);
        $this->assertEquals(64, strlen($token));
        $this->assertArrayHasKey($token, $_SESSION['csrf_tokens']);
    }

    public function testGetTokenFieldReturnsValidHtml(): void
    {
        $token = $this->csrfService->generateToken();
        $field = $this->csrfService->getTokenField();
        
        $expectedField = sprintf('<input type="hidden" name="_token" value="%s">', $token);
        $this->assertEquals($expectedField, $field);
    }

    public function testVerifyPostTokenWithValidToken(): void
    {
        $token = $this->csrfService->generateToken();
        $_POST['_token'] = $token;
        
        $this->assertTrue($this->csrfService->verifyPostToken());
        $this->assertArrayNotHasKey($token, $_SESSION['csrf_tokens']); // Token should be consumed
    }

    public function testVerifyPostTokenWithInvalidToken(): void
    {
        $_POST['_token'] = 'invalid_token';
        
        $this->assertFalse($this->csrfService->verifyPostToken());
    }

    public function testVerifyPostTokenWithMissingToken(): void
    {
        unset($_POST['_token']);
        
        $this->assertFalse($this->csrfService->verifyPostToken());
    }

    public function testMultipleTokensCanBeGenerated(): void
    {
        $token1 = $this->csrfService->generateToken();
        $token2 = $this->csrfService->generateToken();
        
        $this->assertNotEquals($token1, $token2);
        $this->assertArrayHasKey($token1, $_SESSION['csrf_tokens']);
        $this->assertArrayHasKey($token2, $_SESSION['csrf_tokens']);
    }

    public function testTokenCleanupRemovesExpiredTokens(): void
    {
        // Generate a token and make it expired
        $token = $this->csrfService->generateToken();
        $_SESSION['csrf_tokens'][$token] = time() - 7200; // 2 hours ago
        
        // Generate a new token to trigger cleanup
        $newToken = $this->csrfService->generateToken();
        
        $this->assertArrayNotHasKey($token, $_SESSION['csrf_tokens']); // Expired token should be removed
        $this->assertArrayHasKey($newToken, $_SESSION['csrf_tokens']); // New token should exist
    }

    public function testTokenLengthIsCorrect(): void
    {
        $token = $this->csrfService->generateToken();
        
        // 32 bytes = 64 hex characters
        $this->assertEquals(64, strlen($token));
    }
}
