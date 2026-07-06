<?php

namespace App\Models;

class AppointmentTypeModel extends BaseModel
{
    protected $table         = 'appointment_types';
    protected $primaryKey    = 'id';
    protected $allowedFields = [
        'id', 'account_id', 'name', 'description',
        'duration_minutes', 'price', 'currency',
        'availability', 'max_days_ahead', 'buffer_minutes', 'active',
        'created_at', 'updated_at',
    ];

    public function getAvailableSlots(string $typeId, string $date): array
    {
        $type = $this->find($typeId);
        if (!$type) return [];

        $availability = json_decode($type['availability'] ?? '{}', true) ?? [];
        $dayOfWeek    = strtolower(date('l', strtotime($date)));
        $dayConfig    = $availability[$dayOfWeek] ?? null;

        if (!$dayConfig || empty($dayConfig['enabled'])) return [];

        $startTime = $dayConfig['start'] ?? '09:00';
        $endTime   = $dayConfig['end']   ?? '17:00';
        $duration  = (int) $type['duration_minutes'];
        $buffer    = (int) $type['buffer_minutes'];

        $slots   = [];
        $current = strtotime("{$date} {$startTime}");
        $end     = strtotime("{$date} {$endTime}");

        while ($current + ($duration * 60) <= $end) {
            $slots[] = date('H:i', $current);
            $current += ($duration + $buffer) * 60;
        }

        // Remove already-booked slots
        $booked = $this->db->table('appointments')
            ->where('appointment_type_id', $typeId)
            ->where('DATE(scheduled_at)', $date)
            ->whereIn('status', ['confirmed', 'pending'])
            ->get()->getResultArray();

        $bookedTimes = array_map(fn($a) => date('H:i', strtotime($a['scheduled_at'])), $booked);

        return array_values(array_filter($slots, fn($s) => !in_array($s, $bookedTimes)));
    }
}
