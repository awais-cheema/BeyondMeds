<?php
/**
 * SEO titles, descriptions, and social tags.
 *
 * @package OneMCPPro
 */

namespace OneMCPPro;

use ONEMCP\Detector;
use ONEMCP\Helper;
use Exception;

if (!defined('ABSPATH')) {
	exit;
}

class SEO {

	/**
	 * Ability definitions for active SEO plugins.
	 *
	 * @return array<int,array<string,mixed>>
	 */
	 static function definitions() {
		$definitions = array();
		foreach (self::plugins() as $slug => $plugin) {
			if (!\ONEMCP\Detector::is_active($slug)) {
				continue;
			}
			$definitions[] = Helper::ability(
				'get-' . $slug,
				sprintf(
					/* translators: %s: SEO plugin name. */
					__('Get %s fields', 'onemcp-pro'),
					$plugin['label']
				),
				sprintf(
					/* translators: %s: SEO plugin name. */
					__('Reads the %s title, description, focus phrase, social tags, images, canonical URL, breadcrumb, robots flags, and cornerstone flag for one post.', 'onemcp-pro'),
					$plugin['label']
				),
				'onemcp-seo',
				true,
				function ($input) use ($slug) {
					return SEO::read($slug, $input);
				},
				array(
					'post_id' => Helper::int_prop(__('Post or page ID.', 'onemcp-pro')),
				),
				array('post_id'),
				array('permission' => array('\\ONEMCP\\WordPress', 'can_edit'))
			);
			$definitions[] = Helper::ability(
				'update-' . $slug,
				sprintf(
					/* translators: %s: SEO plugin name. */
					__('Update %s fields', 'onemcp-pro'),
					$plugin['label']
				),
				sprintf(
					/* translators: %s: SEO plugin name. */
					__('Updates the %s fields you send. Fields you omit are left unchanged.', 'onemcp-pro'),
					$plugin['label']
				),
				'onemcp-seo',
				false,
				function ($input) use ($slug) {
					return SEO::write($slug, $input);
				},
				self::write_properties(),
				array('post_id'),
				array('permission' => array('\\ONEMCP\\WordPress', 'can_edit'))
			);
		}
		return $definitions;
	}

	/**
	 * Read SEO fields.
	 *
	 * @param string              $slug  Plugin slug.
	 * @param array<string,mixed> $input Input.
	 * @return array<string,mixed>|WP_Error
	 */
	static function read($slug, $input) {
		$post = Helper::editable_post(isset($input['post_id']) ? $input['post_id'] : 0);
		if (is_wp_error($post)) {
			return $post;
		}
		if ($slug === 'aioseo') {
			$model = self::aioseo_values($post->ID);
			if (is_array($model)) {
				$model['post_id'] = (int) $post->ID;
				$model['plugin']  = 'aioseo';
				return $model;
			}
		}
		return array_merge(
			array(
				'post_id' => (int) $post->ID,
				'plugin'  => $slug,
			),
			self::meta_values($post->ID, $slug)
		);
	}

	/**
	 * Update SEO fields.
	 *
	 * @param string              $slug  Plugin slug.
	 * @param array<string,mixed> $input Input.
	 * @return array<string,mixed>|WP_Error
	 */
	static function write($slug, $input) {
		$post = Helper::editable_post(isset($input['post_id']) ? $input['post_id'] : 0);
		if (is_wp_error($post)) {
			return $post;
		}
		$plugins = self::plugins();
		if (!isset($plugins[$slug])) {
			return Helper::error('onemcp_invalid', __('That SEO plugin is not supported.', 'onemcp-pro'));
		}

		$values = self::submitted_values($input);
		if ($values === array()) {
			return Helper::error('onemcp_invalid', __('Provide at least one SEO field to update.', 'onemcp-pro'));
		}
		$supported = array_keys($plugins[$slug]['fields']);
		if ($slug === 'aioseo') {
			$supported[] = 'breadcrumb';
		}
		foreach (array_keys($values) as $name) {
			if (!in_array($name, $supported, true)) {
				return Helper::error(
					'onemcp_invalid',
					sprintf(
						/* translators: %s: SEO plugin name. */
						__('%s does not store that field.', 'onemcp-pro'),
						$plugins[$slug]['label']
					)
				);
			}
		}

		if ($slug === 'aioseo' && self::save_aioseo($post->ID, $values)) {
			return self::read($slug, array('post_id' => $post->ID));
		}

		foreach ($plugins[$slug]['fields'] as $name => $meta_key) {
			if (!array_key_exists($name, $values)) {
				continue;
			}
			if ($name === 'noindex' || $name === 'nofollow' || $name === 'cornerstone') {
				if ($name === 'nofollow') {
					$stored = self::nofollow_storage($slug, (bool) $values[$name], $post->ID);
				} elseif ($name === 'cornerstone') {
					$stored = self::cornerstone_storage($slug, (bool) $values[$name]);
				} else {
					$stored = self::noindex_storage($slug, (bool) $values[$name], $post->ID);
				}
				if ($stored === '' || $stored === array()) {
					delete_post_meta($post->ID, $meta_key);
				} else {
					update_post_meta($post->ID, $meta_key, $stored);
				}
				continue;
			}
			update_post_meta($post->ID, $meta_key, $values[$name]);
		}

		return self::read($slug, array('post_id' => $post->ID));
	}

	/**
	 * Supported plugins and the post meta they read.
	 *
	 * @return array<string,array<string,mixed>>
	 */
	private static function plugins() {
		return array(
			'siteseo' => array(
				'label'  => 'SiteSEO',
				'fields' => array(
					'title'               => '_siteseo_titles_title',
					'description'         => '_siteseo_titles_desc',
					'keywords'            => '_siteseo_analysis_target_kw',
					'og_title'            => '_siteseo_social_fb_title',
					'og_description'      => '_siteseo_social_fb_desc',
					'twitter_title'       => '_siteseo_social_twitter_title',
					'twitter_description' => '_siteseo_social_twitter_desc',
					'canonical'           => '_siteseo_robots_canonical',
					'noindex'             => '_siteseo_robots_index',
					'nofollow'            => '_siteseo_robots_follow',
					'og_image'            => '_siteseo_social_fb_img',
					'twitter_image'       => '_siteseo_social_twitter_img',
					'breadcrumb'          => '_siteseo_robots_breadcrumbs',
				),
			),
			'yoast' => array(
				'label'  => 'Yoast SEO',
				'fields' => array(
					'title'               => '_yoast_wpseo_title',
					'description'         => '_yoast_wpseo_metadesc',
					'keywords'            => '_yoast_wpseo_focuskw',
					'og_title'            => '_yoast_wpseo_opengraph-title',
					'og_description'      => '_yoast_wpseo_opengraph-description',
					'twitter_title'       => '_yoast_wpseo_twitter-title',
					'twitter_description' => '_yoast_wpseo_twitter-description',
					'canonical'           => '_yoast_wpseo_canonical',
					'noindex'             => '_yoast_wpseo_meta-robots-noindex',
					'nofollow'            => '_yoast_wpseo_meta-robots-nofollow',
					'og_image'            => '_yoast_wpseo_opengraph-image',
					'twitter_image'       => '_yoast_wpseo_twitter-image',
					'breadcrumb'          => '_yoast_wpseo_bctitle',
					'cornerstone'         => '_yoast_wpseo_is_cornerstone',
				),
			),
			'rank-math' => array(
				'label'  => 'Rank Math',
				'fields' => array(
					'title'               => 'rank_math_title',
					'description'         => 'rank_math_description',
					'keywords'            => 'rank_math_focus_keyword',
					'og_title'            => 'rank_math_facebook_title',
					'og_description'      => 'rank_math_facebook_description',
					'twitter_title'       => 'rank_math_twitter_title',
					'twitter_description' => 'rank_math_twitter_description',
					'canonical'           => 'rank_math_canonical_url',
					'noindex'             => 'rank_math_robots',
					'nofollow'            => 'rank_math_robots',
					'og_image'            => 'rank_math_facebook_image',
					'twitter_image'       => 'rank_math_twitter_image',
					'breadcrumb'          => 'rank_math_breadcrumb_title',
					'cornerstone'         => 'rank_math_pillar_content',
				),
			),
			'aioseo' => array(
				'label'  => 'All in One SEO',
				'fields' => array(
					'title'               => '_aioseo_title',
					'description'         => '_aioseo_description',
					'keywords'            => '_aioseo_keywords',
					'og_title'            => '_aioseo_og_title',
					'og_description'      => '_aioseo_og_description',
					'twitter_title'       => '_aioseo_twitter_title',
					'twitter_description' => '_aioseo_twitter_description',
					'canonical'           => '_aioseo_canonical_url',
					'noindex'             => '_aioseo_noindex',
					'nofollow'            => '_aioseo_nofollow',
					'og_image'            => '_aioseo_og_image',
					'twitter_image'       => '_aioseo_twitter_image',
					'cornerstone'         => '_aioseo_pillar_content',
				),
			),
			'seopress' => array(
				'label'  => 'SEOPress',
				'fields' => array(
					'title'               => '_seopress_titles_title',
					'description'         => '_seopress_titles_desc',
					'keywords'            => '_seopress_analysis_target_kw',
					'og_title'            => '_seopress_social_fb_title',
					'og_description'      => '_seopress_social_fb_desc',
					'twitter_title'       => '_seopress_social_twitter_title',
					'twitter_description' => '_seopress_social_twitter_desc',
					'canonical'           => '_seopress_robots_canonical',
					'noindex'             => '_seopress_robots_index',
					'nofollow'            => '_seopress_robots_follow',
					'og_image'            => '_seopress_social_fb_img',
					'twitter_image'       => '_seopress_social_twitter_img',
					'breadcrumb'          => '_seopress_robots_breadcrumbs',
				),
			),
		);
	}

	/**
	 * Fields a client can send to an update ability.
	 *
	 * @return array<string,array<string,mixed>>
	 */
	private static function write_properties() {
		return array(
			'post_id'             => Helper::int_prop(__('Post or page ID.', 'onemcp-pro')),
			'title'               => Helper::string_prop(__('SEO title.', 'onemcp-pro'), 200),
			'description'         => Helper::string_prop(__('Meta description.', 'onemcp-pro'), 320),
			'keywords'            => Helper::string_prop(__('Focus keyphrase or target keywords.', 'onemcp-pro'), 200),
			'og_title'            => Helper::string_prop(__('Social title.', 'onemcp-pro'), 200),
			'og_description'      => Helper::string_prop(__('Social description.', 'onemcp-pro'), 320),
			'twitter_title'       => Helper::string_prop(__('X title.', 'onemcp-pro'), 200),
			'twitter_description' => Helper::string_prop(__('X description.', 'onemcp-pro'), 320),
			'canonical'           => Helper::string_prop(__('Canonical URL.', 'onemcp-pro'), 500),
			'noindex'             => Helper::bool_prop(__('True to hide the post from search engines.', 'onemcp-pro')),
			'nofollow'            => Helper::bool_prop(__('True to add nofollow.', 'onemcp-pro')),
			'og_image'            => Helper::string_prop(__('Social image URL.', 'onemcp-pro'), 500),
			'twitter_image'       => Helper::string_prop(__('X image URL.', 'onemcp-pro'), 500),
			'breadcrumb'          => Helper::string_prop(__('Breadcrumb title.', 'onemcp-pro'), 200),
			'cornerstone'         => Helper::bool_prop(__('True to mark the post as cornerstone or pillar content.', 'onemcp-pro')),
		);
	}

	/**
	 * Read post meta into the shared field names.
	 *
	 * @param int    $post_id Post ID.
	 * @param string $slug    Plugin slug.
	 * @return array<string,mixed>
	 */
	private static function meta_values($post_id, $slug) {
		$plugins = self::plugins();
		$values  = array();
		foreach ($plugins[$slug]['fields'] as $name => $meta_key) {
			$stored = get_post_meta($post_id, $meta_key, true);
			if ($name === 'noindex') {
				$values[$name] = self::noindex_is_on($slug, $stored);
				continue;
			}
			if ($name === 'nofollow') {
				$values[$name] = self::nofollow_is_on($slug, $stored);
				continue;
			}
			if ($name === 'cornerstone') {
				$values[$name] = self::flag_is_on($stored);
				continue;
			}
			$values[$name] = is_scalar($stored) ? (string) $stored : '';
		}
		return $values;
	}

	/**
	 * Sanitize the fields present in an update request.
	 *
	 * @param array<string,mixed> $input Input.
	 * @return array<string,mixed>
	 */
	private static function submitted_values($input) {
		$limits = array(
			'title'               => 200,
			'description'         => 320,
			'keywords'            => 200,
			'og_title'            => 200,
			'og_description'      => 320,
			'twitter_title'       => 200,
			'twitter_description' => 320,
			'breadcrumb'          => 200,
		);
		$values = array();
		foreach ($limits as $name => $limit) {
			if (array_key_exists($name, $input)) {
				$values[$name] = Helper::text($input[$name], $limit);
			}
		}
		foreach (array('canonical', 'og_image', 'twitter_image') as $url_field) {
			if (array_key_exists($url_field, $input)) {
				$values[$url_field] = esc_url_raw((string) $input[$url_field]);
			}
		}
		foreach (array('noindex', 'nofollow', 'cornerstone') as $flag) {
			if (array_key_exists($flag, $input)) {
				$values[$flag] = Helper::bool($input[$flag]);
			}
		}
		return $values;
	}

	/**
	 * Storage value for a noindex flag.
	 *
	 * @param string $slug    Plugin slug.
	 * @param bool   $noindex Desired state.
	 * @param int    $post_id Post ID. Rank Math uses it to keep the other robots flags.
	 * @return string|array<int,string>
	 */
	private static function noindex_storage($slug, $noindex, $post_id = 0) {
		if ($slug === 'rank-math') {
			return self::rankmath_robots($post_id, $noindex);
		}
		if (!$noindex) {
			return $slug === 'yoast' ? '2' : '';
		}
		// SiteSEO and SEOPress store the checked robots box as "yes".
		// Yoast and All in One SEO use "1".
		if ($slug === 'seopress' || $slug === 'siteseo') {
			return 'yes';
		}
		return '1';
	}

	/**
	 * Whether a stored noindex flag is on.
	 *
	 * @param string $slug   Plugin slug.
	 * @param mixed  $stored Stored meta.
	 * @return bool
	 */
	private static function noindex_is_on($slug, $stored) {
		if ($slug === 'rank-math') {
			$robots = is_array($stored) ? $stored : array(is_scalar($stored) ? (string) $stored : '');
			return in_array('noindex', $robots, true);
		}
		$value = is_scalar($stored) ? strtolower(trim((string) $stored)) : '';
		if ($slug === 'yoast') {
			return $value === '1';
		}
		return in_array($value, array('1', 'yes', 'true', 'on'), true);
	}

	/**
	 * Rank Math stores robots as a list. Swap index and noindex, and keep the rest.
	 *
	 * @param int  $post_id Post ID.
	 * @param bool $noindex Desired state.
	 * @return array<int,string>
	 */
	private static function rankmath_robots($post_id, $noindex) {
		$stored = get_post_meta($post_id, 'rank_math_robots', true);
		$robots = array();
		if (is_array($stored)) {
			foreach ($stored as $robot) {
				if (!is_string($robot) || $robot === 'index' || $robot === 'noindex') {
					continue;
				}
				$robots[] = $robot;
			}
		}
		$robots[] = $noindex ? 'noindex' : 'index';
		return array_values(array_unique($robots));
	}

	/**
	 * Storage value for a nofollow flag.
	 *
	 * @param string $slug    Plugin slug.
	 * @param bool   $nofollow Desired state.
	 * @param int    $post_id Post ID.
	 * @return string|array<int,string>
	 */
	private static function nofollow_storage($slug, $nofollow, $post_id = 0) {
		if ($slug === 'rank-math') {
			return self::rankmath_toggle($post_id, 'nofollow', $nofollow);
		}
		if (!$nofollow) {
			return '';
		}
		if ($slug === 'seopress' || $slug === 'siteseo') {
			return 'yes';
		}
		return '1';
	}

	/**
	 * Whether a stored nofollow flag is on.
	 *
	 * @param string $slug   Plugin slug.
	 * @param mixed  $stored Stored meta.
	 * @return bool
	 */
	private static function nofollow_is_on($slug, $stored) {
		if ($slug === 'rank-math') {
			$robots = is_array($stored) ? $stored : array(is_scalar($stored) ? (string) $stored : '');
			return in_array('nofollow', $robots, true);
		}
		return self::flag_is_on($stored);
	}

	/**
	 * Add or remove one Rank Math robots token without changing the others.
	 *
	 * @param int    $post_id Post ID.
	 * @param string $flag    Robots token.
	 * @param bool   $enabled Desired state.
	 * @return array<int,string>
	 */
	private static function rankmath_toggle($post_id, $flag, $enabled) {
		$stored = get_post_meta($post_id, 'rank_math_robots', true);
		$robots = array();
		if (is_array($stored)) {
			foreach ($stored as $robot) {
				if (!is_string($robot) || $robot === $flag) {
					continue;
				}
				$robots[] = $robot;
			}
		}
		if ($enabled) {
			$robots[] = $flag;
		}
		if ($robots === array()) {
			$robots[] = 'index';
		}
		return array_values(array_unique($robots));
	}

	/**
	 * Storage value for cornerstone or pillar content.
	 *
	 * @param string $slug    Plugin slug.
	 * @param bool   $enabled Desired state.
	 * @return string
	 */
	private static function cornerstone_storage($slug, $enabled) {
		if (!$enabled) {
			return '';
		}
		return $slug === 'rank-math' ? 'on' : '1';
	}

	/**
	 * Whether a stored yes/on flag is set.
	 *
	 * @param mixed $stored Stored meta.
	 * @return bool
	 */
	private static function flag_is_on($stored) {
		$value = is_scalar($stored) ? strtolower(trim((string) $stored)) : '';
		return in_array($value, array('1', 'yes', 'true', 'on'), true);
	}

	/**
	 * Read All in One SEO from its model when that API exists.
	 *
	 * @param int $post_id Post ID.
	 * @return array<string,mixed>|null
	 */
	private static function aioseo_values($post_id) {
		if (!class_exists('\AIOSEO\Plugin\Common\Models\Post') || !method_exists('\AIOSEO\Plugin\Common\Models\Post', 'getPost')) {
			return null;
		}
		try {
			$model = \AIOSEO\Plugin\Common\Models\Post::getPost($post_id);
		} catch (Exception $exception) {
			return null;
		}
		if (!is_object($model)) {
			return null;
		}
		return array(
			'title'               => self::model_string($model, array('title')),
			'description'         => self::model_string($model, array('description')),
			'keywords'            => self::aioseo_keywords($model),
			'og_title'            => self::model_string($model, array('og_title')),
			'og_description'      => self::model_string($model, array('og_description')),
			'twitter_title'       => self::model_string($model, array('twitter_title')),
			'twitter_description' => self::model_string($model, array('twitter_description')),
			'canonical'           => self::model_string($model, array('canonical_url', 'canonicalUrl')),
			'noindex'             => (bool) self::model_value($model, array('robots_noindex')),
			'nofollow'            => (bool) self::model_value($model, array('robots_nofollow')),
			'og_image'            => self::model_string($model, array('og_image_custom_url')),
			'twitter_image'       => self::model_string($model, array('twitter_image_custom_url')),
			'breadcrumb'          => self::model_string($model, array('breadcrumb_label')),
			'cornerstone'         => (bool) self::model_value($model, array('pillar_content')),
		);
	}

	/**
	 * Save All in One SEO through its model.
	 *
	 * @param int                 $post_id Post ID.
	 * @param array<string,mixed> $values  Sanitized fields.
	 * @return bool
	 */
	private static function save_aioseo($post_id, $values) {
		if (!class_exists('\AIOSEO\Plugin\Common\Models\Post') || !method_exists('\AIOSEO\Plugin\Common\Models\Post', 'getPost')) {
			return false;
		}
		try {
			$model = \AIOSEO\Plugin\Common\Models\Post::getPost($post_id);
			if (!is_object($model) || !method_exists($model, 'save')) {
				return false;
			}
			$map = array(
				'title'               => 'title',
				'description'         => 'description',
				'og_title'            => 'og_title',
				'og_description'      => 'og_description',
				'twitter_title'       => 'twitter_title',
				'twitter_description' => 'twitter_description',
				'canonical'           => 'canonical_url',
				'og_image'            => 'og_image_custom_url',
				'twitter_image'       => 'twitter_image_custom_url',
				'breadcrumb'          => 'breadcrumb_label',
			);
			foreach ($map as $name => $property) {
				if (array_key_exists($name, $values)) {
					$model->{$property} = $values[$name];
				}
			}
			if (array_key_exists('keywords', $values)) {
				$model->keyphrases = wp_json_encode(array(
					'focus'      => array('keyphrase' => $values['keywords']),
					'additional' => array(),
				));
			}
			if (array_key_exists('og_image', $values) && $values['og_image'] !== '') {
				$model->og_image_type = 'custom';
			}
			if (array_key_exists('twitter_image', $values) && $values['twitter_image'] !== '') {
				$model->twitter_image_type = 'custom';
			}
			if (array_key_exists('noindex', $values)) {
				$model->robots_noindex = (bool) $values['noindex'];
			}
			if (array_key_exists('nofollow', $values)) {
				$model->robots_nofollow = (bool) $values['nofollow'];
			}
			if (array_key_exists('cornerstone', $values)) {
				$model->pillar_content = (bool) $values['cornerstone'];
			}
			$model->save();
			return true;
		} catch (Exception $exception) {
			return false;
		}
	}

	/**
	 * Focus keyphrase from an All in One SEO model.
	 *
	 * @param object $model Model.
	 * @return string
	 */
	private static function aioseo_keywords($model) {
		$raw = self::model_value($model, array('keyphrases'));
		if (is_string($raw)) {
			$decoded = json_decode($raw, true);
			if (is_array($decoded) && isset($decoded['focus']['keyphrase'])) {
				return (string) $decoded['focus']['keyphrase'];
			}
			return $raw;
		}
		if (is_object($raw) && isset($raw->focus->keyphrase)) {
			return (string) $raw->focus->keyphrase;
		}
		return self::model_string($model, array('keywords'));
	}

	/**
	 * First string property found on a model.
	 *
	 * @param object            $model Model.
	 * @param array<int,string> $names Property names.
	 * @return string
	 */
	private static function model_string($model, $names) {
		$value = self::model_value($model, $names);
		return is_scalar($value) ? (string) $value : '';
	}

	/**
	 * First existing property on a model.
	 *
	 * @param object            $model Model.
	 * @param array<int,string> $names Property names.
	 * @return mixed
	 */
	private static function model_value($model, $names) {
		foreach ($names as $name) {
			if (isset($model->{$name})) {
				return $model->{$name};
			}
		}
		return '';
	}
}
