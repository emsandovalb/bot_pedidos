<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use App\Models\IncomingMessage;
use App\Services\IntakeMessageService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class SimulatorController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();
        $branches = Branch::query()
            ->whereIn('id', $user?->visibleBranchIds() ?? [])
            ->orderBy('name')
            ->get();

        return view('simulator', [
            'branches' => $branches,
        ]);
    }

    public function store(Request $request, IntakeMessageService $intakeMessageService): RedirectResponse
    {
        $user = $request->user();
        $visibleBranchIds = $user?->visibleBranchIds() ?? [];

        $validated = $request->validate([
            'branch_id' => ['required', 'integer', Rule::in($visibleBranchIds)],
            'customer_phone' => ['required', 'regex:/^\+?[0-9]{8,15}$/'],
            'customer_name' => ['nullable', 'string', 'max:255'],
            'raw_message' => ['required', 'string', 'min:1', 'max:5000'],
        ]);

        $branch = Branch::query()
            ->whereKey($validated['branch_id'])
            ->whereIn('id', $visibleBranchIds)
            ->firstOrFail();

        $duplicateExists = IncomingMessage::query()
            ->where('organization_id', $user->organization_id)
            ->where('branch_id', $branch->id)
            ->where('from_identifier', $validated['customer_phone'])
            ->where('raw_text', $validated['raw_message'])
            ->exists();

        if ($duplicateExists) {
            throw ValidationException::withMessages([
                'raw_message' => 'This intake message was already submitted.',
            ]);
        }

        $result = $intakeMessageService->create(
            user: $user,
            branch: $branch,
            customerPhone: $validated['customer_phone'],
            customerName: $validated['customer_name'] ?? null,
            rawText: $validated['raw_message'],
        );

        return redirect()
            ->route('simulator.index')
            ->with('simulator_result', [
                'success' => true,
                'incoming_message_id' => $result['incoming_message']->id,
                'branch_name' => $branch->name,
                'customer_phone' => $validated['customer_phone'],
                'created_requests_count' => count($result['requests']),
                'request_status' => $result['request']->status,
                'requests' => collect($result['requests'])->map(static function ($request): array {
                    return [
                        'detected_number' => $request->detected_number,
                        'detected_amount' => $request->detected_amount,
                        'status' => $request->status,
                    ];
                })->all(),
                'parser_result' => $result['parser_result'],
                'customer_confirmation_text' => $result['customer_confirmation_text'],
                'message_response_id' => $result['message_response']->id,
            ]);
    }
}
