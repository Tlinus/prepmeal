<?php

declare(strict_types=1);

namespace PrepMeal\Controllers;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use PrepMeal\Core\Services\UserService;
use PrepMeal\Core\Services\CsrfService;

class AuthController extends BaseController
{
    private UserService $userService;
    private CsrfService $csrfService;

    public function __construct(
        \PrepMeal\Core\Views\TwigView $view,
        \PrepMeal\Core\Services\TranslationService $translation,
        UserService $userService,
        CsrfService $csrfService
    ) {
        parent::__construct($view, $translation);
        $this->userService = $userService;
        $this->csrfService = $csrfService;
    }

    public function loginForm(Request $request, Response $response): Response
    {
        if ($this->isAuthenticated()) {
            return $this->redirect($response, '/dashboard');
        }

        $data = [
            'page_title' => $this->translation->translate('navigation.login', $this->getDefaultLocale()),
            'flash_message' => $this->getFlashMessage(),
            'csrf_token' => $this->csrfService->getToken()
        ];

        return $this->render($response, 'auth/login.twig', $data);
    }

    public function login(Request $request, Response $response): Response
    {
        // Verify CSRF token
        if (!$this->csrfService->verifyPostToken()) {
            $this->setFlashMessage('error', 'Token de sécurité invalide. Veuillez réessayer.');
            return $this->redirect($response, '/login');
        }

        $rules = [
            'email' => 'required|email',
            'password' => 'required|min:6'
        ];

        $errors = $this->validateRequest($request, $rules);

        if (!empty($errors)) {
            $this->setFlashMessage('error', 'Veuillez corriger les erreurs dans le formulaire.');
            return $this->render($response, 'auth/login.twig', [
                'errors' => $errors,
                'old_data' => $request->getParsedBody(),
                'csrf_token' => $this->csrfService->getToken()
            ]);
        }

        $email = $this->getPostParam($request, 'email');
        $password = $this->getPostParam($request, 'password');

        try {
            // Authenticate user using UserService
            $user = $this->userService->authenticate($email, $password);
            
            if ($user) {
                $_SESSION['user'] = $user;
                $this->setFlashMessage('success', 'Connexion réussie !');
                return $this->redirect($response, '/dashboard');
            }

            $this->setFlashMessage('error', 'Email ou mot de passe incorrect.');
            return $this->render($response, 'auth/login.twig', [
                'errors' => ['email' => 'Email ou mot de passe incorrect.'],
                'old_data' => $request->getParsedBody(),
                'csrf_token' => $this->csrfService->getToken()
            ]);
        } catch (\Exception $e) {
            $this->setFlashMessage('error', 'Une erreur est survenue lors de la connexion.');
            return $this->render($response, 'auth/login.twig', [
                'errors' => ['email' => 'Une erreur est survenue lors de la connexion.'],
                'old_data' => $request->getParsedBody(),
                'csrf_token' => $this->csrfService->getToken()
            ]);
        }
    }

    public function registerForm(Request $request, Response $response): Response
    {
        if ($this->isAuthenticated()) {
            return $this->redirect($response, '/dashboard');
        }

        $data = [
            'page_title' => $this->translation->translate('navigation.register', $this->getDefaultLocale()),
            'flash_message' => $this->getFlashMessage(),
            'csrf_token' => $this->csrfService->getToken()
        ];

        return $this->render($response, 'auth/register.twig', $data);
    }

    public function register(Request $request, Response $response): Response
    {
        // Verify CSRF token
        if (!$this->csrfService->verifyPostToken()) {
            $this->setFlashMessage('error', 'Token de sécurité invalide. Veuillez réessayer.');
            return $this->redirect($response, '/register');
        }

        $rules = [
            'name' => 'required|min:2|max:50',
            'email' => 'required|email',
            'password' => 'required|min:8',
            'password_confirm' => 'required'
        ];

        $errors = $this->validateRequest($request, $rules);

        // Vérifier que les mots de passe correspondent
        $password = $this->getPostParam($request, 'password');
        $passwordConfirm = $this->getPostParam($request, 'password_confirm');
        
        if ($password !== $passwordConfirm) {
            $errors['password_confirm'] = 'Les mots de passe ne correspondent pas.';
        }

        if (!empty($errors)) {
            $this->setFlashMessage('error', 'Veuillez corriger les erreurs dans le formulaire.');
            return $this->render($response, 'auth/register.twig', [
                'errors' => $errors,
                'old_data' => $request->getParsedBody(),
                'csrf_token' => $this->csrfService->getToken()
            ]);
        }

        try {
            // Register user using UserService
            $userData = [
                'name' => $this->getPostParam($request, 'name'),
                'email' => $this->getPostParam($request, 'email'),
                'password' => $password
            ];

            $user = $this->userService->register($userData);
            
            // Log user in after successful registration
            $_SESSION['user'] = $user;
            $this->setFlashMessage('success', 'Inscription réussie ! Bienvenue sur PrepMeal.');
            return $this->redirect($response, '/dashboard');
            
        } catch (\InvalidArgumentException $e) {
            $this->setFlashMessage('error', $e->getMessage());
            return $this->render($response, 'auth/register.twig', [
                'errors' => ['email' => $e->getMessage()],
                'old_data' => $request->getParsedBody(),
                'csrf_token' => $this->csrfService->getToken()
            ]);
        } catch (\Exception $e) {
            $this->setFlashMessage('error', 'Une erreur est survenue lors de l\'inscription.');
            return $this->render($response, 'auth/register.twig', [
                'errors' => ['email' => 'Une erreur est survenue lors de l\'inscription.'],
                'old_data' => $request->getParsedBody(),
                'csrf_token' => $this->csrfService->getToken()
            ]);
        }
    }

    public function logout(Request $request, Response $response): Response
    {
        session_destroy();
        $this->setFlashMessage('success', 'Vous avez été déconnecté avec succès.');
        return $this->redirect($response, '/');
    }
}
