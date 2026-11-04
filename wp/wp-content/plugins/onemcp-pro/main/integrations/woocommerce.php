<?php
/**
 * WooCommerce products, variations, orders, customers, coupons, and reports.
 *
 * @package OneMCPPro
 */

namespace OneMCPPro;

use ONEMCP\Detector;
use ONEMCP\Helper;
use WP_Query;
use WP_User_Query;
use WP_Post;
use WP_User;
use WP_Term;
use WP_Comment;
use WC_Product;
use WC_Product_Simple;
use WC_Product_Variation;
use WC_Product_Attribute;
use WC_Product_Variable;
use WC_Order;
use WC_Coupon;
use WC_Shipping_Zone;
use WC_Shipping_Zones;
use WC_Tax;
use DateTimeInterface;

if (!defined('ABSPATH')) {
	exit;
}

class WooCommerce {

	/**
	 * Ability definitions. Empty unless WooCommerce is active.
	 *
	 * @return array<int,array<string,mixed>>
	 */
	 static function definitions() {
		if (!\ONEMCP\Detector::is_active('woocommerce')) {
			return array();
		}

		$paging = array(
			'search' => Helper::string_prop(__('Search text.', 'onemcp-pro'), 120),
			'limit'  => Helper::int_prop(__('How many items to return. Maximum 50.', 'onemcp-pro')),
			'page'   => Helper::int_prop(__('Page number, starting at 1.', 'onemcp-pro')),
		);

		$abilities = array(
			Helper::ability(
				'list-products',
				__('List products', 'onemcp-pro'),
				__('Lists WooCommerce products. Filter by status, stock status, or low stock.', 'onemcp-pro'),
				'onemcp-woocommerce',
				true,
				array(__CLASS__, 'list_products'),
				$paging + array(
					'status'       => Helper::string_prop(__('Product status. Defaults to any.', 'onemcp-pro'), 20),
					'stock_status' => Helper::string_prop(__('instock, outofstock, or onbackorder.', 'onemcp-pro'), 20),
					'low_stock'    => Helper::bool_prop(__('Only products at or below the store low-stock threshold.', 'onemcp-pro')),
				),
				array(),
				array('permission' => array(__CLASS__, 'can_manage'))
			),
			Helper::ability(
				'get-product',
				__('Get product', 'onemcp-pro'),
				__('Returns one WooCommerce product, including price, stock, and description.', 'onemcp-pro'),
				'onemcp-woocommerce',
				true,
				array(__CLASS__, 'get_product'),
				array('id' => Helper::int_prop(__('Product ID.', 'onemcp-pro'))),
				array('id'),
				array('permission' => array(__CLASS__, 'can_manage'))
			),
			Helper::ability(
				'create-product',
				__('Create product', 'onemcp-pro'),
				__('Creates a simple WooCommerce product. New products are drafts unless a status is given.', 'onemcp-pro'),
				'onemcp-woocommerce',
				false,
				array(__CLASS__, 'create_product'),
				self::product_properties(),
				array('name'),
				array('permission' => array(__CLASS__, 'can_manage'))
			),
			Helper::ability(
				'update-product',
				__('Update product', 'onemcp-pro'),
				__('Updates the WooCommerce product fields you send.', 'onemcp-pro'),
				'onemcp-woocommerce',
				false,
				array(__CLASS__, 'update_product'),
				self::product_properties() + array(
					'id' => Helper::int_prop(__('Product ID.', 'onemcp-pro')),
				),
				array('id'),
				array('permission' => array(__CLASS__, 'can_manage'))
			),
			Helper::ability(
				'list-orders',
				__('List orders', 'onemcp-pro'),
				__('Lists WooCommerce orders. Search by name, email, or order number, or filter by customer.', 'onemcp-pro'),
				'onemcp-woocommerce',
				true,
				array(__CLASS__, 'list_orders'),
				array(
					'status'      => Helper::string_prop(__('Order status, such as processing or completed.', 'onemcp-pro'), 30),
					'search'      => Helper::string_prop(__('Search billing name, email, or order number.', 'onemcp-pro'), 120),
					'customer_id' => Helper::int_prop(__('Limit to one customer user ID.', 'onemcp-pro')),
					'limit'       => Helper::int_prop(__('How many orders to return. Maximum 50.', 'onemcp-pro')),
					'page'        => Helper::int_prop(__('Page number, starting at 1.', 'onemcp-pro')),
				),
				array(),
				array('permission' => array(__CLASS__, 'can_manage'))
			),
			Helper::ability(
				'get-order',
				__('Get order', 'onemcp-pro'),
				__('Returns one WooCommerce order, including line items.', 'onemcp-pro'),
				'onemcp-woocommerce',
				true,
				array(__CLASS__, 'get_order'),
				array('id' => Helper::int_prop(__('Order ID.', 'onemcp-pro'))),
				array('id'),
				array('permission' => array(__CLASS__, 'can_manage'))
			),
			Helper::ability(
				'update-order-status',
				__('Update order status', 'onemcp-pro'),
				__('Changes the status of one WooCommerce order.', 'onemcp-pro'),
				'onemcp-woocommerce',
				false,
				array(__CLASS__, 'update_order_status'),
				array(
					'id'     => Helper::int_prop(__('Order ID.', 'onemcp-pro')),
					'status' => Helper::string_prop(__('New status, such as processing, completed, or cancelled.', 'onemcp-pro'), 30),
				),
				array('id', 'status'),
				array('permission' => array(__CLASS__, 'can_manage'))
			),
			Helper::ability(
				'list-coupons',
				__('List coupons', 'onemcp-pro'),
				__('Lists WooCommerce coupons.', 'onemcp-pro'),
				'onemcp-woocommerce',
				true,
				array(__CLASS__, 'list_coupons'),
				$paging,
				array(),
				array('permission' => array(__CLASS__, 'can_manage'))
			),
			Helper::ability(
				'create-coupon',
				__('Create coupon', 'onemcp-pro'),
				__('Creates a WooCommerce coupon.', 'onemcp-pro'),
				'onemcp-woocommerce',
				false,
				array(__CLASS__, 'create_coupon'),
				array(
					'code'          => Helper::string_prop(__('Coupon code.', 'onemcp-pro'), 40),
					'amount'        => Helper::string_prop(__('Discount amount.', 'onemcp-pro'), 20),
					'discount_type' => Helper::string_prop(__('percent, fixed_cart, or fixed_product.', 'onemcp-pro'), 20),
					'description'   => Helper::string_prop(__('Coupon description.', 'onemcp-pro'), 200),
					'usage_limit'         => Helper::int_prop(__('Maximum uses. Omit for no limit.', 'onemcp-pro')),
					'usage_limit_per_user' => Helper::int_prop(__('Maximum uses per customer. Omit for no limit.', 'onemcp-pro')),
					'date_expires'    => Helper::string_prop(__('Expiry date, YYYY-MM-DD. Omit for no expiry.', 'onemcp-pro'), 20),
					'free_shipping'   => Helper::bool_prop(__('Grant free shipping.', 'onemcp-pro')),
				),
				array('code', 'amount'),
				array('permission' => array(__CLASS__, 'can_manage'))
			),
			Helper::ability(
				'update-coupon',
				__('Update coupon', 'onemcp-pro'),
				__('Updates the amount, type, description, expiry, or limits of one coupon.', 'onemcp-pro'),
				'onemcp-woocommerce',
				false,
				array(__CLASS__, 'update_coupon'),
				array(
					'id'            => Helper::int_prop(__('Coupon ID.', 'onemcp-pro')),
					'amount'        => Helper::string_prop(__('Discount amount.', 'onemcp-pro'), 20),
					'discount_type' => Helper::string_prop(__('percent, fixed_cart, or fixed_product.', 'onemcp-pro'), 20),
					'description'   => Helper::string_prop(__('Coupon description.', 'onemcp-pro'), 200),
					'usage_limit'         => Helper::int_prop(__('Maximum uses. Use 0 for no limit.', 'onemcp-pro')),
					'usage_limit_per_user' => Helper::int_prop(__('Maximum uses per customer. Use 0 for no limit.', 'onemcp-pro')),
					'date_expires'    => Helper::string_prop(__('Expiry date, YYYY-MM-DD. Send an empty string to clear it.', 'onemcp-pro'), 20),
					'free_shipping'   => Helper::bool_prop(__('Grant free shipping.', 'onemcp-pro')),
				),
				array('id'),
				array('permission' => array(__CLASS__, 'can_manage'))
			),
			Helper::ability(
				'delete-coupon',
				__('Delete coupon', 'onemcp-pro'),
				__('Moves one coupon to the trash. Set confirm to DELETE.', 'onemcp-pro'),
				'onemcp-woocommerce',
				false,
				array(__CLASS__, 'delete_coupon'),
				array(
					'id'      => Helper::int_prop(__('Coupon ID.', 'onemcp-pro')),
					'confirm' => Helper::string_prop(__('Must be DELETE.', 'onemcp-pro'), 20),
				),
				array('id', 'confirm'),
				array('permission' => array(__CLASS__, 'can_manage'), 'destructive' => true)
			),
			Helper::ability(
				'delete-product',
				__('Delete product', 'onemcp-pro'),
				__('Moves one product to the trash. Set confirm to DELETE.', 'onemcp-pro'),
				'onemcp-woocommerce',
				false,
				array(__CLASS__, 'delete_product'),
				array(
					'id'      => Helper::int_prop(__('Product ID.', 'onemcp-pro')),
					'confirm' => Helper::string_prop(__('Must be DELETE.', 'onemcp-pro'), 20),
				),
				array('id', 'confirm'),
				array('permission' => array(__CLASS__, 'can_manage'), 'destructive' => true)
			),
			Helper::ability(
				'list-variations',
				__('List variations', 'onemcp-pro'),
				__('Lists variations of one variable product, including price and stock.', 'onemcp-pro'),
				'onemcp-woocommerce',
				true,
				array(__CLASS__, 'list_variations'),
				array('id' => Helper::int_prop(__('Variable product ID.', 'onemcp-pro'))),
				array('id'),
				array('permission' => array(__CLASS__, 'can_manage'))
			),
			Helper::ability(
				'list-product-categories',
				__('List product categories', 'onemcp-pro'),
				__('Lists WooCommerce product categories.', 'onemcp-pro'),
				'onemcp-woocommerce',
				true,
				array(__CLASS__, 'list_product_categories'),
				$paging,
				array(),
				array('permission' => array(__CLASS__, 'can_manage'))
			),
			Helper::ability(
				'list-customers',
				__('List customers', 'onemcp-pro'),
				__('Lists WooCommerce customers.', 'onemcp-pro'),
				'onemcp-woocommerce',
				true,
				array(__CLASS__, 'list_customers'),
				$paging,
				array(),
				array('permission' => array(__CLASS__, 'can_manage'))
			),
			Helper::ability(
				'get-customer',
				__('Get customer', 'onemcp-pro'),
				__('Returns one customer, including spend totals and recent orders.', 'onemcp-pro'),
				'onemcp-woocommerce',
				true,
				array(__CLASS__, 'get_customer'),
				array('id' => Helper::int_prop(__('Customer user ID.', 'onemcp-pro'))),
				array('id'),
				array('permission' => array(__CLASS__, 'can_manage'))
			),
			Helper::ability(
				'list-order-notes',
				__('List order notes', 'onemcp-pro'),
				__('Lists notes on one WooCommerce order.', 'onemcp-pro'),
				'onemcp-woocommerce',
				true,
				array(__CLASS__, 'list_order_notes'),
				array('id' => Helper::int_prop(__('Order ID.', 'onemcp-pro'))),
				array('id'),
				array('permission' => array(__CLASS__, 'can_manage'))
			),
			Helper::ability(
				'add-order-note',
				__('Add order note', 'onemcp-pro'),
				__('Adds a private or customer-facing note to one order.', 'onemcp-pro'),
				'onemcp-woocommerce',
				false,
				array(__CLASS__, 'add_order_note'),
				array(
					'id'            => Helper::int_prop(__('Order ID.', 'onemcp-pro')),
					'note'          => Helper::string_prop(__('Note text.', 'onemcp-pro'), 1000),
					'customer_note' => Helper::bool_prop(__('True to email the note to the customer.', 'onemcp-pro')),
				),
				array('id', 'note'),
				array('permission' => array(__CLASS__, 'can_manage'))
			),
			Helper::ability(
				'get-sales-summary',
				__('Get sales summary', 'onemcp-pro'),
				__('Revenue, order count, and best-selling products from completed and processing orders. Defaults to the last 30 days. The report reads at most 100 orders.', 'onemcp-pro'),
				'onemcp-woocommerce',
				true,
				array(__CLASS__, 'sales_summary'),
				array(
					'days' => Helper::int_prop(__('How many days to include, from 1 to 365. Defaults to 30.', 'onemcp-pro')),
				),
				array(),
				array('permission' => array(__CLASS__, 'can_manage'))
			),
			Helper::ability(
				'create-product-category',
				__('Create product category', 'onemcp-pro'),
				__('Creates a WooCommerce product category.', 'onemcp-pro'),
				'onemcp-woocommerce',
				false,
				array(__CLASS__, 'create_product_category'),
				array(
					'name'        => Helper::string_prop(__('Category name.', 'onemcp-pro'), 200),
					'slug'        => Helper::string_prop(__('Optional slug.', 'onemcp-pro'), 200),
					'description' => Helper::string_prop(__('Optional description.', 'onemcp-pro'), 2000),
					'parent'      => Helper::int_prop(__('Parent category ID. Use 0 for a top-level category.', 'onemcp-pro')),
				),
				array('name'),
				array('permission' => array(__CLASS__, 'can_manage'))
			),
			Helper::ability(
				'list-product-tags',
				__('List product tags', 'onemcp-pro'),
				__('Lists WooCommerce product tags.', 'onemcp-pro'),
				'onemcp-woocommerce',
				true,
				array(__CLASS__, 'list_product_tags'),
				$paging,
				array(),
				array('permission' => array(__CLASS__, 'can_manage'))
			),
			Helper::ability(
				'create-product-tag',
				__('Create product tag', 'onemcp-pro'),
				__('Creates a WooCommerce product tag.', 'onemcp-pro'),
				'onemcp-woocommerce',
				false,
				array(__CLASS__, 'create_product_tag'),
				array(
					'name'        => Helper::string_prop(__('Tag name.', 'onemcp-pro'), 200),
					'slug'        => Helper::string_prop(__('Optional slug.', 'onemcp-pro'), 200),
					'description' => Helper::string_prop(__('Optional description.', 'onemcp-pro'), 2000),
				),
				array('name'),
				array('permission' => array(__CLASS__, 'can_manage'))
			),
			Helper::ability(
				'list-attributes',
				__('List attributes', 'onemcp-pro'),
				__('Lists global WooCommerce product attributes, such as size and colour, including their terms.', 'onemcp-pro'),
				'onemcp-woocommerce',
				true,
				array(__CLASS__, 'list_attributes'),
				array(),
				array(),
				array('permission' => array(__CLASS__, 'can_manage'))
			),
			Helper::ability(
				'create-attribute',
				__('Create attribute', 'onemcp-pro'),
				__('Creates a global product attribute. Terms are added with create-attribute-term, then assigned on a product.', 'onemcp-pro'),
				'onemcp-woocommerce',
				false,
				array(__CLASS__, 'create_attribute'),
				array(
					'name' => Helper::string_prop(__('Attribute name, such as Colour.', 'onemcp-pro'), 80),
					'slug' => Helper::string_prop(__('Optional slug without the pa_ prefix.', 'onemcp-pro'), 28),
				),
				array('name'),
				array('permission' => array(__CLASS__, 'can_manage'))
			),
			Helper::ability(
				'create-attribute-term',
				__('Create attribute term', 'onemcp-pro'),
				__('Adds a term such as Red or Large to a global product attribute.', 'onemcp-pro'),
				'onemcp-woocommerce',
				false,
				array(__CLASS__, 'create_attribute_term'),
				array(
					'attribute' => Helper::string_prop(__('Attribute slug or name, such as colour or pa_colour.', 'onemcp-pro'), 32),
					'name'      => Helper::string_prop(__('Term name, such as Red.', 'onemcp-pro'), 80),
					'slug'      => Helper::string_prop(__('Optional term slug.', 'onemcp-pro'), 80),
				),
				array('attribute', 'name'),
				array('permission' => array(__CLASS__, 'can_manage'))
			),
			Helper::ability(
				'set-product-attributes',
				__('Set product attributes', 'onemcp-pro'),
				__('Replaces the attributes on one product. Variation attributes turn a simple product into a variable product.', 'onemcp-pro'),
				'onemcp-woocommerce',
				false,
				array(__CLASS__, 'set_product_attributes'),
				array(
					'id'         => Helper::int_prop(__('Product ID.', 'onemcp-pro')),
					'attributes' => array(
						'type'        => 'array',
						'description' => __('Each item has name, options, and optional variation and visible flags.', 'onemcp-pro'),
						'items'       => array(
							'type'                 => 'object',
							'additionalProperties' => false,
							'properties'           => array(
								'name'      => array('type' => 'string'),
								'options'   => array(
									'type'  => 'array',
									'items' => array('type' => 'string'),
								),
								'variation' => array('type' => 'boolean'),
								'visible'   => array('type' => 'boolean'),
							),
							'required'             => array('name', 'options'),
						),
					),
				),
				array('id', 'attributes'),
				array('permission' => array(__CLASS__, 'can_manage'))
			),
			Helper::ability(
				'create-variation',
				__('Create variation', 'onemcp-pro'),
				__('Creates one variation, such as a size and colour, on a product. The parent becomes a variable product when it is not one yet.', 'onemcp-pro'),
				'onemcp-woocommerce',
				false,
				array(__CLASS__, 'create_variation'),
				self::variation_properties(),
				array('id', 'attributes'),
				array('permission' => array(__CLASS__, 'can_manage'))
			),
			Helper::ability(
				'update-variation',
				__('Update variation', 'onemcp-pro'),
				__('Updates price, sale dates, stock, SKU, image, or status on one variation.', 'onemcp-pro'),
				'onemcp-woocommerce',
				false,
				array(__CLASS__, 'update_variation'),
				self::variation_properties() + array(
					'variation_id' => Helper::int_prop(__('Variation ID.', 'onemcp-pro')),
				),
				array('variation_id'),
				array('permission' => array(__CLASS__, 'can_manage'))
			),
			Helper::ability(
				'list-product-reviews',
				__('List product reviews', 'onemcp-pro'),
				__('Lists WooCommerce product reviews, including rating and moderation status.', 'onemcp-pro'),
				'onemcp-woocommerce',
				true,
				array(__CLASS__, 'list_reviews'),
				array(
					'product_id' => Helper::int_prop(__('Limit to one product.', 'onemcp-pro')),
					'status'     => Helper::string_prop(__('approve, hold, spam, trash, or all. Defaults to all.', 'onemcp-pro'), 20),
					'search'     => Helper::string_prop(__('Search review text or author.', 'onemcp-pro'), 120),
					'limit'      => Helper::int_prop(__('How many reviews to return. Maximum 50.', 'onemcp-pro')),
					'page'       => Helper::int_prop(__('Page number, starting at 1.', 'onemcp-pro')),
				),
				array(),
				array('permission' => array(__CLASS__, 'can_manage'))
			),
			Helper::ability(
				'moderate-product-review',
				__('Moderate product review', 'onemcp-pro'),
				__('Approves, unapproves, spams, or trashes one product review.', 'onemcp-pro'),
				'onemcp-woocommerce',
				false,
				array(__CLASS__, 'moderate_review'),
				array(
					'id'     => Helper::int_prop(__('Review comment ID.', 'onemcp-pro')),
					'status' => Helper::string_prop(__('approve, hold, spam, or trash.', 'onemcp-pro'), 20),
				),
				array('id', 'status'),
				array('permission' => array(__CLASS__, 'can_manage'))
			),
			Helper::ability(
				'refund-order',
				__('Refund order', 'onemcp-pro'),
				__('Refunds a WooCommerce order or selected line items. This is a separate action from changing order status. Set confirm to REFUND. Omit amount and line_items to refund the remaining total. Set refund_payment to false for a manual refund that does not call the payment gateway.', 'onemcp-pro'),
				'onemcp-woocommerce',
				false,
				array(__CLASS__, 'refund_order'),
				array(
					'id'             => Helper::int_prop(__('Order ID.', 'onemcp-pro')),
					'amount'         => Helper::string_prop(__('Amount to refund. Omit to refund the remaining total, or to use line item amounts.', 'onemcp-pro'), 20),
					'reason'         => Helper::string_prop(__('Reason stored on the refund.', 'onemcp-pro'), 200),
					'refund_payment' => Helper::bool_prop(__('Send the refund to the payment gateway. Defaults to true.', 'onemcp-pro')),
					'restock'        => Helper::bool_prop(__('Return refunded items to stock.', 'onemcp-pro')),
					'confirm'        => Helper::string_prop(__('Must be REFUND.', 'onemcp-pro'), 20),
					'line_items'     => array(
						'type'        => 'array',
						'description' => __('Selected items. Each item has item_id, and optional quantity and refund_total.', 'onemcp-pro'),
						'items'       => array(
							'type'                 => 'object',
							'additionalProperties' => false,
							'properties'           => array(
								'item_id'      => array('type' => 'integer'),
								'quantity'     => array('type' => 'integer'),
								'refund_total' => array('type' => 'string'),
							),
							'required'             => array('item_id'),
						),
					),
				),
				array('id', 'confirm'),
				array('permission' => array(__CLASS__, 'can_manage'), 'destructive' => true)
			),
			Helper::ability(
				'get-store-settings',
				__('Get store settings', 'onemcp-pro'),
				__('Reads currency, tax, shipping zones, and enabled payment methods. Secrets and API keys are left out.', 'onemcp-pro'),
				'onemcp-woocommerce',
				true,
				array(__CLASS__, 'store_settings'),
				array(),
				array(),
				array('permission' => array(__CLASS__, 'can_manage'))
			),
		);

		return $abilities;
	}

	/**
	 * Shop manager or administrator.
	 *
	 * @return bool
	 */
	static function can_manage() {
		return current_user_can('manage_woocommerce');
	}

	/**
	 * List products.
	 *
	 * @param array<string,mixed> $input Input.
	 * @return array<string,mixed>|WP_Error
	 */
	static function list_products($input) {
		if (!function_exists('wc_get_products')) {
			return self::missing();
		}
		$limit  = Helper::limit(isset($input['limit']) ? $input['limit'] : 20, 20, 50);
		$page   = Helper::page($input);
		$status = isset($input['status']) ? sanitize_key((string) $input['status']) : 'any';
		$args   = array(
			'limit'  => $limit,
			'page'   => $page,
			'status' => $status !== '' ? $status : 'any',
			'paginate' => true,
			'orderby' => 'modified',
			'order'  => 'DESC',
			'return' => 'objects',
		);
		if (!empty($input['search'])) {
			$args['s'] = Helper::text($input['search'], 120);
		}
		if (!empty($input['stock_status'])) {
			$stock_status = sanitize_key((string) $input['stock_status']);
			if (in_array($stock_status, array('instock', 'outofstock', 'onbackorder'), true)) {
				$args['stock_status'] = $stock_status;
			}
		}
		if (!empty($input['low_stock']) && Helper::bool($input['low_stock'])) {
			return self::low_stock_products($args, $limit, $page);
		}
		$result = wc_get_products($args);
		$items  = array();
		$products = is_object($result) && isset($result->products) ? $result->products : (array) $result;
		foreach ($products as $product) {
			if ($product instanceof WC_Product) {
				$items[] = self::product_row($product, false);
			}
		}
		return array(
			'items' => $items,
			'total' => is_object($result) && isset($result->total) ? (int) $result->total : count($items),
			'page'  => $page,
		);
	}

	/**
	 * One product.
	 *
	 * @param array<string,mixed> $input Input.
	 * @return array<string,mixed>|WP_Error
	 */
	static function get_product($input) {
		$product = self::product(isset($input['id']) ? $input['id'] : 0);
		if (is_wp_error($product)) {
			return $product;
		}
		return self::product_row($product, true);
	}

	/**
	 * Create a simple product.
	 *
	 * @param array<string,mixed> $input Input.
	 * @return array<string,mixed>|WP_Error
	 */
	 static function create_product($input) {
		if (!class_exists('WC_Product_Simple')) {
			return self::missing();
		}
		$product = new WC_Product_Simple();
		$product->set_name(Helper::text(isset($input['name']) ? $input['name'] : '', 200));
		$product->set_status(self::product_status(isset($input['status']) ? $input['status'] : 'draft'));
		$applied = self::apply_product($product, $input);
		if (is_wp_error($applied)) {
			return $applied;
		}
		$product->save();
		return self::product_row($product, true);
	}

	/**
	 * Update a product.
	 *
	 * @param array<string,mixed> $input Input.
	 * @return array<string,mixed>|WP_Error
	 */
	static function update_product($input) {
		$product = self::product(isset($input['id']) ? $input['id'] : 0);
		if (is_wp_error($product)) {
			return $product;
		}
		if (array_key_exists('name', $input)) {
			$product->set_name(Helper::text($input['name'], 200));
		}
		if (array_key_exists('status', $input)) {
			$product->set_status(self::product_status($input['status']));
		}
		$applied = self::apply_product($product, $input);
		if (is_wp_error($applied)) {
			return $applied;
		}
		$product->save();
		return self::product_row($product, true);
	}

	/**
	 * List orders.
	 *
	 * @param array<string,mixed> $input Input.
	 * @return array<string,mixed>|WP_Error
	 */
	 static function list_orders($input) {
		if (!function_exists('wc_get_orders')) {
			return self::missing();
		}
		$limit = Helper::limit(isset($input['limit']) ? $input['limit'] : 20, 20, 50);
		$page  = Helper::page($input);
		$args  = array(
			'limit'    => $limit,
			'page'     => $page,
			'paginate' => true,
			'orderby'  => 'date',
			'order'    => 'DESC',
			'return'   => 'objects',
		);
		if (!empty($input['status'])) {
			$status = self::order_status($input['status']);
			if (is_wp_error($status)) {
				return $status;
			}
			$args['status'] = $status;
		}
		if (!empty($input['customer_id'])) {
			$args['customer'] = Helper::absint($input['customer_id']);
		}
		if (!empty($input['search'])) {
			$search = Helper::text($input['search'], 120);
			if (is_email($search)) {
				$args['billing_email'] = $search;
			} else {
				$args['s'] = $search;
			}
		}
		$result = wc_get_orders($args);
		$items  = array();
		$orders = is_object($result) && isset($result->orders) ? $result->orders : (array) $result;
		foreach ($orders as $order) {
			if ($order instanceof WC_Order) {
				$items[] = self::order_row($order, false);
			}
		}
		return array(
			'items' => $items,
			'total' => is_object($result) && isset($result->total) ? (int) $result->total : count($items),
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
		$order = self::order(isset($input['id']) ? $input['id'] : 0);
		if (is_wp_error($order)) {
			return $order;
		}
		$status = self::order_status(isset($input['status']) ? $input['status'] : '');
		if (is_wp_error($status)) {
			return $status;
		}
		$order->update_status($status, __('Status changed from OneMCP.', 'onemcp-pro'), true);
		return self::order_row($order, true);
	}

	/**
	 * List coupons.
	 *
	 * @param array<string,mixed> $input Input.
	 * @return array<string,mixed>
	 */
	 static function list_coupons($input) {
		$limit = Helper::limit(isset($input['limit']) ? $input['limit'] : 20, 20, 50);
		$page  = Helper::page($input);
		$args  = array(
			'post_type'      => 'shop_coupon',
			'post_status'    => array('publish', 'draft'),
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
			if (!$post instanceof WP_Post || !class_exists('WC_Coupon')) {
				continue;
			}
			$coupon = new WC_Coupon($post->ID);
			$items[] = self::coupon_row($coupon);
		}
		return array(
			'items' => $items,
			'total' => (int) $query->found_posts,
			'page'  => $page,
		);
	}

	/**
	 * Create a coupon.
	 *
	 * @param array<string,mixed> $input Input.
	 * @return array<string,mixed>|WP_Error
	 */ 
	 static function create_coupon($input) {
		if (!class_exists('WC_Coupon') || !function_exists('wc_format_coupon_code')) {
			return self::missing();
		}
		$code = wc_format_coupon_code(Helper::text(isset($input['code']) ? $input['code'] : '', 40));
		if ($code === '') {
			return Helper::error('onemcp_invalid', __('Provide a coupon code.', 'onemcp-pro'));
		}
		if (function_exists('wc_get_coupon_id_by_code') && wc_get_coupon_id_by_code($code)) {
			return Helper::error('onemcp_invalid', __('That coupon code already exists.', 'onemcp-pro'));
		}
		$type = isset($input['discount_type']) ? sanitize_key((string) $input['discount_type']) : 'fixed_cart';
		if (!in_array($type, array('percent', 'fixed_cart', 'fixed_product'), true)) {
			return Helper::error('onemcp_invalid', __('Discount type must be percent, fixed_cart, or fixed_product.', 'onemcp-pro'));
		}

		$coupon = new WC_Coupon();
		$coupon->set_code($code);
		$coupon->set_discount_type($type);
		$coupon->set_amount(wc_format_decimal(isset($input['amount']) ? (string) $input['amount'] : '0'));
		if (isset($input['description'])) {
			$coupon->set_description(Helper::text($input['description'], 200));
		}
		if (isset($input['usage_limit'])) {
			$coupon->set_usage_limit(max(0, (int) $input['usage_limit']));
		}
		if (isset($input['usage_limit_per_user'])) {
			$coupon->set_usage_limit_per_user(max(0, (int) $input['usage_limit_per_user']));
		}
		if (array_key_exists('date_expires', $input)) {
			$expires = self::coupon_expiry($input['date_expires']);
			if (is_wp_error($expires)) {
				return $expires;
			}
			if ($expires !== '') {
				$coupon->set_date_expires($expires);
			}
		}
		if (isset($input['free_shipping'])) {
			$coupon->set_free_shipping(Helper::bool($input['free_shipping']));
		}
		$coupon->save();
		return self::coupon_row($coupon);
	}

	/**
	 * Update a coupon.
	 *
	 * @param array<string,mixed> $input Input.
	 * @return array<string,mixed>|WP_Error
	 */
	 static function update_coupon($input) {
		$coupon = self::coupon(isset($input['id']) ? $input['id'] : 0);
		if (is_wp_error($coupon)) {
			return $coupon;
		}
		$changed = false;
		if (array_key_exists('discount_type', $input)) {
			$type = sanitize_key((string) $input['discount_type']);
			if (!in_array($type, array('percent', 'fixed_cart', 'fixed_product'), true)) {
				return Helper::error('onemcp_invalid', __('Discount type must be percent, fixed_cart, or fixed_product.', 'onemcp-pro'));
			}
			$coupon->set_discount_type($type);
			$changed = true;
		}
		if (array_key_exists('amount', $input)) {
			$coupon->set_amount(wc_format_decimal((string) $input['amount']));
			$changed = true;
		}
		if (array_key_exists('description', $input)) {
			$coupon->set_description(Helper::text($input['description'], 200));
			$changed = true;
		}
		if (array_key_exists('usage_limit', $input)) {
			$coupon->set_usage_limit(max(0, (int) $input['usage_limit']));
			$changed = true;
		}
		if (array_key_exists('usage_limit_per_user', $input)) {
			$coupon->set_usage_limit_per_user(max(0, (int) $input['usage_limit_per_user']));
			$changed = true;
		}
		if (array_key_exists('date_expires', $input)) {
			$expires = self::coupon_expiry($input['date_expires']);
			if (is_wp_error($expires)) {
				return $expires;
			}
			$coupon->set_date_expires($expires === '' ? null : $expires);
			$changed = true;
		}
		if (array_key_exists('free_shipping', $input)) {
			$coupon->set_free_shipping(Helper::bool($input['free_shipping']));
			$changed = true;
		}
		if (!$changed) {
			return Helper::error('onemcp_invalid', __('Provide a coupon field to update.', 'onemcp-pro'));
		}
		$coupon->save();
		return self::coupon_row($coupon);
	}

	/**
	 * Trash a coupon.
	 *
	 * @param array<string,mixed> $input Input.
	 * @return array<string,mixed>|WP_Error
	 */
	 static function delete_coupon($input) {
		$coupon = self::coupon(isset($input['id']) ? $input['id'] : 0);
		if (is_wp_error($coupon)) {
			return $coupon;
		}
		if (!isset($input['confirm']) || (string) $input['confirm'] !== 'DELETE') {
			return Helper::error('onemcp_invalid', __('Set confirm to DELETE.', 'onemcp-pro'));
		}
		wp_trash_post($coupon->get_id());
		return array(
			'id'     => $coupon->get_id(),
			'status' => 'trash',
		);
	}

	/**
	 * Trash a product.
	 *
	 * @param array<string,mixed> $input Input.
	 * @return array<string,mixed>|WP_Error
	 */
	 static function delete_product($input) {
		$product = self::product(isset($input['id']) ? $input['id'] : 0);
		if (is_wp_error($product)) {
			return $product;
		}
		if (!isset($input['confirm']) || (string) $input['confirm'] !== 'DELETE') {
			return Helper::error('onemcp_invalid', __('Set confirm to DELETE.', 'onemcp-pro'));
		}
		if (!current_user_can('delete_post', $product->get_id())) {
			return Helper::error('onemcp_forbidden', __('You cannot delete this product.', 'onemcp-pro'));
		}
		$product->delete(false);
		return array(
			'id'     => $product->get_id(),
			'status' => 'trash',
		);
	}

	/**
	 * Variations of one product.
	 *
	 * @param array<string,mixed> $input Input.
	 * @return array<string,mixed>|WP_Error
	 */
	public static function list_variations($input) {
		$product = self::product(isset($input['id']) ? $input['id'] : 0);
		if (is_wp_error($product)) {
			return $product;
		}
		$items = array();
		if ($product->is_type('variable')) {
			foreach ($product->get_children() as $child_id) {
				$child = wc_get_product($child_id);
				if (!$child instanceof WC_Product) {
					continue;
				}
				$items[] = self::variation_row($child);
			}
		}
		return array(
			'product_id' => $product->get_id(),
			'type'       => $product->get_type(),
			'items'      => $items,
		);
	}

	/**
	 * Product categories.
	 *
	 * @param array<string,mixed> $input Input.
	 * @return array<string,mixed>|WP_Error
	 */
	public static function list_product_categories($input) {
		$limit = Helper::limit(isset($input['limit']) ? $input['limit'] : 20, 20, 50);
		$page  = Helper::page($input);
		$args  = array(
			'taxonomy'   => 'product_cat',
			'hide_empty' => false,
			'number'     => $limit,
			'offset'     => ($page - 1) * $limit,
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
			'items' => $items,
			'page'  => $page,
		);
	}

	/**
	 * Customers.
	 *
	 * @param array<string,mixed> $input Input.
	 * @return array<string,mixed>
	 */
	 static function list_customers($input) {
		$args = array(
			'role'   => 'customer',
			'number' => Helper::limit(isset($input['limit']) ? $input['limit'] : 20, 20, 50),
			'paged'  => Helper::page($input),
		);
		if (!empty($input['search'])) {
			$args['search'] = '*' . Helper::text($input['search'], 120) . '*';
			$args['search_columns'] = array('user_login', 'user_email', 'display_name');
		}
		$query = new WP_User_Query($args);
		$items = array();
		foreach ($query->get_results() as $user) {
			if ($user instanceof WP_User) {
				$items[] = self::customer_row($user, false);
			}
		}
		return array(
			'items' => $items,
			'total' => (int) $query->get_total(),
			'page'  => Helper::page($input),
		);
	}

	/**
	 * One customer.
	 *
	 * @param array<string,mixed> $input Input.
	 * @return array<string,mixed>|WP_Error
	 */
	static function get_customer($input) {
		$user = get_user_by('id', Helper::absint(isset($input['id']) ? $input['id'] : 0));
		if (!$user instanceof WP_User) {
			return Helper::error('onemcp_not_found', __('Customer was not found.', 'onemcp-pro'));
		}
		return self::customer_row($user, true);
	}

	/**
	 * Notes on one order.
	 *
	 * @param array<string,mixed> $input Input.
	 * @return array<string,mixed>|WP_Error
	 */
	 static function list_order_notes($input) {
		$order = self::order(isset($input['id']) ? $input['id'] : 0);
		if (is_wp_error($order)) {
			return $order;
		}
		$notes = function_exists('wc_get_order_notes') ? wc_get_order_notes(array(
			'order_id' => $order->get_id(),
			'limit'    => 30,
		)) : array();
		$items = array();
		foreach ((array) $notes as $note) {
			if (!is_object($note)) {
				continue;
			}
			$items[] = array(
				'id'             => isset($note->id) ? (int) $note->id : 0,
				'content'        => isset($note->content) ? Helper::trim_text($note->content, 1000) : '',
				'customer_note'  => !empty($note->customer_note),
				'date'           => isset($note->date_created) && is_object($note->date_created) ? $note->date_created->date('c') : '',
			);
		}
		return array(
			'order_id' => $order->get_id(),
			'items'    => $items,
		);
	}

	/**
	 * Add a note to an order.
	 *
	 * @param array<string,mixed> $input Input.
	 * @return array<string,mixed>|WP_Error
	 */
	 static function add_order_note($input) {
		$order = self::order(isset($input['id']) ? $input['id'] : 0);
		if (is_wp_error($order)) {
			return $order;
		}
		$note = Helper::text(isset($input['note']) ? $input['note'] : '', 1000);
		if ($note === '') {
			return Helper::error('onemcp_invalid', __('Provide a note.', 'onemcp-pro'));
		}
		$note_id = $order->add_order_note($note, !empty($input['customer_note']) && Helper::bool($input['customer_note']));
		return array(
			'id'       => (int) $note_id,
			'order_id' => $order->get_id(),
			'note'     => $note,
		);
	}

	/**
	 * Recent completed and processing sales.
	 *
	 * @param array<string,mixed> $input Input.
	 * @return array<string,mixed>|WP_Error
	 */
	 static function sales_summary($input) {
		if (!function_exists('wc_get_orders')) {
			return self::missing();
		}
		$days = isset($input['days']) ? (int) $input['days'] : 30;
		if ($days < 1 || $days > 365) {
			$days = 30;
		}
		$after  = gmdate('Y-m-d H:i:s', time() - ($days * DAY_IN_SECONDS));
		$result = wc_get_orders(array(
			'status'       => array('completed', 'processing'),
			'date_created' => '>' . strtotime($after),
			'limit'        => 100,
			'paginate'     => true,
			'return'       => 'objects',
		));
		$orders = is_object($result) && isset($result->orders) ? $result->orders : (array) $result;
		$total   = 0.0;
		$count   = 0;
		$sellers = array();
		foreach ($orders as $order) {
			if (!$order instanceof WC_Order) {
				continue;
			}
			$total += (float) $order->get_total();
			$count++;
			foreach ($order->get_items() as $item) {
				$product_id = (int) $item->get_product_id();
				if ($product_id < 1) {
					continue;
				}
				if (!isset($sellers[$product_id])) {
					$sellers[$product_id] = array(
						'product_id' => $product_id,
						'name'       => $item->get_name(),
						'quantity'   => 0,
						'total'      => 0.0,
					);
				}
				$sellers[$product_id]['quantity'] += (int) $item->get_quantity();
				$sellers[$product_id]['total']    += (float) $item->get_total();
			}
		}
		usort($sellers, function ($left, $right) {
			if ($left['quantity'] === $right['quantity']) {
				return $right['total'] <=> $left['total'];
			}
			return $right['quantity'] <=> $left['quantity'];
		});
		$best = array();
		foreach (array_slice($sellers, 0, 10) as $seller) {
			$seller['total'] = function_exists('wc_format_decimal') ? wc_format_decimal($seller['total']) : (string) $seller['total'];
			$best[]          = $seller;
		}
		$found = is_object($result) && isset($result->total) ? (int) $result->total : $count;
		return array(
			'days'         => $days,
			'orders'       => $count,
			'total'        => function_exists('wc_format_decimal') ? wc_format_decimal($total) : (string) $total,
			'currency'     => function_exists('get_woocommerce_currency') ? get_woocommerce_currency() : '',
			'best_sellers' => $best,
			'truncated'    => $found > $count,
		);
	}

	/**
	 * Product fields shared by create and update.
	 *
	 * @return array<string,array<string,mixed>>
	 */
	private static function product_properties() {
		return array(
			'name'              => Helper::string_prop(__('Product name.', 'onemcp-pro'), 200),
			'status'            => Helper::string_prop(__('draft, publish, pending, or private.', 'onemcp-pro'), 20),
			'regular_price'     => Helper::string_prop(__('Regular price.', 'onemcp-pro'), 20),
			'sale_price'        => Helper::string_prop(__('Sale price. Send an empty string to clear it.', 'onemcp-pro'), 20),
			'sku'               => Helper::string_prop(__('SKU.', 'onemcp-pro'), 80),
			'description'       => Helper::string_prop(__('Product description HTML.', 'onemcp-pro'), 50000),
			'short_description' => Helper::string_prop(__('Short description HTML.', 'onemcp-pro'), 5000),
			'stock_quantity'    => Helper::int_prop(__('Stock quantity. Setting this turns on stock management.', 'onemcp-pro')),
			'stock_status'      => Helper::string_prop(__('instock, outofstock, or onbackorder.', 'onemcp-pro'), 20),
			'manage_stock'      => Helper::bool_prop(__('Turn stock management on or off.', 'onemcp-pro')),
			'backorders'        => Helper::string_prop(__('no, notify, or yes.', 'onemcp-pro'), 20),
			'date_on_sale_from' => Helper::string_prop(__('Sale start date, YYYY-MM-DD. Send an empty string to clear it.', 'onemcp-pro'), 20),
			'date_on_sale_to'   => Helper::string_prop(__('Sale end date, YYYY-MM-DD. Send an empty string to clear it.', 'onemcp-pro'), 20),
			'image_id'          => Helper::int_prop(__('Featured image attachment ID. Use 0 to remove it.', 'onemcp-pro')),
			'gallery_ids'       => Helper::int_list_prop(__('Gallery image attachment IDs. Replaces the gallery. Send an empty list to clear it.', 'onemcp-pro')),
			'category_ids'      => Helper::int_list_prop(__('Product category IDs. Replaces the categories on this product.', 'onemcp-pro')),
			'tag_ids'           => Helper::int_list_prop(__('Product tag IDs. Replaces the tags on this product.', 'onemcp-pro')),
		);
	}

	/**
	 * Fields shared by variation create and update.
	 *
	 * @return array<string,array<string,mixed>>
	 */
	private static function variation_properties() {
		return array(
			'id'                => Helper::int_prop(__('Parent product ID. Required when creating a variation.', 'onemcp-pro')),
			'attributes'        => array(
				'type'        => 'array',
				'description' => __('Attribute pairs, such as Size Large and Colour Red. Each item has name and value.', 'onemcp-pro'),
				'items'       => array(
					'type'                 => 'object',
					'additionalProperties' => false,
					'properties'           => array(
						'name'  => array('type' => 'string'),
						'value' => array('type' => 'string'),
					),
					'required'             => array('name', 'value'),
				),
			),
			'regular_price'     => Helper::string_prop(__('Regular price.', 'onemcp-pro'), 20),
			'sale_price'        => Helper::string_prop(__('Sale price. Send an empty string to clear it.', 'onemcp-pro'), 20),
			'date_on_sale_from' => Helper::string_prop(__('Sale start date, YYYY-MM-DD. Send an empty string to clear it.', 'onemcp-pro'), 20),
			'date_on_sale_to'   => Helper::string_prop(__('Sale end date, YYYY-MM-DD. Send an empty string to clear it.', 'onemcp-pro'), 20),
			'sku'               => Helper::string_prop(__('SKU.', 'onemcp-pro'), 80),
			'stock_quantity'    => Helper::int_prop(__('Stock quantity. Setting this turns on stock management.', 'onemcp-pro')),
			'stock_status'      => Helper::string_prop(__('instock, outofstock, or onbackorder.', 'onemcp-pro'), 20),
			'backorders'        => Helper::string_prop(__('no, notify, or yes.', 'onemcp-pro'), 20),
			'image_id'          => Helper::int_prop(__('Variation image attachment ID. Use 0 to remove it.', 'onemcp-pro')),
			'status'            => Helper::string_prop(__('publish or private.', 'onemcp-pro'), 20),
		);
	}

	/**
	 * Apply optional product fields.
	 *
	 * @param WC_Product          $product Product.
	 * @param array<string,mixed> $input   Input.
	 * @return true|WP_Error
	 */
	private static function apply_product($product, $input) {
		if (array_key_exists('regular_price', $input)) {
			$product->set_regular_price(wc_format_decimal((string) $input['regular_price']));
		}
		if (array_key_exists('sale_price', $input)) {
			$sale = trim((string) $input['sale_price']);
			$product->set_sale_price($sale === '' ? '' : wc_format_decimal($sale));
		}
		if (array_key_exists('date_on_sale_from', $input)) {
			$date = self::sale_date($input['date_on_sale_from']);
			if (is_wp_error($date)) {
				return $date;
			}
			$product->set_date_on_sale_from($date === '' ? null : $date);
		}
		if (array_key_exists('date_on_sale_to', $input)) {
			$date = self::sale_date($input['date_on_sale_to']);
			if (is_wp_error($date)) {
				return $date;
			}
			$product->set_date_on_sale_to($date === '' ? null : $date);
		}
		if (array_key_exists('sku', $input)) {
			$product->set_sku(Helper::text($input['sku'], 80));
		}
		if (array_key_exists('description', $input)) {
			$product->set_description(wp_kses_post((string) $input['description']));
		}
		if (array_key_exists('short_description', $input)) {
			$product->set_short_description(wp_kses_post((string) $input['short_description']));
		}
		if (array_key_exists('manage_stock', $input)) {
			$product->set_manage_stock(Helper::bool($input['manage_stock']));
		}
		if (array_key_exists('stock_quantity', $input)) {
			$product->set_manage_stock(true);
			$product->set_stock_quantity((int) $input['stock_quantity']);
		}
		if (array_key_exists('stock_status', $input)) {
			$status = sanitize_key((string) $input['stock_status']);
			if (in_array($status, array('instock', 'outofstock', 'onbackorder'), true)) {
				$product->set_stock_status($status);
			}
		}
		if (array_key_exists('backorders', $input)) {
			$backorders = sanitize_key((string) $input['backorders']);
			if (!in_array($backorders, array('no', 'notify', 'yes'), true)) {
				return Helper::error('onemcp_invalid', __('Backorders must be no, notify, or yes.', 'onemcp-pro'));
			}
			$product->set_backorders($backorders);
		}
		if (array_key_exists('image_id', $input)) {
			$image = self::image_id($input['image_id'], true);
			if (is_wp_error($image)) {
				return $image;
			}
			$product->set_image_id($image);
		}
		if (array_key_exists('gallery_ids', $input)) {
			$gallery = self::image_ids(isset($input['gallery_ids']) ? $input['gallery_ids'] : array());
			if (is_wp_error($gallery)) {
				return $gallery;
			}
			$product->set_gallery_image_ids($gallery);
		}
		if (array_key_exists('category_ids', $input)) {
			$categories = self::term_ids(isset($input['category_ids']) ? $input['category_ids'] : array(), 'product_cat');
			if (is_wp_error($categories)) {
				return $categories;
			}
			$product->set_category_ids($categories);
		}
		if (array_key_exists('tag_ids', $input)) {
			$tags = self::term_ids(isset($input['tag_ids']) ? $input['tag_ids'] : array(), 'product_tag');
			if (is_wp_error($tags)) {
				return $tags;
			}
			$product->set_tag_ids($tags);
		}
		return true;
	}

	/**
	 * Product payload.
	 *
	 * @param WC_Product $product Product.
	 * @param bool       $full    Include descriptions.
	 * @return array<string,mixed>
	 */
	private static function product_row($product, $full) {
		$row = array(
			'id'             => $product->get_id(),
			'name'           => $product->get_name(),
			'slug'           => $product->get_slug(),
			'status'         => $product->get_status(),
			'type'           => $product->get_type(),
			'sku'            => $product->get_sku(),
			'regular_price'  => $product->get_regular_price(),
			'sale_price'     => $product->get_sale_price(),
			'price'          => $product->get_price(),
			'stock_status'   => $product->get_stock_status(),
			'stock_quantity'    => $product->get_stock_quantity(),
			'manage_stock'      => (bool) $product->get_manage_stock(),
			'backorders'        => $product->get_backorders(),
			'date_on_sale_from' => self::wc_date($product->get_date_on_sale_from()),
			'date_on_sale_to'   => self::wc_date($product->get_date_on_sale_to()),
			'permalink'         => $product->get_permalink(),
		);
		$parent_id = (int) $product->get_parent_id();
		if ($parent_id > 0) {
			$row['parent_id'] = $parent_id;
		}
		if ($full) {
			$image_id                  = (int) $product->get_image_id();
			$row['short_description']  = Helper::trim_text($product->get_short_description(), 4000);
			$row['description']        = Helper::trim_text($product->get_description(), 20000);
			$row['catalog_visibility'] = $product->get_catalog_visibility();
			$row['image_id']           = $image_id;
			$row['image_url']          = $image_id ? (string) wp_get_attachment_url($image_id) : '';
			$row['gallery_ids']        = array_map('intval', $product->get_gallery_image_ids());
			$row['category_ids']       = array_map('intval', $product->get_category_ids());
			$row['tag_ids']            = array_map('intval', $product->get_tag_ids());
		}
		return $row;
	}

	/**
	 * Order payload.
	 *
	 * @param WC_Order $order Order.
	 * @param bool     $full  Include line items.
	 * @return array<string,mixed>
	 */
	private static function order_row($order, $full) {
		$created = $order->get_date_created();
		$row     = array(
			'id'            => $order->get_id(),
			'number'        => $order->get_order_number(),
			'status'        => $order->get_status(),
			'currency'      => $order->get_currency(),
			'total'         => $order->get_total(),
			'date'          => $created ? $created->date('c') : '',
			'billing_name'  => trim($order->get_billing_first_name() . ' ' . $order->get_billing_last_name()),
			'billing_email' => $order->get_billing_email(),
		);
		if ($full) {
			$items = array();
			foreach ($order->get_items() as $item) {
				$items[] = array(
					'id'           => $item->get_id(),
					'name'         => $item->get_name(),
					'product_id'   => $item->get_product_id(),
					'variation_id' => $item->get_variation_id(),
					'quantity'     => $item->get_quantity(),
					'total'        => $item->get_total(),
				);
			}
			$row['items']          = $items;
			$row['payment_method'] = $order->get_payment_method();
			$row['customer_note']  = Helper::text($order->get_customer_note(), 1000);
		}
		return $row;
	}

	/**
	 * Coupon payload.
	 *
	 * @param WC_Coupon $coupon Coupon.
	 * @return array<string,mixed>
	 */
	private static function coupon_row($coupon) {
		$expires = $coupon->get_date_expires();
		return array(
			'id'            => $coupon->get_id(),
			'code'          => $coupon->get_code(),
			'amount'        => $coupon->get_amount(),
			'discount_type' => $coupon->get_discount_type(),
			'description'   => $coupon->get_description(),
			'usage_count'          => $coupon->get_usage_count(),
			'usage_limit'          => $coupon->get_usage_limit(),
			'usage_limit_per_user' => $coupon->get_usage_limit_per_user(),
			'free_shipping'        => (bool) $coupon->get_free_shipping(),
			'expires'              => $expires ? $expires->date('Y-m-d') : '',
		);
	}

	/**
	 * Customer payload.
	 *
	 * @param WP_User $user User.
	 * @param bool    $full Include spend totals.
	 * @return array<string,mixed>
	 */
	private static function customer_row($user, $full) {
		$row = array(
			'id'           => (int) $user->ID,
			'login'        => $user->user_login,
			'display_name' => $user->display_name,
			'email'        => $user->user_email,
		);
		if ($full) {
			$row['order_count'] = function_exists('wc_get_customer_order_count') ? (int) wc_get_customer_order_count($user->ID) : 0;
			$row['total_spent'] = function_exists('wc_get_customer_total_spent') ? (string) wc_get_customer_total_spent($user->ID) : '';
			$row['orders']      = self::customer_orders($user->ID);
		}
		return $row;
	}

	/**
	 * A coupon the current user can edit.
	 *
	 * @param mixed $id Coupon ID.
	 * @return WC_Coupon|WP_Error
	 */
	private static function coupon($id) {
		if (!class_exists('WC_Coupon')) {
			return self::missing();
		}
		$coupon = new WC_Coupon(Helper::absint($id));
		if (!$coupon->get_id()) {
			return Helper::error('onemcp_not_found', __('Coupon was not found.', 'onemcp-pro'));
		}
		if (!current_user_can('edit_post', $coupon->get_id()) && !current_user_can('manage_woocommerce')) {
			return Helper::error('onemcp_forbidden', __('You cannot edit this coupon.', 'onemcp-pro'));
		}
		return $coupon;
	}

	/**
	 * A product the current user can edit.
	 *
	 * @param mixed $id Product ID.
	 * @return WC_Product|WP_Error
	 */
	private static function product($id) {
		if (!function_exists('wc_get_product')) {
			return self::missing();
		}
		$product = wc_get_product(Helper::absint($id));
		if (!$product instanceof WC_Product) {
			return Helper::error('onemcp_not_found', __('Product was not found.', 'onemcp-pro'));
		}
		if (!current_user_can('edit_post', $product->get_id())) {
			return Helper::error('onemcp_forbidden', __('You cannot edit this product.', 'onemcp-pro'));
		}
		return $product;
	}

	/**
	 * An order the current user can edit.
	 *
	 * @param mixed $id Order ID.
	 * @return WC_Order|WP_Error
	 */
	private static function order($id) {
		if (!function_exists('wc_get_order')) {
			return self::missing();
		}
		$order = wc_get_order(Helper::absint($id));
		if (!$order instanceof WC_Order) {
			return Helper::error('onemcp_not_found', __('Order was not found.', 'onemcp-pro'));
		}
		if (!current_user_can('edit_shop_order', $order->get_id()) && !current_user_can('manage_woocommerce')) {
			return Helper::error('onemcp_forbidden', __('You cannot edit this order.', 'onemcp-pro'));
		}
		return $order;
	}

	/**
	 * Product status limited to statuses WooCommerce accepts.
	 *
	 * @param mixed $value Requested status.
	 * @return string
	 */
	private static function product_status($value) {
		$status = sanitize_key(is_scalar($value) ? (string) $value : '');
		if (!in_array($status, array('draft', 'pending', 'private', 'publish'), true)) {
			return 'draft';
		}
		if (($status === 'publish' || $status === 'private') && !current_user_can('publish_products') && !current_user_can('manage_woocommerce')) {
			return 'draft';
		}
		return $status;
	}

	/**
	 * Validate an order status without the wc- prefix.
	 *
	 * @param mixed $value Requested status.
	 * @return string|WP_Error
	 */
	private static function order_status($value) {
		$status = sanitize_key(is_scalar($value) ? (string) $value : '');
		if (strpos($status, 'wc-') === 0) {
			$status = substr($status, 3);
		}
		$known = array();
		if (function_exists('wc_get_order_statuses')) {
			foreach (array_keys(wc_get_order_statuses()) as $key) {
				$known[] = strpos($key, 'wc-') === 0 ? substr($key, 3) : $key;
			}
		}
		if (!in_array($status, $known, true)) {
			return Helper::error('onemcp_invalid', __('That order status is not available.', 'onemcp-pro'));
		}
		return $status;
	}

	/**
	 * Products at or below the WooCommerce low-stock threshold.
	 *
	 * @param array<string,mixed> $args  Product query args.
	 * @param int                 $limit Page size.
	 * @param int                 $page  Page number.
	 * @return array<string,mixed>
	 */
	private static function low_stock_products($args, $limit, $page) {
		$threshold = absint(get_option('woocommerce_notify_low_stock_amount', 2));
		$status    = isset($args['status']) ? (string) $args['status'] : 'any';
		$query     = array(
			'post_type'      => array('product', 'product_variation'),
			'post_status'    => ($status !== '' && $status !== 'any') ? $status : array('publish', 'draft', 'pending', 'private'),
			'posts_per_page' => $limit,
			'paged'          => $page,
			'orderby'        => 'modified',
			'order'          => 'DESC',
			'fields'         => 'ids',
			'meta_query'     => array(
				'relation' => 'AND',
				array(
					'key'   => '_manage_stock',
					'value' => 'yes',
				),
				array(
					'key'     => '_stock',
					'value'   => $threshold,
					'compare' => '<=',
					'type'    => 'NUMERIC',
				),
			),
		);
		if (!empty($args['s'])) {
			$query['s'] = $args['s'];
		}
		if (!empty($args['stock_status'])) {
			$query['meta_query'][] = array(
				'key'   => '_stock_status',
				'value' => $args['stock_status'],
			);
		}
		$posts = new WP_Query($query);
		$items = array();
		foreach ($posts->posts as $post_id) {
			$product = wc_get_product($post_id);
			if ($product instanceof WC_Product) {
				$items[] = self::product_row($product, false);
			}
		}
		return array(
			'items'               => $items,
			'total'               => (int) $posts->found_posts,
			'page'                => $page,
			'low_stock_threshold' => $threshold,
		);
	}

	/**
	 * Recent orders for one customer.
	 *
	 * @param int $user_id Customer user ID.
	 * @return array<int,array<string,mixed>>
	 */
	private static function customer_orders($user_id) {
		if (!function_exists('wc_get_orders')) {
			return array();
		}
		$orders = wc_get_orders(array(
			'customer' => (int) $user_id,
			'limit'    => 10,
			'orderby'  => 'date',
			'order'    => 'DESC',
			'return'   => 'objects',
		));
		$items = array();
		foreach ((array) $orders as $order) {
			if ($order instanceof WC_Order) {
				$items[] = self::order_row($order, false);
			}
		}
		return $items;
	}

	/**
	 * Create a product category.
	 *
	 * @param array<string,mixed> $input Input.
	 * @return array<string,mixed>|WP_Error
	 */
	public static function create_product_category($input) {
		return self::create_term('product_cat', $input, true);
	}

	/**
	 * Product tags.
	 *
	 * @param array<string,mixed> $input Input.
	 * @return array<string,mixed>|WP_Error
	 */
	public static function list_product_tags($input) {
		return self::list_terms('product_tag', $input);
	}

	/**
	 * Create a product tag.
	 *
	 * @param array<string,mixed> $input Input.
	 * @return array<string,mixed>|WP_Error
	 */
	public static function create_product_tag($input) {
		return self::create_term('product_tag', $input, false);
	}

	/**
	 * Global attributes and their terms.
	 *
	 * @return array<string,mixed>|WP_Error
	 */
	public static function list_attributes($input = array()) {
		if (!function_exists('wc_get_attribute_taxonomies')) {
			return self::missing();
		}
		$items = array();
		foreach ((array) wc_get_attribute_taxonomies() as $attribute) {
			if (!is_object($attribute)) {
				continue;
			}
			$slug     = isset($attribute->attribute_name) ? (string) $attribute->attribute_name : '';
			$taxonomy = function_exists('wc_attribute_taxonomy_name') ? wc_attribute_taxonomy_name($slug) : 'pa_' . $slug;
			$terms    = array();
			if (taxonomy_exists($taxonomy)) {
				$found = get_terms(array(
					'taxonomy'   => $taxonomy,
					'hide_empty' => false,
					'number'     => 100,
				));
				if (!is_wp_error($found)) {
					foreach ($found as $term) {
						$terms[] = array(
							'id'   => (int) $term->term_id,
							'name' => $term->name,
							'slug' => $term->slug,
						);
					}
				}
			}
			$items[] = array(
				'id'    => isset($attribute->attribute_id) ? (int) $attribute->attribute_id : 0,
				'name'  => isset($attribute->attribute_label) ? (string) $attribute->attribute_label : $slug,
				'slug'  => $slug,
				'terms' => $terms,
			);
		}
		return array('items' => $items);
	}

	/**
	 * Create a global attribute.
	 *
	 * @param array<string,mixed> $input Input.
	 * @return array<string,mixed>|WP_Error
	 */
	public static function create_attribute($input) {
		if (!function_exists('wc_create_attribute')) {
			return self::missing();
		}
		$name = Helper::text(isset($input['name']) ? $input['name'] : '', 80);
		if ($name === '') {
			return Helper::error('onemcp_invalid', __('Provide an attribute name.', 'onemcp-pro'));
		}
		$slug = sanitize_title(isset($input['slug']) ? (string) $input['slug'] : $name);
		if (strpos($slug, 'pa_') === 0) {
			$slug = substr($slug, 3);
		}
		$slug = substr($slug, 0, 28);
		if ($slug === '') {
			return Helper::error('onemcp_invalid', __('Provide an attribute slug.', 'onemcp-pro'));
		}
		$created = wc_create_attribute(array(
			'name'         => $name,
			'slug'         => $slug,
			'type'         => 'select',
			'order_by'     => 'menu_order',
			'has_archives' => false,
		));
		if (is_wp_error($created)) {
			return $created;
		}
		delete_transient('wc_attribute_taxonomies');
		$taxonomy = self::register_attribute_taxonomy($slug, $name);
		return array(
			'id'       => (int) $created,
			'name'     => $name,
			'slug'     => $slug,
			'taxonomy' => $taxonomy,
		);
	}

	/**
	 * Add a term to a global attribute.
	 *
	 * @param array<string,mixed> $input Input.
	 * @return array<string,mixed>|WP_Error
	 */
	public static function create_attribute_term($input) {
		$taxonomy = self::global_attribute_taxonomy(isset($input['attribute']) ? $input['attribute'] : '');
		if ($taxonomy === '') {
			return Helper::error('onemcp_not_found', __('That attribute was not found. Create it first.', 'onemcp-pro'));
		}
		$name = Helper::text(isset($input['name']) ? $input['name'] : '', 80);
		if ($name === '') {
			return Helper::error('onemcp_invalid', __('Provide a term name.', 'onemcp-pro'));
		}
		$args = array();
		if (!empty($input['slug'])) {
			$args['slug'] = sanitize_title(Helper::text($input['slug'], 80));
		}
		$created = wp_insert_term($name, $taxonomy, $args);
		if (is_wp_error($created)) {
			return $created;
		}
		$term = get_term((int) $created['term_id'], $taxonomy);
		if (!$term instanceof WP_Term) {
			return Helper::error('onemcp_failed', __('The attribute term was not created.', 'onemcp-pro'));
		}
		return array(
			'id'        => (int) $term->term_id,
			'name'      => $term->name,
			'slug'      => $term->slug,
			'taxonomy'  => $taxonomy,
		);
	}

	/**
	 * Replace attributes on one product.
	 *
	 * @param array<string,mixed> $input Input.
	 * @return array<string,mixed>|WP_Error
	 */
	public static function set_product_attributes($input) {
		if (!class_exists('WC_Product_Attribute')) {
			return self::missing();
		}
		$product = self::product(isset($input['id']) ? $input['id'] : 0);
		if (is_wp_error($product)) {
			return $product;
		}
		$rows = isset($input['attributes']) ? $input['attributes'] : array();
		if (!is_array($rows) || $rows === array()) {
			return Helper::error('onemcp_invalid', __('Provide at least one attribute.', 'onemcp-pro'));
		}
		$objects         = array();
		$wants_variation = false;
		$position        = 0;
		foreach ($rows as $row) {
			if (!is_array($row)) {
				continue;
			}
			$name    = Helper::text(isset($row['name']) ? $row['name'] : '', 80);
			$options = array();
			foreach ((isset($row['options']) && is_array($row['options'])) ? $row['options'] : array() as $option) {
				$option = Helper::text($option, 80);
				if ($option !== '') {
					$options[] = $option;
				}
			}
			$options = array_values(array_unique($options));
			if ($name === '' || $options === array()) {
				return Helper::error('onemcp_invalid', __('Each attribute needs a name and at least one option.', 'onemcp-pro'));
			}
			$variation = !array_key_exists('variation', $row) || Helper::bool($row['variation']);
			$visible   = !array_key_exists('visible', $row) || Helper::bool($row['visible']);
			if ($variation) {
				$wants_variation = true;
			}
			$attribute = new WC_Product_Attribute();
			$taxonomy  = self::global_attribute_taxonomy($name);
			if ($taxonomy !== '') {
				$term_ids = array();
				foreach ($options as $option) {
					$term = self::ensure_attribute_term($taxonomy, $option);
					if (is_wp_error($term)) {
						return $term;
					}
					$term_ids[] = (int) $term->term_id;
				}
				$attribute->set_id(function_exists('wc_attribute_taxonomy_id_by_name') ? (int) wc_attribute_taxonomy_id_by_name($taxonomy) : 0);
				$attribute->set_name($taxonomy);
				$attribute->set_options($term_ids);
			} else {
				$attribute->set_id(0);
				$attribute->set_name($name);
				$attribute->set_options($options);
			}
			$attribute->set_position($position);
			$attribute->set_visible($visible);
			$attribute->set_variation($variation);
			$objects[] = $attribute;
			$position++;
		}
		if ($wants_variation) {
			$product = self::ensure_variable($product);
			if (is_wp_error($product)) {
				return $product;
			}
		}
		$product->set_attributes($objects);
		$product->save();
		return self::attribute_payload($product);
	}

	/**
	 * Create one variation.
	 *
	 * @param array<string,mixed> $input Input.
	 * @return array<string,mixed>|WP_Error
	 */
	public static function create_variation($input) {
		if (!class_exists('WC_Product_Variation') || !class_exists('WC_Product_Attribute')) {
			return self::missing();
		}
		$parent = self::product(isset($input['id']) ? $input['id'] : 0);
		if (is_wp_error($parent)) {
			return $parent;
		}
		if ($parent->is_type('variation')) {
			return Helper::error('onemcp_invalid', __('Add variations to the parent product, not to a variation.', 'onemcp-pro'));
		}
		$pairs = self::attribute_pairs(isset($input['attributes']) ? $input['attributes'] : array());
		if (is_wp_error($pairs)) {
			return $pairs;
		}
		if ($pairs === array()) {
			return Helper::error('onemcp_invalid', __('Provide at least one attribute, such as Size and Large.', 'onemcp-pro'));
		}
		$parent = self::ensure_variable($parent);
		if (is_wp_error($parent)) {
			return $parent;
		}
		$mapped = self::ensure_variation_attributes($parent, $pairs);
		if (is_wp_error($mapped)) {
			return $mapped;
		}
		foreach ($parent->get_children() as $child_id) {
			$child = wc_get_product($child_id);
			if ($child instanceof WC_Product && $child->get_attributes() == $mapped) {
				return Helper::error(
					'onemcp_invalid',
					sprintf(
						/* translators: %d: variation ID */
						__('A variation with those attributes already exists (%d).', 'onemcp-pro'),
						$child->get_id()
					)
				);
			}
		}
		$variation = new WC_Product_Variation();
		$variation->set_parent_id($parent->get_id());
		$variation->set_attributes($mapped);
		$status = isset($input['status']) ? sanitize_key((string) $input['status']) : 'publish';
		$variation->set_status(in_array($status, array('publish', 'private'), true) ? $status : 'publish');
		$applied = self::apply_product($variation, $input);
		if (is_wp_error($applied)) {
			return $applied;
		}
		$variation->save();
		self::sync_variable($parent->get_id());
		return self::variation_row($variation);
	}

	/**
	 * Update one variation.
	 *
	 * @param array<string,mixed> $input Input.
	 * @return array<string,mixed>|WP_Error
	 */
	public static function update_variation($input) {
		$variation = self::product(isset($input['variation_id']) ? $input['variation_id'] : 0);
		if (is_wp_error($variation)) {
			return $variation;
		}
		if (!$variation->is_type('variation')) {
			return Helper::error('onemcp_invalid', __('That product is not a variation.', 'onemcp-pro'));
		}
		if (array_key_exists('status', $input)) {
			$status = sanitize_key((string) $input['status']);
			if (!in_array($status, array('publish', 'private'), true)) {
				return Helper::error('onemcp_invalid', __('Variation status must be publish or private.', 'onemcp-pro'));
			}
			$variation->set_status($status);
		}
		if (!empty($input['attributes'])) {
			$parent = self::product($variation->get_parent_id());
			if (is_wp_error($parent)) {
				return $parent;
			}
			$pairs = self::attribute_pairs($input['attributes']);
			if (is_wp_error($pairs)) {
				return $pairs;
			}
			$mapped = self::ensure_variation_attributes($parent, $pairs);
			if (is_wp_error($mapped)) {
				return $mapped;
			}
			$variation->set_attributes($mapped);
		}
		$applied = self::apply_product($variation, $input);
		if (is_wp_error($applied)) {
			return $applied;
		}
		$variation->save();
		self::sync_variable($variation->get_parent_id());
		return self::variation_row($variation);
	}

	/**
	 * Product reviews.
	 *
	 * @param array<string,mixed> $input Input.
	 * @return array<string,mixed>
	 */
	public static function list_reviews($input) {
		$status = isset($input['status']) ? sanitize_key((string) $input['status']) : 'all';
		if (!in_array($status, array('approve', 'hold', 'spam', 'trash', 'all'), true)) {
			$status = 'all';
		}
		$limit = Helper::limit(isset($input['limit']) ? $input['limit'] : 20, 20, 50);
		$page  = Helper::page($input);
		$args  = array(
			'type'      => 'review',
			'status'    => $status,
			'number'    => $limit,
			'offset'    => ($page - 1) * $limit,
			'post_type' => 'product',
		);
		if (!empty($input['product_id'])) {
			$args['post_id'] = Helper::absint($input['product_id']);
		}
		if (!empty($input['search'])) {
			$args['search'] = Helper::text($input['search'], 120);
		}
		$items = array();
		foreach (get_comments($args) as $comment) {
			if ($comment instanceof WP_Comment) {
				$items[] = self::review_row($comment);
			}
		}
		return array(
			'items' => $items,
			'page'  => $page,
		);
	}

	/**
	 * Moderate one review.
	 *
	 * @param array<string,mixed> $input Input.
	 * @return array<string,mixed>|WP_Error
	 */
	public static function moderate_review($input) {
		$comment = get_comment(Helper::absint(isset($input['id']) ? $input['id'] : 0));
		if (!$comment instanceof WP_Comment) {
			return Helper::error('onemcp_not_found', __('Review was not found.', 'onemcp-pro'));
		}
		$post = get_post($comment->comment_post_ID);
		if (!$post instanceof WP_Post || $post->post_type !== 'product' || ($comment->comment_type !== 'review' && $comment->comment_type !== '')) {
			return Helper::error('onemcp_invalid', __('That comment is not a product review.', 'onemcp-pro'));
		}
		$status = sanitize_key(isset($input['status']) ? (string) $input['status'] : '');
		if (!in_array($status, array('approve', 'hold', 'spam', 'trash'), true)) {
			return Helper::error('onemcp_invalid', __('Status must be approve, hold, spam, or trash.', 'onemcp-pro'));
		}
		wp_set_comment_status($comment->comment_ID, $status);
		$updated = get_comment($comment->comment_ID);
		return $updated instanceof WP_Comment ? self::review_row($updated) : array('id' => (int) $comment->comment_ID, 'status' => $status);
	}

	/**
	 * Refund an order or selected items.
	 *
	 * @param array<string,mixed> $input Input.
	 * @return array<string,mixed>|WP_Error
	 */
	public static function refund_order($input) {
		if (!isset($input['confirm']) || (string) $input['confirm'] !== 'REFUND') {
			return Helper::error('onemcp_invalid', __('Set confirm to REFUND.', 'onemcp-pro'));
		}
		if (!function_exists('wc_create_refund')) {
			return self::missing();
		}
		$order = self::order(isset($input['id']) ? $input['id'] : 0);
		if (is_wp_error($order)) {
			return $order;
		}
		$remaining = (float) $order->get_remaining_refund_amount();
		if ($remaining <= 0) {
			return Helper::error('onemcp_invalid', __('This order has no amount left to refund.', 'onemcp-pro'));
		}
		$args = array(
			'order_id'       => $order->get_id(),
			'reason'         => Helper::text(isset($input['reason']) ? $input['reason'] : '', 200),
			'refund_payment' => array_key_exists('refund_payment', $input) ? Helper::bool($input['refund_payment']) : true,
			'restock_items'  => !empty($input['restock']) && Helper::bool($input['restock']),
		);
		$lines = array();
		if (!empty($input['line_items'])) {
			if (!is_array($input['line_items'])) {
				return Helper::error('onemcp_invalid', __('Line items must be a list.', 'onemcp-pro'));
			}
			foreach ($input['line_items'] as $row) {
				if (!is_array($row)) {
					continue;
				}
				$item_id = absint(isset($row['item_id']) ? $row['item_id'] : 0);
				$item    = $order->get_item($item_id);
				if (!$item) {
					return Helper::error(
						'onemcp_not_found',
						sprintf(
							/* translators: %d: order item ID */
							__('Order item %d was not found.', 'onemcp-pro'),
							$item_id
						)
					);
				}
				$total = isset($row['refund_total']) ? wc_format_decimal((string) $row['refund_total']) : wc_format_decimal($item->get_total());
				$lines[$item_id] = array(
					'qty'          => isset($row['quantity']) ? max(0, (int) $row['quantity']) : 0,
					'refund_total' => $total,
					'refund_tax'   => array(),
				);
			}
			if ($lines === array()) {
				return Helper::error('onemcp_invalid', __('Provide at least one line item to refund.', 'onemcp-pro'));
			}
			$args['line_items'] = $lines;
		}
		if (array_key_exists('amount', $input) && trim((string) $input['amount']) !== '') {
			$amount = (float) wc_format_decimal((string) $input['amount']);
			if ($amount <= 0) {
				return Helper::error('onemcp_invalid', __('The refund amount must be greater than zero.', 'onemcp-pro'));
			}
			if ($amount - $remaining > 0.0001) {
				return Helper::error('onemcp_invalid', __('The refund amount is higher than the amount left on the order.', 'onemcp-pro'));
			}
			$args['amount'] = wc_format_decimal((string) $input['amount']);
		} elseif ($lines === array()) {
			$args['amount'] = wc_format_decimal($remaining);
		}
		$refund = wc_create_refund($args);
		if (is_wp_error($refund)) {
			return $refund;
		}
		$fresh = wc_get_order($order->get_id());
		return array(
			'refund_id'    => $refund->get_id(),
			'amount'       => $refund->get_amount(),
			'order_id'     => $order->get_id(),
			'order_status' => $fresh instanceof WC_Order ? $fresh->get_status() : $order->get_status(),
			'remaining'    => $fresh instanceof WC_Order ? $fresh->get_remaining_refund_amount() : '',
		);
	}

	/**
	 * Currency, tax, shipping, and payment configuration.
	 *
	 * @return array<string,mixed>|WP_Error
	 */
	public static function store_settings($input = array()) {
		if (!function_exists('get_woocommerce_currency')) {
			return self::missing();
		}
		$currency = array(
			'code'               => get_woocommerce_currency(),
			'symbol'             => function_exists('get_woocommerce_currency_symbol') ? get_woocommerce_currency_symbol() : '',
			'position'           => get_option('woocommerce_currency_pos'),
			'thousand_separator' => get_option('woocommerce_price_thousand_sep'),
			'decimal_separator'  => get_option('woocommerce_price_decimal_sep'),
			'decimals'           => function_exists('wc_get_price_decimals') ? wc_get_price_decimals() : (int) get_option('woocommerce_price_num_decimals', 2),
		);
		$tax = array(
			'enabled'            => function_exists('wc_tax_enabled') ? (bool) wc_tax_enabled() : get_option('woocommerce_calc_taxes') === 'yes',
			'prices_include_tax' => function_exists('wc_prices_include_tax') ? (bool) wc_prices_include_tax() : false,
			'tax_based_on'       => (string) get_option('woocommerce_tax_based_on'),
			'shipping_tax_class' => (string) get_option('woocommerce_shipping_tax_class'),
			'display_shop'       => (string) get_option('woocommerce_tax_display_shop'),
			'display_cart'       => (string) get_option('woocommerce_tax_display_cart'),
			'classes'            => class_exists('WC_Tax') ? WC_Tax::get_tax_classes() : array(),
		);
		return array(
			'currency' => $currency,
			'tax'      => $tax,
			'shipping' => self::shipping_settings(),
			'payments' => self::payment_settings(),
		);
	}

	/**
	 * Variation payload.
	 *
	 * @param WC_Product $variation Variation.
	 * @return array<string,mixed>
	 */
	private static function variation_row($variation) {
		$image_id = (int) $variation->get_image_id();
		return array(
			'id'                => $variation->get_id(),
			'parent_id'         => $variation->get_parent_id(),
			'sku'               => $variation->get_sku(),
			'status'            => $variation->get_status(),
			'regular_price'     => $variation->get_regular_price(),
			'sale_price'        => $variation->get_sale_price(),
			'date_on_sale_from' => self::wc_date($variation->get_date_on_sale_from()),
			'date_on_sale_to'   => self::wc_date($variation->get_date_on_sale_to()),
			'stock_status'      => $variation->get_stock_status(),
			'stock_quantity'    => $variation->get_stock_quantity(),
			'manage_stock'      => (bool) $variation->get_manage_stock(),
			'backorders'        => $variation->get_backorders(),
			'image_id'          => $image_id,
			'attributes'        => $variation->get_attributes(),
		);
	}

	/**
	 * Attribute payload for one product.
	 *
	 * @param WC_Product $product Product.
	 * @return array<string,mixed>
	 */
	private static function attribute_payload($product) {
		$items = array();
		foreach ($product->get_attributes() as $attribute) {
			if (!class_exists('WC_Product_Attribute') || !$attribute instanceof WC_Product_Attribute) {
				continue;
			}
			$options = array();
			if ($attribute->is_taxonomy()) {
				foreach ($attribute->get_options() as $term_id) {
					$term = get_term((int) $term_id);
					if ($term instanceof WP_Term) {
						$options[] = $term->name;
					}
				}
			} else {
				foreach ($attribute->get_options() as $option) {
					$options[] = (string) $option;
				}
			}
			$items[] = array(
				'name'      => $attribute->get_name(),
				'options'   => $options,
				'variation' => (bool) $attribute->get_variation(),
				'visible'   => (bool) $attribute->get_visible(),
			);
		}
		return array(
			'id'         => $product->get_id(),
			'type'       => $product->get_type(),
			'attributes' => $items,
		);
	}

	/**
	 * Review payload.
	 *
	 * @param WP_Comment $comment Review.
	 * @return array<string,mixed>
	 */
	private static function review_row($comment) {
		return array(
			'id'         => (int) $comment->comment_ID,
			'product_id' => (int) $comment->comment_post_ID,
			'product'    => html_entity_decode(get_the_title($comment->comment_post_ID), ENT_QUOTES, 'UTF-8'),
			'author'     => $comment->comment_author,
			'email'      => $comment->comment_author_email,
			'content'    => Helper::trim_text($comment->comment_content, 2000),
			'rating'     => (int) get_comment_meta($comment->comment_ID, 'rating', true),
			'status'     => (string) wp_get_comment_status($comment),
			'date'       => $comment->comment_date_gmt ? mysql2date('c', $comment->comment_date_gmt, false) : '',
		);
	}

	/**
	 * Shipping zones and methods, without credentials.
	 *
	 * @return array<string,mixed>
	 */
	private static function shipping_settings() {
		$zones = array();
		if (!class_exists('WC_Shipping_Zones')) {
			return array(
				'enabled' => get_option('woocommerce_ship_to_countries') !== 'disabled',
				'zones'   => $zones,
			);
		}
		$raw = WC_Shipping_Zones::get_zones();
		if (class_exists('WC_Shipping_Zone')) {
			$rest = new WC_Shipping_Zone(0);
			$raw[] = array(
				'id'               => 0,
				'zone_name'        => $rest->get_zone_name(),
				'zone_locations'   => $rest->get_zone_locations(),
				'shipping_methods' => $rest->get_shipping_methods(),
			);
		}
		foreach ($raw as $zone) {
			if (!is_array($zone)) {
				continue;
			}
			$locations = array();
			foreach ((array) (isset($zone['zone_locations']) ? $zone['zone_locations'] : array()) as $location) {
				if (!is_object($location)) {
					continue;
				}
				$locations[] = array(
					'code' => isset($location->code) ? (string) $location->code : '',
					'type' => isset($location->type) ? (string) $location->type : '',
				);
			}
			$methods = array();
			foreach ((array) (isset($zone['shipping_methods']) ? $zone['shipping_methods'] : array()) as $method) {
				if (!is_object($method) || !method_exists($method, 'get_title')) {
					continue;
				}
				$methods[] = array(
					'id'          => isset($method->id) ? (string) $method->id : '',
					'instance_id' => isset($method->instance_id) ? (int) $method->instance_id : 0,
					'title'       => (string) $method->get_title(),
					'enabled'     => method_exists($method, 'is_enabled') ? (bool) $method->is_enabled() : false,
				);
			}
			$zones[] = array(
				'id'        => isset($zone['id']) ? (int) $zone['id'] : 0,
				'name'      => isset($zone['zone_name']) ? (string) $zone['zone_name'] : '',
				'locations' => $locations,
				'methods'   => $methods,
			);
		}
		return array(
			'enabled' => get_option('woocommerce_ship_to_countries') !== 'disabled',
			'zones'   => $zones,
		);
	}

	/**
	 * Payment methods, without secrets.
	 *
	 * @return array<int,array<string,mixed>>
	 */
	private static function payment_settings() {
		$items = array();
		if (!function_exists('WC') || !WC() || !WC()->payment_gateways()) {
			return $items;
		}
		foreach ((array) WC()->payment_gateways()->payment_gateways() as $gateway) {
			if (!is_object($gateway) || !method_exists($gateway, 'get_title')) {
				continue;
			}
			$items[] = array(
				'id'           => isset($gateway->id) ? (string) $gateway->id : '',
				'title'        => (string) $gateway->get_title(),
				'method_title' => method_exists($gateway, 'get_method_title') ? (string) $gateway->get_method_title() : '',
				'enabled'      => isset($gateway->enabled) && $gateway->enabled === 'yes',
				'description'  => method_exists($gateway, 'get_description') ? Helper::trim_text($gateway->get_description(), 300) : '',
			);
		}
		return $items;
	}

	/**
	 * List terms in one product taxonomy.
	 *
	 * @param string              $taxonomy Taxonomy.
	 * @param array<string,mixed> $input    Input.
	 * @return array<string,mixed>|WP_Error
	 */
	private static function list_terms($taxonomy, $input) {
		$limit = Helper::limit(isset($input['limit']) ? $input['limit'] : 20, 20, 50);
		$page  = Helper::page($input);
		$args  = array(
			'taxonomy'   => $taxonomy,
			'hide_empty' => false,
			'number'     => $limit,
			'offset'     => ($page - 1) * $limit,
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
				'id'    => (int) $term->term_id,
				'name'  => $term->name,
				'slug'  => $term->slug,
				'count' => (int) $term->count,
			);
		}
		return array(
			'items' => $items,
			'page'  => $page,
		);
	}

	/**
	 * Create a product category or tag.
	 *
	 * @param string              $taxonomy Taxonomy.
	 * @param array<string,mixed> $input    Input.
	 * @param bool                $parent   Accept a parent term.
	 * @return array<string,mixed>|WP_Error
	 */
	private static function create_term($taxonomy, $input, $parent) {
		$name = Helper::text(isset($input['name']) ? $input['name'] : '', 200);
		if ($name === '') {
			return Helper::error('onemcp_invalid', __('Provide a name.', 'onemcp-pro'));
		}
		$args = array();
		if (!empty($input['slug'])) {
			$args['slug'] = sanitize_title(Helper::text($input['slug'], 200));
		}
		if (isset($input['description'])) {
			$args['description'] = Helper::text($input['description'], 2000);
		}
		if ($parent && !empty($input['parent'])) {
			$parent_id = absint($input['parent']);
			$existing  = get_term($parent_id, $taxonomy);
			if (!$existing instanceof WP_Term) {
				return Helper::error('onemcp_not_found', __('The parent category was not found.', 'onemcp-pro'));
			}
			$args['parent'] = $parent_id;
		}
		$created = wp_insert_term($name, $taxonomy, $args);
		if (is_wp_error($created)) {
			return $created;
		}
		$term = get_term((int) $created['term_id'], $taxonomy);
		if (!$term instanceof WP_Term) {
			return Helper::error('onemcp_failed', __('The term was not created.', 'onemcp-pro'));
		}
		return array(
			'id'          => (int) $term->term_id,
			'name'        => $term->name,
			'slug'        => $term->slug,
			'parent'      => (int) $term->parent,
			'description' => $term->description,
		);
	}

	/**
	 * Turn a simple product into a variable product.
	 *
	 * @param WC_Product $product Product.
	 * @return WC_Product|WP_Error
	 */
	private static function ensure_variable($product) {
		if ($product->is_type('variable')) {
			return $product;
		}
		if (!$product->is_type('simple')) {
			return Helper::error('onemcp_invalid', __('Variations can be added to a simple product.', 'onemcp-pro'));
		}
		wp_set_object_terms($product->get_id(), 'variable', 'product_type');
		clean_post_cache($product->get_id());
		wc_delete_product_transients($product->get_id());
		$reloaded = wc_get_product($product->get_id());
		if (!$reloaded instanceof WC_Product) {
			return self::missing();
		}
		return $reloaded;
	}

	/**
	 * Make sure each variation attribute exists on the parent.
	 *
	 * @param WC_Product                              $parent Parent product.
	 * @param array<int,array{name:string,value:string}> $pairs Attribute pairs.
	 * @return array<string,string>|WP_Error Map used by the variation.
	 */
	private static function ensure_variation_attributes($parent, $pairs) {
		if (!class_exists('WC_Product_Attribute')) {
			return self::missing();
		}
		$existing = $parent->get_attributes();
		$mapped   = array();
		foreach ($pairs as $pair) {
			$taxonomy = self::global_attribute_taxonomy($pair['name']);
			if ($taxonomy !== '') {
				$term = self::ensure_attribute_term($taxonomy, $pair['value']);
				if (is_wp_error($term)) {
					return $term;
				}
				$key          = $taxonomy;
				$mapped[$key] = $term->slug;
				$attribute    = (isset($existing[$key]) && $existing[$key] instanceof WC_Product_Attribute) ? $existing[$key] : null;
				if (!$attribute) {
					$attribute = new WC_Product_Attribute();
					$attribute->set_id(function_exists('wc_attribute_taxonomy_id_by_name') ? (int) wc_attribute_taxonomy_id_by_name($taxonomy) : 0);
					$attribute->set_name($taxonomy);
					$attribute->set_options(array());
					$attribute->set_position(count($existing));
					$attribute->set_visible(true);
					$attribute->set_variation(true);
				}
				$options = array_map('intval', $attribute->get_options());
				if (!in_array((int) $term->term_id, $options, true)) {
					$options[] = (int) $term->term_id;
					$attribute->set_options($options);
				}
				$attribute->set_variation(true);
				$existing[$key] = $attribute;
				continue;
			}
			$key          = sanitize_title($pair['name']);
			$mapped[$key] = $pair['value'];
			$attribute    = (isset($existing[$key]) && $existing[$key] instanceof WC_Product_Attribute) ? $existing[$key] : null;
			if (!$attribute) {
				$attribute = new WC_Product_Attribute();
				$attribute->set_id(0);
				$attribute->set_name($pair['name']);
				$attribute->set_options(array());
				$attribute->set_position(count($existing));
				$attribute->set_visible(true);
				$attribute->set_variation(true);
			}
			$options = $attribute->get_options();
			if (!in_array($pair['value'], $options, true)) {
				$options[] = $pair['value'];
				$attribute->set_options($options);
			}
			$attribute->set_variation(true);
			$existing[$key] = $attribute;
		}
		$parent->set_attributes($existing);
		$parent->save();
		return $mapped;
	}

	/**
	 * Normalize variation attribute pairs.
	 *
	 * @param mixed $value Raw attributes.
	 * @return array<int,array{name:string,value:string}>|WP_Error
	 */
	private static function attribute_pairs($value) {
		if (!is_array($value)) {
			return Helper::error('onemcp_invalid', __('Provide attributes as a list of name and value.', 'onemcp-pro'));
		}
		$pairs = array();
		foreach ($value as $row) {
			if (!is_array($row)) {
				continue;
			}
			$name  = Helper::text(isset($row['name']) ? $row['name'] : '', 80);
			$option = Helper::text(isset($row['value']) ? $row['value'] : '', 80);
			if ($name === '' || $option === '') {
				continue;
			}
			$pairs[] = array(
				'name'  => $name,
				'value' => $option,
			);
		}
		return $pairs;
	}

	/**
	 * Global attribute taxonomy for a name or slug, registered for this request when needed.
	 *
	 * @param mixed $name Attribute name or slug.
	 * @return string Taxonomy name, or an empty string.
	 */
	private static function global_attribute_taxonomy($name) {
		$slug = sanitize_title(is_scalar($name) ? (string) $name : '');
		if ($slug === '' || !function_exists('wc_get_attribute_taxonomies')) {
			return '';
		}
		foreach ((array) wc_get_attribute_taxonomies() as $attribute) {
			if (!is_object($attribute) || empty($attribute->attribute_name)) {
				continue;
			}
			$attribute_slug = (string) $attribute->attribute_name;
			$taxonomy       = function_exists('wc_attribute_taxonomy_name') ? wc_attribute_taxonomy_name($attribute_slug) : 'pa_' . $attribute_slug;
			$label          = isset($attribute->attribute_label) ? sanitize_title((string) $attribute->attribute_label) : '';
			if (!in_array($slug, array($attribute_slug, $taxonomy, $label), true)) {
				continue;
			}
			$label_text = isset($attribute->attribute_label) ? (string) $attribute->attribute_label : $attribute_slug;
			self::register_attribute_taxonomy($attribute_slug, $label_text);
			return $taxonomy;
		}
		return '';
	}

	/**
	 * Register one attribute taxonomy for the current request.
	 *
	 * @param string $slug  Attribute slug without pa_.
	 * @param string $label Human label.
	 * @return string
	 */
	private static function register_attribute_taxonomy($slug, $label) {
		$taxonomy = function_exists('wc_attribute_taxonomy_name') ? wc_attribute_taxonomy_name($slug) : 'pa_' . $slug;
		if (!taxonomy_exists($taxonomy)) {
			register_taxonomy($taxonomy, array('product'), array(
				'hierarchical' => false,
				'label'        => $label,
				'public'       => false,
				'show_ui'      => false,
				'query_var'    => false,
				'rewrite'      => false,
			));
		}
		return $taxonomy;
	}

	/**
	 * Find or create an attribute term.
	 *
	 * @param string $taxonomy Taxonomy.
	 * @param string $value    Term name.
	 * @return WP_Term|WP_Error
	 */
	private static function ensure_attribute_term($taxonomy, $value) {
		$value = Helper::text($value, 80);
		if ($value === '') {
			return Helper::error('onemcp_invalid', __('Provide an attribute value.', 'onemcp-pro'));
		}
		$existing = get_term_by('slug', sanitize_title($value), $taxonomy);
		if (!$existing instanceof WP_Term) {
			$existing = get_term_by('name', $value, $taxonomy);
		}
		if ($existing instanceof WP_Term) {
			return $existing;
		}
		$created = wp_insert_term($value, $taxonomy);
		if (is_wp_error($created)) {
			return $created;
		}
		$term = get_term((int) $created['term_id'], $taxonomy);
		if (!$term instanceof WP_Term) {
			return Helper::error('onemcp_failed', __('The attribute term was not created.', 'onemcp-pro'));
		}
		return $term;
	}

	/**
	 * Sync a variable product after a variation change.
	 *
	 * @param int $product_id Parent product ID.
	 * @return void
	 */
	private static function sync_variable($product_id) {
		if (class_exists('WC_Product_Variable')) {
			WC_Product_Variable::sync((int) $product_id);
		}
		if (function_exists('wc_delete_product_transients')) {
			wc_delete_product_transients((int) $product_id);
		}
	}

	/**
	 * Sale date, or an error when the text is not a date.
	 *
	 * @param mixed $value Raw date.
	 * @return string|WP_Error Empty string clears the date.
	 */
	private static function sale_date($value) {
		$date = Helper::date($value);
		if ($date === null) {
			return Helper::error('onemcp_invalid', __('Use a sale date in YYYY-MM-DD format.', 'onemcp-pro'));
		}
		return $date;
	}

	/**
	 * Y-m-d from a WooCommerce date, or an empty string.
	 *
	 * @param mixed $value Date object.
	 * @return string
	 */
	private static function wc_date($value) {
		if ($value instanceof DateTimeInterface) {
			return $value->format('Y-m-d');
		}
		return '';
	}

	/**
	 * Coupon expiry date.
	 *
	 * @param mixed $value Raw date.
	 * @return string|WP_Error Empty string clears the date.
	 */
	private static function coupon_expiry($value) {
		$date = Helper::date($value);
		if ($date === null) {
			return Helper::error('onemcp_invalid', __('Use an expiry date in YYYY-MM-DD format.', 'onemcp-pro'));
		}
		return $date;
	}

	/**
	 * One image attachment.
	 *
	 * @param mixed $value       Attachment ID.
	 * @param bool  $allow_clear True when 0 removes the image.
	 * @return int|WP_Error
	 */
	private static function image_id($value, $allow_clear) {
		$id = (int) $value;
		if ($id === 0 && $allow_clear) {
			return 0;
		}
		if ($id < 1 || !wp_attachment_is_image($id)) {
			return Helper::error(
				'onemcp_invalid',
				sprintf(
					/* translators: %d: attachment ID */
					__('Attachment %d is not an image.', 'onemcp-pro'),
					$id
				)
			);
		}
		return $id;
	}

	/**
	 * Image attachment IDs.
	 *
	 * @param mixed $value Raw list.
	 * @return array<int,int>|WP_Error
	 */
	private static function image_ids($value) {
		if (!is_array($value)) {
			return Helper::error('onemcp_invalid', __('Send gallery images as a list of attachment IDs.', 'onemcp-pro'));
		}
		$ids = array();
		foreach ($value as $item) {
			$checked = self::image_id($item, false);
			if (is_wp_error($checked)) {
				return $checked;
			}
			if ($checked > 0) {
				$ids[] = $checked;
			}
		}
		return array_values(array_unique($ids));
	}

	/**
	 * Term IDs that exist in one taxonomy.
	 *
	 * @param mixed  $value    Raw list.
	 * @param string $taxonomy product_cat or product_tag.
	 * @return array<int,int>|WP_Error
	 */
	private static function term_ids($value, $taxonomy) {
		if (!is_array($value)) {
			return Helper::error('onemcp_invalid', __('Send a list of term IDs.', 'onemcp-pro'));
		}
		$ids = array();
		foreach (Helper::int_list($value) as $id) {
			$term = get_term($id, $taxonomy);
			if (!$term instanceof WP_Term) {
				return Helper::error(
					'onemcp_not_found',
					sprintf(
						$taxonomy === 'product_tag'
							/* translators: %d: tag ID */
							? __('Tag %d was not found.', 'onemcp-pro')
							/* translators: %d: category ID */
							: __('Category %d was not found.', 'onemcp-pro'),
						$id
					)
				);
			}
			$ids[] = $id;
		}
		return $ids;
	}

	/**
	 * WooCommerce is not loaded.
	 *
	 * @return WP_Error
	 */
	private static function missing() {
		return Helper::error('onemcp_unavailable', __('WooCommerce is not available.', 'onemcp-pro'));
	}
}
