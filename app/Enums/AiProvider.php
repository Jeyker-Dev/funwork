<?php

namespace App\Enums;

enum AiProvider: string
{
    case OpenAi = 'openai';
    case Anthropic = 'anthropic';
    case Gemini = 'gemini';
    case DeepSeek = 'deepseek';
    case OpenRouter = 'openrouter';
    case OpenCode = 'opencode';
    case Custom = 'custom';

    private const int DEFAULT_TIMEOUT_SECONDS = 60;

    public function label(): string
    {
        return match ($this) {
            self::OpenAi => 'OpenAI',
            self::Anthropic => 'Anthropic',
            self::Gemini => 'Google Gemini',
            self::DeepSeek => 'DeepSeek',
            self::OpenRouter => 'OpenRouter',
            self::OpenCode => 'OpenCode',
            self::Custom => 'Custom (OpenAI-compatible)',
        };
    }

    public function defaultBaseUrl(): ?string
    {
        if ($this === self::Custom) {
            return null;
        }

        $url = config("ai.providers.{$this->value}.base_url");

        return is_string($url) && $url !== '' ? $url : null;
    }

    public function isOpenRouter(): bool
    {
        return $this === self::OpenRouter;
    }

    public function isCustom(): bool
    {
        return $this === self::Custom;
    }

    public function timeout(): int
    {
        return (int) config("ai.providers.{$this->value}.timeout", self::DEFAULT_TIMEOUT_SECONDS);
    }

    /**
     * Suggested model IDs for this provider. For OpenRouter, returns only the :free models
     * so requests stay free by default unless paid fallbacks are explicitly enabled.
     *
     * @return list<string>
     */
    public function suggestedModels(): array
    {
        if ($this->isOpenRouter()) {
            return array_values(config('ai.providers.openrouter.models.free', []));
        }

        return array_values(config("ai.providers.{$this->value}.models", []));
    }

    /**
     * Paid fallback model IDs appended only when an OpenRouter connection enables paid models.
     *
     * @return list<string>
     */
    public function paidFallbackModels(): array
    {
        if (! $this->isOpenRouter()) {
            return [];
        }

        return array_values(config('ai.providers.openrouter.models.paid', []));
    }
}
