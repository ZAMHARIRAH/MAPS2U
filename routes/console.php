<?php

use App\Console\Commands\SendScheduleReminderEmails;
use App\Services\ReportArchiveService;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('reports:archive-yearly {year?}', function (?int $year = null) {
    $targetYear = $year ?: now()->subYear()->year;
    app(ReportArchiveService::class)->archiveYear((int) $targetYear);
    $this->info('Report archive generated for year ' . $targetYear);
})->purpose('Archive annual branch and location report snapshots.');

Schedule::command('reports:archive-yearly')->yearlyOn(12, 31, '23:55');

Schedule::command('maps2u:send-schedule-reminders')->dailyAt('08:00');
