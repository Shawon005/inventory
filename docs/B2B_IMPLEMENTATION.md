# B2B allocated inventory and manual pricing

The user's latest instruction specifies purchased raw materials -> manufactured parts -> final assembly, followed by allocation of selected main-stock quantities into B2B and manually priced B2B sales. Manufacturing already supported that chain. Its combined production/B2B test verifies two separately produced parts feeding one assembled product, with no repeated raw-material deduction.

This B2B delivery implements that clarified stock/pricing flow. It does not claim completion of every automatic-pricing, customer-statement, purchasing or acceptance item in the original broader brief. See [B2B_USER_GUIDE.md](B2B_USER_GUIDE.md).

## Backend

- New `Modules/B2B` module, existing Laravel/session/CSRF authentication stack, business/warehouse checks and ten B2B permissions.
- Explicit product enrollment, reuse/create commercial Contacts with B2B credit limits and terms, filtered catalog, allocated inventory, multiple-product transfers both directions, manual-price checkout, split/credit payments, item returns/refunds, immutable invoice snapshots, reports and CSV.
- Existing master products, `variation_location_details`, purchase cost layers, sell/purchase transfer transactions, sale lines, purchase/sale mapping, transaction payments and payment/account events are reused. There is no duplicate B2B quantity table or independent payment ledger.
- Each main warehouse lazily receives an internal logical stock location for its B2B allocation. Access follows the parent warehouse. The internal location is hidden from normal location selection and cannot be named in main web/API inventory requests.
- Transfers consume eligible source cost layers and create matching destination layers preserving unit cost, lot, manufacture date and expiry. The sum of main and B2B stock is unchanged by a transfer. Transfers create paired movements and transfer records, without customer sales/payment records.
- Checkout requires an explicit positive decimal unit price on each line. Catalog responses do not expose retail prices as a pricing default. Final prices are entered manually; no additional automatic tax/discount is calculated. Retail prices remain unchanged.
- Stock commands use the shared variation/balance locks, purchase-layer allocation and business posting mutex. Duplicate retries return the original result; changed payloads with the same operation key are rejected. Negative stock and missing eligible layers are rejected transactionally.
- Company B2B credit exposure is checked under the posting mutex. Collections cannot overpay. Returns reduce unpaid amounts first and require an exact refund for excess collections. They restore the original sale cost layers and preserve original invoice/return/payment records.
- Main Eloquent location/balance queries exclude internal stock. Main Transaction/TransactionPayment queries exclude B2B documents. Product list, retail search, manufacturing reads and main stock-report queries enforce main-stock visibility. Main stock history deliberately includes native transfer movements at the main location.
- Product structure/unit/deletion and transferred-source purchase edits are protected. Existing transactions and stock layers used by B2B cannot be repurposed through normal document editing.

## Frontend

The existing layout, dark/orange theme, Bootstrap/jQuery/Select2 and shared exact-decimal client helpers are used. The workspace includes dashboard, products, customers, inventory, transfer history, POS, sales/invoices, payments/dues, movements and reports. Forms show available stock, confirm inventory actions, preserve retry keys and display server validation. A transfer accepts several products/quantities and no selling price. Every new cart line starts with a blank manual price.

Invoice printing uses the browser's print/Save PDF flow. The dedicated B2B interface leaves the existing retail POS in place.

## Schema and routes

Migration: `Modules/B2B/Database/Migrations/2026_10_26_000003_create_b2b_workspace.php`.

It adds `transactions.sales_channel` with retail as the existing-record default and creates enrollment, logical-location mapping, customer profile, transfer/layer mapping, sale snapshot and return metadata tables. Foreign key types match existing unsigned integer IDs. Rollback refuses when B2B business records exist. It never creates opening stock or sample transfers/sales.

| Route | Purpose |
| --- | --- |
| `GET /b2b`, `/products`, `/inventory`, `/transfers`, `/customers`, `/pos`, `/sales`, `/dues`, `/reports`, `/movements` under `/b2b` | Workspace pages |
| `GET /b2b/data/{resource}` | Protected, scoped products/customers/sales/transfers/movements/summary JSON |
| `PATCH /b2b/products/{id}` | Enable/disable B2B enrollment; no stock change |
| `POST /b2b/transfers`, `GET /b2b/transfers/{id}` | Post/read stock transfers |
| `POST /b2b/customers` | Create/enroll/update customer terms |
| `POST /b2b/sales` | Manually priced sale |
| `POST /b2b/sales/{id}/payments` | Collect invoice payments |
| `POST /b2b/sales/{id}/returns` | Item returns and required refunds |
| `GET /b2b/invoices/{id}` | HTML print view or JSON details |
| `GET /b2b/reports/export` | Scoped sales CSV |

POST commands require `Idempotency-Key`. Quantities and money are decimal strings, without thousands separators. Item quantities are in the product's inventory unit. Main warehouse IDs are used in B2B forms/API; internal stock location IDs are not user input.

## Verification and deployment

`tests/Feature/B2BWorkflowTest.php` extends the isolated manufacturing fixture. The manufacturing configuration includes both modules. MySQL mode creates only random disposable `codex_mfg_test_*` databases and includes concurrent stock/credit tests.

```powershell
php -d extension=pdo_sqlite vendor/phpunit/phpunit/phpunit -c phpunit.manufacturing.xml
$env:MFG_TEST_MYSQL = '1'
php -d extension=pdo_sqlite vendor/phpunit/phpunit/phpunit -c phpunit.manufacturing.xml
Remove-Item Env:\MFG_TEST_MYSQL
```

The bundled-schema rehearsal applies all three migrations to 85 core table definitions and verifies empty rollback. `tests/Support/b2b_deployment_check.php` backs up existing records privately, restores the backup into a disposable `codex_b2b_verify_*` database, checks old-row hashes around migration, and verifies a real main-stock transfer, manually priced sale, real-layout pages and the existing product list. Its verify mode is read-only against the application database. Sample transactions are confined to the disposable database, which is dropped afterward.

Browser smoke fixtures are generated from actual isolated controller responses with `B2B_EXPORT_FRONTEND_FIXTURE=1`. The local server/Chrome harness tests multi-step transfer retry, blank prices, exact manually entered price/payment payloads, successful sale feedback and mobile overflow. Those browser command responses are synthetic; actual stock and ledger behavior is verified separately by database tests.

Live installation completed on 2026-10-26 using only the B2B migration path. The read-only deployment verification passed: existing-record checksums are unchanged, no sample B2B stock/sales exist, and all ten B2B pages plus six JSON resources return HTTP 200. The disposable MariaDB suite passed 89 tests with 422 assertions (two browser-fixture tests skipped); focused post-install checks also passed. The manufacturing preflight reports `ready: true`. A private pre-install backup is recorded at `C:\Users\LAPTOP POINT\.codex\backups\inventory\before_b2b_20261026_200514.sql` with its JSON manifest beside it. `.env`, existing business records and unrelated uncommitted work are preserved.

## Scope boundaries

This version uses explicit manual final prices. Automatic tiers/customer price lists, MOQ/carton rules, direct B2B purchases/opening/adjustment commands, dedicated multi-report customer statements, and certification of every legacy company-wide report remain outside this clarified delivery. Dedicated B2B reports show current net values of invoices selected by invoice date; they are not an event-date cash ledger. Shared cash/account postings use the existing account integration when an account is selected.
