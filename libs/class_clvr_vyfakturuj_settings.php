<?php
if (!class_exists('Clvr_Vyfakturuj_Settings')){

  /**
   *
   */
  class Clvr_Vyfakturuj_Settings extends Clvr_Vyfakturuj
  {
    private $experimental;
    public $settings;
    function __construct($experimental = false)
    {
      $this->experimental = $experimental;
	  add_filter( 'edd_settings_sections_extensions', array($this,'settings_section') );
	  add_filter( 'edd_settings_sections_emails', array($this, 'email_settings_section') );
	  add_filter( 'edd_settings_extensions', array($this,'add_settings') );
	  add_filter( 'edd_settings_emails', array($this,'add_email_settings'));
      add_action( 'edd_purchase_form_user_info_fields', array($this,'checkout_fields') );
	  add_action( 'edd_payment_personal_details_list', array($this,'view_order_details'), 10, 2 );
	  add_action( 'add_meta_boxes', array($this,'add_meta_boxes') );
	  add_filter( 'edd_metabox_fields_save', array($this,'save_metabox') );
	  add_filter( 'edd_settings_gateways', array($this,'gateway_settings') );
	  add_action( 'edd_insert_payment', array($this,'maybe_send_emails'));
    }

    public function settings_section($sections){
			$sections['eddvyfakturuj-settings'] = __( 'Nastavení Vyfakturuj.cz', 'eddvyfakturuj' );
			return $sections;
	}

	public function email_settings_section($sections){
		$sections[$this->context.'-mail'] = __( 'E-Mailové notifikace po nákupu přes Vyfakturuj.cz platební bránu', $this->context );
		return $sections;
	}

    public function prepare_settings(){
      $eddvyfakturuj_settings = array (
				array(
					'id' => 'eddvyfakturuj_settings',
					'name' => '<strong>Nastavení propojení EDD s Vyfakturuj.cz</strong>',
					'desc' => 'Níže uvedené údaje se budou zobrazovat na každé vystavené faktuře.',
					'type' => 'header'
				),
		    array(
		      'id' => 'eddvyfakturuj_login',
		      'name' => 'Přihlašovací email do vyfakturuj.cz',
					'desc' => 'E-Mail který jste uvedli ve vyfakturuj.cz',
					'type' => 'text',
					'size' => 'regular'
		    ),
		    array(
		      'id' => 'eddvyfakturuj_token',
		      'name' => 'Token vyfakturuj.cz',
					'desc' => 'Vygenerovaný token',
					'type' => 'text',
					'size' => 'regular'
        ),
        array(
          'id' => 'eddvyfakturuj_proforma',
          'name' => 'Vystavovat proforma faktury',
          'desc' => 'Faktury se budou vystavovat po každé objednávce jako proforma faktury',
          'type' => 'checkbox'
        ),
				array(
					'id'	=> 'eddvyfakturuj_typ_platby',
					'name' => 'Způsob platby zobrazený na faktuře',
					'type' => 'select',
					'options' => $this->getPaymentTypes()
				),
				array(
		      'id' => 'eddvyfakturuj_povinne_prijmeni',
		      'name' => 'Povinné příjmení',
					'desc' => 'Má být příjmení povinné?',
					'type' => 'checkbox'
		    ),
				array(
		      'id' => 'eddvyfakturuj_povinny_stat',
		      'name' => 'Povinný stát',
					'desc' => 'Má být stát povinný?',
					'type' => 'checkbox'
		    ),
				array(
		      'id' => 'eddvyfakturuj_povinna_ulice',
		      'name' => 'Povinná ulice',
					'desc' => 'Má být ulice povinná?',
					'type' => 'checkbox'
		    ),
				array(
		      'id' => 'eddvyfakturuj_povinne_mesto',
		      'name' => 'Povinné město',
					'desc' => 'Má být město povinné?',
					'type' => 'checkbox'
		    ),
				array(
		      'id' => 'eddvyfakturuj_povinne_psc',
		      'name' => 'Povinné PSČ',
					'desc' => 'Má být PSČ povinné?',
					'type' => 'checkbox'
		    ),
				array(
					'id' => 'eddvyfakturuj_povinne_firemni_udaje',
		      'name' => 'Skrýt formulář nákupu na firmu',
					'desc' => 'pokud bude zaškrtnuto, tak se formulář s nákupem na firmu nezobrazí',
					'type' => 'checkbox'
				 )
			);
      return $eddvyfakturuj_settings;
    }

    

    public function add_settings($settings){
      $eddvyfakturuj_settings = $this->prepare_settings();
      $settings_to_merge = $this->getPaymentGateways();
      $eddvyfakturuj_settings = array_merge($eddvyfakturuj_settings,$settings_to_merge);

			if ( version_compare( EDD_VERSION, 2.5, '>=' ) ) {
				$eddvyfakturuj_settings = array( 'eddvyfakturuj-settings' => $eddvyfakturuj_settings );
			}
			return array_merge( $settings, $eddvyfakturuj_settings );

    }
    
    public function gateway_settings($settings){
      $my_settings = array(
        array(
					'id' => $this->context.'_gateway_settings',
					'name' => '<strong>Nastavení Vyfakturuj.cz jako platební brány</strong>',
					'desc' => 'Více informací na https://cleverstart.cz/downloads/propojeni-s-vyfakturuj-cz-pro-edd/',
					'type' => 'header'
        ),
    //     array(
				// 	'id'	=> $this->context.'_id_platby',
				// 	'name' => 'ID platební metody pro platbu faktury online',
				// 	'type' => 'select',
				// 	'options' => $this->getPaymentIDs()
				// )

	  );
	  if ($this->isInitialized()){
		  
		  return array_merge( $settings, $my_settings );
	  }else{
		  return $settings;
	  }
    }

    public function checkout_fields() {
    	global $edd_options;
    	if(isset($edd_options['eddvyfakturuj_povinne_firemni_udaje']) && !empty($edd_options['eddvyfakturuj_povinne_firemni_udaje'])){
    		return;
    	}
    ?>
        <p id="edd-faktura-general">
    	  <strong>Fakturační údaje:</strong>
    	</p>
        <p id="edd-faktura-firma-wrap">
            <label class="edd-label" for="edd-firma">Název společnosti</label>
            <span class="edd-description">
            	Vyplňte název vaší společnosti. Pokud toto pole vyplníte, vaše křestní jméno se na faktuře neobjeví
            </span>
            <input class="edd-input" type="text" name="edd_firma" id="edd-firma" placeholder="" />
        </p>
    		<?php if(isset($edd_options['eddvyfakturuj_povinny_stat']) && !empty($edd_options['eddvyfakturuj_povinny_stat'])): ?>
    	<p id="edd-faktura-stat-wrap">
            <label class="edd-label" for="edd-stat">Stát <span class="edd-required-indicator">*</span></label>
            <span class="edd-description">
            	Vyplňte stát vaší společnosti
            </span>
            <input class="edd-input" type="text" name="edd_stat" id="edd-stat" value="Česká Republika" />
        </p>
    	<?php endif;?>
        <p id="edd-faktura-ic-wrap">
            <label class="edd-label" for="edd-ic">IČ</label>
            <span class="edd-description">
            	Vyplňte IČ vaší společnosti
            </span>
            <input class="edd-input" type="text" name="edd_ic" id="edd-ic" placeholder="" />
        </p>
        <p id="edd-faktura-dic-wrap">
            <label class="edd-label" for="edd-dic">DIČ</label>
            <span class="edd-description">
            	Vyplňte DIČ vaší společnosti
            </span>
            <input class="edd-input" type="text" name="edd_dic" id="edd-dic" placeholder="" />
        </p>
    		<?php if(isset($edd_options['eddvyfakturuj_povinna_ulice']) && !empty($edd_options['eddvyfakturuj_povinna_ulice'])): ?>
    	<p id="edd-faktura-ulice-wrap">
            <label class="edd-label" for="edd-ulice">Ulice a číslo popisné <span class="edd-required-indicator">*</span></label>
            <span class="edd-description">
            	Vyplňte ulici sídla vaší společnosti
            </span>
            <input class="edd-input" type="text" name="edd_ulice" id="edd-ulice" placeholder="" />
        </p>
    			<?php endif;?>
    			<?php if(isset($edd_options['eddvyfakturuj_povinne_mesto']) && !empty($edd_options['eddvyfakturuj_povinne_mesto'])): ?>
    	<p id="edd-faktura-mesto-wrap">
            <label class="edd-label" for="edd-mesto">Město <span class="edd-required-indicator">*</span></label>
            <span class="edd-description">
            	Vyplňte město sídla vaší společnosti
            </span>
            <input class="edd-input" type="text" name="edd_mesto" id="edd-mesto" placeholder="" />
        </p>
    		<?php endif;?>
    		<?php if(isset($edd_options['eddvyfakturuj_povinne_psc']) && !empty($edd_options['eddvyfakturuj_povinne_psc'])): ?>
    	<p id="edd-faktura-psc-wrap">
            <label class="edd-label" for="edd-psc">PSČ <span class="edd-required-indicator">*</span></label>
            <span class="edd-description">
            	Vyplňte PSČ
            </span>
            <input class="edd-input" type="text" name="edd_psc" id="edd-psc" placeholder="" />
        </p>
        <?php endif;
    }

    private function getPaymentGateways(){
      $gateways     = edd_get_payment_gateways();
      $settings_to_merge = array();
      foreach ($gateways as $gateway_id => $gateway_array) {
        $gateway_setting =  array(
          'id' => 'eddvyfakturuj_gateway_' .$gateway_id,
          'name' => 'Způsob platby u platby přes platební bránu ' .$gateway_array['admin_label'] ,
          'type' => 'select',
					'options' => $this->getPaymentTypes()
          );
          array_push($settings_to_merge,$gateway_setting);

      }
      return $settings_to_merge;
    }

    private function getPaymentTypes(){
      $payment_types = [
          1 => 'Bankovní převod',
          2  => 'Hotovost',
          4  => 'Dobírka',
          8  => 'Kartou online',
          128  => 'PayPal',
          16  => 'Záloha',
          32  => 'Zápočet'
      ];
      return $payment_types;
    }

    public function view_order_details( $payment_meta, $user_info ) {
    	$payment_meta_wrapper = new Clvr_EDD_Order_Meta_wrapper();
    	if (!isset($payment_meta['key']))
    		{print_r($payment_meta_);}
    	if(isset($payment_meta['key'])){
    	$payment_id = $payment_meta_wrapper->get_payment_id($payment_meta['key']);
    	if(empty($payment_id)){
    		?>
    		<div class="column-container">
        	<div class="column">
        		<strong>Payment ID prazdny: </strong>
        		 <?php echo $payment_meta_wrapper->getSql($payment_meta['key']); ?>
        	</div><?php

    	}
    	if (!empty($payment_id)){
    	$firma  = $payment_meta_wrapper->get_meta($payment_id,'edd_firma');
    	$stat  = $payment_meta_wrapper->get_meta($payment_id,'edd_stat');
    	$ic  = $payment_meta_wrapper->get_meta($payment_id,'edd_ic');
    	$dic  = $payment_meta_wrapper->get_meta($payment_id,'edd_dic');
    	$ulice  = $payment_meta_wrapper->get_meta($payment_id,'edd_ulice');
    	$mesto  = $payment_meta_wrapper->get_meta($payment_id,'edd_mesto');
    	$psc  = $payment_meta_wrapper->get_meta($payment_id,'edd_psc');
      }
    }

    	// $firma = isset( $payment_meta['edd_firma'] ) ? $payment_meta['edd_firma'] : 'položka neuvedena';
     //    $stat = isset( $payment_meta['edd_stat'] ) ? $payment_meta['edd_stat'] : 'položka neuvedena';
     //    $ic = isset( $payment_meta['edd_ic'] ) ? $payment_meta['edd_ic'] : 'položka neuvedena';
     //    $dic = isset( $payment_meta['edd_dic'] ) ? $payment_meta['edd_dic'] : 'položka neuvedena';
     //    $ulice = isset( $payment_meta['edd_ulice'] ) ? $payment_meta['edd_ulice'] : 'položka neuvedena';
     //    $mesto = isset( $payment_meta['edd_mesto'] ) ? $payment_meta['edd_mesto'] : 'položka neuvedena';
     //    $psc = isset( $payment_meta['edd_psc'] ) ? $payment_meta['edd_psc'] : 'položka neuvedena';
    ?>
        <div class="column-container">
        	<div class="column">
        		<strong>Firma: </strong>
        		 <?php echo $firma; ?>
        	</div>
    		<div class="column">
        		<strong>Stát: </strong>
        		 <?php echo $stat; ?>
        	</div>
    		<div class="column">
        		<strong>IČ: </strong>
        		 <?php echo $ic; ?>
        	</div>
    		<div class="column">
        		<strong>DIČ: </strong>
        		 <?php echo $dic; ?>
        	</div>
    		<div class="column">
        		<strong>Ulice a číslo popisné: </strong>
        		 <?php echo $ulice; ?>
        	</div>
    		<div class="column">
        		<strong>Město: </strong>
        		 <?php echo $mesto; ?>
        	</div>
    		<div class="column">
        		<strong>PSČ: </strong>
        		 <?php echo $psc; ?>
        	</div>
        </div>
    <?php
	}
	
	public function add_meta_boxes(){
        if ( current_user_can( 'edit_product', get_the_ID() ) ) {
    			add_meta_box( 'edd_vyfakturuj_prodtype', 'Opakovaná fakturace', array($this,'render_recurring_product') , 'download', 'side' );
			}
			
		if 	( current_user_can( 'edit_product', get_the_ID() ) && edd_use_taxes() ) {
				add_meta_box($this->context.'_vat_rate_type', 'Typ daně na produktu', array($this,'render_vat_type'), 'download', 'side');

		}
      }

      public function render_recurring_product(){
				global $post;
        echo "<p>Jedná se o produkt s opakovanou fakturací?</p>";
      	$selected = get_post_meta( $post->ID, self::RECCURENCE_META_KEY, true );
      	echo '<select name="'.self::RECCURENCE_META_KEY.'" class="'.$selected.'">';
      	echo '<option value="none" '.selected( $selected, 'none' ).'>Bez opakované fakturace</option>';
      	echo '<option value="monthly" '.selected( $selected, 'monthly' ).'>Měsíční opakování fakturace</option>';
      	echo '<option value="yearly" '.selected( $selected, 'yearly' ).'>Roční opakování fakturace</option>';
      	echo '</select>';
	  }
	  
	  public function render_vat_type(){
		  global $post;
		  $selected = get_post_meta( $post->ID, self::VAT_TYPE_KEY, true );
		  if (empty($selected)){
			  $selected = 2;
		  }
		  echo '<select name="'.self::VAT_TYPE_KEY.'">';
		  foreach ($this->getVatTypes() as $vat_type => $vat_name){
			echo '<option value="'.$vat_type.'" '.selected( $selected, $vat_type ).'>'.$vat_name.'</option>';
		  }
		  echo '</select>';
	  }

      public function save_metabox($fields){
      	$fields[] = self::RECCURENCE_META_KEY;
      	return $fields;
	  }
	  
	  public function getVatTypes(){
		  $vat_types = [
			1 => 'Vlastní / historická',
			2 => 'Základní',
			4 => 'Zvýšená',
			8 => 'Snížená',
			16 => 'Druhá snížená',
			32 => 'Nulová',
			64 => 'Neplátce - není daň',
			128 => 'Třetí snížená'
		  ];
		  return $vat_types;
	  }

	  public function add_email_settings($settings){
		$pavel_settings = array(
			array(
			  'id'   => $this->context.'_email_settings',
			  'name' => '<strong>' . __( 'Nastavení notifikačních mailů při nákupu přes bránu Vyfakturuj.cz', $this->context). '</strong>',
			  'desc' => __( 'Nastavte znění mailů', 'eddpdfi' ),
			  'type' => 'header'
			),
					  array(
						  'id'   => $this->context.'_admin_mail_subject',
						  'name' => __( 'Předmět mailu pro admina', $this->context ),
						  'desc' => __( 'Uveďte předmět zprávy, notifikace pro administrátora stránek', $this->context ),
						  'type' => 'text',
						  'std'  => 'Nová objednávka #{payment_id}'
					  ),
					  array(
						  'id'   => $this->context.'_admin_mail_text',
						  'name' => __( 'Text mailu pro admina', $this->context ),
						  'desc' => __( 'Uveďte zprávu, kterou obdrží admin stránek. Dostupné tagy:', $this->context ) . '<br/>' . edd_get_emails_tags_list(),
						  'type' => 'rich_editor',
						  'std'  => $this->defaultAdminMail()
					  ),
					  array(
						  'id'   => $this->context.'_send_admin_notification',
						  'name' => __( 'Zasílat e-mailové notifikace administrátorům', $this->context ),
						  'desc' => __( 'E-mailové notifikace administrátorům po nákupu se začnou zasílat po zaškrtnutí tohoto políčka', 'easy-digital-downloads' ),
						  'type' => 'checkbox',

					  ),
					array(
						'id'   => $this->context.'_user_mail_subject',
						'name' => __( 'Předmět mailu pro uživatele', 'eddfio' ),
						'desc' => __( 'Uveďte předmět zprávy, notifikace pro kupujícího', 'easy-digital-downloads' ),
						'type' => 'text',
						'std'  => 'Děkujeme za nákup!'
					),
					array(
						'id'   => $this->context.'_user_mail_text',
						'name' => __( 'Text mailu pro uživatele', 'easy-digital-downloads' ),
						'desc' => __( 'Uveďte zprávu, kterou obdrží zájemce o koupi. Dostupné tagy:', 'easy-digital-downloads' ) . '<br/>' . edd_get_emails_tags_list(),
						'type' => 'rich_editor',
						'std'  => $this->defaultUserMail()
					),
					array(
						'id'   => $this->context.'_send_user_notification',
						'name' => __( 'Nezasílat e-mailové notifikace uživatelům', $this->context ),
						'desc' => __( 'E-mailové notifikace uživatelům po nákupu se nebudou zasílat po zaškrtnutí tohoto políčka', 'easy-digital-downloads' ),
						'type' => 'checkbox'
						

					),  



		  );
		  if ( version_compare( EDD_VERSION, 2.5, '>=' ) ) {
			$pavel_settings = array( $this->context.'-mail' => $pavel_settings );
		  }

  		return array_merge( $settings, $pavel_settings );
	  }

	  public function hasAdminNotification(){
		  global $edd_options;
		  return (!empty($edd_options[$this->context.'_send_admin_notification']));
	  }

	  public function hasUserNotification(){
		global $edd_options;
		return (empty($edd_options[$this->context.'_send_user_notification']));
	  }
	  
	  public function defaultAdminMail(){
		$default_email_body = "Nová objednávka na platbu převodem" . "\n\n" . sprintf( __( 'A %s purchase has been made', 'easy-digital-downloads' ), edd_get_label_plural() ) . ".\n\n";
		$default_email_body .= sprintf( __( '%s sold:', 'easy-digital-downloads' ), edd_get_label_plural() ) . "\n\n";
		$default_email_body .= '{download_list}' . "\n\n";
		$default_email_body .= __( 'Purchased by: ', 'easy-digital-downloads' ) . ' {name}' . "\n";
		$default_email_body .= __( 'Amount: ', 'easy-digital-downloads' ) . '{price}' . "\n";
		$default_email_body .= __( 'Payment Method: ', 'easy-digital-downloads' ) . ' {payment_method}' . "\n\n";
		$default_email_body .= __( 'Faktura:', $this->context) . '{eddvyfakturuj_faktura_invoice}'. "\n\n";
		$default_email_body .= __( 'Thank you', 'easy-digital-downloads' );
		$message = edd_get_option( $this->context.'_admin_mail_text', false );
		$message = ! empty( $message ) ? $message : $default_email_body;
		return $message;

	  }

	  public function defaultUserMail(){
		$default_email_body = "<h1>Děkujeme vám za nákup</h1>";
		$default_email_body .="<p>Níže posíláme fakturu:</p>";
		$default_email_body .="<p>{eddvyfakturuj_faktura_invoice} </p>";
		$default_email_body .="<p>Jakmile se faktura uhradí, zašleme vám odkaz na stažení vámi zvoleného produktu/produktů a doklad o nákupu.</p>";
		$message = edd_get_option( $this->context.'_user_mail_text', false );
		$message = ! empty( $message ) ? $message : $default_email_body;
  		return $message;

	  }

	  public function maybe_send_emails($payment_id){
		  if ($this->hasUserNotification()){
			$payment = new EDD_Payment($payment_id);
			$to = $payment->email;
			$subject = edd_get_option($this->context.'_user_mail_subject', 'Děkujeme za nákup!');
			$subject = apply_filters( $this->context.'_user_mail_subject', wp_strip_all_tags( $subject ), $payment_id );
			$subject = edd_do_email_tags( $subject, $payment_id );
			$message = edd_get_option( $this->context.'_user_mail_text', $this->defaultUserMail() );
			$message = edd_do_email_tags( $message, $payment_id);
			EDD()->emails->send( $to, $subject, $message );

		  }
		  if ($this->hasAdminNotification()){
			$to = $this->get_admin_notice_emails();
			$subject = edd_get_option($this->context.'_admin_mail_subject', 'Nová objednávka #{payment_id}');
			$subject = apply_filters( $this->context.'_admin_mail_subject', wp_strip_all_tags( $admin_subject ), $payment_id );
			$subject = edd_do_email_tags( $admin_subject, $payment_id );
			$message = edd_get_option( $this->context.'_admin_mail_text', $this->defaultAdminMail() );
			$message = edd_do_email_tags( $message, $payment_id);
			EDD()->emails->send( $to, $subject, $message );
		  }
	  }

	  public function get_admin_notice_emails() {

		global $edd_options;
   
		$emails = isset( $edd_options['admin_notice_emails'] ) && strlen( trim( $edd_options['admin_notice_emails'] ) ) > 0 ? $edd_options['admin_notice_emails'] : get_bloginfo( 'admin_email' );
		$emails = array_map( 'trim', explode( "\n", $emails ) );
   
		return apply_filters( 'edd_admin_notice_emails', $emails );
	}
  }// end class


}// class exists

 ?>
