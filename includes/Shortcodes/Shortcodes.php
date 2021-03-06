<?php

namespace RRZE\Pieksy\Shortcodes;

defined('ABSPATH') || exit;

use RRZE\Pieksy\Shortcodes\Bookings;
use RRZE\Pieksy\Shortcodes\Availability;

use RRZE\Pieksy\Auth\{Auth, IdM};

use function RRZE\Pieksy\Config\getShortcodeSettings;
use function RRZE\Pieksy\plugin;

/**
 * Laden und definieren der Shortcodes
 */
class Shortcodes{
    protected $pluginFile;    
    private $settings = '';
    private $shortcodesettings = 'X';
    protected $idm;

    public function __construct($pluginFile, $settings){
        $this->pluginFile = $pluginFile;
        $this->settings = $settings;
        $this->shortcodesettings = getShortcodeSettings();
        $this->idm = new IdM;
    }

    public function onLoaded(){
        add_action('template_redirect', [$this, 'maybeAuthenticate']);
        add_filter('single_template', [$this, 'includeSingleTemplate']);

        $bookings_shortcode = new Bookings($this->pluginFile,  $this->settings);
        $bookings_shortcode->onLoaded();

        $availability_shortcode = new Availability($this->pluginFile,  $this->settings);
        $availability_shortcode->onLoaded();
    }

    public function gutenberg_init(){
        // Skip block registration if Gutenberg is not enabled/merged.
        if (!function_exists('register_block_type')) {
            return;
        }

        $js = '../assets/js/gutenberg.js';
        $editor_script = $this->settings['block']['blockname'] . '-blockJS';

        wp_register_script(
            $editor_script,
            plugins_url($js, __FILE__),
            array(
                'wp-blocks',
                'wp-i18n',
                'wp-element',
                'wp-components',
                'wp-editor'
            ),
            filemtime(dirname(__FILE__) . '/' . $js)
        );

        wp_localize_script($editor_script, 'blockname', $this->settings['block']['blockname']);

        register_block_type($this->settings['block']['blocktype'], array(
            'editor_script' => $editor_script,
            'render_callback' => [$this, 'shortcodeOutput'],
            'attributes' => $this->settings
        ));

        wp_localize_script($editor_script, $this->settings['block']['blockname'] . 'Config', $this->settings);
    }

    public function shortcodeAtts($atts, $tag, $shortcodesettings){
        // merge given attributes with default ones
        $atts_default = array();
        foreach ($shortcodesettings as $tagname => $settings) {
            foreach ($settings as $k => $v) {
                if ($k != 'block') {
                    $atts_default[$tagname][$k] = $v['default'];
                }
            }
        }
        return shortcode_atts($atts_default[$tag], $atts);
    }

    public function includeSingleTemplate($singleTemplate){
        global $post;
        if (isset($_GET['require-auth']) && wp_verify_nonce($_GET['require-auth'], 'require-auth')) {
            return sprintf('%sincludes/templates/auth/single-auth.php', plugin()->getDirectory());
        } elseif (isset($_REQUEST['nonce']) && wp_verify_nonce($_REQUEST['nonce'], 'pieksy-availability')) {
            return sprintf('%sincludes/templates/single-form.php', plugin()->getDirectory());
        } elseif ($post->post_type == 'room') {
            return dirname($this->pluginFile) . '/includes/templates/single-room.php';
        } elseif ($post->post_type == 'seat') {
            return dirname($this->pluginFile) . '/includes/templates/single-seat.php';
        }
        return $singleTemplate;
    }

    public function maybeAuthenticate() {
        global $post;
        $sso_loggedout = filter_input(INPUT_GET, 'sso_loggedout', FILTER_VALIDATE_INT);

        if (!is_a($post, '\WP_Post') || isset($_GET['require-auth']) || $sso_loggedout) {
            return;
        }
        
        if (isset($_REQUEST['nonce']) && wp_verify_nonce($_REQUEST['nonce'], 'rrze-pieksy-seat-check-inout')) {
            $isAuth = false;
            if ($this->idm->isAuthenticated()) {
                $isAuth = true;
            }
    
            if (!$isAuth) {
                Auth::tryLogIn();
            }
        }     
    }
}
