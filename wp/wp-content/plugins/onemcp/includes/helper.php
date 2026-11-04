<?php
/**
 * Shared helpers.
 *
 * @package OneMCP
 */

namespace ONEMCP;

if (!defined('ABSPATH')) {
	exit;
}

class Helper {

	/**
	 * Clamp a list size.
	 *
	 * @param mixed $value   Requested size.
	 * @param int   $default Default.
	 * @param int   $max     Maximum.
	 * @return int
	 */
	public static function limit($value, $default = 20, $max = 50) {
		$number = isset($value) ? (int) $value : (int) $default;
		if ($number < 1) {
			$number = (int) $default;
		}
		return min($number, (int) $max);
	}

	/**
	 * Positive integer from mixed input.
	 *
	 * @param mixed $value Raw value.
	 * @return int
	 */
	public static function absint($value) {
		return absint($value);
	}

	/**
	 * Plain single-line text.
	 *
	 * @param mixed $value Raw value.
	 * @param int   $max   Maximum length.
	 * @return string
	 */
	public static function text($value, $max = 500) {
		$text = sanitize_text_field(is_scalar($value) ? (string) $value : '');
		if (function_exists('mb_substr')) {
			return mb_substr($text, 0, $max);
		}
		return substr($text, 0, $max);
	}

	/**
	 * Boolean from a schema value.
	 *
	 * @param mixed $value Raw value.
	 * @return bool
	 */
	public static function bool($value) {
		if (is_bool($value)) {
			return $value;
		}
		if (is_numeric($value)) {
			return (int) $value === 1;
		}
		$normalized = strtolower(trim((string) $value));
		return in_array($normalized, array('1', 'true', 'yes', 'on'), true);
	}

	/**
	 * Load a post the current user is allowed to edit.
	 *
	 * @param int $post_id Post ID.
	 * @return \WP_Post|\WP_Error
	 */
	public static function editable_post($post_id) {
		$post_id = (int) $post_id;
		$post    = get_post($post_id);
		if (!$post instanceof \WP_Post) {
			return new \WP_Error('onemcp_not_found', __('Content was not found.', 'onemcp'));
		}
		if (!current_user_can('edit_post', $post_id)) {
			return new \WP_Error('onemcp_forbidden', __('You cannot edit this content.', 'onemcp'));
		}
		return $post;
	}

	/**
	 * Whether the user may create this post type.
	 *
	 * wp_insert_post() does not check create_posts. Callers must.
	 *
	 * @param string $post_type Post type.
	 * @return bool
	 */
	public static function user_can_create($post_type) {
		$object = get_post_type_object($post_type);
		$cap    = ($object && !empty($object->cap->create_posts)) ? $object->cap->create_posts : 'edit_posts';
		return current_user_can($cap);
	}

	/**
	 * Run a post query that hides other authors' unpublished content.
	 *
	 * Published posts stay visible. Drafts, pending, future, trash, and other
	 * people's private posts stay hidden unless this user can edit or read them.
	 *
	 * @param array<string,mixed> $args WP_Query arguments.
	 * @return \WP_Query
	 */
	public static function query_posts($args) {
		$args['onemcp_visible'] = 1;
		add_filter('posts_where', array(__CLASS__, 'visible_posts_where'), 10, 2);
		$query = new \WP_Query($args);
		remove_filter('posts_where', array(__CLASS__, 'visible_posts_where'), 10);
		return $query;
	}

	/**
	 * Limit one OneMCP query to posts this user is allowed to see.
	 *
	 * @param string    $where SQL WHERE.
	 * @param \WP_Query $query Query.
	 * @return string
	 */
	public static function visible_posts_where($where, $query) {
		if (!($query instanceof \WP_Query) || (int) $query->get('onemcp_visible') !== 1) {
			return $where;
		}
		$post_type = $query->get('post_type');
		if (self::can_edit_others($post_type)) {
			return $where;
		}
		global $wpdb;
		$own = $wpdb->prepare("{$wpdb->posts}.post_author = %d", get_current_user_id());
		if (self::can_read_private($post_type)) {
			$clause = "({$wpdb->posts}.post_status = 'publish' OR {$wpdb->posts}.post_status = 'private' OR {$own})";
		} else {
			$clause = "({$wpdb->posts}.post_status = 'publish' OR {$own})";
		}
		return $where . ' AND ' . $clause;
	}

	/**
	 * Whether the user can edit other authors' posts for every queried type.
	 *
	 * An "any" query requires both posts and pages, so a narrower role cannot
	 * see every unpublished type.
	 *
	 * @param mixed $post_type WP_Query post_type value.
	 * @return bool
	 */
	public static function can_edit_others($post_type) {
		return self::has_type_cap($post_type, 'edit_others_posts', 'edit_others_posts', 'edit_others_pages');
	}

	/**
	 * Whether the user can read private posts for every queried type.
	 *
	 * @param mixed $post_type WP_Query post_type value.
	 * @return bool
	 */
	public static function can_read_private($post_type) {
		return self::has_type_cap($post_type, 'read_private_posts', 'read_private_posts', 'read_private_pages');
	}

	/**
	 * Capability check across one or more post types.
	 *
	 * @param mixed  $post_type   WP_Query post_type value.
	 * @param string $cap_field   Post type capability field.
	 * @param string $any_posts   Capability required for posts when the type is any.
	 * @param string $any_pages   Capability required for pages when the type is any.
	 * @return bool
	 */
	private static function has_type_cap($post_type, $cap_field, $any_posts, $any_pages) {
		$types = array();
		if (is_array($post_type)) {
			$types = $post_type;
		} elseif (is_string($post_type) && $post_type !== '' && $post_type !== 'any') {
			$types = array($post_type);
		}
		if ($types === array()) {
			return current_user_can($any_posts) && current_user_can($any_pages);
		}
		foreach ($types as $type) {
			$object = get_post_type_object($type);
			$cap    = ($object && !empty($object->cap->$cap_field)) ? $object->cap->$cap_field : $any_posts;
			if (!current_user_can($cap)) {
				return false;
			}
		}
		return true;
	}

	/**
	 * Trim a string for list responses.
	 *
	 * @param string $text  Source text.
	 * @param int    $limit Character limit.
	 * @return string
	 */
	public static function trim_text($text, $limit = 180) {
		$text = trim(wp_strip_all_tags((string) $text));
		$text = preg_replace('/\s+/', ' ', $text);
		if (!is_string($text)) {
			return '';
		}
		if (function_exists('mb_strlen') && mb_strlen($text) > $limit) {
			return mb_substr($text, 0, $limit - 1) . '…';
		}
		if (strlen($text) > $limit) {
			return substr($text, 0, $limit - 1) . '…';
		}
		return $text;
	}

	/**
	 * Remove secrets from an option array before returning it to an agent.
	 *
	 * @param mixed $value  Option value.
	 * @param int   $depth  Recursion depth.
	 * @return mixed
	 */
	public static function redact($value, $depth = 0) {
		if ($depth > 6) {
			return null;
		}
		if (!is_array($value)) {
			return is_scalar($value) || $value === null ? $value : null;
		}
		$clean = array();
		foreach ($value as $key => $item) {
			$key_text = strtolower((string) $key);
			if (preg_match('/license|api[_-]?key|secret|password|passwd|token|private|client_secret|auth/', $key_text)) {
				continue;
			}
			$clean[$key] = self::redact($item, $depth + 1);
		}
		return $clean;
	}

	/**
	 * Decode JSON or a serialized meta value into an array.
	 *
	 * @param mixed $value Stored meta.
	 * @return array<mixed>
	 */
	public static function decode_meta($value) {
		if (is_array($value)) {
			return $value;
		}
		if (!is_string($value) || $value === '') {
			return array();
		}
		$json = json_decode($value, true);
		if (is_array($json)) {
			return $json;
		}
		$unserialized = maybe_unserialize($value);
		return is_array($unserialized) ? $unserialized : array();
	}

	/**
	 * Return a document payload, or a size notice when it is too large for a tool response.
	 *
	 * @param mixed $data    Document.
	 * @param bool  $include Whether the caller asked for the raw document.
	 * @return mixed|null
	 */
	public static function raw_document($data, $include) {
		if (!$include) {
			return null;
		}
		$json = wp_json_encode($data);
		if (!is_string($json)) {
			return null;
		}
		if (strlen($json) > 180000) {
			return array(
				'omitted' => true,
				'bytes'   => strlen($json),
				'reason'  => __('Document is too large to return. Update one element at a time, or replace it in smaller pieces.', 'onemcp'),
			);
		}
		return $data;
	}

	/**
	 * Whether an array is a JSON list.
	 *
	 * @param mixed $value Value to test.
	 * @return bool
	 */
	public static function is_list($value) {
		if (!is_array($value)) {
			return false;
		}
		if ($value === array()) {
			return true;
		}
		return array_keys($value) === range(0, count($value) - 1);
	}

	/**
	 * Active plugin basenames, including network-activated plugins.
	 *
	 * @return array<int,string>
	 */
	public static function active_plugin_files() {
		$files = (array) get_option('active_plugins', array());
		if (is_multisite()) {
			$network = (array) get_site_option('active_sitewide_plugins', array());
			$files   = array_merge($files, array_keys($network));
		}
		return array_values(array_unique(array_map('strval', $files)));
	}

	/**
	 * Standard ability error.
	 *
	 * @param string $code    Error code.
	 * @param string $message Human message.
	 * @return \WP_Error
	 */
	public static function error($code, $message) {
		return new \WP_Error($code, $message);
	}

	/**
	 * JSON schema for an ability. Tools with no required fields can be called with no arguments.
	 *
	 * @param array<string,array<string,mixed>> $properties Property schemas.
	 * @param array<int,string>                 $required   Required property names.
	 * @return array<string,mixed>
	 */
	public static function schema($properties, $required = array()) {
		$schema = array(
			'type'                 => 'object',
			'additionalProperties' => false,
		);
		if (!empty($properties)) {
			$schema['properties'] = $properties;
		}
		if (!empty($required)) {
			$schema['required'] = array_values($required);
		} else {
			$schema['default'] = array();
		}
		return $schema;
	}

	/**
	 * String schema property.
	 *
	 * @param string $description Description.
	 * @param int    $max         Maximum length.
	 * @return array<string,mixed>
	 */
	public static function string_prop($description, $max = 500) {
		return array(
			'type'        => 'string',
			'description' => $description,
			'maxLength'   => (int) $max,
		);
	}

	/**
	 * Integer schema property.
	 *
	 * @param string $description Description.
	 * @return array<string,mixed>
	 */
	public static function int_prop($description) {
		return array(
			'type'        => 'integer',
			'description' => $description,
		);
	}

	/**
	 * Boolean schema property.
	 *
	 * @param string $description Description.
	 * @return array<string,mixed>
	 */
	public static function bool_prop($description) {
		return array(
			'type'        => 'boolean',
			'description' => $description,
		);
	}

	/**
	 * Array of integers.
	 *
	 * @param string $description Description.
	 * @return array<string,mixed>
	 */
	public static function int_list_prop($description) {
		return array(
			'type'        => 'array',
			'description' => $description,
			'items'       => array(
				'type' => 'integer',
			),
		);
	}

	/**
	 * Unique positive integers from a list.
	 *
	 * @param mixed $value Raw value.
	 * @return array<int,int>
	 */
	public static function int_list($value) {
		if (!is_array($value)) {
			return array();
		}
		$ids = array();
		foreach ($value as $item) {
			$id = absint($item);
			if ($id > 0) {
				$ids[] = $id;
			}
		}
		return array_values(array_unique($ids));
	}

	/**
	 * Calendar date (Y-m-d). Empty input clears the date. Null means the value is not a date.
	 *
	 * @param mixed $value Raw value.
	 * @return string|null
	 */
	public static function date($value) {
		$raw = trim(is_scalar($value) ? (string) $value : '');
		if ($raw === '') {
			return '';
		}
		$timestamp = strtotime($raw);
		if (!$timestamp) {
			return null;
		}
		return wp_date('Y-m-d', $timestamp);
	}

	/**
	 * Object schema property that accepts the keys a builder or SEO update needs.
	 *
	 * @param string $description Description.
	 * @return array<string,mixed>
	 */
	public static function object_prop($description) {
		return array(
			'type'                 => 'object',
			'description'          => $description,
			'additionalProperties' => true,
		);
	}

	/**
	 * One ability definition consumed by Registry.
	 *
	 * @param string              $name        Ability name without the onemcp/ prefix.
	 * @param string              $label       Human label.
	 * @param string              $description What the ability does.
	 * @param string              $category    Category slug.
	 * @param bool                $readonly    True for a read ability.
	 * @param callable            $execute     Handler. Receives an input array.
	 * @param array<string,mixed> $properties  Input properties.
	 * @param array<int,string>   $required    Required property names.
	 * @param array<string,mixed> $extra       cap, permission, destructive, or default_on.
	 * @return array<string,mixed>
	 */
	public static function ability($name, $label, $description, $category, $readonly, $execute, $properties = array(), $required = array(), $extra = array()) {
		$ability = array(
			'name'         => $name,
			'label'        => $label,
			'description'  => $description,
			'category'     => $category,
			'readonly'     => (bool) $readonly,
			'input_schema' => self::schema($properties, $required),
			'execute'      => $execute,
			'cap'          => isset($extra['cap']) ? $extra['cap'] : 'edit_posts',
		);
		if (!empty($extra['destructive'])) {
			$ability['destructive'] = true;
		}
		if (!empty($extra['default_on'])) {
			$ability['default_on'] = true;
		}
		if (isset($extra['permission']) && is_callable($extra['permission'])) {
			$ability['permission'] = $extra['permission'];
		}
		return $ability;
	}

	/**
	 * Page number from ability input.
	 *
	 * @param array<string,mixed> $input Input.
	 * @return int
	 */
	public static function page($input) {
		$page = isset($input['page']) ? (int) $input['page'] : 1;
		return $page > 0 ? $page : 1;
	}

	/**
	 * A post type an editor is allowed to manage through this plugin.
	 *
	 * @param mixed  $value    Requested type.
	 * @param string $fallback Fallback type.
	 * @return string|\WP_Error
	 */
	public static function post_type($value, $fallback = 'post') {
		$type   = sanitize_key(is_scalar($value) && (string) $value !== '' ? (string) $value : $fallback);
		$object = get_post_type_object($type);
		$blocked = array(
			'revision',
			'nav_menu_item',
			'custom_css',
			'customize_changeset',
			'oembed_cache',
			'user_request',
			'wp_global_styles',
			'wp_navigation',
			'attachment',
		);
		if (!$object || empty($object->show_ui) || in_array($type, $blocked, true)) {
			return self::error('onemcp_invalid_type', __('That content type cannot be managed from OneMCP.', 'onemcp'));
		}
		return $type;
	}

	/**
	 * A post status this plugin will write.
	 *
	 * @param mixed  $value     Requested status.
	 * @param string $post_type Post type used for the publish capability.
	 * @param string $fallback  Status used when the request is empty or not allowed.
	 * @return string
	 */
	public static function post_status($value, $post_type = 'post', $fallback = 'draft') {
		$status  = sanitize_key(is_scalar($value) ? (string) $value : '');
		$allowed = array('draft', 'pending', 'private', 'future', 'publish');
		if (!in_array($status, $allowed, true)) {
			$status = $fallback;
		}
		if ($status === 'publish' || $status === 'private' || $status === 'future') {
			$object = get_post_type_object($post_type);
			$cap    = ($object && !empty($object->cap->publish_posts)) ? $object->cap->publish_posts : 'publish_posts';
			if (!current_user_can($cap)) {
				return 'draft';
			}
		}
		return $status;
	}

	/**
	 * Clean a nested settings bag before it is written into builder data.
	 *
	 * @param mixed $value Nested value.
	 * @param int   $depth Current depth.
	 * @return mixed
	 */
	public static function clean_settings($value, $depth = 0) {
		if ($depth > 8) {
			return null;
		}
		if (is_string($value)) {
			$value = str_replace("\0", '', $value);
			if (strlen($value) > 200000) {
				return substr($value, 0, 200000);
			}
			return $value;
		}
		if (is_int($value) || is_float($value) || is_bool($value) || $value === null) {
			return $value;
		}
		if (!is_array($value)) {
			return null;
		}

		$clean = array();
		$count = 0;
		foreach ($value as $key => $item) {
			if ($count >= 200) {
				break;
			}
			if (!is_string($key) && !is_int($key)) {
				continue;
			}
			if (is_string($key)) {
				$key = str_replace("\0", '', $key);
				if ($key === '' || strlen($key) > 120) {
					continue;
				}
			}
			$clean[$key] = self::clean_settings($item, $depth + 1);
			$count++;
		}
		return $clean;
	}

	/**
	 * Compact post row for list responses.
	 *
	 * @param \WP_Post $post Post object.
	 * @return array<string,mixed>
	 */
	public static function post_row($post) {
		return array(
			'id'       => (int) $post->ID,
			'type'     => $post->post_type,
			'status'   => $post->post_status,
			'title'    => html_entity_decode(get_the_title($post), ENT_QUOTES, 'UTF-8'),
			'slug'     => $post->post_name,
			'modified' => $post->post_modified_gmt,
			'link'     => get_permalink($post),
		);
	}
}

class_alias('\ONEMCP\Helper', '\ONEMCP\Helpers');
