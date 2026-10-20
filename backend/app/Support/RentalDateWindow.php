<?php

namespace App\Support;

use App\Exceptions\BusinessRuleException;
use Carbon\Carbon;

/**
 * Single source of truth for the rental start window, evaluated in the
 * Asia/Jakarta timezone regardless of the app/server UTC timezone.
 *
 * NOTE: the H-1 requirement is temporarily lifted for the thesis demo.
 * Restore `addDay()` in minStartDate() (and the H-1 message) afterwards.
 */
final class RentalDateWindow
{
    public const TIMEZONE = 'Asia/Jakarta';

    public const START_MESSAGE = 'Tanggal mulai sewa tidak boleh sebelum hari ini.';

    public const END_MESSAGE = 'Tanggal selesai sewa tidak boleh mendahului tanggal mulai sewa.';

    public static function minStartDate(): string
    {
        // ponytail: H-1 temporarily disabled for demo; addDay() restores it.
        return Carbon::now(self::TIMEZONE)->toDateString();
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
