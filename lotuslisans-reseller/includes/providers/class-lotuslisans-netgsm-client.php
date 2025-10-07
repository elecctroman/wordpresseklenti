<?php
/**
 * Netgsm API client helper.
 *
 * @package LotusLisansReseller
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class LotusLisans_Netgsm_Client extends LotusLisans_Abstract_Provider_Client {

    /**
     * {@inheritDoc}
     */
    protected function get_provider_slug() {
        return LotusLisans_Reseller_Plugin::PROVIDER_NETGSM;
    }

    /**
     * {@inheritDoc}
     */
    public function test_connection() {
        $options  = $this->get_options();
        $usercode = isset( $options['usercode'] ) ? trim( $options['usercode'] ) : '';
        $password = isset( $options['password'] ) ? trim( $options['password'] ) : '';
        $base_url = isset( $options['base_url'] ) && ! empty( $options['base_url'] ) ? $options['base_url'] : 'https://api.netgsm.com.tr';

        if ( '' === $usercode || '' === $password ) {
            return new WP_Error( 'lotuslisans_netgsm_missing_credentials', __( 'Netgsm kullanıcı kodu ve parolası zorunludur.', 'lotuslisans-reseller' ) );
        }

        $endpoint = apply_filters( 'lotuslisans_reseller_netgsm_balance_endpoint', '/account/balance/json' );
        $url      = $this->build_url( $base_url, $endpoint );
        $url      = add_query_arg(
            array(
                'usercode' => $usercode,
                'password' => $password,
            ),
            $url
        );

        $response = $this->request( 'GET', $url );

        if ( is_wp_error( $response ) ) {
            return $response;
        }

        $decoded = $this->decode_response( $response );

        $message = __( 'Netgsm bağlantısı doğrulandı.', 'lotuslisans-reseller' );
        $data    = array();

        if ( is_array( $decoded ) ) {
            $data = $decoded;

            if ( isset( $decoded['balance'] ) ) {
                $message = sprintf(
                    /* translators: %s: balance amount */
                    __( 'Netgsm bakiyesi: %s', 'lotuslisans-reseller' ),
                    $decoded['balance']
                );
            } elseif ( isset( $decoded['balanceTL'] ) ) {
                $message = sprintf(
                    /* translators: %s: balance amount */
                    __( 'Netgsm bakiyesi: %s', 'lotuslisans-reseller' ),
                    $decoded['balanceTL']
                );
            }
        } elseif ( is_string( $decoded ) && '' !== $decoded ) {
            $message = sprintf(
                /* translators: %s: provider response */
                __( 'Netgsm yanıtı: %s', 'lotuslisans-reseller' ),
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
