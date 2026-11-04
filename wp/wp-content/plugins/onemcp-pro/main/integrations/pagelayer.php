<?php
/**
 * Pagelayer pages, posts, theme templates, menus, widgets, and styles.
 *
 * The handlers are Pagelayer's own ability callbacks. This class publishes
 * them on the OneMCP server.
 *
 * @package OneMCPPro
 */

namespace OneMCPPro;

use ONEMCP\Detector;
use ONEMCP\Helper;
use Pagelayer_Abilities_Register;

if (!defined('ABSPATH')) {
	exit;
}

class Pagelayer {

	/**
	 * Ability definitions. Empty when Pagelayer is not active.
	 *
	 * @return array<int,array<string,mixed>>
	 */
	static function definitions() {
		$definitions = array();
		foreach (self::editor_definitions() as $source_id => $def) {
			if (!is_array($def) || empty($def['execute']) || !is_callable($def['execute'])) {
				continue;
			}
			if (array_key_exists('mcp_public', $def) && empty($def['mcp_public'])) {
				continue;
			}
			$slug     = self::slug($source_id);
			$readonly = self::is_readonly($slug);
			$extra    = array(
				'permission' => (isset($def['perm']) && is_callable($def['perm'])) ? $def['perm'] : array('Pagelayer_Abilities_Register', 'can_edit_posts'),
			);
			if (self::is_destructive($slug)) {
				$extra['destructive'] = true;
			}
			if (Detector::is_sitepad()) {
				$extra['default_on'] = true;
			}

			$label = isset($def['label']) ? (string) $def['label'] : $slug;
			$description = isset($def['description']) ? (string) $def['description'] : '';
			$note = self::build_note($slug);
			if ($note !== '') {
				$description = trim($description . ' ' . $note);
			}
			$ability = Helper::ability(
				self::public_name($slug),
				self::scrub($label),
				self::scrub($description),
				self::category(isset($def['category']) ? (string) $def['category'] : ''),
				$readonly,
				$def['execute'],
				array(),
				array(),
				$extra
			);
			$ability['input_schema'] = self::schema(isset($def['input_schema']) ? $def['input_schema'] : array());
			$definitions[] = $ability;
		}
		return $definitions;
	}

	/**
	 * Pagelayer pages, posts, theme templates, widgets, and style abilities.
	 *
	 * Pagelayer registers these on the WordPress Abilities API. Older copies do
	 * not expose ability_definitions(), so this reads the registered abilities.
	 *
	 * @return array<string,array<string,mixed>>
	 */
	public static function editor_definitions() {
		if (!Detector::is_active('pagelayer')) {
			return array();
		}
		self::load_register_class();
		if (!class_exists('Pagelayer_Abilities_Register')) {
			return array();
		}
		if (method_exists('Pagelayer_Abilities_Register', 'ability_definitions')) {
			$defs = Pagelayer_Abilities_Register::ability_definitions();
			return is_array($defs) ? $defs : array();
		}

		self::ensure_registered();
		$out = array();
		foreach (self::registered_editor_abilities() as $name => $ability) {
			$out[$name] = array(
				'label'        => $ability->get_label(),
				'description'  => $ability->get_description(),
				'category'     => $ability->get_category(),
				'input_schema' => $ability->get_input_schema(),
				'execute'      => self::execute_adapter($name),
				'perm'         => self::permission_adapter($name),
			);
		}
		return $out;
	}

	/**
	 * Load Pagelayer's ability class when the plugin is active but has not included it yet.
	 *
	 * @return void
	 */
	private static function load_register_class() {
		if (class_exists('Pagelayer_Abilities_Register') || !defined('PAGELAYER_DIR')) {
			return;
		}
		$file = trailingslashit(PAGELAYER_DIR) . 'main/abilitiesregister.php';
		if (is_readable($file)) {
			include_once $file;
		}
	}

	/**
	 * Make sure Pagelayer has registered its pages, posts, templates, and widgets.
	 *
	 * @return void
	 */
	private static function ensure_registered() {
		if (self::registered_editor_abilities() !== array()) {
			return;
		}
		self::load_register_class();
		if (!class_exists('Pagelayer_Abilities_Register')) {
			return;
		}

		$inside = function_exists('doing_action') && doing_action('wp_abilities_api_init');
		$done   = function_exists('did_action') && did_action('wp_abilities_api_init');
		if (!$inside && !$done && did_action('init') && function_exists('wp_get_abilities')) {
			wp_get_abilities();
			if (self::registered_editor_abilities() !== array()) {
				return;
			}
		}

		if (!$inside || !method_exists('Pagelayer_Abilities_Register', 'register_abilities')) {
			return;
		}
		static $invoked = false;
		if ($invoked) {
			return;
		}
		$invoked = true;
		try {
			$method = new \ReflectionMethod('Pagelayer_Abilities_Register', 'register_abilities');
			$method->setAccessible(true);
			$method->invoke(null);
		} catch (\Throwable $exception) {
			unset($exception);
		}
	}

	/**
	 * Pagelayer abilities already on the WordPress Abilities API.
	 *
	 * @return array<string,\WP_Ability>
	 */
	private static function registered_editor_abilities() {
		if (!function_exists('wp_get_abilities')) {
			return array();
		}
		if (!doing_action('wp_abilities_api_init') && !did_action('wp_abilities_api_init')) {
			return array();
		}

		$out = array();
		foreach (wp_get_abilities() as $ability) {
			if (!$ability instanceof \WP_Ability) {
				continue;
			}
			$name = $ability->get_name();
			if (!is_string($name) || strpos($name, 'pagelayer-') !== 0) {
				continue;
			}
			$meta = $ability->get_meta();
			if (isset($meta['mcp']['public']) && $meta['mcp']['public'] === false) {
				continue;
			}
			$out[$name] = $ability;
		}
		return $out;
	}

	/**
	 * Run one Pagelayer ability through its own handler.
	 *
	 * @param string $name Pagelayer ability id.
	 * @return callable
	 */
	private static function execute_adapter($name) {
		return function ($input) use ($name) {
			$live = function_exists('wp_get_ability') ? wp_get_ability($name) : null;
			if (!$live instanceof \WP_Ability) {
				return new \WP_Error('onemcp_missing', __('That builder ability is not available.', 'onemcp-pro'));
			}
			if (!is_array($input)) {
				$input = array();
			}
			return $live->execute($input);
		};
	}

	/**
	 * Permission check from the Pagelayer ability.
	 *
	 * @param string $name Pagelayer ability id.
	 * @return callable
	 */
	private static function permission_adapter($name) {
		return function () use ($name) {
			$live = function_exists('wp_get_ability') ? wp_get_ability($name) : null;
			if (!$live instanceof \WP_Ability) {
				return false;
			}
			return $live->check_permissions(array());
		};
	}

	/**
	 * Extra instruction for tools that build a layout.
	 *
	 * @param string $slug Ability slug.
	 * @return string
	 */
	public static function build_note($slug) {
		$build = array(
			'create-website',
			'create-page',
			'update-page',
			'create-post',
			'update-post',
			'create-template',
			'update-template',
			'create-design-ui',
			'edit-layout',
			'change-styles',
			'add-element',
			'update-element',
			'get-data-structure',
			'list-widgets',
			'get-widget-schema',
			'get-common-styles',
			'get-all-schemas',
		);
		if (!in_array($slug, $build, true)) {
			return '';
		}
		if (Detector::is_sitepad()) {
			return __('SitePad has no other builder. Use the visual editor widgets and the settings from their schema. Do not invent settings or build this as raw HTML.', 'onemcp-pro');
		}
		return __('Use this builder\'s own widgets and the settings from their schema. Do not invent setting names.', 'onemcp-pro');
	}

	/**
	 * Ability slug from a Pagelayer id such as pagelayer-pages/create-page.
	 *
	 * @param string $source_id Pagelayer ability id.
	 * @return string
	 */
	private static function slug($source_id) {
		$source_id = (string) $source_id;
		$slash     = strrpos($source_id, '/');
		$slug      = $slash === false ? $source_id : substr($source_id, $slash + 1);
		$slug      = strtolower(str_replace('_', '-', $slug));
		$slug      = preg_replace('/[^a-z0-9-]+/', '-', $slug);
		return is_string($slug) ? trim($slug, '-') : '';
	}

	/**
	 * Read abilities stay on until an administrator turns them off.
	 *
	 * @param string $slug Ability slug.
	 * @return bool
	 */
	private static function is_readonly($slug) {
		foreach (array('get-', 'list-', 'find-', 'validate-', 'preview-', 'search-', 'scrape-') as $prefix) {
			if (strpos($slug, $prefix) === 0) {
				return true;
			}
		}
		return $slug === 'navigator';
	}

	/**
	 * Abilities that remove or restore content.
	 *
	 * @param string $slug Ability slug.
	 * @return bool
	 */
	private static function is_destructive($slug) {
		return strpos($slug, 'delete-') === 0 || $slug === 'rollback-transaction';
	}

	/**
	 * Tool slug. On SitePad the editor is built in, so the name does not say Pagelayer.
	 *
	 * @param string $slug Ability slug.
	 * @return string
	 */
	private static function public_name($slug) {
		if (!Detector::is_sitepad()) {
			return 'pagelayer-' . $slug;
		}
		if ($slug === 'create-menu') {
			return 'build-menu';
		}
		return $slug;
	}

	/**
	 * Remove the Pagelayer name from text shown on SitePad.
	 *
	 * @param string $text Label or description.
	 * @return string
	 */
	private static function scrub($text) {
		if (!Detector::is_sitepad()) {
			return $text;
		}
		$clean = str_ireplace(array('Pagelayer', 'pagelayer'), '', $text);
		$clean = preg_replace('/\s{2,}/', ' ', $clean);
		$clean = is_string($clean) ? preg_replace('/\s+([,.;:])/', '$1', $clean) : $text;
		return is_string($clean) ? trim($clean) : $text;
	}

	/**
	 * Map an editor category onto a OneMCP category.
	 *
	 * On SitePad every editor ability belongs to SitePad Core.
	 *
	 * @param string $source Editor category slug.
	 * @return string
	 */
	private static function category($source) {
		if (Detector::is_sitepad()) {
			return 'onemcp-wordpress';
		}
		$map = array(
			'pagelayer-pages'     => 'onemcp-pagelayer-pages',
			'pagelayer-posts'     => 'onemcp-pagelayer-posts',
			'pagelayer-templates' => 'onemcp-pagelayer-templates',
			'pagelayer-menus'     => 'onemcp-pagelayer-menus',
			'pagelayer-widgets'   => 'onemcp-pagelayer-widgets',
			'pagelayer-global'    => 'onemcp-pagelayer-global',
			'pagelayer-media'     => 'onemcp-pagelayer-media',
		);
		return isset($map[$source]) ? $map[$source] : 'onemcp-pagelayer-pages';
	}

	/**
	 * Input schema accepted by the MCP server and the Abilities API.
	 *
	 * @param mixed $schema Pagelayer input schema.
	 * @return array<string,mixed>
	 */
	private static function schema($schema) {
		if (!is_array($schema) || $schema === array()) {
			return array(
				'type'                 => 'object',
				'additionalProperties' => false,
				'default'              => array(),
			);
		}
		if (!isset($schema['type'])) {
			$schema['type'] = 'object';
		}
		return Detector::is_sitepad() ? self::scrub_tree($schema) : $schema;
	}

	/**
	 * Remove the Pagelayer name from schema text. Property names stay unchanged.
	 *
	 * @param mixed $value Schema node.
	 * @return mixed
	 */
	private static function scrub_tree($value) {
		if (is_string($value)) {
			return self::scrub($value);
		}
		if (!is_array($value)) {
			return $value;
		}
		$out = array();
		foreach ($value as $key => $item) {
			$out[$key] = self::scrub_tree($item);
		}
		return $out;
	}
}