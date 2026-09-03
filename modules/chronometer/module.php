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
        return 'Dynamic typography-aware date and timestamp shortcodes for archival dispatches.';
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

    public function has_settings_tab(): bool {
        return true;
    }

    public function render_settings_tab(): void {
        ?>
        <div class="cahier-dev-panel">
            <h2 class="cahier-section__title">Chronometer Documentation & Reference</h2>
            <p>The Chronometer module registers dynamic shortcodes for formatting editorial timestamps.</p>
            <table class="cahier-table">
                <tbody>
                    <tr>
                        <th><code>[cahier_date]</code></th>
                        <td>Outputs current date using site default format.</td>
                    </tr>
                    <tr>
                        <th><code>[cahier_date format="F j, Y"]</code></th>
                        <td>Explicit format override using PHP date characters.</td>
                    </tr>
                    <tr>
                        <th>Typography Variable</th>
                        <td>Inherits CSS variable <code>--cahier-date-font</code> from theme.</td>
                    </tr>
                </tbody>
            </table>
        </div>
        <?php
    }
}