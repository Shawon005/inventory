# Manufacturing backend: Phase 2

Date: 25 October 2026. Implements the backend portion of the approved architecture. The manufacturing UI/product-form additions belong to Phase 3; B2B belongs to Phases 4–5. This document does not certify those later phases.

## Installation state

Current status: both migrations are now installed after a private backup and a successful rehearsal against its disposable restoration. The database is readable and preflight returns `ready: true`. Product/transaction counts and the stock checksum were preserved. The frontend is delivered in [Phase 3](MANUFACTURING_PHASE_3.md); see its installation and verification details and the [user guide](MANUFACTURING_USER_GUIDE.md). The following paragraphs and Phase 2 verification table describe the earlier state at the end of backend work.

The application database has not been migrated. MySQL is reachable, but its existing core tables cannot be read. `SHOW CREATE TABLE products` returned MariaDB error 1932, “table doesn't exist in engine”; reading business and migration tables also fails. No repair, import, stock correction or live migration was attempted.

Run the new read-only check with:

```powershell
php artisan manufacturing:preflight --json
```

It currently returns `ready: false` and lists unreadable core tables. PHP BCMath, a healthy database and a verified backup are required before deployment. Rehearse against a restored disposable database, then run normal migrations with the Manufacturing module enabled. This change does not create a `manufacturing_version` installation marker or turn on the old commercial module UI hooks.

## Implemented behavior

- Existing products have a separate `manufacturing_type`: normal, raw_material, component, or finished_good. Existing single/variable/combo structure remains separate. Manufacturing configuration and product classification create no stock.
- Raw material/component purchasing uses the existing purchase-line utility. Stock conversion retains four decimal places even when the UI displays two; purchase quantities and line costs use decimal arithmetic. Manufacturing purchase receipts/edits through this utility create auditable movements. Internal-only procurement settings reject new external purchases.
- BOM headers use the existing output variation. Revisions retain input units, conversion factors, expected wastage, quantities and estimated labor/machine/overhead/other costs. Revision publication and activation check for indirect cycles under a tenant lock.
- Planning exposes immediate material requirements, availability/shortages, and exploded multi-level leaf requirements. Stock is not reserved. Child orders can reference an open parent order that needs their output; actual component production remains a separate order.
- Orders snapshot their selected BOM revision. Draft/planned orders can be edited before any batch exists. Later BOM edits do not change that order snapshot or posted production costs.
- Starting a batch deducts actual issued materials from the selected warehouse and records WIP cost, existing `production_sell` transactions, exact source purchase layers, and movement history. Input overrides must list exactly the BOM input variations. Actual material waste can be recorded separately from usable quantities.
- Unused material returns restore the original cost layers, reduce WIP cost, and record the return reason in the posting payload. Recorded waste cannot be returned as unused material.
- Completing a batch receives accepted output through existing `production_purchase` transactions and costed purchase lines. Product/location enrollment makes that stock visible to existing location-based product queries. Defective output does not create stock.
- Partial production uses multiple sequential batches. One batch is closed by one receipt; accepted plus defective quantity must equal that batch's planned quantity. Another batch can produce the remaining accepted quantity needed by the order.
- Actual material cost plus actual labor, machine, overhead and other costs is divided by accepted output. Component input costs already include their previous production cost; raw materials are not consumed again during assembly.
- FIFO/LIFO follow the business configuration. Cost layers use `purchase_price_inc_tax`, matching the existing inventory valuation and retail COGS basis. AVCO and expired input layers are rejected explicitly.
- Receipt unit cost is stored at four decimal places. Any residual between total batch cost and quantity × rounded unit cost is recorded as a separate `production_rounding` movement.
- A zero-accepted failed run requires the explicit scrap command and a reason. It writes off batch cost and creates no output receipt.
- Batch reversals preserve original records, add linked inverse movements and restore remaining material-layer usage. A produced layer already sold, transferred, adjusted, returned or consumed cannot be reversed until its dependencies are reversed. Cancellation requires all posted batches to have been reversed.
- Authorized positive/negative manufacturing adjustments are transactional and idempotent. Positive adjustments require an explicit cost and create existing opening-stock purchase layers; negative adjustments consume existing layers through stock-adjustment transactions.
- Existing explicit stock reconciliation uses a locked, audited balance correction after installation. It does not manufacture a new cost layer. Viewing stock history no longer silently overwrites warehouse stock.
- Backend permissions and warehouse/business checks cover command services as well as JSON endpoints. Existing retail overselling settings remain respected by legacy helpers; manufacturing production and adjustments forbid negative balances.

Production expenses are cost records; they do not issue cash payments or payroll automatically. Material requirements are planning information and do not promise reservations.

## Changed files

| Area | Files |
| --- | --- |
| Shared decimal/stock/valuation | `app/Support/Decimal.php`, `app/Services/InventoryStockService.php`, `app/Services/PurchaseLayerService.php`, `app/InventoryMovement.php` |
| Existing models | `app/Product.php`, `app/Variation.php`, `app/PurchaseLine.php`, `app/Transaction.php`: protect BOM references and consumed purchase layers |
| Existing stock/purchase integration | `app/Utils/ProductUtil.php`, `app/Utils/TransactionUtil.php`, `app/Services/CustomerStockTransferService.php`: shared stock locks, decimal purchase posting, purchase audits, locked purchase allocation and correct returned-material history |
| Existing UI/backend integration | `app/Http/Controllers/ProductController.php`: remove stock writes from history reads; `app/Http/Controllers/RoleController.php`: expose installed backend abilities in the existing role editor |
| Manufacturing entities | `Modules/Manufacturing/Entities/{Bom,BomVersion,BomItem,ProductionOrder,ProductionBatch,MfgRecipeIngredient}.php` |
| Manufacturing services | `Modules/Manufacturing/Services/{ManufacturingAccess,ManufacturingCatalog,BomService,PostingService,ProductionService,StockAdjustmentService,PurchasePolicy,PurchaseInventoryAudit,ProductReferences}.php` |
| Manufacturing endpoints | `Modules/Manufacturing/Http/Controllers/{ManufacturingController,DataController}.php`, `Http/Requests/ManufacturingRequest.php`, `Http/Middleware/EnsureManufacturingReady.php`, `Routes/web.php`, `Routes/api.php` |
| Registration/compatibility | `Modules/Manufacturing/Providers/ManufacturingServiceProvider.php`, `Console/PreflightCommand.php`, `Resources/lang/en/lang.php`; legacy recipe references use the new BOM storage |
| Tests | `phpunit.manufacturing.xml`, `tests/Feature/ManufacturingBackendTest.php`, `tests/Unit/ManufacturingDecimalTest.php`, `tests/Support/{ManufacturingTestCase,manufacturing_worker,rehearse_manufacturing_schema}.php` |
| Documentation | This document and the approved architecture document |

The pre-existing uncommitted distribution/shop-owner changes are preserved. `.env` and PHP's global configuration are unchanged.

## Migrations

1. `database/migrations/2026_10_25_000001_add_manufacturing_stock_foundation.php`: product classification, unique variation/location balance key, posting/idempotency records and movement ledger. Duplicate balances stop installation before DDL; they are not silently merged.
2. `Modules/Manufacturing/Database/Migrations/2026_10_25_000002_create_manufacturing_tables.php`: BOMs/revisions/items, product settings, orders, batches, issued cost layers, actual expenses and nine permission abilities. Output receipts are represented by existing purchase lines linked from each batch.

FK types match the existing unsigned integer master IDs. Posted documents, BOMs, configuration and classified products cause destructive rollback attempts to stop. Empty new tables can be rolled back. Permission definitions are retained on rollback so role assignments are not silently rewritten.

Ledger records start with new postings. Existing historical transactions remain the source for pre-installation stock history; no historical movements or opening balance snapshots are invented. Existing retail/distribution documents remain in their current transaction/history systems. The new ledger is not a replacement for those existing histories.

## Routes and permissions

These are authenticated web JSON routes using the existing web/session/CSRF, business-session, language, timezone, menu and login middleware, plus manufacturing readiness checks. Set `Accept: application/json`.

| Method/path | Ability or purpose |
| --- | --- |
| `GET /manufacturing` | `manufacturing.view`; order counts filtered by warehouse access |
| `GET /manufacturing/products` | `manufacturing.view`; filter by type/search |
| `PATCH /manufacturing/products/{id}` | Manage the applicable material/component classification and procurement settings |
| `GET /manufacturing/boms`, `GET /manufacturing/boms/{id}` | `manufacturing.view`; BOM revisions and items |
| `POST /manufacturing/boms` | `manufacturing.bom.manage`; create the first published version |
| `POST /manufacturing/boms/{id}/versions` | `manufacturing.bom.manage`; publish a new immutable revision |
| `PATCH /manufacturing/boms/{id}/active` | `manufacturing.bom.manage`; activate/archive a BOM |
| `GET /manufacturing/bom-versions/{id}/requirements` | `manufacturing.view`; quantity/location preview and nested explosion |
| `GET /manufacturing/production-orders`, `GET /manufacturing/production-orders/{id}` | `manufacturing.view`; paginated orders, batches, issues and expenses |
| `POST /manufacturing/production-orders`, `PATCH /manufacturing/production-orders/{id}` | `manufacturing.orders.create`; create/edit an unstarted order |
| `POST /manufacturing/production-orders/{id}/plan` | `manufacturing.orders.create` |
| `POST /manufacturing/production-orders/{id}/start` | `manufacturing.orders.start`; issue materials for a new batch |
| `POST /manufacturing/production-orders/{id}/cancel` | `manufacturing.orders.start`; reason required; posted batches must already be reversed |
| `POST /manufacturing/production-orders/{id}/batches/{batchId}/receive` | `manufacturing.orders.complete` |
| `POST /manufacturing/production-orders/{id}/batches/{batchId}/return-materials` | `manufacturing.stock.adjust`; restore unused material |
| `POST /manufacturing/production-orders/{id}/batches/{batchId}/scrap` | `manufacturing.stock.adjust`; close a zero-accepted failed run |
| `POST /manufacturing/production-orders/{id}/batches/{batchId}/reverse` | `manufacturing.stock.adjust`; audited reversal |
| `POST /manufacturing/stock-adjustments` | `manufacturing.stock.adjust`; documented positive/negative adjustment |
| `GET /manufacturing/stock-movements` | `manufacturing.view`; paginated ledger with warehouse access filtering |
| `GET /manufacturing/reports` | `manufacturing.reports.view`; date/location filters and order/cost totals |

`GET /api/manufacturing` retains the scaffold identity response for compatibility, protected by Passport authentication and `manufacturing.view`. It does not expose stock-changing commands.

The full permission set is view, materials.manage, components.manage, bom.manage, orders.create, orders.start, orders.complete, stock.adjust and reports.view, each prefixed with `manufacturing.`. Existing company Admin behavior is retained.

## Request examples

Money and quantity values must be decimal **strings**, without thousands separators or scientific notation. IDs are integers. Values are stored at four decimal places; conversions use higher intermediate precision.

Every BOM save/revision, production command and stock adjustment requires a unique `Idempotency-Key` header (8–80 letters/digits/`_.:-`), or an `idempotency_key` body field. Reuse the same key and identical payload when retrying a request. Reusing the key with different content returns validation errors. Permissions and warehouse access are checked again on retries.

Example BOM, with IDs replaced by records from the current business:

```json
{
  "name": "Plastic bulb body",
  "variation_id": 20,
  "output_quantity": "1",
  "labor_cost": "0.20",
  "items": [
    { "variation_id": 10, "unit_id": 2, "quantity": "40", "wastage_percent": "0" }
  ]
}
```

When unit 2 is configured as grams under a KG product unit, that recipe uses 0.0400 KG per body. Create an order with `bom_version_id`, `location_id`, `planned_quantity: "100"`, and `status: "planned"`. Optional fields include planned dates, assigned_to, notes and parent_order_id.

Start a batch with `{"quantity":"100"}`. It uses the snapshotted requirements by default. To record actual issues, supply `materials: [{"variation_id":10,"quantity":"4.1","wastage_quantity":"0.1"}]`; these quantities are in the product's inventory unit (KG), not the recipe's selected grams.

Receive that batch with:

```json
{
  "accepted_quantity": "100",
  "defective_quantity": "0",
  "expenses": { "labor_cost": "20", "machine_cost": "0", "overhead_cost": "0", "other_cost": "0" },
  "lot_number": "BODY-RUN-001"
}
```

Optional `expiry_date` must use `YYYY-MM-DD`. Actual expenses default to zero when omitted; estimated BOM expenses are not silently treated as actual expenditure.

## Verification and remaining boundaries

The isolated test fixture uses existing core relationships and applies the real new migrations. InnoDB runs create random databases under `codex_mfg_test_*`, and remove only the database created by that test. Worker processes reject all other database names. The schema rehearsal creates a separate `codex_mfg_rehearsal_*` database and executes only the bundled CREATE/ALTER definitions, with no customer/business INSERT data.

Commands:

```powershell
# Enable the already-present SQLite driver for this process only.
php -d extension=pdo_sqlite vendor/phpunit/phpunit/phpunit -c phpunit.manufacturing.xml

# Real InnoDB tests, including concurrent deductions and duplicate requests.
$env:MFG_TEST_MYSQL = '1'
php -d extension=pdo_sqlite vendor/phpunit/phpunit/phpunit -c phpunit.manufacturing.xml
Remove-Item Env:\MFG_TEST_MYSQL

# Rehearse migrations and empty rollback against the full bundled core schema.
php tests/Support/rehearse_manufacturing_schema.php
```

Final verification:

| Check | Result |
| --- | --- |
| Manufacturing suite on MariaDB/InnoDB | PASS: 52 tests, 158 assertions; includes real concurrent worker processes |
| Manufacturing suite on SQLite | PASS: 52 tests executed, 145 assertions, 2 InnoDB-only tests explicitly skipped |
| Existing regression selection | PASS: 44 tests, 295 assertions across ShopOwnerApiTest, ShopOwnerManagementTest, CustomerShopVisitApiTest, SrDealerStockTest, DsrPayrollTest and HolidayTadaServiceTest |
| Bundled-schema rehearsal | PASS: all 85 core table CREATE definitions and their ALTER statements; both new migrations apply; empty rollback succeeds |
| PHP syntax | PASS: 49 relevant PHP files |
| Manufacturing route inspection | PASS: 23 web routes; all actions resolve and all include authentication |
| Changed tracked-file whitespace check | PASS |
| Live installation preflight | BLOCKED: existing application core tables are unreadable; live migrations are unapplied |

Additional checks cover installation refusal on duplicate balances, rollback refusal with business records, real Spatie permission grants/denials, cross-business IDs, restricted locations, precision, unit families, expired lots, tax-inclusive valuation, returns, scrap, rounding and downstream-use reversal rejection.

The existing global `artisan route:list` failure involving the absent `PaymentAccountController` remains outside this module's functional routing. Manufacturing routes are verified through HTTP tests and direct registered-route inspection. The entire legacy retail UI/browser workflow has not been certified; the selected regression suite and stock/layer integration tests are narrower evidence. Frontend screens, complete company-wide ledger integration, B2B, exports and the final end-to-end acceptance phase remain later work.
