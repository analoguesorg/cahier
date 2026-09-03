<?php
/**
 * WordPress Date Shortcode Adapter
 *
 * Bridges WordPress shortcode handling with the Cahier core date formatter.
 *
 * @package Cahier\Modules\Chronometer\Adapters\Wordpress
 */

declare(strict_types=1);

namespace Cahier\Modules\Chronometer\Adapters\Wordpress;

use Cahier\Modules\Chronometer\Core\Date_Formatter;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class Date_Shortcode {

    public function register(): void {
        add_shortcode( 'cahier_date', [ $this, 'render' ] );
        add_shortcode( 'cahier.Date', [ $this, 'render' ] );
    }

    /**
     * Shortcode handler for rendering formatted dynamic dates.
     *
     * @param array<string, mixed>|string $atts Shortcode attributes.
     * @return string
     */
    public function render( $atts ): string {
        $atts = shortcode_atts(
            [
                'format' => get_option( 'date_format', 'F j, Y' ),
            ],
            $atts,
            'cahier_date'
        );

        $timestamp = (int) current_time( 'timestamp' );
        $timezone  = wp_timezone_string();
        $format    = (string) $atts['format'];

        $rendered_date = Date_Formatter::format( $timestamp, $format, $timezone );

        return sprintf(
            '<span class="cahier-date" style="font-family: var(--cahier-date-font, inherit);">%s</span>',
            esc_html( $rendered_date )
        );
    }
}