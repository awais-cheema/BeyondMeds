<?php
/**
 * Detects the OneMCP Pro add-on.
 *
 * Pro is a separate plugin. It defines ONEMCP_PRO and loads page builder,
 * SEO, SMTP, cache, store, and backup abilities under the OneMCPPro namespace.
 *
 * @package OneMCP
 */

namespace ONEMCP;

if (!defined('ABSPATH')) {
	exit;
}

class Pro {

	/**
	 * Whether the Pro plugin is loaded.
	 *
	 * @return bool
	 */
	public static function active() {
		return defined('ONEMCP_PRO') && ONEMCP_PRO;
	}

	/**
	 * Sales or docs URL supplied by the Pro plugin.
	 *
	 * @return string
	 */
	public static function url() {
		$url = apply_filters('onemcp_pro_url', '');
		return is_string($url) ? $url : '';
	}
}
