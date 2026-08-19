<?php

namespace App\Domains\Money\Http\Resources;

use App\Domains\Money\Models\MoneyEntry;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MoneyEntryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $labels = MoneyEntry::spendCategoryLabels();
        $categoryLabel = $this->kind === MoneyEntry::KIND_CASH_IN
            ? 'Cash I added'
            : ($labels[$this->category] ?? 'Other');

        return [
            'id' => $this->id,
            'kind' => $this->kind,
            'category' => $this->category,
            'category_label' => $categoryLabel,
            'amount_cents' => (int) $this->amount_cents,
            'occurred_on' => $this->occurred_on?->toDateString(),
            'note' => $this->note,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
