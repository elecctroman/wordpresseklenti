<?php
/**
 * API client wrapper.
 *
 * @package LotusLisansReseller
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class LotusLisans_API_Client {

    /**
     * API base URL.
     */
    const BASE_URL = 'https://partner.lotuslisans.com.tr';

    /**
     * Plugin instance.
     *
     * @var LotusLisans_Reseller_Plugin
     */
    protected $plugin;

    /**
     * Constructor.
     *
     * @param LotusLisans_Reseller_Plugin $plugin Plugin instance.
     */
    public function __construct( LotusLisans_Reseller_Plugin $plugin ) {
        $this->plugin = $plugin;
    }

    /**
     * Fetch authenticated user details.
     *
     * @return array|WP_Error
     */
    public function get_user() {
        $response = $this->request( 'GET', '/api/user' );

        if ( is_wp_error( $response ) ) {
            return $response;
        }

        if ( isset( $response['data'] ) ) {
            $this->plugin->set_cached_balance( $response['data'] );
        }

        return $response;
    }

    /**
     * Fetch product list.
     *
     * @return array|WP_Error
     */
    public function get_products() {
        return $this->request( 'GET', '/api/products' );
    }

    /**
     * Perform API request.
     *
     * @param string $method   HTTP method.
     * @param string $endpoint Endpoint path.
     * @param array  $args     Optional args.
     *
     * @return array|WP_Error
     */
    public function request( $method, $endpoint, array $args = array() ) {
        $api_key = $this->plugin->get_api_key();

        if ( empty( $api_key ) ) {
            return new WP_Error( 'lotuslisans_missing_key', __( 'Lütfen önce API anahtarınızı kaydedin.', 'lotuslisans-reseller' ) );
        }

        $url = trailingslashit( self::BASE_URL ) . ltrim( $endpoint, '/' );
        $url = add_query_arg( array( 'apikey' => rawurlencode( $api_key ) ), $url );

        $request_args = array(
            'method'  => $method,
            'headers' => array(
                'Accept'     => 'application/json',
                'X-API-Key'  => $api_key,
            ),
            'timeout' => 20,
        );

        if ( isset( $args['body'] ) ) {
            $request_args['body']    = wp_json_encode( $args['body'] );
            $request_args['headers']['Content-Type'] = 'application/json';
        }

        $http_response = wp_remote_request( $url, $request_args );

        if ( is_wp_error( $http_response ) ) {
            return $http_response;
        }

        $code = wp_remote_retrieve_response_code( $http_response );
        $body = wp_remote_retrieve_body( $http_response );

        $data = json_decode( $body, true );
        if ( null === $data && JSON_ERROR_NONE !== json_last_error() ) {
            return new WP_Error( 'lotuslisans_invalid_response', __( 'API yanıtı çözümlenemedi.', 'lotuslisans-reseller' ) );
        }

        if ( $code >= 200 && $code < 300 ) {
            return $data;
        }

        $message = isset( $data['message'] ) ? $data['message'] : __( 'Bilinmeyen API hatası oluştu.', 'lotuslisans-reseller' );

        return new WP_Error( 'lotuslisans_api_error', $message, array( 'status' => $code ) );
    }
}
