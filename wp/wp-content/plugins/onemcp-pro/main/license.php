<?php
/**
 * License screen for OneMCP Pro.
 *
 * @package OneMCPPro
 */

namespace OneMCPPro;

if (!defined('ABSPATH')) {
	exit;
}

class License {

	/**
	 * License form on the OneMCP License tab.
	 *
	 * @return void
	 */
	public static function template() {
		global $onemcp;

		if (!current_user_can('manage_options')) {
			return;
		}

		if (isset($_POST['save_onemcp_pro_license'])) {
			self::save();
		}

		if (isset($_POST['delete_onemcp_pro_license'])) {
			self::delete();
		}

		$license = (is_object($onemcp) && isset($onemcp->license) && is_array($onemcp->license)) ? $onemcp->license : array();
		$server  = isset($_SERVER['SERVER_ADDR']) ? sanitize_text_field(wp_unslash($_SERVER['SERVER_ADDR'])) : '';
		$key     = empty($license['license']) ? '' : $license['license'];

		echo '<div class="onemcp-license-tab">';
		echo '<div class="onemcp-license-card">';

		echo '<div class="onemcp-license-row">';
		echo '<div class="onemcp-license-label">' . esc_html__('OneMCP Version', 'onemcp-pro') . '</div>';
		echo '<div class="onemcp-license-value">' . (defined('ONEMCP_PRO_VERSION') ? esc_html(ONEMCP_PRO_VERSION) . ' (' . esc_html__('Pro Version', 'onemcp-pro') . ')' : 'N/A') . '</div>';
		echo '</div>';

		echo '<div class="onemcp-license-row">';
		echo '<div class="onemcp-license-label">' . esc_html__('OneMCP License', 'onemcp-pro') . '</div>';
		echo '<div class="onemcp-license-value">';
		echo '<form method="post" action="' . esc_url(admin_url('admin.php?page=onemcp&tab=license')) . '" class="onemcp-license-input-wrapper">';
		echo '<input type="hidden" name="onemcp_pro_license_nonce" value="' . esc_attr(wp_create_nonce('onemcp_pro_license')) . '" />';

		echo '<div class="onemcp-license-input-row">';
		if (defined('ONEMCP_PRO_VERSION') && empty($license['active'])) {
			echo '<span class="onemcp-license-badge">' . esc_html__('Unlicensed', 'onemcp-pro') . '</span>';
		}
		echo '<input type="text" name="onemcp_pro_license" class="onemcp-license-input" value="' . esc_attr($key) . '" placeholder="ONEMCP-11111-22222-33333-44444" autocomplete="off">';
		echo '</div>';

		echo '<div class="onemcp-license-buttons">';
		echo '<button name="save_onemcp_pro_license" class="onemcp-license-btn onemcp-license-btn-update" type="submit">' . esc_html__('Update License', 'onemcp-pro') . '</button>';
		if ($key !== '') {
			echo '<button name="delete_onemcp_pro_license" class="onemcp-license-btn onemcp-license-btn-delete" type="submit" onclick="return confirm(\'' . esc_js(__('Are you sure you want to delete the license? This will deactivate your license on this site.', 'onemcp-pro')) . '\')">' . esc_html__('Delete License', 'onemcp-pro') . '</button>';
		}
		echo '</div>';

		if (!empty($license) && !empty($license['expires'])) {
			$expires = $license['expires'];
			$shown   = substr($expires, 0, 4) . '/' . substr($expires, 4, 2) . '/' . substr($expires, 6);
			echo '<div class="onemcp-license-info">';
			echo '<span>' . esc_html__('License Status:', 'onemcp-pro') . ' <b>' . (empty($license['status_txt']) ? 'N.A.' : esc_html($license['status_txt'])) . '</b></span>';
			if ($expires <= gmdate('Ymd')) {
				echo '<span>' . esc_html__('License Expires:', 'onemcp-pro') . ' <b class="onemcp-text-danger">' . esc_html($shown) . '</b></span>';
			} elseif (empty($license['has_plid'])) {
				echo '<span>' . esc_html__('License Expires:', 'onemcp-pro') . ' <b>' . esc_html($shown) . '</b></span>';
			}
			echo '</div>';
		}

		echo '</form>';
		echo '</div>';
		echo '</div>';

		echo '<div class="onemcp-license-row">';
		echo '<div class="onemcp-license-label">' . esc_html__('URL', 'onemcp-pro') . '</div>';
		echo '<div class="onemcp-license-value mono">' . esc_html(site_url()) . '</div>';
		echo '</div>';

		echo '<div class="onemcp-license-row">';
		echo '<div class="onemcp-license-label">' . esc_html__('Path', 'onemcp-pro') . '</div>';
		echo '<div class="onemcp-license-value mono">' . esc_html(ABSPATH) . '</div>';
		echo '</div>';

		echo '<div class="onemcp-license-row">';
		echo '<div class="onemcp-license-label">' . esc_html__('Server\'s IP Address', 'onemcp-pro') . '</div>';
		echo '<div class="onemcp-license-value mono">' . esc_html($server) . '</div>';
		echo '</div>';

		echo '<div class="onemcp-license-row">';
		echo '<div class="onemcp-license-label">' . esc_html__('.htaccess is writable', 'onemcp-pro') . '</div>';
		echo '<div class="onemcp-license-value">';
		if (is_writable(ABSPATH . '.htaccess')) {
			echo '<span class="onemcp-text-success">' . esc_html__('Yes', 'onemcp-pro') . '</span>';
		} else {
			echo '<span class="onemcp-text-danger">' . esc_html__('No', 'onemcp-pro') . '</span>';
		}
		echo '</div>';
		echo '</div>';

		echo '</div>';
		echo '</div>';
	}

	/**
	 * Save a license key and ask the license server to confirm it.
	 *
	 * @return void
	 */
	public static function save() {
		global $onemcp, $lic_resp;

		if (!current_user_can('manage_options')) {
			return;
		}

		if (!isset($_POST['onemcp_pro_license_nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['onemcp_pro_license_nonce'])), 'onemcp_pro_license')) {
			echo '<div class="onemcp-license-notice onemcp-license-notice-error">' . esc_html__('Nonce verification failed', 'onemcp-pro') . '</div>';
			return;
		}

		$license = isset($_POST['onemcp_pro_license']) ? sanitize_text_field(wp_unslash($_POST['onemcp_pro_license'])) : '';
		if ($license === '') {
			echo '<div class="onemcp-license-notice onemcp-license-notice-error">' . esc_html__('Please enter a license key.', 'onemcp-pro') . '</div>';
			return;
		}

		onemcp_pro_load_license($license);

		if (is_wp_error($lic_resp) || 200 !== wp_remote_retrieve_response_code($lic_resp)) {
			if (is_wp_error($lic_resp)) {
				echo '<div class="onemcp-license-notice onemcp-license-notice-error">' . esc_html($lic_resp->get_error_message()) . '</div>';
				return;
			}
			echo '<div class="onemcp-license-notice onemcp-license-notice-error">' . esc_html__('An error occurred, please try again. Response code: ', 'onemcp-pro') . esc_html((string) wp_remote_retrieve_response_code($lic_resp)) . '</div>';
			return;
		}

		$tmp = json_decode(wp_remote_retrieve_body($lic_resp), true);
		if (empty($tmp)) {
			echo '<div class="onemcp-license-notice onemcp-license-notice-error">' . esc_html__('Invalid license key', 'onemcp-pro') . '</div>';
			return;
		}

		echo '<div class="onemcp-license-notice onemcp-license-notice-success">' . esc_html__('License activated successfully', 'onemcp-pro') . '</div>';
	}

	/**
	 * Remove the license stored for this site.
	 *
	 * @return void
	 */
	public static function delete() {
		global $onemcp;

		if (!current_user_can('manage_options')) {
			return;
		}

		if (!isset($_POST['onemcp_pro_license_nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['onemcp_pro_license_nonce'])), 'onemcp_pro_license')) {
			echo '<div class="onemcp-license-notice onemcp-license-notice-error">' . esc_html__('Nonce verification failed', 'onemcp-pro') . '</div>';
			return;
		}

		if (!isset($_POST['delete_onemcp_pro_license'])) {
			return;
		}

		delete_option('onemcp_license');
		if (is_object($onemcp)) {
			$onemcp->license = array();
		}

		echo '<div class="onemcp-license-notice onemcp-license-notice-success">' . esc_html__('License deleted successfully', 'onemcp-pro') . '</div>';
	}
}
