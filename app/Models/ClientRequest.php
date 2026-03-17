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

        if (!empty($this->invoice_files)) {
            return 'Finance Pending';
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

        if (!empty($this->invoice_files)) {
            return 'warning';
        }

        return $this->statusBadgeClass();
    }

    public function activeInspectionSession(): ?array
    {
        return collect($this->inspection_sessions ?? [])->first(fn ($session) => empty($session['ended_at']));
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
        return !empty($this->invoice_files) && !$this->finance_completed_at;
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
        if (!$this->assigned_at || !$this->invoice_uploaded_at) {
            return null;
        }

        return $this->invoice_uploaded_at->diffInSeconds($this->assigned_at);
    }

    public function formattedDuration(?int $seconds): string
    {
        if ($seconds === null) {
            return '-';
        }

        $hours = intdiv($seconds, 3600);
        $minutes = intdiv($seconds % 3600, 60);
        $remaining = $seconds % 60;

        if ($hours > 0) {
            return sprintf('%dh %02dm %02ds', $hours, $minutes, $remaining);
        }

        return sprintf('%02dm %02ds', $minutes, $remaining);
    }
}
