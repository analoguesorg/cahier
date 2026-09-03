<?php
/**
 * Module Contract Interface
 *
 * Defines the lifecycle and runtime requirements that every autonomous Cahier module must implement to be registered and booted by the central kernel.
 *
 * @package Cahier\Core
 */

declare(strict_types=1);

namespace Cahier\Core;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

interface Module_Contract {
    /**
     * Unique machine slug (e.g., 'dispatch', 'chronometer')
     */
    public function get_slug(): string;

    /**
     * Human-readable title for admin settings
     */
    public function get_name(): string;

    /**
     * Brief mechanical description
     */
    public function get_description(): string;

    /**
     * Determine if module is currently enabled in settings or config
     */
    public function is_active(): bool;

    /**
     * Run all WordPress hooks and setup when the module is booted
     */
    public function boot(): void;

    /**
     * Enqueue module-specific admin assets
     */
    public function enqueue_admin_assets( string $hook ): void;

    /**
     * Enqueue module-specific frontend assets
     */
    public function enqueue_frontend_assets(): void;
    
    /**
     * Optional: Return whether the module provides a dedicated tab in the Cahier console.
     */
    public function has_settings_tab(): bool;
    
    /**
     * Optional: Render the module's custom settings view within its dedicated tab.
     */
    public function render_settings_tab(): void;
}