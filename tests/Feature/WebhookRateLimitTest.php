<?php

namespace Tests\Feature;

use Tests\TestCase;

class WebhookRateLimitTest extends TestCase
{
    public function test_public_webhooks_are_rate_limited_per_ip(): void
    {
        config(['messaging.webhook_rate_limit_per_minute' => 2]);

        $this->assertNotSame(429, $this->get('/webhooks/whatsapp')->getStatusCode());
        $this->assertNotSame(429, $this->get('/webhooks/whatsapp')->getStatusCode());

        $this->get('/webhooks/whatsapp')->assertTooManyRequests();
    }
}
