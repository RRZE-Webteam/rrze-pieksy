<?php

namespace RRZE\Pieksy;

defined('ABSPATH') || exit;

use DateTime;

class Functions
{
    public static function dateFormat(int $timestamp): string
    {
        return date_i18n(get_option('date_format'), $timestamp);
    }

    public static function timeFormat(int $timestamp): string
    {
        return date_i18n(get_option('time_format'), $timestamp);
    }

    public static function validateDate(string $date, string $format = 'Y-m-d'): bool
    {
        $dt = DateTime::createFromFormat($format, $date);
        return $dt && $dt->format($format) === $date;
    }

    public static function validateTime(string $date, string $format = 'H:i:s'): bool
    {
        return self::validateDate($date, $format);
    }

    // The phone number must be empty or at least 3 digits long.
    public static function validatePhone(string $phone): bool
    {
        return (empty($phone) || preg_match_all('/\d/', $phone) >= 3);
    }

    public static function isLocaleEnglish()
    {
        $locale = get_locale();
        return (strpos($locale, 'en_') === 0);
    }

    public static function daysOfWeekAry(int $startKey = 0, int $startWd = 1, int $abbr = 0): array
    {
        global $wp_locale;
        $weekDays = [];
        for ($wdCount = 0; $wdCount <= 6; $wdCount++) {
            $wd = $wp_locale->get_weekday(($wdCount + $startWd) % 7);
            if ($abbr == 1) {
                $wd = $wp_locale->get_weekday_initial($wd);
            } elseif ($abbr == 2) {
                $wd = $wp_locale->get_weekday_abbrev($wd);
            }
            $weekDays[$wdCount + $startKey] = $wd;
        }
        return $weekDays;
    }

    /**
     * getOccupancyByRoomIdNextHTML
     * returns getOccupancyByRoomIdHTML() if there is an available seat for today; otherwise returns the next available timeslots 
     * @param int $room_id (the room's post id)
     * @return string
     */
    public static function getOccupancyByRoomIdNextHTML(int $room_id): string
    {
        $output = '';

        $duration = get_post_meta($room_id, 'rrze-pieksy-room-days-in-advance', true);
        $timestamp = current_time('timestamp');
        $today = date('Y-m-d', $timestamp);
        $start = $today;
        $end = date("Y-m-d", strtotime("+$duration day"));
        
        $aRoomAvailability = self::getRoomAvailability($room_id, $start, $end);

        if (!$aRoomAvailability){
            return '<span class="rrze-pieksy-occupancy-title">' . sprintf(__('This room has no available seat within %u days.', 'rrze-pieksy'), $duration) . '</span>';
        }

        if (isset($aRoomAvailability[$today])){
            // we have a seat for today
            $output = '<span class="rrze-pieksy-occupancy-title">' . __('Room occupancy for today', 'rrze-pieksy') . '</span>';
            $output .= self::getOccupancyByRoomIdHTML($room_id, true);
        } else {
            // return the next available timeslots
            $nextAvailableDay = array_key_first($aRoomAvailability);
            $countSeats = count(array_values($aRoomAvailability[$nextAvailableDay])[0]);
            $output = '<span class="rrze-pieksy-occupancy-title">' . __('This room has no available seat for today.', 'rrze-pieksy') . ' ' . _n('The next available seat is on', 'The next available seats are on', $countSeats, 'rrze-pieksy') . ' ' . self::dateFormat(strtotime($nextAvailableDay)) . '</span>';
            $output .= self::getOccupancyByRoomIdHTML($room_id, true, strtotime($nextAvailableDay) );
        }
        return $output;
    }


    /**
     * getOccupancyByRoomIdHTML
     * calls getOccupancyByRoomId and returns an HTML table with room's occupancy for given day (or today if no day is given)
     * @param int $room_id (the room's post id)
     * @param boolean $from_now (optional) : return timeslot-spans which end-time (H:i) is in the future 
     * @param int $timestamp (optional, the timestampt to check occupancies at, default: current_time('timestamp') )
     * @return string
     */
    public static function getOccupancyByRoomIdHTML(int $room_id, bool $from_now = NULL, int $timestamp = 0): string
    {
       

        $seats_slots = self::getOccupancyByRoomId($room_id, $from_now, $timestamp);

        if ($seats_slots){
	    $output = '<table class="pieksy-room-occupancy"><tr>';
            $output .= '<th>' . __( 'Seat', 'rrze-pieksy' ) . '</th>';
            foreach($seats_slots['room_slots'] as $room_slot){
                $output .= '<th scope="col"><span class="rrze-pieksy-timeslot">' . str_replace('-', ' - ', $room_slot) . '</span></th>';
            }
            $output .= '</tr>';
            $aRoomSlots = $seats_slots['room_slots'];
            unset($seats_slots['room_slots']);

            foreach($seats_slots as $seat_id => $aSlots){
                $output .= '<tr>';
                $output .= '<th scope="row">' . get_the_title( $seat_id ) . '</th>';
                foreach($aRoomSlots as $slot){
                    $class = ( $aSlots[$slot] ? 'available' : 'not-available' );
                    $output .= '<td><span class="'.$class.'">' . ( $aSlots[$slot] ? 'available' : 'not-available' ) . '</span></td>';
                }
                $output .= '</tr>';
            }
	    $output .= '</table>';
        }else{
            $output = '<div class="alert">' . __('This room has no seats for today.', 'rrze-pieksy') . '</div>';
        }
       

        return $output;
    }

    /**
     * getOccupancyByRoomId
     * Returns an array('room_slots' with all timeslot-spans for this room, seat_id => array(timeslot-span => true/false)) for given day (or today if no day is given)
     * Example: given room has 2 seats; 1 seat is not available at 09:30-10:30 
     *          returns: array(3) { ["room_slots"]=> array(3) { [0]=> string(13) "08:15 - 09:15" [1]=> string(13) "09:30 - 10:30" [2]=> string(13) "11:05 - 12:10" } [2244487]=> array(3) { ["08:15-09:15"]=> bool(true) ["09:30-10:30"]=> bool(true) ["11:05-12:10"]=> bool(true) } [1903]=> array(3) { ["08:15-09:15"]=> bool(true) ["09:30-10:30"]=> bool(false) ["11:05-12:10"]=> bool(true) } }
     * @param int $room_id (the room's post id)
     * @param boolean $from_now (optional) : return timeslot-spans which end-time (H:i) is in the future
     * @param int $timestamp (optional, the timestampt to check occupancies at, default: current_time('timestamp') )
     * @return array
     */
    public static function getOccupancyByRoomId(int $room_id, bool $from_now = NULL, int $timestamp = 0): array {
        $data = [];
        $timestamp = ( !$timestamp ? current_time('timestamp') : $timestamp );
        $thisDay = date('Y-m-d', $timestamp);
        $thisDay_weeknumber = date('N', $timestamp);

        // get timeslots for today for this room
        $slots = self::getRoomSchedule($room_id); // liefert [wochentag-nummer][startzeit] = end-zeit;
        $slots_thisDay_tmp = (isset($slots[$thisDay_weeknumber]) ? $slots[$thisDay_weeknumber] : []);

        $slots_thisDay = [];
        foreach($slots_thisDay_tmp as $start => $end){
            $end_timestamp = strtotime($thisDay . ' ' . $end);
            if ($from_now){
                if ($end_timestamp > $timestamp){
                    $slots_thisDay[] = $start . '-' . $end;
                    $data['room_slots'][] =  $start . '-' . $end;
                }
            } else {
                $slots_thisDay[] = $start . '-' . $end;
                $data['room_slots'][] =  $start . '-' . $end;
            }
        }

        // get seats for this room
        $seatIds = get_posts([
            'post_type' => 'seat',
            'post_status' => 'publish',
            'nopaging' => true,
            'meta_key' => 'rrze-pieksy-seat-room',
            'meta_value' => $room_id,
            // 'fields' => 'ids',
            'orderby'=> 'title', 
            'order' => 'ASC'
        ]);

        // sort by title naturally
        $seatSortedByTitle = [];
        foreach($seatIds as $seat){
            $seatSortedByTitle[$seat->ID] = $seat->post_title;
        }
        natsort($seatSortedByTitle);

        foreach ($seatSortedByTitle as $seat_id => $title){
            $slots_free = self::getSeatAvailability($seat_id, $thisDay, $thisDay);
            $slots_free_thisDay_tmp = ( isset($slots_free[$thisDay]) ? $slots_free[$thisDay] : [] );
            $slots_free_thisDay = array_combine($slots_free_thisDay_tmp, $slots_free_thisDay_tmp); // set values to keys

            foreach($slots_thisDay as $timespan){
                $data[$seat_id][$timespan] = (isset($slots_free_thisDay[$timespan])?true:false);
            }

        }
        return $data;
    }


    /**
     * getOccupancyByRoomIdHTMLAdmin
     * calls getOccupancyByRoomIdAdmin and returns an HTML table with room's occupancy for today 
     * Note: css-classes are identical to status: confirmed, checked-in, available
     * @param int $room_id (the room's post id)
     * @return string
     */
    public static function getOccupancyByRoomIdHTMLAdmin(int $room_id): string
    {
        

        $seats_slots = self::getOccupancyByRoomIdAdmin($room_id);

        if ($seats_slots){
	    $output = '<table class="pieksy-room-occupancy"><tr>';
            $output .= '<th>' . __( 'Seat', 'rrze-pieksy' ) . '</th>';
            foreach($seats_slots['room_slots'] as $room_slot){
                $output .= '<th scope="col"><span class="rrze-pieksy-timeslot">' . $room_slot . '</span></th>';
            }
            $output .= '</tr>';
            $aRoomSlots = $seats_slots['room_slots'];
            unset($seats_slots['room_slots']);

            foreach($seats_slots as $seat_id => $aSlots){
                $output .= '<tr>';
                $output .= '<th scope="row">' . get_the_title( $seat_id ) . '</th>';
                foreach($aRoomSlots as $slot){
                    $output .= '<td><span class="' . $aSlots[$slot] . '">' . $aSlots[$slot] . '</span></td>';
                }
                $output .= '</tr>';
            }
	    $output .= '</table>';
        }else{
	    $output = '<div class="alert">' . __('This room has no seats for today.', 'rrze-pieksy') . '</div>';
        }
        

        return $output;
    }


    /**
     * getOccupancyByRoomIdAdmin
     * Returns an array('room_slots' with all timeslot-spans for given room, post_id of seat => array(timeslot-span => 'confirmed', 'checked-in' or 'available )) for today
     * Example: given room has 3 seats; 1 seat is "confirmed" at 12:30-13:00 
     * array(4) { ["room_slots"]=> array(3) { [0]=> string(13) "11:00 - 12:15" [1]=> string(13) "12:30 - 13:00" [2]=> string(13) "12:45 - 15:00" } [2244521]=> array(3) { ["11:00-12:15"]=> string(9) "available" ["12:30-13:00"]=> string(9) "available" ["12:45-15:00"]=> string(9) "available" } [2244522]=> array(3) { ["12:30-13:00"]=> string(9) "confirmed" ["11:00-12:15"]=> string(9) "available" ["12:45-15:00"]=> string(9) "available" } [2244523]=> array(3) { ["11:00-12:15"]=> string(9) "available" ["12:30-13:00"]=> string(9) "available" ["12:45-15:00"]=> string(9) "available" } }
     * @param int $roomId (the room's post id)
     * @return array
     */
    public static function getOccupancyByRoomIdAdmin(int $roomId): array
    {
        $data = [];

        $timestamp = current_time('timestamp');
        $today = date('Y-m-d', $timestamp);
        $today_weeknumber = date('N', $timestamp);

        // get timeslots for today for this room
        $slots = self::getRoomSchedule($roomId); // liefert [wochentag-nummer][startzeit] = end-zeit;
        $slots_today_tmp = (isset($slots[$today_weeknumber]) ? $slots[$today_weeknumber] : []);
        $slots_today = [];
        foreach($slots_today_tmp as $start => $end){
            $slots_today[] = $start . ' - ' . $end;
            $data['room_slots'][] =  $start . ' - ' . $end;
        }

        // get seats for this room
        $aSeatIds = get_posts([
            'post_type' => 'seat',
            'post_status' => 'publish',
            'nopaging' => true,
            'meta_key' => 'rrze-pieksy-seat-room',
            'meta_value' => $roomId,
            'fields' => 'ids',
            'orderby'=> 'title', 
            'order' => 'ASC'
        ]);

        $end = date('Y-m-d H:i', strtotime($today . ' +23 hours, +59 minutes'));

        foreach ($aSeatIds as $seatId) {
            // get bookingIds for each seat that are confirmed oder checked-in
            $aBookingIds = get_posts([
                'post_type' => 'booking',
                'post_status' => 'publish',
                'fields' => 'ids',
                'meta_query' => [
                    [
                        'key' => 'rrze-pieksy-booking-seat',
                        'value'   => $seatId,
                    ],
                    [
                        'key' => 'rrze-pieksy-booking-status',
                        'value'   => ['confirmed', 'checked-in'],
                        'compare' => 'IN'
                    ],
                    [
                        'key'     => 'rrze-pieksy-booking-start',
                        'value' => array(strtotime($today), strtotime($end)),
                        'compare' => 'BETWEEN',
                        'type' => 'numeric'
                    ],
                ],
            ]);

            if ($aBookingIds){
                foreach ( $aBookingIds as $bookingId ){
                    // set booking-status as value of key "startTime-endTime"
                    $aBookingMeta = get_post_meta($bookingId);
                    $timespan = date('H:i', $aBookingMeta['rrze-pieksy-booking-start'][0]) . ' - ' . date('H:i', $aBookingMeta['rrze-pieksy-booking-end'][0]);
                    $data[$seatId][$timespan] = $aBookingMeta['rrze-pieksy-booking-status'][0];
                }
            }

            foreach($slots_today as $timespan){
                $data[$seatId][$timespan] = (isset($data[$seatId][$timespan])?$data[$seatId][$timespan]:'available');
            }
        }
        return $data;
    }

    public static function getOccupancyLinks(int $roomId): string
    {
        $url = esc_url(get_permalink($roomId));
        return '<span class="rrze-pieksy-occupancylinktitle">' . __('Links to display the current room occupancy', 'rrze-pieksy') . '</span>: <span class="rrze-pieksy-occupancylink"><a href="' . $url . '" target="_blank">' . __('Normal website', 'rrze-pieksy') . '</a></span> <span class="rrze-pieksy-occupancylink"><a href="' . $url . '?format=embedded&show=occupancy_nextavailable" target="_blank">' . __('Website for public displays', 'rrze-pieksy') . '</a></span>';
    }
    

    public static function getBooking(int $bookingId): array {
        $data = [];

        $post = get_post($bookingId);
        if (!$post) {
            return $data;
        }

        $data['id'] = $post->ID;
        $data['status'] = get_post_meta($post->ID, 'rrze-pieksy-booking-status', true);
        $data['start'] = absint(get_post_meta($post->ID, 'rrze-pieksy-booking-start', true));
        $start = new Carbon(date('Y-m-d H:i:s', $data['start']), wp_timezone());
        $end = absint(get_post_meta($post->ID, 'rrze-pieksy-booking-end', true));
        $data['end'] = $end ? $end : $start->endOfDay()->getTimestamp();
        $data['date'] = self::dateFormat((int)$data['start']);
        $data['time'] = self::timeFormat((int)$data['start']) . ' - ' . self::timeFormat((int)$data['end']);
        $data['date_en'] = date('F j, Y', $data['start']);
        $data['time_en'] = date('g:i a', $data['start']) . ' - ' . date('g:i a', $data['end']);

        $data['booking_date_timestamp'] = strtotime($post->post_date);
        $data['booking_date'] = date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($post->post_date));

        $data['seat'] = get_post_meta($post->ID, 'rrze-pieksy-booking-seat', true);
        $data['seat_name'] = !empty($data['seat']) ? get_the_title($data['seat']) : '';

        $data['room'] = get_post_meta($data['seat'], 'rrze-pieksy-seat-room', true);
        $data['room_name'] = get_the_title($data['room']);
        $data['room_street'] = get_post_meta($data['room'], 'rrze-pieksy-room-street', true);
        $data['room_zip'] = get_post_meta($data['room'], 'rrze-pieksy-room-zip', true);
        $data['room_city'] = get_post_meta($data['room'], 'rrze-pieksy-room-city', true);

        $data['notes'] = get_post_meta($post->ID, 'rrze-pieksy-booking-notes', true);

        $data['guest_firstname'] = Functions::crypt(get_post_meta($post->ID, 'rrze-pieksy-booking-guest-firstname', true), 'decrypt');
        $data['guest_lastname'] = Functions::crypt(get_post_meta($post->ID, 'rrze-pieksy-booking-guest-lastname', true), 'decrypt');
        $data['guest_email'] = Functions::crypt(get_post_meta($post->ID, 'rrze-pieksy-booking-guest-email', true), 'decrypt');
        $data['guest_phone'] = Functions::crypt(get_post_meta($post->ID, 'rrze-pieksy-booking-guest-phone', true), 'decrypt');

        $data['post_status'] = $post->post_status;

        return $data;
    }

	public static function isBookingArchived(int $postId): bool
	{
		$now = current_time('timestamp');
		$start = absint(get_post_meta($postId, 'rrze-pieksy-booking-start', true));
		$start = new Carbon(date('Y-m-d H:i:s', $start), wp_timezone());
		$end = absint(get_post_meta($postId, 'rrze-pieksy-booking-end', true));
		$end = $end ? $end : $start->endOfDay()->getTimestamp();
		return ($end < $now);
	}

    public static function canDeletePost(int $postId, string $postType): bool
    {
        switch ($postType) {
            case 'booking':
                return Functions::canDeleteBooking($postId);
            case 'room':
                return Functions::canDeleteRoom($postId);
            case 'seat':
                return Functions::canDeleteSeat($postId);
            default:
                return false;
        }        
    }

	public static function canDeleteBooking(int $postId): bool
	{
		$start = absint(get_post_meta($postId, 'rrze-pieksy-booking-start', true));
		$start = new Carbon(date('Y-m-d H:i:s', $start), wp_timezone());
		$status = get_post_meta($postId, 'rrze-pieksy-booking-status', true);
		if (
			self::isBookingArchived($postId)
			&& !(in_array($status, ['checked-in', 'checked-out']) || $start->endOfDay()->gt(new Carbon('now')))
		) {
			return true;
		} else {
			return false;
		}
    }
        
    public static function canDeleteSeat(int $postId): bool
    {
        $args = [
            'fields'            => 'ids',
            'post_type'         => 'booking',
            'post_status'       => 'publish',
            'nopaging'          => true,
            'meta_query'        => [
                [
                    'key'       => 'rrze-pieksy-booking-seat',
                    'value'     => $postId,
                    'compare'   => '='
                ]
            ]
        ];
        return empty(get_posts($args));
    }

    public static function canDeleteRoom(int $postId): bool
    {
        $seats = self::getAllRoomSeats($postId);
        if(empty($seats)) {
            return true;
        }
        $args = [
            'fields'            => 'ids',
            'post_type'         => 'booking',
            'post_status'       => 'publish',
            'nopaging'          => true,
            'meta_query'        => [
                [
                    'key'       => 'rrze-pieksy-booking-seat',
                    'value'     => $seats,
                    'compare'   => 'IN'
                ]
            ]
        ];
        return empty(get_posts($args));
    }

    public static function getAllRoomSeats(int $roomId): array
    {
        $args = [
            'fields'            => 'ids',
            'post_type'         => 'seat',
            'post_status'       => 'publish',
            'nopaging'          => true,
            'meta_query'        => [
                [
                    'key'       => 'rrze-pieksy-seat-room',
                    'value'     => $roomId,
                    'compare'   => '='
                ]
            ]
        ];
        return get_posts($args);
    }
        
    public static function bookingReplyUrl(string $action, string $password, int $id): string
    {
        $hash = self::crypt($password);
        return get_site_url() . "/pieksy-booking/?booking-reply=" . $hash . "&id=" . $id . "&action=" . $action;
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

    /**
     * getRoomAvailability
     * Returns an array of dates/timeslots/seats available, for a defined period.
     * Array structure: date => timeslot => seat IDs
     * @param string $room_id the room's post id
     * @param string $start start date of the period (format 'Y-m-d')
     * @param string $end end date of the period (format 'Y-m-d')
     * @param bool $showPast include timeslots that ended in the past?
     * @return array ['date(Y-m-d)']['timeslot(H:i-H:i)'] = [seat_id, seat_id...]
     */
    public static function getRoomAvailability($room_id, $start, $end, $showPast = true)
    {
        $availability = [];
        $room_availability = [];
        // Array aus verf??gbaren Timeslots des Raumes erstellen
        $slots = self::getRoomSchedule($room_id, true);
        $room_meta = get_post_meta($room_id);
        $days_blocked_raw = isset($room_meta['rrze-pieksy-room-days-closed']) ? $room_meta['rrze-pieksy-room-days-closed'][0] : '';
        $days_blocked = explode("\n", str_replace("\r", '', $days_blocked_raw));
        // Array aus bereits gebuchten Pl??tzen im Zeitraum erstellen
        $bookingMode = isset($room_meta['rrze-pieksy-room-bookingmode']) ? $room_meta['rrze-pieksy-room-bookingmode'][0] : '';
        $args_seats = ['post_type' => 'seat',
            'post_status' => 'publish',
            'meta_key' => 'rrze-pieksy-seat-room',
            'meta_value' => $room_id,
            'orderby' => 'date',
            'order' => 'ASC',];
        if ($bookingMode == 'consultation') {
            $args_seats['numberposts'] = 1;
        } else {
            $args_seats['nopaging'] = true;
        }
        $seats = get_posts($args_seats);

        $seat_ids = [];
        $seats_booked = [];
        if ($start == $end) {
            $end = date('Y-m-d H:i', strtotime($start . ' +23 hours, +59 minutes'));
        } else {
            $end = date('Y-m-d H:i', strtotime($end . ' +23 hours, +59 minutes'));
        }
        foreach ($seats as $seat) {
            $seat_ids[] = $seat->ID;
            $bookings = get_posts([
                'post_type' => 'booking',
                'post_status' => 'publish',
                'nopaging' => true,
                'meta_query' => [
                    [
                        'key' => 'rrze-pieksy-booking-seat',
                        'value'   => $seat->ID,
                    ],
                    [
                        'key' => 'rrze-pieksy-booking-status',
                        'value'   => ['confirmed', 'checked-in'],
                        'compare' => 'IN'
                    ],
                    [
                        'key'     => 'rrze-pieksy-booking-start',
                        'value' => array(strtotime($start), strtotime($end)),
                        'compare' => 'BETWEEN',
                        'type' => 'numeric'
                    ],
                ],
            ]);
            foreach ($bookings as $booking) {
                $booking_meta = get_post_meta($booking->ID);
                $booking_start = $booking_meta['rrze-pieksy-booking-start'][0];
                //$seats_booked[$seat->ID][date('Y-m-d', $booking_date)] = $booking_time;
                $seats_booked[$booking_start][] = $seat->ID;
            }
        }

        // Tageweise durch den Zeitraum loopen, um die Verf??gbarkeit je Wochentag zu ermitteln
        $loopstart = strtotime($start);
        $loopend = strtotime($end);
        while ($loopstart <= $loopend) {
            $weekday = date('w', $loopstart);
            $dateday = date('Y-m-d', $loopstart);
            if ($weekday == '0') $weekday = '7';
            if (isset($slots[$weekday]) && !in_array($dateday, $days_blocked)) {
                foreach ($slots[$weekday] as $valid => $slot_infos) {
                    $valid_array = explode('-', $valid);
                    $valid_from = $valid_array[0];
                    $valid_to = $valid_array[1];
                    $time_parts = explode(':', $slot_infos['start']);
                    if (($valid_from != 'unlimited' && $valid_to != 'unlimited' && $loopstart >= $valid_from && $loopstart <= $valid_to)
                        || ($valid_from != 'unlimited' && $valid_to == 'unlimited' && $loopstart >= $valid_from)
                        || ($valid_from == 'unlimited' && $valid_to != 'unlimited' && $loopstart <= $valid_to)
                        || ($valid_from == 'unlimited' && $valid_to == 'unlimited')) {
                        $room_availability[strtotime('+' . intval($time_parts[0]) . ' hours, + ' . $time_parts[1] . ' minutes', $loopstart)] = $seat_ids;
                    }
                }
            }
            $loopstart = strtotime("+1 day", $loopstart);
        }

        // Bereits gebuchte Pl??tze aus Array $room_availability entfernen
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
        ksort($room_availability);

        // F??r Kalender aus Array-Ebene Timestamp zwei Ebenen (Tag / Zeit) machen
        foreach ($room_availability as $timestamp => $v) {
            $weekday = (date('w', $timestamp));
            if ($weekday == '0') $weekday = '7';
            $start = date('H:i', $timestamp);
            foreach ($slots[$weekday] as $valid => $data) {
                $valid_array = explode('-', $valid);
                $valid_from = $valid_array[0];
                $valid_to = $valid_array[1] == 'unlimited' ? 'unlimited' : strtotime('+23 hours, +59 minutes', intval($valid_array[1]));
                if (($valid_from != 'unlimited' && $valid_to != 'unlimited' && $timestamp >= $valid_from && $timestamp <= $valid_to)
                    || ($valid_from != 'unlimited' && $valid_to == 'unlimited' && $timestamp >= $valid_from)
                    || ($valid_from == 'unlimited' && $valid_to != 'unlimited' && $timestamp <= $valid_to)
                    || ($valid_from == 'unlimited' && $valid_to == 'unlimited')) {
                    if ($data['start'] == $start) {
                        $end = $data['end'];
                    }
                }
            }
            // remove past timeslots from today if needed
            if ($showPast == false) {
                $endTimestamp = strtotime(date('Y-m-d', $timestamp). ' ' . $end);
                if ($endTimestamp <= current_time('timestamp')) {
                    continue;
                }
            }
            $availability[date('Y-m-d', $timestamp)][$start . '-' . $end] = $v;
        }
        return $availability;
    }

    /**
     * getSeatAvailability
     * Returns an array of dates/timeslots where the seat is available, for a defined period.
     * Array structure: date => timeslot
     * @param string $room the seat's post id
     * @param string $start start date of the period (format 'Y-m-d')
     * @param string $end end date of the period (format 'Y-m-d')
     * @param bool $showPast include timeslots that ended in the past?
     * @return array ['date(Y-m-d)'] => ['start(H:i) - end(H:i)', 'start(H:i) - end(H:i)'...]
     */
    public static function getSeatAvailability($seat, $start, $end, $showPast = true)
    {
        $availability = [];
        $seat_availability = [];
        $timeslots_booked = [];

        // Array aus verf??gbaren Timeslots des Raumes erstellen
        $room_id = get_post_meta($seat, 'rrze-pieksy-seat-room', true);
        $slots = self::getRoomSchedule($room_id, true);
        $days_blocked_raw = get_post_meta($room_id, 'rrze-pieksy-room-days-closed', true);
        $days_blocked = explode("\n", str_replace("\r", '', $days_blocked_raw));
        // Array aus bereits gebuchten Pl??tzen im Zeitraum erstellen
        if ($start == $end) {
            $end = date('Y-m-d H:i', strtotime($start . ' +23 hours, +59 minutes'));
        }
        $bookings = get_posts([
            'post_type' => 'booking',
            'post_status' => 'publish',
            'nopaging' => true,
            'meta_query' => [
                'relation' => 'AND',
                [
                    'key' => 'rrze-pieksy-booking-seat',
                    'value'   => $seat,
                ],
                [
                    'key' => 'rrze-pieksy-booking-status',
                    'value'   => ['booked', 'confirmed', 'checked-in'],
                    'compare' => 'IN'
                ],
                [
                    'key'     => 'rrze-pieksy-booking-start',
                    'value' => array(strtotime($start), strtotime($end)),
                    'compare' => 'BETWEEN',
                    'type' => 'numeric'
                ],
            ],
        ]);

        foreach ($bookings as $booking) {
            $booking_meta = get_post_meta($booking->ID);
            $booking_start = $booking_meta['rrze-pieksy-booking-start'][0];
            $timeslots_booked[] = $booking_start;
        }

        // Tageweise durch den Zeitraum loopen, um die Verf??gbarkeit je Wochentag zu ermitteln
        $loopstart = strtotime($start);
        $loopend = strtotime($end);
        while ($loopstart <= $loopend) {
            $weekday = date('N', $loopstart);
            $dateday = date('Y-m-d', $loopstart);
            if (isset($slots[$weekday]) && !in_array($dateday, $days_blocked)) {
                foreach ($slots[$weekday] as $valid  => $slot_infos) {
                    $valid_array = explode('-', $valid);
                    $valid_from = $valid_array[0];
                    $valid_to = $valid_array[1];
                    $starttime = $slot_infos['start'];
                    $start_parts = explode(':', $starttime);
                    $end_parts = explode(':', $slot_infos['end']);
                    $timestamp = strtotime('+' . $start_parts[0] . ' hours, + ' . $start_parts[1] . ' minutes', $loopstart);
                    $timestamp_end = strtotime('+' . $end_parts[0] . ' hours, + ' . $end_parts[1] . ' minutes', $loopstart);
                    if (!in_array($timestamp, $timeslots_booked)) {
                        if (($valid_from != 'unlimited' && $valid_to != 'unlimited' && $loopstart >= $valid_from && $loopstart <= $valid_to)
                            || ($valid_from != 'unlimited' && $valid_to == 'unlimited' && $loopstart >= $valid_from)
                            || ($valid_from == 'unlimited' && $valid_to != 'unlimited' && $loopstart <= $valid_to)
                            || ($valid_from == 'unlimited' && $valid_to == 'unlimited')) {
                            $seat_availability[$timestamp] = $timestamp_end;
                        }
                    }
                }
            }
            $loopstart = strtotime("+1 day", $loopstart);
        }
        ksort($seat_availability);

        // F??r Ausgabe Timestamp zwei Ebenen (Tag / Zeit) machen
        foreach ($seat_availability as $timestamp => $timestamp_end) {
            if ($showPast == false && $timestamp_end <= current_time('timestamp')) {
                continue;
            }
            $availability[date('Y-m-d', $timestamp)][] = date('H:i', $timestamp) . '-' . date('H:i', $timestamp_end);
        }

        return $availability;
    }

    /**
     * getPagesDropdownOptions
     * Returns an array of post_id => post_title that can be used by settings select callback.
     * Reduced version of wp_dropdown_pages()
     * @param array $args
     * @return array page_id => page_title
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

    /**
     * getRoomSchedule
     * Returns an array of timeslots per weekday for a specific room.
     * @param int $room_id
     * @param boolean $with_duration = false
     * @return array [weekday_number(1-7)][starttime(H:i)] => endtime(H:i) [if $with_duration == false]
     * @return array [weekday_number(1-7)][valid_from-valid_to][start] => starttime(H:i) / [end] => endtime(H:i) [if $with_duration == true]
     */
    public static function getRoomSchedule($room_id, $with_duration = false)
    {
        $schedule = [];
        $room_timeslots = get_post_meta($room_id, 'rrze-pieksy-room-timeslots', true);
        if (is_array($room_timeslots)) {
            foreach ($room_timeslots as $week) {
                if (isset($week['rrze-pieksy-room-weekday'])) {
                    foreach ($week[ 'rrze-pieksy-room-weekday' ] as $day) {
                        if (isset($week[ 'rrze-pieksy-room-starttime' ]) && isset($week[ 'rrze-pieksy-room-endtime' ])) {
                            $valid_from = ((isset($week[ 'rrze-pieksy-room-timeslot-valid-from' ]) && $week[ 'rrze-pieksy-room-timeslot-valid-from' ] != '') ? $week[ 'rrze-pieksy-room-timeslot-valid-from' ] : 'unlimited');
                            $valid_to   = ((isset($week[ 'rrze-pieksy-room-timeslot-valid-to' ]) && $week[ 'rrze-pieksy-room-timeslot-valid-to' ] != '') ? strtotime(
                                '+23 hours, +59 minutes',
                                intval($week[ 'rrze-pieksy-room-timeslot-valid-to' ])
                            ) : 'unlimited');
                            if ($with_duration == true) {
                                $schedule[ $day ][ $valid_from . '-' . $valid_to . '-' . $week[ 'rrze-pieksy-room-starttime' ] . '-' . $week[ 'rrze-pieksy-room-endtime' ] ][ 'start' ] = $week[ 'rrze-pieksy-room-starttime' ];
                                $schedule[ $day ][ $valid_from . '-' . $valid_to . '-' . $week[ 'rrze-pieksy-room-starttime' ] . '-' . $week[ 'rrze-pieksy-room-endtime' ] ][ 'end' ]   = $week[ 'rrze-pieksy-room-endtime' ];
                            } else {
                                $now = current_time('timestamp');
                                if (($valid_from != 'unlimited' && $valid_to != 'unlimited' && $now >= $valid_from && $now <= $valid_to)
                                    || ($valid_from != 'unlimited' && $valid_to == 'unlimited' && $now >= $valid_from)
                                    || ($valid_from == 'unlimited' && $valid_to != 'unlimited' && $now <= $valid_to)
                                    || ($valid_from == 'unlimited' && $valid_to == 'unlimited')) {
                                    $schedule[ $day ][ $week[ 'rrze-pieksy-room-starttime' ] ] = $week[ 'rrze-pieksy-room-endtime' ];
                                }
                            }
                        }
                    }
                }
            }
        }
        foreach ($schedule as $k => &$v) {
            ksort($v);
        }
        return $schedule;
    }

    /**
     * getSelectHTML
     * Returns HTML <select ...><option ...>...
     * @param string $sSelect : the id and name of <select
     * @param string $sAll : the description of option  0 (f.e. --- all seats ---)
     * @param array $aOptions : assoc array with options' values => descriptions
     * @param string $sSelected : value of selected option (optional)
     * @return string
     */
    public static function getSelectHTML(string $sSelect, string $sAll, array $aOptions, string $sSelected = ''): string
    {
        $output = '<select id="' . $sSelect . '" name="' . $sSelect . '">';
        $output .= '<option value="0">' . $sAll . ' </option>';
        foreach ($aOptions as $val => $desc){
            $sel = ($val == $sSelected ? ' selected="selected"' : '');
            $output .= '<option value="' . $val . '"' . $sel . '>' . $desc . ' </option>';
        }
        $output .= '</select>';
        return $output;
    }

    /**
     * sortArrayKeepKeys
     * Returns sorted assoc array but keeps the keys
     * @param array $aInput : assoc array
     * @return no return but $aInput is passed by reference
     */
    public static function sortArrayKeepKeys(array &$aInput)
    {
        uasort($aInput, function ($a, $b) {
            if ($a == $b) { return 0;}
            return ($a < $b) ? -1 : 1;
        });

    }
 
    public static function getBoolValueFromAtt($att): bool
    {
        $att = (string) $att;
        $filter = preg_replace('/[^a-z0-9]/', '', strtolower($att));
        return (in_array($filter, ['1', 'on', 'true', 'wahr', 'aktiv', 'show', 'yes']));
    }
    
    public static function getQueryStr(array $add = [], array $remove = []): string
    {
        if (empty($_SERVER['QUERY_STRING']) && strpos($_SERVER['REQUEST_URI'], '?') === false) {
            return '';
        }
        $queryStr = $_SERVER['QUERY_STRING'];
        parse_str($queryStr, $queryAry);
        $queryAry = array_diff_key(array_merge($queryAry, $add), array_fill_keys($remove, ''));
        return http_build_query($queryAry);
    }

    public static function isNotUniqueBooking(&$roomID, &$customer_email){
        // ??berpr??fen ob bereits eine Buchung mit gleicher E-Mail-Adresse zu diesem Raum vorliegt
        // get seats for this room
        $seatIDs = get_posts([
            'post_type' => 'seat',
            'post_status' => 'publish',
            'nopaging' => true,
            'meta_key' => 'rrze-pieksy-seat-room',
            'meta_value' => $roomID,
            'fields' => 'ids',
            'orderby'=> 'title', 
            'order' => 'ASC'
        ]);

        $check_args = [
            'post_type' => 'booking',
            'meta_query' => [
                'relation' => 'AND',
                [
                    'key' => 'rrze-pieksy-booking-guest-email',
                    'value' => Functions::crypt($customer_email, 'encrypt')
                ],
                [
                    'key' => 'rrze-pieksy-booking-status',
                    'value' => ['booked', 'customer-confirmed', 'confirmed', 'checked-in'],
                    'compare' => 'IN',
                ],
                [
                    'key' => 'rrze-pieksy-booking-seat',
                    'value' => $seatIDs,
                    'compare' => 'IN',
                ],
            ],
            'nopaging' => true,
        ];
        $check_bookings = get_posts($check_args);

        if (!empty($check_bookings)) {
            return FALSE;
        }else{
            return TRUE;
        }
    }

}
