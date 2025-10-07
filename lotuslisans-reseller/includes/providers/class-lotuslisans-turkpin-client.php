<?php
/**
 * Türkpin API client helper.
 *
 * @package LotusLisansReseller
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class LotusLisans_Turkpin_Client extends LotusLisans_Abstract_Provider_Client {

    /**
     * {@inheritDoc}
     */
    protected function get_provider_slug() {
        return LotusLisans_Reseller_Plugin::PROVIDER_TURKPIN;
    }

    /**
     * {@inheritDoc}
     */
    public function test_connection() {
        $options     = $this->get_options();
        $base_url    = isset( $options['base_url'] ) && ! empty( $options['base_url'] ) ? $options['base_url'] : 'https://panel.turkpin.net/api/v1';
        $dealer_code = isset( $options['dealer_code'] ) ? trim( $options['dealer_code'] ) : '';
        $api_key     = isset( $options['api_key'] ) ? trim( $options['api_key'] ) : '';
        $secret_key  = isset( $options['secret_key'] ) ? trim( $options['secret_key'] ) : '';

        if ( '' === $dealer_code || '' === $api_key || '' === $secret_key ) {
            return new WP_Error( 'lotuslisans_turkpin_missing_credentials', __( 'Türkpin dealer kodu, API anahtarı ve gizli anahtar alanları zorunludur.', 'lotuslisans-reseller' ) );
        }

        $endpoint = apply_filters( 'lotuslisans_reseller_turkpin_balance_endpoint', '/balance' );
        $url      = $this->build_url( $base_url, $endpoint );

        $response = $this->request(
            'POST',
            $url,
            array(
                'body' => array(
                    'dealer_code' => $dealer_code,
                    'api_key'     => $api_key,
                    'secret_key'  => $secret_key,
                ),
            )
        );

        if ( is_wp_error( $response ) ) {
            return $response;
        }

        $decoded = $this->decode_response( $response );
        $message = __( 'Türkpin bağlantısı doğrulandı.', 'lotuslisans-reseller' );
        $data    = array();

        if ( is_array( $decoded ) ) {
            $data = $decoded;

            if ( isset( $decoded['balance'] ) ) {
                $message = sprintf(
                    /* translators: %s: balance amount */
                    __( 'Türkpin bakiyesi: %s', 'lotuslisans-reseller' ),
                    $decoded['balance']
                );
            } elseif ( isset( $decoded['status'] ) && 'success' !== strtolower( (string) $decoded['status'] ) ) {
                $message = sprintf(
                    /* translators: %s: provider status */
                    __( 'Türkpin yanıtı: %s', 'lotuslisans-reseller' ),
                    $decoded['status']
                );
            }
        } elseif ( is_string( $decoded ) && '' !== $decoded ) {
            $message = sprintf(
                /* translators: %s: provider response */
                __( 'Türkpin yanıtı: %s', 'lotuslisans-reseller' ),
                $decoded
            );
            $data = array( 'response' => $decoded );
        }

        return array(
            'message' => $message,
            'data'    => $data,
        );
    }
}
