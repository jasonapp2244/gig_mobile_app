# Task Feature Changelog

**Project:** Gig Task Management API  
**Last Updated:** 2026-07-03

---

## Previous Task Features (Existing)

### Task CRUD

| Endpoint | Method | Description |
|----------|--------|-------------|
| `/task/store` | POST | Create a new task |
| `/task/edit/{id}` | GET | Get task details for editing |
| `/task/update/{id}` | PUT | Update task (OT, employer, notes) |
| `/task/delete/{id}` | DELETE | Delete a task |
| `/task/show/{id}` | GET | Show single task |

### Task Listing & Filtering

| Endpoint | Method | Description |
|----------|--------|-------------|
| `/tasks` | GET | List all user tasks (latest first) |
| `/tasks/filter-by-status` | POST | Filter by status + employer summary |
| `/tasks/filter-by-employer` | POST | Filter by employer_id |
| `/tasks/by-date` | POST | Filter tasks by specific date |
| `/task/mark-completed/{id}` | PUT | Manually mark task complete |

### Task Fields

- **Employer:** employer name, employer_id (from employers table)
- **Time:** task_date_time (start), task_end_date_time (end) — stored in UTC
- **Standard Time (ST):** st_wages (rate), st_hours, st_total
- **Overtime (OT):** ot_start_time, ot_end_time, ot_hours, ot_wages, ot_total
- **Computed:** working_hours (st + ot), pay (grand total)
- **Status:** pending, ongoing, completed, cancelled, incomplete
- **Misc:** location, supervisor, position, notes, make_hole

### Reminder System

- **Cron:** `reminders:daily-send` — runs daily at midnight
- **Logic:** Sends Firebase push notification for each task on its scheduled day
- **Tracking:** `is_reminder_sent` (boolean) + `reminder_sent_at` (datetime) per task
- **Timezone-aware:** Converts task UTC time to user's local timezone for day comparison
- **Auto-expire:** Marks past tasks as reminder-sent if their date already passed

### Auto Status Update

- **Cron:** `tasks:update-status` — runs every minute
- **Logic:** Marks `incomplete` tasks as `complete` when `task_end_date_time <= now(UTC)`

### Task Payments

- **Table:** `task_payments`
- **Logic:** Auto-created when task has `st_total_hours`, linked via `task_id`
- **Updated on:** Task update (recalculates grand total)

---

## New Feature: Multiple Tasks at Same Time (2026-07-03)

### Requirement

Client requires users to add one or more tasks at the same hour/time slot — for example, a user working multiple jobs simultaneously with different employers.

### Previous Behavior

- Any time overlap was **completely blocked** regardless of employer
- Error: `"You already have a task during this time. Please choose another time."`

### New Behavior

| Scenario | Result |
|----------|--------|
| Same time + **same employer** | BLOCKED — `"You already have a task for this employer during this time."` |
| Same time + **different employer** | ALLOWED |
| Same time + **no employer** (both null) | ALLOWED |
| Same time + one has employer, one doesn't | ALLOWED |

### What Changed

**File:** `app/Http/Controllers/TaskController.php` → `store()` method

1. Moved employer resolution (`$employerId`) before the overlap check
2. Changed overlap check from "any task at same time" to "same employer at same time"
3. Wrapped check in `if ($employerId)` — tasks without employer skip the check entirely

### What Did NOT Change

| Component | Reason |
|-----------|--------|
| Database schema | Each task is already an independent row — no change needed |
| Reminder system | Already loops each task individually and sends separate notifications |
| `is_reminder_sent` flag | Per-task, works independently |
| `reminders` table | Keyed by `task_id`, supports multiple per user per day |
| Status auto-update cron | Bulk updates all expired tasks regardless of overlap |
| Task payments | Keyed by unique `task_id`, not by time |
| Listing/Filter APIs | Returns all tasks, no time-uniqueness assumption |
| Show/Edit/Delete | Works by `task_id`, not by time |

### How Reminders Work with Multiple Same-Time Tasks

```
User has 3 tasks on 2026-07-04:
  Task #10: Company A, 9:00 AM - 5:00 PM
  Task #11: Company B, 9:00 AM - 1:00 PM
  Task #12: (no employer), 9:00 AM - 12:00 PM

Cron runs at midnight (user's timezone):
  → Sends notification for Task #10: "Employer: Company A, Time: 09:00 AM"
  → Sends notification for Task #11: "Employer: Company B, Time: 09:00 AM"
  → Sends notification for Task #12: "Task scheduled for today, Time: 09:00 AM"
  → Each task's is_reminder_sent = true (independently)
```

### API Request Examples

**Creating multiple tasks at same time (different employers):**

```json
// Request 1: ✅ ALLOWED
{
  "employer": "Company A",
  "task_date_time": "2026-07-04 09:00:00",
  "end_time": "2026-07-04 17:00:00",
  "st_rate": 25
}

// Request 2: ✅ ALLOWED (different employer, same time)
{
  "employer": "Company B",
  "task_date_time": "2026-07-04 09:00:00",
  "end_time": "2026-07-04 13:00:00",
  "st_rate": 30
}

// Request 3: ❌ BLOCKED (same employer "Company A" at overlapping time)
{
  "employer": "Company A",
  "task_date_time": "2026-07-04 10:00:00",
  "end_time": "2026-07-04 14:00:00",
  "st_rate": 25
}
```

---

## Architecture Overview

```
User creates task via POST /task/store
    ↓
Resolve employer (firstOrCreate in employers table)
    ↓
Overlap check: same user + same employer_id + overlapping time?
    ├── YES → return error (blocked)
    └── NO → continue
        ↓
Convert times to UTC, calculate ST/OT totals
    ↓
Save task row in tasks table
    ↓
Create TaskPayment record
    ↓
Return task (times converted back to user timezone)

Background (cron jobs):
    reminders:daily-send → sends push per task on its day
    tasks:update-status  → marks expired tasks as complete
```
