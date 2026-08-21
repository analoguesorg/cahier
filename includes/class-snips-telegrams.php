<?php
/**
 * Telegrams Custom Post Type, Multi-Slot Window Engine & Field Ledger
 *
 * @package Analogues_Snips
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Snips_Telegrams {

    public function init() {
        add_action( 'init', array( $this, 'register_post_type' ) );
        add_action( 'init', array( $this, 'register_telegram_block' ) );
        add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_assets' ) );

        // Dedicated AJAX comment submission endpoints
        add_action( 'wp_ajax_snips_submit_field_note', array( $this, 'handle_ajax_comment' ) );
        add_action( 'wp_ajax_nopriv_snips_submit_field_note', array( $this, 'handle_ajax_comment' ) );

        // Shortcodes
        add_shortcode( 'snip_telegram_countdown', array( $this, 'render_countdown_shortcode' ) );
        add_shortcode( 'snip_telegram_stats', array( $this, 'render_stats_shortcode' ) );
    }

    public function enqueue_assets() {
        if ( is_singular() || is_page() ) {
            wp_enqueue_script( 'comment-reply' );
        }

        if ( file_exists( SNIPS_PATH . 'assets/css/snips-telegrams.css' ) ) {
            wp_enqueue_style(
                'snips-telegrams-css',
                SNIPS_URL . 'assets/css/snips-telegrams.css',
                array(),
                SNIPS_VERSION
            );
        }

        if ( file_exists( SNIPS_PATH . 'assets/js/snips-telegrams.js' ) ) {
            wp_enqueue_script(
                'snips-telegrams-js',
                SNIPS_URL . 'assets/js/snips-telegrams.js',
                array( 'jquery' ),
                SNIPS_VERSION,
                true
            );

            $options   = get_option( 'snips_settings', array() );
            $time_mode = ! empty( $options['timestamp_mode'] ) ? $options['timestamp_mode'] : 'local';

            wp_localize_script( 'snips-telegrams-js', 'SnipsTelegramsData', array(
                'ajaxUrl'  => admin_url( 'admin-ajax.php' ),
                'nonce'    => wp_create_nonce( 'snips_field_note_nonce' ),
                'timeMode' => $time_mode,
            ) );
        }
    }

    public function handle_ajax_comment() {
        check_ajax_referer( 'snips_field_note_nonce', 'nonce' );

        $comment_post_ID = isset( $_POST['comment_post_ID'] ) ? intval( $_POST['comment_post_ID'] ) : 0;
        $comment_parent  = isset( $_POST['comment_parent'] ) ? intval( $_POST['comment_parent'] ) : 0;
        $comment_content = isset( $_POST['comment'] ) ? trim( (string) $_POST['comment'] ) : '';

        if ( empty( $comment_post_ID ) || empty( $comment_content ) ) {
            wp_send_json_error( array( 'message' => __( 'Missing required fields.', 'analogues-snips' ) ) );
        }

        $user = wp_get_current_user();
        $is_user_logged_in = is_user_logged_in();

        if ( $is_user_logged_in ) {
            $author_input = isset( $_POST['author'] ) ? sanitize_text_field( $_POST['author'] ) : '';
            $author       = ! empty( $author_input ) ? $author_input : $user->display_name;
            $email        = $user->user_email;
            $user_id      = $user->ID;

            // Track if user used a custom alias instead of account name
            $is_custom_alias = ( $author !== $user->display_name && $author !== $user->user_login );
        } else {
            $author          = isset( $_POST['author'] ) ? sanitize_text_field( $_POST['author'] ) : 'Guest';
            $email           = isset( $_POST['email'] ) ? sanitize_email( $_POST['email'] ) : '';
            $user_id         = 0;
            $is_custom_alias = false;
        }

        $commentdata = array(
            'comment_post_ID'      => $comment_post_ID,
            'comment_author'       => $author,
            'comment_author_email' => $email,
            'comment_content'      => $comment_content,
            'comment_type'         => 'comment',
            'comment_parent'       => $comment_parent,
            'user_id'              => $user_id,
            'comment_approved'     => 1, // Auto-approve field notes
        );

        $comment_id = wp_insert_comment( $commentdata );

        if ( ! $comment_id ) {
            wp_send_json_error( array( 'message' => __( 'Failed to log field note.', 'analogues-snips' ) ) );
        }

        // Store custom callsign flag in comment meta
        if ( $is_custom_alias ) {
            update_comment_meta( $comment_id, '_snip_is_custom_alias', '1' );
        }

        $comment = get_comment( $comment_id );

        ob_start();
        $depth = ( $comment_parent > 0 ) ? 2 : 1;
        $this->render_ledger_comment_item( $comment, array(), $depth );
        $html = ob_get_clean();

        wp_send_json_success( array(
            'comment_id' => $comment_id,
            'parent_id'  => $comment_parent,
            'html'       => $html,
        ) );
    }

    public function register_post_type() {
        $labels = array(
            'name'               => _x( 'Telegrams', 'post type general name', 'analogues-snips' ),
            'singular_name'      => _x( 'Telegram', 'post type singular name', 'analogues-snips' ),
            'menu_name'          => _x( 'Telegrams', 'admin menu', 'analogues-snips' ),
            'add_new'            => __( 'New Telegram', 'analogues-snips' ),
            'add_new_item'       => __( 'Add New Telegram', 'analogues-snips' ),
            'edit_item'          => __( 'Edit Telegram', 'analogues-snips' ),
            'all_items'          => __( 'All Telegrams', 'analogues-snips' ),
        );

        $args = array(
            'labels'             => $labels,
            'public'             => true,
            'publicly_queryable' => true,
            'show_ui'            => true,
            'show_in_menu'       => true,
            'show_in_rest'       => true,
            'has_archive'        => 'telegrams',
            'rewrite'            => array( 'slug' => 'telegrams', 'with_front' => false ),
            'menu_icon'          => 'dashicons-format-status',
            'supports'           => array( 'title', 'editor', 'excerpt', 'comments' ),
        );

        register_post_type( 'telegram', $args );
    }

    public function register_telegram_block() {
        wp_register_script(
            'snips-telegrams-block-editor',
            SNIPS_URL . 'assets/js/snips-telegrams-block.js',
            array( 'wp-blocks', 'wp-element', 'wp-editor', 'wp-components', 'wp-block-editor' ),
            SNIPS_VERSION
        );

        wp_register_style(
            'snips-telegrams-css',
            SNIPS_URL . 'assets/css/snips-telegrams.css',
            array(),
            SNIPS_VERSION
        );

        register_block_type( 'snips/active-telegram', array(
            'editor_script'   => 'snips-telegrams-block-editor',
            'style'           => 'snips-telegrams-css',
            'render_callback' => array( $this, 'render_field_ledger_markup' ),
            'attributes'      => array(
                'badgeLabel'  => array( 'type' => 'string', 'default' => '' ),
                'buttonText'  => array( 'type' => 'string', 'default' => '' ),
                'footerNote'  => array( 'type' => 'string', 'default' => '' ),
            ),
        ) );
    }

    public function get_active_telegram_data() {
        $options = get_option( 'snips_settings', array() );
        $windows = isset( $options['dispatch_windows'] ) ? $options['dispatch_windows'] : array();
        $now     = time();

        // Slot 1 expiration
        if ( ! empty( $windows[1]['end'] ) ) {
            $end_timestamp = strtotime( $windows[1]['end'] . ' 23:59:59' );
            if ( $end_timestamp < $now ) {
                $windows[0] = $windows[1];
                $windows[1] = isset( $windows[2] ) ? $windows[2] : array( 'telegram_id' => '', 'start' => '', 'end' => '' );
                $windows[2] = isset( $windows[3] ) ? $windows[3] : array( 'telegram_id' => '', 'start' => '', 'end' => '' );
                $windows[3] = array( 'telegram_id' => '', 'start' => '', 'end' => '' );

                $options['dispatch_windows'] = $windows;
                update_option( 'snips_settings', $options );
            }
        }

        if ( ! empty( $windows[1]['telegram_id'] ) && ! empty( $windows[1]['start'] ) && ! empty( $windows[1]['end'] ) ) {
            $start = strtotime( $windows[1]['start'] . ' 00:00:00' );
            $end   = strtotime( $windows[1]['end'] . ' 23:59:59' );

            if ( $now >= $start && $now <= $end ) {
                $post = get_post( intval( $windows[1]['telegram_id'] ) );
                if ( $post && 'publish' === $post->post_status ) {
                    $diff  = $end - $now;
                    $days  = floor( $diff / DAY_IN_SECONDS );
                    $hours = floor( ( $diff % DAY_IN_SECONDS ) / HOUR_IN_SECONDS );

                    return array(
                        'post'        => $post,
                        'is_overtime' => false,
                        'status_text' => ( $days > 0 ) ? sprintf( '%dd %dh remaining', $days, $hours ) : sprintf( '%dh remaining', $hours ),
                        'color_class' => 'snip-status-active',
                    );
                }
            }
        }

        $latest = new WP_Query( array(
            'post_type'      => 'telegram',
            'post_status'    => 'publish',
            'posts_per_page' => 1,
            'no_found_rows'  => true,
        ) );

        if ( $latest->have_posts() ) {
            return array(
                'post'        => $latest->posts[0],
                'is_overtime' => true,
                'status_text' => 'OPEN FORUM',
                'color_class' => 'snip-status-overtime',
            );
        }

        return false;
    }

    public function get_active_telegram() {
        $data = $this->get_active_telegram_data();
        return $data ? $data['post'] : null;
    }

    public function render_countdown_shortcode( $atts ) {
        $atts = shortcode_atts( array(
            'href'   => home_url( '/commons' ),
            'prefix' => '',
            'suffix' => '',
        ), $atts, 'snip_telegram_countdown' );

        $data = $this->get_active_telegram_data();
        if ( ! $data ) {
            return '';
        }

        return sprintf(
            '<a href="%s" class="snip-cadence-pill %s"><span class="snip-pulse-dot"></span>%s%s%s</a>',
            esc_url( $atts['href'] ),
            esc_attr( $data['color_class'] ),
            esc_html( $atts['prefix'] ),
            esc_html( strtoupper( $data['status_text'] ) ),
            esc_html( $atts['suffix'] )
        );
    }

    public function render_stats_shortcode( $atts ) {
        $active = $this->get_active_telegram();
        if ( ! $active ) {
            return '';
        }
        $count = get_comments_number( $active->ID );
        return sprintf( '<span class="snip-stats-pill">%d %s</span>', $count, _n( 'Field Note Logged', 'Field Notes Logged', $count, 'analogues-snips' ) );
    }

    public function get_callsign_badge_data( $comment ) {
        $user_id         = intval( $comment->user_id );
        $is_custom_alias = get_comment_meta( $comment->comment_ID, '_snip_is_custom_alias', true );
        $comment_count   = 0;

        if ( ! empty( $comment->comment_author_email ) ) {
            $comment_count = get_comments( array(
                'author_email' => $comment->comment_author_email,
                'count'        => true,
                'status'       => 'approve',
            ) );
        }

        if ( $user_id > 0 ) {
            if ( '1' === $is_custom_alias ) {
                return array(
                    'class' => 'snip-callsign-alias',
                    'label' => 'ALIAS',
                );
            }
            return array(
                'class' => 'snip-callsign-registered',
                'label' => 'VERIFIED',
            );
        }

        if ( $comment_count >= 3 ) {
            return array(
                'class' => 'snip-callsign-frequent',
                'label' => 'FREQUENT',
            );
        }

        return array(
            'class' => 'snip-callsign-guest',
            'label' => 'GUEST',
        );
    }

    public function render_ledger_comment_item( $comment, $args = array(), $depth = 1 ) {
        $comment_id = $comment->comment_ID;
        $author     = get_comment_author( $comment_id );
        $utc_time   = get_comment_date( 'c', $comment_id );
        $content    = get_comment_text( $comment_id );
        $badge      = $this->get_callsign_badge_data( $comment );
        ?>
        <li id="comment-<?php echo esc_attr( $comment_id ); ?>" class="snip-ledger-entry depth-<?php echo esc_attr( $depth ); ?>">
            <div class="snip-entry-header">
                <span class="snip-entry-author"><?php echo esc_html( $author ); ?></span>
                <span class="snip-badge-callsign <?php echo esc_attr( $badge['class'] ); ?>"><?php echo esc_html( $badge['label'] ); ?></span>
                <span class="snip-entry-separator">/</span>
                <time class="snip-ledger-time" datetime="<?php echo esc_attr( $utc_time ); ?>"><?php echo esc_html( get_comment_date( 'M j, Y g:i A', $comment_id ) ); ?></time>
                <span class="snip-entry-reply-link">
                    <button type="button" class="snip-reply-btn" data-id="<?php echo esc_attr( $comment_id ); ?>" data-author="<?php echo esc_attr( $author ); ?>">reply</button>
                </span>
            </div>
            <div class="snip-entry-content">
                <?php echo wp_kses_post( $content ); ?>
            </div>
        </li>
        <?php
    }

    public function render_field_ledger_markup( $attributes = array() ) {
        $data    = $this->get_active_telegram_data();
        $options = get_option( 'snips_settings', array() );

        if ( ! $data || ! $data['post'] ) {
            return '<div class="snip-telegram-empty"><p>' . esc_html__( 'No active inquiries dispatched yet.', 'analogues-snips' ) . '</p></div>';
        }

        $telegram    = $data['post'];
        $badge_label = ! empty( $attributes['badgeLabel'] ) ? $attributes['badgeLabel'] : ( ! empty( $options['telegram_badge_label'] ) ? $options['telegram_badge_label'] : 'CURRENT INQUIRY' );
        $button_text = ! empty( $attributes['buttonText'] ) ? $attributes['buttonText'] : ( ! empty( $options['telegram_button_text'] ) ? $options['telegram_button_text'] : 'Leave a Field Note ↓' );
        $footer_note = ! empty( $attributes['footerNote'] ) ? $attributes['footerNote'] : ( ! empty( $options['telegram_footer_note'] ) ? $options['telegram_footer_note'] : 'Zero-login required • Open discussion' );

        $title     = get_the_title( $telegram );
        $post_date = get_the_date( 'M Y', $telegram );
        $content   = apply_filters( 'the_content', $telegram->post_content );

        global $post;
        $original_post = $post;
        $post          = $telegram;
        setup_postdata( $post );

        $comments = get_comments( array(
            'post_id' => $telegram->ID,
            'status'  => 'approve',
            'order'   => 'ASC',
        ) );

        $current_user = wp_get_current_user();
        $is_logged_in = is_user_logged_in();

        $default_author = $is_logged_in ? $current_user->display_name : '';
        $default_email  = $is_logged_in ? $current_user->user_email : '';

        ob_start();
        ?>
        <div class="snip-field-ledger-wrapper">
            <article class="snip-ledger-card">
                <!-- Upper Prompt Section -->
                <div class="snip-ledger-prompt">
                    <div class="snip-ledger-kicker">
                        <div class="snip-kicker-left">
                            <span class="snip-ledger-badge"><?php echo esc_html( $badge_label ); ?></span>
                            <span class="snip-ledger-title"><?php echo esc_html( $title ); ?></span>
                            <span class="snip-ledger-date"><?php echo esc_html( strtoupper( $post_date ) ); ?></span>
                        </div>
                        <div class="snip-kicker-right">
                            <span class="snip-cadence-badge <?php echo esc_attr( $data['color_class'] ); ?>">
                                <span class="snip-pulse-dot"></span>
                                <?php echo esc_html( strtoupper( $data['status_text'] ) ); ?>
                            </span>
                        </div>
                    </div>

                    <div class="snip-ledger-content">
                        <?php echo $content; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                    </div>

                    <div class="snip-ledger-actions">
                        <a href="#snip-composer-dock" class="snip-btn-field-note">
                            <?php echo esc_html( $button_text ); ?>
                        </a>
                        <span class="snip-ledger-footnote"><?php echo esc_html( $footer_note ); ?></span>
                    </div>
                </div>

                <!-- Dispatches Stream Feed -->
                <div class="snip-ledger-body">
                    <ul class="snip-ledger-stream" style="<?php echo empty( $comments ) ? 'display: none;' : ''; ?>">
                        <?php
                        if ( ! empty( $comments ) ) {
                            wp_list_comments( array(
                                'callback' => array( $this, 'render_ledger_comment_item' ),
                                'style'    => 'ul',
                            ), $comments );
                        }
                        ?>
                    </ul>
                    <div class="snip-ledger-empty-note" style="<?php echo ! empty( $comments ) ? 'display: none;' : ''; ?>">
                        <span>No dispatches recorded yet. Log the first field note below.</span>
                    </div>
                </div>

                <!-- Anchor for Root Composer Mount -->
                <div id="snip-dock-root-anchor">
                    <div id="snip-composer-dock" class="snip-ledger-dock">
                        <div id="snip-reply-banner" class="snip-reply-context-banner" style="display: none;">
                            <span>Replying to <strong id="snip-reply-target-author"></strong></span>
                            <button type="button" id="snip-cancel-reply-btn">Cancel [esc]</button>
                        </div>

                        <form id="snip-custom-commentform">
                            <div class="snip-dock-input-wrap">
                                <span class="snip-dock-caret">></span>
                                <textarea id="snip-comment-text" name="comment" rows="2" placeholder="Record a field note..." required></textarea>
                            </div>

                            <div class="snip-dock-fields">
                                <input id="snip-author-input" name="author" type="text" value="<?php echo esc_attr( $default_author ); ?>" placeholder="Callsign / Name *" required />
                                <input id="snip-email-input" name="email" type="email" value="<?php echo esc_attr( $default_email ); ?>" placeholder="Email (Private) *" <?php echo $is_logged_in ? 'readonly style="opacity: 0.75;"' : 'required'; ?> />
                            </div>

                            <div class="snip-dock-submit-row">
                                <input type="hidden" name="comment_post_ID" value="<?php echo esc_attr( $telegram->ID ); ?>" />
                                <input type="hidden" name="comment_parent" id="snip_comment_parent" value="0" />
                                <button type="submit" id="snip-submit-btn" class="snip-btn-dispatch">Send Dispatch ↵</button>
                            </div>
                        </form>
                    </div>
                </div>
            </article>
        </div>
        <?php
        $output = ob_get_clean();

        $post = $original_post;
        if ( $post ) {
            setup_postdata( $post );
        } else {
            wp_reset_postdata();
        }

        return $output;
    }
}