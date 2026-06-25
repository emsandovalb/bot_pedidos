# Messaging Platform Foundation

Benditio now treats channels as `Messaging Providers` instead of hard-coding Telegram or WhatsApp behavior into the domain.

## Why providers exist

Providers give every channel the same contract:

- verify a webhook
- receive an inbound webhook
- send an outbound message
- mark a message as read
- report health
- expose the provider name

This keeps channel-specific rules at the edges while preserving a shared application flow.

## Current scope

This sprint adds the abstraction layer only.

- Telegram behavior is not changed.
- WhatsApp Cloud is not integrated yet.
- Existing order, parser, analytics, closure, and setup workflows remain untouched.

## How WhatsApp will plug in

When WhatsApp Cloud API is ready, the `WhatsAppCloudProvider` will implement the same contract as Telegram.

That means WhatsApp can plug into the same manager, DTOs, and webhook flow without changing the downstream order pipeline.

## How Telegram plugs in

`TelegramProvider` is the first adapter. It exists so Telegram can conform to the provider contract without forcing a rewrite of the current Telegram workflow.

The existing Telegram intake code stays where it is until the platform migration is intentionally done.

## How future channels will plug in

Future channels like Instagram, Messenger, and Webchat will add their own provider implementations and register through the same manager.

No downstream business logic should care which channel delivered the message.

## Flow

Webhook

↓

Provider

↓

IncomingMessageDTO

↓

MessagingIngestionService

↓

Deduplication

↓

IncomingMessage

↓

OrderIngestionService

↓

Parser

↓

Orders

## Extensibility rule

New channels should only implement the provider contract and translate external payloads into the shared DTOs. The application layer below the provider should stay channel-agnostic.
