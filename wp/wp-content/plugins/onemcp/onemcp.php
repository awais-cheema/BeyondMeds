<?php
/**
Plugin Name: OneMCP
Plugin URI: https://softwp.net/onemcp
Description: Connect AI assistants to WordPress through a built-in MCP server to manage posts, pages, media, comments, terms, menus, plugins, themes, and users.
Version: 1.0.0
Requires at least: 6.0
Requires PHP: 7.0
Author: Softaculous Team
Author URI: https://softaculous.com/
License: GPLv2
Text Domain: onemcp
*/

if (!defined('ABSPATH')) {
	exit;
}

define('ONEMCP_VERSION', '1.0.0');
define('ONEMCP_FILE', __FILE__);
define('ONEMCP_DIR_PATH', plugin_dir_path(__FILE__));
define('ONEMCP_DIR_URL', plugin_dir_url(__FILE__));
define('ONEMCP_OPTION', 'onemcp_settings');

function onemcp_autoloader($class){
	if(!preg_match('/^ONEMCP\\\(.*)/is', $class, $m)){
		return;
	}

	$relative = str_replace('\\', '/', $m[1]);
	$file     = strtolower($relative) . '.php';

	if ($file === 'helpers.php' && file_exists(ONEMCP_DIR_PATH . 'includes/helper.php')) {
		include_once ONEMCP_DIR_PATH . 'includes/helper.php';
		return;
	}

	if (($relative === 'ONEMCP' || $file === 'onemcp.php') && file_exists(ONEMCP_DIR_PATH . 'includes/plugin.php')) {
		include_once ONEMCP_DIR_PATH . 'includes/plugin.php';
		return;
	}

	// Include file from includes/
	if(file_exists(ONEMCP_DIR_PATH . 'includes/' . $file)){
		include_once(ONEMCP_DIR_PATH . 'includes/' . $file);
		return;
	}

	// Include file from includes/integrations/
	if(file_exists(ONEMCP_DIR_PATH . 'includes/integrations/' . $file)){
		include_once(ONEMCP_DIR_PATH . 'includes/integrations/' . $file);
		return;
	}
}

spl_autoload_register('onemcp_autoloader');

register_activation_hook(__FILE__, '\ONEMCP\Install::activate');
register_deactivation_hook(__FILE__, '\ONEMCP\Install::deactivate');

add_action('plugins_loaded', 'onemcp_load_plugin');


function onemcp_load_plugin(){

	onemcp_check_update();

	\ONEMCP\Audit::maybe_install();
	add_action('wp_abilities_api_categories_init','\ONEMCP\Registry::register_categories');
	// After page builders register their own abilities, so those can be republished here.
	add_action('wp_abilities_api_init', '\ONEMCP\Registry::register_abilities', 100);
	
	\ONEMCP\Server::hooks();
	
	if(wp_doing_ajax()){
		\ONEMCP\Ajax::hooks();
	}

	\ONEMCP\Admin::hooks();
}

function onemcp_check_update(){
	$current_version = get_option('onemcp_version');
	$version = (int) str_replace('.', '', $current_version);

	// Is it first run ?
	if(empty($current_version)){
		\OneMCP\Install::activate();
		return;
	}

	update_option('onemcp_version', ONEMCP_VERSION);
}