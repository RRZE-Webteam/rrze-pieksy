"use strict";

jQuery(document).ready(function ($) {
    var DATE_FORMAT = rrze_pieksy_admin.dateformat,
        TEXT_CANCEL = rrze_pieksy_admin.text_cancel,
        TEXT_CANCELLED = rrze_pieksy_admin.text_cancelled,
        TEXT_CONFIRMED = rrze_pieksy_admin.text_confirmed,
        API_AJAXURL = rrze_pieksy_admin.ajaxurl;

	function bookingAction(type, button) {
		var id = button.attr('data-id'),
			href = button.attr('href');

		jQuery.ajax({
			type: "POST",
			url: API_AJAXURL,
			data: {
				action: 'booking_action',
				id: id,
				type: type
			}
		}).fail(function (jqXHR) {
			console.error("AJAX request failed");
		}).done(function(data) {
			data = JSON.parse(data);
			jQuery.ajax({
				type: "GET",
				url: href,
			}).fail(function (jqXHR) {
				console.error("AJAX request failed");
			}).done(function(data) {
				if (type == 'confirm') {
					button.addClass('rrze-pieksy-confirmed').attr('disabled', 'disabled').html(TEXT_CONFIRMED);
				} else {
					button.attr('disabled', 'disabled').html(TEXT_CANCELLED);
				}
			});

		});

	}

    $('.rrze-pieksy-confirm').click(function(e) {
        e.preventDefault();
        bookingAction('confirm', jQuery(this));
        return false;
    });

    $('.rrze-pieksy-cancel').click(function(e) {
        e.preventDefault();
        if (confirm(TEXT_CANCEL)) {
            bookingAction('cancel', jQuery(this));
        }
        return false;
    });


    /*
     * CPT Booking Backend
     */

    // Set search string in result
    const urlParams = new URLSearchParams(window.location.search);
    if (urlParams.get('s')){
        $('.subtitle').html( $('.subtitle').text().substring(0, $('.subtitle').text().length - 1) + urlParams.get('s') + '&rdquo;');
    }

	// Hide booking mode info text on loading, insert booking mode info icon
	$('select#rrze-pieksy-room-bookingmode').after('<a><span class="dashicons dashicons-editor-help info-bookingmode" title="Informationen zum Buchungsmodus anzeigen" aria-hidden="true"></span><span class="screen-reader-text">Informationen zum Buchungsmodus anzeigen</span></a>');
	$('.cmb2-id-rrze-pieksy-room-bookingmode .cmb2-metabox-description').hide();
	$('.cmb2-id-rrze-pieksy-room-bookingmode').find('.info-bookingmode').click(function() {
		$('.cmb2-id-rrze-pieksy-room-bookingmode .cmb2-metabox-description').slideToggle();
	});

	// CPT Room: Set timeslot remove buttons to disabled if timeslot bookings for this timeslot exist
	var timeslotgroup = $('body.wp-admin.post-type-room #rrze-pieksy-room-timeslots_repeat div.cmb-repeatable-grouping');
	$(timeslotgroup).each(function (index) {
		// console.log($(this).find("input[id$='rrze-pieksy-room-starttime']").prop('disabled'));
		if ($(this).find("input[id$='rrze-pieksy-room-starttime']").prop('disabled') == true) {
			$(this).find('button.cmb-remove-group-row').prop({
				disabled: true
			});
		}
	});

	// Remove disabled attribute from protected timeslot fields before submit
	$('body.wp-admin.post-type-room form, body.wp-admin.post-type-booking form').submit(function(e) {
		$('#postbox-container-2 :disabled').each(function(e) {
			$(this).removeAttr('disabled');
		})
	});

	// Fill date/time inputs with timeslot selector
	var details = jQuery('div#rrze-pieksy-booking-details'),
		seat = details.find('select#rrze-pieksy-booking-seat'),
		startdate = details.find('input#rrze-pieksy-booking-start_date'),
		starttime = details.find('input#rrze-pieksy-booking-start_time'),
		enddate = details.find('input#rrze-pieksy-booking-end_date'),
		endtime = details.find('input#rrze-pieksy-booking-end_time');

	starttime.attr('disabled', 'disabled');

	seat.change(function() {
		startdate.val('');
		starttime.val('');
		enddate.val('');
		endtime.val('');
		jQuery('div.select_timeslot_container').remove();
	});

	startdate.change(function() {
		enddate.val(startdate.val());
		starttime.val('');
		endtime.val('');
		if (seat.val() == '' || startdate.val() == '') {
			alert(rrze_pieksy_admin.alert_no_seat_date);
			startdate.val('');
			enddate.val('');
		} else {
			jQuery('div.select_timeslot_container').remove();
			jQuery.post(API_AJAXURL, {         //POST request
				action: "ShowTimeslots",            //action
				seat: seat.val(),                  //data
				date: startdate.val(),          //data
			}, function (result) {                 //callback
				// console.log(result);
				if (result != false){
					jQuery('input#rrze-pieksy-booking-start_time').after(result);
				}
			});
		}
	});

	$('div.cmb2-id-rrze-pieksy-booking-start').on('change', 'select.select_timeslot', (function() {
		var select_start = jQuery(this).val();
		var select_end   = jQuery(this).find(':selected').data('end');
		//console.log(select_start);
		//console.log(select_end);
		starttime.val(select_start);
		endtime.val(select_end);
	}));


    /* trigger reservations' details - see https://github.com/RRZE-Webteam/rrze-pieksy/issues/92 */
	var bookingModeSelect = $('select#rrze-pieksy-room-bookingmode');
	var bookingMode = bookingModeSelect.val();
	var instantCheckInRow = $('div.cmb2-id-rrze-pieksy-room-instant-check-in');
	var autoConfirmationInput = $('input#rrze-pieksy-room-auto-confirmation');
	var autoConfirmationChecked = autoConfirmationInput.is(':checked');
	var forceConfirmationInput = $('input#rrze-pieksy-room-force-to-checkin');
	var forceConfirmationChecked = forceConfirmationInput.is(':checked');
	triggerModeVisibility(bookingMode);
	triggerInstant(autoConfirmationChecked);
	// triggerCheckInTime(forceConfirmationChecked);

	bookingModeSelect.on('change', function() {
		var bookingMode = $('option:selected',this).val();
		triggerModeVisibility(bookingMode);
	});
	autoConfirmationInput.click(function() {
		var autoConfirmationChecked = $(this).is(':checked');
		triggerInstant(autoConfirmationChecked);
	});
	forceConfirmationInput.click(function() {
		var forceConfirmationChecked = $(this).is(':checked');
		// triggerCheckInTime(forceConfirmationChecked);
	});

	function triggerModeVisibility(bookingMode){
		$('div#cmb2-metabox-rrze_pieksy_general-meta div.cmb-row.hide-'+bookingMode).slideUp();
		$('div#cmb2-metabox-rrze_pieksy_general-meta div.cmb-row').not('.hide-'+bookingMode).slideDown();
		$('div#cmb2-metabox-rrze_pieksy_general-meta div.cmb-row.hide-'+bookingMode+' input').prop('checked', false);
		if (bookingMode === 'check-only') {
			$('input#rrze-pieksy-room-instant-check-in').prop('checked', true);
			$('input#rrze-pieksy-room-auto-confirmation').prop('checked', true);
		}
	}

	function triggerInstant(autoConfirmationChecked){
		if (autoConfirmationChecked === true) {
			var bookingMode = $('option:selected', bookingModeSelect).val();
			if (bookingMode === 'reservation' || bookingMode === 'check-only') {
				instantCheckInRow.slideDown();
			}
		} else {
			instantCheckInRow.slideUp();
			$('#rrze-pieksy-room-instant-check-in').prop('checked', false);
		}
	}

	// function triggerCheckInTime(forceConfirmationChecked) {
	// 	if (forceConfirmationChecked === true) {
	// 		$('div.cmb2-id-rrze-pieksy-room-check-in-time').slideDown();
	// 	} else {
	// 		$('div.cmb2-id-rrze-pieksy-room-check-in-time').slideUp();
	// 	}
	// }
    
    // prevent copy & paste and disable mouse right click and cut for booking overview
    $('body.post-type-booking table.wp-list-table').bind('cut copy paste', function (e) {
        e.preventDefault();
    });
    $('body.post-type-booking table.wp-list-table').on("contextmenu",function(e){
        return false;
    });
});
