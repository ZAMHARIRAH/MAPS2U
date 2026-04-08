<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

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
        'assigned_at' => 'datetime',
        'feedback' => 'array',
        'customer_review_submitted_at' => 'datetime',
        'technician_log_started_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::created(function (ClientRequest $clientRequest) {
            if (!$clientRequest->request_code) {
                $clientRequest->forceFill([
                    'request_code' => 'W' . str_pad((string) $clientRequest->id, 4, '0', STR_PAD_LEFT),
                ])->saveQuietly();
            }
        });
    }


    public static function adminVisibleStatusOptions(): array
    {
        return [
            self::STATUS_UNDER_REVIEW,
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

        return collect($this->quotation_entries ?? [])->firstWhere('slot', $this->approved_quotation_index);
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
            default => 'warning',
        };
    }

    public function adminApprovalLabel(): string
    {
        return match ($this->admin_approval_status) {
            'approved' => 'Approved',
            'rejected' => 'Rejected',
            default => 'Pending Admin Approval',
        };
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

    public function totalInspectionDurationSeconds(): int
    {
        return (int) collect($this->inspection_sessions ?? [])->sum(fn ($session) => $this->inspectionSessionDurationSeconds((array) $session));
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

                $parts = array_filter([
                    $date,
                    ($start || $end) ? trim(($start ?: '-') . ' - ' . ($end ?: '-')) : null,
                    $duration !== '-' ? $duration : null,
                    $remark !== '' ? $remark : '-',
                ]);

                return $parts ? '- ' . implode(' | ', $parts) : null;
            })
            ->filter()
            ->implode("\n");
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

