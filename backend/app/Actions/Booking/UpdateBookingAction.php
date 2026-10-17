<?php

namespace App\Actions\Booking;

use App\Enums\BookingStatus;
use App\Exceptions\BusinessRuleException;
use App\Exceptions\InvalidStateTransitionException;
use App\Models\Booking;
use App\Models\ProjectLocation;
use App\Models\User;
use App\Services\Booking\BookingAvailabilityPrecheckService;
use App\Services\Routing\RouteDistanceService;
use App\Support\AuditLogger;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Correct a booking that has not yet passed Supervisor validation: DRAFT or a
 * PENDING_APPROVAL booking returned for revision. Only the project location and
 * the per-line rental dates can change; MOB/DEMOB snapshots are recomputed from
 * the same origin pool to the new destination (never invented when distance is
 * unavailable; legacy flat pricing is preserved as-is).
 */
class UpdateBookingAction
{
    public function __construct(
        protected BookingAvailabilityPrecheckService $precheck,
        protected RouteDistanceService $routeDistance
    ) {}

    /**
     * @param  array{project_location_id?:int|null, details?:array<int, array{id:int, start_date:string, end_date:string}>}  $data
     */
    public function execute(User $user, Booking $booking, array $data): Booking
    {
        return DB::transaction(function () use ($user, $booking, $data) {
            $isOwner = (int) $booking->user_id === (int) $user->id;
            $isStaff = $user->isAdmin() || $user->isOwner();

            if (! $isOwner && ! $isStaff) {
                throw new InvalidStateTransitionException('Booking bukan milik akun Anda.');
            }

            $revision = $booking->status === BookingStatus::PENDING_APPROVAL
                && $booking->supervisor_decision === 'REVISION';

            if ($booking->status !== BookingStatus::DRAFT && ! $revision) {
                throw new InvalidStateTransitionException(
                    'Perbaikan hanya untuk booking DRAFT atau hasil revisi Supervisor.'
                );
            }

            $booking->load(['details.model.pool', 'details.model.prices']);

            $destination = null;
            if (! empty($data['project_location_id']) && (int) $data['project_location_id'] !== (int) $booking->project_location_id) {
                $destination = ProjectLocation::find((int) $data['project_location_id']);
                if (! $destination) {
                    throw new BusinessRuleException('Lokasi proyek tidak ditemukan.');
                }
                if (! $isStaff && (int) $destination->user_id !== (int) $user->id) {
                    throw new InvalidStateTransitionException('Lokasi proyek bukan milik akun Anda.');
                }
            } else {
                $destination = $booking->projectLocation;
            }

            $lines = [];
            foreach ($data['details'] ?? [] as $row) {
                /** @var \App\Models\BookingDetail|null $detail */
                $detail = $booking->details->firstWhere('id', (int) ($row['id'] ?? 0));
                if (! $detail) {
                    throw new BusinessRuleException('Detail booking tidak sesuai.');
                }

                $start = Carbon::parse($row['start_date'])->startOfDay();
                $end = Carbon::parse($row['end_date'])->startOfDay();
                if ($end->lessThan($start)) {
                    throw new BusinessRuleException('Tanggal selesai tidak boleh sebelum tanggal mulai.');
                }

                $lines[$detail->id] = [$start, $end];
            }

            // Availability pre-check on the resulting schedule.
            $precheckLines = [];
            foreach ($booking->details as $detail) {
                [$start, $end] = $lines[$detail->id] ?? [$detail->start_date, $detail->end_date];
                $precheckLines[] = [
                    'equipment_model_id' => $detail->equipment_model_id,
                    'quantity' => $detail->quantity,
                    'start_date' => $start->toDateString(),
                    'end_date' => $end->toDateString(),
                ];
            }
            if (! empty($precheckLines)) {
                $this->precheck->assertAvailable(
                    $precheckLines,
                    Carbon::parse(collect($precheckLines)->min('start_date')),
                    Carbon::parse(collect($precheckLines)->max('end_date'))
                );
            }

            $total = 0.0;

            foreach ($booking->details as $detail) {
                [$start, $end] = $lines[$detail->id] ?? [$detail->start_date, $detail->end_date];

                $model = $detail->model;
                $origin = $model?->pool;
                $distanceRate = (float) ($model?->prices
                    ->where('is_all_in', $detail->is_all_in)
                    ->where('effective_date', '<=', $start->toDateString())
                    ->sortByDesc('effective_date')
                    ->first()?->distance_rate_per_km ?? $detail->distance_rate_per_km ?? 0);

                $mob = (float) $detail->mob_cost_snapshot;
                $demob = (float) $detail->demob_cost_snapshot;
                $distanceKm = $detail->route_distance_km;
                $distanceAvailable = $detail->distance_rate_per_km !== null && (float) $detail->distance_rate_per_km > 0;

                $originLat = $origin?->latitude !== null ? (float) $origin->latitude : null;
                $originLng = $origin?->longitude !== null ? (float) $origin->longitude : null;
                $destLat = $destination?->latitude !== null ? (float) $destination->latitude : null;
                $destLng = $destination?->longitude !== null ? (float) $destination->longitude : null;

                if ($origin && $destination) {
                    if ($distanceRate <= 0) {
                        throw new BusinessRuleException(
                            'Tarif per kilometer MOB/DEMOB belum dikonfigurasi oleh Admin. Booking belum dapat diperbarui.'
                        );
                    }
                    $resolved = $this->routeDistance->distanceKm($originLat, $originLng, $destLat, $destLng);
                    if ($resolved === null) {
                        throw new BusinessRuleException(
                            'Estimasi jarak rute MOB/DEMOB belum tersedia. Hubungi Admin (layanan routing belum dikonfigurasi).'
                        );
                    }
                    $distanceKm = $resolved;
                    $distanceAvailable = true;
                    $mob = round($resolved * $distanceRate, 2);
                    $demob = $mob;
                }

                $subtotal = ($mob + $demob) * (int) $detail->quantity;
                $total += $subtotal;

                $detail->update([
                    'start_date' => $start->toDateString(),
                    'end_date' => $end->toDateString(),
                    'mob_cost_snapshot' => $mob,
                    'demob_cost_snapshot' => $demob,
                    'subtotal' => $subtotal,
                    'destination_project_location_id' => $destination?->id,
                    'destination_name' => $destination?->project_name,
                    'destination_address' => $destination?->address,
                    'destination_latitude' => $destination?->latitude,
                    'destination_longitude' => $destination?->longitude,
                    'route_distance_km' => $distanceKm,
                    'distance_rate_per_km' => $distanceAvailable ? $distanceRate : $detail->distance_rate_per_km,
                ]);
            }

            $booking->update([
                'project_location_id' => $destination?->id ?? $booking->project_location_id,
                'total_amount' => $total,
            ]);

            AuditLogger::log('BOOKING_UPDATED_BY_USER', $booking, [], [
                'updated_by' => $user->id,
                'supervisor_revision' => $revision,
            ]);

            return $booking->fresh()->load([
                'projectLocation',
                'details.model' => fn ($q) => $q->with(['type', 'prices', 'attachments']),
            ]);
        });
    }
}
