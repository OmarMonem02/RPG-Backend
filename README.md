# RPG Backend

Laravel API for the Real Performance Garage ERP system. The backend manages inventory, sales, maintenance tickets, customer records, reporting, users, permissions, image uploads, import/export workflows, and public customer ticket tracking.

## Tech Stack

- PHP 8.2
- Laravel 12
- Laravel Sanctum for API authentication
- MySQL-compatible database
- Laravel queue jobs for asynchronous work
- Maatwebsite Excel for spreadsheet import/export
- Cloudinary Laravel for image uploads
- Meta WhatsApp Cloud API for customer tracking messages

## Core Features

- Token-based login, logout, and current-user APIs
- Role and permission protected ERP endpoints
- Inventory management for bikes, products, spare parts, brands, categories, and compatibility blueprints
- Sales workflows with line items, returns, exchanges, adjustments, and exports
- Maintenance tickets with tasks, parts, close/reopen flow, and customer-facing tracking links
- Customer workspace endpoints for linked bikes, sales, and tickets
- Reporting APIs for profit/loss, balance sheet, annual summary, and expenses
- Import/export templates, parsing, validation, and professional spreadsheet exports
- Audit history for operational changes

## Requirements

- PHP 8.2 or newer
- Composer
- Node.js and npm
- MySQL or another database supported by Laravel

## Getting Started

Install PHP dependencies:

```bash
composer install
```

Install frontend build tooling used by Laravel/Vite assets:

```bash
npm install
```

Create and configure the environment file:

```bash
cp .env.example .env
php artisan key:generate
```

Update `.env` with the local database connection, then run migrations:

```bash
php artisan migrate
```

Seed default roles and users when needed:

```bash
php artisan db:seed
```

Start the API server:

```bash
php artisan serve
```

By default, the frontend expects the API at `http://127.0.0.1:8000/api`.

## Development Commands

```bash
composer run dev
```

Runs the Laravel server, queue listener, log tailing, and Vite development process together.

```bash
composer test
```

Clears cached configuration and runs the Laravel test suite.

```bash
php artisan queue:listen --tries=1 --timeout=0
```

Runs queued jobs locally, including WhatsApp tracking message dispatch.

```bash
php artisan whatsapp:list-templates
```

Lists available WhatsApp templates for the configured Meta Business account.

## Environment Variables

Important project-specific variables:

| Variable | Purpose |
| --- | --- |
| `FRONTEND_PUBLIC_URL` | Public URL of the Next.js frontend, used when generating customer tracking links. |
| `SHOP_NAME` | Brand name displayed in customer tracking experiences. |
| `SHOP_TAGLINE` | Supporting brand text for the tracking page. |
| `SHOP_LOGO_URL` | Logo path or absolute URL used by the frontend. |
| `SHOP_TRACKING_AUTO_REFRESH_MINUTES` | Customer tracking refresh interval; use `0` for manual refresh only. |
| `WHATSAPP_PHONE_NUMBER_ID` | Meta WhatsApp phone number ID. |
| `WHATSAPP_ACCESS_TOKEN` | Meta system user token with `whatsapp_business_messaging`. |
| `WHATSAPP_API_VERSION` | Meta Graph API version, for example `v21.0`. |
| `WHATSAPP_TRACKING_TEMPLATE_NAME` | Approved WhatsApp utility template name. |
| `WHATSAPP_TEMPLATE_LANGUAGE` | Template language code exactly as configured in Meta. |
| `WHATSAPP_BUSINESS_ACCOUNT_ID` | WhatsApp Business Account ID for template lookup. |

## Customer Ticket Tracking

Staff can send customers a WhatsApp message with a secure link to `/track/{token}` on the frontend. Customers verify their phone number before viewing ticket progress, tasks, parts, services, and totals.

Recommended Meta utility template body:

```text
Hello {{1}}, your maintenance ticket #{{2}} is ready. Track progress here: {{3}}
```

Template parameters:

1. Customer name
2. Zero-padded ticket number
3. Full tracking URL

Relevant endpoints:

| Endpoint | Auth | Purpose |
| --- | --- | --- |
| `POST /api/tickets/{id}/send-tracking-link` | Staff with `maintenance:update` | Send WhatsApp message and ensure a tracking token exists. |
| `POST /api/tickets/{id}/regenerate-tracking-token` | Staff with `maintenance:update` | Invalidate old public tracking links. |
| `GET /api/public/tickets/{token}/meta` | Public | Return ticket preview metadata without pricing. |
| `POST /api/public/tickets/{token}/verify` | Public | Verify phone number and issue a tracking session. |
| `GET /api/public/tickets/{token}` | Public with `X-Tracking-Session` | Return the full customer tracking payload. |

## API Structure

Routes are defined in `routes/api.php`. Public endpoints include login and ticket tracking. ERP endpoints are protected by Sanctum and permission middleware, with admin-only routes grouped separately.

Controllers live under `app/Http/Controllers/Api`, request validation under `app/Http/Requests`, and business logic under `app/Services`, `app/Support`, and `app/Actions`.

## Testing

Run the full backend suite:

```bash
composer test
```

Feature tests cover permissions, settings, reporting, sales, tickets, import/export, spare-part compatibility, customer workspace behavior, and public ticket tracking.

## Related Project

The matching Next.js client is located at `../rpg_frontend`.
