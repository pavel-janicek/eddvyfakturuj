<?php


if (!class_exists('Clvr_EDD_Order_Meta_wrapper')){
  /**
   *
   */
  class Clvr_EDD_Order_Meta_wrapper
  {

    private $table_name;
    private $edd_orders;
    private $primary_key;
    private $version;
    public $sql;

    function __construct()
    {
      global $wpdb;
      $this->table_name  = $wpdb->prefix . 'edd_ordermeta';
      $this->edd_orders = $wpdb->prefix . 'edd_orders';
		  $this->primary_key = 'meta_id';
		  $this->version     = '1.0';
    }

    public function get_columns() {
		return array(
			'meta_id'     => '%d',
			'edd_order_id' => '%d',
			'meta_key'    => '%s',
			'meta_value'  => '%s',
		);
	}

  public function add_meta($payment_id = 0, $meta_key = '', $meta_value, $unique = false ){
    global $wpdb;
    if ($payment_id == 0){
      throw new \Exception("add meta customer id 0", 1);

    }
    if (empty($meta_value)){
      return;
    }

    $maybe_done = $this->get_meta($payment_id,$meta_key);

    if (empty($maybe_done)){
      return $wpdb->insert($this->table_name, array(
    'edd_order_id' => $payment_id,
    'meta_key' => $meta_key,
    'meta_value' => $meta_value // ... and so on
    ));

    }    
    
    
    
  }

  public function get_meta($payment_id = 0, $meta_key = ''){
    global $wpdb;
    if ($payment_id == 0){
      throw new \Exception("get meta customer id 0", 1);

    }
    $this->sql = "SELECT meta_value FROM {$this->table_name} WHERE edd_order_id = {$payment_id} AND meta_key = '{$meta_key}'";
    $result= $wpdb->get_results($this->sql,'ARRAY_A');
    return $result[0]['meta_value'];
  }

  public function get_payment_id($payment_key){
    global $wpdb;
    $this->sql = "SELECT id FROM {$this->edd_orders} WHERE payment_key = '{$payment_key}'";
    $result= $wpdb->get_results($this->sql,'ARRAY_A');
    return $result[0]['id'];
  }

  public function getSql($payment_key){
    $this->sql = "SELECT id FROM {$this->edd_orders} WHERE payment_key = '{$payment_key}'";
    return $this->sql;
  }

  public function getresults($sql){
    global $wpdb;
    $result= $wpdb->get_results($sql,'ARRAY_A');
    return $result;
  }



} //class

}// class exists

 ?>
