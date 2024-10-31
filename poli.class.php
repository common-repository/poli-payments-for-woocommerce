<?php

class uframework_upayments_lib {
	public $splitter="|";
	public function TestingInfo( $text ){
		echo "<div style=\"border: 3px solid red; padding: 1em; background-color: white; color: black; margin: 1em;\">
			<i>This box is only appearing because the payment system on the website is running in debug</i><br><br>".$text."
			</div>";
	}
	public function Pack( $data ){
		$data_packed=implode( $this->splitter, $data );
		return $data_packed;
	}
	public function Unpack( $data_packed ){
		$data=explode( $this->splitter, $data_packed );
		return $data;
	}
	public function MakeTransactionRef( $reference ){
		$reference = strtolower( preg_replace('/[^a-zA-Z0-9\.@\- ]/', '', trim($reference) ) );
		return $reference;
	}
}

class upayments_poli_class extends uframework_upayments_lib {
	var $nudge="";
	var $homepage="";
	var $success="";
	var $failure="";
	var $cancelled="";
	var $authcode="";
	var $merchantcode="";
	var $notify_email="";
	var $max_bank_part_length;

	public function __construct() {
		$this->max_bank_part_length=18;
	}
	public function get_bankparts( ){
		return array( 'particulars', 'code', 'reference' );
	}
	public function generate_bankparts( $values ){
		$gateway_data=array();
		if( isset( $values['bank_part'] ) ) $gateway_data['bank_part']=$values['bank_part'];
		if( isset( $values['bank_code'] ) ) $gateway_data['bank_code']=$values['bank_code'];
		if( isset( $values['bank_ref'] ) ) $gateway_data['bank_ref']=$values['bank_ref'];
		if( isset( $values['bank_refformat'] ) ) $gateway_data['bank_refformat']=$values['bank_refformat'];
		if( isset( $values['bank_other'] ) ) $gateway_data['bank_other']=$values['bank_other'];
		if( !isset( $gateway_data['bank_part'] ) ) $gateway_data['bank_part']="";
		if( !isset( $gateway_data['bank_code'] ) ) $gateway_data['bank_code']="";
		if( !isset( $gateway_data['bank_ref'] ) ) $gateway_data['bank_ref']="";
		if( !isset( $gateway_data['bank_refformat'] ) ) $gateway_data['bank_refformat']="";
		if( !isset( $gateway_data['bank_other'] ) ) $gateway_data['bank_other']="";
		return $gateway_data;
	}

	function escape_field( $text, $length ){
		$clean_code = substr( preg_replace('/[^a-zA-Z0-9\.@\- ]/', '', trim($text) ), 0, $length );
		return $clean_code;
	}
	function escape_html( $text ){
		return urlencode( $text );
	}
	function poli_encrypt($data_input, $key='g84hv8trhe84d'){     
		$td = mcrypt_module_open('cast-256', '', 'ecb', '');
		$iv = mcrypt_create_iv (mcrypt_enc_get_iv_size($td), MCRYPT_RAND);
		mcrypt_generic_init($td, $key, $iv);
		$encrypted_data = mcrypt_generic($td, $data_input);
		mcrypt_generic_deinit($td);
		mcrypt_module_close($td);
		$encoded_64=base64_encode($encrypted_data);
		return $encoded_64;
	}   
	function poli_decrypt($encoded_64, $key='g84hv8trhe84d'){
		$decoded_64=base64_decode($encoded_64);
		$td = mcrypt_module_open('cast-256', '', 'ecb', '');
		$iv = mcrypt_create_iv (mcrypt_enc_get_iv_size($td), MCRYPT_RAND);
		mcrypt_generic_init($td, $key, $iv);
		$decrypted_data = mdecrypt_generic($td, $decoded_64);
		mcrypt_generic_deinit($td);
		mcrypt_module_close($td);
	
		$last_char=substr($decrypted_data,-1);
		$p=strpos( $decrypted_data, $last_char );
		$decrypted_data=substr($decrypted_data,0,$p);
		return $decrypted_data;
	}
	function nudge( $debug=false, $use_uat=false ){
		if(isset($_POST['Token'])){
			$token=$_POST['Token'];
		} else {
			$token=$_GET['token'];
		}
		$auth = base64_encode( $this->merchantcode.":".$this->authcode );
		$header = array();
		$header[] = 'Authorization: Basic '.$auth;
		if( $use_uat ){
			$poli_url="https://poliapi.uat3.paywithpoli.com/api/Transaction/GetTransaction?token=".urlencode($token);
		} else {
			$poli_url="https://poliapi.apac.paywithpoli.com/api/Transaction/GetTransaction?token=".urlencode($token);
		}
		$ch = curl_init( $poli_url );
		curl_setopt( $ch, CURLOPT_HTTPHEADER, $header);
		curl_setopt( $ch, CURLOPT_HEADER, 0);
		curl_setopt( $ch, CURLOPT_SSL_VERIFYHOST, 0);
		curl_setopt( $ch, CURLOPT_SSL_VERIFYPEER, 0);
		curl_setopt( $ch, CURLOPT_POST, 0);
		curl_setopt( $ch, CURLOPT_FOLLOWLOCATION, 0);
		$referrer = "";
		curl_setopt($ch, CURLOPT_REFERER, $referrer);
		curl_setopt( $ch, CURLOPT_RETURNTRANSFER, 1);
		$response = curl_exec( $ch );
		curl_close ($ch);
		$response_json = json_decode( $response, true );

		if( $debug ){
			$debug_html="Server : ".$poli_url." ".$this->merchantcode.":".$this->authcode."<BR>";
			$debug_html.="Response JSON : ".print_r( $response_json, true );
			$this->TestingInfo( $debug_html );
		}

		$gateway_status=$response_json['TransactionStatusCode'];
		$pay_reference=$response_json['TransactionRefNo'];
		$bank=$response_json['FinancialInstitutionCode'];
		$currency=$response_json['CurrencyCode'];
		$amount=$response_json['AmountPaid'];
		$gateway_code=$response_json['TransactionStatusCode'];
		$merchant_data=explode( "|", $response_json['MerchantReferenceData'] );
		$reference=$merchant_data[0];
		unset( $merchant_data[0] );
		$merchant_data = array_values($merchant_data);
		if( $gateway_status == "Completed" ){
			$success = true;
			$gateway_status="The payer has used ".$bank." for payment. Funds transferred between banks are subject to the normal bank settlement processes that apply to Internet Banking payments.";
		} else {
			$success = false;
			if( $gateway_status == "ReceiptUnverified" ){
				$gateway_status="There was a problem processing the payment - however - please check your bank account to make sure the funds have not been transferred. You will need to then contact us with proof of payment (ReceiptUnverified)";
			}
		}
		return array( $success, $pay_reference, $currency, $amount, $gateway_status, $reference, $merchant_data, $gateway_code );
	}
	function initiate( $reference, $name, $currency, $grandtotal, $extra_merchant_data=array(), $bank_part="", $bank_code="", $bank_ref="", $debug=false, $bank_refformat="", $bank_other="", $use_uat=false ){
		$today = date("Y-m-d");
		$time = date("h:i:s");
		$date= $today."T".$time;

		// Internal data
		$merchant_data=$reference."|";
		if( count( $extra_merchant_data ) > 0 ) $merchant_data.=implode( "|", $extra_merchant_data );
		$merchant_data=substr( $merchant_data, 0, 2000 );
		$merchant_data=str_replace( "\n", " ", $merchant_data );
		$merchant_data=str_replace( "\r", " ", $merchant_data );
		$merchant_data=str_replace( "&", "", $merchant_data );
		// $merchant_data=$this->escape_html( $merchant_data );

		// Reference field for bank reconsilation
		$merchant_ref_ar=array();
		$reference_format=$bank_refformat;
		if( $currency == "" ) $currency="NZD";
		if( $currency == "AUD" ){
			// Australia
			$reference_format="";
			$merchant_ref=$this->escape_field( $reference, $this->max_bank_part_length );

		} else {
			// NZD
			if( $reference_format == "NONE" ){
				$reference_format="";
				$merchant_ref_ar[0]=$this->escape_field($reference, $this->max_bank_part_length);
			
			} else if( $bank_part == "" and $bank_code == "" and $bank_ref == "" and $reference_format == "" ){
				// No specified bank data
				$reference_format="4";
				$merchant_ref_ar[0]=$this->escape_field($reference, $this->max_bank_part_length);
				$merchant_ref_ar[1]=$this->escape_field($name, $this->max_bank_part_length);
				$merchant_ref_ar[2]="";
				$merchant_ref_ar[3]="";
	
			} else {
				// Use supplied bank data
				$merchant_ref_ar[0]=$this->escape_field($bank_part, $this->max_bank_part_length);
				$merchant_ref_ar[1]=$this->escape_field($bank_code, $this->max_bank_part_length);
				$merchant_ref_ar[2]=$this->escape_field($bank_ref, $this->max_bank_part_length);
				$merchant_ref_ar[3]=$this->escape_field($bank_other, $this->max_bank_part_length);
				$reference_format="1";
				if( $bank_part == "" ) $reference_format="2";
				if( $bank_code == "" and $bank_part != "" ) $reference_format="3";
				if( $bank_ref == "" and $bank_part != ""  and $bank_code != ""  ) $reference_format="4";
			}
			
			// Create merchant ref string
			$merchant_ref=implode( "|", $merchant_ref_ar );
		}
				
		// Initiate
		if( $this->cancelled == "" ) $this->cancelled=$this->homepage;
		$auth = base64_encode( $this->merchantcode.":".$this->authcode);
		$header = array();
		$header[] = 'Content-Type: application/json';
		$header[] = 'Authorization: Basic '.$auth;
		$transaction_json = '{
			"Amount":"'.$grandtotal.'",
			"CurrencyCode":"'.$currency.'",
			"MerchantReference":"'.$merchant_ref.'",
			"MerchantData":"'.$merchant_data.'",
			"MerchantReferenceFormat":"'.$reference_format.'",
			"MerchantHomepageURL":"'.$this->homepage.'",
			"SuccessURL":"'.$this->success.'",
			"FailureURL":"'.$this->failure.'",
			"CancellationURL":"'.$this->cancelled.'",
			"NotificationURL":"'.$this->nudge.'"
		}';
		if( $use_uat ){
			$poli_url="https://poliapi.uat3.paywithpoli.com/api/Transaction/Initiate";
		} else {
			$poli_url="https://poliapi.apac.paywithpoli.com/api/Transaction/Initiate";
		}
		$ch = curl_init( $poli_url );
		curl_setopt( $ch, CURLOPT_HTTPHEADER, $header);
		curl_setopt( $ch, CURLOPT_HEADER, 0);
		curl_setopt( $ch, CURLOPT_POST, 1);
		curl_setopt( $ch, CURLOPT_POSTFIELDS, $transaction_json );
		curl_setopt( $ch, CURLOPT_FOLLOWLOCATION, 0);
		curl_setopt( $ch, CURLOPT_RETURNTRANSFER, 1);
		curl_setopt( $ch, CURLOPT_SSL_VERIFYHOST, false );
		curl_setopt( $ch, CURLOPT_SSL_VERIFYPEER, false );
		$response = curl_exec( $ch );
		curl_close ($ch);
		$response_json = json_decode($response, true);
		$url=$errortext=$gateway_code=$transactionToken="";
		if( isset( $response_json['NavigateURL'] ) and $response_json['NavigateURL'] != "" ){
			$url=$response_json['NavigateURL'];
		} else {
			$errortext="There was an error processing your payment. ";
		}
		if( isset( $response_json['ErrorMessage'] ) and $response_json['ErrorMessage'] != "" ) $errortext.=" ".$response_json['ErrorMessage'];
		if( isset( $response_json['Message'] ) and $response_json['Message'] != "" ) $errortext.=" ".$response_json['Message'];
		if( isset( $response_json['TransactionRefNo'] ) and $response_json['TransactionRefNo'] != "" ) $transactionToken=$response_json['TransactionRefNo'];
		if( isset( $response_json['ErrorCode'] ) ) $gateway_code=$response_json['ErrorCode'];

		if( $debug ){
			$debug_html="Server : ".$poli_url." ".$this->merchantcode.":".$this->authcode."<BR>";
			$debug_html.="Transaction : ".$transaction_json."<BR>";
			$debug_html.="Response : ".$response;
			$this->TestingInfo( $debug_html );
		}
		return array( $reference, $url, $errortext, $transactionToken, $gateway_code );		
	}
}

?>