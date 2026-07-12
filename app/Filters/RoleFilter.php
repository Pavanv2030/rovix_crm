<?php

namespace App\Filters;

use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;

class RoleFilter implements FilterInterface
{
    public function before(RequestInterface $request, $arguments = null)
    {
        $requiredRole = $arguments[0] ?? 'viewer';
        $currentRole  = session('account_role') ?? 'viewer';

        if (role_rank($currentRole) < role_rank($requiredRole)) {
            $path = trim(uri_string(), '/');
            if ($request->isAJAX() || str_starts_with($path, 'api/')) {
                return service('response')
                    ->setStatusCode(403)
                    ->setJSON(['error' => 'Access denied: insufficient permissions.']);
            }

            return service('response')
                ->setStatusCode(403)
                ->setBody('Access Denied: Insufficient permissions.');
        }
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null) {}
}
