<?php

namespace App\Services\Google;

use App\Models\GoogleAccount;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Throwable;

/**
 * Minimal Google Drive client for publishing Markdown as a Google Doc.
 *
 * Uses the drive.file scope, so it only reaches files this app created.
 */
class GoogleDriveClient
{
    public const SCOPE = 'https://www.googleapis.com/auth/drive.file';

    private const TOKEN_URL = 'https://oauth2.googleapis.com/token';

    private const REVOKE_URL = 'https://oauth2.googleapis.com/revoke';

    private const UPLOAD_URL = 'https://www.googleapis.com/upload/drive/v3/files';

    private const GOOGLE_DOC_MIME = 'application/vnd.google-apps.document';

    /**
     * Creates a Google Doc converted from Markdown and returns its file ID.
     */
    public function createDocument(GoogleAccount $account, string $title, string $markdown): string
    {
        $boundary = 'funwork-'.Str::random(16);
        $metadata = json_encode(['name' => $title, 'mimeType' => self::GOOGLE_DOC_MIME]);

        $body = "--{$boundary}\r\n"
            ."Content-Type: application/json; charset=UTF-8\r\n\r\n"
            ."{$metadata}\r\n"
            ."--{$boundary}\r\n"
            ."Content-Type: text/markdown; charset=UTF-8\r\n\r\n"
            ."{$markdown}\r\n"
            ."--{$boundary}--";

        $response = $this->send(
            $account,
            fn (string $token) => Http::withToken($token)
                ->withBody($body, "multipart/related; boundary={$boundary}")
                ->post(self::UPLOAD_URL.'?uploadType=multipart&fields=id'),
        );

        return $response->throw()->json('id');
    }

    /**
     * Replaces the whole content of an existing Google Doc, keeping its ID.
     *
     * Returns false when the Doc no longer exists for this account (deleted,
     * trashed, or created by another Google account), so the caller can
     * create a new one.
     */
    public function replaceDocument(GoogleAccount $account, string $fileId, string $markdown): bool
    {
        $response = $this->send(
            $account,
            fn (string $token) => Http::withToken($token)
                ->withBody($markdown, 'text/markdown; charset=UTF-8')
                ->patch(self::UPLOAD_URL.'/'.rawurlencode($fileId).'?uploadType=media&fields=id,trashed'),
        );

        if ($response->notFound()) {
            return false;
        }

        return ! $response->throw()->json('trashed', false);
    }

    /**
     * Revokes the app's access. Failures are ignored: the local tokens are
     * removed anyway, and the user can also revoke from their Google account.
     */
    public function revoke(GoogleAccount $account): void
    {
        try {
            Http::asForm()->post(self::REVOKE_URL, [
                'token' => $account->refresh_token ?? $account->access_token,
            ]);
        } catch (Throwable $e) {
            report($e);
        }
    }

    /**
     * Sends a request with a valid access token, refreshing it first when it
     * has expired, and once more if Google answers 401.
     *
     * @param  callable(string): Response  $request
     */
    private function send(GoogleAccount $account, callable $request): Response
    {
        if ($account->accessTokenExpired()) {
            $this->refresh($account);
        }

        $response = $request($account->access_token);

        if ($response->unauthorized()) {
            $this->refresh($account);
            $response = $request($account->access_token);

            if ($response->unauthorized()) {
                throw new GoogleAuthorizationException('Google rejected the access token.');
            }
        }

        return $response;
    }

    private function refresh(GoogleAccount $account): void
    {
        if (blank($account->refresh_token)) {
            throw new GoogleAuthorizationException('There is no refresh token for this Google account.');
        }

        $response = Http::asForm()->post(self::TOKEN_URL, [
            'client_id' => config('services.google.client_id'),
            'client_secret' => config('services.google.client_secret'),
            'refresh_token' => $account->refresh_token,
            'grant_type' => 'refresh_token',
        ]);

        if ($response->json('error') === 'invalid_grant') {
            throw new GoogleAuthorizationException('Google revoked or expired the refresh token.');
        }

        $response->throw();

        $account->forceFill([
            'access_token' => $response->json('access_token'),
            'expires_at' => now()->addSeconds((int) $response->json('expires_in', 3600)),
        ])->save();
    }
}
