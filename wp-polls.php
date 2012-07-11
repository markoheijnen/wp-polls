<?php
/*
Plugin Name: WP-Polls
Plugin URI: http://lesterchan.net/portfolio/programming/php/
Description: Adds an AJAX poll system to your WordPress blog. You can easily include a poll into your WordPress's blog post/page. WP-Polls is extremely customizable via templates and css styles and there are tons of options for you to choose to ensure that WP-Polls runs the way you wanted. It now supports multiple selection of answers.
Version: 3.0
Author: Lester 'GaMerZ' Chan, Marko Heijnen, Van Ons
Author URI: http://lesterchan.net
*/


/*  
	Copyright 2012  Lester Chan  (email : lesterchan@gmail.com)

    This program is free software; you can redistribute it and/or modify
    it under the terms of the GNU General Public License as published by
    the Free Software Foundation; either version 2 of the License, or
    (at your option) any later version.

    This program is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details.

    You should have received a copy of the GNU General Public License
    along with this program; if not, write to the Free Software
    Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA
*/

define( 'WP_POLLS_VERSION', '3.0' );

include 'class.poll.php';
include 'polls-admin.php';
include 'widget.polls.php';
include 'support.wp-stats.php';

if( ! class_exists('Securimage') ) {
	include 'securimage/securimage.php';
}

class WP_Polls {
	private $version     = '3.0';
	private $poll_loaded = false;

	function __construct() {
		if( ! session_id() ) {
			session_start();
		}

		new Polls_Admin();

		add_action( 'plugins_loaded', array( &$this, 'init_database_vars' ) );
		add_action( 'init', array( &$this, 'polls_textdomain' ) );

		add_action( 'widgets_init', array( &$this, 'widget_polls_init' ) );

		add_action( 'wp_head', array( &$this, 'poll_head_scripts' ) );
		add_action( 'wp_poll_loaded', array( &$this, 'poll_scripts' ) );

		add_shortcode( 'page_polls', array( &$this, 'poll_page_shortcode' ) );
		add_shortcode( 'poll', array( &$this, 'poll_shortcode' ) );

		add_action( 'wp_ajax_wp_polls', array( &$this, 'vote_poll' ) );
		add_action( 'wp_ajax_nopriv_wp_polls', array( &$this, 'vote_poll' ) );
	}

	### Polls Table Name
	function init_database_vars() {
		global $wpdb;

		$wpdb->pollsq  = $wpdb->prefix . 'pollsq';
		$wpdb->pollsa  = $wpdb->prefix . 'pollsa';
		$wpdb->pollsip = $wpdb->prefix . 'pollsip';
	}

	### Create Text Domain For Translations
	function polls_textdomain() {
		load_plugin_textdomain( 'wp-polls', false, 'wp-polls' );
	}

	### Function: Init WP-Polls Widget
	function widget_polls_init() {
		register_widget('WP_Widget_Polls');
	}

	### Function: Print Polls Stylesheets That Are Dynamic And jQuery At The Top
	function poll_head_scripts() {
		$pollbar = get_option('poll_bar');

		echo '<style type="text/css">' . "\n";	
		echo '.wp-polls .pollbar {' . "\n";
		echo "\t" . 'margin: 1px;' . "\n";
		echo "\t" . 'font-size: ' . ( $pollbar['height'] - 2 ) .'px;' . "\n";
		echo "\t" . 'line-height: ' . $pollbar['height'].'px;' . "\n";
		echo "\t" . 'height: ' . $pollbar['height'] . 'px;' . "\n";

		if( $pollbar['style'] == 'use_css' ) {
			echo "\t" . 'background: #' . $pollbar['background'] . ';' . "\n";
		}
		else {
			echo "\t" . 'background-image: url(\'' . plugins_url('wp-polls/images/' . $pollbar['style'] . '/pollbg.gif') . '\');' . "\n";
		}

		echo "\t" . 'border: 1px solid #' . $pollbar['border'] . ';' . "\n";
		echo '}' . "\n";
		echo '</style>' . "\n";

		wp_enqueue_script('jquery');
	}

	### Function: Enqueue Polls JavaScripts/CSS
	function poll_scripts() {
		if( $this->poll_loaded )
			return;

		$this->poll_loaded = true;

		if( @ file_exists( TEMPLATEPATH . '/polls-css.css' ) ) {
			wp_enqueue_style( 'wp-polls', get_stylesheet_directory_uri() . '/polls-css.css', false, $this->version, 'all' );
		}
		else {
			wp_enqueue_style( 'wp-polls', plugins_url('wp-polls/polls-css.css'), false, $this->version, 'all' );
		}

		if( is_rtl() ) {
			if( @ file_exists( TEMPLATEPATH . '/polls-css-rtl.css' ) ) {
				wp_enqueue_style( 'wp-polls-rtl', get_stylesheet_directory_uri() . '/polls-css-rtl.css', false, $this->version, 'all' );
			}
			else {
				wp_enqueue_style( 'wp-polls-rtl', plugins_url('wp-polls/polls-css-rtl.css'), false, $this->version, 'all' );
			}
		}

		$poll_ajax_style = get_option('poll_ajax_style');
		$pollbar = get_option('poll_bar');

		wp_enqueue_script( 'wp-polls', plugins_url('wp-polls/polls-js.js'), array( 'jquery' ), $this->version, true );

		wp_localize_script( 'wp-polls', 'pollsL10n', array(
			'ajax_url' => admin_url( 'admin-ajax.php' ),
			'text_wait' => __( 'Your last request is still being processed. Please wait a while ...', 'wp-polls' ),
			'text_valid' => __( 'Please choose a valid poll answer.', 'wp-polls' ),
			'text_multiple' => __( 'Maximum number of choices allowed: ', 'wp-polls' ),
			'show_loading' => intval( $poll_ajax_style['loading'] ),
			'show_fading' => intval( $poll_ajax_style['fading'] )
		));
	}




	### Function: Short Code For Inserting Polls Archive Into Page
	function poll_page_shortcode($atts) {
		return polls_archive();
	}

	### Function: Short Code For Inserting Polls Into Posts
	function poll_shortcode( $atts ) {
		extract( shortcode_atts( array( 'id' => 0, 'type' => 'vote' ), $atts ) );

		do_action( 'wp_poll_loaded', $id, $type );

		if( ! is_feed() ) {
			$id = intval( $id );
			if( $type == 'vote' ) {
				$poll = new WP_Polls_Poll( $id );
				return $poll->get( false );
			}
			elseif( $type == 'result' ) {
				return display_pollresult( $id );
			}
		}
		else {
			return __('Note: There is a poll embedded within this post, please visit the site to participate in this post\'s poll.', 'wp-polls');
		}
	}



	### Function: Vote Poll
	function vote_poll() {
		// Load Headers
		header('Content-Type: text/html; charset=' . get_option('blog_charset') );

		// Get Poll ID
		$poll_id = ( isset( $_REQUEST['poll_id'] ) ? intval( $_REQUEST['poll_id'] ) : 0);

		// Ensure Poll ID Is Valid
		if( $poll_id == 0 )
		{
			_e( 'Invalid Poll ID', 'wp-polls' );
			exit();
		}

		// Verify Referer
		if( ! isset( $_REQUEST['poll_nonce'] ) || ! check_ajax_referer( 'poll_' . $poll_id . '-nonce', 'poll_nonce', false ) )
		{
			_e( 'Failed To Verify Referrer', 'wp-polls' );
			exit();
		}

		$poll = new WP_Polls_Poll( $poll_id );
		$poll->vote();

		exit();
	}
}

new WP_Polls();



### Function: Get IP Address
if(!function_exists('get_ipaddress')) {
	function get_ipaddress() {
		if (empty($_SERVER["HTTP_X_FORWARDED_FOR"])) {
			$ip_address = $_SERVER["REMOTE_ADDR"];
		} else {
			$ip_address = $_SERVER["HTTP_X_FORWARDED_FOR"];
		}
		if(strpos($ip_address, ',') !== false) {
			$ip_address = explode(',', $ip_address);
			$ip_address = $ip_address[0];
		}
		return esc_attr($ip_address);
	}
}


### Function: Get Poll Question Based On Poll ID
if(!function_exists('get_poll_question')) {
	function get_poll_question($poll_id) {
		global $wpdb;
		$poll_id = intval($poll_id);
		$poll_question = $wpdb->get_var("SELECT pollq_question FROM $wpdb->pollsq WHERE pollq_id = $poll_id LIMIT 1");
		return stripslashes($poll_question);
	}
}


### Function: Get Poll Total Questions
if(!function_exists('get_pollquestions')) {
	function get_pollquestions($display = true) {
		global $wpdb;
		$totalpollq = intval($wpdb->get_var("SELECT COUNT(pollq_id) FROM $wpdb->pollsq"));
		if($display) {
			echo $totalpollq;
		} else {
			return $totalpollq;
		}
	}
}


### Function: Get Poll Total Answers
if(!function_exists('get_pollanswers')) {
	function get_pollanswers($display = true) {
		global $wpdb;
		$totalpolla = intval($wpdb->get_var("SELECT COUNT(polla_aid) FROM $wpdb->pollsa"));
		if($display) {
			echo $totalpolla;
		} else {
			return $totalpolla;
		}
	}
}


### Function: Get Poll Total Votes
if(!function_exists('get_pollvotes')) {
	function get_pollvotes( $display = true ) {
		global $wpdb;
		$totalvotes = intval($wpdb->get_var("SELECT SUM(pollq_totalvotes) FROM $wpdb->pollsq"));
		if($display) {
			echo $totalvotes;
		} else {
			return $totalvotes;
		}
	}
}


### Function: Get Poll Total Voters
if( ! function_exists('get_pollvoters') ) {
	function get_pollvoters( $display = true ) {
		global $wpdb;

		$totalvoters = intval( $wpdb->get_var( "SELECT SUM(pollq_totalvoters) FROM $wpdb->pollsq" ) );

		if( $display ) {
			echo $totalvoters;
		}
		else {
			return $totalvoters;
		}
	}
}


### Function: Check Voted To Get Voted Answer
function check_voted_multiple($poll_id, $polls_ips) {
	if( ! empty( $_COOKIE["voted_$poll_id"] ) ) {
		return explode( ',', $_COOKIE["voted_$poll_id"] );
	}
	else {
		if( $polls_ips ) {
			return $polls_ips;
		}
		else {
			return array();
		}
	}
}


### Function: Polls Archive Link
function polls_archive_link($page) {
	$polls_archive_url = get_option('poll_archive_url');
	if($page > 0) {		
		if(strpos($polls_archive_url, '?') !== false) {
			$polls_archive_url = "$polls_archive_url&amp;poll_page=$page";
		} else {
			$polls_archive_url = "$polls_archive_url?poll_page=$page";
		}
	}
	return $polls_archive_url;
}


### Function: Displays Polls Archive Link
function display_polls_archive_link($display = true) {
	$template_pollarchivelink = stripslashes(get_option('poll_template_pollarchivelink'));
	$template_pollarchivelink = str_replace("%POLL_ARCHIVE_URL%", get_option('poll_archive_url'), $template_pollarchivelink);
	if($display) {
		echo $template_pollarchivelink;
	} else{
		return $template_pollarchivelink;
	}
}


### Function: Display Polls Archive
function polls_archive() {
	global $wpdb, $in_pollsarchive;
	// Polls Variables
	$in_pollsarchive = true;
	$page = intval($_GET['poll_page']);
	$polls_questions = array();
	$polls_answers = array();
	$polls_ip = array();
	$polls_perpage = intval(get_option('poll_archive_perpage'));
	$poll_questions_ids = '0';
	$poll_voted = false;
	$poll_voted_aid = 0;
	$poll_id = 0;
	$pollsarchive_output_archive = '';
	$polls_type = intval(get_option('poll_archive_displaypoll'));
	$polls_type_sql = '';
	// Determine What Type Of Polls To Show
	switch($polls_type) {
		case 1:
			$polls_type_sql = 'pollq_active = 0';
			break;
		case 2:
			$polls_type_sql = 'pollq_active = 1';
			break;
		case 3:
			$polls_type_sql = 'pollq_active IN (0,1)';
			break;
	}
	// Get Total Polls
	$total_polls = $wpdb->get_var("SELECT COUNT(pollq_id) FROM $wpdb->pollsq WHERE $polls_type_sql AND pollq_active != -1");

	// Calculate Paging
	$numposts = $total_polls;
	$perpage = $polls_perpage;
	$max_page = ceil($numposts/$perpage);	
	if(empty($page) || $page == 0) {
		$page = 1;
	}
	$offset = ($page-1) * $perpage;
	$pages_to_show = 10;
	$pages_to_show_minus_1 = $pages_to_show-1;
	$half_page_start = floor($pages_to_show_minus_1/2);
	$half_page_end = ceil($pages_to_show_minus_1/2);
	$start_page = $page - $half_page_start;
	if($start_page <= 0) {
		$start_page = 1;
	}
	$end_page = $page + $half_page_end;
	if(($end_page - $start_page) != $pages_to_show_minus_1) {
		$end_page = $start_page + $pages_to_show_minus_1;
	}
	if($end_page > $max_page) {
		$start_page = $max_page - $pages_to_show_minus_1;
		$end_page = $max_page;
	}
	if($start_page <= 0) {
		$start_page = 1;
	}
	if(($offset + $perpage) > $numposts) { 
		$max_on_page = $numposts; 
	} else { 
		$max_on_page = ($offset + $perpage); 
	}
	if (($offset + 1) > ($numposts)) { 
		$display_on_page = $numposts; 
	} else { 
		$display_on_page = ($offset + 1); 
	}
	
	// Get Poll Questions
	$questions = $wpdb->get_results("SELECT * FROM $wpdb->pollsq WHERE $polls_type_sql ORDER BY pollq_id DESC LIMIT $offset, $polls_perpage");
	if($questions) {
		foreach($questions as $question) {
			$polls_questions[] = array('id' => intval($question->pollq_id), 'question' => stripslashes($question->pollq_question), 'timestamp' => $question->pollq_timestamp, 'totalvotes' => intval($question->pollq_totalvotes), 'start' => $question->pollq_timestamp, 'end' => trim($question->pollq_expiry), 'multiple' => intval($question->pollq_multiple), 'totalvoters' => intval($question->pollq_totalvoters));
			$poll_questions_ids .= intval($question->pollq_id).', ';
		}
		$poll_questions_ids = substr($poll_questions_ids, 0, -2);
	}

	// Get Poll Answers
	$answers = $wpdb->get_results("SELECT polla_aid, polla_qid, polla_answers, polla_votes FROM $wpdb->pollsa WHERE polla_qid IN ($poll_questions_ids) ORDER BY ".get_option('poll_ans_result_sortby').' '.get_option('poll_ans_result_sortorder'));
	if($answers) {
		foreach($answers as $answer) {
			$polls_answers[intval($answer->polla_qid)][] = array('aid' => intval($answer->polla_aid), 'qid' => intval($answer->polla_qid), 'answers' => stripslashes($answer->polla_answers), 'votes' => intval($answer->polla_votes));
		}
	}

	// Get Poll IPs
	$ips = $wpdb->get_results("SELECT pollip_qid, pollip_aid FROM $wpdb->pollsip WHERE pollip_qid IN ($poll_questions_ids) AND pollip_ip = '".get_ipaddress()."' ORDER BY pollip_qid ASC");
	if($ips) {
		foreach($ips as $ip) {
			$polls_ips[intval($ip->pollip_qid)][] = intval($ip->pollip_aid);
		}
	}
	// Poll Archives
	$pollsarchive_output_archive .= "<div class=\"wp-polls wp-polls-archive\">\n";
	foreach($polls_questions as $polls_question) {
		// Most/Least Variables
		$poll_most_answer = '';
		$poll_most_votes = 0;
		$poll_most_percentage = 0;
		$poll_least_answer = '';
		$poll_least_votes = 0;
		$poll_least_percentage = 0;
		// Is The Poll Total Votes 0?
		$poll_totalvotes_zero = true;
		if($polls_question['totalvotes'] > 0) {
			$poll_totalvotes_zero = false;
		}
			$poll_start_date = mysql2date(sprintf(__('%s @ %s', 'wp-polls'), get_option('date_format'), get_option('time_format')), gmdate('Y-m-d H:i:s', $polls_question['start']));
			if(empty($polls_question['end'])) {
				$poll_end_date  = __('No Expiry', 'wp-polls');
			} else {
				$poll_end_date  = mysql2date(sprintf(__('%s @ %s', 'wp-polls'), get_option('date_format'), get_option('time_format')), gmdate('Y-m-d H:i:s', $polls_question['end'])); 
			}
		// Archive Poll Header
		$template_archive_header = stripslashes(get_option('poll_template_pollarchiveheader'));
		// Poll Question Variables
		$template_question = stripslashes(get_option('poll_template_resultheader'));
		$template_question = str_replace("%POLL_QUESTION%", $polls_question['question'], $template_question);
		$template_question = str_replace("%POLL_ID%", $polls_question['id'], $template_question);
		$template_question = str_replace("%POLL_TOTALVOTES%", number_format_i18n($polls_question['totalvotes']), $template_question);
		$template_question = str_replace("%POLL_TOTALVOTERS%", number_format_i18n($polls_question['totalvoters']), $template_question);
		$template_question = str_replace("%POLL_START_DATE%", $poll_start_date, $template_question);
		$template_question = str_replace("%POLL_END_DATE%", $poll_end_date, $template_question);
		if($polls_question['multiple'] > 0) {
			$template_question = str_replace("%POLL_MULTIPLE_ANS_MAX%", $polls_question['multiple'], $template_question);
		} else {
			$template_question = str_replace("%POLL_MULTIPLE_ANS_MAX%", '1', $template_question);
		}
		// Print Out Result Header Template
		$pollsarchive_output_archive .= $template_archive_header;
		$pollsarchive_output_archive .= $template_question;
		// Store The Percentage Of The Poll
		$poll_answer_percentage_array = array();
		foreach($polls_answers[$polls_question['id']] as $polls_answer) {
			// Calculate Percentage And Image Bar Width
			if(!$poll_totalvotes_zero) {
				if($polls_answer['votes'] > 0) {
					$poll_answer_percentage = round((($polls_answer['votes']/$polls_question['totalvoters'])*100));
					$poll_answer_imagewidth = round($poll_answer_percentage*0.9);
				} else {
					$poll_answer_percentage = 0;
					$poll_answer_imagewidth = 1;
				}
			} else {
				$poll_answer_percentage = 0;
				$poll_answer_imagewidth = 1;
			}
			// Make Sure That Total Percentage Is 100% By Adding A Buffer To The Last Poll Answer
			if($polls_question['multiple'] == 0) {
				$poll_answer_percentage_array[] = $poll_answer_percentage;
				if(sizeof($poll_answer_percentage_array) == sizeof($polls_answers[$polls_question['id']])) {
					$percentage_error_buffer = 100 - array_sum($poll_answer_percentage_array);
					$poll_answer_percentage = $poll_answer_percentage + $percentage_error_buffer;
					if($poll_answer_percentage < 0) {
						$poll_answer_percentage = 0;
					}
				}
			}
			// Let User See What Options They Voted
			if(in_array($polls_answer['aid'], check_voted_multiple($polls_question['id'], $polls_ips[$polls_question['id']]))) {				
				// Results Body Variables
				$template_answer = stripslashes(get_option('poll_template_resultbody2'));
				$template_answer = str_replace("%POLL_ID%", $polls_question['id'], $template_answer);
				$template_answer = str_replace("%POLL_ANSWER_ID%", $polls_answer['aid'], $template_answer);
				$template_answer = str_replace("%POLL_ANSWER%", $polls_answer['answers'], $template_answer);
				$template_answer = str_replace("%POLL_ANSWER_TEXT%", htmlspecialchars(strip_tags($polls_answer['answers'])), $template_answer);
				$template_answer = str_replace("%POLL_ANSWER_VOTES%", number_format_i18n($polls_answer['votes']), $template_answer);
				$template_answer = str_replace("%POLL_ANSWER_PERCENTAGE%", $poll_answer_percentage, $template_answer);
				$template_answer = str_replace("%POLL_ANSWER_IMAGEWIDTH%", $poll_answer_imagewidth, $template_answer);
				// Print Out Results Body Template
				$pollsarchive_output_archive .= $template_answer;
			} else {
				// Results Body Variables
				$template_answer = stripslashes(get_option('poll_template_resultbody'));
				$template_answer = str_replace("%POLL_ID%", $polls_question['id'], $template_answer);
				$template_answer = str_replace("%POLL_ANSWER_ID%", $polls_answer['aid'], $template_answer);
				$template_answer = str_replace("%POLL_ANSWER%", $polls_answer['answers'], $template_answer);
				$template_answer = str_replace("%POLL_ANSWER_TEXT%", htmlspecialchars(strip_tags($polls_answer['answers'])), $template_answer);
				$template_answer = str_replace("%POLL_ANSWER_VOTES%", number_format_i18n($polls_answer['votes']), $template_answer);
				$template_answer = str_replace("%POLL_ANSWER_PERCENTAGE%", $poll_answer_percentage, $template_answer);
				$template_answer = str_replace("%POLL_ANSWER_IMAGEWIDTH%", $poll_answer_imagewidth, $template_answer);
				// Print Out Results Body Template
				$pollsarchive_output_archive .= $template_answer;
			}
			// Get Most Voted Data
			if($polls_answer['votes'] > $poll_most_votes) {
				$poll_most_answer = $polls_answer['answers'];
				$poll_most_votes = $polls_answer['votes'];
				$poll_most_percentage = $poll_answer_percentage;
			}
			// Get Least Voted Data
			if($poll_least_votes == 0) {
				$poll_least_votes = $polls_answer['votes'];
			}
			if($polls_answer['votes'] <= $poll_least_votes) {
				$poll_least_answer = $polls_answer['answers'];
				$poll_least_votes = $polls_answer['votes'];
				$poll_least_percentage = $poll_answer_percentage;
			}
		}
		// Results Footer Variables
		$template_footer = stripslashes(get_option('poll_template_resultfooter'));
		$template_footer = str_replace("%POLL_ID%", $polls_question['id'], $template_footer);
		$template_footer = str_replace("%POLL_START_DATE%", $poll_start_date, $template_footer);
		$template_footer = str_replace("%POLL_END_DATE%", $poll_end_date, $template_footer);
		$template_footer = str_replace("%POLL_TOTALVOTES%", number_format_i18n($polls_question['totalvotes']), $template_footer);
		$template_footer = str_replace("%POLL_TOTALVOTERS%", number_format_i18n($polls_question['totalvoters']), $template_footer);
		$template_footer = str_replace("%POLL_MOST_ANSWER%", $poll_most_answer, $template_footer);
		$template_footer = str_replace("%POLL_MOST_VOTES%", number_format_i18n($poll_most_votes), $template_footer);
		$template_footer = str_replace("%POLL_MOST_PERCENTAGE%", $poll_most_percentage, $template_footer);
		$template_footer = str_replace("%POLL_LEAST_ANSWER%", $poll_least_answer, $template_footer);
		$template_footer = str_replace("%POLL_LEAST_VOTES%", number_format_i18n($poll_least_votes), $template_footer);
		$template_footer = str_replace("%POLL_LEAST_PERCENTAGE%", $poll_least_percentage, $template_footer);
		if($polls_question['multiple'] > 0) {
			$template_footer  = str_replace("%POLL_MULTIPLE_ANS_MAX%", $polls_question['multiple'], $template_footer);
		} else {
			$template_footer  = str_replace("%POLL_MULTIPLE_ANS_MAX%", '1', $template_footer);
		}
		// Archive Poll Footer
		$template_archive_footer = stripslashes(get_option('poll_template_pollarchivefooter'));
		$template_archive_footer = str_replace("%POLL_START_DATE%", $poll_start_date, $template_archive_footer);
		$template_archive_footer = str_replace("%POLL_END_DATE%", $poll_end_date, $template_archive_footer);
		$template_archive_footer = str_replace("%POLL_TOTALVOTES%", number_format_i18n($polls_question['totalvotes']), $template_archive_footer);
		$template_archive_footer = str_replace("%POLL_TOTALVOTERS%", number_format_i18n($polls_question['totalvoters']), $template_archive_footer);
		$template_archive_footer = str_replace("%POLL_MOST_ANSWER%", $poll_most_answer, $template_archive_footer);
		$template_archive_footer = str_replace("%POLL_MOST_VOTES%", number_format_i18n($poll_most_votes), $template_archive_footer);
		$template_archive_footer = str_replace("%POLL_MOST_PERCENTAGE%", $poll_most_percentage, $template_archive_footer);
		$template_archive_footer = str_replace("%POLL_LEAST_ANSWER%", $poll_least_answer, $template_archive_footer);
		$template_archive_footer = str_replace("%POLL_LEAST_VOTES%", number_format_i18n($poll_least_votes), $template_archive_footer);
		$template_archive_footer = str_replace("%POLL_LEAST_PERCENTAGE%", $poll_least_percentage, $template_archive_footer);
		if($polls_question['multiple'] > 0) {
			$template_archive_footer  = str_replace("%POLL_MULTIPLE_ANS_MAX%", $polls_question['multiple'], $template_archive_footer);
		} else {
			$template_archive_footer  = str_replace("%POLL_MULTIPLE_ANS_MAX%", '1', $template_archive_footer);
		}
		// Print Out Results Footer Template
		$pollsarchive_output_archive .= $template_footer;
		// Print Out Archive Poll Footer Template
		$pollsarchive_output_archive .= $template_archive_footer;
	}
	$pollsarchive_output_archive .= "</div>\n";

	// Polls Archive Paging
	if($max_page > 1) {
		$pollsarchive_output_archive .= stripslashes(get_option('poll_template_pollarchivepagingheader'));
		if(function_exists('wp_pagenavi')) {
			$pollsarchive_output_archive .= '<div class="wp-pagenavi">'."\n";
		} else {
			$pollsarchive_output_archive .= '<div class="wp-polls-paging">'."\n";
		}
		$pollsarchive_output_archive .= '<span class="pages">&#8201;'.sprintf(__('Page %s of %s', 'wp-polls'), number_format_i18n($page), number_format_i18n($max_page)).'&#8201;</span>';
		if ($start_page >= 2 && $pages_to_show < $max_page) {
			$pollsarchive_output_archive .= '<a href="'.polls_archive_link(1).'" title="'.__('&laquo; First', 'wp-polls').'">&#8201;'.__('&laquo; First', 'wp-polls').'&#8201;</a>';
			$pollsarchive_output_archive .= '<span class="extend">...</span>';
		}
		if($page > 1) {
			$pollsarchive_output_archive .= '<a href="'.polls_archive_link(($page-1)).'" title="'.__('&laquo;', 'wp-polls').'">&#8201;'.__('&laquo;', 'wp-polls').'&#8201;</a>';
		}
		for($i = $start_page; $i  <= $end_page; $i++) {						
			if($i == $page) {
				$pollsarchive_output_archive .= '<span class="current">&#8201;'.number_format_i18n($i).'&#8201;</span>';
			} else {
				$pollsarchive_output_archive .= '<a href="'.polls_archive_link($i).'" title="'.number_format_i18n($i).'">&#8201;'.number_format_i18n($i).'&#8201;</a>';
			}
		}
		if(empty($page) || ($page+1) <= $max_page) {
			$pollsarchive_output_archive .= '<a href="'.polls_archive_link(($page+1)).'" title="'.__('&raquo;', 'wp-polls').'">&#8201;'.__('&raquo;', 'wp-polls').'&#8201;</a>';
		}
		if ($end_page < $max_page) {
			$pollsarchive_output_archive .= '<span class="extend">...</span>';
			$pollsarchive_output_archive .= '<a href="'.polls_archive_link($max_page).'" title="'.__('Last &raquo;', 'wp-polls').'">&#8201;'.__('Last &raquo;', 'wp-polls').'&#8201;</a>';
		}
		$pollsarchive_output_archive .= '</div>';
		$pollsarchive_output_archive .= stripslashes(get_option('poll_template_pollarchivepagingfooter'));
	}

	// Output Polls Archive Page
	return apply_filters('polls_archive', $pollsarchive_output_archive);
}


// Edit Timestamp Options
function poll_timestamp($poll_timestamp, $fieldname = 'pollq_timestamp', $display = 'block') {
	global $month;
	echo '<div id="'.$fieldname.'" style="display: '.$display.'">'."\n";
	$day = gmdate('j', $poll_timestamp);
	echo '<select name="'.$fieldname.'_day" size="1">'."\n";
	for($i = 1; $i <=31; $i++) {
		if($day == $i) {
			echo "<option value=\"$i\" selected=\"selected\">$i</option>\n";	
		} else {
			echo "<option value=\"$i\">$i</option>\n";	
		}
	}
	echo '</select>&nbsp;&nbsp;'."\n";
	$month2 = gmdate('n', $poll_timestamp);
	echo '<select name="'.$fieldname.'_month" size="1">'."\n";
	for($i = 1; $i <= 12; $i++) {
		if ($i < 10) {
			$ii = '0'.$i;
		} else {
			$ii = $i;
		}
		if($month2 == $i) {
			echo "<option value=\"$i\" selected=\"selected\">$month[$ii]</option>\n";	
		} else {
			echo "<option value=\"$i\">$month[$ii]</option>\n";	
		}
	}
	echo '</select>&nbsp;&nbsp;'."\n";
	$year = gmdate('Y', $poll_timestamp);
	echo '<select name="'.$fieldname.'_year" size="1">'."\n";
	for($i = 2000; $i <= ($year+10); $i++) {
		if($year == $i) {
			echo "<option value=\"$i\" selected=\"selected\">$i</option>\n";	
		} else {
			echo "<option value=\"$i\">$i</option>\n";	
		}
	}
	echo '</select>&nbsp;@'."\n";
	echo '<span dir="ltr">'."\n";
	$hour = gmdate('H', $poll_timestamp);
	echo '<select name="'.$fieldname.'_hour" size="1">'."\n";
	for($i = 0; $i < 24; $i++) {
		if($hour == $i) {
			echo "<option value=\"$i\" selected=\"selected\">$i</option>\n";	
		} else {
			echo "<option value=\"$i\">$i</option>\n";	
		}
	}
	echo '</select>&nbsp;:'."\n";
	$minute = gmdate('i', $poll_timestamp);
	echo '<select name="'.$fieldname.'_minute" size="1">'."\n";
	for($i = 0; $i < 60; $i++) {
		if($minute == $i) {
			echo "<option value=\"$i\" selected=\"selected\">$i</option>\n";	
		} else {
			echo "<option value=\"$i\">$i</option>\n";	
		}
	}
	
	echo '</select>&nbsp;:'."\n";
	$second = gmdate('s', $poll_timestamp);
	echo '<select name="'.$fieldname.'_second" size="1">'."\n";
	for($i = 0; $i <= 60; $i++) {
		if($second == $i) {
			echo "<option value=\"$i\" selected=\"selected\">$i</option>\n";	
		} else {
			echo "<option value=\"$i\">$i</option>\n";	
		}
	}
	echo '</select>'."\n";
	echo '</span>'."\n";
	echo '</div>'."\n";
}


### Function: Place Cron
function cron_polls_place() {
	wp_clear_scheduled_hook('polls_cron');
	if (!wp_next_scheduled('polls_cron')) {
		wp_schedule_event(time(), 'twicedaily', 'polls_cron');
	}
}


### Funcion: Check All Polls Status To Check If It Expires
add_action('polls_cron', 'cron_polls_status');
function cron_polls_status() {
	global $wpdb;
	// Close Poll
	$close_polls = $wpdb->query("UPDATE $wpdb->pollsq SET pollq_active = 0 WHERE pollq_expiry < '".current_time('timestamp')."' AND pollq_expiry != '' AND pollq_active != 0");
	// Open Future Polls
	$active_polls = $wpdb->query("UPDATE $wpdb->pollsq SET pollq_active = 1 WHERE pollq_timestamp <= '".current_time('timestamp')."' AND pollq_active = -1");
	// Update Latest Poll If Future Poll Is Opened
	if($active_polls) {
		$update_latestpoll = update_option('poll_latestpoll', polls_latest_id());
	}
	return;
}


### Funcion: Get Latest Poll ID
function polls_latest_id() {
	global $wpdb;
	$poll_id = $wpdb->get_var("SELECT pollq_id FROM $wpdb->pollsq WHERE pollq_active = 1 ORDER BY pollq_timestamp DESC LIMIT 1");
	return intval($poll_id);
}


### Check If In Poll Archive Page
function in_pollarchive() {
	$poll_archive_url = get_option('poll_archive_url');
	$poll_archive_url_array = explode('/', $poll_archive_url);
	$poll_archive_url = $poll_archive_url_array[sizeof($poll_archive_url_array)-1];	
	if(empty($poll_archive_url)) {
		$poll_archive_url = $poll_archive_url_array[sizeof($poll_archive_url_array)-2];
	}
	$current_url = $_SERVER['REQUEST_URI'];
	if(strpos($current_url, $poll_archive_url) === false) {
		return false;
	} else {
		return true;
	}
}




### Function: Create Poll Tables
add_action('activate_wp-polls/wp-polls.php', 'create_poll_table');
function create_poll_table() {
	global $wpdb;
	if(@is_file(ABSPATH.'/wp-admin/includes/upgrade.php')) {
		include_once(ABSPATH.'/wp-admin/includes/upgrade.php');
	}elseif(@is_file(ABSPATH.'/wp-admin/upgrade-functions.php')) {
		include_once(ABSPATH.'/wp-admin/upgrade-functions.php');
	} elseif(@is_file(ABSPATH.'/wp-admin/includes/upgrade.php')) {
		include_once(ABSPATH.'/wp-admin/includes/upgrade.php');
	} else {
		die('We have problem finding your \'/wp-admin/upgrade-functions.php\' and \'/wp-admin/includes/upgrade.php\'');
	}
	// Create Poll Tables (3 Tables)
	$charset_collate = '';
	if($wpdb->supports_collation()) {
		if(!empty($wpdb->charset)) {
			$charset_collate = "DEFAULT CHARACTER SET $wpdb->charset";
		}
		if(!empty($wpdb->collate)) {
			$charset_collate .= " COLLATE $wpdb->collate";
		}
	}
	$create_table = array();
	$create_table['pollsq'] = "CREATE TABLE $wpdb->pollsq (".
									"pollq_id int(10) NOT NULL auto_increment,".
									"pollq_question varchar(200) character set utf8 NOT NULL default '',".
									"pollq_timestamp varchar(20) NOT NULL default '',".
									"pollq_totalvotes int(10) NOT NULL default '0',".
									"pollq_active tinyint(1) NOT NULL default '1',".
									"pollq_expiry varchar(20) NOT NULL default '',".
									"pollq_multiple tinyint(3) NOT NULL default '0',".
									"pollq_totalvoters int(10) NOT NULL default '0',".
									"PRIMARY KEY (pollq_id)) $charset_collate;";
	$create_table['pollsa'] = "CREATE TABLE $wpdb->pollsa (".
									"polla_aid int(10) NOT NULL auto_increment,".
									"polla_qid int(10) NOT NULL default '0',".
									"polla_answers varchar(200) character set utf8 NOT NULL default '',".
									"polla_votes int(10) NOT NULL default '0',".
									"PRIMARY KEY (polla_aid)) $charset_collate;";
	$create_table['pollsip'] = "CREATE TABLE $wpdb->pollsip (".
									"pollip_id int(10) NOT NULL auto_increment,".
									"pollip_qid varchar(10) NOT NULL default '',".
									"pollip_aid varchar(10) NOT NULL default '',".
									"pollip_ip varchar(100) NOT NULL default '',".
									"pollip_host VARCHAR(200) NOT NULL default '',".
									"pollip_timestamp varchar(20) NOT NULL default '0000-00-00 00:00:00',".
									"pollip_user tinytext NOT NULL,".
									"pollip_userid int(10) NOT NULL default '0',".
									"PRIMARY KEY (pollip_id),".
									"KEY pollip_ip (pollip_id),".
									"KEY pollip_qid (pollip_qid)".
									") $charset_collate;";
	maybe_create_table($wpdb->pollsq, $create_table['pollsq']);
	maybe_create_table($wpdb->pollsa, $create_table['pollsa']);
	maybe_create_table($wpdb->pollsip, $create_table['pollsip']);
	// Check Whether It is Install Or Upgrade
	$first_poll = $wpdb->get_var("SELECT pollq_id FROM $wpdb->pollsq LIMIT 1");
	// If Install, Insert 1st Poll Question With 5 Poll Answers
	if(empty($first_poll)) {
		// Insert Poll Question (1 Record)
		$insert_pollq = $wpdb->query("INSERT INTO $wpdb->pollsq VALUES (1, '".__('How Is My Site?', 'wp-polls')."', '".current_time('timestamp')."', 0, 1, '', 0, 0);");
		if($insert_pollq) {
			// Insert Poll Answers  (5 Records)
			$wpdb->query("INSERT INTO $wpdb->pollsa VALUES (1, 1, '".__('Good', 'wp-polls')."', 0);");
			$wpdb->query("INSERT INTO $wpdb->pollsa VALUES (2, 1, '".__('Excellent', 'wp-polls')."', 0);");
			$wpdb->query("INSERT INTO $wpdb->pollsa VALUES (3, 1, '".__('Bad', 'wp-polls')."', 0);");
			$wpdb->query("INSERT INTO $wpdb->pollsa VALUES (4, 1, '".__('Can Be Improved', 'wp-polls')."', 0);");
			$wpdb->query("INSERT INTO $wpdb->pollsa VALUES (5, 1, '".__('No Comments', 'wp-polls')."', 0);");
		}
	}
	// Add In Options (16 Records)
	add_option('poll_template_voteheader', '<p style="text-align: center;"><strong>%POLL_QUESTION%</strong></p>'.
	'<div id="polls-%POLL_ID%-ans" class="wp-polls-ans">'.
	'<ul class="wp-polls-ul">');
	add_option('poll_template_votebody', '<li><input type="%POLL_CHECKBOX_RADIO%" id="poll-answer-%POLL_ANSWER_ID%" name="poll_%POLL_ID%" value="%POLL_ANSWER_ID%" /> <label for="poll-answer-%POLL_ANSWER_ID%">%POLL_ANSWER%</label></li>');
	add_option('poll_template_votefooter', '</ul>'.
	'<p style="text-align: center;"><input type="button" name="vote" value="   '.__('Vote', 'wp-polls').'   " class="Buttons" onclick="poll_vote(%POLL_ID%);" /></p>'.
	'<p style="text-align: center;"><a href="#ViewPollResults" onclick="poll_result(%POLL_ID%); return false;" title="'.__('View Results Of This Poll', 'wp-polls').'">'.__('View Results', 'wp-polls').'</a></p>'.
	'</div>');
	add_option('poll_template_resultheader', '<p style="text-align: center;"><strong>%POLL_QUESTION%</strong></p>'.
	'<div id="polls-%POLL_ID%-ans" class="wp-polls-ans">'.
	'<ul class="wp-polls-ul">');
	add_option('poll_template_resultbody', '<li>%POLL_ANSWER% <small>(%POLL_ANSWER_PERCENTAGE%%'.__(',', 'wp-polls').' %POLL_ANSWER_VOTES% '.__('Votes', 'wp-polls').')</small><div class="pollbar" style="width: %POLL_ANSWER_IMAGEWIDTH%%;" title="%POLL_ANSWER_TEXT% (%POLL_ANSWER_PERCENTAGE%% | %POLL_ANSWER_VOTES% '.__('Votes', 'wp-polls').')"></div></li>');
	add_option('poll_template_resultbody2', '<li><strong><i>%POLL_ANSWER% <small>(%POLL_ANSWER_PERCENTAGE%%'.__(',', 'wp-polls').' %POLL_ANSWER_VOTES% '.__('Votes', 'wp-polls').')</small></i></strong><div class="pollbar" style="width: %POLL_ANSWER_IMAGEWIDTH%%;" title="'.__('You Have Voted For This Choice', 'wp-polls').' - %POLL_ANSWER_TEXT% (%POLL_ANSWER_PERCENTAGE%% | %POLL_ANSWER_VOTES% '.__('Votes', 'wp-polls').')"></div></li>');
	add_option('poll_template_resultfooter', '</ul>'.
	'<p style="text-align: center;">'.__('Total Voters', 'wp-polls').': <strong>%POLL_TOTALVOTERS%</strong></p>'.
	'</div>');
	add_option('poll_template_resultfooter2', '</ul>'.
	'<p style="text-align: center;">'.__('Total Voters', 'wp-polls').': <strong>%POLL_TOTALVOTERS%</strong></p>'.
	'<p style="text-align: center;"><a href="#VotePoll" onclick="poll_booth(%POLL_ID%); return false;" title="'.__('Vote For This Poll', 'wp-polls').'">'.__('Vote', 'wp-polls').'</a></p>'.
	'</div>');
	add_option('poll_template_disable', __('Sorry, there are no polls available at the moment.', 'wp-polls'));
	add_option('poll_template_error', __('An error has occurred when processing your poll.', 'wp-polls'));
	add_option('poll_currentpoll', 0);
	add_option('poll_latestpoll', 1);
	add_option('poll_archive_perpage', 5);
	add_option('poll_ans_sortby', 'polla_aid');
	add_option('poll_ans_sortorder', 'asc');
	add_option('poll_ans_result_sortby', 'polla_votes');
	add_option('poll_ans_result_sortorder', 'desc');
	// Database Upgrade For WP-Polls 2.1
	add_option('poll_logging_method', '3');
	add_option('poll_allowtovote', '2');
	maybe_add_column($wpdb->pollsq, 'pollq_active', "ALTER TABLE $wpdb->pollsq ADD pollq_active TINYINT( 1 ) NOT NULL DEFAULT '1';");
	// Database Upgrade For WP-Polls 2.12
	maybe_add_column($wpdb->pollsip, 'pollip_userid', "ALTER TABLE $wpdb->pollsip ADD pollip_userid INT( 10 ) NOT NULL DEFAULT '0';");
	add_option('poll_archive_url', site_url('pollsarchive'));
	// Database Upgrade For WP-Polls 2.13
	add_option('poll_bar', array('style' => 'default', 'background' => 'd8e1eb', 'border' => 'c8c8c8', 'height' => 8));
	// Database Upgrade For WP-Polls 2.14
	maybe_add_column($wpdb->pollsq, 'pollq_expiry', "ALTER TABLE $wpdb->pollsq ADD pollq_expiry varchar(20) NOT NULL default '';");
	add_option('poll_close', 1);
	// Database Upgrade For WP-Polls 2.20
	add_option('poll_ajax_style', array('loading' => 1, 'fading' => 1));
	add_option('poll_template_pollarchivelink', '<ul>'.
	'<li><a href="%POLL_ARCHIVE_URL%">'.__('Polls Archive', 'wp-polls').'</a></li>'.
	'</ul>');
	add_option('poll_archive_displaypoll', 2);
	add_option('poll_template_pollarchiveheader', '');
	add_option('poll_template_pollarchivefooter', '<p>'.__('Start Date:', 'wp-polls').' %POLL_START_DATE%<br />'.__('End Date:', 'wp-polls').' %POLL_END_DATE%</p>');
	maybe_add_column($wpdb->pollsq, 'pollq_multiple', "ALTER TABLE $wpdb->pollsq ADD pollq_multiple TINYINT( 3 ) NOT NULL DEFAULT '0';");
	$pollq_totalvoters = maybe_add_column($wpdb->pollsq, 'pollq_totalvoters', "ALTER TABLE $wpdb->pollsq ADD pollq_totalvoters INT( 10 ) NOT NULL DEFAULT '0';");
	if($pollq_totalvoters) {
		$pollq_totalvoters = intval($wpdb->get_var("SELECT SUM(pollq_totalvoters) FROM $wpdb->pollsq"));
		if($pollq_totalvoters == 0) {
			$wpdb->query("UPDATE $wpdb->pollsq SET pollq_totalvoters = pollq_totalvotes");
		}
	}
	// Database Upgrade For WP-Polls 2.30
	add_option('poll_cookielog_expiry', 0);
	add_option('poll_template_pollarchivepagingheader', '');
	add_option('poll_template_pollarchivepagingfooter', '');
	// Database Upgrade For WP-Polls 2.50
	delete_option('poll_archive_show');
	// Database Upgrade For WP-Polls 2.61
	$wpdb->query("ALTER TABLE $wpdb->pollsip ADD INDEX pollip_ip (pollip_id);");
	$wpdb->query("ALTER TABLE $wpdb->pollsip ADD INDEX pollip_qid (pollip_qid);");
	// Set 'manage_polls' Capabilities To Administrator	
	$role = get_role('administrator');
	if(!$role->has_cap('manage_polls')) {
		$role->add_cap('manage_polls');
	}
	cron_polls_place();
}