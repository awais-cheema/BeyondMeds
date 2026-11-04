<?php
/**
 * Duplicator backup archives.
 *
 * Package builds in current Duplicator releases run as several requests inside
 * Duplicator. This integration lists archives, saves a package through Duplicator's
 * own package class when that class is present, and deletes an archive.
 *
 * @package OneMCPPro
 */

namespace OneMCPPro;

use ONEMCP\Detector;
use ONEMCP\Helper;
use DUP_Package;
use DUP_PRO_Package;

if (!defined('ABSPATH')) {
	exit;
}

class Duplicator {

	/**
	 * Ability definitions. Empty unless Duplicator is active.
	 *
	 * @return array<int,array<string,mixed>>
	 */
	 static function definitions() {
		if (!\ONEMCP\Detector::is_active('duplicator')) {
			return array();
		}

		$cap = array('cap' => 'manage_options');
		return array(
			Helper::ability('list-duplicator-backups', __('List Duplicator backups', 'onemcp-pro'), __('Lists Duplicator packages stored on this site.', 'onemcp-pro'), 'onemcp-backups', true, array(__CLASS__, 'list_backups'), array('limit' => Helper::int_prop(__('How many packages to return. Maximum 50.', 'onemcp-pro')),), array(), array('cap' => 'manage_options')),
			
			Helper::ability(
				'create-duplicator-backup',
				__('Create Duplicator backup', 'onemcp-pro'),
				__('Saves a Duplicator package using the package class shipped with Duplicator. The archive build continues in Duplicator when this version builds across more than one request.', 'onemcp-pro'),
				'onemcp-backups',
				false,
				array(__CLASS__, 'create_backup'),
				array(
					'name'  => Helper::string_prop(__('Package name. A dated name is used when this is empty.', 'onemcp-pro'), 180),
					'notes' => Helper::string_prop(__('Optional package notes.', 'onemcp-pro'), 300),
				),
				array(),
				$cap
			),
			Helper::ability(
				'delete-duplicator-backup',
				__('Delete Duplicator backup', 'onemcp-pro'),
				__('Deletes one Duplicator package and its archive files. Set confirm to DELETE.', 'onemcp-pro'),
				'onemcp-backups',
				false,
				array(__CLASS__, 'delete_backup'),
				array(
					'id'      => Helper::int_prop(__('Package ID from the backup list.', 'onemcp-pro')),
					'confirm' => Helper::string_prop(__('Must be DELETE.', 'onemcp-pro'), 20),
				),
				array('id', 'confirm'),
				array('cap' => 'manage_options', 'destructive' => true)
			),
		);
	}

	/**
	 * List packages from Duplicator's table.
	 *
	 * @param array<string,mixed> $input Input.
	 * @return array<string,mixed>|WP_Error
	 */
	static function list_backups($input) {
		$table = self::table();
		if ($table === '') {
			return array(
				'items'   => array(),
				'total'   => 0,
				'message' => __('Duplicator is active, but its package table was not found.', 'onemcp-pro'),
			);
		}

		global $wpdb;
		$limit = Helper::limit(isset($input['limit']) ? $input['limit'] : 20, 20, 50);
		$wpdb->suppress_errors(true);
		$rows  = $wpdb->get_results('SELECT * FROM `' . esc_sql($table) . '` ORDER BY id DESC LIMIT ' . (int) $limit, ARRAY_A);
		$total = (int) $wpdb->get_var('SELECT COUNT(*) FROM `' . esc_sql($table) . '`');
		$wpdb->suppress_errors(false);
		if (!is_array($rows)) {
			return array(
				'items'   => array(),
				'total'   => 0,
				'message' => __('Duplicator is active, but its package table could not be read.', 'onemcp-pro'),
			);
		}
		$items = array();
		foreach ($rows as $row) {
			if (is_array($row)) {
				$items[] = self::public_row($row);
			}
		}
		return array(
			'items' => $items,
			'total' => $total,
		);
	}

	/**
	 * Save a package through Duplicator's class.
	 *
	 * @param array<string,mixed> $input Input.
	 * @return array<string,mixed>|WP_Error
	 */
	 static function create_backup($input) {
		$class = '';
		if (class_exists('DUP_Package')) {
			$class = 'DUP_Package';
		} elseif (class_exists('DUP_PRO_Package')) {
			$class = 'DUP_PRO_Package';
		}
		if ($class === '') {
			return Helper::error('onemcp_unavailable', __('This Duplicator version does not expose a package class, so a package cannot be created from here. Existing packages can still be listed and deleted.', 'onemcp-pro'));
		}

		$name = Helper::text(isset($input['name']) ? $input['name'] : '', 180);
		if ($name === '') {
			$name = 'MCP ' . gmdate('Y-m-d H:i');
		}

		$package = new $class();
		if (!is_object($package) || !method_exists($package, 'save')) {
			return Helper::error('onemcp_unavailable', __('Duplicator did not provide a save method for packages.', 'onemcp-pro'));
		}
		if (property_exists($package, 'Name')) {
			$package->Name = $name;
		}
		if (property_exists($package, 'name')) {
			$package->name = $name;
		}
		if (property_exists($package, 'Notes')) {
			$package->Notes = Helper::text(isset($input['notes']) ? $input['notes'] : '', 300);
		}

		$package->save();
		$id = 0;
		if (isset($package->ID)) {
			$id = (int) $package->ID;
		} elseif (isset($package->id)) {
			$id = (int) $package->id;
		}

		return array(
			'id'      => $id,
			'name'    => $name,
			'message' => __('The Duplicator package was saved. Open Duplicator to scan and build the archive if it does not finish on its own.', 'onemcp-pro'),
		);
	}

	/**
	 * Delete a package and archive files that belong to its hash.
	 *
	 * @param array<string,mixed> $input Input.
	 * @return array<string,mixed>|WP_Error
	 */
	static function delete_backup($input) {
		$confirm = isset($input['confirm']) ? strtoupper(Helper::text($input['confirm'], 20)) : '';
		if ($confirm !== 'DELETE') {
			return Helper::error('onemcp_confirm', __('Set confirm to DELETE to delete a Duplicator package.', 'onemcp-pro'));
		}

		$id = Helper::absint(isset($input['id']) ? $input['id'] : 0);
		if ($id < 1) {
			return Helper::error('onemcp_invalid', __('Provide a package ID.', 'onemcp-pro'));
		}

		$deleted = false;
		if (class_exists('DUP_Package') && method_exists('DUP_Package', 'get_by_id')) {
			$package = DUP_Package::get_by_id($id);
			if (is_object($package) && method_exists($package, 'delete')) {
				$package->delete();
				$deleted = true;
			}
		}
		if (!$deleted && class_exists('DUP_PRO_Package') && method_exists('DUP_PRO_Package', 'get_by_id')) {
			$package = DUP_PRO_Package::get_by_id($id);
			if (is_object($package) && method_exists($package, 'delete')) {
				$package->delete();
				$deleted = true;
			}
		}
		$row = self::row_by_id($id);
		if (!$deleted && $row === array()) {
			return Helper::error('onemcp_not_found', __('That Duplicator package was not found.', 'onemcp-pro'));
		}
		if (!$deleted) {
			self::delete_row($id);
			self::delete_archives(isset($row['hash']) ? (string) $row['hash'] : '');
			$deleted = true;
		}

		return array(
			'deleted' => true,
			'id'      => $id,
		);
	}

	/**
	 * Duplicator package table, when it exists.
	 *
	 * @return string
	 */
	private static function table() {
		global $wpdb;
		$candidates = array(
			$wpdb->prefix . 'duplicator_packages',
			$wpdb->prefix . 'duplicator_backups',
			$wpdb->prefix . 'duplicator_pro_packages',
		);
		foreach ($candidates as $table) {
			$found = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $wpdb->esc_like($table)));
			if ($found === $table) {
				return $table;
			}
		}
		return '';
	}

	/**
	 * One table row.
	 *
	 * @param int $id Package ID.
	 * @return array<string,mixed>
	 */
	private static function row_by_id($id) {
		global $wpdb;
		$table = self::table();
		if ($table === '') {
			return array();
		}
		$row = $wpdb->get_row($wpdb->prepare('SELECT * FROM `' . esc_sql($table) . '` WHERE id = %d', $id), ARRAY_A);
		return is_array($row) ? $row : array();
	}

	/**
	 * Delete the package row.
	 *
	 * @param int $id Package ID.
	 * @return void
	 */
	private static function delete_row($id) {
		global $wpdb;
		$table = self::table();
		if ($table !== '') {
			$wpdb->delete($table, array('id' => $id), array('%d'));
		}
	}

	/**
	 * Delete archive files whose names contain this package hash.
	 *
	 * @param string $hash Package hash.
	 * @return void
	 */
	private static function delete_archives($hash) {
		if (!preg_match('/^[A-Za-z0-9_-]{6,80}$/', $hash)) {
			return;
		}
		$dirs = array(
			WP_CONTENT_DIR . '/backups-dup-lite',
			WP_CONTENT_DIR . '/backups-dup-pro',
			WP_CONTENT_DIR . '/duplicator-backups',
		);
		foreach ($dirs as $dir) {
			$real = realpath($dir);
			if ($real === false || !is_dir($real)) {
				continue;
			}
			$matches = glob($real . '/*' . $hash . '*');
			if (!is_array($matches)) {
				continue;
			}
			foreach ($matches as $file) {
				$target = realpath($file);
				if ($target === false || strpos($target, $real . DIRECTORY_SEPARATOR) !== 0 || !is_file($target)) {
					continue;
				}
				wp_delete_file($target);
			}
		}
	}

	/**
	 * Fields that are safe to return.
	 *
	 * @param array<string,mixed> $row Database row.
	 * @return array<string,mixed>
	 */
	private static function public_row($row) {
		$status = isset($row['status']) ? (int) $row['status'] : 0;
		$label  = 'building';
		if ($status >= 100) {
			$label = 'complete';
		} elseif ($status < 0) {
			$label = 'error';
		}
		return array(
			'id'      => isset($row['id']) ? (int) $row['id'] : 0,
			'name'    => isset($row['name']) ? (string) $row['name'] : '',
			'hash'    => isset($row['hash']) ? (string) $row['hash'] : '',
			'status'  => $label,
			'code'    => $status,
			'created' => isset($row['created']) ? (string) $row['created'] : '',
		);
	}
}
