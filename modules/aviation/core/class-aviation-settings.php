<?php
/**
 * Aviation Settings & Presets Container
 *
 * Manages design presets, configuration defaults, and JSON serialization.
 *
 * @package Cahier\Modules\Aviation\Core
 */

declare(strict_types=1);

namespace Cahier\Modules\Aviation\Core;

final class Aviation_Settings {

    public const OPTION_KEY = 'cahier_aviation_settings';

    /**
     * Curated visual presets.
     */
    public static function get_presets(): array {
        return [
            'terminal_green' => [
                'name'          => 'Terminal Green (Classic)',
                'bg_color'      => '#1a1a1a',
                'text_color'    => '#10b981',
                'border_color'  => '#333333',
                'font_size'     => '13px',
                'speed'         => 12, // Characters per second (also user modifiable)
                'border_width'  => '1px',
                'border_radius' => '3px',
                'padding'       => '10px 0',
                'show_category' => true,
            ],
            'dark_monolith' => [
                'name'          => 'Dark Monolith',
                'bg_color'      => '#09090b',
                'text_color'    => '#f4f4f5',
                'border_color'  => '#27272a',
                'font_size'     => '12px',
                'speed'         => 14,
                'border_width'  => '1px',
                'border_radius' => '0px',
                'padding'       => '8px 0',
                'show_category' => true,
            ],
            'amber_crt' => [
                'name'          => 'Amber CRT',
                'bg_color'      => '#120d02',
                'text_color'    => '#f59e0b',
                'border_color'  => '#451a03',
                'font_size'     => '13px',
                'speed'         => 10,
                'border_width'  => '1px',
                'border_radius' => '4px',
                'padding'       => '10px 0',
                'show_category' => true,
            ],
            'parchment' => [
                'name'          => 'Editorial Parchment',
                'bg_color'      => '#fbfaf8',
                'text_color'    => '#292524',
                'border_color'  => '#e7e5e4',
                'font_size'     => '12px',
                'speed'         => 11,
                'border_width'  => '1px',
                'border_radius' => '2px',
                'padding'       => '8px 0',
                'show_category' => true,
            ],
        ];
    }
    
    /**
     * Default configuration fallback.
     */
    public static function get_defaults(): array {
        $presets = self::get_presets();
        return array_merge( $presets['terminal_green'], [
            'default_stations' => 'KBOS, KORH, KMVY',
            'preset'           => 'terminal_green',
            'pause_on_hover'   => true,
            'show_category'    => true,
            'cache_ttl'        => 600,
        ] );
    }

    /**
     * Validates and imports a JSON settings string.
     */
    public static function import_json( string $json_str ): array|false {
        $decoded = json_decode( $json_str, true );
        if ( ! is_array( $decoded ) ) {
            return false;
        }

        $defaults = self::get_defaults();
        $clean    = [];
        
        foreach ( $defaults as $k => $def_val ) {
            if ( isset( $decoded[ $k ] ) ) {
                $clean[ $k ] = is_bool( $def_val ) ? (bool) $decoded[ $k ] : sanitize_text_field( (string) $decoded[ $k ] );
            } else {
                $clean[ $k ] = $def_val;
            }
        }

        return $clean;
    }
}