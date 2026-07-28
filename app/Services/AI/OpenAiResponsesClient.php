<?php

namespace App\Services\AI;

use App\Exceptions\AiAgentException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;

class OpenAiResponsesClient
{
    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function create(array $payload): array
    {
        $apiKey = (string) config('appraisal.ai.openai.api_key');
        if ($apiKey === '') {
            throw new AiAgentException(
                'openai_not_configured',
                'OpenAI API belum dikonfigurasi untuk fallback appraisal.',
            );
        }

        $headers = [];
        if (filled(config('appraisal.ai.openai.organization'))) {
            $headers['OpenAI-Organization'] = (string) config(
                'appraisal.ai.openai.organization',
            );
        }
        if (filled(config('appraisal.ai.openai.project'))) {
            $headers['OpenAI-Project'] = (string) config(
                'appraisal.ai.openai.project',
            );
        }

        try {
            $response = Http::baseUrl('https://api.openai.com/v1')
                ->withToken($apiKey)
                ->withHeaders($headers)
                ->acceptJson()
                ->asJson()
                ->timeout((int) config('appraisal.ai.openai.timeout_seconds'))
                ->connectTimeout((int) config('appraisal.ai.openai.connect_timeout_seconds'))
                ->post('/responses', $payload)
                ->throw();
        } catch (ConnectionException $exception) {
            throw new AiAgentException(
                'openai_connection_failed',
                'OpenAI API tidak dapat dihubungi.',
                $exception,
            );
        } catch (RequestException $exception) {
            $status = $exception->response->status();
            $code = match (true) {
                $status === 401 || $status === 403 => 'openai_auth_failed',
                $status === 429 => 'openai_rate_limited',
                $status >= 500 => 'openai_server_error',
                default => 'openai_request_rejected',
            };

            throw new AiAgentException(
                $code,
                'OpenAI API menolak permintaan fallback appraisal.',
                $exception,
            );
        }

        $decoded = $response->json();
        if (! is_array($decoded)) {
            throw new AiAgentException(
                'openai_invalid_response',
                'OpenAI API mengembalikan respons yang tidak valid.',
            );
        }

        return $decoded;
    }
}
