# CDP Inventory API

> **System Name:** Stockly

> **Confidential** — This repository and its contents are proprietary to CDP Empire (Pvt) Ltd. Unauthorized access, copying, or distribution is strictly prohibited.

## Overview

CDP Inventory API is the backend REST API powering the **Stockly** inventory management system by CDP Empire (Pvt) Ltd. It handles the complete lifecycle of product inventory, procurement, stock movements, supplier management, branch-level stock control, damage/expiry tracking, and reporting for CDP Empire (Pvt) Ltd — a Sri Lankan business operations company.

## Tech Stack

| Layer | Technology |
|-------|-----------|
| Framework | Laravel 12 (PHP 8.2+) |
| Database | MySQL 8.0 |
| Authentication | JWT (php-open-source-saver/jwt-auth) |
| Authorization | Spatie Laravel Permission (Roles & Permissions) |
| Queue | Database driver |
| Email | Laravel Mail |
| Social Login | Google OAuth (Laravel Socialite) |
| Testing | Pest PHP |
| Build Tool | Vite 7 |

## Prerequisites

- PHP 8.2 or higher
- MySQL 8.0
- Composer
- Node.js & NPM
- Laragon (recommended for local development on Windows)

## Installation

### 1. Clone the repository

```bash
git clone <repo-url>
cd cdp-inventory-api
```

### 2. Install PHP dependencies

```bash
composer install
```

### 3. Install frontend dependencies

```bash
npm install
```

### 4. Configure environment

```bash
cp .env.example .env
php artisan key:generate
php artisan jwt:secret
```

Configure `.env` with your database credentials, mail settings, and other environment-specific values.

### 5. Run database migrations & seeders

```bash
php artisan migrate --seed
```

### 6. Start the development server

```bash
composer dev
```

This runs the API server, queue worker, log viewer, and Vite dev server concurrently.

## Project Structure

```
app/
├── Console/Commands/        # Custom artisan commands
├── Events/                  # Event classes
├── Exceptions/              # Custom exception classes
├── Http/
│   ├── Controllers/
│   │   └── V1/              # API v1 controllers (43 controllers)
│   ├── Middleware/           # Custom middleware
│   └── Requests/            # Form request validators (88 requests)
├── Jobs/                    # Queued jobs
├── Mail/                    # Mailable classes (9 mailables)
├── Models/                  # Eloquent models (38 models)
├── Notifications/           # Notification classes
├── Providers/               # Service providers
├── Services/                # Business logic services (6 services)
├── Traits/                  # Reusable traits
└── Utilities/               # Helper utilities
config/                      # Configuration files
database/
├── migrations/              # 44 migration files
├── seeders/                 # Database seeders
└── factories/               # Model factories
routes/                      # Route definitions (4 files)
tests/                       # Unit & Feature tests (Pest)
```

## Architecture

### Inventory Management Flow

```
Product Created → Purchase Order → GRN (Goods Received Note)
                                         ↓
                                    Stock Ledger Entry
                                         ↓
                              ┌───────────┴───────────┐
                              ↓                       ↓
                      Stock Transfer            Stock Assignment
                    (Branch-to-Branch)         (Branch Assignment)
                              ↓                       ↓
                         Stock Take              Product Return
                    (Inventory Counting)
                              ↓
               ┌──────────────┼──────────────┐
               ↓              ↓              ↓
         Reorder Level   Damage Record   Expiry Record
        (Low Stock Alert)  (Damaged)      (Expired)
```

### Access Control

The system uses role-based access control with Spatie Permissions:

- **Super Admin** — Full system access with bypass capabilities
- **Admin** — Branch-level or system-wide management
- **Branch User** — Branch-scoped operations (check-in, check-out, stock take)

### Module Categories

| Category | Modules |
|----------|---------|
| **Product Management** | Products, Product Variants, Brands, Main Categories, Sub Categories, Units, Measurement Units, Containers |
| **Procurement** | Suppliers, Supplier Bank Accounts, Supplier Products, Purchase Orders, Purchase Order Items, GRNs, GRN Items |
| **Stock Control** | Stock Ledger, Stock Transfers, Stock Transfer Items, Stock Takes, Stock Take Items, Reorder Levels |
| **Branch Operations** | Branches, Check-Ins, Check-Outs, Product Assignments, Product Returns, Branch Requests |
| **Financial** | Payments, Money Ledger (read-only) |
| **Quality Control** | Damage Records, Expiry Records |
| **User Management** | Users, Roles, Permissions, Reporting Managers |
| **System** | Activity Logs, Notifications, Bulk Import, Database Management |

## API Documentation

### Authentication

All protected endpoints require a valid JWT token via the `Authorization: Bearer {token}` header or HTTP-only cookie.

### Base URL

```
/api/v1
```

### Response Format

```json
{
    "status": true,
    "message": "Success message",
    "data": { ... }
}
```

### Public Endpoints

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/` | API root info |
| GET | `/health` | Health check with DB status |
| GET | `/health-check` | Simple health check |
| GET | `/up` | Laravel framework health |
| POST | `/api/v1/login` | User login (JWT, throttled: 10/min) |

### Protected Endpoints

All protected endpoints require JWT authentication and are rate-limited to 300 requests/minute.

#### Auth & Profile

| Method | Endpoint | Description |
|--------|----------|-------------|
| POST | `/api/v1/logout` | Logout (invalidate token) |
| GET | `/api/v1/me` | Current user info |
| PUT | `/api/v1/profile` | Update profile (30/min) |
| PUT | `/api/v1/profile/password` | Change password (10/min) |

#### Users

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET/POST | `/api/v1/users` | List / Create users |
| GET/PUT/DELETE | `/api/v1/users/{id}` | Show / Update / Delete user |
| GET | `/api/v1/users/list` | User list (dropdown) |
| PATCH | `/api/v1/users/{id}/toggle-status` | Toggle active status |

#### Permissions & Roles

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET/POST | `/api/v1/permissions` | List / Create permissions |
| GET/PUT/DELETE | `/api/v1/permissions/{id}` | Show / Update / Delete permission |
| GET | `/api/v1/permissions/list` | Permission list (dropdown) |
| PATCH | `/api/v1/permissions/{id}/activate` | Activate permission |
| GET/POST | `/api/v1/roles` | List / Create roles |
| GET/PUT/DELETE | `/api/v1/roles/{id}` | Show / Update / Delete role |
| GET | `/api/v1/roles/list/` | Available roles list |
| PATCH | `/api/v1/roles/{id}/activate` | Activate role |
| PATCH | `/api/v1/roles/{id}/deactivate` | Deactivate role |

#### Branches

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET/POST | `/api/v1/branches` | List / Create branches |
| GET/PUT/DELETE | `/api/v1/branches/{id}` | Show / Update / Delete branch |
| PATCH | `/api/v1/branches/{id}/toggle-status` | Toggle status |
| PATCH | `/api/v1/branches/{id}/activate` | Activate branch |
| PATCH | `/api/v1/branches/{id}/deactivate` | Deactivate branch |

#### Products

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET/POST | `/api/v1/products` | List / Create products |
| GET/PUT/DELETE | `/api/v1/products/{id}` | Show / Update / Delete product |
| GET | `/api/v1/products/{id}/details` | Product lookup details |
| PATCH | `/api/v1/products/{id}/toggle-status` | Toggle status |
| PATCH | `/api/v1/products/{id}/activate` | Activate product |
| PATCH | `/api/v1/products/{id}/deactivate` | Deactivate product |

#### Product Variants

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET/POST | `/api/v1/product-variants` | List / Create variants |
| GET/PUT/DELETE | `/api/v1/product-variants/{id}` | Show / Update / Delete variant |
| PATCH | `/api/v1/product-variants/{id}/toggle-status` | Toggle status |
| PATCH | `/api/v1/product-variants/{id}/activate` | Activate variant |
| PATCH | `/api/v1/product-variants/{id}/deactivate` | Deactivate variant |

#### Suppliers

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET/POST | `/api/v1/suppliers` | List / Create suppliers |
| GET/PUT/DELETE | `/api/v1/suppliers/{id}` | Show / Update / Delete supplier |
| PATCH | `/api/v1/suppliers/{id}/toggle-status` | Toggle status |
| PATCH | `/api/v1/suppliers/{id}/activate` | Activate supplier |
| PATCH | `/api/v1/suppliers/{id}/deactivate` | Deactivate supplier |

#### Supplier Bank Accounts

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET/POST | `/api/v1/supplier-bank-accounts` | List / Create bank accounts |
| GET/PUT/DELETE | `/api/v1/supplier-bank-accounts/{id}` | Show / Update / Delete |
| PATCH | `/api/v1/supplier-bank-accounts/{id}/toggle-status` | Toggle status |
| PATCH | `/api/v1/supplier-bank-accounts/{id}/activate` | Activate |
| PATCH | `/api/v1/supplier-bank-accounts/{id}/deactivate` | Deactivate |

#### Supplier Products

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET/POST | `/api/v1/supplier-products` | List / Create supplier-product links |
| GET/PUT/DELETE | `/api/v1/supplier-products/{id}` | Show / Update / Delete |
| PATCH | `/api/v1/supplier-products/{id}/activate` | Activate |
| PATCH | `/api/v1/supplier-products/{id}/deactivate` | Deactivate |

#### Categories & Brands

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET/POST | `/api/v1/main-categories` | List / Create main categories |
| GET/PUT/DELETE | `/api/v1/main-categories/{id}` | Show / Update / Delete |
| PATCH | `/api/v1/main-categories/{id}/toggle-status` | Toggle status |
| PATCH | `/api/v1/main-categories/{id}/activate` | Activate |
| PATCH | `/api/v1/main-categories/{id}/deactivate` | Deactivate |
| GET/POST | `/api/v1/sub-categories` | List / Create sub categories |
| GET/PUT/DELETE | `/api/v1/sub-categories/{id}` | Show / Update / Delete |
| PATCH | `/api/v1/sub-categories/{id}/toggle-status` | Toggle status |
| PATCH | `/api/v1/sub-categories/{id}/activate` | Activate |
| PATCH | `/api/v1/sub-categories/{id}/deactivate` | Deactivate |
| GET/POST | `/api/v1/brands` | List / Create brands |
| GET/PUT/DELETE | `/api/v1/brands/{id}` | Show / Update / Delete |
| PATCH | `/api/v1/brands/{id}/toggle-status` | Toggle status |
| PATCH | `/api/v1/brands/{id}/activate` | Activate |
| PATCH | `/api/v1/brands/{id}/deactivate` | Deactivate |

#### Units & Measurement

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET/POST | `/api/v1/units` | List / Create units |
| GET/PUT/DELETE | `/api/v1/units/{id}` | Show / Update / Delete unit |
| GET/POST | `/api/v1/measurement-units` | List / Create measurement units |
| GET/PUT/DELETE | `/api/v1/measurement-units/{id}` | Show / Update / Delete |
| PATCH | `/api/v1/measurement-units/{id}/toggle-status` | Toggle status |
| PATCH | `/api/v1/measurement-units/{id}/activate` | Activate |
| PATCH | `/api/v1/measurement-units/{id}/deactivate` | Deactivate |

#### Containers

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET/POST | `/api/v1/containers` | List / Create containers |
| GET/PUT/DELETE | `/api/v1/containers/{id}` | Show / Update / Delete |
| PATCH | `/api/v1/containers/{id}/toggle-status` | Toggle status |
| PATCH | `/api/v1/containers/{id}/activate` | Activate |
| PATCH | `/api/v1/containers/{id}/deactivate` | Deactivate |

#### Purchase Orders

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET/POST | `/api/v1/purchase-orders` | List / Create purchase orders |
| GET/PUT/DELETE | `/api/v1/purchase-orders/{id}` | Show / Update / Delete |
| PATCH | `/api/v1/purchase-orders/{id}/activate` | Activate |
| PATCH | `/api/v1/purchase-orders/{id}/deactivate` | Deactivate |
| GET/POST | `/api/v1/purchase-order-items` | List / Create PO items |
| GET/PUT/DELETE | `/api/v1/purchase-order-items/{id}` | Show / Update / Delete |
| PATCH | `/api/v1/purchase-order-items/{id}/activate` | Activate |
| PATCH | `/api/v1/purchase-order-items/{id}/deactivate` | Deactivate |

#### GRNs (Goods Received Notes)

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET/POST | `/api/v1/grns` | List / Create GRNs |
| GET/PUT/DELETE | `/api/v1/grns/{id}` | Show / Update / Delete GRN |
| GET/POST | `/api/v1/grn-items` | List / Create GRN items |
| GET/PUT/DELETE | `/api/v1/grn-items/{id}` | Show / Update / Delete |
| GET | `/api/v1/grn-items/next-serial` | Get next serial number |
| PATCH | `/api/v1/grn-items/{id}/activate` | Activate |
| PATCH | `/api/v1/grn-items/{id}/deactivate` | Deactivate |

#### Purchase Return Notes

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET/POST | `/api/v1/purchase-return-notes` | List / Create PRNs |
| GET/PUT/DELETE | `/api/v1/purchase-return-notes/{id}` | Show / Update / Delete |
| PATCH | `/api/v1/purchase-return-notes/{id}/activate` | Activate |
| PATCH | `/api/v1/purchase-return-notes/{id}/deactivate` | Deactivate |
| GET/POST | `/api/v1/purchase-return-note-items` | List / Create PRN items |
| GET/PUT/DELETE | `/api/v1/purchase-return-note-items/{id}` | Show / Update / Delete |
| PATCH | `/api/v1/purchase-return-note-items/{id}/activate` | Activate |
| PATCH | `/api/v1/purchase-return-note-items/{id}/deactivate` | Deactivate |

#### Stock Ledger

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET/POST | `/api/v1/stock-ledgers` | List / Create stock entries |
| GET/PUT/DELETE | `/api/v1/stock-ledgers/{id}` | Show / Update / Delete |
| GET | `/api/v1/stock-ledgers/balance` | Get stock balance |
| GET | `/api/v1/stock-ledgers/branch-stock` | Branch-wise stock |
| PATCH | `/api/v1/stock-ledgers/{id}/activate` | Activate |
| PATCH | `/api/v1/stock-ledgers/{id}/deactivate` | Deactivate |

#### Money Ledger

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/api/v1/money-ledger` | Read-only financial aggregation |

#### Stock Transfers

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET/POST | `/api/v1/stock-transfers` | List / Create transfers |
| GET/PUT/DELETE | `/api/v1/stock-transfers/{id}` | Show / Update / Delete |
| GET/POST | `/api/v1/stock-transfer-items` | List / Create transfer items |
| GET/PUT/DELETE | `/api/v1/stock-transfer-items/{id}` | Show / Update / Delete |

#### Stock Takes

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET/POST | `/api/v1/stock-takes` | List / Create stock takes |
| GET/PUT/DELETE | `/api/v1/stock-takes/{id}` | Show / Update / Delete |
| PATCH | `/api/v1/stock-takes/{id}/toggle-status` | Toggle status |
| PATCH | `/api/v1/stock-takes/{id}/activate` | Activate |
| PATCH | `/api/v1/stock-takes/{id}/deactivate` | Deactivate |
| GET/POST | `/api/v1/stock-take-items` | List / Create stock take items |
| GET/PUT/DELETE | `/api/v1/stock-take-items/{id}` | Show / Update / Delete |
| PATCH | `/api/v1/stock-take-items/{id}/toggle-status` | Toggle status |
| PATCH | `/api/v1/stock-take-items/{id}/activate` | Activate |
| PATCH | `/api/v1/stock-take-items/{id}/deactivate` | Deactivate |

#### Check-In / Check-Out

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET/POST | `/api/v1/check-ins` | List / Create check-ins |
| GET/PUT/DELETE | `/api/v1/check-ins/{id}` | Show / Update / Delete |
| PATCH | `/api/v1/check-ins/{id}/toggle-status` | Toggle status |
| PATCH | `/api/v1/check-ins/{id}/activate` | Activate |
| PATCH | `/api/v1/check-ins/{id}/deactivate` | Deactivate |
| GET/POST | `/api/v1/check-outs` | List / Create check-outs |
| GET/PUT/DELETE | `/api/v1/check-outs/{id}` | Show / Update / Delete |
| PATCH | `/api/v1/check-outs/{id}/toggle-status` | Toggle status |
| PATCH | `/api/v1/check-outs/{id}/activate` | Activate |
| PATCH | `/api/v1/check-outs/{id}/deactivate` | Deactivate |

#### Product Assignments & Returns

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET/POST | `/api/v1/product-assignments` | List / Create assignments |
| GET/PUT/DELETE | `/api/v1/product-assignments/{id}` | Show / Update / Delete |
| PATCH | `/api/v1/product-assignments/{id}/toggle-status` | Toggle status |
| PATCH | `/api/v1/product-assignments/{id}/activate` | Activate |
| PATCH | `/api/v1/product-assignments/{id}/deactivate` | Deactivate |
| GET/POST | `/api/v1/product-returns` | List / Create returns |
| GET/PUT/DELETE | `/api/v1/product-returns/{id}` | Show / Update / Delete |

#### Branch Requests

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET/POST | `/api/v1/branch-requests` | List / Create branch requests |
| GET/PUT/DELETE | `/api/v1/branch-requests/{id}` | Show / Update / Delete |
| PATCH | `/api/v1/branch-requests/{id}/toggle-status` | Toggle status |
| PATCH | `/api/v1/branch-requests/{id}/activate` | Activate |
| PATCH | `/api/v1/branch-requests/{id}/deactivate` | Deactivate |

#### Damage & Expiry Records

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET/POST | `/api/v1/damage-records` | List / Create damage records |
| GET/PUT/DELETE | `/api/v1/damage-records/{id}` | Show / Update / Delete |
| PATCH | `/api/v1/damage-records/{id}/activate` | Activate |
| PATCH | `/api/v1/damage-records/{id}/deactivate` | Deactivate |
| GET/POST | `/api/v1/expiry-records` | List / Create expiry records |
| GET/PUT/DELETE | `/api/v1/expiry-records/{id}` | Show / Update / Delete |
| PATCH | `/api/v1/expiry-records/{id}/activate` | Activate |
| PATCH | `/api/v1/expiry-records/{id}/deactivate` | Deactivate |

#### Reorder Levels

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET/POST | `/api/v1/reorder-levels` | List / Create reorder levels |
| GET/PUT/DELETE | `/api/v1/reorder-levels/{id}` | Show / Update / Delete |
| PATCH | `/api/v1/reorder-levels/{id}/toggle-status` | Toggle status |
| PATCH | `/api/v1/reorder-levels/{id}/activate` | Activate |
| PATCH | `/api/v1/reorder-levels/{id}/deactivate` | Deactivate |

#### Payments

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET/POST | `/api/v1/payments` | List / Create payments |
| GET/PUT/DELETE | `/api/v1/payments/{id}` | Show / Update / Delete |

#### Reporting Managers

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET/POST | `/api/v1/reporting-managers` | List / Create reporting managers |
| GET/PUT/DELETE | `/api/v1/reporting-managers/{id}` | Show / Update / Delete |
| PATCH | `/api/v1/reporting-managers/{id}/toggle-status` | Toggle status |
| PATCH | `/api/v1/reporting-managers/{id}/activate` | Activate |
| PATCH | `/api/v1/reporting-managers/{id}/deactivate` | Deactivate |

#### Inventory Dashboard

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET/POST | `/api/v1/inventory-dashboards` | List / Create dashboards |
| GET/PUT/DELETE | `/api/v1/inventory-dashboards/{id}` | Show / Update / Delete |
| GET | `/api/v1/inventory-dashboards/stats` | Dashboard statistics |
| PATCH | `/api/v1/inventory-dashboards/{id}/toggle-status` | Toggle status |
| PATCH | `/api/v1/inventory-dashboards/{id}/activate` | Activate |
| PATCH | `/api/v1/inventory-dashboards/{id}/deactivate` | Deactivate |

#### Notifications

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/api/v1/notifications` | List notifications |
| GET | `/api/v1/notifications/unread` | Unread count |
| PATCH | `/api/v1/notifications/{id}/read` | Mark as read |
| POST | `/api/v1/notifications/mark-all-read` | Mark all as read |

#### Activity Logs

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/api/v1/activity-logs` | List activity logs |
| GET | `/api/v1/activity-logs/{id}` | Show activity log detail |

#### Bulk Import

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/api/v1/bulk-import/tables` | List importable tables |
| GET | `/api/v1/bulk-import/{table}/template` | Download CSV template |
| POST | `/api/v1/bulk-import/{table}` | Upload CSV (20/min) |

#### Database Management

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/api/v1/database/overview` | Database overview |
| GET | `/api/v1/database/backup` | Download backup (5/min) |
| POST | `/api/v1/database/clear-cache` | Clear cache (5/min) |

## Key Features

- **Complete Inventory Lifecycle** — Product creation through procurement, receiving, stock management, and returns
- **Multi-Branch Stock Control** — Branch-level stock tracking with inter-branch transfers
- **Role-Based Access Control** — Granular permissions with Spatie Laravel Permission
- **Automated Stock Ledger** — Running balance tracking per product per branch with reorder alerts
- **Bulk Data Management** — CSV import/export for 24+ entity types
- **Activity Logging** — Complete audit trail for all user actions
- **Email Notifications** — Automated alerts for PO creation, GRN received, stock transfers, expiry warnings, and more
- **Damage & Expiry Tracking** — Quality control with automated alerts
- **Inventory Dashboard** — Real-time statistics and reporting
- **Database Backup** — One-click backup and cache management

## Default Credentials

After running seeders, the default admin account is:

| Field | Value |
|-------|-------|
| Email | admin@cdpempire.com |
| Password | password |

> **Note:** Change these credentials immediately in production.

## Custom Artisan Commands

| Command | Description |
|---------|-------------|
| `php artisan permission:toggle {role} {permission?} --grant --revoke --list` | Grant/revoke/list permissions on roles |
| `php artisan stock:recalculate-balances {--product=} {--branch=} --dry-run` | Recalculate stock ledger running balances |

## Composer Scripts

| Script | Description |
|--------|-------------|
| `composer dev` | Start all dev services (server, queue, logs, vite) |
| `composer test` | Clear config and run tests |
| `composer setup` | Full setup: .env, key, migrate:fresh --seed, optimize:clear |
| `composer fresh` | migrate:fresh --seed |
| `composer migrate:seed` | Run migrations then seed |
| `composer seed` | Run database seeders |

## Testing

```bash
# Run all tests
composer test

# Run with coverage
php artisan test --coverage
```

## Deployment Notes

- Ensure `APP_ENV=production` and `APP_DEBUG=false` in `.env`
- Set a strong `APP_KEY` and `JWT_SECRET`
- Configure proper mail credentials for notifications
- Set up queue workers for background jobs: `php artisan queue:work`
- Schedule cron: `php artisan schedule:run`
- Symlink storage: `php artisan storage:link`

## Security

- JWT tokens are stored in HTTP-only secure cookies
- Token TTL: 60 minutes (configurable via `JWT_TTL`)
- Refresh TTL: ~14 days (20160 minutes)
- All sensitive operations are logged via `ActivityLogTrait`
- Soft deletes enabled on core models
- File upload security with extension whitelisting
- Rate limiting on login (10/min), bulk import (20/min), and database backup (5/min)

## License

The Laravel framework is open-sourced software licensed under the [MIT license](https://opensource.org/licenses/MIT).
