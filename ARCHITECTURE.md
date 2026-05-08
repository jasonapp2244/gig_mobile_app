# Gig Portal — Architecture Document

> **Framework:** Laravel 12.0 | **PHP:** ^8.2 | **Frontend:** Vite + Tailwind CSS + Blade  
> **Auth:** Laravel Sanctum (API) + Session (Admin) | **Notifications:** Firebase Cloud Messaging  
> **Database:** SQLite (default) / MySQL | **Queue:** Database driver

---

## Table of Contents

1. [Project Overview](#1-project-overview)
2. [Directory Structure](#2-directory-structure)
3. [Dependencies](#3-dependencies)
4. [Database Schema & Migrations](#4-database-schema--migrations)
5. [Eloquent Models & Relationships](#5-eloquent-models--relationships)
6. [Routing](#6-routing)
7. [Controllers](#7-controllers)
8. [Middleware](#8-middleware)
9. [Service Layer](#9-service-layer)
10. [Console Commands & Scheduling](#10-console-commands--scheduling)
11. [Mail System](#11-mail-system)
12. [Frontend & Views](#12-frontend--views)
13. [Authentication Architecture](#13-authentication-architecture)
14. [API Response Patterns](#14-api-response-patterns)
15. [Key Architectural Patterns](#15-key-architectural-patterns)
16. [Business Domains](#16-business-domains)
17. [Security](#17-security)
18. [Deployment](#18-deployment)

---

## 1. Project Overview

**Gig Portal** is a gig/job management platform with a mobile API backend and an admin panel. The application covers:

- **Task/Job Management** — Create, schedule, and track tasks with complex wage calculations (ST, OT, bonuses, travel pay)
- **Payment Tracking** — Per-task payment records with earning summaries
- **Employer Management** — Employer profiles linked to users and tasks
- **Marketplace (Buy/Sell)** — Product listings with images, reviews, and comments
- **Support System** — Help tickets with admin response workflow
- **Push Notifications** — Firebase Cloud Messaging with scheduled daily reminders
- **Admin Dashboard** — Analytics, user management, job monitoring

---

## 2. Directory Structure

```
app/
├── Console/Commands/
│   ├── SendDailyRemindersCommand.php     # reminders:daily-send
│   ├── UpdateTaskStatusCommand.php        # tasks:update-status
│   └── DeleteExpiredGuestsCommand.php     # guests:delete-expired
├── Http/
│   ├── Controllers/
│   │   ├── AuthController.php             # Signup, login, OTP, password reset, guest login
│   │   ├── TaskController.php             # Task CRUD, filtering
│   │   ├── TaskPaymentController.php      # Payments, earnings summary
│   │   ├── EmployerController.php         # Employer management
│   │   ├── ListController.php             # Product listings CRUD
│   │   ├── ListCategoryController.php     # Listing categories
│   │   ├── ListReviewController.php       # Reviews with ratings
│   │   ├── listCommitController.php       # Comments on listings
│   │   ├── SupportController.php          # Support email submission
│   │   ├── FirebaseNotificationController.php  # FCM notifications
│   │   ├── LanguageController.php         # Multi-language toggle
│   │   ├── AdminProfileController.php
│   │   └── Admin/
│   │       ├── AdminAuthController.php
│   │       ├── DashboardController.php
│   │       ├── AdminUserController.php
│   │       ├── AdminCategoryController.php
│   │       ├── AdminJobMonitoringController.php
│   │       ├── AdminListController.php
│   │       ├── AdminPaymentController.php
│   │       ├── AdminSettingController.php
│   │       └── AdminSupportController.php
│   ├── Middleware/
│   │   ├── AdminMiddleware.php            # Role-based admin check
│   │   ├── Authenticate.php
│   │   ├── SetLocale.php                  # Multi-language locale
│   │   ├── UpdateLastActivity.php         # Tracks last_activity_at
│   │   ├── UserActivity.php               # Caches online status (5 min)
│   │   └── SetAdminTimezone.php           # Dynamic admin timezone
│   └── Requests/
│       └── AuthRequest.php                # Route-based form validation
├── Models/
│   ├── User.php          # Soft deletes, roles, FCM, profile
│   ├── Task.php           # Jobs with wage calculations
│   ├── TaskPayment.php    # Payment records
│   ├── Employer.php       # Employer profiles
│   ├── ListStory.php      # Product/item listings
│   ├── ListCategory.php   # Listing categories
│   ├── ListImage.php      # Listing images
│   ├── ListCommit.php     # Listing comments
│   ├── ListReview.php     # Reviews with ratings
│   ├── Reminder.php       # Task reminders
│   ├── Role.php           # User roles
│   └── SupportEmail.php   # Support tickets
├── Mail/
│   ├── WelcomeMail.php
│   ├── forgotPasswordMail.php
│   ├── NewUserNotificationMail.php        # Queued
│   ├── SupportMail.php
│   └── SupportConfirmationMail.php
├── Services/
│   ├── FirebaseService.php                # FCM wrapper
│   └── ActivityService.php                # Dashboard analytics
└── Providers/
    └── RouteServiceProvider.php

config/
├── app.php          # Locales: en, ur
├── auth.php         # Guards: web, api (sanctum), admin
├── database.php     # SQLite / MySQL
├── firebase.php     # Firebase credentials & projects
├── sanctum.php      # API token config
├── queue.php        # Database queue driver
├── session.php      # Database session driver
└── translation-manager.php

routes/
├── web.php          # Admin panel (session auth)
├── api.php          # Mobile/Frontend API (Sanctum)
└── console.php      # Scheduled tasks

resources/
├── views/
│   ├── admin/       # 16 Blade templates (dashboard, users, jobs, lists, support, settings)
│   ├── layouts/     # admin.blade.php + partials (header, sidebar, footer, scripts)
│   └── mails/       # 5 email templates
└── js/
    ├── app.js       # Main entry point
    └── bootstrap.js # Vite + Axios setup
```

---

## 3. Dependencies

### PHP (composer.json)

| Package | Purpose |
|---------|---------|
| `laravel/framework` ^12.0 | Core framework |
| `laravel/sanctum` ^4.1 | API token authentication |
| `kreait/firebase-php` ^7.21 | Firebase Admin SDK |
| `kreait/laravel-firebase` ^6.1 | Laravel Firebase integration |
| `guzzlehttp/guzzle` ^7.9 | HTTP client |
| `barryvdh/laravel-translation-manager` ^0.6.8 | Translation management |
| `spatie/laravel-translation-loader` ^2.8 | DB-based translations |

### Frontend (package.json)

| Package | Purpose |
|---------|---------|
| `vite` ^6.2.4 | Build tool |
| `tailwindcss` ^4.0.0 | Utility-first CSS |
| `axios` ^1.8.2 | HTTP client |
| `concurrently` ^9.0.1 | Parallel dev scripts |
| `laravel-vite-plugin` ^1.2.0 | Laravel + Vite bridge |

---

## 4. Database Schema & Migrations

### Entity-Relationship Diagram

```
┌──────────┐       ┌──────────┐       ┌───────────────┐
│  roles   │◄──────│  users   │──────►│  employers    │
│          │  1:N  │ (soft    │  1:N  │               │
└──────────┘       │  delete) │       └───────┬───────┘
                   └────┬─────┘               │
                        │                     │
           ┌────────────┼─────────────┐       │
           │            │             │       │
           ▼            ▼             ▼       ▼
    ┌────────────┐ ┌──────────┐ ┌──────────────────┐
    │ list_      │ │ support_ │ │     tasks         │
    │ stories    │ │ emails   │ │                   │
    └──┬───┬───┬─┘ └──────────┘ └──┬────────────┬──┘
       │   │   │                    │            │
       ▼   ▼   ▼                    ▼            ▼
  ┌────┐ ┌────┐ ┌────────┐  ┌───────────┐ ┌──────────┐
  │img │ │cmts│ │reviews │  │task_      │ │reminders │
  └────┘ └────┘ └────────┘  │payments   │ └──────────┘
                             └───────────┘
```

### Migration Summary (21 migrations)

| Table | Key Columns |
|-------|-------------|
| `roles` | id, type, role_status |
| `users` | role_id, name, email, phone, otp, profile_image, status, fcm_token, is_guest, guest_expires_at, soft deletes |
| `tasks` | user_id, employer_id, job_title, job_type (night/day/off/hoot/vocation), job_category (hourly/monthly/yearly), pay, st_wages, ot_wages, st_hours, ot_hours, bonus_pay, travel_pay, start/end dates & times, status, is_reminder_sent |
| `employers` | user_id, employer_name, job_type (JSON), salary, location |
| `task_payments` | user_id, task_id, payment_title, payment, payment_status |
| `list_stories` | user_id, category_id, title, old_price, new_price, condition (new/used) |
| `list_categories` | user_id, category |
| `list_images` | list_id, image_name, path, hash_name |
| `list_commits` | user_id, list_id, commit (message text) |
| `list_reviews` | user_id, list_id, review, rating (1-5), is_anonymous |
| `reminders` | task_id, user_id, reminder_date_time, is_sent |
| `support_emails` | name, email, subject, message, status, response, responded_at |

---

## 5. Eloquent Models & Relationships

```
User
├── belongsTo(Role)
├── hasMany(Task)
├── hasMany(TaskPayment)
├── hasMany(ListStory)
├── hasMany(Employer)
└── Accessors: profile_image_url, cv_url

Task
├── belongsTo(User)
├── belongsTo(Employer)
├── hasMany(TaskPayment)
├── hasMany(Reminder)
└── Accessors: task_date_time, task_end_date_time (timezone-aware)

Employer
├── belongsTo(User)
└── hasMany(Task)

ListStory
├── belongsTo(User)
├── belongsTo(ListCategory)
├── hasMany(ListImage)
├── hasMany(ListCommit)
└── hasMany(ListReview)

ListReview
├── belongsTo(User)
└── belongsTo(ListStory)

TaskPayment
├── belongsTo(User)
└── belongsTo(Task)

Reminder
├── belongsTo(User)
└── belongsTo(Task)

SupportEmail — standalone (no relations)
```

---

## 6. Routing

### API Routes (`routes/api.php`) — Sanctum-protected

| Group | Prefix | Endpoints | Auth |
|-------|--------|-----------|------|
| **Auth** | `/auth` | signup, verify-otp, resend-otp, login, social-login, guest-login, forgot/reset-password | Public |
| **Profile** | `/` | user-profile, update-profile, update-user-status, fcm-token, delete-account | Sanctum |
| **Tasks** | `/tasks` | CRUD, filter by status/employer/date | Sanctum |
| **Payments** | `/` | get_tasks, task-payment CRUD, earning summary | Sanctum |
| **Employers** | `/` | get-employer, update, filter, delete | Sanctum |
| **Listings** | `/` | add/update/get/delete lists, search | Sanctum |
| **Categories** | `/` | get/delete list categories | Sanctum |
| **Comments** | `/` | add/get list commits | Sanctum |
| **Reviews** | `/` | add/get list reviews | Sanctum |
| **Support** | `/support` | send support email | Sanctum |
| **Notifications** | `/` | send-firebase, daily-reminders, reminder-status | Sanctum |

### Web Routes (`routes/web.php`) — Admin Panel

| Group | Prefix | Description | Middleware |
|-------|--------|-------------|------------|
| **Auth** | `/` | Admin login/logout | SetLocale |
| **Dashboard** | `/admin` | Dashboard, charts, activities | auth:admin, admin, setAdminTimezone |
| **Users** | `/admin` | User list, edit, view, status updates | auth:admin, admin |
| **Categories** | `/` | Category CRUD | auth:admin, admin |
| **Jobs** | `/` | Job monitoring, details | auth:admin, admin |
| **Lists** | `/admin` | List CRUD, toggle status, image delete | auth:admin, admin |
| **Support** | `/` | Tickets list, view, respond | auth:admin, admin |
| **Settings** | `/setting` | Profile view/edit, password change | auth:admin, admin |
| **Payments** | `/admin` | Payment tracking | auth:admin, admin |

---

## 7. Controllers

### API Controllers (11)

| Controller | Responsibility |
|------------|---------------|
| `AuthController` | Signup (with OTP), login, social login, guest login, password reset |
| `TaskController` | Task CRUD, filtering by status/employer/date |
| `TaskPaymentController` | Payment CRUD, earning summaries |
| `EmployerController` | Employer profiles, filtering |
| `ListController` | Product listing CRUD with images |
| `ListCategoryController` | Listing categories |
| `ListReviewController` | Reviews with star ratings (1-5), anonymous option |
| `listCommitController` | Comments on listings |
| `SupportController` | Support email submission |
| `FirebaseNotificationController` | FCM notifications, daily reminders |
| `LanguageController` | Locale switching (en/ur) |

### Admin Controllers (8)

| Controller | Responsibility |
|------------|---------------|
| `AdminAuthController` | Admin login/logout |
| `DashboardController` | Dashboard analytics via ActivityService |
| `AdminUserController` | User management, status updates |
| `AdminCategoryController` | Category CRUD |
| `AdminJobMonitoringController` | Job monitoring and tracking |
| `AdminListController` | Listing management |
| `AdminPaymentController` | Payment overview |
| `AdminSettingController` | Admin profile, password change |
| `AdminSupportController` | Support ticket response |

---

## 8. Middleware

| Middleware | Applied To | Purpose |
|------------|-----------|---------|
| `AdminMiddleware` | Admin web routes | Checks user has `admin` role |
| `SetLocale` | All web routes | Sets locale from session (en/ur) |
| `SetAdminTimezone` | Dashboard routes | Sets timezone per admin user |
| `UpdateLastActivity` | API routes (lastActivity) | Updates `last_activity_at` |
| `UserActivity` | API routes | Caches online status (5-min TTL) |
| `Sanctum` | Protected API routes | Token-based API auth |

---

## 9. Service Layer

### `FirebaseService`
- Initializes Firebase Admin SDK from JSON credentials (`storage/app/firebase/gig-ctd-app.json`)
- `sendNotificationToToken($token, $title, $body, $data)` — sends FCM push notification

### `ActivityService`
- `getDashboardData($limit)` — aggregates analytics:
  - User / Task / Employer / Payment counts
  - Support email statistics
  - Recent activities (union query from users, tasks, payments, lists, support)
  - 12-month user signup chart data

---

## 10. Console Commands & Scheduling

| Command | Schedule | Purpose |
|---------|----------|---------|
| `reminders:daily-send` | Daily 00:00 UTC | Sends FCM task reminders (timezone-aware per user), auto-expires overdue tasks |
| `tasks:update-status` | Every minute (dev) / 10 min (prod) | Auto-completes tasks past their end time |
| `guests:delete-expired` | Hourly | Deletes guest accounts past 24-hour expiry, revokes Sanctum tokens |

---

## 11. Mail System

| Mailable | Trigger | Queued? |
|----------|---------|---------|
| `WelcomeMail` | User signup | No |
| `forgotPasswordMail` | Password reset request | No |
| `NewUserNotificationMail` | New user registered (to admin) | **Yes** |
| `SupportMail` | Support ticket submitted (to support team) | No |
| `SupportConfirmationMail` | Support ticket submitted (to user) | No |

**Templates:** `resources/views/mails/` (welcome, forgot_password, new_user_notification, support, support_confirmation)

---

## 12. Frontend & Views

### Admin Panel
- **Layout:** `layouts/admin.blade.php` with partials (header, sidebar, footer, scripts)
- **CSS:** Bootstrap + custom admin styles + dark theme support
- **JS:** Admin-specific scripts in `public/admin/js/`
- **Pages:** 16 Blade templates covering dashboard, users, categories, jobs, lists, support, settings, payments

### Build Pipeline
- **Vite** bundles `resources/js/app.js` with Tailwind CSS
- **Axios** configured as HTTP client in `bootstrap.js`
- **Assets:** Versioned via `laravel-vite-plugin`

---

## 13. Authentication Architecture

```
┌─────────────────────────────────┐
│         Authentication          │
├────────────────┬────────────────┤
│   API Guard    │  Admin Guard   │
│  (Sanctum)     │  (Session)     │
├────────────────┼────────────────┤
│ Token-based    │ Session-based  │
│ Mobile/App     │ Web panel      │
├────────────────┼────────────────┤
│ Endpoints:     │ Endpoints:     │
│ - signup+OTP   │ - /login       │
│ - login        │ - /logout      │
│ - social login │                │
│ - guest login  │                │
│ - forgot/reset │                │
└────────────────┴────────────────┘
```

**OTP Flow:** Signup → 6-digit OTP emailed → 10-min expiry → verify → account active

**Guest Login:** Temporary 24-hour account → auto-deleted by scheduled command

**Social Login:** Google, Facebook, Apple via `service_provider` field

**Roles:** `user`, `admin`, `manager` — checked via `hasRole()` method

---

## 14. API Response Patterns

### Standard Response
```json
{
  "status": true,
  "message": "Human-readable message",
  "data": { ... }
}
```

### Error Response
```json
{
  "status": false,
  "message": "Error description",
  "error": "Technical details"
}
```

### Paginated Response
```json
{
  "status": true,
  "message": "...",
  "data": {
    "data": [...],
    "current_page": 1,
    "per_page": 10,
    "total": 100
  }
}
```

---

## 15. Key Architectural Patterns

| Pattern | Implementation |
|---------|---------------|
| **Multi-Guard Auth** | Sanctum (API) + Session (Admin), both using User model with role differentiation |
| **Timezone Handling** | Per-user timezone, UTC storage, Carbon accessors on Task model |
| **Activity Tracking** | Middleware-based (`UpdateLastActivity`, `UserActivity`) + dashboard aggregation |
| **Real-time Notifications** | Firebase Admin SDK → FCM tokens → scheduled reminders |
| **Multi-Language** | `SetLocale` middleware + `spatie/laravel-translation-loader` (en, ur) |
| **Soft Deletes** | User model (guests use `forceDelete`) |
| **Form Request Validation** | `AuthRequest` with route-based rule switching |
| **Queue** | Database driver for non-blocking emails (`NewUserNotificationMail`) |
| **Eager Loading** | `with()` in controllers to prevent N+1 queries |
| **Image Management** | Laravel Storage (public disk), multiple images per listing, hash naming |

---

## 16. Business Domains

```
┌─────────────────────────────────────────────────────┐
│                    GIG PORTAL                        │
├──────────────┬──────────────┬───────────────────────┤
│  Task/Job    │  Marketplace │  Admin & Support      │
│  Management  │  (Buy/Sell)  │                       │
├──────────────┼──────────────┼───────────────────────┤
│ • Task CRUD  │ • Listings   │ • Dashboard analytics │
│ • Scheduling │ • Categories │ • User management     │
│ • Wages (ST, │ • Images     │ • Job monitoring      │
│   OT, bonus, │ • Reviews    │ • Support tickets     │
│   travel)    │ • Comments   │ • Payment tracking    │
│ • Payments   │ • Conditions │ • Category management │
│ • Reminders  │   (new/used) │ • List management     │
│ • Employers  │              │ • Settings            │
└──────────────┴──────────────┴───────────────────────┘
```

---

## 17. Security

| Measure | Implementation |
|---------|---------------|
| **CSRF** | Laravel default (session middleware) |
| **API Auth** | Sanctum bearer tokens |
| **Password Hashing** | bcrypt, 12 rounds |
| **Email Verification** | OTP with 10-min expiry |
| **Role-Based Access** | `AdminMiddleware` + `hasRole()` |
| **Soft Deletes** | Data preservation for users |
| **Guest Expiry** | 24-hour auto-deletion |
| **FCM Validation** | Token format checking |
| **Foreign Keys** | Cascade deletes on related data |
| **Indexed Columns** | email, phone_number, service_provider_id |

---

## 18. Deployment

### Development
```bash
composer install
npm install
cp .env.example .env
php artisan key:generate
php artisan migrate
composer run dev    # Starts: server + queue + pail + vite
```

### Production
```bash
composer install --no-dev
npm install && npm run build
php artisan migrate --force
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

### Scheduled Tasks (Crontab)
```bash
* * * * * cd /path-to-project && php artisan schedule:run >> /dev/null 2>&1
```

### Testing
```bash
composer test    # Clears config + runs PHPUnit
```
