<?php

namespace App\Libraries;

use App\Models\AiConfigModel;
use App\Models\AiUsageLogModel;
use App\Libraries\WhatsApp\Encryption;

/**
 * Thin shared wrapper around OpenAI's Chat Completions API — used by the
 * flow builder's AI Node and the inbox's translate/rewrite assist features,
 * so the request/response/error handling only lives in one place.
 */
class OpenAiClient
{
    // OpenAI has no public "check my balance" endpoint for a regular API
    // key, so usage is tracked from what each response actually reports.
    // Published per-1M-token rates (USD) — approximate, OpenAI can change
    // these; treat cost_estimate as a guide, not an invoice.
    private const PRICING = [
        'gpt-4o-mini' => ['input' => 0.150,  'output' => 0.600],
        'gpt-4o'      => ['input' => 2.50,   'output' => 10.00],
        'gpt-4-turbo' => ['input' => 10.00,  'output' => 30.00],
    ];

    public static function chat(string $accountId, array $messages, ?string $model = null, int $maxTokens = 500, string $feature = 'unknown'): array
    {
        $aiConfig = (new AiConfigModel())->where('account_id', $accountId)->first();
        if (!$aiConfig || empty($aiConfig['api_key'])) {
            return ['error' => 'AI is not set up yet — add your OpenAI key in Settings → AI.'];
        }

        $model = $model ?: ($aiConfig['model'] ?? 'gpt-4o-mini');

        try {
            $apiKey = (new Encryption())->decrypt($aiConfig['api_key']);
            $client = \Config\Services::curlrequest([
                'timeout'     => 30,
                'http_errors' => false,
                'headers'     => [
                    'Authorization' => 'Bearer ' . $apiKey,
                    'Content-Type'  => 'application/json',
                ],
            ]);
            $response = $client->post('https://api.openai.com/v1/chat/completions', [
                'json' => ['model' => $model, 'messages' => $messages, 'max_tokens' => $maxTokens],
            ]);
            $result = json_decode((string) $response->getBody(), true) ?? [];

            if (isset($result['error'])) {
                log_message('error', '[OpenAiClient] API error: ' . ($result['error']['message'] ?? 'unknown'));
                return ['error' => $result['error']['message'] ?? 'OpenAI error'];
            }

            $text = trim($result['choices'][0]['message']['content'] ?? '');
            if ($text === '') {
                return ['error' => 'AI returned an empty response'];
            }

            self::logUsage($accountId, $feature, $model, $result['usage'] ?? []);

            return ['text' => $text];
        } catch (\Exception $e) {
            log_message('error', '[OpenAiClient] request failed: ' . $e->getMessage());
            return ['error' => 'AI request failed'];
        }
    }

    private static function logUsage(string $accountId, string $feature, string $model, array $usage): void
    {
        $promptTokens     = (int) ($usage['prompt_tokens']     ?? 0);
        $completionTokens = (int) ($usage['completion_tokens'] ?? 0);
        $totalTokens      = (int) ($usage['total_tokens']      ?? ($promptTokens + $completionTokens));

        $rates = self::PRICING[$model] ?? self::PRICING['gpt-4o-mini'];
        $cost  = ($promptTokens / 1_000_000 * $rates['input']) + ($completionTokens / 1_000_000 * $rates['output']);

        try {
            (new AiUsageLogModel())->insert([
                'account_id'        => $accountId,
                'feature'           => $feature,
                'model'             => $model,
                'prompt_tokens'     => $promptTokens,
                'completion_tokens' => $completionTokens,
                'total_tokens'      => $totalTokens,
                'cost_estimate'     => round($cost, 6),
                'created_at'        => date('Y-m-d H:i:s'),
            ]);
        } catch (\Exception $e) {
            // Usage logging must never break the actual AI feature.
            log_message('error', '[OpenAiClient] usage log failed: ' . $e->getMessage());
        }
    }
}
