<?php

namespace RRZE\Pieksy\Shortcodes;

use RRZE\Pieksy\Email;
use RRZE\Pieksy\Helper;
use RRZE\Pieksy\Functions;
use RRZE\Pieksy\Template;
use RRZE\Pieksy\TransientData;

use RRZE\Pieksy\Auth\{Auth, IdM};

use function RRZE\Pieksy\Config\defaultOptions;
use function RRZE\Pieksy\Config\getShortcodeSettings;

defined('ABSPATH') || exit;

/**
 * Define Shortcode Bookings
 */
class Bookings extends Shortcodes {
    protected $pluginFile;
    protected $template;
    private $settings = '';
    private $shortcodesettings = '';
    private $options = '';

    protected $email;
    protected $idm;
    protected $sso = false;
    protected $ssoRequired;
    protected $nonce;

    public function __construct($pluginFile, $settings) {
        parent::__construct($pluginFile, $settings);
        $this->settings = $settings;
        $this->shortcodesettings = getShortcodeSettings();
        $this->options = (object) $this->settings->getOptions();
        $this->email = new Email;
        $this->idm = new IdM;
        $this->template = new Template;
    }

    public function onLoaded() {
        add_action('template_redirect', [$this, 'maybeAuthenticate']);
        add_action('template_redirect', [$this, 'bookingSubmitted']);

        add_action( 'wp_ajax_UpdateCalendar', [$this, 'ajaxUpdateCalendar'] );
        add_action( 'wp_ajax_nopriv_UpdateCalendar', [$this, 'ajaxUpdateCalendar'] );
        add_action( 'wp_ajax_UpdateForm', [$this, 'ajaxUpdateForm'] );
        add_action( 'wp_ajax_nopriv_UpdateForm', [$this, 'ajaxUpdateForm'] );
        add_action( 'wp_ajax_ShowItemInfo', [$this, 'ajaxShowItemInfo'] );
        add_action( 'wp_ajax_nopriv_ShowItemInfo', [$this, 'ajaxShowItemInfo'] );     
        add_shortcode('pieksy-booking', [$this, 'shortcodeBooking'], 10, 2);
    }


    public function maybeAuthenticate(){
        global $post;

        $sso_loggedout = filter_input(INPUT_GET, 'sso_loggedout', FILTER_VALIDATE_INT);

        if (!is_a($post, '\WP_Post') || isset($_GET['require-auth']) || $sso_loggedout) {
                return;
        }
        $this->nonce = (isset($_REQUEST['nonce']) && wp_verify_nonce($_REQUEST['nonce'], 'pieksy-availability')) ? $_REQUEST['nonce'] : '';

        if (isset($_GET['room_id'])) {            
            $roomId = absint($_GET['room_id']);
            if ($this->nonce){
                $this->ssoRequired = Functions::getBoolValueFromAtt(get_post_meta($roomId, 'rrze-pieksy-room-sso-required', true));
            }
        } else {
            $roomId = $this->getShortcodeAtt($post->post_content, 'pieksy-booking', 'room');

            $this->ssoRequired = Functions::getBoolValueFromAtt(get_post_meta($roomId, 'rrze-pieksy-room-sso-required', true));
        }

        if ($this->ssoRequired && $this->idm->isAuthenticated()) {
            $this->sso = true;
        }

        if (($this->ssoRequired) && !$this->sso) {
            Auth::tryLogIn();
        }
    }

    public function shortcodeBooking($atts, $content = '', $tag) {
        global $post;
        $postID = $post->ID;

        if (isset($_GET['transient-data']) && isset($_GET['transient-data-nonce']) && wp_verify_nonce($_GET['transient-data-nonce'], 'transient-data-' . $_GET['transient-data'])) {
            $transient = $_GET['transient-data'];
            $transientData = new TransientData($transient);
            if (empty($fieldErrors = $transientData->getData())) {
                $redirectUrl = add_query_arg(
                    [
                        'nonce' => $this->nonce
                    ],
                    get_permalink()
                );
                wp_redirect($redirectUrl);
                exit;
            }           
        }

        wp_enqueue_style('rrze-pieksy-shortcode');

        if ($output = $this->ssoAuthenticationError()) {
            return $output;
        }
        if ($output = $this->postDataError()) {
            return $output;
        }        
        if ($output = $this->saveError()) {
            return $output;
        }
        if ($output = $this->multipleBookingError()) {
            return $output;
        }                  
        if ($output = $this->seatUnavailableError()) {
            return $output;
        }        
        if ($output = $this->timeslotUnavailableError()) {
            return $output;
        }
        if ($output = $this->bookedNotice()) {
            return $output;
        }

        $data = $this->idm->getCustomerData();
        if (!empty($data['customer_email']) && !empty($postID) && (Functions::isNotUniqueBooking($postID, $data['customer_email']) == FALSE)){
            $data['multiple_booking_error'] = true;        
            $data['multiple_booking'] = __('Booking', 'rrze-pieksy');
            $data['message'] = __('Error: you already have a reservation.', 'rrze-pieksy');
    
            return $this->template->getContent('shortcode/booking-error', $data);
        }

        $shortcode_atts = parent::shortcodeAtts($atts, $tag, $this->shortcodesettings);
        $input_room = sanitize_title($shortcode_atts[ 'room' ]);
        if (is_numeric($input_room)) {
            $post_room = get_post($input_room);
            if ( ! $post_room) {
                return __('Room specified in shortcode does not exist.', 'rrze-pieksy');
            }
        }

        if ($output = $this->selectRoom($input_room)) {
            return $output;
        }

        wp_enqueue_script('rrze-pieksy-shortcode');
        wp_localize_script('rrze-pieksy-shortcode', 'pieksy_ajax', [
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce( 'pieksy-ajax-nonce' ),
        ]);
                
        $output = '';

        $roomID = isset($_GET[ 'room_id' ]) ? absint($_GET[ 'room_id' ]) : $input_room;
        $roomMeta = get_post_meta($roomID);
        $days = $shortcode_atts[ 'days' ] != '' ? (int)$shortcode_atts[ 'days' ] : $roomMeta['rrze-pieksy-room-days-in-advance'][0];
        $comment = (isset($roomMeta['rrze-pieksy-room-notes-check']) && $roomMeta['rrze-pieksy-room-notes-check'][0] == 'on');
    
        $bookingMode = get_post_meta($roomID, 'rrze-pieksy-room-bookingmode', true);
        if ($bookingMode == 'check-only' && !$this->nonce) {
            
            $alert = '<div class="alert alert-info" role="alert">';
            $alert .= '<p><strong>'.__('Checkin in room','rrze-pieksy').'</strong><br>';
            $alert .= __('Reservations disabled. Please checkin at the seats in the room.','rrze-pieksy').'</p>';
            $alert .= '</div>';
            
            
            if (shortcode_exists('collapsibles')) {
            $scheduleinfo = '';
            // Schedule
            $scheduleData = Functions::getRoomSchedule($roomID);
            $schedule = '';
            $weekdays = Functions::daysOfWeekAry(1);
            if (!empty($scheduleData)) {
                $schedule .= '<table class="pieksy-schedule">';
                $schedule .= '<tr>'
                . '<th>'. __('Weekday', 'rrze-pieksy') . '</th>'
                . '<th>'. __('Time slots', 'rrze-pieksy') . '</th>';
                $schedule .= '</tr>';
                foreach ($scheduleData as $weekday => $dailySlots) {
                $schedule .= '<tr>'
                    .'<td>' . $weekdays[$weekday] . '</td>'
                    . '<td>';
                $ts = [];
                foreach ($dailySlots as $start => $end) {
                    $ts[] = $start . ' - ' . $end;
                }
                $schedule .= implode('<br />', $ts);
                $schedule .= '</td>'
                    . '</tr>';
                }
                $schedule .= "</table>";
            }
            if (!empty($schedule)) {
                $scheduleinfo .= '[collapsibles expand-all-link="true"]'
                . '[collapse title="'.__('Schedule','rrze-pieksy').'" name="schedule" load="open"]'
                . $schedule
                . '[/collapse]';
            
            
            }
            
            $scheduleinfo .= '[collapse title="'.__('Current Room Occupancy', 'rrze-pieksy').'" name="occupancy"]'
                . Functions::getOccupancyByRoomIdNextHTML($roomID)
                . '[/collapse]';
            
            $scheduleinfo .= '[/collapsibles]';
            $schedulehtml = do_shortcode($scheduleinfo);
            $alert  .= $schedulehtml;
            } else {
            
            $scheduleinfo = '<h2>' . __('Schedule', 'rrze-pieksy') . '</h2>'
                    //. $schedule
                    //. '<h3>' . __('Room occupancy for today', 'rrze-pieksy') . '</h3>';
                    . Functions::getOccupancyByRoomIdNextHTML($postID);
            $alert  .= $scheduleinfo;
            
            }
            return $alert;
        }
	
        $get_date = isset($_GET[ 'bookingdate' ]) ? sanitize_text_field($_GET[ 'bookingdate' ]) : date('Y-m-d', current_time('timestamp'));
        $get_time = isset($_GET[ 'timeslot' ]) ? sanitize_text_field($_GET[ 'timeslot' ]) : false;
        $get_seat = isset($_GET[ 'seat_id' ]) ? absint($_GET[ 'seat_id' ]) : false;
        $get_instant = (isset($_GET[ 'instant' ]) && $_GET[ 'instant' ] == '1');

        $availability = Functions::getRoomAvailability(
            $roomID,
            $get_date,
            date('Y-m-d', strtotime($get_date . ' +1 days')),
            false
        );

        $output .= '<div class="rrze-pieksy">';
        $output .= '<form action="' . get_permalink() . '" method="post" id="pieksy_by_room" class="mode-'.$bookingMode.'">'
                    . '<div id="loading"><i class="fa fa-refresh fa-spin fa-4x"></i></div>';
// TODO:	
//	$output .= '<fieldset>';  
	// FIELDSET muss noch rein oder unten <legend> raus. Wenn fieldset drin ist, ist die Ausstattungsanzeige des Sitzplatzes allerdings broken.
	// Wahrscheinlich Problem mit dem JS?
        if ($get_instant) {
            $output .= '<div><input type="hidden" value="1" id="pieksy_instant" name="pieksy_instant"></div>';
        }

        if (isset($_GET['nonce']) && wp_verify_nonce($_GET['nonce'], 'pieksy-availability')) {
            $output .= '<div><input type="hidden" value="' . $_GET['nonce'] . '" id="pieksy_availability" name="nonce"></div>';
        }
        $output .= '<p><input type="hidden" value="' . $roomID . '" id="pieksy_room" name="pieksy_room">'
                    . wp_nonce_field('post_nonce', 'rrze_pieksy_post_nonce_field')
                    . __('Book a seat at', 'rrze-pieksy') . ': <strong>' . get_the_title($roomID) . '</strong>'
                    . '</p>';

        if ($bookingMode == 'check-only') {
            // replace Calendar, time and place inputs by get parameters to avoid reservations for future timeslots
            $timeslots = isset($availability[$get_date]) ? $availability[$get_date] : [];
            $timeslot = '';
            foreach($timeslots as $key => $data) {
                if (substr($key, 0, strlen($get_time) ) == $get_time) {
                    $timeslot = $key;
                } else {
                    $timeslot = __('Timeslot not available.', 'rrze-pieksy');
                }
            }
            $output .= '<input type="hidden" name="pieksy_date" value="' . $get_date . '">'
                . '<input type="hidden" name="pieksy_time" value="' . $get_time . '">'
                . '<input type="hidden" name="pieksy_seat" value="' . $get_seat . '">'
                . '<div class="form-group">'
                . '<h2>' . __( 'Your booking','rrze-pieksy') . '</h2>'
                . '<p>' . __('Date', 'rrze-pieksy') . ': <strong>' . date_i18n(get_option('date_format'), strtotime($get_date)).  '</strong></p>'
                . '<p>' . __('Time', 'rrze-pieksy') . ': <strong>' . $timeslot . '</strong></p>'
                . '<p>' . __('Room', 'rrze-pieksy') . ': <strong>' . get_the_title($roomID) . '</strong></p>'
                . '<p>' . __('Seat', 'rrze-pieksy') . ': <strong>' . get_the_title($get_seat) . '</strong></p>'
                . '</div>';
        } else {
            $output .= '<div class="pieksy-datetime-container form-group clearfix">';
            $output .= '<legend>' . __( 'Select date and time','rrze-pieksy') . '</legend>';
            $output .=  '<div class="pieksy-date-container">';
            $get_timestamp = strtotime($get_date);
            $month          = date_i18n('n', $get_timestamp);
            $year           = date_i18n('Y', $get_timestamp);
            $start          = date_i18n('Y-m-d', current_time('timestamp'));
            $end            = date_i18n('Y-m-d', strtotime($start . ' +' . $days . ' days'));
            $output .= $this->buildCalendar($month, $year, $start, $end, $roomID, $get_date);
            $output .= '</div>'; //.pieksy-date-container

            $output .= '<div class="pieksy-time-container">'
                . '<h4>' . __('Available time slots:', 'rrze-pieksy') . '</h4>';
            if ($get_date) {
                $output .= $this->buildTimeslotSelect($roomID, $get_date, $get_time, $availability);
            } else {
                $output .= '<div class="pieksy-time-select error">' . __('Please select a date.', 'rrze-pieksy') . '</div>';
            }
            $output .= '</div>'; //.pieksy-time-container

            $output .= '</div>'; //.pieksy-datetime-container

            if ($bookingMode != 'consultation') {
                $output .= '<div class="pieksy-seat-container">';
                if ($get_date && $get_time) {
                    $output .= $this->buildSeatSelect($roomID, $get_date, $get_time, $get_seat, $availability);
                } else {
                    $output .= '<div class="pieksy-time-select error">' . __('Please select a date and a time slot.', 'rrze-pieksy') . '</div>';
                }
                $output .= '</div>'; //.pieksy-seat-container
            }
            //	$output .= '</fieldset>';
        }

	    $output .= '<fieldset>';  
        $output .= '<legend>' . __('Your data', 'rrze-pieksy') . ' <span class="notice-required">('. __('Required','rrze-pieksy'). ')</span></legend>';
        if ($this->sso) {
            // $data = $this->idm->getCustomerData();

            $output .= '<input type="hidden" value="' . $data['customer_lastname'] . '" id="pieksy_lastname" name="pieksy_lastname">';
            $output .= '<input type="hidden" value="' . $data['customer_firstname'] . '" id="pieksy_firstname" name="pieksy_firstname">';
            $output .= '<input type="hidden" value="' . $data['customer_email'] . '" id="pieksy_email" name="pieksy_email">';

            $output .= '<div class="form-group">'
                . '<p>' . __('Last name', 'rrze-pieksy') . ': <strong>' . $data['customer_lastname'] . '</strong></p>'
                . '<p>' . __('First name', 'rrze-pieksy') . ': <strong>' . $data['customer_firstname'] . '</strong></p>'
                . '<p>' . __('Email', 'rrze-pieksy') . ': <strong>' . $data['customer_email'] . '</strong></p>'
                . '</div>';
        }

        // if (!$this->sso) {
        //     $error = isset($fieldErrors['pieksy_lastname']) ? ' error' : '';
        //     $value = isset($fieldErrors['pieksy_lastname']['value']) ? $fieldErrors['pieksy_lastname']['value'] : '';
        //     $message = isset($fieldErrors['pieksy_lastname']['message']) ? $fieldErrors['pieksy_lastname']['message'] : '';    
        //     $output .= '<div class="form-group' . $error . '"><label for="pieksy_lastname">'
        //         . __('Last name', 'rrze-pieksy') . '</label>'
        //         . '<input type="text" name="pieksy_lastname" value="' . $value . '" id="pieksy_lastname" required aria-required="true">'
        //         . '<div class="error-message">' . $message . '</div>'
        //         . '</div>';

        //     $error = isset($fieldErrors['pieksy_firstname']) ? ' error' : '';
        //     $value = isset($fieldErrors['pieksy_firstname']['value']) ? $fieldErrors['pieksy_firstname']['value'] : '';
        //     $message = isset($fieldErrors['pieksy_firstname']['message']) ? $fieldErrors['pieksy_firstname']['message'] : '';    
        //     $output .= '<div class="form-group' . $error . '"><label for="pieksy_firstname">'
        //         . __('First name', 'rrze-pieksy') . '</label>'
        //         . '<input type="text" name="pieksy_firstname" value="' . $value . '" id="pieksy_firstname" required aria-required="true">'
        //         . '<div class="error-message">' . $message . '</div>'
        //         . '</div>';               
        // }

        // if (!$this->sso) {
        //     $error = isset($fieldErrors['pieksy_email']) ? ' error' : '';
        //     $value = isset($fieldErrors['pieksy_email']['value']) ? $fieldErrors['pieksy_email']['value'] : '';
        //     $message = isset($fieldErrors['pieksy_email']['message']) ? $fieldErrors['pieksy_email']['message'] : '';    
        //     $output .= '<div class="form-group' . $error . '"><label for="pieksy_email">'
        //         . __('Email', 'rrze-pieksy') . '</label>'
        //         . '<input type="email" name="pieksy_email" value="' . $value . '" '
        //         . 'pattern="^([^\x00-\x20\x22\x28\x29\x2c\x2e\x3a-\x3c\x3e\x40\x5b-\x5d\x7f-\xff]+|\x22([^\x0d\x22\x5c\x80-\xff]|\x5c[\x00-\x7f])*\x22)(\x2e([^\x00-\x20\x22\x28\x29\x2c\x2e\x3a-\x3c\x3e\x40\x5b-\x5d\x7f-\xff]+|\x22([^\x0d\x22\x5c\x80-\xff]|\x5c[\x00-\x7f])*\x22))*\x40([^\x00-\x20\x22\x28\x29\x2c\x2e\x3a-\x3c\x3e\x40\x5b-\x5d\x7f-\xff]+|\x5b([^\x0d\x5b-\x5d\x80-\xff]|\x5c[\x00-\x7f])*\x5d)(\x2e([^\x00-\x20\x22\x28\x29\x2c\x2e\x3a-\x3c\x3e\x40\x5b-\x5d\x7f-\xff]+|\x5b([^\x0d\x5b-\x5d\x80-\xff]|\x5c[\x00-\x7f])*\x5d))*(\.\w{2,})+$" '
        //         . 'id="pieksy_email" required aria-required="true">'
        //         . '<div class="error-message">' . $message . '</div>'
        //         . '</div>';            
        // }

        $error = isset($fieldErrors['pieksy_phone']) ? ' error' : '';
        $value = isset($fieldErrors['pieksy_phone']['value']) ? $fieldErrors['pieksy_phone']['value'] : '';
        $message = isset($fieldErrors['pieksy_phone']['message']) ? $fieldErrors['pieksy_phone']['message'] : '';
        $output .= '<div class="form-group' . $error . '"><label for="pieksy_phone">'
            . __('Phone Number', 'rrze-pieksy') . '</label>'
            . '<input type="text" name="pieksy_phone" value="' . $value . '" pattern="^([+])?(\d{1,3})?\s?(\(\d{3,5}\)|\d{3,5})?\s?(\d{1,3}\s?|\d{1,3}[-])?(\d{3,8})$" id="pieksy_phone">'
            . '<div class="error-message">' . $message . '</div>';
        
        $defaults = defaultOptions();
        if ($comment) {
            $label = $roomMeta['rrze-pieksy-room-notes-label'][0];
            if ($label == '') {
                $label = $defaults['room-notes-label'];
            }
            $output .= '<div class="form-group">'
                . '<label for="pieksy_comment">' . $label . '</label>'
                . '<textarea name="pieksy_comment" id="pieksy_comment"></textarea>';
        }
        $output .= '<div class="form-group">'
        . '<input type="checkbox" value="1" id="pieksy_dsgvo" name="pieksy_dsgvo" required> '
        . '<label for="pieksy_dsgvo">' . $defaults['dsgvo-declaration'] . '</label>'
        . '</div>';
        $output .= '</fieldset>';  
        $output .= '<button type="submit" class="btn btn-primary">' . __('Submit booking', 'rrze-pieksy') . '</button>
            </form>
        </div>';

        return $output;
    }


    protected function ssoAuthenticationError(){
        if (!isset($_GET['booking']) || !wp_verify_nonce($_GET['booking'], 'sso_authentication')) {
            return '';
        }

        $data = [];
        $data['sso_authentication_error'] = true;
        $data['sso_authentication'] = __('SSO error', 'rrze-pieksy');
        $data['message'] = __("Error retrieving your data from SSO. Please try again or contact the website administrator.", 'rrze-pieksy');

        return $this->template->getContent('shortcode/booking-error', $data);
    }

    protected function postDataError(){
        if (!isset($_GET['booking']) || !wp_verify_nonce($_GET['booking'], 'post_data')) {
            return '';
        }

        $data = [];
        $data['booking_data_error'] = true;
        $data['booking_data'] =  __('Booking data', 'rrze-pieksy');
        $data['message'] =  __('Invalid or missing booking data.', 'rrze-pieksy');

        return $this->template->getContent('shortcode/booking-error', $data);
    }

    protected function saveError(){
        if (!isset($_GET['booking']) || !wp_verify_nonce($_GET['booking'], 'save_error-' . $_GET['nonce'])) {
            return '';
        }

        $data = [];
        $data['booking_save_error'] = true;
        $data['booking_save'] =  __('Save booking', 'rrze-pieksy');
        $data['message'] =  __('Error saving the booking.', 'rrze-pieksy');

        return $this->template->getContent('shortcode/booking-error', $data);
    }

    protected function multipleBookingError(){
        if (!isset($_GET['booking']) || !wp_verify_nonce($_GET['booking'], 'multiple_booking-' . $_GET['nonce'])) {
            return '';
        }

        $data = [];
        $data['multiple_booking_error'] = true;        
        // $data['multiple_booking'] = __('Multiple Booking', 'rrze-pieksy');
        // $data['message'] = __('<strong>You have already booked a seat for the specified time slot.</strong><br>If you want to change your booking, please cancel the existing booking first. You will find the link to do so in your confirmation email.', 'rrze-pieksy');
        $data['multiple_booking'] = __('Save booking', 'rrze-pieksy');
        $data['message'] = __('Error saving the booking.', 'rrze-pieksy');

        return $this->template->getContent('shortcode/booking-error', $data);
    }

    protected function seatUnavailableError(){
        if (!isset($_GET['url']) || !isset($_GET['booking']) || !wp_verify_nonce($_GET['booking'], 'seat_unavailable-' . $_GET['url'])) {
            return '';
        }
        // $url = $_GET['url'];
        $url = filter_input(INPUT_GET, 'url', FILTER_SANITIZE_URL);

        if (sanitize_text_field($url) != $url) {
            return '';
        }

        $data = [];
        $data['seat_unavailable_error'] = true;
        $data['seat_already_booked'] = __('Seat already booked', 'rrze-pieksy');
        $data['message'] = __('<strong>Sorry! The seat you selected is no longer available.</strong><br>Please try again.', 'rrze-pieksy');
        $data['backlink'] = sprintf(__('<a href="%s">Back to booking form &rarr;</a>', 'rrze-pieksy'), $url);

        return $this->template->getContent('shortcode/booking-error', $data);
    }

    protected function timeslotUnavailableError(){
        if (!isset($_GET['url']) || !isset($_GET['booking']) || !wp_verify_nonce($_GET['booking'], 'timeslot_unavailable-' . $_GET['url'])) {
            return '';
        }
        // $url = $_GET['url'];
        $url = filter_input(INPUT_GET, 'url', FILTER_SANITIZE_URL);

        if (sanitize_text_field($url) != $url) {
            return '';
        }

        $data = [];
        $data['timeslot_unavailable_error'] = true;
        $data['timeslot_in_past'] = __('Timeslot not available.', 'rrze-pieksy');
        $data['message'] = __('<strong>The timeslot you selected lies in the past.</strong><br>Please try again.', 'rrze-pieksy');
        $data['backlink'] = sprintf(__('<a href="%s">Back to booking form &rarr;</a>', 'rrze-pieksy'), $url);

        return $this->template->getContent('shortcode/booking-error', $data);
    }

    protected function selectRoom($input_room = ''){
        $get_room = isset($_GET[ 'room_id' ]) ? absint($_GET[ 'room_id' ]) : '';
        if ($input_room != '' || $get_room != '') {
            return '';
        }
        $selectRoom = '';
        $rooms = get_posts([
            'post_type' => 'room',
            'post_status' => 'publish',
            'nopaging' => true,
            'orderby' => 'title',
            'order' => 'ASC',
        ]);

        $selectRoom .= '<form action="' . get_permalink() . '" method="get" id="pieksy_select_room">';
        $selectRoom .= '<p>' . __('Please select a room.', 'rrze-pieksy') . '</p>';
        $selectRoom .= '<select id="pieksy-room-select" name="room_id">';
        foreach ($rooms as $room) {
            $selectRoom .= '<option value="' . $room->ID . '">' . $room->post_title . '</option>';
        }
        $selectRoom .= '</select>';
        $selectRoom .= '<button type="submit" class="btn btn-primary">' . __('Continue booking', 'rrze-pieksy') . '</button>';
        $selectRoom .= '</form>';
        return $selectRoom;
    }

    protected function bookedNotice(){
        if (!isset($_GET['id']) || !isset($_GET['booking']) || !wp_verify_nonce($_GET['booking'], 'booked-' . $_GET['nonce'])) {
            return '';
        }

        if ($this->idm->isAuthenticated()) {
            $this->idm->logout();
        }

        $bookingId = absint($_GET['id']);
        $booking = Functions::getBooking($bookingId);
        if (!$booking) {
            return '';
        }
        
        $data = [];
        $roomId = $booking['room'];
        $bookingMode = get_post_meta($roomId, 'rrze-pieksy-room-bookingmode', true);
        $roomMeta = get_post_meta($roomId);

        $data['autoconfirmation'] = (isset($roomMeta['rrze-pieksy-room-auto-confirmation']) && $roomMeta['rrze-pieksy-room-auto-confirmation'][0] == 'on');
        // $data['force_to_confirm'] = (isset($roomMeta['rrze-pieksy-room-force-to-confirm']) && $roomMeta['rrze-pieksy-room-force-to-confirm'][0] == 'on');
        $data['force_to_checkin'] = (isset($roomMeta['rrze-pieksy-room-force-to-checkin']) && $roomMeta['rrze-pieksy-room-force-to-checkin'][0] == 'on');

        $data['date'] = $booking['date'];
        $data['date_label'] = __('Date', 'rrze-pieksy');
        $data['time'] = $booking['time'];
        $data['time_label'] = __('Time', 'rrze-pieksy');
        $data['room_name'] = $booking['room_name'];
        $data['room_label'] = __('Room', 'rrze-pieksy');
        $data['seat_name'] = ($bookingMode != 'consultation') ? $booking['seat_name'] : '';
        $data['seat_label'] = __('Seat', 'rrze-pieksy');        
        $data['customer']['name'] = sprintf('%s %s', $booking['guest_firstname'], $booking['guest_lastname']);
        $data['customer']['email'] = $booking['guest_email'];

        $data['data_sent_to_customer_email'] = sprintf(__('These data were also sent to your email address <strong>%s</strong>.', 'rrze-pieksy'), $booking['guest_email']);

        // forceToConfirm
        $data['confirm_your_booking'] = __('Your reservation has been submitted. Please confirm your booking!', 'rrze-pieksy');
        $data['confirmation_request_sent'] = __('An email with the confirmation link and your booking information has been sent to your email address.<br><strong>Please note that unconfirmed bookings automatically expire after one hour.</strong>', 'rrze-pieksy');
        // !forceToConfirm
        $data['reservation_submitted'] = __('Your reservation has been submitted. Thank you for booking!', 'rrze-pieksy');

        // autoconfirmation
        $data['your_reservation'] = __('Your reservation:', 'rrze-pieksy');
        // !autoconfirmation
        $data['your_reservation_request'] = __('Your reservation request:', 'rrze-pieksy');

        // !forceToConfirm && autoconfirmation
        $data['place_has_been_reserved'] = __('<strong>This seat has been reserved for you.</strong><br>You can cancel it at any time if you cannot keep the appointment. You can find information on this in your confirmation email.', 'rrze-pieksy');
        // !forceToConfirm && !autoconfirmation
        $data['reservation_request'] = __('<strong>Please note that this is only a reservation request.</strong><br>It only becomes binding as soon as we confirm your booking by email.', 'rrze-pieksy');

        // forceToCheckin
        $data['check_in_when_arrive'] = __('Please remember to <strong>check in</strong> to your seat when you arrive!', 'rrze-pieksy');

        return $this->template->getContent('shortcode/booking-booked', $data);
    }

    public function bookingSubmitted() {
        if (!isset($_POST['rrze_pieksy_post_nonce_field']) || !wp_verify_nonce($_POST['rrze_pieksy_post_nonce_field'], 'post_nonce')) {
            return;
        }

        array_walk_recursive(
            $_POST, function ( &$value ) {
            if ( is_string( $value ) ) {
                $value = wp_strip_all_tags( trim( $value ) );
            }
            }
        );

        $posted_data = $_POST;
        $booking_date = sanitize_text_field($posted_data['pieksy_date']);
        $booking_start = sanitize_text_field($posted_data['pieksy_time']);
        $booking_timestamp_start = strtotime($booking_date . ' ' . $booking_start);
        $booking_phone = sanitize_text_field($posted_data['pieksy_phone']);
        $booking_instant = (isset($posted_data['pieksy_instant']) && $posted_data['pieksy_instant'] == '1');
        $booking_comment = (isset($posted_data['pieksy_comment']) ? sanitize_textarea_field($posted_data['pieksy_comment']) : '');
        $booking_dsgvo = (isset($posted_data['pieksy_dsgvo']) && $posted_data['pieksy_dsgvo'] == '1');
        $booking_room = absint($posted_data['pieksy_room']);
        $booking_mode = get_post_meta($booking_room, 'rrze-pieksy-room-bookingmode', true);
        if ($booking_mode == 'consultation') {
            $room_seats = get_posts([
                'post_type' => 'seat',
                'meta_key' => 'rrze-pieksy-seat-room',
                'meta_value' => $booking_room,
                'numberposts' => 1,
                'orderby' => 'date',
                'order' => 'ASC',
            ]);
            $booking_seat = $room_seats[0]->ID;
        } else {
            $booking_seat = absint($posted_data['pieksy_seat']);
        }
        $room_id = $booking_room;
        $room_meta = get_post_meta($room_id);
        $room_timeslots = isset($room_meta['rrze-pieksy-room-timeslots']) ? unserialize($room_meta['rrze-pieksy-room-timeslots'][0]) : '';
        foreach ($room_timeslots as $week) {
            $valid_from = ((isset($week[ 'rrze-pieksy-room-timeslot-valid-from' ]) && $week[ 'rrze-pieksy-room-timeslot-valid-from' ] != '') ? $week[ 'rrze-pieksy-room-timeslot-valid-from' ] : 'unlimited');
            $valid_to   = ((isset($week[ 'rrze-pieksy-room-timeslot-valid-to' ]) && $week[ 'rrze-pieksy-room-timeslot-valid-to' ] != '') ? strtotime(
                '+23 hours, +59 minutes',
                intval($week[ 'rrze-pieksy-room-timeslot-valid-to' ])
            ) : 'unlimited');
            foreach ($week['rrze-pieksy-room-weekday'] as $day) {
                if (($valid_from != 'unlimited' && $valid_to != 'unlimited' && $booking_timestamp_start >= $valid_from && $booking_timestamp_start <= $valid_to)
                    || ($valid_from != 'unlimited' && $valid_to == 'unlimited' && $booking_timestamp_start >= $valid_from)
                    || ($valid_from == 'unlimited' && $valid_to != 'unlimited' && $booking_timestamp_start <= $valid_to)
                    || ($valid_from == 'unlimited' && $valid_to == 'unlimited')) {
                    $schedule[$day][$week['rrze-pieksy-room-starttime']] = $week['rrze-pieksy-room-endtime'];
                }
            }
        }
        $weekday = date('N', $booking_timestamp_start);
        $booking_end = array_key_exists($booking_start, $schedule[$weekday]) ? $schedule[$weekday][$booking_start] : $booking_start;
        $booking_timestamp_end = strtotime($booking_date . ' ' . $booking_end);

        if ($this->sso) {
            if ($this->idm->isAuthenticated()){
                $sso_data = $this->idm->getCustomerData();
                $booking_lastname  = $sso_data['customer_lastname'];
                $booking_firstname  = $sso_data['customer_firstname'];
                $booking_email  = $sso_data['customer_email'];
            } else {
                $redirectUrl = add_query_arg(
                    [
                        'booking' => wp_create_nonce('sso_authentication'),
                        'nonce' => $this->nonce
                    ],
                    get_permalink()
                );
                wp_redirect($redirectUrl);
                exit;
            }
        } else {
            $booking_lastname = sanitize_text_field($posted_data['pieksy_lastname']);
            $booking_firstname = sanitize_text_field($posted_data['pieksy_firstname']);
            $booking_email = sanitize_email($posted_data['pieksy_email']);
        }

        // Postdaten überprüfen
        $transientData = new TransientData(bin2hex(random_bytes(8)));

        if (!$booking_dsgvo) {
            $transientData->addData(
                'pieksy_dsgvo', 
                [
                    'value' => $booking_dsgvo,
                    'message' => __('DSGVO field is required.', 'rrze-pieksy')
                ]
            );
        }
        if (!Functions::validateDate($booking_date) || !Functions::validateTime($booking_start, 'H:i')) {
            $transientData->addData(
                'pieksy_date', 
                [
                    'value' => $booking_date,
                    'message' => __('The date or time of the booking is not valid.', 'rrze-pieksy')
                ]
            );
        }
        if (!get_post_meta($booking_seat, 'rrze-pieksy-seat-room', true)) {
            $transientData->addData(
                'pieksy_seat', 
                [
                    'value' => $booking_seat,
                    'message' => __('The room does not exist.', 'rrze-pieksy')
                ]
            );
        }
        if (empty($booking_lastname)) {
            $transientData->addData(
                'customer_lastname', 
                [
                    'value' => $booking_lastname,
                    'message' => __('Your last name is required.', 'rrze-pieksy')
                ]
            );
        }else{
            // encrypt user data
            $booking_lastname = Functions::crypt($booking_lastname, 'encrypt');
        }
        if (empty($booking_firstname)) {
            $transientData->addData(
                'customer_firstname', 
                [
                    'value' => $booking_firstname,
                    'message' => __('Your name is required.', 'rrze-pieksy')
                ]
            );
        }else{
            // encrypt user data
            $booking_firstname = Functions::crypt($booking_firstname, 'encrypt');
        }        
        if (!filter_var($booking_email, FILTER_VALIDATE_EMAIL)) {
            $transientData->addData('
                pieksy_date', 
                [
                    'value' => $booking_email,
                    'message' => __('The email address is not valid.', 'rrze-pieksy')
                ]
            );
        }else{
            // encrypt user data
            $booking_email = Functions::crypt($booking_email, 'encrypt');
        }
        if (!Functions::validatePhone($booking_phone)) {
            $transientData->addData(
                'pieksy_phone', 
                [
                    'value' => $booking_phone,
                    'message' => __('Your phone number is not valid.', 'rrze-pieksy')
                ]
            );
        }else{
            // encrypt user data
            $booking_phone = Functions::crypt($booking_phone, 'encrypt');
        }

        if (!empty($transientData->getData(false))) {           
            $transient = $transientData->getTransient();
            $redirectUrl = add_query_arg(
                [
                    'transient-data-nonce' => wp_create_nonce('transient-data-' . $transient),
                    'transient-data' => $transient,
                    'nonce' => $this->nonce
                ],
                wp_get_referer()
            );
            wp_redirect($redirectUrl);
            exit;             
        }

        // Überprüfen ob bereits eine Buchung mit gleicher E-Mail-Adresse zur gleichen Zeit vorliegt
        $check_args = [
            'post_type' => 'booking',
            'meta_query' => [
                'relation' => 'AND',
                [
                    'key' => 'rrze-pieksy-booking-guest-email',
                    'value' => $booking_email
                ],
                [
                    'key' => 'rrze-pieksy-booking-status',
                    'value' => ['booked', 'customer-confirmed', 'confirmed', 'checked-in'],
                    'compare' => 'IN',
                ],
                [
                    'relation' => 'OR',
                    [
                        'key' => 'rrze-pieksy-booking-start',
                        'value' => [$booking_timestamp_start + 1, $booking_timestamp_end - 1],
                        'compare' => 'BETWEEN',
                    ],
                    [
                        'key' => 'rrze-pieksy-booking-end',
                        'value' => [$booking_timestamp_start + 1, $booking_timestamp_end - 1],
                        'compare' => 'BETWEEN',
                    ],
                   [
                       'key' => 'rrze-pieksy-booking-start',
                       'value' => $booking_timestamp_start,
                       'compare' => '=',
                   ],
                   [
                       'key' => 'rrze-pieksy-booking-end',
                       'value' => $booking_timestamp_end,
                       'compare' => '=',
                   ],
                ]
            ],
            'nopaging' => true,
        ];
        $check_bookings = get_posts($check_args);
        if (!empty($check_bookings)) {
            $redirectUrl = add_query_arg(
                [
                    'booking' => wp_create_nonce('multiple_booking-' . $this->nonce),
                    'nonce' => $this->nonce
                ],
                get_permalink()
            );
            wp_redirect($redirectUrl);
            exit;            
        }

        // Überprüfen ob Timeslot in der Vergangenheit liegt
        if ($booking_timestamp_end < current_time('timestamp')) {
            $url = urlencode(wp_get_referer());
            $redirectUrl = add_query_arg(
                [
                    //'url' => sprintf('%s?room_id=%s&bookingdate=%s&timeslot=%s', get_permalink(), $room_id, $booking_date, $booking_start),
                    'url' => $url,
                    'booking' => wp_create_nonce('timeslot_unavailable-' . $url),
                    'nonce' => $this->nonce
                ],
                get_permalink()
            );
            wp_redirect($redirectUrl);
            exit;
        }

        // Überprüfen ob der Platz in der Zwischenzeit bereits anderweitig gebucht wurde
        $bookings = get_posts([
            'post_type' => 'booking',
            'post_status' => 'publish',
            'nopaging' => true,
            'meta_query' => [
                'relation' => 'AND',
                [
                    'key' => 'rrze-pieksy-booking-seat',
                    'value'   => $booking_seat,
                ],
                [
                    'key' => 'rrze-pieksy-booking-status',
                    'value'   => ['booked', 'customer-confirmed', 'confirmed', 'checked-in'],
                    'compare' => 'IN'
                ],
                [
                    'key'     => 'rrze-pieksy-booking-start',
                    'value' => strtotime($booking_date . ' ' . $booking_start),
                    'compare' => '=',
                ],
            ],
        ]);

        if (!empty($bookings)) {

            $url = urlencode(wp_get_referer());

            $redirectUrl = add_query_arg(
                [
                    //'url' => sprintf('%s?room_id=%s&bookingdate=%s&timeslot=%s', get_permalink(), $room_id, $booking_date, $booking_start),
                    'url' => $url,
                    'booking' => wp_create_nonce('seat_unavailable-' . $url),
                    'nonce' => $this->nonce
                ],
                get_permalink()
            );
            wp_redirect($redirectUrl);
            exit;
        }

        $autoconfirmation = Functions::getBoolValueFromAtt(get_post_meta($room_id, 'rrze-pieksy-room-auto-confirmation', true));
        $instantCheckIn = Functions::getBoolValueFromAtt(get_post_meta($room_id, 'rrze-pieksy-room-instant-check-in', true));
        // $forceToConfirm = Functions::getBoolValueFromAtt(get_post_meta($room_id, 'rrze-pieksy-room-force-to-confirm', true));
        $forceToCheckin = Functions::getBoolValueFromAtt(get_post_meta($room_id, 'rrze-pieksy-room-force-to-checkin', true));
        $sendIcsToAdmin = get_post_meta($room_id, 'rrze-pieksy-room-send-to-email', true);
        if ($booking_mode == 'check-only') {
            $autoconfirmation = true;
            $instantCheckIn = true;
            $forceToCheckin = false;
        }

        //Buchung speichern
        $new_draft = [
            'post_status' => 'publish',
            'post_type' => 'booking',
        ];
        $booking_id = wp_insert_post($new_draft);

        // Booking save error
        if (!$booking_id || is_wp_error($booking_id)) {
            $redirectUrl = add_query_arg(
                [
                    'booking' => wp_create_nonce('save_error-' . $this->nonce),
                    'nonce' => $this->nonce
                ],
                get_permalink()
            );
            wp_redirect($redirectUrl);
            exit;            
        }

        // Successful booking saved
        update_post_meta($booking_id, 'rrze-pieksy-booking-start', $booking_timestamp_start);
        $weekday = date_i18n('w', $booking_timestamp_start);
        update_post_meta($booking_id, 'rrze-pieksy-booking-end', $booking_timestamp_end );
        update_post_meta($booking_id, 'rrze-pieksy-booking-seat', $booking_seat);
        update_post_meta($booking_id, 'rrze-pieksy-booking-guest-lastname', $booking_lastname);
        update_post_meta($booking_id, 'rrze-pieksy-booking-guest-firstname', $booking_firstname);
        update_post_meta($booking_id, 'rrze-pieksy-booking-guest-email', $booking_email);
        update_post_meta($booking_id, 'rrze-pieksy-booking-guest-phone', $booking_phone);

        // Set booking status
        $timestamp = current_time('timestamp');
        $bookingMode = get_post_meta($room_id, 'rrze-pieksy-room-bookingmode', true);

        // if ($forceToConfirm) {
        //     $status = 'booked';
        // } else {
            if ($autoconfirmation) {
                $status = 'confirmed';
                switch ($bookingMode) {
                    case 'check-only':
                        if ($booking_date == date('Y-m-d', $timestamp) && $booking_timestamp_start < $timestamp) {
                            $status = 'checked-in';
                        }
                        break;
                    case 'reservation':
                        if (($booking_instant || $instantCheckIn) && $booking_date == date('Y-m-d', $timestamp) && $booking_timestamp_start < $timestamp) {
                            $status = 'checked-in';
                        }
                        break;
                    default:
                        //
                }
            } else {
                $status = 'booked';
            }
        // }

        update_post_meta($booking_id, 'rrze-pieksy-booking-status', $status );
        update_post_meta($booking_id, 'rrze-pieksy-booking-notes', $booking_comment);
        update_post_meta($booking_id, 'rrze-pieksy-booking-dsgvo', $booking_dsgvo);


        // E-Mail senden
        // if ($forceToConfirm) {
        //     $this->email->doEmail('customerConfirmationRequired', 'customer', $booking_id, $status);
        // } else {
            if ($bookingMode == 'check-only') {
                $this->email->doEmail('bookingCheckedIn', 'customer', $booking_id, $status);
                if ($this->options->email_notification_if_new == 'yes' && $this->options->email_notification_email != '') {
                    $this->email->doEmail('newBooking', 'admin', $booking_id, $status);
                }
            } elseif ($autoconfirmation){
                $this->email->doEmail('adminConfirmed', 'customer', $booking_id, $status);
                if ($this->options->email_notification_if_new == 'yes' && $this->options->email_notification_email != '') {
                    $this->email->doEmail('newBooking', 'admin', $booking_id, $status);
                }
            } else {
                $this->email->doEmail('adminConfirmationRequired', 'customer', $booking_id, $status);
                $this->email->doEmail('adminConfirmationRequired', 'admin', $booking_id, $status);
            }
        // }


        /*switch($bookingMode) {
            case 'check-only':
                if ($status == 'confirmed') {
                    $this->email->doEmail('adminConfirmed', 'customer', $booking_id, $status);
                    if ($this->options->email_notification_if_new == 'yes' && $this->options->email_notification_email != '') {
                        $this->email->doEmail('newBooking', 'admin', $booking_id, $status);
                    }
                }
                break;
            case 'reservation':
            case 'no-check':
            case 'consultation':
                if ($status == 'confirmed') {
                    $this->email->doEmail('adminConfirmed', 'customer', $booking_id, $status);
                } elseif ($status == 'checked-in') {
                    $this->email->doEmail('adminConfirmed', 'customer', $booking_id, $status);
                } elseif ($status == 'booked' && $forceToConfirm) {
                    $this->email->doEmail('customerConfirmationRequired', 'customer', $booking_id, $status);
                } else {
                    if ($this->options->email_notification_if_new == 'yes' && $this->options->email_notification_email != '') {
                        $this->email->doEmail('newBooking', 'admin', $booking_id, $status);
                    }
                }
                break;
            default:
                //
        }*/

        // Redirect zur Seat-Seite, falls
        if ($status == 'checked-in') {
            $redirectUrl = add_query_arg(
                [
                    'id' => $booking_id,
                    'nonce' => wp_create_nonce('rrze-pieksy-checkin-booked-' . $booking_id)
                ],
                get_permalink($booking_seat)
            );
            wp_redirect($redirectUrl);
            exit;
        }

        // Redirect to bookedNotice()
        $redirectUrl = add_query_arg(
            [
                'id' => $booking_id,
                'booking' => wp_create_nonce('booked-' . $this->nonce),
                'nonce' => $this->nonce
            ],
            get_permalink()
        );
        wp_redirect($redirectUrl);
        exit;
    }

    /*
     * Inspirationsquelle:
     * https://css-tricks.com/snippets/php/build-a-calendar-table/
     */
    public function buildCalendar($month, $year, $start = '', $end = '', $roomID = '', $bookingdate_selected = '') {
//        $month = 6;
        if ($start == '') {
            $start = date('Y-m-d', current_time('timestamp'));
        }
        if ($end == '') {
            $end = date('Y-m-d', strtotime($start . ' +7 days'));
        }
        // Create array containing abbreviations of days of week.
        $daysOfWeek = Functions::daysOfWeekAry(0, 1, 2);
        // What is the first day of the month in question?
        $firstDayOfMonth = mktime(0,0,0,$month,1,$year);
        $firstDayOfMonthDate = date('Y-m-d', $firstDayOfMonth);
        // How many days does this month contain?
        $numberDays = date('t', $firstDayOfMonth);
        $lastDayOfMonth = mktime(0,0,0, $month, $numberDays, $year);
        $lastDayOfMonthDate = date('Y-m-d', $lastDayOfMonth);
        // What is the name of the month in question?
        $monthName = date_i18n('w', $firstDayOfMonth);
        // What is the index value (1-7) of the first day of the month in question.
        $dayOfWeek = date('N', $firstDayOfMonth);
        $bookingDaysStart = $start;
        $bookingDaysEnd = $end;
        $link_next = '<a href="#" class="cal-skip cal-next" data-direction="next">&gt;&gt;</a>';
        $link_prev = '<a href="#" class="cal-skip cal-prev" data-direction="prev">&lt;&lt;</a>';
        $availability = Functions::getRoomAvailability($roomID, $bookingDaysStart, $bookingDaysEnd, false);
        // Create the table tag opener and day headers
        $calendar = '<table class="pieksy_calendar" data-period="'.date_i18n('Y-m', $firstDayOfMonth).'" data-end="'.$bookingDaysEnd.'">';
        $calendar .= "<caption>";
        if ($bookingDaysStart <= $firstDayOfMonthDate) {
            $calendar .= $link_prev;
        }
        $calendar .= date_i18n('F Y', $firstDayOfMonth);
        if ($bookingDaysEnd >= $lastDayOfMonthDate) {
            $calendar .= $link_next;
        }
        //print $remainingBookingDays;
        $calendar .= "</caption>";
        // Create the calendar headers
        $calendar .= "<tr>";
        foreach($daysOfWeek as $day) {
            $calendar .= "<th class='header'>$day</th>";
        }
        $calendar .= "</tr>";
        // Create the rest of the calendar
        // Initiate the day counter, starting with the 1st.
        $currentDay = 1;
        $calendar .= "<tr>";
        // The variable $dayOfWeek is used to ensure that the calendar display consists of exactly 7 columns.
        if ($dayOfWeek > 1) {
            $colspan = $dayOfWeek - 1;
            $calendar .= "<td colspan='$colspan'>&nbsp;</td>";
        }
        $month = str_pad($month, 2, "0", STR_PAD_LEFT);
        while ($currentDay <= $numberDays) {
            // Seventh column (Saturday) reached. Start a new row.

            if ($dayOfWeek > 7) {
                $dayOfWeek = 1;
                $calendar .= "</tr><tr>";
            }
            $currentDayRel = str_pad($currentDay, 2, "0", STR_PAD_LEFT);
            $date = "$year-$month-$currentDayRel";
            $class = '';
            $title = '';
            $active = true;
            if ($date < $bookingDaysStart || $date >$bookingDaysEnd) {
                $active = false;
                $title = __('Not bookable (outside booking period)','rrze-pieksy');
            } else {
                $active = false;
                $class = 'soldout';
                $title = __('Not bookable (soldout or room blocked)','rrze-pieksy');
                if ($roomID == '') {

                } else {
                    if (isset($availability[$date])) {
                        foreach ( $availability[ $date ] as $timeslot ) {
                            if ( !empty( $timeslot ) ) {
                                $active = true;
                                $class = 'available';
                                $title = __( 'Seats available', 'rrze-pieksy' );
                            }
                        }
                    }
                }
            }

            $input_open = '<span class="inactive">';
            $input_close = '</span>';
            if ($active) {
                if ($bookingdate_selected == $date || ($bookingdate_selected == false && $date == $bookingDaysStart)) {
                    $checked = 'checked="checked"';
                } else {
                    $checked = '';
                }
                $input_open = "<input type=\"radio\" id=\"pieksy_date_$date\" value=\"$date\" name=\"pieksy_date\" $checked required aria-required='true'><label for=\"pieksy_date_$date\">";
                $input_close = '</label>';
            }
            $calendar .= "<td class='day $class' rel='$date' title='$title'>" . $input_open.$currentDay.$input_close . "</td>";
            // Increment counters
            $currentDay++;
            $dayOfWeek++;
        }
        // Complete the row of the last week in month, if necessary
        if ($dayOfWeek != 8) {
            $remainingDays = 8 - $dayOfWeek;
            $calendar .= "<td colspan='$remainingDays'>&nbsp;</td>";
        }
        $calendar .= "</tr>";
        $calendar .= "</table>";
        return $calendar;
    }

    public function ajaxUpdateCalendar() {
        check_ajax_referer( 'pieksy-ajax-nonce', 'nonce' );
        $period = explode('-', $_POST['month']);
        $month = $period[1];
        $year = $period[0];
        switch ($month) {
            case '1':
                $modMonth = $_POST['direction'] == 'next' ? 1 : 11;
                $modYear = $_POST['direction'] == 'next' ? 0 : -1;
                break;
            case '12':
                $modMonth = $_POST['direction'] == 'next' ? -11 : -1;
                $modYear = $_POST['direction'] == 'next' ? 1 : 0;
                break;
            default:
                $modMonth = $_POST['direction'] == 'next' ? 1 : -1;
                $modYear = 0;
                break;
        }

        $start = date_i18n('Y-m-d', current_time('timestamp'));
        $end = sanitize_text_field($_POST['end']);
        $roomID = (int)$_POST['room'];
        $output = '';
        $output .= $this->buildCalendar($month + $modMonth, $year + $modYear, $start, $end, $roomID);
        echo $output;
        wp_die();
    }

    public function ajaxUpdateForm() {
        check_ajax_referer( 'pieksy-ajax-nonce', 'nonce'  );
        $roomID = ((isset($_POST['room']) && $_POST['room'] > 0) ? (int)$_POST['room'] : '');
        $date = (isset($_POST['date']) ? sanitize_text_field($_POST['date']) : false);
        $time = (isset($_POST['time']) ? sanitize_text_field($_POST['time']) : false);
        $seat = (isset($_POST['seat']) ? sanitize_text_field($_POST['seat']) : false);
        $response = [];
        if ($date !== false) {
            $response['time'] = '<div class="pieksy-time-select error">'.__('Please select a date.', 'rrze-pieksy').'</div>';
        }
        if (!$date || !$time) {
            $response['seat'] = '<div class="pieksy-seat-select error">'.__('Please select a date and a time slot.', 'rrze-pieksy').'</div>';
        }
        $availability = Functions::getRoomAvailability($roomID, $date, $date, false);
        $bookingMode = get_post_meta($roomID, 'rrze-pieksy-room-bookingmode', true);
        if ($date) {
            $response['time'] = $this->buildTimeslotSelect($roomID, $date, $time, $availability);
            if ($time && ($bookingMode != 'consultation')) {
                $seatSelect = $this->buildSeatSelect($roomID, $date, $time, $seat, $availability);
                $response['seat'] = $seatSelect . $seatInfo;
            }
        }
        wp_send_json($response);
    }


    private function buildTimeslotSelect($roomID, $date, $time = false, $availability) {
        $slots = [];
        $timeSelects = '';
        if (!empty($availability) && isset($availability[$date])) {
            $slots = array_keys($availability[$date]);
            foreach ($slots as $slot) {
                $slot_value = explode('-', $slot)[0];
                $id = 'pieksy_time_' . sanitize_title($slot_value);
                $checked = checked($time !== false && $time == $slot_value, true, false);
                $timeSelects .= "<div class='form-group'><input type='radio' id='$id' value='$slot_value' name='pieksy_time' " . $checked . " required aria-required='true'><label for='$id'>$slot</label></div>";
            }
        }
        if ($timeSelects == '') {
            $timeSelects .= __('No time slots available.', 'rrze-pieksy');
        }
        return '<div class="pieksy-time-select">' . $timeSelects . '</div>';
    }

    private function buildSeatSelect($roomID, $date, $time, $seat_id, $availability) {
        foreach ($availability as $xdate => $xtime) {
            foreach ($xtime as $k => $v) {
                $k_new = explode('-', $k)[0];
                $availability[$xdate][$k_new] = $v;
                unset($availability[$xdate][$k]);
            }
        }
        $seats = (isset($availability[$date][$time])) ? $availability[$date][$time] : [];

        // sort by title naturally
        $seatSortedByTitle = [];
        foreach ($seats as $seatID) {
            $seatSortedByTitle[$seatID] = get_the_title($seatID);
        }
        natsort($seatSortedByTitle);

        $seatSelects = '';
        foreach ($seatSortedByTitle as $seat => $title) {
            $seatname = $title;
            $id = 'pieksy_seat_' . sanitize_title($seat);
            $checked = checked($seat_id !== false && $seat == $seat_id, true, false);
            $seatSelects .= "<div class='form-group'>"
                . "<input type='radio' id='$id' value='$seat' name='pieksy_seat' $checked required aria-required='true'>"
                . "<label for='$id'>$seatname</label>"
                . "</div>";
        }
        if ($seatSelects == '') {
            $seatSelects = __('Please select a date and a time slot.', 'rrze-pieksy');
        }
        return '<h4>' . __('Available seats:', 'rrze-pieksy') . '</h4><div class="pieksy-seat-select">' . $seatSelects . '</div>';
    }

    protected function getShortcodeAtt(string $content, string $shortcode, string $att)
    {
        $ret = 0;
        if (has_shortcode($content, $shortcode)) {
            $result = [];
            $pattern = get_shortcode_regex();
            if (preg_match_all('/' . $pattern . '/s', $content, $matches)) {
                $keys = [];
                $result = [];
                foreach ($matches[0] as $key => $value) {
                    $get = str_replace(' ', '&', $matches[3][$key]);
                    parse_str($get, $output);
                    $keys = array_unique(array_merge($keys, array_keys($output)));
                    $result[][$matches[2][$key]] = $output;
                }
            }
            foreach ($result as $key => $value) {
                if (isset($value[$shortcode][$att])) {
                    if (is_array($value[$shortcode][$att])) {
                        $ret = $value[$shortcode][$att];
                    } else {
                        $ret = trim($value[$shortcode][$att],'"');
                    }
                }                
            }
        }
        return absint($ret);
    }
}
