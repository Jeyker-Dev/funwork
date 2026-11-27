<?php

namespace App\Services\AiProviders;

use App\Contracts\AiProvider as AiProviderContract;
use App\Data\AiProviders\AiChatMessage;
use App\Data\AiProviders\AiReply;
use App\Enums\AiMessageRole;
use App\Models\AiConnection;
use RuntimeException;

/**
 * Native Messages API adapter for Anthropic.
 *
 * Official docs: https://platform.claude.com/docs/en/api/messages
 * Versioning: https://platform.claude.com/docs/en/api/versioning
 */
final class AnthropicProvider implements AiProviderContract
{
    private const string MESSAGES_PATH = '/v1/messages';

    private const string DEFAULT_API_VERSION = '2023-06-01';

    private const int DEFAULT_MAX_TOKENS = 4096;

    public function __construct(
        private readonly AiProviderHttpClient $httpClient,
    ) {}

    public function chat(AiConnection $connection, array $messages): AiReply
    {
        $baseUrl = rtrim((string) $connection->effectiveBaseUrl(), '/');

        if ($baseUrl === '') {
            throw new RuntimeException('Missing base URL for Anthropic.');
        }

        $endpoint = str_ends_with($baseUrl, '/v1')
            ? $baseUrl.'/messages'
            : $baseUrl.self::MESSAGES_PATH;

        $response = $this->httpClient->post(
            $connection,
            $endpoint,
            [
                'x-api-key' => (string) $connection->api_key,
                'anthropic-version' => (string) config('ai.providers.anthropic.version', self::DEFAULT_API_VERSION),
            ],
            $this->payloadFor($connection, $messages),
        );

        $text = $this->extractText($response->json('content'));

        if ($text === '') {
            throw new RuntimeException('Anthropic returned an empty or invalid response.');
        }

        $model = $response->json('model');
        $answeringModel = is_string($model) && $model !== '' ? $model : $connection->model;

        return new AiReply($text, $answeringModel);
    }

    /**
     * @param  list<AiChatMessage>  $messages
     * @return array<string, mixed>
     */
    private function payloadFor(AiConnection $connection, array $messages): array
    {
        $systemParts = [];
        $conversationMessages = [];

        foreach ($messages as $message) {
            if ($message->role === AiMessageRole::System) {
                if (trim($message->content) !== '') {
                    $systemParts[] = $message->content;
                }

                continue;
            }

            $conversationMessages[] = [
                'role' => $message->role->value,
                'content' => $message->content,
            ];
        }

        $payload = [
            'model' => $connection->model,
            'max_tokens' => (int) config('ai.providers.anthropic.max_tokens', self::DEFAULT_MAX_TOKENS),
            'messages' => $conversationMessages,
        ];

        $systemPrompt = implode("\n\n", $systemParts);

        if ($systemPrompt !== '') {
            $payload['system'] = $systemPrompt;
        }

        return $payload;
    }

    private function extractText(mixed $contentBlocks): string
    {
        if (! is_array($contentBlocks)) {
            return '';
        }

        $textParts = [];

        foreach ($contentBlocks as $block) {
            if (is_array($block) && ($block['type'] ?? null) === 'text' && is_string($block['text'] ?? null)) {
                $textParts[] = $block['text'];
            }
        }

        return implode("\n", $textParts);
    }
}
