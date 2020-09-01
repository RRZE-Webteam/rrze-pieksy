<?php

namespace RRZE\RSVP;

defined('ABSPATH') || exit;

use function RRZE\RSVP\plugin;

use WP_Query;

$idm = new Idm;

$settings = new Settings(plugin()->getFile());
$options = (object) $settings->getOptions();

global $post;
$postId = $post->ID;

$checkInBooking = null;
$seatCheckInOut = null;
$action = null;

if (isset($_GET['id']) && isset($_GET['nonce']) & wp_verify_nonce($_GET['nonce'], 'rrze-rsvp-checkin-booked')) {
    $bookingId = absint($_GET['id']);
    $checkInBooking = Functions::getBooking($bookingId);
} elseif (isset($_GET['id']) && isset($_GET['action']) && isset($_GET['nonce']) & wp_verify_nonce($_GET['nonce'], 'rrze-rsvp-seat-check-inout')) {
    $bookingId = absint($_GET['id']);
    $action = sanitize_text_field($_GET['action']);
    if ($seatCheckInOut = Functions::getBooking($bookingId)) {
        $room = $seatCheckInOut['room'];
        $customerEmail = $seatCheckInOut['guest_email'];
        $ssoRequired = Functions::getBoolValueFromAtt(get_post_meta($room, 'rrze-rsvp-room-sso-required', true));
        if (!$ssoRequired || !($idm->simplesamlAuth() && $idm->simplesamlAuth->isAuthenticated())) {
            $action = 'no-auth';
        } else {
            $idm->setAttributes();
            $customerData = $idm->getCustomerData();
            if ($customerEmail  != $customerData['customer_email']) {
                $action = 'no-auth';
            }
        }
    }
}

get_header();

if (Helper::isFauTheme()) {
    get_template_part('template-parts/hero', 'small');
    $div_open = '<div id="content">
        <div class="container">
            <div class="row">
                <div class="col-xs-12">
                    <main id="droppoint">
                        <h1 class="screen-reader-text">' . get_the_title() . '</h1>
                        <div class="inline-box">
                            <div class="content-inline">';
    $div_close = '</div>
                        </div>
                    </main>
                </div>
            </div>
        </div>
    </div>';
} else {
    $div_open = '<div id="content">
        <div class="container">
            <div class="row">
                <div class="col-xs-12">
                <h1 class="entry-title">' . get_the_title() . '</h1>';
    $div_close = '</div>
            </div>
        </div>
    </div>';
}

echo $div_open;

$nonce = wp_create_nonce('rrze-rsvp-seat-check-inout');

if ($checkInBooking) {
    $room = $checkInBooking['room'];
    $roomName = $checkInBooking['room_name'];
    $seatName = $checkInBooking['seat_name'];
    $customEmail = $checkInBooking['guest_email'];
    $date = $checkInBooking['date'];
    $time = $checkInBooking['time'];
    echo '<p><strong>' . __('Room', 'rrze-rsvp') . ':</strong> <a href="' . get_permalink($room) . '">' . $roomName . '</a>';
    echo '<div class="rrze-rsvp-seat-check-inout"> <div class="container">';
    echo '<h2>' . __('Booking Checked In', 'rrze-rsvp') . '</h2>';
    echo '<p>', __('<strong>This seat has been reserved for you.</strong>', 'rrze-rsvp') . '</p>';
    echo '<p>' . sprintf(__('Additional information has been sent to your email address <strong>%s</strong>.', 'rrze-rsvp'), $customEmail) . '</p>';
    echo '<p>' . __('Please check out when you leave the site.', 'rrze-rsvp') . '</p>';
    echo '<p class="date">';
    echo $date . '<br>';
    echo $time;
    echo '</p>';
    echo '<p>' . $roomName . '</p>';
    echo '<p>' . $seatName . '</p>';
    $link = sprintf(
        '<a href="%1$s?id=%2$d&action=checkout&nonce=%3$s" class="button button-checkout" data-id="%2$d">%4$s</a>',
        trailingslashit(get_permalink()),
        $bookingId,
        $nonce,
        __('Check out', 'rrze-rsvp')
    );             
    echo '<p>' . $link . '</p>';    
    echo '</div> </div>';
} elseif ($seatCheckInOut) {
    $room = $seatCheckInOut['room'];
    $roomName = $seatCheckInOut['room_name'];
    $seatName = $seatCheckInOut['seat_name'];
    $date = $seatCheckInOut['date'];
    $time = $seatCheckInOut['time'];      
    echo '<p><strong>' . __('Room', 'rrze-rsvp') . ':</strong> <a href="' . get_permalink($room) . '">' . $roomName . '</a>';
    echo '<div class="rrze-rsvp-seat-check-inout"> <div class="container">';
    switch ($action) {
        case 'checkin':
            update_post_meta($bookingId, 'rrze-rsvp-booking-status', 'checked-in');
            do_action('rrze-rsvp-checked-in', get_current_blog_id(), $bookingId);
            $link = sprintf(
                '<a href="%1$s?id=%2$d&action=checkout&nonce=%3$s" class="button button-checkout" data-id="%2$d">%4$s</a>',
                trailingslashit(get_permalink()),
                $bookingId,
                $nonce,
                __('Check out', 'rrze-rsvp')
            );             
            echo '<h2>' . __('Booking Checked In', 'rrze-rsvp') . '</h2>';
            echo '<p>' . __('Check in has been completed.', 'rrze-rsvp') . '</p>';
            echo '<p>' . __('Please check out when you leave the site.', 'rrze-rsvp') . '</p>';
            echo '<p class="date">';
            echo $date . '<br>';
            echo $time;
            echo '</p>';
            echo '<p>' . $roomName . '</p>';
            echo '<p>' . $seatName . '</p>';                
            echo '<p>' . $link . '</p>';          
            break;
        case 'checkout':
            update_post_meta($bookingId, 'rrze-rsvp-booking-status', 'checked-out');
            echo '<h2>' . __('Booking Checked Out', 'rrze-rsvp') . '</h2>';
            echo '<p>' . __('Check-out has been completed.', 'rrze-rsvp') . '</p>';
            echo '<p class="date checked-out">';
			echo $date . '<br>';
			echo $time;
            echo '</p>';
            echo '<p>' . $roomName . '</p>';
            echo '<p>' . $seatName . '</p>';                      
            break;
        default:
            echo '<h2>' . __('Booking', 'rrze-rsvp') . '</h2>';
            echo '<p>' . __('If you reserved this seat please refer to the message sent to your email.', 'rrze-rsvp') . '</p>';
            echo '<p class="date">';
            echo $date . '<br>';
            echo $time;
            echo '</p>';
            echo '<p>' . $roomName . '</p>';
            echo '<p>' . $seatName . '</p>';            
    }
    echo '</div> </div>';
} else {
    $bookingId = null;
    $status = null;
    $ssoRequired = false;
    $roomId = get_post_meta($postId, 'rrze-rsvp-seat-room', true);
    $now = current_time('timestamp');

    echo '<p><strong>' . __('Room', 'rrze-rsvp') . ':</strong> <a href="' . get_permalink($roomId) . '">' . get_the_title($roomId) . '</a>';

    // Array aus bereits gebuchten Plätzen im Zeitraum erstellen
    $args = [
        'fields' => 'ids',
        'post_type' => 'booking',
        'post_status' => 'publish',
        'nopaging' => true,
        'meta_query' => [
            [
                'key' => 'rrze-rsvp-booking-seat',
                'value'   => $postId,
            ],
            [
                'key'     => 'rrze-rsvp-booking-start',
                'value' => $now,
                'compare' => '<=',
                'type' => 'numeric'
            ],
            [
                'key'     => 'rrze-rsvp-booking-end',
                'value' => $now,
                'compare' => '>=',
                'type' => 'numeric'
            ],
        ],
    ];

    $query = new WP_Query($args);

    if ($query->have_posts()) {
        while ($query->have_posts()) {
            $query->the_post();
            $bookingId = get_the_ID();
        }
        wp_reset_postdata();
    }

    if ($bookingId) {
        $data = Functions::getBooking($bookingId);
        $status = $data['status'];
        $room = $data['room'];
        $roomName = $data['room_name'];
        $seatName = $data['seat_name'];
        $date = $data['date'];
        $time = $data['time'];
        $ssoRequired = Functions::getBoolValueFromAtt(get_post_meta($room, 'rrze-rsvp-room-sso-required', true));        
    }
    
    $nonceQuery = !$ssoRequired ? '' : sprintf('&nonce=%s', $nonce);
    echo '<div class="rrze-rsvp-seat-check-inout"> <div class="container">';
    switch ($status) {
        case 'confirmed':
            $link = sprintf(
                '<a href="%1$s?id=%2$d&action=checkin%3$s" class="button button-checkin" data-id="%2$d">%4$s</a>',
                trailingslashit(get_permalink()),
                $bookingId,
                $nonceQuery,
                __('Check in', 'rrze-rsvp')
            );
            echo '<h2>' . __('Check In', 'rrze-rsvp') . '</h2>';          
            echo '<p>' . __('This seat is currently reserved. If you have reserved this seat, <strong>please check in</strong>.', 'rrze-rsvp') . '</p>';
            echo '<p class="date">';
            echo $date . '<br>';
            echo $time;
            echo '</p>';       
            echo '<p>' . $roomName . '</p>';
            echo '<p>' . $seatName . '</p>';                 
            echo '<p>' . $link . '</p>'; 
            break;
        case 'checked-in':
            $link = sprintf(
                '<a href="%1$s?id=%2$d&action=checkout%3$s" class="button button-checkout" data-id="%2$d">%4$s</a>',
                trailingslashit(get_permalink()),
                $bookingId,
                $nonceQuery,
                __('Check out', 'rrze-rsvp')
            );             
            echo '<h2>' . __('Check Out', 'rrze-rsvp') . '</h2>';
            echo '<p>' . __('This seat is currently reserved. If you have reserved this seat, please check out when you leave the site.', 'rrze-rsvp') . '</p>';
            echo '<p class="date">';
            echo $date . '<br>';
            echo $time;
            echo '</p>';
            echo '<p>' . $roomName . '</p>';
            echo '<p>' . $seatName . '</p>';         
            echo '<p>' . $link . '</p>';                
            break;
        default:
            $allow_instant = get_post_meta($roomId, 'rrze-rsvp-room-instant-check-in', true);

            if ($allow_instant == 'on') {
                $timestamp = current_time('timestamp');
                $day = date('Y-m-d', $timestamp);
                $time = date('H:i', $timestamp);
                $weekday = date('N', $timestamp);
                $booking_start = '';
                $schedule = Functions::getRoomSchedule($roomId);
                foreach ($schedule as $wday => $starttimes) {
                    if ($wday == $weekday) {
                        asort($starttimes);
                        foreach ($starttimes as $starttime => $endtime) {
                            if ($endtime > $time && $starttime <= $time) {
                                $booking_start = $starttime;
                            }
                        }
                    }
                }

                if ($booking_start != '') {
                    $nonce = wp_create_nonce('rsvp-availability');
                    $permalink = get_permalink($roomId);
                    $timeslot = explode('-', $booking_start)[0];
                    $url = get_permalink() . "?room_id=$roomId&seat_id=$postId&bookingdate=$day&timeslot=$timeslot&instant=1&nonce=$nonce";
                    echo '<p>' . sprintf(__('This seat is %sfree for instant check-in%s (booking and check-in in one step) for the current timeslot.', 'rrze-rsvp'), '<strong>', '</strong>') . '</p>';
                    echo '<p><a class="btn btn-success btn-lg btn-block" href="' . $url . '">' . __('Instant check-in', 'rrze-rsvp') . '</a></p><hr />';
                }               
            }
            
            echo '<h3>' . __('Book this seat', 'rrze-rsvp') . '</h3>';
            echo do_shortcode('[rsvp-qr seat=' . $postId . ']');
            echo do_shortcode('[rsvp-availability seat=' . $postId . ' days=14 booking_link=true]');             
    }
    echo '</div> </div>';

}

echo $div_close;

wp_enqueue_style('rrze-rsvp-shortcode');

get_footer();
