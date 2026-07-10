<?php

namespace App\Services\Developer;

use Illuminate\Support\Str;

class RandomMessageFactory
{
    /**
     * @return array<int, array{key:string,label:string,description:string,theme:string,products:array<int, string>,delivery_bias:array<int, string>,payment_bias:array<int, string>,date_bias:array<int, string>,urgency_bias:array<int, string>,size_bias:array<int, string>}>
     */
    public function productFamilies(): array
    {
        return [
            [
                'key' => 'hardware',
                'label' => 'Hardware',
                'description' => 'Building materials and shop stock.',
                'theme' => 'hardware',
                'products' => ['bloques', 'cemento', 'pvc', 'steel', 'tornillos', 'brochas', 'mangueras', 'laminas'],
                'delivery_bias' => ['delivery', 'delivery', 'pickup'],
                'payment_bias' => ['sinpe', 'cash', 'sinpe'],
                'date_bias' => ['today', 'tomorrow', 'none', 'none'],
                'urgency_bias' => ['urgent', 'normal', 'normal'],
                'size_bias' => ['medium', 'large', 'small'],
            ],
            [
                'key' => 'construction',
                'label' => 'Construction',
                'description' => 'Heavy orders with bulk quantities.',
                'theme' => 'construction',
                'products' => ['bloques', 'cemento', 'steel', 'pvc', 'arena', 'varilla', 'tubo galvanizado'],
                'delivery_bias' => ['delivery', 'delivery', 'delivery', 'pickup'],
                'payment_bias' => ['sinpe', 'cash', 'cash'],
                'date_bias' => ['today', 'tomorrow', 'tomorrow', 'none'],
                'urgency_bias' => ['urgent', 'high', 'normal'],
                'size_bias' => ['large', 'large', 'medium'],
            ],
            [
                'key' => 'market',
                'label' => 'Market',
                'description' => 'Fresh produce and small baskets.',
                'theme' => 'market',
                'products' => ['tomates', 'lechugas', 'bananos', 'cebollas', 'pepinos', 'huevos', 'aguacates', 'cilantro'],
                'delivery_bias' => ['pickup', 'pickup', 'delivery'],
                'payment_bias' => ['cash', 'sinpe', 'cash'],
                'date_bias' => ['today', 'today', 'none', 'tomorrow'],
                'urgency_bias' => ['normal', 'normal', 'urgent'],
                'size_bias' => ['small', 'small', 'medium'],
            ],
            [
                'key' => 'restaurant',
                'label' => 'Restaurant',
                'description' => 'Daily restaurant supply and replenishment.',
                'theme' => 'restaurant',
                'products' => ['arroz', 'pollo', 'tortillas', 'refrescos', 'papas', 'frijoles', 'queso', 'carne'],
                'delivery_bias' => ['delivery', 'pickup', 'delivery'],
                'payment_bias' => ['sinpe', 'cash', 'sinpe'],
                'date_bias' => ['today', 'today', 'tomorrow', 'none'],
                'urgency_bias' => ['urgent', 'normal', 'normal'],
                'size_bias' => ['medium', 'small', 'large'],
            ],
        ];
    }

    public function customerName(int $index): string
    {
        $firstNames = ['Maria', 'Ana', 'Carla', 'Diana', 'Jose', 'Luis', 'Karla', 'Pablo', 'Sofia', 'Sergio', 'Andrea', 'Javier'];
        $lastNames = ['Lopez', 'Perez', 'Vargas', 'Rojas', 'Gonzalez', 'Castro', 'Morales', 'Quesada', 'Soto', 'Mora', 'Ramirez', 'Chaves'];

        $first = $firstNames[$index % count($firstNames)];
        $last = $lastNames[(int) floor($index / count($firstNames)) % count($lastNames)];

        return $first . ' ' . $last;
    }

    public function customerPhone(string $provider, int $index): string
    {
        $provider = strtolower(trim($provider));

        if ($provider === 'telegram') {
            return '9' . str_pad((string) (1000 + $index), 8, '0', STR_PAD_LEFT);
        }

        return '502555' . str_pad((string) (10000 + $index), 5, '0', STR_PAD_LEFT);
    }

    public function providerUsername(string $name): ?string
    {
        $username = strtolower(preg_replace('/[^a-z0-9]+/i', '', Str::ascii($name)) ?? '');

        return $username !== '' ? $username . '_cr' : null;
    }

    /**
     * @param  array<string, mixed>  $context
     */
    public function buildMessage(array $context = []): string
    {
        $family = (string) ($context['family'] ?? 'hardware');
        $products = (array) ($context['products'] ?? $this->productsForFamily($family));
        $count = max(1, (int) ($context['item_count'] ?? 1));
        $large = (bool) ($context['large'] ?? false);
        $beforeNoon = (bool) ($context['before_noon'] ?? false);
        $deliveryMethod = (string) ($context['delivery_method'] ?? $this->pick(['delivery', 'pickup']));
        $paymentMethod = (string) ($context['payment_method'] ?? $this->pick(['sinpe', 'cash']));
        $datePhrase = (string) ($context['date_phrase'] ?? $this->pick(['today', 'tomorrow', 'none']));
        $urgency = (string) ($context['urgency'] ?? $this->pick(['normal', 'urgent']));

        $items = [];
        for ($i = 0; $i < $count; $i++) {
            $items[] = $this->itemPhrase($products[$i % count($products)], $large, $family);
        }

        $parts = [];
        $parts[] = $this->leadIn($deliveryMethod, $urgency);
        $parts[] = implode($count > 1 ? ', ' : ' ', $items);
        $parts[] = $this->datePhrase($datePhrase, $beforeNoon);
        $parts[] = $this->deliveryPhrase($deliveryMethod);
        $parts[] = $this->paymentPhrase($paymentMethod);

        $parts = array_values(array_filter(array_map('trim', $parts)));

        return implode('. ', $parts) . '.';
    }

    /**
     * @return array<int, string>
     */
    public function randomMessages(int $count): array
    {
        $count = max(1, $count);
        $messages = [];
        $families = array_values(array_column($this->productFamilies(), 'key'));

        for ($i = 0; $i < $count; $i++) {
            $family = $families[$i % count($families)];
            $messages[] = $this->buildMessage([
                'family' => $family,
                'item_count' => random_int(1, 3),
                'large' => random_int(1, 4) === 1,
                'delivery_method' => $this->pick(['delivery', 'pickup']),
                'payment_method' => $this->pick(['sinpe', 'cash']),
                'date_phrase' => $this->pick(['today', 'tomorrow', 'none']),
                'urgency' => $this->pick(['normal', 'urgent']),
                'before_noon' => random_int(1, 3) === 1,
            ]);
        }

        return $messages;
    }

    /**
     * @param  array<int, string>  $values
     */
    public function pick(array $values): string
    {
        $values = array_values(array_filter($values, static fn ($value): bool => is_string($value) && $value !== ''));

        if ($values === []) {
            return '';
        }

        return $values[array_rand($values)];
    }

    /**
     * @return array<int, string>
     */
    public function productsForFamily(string $family): array
    {
        return match ($family) {
            'construction' => ['bloques', 'cemento', 'steel', 'pvc', 'arena', 'varilla'],
            'market' => ['tomates', 'lechugas', 'bananos', 'cebollas', 'pepinos', 'huevos'],
            'restaurant' => ['arroz', 'pollo', 'tortillas', 'refrescos', 'papas', 'frijoles'],
            default => ['bloques', 'cemento', 'pvc', 'tornillos', 'brochas', 'mangueras'],
        };
    }

    private function itemPhrase(string $product, bool $large, string $family): string
    {
        $quantity = $large
            ? random_int(10, 40)
            : match ($family) {
                'market' => random_int(1, 8),
                'restaurant' => random_int(1, 6),
                default => random_int(1, 20),
            };

        $units = match ($family) {
            'market' => $this->pick(['kilos', 'libra', 'cajas', 'paquetes', 'unidades']),
            'construction' => $this->pick(['bloques', 'sacos', 'varillas', 'tubos', 'laminas', 'unidades']),
            'restaurant' => $this->pick(['unidades', 'paquetes', 'cajas', 'porciones']),
            default => $this->pick(['bolsas', 'cajas', 'paquetes', 'unidades', 'sacos']),
        };

        return sprintf('%d %s de %s', $quantity, $units, $product);
    }

    private function leadIn(string $deliveryMethod, string $urgency): string
    {
        if ($deliveryMethod === 'pickup') {
            return $this->pick(['Paso por ellos', 'Yo paso por ellos', 'Los retiro en tienda']);
        }

        if ($urgency === 'urgent') {
            return $this->pick(['Ocupo', 'Necesito', 'Mandeme']);
        }

        return $this->pick(['Ocupo', 'Necesito', 'Favor de mandarme']);
    }

    private function datePhrase(string $datePhrase, bool $beforeNoon): ?string
    {
        return match ($datePhrase) {
            'today' => $beforeNoon ? 'para hoy antes del medio dia' : 'para hoy',
            'tomorrow' => $beforeNoon ? 'para manana temprano' : 'para manana',
            default => null,
        };
    }

    private function deliveryPhrase(string $deliveryMethod): ?string
    {
        return match ($deliveryMethod) {
            'pickup' => $this->pick(['yo paso', 'paso por ellos', 'lo recojo', 'retiro en tienda', 'recojo en sucursal']),
            'delivery' => $this->pick(['mandemelo', 'enviemelo', 'me lo envia', 'llevemelo', 'me lo llevan']),
            default => null,
        };
    }

    private function paymentPhrase(string $paymentMethod): ?string
    {
        return match ($paymentMethod) {
            'sinpe' => $this->pick(['Pago por SINPE', 'Pago por SINPE manana', 'Pago por SINPE transferido']),
            'cash' => $this->pick(['Pago en efectivo', 'Pago en cash', 'Pago al recibir']),
            default => null,
        };
    }
}
