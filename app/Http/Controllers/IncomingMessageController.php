<?php

namespace App\Http\Controllers;

use App\Models\IncomingMessage;
use Illuminate\Contracts\View\View;

class IncomingMessageController extends Controller
{
    public function index(): View
    {
        $user = auth()->user();
        $query = IncomingMessage::query()
            ->with(['branch', 'customer'])
            ->orderByDesc('received_at');

        if ($user?->canViewAllBranchesForRead()) {
            if ($user?->organization_id) {
                $query->where('organization_id', $user->organization_id);
            } else {
                $query->whereRaw('1 = 0');
            }
        } else {
            $query->where('branch_id', $user?->branch_id ?? -1);
        }

        return view('incoming-messages.index', [
            'messages' => $query->limit(25)->get(),
        ]);
    }
}
