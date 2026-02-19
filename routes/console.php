<?php
use Illuminate\Support\Facades\Schedule;

// -------------------------------------------------------
// 1) DAILY MIDNIGHT REMINDER
// -------------------------------------------------------
// Runs every minute so it catches each user's local midnight.
// isSameDay check in command handles per-user timezone.
// ✅ TESTING  → everyMinute()
// ✅ PRODUCTION → everyFiveMinutes()
Schedule::command('reminders:daily-send')->everyMinute();
// Schedule::command('reminders:daily-send')->everyFiveMinutes();

// -------------------------------------------------------
// 2) AUTO-UPDATE TASK STATUS (incomplete → complete)
// -------------------------------------------------------
// Checks every 10 minutes if any task's end time has passed.
// Pure UTC comparison — no timezone issues.
// Example: task ends 2026-02-20 07:00 UTC → at 07:10 UTC → marked complete.
// ✅ TESTING  → everyMinute()
// ✅ PRODUCTION → everyTenMinutes()
Schedule::command('tasks:update-status')->everyMinute();
// Schedule::command('tasks:update-status')->everyTenMinutes();
