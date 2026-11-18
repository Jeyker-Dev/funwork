<?php

namespace App\Http\Requests\Settings;

use App\Data\PushSubscriptionData;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Minishlink\WebPush\ContentEncoding;

class StorePushSubscriptionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'endpoint' => ['required', 'url', 'max:'.PushSubscriptionData::MAX_ENDPOINT_LENGTH],
            'keys' => ['required', 'array'],
            'keys.p256dh' => ['required', 'string', 'max:'.PushSubscriptionData::MAX_KEY_LENGTH],
            'keys.auth' => ['required', 'string', 'max:'.PushSubscriptionData::MAX_KEY_LENGTH],
            'contentEncoding' => ['nullable', 'string', Rule::enum(ContentEncoding::class)],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'endpoint.required' => 'A push subscription endpoint is required.',
            'endpoint.url' => 'The push subscription endpoint must be a valid URL.',
            'keys.required' => 'Push encryption keys are required.',
            'keys.p256dh.required' => 'The push public key (p256dh) is required.',
            'keys.auth.required' => 'The push authentication secret (auth) is required.',
            'contentEncoding.enum' => 'Unsupported push content encoding.',
        ];
    }

    public function toDto(): PushSubscriptionData
    {
        /** @var array{endpoint: string, keys: array{p256dh: string, auth: string}, contentEncoding?: ?string} $validated */
        $validated = $this->validated();

        return new PushSubscriptionData(
            endpoint: $validated['endpoint'],
            publicKey: $validated['keys']['p256dh'],
            authToken: $validated['keys']['auth'],
            contentEncoding: $validated['contentEncoding'] ?? null,
        );
    }
}
