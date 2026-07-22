# Ecommerce domain (Module 11 extension)

Bounded context for click-and-collect retail ecommerce linked to Inventory SKUs.

## Implemented (extension)

- Separate ecommerce catalog (`ecommerce_products`) linked to required `inventory_item_id`
- Public shop APIs for booking carousel products and place-order (click-and-collect)
- Cash / pay-in-salon only (`cash_in_salon`, `unpaid` ? `paid_at_pickup` on collection)
- Order placement decrements stock via `InventoryMovementService` (`sale`, `MovementReferenceType::ECOMMERCE_ORDER`)
- Admin product CRUD + order list / status updates (`pending_pickup` ? `collected` | `cancelled`)
- Stock restored on admin cancel via adjustment movements
- Permissions: `ecommerce.view`, `ecommerce.manage`
- Audit logs on product and order mutations
- `EcommerceDemoSeeder` for BusinessTypeDemoSeeder integration

## Deferred (full Module 11)

- Online payment gateways
- Shipping / delivery addresses and carriers
- Subscriptions, wishlists, promotions engine
- Customer account portal and order history UI
- Automated notifications for order ready / collected

## Admin API (`/api/v1/admin`)

| Method | Path | Permission |
|---|---|---|
| GET | `/ecommerce/products` | ecommerce.view |
| POST | `/ecommerce/products` | ecommerce.manage |
| GET/PUT | `/ecommerce/products/{id}` | view / manage |
| PATCH | `/ecommerce/products/{id}/status` | ecommerce.manage |
| GET | `/ecommerce/orders` | ecommerce.view |
| GET | `/ecommerce/orders/{id}` | ecommerce.view |
| PATCH | `/ecommerce/orders/{id}/status` | ecommerce.manage |

## Public API (`/api/v1/shop`)

| Method | Path | Auth |
|---|---|---|
| GET | `/products?location_id=&carousel=1` | tenant header |
| POST | `/orders` | tenant header |

## Ownership

| Concern | Owner |
|---|---|
| Stock catalogue & movements | Inventory |
| Ecommerce merchandising & orders | Ecommerce |
| Payments at pickup | Ecommerce (cash-in-salon status only) |

See `product/04-delivery/implementation-roadmap.md` for full Module 11 scope.
