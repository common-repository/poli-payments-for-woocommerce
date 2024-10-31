<?php
/**
 * Plugin Name: POLi Payments for WooCommerce
 * Plugin URI: https://www.polipay.co.nz
 * Description: This plugin enables POLi payments for WooCommerce
 * Version: 6.2.2
 * Author: Merco
 * Author URI: https://www.polipay.co.nz/about-merco/
 * License: GPL-3.0
 *
 * WC requires at least: 3.6
 * WC tested up to: 9.2.1
 */

/*
https://github.com/woocommerce/woocommerce/wiki/High-Performance-Order-Storage-Upgrade-Recipe-Book
*/

use Automattic\WooCommerce\Utilities\OrderUtil;

add_action( 'before_woocommerce_init', function() {
	if ( class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {
		\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', __FILE__, true );
	}
} );

add_action('plugins_loaded', 'woocommerce_gateway_poli_init', 0);

function woocommerce_gateway_poli_init() {
    if ( !class_exists( 'WC_Payment_Gateway' ) ) return;
    load_plugin_textdomain('wc-gateway-poli', false, dirname( plugin_basename( __FILE__ ) ) . '/languages');

    class WC_Gateway_POLi extends WC_Payment_Gateway {
		private $upayments_poli;
		private $debug;
		private $bankparts;
		private $using_hpos;
		public function __construct() {
			$this->method_title = 'POLi Payments';
			$this->id = 'poli';
			$this->icon = plugins_url('', __FILE__) . '/images/poli-icon.png';
			$this->title = "POLi - Easy Bank Transfer";
			$this->method_description = "POLi allows users to securely pay directly from their bank accounts. <a href=\"https://www.polipay.co.nz/sell-with-poli/get-poli/?SC=woocommerce2\" target=\"_blank\">Sign up</a> for a POLi account to receive your configuration codes.";
			$this->has_fields         = true;
			$this->supports           = array(
				'products',
				'add_payment_method',
			);
			
			// Gateway
		    if ( !class_exists( 'upayments_poli_class' ) ){
				$path=plugin_dir_path( __FILE__ )."poli.class.php";
				include $path;
			}
			$this->upayments_poli=new upayments_poli_class();
			$this->debug=false;
			$this->bankparts=$this->upayments_poli->get_bankparts();

			// Create admin configuration form
			$this->initForm();
			
			// Initialise gateway settings
			$this->init_settings();
			add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );
			add_action( 'woocommerce_api_poli_nudge', array($this, 'result'));
			$this->description = 'Pay fast and secure with your bank account.<br><br>Choose your bank, login, select an account and complete the payment.<br><br>'
				.'<a target="_blank" href="https://www.polipay.co.nz/poli-for-consumers/">Learn More about POLi</a>'
				/*.'<br><a target="_blank" href = https://transaction.apac.paywithpoli.com/POLiFISupported.aspx?merchantcode='.$this->get_option('merchantcode').'>Available Banks</a>'*/;
				
			if ( class_exists( \Automattic\WooCommerce\Utilities\OrderUtil::class ) ) {
				$this->using_hpos=OrderUtil::custom_orders_table_usage_is_enabled();
			}
			
			// Upgrade plugin
			$install_done61=$this->get_option( 'poli-payments-for-woocommerce-done61' );
			if( $install_done61 != "Y" ){
				$this->update_option( 'poli-payments-for-woocommerce-done61', "Y" );
				$option_authenticationcode=poli_getoptional_setting('authenticationcode');
				$option_merchantcode=poli_getoptional_setting('merchantcode');
				$option_authenticationcode_nzd=poli_getoptional_setting('nzdauthenticationcode');
				$option_merchantcode_nzd=poli_getoptional_setting('nzdmerchantcode');
				$multicurrency=$this->get_option('multicurrency');
				if( $multicurrency == "no" and $option_authenticationcode != "" ){
					poli_set_setting( 'nzdauthenticationcode', $option_authenticationcode );
					poli_set_setting( 'nzdmerchantcode', $option_merchantcode );
				}
			}
		}

		// Settings
		public function cleanSettings(){
			$poliid_used=false;
			$defaults=array( 'orderid', 'name', 'poliid' );
			
			foreach( $this->bankparts as $key => $field ){
				$v=$this->get_option( "nzd".$field );
				if( $v == "" ){
					$this->update_option( "nzd".$field, $defaults[$key] );
				}
				if( $v == "poliid" ){
					if( $poliid_used ) $this->update_option( "nzd".$field, "blank" );
					$poliid_used=true;
				}
				$v=$this->get_option( "nzd".$field."-freetext" );
				$v=preg_replace( "/[^a-zA-Z0-9\s]/", "", $v);
				$v=substr( $v, 0 ,$this->upayments_poli->max_bank_part_length );
				$this->update_option( "nzd".$field."-freetext", $v );
				
				$v=$this->get_option( "uatnzd".$field );
				if( $v == "" ){
					$this->update_option( "uatnzd".$field, $defaults[$key] );
				}
				if( $v == "poliid" ){
					if( $poliid_used ) $this->update_option( "uatnzd".$field, "blank" );
					$poliid_used=true;
				}
				$v=$this->get_option( "uatnzd".$field."-freetext" );
				$v=preg_replace( "/[^a-zA-Z0-9\s]/", "", $v);
				$v=substr( $v, 0 ,$this->upayments_poli->max_bank_part_length );
				$this->update_option( "uatnzd".$field."-freetext", $v );
				
			}
		}
        private function initForm() {
			// Default display mode
			$this->cleanSettings();
			
			$reconciliation_options=array(
				'orderid' => 'Order ID',
				'name' => 'Payer Name',
				'poliid' => 'POLi ID',
				'freetext' => 'Free text',
				'blank' => 'Blank',
			);
            $this->form_fields = array(
				'info1' => array(
					'type'        => 'title',
					'description' => "POLi enables users to pay securely and direct from their bank accounts. <a href=\"https://www.polipay.co.nz/sell-with-poli/get-poli/?SC=woocommerce2\" target=\"_blank\">Sign up</a> for a POLi account to receive your configuration codes. The current supported region is New Zealand.",
				),
                'enabled' => array(
                    'title' => __( 'Enable POLi', 'POLi' ),
                    'label' => __( 'Enable POLi Payments in the checkout', 'POLi' ),
                    'type' => 'checkbox',
                    'description' => '',
                    'default' => 'no'
                ),
                'useuat' => array(
                    'title' => __( 'Enable Test Mode', 'POLi' ),
                    'label' => __( 'Enable POLi test mode', 'POLi' ),
                    'type' => 'checkbox',
                    'description' => 'Use a <a href="https://www.polipay.co.nz/support/developer-test-account/" target=blank>developer account</a> to simulate various transactions using our UAT environment',
                    'default' => 'no'
                ),

				// ------------------------------------ UAT
				'teston' => array( 'type' => 'title', 'description' => "<div id=\"poliuat\"><h1>UAT Account Settings</h1>
					<P>POLi will display on the checkout of NZD payments only.</p>" ),
/*
				'uatmultion' => array( 'type' => 'title', 'description' => "<div id=\"poliuatmulti\">" ),
				'uataudon' => array(
					'type'        => 'title',
					'description' => "<h2>Australian POLi test account</h2>
						<P>This account will be enabled for AUD test payments only.</p>",
				),
                'uataudmerchantcode' => array(
                    'title' => __('Merchant Code', 'POLi'),
                    'type' => 'text',
                    'class' => 'ic-input',
                    'description' => __('Input your Australian POLi Merchant Code. Number sequence should start with 61', 'POLi'),
                    'desc_tip' => true
                ),
                'uataudauthenticationcode' => array(
                    'title' => __('Authentication Code', 'POLi'),
                    'type' => 'text',
                    'class' => 'ic-input',
                    'description' => __('Input your Australian POLi Authentication Code', 'POLi'),
                    'desc_tip' => true
                ),

				'uatnzdon' => array(
					'type'        => 'title',
					'description' => "<h2>POLi Test Account</h2>
						<P>POLi will display on the checkout of NZD payments only.</p>",
				),
*/
                'uatnzdmerchantcode' => array(
                    'title' => __('Merchant Code', 'POLi'),
                    'type' => 'text',
                    'class' => 'ic-input',
                    'description' => __('Input your New Zealand POLi Merchant Code. Number sequence should start with 64', 'POLi'),
                    'desc_tip' => true
                ),
                'uatnzdauthenticationcode' => array(
                    'title' => __('Authentication Code', 'POLi'),
                    'type' => 'text',
                    'class' => 'ic-input',
                    'description' => __('Input your New Zealand POLi Authentication Code', 'POLi'),
                    'desc_tip' => true
                ),
				'uatnzdon2' => array(
					'type'        => 'title',
					'description' => "<h3>Reconciliation</h3>
						<P>Control what data appears on your bank statement.</p>",
				),
                'uatnzdparticulars' => array(
                    'title' => __('Particulars', 'POLi'),
                    'type' => 'select',
					'options' => $reconciliation_options,
                    'class' => 'ic-input',
                    'desc_tip' => false
                ),
				'uatnzdparticulars-freetext-on' => array( 'type' => 'title', 'description' => "<div id=\"uatnzdparticulars_freetext\">" ),
                'uatnzdparticulars-freetext' => array(
                    'type' => 'text',
                    'class' => 'ic-input',
                    'description' => __('Limit of 12 characters. Letters, numbers, and spaces only', 'POLi'),
                    'desc_tip' => true
                ),
				'uatnzdparticulars-freetext-off' => array( 'type' => 'title', 'description' => "</div>" ),
                'uatnzdcode' => array(
                    'title' => __('Code', 'POLi'),
                    'type' => 'select',
					'options' => $reconciliation_options,
                    'class' => 'ic-input',
                    'desc_tip' => false
                ),
				'uatnzdcode-freetext-on' => array( 'type' => 'title', 'description' => "<div id=\"uatnzdcode_freetext\">" ),
                'uatnzdcode-freetext' => array(
                    'type' => 'text',
                    'class' => 'ic-input',
                    'description' => __('Limit of 12 characters. Letters, numbers, and spaces only', 'POLi'),
                    'desc_tip' => true
                ),
				'uatnzdcode-freetext-off' => array( 'type' => 'title', 'description' => "</div>" ),
                'uatnzdreference' => array(
                    'title' => __('Reference', 'POLi'),
                    'type' => 'select',
					'options' => $reconciliation_options,
                    'class' => 'ic-input',
                    'desc_tip' => false
                ),
				'uatnzdreference-freetext-on' => array( 'type' => 'title', 'description' => "<div id=\"uatnzdreference_freetext\">" ),
                'uatnzdreference-freetext' => array(
                    'type' => 'text',
                    'class' => 'ic-input',
                    'description' => __('Limit of 12 characters. Letters, numbers, and spaces only', 'POLi'),
                    'desc_tip' => true
                ),
				'uatnzdreference-freetext-off' => array( 'type' => 'title', 'description' => "</div>" ),
				// 'uatmultioff' => array( 'type' => 'title', 'description' => "</div>" ),
				
				'testoff' => array( 'type' => 'title', 'description' => "</div>" ),

				// ------------------------------------ LIVE
				'liveon' => array( 'type' => 'title', 'description' => "<div id=\"polilive\"><h1>Live Account Settings</h1>
						<P>POLi will display on the checkout of NZD payments only.</p>" ),
/*
                'multicurrency' => array(
                    'title' => __( 'Enable Advanced Features', 'POLi' ),
                    'label' => __( 'Enable multi-currency and reconciliation settings', 'POLi' ),
                    'type' => 'checkbox',
                    'description' => 'Enable both AUD payments, NZD payments, and NZ reconciliation settings.',
                    'default' => 'no'
                ),
*/
				'defaulton' => array( 'type' => 'title', 'description' => "<div id=\"polidefault\">" ),
/*
                'merchantcode' => array(
                    'title' => __('Merchant Code', 'POLi'),
                    'type' => 'text',
                    'class' => 'ic-input',
                    'description' => __('Please input your Merchant Code', 'POLi'),
                    'desc_tip' => true
                ),
                'authenticationcode' => array(
                    'title' => __('Authentication Code', 'POLi'),
                    'type' => 'text',
                    'class' => 'ic-input',
                    'description' => __('Please input your Authentication Code', 'POLi'),
                    'desc_tip' => true
                ),

				'defaultoff' => array( 'type' => 'title', 'description' => "</div>" ),
				'multion' => array( 'type' => 'title', 'description' => "<div id=\"polimulti\">" ),
				'audon' => array(
					'type'        => 'title',
					'description' => "<h2>Australian POLi account</h2>
						<P>This account will be enabled for AUD payments only.</p>",
				),
                'audmerchantcode' => array(
                    'title' => __('Merchant Code', 'POLi'),
                    'type' => 'text',
                    'class' => 'ic-input',
                    'description' => __('Input your Australian POLi Merchant Code. Number sequence should start with 61', 'POLi'),
                    'desc_tip' => true
                ),
                'audauthenticationcode' => array(
                    'title' => __('Authentication Code', 'POLi'),
                    'type' => 'text',
                    'class' => 'ic-input',
                    'description' => __('Input your Australian POLi Authentication Code', 'POLi'),
                    'desc_tip' => true
                ),

				'nzdon' => array(
					'type'        => 'title',
					'description' => "<h2>POLi account</h2>
						<P>POLi will display on the checkout of NZD payments only.</p>",
				),
*/
                'nzdmerchantcode' => array(
                    'title' => __('Merchant Code', 'POLi'),
                    'type' => 'text',
                    'class' => 'ic-input',
                    'description' => __('Input your POLi Merchant Code. Number sequence should start with 64', 'POLi'),
                    'desc_tip' => true
                ),
                'nzdauthenticationcode' => array(
                    'title' => __('Authentication Code', 'POLi'),
                    'type' => 'text',
                    'class' => 'ic-input',
                    'description' => __('Input your POLi Authentication Code', 'POLi'),
                    'desc_tip' => true
                ),
				'nzdon2' => array(
					'type'        => 'title',
					'description' => "<h3>Reconciliation</h3>
						<P>Control what data appears on your bank statement.</p>",
				),
                'nzdparticulars' => array(
                    'title' => __('Particulars', 'POLi'),
                    'type' => 'select',
					'options' => $reconciliation_options,
                    'class' => 'ic-input',
                    'desc_tip' => false
                ),
				'nzdparticulars-freetext-on' => array( 'type' => 'title', 'description' => "<div id=\"nzdparticulars_freetext\">" ),
                'nzdparticulars-freetext' => array(
                    'type' => 'text',
                    'class' => 'ic-input',
                    'description' => __('Limit of 12 characters. Letters, numbers, and spaces only', 'POLi'),
                    'desc_tip' => true
                ),
				'nzdparticulars-freetext-off' => array( 'type' => 'title', 'description' => "</div>" ),
                'nzdcode' => array(
                    'title' => __('Code', 'POLi'),
                    'type' => 'select',
					'options' => $reconciliation_options,
                    'class' => 'ic-input',
                    'desc_tip' => false
                ),
				'nzdcode-freetext-on' => array( 'type' => 'title', 'description' => "<div id=\"nzdcode_freetext\">" ),
                'nzdcode-freetext' => array(
                    'type' => 'text',
                    'class' => 'ic-input',
                    'description' => __('Limit of 12 characters. Letters, numbers, and spaces only', 'POLi'),
                    'desc_tip' => true
                ),
				'nzdcode-freetext-off' => array( 'type' => 'title', 'description' => "</div>" ),
                'nzdreference' => array(
                    'title' => __('Reference', 'POLi'),
                    'type' => 'select',
					'options' => $reconciliation_options,
                    'class' => 'ic-input',
                    'desc_tip' => false
                ),
				'nzdreference-freetext-on' => array( 'type' => 'title', 'description' => "<div id=\"nzdreference_freetext\">" ),
                'nzdreference-freetext' => array(
                    'type' => 'text',
                    'class' => 'ic-input',
                    'description' => __('Limit of 12 characters. Letters, numbers, and spaces only', 'POLi'),
                    'desc_tip' => true
                ),
				'nzdreference-freetext-off' => array( 'type' => 'title', 'description' => "</div>" ),
				'defaultoff' => array( 'type' => 'title', 'description' => "</div>" ),
				
//				'multioff' => array( 'type' => 'title', 'description' => "</div>" ),
				'liveoff' => array( 'type' => 'title', 'description' => "</div>" ),
            );
        }


        function admin_options() {
			$plugin_data = get_plugin_data( __FILE__ );
			$plugin_version = $plugin_data['Version'];
			//wp_register_script( 'poli_admin_js',plugin_dir_url( __FILE__ ) . 'admin.js', '',true );
			//wp_enqueue_script( 'poli_admin_js' );
			echo "<h2>POLi Payments</h2><table class=\"form-table\">";
			$this->generate_settings_html();
			echo "</table>
				<script src=\"".plugin_dir_url( __FILE__ )."admin.js?v=".$plugin_version."\"></script>";
        }
		private function set_credentials( $currency ){
			$authenticationcode = $this->get_option('nzdauthenticationcode');
			$merchantcode=$this->get_option('nzdmerchantcode');
			if( $this->get_use_uat() ){
				$authenticationcode = $this->get_option( 'uatnzdauthenticationcode' );
				$merchantcode = $this->get_option( 'uatnzdmerchantcode' );
			}
/*
            $authenticationcode = $this->get_option('authenticationcode');
            $merchantcode = $this->get_option('merchantcode');
			if( $this->get_use_uat() ){
				$authenticationcode = $this->get_option( 'uat'.$currency.'authenticationcode' );
				$merchantcode = $this->get_option( 'uat'.$currency.'merchantcode' );
			} else {
				$multicurrency=$this->get_option('multicurrency');
				if( $multicurrency == "yes" ){
					$authenticationcode = $this->get_option($currency.'authenticationcode');
					$merchantcode=$this->get_option($currency.'merchantcode');
				}
			}
*/
			$this->upayments_poli->authcode=$authenticationcode;
			$this->upayments_poli->merchantcode=$merchantcode;
		}
		private function get_use_uat( ){
            if( $this->get_option('useuat') == "yes" ){
				return true;
			} else {
				return false;
			}
		}
		private function get_bank_part_value( $order, $currency, $field, $reference ){
			$option_prefix=$currency;
			if( $this->get_use_uat() ) $option_prefix="uat".$option_prefix;
			$setting=$this->get_option($option_prefix.$field);
			$v="";
			switch( $setting ){
				case 'freetext':	$v=$this->get_option($option_prefix.$field."-freetext" );
									break;
				case 'orderid':		$v=$reference;
									break;
				case 'name':		$v=$order->get_billing_first_name()." ".$order->get_billing_last_name();
									break;
				case 'blank':		$v=" ";
									break;
			}
			return $v;
		}
        function process_payment ($order_id) {
            global $woocommerce;

            $order = new WC_Order( $order_id );
            $order->update_status('pending', __( 'Payment yet to be recieved', 'POLi Payments' ));
            $amount = $order->get_total();
            $currency = get_woocommerce_currency();

            $datetime = date('Y-m-d').'T'.date('H:i:s');
            $ipaddress = $_SERVER["REMOTE_ADDR"];
            $baselink = ''.site_url();
			if (function_exists('wc_sequential_order_numbers')) {
				$order = wc_get_order( $order_id );
				$reference = $order->get_order_number();
			} else {
				$reference = $order_id;
			}
	
            $this->upayments_poli->nudge = ''.add_query_arg( 'wc-api', 'poli_nudge', home_url('/') )."&currency=".$currency;
			$this->upayments_poli->success = $this->get_return_url( $order );
            $this->upayments_poli->failure = ''.get_permalink(wc_get_page_id('cart'));
            $this->upayments_poli->cancelled = ''.get_permalink(wc_get_page_id('cart'));
			$this->upayments_poli->homepage=$baselink;
			
			$config_currency=strtolower( $currency );
			$this->set_credentials( $config_currency );

			// Bank reference details
			$values=array();
			$values['bank_part']=$reference;
			$values['bank_refformat']="NONE";
			//$multicurrency=$this->get_option('multicurrency');
			//if( $config_currency == "nzd" ){
			$values['bank_part']=$this->get_bank_part_value( $order, $config_currency, "particulars", $reference );
			$values['bank_code']=$this->get_bank_part_value( $order, $config_currency, "code", $reference );
			$values['bank_ref']=$this->get_bank_part_value( $order, $config_currency, "reference", $reference );
			$values['bank_other']=$reference;
			$values['bank_refformat']="AUTO";
			//}
			$gateway_data=$this->upayments_poli->generate_bankparts( $values );

			// Initiate
			list( $reference, $url, $errortext, $transactionToken, $gateway_code )=$this->upayments_poli->initiate( $reference, "", $currency, $amount, array( 'WooCommerce' ), 
				$gateway_data['bank_part'], $gateway_data['bank_code'], $gateway_data['bank_ref'], $this->debug, $gateway_data['bank_refformat'], $gateway_data['bank_other'], $this->get_use_uat() );

			if( !$this->debug ){
				if( $errortext != "" ){
					wc_add_notice( $errortext, "error" );
					return array( 'result' 	=> 'failure' );
				} else {			
					// Set transaction ID
					$transaction_id="POLIID-".$transactionToken;
					$order->set_transaction_id( $transaction_id );
					return array(
						'result' 	=> 'success',
						'redirect'	=> $url
					);
				}
			}
        }
        public function result() {
            global $woocommerce;

			$config_currency=strtolower( $_REQUEST['currency'] );
			$this->set_credentials( $config_currency );
			list( $success, $pay_reference, $currency, $amount, $gateway_status, $reference, $merchant_data, $gateway_code )=$this->upayments_poli->nudge( $this->debug, $this->get_use_uat() );
			if (function_exists('wc_sequential_order_numbers')) {
				$orderid = wc_sequential_order_numbers()->find_order_by_order_number( (int)wc_clean($reference) );
			} else {
				$orderid = $reference;
			}					
			$order = new WC_Order(''.$orderid);
			$log_note=true;
			$redirect_url = site_url();
			if($orderid!=""||$orderid!=null){
				if( $success ){
					$order->payment_complete();
				} else {
					$new_status="cancelled";
					if( $order->get_status() == "pending" ){
						$order->update_status( $new_status );
					}
				}
				if( $log_note )	$order->add_order_note( __('POLi Nudge Received. Status: '.$gateway_status.' POLi ID: '.$pay_reference, 'woothemes') );
				$redirect_url = $order->get_checkout_order_received_url();
			}
			wp_safe_redirect($redirect_url);
            exit();
        }
		public static function plugin_url() {
			return untrailingslashit( plugins_url( '/', __FILE__ ) );
		}
		public static function plugin_abspath() {
			return trailingslashit( plugin_dir_path( __FILE__ ) );
		}
		static public function poli_block_support() {
			if ( class_exists( 'Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType' ) ) {
				require_once 'blocks/class-wc-gateway-poli.php';
				add_action(
					'woocommerce_blocks_payment_method_type_registration',
					function( Automattic\WooCommerce\Blocks\Payments\PaymentMethodRegistry $payment_method_registry ) {
						$payment_method_registry->register( new WC_Gateway_POLi_Blocks_Support );
					}
				);
			}
		}
	
    }

	function poli_getoptional_setting( $setting ){
		$poli_settings=get_option('woocommerce_poli_settings');
		if( isset( $poli_settings[$setting] ) ){
			return $poli_settings[$setting];
		} else {
			return "";
		}
	}
	function poli_set_setting( $setting, $value ){
		$poli_settings=get_option('woocommerce_poli_settings');
		$poli_settings[$setting]=$value;
		update_option('woocommerce_poli_settings',$poli_settings);
	}

	/* only allow payments for specific options */
	function poli_filter_gateways($gateways){
		global $woocommerce;
		$poli_settings=get_option('woocommerce_poli_settings');
		if( !is_admin() ){
			$authenticationcode=$merchantcode="";
			$config_currency = strtolower( get_woocommerce_currency() );
			if( $config_currency == "nzd" ){ // NZ only
				$authenticationcode_key = $config_currency.'authenticationcode';
				$merchantcode_key = $config_currency.'merchantcode';
				$authenticationcode = isset($poli_settings[$authenticationcode_key]) ? $poli_settings[$authenticationcode_key] : "";
				$merchantcode = isset($poli_settings[$merchantcode_key]) ? $poli_settings[$merchantcode_key] : "";
				
				$useuat = poli_getoptional_setting( "useuat" );
				if( $useuat == "yes" ){
					if( isset( $poli_settings['uat'.$config_currency.'authenticationcode'] ) ) $authenticationcode = $poli_settings['uat'.$config_currency.'authenticationcode'];
					if( isset( $poli_settings['uat'.$config_currency.'merchantcode'] ) ) $merchantcode = $poli_settings['uat'.$config_currency.'merchantcode'];
				}
/*
				$authenticationcode = poli_getoptional_setting( "authenticationcode" );
				$merchantcode = poli_getoptional_setting( "merchantcode" );
				$useuat = poli_getoptional_setting( "useuat" );
				if( $useuat == "yes" ){
					if( isset( $poli_settings['uat'.$config_currency.'authenticationcode'] ) ) $authenticationcode = $poli_settings['uat'.$config_currency.'authenticationcode'];
					if( isset( $poli_settings['uat'.$config_currency.'merchantcode'] ) ) $merchantcode = $poli_settings['uat'.$config_currency.'merchantcode'];
				} else {
					$multicurrency = poli_getoptional_setting( 'multicurrency' );
					if( $multicurrency == "yes" ){
						if( isset( $poli_settings[$config_currency.'authenticationcode'] ) )$authenticationcode = $poli_settings[$config_currency.'authenticationcode'];
						if( isset( $poli_settings[$config_currency.'merchantcode'] ) ) $merchantcode = $poli_settings[$config_currency.'merchantcode'];
					}
				}
*/				
			}
			if( $authenticationcode == "" or $merchantcode == "" ) unset($gateways['poli']);
		}
		return $gateways;
	}
	add_filter('woocommerce_available_payment_gateways', 'poli_filter_gateways' ,1 );

	add_action( 'woocommerce_blocks_loaded', array( 'WC_Gateway_POLi', 'poli_block_support' ) );

/*
    function poli_woocommerce_gateway_icon($icon_html, $gateway_id) {
        if ($gateway_id == 'poli') {
            $icon_html = '<img src="' . plugins_url('', __FILE__) . '/images/poli-icon.png" srcset="
                ' . plugins_url('', __FILE__) . '/images/poli-icon.png 1x,
                ' . plugins_url('', __FILE__) . '/images/poli-icon@2x.png 2x,
                ' . plugins_url('', __FILE__) . '/images/poli-icon@3x.png 3x" width="38" height="24" alt="Pay with POLi">';
        }
        return $icon_html;
    }
    add_filter('woocommerce_gateway_icon', 'poli_woocommerce_gateway_icon', 10, 2);
*/
	
    // Add the Gateway to WooCommerce
    function woocommerce_add_gateway_poli_gateway($methods) {
        $methods[] = 'WC_Gateway_POLi';
        return $methods;
    }

    add_filter('woocommerce_payment_gateways', 'woocommerce_add_gateway_poli_gateway' );

    new WC_Gateway_POLi;
	
	function polipayments_settlinks_link($links) { 
		$settings_link = '<a href="admin.php?page=wc-settings&tab=checkout&section=poli">Settings</a>'; 
		array_unshift($links, $settings_link); 
		return $links; 
	}
	$plugin = plugin_basename(__FILE__); 
	add_filter("plugin_action_links_$plugin", 'polipayments_settlinks_link' );
	
}
?>