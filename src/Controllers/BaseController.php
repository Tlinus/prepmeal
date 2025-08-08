<?php

declare(strict_types=1);

namespace PrepMeal\Controllers;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Slim\Views\Twig;
use PrepMeal\Core\Services\TranslationService;

abstract class BaseController
{
    protected Twig $view;
    protected TranslationService $translation;

    public function __construct(Twig $view, TranslationService $translation)
    {
        $this->view = $view;
        $this->translation = $translation;
    }

    protected function render(ResponseInterface $response, string $template, array $data = []): ResponseInterface
    {
        // Ajouter les données communes à toutes les vues
        $data['locale'] = $this->getLocale();
        $data['translations'] = $this->getCommonTranslations();
        $data['current_user'] = $this->getCurrentUser();
        $data['seasons'] = $this->translation->getSeasons($data['locale']);
        $data['diet_types'] = $this->translation->getDietTypes($data['locale']);
        $data['allergens'] = $this->translation->getAllergens($data['locale']);

        return $this->view->render($response, $template, $data);
    }

    protected function json(ResponseInterface $response, array $data, int $status = 200): ResponseInterface
    {
        $response->getBody()->write(json_encode($data, JSON_UNESCAPED_UNICODE));
        return $response
            ->withHeader('Content-Type', 'application/json')
            ->withStatus($status);
    }

    protected function redirect(ResponseInterface $response, string $url, int $status = 302): ResponseInterface
    {
        return $response
            ->withHeader('Location', $url)
            ->withStatus($status);
    }

    protected function getLocale(): string
    {
        // Détecter la locale depuis la session ou les paramètres
        return $_SESSION['locale'] ?? 'fr';
    }

    protected function getCurrentUser(): ?array
    {
        return $_SESSION['user'] ?? null;
    }

    protected function isAuthenticated(): bool
    {
        return isset($_SESSION['user']);
    }

    protected function requireAuth(ServerRequestInterface $request, ResponseInterface $response): ?ResponseInterface
    {
        if (!$this->isAuthenticated()) {
            return $this->redirect($response, '/login');
        }
        
        return null;
    }

    protected function getCommonTranslations(): array
    {
        $locale = $this->getLocale();
        
        return [
            'common' => [
                'loading' => $this->translation->translate('common.loading', $locale),
                'error' => $this->translation->translate('common.error', $locale),
                'success' => $this->translation->translate('common.success', $locale),
                'cancel' => $this->translation->translate('common.cancel', $locale),
                'save' => $this->translation->translate('common.save', $locale),
                'delete' => $this->translation->translate('common.delete', $locale),
                'edit' => $this->translation->translate('common.edit', $locale),
                'add' => $this->translation->translate('common.add', $locale),
                'search' => $this->translation->translate('common.search', $locale),
                'filter' => $this->translation->translate('common.filter', $locale),
                'sort' => $this->translation->translate('common.sort', $locale),
                'export' => $this->translation->translate('common.export', $locale),
                'import' => $this->translation->translate('common.import', $locale),
                'print' => $this->translation->translate('common.print', $locale),
                'download' => $this->translation->translate('common.download', $locale),
                'upload' => $this->translation->translate('common.upload', $locale),
                'yes' => $this->translation->translate('common.yes', $locale),
                'no' => $this->translation->translate('common.no', $locale),
                'back' => $this->translation->translate('common.back', $locale),
                'next' => $this->translation->translate('common.next', $locale),
                'previous' => $this->translation->translate('common.previous', $locale),
                'close' => $this->translation->translate('common.close', $locale),
                'open' => $this->translation->translate('common.open', $locale),
                'select' => $this->translation->translate('common.select', $locale),
                'all' => $this->translation->translate('common.all', $locale),
                'none' => $this->translation->translate('common.none', $locale),
                'required' => $this->translation->translate('common.required', $locale),
                'optional' => $this->translation->translate('common.optional', $locale)
            ],
            'navigation' => [
                'home' => $this->translation->translate('navigation.home', $locale),
                'recipes' => $this->translation->translate('navigation.recipes', $locale),
                'meal_planning' => $this->translation->translate('navigation.meal_planning', $locale),
                'favorites' => $this->translation->translate('navigation.favorites', $locale),
                'profile' => $this->translation->translate('navigation.profile', $locale),
                'settings' => $this->translation->translate('navigation.settings', $locale),
                'subscription' => $this->translation->translate('navigation.subscription', $locale),
                'logout' => $this->translation->translate('navigation.logout', $locale),
                'login' => $this->translation->translate('navigation.login', $locale),
                'register' => $this->translation->translate('navigation.register', $locale)
            ]
        ];
    }

    protected function validateRequest(ServerRequestInterface $request, array $rules): array
    {
        $data = $request->getParsedBody() ?? [];
        $errors = [];

        foreach ($rules as $field => $rule) {
            $value = $data[$field] ?? null;
            
            if (strpos($rule, 'required') !== false && empty($value)) {
                $errors[$field] = $this->translation->translate('validation.required', $this->getLocale());
                continue;
            }

            if (!empty($value)) {
                if (strpos($rule, 'email') !== false && !filter_var($value, FILTER_VALIDATE_EMAIL)) {
                    $errors[$field] = $this->translation->translate('validation.email', $this->getLocale());
                }

                if (preg_match('/min:(\d+)/', $rule, $matches)) {
                    $min = (int) $matches[1];
                    if (strlen($value) < $min) {
                        $errors[$field] = $this->translation->translate('validation.min_length', $this->getLocale(), ['min' => $min]);
                    }
                }

                if (preg_match('/max:(\d+)/', $rule, $matches)) {
                    $max = (int) $matches[1];
                    if (strlen($value) > $max) {
                        $errors[$field] = $this->translation->translate('validation.max_length', $this->getLocale(), ['max' => $max]);
                    }
                }

                if (strpos($rule, 'numeric') !== false && !is_numeric($value)) {
                    $errors[$field] = $this->translation->translate('validation.numeric', $this->getLocale());
                }

                if (strpos($rule, 'integer') !== false && !filter_var($value, FILTER_VALIDATE_INT)) {
                    $errors[$field] = $this->translation->translate('validation.integer', $this->getLocale());
                }
            }
        }

        return $errors;
    }

    protected function getQueryParam(ServerRequestInterface $request, string $param, $default = null)
    {
        $queryParams = $request->getQueryParams();
        return $queryParams[$param] ?? $default;
    }

    protected function getPostParam(ServerRequestInterface $request, string $param, $default = null)
    {
        $parsedBody = $request->getParsedBody();
        return $parsedBody[$param] ?? $default;
    }

    protected function setFlashMessage(string $type, string $message): void
    {
        $_SESSION['flash'] = [
            'type' => $type,
            'message' => $message
        ];
    }

    protected function getFlashMessage(): ?array
    {
        $flash = $_SESSION['flash'] ?? null;
        unset($_SESSION['flash']);
        return $flash;
    }
}
