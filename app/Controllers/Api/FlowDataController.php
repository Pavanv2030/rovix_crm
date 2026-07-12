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

        try {
            [$body, $aesKey, $ivBinary] = $this->decryptEnvelope(
                $encryptedFlowData,
                $encryptedAesKey,
                $initialVector
            );
        } catch (\Exception $e) {
            log_message('error', 'Flow decryption failed: ' . $e->getMessage());
            return $this->response->setStatusCode(421)->setBody('Decryption failed');
        }

        $action    = $body['action']     ?? '';
        $screen    = $body['screen']     ?? '';
        $flowToken = $body['flow_token'] ?? '';
        $data      = $body['data']       ?? [];

        if ($action === 'ping') {
            return $this->encryptedReply(['data' => ['status' => 'active']], $aesKey, $ivBinary);
        }

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

    private function decryptEnvelope(string $encryptedFlowData, string $encryptedAesKey, string $initialVector): array
    {
        $configs = (new WhatsAppConfigModel())
            ->where('flow_private_key IS NOT NULL')
            ->where('flow_private_key !=', '')
            ->findAll();

        if (empty($configs)) {
            throw new \RuntimeException('Flow encryption not configured');
        }

        $encryption = new Encryption();
        $lastError  = null;

        foreach ($configs as $waConfig) {
            try {
                $privateKeyPem = $encryption->decrypt($waConfig['flow_private_key']);
                $ivBinary      = base64_decode($initialVector);
                $aesKey        = FlowCrypto::decryptAesKey(base64_decode($encryptedAesKey), $privateKeyPem);
                $body          = FlowCrypto::decryptFlowData(base64_decode($encryptedFlowData), $aesKey, $ivBinary);

                return [$body, $aesKey, $ivBinary];
            } catch (\Exception $e) {
                $lastError = $e;
            }
        }

        throw $lastError ?? new \RuntimeException('Decryption failed');
    }

    private function encryptedReply(array $data, string $aesKey, string $ivBinary)
    {
        $encrypted = FlowCrypto::encryptResponse($data, $aesKey, $ivBinary);

        return $this->response
            ->setContentType('text/plain')
            ->setBody($encrypted);
    }
}