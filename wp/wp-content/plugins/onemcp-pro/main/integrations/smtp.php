<?php
/**
 * SMTP mailer settings.
 *
 * Reads and updates the active mailer plugin. Passwords and API keys are
 * never returned. A new password is stored in the format that plugin expects.
 *
 * @package OneMCPPro
 */

namespace OneMCPPro;

use ONEMCP\Detector;
use ONEMCP\Helper;
use Exception;
use PostmanOptions;

if (!defined('ABSPATH')) {
	exit;
}

class SMTP {

	/**
	 * Ability definitions for active SMTP plugins.
	 *
	 * @return array<int,array<string,mixed>>
	 */
	static function definitions() {
		$definitions = array();
		$active      = false;
		foreach (self::plugins() as $slug => $plugin) {
			if (!\ONEMCP\Detector::is_active($slug)) {
				continue;
			}
			$active = true;
			$definitions[] = Helper::ability(
				'get-' . $slug,
				sprintf(
					/* translators: %s: SMTP plugin name. */
					__('Get %s settings', 'onemcp-pro'),
					$plugin['label']
				),
				sprintf(
					/* translators: %s: SMTP plugin name. */
					__('Reads the %s from address, mailer, host, port, and encryption. Passwords and API keys are not included.', 'onemcp-pro'),
					$plugin['label']
				),
				'onemcp-smtp',
				true,
				function ($input) use ($slug) {
					unset($input);
					return SMTP::read($slug);
				},
				array(),
				array(),
				array('cap' => 'manage_options')
			);
			$definitions[] = Helper::ability(
				'update-' . $slug,
				sprintf(
					/* translators: %s: SMTP plugin name. */
					__('Update %s settings', 'onemcp-pro'),
					$plugin['label']
				),
				sprintf(
					/* translators: %s: SMTP plugin name. */
					__('Updates the %s fields you send. Omitted fields, including a saved password, stay as they are.', 'onemcp-pro'),
					$plugin['label']
				),
				'onemcp-smtp',
				false,
				function ($input) use ($slug) {
					return SMTP::write($slug, $input);
				},
				self::write_properties(),
				array(),
				array('cap' => 'manage_options')
			);
		}

		if ($active) {
			$definitions[] = Helper::ability(
				'send-smtp-test',
				__('Send an SMTP test email', 'onemcp-pro'),
				__('Sends one plain-text test email through the active mailer plugin.', 'onemcp-pro'),
				'onemcp-smtp',
				false,
				array(__CLASS__, 'send_test'),
				array(
					'to'      => Helper::string_prop(__('Recipient email address.', 'onemcp-pro'), 200),
					'subject' => Helper::string_prop(__('Email subject. Defaults to an SMTP test subject.', 'onemcp-pro'), 200),
					'message' => Helper::string_prop(__('Plain-text email body.', 'onemcp-pro'), 5000),
				),
				array('to'),
				array('cap' => 'manage_options')
			);
		}

		return $definitions;
	}

	/**
	 * Read one plugin without secrets.
	 *
	 * @param string $slug Plugin slug.
	 * @return array<string,mixed>|WP_Error
	 */
	static function read($slug) {
		if (!isset(self::plugins()[$slug]) || !\ONEMCP\Detector::is_active($slug)) {
			return Helper::error('onemcp_unavailable', __('That SMTP plugin is not active.', 'onemcp-pro'));
		}
		return self::view($slug, self::load($slug));
	}

	/**
	 * Update one plugin and return the public settings.
	 *
	 * @param string              $slug  Plugin slug.
	 * @param array<string,mixed> $input Input.
	 * @return array<string,mixed>|WP_Error
	 */
	static function write($slug, $input) {
		if (!isset(self::plugins()[$slug]) || !\ONEMCP\Detector::is_active($slug)) {
			return Helper::error('onemcp_unavailable', __('That SMTP plugin is not active.', 'onemcp-pro'));
		}
		$changes = self::changes($input);
		if (is_wp_error($changes)) {
			return $changes;
		}
		$updated = self::apply($slug, self::load($slug), $changes);
		if (is_wp_error($updated)) {
			return $updated;
		}
		self::save($slug, $updated);
		return self::view($slug, $updated);
	}

	/**
	 * Send one test message through whichever mailer is hooked to wp_mail.
	 *
	 * @param array<string,mixed> $input Input.
	 * @return array<string,mixed>|WP_Error
	 */
	static function send_test($input) {
		$to = isset($input['to']) ? sanitize_email((string) $input['to']) : '';
		if ($to === '' || !is_email($to)) {
			return Helper::error('onemcp_invalid', __('A valid recipient email address is required.', 'onemcp-pro'));
		}
		$subject = isset($input['subject']) ? Helper::text($input['subject'], 200) : '';
		if ($subject === '') {
			$subject = __('SMTP test from OneMCP', 'onemcp-pro');
		}
		$message = isset($input['message']) ? sanitize_textarea_field((string) $input['message']) : '';
		if (function_exists('mb_substr')) {
			$message = mb_substr($message, 0, 5000);
		} else {
			$message = substr($message, 0, 5000);
		}
		if ($message === '') {
			$message = sprintf(
				/* translators: %s: site name. */
				__('This is a test email sent by OneMCP for %s.', 'onemcp-pro'),
				wp_specialchars_decode(get_bloginfo('name'), ENT_QUOTES)
			);
		}

		$error = '';
		$failed = function ($wp_error) use (&$error) {
			if ($error === '' && is_wp_error($wp_error)) {
				$error = $wp_error->get_error_message();
			}
		};
		add_action('wp_mail_failed', $failed);
		$sent = wp_mail($to, $subject, $message);
		remove_action('wp_mail_failed', $failed);

		if (!$sent && $error === '') {
			global $phpmailer;
			if (is_object($phpmailer) && !empty($phpmailer->ErrorInfo)) {
				$error = (string) $phpmailer->ErrorInfo;
			}
		}
		if (!$sent && $error === '') {
			$error = __('The test email was not sent. Check the mailer settings.', 'onemcp-pro');
		}

		return array(
			'sent'            => (bool) $sent,
			'to'              => $to,
			'error'           => $sent ? '' : $error,
			'active_plugins'  => Detector::active_slugs('smtp'),
		);
	}

	/**
	 * Supported plugins. GoSMTP is first.
	 *
	 * @return array<string,array<string,string>>
	 */
	private static function plugins() {
		return array(
			'gosmtp' => array(
				'label'  => 'GoSMTP',
				'option' => 'gosmtp_options',
			),
			'wp-mail-smtp' => array(
				'label'  => 'WP Mail SMTP',
				'option' => 'wp_mail_smtp',
			),
			'fluent-smtp' => array(
				'label'  => 'FluentSMTP',
				'option' => 'fluentmail-settings',
			),
			'easy-wp-smtp' => array(
				'label'  => 'Easy WP SMTP',
				'option' => 'easy_wp_smtp',
			),
			'post-smtp' => array(
				'label'  => 'Post SMTP',
				'option' => 'postman_options',
			),
			'smtp-mailer' => array(
				'label'  => 'SMTP Mailer',
				'option' => 'smtp_mailer_options',
			),
		);
	}

	/**
	 * Fields a client can send to an update ability.
	 *
	 * @return array<string,array<string,mixed>>
	 */
	private static function write_properties() {
		return array(
			'from_email'       => Helper::string_prop(__('From email address.', 'onemcp-pro'), 200),
			'from_name'        => Helper::string_prop(__('From name.', 'onemcp-pro'), 200),
			'force_from_email' => Helper::bool_prop(__('Use this from email for every message.', 'onemcp-pro')),
			'force_from_name'  => Helper::bool_prop(__('Use this from name for every message.', 'onemcp-pro')),
			'return_path'      => Helper::bool_prop(__('Set the return path to the from email.', 'onemcp-pro')),
			'mailer'           => Helper::string_prop(__('Mailer slug, such as smtp, mail, gmail, or sendgrid.', 'onemcp-pro'), 40),
			'host'             => Helper::string_prop(__('SMTP host, such as smtp.example.com.', 'onemcp-pro'), 253),
			'port'             => Helper::int_prop(__('SMTP port, from 1 to 65535.', 'onemcp-pro')),
			'encryption'       => Helper::string_prop(__('Encryption: none, ssl, or tls.', 'onemcp-pro'), 20),
			'auth'             => Helper::bool_prop(__('Whether the SMTP server requires authentication.', 'onemcp-pro')),
			'username'         => Helper::string_prop(__('SMTP username.', 'onemcp-pro'), 200),
			'password'         => Helper::string_prop(__('SMTP password. Leave this out to keep the saved password. It is not returned.', 'onemcp-pro'), 500),
			'connection_id'    => Helper::string_prop(__('GoSMTP or FluentSMTP connection id. Omit to update the primary connection.', 'onemcp-pro'), 80),
		);
	}

	/**
	 * Option array for a plugin.
	 *
	 * @param string $slug Plugin slug.
	 * @return array<string,mixed>
	 */
	private static function load($slug) {
		$key = self::option_key($slug);
		$stored = get_option($key, array());
		if (!is_array($stored) && $slug === 'easy-wp-smtp') {
			$stored = get_option('swpsmtp_options', array());
		}
		return is_array($stored) ? $stored : array();
	}

	/**
	 * Persist a plugin option.
	 *
	 * @param string              $slug    Plugin slug.
	 * @param array<string,mixed> $options Option array.
	 * @return void
	 */
	private static function save($slug, $options) {
		update_option(self::option_key($slug), $options, false);
		if ($slug === 'post-smtp' && class_exists('PostmanOptions') && method_exists('PostmanOptions', 'getInstance')) {
			$instance = PostmanOptions::getInstance();
			if (is_object($instance) && method_exists($instance, 'reload')) {
				$instance->reload();
			}
		}
	}

	/**
	 * Option name, including the Easy WP SMTP legacy record.
	 *
	 * @param string $slug Plugin slug.
	 * @return string
	 */
	private static function option_key($slug) {
		if ($slug === 'wp-mail-smtp' && class_exists('\WPMailSMTP\Options') && defined('WPMailSMTP\Options::META_KEY')) {
			return \WPMailSMTP\Options::META_KEY;
		}
		if ($slug === 'easy-wp-smtp') {
			if (self::easy_is_modern()) {
				if (class_exists('\EasyWPSMTP\Options') && defined('EasyWPSMTP\Options::META_KEY')) {
					return \EasyWPSMTP\Options::META_KEY;
				}
				return 'easy_wp_smtp';
			}
			return 'swpsmtp_options';
		}
		$plugins = self::plugins();
		return $plugins[$slug]['option'];
	}

	/**
	 * Whether the current Easy WP SMTP release is the rewritten plugin.
	 *
	 * @return bool
	 */
	private static function easy_is_modern() {
		return defined('EASY_WP_SMTP_VERSION') || class_exists('\EasyWPSMTP\Options') || function_exists('easy_wp_smtp');
	}

	/**
	 * Sanitize an update request.
	 *
	 * @param array<string,mixed> $input Input.
	 * @return array<string,mixed>|WP_Error
	 */
	private static function changes($input) {
		if (!is_array($input)) {
			$input = array();
		}
		$names = array(
			'from_email',
			'from_name',
			'force_from_email',
			'force_from_name',
			'return_path',
			'mailer',
			'host',
			'port',
			'encryption',
			'auth',
			'username',
			'password',
			'connection_id',
		);
		$changes = array();
		foreach ($names as $name) {
			if (array_key_exists($name, $input)) {
				$changes[$name] = $input[$name];
			}
		}

		if (array_key_exists('from_email', $changes)) {
			$email = sanitize_email((string) $changes['from_email']);
			if ($email === '' || !is_email($email)) {
				return Helper::error('onemcp_invalid', __('From email must be a valid email address.', 'onemcp-pro'));
			}
			$changes['from_email'] = $email;
		}
		if (array_key_exists('from_name', $changes)) {
			$changes['from_name'] = Helper::text($changes['from_name'], 200);
		}
		foreach (array('force_from_email', 'force_from_name', 'return_path', 'auth') as $flag) {
			if (array_key_exists($flag, $changes)) {
				$changes[$flag] = Helper::bool($changes[$flag]);
			}
		}
		if (array_key_exists('mailer', $changes)) {
			$mailer = sanitize_key((string) $changes['mailer']);
			if ($mailer === '' || strlen($mailer) > 40) {
				return Helper::error('onemcp_invalid', __('Mailer must be a short slug such as smtp or mail.', 'onemcp-pro'));
			}
			$changes['mailer'] = $mailer;
		}
		if (array_key_exists('host', $changes)) {
			$host = strtolower(Helper::text($changes['host'], 253));
			if ($host !== '' && !preg_match('/^[a-z0-9.-]+$/', $host)) {
				return Helper::error('onemcp_invalid', __('SMTP host can only contain letters, numbers, dots, and hyphens.', 'onemcp-pro'));
			}
			$changes['host'] = $host;
		}
		if (array_key_exists('port', $changes)) {
			$port = (int) $changes['port'];
			if ($port < 1 || $port > 65535) {
				return Helper::error('onemcp_invalid', __('SMTP port must be between 1 and 65535.', 'onemcp-pro'));
			}
			$changes['port'] = $port;
		}
		if (array_key_exists('encryption', $changes)) {
			$encryption = self::encryption($changes['encryption']);
			if ($encryption === '') {
				return Helper::error('onemcp_invalid', __('Encryption must be none, ssl, or tls.', 'onemcp-pro'));
			}
			$changes['encryption'] = $encryption;
		}
		if (array_key_exists('username', $changes)) {
			$changes['username'] = Helper::text($changes['username'], 200);
		}
		if (array_key_exists('password', $changes)) {
			$password = is_scalar($changes['password']) ? (string) $changes['password'] : '';
			$password = str_replace("\0", '', $password);
			if (strlen($password) > 500) {
				return Helper::error('onemcp_invalid', __('SMTP password is too long.', 'onemcp-pro'));
			}
			if (trim($password) === '') {
				unset($changes['password']);
			} else {
				$changes['password'] = $password;
			}
		}
		if (array_key_exists('connection_id', $changes)) {
			$changes['connection_id'] = Helper::text($changes['connection_id'], 80);
		}

		$writing = $changes;
		unset($writing['connection_id']);
		if ($writing === array()) {
			return Helper::error('onemcp_invalid', __('Provide at least one SMTP field to update.', 'onemcp-pro'));
		}
		return $changes;
	}

	/**
	 * Normalize an encryption value.
	 *
	 * @param mixed $value Raw value.
	 * @return string none, ssl, tls, or an empty string when it is not recognized.
	 */
	private static function encryption($value) {
		$normalized = strtolower(trim(sanitize_text_field(is_scalar($value) ? (string) $value : '')));
		if (in_array($normalized, array('none', 'off', 'no', 'false'), true)) {
			return 'none';
		}
		if (in_array($normalized, array('ssl', 'smtps'), true)) {
			return 'ssl';
		}
		if (in_array($normalized, array('tls', 'starttls', 'start_tls'), true)) {
			return 'tls';
		}
		return '';
	}

	/**
	 * Apply sanitized changes to a stored option array.
	 *
	 * @param string              $slug    Plugin slug.
	 * @param array<string,mixed> $options Stored options.
	 * @param array<string,mixed> $changes Sanitized changes.
	 * @return array<string,mixed>|WP_Error
	 */
	private static function apply($slug, $options, $changes) {
		if ($slug === 'gosmtp') {
			return self::apply_gosmtp($options, $changes);
		}
		if ($slug === 'wp-mail-smtp') {
			return self::apply_wp_mail_smtp($options, $changes);
		}
		if ($slug === 'fluent-smtp') {
			return self::apply_fluent($options, $changes);
		}
		if ($slug === 'easy-wp-smtp') {
			return self::easy_is_modern() ? self::apply_wp_mail_smtp($options, $changes, 'easy') : self::apply_easy_legacy($options, $changes);
		}
		if ($slug === 'post-smtp') {
			return self::apply_post($options, $changes);
		}
		if ($slug === 'smtp-mailer') {
			return self::apply_smtp_mailer($options, $changes);
		}
		return Helper::error('onemcp_invalid', __('That SMTP plugin is not supported.', 'onemcp-pro'));
	}

	/**
	 * Public settings for a stored option array.
	 *
	 * @param string              $slug    Plugin slug.
	 * @param array<string,mixed> $options Stored options.
	 * @return array<string,mixed>
	 */
	private static function view($slug, $options) {
		if ($slug === 'gosmtp') {
			return self::view_gosmtp($options);
		}
		if ($slug === 'wp-mail-smtp') {
			return self::view_wp_style($slug, $options, '\WPMailSMTP\Options');
		}
		if ($slug === 'fluent-smtp') {
			return self::view_fluent($options);
		}
		if ($slug === 'easy-wp-smtp') {
			if (self::easy_is_modern() || isset($options['mail']) || isset($options['smtp'])) {
				return self::view_wp_style($slug, $options, '\EasyWPSMTP\Options');
			}
			return self::view_easy_legacy($options);
		}
		if ($slug === 'post-smtp') {
			return self::view_post($options);
		}
		return self::view_smtp_mailer($options);
	}

	/**
	 * Shared response shape.
	 *
	 * @param string              $slug   Plugin slug.
	 * @param array<string,mixed> $fields Field values.
	 * @return array<string,mixed>
	 */
	private static function payload($slug, $fields) {
		$plugins = self::plugins();
		$base    = array(
			'plugin'              => $slug,
			'label'               => isset($plugins[$slug]['label']) ? $plugins[$slug]['label'] : $slug,
			'from_email'          => '',
			'from_name'           => '',
			'force_from_email'    => false,
			'force_from_name'     => false,
			'return_path'         => false,
			'mailer'              => '',
			'host'                => '',
			'port'                => '',
			'encryption'          => '',
			'auth'                => false,
			'username'            => '',
			'password_set'        => false,
			'locked_by_constants' => false,
			'connections'         => array(),
		);
		return array_merge($base, $fields);
	}

	/**
	 * Whether a settings bag has a non-empty secret.
	 *
	 * @param mixed $bag Settings.
	 * @return bool
	 */
	private static function secret_set($bag) {
		if (!is_array($bag)) {
			return false;
		}
		foreach ($bag as $key => $value) {
			$key_text = strtolower((string) $key);
			if (!preg_match('/password|passwd|secret|token|api[_-]?key|private/', $key_text)) {
				continue;
			}
			if (is_string($value) && $value !== '') {
				return true;
			}
			if (is_array($value) && $value !== array()) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Store a password with a plugin crypto class when that class exists.
	 *
	 * @param string $password Plain password.
	 * @param string $class    Crypto class name.
	 * @return string|WP_Error
	 */
	private static function encrypt_pass($password, $class) {
		if (!class_exists($class) || !method_exists($class, 'encrypt')) {
			return $password;
		}
		try {
			$encrypted = call_user_func(array($class, 'encrypt'), $password);
		} catch (Exception $exception) {
			return Helper::error('onemcp_invalid', __('The SMTP password could not be stored securely.', 'onemcp-pro'));
		}
		if (!is_string($encrypted) || $encrypted === '') {
			return Helper::error('onemcp_invalid', __('The SMTP password could not be stored securely.', 'onemcp-pro'));
		}
		return $encrypted;
	}

	/**
	 * GoSMTP primary or named connection.
	 *
	 * @param array<string,mixed> $options Stored options.
	 * @param array<string,mixed> $changes Changes.
	 * @return array<string,mixed>|WP_Error
	 */
	private static function apply_gosmtp($options, $changes) {
		if (!isset($options['mailer']) || !is_array($options['mailer'])) {
			$options['mailer'] = array();
		}
		$connection_id = array_key_exists('connection_id', $changes) ? (string) $changes['connection_id'] : '0';
		if ($connection_id === '') {
			$connection_id = '0';
		}
		$primary = ($connection_id === '0');
		// array_key_exists already matches a numeric string to an integer index.
		// Casting with (int) would treat "abc" as 0 and "2abc" as 2.
		if (!$primary && !array_key_exists($connection_id, $options['mailer'])) {
			return Helper::error('onemcp_invalid', __('That GoSMTP connection does not exist.', 'onemcp-pro'));
		}
		$key = $primary ? 0 : $connection_id;
		if (!isset($options['mailer'][$key]) || !is_array($options['mailer'][$key])) {
			$options['mailer'][$key] = array('mail_type' => 'mail');
		}
		$mailer = $options['mailer'][$key];

		$smtp_fields = array('host', 'port', 'encryption', 'auth', 'username', 'password');
		$touches_smtp = false;
		foreach ($smtp_fields as $field) {
			if (array_key_exists($field, $changes)) {
				$touches_smtp = true;
				break;
			}
		}
		if (array_key_exists('mailer', $changes)) {
			$mailer['mail_type'] = $changes['mailer'] === 'php' ? 'mail' : $changes['mailer'];
		} elseif ($touches_smtp && (empty($mailer['mail_type']) || $mailer['mail_type'] === 'mail')) {
			$mailer['mail_type'] = 'smtp';
		}
		if (array_key_exists('host', $changes)) {
			$mailer['smtp_host'] = $changes['host'];
		}
		if (array_key_exists('port', $changes)) {
			$mailer['smtp_port'] = (string) $changes['port'];
		}
		if (array_key_exists('encryption', $changes)) {
			$mailer['encryption'] = $changes['encryption'];
		}
		if (array_key_exists('auth', $changes)) {
			$mailer['smtp_auth'] = $changes['auth'] ? 'Yes' : 'No';
		}
		if (array_key_exists('username', $changes)) {
			$mailer['smtp_username'] = $changes['username'];
		}
		if (array_key_exists('password', $changes)) {
			$mailer['smtp_password'] = $changes['password'];
		}

		$from_target = $primary ? 'options' : 'mailer';
		if (array_key_exists('from_email', $changes)) {
			if ($from_target === 'options') {
				$options['from_email'] = $changes['from_email'];
			} else {
				$mailer['from_email'] = $changes['from_email'];
			}
		}
		if (array_key_exists('from_name', $changes)) {
			if ($from_target === 'options') {
				$options['from_name'] = $changes['from_name'];
			} else {
				$mailer['from_name'] = $changes['from_name'];
			}
		}
		if (array_key_exists('force_from_email', $changes)) {
			$value = $changes['force_from_email'] ? 1 : '';
			if ($from_target === 'options') {
				$options['force_from_email'] = $value;
			} else {
				$mailer['force_from_email'] = $value;
			}
		}
		if (array_key_exists('force_from_name', $changes)) {
			$value = $changes['force_from_name'] ? 1 : '';
			if ($from_target === 'options') {
				$options['force_from_name'] = $value;
			} else {
				$mailer['force_from_name'] = $value;
			}
		}
		if (array_key_exists('return_path', $changes)) {
			$value = $changes['return_path'] ? 1 : '';
			if ($from_target === 'options') {
				$options['return_path'] = $value;
			} else {
				$mailer['return_path'] = $value;
			}
		}

		$options['mailer'][$key] = $mailer;
		return $options;
	}

	/**
	 * GoSMTP settings without secrets.
	 *
	 * @param array<string,mixed> $options Stored options.
	 * @return array<string,mixed>
	 */
	private static function view_gosmtp($options) {
		$mailers = (isset($options['mailer']) && is_array($options['mailer'])) ? $options['mailer'] : array();
		$primary = (isset($mailers[0]) && is_array($mailers[0])) ? $mailers[0] : array();
		$connections = array();
		foreach ($mailers as $id => $mailer) {
			if (!is_array($mailer)) {
				continue;
			}
			$from_email = isset($mailer['from_email']) ? (string) $mailer['from_email'] : '';
			if ((string) $id === '0' && $from_email === '' && isset($options['from_email'])) {
				$from_email = (string) $options['from_email'];
			}
			$connections[] = array(
				'id'                => (string) $id,
				'title'             => isset($mailer['nickname']) ? (string) $mailer['nickname'] : '',
				'mailer'            => isset($mailer['mail_type']) ? (string) $mailer['mail_type'] : 'mail',
				'is_primary'        => (string) $id === '0',
				'from_email'        => $from_email,
				'host'              => isset($mailer['smtp_host']) ? (string) $mailer['smtp_host'] : '',
				'port'              => isset($mailer['smtp_port']) ? (string) $mailer['smtp_port'] : '',
				'encryption'        => isset($mailer['encryption']) ? (string) $mailer['encryption'] : '',
				'username'          => isset($mailer['smtp_username']) ? (string) $mailer['smtp_username'] : '',
				'password_set'      => self::secret_set($mailer),
				'backup_connection' => isset($mailer['backup_connection']) ? (string) $mailer['backup_connection'] : '',
			);
		}
		$auth = isset($primary['smtp_auth']) ? strtolower((string) $primary['smtp_auth']) : '';
		return self::payload('gosmtp', array(
			'from_email'       => isset($options['from_email']) ? (string) $options['from_email'] : '',
			'from_name'        => isset($options['from_name']) ? (string) $options['from_name'] : '',
			'force_from_email' => !empty($options['force_from_email']),
			'force_from_name'  => !empty($options['force_from_name']),
			'return_path'      => !empty($options['return_path']),
			'mailer'           => isset($primary['mail_type']) ? (string) $primary['mail_type'] : 'mail',
			'host'             => isset($primary['smtp_host']) ? (string) $primary['smtp_host'] : '',
			'port'             => isset($primary['smtp_port']) ? (string) $primary['smtp_port'] : '',
			'encryption'       => isset($primary['encryption']) ? (string) $primary['encryption'] : '',
			'auth'             => $auth === 'yes',
			'username'         => isset($primary['smtp_username']) ? (string) $primary['smtp_username'] : '',
			'password_set'     => self::secret_set($primary),
			'connections'      => $connections,
		));
	}

	/**
	 * WP Mail SMTP and the modern Easy WP SMTP option shape.
	 *
	 * @param array<string,mixed> $options Stored options.
	 * @param array<string,mixed> $changes Changes.
	 * @param string              $family  wp or easy.
	 * @return array<string,mixed>|WP_Error
	 */
	private static function apply_wp_mail_smtp($options, $changes, $family = 'wp') {
		if (!isset($options['mail']) || !is_array($options['mail'])) {
			$options['mail'] = array();
		}
		if (!isset($options['smtp']) || !is_array($options['smtp'])) {
			$options['smtp'] = array();
		}
		$crypto = $family === 'easy' ? '\EasyWPSMTP\Helpers\Crypto' : '\WPMailSMTP\Helpers\Crypto';
		if (array_key_exists('from_email', $changes)) {
			$options['mail']['from_email'] = $changes['from_email'];
		}
		if (array_key_exists('from_name', $changes)) {
			$options['mail']['from_name'] = $changes['from_name'];
		}
		if (array_key_exists('force_from_email', $changes)) {
			$options['mail']['from_email_force'] = (bool) $changes['force_from_email'];
		}
		if (array_key_exists('force_from_name', $changes)) {
			$options['mail']['from_name_force'] = (bool) $changes['force_from_name'];
		}
		if (array_key_exists('return_path', $changes)) {
			$options['mail']['return_path'] = (bool) $changes['return_path'];
		}
		$smtp_fields = array('host', 'port', 'encryption', 'auth', 'username', 'password');
		$touches_smtp = false;
		foreach ($smtp_fields as $field) {
			if (array_key_exists($field, $changes)) {
				$touches_smtp = true;
				break;
			}
		}
		if (array_key_exists('mailer', $changes)) {
			$options['mail']['mailer'] = ($changes['mailer'] === 'php') ? 'mail' : $changes['mailer'];
		} elseif ($touches_smtp && (empty($options['mail']['mailer']) || $options['mail']['mailer'] === 'mail')) {
			$options['mail']['mailer'] = 'smtp';
		}
		if (array_key_exists('host', $changes)) {
			$options['smtp']['host'] = $changes['host'];
		}
		if (array_key_exists('port', $changes)) {
			$options['smtp']['port'] = (int) $changes['port'];
		}
		if (array_key_exists('encryption', $changes)) {
			$options['smtp']['encryption'] = $changes['encryption'];
		}
		if (array_key_exists('auth', $changes)) {
			$options['smtp']['auth'] = (bool) $changes['auth'];
		}
		if (array_key_exists('username', $changes)) {
			$options['smtp']['user'] = $changes['username'];
		}
		if (array_key_exists('password', $changes)) {
			$stored = self::encrypt_pass($changes['password'], $crypto);
			if (is_wp_error($stored)) {
				return $stored;
			}
			$options['smtp']['pass'] = $stored;
		}
		return $options;
	}

	/**
	 * WP Mail SMTP style settings, with constant values when those override the database.
	 *
	 * @param string              $slug    Plugin slug.
	 * @param array<string,mixed> $options Stored options.
	 * @param string              $class   Options class.
	 * @return array<string,mixed>
	 */
	private static function view_wp_style($slug, $options, $class) {
		$mail = (isset($options['mail']) && is_array($options['mail'])) ? $options['mail'] : array();
		$smtp = (isset($options['smtp']) && is_array($options['smtp'])) ? $options['smtp'] : array();
		$locked = false;
		$api    = null;
		if (class_exists($class) && method_exists($class, 'init')) {
			$api = call_user_func(array($class, 'init'));
			if (is_object($api) && method_exists($api, 'is_const_enabled')) {
				$locked = (bool) $api->is_const_enabled();
			}
		}
		$pick = function ($group, $key, $stored) use ($locked, $api) {
			if ($locked && is_object($api) && method_exists($api, 'is_const_defined') && $api->is_const_defined($group, $key) && method_exists($api, 'get')) {
				$value = $api->get($group, $key);
				if ($key === 'pass') {
					return $stored;
				}
				return is_scalar($value) ? (string) $value : $stored;
			}
			return $stored;
		};
		$password_set = !empty($smtp['pass']);
		if ($locked && is_object($api) && method_exists($api, 'is_const_defined') && $api->is_const_defined('smtp', 'pass')) {
			$password_set = true;
		}
		$auth = $pick('smtp', 'auth', !empty($smtp['auth']) ? '1' : '');
		return self::payload($slug, array(
			'from_email'          => (string) $pick('mail', 'from_email', isset($mail['from_email']) ? (string) $mail['from_email'] : ''),
			'from_name'           => (string) $pick('mail', 'from_name', isset($mail['from_name']) ? (string) $mail['from_name'] : ''),
			'force_from_email'    => Helper::bool($pick('mail', 'from_email_force', !empty($mail['from_email_force']) ? '1' : '')),
			'force_from_name'     => Helper::bool($pick('mail', 'from_name_force', !empty($mail['from_name_force']) ? '1' : '')),
			'return_path'         => Helper::bool($pick('mail', 'return_path', !empty($mail['return_path']) ? '1' : '')),
			'mailer'              => (string) $pick('mail', 'mailer', isset($mail['mailer']) ? (string) $mail['mailer'] : 'mail'),
			'host'                => (string) $pick('smtp', 'host', isset($smtp['host']) ? (string) $smtp['host'] : ''),
			'port'                => (string) $pick('smtp', 'port', isset($smtp['port']) ? (string) $smtp['port'] : ''),
			'encryption'          => (string) $pick('smtp', 'encryption', isset($smtp['encryption']) ? (string) $smtp['encryption'] : ''),
			'auth'                => Helper::bool($auth),
			'username'            => (string) $pick('smtp', 'user', isset($smtp['user']) ? (string) $smtp['user'] : ''),
			'password_set'        => $password_set,
			'locked_by_constants' => $locked,
		));
	}

	/**
	 * FluentSMTP default or named connection.
	 *
	 * @param array<string,mixed> $options Stored options.
	 * @param array<string,mixed> $changes Changes.
	 * @return array<string,mixed>|WP_Error
	 */
	private static function apply_fluent($options, $changes) {
		if (!isset($options['connections']) || !is_array($options['connections'])) {
			$options['connections'] = array();
		}
		if (!isset($options['misc']) || !is_array($options['misc'])) {
			$options['misc'] = array();
		}
		$connection_id = array_key_exists('connection_id', $changes) ? (string) $changes['connection_id'] : '';
		if ($connection_id === '' && !empty($options['misc']['default_connection'])) {
			$connection_id = (string) $options['misc']['default_connection'];
		}
		if ($connection_id === '' || !isset($options['connections'][$connection_id]) || !is_array($options['connections'][$connection_id])) {
			if (array_key_exists('connection_id', $changes) && $changes['connection_id'] !== '') {
				return Helper::error('onemcp_invalid', __('That FluentSMTP connection does not exist.', 'onemcp-pro'));
			}
			$keys = array_keys($options['connections']);
			if ($keys !== array() && isset($options['connections'][$keys[0]]) && is_array($options['connections'][$keys[0]])) {
				$connection_id = (string) $keys[0];
			} else {
				$connection_id = strtolower(wp_generate_password(12, false, false));
				$options['connections'][$connection_id] = array(
					'title'             => 'SMTP',
					'provider_settings' => array(
						'provider'         => 'smtp',
						'sender_email'     => '',
						'sender_name'      => '',
						'force_from_email' => 'no',
						'force_from_name'  => 'no',
						'return_path'      => 'no',
						'host'             => '',
						'port'             => '587',
						'auth'             => 'yes',
						'username'         => '',
						'password'         => '',
						'auto_tls'         => 'yes',
						'encryption'       => 'tls',
						'key_store'        => 'db',
					),
				);
				$options['misc']['default_connection'] = $connection_id;
			}
		}

		$connection = $options['connections'][$connection_id];
		if (!isset($connection['provider_settings']) || !is_array($connection['provider_settings'])) {
			$connection['provider_settings'] = array('provider' => 'smtp');
		}
		$provider = $connection['provider_settings'];
		$config_store = isset($provider['key_store']) && $provider['key_store'] === 'wp_config';
		if ($config_store && (array_key_exists('username', $changes) || array_key_exists('password', $changes))) {
			return Helper::error('onemcp_invalid', __('This FluentSMTP connection reads the username and password from wp-config.php.', 'onemcp-pro'));
		}

		$smtp_fields = array('host', 'port', 'encryption', 'auth', 'username', 'password');
		$touches_smtp = false;
		foreach ($smtp_fields as $field) {
			if (array_key_exists($field, $changes)) {
				$touches_smtp = true;
				break;
			}
		}
		if (array_key_exists('mailer', $changes)) {
			$provider['provider'] = $changes['mailer'] === 'php' ? 'mail' : $changes['mailer'];
		} elseif ($touches_smtp && (empty($provider['provider']) || $provider['provider'] === 'mail')) {
			$provider['provider'] = 'smtp';
		}
		if (array_key_exists('from_email', $changes)) {
			$provider['sender_email'] = $changes['from_email'];
		}
		if (array_key_exists('from_name', $changes)) {
			$provider['sender_name'] = $changes['from_name'];
		}
		if (array_key_exists('force_from_email', $changes)) {
			$provider['force_from_email'] = $changes['force_from_email'] ? 'yes' : 'no';
		}
		if (array_key_exists('force_from_name', $changes)) {
			$provider['force_from_name'] = $changes['force_from_name'] ? 'yes' : 'no';
		}
		if (array_key_exists('return_path', $changes)) {
			$provider['return_path'] = $changes['return_path'] ? 'yes' : 'no';
		}
		if (array_key_exists('host', $changes)) {
			$provider['host'] = $changes['host'];
		}
		if (array_key_exists('port', $changes)) {
			$provider['port'] = (string) $changes['port'];
		}
		if (array_key_exists('encryption', $changes)) {
			$provider['encryption'] = $changes['encryption'];
		}
		if (array_key_exists('auth', $changes)) {
			$provider['auth'] = $changes['auth'] ? 'yes' : 'no';
		}
		if (array_key_exists('username', $changes)) {
			$provider['username'] = $changes['username'];
		}
		if (array_key_exists('password', $changes)) {
			$provider['password'] = self::fluent_password($options, $provider, $changes['password']);
			if (is_wp_error($provider['password'])) {
				return $provider['password'];
			}
		}
		$connection['provider_settings'] = $provider;
		$options['connections'][$connection_id] = $connection;
		return $options;
	}

	/**
	 * Encrypt a FluentSMTP password when that connection stores secrets encrypted.
	 *
	 * @param array<string,mixed> $options  All settings.
	 * @param array<string,mixed> $provider Provider settings.
	 * @param string              $password Plain password.
	 * @return string|WP_Error
	 */
	private static function fluent_password($options, $provider, $password) {
		$disabled = isset($provider['disable_encryption']) && $provider['disable_encryption'] === 'yes';
		if ($disabled || empty($options['use_encrypt'])) {
			return $password;
		}
		if (!function_exists('fluentMailEncryptDecrypt')) {
			return Helper::error('onemcp_invalid', __('The SMTP password could not be stored securely.', 'onemcp-pro'));
		}
		$encrypted = fluentMailEncryptDecrypt($password, 'e');
		if (!is_string($encrypted) || $encrypted === '') {
			return Helper::error('onemcp_invalid', __('The SMTP password could not be stored securely.', 'onemcp-pro'));
		}
		return $encrypted;
	}

	/**
	 * FluentSMTP settings for the default connection.
	 *
	 * @param array<string,mixed> $options Stored options.
	 * @return array<string,mixed>
	 */
	private static function view_fluent($options) {
		$connections = (isset($options['connections']) && is_array($options['connections'])) ? $options['connections'] : array();
		$default_id  = (isset($options['misc']['default_connection'])) ? (string) $options['misc']['default_connection'] : '';
		if ($default_id === '' || !isset($connections[$default_id])) {
			$keys = array_keys($connections);
			$default_id = $keys !== array() ? (string) $keys[0] : '';
		}
		$rows = array();
		foreach ($connections as $id => $connection) {
			if (!is_array($connection)) {
				continue;
			}
			$provider = (isset($connection['provider_settings']) && is_array($connection['provider_settings'])) ? $connection['provider_settings'] : array();
			$rows[] = array(
				'id'           => (string) $id,
				'title'        => isset($connection['title']) ? (string) $connection['title'] : '',
				'mailer'       => isset($provider['provider']) ? (string) $provider['provider'] : '',
				'is_primary'   => (string) $id === $default_id,
				'from_email'   => isset($provider['sender_email']) ? (string) $provider['sender_email'] : '',
				'host'         => isset($provider['host']) ? (string) $provider['host'] : '',
				'port'         => isset($provider['port']) ? (string) $provider['port'] : '',
				'encryption'   => isset($provider['encryption']) ? (string) $provider['encryption'] : '',
				'username'     => isset($provider['key_store']) && $provider['key_store'] === 'wp_config' && defined('FLUENTMAIL_SMTP_USERNAME') ? (string) FLUENTMAIL_SMTP_USERNAME : (isset($provider['username']) ? (string) $provider['username'] : ''),
				'password_set' => self::secret_set($provider) || (isset($provider['key_store']) && $provider['key_store'] === 'wp_config' && defined('FLUENTMAIL_SMTP_PASSWORD') && FLUENTMAIL_SMTP_PASSWORD !== ''),
			);
		}
		$current  = ($default_id !== '' && isset($connections[$default_id]) && is_array($connections[$default_id])) ? $connections[$default_id] : array();
		$provider = (isset($current['provider_settings']) && is_array($current['provider_settings'])) ? $current['provider_settings'] : array();
		$config   = isset($provider['key_store']) && $provider['key_store'] === 'wp_config';
		$username = isset($provider['username']) ? (string) $provider['username'] : '';
		if ($config && defined('FLUENTMAIL_SMTP_USERNAME')) {
			$username = (string) FLUENTMAIL_SMTP_USERNAME;
		}
		return self::payload('fluent-smtp', array(
			'from_email'          => isset($provider['sender_email']) ? (string) $provider['sender_email'] : '',
			'from_name'           => isset($provider['sender_name']) ? (string) $provider['sender_name'] : '',
			'force_from_email'    => isset($provider['force_from_email']) && $provider['force_from_email'] === 'yes',
			'force_from_name'     => isset($provider['force_from_name']) && $provider['force_from_name'] === 'yes',
			'return_path'         => isset($provider['return_path']) && $provider['return_path'] === 'yes',
			'mailer'              => isset($provider['provider']) ? (string) $provider['provider'] : '',
			'host'                => isset($provider['host']) ? (string) $provider['host'] : '',
			'port'                => isset($provider['port']) ? (string) $provider['port'] : '',
			'encryption'          => isset($provider['encryption']) ? (string) $provider['encryption'] : '',
			'auth'                => isset($provider['auth']) && $provider['auth'] === 'yes',
			'username'            => $username,
			'password_set'        => self::secret_set($provider) || ($config && defined('FLUENTMAIL_SMTP_PASSWORD') && FLUENTMAIL_SMTP_PASSWORD !== ''),
			'locked_by_constants' => $config,
			'connections'         => $rows,
		));
	}

	/**
	 * Legacy Easy WP SMTP option from the 1.x plugin.
	 *
	 * @param array<string,mixed> $options Stored options.
	 * @param array<string,mixed> $changes Changes.
	 * @return array<string,mixed>
	 */
	private static function apply_easy_legacy($options, $changes) {
		if (!isset($options['smtp_settings']) || !is_array($options['smtp_settings'])) {
			$options['smtp_settings'] = array();
		}
		if (array_key_exists('from_email', $changes)) {
			$options['from_email_field'] = $changes['from_email'];
		}
		if (array_key_exists('from_name', $changes)) {
			$options['from_name_field'] = $changes['from_name'];
		}
		if (array_key_exists('force_from_name', $changes)) {
			$options['force_from_name_replace'] = $changes['force_from_name'] ? '1' : '';
		}
		if (array_key_exists('host', $changes)) {
			$options['smtp_settings']['host'] = $changes['host'];
		}
		if (array_key_exists('port', $changes)) {
			$options['smtp_settings']['port'] = (string) $changes['port'];
		}
		if (array_key_exists('encryption', $changes)) {
			$options['smtp_settings']['type_encryption'] = $changes['encryption'];
		}
		if (array_key_exists('auth', $changes)) {
			$options['smtp_settings']['autentication'] = $changes['auth'] ? 'yes' : 'no';
		}
		if (array_key_exists('username', $changes)) {
			$options['smtp_settings']['username'] = $changes['username'];
		}
		if (array_key_exists('password', $changes)) {
			$options['smtp_settings']['password'] = $changes['password'];
		}
		return $options;
	}

	/**
	 * Legacy Easy WP SMTP public settings.
	 *
	 * @param array<string,mixed> $options Stored options.
	 * @return array<string,mixed>
	 */
	private static function view_easy_legacy($options) {
		$smtp = (isset($options['smtp_settings']) && is_array($options['smtp_settings'])) ? $options['smtp_settings'] : array();
		$auth = isset($smtp['autentication']) ? strtolower((string) $smtp['autentication']) : '';
		return self::payload('easy-wp-smtp', array(
			'from_email'       => isset($options['from_email_field']) ? (string) $options['from_email_field'] : '',
			'from_name'        => isset($options['from_name_field']) ? (string) $options['from_name_field'] : '',
			'force_from_name'  => !empty($options['force_from_name_replace']),
			'mailer'           => 'smtp',
			'host'             => isset($smtp['host']) ? (string) $smtp['host'] : '',
			'port'             => isset($smtp['port']) ? (string) $smtp['port'] : '',
			'encryption'       => isset($smtp['type_encryption']) ? (string) $smtp['type_encryption'] : '',
			'auth'             => in_array($auth, array('yes', 'true', '1', 'on'), true),
			'username'         => isset($smtp['username']) ? (string) $smtp['username'] : '',
			'password_set'     => !empty($smtp['password']),
		));
	}

	/**
	 * Post SMTP option keys.
	 *
	 * @param array<string,mixed> $options Stored options.
	 * @param array<string,mixed> $changes Changes.
	 * @return array<string,mixed>
	 */
	private static function apply_post($options, $changes) {
		$smtp_fields = array('host', 'port', 'encryption', 'auth', 'username', 'password');
		$touches_smtp = false;
		foreach ($smtp_fields as $field) {
			if (array_key_exists($field, $changes)) {
				$touches_smtp = true;
				break;
			}
		}
		if (array_key_exists('mailer', $changes)) {
			$options['transport_type'] = ($changes['mailer'] === 'php' || $changes['mailer'] === 'mail') ? 'default' : $changes['mailer'];
		} elseif ($touches_smtp && (empty($options['transport_type']) || $options['transport_type'] === 'default')) {
			$options['transport_type'] = 'smtp';
		}
		if (array_key_exists('from_email', $changes)) {
			$options['sender_email'] = $changes['from_email'];
		}
		if (array_key_exists('from_name', $changes)) {
			$options['sender_name'] = $changes['from_name'];
		}
		if (array_key_exists('force_from_email', $changes)) {
			$options['prevent_sender_email_override'] = (bool) $changes['force_from_email'];
		}
		if (array_key_exists('force_from_name', $changes)) {
			$options['prevent_sender_name_override'] = (bool) $changes['force_from_name'];
		}
		if (array_key_exists('return_path', $changes) && $changes['return_path'] && array_key_exists('from_email', $changes)) {
			$options['envelope_sender'] = $changes['from_email'];
		} elseif (array_key_exists('return_path', $changes) && $changes['return_path'] && !empty($options['sender_email'])) {
			$options['envelope_sender'] = (string) $options['sender_email'];
		}
		if (array_key_exists('host', $changes)) {
			$options['hostname'] = $changes['host'];
		}
		if (array_key_exists('port', $changes)) {
			$options['port'] = (string) $changes['port'];
		}
		if (array_key_exists('encryption', $changes)) {
			$options['enc_type'] = $changes['encryption'];
		}
		if (array_key_exists('auth', $changes)) {
			if ($changes['auth']) {
				$current = isset($options['auth_type']) ? (string) $options['auth_type'] : '';
				if (!in_array($current, array('plain', 'login', 'crammd5', 'oauth2'), true)) {
					$options['auth_type'] = 'login';
				}
			} else {
				$options['auth_type'] = 'none';
			}
		}
		if (array_key_exists('username', $changes)) {
			$options['basic_auth_username'] = $changes['username'];
		}
		if (array_key_exists('password', $changes)) {
			$options['basic_auth_password'] = base64_encode($changes['password']);
			if (empty($options['auth_type']) || $options['auth_type'] === 'none') {
				$options['auth_type'] = 'login';
			}
		}
		return $options;
	}

	/**
	 * Post SMTP public settings.
	 *
	 * @param array<string,mixed> $options Stored options.
	 * @return array<string,mixed>
	 */
	private static function view_post($options) {
		$auth = isset($options['auth_type']) ? (string) $options['auth_type'] : '';
		$locked = (defined('POST_SMTP_AUTH_USERNAME') && POST_SMTP_AUTH_USERNAME) || (defined('POST_SMTP_AUTH_PASSWORD') && POST_SMTP_AUTH_PASSWORD);
		$username = isset($options['basic_auth_username']) ? (string) $options['basic_auth_username'] : '';
		if (defined('POST_SMTP_AUTH_USERNAME') && POST_SMTP_AUTH_USERNAME) {
			$username = (string) POST_SMTP_AUTH_USERNAME;
		}
		$transport = isset($options['transport_type']) ? (string) $options['transport_type'] : 'smtp';
		return self::payload('post-smtp', array(
			'from_email'          => isset($options['sender_email']) ? (string) $options['sender_email'] : '',
			'from_name'           => isset($options['sender_name']) ? (string) $options['sender_name'] : '',
			'force_from_email'    => !empty($options['prevent_sender_email_override']),
			'force_from_name'     => !empty($options['prevent_sender_name_override']),
			'return_path'         => !empty($options['envelope_sender']),
			'mailer'              => $transport,
			'host'                => isset($options['hostname']) ? (string) $options['hostname'] : '',
			'port'                => isset($options['port']) ? (string) $options['port'] : '',
			'encryption'          => isset($options['enc_type']) ? (string) $options['enc_type'] : '',
			'auth'                => in_array($auth, array('plain', 'login', 'crammd5', 'oauth2'), true),
			'username'            => $username,
			'password_set'        => !empty($options['basic_auth_password']) || (defined('POST_SMTP_AUTH_PASSWORD') && POST_SMTP_AUTH_PASSWORD !== ''),
			'locked_by_constants' => (bool) $locked,
		));
	}

	/**
	 * SMTP Mailer option. The password is stored as base64, matching the plugin.
	 *
	 * @param array<string,mixed> $options Stored options.
	 * @param array<string,mixed> $changes Changes.
	 * @return array<string,mixed>
	 */
	private static function apply_smtp_mailer($options, $changes) {
		if (array_key_exists('host', $changes)) {
			$options['smtp_host'] = $changes['host'];
		}
		if (array_key_exists('auth', $changes)) {
			$options['smtp_auth'] = $changes['auth'] ? 'true' : 'false';
		}
		if (array_key_exists('username', $changes)) {
			$options['smtp_username'] = $changes['username'];
		}
		if (array_key_exists('password', $changes)) {
			$options['smtp_password'] = base64_encode($changes['password']);
		}
		if (array_key_exists('encryption', $changes)) {
			$options['type_of_encryption'] = $changes['encryption'];
		}
		if (array_key_exists('port', $changes)) {
			$options['smtp_port'] = (string) $changes['port'];
		}
		if (array_key_exists('from_email', $changes)) {
			$options['from_email'] = $changes['from_email'];
		}
		if (array_key_exists('from_name', $changes)) {
			$options['from_name'] = $changes['from_name'];
		}
		if (array_key_exists('force_from_name', $changes)) {
			$options['force_from_name'] = $changes['force_from_name'] ? '1' : '';
		}
		if (array_key_exists('force_from_email', $changes)) {
			$options['force_from_email'] = $changes['force_from_email'] ? '1' : '';
		}
		return $options;
	}

	/**
	 * SMTP Mailer public settings.
	 *
	 * @param array<string,mixed> $options Stored options.
	 * @return array<string,mixed>
	 */
	private static function view_smtp_mailer($options) {
		return self::payload('smtp-mailer', array(
			'from_email'       => isset($options['from_email']) ? (string) $options['from_email'] : '',
			'from_name'        => isset($options['from_name']) ? (string) $options['from_name'] : '',
			'force_from_email' => !empty($options['force_from_email']),
			'force_from_name'  => !empty($options['force_from_name']),
			'mailer'           => 'smtp',
			'host'             => isset($options['smtp_host']) ? (string) $options['smtp_host'] : '',
			'port'             => isset($options['smtp_port']) ? (string) $options['smtp_port'] : '',
			'encryption'       => isset($options['type_of_encryption']) ? (string) $options['type_of_encryption'] : '',
			'auth'             => isset($options['smtp_auth']) && (string) $options['smtp_auth'] === 'true',
			'username'         => isset($options['smtp_username']) ? (string) $options['smtp_username'] : '',
			'password_set'     => !empty($options['smtp_password']),
		));
	}
}
