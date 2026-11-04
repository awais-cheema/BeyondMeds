<?php
/**
 * Built-in MCP server.
 *
 * Streamable HTTP JSON-RPC at onemcp/v1/mcp. Clients authenticate with a
 * WordPress application password. Tools are the enabled abilities.
 *
 * @package OneMCP
 */

namespace ONEMCP;

use WP_REST_Response;
use WP_REST_Request;
use WP_Error;
use stdClass;

if (!defined('ABSPATH')) {
	exit;
}

class Server {

	const ROUTE = 'onemcp/v1/mcp';

	const SESSION_TTL = 43200;

	/**
	 * Allow application-password creation on plain HTTP for this request only.
	 * Set after the admin capability check, never during login.
	 *
	 * @var bool
	 */
	private static $allow_http_passwords = false;

	/**
	 * Protocol versions this server can speak.
	 *
	 * @return array<int,string>
	 */
	 static function protocol_versions() {
		return array('2025-11-25', '2025-06-18', '2025-03-26', '2024-11-05');
	}

	/**
	 * REST and header hooks.
	 *
	 * @return void
	 */
	 static function hooks() {
		add_action('rest_api_init', '\ONEMCP\Server::register_routes');
		add_filter('rest_pre_serve_request', '\ONEMCP\Server::serve_empty', 10, 3);
		add_filter('rest_allowed_cors_headers', '\ONEMCP\Server::allow_headers');
		add_filter('rest_exposed_cors_headers', '\ONEMCP\Server::expose_headers');
		add_filter('wp_is_application_passwords_available' , '\ONEMCP\Server::passwords_on_http');
	}

	/**
	 * WordPress hides application passwords on plain HTTP.
	 * Leave that protection in place for every other route. The MCP route can
	 * still accept one, and the OneMCP screen can still create one after the admin check.
	 *
	 * @param bool $available Current availability.
	 * @return bool
	 */
	public static function passwords_on_http($available) {
		if ($available) {
			return true;
		}
		// Plain HTTP must not turn application passwords on for the whole site.
		if (self::$allow_http_passwords || self::request_targets_mcp()) {
			return true;
		}
		return false;
	}

	/**
	 * Let the already-authenticated admin screen create one application password.
	 *
	 * @return void
	 */
	public static function allow_password_create() {
		self::$allow_http_passwords = true;
	}

	/**
	 * Whether this request is exactly the MCP route.
	 *
	 * The path is matched, not the raw query string, so another REST route
	 * cannot opt in by mentioning the MCP path.
	 *
	 * @return bool
	 */
	private static function request_targets_mcp() {
		if (isset($_GET['rest_route'])) {
			$route = '/' . ltrim((string) wp_unslash($_GET['rest_route']), '/');
			if ($route === '/onemcp/v1/mcp' || $route === '/onemcp/v1/mcp/') {
				return true;
			}
		}
		$uri  = isset($_SERVER['REQUEST_URI']) ? (string) wp_unslash($_SERVER['REQUEST_URI']) : '';
		$path = (string) wp_parse_url($uri, PHP_URL_PATH);
		if ($path === '') {
			return false;
		}
		$prefix = function_exists('rest_get_url_prefix') ? (string) rest_get_url_prefix() : 'wp-json';
		$prefix = preg_quote(trim($prefix, '/'), '#');
		if ($prefix === '') {
			$prefix = 'wp-json';
		}
		return (bool) preg_match('#/(?:' . $prefix . '|sp-json)/onemcp/v1/mcp/?$#', $path);
	}

	/**
	 * Register the MCP endpoint.
	 *
	 * @return void
	 */
	public static function register_routes() {
		register_rest_route('onemcp/v1', '/mcp', array(
			'methods'             => array('GET', 'POST', 'DELETE', 'HEAD'),
			'callback'            => array(__CLASS__, 'dispatch'),
			'permission_callback' => array(__CLASS__, 'permission'),
		));
	}

	/**
	 * Logged-in users who can edit something this plugin manages.
	 *
	 * @return true|WP_Error
	 */
	 static function permission() {
		if (!is_user_logged_in()) {
			return new \WP_Error(
				'onemcp_unauthorized',
				__('Connect with a WordPress application password.', 'onemcp'),
				array('status' => 401)
			);
		}
		$caps = array('edit_posts', 'edit_pages', 'upload_files', 'manage_options', 'manage_woocommerce', 'edit_products', 'edit_shop_payments');
		foreach ($caps as $cap) {
			if (current_user_can($cap)) {
				return true;
			}
		}
		return new \WP_Error(
			'onemcp_forbidden',
			__('Your account cannot use OneMCP.', 'onemcp'),
			array('status' => 403)
		);
	}

	/**
	 * Route GET, POST, and DELETE.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response
	 */
	 static function dispatch($request) {
		$method = strtoupper($request->get_method());
		if ($method === 'GET' || $method === 'HEAD') {
			$response = self::rpc_error(null, -32601, __('This server does not offer an event stream. Send JSON-RPC with POST.', 'onemcp'), 405, '');
			$response->header('Allow', 'POST, DELETE');
			return $response;
		}
		if ($method === 'DELETE') {
			return self::end_session($request);
		}
		return self::handle_post($request);
	}

	/**
	 * Allow MCP headers on browser clients.
	 *
	 * @param array<int,string> $headers Headers.
	 * @return array<int,string>
	 */
	 static function allow_headers($headers) {
		$headers[] = 'Mcp-Session-Id';
		$headers[] = 'MCP-Protocol-Version';
		$headers[] = 'Last-Event-ID';
		return $headers;
	}

	/**
	 * Let browser clients read the session id.
	 *
	 * @param array<int,string> $headers Headers.
	 * @return array<int,string>
	 */
	 static function expose_headers($headers) {
		$headers[] = 'Mcp-Session-Id';
		return $headers;
	}

	/**
	 * Notifications and session deletion have no body.
	 *
	 * @param bool              $served  Whether a previous filter served the request.
	 * @param WP_HTTP_Response  $result  Response.
	 * @param WP_REST_Request   $request Request.
	 * @return bool
	 */
	 static function serve_empty($served, $result, $request) {
		if ($served || !($result instanceof WP_REST_Response) || !($request instanceof WP_REST_Request)) {
			return $served;
		}
		if ($request->get_route() !== '/' . self::ROUTE) {
			return $served;
		}
		$status = (int) $result->get_status();
		if ($status === 202 || $status === 204) {
			return true;
		}
		return $served;
	}

	/**
	 * Tool name clients call. Slashes are not accepted by every MCP client.
	 *
	 * @param array<string,mixed> $ability Catalog row.
	 * @return string
	 */
	 static function tool_name($ability) {
		$id = isset($ability['id']) ? (string) $ability['id'] : '';
		return str_replace('/', '_', $id);
	}

	/**
	 * Enabled abilities as MCP tools.
	 *
	 * @return array<int,array<string,mixed>>
	 */
	 static function tools() {
		$tools = array();
		foreach (Registry::catalog() as $ability) {
			if (empty($ability['enabled'])) {
				continue;
			}
			$readonly = !empty($ability['readonly']);
			$tools[]  = array(
				'name'        => self::tool_name($ability),
				'description' => isset($ability['description']) ? (string) $ability['description'] : '',
				'inputSchema' => self::schema_for_client(isset($ability['input_schema']) ? $ability['input_schema'] : array()),
				'annotations' => array(
					'title'           => isset($ability['label']) ? (string) $ability['label'] : '',
					'readOnlyHint'    => $readonly,
					'destructiveHint' => !empty($ability['destructive']),
					'idempotentHint'  => $readonly || empty($ability['destructive']),
					'openWorldHint'   => false,
				),
			);
		}
		return $tools;
	}

	/**
	 * Read one JSON-RPC POST.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response
	 */
	private static function handle_post($request) {
		$type = strtolower((string) $request->get_header('content-type'));
		if ($type !== '' && strpos($type, 'application/json') === false) {
			return self::rpc_error(null, -32700, __('Content-Type must be application/json.', 'onemcp'), 415, '');
		}

		$accept = strtolower((string) $request->get_header('accept'));
		if ($accept !== '' && strpos($accept, 'application/json') === false && strpos($accept, 'text/event-stream') === false && strpos($accept, '*/*') === false) {
			return new WP_REST_Response(null, 406);
		}

		$version = (string) $request->get_header('mcp-protocol-version');
		if ($version !== '' && !in_array($version, self::protocol_versions(), true)) {
			return self::rpc_error(null, -32600, __('Unsupported MCP-Protocol-Version.', 'onemcp'), 400, '');
		}

		$body = (string) $request->get_body();
		if (strlen($body) > 2000000) {
			return self::rpc_error(null, -32600, __('The MCP request is too large.', 'onemcp'), 400, '');
		}

		$decoded = json_decode($body, true);
		if (!is_array($decoded) || json_last_error() !== JSON_ERROR_NONE) {
			return self::rpc_error(null, -32700, __('Parse error.', 'onemcp'), 400, '');
		}

		$session = self::incoming_session($request);
		if (Helper::is_list($decoded)) {
			return self::handle_batch($decoded, $session);
		}
		return self::handle_message($decoded, $session);
	}

	/**
	 * One JSON-RPC message.
	 *
	 * A false session means the client sent an unknown id.
	 *
	 * @param array<string,mixed> $message Message.
	 * @param string|false        $session Session id, empty when omitted, false when rejected.
	 * @return WP_REST_Response
	 */
	private static function handle_message($message, $session) {
		if (!isset($message['jsonrpc']) || $message['jsonrpc'] !== '2.0' || empty($message['method']) || !is_string($message['method'])) {
			return self::rpc_error(self::message_id($message), -32600, __('Invalid Request.', 'onemcp'), 400, self::session_header($session));
		}
		if (array_key_exists('id', $message) && !is_scalar($message['id']) && $message['id'] !== null) {
			return self::rpc_error(null, -32600, __('Invalid Request.', 'onemcp'), 400, self::session_header($session));
		}

		$method       = $message['method'];
		$notification = !array_key_exists('id', $message) || strpos($method, 'notifications/') === 0;
		if ($method !== 'initialize' && $session === false) {
			return new WP_REST_Response(null, 404);
		}

		$params = array();
		if (isset($message['params'])) {
			if (!is_array($message['params'])) {
				return self::rpc_error(self::message_id($message), -32602, __('Invalid params.', 'onemcp'), 200, self::session_header($session));
			}
			$params = $message['params'];
		}

		if ($method === 'initialize') {
			$session = self::create_session();
		} elseif (is_string($session) && $session !== '') {
			self::touch_session($session);
		}

		if ($notification) {
			$response = new WP_REST_Response(null, 202);
			if (is_string($session) && $session !== '') {
				$response->header('Mcp-Session-Id', $session);
			}
			return $response;
		}

		$result = self::invoke($method, $params);
		$header = self::session_header($session);
		if (is_wp_error($result)) {
			$code = $result->get_error_code() === 'onemcp_method' ? -32601 : -32602;
			return self::rpc_error(self::message_id($message), $code, $result->get_error_message(), 200, $header);
		}

		return self::respond(array(
			'jsonrpc' => '2.0',
			'id'      => self::message_id($message),
			'result'  => $result,
		), 200, $header);
	}

	/**
	 * A JSON-RPC batch. Notifications produce no result entry.
	 *
	 * @param array<int,mixed> $messages Messages.
	 * @param string|false     $session  Session id.
	 * @return WP_REST_Response
	 */
	private static function handle_batch($messages, $session) {
		if ($messages === array() || count($messages) > 20) {
			return self::rpc_error(null, -32600, __('Invalid Request.', 'onemcp'), 400, '');
		}
		$replies = array();
		$header  = self::session_header($session);
		foreach ($messages as $message) {
			if (!is_array($message)) {
				$replies[] = array(
					'jsonrpc' => '2.0',
					'id'      => null,
					'error'   => array('code' => -32600, 'message' => __('Invalid Request.', 'onemcp')),
				);
				continue;
			}
			$response = self::handle_message($message, $session);
			if ((int) $response->get_status() === 404) {
				return $response;
			}
			$sent = $response->get_headers();
			if (!empty($sent['Mcp-Session-Id'])) {
				$header = (string) $sent['Mcp-Session-Id'];
			}
			if ((int) $response->get_status() === 202) {
				continue;
			}
			$data = $response->get_data();
			if (is_array($data)) {
				$replies[] = $data;
			}
		}
		if ($replies === array()) {
			$response = new WP_REST_Response(null, 202);
			if ($header !== '') {
				$response->header('Mcp-Session-Id', $header);
			}
			return $response;
		}
		return self::respond($replies, 200, $header);
	}

	/**
	 * Run one MCP method.
	 *
	 * @param string              $method Method name.
	 * @param array<string,mixed> $params Params.
	 * @return array<string,mixed>|stdClass|WP_Error
	 */
	private static function invoke($method, $params) {
		if ($method === 'initialize') {
			return self::initialize($params);
		}
		if ($method === 'ping') {
			return new stdClass();
		}
		if ($method === 'tools/list') {
			return array('tools' => self::tools());
		}
		if ($method === 'tools/call') {
			return self::call_tool($params);
		}
		if ($method === 'resources/list') {
			return array('resources' => array());
		}
		if ($method === 'resources/templates/list') {
			return array('resourceTemplates' => array());
		}
		if ($method === 'prompts/list') {
			return array('prompts' => array());
		}
		if ($method === 'logging/setLevel') {
			return new stdClass();
		}
		return new \WP_Error('onemcp_method', __('Method not found.', 'onemcp'));
	}

	/**
	 * Tell the client which builder to use for pages, posts, themes, and UI.
	 *
	 * @return string
	 */
	private static function instructions() {
		$lines = array(
			__('Tools run as the authenticated WordPress user. Reads stay on until an administrator turns them off. Backup delete requires confirm DELETE. Backup restore requires confirm RESTORE.', 'onemcp'),
		);
		if (Detector::is_sitepad()) {
			$lines[] = __('This site is SitePad. SitePad has one visual builder, Pagelayer, and no other page builder. Any request to build or change a page, post, theme template, header, footer, popup, menu, or other visible UI must use the Pagelayer tools (create-website, create-page, update-page, create-post, create-template, add-element, update-element, change-styles, and the widget tools).', 'onemcp');
			$lines[] = __('Before placing or styling anything, call get-data-structure and that widget\'s schema (list-widgets, get-widget-schema, get-common-styles). Use only Pagelayer widget tags and their real settings. Do not invent attribute names, and do not build those layouts with create-content or raw HTML.', 'onemcp');
			return implode(' ', $lines);
		}

		$builders = Detector::active_slugs('builders');
		if ($builders !== array()) {
			$lines[] = sprintf(
				/* translators: %s: comma-separated builder names. */
				__('When the user asks to build or change a page, post, theme template, or other visible UI, use the active page builder (%s) and that builder\'s own widgets and settings. Read the widget schema or the element outline before writing settings. Do not invent setting keys, and do not replace a builder layout with raw HTML.', 'onemcp'),
				implode(', ', $builders)
			);
		}
		$lines[] = __('Write tools that are not part of WordPress core or the active page builder stay off until an administrator turns them on.', 'onemcp');
		return implode(' ', $lines);
	}

	/**
	 * initialize result.
	 *
	 * @param array<string,mixed> $params Params.
	 * @return array<string,mixed>
	 */
	private static function initialize($params) {
		$requested = isset($params['protocolVersion']) ? (string) $params['protocolVersion'] : '';
		$versions  = self::protocol_versions();
		$version   = in_array($requested, $versions, true) ? $requested : $versions[0];
		return array(
			'protocolVersion' => $version,
			'capabilities'    => array(
				'tools' => array(
					'listChanged' => false,
				),
			),
			'serverInfo'      => array(
				'name'    => 'OneMCP',
				'version' => ONEMCP_VERSION,
			),
			'instructions'    => self::instructions(),
		);
	}

	/**
	 * tools/call.
	 *
	 * @param array<string,mixed> $params Params.
	 * @return array<string,mixed>|WP_Error
	 */
	private static function call_tool($params) {
		$name = isset($params['name']) ? (string) $params['name'] : '';
		if ($name === '') {
			return new \WP_Error('onemcp_params', __('Tool name is required.', 'onemcp'));
		}
		$arguments = array();
		if (isset($params['arguments'])) {
			if (!is_array($params['arguments'])) {
				return new \WP_Error('onemcp_params', __('Tool arguments must be an object.', 'onemcp'));
			}
			$arguments = $params['arguments'];
		}

		$ability = self::find_tool($name);
		if ($ability === null) {
			return self::tool_error(__('Unknown tool.', 'onemcp'));
		}

		$result = Registry::call($ability['id'], $arguments);
		if (is_wp_error($result)) {
			return self::tool_error($result->get_error_message());
		}
		return self::tool_success($result);
	}

	/**
	 * Match a client tool name to an enabled ability.
	 *
	 * @param string $name Tool name.
	 * @return array<string,mixed>|null
	 */
	private static function find_tool($name) {
		foreach (Registry::catalog() as $ability) {
			if (empty($ability['enabled'])) {
				continue;
			}
			if ($name === self::tool_name($ability) || $name === $ability['id']) {
				return $ability;
			}
		}
		return null;
	}

	/**
	 * Successful tool payload.
	 *
	 * @param mixed $value Ability result.
	 * @return array<string,mixed>
	 */
	private static function tool_success($value) {
		$text   = wp_json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
		$result = array(
			'content' => array(
				array(
					'type' => 'text',
					'text' => is_string($text) ? $text : '',
				),
			),
			'isError' => false,
		);
		if (is_array($value) && !Helper::is_list($value)) {
			$result['structuredContent'] = $value;
		}
		return $result;
	}

	/**
	 * Tool error payload. The JSON-RPC request itself succeeded.
	 *
	 * @param string $message Message.
	 * @return array<string,mixed>
	 */
	private static function tool_error($message) {
		return array(
			'content' => array(
				array(
					'type' => 'text',
					'text' => $message,
				),
			),
			'isError' => true,
		);
	}

	/**
	 * Publish an object schema. Empty property maps must encode as {}.
	 *
	 * @param mixed $schema Stored schema.
	 * @return array<string,mixed>
	 */
	private static function schema_for_client($schema) {
		if (!is_array($schema)) {
			$schema = array(
				'type'                 => 'object',
				'additionalProperties' => false,
			);
		}
		if (!isset($schema['properties']) || (is_array($schema['properties']) && $schema['properties'] === array())) {
			$schema['properties'] = new stdClass();
		}
		if (isset($schema['default']) && is_array($schema['default']) && $schema['default'] === array()) {
			$schema['default'] = new stdClass();
		}
		return $schema;
	}

	/**
	 * Forget a session.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response
	 */
	private static function end_session($request) {
		$session = self::incoming_session($request);
		if ($session === false) {
			return new WP_REST_Response(null, 404);
		}
		if (is_string($session) && $session !== '') {
			delete_transient(self::session_key($session));
		}
		return new WP_REST_Response(null, 204);
	}

	/**
	 * Session header value, or an empty string when there is none.
	 *
	 * @param string|false $session Session id.
	 * @return string
	 */
	private static function session_header($session) {
		return is_string($session) ? $session : '';
	}

	/**
	 * Read Mcp-Session-Id.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return string|false Empty string when omitted, false when the id is not ours.
	 */
	private static function incoming_session($request) {
		$session = (string) $request->get_header('mcp-session-id');
		if ($session === '') {
			return '';
		}
		if (!preg_match('/^[A-Za-z0-9]{20,80}$/', $session)) {
			return false;
		}
		$stored = get_transient(self::session_key($session));
		if (!is_array($stored) || (int) $stored['user_id'] !== get_current_user_id()) {
			return false;
		}
		return $session;
	}

	/**
	 * Start a session for the current user.
	 *
	 * @return string
	 */
	private static function create_session() {
		$session = wp_generate_password(32, false, false);
		set_transient(self::session_key($session), array(
			'user_id' => get_current_user_id(),
		), self::SESSION_TTL);
		return $session;
	}

	/**
	 * Keep a session alive while the client is connected.
	 *
	 * @param string $session Session id.
	 * @return void
	 */
	private static function touch_session($session) {
		set_transient(self::session_key($session), array(
			'user_id' => get_current_user_id(),
		), self::SESSION_TTL);
	}

	/**
	 * Transient key for a session id.
	 *
	 * @param string $session Session id.
	 * @return string
	 */
	private static function session_key($session) {
		return 'onemcp_sess_' . $session;
	}

	/**
	 * JSON-RPC id, or null when the message has none.
	 *
	 * @param array<string,mixed> $message Message.
	 * @return int|float|string|null
	 */
	private static function message_id($message) {
		if (!is_array($message) || !array_key_exists('id', $message)) {
			return null;
		}
		$id = $message['id'];
		if (is_int($id) || is_float($id) || is_string($id) || $id === null) {
			return $id;
		}
		return null;
	}

	/**
	 * JSON-RPC error response.
	 *
	 * @param int|float|string|null $id      Request id.
	 * @param int                   $code    Error code.
	 * @param string                $message Message.
	 * @param int                   $status  HTTP status.
	 * @param string                $session Session header.
	 * @return WP_REST_Response
	 */
	private static function rpc_error($id, $code, $message, $status, $session) {
		return self::respond(array(
			'jsonrpc' => '2.0',
			'id'      => $id,
			'error'   => array(
				'code'    => (int) $code,
				'message' => $message,
			),
		), $status, $session);
	}

	/**
	 * JSON response plus the session header.
	 *
	 * @param mixed  $data    Response data.
	 * @param int    $status  HTTP status.
	 * @param string $session Session id.
	 * @return WP_REST_Response
	 */
	private static function respond($data, $status, $session) {
		$response = new WP_REST_Response($data, $status);
		$response->header('Cache-Control', 'no-store');
		if ($session !== '') {
			$response->header('Mcp-Session-Id', $session);
		}
		return $response;
	}
}
