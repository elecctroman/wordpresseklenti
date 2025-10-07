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
require_once LOTUSLISANS_RESELLER_PATH . 'includes/providers/class-lotuslisans-abstract-provider-client.php';
require_once LOTUSLISANS_RESELLER_PATH . 'includes/providers/class-lotuslisans-netgsm-client.php';
require_once LOTUSLISANS_RESELLER_PATH . 'includes/providers/class-lotuslisans-turkpin-client.php';
require_once LOTUSLISANS_RESELLER_PATH . 'includes/providers/class-lotuslisans-pinabi-client.php';

class LotusLisans_Reseller_Plugin {

    /**
     * Option key for storing integration settings.
     */
    const OPTION_KEY = 'lotuslisans_reseller_options';

    /**
     * Option key for storing last synced product snapshot.
     */
    const PRODUCTS_OPTION = 'lotuslisans_reseller_products';

    /**
     * Transient key used for temporary admin notices.
     */
    const NOTICE_TRANSIENT = 'lotuslisans_reseller_notices';

    /**
     * Transient key used for caching Lotus balance data.
     */
    const BALANCE_TRANSIENT = 'lotuslisans_reseller_balance';

    /**
     * Supported provider slugs.
     */
    const PROVIDER_LOTUS   = 'lotus';
    const PROVIDER_NETGSM  = 'netgsm';
    const PROVIDER_TURKPIN = 'turkpin';
    const PROVIDER_PINABI  = 'pinabi';

    /**
     * Option key used for license host binding.
     */
    const LICENSE_OPTION = 'lotuslisans_license_host';

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
     * Cached provider client instances.
     *
     * @var array<string, LotusLisans_Abstract_Provider_Client>
     */
    protected $provider_clients = array();

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
        $defaults = $this->get_default_options();

        $options = get_option( self::OPTION_KEY, array() );

        if ( ! is_array( $options ) ) {
            $options = array();
        }

        if ( isset( $options['api_key'] ) && ( ! isset( $options[ self::PROVIDER_LOTUS ]['api_key'] ) || empty( $options[ self::PROVIDER_LOTUS ]['api_key'] ) ) ) {
            $options[ self::PROVIDER_LOTUS ]['api_key'] = $options['api_key'];
            unset( $options['api_key'] );
        }

        return array_replace_recursive( $defaults, $options );
    }

    /**
     * Retrieve default options for all providers.
     *
     * @return array
     */
    protected function get_default_options() {
        return array(
            self::PROVIDER_LOTUS   => array(
                'api_key' => '',
            ),
            self::PROVIDER_NETGSM  => array(
                'base_url' => 'https://api.netgsm.com.tr',
                'usercode' => '',
                'password' => '',
                'header'   => '',
            ),
            self::PROVIDER_TURKPIN => array(
                'base_url'    => 'https://panel.turkpin.net/api/v1',
                'dealer_code' => '',
                'api_key'     => '',
                'secret_key'  => '',
            ),
            self::PROVIDER_PINABI  => array(
                'base_url'    => 'https://panel.pinabi.com/api',
                'username'    => '',
                'password'    => '',
                'api_key'     => '',
                'merchant_id' => '',
            ),
        );
    }

    /**
     * Update plugin options.
     *
     * @param array $options Options to update.
     */
    public function update_options( array $options ) {
        $defaults = $this->get_default_options();

        $options = array_replace_recursive( $defaults, $options );

        update_option( self::OPTION_KEY, $options );
    }

    /**
     * Retrieve the stored API key.
     *
     * @return string
     */
    public function get_api_key() {
        $options = $this->get_provider_options( self::PROVIDER_LOTUS );

        return isset( $options['api_key'] ) ? $options['api_key'] : '';
    }

    /**
     * Retrieve provider specific options.
     *
     * @param string $provider Provider slug.
     *
     * @return array
     */
    public function get_provider_options( $provider ) {
        $options = $this->get_options();

        return isset( $options[ $provider ] ) && is_array( $options[ $provider ] ) ? $options[ $provider ] : array();
    }

    /**
     * Update provider specific options.
     *
     * @param string $provider Provider slug.
     * @param array  $values   Values to merge.
     */
    public function update_provider_options( $provider, array $values ) {
        $options  = $this->get_options();
        $defaults = $this->get_default_options();

        if ( ! isset( $options[ $provider ] ) || ! is_array( $options[ $provider ] ) ) {
            $options[ $provider ] = array();
        }

        $options[ $provider ] = array_replace_recursive(
            isset( $defaults[ $provider ] ) ? $defaults[ $provider ] : array(),
            $options[ $provider ],
            $values
        );

        update_option( self::OPTION_KEY, $options );
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
        $current   = $this->get_options();
        $sanitized = $current;

        if ( isset( $options[ self::PROVIDER_LOTUS ] ) && is_array( $options[ self::PROVIDER_LOTUS ] ) ) {
            $sanitized[ self::PROVIDER_LOTUS ]['api_key'] = sanitize_text_field( $options[ self::PROVIDER_LOTUS ]['api_key'] );
        }

        if ( isset( $options[ self::PROVIDER_NETGSM ] ) && is_array( $options[ self::PROVIDER_NETGSM ] ) ) {
            $sanitized[ self::PROVIDER_NETGSM ]['base_url'] = esc_url_raw( $options[ self::PROVIDER_NETGSM ]['base_url'] );
            $sanitized[ self::PROVIDER_NETGSM ]['usercode'] = sanitize_text_field( $options[ self::PROVIDER_NETGSM ]['usercode'] );
            $sanitized[ self::PROVIDER_NETGSM ]['password'] = sanitize_text_field( $options[ self::PROVIDER_NETGSM ]['password'] );
            $sanitized[ self::PROVIDER_NETGSM ]['header']   = sanitize_text_field( $options[ self::PROVIDER_NETGSM ]['header'] );
        }

        if ( isset( $options[ self::PROVIDER_TURKPIN ] ) && is_array( $options[ self::PROVIDER_TURKPIN ] ) ) {
            $sanitized[ self::PROVIDER_TURKPIN ]['base_url']    = esc_url_raw( $options[ self::PROVIDER_TURKPIN ]['base_url'] );
            $sanitized[ self::PROVIDER_TURKPIN ]['dealer_code'] = sanitize_text_field( $options[ self::PROVIDER_TURKPIN ]['dealer_code'] );
            $sanitized[ self::PROVIDER_TURKPIN ]['api_key']     = sanitize_text_field( $options[ self::PROVIDER_TURKPIN ]['api_key'] );
            $sanitized[ self::PROVIDER_TURKPIN ]['secret_key']  = sanitize_text_field( $options[ self::PROVIDER_TURKPIN ]['secret_key'] );
        }

        if ( isset( $options[ self::PROVIDER_PINABI ] ) && is_array( $options[ self::PROVIDER_PINABI ] ) ) {
            $sanitized[ self::PROVIDER_PINABI ]['base_url']    = esc_url_raw( $options[ self::PROVIDER_PINABI ]['base_url'] );
            $sanitized[ self::PROVIDER_PINABI ]['username']    = sanitize_text_field( $options[ self::PROVIDER_PINABI ]['username'] );
            $sanitized[ self::PROVIDER_PINABI ]['password']    = sanitize_text_field( $options[ self::PROVIDER_PINABI ]['password'] );
            $sanitized[ self::PROVIDER_PINABI ]['api_key']     = sanitize_text_field( $options[ self::PROVIDER_PINABI ]['api_key'] );
            $sanitized[ self::PROVIDER_PINABI ]['merchant_id'] = sanitize_text_field( $options[ self::PROVIDER_PINABI ]['merchant_id'] );
        }

        return $sanitized;
    }

    /**
     * Activation callback.
     */
    public function activate() {
        $current_host = $this->get_current_host();

        if ( ! empty( $current_host ) ) {
            update_option( self::LICENSE_OPTION, $current_host, false );
        }
    }

    /**
     * Enforce domain license.
     */
    public function enforce_license() {
        if ( wp_doing_ajax() ) {
            return;
        }

        $stored_host  = get_option( self::LICENSE_OPTION );
        $current_host = $this->get_current_host();

        if ( empty( $stored_host ) ) {
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
                'href'  => admin_url( 'admin.php?page=lotuslisans-reseller-lotus' ),
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
                    'href'   => admin_url( 'admin.php?page=lotuslisans-reseller-lotus' ),
                )
            );
        }
    }

    /**
     * Retrieve a provider client instance.
     *
     * @param string $provider Provider slug.
     *
     * @return LotusLisans_Abstract_Provider_Client|null
     */
    public function provider_client( $provider ) {
        if ( isset( $this->provider_clients[ $provider ] ) ) {
            return $this->provider_clients[ $provider ];
        }

        $client = $this->instantiate_provider_client( $provider );

        if ( $client ) {
            $this->provider_clients[ $provider ] = $client;
        }

        return $client;
    }

    /**
     * Create a provider client instance for a given provider slug.
     *
     * @param string $provider Provider slug.
     *
     * @return LotusLisans_Abstract_Provider_Client|null
     */
    protected function instantiate_provider_client( $provider ) {
        switch ( $provider ) {
            case self::PROVIDER_NETGSM:
                return new LotusLisans_Netgsm_Client( $this );

            case self::PROVIDER_TURKPIN:
                return new LotusLisans_Turkpin_Client( $this );

            case self::PROVIDER_PINABI:
                return new LotusLisans_Pinabi_Client( $this );

            default:
                return null;
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
