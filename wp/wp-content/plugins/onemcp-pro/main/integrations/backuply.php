<?php
/**
 * Backuply backups.
 *
 * Creating a backup schedules Backuply's own runner on WP-Cron. Backuply continues
 * the job with its self-requests and stops the request when a chunk finishes.
 *
 * @package OneMCPPro
 */

namespace OneMCPPro;

use ONEMCP\Detector;
use ONEMCP\Helper;

if (!defined('ABSPATH')) {
	exit;
}

class Backuply {

	/**
	 * Ability definitions. Empty unless Backuply is active.
	 *
	 * @return array<int,array<string,mixed>>
	 */
	 static function definitions() {
		if (!Detector::is_active('backuply')) {
			return array();
		}

		$cap   = array('cap' => 'manage_options');
		$write = array('cap' => 'manage_options');
		return array(
			Helper::ability(
				'list-backuply-backups',
				__('List Backuply backups', 'onemcp-pro'),
				__('Lists Backuply backups that are stored on this site. Remote credentials are not included.', 'onemcp-pro'),
				'onemcp-backups',
				true,
				array(__CLASS__, 'list_backups'),
				array(
					'limit' => Helper::int_prop(__('How many backups to return. Maximum 50.', 'onemcp-pro')),
				),
				array(),
				$cap
			),
			Helper::ability(
				'get-backuply-status',
				__('Get Backuply status', 'onemcp-pro'),
				__('Returns whether a Backuply backup is running, plus storage location names.', 'onemcp-pro'),
				'onemcp-backups',
				true,
				array(__CLASS__, 'status'),
				array(),
				array(),
				$cap
			),
			Helper::ability(
				'create-backuply-backup',
				__('Create Backuply backup', 'onemcp-pro'),
				__('Starts a Backuply backup of the files, the database, or both. The job runs in the background.', 'onemcp-pro'),
				'onemcp-backups',
				false,
				array(__CLASS__, 'create_backup'),
				array(
					'include_files'    => Helper::bool_prop(__('Include files. Defaults to true.', 'onemcp-pro')),
					'include_database' => Helper::bool_prop(__('Include the database. Defaults to true.', 'onemcp-pro')),
					'note'             => Helper::string_prop(__('Optional note stored with the backup.', 'onemcp-pro'), 180),
					'location_id'      => Helper::int_prop(__('Remote storage location id. Omit to store the backup on this server.', 'onemcp-pro')),
				),
				array(),
				$write
			),
			Helper::ability(
				'stop-backuply-backup',
				__('Stop Backuply backup', 'onemcp-pro'),
				__('Asks Backuply to stop the backup or restore that is running.', 'onemcp-pro'),
				'onemcp-backups',
				false,
				array(__CLASS__, 'stop_backup'),
				array(),
				array(),
				$write
			),
			Helper::ability(
				'delete-backuply-backup',
				__('Delete Backuply backup', 'onemcp-pro'),
				__('Deletes one Backuply backup. Set confirm to DELETE.', 'onemcp-pro'),
				'onemcp-backups',
				false,
				array(__CLASS__, 'delete_backup'),
				array(
					'name'    => Helper::string_prop(__('Backup file name, ending in .tar.gz.', 'onemcp-pro'), 220),
					'confirm' => Helper::string_prop(__('Must be DELETE.', 'onemcp-pro'), 20),
				),
				array('name', 'confirm'),
				array('cap' => 'manage_options', 'destructive' => true)
			),
			Helper::ability(
				'restore-backuply-backup',
				__('Restore Backuply backup', 'onemcp-pro'),
				__('Restores a Backuply backup and replaces the current site. Set confirm to RESTORE.', 'onemcp-pro'),
				'onemcp-backups',
				false,
				array(__CLASS__, 'restore_backup'),
				array(
					'name'    => Helper::string_prop(__('Backup file name, ending in .tar.gz.', 'onemcp-pro'), 220),
					'confirm' => Helper::string_prop(__('Must be RESTORE.', 'onemcp-pro'), 20),
				),
				array('name', 'confirm'),
				array('cap' => 'manage_options', 'destructive' => true)
			),
		);
	}

	/**
	 * Run one Backuply chunk from cron. Backuply ends this request when the chunk finishes.
	 *
	 * @return void
	 */
	 static function run_backup() {
		if (function_exists('backuply_backup_execute')) {
			backuply_backup_execute();
		}
	}

	/**
	 * List backups.
	 *
	 * @param array<string,mixed> $input Input.
	 * @return array<string,mixed>|WP_Error
	 */
	 static function list_backups($input) {
		if (!function_exists('backuply_get_backups_info')) {
			return self::missing();
		}
		$limit = Helper::limit(isset($input['limit']) ? $input['limit'] : 20, 20, 50);
		$infos = (array) backuply_get_backups_info();
		$items = array();
		foreach ($infos as $info) {
			if (count($items) >= $limit) {
				break;
			}
			$row = self::public_backup($info);
			if ($row !== array()) {
				$items[] = $row;
			}
		}
		return array(
			'items' => $items,
			'total' => count($infos),
		);
	}

	/**
	 * Current job and storage locations.
	 *
	 * @param array<string,mixed> $input Input.
	 * @return array<string,mixed>|WP_Error
	 */
	 static function status($input) {
		unset($input);
		if (!defined('BACKUPLY_VERSION')) {
			return self::missing();
		}
		$stored = get_option('backuply_status', array());
		return array(
			'running'   => function_exists('backuply_active') ? (bool) backuply_active() : false,
			'stopped'   => (bool) get_option('backuply_backup_stopped'),
			'status'    => Helper::redact(is_array($stored) ? $stored : array()),
			'locations' => self::locations(),
		);
	}

	/**
	 * Queue a backup.
	 *
	 * @param array<string,mixed> $input Input.
	 * @return array<string,mixed>|WP_Error
	 */
	 static function create_backup($input) {
		if (!function_exists('backuply_create_log_file') || !function_exists('backuply_status_log') || !defined('BACKUPLY_VERSION')) {
			return self::missing();
		}
		if (function_exists('backuply_active') && backuply_active()) {
			return Helper::error('onemcp_busy', __('A Backuply backup is already running.', 'onemcp-pro'));
		}

		$files = !array_key_exists('include_files', $input) || Helper::bool($input['include_files']);
		$db    = !array_key_exists('include_database', $input) || Helper::bool($input['include_database']);
		if (!$files && !$db) {
			return Helper::error('onemcp_invalid', __('Choose files, the database, or both.', 'onemcp-pro'));
		}

		$status = array(
			'backup_dir'  => $files ? 1 : 0,
			'backup_db'   => $db ? 1 : 0,
			'backup_note' => Helper::text(isset($input['note']) ? $input['note'] : '', 180),
			'last_update' => time(),
		);
		if (!empty($input['location_id'])) {
			$location_id = (int) $input['location_id'];
			$locations   = get_option('backuply_remote_backup_locs', array());
			if (!is_array($locations) || !isset($locations[$location_id]) || !is_array($locations[$location_id])) {
				return Helper::error('onemcp_invalid', __('That Backuply storage location does not exist.', 'onemcp-pro'));
			}
			$status['backup_location'] = $location_id;
		}

		update_option('backuply_backup_stopped', false, false);
		update_option('backuply_status', $status, false);
		backuply_create_log_file();
		backuply_status_log('Initializing...', 'info', 13);
		backuply_status_log('Creating a job to start Backup', 'info', 17);

		wp_schedule_single_event(time(), 'onemcp_backuply_execute');
		if (function_exists('spawn_cron')) {
			spawn_cron();
		}

		return array(
			'started'           => true,
			'include_files'     => $files,
			'include_database'  => $db,
			'message'           => __('Backuply was asked to start the backup. Use get backup status to follow it.', 'onemcp-pro'),
		);
	}

	/**
	 * Ask the running job to stop.
	 *
	 * @param array<string,mixed> $input Input.
	 * @return array<string,mixed>|WP_Error
	 */
	 static function stop_backup($input) {
		unset($input);
		if (!defined('BACKUPLY_VERSION')) {
			return self::missing();
		}
		update_option('backuply_backup_stopped', true, false);
		if (function_exists('backuply_status_log')) {
			backuply_status_log('Stopping the Backup', 'info', -1);
		}
		return array('stopping' => true);
	}

	/**
	 * Delete one backup archive.
	 *
	 * @param array<string,mixed> $input Input.
	 * @return array<string,mixed>|WP_Error
	 */
	 static function delete_backup($input) {
		if (!function_exists('backuply_delete_backup')) {
			return self::missing();
		}
		if (self::confirm($input, 'DELETE') !== true) {
			return Helper::error('onemcp_confirm', __('Set confirm to DELETE to delete a backup.', 'onemcp-pro'));
		}
		$name = self::archive_name(isset($input['name']) ? $input['name'] : '');
		if (is_wp_error($name)) {
			return $name;
		}
		if (!self::info_exists($name)) {
			return Helper::error('onemcp_not_found', __('That Backuply backup was not found.', 'onemcp-pro'));
		}
		$deleted = backuply_delete_backup($name);
		if (!$deleted) {
			return Helper::error('onemcp_failed', __('Backuply could not delete that backup.', 'onemcp-pro'));
		}
		return array(
			'deleted' => true,
			'name'    => $name,
		);
	}

	/**
	 * Start a restore through Backuply's restore runner.
	 *
	 * @param array<string,mixed> $input Input.
	 * @return array<string,mixed>|WP_Error
	 */
	 static function restore_backup($input) {
		if (!function_exists('backuply_init_restore') || !function_exists('backuply_get_backup_info') || !function_exists('backuply_update_restore_key') || !defined('BACKUPLY_BACKUP_DIR')) {
			return self::missing();
		}
		if (self::confirm($input, 'RESTORE') !== true) {
			return Helper::error('onemcp_confirm', __('Set confirm to RESTORE. Restoring replaces the current site with that backup.', 'onemcp-pro'));
		}
		$name = self::archive_name(isset($input['name']) ? $input['name'] : '');
		if (is_wp_error($name)) {
			return $name;
		}
		if (!self::info_exists($name)) {
			return Helper::error('onemcp_not_found', __('That Backuply backup was not found.', 'onemcp-pro'));
		}

		$restore_dir = rtrim(BACKUPLY_BACKUP_DIR, '/\\') . '/restoration';
		if (!is_dir($restore_dir) && !wp_mkdir_p($restore_dir)) {
			return Helper::error('onemcp_failed', __('Backuply could not create its restore directory.', 'onemcp-pro'));
		}
		if (!wp_is_writable($restore_dir)) {
			return Helper::error('onemcp_failed', __('Backuply\'s restore directory is not writable.', 'onemcp-pro'));
		}

		$info_data = backuply_get_backup_info($name);
		if (!is_array($info_data) || $info_data === array()) {
			return Helper::error('onemcp_not_found', __('Backuply could not read that backup.', 'onemcp-pro'));
		}

		backuply_update_restore_key();
		if (function_exists('backuply_set_config_file') && !backuply_set_config_file()) {
			return Helper::error('onemcp_failed', __('Backuply could not prepare the restore.', 'onemcp-pro'));
		}

		$info = array(
			'restore_dir'        => !empty($info_data['backup_dir']),
			'restore_db'         => !empty($info_data['backup_db']),
			'backup_backup_dir'  => function_exists('backuply_cleanpath') ? backuply_cleanpath(BACKUPLY_BACKUP_DIR) : BACKUPLY_BACKUP_DIR,
			'softpath'           => wp_normalize_path(get_home_path()),
			'fname'              => $name,
			'size'               => isset($info_data['size']) ? (int) $info_data['size'] : 0,
			'backup_site_url'    => isset($info_data['backup_site_url']) ? esc_url_raw((string) $info_data['backup_site_url']) : '',
			'backup_site_path'   => isset($info_data['backup_site_path']) ? wp_normalize_path((string) $info_data['backup_site_path']) : '',
			'sess_key'           => wp_generate_password(32, false),
			'security'           => wp_create_nonce('backuply_nonce'),
			'loc_id'             => !empty($info_data['backup_location']) ? (int) $info_data['backup_location'] : '',
		);
		if (!empty($info['restore_db'])) {
			$info['dbexist'] = 'softsql.sql';
		}

		backuply_init_restore($info);
		return array(
			'started' => true,
			'name'    => $name,
			'message' => __('Restore started. The site will be replaced by this backup.', 'onemcp-pro'),
		);
	}

	/**
	 * Safe fields from a backup info record.
	 *
	 * @param mixed $info Backup info.
	 * @return array<string,mixed>
	 */
	private static function public_backup($info) {
		$info = (array) $info;
		$row  = array();
		$keep = array('name', 'size', 'backup_date', 'btime', 'backup_note', 'backup_dir', 'backup_db', 'backup_location', 'auto_backup', 'backup_site_url');
		foreach ($keep as $key) {
			if (isset($info[$key]) && (is_scalar($info[$key]) || $info[$key] === null)) {
				$row[$key] = $info[$key];
			}
		}
		return $row;
	}

	/**
	 * Storage locations without secrets.
	 *
	 * @return array<int,array<string,mixed>>
	 */
	private static function locations() {
		$stored = get_option('backuply_remote_backup_locs', array());
		$list   = array();
		if (!is_array($stored)) {
			return $list;
		}
		foreach ($stored as $id => $location) {
			if (!is_array($location)) {
				continue;
			}
			$list[] = array(
				'id'       => (int) $id,
				'name'     => isset($location['name']) ? Helper::text($location['name'], 80) : '',
				'protocol' => isset($location['protocol']) ? Helper::text($location['protocol'], 40) : '',
			);
		}
		return $list;
	}

	/**
	 * A backup archive name with no path.
	 *
	 * @param mixed $name Raw name.
	 * @return string|WP_Error
	 */
	private static function archive_name($name) {
		$name = basename(sanitize_file_name(is_scalar($name) ? (string) $name : ''));
		if ($name === '' || $name === '.' || $name === '..') {
			return Helper::error('onemcp_invalid', __('Provide the backup file name.', 'onemcp-pro'));
		}
		if (substr($name, -7) !== '.tar.gz') {
			$name .= '.tar.gz';
		}
		if (!preg_match('/^[A-Za-z0-9._-]+\.tar\.gz$/', $name)) {
			return Helper::error('onemcp_invalid', __('That backup file name is not valid.', 'onemcp-pro'));
		}
		return $name;
	}

	/**
	 * Whether Backuply has an info file for this archive.
	 *
	 * @param string $name Archive name.
	 * @return bool
	 */
	private static function info_exists($name) {
		if (!function_exists('backuply_glob') || !defined('BACKUPLY_BACKUP_DIR')) {
			return false;
		}
		$dir = backuply_glob('backups_info');
		if (!is_string($dir) || $dir === '') {
			return false;
		}
		$base = basename($name, '.tar.gz');
		$path = $dir . '/' . $base . '.php';
		return is_readable($path);
	}

	/**
	 * Require an exact confirmation word.
	 *
	 * @param array<string,mixed> $input Input.
	 * @param string              $word  Expected word.
	 * @return bool
	 */
	private static function confirm($input, $word) {
		$given = isset($input['confirm']) ? strtoupper(Helper::text($input['confirm'], 20)) : '';
		return $given === $word;
	}

	/**
	 * Backuply is not loaded.
	 *
	 * @return WP_Error
	 */
	private static function missing() {
		return Helper::error('onemcp_unavailable', __('Backuply is not available.', 'onemcp-pro'));
	}
}

add_action('onemcp_backuply_execute', array(__NAMESPACE__ . '\Backuply', 'run_backup'));
