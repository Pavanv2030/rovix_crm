<?php

namespace App\Libraries;

use App\Models\AppointmentTypeModel;
use App\Models\WhatsAppConfigModel;
use App\Models\ContactModel;
use App\Libraries\WhatsApp\MetaApi;
use App\Libraries\WhatsApp\Encryption;

class AppointmentRescheduler
{
    /**
     * Sends the WhatsApp calendar-picker flow for an EXISTING appointment.
     * Shared by the public booking page (customer-initiated) and the CRM
     * Appointments list (agent-initiated on the customer's behalf) — same
     * underlying mechanism, just two different entry points.
     *
     * flow_token_map carries this appointment's id so
     * WebhookController::processFlowCompletion() updates the same row on
     * completion instead of inserting a duplicate booking.
     */
    public static function send(array $appointment): array
    {
        if ($appointment['status'] === 'cancelled') {
            return ['success' => false, 'error' => 'This appointment was cancelled — book a new one instead.'];
        }

        $type = (new AppointmentTypeModel())->find($appointment['appointment_type_id']);
        if (!$type) {
            return ['success' => false, 'error' => 'Appointment type not found'];
        }

        $db      = \Config\Database::connect();
        $flowRow = $db->table('whatsapp_flows')
            ->where('appointment_type_id', $appointment['appointment_type_id'])
            ->where('status', 'published')
            ->get()->getRowArray();

        if (!$flowRow) {
            return ['success' => false, 'error' => "Rescheduling via WhatsApp isn't set up for this appointment type."];
        }

        $waConfig = (new WhatsAppConfigModel())->where('account_id', $appointment['account_id'])->first();
        if (!$waConfig || ($waConfig['status'] ?? '') !== 'connected') {
            return ['success' => false, 'error' => 'WhatsApp is not connected for this business.'];
        }

        $contact = (new ContactModel())->find($appointment['contact_id']);
        if (!$contact) {
            return ['success' => false, 'error' => 'Contact not found'];
        }

        $flowToken   = uniqid('reschedule_', true);
        $accessToken = (new Encryption())->decrypt($waConfig['access_token']);

        $db->table('flow_token_map')->insert([
            'flow_token'          => $flowToken,
            'account_id'          => $appointment['account_id'],
            'appointment_type_id' => $appointment['appointment_type_id'],
            'appointment_id'      => $appointment['id'],
            'contact_id'          => $contact['id'],
            'conversation_id'     => $appointment['conversation_id'],
            'created_at'          => date('Y-m-d H:i:s'),
        ]);

        (new MetaApi())->sendFlowMessage(
            $waConfig['phone_number_id'],
            $accessToken,
            $contact['phone_normalized'],
            "Pick a new date & time for your {$type['name']} appointment 📅",
            'Available Date & Time',
            $flowRow['flow_id'],
            $flowToken,
            [
                'min_date' => date('Y-m-d', strtotime('+1 day')),
                'max_date' => date('Y-m-d', strtotime('+' . ($type['max_days_ahead'] ?? 60) . ' days')),
            ]
        );

        return ['success' => true, 'message' => 'A WhatsApp message with a date/time picker has been sent — check WhatsApp to pick the new time.'];
    }
}
