# Manufacturing and B2B: architecture review and implementation plan

Reviewed: 25 October 2026. Architecture approved by the user. The backend is documented in [MANUFACTURING_PHASE_2.md](MANUFACTURING_PHASE_2.md). The frontend and safely completed application-database installation are documented in [MANUFACTURING_PHASE_3.md](MANUFACTURING_PHASE_3.md). See the [user guide](MANUFACTURING_USER_GUIDE.md) for the operational workflow. The evidence below records the original architecture review, including database problems that have since cleared.

## Evidence and limits

Latest clarification: B2B stock is allocated by selecting main-stock products and quantities, and every B2B sale price is entered manually. This supersedes the automatic pricing precedence proposed below. The delivered scope is documented in [B2B_IMPLEMENTATION.md](B2B_IMPLEMENTATION.md), with operating steps in [B2B_USER_GUIDE.md](B2B_USER_GUIDE.md).

- Active application: repository root. `inventory-old/` is a separate copy and is outside the proposed implementation scope.
- Inspected models, controllers, utilities, middleware, routes, providers, views, migrations, tests, and the schema definitions in `db/invantory (1).sql`.
- Laravel boots and reports version 9.52.21; CLI PHP is 8.3.35. BCMath is available. PDO SQLite is unavailable in this CLI.
- The configured MySQL connection refused connections (PDO error 2002). No live table definitions, migration status, configured valuation method, balances, duplicate stock rows, or module installation markers could be verified.
- The SQL dump contains 85 table definitions, with InnoDB in the inspected core tables, and no named manufacturing/BOM/recipe/B2B/wholesale/channel tables. Its file timestamp is 31 August 2026; it predates several local migrations. It is evidence of a historical schema, not proof of the current database state.
- No migrations, seeds, imports, stock postings, or application changes were performed during this review. Existing uncommitted files and the untracked Manufacturing scaffold are user work and must be preserved.

## Existing architecture and reuse

| Area | Current implementation | Planned reuse |
| --- | --- | --- |
| Application | Laravel 9; Eloquent models mostly directly under `app/`; controllers under `app/Http/Controllers`; newer distribution services/repositories | Extend this application and its conventions |
| Modules | nWidart modules; `Modules/Manufacturing`; `modules_statuses.json`; `ModuleUtil` callbacks | Complete the existing Manufacturing module; add a B2B module with the same registration pattern |
| Product master | `Product`, `ProductVariation`, `Variation`; categories, brands, images, tax and units; `product_locations` | One master product; BOM inputs/outputs and prices reference concrete variations |
| Warehouse stock | `BusinessLocation`, `VariationLocationDetails.qty_available` | Keep existing location/variation balances authoritative |
| Purchasing | `PurchaseController`, `PurchaseLine`, purchase orders/returns; lot and expiry fields | Purchase raw materials and allowed purchased components through existing workflows |
| Stock entry/corrections | `OpeningStockController`, `StockAdjustmentController`, `StockTransferController`, `ProductUtil` | Reuse transaction types and purchase-layer links, with stronger posting safeguards |
| Stock valuation | `TransactionUtil::mapPurchaseSell`, `TransactionSellLinesPurchaseLines`, purchase-layer usage counters | Preserve FIFO/LIFO layer accounting and production consumption mapping |
| Sales/POS | `SellPosController`, `SellController`, `Transaction`, `TransactionSellLine`, receipt views, retail `/pos` routes | Reuse models and invoice infrastructure behind a dedicated B2B controller/service/UI |
| Customers | `Contact`, `CustomerGroup`; credit limit, payment terms, tax number, addresses and balances | B2B profile linked to an existing Contact |
| Payments/accounting | `TransactionPayment`, payment events and account transaction listeners | One payment/accounting record per posting; retain account integration |
| Authorization | Spatie permissions, `AuthServiceProvider`, business-scoped roles, user location permissions | Add backend abilities and use current business/location access rules |
| UI | Blade, LaravelCollective forms, existing Tailwind classes, vendor/Bootstrap/AdminLTE assets, jQuery, Select2, DataTables | Existing layouts, widgets, modals, table controls and translations |
| Reports/exports | `ReportController`, `CommonChart`, Maatwebsite Excel, DataTables and installed PDF libraries | Channel-aware reports and existing export/print mechanisms |

### Important distinctions

1. `products.type` controls `single`, `variable`, `modifier`, and `combo` behavior. Add a separate `manufacturing_type` with normal, raw_material, component, and finished_good values. Keep the current product structure selector and label the new field clearly.
2. `App\Customer` represents distribution SR/DSR staff with login, attendance and stock ownership. `Contact` represents commercial customers/suppliers. B2B customers must not be implemented by repurposing the employee model or `customer_stocks`.
3. A combo product currently consumes its children during sale. Manufacturing must consume inputs during production and receive an independent stock-tracked output. Manufactured outputs should initially support single/variable stocked products; reject combos/modifiers as outputs to prevent double consumption.
4. Product creation already has a separate opening-stock action. Manufacturing classification, BOM assignment, and B2B enrollment must create definitions only.

## Conflicts and prerequisites

| Finding | Consequence | Planned resolution |
| --- | --- | --- |
| Manufacturing scaffold renders Hello World; store/update/destroy are placeholders; no module migrations/entities implement production | Existing folder is not a completed manufacturing feature | Extend the scaffold in place |
| Runtime `/manufacturing` route has only `web` middleware | No authentication/tenant/permission protection on the scaffold route | Use the main application middleware stack plus action permissions before exposing real data |
| Core code already references `production_purchase`, `production_sell`, `mfg_quantity_used`, `mfg_recipes`, and missing `MfgRecipeIngredient` | Enabling legacy installation markers could activate missing-table/class paths | Reconcile these hooks explicitly; do not mark the module installed until required compatibility paths work |
| `ModuleUtil::isModuleInstalled` checks both module presence and a system version marker | `Manufacturing: true` alone does not establish installation | Verify live markers; provide an explicit install/upgrade path compatible with callbacks |
| Stock helpers perform unlocked read/modify/write or unconditional decrement; purchase-layer mapping also reads before updating without explicit locks | Transactions alone do not prevent overselling or stale updates | Shared locked posting primitive used by every competing writer to affected balances/layers |
| Distribution transfer service changes main stock directly, calculates with floats and deletes transfer records on reversal | Cannot reuse it unchanged as the B2B transfer implementation | Preserve the existing workflow; integrate shared stock safeguards where it competes with new postings; use auditable B2B reversals |
| `num_uf`, PurchaseLine accessors and financial code use floats despite DECIMAL storage | Reusing arithmetic unchanged violates precise calculation requirements | Decimal string calculations using BCMath; avoid float accessors in new posting paths; adapt necessary shared calculations |
| Dump has no unique variation/location balance constraint | Concurrent first receipts can create duplicate balances | Read-only preflight first; fail with a reconciliation report if duplicates exist; add a unique key only after consistency is established |
| Dump allows `avco`, but inspected settings expose FIFO/LIFO and mapping branches on LIFO versus the default path | Configured valuation cannot be assumed | Verify actual business configuration; do not silently reinterpret AVCO as FIFO |
| Existing queries frequently filter `transactions.type = sell` without a sales channel | B2B transactions would appear in retail lists/reports | Introduce explicit channel filtering across relevant retail paths; retain deliberate company-wide reports |
| `php artisan route:list --path=manufacturing --json` fails on unresolved `PaymentAccountController` | Standard route verification is currently blocked | Fix the legacy controller reference as a small prerequisite after approval; inspect routes directly in the meantime |
| `phpunit.xml` does not isolate DB settings; several tests create SQLite schemas in their own setup | Running a broad suite against default configuration is unsuitable | Dedicated disposable test database/configuration; enable PDO SQLite if retaining those existing tests; MySQL for locking tests |

No automatic reconciliation, historical rewriting, database import, or valuation-method change is proposed.

## Proposed data design

These names are proposed, not migrations already created. Compare them with the live schema before finalizing.

### Shared stock and product additions

- Add `products.manufacturing_type`, default normal; optional manufacturing profile for procurement/production settings. Reuse category, brand, description, units, images and active status.
- Keep output BOM assignment variation-specific; default BOM references must match the variation and business.
- Add a location profile defining main versus B2B inventory use. Existing locations remain main by default. A physical warehouse may have a separate logical B2B location, retaining `business_locations` and `variation_location_details` as the balance store.
- Do not create a second B2B balance for stock already counted in an existing location. B2B stock is the stock in designated B2B locations.
- Add an append-only `inventory_movements` ledger containing business, location, product, variation, signed base quantity, unit/total cost, movement kind, posting/reference IDs, actor, timestamp and reversal reference. Ledger and balance changes commit together.
- Add posting records/idempotency keys unique by business and operation, with a payload hash and result reference. An identical retry returns the result; reuse with different content is rejected.
- Existing history remains available through existing transactions. Any initial ledger baseline must be explicitly marked as a cutover snapshot; do not invent historical movements.

### Manufacturing additions

| Entity | Purpose and constraints |
| --- | --- |
| `mfg_boms` | Business/output variation, identity, default/active selection |
| `mfg_bom_versions` | Version number, expected output, wastage convention, cost estimates; published revisions immutable |
| `mfg_bom_items` | Version/input variation, configured unit, conversion snapshot, quantity and expected waste |
| `mfg_product_settings` | Procurement mode, production settings and variation-specific default BOM |
| `mfg_production_orders` | Tenant/location/output/version, planned quantity/dates, operator, status, expected and actual cost |
| `mfg_order_materials` | Order-time requirements snapshot, issued/consumed/returned quantities and cost references |
| `mfg_production_batches` | Independent partial runs, accepted/defective output, idempotent state transitions |
| `mfg_material_issues` and lines | Actual issues into WIP and source purchase-layer mappings; link to existing production consumption transactions |
| `mfg_production_outputs` | Accepted output receipts linked to existing production purchase transactions/purchase lines |
| `mfg_production_expenses` | Actual labor, machine, overhead and other costs with WIP/output attribution |
| `mfg_stock_reservations` | Optional reservation support if implemented consistently for all competing stock writers |

Keep stock in the existing balance tables. Raw Materials and Manufactured Components screens are filtered views of master products, not duplicate product tables. Use legacy recipe compatibility adapters or update existing joins/deletion guards explicitly; do not create two independent BOM sources.

### B2B additions

| Entity/change | Purpose and constraints |
| --- | --- |
| `b2b_product_settings` | Shared product enrollment, channel eligibility, purchase allowance, MOQ, selling multiple, carton quantity and status |
| `b2b_variation_prices` | Wholesale base price per concrete variation, with an explicit tax basis |
| `b2b_price_tiers` | Quantity thresholds and prices per variation; reject ambiguous overlapping rules |
| `b2b_customer_profiles` | Unique business/contact profile; registration/contact details, separate billing/shipping data and B2B credit terms/limit |
| `b2b_customer_prices` | Explicit customer/variation price and optional effective dates |
| Location profiles | Designate B2B stock locations while reusing existing location stock balances |
| `transactions.sales_channel` | Default retail; B2B sales still use existing sell transactions, lines, payments and returns |
| B2B transaction metadata | Invoice series, pricing source snapshots, terms/due-date snapshot, approval and idempotency references |
| Transfer posting metadata | Link paired existing sell_transfer/purchase_transfer transactions and reversal postings |

Avoid duplicate B2B sale/item/payment tables where current transaction relationships suffice. Derive B2B outstanding balances from B2B invoices, payments, opening entries and returns; do not treat `contacts.balance` as an independently maintained B2B receivables total. Retain a full company contact statement with a channel filter.

### Constraints and precision

- Match existing unsigned integer FK types; do not introduce incompatible bigint references to int parents.
- Every new tenant-owned record carries `business_id`. Validate related products, variations, locations, contacts, units and users within that tenant on the server.
- Foreign keys use restrictive deletion for posted records. Archive referenced definitions instead of deleting history.
- Index tenant/status/date/location queries; unique tenant/document numbers, BOM versions, enrollment keys, price rules, and operation keys.
- Use DECIMAL quantities/money compatible with existing `(22,4)` storage and decimal strings in PHP. Use higher intermediate precision and explicit rounding at posting boundaries; do not silently truncate unit conversions below supported stock precision.

## Posting rules proposed for approval

### Production

1. Save Draft/Planned orders without stock changes. Snapshot the selected BOM version, unit factors and estimated costs.
2. Validate the entire tenant BOM graph against direct/indirect cycles. A component can be both a produced output and another BOM's input.
3. Expand nested BOMs for planning and shortages. Parent assembly consumes available component stock; it does not also consume the component's original raw materials. Create separate linked child production orders when component production is needed.
4. Start a batch by recording actual issued inputs into WIP. Under one transaction, lock order/batch, stock rows and purchase layers, validate availability, post `production_sell` consumption and movement records, and update purchase-layer usage once.
5. Receive accepted output incrementally with `production_purchase` receipts, costed purchase lines and matching ledger movements. Defects do not increase sellable stock. Costs of rejected units are carried by accepted units under the proposed default rule.
6. Actual cost is issued material/component layer cost plus labor, machine, overhead and other expenses, less valued material returns. Component input cost already includes its own manufacturing cost; never recursively add it again.
7. Maintain unreleased WIP cost separately from received output cost. Snapshot each receipt's allocated cost; finalized receipts do not change when BOMs or current prices change. Reject positive output receipts without accepted quantity and prevent finalization with unallocated WIP; handle a zero-accepted failed batch through explicit scrap/write-off.
8. Support Draft, Planned, In Progress, Partially Completed, Completed and Cancelled through explicit commands, not arbitrary status edits. Distinguish material use, reusable returns, scrap and accepted output.
9. Cancelling an untouched order changes status only. Posted issues/receipts require linked reversal entries. A receipt already consumed, transferred or sold must not be erased or blindly reversed; follow dependent reversals or an authorized corrective posting. Retain all original records.

### B2B

1. Default all existing products to current retail behavior. B2B enrollment is explicit; enabling both channels shares the product identity, while stock remains location-separated.
2. Retail product searches, featured grids, barcode lookups and backend sale validation must enforce retail eligibility and main locations. B2B paths enforce B2B eligibility and B2B locations. A handcrafted request cannot bypass these rules.
3. Transfers debit the source and credit the destination exactly once, preserve cost layers/lot identity, and retain paired transactions and movement records. Lock both locations in deterministic order and require access to both.
4. Proposed pricing precedence: explicit customer price, otherwise highest applicable quantity tier, otherwise base wholesale price. Authorized manual discounts apply afterward and are separately recorded. The UI shows the selected pricing source; the server recalculates it.
5. Validate MOQ and selling multiples in the product's configured unit after conversion. Snapshot carton quantity and tax treatment on the invoice.
6. Lock the customer credit account/profile while checking credit and posting credit sales/payments/returns. Proposed B2B credit limit covers B2B exposure; show combined company exposure separately. Never infer that credit permission allows exceeding the configured limit.
7. Support existing configured payment methods and split/partial payments; reject payment totals exceeding the remaining payable amount. A fully paid sale does not require credit-sale permission; any new due balance does.
8. Keep stored statuses compatible with paid/due/partial. Derive Overdue from due date and unpaid balance. Use a separate B2B invoice sequence and preserve printed customer/address/tax/pricing snapshots.
9. Approved item-level returns cannot exceed net sold quantities. Restore stock at original cost and reverse original layer usage as appropriate; reduce receivables before refunding excess collections. Repeated payments/returns/sales use unique operation keys.
10. New workflows prohibit negative stock by default. Existing retail overselling settings remain intact; any future override for new operations requires explicit permission, configuration and an audit reason.

Reservations, if introduced, must reduce availability in retail, distribution, purchasing returns, adjustments and transfers too. Otherwise show availability checks without claiming stock is reserved.

## Implementation phases and review deliverables

Each phase will report actual changed files, migrations, routes, checks and remaining limitations. The following are targets, not completed work.

| Phase | Files/areas | Proposed migrations and routes | Verification |
| --- | --- | --- | --- |
| 1: review | This document | No migration or route changes | Source/schema review, runtime route inspection, selected baseline tests |
| 2: manufacturing backend | Existing Manufacturing module: entities, services, FormRequests, controllers, DataController callbacks; shared posting/decimal helpers; necessary core integration points | Additive manufacturing/shared-ledger migrations; protected `/manufacturing/boms`, `/production-orders`, explicit start/issue/receipt/cancel/reverse endpoints and read endpoints under `/manufacturing` | BOM cycles/versions/conversions, costing/WIP, shortages, partials, retries, reversals, tenant/location/permission tests; real DB locking tests |
| 3: manufacturing frontend | Module Blade views/assets/lang; existing product create/edit/detail integration; sidebar and relevant report hooks | `/manufacturing`, `/manufacturing/raw-materials`, `/components`, `/boms`, `/production-orders`, `/history`, `/stock-movements`, `/reports` | Forms, product identity/no automatic stock, shortages/progress, responsive UI, report/export access, purchase-to-production flow |
| 4: B2B backend | New B2B module; pricing/customer/checkout/payment/return services; shared stock and transaction integration; retail eligibility/report filters | Additive enrollment/pricing/profiles/location/channel metadata migrations; `/b2b/products`, `/customers`, `/inventory`, `/transfers`, `/sales`, `/payments`, `/returns` | Pricing precedence, MOQ, split payments, credit race, transfer conservation, channel/tenant/location isolation, retries and returns |
| 5: B2B frontend/POS | Existing layout/components with B2B views/assets/translations; dedicated controller and POS bundle | `/b2b`, `/b2b/pos`, `/b2b/invoices`, `/b2b/payments-dues`, `/b2b/reports` and backend phase routes | Search/barcodes, cart price source, discounts/tax, invoice printing/PDF, statements, date/location filters and exports |
| 6: integration | Dedicated test environment, fixtures and regression suites | No new business behavior unless a verified defect requires it | Full manufacturing-to-B2B-sale flow and existing retail/distribution regression; MySQL concurrency and rollback tests |

All module web routes will use `web`, `setData`, `auth`, `SetSessionData`, `language`, `timezone`, `AdminSidebarMenu` and `CheckUserLogin`, matching the existing protected group, plus backend action permissions. Use existing APIs only where needed; new APIs require explicit guard/tenant mapping rather than copying public endpoints.

### Permission groups

- Manufacturing: view, materials.manage, components.manage, bom.manage, orders.create, orders.start, orders.complete, stock.adjust and reports.view.
- B2B: dashboard.view, products.manage, customers.manage, stock.manage, stock.transfer, pos.access, sales.create, discounts.approve, credit.manage, payments.record, returns.process and reports.view.
- Use `manufacturing.*` / `b2b.*` namespaces, register through role UI/module callbacks, and enforce abilities on each command/read/export. Menu visibility is supplementary. Preserve business Admin role behavior and existing contact/location restrictions.

### Acceptance coverage

The regression matrix will include all 20 cases in the request: raw purchase receipt; component consumption; final assembly; nested quantities; insufficient stock; partial production; duplicate completion; actual cost; existing product listing; B2B-only retail exclusion; wholesale price; tiers; allocation/location-only deduction; transfer conservation; credit outstanding; partial payment; returns; unauthorized access; concurrent oversell prevention; retail/product workflow compatibility.

Additional cases: cross-tenant related IDs, forbidden warehouse IDs, missing price/configuration, expired lots, invalid unit conversions, cycle activation races, duplicate first stock receipt, idempotency-key payload conflict, credit concurrency, output reversal after downstream use, ledger/balance reconciliation and migration preflight with duplicates.

## Checks actually performed

| Check | Result |
| --- | --- |
| PHP/Laravel runtime | PHP 8.3.35; Laravel 9.52.21; application bootstrap succeeded |
| Decimal/test driver availability | BCMath available; PDO SQLite unavailable |
| Live MySQL read-only inspection | Connection refused, error 2002; no live schema/data claims |
| Bundled schema review | 85 tables; inspected product/variation/location/transaction/purchase/payment/contact/unit definitions and indexes |
| Direct registered-route inspection | Retail routes retain current protected middleware; `/manufacturing` currently only has `web`; no B2B route found |
| Standard Artisan route listing | Failed on existing unresolved `PaymentAccountController` reference in `routes/web.php` |
| `php vendor/phpunit/phpunit/phpunit --do-not-cache-result tests/Unit/HolidayTadaServiceTest.php` | PASS: 4 tests, 5 assertions |
| `php vendor/phpunit/phpunit/phpunit --do-not-cache-result tests/Unit/CustomerSaleReturnTest.php` | 3 tests executed, 13 assertions, 1 error from unavailable MySQL during Eloquent schema lookup; 2 tests passed |

These are baseline checks only. No manufacturing/B2B feature is implemented or verified by this analysis.

## Approval checkpoint and next action

Approve the architecture and proposed posting rules before Phase 2, as required by the project brief. The proposed defaults needing business agreement are logical B2B stock locations, customer-specific price precedence, B2B-only credit exposure, and rejected-unit cost allocation to accepted units.

After approval: establish a disposable test database; restore live database connectivity for read-only schema/migration/valuation/duplicate preflight; reconcile legacy manufacturing hooks and route verification; implement the first additive backend slice with meaningful tests. No destructive import, automatic stock reconciliation, or existing financial-history rewriting is included.
