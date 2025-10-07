<?php
/**
 * Admin functionality.
 *
 * @package LotusLisansReseller
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class LotusLisans_Admin {

    /**
     * Plugin instance.
     *
     * @var LotusLisans_Reseller_Plugin
     */
    protected $plugin;

    /**
     * API client.
     *
     * @var LotusLisans_API_Client
     */
    protected $api_client;

    /**
     * Constructor.
     *
     * @param LotusLisans_Reseller_Plugin $plugin    Plugin instance.
     * @param LotusLisans_API_Client      $api_client API client.
     */
    public function __construct( LotusLisans_Reseller_Plugin $plugin, LotusLisans_API_Client $api_client ) {
        $this->plugin     = $plugin;
        $this->api_client = $api_client;

        add_action( 'admin_menu', array( $this, 'register_menu' ) );
        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
        add_action( 'wp_ajax_lotuslisans_test_connection', array( $this, 'ajax_test_connection' ) );
        add_action( 'wp_ajax_lotuslisans_test_provider', array( $this, 'ajax_test_provider' ) );
        add_action( 'admin_post_lotuslisans_import_products', array( $this, 'handle_import_products' ) );
    }

    /**
     * Register plugin admin menu.
     */
    public function register_menu() {
        $parent_slug = 'lotuslisans-reseller';

        add_menu_page(
            __( 'LotusLisans API', 'lotuslisans-reseller' ),
            __( 'Reseller - API', 'lotuslisans-reseller' ),
            'manage_options',
            $parent_slug,
            array( $this, 'render_dashboard_page' ),
            'dashicons-rest-api',
            58
        );

        add_submenu_page(
            $parent_slug,
            __( 'Kontrol Paneli', 'lotuslisans-reseller' ),
            __( 'Kontrol Paneli', 'lotuslisans-reseller' ),
            'manage_options',
            $parent_slug,
            array( $this, 'render_dashboard_page' )
        );

        add_submenu_page(
            $parent_slug,
            __( 'LotusLisans Ayarları', 'lotuslisans-reseller' ),
            __( 'Lotus', 'lotuslisans-reseller' ),
            'manage_options',
            'lotuslisans-reseller-lotus',
            array( $this, 'render_lotus_settings_page' )
        );

        add_submenu_page(
            $parent_slug,
            __( 'Netgsm Entegrasyonu', 'lotuslisans-reseller' ),
            __( 'Netgsm', 'lotuslisans-reseller' ),
            'manage_options',
            'lotuslisans-reseller-netgsm',
            array( $this, 'render_netgsm_page' )
        );

        add_submenu_page(
            $parent_slug,
            __( 'Türkpin Entegrasyonu', 'lotuslisans-reseller' ),
            __( 'Türkpin', 'lotuslisans-reseller' ),
            'manage_options',
            'lotuslisans-reseller-turkpin',
            array( $this, 'render_turkpin_page' )
        );

        add_submenu_page(
            $parent_slug,
            __( 'Pinabi Entegrasyonu', 'lotuslisans-reseller' ),
            __( 'Pinabi', 'lotuslisans-reseller' ),
            'manage_options',
            'lotuslisans-reseller-pinabi',
            array( $this, 'render_pinabi_page' )
        );
    }

    /**
     * Enqueue admin assets.
     *
     * @param string $hook Current page hook.
     */
    public function enqueue_assets( $hook ) {
        if ( false === strpos( $hook, 'lotuslisans-reseller' ) ) {
            return;
        }

        wp_enqueue_style(
            'lotuslisans-reseller-admin',
            LOTUSLISANS_RESELLER_URL . 'assets/css/admin.css',
            array(),
            LOTUSLISANS_RESELLER_VERSION
        );

        wp_enqueue_script(
            'lotuslisans-reseller-admin',
            LOTUSLISANS_RESELLER_URL . 'assets/js/admin.js',
            array( 'jquery' ),
            LOTUSLISANS_RESELLER_VERSION,
            true
        );

        wp_localize_script(
            'lotuslisans-reseller-admin',
            'lotuslisansReseller',
            array(
                'ajaxUrl'  => admin_url( 'admin-ajax.php' ),
                'nonce'    => wp_create_nonce( 'lotuslisans_test_connection' ),
                'testing'  => __( 'Bağlantı test ediliyor...', 'lotuslisans-reseller' ),
                'error'    => __( 'Beklenmeyen bir hata oluştu.', 'lotuslisans-reseller' ),
                'success'  => __( 'Bağlantı başarılı.', 'lotuslisans-reseller' ),
                'providers' => array(
                    LotusLisans_Reseller_Plugin::PROVIDER_LOTUS   => __( 'LotusLisans', 'lotuslisans-reseller' ),
                    LotusLisans_Reseller_Plugin::PROVIDER_NETGSM  => __( 'Netgsm', 'lotuslisans-reseller' ),
                    LotusLisans_Reseller_Plugin::PROVIDER_TURKPIN => __( 'Türkpin', 'lotuslisans-reseller' ),
                    LotusLisans_Reseller_Plugin::PROVIDER_PINABI  => __( 'Pinabi', 'lotuslisans-reseller' ),
                ),
            )
        );
    }

    /**
     * Render the integration dashboard page.
     */
    public function render_dashboard_page() {
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }

        $lotus_options   = $this->plugin->get_provider_options( LotusLisans_Reseller_Plugin::PROVIDER_LOTUS );
        $netgsm_options  = $this->plugin->get_provider_options( LotusLisans_Reseller_Plugin::PROVIDER_NETGSM );
        $turkpin_options = $this->plugin->get_provider_options( LotusLisans_Reseller_Plugin::PROVIDER_TURKPIN );
        $pinabi_options  = $this->plugin->get_provider_options( LotusLisans_Reseller_Plugin::PROVIDER_PINABI );
        $balance         = $this->plugin->get_cached_balance();
        $snapshot        = $this->plugin->get_product_snapshot();

        $providers = array(
            array(
                'slug'        => LotusLisans_Reseller_Plugin::PROVIDER_LOTUS,
                'title'       => __( 'LotusLisans', 'lotuslisans-reseller' ),
                'description' => __( 'Lotus ürün kataloğunu WooCommerce’e aktarın, bakiye durumunuzu takip edin ve dijital ürün satışlarınızı otomatikleştirin.', 'lotuslisans-reseller' ),
                'configured'  => $this->is_provider_configured( $lotus_options, array( 'api_key' ) ),
                'link'        => admin_url( 'admin.php?page=lotuslisans-reseller-lotus' ),
                'cta'         => __( 'Lotus Ayarlarını Aç', 'lotuslisans-reseller' ),
            ),
            array(
                'slug'        => LotusLisans_Reseller_Plugin::PROVIDER_NETGSM,
                'title'       => __( 'Netgsm', 'lotuslisans-reseller' ),
                'description' => __( 'Sipariş ve lisans bildirimlerinizi Netgsm SMS servisiniz üzerinden göndermek için kimlik doğrulamasını yapılandırın.', 'lotuslisans-reseller' ),
                'configured'  => $this->is_provider_configured( $netgsm_options, array( 'usercode', 'password' ) ),
                'link'        => admin_url( 'admin.php?page=lotuslisans-reseller-netgsm' ),
                'cta'         => __( 'Netgsm Ayarlarına Git', 'lotuslisans-reseller' ),
            ),
            array(
                'slug'        => LotusLisans_Reseller_Plugin::PROVIDER_TURKPIN,
                'title'       => __( 'Türkpin', 'lotuslisans-reseller' ),
                'description' => __( 'Türkpin stoklarınızı, fiyatlarınızı ve sipariş akışınızı yönetmek için dealer kimlik bilgilerinizi kaydedin.', 'lotuslisans-reseller' ),
                'configured'  => $this->is_provider_configured( $turkpin_options, array( 'dealer_code', 'api_key', 'secret_key' ) ),
                'link'        => admin_url( 'admin.php?page=lotuslisans-reseller-turkpin' ),
                'cta'         => __( 'Türkpin Ayarlarına Git', 'lotuslisans-reseller' ),
            ),
            array(
                'slug'        => LotusLisans_Reseller_Plugin::PROVIDER_PINABI,
                'title'       => __( 'Pinabi', 'lotuslisans-reseller' ),
                'description' => __( 'Pinabi API bilgilerinizi girerek otomatik ürün teslimatını ve bakiye kontrollerini etkinleştirin.', 'lotuslisans-reseller' ),
                'configured'  => $this->is_provider_configured( $pinabi_options, array( 'username', 'password', 'api_key' ) ),
                'link'        => admin_url( 'admin.php?page=lotuslisans-reseller-pinabi' ),
                'cta'         => __( 'Pinabi Ayarlarına Git', 'lotuslisans-reseller' ),
            ),
        );

        $configured_count = 0;
        foreach ( $providers as $provider ) {
            if ( $provider['configured'] ) {
                $configured_count++;
            }
        }

        $snapshot_count = is_array( $snapshot ) ? count( $snapshot ) : 0;
        ?>
        <div class="wrap lotuslisans-reseller lotuslisans-dashboard">
            <h1><?php esc_html_e( 'Reseller API Kontrol Paneli', 'lotuslisans-reseller' ); ?></h1>
            <p class="description"><?php esc_html_e( 'LotusLisans ve ek tedarikçileriniz için tanımladığınız tüm entegrasyonları tek bir noktadan yönetin.', 'lotuslisans-reseller' ); ?></p>

            <?php if ( $balance && isset( $balance['credit'] ) ) : ?>
                <div class="notice notice-success inline">
                    <p>
                        <?php
                        printf(
                            /* translators: %s: balance amount */
                            esc_html__( 'LotusLisans bakiyesi: %s', 'lotuslisans-reseller' ),
                            esc_html( $balance['credit'] )
                        );
                        ?>
                    </p>
                </div>
            <?php elseif ( ! $this->is_provider_configured( $lotus_options, array( 'api_key' ) ) ) : ?>
                <div class="notice notice-warning inline">
                    <p><?php esc_html_e( 'LotusLisans API anahtarınızı tanımlayarak ürün senkronizasyonu ve bakiye kontrollerini etkinleştirebilirsiniz.', 'lotuslisans-reseller' ); ?></p>
                </div>
            <?php endif; ?>

            <div class="lotuslisans-dashboard-metrics">
                <div class="lotuslisans-dashboard-card">
                    <h3><?php esc_html_e( 'Aktif Entegrasyonlar', 'lotuslisans-reseller' ); ?></h3>
                    <p class="lotuslisans-dashboard-value"><?php echo esc_html( $configured_count . ' / ' . count( $providers ) ); ?></p>
                    <p><?php esc_html_e( 'En az bir kimlik bilgisi tanımlanmış sağlayıcıların sayısı.', 'lotuslisans-reseller' ); ?></p>
                </div>
                <div class="lotuslisans-dashboard-card">
                    <h3><?php esc_html_e( 'Saklanan Lotus Ürünleri', 'lotuslisans-reseller' ); ?></h3>
                    <p class="lotuslisans-dashboard-value"><?php echo esc_html( number_format_i18n( $snapshot_count ) ); ?></p>
                    <p><?php esc_html_e( 'Son senkronizasyonda alınan ürün taslaklarının sayısı.', 'lotuslisans-reseller' ); ?></p>
                </div>
            </div>

            <div class="lotuslisans-provider-grid">
                <?php foreach ( $providers as $provider ) : ?>
                    <div class="lotuslisans-provider-card">
                        <h2><?php echo esc_html( $provider['title'] ); ?></h2>
                        <p class="lotuslisans-provider-status">
                            <span class="lotuslisans-status-dot <?php echo $provider['configured'] ? 'status-connected' : 'status-pending'; ?>"></span>
                            <?php echo $provider['configured'] ? esc_html__( 'Hazır', 'lotuslisans-reseller' ) : esc_html__( 'Eksik Ayar', 'lotuslisans-reseller' ); ?>
                        </p>
                        <p><?php echo esc_html( $provider['description'] ); ?></p>
                        <p>
                            <a class="button button-primary" href="<?php echo esc_url( $provider['link'] ); ?>">
                                <?php echo esc_html( $provider['cta'] ); ?>
                            </a>
                        </p>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php
    }

    /**
     * Render LotusLisans provider settings.
     */
    public function render_lotus_settings_page() {
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }

        $options  = $this->plugin->get_provider_options( LotusLisans_Reseller_Plugin::PROVIDER_LOTUS );
        $api_key  = isset( $options['api_key'] ) ? $options['api_key'] : '';
        $balance  = $this->plugin->get_cached_balance();
        $snapshot = $this->plugin->get_product_snapshot();
        ?>
        <div class="wrap lotuslisans-reseller lotuslisans-provider-page lotuslisans-provider-lotus">
            <h1><?php esc_html_e( 'LotusLisans Entegrasyonu', 'lotuslisans-reseller' ); ?></h1>
            <p class="description"><?php esc_html_e( 'LotusLisans Reseller API anahtarınızı kaydedin, bakiyenizi izleyin ve ürün kataloğunu WooCommerce taslaklarına aktarın.', 'lotuslisans-reseller' ); ?></p>

            <form method="post" action="options.php" class="lotuslisans-provider-form">
                <?php settings_fields( 'lotuslisans_reseller_settings' ); ?>
                <table class="form-table" role="presentation">
                    <tbody>
                        <tr>
                            <th scope="row">
                                <label for="lotuslisans-reseller-api-key"><?php esc_html_e( 'API Anahtarı', 'lotuslisans-reseller' ); ?></label>
                            </th>
                            <td>
                                <input type="text" class="regular-text" id="lotuslisans-reseller-api-key" name="<?php echo esc_attr( LotusLisans_Reseller_Plugin::OPTION_KEY ); ?>[<?php echo esc_attr( LotusLisans_Reseller_Plugin::PROVIDER_LOTUS ); ?>][api_key]" value="<?php echo esc_attr( $api_key ); ?>" autocomplete="off" />
                                <p class="description"><?php esc_html_e( 'LotusLisans panelinizdeki Reseller API anahtarını buraya girin.', 'lotuslisans-reseller' ); ?></p>
                            </td>
                        </tr>
                    </tbody>
                </table>
                <?php submit_button( __( 'Ayarları Kaydet', 'lotuslisans-reseller' ) ); ?>
            </form>

            <h2><?php esc_html_e( 'Bağlantı Yönetimi', 'lotuslisans-reseller' ); ?></h2>
            <p><?php esc_html_e( 'API anahtarınızı kaydettikten sonra bağlantıyı test ederek hesabınıza dair bakiye bilgisini doğrulayabilirsiniz.', 'lotuslisans-reseller' ); ?></p>
            <?php $this->render_test_button( LotusLisans_Reseller_Plugin::PROVIDER_LOTUS ); ?>

            <?php if ( $balance && isset( $balance['credit'] ) ) : ?>
                <div class="notice notice-success inline">
                    <p>
                        <?php
                        printf(
                            /* translators: %s: balance amount */
                            esc_html__( 'Güncel LotusLisans bakiyeniz: %s', 'lotuslisans-reseller' ),
                            esc_html( $balance['credit'] )
                        );
                        ?>
                    </p>
                </div>
            <?php endif; ?>

            <h2><?php esc_html_e( 'Ürün Yönetimi', 'lotuslisans-reseller' ); ?></h2>
            <p><?php esc_html_e( 'Tek tıklama ile tüm LotusLisans ürünlerini WooCommerce taslakları olarak içe aktarabilir, fiyat ve stok güncellemelerini düzenli olarak yenileyebilirsiniz.', 'lotuslisans-reseller' ); ?></p>
            <?php if ( ! class_exists( 'WooCommerce' ) ) : ?>
                <div class="notice notice-error inline"><p><?php esc_html_e( 'WooCommerce eklentisi etkin değil. Lütfen WooCommerce kurulumu olmadan ürün içe aktarımı yapmayınız.', 'lotuslisans-reseller' ); ?></p></div>
            <?php endif; ?>
            <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
                <?php wp_nonce_field( 'lotuslisans_import_products' ); ?>
                <input type="hidden" name="action" value="lotuslisans_import_products" />
                <?php submit_button( __( 'Tüm Ürünleri WooCommerce Taslaklarına Aktar', 'lotuslisans-reseller' ), 'primary', 'lotuslisans-import-products' ); ?>
            </form>

            <?php if ( ! empty( $snapshot ) ) : ?>
                <h3><?php esc_html_e( 'Son Senkronizasyon Özeti', 'lotuslisans-reseller' ); ?></h3>
                <p><?php esc_html_e( 'Aşağıda son alınan ürün verilerinden bazı bilgiler yer alıyor.', 'lotuslisans-reseller' ); ?></p>
                <ul class="lotuslisans-sync-summary">
                    <li><?php printf( esc_html__( 'Ürün sayısı: %d', 'lotuslisans-reseller' ), count( $snapshot ) ); ?></li>
                </ul>
            <?php endif; ?>
        </div>
        <?php
    }

    /**
     * Backward compatibility wrapper.
     */
    public function render_settings_page() {
        $this->render_lotus_settings_page();
    }

    /**
     * Render Netgsm integration page.
     */
    public function render_netgsm_page() {
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }

        $options  = $this->plugin->get_provider_options( LotusLisans_Reseller_Plugin::PROVIDER_NETGSM );
        $base_url = isset( $options['base_url'] ) ? $options['base_url'] : '';
        $usercode = isset( $options['usercode'] ) ? $options['usercode'] : '';
        $password = isset( $options['password'] ) ? $options['password'] : '';
        $header   = isset( $options['header'] ) ? $options['header'] : '';
        ?>
        <div class="wrap lotuslisans-reseller lotuslisans-provider-page lotuslisans-provider-netgsm">
            <h1><?php esc_html_e( 'Netgsm Entegrasyonu', 'lotuslisans-reseller' ); ?></h1>
            <p class="description"><?php esc_html_e( 'Sipariş bildirimlerinizi SMS olarak göndermek için Netgsm hesabınızın API bilgilerini tanımlayın.', 'lotuslisans-reseller' ); ?></p>

            <form method="post" action="options.php" class="lotuslisans-provider-form">
                <?php settings_fields( 'lotuslisans_reseller_settings' ); ?>
                <table class="form-table" role="presentation">
                    <tbody>
                        <tr>
                            <th scope="row"><label for="lotuslisans-netgsm-base-url"><?php esc_html_e( 'API Adresi', 'lotuslisans-reseller' ); ?></label></th>
                            <td>
                                <input type="url" class="regular-text" id="lotuslisans-netgsm-base-url" name="<?php echo esc_attr( LotusLisans_Reseller_Plugin::OPTION_KEY ); ?>[<?php echo esc_attr( LotusLisans_Reseller_Plugin::PROVIDER_NETGSM ); ?>][base_url]" value="<?php echo esc_attr( $base_url ); ?>" placeholder="https://api.netgsm.com.tr" />
                                <p class="description"><?php esc_html_e( 'Varsayılan adres https://api.netgsm.com.tr olarak bırakılabilir.', 'lotuslisans-reseller' ); ?></p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="lotuslisans-netgsm-usercode"><?php esc_html_e( 'Kullanıcı Kodu', 'lotuslisans-reseller' ); ?></label></th>
                            <td>
                                <input type="text" class="regular-text" id="lotuslisans-netgsm-usercode" name="<?php echo esc_attr( LotusLisans_Reseller_Plugin::OPTION_KEY ); ?>[<?php echo esc_attr( LotusLisans_Reseller_Plugin::PROVIDER_NETGSM ); ?>][usercode]" value="<?php echo esc_attr( $usercode ); ?>" autocomplete="off" />
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="lotuslisans-netgsm-password"><?php esc_html_e( 'Parola', 'lotuslisans-reseller' ); ?></label></th>
                            <td>
                                <input type="password" class="regular-text" id="lotuslisans-netgsm-password" name="<?php echo esc_attr( LotusLisans_Reseller_Plugin::OPTION_KEY ); ?>[<?php echo esc_attr( LotusLisans_Reseller_Plugin::PROVIDER_NETGSM ); ?>][password]" value="<?php echo esc_attr( $password ); ?>" autocomplete="off" />
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="lotuslisans-netgsm-header"><?php esc_html_e( 'SMS Başlığı', 'lotuslisans-reseller' ); ?></label></th>
                            <td>
                                <input type="text" class="regular-text" id="lotuslisans-netgsm-header" name="<?php echo esc_attr( LotusLisans_Reseller_Plugin::OPTION_KEY ); ?>[<?php echo esc_attr( LotusLisans_Reseller_Plugin::PROVIDER_NETGSM ); ?>][header]" value="<?php echo esc_attr( $header ); ?>" />
                                <p class="description"><?php esc_html_e( 'Netgsm panelinizde tanımlı olan onaylı başlık kullanılmalıdır.', 'lotuslisans-reseller' ); ?></p>
                            </td>
                        </tr>
                    </tbody>
                </table>
                <?php submit_button( __( 'Netgsm Ayarlarını Kaydet', 'lotuslisans-reseller' ) ); ?>
            </form>

            <h2><?php esc_html_e( 'Bağlantı Testi', 'lotuslisans-reseller' ); ?></h2>
            <p><?php esc_html_e( 'Kullanıcı kodu ve parolanızı doğrulamak için Netgsm bakiyenizi sorgulayabilirsiniz.', 'lotuslisans-reseller' ); ?></p>
            <?php $this->render_test_button( LotusLisans_Reseller_Plugin::PROVIDER_NETGSM, __( 'Netgsm Bağlantısını Test Et', 'lotuslisans-reseller' ) ); ?>

            <h2><?php esc_html_e( 'Örnek İstek', 'lotuslisans-reseller' ); ?></h2>
            <pre><code>GET <?php echo esc_html( trailingslashit( $base_url ? $base_url : 'https://api.netgsm.com.tr' ) . 'account/balance/json' ); ?>
usercode=<?php echo esc_html( $usercode ? $usercode : 'NETGSM_KOD' ); ?>&amp;password=****</code></pre>
            <p><?php esc_html_e( 'Yanıt içerisinde bakiye veya hata kodunu görmeniz gerekir. Hata kodu aldığınızda Netgsm panelinizden API erişiminizin açık olduğundan emin olun.', 'lotuslisans-reseller' ); ?></p>
        </div>
        <?php
    }

    /**
     * Render Türkpin integration page.
     */
    public function render_turkpin_page() {
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }

        $options     = $this->plugin->get_provider_options( LotusLisans_Reseller_Plugin::PROVIDER_TURKPIN );
        $base_url    = isset( $options['base_url'] ) ? $options['base_url'] : '';
        $dealer_code = isset( $options['dealer_code'] ) ? $options['dealer_code'] : '';
        $api_key     = isset( $options['api_key'] ) ? $options['api_key'] : '';
        $secret_key  = isset( $options['secret_key'] ) ? $options['secret_key'] : '';
        ?>
        <div class="wrap lotuslisans-reseller lotuslisans-provider-page lotuslisans-provider-turkpin">
            <h1><?php esc_html_e( 'Türkpin Entegrasyonu', 'lotuslisans-reseller' ); ?></h1>
            <p class="description"><?php esc_html_e( 'Türkpin hesabınızı kullanarak stok ve siparişlerinizi otomatikleştirin. Aşağıdaki alanları doldurduktan sonra bağlantıyı test edebilirsiniz.', 'lotuslisans-reseller' ); ?></p>

            <form method="post" action="options.php" class="lotuslisans-provider-form">
                <?php settings_fields( 'lotuslisans_reseller_settings' ); ?>
                <table class="form-table" role="presentation">
                    <tbody>
                        <tr>
                            <th scope="row"><label for="lotuslisans-turkpin-base-url"><?php esc_html_e( 'API Adresi', 'lotuslisans-reseller' ); ?></label></th>
                            <td>
                                <input type="url" class="regular-text" id="lotuslisans-turkpin-base-url" name="<?php echo esc_attr( LotusLisans_Reseller_Plugin::OPTION_KEY ); ?>[<?php echo esc_attr( LotusLisans_Reseller_Plugin::PROVIDER_TURKPIN ); ?>][base_url]" value="<?php echo esc_attr( $base_url ); ?>" placeholder="https://panel.turkpin.net/api/v1" />
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="lotuslisans-turkpin-dealer-code"><?php esc_html_e( 'Dealer Kodu', 'lotuslisans-reseller' ); ?></label></th>
                            <td>
                                <input type="text" class="regular-text" id="lotuslisans-turkpin-dealer-code" name="<?php echo esc_attr( LotusLisans_Reseller_Plugin::OPTION_KEY ); ?>[<?php echo esc_attr( LotusLisans_Reseller_Plugin::PROVIDER_TURKPIN ); ?>][dealer_code]" value="<?php echo esc_attr( $dealer_code ); ?>" autocomplete="off" />
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="lotuslisans-turkpin-api-key"><?php esc_html_e( 'API Anahtarı', 'lotuslisans-reseller' ); ?></label></th>
                            <td>
                                <input type="text" class="regular-text" id="lotuslisans-turkpin-api-key" name="<?php echo esc_attr( LotusLisans_Reseller_Plugin::OPTION_KEY ); ?>[<?php echo esc_attr( LotusLisans_Reseller_Plugin::PROVIDER_TURKPIN ); ?>][api_key]" value="<?php echo esc_attr( $api_key ); ?>" autocomplete="off" />
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="lotuslisans-turkpin-secret-key"><?php esc_html_e( 'Gizli Anahtar', 'lotuslisans-reseller' ); ?></label></th>
                            <td>
                                <input type="password" class="regular-text" id="lotuslisans-turkpin-secret-key" name="<?php echo esc_attr( LotusLisans_Reseller_Plugin::OPTION_KEY ); ?>[<?php echo esc_attr( LotusLisans_Reseller_Plugin::PROVIDER_TURKPIN ); ?>][secret_key]" value="<?php echo esc_attr( $secret_key ); ?>" autocomplete="off" />
                            </td>
                        </tr>
                    </tbody>
                </table>
                <?php submit_button( __( 'Türkpin Ayarlarını Kaydet', 'lotuslisans-reseller' ) ); ?>
            </form>

            <h2><?php esc_html_e( 'Bağlantı Testi', 'lotuslisans-reseller' ); ?></h2>
            <p><?php esc_html_e( 'Dealer kodu, API anahtarı ve gizli anahtarınız ile yetkilendirme yaparak hesabınızın bakiyesini sorgulayabilirsiniz.', 'lotuslisans-reseller' ); ?></p>
            <?php $this->render_test_button( LotusLisans_Reseller_Plugin::PROVIDER_TURKPIN, __( 'Türkpin Bağlantısını Test Et', 'lotuslisans-reseller' ) ); ?>

            <h2><?php esc_html_e( 'Örnek JSON İsteği', 'lotuslisans-reseller' ); ?></h2>
            <pre><code>POST <?php echo esc_html( trailingslashit( $base_url ? $base_url : 'https://panel.turkpin.net/api/v1' ) . 'balance' ); ?>
Content-Type: application/json
{
    "dealer_code": "<?php echo esc_html( $dealer_code ? $dealer_code : 'DEALER_KODU' ); ?>",
    "api_key": "<?php echo esc_html( $api_key ? $api_key : 'API_KEY' ); ?>",
    "secret_key": "********"
}</code></pre>
            <p><?php esc_html_e( 'Türkpin yanıtında bakiye veya hata kodu görüntülenir. Başarılı yanıtlar 200 durum kodu ile dönmelidir.', 'lotuslisans-reseller' ); ?></p>
        </div>
        <?php
    }

    /**
     * Render Pinabi integration page.
     */
    public function render_pinabi_page() {
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }

        $options     = $this->plugin->get_provider_options( LotusLisans_Reseller_Plugin::PROVIDER_PINABI );
        $base_url    = isset( $options['base_url'] ) ? $options['base_url'] : '';
        $username    = isset( $options['username'] ) ? $options['username'] : '';
        $password    = isset( $options['password'] ) ? $options['password'] : '';
        $api_key     = isset( $options['api_key'] ) ? $options['api_key'] : '';
        $merchant_id = isset( $options['merchant_id'] ) ? $options['merchant_id'] : '';
        ?>
        <div class="wrap lotuslisans-reseller lotuslisans-provider-page lotuslisans-provider-pinabi">
            <h1><?php esc_html_e( 'Pinabi Entegrasyonu', 'lotuslisans-reseller' ); ?></h1>
            <p class="description"><?php esc_html_e( 'Pinabi üzerinde tanımlı bayi hesabınızı bağlayarak ürün teslimatlarını otomatik hale getirebilirsiniz.', 'lotuslisans-reseller' ); ?></p>

            <form method="post" action="options.php" class="lotuslisans-provider-form">
                <?php settings_fields( 'lotuslisans_reseller_settings' ); ?>
                <table class="form-table" role="presentation">
                    <tbody>
                        <tr>
                            <th scope="row"><label for="lotuslisans-pinabi-base-url"><?php esc_html_e( 'API Adresi', 'lotuslisans-reseller' ); ?></label></th>
                            <td>
                                <input type="url" class="regular-text" id="lotuslisans-pinabi-base-url" name="<?php echo esc_attr( LotusLisans_Reseller_Plugin::OPTION_KEY ); ?>[<?php echo esc_attr( LotusLisans_Reseller_Plugin::PROVIDER_PINABI ); ?>][base_url]" value="<?php echo esc_attr( $base_url ); ?>" placeholder="https://panel.pinabi.com/api" />
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="lotuslisans-pinabi-username"><?php esc_html_e( 'Kullanıcı Adı', 'lotuslisans-reseller' ); ?></label></th>
                            <td>
                                <input type="text" class="regular-text" id="lotuslisans-pinabi-username" name="<?php echo esc_attr( LotusLisans_Reseller_Plugin::OPTION_KEY ); ?>[<?php echo esc_attr( LotusLisans_Reseller_Plugin::PROVIDER_PINABI ); ?>][username]" value="<?php echo esc_attr( $username ); ?>" autocomplete="off" />
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="lotuslisans-pinabi-password"><?php esc_html_e( 'Parola', 'lotuslisans-reseller' ); ?></label></th>
                            <td>
                                <input type="password" class="regular-text" id="lotuslisans-pinabi-password" name="<?php echo esc_attr( LotusLisans_Reseller_Plugin::OPTION_KEY ); ?>[<?php echo esc_attr( LotusLisans_Reseller_Plugin::PROVIDER_PINABI ); ?>][password]" value="<?php echo esc_attr( $password ); ?>" autocomplete="off" />
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="lotuslisans-pinabi-api-key"><?php esc_html_e( 'API Anahtarı', 'lotuslisans-reseller' ); ?></label></th>
                            <td>
                                <input type="text" class="regular-text" id="lotuslisans-pinabi-api-key" name="<?php echo esc_attr( LotusLisans_Reseller_Plugin::OPTION_KEY ); ?>[<?php echo esc_attr( LotusLisans_Reseller_Plugin::PROVIDER_PINABI ); ?>][api_key]" value="<?php echo esc_attr( $api_key ); ?>" autocomplete="off" />
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="lotuslisans-pinabi-merchant-id"><?php esc_html_e( 'Mağaza/Bayi ID', 'lotuslisans-reseller' ); ?></label></th>
                            <td>
                                <input type="text" class="regular-text" id="lotuslisans-pinabi-merchant-id" name="<?php echo esc_attr( LotusLisans_Reseller_Plugin::OPTION_KEY ); ?>[<?php echo esc_attr( LotusLisans_Reseller_Plugin::PROVIDER_PINABI ); ?>][merchant_id]" value="<?php echo esc_attr( $merchant_id ); ?>" autocomplete="off" />
                            </td>
                        </tr>
                    </tbody>
                </table>
                <?php submit_button( __( 'Pinabi Ayarlarını Kaydet', 'lotuslisans-reseller' ) ); ?>
            </form>

            <h2><?php esc_html_e( 'Bağlantı Testi', 'lotuslisans-reseller' ); ?></h2>
            <p><?php esc_html_e( 'Pinabi kimlik bilgilerinizi doğrulamak için aşağıdaki butonu kullanabilirsiniz.', 'lotuslisans-reseller' ); ?></p>
            <?php $this->render_test_button( LotusLisans_Reseller_Plugin::PROVIDER_PINABI, __( 'Pinabi Bağlantısını Test Et', 'lotuslisans-reseller' ) ); ?>

            <h2><?php esc_html_e( 'Örnek JSON İsteği', 'lotuslisans-reseller' ); ?></h2>
            <pre><code>POST <?php echo esc_html( trailingslashit( $base_url ? $base_url : 'https://panel.pinabi.com/api' ) . 'balance' ); ?>
Content-Type: application/json
{
    "username": "<?php echo esc_html( $username ? $username : 'PINABI_KULLANICI' ); ?>",
    "password": "********",
    "api_key": "<?php echo esc_html( $api_key ? $api_key : 'API_KEY' ); ?>",
    "merchant_id": "<?php echo esc_html( $merchant_id ? $merchant_id : 'BAYI_ID' ); ?>"
}</code></pre>
            <p><?php esc_html_e( 'Başarılı yanıtlar mevcut bakiyenizi döndürür. Geçersiz bilgilerde hata kodu ve mesajı görüntülenir.', 'lotuslisans-reseller' ); ?></p>
        </div>
        <?php
    }

    /**
     * Render test button block for a provider.
     *
     * @param string      $provider Provider slug.
     * @param string|null $label    Optional button label.
     */
    protected function render_test_button( $provider, $label = null ) {
        if ( null === $label ) {
            $label = __( 'Bağlantıyı Test Et', 'lotuslisans-reseller' );
        }
        ?>
        <div class="lotuslisans-test-wrapper" data-provider="<?php echo esc_attr( $provider ); ?>">
            <button type="button" class="button button-secondary lotuslisans-test-connection" data-provider="<?php echo esc_attr( $provider ); ?>">
                <?php echo esc_html( $label ); ?>
            </button>
            <span class="lotuslisans-test-result" aria-live="polite"></span>
        </div>
        <?php
    }

    /**
     * Check whether a provider has the required keys configured.
     *
     * @param array $options       Provider options.
     * @param array $required_keys Required option keys.
     *
     * @return bool
     */
    protected function is_provider_configured( array $options, array $required_keys ) {
        foreach ( $required_keys as $key ) {
            if ( empty( $options[ $key ] ) ) {
                return false;
            }
        }

        return true;
    }

    /**
     * AJAX callback for testing the API connection.
     */
    public function ajax_test_connection() {
        $_POST['provider'] = LotusLisans_Reseller_Plugin::PROVIDER_LOTUS; // phpcs:ignore WordPress.Security.NonceVerification.Missing
        $this->ajax_test_provider();
    }

    /**
     * Generic provider connection tester.
     */
    public function ajax_test_provider() {
        check_ajax_referer( 'lotuslisans_test_connection', 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( __( 'Yetkiniz bulunmuyor.', 'lotuslisans-reseller' ) );
        }

        $provider = isset( $_POST['provider'] ) ? sanitize_key( wp_unslash( $_POST['provider'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing

        if ( empty( $provider ) ) {
            wp_send_json_error( __( 'Sağlayıcı bilgisi eksik.', 'lotuslisans-reseller' ) );
        }

        if ( LotusLisans_Reseller_Plugin::PROVIDER_LOTUS === $provider ) {
            $response = $this->api_client->get_user();

            if ( is_wp_error( $response ) ) {
                wp_send_json_error( $response->get_error_message() );
            }

            $message = __( 'Bağlantı başarılı.', 'lotuslisans-reseller' );

            if ( isset( $response['data']['credit'] ) ) {
                $message = sprintf(
                    /* translators: %s: credit balance */
                    __( 'Bağlantı başarılı. Güncel bakiyeniz: %s', 'lotuslisans-reseller' ),
                    esc_html( $response['data']['credit'] )
                );
            }

            wp_send_json_success(
                array(
                    'message' => $message,
                    'data'    => isset( $response['data'] ) ? $response['data'] : array(),
                )
            );
        }

        $client = $this->plugin->provider_client( $provider );

        if ( ! $client ) {
            wp_send_json_error( __( 'Bu sağlayıcı için test işlemi desteklenmiyor.', 'lotuslisans-reseller' ) );
        }

        $result = $client->test_connection();

        if ( is_wp_error( $result ) ) {
            wp_send_json_error( $result->get_error_message() );
        }

        if ( is_array( $result ) && isset( $result['message'] ) ) {
            wp_send_json_success( $result );
        }

        $message = is_string( $result ) ? $result : __( 'Bağlantı başarılı.', 'lotuslisans-reseller' );

        wp_send_json_success(
            array(
                'message' => $message,
                'data'    => $result,
            )
        );
    }

    /**
     * Handle product import submission.
     */
    public function handle_import_products() {
        if ( ! current_user_can( 'manage_woocommerce' ) && ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'Bu işlem için izniniz yok.', 'lotuslisans-reseller' ) );
        }

        check_admin_referer( 'lotuslisans_import_products' );

        $products_response = $this->api_client->get_products();

        if ( is_wp_error( $products_response ) ) {
            $this->plugin->buffer_notice( $products_response->get_error_message(), 'error' );
            wp_safe_redirect( wp_get_referer() ? wp_get_referer() : admin_url( 'admin.php?page=lotuslisans-reseller-lotus' ) );
            exit;
        }

        $products = isset( $products_response['data'] ) && is_array( $products_response['data'] ) ? $products_response['data'] : array();

        $result = $this->import_products( $products );

        $this->handle_product_change_notifications( $products );

        if ( $result['created'] > 0 || $result['updated'] > 0 ) {
            $this->plugin->buffer_notice(
                sprintf(
                    /* translators: 1: created count 2: updated count */
                    esc_html__( '%1$d yeni ürün taslak olarak oluşturuldu, %2$d ürün güncellendi.', 'lotuslisans-reseller' ),
                    $result['created'],
                    $result['updated']
                ),
                'success'
            );
        } else {
            $this->plugin->buffer_notice( esc_html__( 'İçe aktarılacak yeni ürün bulunamadı.', 'lotuslisans-reseller' ), 'info' );
        }

        wp_safe_redirect( wp_get_referer() ? wp_get_referer() : admin_url( 'admin.php?page=lotuslisans-reseller-lotus' ) );
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
            $this->plugin->buffer_notice( esc_html__( 'WooCommerce bulunamadı. Ürünler içe aktarılamadı.', 'lotuslisans-reseller' ), 'error' );
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
                'meta_key'       => '_lotuslisans_product_id',
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
                'post_title'   => isset( $product['title'] ) ? sanitize_text_field( $product['title'] ) : __( 'LotusLisans Ürünü', 'lotuslisans-reseller' ),
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
        update_post_meta( $post_id, '_lotuslisans_product_id', (int) $product['id'] );
        update_post_meta( $post_id, '_lotuslisans_available', $available ? 'yes' : 'no' );
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
                    esc_html__( 'Yeni ürün bulundu: %s', 'lotuslisans-reseller' ),
                    isset( $product['title'] ) ? esc_html( $product['title'] ) : $id
                );
                continue;
            }

            $previous_product = $indexed_previous[ $id ];

            if ( isset( $previous_product['amount'], $product['amount'] ) && $previous_product['amount'] !== $product['amount'] ) {
                $changes[] = sprintf(
                    /* translators: 1: product title 2: old price 3: new price */
                    esc_html__( '%1$s fiyatı güncellendi: %2$s → %3$s', 'lotuslisans-reseller' ),
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
