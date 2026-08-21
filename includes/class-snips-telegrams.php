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

        $options   = get_option( 'snips_settings', array() );
        $time_mode = ! empty( $options['timestamp_mode'] ) ? $options['timestamp_mode'] : 'local';

        wp_add_inline_script(
            'jquery',
            '
            document.addEventListener("DOMContentLoaded", function() {
                var timeMode = "' . esc_js( $time_mode ) . '";

                function localizeTimestamps() {
                    var times = document.querySelectorAll(".snip-ledger-time[datetime]");
                    var now = new Date();

                    times.forEach(function(el) {
                        var dateStr = el.getAttribute("datetime");
                        if (!dateStr) return;
                        var date = new Date(dateStr);

                        if (timeMode === "utc") {
                            var months = ["JAN", "FEB", "MAR", "APR", "MAY", "JUN", "JUL", "AUG", "SEP", "OCT", "NOV", "DEC"];
                            var month = months[date.getUTCMonth()];
                            var day = date.getUTCDate();
                            var year = date.getUTCFullYear();
                            var hours = String(date.getUTCHours()).padStart(2, "0");
                            var mins = String(date.getUTCMinutes()).padStart(2, "0");
                            el.textContent = month + " " + day + ", " + year + " " + hours + ":" + mins + " UTC";
                        } else {
                            var diffSec = Math.floor((now - date) / 1000);
                            if (diffSec < 60) {
                                el.textContent = "just now";
                            } else if (diffSec < 3600) {
                                el.textContent = Math.floor(diffSec / 60) + "m ago";
                            } else if (diffSec < 86400) {
                                el.textContent = Math.floor(diffSec / 3600) + "h ago";
                            } else if (diffSec < 604800) {
                                el.textContent = Math.floor(diffSec / 86400) + "d ago";
                            } else {
                                el.textContent = date.toLocaleDateString(undefined, { month: "short", day: "numeric" });
                            }
                        }
                    });
                }
                localizeTimestamps();

                // Focus & Smooth Scroll into Composer
                var leaveBtn = document.querySelector(".snip-btn-field-note");
                if (leaveBtn) {
                    leaveBtn.addEventListener("click", function(e) {
                        e.preventDefault();
                        var dockWrap = document.querySelector(".snip-dock-input-wrap");
                        var textarea = document.querySelector("#comment");
                        if (textarea && dockWrap) {
                            textarea.focus();
                            dockWrap.classList.add("snip-dock-highlight");
                            textarea.scrollIntoView({ behavior: "smooth", block: "center" });
                            setTimeout(function() {
                                dockWrap.classList.remove("snip-dock-highlight");
                            }, 1800);
                        }
                    });
                }

                // Toggle Reply / Cancel Behavior
                document.addEventListener("click", function(e) {
                    var replyLink = e.target.closest(".comment-reply-link");
                    if (replyLink) {
                        var commentId = replyLink.getAttribute("data-commentid");
                        var cancelLink = document.getElementById("cancel-comment-reply-link");
                        var respondDiv = document.getElementById("respond");

                        if (respondDiv && respondDiv.parentElement.id === "comment-" + commentId) {
                            e.preventDefault();
                            if (cancelLink) cancelLink.click();
                        }
                    }
                });

                // Zero-Reload AJAX Submission
                var commentForm = document.getElementById("commentform");
                if (!commentForm) return;

                commentForm.addEventListener("submit", function(e) {
                    var commentTextarea = commentForm.querySelector("textarea#comment");
                    var submitBtn = commentForm.querySelector("input[type=\"submit\"], button[type=\"submit\"]");

                    if (!commentTextarea || !commentTextarea.value.trim()) return;

                    e.preventDefault();
                    var formData = new FormData(commentForm);

                    if (submitBtn) {
                        submitBtn.disabled = true;
                        submitBtn.value = "Transmitting...";
                    }

                    fetch(commentForm.action, {
                        method: "POST",
                        body: formData,
                        credentials: "same-origin"
                    })
                    .then(function(res) { return res.text(); })
                    .then(function(html) {
                        var parser = new DOMParser();
                        var doc = parser.parseFromString(html, "text/html");
                        
                        var newComments = doc.querySelector(".snip-ledger-stream");
                        var currentStream = document.querySelector(".snip-ledger-stream");

                        if (newComments && currentStream) {
                            currentStream.innerHTML = newComments.innerHTML;
                        } else if (newComments && !currentStream) {
                            var streamWrap = document.querySelector(".snip-ledger-body");
                            if (streamWrap) streamWrap.appendChild(newComments);
                        }

                        localizeTimestamps();
                        var latestEntry = document.querySelector(".snip-ledger-stream > li:first-child");
                        if (latestEntry) {
                            latestEntry.classList.add("snip-entry-flash");
                            setTimeout(function() { latestEntry.classList.remove("snip-entry-flash"); }, 2000);
                        }

                        commentTextarea.value = "";
                        if (submitBtn) {
                            submitBtn.disabled = false;
                            submitBtn.value = "Send Dispatch ↵";
                        }
                    })
                    .catch(function(err) {
                        console.error("Error submitting dispatch:", err);
                        if (submitBtn) {
                            submitBtn.disabled = false;
                            submitBtn.value = "Send Dispatch ↵";
                        }
                    });
                });
            });
            '
        );
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

        // Check if Slot 1 expired and auto-shift
        if ( ! empty( $windows[1]['end'] ) && strtotime( $windows[1]['end'] ) < $now ) {
            $windows[0] = $windows[1];
            $windows[1] = isset( $windows[2] ) ? $windows[2] : array( 'telegram_id' => '', 'start' => '', 'end' => '' );
            $windows[2] = isset( $windows[3] ) ? $windows[3] : array( 'telegram_id' => '', 'start' => '', 'end' => '' );
            $windows[3] = array( 'telegram_id' => '', 'start' => '', 'end' => '' );

            $options['dispatch_windows'] = $windows;
            update_option( 'snips_settings', $options );
        }

        // Active scheduled window (Slot 1)
        if ( ! empty( $windows[1]['telegram_id'] ) && ! empty( $windows[1]['start'] ) && ! empty( $windows[1]['end'] ) ) {
            $start = strtotime( $windows[1]['start'] );
            $end   = strtotime( $windows[1]['end'] );

            if ( $now >= $start && $now <= $end ) {
                $post = get_post( intval( $windows[1]['telegram_id'] ) );
                if ( $post && 'publish' === $post->post_status ) {
                    $diff  = $end - $now;
                    $days  = floor( $diff / DAY_IN_SECONDS );
                    $hours = floor( ( $diff % DAY_IN_SECONDS ) / HOUR_IN_SECONDS );

                    return array(
                        'post'        => $post,
                        'is_overtime' => false,
                        'status_text' => sprintf( '%dd %dh remaining', $days, $hours ),
                        'color_class' => 'snip-status-active',
                    );
                }
            }
        }

        // Indefinite Open Forum Fallback
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
                'status_text' => 'INDEFINITE // OPEN FORUM',
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

    public function render_ledger_comment_item( $comment, $args, $depth ) {
        $comment_id = $comment->comment_ID;
        $author     = get_comment_author( $comment_id );
        $utc_time   = get_comment_date( 'c', $comment_id );
        $content    = get_comment_text( $comment_id );
        ?>
        <li id="comment-<?php echo esc_attr( $comment_id ); ?>" class="snip-ledger-entry">
            <div class="snip-entry-header">
                <span class="snip-entry-author"><?php echo esc_html( $author ); ?></span>
                <span class="snip-entry-separator">/</span>
                <time class="snip-ledger-time" datetime="<?php echo esc_attr( $utc_time ); ?>"><?php echo esc_html( get_comment_date( 'M j, Y g:i A', $comment_id ) ); ?></time>
                <span class="snip-entry-reply-link">
                    <?php
                    comment_reply_link( array_merge( $args, array(
                        'depth'     => $depth,
                        'max_depth' => $args['max_depth'],
                        'reply_text'=> 'reply',
                    ) ) );
                    ?>
                </span>
            </div>
            <div class="snip-entry-content">
                <?php echo wp_kses_post( $content ); ?>
            </div>
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
                        <a href="#respond" class="snip-btn-field-note">
                            <?php echo esc_html( $button_text ); ?>
                        </a>
                        <span class="snip-ledger-footnote"><?php echo esc_html( $footer_note ); ?></span>
                    </div>
                </div>

                <!-- Dispatches Stream Feed -->
                <div class="snip-ledger-body">
                    <?php if ( ! empty( $comments ) ) : ?>
                        <ul class="snip-ledger-stream">
                            <?php
                            wp_list_comments( array(
                                'callback' => array( $this, 'render_ledger_comment_item' ),
                                'style'    => 'ul',
                            ), $comments );
                            ?>
                        </ul>
                    <?php else : ?>
                        <div class="snip-ledger-empty-note">
                            <span>No dispatches recorded yet. Log the first field note below.</span>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Flush Composer Dock -->
                <div id="respond" class="snip-ledger-dock">
                    <?php
                    $commenter = wp_get_current_commenter();
                    $req       = get_option( 'require_name_email' );
                    $aria_req  = ( $req ? " aria-required='true'" : '' );

                    comment_form( array(
                        'title_reply'          => '',
                        'title_reply_to'       => __( 'Replying to dispatch', 'analogues-snips' ),
                        'cancel_reply_link'    => __( 'Cancel', 'analogues-snips' ),
                        'label_submit'         => __( 'Send Dispatch ↵', 'analogues-snips' ),
                        'class_submit'         => 'snip-btn-dispatch',
                        'comment_notes_before' => '',
                        'comment_notes_after'  => '',
                        'comment_field'        => '<div class="snip-dock-input-wrap"><span class="snip-dock-caret">></span><textarea id="comment" name="comment" rows="2" placeholder="Record a field note..." required></textarea></div>',
                        'fields'               => array(
                            'author' => '<div class="snip-dock-fields"><input id="author" name="author" type="text" value="' . esc_attr( $commenter['comment_author'] ) . '" placeholder="Callsign / Name' . ( $req ? ' *' : '' ) . '" ' . $aria_req . ' />',
                            'email'  => '<input id="email" name="email" type="email" value="' . esc_attr( $commenter['comment_author_email'] ) . '" placeholder="Email (Private)' . ( $req ? ' *' : '' ) . '" ' . $aria_req . ' /></div>',
                        ),
                    ), $telegram->ID );
                    ?>
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