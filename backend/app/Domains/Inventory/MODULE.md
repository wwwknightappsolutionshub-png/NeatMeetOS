# Inventory domain (Module 7A)

Bounded context for stock catalogue, location levels, movement ledger, suppliers, and service consumption rules.

## Scope (Step 14)

- Retail and professional inventory items
- Per-location stock levels with reorder thresholds
- Append-only movement ledger (authoritative stock changes)
- Lightweight suppliers
- Service-linked consumption rules (Booking service ? professional stock)
- Commerce consumption contract execution (`StockConsumptionExecutionContract`)

## Stock model

- **Levels** (`inventory_levels`) hold `on_hand_quantity` per item/location
- **Movements** (`inventory_movements`) are the only way to change on-hand stock
- **Negative stock policy:** blocked by default; pass `allow_negative: true` on manual movements for explicit admin override

## Movement types

`opening`, `adjustment`, `purchase_receipt`, `sale`, `service_consumption`, `waste`, `transfer_in`, `transfer_out`

Low stock: `on_hand_quantity <= reorder_point`

## Ownership

| Concern | Owner |
|---|---|
| Booking services | Booking |
| Consumption rules config | Inventory (references `bookable_services`) |
| Stock execution | Inventory |
| POS checkout | POS (deferred) — emits consumption requests |

## Admin API (`/api/v1/admin`)

| Method | Path | Permission |
|---|---|---|
| GET/POST | `/inventory/items` | view / manage |
| GET/PUT/PATCH | `/inventory/items/{id}` | view / manage |
| GET | `/inventory/items/{id}/levels` | view |
| PUT | `/inventory/items/{id}/levels/{locationId}` | manage |
| GET/POST/PUT/PATCH | `/inventory/suppliers` | view / manage |
| GET | `/inventory/levels` | reporting.view |
| GET/POST | `/inventory/movements` | view / adjust |
| GET/POST/PUT/PATCH | `/inventory/service-consumption-rules` | view / manage |
| POST | `/inventory/consume` | adjust |

## Commerce events

- `stock.consumed` — sale / service consumption
- `stock.restocked` — purchase receipt
- `stock.adjusted` — opening, adjustment, waste, transfers
- `stock.reversed` — reversal consumption type

## Deferred

- Purchase orders, stocktake sessions, barcode scanning
- FIFO / weighted average costing, forecasting
- POS basket UX, auto-deduction on appointment completion
- Product image upload pipeline
