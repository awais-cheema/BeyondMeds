<?php
/**
 * Page builder documents.
 *
 * Read abilities return an outline. Write abilities merge settings into one element.
 *
 * @package OneMCPPro
 */

namespace OneMCPPro;

use ONEMCP\Detector;
use ONEMCP\Helper;

use WP_Query;
use WP_Post;
use Throwable;

if (!defined('ABSPATH')) {
	exit;
}

require_once __DIR__ . '/builder-design.php';

class Builders {

	use BuilderDesign;


	/**
	 * Ability definitions for the builders that are active.
	 *
	 * @return array<int,array<string,mixed>>
	 */
	 static function definitions() {
		$definitions = array();
		$builders    = array(
			'pagelayer'   => 'Pagelayer',
			'elementor'   => 'Elementor',
			'seedprod'    => 'SeedProd',
			'divi'        => 'Divi',
			'bricks'      => 'Bricks',
			'beaver'      => 'Beaver Builder',
			'breakdance'  => 'Breakdance',
		);
		foreach ($builders as $slug => $label) {
			if (!\ONEMCP\Detector::is_active($slug)) {
				continue;
			}
			// Pagelayer's own abilities create pages, posts, theme templates, and widgets.
			if ($slug === 'pagelayer' && \OneMCPPro\Pagelayer::editor_definitions() !== array()) {
				continue;
			}
			$definitions = array_merge($definitions, self::builder_abilities($slug, $label));
		}
		return $definitions;
	}

	/**
	 * List, read, and update abilities for one builder.
	 *
	 * @param string $slug  Builder slug.
	 * @param string $label Builder name.
	 * @return array<int,array<string,mixed>>
	 */
	private static function builder_abilities($slug, $label) {
		$structure_description = sprintf(
			/* translators: %s: builder name. */
			__('Returns an outline of the %s elements on one document. Set include_raw to also return the document when it is small enough.', 'onemcp-pro'),
			$label
		);
		$update_description = sprintf(
			/* translators: %s: builder name. */
			__('Merges settings into one %1$s widget. Use the element id from the structure outline. settings must be that widget\'s own %1$s controls. Do not invent setting names. For Divi shortcodes, the id is the module admin_label or its numeric position.', 'onemcp-pro'),
			$label
		);
		if ($slug === 'pagelayer') {
			$structure_description = __('Returns an outline of the Pagelayer elements on one document. Each id is the element pagelayer-id, or an @0.1 path when the element has no id yet. Set include_raw to also return the document when it is small enough.', 'onemcp-pro');
			$update_description    = __('Merges attributes into one Pagelayer element. Use the id from the structure outline. A content value replaces that element\'s inner HTML when it has no child elements.', 'onemcp-pro');
		}
		list($label, $structure_description, $update_description) = self::editor_copy($slug, $label, $structure_description, $update_description);
		$name = self::design_slug($slug);

		$abilities = array(
			Helper::ability(
				'list-' . $name . '-documents',
				sprintf(
					/* translators: %s: builder name. */
					__('List %s documents', 'onemcp-pro'),
					$label
				),
				sprintf(
					/* translators: %s: builder name. */
					__('Lists pages, posts, custom post types, and templates built with %s.', 'onemcp-pro'),
					$label
				),
				self::design_category(),
				true,
				function ($input) use ($slug) {
					return Builders::list_documents($slug, $input);
				},
				array(
					'post_type' => Helper::string_prop(__('Limit the list to one post type, including a custom post type.', 'onemcp-pro'), 40),
					'search'    => Helper::string_prop(__('Search titles.', 'onemcp-pro'), 120),
					'limit'     => Helper::int_prop(__('How many documents to return. Maximum 50.', 'onemcp-pro')),
					'page'      => Helper::int_prop(__('Page number, starting at 1.', 'onemcp-pro')),
				),
				array(),
				self::design_extra(true)
			),
			Helper::ability(
				'get-' . $name . '-structure',
				sprintf(
					/* translators: %s: builder name. */
					__('Get %s structure', 'onemcp-pro'),
					$label
				),
				$structure_description,
				self::design_category(),
				true,
				function ($input) use ($slug) {
					return \OneMCPPro\Builders::get_structure($slug, $input);
				},
				array(
					'post_id'     => Helper::int_prop(__('Document ID.', 'onemcp-pro')),
					'include_raw' => Helper::bool_prop(__('Include the raw builder document when it fits in a tool response.', 'onemcp-pro')),
				),
				array('post_id'),
				self::design_extra(true)
			),
			Helper::ability(
				'update-' . $name . '-element',
				sprintf(
					/* translators: %s: builder name. */
					__('Update %s element', 'onemcp-pro'),
					$label
				),
				$update_description,
				self::design_category(),
				false,
				function ($input) use ($slug) {
					return \OneMCPPro\Builders::update_element($slug, $input);
				},
				array(
					'post_id'    => Helper::int_prop(__('Document ID.', 'onemcp-pro')),
					'element_id' => Helper::string_prop(__('Element id from the structure outline.', 'onemcp-pro'), 120),
					'settings'   => Helper::object_prop(__('Settings to merge into the element. Existing keys not included here are left as they are.', 'onemcp-pro')),
				),
				array('post_id', 'element_id', 'settings'),
				self::design_extra(false, $slug !== 'pagelayer')
			),
		);
		return array_merge($abilities, self::design_abilities($slug, $label));
	}

	/**
	 * List documents for one builder.
	 *
	 * @param string              $slug  Builder slug.
	 * @param array<string,mixed> $input Input.
	 * @return array<string,mixed>|WP_Error
	 */
	static function list_documents($slug, $input) {
		$args = self::query_args($slug);
		if (is_wp_error($args)) {
			return $args;
		}
		$limit = Helper::limit(isset($input['limit']) ? $input['limit'] : 20, 20, 50);
		$page  = Helper::page($input);
		$args['posts_per_page'] = $limit;
		$args['paged']          = $page;
		$args['orderby']        = 'modified';
		$args['order']          = 'DESC';
		if (!empty($input['search'])) {
			$args['s'] = Helper::text($input['search'], 120);
		}
		if (!empty($input['post_type'])) {
			$type = Helper::post_type($input['post_type']);
			if (is_wp_error($type)) {
				return $type;
			}
			$args['post_type'] = $type;
		}

		$query = Helper::query_posts($args);
		$items = array();
		foreach ($query->posts as $post) {
			if ($post instanceof WP_Post) {
				$items[] = Helper::post_row($post);
			}
		}
		return array(
			'builder' => $slug,
			'items'   => $items,
			'total'   => (int) $query->found_posts,
			'page'    => $page,
		);
	}

	/**
	 * Outline one document.
	 *
	 * @param string              $slug  Builder slug.
	 * @param array<string,mixed> $input Input.
	 * @return array<string,mixed>|WP_Error
	 */
	static function get_structure($slug, $input) {
		$post = self::document($slug, isset($input['post_id']) ? $input['post_id'] : 0);
		if (is_wp_error($post)) {
			return $post;
		}
		$loaded = self::load($slug, $post);
		if (is_wp_error($loaded)) {
			return $loaded;
		}
		$outline = array();
		if ($slug === 'pagelayer') {
			if (!empty($loaded['blocks']) && is_array($loaded['blocks'])) {
				$outline = self::pagelayer_outline_blocks($loaded['blocks']);
			} else {
				$outline = self::pagelayer_outline_nodes($loaded['data']);
			}
		} elseif ($slug === 'bricks' || $slug === 'beaver') {
			$outline = self::flat_outline($loaded['data']);
		} elseif ($slug === 'divi' && !empty($loaded['shortcodes'])) {
			$outline = self::divi_outline($post->post_content);
		} else {
			$outline = Tree::summarize($loaded['data'], self::tree_args($slug));
		}

		$response = array(
			'builder' => $slug,
			'post'    => Helper::post_row($post),
			'count'   => count($outline),
			'outline' => $outline,
		);
		if (!empty($input['include_raw'])) {
			$response['document'] = Helper::raw_document($loaded['raw'], true);
		}
		return $response;
	}

	/**
	 * Merge settings into one element and save the document.
	 *
	 * @param string              $slug  Builder slug.
	 * @param array<string,mixed> $input Input.
	 * @return array<string,mixed>|WP_Error
	 */
	 static function update_element($slug, $input) {
		$post = self::document($slug, isset($input['post_id']) ? $input['post_id'] : 0);
		if (is_wp_error($post)) {
			return $post;
		}
		$element_id = Helper::text(isset($input['element_id']) ? $input['element_id'] : '', 120);
		$settings   = Helper::clean_settings(isset($input['settings']) ? $input['settings'] : array());
		if ($element_id === '' || !is_array($settings) || $settings === array()) {
			return Helper::error('onemcp_invalid', __('Provide an element id and at least one setting.', 'onemcp-pro'));
		}

		$loaded = self::load($slug, $post);
		if (is_wp_error($loaded)) {
			return $loaded;
		}

		if ($slug === 'pagelayer') {
			return self::pagelayer_update_document($post, $element_id, $settings, $loaded);
		}

		if ($slug === 'divi' && !empty($loaded['shortcodes'])) {
			$updated = self::update_divi_shortcode($post->post_content, $element_id, $settings);
			if (is_wp_error($updated)) {
				return $updated;
			}
			$result = wp_update_post(wp_slash(array(
				'ID'           => $post->ID,
				'post_content' => $updated,
			)), true);
			if (is_wp_error($result)) {
				return $result;
			}
			return array(
				'builder'    => $slug,
				'post_id'    => (int) $post->ID,
				'element_id' => $element_id,
				'updated'    => true,
			);
		}

		$data  = $loaded['data'];
		$found = false;
		if ($slug === 'bricks') {
			self::update_bricks($data, $element_id, $settings, $found);
		} elseif ($slug === 'beaver') {
			self::update_beaver($data, $element_id, $settings, $found);
		} else {
			self::merge_tree($data, $element_id, $settings, $found);
		}
		if (!$found) {
			return Helper::error('onemcp_not_found', __('That element was not found in the document.', 'onemcp-pro'));
		}

		$saved = self::save($slug, $post, $data, $loaded);
		if (is_wp_error($saved)) {
			return $saved;
		}
		return array(
			'builder'    => $slug,
			'post_id'    => (int) $post->ID,
			'element_id' => $element_id,
			'updated'    => true,
		);
	}

	/**
	 * WP_Query arguments that find one builder's documents.
	 *
	 * @param string $slug Builder slug.
	 * @return array<string,mixed>|WP_Error
	 */
	private static function query_args($slug) {
		$args = array(
			'post_status' => array('publish', 'draft', 'pending', 'private', 'future'),
			'post_type'   => 'any',
		);
		if ($slug === 'pagelayer') {
			$args['meta_key'] = 'pagelayer-data';
			$args['meta_compare'] = 'EXISTS';
			return $args;
		}
		if ($slug === 'elementor') {
			$args['meta_key']   = '_elementor_edit_mode';
			$args['meta_value'] = 'builder';
			return $args;
		}
		if ($slug === 'seedprod') {
			if (post_type_exists('seedprod')) {
				$args['post_type'] = 'seedprod';
				return $args;
			}
			$args['meta_key'] = '_seedprod_page';
			return $args;
		}
		if ($slug === 'divi') {
			$args['meta_query'] = array(
				array(
					'key'   => '_et_pb_use_builder',
					'value' => 'on',
				),
			);
			return $args;
		}
		if ($slug === 'bricks') {
			$args['meta_query'] = array(
				'relation' => 'OR',
				array(
					'key'   => '_bricks_editor_mode',
					'value' => 'bricks',
				),
				array(
					'key'     => '_bricks_page_content_2',
					'compare' => 'EXISTS',
				),
			);
			return $args;
		}
		if ($slug === 'beaver') {
			$args['meta_key']   = '_fl_builder_enabled';
			$args['meta_value'] = '1';
			return $args;
		}
		if ($slug === 'breakdance') {
			$args['meta_key'] = '_breakdance_data';
			$args['meta_compare'] = 'EXISTS';
			return $args;
		}
		return Helper::error('onemcp_invalid', __('That builder is not supported.', 'onemcp-pro'));
	}

	/**
	 * A document the current user can edit.
	 *
	 * @param string $slug    Builder slug.
	 * @param mixed  $post_id Post ID.
	 * @return WP_Post|WP_Error
	 */
	private static function document($slug, $post_id) {
		$post = Helper::editable_post($post_id);
		if (is_wp_error($post)) {
			return $post;
		}
		if (!self::belongs($slug, $post)) {
			return Helper::error('onemcp_not_builder', __('This content is not a document for that builder.', 'onemcp-pro'));
		}
		return $post;
	}

	/**
	 * Whether a post stores data for this builder.
	 *
	 * @param string  $slug Builder slug.
	 * @param WP_Post $post Document.
	 * @return bool
	 */
	private static function belongs($slug, $post) {
		if ($slug === 'pagelayer') {
			$meta = get_post_meta($post->ID, 'pagelayer-data', true);
			if ($meta !== '' && $meta !== null && $meta !== false && $meta !== array()) {
				return true;
			}
			if ($post->post_type === 'pagelayer-template') {
				return true;
			}
			$content = (string) $post->post_content;
			return strpos($content, 'pagelayer/') !== false || strpos($content, '[pl_') !== false;
		}
		if ($slug === 'elementor') {
			return get_post_meta($post->ID, '_elementor_edit_mode', true) === 'builder';
		}
		if ($slug === 'seedprod') {
			return $post->post_type === 'seedprod' || get_post_meta($post->ID, '_seedprod_page', true) !== '';
		}
		if ($slug === 'divi') {
			return get_post_meta($post->ID, '_et_pb_use_builder', true) === 'on';
		}
		if ($slug === 'bricks') {
			$mode = get_post_meta($post->ID, '_bricks_editor_mode', true);
			$data = get_post_meta($post->ID, '_bricks_page_content_2', true);
			return $mode === 'bricks' || (is_array($data) && $data !== array());
		}
		if ($slug === 'beaver') {
			return (string) get_post_meta($post->ID, '_fl_builder_enabled', true) === '1';
		}
		if ($slug === 'breakdance') {
			$data = get_post_meta($post->ID, '_breakdance_data', true);
			return $data !== '' && $data !== array() && $data !== null;
		}
		return false;
	}

	/**
	 * Load builder data.
	 *
	 * @param string  $slug Builder slug.
	 * @param WP_Post $post Document.
	 * @return array<string,mixed>|WP_Error
	 */
	private static function load($slug, $post) {
		if ($slug === 'pagelayer') {
			return self::load_pagelayer($post);
		}

		if ($slug === 'elementor') {
			$raw  = get_post_meta($post->ID, '_elementor_data', true);
			$data = is_array($raw) ? $raw : json_decode((string) $raw, true);
			if (!is_array($data)) {
				return Helper::error('onemcp_empty', __('This Elementor document has no data.', 'onemcp-pro'));
			}
			return array('data' => $data, 'raw' => $data);
		}

		if ($slug === 'seedprod') {
			// Current SeedProd stores the builder JSON in post_content_filtered.
			// _seedprod_page is usually the flag "1". Older copies keep the JSON in that meta.
			$data   = Helper::decode_meta($post->post_content_filtered);
			$source = 'filtered';
			if ($data === array()) {
				$data   = Helper::decode_meta(get_post_meta($post->ID, '_seedprod_page', true));
				$source = 'meta';
			}
			if ($data === array()) {
				return Helper::error('onemcp_empty', __('This SeedProd document has no data.', 'onemcp-pro'));
			}
			$tree = isset($data['document']) && is_array($data['document']) ? $data['document'] : $data;
			return array(
				'data'    => $tree,
				'raw'     => $data,
				'wrapped' => isset($data['document']),
				'source'  => $source,
			);
		}

		if ($slug === 'divi') {
			$content = (string) $post->post_content;
			$trim    = ltrim($content);
			if ($trim !== '' && ($trim[0] === '{' || $trim[0] === '[')) {
				$json = json_decode($content, true);
				if (is_array($json)) {
					return array('data' => $json, 'raw' => $json, 'shortcodes' => false);
				}
			}
			if (strpos($content, '[et_pb_') === false) {
				return Helper::error('onemcp_empty', __('This Divi document has no modules.', 'onemcp-pro'));
			}
			return array('data' => array(), 'raw' => $content, 'shortcodes' => true);
		}

		if ($slug === 'bricks') {
			$data = get_post_meta($post->ID, '_bricks_page_content_2', true);
			$key  = '_bricks_page_content_2';
			if (!is_array($data) || $data === array()) {
				$data = get_post_meta($post->ID, '_bricks_page_content', true);
				$key  = '_bricks_page_content';
			}
			if (!is_array($data)) {
				return Helper::error('onemcp_empty', __('This Bricks document has no data.', 'onemcp-pro'));
			}
			return array('data' => $data, 'raw' => $data, 'meta_key' => $key);
		}

		if ($slug === 'beaver') {
			$data = get_post_meta($post->ID, '_fl_builder_data', true);
			if (!is_array($data)) {
				return Helper::error('onemcp_empty', __('This Beaver Builder document has no data.', 'onemcp-pro'));
			}
			return array('data' => $data, 'raw' => $data);
		}

		if ($slug === 'breakdance') {
			$stored = get_post_meta($post->ID, '_breakdance_data', true);
			$meta   = is_array($stored) ? $stored : json_decode((string) $stored, true);
			if (!is_array($meta)) {
				return Helper::error('onemcp_empty', __('This Breakdance document has no data.', 'onemcp-pro'));
			}
			$tree = array();
			if (!empty($meta['tree_json_string']) && is_string($meta['tree_json_string'])) {
				$decoded = json_decode($meta['tree_json_string'], true);
				$tree    = is_array($decoded) ? $decoded : array();
			} elseif (isset($meta['tree']) && is_array($meta['tree'])) {
				$tree = $meta['tree'];
			}
			if ($tree === array()) {
				return Helper::error('onemcp_empty', __('This Breakdance document has no tree.', 'onemcp-pro'));
			}
			return array('data' => $tree, 'raw' => $tree, 'meta' => $meta);
		}

		return Helper::error('onemcp_invalid', __('That builder is not supported.', 'onemcp-pro'));
	}

	/**
	 * Persist builder data.
	 *
	 * @param string              $slug   Builder slug.
	 * @param WP_Post             $post   Document.
	 * @param array<mixed>        $data   Updated tree or element list.
	 * @param array<string,mixed> $loaded Original load payload.
	 * @return true|WP_Error
	 */
	private static function save($slug, $post, $data, $loaded) {
		if ($slug === 'pagelayer') {
			return self::save_pagelayer($post, $data, $loaded);
		}

		if ($slug === 'elementor') {
			$json = wp_json_encode($data);
			if (!is_string($json)) {
				return Helper::error('onemcp_failed', __('Elementor data could not be encoded.', 'onemcp-pro'));
			}
			update_post_meta($post->ID, '_elementor_data', wp_slash($json));
			delete_post_meta($post->ID, '_elementor_css');
			delete_post_meta($post->ID, '_elementor_element_cache');
			if (class_exists('\Elementor\Plugin') && isset(\Elementor\Plugin::$instance->files_manager)) {
				$files = \Elementor\Plugin::$instance->files_manager;
				if (is_object($files) && method_exists($files, 'clear_cache')) {
					$files->clear_cache();
				}
			}
			return true;
		}

		if ($slug === 'seedprod') {
			$raw = $loaded['raw'];
			if (!empty($loaded['wrapped']) && is_array($raw)) {
				$raw['document'] = $data;
			} else {
				$raw = $data;
			}
			$json = wp_json_encode($raw);
			if (!is_string($json)) {
				return Helper::error('onemcp_failed', __('SeedProd data could not be encoded.', 'onemcp-pro'));
			}
			if (isset($loaded['source']) && $loaded['source'] === 'meta') {
				update_post_meta($post->ID, '_seedprod_page', wp_slash($json));
			}
			$result = wp_update_post(wp_slash(array(
				'ID'                    => $post->ID,
				'post_content_filtered' => $json,
			)), true);
			return is_wp_error($result) ? $result : true;
		}

		if ($slug === 'divi') {
			$json = wp_json_encode($data);
			if (!is_string($json)) {
				return Helper::error('onemcp_failed', __('Divi data could not be encoded.', 'onemcp-pro'));
			}
			$result = wp_update_post(wp_slash(array(
				'ID'           => $post->ID,
				'post_content' => $json,
			)), true);
			return is_wp_error($result) ? $result : true;
		}

		if ($slug === 'bricks') {
			$key = isset($loaded['meta_key']) ? $loaded['meta_key'] : '_bricks_page_content_2';
			update_post_meta($post->ID, $key, $data);
			return true;
		}

		if ($slug === 'beaver') {
			if (class_exists('FLBuilderModel') && method_exists('FLBuilderModel', 'update_layout_data')) {
				\FLBuilderModel::update_layout_data($data, 'published', $post->ID);
				return true;
			}
			update_post_meta($post->ID, '_fl_builder_data', $data);
			return true;
		}

		if ($slug === 'breakdance') {
			$meta = isset($loaded['meta']) && is_array($loaded['meta']) ? $loaded['meta'] : array();
			$json = wp_json_encode($data);
			if (!is_string($json)) {
				return Helper::error('onemcp_failed', __('Breakdance data could not be encoded.', 'onemcp-pro'));
			}
			if (isset($meta['tree_json_string']) || !isset($meta['tree'])) {
				$meta['tree_json_string'] = $json;
			} else {
				$meta['tree'] = $data;
			}
			// set_meta() stores this as a JSON string. A PHP array is rejected by the decoder.
			$stored = wp_json_encode($meta);
			if (!is_string($stored)) {
				return Helper::error('onemcp_failed', __('Breakdance data could not be encoded.', 'onemcp-pro'));
			}
			update_post_meta($post->ID, '_breakdance_data', wp_slash($stored));
			if (function_exists('\\Breakdance\\Render\\generateCacheForPost')) {
				try {
					\Breakdance\Render\generateCacheForPost($post->ID);
				} catch (Throwable $exception) {
					unset($exception);
				}
			}
			do_action('breakdance_after_save_document', $post->ID);
			return true;
		}

		return Helper::error('onemcp_invalid', __('That builder is not supported.', 'onemcp-pro'));
	}

	/**
	 * Tree walker arguments.
	 *
	 * @param string $slug Builder slug.
	 * @return array<string,array<int,string>>
	 */
	private static function tree_args($slug) {
		if ($slug === 'elementor') {
			return array(
				'id_keys'    => array('id'),
				'type_keys'  => array('widgetType', 'elType'),
				'child_keys' => array('elements'),
			);
		}
		return array(
			'id_keys'    => array('id', 'element_id'),
			'type_keys'  => array('type', 'widgetType', 'elType', 'name'),
			'child_keys' => array('elements', 'children', 'sections', 'rows', 'cols', 'blocks', 'content', 'nodes'),
		);
	}

	/**
	 * Outline a flat element list used by Bricks and Beaver Builder.
	 *
	 * @param array<mixed> $elements Elements.
	 * @return array<int,array<string,string>>
	 */
	private static function flat_outline($elements) {
		$out = array();
		foreach ($elements as $key => $element) {
			if (count($out) >= 200) {
				break;
			}
			$values = is_object($element) ? get_object_vars($element) : (is_array($element) ? $element : array());
			if ($values === array()) {
				continue;
			}
			$id = '';
			if (isset($values['id'])) {
				$id = (string) $values['id'];
			} elseif (isset($values['node'])) {
				$id = (string) $values['node'];
			} else {
				$id = (string) $key;
			}
			$type = '';
			if (isset($values['name'])) {
				$type = (string) $values['name'];
			} elseif (isset($values['type'])) {
				$type = (string) $values['type'];
			} elseif (isset($values['settings']->type)) {
				$type = (string) $values['settings']->type;
			}
			$settings = array();
			if (isset($values['settings'])) {
				$settings = is_object($values['settings']) ? get_object_vars($values['settings']) : (array) $values['settings'];
			}
			$row = array(
				'id'   => $id,
				'type' => $type,
			);
			if (isset($values['parent'])) {
				$row['parent'] = (string) $values['parent'];
			}
			$text = Tree::first_text($settings);
			if ($text !== '') {
				$row['text'] = $text;
			}
			$out[] = $row;
		}
		return $out;
	}

	/**
	 * Outline Divi shortcodes.
	 *
	 * @param string $content Post content.
	 * @return array<int,array<string,string>>
	 */
	private static function divi_outline($content) {
		$out = array();
		if (!preg_match_all('/\[(et_pb_[a-z0-9_]+)([^\]]*)\]/', $content, $matches, PREG_SET_ORDER)) {
			return $out;
		}
		$index = 0;
		foreach ($matches as $match) {
			if (count($out) >= 200) {
				break;
			}
			$attrs = $match[2];
			$label = '';
			if (preg_match('/admin_label=("|\')(.*?)\1/', $attrs, $label_match)) {
				$label = $label_match[2];
			}
			$row = array(
				'id'   => $label !== '' ? $label : (string) $index,
				'type' => $match[1],
				'path' => (string) $index,
			);
			if ($label !== '') {
				$row['admin_label'] = $label;
			}
			$out[] = $row;
			$index++;
		}
		return $out;
	}

	/**
	 * Replace attributes, and optionally inner content, on one Divi module.
	 *
	 * @param string              $content    Post content.
	 * @param string              $element_id Admin label or numeric module index.
	 * @param array<string,mixed> $settings   Settings.
	 * @return string|WP_Error
	 */
	private static function update_divi_shortcode($content, $element_id, $settings) {
		$pattern = '/\[(et_pb_[a-z0-9_]+)([^\]]*)\]/';
		if (!preg_match_all($pattern, $content, $matches, PREG_OFFSET_CAPTURE)) {
			return Helper::error('onemcp_not_found', __('No Divi modules were found.', 'onemcp-pro'));
		}

		$target = null;
		if (preg_match('/^[0-9]+$/', $element_id)) {
			$index = (int) $element_id;
			if (isset($matches[0][$index])) {
				$target = $index;
			}
		}
		if ($target === null) {
			foreach ($matches[2] as $index => $attr_match) {
				if (preg_match('/admin_label=("|\')' . preg_quote($element_id, '/') . '\1/', $attr_match[0])) {
					$target = $index;
					break;
				}
			}
		}
		if ($target === null) {
			return Helper::error('onemcp_not_found', __('That Divi module was not found. Use its admin_label or the numeric path from the outline.', 'onemcp-pro'));
		}

		$full   = $matches[0][$target][0];
		$offset = $matches[0][$target][1];
		$tag    = $matches[1][$target][0];
		$attrs  = $matches[2][$target][0];
		$inner  = null;

		foreach ($settings as $key => $value) {
			if (!is_string($key) || !is_scalar($value)) {
				continue;
			}
			if ($key === 'content') {
				$inner = (string) $value;
				continue;
			}
			$attr = sanitize_key(str_replace('-', '_', $key));
			if ($attr === '') {
				continue;
			}
			$encoded = esc_attr((string) $value);
			$attr_pattern = '/' . preg_quote($attr, '/') . '=("|\')(.*?)\1/';
			if (preg_match($attr_pattern, $attrs)) {
				$attrs = preg_replace($attr_pattern, $attr . '="' . $encoded . '"', $attrs, 1);
			} else {
				$attrs .= ' ' . $attr . '="' . $encoded . '"';
			}
		}

		$opening = '[' . $tag . $attrs . ']';
		$content = substr_replace($content, $opening, $offset, strlen($full));
		if ($inner !== null) {
			$close = '[/' . $tag . ']';
			$start = $offset + strlen($opening);
			$end   = strpos($content, $close, $start);
			if ($end === false) {
				return Helper::error('onemcp_failed', __('That Divi module has no closing tag, so its inner content was not changed.', 'onemcp-pro'));
			}
			$content = substr_replace($content, $inner, $start, $end - $start);
		}
		return $content;
	}

	/**
	 * Merge settings into a nested builder tree.
	 *
	 * @param array<mixed>        $nodes      Tree.
	 * @param string              $element_id Element id.
	 * @param array<string,mixed> $settings   Settings.
	 * @param bool                $found      Set when updated.
	 * @param int                 $depth      Recursion depth.
	 * @return void
	 */
	private static function merge_tree(&$nodes, $element_id, $settings, &$found, $depth = 0) {
		if ($found || $depth > 30 || !is_array($nodes)) {
			return;
		}
		if (Helper::is_list($nodes)) {
			foreach ($nodes as &$node) {
				self::merge_tree($node, $element_id, $settings, $found, $depth + 1);
				if ($found) {
					return;
				}
			}
			unset($node);
			return;
		}

		foreach (array('id', 'element_id') as $id_key) {
			if (isset($nodes[$id_key]) && (string) $nodes[$id_key] === (string) $element_id) {
				self::merge_node($nodes, $settings);
				$found = true;
				return;
			}
		}

		foreach (self::tree_args('generic')['child_keys'] as $child_key) {
			if (!empty($nodes[$child_key]) && is_array($nodes[$child_key])) {
				self::merge_tree($nodes[$child_key], $element_id, $settings, $found, $depth + 1);
				if ($found) {
					return;
				}
			}
		}
	}

	/**
	 * Merge settings onto the field the builder actually reads.
	 *
	 * @param array<string,mixed> $node     Element.
	 * @param array<string,mixed> $settings Settings.
	 * @return void
	 */
	private static function merge_node(&$node, $settings) {
		if (isset($node['data']['properties']) && is_array($node['data']['properties'])) {
			$node['data']['properties'] = array_replace_recursive($node['data']['properties'], $settings);
			return;
		}
		if (isset($node['props']) && is_array($node['props'])) {
			$node['props'] = array_replace_recursive($node['props'], $settings);
			return;
		}
		if (isset($node['attrs']) && is_array($node['attrs'])) {
			$node['attrs'] = array_replace_recursive($node['attrs'], $settings);
			return;
		}

		\OneMCPPro\Tree::merge_settings($node, $settings);
	}

	/**
	 * Update one Bricks element.
	 *
	 * @param array<mixed>        $elements   Element list.
	 * @param string              $element_id Element id.
	 * @param array<string,mixed> $settings   Settings.
	 * @param bool                $found      Set when updated.
	 * @return void
	 */
	private static function update_bricks(&$elements, $element_id, $settings, &$found) {
		foreach ($elements as &$element) {
			if (!is_array($element) || !isset($element['id']) || (string) $element['id'] !== (string) $element_id) {
				continue;
			}
			$current = isset($element['settings']) && is_array($element['settings']) ? $element['settings'] : array();
			$element['settings'] = array_merge($current, $settings);
			$found = true;
			return;
		}
		unset($element);
	}

	/**
	 * Update one Beaver Builder node.
	 *
	 * @param array<mixed>        $nodes      Nodes keyed by id.
	 * @param string              $element_id Node id.
	 * @param array<string,mixed> $settings   Settings.
	 * @param bool                $found      Set when updated.
	 * @return void
	 */
	private static function update_beaver(&$nodes, $element_id, $settings, &$found) {
		foreach ($nodes as $key => $node) {
			$node_id = (string) $key;
			if (is_object($node) && isset($node->node)) {
				$node_id = (string) $node->node;
			} elseif (is_array($node) && isset($node['node'])) {
				$node_id = (string) $node['node'];
			}
			if ($node_id !== (string) $element_id) {
				continue;
			}
			if (is_object($node)) {
				$current = isset($node->settings) ? (array) $node->settings : array();
				$node->settings = (object) array_merge($current, $settings);
				$nodes[$key] = $node;
			} elseif (is_array($node)) {
				$current = isset($node['settings']) ? (array) $node['settings'] : array();
				$node['settings'] = array_merge($current, $settings);
				$nodes[$key] = $node;
			}
			$found = true;
			return;
		}
	}

	/**
	 * Load a Pagelayer document.
	 *
	 * The visual editor stores Gutenberg blocks in post_content and a timestamp
	 * in pagelayer-data. The abilities API may store a node tree in that meta
	 * instead. Blocks win when both exist, because that is what the front end renders.
	 *
	 * @param WP_Post $post Document.
	 * @return array<string,mixed>|WP_Error
	 */
	private static function load_pagelayer($post) {
		$content   = (string) $post->post_content;
		$converted = false;
		if (strpos($content, 'pagelayer/') === false && strpos($content, '[pl_') !== false && function_exists('pagelayer_do_shortcode_to_block')) {
			$content   = pagelayer_do_shortcode_to_block($content);
			$converted = true;
		}

		$blocks = function_exists('parse_blocks') ? parse_blocks($content) : array();
		$meta   = get_post_meta($post->ID, 'pagelayer-data', true);
		$tree   = self::pagelayer_node_tree($meta);

		if (self::pagelayer_blocks_present($blocks)) {
			return array(
				'data'      => self::pagelayer_blocks_to_nodes($blocks),
				'raw'       => $blocks,
				'blocks'    => $blocks,
				'source'    => 'blocks',
				'sync_meta' => $tree !== null,
				'converted' => $converted,
			);
		}

		if ($tree !== null) {
			return array(
				'data'   => $tree,
				'raw'    => $tree,
				'source' => 'meta',
			);
		}

		return array(
			'data'   => array(),
			'raw'    => array(),
			'blocks' => array(),
			'source' => 'blocks',
		);
	}

	/**
	 * Persist a Pagelayer document.
	 *
	 * @param WP_Post             $post   Document.
	 * @param array<mixed>        $data   Updated blocks or node tree.
	 * @param array<string,mixed> $loaded Original load payload.
	 * @return true|WP_Error
	 */
	private static function save_pagelayer($post, $data, $loaded) {
		if (isset($loaded['source']) && $loaded['source'] === 'meta') {
			update_post_meta($post->ID, 'pagelayer-data', $data);
			$content = self::pagelayer_serialize_nodes($data);
			$result  = wp_update_post(wp_slash(array(
				'ID'           => $post->ID,
				'post_content' => $content,
			)), true);
			return is_wp_error($result) ? $result : true;
		}

		if (!function_exists('serialize_blocks')) {
			return Helper::error('onemcp_failed', __('WordPress cannot serialize Pagelayer blocks on this site.', 'onemcp-pro'));
		}

		$content = serialize_blocks(is_array($data) ? $data : array());
		$result  = wp_update_post(wp_slash(array(
			'ID'           => $post->ID,
			'post_content' => $content,
		)), true);
		if (is_wp_error($result)) {
			return $result;
		}
		if (!empty($loaded['sync_meta'])) {
			update_post_meta($post->ID, 'pagelayer-data', self::pagelayer_blocks_to_nodes($data));
		}
		return true;
	}

	/**
	 * Update one Pagelayer element and save the document.
	 *
	 * @param WP_Post             $post       Document.
	 * @param string              $element_id Element id or @path.
	 * @param array<string,mixed> $settings   Attributes to merge. content replaces inner HTML.
	 * @param array<string,mixed> $loaded     Load payload.
	 * @return array<string,mixed>|WP_Error
	 */
	private static function pagelayer_update_document($post, $element_id, $settings, $loaded) {
		$found = false;
		if (isset($loaded['source']) && $loaded['source'] === 'meta') {
			$data = isset($loaded['data']) && is_array($loaded['data']) ? $loaded['data'] : array();
			self::pagelayer_update_nodes($data, $element_id, $settings, $found);
			if (!$found) {
				return Helper::error('onemcp_not_found', __('That element was not found in the document.', 'onemcp-pro'));
			}
			$saved = self::save('pagelayer', $post, $data, $loaded);
		} else {
			$blocks = isset($loaded['blocks']) && is_array($loaded['blocks']) ? $loaded['blocks'] : array();
			self::pagelayer_update_blocks($blocks, $element_id, $settings, $found);
			if (!$found) {
				return Helper::error('onemcp_not_found', __('That element was not found in the document.', 'onemcp-pro'));
			}
			$saved = self::save('pagelayer', $post, $blocks, $loaded);
		}
		if (is_wp_error($saved)) {
			return $saved;
		}
		return array(
			'builder'    => 'pagelayer',
			'post_id'    => (int) $post->ID,
			'element_id' => $element_id,
			'updated'    => true,
		);
	}

	/**
	 * A stored pagelayer-data value that is a layout tree, not the editor timestamp.
	 *
	 * @param mixed $meta Meta value.
	 * @return array<mixed>|null
	 */
	private static function pagelayer_node_tree($meta) {
		if (is_string($meta)) {
			$meta = Helper::decode_meta($meta);
		}
		if (!is_array($meta) || $meta === array()) {
			return null;
		}
		if (isset($meta['pagelayer_data']) && is_array($meta['pagelayer_data'])) {
			$meta = $meta['pagelayer_data'];
		}
		$first = reset($meta);
		if (!is_array($first) || empty($first['tag']) || !is_string($first['tag'])) {
			return null;
		}
		return array_values($meta);
	}

	/**
	 * Whether parsed blocks include at least one Pagelayer block.
	 *
	 * @param array<mixed> $blocks Parsed blocks.
	 * @return bool
	 */
	private static function pagelayer_blocks_present($blocks) {
		if (!is_array($blocks)) {
			return false;
		}
		foreach ($blocks as $block) {
			if (self::pagelayer_block_tag($block) !== '') {
				return true;
			}
			if (is_array($block) && !empty($block['innerBlocks']) && self::pagelayer_blocks_present($block['innerBlocks'])) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Widget tag from a parsed block name, or an empty string.
	 *
	 * @param mixed $block Parsed block.
	 * @return string
	 */
	private static function pagelayer_block_tag($block) {
		if (!is_array($block) || empty($block['blockName']) || !is_string($block['blockName'])) {
			return '';
		}
		$pos = strpos($block['blockName'], 'pagelayer/');
		if ($pos === false) {
			return '';
		}
		return substr($block['blockName'], $pos + 10);
	}

	/**
	 * Whether an outline id matches this element.
	 *
	 * @param string $id         pagelayer-id, possibly empty.
	 * @param string $path       Positional path such as 0.1.
	 * @param string $element_id Requested id.
	 * @return bool
	 */
	private static function pagelayer_id_matches($id, $path, $element_id) {
		$element_id = (string) $element_id;
		if ($id !== '' && $id === $element_id) {
			return true;
		}
		return $element_id === '@' . $path || $element_id === $path;
	}

	/**
	 * Short visible text for one element.
	 *
	 * @param array<string,mixed> $attrs Attributes.
	 * @param string              $html  Inner HTML.
	 * @return string
	 */
	private static function pagelayer_preview_text($attrs, $html) {
		$text = '';
		if (is_string($html) && trim($html) !== '') {
			$text = Helper::trim_text($html, 80);
		}
		if ($text === '' && is_array($attrs)) {
			foreach (array('text', 'heading', 'title', 'service_heading', 'service_text', 'quote_content', 'cite', 'caption') as $key) {
				if (!empty($attrs[$key]) && is_string($attrs[$key])) {
					$text = Helper::trim_text($attrs[$key], 80);
					break;
				}
			}
		}
		return $text;
	}

	/**
	 * Outline parsed Pagelayer blocks.
	 *
	 * @param array<mixed>                    $blocks Parsed blocks.
	 * @param array<int,array<string,string>> $out    Collected rows.
	 * @param string                          $path   Path prefix.
	 * @return array<int,array<string,string>>
	 */
	private static function pagelayer_outline_blocks($blocks, &$out = array(), $path = '') {
		if (!is_array($blocks)) {
			return $out;
		}
		$index = 0;
		foreach ($blocks as $block) {
			$tag = self::pagelayer_block_tag($block);
			if ($tag === '') {
				continue;
			}
			if (count($out) >= 200) {
				break;
			}
			$here  = $path === '' ? (string) $index : $path . '.' . $index;
			$attrs = (is_array($block) && isset($block['attrs']) && is_array($block['attrs'])) ? $block['attrs'] : array();
			$id    = !empty($attrs['pagelayer-id']) ? (string) $attrs['pagelayer-id'] : '@' . $here;
			$row   = array(
				'id'   => $id,
				'type' => $tag,
				'path' => $here,
			);
			if ($tag === 'pl_col' && isset($attrs['col'])) {
				$row['col'] = (string) $attrs['col'];
			}
			$html = (is_array($block) && isset($block['innerHTML'])) ? (string) $block['innerHTML'] : '';
			$text = self::pagelayer_preview_text($attrs, $html);
			if ($text !== '') {
				$row['text'] = $text;
			}
			$out[] = $row;
			if (is_array($block) && !empty($block['innerBlocks']) && is_array($block['innerBlocks'])) {
				self::pagelayer_outline_blocks($block['innerBlocks'], $out, $here);
			}
			$index++;
		}
		return $out;
	}

	/**
	 * Outline a Pagelayer node tree.
	 *
	 * @param array<mixed>                    $nodes Nodes.
	 * @param array<int,array<string,string>> $out   Collected rows.
	 * @param string                          $path  Path prefix.
	 * @return array<int,array<string,string>>
	 */
	private static function pagelayer_outline_nodes($nodes, &$out = array(), $path = '') {
		if (!is_array($nodes)) {
			return $out;
		}
		$index = 0;
		foreach ($nodes as $node) {
			if (!is_array($node) || empty($node['tag']) || !is_string($node['tag'])) {
				continue;
			}
			if (count($out) >= 200) {
				break;
			}
			$here  = $path === '' ? (string) $index : $path . '.' . $index;
			$attrs = isset($node['attrs']) && is_array($node['attrs']) ? $node['attrs'] : array();
			$id    = !empty($attrs['pagelayer-id']) ? (string) $attrs['pagelayer-id'] : '@' . $here;
			$row   = array(
				'id'   => $id,
				'type' => $node['tag'],
				'path' => $here,
			);
			if ($node['tag'] === 'pl_col' && isset($attrs['col'])) {
				$row['col'] = (string) $attrs['col'];
			}
			$html = (isset($node['content']) && is_string($node['content'])) ? $node['content'] : '';
			$text = self::pagelayer_preview_text($attrs, $html);
			if ($text !== '') {
				$row['text'] = $text;
			}
			$out[] = $row;
			if (isset($node['content']) && is_array($node['content'])) {
				self::pagelayer_outline_nodes($node['content'], $out, $here);
			}
			$index++;
		}
		return $out;
	}

	/**
	 * Merge attributes into one parsed block.
	 *
	 * @param array<mixed>        $blocks     Parsed blocks.
	 * @param string              $element_id Element id or @path.
	 * @param array<string,mixed> $settings   Settings.
	 * @param bool                $found      Set when updated.
	 * @param string              $path       Path prefix.
	 * @return void
	 */
	private static function pagelayer_update_blocks(&$blocks, $element_id, $settings, &$found, $path = '') {
		if (!is_array($blocks) || $found) {
			return;
		}
		$index = 0;
		foreach ($blocks as &$block) {
			$tag = self::pagelayer_block_tag($block);
			if ($tag === '') {
				continue;
			}
			$here  = $path === '' ? (string) $index : $path . '.' . $index;
			$attrs = (isset($block['attrs']) && is_array($block['attrs'])) ? $block['attrs'] : array();
			$id    = isset($attrs['pagelayer-id']) ? (string) $attrs['pagelayer-id'] : '';
			if (self::pagelayer_id_matches($id, $here, $element_id)) {
				self::pagelayer_apply_block_settings($block, $settings);
				$found = true;
				return;
			}
			if (!empty($block['innerBlocks']) && is_array($block['innerBlocks'])) {
				self::pagelayer_update_blocks($block['innerBlocks'], $element_id, $settings, $found, $here);
				if ($found) {
					return;
				}
			}
			$index++;
		}
		unset($block);
	}

	/**
	 * Merge attributes into one node.
	 *
	 * @param array<mixed>        $nodes      Node tree.
	 * @param string              $element_id Element id or @path.
	 * @param array<string,mixed> $settings   Settings.
	 * @param bool                $found      Set when updated.
	 * @param string              $path       Path prefix.
	 * @return void
	 */
	private static function pagelayer_update_nodes(&$nodes, $element_id, $settings, &$found, $path = '') {
		if (!is_array($nodes) || $found) {
			return;
		}
		$index = 0;
		foreach ($nodes as &$node) {
			if (!is_array($node) || empty($node['tag']) || !is_string($node['tag'])) {
				continue;
			}
			$here  = $path === '' ? (string) $index : $path . '.' . $index;
			$attrs = isset($node['attrs']) && is_array($node['attrs']) ? $node['attrs'] : array();
			$id    = isset($attrs['pagelayer-id']) ? (string) $attrs['pagelayer-id'] : '';
			if (self::pagelayer_id_matches($id, $here, $element_id)) {
				self::pagelayer_apply_node_settings($node, $settings);
				$found = true;
				return;
			}
			if (isset($node['content']) && is_array($node['content'])) {
				self::pagelayer_update_nodes($node['content'], $element_id, $settings, $found, $here);
				if ($found) {
					return;
				}
			}
			$index++;
		}
		unset($node);
	}

	/**
	 * Write settings onto a parsed block.
	 *
	 * @param array<string,mixed> $block    Parsed block.
	 * @param array<string,mixed> $settings Settings.
	 * @return void
	 */
	private static function pagelayer_apply_block_settings(&$block, $settings) {
		$incoming = $settings;
		if (array_key_exists('content', $incoming) && is_string($incoming['content']) && empty($block['innerBlocks'])) {
			$block['innerHTML']    = $incoming['content'];
			$block['innerContent'] = array($incoming['content']);
			unset($incoming['content']);
		} else {
			unset($incoming['content']);
		}
		if (!isset($block['attrs']) || !is_array($block['attrs'])) {
			$block['attrs'] = array();
		}
		foreach ($incoming as $key => $value) {
			if (!is_string($key) || $key === '') {
				continue;
			}
			$block['attrs'][$key] = $value;
		}
	}

	/**
	 * Write settings onto a node.
	 *
	 * @param array<string,mixed> $node     Node.
	 * @param array<string,mixed> $settings Settings.
	 * @return void
	 */
	private static function pagelayer_apply_node_settings(&$node, $settings) {
		$incoming = $settings;
		if (array_key_exists('content', $incoming) && is_string($incoming['content']) && (!isset($node['content']) || is_string($node['content']))) {
			$node['content'] = $incoming['content'];
		}
		unset($incoming['content']);
		if (!isset($node['attrs']) || !is_array($node['attrs'])) {
			$node['attrs'] = array();
		}
		foreach ($incoming as $key => $value) {
			if (!is_string($key) || $key === '') {
				continue;
			}
			$node['attrs'][$key] = $value;
		}
	}

	/**
	 * Turn parsed blocks into the node tree stored by the abilities API.
	 *
	 * @param array<mixed> $blocks Parsed blocks.
	 * @return array<int,array<string,mixed>>
	 */
	private static function pagelayer_blocks_to_nodes($blocks) {
		$nodes = array();
		if (!is_array($blocks)) {
			return $nodes;
		}
		foreach ($blocks as $block) {
			$tag = self::pagelayer_block_tag($block);
			if ($tag === '') {
				continue;
			}
			$node = array(
				'tag'   => $tag,
				'attrs' => (isset($block['attrs']) && is_array($block['attrs'])) ? $block['attrs'] : array(),
			);
			if (!empty($block['innerBlocks']) && is_array($block['innerBlocks'])) {
				$children = self::pagelayer_blocks_to_nodes($block['innerBlocks']);
				if ($children !== array()) {
					$node['content'] = $children;
				}
			}
			if (!isset($node['content'])) {
				$html = isset($block['innerHTML']) ? (string) $block['innerHTML'] : '';
				if (trim($html) !== '') {
					$node['content'] = $html;
				}
			}
			$nodes[] = $node;
		}
		return $nodes;
	}

	/**
	 * Serialize a node tree to Pagelayer block comments.
	 *
	 * @param array<mixed> $nodes Nodes.
	 * @return string
	 */
	private static function pagelayer_serialize_nodes($nodes) {
		if (class_exists('Pagelayer_Abilities_Register') && method_exists('Pagelayer_Abilities_Register', 'serialize_layout_to_blocks')) {
			return (string) \Pagelayer_Abilities_Register::serialize_layout_to_blocks($nodes);
		}
		return self::pagelayer_serialize_nodes_fallback($nodes);
	}

	/**
	 * Block markup used when Pagelayer's own serializer is not loaded.
	 *
	 * @param array<mixed> $nodes Nodes.
	 * @return string
	 */
	private static function pagelayer_serialize_nodes_fallback($nodes) {
		if (!is_array($nodes)) {
			return '';
		}
		$prefix = defined('PAGELAYER_BLOCK_PREFIX') ? PAGELAYER_BLOCK_PREFIX : 'wp';
		$out    = '';
		foreach ($nodes as $node) {
			if (!is_array($node) || empty($node['tag']) || !is_string($node['tag'])) {
				continue;
			}
			$tag = $node['tag'];
			if (strpos($tag, 'pl_') !== 0) {
				$tag = 'pl_' . $tag;
			}
			$attrs = isset($node['attrs']) && is_array($node['attrs']) ? $node['attrs'] : array();
			$name  = $prefix . ':pagelayer/' . $tag;
			$json  = $attrs === array() ? '' : ' ' . wp_json_encode($attrs);
			$inner = '';
			if (isset($node['content']) && is_array($node['content'])) {
				$inner = self::pagelayer_serialize_nodes_fallback($node['content']);
			} elseif (isset($node['content']) && is_string($node['content'])) {
				$inner = $node['content'];
			}
			if ($inner === '') {
				$out .= '<!-- ' . $name . $json . " /-->\n";
			} else {
				$out .= '<!-- ' . $name . $json . ' -->' . $inner . '<!-- /' . $name . " -->\n";
			}
		}
		return $out;
	}
}
