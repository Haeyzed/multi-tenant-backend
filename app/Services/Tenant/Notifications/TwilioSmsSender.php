<?php

declare(strict_types=1);

namespace App\Services\Tenant\Notifications;

use App\Contracts\Tenant\SmsSender;
use RuntimeException;
use Twilio\Exceptions\TwilioException;
use Twilio\Rest\Client;

final class TwilioSmsSender implements SmsSender
{
    public function __construct(private ?Client $client = null) {}

    /**
     * Send an SMS message via Twilio.
     *
     * @throws RuntimeException if the from number is not configured
     * @throws TwilioException
     */
    public function send(string $to, string $message): void
    {
        $from = config('services.sms.from') ?: config('services.twilio.from');

        if (! is_string($from) || $from === '') {
            throw new RuntimeException('Twilio SMS from number is not configured.');
        }

        $this->client()->messages->create($to, [
            'from' => $from,
            'body' => $message,
        ]);
    }

    /**
     * Resolve the Twilio REST client, creating one from configured credentials if needed.
     *
     * @throws RuntimeException if the Twilio credentials are not configured
     */
    private function client(): Client
    {
        if ($this->client instanceof Client) {
            return $this->client;
        }

        $sid = config('services.twilio.sid');
        $token = config('services.twilio.token');

        if (! is_string($sid) || $sid === '' || ! is_string($token) || $token === '') {
            throw new RuntimeException('Twilio credentials are not configured.');
        }

        return $this->client = new Client($sid, $token);
    }
}
