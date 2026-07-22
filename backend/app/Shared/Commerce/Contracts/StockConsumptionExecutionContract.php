<?php

namespace App\Shared\Commerce\Contracts;

use App\Shared\Commerce\DTO\InventoryConsumptionRequestDto;

interface StockConsumptionExecutionContract
{
    /**
     * Execute stock consumption requests from POS / commerce flows.
     *
     * @param  list<InventoryConsumptionRequestDto>  $requests
     * @return array{
     *     processed: list<array{movement_id: string, product_id: string, quantity: string}>,
     *     failures: list<array{product_id: string, reason: string}>
     * }
     */
    public function execute(array $requests, ?string $teamMemberId = null): array;
}
