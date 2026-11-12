# Module Breakdown — Distribution, Inventory & Brand Shop Management System

> Source: **Project Meeting Minutes — 28th Oct 2026** (prepared by Endow Tech)
> This document enumerates every required module one by one, with features, data model,
> screens, permissions, and dependencies, so each module can be implemented and tracked
> independently.

---

## Project Snapshot

| Item | Value |
|---|---|
| Business | Cox's Bazaar Mobile Center (mobile phone distribution & brand shops) |
| System | Distribution, Inventory & Brand Shop Management System |
| Stack | Laravel 13 (PHP 8.4), Blade + Tailwind CSS 4 + Alpine.js, Vite |
| Packages | `spatie/laravel-permission` (RBAC), `maatwebsite/excel` (import/export), Pest (tests) |
| Repo state | Foundation only — auth, RBAC seeders, settings, audit log, layout components |

### What already exists (foundation)

| Area | Status |
|---|---|
| Auth (login, forgot/reset password) | Done |
| User model (status, branch, soft deletes, roles) | Done |
| Roles & permissions seeders (Super Admin, Manager, Accountant, Sales Staff, Storekeeper) | Done |
| Settings system (company, numbering prefixes, currency, thresholds) | Done |
| Audit log model + `AuditService` | Done |
| `NumberingService` (sequential document numbers) | Done |
| Layout + Blade components (app/guest layouts, status badge, empty state, pagination, confirm-delete) | Done |
| Domain modules (products, inventory, sales, accounts, etc.) | **Not started** — `routes/web.php` has only the welcome route |

---

## Module Index

| # | Module | Key functions (per minutes) | Status |
|---|---|---|---|
| 01 | Dashboard & Reports | Business overview, stock, sales, dues | Not started |
| 02 | User Management & RBAC | Users, roles, permissions, access control | Partial (seeders done) |
| 03 | Master Data & Settings | Products, brands, models, locations, customers | Partial (settings done) |
| 04 | Inventory Management | Stock-in/out, transfers, adjustments | Not started |
| 05 | Distribution & SR/DSR | Placement, quantities, destination tracking | Not started |
| 06 | Retailer Management | Customer records, transaction history | Not started |
| 07 | Accounts & Cash/Bank Management | Accounts, deposits, withdrawals, balances, reports | Not started |
| 08 | Brand Shop Management | Supplied, sold, returned, remaining stock | Not started |
| 09 | Sales & Invoicing | Invoice generation, printing, payment status | Not started |
| 10 | Payments & Due Tracking | Partial payments, receipts, outstanding balances | Not started |
| 11 | Returns & Adjustments | Returns, damaged goods, stock correction | Not started |
| 12 | Notifications & Audit Trail | Alerts, user activity history | Partial (audit log done) |
| 13 | Backup & Data Import | Backup, recovery, existing data import | Not started |

---

## Module 01 — Dashboard & Reports

**Goal:** One-screen business overview plus drill-down reports.

### Features
- Business overview cards: today's sales, purchases, collections, expenses, net cash.
- Stock summary: total products, stock value, low-stock / out-of-stock counts.
- Sales summary: invoices today / this month, top products, top retailers.
- Dues summary: total receivable (retailers), total payable (suppliers), overdue dues.
- Charts: sales vs collection trend (date-wise), brand-wise sales share.
- Quick links: pending invoices, pending payments, low-stock list, recent activity.
- Reports (exportable to Excel/PDF via `maatwebsite/excel`):
  - Stock report (product-wise, location-wise)
  - Sales report (date-wise, invoice-wise, product-wise, retailer-wise, SR-wise)
  - Due report (retailer-wise outstanding, ageing)
  - Purchase report (supplier-wise)
  - Cash/bank book report (from Module 07)
  - Profit summary (sales − cost − expenses)
  - Brand shop stock report (from Module 08)

### Core data model
- Mostly read models/aggregates over Modules 03–11 tables; no new tables required.
- Optional `report_snapshots` table for cached daily summaries.

### Screens
- `/dashboard` — main dashboard (role-aware widgets).
- `/reports/*` — report index, parameter filters (date range, brand, SR, retailer), export buttons.

### Permissions
- `dashboard.view`, `reports.view`, `reports.export` (already seeded).

### Dependencies
- Aggregates data from Modules 03, 04, 07, 08, 09, 10. **Build this last** (or incrementally).

---

## Module 02 — User Management & RBAC

**Goal:** Manage users, roles, permissions, and access control.

### Features
- User CRUD: name, email, phone, password, branch/location, status (active/inactive), role assignment.
- Deactivate/activate users (soft delete preserved).
- Role CRUD: create roles, assign permissions per module/action.
- Permission matrix UI (role × permission grid, grouped by module).
- Access control: policy/gate enforcement on every route and controller.
- User activity history link (from Module 12 audit trail).

### Core data model
- `users` (exists), `branches` (exists), spatie `roles` / `permissions` / `model_has_*` (exists).
- Roles seeded: Super Admin, Manager, Accountant, Sales Staff, Storekeeper.

### Screens
- `/users` (list), `/users/create`, `/users/{id}/edit`.
- `/roles` (list), `/roles/{id}/permissions` (matrix editor).

### Permissions
- `users.view/create/update/deactivate`, `roles.view/manage` (already seeded).

### Dependencies
- None — implement early (needed to gate every other module).

---

## Module 03 — Master Data & Settings

**Goal:** All reference data: products, brands, models, locations, customers.

### Features
- **Brands**: CRUD (Samsung, Xiaomi, Oppo, ...).
- **Product models / categories / units**: CRUD (phone model, accessory category, pcs/box).
- **Products**: CRUD with SKU/code, brand, model, category, unit, cost/sale price,
  warranty, barcode, opening stock, low-stock threshold, image, status.
- **Locations**: warehouses/branches/brand shops (source/destination for transfers).
- **Customers (retailers)**: also owned by Module 06 — master record lives here.
- **Settings UI**: edit company info, numbering prefixes, currency, low-stock threshold,
  `allow_negative_stock` (values already seeded in `settings` table).

### Core data model
- `brands`, `categories`, `units`, `products`, `locations` (new).
- `settings` (exists) + settings edit screens.

### Screens
- `/brands`, `/categories`, `/units`, `/products`, `/locations`, `/settings`.

### Permissions
- `brands.manage`, `categories.manage`, `units.manage`, `products.manage/view`,
  `warehouses.manage`, `settings.manage` (already seeded).

### Dependencies
- None — implement right after Module 02 (products are needed by 04, 05, 08, 09, 11).

---

## Module 04 — Inventory Management

**Goal:** All stock movements: stock-in, stock-out, transfers, adjustments.

### Features
- Stock-in (purchase receipt / opening / manual in) and stock-out (sale / manual out).
- Stock transfers between locations (warehouse ↔ brand shop ↔ SR placement).
- Stock adjustments: damage, loss, found, correction (with reason and approval note).
- Real-time stock ledger: every movement recorded (product × location × qty × ref doc).
- Current stock view per product per location; valuation (qty × cost).
- Low-stock and out-of-stock alerts (threshold from settings).
- Guard: block negative stock unless `allow_negative_stock` is enabled.

### Core data model
- `stock_ledger` (append-only movement log: product_id, location_id, type, qty, unit_cost, ref_type, ref_id, user_id, movement_date).
- `stock_transfers` + `stock_transfer_items` (from_location, to_location, status: draft/in-transit/received/cancelled).
- `stock_adjustments` + `stock_adjustment_items` (type: damage/loss/found/correction, reason).
- Derived `current_stock` view/materialized query (product × location).

### Screens
- `/inventory` (stock overview), `/inventory/ledger`, `/stock-transfers`, `/stock-adjustments`.

### Permissions
- `inventory.view`, `stock-adjustments.manage`, `stock-transfers.manage` (already seeded).

### Dependencies
- Module 03 (products, locations). Consumed by Modules 05, 08, 09, 11.

---

## Module 05 — Distribution & SR/DSR

**Goal:** Track product placement via Sales Representatives (SR) / District Sales Representatives (DSR).

### Features
- SR/DSR profile management (link to user or dedicated rep records with territory/beat).
- Placement entries: products + quantities placed with a retailer/destination, per SR.
- Destination tracking: which retailer/location received what, when, via which SR.
- SR stock: goods handed to SR (in-transit) vs placed vs returned vs sold.
- SR-wise reports: placement summary, quantity placed/sold/returned per period.
- Delivery/challan printout per placement.

### Core data model
- `sales_reps` (code, name, phone, territory, user_id nullable, status).
- `placements` + `placement_items` (sr_id, retailer_id/customer_id, location_id, placement_date, status, totals).
- Reuses `stock_ledger` (Module 04) for movement effects (SR acts as a location or a placement ref).

### Screens
- `/sales-reps`, `/placements` (create/list/detail), `/reports/sr` (SR-wise placement report).

### Permissions
- New: `placements.manage`, `placements.view`, `sales-reps.manage` (add to seeder).

### Dependencies
- Modules 03, 04, 06 (retailers as destination). Feeds dues in Module 10.

---

## Module 06 — Retailer Management

**Goal:** Customer (retailer) records and full transaction history.

### Features
- Retailer CRUD: shop name, owner, phone, address, area/beat, credit limit, opening balance, status.
- Transaction history timeline: invoices, payments/collections, returns, placements — per retailer.
- Ledger view: running balance (debit/credit) per retailer.
- Statement printout (date range).
- Search/filter by area, name, due status.

### Core data model
- `customers` (retailers) — type flag if distinguishing walk-in vs retailer.
- `customer_ledger` (append-only: customer_id, type, ref_type, ref_id, debit, credit, balance_after, date).

### Screens
- `/customers`, `/customers/create`, `/customers/{id}` (profile + ledger + history tabs).

### Permissions
- `customers.manage`, `customers.view` (already seeded).

### Dependencies
- Module 03 (master data). Feeds Modules 05, 09, 10.

---

## Module 07 — Accounts & Cash/Bank Management

**Goal:** Full account management — the most detailed module in the minutes.

### Features
- **Account creation & management**: cash accounts, bank accounts (name, type, number, branch, opening balance, status).
- **Deposit entries**: money in (deposit type, account, amount, date, method, reference, note).
- **Withdrawal / expense entries**: money out (withdrawal or expense type, category, payee, amount).
- **Account-wise transaction history**: filterable ledger per account (date range, type, status).
- **Daily and date-wise summaries**: per-day totals of deposits, withdrawals, closing balance.
- **Balances**: opening balance, total deposits, total withdrawals, current/closing balance
  (per account and overall).
- **Transaction status workflow**: `Pending`, `Completed`, `Cancelled` (only Completed affects balance).
- **Payment references**: link transactions to invoices/payments (INV-/PAY-/COL- references).
- **Account-wise reports**: account book, daily cash summary, expense category report; exportable.

### Core data model
- `accounts` (name, type: cash/bank, account_number, branch, opening_balance, current_balance, status).
- `account_transactions` (account_id, type: deposit/withdrawal/expense, amount, status: pending/completed/cancelled, category, reference_no, ref_type/ref_id, transaction_date, note, user_id).
- Balance recompute strategy: keep `current_balance` denormalized + ledger rows for history.

### Screens
- `/accounts`, `/accounts/{id}` (ledger + summary cards), `/account-transactions` (create deposit/withdraw/expense),
  `/reports/accounts` (account-wise + daily summaries).

### Permissions
- `cash-accounts.manage`, `bank-accounts.manage`, `income-transactions.manage`,
  `expense-transactions.manage`, `account-transfers.manage`, `finance.manage/view` (already seeded).
- New: `account-transactions.view` if finer granularity is needed.

### Dependencies
- Modules 09, 10 (invoice payments/collections post into accounts). Core of the money flow.

---

## Module 08 — Brand Shop Management

**Goal:** Dedicated stock accounting per brand shop (company-operated outlets).

### Features
- Brand shop setup (as a `locations` entry with type `brand_shop`).
- **Supplied**: stock sent from warehouse to brand shop (ties to Module 04 transfers).
- **Sold**: POS/retail sales at the shop (ties to Module 09 sales invoices).
- **Returned**: shop-to-warehouse returns (ties to Module 11 returns).
- **Remaining stock**: live per-shop stock = supplied − sold − returned ± adjustments.
- Per-shop reports: supplied/sold/returned/remaining by product and date range.
- Shop-wise sales and cash summary.

### Core data model
- No new tables strictly required — views over `stock_ledger` + `sales_invoices` + `sales_returns`
  filtered to `locations.type = brand_shop`. Optional `brand_shops` metadata table (manager, opening hours).

### Screens
- `/brand-shops` (list + per-shop dashboard), `/reports/brand-shops` (supplied/sold/returned/remaining).

### Permissions
- Reuse `inventory.view`, `sales-invoices.manage/view`; new `brand-shops.manage`.

### Dependencies
- Modules 04 (transfers), 09 (sales), 11 (returns).

---

## Module 09 — Sales & Invoicing

**Goal:** Invoice generation, printing, payment status.

### Features
- Sales invoice create/edit: retailer/customer, items (product, qty, rate, discount, VAT/tax), totals.
- Invoice number auto-generated via `NumberingService` (`INV-YYYYMM-######`).
- Invoice printing: printable A4/CUSTOM receipt format with company branding (settings).
- Payment status per invoice: `Due`, `Partial`, `Paid`, `Cancelled` (derived from payments).
- POS-style quick sale entry (permission `pos.access` already seeded).
- Post/void workflow: draft → posted (posts stock-out + ledger) → voided (reverses).
- Invoice list with filters (date, customer, status, SR) and export.

### Core data model
- `sales_invoices` (invoice_no, customer_id, sr_id nullable, invoice_date, subtotal, discount, tax, total, paid, due, status, location_id, user_id).
- `sales_invoice_items` (product_id, qty, rate, discount, total, stock effect ref).

### Screens
- `/sales` (list), `/sales/create`, `/sales/{id}` (detail + print + record payment), `/pos` (quick sale).

### Permissions
- `sales-orders.manage`, `sales-invoices.manage/post/view`, `pos.access` (already seeded).

### Dependencies
- Modules 03, 04 (stock-out on post), 06 (customers). Payments go to Module 10.

---

## Module 10 — Payments & Due Tracking

**Goal:** Collect against invoices; track outstanding balances.

### Features
- **Partial payments**: record payment amount < invoice total; invoice moves to `Partial`.
- **Receipts**: printable payment receipt per collection (numbering `COL-YYYYMM-######`).
- **Outstanding balances**: per invoice, per customer, overall receivable total.
- Due ageing: current, 1–30 days, 31–60 days, 60+ overdue.
- Payment allocation: a payment can settle one invoice (or be on-account advance).
- Link payments to cash/bank accounts (Module 07 deposit entries).
- Supplier side (purchase dues): mirror features for supplier payments (`PAY-` prefix).

### Core data model
- `payments` (payment_no, customer_id nullable, supplier_id nullable, account_id, amount, method, payment_date, status, reference_no, note).
- `payment_allocations` (payment_id, invoice_type, invoice_id, amount) — supports partial + multi-invoice.
- Due fields on invoices: `paid`, `due` (denormalized, updated transactionally).

### Screens
- `/payments` (list), `/payments/create` (select customer → open invoices → allocate),
  `/customers/{id}/dues`, `/reports/dues` (ageing report).

### Permissions
- `customer-collections.manage`, `supplier-payments.manage/view` (already seeded).

### Dependencies
- Modules 06, 07 (accounts), 09 (invoices).

---

## Module 11 — Returns & Adjustments

**Goal:** Handle returns, damaged goods, and stock corrections.

### Features
- **Sales returns**: return from retailer (link to original invoice optional), qty + reason + condition.
- **Purchase returns**: return to supplier (link to purchase invoice).
- **Damaged goods**: mark returned/received items as damaged → moves to damaged stock or write-off.
- **Stock correction**: reuses Module 04 stock adjustments for corrections with reason + approval.
- Return effect on dues: reduces invoice due / issues credit note; effect on stock: stock-in on return.
- Return reports: date-wise, product-wise, customer/supplier-wise.

### Core data model
- `sales_returns` + `sales_return_items` (return_no `SR-`, customer_id, invoice_id nullable, condition: good/damaged, restock flag).
- `purchase_returns` + `purchase_return_items` (return_no `PR-`).
- All posts to `stock_ledger` (Module 04) and adjusts dues (Module 10).

### Screens
- `/sales-returns`, `/purchase-returns` (list/create/detail), `/reports/returns`.

### Permissions
- `sales-returns.manage`, `purchase-returns.manage` (already seeded).

### Dependencies
- Modules 04, 09, 10.

---

## Module 12 — Notifications & Audit Trail

**Goal:** Alerts for important events + immutable user activity history.

### Features
- **Alerts**: low stock, overdue dues, pending payments, cancelled documents, stock transfer pending receipt.
- In-app notification center (bell + list); optional email notifications (Laravel Notifications).
- **Audit trail**: who did what, when (already have `AuditLog` + `AuditService`).
- Audit UI: filter by user, event, model type, date; old/new value diff viewer.
- Wire `AuditService` calls into every write controller (created/updated/deleted/posted/voided).

### Core data model
- `audit_logs` (exists), `notifications` (Laravel default table — add migration), optional `alerts` config in `settings`.

### Screens
- `/notifications` (center), `/audit-logs` (list + detail diff).

### Permissions
- `audit-logs.view` (already seeded). Notifications readable by all users (own) / admin (all).

### Dependencies
- Cross-cutting — add hooks in every module's controllers.

---

## Module 13 — Backup & Data Import

**Goal:** Backup/recovery and import of existing business data.

### Features
- **Backup**: scheduled DB + storage backups (spatie/laravel-backup or artisan command to dump),
  download backup file from UI, backup history list.
- **Recovery**: restore from uploaded backup file (admin-only, with confirmation).
- **Existing data import**: Excel import (`maatwebsite/excel` already required) for:
  - Products + opening stock
  - Customers/retailers (with opening balances)
  - Suppliers
  - Opening account balances
- Import validation preview (dry-run) before committing; error report download.
- Export any list to Excel (cross-module convenience).

### Core data model
- `backups` (file_name, disk, size, created_by, status) and `import_batches` (type, file, status, summary JSON).

### Screens
- `/backups` (list + download + restore), `/import` (wizard: choose type → upload → map → preview → commit).

### Permissions
- New: `backups.manage`, `imports.manage` (Super Admin only initially).

### Dependencies
- Modules 03, 06, 07 (import targets). Can be built any time after those exist.

---

## Suggested Implementation Order

| Phase | Modules | Rationale |
|---|---|---|
| 1 | 02 User Management & RBAC | Access control gates everything else |
| 2 | 03 Master Data & Settings | Products/locations/customers are prerequisites |
| 3 | 04 Inventory Management | Stock ledger is the backbone |
| 4 | 09 Sales & Invoicing + 06 Retailer Management | Core daily operations |
| 5 | 10 Payments & Due Tracking + 07 Accounts & Cash/Bank | Money flow + balances |
| 6 | 11 Returns & Adjustments, 05 Distribution & SR/DSR, 08 Brand Shop | Operational depth |
| 7 | 12 Notifications & Audit Trail, 01 Dashboard & Reports | Cross-cutting + reporting |
| 8 | 13 Backup & Data Import | Hardening & migration |

Each phase should end with: migration + model + factory + seeder + controller/routes + Blade UI +
Pest feature tests + Pint formatting.

---

## Notes & Assumptions

- Currency: BDT (৳) — set in `settings`. Low-stock threshold defaults to 5.
- Document numbering prefixes are pre-seeded (`INV`, `SR`, `COL`, `PAY`, `ADJ`, `TRF`, `EXP`, ...).
- "SR/DSR" = Sales Representative / District Sales Representative (field sales force).
- "Brand Shop" = company-operated brand outlet (e.g., phone brand showroom).
- Roles seeded so far (Super Admin, Manager, Accountant, Sales Staff, Storekeeper) map well to
  the modules; new permissions listed above must be appended to `RolesAndPermissionsSeeder`.
- This document tracks *what* to build; per-module technical design (exact schema columns, routes)
  should be finalized at the start of each module's implementation.
