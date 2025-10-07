<?php
/**
 * Admin functionality.
 *
 * @package ResellersAPI
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Resellers_API_Admin {

    /**
     * Plugin instance.
     *
     * @var Resellers_API_Plugin
     */
    protected $plugin;

    /**
     * API client.
     *
     * @var Resellers_API_Client
     */
    protected $api_client;

    /**
     * Constructor.
     *
     * @param Resellers_API_Plugin $plugin    Plugin instance.
     * @param Resellers_API_Client      $api_client API client.
     */
    public function __construct( Resellers_API_Plugin $plugin, Resellers_API_Client $api_client ) {
        $this->plugin     = $plugin;
        $this->api_client = $api_client;

        add_action( 'admin_menu', array( $this, 'register_menu' ) );
        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
        add_action( 'wp_ajax_resellers_api_test_connection', array( $this, 'ajax_test_connection' ) );
        add_action( 'admin_post_resellers_api_import_products', array( $this, 'handle_import_products' ) );
    }

    /**
     * Register plugin admin menu.
     */
    public function register_menu() {
        add_menu_page(
            __( 'Resellers API Ayarları', 'resellers-api' ),
            __( 'Resellers API', 'resellers-api' ),
            'manage_options',
            'resellers-api',
            array( $this, 'render_settings_page' ),
            'dashicons-rest-api',
            58
        );
    }

    /**
     * Enqueue admin assets.
     *
     * @param string $hook Current page hook.
     */
    public function enqueue_assets( $hook ) {
        if ( 'toplevel_page_resellers-api' !== $hook ) {
            return;
        }

        wp_enqueue_style(
            'resellers-api-admin',
            RESELLERS_API_URL . 'assets/css/admin.css',
            array(),
            RESELLERS_API_VERSION
        );

        wp_enqueue_script(
            'resellers-api-admin',
            RESELLERS_API_URL . 'assets/js/admin.js',
            array( 'jquery' ),
            RESELLERS_API_VERSION,
            true
        );

        wp_localize_script(
            'resellers-api-admin',
            'resellersApiAdmin',
            array(
                'ajaxUrl'  => admin_url( 'admin-ajax.php' ),
                'nonce'    => wp_create_nonce( 'resellers_api_test_connection' ),
                'testing'  => __( 'Bağlantı test ediliyor...', 'resellers-api' ),
                'error'    => __( 'Beklenmeyen bir hata oluştu.', 'resellers-api' ),
                'success'  => __( 'Bağlantı başarılı.', 'resellers-api' ),
            )
        );
    }

    /**
     * Render settings page.
     */
    public function render_settings_page() {
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }

        $options  = $this->plugin->get_options();
        $api_key  = isset( $options['api_key'] ) ? $options['api_key'] : '';
        $balance  = $this->plugin->get_cached_balance();
        $snapshot = $this->plugin->get_product_snapshot();
        ?>
        <div class="wrap resellers-api">
            <h1><?php esc_html_e( 'Resellers API Ayarları', 'resellers-api' ); ?></h1>
            <form method="post" action="options.php">
                <?php
                settings_fields( 'resellers_api_settings' );
                ?>
                <table class="form-table" role="presentation">
                    <tbody>
                        <tr>
                            <th scope="row">
                                <label for="resellers-api-api-key"><?php esc_html_e( 'API Anahtarı', 'resellers-api' ); ?></label>
                            </th>
                            <td>
                                <input type="text" class="regular-text" id="resellers-api-api-key" name="<?php echo esc_attr( Resellers_API_Plugin::OPTION_KEY ); ?>[api_key]" value="<?php echo esc_attr( $api_key ); ?>" autocomplete="off" />
                                <p class="description"><?php esc_html_e( 'Mağaza panelinizden aldığınız API anahtarını giriniz.', 'resellers-api' ); ?></p>
                            </td>
                        </tr>
                    </tbody>
                </table>
                <?php submit_button( __( 'Kaydet', 'resellers-api' ) ); ?>
            </form>

            <hr />

            <h2><?php esc_html_e( 'Bağlantı Yönetimi', 'resellers-api' ); ?></h2>
            <p>
                <button type="button" class="button button-secondary" id="resellers-api-test-connection">
                    <?php esc_html_e( 'Bağlantıyı Test Et', 'resellers-api' ); ?>
                </button>
                <span class="resellers-api-test-result" aria-live="polite"></span>
            </p>

            <?php if ( $balance && isset( $balance['credit'] ) ) : ?>
                <div class="notice notice-success inline">
                    <p>
                        <?php
                        printf(
                            /* translators: %s: balance amount */
                            esc_html__( 'Güncel bakiyeniz: %s', 'resellers-api' ),
                            esc_html( $balance['credit'] )
                        );
                        ?>
                    </p>
                </div>
            <?php endif; ?>

            <hr />

            <h2><?php esc_html_e( 'Ürün Yönetimi', 'resellers-api' ); ?></h2>
            <?php if ( ! class_exists( 'WooCommerce' ) ) : ?>
                <div class="notice notice-error inline"><p><?php esc_html_e( 'WooCommerce eklentisi etkin değil. Lütfen WooCommerce kurulumu olmadan ürün içe aktarımı yapmayınız.', 'resellers-api' ); ?></p></div>
            <?php endif; ?>
            <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
                <?php wp_nonce_field( 'resellers_api_import_products' ); ?>
                <input type="hidden" name="action" value="resellers_api_import_products" />
                <?php submit_button( __( 'Tüm Ürünleri WooCommerce Taslaklarına Aktar', 'resellers-api' ), 'primary', 'resellers-api-import-products' ); ?>
            </form>

            <?php if ( ! empty( $snapshot ) ) : ?>
                <h3><?php esc_html_e( 'Son Senkronizasyon Özeti', 'resellers-api' ); ?></h3>
                <p><?php esc_html_e( 'Aşağıda son alınan ürün verilerinden bazı bilgiler yer alıyor.', 'resellers-api' ); ?></p>
                <ul class="resellers-api-sync-summary">
                    <li><?php printf( esc_html__( 'Ürün sayısı: %d', 'resellers-api' ), count( $snapshot ) ); ?></li>
                </ul>
            <?php endif; ?>
        </div>
        <?php
    }

    /**
     * AJAX callback for testing the API connection.
     */
    public function ajax_test_connection() {
        check_ajax_referer( 'resellers_api_test_connection', 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( __( 'Yetkiniz bulunmuyor.', 'resellers-api' ) );
        }

        $response = $this->api_client->get_user();

        if ( is_wp_error( $response ) ) {
            wp_send_json_error( $response->get_error_message() );
        }

        $message = __( 'Bağlantı başarılı.', 'resellers-api' );

        if ( isset( $response['data']['credit'] ) ) {
            $message = sprintf(
                /* translators: %s: credit balance */
                __( 'Bağlantı başarılı. Güncel bakiyeniz: %s', 'resellers-api' ),
                esc_html( $response['data']['credit'] )
            );
        }

        wp_send_json_success( $message );
    }

    /**
     * Handle product import submission.
     */
    public function handle_import_products() {
        if ( ! current_user_can( 'manage_woocommerce' ) && ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'Bu işlem için izniniz yok.', 'resellers-api' ) );
        }

        check_admin_referer( 'resellers_api_import_products' );

        $products_response = $this->api_client->get_products();

        if ( is_wp_error( $products_response ) ) {
            $this->plugin->buffer_notice( $products_response->get_error_message(), 'error' );
            wp_safe_redirect( wp_get_referer() ? wp_get_referer() : admin_url( 'admin.php?page=resellers-api' ) );
            exit;
        }

        $products = isset( $products_response['data'] ) && is_array( $products_response['data'] ) ? $products_response['data'] : array();

        $result = $this->import_products( $products );

        $this->handle_product_change_notifications( $products );

        if ( $result['created'] > 0 || $result['updated'] > 0 ) {
            $this->plugin->buffer_notice(
                sprintf(
                    /* translators: 1: created count 2: updated count */
                    esc_html__( '%1$d yeni ürün taslak olarak oluşturuldu, %2$d ürün güncellendi.', 'resellers-api' ),
                    $result['created'],
                    $result['updated']
                ),
                'success'
            );
        } else {
            $this->plugin->buffer_notice( esc_html__( 'İçe aktarılacak yeni ürün bulunamadı.', 'resellers-api' ), 'info' );
        }

        wp_safe_redirect( wp_get_referer() ? wp_get_referer() : admin_url( 'admin.php?page=resellers-api' ) );
        exit;
    }

    /**
     * Import products into WooCommerce.
     *
     * @param array $products API product list.
     *
     * @return array
     */
    protected function import_products( array $products ) {
        $results = array(
            'created' => 0,
            'updated' => 0,
        );

        if ( empty( $products ) ) {
            return $results;
        }

        if ( ! class_exists( 'WooCommerce' ) ) {
            $this->plugin->buffer_notice( esc_html__( 'WooCommerce bulunamadı. Ürünler içe aktarılamadı.', 'resellers-api' ), 'error' );
            return $results;
        }

        foreach ( $products as $product ) {
            if ( empty( $product['id'] ) ) {
                continue;
            }

            $existing_id = $this->find_product_by_remote_id( $product['id'] );

            if ( $existing_id ) {
                $updated = $this->update_existing_product( $existing_id, $product );
                if ( $updated ) {
                    $results['updated']++;
                }
                continue;
            }

            $created = $this->create_product( $product );
            if ( $created ) {
                $results['created']++;
            }
        }

        return $results;
    }

    /**
     * Find a local product by remote ID.
     *
     * @param int $remote_id Remote product ID.
     *
     * @return int|false
     */
    protected function find_product_by_remote_id( $remote_id ) {
        $query = new WP_Query(
            array(
                'post_type'      => 'product',
                'posts_per_page' => 1,
                'post_status'    => 'any',
                'fields'         => 'ids',
                'meta_key'       => '_resellers_api_product_id',
                'meta_value'     => $remote_id,
            )
        );

        if ( empty( $query->posts ) ) {
            return false;
        }

        return (int) $query->posts[0];
    }

    /**
     * Create WooCommerce product draft.
     *
     * @param array $product Product data.
     *
     * @return bool
     */
    protected function create_product( array $product ) {
        $post_id = wp_insert_post(
            array(
                'post_title'   => isset( $product['title'] ) ? sanitize_text_field( $product['title'] ) : __( 'Resellers API Ürünü', 'resellers-api' ),
                'post_content' => isset( $product['content'] ) ? wp_kses_post( $product['content'] ) : '',
                'post_status'  => 'draft',
                'post_type'    => 'product',
            ),
            true
        );

        if ( is_wp_error( $post_id ) ) {
            $this->plugin->buffer_notice( $post_id->get_error_message(), 'error' );
            return false;
        }

        $this->update_product_meta( $post_id, $product );

        return true;
    }

    /**
     * Update an existing product.
     *
     * @param int   $post_id Product ID.
     * @param array $product Product data.
     *
     * @return bool
     */
    protected function update_existing_product( $post_id, array $product ) {
        $updated_post = array(
            'ID'           => $post_id,
            'post_title'   => isset( $product['title'] ) ? sanitize_text_field( $product['title'] ) : get_the_title( $post_id ),
            'post_content' => isset( $product['content'] ) ? wp_kses_post( $product['content'] ) : get_post_field( 'post_content', $post_id ),
        );

        $result = wp_update_post( $updated_post, true );

        if ( is_wp_error( $result ) ) {
            $this->plugin->buffer_notice( $result->get_error_message(), 'error' );
            return false;
        }

        $this->update_product_meta( $post_id, $product );

        return true;
    }

    /**
     * Update WooCommerce product meta information.
     *
     * @param int   $post_id Product ID.
     * @param array $product Product data.
     */
    protected function update_product_meta( $post_id, array $product ) {
        if ( isset( $product['amount'] ) ) {
            $price = function_exists( 'wc_format_decimal' ) ? wc_format_decimal( $product['amount'] ) : sanitize_text_field( $product['amount'] );
            update_post_meta( $post_id, '_regular_price', $price );
            update_post_meta( $post_id, '_price', $price );
        }

        $available = isset( $product['available'] ) ? (bool) $product['available'] : false;
        update_post_meta( $post_id, '_stock_status', $available ? 'instock' : 'onbackorder' );
        update_post_meta( $post_id, '_manage_stock', 'no' );
        update_post_meta( $post_id, '_resellers_api_product_id', (int) $product['id'] );
        update_post_meta( $post_id, '_resellers_api_available', $available ? 'yes' : 'no' );
        update_post_meta( $post_id, '_virtual', 'yes' );
        update_post_meta( $post_id, '_downloadable', 'no' );
    }

    /**
     * Handle notifications for product changes.
     *
     * @param array $products Latest product payload.
     */
    protected function handle_product_change_notifications( array $products ) {
        $previous = $this->plugin->get_product_snapshot();
        $changes  = array();

        $indexed_previous = array();
        foreach ( $previous as $product ) {
            if ( isset( $product['id'] ) ) {
                $indexed_previous[ $product['id'] ] = $product;
            }
        }

        foreach ( $products as $product ) {
            if ( ! isset( $product['id'] ) ) {
                continue;
            }

            $id = (int) $product['id'];

            if ( ! isset( $indexed_previous[ $id ] ) ) {
                $changes[] = sprintf(
                    /* translators: %s: product title */
                    esc_html__( 'Yeni ürün bulundu: %s', 'resellers-api' ),
                    isset( $product['title'] ) ? esc_html( $product['title'] ) : $id
                );
                continue;
            }

            $previous_product = $indexed_previous[ $id ];

            if ( isset( $previous_product['amount'], $product['amount'] ) && $previous_product['amount'] !== $product['amount'] ) {
                $changes[] = sprintf(
                    /* translators: 1: product title 2: old price 3: new price */
                    esc_html__( '%1$s fiyatı güncellendi: %2$s → %3$s', 'resellers-api' ),
                    isset( $product['title'] ) ? esc_html( $product['title'] ) : $id,
                    esc_html( $previous_product['amount'] ),
                    esc_html( $product['amount'] )
                );
            }
        }

        if ( ! empty( $changes ) ) {
            foreach ( $changes as $change ) {
                $this->plugin->buffer_notice( $change, 'warning' );
            }
        }

        $this->plugin->save_product_snapshot( $products );
    }
}
