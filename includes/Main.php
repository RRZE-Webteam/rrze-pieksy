<?php

namespace RRZE\Pieksy;

defined('ABSPATH') || exit;

use RRZE\Pieksy\CPT\CPT;
use RRZE\Pieksy\Shortcodes\Shortcodes;
use RRZE\Pieksy\Printing\Printing;

use function RRZE\Pieksy\Config\getOptionName;


/**
 * [Main description]
 */
class Main
{
	protected $pluginFile;

	/**
	 * [__construct description]
	 */
	public function __construct($pluginFile)
	{
        $this->pluginFile = $pluginFile;
	}

	public function onLoaded()
	{
		// Settings
		$settings = new Settings($this->pluginFile);
		$settings->onLoaded();		

		// Posttypes 
		$cpt = new CPT;
		$cpt->onLoaded();

		// CMB2
		$metaboxes = new Metaboxes;
		$metaboxes->onLoaded();

		$shortcodes = new Shortcodes($this->pluginFile, $settings);
		$shortcodes->onLoaded();

		$schedule = new Schedule;
		$schedule->onLoaded();

		$occupancy = new Occupancy;
		$occupancy->onLoaded();

		$tools = new Tools;
		$tools->onLoaded();

        $virtualPage = new VirtualPage(__('Booking', 'rrze-pieksy'), 'pieksy-booking');
        $virtualPage->onLoaded();
        
		$actions = new Actions;
		$actions->onLoaded();

		add_action('admin_enqueue_scripts', [$this, 'adminEnqueueScripts']);
		add_action('wp_enqueue_scripts', [$this, 'wpEnqueueScripts']);

		add_action('rest_api_init', function () {
			//$api = new API;
			//$api->register_routes();
        });
        
		add_action('update_option_rrze_pieksy', [$this, 'resetSettings']);
		
		// RRZE Cache Plugin: Skip Cache
		add_filter('rrzecache_skip_cache', [$this, 'skipCache']);		
    }
	
	/**
	 * skipCache
	 * Check if cache is bypassed.
	 * @return boolean
	 */
	public function skipCache(): bool
	{
		global $post_type;
		if (in_array($post_type, array_keys(Capabilities::getCurrentCptArgs()))) {
			return true;
		}
		return false;		
	}

    public function resetSettings(){
        if (isset($_POST['rrze_pieksy']) && isset($_POST['rrze_pieksy']['reset_reset_settings']) && $_POST['rrze_pieksy']['reset_reset_settings'] == 'on'){
            $optionName = getOptionName();
            delete_option($optionName);
        }
    }

	public function adminEnqueueScripts()
	{
		global $post_type;

		wp_enqueue_style(
			'rrze-pieksy-admin-menu',
			plugins_url('assets/css/rrze-pieksy-admin-menu.css', plugin()->getBasename()),
			[],
			plugin()->getVersion()
		);

		if (!in_array($post_type, array_keys(Capabilities::getCurrentCptArgs()))) {
			return;
		}

		wp_enqueue_style(
			'rrze-pieksy-admin',
			plugins_url('assets/css/rrze-pieksy-admin.css', plugin()->getBasename()),
			[],
			plugin()->getVersion()
		);

		wp_enqueue_script(
			'rrze-pieksy-admin',
			plugins_url('assets/js/rrze-pieksy-admin.js', plugin()->getBasename()),
			['jquery'],
			plugin()->getVersion()
		);

		wp_localize_script('rrze-pieksy-admin', 'rrze_pieksy_admin', array(
			'dateformat' => get_option('date_format'),
			'text_cancel' => __('Do you want to cancel?', 'rrze-pieksy'),
			'text_cancelled' => _x('Cancelled', 'Booking', 'rrze-pieksy'),
			'text_confirmed' => _x('Confirmed', 'Booking', 'rrze-pieksy'),
			'ajaxurl' => admin_url('admin-ajax.php'),
			// Strings für CPT Booking Backend
			'alert_no_seat_date' => __('Please select a seat first.', 'rrze-pieksy')
		));

		if ($post_type == 'booking') {
			wp_dequeue_script('autosave');
		} elseif ($post_type == 'room') {
			wp_dequeue_script('autosave');
		} elseif ($post_type == 'seat') {
			wp_dequeue_script('autosave');
			wp_enqueue_script(
				'rrze-pieksy-seat',
				plugins_url('assets/js/rrze-pieksy-seat.js', plugin()->getBasename()),
				['jquery'],
				plugin()->getVersion()
			);

			wp_localize_script('rrze-pieksy-seat', 'rrze_pieksy_seat', ['button_label' => __('Create Seats', 'rrze-pieksy')]);
		}
	}

	public function wpEnqueueScripts()
	{
		wp_register_style(
			'rrze-pieksy-shortcode',
			plugins_url('assets/css/rrze-pieksy.css', plugin()->getBasename()),
			[],
			plugin()->getVersion()
		);
		wp_register_script(
			'rrze-pieksy-shortcode',
			plugins_url('assets/js/shortcode.js', plugin()->getBasename()),
			['jquery'],
			plugin()->getVersion()
		);
	}
}
