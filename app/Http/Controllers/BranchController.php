<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use Illuminate\Contracts\View\View;

class BranchController extends Controller
{
    public function index(): View
    {
        $user = auth()->user();
        $query = Branch::query()->with('organization')->orderBy('name');

        if ($user?->canViewAllBranchesForRead()) {
            if ($user?->organization_id) {
                $query->where('organization_id', $user->organization_id);
            } else {
                $query->whereRaw('1 = 0');
            }
        } else {
            $query->whereKey($user?->branch_id ?? -1);
        }

        return view('branches.index', [
            'branches' => $query->get(),
        ]);
    }
}
