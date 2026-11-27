<?php

namespace App\Actions\AiConnections;

use App\Data\AiConnections\AiConnectionData;
use App\Enums\AiProvider;
use App\Exceptions\AiConnections\EmptyAiModelException;
use App\Exceptions\AiConnections\InvalidAiBaseUrlException;

/**
 * Validates and normalizes AI connection attributes according to provider rules.
 */
final class ValidateAiConnectionAction
{
    /**
     * @return array{provider: AiProvider, label: ?string, base_url: ?string, model: string, allow_paid_models: bool}
     */
    public function __invoke(AiConnectionData $input): array
    {
        $model = trim($input->model);

        if ($model === '') {
            throw EmptyAiModelException::make();
        }

        $label = $input->label !== null ? trim($input->label) : '';

        return [
            'provider' => $input->provider,
            'label' => $label !== '' ? $label : null,
            'base_url' => $this->resolveBaseUrl($input->provider, $input->baseUrl),
            'model' => $model,
            'allow_paid_models' => $input->provider->isOpenRouter() && $input->allowPaidModels,
        ];
    }

    private function resolveBaseUrl(AiProvider $provider, ?string $baseUrl): ?string
    {
        if (! $provider->isCustom()) {
            return null;
        }

        $normalized = rtrim(trim((string) $baseUrl), '/');

        if (
            $normalized === ''
            || filter_var($normalized, FILTER_VALIDATE_URL) === false
            || strtolower((string) parse_url($normalized, PHP_URL_SCHEME)) !== 'https'
            || blank(parse_url($normalized, PHP_URL_HOST))
        ) {
            throw InvalidAiBaseUrlException::forValue($baseUrl);
        }

        return $normalized;
    }
}
