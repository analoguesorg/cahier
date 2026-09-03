<?php
/**
 * Standard Date Formatter
 *
 * Platform-agnostic pure PHP utility for converting timestamps into formatted date strings according to specified timezones and patterns.
 *
 * @package Cahier\Modules\Chronometer\Core
 */

declare(strict_types=1);

namespace Cahier\Modules\Chronometer\Core;

use DateTimeImmutable;
use DateTimeZone;
use Exception;

final class Date_Formatter {

    /**
     * Formats a Unix timestamp into a date string.
     *
     * @param int    $timestamp Unix timestamp.
     * @param string $format    PHP date format character string.
     * @param string $timezone  Timezone string (e.g. 'America/New_York', 'UTC').
     * @return string
     */
    public static function format( int $timestamp, string $format, string $timezone = 'UTC' ): string {
        try {
            $tz   = new DateTimeZone( $timezone );
            $date = ( new DateTimeImmutable( "@{$timestamp}" ) )->setTimezone( $tz );
            return $date->format( $format );
        } catch ( Exception $e ) {
            return date( $format, $timestamp );
        }
    }
}