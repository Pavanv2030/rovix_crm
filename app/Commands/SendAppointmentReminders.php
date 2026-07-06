<?php

namespace App\Commands;

use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
use App\Models\AppointmentModel;
use App\Models\AppointmentTypeModel;
use App\Models\WhatsAppConfigModel;
use App\Models\MessageModel;
use App\Models\ConversationModel;
use App\Libraries\WhatsApp\MetaApi;
use App\Libraries\WhatsApp\Encryption;

class SendAppointmentReminders extends BaseCommand
{
    protected $group       = 'Appointments';
    protected $name        = 'appointments:reminders';
    protected $description = 'Send 24hr reminders + post-appointment follow-ups + auto-complete';

    public function run(array $params)
    {
        $this->sendReminders();
        $this->sendFollowUps();
        $this->markCompleted();
    }

    private function sendReminders(): void
    {
        $tomorrow = date('Y-m-d', strtotime('+1 day'));

        $appointments = (new AppointmentModel())
            ->where('DATE(scheduled_at)', $tomorrow)
            ->whereIn('status', ['confirmed', 'pending'])
            ->where('reminder_sent_at', null)
            ->findAll();

        foreach ($appointments as $appt) {
            $this->sendWaMessage($appt, $this->buildReminderMsg($appt));
            (new AppointmentModel())->update($appt['id'], ['reminder_sent_at' => date('Y-m-d H:i:s')]);
            CLI::write("Reminder sent → {$appt['contact_phone']}");
        }
    }

    private function buildReminderMsg(array $appt): string
    {
        $type          = (new AppointmentTypeModel())->find($appt['appointment_type_id']);
        $dateFormatted = date('D, d M Y', strtotime($appt['scheduled_at']));
        $timeFormatted = date('h:i A', strtotime($appt['scheduled_at']));

        $msg  = "⏰ *Appointment Reminder*\n\n";
        $msg .= "Your appointment is *tomorrow*!\n\n";
        $msg .= "📋 *{$type['name']}*\n";
        $msg .= "📅 {$dateFormatted} at {$timeFormatted}\n";
        $msg .= "⏱ {$type['duration_minutes']} mins\n";
        if ($appt['meet_link']) $msg .= "🎥 Meet: {$appt['meet_link']}\n";
        $msg .= "\nReply *CANCEL* if you can't make it.";
        return $msg;
    }

    private function sendFollowUps(): void
    {
        $cutoff = date('Y-m-d H:i:s', strtotime('-1 hour'));

        $appointments = \Config\Database::connect()->table('appointments')
            ->whereIn('status', ['confirmed', 'completed'])
            ->where('end_at <=', $cutoff)
            ->where('follow_up_sent_at', null)
            ->get()->getResultArray();

        foreach ($appointments as $appt) {
            $this->sendWaMessage($appt, $this->buildFollowUpMsg($appt));
            \Config\Database::connect()->table('appointments')
                ->where('id', $appt['id'])
                ->update(['follow_up_sent_at' => date('Y-m-d H:i:s'), 'status' => 'completed']);
            CLI::write("Follow-up sent → {$appt['contact_phone']}");
        }
    }

    private function buildFollowUpMsg(array $appt): string
    {
        $type       = (new AppointmentTypeModel())->find($appt['appointment_type_id']);
        $bookingUrl = base_url("booking/{$appt['booking_token']}");

        $msg  = "👋 Hi *{$appt['contact_name']}*!\n\n";
        $msg .= "Hope your *{$type['name']}* session went well 😊\n\n";
        $msg .= "We'd love to hear your feedback! Reply with:\n";
        $msg .= "⭐ 1-5 rating\n";
        $msg .= "💬 Any comments\n\n";
        $msg .= "📄 View your booking: {$bookingUrl}\n\n";
        $msg .= "Want to *book again*? Reply *BOOK* anytime!";
        return $msg;
    }

    private function markCompleted(): void
    {
        $cutoff = date('Y-m-d H:i:s', strtotime('-2 hours'));
        \Config\Database::connect()->table('appointments')
            ->where('status', 'confirmed')
            ->where('end_at <=', $cutoff)
            ->where('follow_up_sent_at IS NOT NULL')
            ->update(['status' => 'completed', 'updated_at' => date('Y-m-d H:i:s')]);
    }

    private function sendWaMessage(array $appt, string $message): void
    {
        if (empty($appt['contact_phone'])) return;

        $waConfig = (new WhatsAppConfigModel())->where('account_id', $appt['account_id'])->first();
        if (!$waConfig) return;

        try {
            $accessToken = (new Encryption())->decrypt($waConfig['access_token']);
            $response    = (new MetaApi())->sendText(
                $waConfig['phone_number_id'],
                $accessToken,
                $appt['contact_phone'],
                $message
            );

            if (!empty($appt['conversation_id'])) {
                (new MessageModel())->insert([
                    'conversation_id'     => $appt['conversation_id'],
                    'account_id'          => $appt['account_id'],
                    'sender_type'         => 'bot',
                    'content_type'        => 'text',
                    'content_text'        => $message,
                    'status'              => 'sent',
                    'whatsapp_message_id' => $response['messages'][0]['id'] ?? null,
                    'created_at'          => date('Y-m-d H:i:s'),
                ]);
                (new ConversationModel())->update($appt['conversation_id'], [
                    'last_message_text' => mb_strimwidth(str_replace(["\n", "\r"], ' ', $message), 0, 100, '...'),
                    'last_message_at'   => date('Y-m-d H:i:s'),
                ]);
            }
        } catch (\Exception $e) {
            log_message('error', "Appointment msg failed [{$appt['id']}]: " . $e->getMessage());
        }
    }
}
