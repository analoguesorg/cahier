<?php
/**
 * Dynamic Date Shortcode Module
 *
 * @package Analogues_Snips
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Snips_Date {

    public function init() {
        add_shortcode( 'snip.Date', array( $this, 'render_date' ) );
        add_shortcode( 'snip_date', array( $this, 'render_date' ) );
    }

    public function render_date( $atts ) {
        $atts = shortcode_atts(
            array(
                'format' => get_option( 'date_format' ),
            ),
            $atts,
            'snip_date'
        );

        $rendered_date = wp_date( $atts['format'] );

        return sprintf(
            '<span class="snip-date" style="font-family: var(--snip-date-font, var(--snip-global-font));">%s</span>',
            esc_html( $rendered_date )
        );
    }
}