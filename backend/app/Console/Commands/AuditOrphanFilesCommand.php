<?php

namespace App\Console\Commands;

use App\Models\Attachment;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

/**
 * Reports storage integrity issues without deleting anything:
 *  - attachment rows whose file is missing on disk
 *  - files on the private disk (older than 1 day) with no attachment row
 * Manual review is required; nothing is auto-removed.
 */
class AuditOrphanFilesCommand extends Command
{
    protected $signature = 'storage:audit-orphans';

    protected $description = 'List missing attachment files and orphan files on the private disk';

    public function handle(): int
    {
        $missing = [];
        Attachment::query()->chunk(200, function ($attachments) use (&$missing) {
            foreach ($attachments as $attachment) {
                $disk = str_starts_with($attachment->file_path, 'equipment') ? 'public' : 'local';
                if (! Storage::disk($disk)->exists($attachment->file_path)) {
                    $missing[] = sprintf('MISSING %s #%d → %s', $attachment->document_type, $attachment->id, $attachment->file_path);
                }
            }
        });

        $known = Attachment::query()->pluck('file_path')->flip();
        $orphans = [];
        foreach (Storage::disk('local')->allFiles() as $file) {
            $storagePath = Storage::disk('local')->path($file);
            $modified = filemtime($storagePath);
            if ($modified && $modified > now()->subDay()->getTimestamp()) {
                continue; // recently written slot (still referenced/unsettled)
            }
            if (! $known->has($file)) {
                $orphans[] = $file;
            }
        }

        $out = array_merge(['[MISSING ATTACHMENTS]'], $missing, ['[ORPHAN FILES (private, >1 day)]'], $orphans);
        foreach ($out as $line) {
            $this->line($line);
        }

        $this->info(sprintf('Ditemukan %d attachment tanpa file, %d orphan file.', count($missing), count($orphans)));

        return self::SUCCESS;
    }
}
