<?php

namespace RRZE\Pieksy\Config;

use RRZE\Pieksy\Functions;

defined('ABSPATH') || exit;

/**
 * Gibt der Name der Option zurück.
 * @return array [description]
 */
function getOptionName() {
    return 'rrze_pieksy';
}

/**
 * Fixe und nicht aenderbare Plugin-Optionen
 * @return array 
 */
function getConstants() {
        $options = array(
	    
	    'fauthemes' => [
		'FAU-Einrichtungen', 
		'FAU-Philfak',
		'FAU-Natfak', 
		'FAU-RWFak', 
		'FAU-Medfak', 
		'FAU-Techfak',
		'FAU-Jobs'
		],


        );               
        // für ergänzende Optionen aus anderen Plugins
        $options = apply_filters('rrze_pieksy_constants', $options);
        return $options; // Standard-Array für zukünftige Optionen
    }

function isAllowedSearchForGuest(){
    $allowedDomains = [
        'www.nickless.test.rrze.fau.de',
    ];
    return in_array($_SERVER['SERVER_NAME'], $allowedDomains);
}

// used in wp_kses_custom()  
function getAllowedHTML(){
    return [
        'a' => [
            'href' => [],
        ],
        'br' => [],
        'h3' => [],
        'li' => [],
        'p' => [],
        'ul' => [],
    ];
}    

// sanitizes but allows defined tags and protocols
function wp_kses_custom($str){
    $allowed_html = getAllowedHTML();

    $allowed_protocols = [
        'http' => [],
        'https' => [],
        'mailto' => [],
    ];

    return wp_kses( $str, $allowed_html, $allowed_protocols );
}

// sanitzes natural number (positive INT)
function sanitize_natint_field( $input ) {
    return filter_var($input, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
}

    
function defaultOptions()  {

    $sender_name = '';
    $notification_email = '';
    $sender_email = '';

    $blogAdminUsers = get_users( 'role=Administrator' );
    if ($blogAdminUsers){
        $sender_name = $blogAdminUsers[0]->display_name;
        $sender_email = $blogAdminUsers[0]->user_email;
        $notification_email = $blogAdminUsers[0]->user_email;
    }

        return [
            'single_room_availability_table' => 'yes_link',
            'notification_email' => $notification_email,
            'notification_if_new' => 'yes',
            'notification_if_cancel' => 'yes',
            'sender_name' => $sender_name,
            'sender_email' => $sender_email,
            'received_subject' => __('Thank you for booking', 'rrze-pieksy'),
            'received_subject_en' => 'Thank you for booking',
            'received_text' => __('We received your booking and we will notify you once it has been confirmed.', 'rrze-pieksy'),
            'received_text_en' => 'We received your booking and we will notify you once it has been confirmed.',
            'force_to_confirm_subject' => __('Please confirm your booking', 'rrze-pieksy'),
            'force_to_confirm_subject_en' => 'Please confirm your booking',
            'force_to_confirm_text' => __('You are required to confirm the booking now. Please note that unconfirmed bookings automatically expire after one hour.', 'rrze-pieksy'),
            'force_to_confirm_text_en' => 'You are required to confirm the booking now. Please note that unconfirmed bookings automatically expire after one hour.',                         
            'confirm_subject' => __('Your booking has been confirmed', 'rrze-pieksy'),
            'confirm_subject_en' => 'Your booking has been confirmed',            
            'confirm_text' => __('We are happy to inform you that your booking has been confirmed.', 'rrze-pieksy'),
            'confirm_text_en' => 'We are happy to inform you that your booking has been confirmed.',
            'cancel_subject' => __('Your booking has been cancelled', 'rrze-pieksy'),
            'cancel_subject_en' => 'Your booking has been cancelled',
            'cancel_reason_notconfirmed' => __('You haven\'t confirmed your reservation.', 'rrze-pieksy'),
            'cancel_reason_notconfirmed_en' => 'You haven\'t confirmed your reservation.',
            'cancel_reason_notcheckedin' => __('You haven\'t checked in.', 'rrze-pieksy'),
            'cancel_reason_notcheckedin_en' => 'You haven\'t  checked in.',
            'cancel_text' => __('Unfortunately we have to cancel your booking on {{date}} at {{time}}.', 'rrze-pieksy'),
            'cancel_text_en' => 'Unfortunately we have to cancel your booking on {{date_en}} at {{time_en}}.',
            'fau_logo' => 'on',
            'website_logo' => 'off',
            'website_url' => 'on',
            'instructions_de' => 'Bitte lesen Sie den QR Code ein, um auf diesem Platz einzuchecken oder diesen Platz für einen späteren Zeitpunkt zu reservieren.',
            'instructions_en' => 'Please scan the QR code to check in at this place or to reserve this place for a later date.',
            'room_text' => 'off',
            // 'room_image' => 'off',
            'room_address' => 'off',
            // 'room_floorplan' => 'off',
            'room-notes-label' => __('Additional informations', 'rrze-pieksy'),
            'check-in-time' => '15',
        ];
    }
    
/**
 * Gibt die Einstellungen des Menus zurück.
 * @return array [description]
 */
function getMenuSettings()
{
    return [
        'page_title'    => __('RRZE Pieksy', 'rrze-pieksy'),
        'menu_title'    => __('RRZE Pieksy', 'rrze-pieksy'),
        'capability'    => 'manage_options',
        'menu_slug'     => 'rrze-pieksy',
        'title'         => __('RRZE Pieksy Settings', 'rrze-pieksy'),
    ];
}

/**
 * Gibt die Einstellungen der Optionsbereiche zurück.
 * @return array [description]
 */
function getSections()
{
    return [
        [
            'id'    => 'general',
            'title' => __('General Settings', 'rrze-pieksy')
        ],
        [
            'id'    => 'email',
            'title' => __('E-Mail Settings', 'rrze-pieksy')
        ],
        [
            'id'    => 'pdf',
            'title' => __('QR PDF Settings', 'rrze-pieksy')
        ],
        [
            'id'    => 'reset',
            'title' => __('Reset Settings', 'rrze-pieksy')
        ],
    ];
}

/**
 * Gibt die Einstellungen der Optionsfelder zurück.
 * @return array [description]
 */
function getFields(){
    $defaults = defaultOptions();
    
    return [
        'general' => [
            [
                'name'    => 'check-in-time',
                'label'   => __('Allowed Check-In Time.', 'rrze-pieksy'),
                'type'    => 'number',
                'default' => $defaults['check-in-time'],
                'sanitize_callback' => 'sanitize_natint_field',
            ],
            [
                'name'    => 'single_room_availability_table',
                'label'   => __('Show Availability table on Room page.', 'rrze-pieksy'),
                'type'    => 'radio',
                'default' => $defaults['single_room_availability_table'],
                'options' => [
                    'yes_link' => __('Yes (with seats linked to booking form)', 'rrze-pieksy'),
                    'yes' => __('Yes (no link)', 'rrze-pieksy'),
                    'no'  => __('No', 'rrze-pieksy')
                ]
            ],
        ],
        'email' => [
            [
                'name'    => 'notification_email',
                'label'   => __('Notification email', 'rrze-pieksy'),
                'desc'    => __('Email address for notifications.', 'rrze-pieksy'),
                'type'    => 'email',
                'default' => $defaults['notification_email'],
		        'sanitize_callback' => 'sanitize_email'
            ],
            [
                'name'    => 'notification_if_new',
                'label'   => __('Booking Notification', 'rrze-pieksy'),
                'desc'    => __('New booking notification.', 'rrze-pieksy'),
                'type'    => 'radio',
                'options' => [
                    'yes' => __('Yes', 'rrze-pieksy'),
                    'no'  => __('No', 'rrze-pieksy')
                ],
		        'default'   => $defaults['notification_if_new'],
            ],
	        [
                'name'    => 'notification_if_cancel',
                'label'   => __('Cancel Notification', 'rrze-pieksy'),
                'desc'    => __('Notification of booking cancellation.', 'rrze-pieksy'),
                'type'    => 'radio',
                'options' => [
                    'yes' => __('Yes', 'rrze-pieksy'),
                    'no'  => __('No', 'rrze-pieksy')
                ],
		        'default'   => $defaults['notification_if_cancel'],
            ],
	        [
                'name'              => 'sender_name',
                'label'             => __('Sender name', 'rrze-pieksy'),
                'desc'              => __('Name for Sender for the booking system.', 'rrze-pieksy'),
                'placeholder'       => __('Sender name', 'rrze-pieksy'),
                'type'              => 'text',
                'default'           =>  $defaults['sender_name'],
                'sanitize_callback' => 'sanitize_text_field'
            ],
	        [
                'name'    => 'sender_email',
                'label'   => __('Sender email', 'rrze-pieksy'),
                'desc'    => __('Email address of sender.', 'rrze-pieksy'),
                'type'    => 'email',
                'default' =>  $defaults['sender_email'],
		        'sanitize_callback' => 'sanitize_email'
            ],
	        [
                'name'              => 'received_subject',
                'label'             => __('Subject of the received booking', 'rrze-pieksy'),
                'desc'              => __('Subject of the email replying to a booking received.', 'rrze-pieksy'),
                'type'              => 'text',
                'default'           =>  $defaults['received_subject'],
                'sanitize_callback' => 'sanitize_text_field'
            ],
            [
                'name'              => 'received_subject_en',
                'label'             => __('Subject of the received booking (english)', 'rrze-pieksy'),
                'desc'              => __('Subject of the email replying to a booking received.', 'rrze-pieksy'),
                'type'              => 'text',
                'default'           =>  $defaults['received_subject_en'],
                'sanitize_callback' => 'sanitize_text_field',
                'exception'         => ['locale' => 'en']
            ],            
	        [
                'name'              => 'received_text',
                'label'             => __('Text of the received booking', 'rrze-pieksy'),
                'type'              => 'textarea',
                'default'           => $defaults['received_text']
            ],
            [
                'name'              => 'received_text_en',
                'label'             => __('Text of the received booking (english)', 'rrze-pieksy'),
                'type'              => 'textarea',
                'default'           => $defaults['received_text_en'],
                'exception'         => ['locale' => 'en']
            ],  
            [
                'name'              => 'force_to_confirm_subject',
                'label'             => __('Subject for confirmation required.', 'rrze-pieksy'),
                'desc'              => __('Subject of the email where confirmation of the booking by the customer is required.', 'rrze-pieksy'),
                'type'              => 'text',
                'default'           => $defaults['force_to_confirm_subject'],
                'sanitize_callback' => 'sanitize_text_field'
            ],  
            [
                'name'              => 'force_to_confirm_subject_en',
                'label'             => __('Subject for confirmation required (english)', 'rrze-pieksy'),
                'desc'              => __('Subject of the email where confirmation of the booking by the customer is required.', 'rrze-pieksy'),
                'type'              => 'text',
                'default'           => $defaults['force_to_confirm_subject_en'],
                'sanitize_callback' => 'sanitize_text_field',
                'exception'         => ['locale' => 'en']
            ], 
            [
                'name'              => 'force_to_confirm_text',
                'label'             => __('Text for confirmation required', 'rrze-pieksy'),
                'type'              => 'textarea',
                'default'           => $defaults['force_to_confirm_text']
            ],   
            [
                'name'              => 'force_to_confirm_text_en',
                'label'             => __('Text for confirmation required (english)', 'rrze-pieksy'),
                'type'              => 'textarea',
                'default'           => $defaults['force_to_confirm_text_en'],
                'exception'         => ['locale' => 'en']
            ],                                                    
	        [
                'name'              => 'confirm_subject',
                'label'             => __('Subject Confirmation', 'rrze-pieksy'),
                'desc'              => __('Subject for confirmation mails', 'rrze-pieksy'),
                'type'              => 'text',
                'default'           => $defaults['confirm_subject'],
                'sanitize_callback' => 'sanitize_text_field'
            ],
            [
                'name'              => 'confirm_subject_en',
                'label'             => __('Subject Confirmation (english)', 'rrze-pieksy'),
                'desc'              => __('Subject for confirmation mails', 'rrze-pieksy'),
                'type'              => 'text',
                'default'           => $defaults['confirm_subject_en'],
                'sanitize_callback' => 'sanitize_text_field',
                'exception'         => ['locale' => 'en']
            ],            
	        [
                'name'              => 'confirm_text',
                'label'             => __('Confirmation Text', 'rrze-pieksy'),
                'type'              => 'textarea',
                'default'           => $defaults['confirm_text']
            ],
            [
                'name'              => 'confirm_text_en',
                'label'             => __('Confirmation Text (english)', 'rrze-pieksy'),
                'type'              => 'textarea',
                'default'           => $defaults['confirm_text_en'],
                'exception'         => ['locale' => 'en']
            ],            
	        [
                'name'              => 'cancel_subject',
                'label'             => __('Subject Cancelling', 'rrze-pieksy'),
                'desc'              => __('Subject for cancelling mails', 'rrze-pieksy'),
                'type'              => 'text',
                'default'           =>  $defaults['cancel_subject'],
                'sanitize_callback' => 'sanitize_text_field'
            ],
	        [
                'name'              => 'cancel_subject_en',
                'label'             => __('Subject Cancelling (english)', 'rrze-pieksy'),
                'desc'              => __('Subject for cancelling mails', 'rrze-pieksy'),
                'type'              => 'text',
                'default'           =>  $defaults['cancel_subject_en'],
                'sanitize_callback' => 'sanitize_text_field',
                'exception'         => ['locale' => 'en']
            ],            
	        [
                'name'              => 'cancel_reason_notconfirmed',
                'label'             => __('Reason Cancelling Not Confirmed', 'rrze-pieksy'),
                'desc'              => __('Reason for cancelling mails because there is no confirmation', 'rrze-pieksy'),
                'type'              => 'textarea',
                'default'           =>  $defaults['cancel_reason_notconfirmed'],
                'sanitize_callback' => 'sanitize_text_field'
            ],
	        [
                'name'              => 'cancel_reason_notconfirmed_en',
                'label'             => __('Reason Cancelling Not Confirmed (english)', 'rrze-pieksy'),
                'desc'              => __('Reason for cancelling mails because there is no confirmation', 'rrze-pieksy'),
                'type'              => 'textarea',
                'default'           =>  $defaults['cancel_reason_notconfirmed_en'],
                'sanitize_callback' => 'sanitize_text_field',
                'exception'         => ['locale' => 'en']
            ],
	        [
                'name'              => 'cancel_reason_notcheckedin',
                'label'             => __('Reason Cancelling Not Checked In', 'rrze-pieksy'),
                'desc'              => __('Reason for cancelling mails because there is no check-in', 'rrze-pieksy'),
                'type'              => 'textarea',
                'default'           =>  $defaults['cancel_reason_notcheckedin'],
                'sanitize_callback' => 'sanitize_text_field'
            ],
	        [
                'name'              => 'cancel_reason_notcheckedin_en',
                'label'             => __('Reason Cancelling Not Checked In (english)', 'rrze-pieksy'),
                'desc'              => __('Reason for cancelling mails because there is no check-in', 'rrze-pieksy'),
                'type'              => 'textarea',
                'default'           =>  $defaults['cancel_reason_notcheckedin_en'],
                'sanitize_callback' => 'sanitize_text_field',
                'exception'         => ['locale' => 'en']
            ],
	        [
                'name'              => 'cancel_text',
                'label'             => __('Cancel Text', 'rrze-pieksy'),
                'type'              => 'textarea',
                'default'           => $defaults['cancel_text']
            ],
	        [
            'name'              => 'cancel_text_en',
            'label'             => __('Cancel Text (english)', 'rrze-pieksy'),
            'type'              => 'textarea',
            'default'           => $defaults['cancel_text_en'],
            'exception'         => ['locale' => 'en']
            ]         
        ],
        'pdf' => [
            [
                'name'  => 'fau_logo',
                'label' => __('Print FAU logo', 'rrze-pieksy'),
                'default'           => $defaults['fau_logo'],
                'type'  => 'checkbox'
            ],
            [
                'name'  => 'website_logo',
                'label' => __('Print website\'s logo or title', 'rrze-pieksy'),
                'default'           => $defaults['website_logo'],
                'type'  => 'checkbox'
            ],
            [
                'name'  => 'website_url',
                'label' => __('Print website\'s URL', 'rrze-pieksy'),
                'default'           => $defaults['website_url'],
                'type'  => 'checkbox'
            ],
            [
                'name'              => 'instructions_de',
                'label'             => __('Instructions in German', 'rrze-pieksy'),
                'desc'              => __('This text will be shown above the QR code.', 'rrze-pieksy'),
                'placeholder'       => __('Instructions in German', 'rrze-pieksy'),
                'type'              => 'textarea',
                'default'           => $defaults['instructions_de'],
                'sanitize_callback' => 'sanitize_text_field'
            ],
            [
                'name'              => 'instructions_en',
                'label'             => __('Instructions in English', 'rrze-pieksy'),
                'desc'              => __('This text will be shown above the QR code.', 'rrze-pieksy'),
                'placeholder'       => __('Instructions in English', 'rrze-pieksy'),
                'type'              => 'textarea',
                'default'           => $defaults['instructions_en'],
                'sanitize_callback' => 'sanitize_text_field'
            ],
            [
                'name'  => 'room_text',
                'label' => __('Print room\'s text', 'rrze-pieksy'),
                'default' => $defaults['room_text'],
                'type'  => 'checkbox'
            ],
            // [
            //     'name'  => 'room_image',
            //     'label' => __('Print room\'s image', 'rrze-pieksy'),
            //     'default' => $defaults['room_image'],
            //     'type'  => 'checkbox'
            // ],
            [
                'name'  => 'room_address',
                'label' => __('Print room\'s address', 'rrze-pieksy'),
                'default' => $defaults['room_address'],
                'type'  => 'checkbox'
            ],
            // [
            //     'name'  => 'room_floorplan',
            //     'label' => __('Print floor plan', 'rrze-pieksy'),
            //     'default' => $defaults['room_floorplan'],
            //     'type'  => 'checkbox'
            // ],
        ],
        'reset' => [
            [
                'name'  => 'reset_settings',
                'label'   => '',
                'desc'   => __('Yes, I want to reset <strong>all</strong> settings.', 'rrze-pieksy'),
                'type'  => 'checkbox'
            ],
        ]
    ];
}


/**
 * Gibt die Einstellungen der Parameter für Shortcode für den klassischen Editor und für Gutenberg zurück.
 * @return array [description]
 */

function getShortcodeSettings(){
	return [
        'pieksy-booking' => [ // Key muss mit dem dazugehörigen Shortcode identisch sein
            'block' => [
                'blocktype' => 'rrze-pieksy/pieksy-booking', // dieser Wert muss angepasst werden
                'blockname' => 'pieksy_booking', // dieser Wert muss angepasst werden
                'title' => __('Pieksy Booking', 'rrze-pieksy'), // Der Titel, der in der Blockauswahl im Gutenberg Editor angezeigt wird
                'category' => 'widgets', // Die Kategorie, in der der Block im Gutenberg Editor angezeigt wird
                'icon' => 'admin-users',  // Das Icon des Blocks
                'show_block' => 'content', // 'right' or 'content' : Anzeige des Blocks im Content-Bereich oder in der rechten Spalte
                'message' => __( 'Find the settings on the right side', 'rrze-pieksy' ) // erscheint bei Auswahl des Blocks, wenn "show_block" auf 'right' gesetzt ist
            ],
            'days' => [
                'default' => '',
                'field_type' => 'text', // Art des Feldes im Gutenberg Editor
                'label' => __( 'Days in advance', 'rrze-pieksy' ),
                'type' => 'number' // Variablentyp der Eingabe
            ],
            'room' => [
                'default' => '',
                'field_type' => 'text', // Art des Feldes im Gutenberg Editor
                'label' => __( 'Room', 'rrze-pieksy' ),
                'type' => 'text' // Variablentyp der Eingabe
            ],
            'sso' => [
                'field_type' => 'toggle',
                'label' => __( 'Require SSO Authentication', 'rrze-pieksy' ),
                'type' => 'boolean',
                'default'   => TRUE
            ],
        ],
        'pieksy-availability' => [
            'block' => [
                'blocktype' => 'rrze-pieksy/pieksy-availability', // dieser Wert muss angepasst werden
                'blockname' => 'pieksy-availability', // dieser Wert muss angepasst werden
                'title' => __('Pieksy Availability', 'rrze-pieksy'), // Der Titel, der in der Blockauswahl im Gutenberg Editor angezeigt wird
                'category' => 'widgets', // Die Kategorie, in der der Block im Gutenberg Editor angezeigt wird
                'icon' => 'admin-users',  // Das Icon des Blocks
                'show_block' => 'content', // 'right' or 'content' : Anzeige des Blocks im Content-Bereich oder in der rechten Spalte
                'message' => __( 'Find the settings on the right side', 'rrze-pieksy' ) // erscheint bei Auswahl des Blocks, wenn "show_block" auf 'right' gesetzt ist
            ],
            'days' => [
                'default' => '',
                'field_type' => 'text', // Art des Feldes im Gutenberg Editor
                'label' => __( 'Days in advance', 'rrze-pieksy' ),
                'type' => 'number' // Variablentyp der Eingabe
            ],
            'room' => [
                'default' => '',
                'field_type' => 'text', // Art des Feldes im Gutenberg Editor
                'label' => __( 'Room(s)', 'rrze-pieksy' ),
                'type' => 'text' // Variablentyp der Eingabe
            ],
            'seat' => [
                'default' => '',
                'field_type' => 'text', // Art des Feldes im Gutenberg Editor
                'label' => __( 'Seat(s)', 'rrze-pieksy' ),
                'type' => 'text' // Variablentyp der Eingabe
            ],
            'booking_link' => [
                'field_type' => 'toggle',
                'label' => __( 'Show booking link', 'rrze-pieksy' ),
                'type' => 'boolean',
                'default'   => false
            ],

        ],
        'pieksy-qr' => [
            'block' => [
                'blocktype' => 'rrze-pieksy/pieksy-qr', // dieser Wert muss angepasst werden
                'blockname' => 'pieksy-qr', // dieser Wert muss angepasst werden
                'title' => 'Pieksy QR', // Der Titel, der in der Blockauswahl im Gutenberg Editor angezeigt wird
                'category' => 'widgets', // Die Kategorie, in der der Block im Gutenberg Editor angezeigt wird
                'icon' => 'admin-users',  // Das Icon des Blocks
                'show_block' => 'content', // 'right' or 'content' : Anzeige des Blocks im Content-Bereich oder in der rechten Spalte
                'message' => __( 'Find the settings on the right side', 'rrze-pieksy' ) // erscheint bei Auswahl des Blocks, wenn "show_block" auf 'right' gesetzt ist
            ],
            'seat' => [
                'default' => 0,
                'field_type' => 'text', // Art des Feldes im Gutenberg Editor
                'label' => __( 'Seat ID', 'rrze-pieksy' ),
                'type' => 'number' // Variablentyp der Eingabe
            ],
        ]
    ];
}

