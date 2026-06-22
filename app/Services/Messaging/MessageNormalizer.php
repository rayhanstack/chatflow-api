<?php

namespace App\Services\Messaging;

/**
 * Converts raw platform payloads into a single unified format
 * so the rest of the app never needs to know which platform sent the message.
 *
 * Unified message shape:
 * [
 *   'platform'    => 'messenger' | 'whatsapp' | 'telegram',
 *   'platform_id' => string,   // sender's unique id on that platform
 *   'name'        => string,   // sender display name (if available)
 *   'text'        => string,   // message body
 *   'type'        => 'text' | 'image' | 'audio' | 'file',
 *   'media_url'   => string|null,
 *   'raw'         => array,    // original payload for debugging
 * ]
 */
class MessageNormalizer
{
    public function fromMessenger(array $payload): array
    {
        $entry    = $payload['entry'][0] ?? [];
        $event    = $entry['messaging'][0] ?? [];
        $sender   = $event['sender']['id'] ?? '';
        $message  = $event['message'] ?? [];

        $type     = isset($message['attachments']) ? $this->resolveAttachmentType($message['attachments'][0]) : 'text';
        $mediaUrl = $type !== 'text' ? ($message['attachments'][0]['payload']['url'] ?? null) : null;

        return [
            'platform'    => 'messenger',
            'platform_id' => $sender,
            'name'        => null, // fetched separately via Graph API
            'text'        => $message['text'] ?? '',
            'type'        => $type,
            'media_url'   => $mediaUrl,
            'raw'         => $payload,
        ];
    }

    public function fromWhatsApp(array $payload): array
    {
        $change  = $payload['entry'][0]['changes'][0]['value'] ?? [];
        $message = $change['messages'][0] ?? [];
        $contact = $change['contacts'][0] ?? [];

        $type     = $message['type'] ?? 'text';
        $text     = $message['text']['body'] ?? $message['caption'] ?? '';
        $mediaUrl = isset($message[$type]['id']) ? 'wa-media://' . $message[$type]['id'] : null;

        return [
            'platform'    => 'whatsapp',
            'platform_id' => $message['from'] ?? '',
            'name'        => $contact['profile']['name'] ?? null,
            'text'        => $text,
            'type'        => in_array($type, ['image', 'audio', 'document']) ? $type : 'text',
            'media_url'   => $mediaUrl,
            'raw'         => $payload,
        ];
    }

    public function fromTelegram(array $payload): array
    {
        $message = $payload['message'] ?? [];
        $from    = $message['from'] ?? [];

        $type     = isset($message['photo']) ? 'image'
                  : (isset($message['voice']) ? 'audio'
                  : (isset($message['document']) ? 'file' : 'text'));

        return [
            'platform'    => 'telegram',
            'platform_id' => (string) ($from['id'] ?? ''),
            'name'        => trim(($from['first_name'] ?? '') . ' ' . ($from['last_name'] ?? '')),
            'text'        => $message['text'] ?? $message['caption'] ?? '',
            'type'        => $type,
            'media_url'   => null,
            'raw'         => $payload,
        ];
    }

    private function resolveAttachmentType(array $attachment): string
    {
        return match ($attachment['type'] ?? '') {
            'image'  => 'image',
            'audio'  => 'audio',
            'file'   => 'file',
            default  => 'text',
        };
    }
}