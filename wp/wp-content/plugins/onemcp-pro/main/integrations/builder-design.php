<?php
/**
 * Create any post type and any theme template with the active page builder.
 *
 * On SitePad the only editor is Pagelayer, so these abilities build with its widgets.
 *
 * @package OneMCPPro
 */

namespace OneMCPPro;

use ONEMCP\Detector;
use ONEMCP\Helper;

if (!defined('ABSPATH')) {
	exit;
}

trait BuilderDesign {

	/**
	 * True when insert_template_post() created a new post in this request.
	 *
	 * An existing header or footer must not be deleted if saving the new design fails.
	 *
	 * @var bool
	 */
	protected static $created_template = false;

	/**
	 * Abilities that create documents and theme templates for one builder.
	 *
	 * @param string $slug  Builder slug.
	 * @param string $label Builder name.
	 * @return array<int,array<string,mixed>>
	 */
	protected static function design_abilities($slug, $label) {
		$prefix   = self::design_slug($slug);
		$category = self::design_category();
		$editor   = self::editor_label($slug, $label);
		$widget   = self::design_slug($slug) === 'editor'
			? __('SitePad builds every page, post, and theme template with the visual editor. Call list-editor-widgets, then get-editor-widget for each widget you place. There is no other editor.', 'onemcp-pro')
			: sprintf(
				/* translators: %s: builder name. */
				__('Use list-%1$s-widgets, then get-%1$s-widget, and pass those widgets in elements.', 'onemcp-pro'),
				$prefix
			);

		return array(
			Helper::ability(
				'list-' . $prefix . '-post-types',
				sprintf(
					/* translators: %s: editor name. */
					__('List %s post types', 'onemcp-pro'),
					$editor
				),
				sprintf(
					/* translators: %s: tool prefix. */
					__('Lists pages, posts, and every other public post type. Create one with create-%s-document.', 'onemcp-pro'),
					$prefix
				),
				$category,
				true,
				function () use ($slug) {
					return self::list_post_types($slug);
				},
				array(),
				array(),
				self::design_extra(true)
			),
			Helper::ability(
				'list-' . $prefix . '-template-types',
				sprintf(
					/* translators: %s: editor name. */
					__('List %s theme template types', 'onemcp-pro'),
					$editor
				),
				sprintf(
					/* translators: %s: tool prefix. */
					__('Lists header, footer, single, archive, and every other theme template, including a target for each public post type and taxonomy. Create one with create-%s-template.', 'onemcp-pro'),
					$prefix
				),
				$category,
				true,
				function () use ($slug) {
					return self::list_template_types($slug);
				},
				array(),
				array(),
				self::design_extra(true)
			),
			Helper::ability(
				'list-' . $prefix . '-widgets',
				sprintf(
					/* translators: %s: editor name. */
					__('List %s widgets', 'onemcp-pro'),
					$editor
				),
				$widget,
				$category,
				true,
				function ($input) use ($slug) {
					return self::list_widgets($slug, is_array($input) ? $input : array());
				},
				array(
					'search' => Helper::string_prop(__('Filter widgets by name or tag.', 'onemcp-pro'), 80),
				),
				array(),
				self::design_extra(true)
			),
			Helper::ability(
				'get-' . $prefix . '-widget',
				sprintf(
					/* translators: %s: editor name. */
					__('Get %s widget', 'onemcp-pro'),
					$editor
				),
				sprintf(
					/* translators: %s: editor name. */
					__('Returns the attributes for one %s widget so a layout can use that widget correctly.', 'onemcp-pro'),
					$editor
				),
				$category,
				true,
				function ($input) use ($slug) {
					return self::get_widget($slug, is_array($input) ? $input : array());
				},
				array(
					'widget' => Helper::string_prop(__('Widget tag from the widget list, such as pl_heading.', 'onemcp-pro'), 80),
				),
				array('widget'),
				self::design_extra(true)
			),
			Helper::ability(
				'create-' . $prefix . '-document',
				sprintf(
					/* translators: %s: editor name. */
					__('Create %s document', 'onemcp-pro'),
					$editor
				),
				sprintf(
					/* translators: 1: editor name, 2: tool prefix. */
					__('Creates a document of any post type and opens it in %1$s. Pass elements built from %2$s widgets. New documents are drafts unless a status is given.', 'onemcp-pro'),
					$editor,
					$prefix
				),
				$category,
				false,
				function ($input) use ($slug) {
					return self::create_document($slug, is_array($input) ? $input : array());
				},
				array(
					'post_type' => Helper::string_prop(__('Any public post type. Defaults to page.', 'onemcp-pro'), 40),
					'title'     => Helper::string_prop(__('Title.', 'onemcp-pro'), 200),
					'status'    => Helper::string_prop(__('draft, pending, private, future, or publish.', 'onemcp-pro'), 20),
					'excerpt'   => Helper::string_prop(__('Optional excerpt.', 'onemcp-pro'), 1000),
					'elements'  => self::elements_prop(__('Widget tree for this builder. Pagelayer nodes are {tag, attrs, content}.', 'onemcp-pro')),
					'content'   => Helper::string_prop(__('Divi or other shortcode markup, when the builder stores content as shortcodes.', 'onemcp-pro'), 200000),
				),
				array('title'),
				self::design_extra(false)
			),
			Helper::ability(
				'create-' . $prefix . '-template',
				sprintf(
					/* translators: %s: editor name. */
					__('Create %s theme template', 'onemcp-pro'),
					$editor
				),
				sprintf(
					/* translators: 1: editor name, 2: tool prefix. */
					__('Creates any %1$s theme template. Set type to a value from list-%2$s-template-types. For a single or archive template set sub_template to the post type or taxonomy. Build the layout with %2$s widgets.', 'onemcp-pro'),
					$editor,
					$prefix
				),
				$category,
				false,
				function ($input) use ($slug) {
					return self::create_template($slug, is_array($input) ? $input : array());
				},
				array(
					'title'        => Helper::string_prop(__('Template title.', 'onemcp-pro'), 200),
					'type'         => Helper::string_prop(__('Template type, such as header, footer, single, or archive.', 'onemcp-pro'), 60),
					'sub_template' => Helper::string_prop(__('Post type or taxonomy this single or archive template applies to. Empty means all.', 'onemcp-pro'), 60),
					'elements'     => self::elements_prop(__('Widget tree for this template.', 'onemcp-pro')),
					'content'      => Helper::string_prop(__('Shortcode markup for builders that store templates as shortcodes.', 'onemcp-pro'), 200000),
					'conditions'   => array(
						'type'        => 'array',
						'description' => __('Optional display rules. Pagelayer rules are {type, template, sub_template, id}.', 'onemcp-pro'),
						'items'       => array(
							'type'                 => 'object',
							'additionalProperties' => true,
						),
					),
				),
				array('title', 'type'),
				self::design_extra(false)
			),
		);
	}

	/**
	 * Category these abilities are listed under.
	 *
	 * SitePad shows them with SitePad Core so they are on until an administrator turns them off.
	 *
	 * @return string
	 */
	protected static function design_category() {
		return Detector::is_sitepad() ? 'onemcp-wordpress' : 'onemcp-builders';
	}

	/**
	 * Tool-name prefix. SitePad has one editor, so the name does not say Pagelayer.
	 *
	 * @param string $slug Builder slug.
	 * @return string
	 */
	protected static function design_slug($slug) {
		if (Detector::is_sitepad()) {
			return 'editor';
		}
		return $slug;
	}

	/**
	 * Name used in labels.
	 *
	 * @param string $slug  Builder slug.
	 * @param string $label Builder name.
	 * @return string
	 */
	protected static function editor_label($slug, $label) {
		if (Detector::is_sitepad()) {
			return __('visual editor', 'onemcp-pro');
		}
		return $label;
	}

	/**
	 * Copy for the outline and update tools.
	 *
	 * @param string $slug      Builder slug.
	 * @param string $label     Builder name.
	 * @param string $structure Structure description.
	 * @param string $update    Update description.
	 * @return array{0:string,1:string,2:string}
	 */
	protected static function editor_copy($slug, $label, $structure, $update) {
		if (!Detector::is_sitepad()) {
			return array($label, $structure, $update);
		}
		return array(
			__('visual editor', 'onemcp-pro'),
			__('Returns an outline of the widgets on one document. Each id is the element id, or an @0.1 path. Create any post type with create-editor-document and any theme template with create-theme-template. Place widgets from list-editor-widgets.', 'onemcp-pro'),
			__('Merges attributes into one widget. Use the id from the structure outline. A content value replaces that widget\'s inner HTML when it has no child widgets.', 'onemcp-pro'),
		);
	}

	/**
	 * Permission and default switch for a design ability.
	 *
	 * @param bool $readonly True for a read ability.
	 * @return array<string,mixed>
	 */
	protected static function design_extra($readonly) {
		$extra = array(
			'permission' => array('\\ONEMCP\\WordPress', 'can_edit'),
		);
		if (Detector::is_sitepad()) {
			$extra['default_on'] = true;
		}
		return $extra;
	}

	/**
	 * Schema for a widget tree.
	 *
	 * @param string $description Description.
	 * @return array<string,mixed>
	 */
	protected static function elements_prop($description) {
		return array(
			'type'        => 'array',
			'description' => $description,
			'items'       => array(
				'type'                 => 'object',
				'additionalProperties' => true,
			),
		);
	}

	/**
	 * Post types this builder can be turned on for.
	 *
	 * @param string $slug Builder slug.
	 * @return array<string,mixed>
	 */
	public static function list_post_types($slug) {
		$items = array();
		foreach (self::content_post_types() as $type) {
			$items[] = array(
				'name'  => $type['name'],
				'label' => $type['label'],
			);
		}
		return array(
			'builder'    => $slug,
			'post_types' => $items,
			'note'       => Detector::is_sitepad()
				? __('Create any of these with create-editor-document. Build the layout from list-editor-widgets. Theme templates are a separate list.', 'onemcp-pro')
				: __('Create any of these with the create document ability. Theme templates are listed separately.', 'onemcp-pro'),
		);
	}

	/**
	 * Theme template types, including one single and archive target per public post type.
	 *
	 * @param string $slug Builder slug.
	 * @return array<string,mixed>
	 */
	public static function list_template_types($slug) {
		$types = array();
		foreach (self::template_catalog($slug) as $row) {
			if (!empty($row['post_type']) && !post_type_exists($row['post_type'])) {
				continue;
			}
			$types[] = $row;
		}
		return array(
			'builder' => $slug,
			'types'   => $types,
			'note'    => __('Use type for the template kind. For single or archive, set sub_template to a post type or taxonomy from that type. Empty sub_template applies to all of that kind.', 'onemcp-pro'),
		);
	}

	/**
	 * Widgets the builder can place.
	 *
	 * @param string              $slug  Builder slug.
	 * @param array<string,mixed> $input Input.
	 * @return array<string,mixed>
	 */
	public static function list_widgets($slug, $input) {
		$search = Helper::text(isset($input['search']) ? $input['search'] : '', 80);
		$items  = self::widget_index($slug);
		if ($search !== '') {
			$needle = strtolower($search);
			$items  = array_values(array_filter($items, function ($item) use ($needle) {
				$hay = strtolower($item['tag'] . ' ' . $item['name'] . ' ' . $item['group']);
				return strpos($hay, $needle) !== false;
			}));
		}
		return array(
			'builder' => $slug,
			'count'   => count($items),
			'widgets' => $items,
			'node'    => self::widget_node_hint($slug),
		);
	}

	/**
	 * One widget's attributes.
	 *
	 * @param string              $slug  Builder slug.
	 * @param array<string,mixed> $input Input.
	 * @return array<string,mixed>|\WP_Error
	 */
	public static function get_widget($slug, $input) {
		$widget = Helper::text(isset($input['widget']) ? $input['widget'] : '', 80);
		if ($widget === '') {
			return Helper::error('onemcp_invalid', __('Provide a widget tag from the widget list.', 'onemcp-pro'));
		}
		$found = self::widget_detail($slug, $widget);
		if (is_wp_error($found)) {
			return $found;
		}
		$found['builder'] = $slug;
		return $found;
	}

	/**
	 * Create a builder document of any supported post type.
	 *
	 * @param string              $slug  Builder slug.
	 * @param array<string,mixed> $input Input.
	 * @return array<string,mixed>|\WP_Error
	 */
	public static function create_document($slug, $input) {
		$title = Helper::text(isset($input['title']) ? $input['title'] : '', 200);
		if ($title === '') {
			return Helper::error('onemcp_invalid', __('A title is required.', 'onemcp-pro'));
		}
		$type = Helper::post_type(isset($input['post_type']) ? $input['post_type'] : 'page');
		if (is_wp_error($type)) {
			return $type;
		}
		if (in_array($type, self::template_storage_types(), true)) {
			return Helper::error('onemcp_invalid_type', __('That type is a theme template. Use the create template ability and a type from the template list.', 'onemcp-pro'));
		}
		$known = wp_list_pluck(self::content_post_types(), 'name');
		if (!in_array($type, $known, true)) {
			return Helper::error('onemcp_invalid_type', __('That post type cannot be edited with this builder.', 'onemcp-pro'));
		}
		if (!Helper::user_can_create($type)) {
			return Helper::error('onemcp_forbidden', __('You cannot create this content type.', 'onemcp-pro'));
		}

		$status = Helper::post_status(isset($input['status']) ? $input['status'] : 'draft', $type, 'draft');
		$postarr = array(
			'post_title'  => $title,
			'post_type'   => $type,
			'post_status' => $status,
		);
		if (isset($input['excerpt'])) {
			$postarr['post_excerpt'] = Helper::text($input['excerpt'], 1000);
		}
		$post_id = wp_insert_post(wp_slash($postarr), true);
		if (is_wp_error($post_id)) {
			return $post_id;
		}

		$stored = self::store_builder($slug, (int) $post_id, $type, $input, '');
		if (is_wp_error($stored)) {
			wp_delete_post((int) $post_id, true);
			return $stored;
		}
		self::enable_post_type($slug, $type);

		$post = get_post((int) $post_id);
		$row  = $post instanceof \WP_Post ? Helper::post_row($post) : array('id' => (int) $post_id);
		$row['builder'] = $slug;
		$row['edit_url'] = get_edit_post_link((int) $post_id, 'raw');
		return $row;
	}

	/**
	 * Create a theme template of any type the builder supports.
	 *
	 * @param string              $slug  Builder slug.
	 * @param array<string,mixed> $input Input.
	 * @return array<string,mixed>|\WP_Error
	 */
	public static function create_template($slug, $input) {
		$title = Helper::text(isset($input['title']) ? $input['title'] : '', 200);
		$type  = sanitize_key(isset($input['type']) ? (string) $input['type'] : '');
		if ($title === '' || $type === '') {
			return Helper::error('onemcp_invalid', __('A title and a template type are required.', 'onemcp-pro'));
		}
		$catalog = self::template_catalog($slug);
		$match   = null;
		foreach ($catalog as $row) {
			if ($row['type'] === $type) {
				$match = $row;
				break;
			}
		}
		if ($match === null) {
			return Helper::error('onemcp_invalid_type', __('That template type is not available. Read the template type list and use one of those types.', 'onemcp-pro'));
		}

		$sub = sanitize_key(isset($input['sub_template']) ? (string) $input['sub_template'] : '');
		if ($sub !== '' && !empty($match['sub_templates'])) {
			$allowed = wp_list_pluck($match['sub_templates'], 'name');
			if (!in_array($sub, $allowed, true)) {
				return Helper::error('onemcp_invalid_type', __('That sub_template is not valid for this template type.', 'onemcp-pro'));
			}
		}
		if ($sub !== '' && empty($match['sub_templates'])) {
			$sub = '';
		}

		$post_type = isset($match['post_type']) ? (string) $match['post_type'] : '';
		if ($post_type === '' || !post_type_exists($post_type)) {
			return Helper::error('onemcp_invalid_type', __('This builder has not registered that template yet.', 'onemcp-pro'));
		}

		$post_id = self::insert_template_post($slug, $post_type, $title, $type);
		if (is_wp_error($post_id)) {
			return $post_id;
		}

		$stored = self::store_builder($slug, (int) $post_id, $type, $input, $sub);
		if (is_wp_error($stored)) {
			if (self::$created_template) {
				wp_delete_post((int) $post_id, true);
			}
			return $stored;
		}

		return array(
			'builder'      => $slug,
			'id'           => (int) $post_id,
			'type'         => $type,
			'sub_template' => $sub,
			'title'        => $title,
			'edit_url'     => get_edit_post_link((int) $post_id, 'raw'),
		);
	}

	/**
	 * Public post types a builder document can use.
	 *
	 * @return array<int,array{name:string,label:string,has_archive:bool}>
	 */
	protected static function content_post_types() {
		$skip = array_merge(self::template_storage_types(), array(
			'attachment',
			'revision',
			'nav_menu_item',
			'custom_css',
			'customize_changeset',
			'oembed_cache',
			'user_request',
			'wp_global_styles',
			'wp_navigation',
			'wp_template',
			'wp_template_part',
			'wp_font_family',
			'wp_font_face',
			'wp_block',
			'wp_pattern',
		));
		$out = array();
		foreach (get_post_types(array(), 'objects') as $name => $object) {
			if (!is_object($object) || in_array($name, $skip, true)) {
				continue;
			}
			if (!empty($object->_builtin) && $name !== 'post' && $name !== 'page') {
				continue;
			}
			if (empty($object->show_ui) && empty($object->public)) {
				continue;
			}
			$out[] = array(
				'name'        => (string) $name,
				'label'       => isset($object->labels->singular_name) ? (string) $object->labels->singular_name : (string) $name,
				'has_archive' => !empty($object->has_archive) || $name === 'post',
			);
		}
		return $out;
	}

	/**
	 * Post types that store theme templates rather than site content.
	 *
	 * @return array<int,string>
	 */
	protected static function template_storage_types() {
		return array(
			'pagelayer-template',
			'elementor_library',
			'bricks_template',
			'fl-builder-template',
			'fl-theme-layout',
			'seedprod',
			'et_template',
			'et_header_layout',
			'et_body_layout',
			'et_footer_layout',
			'et_pb_layout',
			'breakdance_template',
			'breakdance_header',
			'breakdance_footer',
			'breakdance_block',
			'breakdance_popup',
			'breakdance_acf_block',
		);
	}

	/**
	 * Template types for the active builder.
	 *
	 * @param string $slug Builder slug.
	 * @return array<int,array<string,mixed>>
	 */
	protected static function template_catalog($slug) {
		if ($slug === 'pagelayer') {
			return self::pagelayer_template_catalog();
		}
		if ($slug === 'elementor') {
			return self::elementor_template_catalog();
		}
		if ($slug === 'bricks') {
			return self::labeled_catalog('bricks_template', array(
				'header'  => __('Header', 'onemcp-pro'),
				'footer'  => __('Footer', 'onemcp-pro'),
				'content' => __('Single', 'onemcp-pro'),
				'section' => __('Section', 'onemcp-pro'),
				'archive' => __('Archive', 'onemcp-pro'),
				'search'  => __('Search', 'onemcp-pro'),
				'error'   => __('404', 'onemcp-pro'),
				'popup'   => __('Popup', 'onemcp-pro'),
			), array('content', 'archive'));
		}
		if ($slug === 'beaver') {
			if (post_type_exists('fl-theme-layout')) {
				return self::labeled_catalog('fl-theme-layout', array(
					'header'   => __('Header', 'onemcp-pro'),
					'footer'   => __('Footer', 'onemcp-pro'),
					'singular' => __('Single', 'onemcp-pro'),
					'archive'  => __('Archive', 'onemcp-pro'),
					'404'      => __('404', 'onemcp-pro'),
					'part'     => __('Part', 'onemcp-pro'),
				), array('singular', 'archive'));
			}
			return self::labeled_catalog('fl-builder-template', array(
				'layout' => __('Layout', 'onemcp-pro'),
				'row'    => __('Saved row', 'onemcp-pro'),
				'module' => __('Saved module', 'onemcp-pro'),
			), array());
		}
		if ($slug === 'divi') {
			return self::divi_template_catalog();
		}
		if ($slug === 'breakdance') {
			return self::breakdance_template_catalog();
		}
		if ($slug === 'seedprod') {
			return self::labeled_catalog('seedprod', array(
				'page'  => __('Landing page', 'onemcp-pro'),
				'cs'    => __('Coming soon', 'onemcp-pro'),
				'mm'    => __('Maintenance', 'onemcp-pro'),
				'login' => __('Login', 'onemcp-pro'),
				'404'   => __('404', 'onemcp-pro'),
			), array());
		}
		return array();
	}

	/**
	 * Pagelayer template types, plus a single and archive target for every public type.
	 *
	 * @return array<int,array<string,mixed>>
	 */
	protected static function pagelayer_template_catalog() {
		$labels = array(
			'header'         => __('Header', 'onemcp-pro'),
			'footer'         => __('Footer', 'onemcp-pro'),
			'single'         => __('Single', 'onemcp-pro'),
			'archive'        => __('Archive', 'onemcp-pro'),
			'popup'          => __('Popup', 'onemcp-pro'),
			'section'        => __('Section', 'onemcp-pro'),
			'global_section' => __('Global section', 'onemcp-pro'),
			'global_widget'  => __('Global widget', 'onemcp-pro'),
		);
		global $pagelayer;
		if (isset($pagelayer->builder['type']) && is_array($pagelayer->builder['type'])) {
			foreach ($pagelayer->builder['type'] as $key => $value) {
				$key = sanitize_key((string) $key);
				if ($key === '') {
					continue;
				}
				if (!isset($labels[$key])) {
					$labels[$key] = is_array($value) && isset($value['label']) ? (string) $value['label'] : (string) $value;
				}
			}
		}
		$rows = array();
		foreach ($labels as $type => $label) {
			$row = array(
				'type'      => $type,
				'label'     => $label,
				'post_type' => 'pagelayer-template',
			);
			if ($type === 'single') {
				$row['sub_templates'] = self::target_choices(false);
			} elseif ($type === 'archive') {
				$row['sub_templates'] = self::target_choices(true);
			}
			$rows[] = $row;
		}
		return $rows;
	}

	/**
	 * Elementor library types, with single and archive targets for every public type.
	 *
	 * @return array<int,array<string,mixed>>
	 */
	protected static function elementor_template_catalog() {
		$labels = array(
			'header'         => __('Header', 'onemcp-pro'),
			'footer'         => __('Footer', 'onemcp-pro'),
			'single'         => __('Single', 'onemcp-pro'),
			'single-post'    => __('Single post', 'onemcp-pro'),
			'single-page'    => __('Single page', 'onemcp-pro'),
			'archive'        => __('Archive', 'onemcp-pro'),
			'search-results' => __('Search results', 'onemcp-pro'),
			'error-404'      => __('404', 'onemcp-pro'),
			'section'        => __('Section', 'onemcp-pro'),
			'container'      => __('Container', 'onemcp-pro'),
			'page'           => __('Page', 'onemcp-pro'),
			'loop-item'      => __('Loop item', 'onemcp-pro'),
			'popup'          => __('Popup', 'onemcp-pro'),
		);
		if (class_exists('\Elementor\TemplateLibrary\Source_Local')) {
			$live = \Elementor\TemplateLibrary\Source_Local::get_template_types();
			if (is_array($live)) {
				foreach ($live as $key => $label) {
					$name = sanitize_key(is_string($key) ? $key : (string) $label);
					if ($name !== '' && !isset($labels[$name])) {
						$labels[$name] = is_string($label) ? $label : $name;
					}
				}
			}
		}
		return self::labeled_catalog('elementor_library', $labels, array('single', 'single-post', 'single-page', 'archive', 'loop-item'));
	}

	/**
	 * Divi theme-builder post types that are actually registered.
	 *
	 * @return array<int,array<string,mixed>>
	 */
	protected static function divi_template_catalog() {
		$map = array(
			'header' => array('et_header_layout', __('Header', 'onemcp-pro')),
			'footer' => array('et_footer_layout', __('Footer', 'onemcp-pro')),
			'body'   => array('et_body_layout', __('Body', 'onemcp-pro')),
		);
		$rows = array();
		foreach ($map as $type => $pair) {
			if (!post_type_exists($pair[0])) {
				continue;
			}
			$rows[] = array(
				'type'      => $type,
				'label'     => $pair[1],
				'post_type' => $pair[0],
			);
		}
		if (post_type_exists('et_template')) {
			$rows[] = array(
				'type'           => 'single',
				'label'          => __('Single', 'onemcp-pro'),
				'post_type'      => 'et_template',
				'sub_templates'  => self::target_choices(false),
			);
			$rows[] = array(
				'type'           => 'archive',
				'label'          => __('Archive', 'onemcp-pro'),
				'post_type'      => 'et_template',
				'sub_templates'  => self::target_choices(true),
			);
		}
		if ($rows === array() && post_type_exists('et_pb_layout')) {
			$rows[] = array(
				'type'      => 'layout',
				'label'     => __('Saved layout', 'onemcp-pro'),
				'post_type' => 'et_pb_layout',
			);
		}
		return $rows;
	}

	/**
	 * Breakdance template post types that are registered.
	 *
	 * @return array<int,array<string,mixed>>
	 */
	protected static function breakdance_template_catalog() {
		$map = array(
			'header'    => 'breakdance_header',
			'footer'    => 'breakdance_footer',
			'block'     => 'breakdance_block',
			'popup'     => 'breakdance_popup',
			'acf_block' => 'breakdance_acf_block',
		);
		$rows = array();
		foreach ($map as $type => $post_type) {
			if (!post_type_exists($post_type)) {
				continue;
			}
			$rows[] = array(
				'type'      => $type,
				'label'     => $type,
				'post_type' => $post_type,
			);
		}
		if (post_type_exists('breakdance_template')) {
			foreach (array('single' => __('Single', 'onemcp-pro'), 'archive' => __('Archive', 'onemcp-pro'), 'search' => __('Search', 'onemcp-pro'), '404' => __('404', 'onemcp-pro')) as $type => $label) {
				$row = array(
					'type'      => $type,
					'label'     => $label,
					'post_type' => 'breakdance_template',
				);
				if ($type === 'single') {
					$row['sub_templates'] = self::target_choices(false);
				} elseif ($type === 'archive') {
					$row['sub_templates'] = self::target_choices(true);
				}
				$rows[] = $row;
			}
		}
		return $rows;
	}

	/**
	 * Build catalog rows that share one storage post type.
	 *
	 * @param string              $post_type Storage post type.
	 * @param array<string,string> $labels   Type to label.
	 * @param array<int,string>   $targeted  Types that accept sub_template.
	 * @return array<int,array<string,mixed>>
	 */
	protected static function labeled_catalog($post_type, $labels, $targeted) {
		$rows = array();
		foreach ($labels as $type => $label) {
			$row = array(
				'type'      => (string) $type,
				'label'     => (string) $label,
				'post_type' => $post_type,
			);
			if (in_array($type, $targeted, true)) {
				$row['sub_templates'] = self::target_choices($type === 'archive');
			}
			$rows[] = $row;
		}
		return $rows;
	}

	/**
	 * Post types and taxonomies a single or archive template can target.
	 *
	 * @param bool $archives True for archive targets.
	 * @return array<int,array<string,string>>
	 */
	protected static function target_choices($archives) {
		$out = array();
		foreach (self::content_post_types() as $type) {
			if ($archives && empty($type['has_archive'])) {
				continue;
			}
			$out[] = array(
				'name'  => $type['name'],
				'label' => $type['label'],
				'kind'  => 'post_type',
			);
		}
		foreach (get_taxonomies(array('public' => true), 'objects') as $name => $tax) {
			if (!is_object($tax) || in_array($name, array('nav_menu', 'link_category', 'post_format'), true)) {
				continue;
			}
			$out[] = array(
				'name'  => (string) $name,
				'label' => isset($tax->labels->singular_name) ? (string) $tax->labels->singular_name : (string) $name,
				'kind'  => 'taxonomy',
			);
		}
		return $out;
	}

	/**
	 * Insert the template post, reusing a Pagelayer header or footer when one already exists.
	 *
	 * @param string $slug      Builder slug.
	 * @param string $post_type Template post type.
	 * @param string $title     Title.
	 * @param string $type      Template type.
	 * @return int|\WP_Error
	 */
	protected static function insert_template_post($slug, $post_type, $title, $type) {
		self::$created_template = false;
		if ($slug === 'pagelayer' && in_array($type, array('header', 'footer'), true)) {
			$existing = get_posts(array(
				'post_type'      => 'pagelayer-template',
				'post_status'    => array('publish', 'draft', 'private'),
				'posts_per_page' => 1,
				'meta_key'       => 'pagelayer_template_type',
				'meta_value'     => $type,
				'fields'         => 'ids',
			));
			if (!empty($existing)) {
				$existing_id = (int) $existing[0];
				if (!current_user_can('edit_post', $existing_id)) {
					return Helper::error('onemcp_forbidden', __('You cannot change the current header or footer.', 'onemcp-pro'));
				}
				$updated = wp_update_post(wp_slash(array(
					'ID'         => $existing_id,
					'post_title' => $title,
				)), true);
				return is_wp_error($updated) ? $updated : $existing_id;
			}
		}

		if (!Helper::user_can_create($post_type)) {
			return Helper::error('onemcp_forbidden', __('You cannot create this template.', 'onemcp-pro'));
		}
		$object     = get_post_type_object($post_type);
		$create_cap = ($object && !empty($object->cap->create_posts)) ? $object->cap->create_posts : '';
		$weak_caps  = array('edit_posts', 'edit_pages', 'publish_posts', 'publish_pages');
		if (in_array($create_cap, $weak_caps, true) && !current_user_can('edit_theme_options')) {
			return Helper::error('onemcp_forbidden', __('Theme templates require permission to edit the theme.', 'onemcp-pro'));
		}

		$created = wp_insert_post(wp_slash(array(
			'post_title'  => $title,
			'post_type'   => $post_type,
			'post_status' => Helper::post_status('publish', $post_type, 'draft'),
		)), true);
		if (is_wp_error($created)) {
			return $created;
		}
		self::$created_template = true;
		return (int) $created;
	}

	/**
	 * Write builder data and the template rules for one new document.
	 *
	 * @param string              $slug  Builder slug.
	 * @param int                 $post_id Post ID.
	 * @param string              $type  Post type or template type.
	 * @param array<string,mixed> $input Input.
	 * @param string              $sub   Template target.
	 * @return true|\WP_Error
	 */
	protected static function store_builder($slug, $post_id, $type, $input, $sub) {
		$elements = isset($input['elements']) && is_array($input['elements']) ? Helper::clean_settings($input['elements']) : array();
		if (!is_array($elements)) {
			$elements = array();
		}
		$content = isset($input['content']) ? (string) Helper::clean_settings($input['content']) : '';
		if (!is_string($content)) {
			$content = '';
		}

		if ($slug === 'pagelayer') {
			return self::store_pagelayer($post_id, $type, $elements, $input, $sub);
		}
		if ($slug === 'elementor') {
			return self::store_elementor($post_id, $type, $elements, $sub);
		}
		if ($slug === 'bricks') {
			return self::store_bricks($post_id, $type, $elements, $sub);
		}
		if ($slug === 'beaver') {
			return self::store_beaver($post_id, $type, $elements, $sub);
		}
		if ($slug === 'divi') {
			return self::store_divi($post_id, $type, $elements, $content, $sub);
		}
		if ($slug === 'breakdance') {
			return self::store_breakdance($post_id, $type, $elements, $sub);
		}
		if ($slug === 'seedprod') {
			return self::store_seedprod($post_id, $type, $elements);
		}
		return Helper::error('onemcp_invalid', __('That builder is not supported.', 'onemcp-pro'));
	}

	/**
	 * Save a Pagelayer document or theme template.
	 *
	 * @param int                 $post_id  Post ID.
	 * @param string              $type     Post type or template type.
	 * @param array<mixed>        $elements Widget nodes.
	 * @param array<string,mixed> $input    Input.
	 * @param string              $sub      Template target.
	 * @return true|\WP_Error
	 */
	protected static function store_pagelayer($post_id, $type, $elements, $input, $sub) {
		if ($elements === array()) {
			$elements = array(
				array(
					'tag'     => 'pl_row',
					'attrs'   => array(),
					'content' => array(
						array(
							'tag'     => 'pl_col',
							'attrs'   => array('col' => '12'),
							'content' => array(),
						),
					),
				),
			);
		}
		$nodes = self::pagelayer_ensure_ids($elements);
		if (class_exists('Pagelayer_Abilities_Register') && method_exists('Pagelayer_Abilities_Register', 'normalize_layout_data')) {
			$normalized = \Pagelayer_Abilities_Register::normalize_layout_data($nodes);
			if (is_array($normalized)) {
				$nodes = $normalized;
			}
		}
		update_post_meta($post_id, 'pagelayer-data', $nodes);
		$markup = self::pagelayer_serialize_nodes($nodes);
		$result = wp_update_post(wp_slash(array(
			'ID'           => $post_id,
			'post_content' => $markup,
		)), true);
		if (is_wp_error($result)) {
			return $result;
		}

		if (get_post_type($post_id) !== 'pagelayer-template') {
			return true;
		}

		update_post_meta($post_id, 'pagelayer_template_type', $type);
		$conditions = self::pagelayer_conditions($type, $sub, isset($input['conditions']) ? $input['conditions'] : array());
		update_post_meta($post_id, 'pagelayer_template_conditions', $conditions);
		return true;
	}

	/**
	 * Give every Pagelayer node an id so later edits can find it.
	 *
	 * @param array<mixed> $nodes Nodes.
	 * @return array<mixed>
	 */
	protected static function pagelayer_ensure_ids($nodes) {
		if (!is_array($nodes)) {
			return array();
		}
		foreach ($nodes as $key => $node) {
			if (!is_array($node)) {
				continue;
			}
			if (!isset($node['attrs']) || !is_array($node['attrs'])) {
				$node['attrs'] = array();
			}
			if (empty($node['attrs']['pagelayer-id'])) {
				$node['attrs']['pagelayer-id'] = function_exists('pagelayer_create_id') ? pagelayer_create_id() : substr(md5(uniqid('', true)), 0, 8);
			}
			if (isset($node['content']) && is_array($node['content'])) {
				$node['content'] = self::pagelayer_ensure_ids($node['content']);
			}
			$nodes[$key] = $node;
		}
		return $nodes;
	}

	/**
	 * Display conditions for a Pagelayer theme template.
	 *
	 * @param string $type       Template type.
	 * @param string $sub        Post type or taxonomy.
	 * @param mixed  $requested  Caller conditions.
	 * @return array<int,array<string,string>>
	 */
	protected static function pagelayer_conditions($type, $sub, $requested) {
		$conditions = array();
		if (is_array($requested)) {
			foreach ($requested as $rule) {
				if (!is_array($rule)) {
					continue;
				}
				$action = isset($rule['type']) ? sanitize_key((string) $rule['type']) : 'include';
				if ($action !== 'include' && $action !== 'exclude') {
					$action = 'include';
				}
				$conditions[] = array(
					'type'         => $action,
					'template'     => sanitize_key(isset($rule['template']) ? (string) $rule['template'] : ''),
					'sub_template' => sanitize_key(isset($rule['sub_template']) ? (string) $rule['sub_template'] : ''),
					'id'           => Helper::text(isset($rule['id']) ? $rule['id'] : '', 40),
				);
			}
		}
		if ($conditions === array()) {
			$scope = '';
			if ($type === 'single') {
				$scope = 'singular';
			} elseif ($type === 'archive') {
				$scope = 'archives';
			}
			$conditions[] = array(
				'type'         => 'include',
				'template'     => $scope,
				'sub_template' => ($type === 'single' || $type === 'archive') ? $sub : '',
				'id'           => '',
			);
		}
		if ($type === 'header' || $type === 'footer') {
			$has_site = false;
			foreach ($conditions as $rule) {
				if ($rule['type'] === 'include' && $rule['template'] === '') {
					$has_site = true;
					break;
				}
			}
			if (!$has_site) {
				array_unshift($conditions, array(
					'type'         => 'include',
					'template'     => '',
					'sub_template' => '',
					'id'           => '',
				));
			}
		}
		return $conditions;
	}

	/**
	 * Save Elementor data and, for a library item, its template type and conditions.
	 *
	 * @param int          $post_id  Post ID.
	 * @param string       $type     Post type or template type.
	 * @param array<mixed> $elements Element tree.
	 * @param string       $sub      Template target.
	 * @return true|\WP_Error
	 */
	protected static function store_elementor($post_id, $type, $elements, $sub) {
		$json = wp_json_encode($elements);
		if (!is_string($json)) {
			return Helper::error('onemcp_failed', __('Elementor data could not be encoded.', 'onemcp-pro'));
		}
		update_post_meta($post_id, '_elementor_edit_mode', 'builder');
		update_post_meta($post_id, '_elementor_data', wp_slash($json));
		if (defined('ELEMENTOR_VERSION')) {
			update_post_meta($post_id, '_elementor_version', ELEMENTOR_VERSION);
		}

		if (get_post_type($post_id) === 'elementor_library') {
			update_post_meta($post_id, '_elementor_template_type', $type);
			if (taxonomy_exists('elementor_library_type')) {
				wp_set_object_terms($post_id, $type, 'elementor_library_type');
			}
			update_post_meta($post_id, '_elementor_conditions', self::elementor_conditions($type, $sub));
			return true;
		}

		$document = 'wp-post';
		if ($type === 'page') {
			$document = 'wp-page';
		} elseif ($type !== 'post') {
			$document = $type;
		}
		update_post_meta($post_id, '_elementor_template_type', $document);
		return true;
	}

	/**
	 * Elementor display conditions for one library template.
	 *
	 * @param string $type Template type.
	 * @param string $sub  Post type or taxonomy.
	 * @return array<int,string>
	 */
	protected static function elementor_conditions($type, $sub) {
		if ($type === 'header' || $type === 'footer') {
			return array('include/general');
		}
		if ($type === 'error-404') {
			return array('include/singular/not_found404');
		}
		if ($type === 'search-results') {
			return array('include/archive/search');
		}
		if ($type === 'archive') {
			return array($sub === '' ? 'include/archive' : 'include/archive/' . $sub);
		}
		if ($type === 'single' || $type === 'single-post' || $type === 'single-page' || $type === 'loop-item') {
			if ($sub === '' && $type === 'single-page') {
				$sub = 'page';
			}
			if ($sub === '' && $type === 'single-post') {
				$sub = 'post';
			}
			return array($sub === '' ? 'include/singular' : 'include/singular/' . $sub);
		}
		return array();
	}

	/**
	 * Save Bricks content and template settings.
	 *
	 * @param int          $post_id  Post ID.
	 * @param string       $type     Template type.
	 * @param array<mixed> $elements Elements.
	 * @param string       $sub      Template target.
	 * @return true
	 */
	protected static function store_bricks($post_id, $type, $elements, $sub) {
		update_post_meta($post_id, '_bricks_editor_mode', 'bricks');
		update_post_meta($post_id, '_bricks_page_content_2', $elements);
		if (get_post_type($post_id) !== 'bricks_template') {
			return true;
		}
		update_post_meta($post_id, '_bricks_template_type', $type);
		$main = 'any';
		if ($type === 'content' || $type === 'product') {
			$main = 'postType';
		} elseif ($type === 'archive' || $type === 'product_archive') {
			$main = 'archiveType';
		} elseif ($type === 'search') {
			$main = 'search';
		} elseif ($type === 'error') {
			$main = 'error';
		}
		update_post_meta($post_id, '_bricks_template_settings', array(
			'type'       => $type,
			'conditions' => array(
				array(
					'main'     => $main,
					'postType' => $sub === '' ? array() : array($sub),
				),
			),
		));
		return true;
	}

	/**
	 * Save Beaver Builder data and Themer location rules when that post type exists.
	 *
	 * @param int          $post_id  Post ID.
	 * @param string       $type     Template type.
	 * @param array<mixed> $elements Nodes.
	 * @param string       $sub      Template target.
	 * @return true
	 */
	protected static function store_beaver($post_id, $type, $elements, $sub) {
		update_post_meta($post_id, '_fl_builder_enabled', '1');
		update_post_meta($post_id, '_fl_builder_data', $elements);
		$post_type = get_post_type($post_id);
		if ($post_type === 'fl-theme-layout') {
			$location = 'general:site';
			if (($type === 'singular' || $type === 'archive') && $sub !== '') {
				$location = ($type === 'archive' ? 'archive:' : 'post:') . $sub;
			} elseif ($type === '404') {
				$location = 'general:404';
			}
			update_post_meta($post_id, '_fl_theme_layout_type', $type);
			update_post_meta($post_id, '_fl_theme_layout_settings', array(
				'locations' => array(
					array('location' => $location),
				),
			));
		} elseif ($post_type === 'fl-builder-template') {
			update_post_meta($post_id, '_fl_builder_template_type', $type);
			if (taxonomy_exists('fl-builder-template-type')) {
				wp_set_object_terms($post_id, $type, 'fl-builder-template-type');
			}
		}
		return true;
	}

	/**
	 * Save Divi builder content and theme-builder use rules.
	 *
	 * @param int          $post_id  Post ID.
	 * @param string       $type     Template type.
	 * @param array<mixed> $elements JSON layout, when the builder stores JSON.
	 * @param string       $content  Shortcode markup.
	 * @param string       $sub      Template target.
	 * @return true|\WP_Error
	 */
	protected static function store_divi($post_id, $type, $elements, $content, $sub) {
		update_post_meta($post_id, '_et_pb_use_builder', 'on');
		$body = $content;
		if ($body === '' && $elements !== array()) {
			$json = wp_json_encode($elements);
			$body = is_string($json) ? $json : '';
		}
		if ($body !== '') {
			$result = wp_update_post(wp_slash(array(
				'ID'           => $post_id,
				'post_content' => $body,
			)), true);
			if (is_wp_error($result)) {
				return $result;
			}
		}
		if (get_post_type($post_id) !== 'et_template') {
			return true;
		}
		$group = 'site';
		$payload = '';
		if ($type === 'single') {
			$group = 'singular';
			$payload = $sub;
		} elseif ($type === 'archive') {
			$group = 'archive';
			$payload = $sub;
		}
		update_post_meta($post_id, '_et_enabled', '1');
		update_post_meta($post_id, '_et_use_on', array(
			array(
				array(
					'type'     => 'include',
					'group'    => $group,
					'subGroup' => $payload === '' ? '' : 'post_type',
					'payload'  => $payload,
				),
			),
		));
		return true;
	}

	/**
	 * Save Breakdance data and the template type.
	 *
	 * @param int          $post_id  Post ID.
	 * @param string       $type     Template type.
	 * @param array<mixed> $elements Tree.
	 * @param string       $sub      Template target.
	 * @return true|\WP_Error
	 */
	protected static function store_breakdance($post_id, $type, $elements, $sub) {
		$json = wp_json_encode($elements);
		if (!is_string($json)) {
			return Helper::error('onemcp_failed', __('Breakdance data could not be encoded.', 'onemcp-pro'));
		}
		$stored = wp_json_encode(array('tree_json_string' => $json));
		if (!is_string($stored)) {
			return Helper::error('onemcp_failed', __('Breakdance data could not be encoded.', 'onemcp-pro'));
		}
		update_post_meta($post_id, '_breakdance_data', wp_slash($stored));
		if (get_post_type($post_id) === 'breakdance_template') {
			update_post_meta($post_id, '_breakdance_template_settings', array(
				'type'         => $type,
				'sub_template' => $sub,
			));
		}
		return true;
	}

	/**
	 * Save a SeedProd landing page.
	 *
	 * @param int          $post_id  Post ID.
	 * @param string       $type     Page mode.
	 * @param array<mixed> $elements Document.
	 * @return true|\WP_Error
	 */
	protected static function store_seedprod($post_id, $type, $elements) {
		$document = $elements;
		if ($type !== '' && get_post_type($post_id) === 'seedprod' && !isset($document['page_type'])) {
			$document = array(
				'page_type' => $type,
				'document'  => $elements,
			);
		}
		$json = wp_json_encode($document);
		if (!is_string($json)) {
			return Helper::error('onemcp_failed', __('SeedProd data could not be encoded.', 'onemcp-pro'));
		}
		update_post_meta($post_id, '_seedprod_page', '1');
		if ($type !== '') {
			update_post_meta($post_id, '_seedprod_page_type', $type);
		}
		$result = wp_update_post(wp_slash(array(
			'ID'                    => $post_id,
			'post_content_filtered' => $json,
		)), true);
		return is_wp_error($result) ? $result : true;
	}

	/**
	 * Turn the builder on for a post type that was not enabled yet.
	 *
	 * @param string $slug Builder slug.
	 * @param string $type Post type.
	 * @return void
	 */
	protected static function enable_post_type($slug, $type) {
		if ($slug === 'pagelayer') {
			self::append_option_list('pl_support_ept', $type, array('post', 'page'));
			return;
		}
		if ($slug === 'elementor') {
			self::append_option_list('elementor_cpt_support', $type, array('page', 'post'));
			add_post_type_support($type, 'elementor');
			return;
		}
		if ($slug === 'beaver') {
			self::append_option_list('_fl_builder_post_types', $type, array('page', 'fl-builder-template'));
			return;
		}
		if ($slug === 'bricks') {
			$settings = get_option('bricks_global_settings');
			if (!is_array($settings)) {
				return;
			}
			if (!isset($settings['postTypes']) || !is_array($settings['postTypes'])) {
				$settings['postTypes'] = array('post', 'page');
			}
			if (!in_array($type, $settings['postTypes'], true)) {
				$settings['postTypes'][] = $type;
				update_option('bricks_global_settings', $settings);
			}
		}
	}

	/**
	 * Add one slug to a saved list option without dropping the current values.
	 *
	 * @param string            $option   Option name.
	 * @param string            $value    Slug to add.
	 * @param array<int,string> $defaults Values used when the option is empty.
	 * @return void
	 */
	protected static function append_option_list($option, $value, $defaults) {
		$saved = get_option($option, $defaults);
		if (!is_array($saved) || $saved === array()) {
			$saved = $defaults;
		}
		if (!in_array($value, $saved, true)) {
			$saved[] = $value;
			update_option($option, array_values($saved));
		}
	}

	/**
	 * Compact widget list for one builder.
	 *
	 * @param string $slug Builder slug.
	 * @return array<int,array{tag:string,name:string,group:string}>
	 */
	protected static function widget_index($slug) {
		if ($slug === 'pagelayer') {
			return self::pagelayer_widget_index();
		}
		if ($slug === 'elementor' && class_exists('\Elementor\Plugin') && isset(\Elementor\Plugin::$instance->widgets_manager)) {
			$manager = \Elementor\Plugin::$instance->widgets_manager;
			$types   = method_exists($manager, 'get_widget_types') ? $manager->get_widget_types() : array();
			$items   = array();
			if (is_array($types)) {
				foreach ($types as $name => $widget) {
					if (!is_object($widget) || !method_exists($widget, 'get_title')) {
						continue;
					}
					$group = method_exists($widget, 'get_categories') ? $widget->get_categories() : array();
					$items[] = array(
						'tag'   => (string) $name,
						'name'  => (string) $widget->get_title(),
						'group' => is_array($group) ? implode(', ', $group) : '',
					);
				}
			}
			return $items;
		}
		if ($slug === 'bricks' && class_exists('\Bricks\Elements')) {
			try {
				$elements = isset(\Bricks\Elements::$elements) ? \Bricks\Elements::$elements : array();
				return self::name_index($elements);
			} catch (\Throwable $exception) {
				unset($exception);
				return array();
			}
		}
		if ($slug === 'beaver' && class_exists('FLBuilderModel') && method_exists('FLBuilderModel', 'get_enabled_modules')) {
			$modules = \FLBuilderModel::get_enabled_modules();
			$items   = array();
			if (is_array($modules)) {
				foreach ($modules as $module) {
					$items[] = array(
						'tag'   => (string) $module,
						'name'  => (string) $module,
						'group' => 'module',
					);
				}
			}
			return $items;
		}
		if ($slug === 'divi' && class_exists('ET_Builder_Element') && method_exists('ET_Builder_Element', 'get_modules')) {
			$modules = \ET_Builder_Element::get_modules();
			$items   = array();
			if (is_array($modules)) {
				foreach ($modules as $key => $module) {
					$slug_name = is_object($module) && isset($module->slug) ? (string) $module->slug : (string) $key;
					$label     = is_object($module) && isset($module->name) ? (string) $module->name : $slug_name;
					$items[] = array(
						'tag'   => $slug_name,
						'name'  => $label,
						'group' => 'module',
					);
				}
			}
			return $items;
		}
		if ($slug === 'breakdance') {
			return self::breakdance_widget_index();
		}
		return array();
	}

	/**
	 * Pagelayer widgets from the live shortcode list.
	 *
	 * @return array<int,array{tag:string,name:string,group:string}>
	 */
	protected static function pagelayer_widget_index() {
		self::load_pagelayer_shortcodes();
		global $pagelayer;
		$items = array();
		if (empty($pagelayer->shortcodes) || !is_array($pagelayer->shortcodes)) {
			return $items;
		}
		foreach ($pagelayer->shortcodes as $tag => $data) {
			if (!is_array($data)) {
				continue;
			}
			$items[] = array(
				'tag'   => (string) $tag,
				'name'  => isset($data['name']) ? (string) $data['name'] : (string) $tag,
				'group' => isset($data['group']) ? (string) $data['group'] : '',
			);
		}
		return $items;
	}

	/**
	 * Load Pagelayer widget definitions when they are not in memory yet.
	 *
	 * @return void
	 */
	protected static function load_pagelayer_shortcodes() {
		global $pagelayer;
		if ((empty($pagelayer) || empty($pagelayer->shortcodes)) && function_exists('pagelayer_load_shortcodes')) {
			pagelayer_load_shortcodes();
		}
	}

	/**
	 * Turn a tag-to-label map into widget rows.
	 *
	 * @param mixed $elements Source map.
	 * @return array<int,array{tag:string,name:string,group:string}>
	 */
	protected static function name_index($elements) {
		$items = array();
		if (!is_array($elements)) {
			return $items;
		}
		foreach ($elements as $tag => $data) {
			$name = (string) $tag;
			if (is_array($data) && isset($data['name'])) {
				$name = (string) $data['name'];
			} elseif (is_object($data) && isset($data->name)) {
				$name = (string) $data->name;
			}
			$items[] = array(
				'tag'   => (string) $tag,
				'name'  => $name,
				'group' => '',
			);
		}
		return $items;
	}

	/**
	 * Breakdance elements, when the element API is loaded.
	 *
	 * @return array<int,array{tag:string,name:string,group:string}>
	 */
	protected static function breakdance_widget_index() {
		if (!function_exists('\\Breakdance\\Elements\\get_elements')) {
			return array();
		}
		$elements = \Breakdance\Elements\get_elements();
		$items    = array();
		if (!is_array($elements)) {
			return $items;
		}
		foreach ($elements as $element) {
			if (!is_array($element)) {
				continue;
			}
			$tag = isset($element['slug']) ? (string) $element['slug'] : '';
			if ($tag === '' && isset($element['name'])) {
				$tag = (string) $element['name'];
			}
			if ($tag === '') {
				continue;
			}
			$items[] = array(
				'tag'   => $tag,
				'name'  => isset($element['name']) ? (string) $element['name'] : $tag,
				'group' => isset($element['category']) ? (string) $element['category'] : '',
			);
		}
		return $items;
	}

	/**
	 * Attribute list for one widget.
	 *
	 * @param string $slug   Builder slug.
	 * @param string $widget Widget tag.
	 * @return array<string,mixed>|\WP_Error
	 */
	protected static function widget_detail($slug, $widget) {
		if ($slug === 'pagelayer') {
			return self::pagelayer_widget_detail($widget);
		}
		if ($slug === 'elementor' && class_exists('\Elementor\Plugin') && isset(\Elementor\Plugin::$instance->widgets_manager)) {
			$manager = \Elementor\Plugin::$instance->widgets_manager;
			$type    = method_exists($manager, 'get_widget_types') ? $manager->get_widget_types($widget) : null;
			if (!is_object($type) || !method_exists($type, 'get_controls')) {
				return Helper::error('onemcp_not_found', __('That widget was not found.', 'onemcp-pro'));
			}
			$controls = array();
			foreach ($type->get_controls() as $name => $control) {
				if (!is_array($control) || count($controls) >= 80) {
					break;
				}
				$row = array(
					'name'  => (string) $name,
					'type'  => isset($control['type']) ? (string) $control['type'] : '',
					'label' => isset($control['label']) ? (string) $control['label'] : '',
				);
				if (isset($control['default'])) {
					$row['default'] = $control['default'];
				}
				if (isset($control['options']) && is_array($control['options'])) {
					$row['options'] = array_keys($control['options']);
				}
				$controls[] = $row;
			}
			return array(
				'tag'      => $widget,
				'name'     => method_exists($type, 'get_title') ? (string) $type->get_title() : $widget,
				'controls' => $controls,
			);
		}
		$index = self::widget_index($slug);
		foreach ($index as $item) {
			if ($item['tag'] === $widget) {
				return $item;
			}
		}
		return Helper::error('onemcp_not_found', __('That widget was not found.', 'onemcp-pro'));
	}

	/**
	 * Pagelayer widget attributes from its own schema, without the shared style sections.
	 *
	 * @param string $widget Widget tag.
	 * @return array<string,mixed>|\WP_Error
	 */
	protected static function pagelayer_widget_detail($widget) {
		self::load_pagelayer_shortcodes();
		global $pagelayer;
		if (empty($pagelayer->shortcodes[$widget]) || !is_array($pagelayer->shortcodes[$widget])) {
			return Helper::error('onemcp_not_found', __('That widget was not found.', 'onemcp-pro'));
		}
		$data = $pagelayer->shortcodes[$widget];
		if (class_exists('Pagelayer_Abilities_Register') && method_exists('Pagelayer_Abilities_Register', 'extract_widget_schema')) {
			$schema = \Pagelayer_Abilities_Register::extract_widget_schema($widget, $data);
			if (is_array($schema)) {
				unset($schema['html']);
				return $schema;
			}
		}

		$sections = array();
		$own = isset($data['settings']) && is_array($data['settings']) ? $data['settings'] : array();
		foreach ($own as $section => $label) {
			if (!isset($data[$section]) || !is_array($data[$section])) {
				continue;
			}
			$props = array();
			foreach ($data[$section] as $key => $prop) {
				if (!is_array($prop)) {
					$props[$key] = array('label' => (string) $prop);
					continue;
				}
				$props[$key] = array(
					'type'    => isset($prop['type']) ? $prop['type'] : '',
					'label'   => isset($prop['label']) ? $prop['label'] : '',
					'default' => isset($prop['default']) ? $prop['default'] : null,
				);
				if (isset($prop['list']) && is_array($prop['list'])) {
					$props[$key]['allowed_values'] = $prop['list'];
				}
				if (isset($prop['req'])) {
					$props[$key]['requires'] = $prop['req'];
				}
			}
			$sections[$section] = array(
				'label'      => is_string($label) ? $label : (string) $section,
				'properties' => $props,
			);
		}
		return array(
			'id'        => $widget,
			'name'      => isset($data['name']) ? $data['name'] : $widget,
			'group'     => isset($data['group']) ? $data['group'] : '',
			'innerHTML' => isset($data['innerHTML']) ? $data['innerHTML'] : '',
			'parent'    => isset($data['parent']) ? $data['parent'] : array(),
			'sections'  => $sections,
		);
	}

	/**
	 * How a caller should shape one element.
	 *
	 * @param string $slug Builder slug.
	 * @return string
	 */
	protected static function widget_node_hint($slug) {
		if ($slug === 'pagelayer') {
			return __('Each element is {"tag":"pl_heading","attrs":{},"content":"text or child nodes"}. A pl_row contains pl_col nodes, and a pl_col contains widgets. Read get-editor-widget or get-pagelayer-widget before setting attributes.', 'onemcp-pro');
		}
		if ($slug === 'elementor') {
			return __('elements is the Elementor element tree: elType, widgetType, settings, and nested elements.', 'onemcp-pro');
		}
		if ($slug === 'divi') {
			return __('Pass content as Divi shortcodes, or pass elements as the builder JSON tree.', 'onemcp-pro');
		}
		return __('Pass elements as that builder\'s native element list. Use the widget tag from this list.', 'onemcp-pro');
	}
}
