<?php

namespace App\Libraries;

class GoogleCalendar
{
    private const AUTH_URL     = 'https://accounts.google.com/o/oauth2/auth';
    private const TOKEN_URL    = 'https://oauth2.googleapis.com/token';
    private const CALENDAR_URL = 'https://www.googleapis.com/calendar/v3';
    private const SCOPE        = 'https://www.googleapis.com/auth/calendar https://www.googleapis.com/auth/calendar.events';

    public function getAuthUrl(string $redirectUri): string
    {
        return self::AUTH_URL . '?' . http_build_query([
            'client_id'     => env('GOOGLE_CLIENT_ID'),
            'redirect_uri'  => $redirectUri,
            'response_type' => 'code',
            'scope'         => self::SCOPE,
            'access_type'   => 'offline',
            'prompt'        => 'consent',
        ]);
    }

    public function exchangeCode(string $code, string $redirectUri): array
    {
        return $this->postToken([
            'code'          => $code,
            'client_id'     => env('GOOGLE_CLIENT_ID'),
            'client_secret' => env('GOOGLE_CLIENT_SECRET'),
            'redirect_uri'  => $redirectUri,
            'grant_type'    => 'authorization_code',
        ]);
    }

    public function refreshToken(string $refreshToken): array
    {
        return $this->postToken([
            'refresh_token' => $refreshToken,
            'client_id'     => env('GOOGLE_CLIENT_ID'),
            'client_secret' => env('GOOGLE_CLIENT_SECRET'),
            'grant_type'    => 'refresh_token',
        ]);
    }

    public function getValidToken(array $tokenRow): string
    {
        $expiresAt = strtotime($tokenRow['expires_at'] ?? '');
        if ($expiresAt && $expiresAt > time() + 60) {
            return $tokenRow['access_token'];
        }

        if (empty($tokenRow['refresh_token'])) {
            throw new \RuntimeException('Google token expired and no refresh token available');
        }

        $refreshed = $this->refreshToken($tokenRow['refresh_token']);

        if (empty($refreshed['access_token'])) {
            throw new \RuntimeException('Google token refresh failed: ' . json_encode($refreshed));
        }

        // Update token in DB
        \Config\Database::connect()->table('google_oauth_tokens')
            ->where('account_id', $tokenRow['account_id'])
            ->update([
                'access_token' => $refreshed['access_token'],
                'expires_at'   => date('Y-m-d H:i:s', time() + ($refreshed['expires_in'] ?? 3600)),
                'updated_at'   => date('Y-m-d H:i:s'),
            ]);

        return $refreshed['access_token'];
    }

    public function createEvent(
        string $accessToken,
        string $calendarId,
        string $title,
        string $description,
        string $startDateTime,
        string $endDateTime,
        string $timezone = 'Asia/Kolkata',
        string $attendeeEmail = ''
    ): array {
        $body = [
            'summary'     => $title,
            'description' => $description,
            'start'       => ['dateTime' => $startDateTime, 'timeZone' => $timezone],
            'end'         => ['dateTime' => $endDateTime,   'timeZone' => $timezone],
            'conferenceData' => [
                'createRequest' => [
                    'requestId'             => uniqid(),
                    'conferenceSolutionKey' => ['type' => 'hangoutsMeet'],
                ],
            ],
        ];

        if ($attendeeEmail) {
            $body['attendees'] = [['email' => $attendeeEmail]];
        }

        return $this->callCalendar(
            'POST',
            "/calendars/{$calendarId}/events?conferenceDataVersion=1&sendUpdates=all",
            $accessToken,
            $body
        );
    }

    public function deleteEvent(string $accessToken, string $calendarId, string $eventId): void
    {
        $this->callCalendar('DELETE', "/calendars/{$calendarId}/events/{$eventId}", $accessToken);
    }

    public function updateEventTime(
        string $accessToken,
        string $calendarId,
        string $eventId,
        string $startDateTime,
        string $endDateTime,
        string $timezone = 'Asia/Kolkata'
    ): array {
        return $this->callCalendar(
            'PATCH',
            "/calendars/{$calendarId}/events/{$eventId}?sendUpdates=all",
            $accessToken,
            [
                'start' => ['dateTime' => $startDateTime, 'timeZone' => $timezone],
                'end'   => ['dateTime' => $endDateTime,   'timeZone' => $timezone],
            ]
        );
    }

    private function callCalendar(string $method, string $path, string $accessToken, ?array $body = null): array
    {
        $ch = curl_init(self::CALENDAR_URL . $path);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 15,
            CURLOPT_HTTPHEADER     => [
                'Authorization: Bearer ' . $accessToken,
                'Content-Type: application/json',
            ],
        ]);

        if ($method === 'POST') {
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body));
        } elseif ($method === 'PATCH') {
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PATCH');
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body));
        } elseif ($method === 'DELETE') {
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'DELETE');
        }

        $response = curl_exec($ch);
        $status   = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($status >= 400) {
            throw new \RuntimeException("Google Calendar API error [{$status}]: {$response}");
        }

        return $response ? (json_decode($response, true) ?? []) : [];
    }

    private function postToken(array $params): array
    {
        $ch = curl_init(self::TOKEN_URL);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => http_build_query($params),
            CURLOPT_HTTPHEADER     => ['Content-Type: application/x-www-form-urlencoded'],
            CURLOPT_TIMEOUT        => 15,
        ]);
        $result = curl_exec($ch);
        curl_close($ch);
        return json_decode($result, true) ?? [];
    }
}
