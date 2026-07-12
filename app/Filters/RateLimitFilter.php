<?php

namespace App\Filters;

use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;

class RateLimitFilter implements FilterInterface
{
    private const MAX_ATTEMPTS = 5;
    private const WINDOW_SEC = 300; // 5 minutes

    public function before(RequestInterface $request, $arguments = null)
    {
        $ip = $request->getIPAddress();
        $path = $request->getPath();
        $key = 'rate_limit_' . md5($ip . $path);

        $cache = \Config\Services::cache();
        $attempts = (int)$cache->get($key);

        if ($attempts >= self::MAX_ATTEMPTS) {
            log_message('warning', "Rate limit exceeded for IP {$ip} on path {$path}");
            return service('response')
                ->setStatusCode(429)
                ->setJSON(['error' => 'Too many attempts. Try again in 5 minutes.']);
        }

        $cache->save($key, $attempts + 1, self::WINDOW_SEC);
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null) {}
}
