<?php

namespace App\Support;

use App\Exceptions\BusinessRuleException;
use Carbon\Carbon;

/**
 * Single source of truth for the H-1 rental start window, evaluated in the
 * Asia/Jakarta timezone regardless of the app/server UTC timezone.
 */
final class RentalDateWindow
{
    public const TIMEZONE = 'Asia/Jakarta';

    public const START_MESSAGE = 'Tanggal mulai sewa minimal besok karena pemesanan wajib dilakukan H-1.';

    public const END_MESSAGE = 'Tanggal selesai sewa tidak boleh mendahului tanggal mulai sewa.';

    public static function minStartDate(): string
    {
        return Carbon::now(self::TIMEZONE)->addDay()->toDateString();
    }

    public static function isStartAllowed(string $date): bool
    {
        return Carbon::parse($date, self::TIMEZONE)->toDateString() >= self::minStartDate();
    }

    public static function isEndAllowed(string $startDate, string $endDate): bool
    {
        return Carbon::parse($endDate, self::TIMEZONE)->toDateString()
            >= Carbon::parse($startDate, self::TIMEZONE)->toDateString();
    }

    /**
     * @throws BusinessRuleException
     */
    public static function assertAllowed(string $startDate, ?string $endDate = null): void
    {
        if (! self::isStartAllowed($startDate)) {
            throw new BusinessRuleException(self::START_MESSAGE);
        }

        if ($endDate !== null && ! self::isEndAllowed($startDate, $endDate)) {
            throw new BusinessRuleException(self::END_MESSAGE);
        }
    }
}
