<?php

namespace App\Http\Requests\Settings;

use App\Data\PushSubscriptionData;
use Illuminate\Foundation\Http\FormRequest;

class DeletePushSubscriptionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'endpoint' => ['required', 'url', 'max:'.PushSubscriptionData::MAX_ENDPOINT_LENGTH],
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
        ];
    }

    public function endpoint(): string
    {
        return (string) $this->validated('endpoint');
    }
}
