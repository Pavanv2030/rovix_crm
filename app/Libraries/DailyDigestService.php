<?php

namespace App\Libraries;

use App\Models\AccountModel;
use App\Models\AppointmentModel;
use App\Models\ContactModel;
use App\Models\MessageModel;

class DailyDigestService
{
    private $db;

    public function __construct()
    {
        $this->db = \Config\Database::connect();
    }

    public function generateStats(string $accountId): array
    {
        $account   = (new AccountModel())->find($accountId);
        $yesterday = date('Y-m-d H:i:s', strtotime('-24 hours'));
        $todayStart = date('Y-m-d') . ' 00:00:00';

        $teamTotalReached = $this->db->table('contacts')
            ->where('account_id', $accountId)
            ->where('updated_at >=', $yesterday)
            ->whereIn('status', ['Contacted', 'Qualified', 'Hot Lead', 'Converted'])
            ->countAllResults();

        $agents = $this->getAgentStats($accountId, $yesterday);
        $totalReps = count($agents);
        $totalTimeLogged = array_sum(array_column($agents, 'hours_logged'));

        $newLeads = (new ContactModel())
            ->where('account_id', $accountId)
            ->where('created_at >=', $todayStart)
            ->countAllResults();

        $messagesSent = (new MessageModel())
            ->where('account_id', $accountId)
            ->where('sender_type', 'agent')
            ->where('created_at >=', $todayStart)
            ->countAllResults();

        $messagesReceived = (new MessageModel())
            ->where('account_id', $accountId)
            ->where('sender_type', 'customer')
            ->where('created_at >=', $todayStart)
            ->countAllResults();

        $appointmentsBooked = (new AppointmentModel())
            ->where('account_id', $accountId)
            ->where('created_at >=', $todayStart)
            ->countAllResults();

        $followUps = $this->getFollowUps($accountId);
        $dispositions = $this->getDispositions($accountId);

        $topAgent = null;
        foreach ($agents as $agent) {
            if ($topAgent === null || $agent['reached'] > $topAgent['reached']) {
                $topAgent = $agent;
            }
        }

        return [
            'account_name'       => $account['name'] ?? 'Your Team',
            'report_date'        => date('l, j F Y'),
            'report_period'      => date('M j, g:i A', strtotime($yesterday)) . ' – ' . date('M j, g:i A'),
            'date'               => date('l, F j, Y', strtotime($yesterday)),
            'team_total_reached' => $teamTotalReached,
            'total_reps'         => $totalReps,
            'total_time_logged'  => $totalTimeLogged,
            'new_leads'          => $newLeads,
            'messages_sent'      => $messagesSent,
            'messages_received'  => $messagesReceived,
            'appointments_booked'=> $appointmentsBooked,
            'follow_up_count'    => count($followUps),
            'hot_lead_count'     => count($dispositions['strong']),
            'top_agent'          => $topAgent,
            'agents'             => $agents,
            'follow_ups'         => $followUps,
            'dispositions'       => $dispositions,
        ];
    }

    public function formatTemplateVariables(array $stats, string $recipientRole = 'executive'): array
    {
        $reps = $stats['total_reps'];
        $repLabel = $reps === 1 ? 'rep' : 'reps';
        $hours = $stats['total_time_logged'] > 0
            ? number_format((float) $stats['total_time_logged'], 1) . ' hrs'
            : '—';

        $summary = sprintf(
            '%d reached · %d %s · %s logged',
            $stats['team_total_reached'],
            $reps,
            $repLabel,
            $hours
        );

        $activity = sprintf(
            '%d sent · %d received · %d new leads',
            $stats['messages_sent'],
            $stats['messages_received'],
            $stats['new_leads']
        );

        $pipeline = sprintf(
            '%d hot leads · %d follow-ups · %d appointments',
            $stats['hot_lead_count'],
            $stats['follow_up_count'],
            $stats['appointments_booked']
        );

        $topLine = 'No agent activity recorded';
        if (!empty($stats['top_agent'])) {
            $topLine = sprintf(
                'Top: %s (%d reached)',
                $stats['top_agent']['name'],
                $stats['top_agent']['reached']
            );
        }

        if ($recipientRole === 'hr') {
            $greeting = $stats['report_date'];
        } else {
            $greeting = $stats['report_date'] . ' · ' . ($stats['account_name'] ?? 'Rovix CRM');
        }

        return [
            'body_1' => $greeting,
            'body_2' => $summary,
            'body_3' => $activity,
            'body_4' => $pipeline,
            'body_5' => $topLine,
        ];
    }

    public function formatMessage(array $stats, string $recipientRole = 'executive'): string
    {
        $divider  = '━━━━━━━━━━━━━━━━━━━━━━━━';
        $section  = '────────────────────────';
        $salutation = $recipientRole === 'hr'
            ? 'Dear HR Team,'
            : 'Dear Founder,';

        $hours = $stats['total_time_logged'] > 0
            ? number_format((float) $stats['total_time_logged'], 1) . ' hrs'
            : '—';
        $repLabel = $stats['total_reps'] === 1 ? 'rep' : 'reps';

        $message  = "{$divider}\n";
        $message .= "  ROVIX CRM · DAILY EXECUTIVE REPORT\n";
        $message .= "{$divider}\n\n";
        $message .= "{$salutation}\n\n";
        $message .= "📅 {$stats['report_date']}\n";
        $message .= "🏢 {$stats['account_name']}\n";
        $message .= "⏱ Period: {$stats['report_period']}\n\n";

        $message .= "{$section}\n";
        $message .= " EXECUTIVE SUMMARY\n";
        $message .= "{$section}\n";
        $message .= "▸ Team Reached      {$stats['team_total_reached']}\n";
        $message .= "▸ Active Reps       {$stats['total_reps']} {$repLabel}\n";
        $message .= "▸ Hours Logged      {$hours}\n";
        $message .= "▸ New Leads         {$stats['new_leads']}\n";
        $message .= "▸ Messages          {$stats['messages_sent']} sent · {$stats['messages_received']} received\n";
        $message .= "▸ Appointments      {$stats['appointments_booked']}\n";
        $message .= "▸ Hot Leads         {$stats['hot_lead_count']}\n";
        $message .= "▸ Follow-ups Due    {$stats['follow_up_count']}\n\n";

        if (!empty($stats['agents'])) {
            $message .= "{$section}\n";
            $message .= " TEAM PERFORMANCE\n";
            $message .= "{$section}\n\n";

            foreach ($stats['agents'] as $agent) {
                $agentHours = $agent['hours_logged'] > 0
                    ? number_format((float) $agent['hours_logged'], 1) . ' hrs'
                    : '—';

                $message .= "👤 *{$agent['name']}*\n";
                $message .= "   Reached: {$agent['reached']}  |  Time: {$agentHours}\n";
                $message .= "   Outreach: Call {$agent['outreach']['call']} · WhatsApp {$agent['outreach']['whatsapp']}\n";
                $message .= "   Pipeline: Reached {$agent['status_changes']['reached_out']}";
                $message .= " · No Response {$agent['status_changes']['no_response']}";
                $message .= " · Follow-up {$agent['status_changes']['follow_up']}\n\n";

                if (!empty($agent['follow_ups'])) {
                    $message .= "   📋 Follow-ups\n";
                    foreach (array_slice($agent['follow_ups'], 0, 3) as $fu) {
                        $name = $fu['name'] ?: $fu['phone'];
                        $company = $fu['company'] ? " ({$fu['company']})" : '';
                        $note = $this->truncateNote($fu['latest_note'] ?? '', 60);
                        $message .= "   • {$name}{$company}\n";
                        $message .= "     {$note}\n";
                    }
                    $message .= "\n";
                }
            }
        }

        if (!empty($stats['dispositions']['strong']) || !empty($stats['dispositions']['could'])) {
            $message .= "{$section}\n";
            $message .= " PIPELINE HIGHLIGHTS\n";
            $message .= "{$section}\n\n";

            foreach ($stats['dispositions']['strong'] as $lead) {
                $message .= $this->formatLeadBlock($lead, $lead['status'] === 'Converted' ? 'Strong' : 'Strong Could');
            }

            foreach (array_slice($stats['dispositions']['could'], 0, 5) as $lead) {
                $message .= $this->formatLeadBlock($lead, 'Could');
            }
        }

        $message .= "{$divider}\n";
        $message .= " Generated by Rovix CRM\n";
        $message .= " Confidential — for internal leadership use only\n";
        $message .= "{$divider}";

        return $message;
    }

    public function renderEmailHtml(array $stats, string $recipientRole = 'executive'): string
    {
        return view('emails/daily_executive_report', [
            'stats'          => $stats,
            'recipientRole'  => $recipientRole,
        ]);
    }

    private function formatLeadBlock(array $lead, string $label): string
    {
        $name = $lead['name'] ?: $lead['phone'];
        $company = $lead['company'] ? "\n   {$lead['company']}" : '';
        $note = $this->truncateNote($lead['latest_note'] ?? '', 80);
        $time = !empty($lead['note_time'])
            ? date('M j, Y · g:i A', strtotime($lead['note_time']))
            : '—';

        $block  = "🔥 *{$label}*\n";
        $block .= "   *{$name}*{$company}\n";
        $block .= "   {$note}\n";
        $block .= "   {$lead['phone']} · {$time}\n\n";

        return $block;
    }

    private function truncateNote(?string $note, int $max): string
    {
        $note = trim((string) $note);
        if ($note === '') {
            return 'No recent note';
        }
        if (mb_strlen($note) <= $max) {
            return $note;
        }

        return mb_substr($note, 0, $max) . '…';
    }

    private function getAgentStats(string $accountId, string $since): array
    {
        $agents = $this->db->table('profiles')
            ->select('profiles.id, profiles.full_name')
            ->where('profiles.account_id', $accountId)
            ->where('profiles.is_active', 1)
            ->get()
            ->getResultArray();

        $agentStats = [];

        foreach ($agents as $agent) {
            $agentId = $agent['id'];

            $timeLogged = $this->db->table('agent_time_logs')
                ->select('hours_logged')
                ->where('account_id', $accountId)
                ->where('agent_id', $agentId)
                ->where('log_date', date('Y-m-d', strtotime($since)))
                ->get()
                ->getRowArray();

            $hoursLogged = $timeLogged ? $timeLogged['hours_logged'] : 0;

            $reached = $this->db->table('contacts')
                ->where('account_id', $accountId)
                ->where('assigned_agent_id', $agentId)
                ->where('updated_at >=', $since)
                ->whereIn('status', ['Contacted', 'Qualified', 'Hot Lead', 'Converted'])
                ->countAllResults();

            $messages = $this->db->table('messages m')
                ->select('m.content_type')
                ->join('conversations c', 'c.id = m.conversation_id')
                ->where('m.account_id', $accountId)
                ->where('c.assigned_agent_id', $agentId)
                ->where('m.sender_type', 'agent')
                ->where('m.created_at >=', $since)
                ->get()
                ->getResultArray();

            $callCount = 0;
            $whatsappCount = 0;

            foreach ($messages as $msg) {
                if (in_array($msg['content_type'], ['audio', 'voice'])) {
                    $callCount++;
                } else {
                    $whatsappCount++;
                }
            }

            $statusChanges = [
                'reached_out' => $this->db->table('contacts')
                    ->where('account_id', $accountId)
                    ->where('assigned_agent_id', $agentId)
                    ->where('status', 'Contacted')
                    ->where('updated_at >=', $since)
                    ->countAllResults(),
                'no_response' => $this->db->table('contacts')
                    ->where('account_id', $accountId)
                    ->where('assigned_agent_id', $agentId)
                    ->where('status', 'Not Interested')
                    ->where('updated_at >=', $since)
                    ->countAllResults(),
                'follow_up' => $this->db->table('contacts')
                    ->where('account_id', $accountId)
                    ->where('assigned_agent_id', $agentId)
                    ->where('status', 'Qualified')
                    ->where('updated_at >=', $since)
                    ->countAllResults(),
            ];

            $followUps = $this->db->query("
                SELECT c.name, c.company, c.phone, c.follow_up_date,
                       (SELECT content_text FROM messages
                        WHERE conversation_id = cv.id
                        ORDER BY created_at DESC LIMIT 1) as latest_note,
                       (SELECT created_at FROM messages
                        WHERE conversation_id = cv.id
                        ORDER BY created_at DESC LIMIT 1) as note_time
                FROM contacts c
                LEFT JOIN conversations cv ON cv.contact_id = c.id AND cv.account_id = c.account_id
                WHERE c.account_id = ?
                AND c.assigned_agent_id = ?
                AND c.follow_up_date IS NOT NULL
                ORDER BY c.follow_up_date ASC
                LIMIT 10
            ", [$accountId, $agentId])->getResultArray();

            if ($reached > 0 || count($followUps) > 0 || $hoursLogged > 0) {
                $agentStats[] = [
                    'name'           => $agent['full_name'],
                    'reached'        => $reached,
                    'hours_logged'   => $hoursLogged,
                    'outreach'       => [
                        'call'     => $callCount,
                        'whatsapp' => $whatsappCount,
                        'general'  => 0,
                    ],
                    'status_changes' => $statusChanges,
                    'follow_ups'     => $followUps,
                ];
            }
        }

        usort($agentStats, fn ($a, $b) => $b['reached'] <=> $a['reached']);

        return $agentStats;
    }

    private function getFollowUps(string $accountId): array
    {
        return $this->db->query("
            SELECT c.name, c.company, c.phone, c.follow_up_date,
                   (SELECT content_text FROM messages
                    WHERE conversation_id = cv.id
                    ORDER BY created_at DESC LIMIT 1) as latest_note,
                   (SELECT created_at FROM messages
                    WHERE conversation_id = cv.id
                    ORDER BY created_at DESC LIMIT 1) as note_time
            FROM contacts c
            LEFT JOIN conversations cv ON cv.contact_id = c.id AND cv.account_id = c.account_id
            WHERE c.account_id = ?
            AND c.follow_up_date IS NOT NULL
            ORDER BY c.follow_up_date ASC
            LIMIT 20
        ", [$accountId])->getResultArray();
    }

    private function getDispositions(string $accountId): array
    {
        $strong = $this->db->query("
            SELECT c.name, c.company, c.phone, c.status,
                   (SELECT content_text FROM messages
                    WHERE conversation_id = cv.id
                    ORDER BY created_at DESC LIMIT 1) as latest_note,
                   (SELECT created_at FROM messages
                    WHERE conversation_id = cv.id
                    ORDER BY created_at DESC LIMIT 1) as note_time
            FROM contacts c
            LEFT JOIN conversations cv ON cv.contact_id = c.id AND cv.account_id = c.account_id
            WHERE c.account_id = ?
            AND c.status IN ('Hot Lead', 'Converted')
            ORDER BY note_time DESC
            LIMIT 10
        ", [$accountId])->getResultArray();

        $could = $this->db->query("
            SELECT c.name, c.company, c.phone, c.status,
                   (SELECT content_text FROM messages
                    WHERE conversation_id = cv.id
                    ORDER BY created_at DESC LIMIT 1) as latest_note,
                   (SELECT created_at FROM messages
                    WHERE conversation_id = cv.id
                    ORDER BY created_at DESC LIMIT 1) as note_time
            FROM contacts c
            LEFT JOIN conversations cv ON cv.contact_id = c.id AND cv.account_id = c.account_id
            WHERE c.account_id = ?
            AND c.status IN ('Contacted', 'Qualified')
            ORDER BY note_time DESC
            LIMIT 10
        ", [$accountId])->getResultArray();

        return [
            'strong' => $strong,
            'could'  => $could,
        ];
    }
}