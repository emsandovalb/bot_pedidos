<?php

namespace App\Services\Messaging\DTO;

readonly class ProviderCapabilities
{
    public function __construct(
        public string $provider,
        public bool $receive_messages = false,
        public bool $send_messages = false,
        public bool $images = false,
        public bool $files = false,
        public bool $audio = false,
        public bool $video = false,
        public bool $templates = false,
        public bool $catalog = false,
        public bool $reactions = false,
        public bool $buttons = false,
        public bool $location = false,
        public bool $contacts = false,
        public bool $send_images = false,
        public bool $send_documents = false,
        public bool $send_audio = false,
        public bool $send_video = false,
        public bool $interactive_buttons = false,
        public bool $typing_indicator = false,
        public bool $delivery_receipts = false,
        public bool $read_receipts = false,
        public bool $voice_notes = false,
        public bool $reaction_support = false,
    ) {
    }

    /**
     * @return array<string, bool|string>
     */
    public function toArray(): array
    {
        return [
            'provider' => $this->provider,
            'receive_messages' => $this->receive_messages,
            'send_messages' => $this->send_messages,
            'images' => $this->images,
            'files' => $this->files,
            'audio' => $this->audio,
            'video' => $this->video,
            'templates' => $this->templates,
            'catalog' => $this->catalog,
            'reactions' => $this->reactions,
            'buttons' => $this->buttons,
            'location' => $this->location,
            'contacts' => $this->contacts,
            'send_images' => $this->send_images || $this->images,
            'send_documents' => $this->send_documents || $this->files,
            'send_audio' => $this->send_audio || $this->audio,
            'send_video' => $this->send_video || $this->video,
            'interactive_buttons' => $this->interactive_buttons || $this->buttons,
            'typing_indicator' => $this->typing_indicator,
            'delivery_receipts' => $this->delivery_receipts,
            'read_receipts' => $this->read_receipts,
            'voice_notes' => $this->voice_notes,
            'reaction_support' => $this->reaction_support || $this->reactions,
        ];
    }
}
