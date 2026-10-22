<?php

/**
 * Admin notices.
 *
 * A persistent, dismissible queue of admin notices.
 *
 * WordPress can render a notice (`wp_admin_notice()`, since 6.4), but it has
 * no memory of one. Its `dismissible` flag only hides the notice in the
 * browser, so the notice is back on the next page load. A notice that should
 * stay dismissed, or that is raised in one request and shown in a later one
 * (an update routine, a background job), needs its own storage.
 *
 * That storage is a single option holding a list of notice names. The markup
 * is never stored: each name maps to a render callback registered at boot, so
 * a notice always renders with current code and current state. A queued name
 * with no registered renderer is simply ignored.
 *
 * Every add() and remove() re-reads the option and writes it straight back.
 * An earlier design kept the list in memory and saved it on `shutdown`, which
 * let any request that ran alongside (a cron run, an Action Scheduler async
 * job) overwrite the list with its own stale copy and drop notices another
 * request had just added. A read-modify-write per change keeps that window to
 * the width of one update_option() call.
 */

namespace Hybrid\Tools\WordPress;

class AdminNotices {
    /**
     * Render callbacks, keyed by notice name.
     *
     * @var array<string, callable>
     */
    protected array $renderers = [];

    /**
     * The names below are built from the prefix and stored or sent by
     * browsers, so they have to stay stable across releases:
     *
     * - option:       `{prefix}_admin_notices`
     * - query arg:    `{prefix}-hide-notice`
     * - nonce field:  `_{prefix}_notice_nonce`
     * - nonce action: `{prefix}_hide_notices_nonce`
     *
     * @param string             $prefix     Prefix for the option, query arg, nonce and actions.
     * @param string             $capability Capability needed to see and dismiss notices.
     * @param array<string>|null $screens    Screen IDs to render on; null for every admin screen.
     */
    public function __construct(
        protected string $prefix,
        protected string $capability = 'manage_options',
        protected ?array $screens = null
    ) {}

    /**
     * Boot.
     */
    public function boot(): void {
        add_action( 'admin_init', $this->handleDismiss( ... ) );
        add_action( 'admin_notices', $this->render( ... ) );
    }

    /**
     * Register the callback that echoes a notice's markup.
     *
     * @param string   $name   Notice name.
     * @param callable $render Echoes the notice markup.
     */
    public function register( string $name, callable $render ): static {
        $this->renderers[ $name ] = $render;

        return $this;
    }

    /**
     * Queue a notice. Saved immediately.
     *
     * @param string $name Notice name.
     */
    public function add( string $name ): void {
        $this->save( array_unique( array_merge( $this->all(), [ $name ] ) ) );
    }

    /**
     * Remove a notice from the queue. Saved immediately.
     *
     * @param string $name Notice name.
     */
    public function remove( string $name ): void {
        $this->save( array_diff( $this->all(), [ $name ] ) );
    }

    /**
     * Whether a notice is queued.
     *
     * @param string $name Notice name.
     */
    public function has( string $name ): bool {
        return in_array( $name, $this->all(), true );
    }

    /**
     * Queued notice names, read fresh from the option.
     *
     * @return array<int, string>
     */
    public function all(): array {
        $notices = get_option( $this->optionName(), [] );

        return is_array( $notices ) ? array_values( $notices ) : [];
    }

    /**
     * Empty the queue.
     */
    public function clear(): void {
        $this->save( [] );
    }

    /**
     * Nonced URL that dismisses a notice, relative to the current admin page.
     *
     * @param string $name Notice name.
     */
    public function dismissUrl( string $name ): string {
        return wp_nonce_url(
            add_query_arg( $this->prefix . '-hide-notice', $name ),
            $this->prefix . '_hide_notices_nonce',
            '_' . $this->prefix . '_notice_nonce'
        );
    }

    /**
     * Handle the dismiss GET on `admin_init`.
     *
     * The nonce is checked before the capability so a forged link fails with
     * the same message whoever clicks it.
     */
    protected function handleDismiss(): void {
        $arg   = $this->prefix . '-hide-notice';
        $field = '_' . $this->prefix . '_notice_nonce';

        // phpcs:disable WordPress.Security.NonceVerification.Recommended -- verified below.
        if ( ! isset( $_GET[ $arg ], $_GET[ $field ] ) ) {
            return;
        }

        if ( ! wp_verify_nonce( sanitize_key( wp_unslash( $_GET[ $field ] ) ), $this->prefix . '_hide_notices_nonce' ) ) {
            wp_die( esc_html__( 'Action failed. Please refresh the page and retry.' ) );
        }

        if ( ! current_user_can( $this->capability ) ) {
            wp_die( esc_html__( 'Sorry, you are not allowed to do that.' ) );
        }

        $name = sanitize_text_field( wp_unslash( $_GET[ $arg ] ) );
        // phpcs:enable

        // The nonce does not cover the name, so a valid link could otherwise
        // write user meta and fire a hide action for any name at all.
        if ( ! isset( $this->renderers[ $name ] ) ) {
            return;
        }

        $this->remove( $name );

        update_user_meta( get_current_user_id(), 'dismissed_' . $name . '_notice', true );

        do_action( "{$this->prefix}_hide_{$name}_notice" );
    }

    /**
     * Render queued, registered notices on `admin_notices`.
     */
    protected function render(): void {
        if ( ! current_user_can( $this->capability ) || ! $this->onAllowedScreen() ) {
            return;
        }

        foreach ( $this->all() as $name ) {
            if ( isset( $this->renderers[ $name ] ) ) {
                ( $this->renderers[ $name ] )();
            }
        }
    }

    /**
     * Whether the current screen is one notices render on.
     *
     * Public so a consumer can enqueue notice styles on exactly the same
     * screens.
     */
    public function onAllowedScreen(): bool {
        if ( null === $this->screens ) {
            return true;
        }

        $screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;

        return $screen && in_array( $screen->id, $this->screens, true );
    }

    /**
     * Option holding the queued notice names.
     */
    protected function optionName(): string {
        return $this->prefix . '_admin_notices';
    }

    /**
     * Save the queue.
     *
     * @param array<int, string> $notices Notice names.
     */
    protected function save( array $notices ): void {
        update_option( $this->optionName(), array_values( $notices ) );
    }
}
