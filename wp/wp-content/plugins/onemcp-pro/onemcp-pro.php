<?php
/**
Plugin Name: OneMCP Pro
Description: Page builders, SEO, SMTP, cache, WooCommerce, Easy Digital Downloads, and backups for OneMCP.
Version: 1.0.0
Requires at least: 6.0
Requires PHP: 7.4
Requires Plugins: onemcp
Author: Softaculous
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html
Text Domain: onemcp-pro
Requires OneMCP
*/

if (!defined('ABSPATH')) {
	exit;
}

define('ONEMCP_PRO', true);
define('ONEMCP_PRO_VERSION', '1.0.0');
define('ONEMCP_PRO_FILE', __FILE__);
define('ONEMCP_PRO_DIR', plugin_dir_path(__FILE__));
define('ONEMCP_PRO_URL', plugin_dir_url(__FILE__));
define('ONEMCP_API', 'https://a.softaculous.com/onemcp');

require_once ONEMCP_PRO_DIR . 'functions.php';

/**
 * Autoloader for the OneMCPPro namespace.
 *
 * OneMCPPro\Plugin loads includes/plugin.php.
 * OneMCPPro\Builders loads includes/integrations/builders.php.
 *
 * @param string $class Class name.
 * @return void
 */
function onemcppro_autoloader($class) {
	if (!preg_match('/^OneMCPPro\\\(.*)/is', $class, $m)) {
		return;
	}

	$relative = strtolower(str_replace('\\', '/', $m[1]));
	$file     = $relative . '.php';

	if (file_exists(ONEMCP_PRO_DIR . 'main/' . $file)) {
		include_once ONEMCP_PRO_DIR . 'main/' . $file;
		return;
	}

	if (file_exists(ONEMCP_PRO_DIR . 'main/integrations/' . $file)) {
		include_once ONEMCP_PRO_DIR . 'main/integrations/' . $file;
	}
}

spl_autoload_register('onemcppro_autoloader');
register_activation_hook(ONEMCP_PRO_FILE, '\OneMCPPro\Install::activate');
register_deactivation_hook(ONEMCP_PRO_FILE, '\OneMCPPro\Install::deactivate');
register_uninstall_hook(ONEMCP_PRO_FILE, '\OneMCPPro\Install::uninstall');
add_action('plugins_loaded', 'onemcp_pro_load', 20);


// Use a late priority so we run after the plugin-update-checker library
// injects the Pro update into the transient.
add_filter('site_transient_update_plugins', 'onemcp_pro_disable_manual_update_for_plugin');
add_filter('pre_site_transient_update_plugins', 'onemcp_pro_disable_manual_update_for_plugin');

/**
 * Load Pro abilities after the free plugin.
 *
 * @return void
 */
function onemcp_pro_load() {
	global $onemcp;

	if (!is_object($onemcp)) {
		$onemcp = new stdClass();
	}

	onemcp_pro_load_license();
	onemcp_pro_check_updates();
	
	include_once(ONEMCP_PRO_DIR . 'main/plugin-update-checker.php');
	$onemcp_updater = OneMCP_PucFactory::buildUpdateChecker(onemcp_pro_api_url().'/updates.php?version='.ONEMCP_PRO_VERSION, ONEMCP_PRO_FILE);

	if(defined('ONEMCP_VERSION') && defined('ONEMCP_FILE')){
		OneMCP_PucFactory::buildUpdateChecker(onemcp_pro_api_url().'/updates.php?type=free&version='.ONEMCP_VERSION, ONEMCP_FILE);
	}

	// Add the license key to query arguments
	$onemcp_updater->addQueryArgFilter('onemcp_pro_updater_filter_args');

	// Show the text to install the license key
	add_filter('puc_manual_final_check_link-onemcp-pro', 'onemcp_pro_updater_check_link', 10, 1);

	\OneMCPPro\Plugin::hooks();
}


function onemcp_pro_check_updates(){
	$current_version = get_option('onemcp_pro_version');
	$version = (int) str_replace('.', '', $current_version);

	// Is it first run ?
	if(empty($current_version)){
		\OneMCPPro\Install::activate();
		return;
	}

	update_option('onemcp_pro_version', ONEMCP_PRO_VERSION);

}