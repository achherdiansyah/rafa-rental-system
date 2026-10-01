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

class SignTimesheetAction
{
    /**
     * Attach the PIC/operator signature to a timesheet entry.
     * The file is stored on the private 'local' disk (no public exposure).
     */
    public function execute(Timesheet $timesheet, UploadedFile $file, User $signer): Attachment
    {
        return DB::transaction(function () use ($timesheet, $file, $signer) {
            if (! in_array($timesheet->status->value, ['DRAFT', 'SUBMITTED', 'APPROVED'], true)) {
                throw new BusinessRuleException(
                    'Tanda tangan hanya dapat dilampirkan pada timesheet DRAFT atau SUBMITTED.'
                );
            }

            $extension = strtolower($file->getClientOriginalExtension());
            $securePath = FileSecurity::generateSecurePath('signatures', $extension);

            // Private storage: file not placed on the public disk
            $file->storeAs('', $securePath, 'local');

            /** @var Attachment $attachment */
            $attachment = $timesheet->attachments()->create([
                'document_type' => 'TIMESHEET_SIGNATURE',
                'file_path' => $securePath,
                'file_name' => $file->getClientOriginalName(),
                'mime_type' => $file->getMimeType(),
                'file_size' => $file->getSize(),
                'uploaded_by' => $signer->id,
            ]);

            // Reference the signature in the timesheet record
            $timesheet->update(['signature_reference' => (string) $attachment->id]);

            AuditLogger::log('TIMESHEET_SIGNED', $timesheet, [], [
                'attachment_id' => $attachment->id,
                'signed_by' => $signer->id,
            ]);

            return $attachment;
        });
    }
}
