<?php

declare(strict_types=1);

namespace App\Services\Tenant\Notifications;

use App\Contracts\Tenant\PushSender;
use Kreait\Firebase\Contract\Messaging;
use Kreait\Firebase\Exception\FirebaseException;
use Kreait\Firebase\Exception\MessagingException;
use Kreait\Firebase\Factory;
use Kreait\Firebase\Messaging\CloudMessage;
use Kreait\Firebase\Messaging\Notification;
use RuntimeException;

final class FcmPushSender implements PushSender
{
    public function __construct(private ?Messaging $messaging = null) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function send(string $deviceToken, string $title, string $body, array $data = []): void
    {
        $message = CloudMessage::new()
            ->withToken($deviceToken)
            ->withNotification(Notification::create($title, $body));

        if ($data !== []) {
            /** @var array<string, string> $stringData */
            $stringData = array_map(static fn (mixed $value): string => (string) $value, $data);
            $message = $message->withData($stringData);
        }

        try {
            $this->messaging()->send($message);
        } catch (MessagingException|FirebaseException $e) {

        }
    }

    private function messaging(): Messaging
    {
        if ($this->messaging instanceof Messaging) {
            return $this->messaging;
        }

        $credentials = config('services.firebase.credentials');

        if (! is_string($credentials) || $credentials === '' || ! is_file($credentials)) {
            throw new RuntimeException('Firebase credentials file is not configured.');
        }

        return $this->messaging = (new Factory)
            ->withServiceAccount($credentials)
            ->createMessaging();
    }
}
