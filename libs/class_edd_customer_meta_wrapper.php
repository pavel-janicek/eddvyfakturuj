<?php


if (!class_exists('Clvr_EDD_Customer_Meta_wrapper')){
  /**
   *
   */
  class Clvr_EDD_Customer_Meta_wrapper
  {

    private $table_name;
    private $primary_key;
    private $version;
    public $sql;

    function __construct()
    {
      global $wpdb;
      $this->table_name  = $wpdb->prefix . 'edd_customermeta';
		  $this->primary_key = 'meta_id';
		  $this->version     = '1.0';
    }

    public function get_columns() {
		return array(
			'meta_id'     => '%d',
			'customer_id' => '%d',
			'meta_key'    => '%s',
			'meta_value'  => '%s',
		);
	}

  public function add_meta($customer_id = 0, $meta_key = '', $meta_value, $unique = false ){
    global $wpdb;
    if ($customer_id == 0){
      throw new \Exception("add meta customer id 0", 1);

    }
    return $wpdb->insert($this->table_name, array(
    'customer_id' => $customer_id,
    'meta_key' => $meta_key,
    'meta_value' => $meta_value // ... and so on
    ));
  }

  public function get_meta($customer_id = 0, $meta_key = ''){
    global $wpdb;
    if ($customer_id == 0){
      throw new \Exception("get meta customer id 0", 1);

    }
    $this->sql = "SELECT meta_value FROM {$this->table_name} WHERE customer_id = {$customer_id} AND meta_key = '{$meta_key}'";
    $result= $wpdb->get_results($this->sql,'ARRAY_A');
    return $result[0]['meta_value'];
  }



} //class

}// class exists

 ?>
