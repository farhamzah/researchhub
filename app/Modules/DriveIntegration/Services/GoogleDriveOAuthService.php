<?php

namespace App\Modules\DriveIntegration\Services;

use Google\Client;
use Google\Service\Oauth2;
use Illuminate\Support\Arr;
use RuntimeException;

class GoogleDriveOAuthService
{
    public function authorizationUrl(string $state): string
    {
        return $this->client($state)->createAuthUrl();
    }

    /**
     * @return array<string, mixed>
     */
    public function fetchToken(string $code): array
    {
        $token = $this->client()->fetchAccessTokenWithAuthCode($code);

        if (isset($token['error'])) {
            throw new RuntimeException($this->safeErrorMessage($token));
        }

        return $token;
    }

    /**
     * @param  array<string, mixed>  $token
     * @return array{id: string|null, email: string|null}
     */
    public function userInfo(array $token): array
    {
        $client = $this->client();
        $client->setAccessToken($token);

        $userInfo = (new Oauth2($client))->userinfo->get();

        return [
            'id' => $userInfo->getId(),
            'email' => $userInfo->getEmail(),
        ];
    }

    private function client(?string $state = null): Client
    {
        $client = new Client;
        $client->setClientId((string) config('google.client_id'));
        $client->setClientSecret((string) config('google.client_secret'));
        $client->setRedirectUri((string) config('google.redirect_uri'));
        $client->setScopes(config('google.drive_scopes', []));
        $client->setAccessType('offline');
        $client->setPrompt('consent');
        $client->setIncludeGrantedScopes(true);

        if ($state !== null) {
            $client->setState($state);
        }

        return $client;
    }

    /**
     * @param  array<string, mixed>  $token
     */
    private function safeErrorMessage(array $token): string
    {
        return (string) (Arr::get($token, 'error_description')
            ?: Arr::get($token, 'error')
            ?: 'Google Drive authorization failed.');
    }
}
