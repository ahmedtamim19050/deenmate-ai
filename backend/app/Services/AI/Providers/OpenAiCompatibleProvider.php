<?php

namespace App\Services\AI\Providers;

use App\Services\AI\Contracts\LlmProvider;
use App\Services\AI\LlmException;
use App\Services\AI\LlmResponse;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

/** Any endpoint that implements POST {base}/chat/completions with json_schema output. */
class OpenAiCompatibleProvider implements LlmProvider
{
    public function name(): string
    {
        return 'openai_compatible';
    }

    public function isConfigured(): bool
    {
        return filled(config('deenmate.ai.api_key')) && filled(config('deenmate.ai.base_url'));
    }

    public function complete(string $system, array $messages, array $jsonSchema): LlmResponse
    {
        try {
            $response = Http::withToken(config('deenmate.ai.api_key'))
                ->timeout((int) config('deenmate.ai.request_timeout'))
                ->post(rtrim(config('deenmate.ai.base_url'), '/').'/chat/completions', [
                    'model' => config('deenmate.ai.model'),
                    'max_tokens' => (int) config('deenmate.ai.max_output_tokens'),
                    'messages' => array_merge([['role' => 'system', 'content' => $system]], $messages),
                    'response_format' => [
                        'type' => 'json_schema',
                        'json_schema' => ['name' => 'islamic_answer', 'strict' => true, 'schema' => $jsonSchema],
                    ],
                ]);
        } catch (ConnectionException) {
            throw new LlmException('Could not reach the AI provider.', true);
        }

        if ($response->failed()) {
            throw new LlmException('The AI provider returned an error (HTTP '.$response->status().').', $response->serverError() || $response->status() === 429);
        }

        return new LlmResponse(
            text: (string) $response->json('choices.0.message.content', ''),
            inputTokens: (int) $response->json('usage.prompt_tokens', 0),
            outputTokens: (int) $response->json('usage.completion_tokens', 0),
            model: (string) $response->json('model', config('deenmate.ai.model')),
            refused: filled($response->json('choices.0.message.refusal')),
        );
    }
}
