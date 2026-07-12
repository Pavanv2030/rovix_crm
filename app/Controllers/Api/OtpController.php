<?php

namespace App\Controllers\Api;

use App\Controllers\BaseController;
use App\Models\OtpVerificationModel;
use App\Services\WhatsappOtpService;

class OtpController extends BaseController
{
    /**
     * POST /api/otp/send
     * Body: { "phone": "91XXXXXXXXXX" }
     */
    public function send(): \CodeIgniter\HTTP\ResponseInterface
    {
        $phone = trim((string) ($this->request->getJSON(true)['phone'] ?? $this->request->getPost('phone') ?? ''));

        if (empty($phone)) {
            return $this->jsonError('phone is required.', 400);
        }

        // Normalise: strip spaces/dashes/plus
        $phone = preg_replace('/[\s\-+]/', '', $phone);

        if (!preg_match('/^\d{10,15}$/', $phone)) {
            return $this->jsonError('Invalid phone number format. Use digits only, 10–15 chars (e.g. 919876543210).', 422);
        }

        // Rate limit: one OTP per 60 seconds per number
        $model  = new OtpVerificationModel();
        $recent = $model->where('phone_number', $phone)
            ->where('created_at >=', date('Y-m-d H:i:s', strtotime('-60 seconds')))
            ->first();

        if ($recent) {
            $wait = 60 - (time() - strtotime($recent['created_at']));
            return $this->jsonError("Please wait {$wait} second(s) before requesting another OTP.", 429);
        }

        $accountId = session('account_id');
        if (!$accountId) {
            return $this->jsonError('Authentication required.', 401);
        }

        $service = new WhatsappOtpService();
        $result  = $service->sendOtp($phone, $accountId);

        if (!$result['success']) {
            return $this->jsonError($result['message'], 500);
        }

        return $this->response->setJSON(['success' => true, 'message' => $result['message']]);
    }

    /**
     * POST /api/otp/verify
     * Body: { "phone": "91XXXXXXXXXX", "otp": "123456" }
     */
    public function verify(): \CodeIgniter\HTTP\ResponseInterface
    {
        $body  = $this->request->getJSON(true) ?? [];
        $phone = trim((string) ($body['phone'] ?? $this->request->getPost('phone') ?? ''));
        $otp   = trim((string) ($body['otp']   ?? $this->request->getPost('otp')   ?? ''));

        if (empty($phone) || empty($otp)) {
            return $this->jsonError('phone and otp are required.', 400);
        }

        $phone = preg_replace('/[\s\-+]/', '', $phone);

        if (!preg_match('/^\d{10,15}$/', $phone)) {
            return $this->jsonError('Invalid phone number format.', 422);
        }

        if (!preg_match('/^\d{6}$/', $otp)) {
            return $this->jsonError('OTP must be exactly 6 digits.', 422);
        }

        $service = new WhatsappOtpService();
        $result  = $service->verifyOtp($phone, $otp);

        if (!$result['success']) {
            return $this->response->setStatusCode(422)->setJSON([
                'success' => false,
                'message' => $result['message'],
            ]);
        }

        return $this->response->setJSON(['success' => true, 'message' => $result['message']]);
    }

    private function jsonError(string $message, int $status = 400): \CodeIgniter\HTTP\ResponseInterface
    {
        return $this->response->setStatusCode($status)->setJSON(['success' => false, 'message' => $message]);
    }
}
