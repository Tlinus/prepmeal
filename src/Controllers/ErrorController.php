<?php

declare(strict_types=1);

namespace PrepMeal\Controllers;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

class ErrorController extends BaseController
{
    public function __construct(
        \PrepMeal\Core\Views\TwigView $view,
        \PrepMeal\Core\Services\TranslationService $translation
    ) {
        parent::__construct($view, $translation);
    }

    public function notFound(Request $request, Response $response): Response
    {
        $locale = $this->getDefaultLocale();
        
        $data = [
            'page_title' => $this->translation->translate('errors.404', $locale),
            'error_code' => 404,
            'error_message' => $this->translation->translate('errors.404', $locale)
        ];

        return $this->render($response, 'errors/404.twig', $data)
            ->withStatus(404);
    }

    public function serverError(Request $request, Response $response): Response
    {
        $locale = $this->getDefaultLocale();
        
        $data = [
            'page_title' => $this->translation->translate('errors.500', $locale),
            'error_code' => 500,
            'error_message' => $this->translation->translate('errors.500', $locale)
        ];

        return $this->render($response, 'errors/500.twig', $data)
            ->withStatus(500);
    }
}
