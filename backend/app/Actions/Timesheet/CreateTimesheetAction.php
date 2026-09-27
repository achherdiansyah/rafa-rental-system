<?php

namespace App\Actions\Timesheet;

use App\Enums\RentalStatus;
use App\Enums\TimesheetStatus;
use App\Exceptions\BusinessRuleException;
use App\Models\RentalDetail;
use App\Models\Timesheet;
use App\Models\User;
use App\Support\AuditLogger;

class CreateTimesheetAction
{
    /**
     * Create a timesheet entry (operator-filled). Actual working hours are
     * computed server-side: (end_hm - start_hm) minus break => DECIMAL(8,2),
     * no arbitrary rounding beyond the 2-decimal storage step.
     *
     * @param  array<string, mixed>  $data
     *
     * @throws BusinessRuleException
     */
    public function execute(User $operator, array $data): Timesheet
    {
        /** @var RentalDetail|null $rentalDetail */
        $rentalDetail = RentalDetail::with(['rental.booking'])->find($data['rental_detail_id']);

        if (! $rentalDetail || ! $rentalDetail->rental) {
            throw new BusinessRuleException('Rincian rental tidak ditemukan.');
        }

        // Hanya operator/owner rental atau staff yang boleh mencatat timesheet
        if (! $operator->isAdmin() && ! $operator->isOwner()) {
            $ownerId = $rentalDetail->rental->booking?->user_id;
            if (! $ownerId || (int) $ownerId !== (int) $operator->id) {
                throw new BusinessRuleException('Timesheet hanya dapat dicatat untuk rental milik akun Anda.');
            }
        }

        // Timesheet hanya boleh untuk rental yang sedang ONGOING
        if ($rentalDetail->rental->status !== RentalStatus::ONGOING) {
            throw new BusinessRuleException(
                'Timesheet hanya dapat dicatat untuk rental yang sedang berstatus ONGOING.'
            );
        }

        // Satu timesheet per unit per tanggal kerja
        $exists = Timesheet::where('rental_detail_id', $rentalDetail->id)
            ->where('report_date', $data['report_date'])
            ->exists();

        if ($exists) {
            throw new BusinessRuleException(
                'Timesheet untuk unit ini pada tanggal tersebut sudah pernah dicatat.'
            );
        }

        // Pakai nilai akhir hour meter sebagai referensi konsistensi durasi
        $startHm = (float) $data['start_hm'];
        $endHm = (float) $data['end_hm'];
        $breakMinutes = (int) ($data['break_minutes'] ?? 0);

        $elapsed = $endHm - $startHm;
        $breakHours = $breakMinutes / 60;

        $workHours = round($elapsed - $breakHours, 2);
        if ($workHours < 0) {
            throw new BusinessRuleException('Durasi kerja tidak konsisten: melebihi selisih jam meter setelah break.');
        }

        // Konsistensi: total >= breakdown + standby
        $breakdown = (float) ($data['breakdown_hours'] ?? 0);
        $standby = (float) ($data['standby_hours'] ?? 0);
        if ($workHours < ($breakdown + $standby)) {
            throw new BusinessRuleException(
                'Jam kerja aktual tidak konsisten dengan total breakdown + standby.'
            );
        }

        $timesheet = Timesheet::create([
            'rental_detail_id' => $rentalDetail->id,
            'report_date' => $data['report_date'],
            'start_hm' => $startHm,
            'end_hm' => $endHm,
            'break_minutes' => $breakMinutes,
            'total_work_hours' => $workHours,
            'standby_hours' => $standby,
            'breakdown_hours' => $breakdown,
            'operator_name' => $data['operator_name'] ?? $operator->name,
            'notes' => $data['notes'] ?? null,
            'signature_reference' => $data['signature_reference'] ?? null,
            'status' => TimesheetStatus::DRAFT,
        ]);

        AuditLogger::log('TIMESHEET_CREATED', $timesheet, [], [
            'rental_detail_id' => $rentalDetail->id,
            'report_date' => $data['report_date'],
            'total_work_hours' => $workHours,
            'created_by' => $operator->id,
        ]);

        $timesheet->load([
            'rentalDetail.assignment.unit',
            'rentalDetail.rental.booking.projectLocation',
        ]);

        return $timesheet;
    }
}
