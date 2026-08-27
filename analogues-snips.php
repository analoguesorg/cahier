<?php
/**
 * SNIPS - Core Loader
 *
 * Custom modifications for analogues.org.
 *
 * @wordpress-plugin
 * Plugin Name:         Snips
 * Plugin URI:          https://github.com/analoguesorg/snips
 * Description:         Custom modifications for analogues.org.
 *
 * Version:             1.3.0
 * Text Domain:         analogues-snips
 * GitHub Plugin URI:   analoguesorg/snips
 * Primary Branch:      main
 *
 * Requires at least:   6.0
 * Requires PHP:        7.4
 *
 * Author:              Sahil Nawab
 * Author URI:          https://www.analogues.org/development
 * License:             GPL-3.0-or-later
 * License URI:         https://www.gnu.org/licenses/gpl-3.0.html
 *
 * @package             Analogues-Snips
 * @branch              feature/type-studio
 * @version             1.3.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

define( 'SNIPS_VERSION', '1.2.1' );
define( 'SNIPS_PATH', plugin_dir_path( __FILE__ ) );
define( 'SNIPS_URL', plugin_dir_url( __FILE__ ) );

// Load modular classes
require_once SNIPS_PATH . 'includes/class-snips-admin.php';
require_once SNIPS_PATH . 'includes/class-snips-date.php';
require_once SNIPS_PATH . 'includes/class-snips-metars.php';
require_once SNIPS_PATH . 'includes/class-snips-discord.php';
require_once SNIPS_PATH . 'includes/class-snips-telegrams.php';
require_once SNIPS_PATH . 'includes/class-snips-typography.php'; // Typography module

function analogues_snips_init() {
    ( new Snips_Admin() )->init();
    ( new Snips_Date() )->init();
    ( new Snips_METAR() )->init();
    ( new Snips_Discord() )->init();
    ( new Snips_Telegrams() )->init();
    ( new Snips_Typography() )->init();
}
add_action( 'plugins_loaded', 'analogues_snips_init' );