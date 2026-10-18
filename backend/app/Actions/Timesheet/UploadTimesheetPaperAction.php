<?php

namespace App\Actions\Timesheet;

use App\Exceptions\BusinessRuleException;
use App\Models\Attachment;
use App\Models\Timesheet;
use App\Models\User;
use App\Support\AuditLogger;
use App\Support\FileSecurity;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;

class UploadTimesheetPaperAction
{
    public function execute(Timesheet $timesheet, UploadedFile $file, User $actor): Attachment
    {
        return DB::transaction(function () use ($timesheet, $file, $actor) {
            if (! in_array($timesheet->status->value, ['DRAFT', 'REJECTED'], true)) {
                throw new BusinessRuleException('Foto kertas hanya dapat diunggah pada Timesheet DRAFT/REJECTED.');
            }

            $security = FileSecurity::validate($file);
            if (! $security['valid']) {
                throw new BusinessRuleException($security['error'] ?? 'Berkas tidak valid.');
            }

            $extension = strtolower($file->getClientOriginalExtension());
            $path = FileSecurity::generateSecurePath('timesheets/paper', $extension);
            $file->storeAs('', $path, 'local');

            /** @var Attachment $attachment */
            $attachment = $timesheet->attachments()->create([
                'document_type' => 'TIMESHEET_PAPER',
                'file_path' => $path,
                'file_name' => $file->getClientOriginalName(),
                'mime_type' => $file->getMimeType(),
                'file_size' => $file->getSize(),
                'uploaded_by' => $actor->id,
            ]);

            AuditLogger::log('TIMESHEET_PAPER_UPLOADED', $timesheet, [], [
                'attachment_id' => $attachment->id,
                'uploaded_by' => $actor->id,
            ]);

            return $attachment;
        });
    }
}
