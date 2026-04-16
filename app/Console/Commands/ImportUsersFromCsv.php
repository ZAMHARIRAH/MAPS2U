<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class ImportUsersFromCsv extends Command
{
    protected $signature = 'import:users-csv
        {--file= : Absolute or relative path to CSV file}
        {--skip-existing : Skip creating user if email already exists}
        {--dry-run : Preview import without saving to DB}
    ';

    protected $description = 'Import users from CSV into the users table';

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

            $name  = trim((string)($row['name'] ?? ''));
            $email = strtolower(trim((string)($row['email'] ?? '')));
            $phone = $this->normalizePhone($row['phone_number'] ?? $row['phone'] ?? null);
            $address = trim((string)($row['address'] ?? ''));
            $role = trim((string)($row['role'] ?? 'user'));
            $subRole = trim((string)($row['sub_role'] ?? ''));

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
                        $existingUser->update([
                            'name' => $name ?: $existingUser->name,
                            'phone_number' => $phone ?: $existingUser->phone_number,
                            'address' => $address !== '' ? $address : $existingUser->address,
                            'role' => $role ?: $existingUser->role,
                            'sub_role' => $subRole !== '' ? $subRole : $existingUser->sub_role,
                        ]);
                    }

                    $ok++;
                    $this->line("🔄 Row {$rowNum}: {$email} updated");
                    continue;
                }

                $userData = [
                    'name' => $name ?: $email,
                    'email' => $email,
                    'email_verified_at' => now(),
                    'phone_number' => $phone,
                    'address' => $address !== '' ? $address : null,
                    'role' => $role ?: 'user',
                    'sub_role' => $subRole !== '' ? $subRole : null,
                    'profile_photo_path' => null,
                    'password' => Hash::make(Str::random(24)),
                ];

                if (!$dryRun) {
                    $user = User::create($userData);
                    $this->line("✅ Row {$rowNum}: {$email} -> user_id={$user->id}");
                } else {
                    $this->line("🧪 Row {$rowNum}: {$email} would be created");
                }

                $ok++;
            } catch (\Throwable $e) {
                $failed++;
                $this->error("❌ Row {$rowNum} failed for {$email}: " . $e->getMessage());
            }
        }

        $this->info("Done. OK={$ok}, skipped={$skipped}, failed={$failed}");

        return self::SUCCESS;
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

        $header = array_map(fn ($h) => strtolower(trim((string) $h)), $header);

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

        if ($s === '' || strtolower($s) === 'nan') {
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