<?php

declare(strict_types=1);

namespace App\Services\Tenant;

use App\Enums\Tenant\ChannelAdapterKey;
use App\Models\Tenant\Channel;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Throwable;

/**
 * Builds marketplace OAuth authorize URLs and exchanges authorization codes for tokens.
 */
final class ChannelOAuthService
{
    public function redirectUrl(Channel $channel, string $callbackUrl): string
    {
        $adapter = $channel->adapter ?? ChannelAdapterKey::None;

        return match ($adapter) {
            ChannelAdapterKey::Amazon => $this->amazonAuthorizeUrl($channel, $callbackUrl),
            ChannelAdapterKey::Ebay => $this->ebayAuthorizeUrl($channel, $callbackUrl),
            default => throw ValidationException::withMessages([
                'adapter' => ['OAuth is only supported for amazon and ebay adapters.'],
            ]),
        };
    }

    public function handleCallback(string $adapter, string $code, string $state): Channel
    {
        $channelId = (int) Str::before($state, ':');
        $channel = Channel::query()->findOrFail($channelId);
        $expected = $adapter.'-'.$channel->id;

        if (! hash_equals($expected, Str::after($state, ':'))) {
            throw ValidationException::withMessages([
                'state' => ['Invalid OAuth state.'],
            ]);
        }

        $enum = ChannelAdapterKey::tryFrom($adapter);

        return match ($enum) {
            ChannelAdapterKey::Amazon => $this->exchangeAmazon($channel, $code),
            ChannelAdapterKey::Ebay => $this->exchangeEbay($channel, $code),
            default => throw ValidationException::withMessages([
                'adapter' => ['Unsupported OAuth adapter.'],
            ]),
        };
    }

    /**
     * @param  array<string, mixed>  $config
     * @return array<string, mixed>
     */
    public function encryptSensitiveConfig(array $config): array
    {
        if (isset($config['client_secret']) && is_string($config['client_secret']) && $config['client_secret'] !== '') {
            if (! Str::startsWith($config['client_secret'], 'eyJpdiI')) {
                $config['client_secret'] = Crypt::encryptString($config['client_secret']);
            }
        }

        return $config;
    }

    private function amazonAuthorizeUrl(Channel $channel, string $callbackUrl): string
    {
        $applicationId = config('services.amazon.application_id') ?: $this->clientId($channel);

        if (! is_string($applicationId) || $applicationId === '') {
            throw new RuntimeException('Amazon application id is not configured.');
        }

        return (string) config('services.amazon.authorize_url').'?'.http_build_query([
            'application_id' => $applicationId,
            'state' => $channel->id.':amazon-'.$channel->id,
            'redirect_uri' => $callbackUrl,
            'version' => 'beta',
        ]);
    }

    private function ebayAuthorizeUrl(Channel $channel, string $callbackUrl): string
    {
        $clientId = $this->clientId($channel);
        $ruName = config('services.ebay.ru_name');

        if ($clientId === null || ! is_string($ruName) || $ruName === '') {
            throw new RuntimeException('eBay OAuth client id / RuName is not configured.');
        }

        return (string) config('services.ebay.authorize_url').'?'.http_build_query([
            'client_id' => $clientId,
            'response_type' => 'code',
            'redirect_uri' => $ruName,
            'scope' => config('services.ebay.scopes'),
            'state' => $channel->id.':ebay-'.$channel->id,
        ]);
    }

    /**
     * @throws RequestException
     * @throws ConnectionException
     */
    private function exchangeAmazon(Channel $channel, string $code): Channel
    {
        $clientId = $this->clientId($channel);
        $clientSecret = $this->clientSecret($channel);

        if ($clientId === null || $clientSecret === null) {
            throw new RuntimeException('Amazon channel credentials are not configured.');
        }

        $json = Http::asForm()
            ->acceptJson()
            ->post((string) config('services.amazon.token_url'), [
                'grant_type' => 'authorization_code',
                'code' => $code,
                'client_id' => $clientId,
                'client_secret' => $clientSecret,
            ])
            ->throw()
            ->json();

        return $this->storeTokens($channel, is_array($json) ? $json : []);
    }

    /**
     * @throws RequestException
     * @throws ConnectionException
     */
    private function exchangeEbay(Channel $channel, string $code): Channel
    {
        $clientId = $this->clientId($channel);
        $clientSecret = $this->clientSecret($channel);
        $ruName = config('services.ebay.ru_name');

        if ($clientId === null || $clientSecret === null || ! is_string($ruName) || $ruName === '') {
            throw new RuntimeException('eBay channel credentials are not configured.');
        }

        $json = Http::withBasicAuth($clientId, $clientSecret)
            ->asForm()
            ->acceptJson()
            ->post((string) config('services.ebay.token_url'), [
                'grant_type' => 'authorization_code',
                'code' => $code,
                'redirect_uri' => $ruName,
            ])
            ->throw()
            ->json();

        return $this->storeTokens($channel, is_array($json) ? $json : []);
    }

    /**
     * @param  array<string, mixed>  $tokenResponse
     */
    private function storeTokens(Channel $channel, array $tokenResponse): Channel
    {
        $config = is_array($channel->config) ? $channel->config : [];
        $config['access_token'] = $tokenResponse['access_token'] ?? null;

        if (isset($tokenResponse['refresh_token']) && is_string($tokenResponse['refresh_token'])) {
            $config['refresh_token'] = $tokenResponse['refresh_token'];
        }

        $expiresIn = isset($tokenResponse['expires_in']) ? (int) $tokenResponse['expires_in'] : 3600;
        $config['access_token_expires_at'] = now()->addSeconds(max(60, $expiresIn - 60))->toIso8601String();

        $channel->forceFill(['config' => $config])->save();

        return $channel->refresh();
    }

    private function clientId(Channel $channel): ?string
    {
        $value = is_array($channel->config) ? ($channel->config['client_id'] ?? null) : null;

        return is_string($value) && $value !== '' ? $value : null;
    }

    private function clientSecret(Channel $channel): ?string
    {
        $value = is_array($channel->config) ? ($channel->config['client_secret'] ?? null) : null;

        if (! is_string($value) || $value === '') {
            return null;
        }

        try {
            return Crypt::decryptString($value);
        } catch (Throwable) {
            return $value;
        }
    }
}
