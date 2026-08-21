<?php
/**
 * Discord Live Widget & Presence Status Module
 *
 * @package Analogues_Snips
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Snips_Discord {

    public function init() {
        add_action( 'init', array( $this, 'register_discord_block' ) );
        add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_assets' ) );
        
        add_shortcode( 'snip_discord_status', array( $this, 'render_status_shortcode' ) );
    }

    public function enqueue_assets() {
        wp_enqueue_script(
            'widgetbot-embed',
            'https://cdn.jsdelivr.net/npm/@widgetbot/html-embed',
            array(),
            null,
            true
        );

        if ( file_exists( SNIPS_PATH . 'assets/css/snips-discord.css' ) ) {
            wp_enqueue_style(
                'snips-discord-css',
                SNIPS_URL . 'assets/css/snips-discord.css',
                array(),
                SNIPS_VERSION
            );
        }
    }

    public function register_discord_block() {
        wp_register_script(
            'snips-discord-block-editor',
            SNIPS_URL . 'assets/js/snips-discord-block.js',
            array( 'wp-blocks', 'wp-element', 'wp-editor', 'wp-components', 'wp-block-editor' ),
            SNIPS_VERSION
        );

        register_block_type( 'snips/discord-frame', array(
            'editor_script'   => 'snips-discord-block-editor',
            'render_callback' => array( $this, 'render_block_frame' ),
            'attributes'      => array(
                'serverId'   => array( 'type' => 'string', 'default' => '' ),
                'channelId'  => array( 'type' => 'string', 'default' => '' ),
                'height'     => array( 'type' => 'number', 'default' => 0 ),
                'matchFull'  => array( 'type' => 'boolean', 'default' => true ),
            ),
        ) );
    }

    public function get_presence_data( $server_id ) {
        if ( empty( $server_id ) ) {
            return array( 'status' => 'red', 'label' => 'STANDBY' );
        }

        $transient_key = 'snips_disc_pres_' . $server_id;
        $state         = get_transient( $transient_key );

        if ( false === $state ) {
            $response = wp_remote_get( "https://discord.com/api/guilds/{$server_id}/widget.json", array( 'timeout' => 4 ) );

            if ( is_wp_error( $response ) || 200 !== wp_remote_retrieve_response_code( $response ) ) {
                $state = array( 'status' => 'green', 'label' => 'LIVE' );
            } else {
                $body     = json_decode( wp_remote_retrieve_body( $response ), true );
                $presence = isset( $body['presence_count'] ) ? intval( $body['presence_count'] ) : 0;

                if ( $presence > 0 ) {
                    $state = array( 'status' => 'green', 'label' => $presence . ' ACTIVE' );
                } else {
                    $state = array( 'status' => 'yellow', 'label' => 'STANDBY' );
                }
            }

            set_transient( $transient_key, $state, 300 );
        }

        return $state;
    }

    public function render_status_shortcode( $atts ) {
        $options   = get_option( 'snips_settings', array() );
        $default_s = ! empty( $options['default_discord_server'] ) ? $options['default_discord_server'] : '';

        $atts = shortcode_atts(
            array(
                'server'     => $default_s,
                'show_label' => 'true',
            ),
            $atts,
            'snip_discord_status'
        );

        if ( empty( $atts['server'] ) ) {
            return '';
        }

        $presence   = $this->get_presence_data( $atts['server'] );
        $dot_class  = 'snip-dot-' . esc_attr( $presence['status'] );
        $show_label = filter_var( $atts['show_label'], FILTER_VALIDATE_BOOLEAN );

        $output  = '<span class="snip-discord-status-wrap">';
        $output .= '<span class="snip-indicator-dot ' . $dot_class . '"></span>';
        if ( $show_label ) {
            $output .= '<span class="snip-status-text">' . esc_html( $presence['label'] ) . '</span>';
        }
        $output .= '</span>';

        return $output;
    }

    public function render_block_frame( $attributes ) {
        $options    = get_option( 'snips_settings', array() );
        $server_id  = ! empty( $attributes['serverId'] ) ? esc_attr( $attributes['serverId'] ) : ( ! empty( $options['default_discord_server'] ) ? esc_attr( $options['default_discord_server'] ) : '' );
        $channel_id = ! empty( $attributes['channelId'] ) ? esc_attr( $attributes['channelId'] ) : '';
        $match_full = isset( $attributes['matchFull'] ) ? (bool) $attributes['matchFull'] : true;
        $height     = ! empty( $attributes['height'] ) ? intval( $attributes['height'] ) : 0;

        if ( empty( $server_id ) ) {
            return '<div class="snip-widgetbot-placeholder" style="padding: 24px; background: #0c0d10; border: 1px dashed #27272a; border-radius: 4px; text-align: center; color: #71717a; font-family: var(--snip-global-font, monospace); font-size: 0.82rem;">' . esc_html__( 'Please configure a Discord Server ID in the block sidebar or Snips settings.', 'analogues-snips' ) . '</div>';
        }

        $height_style = ( $match_full || 0 === $height ) ? 'height: 100%; min-height: 500px;' : 'height: ' . $height . 'px;';

        ob_start();
        ?>
        <div class="snip-widgetbot-container <?php echo $match_full ? 'snip-discord-fullheight' : ''; ?>" style="<?php echo esc_attr( $height_style ); ?>">
            <widgetbot 
                server="<?php echo esc_attr( $server_id ); ?>" 
                <?php if ( ! empty( $channel_id ) ) : ?>channel="<?php echo esc_attr( $channel_id ); ?>"<?php endif; ?>
                width="100%" 
                height="100%">
            </widgetbot>
        </div>
        <?php
        return ob_get_clean();
    }
}