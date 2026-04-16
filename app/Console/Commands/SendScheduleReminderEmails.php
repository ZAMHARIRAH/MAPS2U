<?php

namespace App\Console\Commands;

use App\Models\ClientRequest;
use App\Services\ClientCommunicationService;
use Illuminate\Console\Command;

class SendScheduleReminderEmails extends Command
{
    protected $signature = 'maps2u:send-schedule-reminders';

    protected $description = 'Send reminder notifications one day before scheduled technician visits.';

    public function handle(ClientCommunicationService $communicationService): int
    {
        $targetDate = now('Asia/Kuala_Lumpur')->addDay()->startOfDay();

        $jobs = ClientRequest::with(['user', 'requestType', 'assignedTechnician'])
            ->whereDate('scheduled_date', $targetDate->toDateString())
            ->whereNotNull('scheduled_date')
            ->whereNotNull('scheduled_time')
            ->whereNull('schedule_reminder_sent_at')
            ->whereNull('technician_completed_at')
            ->where('status', '!=', ClientRequest::STATUS_REJECTED)
            ->get();

        $sent = 0;

        foreach ($jobs as $job) {
            $communicationService->notify($job, 'inspection_schedule_reminder');
            $job->forceFill([
                'schedule_reminder_sent_at' => now('Asia/Kuala_Lumpur'),
            ])->save();
            $sent++;
        }

        $this->info("Sent {$sent} schedule reminder notification(s).");

        return self::SUCCESS;
    }
}
