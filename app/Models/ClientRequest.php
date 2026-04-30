<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use App\Models\TaskTitle;
use Illuminate\Support\Facades\DB;

class ClientRequest extends Model
{
    use HasFactory;

    public const STATUS_UNDER_REVIEW = 'Under Review';
    public const STATUS_RETURNED = 'Returned for Update';
    public const STATUS_PENDING_APPROVAL = 'Pending Approval';
    public const STATUS_APPROVED = 'Approved';
    public const STATUS_WORK_IN_PROGRESS = 'Work In Progress';
    public const STATUS_PENDING_CUSTOMER_REVIEW = 'Pending Customer Review';
    public const STATUS_COMPLETED = 'Completed';
    public const STATUS_REJECTED = 'Rejected';
    public const STATUS_CLIENT_RETURNED = 'Client Has Returned Request';
    public const STATUS_PENDING_TECHNICIAN_FEEDBACK = 'Pending Technician Feedback';
    public const STATUS_FINANCE_PENDING = 'Finance Pending';

    protected $fillable = [
        'request_code',
        'user_id',
        'legacy_import_email',
        'assigned_technician_id',
        'request_type_id',
        'location_id',
        'department_id',
        'full_name',
        'phone_number',
        'urgency_level',
        'answers',
        'attachments',
        'technician_review',
        'technician_return_remark',
        'technician_review_updated_at',
        'costing_entries',
        'costing_receipts',
        'quotation_entries',
        'approved_quotation_index',
        'quotation_submitted_at',
        'scheduled_date',
        'scheduled_time',
        'schedule_reminder_sent_at',
        'payment_receipt_files',
        'payment_receipt_history',
        'payment_type',
        'inspect_data',
        'inspection_sessions',
        'invoice_files',
        'invoice_uploaded_at',
        'customer_service_report',
        'technician_completed_at',
        'finance_form',
        'finance_completed_at',
        'feedback',
        'customer_review_submitted_at',
        'related_request_id',
        'status',
        'admin_approval_status',
        'admin_approval_remark',
        'admin_approved_remark',
        'subject_to_approval_remark',
        'subject_to_approval_requested_at',
        'subject_to_approval_checked_at',
        'admin_technician_remarks',
        'viewer_summary_remark',
        'viewer_summary_signature',
        'viewer_summary_updated_by_name',
        'viewer_summary_updated_at',
        'viewer_summary_history',
        'admin_approved_at',
        'assigned_at',
        'quotation_return_remark',
        'technician_log_started_at',
        'technician_log_started_label',
    ];

    protected $casts = [
        'answers' => 'array',
        'attachments' => 'array',
        'urgency_level' => 'integer',
        'technician_review' => 'array',
        'costing_entries' => 'array',
        'costing_receipts' => 'array',
        'quotation_entries' => 'array',
        'approved_quotation_index' => 'integer',
        'quotation_submitted_at' => 'datetime',
        'technician_review_updated_at' => 'datetime',
        'scheduled_date' => 'date',
        'schedule_reminder_sent_at' => 'datetime',
        'payment_receipt_files' => 'array',
        'payment_receipt_history' => 'array',
        'inspect_data' => 'array',
        'inspection_sessions' => 'array',
        'invoice_files' => 'array',
        'invoice_uploaded_at' => 'datetime',
        'customer_service_report' => 'array',
        'technician_completed_at' => 'datetime',
        'finance_form' => 'array',
        'finance_completed_at' => 'datetime',
        'admin_approved_at' => 'datetime',
        'subject_to_approval_requested_at' => 'datetime',
        'subject_to_approval_checked_at' => 'datetime',
        'admin_technician_remarks' => 'array',
        'viewer_summary_history' => 'array',
        'assigned_at' => 'datetime',
        'viewer_summary_updated_at' => 'datetime',
        'feedback' => 'array',
        'customer_review_submitted_at' => 'datetime',
        'technician_log_started_at' => 'datetime',
    ];

    public function scopeVisibleToClientEmail($query, User $user)
    {
        $email = strtolower(trim((string) $user->email));

        return $query->where(function ($q) use ($user, $email) {
            $q->where('user_id', $user->id)
                ->orWhere(function ($sub) use ($email) {
                    $sub->whereNull('user_id')
                        ->whereNotNull('legacy_import_email')
                        ->whereRaw('LOWER(legacy_import_email) = ?', [$email]);
                });
        });
    }

    public function belongsToClient(User $user): bool
    {
        if ((int) $this->user_id === (int) $user->id) {
            return true;
        }

        return $this->user_id === null
            && filled($this->legacy_import_email)
            && strtolower((string) $this->legacy_import_email) === strtolower((string) $user->email);
    }

    protected static function booted(): void
    {
        static::created(function (ClientRequest $clientRequest) {
            if (!$clientRequest->request_code) {
                $clientRequest->forceFill([
                    'request_code' => self::consumeNextRequestCode(),
                ])->saveQuietly();
            }
        });
    }


    public static function consumeNextRequestCode(): string
    {
        return DB::transaction(function () {
            $setting = DB::table('maps2u_settings')
                ->where('key', 'next_job_request_code')
                ->lockForUpdate()
                ->first();

            $current = strtoupper((string) ($setting?->value ?: 'W0001'));
            if (!preg_match('/^([A-Z]+)(\\d+)$/', $current, $matches)) {
                $current = 'W0001';
                $matches = ['W0001', 'W', '0001'];
            }

            $prefix = $matches[1];
            $number = (int) $matches[2];
            $width = strlen($matches[2]);
            $next = $prefix . str_pad((string) ($number + 1), $width, '0', STR_PAD_LEFT);

            DB::table('maps2u_settings')->updateOrInsert(
                ['key' => 'next_job_request_code'],
                ['value' => $next, 'created_at' => now(), 'updated_at' => now()]
            );

            return $current;
        });
    }

    public static function adminVisibleStatusOptions(): array
    {
        return [
            self::STATUS_UNDER_REVIEW,
            'Subject To Approval',
            self::STATUS_RETURNED,
            self::STATUS_PENDING_APPROVAL,
            self::STATUS_APPROVED,
            self::STATUS_WORK_IN_PROGRESS,
            self::STATUS_PENDING_CUSTOMER_REVIEW,
            self::STATUS_PENDING_TECHNICIAN_FEEDBACK,
            self::STATUS_CLIENT_RETURNED,
            self::STATUS_FINANCE_PENDING,
            self::STATUS_COMPLETED,
            self::STATUS_REJECTED,
        ];
    }

    public function user() { return $this->belongsTo(User::class); }
    public function assignedTechnician() { return $this->belongsTo(User::class, 'assigned_technician_id'); }
    public function requestType() { return $this->belongsTo(RequestType::class); }
    public function location() { return $this->belongsTo(Location::class); }
    public function department() { return $this->belongsTo(Department::class); }
    public function relatedRequest() { return $this->belongsTo(self::class, 'related_request_id'); }
    public function childRequests() { return $this->hasMany(self::class, 'related_request_id'); }

    public function urgencyLabel(): string
    {
        return match ($this->urgency_level) {
            3 => 'High', 2 => 'Medium', 1 => 'Low', default => '-',
        };
    }

    public function urgencyBadgeClass(): string
    {
        return match ($this->urgency_level) {
            3 => 'danger', 2 => 'warning', 1 => 'success', default => 'neutral',
        };
    }

    public function statusBadgeClass(): string
    {
        return match ($this->status) {
            self::STATUS_APPROVED => 'success',
            'Subject To Approval' => 'warning',
            self::STATUS_PENDING_APPROVAL, self::STATUS_PENDING_CUSTOMER_REVIEW => 'warning',
            self::STATUS_RETURNED => 'danger',
            self::STATUS_WORK_IN_PROGRESS => 'accent',
            self::STATUS_COMPLETED => 'success',
            self::STATUS_REJECTED => 'danger',
            self::STATUS_CLIENT_RETURNED, self::STATUS_PENDING_TECHNICIAN_FEEDBACK => 'info',
            default => 'info',
        };
    }

    public function reviewValue(string $key, string $fallback = '-'): string
    {
        return data_get($this->technician_review, $key, $fallback) ?: $fallback;
    }

    public function approvedQuotation(): ?array
    {
        if ($this->approved_quotation_index === null) {
            return null;
        }

        $approvedSlot = (int) $this->approved_quotation_index;

        return collect($this->quotation_entries ?? [])->first(function ($entry, $index) use ($approvedSlot) {
            return (int) ($entry['slot'] ?? ($index + 1)) === $approvedSlot;
        });
    }

    public function approvedCostAmount(): float
    {
        $amount = data_get($this->approvedQuotation(), 'amount');

        return is_numeric($amount) ? (float) $amount : 0.0;
    }



    public function isBulkImported(): bool
    {
        return data_get($this->inspect_data, 'source') === 'bulk_import';
    }

    public function effectiveClientRole(): ?string
    {
        if ($this->isBulkImported() && data_get($this->inspect_data, 'legacy_client_role')) {
            return data_get($this->inspect_data, 'legacy_client_role');
        }

        return $this->user?->sub_role ?: data_get($this->inspect_data, 'legacy_client_role');
    }

    public function isCompletedForImportedHistory(): bool
    {
        return $this->isBulkImported() && $this->status === self::STATUS_COMPLETED;
    }

    public function displayAnswerForQuestion($question): string
    {
        $answer = data_get($this->answers, $question->id);

        if ($question->question_type === RequestQuestion::TYPE_REMARK) {
            return trim((string) ($answer ?: '-')) ?: '-';
        }

        if (in_array($question->question_type, [RequestQuestion::TYPE_RADIO, RequestQuestion::TYPE_TASK_TITLE], true)) {
            $label = trim((string) data_get($answer, 'label', ''));
            $value = data_get($answer, 'value');

            if ($question->question_type === RequestQuestion::TYPE_TASK_TITLE && $label === '' && is_numeric($value)) {
                $label = (string) (TaskTitle::find((int) $value)?->title ?: '');
            }

            $text = $label !== '' ? $label : trim((string) ($value ?? '-'));
            $other = trim((string) data_get($answer, 'other', ''));

            return trim($text . ($other !== '' ? ' - ' . $other : '')) ?: '-';
        }

        if ($question->question_type === RequestQuestion::TYPE_DATE_RANGE) {
            return ($question->start_label ?: 'Start Date') . ': ' . (data_get($answer, 'start') ?: '-')
                . "\n" . ($question->end_label ?: 'End Date') . ': ' . (data_get($answer, 'end') ?: '-');
        }

        $items = collect($answer ?? [])->map(function ($selected) {
            $label = trim((string) data_get($selected, 'label', ''));
            $value = $label !== '' ? $label : trim((string) data_get($selected, 'value', '-'));
            $other = trim((string) data_get($selected, 'other', ''));
            return trim($value . ($other !== '' ? ' - ' . $other : ''));
        })->filter()->values();

        return $items->isNotEmpty() ? $items->implode("\n") : '-';
    }


    public function selectedTaskTitleNames(): array
    {
        $questions = $this->requestType?->questions ?? collect();
        $taskQuestions = collect($questions)->filter(fn ($question) => $question->question_type === RequestQuestion::TYPE_TASK_TITLE);

        return $taskQuestions->map(function ($question) {
            $answer = data_get($this->answers, $question->id);
            $label = trim((string) data_get($answer, 'label', ''));
            if ($label !== '') {
                return $label;
            }

            $value = data_get($answer, 'value');
            if (is_numeric($value)) {
                return TaskTitle::find((int) $value)?->title;
            }

            return trim((string) ($value ?? ''));
        })->filter()->values()->all();
    }

    public function primaryTaskTitleName(): ?string
    {
        return $this->selectedTaskTitleNames()[0] ?? null;
    }

    public function feedbackAverage(): ?float
    {
        $ratings = collect(data_get($this->feedback, 'ratings', []))
            ->flatten()
            ->filter(fn ($value) => is_numeric($value))
            ->map(fn ($value) => (int) $value);

        if ($ratings->isEmpty()) {
            return null;
        }

        return round($ratings->avg(), 2);
    }

    public function technicianStatusLabel(): string
    {
        return $this->technician_completed_at ? 'Completed' : ($this->status === self::STATUS_PENDING_TECHNICIAN_FEEDBACK ? self::STATUS_PENDING_TECHNICIAN_FEEDBACK : $this->status);
    }

    public function technicianStatusBadgeClass(): string
    {
        return $this->technician_completed_at ? 'success' : $this->statusBadgeClass();
    }

    public function adminWorkflowLabel(): string
    {
        if ($this->finance_completed_at) {
            return 'Completed';
        }

        if ($this->status === self::STATUS_REJECTED) {
            return self::STATUS_REJECTED;
        }

        if ($this->hasFinancePending()) {
            return self::STATUS_FINANCE_PENDING;
        }

        return $this->status;
    }

    public function adminWorkflowBadgeClass(): string
    {
        if ($this->finance_completed_at) {
            return 'success';
        }

        if ($this->status === self::STATUS_REJECTED) {
            return 'danger';
        }

        if ($this->hasFinancePending()) {
            return 'warning';
        }

        return $this->statusBadgeClass();
    }


    public function activeInspectionSession(): ?array
    {
        return collect($this->inspection_sessions ?? [])->first(fn ($session) => empty($session['ended_at']));
    }

    public function hasActiveTechnicianLog(): bool
    {
        return !empty($this->technician_log_started_at);
    }

    public function technicianLogStartedAt(): ?Carbon
    {
        $label = trim((string) ($this->technician_log_started_label ?? ''));
        if ($label !== '') {
            foreach (['Y-m-d H:i:s', 'd M Y H:i', 'd M Y H:i:s', 'd M Y h:i A', 'd M Y h:i:s A'] as $format) {
                try {
                    return Carbon::createFromFormat($format, $label, 'Asia/Kuala_Lumpur');
                } catch (\Throwable $e) {
                }
            }

            try {
                return Carbon::parse($label, 'Asia/Kuala_Lumpur');
            } catch (\Throwable $e) {
            }
        }

        if (!$this->technician_log_started_at) {
            return null;
        }

        if ($this->technician_log_started_at instanceof Carbon) {
            return $this->technician_log_started_at->copy()->timezone('Asia/Kuala_Lumpur');
        }

        return Carbon::parse($this->technician_log_started_at, 'UTC')->timezone('Asia/Kuala_Lumpur');
    }

    public function technicianLogStartedLabel(): ?string
    {
        $startedAt = $this->technicianLogStartedAt();

        return $startedAt?->format('d M Y H:i');
    }

    public function latestInspectionSession(): ?array
    {
        return collect($this->inspection_sessions ?? [])->last();
    }

    public function inspectionDate(): ?string
    {
        $first = collect($this->inspection_sessions ?? [])->first();
        if (!$first || empty($first['started_at'])) {
            return null;
        }

        return Carbon::parse($first['started_at'])->format('d M Y');
    }

    public function upcomingScheduleDays(): ?int
    {
        if (!$this->scheduled_date || $this->technician_completed_at) {
            return null;
        }

        $days = now()->startOfDay()->diffInDays($this->scheduled_date->copy()->startOfDay(), false);
        if ($days < 0 || $days > 3) {
            return null;
        }

        return $days;
    }

    public function hasFinancePending(): bool
    {
        if ($this->isBulkImported() && $this->status === self::STATUS_COMPLETED) {
            return false;
        }

        return (bool) $this->technician_completed_at && !$this->finance_completed_at && (bool) $this->approvedQuotation();
    }

    public function financeAmount(): ?float
    {
        $value = data_get($this->finance_form, 'approved_amount');
        return is_numeric($value) ? (float) $value : null;
    }


    public function adminApprovalBadgeClass(): string
    {
        return match ($this->admin_approval_status) {
            'approved' => 'success',
            'rejected' => 'danger',
            'subject_to_approval' => 'warning',
            default => 'warning',
        };
    }

    public function adminApprovalLabel(): string
    {
        return match ($this->admin_approval_status) {
            'approved' => 'Approved',
            'rejected' => 'Rejected',
            'subject_to_approval' => 'Subject To Approval',
            default => 'Pending Admin Approval',
        };
    }

    public function subjectToApprovalTicked(): bool
    {
        return (bool) $this->subject_to_approval_checked_at;
    }

    public function subjectToApprovalPending(): bool
    {
        return $this->admin_approval_status === 'subject_to_approval' && !$this->subjectToApprovalTicked();
    }

    public function approvalPrintableRemarks(): array
    {
        $items = [];

        if ($this->admin_approved_remark) {
            $items[] = ['label' => 'Approved Remark', 'value' => $this->admin_approved_remark];
        }

        if ($this->admin_approval_remark) {
            $items[] = ['label' => 'Rejected Remark', 'value' => $this->admin_approval_remark];
        }

        if ($this->subject_to_approval_remark) {
            $items[] = ['label' => 'Subject To Approval Remark', 'value' => $this->subject_to_approval_remark];
        }

        return $items;
    }

    public function adminTechnicianRemarkLines(): array
    {
        return collect($this->admin_technician_remarks ?? [])->map(function ($item) {
            $sender = ucfirst((string) data_get($item, 'sender_type', 'Admin'));
            $name = trim((string) data_get($item, 'sender_name', ''));
            $when = (string) data_get($item, 'created_at_label', data_get($item, 'created_at', ''));
            $remark = trim((string) data_get($item, 'remark', ''));

            $head = $name !== '' ? "{$sender} - {$name}" : $sender;
            if ($when !== '') {
                $head .= " ({$when})";
            }

            return ['header' => $head, 'remark' => $remark];
        })->filter(fn ($item) => $item['remark'] !== '')->values()->all();
    }


    public function viewerSummaryCurrent(): array
    {
        return [
            'remark' => trim((string) ($this->viewer_summary_remark ?? '')),
            'signature' => $this->viewer_summary_signature,
            'updated_by_name' => $this->viewer_summary_updated_by_name,
            'updated_at' => $this->viewer_summary_updated_at,
            'updated_at_label' => $this->viewer_summary_updated_at?->copy()->timezone('Asia/Kuala_Lumpur')->format('d M Y h:i A'),
        ];
    }

    public function viewerSummaryHistoryLines(): array
    {
        return collect($this->viewer_summary_history ?? [])->map(function ($item) {
            $remark = trim((string) data_get($item, 'remark', ''));
            if ($remark === '') {
                return null;
            }

            return [
                'header' => trim((string) data_get($item, 'updated_by_name', 'Viewer')) . ' (' . trim((string) data_get($item, 'updated_at_label', data_get($item, 'updated_at', '-'))) . ')',
                'remark' => $remark,
                'signature' => data_get($item, 'signature'),
            ];
        })->filter()->values()->all();
    }

    public function technicianProductivitySeconds(): ?int
    {
        if (!$this->assigned_at || !$this->technician_completed_at) {
            return null;
        }

        return $this->technician_completed_at->diffInSeconds($this->assigned_at);
    }

    public function inspectionSessionDurationSeconds(array $session): int
    {
        $stored = $session['duration_seconds'] ?? null;
        if (is_numeric($stored) && (int) $stored > 0) {
            return (int) $stored;
        }

        $startedUnix = $session['started_at_unix'] ?? null;
        $endedUnix = $session['ended_at_unix'] ?? null;
        if (is_numeric($startedUnix) && is_numeric($endedUnix)) {
            return max(0, (int) $endedUnix - (int) $startedUnix);
        }

        $startedAt = $this->parseMalaysiaSessionTime($session['started_at'] ?? null, $session['started_label'] ?? null);
        $endedAt = $this->parseMalaysiaSessionTime($session['ended_at'] ?? null, $session['ended_label'] ?? null);
        if (!$startedAt || !$endedAt) {
            return 0;
        }

        try {
            return max(0, $startedAt->diffInSeconds($endedAt, false));
        } catch (\Throwable $e) {
            return 0;
        }
    }


    protected function parseMalaysiaSessionTime(?string $primary, ?string $label = null): ?Carbon
    {
        foreach ([$primary, $label] as $value) {
            $value = trim((string) $value);
            if ($value === '') {
                continue;
            }

            foreach (['Y-m-d H:i:s', 'd M Y H:i', 'd M Y H:i:s', 'd M Y h:i A', 'd M Y h:i:s A'] as $format) {
                try {
                    return Carbon::createFromFormat($format, $value, 'Asia/Kuala_Lumpur');
                } catch (\Throwable $e) {
                }
            }

            try {
                return Carbon::parse($value, 'Asia/Kuala_Lumpur');
            } catch (\Throwable $e) {
            }
        }

        return null;
    }


    public function reportDurationSeconds(): int
    {
        if ($this->isBulkImported()) {
            $completeRaw = data_get($this->customer_service_report, 'legacy_duration_complete');
            $pendingRaw = data_get($this->customer_service_report, 'legacy_duration_pending') ?: data_get($this->inspect_data, 'legacy_hold_customer_service_report.legacy_duration_pending');

            $completeSeconds = $this->legacyDurationToSeconds($completeRaw);
            $pendingSeconds = $this->legacyDurationToSeconds($pendingRaw);

            if ($this->status === self::STATUS_COMPLETED && $completeSeconds !== null) {
                return $completeSeconds;
            }

            if ($pendingSeconds !== null) {
                $currentLogSeconds = (int) collect($this->inspection_sessions ?? [])->sum(fn ($session) => $this->inspectionSessionDurationSeconds((array) $session));
                return $pendingSeconds + $currentLogSeconds;
            }

            if ($completeSeconds !== null) {
                return $completeSeconds;
            }
        }

        $reportSeconds = data_get($this->customer_service_report, 'duration_seconds');
        if (is_numeric($reportSeconds) && (int) $reportSeconds > 0) {
            return (int) $reportSeconds;
        }

        $inspectionSeconds = $this->totalReportableInspectionDurationSeconds();
        if ($inspectionSeconds > 0) {
            return $inspectionSeconds;
        }

        $started = $this->technicianLogStartedAt();
        if (!$started || !$this->technician_completed_at) {
            return 0;
        }

        try {
            return max(0, $started->diffInSeconds($this->technician_completed_at->copy()->timezone('Asia/Kuala_Lumpur'), false));
        } catch (\Throwable $e) {
            return 0;
        }
    }

    protected function legacyDurationToSeconds($value): ?int
    {
        $value = trim((string) $value);
        if ($value === '') {
            return null;
        }
        if (is_numeric($value)) {
            return (int) round(((float) $value) * 60);
        }
        $seconds = 0;
        if (preg_match_all('/(\d+(?:\.\d+)?)\s*(days?|d|hours?|hrs?|h|minutes?|mins?|m|seconds?|secs?|s)/i', strtolower($value), $matches, PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                $number = (float) $match[1];
                $unit = strtolower($match[2]);
                $seconds += in_array($unit, ['day', 'days', 'd'], true) ? (int) round($number * 86400)
                    : (in_array($unit, ['hour', 'hours', 'hr', 'hrs', 'h'], true) ? (int) round($number * 3600)
                    : (in_array($unit, ['minute', 'minutes', 'min', 'mins', 'm'], true) ? (int) round($number * 60) : (int) round($number)));
            }
            return $seconds;
        }
        return null;
    }

    public function reportDurationHours(): float
    {
        return round($this->reportDurationSeconds() / 3600, 2);
    }

    public function totalInspectionDurationSeconds(): int
    {
        return (int) collect($this->inspection_sessions ?? [])->sum(fn ($session) => $this->inspectionSessionDurationSeconds((array) $session));
    }

    public function legacyHeldInspectionSessions(): array
    {
        return collect(data_get($this->inspect_data, 'legacy_hold_inspection_sessions', []))
            ->map(fn ($session) => (array) $session)
            ->filter(fn ($session) => $this->inspectionSessionDurationSeconds($session) > 0)
            ->values()
            ->all();
    }

    public function reportableInspectionSessions(): array
    {
        return collect($this->legacyHeldInspectionSessions())
            ->merge(collect($this->inspection_sessions ?? [])->map(fn ($session) => (array) $session))
            ->values()
            ->all();
    }

    public function totalReportableInspectionDurationSeconds(): int
    {
        return (int) collect($this->reportableInspectionSessions())
            ->sum(fn ($session) => $this->inspectionSessionDurationSeconds((array) $session));
    }

    public function compiledDailyLogDescription(): string
    {
        return collect($this->inspection_sessions ?? [])
            ->map(function ($session) {
                $session = (array) $session;
                $date = $session['date_label'] ?? null;
                $start = $session['time_start'] ?? null;
                $end = $session['time_end'] ?? null;
                $durationSeconds = $this->inspectionSessionDurationSeconds($session);
                $duration = $this->formattedDuration($durationSeconds);
                $remark = trim((string) ($session['remark'] ?? '-'));
                $verifySignedAt = trim((string) ($session['verify_by_signed_at_label'] ?? ''));

                $parts = array_filter([
                    $date,
                    ($start || $end) ? trim(($start ?: '-') . ' - ' . ($end ?: '-')) : null,
                    $duration !== '-' ? $duration : null,
                    $remark !== '' ? $remark : '-',
                    $verifySignedAt !== '' ? 'Verify signed: ' . $verifySignedAt : null,
                ]);

                return $parts ? '- ' . implode(' | ', $parts) : null;
            })
            ->filter()
            ->implode("
");
    }

    public function formattedDuration(?int $seconds): string
    {
        if ($seconds === null) {
            return '-';
        }

        $seconds = max(0, (int) $seconds);
        $days = intdiv($seconds, 86400);
        $hours = intdiv($seconds % 86400, 3600);
        $minutes = intdiv($seconds % 3600, 60);
        $remaining = $seconds % 60;

        $parts = [];
        if ($days > 0) {
            $parts[] = $days . 'd';
        }
        if ($hours > 0 || $days > 0) {
            $parts[] = sprintf('%02dh', $hours);
        }
        $parts[] = sprintf('%02dm', $minutes);
        if ($days === 0) {
            $parts[] = sprintf('%02ds', $remaining);
        }

        return implode(' ', $parts);
    }
}

