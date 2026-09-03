<?php
/**
 * Aviation WordPress Presentation Adapter
 *
 * Registers shortcodes, Gutenberg block definitions, transient caching,
 * and presentation templates for METAR tickers and badges.
 *
 * @package Cahier\Modules\Aviation\Adapters\Wordpress
 */

declare(strict_types=1);

namespace Cahier\Modules\Aviation\Adapters\Wordpress;

use Cahier\Modules\Aviation\Core\Aviation_Settings;
use Cahier\Modules\Aviation\Core\Metar_Service;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class Aviation_Renderer {

    public function register(): void {
        // Universal and specific shortcodes
        add_shortcode( 'cahier_aviation', [ $this, 'render_unified' ] );
        add_shortcode( 'cahier_metar_raw', [ $this, 'render_raw' ] );
        add_shortcode( 'cahier_metar_category', [ $this, 'render_category' ] );
        add_shortcode( 'cahier_metar_ticker', [ $this, 'render_ticker' ] );

        // Gutenberg unified block
        add_action( 'init', [ $this, 'register_unified_block' ] );
    }

    public function register_unified_block(): void {
        if ( ! function_exists( 'register_block_type' ) ) {
            return;
        }

        register_block_type( 'cahier/aviation', [
            'editor_script'   => 'cahier-aviation-blocks',
            'render_callback' => [ $this, 'render_block_callback' ],
            'attributes'      => [
                'displayType' => [
                    'type'    => 'string',
                    'default' => 'ticker',
                ],
                'icao' => [
                    'type'    => 'string',
                    'default' => '',
                ],
                'preset' => [
                    'type'    => 'string',
                    'default' => 'default',
                ],
                'bgColor' => [
                    'type'    => 'string',
                    'default' => '',
                ],
                'textColor' => [
                    'type'    => 'string',
                    'default' => '',
                ],
                'speed' => [
                    'type'    => 'number',
                    'default' => 0,
                ],
            ],
        ] );
    }

    /**
     * Resolves station observations using WordPress transient caching.
     */
    public function get_metar_data( string $icao ): array|false {
        $normalized = Metar_Service::normalize_icaos( $icao );
        if ( empty( $normalized ) ) {
            return false;
        }
        $station = $normalized[0];

        $transient_key = 'cahier_metar_' . $station;
        $cached        = get_transient( $transient_key );

        if ( false !== $cached && is_array( $cached ) ) {
            return $cached;
        }

        $data = Metar_Service::fetch_station( $station );
        if ( null === $data ) {
            return false;
        }

        $global_settings = get_option( Aviation_Settings::OPTION_KEY, Aviation_Settings::get_defaults() );
        $ttl             = (int) ( $global_settings['cache_ttl'] ?? 600 );

        set_transient( $transient_key, $data, $ttl );

        return $data;
    }

    /**
     * Server-side render callback for Gutenberg editor canvas.
     */
    public function render_block_callback( array $attributes ): string {
        $type = $attributes['displayType'] ?? 'ticker';
        $icao = ! empty( $attributes['icao'] ) ? (string) $attributes['icao'] : '';

        if ( 'raw' === $type ) {
            return $this->render_raw( [ 'icao' => $icao ] );
        }
        if ( 'category' === $type ) {
            return $this->render_category( [ 'icao' => $icao ] );
        }

        return $this->render_ticker( [
            'icao'       => $icao,
            'preset'     => $attributes['preset'] ?? 'default',
            'bg_color'   => $attributes['bgColor'] ?? '',
            'text_color' => $attributes['textColor'] ?? '',
            'speed'      => $attributes['speed'] ?? 0,
        ] );
    }

    public function render_unified( $atts = [] ): string {
        $atts = shortcode_atts( [ 'type' => 'ticker' ], $atts, 'cahier_aviation' );
        return 'ticker' === $atts['type'] ? $this->render_ticker( $atts ) : $this->render_raw( $atts );
    }

    public function render_raw( $atts = [] ): string {
        $defaults = Aviation_Settings::get_defaults();
        $atts     = shortcode_atts( [ 'icao' => $defaults['default_stations'] ], $atts, 'cahier_metar_raw' );

        $stations = Metar_Service::normalize_icaos( (string) $atts['icao'] );
        $station  = $stations[0] ?? 'KBOS';

        $data = $this->get_metar_data( $station );
        if ( empty( $data['raw'] ) ) {
            return '<span class="cahier-metar-empty">METAR currently unavailable for ' . esc_html( $station ) . '</span>';
        }

        wp_enqueue_style( 'cahier-aviation' );

        return sprintf(
            '<span class="cahier-metar-raw" style="font-family: var(--cahier-metar-font, inherit); font-variant-numeric: tabular-nums;">%s</span>',
            esc_html( (string) $data['raw'] )
        );
    }

    public function render_category( $atts = [] ): string {
        $defaults = Aviation_Settings::get_defaults();
        $atts     = shortcode_atts( [ 'icao' => $defaults['default_stations'] ], $atts, 'cahier_metar_category' );

        $stations = Metar_Service::normalize_icaos( (string) $atts['icao'] );
        $station  = $stations[0] ?? 'KBOS';

        $data = $this->get_metar_data( $station );
        if ( empty( $data['category'] ) ) {
            return '';
        }

        wp_enqueue_style( 'cahier-aviation' );

        $cat   = strtoupper( (string) $data['category'] );
        $color = match ( $cat ) {
            'VFR'   => '#10b981',
            'MVFR'  => '#3b82f6',
            'IFR'   => '#ef4444',
            'LIFR'  => '#d946ef',
            default => '#555555',
        };

        return sprintf(
            '<span class="cahier-metar-badge" style="background-color: %s;">%s</span>',
            esc_attr( $color ),
            esc_html( $cat )
        );
    }

    public function render_ticker( $atts = [] ): string {
        $global = get_option( Aviation_Settings::OPTION_KEY, Aviation_Settings::get_defaults() );

        $atts = shortcode_atts( [
            'icao'          => $global['default_stations'],
            'preset'        => 'default',
            'bg_color'      => '',
            'text_color'    => '',
            'speed'         => 0,
            'show_category' => null,
        ], $atts, 'cahier_metar_ticker' );

        $stations = Metar_Service::normalize_icaos( (string) $atts['icao'] );
        if ( empty( $stations ) ) {
            $stations = Metar_Service::normalize_icaos( $global['default_stations'] );
        }

        $show_category = null !== $atts['show_category']
            ? filter_var( $atts['show_category'], FILTER_VALIDATE_BOOLEAN )
            : (bool) ( $global['show_category'] ?? true );

        $items_html = '';
        $count = 0;

        foreach ( $stations as $station ) {
            $data = $this->get_metar_data( $station );
            if ( ! empty( $data['raw'] ) ) {
                $count++;
                $category_badge = '';

                if ( $show_category && ! empty( $data['category'] ) ) {
                    $cat   = strtoupper( (string) $data['category'] );
                    $color = match ( $cat ) {
                        'VFR'   => '#10b981',
                        'MVFR'  => '#3b82f6',
                        'IFR'   => '#ef4444',
                        'LIFR'  => '#d946ef',
                        default => '#555555',
                    };
                    $category_badge = sprintf(
                        '<span class="cahier-metar-badge" style="background-color:%s;margin-right:8px;">%s</span>',
                        esc_attr( $color ),
                        esc_html( $cat )
                    );
                }

                $items_html .= sprintf(
                    '<span class="cahier-metar-item">%s<span class="cahier-metar-raw">%s</span></span><span class="cahier-metar-sep">&brvbar;</span>',
                    $category_badge,
                    esc_html( (string) $data['raw'] )
                );
            }
        }

        if ( 0 === $count ) {
            return '<div class="cahier-metar-empty" style="padding:10px;font-size:12px;font-family:monospace;">Awaiting METAR observations...</div>';
        }

        $bg_color       = ! empty( $atts['bg_color'] ) ? $atts['bg_color'] : $global['bg_color'];
        $text_color     = ! empty( $atts['text_color'] ) ? $atts['text_color'] : $global['text_color'];
        $chars_per_sec  = ! empty( $atts['speed'] ) && (int) $atts['speed'] > 0
            ? (int) $atts['speed']
            : (int) ( $global['speed'] ?? 12 );
        $pause_on_hover = ! empty( $global['pause_on_hover'] ) ? '1' : '0';

        $style_vars = sprintf(
            '--cahier-metar-bg: %s; --cahier-metar-color: %s; --cahier-metar-border: %s; --cahier-metar-size: %s; --cahier-metar-radius: %s; --cahier-metar-padding: %s;',
            esc_attr( $bg_color ),
            esc_attr( $text_color ),
            esc_attr( $global['border_color'] ),
            esc_attr( $global['font_size'] ),
            esc_attr( $global['border_radius'] ),
            esc_attr( $global['padding'] )
        );

        wp_enqueue_style( 'cahier-aviation' );
        wp_enqueue_script( 'cahier-aviation-ticker' );

        return sprintf(
            '<div class="cahier-metar-ticker-wrap" style="%s" data-cps="%d" data-pause-on-hover="%s"><div class="cahier-metar-ticker-track"><div class="cahier-metar-ticker-content">%s</div></div></div>',
            $style_vars,
            $chars_per_sec,
            esc_attr( $pause_on_hover ),
            $items_html
        );
    }
}