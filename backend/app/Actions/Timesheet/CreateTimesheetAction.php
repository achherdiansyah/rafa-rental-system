<?php

namespace App\Actions\Timesheet;

use App\Enums\RentalStatus;
use App\Enums\TimesheetStatus;
use App\Exceptions\BusinessRuleException;
use App\Models\RentalDetail;
use App\Models\RentalOperatorAssignment;
use App\Models\Timesheet;
use App\Models\User;
use App\Services\Billing\PaymentHoldService;
use App\Support\AuditLogger;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;

class CreateTimesheetAction
{
    public function __construct(
        private readonly PaymentHoldService $paymentHolds
    ) {}

    public function execute(User $operator, array $data): Timesheet
    {
        return DB::transaction(function () use ($operator, $data) {
            /** @var RentalDetail|null $rentalDetail */
            $rentalDetail = RentalDetail::with(['rental.booking'])->find($data['rental_detail_id']);

            if (! $rentalDetail || ! $rentalDetail->rental) {
                throw new BusinessRuleException('Rincian rental tidak ditemukan.');
            }

            if (! $operator->isOperator()) {
                throw new AuthorizationException('Hanya Operator yang dapat membuat Timesheet operasional.');
            }

            $assigned = RentalOperatorAssignment::where('rental_detail_id', $rentalDetail->id)
                ->where('operator_user_id', $operator->id)
                ->where('status', RentalOperatorAssignment::ACTIVE)
                ->exists();

            if (! $assigned) {
                throw new AuthorizationException('Anda hanya dapat menginput Timesheet untuk unit yang ditugaskan kepada Anda.');
            }

            if ($rentalDetail->rental->status !== RentalStatus::ONGOING) {
                throw new BusinessRuleException('Timesheet hanya dapat dicatat untuk rental yang sedang berstatus ONGOING.');
            }

            if ($this->paymentHolds->isHeld($rentalDetail->rental)) {
                throw new BusinessRuleException('Rental sedang dalam PAYMENT HOLD. Selesaikan tunggakan sebelum menginput Timesheet.');
            }

            $exists = Timesheet::where('rental_detail_id', $rentalDetail->id)
                ->where('report_date', $data['report_date'])
                ->exists();

            if ($exists) {
                throw new BusinessRuleException('Timesheet untuk unit ini pada tanggal tersebut sudah pernah dicatat.');
            }

            $startTime = $data['start_time'] ?? null;
            $endTime = $data['end_time'] ?? null;
            $breakMinutes = (int) ($data['break_minutes'] ?? 0);

            if ($startTime && $endTime) {
                $startCarbon = \Carbon\Carbon::createFromFormat('H:i', $startTime);
                $endCarbon = \Carbon\Carbon::createFromFormat('H:i', $endTime);
                if ($endCarbon->lessThanOrEqualTo($startCarbon)) {
                    throw new BusinessRuleException('Jam selesai harus lebih besar dari jam mulai.');
                }
                $elapsed = $startCarbon->diffInMinutes($endCarbon) / 60;
                $startHm = isset($data['start_hm']) ? (float) $data['start_hm'] : null;
                $endHm = isset($data['end_hm']) ? (float) $data['end_hm'] : null;
            } else {
                $startHm = (float) ($data['start_hm'] ?? 0);
                $endHm = (float) ($data['end_hm'] ?? 0);
                $elapsed = $endHm - $startHm;
            }

            $breakHours = $breakMinutes / 60;

            // Dihitung apa adanya (tanpa pembulatan); presisi desimal ditangani kolom DECIMAL(8,2).
            $workHours = $elapsed - $breakHours;
            if ($workHours < 0) {
                throw new BusinessRuleException('Durasi kerja tidak konsisten: melebihi selisih jam kerja setelah break.');
            }

            $breakdown = (float) ($data['breakdown_hours'] ?? 0);
            $standby = (float) ($data['standby_hours'] ?? 0);
            if ($workHours < ($breakdown + $standby)) {
                throw new BusinessRuleException('Jam kerja aktual tidak konsisten dengan total breakdown + standby.');
            }

            // No direct-entry bypass: every new operational Timesheet is DRAFT
            // until Admin approval (only approval creates the Daily Invoice).
            $timesheet = Timesheet::create([
                'rental_detail_id' => $rentalDetail->id,
                'report_date' => $data['report_date'],
                'start_time' => $startTime,
                'end_time' => $endTime,
                'start_hm' => $startHm,
                'end_hm' => $endHm,
                'break_minutes' => $breakMinutes,
                'total_work_hours' => $workHours,
                'standby_hours' => $standby,
                'breakdown_hours' => $breakdown,
                'operator_name' => $operator->name,
                'notes' => $data['notes'] ?? null,
                'signature_reference' => $data['signature_reference'] ?? null,
                'status' => TimesheetStatus::DRAFT,
                'approved_by' => null,
            ]);

            AuditLogger::log('TIMESHEET_CREATED', $timesheet, [], [
                'rental_detail_id' => $rentalDetail->id,
                'report_date' => $data['report_date'],
                'total_work_hours' => $workHours,
                'created_by' => $operator->id,
                'status' => TimesheetStatus::DRAFT->value,
            ]);

            $timesheet->load([
                'rentalDetail.assignment.unit',
                'rentalDetail.rental.booking.projectLocation',
                'attachments',
            ]);

            return $timesheet;
        });
    }
}