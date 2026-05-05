<?php

namespace App\Notifications;

use Illuminate\Support\Str;

class ContactMessageReceivedNotification extends PanelDatabaseNotification
{
    public function __construct(private readonly array $payload) {}

    protected function typeSlug(): string
    {
        return 'contact-message-received';
    }

    /**
     * @return array<string, mixed>
     */
    protected function data(): array
    {
        $preview = Str::limit((string) $this->payload['message'], 80);

        return [
            'title' => 'New contact message',
            'message' => sprintf('%s (%s): %s', $this->payload['name'], $this->payload['topic'], $preview),
            'action_url' => null,
            'type' => $this->typeSlug(),
            'severity' => 'info',
            'subject_id' => null,
            'subject_type' => 'contact-message',
            'sender_email' => $this->payload['email'],
            'sender_contact' => $this->payload['contact'] ?? null,
            'occurred_at' => now()->toIso8601String(),
        ];
    }
}
