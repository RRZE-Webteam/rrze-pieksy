"use strict";function updateTable(){var a=jQuery("#pieksy_room_id").val();jQuery.post(pieksy_ajax.ajax_url,{_ajax_nonce:pieksy_ajax.nonce,action:"ShowOccupancy",roomId:a},function(a){jQuery("div.pieksy-occupancy-container").html(a)})}function updateLink(){var a=jQuery("#pieksy_room_id").val();jQuery.post(pieksy_ajax.ajax_url,{_ajax_nonce:pieksy_ajax.nonce,action:"ShowOccupancyLinks",roomId:a},function(a){jQuery("div.pieksy-occupancy-links").html(a)})}jQuery(document).ready(function(a){var e=a("#loading").hide();a(document).ajaxStart(function(){e.show()}).ajaxStop(function(){e.hide()}),a("select#pieksy_room_id").change(updateTable),a("select#pieksy_room_id").change(updateLink)});