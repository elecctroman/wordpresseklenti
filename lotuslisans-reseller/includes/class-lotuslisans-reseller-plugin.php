<?php
/**
 * Main plugin bootstrap.
 *
 * @package LotusLisansReseller
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

require_once LOTUSLISANS_RESELLER_PATH . 'includes/class-lotuslisans-api-client.php';
require_once LOTUSLISANS_RESELLER_PATH . 'includes/class-lotuslisans-admin.php';

class LotusLisans_Reseller_Plugin {

    const OPTION_KEY        = 'lotuslisans_reseller_options';
    const LICENSE_OPTION    = 'lotuslisans_reseller_license_host';
    const PRODUCTS_OPTION   = 'lotuslisans_reseller_last_products';
    const NOTICE_TRANSIENT  = 'lotuslisans_reseller_notice_buffer';
    const BALANCE_TRANSIENT = 'lotuslisans_reseller_balance_cache';

    /**
     * Plugin instance.
     *
     * @var LotusLisans_Reseller_Plugin|null
     */
    protected static $instance = null;

    /**
     * API client.
     *
     * @var LotusLisans_API_Client
     */
    protected $api_client;

    /**
     * Admin handler.
     *
     * @var LotusLisans_Admin
     */
    protected $admin;

    /**
     * Plugin basename.
     *
     * @var string
     */
    protected $plugin_basename;

    /**
     * Singleton bootstrap.
     */
    public static function instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    /**
     * Constructor.
     */
    protected function __construct() {
        $this->plugin_basename = plugin_basename( LOTUSLISANS_RESELLER_FILE );
        $this->api_client      = new LotusLisans_API_Client( $this );
        $this->admin           = new LotusLisans_Admin( $this, $this->api_client );

        register_activation_hook( LOTUSLISANS_RESELLER_FILE, array( $this, 'activate' ) );

        add_action( 'plugins_loaded', array( $this, 'maybe_load_textdomain' ) );
        add_action( 'plugins_loaded', array( $this, 'enforce_license' ), 1 );
        add_action( 'admin_init', array( $this, 'register_settings' ) );
        add_action( 'admin_notices', array( $this, 'render_buffered_notices' ) );
        add_action( 'admin_bar_menu', array( $this, 'register_admin_bar_nodes' ), 100 );
    }

    /**
     * Get plugin options.
     *
     * @return array
     */
    public function get_options() {
        $defaults = array(
            'api_key' => '',
        );

        $options = get_option( self::OPTION_KEY, array() );

        if ( ! is_array( $options ) ) {
            $options = array();
        }

        return wp_parse_args( $options, $defaults );
    }

    /**
     * Update plugin options.
     *
     * @param array $options Options to update.
     */
    public function update_options( array $options ) {
        update_option( self::OPTION_KEY, $options );
    }

    /**
     * Retrieve the stored API key.
     *
     * @return string
     */
    public function get_api_key() {
        $options = $this->get_options();

        return isset( $options['api_key'] ) ? $options['api_key'] : '';
    }

    /**
     * Register plugin settings.
     */
    public function register_settings() {
        register_setting( 'lotuslisans_reseller_settings', self::OPTION_KEY, array( $this, 'sanitize_options' ) );
    }

    /**
     * Sanitize options.
     *
     * @param array $options Options to sanitize.
     *
     * @return array
     */
    public function sanitize_options( $options ) {
        $sanitized = array();

        if ( isset( $options['api_key'] ) ) {
            $sanitized['api_key'] = sanitize_text_field( $options['api_key'] );
        }

        return $sanitized;
    }

    /**
     * Activation callback.
     */
    public function activate() {
        $current_host = $this->get_current_host();
        update_option( self::LICENSE_OPTION, $current_host, false );
    }

    /**
     * Enforce domain license.
     */
    public function enforce_license() {
        if ( wp_doing_ajax() ) {
            return;
        }

        $stored_host = get_option( self::LICENSE_OPTION );
        $current_host = $this->get_current_host();

        if ( empty( $stored_host ) ) {
            update_option( self::LICENSE_OPTION, $current_host, false );
            return;
        }

        if ( hash_equals( $stored_host, $current_host ) ) {
            return;
        }

        if ( is_admin() && current_user_can( 'activate_plugins' ) ) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
            deactivate_plugins( $this->plugin_basename );
            add_action(
                'admin_notices',
                function() use ( $stored_host, $current_host ) {
                    printf(
                        '<div class="notice notice-error"><p>%s</p></div>',
                        esc_html( sprintf(
                            /* translators: 1: stored host 2: current host */
                            __( 'LotusLisans Reseller eklentisi bu alan adına lisanslı: %1$s. Mevcut alan adı (%2$s) ile eşleşmediği için eklenti devre dışı bırakıldı.', 'lotuslisans-reseller' ),
                            $stored_host,
                            $current_host
                        ) )
                    );
                }
            );
        }
    }

    /**
     * Get current site host.
     *
     * @return string
     */
    public function get_current_host() {
        $home_url = home_url();
        $host     = wp_parse_url( $home_url, PHP_URL_HOST );

        return strtolower( (string) $host );
    }

    /**
     * Load plugin textdomain.
     */
    public function maybe_load_textdomain() {
        load_plugin_textdomain( 'lotuslisans-reseller', false, dirname( $this->plugin_basename ) . '/languages' );
    }

    /**
     * Store product snapshot for change detection.
     *
     * @param array $products Products array.
     */
    public function save_product_snapshot( array $products ) {
        update_option( self::PRODUCTS_OPTION, $products, false );
    }

    /**
     * Retrieve stored snapshot of products.
     *
     * @return array
     */
    public function get_product_snapshot() {
        $products = get_option( self::PRODUCTS_OPTION, array() );

        return is_array( $products ) ? $products : array();
    }

    /**
     * Buffer an admin notice to display later.
     *
     * @param string $message Message content.
     * @param string $type    Notice type.
     */
    public function buffer_notice( $message, $type = 'info' ) {
        $notices   = get_transient( self::NOTICE_TRANSIENT );
        $notices   = is_array( $notices ) ? $notices : array();
        $notices[] = array(
            'message' => wp_kses_post( $message ),
            'type'    => sanitize_html_class( $type ),
        );

        set_transient( self::NOTICE_TRANSIENT, $notices, MINUTE_IN_SECONDS * 30 );
    }

    /**
     * Render buffered notices.
     */
    public function render_buffered_notices() {
        $notices = get_transient( self::NOTICE_TRANSIENT );
        if ( empty( $notices ) || ! is_array( $notices ) ) {
            return;
        }

        delete_transient( self::NOTICE_TRANSIENT );

        foreach ( $notices as $notice ) {
            $type    = isset( $notice['type'] ) ? $notice['type'] : 'info';
            $message = isset( $notice['message'] ) ? $notice['message'] : '';

            printf( '<div class="notice notice-%1$s"><p>%2$s</p></div>', esc_attr( $type ), $message );
        }
    }

    /**
     * Update cached balance data.
     *
     * @param array $data Balance payload.
     */
    public function set_cached_balance( array $data ) {
        set_transient( self::BALANCE_TRANSIENT, $data, MINUTE_IN_SECONDS * 15 );
    }

    /**
     * Retrieve cached balance data.
     *
     * @return array|null
     */
    public function get_cached_balance() {
        $data = get_transient( self::BALANCE_TRANSIENT );

        return is_array( $data ) ? $data : null;
    }

    /**
     * Register admin bar entries for balance and notifications.
     *
     * @param WP_Admin_Bar $admin_bar Admin bar instance.
     */
    public function register_admin_bar_nodes( $admin_bar ) {
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }

        $balance_data = $this->get_cached_balance();

        if ( null === $balance_data && ! empty( $this->get_api_key() ) ) {
            $response = $this->api_client()->get_user();
            if ( ! is_wp_error( $response ) && isset( $response['data'] ) ) {
                $balance_data = $response['data'];
            }
        }
        $balance_text = __( 'LotusLisans Bakiye', 'lotuslisans-reseller' );

        if ( null !== $balance_data && isset( $balance_data['credit'] ) ) {
            $balance_text = sprintf(
                __( 'LotusLisans Bakiye: %s', 'lotuslisans-reseller' ),
                esc_html( $balance_data['credit'] )
            );
        }

        $admin_bar->add_menu(
            array(
                'id'    => 'lotuslisans-balance',
                'title' => esc_html( $balance_text ),
                'href'  => admin_url( 'admin.php?page=lotuslisans-reseller' ),
                'meta'  => array( 'title' => __( 'LotusLisans API Ayarları', 'lotuslisans-reseller' ) ),
            )
        );

        $notices = get_transient( self::NOTICE_TRANSIENT );
        if ( ! empty( $notices ) && is_array( $notices ) ) {
            $admin_bar->add_menu(
                array(
                    'id'     => 'lotuslisans-alerts',
                    'parent' => 'top-secondary',
                    'title'  => __( 'LotusLisans Bildirimleri', 'lotuslisans-reseller' ),
                    'href'   => admin_url( 'admin.php?page=lotuslisans-reseller' ),
                )
            );
        }
    }

    /**
     * Provide access to the admin handler.
     *
     * @return LotusLisans_Admin
     */
    public function admin() {
        return $this->admin;
    }

    /**
     * Provide access to the API client.
     *
     * @return LotusLisans_API_Client
     */
    public function api_client() {
        return $this->api_client;
    }
}
