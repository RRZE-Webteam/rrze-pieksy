<?php

namespace RRZE\Pieksy;

defined('ABSPATH') || exit;

use WP_Query;

/**
 * Schedule
 * Manage the cron schedules
 */
class Schedule
{
    /**
     * Email object
     * @var object
     */
    protected $email;

    /**
     * __construct
     */
    public function __construct()
    {
        $this->email = new Email;
        $this->settings = new Settings(plugin()->getFile());
        $this->options = $this->settings->getOptions();
    }

    /**
     * onLoaded
     * Launch all hooks.
     * @return void
     */
    public function onLoaded()
    {
        add_action('wp', [$this, 'activateScheduledEvents']);
        add_filter('cron_schedules', [$this, 'customCronSchedules']);
        add_action('rrze_pieksy_every5minutes_event', [$this, 'Every5MinutesEvent']);
        add_action('rrze_pieksy_daily_event', [$this, 'dailyEvent']);
    }

    /**
     * customCronSchedules
     * Add custom cron schedules.
     * @param array $schedules Available cron schedules
     * @return array New cron schedules
     */
    public function customCronSchedules(array $schedules): array
    {
        $schedules['rrze_pieksy_every5minutes'] = [
            'interval' => 5 * MINUTE_IN_SECONDS,
            'display' => __('Every five minutes', 'rrze-pieksy')
        ];
        return $schedules;
    }

    /**
     * activateScheduledEvents
     * Activate all scheduled events.
     * @return void
     */
    public function activateScheduledEvents()
    {
        if (!wp_next_scheduled('rrze_pieksy_every5minutes_event')) {
            wp_schedule_event(time(), 'rrze_pieksy_every5minutes', 'rrze_pieksy_every5minutes_event');
        }
        if (!wp_next_scheduled('rrze_pieksy_daily_event')) {
            wp_schedule_event(time(), 'daily', 'rrze_pieksy_daily_event');
        }
    }

    /**
     * Every5MinutesEvent
     * Run the event every 5 minutes.
     * @return void
     */
    public function Every5MinutesEvent()
    {
        $this->cancelUnconfirmedBookings();
        $this->cancelNotCheckedInBookings();
        $this->checkOutNotCheckedOutBookings();
    }

    /**
     * dailyEvent
     * Run the event daily.
     * @return void
     */
    public function dailyEvent()
    {
        $this->deleteOldBookings();
        $this->deleteCancelledBookings();
    }

    /**
     * deleteOldBookings
     * Delete all reservations whose start datetime is older than 4 weeks.
     * @return void
     */
    protected function deleteOldBookings()
    {
        $timeStampBefore = current_time('timestamp') - (4 * WEEK_IN_SECONDS);

        $args = [
            'fields'            => 'ids',
            'post_type'         => ['booking'],
            'post_status'       => 'publish',
            'nopaging'          => true,
            'meta_query'        => [
                [
                    'key'       => 'rrze-pieksy-booking-start',
                    'value'     => $timeStampBefore,
                    'compare'   => '<'
                ]
            ]
        ];

        $query = new WP_Query($args);

        if ($query->have_posts()) {
            while ($query->have_posts()) {
                $query->the_post();
                wp_delete_post(get_the_ID(), true);
            }
            wp_reset_postdata();
        }
    }

    /**
     * deleteCancelledBookings
     * Delete bookings that were cancelled more than 1 day ago.
     * @return void
     */
    protected function deleteCancelledBookings()
    {
        $timeStampBefore = current_time('timestamp') - (1 * DAY_IN_SECONDS);

        $args = [
            'fields'            => 'ids',
            'post_type'         => ['booking'],
            'post_status'       => 'publish',
            'nopaging'          => true,
            'date_query'        => [
                [
                    'before'    => date('Y-m-d H:i:s', $timeStampBefore),
                    'inclusive' => false,
                ],
            ],
            'meta_query'        => [
                [
                    'key'       => 'rrze-pieksy-booking-status',
                    'value'     => ['cancelled'],
                    'compare'   => 'IN'
                ]
            ]
        ];

        $query = new WP_Query($args);

        if ($query->have_posts()) {
            while ($query->have_posts()) {
                $query->the_post();
                wp_delete_post(get_the_ID(), true);
            }
            wp_reset_postdata();
        }
    }

    /**
     * cancelUnconfirmedBookings
     * Cancel bookings with a date greater than 1 hour that were not 
     * confirmed by the customer. It depends on whether the request for 
     * confirmation by the customer is activated in the room settings.
     * @return void
     */
    protected function cancelUnconfirmedBookings()
    {
        $timeStampBefore = current_time('timestamp') - (1 * HOUR_IN_SECONDS);

        $args = [
            'fields'            => 'ids',
            'post_type'         => ['booking'],
            'post_status'       => 'publish',
            'nopaging'          => true,
            'date_query'        => [
                [
                    'before'    => date('Y-m-d H:i:s', $timeStampBefore),
                    'inclusive' => false,
                ],
            ],
            'meta_query'        => [
                'booking_status_clause' => [
                    'key'       => 'rrze-pieksy-booking-status',
                    'value'     => 'booked',
                    'compare'   => '='
                ]
            ]
        ];

        $query = new WP_Query($args);

        if ($query->have_posts()) {
            while ($query->have_posts()) {
                $query->the_post();
                $bookingId = get_the_ID();
                $seatId = get_post_meta($bookingId, 'rrze-pieksy-booking-seat', true);
                $roomId = get_post_meta($seatId, 'rrze-pieksy-seat-room', true);
                // if (get_post_meta($roomId, 'rrze-pieksy-room-force-to-confirm', true)) {
                //     update_post_meta($bookingId, 'rrze-pieksy-booking-status', 'cancelled');
                //     $this->email->doEmail('bookingCancelled', 'customer', $bookingId, 'cancelled', 'notconfirmed');
                // }
            }
            wp_reset_postdata();
        }
    }

    /**
     * cancelNotCheckedInBookings
     * Cancel booking that were not checked-in in time
     * of starting the event. It depends on whether the check-in requirement 
     * is activated in the room settings.
     * @return void
     */
    protected function cancelNotCheckedInBookings()
    {
        $args = [
            'fields'            => 'ids',
            'post_type'         => ['booking'],
            'post_status'       => 'publish',
            'nopaging'          => true,
            'meta_query'        => [
                'relation'      => 'AND',
                'booking_status_clause' => [
                    'key'       => 'rrze-pieksy-booking-status',
                    'value'     => ['confirmed'],
                    'compare'   => 'IN'
                ],
            ]
        ];

        $query = new WP_Query($args);

        if ($query->have_posts()) {
            while ($query->have_posts()) {
                $query->the_post();
                $bookingId = get_the_ID();
                $booking = Functions::getBooking($bookingId);
                $roomId = $booking['room'];
                $roomMeta = get_post_meta($roomId);
                $bookingMode = isset($roomMeta['rrze-pieksy-room-bookingmode']) ? $roomMeta['rrze-pieksy-room-bookingmode'][0] : '';
                $checkInRequired = isset($roomMeta['rrze-pieksy-room-force-to-checkin']) ? Functions::getBoolValueFromAtt($roomMeta['rrze-pieksy-room-force-to-checkin'][0]) : false;
                if (!$checkInRequired && !in_array($bookingMode, ['consultation', 'no-check'])) {
                    continue;
                }
                $bookingTimeStamp = $booking['booking_date_timestamp'];
                $bookingStart = $booking['start'];
                // $checkInTime = isset($roomMeta['rrze-pieksy-room-check-in-time']) ? $roomMeta['rrze-pieksy-room-check-in-time'][0] : '';
                // if ($checkInTime == '') {
                //     $defaultCheckInTime = $this->settings->getDefault('general', 'check-in-time');
                //     $settingsCheckInTime = $this->settings->getOption('general', 'check-in-time', $defaultCheckInTime, true);
                //     $checkInTime = $settingsCheckInTime;
                // }
                if ($bookingStart < $bookingTimeStamp) {
                    //Seat booked after beginning of timeslot
                    // $timeStampCheckIn = $bookingTimeStamp + ($checkInTime * MINUTE_IN_SECONDS);
                    $timeStampCheckIn = $bookingTimeStamp;
                } else {
                    // $timeStampCheckIn = $bookingStart + ($checkInTime * MINUTE_IN_SECONDS);
                    $timeStampCheckIn = $bookingStart;
                }
                if (current_time('timestamp') < $timeStampCheckIn) {
                    continue;
                }
                if (in_array($bookingMode, ['consultation', 'no-check'])) {
                    update_post_meta($bookingId, 'rrze-pieksy-booking-status', 'checked-in');
                } elseif ($checkInRequired) {
                    update_post_meta($bookingId, 'rrze-pieksy-booking-status', 'cancelled');
                    $this->email->doEmail('bookingCancelled', 'customer', $bookingId, 'cancelled', 'notcheckedin');
                }
            }
            wp_reset_postdata();
        }
    }

    /**
     * checkOutNotCheckedOutBookings
     * Check-out booking with checked-in status that were 
     * not checked-out by the customer.
     * @return void
     */
    protected function checkOutNotCheckedOutBookings()
    {
        $timeStamp = current_time('timestamp');

        $args = [
            'fields'            => 'ids',
            'post_type'         => ['booking'],
            'post_status'       => 'publish',
            'nopaging'          => true,
            'meta_query'        => [
                'relation'      => 'AND',
                'booking_status_clause' => [
                    'key'       => 'rrze-pieksy-booking-status',
                    'value'     => ['checked-in'],
                    'compare'   => 'IN'
                ],
                'booking_start_clause' => [
                    'key'       => 'rrze-pieksy-booking-end',
                    'value'     => $timeStamp,
                    'compare'   => '<',
                    'type' => 'numeric'
                ]
            ]
        ];

        $query = new WP_Query($args);

        if ($query->have_posts()) {
            while ($query->have_posts()) {
                $query->the_post();
                update_post_meta(get_the_ID(), 'rrze-pieksy-booking-status', 'checked-out');
            }
            wp_reset_postdata();
        }
    }

}
