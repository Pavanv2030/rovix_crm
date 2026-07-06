<?php

namespace App\Filters;

use App\Libraries\WhatsApp\WebhookSignature;
use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;

class WebhookSignatureFilter implements FilterInterface
{
    public function before(RequestInterface $request, $arguments = null)
    {
        $rawBody   = file_get_contents('php://input');
        $signature = $request->getHeaderLine('X-Hub-Signature-256');
        $appSecret = config('WhatsApp')->metaAppSecret;

        if (empty($appSecret)) {
            log_message('error', 'Webhook: META_APP_SECRET not configured');
            return service('response')->setStatusCode(403)->setJSON(['error' => 'Webhook not configured']);
        }

        if (!WebhookSignature::verify($rawBody, $signature, $appSecret)) {
            log_message('error', 'Webhook: invalid signature');
            return service('response')->setStatusCode(403)->setJSON(['error' => 'Invalid signature']);
        }
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null) {}
}
