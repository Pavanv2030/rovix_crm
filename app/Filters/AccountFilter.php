<?php

namespace App\Filters;

use App\Models\ProfileModel;
use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;

class AccountFilter implements FilterInterface
{
    private array $skipRoutes = [
        'login',
        'signup',
        'forgot-password',
        'reset-password',
        'media/template',
        'api/whatsapp/webhook',
        'join',
    ];

    public function before(RequestInterface $request, $arguments = null)
    {
        $path   = trim(uri_string(), '/');
        $userId = session('user_id');

        if (!$userId) {
            return;
        }

        foreach ($this->skipRoutes as $route) {
            if ($path === $route || str_starts_with($path, $route)) {
                return;
            }
        }

        ProfileModel::setBypassAccountScope(true);
        $profile = (new ProfileModel())->where('user_id', $userId)->first();
        ProfileModel::setBypassAccountScope(false);

        if (!$profile) {
            session()->destroy();
            return redirect()->to(base_url('login'));
        }

        if (empty($profile['is_active'])) {
            session()->destroy();
            return redirect()->to(base_url('login'))->with('error', 'Your account has been deactivated. Contact an admin.');
        }

        session()->set([
            'account_id'   => $profile['account_id'],
            'account_role' => $profile['account_role'],
            'full_name'    => $profile['full_name'],
            'email'        => $profile['email'],
            'avatar_url'   => $profile['avatar_url'] ?? null,
            'profile'      => $profile,
        ]);
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null) {}
}
