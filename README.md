<p align="center"><a href="https://laravel.com" target="_blank"><img src="https://raw.githubusercontent.com/laravel/art/master/logo-lockup/5%20SVG/2%20CMYK/1%20Full%20Color/laravel-logolockup-cmyk-red.svg" width="400" alt="Laravel Logo"></a></p>

<p align="center">
<a href="https://github.com/laravel/framework/actions"><img src="https://github.com/laravel/framework/workflows/tests/badge.svg" alt="Build Status"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/dt/laravel/framework" alt="Total Downloads"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/v/laravel/framework" alt="Latest Stable Version"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/l/laravel/framework" alt="License"></a>
</p>

## About Laravel

Laravel is a web application framework with expressive, elegant syntax. We believe development must be an enjoyable and creative experience to be truly fulfilling. Laravel takes the pain out of development by easing common tasks used in many web projects, such as:

- [Simple, fast routing engine](https://laravel.com/docs/routing).
- [Powerful dependency injection container](https://laravel.com/docs/container).
- Multiple back-ends for [session](https://laravel.com/docs/session) and [cache](https://laravel.com/docs/cache) storage.
- Expressive, intuitive [database ORM](https://laravel.com/docs/eloquent).
- Database agnostic [schema migrations](https://laravel.com/docs/migrations).
- [Robust background job processing](https://laravel.com/docs/queues).
- [Real-time event broadcasting](https://laravel.com/docs/broadcasting).

Laravel is accessible, powerful, and provides tools required for large, robust applications.

## Learning Laravel

Laravel has the most extensive and thorough [documentation](https://laravel.com/docs) and video tutorial library of all modern web application frameworks, making it a breeze to get started with the framework. You can also check out [Laravel Learn](https://laravel.com/learn), where you will be guided through building a modern Laravel application.

If you don't feel like reading, [Laracasts](https://laracasts.com) can help. Laracasts contains thousands of video tutorials on a range of topics including Laravel, modern PHP, unit testing, and JavaScript. Boost your skills by digging into our comprehensive video library.

## Laravel Sponsors

We would like to extend our thanks to the following sponsors for funding Laravel development. If you are interested in becoming a sponsor, please visit the [Laravel Partners program](https://partners.laravel.com).

### Premium Partners

- **[Vehikl](https://vehikl.com)**
- **[Tighten Co.](https://tighten.co)**
- **[Kirschbaum Development Group](https://kirschbaumdevelopment.com)**
- **[64 Robots](https://64robots.com)**
- **[Curotec](https://www.curotec.com/services/technologies/laravel)**
- **[DevSquad](https://devsquad.com/hire-laravel-developers)**
- **[Redberry](https://redberry.international/laravel-development)**
- **[Active Logic](https://activelogic.com)**

## Contributing

Thank you for considering contributing to the Laravel framework! The contribution guide can be found in the [Laravel documentation](https://laravel.com/docs/contributions).

## Code of Conduct

In order to ensure that the Laravel community is welcoming to all, please review and abide by the [Code of Conduct](https://laravel.com/docs/contributions#code-of-conduct).

## Security Vulnerabilities

If you discover a security vulnerability within Laravel, please send an e-mail to Taylor Otwell via [taylor@laravel.com](mailto:taylor@laravel.com). All security vulnerabilities will be promptly addressed.

## Customer maintenance ticket tracking (WhatsApp)

Staff can send customers a WhatsApp message with a link to `/track/{token}` on the Next.js app. Customers verify their phone number, then view ticket progress, parts, services, and total.

### Environment

Copy from `.env.example`:

- `FRONTEND_PUBLIC_URL` — base URL of the Next.js app (e.g. `https://rpg-erp-system.vercel.app`)
- `WHATSAPP_PHONE_NUMBER_ID` — from Meta Business Manager → WhatsApp → API setup
- `WHATSAPP_ACCESS_TOKEN` — permanent system user token with `whatsapp_business_messaging`
- `WHATSAPP_TRACKING_TEMPLATE_NAME` — approved template name (default: `maintenance_ticket_tracking`)
- `WHATSAPP_TEMPLATE_LANGUAGE` — template language code (default: `en`)

### Meta message template

Create and approve a **utility** template in Meta Business Manager, for example:

> Hello {{1}}, your maintenance ticket #{{2}} is ready. Track progress here: {{3}}

Body parameters: customer name, zero-padded ticket number, full tracking URL.

### API

| Endpoint | Auth | Purpose |
|----------|------|---------|
| `POST /api/tickets/{id}/send-tracking-link` | Staff (`maintenance,update`) | Send WhatsApp + ensure token |
| `POST /api/tickets/{id}/regenerate-tracking-token` | Staff | Invalidate old links |
| `GET /api/public/tickets/{token}/meta` | Public | Preview (no prices) |
| `POST /api/public/tickets/{token}/verify` | Public | Phone verification → session |
| `GET /api/public/tickets/{token}` | Public + `X-Tracking-Session` | Full tracking payload |

Run migrations: `php artisan migrate`

## License

The Laravel framework is open-sourced software licensed under the [MIT license](https://opensource.org/licenses/MIT).
