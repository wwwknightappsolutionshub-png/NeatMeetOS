# Inventory Consumption Contracts

**Authority:** Inventory (Module 7)  
**Trigger:** POS emits consumption intents; Inventory executes movements

## Consumption types (`InventoryConsumptionType`)

| Type | Trigger line | Behavior |
|---|---|---|
| `retail_sale` | `retail_product` checkout line | Decrement retail stock on `checkout.completed` |
| `professional_use` | `appointment_service` line (recipe-driven later) | Decrement professional/backbar stock |
| `reversal` | void/refund | Restore quantities |

## Request payload (`InventoryConsumptionRequestDto`)

```json
{
  "checkout_id": "uuid",
  "checkout_line_id": "uuid",
  "consumption_type": "retail_sale | professional_use | reversal",
  "product_id": "uuid",
  "quantity": "decimal",
  "location_id": "uuid",
  "appointment_service_line_id": "uuid|null",
  "recipe_snapshot": {}
}
```

## Emission rules

| Event | Inventory action |
|---|---|
| `checkout.completed` | POS builds requests for all stock-backed lines; Inventory processes atomically |
| `checkout.voided` | POS emits `reversal` requests with original line references |
| Partial refund | Payments/POS emit targeted reversals (Module 8) |

## Service consumption paths

- **Phase 1 (Module 7):** line-driven placeholder — service lines may reference a default product_id in snapshot
- **Phase 2:** recipe/BOM per `booking_service` drives multiple consumption lines

Inventory **never** owns checkout totals or appointment state.

## Event

`stock.consumed`, `stock.reversed` — see [commerce-events-catalog.md](./commerce-events-catalog.md)
