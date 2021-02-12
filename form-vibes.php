<?php

/*
Plugin Name: Form Vibes - Database Manager for Forms
Plugin URI: https://formvibes.com
Description: Lead Management and Graphical Reports for Elementor Pro, Contact Form 7 & Caldera form submissions.
Author: WPVibes
Version: 1.3.4
Author URI: https://wpvibes.com/
Text Domain: wpv-fv
License: GPLv2
*/

if (!defined('ABSPATH')) exit; // Exit if accessed directly

class FORM_VIBES
{

	function __construct()
	{
		// Do nothing.
	}
	function initialize()
	{
		define('WPV_FV_VERSION', '1.3.4');
		define('WPV_FV_URL', plugins_url('/', __FILE__));
		define('WPV_FV_PATH', plugin_dir_path(__FILE__));
		define('WPV_FV_FILE', __FILE__);
		define('WPV_FV__PLUGIN_BASE', plugin_basename(__FILE__));
		define('WPV_FV_PLAN', 'FREE');
		define('WPV_FV_SCRIPT_SUFFIX', defined('SCRIPT_DEBUG') && SCRIPT_DEBUG ? '' : '.min');
		define('WPV_FV_PHP_VERSION_REQUIRED', '5.6');


		if (WPV_FV_PLAN === 'FREE') {
			if (!function_exists('wpv_fv')) {
				// Create a helper function for easy SDK access.
				function wpv_fv()
				{
					global $wpv_fv;
					if (!isset($wpv_fv)) {

						// Include Freemius SDK.
						require_once dirname(__FILE__) . '/freemius/start.php';
						$wpv_fv = fs_dynamic_init(array(
							'id'                  => '4666',
							'slug'                => 'form-vibes',
							'type'                => 'plugin',
							'public_key'          => 'pk_321780b7f1d1ee45009cf6da38431',
							'is_premium'          => false,
							'has_addons'          => false,
							'has_paid_plans'      => false,
							'menu'                => array(
								'slug'           => 'fv-leads',
								'first-path'     => 'admin.php?page=fv-db-settings',
								'account'        => false,
								'contact'        => false,
								'support'        => false,
							),
						));
					}

					return $wpv_fv;
				}

				// Init Freemius.
				wpv_fv();
				// Signal that SDK was initiated.
				do_action('wpv_fv_loaded');
			}
		}
	}
}

function fv()
{
	global $fv;

	// Instantiate only once.
	if (!isset($fv)) {
		$fv = new FORM_VIBES();
		$fv->initialize();
	}
	return $fv;
}

// Instantiate.
fv();



require_once WPV_FV_PATH . '/vendor/autoload.php';
require_once WPV_FV_PATH . '/inc/bootstrap.php';
