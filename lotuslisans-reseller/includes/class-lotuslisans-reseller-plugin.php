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

    const OPTION_KEY         = 'lotuslisans_reseller_options';
    const LICENSE_OPTION     = 'lotuslisans_reseller_license_host';
    const PRODUCTS_OPTION    = 'lotuslisans_reseller_last_products';
    const NOTICE_TRANSIENT   = 'lotuslisans_reseller_notice_buffer';
    const BALANCE_TRANSIENT  = 'lotuslisans_reseller_balance_cache';
    const USER_API_KEY_META  = '_lotuslisans_reseller_api_key';
    const DOCS_ENDPOINT_SLUG = 'reseller-api-docs';
    const REST_NAMESPACE     = 'reseller-api/v1';

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
        add_action( 'init', array( __CLASS__, 'register_rewrite_endpoint' ) );
        add_filter( 'query_vars', array( $this, 'register_query_vars' ) );
        add_filter( 'template_include', array( $this, 'maybe_render_docs_template' ) );
        add_action( 'rest_api_init', array( $this, 'register_rest_routes' ) );
        add_action( 'init', array( $this, 'maybe_handle_account_actions' ) );
        add_action( 'woocommerce_account_dashboard', array( $this, 'render_customer_api_panel' ), 25 );
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
        update_option( $this->get_license_option_name(), $current_host, false );
        self::register_rewrite_endpoint();
        flush_rewrite_rules();
    }

    /**
     * Enforce domain license.
     */
    public function enforce_license() {
        if ( wp_doing_ajax() ) {
            return;
        }

        $stored_host = get_option( $this->get_license_option_name() );
        $current_host = $this->get_current_host();

        if ( empty( $stored_host ) ) {
            update_option( $this->get_license_option_name(), $current_host, false );
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
     * Retrieve the persistent option name used for license storage.
     *
     * Some hosting environments reported the class constant lookup failing,
     * therefore we provide a backwards-compatible fallback to the literal
     * option key.
     *
     * @return string
     */
    protected function get_license_option_name() {
        if ( defined( __CLASS__ . '::LICENSE_OPTION' ) ) {
            return self::LICENSE_OPTION;
        }

        return 'lotuslisans_reseller_license_host';
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
     * Register public documentation endpoint.
     */
    public static function register_rewrite_endpoint() {
        add_rewrite_endpoint( self::DOCS_ENDPOINT_SLUG, EP_ROOT | EP_PAGES );
    }

    /**
     * Register query vars used by the plugin.
     *
     * @param array $vars Query vars.
     *
     * @return array
     */
    public function register_query_vars( $vars ) {
        $vars[] = self::DOCS_ENDPOINT_SLUG;

        return $vars;
    }

    /**
     * Maybe switch the template to the API documentation view.
     *
     * @param string $template Current template path.
     *
     * @return string
     */
    public function maybe_render_docs_template( $template ) {
        $docs_request = get_query_var( self::DOCS_ENDPOINT_SLUG, null );

        if ( null === $docs_request ) {
            return $template;
        }

        $docs_template = LOTUSLISANS_RESELLER_PATH . 'templates/api-docs.php';

        if ( file_exists( $docs_template ) ) {
            return $docs_template;
        }

        return $template;
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
     * Retrieve the API key for a user, generating one if needed.
     *
     * @param int $user_id User ID.
     *
     * @return string
     */
    public function get_user_api_key( $user_id ) {
        $user_id = absint( $user_id );

        if ( $user_id <= 0 ) {
            return '';
        }

        $existing = get_user_meta( $user_id, self::USER_API_KEY_META, true );

        if ( empty( $existing ) ) {
            return $this->generate_user_api_key( $user_id, true );
        }

        return $existing;
    }

    /**
     * Generate and persist a new API key for a user.
     *
     * @param int  $user_id    User ID.
     * @param bool $force_save Whether to force creation of a new key.
     *
     * @return string
     */
    public function generate_user_api_key( $user_id, $force_save = false ) {
        $user_id = absint( $user_id );

        if ( $user_id <= 0 ) {
            return '';
        }

        if ( ! $force_save ) {
            $existing = get_user_meta( $user_id, self::USER_API_KEY_META, true );
            if ( ! empty( $existing ) ) {
                return $existing;
            }
        }

        $new_key = wp_generate_password( 32, false, false );
        update_user_meta( $user_id, self::USER_API_KEY_META, $new_key );

        return $new_key;
    }

    /**
     * Handle customer-facing actions like API key regeneration.
     */
    public function maybe_handle_account_actions() {
        if ( empty( $_POST['lotuslisans_action'] ) ) {
            return;
        }

        if ( ! is_user_logged_in() ) {
            return;
        }

        $action = sanitize_text_field( wp_unslash( $_POST['lotuslisans_action'] ) );

        if ( 'regenerate_api_key' !== $action ) {
            return;
        }

        $nonce = isset( $_POST['_lotuslisans_nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['_lotuslisans_nonce'] ) ) : '';

        if ( ! wp_verify_nonce( $nonce, 'lotuslisans_regenerate_api_key' ) ) {
            if ( function_exists( 'wc_add_notice' ) ) {
                wc_add_notice( __( 'Güvenlik doğrulaması başarısız oldu.', 'lotuslisans-reseller' ), 'error' );
            }
            wp_safe_redirect( $this->get_account_dashboard_url() );
            exit;
        }

        $user_id = get_current_user_id();
        $this->generate_user_api_key( $user_id, true );

        if ( function_exists( 'wc_add_notice' ) ) {
            wc_add_notice( __( 'API anahtarınız yenilendi.', 'lotuslisans-reseller' ), 'success' );
        }

        wp_safe_redirect( $this->get_account_dashboard_url() );
        exit;
    }

    /**
     * Render API details on the WooCommerce account dashboard.
     */
    public function render_customer_api_panel() {
        if ( ! is_user_logged_in() ) {
            return;
        }

        $user_id = get_current_user_id();
        $api_key = $this->get_user_api_key( $user_id );

        if ( empty( $api_key ) ) {
            $api_key = $this->generate_user_api_key( $user_id, true );
        }

        $api_base = $this->get_api_base_url();
        $docs_url = $this->get_docs_url();
        ?>
        <section class="lotuslisans-customer-api">
            <h2><?php esc_html_e( 'Reseller API Bilgileriniz', 'lotuslisans-reseller' ); ?></h2>
            <p><?php esc_html_e( 'Bu anahtar ile ürünleri sitenize aktarabilir ve siparişleri otomatikleştirebilirsiniz. Anahtarınızı güvende tutunuz.', 'lotuslisans-reseller' ); ?></p>
            <table class="shop_table shop_table_responsive">
                <tbody>
                    <tr>
                        <th><?php esc_html_e( 'API Temel Adresi', 'lotuslisans-reseller' ); ?></th>
                        <td><code><?php echo esc_html( $api_base ); ?></code></td>
                    </tr>
                    <tr>
                        <th><?php esc_html_e( 'API Anahtarı', 'lotuslisans-reseller' ); ?></th>
                        <td><code><?php echo esc_html( $api_key ); ?></code></td>
                    </tr>
                    <tr>
                        <th><?php esc_html_e( 'Dokümantasyon', 'lotuslisans-reseller' ); ?></th>
                        <td><a href="<?php echo esc_url( $docs_url ); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'Reseller API Dokümantasyonu', 'lotuslisans-reseller' ); ?></a></td>
                    </tr>
                </tbody>
            </table>
            <form method="post" class="lotuslisans-api-regenerate">
                <?php wp_nonce_field( 'lotuslisans_regenerate_api_key', '_lotuslisans_nonce' ); ?>
                <input type="hidden" name="lotuslisans_action" value="regenerate_api_key" />
                <button type="submit" class="button" onclick="return confirm('<?php echo esc_js( __( 'Yeni bir anahtar oluşturmak üzeresiniz. Eski anahtar geçersiz hale gelecektir. Devam edilsin mi?', 'lotuslisans-reseller' ) ); ?>');">
                    <?php esc_html_e( 'API Anahtarını Yenile', 'lotuslisans-reseller' ); ?>
                </button>
            </form>
        </section>
        <?php
    }

    /**
     * Retrieve the reseller API documentation URL.
     *
     * @return string
     */
    public function get_docs_url() {
        return home_url( '/' . self::DOCS_ENDPOINT_SLUG . '/' );
    }

    /**
     * Retrieve the base REST API URL for reseller endpoints.
     *
     * @return string
     */
    public function get_api_base_url() {
        return trailingslashit( rest_url( self::REST_NAMESPACE ) );
    }

    /**
     * Get WooCommerce account dashboard URL.
     *
     * @return string
     */
    protected function get_account_dashboard_url() {
        if ( function_exists( 'wc_get_account_endpoint_url' ) ) {
            return wc_get_account_endpoint_url( 'dashboard' );
        }

        return home_url();
    }

    /**
     * Register REST API routes used by external resellers.
     */
    public function register_rest_routes() {
        register_rest_route(
            self::REST_NAMESPACE,
            '/products',
            array(
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => array( $this, 'rest_get_products' ),
                'permission_callback' => array( $this, 'authenticate_rest_request' ),
                'args'                => array(
                    'page' => array(
                        'default'           => 1,
                        'sanitize_callback' => 'absint',
                    ),
                    'per_page' => array(
                        'default'           => 20,
                        'sanitize_callback' => array( $this, 'sanitize_per_page' ),
                    ),
                    'search' => array(
                        'sanitize_callback' => 'sanitize_text_field',
                    ),
                    'stock_status' => array(
                        'sanitize_callback' => 'sanitize_text_field',
                    ),
                    'updated_after' => array(
                        'sanitize_callback' => 'sanitize_text_field',
                    ),
                ),
            )
        );

        register_rest_route(
            self::REST_NAMESPACE,
            '/products/(?P<id>\d+)',
            array(
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => array( $this, 'rest_get_single_product' ),
                'permission_callback' => array( $this, 'authenticate_rest_request' ),
                'args'                => array(
                    'id' => array(
                        'required'          => true,
                        'sanitize_callback' => 'absint',
                    ),
                ),
            )
        );

        register_rest_route(
            self::REST_NAMESPACE,
            '/orders',
            array(
                'methods'             => WP_REST_Server::CREATABLE,
                'callback'            => array( $this, 'rest_create_order' ),
                'permission_callback' => array( $this, 'authenticate_rest_request' ),
                'args'                => array(
                    'product_id' => array(
                        'required'          => true,
                        'sanitize_callback' => 'absint',
                    ),
                    'quantity' => array(
                        'default'           => 1,
                        'sanitize_callback' => 'absint',
                    ),
                    'customer_email' => array(
                        'sanitize_callback' => 'sanitize_email',
                    ),
                    'customer_name' => array(
                        'sanitize_callback' => 'sanitize_text_field',
                    ),
                    'note' => array(
                        'sanitize_callback' => array( $this, 'sanitize_note_field' ),
                    ),
                ),
            )
        );
    }

    /**
     * Sanitize per_page value.
     *
     * @param int|string $value Raw value.
     *
     * @return int
     */
    public function sanitize_per_page( $value ) {
        $per_page = absint( $value );

        if ( $per_page < 1 ) {
            $per_page = 20;
        }

        return min( 100, $per_page );
    }

    /**
     * Sanitize note field content.
     *
     * @param string $value Raw note.
     *
     * @return string
     */
    public function sanitize_note_field( $value ) {
        return sanitize_textarea_field( $value );
    }

    /**
     * Authenticate REST request via API key.
     *
     * @param WP_REST_Request $request REST request.
     *
     * @return bool|WP_Error
     */
    public function authenticate_rest_request( $request ) {
        $api_key = $this->extract_api_key_from_request( $request );

        if ( empty( $api_key ) ) {
            return new WP_Error( 'lotuslisans_missing_key', __( 'API anahtarı gerekli.', 'lotuslisans-reseller' ), array( 'status' => 401 ) );
        }

        $user_id = $this->find_user_by_api_key( $api_key );

        if ( ! $user_id ) {
            return new WP_Error( 'lotuslisans_invalid_key', __( 'API anahtarınız doğrulanamadı.', 'lotuslisans-reseller' ), array( 'status' => 401 ) );
        }

        if ( $request instanceof WP_REST_Request ) {
            $request->set_attribute( 'lotuslisans_user_id', $user_id );
        }

        return true;
    }

    /**
     * Extract API key from request headers or parameters.
     *
     * @param WP_REST_Request $request REST request.
     *
     * @return string
     */
    protected function extract_api_key_from_request( $request ) {
        $header_key = $request->get_header( 'X-API-Key' );
        if ( ! empty( $header_key ) ) {
            return sanitize_text_field( $header_key );
        }

        $param_key = $request->get_param( 'api_key' );
        if ( ! empty( $param_key ) ) {
            return sanitize_text_field( $param_key );
        }

        return '';
    }

    /**
     * Find a user ID by stored API key.
     *
     * @param string $api_key API key.
     *
     * @return int
     */
    protected function find_user_by_api_key( $api_key ) {
        global $wpdb;

        if ( empty( $api_key ) ) {
            return 0;
        }

        $meta_key = self::USER_API_KEY_META;
        $user_id  = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT user_id FROM {$wpdb->usermeta} WHERE meta_key = %s AND meta_value = %s LIMIT 1",
                $meta_key,
                $api_key
            )
        );

        return $user_id ? (int) $user_id : 0;
    }

    /**
     * Return a paginated list of WooCommerce products for API consumers.
     *
     * @param WP_REST_Request $request REST request.
     *
     * @return WP_REST_Response|WP_Error
     */
    public function rest_get_products( $request ) {
        if ( ! class_exists( 'WooCommerce' ) ) {
            return new WP_Error( 'lotuslisans_woocommerce_missing', __( 'WooCommerce etkin değil.', 'lotuslisans-reseller' ), array( 'status' => 503 ) );
        }

        $page     = max( 1, absint( $request->get_param( 'page' ) ) );
        $per_page = $this->sanitize_per_page( $request->get_param( 'per_page' ) );
        $search   = $request->get_param( 'search' );
        $status   = $request->get_param( 'stock_status' );
        $updated  = $request->get_param( 'updated_after' );

        $args = array(
            'status'   => array( 'publish' ),
            'limit'    => $per_page,
            'page'     => $page,
            'paginate' => true,
            'orderby'  => 'date',
            'order'    => 'DESC',
        );

        if ( ! empty( $search ) ) {
            $args['search'] = $search;
        }

        if ( ! empty( $status ) ) {
            $args['stock_status'] = $status;
        }

        if ( ! empty( $updated ) ) {
            $timestamp = strtotime( $updated );
            if ( false !== $timestamp ) {
                $args['date_query'] = array(
                    array(
                        'column' => 'post_modified_gmt',
                        'after'  => gmdate( 'Y-m-d H:i:s', $timestamp ),
                    ),
                );
            }
        }

        $results = wc_get_products( $args );

        $products = array();
        $total    = 0;
        $pages    = 1;

        if ( is_array( $results ) ) {
            $products = $results;
            $total    = count( $results );
        } elseif ( isset( $results->products ) ) {
            $products = $results->products;
            $total    = isset( $results->total ) ? (int) $results->total : count( $products );
            $pages    = isset( $results->max_num_pages ) ? (int) $results->max_num_pages : 1;
        }

        $data = array();

        foreach ( $products as $product ) {
            if ( $product && is_a( $product, 'WC_Product' ) ) {
                $data[] = $this->format_product_for_api( $product );
            }
        }

        $response = array(
            'data' => $data,
            'meta' => array(
                'page'        => $page,
                'per_page'    => $per_page,
                'total'       => $total,
                'total_pages' => $pages,
                'currency'    => get_woocommerce_currency(),
            ),
        );

        return rest_ensure_response( $response );
    }

    /**
     * Format product data for external API consumers.
     *
     * @param WC_Product $product WooCommerce product.
     *
     * @return array
     */
    protected function format_product_for_api( $product ) {
        if ( ! $product || ! is_a( $product, 'WC_Product' ) ) {
            return array();
        }

        $images    = array();
        $image_ids = array();

        $featured_id = $product->get_image_id();
        if ( $featured_id ) {
            $image_ids[] = $featured_id;
        }

        $gallery_ids = $product->get_gallery_image_ids();
        if ( ! empty( $gallery_ids ) ) {
            $image_ids = array_merge( $image_ids, $gallery_ids );
        }

        $image_ids = array_unique( array_filter( array_map( 'absint', $image_ids ) ) );

        foreach ( $image_ids as $attachment_id ) {
            $src = wp_get_attachment_image_url( $attachment_id, 'full' );
            if ( ! $src ) {
                continue;
            }

            $images[] = array(
                'id'  => $attachment_id,
                'src' => esc_url_raw( $src ),
                'alt' => get_post_meta( $attachment_id, '_wp_attachment_image_alt', true ),
            );
        }

        $categories = array();
        $terms      = wp_get_post_terms( $product->get_id(), 'product_cat', array( 'fields' => 'all' ) );
        if ( ! is_wp_error( $terms ) ) {
            foreach ( $terms as $term ) {
                $categories[] = array(
                    'id'   => $term->term_id,
                    'name' => $term->name,
                    'slug' => $term->slug,
                );
            }
        }

        $attributes = array();
        foreach ( $product->get_attributes() as $attribute ) {
            if ( is_object( $attribute ) && method_exists( $attribute, 'get_name' ) ) {
                $attribute_name = $attribute->get_name();
                $attribute_data = array(
                    'name'    => wc_attribute_label( $attribute_name ),
                    'slug'    => $attribute_name,
                    'options' => $attribute->get_options(),
                );
            } else {
                $attribute_data = array(
                    'name'    => '',
                    'slug'    => '',
                    'options' => array(),
                );
            }

            $attributes[] = $attribute_data;
        }

        $data = array(
            'id'                => $product->get_id(),
            'type'              => $product->get_type(),
            'sku'               => $product->get_sku(),
            'name'              => $product->get_name(),
            'slug'              => $product->get_slug(),
            'permalink'         => get_permalink( $product->get_id() ),
            'description'       => wp_kses_post( $product->get_description() ),
            'short_description' => wp_kses_post( $product->get_short_description() ),
            'price'             => $product->get_price(),
            'regular_price'     => $product->get_regular_price(),
            'sale_price'        => $product->get_sale_price(),
            'currency'          => get_woocommerce_currency(),
            'manage_stock'      => $product->get_manage_stock(),
            'stock_status'      => $product->get_stock_status(),
            'stock_quantity'    => $product->get_stock_quantity(),
            'images'            => $images,
            'categories'        => $categories,
            'attributes'        => $attributes,
            'updated_at'        => get_post_modified_time( DATE_ATOM, true, $product->get_id() ),
        );

        if ( $product->is_type( 'variable' ) ) {
            $variations = array();
            foreach ( $product->get_children() as $child_id ) {
                $variation = wc_get_product( $child_id );
                if ( ! $variation || ! is_a( $variation, 'WC_Product' ) ) {
                    continue;
                }

                $variations[] = array(
                    'id'             => $variation->get_id(),
                    'sku'            => $variation->get_sku(),
                    'price'          => $variation->get_price(),
                    'regular_price'  => $variation->get_regular_price(),
                    'sale_price'     => $variation->get_sale_price(),
                    'stock_status'   => $variation->get_stock_status(),
                    'stock_quantity' => $variation->get_stock_quantity(),
                    'attributes'     => $variation->get_attributes(),
                );
            }

            $data['variations'] = $variations;
        }

        return $data;
    }

    /**
     * Retrieve a single product representation.
     *
     * @param WP_REST_Request $request REST request.
     *
     * @return WP_REST_Response|WP_Error
     */
    public function rest_get_single_product( $request ) {
        if ( ! class_exists( 'WooCommerce' ) ) {
            return new WP_Error( 'lotuslisans_woocommerce_missing', __( 'WooCommerce etkin değil.', 'lotuslisans-reseller' ), array( 'status' => 503 ) );
        }

        $product_id = absint( $request->get_param( 'id' ) );
        $product    = wc_get_product( $product_id );

        if ( ! $product || 'publish' !== $product->get_status() ) {
            return new WP_Error( 'lotuslisans_product_not_found', __( 'Ürün bulunamadı.', 'lotuslisans-reseller' ), array( 'status' => 404 ) );
        }

        return rest_ensure_response(
            array(
                'data' => $this->format_product_for_api( $product ),
            )
        );
    }

    /**
     * Create an order on behalf of the API consumer.
     *
     * @param WP_REST_Request $request REST request.
     *
     * @return WP_REST_Response|WP_Error
     */
    public function rest_create_order( $request ) {
        if ( ! class_exists( 'WooCommerce' ) ) {
            return new WP_Error( 'lotuslisans_woocommerce_missing', __( 'WooCommerce etkin değil.', 'lotuslisans-reseller' ), array( 'status' => 503 ) );
        }

        $product_id = absint( $request->get_param( 'product_id' ) );
        $quantity   = max( 1, absint( $request->get_param( 'quantity' ) ) );

        $product = wc_get_product( $product_id );

        if ( ! $product || 'publish' !== $product->get_status() ) {
            return new WP_Error( 'lotuslisans_product_not_found', __( 'Ürün bulunamadı.', 'lotuslisans-reseller' ), array( 'status' => 404 ) );
        }

        $order = wc_create_order();

        if ( is_wp_error( $order ) ) {
            return $order;
        }

        if ( method_exists( $order, 'set_created_via' ) ) {
            $order->set_created_via( 'lotuslisans-reseller-api' );
        }

        try {
            $order->add_product( $product, $quantity );
            $order->calculate_totals();
        } catch ( Exception $exception ) {
            return new WP_Error( 'lotuslisans_order_error', $exception->getMessage(), array( 'status' => 400 ) );
        }

        $customer_id = $request->get_attribute( 'lotuslisans_user_id' );
        if ( $customer_id && method_exists( $order, 'set_customer_id' ) ) {
            $order->set_customer_id( (int) $customer_id );
        }

        $customer_email = $request->get_param( 'customer_email' );
        if ( ! empty( $customer_email ) && method_exists( $order, 'set_billing_email' ) ) {
            $order->set_billing_email( sanitize_email( $customer_email ) );
        }

        $customer_name = $request->get_param( 'customer_name' );
        if ( ! empty( $customer_name ) ) {
            $name_parts = explode( ' ', $customer_name, 2 );
            $first_name = isset( $name_parts[0] ) ? sanitize_text_field( $name_parts[0] ) : '';
            $last_name  = isset( $name_parts[1] ) ? sanitize_text_field( $name_parts[1] ) : '';

            if ( method_exists( $order, 'set_billing_first_name' ) ) {
                $order->set_billing_first_name( $first_name );
            }

            if ( method_exists( $order, 'set_billing_last_name' ) ) {
                $order->set_billing_last_name( $last_name );
            }
        }

        $note = $request->get_param( 'note' );
        if ( ! empty( $note ) && method_exists( $order, 'add_order_note' ) ) {
            $order->add_order_note( sanitize_textarea_field( $note ), false, false );
        }

        $order->update_meta_data( '_lotuslisans_reseller_api_consumer', $request->get_attribute( 'lotuslisans_user_id' ) );
        $order->save();

        return rest_ensure_response(
            array(
                'data' => array(
                    'order_id' => $order->get_id(),
                    'status'   => $order->get_status(),
                    'total'    => $order->get_total(),
                    'currency' => $order->get_currency(),
                ),
            )
        );
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
