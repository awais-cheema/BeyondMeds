<?php
/**
 * Page cache plugins.
 *
 * Reads public cache settings and purges cache through each plugin's own API.
 * Secrets such as CDN tokens are not returned. SpeedyCache is first.
 *
 * @package OneMCPPro
 */

namespace OneMCPPro;

use ONEMCP\Detector;
use ONEMCP\Helper;

if (!defined('ABSPATH')) {
	exit;
}

class Cache {

	/**
	 * Ability definitions for active cache plugins.
	 *
	 * @return array<int,array<string,mixed>>
	 */
	static function definitions() {
		$definitions = array();
		$active      = false;
		foreach (self::plugins() as $slug => $plugin) {
			if (!Detector::is_active($slug)) {
				continue;
			}
			$active = true;
			$definitions[] = Helper::ability(
				'get-' . $slug,
				sprintf(
					/* translators: %s: cache plugin name. */
					__('Get %s cache', 'onemcp-pro'),
					$plugin['label']
				),
				sprintf(
					/* translators: %s: cache plugin name. */
					__('Reads whether %s caching is on, plus mobile, preload, and minify flags. CDN tokens are not included.', 'onemcp-pro'),
					$plugin['label']
				),
				'onemcp-cache',
				true,
				function ($input) use ($slug) {
					unset($input);
					return Cache::read($slug);
				},
				array(),
				array(),
				array('cap' => 'manage_options')
			);
			$definitions[] = Helper::ability(
				'purge-' . $slug,
				sprintf(
					/* translators: %s: cache plugin name. */
					__('Purge %s cache', 'onemcp-pro'),
					$plugin['label']
				),
				sprintf(
					/* translators: %s: cache plugin name. */
					__('Purges the %s page cache. Send post_id or url to purge one page. Omit both to purge the whole cache.', 'onemcp-pro'),
					$plugin['label']
				),
				'onemcp-cache',
				false,
				function ($input) use ($slug) {
					return Cache::purge($slug, $input);
				},
				self::purge_properties(),
				array(),
				array('cap' => 'manage_options', 'destructive' => true)
			);
		}

		if ($active) {
			$definitions[] = Helper::ability(
				'get-cache',
				__('Get cache status', 'onemcp-pro'),
				__('Reads the public cache settings of every active cache plugin. SpeedyCache is listed first when it is active.', 'onemcp-pro'),
				'onemcp-cache',
				true,
				array(__CLASS__, 'read_all'),
				array(),
				array(),
				array('cap' => 'manage_options')
			);
			$definitions[] = Helper::ability(
				'purge-cache',
				__('Purge cache', 'onemcp-pro'),
				__('Purges every active cache plugin. Send post_id or url to purge one page. Omit both to purge the whole cache. SpeedyCache is purged first when it is active.', 'onemcp-pro'),
				'onemcp-cache',
				false,
				array(__CLASS__, 'purge_all'),
				self::purge_properties(),
				array(),
				array('cap' => 'manage_options', 'destructive' => true)
			);
		}

		return $definitions;
	}

	/**
	 * Read one plugin.
	 *
	 * @param string $slug Plugin slug.
	 * @return array<string,mixed>|WP_Error
	 */
	static function read($slug) {
		if (!isset(self::plugins()[$slug]) || !Detector::is_active($slug)) {
			return Helper::error('onemcp_unavailable', __('That cache plugin is not active.', 'onemcp-pro'));
		}
		return self::view($slug);
	}

	/**
	 * Read every active cache plugin. SpeedyCache comes first.
	 *
	 * @param array<string,mixed> $input Input.
	 * @return array<string,mixed>
	 */
	static function read_all($input) {
		unset($input);
		$plugins = array();
		foreach (self::plugins() as $slug => $plugin) {
			if (!Detector::is_active($slug)) {
				continue;
			}
			$plugins[] = self::view($slug);
		}
		return array(
			'plugins' => $plugins,
		);
	}

	/**
	 * Purge one plugin.
	 *
	 * @param string              $slug  Plugin slug.
	 * @param array<string,mixed> $input Input.
	 * @return array<string,mixed>|WP_Error
	 */
	static function purge($slug, $input) {
		if (!isset(self::plugins()[$slug]) || !Detector::is_active($slug)) {
			return Helper::error('onemcp_unavailable', __('That cache plugin is not active.', 'onemcp-pro'));
		}
		$target = self::target(is_array($input) ? $input : array());
		if (is_wp_error($target)) {
			return $target;
		}
		return self::purge_one($slug, $target, is_array($input) ? $input : array());
	}

	/**
	 * Purge every active cache plugin. SpeedyCache runs first.
	 *
	 * @param array<string,mixed> $input Input.
	 * @return array<string,mixed>|WP_Error
	 */
	static function purge_all($input) {
		$input  = is_array($input) ? $input : array();
		$target = self::target($input);
		if (is_wp_error($target)) {
			return $target;
		}
		$results = array();
		foreach (self::plugins() as $slug => $plugin) {
			if (!Detector::is_active($slug)) {
				continue;
			}
			$result = self::purge_one($slug, $target, $input);
			if (is_wp_error($result)) {
				$results[] = array(
					'plugin'  => $slug,
					'label'   => $plugin['label'],
					'purged'  => false,
					'message' => $result->get_error_message(),
				);
				continue;
			}
			$results[] = $result;
		}
		if ($results === array()) {
			return Helper::error('onemcp_unavailable', __('No supported cache plugin is active.', 'onemcp-pro'));
		}
		return array(
			'scope'   => $target['scope'],
			'post_id' => $target['post_id'],
			'url'     => $target['url'],
			'plugins' => $results,
		);
	}

	/**
	 * Supported plugins. SpeedyCache is first.
	 *
	 * @return array<string,array<string,string>>
	 */
	private static function plugins() {
		return array(
			'speedycache' => array(
				'label' => 'SpeedyCache',
			),
			'litespeed' => array(
				'label' => 'LiteSpeed Cache',
			),
			'wp-rocket' => array(
				'label' => 'WP Rocket',
			),
			'wp-super-cache' => array(
				'label' => 'WP Super Cache',
			),
			'w3-total-cache' => array(
				'label' => 'W3 Total Cache',
			),
			'wp-fastest-cache' => array(
				'label' => 'WP Fastest Cache',
			),
			'wp-optimize' => array(
				'label' => 'WP-Optimize',
			),
			'siteground' => array(
				'label' => 'SiteGround Optimizer',
			),
		);
	}

	/**
	 * Fields a purge ability accepts.
	 *
	 * @return array<string,array<string,mixed>>
	 */
	private static function purge_properties() {
		return array(
			'post_id'   => Helper::int_prop(__('Post or page ID to purge. Use 0 for the homepage. Omit, along with url, to purge the whole cache.', 'onemcp-pro')),
			'url'       => Helper::string_prop(__('A URL on this site to purge. Omit, along with post_id, to purge the whole cache.', 'onemcp-pro'), 500),
			'minified'  => Helper::bool_prop(__('Also delete minified CSS and JS. Used by SpeedyCache and WP Fastest Cache on a full purge.', 'onemcp-pro')),
			'fonts'     => Helper::bool_prop(__('Also delete locally cached fonts. Used by SpeedyCache on a full purge.', 'onemcp-pro')),
			'gravatars' => Helper::bool_prop(__('Also delete cached gravatars. Used by SpeedyCache on a full purge.', 'onemcp-pro')),
			'preload'   => Helper::bool_prop(__('Rebuild the SpeedyCache preload list after a full purge.', 'onemcp-pro')),
		);
	}

	/**
	 * Public settings for one plugin.
	 *
	 * @param string $slug Plugin slug.
	 * @return array<string,mixed>
	 */
	private static function view($slug) {
		$plugins = self::plugins();
		$base    = array(
			'plugin' => $slug,
			'label'  => $plugins[$slug]['label'],
		);
		if ($slug === 'speedycache') {
			return array_merge($base, self::view_speedycache());
		}
		if ($slug === 'litespeed') {
			return array_merge($base, self::view_litespeed());
		}
		if ($slug === 'wp-rocket') {
			return array_merge($base, self::view_rocket());
		}
		if ($slug === 'wp-super-cache') {
			return array_merge($base, self::view_super_cache());
		}
		if ($slug === 'w3-total-cache') {
			return array_merge($base, self::view_w3tc());
		}
		if ($slug === 'wp-fastest-cache') {
			return array_merge($base, self::view_fastest());
		}
		if ($slug === 'wp-optimize') {
			return array_merge($base, self::view_wp_optimize());
		}
		if ($slug === 'siteground') {
			return array_merge($base, self::view_siteground());
		}
		return $base;
	}

	/**
	 * SpeedyCache settings and cache size. Credentials are left out.
	 *
	 * @return array<string,mixed>
	 */
	private static function view_speedycache() {
		$options = get_option('speedycache_options', array());
		if (!is_array($options)) {
			$options = array();
		}
		$cdn = get_option('speedycache_cdn', array());
		if (!is_array($cdn)) {
			$cdn = array();
		}
		$excludes = get_option('speedycache_exclude', array());
		$queued   = get_transient('speedycache_preload_transient');
		$size     = (int) get_option('speedycache_html_size', 0);

		return array(
			'cache_enabled'       => !empty($options['status']),
			'gzip'                => !empty($options['gzip']),
			'logged_in_user'      => !empty($options['logged_in_user']),
			'mobile'              => !empty($options['mobile']),
			'mobile_theme'        => !empty($options['mobile_theme']),
			'browser_cache'       => !empty($options['lbc']),
			'purge_varnish'       => !empty($options['purge_varnish']),
			'varnish_ip'          => isset($options['varniship']) ? Helper::text($options['varniship'], 80) : '',
			'preload'             => !empty($options['preload']),
			'preload_queued'      => is_array($queued) ? count($queued) : 0,
			'cache_lifespan'      => isset($options['purge_interval']) ? (int) $options['purge_interval'] : 0,
			'cache_lifespan_unit' => isset($options['purge_interval_unit']) ? Helper::text($options['purge_interval_unit'], 20) : '',
			'minify_html'         => !empty($options['minify_html']),
			'minify_css'          => !empty($options['minify_css']),
			'minify_js'           => !empty($options['minify_js']),
			'combine_css'         => !empty($options['combine_css']),
			'combine_js'          => !empty($options['combine_js']),
			'lazy_load'           => !empty($options['lazy_load']),
			'delay_js'            => !empty($options['delay_js']),
			'cdn_enabled'         => !empty($cdn['enabled']),
			'cdn_url'             => isset($cdn['cdn_url']) ? esc_url_raw((string) $cdn['cdn_url']) : '',
			'exclude_rules'       => is_array($excludes) ? count($excludes) : 0,
			'cached_pages'        => (int) get_option('speedycache_html', 0),
			'cache_size_kb'       => round($size / 1000, 2),
		);
	}

	/**
	 * LiteSpeed Cache flags stored as individual options.
	 *
	 * @return array<string,mixed>
	 */
	private static function view_litespeed() {
		return array(
			'cache_enabled'  => self::option_on('litespeed.conf.cache'),
			'mobile'         => self::option_on('litespeed.conf.cache-mobile'),
			'logged_in_user' => self::option_on('litespeed.conf.cache-priv'),
			'object_cache'   => self::option_on('litespeed.conf.object'),
			'browser_cache'  => self::option_on('litespeed.conf.cache-browser'),
		);
	}

	/**
	 * WP Rocket public flags. API keys stay in the option.
	 *
	 * @return array<string,mixed>
	 */
	private static function view_rocket() {
		$settings = get_option('wp_rocket_settings', array());
		if (!is_array($settings)) {
			$settings = array();
		}
		return array(
			'cache_enabled'  => true,
			'mobile'         => !empty($settings['cache_mobile']),
			'logged_in_user' => !empty($settings['cache_logged_user']),
			'preload'        => !empty($settings['manual_preload']),
			'minify_css'     => !empty($settings['minify_css']),
			'minify_js'      => !empty($settings['minify_js']),
			'lazy_load'      => !empty($settings['lazyload']),
			'delay_js'       => !empty($settings['delay_js']),
			'cdn_enabled'    => !empty($settings['cdn']),
		);
	}

	/**
	 * WP Super Cache switches from its runtime config.
	 *
	 * @return array<string,mixed>
	 */
	private static function view_super_cache() {
		global $cache_enabled, $super_cache_enabled, $wp_cache_mod_rewrite, $cache_max_time;
		return array(
			'cache_enabled' => !empty($cache_enabled),
			'supercache'    => !empty($super_cache_enabled),
			'mod_rewrite'   => !empty($wp_cache_mod_rewrite),
			'cache_max_time'=> isset($cache_max_time) ? (int) $cache_max_time : 0,
		);
	}

	/**
	 * W3 Total Cache page-cache flag.
	 *
	 * @return array<string,mixed>
	 */
	private static function view_w3tc() {
		$enabled = null;
		$object  = null;
		if (function_exists('w3tc_config')) {
			$config = w3tc_config();
			if (is_object($config) && method_exists($config, 'get_boolean')) {
				$enabled = (bool) $config->get_boolean('pgcache.enabled');
				$object  = (bool) $config->get_boolean('objectcache.enabled');
			}
		}
		return array(
			'cache_enabled' => $enabled,
			'object_cache'  => $object,
		);
	}

	/**
	 * WP Fastest Cache status from its JSON option.
	 *
	 * @return array<string,mixed>
	 */
	private static function view_fastest() {
		$stored = get_option('WpFastestCache', '');
		$data   = is_string($stored) ? json_decode($stored, true) : $stored;
		if (!is_array($data)) {
			$data = array();
		}
		return array(
			'cache_enabled' => !empty($data['wpFastestCacheStatus']),
			'mobile'        => !empty($data['wpFastestCacheMobile']),
			'preload'       => !empty($data['wpFastestCachePreload']),
			'minify_css'    => !empty($data['wpFastestCacheMinifyCss']),
			'minify_js'     => !empty($data['wpFastestCacheMinifyJs']),
		);
	}

	/**
	 * WP-Optimize page cache config.
	 *
	 * @return array<string,mixed>
	 */
	private static function view_wp_optimize() {
		$config = get_option('wpo_cache_config', array());
		if (!is_array($config)) {
			$config = array();
		}
		return array(
			'cache_enabled' => !empty($config['enable_page_caching']),
			'mobile'        => !empty($config['enable_mobile_caching']),
			'preload'       => !empty($config['enable_preload']),
		);
	}


	/**
	 * SiteGround Optimizer dynamic cache switch.
	 *
	 * @return array<string,mixed>
	 */
	private static function view_siteground() {
		return array(
			'cache_enabled' => self::option_on('siteground_optimizer_enable_cache'),
			'mobile'        => self::option_on('siteground_optimizer_file_caching'),
		);
	}


	/**
	 * Whether a WordPress option is stored as on.
	 *
	 * @param string $name Option name.
	 * @return bool
	 */
	private static function option_on($name) {
		$value = get_option($name, '');
		if (is_bool($value)) {
			return $value;
		}
		if (is_numeric($value)) {
			return (int) $value === 1;
		}
		return in_array(strtolower(trim((string) $value)), array('1', 'true', 'yes', 'on'), true);
	}

	/**
	 * Resolve a full purge, a post, or a same-site URL.
	 *
	 * @param array<string,mixed> $input Input.
	 * @return array<string,mixed>|WP_Error
	 */
	private static function target($input) {
		$has_post = array_key_exists('post_id', $input) && $input['post_id'] !== '' && $input['post_id'] !== null;
		$url      = '';
		if (isset($input['url']) && (string) $input['url'] !== '') {
			$url = self::site_url($input['url']);
			if ($url === null) {
				return Helper::error('onemcp_invalid', __('The cache URL must be a page on this site.', 'onemcp-pro'));
			}
		}
		if ($has_post) {
			$post_id = (int) $input['post_id'];
			if ($post_id < 0) {
				return Helper::error('onemcp_invalid', __('Post ID must be 0 for the homepage, or a positive ID.', 'onemcp-pro'));
			}
			if ($post_id > 0) {
				$post = get_post($post_id);
				if (!$post instanceof \WP_Post) {
					return Helper::error('onemcp_not_found', __('That post was not found.', 'onemcp-pro'));
				}
			}
			return array(
				'scope'   => 'post',
				'post_id' => $post_id,
				'url'     => $post_id > 0 ? (string) get_permalink($post_id) : (string) home_url('/'),
			);
		}
		if ($url !== '') {
			$post_id = url_to_postid($url);
			return array(
				'scope'   => 'url',
				'post_id' => $post_id > 0 ? $post_id : 0,
				'url'     => $url,
			);
		}
		return array(
			'scope'   => 'all',
			'post_id' => 0,
			'url'     => '',
		);
	}

	/**
	 * A URL on this site, or null when it points somewhere else.
	 *
	 * @param mixed $value Raw URL.
	 * @return string|null
	 */
	private static function site_url($value) {
		$url  = esc_url_raw(is_scalar($value) ? (string) $value : '');
		$home = wp_parse_url(home_url());
		$parts = wp_parse_url($url);
		if ($url === '' || empty($parts['host']) || empty($home['host'])) {
			return null;
		}
		if (strtolower((string) $parts['host']) !== strtolower((string) $home['host'])) {
			return null;
		}
		return $url;
	}

	/**
	 * Purge one plugin and describe what happened.
	 *
	 * @param string              $slug   Plugin slug.
	 * @param array<string,mixed> $target Resolved target.
	 * @param array<string,mixed> $input  Original input.
	 * @return array<string,mixed>|WP_Error
	 */
	private static function purge_one($slug, $target, $input) {
		$purged = false;
		$note   = '';
		if ($slug === 'speedycache') {
			$purged = self::purge_speedycache($target, $input, $note);
		} elseif ($slug === 'litespeed') {
			$purged = self::purge_litespeed($target, $note);
		} elseif ($slug === 'wp-rocket') {
			$purged = self::purge_rocket($target, $note);
		} elseif ($slug === 'wp-super-cache') {
			$purged = self::purge_super_cache($target, $note);
		} elseif ($slug === 'w3-total-cache') {
			$purged = self::purge_w3tc($target, $note);
		} elseif ($slug === 'wp-fastest-cache') {
			$purged = self::purge_fastest($target, $input, $note);
		} elseif ($slug === 'wp-optimize') {
			$purged = self::purge_wp_optimize($target, $note);
		} elseif ($slug === 'siteground') {
			$purged = self::purge_siteground($target, $note);
		}

		if (!$purged) {
			return Helper::error(
				'onemcp_failed',
				sprintf(
					/* translators: %s: cache plugin name. */
					__('%s did not expose a purge method.', 'onemcp-pro'),
					self::plugins()[$slug]['label']
				)
			);
		}

		$plugins = self::plugins();
		$message = $target['scope'] === 'all'
			? sprintf(
				/* translators: %s: cache plugin name. */
				__('Purged the %s cache.', 'onemcp-pro'),
				$plugins[$slug]['label']
			)
			: sprintf(
				/* translators: %s: cache plugin name. */
				__('Purged the %s cache for that page.', 'onemcp-pro'),
				$plugins[$slug]['label']
			);
		if ($note !== '') {
			$message .= ' ' . $note;
		}

		return array(
			'plugin'  => $slug,
			'label'   => $plugins[$slug]['label'],
			'purged'  => true,
			'scope'   => $target['scope'],
			'post_id' => $target['post_id'],
			'url'     => $target['url'],
			'message' => $message,
		);
	}

	/**
	 * SpeedyCache purge through Delete and, on request, Preload.
	 *
	 * @param array<string,mixed> $target Target.
	 * @param array<string,mixed> $input  Input.
	 * @param string              $note   Extra note.
	 * @return bool
	 */
	private static function purge_speedycache($target, $input, &$note) {
		if (!class_exists('\SpeedyCache\Delete')) {
			if ($target['scope'] === 'all' && function_exists('speedycache_delete_cache')) {
				speedycache_delete_cache(!empty($input['minified']));
				return true;
			}
			return false;
		}
		if ($target['scope'] === 'post' && method_exists('\SpeedyCache\Delete', 'cache')) {
			\SpeedyCache\Delete::cache((int) $target['post_id']);
			return true;
		}
		if ($target['scope'] === 'url' && method_exists('\SpeedyCache\Delete', 'url')) {
			\SpeedyCache\Delete::url(array($target['url']));
			return true;
		}
		if ($target['scope'] !== 'all' || !method_exists('\SpeedyCache\Delete', 'run')) {
			return false;
		}
		$actions = array();
		if (!empty($input['minified'])) {
			$actions['minified'] = true;
		}
		if (!empty($input['fonts'])) {
			$actions['font'] = true;
		}
		if (!empty($input['gravatars'])) {
			$actions['gravatars'] = true;
		}
		if (!empty($input['preload'])) {
			$actions['preload'] = true;
		}
		\SpeedyCache\Delete::run($actions);
		if (!empty($input['preload']) && class_exists('\SpeedyCache\Preload') && method_exists('\SpeedyCache\Preload', 'build_preload_list')) {
			\SpeedyCache\Preload::build_preload_list();
			$note = __('The preload list was rebuilt.', 'onemcp-pro');
		}
		return true;
	}

	/**
	 * LiteSpeed Cache purge hooks.
	 *
	 * @param array<string,mixed> $target Target.
	 * @param string              $note   Extra note.
	 * @return bool
	 */
	private static function purge_litespeed($target, &$note) {
		unset($note);
		if ($target['scope'] === 'post') {
			if (!has_action('litespeed_purge_post') && !(class_exists('\LiteSpeed\Purge') && method_exists('\LiteSpeed\Purge', 'purge_post'))) {
				return false;
			}
			do_action('litespeed_purge_post', (int) $target['post_id']);
			return true;
		}
		if ($target['scope'] === 'url') {
			if (!has_action('litespeed_purge_url')) {
				return false;
			}
			do_action('litespeed_purge_url', $target['url']);
			return true;
		}
		if (class_exists('\LiteSpeed\Purge') && method_exists('\LiteSpeed\Purge', 'purge_all')) {
			\LiteSpeed\Purge::purge_all();
			return true;
		}
		if (!has_action('litespeed_purge_all')) {
			return false;
		}
		do_action('litespeed_purge_all');
		return true;
	}

	/**
	 * WP Rocket purge functions.
	 *
	 * @param array<string,mixed> $target Target.
	 * @param string              $note   Extra note.
	 * @return bool
	 */
	private static function purge_rocket($target, &$note) {
		unset($note);
		if ($target['scope'] === 'post' && function_exists('rocket_clean_post')) {
			rocket_clean_post((int) $target['post_id']);
			return true;
		}
		if ($target['scope'] === 'url' && function_exists('rocket_clean_files')) {
			rocket_clean_files(array($target['url']));
			return true;
		}
		if ($target['scope'] === 'all' && function_exists('rocket_clean_domain')) {
			rocket_clean_domain();
			return true;
		}
		return false;
	}

	/**
	 * WP Super Cache purge. A single URL falls back to that post when one exists.
	 *
	 * @param array<string,mixed> $target Target.
	 * @param string              $note   Extra note.
	 * @return bool
	 */
	private static function purge_super_cache($target, &$note) {
		$post_id = (int) $target['post_id'];
		if ($target['scope'] !== 'all' && $post_id > 0) {
			if (function_exists('wpsc_delete_post_cache')) {
				wpsc_delete_post_cache($post_id);
				return true;
			}
			if (function_exists('wp_cache_post_change')) {
				wp_cache_post_change($post_id);
				return true;
			}
		}
		if (!function_exists('wp_cache_clear_cache')) {
			return false;
		}
		if (is_multisite()) {
			wp_cache_clear_cache(get_current_blog_id());
		} else {
			wp_cache_clear_cache();
		}
		if ($target['scope'] !== 'all') {
			$note = __('WP Super Cache cleared the whole cache for this site.', 'onemcp-pro');
		}
		return true;
	}

	/**
	 * W3 Total Cache flush helpers.
	 *
	 * @param array<string,mixed> $target Target.
	 * @param string              $note   Extra note.
	 * @return bool
	 */
	private static function purge_w3tc($target, &$note) {
		unset($note);
		if ($target['scope'] === 'post' && function_exists('w3tc_flush_post')) {
			w3tc_flush_post((int) $target['post_id']);
			return true;
		}
		if ($target['scope'] === 'url' && function_exists('w3tc_flush_url')) {
			w3tc_flush_url($target['url']);
			return true;
		}
		if ($target['scope'] === 'all' && function_exists('w3tc_flush_all')) {
			w3tc_flush_all();
			return true;
		}
		return false;
	}

	/**
	 * WP Fastest Cache purge. Minified files are included only on a full purge.
	 *
	 * @param array<string,mixed> $target Target.
	 * @param array<string,mixed> $input  Input.
	 * @param string              $note   Extra note.
	 * @return bool
	 */
	private static function purge_fastest($target, $input, &$note) {
		unset($note);
		$cache = isset($GLOBALS['wp_fastest_cache']) ? $GLOBALS['wp_fastest_cache'] : null;
		if ($target['scope'] !== 'all' && (int) $target['post_id'] > 0 && is_object($cache) && method_exists($cache, 'singleDeleteCache')) {
			$cache->singleDeleteCache(false, (int) $target['post_id']);
			return true;
		}
		if ($target['scope'] !== 'all') {
			return false;
		}
		$minified = !empty($input['minified']);
		if (is_object($cache) && method_exists($cache, 'deleteCache')) {
			$cache->deleteCache($minified);
			return true;
		}
		if (function_exists('wpfc_clear_all_cache')) {
			wpfc_clear_all_cache($minified);
			return true;
		}
		return false;
	}

	/**
	 * WP-Optimize page cache purge.
	 *
	 * @param array<string,mixed> $target Target.
	 * @param string              $note   Extra note.
	 * @return bool
	 */
	private static function purge_wp_optimize($target, &$note) {
		unset($note);
		if ($target['scope'] === 'post' && class_exists('WPO_Page_Cache') && method_exists('WPO_Page_Cache', 'delete_single_post_cache')) {
			\WPO_Page_Cache::delete_single_post_cache((int) $target['post_id']);
			return true;
		}
		if ($target['scope'] === 'url' && class_exists('WPO_Page_Cache') && method_exists('WPO_Page_Cache', 'delete_cache_by_url')) {
			\WPO_Page_Cache::delete_cache_by_url($target['url']);
			return true;
		}
		if ($target['scope'] !== 'all') {
			return false;
		}
		if (function_exists('wpo_cache_flush')) {
			wpo_cache_flush();
			return true;
		}
		if (function_exists('WP_Optimize')) {
			$optimize = WP_Optimize();
			if (is_object($optimize) && method_exists($optimize, 'get_page_cache')) {
				$page_cache = $optimize->get_page_cache();
				if (is_object($page_cache) && method_exists($page_cache, 'purge')) {
					$page_cache->purge();
					return true;
				}
			}
		}
		return false;
	}


	/**
	 * SiteGround Optimizer purge helper.
	 *
	 * @param array<string,mixed> $target Target.
	 * @param string              $note   Extra note.
	 * @return bool
	 */
	private static function purge_siteground($target, &$note) {
		unset($note);
		if (!function_exists('sg_cachepress_purge_cache')) {
			return false;
		}
		if ($target['scope'] === 'all') {
			sg_cachepress_purge_cache();
			return true;
		}
		if ($target['url'] === '') {
			return false;
		}
		sg_cachepress_purge_cache($target['url']);
		return true;
	}

}
