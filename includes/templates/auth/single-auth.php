<?php

namespace RRZE\Pieksy;

defined('ABSPATH') || exit;

use RRZE\Pieksy\Auth\{IdM};

$idm = new IdM;
$template = new Template;
$settings = new Settings(plugin()->getFile());

$email_error = filter_input(INPUT_GET, 'email_error', FILTER_VALIDATE_INT);
$email_error = ($email_error ? '<p class="error-message">' . __('Please login to the account you have used to book this seat.', 'rrze-pieksy') . '</p><br><br>' : '');


$roomID = isset($_GET['room_id']) ? absint($_GET['room_id']) : 0;
if (!$roomID && isset($_GET['id'])){
    // get room ID from booking via seat
    $bookingID = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
    $seatID = get_post_meta($bookingID, 'rrze-pieksy-booking-seat', true);
    $roomID = get_post_meta($seatID, 'rrze-pieksy-seat-room', true);
}

$ssoRequired = Functions::getBoolValueFromAtt(get_post_meta($roomID, 'rrze-pieksy-room-sso-required', true));

if ($ssoRequired && $idm->simplesamlAuth) {
    $loginUrl = $idm->getLoginURL();
    $idmLogin = sprintf(__('<a href="%s">Please login with your IdM username</a>.', 'rrze-pieksy'), $loginUrl);
}

get_header();

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
$title = __('Authentication Required', 'rrze-pieksy');
echo $divOpen;

echo <<<DIVEND
<div class="rrze-pieksy-booking-reply rrze-pieksy">
    <div class="container">    
        <h2>$title</h2>
        $email_error
DIVEND;

$sOr = '';
if ($ssoRequired) {
    echo "<p>$idmLogin</p>";
    $sOr = '<br><strong>' . __('Oder', 'rrze-pieksy') . '</strong><br>&nbsp;<br>';
}

echo $divClose;

wp_enqueue_style('rrze-pieksy-shortcode');

get_footer();
