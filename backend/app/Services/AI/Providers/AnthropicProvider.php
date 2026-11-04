<?php

namespace App\Services\AI\Providers;

use Anthropic\Client;
use Anthropic\Core\Exceptions\APIConnectionException;
use Anthropic\Core\Exceptions\APIException;
use Anthropic\Core\Exceptions\APIStatusException;
use Anthropic\Core\Exceptions\RateLimitException;
use App\Services\AI\Contracts\LlmProvider;
use App\Services\AI\LlmException;
use App\Services\AI\LlmResponse;

class AnthropicProvider implements LlmProvider
{
    public function name(): string
    {
        return 'anthropic';
    }

    public function isConfigured(): bool
    {
        return filled(config('deenmate.ai.api_key'));
    }

    public function complete(string $system, array $messages, array $jsonSchema): LlmResponse
    {
        $client = new Client(
            apiKey: config('deenmate.ai.api_key'),
            baseUrl: config('deenmate.ai.base_url') ?: null,
            requestOptions: ['timeout' => (float) config('deenmate.ai.request_timeout')],
        );

        try {
            // Server-side fallback retries a policy decline on a suitable model; a final
            // decline still surfaces as stop_reason "refusal" and is handled below.
            $response = $client->beta->messages->create(
                maxTokens: (int) config('deenmate.ai.max_output_tokens'),
                messages: $messages,
                model: config('deenmate.ai.model'),
                fallbacks: 'default',
                outputConfig: [
                    'effort' => config('deenmate.ai.effort'),
                    'format' => ['type' => 'json_schema', 'schema' => $jsonSchema],
                ],
                system: $system,
                betas: ['server-side-fallback-2026-07-01'],
            );
        } catch (RateLimitException $e) {
            throw new LlmException('The AI provider is rate limiting requests.', true);
        } catch (APIConnectionException $e) {
            throw new LlmException('Could not reach the AI provider.', true);
        } catch (APIStatusException $e) {
            throw new LlmException('The AI provider returned an error (HTTP '.$e->getCode().').', $e->getCode() >= 500);
        } catch (APIException $e) {
            throw new LlmException('The AI provider request failed.');
        }

        $text = '';
        foreach ($response->content as $block) {
            if (($block->type ?? null) === 'text') {
                $text .= $block->text;
            }
        }

        return new LlmResponse(
            text: $text,
            inputTokens: $response->usage->inputTokens,
            outputTokens: $response->usage->outputTokens,
            model: (string) (is_string($response->model) ? $response->model : $response->model->value),
            refused: (is_string($response->stopReason) ? $response->stopReason : $response->stopReason?->value) === 'refusal',
        );
    }
}
