<?php
/**
 * METAR Aviation Weather Module
 *
 * @package Analogues_Snips
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Snips_METAR {

    public function init() {
        add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_assets' ) );

        // Legacy shortcodes
        add_shortcode( 'snip.METAR-raw', array( $this, 'render_raw' ) );
        add_shortcode( 'snip.METAR-category', array( $this, 'render_category' ) );
        add_shortcode( 'snip.METAR-ticker', array( $this, 'render_ticker' ) );

        // Standard shortcodes
        add_shortcode( 'snip_metar_raw', array( $this, 'render_raw' ) );
        add_shortcode( 'snip_metar_category', array( $this, 'render_category' ) );
        add_shortcode( 'snip_metar_ticker', array( $this, 'render_ticker' ) );
    }

    public function enqueue_assets() {
        if ( file_exists( SNIPS_PATH . 'assets/css/snips-metars.css' ) ) {
            wp_enqueue_style(
                'snips-metars-css',
                SNIPS_URL . 'assets/css/snips-metars.css',
                array(),
                SNIPS_VERSION
            );
        }
    }

    public function get_metar_data( $icao ) {
        $icao = strtoupper( sanitize_text_field( $icao ) );
        $transient_key = 'snips_metar_' . $icao;
        $data = get_transient( $transient_key );

        if ( false === $data ) {
            $response = wp_remote_get( 'https://metars.eu/widget/' . $icao );

            if ( is_wp_error( $response ) ) {
                return false;
            }

            $html = wp_remote_retrieve_body( $response );
            $data = array( 'raw' => '', 'category' => '' );

            if ( preg_match( '/<div class="raw">(.*?)<\/div>/is', $html, $matches ) ) {
                $data['raw'] = trim( strip_tags( $matches[1] ) );
            }

            if ( preg_match( '/<span class="fc"[^>]*>(.*?)<\/span>/is', $html, $matches ) ) {
                $data['category'] = trim( strip_tags( $matches[1] ) );
            }

            set_transient( $transient_key, $data, 600 );
        }

        return $data;
    }

    public function render_raw( $atts ) {
        $atts = shortcode_atts( array( 'icao' => 'KBOS' ), $atts );
        $data = $this->get_metar_data( $atts['icao'] );

        if ( empty( $data['raw'] ) ) {
            return 'METAR currently unavailable';
        }

        return sprintf(
            '<span class="snip-metar-raw" style="font-family: var(--snip-metar-font, var(--snip-global-font));">%s</span>',
            esc_html( $data['raw'] )
        );
    }

    public function render_category( $atts ) {
        $atts = shortcode_atts( array( 'icao' => 'KBOS' ), $atts );
        $data = $this->get_metar_data( $atts['icao'] );

        if ( empty( $data['category'] ) ) {
            return '';
        }

        $cat   = strtoupper( $data['category'] );
        $color = '#555555';

        if ( 'VFR' === $cat ) {
            $color = '#10b981';
        } elseif ( 'MVFR' === $cat ) {
            $color = '#3b82f6';
        } elseif ( 'IFR' === $cat ) {
            $color = '#ef4444';
        } elseif ( 'LIFR' === $cat ) {
            $color = '#d946ef';
        }

        return sprintf(
            '<span class="snip-metar-badge" style="background-color: %s;">%s</span>',
            esc_attr( $color ),
            esc_html( $cat )
        );
    }

    public function render_ticker( $atts ) {
        $atts     = shortcode_atts( array( 'icao' => 'KBOS' ), $atts );
        $stations = explode( ',', $atts['icao'] );
        $ticker_text = '';

        foreach ( $stations as $station ) {
            $station = trim( $station );
            $data    = $this->get_metar_data( $station );

            if ( ! empty( $data['raw'] ) ) {
                $ticker_text .= esc_html( $data['raw'] ) . ' &nbsp;&nbsp;&brvbar;&nbsp;&nbsp; ';
            }
        }

        if ( empty( $ticker_text ) ) {
            return '';
        }

        return '<div class="snip-metar-ticker-wrap"><div class="snip-metar-ticker-content">' . $ticker_text . '</div></div>';
    }
}