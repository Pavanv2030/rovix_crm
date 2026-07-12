<?php

namespace App\Filters;

use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;

/**
 * Applies RoleFilter only for mutating HTTP methods so viewers can still read pages.
 */
class WriteRoleFilter implements FilterInterface
{
    public function before(RequestInterface $request, $arguments = null)
    {
        if (!in_array($request->getMethod(), ['POST', 'PUT', 'DELETE', 'PATCH'], true)) {
            return;
        }

        return (new RoleFilter())->before($request, $arguments);
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null) {}
}