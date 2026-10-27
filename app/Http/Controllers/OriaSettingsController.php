<?php

namespace App\Http\Controllers;

use App\Models\Organization;
use App\Services\Oria\OriaPlus;
use App\Services\Oria\OriaRejectedException;
use App\Services\Oria\OriaUnavailableException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** Configuración → Oria: conectar el negocio con su cuenta de Oria, ver el plan y, con Plus, "Pagar servicios". */
class OriaSettingsController extends Controller
{
    private const UNAVAILABLE = 'Oria no está disponible en este momento. Inténtalo de nuevo en unos minutos.';

    public function __construct(private readonly OriaPlus $oria) {}

    public function index(Request $request): View
    {
        $organization = $this->organizationFor($request);
        $connection = $this->oria->connectionFor($organization->id);

        return view('settings.oria', [
            'organization' => $organization,
            'connection' => $connection ? $this->oria->refreshed($connection) : null,
            'configured' => $this->oria->isConfigured(),
        ]);
    }

    public function connect(Request $request): RedirectResponse
    {
        $organization = $this->organizationFor($request);
        if ($this->oria->connectionFor($organization->id) !== null) {
            return redirect()->route('settings.oria.index');
        }

        try {
            return redirect()->away($this->oria->startLink($organization, $request->session()));
        } catch (OriaUnavailableException|OriaRejectedException) {
            return redirect()->route('settings.oria.index')->with('error', self::UNAVAILABLE);
        }
    }

    /** Oria devuelve a la persona aquí, con el código para cambiar por el vínculo, o con error=denied. */
    public function callback(Request $request): RedirectResponse
    {
        $organization = $this->organizationFor($request);
        if ($request->query('error') === 'denied') {
            $request->session()->forget('oria.link_state');

            return redirect()->route('settings.oria.index')->with('status', 'No se conectó con Oria.');
        }

        $validated = $request->validate([
            'code' => ['required', 'string', 'max:100'],
            'state' => ['required', 'string', 'max:200'],
        ]);

        try {
            $connection = $this->oria->completeLink($organization, $request->user(), $validated['code'], $validated['state'], $request->session());
        } catch (OriaRejectedException $exception) {
            return redirect()->route('settings.oria.index')->with('error', $exception->getMessage());
        } catch (OriaUnavailableException) {
            return redirect()->route('settings.oria.index')->with('error', self::UNAVAILABLE);
        }

        return redirect()->route('settings.oria.index')
            ->with('status', "Listo: el negocio quedó conectado con la cuenta {$connection->account_email} de Oria.");
    }

    public function disconnect(Request $request): RedirectResponse
    {
        $connection = $this->oria->connectionFor($this->organizationFor($request)->id);
        if ($connection === null) {
            return redirect()->route('settings.oria.index');
        }

        try {
            $this->oria->disconnect($connection);
        } catch (OriaUnavailableException|OriaRejectedException) {
            return redirect()->route('settings.oria.index')->with('error', self::UNAVAILABLE);
        }

        return redirect()->route('settings.oria.index')->with('status', 'El negocio ya no está conectado con Oria.');
    }

    /** Abre Oria con la sesión iniciada, en "Pagar servicios". Solo con plan Plus. */
    public function payServices(Request $request): RedirectResponse
    {
        $connection = $this->oria->connectionFor($this->organizationFor($request)->id);
        $connection = $connection ? $this->oria->refreshed($connection) : null;
        if (! $connection?->isPlus()) {
            return redirect()->route('settings.oria.index')->with('error', 'Pagar servicios es parte del plan Plus de Oria.');
        }

        try {
            return redirect()->away($this->oria->signInUrl($connection));
        } catch (OriaRejectedException $exception) {
            return redirect()->route('settings.oria.index')->with('error', $exception->getMessage());
        } catch (OriaUnavailableException) {
            return redirect()->route('settings.oria.index')->with('error', self::UNAVAILABLE);
        }
    }

    private function organizationFor(Request $request): Organization
    {
        $user = $request->user();
        abort_unless($user !== null && $user->organization_id !== null, 403);

        return Organization::query()->findOrFail($user->organization_id);
    }
}
