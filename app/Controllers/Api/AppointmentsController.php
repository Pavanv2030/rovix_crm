<?php

namespace App\Controllers\Api;

use App\Controllers\BaseController;
use App\Libraries\WhatsApp\MetaApi;
use App\Libraries\WhatsApp\Encryption;
use App\Models\AppointmentTypeModel;
use App\Models\ConversationModel;
use App\Models\ContactModel;
use App\Models\WhatsAppConfigModel;
use App\Models\MessageModel;

class AppointmentsController extends BaseController
{
    public function types()
    {
        $types = (new AppointmentTypeModel())
            ->where('account_id', session('account_id'))
            ->where('active', 1)
            ->findAll();

        return $this->response->setJSON(['types' => $types]);
    }

    public function slots()
    {
        $typeId = $this->request->getGet('type_id');
        $date   = $this->request->getGet('date');

        if (!$typeId || !$date) {
            return $this->response->setStatusCode(400)->setJSON(['error' => 'type_id and date required']);
        }

        $slots = (new AppointmentTypeModel())->getAvailableSlots($typeId, $date);
        return $this->response->setJSON(['slots' => $slots]);
    }

    public function sendFlow()
    {
        $typeId         = $this->request->getPost('appointment_type_id');
        $conversationId = $this->request->getPost('conversation_id');
        $bodyText       = $this->request->getPost('body_text')
            ?: 'Please choose a date & time for your appointment 👇';
        $buttonText     = $this->request->getPost('button_text')
            ?: 'Available Date & Time';

        $type         = (new AppointmentTypeModel())->find($typeId);
        $conversation = (new ConversationModel())->find($conversationId);
        $contact      = $conversation ? (new ContactModel())->find($conversation['contact_id']) : null;
        $waConfig     = (new WhatsAppConfigModel())->where('account_id', session('account_id'))->first();

        if (!$type || !$conversation || !$contact || !$waConfig) {
            return $this->response->setStatusCode(400)->setJSON(['error' => 'Missing required data']);
        }

        $flowRow = \Config\Database::connect()
            ->table('whatsapp_flows')
            ->where('appointment_type_id', $typeId)
            ->where('status', 'published')
            ->get()->getRowArray();

        if (!$flowRow) {
            return $this->response->setStatusCode(400)->setJSON([
                'error' => 'No published flow for this type. Go to Appointments → Types → Create Flow first.',
            ]);
        }

        $flowToken   = uniqid('apt_', true);
        $accessToken = (new Encryption())->decrypt($waConfig['access_token']);

        \Config\Database::connect()->table('flow_token_map')->insert([
            'flow_token'          => $flowToken,
            'account_id'          => session('account_id'),
            'appointment_type_id' => $typeId,
            'contact_id'          => $contact['id'],
            'conversation_id'     => $conversationId,
            'created_at'          => date('Y-m-d H:i:s'),
        ]);

        try {
            $response = (new MetaApi())->sendFlowMessage(
                $waConfig['phone_number_id'],
                $accessToken,
                $contact['phone_normalized'],
                $bodyText,
                $buttonText,
                $flowRow['flow_id'],
                $flowToken,
                [
                    'min_date' => date('Y-m-d', strtotime('+1 day')),
                    'max_date' => date('Y-m-d', strtotime('+' . ($type['max_days_ahead'] ?? 60) . ' days')),
                ]
            );

            (new MessageModel())->insert([
                'conversation_id'     => $conversationId,
                'account_id'          => session('account_id'),
                'sender_type'         => 'agent',
                'content_type'        => 'flow',
                'content_text'        => json_encode(['body' => $bodyText, 'button' => $buttonText]),
                'status'              => 'sent',
                'whatsapp_message_id' => $response['messages'][0]['id'] ?? null,
                'created_at'          => date('Y-m-d H:i:s'),
            ]);
            (new ConversationModel())->update($conversationId, [
                'last_message_text' => 'Appointment booking sent',
                'last_message_at'   => date('Y-m-d H:i:s'),
            ]);

            return $this->response->setJSON(['success' => true, 'meta' => $response]);
        } catch (\Exception $e) {
            return $this->response->setStatusCode(500)->setJSON(['error' => $e->getMessage()]);
        }
    }
}
