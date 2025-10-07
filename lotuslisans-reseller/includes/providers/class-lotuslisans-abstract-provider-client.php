<?php
/**
 * Base provider client for external integrations.
 *
 * @package LotusLisansReseller
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

abstract class LotusLisans_Abstract_Provider_Client {

    /**
     * Plugin bootstrap instance.
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
     * Retrieve provider slug handled by the client.
     *
     * @return string
     */
    abstract protected function get_provider_slug();

    /**
     * Execute a connectivity test against the provider API.
     *
     * @return array|string|WP_Error
     */
    abstract public function test_connection();

    /**
     * Retrieve provider options.
     *
     * @return array
     */
    protected function get_options() {
        return $this->plugin->get_provider_options( $this->get_provider_slug() );
    }

    /**
     * Helper for building provider URLs.
     *
     * @param string $base_url Provider base URL.
     * @param string $endpoint Endpoint path.
     *
     * @return string
     */
    protected function build_url( $base_url, $endpoint ) {
        $base_url = untrailingslashit( $base_url );
        $endpoint = '/' . ltrim( $endpoint, '/' );

        return $base_url . $endpoint;
    }

    /**
     * Perform an HTTP request.
     *
     * @param string $method HTTP method.
     * @param string $url    Target URL.
     * @param array  $args   Optional request arguments.
     *
     * @return array|WP_Error
     */
    protected function request( $method, $url, array $args = array() ) {
        $defaults = array(
            'method'  => strtoupper( $method ),
            'timeout' => 20,
            'headers' => array(
                'Accept' => 'application/json',
            ),
        );

        $args = wp_parse_args( $args, $defaults );

        if ( isset( $args['body'] ) && is_array( $args['body'] ) ) {
            $args['body'] = wp_json_encode( $args['body'] );
            if ( ! isset( $args['headers']['Content-Type'] ) ) {
                $args['headers']['Content-Type'] = 'application/json';
            }
        }

        $response = wp_remote_request( $url, $args );

        if ( is_wp_error( $response ) ) {
            return $response;
        }

        $status = wp_remote_retrieve_response_code( $response );

        if ( $status >= 200 && $status < 300 ) {
            return $response;
        }

        $body    = wp_remote_retrieve_body( $response );
        $message = $this->extract_error_message( $body, $status );

        return new WP_Error( 'lotuslisans_provider_http_error', $message, array( 'status' => $status ) );
    }

    /**
     * Decode provider response.
     *
     * @param array $response HTTP response.
     *
     * @return array|string|WP_Error
     */
    protected function decode_response( $response ) {
        $body = wp_remote_retrieve_body( $response );

        if ( '' === trim( $body ) ) {
            return array();
        }

        $decoded = json_decode( $body, true );

        if ( null !== $decoded || JSON_ERROR_NONE === json_last_error() ) {
            return $decoded;
        }

        if ( function_exists( 'simplexml_load_string' ) ) {
            $xml = @simplexml_load_string( $body ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
            if ( false !== $xml ) {
                return json_decode( wp_json_encode( $xml ), true );
            }
        }

        return trim( $body );
    }

    /**
     * Provide default error messages for failed responses.
     *
     * @param string $body   Raw response body.
     * @param int    $status HTTP status code.
     *
     * @return string
     */
    protected function extract_error_message( $body, $status ) {
        $decoded = json_decode( $body, true );

        if ( is_array( $decoded ) ) {
            if ( isset( $decoded['message'] ) && is_string( $decoded['message'] ) ) {
                return $decoded['message'];
            }

            if ( isset( $decoded['error'] ) ) {
                if ( is_string( $decoded['error'] ) ) {
                    return $decoded['error'];
                }

                return wp_json_encode( $decoded['error'] );
            }
        }

        $body = trim( wp_strip_all_tags( $body ) );
        if ( '' !== $body ) {
            return $body;
        }

        return sprintf(
            /* translators: %s: HTTP status code */
            __( 'Servis %s hatası döndürdü.', 'lotuslisans-reseller' ),
            absint( $status )
        );
    }
}
