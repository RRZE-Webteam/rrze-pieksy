<?php

namespace RRZE\RSVP;

defined('ABSPATH') || exit;

class Functions
{
    public static function actionUrl($atts = [])
    {
        $atts = array_merge(
            [
                'page' => 'rrze-rsvp'
            ],
            $atts
        );
        if (isset($atts['action'])) {
            switch ($atts['action']) {
                case 'add':
                    $atts['_wpnonce'] = wp_create_nonce('add');
                    break;
                case 'edit':
                    $atts['_wpnonce'] = wp_create_nonce('edit');
                    break;
                case 'delete':
                    $atts['_wpnonce'] = wp_create_nonce('delete');
                    break;
                default:
                    break;
            }
        }
        return add_query_arg($atts, get_admin_url(null, 'admin.php'));
    }

    public static function requestVar($param, $default = '')
    {
        if (isset($_POST[$param])) {
            return $_POST[$param];
        }

        if (isset($_GET[$param])) {
            return $_GET[$param];
        }

        return $default;
    }

    public static function dateFormat(int $timestamp): string
    {
        return date_i18n(get_option('date_format'), $timestamp);
    }

    public static function timeFormat(int $timestamp): string
    {
        return date_i18n(get_option('time_format'), $timestamp);
    }

    public static function isLocaleEnglish()
    {
        $locale = get_locale();
        return (strpos($locale, 'en_') === 0);
    }

    public static function hasShortcodeSSO(string $shortcode): bool
    {
        global $post;
        if (is_a($post, '\WP_Post') && has_shortcode($post->post_content, $shortcode)) {
            $result = [];
            $pattern = get_shortcode_regex();
            if (preg_match_all('/' . $pattern . '/s', $post->post_content, $matches)) {
                $keys = [];
                $result = [];
                foreach ($matches[0] as $key => $value) {
                    $get = str_replace(" ", "&", $matches[3][$key]);
                    parse_str($get, $output);
                    $keys = array_unique(array_merge($keys, array_keys($output)));
                    $result[][$matches[2][$key]] = $output;
                }
            }
            foreach ($result as $key => $value) {
                if (isset($value[$shortcode]) && !empty($value[$shortcode]['sso'])) {
                    return true;
                }
            }
        }
        return false;
    }

    public static function getBooking(int $bookingId): array
    {
        $data = [];

        $post = get_post($bookingId);
        if (!$post) {
            return $data;
        }

        $data['id'] = $post->ID;
        $data['status'] = get_post_meta($post->ID, 'rrze-rsvp-booking-status', true);
        $data['start'] = absint(get_post_meta($post->ID, 'rrze-rsvp-booking-start', true));
        $start = new Carbon(date('Y-m-d H:i:s', $data['start']), wp_timezone());
        $end = absint(get_post_meta($post->ID, 'rrze-rsvp-booking-end', true));
        $data['end'] = $end ? $end : $start->endOfDay()->getTimestamp();
        $data['date'] = Functions::dateFormat($data['start']);
        $data['time'] = Functions::timeFormat($data['start']) . ' - ' . Functions::timeFormat($data['end']);
        $data['date_en'] = date('F j, Y', $data['start']);
        $data['time_en'] = date('g:i a', $data['start']) . ' - ' . date('g:i a', $data['end']);

        $data['booking_date'] = date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($post->post_date));

        $data['seat'] = get_post_meta($post->ID, 'rrze-rsvp-booking-seat', true);
        $data['seat_name'] = !empty($data['seat']) ? get_the_title($data['seat']) : '';

        $data['room'] = get_post_meta($data['seat'], 'rrze-rsvp-seat-room', true);
        $data['room_name'] = get_the_title($data['room']);

        $data['notes'] = get_post_meta($post->ID, 'rrze-rsvp-booking-notes', true);

        $data['guest_firstname'] = get_post_meta($post->ID, 'rrze-rsvp-booking-guest-firstname', true);
        $data['guest_lastname'] = get_post_meta($post->ID, 'rrze-rsvp-booking-guest-lastname', true);
        $data['guest_email'] = get_post_meta($post->ID, 'rrze-rsvp-booking-guest-email', true);
        $data['guest_phone'] = get_post_meta($post->ID, 'rrze-rsvp-booking-guest-phone', true);

        return $data;
    }

    public static function bookingReplyUrl(string $action, string $password, int $id): string
    {
        //$hash = password_hash($password, PASSWORD_DEFAULT);
        $hash = self::crypt($password);
        return get_site_url() . "/?rrze-rsvp-booking-reply=" . $hash . "&id=" . $id . "&action=" . $action;
    }

    public static function crypt(string $string, string $action = 'encrypt')
    {
        $secretKey = AUTH_KEY;
        $secretSalt = AUTH_SALT;

        $output = false;
        $encryptMethod = 'AES-256-CBC';
        $key = hash('sha256', $secretKey);
        $salt = substr(hash('sha256', $secretSalt), 0, 16);

        if ($action == 'encrypt') {
            $output = base64_encode(openssl_encrypt($string, $encryptMethod, $key, 0, $salt));
        } else if ($action == 'decrypt') {
            $output = openssl_decrypt(base64_decode($string), $encryptMethod, $key, 0, $salt);
        }

        return $output;
    }

    public static function decrypt(string $string)
    {
        return self::crypt($string, 'decrypt');
    }

    public static function getRoomAvailability($room_id, $start, $end)
    {
        $availability = [];
        // Array aus verfügbaren Timeslots des Raumes erstellen
        $timeslots = get_post_meta($room_id, 'rrze-rsvp-room-timeslots', true);
        $slots = self::getRoomSchedule($room_id);
        // Array aus bereits gebuchten Plätzen im Zeitraum erstellen
        $seats = get_posts([
            'post_type' => 'seat',
            'post_status' => 'publish',
            'nopaging' => true,
            'meta_key' => 'rrze-rsvp-seat-room',
            'meta_value' => $room_id,
        ]);
        $seat_ids = [];
        $seats_booked = [];
        foreach ($seats as $seat) {
            $seat_ids[] = $seat->ID;
            $bookings = get_posts([
                'post_type' => 'booking',
                'post_status' => 'publish',
                'nopaging' => true,
                'meta_query' => [
                    [
                        'key' => 'rrze-rsvp-booking-seat',
                        'value'   => $seat->ID,
                    ],
                    [
                        'key' => 'rrze-rsvp-booking-status',
                        'value'   => ['confirmed', 'checked-in'],
                        'compare' => 'IN'
                    ],
                    [
                        'key'     => 'rrze-rsvp-booking-start',
                        'value' => array(strtotime($start), strtotime($end)),
                        'compare' => 'BETWEEN',
                        'type' => 'numeric'
                    ],
                ],
            ]);
            foreach ($bookings as $booking) {
                $booking_meta = get_post_meta($booking->ID);
                $booking_start = $booking_meta['rrze-rsvp-booking-start'][0];
                //$seats_booked[$seat->ID][date('Y-m-d', $booking_date)] = $booking_time;
                $seats_booked[$booking_start][] = $seat->ID;
            }
        }

        // Tageweise durch den Zeitraum loopen, um die Verfügbarkeit je Wochentag zu ermitteln
        $loopstart = strtotime($start);
        $loopend = strtotime($end);
        while ($loopstart <= $loopend) {
            $weekday = date('w', $loopstart);
            if (isset($slots[$weekday])) {
                foreach ($slots[$weekday] as $time => $endtime) {
                    $hours = explode(':', $time)[0];
                    $room_availability[strtotime('+' . $hours . ' hours', $loopstart)] = $seat_ids;
                }
            }
            $loopstart = strtotime("+1 day", $loopstart);
        }

        // Bereits gebuchte Plätze aus Array $room_availability entfernen
        foreach ($seats_booked as $timestamp => $v) {
            foreach ($v as $k => $seat_booked) {
                if (isset($room_availability[$timestamp])) {
                    $key = array_search($seat_booked, $room_availability[$timestamp]);
                    if ($key !== false) {
                        unset($room_availability[$timestamp][$key]);
                    }
                    if (empty($room_availability[$timestamp])) {
                        unset($room_availability[$timestamp]);
                    }
                }
            }
        }

        // Für Kalender aus Array-Ebene Timestamp zwei Ebenen (Tag / Zeit) machen
        foreach ($room_availability as $timestamp => $v) {
            $weekday = (date('w', $timestamp));
            $start = date('H:i', $timestamp);
            $end = $slots[$weekday][$start];

            $availability[date('Y-m-d', $timestamp)][$start . '-' . $end] = $v;
        }

        return $availability;
    }

    /**
     * getSeatAvailability
     * Returns an array of dates/timeslots where the seat is available, for a defined period.
     * Array structure: date => timeslot
     * @param string $room the room's post id
     * @param string $start start date of the period (format 'Y-m-d')
     * @param string $end end date of the period (format 'Y-m-d')
     * @return array
     */
    public static function getSeatAvailability($seat, $start, $end)
    {
        $availability = [];
        $seat_availability = [];
        $timeslots_booked = [];

        // Array aus verfügbaren Timeslots des Raumes erstellen
        $room_id = get_post_meta($seat, 'rrze-rsvp-seat-room', true);
        $slots = self::getRoomSchedule($room_id);
        // Array aus bereits gebuchten Plätzen im Zeitraum erstellen
        $bookings = get_posts([
            'post_type' => 'booking',
            'post_status' => 'publish',
            'nopaging' => true,
            'meta_query' => [
                [
                    'key' => 'rrze-rsvp-booking-seat',
                    'value'   => $seat,
                ],
                [
                    'key' => 'rrze-rsvp-booking-status',
                    'value'   => ['confirmed', 'checked-in'],
                    'compare' => 'IN'
                ],
                [
                    'key'     => 'rrze-rsvp-booking-start',
                    'value' => array(strtotime($start), strtotime($end)),
                    'compare' => 'BETWEEN',
                    'type' => 'numeric'
                ],
            ],
        ]);

        foreach ($bookings as $booking) {
            $booking_meta = get_post_meta($booking->ID);
            $booking_start = $booking_meta['rrze-rsvp-booking-start'][0];
            $timeslots_booked[] = $booking_start;
        }

        // Tageweise durch den Zeitraum loopen, um die Verfügbarkeit je Wochentag zu ermitteln
        $loopstart = strtotime($start);
        $loopend = strtotime($end);
        while ($loopstart <= $loopend) {
            $weekday = date('w', $loopstart);
            if (isset($slots[$weekday])) {
                foreach ($slots[$weekday] as $starttime  => $endtime) {
                    $hours_start = explode(':', $starttime)[0];
                    $hours_end = explode(':', $endtime)[0];
                    $timestamp = strtotime('+' . $hours_start . ' hours', $loopstart);
                    $timestamp_end = strtotime('+' . $hours_end . ' hours', $loopstart);
                    if (!in_array($timestamp, $timeslots_booked)) {
                        $seat_availability[$timestamp] = $timestamp_end;
                    }
                }
            }
            $loopstart = strtotime("+1 day", $loopstart);
        }

        // Für Ausgabe Timestamp zwei Ebenen (Tag / Zeit) machen
        foreach ($seat_availability as $timestamp => $timestamp_end) {
            $availability[date('Y-m-d', $timestamp)][] = date('H:i', $timestamp) . '-' . date('H:i', $timestamp_end);
        }

        return $availability;
    }

    /**
     * getPagesDropdownOptions
     * Returns an array of post_id => post_title that can be used by settings select callback.
     * Reduced version of wp_dropdown_pages()
     * @param array $args
     * @return array
     */
    public static function getPagesDropdownOptions($args = '')
    {
        $defaults = array(
            'depth' => 0,
            'child_of' => 0,
            'show_option_none' => '',
            'show_option_no_change' => '',
            'option_none_value' => '',
            'sort_column' => 'post_title',
        );
        $parsed_args = wp_parse_args($args, $defaults);
        $pages = get_pages($parsed_args);

        $output = [];
        if (!empty($pages)) {
            if ($parsed_args['show_option_no_change']) {
                $output['-1'] = $parsed_args['show_option_no_change'];
            }
            if ($parsed_args['show_option_none']) {
                $output[esc_attr($parsed_args['option_none_value'])] = $parsed_args['show_option_none'];
            }
            foreach ($pages as $page) {
                $output[$page->ID] = $page->post_title;
            }
        }
        return $output;
    }

    public static function getRoomSchedule($room_id)
    {
        $schedule = [];
        $room_timeslots = get_post_meta($room_id, 'rrze-rsvp-room-timeslots', true);
        foreach ($room_timeslots as $week) {
            foreach ($week['rrze-rsvp-room-weekday'] as $day) {
                $schedule[$day][$week['rrze-rsvp-room-starttime']] = $week['rrze-rsvp-room-endtime'];
            }
        }
        return $schedule;
    }
}
