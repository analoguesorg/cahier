<?php
/**
 * SNIPS - Admin Page & Developer Tools
 *
 * Unified modular control center with JSON Preset Importers/Exporters for individual Snips modules.
 *
 * @package         Analogues_Snips
 * @branch          main
 * @version         main.7-snips-admin-php
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
        add_action( 'admin_post_snips_flush_transients', array( $this, 'handle_flush_transients' ) );
        add_action( 'admin_post_snips_export_module_preset', array( $this, 'handle_export_preset' ) );
        add_action( 'admin_post_snips_import_module_preset', array( $this, 'handle_import_preset' ) );
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
        if ( file_exists( SNIPS_PATH . 'assets/css/snips-metars.css' ) ) {
            wp_enqueue_style( 'snips-metars-css', SNIPS_URL . 'assets/css/snips-metars.css', array(), SNIPS_VERSION );
        }

        wp_add_inline_script(
            'jquery',
            '
            jQuery(document).ready(function($){
                // 1. Zero-Jump Hash & Tab Navigation
                function activateTab(targetTab) {
                    if (!targetTab || !$(targetTab).length) {
                        targetTab = "#tab-telegrams";
                    }
                    $(".nav-tab-wrapper a").removeClass("nav-tab-active");
                    $(".nav-tab-wrapper a[data-tab=\"" + targetTab + "\"]").addClass("nav-tab-active");
                    $(".snips-tab-content").hide();
                    $(targetTab).show();
                    if (window.history && window.history.replaceState) {
                        window.history.replaceState(null, null, targetTab);
                    }
                }

                var initialHash = window.location.hash;
                if (initialHash && $(initialHash).length) {
                    activateTab(initialHash);
                }

                $(".nav-tab-wrapper a").on("click", function(e){
                    e.preventDefault();
                    var targetTab = $(this).attr("data-tab");
                    activateTab(targetTab);
                });

                var $toast = $("#snips-toast");
                if ($toast.length) {
                    setTimeout(function(){
                        $toast.addClass("fade-out");
                        setTimeout(function(){ $toast.remove(); }, 300);
                    }, 2400);
                }

                $(document).on("change", ".snips-preset-select", function(){
                    var val = $(this).val();
                    if (val) {
                        $(this).closest(".snips-stack-control").find(".snips-fallback-input").val(val);
                    }
                });

                // 2. OpenType & Dual-Pane Studio Engine
                function updateStudio() {
                    var mode = $("input[name=\'studio_view_mode\']:checked").val();
                    if (mode === "split") {
                        $("#snips-pane-b-col").show();
                        $("#snips-studio-grid").css("grid-template-columns", "minmax(0, 1fr) minmax(0, 1fr)");
                    } else {
                        $("#snips-pane-b-col").hide();
                        $("#snips-studio-grid").css("grid-template-columns", "minmax(0, 1fr)");
                    }

                    var theme = $("#studio_theme_preset").val();
                    var bg = "#0c0d10", text = "#f4f4f5";
                    if (theme === "terminal") { bg = "#090a0d"; text = "#10b981"; }
                    else if (theme === "amber") { bg = "#0d0b07"; text = "#f59e0b"; }
                    else if (theme === "paper") { bg = "#ffffff"; text = "#0f172a"; }
                    else if (theme === "charcoal") { bg = "#18191c"; text = "#d4d4d8"; }
                    $(".snips-preview-canvas").css({"background-color": bg, "color": text});

                    // Collect OpenType Feature Flags
                    var activeFeatures = [];
                    if ($("#studio_feat_calt").is(":checked")) activeFeatures.push(\'"calt" 1\');
                    if ($("#studio_feat_liga").is(":checked")) activeFeatures.push(\'"liga" 1\');
                    if ($("#studio_feat_dlig").is(":checked")) activeFeatures.push(\'"dlig" 1\');
                    if ($("#studio_feat_hlig").is(":checked")) activeFeatures.push(\'"hlig" 1\');
                    if ($("#studio_feat_tnum").is(":checked")) activeFeatures.push(\'"tnum" 1\');
                    if ($("#studio_feat_pnum").is(":checked")) activeFeatures.push(\'"pnum" 1\');
                    if ($("#studio_feat_zero").is(":checked")) activeFeatures.push(\'"zero" 1\');
                    if ($("#studio_feat_onum").is(":checked")) activeFeatures.push(\'"onum" 1\');
                    if ($("#studio_feat_frac").is(":checked")) activeFeatures.push(\'"frac" 1\');
                    if ($("#studio_feat_smcp").is(":checked")) activeFeatures.push(\'"smcp" 1\');
                    if ($("#studio_feat_case").is(":checked")) activeFeatures.push(\'"case" 1\');

                    var customTags = $("#studio_custom_ot_tags").val();
                    if (customTags) {
                        var parts = customTags.split(",");
                        for (var i = 0; i < parts.length; i++) {
                            var tag = $.trim(parts[i]).toLowerCase();
                            if (tag.length === 4) {
                                activeFeatures.push(\'"\' + tag + \'" 1\');
                            }
                        }
                    }

                    var otString = activeFeatures.length ? activeFeatures.join(", ") : "normal";

                    var fontA = $("#pane_a_font").val();
                    var weightA = $("#pane_a_weight").val();
                    var styleA = $("#pane_a_style").val();
                    var sizeA = $("#pane_a_size").val() + "px";
                    var trackA = $("#pane_a_track").val() + "em";

                    $("#canvas_pane_a").css({
                        "font-family": fontA,
                        "font-weight": weightA,
                        "font-style": styleA,
                        "font-size": sizeA,
                        "letter-spacing": trackA,
                        "font-feature-settings": otString
                    });
                    $("#pane_a_size_val").text(sizeA);
                    $("#pane_a_track_val").text(trackA);

                    var fontB = $("#pane_b_font").val();
                    var weightB = $("#pane_b_weight").val();
                    var styleB = $("#pane_b_style").val();
                    var sizeB = $("#pane_b_size").val() + "px";
                    var trackB = $("#pane_b_track").val() + "em";

                    $("#canvas_pane_b").css({
                        "font-family": fontB,
                        "font-weight": weightB,
                        "font-style": styleB,
                        "font-size": sizeB,
                        "letter-spacing": trackB,
                        "font-feature-settings": otString
                    });
                    $("#pane_b_size_val").text(sizeB);
                    $("#pane_b_track_val").text(trackB);
                }

                $(document).on("input change", ".snips-studio-ctrl", updateStudio);

                $(document).on("click", ".snips-sample-btn", function(e){
                    e.preventDefault();
                    var sample = $(this).attr("data-sample");
                    var content = "";
                    if (sample === "pangram") content = "The quick brown fox jumps over the lazy dog. 0123456789";
                    else if (sample === "numbers") content = "0123456789 -> != == >= <= / * $ # @ & % 1/2 3/4 ( ) [ ] { }";
                    else if (sample === "prose") content = "Standard navigational avionics tracking steady radial. Airspeed nominal across the coastal approach waypoint.";
                    $(".snips-preview-canvas").text(content);
                });

                // 3. Live METAR Ticker & Badges Preview
                function updateMetarPreview() {
                    var bg = $("#metar_custom_bg").val() || "#0c0d10";
                    var text = $("#metar_custom_text").val() || "#10b981";
                    var border = $("#metar_custom_border").val() || "#1f1f23";
                    var radius = ($("#metar_custom_radius").val() || 4) + "px";
                    var size = ($("#metar_font_size").val() || 13) + "px";
                    var speed = ($("#metar_ticker_speed").val() || 30) + "s";

                    $("#metar_live_preview_wrap").css({
                        "background-color": bg,
                        "color": text,
                        "border-color": border,
                        "border-radius": radius,
                        "font-size": size
                    });
                    $("#metar_live_preview_track").css("animation-duration", speed);

                    var vfr = $("#metar_color_vfr").val() || "#10b981";
                    var mvfr = $("#metar_color_mvfr").val() || "#0284c7";
                    var ifr = $("#metar_color_ifr").val() || "#ef4444";
                    var lifr = $("#metar_color_lifr").val() || "#d946ef";

                    $("#preview_badge_vfr").css("background-color", vfr);
                    $("#preview_badge_mvfr").css("background-color", mvfr);
                    $("#preview_badge_ifr").css("background-color", ifr);
                    $("#preview_badge_lifr").css("background-color", lifr);
                }

                $(document).on("input change", ".snips-metar-ctrl", updateMetarPreview);

                $(document).on("change", "#metar_preset_picker", function(){
                    var preset = $(this).val();
                    if (preset === "terminal") {
                        $("#metar_custom_bg").val("#0c0d10");
                        $("#metar_custom_text").val("#10b981");
                        $("#metar_custom_border").val("#1f1f23");
                    } else if (preset === "amber") {
                        $("#metar_custom_bg").val("#0d0b07");
                        $("#metar_custom_text").val("#f59e0b");
                        $("#metar_custom_border").val("#292010");
                    } else if (preset === "paper") {
                        $("#metar_custom_bg").val("#ffffff");
                        $("#metar_custom_text").val("#0f172a");
                        $("#metar_custom_border").val("#e2e8f0");
                    } else if (preset === "slate") {
                        $("#metar_custom_bg").val("#18191c");
                        $("#metar_custom_text").val("#f4f4f5");
                        $("#metar_custom_border").val("#27272a");
                    }
                    updateMetarPreview();
                });

                updateStudio();
                updateMetarPreview();
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

        // Telegrams
        add_settings_section( 'snips_telegrams_section', '', null, 'snips-settings-telegrams' );
        add_settings_field( 'timestamp_mode', __( 'Timestamp Format', 'analogues-snips' ), array( $this, 'render_select_field' ), 'snips-settings-telegrams', 'snips_telegrams_section', array(
            'key'     => 'timestamp_mode',
            'options' => array(
                'local' => __( 'Local Device Time (Relative: "12m ago", "Aug 20")', 'analogues-snips' ),
                'utc'   => __( 'UTC Coordinated Universal Time (Explicit: "AUG 20, 2026 23:01 UTC")', 'analogues-snips' ),
            ),
        ) );
        add_settings_field( 'telegram_badge_label', __( 'Default Badge Label', 'analogues-snips' ), array( $this, 'render_text_field' ), 'snips-settings-telegrams', 'snips_telegrams_section', array( 'key' => 'telegram_badge_label', 'placeholder' => 'CURRENT INQUIRY' ) );
        add_settings_field( 'telegram_button_text', __( 'Action Button Label', 'analogues-snips' ), array( $this, 'render_text_field' ), 'snips-settings-telegrams', 'snips_telegrams_section', array( 'key' => 'telegram_button_text', 'placeholder' => 'Leave a Field Note ↗' ) );
        add_settings_field( 'telegram_footer_note', __( 'Footnote Copy', 'analogues-snips' ), array( $this, 'render_text_field' ), 'snips-settings-telegrams', 'snips_telegrams_section', array( 'key' => 'telegram_footer_note', 'placeholder' => 'Zero-login required • Open discussion' ) );

        // Discord
        add_settings_section( 'snips_discord_section', '', null, 'snips-settings-discord' );
        add_settings_field( 'default_discord_server', __( 'Default Server ID', 'analogues-snips' ), array( $this, 'render_text_field' ), 'snips-settings-discord', 'snips_discord_section', array( 'key' => 'default_discord_server', 'placeholder' => 'Enter Discord Server ID' ) );
        add_settings_field( 'threshold_green_hours', __( 'Active Threshold (Hours)', 'analogues-snips' ), array( $this, 'render_number_field' ), 'snips-settings-discord', 'snips_discord_section', array( 'key' => 'threshold_green_hours', 'min' => 1, 'max' => 168 ) );
        add_settings_field( 'threshold_yellow_hours', __( 'Idle Threshold (Hours)', 'analogues-snips' ), array( $this, 'render_number_field' ), 'snips-settings-discord', 'snips_discord_section', array( 'key' => 'threshold_yellow_hours', 'min' => 1, 'max' => 336 ) );

        // METAR
        add_settings_section( 'snips_metar_section', '', null, 'snips-settings-metar' );
        add_settings_field( 'metar_default_stations', __( 'Default Station Identifiers (ICAO)', 'analogues-snips' ), array( $this, 'render_text_field' ), 'snips-settings-metar', 'snips_metar_section', array( 'key' => 'metar_default_stations', 'placeholder' => 'KBOS, KJFK, KLGA', 'description' => 'Comma-separated ICAO airport codes.' ) );
        add_settings_field( 'metar_ticker_speed', __( 'Scroll Loop Duration (Seconds)', 'analogues-snips' ), array( $this, 'render_number_field' ), 'snips-settings-metar', 'snips_metar_section', array( 'key' => 'metar_ticker_speed', 'min' => 10, 'max' => 180, 'class' => 'snips-metar-ctrl' ) );
        add_settings_field( 'metar_font_size', __( 'Ticker Font Size (px)', 'analogues-snips' ), array( $this, 'render_number_field' ), 'snips-settings-metar', 'snips_metar_section', array( 'key' => 'metar_font_size', 'min' => 10, 'max' => 28, 'class' => 'snips-metar-ctrl' ) );
        add_settings_field( 'metar_divider', __( 'Observation Separator Symbol', 'analogues-snips' ), array( $this, 'render_text_field' ), 'snips-settings-metar', 'snips_metar_section', array( 'key' => 'metar_divider', 'placeholder' => '¦' ) );
        add_settings_field( 'metar_cache_ttl', __( 'Cache Lifetime (Minutes)', 'analogues-snips' ), array( $this, 'render_number_field' ), 'snips-settings-metar', 'snips_metar_section', array( 'key' => 'metar_cache_ttl', 'min' => 1, 'max' => 60 ) );

        // Date
        add_settings_section( 'snips_date_section', '', null, 'snips-settings-date' );
        add_settings_field( 'date_default_format', __( 'Default Date Output Format', 'analogues-snips' ), array( $this, 'render_text_field' ), 'snips-settings-date', 'snips_date_section', array( 'key' => 'date_default_format', 'placeholder' => 'F j, Y' ) );

        // Developer
        add_settings_section( 'snips_developer_section', '', null, 'snips-settings-dev' );
        add_settings_field( 'dev_debug_mode', __( 'Debug Logging', 'analogues-snips' ), array( $this, 'render_select_field' ), 'snips-settings-dev', 'snips_developer_section', array(
            'key'     => 'dev_debug_mode',
            'options' => array(
                'disabled' => __( 'Disabled', 'analogues-snips' ),
                'enabled'  => __( 'Enabled (Console)', 'analogues-snips' ),
            ),
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
            'telegram_button_text'   => 'Leave a Field Note ↗',
            'telegram_footer_note'   => 'Zero-login required • Open discussion',
            'default_discord_server' => '',
            'threshold_green_hours'  => 24,
            'threshold_yellow_hours' => 48,
            'metar_default_stations' => 'KBOS, KJFK, KLGA',
            'metar_ticker_speed'     => 30,
            'metar_font_size'        => 13,
            'metar_divider'          => '¦',
            'metar_cache_ttl'        => 10,
            'metar_custom_bg'        => '#0c0d10',
            'metar_custom_text'      => '#10b981',
            'metar_custom_border'    => '#1f1f23',
            'metar_custom_radius'    => 4,
            'metar_color_vfr'        => '#10b981',
            'metar_color_mvfr'       => '#0284c7',
            'metar_color_ifr'        => '#ef4444',
            'metar_color_lifr'       => '#d946ef',
            'date_default_format'    => 'F j, Y',
            'dev_debug_mode'         => 'disabled',
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

        wp_safe_redirect( add_query_arg( array( 'page' => 'snips-settings', 'settings-updated' => 'true' ), admin_url( 'admin.php#tab-developer' ) ) );
        exit;
    }

    public function handle_export_preset() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'Unauthorized user.', 'analogues-snips' ) );
        }

        check_admin_referer( 'snips_export_preset_action', 'snips_export_nonce' );

        $module   = isset( $_POST['module_key'] ) ? sanitize_text_field( $_POST['module_key'] ) : 'all';
        $options  = get_option( self::OPTION_NAME, $this->get_default_settings() );
        $payload  = array(
            'export_format' => 'snips_preset_v1',
            'module'        => $module,
            'timestamp'     => gmdate( 'c' ),
            'data'          => ( 'typography' === $module ) ? get_option( 'snips_registered_fonts', array() ) : $options,
        );

        $json_data = wp_json_encode( $payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES );
        $filename  = 'snips-' . sanitize_title( $module ) . '-preset-' . gmdate( 'Ymd-His' ) . '.json';

        header( 'Content-Type: application/json; charset=utf-8' );
        header( 'Content-Disposition: attachment; filename="' . $filename . '"' );
        header( 'Pragma: no-cache' );
        echo $json_data;
        exit;
    }

    public function handle_import_preset() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'Unauthorized user.', 'analogues-snips' ) );
        }

        check_admin_referer( 'snips_import_preset_action', 'snips_import_nonce' );

        $module   = isset( $_POST['module_key'] ) ? sanitize_text_field( $_POST['module_key'] ) : 'general';
        $redirect = isset( $_POST['return_tab'] ) ? sanitize_text_field( $_POST['return_tab'] ) : 'tab-developer';
        $json_str = '';

        if ( ! empty( $_FILES['preset_file']['tmp_name'] ) ) {
            $json_str = file_get_contents( $_FILES['preset_file']['tmp_name'] );
        } elseif ( ! empty( $_POST['preset_json_raw'] ) ) {
            $json_str = wp_unslash( trim( $_POST['preset_json_raw'] ) );
        }

        if ( ! empty( $json_str ) ) {
            $parsed = json_decode( $json_str, true );
            if ( is_array( $parsed ) && isset( $parsed['data'] ) ) {
                if ( 'typography' === $module ) {
                    update_option( 'snips_registered_fonts', $parsed['data'] );
                } else {
                    $existing = get_option( self::OPTION_NAME, $this->get_default_settings() );
                    $merged   = array_merge( $existing, $parsed['data'] );
                    update_option( self::OPTION_NAME, $merged );
                }
            }
        }

        wp_safe_redirect( add_query_arg( array( 'page' => 'snips-settings', 'settings-updated' => 'true' ), admin_url( 'admin.php#' . $redirect ) ) );
        exit;
    }

    public function render_text_field( $args ) {
        $options = get_option( self::OPTION_NAME, $this->get_default_settings() );
        $key     = $args['key'];
        $val     = isset( $options[ $key ] ) ? $options[ $key ] : '';
        $class   = isset( $args['class'] ) ? $args['class'] : 'regular-text';

        printf(
            '<input type="text" id="%1$s" name="%2$s[%1$s]" value="%3$s" placeholder="%4$s" class="%5$s" />',
            esc_attr( $key ),
            esc_attr( self::OPTION_NAME ),
            esc_attr( $val ),
            ! empty( $args['placeholder'] ) ? esc_attr( $args['placeholder'] ) : '',
            esc_attr( $class )
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
        $class   = isset( $args['class'] ) ? $args['class'] : 'small-text';

        printf(
            '<input type="number" id="%1$s" name="%2$s[%1$s]" value="%3$s" min="%4$d" max="%5$d" class="%6$s" />',
            esc_attr( $key ),
            esc_attr( self::OPTION_NAME ),
            esc_attr( $val ),
            isset( $args['min'] ) ? intval( $args['min'] ) : 1,
            isset( $args['max'] ) ? intval( $args['max'] ) : 500,
            esc_attr( $class )
        );

        if ( ! empty( $args['description'] ) ) {
            printf( '<p class="description">%s</p>', esc_html( $args['description'] ) );
        }
    }

    public static function render_preset_drawer( $module_key, $module_title, $tab_id ) {
        ?>
        <div class="snips-preset-drawer">
            <details>
                <summary><span class="dashicons dashicons-download"></span> <?php printf( esc_html__( 'Backup & Presets: %s', 'analogues-snips' ), esc_html( $module_title ) ); ?></summary>
                <div class="snips-preset-drawer-content">
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
                        <div>
                            <h4><?php esc_html_e( 'Export Configuration', 'analogues-snips' ); ?></h4>
                            <p class="description"><?php esc_html_e( 'Download settings as a structured JSON file.', 'analogues-snips' ); ?></p>
                            <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="margin-top: 10px;">
                                <?php wp_nonce_field( 'snips_export_preset_action', 'snips_export_nonce' ); ?>
                                <input type="hidden" name="action" value="snips_export_module_preset" />
                                <input type="hidden" name="module_key" value="<?php echo esc_attr( $module_key ); ?>" />
                                <button type="submit" class="button button-secondary">
                                    ↓ <?php esc_html_e( 'Export Preset (.json)', 'analogues-snips' ); ?>
                                </button>
                            </form>
                        </div>
                        <div>
                            <h4><?php esc_html_e( 'Import Configuration', 'analogues-snips' ); ?></h4>
                            <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" enctype="multipart/form-data">
                                <?php wp_nonce_field( 'snips_import_preset_action', 'snips_import_nonce' ); ?>
                                <input type="hidden" name="action" value="snips_import_module_preset" />
                                <input type="hidden" name="module_key" value="<?php echo esc_attr( $module_key ); ?>" />
                                <input type="hidden" name="return_tab" value="<?php echo esc_attr( $tab_id ); ?>" />

                                <div style="margin-bottom: 8px;">
                                    <input type="file" name="preset_file" accept=".json" style="font-size: 0.8rem;" />
                                </div>
                                <div style="margin-bottom: 10px;">
                                    <textarea name="preset_json_raw" placeholder="Or paste raw JSON preset string here..." rows="2" class="widefat" style="font-family: ui-monospace, monospace; font-size: 0.75rem;"></textarea>
                                </div>
                                <button type="submit" class="button button-secondary" onclick="return confirm('Importing will overwrite current settings for this module. Continue?');">
                                    ↑ <?php esc_html_e( 'Apply Preset', 'analogues-snips' ); ?>
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            </details>
        </div>
        <?php
    }

    public function render_admin_page() {
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }

        $saved         = isset( $_GET['settings-updated'] ) && 'true' === $_GET['settings-updated'];
        $options       = get_option( self::OPTION_NAME, $this->get_default_settings() );
        $telegrams_mod = new Snips_Telegrams();
        $active_data   = $telegrams_mod->get_active_telegram_data();
        $all_telegrams = get_posts( array( 'post_type' => 'telegram', 'posts_per_page' => 50, 'post_status' => array( 'publish', 'draft' ) ) );
        $windows       = isset( $options['dispatch_windows'] ) ? $options['dispatch_windows'] : $this->get_default_settings()['dispatch_windows'];
        $json_preview  = wp_json_encode( $options, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES );
        ?>
        <div class="wrap snips-admin-wrap">
            <?php if ( $saved ) : ?>
                <div id="snips-toast" class="snips-toast-notification">
                    <span>✓ Configuration synchronized</span>
                </div>
            <?php endif; ?>

            <div class="snips-admin-header">
                <h1><?php printf( esc_html__( 'Snips Configuration (v%s)', 'analogues-snips' ), esc_html( SNIPS_VERSION ) ); ?></h1>
                <p class="description"><?php esc_html_e( 'Modular control center for dispatches, typography studio, atmospheric telemetry, and presets.', 'analogues-snips' ); ?></p>
            </div>

            <nav class="nav-tab-wrapper snips-nav-tab-wrapper">
                <a href="#tab-telegrams" data-tab="#tab-telegrams" class="nav-tab nav-tab-active" data-color="emerald">
                    <span class="snips-tab-title"><?php esc_html_e( 'Telegrams & Cadence', 'analogues-snips' ); ?></span>
                    <span class="snips-tab-bar"></span>
                </a>
                <a href="#tab-typography" data-tab="#tab-typography" class="nav-tab" data-color="sky">
                    <span class="snips-tab-title"><?php esc_html_e( 'Typography Studio', 'analogues-snips' ); ?></span>
                    <span class="snips-tab-bar"></span>
                </a>
                <a href="#tab-metar" data-tab="#tab-metar" class="nav-tab" data-color="amber">
                    <span class="snips-tab-title"><?php esc_html_e( 'METAR Weather', 'analogues-snips' ); ?></span>
                    <span class="snips-tab-bar"></span>
                </a>
                <a href="#tab-discord" data-tab="#tab-discord" class="nav-tab" data-color="indigo">
                    <span class="snips-tab-title"><?php esc_html_e( 'Discord Live', 'analogues-snips' ); ?></span>
                    <span class="snips-tab-bar"></span>
                </a>
                <a href="#tab-date" data-tab="#tab-date" class="nav-tab" data-color="purple">
                    <span class="snips-tab-title"><?php esc_html_e( 'Dynamic Date', 'analogues-snips' ); ?></span>
                    <span class="snips-tab-bar"></span>
                </a>
                <a href="#tab-registry" data-tab="#tab-registry" class="nav-tab" data-color="slate">
                    <span class="snips-tab-title"><?php esc_html_e( 'Module Registry', 'analogues-snips' ); ?></span>
                    <span class="snips-tab-bar"></span>
                </a>
                <a href="#tab-developer" data-tab="#tab-developer" class="nav-tab snips-nav-tab-dev" data-color="rose">
                    <span class="snips-tab-title"><?php esc_html_e( 'Developer', 'analogues-snips' ); ?></span>
                    <span class="snips-tab-bar"></span>
                </a>
            </nav>

            <!-- TAB 1: Telegrams -->
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

                <?php self::render_preset_drawer( 'telegrams', 'Telegrams & Cadence', 'tab-telegrams' ); ?>
            </div>

            <!-- TAB 2: Typography Studio & Manager -->
            <div id="tab-typography" class="snips-tab-content" style="display: none;">
                <?php $stored_fonts = Snips_Typography::get_all_fonts(); ?>

                <!-- 1. Dual-Pane Specimen Testing Studio -->
                <div class="snips-card">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 14px;">
                        <h2 style="margin: 0;"><?php esc_html_e( 'Type Specimen Studio & Comparator', 'analogues-snips' ); ?></h2>
                        <div style="display: flex; gap: 14px; align-items: center;">
                            <label style="font-size: 0.8rem; font-weight: 600; cursor: pointer;">
                                <input type="radio" name="studio_view_mode" value="single" class="snips-studio-ctrl" checked /> Single View
                            </label>
                            <label style="font-size: 0.8rem; font-weight: 600; cursor: pointer;">
                                <input type="radio" name="studio_view_mode" value="split" class="snips-studio-ctrl" /> Split Comparison (50 / 50)
                            </label>
                        </div>
                    </div>

                    <!-- OpenType Features Switchboard -->
                    <div style="background: #f8fafc; border: 1px solid #e2e8f0; padding: 14px 16px; border-radius: 4px; margin-bottom: 16px;">
                        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px;">
                            <span style="font-size: 0.75rem; font-weight: 700; text-transform: uppercase; color: #475569;">OpenType Layout Features</span>
                            <div style="display: flex; gap: 8px;">
                                <button type="button" class="button button-small snips-sample-btn" data-sample="pangram">Pangram</button>
                                <button type="button" class="button button-small snips-sample-btn" data-sample="numbers">Numerics / Code</button>
                                <button type="button" class="button button-small snips-sample-btn" data-sample="prose">Editorial Prose</button>
                            </div>
                        </div>

                        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 8px; font-size: 0.8rem; margin-bottom: 12px;">
                            <label><input type="checkbox" id="studio_feat_calt" class="snips-studio-ctrl" checked /> Contextual Alternates (<code>calt</code>)</label>
                            <label><input type="checkbox" id="studio_feat_liga" class="snips-studio-ctrl" checked /> Standard Ligatures (<code>liga</code>)</label>
                            <label><input type="checkbox" id="studio_feat_dlig" class="snips-studio-ctrl" /> Discretionary (<code>dlig</code>)</label>
                            <label><input type="checkbox" id="studio_feat_hlig" class="snips-studio-ctrl" /> Historical (<code>hlig</code>)</label>
                            <label><input type="checkbox" id="studio_feat_tnum" class="snips-studio-ctrl" /> Tabular Figures (<code>tnum</code>)</label>
                            <label><input type="checkbox" id="studio_feat_pnum" class="snips-studio-ctrl" /> Proportional (<code>pnum</code>)</label>
                            <label><input type="checkbox" id="studio_feat_zero" class="snips-studio-ctrl" /> Slashed Zero (<code>zero</code>)</label>
                            <label><input type="checkbox" id="studio_feat_onum" class="snips-studio-ctrl" /> Oldstyle Figures (<code>onum</code>)</label>
                            <label><input type="checkbox" id="studio_feat_frac" class="snips-studio-ctrl" /> Fractions (<code>frac</code>)</label>
                            <label><input type="checkbox" id="studio_feat_smcp" class="snips-studio-ctrl" /> Small Caps (<code>smcp</code>)</label>
                            <label><input type="checkbox" id="studio_feat_case" class="snips-studio-ctrl" /> Case Forms (<code>case</code>)</label>
                        </div>

                        <div style="display: flex; gap: 12px; align-items: center; border-top: 1px dashed #cbd5e1; padding-top: 10px;">
                            <span style="font-size: 0.75rem; font-weight: 700; text-transform: uppercase; color: #64748b; white-space: nowrap;">Stylistic Sets / Tags:</span>
                            <input type="text" id="studio_custom_ot_tags" placeholder="e.g. ss01, ss02, cv01, salt, swsh, titl" class="widefat snips-studio-ctrl" style="font-size: 0.8rem;" />
                            <select id="studio_theme_preset" class="snips-studio-ctrl" style="font-size: 0.8rem; min-width: 140px;">
                                <option value="dark">Pitch Dark</option>
                                <option value="terminal">Terminal Glow</option>
                                <option value="amber">Amber CRT</option>
                                <option value="paper">Paper Light</option>
                                <option value="charcoal">Muted Charcoal</option>
                            </select>
                        </div>
                    </div>

                    <!-- Viewport Grid (Symmetrical 50/50 Layout) -->
                    <div id="snips-studio-grid" style="display: grid; grid-template-columns: minmax(0, 1fr); gap: 16px; width: 100%;">
                        <!-- Pane A Column -->
                        <div style="min-width: 0; display: flex; flex-direction: column; gap: 8px;">
                            <div style="display: flex; gap: 10px; align-items: center; background: #f1f5f9; padding: 8px 12px; border-radius: 4px; font-size: 0.8rem; flex-wrap: wrap;">
                                <strong>Pane A:</strong>
                                <select id="pane_a_font" class="snips-studio-ctrl">
                                    <option value="ui-monospace, 'Courier New', Courier, monospace">Courier (System Mono)</option>
                                    <option value="system-ui, -apple-system, Helvetica, Arial, sans-serif">Helvetica (System Sans)</option>
                                    <option value="ui-serif, 'Times New Roman', Times, Georgia, serif">Times New Roman (System Serif)</option>
                                    <?php foreach ( $stored_fonts as $slug => $font ) : ?>
                                        <option value="'SnipsPreview_<?php echo esc_attr( $slug ); ?>', <?php echo esc_attr( $font['fallback'] ); ?>">
                                            <?php echo esc_html( $font['name'] ); ?> <?php echo ! empty( $font['active'] ) ? '(Active)' : '(Inactive)'; ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <select id="pane_a_weight" class="snips-studio-ctrl">
                                    <option value="400">Regular (400)</option>
                                    <option value="450">Book (450)</option>
                                    <option value="500">Medium (500)</option>
                                    <option value="600">SemiBold (600)</option>
                                    <option value="700">Bold (700)</option>
                                    <option value="900">Black (900)</option>
                                </select>
                                <select id="pane_a_style" class="snips-studio-ctrl">
                                    <option value="normal">Normal</option>
                                    <option value="italic">Italic</option>
                                </select>
                                <div style="display: flex; align-items: center; gap: 6px; margin-left: auto;">
                                    <span>Size:</span>
                                    <input type="range" id="pane_a_size" class="snips-studio-ctrl" min="12" max="72" value="22" style="width: 80px;" />
                                    <span id="pane_a_size_val" style="min-width: 34px;">22px</span>
                                </div>
                                <div style="display: flex; align-items: center; gap: 6px;">
                                    <span>Track:</span>
                                    <input type="range" id="pane_a_track" class="snips-studio-ctrl" min="-0.05" max="0.25" step="0.01" value="0" style="width: 70px;" />
                                    <span id="pane_a_track_val" style="min-width: 38px;">0em</span>
                                </div>
                            </div>
                            <div id="canvas_pane_a" class="snips-preview-canvas" contenteditable="true" spellcheck="false" style="padding: 24px; min-height: 140px; border: 1px solid #1f1f23; border-radius: 4px; outline: none; overflow-wrap: break-word; word-break: break-word;">
                                The quick brown fox jumps over the lazy dog. 0123456789
                            </div>
                        </div>

                        <!-- Pane B Column -->
                        <div id="snips-pane-b-col" style="min-width: 0; display: none; flex-direction: column; gap: 8px;">
                            <div style="display: flex; gap: 10px; align-items: center; background: #f1f5f9; padding: 8px 12px; border-radius: 4px; font-size: 0.8rem; flex-wrap: wrap;">
                                <strong>Pane B:</strong>
                                <select id="pane_b_font" class="snips-studio-ctrl">
                                    <option value="ui-monospace, 'Courier New', Courier, monospace">Courier (System Mono)</option>
                                    <option value="system-ui, -apple-system, Helvetica, Arial, sans-serif">Helvetica (System Sans)</option>
                                    <option value="ui-serif, 'Times New Roman', Times, Georgia, serif">Times New Roman (System Serif)</option>
                                    <?php foreach ( $stored_fonts as $slug => $font ) : ?>
                                        <option value="'SnipsPreview_<?php echo esc_attr( $slug ); ?>', <?php echo esc_attr( $font['fallback'] ); ?>">
                                            <?php echo esc_html( $font['name'] ); ?> <?php echo ! empty( $font['active'] ) ? '(Active)' : '(Inactive)'; ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <select id="pane_b_weight" class="snips-studio-ctrl">
                                    <option value="400">Regular (400)</option>
                                    <option value="450">Book (450)</option>
                                    <option value="500">Medium (500)</option>
                                    <option value="600">SemiBold (600)</option>
                                    <option value="700">Bold (700)</option>
                                    <option value="900">Black (900)</option>
                                </select>
                                <select id="pane_b_style" class="snips-studio-ctrl">
                                    <option value="normal">Normal</option>
                                    <option value="italic">Italic</option>
                                </select>
                                <div style="display: flex; align-items: center; gap: 6px; margin-left: auto;">
                                    <span>Size:</span>
                                    <input type="range" id="pane_b_size" class="snips-studio-ctrl" min="12" max="72" value="22" style="width: 80px;" />
                                    <span id="pane_b_size_val" style="min-width: 34px;">22px</span>
                                </div>
                                <div style="display: flex; align-items: center; gap: 6px;">
                                    <span>Track:</span>
                                    <input type="range" id="pane_b_track" class="snips-studio-ctrl" min="-0.05" max="0.25" step="0.01" value="0" style="width: 70px;" />
                                    <span id="pane_b_track_val" style="min-width: 38px;">0em</span>
                                </div>
                            </div>
                            <div id="canvas_pane_b" class="snips-preview-canvas" contenteditable="true" spellcheck="false" style="padding: 24px; min-height: 140px; border: 1px solid #1f1f23; border-radius: 4px; outline: none; overflow-wrap: break-word; word-break: break-word;">
                                The quick brown fox jumps over the lazy dog. 0123456789
                            </div>
                        </div>
                    </div>
                </div>

                <!-- 2. Smart Multi-File Batch Ingestion -->
                <div class="snips-card">
                    <h2><?php esc_html_e( 'Batch Install Typeface Family', 'analogues-snips' ); ?></h2>
                    <p class="description" style="margin-bottom: 20px;">
                        <?php esc_html_e( 'Select all font variant files at once (.woff2, .woff, .ttf, .otf). Snips classifies Book (450), Thin to Black, and Italics automatically.', 'analogues-snips' ); ?>
                    </p>

                    <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" enctype="multipart/form-data">
                        <?php wp_nonce_field( 'snips_upload_font_action', 'snips_font_nonce' ); ?>
                        <input type="hidden" name="action" value="snips_upload_font_family" />

                        <div style="display: grid; grid-template-columns: 1fr 1.5fr; gap: 20px; margin-bottom: 20px;">
                            <div>
                                <label for="font_family_name" style="display:block; font-weight:600; margin-bottom:6px;">
                                    <?php esc_html_e( 'Typeface Family Name *', 'analogues-snips' ); ?>
                                </label>
                                <input type="text" id="font_family_name" name="font_family_name" placeholder="e.g. Courier, Helvetica, Times New Roman" class="widefat" required />
                            </div>
                            <div class="snips-stack-control">
                                <label style="display:block; font-weight:600; margin-bottom:6px;">
                                    <?php esc_html_e( 'Fallback Stack & Standard Presets *', 'analogues-snips' ); ?>
                                </label>
                                <div style="display: flex; gap: 8px;">
                                    <input type="text" name="font_fallback_stack" value="ui-monospace, monospace" class="widefat snips-fallback-input" required />
                                    <select class="snips-preset-select" style="max-width: 170px;">
                                        <option value=""><?php esc_html_e( 'Standard Presets...', 'analogues-snips' ); ?></option>
                                        <option value="ui-monospace, 'Courier New', Courier, Monaco, Consolas, monospace">Mono (Standard Courier)</option>
                                        <option value="system-ui, -apple-system, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif">Sans (Standard Helvetica / Arial)</option>
                                        <option value="ui-serif, 'Times New Roman', Times, Georgia, serif">Serif (Standard Times / Georgia)</option>
                                    </select>
                                </div>
                            </div>
                        </div>

                        <div style="background: #f8fafc; border: 2px dashed #cbd5e1; padding: 24px; border-radius: 6px; text-align: center; margin-bottom: 20px;">
                            <label for="font_batch_files" style="display: block; font-size: 0.95rem; font-weight: 700; color: #1e293b; margin-bottom: 8px; cursor: pointer;">
                                <?php esc_html_e( 'Select or Drag All Family Files at Once', 'analogues-snips' ); ?>
                            </label>
                            <input type="file" id="font_batch_files" name="font_batch_files[]" multiple accept=".woff2,.woff,.ttf,.otf" required style="font-size: 0.85rem;" />
                        </div>

                        <?php submit_button( __( 'Parse & Register Typeface Family', 'analogues-snips' ) ); ?>
                    </form>
                </div>

                <!-- 3. Installed Typeface Families Manager -->
                <div class="snips-card">
                    <h2><?php esc_html_e( 'Registered Typeface Families', 'analogues-snips' ); ?></h2>
                    <?php if ( empty( $stored_fonts ) ) : ?>
                        <p class="description"><?php esc_html_e( 'No custom typeface families installed yet.', 'analogues-snips' ); ?></p>
                    <?php else : ?>
                        <?php foreach ( $stored_fonts as $slug => $font ) : ?>
                            <div style="background: #ffffff; border: 1px solid #e2e8f0; border-radius: 4px; padding: 20px; margin-bottom: 24px;">
                                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px; border-bottom: 1px solid #f1f5f9; padding-bottom: 12px;">
                                    <div>
                                        <div style="display: flex; align-items: center; gap: 10px;">
                                            <h3 style="margin: 0; font-size: 1.2rem; font-family: 'SnipsPreview_<?php echo esc_attr( $slug ); ?>', monospace;">
                                                <?php echo esc_html( $font['name'] ); ?>
                                            </h3>
                                            <?php if ( ! empty( $font['active'] ) ) : ?>
                                                <span class="snips-pill" style="background: #ecfdf5; color: #059669; border-color: #a7f3d0;">Active in WordPress</span>
                                            <?php else : ?>
                                                <span class="snips-pill" style="background: #f1f5f9; color: #64748b;">Inactive (Testing Only)</span>
                                            <?php endif; ?>
                                        </div>
                                        <code style="font-size: 0.74rem; color: #64748b;">--wp--preset--font-family--<?php echo esc_html( $slug ); ?></code>
                                    </div>

                                    <div style="display: flex; align-items: center; gap: 10px;">
                                        <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="margin: 0;">
                                            <?php wp_nonce_field( 'snips_toggle_font_action', 'snips_toggle_nonce' ); ?>
                                            <input type="hidden" name="action" value="snips_toggle_font_status" />
                                            <input type="hidden" name="font_slug" value="<?php echo esc_attr( $slug ); ?>" />
                                            <button type="submit" class="button <?php echo ! empty( $font['active'] ) ? 'button-secondary' : 'button-primary'; ?>">
                                                <?php echo ! empty( $font['active'] ) ? esc_html__( 'Deactivate from Site', 'analogues-snips' ) : esc_html__( 'Activate on Site', 'analogues-snips' ); ?>
                                            </button>
                                        </form>

                                        <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="margin: 0;">
                                            <?php wp_nonce_field( 'snips_delete_font_action', 'snips_delete_nonce' ); ?>
                                            <input type="hidden" name="action" value="snips_delete_font_family" />
                                            <input type="hidden" name="font_slug" value="<?php echo esc_attr( $slug ); ?>" />
                                            <button type="submit" class="button button-link-delete" onclick="return confirm('Delete this typeface family and remove its font files?');">
                                                <?php esc_html_e( 'Delete', 'analogues-snips' ); ?>
                                            </button>
                                        </form>
                                    </div>
                                </div>

                                <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" enctype="multipart/form-data">
                                    <?php wp_nonce_field( 'snips_update_font_action', 'snips_font_nonce' ); ?>
                                    <input type="hidden" name="action" value="snips_update_font_family" />
                                    <input type="hidden" name="font_slug" value="<?php echo esc_attr( $slug ); ?>" />

                                    <div style="display: grid; grid-template-columns: 1fr 1.5fr; gap: 16px; margin-bottom: 16px;">
                                        <div>
                                            <label style="display:block; font-size: 0.78rem; font-weight:700; text-transform:uppercase; margin-bottom:4px; color:#475569;">
                                                <?php esc_html_e( 'Family Display Name', 'analogues-snips' ); ?>
                                            </label>
                                            <input type="text" name="font_family_name" value="<?php echo esc_attr( $font['name'] ); ?>" class="widefat" required />
                                        </div>
                                        <div class="snips-stack-control">
                                            <label style="display:block; font-size: 0.78rem; font-weight:700; text-transform:uppercase; margin-bottom:4px; color:#475569;">
                                                <?php esc_html_e( 'Fallback Font Stack', 'analogues-snips' ); ?>
                                            </label>
                                            <div style="display: flex; gap: 8px;">
                                                <input type="text" name="font_fallback_stack" value="<?php echo esc_attr( $font['fallback'] ); ?>" class="widefat snips-fallback-input" required />
                                                <select class="snips-preset-select" style="max-width: 170px;">
                                                    <option value=""><?php esc_html_e( 'Standard Presets...', 'analogues-snips' ); ?></option>
                                                    <option value="ui-monospace, 'Courier New', Courier, Monaco, Consolas, monospace">Mono (Standard Courier)</option>
                                                    <option value="system-ui, -apple-system, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif">Sans (Standard Helvetica / Arial)</option>
                                                    <option value="ui-serif, 'Times New Roman', Times, Georgia, serif">Serif (Standard Times / Georgia)</option>
                                                </select>
                                            </div>
                                        </div>
                                    </div>

                                    <p style="font-weight: 700; font-size: 0.78rem; text-transform: uppercase; color: #475569; margin: 14px 0 8px 0;">
                                        <?php esc_html_e( 'Installed Variant Faces', 'analogues-snips' ); ?>
                                    </p>
                                    <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(240px, 1fr)); gap: 10px; margin-bottom: 16px;">
                                        <?php foreach ( $font['fontFace'] as $face ) : ?>
                                            <div style="background: #f8fafc; border: 1px solid #e2e8f0; padding: 8px 12px; border-radius: 4px; display: flex; align-items: center; justify-content: space-between; font-size: 0.8rem;">
                                                <div>
                                                    <strong><?php echo isset( $face['label'] ) ? esc_html( $face['label'] ) : esc_html( $face['fontWeight'] . ' ' . $face['fontStyle'] ); ?></strong>
                                                    <span style="display: block; font-size: 0.72rem; color: #64748b;"><?php echo esc_html( basename( is_array( $face['src'] ) ? $face['src'][0] : $face['src'] ) ); ?></span>
                                                </div>
                                                <button type="submit" formaction="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" onclick="this.form.action.value='snips_delete_font_variant'; document.getElementById('snip_del_weight_<?php echo esc_attr( $slug ); ?>').value='<?php echo esc_attr( $face['fontWeight'] ); ?>'; document.getElementById('snip_del_style_<?php echo esc_attr( $slug ); ?>').value='<?php echo esc_attr( $face['fontStyle'] ); ?>'; return confirm('Remove this variant file?');" style="background: none; border: none; color: #ef4444; font-size: 0.85rem; cursor: pointer;">✕</button>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>

                                    <input type="hidden" id="snip_del_weight_<?php echo esc_attr( $slug ); ?>" name="font_weight" value="" />
                                    <input type="hidden" id="snip_del_style_<?php echo esc_attr( $slug ); ?>" name="font_style" value="" />

                                    <div style="margin-bottom: 14px;">
                                        <label style="display: block; font-size: 0.78rem; font-weight: 700; text-transform: uppercase; color: #475569; margin-bottom: 6px;">
                                            + <?php esc_html_e( 'Batch Add Variant Files', 'analogues-snips' ); ?>
                                        </label>
                                        <input type="file" name="font_additional_files[]" multiple accept=".woff2,.woff,.ttf,.otf" style="font-size: 0.8rem;" />
                                    </div>

                                    <?php submit_button( __( 'Save Family Changes', 'analogues-snips' ), 'secondary', 'submit', false ); ?>
                                </form>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>

                <?php self::render_preset_drawer( 'typography', 'Typography Font Families', 'tab-typography' ); ?>
            </div>

            <!-- TAB 3: METAR Weather & Live Styler -->
            <div id="tab-metar" class="snips-tab-content" style="display: none;">
                <!-- 1. Live Authentic METAR Preview -->
                <div class="snips-card">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 14px;">
                        <h2 style="margin: 0;"><?php esc_html_e( 'Live Atmospheric Ticker Preview', 'analogues-snips' ); ?></h2>
                        <div style="display: flex; align-items: center; gap: 8px;">
                            <span style="font-size: 0.75rem; font-weight: 700; text-transform: uppercase; color: #64748b;">Theme Preset:</span>
                            <select id="metar_preset_picker" style="font-size: 0.8rem;">
                                <option value="terminal">Terminal Emerald</option>
                                <option value="amber">Cockpit Amber</option>
                                <option value="slate">Instrument Slate</option>
                                <option value="paper">Paper Slate</option>
                            </select>
                        </div>
                    </div>

                    <!-- Authentic Live Ticker Tape Container -->
                    <div id="metar_live_preview_wrap" class="snip-metar-ticker-wrap" style="margin-bottom: 18px;">
                        <div id="metar_live_preview_track" class="snip-metar-ticker-content">
                            KBOS 271854Z 09009KT 10SM CLR 24/15 A3012 RMK AO2 &nbsp;&nbsp;¦&nbsp;&nbsp;
                            KJFK 271851Z 13012KT 10SM FEW050 26/18 A3010 RMK AO2 &nbsp;&nbsp;¦&nbsp;&nbsp;
                            KLGA 271851Z 11011KT 10SM SCT060 27/17 A3011 RMK AO2 &nbsp;&nbsp;¦&nbsp;&nbsp;
                        </div>
                    </div>

                    <!-- Authentic Category Badges -->
                    <div style="display: flex; gap: 10px; align-items: center; flex-wrap: wrap;">
                        <span style="font-size: 0.75rem; font-weight: 700; color: #64748b; text-transform: uppercase;">Flight Category Rules:</span>
                        <span id="preview_badge_vfr" class="snip-metar-badge" style="background-color: <?php echo esc_attr( $options['metar_color_vfr'] ); ?>;">VFR</span>
                        <span id="preview_badge_mvfr" class="snip-metar-badge" style="background-color: <?php echo esc_attr( $options['metar_color_mvfr'] ); ?>;">MVFR</span>
                        <span id="preview_badge_ifr" class="snip-metar-badge" style="background-color: <?php echo esc_attr( $options['metar_color_ifr'] ); ?>;">IFR</span>
                        <span id="preview_badge_lifr" class="snip-metar-badge" style="background-color: <?php echo esc_attr( $options['metar_color_lifr'] ); ?>;">LIFR</span>
                    </div>
                </div>

                <!-- 2. Settings & Telemetry Form -->
                <div class="snips-card">
                    <h2><?php esc_html_e( 'METAR Station Feeds & Custom Styling', 'analogues-snips' ); ?></h2>
                    <form method="post" action="options.php">
                        <?php settings_fields( self::OPTION_GROUP ); ?>
                        <?php do_settings_sections( 'snips-settings-metar' ); ?>

                        <h3 style="margin-top: 24px; font-size: 1rem;"><?php esc_html_e( 'Ticker Visual Theme & Colors', 'analogues-snips' ); ?></h3>
                        <table class="form-table">
                            <tr>
                                <th><?php esc_html_e( 'Background Color', 'analogues-snips' ); ?></th>
                                <td><input type="text" id="metar_custom_bg" name="snips_settings[metar_custom_bg]" value="<?php echo esc_attr( isset( $options['metar_custom_bg'] ) ? $options['metar_custom_bg'] : '#0c0d10' ); ?>" class="regular-text snips-metar-ctrl" /></td>
                            </tr>
                            <tr>
                                <th><?php esc_html_e( 'Text / Observation Color', 'analogues-snips' ); ?></th>
                                <td><input type="text" id="metar_custom_text" name="snips_settings[metar_custom_text]" value="<?php echo esc_attr( isset( $options['metar_custom_text'] ) ? $options['metar_custom_text'] : '#10b981' ); ?>" class="regular-text snips-metar-ctrl" /></td>
                            </tr>
                            <tr>
                                <th><?php esc_html_e( 'Border Color', 'analogues-snips' ); ?></th>
                                <td><input type="text" id="metar_custom_border" name="snips_settings[metar_custom_border]" value="<?php echo esc_attr( isset( $options['metar_custom_border'] ) ? $options['metar_custom_border'] : '#1f1f23' ); ?>" class="regular-text snips-metar-ctrl" /></td>
                            </tr>
                            <tr>
                                <th><?php esc_html_e( 'Corner Radius (px)', 'analogues-snips' ); ?></th>
                                <td><input type="number" id="metar_custom_radius" name="snips_settings[metar_custom_radius]" value="<?php echo esc_attr( isset( $options['metar_custom_radius'] ) ? $options['metar_custom_radius'] : 4 ); ?>" min="0" max="30" class="small-text snips-metar-ctrl" /></td>
                            </tr>
                        </table>

                        <h3 style="margin-top: 24px; font-size: 1rem;"><?php esc_html_e( 'Flight Rule Category Palette', 'analogues-snips' ); ?></h3>
                        <table class="form-table">
                            <tr>
                                <th><?php esc_html_e( 'VFR (Visual Flight Rules)', 'analogues-snips' ); ?></th>
                                <td><input type="text" id="metar_color_vfr" name="snips_settings[metar_color_vfr]" value="<?php echo esc_attr( isset( $options['metar_color_vfr'] ) ? $options['metar_color_vfr'] : '#10b981' ); ?>" class="regular-text snips-metar-ctrl" /></td>
                            </tr>
                            <tr>
                                <th><?php esc_html_e( 'MVFR (Marginal VFR)', 'analogues-snips' ); ?></th>
                                <td><input type="text" id="metar_color_mvfr" name="snips_settings[metar_color_mvfr]" value="<?php echo esc_attr( isset( $options['metar_color_mvfr'] ) ? $options['metar_color_mvfr'] : '#0284c7' ); ?>" class="regular-text snips-metar-ctrl" /></td>
                            </tr>
                            <tr>
                                <th><?php esc_html_e( 'IFR (Instrument Flight Rules)', 'analogues-snips' ); ?></th>
                                <td><input type="text" id="metar_color_ifr" name="snips_settings[metar_color_ifr]" value="<?php echo esc_attr( isset( $options['metar_color_ifr'] ) ? $options['metar_color_ifr'] : '#ef4444' ); ?>" class="regular-text snips-metar-ctrl" /></td>
                            </tr>
                            <tr>
                                <th><?php esc_html_e( 'LIFR (Low IFR)', 'analogues-snips' ); ?></th>
                                <td><input type="text" id="metar_color_lifr" name="snips_settings[metar_color_lifr]" value="<?php echo esc_attr( isset( $options['metar_color_lifr'] ) ? $options['metar_color_lifr'] : '#d946ef' ); ?>" class="regular-text snips-metar-ctrl" /></td>
                            </tr>
                        </table>

                        <?php submit_button( __( 'Save METAR Configuration', 'analogues-snips' ) ); ?>
                    </form>
                </div>

                <?php self::render_preset_drawer( 'metar', 'METAR Aviation Weather', 'tab-metar' ); ?>
            </div>

            <!-- TAB 4: Discord Live -->
            <div id="tab-discord" class="snips-tab-content" style="display: none;">
                <div class="snips-card">
                    <h2><?php esc_html_e( 'Discord Live Chat Settings', 'analogues-snips' ); ?></h2>
                    <form method="post" action="options.php">
                        <?php
                        settings_fields( self::OPTION_GROUP );
                        do_settings_sections( 'snips-settings-discord' );
                        submit_button( __( 'Save Discord Settings', 'analogues-snips' ) );
                        ?>
                    </form>
                </div>

                <?php self::render_preset_drawer( 'discord', 'Discord Live', 'tab-discord' ); ?>
            </div>

            <!-- TAB 5: Dynamic Date -->
            <div id="tab-date" class="snips-tab-content" style="display: none;">
                <div class="snips-card">
                    <h2><?php esc_html_e( 'Dynamic Date Settings', 'analogues-snips' ); ?></h2>
                    <form method="post" action="options.php">
                        <?php
                        settings_fields( self::OPTION_GROUP );
                        do_settings_sections( 'snips-settings-date' );
                        submit_button( __( 'Save Date Settings', 'analogues-snips' ) );
                        ?>
                    </form>
                </div>

                <?php self::render_preset_drawer( 'date', 'Dynamic Date', 'tab-date' ); ?>
            </div>

            <!-- TAB 6: Module Registry -->
            <div id="tab-registry" class="snips-tab-content" style="display: none;">
                <div class="snips-card">
                    <h2><?php esc_html_e( 'Registered Modules & Shortcodes', 'analogues-snips' ); ?></h2>
                    <p class="description" style="margin-bottom: 16px;"><?php esc_html_e( 'Quick reference for Gutenberg blocks and shortcode tags across all modules.', 'analogues-snips' ); ?></p>

                    <details class="snips-module-accordion" open>
                        <summary><span class="dashicons dashicons-format-status"></span> <?php esc_html_e( 'Telegrams & Inquiries Module', 'analogues-snips' ); ?></summary>
                        <div class="snips-accordion-content">
                            <table class="snips-table-aligned">
                                <thead>
                                    <tr>
                                        <th style="width: 30%;">Component</th>
                                        <th style="width: 15%;">Type</th>
                                        <th>Identifier / Usage</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr>
                                        <td><strong>Snip Active Telegram</strong></td>
                                        <td><span class="snips-pill">Block</span></td>
                                        <td><code>snips/active-telegram</code></td>
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
                        <summary><span class="dashicons dashicons-editor-textcolor"></span> <?php esc_html_e( 'Typography Engine', 'analogues-snips' ); ?></summary>
                        <div class="snips-accordion-content">
                            <table class="snips-table-aligned">
                                <thead>
                                    <tr>
                                        <th style="width: 30%;">Component</th>
                                        <th style="width: 15%;">Type</th>
                                        <th>Identifier / Usage</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr>
                                        <td><strong>Site Editor Typography</strong></td>
                                        <td><span class="snips-pill">Preset</span></td>
                                        <td><code>--wp--preset--font-family--{slug}</code> (Site Editor > Styles)</td>
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
                                        <th style="width: 30%;">Component</th>
                                        <th style="width: 15%;">Type</th>
                                        <th>Identifier / Usage</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr>
                                        <td><strong>Live Ticker Tape</strong></td>
                                        <td><span class="snips-pill">Shortcode</span></td>
                                        <td><code class="snips-shortcode-tag">[snip_metar_ticker icao="KBOS, KJFK, KLGA"]</code></td>
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
                        <summary><span class="dashicons dashicons-format-chat"></span> <?php esc_html_e( 'Discord Live Module', 'analogues-snips' ); ?></summary>
                        <div class="snips-accordion-content">
                            <table class="snips-table-aligned">
                                <thead>
                                    <tr>
                                        <th style="width: 30%;">Component</th>
                                        <th style="width: 15%;">Type</th>
                                        <th>Identifier / Usage</th>
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
                        <summary><span class="dashicons dashicons-calendar-alt"></span> <?php esc_html_e( 'Date & Time Module', 'analogues-snips' ); ?></summary>
                        <div class="snips-accordion-content">
                            <table class="snips-table-aligned">
                                <thead>
                                    <tr>
                                        <th style="width: 30%;">Component</th>
                                        <th style="width: 15%;">Type</th>
                                        <th>Identifier / Usage</th>
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

            <!-- TAB 7: Developer -->
            <div id="tab-developer" class="snips-tab-content" style="display: none;">
                <div class="snips-card">
                    <h2><?php esc_html_e( 'Cache Operations & Utilities', 'analogues-snips' ); ?></h2>
                    <p class="description" style="margin-bottom: 16px;"><?php esc_html_e( 'Flush cached API payloads without waiting for standard expiration windows.', 'analogues-snips' ); ?></p>
                    <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display: inline-block;">
                        <?php wp_nonce_field( 'snips_flush_transients_action', 'snips_flush_nonce' ); ?>
                        <input type="hidden" name="action" value="snips_flush_transients" />
                        <button type="submit" class="button button-secondary">
                            <?php esc_html_e( 'Purge Transients', 'analogues-snips' ); ?>
                        </button>
                    </form>
                </div>

                <div class="snips-card">
                    <h2><?php esc_html_e( 'Developer Options', 'analogues-snips' ); ?></h2>
                    <form method="post" action="options.php">
                        <?php
                        settings_fields( self::OPTION_GROUP );
                        do_settings_sections( 'snips-settings-dev' );
                        submit_button( __( 'Save Developer Settings', 'analogues-snips' ) );
                        ?>
                    </form>
                </div>

                <div class="snips-card">
                    <h2><?php esc_html_e( 'Raw Database Payload (JSON)', 'analogues-snips' ); ?></h2>
                    <pre class="snips-json-inspector"><code><?php echo esc_html( $json_preview ); ?></code></pre>
                </div>

                <div class="snips-card">
                    <h2><?php esc_html_e( 'Environment Telemetry', 'analogues-snips' ); ?></h2>
                    <table class="snips-table-aligned">
                        <tbody>
                            <tr>
                                <td style="width: 30%;"><strong>Snips Version</strong></td>
                                <td><code>v<?php echo esc_html( SNIPS_VERSION ); ?></code></td>
                            </tr>
                            <tr>
                                <td><strong>PHP Version</strong></td>
                                <td><code><?php echo esc_html( PHP_VERSION ); ?></code></td>
                            </tr>
                            <tr>
                                <td><strong>Server Time (UTC)</strong></td>
                                <td><code><?php echo esc_html( gmdate( 'Y-m-d H:i:s' ) ); ?> UTC</code></td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <?php self::render_preset_drawer( 'all', 'Complete Snips Suite', 'tab-developer' ); ?>
            </div>
        </div>
        <?php
    }
}