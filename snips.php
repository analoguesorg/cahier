<?php
/**
 * Plugin Name: Snips
 * Plugin URI:  https://www.analogues.org/development
 * Description: Custom components and modifications for the analogues.org website.
 * Version:     1.0.3
 * Author:      Sahil Nawab
 * Text Domain: analogues-snips
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

define( 'SNIPS_VERSION', '1.0.3' );
define( 'SNIPS_PATH', plugin_dir_path( __FILE__ ) );
define( 'SNIPS_URL', plugin_dir_url( __FILE__ ) );

// Load modular classes
require_once SNIPS_PATH . 'includes/class-snips-admin.php';
require_once SNIPS_PATH . 'includes/class-snips-date.php';
require_once SNIPS_PATH . 'includes/class-snips-metars.php';
require_once SNIPS_PATH . 'includes/class-snips-discord.php';
require_once SNIPS_PATH . 'includes/class-snips-telegrams.php';

function analogues_snips_init() {
    ( new Snips_Admin() )->init();
    ( new Snips_Date() )->init();
    ( new Snips_METAR() )->init();
    ( new Snips_Discord() )->init();
    ( new Snips_Telegrams() )->init();
}
add_action( 'plugins_loaded', 'analogues_snips_init' );