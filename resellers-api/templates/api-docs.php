<?php
/**
 * Reseller API documentation template.
 *
 * @package ResellersAPI
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

get_header();

$plugin            = function_exists( 'resellers_api' ) ? resellers_api() : null;
$rest_namespace    = class_exists( 'Resellers_API_Plugin' ) ? Resellers_API_Plugin::REST_NAMESPACE : 'reseller-api/v1';
$api_base          = $plugin && method_exists( $plugin, 'get_api_base_url' ) ? $plugin->get_api_base_url() : trailingslashit( rest_url( $rest_namespace ) );
$products_endpoint = $api_base . 'products';
$orders_endpoint   = $api_base . 'orders';
$docs_title        = __( 'Reseller API Dokümantasyonu', 'resellers-api' );
?>
<div class="resellers-api-docs" style="max-width: 960px; margin: 40px auto; padding: 0 20px;">
    <h1><?php echo esc_html( $docs_title ); ?></h1>
    <p><?php esc_html_e( 'Bu doküman, mağazamızdaki ürünleri kendi sitenize aktarıp satışa sunmanız için hazırlanan Reseller API hakkında ayrıntılı bilgi sağlar.', 'resellers-api' ); ?></p>

    <section>
        <h2><?php esc_html_e( 'Kimlik Doğrulama', 'resellers-api' ); ?></h2>
        <p><?php esc_html_e( 'Tüm isteklerde hesabınıza özel API anahtarını kullanmanız gerekir. Anahtarınıza WooCommerce "Hesabım" sayfanızdaki Reseller API panelinden ulaşabilirsiniz.', 'resellers-api' ); ?></p>
        <ul>
            <li><?php esc_html_e( 'Header: X-API-Key: YOUR_KEY', 'resellers-api' ); ?></li>
            <li><?php esc_html_e( 'Sorgu parametresi: ?api_key=YOUR_KEY', 'resellers-api' ); ?></li>
        </ul>
        <p><?php esc_html_e( 'Geçersiz veya eksik anahtar kullanılırsa istek 401 Unauthorized hatası döndürür.', 'resellers-api' ); ?></p>
    </section>

    <section>
        <h2><?php esc_html_e( 'Temel URL', 'resellers-api' ); ?></h2>
        <pre><code><?php echo esc_html( $api_base ); ?></code></pre>
    </section>

    <section>
        <h2><?php esc_html_e( 'Uç Noktalar', 'resellers-api' ); ?></h2>
        <table style="width:100%; border-collapse: collapse;">
            <thead>
                <tr>
                    <th style="text-align:left; border-bottom:1px solid #ddd; padding:8px;"><?php esc_html_e( 'Yöntem', 'resellers-api' ); ?></th>
                    <th style="text-align:left; border-bottom:1px solid #ddd; padding:8px;"><?php esc_html_e( 'Uç Nokta', 'resellers-api' ); ?></th>
                    <th style="text-align:left; border-bottom:1px solid #ddd; padding:8px;"><?php esc_html_e( 'Açıklama', 'resellers-api' ); ?></th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td style="padding:8px;">GET</td>
                    <td style="padding:8px;"><code>/products</code></td>
                    <td style="padding:8px;"><?php esc_html_e( 'Yayınlanmış ürünleri sayfalı şekilde listeler. WooCommerce ürünlerinizi senkronize etmek için kullanabilirsiniz.', 'resellers-api' ); ?></td>
                </tr>
                <tr>
                    <td style="padding:8px;">GET</td>
                    <td style="padding:8px;"><code>/products/{id}</code></td>
                    <td style="padding:8px;"><?php esc_html_e( 'Tek bir ürünün tüm detaylarını döndürür.', 'resellers-api' ); ?></td>
                </tr>
                <tr>
                    <td style="padding:8px;">POST</td>
                    <td style="padding:8px;"><code>/orders</code></td>
                    <td style="padding:8px;"><?php esc_html_e( 'Belirtilen ürünü sepetinize ekleyerek mağazamızda yeni bir sipariş oluşturur. Otomatik teslimat iş akışı kurmak için kullanabilirsiniz.', 'resellers-api' ); ?></td>
                </tr>
            </tbody>
        </table>
    </section>

    <section>
        <h2><?php esc_html_e( 'Ürün Listesi Örneği', 'resellers-api' ); ?></h2>
        <pre><code>GET <?php echo esc_html( $products_endpoint ); ?>?per_page=10&amp;page=1&amp;search=office</code></pre>
        <pre><code>{
  "data": [
    {
      "id": 120,
      "name": "Office 2021 Pro Plus",
      "price": "89.90",
      "currency": "TRY",
      "stock_status": "instock",
      "updated_at": "2024-03-12T12:45:00+00:00"
    }
  ],
  "meta": {
    "page": 1,
    "per_page": 10,
    "total": 42,
    "total_pages": 5,
    "currency": "TRY"
  }
}</code></pre>
        <p><?php esc_html_e( 'API, WooCommerce ürün verilerini döndürür; bu verilerle sitenizde otomatik ürün oluşturabilir, fiyat ve stok güncellemelerini düzenli olarak çekebilirsiniz.', 'resellers-api' ); ?></p>
    </section>

    <section>
        <h2><?php esc_html_e( 'Sipariş Oluşturma Örneği', 'resellers-api' ); ?></h2>
        <pre><code>POST <?php echo esc_html( $orders_endpoint ); ?>
Header: X-API-Key: YOUR_KEY
Body:
{
  "product_id": 120,
  "quantity": 1,
  "customer_email": "musteri@example.com",
  "customer_name": "Müşteri Adı",
  "note": "Sipariş notu"
}</code></pre>
        <pre><code>{
  "data": {
    "order_id": 4891,
    "status": "processing",
    "total": "89.90",
    "currency": "TRY"
  }
}</code></pre>
        <p><?php esc_html_e( 'Siparişler varsayılan olarak işleme alınır ve mağaza panelinizde görüntülenir. Webhook veya e-posta bildirimleri ile siparişleri takip edebilirsiniz.', 'resellers-api' ); ?></p>
    </section>

    <section>
        <h2><?php esc_html_e( 'Filtreleme ve Güncelleme Stratejileri', 'resellers-api' ); ?></h2>
        <ul>
            <li><?php esc_html_e( 'updated_after parametresi ile yalnızca belirli tarihten sonra güncellenen ürünleri çekebilirsiniz.', 'resellers-api' ); ?></li>
            <li><?php esc_html_e( 'per_page değeri en fazla 100 olabilir. Daha yüksek hacimli senkronizasyonlarda sayfalama kullanınız.', 'resellers-api' ); ?></li>
            <li><?php esc_html_e( 'stock_status parametresi ile instock, onbackorder gibi stok durumlarına göre filtreleme yapabilirsiniz.', 'resellers-api' ); ?></li>
        </ul>
    </section>

    <section>
        <h2><?php esc_html_e( 'WordPress/WooCommerce İçin Örnek Kod', 'resellers-api' ); ?></h2>
        <pre><code>add_action( 'init', function() {
    $response = wp_remote_get( '<?php echo esc_url_raw( $products_endpoint ); ?>', array(
        'headers' => array( 'X-API-Key' => 'YOUR_KEY' ),
    ) );

    if ( is_wp_error( $response ) ) {
        return;
    }

    $body = json_decode( wp_remote_retrieve_body( $response ), true );
    // $body['data'] içindeki ürünleri sitenize kaydedebilirsiniz.
} );</code></pre>
    </section>

    <section>
        <h2><?php esc_html_e( 'Özel Yazılımlar İçin Notlar', 'resellers-api' ); ?></h2>
        <ul>
            <li><?php esc_html_e( 'Tüm yanıtlar JSON formatındadır. Başarılı istekler 200, kimlik doğrulaması olmayan istekler 401, bulunamayan kaynaklar 404 kodu döndürür.', 'resellers-api' ); ?></li>
            <li><?php esc_html_e( 'TLS (HTTPS) zorunludur. API anahtarınızı istemci tarafında saklarken şifreli depolama tercih ediniz.', 'resellers-api' ); ?></li>
            <li><?php esc_html_e( 'Siparişleriniz mağaza panelinizde işlenir ve faturalanır; stok güncellemelerini düzenli olarak ürün listesi üzerinden senkronize edebilirsiniz.', 'resellers-api' ); ?></li>
        </ul>
    </section>

    <section>
        <h2><?php esc_html_e( 'Sık Sorulan Sorular', 'resellers-api' ); ?></h2>
        <dl>
            <dt><?php esc_html_e( 'API anahtarımı nasıl yenilerim?', 'resellers-api' ); ?></dt>
            <dd><?php esc_html_e( 'WooCommerce hesabım sayfanızdaki "API Anahtarını Yenile" butonunu kullanabilirsiniz. Eski anahtar hemen geçersiz olur.', 'resellers-api' ); ?></dd>
            <dt><?php esc_html_e( 'Ürün fiyatları güncellendiğinde bildirim alabilir miyim?', 'resellers-api' ); ?></dt>
            <dd><?php esc_html_e( 'Planlı görevlerinizde updated_after filtresini kullanarak yeni fiyatları çekebilir veya mağaza panelinizdeki bildirimleri takip edebilirsiniz.', 'resellers-api' ); ?></dd>
            <dt><?php esc_html_e( 'Sipariş oluştururken müşteri bilgisi zorunlu mu?', 'resellers-api' ); ?></dt>
            <dd><?php esc_html_e( 'Hayır, ancak e-posta ve isim iletirseniz fatura ve teslimat iletişimi daha sağlıklı ilerler.', 'resellers-api' ); ?></dd>
        </dl>
    </section>

    <section>
        <h2><?php esc_html_e( 'Destek', 'resellers-api' ); ?></h2>
        <p><?php esc_html_e( 'API ile ilgili talepleriniz için mağaza panelinizdeki destek kanallarından bize ulaşabilirsiniz.', 'resellers-api' ); ?></p>
        <?php if ( $plugin ) : ?>
            <p><a class="button" href="<?php echo esc_url( admin_url( 'admin.php?page=resellers-api' ) ); ?>"><?php esc_html_e( 'Yönetim Paneline Dön', 'resellers-api' ); ?></a></p>
        <?php endif; ?>
    </section>
</div>
<?php
get_footer();
