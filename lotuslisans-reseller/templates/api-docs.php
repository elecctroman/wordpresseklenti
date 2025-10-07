<?php
/**
 * Reseller API documentation template.
 *
 * @package LotusLisansReseller
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

get_header();


$docs_title        = __( 'Reseller API Dokümantasyonu', 'lotuslisans-reseller' );
?>
<div class="lotuslisans-api-docs" style="max-width: 960px; margin: 40px auto; padding: 0 20px;">
    <h1><?php echo esc_html( $docs_title ); ?></h1>
    <p><?php esc_html_e( 'Bu doküman, mağazamızdaki ürünleri kendi sitenize aktarıp satışa sunmanız için hazırlanan Reseller API hakkında ayrıntılı bilgi sağlar.', 'lotuslisans-reseller' ); ?></p>

    <section>
        <h2><?php esc_html_e( 'Kimlik Doğrulama', 'lotuslisans-reseller' ); ?></h2>
        <p><?php esc_html_e( 'Tüm isteklerde hesabınıza özel API anahtarını kullanmanız gerekir. Anahtarınıza WooCommerce "Hesabım" sayfanızdaki Reseller API panelinden ulaşabilirsiniz.', 'lotuslisans-reseller' ); ?></p>
        <ul>
            <li><?php esc_html_e( 'Header: X-API-Key: YOUR_KEY', 'lotuslisans-reseller' ); ?></li>
            <li><?php esc_html_e( 'Sorgu parametresi: ?api_key=YOUR_KEY', 'lotuslisans-reseller' ); ?></li>
        </ul>
        <p><?php esc_html_e( 'Geçersiz veya eksik anahtar kullanılırsa istek 401 Unauthorized hatası döndürür.', 'lotuslisans-reseller' ); ?></p>
    </section>

    <section>
        <h2><?php esc_html_e( 'Temel URL', 'lotuslisans-reseller' ); ?></h2>
        <pre><code><?php echo esc_html( $api_base ); ?></code></pre>
    </section>

    <section>
        <h2><?php esc_html_e( 'Uç Noktalar', 'lotuslisans-reseller' ); ?></h2>
        <table style="width:100%; border-collapse: collapse;">
            <thead>
                <tr>
                    <th style="text-align:left; border-bottom:1px solid #ddd; padding:8px;"><?php esc_html_e( 'Yöntem', 'lotuslisans-reseller' ); ?></th>
                    <th style="text-align:left; border-bottom:1px solid #ddd; padding:8px;"><?php esc_html_e( 'Uç Nokta', 'lotuslisans-reseller' ); ?></th>
                    <th style="text-align:left; border-bottom:1px solid #ddd; padding:8px;"><?php esc_html_e( 'Açıklama', 'lotuslisans-reseller' ); ?></th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td style="padding:8px;">GET</td>
                    <td style="padding:8px;"><code>/products</code></td>
                    <td style="padding:8px;"><?php esc_html_e( 'Yayınlanmış ürünleri sayfalı şekilde listeler. WooCommerce ürünlerinizi senkronize etmek için kullanabilirsiniz.', 'lotuslisans-reseller' ); ?></td>
                </tr>
                <tr>
                    <td style="padding:8px;">GET</td>
                    <td style="padding:8px;"><code>/products/{id}</code></td>
                    <td style="padding:8px;"><?php esc_html_e( 'Tek bir ürünün tüm detaylarını döndürür.', 'lotuslisans-reseller' ); ?></td>
                </tr>
                <tr>
                    <td style="padding:8px;">POST</td>
                    <td style="padding:8px;"><code>/orders</code></td>
                    <td style="padding:8px;"><?php esc_html_e( 'Belirtilen ürünü sepetinize ekleyerek mağazamızda yeni bir sipariş oluşturur. Otomatik teslimat iş akışı kurmak için kullanabilirsiniz.', 'lotuslisans-reseller' ); ?></td>
                </tr>
            </tbody>
        </table>
    </section>

    <section>
        <h2><?php esc_html_e( 'Ürün Listesi Örneği', 'lotuslisans-reseller' ); ?></h2>
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
        <p><?php esc_html_e( 'API, WooCommerce ürün verilerini döndürür; bu verilerle sitenizde otomatik ürün oluşturabilir, fiyat ve stok güncellemelerini düzenli olarak çekebilirsiniz.', 'lotuslisans-reseller' ); ?></p>
    </section>

    <section>
        <h2><?php esc_html_e( 'Sipariş Oluşturma Örneği', 'lotuslisans-reseller' ); ?></h2>
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
        <p><?php esc_html_e( 'Siparişler varsayılan olarak işleme alınır ve mağaza panelinizde görüntülenir. Webhook veya e-posta bildirimleri ile siparişleri takip edebilirsiniz.', 'lotuslisans-reseller' ); ?></p>
    </section>

    <section>
        <h2><?php esc_html_e( 'Filtreleme ve Güncelleme Stratejileri', 'lotuslisans-reseller' ); ?></h2>
        <ul>
            <li><?php esc_html_e( 'updated_after parametresi ile yalnızca belirli tarihten sonra güncellenen ürünleri çekebilirsiniz.', 'lotuslisans-reseller' ); ?></li>
            <li><?php esc_html_e( 'per_page değeri en fazla 100 olabilir. Daha yüksek hacimli senkronizasyonlarda sayfalama kullanınız.', 'lotuslisans-reseller' ); ?></li>
            <li><?php esc_html_e( 'stock_status parametresi ile instock, onbackorder gibi stok durumlarına göre filtreleme yapabilirsiniz.', 'lotuslisans-reseller' ); ?></li>
        </ul>
    </section>

    <section>
        <h2><?php esc_html_e( 'WordPress/WooCommerce İçin Örnek Kod', 'lotuslisans-reseller' ); ?></h2>
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
        <h2><?php esc_html_e( 'Özel Yazılımlar İçin Notlar', 'lotuslisans-reseller' ); ?></h2>
        <ul>
            <li><?php esc_html_e( 'Tüm yanıtlar JSON formatındadır. Başarılı istekler 200, kimlik doğrulaması olmayan istekler 401, bulunamayan kaynaklar 404 kodu döndürür.', 'lotuslisans-reseller' ); ?></li>
            <li><?php esc_html_e( 'TLS (HTTPS) zorunludur. API anahtarınızı istemci tarafında saklarken şifreli depolama tercih ediniz.', 'lotuslisans-reseller' ); ?></li>
            <li><?php esc_html_e( 'Siparişleriniz mağaza panelinizde işlenir ve faturalanır; stok güncellemelerini düzenli olarak ürün listesi üzerinden senkronize edebilirsiniz.', 'lotuslisans-reseller' ); ?></li>
        </ul>
    </section>

    <section>
        <h2><?php esc_html_e( 'Sık Sorulan Sorular', 'lotuslisans-reseller' ); ?></h2>
        <dl>
            <dt><?php esc_html_e( 'API anahtarımı nasıl yenilerim?', 'lotuslisans-reseller' ); ?></dt>
            <dd><?php esc_html_e( 'WooCommerce hesabım sayfanızdaki "API Anahtarını Yenile" butonunu kullanabilirsiniz. Eski anahtar hemen geçersiz olur.', 'lotuslisans-reseller' ); ?></dd>
            <dt><?php esc_html_e( 'Ürün fiyatları güncellendiğinde bildirim alabilir miyim?', 'lotuslisans-reseller' ); ?></dt>
            <dd><?php esc_html_e( 'Planlı görevlerinizde updated_after filtresini kullanarak yeni fiyatları çekebilir veya mağaza panelinizdeki bildirimleri takip edebilirsiniz.', 'lotuslisans-reseller' ); ?></dd>
            <dt><?php esc_html_e( 'Sipariş oluştururken müşteri bilgisi zorunlu mu?', 'lotuslisans-reseller' ); ?></dt>
            <dd><?php esc_html_e( 'Hayır, ancak e-posta ve isim iletirseniz fatura ve teslimat iletişimi daha sağlıklı ilerler.', 'lotuslisans-reseller' ); ?></dd>
        </dl>
    </section>

    <section>
        <h2><?php esc_html_e( 'Destek', 'lotuslisans-reseller' ); ?></h2>
        <p><?php esc_html_e( 'API ile ilgili talepleriniz için mağaza panelinizdeki destek kanallarından bize ulaşabilirsiniz.', 'lotuslisans-reseller' ); ?></p>
        <?php if ( $plugin ) : ?>
            <p><a class="button" href="<?php echo esc_url( admin_url( 'admin.php?page=lotuslisans-reseller' ) ); ?>"><?php esc_html_e( 'Yönetim Paneline Dön', 'lotuslisans-reseller' ); ?></a></p>
        <?php endif; ?>
    </section>
</div>
<?php
get_footer();
