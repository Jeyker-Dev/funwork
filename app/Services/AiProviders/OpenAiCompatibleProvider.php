<?php

namespace App\Services\AiProviders;

use App\Contracts\AiProvider as AiProviderContract;
use App\Data\AiProviders\AiChatMessage;
use App\Data\AiProviders\AiReply;
use App\Enums\AiMessageRole;
use App\Models\AiConnection;
use RuntimeException;

/**
 * Adapter for OpenAI-compatible chat completions endpoints (OpenAI, Gemini,
 * DeepSeek, OpenRouter, OpenCode, and user-configured Custom endpoints).
 */
final class OpenAiCompatibleProvider implements AiProviderContract
{
    private const string CHAT_COMPLETIONS_PATH = '/chat/completions';

    private const string FREE_MODEL_SUFFIX = ':free';

    public function __construct(
        private readonly AiProviderHttpClient $httpClient,
    ) {}

    public function chat(AiConnection $connection, array $messages): AiReply
    {
        $baseUrl = rtrim((string) $connection->effectiveBaseUrl(), '/');

        if ($baseUrl === '') {
            throw new RuntimeException("Missing base URL for {$connection->provider->label()}.");
        }

        $response = $this->httpClient->post(
            $connection,
            $baseUrl.self::CHAT_COMPLETIONS_PATH,
            $this->headersFor($connection),
            $this->payloadFor($connection, $messages),
        );

        $text = $response->json('choices.0.message.content');

        if (! is_string($text) || trim($text) === '') {
            throw new RuntimeException("{$connection->provider->label()} returned an empty or invalid response.");
        }

        $model = $response->json('model');
        $answeringModel = is_string($model) && $model !== '' ? $model : $connection->model;

        return new AiReply($text, $answeringModel);
    }

    /**
     * @return array<string, string>
     */
    private function headersFor(AiConnection $connection): array
    {
        $headers = [
            'Authorization' => 'Bearer '.(string) $connection->api_key,
        ];

        if ($connection->provider->isOpenRouter()) {
            $appName = (string) config('app.name', 'FunWork');
            $appUrl = (string) config('app.url', '');

            if ($appName !== '') {
                $headers['X-OpenRouter-Title'] = $appName;
            }

            if ($appUrl !== '') {
                $headers['HTTP-Referer'] = $appUrl;
            }
        }

        return $headers;
    }

    /**
     * @param  list<AiChatMessage>  $messages
     * @return array<string, mixed>
     */
    private function payloadFor(AiConnection $connection, array $messages): array
    {
        $formattedMessages = $this->formatMessages($messages);

        if ($connection->provider->isOpenRouter()) {
            return [
                'models' => $this->openRouterModels($connection),
                'messages' => $formattedMessages,
            ];
        }

        return [
            'model' => $connection->model,
            'messages' => $formattedMessages,
        ];
    }

    /**
     * Orders system instructions ahead of user/assistant turns for OpenAI-compatible APIs.
     *
     * @param  list<AiChatMessage>  $messages
     * @return list<array{role: string, content: string}>
     */
    private function formatMessages(array $messages): array
    {
        $systemMessages = [];
        $conversationMessages = [];

        foreach ($messages as $message) {
            $entry = [
                'role' => $message->role->value,
                'content' => $message->content,
            ];

            if ($message->role === AiMessageRole::System) {
                $systemMessages[] = $entry;
            } else {
                $conversationMessages[] = $entry;
            }
        }

        return [...$systemMessages, ...$conversationMessages];
    }

    /**
     * Builds OpenRouter's fallback `models` list, keeping requests free-only unless paid fallback is enabled.
     *
     * @return list<string>
     */
    private function openRouterModels(AiConnection $connection): array
    {
        $freeModels = $connection->provider->suggestedModels();
        $selectedModel = trim($connection->model);
        $selectedIsFree = $selectedModel !== '' && str_ends_with($selectedModel, self::FREE_MODEL_SUFFIX);

        $orderedFree = $selectedIsFree
            ? array_values(array_unique([$selectedModel, ...$freeModels]))
            : $freeModels;

        if (! $connection->allow_paid_models) {
            return $orderedFree;
        }

        $paidModels = $connection->provider->paidFallbackModels();
        $orderedPaid = (! $selectedIsFree && $selectedModel !== '')
            ? array_values(array_unique([$selectedModel, ...$paidModels]))
            : $paidModels;

        return array_values(array_unique([...$orderedFree, ...$orderedPaid]));
    }
}
