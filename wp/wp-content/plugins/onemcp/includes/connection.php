<?php
/**
 * Built-in MCP server URL, client snippet, and connection test.
 *
 * @package OneMCP
 */

namespace ONEMCP;

use WP_REST_Request;
use WP_REST_Response;
use WP_Error;
use stdClass;

if (!defined('ABSPATH')) {
	exit;
}

class Connection {

	/**
	 * Whether the Abilities API is available.
	 *
	 * @return bool
	 */
	 static function abilities_ready() {
		return function_exists('wp_register_ability');
	}

	/**
	 * Whether WordPress will accept application passwords.
	 *
	 * @return bool
	 */
	 static function passwords_available() {
		return function_exists('wp_is_application_passwords_available') && wp_is_application_passwords_available();
	}

	/**
	 * MCP server URL.
	 *
	 * @return string
	 */
	 static function endpoint() {
		return rest_url(Server::ROUTE);
	}

	/**
	 * Compact status for the site-info ability.
	 *
	 * @return array<string,mixed>
	 */
	 static function summary() {
		return array(
			'builtin'                => true,
			'endpoint'               => self::endpoint(),
			'abilities_api'          => self::abilities_ready(),
			'application_passwords'  => self::passwords_available(),
		);
	}

	/**
	 * Client configuration with a placeholder or specified authorization token.
	 *
	 * @param string $auth_token Optional authorization base64 token.
	 * @return string
	 */
	 static function client_snippet($auth_token = '') {
		if (empty($auth_token)) {
			$auth_token = 'YOUR_BASE64_USERNAME_COLON_APPLICATION_PASSWORD';
		}
		$config = array(
			'mcpServers' => array(
				'onemcp' => array(
					'type'    => 'http',
					'url'     => self::endpoint(),
					'headers' => array(
						'Authorization' => 'Basic ' . $auth_token,
					),
				),
			),
		);
		$json = wp_json_encode($config, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
		return is_string($json) ? $json : '';
	}

	/**
	 * AI clients the connection screen can configure.
	 *
	 * Each entry uses this plugin's HTTP server. Formats match the client's
	 * own remote-MCP config, with Basic authentication from an application password.
	 *
	 * @return array<int,array<string,mixed>>
	 */
	 static function clients() {
		return array(
			array(
				'id'        => 'claude-desktop',
				'label'     => __('Claude Desktop', 'onemcp'),
				'format'    => 'claude-desktop',
				'pathLabel' => __('Save to', 'onemcp'),
				'path'      => 'claude_desktop_config.json',
				'title'     => 'claude_desktop_config.json',
				'steps'     => array(
					__('Open Claude Desktop settings or ~/.config/Claude/claude_desktop_config.json (macOS: ~/Library/Application Support/Claude/claude_desktop_config.json).', 'onemcp'),
					__('Paste the snippet into mcpServers. It connects directly to your self-hosted WordPress site using your Application Password.', 'onemcp'),
					__('Restart Claude Desktop to start using your WordPress tools.', 'onemcp'),
				),
			),
			array(
				'id'        => 'grok',
				'label'     => __('Grok', 'onemcp'),
				'format'    => 'grok',
				'pathLabel' => __('Command / Saves to', 'onemcp'),
				'path'      => 'grok mcp add  |  ~/.grok/config.toml',
				'title'     => 'grok mcp add',
				'steps'     => array(
					__('Open a terminal.', 'onemcp'),
					__('Paste the command and run it. Grok connects directly to your self-hosted WordPress MCP endpoint and saves it in ~/.grok/config.toml.', 'onemcp'),
					__('Run "grok mcp doctor onemcp" to verify the connection.', 'onemcp'),
					__('Add --scope project before the server name if this server should live only in the current project (.grok/config.toml).', 'onemcp'),
				),
			),
			array(
				'id'        => 'codex',
				'label'     => __('Codex', 'onemcp'),
				'format'    => 'codex',
				'pathLabel' => __('Save to', 'onemcp'),
				'path'      => '~/.codex/config.toml',
				'title'     => 'config.toml',
				'steps'     => array(
					__('Open ~/.codex/config.toml, or .codex/config.toml inside a trusted project.', 'onemcp'),
					__('Paste the table into the file. This server uses Basic authentication, so leave bearer_token unset.', 'onemcp'),
					__('Start a new Codex session and confirm the onemcp tools are listed.', 'onemcp'),
				),
			),
			array(
				'id'        => 'cursor',
				'label'     => __('Cursor', 'onemcp'),
				'format'    => 'cursor',
				'pathLabel' => __('Save to', 'onemcp'),
				'path'      => '.cursor/mcp.json  |  ~/.cursor/mcp.json',
				'title'     => 'mcp.json',
				'steps'     => array(
					__('In Cursor, open Settings, then MCP, or create the file shown above.', 'onemcp'),
					__('Paste the snippet and save. Use the project file for one repo, or the home file for every project.', 'onemcp'),
					__('Enable onemcp in the MCP list.', 'onemcp'),
				),
			),
			array(
				'id'        => 'vscode',
				'label'     => __('VS Code', 'onemcp'),
				'format'    => 'vscode',
				'pathLabel' => __('Save to', 'onemcp'),
				'path'      => '.vscode/mcp.json',
				'title'     => 'mcp.json',
				'steps'     => array(
					__('In VS Code, run MCP: Add Server from the Command Palette, or create .vscode/mcp.json.', 'onemcp'),
					__('Choose HTTP, paste the server URL, then add the Authorization header from the snippet.', 'onemcp'),
					__('Reload the window. GitHub Copilot in VS Code uses this same file.', 'onemcp'),
				),
			),
			array(
				'id'        => 'windsurf',
				'label'     => __('Windsurf', 'onemcp'),
				'format'    => 'windsurf',
				'pathLabel' => __('Save to', 'onemcp'),
				'path'      => '~/.codeium/windsurf/mcp_config.json',
				'title'     => 'mcp_config.json',
				'steps'     => array(
					__('Open ~/.codeium/windsurf/mcp_config.json, or create it.', 'onemcp'),
					__('Paste the snippet. Windsurf reads serverUrl for a remote HTTP server.', 'onemcp'),
					__('Restart Windsurf and enable onemcp.', 'onemcp'),
				),
			),
			array(
				'id'        => 'opencode',
				'label'     => __('OpenCode', 'onemcp'),
				'format'    => 'opencode',
				'pathLabel' => __('Save to', 'onemcp'),
				'path'      => 'opencode.json  |  ~/.config/opencode/opencode.json',
				'title'     => 'opencode.json',
				'steps'     => array(
					__('Create opencode.json in the project, or ~/.config/opencode/opencode.json for every project.', 'onemcp'),
					__('Paste the snippet. oauth is false so OpenCode sends the application-password header instead of starting an OAuth login.', 'onemcp'),
					__('Restart OpenCode.', 'onemcp'),
				),
			),
			array(
				'id'        => 'antigravity',
				'label'     => __('Antigravity', 'onemcp'),
				'format'    => 'antigravity',
				'pathLabel' => __('Save to', 'onemcp'),
				'path'      => 'nano ~/.gemini/config/mcp_config.json',
				'title'     => 'mcp_config.json',
				'steps'     => array(
					__('Open the Antigravity MCP config, or create it.', 'onemcp'),
					__('Paste the snippet and save.', 'onemcp'),
					__('Restart Antigravity and enable onemcp.', 'onemcp'),
				),
			),
			array(
				'id'        => 'gemini',
				'label'     => __('Gemini CLI', 'onemcp'),
				'format'    => 'gemini',
				'pathLabel' => __('Save to', 'onemcp'),
				'path'      => '~/.gemini/settings.json  |  .gemini/settings.json',
				'title'     => 'settings.json',
				'steps'     => array(
					__('Open ~/.gemini/settings.json, or .gemini/settings.json in the project.', 'onemcp'),
					__('Paste the snippet. httpUrl selects streamable HTTP. A url field would select the older SSE transport.', 'onemcp'),
					__('Restart Gemini CLI.', 'onemcp'),
				),
			),
			array(
				'id'        => 'other',
				'label'     => __('Other', 'onemcp'),
				'format'    => 'other',
				'pathLabel' => __('Use in', 'onemcp'),
				'path'      => __('Any MCP client that accepts an HTTP server and headers', 'onemcp'),
				'title'     => 'mcp.json',
				'steps'     => array(
					__('Choose HTTP or streamable HTTP, and paste the server URL.', 'onemcp'),
					__('Set the Authorization header to the Basic value in the snippet.', 'onemcp'),
					__('Generate a separate application password for each client so you can revoke one without affecting the others.', 'onemcp'),
				),
			),
		);
	}

	/**
	 * Last connection test for the current user.
	 *
	 * @return array<string,mixed>
	 */
	 static function last_test() {
		$stored = get_user_meta(get_current_user_id(), 'onemcp_test_status', true);
		return is_array($stored) ? $stored : array();
	}

	/**
	 * Initialize the built-in server, list tools, and call get-site-info.
	 *
	 * @return array<string,mixed>
	 */
	 static function test() {
		$result = array(
			'ok'          => false,
			'checked_at'  => time(),
			'http_status' => 0,
			'message'     => '',
		);

		$init = self::rpc(array(
			'jsonrpc' => '2.0',
			'id'      => 1,
			'method'  => 'initialize',
			'params'  => array(
				'protocolVersion' => '2025-03-26',
				'capabilities'    => new stdClass(),
				'clientInfo'      => array(
					'name'    => 'onemcp',
					'version' => ONEMCP_VERSION,
				),
			),
		), '');
		if (is_wp_error($init)) {
			$result['message'] = $init->get_error_message();
			self::store_test($result);
			return $result;
		}

		$response = $init;
		$status   = (int) $response->get_status();
		$data     = $response->get_data();
		$result['http_status'] = $status;
		if ($status < 200 || $status >= 300 || !is_array($data) || empty($data['result']['serverInfo']['name'])) {
			$result['message'] = sprintf(
				/* translators: %d: HTTP status code. */
				__('The built-in MCP server responded with status %d.', 'onemcp'),
				$status
			);
			self::store_test($result);
			return $result;
		}

		$session = self::header_value($response, 'mcp-session-id');
		$list    = self::rpc(array(
			'jsonrpc' => '2.0',
			'id'      => 2,
			'method'  => 'tools/list',
			'params'  => new stdClass(),
		), $session);
		$list_data = is_wp_error($list) ? null : $list->get_data();
		$tools     = is_array($list_data) && isset($list_data['result']['tools']) && is_array($list_data['result']['tools']) ? $list_data['result']['tools'] : null;
		if ($tools === null) {
			$result['message'] = __('The MCP server initialized, but tools/list failed.', 'onemcp');
			self::store_test($result);
			return $result;
		}

		$call = self::rpc(array(
			'jsonrpc' => '2.0',
			'id'      => 3,
			'method'  => 'tools/call',
			'params'  => array(
				'name'      => 'onemcp_get-site-info',
				'arguments' => new stdClass(),
			),
		), $session);
		$called  = is_wp_error($call) ? array() : $call->get_data();
		$content = is_array($called) && isset($called['result']['structuredContent']) ? $called['result']['structuredContent'] : null;
		if (!is_array($content) || !empty($called['result']['isError']) || !array_key_exists('name', $content)) {
			$result['message'] = __('The MCP server listed tools, but get-site-info failed.', 'onemcp');
			self::store_test($result);
			return $result;
		}

		$result['ok']      = true;
		$result['message'] = sprintf(
			/* translators: %d: number of MCP tools. */
			__('Built-in MCP server is ready. %d tools are available to this account.', 'onemcp'),
			count($tools)
		);
		self::store_test($result);
		return $result;
	}

	/**
	 * Send one JSON-RPC message through the REST server.
	 *
	 * @param array<string,mixed> $payload JSON-RPC message.
	 * @param string              $session Session id.
	 * @return WP_REST_Response|WP_Error
	 */
	private static function rpc($payload, $session) {
		$request = new WP_REST_Request('POST', '/' . Server::ROUTE);
		$request->set_header('Content-Type', 'application/json');
		$request->set_header('Accept', 'application/json, text/event-stream');
		if ($session !== '') {
			$request->set_header('Mcp-Session-Id', $session);
		}
		$body = wp_json_encode($payload);
		$request->set_body(is_string($body) ? $body : '');
		$response = rest_do_request($request);
		if ($response->is_error()) {
			$error = $response->as_error();
			return new WP_Error($error->get_error_code(), $error->get_error_message());
		}
		return $response;
	}

	/**
	 * Header value from a REST response.
	 *
	 * @param WP_REST_Response $response Response.
	 * @param string           $name     Header name.
	 * @return string
	 */
	private static function header_value($response, $name) {
		$headers = $response->get_headers();
		foreach ($headers as $key => $value) {
			if (strtolower((string) $key) !== strtolower($name)) {
				continue;
			}
			if (is_array($value)) {
				$value = reset($value);
			}
			return is_scalar($value) ? (string) $value : '';
		}
		return '';
	}

	/**
	 * Save a test result on the current user.
	 *
	 * @param array<string,mixed> $result Test result.
	 * @return void
	 */
	private static function store_test($result) {
		$user_id = get_current_user_id();
		if ($user_id) {
			update_user_meta($user_id, 'onemcp_test_status', $result);
		}
	}
}
