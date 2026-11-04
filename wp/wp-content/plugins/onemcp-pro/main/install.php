<?php
namespace OneMCPPro;

if(!defined('ABSPATH')){
	exit;
}

class Install{
	
	static function activate(){
		update_option('onemcp_pro_version', ONEMCP_PRO_VERSION);
	}
	
	static function deactivate(){
		
	}
	
	static function uninstall(){
		delete_option('onemcp_pro_version');
	}
}