<?php
/**
 * Chronometer Module Manifest
 *
 * Satisfies the Module_Contract to register and boot the Chronometer date shortcodes within the Cahier ecosystem.
 *
 * @package Cahier\Modules\Chronometer
 */

declare(strict_types=1);

namespace Cahier\Modules\Chronometer;

use Cahier\Core\Module_Contract;
use Cahier\Modules\Chronometer\Adapters\Wordpress\Date_Shortcode;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

require_once __DIR__ . '/core/class-date-formatter.php';
require_once __DIR__ . '/adapters/wordpress/class-date-shortcode.php';

final class Module implements Module_Contract {

    public function get_slug(): string {
        return 'chronometer';
    }

    public function get_name(): string {
        return 'Chronometer';
    }

    public function get_description(): string {
        return 'Dynamic typography-aware date and timestamp shortcodes.';
    }

    public function is_active(): bool {
        return true;
    }

    public function boot(): void {
        $shortcode = new Date_Shortcode();
        $shortcode->register();
    }

    public function enqueue_admin_assets( string $hook ): void {}

    public function enqueue_frontend_assets(): void {}
}