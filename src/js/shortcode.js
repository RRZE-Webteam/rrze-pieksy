"use strict";

jQuery(document).ready(function($){
    var $loading = $('#loading').hide();
    $(document)
        .ajaxStart(function () {
            $loading.show();
        })
        .ajaxStop(function () {
            $loading.hide();
        });

    $('div.pieksy-datetime-container, div.pieksy-seat-container').on('click', 'input', updateForm);

    $('div.pieksy-date-container').on('click', 'a.cal-skip', function(e) {
        e.preventDefault();
        var monthCurrent = $('table.pieksy_calendar').data('period');
        var endDate = $('table.pieksy_calendar').data('end');
        var direction = $(this).data('direction');
        var room = $('#pieksy_room').val();
        $('table.pieksy_calendar').remove();
        $('div.pieksy-time-select').remove();
        $('div.pieksy-seat-select').remove();
        $.post(pieksy_ajax.ajax_url, {         //POST request
            _ajax_nonce: pieksy_ajax.nonce,     //nonce
            action: "UpdateCalendar",            //action
            month: monthCurrent ,                  //data
            end: endDate,
            direction: direction,
            room: room,
        }, function(result) {                 //callback
            $('div.pieksy-date-container').html(result);
        });
    });

    updateForm();
});

function updateForm() {
    var room = jQuery('#pieksy_room').val();
    var date = jQuery('table.pieksy_calendar input[name="pieksy_date"]:checked').val();
    var time = jQuery('div.pieksy-time-container input[name="pieksy_time"]:checked').val();
    var seat = jQuery('div.pieksy-seat-container input[name="pieksy_seat"]:checked').val();
    // console.log(room);
    // console.log(date);
    // console.log(time);
    jQuery('div.pieksy-time-select').remove();
    jQuery('div.pieksy-seat-select').remove();
    jQuery.post(pieksy_ajax.ajax_url, {         //POST request
        _ajax_nonce: pieksy_ajax.nonce,     //nonce
        action: "UpdateForm",            //action
        room: room,                  //data
        date: date,          //data
        time: time,          //data
        seat: seat,          //data
    }, function(result) {                 //callback
        //console.log(result);
        jQuery('div.pieksy-time-container').append(result['time']);
        jQuery('div.pieksy-seat-container').html(result['seat']);
    });
}
