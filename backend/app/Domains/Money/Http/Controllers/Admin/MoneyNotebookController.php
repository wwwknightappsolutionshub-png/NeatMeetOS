<?php

namespace App\Domains\Money\Http\Controllers\Admin;

use App\Domains\Identity\Http\Controllers\Controller;
use App\Domains\Money\Http\Resources\MoneyEntryResource;
use App\Domains\Money\Models\MoneyEntry;
use App\Domains\Money\Services\MoneyNotebookService;
use App\Shared\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class MoneyNotebookController extends Controller
{
    public function __construct(
        private readonly MoneyNotebookService $notebook,
    ) {}

    public function summary(Request $request): JsonResponse
    {
        $data = $request->validate([
            'month' => ['nullable', 'string', 'regex:/^\d{4}-\d{2}$/'],
        ]);

        return ApiResponse::success($this->notebook->summary($data['month'] ?? null));
    }

    public function ledger(Request $request): JsonResponse
    {
        $data = $request->validate([
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d'],
            'direction' => ['nullable', 'string', Rule::in(['all', 'inflow', 'outflow'])],
        ]);

        return ApiResponse::success($this->notebook->ledger(
            $data['from'] ?? null,
            $data['to'] ?? null,
            $data['direction'] ?? 'all',
        ));
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'kind' => ['required', 'string', Rule::in(MoneyEntry::kinds())],
            'category' => ['required_if:kind,'.MoneyEntry::KIND_SPEND, 'nullable', 'string', Rule::in(MoneyEntry::spendCategories())],
            'amount_pounds' => ['nullable', 'numeric', 'min:0'],
            'amount_cents' => ['nullable', 'integer', 'min:0'],
            'occurred_on' => ['required', 'date'],
            'note' => ['nullable', 'string', 'max:255'],
        ]);

        $entry = $this->notebook->create($data);

        return ApiResponse::success((new MoneyEntryResource($entry))->resolve(), 'Saved', 201);
    }

    public function destroy(string $id): JsonResponse
    {
        $this->notebook->delete($id);

        return ApiResponse::success(null, 'Removed');
    }
}
