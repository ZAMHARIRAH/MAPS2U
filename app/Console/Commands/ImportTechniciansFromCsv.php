<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class ImportTechniciansFromCsv extends Command
{
    protected $signature = 'import:technicians-csv
        {--file= : Absolute or relative path to CSV file}
        {--skip-existing : Skip updating technician if email already exists}
        {--dry-run : Preview import without saving to DB}
    ';

    protected $description = 'Import technician accounts from CSV into the users table';

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

            $name = trim((string)($row['name'] ?? $row['technician_name'] ?? $row['technician'] ?? ''));
            $email = strtolower(trim((string)($row['email'] ?? $row['technician_email'] ?? '')));
            $phone = $this->normalizePhone($row['phone_number'] ?? $row['phone'] ?? $row['contact_number'] ?? null);
            $address = trim((string)($row['address'] ?? ''));
            $password = trim((string)($row['password'] ?? ''));

            if (!$email || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $this->warn("Row {$rowNum} skipped (invalid email): " . json_encode($row));
                $skipped++;
                continue;
            }

            try {
                $existingUser = User::where('email', $email)->first();

                if ($existingUser) {
                    if ($skipExisting) {
                        $this->line("⏭️ Row {$rowNum}: {$email} already exists, skipped");
                        $skipped++;
                        continue;
                    }

                    if (!$dryRun) {
                        $updateData = [
                            'name' => $name ?: $existingUser->name,
                            'phone_number' => $phone ?: $existingUser->phone_number,
                            'address' => $address !== '' ? $address : $existingUser->address,
                            'role' => User::ROLE_TECHNICIAN,
                            'sub_role' => 'technician',
                        ];

                        if ($password !== '') {
                            $updateData['password'] = Hash::make($password);
                        }

                        $existingUser->update($updateData);
                    }

                    $ok++;
                    $this->line("🔄 Row {$rowNum}: {$email} would be updated as technician" . ($dryRun ? ' (dry-run)' : ''));
                    continue;
                }

                $userData = [
                    'name' => $name ?: $email,
                    'email' => $email,
                    'email_verified_at' => now(),
                    'phone_number' => $phone,
                    'address' => $address !== '' ? $address : null,
                    'role' => User::ROLE_TECHNICIAN,
                    'sub_role' => 'technician',
                    'profile_photo_path' => null,
                    'password' => Hash::make($password !== '' ? $password : Str::random(24)),
                ];

                if (!$dryRun) {
                    $user = User::create($userData);
                    $this->line("✅ Row {$rowNum}: {$email} -> technician_id={$user->id}");
                } else {
                    $this->line("🧪 Row {$rowNum}: {$email} would be created as technician");
                }

                $ok++;
            } catch (\Throwable $e) {
                $failed++;
                $this->error("❌ Row {$rowNum} failed for {$email}: " . $e->getMessage());
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

    private function normalizePhone($value): ?string
    {
        if ($value === null) {
            return null;
        }

        $s = trim((string) $value);

        if ($s === '' || strtolower($s) === 'nan' || $s === '-') {
            return null;
        }

        $digits = preg_replace('/\D+/', '', $s);

        if (!$digits) {
            return null;
        }

        if (strlen($digits) === 9 && str_starts_with($digits, '1')) {
            $digits = '0' . $digits;
        }

        return $digits;
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
