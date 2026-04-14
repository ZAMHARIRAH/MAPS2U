<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ClientRequest;
use App\Models\Location;
use App\Models\RequestType;
use App\Models\TaskTitle;
use App\Models\User;
use App\Models\RequestQuestion;
use App\Models\ReportArchive;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ReportController extends Controller
{
    public function jobRequests(Request $request)
    {
        $admin = Auth::user();
        $query = ClientRequest::with(['user', 'location', 'department', 'requestType'])
            ->whereHas('user', fn ($q) => $q->whereIn('sub_role', $admin->handledClientRoles()));

        if ($request->filled('location_id')) {
            $query->where('location_id', $request->integer('location_id'));
        }
        if ($request->filled('client_name')) {
            $query->where('full_name', 'like', '%'.$request->string('client_name')->toString().'%');
        }
        if ($request->filled('status')) {
            $status = (string) $request->input('status');

            if ($status === ClientRequest::STATUS_COMPLETED) {
                $query->whereNotNull('finance_completed_at');
            } elseif ($status === ClientRequest::STATUS_FINANCE_PENDING) {
                $query->whereNotNull('technician_completed_at')
                    ->whereNull('finance_completed_at')
                    ->whereNotNull('approved_quotation_index');
            } else {
                $query->where('status', $status);
            }
        }

        if ($request->filled('date_from')) {
            $query->whereDate('created_at', '>=', $request->input('date_from'));
        }
        if ($request->filled('date_to')) {
            $query->whereDate('created_at', '<=', $request->input('date_to'));
        }

        $items = $query->latest()->get();
        $clientRole = $admin->primaryHandledClientRole();
        $locationType = $clientRole === User::CLIENT_HQ ? Location::TYPE_HQ : Location::TYPE_BRANCH;

        return view('admin.reports.job-requests', [
            'items' => $items,
            'locations' => Location::where('type', $locationType)->orderBy('name')->get(),
            'clientRole' => $clientRole,
            'filters' => $request->all(),
        ]);
    }

    public function locationStatistics(Request $request)
    {
        $admin = Auth::user();
        $items = $this->buildStatisticsBaseQuery($request, $admin, Location::TYPE_HQ)->get();

        return view('admin.reports.analytics-dashboard', $this->buildAnalyticsViewData(
            request: $request,
            items: $items,
            locations: Location::where('type', Location::TYPE_HQ)->where('is_active', true)->orderBy('name')->get(),
            selectedYear: (int) ($request->input('year') ?: now()->year),
            title: 'Locations',
            entityLabel: 'Location',
            routeName: 'admin.reports.locations',
        ));
    }

    public function branchStatistics(Request $request)
    {
        $admin = Auth::user();
        $items = $this->buildStatisticsBaseQuery($request, $admin, Location::TYPE_BRANCH)->get();

        return view('admin.reports.analytics-dashboard', $this->buildAnalyticsViewData(
            request: $request,
            items: $items,
            locations: Location::where('type', Location::TYPE_BRANCH)->where('is_active', true)->orderBy('name')->get(),
            selectedYear: (int) ($request->input('year') ?: now()->year),
            title: 'Branches',
            entityLabel: 'Branch',
            routeName: 'admin.reports.branches',
        ));
    }


    public function branchArchiveIndex()
    {
        return redirect()->route('admin.reports.branches');
    }

    public function branchArchiveShow(int $year)
    {
        $archive = ReportArchive::where('report_type', ReportArchive::TYPE_BRANCHES)
            ->where('archive_year', $year)
            ->firstOrFail();

        $payload = $this->normalizeArchivePayload($archive, 'Branches', 'Branch', 'admin.reports.branches.archive.show');
        return request()->boolean('print') ? view('admin.reports.analytics-documents-print', $payload) : view('admin.reports.analytics-dashboard', $payload);
    }

    public function locationArchiveIndex()
    {
        return redirect()->route('admin.reports.locations');
    }

    public function locationArchiveShow(int $year)
    {
        $archive = ReportArchive::where('report_type', ReportArchive::TYPE_LOCATIONS)
            ->where('archive_year', $year)
            ->firstOrFail();

        $payload = $this->normalizeArchivePayload($archive, 'Locations', 'Location', 'admin.reports.locations.archive.show');
        return request()->boolean('print') ? view('admin.reports.analytics-documents-print', $payload) : view('admin.reports.analytics-dashboard', $payload);
    }

    public function technicians(Request $request)
    {
        $items = $this->buildTechnicianReportQuery($request)->latest()->get();
        $reportItems = $items->filter(fn (ClientRequest $item) => (bool) $item->technician_completed_at && !empty($item->customer_service_report))->values();

        return view('admin.reports.technicians', [
            'items' => $items,
            'reportItems' => $reportItems,
            'technicians' => User::where('role', User::ROLE_TECHNICIAN)->orderBy('name')->get(),
            'filters' => $request->all(),
        ]);
    }

    public function mergedTechnicianDocuments(Request $request)
    {
        $items = $this->buildTechnicianReportQuery($request)
            ->latest()
            ->get()
            ->filter(fn (ClientRequest $item) => (bool) $item->technician_completed_at && !empty($item->customer_service_report))
            ->values();

        return view('admin.reports.technician-merged', [
            'items' => $items,
            'printMode' => (bool) $request->boolean('print'),
            'filters' => $request->all(),
            'feedbackSections' => $this->feedbackSections(),
        ]);
    }

    public function technicianJobReport(Request $request, ClientRequest $clientRequest)
    {
        $job = $this->loadReportRequest($clientRequest);
        abort_unless(!empty($job->customer_service_report), 404);

        return view('technician.requests.report', [
            'job' => $job,
            'printMode' => (bool) $request->boolean('print'),
            'backRoute' => route('admin.reports.technician'),
            'printRoute' => route('admin.reports.technician.job-report', [$job, 'print' => 1]),
        ]);
    }

    public function clientFeedbackReport(Request $request, ClientRequest $clientRequest)
    {
        $job = $this->loadReportRequest($clientRequest);
        abort_unless(!empty($job->feedback), 404);

        return view('admin.reports.client-feedback', [
            'job' => $job,
            'printMode' => (bool) $request->boolean('print'),
            'backRoute' => route('admin.reports.technician'),
            'printRoute' => route('admin.reports.technician.feedback-report', [$job, 'print' => 1]),
            'feedbackSections' => $this->feedbackSections(),
        ]);
    }


    private function buildTechnicianReportQuery(Request $request)
    {
        $query = ClientRequest::with(['assignedTechnician', 'requestType', 'location', 'department', 'relatedRequest'])
            ->whereNotNull('assigned_technician_id');

        if ($request->filled('technician_id')) {
            $query->where('assigned_technician_id', $request->integer('technician_id'));
        }
        if ($request->filled('month')) {
            [$year, $month] = explode('-', $request->input('month'));
            $query->whereYear('created_at', (int) $year)->whereMonth('created_at', (int) $month);
        }
        if ($request->filled('task')) {
            $query->whereHas('requestType', fn ($q) => $q->where('name', 'like', '%'.$request->string('task')->toString().'%'));
        }

        return $query;
    }

    private function loadReportRequest(ClientRequest $clientRequest): ClientRequest
    {
        return ClientRequest::with([
            'assignedTechnician',
            'requestType.questions.options',
            'location',
            'department',
            'relatedRequest',
            'user',
        ])->findOrFail($clientRequest->id);
    }



    private function buildStatisticsBaseQuery(Request $request, User $admin, string $locationType)
    {
        $query = ClientRequest::with(['user', 'location', 'department', 'requestType.questions', 'assignedTechnician'])
            ->whereHas('location', fn ($q) => $q->where('type', $locationType));

        if ($request->filled('location_id')) {
            $query->where('location_id', $request->integer('location_id'));
        }
        if ($request->filled('state')) {
            $state = $request->string('state')->toString();
            $query->whereHas('location', fn ($q) => $q->where('state', $state));
        }
        if ($request->filled('status')) {
            $status = (string) $request->input('status');

            if ($status === ClientRequest::STATUS_COMPLETED) {
                $query->whereNotNull('finance_completed_at');
            } elseif ($status === ClientRequest::STATUS_FINANCE_PENDING) {
                $query->whereNotNull('technician_completed_at')
                    ->whereNull('finance_completed_at')
                    ->whereNotNull('approved_quotation_index');
            } else {
                $query->where('status', $status);
            }
        }

        if ($request->filled('date_from')) {
            $query->whereDate('created_at', '>=', $request->input('date_from'));
        }
        if ($request->filled('date_to')) {
            $query->whereDate('created_at', '<=', $request->input('date_to'));
        }

        return $query;
    }

    private function buildStatisticsRows(Collection $items, string $locationType, Collection $requestTypes): Collection
    {
        $grouped = $items->groupBy('location_id');

        return $grouped->map(function (Collection $group) use ($requestTypes) {
            $location = $group->first()?->location;
            $taskCounts = [];
            $taskCosts = [];

            foreach ($requestTypes as $requestType) {
                $matching = $group->filter(fn (ClientRequest $item) => (int) $item->request_type_id === (int) $requestType->id);
                $taskCounts[$requestType->id] = $matching->count();
                $taskCosts[$requestType->id] = round($matching->sum(function (ClientRequest $item) {
                    $amount = data_get($item->approvedQuotation(), 'amount');
                    return is_numeric($amount) ? (float) $amount : 0;
                }), 2);
            }

            $durationHours = round($group->sum(function (ClientRequest $item) {
                $started = $item->technicianLogStartedAt();
                if (!$started || !$item->technician_completed_at) {
                    return 0;
                }

                return max(0, $started->diffInMinutes($item->technician_completed_at->copy()->timezone('Asia/Kuala_Lumpur')) / 60);
            }), 2);

            $totalCost = round($group->sum(function (ClientRequest $item) {
                $amount = data_get($item->approvedQuotation(), 'amount');
                return is_numeric($amount) ? (float) $amount : 0;
            }), 2);

            return [
                'location' => $location,
                'total_jobs' => $group->count(),
                'task_counts' => $taskCounts,
                'task_costs' => $taskCosts,
                'total_cost' => $totalCost,
                'duration_hours' => $durationHours,
                'jobs' => $group->sortByDesc('created_at')->values(),
            ];
        })->sortBy(fn ($row) => strtolower($row['location']?->name ?? ''))->values();
    }


    private function requestTaskTitles(ClientRequest $item): array
    {
        $questions = $item->requestType?->questions ?? collect();
        return collect($questions)
            ->filter(fn ($question) => $question->question_type === RequestQuestion::TYPE_TASK_TITLE)
            ->map(function ($question) use ($item) {
                return $this->taskAnswerDisplayValue(data_get($item->answers, $question->id));
            })
            ->filter()
            ->values()
            ->all();
    }

    private function approvedAmount(ClientRequest $item): float
    {
        $amount = data_get($item->approvedQuotation(), 'amount');
        return is_numeric($amount) ? (float) $amount : 0.0;
    }

    private function durationHours(ClientRequest $item): float
    {
        return $item->reportDurationHours();
    }

    private function buildMonthlyTaskSummaryFromNames(Collection $items, Collection $taskNames, int $year): Collection
    {
        return $taskNames->map(function (string $taskName) use ($items, $year) {
            $months = collect(range(1, 12))->mapWithKeys(function (int $month) use ($items, $taskName, $year) {
                $count = $items->filter(function (ClientRequest $item) use ($taskName, $month, $year) {
                    return (int) $item->created_at->format('Y') === $year
                        && (int) $item->created_at->format('n') === $month
                        && in_array($taskName, $this->requestTaskTitles($item), true);
                })->count();
                return [$month => $count];
            });

            return [
                'task_name' => $taskName,
                'months' => $months,
                'total' => $months->sum(),
            ];
        })->filter(fn ($row) => $row['total'] > 0)->values();
    }

    private function buildMonthlyEntitySummary(Collection $items, Collection $locations, int $year): Collection
    {
        return $locations->map(function (Location $branch) use ($items, $year) {
            $months = collect(range(1, 12))->mapWithKeys(function (int $month) use ($items, $branch, $year) {
                $count = $items->filter(fn (ClientRequest $item) => (int) $item->location_id === (int) $branch->id && (int) $item->created_at->format('Y') === $year && (int) $item->created_at->format('n') === $month)->count();
                return [$month => $count];
            });

            return [
                'branch' => $branch,
                'months' => $months,
                'total' => $months->sum(),
            ];
        })->filter(fn ($row) => $row['total'] > 0)->values();
    }

    private function buildEntityDetailDataset(Collection $items, ?int $entityId, ?string $taskFilter): array
    {
        $branchItems = $items;
        $selectedBranch = null;
        if ($entityId) {
            $branchItems = $branchItems->where('location_id', $entityId)->values();
            $selectedBranch = $branchItems->first()?->location;
        }

        if ($taskFilter) {
            $branchItems = $branchItems->filter(fn (ClientRequest $item) => in_array($taskFilter, $this->requestTaskTitles($item), true))->values();
        }

        $taskNames = $branchItems->flatMap(fn (ClientRequest $item) => $this->requestTaskTitles($item))->filter()->unique()->sort()->values();
        $rows = $taskNames->map(function (string $taskName) use ($branchItems) {
            $matching = $branchItems->filter(fn (ClientRequest $item) => in_array($taskName, $this->requestTaskTitles($item), true));
            return [
                'task' => $taskName,
                'total_job' => $matching->count(),
                'total_hours' => round($matching->sum(fn (ClientRequest $item) => $this->durationHours($item)), 2),
                'total_cost' => round($matching->sum(fn (ClientRequest $item) => $this->approvedAmount($item)), 2),
                'total_per_task' => round($matching->sum(fn (ClientRequest $item) => $this->approvedAmount($item)), 2),
            ];
        })->values();

        return [
            'selectedBranch' => $selectedBranch,
            'selectedTask' => $taskFilter,
            'rows' => $rows,
            'jobs' => $branchItems->sortByDesc('created_at')->values(),
            'summary' => [
                'total_jobs' => $branchItems->count(),
                'total_hours' => round($branchItems->sum(fn (ClientRequest $item) => $this->durationHours($item)), 2),
                'total_cost' => round($branchItems->sum(fn (ClientRequest $item) => $this->approvedAmount($item)), 2),
            ],
        ];
    }

    private function buildCombinedEntityStatistics(Collection $items, Collection $locations, Collection $taskNames, int $year): array
    {
        $taskBranchMatrix = $taskNames->map(function (string $taskName) use ($locations, $items) {
            return [
                'task' => $taskName,
                'entities' => $locations->mapWithKeys(function (Location $branch) use ($taskName, $items) {
                    $count = $items->filter(fn (ClientRequest $item) => (int) $item->location_id === (int) $branch->id && in_array($taskName, $this->requestTaskTitles($item), true))->count();
                    return [$branch->name => $count];
                }),
            ];
        })->filter(fn ($row) => collect($row['entities'])->sum() > 0)->values();

        $branchPerformance = $locations->map(function (Location $branch) use ($items) {
            $matching = $items->where('location_id', $branch->id);
            return [
                'entity' => $branch,
                'total_jobs' => $matching->count(),
                'total_hours' => round($matching->sum(fn (ClientRequest $item) => $this->durationHours($item)), 2),
                'total_cost' => round($matching->sum(fn (ClientRequest $item) => $this->approvedAmount($item)), 2),
            ];
        })->filter(fn ($row) => $row['total_jobs'] > 0)->sortByDesc('total_jobs')->values();

        $taskTrend = $taskNames->map(function (string $taskName) use ($items, $year) {
            $series = collect(range(1, 12))->mapWithKeys(function (int $month) use ($items, $taskName, $year) {
                $count = $items->filter(fn (ClientRequest $item) => (int) $item->created_at->format('Y') === $year && (int) $item->created_at->format('n') === $month && in_array($taskName, $this->requestTaskTitles($item), true))->count();
                return [Carbon::create(null, $month, 1)->format('M') => $count];
            });
            return ['task' => $taskName, 'series' => $series, 'total' => $series->sum()];
        })->filter(fn ($row) => $row['total'] > 0)->values();

        return [
            'taskBranchMatrix' => $taskBranchMatrix,
            'branchPerformance' => $branchPerformance,
            'taskTrend' => $taskTrend,
            'branchMonthMatrix' => $this->buildMonthEntityMatrix($items, $locations, $year),
            'taskMonthMatrix' => $this->buildMonthTaskMatrix($items, $taskNames, $year),
        ];
    }



    public function locationDocumentsPrint(Request $request)
    {
        $admin = Auth::user();
        $items = $this->buildStatisticsBaseQuery($request, $admin, Location::TYPE_HQ)->get();

        return view('admin.reports.analytics-documents-print', $this->buildAnalyticsViewData(
            request: $request,
            items: $items,
            locations: Location::where('type', Location::TYPE_HQ)->where('is_active', true)->orderBy('name')->get(),
            selectedYear: (int) ($request->input('year') ?: now()->year),
            title: 'Locations',
            entityLabel: 'Location',
            routeName: 'admin.reports.locations',
        ));
    }

    public function branchDocumentsPrint(Request $request)
    {
        $admin = Auth::user();
        $items = $this->buildStatisticsBaseQuery($request, $admin, Location::TYPE_BRANCH)->get();

        return view('admin.reports.analytics-documents-print', $this->buildAnalyticsViewData(
            request: $request,
            items: $items,
            locations: Location::where('type', Location::TYPE_BRANCH)->where('is_active', true)->orderBy('name')->get(),
            selectedYear: (int) ($request->input('year') ?: now()->year),
            title: 'Branches',
            entityLabel: 'Branch',
            routeName: 'admin.reports.branches',
        ));
    }

    private function buildMonthEntityMatrix(Collection $items, Collection $locations, int $year): Collection
    {
        return $locations->map(function (Location $entity) use ($items, $year) {
            $months = collect(range(1, 12))->mapWithKeys(function (int $month) use ($items, $entity, $year) {
                $matching = $items->filter(fn (ClientRequest $item) => (int) $item->location_id === (int) $entity->id && (int) $item->created_at->format('Y') === $year && (int) $item->created_at->format('n') === $month);
                return [$month => $matching->count()];
            });

            return [
                'entity' => $entity,
                'months' => $months,
                'total' => $months->sum(),
            ];
        })->filter(fn ($row) => $row['total'] > 0)->values();
    }

    private function buildMonthTaskMatrix(Collection $items, Collection $taskNames, int $year): Collection
    {
        return $taskNames->map(function (string $taskName) use ($items, $year) {
            $months = collect(range(1, 12))->mapWithKeys(function (int $month) use ($items, $taskName, $year) {
                $matching = $items->filter(fn (ClientRequest $item) => (int) $item->created_at->format('Y') === $year && (int) $item->created_at->format('n') === $month && in_array($taskName, $this->requestTaskTitles($item), true));
                return [$month => $matching->count()];
            });

            return [
                'task' => $taskName,
                'months' => $months,
                'total' => $months->sum(),
            ];
        })->filter(fn ($row) => $row['total'] > 0)->values();
    }

    private function buildOverviewMetrics(Collection $items, Collection $locations, Collection $taskNames): array
    {
        $totalJobs = $items->count();
        $totalHours = round($items->sum(fn (ClientRequest $item) => $this->durationHours($item)), 2);
        $totalCost = round($items->sum(fn (ClientRequest $item) => $this->approvedAmount($item)), 2);
        $completedItems = $items->filter(fn (ClientRequest $item) => (bool) $item->finance_completed_at)->values();
        $completedCount = max(1, $completedItems->count());

        return [
            'total_jobs' => $totalJobs,
            'completed_jobs' => $completedItems->count(),
            'average_jobs_per_entity' => round($locations->count() ? $totalJobs / max(1, $locations->count()) : 0, 2),
            'average_jobs_per_task' => round($taskNames->count() ? $totalJobs / max(1, $taskNames->count()) : 0, 2),
            'average_cost_per_completed_job' => round($totalCost / $completedCount, 2),
            'average_hours_per_completed_job' => round($totalHours / $completedCount, 2),
            'total_cost' => $totalCost,
            'total_hours' => $totalHours,
        ];
    }

    private function buildAnalyticsViewData(Request $request, Collection $items, Collection $locations, int $selectedYear, string $title, string $entityLabel, string $routeName): array
    {
        $taskNames = $this->allTaskNamesFromItems($items);
        $months = collect(range(1, 12))->mapWithKeys(fn ($month) => [$month => Carbon::create(null, $month, 1)->format('M')]);
        $monthlyTaskSummary = $this->buildMonthlyTaskSummaryFromNames($items, $taskNames, $selectedYear);
        $monthlyEntitySummary = $this->buildMonthlyEntitySummary($items, $locations, $selectedYear);
        $selectedEntityId = $request->integer('detail_location_id') ?: $request->integer('location_id');
        $detail = $this->buildEntityDetailDataset($items, $selectedEntityId, $request->input('detail_task'));
        $combined = $this->buildCombinedEntityStatistics($items, $locations, $taskNames, $selectedYear);
        $overviewMetrics = $this->buildOverviewMetrics($items, $locations, $taskNames);
        $printSection = (string) ($request->input('print_section') ?: 'overview');

        $years = $items->map(fn (ClientRequest $item) => (int) $item->created_at->format('Y'))
            ->toBase()
            ->push($selectedYear)
            ->push(now()->year)
            ->unique(fn ($year) => (int) $year)
            ->sortDesc()
            ->values();

        return [
            'items' => $items,
            'locations' => $locations,
            'taskNames' => $taskNames,
            'months' => $months,
            'selectedYear' => $selectedYear,
            'filters' => $request->all(),
            'availableStates' => Location::stateOptions(),
            'monthlyTaskSummary' => $monthlyTaskSummary,
            'monthlyEntitySummary' => $monthlyEntitySummary,
            'detail' => $detail,
            'combined' => $combined,
            'overviewMetrics' => $overviewMetrics,
            'printMode' => (bool) $request->boolean('print'),
            'printSection' => $printSection,
            'title' => $title,
            'entityLabel' => $entityLabel,
            'entityPlural' => $title,
            'reportRouteName' => $routeName,
            'statusOptions' => ClientRequest::adminVisibleStatusOptions(),
            'statisticsPrintRoute' => route($routeName, array_merge($request->all(), ['print' => 1])),
            'documentPrintBaseRoute' => route($routeName === 'admin.reports.locations' ? 'admin.reports.locations.documents' : 'admin.reports.branches.documents'),
            'availableYears' => $years,
            'compiledArchiveYears' => ReportArchive::where('report_type', $entityLabel === 'Branch' ? ReportArchive::TYPE_BRANCHES : ReportArchive::TYPE_LOCATIONS)->orderByDesc('archive_year')->pluck('archive_year'),
            'isArchiveView' => false,
            'archiveMeta' => null,
            'selectedEntityName' => $locations->firstWhere('id', $selectedEntityId)?->name,
            'selectedState' => $request->input('state'),
        ];
    }

    private function allTaskNamesFromItems(Collection $items): Collection
    {
        $taskNames = collect();

        foreach ($items as $item) {
            foreach ($this->requestTaskTitles($item) as $name) {
                $taskNames->push($name);
            }
        }

        foreach (TaskTitle::orderBy('title')->pluck('title') as $title) {
            $taskNames->push($title);
        }

        return $taskNames->filter()->map(fn ($name) => trim((string) $name))->filter()->unique()->sort()->values();
    }

    private function taskAnswerDisplayValue($answer): ?string
    {
        $value = data_get($answer, 'value');
        $label = data_get($answer, 'label');
        if (filled($label)) {
            return trim((string) $label);
        }
        if (is_numeric($value)) {
            $task = TaskTitle::find((int) $value);
            if ($task) {
                return $task->title;
            }
        }
        return filled($value) ? trim((string) $value) : null;
    }


    private function normalizeArchivePayload(ReportArchive $archive, string $title, string $entityLabel, string $routeName): array
    {
        $payload = $archive->payload ?? [];
        $months = collect($payload['months'] ?? collect(range(1, 12))->mapWithKeys(fn ($month) => [$month => Carbon::create(null, $month, 1)->format('M')])->all());
        $detail = $payload['detail'] ?? ['rows' => [], 'jobs' => [], 'summary' => ['total_jobs' => 0, 'total_hours' => 0, 'total_cost' => 0], 'selectedBranch' => null, 'selectedTask' => null];
        $combined = $payload['combined'] ?? ['taskBranchMatrix' => [], 'branchPerformance' => [], 'taskTrend' => []];
        $archiveYears = ReportArchive::where('report_type', $archive->report_type)->orderByDesc('archive_year')->pluck('archive_year');

        return [
            'items' => collect(),
            'locations' => collect(),
            'taskNames' => collect($payload['task_names'] ?? []),
            'months' => $months,
            'selectedYear' => (int) ($payload['selected_year'] ?? $archive->archive_year),
            'filters' => ['year' => (int) ($payload['selected_year'] ?? $archive->archive_year)],
            'availableStates' => $payload['available_states'] ?? Location::stateOptions(),
            'monthlyTaskSummary' => collect($payload['monthlyTaskSummary'] ?? []),
            'monthlyEntitySummary' => collect($payload['monthlyEntitySummary'] ?? []),
            'detail' => [
                'rows' => collect($detail['rows'] ?? []),
                'jobs' => collect($detail['jobs'] ?? []),
                'summary' => $detail['summary'] ?? ['total_jobs' => 0, 'total_hours' => 0, 'total_cost' => 0],
                'selectedBranch' => $detail['selectedBranch'] ?? null,
                'selectedTask' => $detail['selectedTask'] ?? null,
            ],
            'combined' => [
                'taskBranchMatrix' => collect($combined['taskBranchMatrix'] ?? []),
                'branchPerformance' => collect($combined['branchPerformance'] ?? []),
                'taskTrend' => collect($combined['taskTrend'] ?? []),
            ],
            'overviewMetrics' => $payload['overviewMetrics'] ?? ['total_jobs' => 0, 'total_hours' => 0, 'total_cost' => 0],
            'printMode' => (bool) request()->boolean('print'),
            'printSection' => (string) (request()->input('print_section') ?: 'overview'),
            'title' => $title,
            'entityLabel' => $entityLabel,
            'entityPlural' => $title,
            'reportRouteName' => $routeName,
            'statusOptions' => ClientRequest::adminVisibleStatusOptions(),
            'documentPrintBaseRoute' => route($routeName, ['year' => (int) ($payload['selected_year'] ?? $archive->archive_year), 'print' => 1]),
            'availableYears' => collect([$archive->archive_year]),
            'compiledArchiveYears' => $archiveYears,
            'isArchiveView' => true,
            'archiveMeta' => ['year' => $archive->archive_year, 'archived_at' => $archive->archived_at],
            'selectedEntityName' => null,
            'selectedState' => null,
        ];
    }

    private function feedbackSections(): array
    {
        return [
            'reliability' => ['title' => 'Section 1 : Reliability', 'questions' => [
                'q1' => 'The department consistently delivers services without significant disruptions.',
                'q2' => 'Processes are reliable and meet expected timelines.',
            ]],
            'accessibility' => ['title' => 'Section 2 : Accessibility', 'questions' => [
                'q1' => "The department's services are easy to access when needed.",
                'q2' => "Information about the department's processes is readily available and clear.",
            ]],
            'responsiveness' => ['title' => 'Section 3 : Responsiveness', 'questions' => [
                'q1' => 'The department responds to service requests promptly.',
                'q2' => 'Follow-up actions are carried out in a timely manner after requests are submitted.',
            ]],
            'effectiveness' => ['title' => 'Section 4 : Effectiveness', 'questions' => [
                'q1' => 'The workflows are efficient and adequately support our team’s operational requirements.',
                'q2' => 'The department’s processes contribute effectively to achieving organizational objectives.',
            ]],
            'communication' => ['title' => 'Section 5 : Quality of Communication', 'questions' => [
                'q1' => 'The department provides regular updates on the status of ongoing services or requests.',
                'q2' => 'Communication from the department is clear, concise, and professional.',
            ]],
            'flexibility' => ['title' => 'Section 6 : Flexibility', 'questions' => [
                'q1' => 'The department is adaptable to changes in priorities or requirements.',
                'q2' => 'Processes can be easily adjusted to address unexpected challenges.',
            ]],
            'support' => ['title' => 'Section 7 : Employee Support', 'questions' => [
                'q1' => 'The department provides adequate guidance and support for understanding workflows.',
                'q2' => 'Feedback provided to the department is acknowledged and acted upon appropriately.',
            ]],
            'satisfaction' => ['title' => 'Section 8 : Overall Satisfaction', 'questions' => [
                'q1' => 'I am satisfied with the overall performance of the infrastructure management department in this year.',
            ]],
        ];
    }
}
