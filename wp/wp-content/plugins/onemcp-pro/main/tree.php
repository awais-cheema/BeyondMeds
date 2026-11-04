<?php
/**
 * Walk builder element trees.
 *
 * @package OneMCPPro
 */

namespace OneMCPPro;

use ONEMCP\Helper;

if (!defined('ABSPATH')) {
	exit;
}

class Tree {

	/**
	 * Keys that usually hold visible text.
	 *
	 * @return array<int,string>
	 */
	 static function text_keys() {
		return array( 'title', 'text', 'headerText', 'editor', 'html', 'content', 'heading', 'description', 'button_text', 'buttonText', 'caption', 'title_text',);
	}

	/**
	 * Flatten a builder tree into a short outline.
	 *
	 * @param mixed                    $nodes Nodes.
	 * @param array<string,mixed>      $args  id_keys, type_keys, child_keys.
	 * @param array<int,array<string,string>> $out Collected rows.
	 * @param string                   $path  Path prefix.
	 * @return array<int,array<string,string>>
	 */
	 static function summarize($nodes, $args, &$out = array(), $path = '') {
		if (!is_array($nodes) || count($out) >= 200) {
			return $out;
		}

		$index = 0;
		foreach ($nodes as $node) {
			if (!is_array($node) || count($out) >= 200) {
				$index++;
				continue;
			}

			$id   = self::first_string($node, $args['id_keys']);
			$type = self::first_string($node, $args['type_keys']);
			if ($type === '' && isset($node['data']['type']) && is_string($node['data']['type'])) {
				$type = $node['data']['type'];
			}

			$settings = array();
			if (isset($node['settings'])) {
				$settings = is_object($node['settings']) ? get_object_vars($node['settings']) : (array) $node['settings'];
				if ($type === '' && !empty($settings['type']) && is_string($settings['type'])) {
					$type = $settings['type'];
				}
			}

			$text = self::first_text($settings);
			if ($text === '' && isset($node['data']['properties'])) {
				$text = self::deep_text($node['data']['properties']);
			}

			$here = $path === '' ? (string) $index : $path . '.' . $index;
			if ($id !== '' || $type !== '' || $text !== '') {
				$row = array(
					'id'   => $id,
					'type' => $type,
					'path' => $here,
				);
				if ($text !== '') {
					$row['text'] = $text;
				}
				$out[] = $row;
			}

			foreach ($args['child_keys'] as $child_key) {
				if (!empty($node[$child_key]) && is_array($node[$child_key])) {
					self::summarize($node[$child_key], $args, $out, $here);
				}
			}
			$index++;
		}

		return $out;
	}

	/**
	 * Merge settings into the element with this id.
	 *
	 * @param array<mixed>        $nodes      Tree, passed by reference.
	 * @param string              $element_id Element id.
	 * @param array<string,mixed> $settings   Settings to merge.
	 * @param array<string,mixed> $args       id_keys and child_keys.
	 * @param bool                $found      Set when the element is updated.
	 * @return void
	 */
	 static function update(&$nodes, $element_id, $settings, $args, &$found = false) {
		if (!is_array($nodes) || $found) {
			return;
		}
		foreach ($nodes as &$node) {
			if (!is_array($node) || $found) {
				continue;
			}
			foreach ($args['id_keys'] as $id_key) {
				if (isset($node[$id_key]) && (string) $node[$id_key] === (string) $element_id) {
					self::merge_settings($node, $settings);
					$found = true;
					return;
				}
			}
			foreach ($args['child_keys'] as $child_key) {
				if (!empty($node[$child_key]) && is_array($node[$child_key])) {
					self::update($node[$child_key], $element_id, $settings, $args, $found);
					if ($found) {
						return;
					}
				}
			}
		}
	}

	/**
	 * Merge a settings array onto a node, keeping object settings as objects.
	 *
	 * @param array<string,mixed> $node     Element.
	 * @param array<string,mixed> $settings New settings.
	 * @return void
	 */
	public static function merge_settings(&$node, $settings) {
		$current    = isset($node['settings']) ? $node['settings'] : array();
		$was_object = is_object($current);
		$merged     = array_merge($was_object ? get_object_vars($current) : (array) $current, $settings);
		$node['settings'] = $was_object ? (object) $merged : $merged;
	}

	/**
	 * First non-empty string found under the given keys.
	 *
	 * @param array<string,mixed> $node Node.
	 * @param array<int,string>   $keys Candidate keys.
	 * @return string
	 */
	private static function first_string($node, $keys) {
		foreach ($keys as $key) {
			if (isset($node[$key]) && (is_string($node[$key]) || is_numeric($node[$key]))) {
				return (string) $node[$key];
			}
		}
		return '';
	}

	/**
	 * First readable string in a settings array.
	 *
	 * @param array<string,mixed> $settings Settings.
	 * @return string
	 */
	 static function first_text($settings) {
		foreach (self::text_keys() as $key) {
			if (!empty($settings[$key]) && is_string($settings[$key])) {
				$text = Helper::trim_text($settings[$key], 160);
				if ($text !== '') {
					return $text;
				}
			}
		}
		return '';
	}

	/**
	 * First readable string in a nested property bag.
	 *
	 * @param mixed $value Nested value.
	 * @param int   $depth Current depth.
	 * @return string
	 */
	 static function deep_text($value, $depth = 0) {
		if ($depth > 5 || !is_array($value)) {
			return '';
		}
		foreach (self::text_keys() as $key) {
			if (isset($value[$key]) && is_string($value[$key])) {
				$text = Helper::trim_text($value[$key], 160);
				if ($text !== '' && stripos($text, 'http') !== 0) {
					return $text;
				}
			}
		}
		foreach ($value as $child) {
			if (is_array($child)) {
				$found = self::deep_text($child, $depth + 1);
				if ($found !== '') {
					return $found;
				}
			}
		}
		return '';
	}
}
