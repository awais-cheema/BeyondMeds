<?php
/**
 * Registers OneMCP Pro integrations with the free plugin.
 *
 * @package OneMCPPro
 */

namespace OneMCPPro;

if (!defined('ABSPATH')) {
	exit;
}

class Plugin {

	/**
	 * Register Pro hooks after the free plugin has loaded.
	 *
	 * @return void
	 */
	public static function hooks() {
		add_filter('onemcp_sources', '\OneMCPPro\Plugin::sources');
		add_filter('onemcp_categories', '\OneMCPPro\Plugin::categories');
		add_filter('onemcp_audit_keep', '\OneMCPPro\Plugin::audit_keep');
		add_action('onemcp_activity_toolbar', '\OneMCPPro\Plugin::export_button');
		add_action('onemcp_render_license_page', '\OneMCPPro\License::template');
		add_action('admin_enqueue_scripts', '\OneMCPPro\Plugin::enqueue_admin_assets');
		add_action('admin_init', '\OneMCPPro\Plugin::export_log', 5);
		add_filter('plugin_action_links_' . plugin_basename(ONEMCP_PRO_FILE), array(__CLASS__, 'links'));
	}

	/**
	 * Integration classes. The OneMCPPro autoloader loads each one.
	 *
	 * @param array<int,string> $sources Free plugin sources.
	 * @return array<int,string>
	 */
	public static function sources($sources) {
		if (!is_array($sources)) {
			$sources = array();
		}
		$classes = defined('SITEPAD')
			? array('Builders', 'Pagelayer', 'SEO', 'SMTP', 'Cache')
			: array('Builders', 'Pagelayer', 'SEO', 'SMTP', 'Cache', 'WooCommerce', 'Backuply', 'Duplicator', 'EDD');
		foreach ($classes as $class) {
			$sources[] = __NAMESPACE__ . '\\' . $class;
		}
		return $sources;
	}

	/**
	 * Ability categories for the plugin integrations.
	 *
	 * @param array<string,array{label:string,description:string}> $categories Free categories.
	 * @return array<string,array{label:string,description:string}>
	 */
	public static function categories($categories) {
		if (!is_array($categories)) {
			$categories = array();
		}
		if (defined('SITEPAD')) {
			$categories['onemcp-seo'] = array(
				'label'       => __('SEO', 'onemcp-pro'),
				'description' => __('SiteSEO titles, social tags, robots, and breadcrumbs.', 'onemcp-pro'),
			);
			$categories['onemcp-smtp'] = array(
				'label'       => __('SMTP', 'onemcp-pro'),
				'description' => __('GoSMTP from address, host, port, encryption, and a test email.', 'onemcp-pro'),
			);
			$categories['onemcp-cache'] = array(
				'label'       => __('Cache', 'onemcp-pro'),
				'description' => __('SpeedyCache page cache, preload, minify, and purge.', 'onemcp-pro'),
			);
			return $categories;
		}
		$categories['onemcp-builders'] = array(
			'label'       => __('Page builders', 'onemcp-pro'),
			'description' => __('Pagelayer, Elementor, SeedProd, Divi, Bricks, Beaver Builder, and Breakdance. Create any post type and any theme template, and build them with that editor\'s widgets.', 'onemcp-pro'),
		);
		$categories['onemcp-pagelayer-pages'] = array(
			'label'       => __('Pagelayer pages', 'onemcp-pro'),
			'description' => __('Create, update, publish, and edit pages built with Pagelayer.', 'onemcp-pro'),
		);
		$categories['onemcp-pagelayer-posts'] = array(
			'label'       => __('Pagelayer posts', 'onemcp-pro'),
			'description' => __('Create, update, list, and publish blog posts built with Pagelayer.', 'onemcp-pro'),
		);
		$categories['onemcp-pagelayer-templates'] = array(
			'label'       => __('Pagelayer theme templates', 'onemcp-pro'),
			'description' => __('Header, footer, archive, single, and other theme builder templates.', 'onemcp-pro'),
		);
		$categories['onemcp-pagelayer-menus'] = array(
			'label'       => __('Pagelayer menus', 'onemcp-pro'),
			'description' => __('Navigation menus used by Pagelayer headers and footers.', 'onemcp-pro'),
		);
		$categories['onemcp-pagelayer-widgets'] = array(
			'label'       => __('Pagelayer widgets', 'onemcp-pro'),
			'description' => __('Widget lists, schemas, and examples.', 'onemcp-pro'),
		);
		$categories['onemcp-pagelayer-global'] = array(
			'label'       => __('Pagelayer theme and styles', 'onemcp-pro'),
			'description' => __('Theme settings, global colors, fonts, icons, and presets.', 'onemcp-pro'),
		);
		$categories['onemcp-pagelayer-media'] = array(
			'label'       => __('Pagelayer media', 'onemcp-pro'),
			'description' => __('Upload images into the media library for Pagelayer layouts.', 'onemcp-pro'),
		);
		$categories['onemcp-seo'] = array(
			'label'       => __('SEO', 'onemcp-pro'),
			'description' => __('SiteSEO, Yoast SEO, Rank Math, All in One SEO, and SEOPress titles, social tags, robots, and cornerstone content.', 'onemcp-pro'),
		);
		$categories['onemcp-smtp'] = array(
			'label'       => __('SMTP', 'onemcp-pro'),
			'description' => __('GoSMTP, WP Mail SMTP, FluentSMTP, Easy WP SMTP, Post SMTP, and SMTP Mailer.', 'onemcp-pro'),
		);
		$categories['onemcp-cache'] = array(
			'label'       => __('Cache', 'onemcp-pro'),
			'description' => __('SpeedyCache, LiteSpeed Cache, WP Rocket, WP Super Cache, W3 Total Cache, WP Fastest Cache, WP-Optimize, and SiteGround Optimizer.', 'onemcp-pro'),
		);
		$categories['onemcp-woocommerce'] = array(
			'label'       => __('WooCommerce', 'onemcp-pro'),
			'description' => __('Products, variations, prices, stock, categories, orders, customers, coupons, reports, reviews, refunds, and store settings.', 'onemcp-pro'),
		);
		$categories['onemcp-edd'] = array(
			'label'       => __('Easy Digital Downloads', 'onemcp-pro'),
			'description' => __('Downloads, prices, files, orders, customers, discounts, reports, download logs, access, licenses, and subscriptions.', 'onemcp-pro'),
		);
		$categories['onemcp-backups'] = array(
			'label'       => __('Backups', 'onemcp-pro'),
			'description' => __('Backuply and Duplicator backups.', 'onemcp-pro'),
		);
		return $categories;
	}

	/**
	 * Keep a longer activity log while Pro is active.
	 *
	 * @return int
	 */
	public static function audit_keep() {
		return 2000;
	}

	/**
	 * Settings link on the plugins screen.
	 *
	 * @param array<int,string> $links Existing links.
	 * @return array<int,string>
	 */
	public static function links($links) {
		if (!defined('ONEMCP_VERSION')) {
			return $links;
		}
		$url = admin_url('admin.php?page=onemcp&tab=abilities');
		array_unshift($links, '<a href="' . esc_url($url) . '">' . esc_html__('Abilities', 'onemcp-pro') . '</a>');
		if (!defined('SITEPAD')) {
			$license = admin_url('admin.php?page=onemcp&tab=license');
			array_unshift($links, '<a href="' . esc_url($license) . '">' . esc_html__('License', 'onemcp-pro') . '</a>');
		}
		return $links;
	}

	/**
	 * License screen styles on the OneMCP admin page.
	 *
	 * @param string $hook Current admin page hook.
	 * @return void
	 */
	public static function enqueue_admin_assets($hook) {
		if ($hook !== 'toplevel_page_onemcp') {
			return;
		}
		wp_enqueue_style('onemcp-pro-admin', ONEMCP_PRO_URL . 'assets/css/admin.css', array('onemcp-admin'), ONEMCP_PRO_VERSION);
	}

	/**
	 * CSV download control on the activity screen.
	 *
	 * @return void
	 */
	public static function export_button() {
		echo '<form method="post" class="onemcp-inline-form">';
		wp_nonce_field('onemcp_admin', 'onemcp_nonce');
		echo '<input type="hidden" name="onemcp_action" value="export-log">';
		echo '<button type="submit" class="onemcp-btn">' . esc_html__('Export CSV', 'onemcp-pro') . '</button>';
		echo '</form>';
	}

	/**
	 * Download the activity log as CSV.
	 *
	 * @return void
	 */
	public static function export_log() {
		if (!is_admin() || !current_user_can('manage_options') || empty($_POST['onemcp_action'])) {
			return;
		}
		$action = sanitize_key(wp_unslash($_POST['onemcp_action']));
		if ($action !== 'export-log') {
			return;
		}
		check_admin_referer('onemcp_admin', 'onemcp_nonce');

		global $wpdb;

		if (!class_exists('\ONEMCP\Audit') || get_option('onemcp_db_version') === false) {
			return;
		}

		$rows = $wpdb->get_results(
			$wpdb->prepare(
				'SELECT id, ability, user_id, ok, message, created_at FROM ' . esc_sql(\ONEMCP\Audit::table()) . ' ORDER BY id DESC LIMIT %d',
				2000
			),
			ARRAY_A
		);
		if (!is_array($rows)) {
			$rows = array();
		}

		nocache_headers();
		header('Content-Type: text/csv; charset=utf-8');
		header('Content-Disposition: attachment; filename=onemcp-activity.csv');

		$output = fopen('php://output', 'w');
		if ($output === false) {
			exit;
		}
		$escape_csv = function ($value) {
			$str = (string) $value;
			if ($str !== '' && in_array($str[0], array('=', '+', '-', '@', "\t", "\r"), true)) {
				return "'" . $str;
			}
			return $str;
		};

		fputcsv($output, array('id', 'ability', 'user_id', 'ok', 'message', 'created_at'));
		foreach ($rows as $row) {
			fputcsv($output, array(
				$escape_csv(isset($row['id']) ? $row['id'] : ''),
				$escape_csv(isset($row['ability']) ? $row['ability'] : ''),
				$escape_csv(isset($row['user_id']) ? $row['user_id'] : ''),
				$escape_csv(isset($row['ok']) ? $row['ok'] : ''),
				$escape_csv(isset($row['message']) ? $row['message'] : ''),
				$escape_csv(isset($row['created_at']) ? $row['created_at'] : ''),
			));
		}
		fclose($output);
		exit;
	}
}
