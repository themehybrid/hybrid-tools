<?php

/**
 * Manages the WordPress execution context.
 *
 * Detects whether the current request is in the admin, frontend, CLI, REST API, etc.
 * Provides a singleton instance and allows forced context overrides.
 */

namespace Hybrid\Tools\WordPress;

use Hybrid\Tools\WordPress\Traits\AccessiblePrivateMethods;
use JsonSerializable;
use WP_Rewrite;
use WP_Screen;

class WPContext implements JsonSerializable {

    use AccessiblePrivateMethods;

    private const AJAX = 'ajax';

    private const BACKOFFICE = 'backoffice';

    private const CLI = 'wpcli';

    private const CORE = 'core';

    private const CRON = 'cron';

    private const FRONTOFFICE = 'frontoffice';

    private const INSTALLING = 'installing';

    private const LOGIN = 'login';

    private const REST = 'rest';

    private const XML_RPC = 'xml-rpc';

    private const WP_ACTIVATE = 'wp-activate';

    private const BLOCK_EDITOR = 'block_editor';

    private const SITE_EDITOR = 'site_editor';

    private const ACTIONS_PRIORITY = -300982;

    private const ALL = [
        self::AJAX,
        self::BACKOFFICE,
        self::CLI,
        self::CORE,
        self::CRON,
        self::FRONTOFFICE,
        self::INSTALLING,
        self::LOGIN,
        self::REST,
        self::XML_RPC,
        self::WP_ACTIVATE,
        self::BLOCK_EDITOR,
        self::SITE_EDITOR,
    ];

    /** @var array<value-of<self::ALL>, bool> Context state storage */
    private $data;

    /** @var array<string, callable> Registered action callbacks */
    private array $actionCallbacks = [];

    /** @var \Hybrid\Tools\WordPress\WPContext|null */
    private static ?self $instance = null;

    /**
     * Constructor.
     *
     * @param array<value-of<\Hybrid\Tools\WordPress\WPContext::ALL>, bool> $data Initial context values.
     */
    public function __construct( array $data = [] ) {
        $this->data = empty( $data ) ? array_fill_keys( self::ALL, false ) : $data;
    }

    /**
     * Retrieves the singleton instance, initializing if necessary.
     *
     * @return \Hybrid\Tools\WordPress\WPContext
     */
    public static function getInstance(): self {
        if ( is_null( self::$instance ) ) {
            self::$instance = ( new self )->determine();
        }

        return self::$instance;
    }

    /**
     * Creates new instance with all contexts disabled.
     *
     * @return \Hybrid\Tools\WordPress\WPContext
     */
    final public function new(): self {
        return new self( array_fill_keys( self::ALL, false ) );
    }

    /**
     * Detects the current WordPress execution context.
     *
     * @return \Hybrid\Tools\WordPress\WPContext
     */
    final public function determine(): self {
        $screen = $this->getCurrentScreen();
        /** @psalm-suppress RedundantCondition */
        $installing = defined( 'WP_INSTALLING' ) && WP_INSTALLING;
        /** @psalm-suppress RedundantCondition */
        $xmlRpc        = defined( 'XMLRPC_REQUEST' ) && XMLRPC_REQUEST;
        $isCore        = defined( 'ABSPATH' );
        $isCli         = defined( 'WP_CLI' );
        $notInstalling = $isCore && ! $installing;
        $isAjax        = $notInstalling && wp_doing_ajax();
        $isAdmin       = $notInstalling && is_admin() && ! $isAjax;
        $isCron        = $notInstalling && wp_doing_cron();
        $isWpActivate  = $installing && is_multisite() && $this->isWpActivateRequest();

        $isBlockEditor = false;

        if ( $screen ) {
            $isBlockEditor = is_admin() && $screen->is_block_editor();
        }

        $isSiteEditor = $this->isSiteEditorRequest();

        $undetermined = $notInstalling && ! $isAdmin && ! $isCron && ! $isCli && ! $xmlRpc && ! $isAjax && ! $isBlockEditor && ! $isSiteEditor;

        $isRest  = $undetermined && $this->isRestRequest();
        $isLogin = $undetermined && ! $isRest && $this->isLoginRequest();

        // When nothing else matches, we assume it is a front-office request.
        $isFront = $undetermined && ! $isRest && ! $isLogin;

        /*
         * Note that when core is installing **only** `INSTALLING` will be true, not even `CORE`.
         * This is done to do as less as possible during installation, when most of WP does not act
         * as expected.
         */

        $instance = new self(
            [
                self::AJAX         => $isAjax,
                self::BACKOFFICE   => $isAdmin,
                self::CLI          => $isCli,
                self::CORE         => ( $isCore || $xmlRpc ) && ( ! $installing || $isWpActivate ),
                self::CRON         => $isCron,
                self::FRONTOFFICE  => $isFront,
                self::INSTALLING   => $installing && ! $isWpActivate,
                self::LOGIN        => $isLogin,
                self::REST         => $isRest,
                self::XML_RPC      => $xmlRpc && ! $installing,
                self::WP_ACTIVATE  => $isWpActivate,
                self::BLOCK_EDITOR => $isBlockEditor,
                self::SITE_EDITOR  => $isSiteEditor,
            ]
        );

        $instance->addActionHooks();

        return $instance;
    }

    /**
     * Determines if the request is for the REST API.
     *
     * @see https://core.trac.wordpress.org/ticket/42061#comment:39
     */
    private function isRestRequest(): bool {
        /** @psalm-suppress RedundantCondition */
        if (
            ( defined( 'REST_REQUEST' ) && \REST_REQUEST )
            || ( isset( $_GET['rest_route'] ) && (bool) $_GET['rest_route'] ) // phpcs:ignore
        ) {
            return true;
        }

        if ( ! get_option( 'permalink_structure' ) ) {
            return false;
        }

        /*
         * This is needed because, if called early, global $wp_rewrite is not defined but required
         * by get_rest_url(). WP will reuse what we set here, or in worst case will replace, but no
         * consequences for us in any case.
         */
        if ( empty( $GLOBALS['wp_rewrite'] ) ) {
            $GLOBALS['wp_rewrite'] = new WP_Rewrite;
        }

        $currentUrl  = (string) parse_url( (string) add_query_arg( [] ), PHP_URL_PATH );
        $currentPath = trim( $currentUrl, '/' ) . '/';

        $restUrlPath = (string) parse_url( (string) get_rest_url(), PHP_URL_PATH );
        $restPath    = trim( $restUrlPath, '/' ) . '/';

        return strpos( $currentPath, $restPath ) === 0;
    }

    /**
     * Determines if the request is a login request.
     */
    private function isLoginRequest(): bool {
        return is_login() !== false;
    }

    private function isWpActivateRequest(): bool {
        return $this->isPageNow( 'wp-activate.php', network_site_url( 'wp-activate.php' ) );
    }

    private function isPageNow( string $page, string $url ): bool {
        $pageNow = (string) ( $GLOBALS['pagenow'] ?? '' );
        if ( $pageNow && ( basename( $pageNow ) === $page ) ) {
            return true;
        }

        $currentPath = (string) parse_url( add_query_arg( [] ), PHP_URL_PATH );
        $targetPath  = (string) parse_url( $url, PHP_URL_PATH );

        return trim( $currentPath, '/' ) === trim( $targetPath, '/' );
    }

    /**
     * Forces specific context and disables auto-detection.
     *
     * @return \Hybrid\Tools\WordPress\WPContext
     */
    final public function force( string $context ): self {
        if ( ! in_array( $context, self::ALL, true ) ) {
            throw new \LogicException( esc_html( "'{$context}' is not a valid context." ) );
        }

        $this->removeActionHooks();

        $data             = array_fill_keys( self::ALL, false );
        $data[ $context ] = true;
        if ( ! in_array( $context, [ self::INSTALLING, self::CLI, self::CORE ], true ) ) {
            $data[ self::CORE ] = true;
        }

        $this->data = $data;

        return $this;
    }

    /**
     * Enables CLI context flag.
     *
     * @return \Hybrid\Tools\WordPress\WPContext
     */
    final public function withCli(): self {
        $this->data[ self::CLI ] = true;

        return $this;
    }

    /**
     * Checks if any of given contexts are active.
     *
     * @param string $context
     * @param string ...$contexts
     */
    final public function is( string $context, string ...$contexts ): bool {
        array_unshift( $contexts, $context );

        foreach ( $contexts as $context ) {
            if ( ( $this->data[ $context ] ?? null ) ) {
                return true;
            }
        }

        return false;
    }

    public function isCore(): bool {
        return $this->is( self::CORE );
    }

    public function isFrontoffice(): bool {
        return $this->is( self::FRONTOFFICE );
    }

    public function isBackoffice(): bool {
        return $this->is( self::BACKOFFICE );
    }

    public function isBlockEditor(): bool {
        return $this->is( self::BLOCK_EDITOR );
    }

    public function isSiteEditor(): bool {
        return $this->is( self::SITE_EDITOR );
    }

    public function isAjax(): bool {
        return $this->is( self::AJAX );
    }

    public function isLogin(): bool {
        return $this->is( self::LOGIN );
    }

    public function isRest(): bool {
        return $this->is( self::REST );
    }

    public function isCron(): bool {
        return $this->is( self::CRON );
    }

    public function isWpCli(): bool {
        return $this->is( self::CLI );
    }

    public function isXmlRpc(): bool {
        return $this->is( self::XML_RPC );
    }

    public function isInstalling(): bool {
        return $this->is( self::INSTALLING );
    }

    public function isWpActivate(): bool {
        return $this->is( self::WP_ACTIVATE );
    }

    /**
     * Serializes the context state.
     *
     * @return array<value-of<\Hybrid\Tools\WordPress\WPContext::ALL>, bool>
     */
    public function jsonSerialize(): array {
        return $this->data;
    }

    /**
     * Registers WordPress hooks for dynamic context updates.
     *
     * When context is determined very early we do our best to understand some context like
     * login, rest and front-office even if WordPress normally would require a later hook.
     * When that later hook happen, we change what we have determined, leveraging the more
     * "core-compliant" approach.
     */
    private function addActionHooks(): void {
        $this->actionCallbacks = [
            'login_init'        => function (): void {
                $this->resetAndForce( self::LOGIN );
            },
            'rest_api_init'     => function (): void {
                $this->resetAndForce( self::REST );
            },
            'rest_api_init'     => function (): void {
                $this->isSiteEditorRequest() && $this->resetAndForce( self::SITE_EDITOR );
            },
            'activate_header'   => function (): void {
                $this->resetAndForce( self::WP_ACTIVATE );
            },
            'template_redirect' => function (): void {
                $this->resetAndForce( self::FRONTOFFICE );
            },
            'current_screen'    => function ( WP_Screen $screen ): void {
                $screen->in_admin() and $this->resetAndForce( self::BACKOFFICE );
            },
            'current_screen'    => function ( WP_Screen $screen ): void {
                $screen->is_block_editor() and $this->resetAndForce( self::BLOCK_EDITOR );
            },
        ];

        foreach ( $this->actionCallbacks as $action => $callback ) {
            add_action( $action, $callback, self::ACTIONS_PRIORITY );
        }
    }

    /**
     * Removes registered context update hooks.
     *
     * When "force" is called on an instance created via `determine()` we need to remove added hooks
     * or what we are forcing might be overridden.
     */
    private function removeActionHooks(): void {
        foreach ( $this->actionCallbacks as $action => $callback ) {
            remove_action( $action, $callback, self::ACTIONS_PRIORITY );
        }

        $this->actionCallbacks = [];
    }

    private function resetAndForce( string $context ): void {
        $cli = $this->isWpCli();
        $this->force( $context );
        $cli and $this->withCli();
    }

    /**
     * Gets the WordPress current screen.
     *
     * @return \WP_Screen|null
     */
    public function getCurrentScreen() {
        return function_exists( 'get_current_screen' ) ? get_current_screen() : null;
    }

    /**
     * Detects Site Editor requests (admin or REST API).
     *
     * @return bool
     */
    private function isSiteEditorRequest() {
        // Admin context.
        if ( is_admin() ) {
            $screen = $this->getCurrentScreen();

            return $screen && 'site-editor' === $screen->base;
        }

        // REST API context.
        if ( $this->isRest() || $this->isRestRequest() ) {
            $uri = isset( $_SERVER['REQUEST_URI'] )
                ? esc_url_raw( wp_unslash( $_SERVER['REQUEST_URI'] ) )
                : '';

            return strpos( $uri, '/wp/v2/templates' ) !== false
                || strpos( $uri, '/wp/v2/template-parts' ) !== false;
        }

        return false;
    }
}
