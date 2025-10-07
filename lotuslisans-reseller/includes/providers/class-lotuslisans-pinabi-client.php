<?php
/**
 * Pinabi API client helper.
 *
 * @package LotusLisansReseller
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class LotusLisans_Pinabi_Client extends LotusLisans_Abstract_Provider_Client {

    /**
     * {@inheritDoc}
     */
    protected function get_provider_slug() {
        return LotusLisans_Reseller_Plugin::PROVIDER_PINABI;
    }

    /**
     * {@inheritDoc}
     */
    public function test_connection() {
        $options     = $this->get_options();
        $base_url    = isset( $options['base_url'] ) && ! empty( $options['base_url'] ) ? $options['base_url'] : 'https://panel.pinabi.com/api';
        $username    = isset( $options['username'] ) ? trim( $options['username'] ) : '';
        $password    = isset( $options['password'] ) ? trim( $options['password'] ) : '';
        $api_key     = isset( $options['api_key'] ) ? trim( $options['api_key'] ) : '';
        $merchant_id = isset( $options['merchant_id'] ) ? trim( $options['merchant_id'] ) : '';

        if ( '' === $username || '' === $password || '' === $api_key ) {
            return new WP_Error( 'lotuslisans_pinabi_missing_credentials', __( 'Pinabi kullanıcı adı, parola ve API anahtarı alanları zorunludur.', 'lotuslisans-reseller' ) );
        }

        $endpoint = apply_filters( 'lotuslisans_reseller_pinabi_balance_endpoint', '/balance' );
        $url      = $this->build_url( $base_url, $endpoint );

        $body = array(
            'username' => $username,
            'password' => $password,
            'api_key'  => $api_key,
        );

        if ( '' !== $merchant_id ) {
            $body['merchant_id'] = $merchant_id;
        }

        $response = $this->request(
            'POST',
            $url,
            array(
                'body' => $body,
            )
        );

        if ( is_wp_error( $response ) ) {
            return $response;
        }

        $decoded = $this->decode_response( $response );
        $message = __( 'Pinabi bağlantısı doğrulandı.', 'lotuslisans-reseller' );
        $data    = array();

        if ( is_array( $decoded ) ) {
            $data = $decoded;

            if ( isset( $decoded['balance'] ) ) {
                $message = sprintf(
                    /* translators: %s: balance amount */
                    __( 'Pinabi bakiyesi: %s', 'lotuslisans-reseller' ),
                    $decoded['balance']
                );
            } elseif ( isset( $decoded['message'] ) && is_string( $decoded['message'] ) ) {
                $message = $decoded['message'];
            }
        } elseif ( is_string( $decoded ) && '' !== $decoded ) {
            $message = sprintf(
                /* translators: %s: provider response */
                __( 'Pinabi yanıtı: %s', 'lotuslisans-reseller' ),
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
