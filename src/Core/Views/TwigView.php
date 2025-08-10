<?php

declare(strict_types=1);

namespace PrepMeal\Core\Views;

use Psr\Http\Message\ResponseInterface;
use Twig\Environment;

class TwigView
{
    private Environment $twig;

    public function __construct(Environment $twig)
    {
        $this->twig = $twig;
    }

    public function render(ResponseInterface $response, string $template, array $data = []): ResponseInterface
    {
        $html = $this->twig->render($template, $data);
        $response->getBody()->write($html);
        return $response->withHeader('Content-Type', 'text/html; charset=utf-8');
    }
}

