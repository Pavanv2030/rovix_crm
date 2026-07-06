<?php

namespace App\Controllers;

use App\Models\BroadcastModel;

class ReportsController extends BaseController
{
    public function sendingHistory()
    {
        $accountId = session('account_id');
        $db        = \Config\Database::connect();

        // Broadcasts sent or sending
        $broadcastModel = new BroadcastModel();
        $broadcasts = $broadcastModel
            ->whereIn('status', ['sending', 'sent'])
            ->orderBy('sent_at', 'DESC')
            ->findAll(200);

        // Individual messages sent by agent or bot
        $messages = $db->table('messages m')
            ->select('m.id, m.content_text, m.content_type, m.template_name, m.status, m.sender_type, m.created_at, co.name as contact_name, co.phone as phone_number')
            ->join('conversations cv', 'cv.id = m.conversation_id')
            ->join('contacts co', 'co.id = cv.contact_id', 'left')
            ->where('m.account_id', $accountId)
            ->whereIn('m.sender_type', ['agent', 'bot'])
            ->orderBy('m.created_at', 'DESC')
            ->limit(500)
            ->get()
            ->getResultArray();

        return view('reports/sending_history', [
            'pageTitle'  => 'Sending History',
            'broadcasts' => $broadcasts,
            'messages'   => $messages,
        ]);
    }

    public function scheduledLog()
    {
        $broadcastModel = new BroadcastModel();
        $scheduled = $broadcastModel
            ->where('status', 'scheduled')
            ->orderBy('scheduled_at', 'ASC')
            ->findAll();

        return view('reports/scheduled_log', [
            'pageTitle' => 'Scheduled Log',
            'scheduled' => $scheduled,
        ]);
    }
}
