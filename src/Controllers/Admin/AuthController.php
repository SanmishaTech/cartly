<?php

namespace App\Controllers\Admin;

use App\Models\User;
use App\Services\AuthorizationService;
use App\Services\FlashService;
use App\Services\MailService;
use App\Services\PasswordResetService;
use Cartly\Utilities\AuthLogger;
use Slim\Psr7\Response;
use Valitron\Validator;

class AuthController extends AppController
{
    /**
     * Start PHP session if not already started
     */
    private function ensureSession(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
    }

    /**
     * Show admin/root login form (NO OAuth - secure only)
     */
    public function adminLoginForm($request, Response $response): Response
    {
        $this->ensureSession();
        $shop = $request->getAttribute('shop');
        $errors = FlashService::get('errors', []);
        $old = FlashService::get('old', []);
        $errorMessage = FlashService::get('error', null);
        $email = $old['user']['email'] ?? ($_SESSION['last_login_email'] ?? null);
        
        // Prepare session data to pass to view (for flash_toasts.twig)
        $sessionData = [
            'shop' => $shop,
            'is_admin' => true,
            'session' => [
                'flash_error' => $_SESSION['flash_error'] ?? null,
                'flash_success' => $_SESSION['flash_success'] ?? null,
                'flash_info' => $_SESSION['flash_info'] ?? null,
                'flash_warning' => $_SESSION['flash_warning'] ?? null,
                'last_login_email' => $_SESSION['last_login_email'] ?? null,
            ],
            'errors' => $errors,
            'error' => $errorMessage,
            'email' => $email,
            'data' => $old,
        ];
        
        // Clear one-time flash messages
        unset($_SESSION['flash_error']);
        unset($_SESSION['flash_success']);
        unset($_SESSION['flash_info']);
        unset($_SESSION['flash_warning']);
        
        return $this->view->render($response, 'auth/login.twig', $sessionData);
    }

    /**
     * Handle admin/root login submission (form-based POST)
     * NO OAuth - secure email/password only
     */
    public function adminLogin($request, Response $response): Response
    {
        $this->ensureSession();
        $params = $request->getParsedBody();
        $userData = $params['user'] ?? [];
        $email = $userData['email'] ?? '';
        $password = $userData['password'] ?? '';
        $remember = $params['remember'] ?? false;
        
        // Validate input
        $validator = new Validator($params);
        $validator->rule('required', 'user.email')->message('Email is required.');
        $validator->rule('required', 'user.password')->message('Password is required.');
        $validator->rule('email', 'user.email')->message('Enter a valid email address.');
        $errors = $validator->validate() ? [] : $this->formatValitronErrors($validator->errors());
        
        if (!empty($errors)) {
            FlashService::set('errors', $errors);
            FlashService::set('old', ['user' => ['email' => $email]]);
            return $response
                ->withStatus(302)
                ->withHeader('Location', '/admin/login');
        }
        
        // Find user by email
        $user = User::where('email', $email)->first();
        
        if (!$user || !$user->verifyPassword($password)) {
            // Use session-backed flash for cross-page toast
            $_SESSION['flash_error'] = 'Invalid email or password.';
            // Optional: preserve entered email for convenience
            $_SESSION['last_login_email'] = $email;
            FlashService::set('old', ['user' => ['email' => $email]]);
            return $response
                ->withStatus(302)
                ->withHeader('Location', '/admin/login');
        }
        
        // Check if user can access admin area
        $authorization = new AuthorizationService();
        if (!$authorization->roleHasPermission($user->role, AuthorizationService::PERMISSION_DASHBOARD_ACCESS)) {
            FlashService::set('error', 'This login is for administrators only. Please use shopper login.');
            FlashService::set('old', ['user' => ['email' => $email]]);
            return $response
                ->withStatus(302)
                ->withHeader('Location', '/admin/login');
        }
        
        // Check if user is active
        if ($user->status !== 'active') {
            FlashService::set('error', 'Your account is not active. Please contact support.');
            FlashService::set('old', ['user' => ['email' => $email]]);
            return $response
                ->withStatus(302)
                ->withHeader('Location', '/admin/login');
        }
        
        // Set session
        $_SESSION['user_id'] = $user->id;
        $_SESSION['user_email'] = $user->email;
        $_SESSION['user_name'] = $user->name;
        $_SESSION['user_role'] = $user->role;
        $_SESSION['shop_id'] = $user->shop_id;

        if (function_exists('session_regenerate_id')) {
            session_regenerate_id(true);
        }
        
        // Handle "Keep me signed in" - extend session lifetime
        if ($remember) {
            // Set session cookie to expire in 30 days (instead of browser close)
            $sessionName = session_name();
            $sessionId = session_id();
            setcookie(
                $sessionName,
                $sessionId,
                time() + (30 * 24 * 60 * 60), // 30 days
                '/',
                '',
                isset($_SERVER['HTTPS']), // secure if HTTPS
                true // httponly
            );
        }
        
        // Update last login
        $user->last_login_at = date('Y-m-d H:i:s');
        $user->save();
        
        // Determine redirect URL based on role
        $redirectUrl = match($user->role) {
            'root', 'admin', 'helpdesk', 'operations' => '/admin/dashboard',
            default => '/admin/dashboard'
        };
        
        return $response
            ->withStatus(302)
            ->withHeader('Location', $redirectUrl);
    }

    /**
     * Show forgot password form
     */
    public function adminForgotPasswordForm($request, Response $response): Response
    {
        $this->ensureSession();
        $shop = $request->getAttribute('shop');
        $errors = FlashService::get('errors', []);
        $old = FlashService::get('old', []);
        $errorMessage = FlashService::get('error', null);

        $sessionData = [
            'shop' => $shop,
            'is_admin' => true,
            'session' => [
                'flash_error' => $_SESSION['flash_error'] ?? null,
                'flash_success' => $_SESSION['flash_success'] ?? null,
                'flash_info' => $_SESSION['flash_info'] ?? null,
                'flash_warning' => $_SESSION['flash_warning'] ?? null,
                'last_login_email' => $_SESSION['last_login_email'] ?? null,
            ],
            'errors' => $errors,
            'error' => $errorMessage,
            'data' => $old,
        ];

        unset($_SESSION['flash_error']);
        unset($_SESSION['flash_success']);
        unset($_SESSION['flash_info']);
        unset($_SESSION['flash_warning']);

        return $this->view->render($response, 'auth/forgot_password.twig', $sessionData);
    }

    /**
     * Handle forgot password submission
     */
    public function adminForgotPassword($request, Response $response): Response
    {
        $this->ensureSession();
        $params = $request->getParsedBody();
        $userData = $params['user'] ?? [];
        $email = trim((string)($userData['email'] ?? ''));

        $validator = new Validator($params);
        $validator->rule('required', 'user.email')->message('Email is required.');
        $validator->rule('email', 'user.email')->message('Enter a valid email address.');
        $errors = $validator->validate() ? [] : $this->formatValitronErrors($validator->errors());

        if (!empty($errors)) {
            FlashService::set('errors', $errors);
            FlashService::set('old', ['user' => ['email' => $email]]);
            return $response
                ->withStatus(302)
                ->withHeader('Location', '/admin/forgot-password');
        }

        $user = User::where('email', $email)->first();
        if ($user && $user->status === 'active') {
            $resetService = new PasswordResetService();
            $token = $resetService->createForUser($user);
            $resetUrl = $this->buildResetUrl($request, $token);

            $mailService = new MailService();
            $subject = 'Reset your Cartly password';
            $htmlBody = $this->buildResetEmailHtml($user->name, $resetUrl);
            $textBody = $this->buildResetEmailText($user->name, $resetUrl);
            $mailService->send($user->email, $user->name, $subject, $htmlBody, $textBody);

            $logger = new AuthLogger();
            $logger->logPasswordResetRequested(
                $user->email,
                $request->getServerParams()['REMOTE_ADDR'] ?? 'Unknown'
            );
        }

        $_SESSION['flash_success'] = 'If an account exists for that email, a reset link has been sent.';

        return $response
            ->withStatus(302)
            ->withHeader('Location', '/admin/forgot-password');
    }

    /**
     * Show reset password form
     */
    public function adminResetPasswordForm($request, Response $response): Response
    {
        $this->ensureSession();
        $token = (string)($request->getQueryParams()['token'] ?? '');
        $resetService = new PasswordResetService();
        $reset = $resetService->getValidReset($token);

        if (!$reset) {
            $_SESSION['flash_error'] = 'Reset link is invalid or expired.';
            return $response
                ->withStatus(302)
                ->withHeader('Location', '/admin/forgot-password');
        }

        $shop = $request->getAttribute('shop');
        $errors = FlashService::get('errors', []);
        $old = FlashService::get('old', []);
        $errorMessage = FlashService::get('error', null);

        $sessionData = [
            'shop' => $shop,
            'is_admin' => true,
            'session' => [
                'flash_error' => $_SESSION['flash_error'] ?? null,
                'flash_success' => $_SESSION['flash_success'] ?? null,
                'flash_info' => $_SESSION['flash_info'] ?? null,
                'flash_warning' => $_SESSION['flash_warning'] ?? null,
                'last_login_email' => $_SESSION['last_login_email'] ?? null,
            ],
            'errors' => $errors,
            'error' => $errorMessage,
            'data' => $old,
            'token' => $token,
        ];

        unset($_SESSION['flash_error']);
        unset($_SESSION['flash_success']);
        unset($_SESSION['flash_info']);
        unset($_SESSION['flash_warning']);

        return $this->view->render($response, 'auth/reset_password.twig', $sessionData);
    }

    /**
     * Handle reset password submission
     */
    public function adminResetPassword($request, Response $response): Response
    {
        $this->ensureSession();
        $params = $request->getParsedBody();
        $userData = $params['user'] ?? [];
        $token = (string)($params['token'] ?? '');
        $password = (string)($userData['password'] ?? '');
        $passwordConfirm = (string)($userData['password_confirm'] ?? '');

        $validator = new Validator($params);
        $validator->rule('required', 'user.password')->message('Password is required.');
        $validator->rule('lengthMin', 'user.password', 6)->message('Password must be at least 6 characters.');
        $validator->rule('required', 'user.password_confirm')->message('Confirm password is required.');
        $errors = $validator->validate() ? [] : $this->formatValitronErrors($validator->errors());

        if ($password !== $passwordConfirm) {
            $errors['user.password_confirm'] = 'Passwords do not match.';
        }

        if (!empty($errors)) {
            FlashService::set('errors', $errors);
            return $response
                ->withStatus(302)
                ->withHeader('Location', '/admin/reset-password?token=' . urlencode($token));
        }

        $resetService = new PasswordResetService();
        $reset = $resetService->getValidReset($token);
        if (!$reset || !$reset->user || $reset->user->status !== 'active') {
            $_SESSION['flash_error'] = 'Reset link is invalid or expired.';
            return $response
                ->withStatus(302)
                ->withHeader('Location', '/admin/forgot-password');
        }

        $user = $reset->user;
        $user->password = $password;
        $user->save();
        $resetService->markUsed($reset);

        $logger = new AuthLogger();
        $logger->logPasswordChanged(
            $user->id,
            $user->email
        );

        $_SESSION['flash_success'] = 'Your password has been updated. Please log in.';

        return $response
            ->withStatus(302)
            ->withHeader('Location', '/admin/login');
    }

    /**
     * Helper method to return JSON response
     */
    private function jsonResponse(Response $response, array $data, int $status = 200): Response
    {
        $response = $response->withStatus($status);
        $response->getBody()->write(json_encode($data));
        return $response->withHeader('Content-Type', 'application/json');
    }

    /**
     * Logout user
     */
    public function logout($request, Response $response): Response
    {
        $this->ensureSession();
        
        // Clear user-specific session data but keep session for flash
        unset($_SESSION['user_id'], $_SESSION['user_email'], $_SESSION['user_name'], $_SESSION['user_role'], $_SESSION['shop_id']);
        
        // Add a success flash message
        $_SESSION['flash_success'] = 'You have been logged out.';
        
        // Optionally regenerate session id for safety
        if (function_exists('session_regenerate_id')) {
            session_regenerate_id(true);
        }
        
        return $response
            ->withStatus(302)
            ->withHeader('Location', '/');
    }

    private function buildResetUrl($request, string $token): string
    {
        $appUrl = $_ENV['APP_URL'] ?? getenv('APP_URL');
        if ($appUrl) {
            return rtrim($appUrl, '/') . '/admin/reset-password?token=' . urlencode($token);
        }

        $uri = $request->getUri();
        $scheme = $uri->getScheme();
        $host = $uri->getHost();
        $port = $uri->getPort();

        $base = $scheme . '://' . $host;
        if ($port && !in_array($port, [80, 443], true)) {
            $base .= ':' . $port;
        }

        return $base . '/admin/reset-password?token=' . urlencode($token);
    }

    private function buildResetEmailHtml(string $name, string $resetUrl): string
    {
        $safeName = htmlspecialchars($name !== '' ? $name : 'there', ENT_QUOTES, 'UTF-8');
        $safeUrl = htmlspecialchars($resetUrl, ENT_QUOTES, 'UTF-8');

        return '<p>Hi ' . $safeName . ',</p>'
            . '<p>You requested a password reset for your Cartly account.</p>'
            . '<p><a href="' . $safeUrl . '">Reset your password</a></p>'
            . '<p>If you did not request this, you can ignore this email.</p>';
    }

    private function buildResetEmailText(string $name, string $resetUrl): string
    {
        $greetingName = $name !== '' ? $name : 'there';

        return "Hi {$greetingName},\n\n"
            . "You requested a password reset for your Cartly account.\n"
            . "Reset your password: {$resetUrl}\n\n"
            . "If you did not request this, you can ignore this email.\n";
    }
}
