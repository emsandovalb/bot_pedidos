<?php

namespace App\Http\Controllers;

use App\Models\ChannelConnection;
use App\Models\SetupRequest;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;

class SetupRequestController extends Controller
{
    public function index(): View
    {
        $organizationId = auth()->user()?->organization_id;

        $query = SetupRequest::query()
            ->with(['organization', 'channelConnection', 'assignedTo'])
            ->when($organizationId, fn (Builder $query) => $query->where('organization_id', $organizationId), fn (Builder $query) => $query->whereRaw('1 = 0'))
            ->orderByDesc('requested_at')
            ->orderByDesc('id');

        $requests = $query->paginate(12)->withQueryString();

        $scopedRequests = SetupRequest::query()
            ->when($organizationId, fn (Builder $query) => $query->where('organization_id', $organizationId), fn (Builder $query) => $query->whereRaw('1 = 0'));

        $averageMinutes = $this->averageCompletionMinutes(
            (clone $scopedRequests)
                ->where('status', SetupRequest::STATUS_COMPLETED)
                ->whereNotNull('requested_at')
                ->whereNotNull('completed_at')
                ->get(['requested_at', 'completed_at'])
        );

        return view('setup-requests.index', [
            'requests' => $requests,
            'metrics' => [
                [
                    'label' => 'Solicitudes abiertas',
                    'value' => (clone $scopedRequests)->where('status', SetupRequest::STATUS_OPEN)->count(),
                    'detail' => 'Casos pendientes de contacto.',
                    'tone' => 'amber',
                ],
                [
                    'label' => 'En progreso',
                    'value' => (clone $scopedRequests)->where('status', SetupRequest::STATUS_IN_PROGRESS)->count(),
                    'detail' => 'Asistencias activas hoy.',
                    'tone' => 'blue',
                ],
                [
                    'label' => 'Completadas',
                    'value' => (clone $scopedRequests)->where('status', SetupRequest::STATUS_COMPLETED)->count(),
                    'detail' => 'Onboardings cerrados correctamente.',
                    'tone' => 'emerald',
                ],
                [
                    'label' => 'Tiempo promedio',
                    'value' => $averageMinutes !== null ? $this->formatAverageDuration($averageMinutes) : 'Sin dato',
                    'detail' => 'Promedio de solicitud a cierre.',
                    'tone' => 'slate',
                ],
            ],
            'statusLabels' => $this->statusLabels(),
            'statusClasses' => $this->statusClasses(),
        ]);
    }

    public function show(SetupRequest $setupRequest): View
    {
        $this->authorizeOrganization($setupRequest);

        return view('setup-requests.show', $this->viewData($setupRequest->load(['organization', 'channelConnection', 'assignedTo'])));
    }

    public function store(Request $request): RedirectResponse
    {
        $organizationId = $request->user()?->organization_id;

        abort_unless($organizationId, 403);

        $validated = $request->validate([
            'channel_connection_id' => ['nullable', 'integer', Rule::exists('channel_connections', 'id')->where(fn ($query) => $query->where('organization_id', $organizationId))],
            'contact_name' => ['nullable', 'string', 'max:255'],
            'contact_phone' => ['nullable', 'string', 'max:255'],
            'contact_email' => ['nullable', 'email', 'max:255'],
            'preferred_contact_time' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:5000'],
        ]);

        $channelConnection = $this->resolveChannelConnection($organizationId, $validated['channel_connection_id'] ?? null);
        $existingRequest = SetupRequest::query()
            ->where('organization_id', $organizationId)
            ->where('type', SetupRequest::TYPE_WHATSAPP_ASSISTED_SETUP)
            ->whereIn('status', [SetupRequest::STATUS_OPEN, SetupRequest::STATUS_SCHEDULED, SetupRequest::STATUS_IN_PROGRESS])
            ->where(function (Builder $query) use ($channelConnection): void {
                if ($channelConnection?->id) {
                    $query->where('channel_connection_id', $channelConnection->id);
                } else {
                    $query->whereNull('channel_connection_id');
                }
            })
            ->latest('requested_at')
            ->first();

        if ($existingRequest !== null) {
            return redirect()
                ->route('setup-requests.show', $existingRequest)
                ->with('status', 'Ya existe una solicitud abierta de configuracion asistida.');
        }

        $requestRecord = SetupRequest::query()->create([
            'organization_id' => $organizationId,
            'channel_connection_id' => $channelConnection?->id,
            'type' => SetupRequest::TYPE_WHATSAPP_ASSISTED_SETUP,
            'status' => SetupRequest::STATUS_OPEN,
            'contact_name' => $this->fallbackValue($validated['contact_name'] ?? null, $channelConnection?->display_name ?? $request->user()?->name ?? 'Contacto Benditio'),
            'contact_phone' => $this->fallbackValue($validated['contact_phone'] ?? null, $channelConnection?->phone_number ?? ''),
            'contact_email' => $this->fallbackValue($validated['contact_email'] ?? null, $request->user()?->email),
            'preferred_contact_time' => $this->fallbackValue($validated['preferred_contact_time'] ?? null, null),
            'notes' => $this->fallbackValue($validated['notes'] ?? null, data_get($channelConnection?->metadata_json, 'notes')),
            'requested_at' => now(),
        ]);

        return redirect()
            ->route('setup-requests.show', $requestRecord)
            ->with('status', 'Solicitud de configuracion asistida creada.');
    }

    public function update(Request $request, SetupRequest $setupRequest): RedirectResponse
    {
        $this->authorizeOrganization($setupRequest);

        $validated = $request->validate([
            'status' => ['nullable', Rule::in([
                SetupRequest::STATUS_OPEN,
                SetupRequest::STATUS_SCHEDULED,
                SetupRequest::STATUS_IN_PROGRESS,
                SetupRequest::STATUS_COMPLETED,
                SetupRequest::STATUS_CANCELLED,
            ])],
            'assigned_to' => [
                'nullable',
                'integer',
                Rule::exists('users', 'id')->where(fn ($query) => $query->where('organization_id', $setupRequest->organization_id)),
            ],
            'notes' => ['nullable', 'string', 'max:5000'],
            'started_at' => ['nullable', 'date'],
            'completed_at' => ['nullable', 'date'],
        ]);

        $payload = [
            'assigned_to' => $validated['assigned_to'] ?? null,
            'notes' => $validated['notes'] ?? null,
            'status' => $validated['status'] ?? $setupRequest->status,
        ];

        if (($request->input('transition_action') ?? '') === 'schedule') {
            $payload['status'] = SetupRequest::STATUS_SCHEDULED;
        }

        if (($request->input('transition_action') ?? '') === 'start') {
            $payload['status'] = SetupRequest::STATUS_IN_PROGRESS;
            $payload['started_at'] = now();
        } elseif (array_key_exists('started_at', $validated)) {
            $payload['started_at'] = $validated['started_at'] ? Carbon::parse($validated['started_at']) : null;
        }

        if (($request->input('transition_action') ?? '') === 'complete') {
            $payload['status'] = SetupRequest::STATUS_COMPLETED;
            $payload['completed_at'] = now();
            $payload['started_at'] = $setupRequest->started_at ?? now();
        } elseif (array_key_exists('completed_at', $validated)) {
            $payload['completed_at'] = $validated['completed_at'] ? Carbon::parse($validated['completed_at']) : null;
        }

        if (($request->input('transition_action') ?? '') === 'cancel') {
            $payload['status'] = SetupRequest::STATUS_CANCELLED;
        }

        if (($payload['status'] ?? $setupRequest->status) === SetupRequest::STATUS_IN_PROGRESS && ! array_key_exists('started_at', $payload)) {
            $payload['started_at'] = $setupRequest->started_at ?? now();
        }

        if (($payload['status'] ?? $setupRequest->status) === SetupRequest::STATUS_COMPLETED && ! array_key_exists('completed_at', $payload)) {
            $payload['completed_at'] = $setupRequest->completed_at ?? now();
        }

        $setupRequest->fill($payload);
        $setupRequest->save();

        return redirect()
            ->route('setup-requests.show', $setupRequest)
            ->with('status', 'Solicitud actualizada.');
    }

    private function viewData(SetupRequest $setupRequest): array
    {
        return [
            'setupRequest' => $setupRequest,
            'statusLabels' => $this->statusLabels(),
            'statusClasses' => $this->statusClasses(),
            'progress' => $this->progressState($setupRequest),
            'timeline' => $this->timeline($setupRequest),
            'checklist' => $this->checklist($setupRequest),
            'assignedUsers' => $this->assignedUsers($setupRequest),
        ];
    }

    private function authorizeOrganization(SetupRequest $setupRequest): void
    {
        abort_unless($setupRequest->organization_id === auth()->user()?->organization_id, 403);
    }

    private function resolveChannelConnection(int $organizationId, ?int $channelConnectionId): ?ChannelConnection
    {
        if ($channelConnectionId === null) {
            return ChannelConnection::query()
                ->where('organization_id', $organizationId)
                ->where('channel', ChannelConnection::CHANNEL_WHATSAPP)
                ->latest('id')
                ->first();
        }

        return ChannelConnection::query()
            ->where('organization_id', $organizationId)
            ->whereKey($channelConnectionId)
            ->first();
    }

    private function progressState(SetupRequest $setupRequest): array
    {
        $steps = collect([
            ['label' => 'Solicitada', 'done' => true],
            ['label' => 'Programada', 'done' => in_array($setupRequest->status, [SetupRequest::STATUS_SCHEDULED, SetupRequest::STATUS_IN_PROGRESS, SetupRequest::STATUS_COMPLETED], true)],
            ['label' => 'En progreso', 'done' => in_array($setupRequest->status, [SetupRequest::STATUS_IN_PROGRESS, SetupRequest::STATUS_COMPLETED], true)],
            ['label' => 'Completada', 'done' => $setupRequest->status === SetupRequest::STATUS_COMPLETED],
        ]);

        return [
            'percentage' => (int) round($steps->where('done', true)->count() * 100 / $steps->count()),
            'steps' => $steps,
        ];
    }

    private function checklist(SetupRequest $setupRequest): array
    {
        return [
            ['label' => 'Solicitud creada', 'done' => $setupRequest->requested_at !== null],
            ['label' => 'Asignacion definida', 'done' => $setupRequest->assigned_to !== null],
            ['label' => 'Inicio registrado', 'done' => $setupRequest->started_at !== null],
            ['label' => 'Cierre registrado', 'done' => $setupRequest->completed_at !== null || $setupRequest->status === SetupRequest::STATUS_COMPLETED],
        ];
    }

    private function timeline(SetupRequest $setupRequest): array
    {
        return [
            [
                'title' => 'Solicitud recibida',
                'time' => $setupRequest->requested_at?->format('d/m/Y H:i') ?? 'Sin fecha',
                'detail' => 'La solicitud se registro en el centro operativo de Benditio.',
            ],
            [
                'title' => 'Configuracion iniciada',
                'time' => $setupRequest->started_at?->format('d/m/Y H:i') ?? 'Pendiente',
                'detail' => $setupRequest->started_at ? 'El equipo ya comenzo la asistencia.' : 'Aun no se registra inicio.',
            ],
            [
                'title' => 'Configuracion finalizada',
                'time' => $setupRequest->completed_at?->format('d/m/Y H:i') ?? 'Pendiente',
                'detail' => $setupRequest->completed_at ? 'La solicitud quedo cerrada correctamente.' : 'Pendiente de cierre.',
            ],
        ];
    }

    private function assignedUsers(SetupRequest $setupRequest): Collection
    {
        return User::query()
            ->where('organization_id', $setupRequest->organization_id)
            ->orderBy('name')
            ->get();
    }

    private function statusLabels(): array
    {
        return [
            SetupRequest::STATUS_OPEN => 'Abierta',
            SetupRequest::STATUS_SCHEDULED => 'Programada',
            SetupRequest::STATUS_IN_PROGRESS => 'En progreso',
            SetupRequest::STATUS_COMPLETED => 'Completada',
            SetupRequest::STATUS_CANCELLED => 'Cancelada',
        ];
    }

    private function statusClasses(): array
    {
        return [
            SetupRequest::STATUS_OPEN => 'bg-amber-50 text-amber-800 ring-1 ring-amber-100',
            SetupRequest::STATUS_SCHEDULED => 'bg-blue-50 text-blue-800 ring-1 ring-blue-100',
            SetupRequest::STATUS_IN_PROGRESS => 'bg-sky-50 text-sky-800 ring-1 ring-sky-100',
            SetupRequest::STATUS_COMPLETED => 'bg-emerald-50 text-emerald-800 ring-1 ring-emerald-100',
            SetupRequest::STATUS_CANCELLED => 'bg-slate-100 text-slate-700 ring-1 ring-slate-200',
        ];
    }

    private function averageCompletionMinutes(Collection $requests): ?float
    {
        if ($requests->isEmpty()) {
            return null;
        }

        return $requests
            ->map(function (SetupRequest $request): float {
                return $request->requested_at && $request->completed_at
                    ? (float) $request->requested_at->diffInMinutes($request->completed_at)
                    : 0.0;
            })
            ->average();
    }

    private function formatAverageDuration(float $minutes): string
    {
        $rounded = (int) round($minutes);

        if ($rounded < 60) {
            return $rounded . ' min';
        }

        $hours = intdiv($rounded, 60);
        $remainingMinutes = $rounded % 60;

        return $remainingMinutes > 0
            ? $hours . ' h ' . str_pad((string) $remainingMinutes, 2, '0', STR_PAD_LEFT) . ' min'
            : $hours . ' h';
    }

    private function fallbackValue(mixed $value, mixed $fallback): mixed
    {
        return blank($value) ? $fallback : $value;
    }
}
