<?php

namespace RRZE\Pieksy;

defined('ABSPATH') || exit;

use RRZE\Pieksy\Settings;

class Metaboxes
{
    public function __construct()
    {
        require_once plugin()->getPath('vendor/cmb2') . 'init.php';
        $this->settings = new Settings(plugin()->getFile());
    }

    public function onLoaded()
    {
        add_action('cmb2_admin_init', [$this, 'booking']);
        add_action('cmb2_admin_init', [$this, 'room']);
        add_action('cmb2_admin_init', [$this, 'seat']);
    }

    public function cb_encrypt($value){
        if ($value){
            return Functions::crypt($value, 'encrypt');
        }
    }

    public function cb_decrypt($value){
        if ($value){
            return Functions::crypt($value, 'decrypt');
        }
    }

    /*
     * Set Timeslot weekday, start time and end time to disabled if there are bookings for this timeslot.
     * Valid from/to can still be modified.
     * @param  object $field_args Current field args
     * @param  object $field      Current field object
     */
    public function cbTimeslotAttributes($args, $field) {
        $seats = Functions::getAllRoomSeats($field->object_id);
        $bookings = get_posts([
            'post_type' => 'booking',
            'post_statue' => 'publish',
            'nopaging' => true,
            'orderby' => 'title',
            'order' => 'ASC',
            'meta_key' => 'rrze-pieksy-booking-seat',
            'meta_value' => $seats,
            'meta_compare' => 'IN',
        ]);
        $fieldKey = str_replace(array('+','-'), '', filter_var($args['_name'], FILTER_SANITIZE_NUMBER_INT));
        $timeslots = get_post_meta($field->object_id, 'rrze-pieksy-room-timeslots', true);
        $weekdays = isset($timeslots[$fieldKey]['rrze-pieksy-room-weekday']) ? $timeslots[$fieldKey]['rrze-pieksy-room-weekday'] : [];
        $starttime = isset($timeslots[$fieldKey]['rrze-pieksy-room-starttime']) ? $timeslots[$fieldKey]['rrze-pieksy-room-starttime'] : '';
        $endtime = isset($timeslots[$fieldKey]['rrze-pieksy-room-endtime']) ? $timeslots[$fieldKey]['rrze-pieksy-room-endtime'] : '';
        $validfrom = (isset($timeslots[$fieldKey]['rrze-pieksy-room-timeslot-valid-from']) && $timeslots[$fieldKey]['rrze-pieksy-room-timeslot-valid-from'] !== false) ? $timeslots[$fieldKey]['rrze-pieksy-room-timeslot-valid-from'] : 0;
        $validto = (isset($timeslots[$fieldKey]['rrze-pieksy-room-timeslot-valid-to']) && $timeslots[$fieldKey]['rrze-pieksy-room-timeslot-valid-to'] !== false) ? $timeslots[$fieldKey]['rrze-pieksy-room-timeslot-valid-to'] : 9999999999;
        foreach ($bookings as $booking) {
            $bookingMeta = get_post_meta($booking->ID);
            if (!isset($bookingMeta['rrze-pieksy-booking-start']) || !isset($bookingMeta['rrze-pieksy-booking-end']))
                continue;
            $startTimestamp = $bookingMeta['rrze-pieksy-booking-start'][0];
            $endTimestamp = $bookingMeta['rrze-pieksy-booking-end'][0];
            if (date('H:i', $startTimestamp) == $starttime
                && date('H:i', $endTimestamp) == $endtime
                && in_array(date('N', $startTimestamp), $weekdays)
                && $startTimestamp > $validfrom
                && $startTimestamp < $validto) {
                    $field->args['attributes']['disabled'] = 'disabled';
                    break;
            }
        }
    }

    public function cbBookingStatusAttributes($args, $field) {
        $field->args['attributes']['required'] = 'required';
        // Allow status selection only for new posts
        // Disable on edit-post screen -> Controlled status changes via list table only
        $screen = get_current_screen();
        if( $screen->action != 'add') {
            $field->args['attributes']['disabled'] = 'disabled';
        }
    }


    public function booking()
    {
        $cmb = new_cmb2_box(array(
            'id'            => 'rrze-pieksy-booking-details',
            'title'         => __('Details', 'rrze-pieksy'),
            'object_types'  => array('booking'), // Post type
            'context'       => 'normal',
            'priority'      => 'high',
            'show_names'    => true, // Show field names on the left
            // 'cmb_styles' => false, // false to disable the CMB stylesheet
            // 'closed'     => true, // Keep the metabox closed by default
        ));

        $cmb->add_field(array(
            'name'             => __('Seat', 'rrze-pieksy'),
            'id'               => 'rrze-pieksy-booking-seat',
            'type'             => 'select',
            'show_option_none' => '&mdash; ' . __('Please select', 'rrze-pieksy') . ' &mdash;',
            'default'          => 'custom',
            'options'          => $this->getSeats(),
            'attributes'  =>  [
                'required' => 'required',
            ],             
        ));

        $cmb->add_field(array(
            'name'             => __('Timeslot', 'rrze-pieksy'),
            'id'               => 'rrze-pieksy-booking-start',
            //'type' => 'text_date_timestamp',
            'type' => 'text_datetime_timestamp',
            'date_format' => 'd.m.Y',
            'time_format' => 'H:i',
            'attributes' => array(
                'data-timepicker' => json_encode(
                    array(
                        'timeFormat' => 'HH:mm',
                        'stepMinute' => 10,
                    )
                ),
                'required' => 'required',
            ),
            'after'     => [$this, 'cbDisplayTimeslot'],
            'description'   => __('Click on the date input to select a date and a time slot.', 'rrze-pieksy'),
        ));

        $cmb->add_field(array(
            'name'             => __('End', 'rrze-pieksy'),
            'id'               => 'rrze-pieksy-booking-end',
            //'type' => 'text_date_timestamp',
            'type' => 'text_datetime_timestamp',
            'date_format' => 'd.m.Y',
            'time_format' => 'H:i',
            'attributes' => array(
                'data-timepicker' => json_encode(
                    array(
                        'timeFormat' => 'HH:mm',
                        'stepMinute' => 10,
                    )
                ),
                'disabled' => 'disabled',
            ),
            'required'  => 'required',
            'classes'   => 'hidden'
        ));

        $cmb->add_field(array(
            'name'             => __('Status', 'rrze-pieksy'),
            'id'               => 'rrze-pieksy-booking-status',
            'type'             => 'select',
            'show_option_none' => '&mdash; ' . __('Please select', 'rrze-pieksy') . ' &mdash;',
            'default'          => 'booked',
            'options'          => array(
                'booked' => _x('Booked', 'Booking status in booking editor', 'rrze-pieksy'),
                // 'customer-confirmed'   => _x('Confirmed by customer', 'Booking status in booking editor', 'rrze-pieksy'),
                // 'confirmed'   => _x('Confirmed', 'Booking status in booking editor', 'rrze-pieksy'),
                // 'cancelled'     => _x('Cancelled', 'Booking status in booking editor', 'rrze-pieksy'),
                // 'checked-in'     => _x('Checked In', 'Booking status in booking editor', 'rrze-pieksy'),
                // 'checked-out'     => _x('Checked Out', 'Booking status in booking editor', 'rrze-pieksy'),
            ),
            'before' => [$this, 'cbBookingStatusAttributes'],
        ));


        $cmb->add_field(array(
            'name'    => __('Last name', 'rrze-pieksy'),
            'id'      => 'rrze-pieksy-booking-guest-lastname',
            'type'    => 'text',
		    'sanitization_cb' => [$this, 'cb_encrypt'], // encrypt before storing
            'escape_cb'       => [$this, 'cb_decrypt'], // decrypt before displaying
            'attributes'  =>  [
                'required' => 'required',
            ],            
        ));

        $cmb->add_field(array(
            'name'    => __('First name', 'rrze-pieksy'),
            'id'      => 'rrze-pieksy-booking-guest-firstname',
            'type'    => 'text',
		    'sanitization_cb' => [$this, 'cb_encrypt'],
            'escape_cb'       => [$this, 'cb_decrypt'],
            'attributes'  =>  [
                'required' => 'required',
            ],             
        ));

        $cmb->add_field(array(
            'name'    => __('Email', 'rrze-pieksy'),
            'id'      => 'rrze-pieksy-booking-guest-email',
            'type'    => 'text_email',
		    'sanitization_cb' => [$this, 'cb_encrypt'],
            'escape_cb'       => [$this, 'cb_decrypt'],
            'attributes'  =>  [
                'required' => 'required',
            ],             
        ));

        $cmb->add_field(array(
            'name'    => __('Phone', 'rrze-pieksy'),
            'id'      => 'rrze-pieksy-booking-guest-phone',
            'type'    => 'text_medium',
            'attributes' => array(
                'type' => 'tel',
//                'required' => 'required',
            ),
		    'sanitization_cb' => [$this, 'cb_encrypt'],
            'escape_cb'       => [$this, 'cb_decrypt'],
        ));

        $cmb->add_field(array(
            'name' => __('Notes', 'rrze-pieksy'),
            'id' => 'rrze-pieksy-booking-notes',
            'type' => 'textarea',
            'desc' => __("This textarea contains the 'Additional Information' field content if this option is activated in the room settings. It can also be used for notes for internal use.", 'rrze-pieksy'),
        ));
    }

    public function room()
    {
        $cmb_timeslots = new_cmb2_box(array(
            'id'            => 'rrze-pieksy-room-timeslots_meta',
            'title'         => __('Timeslots', 'rrze-pieksy'),
            'object_types'  => array('room',), // Post type
            'context'       => 'normal',
            'priority'      => 'high',
            'show_names'    => true, // Show field names on the left
            // 'cmb_styles' => false, // false to disable the CMB stylesheet
            // 'closed'     => true, // Keep the metabox closed by default
        ));

        $group_field_id = $cmb_timeslots->add_field(array(
            'id'          => 'rrze-pieksy-room-timeslots',
            'type'        => 'group',
            'description' => __('Define bookable time slots.', 'rrze-pieksy'),
            'options'     => array(
                'group_title'       => __('Time slot {#}', 'rrze-pieksy'), // since version 1.1.4, {#} gets replaced by row number
                'add_button'        => __('Add Another Entry', 'cmb2'),
                'remove_button'     => __('Remove Entry', 'cmb2'),
                'sortable'          => false,
                // 'closed'         => true, // true to have the groups closed by default
                // 'remove_confirm' => esc_html__( 'Are you sure you want to remove?', 'cmb2' ), // Performs confirmation before removing group.
            ),
        ));

        $cmb_timeslots->add_group_field($group_field_id, array(
            'name'    => __('Week day', 'rrze-pieksy'),
            //'desc'    => 'field description (optional)',
            'id'      => 'rrze-pieksy-room-weekday',
            'type'    => 'multicheck',
            'options' => Functions::daysOfWeekAry(1),
            'before' => [$this, 'cbTimeslotAttributes'],
            'default' => ['1', '2', '3', '4', '5'],
        ));

        $cmb_timeslots->add_group_field($group_field_id,  array(
            'name' => __('Start time', 'rrze-pieksy'),
            'id' => 'rrze-pieksy-room-starttime',
            'type' => 'text_time',
            'time_format' => 'H:i',
            'before' => [$this, 'cbTimeslotAttributes'],
            'default' => '00:00',
        ));

        $cmb_timeslots->add_group_field($group_field_id,  array(
            'name' => __('End time', 'rrze-pieksy'),
            'id' => 'rrze-pieksy-room-endtime',
            'type' => 'text_time',
            'time_format' => 'H:i',
            'before' => [$this, 'cbTimeslotAttributes'],
            'default' => '00:00',
        ));

        $cmb_timeslots->add_group_field($group_field_id,  array(
            'name' => __('Valid from (optional)', 'rrze-pieksy'),
            'id' => 'rrze-pieksy-room-timeslot-valid-from',
            'type' => 'text_date_timestamp',
            'date_format' => 'd.m.Y',
        ));

        $cmb_timeslots->add_group_field($group_field_id,  array(
            'name' => __('Valid until (optional)', 'rrze-pieksy'),
            'id' => 'rrze-pieksy-room-timeslot-valid-to',
            'type' => 'text_date_timestamp',
            'date_format' => 'd.m.Y',
        ));

        $cmb_timeslots->add_field(array(
            'name' => __('Days closed', 'rrze-pieksy'),
            'desc' => __('Enter days when this room is not available (format YYYY-MM-DD). One date per line. Will overwrite timeslots (e.g. Room is available every Monday, except 2020-09-21.)', 'rrze-pieksy'),
            //'default' => 'standard value (optional)',
            'id' => 'rrze-pieksy-room-days-closed',
            'type' => 'textarea_small'
        ));


        $cmb_general = new_cmb2_box(array(
            'id'            => 'rrze_pieksy_general-meta',
            'title'         => __('Details', 'rrze-pieksy'),
            'object_types'  => array('room',), // Post type
            'context'       => 'normal',
            'priority'      => 'high',
            'show_names'    => true, // Show field names on the left
            // 'cmb_styles' => false, // false to disable the CMB stylesheet
            // 'closed'     => true, // Keep the metabox closed by default
        ));

        $cmb_general->add_field(array(
            'name'             => __('Booking mode', 'rrze-pieksy'),
            // 'desc'             => __('', 'rrze-pieksy'),
            'id'               => 'rrze-pieksy-room-bookingmode',
            'type'             => 'select',
            'default'          => 'reservation',
            'options' => array(
                // 'reservation' => __('Reservation with check-in', 'rrze-pieksy'),
                'no-check' => __('Reservation without check-in', 'rrze-pieksy'),
                // 'consultation' => __('Consultation', 'rrze-pieksy'),
                // 'check-only' => __('Check-in and check-out only on site', 'rrze-pieksy'), // Nur Ein- und Auschecken vor Ort
            ),
            'description' => '',
        ));

        $cmb_general->add_field(array(
            'name' => __('SSO is required', 'rrze-pieksy'),
            'desc' => __('If SSO is enabled, the customer must log in via SSO in order to use the booking system.', 'rrze-pieksy'),
            'id'   => 'rrze-pieksy-room-sso-required',
            'type' => 'checkbox',
            'default' => true,
        ));

        $cmb_general->add_field(array(
            'name' => __('Available days in advance', 'rrze-pieksy'),
            'desc' => __('Number of days for which bookings are available in advance.', 'rrze-pieksy'),
            'id'   => 'rrze-pieksy-room-days-in-advance',
            'type' => 'text',
            'attributes' => array(
                'type' => 'number',
                'min' => '0',
            ),
            'default' => 7,
            'classes' => ['hide-check-only']
        ));

        $cmb_general->add_field(array(
            'name' => __('Automatic confirmation', 'rrze-pieksy'),
            'desc' => __('Incoming bookings do <u>not</u> need to be confirmed by the booking managers.', 'rrze-pieksy'),
            'id'   => 'rrze-pieksy-room-auto-confirmation',
            'type' => 'checkbox',
            'default' => true,
            'classes' => ['hide-check-only']
        ));

        // $cmb_general->add_field(array(
        //     'name' => __('Customer confirmation required', 'rrze-pieksy'),
        //     'desc' => __('The customer must confirm his reservation within one hour. Otherwise the system will cancel the booking.', 'rrze-pieksy'),
        //     'id'   => 'rrze-pieksy-room-force-to-confirm',
        //     'type' => 'checkbox',
        //     'default' => '',
        //     'classes' => ['hide-check-only']
        // ));

        $cmb_general->add_field(array(
            'name' => __('Checkout notification', 'rrze-pieksy'),
            'desc' => __('Send an email to the booking manager when customer has checked out.', 'rrze-pieksy'),
            'id'   => 'rrze-pieksy-room-checkout-notification',
            'type' => 'checkbox',
            'default' => '',
            'classes' => ['hide-no-check', 'hide-consultation']
        ));

        $cmb_general->add_field(array(
            'name' => __('Additional ICS email', 'rrze-pieksy'),
            'desc' => __('A copy of the confirmed booking will be sent to the specified email address with a calendar file (.ics) as an attachment.', 'rrze-pieksy'),
            'id'   => 'rrze-pieksy-room-send-to-email',
            'type' => 'text_email',
            'default' => '',
        ));

        $cmb_general->add_field(array(
            'name' => __('Allow Instant Check-In', 'rrze-pieksy'),
            'desc' => __('Seats can be booked and checked-in in one step. This only works if automatic confirmation activated!', 'rrze-pieksy'),
            'id'   => 'rrze-pieksy-room-instant-check-in',
            'type' => 'checkbox',
            'default' => '',
            'classes' => ['hide-no-check', 'hide-consultation', 'hide-check-only']
        ));

        $cmb_general->add_field(array(
            'name' => __('Check-in is required', 'rrze-pieksy'),
            'desc' => __('The customer must check-in their booking within a certain time from the start of the event. Otherwise the system will cancel the booking. The time allowed can be set just below in the "Allowed Check-In Time" section.', 'rrze-pieksy'),
            'id'   => 'rrze-pieksy-room-force-to-checkin',
            'type' => 'checkbox',
            'default' => '',
            'classes' => ['hide-no-check', 'hide-consultation', 'hide-check-only']
        ));

        // $defaultCheckInTime = $this->settings->getDefault('general', 'check-in-time');
        // $settingsCheckInTime = $this->settings->getOption('general', 'check-in-time', $defaultCheckInTime, true);
        // $cmb_general->add_field(array(
        //     'name' => __('Allowed Check-In Time (minutes)', 'rrze-pieksy'),
        //     'desc' => sprintf(__('You can specify an allowed check-in time for this room. If "Check-In required" is checked, the system will cancel the booking after this time. Default is %s minutes.', 'rrze-pieksy'), $settingsCheckInTime),
        //     'id'   => 'rrze-pieksy-room-check-in-time',
        //     'type' => 'text',
        //     'attributes' => array(
        //         'type' => 'number',
        //         'min' => '5',
        //     ),
        //     'default' => $settingsCheckInTime,
        //     'classes' => ['hide-no-check', 'hide-consultation', 'hide-check-only']
        // ));

        $cmb_general->add_field(array(
            'name' => __('Show notes/comment input in booking form', 'rrze-pieksy'),
            'desc' => __('If not checked, the comment text input will still be visible in the backend for booking admins for internal notes.', 'rrze-pieksy'),
            'id'   => 'rrze-pieksy-room-notes-check',
            'type' => 'checkbox',
            'default' => '',
        ));

        $cmb_general->add_field(array(
            'name' => __('Comment input label', 'rrze-pieksy'),
            'desc' => __("Choose a label for the text input on the booking form. E.g. 'Additional information' or 'Main interests'", 'rrze-pieksy'),
            'type' => 'text',
            'id'   => 'rrze-pieksy-room-notes-label',
            'default' => __('Additional information', 'rrze-pieksy'),
        ));

        $cmb_general->add_field(array(
            'name'    => __('Street', 'rrze-pieksy'),
            'id'      => 'rrze-pieksy-room-street',
            'type'    => 'text',
        ));

        $cmb_general->add_field(array(
            'name'    => __('ZIP', 'rrze-pieksy'),
            'id'      => 'rrze-pieksy-room-zip',
            'type'    => 'text',
        ));

        $cmb_general->add_field(array(
            'name'    => __('City', 'rrze-pieksy'),
            'id'      => 'rrze-pieksy-room-city',
            'type'    => 'text',
        ));

        $cmb_general->add_field(array(
            'name'    => __('Floor plan', 'rrze-pieksy'),
            'desc'    => 'Upload an image.',
            'id'      => 'rrze-pieksy-room-floorplan',
            'type'    => 'file',
            // Optional:
            'options' => array(
                'url' => false, // Hide the text input for the url
            ),
            //            'text'    => array(
            //                'add_upload_file_text' => 'Add File' // Change upload button text. Default: "Add or Upload File"
            //            ),
            // query_args are passed to wp.media's library query.
            'query_args' => array(
                //'type' => 'application/pdf', // Make library only display PDFs.
                // Or only allow gif, jpg, or png images
                'type' => array(
                    'image/gif',
                    'image/jpeg',
                    'image/png',
                ),
            ),
            'preview_size' => 'large', // Image size to use when previewing in the admin.
        ));
    }

    public function seat()
    {
        $cmb = new_cmb2_box(array(
            'id'            => 'rrze-pieksy-seat-details-meta',
            'title'         => __('Details', 'rrze-pieksy'),
            'object_types'  => array('seat',), // Post type
            'context'       => 'normal',
            'priority'      => 'high',
            'show_names'    => true, // Show field names on the left
            // 'cmb_styles' => false, // false to disable the CMB stylesheet
            // 'closed'     => true, // Keep the metabox closed by default
        ));

        $cmb->add_field(array(
            'name'             => __('Room', 'rrze-pieksy'),
            //'desc'             => 'Select an option',
            'id'               => 'rrze-pieksy-seat-room',
            'type'             => 'select',
            'show_option_none' => '&mdash; ' . __('Please select', 'rrze-pieksy') . ' &mdash;',
            'default'          => '-1',
            'options'          => $this->getPosts('room'),
            'attributes'  =>  [
                'required' => 'required',
            ],             
        ));
    }

    public function getSeats()
    {
        $seats = get_posts([
            'post_type' => 'seat',
            'post_statue' => 'publish',
            'nopaging' => true,
            'orderby' => 'title',
            'order' => 'ASC',
        ]);
        if (empty($seats)) {
            return [];
        }        
        $result = [];
        foreach ($seats as $seat) {
            $room = get_post_meta($seat->ID, 'rrze-pieksy-seat-room', true);
            $room_title = get_the_title($room);
            $result[$seat->ID] = $room_title . ' ??? ' . $seat->post_title;
        }
        natsort($result);
        return $result;
    }

    public static function getPosts(string $postType): array
    {
        $posts = get_posts([
            'post_type' => $postType,
            'post_statue' => 'publish',
            'nopaging' => true,
            'orderby' => 'title',
            'order' => 'ASC',
        ]);
        if (empty($posts)) {
            return [];
        }
        $result = [];
        foreach ($posts as $post) {
            $result[$post->ID] = $post->post_title;
        }
        return $result;
    }

    /**
     * Output a message if the current page has the id of "2" (the about page)
     * @param  object $field_args Current field args
     * @param  object $field      Current field object
     */
    public function cbDisplayTimeslot($field_args, $field ) {
        $start = get_post_meta($field->object_id, 'rrze-pieksy-booking-start', true);
        $end = get_post_meta($field->object_id, 'rrze-pieksy-booking-end', true);
        if ($start != '' && $end != '') {
            echo '<span class="display-timeslot">' . __('Time slot booked', 'rrze-pieksy') . ': ' . date_i18n(get_option('date_format'), $start) . ' // '
                . date('H:i', $start) . ' - ' . date('H:i', $end) . '</span>';
        }

    }
}
