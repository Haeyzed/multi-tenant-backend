<?php

declare(strict_types=1);

namespace App\Services\Tenant\ChannelAdapters;

use App\Models\Tenant\Channel;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * Shared OAuth token + credential helpers for marketplace HTTP adapters.
 */
abstract class AbstractMarketplaceHttpAdapter
{
    /**
     * @return array<string, mixed>
     */
    protected function credentials(Channel $channel): array
    {
        return is_array($channel->config) ? $channel->config : [];
    }

    protected function clientId(Channel $channel): ?string
    {
        $value = $this->credentials($channel)['client_id'] ?? null;

        return is_string($value) && $value !== '' ? $value : null;
    }

    protected function clientSecret(Channel $channel): ?string
    {
        $value = $this->credentials($channel)['client_secret'] ?? null;

        if (! is_string($value) || $value === '') {
            return null;
        }

        try {
            return Crypt::decryptString($value);
        } catch (\Throwable) {
            return $value;
        }
    }

    protected function accessToken(Channel $channel): ?string
    {
        $creds = $this->credentials($channel);
        $token = $creds['access_token'] ?? null;

        if (! is_string($token) || $token === '') {
            return null;
        }

        $expiresAt = $creds['access_token_expires_at'] ?? null;

        if (is_string($expiresAt) && $expiresAt !== '' && now()->gte($expiresAt)) {
            return null;
        }

        return $token;
    }

    /**
     * @param  array<string, mixed>  $tokenResponse
     */
    protected function persistTokens(Channel $channel, array $tokenResponse): void
    {
        $config = $this->credentials($channel);
        $config['access_token'] = $tokenResponse['access_token'] ?? $config['access_token'] ?? null;

        if (isset($tokenResponse['refresh_token']) && is_string($tokenResponse['refresh_token'])) {
            $config['refresh_token'] = $tokenResponse['refresh_token'];
        }

        $expiresIn = isset($tokenResponse['expires_in']) ? (int) $tokenResponse['expires_in'] : 3600;
        $config['access_token_expires_at'] = now()->addSeconds(max(60, $expiresIn - 60))->toIso8601String();

        $channel->forceFill(['config' => $config])->save();
    }

    protected function requireAccessToken(Channel $channel, string $tokenUrl, bool $basicAuth = false): string
    {
        $token = $this->accessToken($channel);

        if (is_string($token) && $token !== '') {
            return $token;
        }

        $refresh = $this->credentials($channel)['refresh_token'] ?? null;
        $clientId = $this->clientId($channel);
        $clientSecret = $this->clientSecret($channel);

        if (! is_string($refresh) || $refresh === '' || $clientId === null || $clientSecret === null) {
            throw new RuntimeException("Marketplace channel [{$channel->id}] is missing OAuth credentials.");
        }

        $request = Http::asForm()->acceptJson();

        if ($basicAuth) {
            $request = $request->withBasicAuth($clientId, $clientSecret);
            $response = $request->post($tokenUrl, [
                'grant_type' => 'refresh_token',
                'refresh_token' => $refresh,
            ]);
        } else {
            $response = $request->post($tokenUrl, [
                'grant_type' => 'refresh_token',
                'refresh_token' => $refresh,
                'client_id' => $clientId,
                'client_secret' => $clientSecret,
            ]);
        }

        $json = $response->throw()->json();

        if (! is_array($json) || ! isset($json['access_token'])) {
            throw new RuntimeException("Marketplace channel [{$channel->id}] token refresh failed.");
        }

        $this->persistTokens($channel, $json);

        return (string) $json['access_token'];
    }

    protected function logStub(string $event, Channel $channel, array $context = []): void
    {
        Log::info($event, array_merge([
            'adapter' => $this->key(),
            'channel_id' => $channel->id,
        ], $context));
    }

    abstract public function key(): string;
}
