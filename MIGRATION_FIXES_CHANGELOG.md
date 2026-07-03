# Migration & Model Fixes Changelog

**Date:** 2026-07-03  
**Purpose:** Fix migration conflicts so `php artisan migrate:fresh` runs without errors and matches the live SQL schema.

---

## BLOCKER Fixes

### 1. Tasks migration runs before Employers (FK failure)

- **File:** `2025_07_15_164626_create_tasks_table.php`
- **Problem:** `tasks` migration had timestamp `164626`, but `employers` migration had `233537` (same date). Since `tasks` has a foreign key `employer_id` referencing `employers`, migrating fresh would fail because `employers` table doesn't exist yet.
- **Fix:** Renamed file to `2025_07_15_234000_create_tasks_table.php` so it runs AFTER `employers`.

### 2. `online_status` column — `.change()` on non-existent column

- **File:** `database/migrations/0001_01_01_000000_create_users_table.php` (line 73)
- **Problem:** `$table->string('online_status', 20)->default('offline')->change()` — the `.change()` modifier is used to alter an existing column, but this is inside a `Schema::create()` block. The column doesn't exist yet, so this would crash on fresh migration.
- **Fix:** Removed `.change()` → `$table->string('online_status', 20)->default('offline')`

### 3. Duplicate `ot_wages` column across two migrations

- **File 1:** `database/migrations/2025_11_20_173300_add_column_to_tasks_table.php` (line 17)
- **File 2:** `database/migrations/2025_11_26_171322_add_columns_to_tasks_table.php` (line 22)
- **Problem:** `ot_wages` was defined in both migrations. Running them sequentially would fail with "duplicate column" error. Also, the first migration used `decimal()` (defaults to 8,2) while the second used `decimal(10,2)`.
- **Fix:**
  - Removed `ot_wages` from the second migration (Nov 26 file)
  - Updated first migration to use `decimal('ot_wages', 10, 2)` to match SQL schema
  - Removed `ot_wages` from the `down()` method of the second migration

---

## Column Name Mismatch Fixes

### 4. `last_logout_time` vs `last_logout_at`

- **File:** `database/migrations/0001_01_01_000000_create_users_table.php` (line 72)
- **Problem:** Migration created column as `last_logout_time`, but the `User.php` model references `last_logout_at` in both `$fillable` and `$casts`. All reads/writes to this field would silently fail.
- **Fix:** Changed migration column name from `last_logout_time` to `last_logout_at` to match the model.

---

## Missing Column Fixes

### 5. `timezone` column missing from migrations

- **File:** `database/migrations/0001_01_01_000000_create_users_table.php`
- **Problem:** The `timezone` column (`varchar(100) DEFAULT 'America/New_York'`) exists in the live SQL database and is referenced in the User model `$fillable`, but no migration created it.
- **Fix:** Added `$table->string('timezone', 100)->default('America/New_York')` to the users migration.

### 6. `reminder_sent_at` column missing from migrations

- **File:** `database/migrations/0001_01_01_000000_create_users_table.php`
- **Problem:** The `reminder_sent_at` column (`datetime`) exists in the live SQL but no migration created it.
- **Fix:** Added `$table->timestamp('reminder_sent_at')->nullable()` to the users migration.

### 7. `device_type` and `device_token` missing from User model `$fillable`

- **File:** `app/Models/User.php`
- **Problem:** Both columns exist in the migration and SQL but were not in the model's `$fillable` array, meaning they could not be mass-assigned.
- **Fix:** Added `device_type` and `device_token` to `$fillable`.

---

## Type/Size Mismatch Fixes

### 8. `service_provider_id` — varchar(255) vs varchar(500)

- **File:** `database/migrations/0001_01_01_000000_create_users_table.php` (line 31)
- **Problem:** Migration used `string('service_provider_id')` which defaults to `varchar(255)`, but SQL schema has `varchar(500)`. Long OAuth tokens could be truncated.
- **Fix:** Changed to `string('service_provider_id', 500)`

### 9. `fcm_token` — varchar(255) vs varchar(200)

- **File:** `database/migrations/0001_01_01_000000_create_users_table.php`
- **Problem:** Migration defaulted to `varchar(255)`, SQL has `varchar(200)`.
- **Fix:** Changed to `string('fcm_token', 200)`

### 10. `device_type` — varchar(255) vs varchar(200) + wrong default

- **File:** `database/migrations/0001_01_01_000000_create_users_table.php`
- **Problem:** Migration used `varchar(255)` with `->default('null')` (literal string "null"). SQL has `varchar(200)` with `DEFAULT NULL` (actual null).
- **Fix:** Changed to `string('device_type', 200)->nullable()`

### 11. `device_token` — varchar(255) vs varchar(100)

- **File:** `database/migrations/0001_01_01_000000_create_users_table.php`
- **Problem:** Migration defaulted to `varchar(255)`, SQL has `varchar(100)`.
- **Fix:** Changed to `string('device_token', 100)`

### 12. `ot_wages` precision — decimal(8,2) vs decimal(10,2)

- **File:** `database/migrations/2025_11_20_173300_add_column_to_tasks_table.php`
- **Problem:** Used `decimal('ot_wages')` which defaults to `decimal(8,2)`, SQL has `decimal(10,2)`.
- **Fix:** Changed to `decimal('ot_wages', 10, 2)`

---

## Known Minor Issues (Not Fixed — No Runtime Impact)

### `rate` column type vs model cast

- **File:** `app/Models/Task.php` (line 71)
- **Detail:** Column is `varchar(255)` in both migration and SQL, but the Task model casts it as `decimal:2`. Laravel will auto-convert on read, which works if values are always numeric strings. No migration change needed, but be aware if non-numeric values are stored.

### Column ordering differences

- **Detail:** Some column positions differ between migration order and SQL dump (e.g., `task_end_date_time` position in `tasks` table). This is cosmetic and has no functional impact.

---

## Files Changed

| File | Change |
|---|---|
| `database/migrations/0001_01_01_000000_create_users_table.php` | Fixed 8 issues (columns, types, sizes) |
| `database/migrations/2025_11_20_173300_add_column_to_tasks_table.php` | Fixed `ot_wages` precision |
| `database/migrations/2025_11_26_171322_add_columns_to_tasks_table.php` | Removed duplicate `ot_wages` |
| `database/migrations/2025_07_15_164626_create_tasks_table.php` | Renamed to `2025_07_15_234000_create_tasks_table.php` |
| `app/Models/User.php` | Added `device_type`, `device_token` to `$fillable` |

---

## Verification

After these fixes, running `php artisan migrate:fresh` should:
- Create all tables in correct order (roles -> users -> employers -> tasks -> ...)
- Create all columns matching the live SQL schema
- Have no duplicate column errors
- Have all model `$fillable` and `$casts` fields matching migration column names
