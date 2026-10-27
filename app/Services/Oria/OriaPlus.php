<?php

namespace App\Services\Oria;

use App\Models\Organization;
use App\Models\OriaConnection;
use App\Models\User;
use Illuminate\Contracts\Session\Session;
use Illuminate\Support\Str;

/**
 * La conexión de una organización con Oria y su plan Plus ("Pagar servicios"):
 *
 * 1. startLink pide el vínculo a Oria y devuelve la página de Oria donde la persona lo aprueba.
 * 2. Oria la devuelve a settings.oria.callback con un código; completeLink revisa el state y lo cambia por el vínculo.
 * 3. refreshed le vuelve a preguntar el plan a Oria cada tanto; si Oria no responde, sigue el último conocido.
 * 4. signInUrl pide a Oria una dirección de un solo uso que abre Oria con la sesión iniciada (solo con Plus).
 */
class OriaPlus
{
    private const STATE_KEY = 'oria.link_state';

    public function __construct(private readonly OriaClient $client) {}

    public function isConfigured(): bool
    {
        return $this->client->isConfigured();
    }

    public function connectionFor(?int $organizationId): ?OriaConnection
    {
        return $organizationId === null ? null : OriaConnection::query()->where('organization_id', $organizationId)->first();
    }

    /** El plan al día: si se revisó hace poco, el guardado; si Oria ya no tiene el vínculo, la conexión se borra. */
    public function refreshed(OriaConnection $connection): ?OriaConnection
    {
        $ttl = (int) config('oria.plan_ttl_minutes', 10);
        if ($connection->plan_checked_at !== null && $connection->plan_checked_at->gt(now()->subMinutes($ttl))) {
            return $connection;
        }

        try {
            $link = $this->client->link($connection->oria_link_id);
        } catch (OriaUnavailableException) {
            // Se sigue con el último plan y no se vuelve a intentar hasta el próximo plazo, para que el menú no espere
            // a Oria en cada página mientras está caída.
            $connection->forceFill(['plan_checked_at' => now()])->save();

            return $connection;
        } catch (OriaRejectedException $exception) {
            if ($exception->status !== 404) {
                return $connection;
            }
            $link = ['active' => false];
        }

        if (! ($link['active'] ?? false)) {
            $connection->delete();

            return null;
        }

        $connection->fill($this->linkAttributes($link))->save();

        return $connection;
    }

    /** La página de Oria donde la persona aprueba conectar la organización. */
    public function startLink(Organization $organization, Session $session): string
    {
        $state = Str::random(40);
        $session->put(self::STATE_KEY, $state);

        return $this->client->requestLink(
            (string) $organization->id,
            $organization->name,
            route('settings.oria.callback'),
            $state,
        )['approveUrl'];
    }

    public function completeLink(Organization $organization, User $user, string $code, string $state, Session $session): OriaConnection
    {
        $expected = $session->pull(self::STATE_KEY);
        if (! is_string($expected) || ! hash_equals($expected, $state)) {
            throw new OriaRejectedException(422, 'La respuesta de Oria no corresponde a esta sesión. Vuelve a intentarlo.');
        }

        $link = $this->client->exchange($code);
        if ((string) ($link['externalId'] ?? '') !== (string) $organization->id) {
            throw new OriaRejectedException(422, 'Oria devolvió el vínculo de otro negocio.');
        }

        return OriaConnection::query()->updateOrCreate(
            ['organization_id' => $organization->id],
            [...$this->linkAttributes($link), 'oria_link_id' => (int) $link['id'], 'connected_by' => $user->id],
        );
    }

    /** Si Oria dice que no (por ejemplo, el negocio ya no es Plus), el plan se vuelve a revisar en la próxima consulta. */
    public function signInUrl(OriaConnection $connection): string
    {
        try {
            return $this->client->signInUrl($connection->oria_link_id);
        } catch (OriaRejectedException $exception) {
            $connection->forceFill(['plan_checked_at' => null])->save();

            throw $exception;
        }
    }

    public function disconnect(OriaConnection $connection): void
    {
        try {
            $this->client->unlink($connection->oria_link_id);
        } catch (OriaRejectedException $exception) {
            // Oria ya no lo tenía: igual se borra aquí.
            if ($exception->status !== 404) {
                throw $exception;
            }
        }

        $connection->delete();
    }

    /**
     * @param  array<string, mixed>  $link
     * @return array<string, mixed>
     */
    private function linkAttributes(array $link): array
    {
        return [
            'plan' => $link['plan'] === OriaConnection::PLAN_PLUS ? OriaConnection::PLAN_PLUS : OriaConnection::PLAN_BASIC,
            'account_name' => $link['account']['name'] ?? null,
            'account_email' => $link['account']['email'] ?? null,
            'plan_checked_at' => now(),
        ];
    }
}
