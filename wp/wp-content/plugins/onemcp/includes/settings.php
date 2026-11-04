<?php
/**
 * Ability switches.
 *
 * Read abilities are on until an administrator turns them off.
 * WordPress core abilities are on until an administrator turns them off.
 * Page builder abilities that create pages, posts, theme templates, and UI are on the same way.
 * Other write abilities stay off until an administrator turns them on.
 *
 * @package OneMCP
 */

namespace ONEMCP;

if (!defined('ABSPATH')) {
	exit;
}

class Settings {

	/**
	 * Saved switches.
	 *
	 * Reads and default-on writes are stored as the ids an administrator turned off.
	 * Other writes are stored as the ids an administrator turned on.
	 *
	 * @return array{disabled_reads:array<int,string>,enabled_writes:array<int,string>,disabled_writes:array<int,string>}
	 */
	 static function all() {
		$saved = get_option(ONEMCP_OPTION, false);
		if ($saved === false) {
			$saved = get_option('onemcp_settings', array());
		}
		if (!is_array($saved)) {
			$saved = array();
		}
		return array(
			'disabled_reads'  => isset($saved['disabled_reads']) && is_array($saved['disabled_reads']) ? array_values($saved['disabled_reads']) : array(),
			'enabled_writes'  => isset($saved['enabled_writes']) && is_array($saved['enabled_writes']) ? array_values($saved['enabled_writes']) : array(),
			'disabled_writes' => isset($saved['disabled_writes']) && is_array($saved['disabled_writes']) ? array_values($saved['disabled_writes']) : array(),
		);
	}

	/**
	 * WordPress core starts enabled. On SitePad, SitePad Core and Pagelayer do too.
	 *
	 * @param array<string,mixed> $ability Catalog row.
	 * @return bool
	 */
	 static function defaults_on($ability) {
		if (!empty($ability['default_on'])) {
			return true;
		}
		$category = isset($ability['category']) ? (string) $ability['category'] : '';
		if ($category === 'onemcp-wordpress') {
			return true;
		}
		return Detector::is_sitepad() && strpos($category, 'onemcp-pagelayer') === 0;
	}

	/**
	 * Whether one catalog row should be registered for MCP.
	 *
	 * @param array<string,mixed> $ability Catalog row.
	 * @return bool
	 */
	 static function ability_enabled($ability) {
		$id = isset($ability['id']) ? (string) $ability['id'] : '';
		return self::is_enabled($id, !empty($ability['readonly']), self::defaults_on($ability));
	}

	/**
	 * Whether an ability should be registered for MCP.
	 *
	 * @param string $id         Ability id, such as onemcp/get-site-info.
	 * @param bool   $readonly   True for a read ability.
	 * @param bool   $default_on True when this write ability starts enabled.
	 * @return bool
	 */
	 static function is_enabled($id, $readonly, $default_on = false) {
		$settings = self::all();
		if ($readonly) {
			return !in_array($id, $settings['disabled_reads'], true);
		}
		if ($default_on) {
			return !in_array($id, $settings['disabled_writes'], true);
		}
		return in_array($id, $settings['enabled_writes'], true);
	}

	/**
	 * Turn one ability on or off.
	 *
	 * @param string $id       Ability id.
	 * @param bool   $enabled  Desired state.
	 * @param bool   $readonly True for a read ability.
	 * @return bool
	 */
	 static function set_enabled($id, $enabled, $readonly) {
		if (!self::is_known($id)) {
			return false;
		}
		$settings   = self::all();
		$on         = (bool) $enabled;
		$default_on = !$readonly && self::id_defaults_on($id);
		if ($readonly) {
			$list = array_values(array_diff($settings['disabled_reads'], array($id)));
			if (!$on) {
				$list[] = $id;
			}
			$settings['disabled_reads'] = array_values(array_unique($list));
		} elseif ($default_on) {
			$list = array_values(array_diff($settings['disabled_writes'], array($id)));
			if (!$on) {
				$list[] = $id;
			}
			$settings['disabled_writes'] = array_values(array_unique($list));
			$settings['enabled_writes']  = array_values(array_diff($settings['enabled_writes'], array($id)));
		} else {
			$list = array_values(array_diff($settings['enabled_writes'], array($id)));
			if ($on) {
				$list[] = $id;
			}
			$settings['enabled_writes'] = array_values(array_unique($list));
		}
		update_option(ONEMCP_OPTION, $settings, false);
		return true;
	}

	/**
	 * Whether this ability id starts enabled.
	 *
	 * @param string $id Ability id.
	 * @return bool
	 */
	private static function id_defaults_on($id) {
		foreach (\ONEMCP\Registry::catalog() as $ability) {
			if ($ability['id'] === $id) {
				return self::defaults_on($ability);
			}
		}
		return false;
	}

	/**
	 * Enable every read, enable every write, or disable every write.
	 *
	 * @param string $mode enable-reads, enable-writes, or disable-writes.
	 * @return int Number of abilities changed.
	 */
	 static function bulk($mode) {
		$catalog  = \ONEMCP\Registry::catalog();
		$settings = self::all();
		$count    = 0;
		if ($mode === 'enable-reads') {
			$settings['disabled_reads'] = array();
			$count = count($catalog);
		} elseif ($mode === 'disable-writes') {
			$settings['enabled_writes'] = array();
			$disabled = array();
			foreach ($catalog as $ability) {
				if (empty($ability['readonly']) && self::defaults_on($ability)) {
					$disabled[] = $ability['id'];
				}
			}
			$settings['disabled_writes'] = $disabled;
			$count = count($disabled);
		} elseif ($mode === 'enable-writes') {
			$ids = array();
			foreach ($catalog as $ability) {
				if (empty($ability['readonly'])) {
					$ids[] = $ability['id'];
				}
			}
			$settings['disabled_writes'] = array();
			$settings['enabled_writes'] = $ids;
			$count = count($ids);
		} else {
			return 0;
		}
		update_option(ONEMCP_OPTION, $settings, false);
		return $count;
	}

	/**
	 * Whether the catalog currently knows this ability id.
	 *
	 * @param string $id Ability id.
	 * @return bool
	 */
	 static function is_known($id) {
		if (!is_string($id) || !preg_match('/^onemcp\/[a-z0-9-]{2,120}$/', $id)) {
			return false;
		}
		foreach (\ONEMCP\Registry::catalog() as $ability) {
			if ($ability['id'] === $id) {
				return true;
			}
		}
		return false;
	}
}
