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

        $('select#pieksy_room_id').change(updateTable);
        $('select#pieksy_room_id').change(updateLink);
});


function updateTable() {
    var roomId = jQuery('#pieksy_room_id').val();

    jQuery.post(pieksy_ajax.ajax_url, { //POST request
        _ajax_nonce: pieksy_ajax.nonce, //nonce
        action: "ShowOccupancy",      //action
        roomId: roomId,               //data
    }, function(result) {             //callback
        jQuery('div.pieksy-occupancy-container').html(result);
    });
}

function updateLink(){
    var roomId = jQuery('#pieksy_room_id').val();

    jQuery.post(pieksy_ajax.ajax_url, { //POST request
        _ajax_nonce: pieksy_ajax.nonce, //nonce
        action: "ShowOccupancyLinks",      //action
        roomId: roomId,               //data
    }, function(result) {             //callback
        jQuery('div.pieksy-occupancy-links').html(result);
    });
}