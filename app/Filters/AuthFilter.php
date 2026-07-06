<?php

namespace App\Filters;

use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;

class AuthFilter implements FilterInterface
{
    private array $publicRoutes = [
        'login',
        'signup',
        'forgot-password',
        'api/whatsapp/webhook',
    ];

    public function before(RequestInterface $request, $arguments = null)
    {
        $path = trim(uri_string(), '/');

        // Always allow join/* and team/accept/* routes (unauthenticated)
        if (str_starts_with($path, 'join/') || $path === 'join') return;
        if (str_starts_with($path, 'team/accept')) return;

        // Always allow public routes
        foreach ($this->publicRoutes as $route) {
            if ($path === $route || str_starts_with($path, $route)) {
                if (in_array($path, ['login', 'signup']) && session('user_id')) {
                    return redirect()->to(base_url('dashboard'));
                }
                return;
            }
        }

        // Require authentication
        if (!session('user_id')) {
            return redirect()->to(base_url('login'));
        }

        // Block inactive accounts
        if (session('profile') && isset(session('profile')['is_active']) && !session('profile')['is_active']) {
            session()->destroy();
            return redirect()->to(base_url('login'))->with('error', 'Your account has been deactivated. Contact an admin.');
        }

        // Update last_seen_at (throttled: once per 5 minutes max)
        $lastSeen = session('last_seen_updated_at') ?? 0;
        if ((time() - $lastSeen) > 300) {
            try {
                $profileModel = new \App\Models\ProfileModel();
                $profileModel->where('user_id', session('user_id'))->set(['last_seen_at' => date('Y-m-d H:i:s')])->update();
                session()->set('last_seen_updated_at', time());
            } catch (\Throwable $e) {
                // non-fatal
            }
        }
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null) {}
}
