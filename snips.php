<?php
/**
 * Plugin Name:       Snips
 * Plugin URI:        https://github.com/analoguesorg/snips
 * Description:       Custom modifications for analogues.org, including 
 * Version:           1.1.1
 * Requires at least: 6.0
 * Requires PHP:      7.4
 * Author:            Analogues & Sahil Nawab
 * Author URI:        https://analogues.org/development
 *
 * Text Domain:       analogues-snips
 *
 * GitHub Plugin URI: analoguesorg/snips
 * Primary Branch:    main
 *
 * @package Analogues-Snips
 * @version 1.1.1
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

define( 'SNIPS_VERSION', '1.1.1' );
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