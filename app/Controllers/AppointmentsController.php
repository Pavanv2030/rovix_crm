<?php

namespace App\Controllers;

use App\Libraries\GoogleCalendar;
use App\Libraries\AppointmentFlowSchema;
use App\Libraries\AppointmentRescheduler;
use App\Libraries\WhatsApp\MetaApi;
use App\Libraries\WhatsApp\Encryption;
use App\Models\AppointmentTypeModel;
use App\Models\AppointmentModel;
use App\Models\WhatsAppConfigModel;
use App\Models\MessageModel;
use App\Models\ConversationModel;
use App\Models\ActivityLogModel;

class AppointmentsController extends BaseController
{
    public function index()
    {
        $appointmentModel = new AppointmentModel();
        $db               = \Config\Database::connect();

        $appointments = $db->table('appointments a')
            ->select('a.*, at.name as type_name, at.duration_minutes, at.currency')
            ->join('appointment_types at', 'at.id = a.appointment_type_id', 'left')
            ->where('a.account_id', session('account_id'))
            ->orderBy('a.scheduled_at', 'DESC')
            ->limit(100)
            ->get()->getResultArray();

        $googleToken = $db->table('google_oauth_tokens')
            ->where('account_id', session('account_id'))
            ->get()->getRowArray();

        return view('appointments/index', [
            'pageTitle'   => 'Appointments',
            'appointments'=> $appointments,
            'googleToken' => $googleToken,
        ]);
    }

    public function types()
    {
        $types = (new AppointmentTypeModel())
            ->where('account_id', session('account_id'))
            ->orderBy('created_at', 'DESC')
            ->findAll();

        $db    = \Config\Database::connect();
        $flows = $db->table('whatsapp_flows')
            ->where('account_id', session('account_id'))
            ->get()->getResultArray();

        $flowByType = [];
        foreach ($flows as $f) {
            $flowByType[$f['appointment_type_id']] = $f;
        }

        $googleToken = $db->table('google_oauth_tokens')
            ->where('account_id', session('account_id'))
            ->get()->getRowArray();

        return view('appointments/types', [
            'pageTitle'   => 'Appointment Types',
            'types'       => $types,
            'flowByType'  => $flowByType,
            'googleToken' => $googleToken,
        ]);
    }

    public function createType()
    {
        if (!can_edit_settings()) {
            return $this->response->setJSON(['error' => 'Access denied'])->setStatusCode(403);
        }

        $name        = trim($this->request->getPost('name') ?? '');
        $description = trim($this->request->getPost('description') ?? '');
        $duration    = (int) ($this->request->getPost('duration_minutes') ?? 30);
        $price       = (float) ($this->request->getPost('price') ?? 0);
        $currency    = strtoupper(trim($this->request->getPost('currency') ?? 'INR'));

        $availabilityJson = $this->request->getPost('availability');
        $availability     = $availabilityJson ? json_decode($availabilityJson, true) : $this->defaultAvailability();

        if (empty($name)) {
            return $this->response->setJSON(['error' => 'Name required'])->setStatusCode(400);
        }

        $model = new AppointmentTypeModel();
        $id    = $model->insert([
            'account_id'       => session('account_id'),
            'name'             => $name,
            'description'      => $description,
            'duration_minutes' => $duration,
            'price'            => $price,
            'currency'         => $currency,
            'availability'     => json_encode($availability),
            'max_days_ahead'   => (int) ($this->request->getPost('max_days_ahead') ?? 60),
            'buffer_minutes'   => (int) ($this->request->getPost('buffer_minutes') ?? 0),
            'active'           => 1,
            'created_at'       => date('Y-m-d H:i:s'),
            'updated_at'       => date('Y-m-d H:i:s'),
        ]);

        return $this->response->setJSON(['success' => true, 'id' => $id]);
    }

    public function deleteType(string $typeId)
    {
        if (!can_edit_settings()) {
            return $this->response->setJSON(['error' => 'Access denied'])->setStatusCode(403);
        }

        $model = new AppointmentTypeModel();
        $type  = $model->where('account_id', session('account_id'))->find($typeId);

        if (!$type) {
            return $this->response->setJSON(['error' => 'Not found'])->setStatusCode(404);
        }

        $model->delete($typeId);
        return $this->response->setJSON(['success' => true]);
    }

    public function createFlow()
    {
        if (!can_edit_settings()) {
            return $this->response->setJSON(['error' => 'Access denied'])->setStatusCode(403);
        }

        $typeId = $this->request->getPost('appointment_type_id');
        $type   = (new AppointmentTypeModel())
            ->where('account_id', session('account_id'))->find($typeId);

        if (!$type) {
            return $this->response->setJSON(['error' => 'Type not found'])->setStatusCode(404);
        }

        $waConfig = (new WhatsAppConfigModel())->where('account_id', session('account_id'))->first();
        if (!$waConfig || empty($waConfig['waba_id'])) {
            return $this->response->setJSON(['error' => 'WhatsApp not connected or WABA ID missing'])->setStatusCode(400);
        }

        $accessToken = (new Encryption())->decrypt($waConfig['access_token']);
        $metaApi     = new MetaApi();

        try {
            // Meta requires a registered RSA public key on the phone number
            // before any Flow with a Data Exchange endpoint can be published.
            // Generate + register once per account, reuse afterward.
            if (empty($waConfig['flow_public_key'])) {
                [$publicPem, $privatePem] = \App\Libraries\WhatsApp\FlowCrypto::generateKeyPair();
                $metaApi->setFlowPublicKey($waConfig['phone_number_id'], $accessToken, $publicPem);

                $encryption = new Encryption();
                (new WhatsAppConfigModel())->update($waConfig['id'], [
                    'flow_public_key'  => $publicPem,
                    'flow_private_key' => $encryption->encrypt($privatePem),
                ]);
            }

            // Flow names must be unique per WABA. A retry after a failed
            // create/publish attempt (create+upload can succeed even when
            // publish fails) leaves an orphaned draft with the same name,
            // so a fixed name collides on the next try. Suffix with a short
            // unique token so retries never collide.
            $flowName = "Appointment: {$type['name']} (" . substr(bin2hex(random_bytes(3)), 0, 6) . ')';

            $created = $metaApi->createFlow(
                $waConfig['waba_id'],
                $accessToken,
                $flowName
            );

            if (empty($created['id'])) {
                return $this->response->setJSON(['error' => 'Meta flow creation failed', 'meta' => $created])->setStatusCode(500);
            }

            $flowId   = $created['id'];
            $flowJson = AppointmentFlowSchema::build($type['name'], $type['description'] ?? '');

            $metaApi->uploadFlowAsset($flowId, $accessToken, $flowJson);
            $metaApi->setFlowEndpoint($flowId, $accessToken, base_url('api/flows/data-exchange'));
            $metaApi->publishFlow($flowId, $accessToken);

            $db = \Config\Database::connect();
            $db->table('whatsapp_flows')->insert([
                'id'                  => generate_uuid(),
                'account_id'          => session('account_id'),
                'appointment_type_id' => $typeId,
                'flow_id'             => $flowId,
                'flow_name'           => $flowName,
                'status'              => 'published',
                'created_at'          => date('Y-m-d H:i:s'),
                'updated_at'          => date('Y-m-d H:i:s'),
            ]);

            return $this->response->setJSON(['success' => true, 'flow_id' => $flowId]);
        } catch (\Exception $e) {
            return $this->response->setStatusCode(500)->setJSON(['error' => $e->getMessage()]);
        }
    }

    public function cancel(string $appointmentId)
    {
        $model       = new AppointmentModel();
        $appointment = $model->where('account_id', session('account_id'))->find($appointmentId);

        if (!$appointment) {
            return $this->response->setJSON(['error' => 'Not found'])->setStatusCode(404);
        }

        $model->update($appointmentId, ['status' => 'cancelled', 'updated_at' => date('Y-m-d H:i:s')]);

        // Delete Google Calendar event if linked
        if ($appointment['google_event_id']) {
            try {
                $db          = \Config\Database::connect();
                $tokenRow    = $db->table('google_oauth_tokens')
                    ->where('account_id', session('account_id'))->get()->getRowArray();

                if ($tokenRow) {
                    $gc    = new GoogleCalendar();
                    $token = $gc->getValidToken($tokenRow);
                    $gc->deleteEvent($token, $tokenRow['calendar_id'], $appointment['google_event_id']);
                }
            } catch (\Exception $e) {
                log_message('warning', 'Could not delete Google event on cancel: ' . $e->getMessage());
            }
        }

        return $this->response->setJSON(['success' => true]);
    }

    /**
     * Agent-initiated reschedule (customer-side lives on the public booking
     * page) — same underlying send, just triggered from the CRM instead.
     */
    public function reschedule(string $appointmentId)
    {
        $appointment = (new AppointmentModel())->where('account_id', session('account_id'))->find($appointmentId);
        if (!$appointment) {
            return $this->response->setStatusCode(404)->setJSON(['error' => 'Not found']);
        }

        $result = AppointmentRescheduler::send($appointment);

        if ($result['success']) {
            ActivityLogModel::record('appointment.reschedule_requested', 'appointment', $appointmentId);
        }

        return $this->response->setStatusCode($result['success'] ? 200 : 400)->setJSON($result);
    }

    public function updateStatus(string $appointmentId)
    {
        $allowed = ['pending', 'confirmed', 'cancelled', 'completed'];
        $status  = $this->request->getPost('status');

        if (!in_array($status, $allowed)) {
            return $this->response->setJSON(['error' => 'Invalid status'])->setStatusCode(400);
        }

        $model       = new AppointmentModel();
        $appointment = $model->where('account_id', session('account_id'))->find($appointmentId);

        if (!$appointment) {
            return $this->response->setJSON(['error' => 'Not found'])->setStatusCode(404);
        }

        $model->update($appointmentId, ['status' => $status, 'updated_at' => date('Y-m-d H:i:s')]);
        return $this->response->setJSON(['success' => true]);
    }

    public function sendReminder(string $appointmentId)
    {
        $model       = new AppointmentModel();
        $appointment = $model->where('account_id', session('account_id'))->find($appointmentId);

        if (!$appointment) {
            return $this->response->setJSON(['error' => 'Not found'])->setStatusCode(404);
        }
        if (empty($appointment['contact_phone'])) {
            return $this->response->setJSON(['error' => 'This appointment has no phone number on file'])->setStatusCode(400);
        }

        $waConfig = (new WhatsAppConfigModel())->where('account_id', session('account_id'))->first();
        if (!$waConfig) {
            return $this->response->setJSON(['error' => 'WhatsApp not connected'])->setStatusCode(400);
        }

        $type          = (new AppointmentTypeModel())->find($appointment['appointment_type_id']);
        $dateFormatted = date('D, d M Y', strtotime($appointment['scheduled_at']));
        $timeFormatted = date('h:i A', strtotime($appointment['scheduled_at']));

        $message  = "⏰ *Appointment Reminder*\n\n";
        $message .= "Just a reminder about your upcoming appointment!\n\n";
        $message .= "📋 *{$type['name']}*\n";
        $message .= "📅 {$dateFormatted} at {$timeFormatted}\n";
        $message .= "⏱ {$type['duration_minutes']} mins\n";
        if ($appointment['meet_link']) $message .= "🎥 Meet: {$appointment['meet_link']}\n";
        $message .= "\nReply *CANCEL* if you can't make it.";

        try {
            $accessToken = (new Encryption())->decrypt($waConfig['access_token']);
            $response    = (new MetaApi())->sendText(
                $waConfig['phone_number_id'],
                $accessToken,
                $appointment['contact_phone'],
                $message
            );
        } catch (\Exception $e) {
            return $this->response->setJSON(['error' => $e->getMessage()])->setStatusCode(500);
        }

        $model->update($appointmentId, ['reminder_sent_at' => date('Y-m-d H:i:s')]);

        if (!empty($appointment['conversation_id'])) {
            (new MessageModel())->insert([
                'conversation_id'     => $appointment['conversation_id'],
                'account_id'          => session('account_id'),
                'sender_type'         => 'bot',
                'content_type'        => 'text',
                'content_text'        => $message,
                'status'              => 'sent',
                'whatsapp_message_id' => $response['messages'][0]['id'] ?? null,
                'created_at'          => date('Y-m-d H:i:s'),
            ]);
            (new ConversationModel())->update($appointment['conversation_id'], [
                'last_message_text' => mb_strimwidth(str_replace(["\n", "\r"], ' ', $message), 0, 100, '...'),
                'last_message_at'   => date('Y-m-d H:i:s'),
            ]);
        }

        ActivityLogModel::record('appointments.reminder_sent_manual', 'appointment', $appointmentId, [
            'contact_phone' => $appointment['contact_phone'],
        ]);

        return $this->response->setJSON(['success' => true]);
    }

    // ── Google OAuth ──────────────────────────────────────────────────────────

    public function googleConnect()
    {
        if (empty(env('GOOGLE_CLIENT_ID'))) {
            return redirect()->to('appointments/types')->with('error', 'Google OAuth not configured. Add GOOGLE_CLIENT_ID and GOOGLE_CLIENT_SECRET to .env');
        }

        $redirectUri = base_url('appointments/google/callback');
        $authUrl     = (new GoogleCalendar())->getAuthUrl($redirectUri);
        return redirect()->to($authUrl);
    }

    public function googleCallback()
    {
        $code  = $this->request->getGet('code');
        $error = $this->request->getGet('error');

        if ($error || !$code) {
            return redirect()->to('appointments/types')->with('error', 'Google OAuth cancelled or failed.');
        }

        try {
            $redirectUri = base_url('appointments/google/callback');
            $tokens      = (new GoogleCalendar())->exchangeCode($code, $redirectUri);

            if (empty($tokens['access_token'])) {
                return redirect()->to('appointments/types')->with('error', 'Google token exchange failed.');
            }

            $db       = \Config\Database::connect();
            $existing = $db->table('google_oauth_tokens')
                ->where('account_id', session('account_id'))->get()->getRowArray();

            $tokenData = [
                'access_token'  => $tokens['access_token'],
                'refresh_token' => $tokens['refresh_token'] ?? null,
                'expires_at'    => date('Y-m-d H:i:s', time() + ($tokens['expires_in'] ?? 3600)),
                'updated_at'    => date('Y-m-d H:i:s'),
            ];

            if ($existing) {
                $db->table('google_oauth_tokens')
                    ->where('account_id', session('account_id'))
                    ->update($tokenData);
            } else {
                $db->table('google_oauth_tokens')->insert(array_merge($tokenData, [
                    'id'         => generate_uuid(),
                    'account_id' => session('account_id'),
                    'created_at' => date('Y-m-d H:i:s'),
                ]));
            }

            return redirect()->to('appointments/types')->with('success', 'Google Calendar connected!');
        } catch (\Exception $e) {
            return redirect()->to('appointments/types')->with('error', 'Google Calendar connection failed: ' . $e->getMessage());
        }
    }

    public function googleDisconnect()
    {
        \Config\Database::connect()->table('google_oauth_tokens')
            ->where('account_id', session('account_id'))
            ->delete();

        return $this->response->setJSON(['success' => true]);
    }

    private function defaultAvailability(): array
    {
        $days = ['monday', 'tuesday', 'wednesday', 'thursday', 'friday'];
        $avail = [];
        foreach ($days as $day) {
            $avail[$day] = ['enabled' => true, 'start' => '09:00', 'end' => '17:00'];
        }
        foreach (['saturday', 'sunday'] as $day) {
            $avail[$day] = ['enabled' => false, 'start' => '09:00', 'end' => '17:00'];
        }
        return $avail;
    }
}
