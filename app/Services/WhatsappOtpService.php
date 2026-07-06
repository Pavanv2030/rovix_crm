<?php

namespace App\Services;

use App\Libraries\WhatsApp\Encryption;
use App\Libraries\WhatsApp\MetaApi;
use App\Models\OtpVerificationModel;
use App\Models\WhatsAppConfigModel;

class WhatsappOtpService
{
    private MetaApi $api;
    private Encryption $enc;

    public function __construct()
    {
        $this->api = new MetaApi();
        $this->enc = new Encryption();
    }

    /**
     * Generate, store and send an OTP to the given phone number.
     * Returns ['success' => bool, 'message' => string].
     */
    public function sendOtp(string $phone, string $accountId): array
    {
        $waConfig = (new WhatsAppConfigModel())->where('account_id', $accountId)->first();
        if (!$waConfig || empty($waConfig['phone_number_id'])) {
            return ['success' => false, 'message' => 'WhatsApp is not configured for this account.'];
        }

        $accessToken   = $this->enc->decrypt($waConfig['access_token']);
        $phoneNumberId = $waConfig['phone_number_id'];

        $otp   = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        $model = new OtpVerificationModel();

        // Remove any existing pending OTPs for this number
        $model->deleteUnverifiedForPhone($phone);

        $model->insert([
            'id'           => generate_uuid(),
            'phone_number' => $phone,
            'otp_code'     => $otp,
            'is_verified'  => 0,
            'attempts'     => 0,
            'expires_at'   => date('Y-m-d H:i:s', strtotime('+10 minutes')),
            'created_at'   => date('Y-m-d H:i:s'),
        ]);

        // Authentication template: body {{1}} = OTP, optional CTA button also gets the code
        $components = [
            [
                'type'       => 'body',
                'parameters' => [
                    ['type' => 'text', 'text' => $otp],
                ],
            ],
        ];

        $result = $this->api->sendTemplate(
            $phoneNumberId,
            $accessToken,
            $phone,
            'otp_verification',
            'en',
            $components
        );

        if (!empty($result['error'])) {
            // Clean up the stored OTP since send failed
            $model->deleteUnverifiedForPhone($phone);
            $errMsg = $result['error']['message'] ?? 'Failed to send OTP via WhatsApp.';
            return ['success' => false, 'message' => $errMsg];
        }

        return ['success' => true, 'message' => 'OTP sent successfully.'];
    }

    /**
     * Verify a submitted OTP. Returns ['success' => bool, 'message' => string].
     */
    public function verifyOtp(string $phone, string $submittedOtp): array
    {
        $model  = new OtpVerificationModel();
        $record = $model->getLatestForPhone($phone);

        if (!$record) {
            return ['success' => false, 'message' => 'No OTP found for this number. Please request a new one.'];
        }

        if (strtotime($record['expires_at']) < time()) {
            return ['success' => false, 'message' => 'OTP has expired. Please request a new one.'];
        }

        if ((int) $record['attempts'] >= 3) {
            return ['success' => false, 'message' => 'Too many incorrect attempts. Please request a new OTP.'];
        }

        if ($record['otp_code'] !== trim($submittedOtp)) {
            $model->update($record['id'], ['attempts' => (int) $record['attempts'] + 1]);
            $remaining = 3 - ((int) $record['attempts'] + 1);
            return [
                'success' => false,
                'message' => 'Incorrect OTP.' . ($remaining > 0 ? " {$remaining} attempt(s) remaining." : ' No attempts left.'),
            ];
        }

        $model->update($record['id'], ['is_verified' => 1]);
        return ['success' => true, 'message' => 'Phone number verified successfully.'];
    }
}
