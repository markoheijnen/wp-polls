/*
+----------------------------------------------------------------+
|																							|
|	WordPress Plugin: WP-Polls										|
|	Copyright © 2012 Lester "GaMerZ" Chan									|
|																							|
|	File Written By:																	|
|	- Lester "GaMerZ" Chan															|
|	- http://lesterchan.net															|
|																							|
|	File Information:																	|
|	- Polls Javascript File															|
|	- wp-content/plugins/wp-polls/polls-js.js									|
|																							|
+----------------------------------------------------------------+
*/


// Variables
var poll_id = 0;
var poll_answer_id = '';
var is_being_voted = false;
pollsL10n.show_loading = parseInt( pollsL10n.show_loading, 10 );
pollsL10n.show_fading = parseInt( pollsL10n.show_fading, 10 );

// When User Vote For Poll
function poll_vote(current_poll_id) {
	if( ! is_being_voted ) {
		set_is_being_voted(true);
		poll_id = current_poll_id;
		poll_answer_id = '';
		poll_multiple_ans = 0;
		poll_multiple_ans_count = 0;

		if(jQuery('#poll_multiple_ans_' + poll_id).length) {
			poll_multiple_ans = parseInt( jQuery('#poll_multiple_ans_' + poll_id).val(), 10 );
		}

		jQuery('#polls_form_' + poll_id + ' input:checkbox, #polls_form_' + poll_id + ' input:radio').each(function(i){
			if (jQuery(this).is(':checked')) {
				if(poll_multiple_ans > 0) {
					poll_answer_id = jQuery(this).val() + ',' + poll_answer_id;
					poll_multiple_ans_count++;
				}
				else {
					poll_answer_id = parseInt( jQuery(this).val(), 10 );
				}
			}
		});

		if(poll_multiple_ans > 0) {
			if(poll_multiple_ans_count > 0 && poll_multiple_ans_count <= poll_multiple_ans) {
				poll_answer_id = poll_answer_id.substring(0, (poll_answer_id.length-1));
				poll_process();
			}
			else if(poll_multiple_ans_count === 0) {
				set_is_being_voted(false);
				alert(pollsL10n.text_valid);
			}
			else {
				set_is_being_voted(false);
				alert(pollsL10n.text_multiple + ' ' + poll_multiple_ans);
			}
		}
		else {
			if(poll_answer_id > 0) {
				poll_process();
			}
			else {
				set_is_being_voted(false);
				alert(pollsL10n.text_valid);
			}
		}
	}
	else {
		alert(pollsL10n.text_wait);
	}
}

// Process Poll (User Click "Vote" Button)
function poll_process() {
	if(pollsL10n.show_fading) {
		jQuery('#polls-' + poll_id).fadeTo('def', 0, function () {
			_perform_request_process();
		});
	}
	else {
		_perform_request_process();
	}
}

function _perform_request_process() {
	poll_nonce   = jQuery('#poll_' + poll_id + '_nonce').val();
	poll_captcha = jQuery('#captcha_code-' + poll_id ).val();

	if( pollsL10n.show_loading ) {
		jQuery('#polls-' + poll_id + '-loading').show();
	}

	var data = {
		action: 'wp_polls',
		view: 'process',
		poll_id: poll_id,
		poll_answer: poll_answer_id,
		poll_nonce: poll_nonce,
		poll_captcha: poll_captcha
	};

	jQuery.ajax({
		type: 'POST',
		url: pollsL10n.ajax_url,
		data: data,
		cache: false,
		success: poll_process_success
	});
}

function _perform_request_result() {
	poll_nonce = jQuery('#poll_' + poll_id + '_nonce').val();

	if(pollsL10n.show_loading) {
		jQuery('#polls-' + poll_id + '-loading').show();
	}

	var data = {
		action: 'wp_polls',
		view: 'result',
		poll_id: poll_id,
		poll_nonce: poll_nonce
	};

	jQuery.ajax({
		type: 'GET',
		url: pollsL10n.ajax_url,
		data: data,
		cache: false,
		success: poll_process_success
	});
}

function _perform_request_booth() {
	poll_nonce = jQuery('#poll_' + poll_id + '_nonce').val();

	if(pollsL10n.show_loading) {
		jQuery('#polls-' + poll_id + '-loading').show();
	}

	var data = {
		action: 'wp_polls',
		view: 'booth',
		poll_id: poll_id,
		poll_nonce: poll_nonce
	};

	jQuery.ajax({
		type: 'GET',
		url: pollsL10n.ajax_url,
		data: data,
		cache: false,
		success: poll_process_success
	});
}

// Poll's Result (User Click "View Results" Link)
function poll_result(current_poll_id) {
	if( ! is_being_voted ) {
		set_is_being_voted(true);
		poll_id = current_poll_id;

		if( pollsL10n.show_fading ) {
			jQuery('#polls-' + poll_id).fadeTo('def', 0, function () {
				_perform_request_result();
			});
		}
		else {
			_perform_request_result();
		}
	}
	else {
		alert(pollsL10n.text_wait);
	}
}

// Poll's Voting Booth  (User Click "Vote" Link)
function poll_booth(current_poll_id) {
	if( ! is_being_voted ) {
		set_is_being_voted(true);
		poll_id = current_poll_id;

		if(pollsL10n.show_fading) {
			jQuery('#polls-' + poll_id).fadeTo('def', 0, function () {
				_perform_request_booth();
			});
		}
		else {
			_perform_request_booth();
		}
	}
	else {
		alert(pollsL10n.text_wait);
	}
}

// Poll Process Successfully
function poll_process_success(data) {
	jQuery('#polls-' + poll_id).replaceWith(data);

	if(pollsL10n.show_loading) {
		jQuery('#polls-' + poll_id + '-loading').hide();
	}

	if(pollsL10n.show_fading) {
		jQuery('#polls-' + poll_id).fadeTo('def', 1, function () {
			set_is_being_voted(false);
		});
	}
	else {
		set_is_being_voted(false);
	}
}

// Set is_being_voted Status
function set_is_being_voted(voted_status) {
	is_being_voted = voted_status;
}