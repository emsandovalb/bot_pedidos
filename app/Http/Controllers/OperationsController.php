<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Services\Operations\FocusModePresenter;
use App\Services\Operations\OperationsInbox;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OperationsController extends Controller
{
    public function __construct(
        private readonly OperationsInbox $operationsInbox,
        private readonly FocusModePresenter $focusModePresenter,
    ) {}

    private const STATUS_FILTERS = [
        'all',
        'nuevos',
        'en_revision',
        'preparando',
        'listos',
        'despachados',
    ];

    private const CHANNEL_FILTERS = [
        'whatsapp',
        'telegram',
    ];

    private const PRIORITY_FILTERS = [
        'urgent',
        'duplicate',
        'vip',
    ];

    public function index(Request $request): View
    {
        if (config('operations.focus_mode_default', false)) {
            return redirect()->route('operations.focus');
        }

        $payload = $this->operationsInbox->buildOperationsPayload($request);

        return view('operations.index', [
            'ordersData' => $payload['ordersData'],
            'feedData' => $payload['feedData'],
            'agendaData' => $payload['agendaData'],
            'queueData' => $payload['queueData'],
            'filters' => $payload['filters'],
            'selectedOrderId' => $payload['selectedOrderId'],
            'selectedOrder' => $payload['selectedOrder'],
            'statusFilters' => self::STATUS_FILTERS,
            'channelFilters' => self::CHANNEL_FILTERS,
            'priorityFilters' => self::PRIORITY_FILTERS,
        ]);
    }

    public function focus(Request $request): View
    {
        return view('operations.focus', [
            'focusData' => $this->focusModePresenter->build($request),
        ]);
    }

    public function feed(Request $request): JsonResponse
    {
        return response()->json($this->operationsInbox->buildFeedPayload($request));
    }

    public function snapshot(Order $order): JsonResponse
    {
        return response()->json($this->operationsInbox->snapshot($order));
    }
}
