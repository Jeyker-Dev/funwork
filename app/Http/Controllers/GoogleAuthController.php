<?php

namespace App\Http\Controllers;

use App\Models\GoogleAccount;
use App\Services\Google\GoogleDriveClient;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\InvalidStateException;
use Symfony\Component\HttpFoundation\RedirectResponse as SymfonyRedirectResponse;

class GoogleAuthController extends Controller
{
    public function redirect(): SymfonyRedirectResponse
    {
        return Socialite::driver('google')
            ->scopes([GoogleDriveClient::SCOPE])
            ->with(['access_type' => 'offline', 'prompt' => 'consent'])
            ->redirect();
    }

    public function callback(Request $request): RedirectResponse
    {
        if ($request->filled('error')) {
            return $this->failed($request, __('The Google connection was cancelled.'));
        }

        try {
            $googleUser = Socialite::driver('google')->user();
        } catch (InvalidStateException) {
            return $this->failed($request, __('The Google connection expired. Try again.'));
        }

        if (! in_array(GoogleDriveClient::SCOPE, $googleUser->approvedScopes ?? [], true)) {
            return $this->failed($request, __('FunWork needs access to the Google Drive files it creates. Allow it and try again.'));
        }

        $account = GoogleAccount::firstOrNew(['user_id' => $request->user()->id]);

        $account->fill([
            'google_id' => $googleUser->getId(),
            'email' => $googleUser->getEmail(),
            'access_token' => $googleUser->token,
            'refresh_token' => $googleUser->refreshToken ?: $account->refresh_token,
            'expires_at' => now()->addSeconds((int) ($googleUser->expiresIn ?? 3600)),
        ])->save();

        return redirect($this->returnPath($request))
            ->with('google_status', __('Google account connected.'));
    }

    /**
     * Only same-site paths are followed, so the callback cannot be turned
     * into an open redirect.
     */
    private function returnPath(Request $request): string
    {
        $path = $request->session()->pull('google_return_to');

        // Browsers read "/\host" like "//host", so backslashes are rejected too.
        if (is_string($path) && str_starts_with($path, '/') && ! str_starts_with($path, '//') && ! str_contains($path, '\\')) {
            return $path;
        }

        return route('integrations.edit');
    }

    private function failed(Request $request, string $message): RedirectResponse
    {
        $request->session()->forget('google_return_to');

        return redirect()->route('integrations.edit')->with('google_error', $message);
    }
}
