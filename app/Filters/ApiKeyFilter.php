<?php

namespace App\Filters;

use App\Models\AccountModel;
use App\Models\ProfileModel;
use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;

/**
 * Authenticates API requests via X-API-Key when no browser session exists.
 */
class ApiKeyFilter implements FilterInterface
{
    public function before(RequestInterface $request, $arguments = null)
    {
        if (session('user_id')) {
            return;
        }

        $apiKey = trim($request->getHeaderLine('X-API-Key'));
        if ($apiKey === '') {
            return;
        }

        AccountModel::setBypassAccountScope(true);
        $account = (new AccountModel())->where('api_key', $apiKey)->first();
        AccountModel::setBypassAccountScope(false);

        if (!$account) {
            return service('response')
                ->setStatusCode(401)
                ->setJSON(['error' => 'Invalid API key.']);
        }

        ProfileModel::setBypassAccountScope(true);
        $profile = (new ProfileModel())
            ->where('account_id', $account['id'])
            ->where('is_active', 1)
            ->orderBy('created_at', 'ASC')
            ->first();
        ProfileModel::setBypassAccountScope(false);

        if (!$profile) {
            return service('response')
                ->setStatusCode(401)
                ->setJSON(['error' => 'No active profile for this API key.']);
        }

        session()->set([
            'user_id'      => $profile['user_id'],
            'account_id'   => $profile['account_id'],
            'account_role' => $profile['account_role'],
            'full_name'    => $profile['full_name'],
            'email'        => $profile['email'],
            'avatar_url'   => $profile['avatar_url'] ?? null,
            'profile'      => $profile,
            'api_key_auth' => true,
        ]);
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null) {}
}