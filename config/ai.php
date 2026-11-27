<?php

return [

    /*
    |--------------------------------------------------------------------------
    | AI Providers Configuration
    |--------------------------------------------------------------------------
    |
    | Base URLs, request timeouts (in seconds), and suggested model lists for
    | user-configured AI connections. Model lists change often as providers
    | release or deprecate models, so treat these lists as editable defaults;
    | users can also type any model ID in Settings -> Integrations.
    |
    */

    'providers' => [

        // Official docs: https://platform.openai.com/docs/api-reference/chat
        'openai' => [
            'base_url' => 'https://api.openai.com/v1',
            'timeout' => 60,
            'models' => [
                'gpt-5',
                'gpt-5-mini',
                'gpt-4.1',
                'gpt-4.1-mini',
            ],
        ],

        // Official docs: https://platform.claude.com/docs/en/api/messages
        // Versioning docs: https://platform.claude.com/docs/en/api/versioning
        // Uses Anthropic's native Messages API (POST {base_url}/v1/messages), not its testing-only OpenAI shim.
        'anthropic' => [
            'base_url' => 'https://api.anthropic.com',
            'version' => '2023-06-01',
            'max_tokens' => 4096,
            'timeout' => 60,
            'models' => [
                'claude-sonnet-5-5',
                'claude-haiku-5-5',
                'claude-opus-5-5',
            ],
        ],

        // Official docs: https://ai.google.dev/gemini-api/docs/openai
        'gemini' => [
            'base_url' => 'https://generativelanguage.googleapis.com/v1beta/openai',
            'timeout' => 60,
            'models' => [
                'gemini-3.6-flash',
                'gemini-3.5-flash-lite',
                'gemini-3.1-pro-preview',
            ],
        ],

        // Official docs: https://api-docs.deepseek.com/
        'deepseek' => [
            'base_url' => 'https://api.deepseek.com/v1',
            'timeout' => 60,
            'models' => [
                'deepseek-chat',
                'deepseek-reasoner',
            ],
        ],

        // Official docs: https://openrouter.ai/docs/api-reference/overview
        'openrouter' => [
            'base_url' => 'https://openrouter.ai/api/v1',
            'timeout' => 60,
            'models' => [
                'free' => [
                    'meta-llama/llama-3.3-70b-instruct:free',
                    'deepseek/deepseek-r1:free',
                    'mistralai/mistral-small-3.1-24b-instruct:free',
                ],
                'paid' => [
                    'anthropic/claude-sonnet-5-5',
                    'openai/gpt-5-mini',
                    'google/gemini-3.6-flash',
                ],
            ],
        ],

        // Official docs: https://opencode.ai/docs/zen/
        'opencode' => [
            'base_url' => 'https://opencode.ai/zen/v1',
            'timeout' => 60,
            'models' => [
                'deepseek-v4-pro',
                'glm-5.2',
                'minimax-m3',
            ],
        ],

        // User-supplied OpenAI-compatible HTTPS endpoint.
        'custom' => [
            'base_url' => null,
            'timeout' => 60,
            'models' => [],
        ],

    ],

];
