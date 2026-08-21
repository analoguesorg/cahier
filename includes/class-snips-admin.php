<?php
/**
 * Admin Configuration, Multi-Slot Conveyor & Developer Tools
 *
 * @package Analogues_Snips
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Snips_Admin {

    const OPTION_GROUP = 'analogues_snips_settings_group';
    const OPTION_NAME  = 'snips_settings';

    public function init() {
        add_action( 'admin_menu', array( $this, 'register_admin_menu' ) );
        add_action( 'admin_init', array( $this, 'register_settings' ) );
        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_assets' ) );
        add_action( 'wp_enqueue_scripts', array( $this, 'inject_frontend_typography' ), 20 );
        add_action( 'admin_post_snips_flush_transients', array( $this, 'handle_flush_transients' ) );
    }

    public function register_admin_menu() {
        add_menu_page(
            __( 'Snips Configuration', 'analogues-snips' ),
            __( 'Snips', 'analogues-snips' ),
            'manage_options',
            'snips-settings',
            array( $this, 'render_admin_page' ),
            'dashicons-editor-code',
            58
        );
    }

    public function enqueue_admin_assets( $hook ) {
        if ( 'toplevel_page_snips-settings' !== $hook ) {
            return;
        }

        if ( file_exists( SNIPS_PATH . 'assets/css/snips-admin.css' ) ) {
            wp_enqueue_style( 'snips-admin-css', SNIPS_URL . 'assets/css/snips-admin.css', array(), SNIPS_VERSION );
        }

        wp_add_inline_script(
            'jquery',
            '
            jQuery(document).ready(function($){
                $(".nav-tab-wrapper a").on("click", function(e){
                    e.preventDefault();
                    var targetTab = $(this).attr("href");

                    $(".nav-tab-wrapper a").removeClass("nav-tab-active");
                    $(this).addClass("nav-tab-active");

                    $(".snips-tab-content").hide();
                    $(targetTab).fadeIn(120);
                });

                var $toast = $("#snips-toast");
                if ($toast.length) {
                    setTimeout(function(){
                        $toast.addClass("fade-out");
                        setTimeout(function(){ $toast.remove(); }, 300);
                    }, 2600);
                }
            });
            '
        );
    }

    public function register_settings() {
        register_setting(
            self::OPTION_GROUP,
            self::OPTION_NAME,
            array(
                'type'              => 'array',
                'sanitize_callback' => array( $this, 'sanitize_settings' ),
                'default'           => $this->get_default_settings(),
            )
        );

        // Telegrams Section
        add_settings_section( 'snips_telegrams_section', '', null, 'snips-settings-telegrams' );
        add_settings_field( 'timestamp_mode', __( 'Timestamp Format', 'analogues-snips' ), array( $this, 'render_select_field' ), 'snips-settings-telegrams', 'snips_telegrams_section', array(
            'key'     => 'timestamp_mode',
            'options' => array(
                'local' => __( 'Local Device Time (Relative: "12m ago", "Aug 20")', 'analogues-snips' ),
                'utc'   => __( 'UTC Coordinated Universal Time (Explicit: "AUG 20, 2026 23:01 UTC")', 'analogues-snips' ),
            ),
        ) );
        add_settings_field( 'telegram_badge_label', __( 'Default Badge Label', 'analogues-snips' ), array( $this, 'render_text_field' ), 'snips-settings-telegrams', 'snips_telegrams_section', array( 'key' => 'telegram_badge_label', 'placeholder' => 'CURRENT INQUIRY' ) );
        add_settings_field( 'telegram_button_text', __( 'Action Button Label', 'analogues-snips' ), array( $this, 'render_text_field' ), 'snips-settings-telegrams', 'snips_telegrams_section', array( 'key' => 'telegram_button_text', 'placeholder' => 'Leave a Field Note ↓' ) );
        add_settings_field( 'telegram_footer_note', __( 'Footnote Copy', 'analogues-snips' ), array( $this, 'render_text_field' ), 'snips-settings-telegrams', 'snips_telegrams_section', array( 'key' => 'telegram_footer_note', 'placeholder' => 'Zero-login required • Open discussion' ) );

        // Discord Section
        add_settings_section( 'snips_discord_section', '', null, 'snips-settings-discord' );
        add_settings_field( 'default_discord_server', __( 'Default Server ID', 'analogues-snips' ), array( $this, 'render_text_field' ), 'snips-settings-discord', 'snips_discord_section', array( 'key' => 'default_discord_server', 'placeholder' => 'Enter Discord Server ID' ) );
        add_settings_field( 'threshold_green_hours', __( 'Active Threshold (Hours)', 'analogues-snips' ), array( $this, 'render_number_field' ), 'snips-settings-discord', 'snips_discord_section', array( 'key' => 'threshold_green_hours', 'min' => 1, 'max' => 168 ) );
        add_settings_field( 'threshold_yellow_hours', __( 'Idle Threshold (Hours)', 'analogues-snips' ), array( $this, 'render_number_field' ), 'snips-settings-discord', 'snips_discord_section', array( 'key' => 'threshold_yellow_hours', 'min' => 1, 'max' => 336 ) );

        // Typography Section
        add_settings_section( 'snips_typography_section', '', null, 'snips-settings-typo' );
        add_settings_field( 'global_font_family', __( 'Global Monospace Font', 'analogues-snips' ), array( $this, 'render_text_field' ), 'snips-settings-typo', 'snips_typography_section', array( 'key' => 'global_font_family' ) );
        add_settings_field( 'metar_font_family', __( 'METAR Module Font', 'analogues-snips' ), array( $this, 'render_text_field' ), 'snips-settings-typo', 'snips_typography_section', array( 'key' => 'metar_font_family' ) );

        // Developer Section
        add_settings_section( 'snips_developer_section', '', null, 'snips-settings-dev' );
        add_settings_field( 'dev_debug_mode', __( 'Debug Logging', 'analogues-snips' ), array( $this, 'render_select_field' ), 'snips-settings-dev', 'snips_developer_section', array(
            'key'     => 'dev_debug_mode',
            'options' => array(
                'disabled' => __( 'Disabled', 'analogues-snips' ),
                'enabled'  => __( 'Enabled (Console)', 'analogues-snips' ),
            ),
        ) );
        add_settings_field( 'custom_api_endpoint', __( 'Telemetry / Update Endpoint', 'analogues-snips' ), array( $this, 'render_text_field' ), 'snips-settings-dev', 'snips_developer_section', array(
            'key'         => 'custom_api_endpoint',
            'placeholder' => 'https://updates.analogues.org/api/v1',
            'description' => __( 'Optional endpoint for future auto-update servers or telemetry.', 'analogues-snips' ),
        ) );
    }

    public function get_default_settings() {
        return array(
            'timestamp_mode'         => 'local',
            'dispatch_windows'       => array(
                0 => array( 'telegram_id' => '', 'start' => '', 'end' => '' ),
                1 => array( 'telegram_id' => '', 'start' => '', 'end' => '' ),
                2 => array( 'telegram_id' => '', 'start' => '', 'end' => '' ),
                3 => array( 'telegram_id' => '', 'start' => '', 'end' => '' ),
            ),
            'telegram_badge_label'   => 'CURRENT INQUIRY',
            'telegram_button_text'   => 'Leave a Field Note ↓',
            'telegram_footer_note'   => 'Zero-login required • Open discussion',
            'default_discord_server' => '',
            'threshold_green_hours'  => 24,
            'threshold_yellow_hours' => 48,
            'global_font_family'     => 'var(--wp--preset--font-family--anonymous-pro, ui-monospace, monospace)',
            'metar_font_family'      => '',
            'dev_debug_mode'         => 'disabled',
            'custom_api_endpoint'    => '',
        );
    }

    public function sanitize_settings( $input ) {
        $defaults  = $this->get_default_settings();
        $existing  = get_option( self::OPTION_NAME, $defaults );
        $sanitized = is_array( $existing ) ? $existing : $defaults;

        if ( ! is_array( $input ) ) {
            return $sanitized;
        }

        if ( isset( $input['dispatch_windows'] ) && is_array( $input['dispatch_windows'] ) ) {
            $sanitized['dispatch_windows'] = array();
            for ( $i = 0; $i <= 3; $i++ ) {
                $sanitized['dispatch_windows'][ $i ] = array(
                    'telegram_id' => isset( $input['dispatch_windows'][ $i ]['telegram_id'] ) ? sanitize_text_field( $input['dispatch_windows'][ $i ]['telegram_id'] ) : '',
                    'start'       => isset( $input['dispatch_windows'][ $i ]['start'] ) ? sanitize_text_field( $input['dispatch_windows'][ $i ]['start'] ) : '',
                    'end'         => isset( $input['dispatch_windows'][ $i ]['end'] ) ? sanitize_text_field( $input['dispatch_windows'][ $i ]['end'] ) : '',
                );
            }
        }

        foreach ( $defaults as $key => $val ) {
            if ( 'dispatch_windows' === $key ) {
                continue;
            }
            if ( array_key_exists( $key, $input ) ) {
                $sanitized[ $key ] = is_numeric( $input[ $key ] ) ? intval( $input[ $key ] ) : sanitize_text_field( $input[ $key ] );
            }
        }

        return $sanitized;
    }

    public function handle_flush_transients() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'Unauthorized access.', 'analogues-snips' ) );
        }
        check_admin_referer( 'snips_flush_transients_action', 'snips_flush_nonce' );

        global $wpdb;
        $wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_snips_%' OR option_name LIKE '_transient_timeout_snips_%'" );

        wp_safe_redirect( add_query_arg( array( 'page' => 'snips-settings', 'settings-updated' => 'true' ), admin_url( 'admin.php' ) ) );
        exit;
    }

    public function render_text_field( $args ) {
        $options = get_option( self::OPTION_NAME, $this->get_default_settings() );
        $key     = $args['key'];
        $val     = isset( $options[ $key ] ) ? $options[ $key ] : '';

        printf(
            '<input type="text" id="%1$s" name="%2$s[%1$s]" value="%3$s" placeholder="%4$s" class="regular-text" />',
            esc_attr( $key ),
            esc_attr( self::OPTION_NAME ),
            esc_attr( $val ),
            ! empty( $args['placeholder'] ) ? esc_attr( $args['placeholder'] ) : ''
        );
        if ( ! empty( $args['description'] ) ) {
            printf( '<p class="description">%s</p>', esc_html( $args['description'] ) );
        }
    }

    public function render_select_field( $args ) {
        $options = get_option( self::OPTION_NAME, $this->get_default_settings() );
        $key     = $args['key'];
        $val     = isset( $options[ $key ] ) ? $options[ $key ] : '';

        echo '<select id="' . esc_attr( $key ) . '" name="' . esc_attr( self::OPTION_NAME ) . '[' . esc_attr( $key ) . ']">';
        foreach ( $args['options'] as $opt_key => $opt_label ) {
            printf( '<option value="%s" %s>%s</option>', esc_attr( $opt_key ), selected( $val, $opt_key, false ), esc_html( $opt_label ) );
        }
        echo '</select>';
    }

    public function render_number_field( $args ) {
        $options = get_option( self::OPTION_NAME, $this->get_default_settings() );
        $key     = $args['key'];
        $val     = isset( $options[ $key ] ) ? $options[ $key ] : $this->get_default_settings()[ $key ];

        printf(
            '<input type="number" id="%1$s" name="%2$s[%1$s]" value="%3$s" min="%4$d" max="%5$d" class="small-text" />',
            esc_attr( $key ),
            esc_attr( self::OPTION_NAME ),
            esc_attr( $val ),
            isset( $args['min'] ) ? intval( $args['min'] ) : 1,
            isset( $args['max'] ) ? intval( $args['max'] ) : 500
        );
        if ( ! empty( $args['description'] ) ) {
            printf( '<p class="description">%s</p>', esc_html( $args['description'] ) );
        }
    }

    public function inject_frontend_typography() {
        $options     = get_option( self::OPTION_NAME, $this->get_default_settings() );
        $global_font = ! empty( $options['global_font_family'] ) ? $options['global_font_family'] : 'ui-monospace, monospace';
        $metar_font  = ! empty( $options['metar_font_family'] ) ? $options['metar_font_family'] : 'var(--snip-global-font)';

        $custom_css = "
            :root {
                --snip-global-font: {$global_font};
                --snip-metar-font: {$metar_font};
            }
        ";

        wp_register_style( 'snips-global-vars', false );
        wp_enqueue_style( 'snips-global-vars' );
        wp_add_inline_style( 'snips-global-vars', $custom_css );
    }

    public function render_admin_page() {
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }

        $saved          = isset( $_GET['settings-updated'] ) && 'true' === $_GET['settings-updated'];
        $options        = get_option( self::OPTION_NAME, $this->get_default_settings() );
        $telegrams_mod  = new Snips_Telegrams();
        $active_data    = $telegrams_mod->get_active_telegram_data();
        $all_telegrams  = get_posts( array( 'post_type' => 'telegram', 'posts_per_page' => 50, 'post_status' => array( 'publish', 'draft' ) ) );
        $windows        = isset( $options['dispatch_windows'] ) ? $options['dispatch_windows'] : $this->get_default_settings()['dispatch_windows'];
        $json_preview   = wp_json_encode( $options, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES );
        ?>
        <div class="wrap snips-admin-wrap">
            <?php if ( $saved ) : ?>
                <div id="snips-toast" class="snips-toast-notification">
                    <span>✓ Configuration updated</span>
                </div>
            <?php endif; ?>

            <div class="snips-admin-header">
                <h1><?php printf( esc_html__( 'Snips Configuration (v%s)', 'analogues-snips' ), esc_html( SNIPS_VERSION ) ); ?></h1>
                <p class="description"><?php esc_html_e( 'Manage dispatch schedules, live chat, timing modes, and shortcodes.', 'analogues-snips' ); ?></p>
            </div>

            <!-- Direct .nav-tab anchors without breaking wrapper divs -->
            <nav class="nav-tab-wrapper snips-nav-tab-wrapper">
                <a href="#tab-telegrams" class="nav-tab nav-tab-active"><?php esc_html_e( 'Telegrams & Cadence', 'analogues-snips' ); ?></a>
                <a href="#tab-discord" class="nav-tab"><?php esc_html_e( 'Discord & Activity', 'analogues-snips' ); ?></a>
                <a href="#tab-typography" class="nav-tab"><?php esc_html_e( 'Typography', 'analogues-snips' ); ?></a>
                <a href="#tab-registry" class="nav-tab"><?php esc_html_e( 'Module Registry', 'analogues-snips' ); ?></a>
                <a href="#tab-developer" class="nav-tab snips-nav-tab-dev"><?php esc_html_e( 'Developer', 'analogues-snips' ); ?></a>
            </nav>

            <!-- TAB 1: Telegrams Multi-Window Ops -->
            <div id="tab-telegrams" class="snips-tab-content">
                <div class="snips-dashboard-grid">
                    <div class="snips-stat-box">
                        <span class="snips-stat-label"><?php esc_html_e( 'Active Ledger', 'analogues-snips' ); ?></span>
                        <span class="snips-stat-value"><?php echo ( $active_data && $active_data['post'] ) ? esc_html( get_the_title( $active_data['post'] ) ) : 'None'; ?></span>
                        <span class="snips-stat-sub"><?php echo ( $active_data && $active_data['post'] ) ? esc_html( get_the_date( 'M j, Y', $active_data['post'] ) ) : 'Publish a telegram dispatch'; ?></span>
                    </div>

                    <div class="snips-stat-box">
                        <span class="snips-stat-label"><?php esc_html_e( 'Current Window State', 'analogues-snips' ); ?></span>
                        <span class="snips-stat-value <?php echo ( $active_data && $active_data['is_overtime'] ) ? 'snip-text-overtime' : 'snip-text-active'; ?>">
                            <?php echo $active_data ? esc_html( $active_data['status_text'] ) : 'Standby'; ?>
                        </span>
                        <span class="snips-stat-sub">
                            <?php echo ( $active_data && $active_data['is_overtime'] ) ? esc_html__( 'Open forum mode active on latest published telegram', 'analogues-snips' ) : esc_html__( 'Currently in scheduled dispatch window', 'analogues-snips' ); ?>
                        </span>
                    </div>

                    <div class="snips-stat-box">
                        <span class="snips-stat-label"><?php esc_html_e( 'Pipeline Depth', 'analogues-snips' ); ?></span>
                        <span class="snips-stat-value"><?php echo count( $all_telegrams ); ?> Dispatches</span>
                        <span class="snips-stat-sub">
                            <a href="<?php echo esc_url( admin_url( 'post-new.php?post_type=telegram' ) ); ?>" class="button button-small" style="margin-top: 4px;"><?php esc_html_e( '+ Draft New Telegram', 'analogues-snips' ); ?></a>
                        </span>
                    </div>
                </div>

                <form method="post" action="options.php">
                    <?php settings_fields( self::OPTION_GROUP ); ?>

                    <div class="snips-card">
                        <h2><?php esc_html_e( 'Rotating Dispatch Conveyor (4 Slot Sequence)', 'analogues-snips' ); ?></h2>
                        <p class="description" style="margin-bottom: 20px;"><?php esc_html_e( 'When Slot 1 expires at the end of its final date, it archives into Slot 0, shifting remaining slots forward. If no window is active, the system runs in Open Forum mode.', 'analogues-snips' ); ?></p>

                        <div class="snips-slots-container">
                            <!-- Slot 0: Read-Only Archive -->
                            <div class="snips-slot-card snips-slot-archive">
                                <div class="snips-slot-header">
                                    <span class="snips-slot-indicator"></span>
                                    <strong><?php esc_html_e( 'Slot 0 (Completed / Archived Dispatch)', 'analogues-snips' ); ?></strong>
                                </div>
                                <div class="snips-slot-grid">
                                    <div class="snips-slot-field">
                                        <label><?php esc_html_e( 'Archived Telegram:', 'analogues-snips' ); ?></label>
                                        <select name="snips_settings[dispatch_windows][0][telegram_id]" class="widefat" style="opacity: 0.75;">
                                            <option value=""><?php esc_html_e( '-- Empty Archive --', 'analogues-snips' ); ?></option>
                                            <?php foreach ( $all_telegrams as $tel ) : ?>
                                                <option value="<?php echo esc_attr( $tel->ID ); ?>" <?php selected( isset( $windows[0]['telegram_id'] ) ? $windows[0]['telegram_id'] : '', $tel->ID ); ?>>
                                                    <?php echo esc_html( get_the_title( $tel ) ); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="snips-slot-field">
                                        <label><?php esc_html_e( 'Completed Start Date:', 'analogues-snips' ); ?></label>
                                        <input type="date" name="snips_settings[dispatch_windows][0][start]" value="<?php echo esc_attr( isset( $windows[0]['start'] ) ? $windows[0]['start'] : '' ); ?>" class="widefat" readonly />
                                    </div>
                                    <div class="snips-slot-field">
                                        <label><?php esc_html_e( 'Completed End Date:', 'analogues-snips' ); ?></label>
                                        <input type="date" name="snips_settings[dispatch_windows][0][end]" value="<?php echo esc_attr( isset( $windows[0]['end'] ) ? $windows[0]['end'] : '' ); ?>" class="widefat" readonly />
                                    </div>
                                </div>
                            </div>

                            <!-- Slots 1..3: Date-Only Slots -->
                            <?php
                            $slot_colors = array( 1 => 'emerald', 2 => 'cyan', 3 => 'amber' );
                            $slot_names  = array( 1 => 'Slot 1 (Active Dispatch Window)', 2 => 'Slot 2 (Upcoming Rotation)', 3 => 'Slot 3 (Future Pipeline)' );

                            for ( $i = 1; $i <= 3; $i++ ) :
                                $win    = isset( $windows[ $i ] ) ? $windows[ $i ] : array();
                                $sel_id = isset( $win['telegram_id'] ) ? $win['telegram_id'] : '';
                                $start  = isset( $win['start'] ) ? $win['start'] : '';
                                $end    = isset( $win['end'] ) ? $win['end'] : '';
                            ?>
                                <div class="snips-slot-card snips-slot-<?php echo esc_attr( $slot_colors[ $i ] ); ?>">
                                    <div class="snips-slot-header">
                                        <span class="snips-slot-indicator"></span>
                                        <strong><?php echo esc_html( $slot_names[ $i ] ); ?></strong>
                                    </div>
                                    <div class="snips-slot-grid">
                                        <div class="snips-slot-field">
                                            <label><?php esc_html_e( 'Assigned Telegram:', 'analogues-snips' ); ?></label>
                                            <select name="snips_settings[dispatch_windows][<?php echo $i; ?>][telegram_id]" class="widefat">
                                                <option value=""><?php esc_html_e( '-- Select Telegram --', 'analogues-snips' ); ?></option>
                                                <?php foreach ( $all_telegrams as $tel ) : ?>
                                                    <option value="<?php echo esc_attr( $tel->ID ); ?>" <?php selected( $sel_id, $tel->ID ); ?>>
                                                        <?php echo esc_html( get_the_title( $tel ) . ' (' . $tel->post_status . ')' ); ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                        <div class="snips-slot-field">
                                            <label><?php esc_html_e( 'Start Date:', 'analogues-snips' ); ?></label>
                                            <input type="date" name="snips_settings[dispatch_windows][<?php echo $i; ?>][start]" value="<?php echo esc_attr( $start ); ?>" class="widefat" />
                                        </div>
                                        <div class="snips-slot-field">
                                            <label><?php esc_html_e( 'End Date (Inclusive):', 'analogues-snips' ); ?></label>
                                            <input type="date" name="snips_settings[dispatch_windows][<?php echo $i; ?>][end]" value="<?php echo esc_attr( $end ); ?>" class="widefat" />
                                        </div>
                                    </div>
                                </div>
                            <?php endfor; ?>
                        </div>
                    </div>

                    <div class="snips-card">
                        <h2><?php esc_html_e( 'Ledger Defaults & Timing Format', 'analogues-snips' ); ?></h2>
                        <?php do_settings_sections( 'snips-settings-telegrams' ); ?>
                        <?php submit_button( __( 'Save Telegram Settings', 'analogues-snips' ) ); ?>
                    </div>
                </form>
            </div>

            <!-- TAB 2: Discord -->
            <div id="tab-discord" class="snips-tab-content" style="display: none;">
                <div class="snips-card">
                    <h2><?php esc_html_e( 'Discord Live Chat & Status Rules', 'analogues-snips' ); ?></h2>
                    <form method="post" action="options.php">
                        <?php
                        settings_fields( self::OPTION_GROUP );
                        do_settings_sections( 'snips-settings-discord' );
                        submit_button( __( 'Save Discord Settings', 'analogues-snips' ) );
                        ?>
                    </form>
                </div>
            </div>

            <!-- TAB 3: Typography -->
            <div id="tab-typography" class="snips-tab-content" style="display: none;">
                <div class="snips-card">
                    <h2><?php esc_html_e( 'Font Family Hierarchy', 'analogues-snips' ); ?></h2>
                    <form method="post" action="options.php">
                        <?php
                        settings_fields( self::OPTION_GROUP );
                        do_settings_sections( 'snips-settings-typo' );
                        submit_button( __( 'Save Typography Settings', 'analogues-snips' ) );
                        ?>
                    </form>
                </div>
            </div>

            <!-- TAB 4: Developer -->
            <div id="tab-developer" class="snips-tab-content" style="display: none;">
                <div class="snips-card">
                    <h2><?php esc_html_e( 'Developer Environment & Infrastructure', 'analogues-snips' ); ?></h2>
                    <p class="description" style="margin-bottom: 16px;"><?php esc_html_e( 'Configure debugging options and update/licensing endpoint parameters.', 'analogues-snips' ); ?></p>
                    <form method="post" action="options.php">
                        <?php
                        settings_fields( self::OPTION_GROUP );
                        do_settings_sections( 'snips-settings-dev' );
                        submit_button( __( 'Save Developer Settings', 'analogues-snips' ) );
                        ?>
                    </form>
                </div>

                <div class="snips-card">
                    <h2><?php esc_html_e( 'Cache Operations & Utilities', 'analogues-snips' ); ?></h2>
                    <p class="description" style="margin-bottom: 16px;"><?php esc_html_e( 'Flush cached API payloads without waiting for standard expiration windows.', 'analogues-snips' ); ?></p>
                    <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display: inline-block;">
                        <?php wp_nonce_field( 'snips_flush_transients_action', 'snips_flush_nonce' ); ?>
                        <input type="hidden" name="action" value="snips_flush_transients" />
                        <button type="submit" class="button button-secondary">
                            <?php esc_html_e( 'Purge METAR & Discord Transients', 'analogues-snips' ); ?>
                        </button>
                    </form>
                </div>

                <div class="snips-card">
                    <h2><?php esc_html_e( 'Raw Database Options Payload (JSON)', 'analogues-snips' ); ?></h2>
                    <p class="description" style="margin-bottom: 12px;"><?php esc_html_e( 'Read-only view of the snips_settings array currently persisted in wp_options.', 'analogues-snips' ); ?></p>
                    <pre class="snips-json-inspector"><code><?php echo esc_html( $json_preview ); ?></code></pre>
                </div>

                <div class="snips-card">
                    <h2><?php esc_html_e( 'Environment Telemetry', 'analogues-snips' ); ?></h2>
                    <table class="snips-table-aligned">
                        <tbody>
                            <tr>
                                <td style="width: 30%;"><strong><?php esc_html_e( 'Snips Build Version', 'analogues-snips' ); ?></strong></td>
                                <td><code>v<?php echo esc_html( SNIPS_VERSION ); ?></code></td>
                            </tr>
                            <tr>
                                <td><strong><?php esc_html_e( 'PHP Version', 'analogues-snips' ); ?></strong></td>
                                <td><code><?php echo esc_html( PHP_VERSION ); ?></code></td>
                            </tr>
                            <tr>
                                <td><strong><?php esc_html_e( 'Memory Limit', 'analogues-snips' ); ?></strong></td>
                                <td><code><?php echo esc_html( WP_MEMORY_LIMIT ); ?></code></td>
                            </tr>
                            <tr>
                                <td><strong><?php esc_html_e( 'Server Time (UTC)', 'analogues-snips' ); ?></strong></td>
                                <td><code><?php echo esc_html( gmdate( 'Y-m-d H:i:s' ) ); ?> UTC</code></td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- TAB 5: Module Registry -->
            <div id="tab-registry" class="snips-tab-content" style="display: none;">
                <div class="snips-card">
                    <h2><?php esc_html_e( 'Registered Modules & Shortcodes', 'analogues-snips' ); ?></h2>
                    <p class="description" style="margin-bottom: 16px;"><?php esc_html_e( 'Inspect registered Gutenberg blocks and shortcode tags across modules.', 'analogues-snips' ); ?></p>

                    <details class="snips-module-accordion" open>
                        <summary><span class="dashicons dashicons-format-status"></span> <?php esc_html_e( 'Telegrams & Inquiries Module', 'analogues-snips' ); ?></summary>
                        <div class="snips-accordion-content">
                            <table class="snips-table-aligned">
                                <thead>
                                    <tr>
                                        <th style="width: 30%;"><?php esc_html_e( 'Component', 'analogues-snips' ); ?></th>
                                        <th style="width: 15%;"><?php esc_html_e( 'Type', 'analogues-snips' ); ?></th>
                                        <th><?php esc_html_e( 'Identifier / Usage', 'analogues-snips' ); ?></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr>
                                        <td><strong>Snip Active Telegram</strong></td>
                                        <td><span class="snips-pill">Block</span></td>
                                        <td><code>snips/active-telegram</code> (Inquiry Deck + Open Forum State)</td>
                                    </tr>
                                    <tr>
                                        <td><strong>Cadence Countdown Pill</strong></td>
                                        <td><span class="snips-pill">Shortcode</span></td>
                                        <td><code class="snips-shortcode-tag">[snip_telegram_countdown href="/commons"]</code></td>
                                    </tr>
                                    <tr>
                                        <td><strong>Field Note Counter</strong></td>
                                        <td><span class="snips-pill">Shortcode</span></td>
                                        <td><code class="snips-shortcode-tag">[snip_telegram_stats]</code></td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </details>

                    <details class="snips-module-accordion" open>
                        <summary><span class="dashicons dashicons-format-chat"></span> <?php esc_html_e( 'Discord Live Module', 'analogues-snips' ); ?></summary>
                        <div class="snips-accordion-content">
                            <table class="snips-table-aligned">
                                <thead>
                                    <tr>
                                        <th style="width: 30%;"><?php esc_html_e( 'Component', 'analogues-snips' ); ?></th>
                                        <th style="width: 15%;"><?php esc_html_e( 'Type', 'analogues-snips' ); ?></th>
                                        <th><?php esc_html_e( 'Identifier / Usage', 'analogues-snips' ); ?></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr>
                                        <td><strong>Snip Discord Panel</strong></td>
                                        <td><span class="snips-pill">Block</span></td>
                                        <td><code>snips/discord-frame</code></td>
                                    </tr>
                                    <tr>
                                        <td><strong>Live Status Indicator</strong></td>
                                        <td><span class="snips-pill">Shortcode</span></td>
                                        <td><code class="snips-shortcode-tag">[snip_discord_status]</code></td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </details>

                    <details class="snips-module-accordion">
                        <summary><span class="dashicons dashicons-cloud"></span> <?php esc_html_e( 'METAR Weather Module', 'analogues-snips' ); ?></summary>
                        <div class="snips-accordion-content">
                            <table class="snips-table-aligned">
                                <thead>
                                    <tr>
                                        <th style="width: 30%;"><?php esc_html_e( 'Component', 'analogues-snips' ); ?></th>
                                        <th style="width: 15%;"><?php esc_html_e( 'Type', 'analogues-snips' ); ?></th>
                                        <th><?php esc_html_e( 'Identifier / Usage', 'analogues-snips' ); ?></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr>
                                        <td><strong>Live Ticker Tape</strong></td>
                                        <td><span class="snips-pill">Shortcode</span></td>
                                        <td><code class="snips-shortcode-tag">[snip_metar_ticker icao="KBOS,KORH"]</code></td>
                                    </tr>
                                    <tr>
                                        <td><strong>Flight Rule Badge</strong></td>
                                        <td><span class="snips-pill">Shortcode</span></td>
                                        <td><code class="snips-shortcode-tag">[snip_metar_category icao="KBOS"]</code></td>
                                    </tr>
                                    <tr>
                                        <td><strong>Raw Observation</strong></td>
                                        <td><span class="snips-pill">Shortcode</span></td>
                                        <td><code class="snips-shortcode-tag">[snip_metar_raw icao="KBOS"]</code></td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </details>

                    <details class="snips-module-accordion">
                        <summary><span class="dashicons dashicons-calendar-alt"></span> <?php esc_html_e( 'Date & Time Module', 'analogues-snips' ); ?></summary>
                        <div class="snips-accordion-content">
                            <table class="snips-table-aligned">
                                <thead>
                                    <tr>
                                        <th style="width: 30%;"><?php esc_html_e( 'Component', 'analogues-snips' ); ?></th>
                                        <th style="width: 15%;"><?php esc_html_e( 'Type', 'analogues-snips' ); ?></th>
                                        <th><?php esc_html_e( 'Identifier / Usage', 'analogues-snips' ); ?></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr>
                                        <td><strong>Dynamic Site Date</strong></td>
                                        <td><span class="snips-pill">Shortcode</span></td>
                                        <td><code class="snips-shortcode-tag">[snip_date format="F j, Y"]</code></td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </details>
                </div>
            </div>
        </div>
        <?php
    }
}