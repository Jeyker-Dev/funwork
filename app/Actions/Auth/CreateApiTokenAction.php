<?php

namespace App\Actions\Auth;

use App\Data\CreatedApiTokenData;
use App\Models\ApiToken;
use App\Models\User;
use Illuminate\Support\Str;

class CreateApiTokenAction
{
    public function execute(User $user, string $name): CreatedApiTokenData
    {
        $plainTextToken = ApiToken::TOKEN_PREFIX.Str::random(40);

        $apiToken = $user->apiTokens()->create([
            'name' => $name,
            'token' => ApiToken::hashToken($plainTextToken),
        ]);

        return new CreatedApiTokenData(
            apiToken: $apiToken,
            plainTextToken: $plainTextToken,
        );
    }

    public function __invoke(User $user, string $name): CreatedApiTokenData
    {
        return $this->execute($user, $name);
    }
}
