<?php
/**
 * SNIPS - Typography Manager Module
 *
 * Handles font uploads, variant editing, active/inactive state toggles, native WordPress theme.json registration, and isolated admin specimen loading.
 *
 * @package Analogues_Snips
 * @branch  main
 * @version main.6-snips-typography-php
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Snips_Typography {

    const OPTION_KEY = 'snips_registered_fonts';

    public function init() {
        add_action( 'admin_post_snips_upload_font_family', array( $this, 'handle_upload' ) );
        add_action( 'admin_post_snips_update_font_family', array( $this, 'handle_update' ) );
        add_action( 'admin_post_snips_toggle_font_status', array( $this, 'handle_toggle_status' ) );
        add_action( 'admin_post_snips_delete_font_variant', array( $this, 'handle_delete_variant' ) );
        add_action( 'admin_post_snips_delete_font_family', array( $this, 'handle_delete_family' ) );

        add_action( 'enqueue_block_editor_assets', array( $this, 'enqueue_block_editor_assets' ) );
        add_filter( 'wp_theme_json_data_theme', array( $this, 'register_active_fonts_with_theme_json' ) );
        add_action( 'admin_head', array( $this, 'inject_admin_preview_font_faces' ), 30 );
    }

    public static function get_fonts_dir() {
        $upload_dir = wp_upload_dir();
        $fonts_dir  = trailingslashit( $upload_dir['basedir'] ) . 'fonts/';
        $fonts_url  = trailingslashit( $upload_dir['baseurl'] ) . 'fonts/';

        if ( ! file_exists( $fonts_dir ) ) {
            wp_mkdir_p( $fonts_dir );
            file_put_contents( $fonts_dir . '.htaccess', "Options -Indexes\n<FilesMatch \"\.(php|phtml|php[0-9]?|phps)$\">\nOrder Deny,Allow\nDeny from all\n</FilesMatch>" );
            file_put_contents( $fonts_dir . 'index.php', '<?php // Silence is golden' );
        }

        return array(
            'dir' => $fonts_dir,
            'url' => $fonts_url,
        );
    }

    public function enqueue_block_editor_assets() {
        if ( file_exists( SNIPS_PATH . 'assets/js/snips-typography-block.js' ) ) {
            wp_enqueue_script(
                'snips-typography-block',
                SNIPS_URL . 'assets/js/snips-typography-block.js',
                array( 'wp-blocks', 'wp-element', 'wp-editor', 'wp-block-editor', 'wp-components', 'wp-compose', 'wp-hooks' ),
                SNIPS_VERSION,
                true
            );
        }
    }

    /**
     * Analyzes filename strings to automatically classify weights, styles, and labels.
     */
    public static function classify_font_file( $filename ) {
        $clean = strtolower( pathinfo( $filename, PATHINFO_FILENAME ) );

        $is_italic = ( false !== strpos( $clean, 'italic' ) || false !== strpos( $clean, 'oblique' ) || false !== strpos( $clean, 'slanted' ) );
        $font_style = $is_italic ? 'italic' : 'normal';

        $font_weight  = '400';
        $weight_label = 'Regular';

        if ( preg_match( '/(hairline|50)/i', $clean ) ) {
            $font_weight = '50'; $weight_label = 'Hairline';
        } elseif ( preg_match( '/(thin|100)/i', $clean ) ) {
            $font_weight = '100'; $weight_label = 'Thin';
        } elseif ( preg_match( '/(extralight|ultralight|200)/i', $clean ) ) {
            $font_weight = '200'; $weight_label = 'ExtraLight';
        } elseif ( preg_match( '/(light|300)/i', $clean ) ) {
            $font_weight = '300'; $weight_label = 'Light';
        } elseif ( preg_match( '/(book|450)/i', $clean ) ) {
            $font_weight = '450'; $weight_label = 'Book';
        } elseif ( preg_match( '/(medium|500)/i', $clean ) ) {
            $font_weight = '500'; $weight_label = 'Medium';
        } elseif ( preg_match( '/(semibold|demibold|semi|600)/i', $clean ) ) {
            $font_weight = '600'; $weight_label = 'SemiBold';
        } elseif ( preg_match( '/(extrabold|ultrabold|800)/i', $clean ) ) {
            $font_weight = '800'; $weight_label = 'ExtraBold';
        } elseif ( preg_match( '/(bold|700)/i', $clean ) ) {
            $font_weight = '700'; $weight_label = 'Bold';
        } elseif ( preg_match( '/(extrablack|ultrablack|950)/i', $clean ) ) {
            $font_weight = '950'; $weight_label = 'ExtraBlack';
        } elseif ( preg_match( '/(black|heavy|900)/i', $clean ) ) {
            $font_weight = '900'; $weight_label = 'Black';
        }

        if ( $is_italic ) {
            $weight_label .= ' Italic';
        }

        return array(
            'weight' => $font_weight,
            'style'  => $font_style,
            'label'  => $weight_label,
        );
    }

    public function handle_upload() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'Unauthorized access.', 'analogues-snips' ) );
        }

        check_admin_referer( 'snips_upload_font_action', 'snips_font_nonce' );

        $family_name = isset( $_POST['font_family_name'] ) ? sanitize_text_field( $_POST['font_family_name'] ) : '';
        $fallback    = isset( $_POST['font_fallback_stack'] ) ? sanitize_text_field( $_POST['font_fallback_stack'] ) : 'ui-monospace, monospace';
        $font_slug   = sanitize_title( $family_name );

        if ( empty( $family_name ) || empty( $_FILES['font_batch_files']['name'][0] ) ) {
            wp_safe_redirect( add_query_arg( array( 'page' => 'snips-settings', 'typo_error' => '1' ), admin_url( 'admin.php#tab-typography' ) ) );
            exit;
        }

        $storage    = self::get_fonts_dir();
        $target_dir = $storage['dir'] . $font_slug . '/';
        $target_url = $storage['url'] . $font_slug . '/';

        if ( ! file_exists( $target_dir ) ) {
            wp_mkdir_p( $target_dir );
        }

        $allowed_mimes = array( 'woff2' => 'font/woff2', 'woff' => 'font/woff', 'ttf' => 'font/ttf', 'otf' => 'font/otf' );
        $stored_fonts  = get_option( self::OPTION_KEY, array() );
        $faces         = isset( $stored_fonts[ $font_slug ]['fontFace'] ) ? $stored_fonts[ $font_slug ]['fontFace'] : array();
        $total_files   = count( $_FILES['font_batch_files']['name'] );

        for ( $i = 0; $i < $total_files; $i++ ) {
            $file_name = $_FILES['font_batch_files']['name'][ $i ];
            $file_tmp  = $_FILES['font_batch_files']['tmp_name'][ $i ];
            $check     = wp_check_filetype( $file_name, $allowed_mimes );

            if ( ! empty( $check['ext'] ) ) {
                $meta      = self::classify_font_file( $file_name );
                $dest_name = $font_slug . '-' . sanitize_file_name( $file_name );
                $dest_path = $target_dir . $dest_name;
                $dest_url  = $target_url . $dest_name;

                if ( move_uploaded_file( $file_tmp, $dest_path ) ) {
                    $faces = array_values( array_filter( $faces, function( $f ) use ( $meta ) {
                        return ! ( $f['fontWeight'] === $meta['weight'] && $f['fontStyle'] === $meta['style'] );
                    } ) );

                    $faces[] = array(
                        'fontFamily'  => $family_name,
                        'fontWeight'  => $meta['weight'],
                        'fontStyle'   => $meta['style'],
                        'fontDisplay' => 'swap',
                        'label'       => $meta['label'],
                        'src'         => array( esc_url_raw( $dest_url ) ),
                    );
                }
            }
        }

        if ( ! empty( $faces ) ) {
            $stored_fonts[ $font_slug ] = array(
                'name'       => $family_name,
                'slug'       => $font_slug,
                'fallback'   => $fallback,
                'fontFamily' => '"' . $family_name . '", ' . $fallback,
                'active'     => true,
                'fontFace'   => $faces,
                'openType'   => array(),
            );
            update_option( self::OPTION_KEY, $stored_fonts );
        }

        wp_safe_redirect( add_query_arg( array( 'page' => 'snips-settings', 'settings-updated' => 'true' ), admin_url( 'admin.php#tab-typography' ) ) );
        exit;
    }

    public function handle_update() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'Unauthorized access.', 'analogues-snips' ) );
        }

        check_admin_referer( 'snips_update_font_action', 'snips_font_nonce' );

        $font_slug = isset( $_POST['font_slug'] ) ? sanitize_title( $_POST['font_slug'] ) : '';
        $stored    = get_option( self::OPTION_KEY, array() );

        if ( ! isset( $stored[ $font_slug ] ) ) {
            wp_safe_redirect( add_query_arg( array( 'page' => 'snips-settings' ), admin_url( 'admin.php#tab-typography' ) ) );
            exit;
        }

        $family_name = isset( $_POST['font_family_name'] ) ? sanitize_text_field( $_POST['font_family_name'] ) : $stored[ $font_slug ]['name'];
        $fallback    = isset( $_POST['font_fallback_stack'] ) ? sanitize_text_field( $_POST['font_fallback_stack'] ) : $stored[ $font_slug ]['fallback'];

        $stored[ $font_slug ]['name']       = $family_name;
        $stored[ $font_slug ]['fallback']   = $fallback;
        $stored[ $font_slug ]['fontFamily'] = '"' . $family_name . '", ' . $fallback;

        // Process Additional Batch Variant Files
        $storage       = self::get_fonts_dir();
        $target_dir    = $storage['dir'] . $font_slug . '/';
        $target_url    = $storage['url'] . $font_slug . '/';
        $allowed_mimes = array( 'woff2' => 'font/woff2', 'woff' => 'font/woff', 'ttf' => 'font/ttf', 'otf' => 'font/otf' );
        $faces         = $stored[ $font_slug ]['fontFace'];

        if ( ! empty( $_FILES['font_additional_files']['name'][0] ) ) {
            $total_files = count( $_FILES['font_additional_files']['name'] );
            for ( $i = 0; $i < $total_files; $i++ ) {
                $file_name = $_FILES['font_additional_files']['name'][ $i ];
                $file_tmp  = $_FILES['font_additional_files']['tmp_name'][ $i ];
                $check     = wp_check_filetype( $file_name, $allowed_mimes );

                if ( ! empty( $check['ext'] ) ) {
                    $meta      = self::classify_font_file( $file_name );
                    $dest_name = $font_slug . '-' . sanitize_file_name( $file_name );
                    $dest_path = $target_dir . $dest_name;
                    $dest_url  = $target_url . $dest_name;

                    if ( move_uploaded_file( $file_tmp, $dest_path ) ) {
                        $faces = array_values( array_filter( $faces, function( $f ) use ( $meta ) {
                            return ! ( $f['fontWeight'] === $meta['weight'] && $f['fontStyle'] === $meta['style'] );
                        } ) );

                        $faces[] = array(
                            'fontFamily'  => $family_name,
                            'fontWeight'  => $meta['weight'],
                            'fontStyle'   => $meta['style'],
                            'fontDisplay' => 'swap',
                            'label'       => $meta['label'],
                            'src'         => array( esc_url_raw( $dest_url ) ),
                        );
                    }
                }
            }
        }

        $stored[ $font_slug ]['fontFace'] = $faces;
        update_option( self::OPTION_KEY, $stored );

        wp_safe_redirect( add_query_arg( array( 'page' => 'snips-settings', 'settings-updated' => 'true' ), admin_url( 'admin.php#tab-typography' ) ) );
        exit;
    }

    public function handle_toggle_status() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'Unauthorized access.', 'analogues-snips' ) );
        }

        check_admin_referer( 'snips_toggle_font_action', 'snips_toggle_nonce' );

        $slug   = isset( $_POST['font_slug'] ) ? sanitize_title( $_POST['font_slug'] ) : '';
        $stored = get_option( self::OPTION_KEY, array() );

        if ( isset( $stored[ $slug ] ) ) {
            $stored[ $slug ]['active'] = empty( $stored[ $slug ]['active'] );
            update_option( self::OPTION_KEY, $stored );
        }

        wp_safe_redirect( add_query_arg( array( 'page' => 'snips-settings', 'settings-updated' => 'true' ), admin_url( 'admin.php#tab-typography' ) ) );
        exit;
    }

    public function handle_delete_variant() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'Unauthorized access.', 'analogues-snips' ) );
        }

        check_admin_referer( 'snips_delete_variant_action', 'snips_delete_variant_nonce' );

        $slug   = isset( $_POST['font_slug'] ) ? sanitize_title( $_POST['font_slug'] ) : '';
        $weight = isset( $_POST['font_weight'] ) ? sanitize_text_field( $_POST['font_weight'] ) : '';
        $style  = isset( $_POST['font_style'] ) ? sanitize_text_field( $_POST['font_style'] ) : '';

        $stored = get_option( self::OPTION_KEY, array() );

        if ( isset( $stored[ $slug ] ) && ! empty( $stored[ $slug ]['fontFace'] ) ) {
            $updated_faces = array();
            foreach ( $stored[ $slug ]['fontFace'] as $face ) {
                if ( $face['fontWeight'] === $weight && $face['fontStyle'] === $style ) {
                    $url = is_array( $face['src'] ) ? $face['src'][0] : $face['src'];
                    $file_name = basename( parse_url( $url, PHP_URL_PATH ) );
                    $storage   = self::get_fonts_dir();
                    $file_path = $storage['dir'] . $slug . '/' . $file_name;
                    if ( file_exists( $file_path ) ) {
                        unlink( $file_path );
                    }
                } else {
                    $updated_faces[] = $face;
                }
            }
            $stored[ $slug ]['fontFace'] = $updated_faces;
            update_option( self::OPTION_KEY, $stored );
        }

        wp_safe_redirect( add_query_arg( array( 'page' => 'snips-settings', 'settings-updated' => 'true' ), admin_url( 'admin.php#tab-typography' ) ) );
        exit;
    }

    public function handle_delete_family() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'Unauthorized access.', 'analogues-snips' ) );
        }

        check_admin_referer( 'snips_delete_font_action', 'snips_delete_nonce' );

        $slug   = isset( $_POST['font_slug'] ) ? sanitize_title( $_POST['font_slug'] ) : '';
        $stored = get_option( self::OPTION_KEY, array() );

        if ( isset( $stored[ $slug ] ) ) {
            $storage    = self::get_fonts_dir();
            $target_dir = $storage['dir'] . $slug . '/';

            if ( is_dir( $target_dir ) ) {
                $files = glob( $target_dir . '*' );
                foreach ( $files as $file ) {
                    if ( is_file( $file ) ) {
                        unlink( $file );
                    }
                }
                @rmdir( $target_dir );
            }

            unset( $stored[ $slug ] );
            update_option( self::OPTION_KEY, $stored );
        }

        wp_safe_redirect( add_query_arg( array( 'page' => 'snips-settings', 'settings-updated' => 'true' ), admin_url( 'admin.php#tab-typography' ) ) );
        exit;
    }

    public function register_active_fonts_with_theme_json( $theme_json ) {
        $stored_fonts = get_option( self::OPTION_KEY, array() );
        if ( empty( $stored_fonts ) ) {
            return $theme_json;
        }

        $active_families = array();
        foreach ( $stored_fonts as $font ) {
            if ( ! empty( $font['active'] ) && ! empty( $font['fontFace'] ) ) {
                $active_families[] = array(
                    'fontFamily' => $font['fontFamily'],
                    'name'       => $font['name'],
                    'slug'       => $font['slug'],
                    'fontFace'   => $font['fontFace'],
                );
            }
        }

        if ( empty( $active_families ) ) {
            return $theme_json;
        }

        $new_data = array(
            'version'  => 2,
            'settings' => array(
                'typography' => array(
                    'fontFamilies' => $active_families,
                ),
            ),
        );

        return $theme_json->update_with( $new_data );
    }

    public function inject_admin_preview_font_faces() {
        $screen = get_current_screen();
        if ( ! $screen || 'toplevel_page_snips-settings' !== $screen->id ) {
            return;
        }

        $stored_fonts = get_option( self::OPTION_KEY, array() );
        if ( empty( $stored_fonts ) ) {
            return;
        }

        echo "<style id=\"snips-admin-preview-fontfaces\">\n";
        foreach ( $stored_fonts as $slug => $font ) {
            if ( empty( $font['fontFace'] ) ) {
                continue;
            }

            foreach ( $font['fontFace'] as $face ) {
                $src_url = is_array( $face['src'] ) ? $face['src'][0] : $face['src'];
                $ext     = pathinfo( parse_url( $src_url, PHP_URL_PATH ), PATHINFO_EXTENSION );
                $format  = ( 'woff2' === $ext ) ? 'woff2' : ( ( 'woff' === $ext ) ? 'woff' : 'truetype' );

                echo "@font-face {\n";
                echo "    font-family: 'SnipsPreview_" . esc_attr( $slug ) . "';\n";
                echo "    font-weight: " . esc_attr( $face['fontWeight'] ) . ";\n";
                echo "    font-style: " . esc_attr( $face['fontStyle'] ) . ";\n";
                echo "    font-display: swap;\n";
                echo "    src: url('" . esc_url( $src_url ) . "') format('" . esc_attr( $format ) . "');\n";
                echo "}\n";
            }
        }
        echo "</style>\n";
    }

    public static function get_all_fonts() {
        return get_option( self::OPTION_KEY, array() );
    }
}