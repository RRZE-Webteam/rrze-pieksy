<?php

namespace RRZE\Pieksy;

defined('ABSPATH') || exit;

use RRZE\Pieksy\Auth\{IdM};

use WP_Query;

$idm = new IdM;

$settings = new Settings(plugin()->getFile());
$options = (object) $settings->getOptions();

global $post;
$seatId = $post->ID;

$checkInBooking = null;
$seatCheckInOut = null;
$action = null;


if (isset($_GET['id']) && isset($_GET['nonce']) && wp_verify_nonce($_GET['nonce'], 'rrze-pieksy-checkin-booked-' . $_GET['id'])) {
    $bookingId = absint($_GET['id']);
    $checkInBooking = Functions::getBooking($bookingId);
} elseif (isset($_GET['id']) && isset($_GET['action']) && isset($_GET['nonce']) && wp_verify_nonce($_GET['nonce'], 'rrze-pieksy-seat-check-inout')) {
    $bookingId = absint($_GET['id']);
    $action = sanitize_text_field($_GET['action']);
    if ($seatCheckInOut = Functions::getBooking($bookingId)) {
        $room = $seatCheckInOut['room'];
        $customerEmail = $seatCheckInOut['guest_email'];
        $ssoRequired = Functions::getBoolValueFromAtt(get_post_meta($room, 'rrze-pieksy-room-sso-required', true));
  
        $bSSO = true;
        if (!$ssoRequired || !$idm->isAuthenticated()) {
            $bSSO = false;
        }

        if ($bSSO) {
            if ($bSSO) {
                $idm->setAttributes();
                $customerData = $idm->getCustomerData();
                // check if booking email is logged in email
                if ($customerEmail != $customerData['customer_email']){
                    $idm->logout(home_url( add_query_arg( null, null ) . '&email_error=1'));
                    exit;
                }else{
                    $idm->logout(home_url( add_query_arg( null, null ) . '&sso_loggedout=1'));
                }
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

$nonce = wp_create_nonce('rrze-pieksy-seat-check-inout');

echo 'BK TEST';
exit;

if ($checkInBooking) {
    $roomId = $checkInBooking['room'];
    $roomName = $checkInBooking['room_name'];
    $seatName = $checkInBooking['seat_name'];
    $customEmail = $checkInBooking['guest_email'];
    $date = $checkInBooking['date'];
    $time = $checkInBooking['time'];
    $bookingmode = get_post_meta($roomId, 'rrze-pieksy-room-bookingmode', true);
    echo '<p><strong>' . __('Room', 'rrze-pieksy') . ':</strong> <a href="' . get_permalink($roomId) . '">' . $roomName . '</a>';
    echo '<div class="rrze-pieksy-seat-check-inout"> <div class="container">';
    if ($bookingmode == 'consultation') {
        echo '<h2>' . __('Consult', 'rrze-pieksy') . '</h2>';
        echo '<p>', __('This time slot has been reserved for you.', 'rrze-pieksy') . '</p>';
        echo '<p>' . sprintf(__('Additional information has been sent to your email address <strong>%s</strong>.', 'rrze-pieksy'), $customEmail) . '</p>';
        echo '<p class="date">';
        echo $date . '<br>';
        echo $time;
        echo '</p>';
        echo '<p>' . $roomName . '</p>';    
    } else {
        echo '<h2>' . __('Booking Checked In', 'rrze-pieksy') . '</h2>';
        echo '<p>', __('Check in has been completed.', 'rrze-pieksy') . '</p>';
        echo '<p>' . sprintf(__('Additional information has been sent to your email address <strong>%s</strong>.', 'rrze-pieksy'), $customEmail) . '</p>';
        echo '<p>' . __('Please check out when you leave the site.', 'rrze-pieksy') . '</p>';
        echo '<p class="date">';
        echo $date . '<br>';
        echo $time;
        echo '</p>';
        echo '<p>' . $roomName . '</p>';
        echo '<p>' . $seatName . '</p>';
        // check-out btn
        $link = sprintf(
            '<a href="%1$s?id=%2$d&action=checkout&nonce=%3$s" class="button button-checkout" data-id="%2$d">%4$s</a>',
            trailingslashit(get_permalink()),
            $bookingId,
            $nonce,
            __('Check out', 'rrze-pieksy')
        );
        echo '<p>' . $link . '</p>';        
    }
    echo '</div> </div>';
} elseif ($seatCheckInOut) {
    $status = $seatCheckInOut['status'];
    $roomId = $seatCheckInOut['room'];
    $roomName = $seatCheckInOut['room_name'];
    $seatName = $seatCheckInOut['seat_name'];
    $date = $seatCheckInOut['date'];
    $time = $seatCheckInOut['time'];
    echo '<p><strong>' . __('Room:', 'rrze-pieksy') . '</strong> <a href="' . get_permalink($roomId) . '">' . $roomName . '</a>';
    echo '<div class="rrze-pieksy-seat-check-inout"> <div class="container">';
    switch ($action) {
        case 'checkin':
            if ($status == 'confirmed') {
                update_post_meta($bookingId, 'rrze-pieksy-booking-status', 'checked-in');
            }
            $link = sprintf(
                '<a href="%1$s?id=%2$d&action=checkout&nonce=%3$s" class="button button-checkout" data-id="%2$d">%4$s</a>',
                trailingslashit(get_permalink()),
                $bookingId,
                $nonce,
                __('Check out', 'rrze-pieksy')
            );
            echo '<h2>' . __('Booking Checked In', 'rrze-pieksy') . '</h2>';
            echo '<p>' . __('Check in has been completed.', 'rrze-pieksy') . '</p>';
            echo '<p>' . __('Please check out when you leave the site.', 'rrze-pieksy') . '</p>';
            echo '<p class="date">';
            echo $date . '<br>';
            echo $time;
            echo '</p>';
            echo '<p>' . $roomName . '</p>';
            echo '<p>' . $seatName . '</p>';
            echo '<p>' . $link . '</p>';
            break;
        case 'checkout':
            if ($status == 'checked-in') {
                update_post_meta($bookingId, 'rrze-pieksy-booking-status', 'checked-out');
            }
            echo '<h2>' . __('Booking Checked Out', 'rrze-pieksy') . '</h2>';
            echo '<p>' . __('Check-out has been completed.', 'rrze-pieksy') . '</p>';
            echo '<p class="date checked-out">';
            echo $date . '<br>';
            echo $time;
            echo '</p>';
            echo '<p>' . $roomName . '</p>';
            echo '<p>' . $seatName . '</p>';
            break;
        default:
            echo '<h2>' . __('Booking', 'rrze-pieksy') . '</h2>';
            echo '<p>' . __('If you reserved this seat please refer to the message sent to your email address.', 'rrze-pieksy') . '</p>';
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
   
    $roomId = get_post_meta($seatId, 'rrze-pieksy-seat-room', true);
    $now = current_time('timestamp');

    echo '<p><strong>' . __('Room:', 'rrze-pieksy') . '</strong> <a href="' . get_permalink($roomId) . '">' . get_the_title($roomId) . '</a>';

    $args = [
        'fields' => 'ids',
        'post_type' => 'booking',
        'post_status' => 'publish',
        'nopaging' => true,
        'meta_query' => [
            'booking_status_clause' => [
                'key'       => 'rrze-pieksy-booking-status',
                'value'     => ['confirmed', 'checked-in'],
                'compare'   => 'IN'
            ],
            'seat_id_clause' => [
                'key' => 'rrze-pieksy-booking-seat',
                'value'   => $seatId,
            ],
            'booking_start_clause' => [
                'key'     => 'rrze-pieksy-booking-start',
                'value' => $now,
                'compare' => '<=',
                'type' => 'numeric'
            ],
            'booking_end_clause' => [
                'key'     => 'rrze-pieksy-booking-end',
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
        $ssoRequired = Functions::getBoolValueFromAtt(get_post_meta($room, 'rrze-pieksy-room-sso-required', true));
    }

    $bookingmode = get_post_meta($roomId, 'rrze-pieksy-room-bookingmode', true);
    $daysInAdvance = get_post_meta($roomId, 'rrze-pieksy-room-days-in-advance', true);
    $allowInstant = Functions::getBoolValueFromAtt(get_post_meta($roomId, 'rrze-pieksy-room-instant-check-in', true));

    //$nonceQuery = (!$ssoRequired ? '' : '&nonce=' . $nonce );
    $nonceQuery = ( !$ssoRequired ? '' : '&nonce=' . $nonce );

    if ($bookingmode == 'reservation' && $status == 'confirmed') {
        $link = sprintf(
            '<a href="%1$s?id=%2$d&action=checkin%3$s" class="button button-checkin" data-id="%2$d">%4$s</a>',
            trailingslashit(get_permalink()),
            $bookingId,
            $nonceQuery,
            __('Check in', 'rrze-pieksy')
        );
        echo '<div class="rrze-pieksy-seat-check-inout"> <div class="container">';
        echo '<h2>' . __('Check In', 'rrze-pieksy') . '</h2>';
        echo '<p>' . __('This seat is currently reserved. If you have reserved this seat, please check in.', 'rrze-pieksy') . '</p>';
        echo '<p class="date">';
        echo $date . '<br>';
        echo $time;
        echo '</p>';
        echo '<p>' . $roomName . '</p>';
        echo '<p>' . $seatName . '</p>';
        echo '<p>' . $link . '</p>';
        echo '</div> </div>';
    } else if (in_array($bookingmode, ['check-only', 'reservation']) && $status == 'checked-in') {
        $link = sprintf(
            '<a href="%1$s?id=%2$d&action=checkout%3$s" class="button button-checkout" data-id="%2$d">%4$s</a>',
            trailingslashit(get_permalink()),
            $bookingId,
            $nonceQuery,
            __('Check out', 'rrze-pieksy')
        );
        echo '<div class="rrze-pieksy-seat-check-inout"> <div class="container">';
        echo '<h2>' . __('Check Out', 'rrze-pieksy') . '</h2>';
        echo '<p>' . __('This seat is currently reserved. If you have reserved this seat, please check out when you leave the site.', 'rrze-pieksy') . '</p>';
        echo '<p class="date">';
        echo $date . '<br>';
        echo $time;
        echo '</p>';
        echo '<p>' . $roomName . '</p>';
        echo '<p>' . $seatName . '</p>';
        echo '<p>' . $link . '</p>';
        echo '</div> </div>';
    } else {
        $bookingStart = null;
        $bookingEnd = null;

        if ($bookingmode == 'check-only' || ($bookingmode == 'reservation' && $allowInstant)) {
            $timestamp = current_time('timestamp');
            $day = date('Y-m-d', $timestamp);
            $time = date('H:i', $timestamp);
            $weekday = date('N', $timestamp);
            $schedule = Functions::getRoomSchedule($roomId);
            foreach ($schedule as $wday => $starttimes) {
                if ($wday == $weekday) {
                    asort($starttimes);
                    foreach ($starttimes as $starttime => $endtime) {
                        if ($endtime > $time && $starttime <= $time) {
                            $bookingStart = $starttime;
                            $bookingEnd = $endtime;
                        }
                    }
                }
            }
        }
        
        if ($bookingStart && $bookingEnd) {
            $start = strtotime($day . ' ' . $bookingStart);
            $end = strtotime($day . ' ' . $bookingEnd);
            $date = Functions::dateFormat($start);
            $time = Functions::timeFormat($start) . ' - ' . Functions::timeFormat($end);
            $roomName = get_the_title($roomId);
            $seatName = get_the_title($seatId);
            $timeslot = explode('-', $bookingStart)[0];
            $nonce = wp_create_nonce('pieksy-availability');
            $link = sprintf(
                '<a href="%1$s?room_id=%2$d&seat_id=%3$d&bookingdate=%4$s&timeslot=%5$s&instant=1&nonce=%6$s" class="button button-checkin" data-id="%2$d">%7$s</a>',
                trailingslashit(get_permalink($roomId)),
                $roomId,
                $seatId,
                $day,
                $timeslot,
                $nonce,
                __('Check In', 'rrze-pieksy')
            );
            echo '<div class="rrze-pieksy-seat-check-inout"> <div class="container">';
            echo '<h2>' . __('Check In', 'rrze-pieksy') . '</h2>';
            echo '<p>' . __('This seat is free for instant check-in (booking and check-in in one step) for the current timeslot.', 'rrze-pieksy') . '</p>';
            echo '<p class="date">';
            echo $date . '<br>';
            echo $time;
            echo '</p>';
            echo '<p>' . $roomName . '</p>';
            echo '<p>' . $seatName . '</p>';
            echo '<p>' . $link . '</p>';
            echo '</div> </div>';
        } else {
            if ($bookingmode == 'consultation') {
                echo '<h2>' . __('Consultation', 'rrze-pieksy') . '</h2>';
                echo '<p>' . __('Please reserve a time slot for a consultation.', 'rrze-pieksy') . '</p>';
            } elseif ($bookingmode == 'check-only') {
                echo '<h2>' . __('Availability information', 'rrze-pieksy') . '</h2>';
                echo '<p>' . __('This seat is for instant check-in only and cannot be reserved. These are the next available time slots:', 'rrze-pieksy') . '</p>';
            } else {
                echo '<h2>' . __('Reservation', 'rrze-pieksy') . '</h2>';
                echo '<p>' . __('Please reserve a time slot for this seat', 'rrze-pieksy') . '</p>';
            }
            $bookingLink = $bookingmode == 'check-only' ? 'false' : 'true';
            echo do_shortcode(sprintf('[pieksy-availability seat=%s days=%s booking_link=%s]', $seatId, $daysInAdvance, $bookingLink));
        }
    }
}

echo $div_close;

wp_enqueue_style('rrze-pieksy-shortcode');
get_footer();



