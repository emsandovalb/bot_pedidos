<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\ProductBulkImportService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ProductImportController extends Controller
{
    public function __construct(
        private readonly ProductBulkImportService $bulkImportService,
    ) {
    }

    public function index(Request $request): View
    {
        $this->authorizeManageProducts($request->user());

        return view('products.import');
    }

    public function store(Request $request): RedirectResponse
    {
        $user = $request->user();
        $this->authorizeManageProducts($user);

        $validated = $request->validate([
            'content' => ['required', 'string'],
        ]);

        $summary = $this->bulkImportService->import(
            organization: $user->organization,
            content: $validated['content'],
        );

        return redirect()
            ->route('products.import')
            ->with('import_summary', $summary)
            ->with('status', 'Products imported successfully.');
    }

    private function authorizeManageProducts(?User $user): void
    {
        abort_unless($user?->organization_id !== null && $user->canViewAllBranches(), 403);
    }
}
