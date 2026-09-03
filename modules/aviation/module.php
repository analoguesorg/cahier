<?php
/**
 * Aviation Weather Module Manifest
 *
 * Implements Module_Contract to boot Aviation features, register settings,
 * and provide the administrative interface inside the Cahier Console.
 *
 * @package Cahier\Modules\Aviation
 */

declare(strict_types=1);

namespace Cahier\Modules\Aviation;

use Cahier\Core\Module_Contract;
use Cahier\Modules\Aviation\Core\Aviation_Settings;
use Cahier\Modules\Aviation\Adapters\Wordpress\Aviation_Renderer;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

require_once __DIR__ . '/core/class-aviation-settings.php';
require_once __DIR__ . '/core/class-metar-service.php';
require_once __DIR__ . '/adapters/wordpress/class-aviation-renderer.php';

final class Module implements Module_Contract {

    public function get_slug(): string {
        return 'aviation';
    }

    public function get_name(): string {
        return 'Aviation';
    }

    public function get_description(): string {
        return 'METAR station weather, category badges, animated ticker tape, and Gutenberg blocks.';
    }

    public function is_active(): bool {
        return true;
    }

    public function boot(): void {
        $renderer = new Aviation_Renderer();
        $renderer->register();

        add_action( 'enqueue_block_editor_assets', [ $this, 'enqueue_block_editor_assets' ] );
        add_action( 'admin_post_cahier_save_aviation_settings', [ $this, 'handle_save_settings' ] );
        add_action( 'admin_post_cahier_import_aviation_json', [ $this, 'handle_import_json' ] );
    }

    public function enqueue_admin_assets( string $hook ): void {}

    public function enqueue_block_editor_assets(): void {
        wp_enqueue_script(
            'cahier-aviation-blocks',
            CAHIER_URL . 'modules/aviation/assets/js/aviation-blocks.js',
            [ 'wp-blocks', 'wp-element', 'wp-components', 'wp-block-editor', 'wp-server-side-render' ],
            CAHIER_VERSION,
            true
        );

        wp_enqueue_script(
            'cahier-aviation-ticker',
            CAHIER_URL . 'modules/aviation/assets/js/aviation-ticker.js',
            [],
            CAHIER_VERSION,
            true
        );

        $settings = get_option( Aviation_Settings::OPTION_KEY, Aviation_Settings::get_defaults() );
        wp_localize_script( 'cahier-aviation-blocks', 'cahierAviationDefaults', [
            'settings' => $settings,
            'presets'  => Aviation_Settings::get_presets(),
        ] );

        wp_enqueue_style(
            'cahier-aviation',
            CAHIER_URL . 'modules/aviation/assets/css/aviation.css',
            [],
            CAHIER_VERSION
        );
    }

    public function enqueue_frontend_assets(): void {
        wp_register_style(
            'cahier-aviation',
            CAHIER_URL . 'modules/aviation/assets/css/aviation.css',
            [],
            CAHIER_VERSION
        );

        wp_register_script(
            'cahier-aviation-ticker',
            CAHIER_URL . 'modules/aviation/assets/js/aviation-ticker.js',
            [],
            CAHIER_VERSION,
            true
        );
    }

    public function has_settings_tab(): bool {
        return true;
    }

    public function handle_save_settings(): void {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( 'Unauthorized' );
        }

        check_admin_referer( 'cahier_aviation_save_action', 'cahier_aviation_nonce' );

        $defaults = Aviation_Settings::get_defaults();
        $posted   = $_POST['cahier_aviation'] ?? [];
        $settings = [];

        $settings['default_stations'] = sanitize_text_field( $posted['default_stations'] ?? $defaults['default_stations'] );
        $settings['bg_color']         = sanitize_hex_color( $posted['bg_color'] ?? $defaults['bg_color'] ) ?: $defaults['bg_color'];
        $settings['text_color']       = sanitize_hex_color( $posted['text_color'] ?? $defaults['text_color'] ) ?: $defaults['text_color'];
        $settings['border_color']     = sanitize_hex_color( $posted['border_color'] ?? $defaults['border_color'] ) ?: $defaults['border_color'];
        $settings['font_size']        = sanitize_text_field( $posted['font_size'] ?? $defaults['font_size'] );
        // Calibrated as Characters Per Second (CPS)
        $settings['speed']            = max( 4, min( 60, (int) ( $posted['speed'] ?? $defaults['speed'] ) ) );
        $settings['border_width']     = sanitize_text_field( $posted['border_width'] ?? $defaults['border_width'] );
        $settings['border_radius']    = sanitize_text_field( $posted['border_radius'] ?? $defaults['border_radius'] );
        $settings['padding']          = sanitize_text_field( $posted['padding'] ?? $defaults['padding'] );
        $settings['pause_on_hover']   = ! empty( $posted['pause_on_hover'] );
        $settings['show_category']    = ! empty( $posted['show_category'] );
        $settings['preset']           = sanitize_key( $posted['preset'] ?? 'custom' );

        update_option( Aviation_Settings::OPTION_KEY, $settings );

        wp_safe_redirect( admin_url( 'admin.php?page=cahier&tab=aviation&saved=1' ) );
        exit;
    }

    public function handle_import_json(): void {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( 'Unauthorized' );
        }

        check_admin_referer( 'cahier_aviation_json_action', 'cahier_aviation_json_nonce' );

        $json_str = '';
        if ( ! empty( $_FILES['import_file']['tmp_name'] ) ) {
            $json_str = (string) file_get_contents( $_FILES['import_file']['tmp_name'] );
        } elseif ( ! empty( $_POST['import_json_text'] ) ) {
            $json_str = wp_unslash( (string) $_POST['import_json_text'] );
        }

        $imported = Aviation_Settings::import_json( $json_str );
        if ( $imported ) {
            update_option( Aviation_Settings::OPTION_KEY, $imported );
            wp_safe_redirect( admin_url( 'admin.php?page=cahier&tab=aviation&imported=1' ) );
        } else {
            wp_safe_redirect( admin_url( 'admin.php?page=cahier&tab=aviation&error=invalid_json' ) );
        }
        exit;
    }

    public function render_settings_tab(): void {
        $settings = get_option( Aviation_Settings::OPTION_KEY, Aviation_Settings::get_defaults() );
        $presets  = Aviation_Settings::get_presets();
        ?>
        <div class="cahier-aviation-admin">
            <?php if ( isset( $_GET['saved'] ) ) : ?>
                <div class="cahier-notice"><p>Aviation parameters updated successfully.</p></div>
            <?php elseif ( isset( $_GET['imported'] ) ) : ?>
                <div class="cahier-notice"><p>Settings successfully imported from JSON configuration.</p></div>
            <?php elseif ( isset( $_GET['error'] ) ) : ?>
                <div class="cahier-notice" style="background:#fef2f2;border-color:#fecaca;color:#991b1b;"><p>Invalid JSON provided.</p></div>
            <?php endif; ?>

            <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="cahier-panel" style="margin-bottom: 2rem;">
                <input type="hidden" name="action" value="cahier_save_aviation_settings">
                <?php wp_nonce_field( 'cahier_aviation_save_action', 'cahier_aviation_nonce' ); ?>

                <h2 class="cahier-panel-title">Default Stations & Normalization</h2>
                <p style="font-size:0.875rem; color:#666; margin-bottom: 1rem;">
                    Accepts comma-separated, space-separated, or 3-letter US codes (e.g. <code>bos, orh, kmvy, jfk</code> expand to <code>KBOS, KORH, KMVY, KJFK</code>).
                </p>
                <div style="margin-bottom: 1.5rem;">
                    <label style="display:block; font-weight:600; margin-bottom: 4px;">Default Airport Codes</label>
                    <input type="text" name="cahier_aviation[default_stations]" value="<?php echo esc_attr( $settings['default_stations'] ); ?>" style="width:100%; max-width: 500px; font-family:var(--cahier-mono);" />
                </div>

                <h2 class="cahier-panel-title" style="margin-top: 2rem;">Design Presets</h2>
                <div style="display: flex; gap: 0.5rem; flex-wrap: wrap; margin-bottom: 1.5rem;">
                    <?php foreach ( $presets as $key => $preset ) : ?>
                        <button type="button" class="button button-secondary cahier-preset-btn"
                            data-bg="<?php echo esc_attr( $preset['bg_color'] ); ?>"
                            data-text="<?php echo esc_attr( $preset['text_color'] ); ?>"
                            data-border="<?php echo esc_attr( $preset['border_color'] ); ?>"
                            data-size="<?php echo esc_attr( $preset['font_size'] ); ?>"
                            data-speed="<?php echo esc_attr( (string) $preset['speed'] ); ?>"
                            data-radius="<?php echo esc_attr( $preset['border_radius'] ); ?>"
                            data-padding="<?php echo esc_attr( $preset['padding'] ); ?>"
                            data-category="<?php echo ! empty( $preset['show_category'] ) ? '1' : '0'; ?>"
                            data-preset="<?php echo esc_attr( $key ); ?>">
                            <?php echo esc_html( $preset['name'] ); ?>
                        </button>
                    <?php endforeach; ?>
                </div>
                <input type="hidden" id="cahier_preset_input" name="cahier_aviation[preset]" value="<?php echo esc_attr( $settings['preset'] ); ?>">

                <h2 class="cahier-panel-title">Visual Parameters</h2>
                <table class="cahier-table" style="max-width: 650px;">
                    <tbody>
                        <tr>
                            <th>Show Flight Category Pill</th>
                            <td>
                                <input type="checkbox" id="cahier_show_category" name="cahier_aviation[show_category]" value="1" <?php checked( ! empty( $settings['show_category'] ) ); ?>>
                                <span style="font-size:0.8125rem; color:#666; margin-left: 6px;">Display VFR/MVFR/IFR badge before each station report</span>
                            </td>
                        </tr>
                        <tr>
                            <th>Scroll Pace (Chars / Second)</th>
                            <td>
                                <input type="number" id="cahier_speed" name="cahier_aviation[speed]" value="<?php echo esc_attr( (string) $settings['speed'] ); ?>" min="4" max="60" style="width:120px;">
                                <span style="font-size:0.8125rem; color:#666; margin-left: 6px;">Keeps ticker speed identical whether 1 or 20 airports are active.</span>
                            </td>
                        </tr>
                        <tr>
                            <th>Background Color</th>
                            <td><input type="color" id="cahier_bg_color" name="cahier_aviation[bg_color]" value="<?php echo esc_attr( $settings['bg_color'] ); ?>"></td>
                        </tr>
                        <tr>
                            <th>Text Color</th>
                            <td><input type="color" id="cahier_text_color" name="cahier_aviation[text_color]" value="<?php echo esc_attr( $settings['text_color'] ); ?>"></td>
                        </tr>
                        <tr>
                            <th>Border Color</th>
                            <td><input type="color" id="cahier_border_color" name="cahier_aviation[border_color]" value="<?php echo esc_attr( $settings['border_color'] ); ?>"></td>
                        </tr>
                        <tr>
                            <th>Font Size</th>
                            <td><input type="text" id="cahier_font_size" name="cahier_aviation[font_size]" value="<?php echo esc_attr( $settings['font_size'] ); ?>" style="width:120px;"></td>
                        </tr>
                        <tr>
                            <th>Border Radius</th>
                            <td><input type="text" id="cahier_border_radius" name="cahier_aviation[border_radius]" value="<?php echo esc_attr( $settings['border_radius'] ); ?>" style="width:120px;"></td>
                        </tr>
                        <tr>
                            <th>Vertical Padding</th>
                            <td><input type="text" id="cahier_padding" name="cahier_aviation[padding]" value="<?php echo esc_attr( $settings['padding'] ); ?>" style="width:120px;"></td>
                        </tr>
                        <tr>
                            <th>Pause on Hover</th>
                            <td>
                                <input type="checkbox" name="cahier_aviation[pause_on_hover]" value="1" <?php checked( ! empty( $settings['pause_on_hover'] ) ); ?>>
                                <span style="font-size:0.8125rem; color:#666; margin-left: 6px;">Pause auto-scroll on cursor hover (drag/scrub remains active)</span>
                            </td>
                        </tr>
                    </tbody>
                </table>

                <p style="margin-top: 1.5rem;">
                    <button type="submit" class="button button-primary cahier-button">Save Aviation Settings</button>
                </p>
            </form>

            <div class="cahier-panel">
                <h2 class="cahier-panel-title">JSON Configuration Import / Export</h2>
                <p style="font-size:0.875rem; color:#666;">Export your configuration for safe storage or paste a JSON configuration string to restore.</p>
                
                <div style="margin-bottom: 1.5rem;">
                    <label style="display:block; font-weight:600; margin-bottom: 4px;">Current Configuration JSON</label>
                    <textarea readonly class="cahier-code-block" style="width:100%; height:120px; font-family:var(--cahier-mono); font-size:12px;"><?php echo esc_textarea( wp_json_encode( $settings, JSON_PRETTY_PRINT ) ); ?></textarea>
                </div>

                <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" enctype="multipart/form-data">
                    <input type="hidden" name="action" value="cahier_import_aviation_json">
                    <?php wp_nonce_field( 'cahier_aviation_json_action', 'cahier_aviation_json_nonce' ); ?>
                    
                    <div style="margin-bottom: 1rem;">
                        <label style="display:block; font-weight:600; margin-bottom: 4px;">Paste JSON to Import:</label>
                        <textarea name="import_json_text" placeholder="Paste JSON here..." style="width:100%; height:80px; font-family:var(--cahier-mono); font-size:12px;"></textarea>
                    </div>
                    <div style="margin-bottom: 1rem;">
                        <label style="display:block; font-weight:600; margin-bottom: 4px;">Or Upload JSON File:</label>
                        <input type="file" name="import_file" accept=".json">
                    </div>
                    <button type="submit" class="button button-secondary">Import JSON Configuration</button>
                </form>
            </div>
        </div>

        <script>
        document.querySelectorAll('.cahier-preset-btn').forEach(function(btn) {
            btn.addEventListener('click', function() {
                document.getElementById('cahier_bg_color').value = this.dataset.bg;
                document.getElementById('cahier_text_color').value = this.dataset.text;
                document.getElementById('cahier_border_color').value = this.dataset.border;
                document.getElementById('cahier_font_size').value = this.dataset.size;
                document.getElementById('cahier_speed').value = this.dataset.speed;
                document.getElementById('cahier_border_radius').value = this.dataset.radius;
                document.getElementById('cahier_padding').value = this.dataset.padding;
                document.getElementById('cahier_preset_input').value = this.dataset.preset;
                const catBox = document.getElementById('cahier_show_category');
                if (catBox) {
                    catBox.checked = this.dataset.category !== '0';
                }
            });
        });
        </script>
        <?php
    }
}