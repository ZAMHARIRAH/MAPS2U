<?php

namespace App\Console\Commands;

use App\Models\Location;
use Illuminate\Console\Command;

class ImportBranchesFromCsv extends Command
{
    protected $signature = 'import:branches-csv
        {--file= : Absolute or relative path to CSV file}
        {--skip-existing : Skip updating branch if branch name already exists}
        {--dry-run : Preview import without saving to DB}
    ';

    protected $description = 'Import branch locations from CSV into the locations table';

    public function handle(): int
    {
        $fileInput = (string) $this->option('file');

        if (!$fileInput) {
            $this->error('Missing --file option.');
            return self::FAILURE;
        }

        $file = $this->resolvePath($fileInput);

        if (!file_exists($file)) {
            $this->error("CSV file not found: {$file}");
            return self::FAILURE;
        }

        $rows = $this->readCsv($file);
        $dryRun = (bool) $this->option('dry-run');
        $skipExisting = (bool) $this->option('skip-existing');

        $ok = 0;
        $skipped = 0;
        $failed = 0;

        foreach ($rows as $i => $row) {
            $rowNum = $i + 2;

            $name = trim((string)($row['name'] ?? $row['branch_name'] ?? $row['branch'] ?? $row['location'] ?? ''));
            $address = trim((string)($row['address'] ?? $row['branch_address'] ?? ''));
            $isActive = $this->toBoolean($row['is_active'] ?? $row['active'] ?? '1');

            if ($name === '') {
                $this->warn("Row {$rowNum} skipped (missing branch name): " . json_encode($row));
                $skipped++;
                continue;
            }

            try {
                $existingBranch = Location::where('type', Location::TYPE_BRANCH)
                    ->whereRaw('LOWER(name) = ?', [strtolower($name)])
                    ->first();

                if ($existingBranch) {
                    if ($skipExisting) {
                        $this->line("⏭️ Row {$rowNum}: {$name} already exists, skipped");
                        $skipped++;
                        continue;
                    }

                    if (!$dryRun) {
                        $existingBranch->update([
                            'name' => $name,
                            'type' => Location::TYPE_BRANCH,
                            'address' => $address !== '' ? $address : $existingBranch->address,
                            'is_active' => $isActive,
                        ]);
                    }

                    $ok++;
                    $this->line("🔄 Row {$rowNum}: {$name} would be updated" . ($dryRun ? ' (dry-run)' : ''));
                    continue;
                }

                $branchData = [
                    'name' => $name,
                    'type' => Location::TYPE_BRANCH,
                    'address' => $address !== '' ? $address : null,
                    'is_active' => $isActive,
                ];

                if (!$dryRun) {
                    $branch = Location::create($branchData);
                    $this->line("✅ Row {$rowNum}: {$name} -> branch_id={$branch->id}");
                } else {
                    $this->line("🧪 Row {$rowNum}: {$name} would be created as branch");
                }

                $ok++;
            } catch (\Throwable $e) {
                $failed++;
                $this->error("❌ Row {$rowNum} failed for {$name}: " . $e->getMessage());
            }
        }

        $this->info("Done. OK={$ok}, skipped={$skipped}, failed={$failed}");

        return $failed > 0 ? self::FAILURE : self::SUCCESS;
    }

    private function readCsv(string $file): array
    {
        $handle = fopen($file, 'r');
        if (!$handle) {
            return [];
        }

        $header = fgetcsv($handle);
        if (!$header) {
            fclose($handle);
            return [];
        }

        $header = array_map(function ($h) {
            $h = preg_replace('/^\xEF\xBB\xBF/', '', (string) $h);
            return strtolower(trim($h));
        }, $header);

        $rows = [];

        while (($data = fgetcsv($handle)) !== false) {
            $row = [];
            foreach ($header as $idx => $key) {
                $row[$key] = $data[$idx] ?? null;
            }
            $rows[] = $row;
        }

        fclose($handle);

        return $rows;
    }

    private function toBoolean($value): bool
    {
        $s = strtolower(trim((string) $value));

        if (in_array($s, ['0', 'false', 'no', 'n', 'inactive', 'tidak'], true)) {
            return false;
        }

        return true;
    }

    private function resolvePath(string $fileInput): string
    {
        if (preg_match('/^[A-Z]:\\\\/i', $fileInput)) {
            return $fileInput;
        }

        if (str_starts_with($fileInput, '/') || str_starts_with($fileInput, '\\')) {
            return $fileInput;
        }

        return base_path($fileInput);
    }
}
