"use strict";function updateForm(){var e=jQuery("#pieksy_room").val(),a=jQuery('table.pieksy_calendar input[name="pieksy_date"]:checked').val(),t=jQuery('div.pieksy-time-container input[name="pieksy_time"]:checked').val(),r=jQuery('div.pieksy-seat-container input[name="pieksy_seat"]:checked').val();jQuery("div.pieksy-time-select").remove(),jQuery("div.pieksy-seat-select").remove(),jQuery.post(pieksy_ajax.ajax_url,{_ajax_nonce:pieksy_ajax.nonce,action:"UpdateForm",room:e,date:a,time:t,seat:r},function(e){jQuery("div.pieksy-time-container").append(e.time),jQuery("div.pieksy-seat-container").html(e.seat)})}jQuery(document).ready(function(n){var e=n("#loading").hide();n(document).ajaxStart(function(){e.show()}).ajaxStop(function(){e.hide()}),n("div.pieksy-datetime-container, div.pieksy-seat-container").on("click","input",updateForm),n("div.pieksy-date-container").on("click","a.cal-skip",function(e){e.preventDefault();var a=n("table.pieksy_calendar").data("period"),t=n("table.pieksy_calendar").data("end"),r=n(this).data("direction"),e=n("#pieksy_room").val();n("table.pieksy_calendar").remove(),n("div.pieksy-time-select").remove(),n("div.pieksy-seat-select").remove(),n.post(pieksy_ajax.ajax_url,{_ajax_nonce:pieksy_ajax.nonce,action:"UpdateCalendar",month:a,end:t,direction:r,room:e},function(e){n("div.pieksy-date-container").html(e)})}),updateForm()});