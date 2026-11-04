<?php
/**
 * Registers abilities with the WordPress Abilities API.
 *
 * @package OneMCP
 */

namespace ONEMCP;

use WP_Ability;

if (!defined('ABSPATH')) {
	exit;
}

class Registry {

	/**
	 * True while catalog() is collecting definitions.
	 *
	 * @var bool
	 */
	private static $building = false;

	/**
	 * True while register_abilities() is applying the catalog.
	 *
	 * @var bool
	 */
	private static $registering = false;

	/**
	 * Integration classes that contribute abilities.
	 *
	 * @return array<int,string>
	 */
	 static function sources() {
		$sources = array('WordPress');
		$sources = apply_filters('onemcp_sources', $sources);
		return is_array($sources) ? $sources : array('WordPress');
	}

	/**
	 * Ability categories.
	 *
	 * @return array<string,array{label:string,description:string}>
	 */
	 static function categories() {
		$categories = array(
			'onemcp-wordpress' => array(
				'label'       => Detector::core_label(),
				'description' => Detector::is_sitepad()
					? __('Site info, any post type, theme templates, the visual editor and its widgets, media, comments, terms, menus, plugins, themes, and users.', 'onemcp')
					: __('Site info, posts, pages, media, comments, terms, menus, plugins, themes, and users.', 'onemcp'),
			),
		);
		$categories = apply_filters('onemcp_categories', $categories);
		return is_array($categories) ? $categories : array();
	}

	/**
	 * Every ability the active integrations can offer, including ones that are switched off.
	 *
	 * @return array<int,array<string,mixed>>
	 */
	 static function catalog() {
		static $catalog = null;
		if (is_array($catalog)) {
			return $catalog;
		}
		// Open the abilities registry first so page builders register, and this
		// plugin can republish those abilities, while the registry hook is open.
		if (!self::$building && !self::$registering && !doing_action('wp_abilities_api_init') && !did_action('wp_abilities_api_init') && did_action('init') && function_exists('wp_get_abilities')) {
			wp_get_abilities();
			if (is_array($catalog)) {
				return $catalog;
			}
		}
		// A nested call happens when loading the builder abilities starts the
		// abilities registry, which calls register_abilities(), which calls catalog().
		if (self::$building) {
			return array();
		}

		self::$building = true;
		$built = array();
		foreach (self::sources() as $source) {
			if (!class_exists($source) && class_exists(__NAMESPACE__ . '\\' . $source)) {
				$source = __NAMESPACE__ . '\\' . $source;
			}
			if (!class_exists($source) || !method_exists($source, 'definitions')) {
				continue;
			}
			$definitions = call_user_func(array($source, 'definitions'));
			if (!is_array($definitions)) {
				continue;
			}
			foreach ($definitions as $definition) {
				if (empty($definition['name']) || empty($definition['execute'])) {
					continue;
				}
				$definition['id']       = 'onemcp/' . $definition['name'];
				$definition['readonly'] = !empty($definition['readonly']);
				$definition['enabled']  = Settings::ability_enabled($definition);
				$built[] = $definition;
			}
		}
		self::$building = false;
		$catalog = $built;

		if (!self::$registering && doing_action('wp_abilities_api_init')) {
			self::register_abilities();
		}

		return $catalog;
	}

	/**
	 * Register categories. Hooked to wp_abilities_api_categories_init.
	 *
	 * @return void
	 */
	 static function register_categories() {
		if (!function_exists('wp_register_ability_category')) {
			return;
		}
		foreach (self::categories() as $slug => $category) {
			wp_register_ability_category($slug, array(
				'label'       => $category['label'],
				'description' => $category['description'],
			));
		}
	}

	/**
	 * Register enabled abilities. Hooked to wp_abilities_api_init.
	 *
	 * @return void
	 */
	 static function register_abilities() {
		static $done = false;
		if ($done || !function_exists('wp_register_ability')) {
			return;
		}
		// catalog() may be the caller that started the abilities registry.
		// Wait until that collection finishes, then register the complete list.
		if (self::$building) {
			return;
		}
		$done = true;
		self::$registering = true;

		foreach (self::catalog() as $ability) {
			if (empty($ability['enabled'])) {
				continue;
			}
			self::register_one($ability);
		}
		self::$registering = false;
	}

	/**
	 * Register a single ability.
	 *
	 * @param array<string,mixed> $ability Catalog row.
	 * @return void
	 */
	private static function register_one($ability) {
		$readonly = !empty($ability['readonly']);
		$id       = $ability['id'];

		wp_register_ability($id, array(
			'label'               => $ability['label'],
			'description'         => $ability['description'],
			'category'            => $ability['category'],
			'input_schema'        => $ability['input_schema'],
			'execute_callback'    => function ($input) use ($ability) {
				return self::execute($ability, $input);
			},
			'permission_callback' => function () use ($ability) {
				return self::permit($ability);
			},
			'meta'                => array(
				'public'       => true,
				'show_in_rest' => true,
				'annotations'  => array(
					'readonly'    => $readonly,
					'destructive' => !empty($ability['destructive']),
					'idempotent'  => $readonly || empty($ability['destructive']),
				),
			),
		));
	}

	/**
	 * Run an ability and write the activity log.
	 *
	 * @param array<string,mixed> $ability Catalog row.
	 * @param mixed               $input   Arguments from the client.
	 * @return mixed
	 */
	 static function execute($ability, $input) {
		if (!is_array($input)) {
			$input = array();
		}
		try {
			$result = call_user_func($ability['execute'], $input);
		} catch (\Exception $exception) {
			$result = new \WP_Error('onemcp_exception', $exception->getMessage());
		}

		$ok      = !is_wp_error($result);
		$message = $ok ? 'ok' : $result->get_error_message();
		\ONEMCP\Audit::log($ability['id'], $ok, $message);
		return $result;
	}

	/**
	 * Run one enabled ability for the built-in MCP server.
	 *
	 * @param string              $id    Ability id, including the onemcp/ prefix.
	 * @param array<string,mixed> $input Arguments.
	 * @return mixed|WP_Error
	 */
	 static function call($id, $input) {
		$ability = null;
		foreach (self::catalog() as $row) {
			if ($row['id'] === $id) {
				$ability = $row;
				break;
			}
		}
		if (!$ability || empty($ability['enabled'])) {
			return new \WP_Error('onemcp_unknown', __('That ability is not available.', 'onemcp'));
		}
		if (!is_array($input)) {
			$input = array();
		}

		$allowed = self::permit($ability);
		if (true !== $allowed) {
			return is_wp_error($allowed) ? $allowed : new \WP_Error('onemcp_forbidden', __('You do not have permission to run this ability.', 'onemcp'));
		}
		if (function_exists('wp_get_ability')) {
			$registered = wp_get_ability($id);
			if ($registered instanceof WP_Ability) {
				return $registered->execute($input);
			}
		}
		return self::execute($ability, $input);
	}

	/**
	 * Coarse permission check. Handlers still check the object they touch.
	 *
	 * @param array<string,mixed> $ability Catalog row.
	 * @return true|WP_Error
	 */
	private static function permit($ability) {
		if (!Settings::ability_enabled($ability)) {
			return new \WP_Error('onemcp_disabled', __('This ability is turned off in OneMCP.', 'onemcp'));
		}
		if (isset($ability['permission']) && is_callable($ability['permission'])) {
			$allowed = call_user_func($ability['permission']);
			if ($allowed === true) {
				return true;
			}
			if (is_wp_error($allowed)) {
				return $allowed;
			}
			return new \WP_Error('onemcp_forbidden', __('You do not have permission to run this ability.', 'onemcp'));
		}
		$cap = isset($ability['cap']) ? $ability['cap'] : 'edit_posts';
		if (!current_user_can($cap)) {
			return new \WP_Error('onemcp_forbidden', __('You do not have permission to run this ability.', 'onemcp'));
		}
		return true;
	}
}
