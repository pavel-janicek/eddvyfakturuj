<?php
if (!class_exists('Clvr_Vyfakturuj')){

	/**
	 *
	 */
	class Clvr_Vyfakturuj
	{
    const RECCURENCE_META_KEY = "_edd_vyfakturuj_prodtype";
		const INVOICE_ID_META_KEY = "_vyfakturuj_faktura_id";
		const CUSTOMER_ID_META_KEY = '_vyfakturuj_client_id';
		const VAT_TYPE_KEY = '_vyfakturuj_vat_type';
		protected $context = 'eddvyfakturuj';
		private $client;
		function __construct($experimental=false)
		{

        require 'class_clvr_vyfakturuj_settings.php';
		require 'class_edd_customer_meta_wrapper.php';
		require 'class_edd_order_meta_wrapper.php';
        $this->settings = new Clvr_Vyfakturuj_Settings($experimental);
        //add_filter( 'edd_payment_meta', array($this,'store_payment_meta') );
        add_filter( 'edd_purchase_form_required_fields', array($this,'purchase_form_required_fields') );
        add_action( 'edd_payment_receipt_after', array($this, 'addInvoiceToThankYou'), 10 );
		add_action( 'edd_complete_purchase', array($this, 'setInvoicePaid') );
		add_action( 'init', array($this,'listener'));
		add_action( 'edd_insert_payment', array($this,'maybe_create_proforma'));
		remove_action('edd_purchase_form_after_cc_form','edd_checkout_tax_fields',999);
		add_filter( 'edd_require_billing_address', '__return_false', 9999 );
		add_filter( 'edd_payment_gateways', array($this,'register_gateway') );
		add_action('edd_'.$this->context.'_cc_form', array($this,'cc_form'));
		add_action( 'edd_gateway_'.$this->context, array($this,'process_payment') );
        //$this->add_email_tags();
		add_action('edd_add_email_tags',array($this,'add_email_tags'));
        if ($experimental){
          add_action('init', array($this,'debug_options'));

        }



		}

    public function isInitialized(){
      global $edd_options;
      return (isset($edd_options['eddvyfakturuj_login'])) and (isset($edd_options['eddvyfakturuj_token']));
	}

	public function register_gateway($gateways){
		if ($this->isInitialized()){
			$gateways[$this->context] =  array( 'admin_label' => 'Vyfakturuj.cz', 'checkout_label' => __( 'Zaplatit kartou nebo bankovním převodem', $this->context ) );
		}
		return $gateways;
	}

	public function cc_form() {
		return;
	}

	public function getClient(){
		if (!$this->isInitialized()){
			return;
		}
		if (!empty($this->client)){
			return $this->client;
		}
		global $edd_options;
		$this->client = new VyfakturujAPI($edd_options['eddvyfakturuj_login'],$edd_options['eddvyfakturuj_token']);
		return $this->client;

	}

	public function getPaymentIDs(){
		if (!$this->isInitialized()){
			return [-1 => 'Prosím nejprve aktivujte propojení s Vyfakturuj.cz'];
		}
		if(isset($edd_options[$this->context.'_id_platby'])){
			return [$edd_options[$this->context.'_id_platby'] => 'Platba jiz zvolena'];

		}
		$payment_ids = [];
		$payment_methods = $this->getClient()->getSettings_paymentMethods();
		foreach ($payment_methods as $payment_method) {
			$payment_ids[$payment_method['id_payment_method']] = $payment_method['name'];
		}
		return $payment_ids;
	}

	public function process_payment($purchase_data){
		$payment_id = edd_insert_payment( $purchase_data );
		$proforma = isset($edd_options[$this->context.'_proforma']) && !empty($edd_options[$this->context.'_proforma']);
		$invoice =$this->getInvoice($payment_id,$proforma);
		edd_empty_cart();
		$url = $invoice['url_online_payment'];
		wp_redirect($url);
		exit;

	}

	public function listener(){
		if (isset($_REQUEST['edd-listener']) && ($_REQUEST['edd-listener'] =='eddvyfakturuj') && isset($_REQUEST['paymentid'])){
			echo "firing pingback";
			$this->pingback($_REQUEST['paymentid']);
		}
	}

	public function pingback($payment_id){
		edd_update_payment_status( $payment_id, 'publish' );

	}

	public function maybe_create_proforma($payment_id){
		global $edd_options;
		$payment = new EDD_Payment($payment_id);
		
		$this->store_payment_meta($payment);
		if (isset($edd_options['eddvyfakturuj_proforma']) && !empty($edd_options['eddvyfakturuj_proforma'])){
			return $this->getInvoice($payment_id,true);
		}else{
			return false;
		}
		
	}

    function all_extra_fields() {
      $eddvyfakturuj_fields[] = "edd_firma";
      $eddvyfakturuj_fields[] = "edd_stat";
      $eddvyfakturuj_fields[] = "edd_ic";
      $eddvyfakturuj_fields[] = "edd_dic";
      $eddvyfakturuj_fields[] = "edd_ulice";
      $eddvyfakturuj_fields[] = "edd_mesto";
      $eddvyfakturuj_fields[] = "edd_psc";
      return $eddvyfakturuj_fields;
    }

    public function store_payment_meta($payment){
      $payment_meta_wrapper = new Clvr_EDD_Order_Meta_wrapper();	
      $extra_fields = $this->all_extra_fields();
      
      foreach ($extra_fields as $key => $extra_field){
    		if(empty($payment_meta[$extra_field])){
    			//$payment_meta[$extra_field] = isset( $_POST[$extra_field] ) ? sanitize_text_field( $_POST[$extra_field] ) : '';

    			//$payment->update_meta($extra_field,isset( $_POST[$extra_field] ) ? sanitize_text_field( $_POST[$extra_field] ) : '');
    			$payment_meta_wrapper->add_meta($payment->ID,$extra_field,isset( $_POST[$extra_field] ) ? sanitize_text_field( $_POST[$extra_field] ) : '');
    		}
      }

      $payment->save();
    }

		public function getVyfakturujCustomer($payment_id){
			global $edd_options;
			$payment      = new EDD_Payment( $payment_id );
			$edd_customer_id = $payment->customer_id;
			$wrapper = new Clvr_EDD_Customer_Meta_wrapper();
			$payment_meta_wrapper = new Clvr_EDD_Order_Meta_wrapper();
			$vyfakturuj_customer_id = $wrapper->get_meta($edd_customer_id,self::CUSTOMER_ID_META_KEY);
			if (!empty($vyfakturuj_customer_id)){
				return $vyfakturuj_customer_id;
			}
			$payment_meta   = $payment->get_meta();
			$user_info = edd_get_payment_meta_user_info( $payment_id );
			$customer_data = [
				'IC' =>  $payment_meta_wrapper->get_meta($payment_id,'edd_ic'),
				'name' => $this->getCustomerName($payment_meta_wrapper->get_meta($payment_id,'edd_firma'),$payment_meta['user_info']['first_name'],$payment_meta['user_info']['last_name']),
    		'note' => 'Kontakt vytvořený přes plugin EDD Vyfakturuj od cleverstart.cz ',
    		'company' => $payment_meta_wrapper->get_meta($payment_id,'edd_firma'),
    		'street' => $payment_meta_wrapper->get_meta($payment_id,'edd_ulice'),
    		'city' => $payment_meta_wrapper->get_meta($payment_id,'edd_mesto'),
    		'zip' => $payment_meta_wrapper->get_meta($payment_id,'edd_psc'),
    		'country' => $payment_meta_wrapper->get_meta($payment_id,'edd_stat'),
    		'mail_to' => $user_info['email'],
			];
			$vyfakturuj_api = new VyfakturujAPI($edd_options['eddvyfakturuj_login'],$edd_options['eddvyfakturuj_token']);
			$vyfakturuj_customer = $vyfakturuj_api->createContact($customer_data);
			$vyfakturuj_customer_id = $vyfakturuj_customer['id'];
			$result = $wrapper->add_meta($edd_customer_id,self::CUSTOMER_ID_META_KEY,$vyfakturuj_customer_id);
			return $vyfakturuj_customer_id;


		}



    public function add_email_tags($payment_id){
      edd_add_email_tag( 'eddvyfakturuj_firma', 'Firma zákazníka', array($this,'email_tag_firma') );
      edd_add_email_tag( 'eddvyfakturuj_stat', 'Stát zákazníka', array($this, 'email_tag_stat') );
      edd_add_email_tag( 'eddvyfakturuj_ic', 'IČ zákazníka', array($this, 'email_tag_ic') );
      edd_add_email_tag( 'eddvyfakturuj_dic', 'DIČ zákazníka', array($this, 'email_tag_dic') );
      edd_add_email_tag( 'eddvyfakturuj_ulice', 'Ulice a číslo popisné zákazníka', array($this,'email_tag_ulice') );
      edd_add_email_tag( 'eddvyfakturuj_mesto', 'Město zákazníka', array($this,'email_tag_mesto') );
      edd_add_email_tag( 'eddvyfakturuj_psc', 'PSČ zákazníka', array($this,'email_tag_psc') );
      edd_add_email_tag( 'eddvyfakturuj_polozky', 'Položky v košíku', array($this,'email_tag_polozky') );
      edd_add_email_tag( 'eddvyfakturuj_datum', 'Datum nákupu', array($this,'email_tag_datum') );
      edd_add_email_tag( 'eddvyfakturuj_faktura_link', 'Neformátovaný odkaz na fakturu', array($this,'getInvoiceLink') );
	  edd_add_email_tag( 'eddvyfakturuj_faktura_invoice', 'Vloží odkaz s textem Fakturu si stáhněte zde', array($this,'getInvoiceDownload') );
	  add_filter( 'edd_email_preview_template_tags', array($this,'email_preview'));
	}


	public function email_preview($message){
		$download_list = '<ul>';
		$download_list .= '<li>' . __( 'Sample Product Title', 'easy-digital-downloads' ) . '<br />';
		$download_list .= '<div>';
		$download_list .=  __( 'Sample Download File Name', 'easy-digital-downloads' ) . ' - <small>' . __( 'Optional notes about this download.', 'easy-digital-downloads' ) . '</small>';
		$download_list .= '</div>';
		$download_list .= '</li>';
		$download_list .= '</ul>';
		$link = 'https://www.vyfakturuj.cz/print/document/pdf/1305897/81518f/download/';
		$html = "<a href=\"" .$link. "\">Fakturu si stáhněte zde</a>";

		$message = str_replace( '{'.$this->context.'_firma}', 'Ukázková firma s.r.o.', $message );
		$message = str_replace( '{'.$this->context.'_stat}', 'Česko', $message );
		$message = str_replace( '{'.$this->context.'_ic}', '25596641', $message );
		$message = str_replace( '{'.$this->context.'_dic}', 'CZ25596641', $message );
		$message = str_replace( '{'.$this->context.'_ulice}', 'Ukázková ulice 1', $message );
		$message = str_replace( '{'.$this->context.'_mesto}', 'Ukázkové město', $message );
		$message = str_replace( '{'.$this->context.'_psc}', '11150', $message );
		$message = str_replace( '{'.$this->context.'_polozky}', $download_list, $message );
		$message = str_replace( '{'.$this->context.'_datum}', '21.12.2012', $message );
		$message = str_replace( '{'.$this->context.'_faktura_link}', $link, $message );
		$message = str_replace( '{'.$this->context.'_faktura_invoice}', $html, $message );
		//$message = apply_filters( 'edd_email_preview_template_tags', $message );

		return apply_filters( 'edd_email_template_wpautop', true ) ? wpautop( $message ) : $message;
	}

    /**
     * The {Firma} email tag
     */
    public function email_tag_firma( $payment_id ) {
    	$wrapper = new Clvr_EDD_Order_Meta_wrapper();
    	return $wrapper->get_meta($payment_id,'edd_firma');
    }

    /**
     * The {Stat} email tag
     */
    public function email_tag_stat( $payment_id ) {
    	$wrapper = new Clvr_EDD_Order_Meta_wrapper();
    	return $wrapper->get_meta($payment_id,'edd_stat');
    }

    /**
     * The {IC} email tag
     */
    public function email_tag_ic( $payment_id ) {
    	$wrapper = new Clvr_EDD_Order_Meta_wrapper();
    	return $wrapper->get_meta($payment_id,'edd_ic');
    }

    /**
     * The {DIC} email tag
     */
    public function email_tag_dic( $payment_id ) {
    	$wrapper = new Clvr_EDD_Order_Meta_wrapper();
    	return $wrapper->get_meta($payment_id,'edd_dic');
    }

    /**
     * The {Ulice} email tag
     */
    public function email_tag_ulice( $payment_id ) {
    	$wrapper = new Clvr_EDD_Order_Meta_wrapper();
    	return $wrapper->get_meta($payment_id,'edd_ulice');
    }

    /**
     * The {Mesto} email tag
     */
    public function email_tag_mesto( $payment_id ) {
    	$wrapper = new Clvr_EDD_Order_Meta_wrapper();
    	return $wrapper->get_meta($payment_id,'edd_mesto');
    }

    /**
     * The {PSC} email tag
     */
    public function email_tag_psc( $payment_id ) {
    	$wrapper = new Clvr_EDD_Order_Meta_wrapper();
    	return $wrapper->get_meta($payment_id,'edd_psc');
    }

    public function email_tag_polozky($payment_id){
       $decissions = $this->format_cart_items($payment_id);
       return $decissions[1];
    }

    public function email_tag_datum($payment_id){
      $payment_meta = edd_get_payment_meta( $payment_id );
      $date = DateTime::createFromFormat('Y-m-d G:i:s', $payment_meta['date']);
      return $date->format('d.m. Y');
    }

    /*
    * TODO: Needs refactoring
    *
    */
    private function format_cart_items($payment_id){
        if (function_exists('get_home_path')){
        	$path = get_home_path();
        	$path .= "wp-content/plugins/easy-digital-downloads/includes/payments/class-edd-payment.php";
        }else{
        	$path = dirname(__FILE__) . "/../easy-digital-downloads/includes/payments/class-edd-payment.php";
        }
    if (!class_exists('EDD_Payment')){
    	require_once($path);
    }    
		
		global $edd_options;

    	$cart_items = edd_get_payment_meta_cart_details( $payment_id );
      $payment = new EDD_Payment( $payment_id );
    	$payment_data  = $payment->get_meta();
    	$download_list = '<ul>';
    	$cart_items    = $payment->cart_details;
    	$email         = $payment->email;
        	if ( $cart_items ) {
    		$show_names = apply_filters( 'edd_email_show_names', true );
    		$show_links = apply_filters( 'edd_email_show_links', true );
            $i = 0;
    		foreach ( $cart_items as $item ) {
    			if ( edd_use_skus() ) {
    				$sku = edd_get_download_sku( $item['id'] );
    			}

    				$quantity = $item['quantity'];
                    $pavelCart[$i]['quantity'] = $item['quantity'];

            $price_id = edd_get_cart_item_price_id( $item );
    			//if ( $show_names ) {
    			if ( false ) {
    				$title = '<strong>' . get_the_title( $item['id'] ) . '</strong>';

    				if ( ! empty( $quantity ) && $quantity > 1 ) {
    					$title .= "&nbsp;&ndash;&nbsp;" . __( 'Quantity', 'easy-digital-downloads' ) . ': ' . $quantity;
    				}
    				if ( ! empty( $sku ) ) {
    					$title .= "&nbsp;&ndash;&nbsp;" . __( 'SKU', 'easy-digital-downloads' ) . ': ' . $sku;
    				}
    				if ( $price_id !== null ) {
    					$title .= "&nbsp;&ndash;&nbsp;" . edd_get_price_option_name( $item['id'], $price_id, $payment_id );

    				}
    				$download_list .= '<li>' . apply_filters( 'edd_email_receipt_download_title', $title, $item, $price_id, $payment_id ) . '<br/>';
    			}
          			$price_id = edd_get_cart_item_price_id( $item );
                    $pavelCart[$i]['price_id']  = edd_get_cart_item_price_id( $item );
    			if ( $show_names ) {
    				$title = '<strong>' . get_the_title( $item['id'] ) . '</strong>';
                    $pavelCart[$i]['title'] = get_the_title( $item['id'] );
    				if ( ! empty( $quantity )  ) {
    					$title .= "&nbsp;&ndash;&nbsp;" . __( 'Množství', 'easy-digital-downloads' ) . ': ' . $quantity ." ks";
    				}
    				if ( ! empty( $sku ) ) {
    					$title .= "&nbsp;&ndash;&nbsp;" . __( 'SKU', 'easy-digital-downloads' ) . ': ' . $sku;
    				}
    				if ( $price_id !== null ) {
    					$title .= "&nbsp;&ndash;&nbsp;" . edd_get_price_option_name( $item['id'], $price_id, $payment_id );
                        $pavelCart[$i]['price_option_name'] = edd_get_price_option_name( $item['id'], $price_id, $payment_id );
    				}
                    $title .="&nbsp;&ndash;&nbsp;" . "Jednotková cena: " .$item["price"]. " " .$edd_options['currency'] . "</span>";
                    $pavelCart[$i]['jednotkova_cena'] = $item["price"];
                    //$title .="<span>; " .$item["quantity"]. " ks";
                    $i++;
    				$download_list .= '<li>' . apply_filters( 'edd_email_receipt_download_title', $title, $item, $price_id, $payment_id ) . '<br/>';
    			}
    			$files = edd_get_download_files( $item['id'], $price_id );
    			if ( ! empty( $files ) ) {
    				foreach ( $files as $filekey => $file ) {
    					if ( $show_links ) {
    						$download_list .= '<div>';
    							$file_url = edd_get_download_file_url( $payment_data['key'], $email, $filekey, $item['id'], $price_id );
    							$download_list .= '<a href="' . esc_url( $file_url ) . '">' . edd_get_file_name( $file ) . '</a>';
    							$download_list .= '</div>';
    					} else {
    						$download_list .= '<div>';
    							$download_list .= edd_get_file_name( $file );
    						$download_list .= '</div>';
    					}
    				}
    			} elseif ( edd_is_bundled_product( $item['id'] ) ) {
    				$bundled_products = apply_filters( 'edd_email_tag_bundled_products', edd_get_bundled_products( $item['id'] ), $item, $payment_id, 'download_list' );
    				foreach ( $bundled_products as $bundle_item ) {
    					$download_list .= '<div class="edd_bundled_product"><strong>' . get_the_title( $bundle_item ) . '</strong></div>';
    					$files = edd_get_download_files( $bundle_item );
    					foreach ( $files as $filekey => $file ) {
    						if ( $show_links ) {
    							$download_list .= '<div>';
    							$file_url = edd_get_download_file_url( $payment_data['key'], $email, $filekey, $bundle_item, $price_id );
    							$download_list .= '<a href="' . esc_url( $file_url ) . '">' . edd_get_file_name( $file ) . '</a>';
    							$download_list .= '</div>';
    						} else {
    							$download_list .= '<div>';
    							$download_list .= edd_get_file_name( $file );
    							$download_list .= '</div>';
    						}
    					}
    				}
    			}
    			if ( '' != edd_get_product_notes( $item['id'] ) ) {
    				$download_list .= ' &mdash; <small>' . edd_get_product_notes( $item['id'] ) . '</small>';
    			}
    			if ( $show_names ) {
    				$download_list .= '</li>';
    			}
    		}
    	}
    	$download_list .= '</ul>';

        $decissions = array(
           '1' =>$download_list,
           '2' =>$pavelCart
        );
    	return $decissions;

    }

    /*returns full invoice */
    public function getInvoice( $payment_id, $proforma=false ) {
      global $edd_options;
      if (!$this->isInitialized()){
        return;
      }
      //include_once 'VyfakturujAPI.class.php';
      $wrapper = new Clvr_EDD_Order_Meta_wrapper();
      $invoice_id = $wrapper->get_meta(  $payment_id, self::INVOICE_ID_META_KEY );
      $vyfakturuj_api = $this->getClient();
      if (!empty($invoice_id)){
        $inv = $vyfakturuj_api->getInvoice($invoice_id);
        return $inv;
      }
      $payment_meta = edd_get_payment_meta( $payment_id );
      $data = $this->prepareDataForInvoice($payment_meta, $payment_id, $proforma);
			if ($this->hasReccuringItems($payment_meta['cart_details'])){
				$this->createTemplate($payment_id,$payment_meta);
			}
      $inv = $vyfakturuj_api->createInvoice($data);
      $wrapper->add_meta( $payment_id, self::INVOICE_ID_META_KEY, $inv['id'] );
      return $inv;
    }

    public function getInvoiceLink($payment_id){

		$inv = $this->maybe_create_proforma($payment_id);
		if ($inv){
			$link = $inv['url_download_pdf'];
    		return $link;
		}else{
			$inv = $this->getInvoice($payment_id);
			$link = $inv['url_download_pdf'];
			return $link;

		}
    }



    public function debug_options(){
      global $edd_options;
      if (isset($_GET['listener']) && ($_GET['listener'] == 'eddvyfakturuj')){
		if (isset($_GET['id'])){
			$payment_id = $_GET['id'];
		}else{
			$payment_id = 73;
		}

		$payment = new EDD_Payment($payment_id);
		$payment_meta = $payment->get_meta();
		$data = $this->prepareDataForInvoice($payment_meta,$payment_id);
		print_r($data);

		echo 'cart <br />';
		print_r($payment_meta['cart_details']);

		echo 'payment meta <br />';
		print_r($payment_meta);

		echo 'invoice <br />';
		print_r($this->getInvoice($payment_id));

		echo 'getting customer <br>';
		$wrapper = new Clvr_EDD_Order_Meta_wrapper();
		print_r($payment_meta['key']);
		print_r($wrapper->getSql($payment_meta['key']));
		//print_r($wrapper->get_payment_id($payment_meta['key']));
		global $wpdb;
		$result= $wpdb->get_results($wrapper->getSql($payment_meta['key']),'ARRAY_A');
		print_r($result);

		exit;



      }
    }

    public function getPaymentMethod($payment_id){
      global $edd_options;
      $payment_method = $edd_options['eddvyfakturuj_typ_platby'];
      $gateway = get_post_meta($payment_id,'_edd_payment_gateway',true);
      $id = 'eddvyfakturuj_gateway_' .$gateway;
      if (empty($edd_options[$id])){
        return $payment_method;
      }else{
        return $edd_options[$id];
      }

    }

    private function prepareDataForInvoice($payment_meta, $payment_id, $proforma = false){
        global $edd_options;
		$payment_method = $this->getPaymentMethod($payment_id);
		$payment = new EDD_Payment($payment_id);

		$type = 1; //invoice

		if ($proforma){
			$type = 4; //proforma invoice
		}

		$flags = 2; //generic flag
		if(  edd_is_cart_taxed() ){
			$flags--;//invoice contains VAT
		}
		$wrapper = new Clvr_EDD_Order_Meta_wrapper();
		$edd_firma = $wrapper->get_meta($payment_id,'edd_firma');

        $opt = array(
          	'type' => $type,
      		'flags' => $flags,
          	'calculate_vat' => $flags,
          	'payment_method' => $payment_method,
      		'items' => $this->prepareInvoiceItems($payment_meta['cart_details']),
          	'action_after_create_send_to_eet' => true,
          	'currency' => $payment_meta['currency'],
			'customer_name' => $this->getCustomerName($edd_firma,$payment_meta['user_info']['first_name'],$payment_meta['user_info']['last_name']),
			'webhook_paid' => get_home_url() .'?edd-listener=eddvyfakturuj&paymentid=' . $payment_id,
			'VS' => $payment->number,
			'mail_to' => $payment->email

        );
      	if(!empty($wrapper->get_meta($payment_id,'edd_ic'))){
      		$opt['customer_IC'] = $wrapper->get_meta($payment_id,'edd_ic');
      	}
      	if(!empty($wrapper->get_meta($payment_id,'edd_dic'))){
      		$opt['customer_DIC'] = $wrapper->get_meta($payment_id,'edd_dic');
      	}
      	if(!empty($wrapper->get_meta($payment_id,'edd_ulice'))){
      		$opt['customer_street'] = $wrapper->get_meta($payment_id,'edd_ulice');
      	}
      	if(!empty($wrapper->get_meta($payment_id,'edd_mesto'))){
      		$opt['customer_city'] = $wrapper->get_meta($payment_id,'edd_mesto');
      	}
      	if(!empty($wrapper->get_meta($payment_id,'edd_psc'))){
      		$opt['customer_zip'] = $wrapper->get_meta($payment_id,'edd_psc');
      	}
      	if(!empty($wrapper->get_meta($payment_id,'edd_stat'))){
      			$opt['customer_country'] = $wrapper->get_meta($payment_id,'edd_stat');
		}
		// if(isset($edd_options[$this->context.'_id_platby'])){
		// 	$opt['id_payment_method'] = $edd_options[$this->context.'_id_platby'];
		// }
		if(  edd_is_cart_taxed() ){
			$opt['calculate_vat'] = 2; // items have final price including VAT
		}
		$opt['total'] = $payment->total;




        return $opt;
      }

			public function hasReccuringItems($cart){
				$hasRecurringItems = false;
				foreach ($cart as $cart_item) {
					$prodtype = get_post_meta($cart_item['id'],self::RECCURENCE_META_KEY,true);
					if ($prodtype=="yearly" or $prodtype=="monthly"){
						$hasRecurringItems = true;
					}
				}
				return $hasRecurringItems;
			}

			public function hasItemsOfReccurence($cart,$recurrence){
				$hasItemsOfReccurence = false;
				foreach ($cart as $cart_item) {
					$prodtype = get_post_meta($cart_item['id'],self::RECCURENCE_META_KEY,true);
					if ($prodtype==$recurrence){
						$hasItemsOfReccurence = true;
					}
				}
				return $hasItemsOfReccurence;

			}

			public function getReccuringItems($cart,$recurrence){
				$foundCartItems = [];
				foreach ($cart as $cart_item){
					$prodtype = get_post_meta($cart_item['id'],self::RECCURENCE_META_KEY,true);
					if ($prodtype == $recurrence){
						$foundCartItems[] = $cart_item;
					}
				}
				return $foundCartItems;
			}

			private function prepareDataForTemplate($payment_id,$payment_meta,$recurrence){
				global $edd_options;
        $payment_method = $this->getPaymentMethod($payment_id);

				$int_recurrence = 1;
				if ($recurrence == 'yearly'){
					$int_recurrence = 12;
				}
				$date = DateTime::createFromFormat('Y-m-d G:i:s', $payment_meta['date']);
				$fullName = get_bloginfo('name') . ' EDD ' . $payment_id . ' ' .$recurrence;

        $opt = array(
					'id_customer' => $this->getVyfakturujCustomer($payment_id), //get customer from Vyfakturuj. If does not exist, create them
					'name' => $this->setProdName($fullName),
          'type' => 2, //reccuring invoice
					'start_type' => 1, //create on exact date
					'end_type' => 1, //without set end
					'date_start' => $date->format('Y-m-d'),
					'repeat_period' => $int_recurrence, //amount in months for reccurence
					'doc_type' => 1, //invoice
          'doc_calculate_vat' => 2,
          'doc_payment_method' => $payment_method,
      		'items' => $this->prepareInvoiceItems($this->getReccuringItems($payment_meta['cart_details'],$recurrence))
        );

        return $opt;
      }


			public function createTemplate($payment_id,$payment_meta){
				global $edd_options;
				$recurrences = ['monthly','yearly'];
				$vyfakturuj_api = new VyfakturujAPI($edd_options['eddvyfakturuj_login'],$edd_options['eddvyfakturuj_token']);
				$templates = [];
				foreach ($recurrences as $recurrence){
					if ($this->hasItemsOfReccurence($payment_meta['cart_details'],$recurrence)){
						$data = $this->prepareDataForTemplate($payment_id,$payment_meta,$recurrence);
						$templates[] = $vyfakturuj_api->createTemplate($data);
					}
				}

				return $templates;

			}

      private function prepareInvoiceItems($cart){


          $items = array();
          foreach($cart as $cart_item){
			$vat_type = get_post_meta($cart_item['id'],self::VAT_TYPE_KEY,true);
			if (empty($vat_type)){
				if (edd_use_taxes()){
					$vat_type = 2; // default vat type
				}else{
					$vat_type = 64; // does not contain VAT
				}
			}

			$total = $cart_item['item_price'] - $cart_item['discount'];
			//$cart_item['item_price'],
            $vyfakturuj_item = array(
              'text' => $cart_item['name'],
              'unit_price' => $total / $cart_item['quantity'],
			  'vat' => $cart_item['tax'],
			  'total_without_vat'=> $total,
			  'quantity' => $cart_item['quantity'],
			  'vat_rate_type' => $vat_type,
			  'total' => $total
            );
            array_push($items,$vyfakturuj_item);
          }
          return $items;

      }

      private function getCustomerName($company, $firstName, $lastName){
        if (empty($company)){
          if (!empty($lastName)){
            return $firstName .' '. $lastName;
          }else{
            return $firstName;
          }
        }
        return $company;
      }

      public function getInvoiceDownload($payment_id){
        $link = $this->getInvoiceLink($payment_id);
        $html = "<a href=\"" .$link. "\">Fakturu si stáhněte zde</a>";
        return $html;
      }

      public function purchase_form_required_fields( $required_fields ) {
      	global $edd_options;
      	if(isset($edd_options['eddvyfakturuj_povinne_prijmeni']) && !empty($edd_options['eddvyfakturuj_povinne_prijmeni'])){
      		$required_fields['edd_last'] = array(
              'error_id' => 'invalid_last_name',
              'error_message' => 'Prosím vyplňte příjmení.'
          );
      	}
          if(isset($edd_options['eddvyfakturuj_povinny_stat']) && !empty($edd_options['eddvyfakturuj_povinny_stat'])){
      			$required_fields['edd_stat'] = array(
              	'error_id' => 'invalid_edd_stat',
              	'error_message' => 'Prosím vyplňte stát.'
          	);
      		}
      		if(isset($edd_options['eddvyfakturuj_povinna_ulice']) && !empty($edd_options['eddvyfakturuj_povinna_ulice'])){
      			$required_fields['edd_ulice'] = array(
              	'error_id' => 'invalid_edd_ulice',
              	'error_message' => 'Prosím vyplňte ulici a číslo popisné.'
          	);
      		}
      		if(isset($edd_options['eddvyfakturuj_povinne_mesto']) && !empty($edd_options['eddvyfakturuj_povinne_mesto'])){
      			$required_fields['edd_mesto'] = array(
              	'error_id' => 'invalid_edd_mesto',
              	'error_message' => 'Prosím vyplňte město.'
          	);
      		}
      		if(isset($edd_options['eddvyfakturuj_povinne_psc']) && !empty($edd_options['eddvyfakturuj_povinne_psc'])){
      		$required_fields['edd_psc'] = array(
              'error_id' => 'invalid_edd_psc',
              'error_message' => 'Prosím vyplňte PSČ.'
          );
		   }
          return $required_fields;
      }

      public function addInvoiceToThankYou( $payment ) {
      	global $edd_options;
        if (!$this->isInitialized() ){
          return;
        }

      	$purchase_data = edd_get_payment_meta( $payment->ID );
      	?>
      	<tr>
      		<td><strong><?php _e( 'Faktura', 'eddvyfakturuj' ); ?>:</strong></td>
      		<td><a class="edd_invoice_link" title="<?php _e( 'Stáhnout fakturu', 'eddvyfakturuj' ); ?>" href="<?php echo esc_url($this->getInvoiceLink($payment->ID) ); ?>"><?php _e( 'Stáhnout fakturu', 'eddvyfakturuj' ); ?></a></td>
      	</tr>
      	<?php
      }

			

			public function setProdName($string,$length=100,$dots='…'){
		      //https://stackoverflow.com/a/3161830/855636
		      return (strlen($string) > $length) ? substr($string, 0, $length - strlen($dots)) . $dots : $string;
		  }

      public function setInvoicePaid($payment_id){
        if (!$this->isInitialized() ){
          return;
        }
				global $edd_options;
      	$vyfakturuj_api = new VyfakturujAPI($edd_options['eddvyfakturuj_login'],$edd_options['eddvyfakturuj_token']);
      	$inv = $this->getInvoice($payment_id);
		if(is_array($inv)) {
			$vyfakturuj_api->invoice_setPayment($inv['id'],date('Y-m-d'));
		}      	
	  }










	}// end class


}// class_exists
