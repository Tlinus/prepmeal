<?php

declare(strict_types=1);

namespace PrepMeal\Controllers;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

class TestController
{
    public function index(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $response->getBody()->write(json_encode([
            'status' => 'success',
            'message' => 'PrepMeal application is running!',
            'timestamp' => date('Y-m-d H:i:s'),
            'php_version' => PHP_VERSION,
            'environment' => $_ENV['APP_ENV'] ?? 'development'
        ]));
        
        return $response->withHeader('Content-Type', 'application/json');
    }
}
