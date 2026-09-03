<?php
/**
 * Platform-Agnostic METAR Service & Normalizer
 *
 * Normalizes ICAO station codes, performs network transport, and extracts
 * meteorological transmission data from metars.eu widgets. Pure PHP.
 *
 * @package Cahier\Modules\Aviation\Core
 */

declare(strict_types=1);

namespace Cahier\Modules\Aviation\Core;

final class Metar_Service {

    /**
     * Normalizes user-input station identifiers:
     * - Splits commas, spaces, semicolons, and slashes
     * - Uppercases and trims whitespace
     * - Prepends 'K' to 3-letter US codes (e.g., 'BOS' -> 'KBOS')
     * - Discards invalid tokens
     *
     * @param string|array<string> $input Station strings or raw array.
     * @return array<string> Sanitized 4-character ICAO identifiers.
     */
    public static function normalize_icaos( string|array $input ): array {
        if ( is_array( $input ) ) {
            $raw_tokens = $input;
        } else {
            $raw_tokens = preg_split( '/[\s,;|\/]+/', (string) $input, -1, PREG_SPLIT_NO_EMPTY );
        }

        $clean = [];
        foreach ( (array) $raw_tokens as $token ) {
            $station = strtoupper( trim( (string) $token ) );
            if ( '' === $station ) {
                continue;
            }

            // Expand 3-letter alphabetical codes to standard Continental US ICAO
            if ( 3 === strlen( $station ) && ctype_alpha( $station ) ) {
                $station = 'K' . $station;
            }

            // Accept valid 4-character alphanumeric ICAO identifiers
            if ( 4 === strlen( $station ) && ctype_alnum( $station ) ) {
                $clean[] = $station;
            }
        }

        return array_values( array_unique( $clean ) );
    }

    /**
     * Fetches and extracts observation data for a single station.
     *
     * @param string $icao Station identifier.
     * @param int    $timeout Network timeout in seconds.
     * @return array{raw: string, category: string}|null Structured data or null on error.
     */
    public static function fetch_station( string $icao, int $timeout = 5 ): ?array {
        $normalized = self::normalize_icaos( $icao );
        if ( empty( $normalized ) ) {
            return null;
        }

        $station = $normalized[0];
        $url     = 'https://metars.eu/widget/' . rawurlencode( $station );

        $html = self::request_url( $url, $timeout );
        if ( false === $html || '' === trim( $html ) ) {
            return null;
        }

        return self::parse_widget_html( $html );
    }

    /**
     * Extracts the raw observation string and flight category from markup.
     *
     * @param string $html Raw HTML response.
     * @return array{raw: string, category: string}
     */
    public static function parse_widget_html( string $html ): array {
        $data = [
            'raw'      => '',
            'category' => '',
        ];

        if ( preg_match( '/<div class="raw">(.*?)<\/div>/is', $html, $matches ) ) {
            $data['raw'] = trim( strip_tags( $matches[1] ) );
        }

        if ( preg_match( '/<span class="fc"[^>]*>(.*?)<\/span>/is', $html, $matches ) ) {
            $data['category'] = strtoupper( trim( strip_tags( $matches[1] ) ) );
        }

        return $data;
    }

    /**
     * HTTP GET execution with configurable stream context timeout.
     */
    private static function request_url( string $url, int $timeout ): string|false {
        $options = [
            'http' => [
                'method'  => 'GET',
                'timeout' => $timeout,
                'header'  => "User-Agent: CahierAviation/1.0\r\nAccept: text/html\r\n",
            ],
        ];

        $context = stream_context_create( $options );
        return @file_get_contents( $url, false, $context );
    }
}