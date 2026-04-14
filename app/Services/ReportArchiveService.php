<?php

namespace App\Services;

use App\Models\ClientRequest;
use App\Models\Location;
use App\Models\ReportArchive;
use App\Models\TaskTitle;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class ReportArchiveService
{
    public function archiveYear(int $year): array
    {
        $branch = $this->buildArchivePayload(ReportArchive::TYPE_BRANCHES, Location::TYPE_BRANCH, $year, 'Branches', 'Branch');
        $location = $this->buildArchivePayload(ReportArchive::TYPE_LOCATIONS, Location::TYPE_HQ, $year, 'Locations', 'Location');

        return [
            'branches' => $branch,
            'locations' => $location,
        ];
    }

    public function buildArchivePayload(string $reportType, string $locationType, int $year, string $title, string $entityLabel): ReportArchive
    {
        $items = ClientRequest::with(['location', 'requestType.questions', 'assignedTechnician'])
            ->whereHas('location', fn ($q) => $q->where('type', $locationType))
            ->whereYear('created_at', $year)
            ->get();

        $locations = Location::where('type', $locationType)->where('is_active', true)->orderBy('name')->get();
        $taskNames = $this->allTaskNamesFromItems($items);
        $months = collect(range(1, 12))->mapWithKeys(fn ($month) => [$month => Carbon::create(null, $month, 1)->format('M')]);

        $payload = [
            'title' => $title,
            'entity_label' => $entityLabel,
            'selected_year' => $year,
            'archived_at' => now()->toIso8601String(),
            'months' => $months->all(),
            'task_names' => $taskNames->all(),
            'available_states' => Location::stateOptions(),
            'filters' => ['year' => $year],
            'monthlyTaskSummary' => $this->buildMonthlyTaskSummary($items, $taskNames, $year)->values()->all(),
            'monthlyEntitySummary' => $this->buildMonthlyEntitySummary($items, $locations, $year)->values()->all(),
            'overviewMetrics' => $this->buildOverviewMetrics($items, $locations, $taskNames),
            'detail' => $this->buildDetail($items),
            'combined' => $this->buildCombined($items, $locations, $taskNames, $year),
        ];

        return ReportArchive::updateOrCreate(
            ['report_type' => $reportType, 'archive_year' => $year],
            ['payload' => $payload, 'archived_at' => now()]
        );
    }

    private function taskNamesForItem(ClientRequest $item): array
    {
        return $item->selectedTaskTitleNames();
    }

    private function allTaskNamesFromItems(Collection $items): Collection
    {
        $taskNames = collect();
        foreach ($items as $item) {
            foreach ($this->taskNamesForItem($item) as $name) {
                $taskNames->push($name);
            }
        }
        foreach (TaskTitle::orderBy('title')->pluck('title') as $title) {
            $taskNames->push($title);
        }
        return $taskNames->filter()->map(fn ($name) => trim((string) $name))->filter()->unique()->sort()->values();
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

    private function buildMonthlyTaskSummary(Collection $items, Collection $taskNames, int $year): Collection
    {
        return $taskNames->map(function (string $taskName) use ($items, $year) {
            $months = collect(range(1, 12))->mapWithKeys(function (int $month) use ($items, $taskName, $year) {
                $matching = $items->filter(fn (ClientRequest $item) => (int) $item->created_at->format('Y') === $year && (int) $item->created_at->format('n') === $month && in_array($taskName, $this->taskNamesForItem($item), true));
                return [$month => $matching->count()];
            });
            $all = $items->filter(fn (ClientRequest $item) => in_array($taskName, $this->taskNamesForItem($item), true));
            return [
                'task_name' => $taskName,
                'months' => $months->all(),
                'total' => $months->sum(),
                'average_month' => round($months->sum() / 12, 2),
                'total_hours' => round($all->sum(fn (ClientRequest $item) => $this->durationHours($item)), 2),
                'total_cost' => round($all->sum(fn (ClientRequest $item) => $this->approvedAmount($item)), 2),
            ];
        })->filter(fn ($row) => $row['total'] > 0)->values();
    }

    private function buildMonthlyEntitySummary(Collection $items, Collection $locations, int $year): Collection
    {
        return $locations->map(function (Location $location) use ($items, $year) {
            $months = collect(range(1, 12))->mapWithKeys(function (int $month) use ($items, $location, $year) {
                $matching = $items->filter(fn (ClientRequest $item) => (int) $item->location_id === (int) $location->id && (int) $item->created_at->format('Y') === $year && (int) $item->created_at->format('n') === $month);
                return [$month => $matching->count()];
            });
            $all = $items->where('location_id', $location->id);
            return [
                'branch' => ['id' => $location->id, 'name' => $location->name, 'state' => $location->state],
                'months' => $months->all(),
                'total' => $months->sum(),
                'average_month' => round($months->sum() / 12, 2),
                'total_hours' => round($all->sum(fn (ClientRequest $item) => $this->durationHours($item)), 2),
                'total_cost' => round($all->sum(fn (ClientRequest $item) => $this->approvedAmount($item)), 2),
            ];
        })->filter(fn ($row) => $row['total'] > 0)->values();
    }

    private function buildDetail(Collection $items): array
    {
        $taskNames = $this->allTaskNamesFromItems($items);
        $rows = $taskNames->map(function (string $taskName) use ($items) {
            $matching = $items->filter(fn (ClientRequest $item) => in_array($taskName, $this->taskNamesForItem($item), true));
            return [
                'task' => $taskName,
                'total_job' => $matching->count(),
                'total_hours' => round($matching->sum(fn (ClientRequest $item) => $this->durationHours($item)), 2),
                'total_cost' => round($matching->sum(fn (ClientRequest $item) => $this->approvedAmount($item)), 2),
                'total_per_task' => round($matching->sum(fn (ClientRequest $item) => $this->approvedAmount($item)), 2),
            ];
        })->filter(fn ($row) => $row['total_job'] > 0)->values();

        $jobs = $items->sortByDesc('created_at')->map(function (ClientRequest $item) {
            return [
                'request_code' => $item->request_code,
                'location_name' => $item->location?->name,
                'task_title' => $item->primaryTaskTitleName() ?? ($item->requestType?->name ?? '-'),
                'status' => $item->adminWorkflowLabel(),
                'submitted_at' => optional($item->created_at)->timezone('Asia/Kuala_Lumpur')?->format('d M Y h:i A'),
                'completed_at' => $item->finance_completed_at?->copy()->timezone('Asia/Kuala_Lumpur')->format('d M Y h:i A'),
                'cost' => $this->approvedAmount($item),
                'hours' => $this->durationHours($item),
            ];
        })->values()->all();

        return [
            'selectedBranch' => null,
            'selectedTask' => null,
            'rows' => $rows->all(),
            'jobs' => $jobs,
            'summary' => [
                'total_jobs' => $items->count(),
                'total_hours' => round($items->sum(fn (ClientRequest $item) => $this->durationHours($item)), 2),
                'total_cost' => round($items->sum(fn (ClientRequest $item) => $this->approvedAmount($item)), 2),
            ],
        ];
    }

    private function buildCombined(Collection $items, Collection $locations, Collection $taskNames, int $year): array
    {
        $taskBranchMatrix = $taskNames->map(function (string $taskName) use ($locations, $items) {
            $entities = [];
            foreach ($locations as $location) {
                $entities[$location->name] = $items->filter(fn (ClientRequest $item) => (int) $item->location_id === (int) $location->id && in_array($taskName, $this->taskNamesForItem($item), true))->count();
            }
            return ['task' => $taskName, 'entities' => $entities];
        })->filter(fn ($row) => collect($row['entities'])->sum() > 0)->values();

        $branchPerformance = $locations->map(function (Location $location) use ($items) {
            $matching = $items->where('location_id', $location->id);
            return [
                'entity' => ['id' => $location->id, 'name' => $location->name],
                'total_jobs' => $matching->count(),
                'total_hours' => round($matching->sum(fn (ClientRequest $item) => $this->durationHours($item)), 2),
                'total_cost' => round($matching->sum(fn (ClientRequest $item) => $this->approvedAmount($item)), 2),
                'average_jobs' => round($matching->count() / 12, 2),
            ];
        })->filter(fn ($row) => $row['total_jobs'] > 0)->sortByDesc('total_jobs')->values();

        $taskTrend = $taskNames->map(function (string $taskName) use ($items, $year) {
            $series = collect(range(1, 12))->mapWithKeys(function (int $month) use ($items, $taskName, $year) {
                $count = $items->filter(fn (ClientRequest $item) => (int) $item->created_at->format('Y') === $year && (int) $item->created_at->format('n') === $month && in_array($taskName, $this->taskNamesForItem($item), true))->count();
                return [Carbon::create(null, $month, 1)->format('M') => $count];
            });
            return ['task' => $taskName, 'series' => $series->all(), 'total' => $series->sum()];
        })->filter(fn ($row) => $row['total'] > 0)->values();

        return [
            'taskBranchMatrix' => $taskBranchMatrix->all(),
            'branchPerformance' => $branchPerformance->all(),
            'taskTrend' => $taskTrend->all(),
        ];
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
}
