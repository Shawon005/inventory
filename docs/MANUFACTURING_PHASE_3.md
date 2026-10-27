# Manufacturing frontend: Phase 3

Phase 3 adds the manufacturing workspace and connects manufacturing settings and detail tabs to existing products. The backend is described in [Phase 2](MANUFACTURING_PHASE_2.md). The operational walkthrough is in the [user guide](MANUFACTURING_USER_GUIDE.md).

## Delivered

- Overview, raw materials, components/finished-product filtering, versioned BOMs, production orders, batch history, stock movements and production cost reports.
- BOM input/output search, unit families, waste allowances, revision publication and recipe archive/activation.
- Production planning, pinned requirements, component child-order planning, actual input issues, accepted/defective receipts, actual expenses, material returns, scrap and reversals.
- Existing product create/edit classification, procurement policy, optional BOM-template copying and production notes. Product details include warehouse stock, production, component usage and purchases.
- Existing layout and dark/orange styling, responsive tables, filtered/paginated lists, loading/error feedback, permitted CSV export and duplicate-submission protection.
- Existing JSON endpoints remain available through content negotiation; browser requests render the new screens. Backend business, warehouse and action permissions remain authoritative.

Primary implementation files are `ManufacturingPageController`, `ManufacturingReadService`, `ProductFormService`, `ProductionOrdersExport`, the Manufacturing views and routes, and the assets under `public/modules/manufacturing`. Core integration is in `ProductController`, `AdminSidebarMenu`, and existing product views. No new frontend framework was introduced.

## Installation and existing data

The earlier unreadable-database condition cleared. A private SQL backup was taken under `%USERPROFILE%\.codex\backups\inventory\before_manufacturing_20261025_170844.sql`, with its checksum manifest alongside it. That backup was restored into a disposable database and both Phase 2 migrations were rehearsed successfully before applying only those two migrations to the application database. Phase 3 needs no additional migration.

After installation, the stock checksum, 206 existing product records and 411 existing transactions were unchanged. No demonstration production, manufacturing stock movements or business records were posted. `.env` was unchanged. Existing unrelated uncommitted work was preserved.

The read-only installation preflight now returns `ready: true`. The separate commercial `manufacturing_version` marker was not introduced; sidebar and product integration check the installed schema.

## Verification

| Check | Result |
| --- | --- |
| Manufacturing tests on SQLite | 66 tests, 249 assertions; 2 InnoDB-only tests and 1 opt-in browser-fixture test skipped |
| Manufacturing tests on MariaDB/InnoDB | 66 tests, 262 assertions; only the opt-in browser-fixture test skipped |
| Existing selected regressions | 44 tests, 295 assertions passed |
| Opt-in actual-controller browser-fixture export | 1 test, 15 assertions passed |
| Browser fixture smoke | Desktop BOM and production forms, mobile overflow, decimal payloads, retry keys and error feedback passed |
| Actual application read-only checks | Eight manufacturing pages, existing product create/edit, existing product list and manufacturing JSON endpoints returned HTTP 200 |
| Decimal client calculations and JS syntax | Passed |

Browser interaction checks used synthetic fixtures on a local server, not production stock postings. The actual application checks exercised GET requests through the application's real layout/middleware with the existing Admin identity in CLI memory. They do not certify every legacy retail workflow.

Production expense entries calculate manufacturing inventory cost; they do not automatically post cash or payroll. Planning does not reserve stock. The later B2B allocation/manual-price delivery is documented in [B2B_IMPLEMENTATION.md](B2B_IMPLEMENTATION.md).
