<?php
use Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType;

final class WC_Gateway_POLi_Blocks_Support extends AbstractPaymentMethodType {
	private $gateway;

	protected $name = 'poli';

	public function initialize() {
		$this->settings = get_option( 'woocommerce_dummy_settings', [] );
		$this->gateway  = new WC_Gateway_POLi();
	}

	public function is_active() {
		return $this->gateway->is_available();
	}

	public function get_payment_method_script_handles() {
		$script_path       = '/frontend/blocks.js';
		$script_asset_path = WC_Gateway_POLi::plugin_abspath() . 'frontend/blocks.asset.php';
		$script_asset      = file_exists( $script_asset_path )
			? require( $script_asset_path )
			: array(
				'dependencies' => array(),
				'version'      => '6.1.0'
			);
		$script_url        = WC_Gateway_POLi::plugin_url() . $script_path;

		wp_register_script(
			'wc-poli-payments-blocks',
			$script_url,
			$script_asset[ 'dependencies' ],
			$script_asset[ 'version' ],
			true
		);
/*
		if ( function_exists( 'wp_set_script_translations' ) ) {
			wp_set_script_translations( 'wc-dummy-payments-blocks', 'woocommerce-gateway-dummy', WC_Dummy_Payments::plugin_abspath() . 'languages/' );
		}
*/
		return [ 'wc-poli-payments-blocks' ];
	}

	public function get_payment_method_data() {
		return [
			'title'       => $this->get_setting( 'title' ),
			'description' => $this->get_setting( 'description' ),
			'supports'    => array_filter( $this->gateway->supports, [ $this->gateway, 'supports' ] )
		];
	}
}
