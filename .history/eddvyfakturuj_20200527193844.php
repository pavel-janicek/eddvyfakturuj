<?php
/*
Plugin Name: Propojení Vyfakturuj.cz a EDD
Plugin URL: https://cleverstart.cz
Description: Vygeneruje fakturu po zprocesování platby a pošle ji zákazníkovi na e-mail který uvedl při nákupu
Version: 2.5.118
Author: Pavel Janíček
Author URI: https://cleverstart.cz
*/


require plugin_dir_path(__FILE__) . 'libs/class_clvr_vyfakturuj.php';
require plugin_dir_path(__FILE__) . 'vendor/autoload.php';
$myUpdateChecker = Puc_v4_Factory::buildUpdateChecker(
	'https://plugins.cleverstart.cz/?action=get_metadata&slug=eddvyfakturuj',
	__FILE__, //Full path to the main plugin file or functions.php.
	'eddvyfakturuj'
);
$debug = new Clvr_Vyfakturuj(true);


