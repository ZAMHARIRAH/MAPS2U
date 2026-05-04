<?php

namespace App\Console\Commands;

use App\Models\Vendor;
use Illuminate\Console\Command;

class ImportVendorsFromCsv extends Command
{
    protected $signature = 'import:vendors-csv
        {--file= : Absolute or relative path to CSV file}
        {--skip-existing : Skip updating vendor if vendor already exists}
        {--dry-run : Preview import without saving to DB}
    ';

    protected $description = 'Import vendors from CSV into the vendors table';

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

            $companyName = trim((string)($row['company_name'] ?? $row['company'] ?? $row['vendor_name'] ?? $row['vendor'] ?? ''));
            $officialEmail = strtolower(trim((string)($row['official_email'] ?? $row['email'] ?? '')));

            if ($companyName === '') {
                $this->warn("Row {$rowNum} skipped (missing company_name): " . json_encode($row));
                $skipped++;
                continue;
            }

            if ($officialEmail !== '' && !filter_var($officialEmail, FILTER_VALIDATE_EMAIL)) {
                $this->warn("Row {$rowNum} skipped (invalid official_email): " . json_encode($row));
                $skipped++;
                continue;
            }

            try {
                $existingVendor = $this->findExistingVendor($companyName, $officialEmail);

                $vendorData = [
                    'company_name' => $companyName,
                    'ssm_number' => $this->cleanNullable($row['ssm_number'] ?? $row['ssm'] ?? null),
                    'office_address' => $this->cleanNullable($row['office_address'] ?? $row['address'] ?? null),
                    'phone_number' => $this->cleanNullable($row['phone_number'] ?? $row['phone'] ?? null),
                    'fax_number' => $this->cleanNullable($row['fax_number'] ?? $row['fax'] ?? null),
                    'official_email' => $officialEmail !== '' ? $officialEmail : null,
                    'contact_person' => $this->cleanNullable($row['contact_person'] ?? $row['pic'] ?? $row['person_in_charge'] ?? null),
                    'bank' => $this->cleanNullable($row['bank'] ?? $row['bank_name'] ?? null),
                    'account_number_for_payment' => $this->cleanNullable($row['account_number_for_payment'] ?? $row['account_number'] ?? $row['bank_account'] ?? null),
                    'document_path' => null,
                    'document_original_name' => null,
                ];

                if ($existingVendor) {
                    if ($skipExisting) {
                        $this->line("⏭️ Row {$rowNum}: {$companyName} already exists, skipped");
                        $skipped++;
                        continue;
                    }

                    if (!$dryRun) {
                        $existingVendor->update($vendorData);
                    }

                    $ok++;
                    $this->line("🔄 Row {$rowNum}: {$companyName} would be updated" . ($dryRun ? ' (dry-run)' : ''));
                    continue;
                }

                if (!$dryRun) {
                    $vendor = Vendor::create($vendorData);
                    $this->line("✅ Row {$rowNum}: {$companyName} -> vendor_id={$vendor->id}");
                } else {
                    $this->line("🧪 Row {$rowNum}: {$companyName} would be created");
                }

                $ok++;
            } catch (\Throwable $e) {
                $failed++;
                $this->error("❌ Row {$rowNum} failed for {$companyName}: " . $e->getMessage());
            }
        }

        $this->info("Done. OK={$ok}, skipped={$skipped}, failed={$failed}");

        return $failed > 0 ? self::FAILURE : self::SUCCESS;
    }

    private function findExistingVendor(string $companyName, string $officialEmail): ?Vendor
    {
        if ($officialEmail !== '') {
            $vendor = Vendor::whereRaw('LOWER(official_email) = ?', [strtolower($officialEmail)])->first();
            if ($vendor) {
                return $vendor;
            }
        }

        return Vendor::whereRaw('LOWER(company_name) = ?', [strtolower($companyName)])->first();
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

    private function cleanNullable($value): ?string
    {
        if ($value === null) {
            return null;
        }

        $s = trim((string) $value);

        if ($s === '' || $s === '-' || strtolower($s) === 'nan') {
            return null;
        }

        return $s;
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
