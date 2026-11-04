<?php
/**
 * AJAX endpoints for OneMCP.
 *
 * @package OneMCP
 */

namespace ONEMCP;

if (!defined('ABSPATH')) {
	exit;
}

class Ajax {

	/**
	 * Register AJAX hooks.
	 *
	 * @return void
	 */
	static function hooks() {
		add_action('wp_ajax_onemcp_generate_app_password', '\ONEMCP\Ajax::generate_app_password');
		add_action('wp_ajax_onemcp_test_connection', '\ONEMCP\Ajax::test_connection');
	}

	/**
	 * AJAX endpoint to create an application password for the current user.
	 *
	 * @return void
	 */
	static function generate_app_password() {
		check_ajax_referer('onemcp_admin_nonce', 'nonce');

		if (!current_user_can('manage_options')) {
			wp_send_json_error(array('message' => __('Permission denied.', 'onemcp')));
		}

		$user = wp_get_current_user();
		if (!$user || !$user->exists()) {
			wp_send_json_error(array('message' => __('Current user not found.', 'onemcp')));
		}

		if (!class_exists('\WP_Application_Passwords')) {
			require_once ABSPATH . WPINC . '/class-wp-application-passwords.php';
		}

		Server::allow_password_create();

		$name = !empty($_POST['name']) ? sanitize_text_field(wp_unslash($_POST['name'])) : '';
		if ($name === '') {
			// translators: %s: Current date and time (e.g., 2026-10-10 10:22)
			$name = sprintf(__('OneMCP (%s)', 'onemcp'), wp_date('Y-m-d H:i'));
		}

		$created = \WP_Application_Passwords::create_new_application_password($user->ID, array(
			'name'   => $name,
			'app_id' => 'onemcp',
		));

		if (is_wp_error($created)) {
			wp_send_json_error(array('message' => $created->get_error_message()));
		}

		$raw_password     = $created[0];
		$chunked_password = \WP_Application_Passwords::chunk_password($raw_password);
		$auth_token       = base64_encode($user->user_login . ':' . $raw_password);

		wp_send_json_success(array(
			'username'         => $user->user_login,
			'password'         => $raw_password,
			'chunked_password' => $chunked_password,
			'auth_token'       => $auth_token,
			'auth_header'      => 'Basic ' . $auth_token,
			'config_snippet'   => \ONEMCP\Connection::client_snippet($auth_token),
			'name'             => $name,
		));
	}

	/**
	 * AJAX endpoint to test the built-in MCP server connection.
	 *
	 * @return void
	 */
	static function test_connection() {
		check_ajax_referer('onemcp_admin_nonce', 'nonce');

		if (!current_user_can('manage_options')) {
			wp_send_json_error(array('message' => __('Permission denied.', 'onemcp')));
		}

		$result = \ONEMCP\Connection::test();
		$when   = !empty($result['checked_at']) ? wp_date(get_option('date_format') . ' ' . get_option('time_format'), (int) $result['checked_at']) : '';

		wp_send_json_success(array(
			'ok' => !empty($result['ok']),
			'message'    => !empty($result['message']) ? $result['message'] : '',
			'checked_at' => !empty($result['checked_at']) ? (int) $result['checked_at'] : time(),
			'when'       => $when,
		));
	}

}
