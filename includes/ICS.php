<?php

namespace RRZE\Pieksy;

defined('ABSPATH') || exit;

use DateTime;

class ICS
{
	public static function generate(array &$booking, string $filename, string $recipient = 'customer'): string
	{

		if (empty($booking)) {
			return '';
		}

		$output = '';
		$output .= "BEGIN:VCALENDAR\r\n";
		$output .= "VERSION:2.0\r\n";
		$output .= "PRODID:-//rrze//pieksy//EN\r\n";
		$output .= self::vevent($booking, $recipient);
		$output .= "END:VCALENDAR\r\n";
		return $output;
	}

	protected static function vevent(array &$booking, string $recipient = 'customer'): string
	{
		$timezoneString = get_option('timezone_string');
		$dtstamp = date('Ymd\THis');
		$dtstampFormat = Functions::dateFormat(current_time('timestamp')) . ' ' . Functions::timeFormat(current_time('timestamp'));

		$timestamp = date('ymdHi', $booking['start']);
		$dtstamp = date('Ymd\THis');
		$dtstart = date('Ymd\THis', $booking['start']);
		$dtend = date('Ymd\THis', $booking['end']);

		$summary = $booking['room_name'];

		$description = $booking['room_name'] . '\\n';
		$description .= !empty($booking['seat_name']) ? $booking['seat_name'] . '\\n' : '';
		if ($recipient == 'customer') {
			$cancelUrl = Functions::bookingReplyUrl('cancel', sprintf('%s-%s-customer', $booking['id'], $booking['start']), $booking['id']);
			$description .= "\\n\\n" . __('Cancel Booking', 'rrze-pieksy') . ':\\n' . $cancelUrl;	
		}
		$description .= "\\n\\n" . __('Generated', 'rrze-pieksy') . ': ' . $dtstampFormat;

		$output = '';
		$output .= "BEGIN:VEVENT\r\n";
		if ($booking['status'] == 'cancelled'){
			$output .= "METHOD:CANCEL\r\n";
			$output .= "STATUS:CANCELLED\r\n";
			$uid = get_post_meta($booking['id'], 'rrze-pieksy-booking-ics-uid', TRUE);
		}else{
			$uid = md5($timestamp . date('ymdHi')) . "@rrze-pieksy";
			update_post_meta($booking['id'], 'rrze-pieksy-booking-ics-uid', $uid);
		}
		$output .= "UID:" . $uid . "\r\n";
		$output .= "DTSTAMP:" . $dtstamp . "\r\n";
		$output .= "DTSTART;TZID=" . $timezoneString . ":" . $dtstart . "\r\n";
		$output .= "DTEND;TZID=" . $timezoneString . ":" . $dtend . "\r\n";
		$output .= "SUMMARY:" . $summary . "\r\n";
		$output .= "DESCRIPTION:" . $description . "\r\n";
		$output .= "END:VEVENT\r\n";
		return $output;
	}
}
