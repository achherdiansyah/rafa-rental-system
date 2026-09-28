<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

/**
 * Quick logical DB backup via mysqldump into the local private disk.
 * Restore: mysql -h HOST -P PORT -u USER -p DATABASE < file.sql
 */
class DatabaseBackupCommand extends Command
{
    protected $signature = 'db:backup {--rot=OFF}';

    protected $description = 'Dump the MySQL database into storage/app/backups via mysqldump';

    public function handle(): int
    {
        $connection = config('database.connections.mysql');

        $host = env('DB_HOST', $connection['host'] ?? '127.0.0.1');
        $port = env('DB_PORT', $connection['port'] ?? '3306');
        $db = env('DB_DATABASE', $connection['database'] ?? '');
        $user = env('DB_USERNAME', $connection['username'] ?? '');
        $password = env('DB_PASSWORD', $connection['password'] ?? '');

        if (! $db || ! $user) {
            $this->error('Konfigurasi DB_DATABASE/DB_USERNAME belum lengkap.');

            return self::FAILURE;
        }

        Storage::disk('local')->makeDirectory('backups');
        $stamp = now()->format('Y-m-d-Hi');
        $filename = "backups/rafa-{$db}-{$stamp}.sql";
        $path = Storage::disk('local')->path($filename);

        $cmd = sprintf(
            'mysqldump --no-tablespaces -h %s -P %s -u %s %s %s > %s 2>&1',
            escapeshellarg($host),
            escapeshellarg($port),
            escapeshellarg($user),
            $password !== '' ? '-p'.escapeshellarg($password) : '',
            escapeshellarg($db),
            escapeshellarg($path)
        );

        exec($cmd, $output, $exit);

        if ($exit !== 0 || ! file_exists($path) || filesize($path) === 0) {
            $this->error('mysqldump gagal: '.implode("\n", array_slice($output, -3)));

            return self::FAILURE;
        }

        $this->info("Backup dibuat: {$filename} (".number_format(filesize($path) / 1024, 1).' KB)');
        $this->line('Restore: mysql ... '.$db.' < '.$path);

        return self::SUCCESS;
    }
}
