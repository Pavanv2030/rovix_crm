<?php

namespace App\Libraries;

use App\Models\BroadcastModel;
use App\Models\BroadcastRecipientModel;
use App\Models\ContactModel;

class BroadcastExporter
{
    public function exportToCsv(string $broadcastId): string
    {
        $broadcast = (new BroadcastModel())->find($broadcastId);
        if (!$broadcast) throw new \Exception('Broadcast not found');

        $recipients   = (new BroadcastRecipientModel())->where('broadcast_id', $broadcastId)->orderBy('created_at', 'ASC')->findAll();
        $contactModel = new ContactModel();

        $output = fopen('php://temp', 'r+');

        fputcsv($output, ['Contact Name', 'Phone', 'Status', 'Variables', 'WhatsApp Message ID', 'Sent At', 'Delivered At', 'Read At', 'Error Message']);

        foreach ($recipients as $r) {
            $contact = $contactModel->find($r['contact_id']);
            $vars    = json_decode($r['variables'] ?? '{}', true);
            $varStr  = implode(', ', array_map(fn($k, $v) => "{{{$k}}}={$v}", array_keys($vars), $vars));

            fputcsv($output, [
                $contact['name']  ?? '',
                $contact['phone'] ?? '',
                $r['status'],
                $varStr,
                $r['whatsapp_message_id'] ?? '',
                $r['sent_at']      ?? '',
                $r['delivered_at'] ?? '',
                $r['read_at']      ?? '',
                $r['error_message'] ?? '',
            ]);
        }

        rewind($output);
        $csv = stream_get_contents($output);
        fclose($output);

        return $csv;
    }
}
