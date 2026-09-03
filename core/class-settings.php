<?php
/**
 * Cahier Central Administration Console
 *
 * Provides a unified workbench for module lifecycle management, module-specific configurations, and developer settings.
 *
 * @package Cahier\Core
 */

declare(strict_types=1);

namespace Cahier\Core;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class Settings {
    private static ?Settings $instance = null;
    private Plugin $kernel;

    public static function instance( Plugin $kernel ): self {
        if ( null === self::$instance ) {
            self::$instance = new self( $kernel );
        }
        return self::$instance;
    }

    private function __construct( Plugin $kernel ) {
        $this->kernel = $kernel;

        add_action( 'admin_menu', [ $this, 'register_menu' ] );
        add_action( 'admin_post_cahier_save_modules', [ $this, 'handle_module_toggle_save' ] );
        add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_console_styles' ] );
    }

    public function register_menu(): void {
        add_menu_page(
            'Cahier',
            'Cahier',
            'manage_options',
            'cahier',
            [ $this, 'render_console' ],
            'dashicons-book-alt',
            62
        );
    }

    public function enqueue_console_styles( string $hook ): void {
        if ( 'toplevel_page_cahier' !== $hook ) {
            return;
        }

        wp_enqueue_style(
            'cahier-admin-console',
            CAHIER_URL . 'assets/css/admin-console.css',
            [],
            CAHIER_VERSION
        );
    }

    public function handle_module_toggle_save(): void {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( 'Unauthorized' );
        }

        check_admin_referer( 'cahier_save_modules_action', 'cahier_modules_nonce' );

        $selected = isset( $_POST['cahier_modules'] ) && is_array( $_POST['cahier_modules'] )
            ? array_map( 'sanitize_key', $_POST['cahier_modules'] )
            : [];

        update_option( 'cahier_active_modules', $selected );

        wp_safe_redirect( add_query_arg( [ 'page' => 'cahier', 'tab' => 'registry', 'saved' => 'true' ], admin_url( 'admin.php' ) ) );
        exit;
    }

    public function render_console(): void {
        $active_tab = isset( $_GET['tab'] ) ? sanitize_key( $_GET['tab'] ) : 'registry';
        $modules    = $this->kernel->get_all_modules();
        $actives    = $this->kernel->get_active_module_slugs();
        ?>
        <div class="wrap cahier-wrap">
            <header class="cahier-header">
                <div class="cahier-brand">
                    <h1 class="cahier-title">Cahier</h1>
                    <span class="cahier-badge">v<?php echo esc_html( CAHIER_VERSION ); ?></span>
                </div>
            </header>

            <nav class="cahier-tabs">
                <a href="<?php echo esc_url( admin_url( 'admin.php?page=cahier&tab=registry' ) ); ?>"
                   class="cahier-tab cahier-tab--registry <?php echo 'registry' === $active_tab ? 'is-active' : ''; ?>">
                   Registry
                </a>

                <?php foreach ( $modules as $slug => $module ) : ?>
                    <?php if ( in_array( $slug, $actives, true ) && $module->has_settings_tab() ) : ?>
                        <a href="<?php echo esc_url( admin_url( 'admin.php?page=cahier&tab=' . $slug ) ); ?>"
                           class="cahier-tab cahier-tab--<?php echo esc_attr( $slug ); ?> <?php echo $active_tab === $slug ? 'is-active' : ''; ?>">
                           <?php echo esc_html( $module->get_name() ); ?>
                        </a>
                    <?php endif; ?>
                <?php endforeach; ?>

                <a href="<?php echo esc_url( admin_url( 'admin.php?page=cahier&tab=developer' ) ); ?>"
                   class="cahier-tab cahier-tab--developer <?php echo 'developer' === $active_tab ? 'is-active' : ''; ?>">
                   Developer
                </a>
            </nav>

            <main class="cahier-stage">
                <?php
                if ( isset( $_GET['saved'] ) ) {
                    echo '<div class="cahier-notice"><p>Module settings saved.</p></div>';
                }

                if ( 'registry' === $active_tab ) {
                    $this->render_registry_tab( $modules, $actives );
                } elseif ( 'developer' === $active_tab ) {
                    $this->render_developer_tab();
                } elseif ( isset( $modules[ $active_tab ] ) && $modules[ $active_tab ]->has_settings_tab() ) {
                    $modules[ $active_tab ]->render_settings_tab();
                }
                ?>
            </main>
        </div>
        <?php
    }

    private function render_registry_tab( array $modules, array $actives ): void {
        ?>
        <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
            <input type="hidden" name="action" value="cahier_save_modules">
            <?php wp_nonce_field( 'cahier_save_modules_action', 'cahier_modules_nonce' ); ?>

            <div class="cahier-grid">
                <?php foreach ( $modules as $slug => $module ) :
                    $is_checked = in_array( $slug, $actives, true );
                ?>
                    <article class="cahier-card <?php echo $is_checked ? 'is-enabled' : 'is-idle'; ?>">
                        <div class="cahier-card__header">
                            <div class="cahier-card__meta">
                                <span class="cahier-card__slug"><?php echo esc_html( $slug ); ?></span>
                                <h2 class="cahier-card__title"><?php echo esc_html( $module->get_name() ); ?></h2>
                            </div>
                            <label class="cahier-switch">
                                <input type="checkbox" name="cahier_modules[]" value="<?php echo esc_attr( $slug ); ?>" <?php checked( $is_checked ); ?>>
                                <span class="cahier-switch__slider"></span>
                            </label>
                        </div>
                        <div class="cahier-card__body">
                            <p><?php echo esc_html( $module->get_description() ); ?></p>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>

            <div class="cahier-actions">
                <button type="submit" class="button button-primary cahier-button">Apply Changes</button>
            </div>
        </form>
        <?php
    }

    private function render_developer_tab(): void {
        // Blank developer panel for now
    }
}