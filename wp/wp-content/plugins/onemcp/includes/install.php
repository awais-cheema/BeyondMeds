<?php
/*
* SITESEO
* https://siteseo.io
* (c) SiteSEO Team
*/

namespace ONEMCP;

if(!defined('ABSPATH')){
	die('HACKING ATTEMPT!');
}

class Install{
	
	
	static function activate(){
		\ONEMCP\Audit::install();
		if (get_option(ONEMCP_OPTION) === false) {
			$old = get_option('onemcp_settings', false);
			$initial = ($old !== false && is_array($old)) ? $old : array('disabled_reads' => array(), 'enabled_writes' => array(), 'disabled_writes' => array());
			update_option(ONEMCP_OPTION, $initial, false);
		}
	}
	
	static function deactivate(){
	}
	
	
}