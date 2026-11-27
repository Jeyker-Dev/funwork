<?php

namespace App\Services\AiProviders;

use App\Exceptions\AiProviders\AiKeyRejectedException;
use App\Exceptions\AiProviders\AiOutOfCreditException;
use App\Exceptions\AiProviders\AiProviderUnavailableException;
use App\Exceptions\AiProviders\AiRateLimitedException;
use App\Models\AiConnection;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

/**
 * Shared outbound HTTP POST helper for AI provider adapters that enforces
 * configured timeouts and maps provider error responses without leaking keys.
 */
final class AiProviderHttpClient
{
    /**
     * @var list<string>
     */
    private const array BILLING_ERROR_CODES = [
        'insufficient_quota',
        'credit_balance_exhausted',
        'billing_hard_limit_reached',
    ];

    /**
     * @var list<string>
     */
    private const array BILLING_ERROR_TYPES = [
        'insufficient_quota',
        'billing_error',
    ];

    /**
     * @param  array<string, string>  $headers
     * @param  array<string, mixed>  $payload
     */
    public function post(AiConnection $connection, string $url, array $headers, array $payload): Response
    {
        try {
            $response = Http::acceptJson()
                ->withHeaders($headers)
                ->timeout($connection->provider->timeout())
                ->post($url, $payload);
        } catch (ConnectionException) {
            throw AiProviderUnavailableException::forProvider($connection->provider);
        }

        $this->ensureSuccessfulResponse($connection, $response);

        return $response;
    }

    private function ensureSuccessfulResponse(AiConnection $connection, Response $response): void
    {
        if ($response->successful()) {
            return;
        }

        $status = $response->status();
        $provider = $connection->provider;

        if (in_array($status, [HttpResponse::HTTP_UNAUTHORIZED, HttpResponse::HTTP_FORBIDDEN], true)) {
            throw AiKeyRejectedException::forProvider($provider);
        }

        if ($status === HttpResponse::HTTP_PAYMENT_REQUIRED || $this->isBillingError($response, $status)) {
            throw AiOutOfCreditException::forProvider($provider);
        }

        if ($status === HttpResponse::HTTP_TOO_MANY_REQUESTS) {
            if ($provider->isOpenRouter() && ! $connection->allow_paid_models) {
                throw AiRateLimitedException::forOpenRouterFree();
            }

            throw AiRateLimitedException::forProvider($provider);
        }

        if ($status >= HttpResponse::HTTP_INTERNAL_SERVER_ERROR && $status < 600) {
            throw AiProviderUnavailableException::forProvider($provider);
        }

        // Never include request headers, API keys, or raw provider bodies in unexpected error messages.
        throw new RuntimeException("Unexpected response from {$provider->label()} (HTTP {$status}).");
    }

    private function isBillingError(Response $response, int $status): bool
    {
        $errorCode = strtolower((string) $response->json('error.code', ''));
        $errorType = strtolower((string) $response->json('error.type', ''));
        $errorMessage = strtolower((string) $response->json('error.message', ''));

        if (in_array($errorCode, self::BILLING_ERROR_CODES, true)) {
            return true;
        }

        if (in_array($errorType, self::BILLING_ERROR_TYPES, true)) {
            return true;
        }

        return $status === HttpResponse::HTTP_BAD_REQUEST && str_contains($errorMessage, 'credit balance');
    }
}
