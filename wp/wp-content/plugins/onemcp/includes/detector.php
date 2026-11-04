<?php
/**
 * Detects supported plugins and themes.
 *
 * @package OneMCP
 */

namespace ONEMCP;

if (!defined('ABSPATH')) {
	exit;
}

class Detector {

	/**
	 * SitePad uses this same plugin. The editor is Pagelayer, so it is not listed
	 * as its own integration.
	 *
	 * @return bool
	 */
	static function is_sitepad() {
		return defined('SITEPAD');
	}

	/**
	 * Label for the built-in ability group.
	 *
	 * @return string
	 */
	static function core_label() {
		return self::is_sitepad() ? __('SitePad Core', 'onemcp') : __('WordPress Core', 'onemcp');
	}

	/**
	 * Supported products, keyed by slug.
	 *
	 * On SitePad only the Softaculous plugins are offered. Pagelayer stays
	 * available through is_active() because it is the SitePad editor.
	 *
	 * @return array<string,array<string,mixed>>
	 */
	static function products() {
		$products = self::product_catalog();
		if (!self::is_sitepad()) {
			return $products;
		}
		$keep = array('gosmtp', 'siteseo', 'speedycache');
		$out  = array();
		foreach ($keep as $slug) {
			if (isset($products[$slug])) {
				$out[$slug] = $products[$slug];
			}
		}
		return $out;
	}

	/**
	 * Every product this plugin can detect on WordPress.
	 *
	 * @return array<string,array<string,mixed>>
	 */
	private static function product_catalog() {
		return array(
			'pagelayer' => array(
				'label'   => 'Pagelayer',
				'group'   => 'builders',
				'summary' => __('Pages, posts, and Pagelayer templates.', 'onemcp'),
				'search'  => 'pagelayer',
				'active'  => function () {
					return defined('PAGELAYER_VERSION') || class_exists('Pagelayer');
				},
				'version' => defined('PAGELAYER_VERSION') ? PAGELAYER_VERSION : '',
			),
			'elementor' => array(
				'label'   => 'Elementor',
				'group'   => 'builders',
				'summary' => __('Pages, posts, and Elementor library templates.', 'onemcp'),
				'search'  => 'elementor',
				'active'  => function () {
					return defined('ELEMENTOR_VERSION') || class_exists('\Elementor\Plugin');
				},
				'version' => defined('ELEMENTOR_VERSION') ? ELEMENTOR_VERSION : '',
			),
			'seedprod' => array(
				'label'   => 'SeedProd',
				'group'   => 'builders',
				'summary' => __('Landing pages, coming soon, and maintenance pages.', 'onemcp'),
				'search'  => 'seedprod',
				'active'  => function () {
					return defined('SEEDPROD_VERSION') || defined('SEEDPROD_PRO_VERSION');
				},
				'version' => defined('SEEDPROD_VERSION') ? SEEDPROD_VERSION : (defined('SEEDPROD_PRO_VERSION') ? SEEDPROD_PRO_VERSION : ''),
			),
			'divi' => array(
				'label'   => 'Divi',
				'group'   => 'builders',
				'summary' => __('Divi Builder pages and saved layouts.', 'onemcp'),
				'search'  => 'divi builder',
				'active'  => function () {
					$theme = function_exists('get_template') ? get_template() : '';
					return in_array($theme, array('Divi', 'Extra'), true) || defined('ET_BUILDER_VERSION') || defined('ET_BUILDER_PLUGIN_VERSION');
				},
				'version' => defined('ET_BUILDER_VERSION') ? ET_BUILDER_VERSION : '',
			),
			'bricks' => array(
				'label'   => 'Bricks',
				'group'   => 'builders',
				'summary' => __('Bricks pages and templates.', 'onemcp'),
				'search'  => 'bricks',
				'active'  => function () {
					$theme = function_exists('get_template') ? get_template() : '';
					return $theme === 'bricks' || defined('BRICKS_VERSION');
				},
				'version' => defined('BRICKS_VERSION') ? BRICKS_VERSION : '',
			),
			'beaver' => array(
				'label'   => 'Beaver Builder',
				'group'   => 'builders',
				'summary' => __('Beaver Builder layouts and templates.', 'onemcp'),
				'search'  => 'beaver builder',
				'active'  => function () {
					return defined('FL_BUILDER_VERSION') || class_exists('FLBuilderModel');
				},
				'version' => defined('FL_BUILDER_VERSION') ? FL_BUILDER_VERSION : '',
			),
			'breakdance' => array(
				'label'   => 'Breakdance',
				'group'   => 'builders',
				'summary' => __('Breakdance pages and templates.', 'onemcp'),
				'search'  => 'breakdance',
				'active'  => function () {
					return defined('__BREAKDANCE_VERSION') || defined('BREAKDANCE_VERSION');
				},
				'version' => defined('__BREAKDANCE_VERSION') ? __BREAKDANCE_VERSION : (defined('BREAKDANCE_VERSION') ? BREAKDANCE_VERSION : ''),
			),
			'siteseo' => array(
				'label'   => 'SiteSEO',
				'group'   => 'seo',
				'summary' => __('Titles, meta descriptions, keywords, and social tags.', 'onemcp'),
				'search'  => 'siteseo',
				'active'  => function () {
					return defined('SITESEO_VERSION');
				},
				'version' => defined('SITESEO_VERSION') ? SITESEO_VERSION : '',
			),
			'yoast' => array(
				'label'   => 'Yoast SEO',
				'group'   => 'seo',
				'summary' => __('Yoast titles, descriptions, focus keyphrases, and social tags.', 'onemcp'),
				'search'  => 'yoast seo',
				'active'  => function () {
					return defined('WPSEO_VERSION');
				},
				'version' => defined('WPSEO_VERSION') ? WPSEO_VERSION : '',
			),
			'rank-math' => array(
				'label'   => 'Rank Math',
				'group'   => 'seo',
				'summary' => __('Rank Math titles, descriptions, focus keywords, and social tags.', 'onemcp'),
				'search'  => 'rank math',
				'active'  => function () {
					return defined('RANK_MATH_VERSION') || class_exists('RankMath') || in_array('seo-by-rank-math/rank-math.php', Helper::active_plugin_files(), true);
				},
				'version' => defined('RANK_MATH_VERSION') ? RANK_MATH_VERSION : '',
			),
			'aioseo' => array(
				'label'   => 'All in One SEO',
				'group'   => 'seo',
				'summary' => __('All in One SEO titles, descriptions, and social tags.', 'onemcp'),
				'search'  => 'all in one seo',
				'active'  => function () {
					return defined('AIOSEO_VERSION') || function_exists('aioseo');
				},
				'version' => defined('AIOSEO_VERSION') ? AIOSEO_VERSION : '',
			),
			'seopress' => array(
				'label'   => 'SEOPress',
				'group'   => 'seo',
				'summary' => __('SEOPress titles, descriptions, keywords, and social tags.', 'onemcp'),
				'search'  => 'seopress',
				'active'  => function () {
					return defined('SEOPRESS_VERSION');
				},
				'version' => defined('SEOPRESS_VERSION') ? SEOPRESS_VERSION : '',
			),
			'gosmtp' => array(
				'label'    => 'GoSMTP',
				'group'    => 'smtp',
				'summary'  => __('From address, SMTP host, port, encryption, and a test email.', 'onemcp'),
				'search'   => 'gosmtp',
				'featured' => true,
				'active'   => function () {
					return defined('GOSMTP_VERSION') || class_exists('GOSMTP\AbilitiesRegister') || in_array('gosmtp/gosmtp.php', Helper::active_plugin_files(), true);
				},
				'version'  => defined('GOSMTP_VERSION') ? GOSMTP_VERSION : '',
			),
			'wp-mail-smtp' => array(
				'label'   => 'WP Mail SMTP',
				'group'   => 'smtp',
				'summary' => __('From address, mailer, SMTP host, port, and encryption.', 'onemcp'),
				'search'  => 'wp mail smtp',
				'active'  => function () {
					return defined('WPMS_PLUGIN_VER') || function_exists('wp_mail_smtp') || in_array('wp-mail-smtp/wp_mail_smtp.php', Helper::active_plugin_files(), true);
				},
				'version' => defined('WPMS_PLUGIN_VER') ? WPMS_PLUGIN_VER : '',
			),
			'fluent-smtp' => array(
				'label'   => 'FluentSMTP',
				'group'   => 'smtp',
				'summary' => __('FluentSMTP connections, from address, host, port, and encryption.', 'onemcp'),
				'search'  => 'fluent smtp',
				'active'  => function () {
					return defined('FLUENTMAIL') || defined('FLUENTMAIL_PLUGIN_VERSION') || in_array('fluent-smtp/fluent-smtp.php', Helper::active_plugin_files(), true);
				},
				'version' => defined('FLUENTMAIL_PLUGIN_VERSION') ? FLUENTMAIL_PLUGIN_VERSION : '',
			),
			'easy-wp-smtp' => array(
				'label'   => 'Easy WP SMTP',
				'group'   => 'smtp',
				'summary' => __('From address, SMTP host, port, encryption, and authentication.', 'onemcp'),
				'search'  => 'easy wp smtp',
				'active'  => function () {
					return defined('EASY_WP_SMTP_VERSION') || function_exists('easy_wp_smtp') || function_exists('swpsmtp_init') || in_array('easy-wp-smtp/easy-wp-smtp.php', Helper::active_plugin_files(), true);
				},
				'version' => defined('EASY_WP_SMTP_VERSION') ? EASY_WP_SMTP_VERSION : '',
			),
			'post-smtp' => array(
				'label'   => 'Post SMTP',
				'group'   => 'smtp',
				'summary' => __('Sender, host, port, security, and authentication.', 'onemcp'),
				'search'  => 'post smtp',
				'active'  => function () {
					return defined('POST_SMTP_VER') || class_exists('PostmanOptions') || in_array('post-smtp/postman-smtp.php', Helper::active_plugin_files(), true);
				},
				'version' => defined('POST_SMTP_VER') ? POST_SMTP_VER : '',
			),
			'smtp-mailer' => array(
				'label'   => 'SMTP Mailer',
				'group'   => 'smtp',
				'summary' => __('SMTP host, port, encryption, from address, and authentication.', 'onemcp'),
				'search'  => 'smtp mailer',
				'active'  => function () {
					return defined('SMTP_MAILER_VERSION') || class_exists('SMTP_MAILER') || in_array('smtp-mailer/main.php', Helper::active_plugin_files(), true);
				},
				'version' => defined('SMTP_MAILER_VERSION') ? SMTP_MAILER_VERSION : '',
			),
			'speedycache' => array(
				'label'    => 'SpeedyCache',
				'group'    => 'cache',
				'summary'  => __('Page cache, preload, minify, and a full or single-page purge.', 'onemcp'),
				'search'   => 'speedycache',
				'featured' => true,
				'active'   => function () {
					return defined('SPEEDYCACHE_VERSION') || class_exists('\SpeedyCache\Delete') || class_exists('SpeedyCache') || in_array('speedycache/speedycache.php', Helper::active_plugin_files(), true);
				},
				'version'  => defined('SPEEDYCACHE_VERSION') ? SPEEDYCACHE_VERSION : '',
			),
			'litespeed' => array(
				'label'   => 'LiteSpeed Cache',
				'group'   => 'cache',
				'summary' => __('LiteSpeed page cache, mobile cache, and purge.', 'onemcp'),
				'search'  => 'litespeed cache',
				'active'  => function () {
					return defined('LSCWP_V') || class_exists('\LiteSpeed\Purge') || in_array('litespeed-cache/litespeed-cache.php', Helper::active_plugin_files(), true);
				},
				'version' => defined('LSCWP_V') ? LSCWP_V : '',
			),
			'wp-rocket' => array(
				'label'   => 'WP Rocket',
				'group'   => 'cache',
				'summary' => __('WP Rocket page cache, preload, and purge.', 'onemcp'),
				'search'  => 'wp rocket',
				'active'  => function () {
					return defined('WP_ROCKET_VERSION') || function_exists('rocket_clean_domain') || in_array('wp-rocket/wp-rocket.php', Helper::active_plugin_files(), true);
				},
				'version' => defined('WP_ROCKET_VERSION') ? WP_ROCKET_VERSION : '',
			),
			'wp-super-cache' => array(
				'label'   => 'WP Super Cache',
				'group'   => 'cache',
				'summary' => __('WP Super Cache page cache and purge.', 'onemcp'),
				'search'  => 'wp super cache',
				'active'  => function () {
					return defined('WPCACHEHOME') || function_exists('wp_cache_clear_cache') || in_array('wp-super-cache/wp-cache.php', Helper::active_plugin_files(), true);
				},
				'version' => defined('WPSC_VERSION_ID') ? WPSC_VERSION_ID : '',
			),
			'w3-total-cache' => array(
				'label'   => 'W3 Total Cache',
				'group'   => 'cache',
				'summary' => __('W3 Total Cache page cache and flush.', 'onemcp'),
				'search'  => 'w3 total cache',
				'active'  => function () {
					return defined('W3TC_VERSION') || function_exists('w3tc_flush_all') || in_array('w3-total-cache/w3-total-cache.php', Helper::active_plugin_files(), true);
				},
				'version' => defined('W3TC_VERSION') ? W3TC_VERSION : '',
			),
			'wp-fastest-cache' => array(
				'label'   => 'WP Fastest Cache',
				'group'   => 'cache',
				'summary' => __('WP Fastest Cache page cache, minify, and purge.', 'onemcp'),
				'search'  => 'wp fastest cache',
				'active'  => function () {
					return defined('WPFC_MAIN_PATH') || defined('WPFC_WP_PLUGIN_DIR') || isset($GLOBALS['wp_fastest_cache']) || in_array('wp-fastest-cache/wpFastestCache.php', Helper::active_plugin_files(), true);
				},
				'version' => defined('WPFC_VERSION') ? WPFC_VERSION : '',
			),
			'wp-optimize' => array(
				'label'   => 'WP-Optimize',
				'group'   => 'cache',
				'summary' => __('WP-Optimize page cache and purge.', 'onemcp'),
				'search'  => 'wp-optimize',
				'active'  => function () {
					return defined('WPO_VERSION') || function_exists('wpo_cache_flush') || in_array('wp-optimize/wp-optimize.php', Helper::active_plugin_files(), true);
				},
				'version' => defined('WPO_VERSION') ? WPO_VERSION : '',
			),
			'siteground' => array(
				'label'   => 'SiteGround Optimizer',
				'group'   => 'cache',
				'summary' => __('SiteGround dynamic cache and purge.', 'onemcp'),
				'search'  => 'siteground optimizer',
				'active'  => function () {
					return defined('SG_CACHEPRESS_VERSION') || function_exists('sg_cachepress_purge_cache') || in_array('sg-cachepress/sg-cachepress.php', Helper::active_plugin_files(), true);
				},
				'version' => defined('SG_CACHEPRESS_VERSION') ? SG_CACHEPRESS_VERSION : '',
			),
			'woocommerce' => array(
				'label'   => 'WooCommerce',
				'group'   => 'stores',
				'summary' => __('Products, variations, orders, customers, coupons, and sales. These abilities load with OneMCP Pro.', 'onemcp'),
				'search'  => 'woocommerce',
				'active'  => function () {
					return class_exists('WooCommerce') || defined('WC_VERSION');
				},
				'version' => defined('WC_VERSION') ? WC_VERSION : '',
			),
			'edd' => array(
				'label'   => 'Easy Digital Downloads',
				'group'   => 'stores',
				'summary' => __('Downloads, orders, discounts, licenses, and subscriptions. These abilities load with OneMCP Pro.', 'onemcp'),
				'search'  => 'easy digital downloads',
				'active'  => function () {
					return function_exists('EDD') || defined('EDD_VERSION');
				},
				'version' => defined('EDD_VERSION') ? EDD_VERSION : '',
			),
			'backuply' => array(
				'label'   => 'Backuply',
				'group'   => 'backups',
				'summary' => __('List, create, stop, delete, and restore backups. These abilities load with OneMCP Pro.', 'onemcp'),
				'search'  => 'backuply',
				'active'  => function () {
					return defined('BACKUPLY_VERSION');
				},
				'version' => defined('BACKUPLY_VERSION') ? BACKUPLY_VERSION : '',
			),
			'duplicator' => array(
				'label'   => 'Duplicator',
				'group'   => 'backups',
				'summary' => __('List, create, and delete packages. These abilities load with OneMCP Pro.', 'onemcp'),
				'search'  => 'duplicator',
				'active'  => function () {
					return defined('DUPLICATOR_VERSION') || defined('DUPLICATOR_PRO_VERSION');
				},
				'version' => defined('DUPLICATOR_VERSION') ? DUPLICATOR_VERSION : (defined('DUPLICATOR_PRO_VERSION') ? DUPLICATOR_PRO_VERSION : ''),
			),
		);
	}

	/**
	 * Whether a supported product is loaded.
	 *
	 * @param string $slug Product slug.
	 * @return bool
	 */
	static function is_active($slug) {
		if ($slug === 'pagelayer' && self::is_sitepad()) {
			return defined('PAGELAYER_VERSION') || class_exists('Pagelayer');
		}
		$products = self::products();
		if (!isset($products[$slug]['active']) || !is_callable($products[$slug]['active'])) {
			return false;
		}
		return (bool) call_user_func($products[$slug]['active']);
	}

	/**
	 * Active product slugs in one group.
	 *
	 * @param string $group builders, seo, smtp, cache, stores, or backups.
	 * @return array<int,string>
	 */
	static function active_slugs($group) {
		$slugs = array();
		foreach (self::products() as $slug => $product) {
			if ($product['group'] === $group && self::is_active($slug)) {
				$slugs[] = $slug;
			}
		}
		return $slugs;
	}

	/**
	 * Admin card data for every supported product.
	 *
	 * @return array<int,array<string,mixed>>
	 */
	static function cards() {
		$cards = array();
		foreach (self::products() as $slug => $product) {
			$active = self::is_active($slug);
			$cards[] = array(
				'slug'    => $slug,
				'label'   => $product['label'],
				'group'   => $product['group'],
				'summary'  => $product['summary'],
				'version'  => $active ? (string) $product['version'] : '',
				'active'   => $active,
				'featured' => !empty($product['featured']),
				'search'   => admin_url('plugin-install.php?s=' . rawurlencode($product['search']) . '&tab=search&type=term'),
			);
		}
		return $cards;
	}

	/**
	 * Compact status list for the site-info ability.
	 *
	 * @return array<int,array<string,mixed>>
	 */
	 static function status_list() {
		$list = array();
		foreach (self::products() as $slug => $product) {
			$active = self::is_active($slug);
			$list[] = array(
				'slug'    => $slug,
				'label'   => $product['label'],
				'group'   => $product['group'],
				'active'  => $active,
				'version' => $active ? (string) $product['version'] : '',
			);
		}
		return $list;
	}
}
