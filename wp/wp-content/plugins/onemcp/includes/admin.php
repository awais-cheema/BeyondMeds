<?php
/**
 * Settings screen.
 *
 * @package OneMCP
 */

namespace ONEMCP;

use WP_User;

if (!defined('ABSPATH')) {
	exit;
}

class Admin {

	/**
	 * Admin hooks.
	 *
	 * @return void
	 */
	static function hooks() {
		add_action('admin_menu', '\ONEMCP\Admin::menu');
		add_action('admin_enqueue_scripts', '\ONEMCP\Admin::enqueue_admin_assets');
		add_action('admin_init', '\ONEMCP\Admin::handle');
		add_filter('plugin_action_links_' . plugin_basename(ONEMCP_FILE), array(__CLASS__, 'links'));
	}

	/**
	 * Settings link on the plugins screen.
	 *
	 * @param array<int,string> $links Existing links.
	 * @return array<int,string>
	 */
	static function links($links) {
		$url = admin_url('admin.php?page=onemcp');
		array_unshift($links, '<a href="' . esc_url($url) . '">' . esc_html__('Settings', 'onemcp') . '</a>');
		return $links;
	}

	/**
	 * Register the menu page.
	 *
	 * SitePad: appears under Settings as "MCP" (after Reading).
	 * Otherwise: appears as a top-level "OneMCP" menu with an icon.
	 *
	 * @return void
	 */
	static function menu() {
		if (\ONEMCP\Detector::is_sitepad()) {
			add_options_page(__('MCP', 'onemcp'), __('MCP', 'onemcp'), 'manage_options', 'onemcp', array(__CLASS__, 'render'), 11);
			return;
		}

		$icon_url = ONEMCP_DIR_URL . 'assets/icons/onemcp.svg';
		add_menu_page(__('OneMCP', 'onemcp'), __('OneMCP', 'onemcp'), 'manage_options', 'onemcp', array(__CLASS__, 'render'), $icon_url);
	}

	/**
	 * Load admin assets.
	 *
	 * Uses $_GET['page'] so it works whether OneMCP is a top-level menu
	 * (toplevel_page_onemcp) or a Settings submenu (settings_page_onemcp).
	 *
	 * @param string $hook Current admin page hook.
	 * @return void
	 */
	static function enqueue_admin_assets($hook) {
		// menu.css only styles the top-level admin menu icon, which does
		// not exist on SitePad (OneMCP lives under Settings there).
		if (!\ONEMCP\Detector::is_sitepad()) {
			wp_enqueue_style('onemcp-menu', ONEMCP_DIR_URL . 'assets/css/menu.css', array(), ONEMCP_VERSION);
		}

		$page = isset($_GET['page']) ? sanitize_key(wp_unslash($_GET['page'])) : '';
		if ($page !== 'onemcp') {
			return;
		}

		wp_enqueue_style('onemcp-admin', ONEMCP_DIR_URL . 'assets/css/admin.css', array(), ONEMCP_VERSION);
		wp_enqueue_script('onemcp-admin', ONEMCP_DIR_URL . 'assets/js/admin.js', array(), ONEMCP_VERSION, true);

		$user = wp_get_current_user();
		wp_localize_script('onemcp-admin', 'onemcpData', array(
			'ajaxUrl'   => admin_url('admin-ajax.php'),
			'nonce'     => wp_create_nonce('onemcp_admin_nonce'),
			'username'  => ($user && $user->exists()) ? $user->user_login : '',
			'endpoint'  => \ONEMCP\Connection::endpoint(),
			'clients'   => \ONEMCP\Connection::clients(),
			'i18n'      => array(
				'copied'            => __('Copied!', 'onemcp'),
				'generating'        => __('Generating...', 'onemcp'),
				'generate'          => __('Generate Password', 'onemcp'),
				'passwordGenerated' => __('Application Password generated. Copy it now — it will not be shown again.', 'onemcp'),
				'genericError'      => __('Failed to generate password. Please try again.', 'onemcp'),
				'testing'           => __('Testing server...', 'onemcp'),
				'testServer'        => __('Test MCP server', 'onemcp'),
				'testFailed'        => __('Connection test failed. Please try again.', 'onemcp'),
			),
		));
	}

	/**
	 * Save settings, run a connection test, or clear the log.
	 *
	 * @return void
	 */
	 static function handle() {
		if (!is_admin() || !current_user_can('manage_options') || empty($_POST['onemcp_action'])) {
			return;
		}
		check_admin_referer('onemcp_admin', 'onemcp_nonce');

		$action = sanitize_key(wp_unslash($_POST['onemcp_action']));
		$tab    = 'connection';
		$notice = 'saved';

		if ($action === 'save-abilities') {
			$tab     = 'abilities';
			$enabled = array();
			if (isset($_POST['enabled']) && is_array($_POST['enabled'])) {
				$enabled = array_map('sanitize_text_field', wp_unslash($_POST['enabled']));
			}
			foreach (\ONEMCP\Registry::catalog() as $ability) {
				$on = in_array($ability['id'], $enabled, true);
				\ONEMCP\Settings::set_enabled($ability['id'], $on, !empty($ability['readonly']));
			}
		} elseif ($action === 'bulk') {
			$tab  = 'abilities';
			$mode = isset($_POST['mode']) ? sanitize_key(wp_unslash($_POST['mode'])) : '';
			\ONEMCP\Settings::bulk($mode);
			$notice = 'bulk';
		} elseif ($action === 'clear-log') {
			$tab = 'activity';
			\ONEMCP\Audit::clear();
			$notice = 'cleared';
		} elseif ($action === 'test-connection') {
			$tab = 'connection';
			\ONEMCP\Connection::test();
			$notice = 'tested';
		} elseif ($action === 'generate-app-password') {
			$tab  = 'connection';
			$user = wp_get_current_user();
			$name = !empty($_POST['app_name']) ? sanitize_text_field(wp_unslash($_POST['app_name'])) : '';
			if ($name === '') {
				// translators: %s: Current date and time (e.g., 2026-10-10 10:18)
				$name = sprintf(__('OneMCP (%s)', 'onemcp'), wp_date('Y-m-d H:i'));
			}

			if (!class_exists('\WP_Application_Passwords')) {
				require_once ABSPATH . WPINC . '/class-wp-application-passwords.php';
			}

			Server::allow_password_create();

			$created = \WP_Application_Passwords::create_new_application_password($user->ID, array(
				'name'   => $name,
				'app_id' => 'onemcp',
			));

			if (!is_wp_error($created)) {
				set_transient('onemcp_gen_pw_' . $user->ID, array(
					'password' => $created[0],
					'chunked'  => \WP_Application_Passwords::chunk_password($created[0]),
					'token'    => base64_encode($user->user_login . ':' . $created[0]),
					'name'     => $name,
				), 120);
				$notice = 'password-created';
			} else {
				$notice = 'password-error';
			}
		} else {
			return;
		}

		wp_safe_redirect(add_query_arg(array(
			'page'        => 'onemcp',
			'tab'         => $tab,
			'onemcp_msg'  => $notice,
		), admin_url('admin.php')));
		exit;
	}

	/**
	 * Render the screen.
	 *
	 * @return void
	 */
	static function render() {
		if (!current_user_can('manage_options')) {
			return;
		}

		$tab   = isset($_GET['tab']) ? sanitize_key(wp_unslash($_GET['tab'])) : 'connection';
		$tabs  = array(
			'connection' => array(
				'label' => __('Connection', 'onemcp'),
				'icon'  => 'connection',
			),
			'products'   => array(
				'label' => __('Integrations', 'onemcp'),
				'icon'  => 'integrations',
			),
			'abilities'  => array(
				'label' => __('Abilities', 'onemcp'),
				'icon'  => 'abilities',
			),
			'activity'   => array(
				'label' => __('Activity', 'onemcp'),
				'icon'  => 'activity',
			),
		);
		if (\ONEMCP\Pro::active() && !\ONEMCP\Detector::is_sitepad()) {
			$tabs['license'] = array(
				'label' => __('License', 'onemcp'),
				'icon'  => 'key',
			);
		}
		if (!isset($tabs[$tab])) {
			$tab = 'connection';
		}

		//echo '<div class="wrap onemcp-wrap">';
		
		// FormLayer style Header Bar
		echo '<header class="onemcp-topbar">';
		echo '<div class="onemcp-topbar-left">';
		if (!\ONEMCP\Detector::is_sitepad()) {
			echo '<div class="onemcp-brand">';
			echo '<div class="onemcp-brand-icon">' . wp_kses(self::icon('logo'), self::svg_kses()) . '</div>';
			echo '<span class="onemcp-brand-name">' . esc_html__('OneMCP', 'onemcp') . '</span>';
			echo '</div>';
		}

		echo '<nav class="onemcp-nav-tabs" id="onemcp-main-nav">';
		foreach ($tabs as $slug => $data) {
			$url    = '#' . $slug;
			$active = $slug === $tab;
			$class  = 'onemcp-nav-item' . ($active ? ' is-active' : '');
			echo '<a class="' . esc_attr($class) . '" href="' . esc_url($url) . '" data-tab="' . esc_attr($slug) . '">';
			echo wp_kses(self::icon($data['icon']), self::svg_kses());
			echo '<span>' . esc_html($data['label']) . '</span>';
			echo '</a>';
		}
		echo '</nav>';
		echo '</div>';

	//	echo '<div class="wrap onemcp-wrap">';

		echo '<div class="onemcp-topbar-right">';
		echo '<span class="onemcp-version-badge">v' . esc_html(ONEMCP_VERSION) . '</span>';
		echo '</div>';
		echo '</header>';

		self::notice();

		echo '<main class="onemcp-admin-main-content">';
		echo '<div id="onemcp-tab-connection" class="onemcp-tab-content' . ($tab === 'connection' ? ' is-active' : '') . '"' . ($tab === 'connection' ? '' : ' style="display:none;"') . '>';
		echo '<div class="onemcp-panel">';
		self::render_connection();
		echo '</div>';
		echo '</div>';

		echo '<div id="onemcp-tab-products" class="onemcp-tab-content' . ($tab === 'products' ? ' is-active' : '') . '"' . ($tab === 'products' ? '' : ' style="display:none;"') . '>';
		echo '<div class="onemcp-panel">';
		self::render_products();
		echo '</div>';
		echo '</div>';

		echo '<div id="onemcp-tab-abilities" class="onemcp-tab-content' . ($tab === 'abilities' ? ' is-active' : '') . '"' . ($tab === 'abilities' ? '' : ' style="display:none;"') . '>';
		echo '<div class="onemcp-panel">';
		self::render_abilities();
		echo '</div>';
		echo '</div>';

		echo '<div id="onemcp-tab-activity" class="onemcp-tab-content' . ($tab === 'activity' ? ' is-active' : '') . '"' . ($tab === 'activity' ? '' : ' style="display:none;"') . '>';
		echo '<div class="onemcp-panel">';
		self::render_activity();
		echo '</div>';
		echo '</div>';

		if (\ONEMCP\Pro::active() && !\ONEMCP\Detector::is_sitepad()) {
			echo '<div id="onemcp-tab-license" class="onemcp-tab-content' . ($tab === 'license' ? ' is-active' : '') . '"' . ($tab === 'license' ? '' : ' style="display:none;"') . '>';
			do_action('onemcp_render_license_page');
			echo '</div>';
		}
		echo '</main>';
	}

	/**
	 * Status message after a redirect.
	 *
	 * @return void
	 */
	static function notice() {
		$msg = isset($_GET['onemcp_msg']) ? sanitize_key(wp_unslash($_GET['onemcp_msg'])) : '';
		$text = '';
		if ($msg === 'saved' || $msg === 'bulk') {
			$text = __('Abilities updated successfully.', 'onemcp');
		} elseif ($msg === 'cleared') {
			$text = __('Activity log cleared.', 'onemcp');
		} elseif ($msg === 'tested') {
			$text = __('Connection test completed.', 'onemcp');
		} elseif ($msg === 'password-created') {
			$text = __('Application Password generated. Copy it now — it will not be shown again.', 'onemcp');
		}

		if ($text !== '') {
			echo '<div class="onemcp-alert onemcp-alert-success">';
			echo '<span class="onemcp-alert-icon">' . wp_kses(self::icon('check'), self::svg_kses()) . '</span>';
			echo '<span>' . esc_html($text) . '</span>';
			echo '</div>';
		} elseif ($msg === 'password-error') {
			echo '<div class="onemcp-alert onemcp-alert-error">';
			echo '<span class="onemcp-alert-icon">' . wp_kses(self::icon('alert'), self::svg_kses()) . '</span>';
			echo '<span>' . esc_html__('Failed to generate application password.', 'onemcp') . '</span>';
			echo '</div>';
		}
	}

	/**
	 * Connection tab.
	 *
	 * @return void
	 */
	static function render_connection() {
		$ready     = \ONEMCP\Connection::abilities_ready();
		$passwords = \ONEMCP\Connection::passwords_available();
		$test      = \ONEMCP\Connection::last_test();
		$user      = wp_get_current_user();
		$username  = ($user && $user->exists()) ? $user->user_login : '';

		// Check if a password was generated in a non-AJAX POST fallback.
		$initial_password = '';
		$initial_token    = '';
		$user_id          = get_current_user_id();
		$generated_pw     = $user_id ? get_transient('onemcp_gen_pw_' . $user_id) : false;
		if ($generated_pw && is_array($generated_pw)) {
			delete_transient('onemcp_gen_pw_' . $user_id);
			$initial_password = !empty($generated_pw['chunked']) ? $generated_pw['chunked'] : '';
			$initial_token    = !empty($generated_pw['token']) ? $generated_pw['token'] : '';
		}

		// Status cards row
		echo '<div class="onemcp-status-grid">';
		self::status_card(__('MCP server', 'onemcp'), true, __('Answers MCP requests on its own REST URL.', 'onemcp'), 'server');
		self::status_card(__('Abilities API', 'onemcp'), $ready, $ready ? __('WordPress can register native abilities.', 'onemcp') : __('Tools run directly through this plugin.', 'onemcp'), 'abilities');
		$password_detail = __('Clients authenticate with an application password.', 'onemcp');
		if ($passwords && !is_ssl()) {
			$password_detail = __('Clients authenticate with application passwords. SSL/HTTPS is recommended.', 'onemcp');
		} elseif (!$passwords) {
			$password_detail = __('Turn on HTTPS so WordPress can issue application passwords.', 'onemcp');
		}
		self::status_card(__('Application passwords', 'onemcp'), $passwords, $password_detail, 'key');
		echo '</div>';

		// Step 1: Generate or Enter Application Password
		echo '<div class="onemcp-card-box">';
		echo '<div class="onemcp-card-header-row">';
		echo '<div class="onemcp-step-title">';
		echo '<span class="onemcp-step-badge">1</span>';
		echo '<h2 class="onemcp-section-title">' . esc_html__('Step 1: Application Password', 'onemcp') . '</h2>';
		echo '</div>';
		echo '</div>';
		echo '<p class="onemcp-section-desc">' . esc_html__('Generate an application password directly below or paste an existing one. The MCP client configuration in Step 2 updates automatically.', 'onemcp') . '</p>';

		echo '<div class="onemcp-pw-row">';
		echo '<div class="onemcp-pw-field">';
		echo '<label class="onemcp-label" for="onemcp-app-name">' . esc_html__('Password name', 'onemcp') . '</label>';
		echo '<input type="text" id="onemcp-app-name" class="onemcp-input" value="' . esc_attr(sprintf(
    // translators: %s: Current date and time (e.g., 2026-10-10 10:19)
    __('OneMCP (%s)', 'onemcp'), wp_date('Y-m-d H:i'))) . '" placeholder="' . esc_attr__('e.g. OneMCP Client', 'onemcp') . '">';
		echo '</div>';
		echo '<div class="onemcp-pw-action">';
		echo '<button type="button" id="onemcp-btn-generate" class="onemcp-btn onemcp-btn-primary">';
		echo wp_kses(self::icon('key'), self::svg_kses()) . ' ' . esc_html__('Generate Password', 'onemcp');
		echo '</button>';
		echo '</div>';
		echo '</div>';

		$notice_display = $initial_password ? 'display:flex;' : 'display:none;';
		echo '<div id="onemcp-pw-notice" class="onemcp-notice-banner" style="' . esc_attr($notice_display) . '">';
		echo wp_kses(self::icon('check'), self::svg_kses());
		echo '<span id="onemcp-pw-notice-text">' . ($initial_password ? esc_html__('Application Password generated. Copy it now — it will not be shown again.', 'onemcp') : '') . '</span>';
		echo '</div>';

		echo '<div style="margin-top:16px;">';
		echo '<label class="onemcp-label" for="onemcp-app-password">' . sprintf(
			/* translators: %s: current username */
			esc_html__('Application password for user "%s":', 'onemcp'),
			esc_html($username)
		) . '</label>';
		echo '<div class="onemcp-input-group">';
		echo '<input id="onemcp-app-password" class="onemcp-input onemcp-font-mono" type="text" value="' . esc_attr($initial_password) . '" placeholder="' . esc_attr__('Generate a password above or paste your own here...', 'onemcp') . '">';
		echo '<button type="button" class="onemcp-btn onemcp-btn-secondary" data-onemcp-copy="onemcp-app-password" data-copied="' . esc_attr__('Copied!', 'onemcp') . '">';
		echo wp_kses(self::icon('copy'), self::svg_kses()) . ' ' . esc_html__('Copy Password', 'onemcp');
		echo '</button>';
		echo '</div>';
		echo '<p class="description">' . esc_html__('You can generate a new password or paste your own anytime. The configuration below will automatically sync.', 'onemcp') . '</p>';
		echo '</div>';
		echo '</div>';

		// Test Connection (compact UI between Step 1 and Step 2)
		$is_ok = !empty($test['ok']);
		$when  = !empty($test['checked_at']) ? wp_date(get_option('date_format') . ' ' . get_option('time_format'), (int) $test['checked_at']) : '';

		echo '<div class="onemcp-card-box onemcp-test-card">';
		echo '<div class="onemcp-test-bar">';
		echo '<div class="onemcp-test-info">';
		echo '<div class="onemcp-test-badge">' . wp_kses(self::icon('refresh'), self::svg_kses()) . '</div>';
		echo '<div>';
		echo '<h3 class="onemcp-test-title">' . esc_html__('Test Connection', 'onemcp') . '</h3>';
		echo '<p class="onemcp-test-desc">' . esc_html__('Verify that the built-in MCP server is functioning properly and can respond to MCP tool requests.', 'onemcp') . '</p>';
		echo '</div>';
		echo '</div>';
		echo '<div class="onemcp-test-action">';
		echo '<form method="post" class="onemcp-inline-form" style="margin:0;">';
		wp_nonce_field('onemcp_admin', 'onemcp_nonce');
		echo '<input type="hidden" name="onemcp_action" value="test-connection">';
		echo '<button type="submit" id="onemcp-btn-test-connection" class="onemcp-btn onemcp-btn-secondary">';
		echo wp_kses(self::icon('refresh'), self::svg_kses()) . ' ' . esc_html__('Test MCP server', 'onemcp');
		echo '</button>';
		echo '</form>';
		echo '</div>';
		echo '</div>';

		$has_test_msg = !empty($test['message']);
		$alert_style  = $has_test_msg ? 'margin-top:14px;margin-bottom:0;' : 'display:none;margin-top:14px;margin-bottom:0;';
		echo '<div id="onemcp-test-result" class="onemcp-alert ' . ($is_ok ? 'onemcp-alert-success' : 'onemcp-alert-error') . '" style="' . esc_attr($alert_style) . '">';
		echo '<span class="onemcp-alert-icon">' . wp_kses(self::icon($is_ok ? 'check' : 'alert'), self::svg_kses()) . '</span>';
		echo '<span id="onemcp-test-result-text">';
		if ($has_test_msg) {
			echo esc_html($test['message']);
			if ($when !== '') {
				echo ' <span style="opacity:0.8;font-size:12px;">(' . esc_html($when) . ')</span>';
			}
		}
		echo '</span>';
		echo '</div>';
		echo '</div>';

		// Step 2: Copy & Use Client Configuration
		echo '<div class="onemcp-card-box">';
		echo '<div class="onemcp-card-header-row">';
		echo '<div class="onemcp-step-title">';
		echo '<span class="onemcp-step-badge">2</span>';
		echo '<h2 class="onemcp-section-title">' . esc_html__('Step 2: Copy & Use Configuration', 'onemcp') . '</h2>';
		echo '</div>';
		echo '</div>';
		echo '<p class="onemcp-section-desc">' . esc_html__('Select your AI client below to copy its configuration or command. Most clients connect directly to your self-hosted site using your Application Password.', 'onemcp') . '</p>';

		$clients     = \ONEMCP\Connection::clients();
		$first_title = isset($clients[0]['title']) ? (string) $clients[0]['title'] : 'mcp.json';
		echo '<div class="onemcp-client-tabs" role="tablist" aria-label="' . esc_attr__('AI clients', 'onemcp') . '">';
		foreach ($clients as $index => $client) {
			$client_id = isset($client['id']) ? (string) $client['id'] : '';
			$active    = $index === 0;
			echo '<button type="button" class="onemcp-client-tab' . ($active ? ' is-active' : '') . '" role="tab" data-onemcp-client="' . esc_attr($client_id) . '" aria-selected="' . ($active ? 'true' : 'false') . '">';
			echo esc_html(isset($client['label']) ? (string) $client['label'] : $client_id);
			echo '</button>';
		}
		echo '</div>';
		echo '<p class="onemcp-client-path"><span id="onemcp-client-path-label"></span> <code id="onemcp-client-path"></code></p>';

		// Modern IDE preview box
		echo '<div class="onemcp-ide-window">';
		echo '<div class="onemcp-ide-header">';
		echo '<div class="onemcp-ide-dots">';
		echo '<span class="onemcp-dot dot-red"></span>';
		echo '<span class="onemcp-dot dot-yellow"></span>';
		echo '<span class="onemcp-dot dot-green"></span>';
		echo '</div>';
		echo '<span class="onemcp-ide-title" id="onemcp-ide-title">' . esc_html($first_title) . '</span>';
		echo '<button type="button" class="onemcp-ide-copy-btn" data-onemcp-copy="onemcp-config" data-copied="' . esc_attr__('Copied!', 'onemcp') . '">';
		echo wp_kses(self::icon('copy'), self::svg_kses()) . ' ' . esc_html__('Copy', 'onemcp');
		echo '</button>';
		echo '</div>';
		echo '<textarea id="onemcp-config" class="onemcp-code-editor" rows="12" readonly>' . esc_textarea(Connection::client_snippet($initial_token)) . '</textarea>';
		echo '</div>';

		echo '<div class="onemcp-bottom-bar">';
		echo '<button type="button" class="onemcp-btn onemcp-btn-primary onemcp-btn-lg" data-onemcp-copy="onemcp-config" data-copied="' . esc_attr__('Copied Configuration!', 'onemcp') . '">';
		echo wp_kses(self::icon('copy'), self::svg_kses()) . ' ' . esc_html__('Copy Configuration', 'onemcp');
		echo '</button>';
		echo '</div>';
		echo '<ol class="onemcp-client-steps" id="onemcp-client-steps"></ol>';

		// Expandable Credentials Section
		echo '<details class="onemcp-details">';
		echo '<summary>' . wp_kses(self::icon('chevron-down'), self::svg_kses()) . ' ' . esc_html__('View Direct Credentials & Server URL', 'onemcp') . '</summary>';
		echo '<div class="onemcp-details-body">';
		
		echo '<label class="onemcp-label" for="onemcp-endpoint">' . esc_html__('Server URL', 'onemcp') . '</label>';
		echo '<div class="onemcp-input-group">';
		echo '<input id="onemcp-endpoint" class="onemcp-input onemcp-font-mono" type="text" readonly value="' . esc_attr(Connection::endpoint()) . '">';
		echo '<button type="button" class="onemcp-btn onemcp-btn-secondary" data-onemcp-copy="onemcp-endpoint" data-copied="' . esc_attr__('Copied!', 'onemcp') . '">';
		echo wp_kses(self::icon('copy'), self::svg_kses()) . ' ' . esc_html__('Copy URL', 'onemcp');
		echo '</button>';
		echo '</div>';

		echo '<label class="onemcp-label" for="onemcp-auth-header">' . esc_html__('Authorization Header', 'onemcp') . '</label>';
		echo '<div class="onemcp-input-group">';
		echo '<input id="onemcp-auth-header" class="onemcp-input onemcp-font-mono" type="text" readonly value="' . esc_attr($initial_token ? 'Basic ' . $initial_token : '') . '" placeholder="' . esc_attr__('Populates when password is generated or entered above', 'onemcp') . '">';
		echo '<button type="button" class="onemcp-btn onemcp-btn-secondary" data-onemcp-copy="onemcp-auth-header" data-copied="' . esc_attr__('Copied!', 'onemcp') . '">';
		echo wp_kses(self::icon('copy'), self::svg_kses()) . ' ' . esc_html__('Copy Header', 'onemcp');
		echo '</button>';
		echo '</div>';

		echo '<label class="onemcp-label" for="onemcp-username">' . esc_html__('Username', 'onemcp') . '</label>';
		echo '<div class="onemcp-input-group">';
		echo '<input id="onemcp-username" class="onemcp-input onemcp-font-mono" type="text" readonly value="' . esc_attr($username) . '">';
		echo '<button type="button" class="onemcp-btn onemcp-btn-secondary" data-onemcp-copy="onemcp-username" data-copied="' . esc_attr__('Copied!', 'onemcp') . '">';
		echo wp_kses(self::icon('copy'), self::svg_kses()) . ' ' . esc_html__('Copy Username', 'onemcp');
		echo '</button>';
		echo '</div>';
		
		echo '</div>';
		echo '</details>';
		echo '</div>';
	}

	/**
	 * Integration cards.
	 *
	 * @return void
	 */	
	 static function render_products() {
		$sitepad    = Detector::is_sitepad();
		$core_label = Detector::core_label();
		$groups = array(
			'builders' => array('label' => __('Page builders', 'onemcp'), 'icon' => 'integrations'),
			'smtp'     => array('label' => __('SMTP & Mailers', 'onemcp'), 'icon' => 'mail'),
			'seo'      => array('label' => __('SEO Plugins', 'onemcp'), 'icon' => 'abilities'),
			'cache'    => array('label' => __('Cache & Performance', 'onemcp'), 'icon' => 'refresh'),
			'stores'   => array('label' => __('eCommerce & Stores', 'onemcp'), 'icon' => 'server'),
			'backups'  => array('label' => __('Backups & Migration', 'onemcp'), 'icon' => 'save'),
		);
		if ($sitepad) {
			unset($groups['builders'], $groups['stores'], $groups['backups']);
		}
		$cards = Detector::cards();

		// Calculate statistics
		$active_cards = array();
		foreach ($cards as $c) {
			if (!empty($c['active'])) {
				$active_cards[] = $c;
			}
		}
		$active_count    = count($active_cards) + 1; // +1 for the built-in core
		$catalog         = \ONEMCP\Registry::catalog();
		$core_tools      = 0;
		foreach ($catalog as $ability) {
			if (isset($ability['category']) && $ability['category'] === 'onemcp-wordpress') {
				$core_tools++;
			}
		}
		$total_tools     = count($catalog);
		$enabled_tools   = 0;
		foreach ($catalog as $a) {
			if (\ONEMCP\Settings::ability_enabled($a)) {
				$enabled_tools++;
			}
		}

		// 1. Hero Stats Grid
		echo '<div class="onemcp-status-grid" style="margin-bottom:24px;">';
		
		echo '<article class="onemcp-status-card">';
		echo '<div class="onemcp-status-card-header">';
		echo '<div style="display:flex;align-items:center;gap:10px;">';
		echo '<div class="onemcp-status-icon-wrap" style="background:#ecfdf5;color:#059669;">' . wp_kses(self::icon('check'), self::svg_kses()) . '</div>';
		echo '<div><h3 style="margin:0;font-size:18px;font-weight:800;">' . esc_html($active_count) . ' ' . esc_html__('Active', 'onemcp') . '</h3>';
		echo '<span style="font-size:12px;color:#64748b;">' . esc_html__('Connected Integrations', 'onemcp') . '</span></div>';
		echo '</div>';
		echo '<span class="onemcp-badge onemcp-badge-success"><span class="onemcp-pulse-dot"></span> ' . esc_html__('Live', 'onemcp') . '</span>';
		echo '</div>';
		echo '<p style="margin:0;font-size:12px;color:#64748b;">' . esc_html($sitepad ? __('SitePad Core plus GoSMTP, SiteSEO, and SpeedyCache.', 'onemcp') : __('WordPress Core plus any active supported plugins delivering MCP tools to your AI.', 'onemcp')) . '</p>';
		echo '</article>';

		echo '<article class="onemcp-status-card">';
		echo '<div class="onemcp-status-card-header">';
		echo '<div style="display:flex;align-items:center;gap:10px;">';
		echo '<div class="onemcp-status-icon-wrap" style="background:#f0f9ff;color:#0284c7;">' . wp_kses(self::icon('abilities'), self::svg_kses()) . '</div>';
		echo '<div><h3 style="margin:0;font-size:18px;font-weight:800;">' . esc_html($enabled_tools) . ' / ' . esc_html($total_tools) . '</h3>';
		echo '<span style="font-size:12px;color:#64748b;">' . esc_html__('Enabled AI Tools', 'onemcp') . '</span></div>';
		echo '</div>';
		echo '<span class="onemcp-badge onemcp-badge-read">' . esc_html__('Ready', 'onemcp') . '</span>';
		echo '</div>';
		echo '<p style="margin:0;font-size:12px;color:#64748b;">' . esc_html__('Active MCP tools accessible by Claude, Cursor, Windsurf, and other clients.', 'onemcp') . '</p>';
		echo '</article>';

		echo '<article class="onemcp-status-card">';
		echo '<div class="onemcp-status-card-header">';
		echo '<div style="display:flex;align-items:center;gap:10px;">';
		echo '<div class="onemcp-status-icon-wrap" style="background:#f5f3ff;color:#7c3aed;">' . wp_kses(self::icon('integrations'), self::svg_kses()) . '</div>';
		echo '<div><h3 style="margin:0;font-size:18px;font-weight:800;">' . esc_html($sitepad ? (string) count($cards) : '30+') . '</h3>';
		echo '<span style="font-size:12px;color:#64748b;">' . esc_html__('Supported Plugins', 'onemcp') . '</span></div>';
		echo '</div>';
		echo '<span class="onemcp-badge onemcp-badge-muted">' . esc_html__('Zero Config', 'onemcp') . '</span>';
		echo '</div>';
		echo '<p style="margin:0;font-size:12px;color:#64748b;">' . esc_html($sitepad ? __('GoSMTP, SiteSEO, and SpeedyCache.', 'onemcp') : __('Automatic auto-detection for page builders, SEO, mailers, cache, and stores.', 'onemcp')) . '</p>';
		echo '</article>';

		$pro_active = \ONEMCP\Pro::active();
		echo '<article class="onemcp-status-card">';
		echo '<div class="onemcp-status-card-header">';
		echo '<div style="display:flex;align-items:center;gap:10px;">';
		echo '<div class="onemcp-status-icon-wrap" style="background:' . ($pro_active ? '#ede9fe;color:#6d28d9;' : '#f8fafc;color:#64748b;') . '">' . wp_kses(self::icon('sparkles'), self::svg_kses()) . '</div>';
		echo '<div><h3 style="margin:0;font-size:18px;font-weight:800;">' . ($pro_active ? esc_html__('Pro Edition', 'onemcp') : esc_html__('Free Edition', 'onemcp')) . '</h3>';
		echo '<span style="font-size:12px;color:#64748b;">' . ($pro_active ? esc_html__('Plugin Integrations Unlocked', 'onemcp') : esc_html($core_label)) . '</span></div>';
		echo '</div>';
		echo '<span class="onemcp-badge ' . ($pro_active ? 'onemcp-badge-success' : 'onemcp-badge-pro') . '">' . ($pro_active ? esc_html__('Active', 'onemcp') : esc_html__('Pro Available', 'onemcp')) . '</span>';
		echo '</div>';
		if ($sitepad) {
			$edition_detail = $pro_active ? __('GoSMTP, SiteSEO, and SpeedyCache are active.', 'onemcp') : __('Install OneMCP Pro to unlock GoSMTP, SiteSEO, and SpeedyCache.', 'onemcp');
		} else {
			$edition_detail = $pro_active ? __('Page builders, SEO, SMTP, cache, stores, and backups are active.', 'onemcp') : __('Install OneMCP Pro to unlock page builders, SEO, SMTP, cache, stores, and backups.', 'onemcp');
		}
		echo '<p style="margin:0;font-size:12px;color:#64748b;">' . esc_html($edition_detail) . '</p>';
		echo '</article>';

		echo '</div>'; // End Hero Stats

		// 2. Active on This Site Showcase
		echo '<div class="onemcp-card-box" style="margin-bottom:28px;border-left:4px solid var(--onemcp-success);">';
		echo '<div class="onemcp-card-header-row">';
		echo '<div>';
		echo '<h2 class="onemcp-section-title" style="display:flex;align-items:center;gap:8px;">' . wp_kses(self::icon('check-circle'), self::svg_kses()) . ' ' . esc_html__('Active on this Site', 'onemcp') . '</h2>';
		echo '<p class="onemcp-section-desc">' . esc_html__('These services are active and ready. Their tools are currently registered and available for your AI assistant.', 'onemcp') . '</p>';
		echo '</div>';
		echo '<a href="#abilities" class="onemcp-btn onemcp-btn-secondary onemcp-btn-sm">';
		echo wp_kses(self::icon('sliders'), self::svg_kses()) . ' ' . esc_html__('Manage All Abilities', 'onemcp');
		echo '</a>';
		echo '</div>';

		echo '<div class="onemcp-products-grid" style="margin-top:16px;">';
		
		echo '<article class="onemcp-product-card is-active" style="border-color:#a7f3d0;box-shadow:0 4px 12px rgba(16,185,129,0.08);">';
		echo '<div class="onemcp-product-header">';
		echo '<div class="onemcp-product-meta">';
		$wp_info = self::get_product_badge_info('wordpress');
		self::render_avatar($wp_info, $core_label);
		echo '<div class="onemcp-product-title">';
		echo '<h3 class="onemcp-product-name">' . esc_html($core_label) . '</h3>';
		echo '<span class="onemcp-badge onemcp-badge-success"><span class="onemcp-pulse-dot"></span> v' . esc_html(get_bloginfo('version')) . ' ' . esc_html__('Active', 'onemcp') . '</span>';
		echo '</div>';
		echo '</div>';
		echo '<span class="onemcp-badge onemcp-badge-read">' . esc_html(sprintf(
			/* translators: %d: number of built-in tools. */
			__('%d Tools Built-in', 'onemcp'),
			$core_tools
		)) . '</span>';
		echo '</div>';
		$core_desc = $sitepad
			? __('Site information, pages, posts, the visual editor, media, terms, comments, menus, and users.', 'onemcp')
			: __('Site information, posts, pages, media library, terms, comments, menus, and users. Always operational natively.', 'onemcp');
		echo '<p class="onemcp-product-desc">' . esc_html($core_desc) . '</p>';
		echo '<div class="onemcp-product-footer">';
		echo '<span class="onemcp-active-indicator">' . wp_kses(self::icon('check'), self::svg_kses()) . ' ' . esc_html__('Native Core Connected', 'onemcp') . '</span>';
		echo '<a href="#abilities&cat=onemcp-wordpress" class="onemcp-btn onemcp-btn-primary onemcp-btn-sm">';
		echo esc_html__('Configure Tools', 'onemcp') . ' ' . wp_kses(self::icon('chevron-right'), self::svg_kses());
		echo '</a>';
		echo '</div>';
		echo '</article>';

		// Any other active plugins
		foreach ($active_cards as $active_card) {
			$binfo = self::get_product_badge_info($active_card['slug']);
			echo '<article class="onemcp-product-card is-active" style="border-color:#a7f3d0;box-shadow:0 4px 12px rgba(16,185,129,0.08);">';
			echo '<div class="onemcp-product-header">';
			echo '<div class="onemcp-product-meta">';
			self::render_avatar($binfo, $active_card['label']);
			echo '<div class="onemcp-product-title">';
			echo '<h3 class="onemcp-product-name">' . esc_html($active_card['label']) . '</h3>';
			echo '<span class="onemcp-badge onemcp-badge-success"><span class="onemcp-pulse-dot"></span> ' . esc_html($active_card['version'] !== '' ? 'v' . $active_card['version'] : __('Active', 'onemcp')) . '</span>';
			echo '</div>';
			echo '</div>';
			echo '<span class="onemcp-badge onemcp-badge-read">' . esc_html__('Connected', 'onemcp') . '</span>';
			echo '</div>';
			echo '<p class="onemcp-product-desc">' . esc_html($active_card['summary']) . '</p>';
			echo '<div class="onemcp-product-footer">';
			echo '<span class="onemcp-active-indicator">' . wp_kses(self::icon('check'), self::svg_kses()) . ' ' . esc_html__('Auto-Detected', 'onemcp') . '</span>';
			echo '<a href="#abilities" class="onemcp-btn onemcp-btn-primary onemcp-btn-sm">';
			echo esc_html__('Configure Tools', 'onemcp') . ' ' . wp_kses(self::icon('chevron-right'), self::svg_kses());
			echo '</a>';
			echo '</div>';
			echo '</article>';
		}

		echo '</div>'; // End active grid

		if (empty($active_cards)) {
			echo '<div class="onemcp-hint-banner" style="margin-top:16px;">';
			echo wp_kses(self::icon('sparkles'), self::svg_kses());
			$tip = $sitepad
				? __('Tip: Install OneMCP Pro, then activate GoSMTP, SiteSEO, or SpeedyCache. Its tools register automatically.', 'onemcp')
				: __('Tip: Install OneMCP Pro, then activate a supported plugin such as WooCommerce, Elementor, or Yoast SEO. Its MCP tools register automatically.', 'onemcp');
			echo '<span>' . esc_html($tip) . '</span>';
			echo '</div>';
		}
		echo '</div>';

		// 3. Interactive Search & Category Filter Toolbar
		echo '<div class="onemcp-filter-toolbar">';
		echo '<div class="onemcp-search-wrap">';
		echo '<span class="onemcp-search-icon">' . wp_kses(self::icon('search'), self::svg_kses()) . '</span>';
		$search_placeholder = $sitepad
			? __('Search integrations (GoSMTP, SiteSEO, SpeedyCache)...', 'onemcp')
			: __('Search integrations by name or function (e.g. Elementor, WooCommerce, Yoast, SMTP)...', 'onemcp');
		echo '<input type="text" id="onemcp-integrations-search" class="onemcp-search-input" placeholder="' . esc_attr($search_placeholder) . '">';
		echo '<button type="button" class="onemcp-search-clear" id="onemcp-integrations-clear" style="display:none;" title="' . esc_attr__('Clear search', 'onemcp') . '">✕</button>';
		echo '</div>';

		echo '<div class="onemcp-filter-pills" id="onemcp-integrations-pills">';
		echo '<button type="button" class="onemcp-filter-pill is-active" data-group="all">' . esc_html__('All Supported', 'onemcp') . ' (' . count($cards) . ')</button>';
		echo '<button type="button" class="onemcp-filter-pill" data-group="active">' . esc_html__('Active Only', 'onemcp') . ' (' . count($active_cards) . ')</button>';
		foreach ($groups as $grp_key => $grp_meta) {
			$grp_count = 0;
			foreach ($cards as $c) {
				if ($c['group'] === $grp_key) {
					$grp_count++;
				}
			}
			echo '<button type="button" class="onemcp-filter-pill" data-group="' . esc_attr($grp_key) . '">' . esc_html($grp_meta['label']) . ' (' . esc_html($grp_count) . ')</button>';
		}
		echo '</div>';
		echo '</div>';

		echo '<div id="onemcp-integrations-count-text" class="onemcp-results-count">' . esc_html(
			sprintf(
				/* translators: %d: Number of supported integrations. */
				__('Showing %d supported integrations', 'onemcp'),
				count($cards)
			)
		) . '</div>';

		// 4. Supported Product Cards by Group
		echo '<div id="onemcp-integrations-container">';
		foreach ($groups as $group => $meta) {
			$group_cards = array();
			foreach ($cards as $c) {
				if ($c['group'] === $group) {
					$group_cards[] = $c;
				}
			}
			if (empty($group_cards)) {
				continue;
			}

			// Leaders
			$leaders = array(
				'builders' => 'pagelayer',
				'smtp'     => 'gosmtp',
				'cache'    => 'speedycache',
			);
			if (isset($leaders[$group])) {
				$pinned = array();
				$rest   = array();
				foreach ($group_cards as $card) {
					if ($card['slug'] === $leaders[$group]) {
						$pinned[] = $card;
					} else {
						$rest[] = $card;
					}
				}
				$group_cards = array_merge($pinned, $rest);
			}

			echo '<div class="onemcp-category-section" data-group="' . esc_attr($group) . '">';
			echo '<div class="onemcp-category-header">';
			echo '<div style="display:flex;align-items:center;gap:8px;">';
			echo '<span class="onemcp-category-icon-tag">' . wp_kses(self::icon($meta['icon']), self::svg_kses()) . '</span>';
			echo '<h2 class="onemcp-category-title">' . esc_html($meta['label']) . '</h2>';
			echo '</div>';
			echo '<span class="onemcp-count-pill">' . count($group_cards) . '</span>';
			echo '</div>';

			echo '<div class="onemcp-products-grid">';
			foreach ($group_cards as $card) {
				$is_active  = !empty($card['active']);
				$badge_info = self::get_product_badge_info($card['slug']);
				$featured   = !empty($card['featured']);
				$tier       = self::product_tier();

				echo '<article class="onemcp-product-card' . ($is_active ? ' is-active' : '') . ($featured ? ' is-featured' : '') . '" data-slug="' . esc_attr($card['slug']) . '" data-group="' . esc_attr($card['group']) . '" data-active="' . ($is_active ? '1' : '0') . '" data-name="' . esc_attr(strtolower($card['label'] . ' ' . $card['summary'])) . '">';
				echo '<div class="onemcp-product-header">';
				echo '<div class="onemcp-product-meta">';
				self::render_avatar($badge_info, $card['label']);
				echo '<div class="onemcp-product-title">';
				echo '<h3 class="onemcp-product-name">' . esc_html($card['label']) . '</h3>';
				if ($featured) {
					echo '<span class="onemcp-badge onemcp-badge-read">' . esc_html__('Recommended', 'onemcp') . '</span>';
				}
				if (!\ONEMCP\Pro::active() && $tier === 'pro') {
					echo '<span class="onemcp-badge onemcp-badge-pro">' . esc_html__('Pro Only', 'onemcp') . '</span>';
				}
				echo '</div>';
				echo '</div>';

				if ($is_active) {
					$label = $card['version'] !== '' ? 'v' . $card['version'] : __('Active', 'onemcp');
					echo '<span class="onemcp-badge onemcp-badge-success"><span class="onemcp-pulse-dot"></span> ' . esc_html($label) . '</span>';
				} else {
					echo '<span class="onemcp-badge onemcp-badge-muted">' . esc_html__('Supported', 'onemcp') . '</span>';
				}
				echo '</div>';

				echo '<p class="onemcp-product-desc">' . esc_html($card['summary']) . '</p>';

				echo '<div class="onemcp-product-footer">';
				if ($is_active) {
					echo '<span class="onemcp-active-indicator">' . wp_kses(self::icon('check'), self::svg_kses()) . ' ' . esc_html__('Connected', 'onemcp') . '</span>';
					echo '<a class="onemcp-btn onemcp-btn-secondary onemcp-btn-sm" href="#abilities">';
					echo esc_html__('Configure Tools', 'onemcp') . ' ' . wp_kses(self::icon('chevron-right'), self::svg_kses());
					echo '</a>';
				} else {
					echo '<span style="font-size:12px;color:#64748b;display:inline-flex;align-items:center;gap:6px;"><span class="onemcp-dot" style="background:#cbd5e1;width:7px;height:7px;"></span> ' . esc_html__('Auto-connects when active', 'onemcp') . '</span>';
					echo '<a class="onemcp-link-btn" href="' . esc_url($card['search']) . '">';
					echo esc_html__('Learn More', 'onemcp') . ' ' . wp_kses(self::icon('external'), self::svg_kses());
					echo '</a>';
				}
				echo '</div>';

				echo '</article>';
			}
			echo '</div>';
			echo '</div>';
		}
		echo '</div>'; // End integrations container

		// Empty search state
		echo '<div id="onemcp-integrations-empty" class="onemcp-card-box" style="display:none;text-align:center;padding:48px 24px;">';
		echo '<div class="onemcp-empty-icon">' . wp_kses(self::icon('search'), self::svg_kses()) . '</div>';
		echo '<h3 style="font-size:16px;margin:0 0 6px 0;">' . esc_html__('No matching integrations found', 'onemcp') . '</h3>';
		echo '<p style="color:#64748b;font-size:13px;margin:0;">' . esc_html__('Try clearing your search term or filtering by a different category.', 'onemcp') . '</p>';
		echo '</div>';
	}

	/**
	 * Plugin integrations ship in OneMCP Pro. WordPress core stays free.
	 *
	 * @return string
	 */
	private static function product_tier() {
		return 'pro';
	}

	/**
	 * Ability switches.
	 *
	 * @return void
	 */
	static function render_abilities() {
		$catalog    = \ONEMCP\Registry::catalog();
		$categories = \ONEMCP\Registry::categories();
		$reads      = 0;
		$writes     = 0;
		$active_total = 0;
		foreach ($catalog as $ability) {
			$on = \ONEMCP\Settings::ability_enabled($ability);
			if (!$on) {
				continue;
			}
			$active_total++;
			if (!empty($ability['readonly'])) {
				$reads++;
			} else {
				$writes++;
			}
		}

		// 1. Ability Controls Header Box
		echo '<div class="onemcp-card-box" style="margin-bottom:24px;">';
		echo '<div class="onemcp-card-header-row">';
		echo '<div>';
		echo '<h2 class="onemcp-section-title">' . esc_html__('Ability Controls & Permissions', 'onemcp') . '</h2>';
		$abilities_intro = Detector::is_sitepad()
			? __('Choose which SitePad tools your AI assistants can call. SitePad Core and Pagelayer page, post, theme, and widget tools stay on until you turn them off. Other write tools stay off until you turn them on.', 'onemcp')
			: __('Choose which WordPress tools your AI assistants (Claude, Cursor, Windsurf) can call. WordPress core tools and the active page builder\'s page, post, theme, and widget tools stay on until you turn them off. Other write tools stay off until you turn them on.', 'onemcp');
		echo '<p class="onemcp-section-desc">' . esc_html($abilities_intro) . '</p>';
		echo '</div>';
		echo '</div>';

		// FormLayer style metrics & quick toolbar
		echo '<div class="onemcp-toolbar" style="margin-top:16px;">';
		echo '<div class="onemcp-metric-chips">';
		echo '<span class="onemcp-metric-chip onemcp-chip-reads" title="' . esc_attr__('Safe inspection tools that do not alter data', 'onemcp') . '">';
		echo wp_kses(self::icon('shield'), self::svg_kses()) . ' ' . esc_html(sprintf(
		// translators: %d: Number of active read abilities
		__('%d Read (Safe) Active', 'onemcp'), $reads)) . '</span>';

		echo '<span class="onemcp-metric-chip onemcp-chip-writes" title="' . esc_attr__('Tools that can create, update, or delete site data', 'onemcp') . '">';
		echo wp_kses(self::icon('zap'), self::svg_kses()) . ' ' . esc_html(sprintf(
		// translators: %d: Number of active write abilities
		__('%d Write (Actions) Active', 'onemcp'), $writes)) . '</span>';

		echo '<span class="onemcp-metric-chip" style="background:#f5f3ff;color:#7c3aed;border-color:#ddd6fe;">';
		echo wp_kses(self::icon('abilities'), self::svg_kses()) . ' ' . esc_html(sprintf(
		// translators: %d: Total abilities
		__('%d Total Tools', 'onemcp'), count($catalog))) . '</span>';
		echo '</div>';

		echo '<div class="onemcp-bulk-actions">';
		self::bulk_button('enable-reads', __('Enable all reads', 'onemcp'), false);
		self::bulk_button('disable-writes', __('Disable all writes', 'onemcp'), false);
		self::bulk_button('enable-writes', __('Enable all writes', 'onemcp'), true);
		echo '</div>';
		echo '</div>';
		echo '</div>';

		if ($catalog === array()) {
			echo '<div class="onemcp-card-box">';
			echo '<div class="onemcp-empty-state">';
			echo '<div class="onemcp-empty-icon">' . wp_kses(self::icon('abilities'), self::svg_kses()) . '</div>';
			echo '<h3>' . esc_html__('No abilities found', 'onemcp') . '</h3>';
			$empty_abilities = Detector::is_sitepad()
				? __('SitePad Core tools register automatically. Activate GoSMTP, SiteSEO, or SpeedyCache for more.', 'onemcp')
				: __('Activate WordPress or any supported page builder, SMTP, SEO, cache, or store plugin to register abilities.', 'onemcp');
			echo '<p>' . esc_html($empty_abilities) . '</p>';
			echo '</div>';
			echo '</div>';
			return;
		}

		// 2. Interactive Search & Multi-Filter Toolbar
		echo '<div class="onemcp-filter-toolbar">';
		echo '<div class="onemcp-search-wrap">';
		echo '<span class="onemcp-search-icon">' . wp_kses(self::icon('search'), self::svg_kses()) . '</span>';
		echo '<input type="text" id="onemcp-abilities-search" class="onemcp-search-input" placeholder="' . esc_attr__('Search tools by name, description, or slug (e.g. post, media, user, update, seo)...', 'onemcp') . '">';
		echo '<button type="button" class="onemcp-search-clear" id="onemcp-abilities-clear" style="display:none;" title="' . esc_attr__('Clear search', 'onemcp') . '">✕</button>';
		echo '</div>';

		echo '<div class="onemcp-filter-pills" id="onemcp-abilities-type-pills">';
		echo '<button type="button" class="onemcp-filter-pill is-active" data-type="all">' . esc_html__('All Tools', 'onemcp') . ' (' . count($catalog) . ')</button>';
		echo '<button type="button" class="onemcp-filter-pill" data-type="read">' . wp_kses(self::icon('shield'), self::svg_kses()) . ' ' . esc_html__('Read Only (Safe)', 'onemcp') . '</button>';
		echo '<button type="button" class="onemcp-filter-pill" data-type="write">' . wp_kses(self::icon('zap'), self::svg_kses()) . ' ' . esc_html__('Write (Actions)', 'onemcp') . '</button>';
		echo '<button type="button" class="onemcp-filter-pill" data-type="active">' . esc_html__('Active (On)', 'onemcp') . '</button>';
		echo '<button type="button" class="onemcp-filter-pill" data-type="inactive">' . esc_html__('Inactive (Off)', 'onemcp') . '</button>';
		echo '</div>';
		echo '</div>';

		// Category quick tabs
		echo '<div class="onemcp-category-pills-bar">';
		echo '<span style="font-size:12px;font-weight:700;color:#64748b;margin-right:4px;">' . esc_html__('Categories:', 'onemcp') . '</span>';
		echo '<button type="button" class="onemcp-cat-pill is-active" data-cat="all">' . esc_html__('All', 'onemcp') . '</button>';
		foreach ($categories as $slug => $category) {
			$cat_rows = 0;
			foreach ($catalog as $ability) {
				if ($ability['category'] === $slug) {
					$cat_rows++;
				}
			}
			if ($cat_rows === 0) {
				continue;
			}
			echo '<button type="button" class="onemcp-cat-pill" data-cat="' . esc_attr($slug) . '">' . esc_html($category['label']) . ' (' . esc_html($cat_rows) . ')</button>';
		}
		echo '</div>';

		echo '<div id="onemcp-abilities-count-text" class="onemcp-results-count">' . esc_html(
			sprintf(
				/* translators: %d: Number of abilities across all categories. */
				__('Showing %d abilities across all categories', 'onemcp'),
				count($catalog)
			)
		) . '</div>';

		// Form with abilities
		echo '<form method="post" id="onemcp-abilities-form">';
		wp_nonce_field('onemcp_admin', 'onemcp_nonce');
		echo '<input type="hidden" name="onemcp_action" value="save-abilities">';

		echo '<div id="onemcp-abilities-container">';
		foreach ($categories as $slug => $category) {
			$rows = array();
			$cat_active = 0;
			foreach ($catalog as $ability) {
				if ($ability['category'] === $slug) {
					$rows[] = $ability;
					$is_on = \ONEMCP\Settings::ability_enabled($ability);
					if ($is_on) {
						$cat_active++;
					}
				}
			}
			if ($rows === array()) {
				continue;
			}

			echo '<div class="onemcp-category-section" data-cat-slug="' . esc_attr($slug) . '">';
			echo '<div class="onemcp-category-header onemcp-category-toggle-header" data-toggle-target="' . esc_attr($slug) . '">';
			echo '<div style="display:flex;align-items:center;gap:10px;">';
			echo '<button type="button" class="onemcp-accordion-btn" title="' . esc_attr__('Collapse / Expand', 'onemcp') . '">' . wp_kses(self::icon('chevron-down'), self::svg_kses()) . '</button>';
			echo '<h2 class="onemcp-category-title">' . esc_html($category['label']) . '</h2>';
			echo '<span class="onemcp-count-pill" id="cat-count-' . esc_attr($slug) . '">' . esc_html(count($rows)) . ' ' . esc_html__('tools', 'onemcp') . ' • <strong style="color:var(--onemcp-success-dark);">' . esc_html($cat_active) . ' ' . esc_html__('active', 'onemcp') . '</strong></span>';
			echo '</div>';

			echo '<div class="onemcp-cat-actions">';
			echo '<button type="button" class="onemcp-cat-quick-btn" data-cat-action="enable" data-cat="' . esc_attr($slug) . '">' . esc_html__('Enable All', 'onemcp') . '</button>';
			echo '<button type="button" class="onemcp-cat-quick-btn" data-cat-action="disable" data-cat="' . esc_attr($slug) . '">' . esc_html__('Disable All', 'onemcp') . '</button>';
			echo '</div>';
			echo '</div>';

			// Modern styled table with iOS toggle switches
			echo '<div class="onemcp-table-container" id="cat-body-' . esc_attr($slug) . '">';
			echo '<table class="onemcp-table"><thead><tr>';
			echo '<th class="col-check">' . esc_html__('Status', 'onemcp') . '</th>';
			echo '<th>' . esc_html__('Tool / Description', 'onemcp') . '</th>';
			echo '<th class="col-type">' . esc_html__('Permission', 'onemcp') . '</th>';
			echo '</tr></thead><tbody>';

			foreach ($rows as $ability) {
				$readonly = !empty($ability['readonly']);
				$on       = \ONEMCP\Settings::ability_enabled($ability);
				$type_tag = $readonly ? 'read' : 'write';
				$search_data = strtolower($ability['label'] . ' ' . $ability['id'] . ' ' . $ability['description'] . ' ' . $category['label']);

				echo '<tr class="onemcp-ability-row" data-type="' . esc_attr($type_tag) . '" data-active="' . ($on ? '1' : '0') . '" data-search="' . esc_attr($search_data) . '">';

				echo '<td class="col-check">';
				echo '<label class="onemcp-switch" title="' . esc_attr__('Toggle this tool', 'onemcp') . '">';
				echo '<input type="checkbox" class="onemcp-switch-input" name="enabled[]" value="' . esc_attr($ability['id']) . '"' . checked($on, true, false) . ' data-cat="' . esc_attr($slug) . '">';
				echo '<span class="onemcp-switch-slider"></span>';
				echo '</label>';
				echo '</td>';

				echo '<td>';
				echo '<div style="display:flex;align-items:center;gap:8px;flex-wrap:wrap;">';
				echo '<span class="onemcp-ability-title">' . esc_html($ability['label']) . '</span>';
				echo '<span class="onemcp-slug-badge" data-onemcp-copy-text="' . esc_attr($ability['id']) . '" title="' . esc_attr__('Click to copy tool name', 'onemcp') . '">' . esc_html($ability['id']) . ' ' . wp_kses(self::icon('copy'), self::svg_kses()) . '</span>';
				echo '</div>';
				echo '<span class="onemcp-ability-desc">' . esc_html($ability['description']) . '</span>';
				echo '</td>';

				echo '<td>';
				if ($readonly) {
					echo '<span class="onemcp-badge onemcp-badge-read" title="' . esc_attr__('Safe read-only tool', 'onemcp') . '">' . wp_kses(self::icon('shield'), self::svg_kses()) . ' ' . esc_html__('Read (Safe)', 'onemcp') . '</span>';
				} else {
					$destructive = !empty($ability['destructive']);
					if ($destructive) {
						echo '<span class="onemcp-badge" style="background:#fef2f2;color:#dc2626;border:1px solid #fecaca;" title="' . esc_attr__('Can permanently delete or trash items', 'onemcp') . '">' . wp_kses(self::icon('trash'), self::svg_kses()) . ' ' . esc_html__('Destructive', 'onemcp') . '</span>';
					} else {
						echo '<span class="onemcp-badge onemcp-badge-write" title="' . esc_attr__('Can create or update data', 'onemcp') . '">' . wp_kses(self::icon('zap'), self::svg_kses()) . ' ' . esc_html__('Write Action', 'onemcp') . '</span>';
					}
				}
				echo '</td>';
				echo '</tr>';
			}

			echo '</tbody></table>';
			echo '</div>';
			echo '</div>';
		}
		echo '</div>'; // End container

		// Empty search state
		echo '<div id="onemcp-abilities-empty" class="onemcp-card-box" style="display:none;text-align:center;padding:48px 24px;">';
		echo '<div class="onemcp-empty-icon">' . wp_kses(self::icon('search'), self::svg_kses()) . '</div>';
		echo '<h3 style="font-size:16px;margin:0 0 6px 0;">' . esc_html__('No matching abilities found', 'onemcp') . '</h3>';
		echo '<p style="color:#64748b;font-size:13px;margin:0;">' . esc_html__('Try clearing your search term or selecting "All Tools".', 'onemcp') . '</p>';
		echo '</div>';

		// Sticky Floating Bottom Bar for Easy 1-Click Saving
		echo '<div class="onemcp-sticky-bar">';
		echo '<div class="onemcp-sticky-bar-inner">';
		echo '<div class="onemcp-sticky-info">';
		echo '<span id="onemcp-sticky-counter" style="font-weight:700;color:var(--onemcp-text-main);">' . esc_html(
			sprintf(
				/* translators: 1: Number of enabled tools, 2: Total number of tools. */
				__('%1$d of %2$d tools enabled', 'onemcp'),
				$active_total,
				count($catalog)
			)
		) . '</span>';
		echo '<span class="onemcp-unsaved-badge" id="onemcp-unsaved-indicator" style="display:none;"><span class="onemcp-pulse-dot" style="background:#f59e0b;"></span> ' . esc_html__('Unsaved changes', 'onemcp') . '</span>';
		echo '</div>';

		echo '<div style="display:flex;align-items:center;gap:10px;">';
		echo '<button type="submit" class="onemcp-btn onemcp-btn-primary onemcp-btn-lg" id="onemcp-save-btn">';
		echo wp_kses(self::icon('save'), self::svg_kses()) . ' ' . esc_html__('Save Abilities', 'onemcp');
		echo '</button>';
		echo '</div>';
		echo '</div>';
		echo '</div>';

		echo '</form>';
	}

	/**
	 * Recent ability calls.
	 *
	 * @return void
	 */
	 static function render_activity() {
		$rows = \ONEMCP\Audit::recent(50);

		echo '<div class="onemcp-card-box">';
		echo '<div class="onemcp-card-header-row">';
		echo '<div>';
		$keep = (int) apply_filters('onemcp_audit_keep', 100);
		echo '<h2 class="onemcp-section-title">' . esc_html__('Activity Log', 'onemcp') . '</h2>';
		echo '<p class="onemcp-section-desc">' . esc_html(sprintf(
			/* translators: %d: number of log rows kept. */
			__('Audit trail of MCP requests executed by connected AI clients. The newest %d entries are kept.', 'onemcp'),
			$keep
		)) . '</p>';
		echo '</div>';
		do_action('onemcp_activity_toolbar');
		if ($rows !== array()) {
			echo '<form method="post" class="onemcp-inline-form" data-onemcp-confirm="' . esc_attr__('Clear the activity log?', 'onemcp') . '">';
			wp_nonce_field('onemcp_admin', 'onemcp_nonce');
			echo '<input type="hidden" name="onemcp_action" value="clear-log">';
			echo '<button type="submit" class="onemcp-btn onemcp-btn-danger">';
			echo wp_kses(self::icon('trash'), self::svg_kses()) . ' ' . esc_html__('Clear log', 'onemcp');
			echo '</button>';
			echo '</form>';
		}
		echo '</div>';

		if ($rows === array()) {
			// FormLayer styled empty state matching Screenshot 5
			echo '<div class="onemcp-empty-state">';
			echo '<div class="onemcp-empty-icon">' . wp_kses(self::icon('activity'), self::svg_kses()) . '</div>';
			echo '<h3>' . esc_html__('No activity recorded yet', 'onemcp') . '</h3>';
			echo '<p>' . esc_html__('When your MCP client or AI connects and invokes tools, requests and execution details will be recorded here.', 'onemcp') . '</p>';
			echo '</div>';
			echo '</div>';
			return;
		}

		echo '<div class="onemcp-table-container" style="margin-top:16px;">';
		echo '<table class="onemcp-table"><thead><tr>';
		echo '<th>' . esc_html__('Time (UTC)', 'onemcp') . '</th>';
		echo '<th>' . esc_html__('Ability', 'onemcp') . '</th>';
		echo '<th>' . esc_html__('User', 'onemcp') . '</th>';
		echo '<th>' . esc_html__('Result', 'onemcp') . '</th>';
		echo '</tr></thead><tbody>';

		foreach ($rows as $row) {
			$user   = get_userdata((int) $row['user_id']);
			$name   = $user instanceof WP_User ? $user->display_name : ('#' . (int) $row['user_id']);
			$is_ok  = !empty($row['ok']);
			$result = $is_ok ? (string) $row['message'] : __('Failed', 'onemcp') . ': ' . (string) $row['message'];

			echo '<tr>';
			echo '<td style="color:#64748b;font-size:12px;white-space:nowrap;">' . esc_html((string) $row['created_at']) . '</td>';
			echo '<td><span class="onemcp-slug-badge">' . esc_html((string) $row['ability']) . '</span></td>';
			echo '<td><strong>' . esc_html($name) . '</strong></td>';
			echo '<td>';
			if ($is_ok) {
				echo '<span class="onemcp-badge onemcp-badge-success">' . esc_html($result) . '</span>';
			} else {
				echo '<span class="onemcp-badge" style="background:#fef2f2;color:#dc2626;border:1px solid #fecaca;">' . esc_html($result) . '</span>';
			}
			echo '</td>';
			echo '</tr>';
		}

		echo '</tbody></table>';
		echo '</div>';
		echo '</div>';
	}

	/**
	 * One status card.
	 *
	 * @param string $title  Card title.
	 * @param bool   $ok     Whether the check passed.
	 * @param string $detail Short explanation.
	 * @param string $icon   Icon name.
	 * @return void
	 */
	private static function status_card($title, $ok, $detail, $icon = 'server') {
		echo '<article class="onemcp-status-card">';
		echo '<div class="onemcp-status-card-header">';
		echo '<div style="display:flex;align-items:center;gap:10px;">';
		echo '<div class="onemcp-status-icon-wrap">' . wp_kses(self::icon($icon), self::svg_kses()) . '</div>';
		echo '<h3>' . esc_html($title) . '</h3>';
		echo '</div>';
		if ($ok) {
			echo '<span class="onemcp-badge onemcp-badge-success"><span class="onemcp-pulse-dot"></span> ' . esc_html__('Ready', 'onemcp') . '</span>';
		} else {
			echo '<span class="onemcp-badge onemcp-badge-muted">' . esc_html__('Missing', 'onemcp') . '</span>';
		}
		echo '</div>';
		echo '<p>' . esc_html($detail) . '</p>';
		echo '</article>';
	}

	/**
	 * A one-click bulk action.
	 *
	 * @param string $mode    Bulk mode.
	 * @param string $label   Button label.
	 * @param bool   $confirm Ask before submitting.
	 * @return void
	 */
	 static function bulk_button($mode, $label, $confirm) {
		$confirm_text = $confirm ? __('Turn on every write ability? Writes can change the site.', 'onemcp') : '';
		echo '<form method="post" class="onemcp-inline-form"' . ($confirm_text !== '' ? ' data-onemcp-confirm="' . esc_attr($confirm_text) . '"' : '') . '>';
		wp_nonce_field('onemcp_admin', 'onemcp_nonce');
		echo '<input type="hidden" name="onemcp_action" value="bulk">';
		echo '<input type="hidden" name="mode" value="' . esc_attr($mode) . '">';
		echo '<button type="submit" class="onemcp-btn ' . ($confirm ? 'onemcp-btn-warning' : 'onemcp-btn-secondary') . '">';
		echo esc_html($label);
		echo '</button>';
		echo '</form>';
	}

	/**
	 * Render integration product avatar or official logo image.
	 *
	 * @param array{initials: string, color: string, bg: string, logo_url?: string} $info
	 * @param string $label
	 * @return void
	 */
	private static function render_avatar($info, $label) {
		if (!empty($info['logo_url'])) {
			echo '<div class="onemcp-product-avatar onemcp-avatar-has-img"><img src="' . esc_url($info['logo_url']) . '" alt="' . esc_attr($label) . '" class="onemcp-product-img" loading="lazy"></div>';
		} else {
			echo '<div class="onemcp-product-avatar" style="background:' . esc_attr($info['bg']) . ';color:' . esc_attr($info['color']) . ';">' . esc_html($info['initials']) . '</div>';
		}
	}

	/**
	 * Helper to get initials, brand colors, and official logo for each product integration.
	 *
	 * @param string $slug Product slug.
	 * @return array{initials: string, color: string, bg: string, logo_url?: string}
	 */
	static function get_product_badge_info($slug) {
		if ($slug === 'wordpress' && Detector::is_sitepad()) {
			return array(
				'initials' => 'SP',
				'color'    => '#0f766e',
				'bg'       => '#ccfbf1',
				'logo_url' => '',
			);
		}
		$map = array(
			'wordpress'        => array('initials' => 'WP',  'color' => '#0073aa', 'bg' => '#e5f5fa', 'icon' => 'wordpress.svg'),
			'pagelayer'        => array('initials' => 'PL',  'color' => '#0284c7', 'bg' => '#e0f2fe', 'icon' => 'pagelayer.png'),
			'elementor'        => array('initials' => 'EL',  'color' => '#e11d48', 'bg' => '#ffe4e6', 'icon' => 'elementor.gif'),
			'seedprod'         => array('initials' => 'SP',  'color' => '#ea580c', 'bg' => '#ffedd5', 'icon' => 'seedprod.png'),
			'divi'             => array('initials' => 'DV',  'color' => '#7c3aed', 'bg' => '#ede9fe', 'icon' => 'divi.svg'),
			'bricks'           => array('initials' => 'BR',  'color' => '#d97706', 'bg' => '#fef3c7', 'icon' => 'bricks.svg'),
			'beaver'           => array('initials' => 'BB',  'color' => '#0d9488', 'bg' => '#ccfbf1', 'icon' => 'beaver.png'),
			'breakdance'       => array('initials' => 'BD',  'color' => '#4f46e5', 'bg' => '#e0e7ff', 'icon' => 'breakdance.svg'),
			'siteseo'          => array('initials' => 'SS',  'color' => '#2563eb', 'bg' => '#dbeafe', 'icon' => 'siteseo.png'),
			'yoast'            => array('initials' => 'YS',  'color' => '#9333ea', 'bg' => '#f3e8ff', 'icon' => 'yoast.gif'),
			'rank-math'        => array('initials' => 'RM',  'color' => '#1d4ed8', 'bg' => '#dbeafe', 'icon' => 'rank-math.svg'),
			'aioseo'           => array('initials' => 'AIO', 'color' => '#0284c7', 'bg' => '#e0f2fe', 'icon' => 'aioseo.svg'),
			'seopress'         => array('initials' => 'SP',  'color' => '#059669', 'bg' => '#d1fae5', 'icon' => 'seopress.gif'),
			'woocommerce'      => array('initials' => 'WC',  'color' => '#7c3aed', 'bg' => '#ede9fe', 'icon' => 'woocommerce.svg'),
			'edd'              => array('initials' => 'ED',  'color' => '#2563eb', 'bg' => '#dbeafe', 'icon' => 'edd.svg'),
			'backuply'         => array('initials' => 'BL',  'color' => '#0284c7', 'bg' => '#e0f2fe', 'icon' => 'backuply.png'),
			'duplicator'       => array('initials' => 'DP',  'color' => '#475569', 'bg' => '#f1f5f9', 'icon' => 'duplicator.png'),
			'gosmtp'           => array('initials' => 'GO',  'color' => '#0284c7', 'bg' => '#e0f2fe', 'icon' => 'gosmtp.png'),
			'wp-mail-smtp'     => array('initials' => 'WP',  'color' => '#7c3aed', 'bg' => '#ede9fe', 'icon' => 'wp-mail-smtp.png'),
			'fluent-smtp'      => array('initials' => 'FS',  'color' => '#9333ea', 'bg' => '#f3e8ff', 'icon' => 'fluent-smtp.svg'),
			'easy-wp-smtp'     => array('initials' => 'EW',  'color' => '#ea580c', 'bg' => '#ffedd5', 'icon' => 'easy-wp-smtp.svg'),
			'post-smtp'        => array('initials' => 'PS',  'color' => '#059669', 'bg' => '#d1fae5', 'icon' => 'post-smtp.gif'),
			'smtp-mailer'      => array('initials' => 'SM',  'color' => '#2563eb', 'bg' => '#dbeafe', 'icon' => 'smtp-mailer.png'),
			'speedycache'      => array('initials' => 'SC',  'color' => '#0284c7', 'bg' => '#e0f2fe', 'icon' => 'speedycache.png'),
			'litespeed'        => array('initials' => 'LS',  'color' => '#0f766e', 'bg' => '#ccfbf1', 'icon' => 'litespeed.png'),
			'wp-rocket'        => array('initials' => 'WR',  'color' => '#e11d48', 'bg' => '#ffe4e6', 'icon' => 'wp-rocket.svg'),
			'wp-super-cache'   => array('initials' => 'WSC', 'color' => '#2563eb', 'bg' => '#dbeafe', 'icon' => 'wp-super-cache.png'),
			'w3-total-cache'   => array('initials' => 'W3',  'color' => '#7c3aed', 'bg' => '#ede9fe', 'icon' => 'w3-total-cache.png'),
			'wp-fastest-cache' => array('initials' => 'FC',  'color' => '#ea580c', 'bg' => '#ffedd5', 'icon' => 'wp-fastest-cache.png'),
			'wp-optimize'      => array('initials' => 'WPO', 'color' => '#4f46e5', 'bg' => '#e0e7ff', 'icon' => 'wp-optimize.png'),
			'siteground'       => array('initials' => 'SG',  'color' => '#16a34a', 'bg' => '#dcfce7', 'icon' => 'siteground.svg'),
		);

		$info = isset($map[$slug]) ? $map[$slug] : array('initials' => strtoupper(substr($slug, 0, 2)), 'color' => '#6366f1', 'bg' => '#e0e7ff');
		if (!empty($info['icon']) && file_exists(ONEMCP_DIR_PATH . 'assets/icons/' . $info['icon'])) {
			$info['logo_url'] = ONEMCP_DIR_URL . 'assets/icons/' . $info['icon'];
		} else {
			$info['logo_url'] = '';
		}
		return $info;
	}

	/**
	 * Tags and attributes allowed in the inline SVG icons.
	 *
	 * @return array<string,array<string,bool>>
	 */
	private static function svg_kses() {
		$attrs = array(
			'class'           => true,
			'width'           => true,
			'height'          => true,
			'viewbox'         => true,
			'fill'            => true,
			'stroke'          => true,
			'stroke-width'    => true,
			'stroke-linecap'  => true,
			'stroke-linejoin' => true,
			'points'          => true,
			'd'               => true,
			'x'               => true,
			'y'               => true,
			'x1'              => true,
			'y1'              => true,
			'x2'              => true,
			'y2'              => true,
			'cx'              => true,
			'cy'              => true,
			'r'               => true,
			'rx'              => true,
			'ry'              => true,
		);

		return array(
			'svg'      => $attrs,
			'path'     => $attrs,
			'polyline' => $attrs,
			'polygon'  => $attrs,
			'line'     => $attrs,
			'circle'   => $attrs,
			'rect'     => $attrs,
		);
	}

	/**
	 * Inline SVG icon library.
	 *
	 * @param string $name  Icon name.
	 * @param string $class Additional CSS classes.
	 * @return string SVG markup.
	 */
	public static function icon($name, $class = '') {
		$class_attr = $class !== '' ? ' class="' . esc_attr($class) . '"' : '';
		switch ($name) {
			case 'logo':
				return '<svg' . $class_attr . ' width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"/><polyline points="3.27 6.96 12 12.01 20.73 6.96"/><line x1="12" y1="22.08" x2="12" y2="12"/><circle cx="12" cy="12" r="2.5" fill="currentColor"/></svg>';
			case 'connection':
				return '<svg' . $class_attr . ' width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M10 13a5 5 0 0 0 7.54.54l3-3a5 5 0 0 0-7.07-7.07l-1.72 1.71"/><path d="M14 11a5 5 0 0 0-7.54-.54l-3 3a5 5 0 0 0 7.07 7.07l1.71-1.71"/></svg>';
			case 'integrations':
				return '<svg' . $class_attr . ' width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="7" height="7" rx="1.5"/><rect x="14" y="3" width="7" height="7" rx="1.5"/><rect x="14" y="14" width="7" height="7" rx="1.5"/><rect x="3" y="14" width="7" height="7" rx="1.5"/></svg>';
			case 'abilities':
				return '<svg' . $class_attr . ' width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2"/></svg>';
			case 'activity':
				return '<svg' . $class_attr . ' width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/></svg>';
			case 'server':
				return '<svg' . $class_attr . ' width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="2" width="20" height="8" rx="2"/><rect x="2" y="14" width="20" height="8" rx="2"/><line x1="6" y1="6" x2="6.01" y2="6"/><line x1="6" y1="18" x2="6.01" y2="18"/></svg>';
			case 'key':
				return '<svg' . $class_attr . ' width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="7.5" cy="15.5" r="4.5"/><path d="M21 2l-9.6 9.6"/><path d="M15.5 7.5l3 3L22 7l-2-2-1.5 1.5"/></svg>';
			case 'copy':
				return '<svg' . $class_attr . ' width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="9" y="9" width="13" height="13" rx="2" ry="2"/><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"/></svg>';
			case 'check':
				return '<svg' . $class_attr . ' width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>';
			case 'chevron-down':
				return '<svg' . $class_attr . ' width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="6 9 12 15 18 9"/></svg>';
			case 'external':
				return '<svg' . $class_attr . ' width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"/><polyline points="15 3 21 3 21 9"/><line x1="10" y1="14" x2="21" y2="3"/></svg>';
			case 'trash':
				return '<svg' . $class_attr . ' width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg>';
			case 'alert':
				return '<svg' . $class_attr . ' width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>';
			case 'save':
				return '<svg' . $class_attr . ' width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"/><polyline points="17 21 17 13 7 13 7 21"/><polyline points="7 3 7 8 15 8"/></svg>';
			case 'refresh':
				return '<svg' . $class_attr . ' width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="23 4 23 10 17 10"/><path d="M20.49 15a9 9 0 1 1-2.12-9.36L23 10"/></svg>';
			case 'search':
				return '<svg' . $class_attr . ' width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>';
			case 'sliders':
				return '<svg' . $class_attr . ' width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="4" y1="21" x2="4" y2="14"/><line x1="4" y1="10" x2="4" y2="3"/><line x1="12" y1="21" x2="12" y2="12"/><line x1="12" y1="8" x2="12" y2="3"/><line x1="20" y1="21" x2="20" y2="16"/><line x1="20" y1="12" x2="20" y2="3"/><line x1="1" y1="14" x2="7" y2="14"/><line x1="9" y1="8" x2="15" y2="8"/><line x1="17" y1="16" x2="23" y2="16"/></svg>';
			case 'shield':
				return '<svg' . $class_attr . ' width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>';
			case 'zap':
				return '<svg' . $class_attr . ' width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2"/></svg>';
			case 'lock':
				return '<svg' . $class_attr . ' width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>';
			case 'chevron-right':
				return '<svg' . $class_attr . ' width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="9 18 15 12 9 6"/></svg>';
			case 'check-circle':
				return '<svg' . $class_attr . ' width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>';
			case 'sparkles':
				return '<svg' . $class_attr . ' width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 3l1.912 5.885L20 10.8l-4.944 3.73L16.824 20 12 16.326 7.176 20l1.768-5.47L4 10.8l6.088-1.915L12 3z"/></svg>';
			case 'wordpress':
				return '<svg' . $class_attr . ' width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><path d="M2.05 12a9.94 9.94 0 0 0 3.74 7.69L10 6.5l3.5 13.5 2.5-9.5 2.21 7.19A9.94 9.94 0 0 0 21.95 12"/></svg>';
			default:
				return '';
		}
	}
}