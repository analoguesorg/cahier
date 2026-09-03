<?php
/**
 * Cahier Plugin Kernel
 *
 * The sovereign application kernel. Responsible for discovering, loading, and managing the lifecycle of autonomous modules.
 *
 * @package Cahier\Core
 */

declare(strict_types=1);

namespace Cahier\Core;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class Plugin {
    private static ?Plugin $instance = null;

    /**
     * All discovered modules.
     *
     * @var array<string, Module_Contract>
     */
    private array $modules = [];

    /**
     * The master candidate registry mapping module slugs to entry classes.
     *
     * @var array<string, class-string<Module_Contract>>
     */
    private array $registry = [
        'chronometer' => \Cahier\Modules\Chronometer\Module::class,
        'aviation'    => \Cahier\Modules\Aviation\Module::class,
        'dispatch'    => \Cahier\Modules\Dispatch\Module::class,
        'type_studio' => \Cahier\Modules\TypeStudio\Module::class,
        'patronage'   => \Cahier\Modules\Patronage\Module::class,
    ];

    public static function instance(): self {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        $this->load_modules();
        $this->boot_modules();

        // Boot central administration console
        require_once CAHIER_PATH . 'core/class-settings.php';
        Settings::instance( $this );
    }

    private function load_modules(): void {
        $saved_actives = get_option( 'cahier_active_modules', [
            'chronometer' => true,
            'aviation'    => true,
        ] );

        foreach ( $this->registry as $slug => $class_name ) {
            $folder_slug = str_replace( '_', '-', $slug );
            $module_entry = CAHIER_PATH . "modules/{$folder_slug}/module.php";

            if ( file_exists( $module_entry ) ) {
                require_once $module_entry;

                if ( class_exists( $class_name ) ) {
                    $module = new $class_name();
                    if ( $module instanceof Module_Contract ) {
                        $this->modules[ $slug ] = $module;
                    }
                }
            }
        }
    }

    private function boot_modules(): void {
        $active_slugs = $this->get_active_module_slugs();

        foreach ( $this->modules as $slug => $module ) {
            if ( in_array( $slug, $active_slugs, true ) ) {
                $module->boot();

                add_action( 'admin_enqueue_scripts', [ $module, 'enqueue_admin_assets' ] );
                add_action( 'wp_enqueue_scripts', [ $module, 'enqueue_frontend_assets' ] );
            }
        }
    }

    public function get_module( string $slug ): ?Module_Contract {
        return $this->modules[ $slug ] ?? null;
    }

    public function get_all_modules(): array {
        return $this->modules;
    }

    public function get_active_module_slugs(): array {
        $default = [ 'chronometer', 'aviation' ];
        $saved = get_option( 'cahier_active_modules', $default );
        return is_array( $saved ) ? $saved : $default;
    }
}