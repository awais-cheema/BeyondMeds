<?php
/**
 * Easy Digital Downloads products, orders, customers, discounts, and reports.
 *
 * Loaded by the OneMCPPro autoloader. The free plugin calls this class
 * through the onemcp_sources filter.
 *
 * @package OneMCPPro
 */

namespace OneMCPPro;

use ONEMCP\Detector;
use ONEMCP\Helper;
use WP_Query;
use WP_Post;
use WP_User;

if (!defined('ABSPATH')) {
	exit;
}

class EDD {

	/**
	 * Ability definitions. Empty unless EDD is active.
	 *
	 * @return array<int,array<string,mixed>>
	 */
	static function definitions() {
		if (!\ONEMCP\Detector::is_active('edd')) {
			return array();
		}

		$abilities = array(
			Helper::ability(
				'list-downloads',
				__('List downloads', 'onemcp-pro'),
				__('Lists Easy Digital Downloads products.', 'onemcp-pro'),
				'onemcp-pro-edd',
				true,
				array(__CLASS__, 'list_downloads'),
				array(
					'search' => Helper::string_prop(__('Search titles.', 'onemcp-pro'), 120),
					'status' => Helper::string_prop(__('publish, draft, pending, private, or any.', 'onemcp-pro'), 20),
					'limit'  => Helper::int_prop(__('How many downloads to return. Maximum 50.', 'onemcp-pro')),
					'page'   => Helper::int_prop(__('Page number, starting at 1.', 'onemcp-pro')),
				),
				array(),
				array('permission' => array(__CLASS__, 'can_edit_downloads'))
			),
			Helper::ability(
				'get-download',
				__('Get download', 'onemcp-pro'),
				__('Returns one download, including prices, files, and the download limit.', 'onemcp-pro'),
				'onemcp-pro-edd',
				true,
				array(__CLASS__, 'get_download'),
				array('id' => Helper::int_prop(__('Download ID.', 'onemcp-pro'))),
				array('id'),
				array('permission' => array(__CLASS__, 'can_edit_downloads'))
			),
			Helper::ability(
				'create-download',
				__('Create download', 'onemcp-pro'),
				__('Creates an Easy Digital Downloads product. New downloads are drafts unless a status is given.', 'onemcp-pro'),
				'onemcp-pro-edd',
				false,
				array(__CLASS__, 'create_download'),
				array(
					'title'          => Helper::string_prop(__('Download title.', 'onemcp-pro'), 200),
					'content'        => Helper::string_prop(__('Description HTML.', 'onemcp-pro'), 50000),
					'status'         => Helper::string_prop(__('draft, pending, private, or publish.', 'onemcp-pro'), 20),
					'price'          => Helper::string_prop(__('Simple price. Ignored when prices is set.', 'onemcp-pro'), 20),
					'prices'         => self::variable_price_prop(),
					'files'          => self::download_file_prop(),
					'download_limit' => Helper::int_prop(__('Maximum downloads per customer. 0 is unlimited. -1 uses the store default.', 'onemcp-pro')),
				),
				array('title'),
				array('permission' => array(__CLASS__, 'can_edit_downloads'))
			),
			Helper::ability(
				'update-download',
				__('Update download', 'onemcp-pro'),
				__('Updates the title, content, status, simple price, variable prices, files, or download limit of one download.', 'onemcp-pro'),
				'onemcp-pro-edd',
				false,
				array(__CLASS__, 'update_download'),
				array(
					'id'             => Helper::int_prop(__('Download ID.', 'onemcp-pro')),
					'title'          => Helper::string_prop(__('New title.', 'onemcp-pro'), 200),
					'content'        => Helper::string_prop(__('New description HTML.', 'onemcp-pro'), 50000),
					'status'         => Helper::string_prop(__('draft, pending, private, or publish.', 'onemcp-pro'), 20),
					'price'          => Helper::string_prop(__('Simple price. Ignored when prices is set.', 'onemcp-pro'), 20),
					'prices'         => self::variable_price_prop(),
					'files'          => self::download_file_prop(),
					'download_limit' => Helper::int_prop(__('Maximum downloads per customer. 0 is unlimited. -1 uses the store default.', 'onemcp-pro')),
				),
				array('id'),
				array('permission' => array(__CLASS__, 'can_edit_downloads'))
			),
			Helper::ability(
				'list-edd-orders',
				__('List EDD orders', 'onemcp-pro'),
				__('Lists Easy Digital Downloads orders. Search by email or order number, or filter by customer.', 'onemcp-pro'),
				'onemcp-pro-edd',
				true,
				array(__CLASS__, 'list_orders'),
				array(
					'status'      => Helper::string_prop(__('Order status, such as complete or pending.', 'onemcp-pro'), 30),
					'search'      => Helper::string_prop(__('Search by email or order number.', 'onemcp-pro'), 120),
					'customer_id' => Helper::int_prop(__('Limit to one EDD customer ID.', 'onemcp-pro')),
					'limit'       => Helper::int_prop(__('How many orders to return. Maximum 50.', 'onemcp-pro')),
					'page'        => Helper::int_prop(__('Page number, starting at 1.', 'onemcp-pro')),
				),
				array(),
				array('permission' => array(__CLASS__, 'can_manage_orders'))
			),
			Helper::ability(
				'get-edd-order',
				__('Get EDD order', 'onemcp-pro'),
				__('Returns one Easy Digital Downloads order, including purchased items.', 'onemcp-pro'),
				'onemcp-pro-edd',
				true,
				array(__CLASS__, 'get_order'),
				array('id' => Helper::int_prop(__('Order ID.', 'onemcp-pro'))),
				array('id'),
				array('permission' => array(__CLASS__, 'can_manage_orders'))
			),
			Helper::ability(
				'update-edd-order-status',
				__('Update EDD order status', 'onemcp-pro'),
				__('Changes the status of one Easy Digital Downloads order.', 'onemcp-pro'),
				'onemcp-pro-edd',
				false,
				array(__CLASS__, 'update_order_status'),
				array(
					'id'     => Helper::int_prop(__('Order ID.', 'onemcp-pro')),
					'status' => Helper::string_prop(__('complete, pending, processing, refunded, partially_refunded, failed, abandoned, revoked, or on_hold.', 'onemcp-pro'), 30),
				),
				array('id', 'status'),
				array('permission' => array(__CLASS__, 'can_manage_orders'))
			),
			Helper::ability(
				'list-discounts',
				__('List discounts', 'onemcp-pro'),
				__('Lists discount codes, including limits, dates, and product restrictions.', 'onemcp-pro'),
				'onemcp-pro-edd',
				true,
				array(__CLASS__, 'list_discounts'),
				array(
					'limit' => Helper::int_prop(__('How many discounts to return. Maximum 50.', 'onemcp-pro')),
					'page'  => Helper::int_prop(__('Page number, starting at 1.', 'onemcp-pro')),
				),
				array(),
				array('permission' => array(__CLASS__, 'can_manage_orders'))
			),
			Helper::ability(
				'create-discount',
				__('Create discount', 'onemcp-pro'),
				__('Creates an Easy Digital Downloads discount code. New codes are active unless status is inactive.', 'onemcp-pro'),
				'onemcp-pro-edd',
				false,
				array(__CLASS__, 'create_discount'),
				self::discount_properties(),
				array('code', 'amount', 'amount_type'),
				array('permission' => array(__CLASS__, 'can_manage_orders'))
			),
			Helper::ability(
				'update-discount',
				__('Update discount', 'onemcp-pro'),
				__('Updates the code, amount, dates, limits, or product restrictions of one discount.', 'onemcp-pro'),
				'onemcp-pro-edd',
				false,
				array(__CLASS__, 'update_discount'),
				self::discount_properties() + array(
					'id' => Helper::int_prop(__('Discount ID.', 'onemcp-pro')),
				),
				array('id'),
				array('permission' => array(__CLASS__, 'can_manage_orders'))
			),
			Helper::ability(
				'update-download-link-expiration',
				__('Update download link expiration', 'onemcp-pro'),
				__('Sets how many hours a download link stays valid. This is a store-wide setting. 0 means links do not expire.', 'onemcp-pro'),
				'onemcp-pro-edd',
				false,
				array(__CLASS__, 'update_link_expiration'),
				array(
					'hours' => Helper::int_prop(__('Hours before a download link expires. 0 to 8760. 0 means never.', 'onemcp-pro')),
				),
				array('hours'),
				array('permission' => array(__CLASS__, 'can_manage_settings'))
			),
			Helper::ability(
				'update-edd-order',
				__('Update EDD order', 'onemcp-pro'),
				__('Updates the customer email or status of one order. Totals, gateway, and payment key stay unchanged.', 'onemcp-pro'),
				'onemcp-pro-edd',
				false,
				array(__CLASS__, 'update_order'),
				array(
					'id'     => Helper::int_prop(__('Order ID.', 'onemcp-pro')),
					'email'  => Helper::string_prop(__('Customer email.', 'onemcp-pro'), 190),
					'status' => Helper::string_prop(__('complete, pending, processing, refunded, partially_refunded, failed, abandoned, revoked, or on_hold.', 'onemcp-pro'), 30),
				),
				array('id'),
				array('permission' => array(__CLASS__, 'can_manage_orders'))
			),
			Helper::ability(
				'add-edd-order-note',
				__('Add EDD order note', 'onemcp-pro'),
				__('Adds a note to one Easy Digital Downloads order.', 'onemcp-pro'),
				'onemcp-pro-edd',
				false,
				array(__CLASS__, 'add_order_note'),
				array(
					'id'      => Helper::int_prop(__('Order ID.', 'onemcp-pro')),
					'content' => Helper::string_prop(__('Note text.', 'onemcp-pro'), 5000),
				),
				array('id', 'content'),
				array('permission' => array(__CLASS__, 'can_manage_orders'))
			),
			Helper::ability(
				'list-edd-customers',
				__('List EDD customers', 'onemcp-pro'),
				__('Lists Easy Digital Downloads customers.', 'onemcp-pro'),
				'onemcp-pro-edd',
				true,
				array(__CLASS__, 'list_customers'),
				array(
					'search' => Helper::string_prop(__('Search name or email.', 'onemcp-pro'), 120),
					'limit'  => Helper::int_prop(__('How many customers to return. Maximum 50.', 'onemcp-pro')),
					'page'   => Helper::int_prop(__('Page number, starting at 1.', 'onemcp-pro')),
				),
				array(),
				array('permission' => array(__CLASS__, 'can_manage_orders'))
			),
			Helper::ability(
				'get-edd-customer',
				__('Get EDD customer', 'onemcp-pro'),
				__('Returns one customer, including recent purchases and download history.', 'onemcp-pro'),
				'onemcp-pro-edd',
				true,
				array(__CLASS__, 'get_customer'),
				array(
					'id'    => Helper::int_prop(__('Customer ID.', 'onemcp-pro')),
					'email' => Helper::string_prop(__('Customer email. Used when id is omitted.', 'onemcp-pro'), 190),
				),
				array(),
				array('permission' => array(__CLASS__, 'can_manage_orders'))
			),
			Helper::ability(
				'get-edd-sales-summary',
				__('Get EDD sales summary', 'onemcp-pro'),
				__('Shows earnings, order count, and best-selling downloads. Best sellers use at most the latest 100 matching orders.', 'onemcp-pro'),
				'onemcp-pro-edd',
				true,
				array(__CLASS__, 'sales_summary'),
				array(
					'days' => Helper::int_prop(__('How many days to include. 1 to 365. Defaults to 30.', 'onemcp-pro')),
				),
				array(),
				array('permission' => array(__CLASS__, 'can_manage_orders'))
			),
			Helper::ability(
				'list-download-logs',
				__('List download logs', 'onemcp-pro'),
				__('Lists file download activity so you can investigate access. Includes the customer IP address.', 'onemcp-pro'),
				'onemcp-pro-edd',
				true,
				array(__CLASS__, 'list_download_logs'),
				array(
					'product_id'  => Helper::int_prop(__('Limit to one download ID.', 'onemcp-pro')),
					'customer_id' => Helper::int_prop(__('Limit to one customer ID.', 'onemcp-pro')),
					'order_id'    => Helper::int_prop(__('Limit to one order ID.', 'onemcp-pro')),
					'email'       => Helper::string_prop(__('Limit to one customer email.', 'onemcp-pro'), 190),
					'limit'       => Helper::int_prop(__('How many logs to return. Maximum 50.', 'onemcp-pro')),
					'page'        => Helper::int_prop(__('Page number, starting at 1.', 'onemcp-pro')),
				),
				array(),
				array('permission' => array(__CLASS__, 'can_manage_orders'))
			),
			Helper::ability(
				'set-download-access',
				__('Set download access', 'onemcp-pro'),
				__('Grants or revokes download access. Grant without an order creates a manual zero-total completed order and does not email a receipt. Set confirm to GRANT or REVOKE.', 'onemcp-pro'),
				'onemcp-pro-edd',
				false,
				array(__CLASS__, 'set_download_access'),
				array(
					'action'        => Helper::string_prop(__('grant or revoke.', 'onemcp-pro'), 20),
					'confirm'       => Helper::string_prop(__('GRANT when granting, or REVOKE when revoking.', 'onemcp-pro'), 20),
					'order_id'      => Helper::int_prop(__('Existing order ID. Required to revoke.', 'onemcp-pro')),
					'order_item_id' => Helper::int_prop(__('Order item ID to update.', 'onemcp-pro')),
					'customer_id'   => Helper::int_prop(__('Customer ID. Used when granting without an order.', 'onemcp-pro')),
					'email'         => Helper::string_prop(__('Customer email. Used when granting without an order or customer id.', 'onemcp-pro'), 190),
					'download_id'   => Helper::int_prop(__('Download ID.', 'onemcp-pro')),
					'price_id'      => Helper::int_prop(__('Variable price index. Required when the download has variable prices.', 'onemcp-pro')),
				),
				array('action', 'confirm'),
				array('permission' => array(__CLASS__, 'can_manage_orders'), 'destructive' => true)
			),
		);

		return array_merge($abilities, self::extension_abilities());
	}

	/**
	 * Shop staff who can edit downloads.
	 *
	 * @return bool
	 */
	static function can_edit_downloads() {
		return current_user_can('edit_products') || current_user_can('manage_options');
	}

	/**
	 * Shop staff who can manage orders.
	 *
	 * @return bool
	 */
	static function can_manage_orders() {
		return current_user_can('edit_shop_payments') || current_user_can('manage_options');
	}

	/**
	 * Shop staff who can change store settings.
	 *
	 * @return bool
	 */
	static function can_manage_settings() {
		return current_user_can('manage_shop_settings') || current_user_can('manage_options');
	}

	/**
	 * List downloads.
	 *
	 * @param array<string,mixed> $input Input.
	 * @return array<string,mixed>|WP_Error
	 */
	 static function list_downloads($input) {
		if (!post_type_exists('download')) {
			return self::missing();
		}
		$status = isset($input['status']) ? sanitize_key((string) $input['status']) : 'any';
		if (!in_array($status, array('any', 'publish', 'draft', 'pending', 'private'), true)) {
			$status = 'any';
		}
		$limit = Helper::limit(isset($input['limit']) ? $input['limit'] : 20, 20, 50);
		$page  = Helper::page($input);
		$args  = array(
			'post_type'      => 'download',
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
				$items[] = self::download_row($post, false);
			}
		}
		return array(
			'items' => $items,
			'total' => (int) $query->found_posts,
			'page'  => $page,
		);
	}

	/**
	 * One download.
	 *
	 * @param array<string,mixed> $input Input.
	 * @return array<string,mixed>|WP_Error
	 */
	 static function get_download($input) {
		$post = self::download(isset($input['id']) ? $input['id'] : 0);
		if (is_wp_error($post)) {
			return $post;
		}
		return self::download_row($post, true);
	}

	/**
	 * Create a download.
	 *
	 * @param array<string,mixed> $input Input.
	 * @return array<string,mixed>|WP_Error
	 */
	 static function create_download($input) {
		if (!post_type_exists('download')) {
			return self::missing();
		}
		$title = Helper::text(isset($input['title']) ? $input['title'] : '', 200);
		if ($title === '') {
			return Helper::error('onemcp-pro_invalid', __('Provide a download title.', 'onemcp-pro'));
		}
		if (!Helper::user_can_create('download')) {
			return Helper::error('onemcp-pro_forbidden', __('You cannot create downloads.', 'onemcp-pro'));
		}
		$post_id = wp_insert_post(wp_slash(array(
			'post_type'    => 'download',
			'post_title'   => $title,
			'post_content' => isset($input['content']) ? wp_kses_post((string) $input['content']) : '',
			'post_status'  => Helper::post_status(isset($input['status']) ? $input['status'] : 'draft', 'download', 'draft'),
		)), true);
		if (is_wp_error($post_id)) {
			return $post_id;
		}
		$commerce = self::apply_download_commerce($post_id, $input);
		if (is_wp_error($commerce)) {
			wp_delete_post($post_id, true);
			return $commerce;
		}
		$post = get_post($post_id);
		return $post instanceof WP_Post ? self::download_row($post, true) : array('id' => (int) $post_id);
	}

	/**
	 * Update a download.
	 *
	 * @param array<string,mixed> $input Input.
	 * @return array<string,mixed>|WP_Error
	 */
	 static function update_download($input) {
		$post = self::download(isset($input['id']) ? $input['id'] : 0);
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
		if (array_key_exists('status', $input)) {
			$update['post_status'] = Helper::post_status($input['status'], 'download', $post->post_status);
		}
		if (count($update) > 1) {
			$result = wp_update_post(wp_slash($update), true);
			if (is_wp_error($result)) {
				return $result;
			}
		}
		$commerce = self::apply_download_commerce($post->ID, $input);
		if (is_wp_error($commerce)) {
			return $commerce;
		}
		if (count($update) === 1 && !$commerce) {
			return Helper::error('onemcp-pro_invalid', __('Provide a title, content, status, price, prices, files, or download limit.', 'onemcp-pro'));
		}
		$fresh = get_post($post->ID);
		return $fresh instanceof WP_Post ? self::download_row($fresh, true) : array('id' => (int) $post->ID);
	}

	/**
	 * List orders.
	 *
	 * @param array<string,mixed> $input Input.
	 * @return array<string,mixed>|WP_Error
	 */
	 static function list_orders($input) {
		if (!function_exists('edd_get_orders')) {
			return Helper::error('onemcp-pro_unavailable', __('This Easy Digital Downloads version does not expose edd_get_orders.', 'onemcp-pro'));
		}
		$limit  = Helper::limit(isset($input['limit']) ? $input['limit'] : 20, 20, 50);
		$page   = Helper::page($input);
		$args   = array(
			'number' => $limit,
			'offset' => ($page - 1) * $limit,
			'orderby' => 'date_created',
			'order'  => 'DESC',
		);
		if (!empty($input['status'])) {
			$status = self::order_status($input['status']);
			if (is_wp_error($status)) {
				return $status;
			}
			$args['status'] = $status;
		}
		$customer_id = isset($input['customer_id']) ? Helper::absint($input['customer_id']) : 0;
		if ($customer_id > 0) {
			$args['customer_id'] = $customer_id;
		}
		if (!empty($input['search'])) {
			$search = Helper::text($input['search'], 120);
			if (is_email($search)) {
				$args['email'] = $search;
			} else {
				$args['search'] = $search;
			}
		}
		$orders = edd_get_orders($args);
		$items  = array();
		foreach ((array) $orders as $order) {
			$row = self::order_row($order);
			if ($row !== array()) {
				$items[] = $row;
			}
		}
		return array(
			'items' => $items,
			'page'  => $page,
		);
	}

	/**
	 * One order.
	 *
	 * @param array<string,mixed> $input Input.
	 * @return array<string,mixed>|WP_Error
	 */
	static function get_order($input) {
		$order = self::order(isset($input['id']) ? $input['id'] : 0);
		if (is_wp_error($order)) {
			return $order;
		}
		return self::order_row($order, true);
	}

	/**
	 * Change an order status.
	 *
	 * @param array<string,mixed> $input Input.
	 * @return array<string,mixed>|WP_Error
	 */
	 static function update_order_status($input) {
		if (!function_exists('edd_update_order_status')) {
			return Helper::error('onemcp-pro_unavailable', __('This Easy Digital Downloads version cannot update order status.', 'onemcp-pro'));
		}
		$order = self::order(isset($input['id']) ? $input['id'] : 0);
		if (is_wp_error($order)) {
			return $order;
		}
		$status = self::order_status(isset($input['status']) ? $input['status'] : '');
		if (is_wp_error($status)) {
			return $status;
		}
		$updated = edd_update_order_status($order->id, $status);
		if (!$updated) {
			return Helper::error('onemcp-pro_failed', __('The order status was not changed.', 'onemcp-pro'));
		}
		return self::get_order(array('id' => $order->id));
	}

	/**
	 * Discount codes.
	 *
	 * @param array<string,mixed> $input Input.
	 * @return array<string,mixed>|WP_Error
	 */
	 static function list_discounts($input) {
		if (!function_exists('edd_get_discounts')) {
			return Helper::error('onemcp-pro_unavailable', __('This Easy Digital Downloads version does not expose discounts.', 'onemcp-pro'));
		}
		$limit    = Helper::limit(isset($input['limit']) ? $input['limit'] : 20, 20, 50);
		$page     = Helper::page($input);
		$discounts = edd_get_discounts(array(
			'number' => $limit,
			'offset' => ($page - 1) * $limit,
		));
		$items = array();
		foreach ((array) $discounts as $discount) {
			$row = self::discount_row($discount);
			if ($row !== array()) {
				$items[] = $row;
			}
		}
		return array(
			'items' => $items,
			'page'  => $page,
		);
	}

	/**
	 * Download payload.
	 *
	 * @param WP_Post $post Download.
	 * @param bool    $full Include content.
	 * @return array<string,mixed>
	 */
	private static function download_row($post, $full) {
		$price    = get_post_meta($post->ID, 'edd_price', true);
		$variable = get_post_meta($post->ID, 'edd_variable_prices', true);
		if ($price === '' && function_exists('edd_get_download_price')) {
			$price = edd_get_download_price($post->ID);
		}
		$row = array(
			'id'               => (int) $post->ID,
			'title'            => html_entity_decode(get_the_title($post), ENT_QUOTES, 'UTF-8'),
			'status'           => $post->post_status,
			'slug'             => $post->post_name,
			'price'            => is_scalar($price) ? (string) $price : '',
			'variable_prices'  => is_array($variable) && $variable !== array(),
			'link'             => get_permalink($post),
		);
		if ($full) {
			$row['content']                 = Helper::trim_text($post->post_content, 20000);
			$row['prices']                  = self::price_rows($post->ID, is_array($variable) ? $variable : array());
			$row['files']                   = self::file_rows($post->ID);
			$stored_limit                   = get_post_meta($post->ID, '_edd_download_limit', true);
			$row['download_limit']          = ($stored_limit === '' || false === $stored_limit) ? null : (int) $stored_limit;
			$row['download_limit_effective'] = self::effective_download_limit($post->ID);
			$row['link_expiration_hours']   = self::link_expiration_hours();
		}
		return $row;
	}

	/**
	 * Public order fields. Payment keys are omitted.
	 *
	 * @param mixed $order Order object.
	 * @param bool  $full  Include purchased items.
	 * @return array<string,mixed>
	 */
	private static function order_row($order, $full = false) {
		if (!is_object($order)) {
			return array();
		}
		$row = array(
			'id'          => isset($order->id) ? (int) $order->id : 0,
			'number'      => isset($order->order_number) ? (string) $order->order_number : '',
			'status'      => isset($order->status) ? (string) $order->status : '',
			'total'       => isset($order->total) ? (string) $order->total : '',
			'currency'    => isset($order->currency) ? (string) $order->currency : '',
			'email'       => isset($order->email) ? (string) $order->email : '',
			'customer_id' => isset($order->customer_id) ? (int) $order->customer_id : 0,
			'date'        => isset($order->date_created) ? (string) $order->date_created : '',
		);
		if ($full) {
			$row['items'] = self::order_item_rows(isset($order->id) ? (int) $order->id : 0);
		}
		return $row;
	}

	/**
	 * A download the current user can edit.
	 *
	 * @param mixed $id Download ID.
	 * @return WP_Post|WP_Error
	 */
	private static function download($id) {
		$post = get_post(Helper::absint($id));
		if (!$post instanceof WP_Post || $post->post_type !== 'download') {
			return Helper::error('onemcp-pro_not_found', __('Download was not found.', 'onemcp-pro'));
		}
		if (!current_user_can('edit_post', $post->ID)) {
			return Helper::error('onemcp-pro_forbidden', __('You cannot edit this download.', 'onemcp-pro'));
		}
		return $post;
	}

	/**
	 * One EDD order.
	 *
	 * @param mixed $id Order ID.
	 * @return object|WP_Error
	 */
	private static function order($id) {
		if (!function_exists('edd_get_order')) {
			return Helper::error('onemcp-pro_unavailable', __('This Easy Digital Downloads version does not expose edd_get_order.', 'onemcp-pro'));
		}
		$order = edd_get_order(Helper::absint($id));
		if (!is_object($order) || empty($order->id)) {
			return Helper::error('onemcp-pro_not_found', __('Order was not found.', 'onemcp-pro'));
		}
		return $order;
	}

	/**
	 * Allowed order statuses.
	 *
	 * @param mixed $value Requested status.
	 * @return string|WP_Error
	 */
	private static function order_status($value) {
		$status  = sanitize_key(is_scalar($value) ? (string) $value : '');
		$allowed = array('complete', 'pending', 'processing', 'refunded', 'partially_refunded', 'failed', 'abandoned', 'revoked', 'on_hold');
		if (!in_array($status, $allowed, true)) {
			return Helper::error('onemcp-pro_invalid', __('That order status is not available.', 'onemcp-pro'));
		}
		return $status;
	}

	/**
	 * License and subscription abilities, only when those extensions are loaded.
	 *
	 * @return array<int,array<string,mixed>>
	 */
	private static function extension_abilities() {
		$abilities = array();
		if (function_exists('edd_software_licensing')) {
			$abilities[] = Helper::ability(
				'list-licenses',
				__('List licenses', 'onemcp-pro'),
				__('Lists Software Licensing licenses. Available only while that extension is active.', 'onemcp-pro'),
				'onemcp-pro-edd',
				true,
				array(__CLASS__, 'list_licenses'),
				array(
					'search' => Helper::string_prop(__('Search license keys.', 'onemcp-pro'), 120),
					'limit'  => Helper::int_prop(__('How many licenses to return. Maximum 50.', 'onemcp-pro')),
					'page'   => Helper::int_prop(__('Page number, starting at 1.', 'onemcp-pro')),
				),
				array(),
				array('permission' => array(__CLASS__, 'can_manage_orders'))
			);
			$abilities[] = Helper::ability(
				'get-license',
				__('Get license', 'onemcp-pro'),
				__('Returns one Software Licensing license by id or key.', 'onemcp-pro'),
				'onemcp-pro-edd',
				true,
				array(__CLASS__, 'get_license'),
				array(
					'id'  => Helper::int_prop(__('License ID.', 'onemcp-pro')),
					'key' => Helper::string_prop(__('License key. Used when id is omitted.', 'onemcp-pro'), 120),
				),
				array(),
				array('permission' => array(__CLASS__, 'can_manage_orders'))
			);
			$abilities[] = Helper::ability(
				'set-license-status',
				__('Set license status', 'onemcp-pro'),
				__('Changes a Software Licensing license to active, inactive, disabled, or expired. Set confirm to UPDATE.', 'onemcp-pro'),
				'onemcp-pro-edd',
				false,
				array(__CLASS__, 'set_license_status'),
				array(
					'id'      => Helper::int_prop(__('License ID.', 'onemcp-pro')),
					'key'     => Helper::string_prop(__('License key. Used when id is omitted.', 'onemcp-pro'), 120),
					'status'  => Helper::string_prop(__('active, inactive, disabled, or expired.', 'onemcp-pro'), 20),
					'confirm' => Helper::string_prop(__('Must be UPDATE.', 'onemcp-pro'), 20),
				),
				array('status', 'confirm'),
				array('permission' => array(__CLASS__, 'can_manage_orders'), 'destructive' => true)
			);
		}
		if (class_exists('EDD_Subscriptions_DB') && class_exists('EDD_Subscription')) {
			$abilities[] = Helper::ability(
				'list-subscriptions',
				__('List subscriptions', 'onemcp-pro'),
				__('Lists Recurring Payments subscriptions. Available only while that extension is active.', 'onemcp-pro'),
				'onemcp-pro-edd',
				true,
				array(__CLASS__, 'list_subscriptions'),
				array(
					'status'      => Helper::string_prop(__('pending, active, cancelled, expired, trialling, failing, or completed.', 'onemcp-pro'), 20),
					'customer_id' => Helper::int_prop(__('Limit to one customer ID.', 'onemcp-pro')),
					'product_id'  => Helper::int_prop(__('Limit to one download ID.', 'onemcp-pro')),
					'search'      => Helper::string_prop(__('Search subscriptions.', 'onemcp-pro'), 120),
					'limit'       => Helper::int_prop(__('How many subscriptions to return. Maximum 50.', 'onemcp-pro')),
					'page'        => Helper::int_prop(__('Page number, starting at 1.', 'onemcp-pro')),
				),
				array(),
				array('permission' => array(__CLASS__, 'can_manage_orders'))
			);
			$abilities[] = Helper::ability(
				'get-subscription',
				__('Get subscription', 'onemcp-pro'),
				__('Returns one Recurring Payments subscription.', 'onemcp-pro'),
				'onemcp-pro-edd',
				true,
				array(__CLASS__, 'get_subscription'),
				array('id' => Helper::int_prop(__('Subscription ID.', 'onemcp-pro'))),
				array('id'),
				array('permission' => array(__CLASS__, 'can_manage_orders'))
			);
			$abilities[] = Helper::ability(
				'set-subscription-status',
				__('Set subscription status', 'onemcp-pro'),
				__('Changes a subscription status in Easy Digital Downloads. Notifying the gateway is only used when cancelling. Set confirm to UPDATE.', 'onemcp-pro'),
				'onemcp-pro-edd',
				false,
				array(__CLASS__, 'set_subscription_status'),
				array(
					'id'             => Helper::int_prop(__('Subscription ID.', 'onemcp-pro')),
					'status'         => Helper::string_prop(__('pending, active, cancelled, expired, trialling, failing, or completed.', 'onemcp-pro'), 20),
					'notify_gateway' => Helper::bool_prop(__('When true and status is cancelled, also cancel the subscription at the payment gateway.', 'onemcp-pro')),
					'confirm'        => Helper::string_prop(__('Must be UPDATE.', 'onemcp-pro'), 20),
				),
				array('id', 'status', 'confirm'),
				array('permission' => array(__CLASS__, 'can_manage_orders'), 'destructive' => true)
			);
		}
		return $abilities;
	}

	/**
	 * Schema for variable price options.
	 *
	 * @return array<string,mixed>
	 */
	private static function variable_price_prop() {
		return array(
			'type'        => 'array',
			'description' => __('Variable prices. Replaces the price options. Each item has name and amount. Set default true on one item.', 'onemcp-pro'),
			'items'       => array(
				'type'                 => 'object',
				'additionalProperties' => false,
				'properties'           => array(
					'name'    => array('type' => 'string'),
					'amount'  => array('type' => 'string'),
					'default' => array('type' => 'boolean'),
				),
				'required'             => array('name', 'amount'),
			),
		);
	}

	/**
	 * Schema for downloadable files.
	 *
	 * @return array<string,mixed>
	 */
	private static function download_file_prop() {
		return array(
			'type'        => 'array',
			'description' => __('Downloadable files. Replaces the whole file list. An empty list removes every file.', 'onemcp-pro'),
			'items'       => array(
				'type'                 => 'object',
				'additionalProperties' => false,
				'properties'           => array(
					'name'          => array('type' => 'string'),
					'file'          => array('type' => 'string'),
					'attachment_id' => array('type' => 'integer'),
					'price_id'      => array(
						'type'        => 'string',
						'description' => __('all, or the variable price index this file belongs to.', 'onemcp-pro'),
					),
				),
				'required'             => array('name'),
			),
		);
	}

	/**
	 * Fields shared by creating and updating a discount.
	 *
	 * @return array<string,mixed>
	 */
	private static function discount_properties() {
		return array(
			'name'                 => Helper::string_prop(__('Discount name.', 'onemcp-pro'), 200),
			'code'                 => Helper::string_prop(__('Discount code.', 'onemcp-pro'), 50),
			'amount'               => Helper::string_prop(__('Discount amount.', 'onemcp-pro'), 20),
			'amount_type'          => Helper::string_prop(__('percent or flat.', 'onemcp-pro'), 20),
			'status'               => Helper::string_prop(__('active or inactive. Defaults to active on create.', 'onemcp-pro'), 20),
			'max_uses'             => Helper::int_prop(__('Maximum uses. 0 is unlimited.', 'onemcp-pro')),
			'once_per_customer'    => Helper::bool_prop(__('Limit the code to one use per customer.', 'onemcp-pro')),
			'min_charge_amount'    => Helper::string_prop(__('Minimum order amount required.', 'onemcp-pro'), 20),
			'product_ids'          => Helper::int_list_prop(__('Downloads this discount requires.', 'onemcp-pro')),
			'excluded_product_ids' => Helper::int_list_prop(__('Downloads this discount cannot apply to.', 'onemcp-pro')),
			'product_condition'    => Helper::string_prop(__('all or any. Used when more than one download is required.', 'onemcp-pro'), 10),
			'scope'                => Helper::string_prop(__('global or not_global. Defaults to not_global when product_ids is set.', 'onemcp-pro'), 20),
			'start_date'           => Helper::string_prop(__('Start date, YYYY-MM-DD. An empty string clears it.', 'onemcp-pro'), 20),
			'end_date'             => Helper::string_prop(__('End date, YYYY-MM-DD. An empty string clears it.', 'onemcp-pro'), 20),
		);
	}

	/**
	 * Store-wide download link expiration, in hours.
	 *
	 * @param array<string,mixed> $input Input.
	 * @return array<string,mixed>|WP_Error
	 */
	 static function update_link_expiration($input) {
		if (!function_exists('edd_update_option') || !function_exists('edd_get_option')) {
			return Helper::error('onemcp-pro_unavailable', __('This Easy Digital Downloads version cannot update download link expiration.', 'onemcp-pro'));
		}
		if (!array_key_exists('hours', $input)) {
			return Helper::error('onemcp-pro_invalid', __('Provide the number of hours.', 'onemcp-pro'));
		}
		$hours = (int) $input['hours'];
		if ($hours < 0 || $hours > 8760) {
			return Helper::error('onemcp-pro_invalid', __('Hours must be from 0 to 8760. 0 means links do not expire.', 'onemcp-pro'));
		}
		edd_update_option('download_link_expiration', $hours);
		return array(
			'link_expiration_hours' => absint(edd_get_option('download_link_expiration', $hours)),
		);
	}

	/**
	 * Update an order email or status.
	 *
	 * @param array<string,mixed> $input Input.
	 * @return array<string,mixed>|WP_Error
	 */
	static function update_order($input) {
		$order = self::order(isset($input['id']) ? $input['id'] : 0);
		if (is_wp_error($order)) {
			return $order;
		}
		$changed = false;
		if (array_key_exists('email', $input)) {
			if (!function_exists('edd_update_order')) {
				return Helper::error('onemcp-pro_unavailable', __('This Easy Digital Downloads version cannot update an order.', 'onemcp-pro'));
			}
			$email = sanitize_email(is_scalar($input['email']) ? (string) $input['email'] : '');
			if (!is_email($email)) {
				return Helper::error('onemcp-pro_invalid', __('Provide a valid email address.', 'onemcp-pro'));
			}
			$updated = edd_update_order($order->id, array('email' => $email));
			if (is_wp_error($updated)) {
				return $updated;
			}
			$fresh = function_exists('edd_get_order') ? edd_get_order($order->id) : null;
			if (!is_object($fresh) || strtolower((string) $fresh->email) !== strtolower($email)) {
				return Helper::error('onemcp-pro_failed', __('The order email was not changed.', 'onemcp-pro'));
			}
			$order   = $fresh;
			$changed = true;
		}
		if (array_key_exists('status', $input)) {
			$status = self::order_status($input['status']);
			if (is_wp_error($status)) {
				return $status;
			}
			if ((string) $order->status !== $status) {
				if (!function_exists('edd_update_order_status')) {
					return Helper::error('onemcp-pro_unavailable', __('This Easy Digital Downloads version cannot update order status.', 'onemcp-pro'));
				}
				$updated = edd_update_order_status($order->id, $status);
				if (!$updated) {
					return Helper::error('onemcp-pro_failed', __('The order status was not changed.', 'onemcp-pro'));
				}
			} elseif (!$changed) {
				return Helper::error('onemcp-pro_failed', __('The order status was not changed.', 'onemcp-pro'));
			}
			$changed = true;
		}
		if (!$changed) {
			return Helper::error('onemcp-pro_invalid', __('Provide an email or status.', 'onemcp-pro'));
		}
		return self::get_order(array('id' => $order->id));
	}

	/**
	 * Add an order note.
	 *
	 * @param array<string,mixed> $input Input.
	 * @return array<string,mixed>|WP_Error
	 */
	 static function add_order_note($input) {
		if (!function_exists('edd_add_note')) {
			return Helper::error('onemcp-pro_unavailable', __('This Easy Digital Downloads version cannot add order notes.', 'onemcp-pro'));
		}
		$order = self::order(isset($input['id']) ? $input['id'] : 0);
		if (is_wp_error($order)) {
			return $order;
		}
		$content = Helper::text(isset($input['content']) ? $input['content'] : '', 5000);
		if ($content === '') {
			return Helper::error('onemcp-pro_invalid', __('Provide the note text.', 'onemcp-pro'));
		}
		$note_id = edd_add_note(array(
			'object_id'   => $order->id,
			'object_type' => 'order',
			'content'     => $content,
			'user_id'     => get_current_user_id(),
		));
		if (empty($note_id)) {
			return Helper::error('onemcp-pro_failed', __('The order note was not added.', 'onemcp-pro'));
		}
		return array(
			'id'       => (int) $note_id,
			'order_id' => (int) $order->id,
			'content'  => $content,
		);
	}

	/**
	 * Create a discount.
	 *
	 * @param array<string,mixed> $input Input.
	 * @return array<string,mixed>|WP_Error
	 */
	 static function create_discount($input) {
		if (!function_exists('edd_add_discount') || !function_exists('edd_get_discount_by_code')) {
			return Helper::error('onemcp-pro_unavailable', __('This Easy Digital Downloads version cannot create discounts.', 'onemcp-pro'));
		}
		$data = self::discount_payload($input, true);
		if (is_wp_error($data)) {
			return $data;
		}
		$existing = edd_get_discount_by_code($data['code']);
		if (is_object($existing) && !empty($existing->id)) {
			return Helper::error('onemcp-pro_invalid', __('That discount code already exists.', 'onemcp-pro'));
		}
		// The adjustment type is discount. percent and flat belong in amount_type.
		$data['type'] = 'discount';
		if (!isset($data['status'])) {
			$data['status'] = 'active';
		}
		$discount_id = edd_add_discount($data);
		if (is_wp_error($discount_id) || empty($discount_id)) {
			return is_wp_error($discount_id) ? $discount_id : Helper::error('onemcp-pro_failed', __('The discount was not created.', 'onemcp-pro'));
		}
		$discount = function_exists('edd_get_discount') ? edd_get_discount($discount_id) : null;
		return is_object($discount) ? self::discount_row($discount) : array('id' => (int) $discount_id);
	}

	/**
	 * Update a discount.
	 *
	 * @param array<string,mixed> $input Input.
	 * @return array<string,mixed>|WP_Error
	 */
	 static function update_discount($input) {
		if (!function_exists('edd_update_discount') || !function_exists('edd_get_discount')) {
			return Helper::error('onemcp-pro_unavailable', __('This Easy Digital Downloads version cannot update discounts.', 'onemcp-pro'));
		}
		$id       = Helper::absint(isset($input['id']) ? $input['id'] : 0);
		$discount = edd_get_discount($id);
		if (!is_object($discount) || empty($discount->id)) {
			return Helper::error('onemcp-pro_not_found', __('Discount was not found.', 'onemcp-pro'));
		}
		$data = self::discount_payload($input, false);
		if (is_wp_error($data)) {
			return $data;
		}
		if ($data === array()) {
			return Helper::error('onemcp-pro_invalid', __('Provide a field to update.', 'onemcp-pro'));
		}
		if (isset($data['code']) && function_exists('edd_get_discount_by_code')) {
			$existing = edd_get_discount_by_code($data['code']);
			if (is_object($existing) && !empty($existing->id) && (int) $existing->id !== (int) $discount->id) {
				return Helper::error('onemcp-pro_invalid', __('That discount code already exists.', 'onemcp-pro'));
			}
		}
		$columns = array('name', 'code', 'status', 'amount_type', 'amount', 'scope', 'max_uses', 'once_per_customer', 'min_charge_amount', 'start_date', 'end_date');
		$changes_columns = false;
		foreach ($columns as $column) {
			if (array_key_exists($column, $data)) {
				$changes_columns = true;
				break;
			}
		}
		$result = edd_update_discount($discount->id, $data);
		if (is_wp_error($result)) {
			return $result;
		}
		// A meta-only change can report false when no discount columns were written.
		if (false === $result && $changes_columns) {
			return Helper::error('onemcp-pro_failed', __('The discount was not updated.', 'onemcp-pro'));
		}
		$fresh = edd_get_discount($discount->id);
		return is_object($fresh) ? self::discount_row($fresh) : Helper::error('onemcp-pro_failed', __('The discount was not updated.', 'onemcp-pro'));
	}

	/**
	 * List customers.
	 *
	 * @param array<string,mixed> $input Input.
	 * @return array<string,mixed>|WP_Error
	 */
	static function list_customers($input) {
		if (!function_exists('edd_get_customers')) {
			return Helper::error('onemcp-pro_unavailable', __('This Easy Digital Downloads version does not expose customers.', 'onemcp-pro'));
		}
		$limit = Helper::limit(isset($input['limit']) ? $input['limit'] : 20, 20, 50);
		$page  = Helper::page($input);
		$args  = array(
			'number'  => $limit,
			'offset'  => ($page - 1) * $limit,
			'orderby' => 'id',
			'order'   => 'DESC',
		);
		if (!empty($input['search'])) {
			$args['search'] = Helper::text($input['search'], 120);
		}
		$items = array();
		foreach ((array) edd_get_customers($args) as $customer) {
			$row = self::customer_row($customer, false);
			if ($row !== array()) {
				$items[] = $row;
			}
		}
		return array(
			'items' => $items,
			'page'  => $page,
		);
	}

	/**
	 * One customer, with recent purchases and download history.
	 *
	 * @param array<string,mixed> $input Input.
	 * @return array<string,mixed>|WP_Error
	 */
	static function get_customer($input) {
		$customer = self::customer_from_input($input, false);
		if (is_wp_error($customer)) {
			return $customer;
		}
		return self::customer_row($customer, true);
	}

	/**
	 * Earnings, order count, and best sellers.
	 *
	 * @param array<string,mixed> $input Input.
	 * @return array<string,mixed>|WP_Error
	 */	
	 static function sales_summary($input) {
		if (!function_exists('edd_get_orders')) {
			return Helper::error('onemcp-pro_unavailable', __('This Easy Digital Downloads version does not expose orders.', 'onemcp-pro'));
		}
		if (array_key_exists('days', $input)) {
			$days = (int) $input['days'];
			if ($days < 1 || $days > 365) {
				return Helper::error('onemcp-pro_invalid', __('Days must be from 1 to 365.', 'onemcp-pro'));
			}
		} else {
			$days = 30;
		}
		$start_ts = time() - ($days * DAY_IN_SECONDS);
		$start    = gmdate('Y-m-d H:i:s', $start_ts);
		$end      = gmdate('Y-m-d H:i:s');
		$stats    = self::stats_totals($start, $end);
		$sample   = self::recent_sales($start_ts);
		$response = array(
			'days'         => $days,
			'currency'     => function_exists('edd_get_currency') ? (string) edd_get_currency() : '',
			'earnings'     => $stats['earnings'] !== null ? $stats['earnings'] : (string) $sample['earnings'],
			'order_count'  => $stats['count'] !== null ? $stats['count'] : $sample['count'],
			'best_sellers' => $sample['best_sellers'],
			'truncated'    => $sample['truncated'],
		);
		$notes = array();
		if ($stats['earnings'] === null || $stats['count'] === null) {
			$notes[] = __('Earnings or the order count came from the latest matching orders.', 'onemcp-pro');
		}
		if ($sample['truncated']) {
			$notes[] = __('Best sellers use at most the latest 100 complete orders in this range.', 'onemcp-pro');
		}
		if ($notes !== array()) {
			$response['note'] = implode(' ', $notes);
		}
		return $response;
	}

	/**
	 * File download logs.
	 *
	 * @param array<string,mixed> $input Input.
	 * @return array<string,mixed>|WP_Error
	 */
	static function list_download_logs($input) {
		if (!function_exists('edd_get_file_download_logs')) {
			return Helper::error('onemcp-pro_unavailable', __('This Easy Digital Downloads version does not expose download logs.', 'onemcp-pro'));
		}
		$limit = Helper::limit(isset($input['limit']) ? $input['limit'] : 20, 20, 50);
		$page  = Helper::page($input);
		$args  = array(
			'number'  => $limit,
			'offset'  => ($page - 1) * $limit,
			'orderby' => 'id',
			'order'   => 'DESC',
		);
		foreach (array('product_id', 'customer_id', 'order_id') as $key) {
			$id = isset($input[ $key ]) ? Helper::absint($input[ $key ]) : 0;
			if ($id > 0) {
				$args[ $key ] = $id;
			}
		}
		if (!empty($input['email']) && empty($args['customer_id'])) {
			$email = sanitize_email(is_scalar($input['email']) ? (string) $input['email'] : '');
			if (!is_email($email)) {
				return Helper::error('onemcp-pro_invalid', __('Provide a valid email address.', 'onemcp-pro'));
			}
			if (!function_exists('edd_get_customer_by')) {
				return Helper::error('onemcp-pro_unavailable', __('This Easy Digital Downloads version cannot look up a customer by email.', 'onemcp-pro'));
			}
			$customer = edd_get_customer_by('email', $email);
			if (!is_object($customer) || empty($customer->id)) {
				return Helper::error('onemcp-pro_not_found', __('No customer uses that email.', 'onemcp-pro'));
			}
			$args['customer_id'] = (int) $customer->id;
		}
		return array(
			'items' => self::log_rows($args, true),
			'page'  => $page,
		);
	}

	/**
	 * Grant or revoke download access.
	 *
	 * A grant without an order inserts a completed zero-total order directly.
	 * It does not call the status transition that emails a receipt.
	 *
	 * @param array<string,mixed> $input Input.
	 * @return array<string,mixed>|WP_Error
	 */
	public static function set_download_access($input) {
		$action  = sanitize_key(isset($input['action']) ? (string) $input['action'] : '');
		$confirm = isset($input['confirm']) ? (string) $input['confirm'] : '';
		if ($action === 'grant' && $confirm !== 'GRANT') {
			return Helper::error('onemcp-pro_invalid', __('Set confirm to GRANT.', 'onemcp-pro'));
		}
		if ($action === 'revoke' && $confirm !== 'REVOKE') {
			return Helper::error('onemcp-pro_invalid', __('Set confirm to REVOKE.', 'onemcp-pro'));
		}
		if ($action !== 'grant' && $action !== 'revoke') {
			return Helper::error('onemcp-pro_invalid', __('Action must be grant or revoke.', 'onemcp-pro'));
		}
		if (!function_exists('edd_get_order_items') || !function_exists('edd_update_order_item')) {
			return Helper::error('onemcp-pro_unavailable', __('This Easy Digital Downloads version cannot change download access.', 'onemcp-pro'));
		}
		$order_id    = isset($input['order_id']) ? Helper::absint($input['order_id']) : 0;
		$item_id     = isset($input['order_item_id']) ? Helper::absint($input['order_item_id']) : 0;
		$download_id = isset($input['download_id']) ? Helper::absint($input['download_id']) : 0;

		if ($item_id > 0) {
			$item = function_exists('edd_get_order_item') ? edd_get_order_item($item_id) : null;
			if (!is_object($item) || empty($item->id)) {
				return Helper::error('onemcp-pro_not_found', __('Order item was not found.', 'onemcp-pro'));
			}
			if ($order_id > 0 && (int) $item->order_id !== $order_id) {
				return Helper::error('onemcp-pro_invalid', __('That order item is not on this order.', 'onemcp-pro'));
			}
			if ($download_id > 0 && (int) $item->product_id !== $download_id) {
				return Helper::error('onemcp-pro_invalid', __('That order item is not this download.', 'onemcp-pro'));
			}
			$order = self::order($item->order_id);
			if (is_wp_error($order)) {
				return $order;
			}
			if ($action === 'grant') {
				$ready = self::grantable_order($order);
				if (is_wp_error($ready)) {
					return $ready;
				}
			}
			$saved = self::set_item_status($item, $action === 'grant' ? 'complete' : 'revoked');
			if (is_wp_error($saved)) {
				return $saved;
			}
			self::access_note($order->id, $action, isset($item->product_name) ? (string) $item->product_name : '');
			return self::access_result($action, (int) $order->id, false);
		}

		if ($order_id > 0) {
			return self::access_on_order($action, $order_id, $download_id, $input);
		}
		if ($action === 'revoke') {
			return Helper::error('onemcp-pro_invalid', __('Revoking access needs an existing order.', 'onemcp-pro'));
		}
		if ($download_id <= 0) {
			return Helper::error('onemcp-pro_invalid', __('Provide a download ID.', 'onemcp-pro'));
		}
		$download = self::download_post($download_id);
		if (is_wp_error($download)) {
			return $download;
		}
		$price_id = self::access_price_id($download_id, $input, true);
		if (is_wp_error($price_id)) {
			return $price_id;
		}
		$customer = self::customer_from_input($input, true);
		if (is_wp_error($customer)) {
			return $customer;
		}
		$created = self::add_manual_order($customer, $download, $price_id);
		if (is_wp_error($created)) {
			return $created;
		}
		return self::access_result('grant', (int) $created, true);
	}

	/**
	 * List Software Licensing records stored as edd_license posts.
	 *
	 * @param array<string,mixed> $input Input.
	 * @return array<string,mixed>|WP_Error
	 */
	public static function list_licenses($input) {
		if (!function_exists('edd_software_licensing')) {
			return Helper::error('onemcp-pro_unavailable', __('Software Licensing is not active.', 'onemcp-pro'));
		}
		if (!post_type_exists('edd_license')) {
			return Helper::error('onemcp-pro_unavailable', __('This Software Licensing version does not expose edd_license posts. Look up a license by id or key.', 'onemcp-pro'));
		}
		$limit = Helper::limit(isset($input['limit']) ? $input['limit'] : 20, 20, 50);
		$page  = Helper::page($input);
		$args  = array(
			'post_type'      => 'edd_license',
			'post_status'    => 'any',
			'posts_per_page' => $limit,
			'paged'          => $page,
			'orderby'        => 'date',
			'order'          => 'DESC',
		);
		if (!empty($input['search'])) {
			$args['meta_query'] = array(
				array(
					'key'     => '_edd_sl_key',
					'value'   => Helper::text($input['search'], 120),
					'compare' => 'LIKE',
				),
			);
		}
		$query = new WP_Query($args);
		$items = array();
		foreach ($query->posts as $post) {
			if ($post instanceof WP_Post) {
				$items[] = self::license_from_post($post);
			}
		}
		return array(
			'items' => $items,
			'total' => (int) $query->found_posts,
			'page'  => $page,
		);
	}

	/**
	 * One license.
	 *
	 * @param array<string,mixed> $input Input.
	 * @return array<string,mixed>|WP_Error
	 */
	static function get_license($input) {
		$license = self::fetch_license(isset($input['id']) ? $input['id'] : 0, isset($input['key']) ? $input['key'] : '');
		if (is_wp_error($license)) {
			return $license;
		}
		return self::license_row($license);
	}

	/**
	 * Change a license status and read it back.
	 *
	 * @param array<string,mixed> $input Input.
	 * @return array<string,mixed>|WP_Error
	 */
	static function set_license_status($input) {
		if (!isset($input['confirm']) || (string) $input['confirm'] !== 'UPDATE') {
			return Helper::error('onemcp-pro_invalid', __('Set confirm to UPDATE.', 'onemcp-pro'));
		}
		$status = self::choice_status(isset($input['status']) ? $input['status'] : '', array('active', 'inactive', 'disabled', 'expired'));
		if (is_wp_error($status)) {
			return $status;
		}
		$license = self::fetch_license(isset($input['id']) ? $input['id'] : 0, isset($input['key']) ? $input['key'] : '');
		if (is_wp_error($license)) {
			return $license;
		}
		$license_id = self::license_id($license);
		try {
			$saved_locally = false;
			if (method_exists($license, 'set_status')) {
				$license->set_status($status);
				$saved_locally = true;
			}
			if (method_exists($license, 'save')) {
				if (!$saved_locally) {
					$license->status = $status;
				}
				$license->save();
				$saved_locally = true;
			}
			if (!$saved_locally && function_exists('edd_software_licensing')) {
				$licensing = edd_software_licensing();
				if (is_object($licensing) && method_exists($licensing, 'set_license_status')) {
					$licensing->set_license_status($license_id, $status);
					$saved_locally = true;
				}
			}
			if (!$saved_locally) {
				return Helper::error('onemcp-pro_unavailable', __('This Software Licensing version cannot change a license status.', 'onemcp-pro'));
			}
		} catch (\Throwable $exception) {
			return Helper::error('onemcp-pro_failed', __('The license status was not changed.', 'onemcp-pro'));
		}
		$fresh = self::fetch_license($license_id, '');
		if (is_wp_error($fresh)) {
			return $fresh;
		}
		$saved = self::object_value($fresh, array('status'));
		if ((string) $saved !== $status) {
			return Helper::error('onemcp-pro_failed', __('The license status was not changed.', 'onemcp-pro'));
		}
		return self::license_row($fresh);
	}

	/**
	 * List subscriptions.
	 *
	 * @param array<string,mixed> $input Input.
	 * @return array<string,mixed>|WP_Error
	 */
	static function list_subscriptions($input) {
		if (!class_exists('EDD_Subscriptions_DB')) {
			return Helper::error('onemcp-pro_unavailable', __('Recurring Payments is not active.', 'onemcp-pro'));
		}
		$db = new \EDD_Subscriptions_DB();
		if (!is_object($db) || !method_exists($db, 'get_subscriptions')) {
			return Helper::error('onemcp-pro_unavailable', __('This Recurring Payments version cannot list subscriptions.', 'onemcp-pro'));
		}
		$limit = Helper::limit(isset($input['limit']) ? $input['limit'] : 20, 20, 50);
		$page  = Helper::page($input);
		$args  = array(
			'number'  => $limit,
			'offset'  => ($page - 1) * $limit,
			'orderby' => 'id',
			'order'   => 'DESC',
		);
		if (!empty($input['status'])) {
			$status = self::choice_status($input['status'], array('pending', 'active', 'cancelled', 'expired', 'trialling', 'failing', 'completed'));
			if (is_wp_error($status)) {
				return $status;
			}
			$args['status'] = $status;
		}
		$customer_id = isset($input['customer_id']) ? Helper::absint($input['customer_id']) : 0;
		if ($customer_id > 0) {
			$args['customer_id'] = $customer_id;
		}
		$product_id = isset($input['product_id']) ? Helper::absint($input['product_id']) : 0;
		if ($product_id > 0) {
			$args['product_id'] = $product_id;
		}
		if (!empty($input['search'])) {
			$args['search'] = Helper::text($input['search'], 120);
		}
		$items = array();
		try {
			$rows = $db->get_subscriptions($args);
		} catch (\Throwable $exception) {
			return Helper::error('onemcp-pro_failed', __('Subscriptions could not be listed.', 'onemcp-pro'));
		}
		foreach ((array) $rows as $row) {
			$subscription = self::subscription_row(is_array($row) ? (object) $row : $row);
			if ($subscription !== array()) {
				$items[] = $subscription;
			}
		}
		return array(
			'items' => $items,
			'page'  => $page,
		);
	}

	/**
	 * One subscription.
	 *
	 * @param array<string,mixed> $input Input.
	 * @return array<string,mixed>|WP_Error
	 */
	static function get_subscription($input) {
		$subscription = self::load_subscription(isset($input['id']) ? $input['id'] : 0);
		if (is_wp_error($subscription)) {
			return $subscription;
		}
		return self::subscription_row($subscription);
	}

	/**
	 * Change a subscription status.
	 *
	 * @param array<string,mixed> $input Input.
	 * @return array<string,mixed>|WP_Error
	 */
	 static function set_subscription_status($input) {
		if (!isset($input['confirm']) || (string) $input['confirm'] !== 'UPDATE') {
			return Helper::error('onemcp-pro_invalid', __('Set confirm to UPDATE.', 'onemcp-pro'));
		}
		$status = self::choice_status(isset($input['status']) ? $input['status'] : '', array('pending', 'active', 'cancelled', 'expired', 'trialling', 'failing', 'completed'));
		if (is_wp_error($status)) {
			return $status;
		}
		$subscription = self::load_subscription(isset($input['id']) ? $input['id'] : 0);
		if (is_wp_error($subscription)) {
			return $subscription;
		}
		$notify = !empty($input['notify_gateway']);
		try {
			if ($notify) {
				if ($status !== 'cancelled') {
					return Helper::error('onemcp-pro_invalid', __('The payment gateway is contacted only when cancelling a subscription.', 'onemcp-pro'));
				}
				if (!method_exists($subscription, 'cancel')) {
					return Helper::error('onemcp-pro_unavailable', __('This Recurring Payments version cannot cancel a subscription at the gateway.', 'onemcp-pro'));
				}
				$result = $subscription->cancel();
				if (false === $result) {
					return Helper::error('onemcp-pro_failed', __('The subscription was not cancelled at the payment gateway.', 'onemcp-pro'));
				}
			} elseif (method_exists($subscription, 'update')) {
				$subscription->update(array('status' => $status));
			} else {
				return Helper::error('onemcp-pro_unavailable', __('This Recurring Payments version cannot update a subscription.', 'onemcp-pro'));
			}
		} catch (\Throwable $exception) {
			return Helper::error('onemcp-pro_failed', __('The subscription status was not changed.', 'onemcp-pro'));
		}
		$fresh = self::load_subscription($subscription->id);
		if (is_wp_error($fresh)) {
			return $fresh;
		}
		if ((string) $fresh->status !== $status) {
			return Helper::error('onemcp-pro_failed', __('The subscription status was not changed.', 'onemcp-pro'));
		}
		return self::subscription_row($fresh);
	}

	/**
	 * Write price, file, and download-limit changes.
	 *
	 * @param int                 $post_id Download ID.
	 * @param array<string,mixed> $input   Input.
	 * @return bool|WP_Error True when a commerce field was written.
	 */
	private static function apply_download_commerce($post_id, $input) {
		$changed = false;
		if (array_key_exists('prices', $input)) {
			$saved = self::save_prices($post_id, $input['prices']);
			if (is_wp_error($saved)) {
				return $saved;
			}
			$changed = true;
		} elseif (array_key_exists('price', $input)) {
			$saved = self::save_simple_price($post_id, $input['price']);
			if (is_wp_error($saved)) {
				return $saved;
			}
			$changed = true;
		}
		if (array_key_exists('files', $input)) {
			$saved = self::save_files($post_id, $input['files']);
			if (is_wp_error($saved)) {
				return $saved;
			}
			$changed = true;
		}
		if (array_key_exists('download_limit', $input)) {
			$saved = self::save_download_limit($post_id, $input['download_limit']);
			if (is_wp_error($saved)) {
				return $saved;
			}
			$changed = true;
		}
		return $changed;
	}

	/**
	 * Replace variable prices. Indexes start at 1, matching Easy Digital Downloads.
	 *
	 * @param int   $post_id Download ID.
	 * @param mixed $prices  Requested prices.
	 * @return true|WP_Error
	 */
	private static function save_prices($post_id, $prices) {
		if (!is_array($prices) || $prices === array()) {
			return Helper::error('onemcp-pro_invalid', __('Provide at least one variable price.', 'onemcp-pro'));
		}
		$stored         = array();
		$default_id     = 0;
		$default_amount = '';
		$index          = 1;
		foreach ($prices as $price) {
			if (!is_array($price)) {
				return Helper::error('onemcp-pro_invalid', __('Each variable price needs a name and amount.', 'onemcp-pro'));
			}
			$name   = Helper::text(isset($price['name']) ? $price['name'] : '', 100);
			$amount = onemcp-pro_price(isset($price['amount']) ? (string) $price['amount'] : '');
			if ($name === '' || $amount === '' || !is_numeric($amount)) {
				return Helper::error('onemcp-pro_invalid', __('Each variable price needs a name and amount.', 'onemcp-pro'));
			}
			$stored[ $index ] = array(
				'index'  => $index,
				'name'   => $name,
				'amount' => $amount,
			);
			if ($default_id === 0 && !empty($price['default'])) {
				$default_id     = $index;
				$default_amount = $amount;
			}
			if ($default_amount === '') {
				$default_amount = $amount;
			}
			$index++;
		}
		if ($default_id === 0) {
			$default_id     = 1;
			$default_amount = $stored[1]['amount'];
		}
		update_post_meta($post_id, '_variable_pricing', 1);
		update_post_meta($post_id, 'edd_variable_prices', $stored);
		update_post_meta($post_id, '_edd_default_price_id', $default_id);
		update_post_meta($post_id, 'edd_price', $default_amount);
		return true;
	}

	/**
	 * Set the single price. Refuses when variable prices are already stored.
	 *
	 * @param int    $post_id Download ID.
	 * @param mixed  $price   Raw price.
	 * @return true|WP_Error
	 */
	private static function save_simple_price($post_id, $price) {
		$variable = get_post_meta($post_id, 'edd_variable_prices', true);
		if (is_array($variable) && $variable !== array()) {
			return Helper::error('onemcp-pro_invalid', __('This download uses variable prices. The simple price was left unchanged.', 'onemcp-pro'));
		}
		update_post_meta($post_id, 'edd_price', onemcp-pro_price(is_scalar($price) ? (string) $price : ''));
		update_post_meta($post_id, '_variable_pricing', 0);
		return true;
	}

	/**
	 * Replace the downloadable file list.
	 *
	 * @param int   $post_id Download ID.
	 * @param mixed $files   Requested files.
	 * @return true|WP_Error
	 */
	private static function save_files($post_id, $files) {
		if (!is_array($files)) {
			return Helper::error('onemcp-pro_invalid', __('Files must be a list.', 'onemcp-pro'));
		}
		$stored = array();
		$index  = 0;
		foreach ($files as $file) {
			if (!is_array($file)) {
				return Helper::error('onemcp-pro_invalid', __('Each file needs a name and a file URL or attachment.', 'onemcp-pro'));
			}
			$name = Helper::text(isset($file['name']) ? $file['name'] : '', 200);
			if ($name === '') {
				return Helper::error('onemcp-pro_invalid', __('Each file needs a name.', 'onemcp-pro'));
			}
			$attachment_id = isset($file['attachment_id']) ? absint($file['attachment_id']) : 0;
			$url           = isset($file['file']) ? esc_url_raw((string) $file['file']) : '';
			if ($attachment_id > 0) {
				$attachment = get_post($attachment_id);
				if (!$attachment instanceof WP_Post || $attachment->post_type !== 'attachment') {
					return Helper::error('onemcp-pro_not_found', sprintf(
						/* translators: %d: attachment ID. */
						__('File attachment %d was not found.', 'onemcp-pro'),
						$attachment_id
					));
				}
				if ($url === '') {
					$url = (string) wp_get_attachment_url($attachment_id);
				}
			}
			if ($url === '') {
				return Helper::error('onemcp-pro_invalid', __('Each file needs a file URL or attachment.', 'onemcp-pro'));
			}
			$condition = 'all';
			if (isset($file['price_id']) && $file['price_id'] !== '' && $file['price_id'] !== null) {
				$raw = strtolower(trim(is_scalar($file['price_id']) ? (string) $file['price_id'] : ''));
				if ($raw === 'all') {
					$condition = 'all';
				} elseif (preg_match('/^\d+$/', $raw)) {
					$condition = $raw;
				} else {
					return Helper::error('onemcp-pro_invalid', __('File price_id must be all or a variable price index.', 'onemcp-pro'));
				}
			}
			$stored[ $index ] = array(
				'index'         => $index,
				'attachment_id' => $attachment_id,
				'name'          => $name,
				'file'          => $url,
				'condition'     => $condition,
			);
			$index++;
		}
		update_post_meta($post_id, 'edd_download_files', $stored);
		return true;
	}

	/**
	 * Per-customer download limit. Negative values remove the product limit so the store default applies.
	 *
	 * @param int   $post_id Download ID.
	 * @param mixed $limit   Requested limit.
	 * @return true|WP_Error
	 */
	private static function save_download_limit($post_id, $limit) {
		$limit = (int) $limit;
		if ($limit < 0) {
			delete_post_meta($post_id, '_edd_download_limit');
			return true;
		}
		if ($limit > 5000) {
			return Helper::error('onemcp-pro_invalid', __('Download limit cannot be higher than 5000.', 'onemcp-pro'));
		}
		update_post_meta($post_id, '_edd_download_limit', $limit);
		return true;
	}

	/**
	 * Variable prices for a full download payload.
	 *
	 * @param int                  $post_id  Download ID.
	 * @param array<int,mixed>     $variable Stored prices.
	 * @return array<int,array<string,mixed>>
	 */
	private static function price_rows($post_id, $variable) {
		$default = (int) get_post_meta($post_id, '_edd_default_price_id', true);
		$rows    = array();
		foreach ($variable as $key => $price) {
			if (!is_array($price)) {
				continue;
			}
			$index  = isset($price['index']) ? (int) $price['index'] : (int) $key;
			$rows[] = array(
				'id'      => $index,
				'name'    => isset($price['name']) ? (string) $price['name'] : '',
				'amount'  => isset($price['amount']) ? (string) $price['amount'] : '',
				'default' => $index === $default,
			);
		}
		return $rows;
	}

	/**
	 * Files for a full download payload.
	 *
	 * @param int $post_id Download ID.
	 * @return array<int,array<string,mixed>>
	 */
	private static function file_rows($post_id) {
		$files = get_post_meta($post_id, 'edd_download_files', true);
		$rows  = array();
		if (!is_array($files)) {
			return $rows;
		}
		foreach ($files as $key => $file) {
			if (!is_array($file)) {
				continue;
			}
			$rows[] = array(
				'index'         => isset($file['index']) ? (int) $file['index'] : (int) $key,
				'name'          => isset($file['name']) ? (string) $file['name'] : '',
				'file'          => isset($file['file']) ? (string) $file['file'] : '',
				'attachment_id' => isset($file['attachment_id']) ? (int) $file['attachment_id'] : 0,
				'condition'     => isset($file['condition']) ? (string) $file['condition'] : 'all',
			);
		}
		return $rows;
	}

	/**
	 * The effective per-customer limit, when this EDD version can report it.
	 *
	 * @param int $download_id Download ID.
	 * @return int|null
	 */
	private static function effective_download_limit($download_id) {
		if (!function_exists('edd_get_file_download_limit')) {
			return null;
		}
		try {
			$method = new \ReflectionFunction('edd_get_file_download_limit');
			if ($method->getNumberOfRequiredParameters() > 1) {
				return null;
			}
			$result = $method->invoke($download_id);
		} catch (\Throwable $exception) {
			return null;
		}
		return is_numeric($result) ? (int) $result : null;
	}

	/**
	 * Store-wide link expiration in hours, or null when settings are unavailable.
	 *
	 * @return int|null
	 */
	private static function link_expiration_hours() {
		if (!function_exists('edd_get_option')) {
			return null;
		}
		return absint(edd_get_option('download_link_expiration', 24));
	}

	/**
	 * Purchased items. Payment keys are not included.
	 *
	 * @param int $order_id Order ID.
	 * @return array<int,array<string,mixed>>
	 */
	private static function order_item_rows($order_id) {
		if ($order_id <= 0 || !function_exists('edd_get_order_items')) {
			return array();
		}
		$items = edd_get_order_items(array(
			'order_id' => $order_id,
			'number'   => 100,
			'orderby'  => 'id',
			'order'    => 'ASC',
		));
		$rows = array();
		foreach ((array) $items as $item) {
			if (!is_object($item)) {
				continue;
			}
			$rows[] = array(
				'id'           => isset($item->id) ? (int) $item->id : 0,
				'product_id'   => isset($item->product_id) ? (int) $item->product_id : 0,
				'product_name' => isset($item->product_name) ? (string) $item->product_name : '',
				'price_id'     => self::item_price_id($item),
				'quantity'     => isset($item->quantity) ? (int) $item->quantity : 0,
				'amount'       => isset($item->amount) ? (string) $item->amount : '',
				'total'        => isset($item->total) ? (string) $item->total : '',
				'status'       => isset($item->status) ? (string) $item->status : '',
			);
		}
		return $rows;
	}

	/**
	 * Price id, or null when the item has none. Zero is a real price index.
	 *
	 * @param object $item Order item or log.
	 * @return int|null
	 */
	private static function item_price_id($item) {
		if (!is_object($item) || !isset($item->price_id) || $item->price_id === '' || $item->price_id === null) {
			return null;
		}
		return (int) $item->price_id;
	}

	/**
	 * Discount fields safe to show and send back.
	 *
	 * @param object $discount Discount object.
	 * @return array<string,mixed>
	 */
	private static function discount_row($discount) {
		if (!is_object($discount) || empty($discount->id)) {
			return array();
		}
		$id          = (int) $discount->id;
		$amount_type = '';
		if (isset($discount->amount_type) && in_array((string) $discount->amount_type, array('percent', 'flat'), true)) {
			$amount_type = (string) $discount->amount_type;
		} else {
			$type = isset($discount->type) ? (string) $discount->type : '';
			if (in_array($type, array('percent', 'flat'), true)) {
				$amount_type = $type;
			}
		}
		$reqs = array();
		if (function_exists('edd_get_discount_product_reqs')) {
			$raw = edd_get_discount_product_reqs($id);
			if (is_array($raw)) {
				$reqs = array_values(array_filter(array_map('absint', $raw)));
			}
		}
		$excluded = array();
		if (function_exists('edd_get_discount_excluded_products')) {
			$raw = edd_get_discount_excluded_products($id);
			if (is_array($raw)) {
				$excluded = array_values(array_filter(array_map('absint', $raw)));
			}
		}
		$condition = function_exists('edd_get_discount_product_condition') ? (string) edd_get_discount_product_condition($id) : '';
		$scope     = isset($discount->scope) ? (string) $discount->scope : '';
		if ($scope === '' && function_exists('edd_get_discount_scope')) {
			$scope = (string) edd_get_discount_scope($id);
		}
		$raw_type = isset($discount->type) ? (string) $discount->type : '';
		return array(
			'id'                   => $id,
			'name'                 => isset($discount->name) ? (string) $discount->name : '',
			'code'                 => isset($discount->code) ? (string) $discount->code : '',
			'amount'               => isset($discount->amount) ? (string) $discount->amount : '',
			'amount_type'          => $amount_type,
			'type'                 => $amount_type !== '' ? $amount_type : $raw_type,
			'status'               => isset($discount->status) ? (string) $discount->status : '',
			'max_uses'             => isset($discount->max_uses) ? (int) $discount->max_uses : 0,
			'use_count'            => isset($discount->use_count) ? (int) $discount->use_count : 0,
			'once_per_customer'    => !empty($discount->once_per_customer),
			'min_charge_amount'    => isset($discount->min_charge_amount) ? (string) $discount->min_charge_amount : '',
			'scope'                => $scope,
			'start_date'           => self::date_text(isset($discount->start_date) ? $discount->start_date : ''),
			'end_date'             => self::date_text(isset($discount->end_date) ? $discount->end_date : ''),
			'product_ids'          => $reqs,
			'excluded_product_ids' => $excluded,
			'product_condition'    => $condition,
		);
	}

	/**
	 * Discount columns and requirement meta for edd_add_discount or edd_update_discount.
	 *
	 * @param array<string,mixed> $input    Input.
	 * @param bool                $creating True while creating.
	 * @return array<string,mixed>|WP_Error
	 */
	private static function discount_payload($input, $creating) {
		$data = array();
		if (array_key_exists('name', $input)) {
			$name = Helper::text($input['name'], 200);
			if ($name === '') {
				return Helper::error('onemcp-pro_invalid', __('Provide a discount name.', 'onemcp-pro'));
			}
			$data['name'] = $name;
		}
		if ($creating || array_key_exists('code', $input)) {
			$code = self::discount_code(isset($input['code']) ? $input['code'] : '');
			if (is_wp_error($code)) {
				return $code;
			}
			$data['code'] = $code;
			if ($creating && !isset($data['name'])) {
				$data['name'] = $code;
			}
		}
		if ($creating || array_key_exists('amount', $input)) {
			$amount = self::discount_amount(isset($input['amount']) ? $input['amount'] : '');
			if (is_wp_error($amount)) {
				return $amount;
			}
			$data['amount'] = $amount;
		}
		if ($creating || array_key_exists('amount_type', $input)) {
			$raw_type = isset($input['amount_type']) ? $input['amount_type'] : '';
			$type     = sanitize_key(is_scalar($raw_type) ? (string) $raw_type : '');
			if (!in_array($type, array('percent', 'flat'), true)) {
				return Helper::error('onemcp-pro_invalid', __('Amount type must be percent or flat.', 'onemcp-pro'));
			}
			$data['amount_type'] = $type;
		}
		if (array_key_exists('status', $input)) {
			$status = sanitize_key(is_scalar($input['status']) ? (string) $input['status'] : '');
			if (!in_array($status, array('active', 'inactive'), true)) {
				return Helper::error('onemcp-pro_invalid', __('Discount status must be active or inactive.', 'onemcp-pro'));
			}
			$data['status'] = $status;
		}
		if (array_key_exists('max_uses', $input)) {
			$max = (int) $input['max_uses'];
			if ($max < 0) {
				return Helper::error('onemcp-pro_invalid', __('Maximum uses cannot be negative.', 'onemcp-pro'));
			}
			$data['max_uses'] = $max;
		}
		if (array_key_exists('once_per_customer', $input)) {
			$data['once_per_customer'] = !empty($input['once_per_customer']) ? 1 : 0;
		}
		if (array_key_exists('min_charge_amount', $input)) {
			$min = onemcp-pro_price(is_scalar($input['min_charge_amount']) ? (string) $input['min_charge_amount'] : '');
			if ($min === '' || !is_numeric($min) || (float) $min < 0) {
				return Helper::error('onemcp-pro_invalid', __('Minimum charge amount is not a price.', 'onemcp-pro'));
			}
			$data['min_charge_amount'] = $min;
		}
		$product_ids = null;
		if (array_key_exists('product_ids', $input)) {
			if (!is_array($input['product_ids'])) {
				return Helper::error('onemcp-pro_invalid', __('Product IDs must be a list.', 'onemcp-pro'));
			}
			$product_ids = self::download_id_list($input['product_ids']);
			if (is_wp_error($product_ids)) {
				return $product_ids;
			}
			$data['product_reqs'] = $product_ids;
		}
		if (array_key_exists('excluded_product_ids', $input)) {
			if (!is_array($input['excluded_product_ids'])) {
				return Helper::error('onemcp-pro_invalid', __('Excluded product IDs must be a list.', 'onemcp-pro'));
			}
			$excluded = self::download_id_list($input['excluded_product_ids']);
			if (is_wp_error($excluded)) {
				return $excluded;
			}
			$data['excluded_products'] = $excluded;
		}
		if (array_key_exists('product_condition', $input)) {
			$condition = sanitize_key(is_scalar($input['product_condition']) ? (string) $input['product_condition'] : '');
			if (!in_array($condition, array('all', 'any'), true)) {
				return Helper::error('onemcp-pro_invalid', __('Product condition must be all or any.', 'onemcp-pro'));
			}
			$data['product_condition'] = $condition;
		} elseif ($creating && !empty($data['product_reqs'])) {
			$data['product_condition'] = 'all';
		}
		$scope = '';
		if (array_key_exists('scope', $input)) {
			$scope = sanitize_key(is_scalar($input['scope']) ? (string) $input['scope'] : '');
			if (!in_array($scope, array('global', 'not_global'), true)) {
				return Helper::error('onemcp-pro_invalid', __('Scope must be global or not_global.', 'onemcp-pro'));
			}
		}
		if ($scope === 'global' && is_array($product_ids) && $product_ids !== array()) {
			return Helper::error('onemcp-pro_invalid', __('A global discount cannot require specific downloads.', 'onemcp-pro'));
		}
		if ($scope === 'not_global' && is_array($product_ids) && $product_ids === array()) {
			return Helper::error('onemcp-pro_invalid', __('A product-specific discount needs at least one download.', 'onemcp-pro'));
		}
		if ($scope === '' && is_array($product_ids)) {
			$scope = $product_ids === array() ? 'global' : 'not_global';
		}
		if ($creating && $scope === 'not_global' && $product_ids === null) {
			return Helper::error('onemcp-pro_invalid', __('A product-specific discount needs at least one download.', 'onemcp-pro'));
		}
		if ($scope !== '') {
			$data['scope'] = $scope;
		} elseif ($creating) {
			$data['scope'] = 'global';
		}
		foreach (array('start_date' => false, 'end_date' => true) as $key => $end) {
			if (!array_key_exists($key, $input)) {
				continue;
			}
			$date = self::discount_date($input[ $key ], $end);
			if (is_wp_error($date)) {
				return $date;
			}
			if ($date === '' && $creating) {
				continue;
			}
			$data[ $key ] = $date === '' ? null : $date;
		}
		return $data;
	}

	/**
	 * Uppercase discount code.
	 *
	 * @param mixed $value Raw code.
	 * @return string|WP_Error
	 */
	private static function discount_code($value) {
		$raw = strtoupper(preg_replace('/\s+/', '', Helper::text($value, 50)));
		if (!is_string($raw)) {
			$raw = '';
		}
		$code = preg_replace('/[^A-Z0-9_\-]/', '', $raw);
		if (!is_string($code) || $code === '') {
			return Helper::error('onemcp-pro_invalid', __('Provide a discount code.', 'onemcp-pro'));
		}
		return $code;
	}

	/**
	 * Non-negative discount amount.
	 *
	 * @param mixed $value Raw amount.
	 * @return string|WP_Error
	 */
	private static function discount_amount($value) {
		$amount = onemcp-pro_price(is_scalar($value) ? (string) $value : '');
		if ($amount === '' || !is_numeric($amount) || (float) $amount < 0) {
			return Helper::error('onemcp-pro_invalid', __('Provide a discount amount.', 'onemcp-pro'));
		}
		return $amount;
	}

	/**
	 * Discount boundary timestamp. Empty means clear.
	 *
	 * @param mixed $value Raw date.
	 * @param bool  $end   True for the end of that day.
	 * @return string|WP_Error
	 */
	private static function discount_date($value, $end) {
		$date = Helper::date($value);
		if ($date === null) {
			return Helper::error('onemcp-pro_invalid', __('Use a YYYY-MM-DD date.', 'onemcp-pro'));
		}
		if ($date === '') {
			return '';
		}
		return $end ? $date . ' 23:59:59' : $date . ' 00:00:00';
	}

	/**
	 * Download IDs that exist.
	 *
	 * @param mixed $value Raw list.
	 * @return array<int,int>|WP_Error
	 */
	private static function download_id_list($value) {
		$ids = Helper::int_list($value);
		foreach ($ids as $id) {
			$post = get_post($id);
			if (!$post instanceof WP_Post || $post->post_type !== 'download') {
				return Helper::error('onemcp-pro_not_found', sprintf(
					/* translators: %d: download ID. */
					__('Download %d was not found.', 'onemcp-pro'),
					$id
				));
			}
		}
		return $ids;
	}

	/**
	 * Y-m-d, or an empty string when the value is blank.
	 *
	 * @param mixed $value Raw date.
	 * @return string
	 */
	private static function date_text($value) {
		$raw = is_scalar($value) ? trim((string) $value) : '';
		if ($raw === '' || strpos($raw, '0000-00-00') === 0) {
			return '';
		}
		$timestamp = strtotime($raw);
		if (!$timestamp) {
			return '';
		}
		return wp_date('Y-m-d', $timestamp);
	}

	/**
	 * Customer payload.
	 *
	 * @param object $customer Customer.
	 * @param bool   $full     Include purchases and download history.
	 * @return array<string,mixed>
	 */
	private static function customer_row($customer, $full) {
		if (!is_object($customer) || empty($customer->id)) {
			return array();
		}
		$row = array(
			'id'             => (int) $customer->id,
			'email'          => isset($customer->email) ? (string) $customer->email : '',
			'name'           => isset($customer->name) ? (string) $customer->name : '',
			'user_id'        => isset($customer->user_id) ? (int) $customer->user_id : 0,
			'purchase_count' => isset($customer->purchase_count) ? (int) $customer->purchase_count : 0,
			'purchase_value' => isset($customer->purchase_value) ? (string) $customer->purchase_value : '',
			'date'           => isset($customer->date_created) ? (string) $customer->date_created : '',
		);
		if (!$full) {
			return $row;
		}
		$row['orders'] = self::customer_orders((int) $customer->id);
		if (function_exists('edd_get_file_download_logs')) {
			$row['download_logs'] = self::log_rows(array(
				'customer_id' => (int) $customer->id,
				'number'      => 10,
				'orderby'     => 'id',
				'order'       => 'DESC',
			), false);
		} else {
			$row['download_logs']           = array();
			$row['download_logs_available'] = false;
		}
		return $row;
	}

	/**
	 * Recent purchases for one customer.
	 *
	 * @param int $customer_id Customer ID.
	 * @return array<int,array<string,mixed>>
	 */
	private static function customer_orders($customer_id) {
		if (!function_exists('edd_get_orders')) {
			return array();
		}
		$orders = edd_get_orders(array(
			'customer_id' => $customer_id,
			'type'        => 'sale',
			'number'      => 10,
			'orderby'     => 'date_created',
			'order'       => 'DESC',
		));
		$rows = array();
		foreach ((array) $orders as $order) {
			$row = self::order_row($order, false);
			if ($row !== array()) {
				$rows[] = $row;
			}
		}
		return $rows;
	}

	/**
	 * Find a customer, and create one when granting access by email.
	 *
	 * @param array<string,mixed> $input  Input.
	 * @param bool                $create Create a customer for a new email.
	 * @return object|WP_Error
	 */
	private static function customer_from_input($input, $create) {
		$customer_id = isset($input['customer_id']) ? Helper::absint($input['customer_id']) : 0;
		if ($customer_id > 0) {
			if (!function_exists('edd_get_customer')) {
				return Helper::error('onemcp-pro_unavailable', __('This Easy Digital Downloads version does not expose customers.', 'onemcp-pro'));
			}
			$customer = edd_get_customer($customer_id);
			if (!is_object($customer) || empty($customer->id)) {
				return Helper::error('onemcp-pro_not_found', __('Customer was not found.', 'onemcp-pro'));
			}
			return $customer;
		}
		$email_raw = isset($input['email']) ? $input['email'] : '';
		$email     = sanitize_email(is_scalar($email_raw) ? (string) $email_raw : '');
		if ($email === '' || !is_email($email)) {
			return Helper::error('onemcp-pro_invalid', __('Provide a customer id or email.', 'onemcp-pro'));
		}
		if (function_exists('edd_get_customer_by')) {
			$customer = edd_get_customer_by('email', $email);
			if (is_object($customer) && !empty($customer->id)) {
				return $customer;
			}
		}
		if (!$create) {
			return Helper::error('onemcp-pro_not_found', __('Customer was not found.', 'onemcp-pro'));
		}
		if (!class_exists('EDD_Customer')) {
			return Helper::error('onemcp-pro_unavailable', __('This Easy Digital Downloads version cannot create a customer.', 'onemcp-pro'));
		}
		$customer = new \EDD_Customer($email);
		if (!empty($customer->id)) {
			return $customer;
		}
		$user    = get_user_by('email', $email);
		$created = $customer->create(array(
			'email'   => $email,
			'name'    => $email,
			'user_id' => $user instanceof WP_User ? (int) $user->ID : 0,
		));
		if (empty($customer->id) && is_numeric($created) && function_exists('edd_get_customer')) {
			$customer = edd_get_customer((int) $created);
		}
		if (!is_object($customer) || empty($customer->id)) {
			return Helper::error('onemcp-pro_failed', __('The customer could not be created.', 'onemcp-pro'));
		}
		return $customer;
	}

	/**
	 * Store stats for the window, ignoring a stats API that does not match.
	 *
	 * @param string $start Range start, GMT.
	 * @param string $end   Range end, GMT.
	 * @return array{earnings:?string,count:?int}
	 */
	private static function stats_totals($start, $end) {
		$totals = array(
			'earnings' => null,
			'count'    => null,
		);
		if (!class_exists('\EDD\Stats')) {
			return $totals;
		}
		try {
			$stats = new \EDD\Stats();
		} catch (\Throwable $exception) {
			return $totals;
		}
		if (method_exists($stats, 'get_order_earnings')) {
			try {
				$earnings = $stats->get_order_earnings(array(
					'function'     => 'SUM',
					'output'       => 'raw',
					'relative'     => false,
					'revenue_type' => 'net',
					'range'        => 'other',
					'start'        => $start,
					'end'          => $end,
				));
				if (is_numeric($earnings)) {
					$totals['earnings'] = (string) $earnings;
				}
			} catch (\Throwable $exception) {
				$totals['earnings'] = null;
			}
		}
		if (method_exists($stats, 'get_order_count')) {
			try {
				$count = $stats->get_order_count(array(
					'function' => 'COUNT',
					'output'   => 'raw',
					'relative' => false,
					'range'    => 'other',
					'start'    => $start,
					'end'      => $end,
				));
				if (is_numeric($count)) {
					$totals['count'] = (int) $count;
				}
			} catch (\Throwable $exception) {
				$totals['count'] = null;
			}
		}
		return $totals;
	}

	/**
	 * Latest complete orders, filtered to the requested window in PHP.
	 *
	 * @param int $start_ts Window start as a timestamp.
	 * @return array{earnings:float,count:int,best_sellers:array<int,array<string,mixed>>,truncated:bool}
	 */
	private static function recent_sales($start_ts) {
		$orders = edd_get_orders(array(
			'number'     => 100,
			'status__in' => array('complete', 'partially_refunded'),
			'type'       => 'sale',
			'orderby'    => 'date_created',
			'order'      => 'DESC',
		));
		$matched   = array();
		$fetched   = 0;
		$saw_older = false;
		foreach ((array) $orders as $order) {
			if (!is_object($order)) {
				continue;
			}
			$fetched++;
			$status = isset($order->status) ? (string) $order->status : '';
			if (!in_array($status, array('complete', 'partially_refunded'), true)) {
				continue;
			}
			$created = isset($order->date_created) ? strtotime((string) $order->date_created) : false;
			if ($created && $created < $start_ts) {
				$saw_older = true;
				continue;
			}
			$matched[] = $order;
		}
		$earnings = 0.0;
		foreach ($matched as $order) {
			$earnings += isset($order->total) ? (float) $order->total : 0.0;
		}
		return array(
			'earnings'     => $earnings,
			'count'        => count($matched),
			'best_sellers' => self::best_sellers($matched),
			'truncated'    => $fetched >= 100 && !$saw_older,
		);
	}

	/**
	 * Top downloads from the same order sample used for the truncated flag.
	 *
	 * @param array<int,object> $orders Orders.
	 * @return array<int,array<string,mixed>>
	 */
	private static function best_sellers($orders) {
		if (!function_exists('edd_get_order_items')) {
			return array();
		}
		$deliverable = self::deliverable_statuses();
		$sellers     = array();
		foreach ($orders as $order) {
			$items = edd_get_order_items(array(
				'order_id' => (int) $order->id,
				'number'   => 100,
			));
			foreach ((array) $items as $item) {
				if (!is_object($item)) {
					continue;
				}
				$product_id = isset($item->product_id) ? (int) $item->product_id : 0;
				$status     = isset($item->status) ? (string) $item->status : '';
				if ($product_id <= 0 || !in_array($status, $deliverable, true)) {
					continue;
				}
				if (!isset($sellers[ $product_id ])) {
					$sellers[ $product_id ] = array(
						'product_id' => $product_id,
						'name'       => isset($item->product_name) ? (string) $item->product_name : '',
						'quantity'   => 0,
						'total'      => 0.0,
					);
				}
				$sellers[ $product_id ]['quantity'] += isset($item->quantity) ? (int) $item->quantity : 0;
				$sellers[ $product_id ]['total']    += isset($item->total) ? (float) $item->total : 0.0;
			}
		}
		$rows = array_values($sellers);
		usort($rows, function ($left, $right) {
			$quantity = $right['quantity'] <=> $left['quantity'];
			if ($quantity !== 0) {
				return $quantity;
			}
			return $right['total'] <=> $left['total'];
		});
		$rows = array_slice($rows, 0, 10);
		foreach ($rows as $index => $row) {
			$rows[ $index ]['total'] = (string) $row['total'];
		}
		return $rows;
	}

	/**
	 * Download log rows. IP is included only for the log ability.
	 *
	 * @param array<string,mixed> $args       Query args.
	 * @param bool                $include_ip Include IP and user agent.
	 * @return array<int,array<string,mixed>>
	 */
	private static function log_rows($args, $include_ip) {
		if (!function_exists('edd_get_file_download_logs')) {
			return array();
		}
		$items = array();
		foreach ((array) edd_get_file_download_logs($args) as $log) {
			if (!is_object($log)) {
				continue;
			}
			$product_id = isset($log->product_id) ? (int) $log->product_id : 0;
			$file_id    = isset($log->file_id) ? (int) $log->file_id : 0;
			$file_name  = '';
			if ($product_id > 0) {
				$files = get_post_meta($product_id, 'edd_download_files', true);
				if (is_array($files) && isset($files[ $file_id ]['name'])) {
					$file_name = (string) $files[ $file_id ]['name'];
				}
			}
			$row = array(
				'id'           => isset($log->id) ? (int) $log->id : 0,
				'product_id'   => $product_id,
				'product_name' => $product_id > 0 ? html_entity_decode(get_the_title($product_id), ENT_QUOTES, 'UTF-8') : '',
				'file_id'      => $file_id,
				'file_name'    => $file_name,
				'order_id'     => isset($log->order_id) ? (int) $log->order_id : 0,
				'price_id'     => self::item_price_id($log),
				'customer_id'  => isset($log->customer_id) ? (int) $log->customer_id : 0,
				'date'         => isset($log->date_created) ? (string) $log->date_created : '',
			);
			if ($include_ip) {
				$agent              = isset($log->user_agent) ? (string) $log->user_agent : '';
				$row['ip']          = isset($log->ip) ? (string) $log->ip : '';
				$row['user_agent']  = Helper::trim_text($agent, 180);
			}
			$items[] = $row;
		}
		return $items;
	}

	/**
	 * Grant or revoke one download on an existing order.
	 *
	 * @param string              $action      grant or revoke.
	 * @param int                 $order_id    Order ID.
	 * @param int                 $download_id Download ID.
	 * @param array<string,mixed> $input       Input.
	 * @return array<string,mixed>|WP_Error
	 */
	private static function access_on_order($action, $order_id, $download_id, $input) {
		$order = self::order($order_id);
		if (is_wp_error($order)) {
			return $order;
		}
		if ($download_id <= 0) {
			return Helper::error('onemcp-pro_invalid', __('Provide a download ID.', 'onemcp-pro'));
		}
		$download = self::download_post($download_id);
		if (is_wp_error($download)) {
			return $download;
		}
		$price_id = self::access_price_id($download_id, $input, $action === 'grant');
		if (is_wp_error($price_id)) {
			return $price_id;
		}
		$items = self::matching_items($order_id, $download_id, $price_id);
		if ($action === 'revoke') {
			$deliverable = self::deliverable_statuses();
			$targets     = array();
			foreach ($items as $item) {
				if (in_array((string) $item->status, $deliverable, true)) {
					$targets[] = $item;
				}
			}
			if ($targets === array()) {
				return Helper::error('onemcp-pro_not_found', __('No active download access was found on that order.', 'onemcp-pro'));
			}
			foreach ($targets as $item) {
				$saved = self::set_item_status($item, 'revoked');
				if (is_wp_error($saved)) {
					return $saved;
				}
			}
		} else {
			$ready = self::grantable_order($order);
			if (is_wp_error($ready)) {
				return $ready;
			}
			if ($items === array()) {
				$added = self::add_access_item($order_id, $download, $price_id);
				if (is_wp_error($added)) {
					return $added;
				}
			} else {
				foreach ($items as $item) {
					$saved = self::set_item_status($item, 'complete');
					if (is_wp_error($saved)) {
						return $saved;
					}
				}
			}
		}
		self::access_note($order_id, $action, html_entity_decode(get_the_title($download), ENT_QUOTES, 'UTF-8'));
		return self::access_result($action, $order_id, false);
	}

	/**
	 * Access result without the order payment key.
	 *
	 * @param string $action  grant or revoke.
	 * @param int    $order_id Order ID.
	 * @param bool   $created Whether this call created the order.
	 * @return array<string,mixed>
	 */
	private static function access_result($action, $order_id, $created) {
		return array(
			'action'        => $action,
			'order_id'      => $order_id,
			'created_order' => $created,
			'items'         => self::order_item_rows($order_id),
		);
	}

	/**
	 * A download post, without the edit-post capability used by product edits.
	 *
	 * @param int $id Download ID.
	 * @return WP_Post|WP_Error
	 */
	private static function download_post($id) {
		$post = get_post(Helper::absint($id));
		if (!$post instanceof WP_Post || $post->post_type !== 'download') {
			return Helper::error('onemcp-pro_not_found', __('Download was not found.', 'onemcp-pro'));
		}
		return $post;
	}

	/**
	 * Variable price index. Null means the caller did not choose one.
	 *
	 * @param int                 $download_id       Download ID.
	 * @param array<string,mixed> $input             Input.
	 * @param bool                $require_variable  Require a price when the download has variable prices.
	 * @return int|null|WP_Error
	 */
	private static function access_price_id($download_id, $input, $require_variable) {
		$variable     = get_post_meta($download_id, 'edd_variable_prices', true);
		$has_variable = is_array($variable) && $variable !== array();
		if (!$has_variable) {
			return null;
		}
		if (!array_key_exists('price_id', $input)) {
			if ($require_variable) {
				return Helper::error('onemcp-pro_invalid', __('This download has variable prices. Provide price_id.', 'onemcp-pro'));
			}
			return null;
		}
		$price_id = (int) $input['price_id'];
		foreach ($variable as $key => $price) {
			$index = is_array($price) && isset($price['index']) ? (int) $price['index'] : (int) $key;
			if ($index === $price_id) {
				return $price_id;
			}
		}
		return Helper::error('onemcp-pro_invalid', __('That price option was not found.', 'onemcp-pro'));
	}

	/**
	 * Orders that can receive download access.
	 *
	 * @param object $order Order.
	 * @return true|WP_Error
	 */
	private static function grantable_order($order) {
		$status = isset($order->status) ? (string) $order->status : '';
		if (!in_array($status, array('complete', 'partially_refunded'), true)) {
			return Helper::error('onemcp-pro_invalid', __('Access can be granted on a complete order. Omit the order to record a manual zero-total order instead.', 'onemcp-pro'));
		}
		return true;
	}

	/**
	 * Order items for one download, optionally one price.
	 *
	 * @param int      $order_id    Order ID.
	 * @param int      $download_id Download ID.
	 * @param int|null $price_id    Price index, or null for every price.
	 * @return array<int,object>
	 */
	private static function matching_items($order_id, $download_id, $price_id) {
		$items   = edd_get_order_items(array(
			'order_id'   => $order_id,
			'product_id' => $download_id,
			'number'     => 100,
			'orderby'    => 'id',
			'order'      => 'ASC',
		));
		$matched = array();
		foreach ((array) $items as $item) {
			if (!is_object($item) || empty($item->id)) {
				continue;
			}
			if ($price_id === null || self::item_price_id($item) === $price_id) {
				$matched[] = $item;
			}
		}
		return $matched;
	}

	/**
	 * Statuses that still allow the customer to download.
	 *
	 * @return array<int,string>
	 */
	private static function deliverable_statuses() {
		if (function_exists('edd_get_deliverable_order_item_statuses')) {
			$statuses = edd_get_deliverable_order_item_statuses();
			if (is_array($statuses) && $statuses !== array()) {
				return array_map('strval', $statuses);
			}
		}
		return array('complete', 'partially_refunded');
	}

	/**
	 * Set an order item status and read it back.
	 *
	 * @param object $item   Order item.
	 * @param string $status Target status.
	 * @return true|WP_Error
	 */
	private static function set_item_status($item, $status) {
		if (!function_exists('edd_update_order_item')) {
			return Helper::error('onemcp-pro_unavailable', __('This Easy Digital Downloads version cannot update an order item.', 'onemcp-pro'));
		}
		$item_id = isset($item->id) ? (int) $item->id : 0;
		if ($item_id <= 0) {
			return Helper::error('onemcp-pro_not_found', __('Order item was not found.', 'onemcp-pro'));
		}
		if (isset($item->status) && (string) $item->status === $status) {
			return true;
		}
		$updated = edd_update_order_item($item_id, array('status' => $status));
		if (function_exists('edd_get_order_item')) {
			$fresh = edd_get_order_item($item_id);
			if (is_object($fresh) && (string) $fresh->status === $status) {
				return true;
			}
		}
		if (false === $updated) {
			return Helper::error('onemcp-pro_failed', __('The download access was not changed.', 'onemcp-pro'));
		}
		return true;
	}

	/**
	 * Insert a completed zero-total order. This does not email a receipt.
	 *
	 * @param object  $customer Customer.
	 * @param WP_Post $download Download.
	 * @param int|null $price_id Price index.
	 * @return int|WP_Error Order ID.
	 */
	private static function add_manual_order($customer, $download, $price_id) {
		if (!function_exists('edd_add_order') || !function_exists('edd_add_order_item')) {
			return Helper::error('onemcp-pro_unavailable', __('This Easy Digital Downloads version cannot create an order.', 'onemcp-pro'));
		}
		$email = isset($customer->email) ? sanitize_email((string) $customer->email) : '';
		if (!is_email($email)) {
			return Helper::error('onemcp-pro_invalid', __('That customer has no email address.', 'onemcp-pro'));
		}
		if (function_exists('edd_generate_order_payment_key')) {
			$payment_key = edd_generate_order_payment_key($email);
		} else {
			$payment_key = md5(uniqid('onemcp-pro', true));
		}
		$mode = (function_exists('edd_is_test_mode') && edd_is_test_mode()) ? 'test' : 'live';
		$order_id = edd_add_order(array(
			'status'         => 'complete',
			'type'           => 'sale',
			'user_id'        => isset($customer->user_id) ? (int) $customer->user_id : 0,
			'customer_id'    => (int) $customer->id,
			'email'          => $email,
			'gateway'        => 'manual',
			'mode'           => $mode,
			'currency'       => function_exists('edd_get_currency') ? edd_get_currency() : '',
			'payment_key'    => $payment_key,
			'subtotal'       => 0,
			'discount'       => 0,
			'tax'            => 0,
			'total'          => 0,
			'date_completed' => gmdate('Y-m-d H:i:s'),
		));
		if (empty($order_id)) {
			return Helper::error('onemcp-pro_failed', __('The download access was not granted.', 'onemcp-pro'));
		}
		$item_id = self::add_access_item((int) $order_id, $download, $price_id);
		if (is_wp_error($item_id)) {
			if (function_exists('edd_delete_order')) {
				edd_delete_order($order_id);
			}
			return $item_id;
		}
		try {
			if (method_exists($customer, 'attach_payment')) {
				$customer->attach_payment($order_id, false);
			}
			if (method_exists($customer, 'recalculate_stats')) {
				$customer->recalculate_stats();
			}
		} catch (\Throwable $exception) {
			// The order and item exist. A stats failure must not remove the grant.
		}
		self::access_note((int) $order_id, 'grant', html_entity_decode(get_the_title($download), ENT_QUOTES, 'UTF-8'));
		return (int) $order_id;
	}

	/**
	 * Add a zero-total download item.
	 *
	 * @param int      $order_id Order ID.
	 * @param WP_Post  $download Download.
	 * @param int|null $price_id Price index.
	 * @return int|WP_Error Item ID.
	 */
	private static function add_access_item($order_id, $download, $price_id) {
		if (!function_exists('edd_add_order_item')) {
			return Helper::error('onemcp-pro_unavailable', __('This Easy Digital Downloads version cannot add an order item.', 'onemcp-pro'));
		}
		$name = html_entity_decode(get_the_title($download), ENT_QUOTES, 'UTF-8');
		if ($price_id !== null && function_exists('edd_get_price_option_name')) {
			$option = edd_get_price_option_name($download->ID, $price_id);
			if (is_string($option) && $option !== '') {
				$name .= ' - ' . $option;
			}
		}
		$item_id = edd_add_order_item(array(
			'order_id'     => $order_id,
			'product_id'   => (int) $download->ID,
			'product_name' => $name,
			'price_id'     => $price_id,
			'quantity'     => 1,
			'amount'       => 0,
			'subtotal'     => 0,
			'discount'     => 0,
			'tax'          => 0,
			'total'        => 0,
			'status'       => 'complete',
			'type'         => 'download',
		));
		if (empty($item_id)) {
			return Helper::error('onemcp-pro_failed', __('The download access was not granted.', 'onemcp-pro'));
		}
		return (int) $item_id;
	}

	/**
	 * Record who changed access. Failure to write the note does not undo the change.
	 *
	 * @param int    $order_id Order ID.
	 * @param string $action   grant or revoke.
	 * @param string $name     Download name.
	 * @return void
	 */
	private static function access_note($order_id, $action, $name) {
		if (!function_exists('edd_add_note')) {
			return;
		}
		$user = wp_get_current_user();
		$who  = ($user instanceof WP_User && $user->display_name !== '') ? $user->display_name : __('a shop manager', 'onemcp-pro');
		if ($action === 'revoke') {
			$content = sprintf(
				/* translators: 1: download name, 2: user display name. */
				__('Download access for %1$s was revoked by %2$s.', 'onemcp-pro'),
				$name,
				$who
			);
		} else {
			$content = sprintf(
				/* translators: 1: download name, 2: user display name. */
				__('Download access for %1$s was granted by %2$s. No receipt was sent.', 'onemcp-pro'),
				$name,
				$who
			);
		}
		edd_add_note(array(
			'object_id'   => $order_id,
			'object_type' => 'order',
			'content'     => $content,
			'user_id'     => get_current_user_id(),
		));
	}

	/**
	 * License row from a post when the license object cannot be loaded.
	 *
	 * @param WP_Post $post License post.
	 * @return array<string,mixed>
	 */
	private static function license_from_post($post) {
		$license = self::fetch_license($post->ID, '');
		if (!is_wp_error($license)) {
			return self::license_row($license);
		}
		$expiration = get_post_meta($post->ID, '_edd_sl_expiration', true);
		$when       = is_numeric($expiration) && (int) $expiration > 0 ? gmdate('Y-m-d', (int) $expiration) : self::date_text($expiration);
		return array(
			'id'               => (int) $post->ID,
			'key'              => (string) get_post_meta($post->ID, '_edd_sl_key', true),
			'status'           => (string) get_post_meta($post->ID, '_edd_sl_status', true),
			'download_id'      => (int) get_post_meta($post->ID, '_edd_sl_download_id', true),
			'order_id'         => (int) get_post_meta($post->ID, '_edd_sl_payment_id', true),
			'expiration'       => $when,
			'activation_limit' => (int) get_post_meta($post->ID, '_edd_sl_limit', true),
		);
	}

	/**
	 * Load one license by id or key.
	 *
	 * @param mixed $id  License ID.
	 * @param mixed $key License key.
	 * @return object|WP_Error
	 */
	private static function fetch_license($id, $key) {
		if (!function_exists('edd_software_licensing')) {
			return Helper::error('onemcp-pro_unavailable', __('Software Licensing is not active.', 'onemcp-pro'));
		}
		$licensing = edd_software_licensing();
		if (!is_object($licensing) || !method_exists($licensing, 'get_license')) {
			return Helper::error('onemcp-pro_unavailable', __('This Software Licensing version cannot read a license.', 'onemcp-pro'));
		}
		$key = is_scalar($key) ? trim((string) $key) : '';
		$id  = Helper::absint($id);
		if ($id <= 0 && $key === '') {
			return Helper::error('onemcp-pro_invalid', __('Provide a license id or key.', 'onemcp-pro'));
		}
		try {
			if ($key !== '' && $id <= 0) {
				$license = self::license_by_key($licensing, $key);
			} else {
				$license = self::invoke_get_license($licensing, $id, false);
			}
		} catch (\Throwable $exception) {
			return Helper::error('onemcp-pro_failed', __('The license could not be read.', 'onemcp-pro'));
		}
		if (!is_object($license) || self::license_id($license) <= 0) {
			return Helper::error('onemcp-pro_not_found', __('License was not found.', 'onemcp-pro'));
		}
		return $license;
	}

	/**
	 * Find a license from its key without passing an extra argument to an older method.
	 *
	 * @param object $licensing Licensing service.
	 * @param string $key       License key.
	 * @return mixed
	 */
	private static function license_by_key($licensing, $key) {
		if (method_exists($licensing, 'get_license_by_key')) {
			$found = $licensing->get_license_by_key($key);
			if (is_object($found) && self::license_id($found) > 0) {
				return $found;
			}
			if (is_numeric($found) && (int) $found > 0) {
				return self::invoke_get_license($licensing, (int) $found, false);
			}
		}
		return self::invoke_get_license($licensing, $key, true);
	}

	/**
	 * Call get_license with the number of arguments that method declares.
	 *
	 * @param object     $licensing Licensing service.
	 * @param int|string $value     License ID or key.
	 * @param bool       $by_key    True when $value is a key.
	 * @return mixed
	 */
	private static function invoke_get_license($licensing, $value, $by_key) {
		$method = new \ReflectionMethod($licensing, 'get_license');
		if ($by_key && $method->getNumberOfParameters() >= 2) {
			return $licensing->get_license($value, true);
		}
		return $licensing->get_license($value);
	}

	/**
	 * License fields. Gateway secrets are not part of this object.
	 *
	 * @param object $license License.
	 * @return array<string,mixed>
	 */
	private static function license_row($license) {
		$id         = self::license_id($license);
		$expiration = self::object_value($license, array('expiration', 'expires'));
		if (is_numeric($expiration) && (int) $expiration > 0) {
			$expiration = gmdate('Y-m-d', (int) $expiration);
		} elseif (is_string($expiration) && strtolower($expiration) === 'lifetime') {
			$expiration = 'lifetime';
		} else {
			$expiration = self::date_text($expiration);
		}
		$lifetime = false;
		if (is_object($license) && method_exists($license, 'is_lifetime')) {
			try {
				$lifetime = (bool) $license->is_lifetime();
			} catch (\Throwable $exception) {
				$lifetime = false;
			}
		} elseif (is_object($license) && isset($license->is_lifetime)) {
			$lifetime = (bool) $license->is_lifetime;
		}
		$limit = self::object_value($license, array('activation_limit', 'license_limit', 'limit'));
		$count = self::object_value($license, array('activation_count', 'site_count'));
		return array(
			'id'               => $id,
			'key'              => (string) self::object_value($license, array('license_key', 'key')),
			'status'           => (string) self::object_value($license, array('status')),
			'download_id'      => (int) self::object_value($license, array('download_id')),
			'order_id'         => (int) self::object_value($license, array('order_id', 'payment_id')),
			'customer_id'      => (int) self::object_value($license, array('customer_id')),
			'expiration'       => $expiration,
			'is_lifetime'      => $lifetime || $expiration === 'lifetime',
			'activation_limit' => $limit === null ? 0 : (int) $limit,
			'activation_count' => $count === null ? 0 : (int) $count,
		);
	}

	/**
	 * Numeric license ID.
	 *
	 * @param object $license License.
	 * @return int
	 */
	private static function license_id($license) {
		$value = self::object_value($license, array('ID', 'id'));
		return $value === null ? 0 : (int) $value;
	}

	/**
	 * First populated object property.
	 *
	 * @param object            $object Object.
	 * @param array<int,string> $keys   Property names.
	 * @return mixed|null
	 */
	private static function object_value($object, $keys) {
		if (!is_object($object)) {
			return null;
		}
		foreach ($keys as $key) {
			if (isset($object->{$key}) && $object->{$key} !== '' && $object->{$key} !== null) {
				return $object->{$key};
			}
		}
		return null;
	}

	/**
	 * One allowed status.
	 *
	 * @param mixed             $value   Requested status.
	 * @param array<int,string> $allowed Allowed values.
	 * @return string|WP_Error
	 */
	private static function choice_status($value, $allowed) {
		$status = sanitize_key(is_scalar($value) ? (string) $value : '');
		if (!in_array($status, $allowed, true)) {
			return Helper::error('onemcp-pro_invalid', __('That status is not available.', 'onemcp-pro'));
		}
		return $status;
	}

	/**
	 * Load a subscription. An empty object means the ID was not found.
	 *
	 * @param mixed $id Subscription ID.
	 * @return object|WP_Error
	 */
	private static function load_subscription($id) {
		if (!class_exists('EDD_Subscription')) {
			return Helper::error('onemcp-pro_unavailable', __('Recurring Payments is not active.', 'onemcp-pro'));
		}
		try {
			$subscription = new \EDD_Subscription(Helper::absint($id));
		} catch (\Throwable $exception) {
			return Helper::error('onemcp-pro_failed', __('The subscription could not be read.', 'onemcp-pro'));
		}
		if (!is_object($subscription) || empty($subscription->id)) {
			return Helper::error('onemcp-pro_not_found', __('Subscription was not found.', 'onemcp-pro'));
		}
		return $subscription;
	}

	/**
	 * Subscription fields. Profile and gateway transaction secrets are omitted.
	 *
	 * @param mixed $subscription Subscription object.
	 * @return array<string,mixed>
	 */
	private static function subscription_row($subscription) {
		if (!is_object($subscription) || empty($subscription->id)) {
			return array();
		}
		return array(
			'id'               => (int) $subscription->id,
			'customer_id'      => isset($subscription->customer_id) ? (int) $subscription->customer_id : 0,
			'product_id'       => isset($subscription->product_id) ? (int) $subscription->product_id : 0,
			'status'           => isset($subscription->status) ? (string) $subscription->status : '',
			'period'           => isset($subscription->period) ? (string) $subscription->period : '',
			'initial_amount'   => isset($subscription->initial_amount) ? (string) $subscription->initial_amount : '',
			'recurring_amount' => isset($subscription->recurring_amount) ? (string) $subscription->recurring_amount : '',
			'bill_times'       => isset($subscription->bill_times) ? (int) $subscription->bill_times : 0,
			'expiration'       => isset($subscription->expiration) ? (string) $subscription->expiration : '',
			'created'          => isset($subscription->created) ? (string) $subscription->created : '',
			'parent_payment_id' => isset($subscription->parent_payment_id) ? (int) $subscription->parent_payment_id : 0,
			'gateway'          => isset($subscription->gateway) ? (string) $subscription->gateway : '',
		);
	}

	/**
	 * EDD is not loaded.
	 *
	 * @return WP_Error
	 */
	private static function missing() {
		return Helper::error('onemcp-pro_unavailable', __('Easy Digital Downloads is not available.', 'onemcp-pro'));
	}
}

/**
 * Decimal string without requiring WooCommerce.
 *
 * @param string $value Raw price.
 * @return string
 */
function onemcp_pro_price($value) {
	if (function_exists('wc_format_decimal')) {
		return wc_format_decimal($value);
	}
	$value = preg_replace('/[^0-9.\-]/', '', (string) $value);
	return is_string($value) ? $value : '';
}
