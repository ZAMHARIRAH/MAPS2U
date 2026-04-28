<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ClientRequest;
use App\Models\Department;
use App\Models\Location;
use App\Models\RequestQuestion;
use App\Models\RequestType;
use App\Models\TaskTitle;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class BulkImportController extends Controller
{
    public function index()
    {
        return view('admin.bulk-import.index', [
            'recentImports' => ClientRequest::with(['user', 'requestType', 'location', 'department', 'assignedTechnician'])
                ->where('inspect_data->source', 'bulk_import')
                ->latest()
                ->limit(15)
                ->get(),
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'csv_file' => ['required', 'file', 'mimes:csv,txt', 'max:10240'],
            'dry_run' => ['nullable', 'boolean'],
        ]);

        $rows = $this->readCsv($request->file('csv_file')->getRealPath());
        $dryRun = $request->boolean('dry_run');
        $results = [];
        $created = 0;
        $failed = 0;

        DB::beginTransaction();
        try {
            foreach ($rows as $index => $row) {
                $line = $index + 2;
                try {
                    $payload = $this->buildPayload($row);

                    if (!$dryRun) {
                        $clientRequest = ClientRequest::firstOrNew(['request_code' => $payload['request_code']]);
                        $clientRequest->forceFill($payload)->save();
                    }

                    $created++;
                    $results[] = ['line' => $line, 'status' => 'OK', 'message' => ($dryRun ? 'Valid' : 'Imported') . ' - ' . $payload['request_code']];
                } catch (\Throwable $e) {
                    $failed++;
                    $results[] = ['line' => $line, 'status' => 'FAILED', 'message' => $e->getMessage()];
                }
            }

            if ($dryRun || $failed > 0) {
                DB::rollBack();
            } else {
                DB::commit();
            }
        } catch (\Throwable $e) {
            DB::rollBack();
            return back()->withErrors(['csv_file' => $e->getMessage()]);
        }

        return back()->with('import_results', $results)->with('success', ($dryRun ? 'Dry run complete.' : 'Bulk import complete.') . " OK={$created}, failed={$failed}");
    }

    private function readCsv(string $path): array
    {
        $handle = fopen($path, 'r');
        if (!$handle) {
            throw new \RuntimeException('Cannot read uploaded CSV file.');
        }

        $headers = fgetcsv($handle);
        if (!$headers) {
            throw new \RuntimeException('CSV header row is missing.');
        }

        $headers = array_map(fn ($h) => $this->normalizeHeader((string) $h), $headers);
        $rows = [];
        while (($data = fgetcsv($handle)) !== false) {
            if (count(array_filter($data, fn ($v) => trim((string) $v) !== '')) === 0) {
                continue;
            }
            $row = [];
            foreach ($headers as $i => $header) {
                if ($header === '') {
                    continue;
                }
                $row[$header] = trim((string) ($data[$i] ?? ''));
            }
            $rows[] = $row;
        }
        fclose($handle);

        return $rows;
    }

    private function normalizeHeader(string $header): string
    {
        $header = trim($header);
        $header = preg_replace('/[^A-Za-z0-9]+/', '_', $header) ?: '';
        return trim(Str::lower($header), '_');
    }

    private function buildPayload(array $row): array
    {
        $requestCode = $this->requiredAny($row, ['wo_no', 'request_code'], 'WO No');
        $createdAt = $this->parseDate($this->value($row, ['timestamp', 'created_at'])) ?? now('Asia/Kuala_Lumpur');
        $completedAt = $this->parseDate($this->value($row, ['end_date', 'completed_at']));

        $branchName = $this->value($row, ['branch']);
        $hqLocationName = $this->value($row, ['location_hq', 'location']);
        $clientRole = filled($hqLocationName) ? User::CLIENT_HQ : User::CLIENT_KINDERGARTEN;
        $locationName = $clientRole === User::CLIENT_HQ ? $hqLocationName : $branchName;
        if (!filled($locationName)) {
            throw new \RuntimeException('Missing Branch or Location (HQ). One of them must be filled.');
        }

        $location = $this->findOrCreateLocation($locationName, $clientRole === User::CLIENT_HQ ? Location::TYPE_HQ : Location::TYPE_BRANCH);

        $requestTypeName = $this->requiredAny($row, ['type_of_request', 'request_type'], 'Type Of Request');
        $requestType = RequestType::whereRaw('LOWER(name) = ?', [Str::lower($requestTypeName)])->first();
        if (!$requestType) {
            throw new \RuntimeException('Request type not found: ' . $requestTypeName);
        }

        $technician = $this->resolveTechnician($row);
        $email = $this->normalizeEmail($this->value($row, ['email_responder', 'email']));
        $user = $email ? User::whereRaw('LOWER(email) = ?', [Str::lower($email)])->first() : null;

        $taskApproval = $this->value($row, ['task_approval']);
        $hasLegacyDescription = !empty($this->descriptionParts($row));
        $status = $this->normalizeStatus($this->value($row, ['status_wo', 'status']), $technician, $taskApproval, $hasLegacyDescription);
        $isCompleted = $status === ClientRequest::STATUS_COMPLETED;

        $answers = $this->buildAnswers($requestType, $row);
        $attachments = $this->buildAttachments($this->value($row, ['attachment', 'attachments']));
        $durationCompleteSeconds = $this->durationToSeconds($this->value($row, ['duration_complete']));
        $durationPendingSeconds = $this->durationToSeconds($this->value($row, ['duration_pending']));
        $inspectionSessions = $this->buildInspectionSessions($row, $createdAt, $durationPendingSeconds, $durationCompleteSeconds, $isCompleted);
        $csr = $isCompleted ? $this->buildCustomerServiceReport($row, $requestCode, $technician, $createdAt, $completedAt, $durationCompleteSeconds, $durationPendingSeconds, $inspectionSessions, $isCompleted) : null;
        $legacyHeldSessions = $isCompleted ? [] : $this->buildInspectionSessions($row, $createdAt, $durationPendingSeconds, $durationCompleteSeconds, false, true);
        $legacyHeldCsr = $isCompleted ? null : $this->buildCustomerServiceReport($row, $requestCode, $technician, $createdAt, $completedAt, $durationCompleteSeconds, $durationPendingSeconds, $legacyHeldSessions, false);
        $quotation = $this->buildApprovedQuotation($row, $createdAt);
        $paymentAmount = $this->moneyValue($this->value($row, ['payment_amount']));

        $inspectData = [
            'source' => 'bulk_import',
            'imported_at' => now('Asia/Kuala_Lumpur')->toDateTimeString(),
            'legacy_import_email' => $email,
            'legacy_client_role' => $clientRole,
            'legacy_month' => $this->value($row, ['month']),
            'legacy_status_wo' => $this->value($row, ['status_wo', 'status']),
            'legacy_task_approval' => $taskApproval,
            'legacy_branch' => $branchName,
            'legacy_hq_location' => $hqLocationName,
            'legacy_hold_customer_service_report' => $legacyHeldCsr,
            'legacy_hold_inspection_sessions' => $legacyHeldSessions,
            'legacy_hold_has_description' => $hasLegacyDescription,
        ];

        return [
            'request_code' => $requestCode,
            'user_id' => $user?->id,
            'legacy_import_email' => $email,
            'assigned_technician_id' => $technician?->id,
            'request_type_id' => $requestType->id,
            'location_id' => $location->id,
            'department_id' => null,
            'full_name' => $this->fallbackFullName($row, $locationName, $email),
            'phone_number' => $this->value($row, ['phone_number', 'phone']) ?: '-',
            'urgency_level' => $this->normalizeUrgency($this->value($row, ['urgency_of_needed', 'urgency_level'])),
            'answers' => $answers,
            'attachments' => $attachments,
            'status' => $status,
            'admin_approval_status' => $this->isTruthy($taskApproval) || in_array($status, [ClientRequest::STATUS_COMPLETED, ClientRequest::STATUS_WORK_IN_PROGRESS, ClientRequest::STATUS_APPROVED], true) ? 'approved' : null,
            'admin_approved_at' => $this->isTruthy($taskApproval) || in_array($status, [ClientRequest::STATUS_COMPLETED, ClientRequest::STATUS_WORK_IN_PROGRESS, ClientRequest::STATUS_APPROVED], true) ? $createdAt : null,
            'assigned_at' => $technician ? $createdAt : null,
            'inspect_data' => $inspectData,
            'inspection_sessions' => $inspectionSessions,
            'customer_service_report' => $csr,
            'technician_completed_at' => $isCompleted ? ($completedAt ?: $createdAt) : null,
            'invoice_uploaded_at' => $isCompleted ? ($completedAt ?: $createdAt) : null,
            'quotation_entries' => $quotation ? [$quotation] : null,
            'approved_quotation_index' => $quotation ? 1 : null,
            'quotation_submitted_at' => $quotation ? $createdAt : null,
            'finance_form' => $paymentAmount !== null ? ['approved_amount' => $paymentAmount, 'source' => 'bulk_import'] : null,
            'finance_completed_at' => $isCompleted ? ($completedAt ?: $createdAt) : null,
            'feedback' => $isCompleted ? ['source' => 'bulk_import', 'assumed_submitted' => true] : null,
            'customer_review_submitted_at' => $isCompleted ? ($completedAt ?: $createdAt) : null,
            'created_at' => $createdAt,
            'updated_at' => now('Asia/Kuala_Lumpur'),
        ];
    }

    private function buildAnswers(RequestType $requestType, array $row): array
    {
        $requestType->loadMissing('questions.options');
        $answers = [];

        foreach ($requestType->questions as $question) {
            $questionKey = $this->normalizeHeader($question->question_text);
            $raw = $this->answerValueForQuestion($question, $questionKey, $row);

            if ($question->question_type === RequestQuestion::TYPE_TASK_TITLE || $questionKey === 'task_title' || (int) $question->sort_order === 1) {
                $task = filled($raw) ? TaskTitle::whereRaw('LOWER(title) = ?', [Str::lower($raw)])->first() : null;
                $answers[$question->id] = [
                    'value' => $task?->id ? (string) $task->id : (string) ($raw ?: '-'),
                    'label' => (string) ($task?->title ?: ($raw ?: '-')),
                    'legacy_value' => (string) ($raw ?: '-'),
                    'source' => 'bulk_import',
                ];
            } elseif (in_array($question->question_type, [RequestQuestion::TYPE_RADIO], true)) {
                $answers[$question->id] = ['value' => (string) ($raw ?: '-'), 'label' => (string) ($raw ?: '-')];
            } else {
                $answers[$question->id] = (string) ($raw ?: '-');
            }
        }

        return $answers;
    }

    private function answerValueForQuestion(RequestQuestion $question, string $questionKey, array $row): ?string
    {
        $keys = [$questionKey];

        if ($questionKey === 'task_title' || (int) $question->sort_order === 1) {
            $keys = array_merge($keys, ['task_title', 'answer_1', 'question_1']);
        }

        if (in_array($questionKey, ['issues_request', 'issue_request', 'issues'], true) || (int) $question->sort_order === 2) {
            $keys = array_merge($keys, ['issues', 'issues_request', 'issue_request', 'answer_2', 'question_2', 'remark', 'remarks_notes']);
        }

        foreach (array_unique($keys) as $key) {
            if (array_key_exists($key, $row) && filled($row[$key])) {
                return $row[$key];
            }
        }

        return (int) $question->sort_order === 2 ? '-' : null;
    }

    private function resolveTechnician(array $row): ?User
    {
        $email = $this->normalizeEmail($this->value($row, ['email_pic']), false);
        if ($email) {
            $technician = User::where('role', User::ROLE_TECHNICIAN)
                ->whereRaw('LOWER(email) = ?', [Str::lower($email)])
                ->first();
            if (!$technician) {
                throw new \RuntimeException('Technician email not found: ' . $email);
            }
            return $technician;
        }

        $name = $this->value($row, ['pic_maps', 'technician_name']);
        if (filled($name)) {
            $technician = User::where('role', User::ROLE_TECHNICIAN)
                ->whereRaw('LOWER(name) = ?', [Str::lower($name)])
                ->first();
            if (!$technician) {
                throw new \RuntimeException('Technician name not found: ' . $name);
            }
            return $technician;
        }

        return null;
    }

    private function findOrCreateLocation(string $name, string $type): Location
    {
        $location = Location::where('type', $type)
            ->whereRaw('LOWER(name) = ?', [Str::lower($name)])
            ->first();

        if ($location) {
            return $location;
        }

        return Location::create([
            'name' => $name,
            'type' => $type,
            'address' => '-',
            'state' => null,
            'is_active' => true,
        ]);
    }

    private function buildAttachments(?string $value): array
    {
        if (!filled($value)) {
            return [];
        }

        return collect(preg_split('/[\r\n,|]+/', $value))
            ->map(fn ($link) => trim((string) $link))
            ->filter()
            ->values()
            ->map(fn ($link) => [
                'original_name' => 'Legacy attachment link',
                'path' => $link,
                'url' => $link,
                'is_legacy_link' => true,
                'mime_type' => 'text/uri-list',
                'size' => null,
            ])->all();
    }

    private function buildInspectionSessions(array $row, Carbon $createdAt, ?int $durationPendingSeconds, ?int $durationCompleteSeconds, bool $isCompleted, bool $forcePendingSession = false): array
    {
        $sessions = [];
        $remarkParts = $this->descriptionParts($row);
        $remark = $remarkParts ? implode("\n", $remarkParts) : 'Legacy migrated work log.';

        if ($forcePendingSession && $durationPendingSeconds !== null && $durationPendingSeconds > 0) {
            $end = $createdAt->copy()->addSeconds($durationPendingSeconds);
            $sessions[] = [
                'source' => 'bulk_import_duration_pending',
                'started_at' => $createdAt->toDateTimeString(),
                'ended_at' => $end->toDateTimeString(),
                'started_at_unix' => $createdAt->timestamp,
                'ended_at_unix' => $end->timestamp,
                'date_label' => $createdAt->format('d M Y'),
                'time_start' => $createdAt->format('H:i'),
                'time_end' => $end->format('H:i'),
                'duration_seconds' => $durationPendingSeconds,
                'duration_label' => ClientRequest::make()->formattedDuration($durationPendingSeconds),
                'remark' => trim("Legacy pending duration.\n" . $remark),
                'attachments' => [],
            ];
        }

        if ($isCompleted && $durationCompleteSeconds !== null && $durationCompleteSeconds > 0) {
            $start = $createdAt->copy();
            $end = $start->copy()->addSeconds($durationCompleteSeconds);
            $sessions[] = [
                'source' => 'bulk_import_duration_complete',
                'started_at' => $start->toDateTimeString(),
                'ended_at' => $end->toDateTimeString(),
                'started_at_unix' => $start->timestamp,
                'ended_at_unix' => $end->timestamp,
                'date_label' => $start->format('d M Y'),
                'time_start' => $start->format('H:i'),
                'time_end' => $end->format('H:i'),
                'duration_seconds' => $durationCompleteSeconds,
                'duration_label' => ClientRequest::make()->formattedDuration($durationCompleteSeconds),
                'remark' => $remark,
                'attachments' => [],
            ];
        }

        return $sessions;
    }

    private function buildCustomerServiceReport(array $row, string $requestCode, ?User $technician, Carbon $createdAt, ?Carbon $completedAt, ?int $durationCompleteSeconds, ?int $durationPendingSeconds, array $sessions, bool $isCompleted): ?array
    {
        $parts = $this->descriptionParts($row);
        $hasAny = $parts || $durationCompleteSeconds !== null || $durationPendingSeconds !== null || !empty($sessions);
        if (!$hasAny) {
            return null;
        }

        $durationSeconds = $durationCompleteSeconds ?? ($durationPendingSeconds ?? (int) collect($sessions)->sum(fn ($session) => (int) ($session['duration_seconds'] ?? 0)));
        $description = $parts ? implode("\n", $parts) : 'Legacy migrated customer service report.';

        return [
            'source' => 'bulk_import',
            'technician_name' => $technician?->name,
            'job_id' => $requestCode,
            'date_inspection' => ($completedAt ?: $createdAt)->format('d M Y'),
            'duration_seconds' => $durationSeconds,
            'duration_of_work' => ClientRequest::make()->formattedDuration($durationSeconds),
            'legacy_duration_complete' => $this->value($row, ['duration_complete']),
            'legacy_duration_pending' => $this->value($row, ['duration_pending']),
            'time_history' => $sessions,
            'description_of_work' => $description,
            'description_entries' => collect($sessions)->map(fn ($session) => [
                'date_label' => $session['date_label'] ?? null,
                'time_range' => trim((($session['time_start'] ?? '-') . ' - ' . ($session['time_end'] ?? '-'))),
                'duration_label' => $session['duration_label'] ?? '-',
                'remark' => $session['remark'] ?? $description,
                'verify_by_signed_at_label' => null,
                'verify_by_signature' => null,
            ])->values()->all(),
            'suggestion_recommendation' => null,
            'attachments' => [],
            'daily_log_attachments' => [],
            'person_in_charge_signature' => null,
            'verify_by_signature' => null,
            'submitted_at' => ($completedAt ?: $createdAt)->toDateTimeString(),
            'is_legacy_incomplete_report' => !$isCompleted,
        ];
    }

    private function descriptionParts(array $row): array
    {
        $parts = [];
        $remarks = $this->value($row, ['remarks_notes']);
        $progress = $this->value($row, ['latest_progress']);

        if (filled($remarks)) {
            $parts[] = 'Remarks/Notes: ' . $remarks;
        }
        if (filled($progress)) {
            $parts[] = 'Latest Progress: ' . $progress;
        }

        return $parts;
    }

    private function buildApprovedQuotation(array $row, Carbon $createdAt): ?array
    {
        $amount = $this->moneyValue($this->value($row, ['payment_amount']));
        if ($amount === null) {
            return null;
        }

        return [
            'slot' => 1,
            'source' => 'bulk_import',
            'company_name' => 'Legacy Approved Amount',
            'subject_to_approval' => false,
            'amount' => $amount,
            'summary_report' => 'Legacy migrated approved payment amount only.',
            'summary_files' => [],
            'admin_signed_at' => $createdAt->format('d M Y H:i'),
            'vendor_snapshot' => ['company_name' => 'Legacy Approved Amount'],
        ];
    }

    private function normalizeEmail(?string $email, bool $throw = true): ?string
    {
        $email = trim((string) $email);
        if ($email === '') {
            return null;
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            if ($throw) {
                throw new \RuntimeException('Invalid email format: ' . $email);
            }
            return null;
        }

        return Str::lower($email);
    }

    private function normalizeStatus(?string $status, ?User $technician, ?string $taskApproval, bool $hasLegacyDescription = false): string
    {
        $value = Str::lower(trim((string) $status));
        $value = preg_replace('/[\s_-]+/', ' ', $value);

        if (in_array($value, ['completed', 'complete', 'closed', 'done'], true)) {
            return ClientRequest::STATUS_COMPLETED;
        }
        if (in_array($value, ['pending customer review'], true)) {
            return ClientRequest::STATUS_COMPLETED;
        }
        if (in_array($value, ['pending'], true)) {
            if (!$technician) {
                return ClientRequest::STATUS_UNDER_REVIEW;
            }

            return $hasLegacyDescription ? ClientRequest::STATUS_WORK_IN_PROGRESS : ClientRequest::STATUS_UNDER_REVIEW;
        }
        if (in_array($value, ['work in progress', 'wip', 'in progress', 'ongoing'], true)) {
            return $technician && $hasLegacyDescription ? ClientRequest::STATUS_WORK_IN_PROGRESS : ClientRequest::STATUS_UNDER_REVIEW;
        }
        if (in_array($value, ['approved'], true) || $this->isTruthy($taskApproval)) {
            return $technician && $hasLegacyDescription ? ClientRequest::STATUS_WORK_IN_PROGRESS : ClientRequest::STATUS_APPROVED;
        }

        return $technician && $hasLegacyDescription ? ClientRequest::STATUS_WORK_IN_PROGRESS : ClientRequest::STATUS_UNDER_REVIEW;
    }

    private function normalizeUrgency(?string $value): ?int
    {
        $value = Str::lower(trim((string) $value));
        return match ($value) {
            '3', 'high', 'urgent', 'urgently needed' => 3,
            '2', 'medium', 'normal' => 2,
            '1', 'low', 'not urgent' => 1,
            default => null,
        };
    }

    private function durationToSeconds(?string $value): ?int
    {
        $value = trim((string) $value);
        if ($value === '') {
            return null;
        }

        if (is_numeric($value)) {
            return (int) round(((float) $value) * 3600);
        }

        $lower = Str::lower($value);
        $seconds = 0;
        if (preg_match_all('/(\d+(?:\.\d+)?)\s*(days?|d|hours?|hrs?|h|minutes?|mins?|m|seconds?|secs?|s)/i', $lower, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                $number = (float) $match[1];
                $unit = Str::lower($match[2]);
                if (in_array($unit, ['day', 'days', 'd'], true)) {
                    $seconds += (int) round($number * 86400);
                } elseif (in_array($unit, ['hour', 'hours', 'hr', 'hrs', 'h'], true)) {
                    $seconds += (int) round($number * 3600);
                } elseif (in_array($unit, ['minute', 'minutes', 'min', 'mins', 'm'], true)) {
                    $seconds += (int) round($number * 60);
                } else {
                    $seconds += (int) round($number);
                }
            }
            return $seconds;
        }

        if (preg_match('/^(\d{1,2}):(\d{2})(?::(\d{2}))?$/', $value, $match)) {
            return ((int) $match[1] * 3600) + ((int) $match[2] * 60) + (int) ($match[3] ?? 0);
        }

        return null;
    }

    private function moneyValue(?string $value): ?float
    {
        $value = trim((string) $value);
        if ($value === '') {
            return null;
        }
        $clean = preg_replace('/[^0-9.\-]/', '', $value);
        return is_numeric($clean) ? (float) $clean : null;
    }

    private function parseDate(?string $value): ?Carbon
    {
        if (!filled($value)) {
            return null;
        }
        foreach (['Y-m-d H:i:s', 'Y-m-d H:i', 'd/m/Y H:i:s', 'd/m/Y H:i', 'j/n/Y H:i', 'd/m/Y', 'j/n/Y', 'm/d/Y H:i', 'm/d/Y'] as $format) {
            try {
                return Carbon::createFromFormat($format, $value, 'Asia/Kuala_Lumpur');
            } catch (\Throwable $e) {}
        }
        return Carbon::parse($value, 'Asia/Kuala_Lumpur');
    }

    private function value(array $row, array $keys): ?string
    {
        foreach ($keys as $key) {
            if (array_key_exists($key, $row) && filled($row[$key])) {
                return trim((string) $row[$key]);
            }
        }
        return null;
    }

    private function requiredAny(array $row, array $keys, string $label): string
    {
        $value = $this->value($row, $keys);
        if (!filled($value)) {
            throw new \RuntimeException("Missing required column value: {$label}");
        }
        return $value;
    }

    private function fallbackFullName(array $row, string $locationName, ?string $email): string
    {
        return $this->value($row, ['full_name', 'name', 'client_name'])
            ?: ($email ?: $locationName);
    }

    private function isTruthy(?string $value): bool
    {
        $value = Str::lower(trim((string) $value));
        return in_array($value, ['yes', 'y', 'true', '1', 'approved', 'approve'], true);
    }
}
