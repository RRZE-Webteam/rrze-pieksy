<?php

namespace RRZE\Pieksy;

defined('ABSPATH') || exit;

$settings = new Settings(plugin()->getFile());
$options = (object) $settings->getOptions();
global $post;

$roomId = $post->ID;
$meta = get_post_meta($roomId);

// Schedule
$scheduleData = Functions::getRoomSchedule($roomId);
$schedule = '';
$weekdays = Functions::daysOfWeekAry(1);

if (!empty($scheduleData)) {
    $schedule .= '<table class="pieksy-schedule">';
    $schedule .= '<tr>'
        . '<th>' . __('Weekday', 'rrze-pieksy') . '</th>'
        . '<th>' . __('Time slots', 'rrze-pieksy') . '</th>';
    $schedule .= '</tr>';
    foreach ($scheduleData as $weekday => $dailySlots) {
        $schedule .= '<tr>'
            . '<td>' . $weekdays[$weekday] . '</td>'
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
} else {
    $schedule .= '<p>' . __('No schedule available.') . '</p>';
}

// Floorplan ID
if (isset($meta['rrze-pieksy-room-floorplan_id']) && $meta['rrze-pieksy-room-floorplan_id'] != '') {
    $imgID = $meta['rrze-pieksy-room-floorplan_id'][0];
}

get_header();

/*
 * Ausgabe ?format=embedded&show=xyz für Public Displays
 */
if (isset($_GET['format']) && $_GET['format'] == 'embedded') {
    $width = isset($_GET['width']) ? absint($_GET['width']) : '1820';
    $height = isset($_GET['height']) ? absint($_GET['height']) : '790';
    $innerWidth = (int)$width - 20;
    $innerHeight = (int)$height - 20;
    echo '<style> body.embedded {width:' . $width . 'px; height:' . $height . 'px;} </style>';

    if (isset($_GET['show'])) {
        switch ($_GET['show']) {
            case 'info':
                if (has_post_thumbnail()) {
                    echo get_the_post_thumbnail($roomId, 'medium', array("class" => "alignright"));
                }
                echo get_the_content(null, false, $roomId);
                break;
            case 'floorplan':
                if (isset($meta['rrze-pieksy-room-floorplan_id']) && $meta['rrze-pieksy-room-floorplan_id'] != '') {
                    echo wp_get_attachment_image($imgID, [$innerWidth, $innerHeight]);
                } else {
                    echo __('No floorplan available.', 'rrze-pieksy');
                }
                break;
            case 'schedule':
                echo $schedule;
                break;
            case 'availability':
                $daysInAdvance = get_post_meta($roomId, 'rrze-pieksy-room-days-in-advance', true);
                if (empty($daysInAdvance)) {
                    $daysInAdvance = '10';
                }
                echo do_shortcode('[pieksy-availability room=' . $roomId . ' days=' . $daysInAdvance . ']');
                break;
            case 'occupancy':
                echo Functions::getOccupancyByRoomIdHTML($roomId);
                break;
            case 'occupancy_now':
                echo Functions::getOccupancyByRoomIdHTML($roomId, true);
                break;
            case 'occupancy_nextavailable':
                echo Functions::getOccupancyByRoomIdNextHTML($roomId);
                break;
            default:
                echo Functions::getOccupancyByRoomIdHTML($roomId, true);
                break;
        }
    }

    wp_enqueue_style('rrze-pieksy-shortcode');
    get_footer();

    return;
}

/*
 * div-/Seitenstruktur für FAU- und andere Themes
 */
if (Helper::isFauTheme()) {
    get_template_part('template-parts/hero', 'small');
    $divOpen = '<div id="content">
        <div class="container">
            <div class="row">
                <div class="col-xs-12">
                    <main id="droppoint">
                        <h1 class="screen-reader-text">' . get_the_title() . '</h1>
                        <div class="inline-box">
                            <div class="content-inline">';
    $divClose = '</div>
                        </div>
                    </main>
                </div>
            </div>
        </div>
    </div>';
} else {
    $divOpen = '<div id="content">
        <div class="container">
            <div class="row">
                <div class="col-xs-12">
                <h1 class="entry-title">' . get_the_title() . '</h1>';
    $divClose = '</div>
            </div>
        </div>
    </div>';
}


/*
 * Eigentlicher Content
 */
echo $divOpen;

while (have_posts()) : the_post();

    if (has_post_thumbnail()) {
        the_post_thumbnail('medium', array("class" => "alignright"));
    }
    the_content();

    if (isset($meta['rrze-pieksy-room-timeslots']) && !empty($meta['rrze-pieksy-room-timeslots'])) {

        if ($options->general_single_room_availability_table != 'no') {
            $booking_link = '';
            if ($options->general_single_room_availability_table == 'yes_link') {
                $booking_link = 'booking_link=true';
            }
        }
        $daysInAdvance = get_post_meta($roomId, 'rrze-pieksy-room-days-in-advance', true);
        if (empty($daysInAdvance)) {
            $daysInAdvance = '30';
        }

        $bookingmode = get_post_meta($roomId, 'rrze-pieksy-room-bookingmode', true);
        $timetables = '<h3>' . __('Availability', 'rrze-pieksy') . '</h3>'
        . do_shortcode('[pieksy-availability room=' . $roomId . ' days=' . $daysInAdvance . ' ' . $booking_link . ']');

        echo $timetables;
    }

    if (isset($meta['rrze-pieksy-room-floorplan_id']) && $meta['rrze-pieksy-room-floorplan_id']  != '') {
        echo '<h2>' . __('Floor Plan', 'rrze-pieksy') . '</h2>';
        $imgSrc = wp_get_attachment_image_src($imgID, 'full');
        $floorplan = wp_get_attachment_image($imgID, 'large');
        echo '<a href="' . $imgSrc[0] . '" class="lightbox">' . $floorplan . '</a>';
    }

endwhile;

echo $divClose;

wp_enqueue_style('rrze-pieksy-shortcode');

get_footer();
