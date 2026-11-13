<?php

namespace Tests\Feature;

use Illuminate\Console\Scheduling\Schedule;
use Tests\TestCase;

class ScheduleTest extends TestCase
{
    public function test_every_scheduled_job_runs_once_per_slot(): void
    {
        $events = $this->app->make(Schedule::class)->events();

        $this->assertNotEmpty($events);
        foreach ($events as $event) {
            // A duplicate crontab line must not run the job (e.g. midnight reminders) twice
            $this->assertTrue($event->onOneServer, "{$event->command} must use onOneServer()");
        }
    }
}
