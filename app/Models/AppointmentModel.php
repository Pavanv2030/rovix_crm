<?php

namespace App\Models;

class AppointmentModel extends BaseModel
{
    protected $table         = 'appointments';
    protected $primaryKey    = 'id';
    protected $allowedFields = [
        'id', 'account_id', 'appointment_type_id', 'contact_id',
        'conversation_id', 'contact_name', 'contact_phone', 'contact_email',
        'scheduled_at', 'end_at', 'status', 'answers',
        'google_event_id', 'meet_link', 'price_paid', 'notes',
        'reminder_sent_at', 'follow_up_sent_at', 'booking_token',
        'created_at', 'updated_at',
    ];

    public function generateBookingToken(): string
    {
        return bin2hex(random_bytes(16));
    }
}
