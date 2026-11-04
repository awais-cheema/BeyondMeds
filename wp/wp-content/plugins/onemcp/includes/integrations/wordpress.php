<?php
/**
 * WordPress content, media, terms, and comments.
 *
 * @package OneMCP
 */

namespace ONEMCP;

use WP_Query;
use WP_User_Query;
use WP_Post;
use WP_User;
use WP_Term;
use WP_Comment;

if (!defined('ABSPATH')) {
	exit;
}

class WordPress {

	/**
	 * Ability definitions.
	 *
	 * @return array<int,array<string,mixed>>
	 */
	static function definitions() {
		$list = array(
			'post_type' => Helper::string_prop(__('Content type, such as post or page.', 'onemcp'), 40),
			'status'    => Helper::string_prop(__('publish, draft, pending, private, future, or any.', 'onemcp'), 20),
			'search'    => Helper::string_prop(__('Search titles and content.', 'onemcp'), 120),
			'limit'     => Helper::int_prop(__('How many items to return. Maximum 50.', 'onemcp')),
			'page'      => Helper::int_prop(__('Page number, starting at 1.', 'onemcp')),
		);

		return array(
			Helper::ability(
				'get-site-info',
				__('Get site info', 'onemcp'),
				Detector::is_sitepad()
					? __('Returns the site name, address, SitePad version, theme, supported integrations, and the MCP endpoint.', 'onemcp')
					: __('Returns the site name, address, WordPress version, theme, supported integrations, and the MCP endpoint.', 'onemcp'),
				'onemcp-wordpress',
				true,
				array(__CLASS__, 'get_site_info'),
				array(),
				array(),
				array('permission' => array(__CLASS__, 'can_edit'))
			),
			Helper::ability(
				'list-content',
				__('List content', 'onemcp'),
				__('Lists posts, pages, or another public content type.', 'onemcp'),
				'onemcp-wordpress',
				true,
				array(__CLASS__, 'list_content'),
				$list,
				array(),
				array('permission' => array(__CLASS__, 'can_edit'))
			),
			Helper::ability(
				'get-content',
				__('Get content', 'onemcp'),
				__('Returns one post or page, including its content when requested.', 'onemcp'),
				'onemcp-wordpress',
				true,
				array(__CLASS__, 'get_content'),
				array(
					'id'              => Helper::int_prop(__('Post ID.', 'onemcp')),
					'include_content' => Helper::bool_prop(__('Include the post content. Large content is truncated.', 'onemcp')),
				),
				array('id'),
				array('permission' => array(__CLASS__, 'can_edit'))
			),
			Helper::ability(
				'create-content',
				__('Create content', 'onemcp'),
				Detector::is_sitepad()
					? __('Creates a plain post or page as HTML. SitePad builds pages, posts, theme templates, and other UI only with the Pagelayer tools, using Pagelayer widgets and their settings. Do not use this tool for a visual layout.', 'onemcp')
					: __('Creates a post or page. New content is a draft unless a status is given and the user can publish. When a page builder is active, design the layout with that builder\'s widgets and settings instead of raw HTML.', 'onemcp'),
				'onemcp-wordpress',
				false,
				array(__CLASS__, 'create_content'),
				array(
					'post_type' => Helper::string_prop(__('Content type. Defaults to post.', 'onemcp'), 40),
					'title'     => Helper::string_prop(__('Title.', 'onemcp'), 200),
					'content'   => Helper::string_prop(__('Body HTML.', 'onemcp'), 200000),
					'excerpt'   => Helper::string_prop(__('Excerpt.', 'onemcp'), 1000),
					'status'    => Helper::string_prop(__('draft, pending, private, future, or publish.', 'onemcp'), 20),
				),
				array('title'),
				array('permission' => array(__CLASS__, 'can_edit'))
			),
			Helper::ability(
				'update-content',
				__('Update content', 'onemcp'),
				Detector::is_sitepad()
					? __('Updates the title, excerpt, or status of one post or page. To change a visual layout, use the Pagelayer element and widget tools and that widget\'s real settings. Do not replace a builder layout with raw HTML.', 'onemcp')
					: __('Updates the title, content, excerpt, or status of one post or page. When a page builder owns the layout, change it with that builder\'s widgets and settings instead of replacing the content with raw HTML.', 'onemcp'),
				'onemcp-wordpress',
				false,
				array(__CLASS__, 'update_content'),
				array(
					'id'      => Helper::int_prop(__('Post ID.', 'onemcp')),
					'title'   => Helper::string_prop(__('New title.', 'onemcp'), 200),
					'content' => Helper::string_prop(__('New body HTML.', 'onemcp'), 200000),
					'excerpt' => Helper::string_prop(__('New excerpt.', 'onemcp'), 1000),
					'status'  => Helper::string_prop(__('draft, pending, private, future, or publish.', 'onemcp'), 20),
				),
				array('id'),
				array('permission' => array(__CLASS__, 'can_edit'))
			),
			Helper::ability(
				'trash-content',
				__('Trash content', 'onemcp'),
				__('Moves one post or page to the trash.', 'onemcp'),
				'onemcp-wordpress',
				false,
				array(__CLASS__, 'trash_content'),
				array(
					'id' => Helper::int_prop(__('Post ID.', 'onemcp')),
				),
				array('id'),
				array(
					'destructive' => true,
					'permission'  => array(__CLASS__, 'can_delete'),
				)
			),
			Helper::ability(
				'list-media',
				__('List media', 'onemcp'),
				__('Lists files in the media library.', 'onemcp'),
				'onemcp-wordpress',
				true,
				array(__CLASS__, 'list_media'),
				array(
					'search' => Helper::string_prop(__('Search titles.', 'onemcp'), 120),
					'limit'  => Helper::int_prop(__('How many items to return. Maximum 50.', 'onemcp')),
					'page'   => Helper::int_prop(__('Page number, starting at 1.', 'onemcp')),
				),
				array(),
				array('cap' => 'upload_files')
			),
			Helper::ability(
				'get-media',
				__('Get media', 'onemcp'),
				__('Returns one media item, including its URL and alt text.', 'onemcp'),
				'onemcp-wordpress',
				true,
				array(__CLASS__, 'get_media'),
				array(
					'id' => Helper::int_prop(__('Attachment ID.', 'onemcp')),
				),
				array('id'),
				array('cap' => 'upload_files')
			),
			Helper::ability(
				'update-media',
				__('Update media', 'onemcp'),
				__('Updates the title, caption, or alt text of one media item.', 'onemcp'),
				'onemcp-wordpress',
				false,
				array(__CLASS__, 'update_media'),
				array(
					'id'      => Helper::int_prop(__('Attachment ID.', 'onemcp')),
					'title'   => Helper::string_prop(__('New title.', 'onemcp'), 200),
					'caption' => Helper::string_prop(__('New caption.', 'onemcp'), 1000),
					'alt'     => Helper::string_prop(__('New alt text.', 'onemcp'), 300),
				),
				array('id'),
				array('cap' => 'upload_files')
			),
			Helper::ability(
				'add-media-from-url',
				__('Add media from URL', 'onemcp'),
				__('Downloads an http or https file into the media library.', 'onemcp'),
				'onemcp-wordpress',
				false,
				array(__CLASS__, 'add_media_from_url'),
				array(
					'url'         => Helper::string_prop(__('Public http or https file URL.', 'onemcp'), 2000),
					'title'       => Helper::string_prop(__('Optional title.', 'onemcp'), 200),
					'alt'         => Helper::string_prop(__('Optional alt text.', 'onemcp'), 300),
					'description' => Helper::string_prop(__('Optional caption.', 'onemcp'), 1000),
				),
				array('url'),
				array('cap' => 'upload_files')
			),
			Helper::ability(
				'list-terms',
				__('List terms', 'onemcp'),
				__('Lists categories, tags, or terms from another taxonomy.', 'onemcp'),
				'onemcp-wordpress',
				true,
				array(__CLASS__, 'list_terms'),
				array(
					'taxonomy' => Helper::string_prop(__('Taxonomy. Defaults to category.', 'onemcp'), 40),
					'search'   => Helper::string_prop(__('Search term names.', 'onemcp'), 120),
					'limit'    => Helper::int_prop(__('How many terms to return. Maximum 50.', 'onemcp')),
				),
				array(),
				array('permission' => array(__CLASS__, 'can_edit'))
			),
			Helper::ability(
				'list-comments',
				__('List comments', 'onemcp'),
				__('Lists comments, optionally for one post.', 'onemcp'),
				'onemcp-wordpress',
				true,
				array(__CLASS__, 'list_comments'),
				array(
					'post_id' => Helper::int_prop(__('Limit comments to this post ID.', 'onemcp')),
					'status'  => Helper::string_prop(__('approve, hold, or all. Defaults to approve.', 'onemcp'), 20),
					'limit'   => Helper::int_prop(__('How many comments to return. Maximum 50.', 'onemcp')),
				),
				array(),
				array('permission' => array(__CLASS__, 'can_edit'))
			),
			Helper::ability(
				'moderate-comment',
				__('Moderate comment', 'onemcp'),
				__('Approves, holds, spams, or trashes one comment.', 'onemcp'),
				'onemcp-wordpress',
				false,
				array(__CLASS__, 'moderate_comment'),
				array(
					'id'     => Helper::int_prop(__('Comment ID.', 'onemcp')),
					'status' => Helper::string_prop(__('approve, hold, spam, or trash.', 'onemcp'), 20),
				),
				array('id', 'status'),
				array('cap' => 'moderate_comments')
			),
			Helper::ability(
				'list-plugins',
				__('List plugins', 'onemcp'),
				__('Lists installed plugins and whether each one is active.', 'onemcp'),
				'onemcp-wordpress',
				true,
				array(__CLASS__, 'list_plugins'),
				array(
					'search' => Helper::string_prop(__('Search plugin names.', 'onemcp'), 120),
				),
				array(),
				array('cap' => 'activate_plugins')
			),
			Helper::ability(
				'list-themes',
				__('List themes', 'onemcp'),
				__('Lists installed themes and which one is active.', 'onemcp'),
				'onemcp-wordpress',
				true,
				array(__CLASS__, 'list_themes'),
				array(),
				array(),
				array('cap' => 'switch_themes')
			),
			Helper::ability(
				'list-users',
				__('List users', 'onemcp'),
				__('Lists user accounts. Passwords and sessions are not included.', 'onemcp'),
				'onemcp-wordpress',
				true,
				array(__CLASS__, 'list_users'),
				array(
					'search' => Helper::string_prop(__('Search login, name, or email.', 'onemcp'), 120),
					'limit'  => Helper::int_prop(__('How many users to return. Maximum 50.', 'onemcp')),
					'page'   => Helper::int_prop(__('Page number, starting at 1.', 'onemcp')),
				),
				array(),
				array('cap' => 'list_users')
			),
			Helper::ability(
				'list-taxonomies',
				__('List taxonomies', 'onemcp'),
				__('Lists public taxonomies such as categories and tags.', 'onemcp'),
				'onemcp-wordpress',
				true,
				array(__CLASS__, 'list_taxonomies'),
				array(),
				array(),
				array('permission' => array(__CLASS__, 'can_edit'))
			),
			Helper::ability(
				'create-term',
				__('Create term', 'onemcp'),
				__('Creates a category, tag, or other term.', 'onemcp'),
				'onemcp-wordpress',
				false,
				array(__CLASS__, 'create_term'),
				array(
					'taxonomy'    => Helper::string_prop(__('Taxonomy. Defaults to category.', 'onemcp'), 40),
					'name'        => Helper::string_prop(__('Term name.', 'onemcp'), 200),
					'slug'        => Helper::string_prop(__('Optional slug.', 'onemcp'), 200),
					'description' => Helper::string_prop(__('Optional description.', 'onemcp'), 500),
					'parent'      => Helper::int_prop(__('Parent term ID. Use 0 for a top-level term.', 'onemcp')),
				),
				array('name'),
				array('cap' => 'manage_categories')
			),
			Helper::ability(
				'update-term',
				__('Update term', 'onemcp'),
				__('Updates the name, slug, or description of one term.', 'onemcp'),
				'onemcp-wordpress',
				false,
				array(__CLASS__, 'update_term'),
				array(
					'id'          => Helper::int_prop(__('Term ID.', 'onemcp')),
					'taxonomy'    => Helper::string_prop(__('Taxonomy. Defaults to category.', 'onemcp'), 40),
					'name'        => Helper::string_prop(__('New name.', 'onemcp'), 200),
					'slug'        => Helper::string_prop(__('New slug.', 'onemcp'), 200),
					'description' => Helper::string_prop(__('New description.', 'onemcp'), 500),
				),
				array('id'),
				array('cap' => 'manage_categories')
			),
			Helper::ability(
				'set-content-terms',
				__('Set content terms', 'onemcp'),
				__('Replaces the categories, tags, or other terms on one post. Send names or IDs separated by commas.', 'onemcp'),
				'onemcp-wordpress',
				false,
				array(__CLASS__, 'set_content_terms'),
				array(
					'id'       => Helper::int_prop(__('Post ID.', 'onemcp')),
					'taxonomy' => Helper::string_prop(__('Taxonomy. Defaults to category.', 'onemcp'), 40),
					'terms'    => Helper::string_prop(__('Comma-separated term names or IDs.', 'onemcp'), 1000),
				),
				array('id', 'terms'),
				array('permission' => array(__CLASS__, 'can_edit'))
			),
			Helper::ability(
				'get-content-meta',
				__('Get content meta', 'onemcp'),
				__('Reads custom fields on one post. Passwords, tokens, and API keys are omitted.', 'onemcp'),
				'onemcp-wordpress',
				true,
				array(__CLASS__, 'get_content_meta'),
				array(
					'id'  => Helper::int_prop(__('Post ID.', 'onemcp')),
					'key' => Helper::string_prop(__('One meta key. Omit to list keys.', 'onemcp'), 180),
				),
				array('id'),
				array('permission' => array(__CLASS__, 'can_edit'))
			),
			Helper::ability(
				'update-content-meta',
				__('Update content meta', 'onemcp'),
				__('Sets one custom field. An empty value deletes it. Secret keys are refused.', 'onemcp'),
				'onemcp-wordpress',
				false,
				array(__CLASS__, 'update_content_meta'),
				array(
					'id'    => Helper::int_prop(__('Post ID.', 'onemcp')),
					'key'   => Helper::string_prop(__('Meta key.', 'onemcp'), 180),
					'value' => Helper::string_prop(__('Meta value. Empty deletes the field.', 'onemcp'), 10000),
				),
				array('id', 'key'),
				array('permission' => array(__CLASS__, 'can_edit'))
			),
			Helper::ability(
				'list-menus',
				__('List menus', 'onemcp'),
				__('Lists navigation menus.', 'onemcp'),
				'onemcp-wordpress',
				true,
				array(__CLASS__, 'list_menus'),
				array(),
				array(),
				array('cap' => 'edit_theme_options')
			),
			Helper::ability(
				'list-menu-items',
				__('List menu items', 'onemcp'),
				__('Lists the items in one navigation menu.', 'onemcp'),
				'onemcp-wordpress',
				true,
				array(__CLASS__, 'list_menu_items'),
				array(
					'menu_id' => Helper::int_prop(__('Menu ID.', 'onemcp')),
				),
				array('menu_id'),
				array('cap' => 'edit_theme_options')
			),
			Helper::ability(
				'create-menu',
				__('Create menu', 'onemcp'),
				__('Creates an empty navigation menu.', 'onemcp'),
				'onemcp-wordpress',
				false,
				array(__CLASS__, 'create_menu'),
				array(
					'name' => Helper::string_prop(__('Menu name.', 'onemcp'), 200),
				),
				array('name'),
				array('cap' => 'edit_theme_options')
			),
			Helper::ability(
				'add-menu-item',
				__('Add menu item', 'onemcp'),
				__('Adds a custom link to a navigation menu.', 'onemcp'),
				'onemcp-wordpress',
				false,
				array(__CLASS__, 'add_menu_item'),
				array(
					'menu_id' => Helper::int_prop(__('Menu ID.', 'onemcp')),
					'title'   => Helper::string_prop(__('Link title.', 'onemcp'), 200),
					'url'     => Helper::string_prop(__('Link URL.', 'onemcp'), 500),
				),
				array('menu_id', 'title', 'url'),
				array('cap' => 'edit_theme_options')
			),
		);
	}

	/**
	 * Coarse edit check. Handlers still check the individual post.
	 *
	 * @return bool
	 */
	 static function can_edit() {
		return current_user_can('edit_posts') || current_user_can('edit_pages');
	}

	/**
	 * Coarse delete check.
	 *
	 * @return bool
	 */
	 static function can_delete() {
		return current_user_can('delete_posts') || current_user_can('delete_pages');
	}

	/**
	 * Site summary.
	 *
	 * @param array<string,mixed> $input Input.
	 * @return array<string,mixed>
	 */
	 static function get_site_info($input) {
		unset($input);
		$theme = wp_get_theme();
		return array(
			'name'                => get_bloginfo('name'),
			'description'         => get_bloginfo('description'),
			'url'                 => home_url('/'),
			'wp_version'          => get_bloginfo('version'),
			'php_version'         => PHP_VERSION,
			'language'            => get_locale(),
			'timezone'            => wp_timezone_string(),
			'permalink_structure' => (string) get_option('permalink_structure'),
			'theme'               => array(
				'name'       => $theme->get('Name'),
				'stylesheet' => $theme->get_stylesheet(),
				'version'    => $theme->get('Version'),
			),
			'counts'              => array(
				'posts' => (int) wp_count_posts('post')->publish,
				'pages' => (int) wp_count_posts('page')->publish,
			),
			'integrations'        => \ONEMCP\Detector::status_list(),
			'visual_editor'       => self::visual_editor(),
			'mcp'                 => \ONEMCP\Connection::summary(),
		);
	}

	/**
	 * Which builder should design pages, posts, theme templates, and other UI.
	 *
	 * @return array<string,mixed>
	 */
	private static function visual_editor() {
		if (Detector::is_sitepad()) {
			return array(
				'builder'      => 'pagelayer',
				'only_builder' => true,
				'note'         => 'SitePad includes only the Pagelayer builder. Build pages, posts, theme templates, headers, footers, and other UI with the Pagelayer tools. Use Pagelayer widgets and the settings from their schemas.',
			);
		}
		$builders = Detector::active_slugs('builders');
		return array(
			'builders' => $builders,
			'note'     => $builders === array()
				? 'No page builder is active. Create plain content with the content tools.'
				: 'Build visual layouts with the active page builder and that builder\'s own widgets and settings.',
		);
	}

	/**
	 * List posts.
	 *
	 * @param array<string,mixed> $input Input.
	 * @return array<string,mixed>|WP_Error
	 */
	 static function list_content($input) {
		$type = Helper::post_type(isset($input['post_type']) ? $input['post_type'] : 'post');
		if (is_wp_error($type)) {
			return $type;
		}

		$status = isset($input['status']) ? sanitize_key((string) $input['status']) : 'any';
		$allowed = array('any', 'publish', 'draft', 'pending', 'private', 'future', 'trash');
		if (!in_array($status, $allowed, true)) {
			$status = 'any';
		}

		$limit = Helper::limit(isset($input['limit']) ? $input['limit'] : 20, 20, 50);
		$page  = Helper::page($input);
		$args  = array(
			'post_type'      => $type,
			'post_status'    => $status,
			'posts_per_page' => $limit,
			'paged'          => $page,
			'orderby'        => 'modified',
			'order'          => 'DESC',
		);
		if (!empty($input['search'])) {
			$args['s'] = Helper::text($input['search'], 120);
		}

		$query = Helper::query_posts($args);
		$items = array();
		foreach ($query->posts as $post) {
			if ($post instanceof WP_Post) {
				$items[] = Helper::post_row($post);
			}
		}

		return array(
			'items' => $items,
			'total' => (int) $query->found_posts,
			'page'  => $page,
		);
	}

	/**
	 * One post.
	 *
	 * @param array<string,mixed> $input Input.
	 * @return array<string,mixed>|WP_Error
	 */
	 static function get_content($input) {
		$post = Helper::editable_post(isset($input['id']) ? $input['id'] : 0);
		if (is_wp_error($post)) {
			return $post;
		}

		$row = Helper::post_row($post);
		$row['excerpt'] = Helper::trim_text($post->post_excerpt, 500);
		if (!empty($input['include_content'])) {
			$content = (string) $post->post_content;
			if (strlen($content) > 100000) {
				$row['content']   = substr($content, 0, 100000);
				$row['truncated'] = true;
			} else {
				$row['content'] = $content;
			}
		}
		return $row;
	}

	/**
	 * Create a post.
	 *
	 * @param array<string,mixed> $input Input.
	 * @return array<string,mixed>|WP_Error
	 */
	 static function create_content($input) {
		$type = Helper::post_type(isset($input['post_type']) ? $input['post_type'] : 'post');
		if (is_wp_error($type)) {
			return $type;
		}
		$object = get_post_type_object($type);
		$cap    = ($object && !empty($object->cap->create_posts)) ? $object->cap->create_posts : 'edit_posts';
		if (!current_user_can($cap)) {
			return Helper::error('onemcp_forbidden', __('You cannot create this content type.', 'onemcp'));
		}

		$post_id = wp_insert_post(wp_slash(array(
			'post_type'    => $type,
			'post_title'   => Helper::text(isset($input['title']) ? $input['title'] : '', 200),
			'post_content' => isset($input['content']) ? wp_kses_post((string) $input['content']) : '',
			'post_excerpt' => Helper::text(isset($input['excerpt']) ? $input['excerpt'] : '', 1000),
			'post_status'  => Helper::post_status(isset($input['status']) ? $input['status'] : 'draft', $type, 'draft'),
		)), true);

		if (is_wp_error($post_id)) {
			return $post_id;
		}
		return self::get_content(array('id' => $post_id, 'include_content' => true));
	}

	/**
	 * Update a post.
	 *
	 * @param array<string,mixed> $input Input.
	 * @return array<string,mixed>|WP_Error
	 */
	 static function update_content($input) {
		$post = Helper::editable_post(isset($input['id']) ? $input['id'] : 0);
		if (is_wp_error($post)) {
			return $post;
		}

		$update = array('ID' => $post->ID);
		if (array_key_exists('title', $input)) {
			$update['post_title'] = Helper::text($input['title'], 200);
		}
		if (array_key_exists('content', $input)) {
			$update['post_content'] = wp_kses_post((string) $input['content']);
		}
		if (array_key_exists('excerpt', $input)) {
			$update['post_excerpt'] = Helper::text($input['excerpt'], 1000);
		}
		if (array_key_exists('status', $input)) {
			$update['post_status'] = Helper::post_status($input['status'], $post->post_type, $post->post_status);
		}
		if (count($update) === 1) {
			return Helper::error('onemcp_invalid', __('Provide a title, content, excerpt, or status to update.', 'onemcp'));
		}

		$result = wp_update_post(wp_slash($update), true);
		if (is_wp_error($result)) {
			return $result;
		}
		return self::get_content(array('id' => $post->ID, 'include_content' => true));
	}

	/**
	 * Trash a post.
	 *
	 * @param array<string,mixed> $input Input.
	 * @return array<string,mixed>|WP_Error
	 */
	 static function trash_content($input) {
		$post = Helper::editable_post(isset($input['id']) ? $input['id'] : 0);
		if (is_wp_error($post)) {
			return $post;
		}
		if (!current_user_can('delete_post', $post->ID)) {
			return Helper::error('onemcp_forbidden', __('You cannot trash this content.', 'onemcp'));
		}
		$trashed = wp_trash_post($post->ID);
		if (!$trashed) {
			return Helper::error('onemcp_failed', __('The content could not be moved to the trash.', 'onemcp'));
		}
		return array(
			'id'     => (int) $post->ID,
			'status' => 'trash',
		);
	}

	/**
	 * List attachments.
	 *
	 * @param array<string,mixed> $input Input.
	 * @return array<string,mixed>
	 */
	 static function list_media($input) {
		$limit = Helper::limit(isset($input['limit']) ? $input['limit'] : 20, 20, 50);
		$page  = Helper::page($input);
		$args  = array(
			'post_type'      => 'attachment',
			'post_status'    => 'inherit',
			'posts_per_page' => $limit,
			'paged'          => $page,
			'orderby'        => 'date',
			'order'          => 'DESC',
		);
		if (!empty($input['search'])) {
			$args['s'] = Helper::text($input['search'], 120);
		}
		$query = new WP_Query($args);
		$items = array();
		foreach ($query->posts as $post) {
			if ($post instanceof WP_Post) {
				$items[] = self::media_row($post, false);
			}
		}
		return array(
			'items' => $items,
			'total' => (int) $query->found_posts,
			'page'  => $page,
		);
	}

	/**
	 * One attachment.
	 *
	 * @param array<string,mixed> $input Input.
	 * @return array<string,mixed>|WP_Error
	 */
	 static function get_media($input) {
		$post = self::attachment(isset($input['id']) ? $input['id'] : 0);
		if (is_wp_error($post)) {
			return $post;
		}
		return self::media_row($post, true);
	}

	/**
	 * Update attachment text fields.
	 *
	 * @param array<string,mixed> $input Input.
	 * @return array<string,mixed>|WP_Error
	 */
	 static function update_media($input) {
		$post = self::attachment(isset($input['id']) ? $input['id'] : 0);
		if (is_wp_error($post)) {
			return $post;
		}

		$update = array('ID' => $post->ID);
		if (array_key_exists('title', $input)) {
			$update['post_title'] = Helper::text($input['title'], 200);
		}
		if (array_key_exists('caption', $input)) {
			$update['post_excerpt'] = Helper::text($input['caption'], 1000);
		}
		if (count($update) > 1) {
			$result = wp_update_post(wp_slash($update), true);
			if (is_wp_error($result)) {
				return $result;
			}
		}
		if (array_key_exists('alt', $input)) {
			update_post_meta($post->ID, '_wp_attachment_image_alt', Helper::text($input['alt'], 300));
		}
		if (count($update) === 1 && !array_key_exists('alt', $input)) {
			return Helper::error('onemcp_invalid', __('Provide a title, caption, or alt text.', 'onemcp'));
		}
		return self::media_row(get_post($post->ID), true);
	}

	/**
	 * Sideload a remote file.
	 *
	 * @param array<string,mixed> $input Input.
	 * @return array<string,mixed>|WP_Error
	 */
	 static function add_media_from_url($input) {
		$url = isset($input['url']) ? esc_url_raw((string) $input['url']) : '';
		if ($url === '' || !wp_http_validate_url($url)) {
			return Helper::error('onemcp_invalid', __('Provide a public http or https file URL.', 'onemcp'));
		}

		if (defined('SITEPAD')) {
			require_once ABSPATH . 'site-admin/includes/file.php';
			require_once ABSPATH . 'site-admin/includes/media.php';
			require_once ABSPATH . 'site-admin/includes/image.php';
		} else {
			require_once ABSPATH . 'wp-admin/includes/file.php';
			require_once ABSPATH . 'wp-admin/includes/media.php';
			require_once ABSPATH . 'wp-admin/includes/image.php';
		}

		$tmp = download_url($url, 30);
		if (is_wp_error($tmp)) {
			return $tmp;
		}

		$path = wp_parse_url($url, PHP_URL_PATH);
		$name = is_string($path) ? sanitize_file_name(basename($path)) : '';
		if ($name === '') {
			$name = 'download';
		}
		$file = array(
			'name'     => $name,
			'tmp_name' => $tmp,
		);
		$attachment_id = media_handle_sideload($file, 0, Helper::text(isset($input['title']) ? $input['title'] : '', 200));
		if (is_wp_error($attachment_id)) {
			wp_delete_file($tmp);
			return $attachment_id;
		}
		if (!empty($input['alt'])) {
			update_post_meta($attachment_id, '_wp_attachment_image_alt', Helper::text($input['alt'], 300));
		}
		if (!empty($input['description'])) {
			wp_update_post(array(
				'ID'           => $attachment_id,
				'post_excerpt' => Helper::text($input['description'], 1000),
			));
		}
		$post = get_post($attachment_id);
		return $post instanceof WP_Post ? self::media_row($post, true) : array('id' => (int) $attachment_id);
	}

	/**
	 * List terms.
	 *
	 * @param array<string,mixed> $input Input.
	 * @return array<string,mixed>|WP_Error
	 */
	 static function list_terms($input) {
		$taxonomy = sanitize_key(isset($input['taxonomy']) ? (string) $input['taxonomy'] : 'category');
		if (!taxonomy_exists($taxonomy)) {
			return Helper::error('onemcp_invalid', __('That taxonomy does not exist.', 'onemcp'));
		}
		$args = array(
			'taxonomy'   => $taxonomy,
			'hide_empty' => false,
			'number'     => Helper::limit(isset($input['limit']) ? $input['limit'] : 20, 20, 50),
		);
		if (!empty($input['search'])) {
			$args['search'] = Helper::text($input['search'], 120);
		}
		$terms = get_terms($args);
		if (is_wp_error($terms)) {
			return $terms;
		}
		$items = array();
		foreach ($terms as $term) {
			$items[] = array(
				'id'     => (int) $term->term_id,
				'name'   => $term->name,
				'slug'   => $term->slug,
				'parent' => (int) $term->parent,
				'count'  => (int) $term->count,
			);
		}
		return array(
			'taxonomy' => $taxonomy,
			'items'    => $items,
		);
	}

	/**
	 * List comments without email addresses.
	 *
	 * @param array<string,mixed> $input Input.
	 * @return array<string,mixed>|WP_Error
	 */
	 static function list_comments($input) {
		$status = isset($input['status']) ? sanitize_key((string) $input['status']) : 'approve';
		if (!in_array($status, array('approve', 'hold', 'all'), true)) {
			$status = 'approve';
		}
		$args = array(
			'status' => $status,
			'number' => Helper::limit(isset($input['limit']) ? $input['limit'] : 20, 20, 50),
			'orderby' => 'comment_date_gmt',
			'order'  => 'DESC',
		);
		if (!empty($input['post_id'])) {
			$post = Helper::editable_post($input['post_id']);
			if (is_wp_error($post)) {
				return $post;
			}
			$args['post_id'] = $post->ID;
		} elseif ($status !== 'approve' && !current_user_can('moderate_comments')) {
			return Helper::error('onemcp_forbidden', __('You cannot list unapproved comments.', 'onemcp'));
		}
		$comments = get_comments($args);
		$items    = array();
		foreach ($comments as $comment) {
			if (!$comment instanceof WP_Comment) {
				continue;
			}
			if (!current_user_can('moderate_comments') && !current_user_can('read_post', (int) $comment->comment_post_ID)) {
				continue;
			}
			$items[] = array(
				'id'      => (int) $comment->comment_ID,
				'post_id' => (int) $comment->comment_post_ID,
				'author'  => $comment->comment_author,
				'date'    => $comment->comment_date_gmt,
				'status'  => wp_get_comment_status($comment),
				'content' => Helper::trim_text($comment->comment_content, 240),
			);
		}
		return array('items' => $items);
	}

	/**
	 * Change one comment's moderation status.
	 *
	 * @param array<string,mixed> $input Input.
	 * @return array<string,mixed>|WP_Error
	 */
	 static function moderate_comment($input) {
		$comment = get_comment(Helper::absint(isset($input['id']) ? $input['id'] : 0));
		if (!$comment instanceof WP_Comment) {
			return Helper::error('onemcp_not_found', __('Comment was not found.', 'onemcp'));
		}
		$status = isset($input['status']) ? sanitize_key((string) $input['status']) : '';
		$allowed = array('approve', 'hold', 'spam', 'trash');
		if (!in_array($status, $allowed, true)) {
			return Helper::error('onemcp_invalid', __('Status must be approve, hold, spam, or trash.', 'onemcp'));
		}
		if (!current_user_can('moderate_comments') && !current_user_can('edit_comment', $comment->comment_ID)) {
			return Helper::error('onemcp_forbidden', __('You cannot moderate this comment.', 'onemcp'));
		}
		$updated = wp_set_comment_status($comment->comment_ID, $status);
		if (!$updated) {
			return Helper::error('onemcp_failed', __('The comment status was not changed.', 'onemcp'));
		}
		return array(
			'id'     => (int) $comment->comment_ID,
			'status' => wp_get_comment_status($comment),
		);
	}

	/**
	 * Installed plugins.
	 *
	 * @param array<string,mixed> $input Input.
	 * @return array<string,mixed>
	 */
	 static function list_plugins($input) {
		if (!function_exists('get_plugins')) {
			if (defined('SITEPAD')) {
				require_once ABSPATH . 'site-admin/includes/plugin.php';
			} else {
				require_once ABSPATH . 'wp-admin/includes/plugin.php';
			}
		}
		$search = isset($input['search']) ? strtolower(Helper::text($input['search'], 120)) : '';
		$active = Helper::active_plugin_files();
		$items  = array();
		foreach (get_plugins() as $file => $plugin) {
			$name = isset($plugin['Name']) ? (string) $plugin['Name'] : '';
			if ($search !== '' && strpos(strtolower($name), $search) === false && strpos(strtolower((string) $file), $search) === false) {
				continue;
			}
			$items[] = array(
				'file'    => (string) $file,
				'name'    => $name,
				'version' => isset($plugin['Version']) ? (string) $plugin['Version'] : '',
				'active'  => in_array((string) $file, $active, true),
			);
		}
		return array(
			'items' => $items,
			'total' => count($items),
		);
	}

	/**
	 * Installed themes.
	 *
	 * @param array<string,mixed> $input Input.
	 * @return array<string,mixed>
	 */
	 static function list_themes($input) {
		unset($input);
		$active = get_stylesheet();
		$items  = array();
		foreach (wp_get_themes() as $stylesheet => $theme) {
			$items[] = array(
				'stylesheet' => (string) $stylesheet,
				'name'       => $theme->get('Name'),
				'version'    => $theme->get('Version'),
				'active'     => (string) $stylesheet === $active,
			);
		}
		return array(
			'items' => $items,
			'total' => count($items),
		);
	}

	/**
	 * User accounts without secrets.
	 *
	 * @param array<string,mixed> $input Input.
	 * @return array<string,mixed>
	 */
	 static function list_users($input) {
		$limit = Helper::limit(isset($input['limit']) ? $input['limit'] : 20, 20, 50);
		$page  = Helper::page($input);
		$args  = array(
			'number' => $limit,
			'paged'  => $page,
			'fields' => 'all',
		);
		if (!empty($input['search'])) {
			$args['search'] = '*' . Helper::text($input['search'], 120) . '*';
			$args['search_columns'] = array('user_login', 'user_email', 'display_name');
		}
		$query = new WP_User_Query($args);
		$items = array();
		foreach ($query->get_results() as $user) {
			if (!$user instanceof WP_User) {
				continue;
			}
			$items[] = array(
				'id'           => (int) $user->ID,
				'login'        => $user->user_login,
				'display_name' => $user->display_name,
				'email'        => $user->user_email,
				'roles'        => array_values((array) $user->roles),
			);
		}
		return array(
			'items' => $items,
			'total' => (int) $query->get_total(),
			'page'  => $page,
		);
	}

	/**
	 * Public taxonomies.
	 *
	 * @param array<string,mixed> $input Input.
	 * @return array<string,mixed>
	 */
	 static function list_taxonomies($input) {
		unset($input);
		$items = array();
		foreach (get_taxonomies(array('public' => true), 'objects') as $taxonomy) {
			$items[] = array(
				'name'         => $taxonomy->name,
				'label'        => $taxonomy->label,
				'hierarchical' => (bool) $taxonomy->hierarchical,
			);
		}
		return array('items' => $items);
	}

	/**
	 * Create a term.
	 *
	 * @param array<string,mixed> $input Input.
	 * @return array<string,mixed>|WP_Error
	 */
	 static function create_term($input) {
		$taxonomy = self::taxonomy_name(isset($input['taxonomy']) ? $input['taxonomy'] : 'category');
		if (is_wp_error($taxonomy)) {
			return $taxonomy;
		}
		$name = Helper::text(isset($input['name']) ? $input['name'] : '', 200);
		if ($name === '') {
			return Helper::error('onemcp_invalid', __('Provide a term name.', 'onemcp'));
		}
		$args = array();
		if (!empty($input['slug'])) {
			$args['slug'] = sanitize_title(Helper::text($input['slug'], 200));
		}
		if (isset($input['description'])) {
			$args['description'] = Helper::text($input['description'], 500);
		}
		if (isset($input['parent'])) {
			$args['parent'] = Helper::absint($input['parent']);
		}
		$result = wp_insert_term($name, $taxonomy, $args);
		if (is_wp_error($result)) {
			return $result;
		}
		return self::term_row(get_term((int) $result['term_id'], $taxonomy));
	}

	/**
	 * Update a term.
	 *
	 * @param array<string,mixed> $input Input.
	 * @return array<string,mixed>|WP_Error
	 */
	 static function update_term($input) {
		$taxonomy = self::taxonomy_name(isset($input['taxonomy']) ? $input['taxonomy'] : 'category');
		if (is_wp_error($taxonomy)) {
			return $taxonomy;
		}
		$term = get_term(Helper::absint(isset($input['id']) ? $input['id'] : 0), $taxonomy);
		if (!$term instanceof WP_Term) {
			return Helper::error('onemcp_not_found', __('Term was not found.', 'onemcp'));
		}
		$args = array();
		if (array_key_exists('name', $input)) {
			$args['name'] = Helper::text($input['name'], 200);
		}
		if (array_key_exists('slug', $input)) {
			$args['slug'] = sanitize_title(Helper::text($input['slug'], 200));
		}
		if (array_key_exists('description', $input)) {
			$args['description'] = Helper::text($input['description'], 500);
		}
		if ($args === array()) {
			return Helper::error('onemcp_invalid', __('Provide a name, slug, or description.', 'onemcp'));
		}
		$result = wp_update_term($term->term_id, $taxonomy, $args);
		if (is_wp_error($result)) {
			return $result;
		}
		return self::term_row(get_term($term->term_id, $taxonomy));
	}

	/**
	 * Replace terms on one post.
	 *
	 * @param array<string,mixed> $input Input.
	 * @return array<string,mixed>|WP_Error
	 */
	 static function set_content_terms($input) {
		$post = Helper::editable_post(isset($input['id']) ? $input['id'] : 0);
		if (is_wp_error($post)) {
			return $post;
		}
		$taxonomy = self::public_taxonomy(isset($input['taxonomy']) ? $input['taxonomy'] : 'category');
		if (is_wp_error($taxonomy)) {
			return $taxonomy;
		}
		$object = get_taxonomy($taxonomy);
		if ($object && !current_user_can($object->cap->assign_terms)) {
			return Helper::error('onemcp_forbidden', __('You cannot assign these terms.', 'onemcp'));
		}
		$terms = array();
		foreach (explode(',', (string) (isset($input['terms']) ? $input['terms'] : '')) as $part) {
			$part = trim($part);
			if ($part === '') {
				continue;
			}
			$terms[] = ctype_digit($part) ? (int) $part : $part;
		}
		if ($terms === array()) {
			return Helper::error('onemcp_invalid', __('Provide at least one term.', 'onemcp'));
		}
		$result = wp_set_object_terms($post->ID, $terms, $taxonomy, false);
		if (is_wp_error($result)) {
			return $result;
		}
		return array(
			'id'       => (int) $post->ID,
			'taxonomy' => $taxonomy,
			'terms'    => array_map('intval', (array) $result),
		);
	}

	/**
	 * Read post meta with secrets removed.
	 *
	 * @param array<string,mixed> $input Input.
	 * @return array<string,mixed>|WP_Error
	 */
	 static function get_content_meta($input) {
		$post = Helper::editable_post(isset($input['id']) ? $input['id'] : 0);
		if (is_wp_error($post)) {
			return $post;
		}
		if (!empty($input['key'])) {
			$key = self::meta_key($input['key']);
			if (is_wp_error($key)) {
				return $key;
			}
			$value = get_post_meta($post->ID, $key, true);
			return array(
				'id'    => (int) $post->ID,
				'key'   => $key,
				'value' => self::meta_value($value),
			);
		}
		$all   = get_post_meta($post->ID);
		$items = array();
		if (is_array($all)) {
			foreach ($all as $key => $values) {
				if (self::meta_key($key, false) === '') {
					continue;
				}
				$raw = is_array($values) ? reset($values) : $values;
				$items[] = array(
					'key'   => (string) $key,
					'value' => self::meta_value($raw),
				);
				if (count($items) >= 80) {
					break;
				}
			}
		}
		return array(
			'id'    => (int) $post->ID,
			'items' => $items,
		);
	}

	/**
	 * Write one post meta value.
	 *
	 * @param array<string,mixed> $input Input.
	 * @return array<string,mixed>|WP_Error
	 */
	static function update_content_meta($input) {
		$post = Helper::editable_post(isset($input['id']) ? $input['id'] : 0);
		if (is_wp_error($post)) {
			return $post;
		}
		$key = self::meta_key(isset($input['key']) ? $input['key'] : '');
		if (is_wp_error($key)) {
			return $key;
		}
		$value = isset($input['value']) ? (string) $input['value'] : '';
		$value = str_replace("\0", '', $value);
		if (strlen($value) > 10000) {
			return Helper::error('onemcp_invalid', __('Meta value is too long.', 'onemcp'));
		}
		if (trim($value) === '') {
			delete_post_meta($post->ID, $key);
			return array(
				'id'      => (int) $post->ID,
				'key'     => $key,
				'deleted' => true,
			);
		}
		update_post_meta($post->ID, $key, sanitize_textarea_field($value));
		return array(
			'id'    => (int) $post->ID,
			'key'   => $key,
			'value' => (string) get_post_meta($post->ID, $key, true),
		);
	}

	/**
	 * Navigation menus.
	 *
	 * @param array<string,mixed> $input Input.
	 * @return array<string,mixed>
	 */
	static function list_menus($input) {
		unset($input);
		$items = array();
		foreach (wp_get_nav_menus() as $menu) {
			$items[] = array(
				'id'    => (int) $menu->term_id,
				'name'  => $menu->name,
				'slug'  => $menu->slug,
				'count' => (int) $menu->count,
			);
		}
		return array('items' => $items);
	}

	/**
	 * Items in one menu.
	 *
	 * @param array<string,mixed> $input Input.
	 * @return array<string,mixed>|WP_Error
	 */
	static function list_menu_items($input) {
		$menu = self::menu(isset($input['menu_id']) ? $input['menu_id'] : 0);
		if (is_wp_error($menu)) {
			return $menu;
		}
		$items = array();
		foreach ((array) wp_get_nav_menu_items($menu->term_id) as $item) {
			if (!$item instanceof WP_Post) {
				continue;
			}
			$items[] = array(
				'id'        => (int) $item->ID,
				'title'     => $item->title,
				'type'      => $item->type,
				'url'       => $item->url,
				'parent'    => (int) $item->menu_item_parent,
				'object_id' => (int) $item->object_id,
			);
		}
		return array(
			'menu_id' => (int) $menu->term_id,
			'items'   => $items,
		);
	}

	/**
	 * Create an empty menu.
	 *
	 * @param array<string,mixed> $input Input.
	 * @return array<string,mixed>|WP_Error
	 */
	 static function create_menu($input) {
		$name = Helper::text(isset($input['name']) ? $input['name'] : '', 200);
		if ($name === '') {
			return Helper::error('onemcp_invalid', __('Provide a menu name.', 'onemcp'));
		}
		$menu_id = wp_create_nav_menu($name);
		if (is_wp_error($menu_id)) {
			return $menu_id;
		}
		$menu = wp_get_nav_menu_object($menu_id);
		return array(
			'id'   => (int) $menu_id,
			'name' => $menu ? $menu->name : $name,
			'slug' => $menu ? $menu->slug : '',
		);
	}

	/**
	 * Add a custom link to a menu.
	 *
	 * @param array<string,mixed> $input Input.
	 * @return array<string,mixed>|WP_Error
	 */
	static function add_menu_item($input) {
		$menu = self::menu(isset($input['menu_id']) ? $input['menu_id'] : 0);
		if (is_wp_error($menu)) {
			return $menu;
		}
		$title = Helper::text(isset($input['title']) ? $input['title'] : '', 200);
		$url   = isset($input['url']) ? esc_url_raw((string) $input['url']) : '';
		if ($title === '' || $url === '') {
			return Helper::error('onemcp_invalid', __('Provide a title and a URL.', 'onemcp'));
		}
		$item_id = wp_update_nav_menu_item($menu->term_id, 0, array(
			'menu-item-title'  => $title,
			'menu-item-url'    => $url,
			'menu-item-status' => 'publish',
			'menu-item-type'   => 'custom',
		));
		if (is_wp_error($item_id)) {
			return $item_id;
		}
		return array(
			'id'      => (int) $item_id,
			'menu_id' => (int) $menu->term_id,
			'title'   => $title,
			'url'     => $url,
		);
	}

	/**
	 * A public taxonomy slug.
	 *
	 * @param mixed $value Requested taxonomy.
	 * @return string|WP_Error
	 */
	private static function public_taxonomy($value) {
		$taxonomy = sanitize_key(is_scalar($value) && (string) $value !== '' ? (string) $value : 'category');
		$object   = get_taxonomy($taxonomy);
		if (!$object || empty($object->public)) {
			return Helper::error('onemcp_invalid', __('That taxonomy does not exist.', 'onemcp'));
		}
		return $taxonomy;
	}

	/**
	 * A public taxonomy the user can manage.
	 *
	 * @param mixed $value Requested taxonomy.
	 * @return string|WP_Error
	 */
	private static function taxonomy_name($value) {
		$taxonomy = self::public_taxonomy($value);
		if (is_wp_error($taxonomy)) {
			return $taxonomy;
		}
		$object = get_taxonomy($taxonomy);
		$cap    = ($object && !empty($object->cap->manage_terms)) ? $object->cap->manage_terms : 'manage_categories';
		if (!current_user_can($cap)) {
			return Helper::error('onemcp_forbidden', __('You cannot manage these terms.', 'onemcp'));
		}
		return $taxonomy;
	}

	/**
	 * Term payload.
	 *
	 * @param mixed $term Term.
	 * @return array<string,mixed>|WP_Error
	 */
	private static function term_row($term) {
		if (!$term instanceof WP_Term) {
			return Helper::error('onemcp_not_found', __('Term was not found.', 'onemcp'));
		}
		return array(
			'id'          => (int) $term->term_id,
			'taxonomy'    => $term->taxonomy,
			'name'        => $term->name,
			'slug'        => $term->slug,
			'description' => $term->description,
			'parent'      => (int) $term->parent,
			'count'       => (int) $term->count,
		);
	}

	/**
	 * A meta key that is safe to read or write.
	 *
	 * @param mixed $value  Requested key.
	 * @param bool  $error  True to return an error. False returns an empty string when blocked.
	 * @return string|WP_Error
	 */
	private static function meta_key($value, $error = true) {
		$key = sanitize_key(is_scalar($value) ? (string) $value : '');
		// Keys that start with an underscore are protected post meta. Writing
		// them lets an author change attachment paths, page templates, and
		// other internals that update_post_meta() does not reject by itself.
		$protected = ($key !== '' && $key[0] === '_') || (function_exists('is_protected_meta') && is_protected_meta($key, 'post'));
		$blocked = ($key === '' || strlen($key) > 180 || $protected || preg_match('/password|passwd|secret|token|api[_-]?key|license|private|session|capability|user_level/', $key));
		if ($blocked) {
			if (!$error) {
				return '';
			}
			return Helper::error('onemcp_invalid', __('That meta key cannot be read or changed from here.', 'onemcp'));
		}
		return $key;
	}

	/**
	 * A meta value small enough to return.
	 *
	 * @param mixed $value Stored value.
	 * @return mixed
	 */
	private static function meta_value($value) {
		if (is_string($value) && strlen($value) > 4000) {
			return substr($value, 0, 4000);
		}
		if (is_array($value)) {
			$clean = Helper::redact(Helper::decode_meta($value));
			$json  = wp_json_encode($clean);
			if (is_string($json) && strlen($json) > 4000) {
				return array('truncated' => true);
			}
			return $clean;
		}
		return is_scalar($value) || $value === null ? $value : '';
	}

	/**
	 * One navigation menu.
	 *
	 * @param mixed $id Menu ID.
	 * @return WP_Term|WP_Error
	 */
	private static function menu($id) {
		$menu = wp_get_nav_menu_object(Helper::absint($id));
		if (!$menu instanceof WP_Term) {
			return Helper::error('onemcp_not_found', __('Menu was not found.', 'onemcp'));
		}
		return $menu;
	}

	/**
	 * An attachment the user can edit.
	 *
	 * @param mixed $id Attachment ID.
	 * @return WP_Post|WP_Error
	 */
	private static function attachment($id) {
		$post = get_post(Helper::absint($id));
		if (!$post instanceof WP_Post || $post->post_type !== 'attachment') {
			return Helper::error('onemcp_not_found', __('Media was not found.', 'onemcp'));
		}
		if (!current_user_can('edit_post', $post->ID)) {
			return Helper::error('onemcp_forbidden', __('You cannot edit this media item.', 'onemcp'));
		}
		return $post;
	}

	/**
	 * Media payload.
	 *
	 * @param WP_Post $post Attachment.
	 * @param bool    $full Include caption.
	 * @return array<string,mixed>
	 */
	private static function media_row($post, $full) {
		$row = array(
			'id'    => (int) $post->ID,
			'title' => html_entity_decode(get_the_title($post), ENT_QUOTES, 'UTF-8'),
			'mime'  => $post->post_mime_type,
			'url'   => wp_get_attachment_url($post->ID),
			'alt'   => (string) get_post_meta($post->ID, '_wp_attachment_image_alt', true),
			'date'  => $post->post_date_gmt,
		);
		if ($full) {
			$row['caption'] = $post->post_excerpt;
		}
		return $row;
	}
}
