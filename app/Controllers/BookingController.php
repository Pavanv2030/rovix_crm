<?php

namespace App\Controllers;

use App\Models\AppointmentModel;
use App\Models\AppointmentTypeModel;
use App\Libraries\AppointmentRescheduler;
use CodeIgniter\Controller;

class BookingController extends Controller
{
    /**
     * Public page — no login required.
     * URL: /booking/{token}
     */
    public function show(?string $token = null)
    {
        // Hit without a token (bot/crawler traffic, WhatsApp's own link
        // preview fetcher, or a stray request) previously fatal-errored
        // with ArgumentCountError instead of a normal 404.
        if ($token === null) {
            return $this->response->setStatusCode(404)
                ->setBody('<!DOCTYPE html><html><body style="font-family:sans-serif;text-align:center;padding:60px"><h1>Booking Not Found</h1><p>This booking link is invalid or has expired.</p></body></html>');
        }

        $appointment = (new AppointmentModel())
            ->where('booking_token', $token)
            ->first();

        if (!$appointment) {
            return $this->response->setStatusCode(404)
                ->setBody('<!DOCTYPE html><html><body style="font-family:sans-serif;text-align:center;padding:60px"><h1>Booking Not Found</h1><p>This booking link is invalid or has expired.</p></body></html>');
        }

        $type          = (new AppointmentTypeModel())->find($appointment['appointment_type_id']);
        $invoiceNumber = '#' . strtoupper(substr($appointment['id'], -6));

        return view('booking/public', [
            'appointment'   => $appointment,
            'type'          => $type,
            'invoiceNumber' => $invoiceNumber,
        ]);
    }

    /**
     * Re-sends the WhatsApp calendar-picker flow for an EXISTING booking.
     * flow_token_map carries this appointment's id so processFlowCompletion()
     * updates the same row on completion instead of inserting a duplicate
     * booking (which is what the old "text BOOK to rebook" path did).
     */
    public function reschedule(string $token)
    {
        $appointment = (new AppointmentModel())->where('booking_token', $token)->first();
        if (!$appointment) {
            return $this->response->setStatusCode(404)->setJSON(['error' => 'Booking not found']);
        }

        if (in_array($appointment['status'] ?? '', ['cancelled', 'completed'], true)) {
            return $this->response->setStatusCode(400)->setJSON(['error' => 'This booking can no longer be rescheduled.']);
        }

        $cache = \Config\Services::cache();
        $rateKey = 'booking_reschedule_' . md5($token);
        $attempts = (int) $cache->get($rateKey);
        if ($attempts >= 3) {
            return $this->response->setStatusCode(429)->setJSON(['error' => 'Too many reschedule attempts. Try again later.']);
        }
        $cache->save($rateKey, $attempts + 1, 3600);

        $result = AppointmentRescheduler::send($appointment);

        return $this->response->setStatusCode($result['success'] ? 200 : 400)->setJSON($result);
    }
}
