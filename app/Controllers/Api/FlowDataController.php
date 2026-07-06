<?php

namespace App\Controllers\Api;

use App\Controllers\BaseController;
use App\Models\AppointmentTypeModel;
use App\Models\WhatsAppConfigModel;
use App\Libraries\WhatsApp\Encryption;
use App\Libraries\WhatsApp\FlowCrypto;

class FlowDataController extends BaseController
{
    /**
     * Meta calls this endpoint when a customer interacts with a Flow screen.
     * Set endpoint URL in Meta Developer Console → WhatsApp → Flows → your
     * flow → Endpoint URI. Requests/responses are encrypted per Meta's
     * Business Encryption spec (RSA-OAEP-SHA256 key unwrap + AES-128-GCM).
     */
    public function handle()
    {
        $envelope = $this->request->getJSON(true) ?? [];

        $encryptedFlowData = $envelope['encrypted_flow_data'] ?? null;
        $encryptedAesKey   = $envelope['encrypted_aes_key']   ?? null;
        $initialVector     = $envelope['initial_vector']      ?? null;

        if (!$encryptedFlowData || !$encryptedAesKey || !$initialVector) {
            return $this->response->setStatusCode(400)->setJSON(['error' => 'Missing encrypted fields']);
        }

        // Public endpoint, no session. This app currently manages a single
        // active WhatsApp connection at a time, so the first row's key pair
        // is the one Meta encrypted against.
        $waConfig = (new WhatsAppConfigModel())->first();
        if (!$waConfig || empty($waConfig['flow_private_key'])) {
            return $this->response->setStatusCode(421)->setBody('Flow encryption not configured');
        }

        try {
            $privateKeyPem = (new Encryption())->decrypt($waConfig['flow_private_key']);

            $ivBinary = base64_decode($initialVector);
            $aesKey   = FlowCrypto::decryptAesKey(base64_decode($encryptedAesKey), $privateKeyPem);
            $body     = FlowCrypto::decryptFlowData(base64_decode($encryptedFlowData), $aesKey, $ivBinary);
        } catch (\Exception $e) {
            log_message('error', 'Flow decryption failed: ' . $e->getMessage());
            return $this->response->setStatusCode(421)->setBody('Decryption failed');
        }

        $action    = $body['action']     ?? '';
        $screen    = $body['screen']     ?? '';
        $flowToken = $body['flow_token'] ?? '';
        $data      = $body['data']       ?? [];

        // Ping health-check from Meta
        if ($action === 'ping') {
            return $this->encryptedReply(['data' => ['status' => 'active']], $aesKey, $ivBinary);
        }

        // Customer tapped "Next" on SELECT_DATE → return time slots for SELECT_TIME
        if ($screen === 'SELECT_DATE' && $action === 'data_exchange') {
            $selectedDate = $data['selected_date'] ?? null;
            $token        = $data['flow_token']    ?? $flowToken;

            $flowMeta = \Config\Database::connect()
                ->table('flow_token_map')
                ->where('flow_token', $token)
                ->get()->getRowArray();

            $typeId = $flowMeta['appointment_type_id'] ?? null;
            $slots  = [];

            if ($typeId && $selectedDate) {
                $rawSlots = (new AppointmentTypeModel())->getAvailableSlots($typeId, $selectedDate);
                foreach ($rawSlots as $slot) {
                    $slots[] = [
                        'id'    => $slot,
                        'title' => date('h:i A', strtotime("{$selectedDate} {$slot}")),
                    ];
                }
            }

            if (empty($slots)) {
                $slots = [['id' => 'none', 'title' => 'No slots available this day']];
            }

            return $this->encryptedReply([
                'screen' => 'SELECT_TIME',
                'data'   => [
                    'selected_date' => $selectedDate,
                    'flow_token'    => $token,
                    'time_slots'    => $slots,
                ],
            ], $aesKey, $ivBinary);
        }

        return $this->encryptedReply(['data' => []], $aesKey, $ivBinary);
    }

    private function encryptedReply(array $data, string $aesKey, string $ivBinary)
    {
        $encrypted = FlowCrypto::encryptResponse($data, $aesKey, $ivBinary);

        // Meta requires the raw base64 string as the body, Content-Type
        // text/plain — NOT wrapped in JSON.
        return $this->response
            ->setContentType('text/plain')
            ->setBody($encrypted);
    }
}
