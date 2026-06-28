<?php

namespace App\Services;

class NotificationTemplateRenderer
{
    /**
     * @param  array{order_id?: string|int|null, customer_name?: string|null, status?: string|null, business_name?: string|null}  $context
     */
    public function render(string $template, array $context = []): string
    {
        return strtr($template, [
            '{order_id}' => (string) ($context['order_id'] ?? ''),
            '{customer_name}' => (string) ($context['customer_name'] ?? ''),
            '{status}' => (string) ($context['status'] ?? ''),
            '{business_name}' => (string) ($context['business_name'] ?? ''),
        ]);
    }
}
