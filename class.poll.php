<?php


class WP_Polls_Poll {
	private $poll_id;

	function __construct( $poll_id ) {
		$this->poll_id    = intval( $poll_id );
	}

	### Function: Get Poll
	function get( $display = true ) {
		global $wpdb, $polls_loaded;

		// Poll Result Link
		if( isset( $_GET['pollresult'] ) ) {
			$pollresult_id = intval( $_GET['pollresult'] );
		}
		else {
			$pollresult_id = 0;
		}

		// Check Whether Poll Is Disabled
		if( intval( get_option('poll_currentpoll') ) == -1 ) {
			if( $display ) {
				echo stripslashes( get_option('poll_template_disable') );
				return;
			} else {
				return stripslashes( get_option('poll_template_disable') );
			}		
		// Poll Is Enabled
		}
		else {
			// Hardcoded Poll ID Is Not Specified
			switch( $this->poll_id ) {
				// Random Poll
				case -2:
					$this->poll_id = $wpdb->get_var("SELECT pollq_id FROM $wpdb->pollsq WHERE pollq_active = 1 ORDER BY RAND() LIMIT 1");
					break;
				// Latest Poll
				case 0:
					// Random Poll
					if(intval(get_option('poll_currentpoll')) == -2) {
						$random_poll_id = $wpdb->get_var("SELECT pollq_id FROM $wpdb->pollsq WHERE pollq_active = 1 ORDER BY RAND() LIMIT 1");
						$this->poll_id = intval( $random_poll_id );

						if( $pollresult_id > 0 ) {
							$this->poll_id = $pollresult_id;
						}
						elseif( intval( $_POST['poll_id'] ) > 0) {
							$this->poll_id = intval( $_POST['poll_id'] );
						}
					// Current Poll ID Is Not Specified
					} elseif(intval(get_option('poll_currentpoll')) == 0) {
						// Get Lastest Poll ID
						$this->poll_id = intval(get_option('poll_latestpoll'));
					} else {
						// Get Current Poll ID
						$this->poll_id = intval(get_option('poll_currentpoll'));
					}
					break;
			}
		}

		// Assign All Loaded Poll To $polls_loaded
		if( empty( $polls_loaded ) ) {
			$polls_loaded = array();
		}
		if( ! in_array( $this->poll_id, $polls_loaded ) ) {
			$polls_loaded[] = $this->poll_id;
		}

		// User Click on View Results Link
		if( $pollresult_id == $this->poll_id ) {
			if( $display ) {
				echo $this->display_pollresult( $this->poll_id );
				return;
			}
			else {
				return $this->display_pollresult( $this->poll_id );
			}
		// Check Whether User Has Voted
		}
		else {
			$poll_active = $wpdb->get_var( "SELECT pollq_active FROM $wpdb->pollsq WHERE pollq_id = " . $this->poll_id );
			$poll_active = intval( $poll_active );
			$check_voted = $this->check_voted();
			if($poll_active == 0) {
				$poll_close = intval(get_option('poll_close'));
			} else {
				$poll_close = 0;
			}
			if(intval($check_voted) > 0 || (is_array($check_voted) && sizeof($check_voted) > 0) || ($poll_active == 0 && $poll_close == 1)) {
				if($display) {
					echo $this->display_pollresult( $this->poll_id, $check_voted );
					return;
				} else {
					return $this->display_pollresult( $this->poll_id, $check_voted);
				}
			} elseif( ! $this->check_allowtovote() || ( $poll_active == 0 && $poll_close == 3 ) ) {
				$disable_poll_js = '<script type="text/javascript">jQuery("#polls_form_' . $this->poll_id . ' :input").each(function (i){jQuery(this).attr("disabled","disabled")});</script>';
				if($display) {
					echo $this->display_pollvote( $this->poll_id ) . $disable_poll_js;
					return;
				} else {
					return $this->display_pollvote( $this->poll_id ) . $disable_poll_js;
				}			
			} elseif($poll_active == 1) {
				if($display) {
					echo $this->display_pollvote( $this->poll_id );
					return;
				} else {
					return $this->display_pollvote( $this->poll_id );
				}
			}
		}
	}

	function vote() {
		global $wpdb, $user_identity, $user_ID;

		// Which View
		switch( $_REQUEST['view'] )
		{
			// Poll Vote
			case 'process':
				// Verify Captcha
				if( '1' == get_option('poll_spam_captcha') ) {
					$securimage = new Securimage();
					if( $securimage->check( $_POST['poll_captcha'] ) == false) {
						echo $this->display_pollvote( $this->poll_id, false, __( "You didn't fill in the Captcha correctly.", 'wp-polls' ) );
						exit();
					}
				}

				$poll_aid = $_POST["poll_answer"];
				$poll_aid_array = array_unique(array_map('intval', explode(',', $poll_aid)));
				if( $this->poll_id > 0 && ! empty( $poll_aid_array ) && $this->check_allowtovote() ) {
					$check_voted = $this->check_voted();

					if( $check_voted == 0 ) {
						if( ! empty( $user_identity ) ) {
							$pollip_user = htmlspecialchars( addslashes( $user_identity ) );
						}
						elseif( ! empty( $_COOKIE['comment_author_' . COOKIEHASH] ) ) {
							$pollip_user = htmlspecialchars( addslashes( $_COOKIE['comment_author_' . COOKIEHASH] ) );
						}
						else {
							$pollip_user = __('Guest', 'wp-polls');
						}

						$pollip_userid = intval($user_ID);
						$pollip_ip = get_ipaddress();
						$pollip_host = esc_attr(@gethostbyaddr($pollip_ip));
						$pollip_timestamp = current_time('timestamp');

						// Only Create Cookie If User Choose Logging Method 1 Or 2
						$poll_logging_method = intval(get_option('poll_logging_method'));
						if($poll_logging_method == 1 || $poll_logging_method == 3) {
							$cookie_expiry = intval(get_option('poll_cookielog_expiry'));
							if($cookie_expiry == 0) {
								$cookie_expiry = 30000000;
							}
							$vote_cookie = setcookie( 'voted_' . $this->poll_id, $poll_aid, ($pollip_timestamp + $cookie_expiry), COOKIEPATH );						
						}

						$i = 0;
						foreach($poll_aid_array as $polla_aid) {
							$update_polla_votes = $wpdb->query("UPDATE $wpdb->pollsa SET polla_votes = (polla_votes+1) WHERE polla_qid = $this->poll_id AND polla_aid = $polla_aid");
							if(!$update_polla_votes) {
								unset($poll_aid_array[$i]);
							}
							$i++;
						}

						$vote_q = $wpdb->query("UPDATE $wpdb->pollsq SET pollq_totalvotes = (pollq_totalvotes+".sizeof($poll_aid_array)."), pollq_totalvoters = (pollq_totalvoters+1) WHERE pollq_id = $this->poll_id AND pollq_active = 1");
						if($vote_q) {
							foreach($poll_aid_array as $polla_aid) {
								$wpdb->query("INSERT INTO $wpdb->pollsip VALUES (0, $this->poll_id, $polla_aid, '$pollip_ip', '$pollip_host', '$pollip_timestamp', '$pollip_user', $pollip_userid)");
							}
							echo $this->display_pollresult( $this->poll_id, $poll_aid_array, false );
						}
						else {
							printf( __('Unable To Update Poll Total Votes And Poll Total Voters. Poll ID #%s', 'wp-polls'), $this->poll_id );
						} // End if($vote_a)
					}
					else {
						printf( __('You Had Already Voted For This Poll. Poll ID #%s', 'wp-polls'), $this->poll_id );
					}// End if($check_voted)
				}
				else {
					printf( __('Invalid Poll ID. Poll ID #%s', 'wp-polls'), $this->poll_id );
				}

				break;
			// Poll Result
			case 'result':
				echo $this->display_pollresult( $this->poll_id, 0, false );
				break;
			// Poll Booth Aka Poll Voting Form
			case 'booth':
				echo $this->display_pollvote( $this->poll_id, false );
				break;
		} // End switch($_REQUEST['view'])
	}


	### Function: Check Who Is Allow To Vote
	function check_allowtovote() {
		global $user_ID;
		$user_ID = intval( $user_ID );
		$allow_to_vote = intval( get_option('poll_allowtovote') );

		switch( $allow_to_vote ) {
			// Guests Only
			case 0:
				if( $user_ID > 0 ) {
					return false;
				}
				return true;
				break;
			// Registered Users Only
			case 1:
				if($user_ID == 0) {
					return false;
				}
				return true;
				break;
			// Registered Users And Guests
			case 2:
			default:
				return true;
		}
	}

	### Funcrion: Check Voted By Cookie Or IP
	private function check_voted() {
		$poll_logging_method = intval( get_option('poll_logging_method') );

		switch( $poll_logging_method ) {
			// Do Not Log
			case 0:
				return 0;
				break;
			// Logged By Cookie
			case 1:
				return $this->check_voted_cookie();
				break;
			// Logged By IP
			case 2:
				return $this->check_voted_ip();
				break;
			// Logged By Cookie And IP
			case 3:
				$check_voted_cookie = $this->check_voted_cookie();

				if( ! empty( $check_voted_cookie ) ) {
					return $check_voted_cookie;
				}
				else {
					return $this->check_voted_ip();
				}
				break;
			// Logged By Username
			case 4:
				return $this->check_voted_username();
				break;
		}
	}

	### Function: Check Voted By Cookie
	private function check_voted_cookie() {
		if( ! empty( $_COOKIE[ "voted_" . $this->poll_id ] ) ) {
			$get_voted_aids = explode( ',', $_COOKIE[ "voted_" . $this->poll_id ] );
		}
		else {
			$get_voted_aids = 0;
		}

		return $get_voted_aids;
	}

	### Function: Check Voted By IP
	private function check_voted_ip() {
		global $wpdb;
		$log_expiry = intval(get_option('poll_cookielog_expiry'));
		$log_expiry_sql = '';
		if($log_expiry > 0) {
			$log_expiry_sql = 'AND ('.current_time('timestamp').'-(pollip_timestamp+0)) < '.$log_expiry;
		}
		// Check IP From IP Logging Database
		$get_voted_aids = $wpdb->get_col("SELECT pollip_aid FROM $wpdb->pollsip WHERE pollip_qid = " . $this->poll_id . " AND pollip_ip = '".get_ipaddress()."' $log_expiry_sql");
		if($get_voted_aids) {
			return $get_voted_aids;
		} else {
			return 0;
		}
	}

	### Function: Check Voted By Username
	private function check_voted_username() {
		global $wpdb, $user_ID;
		// Check IP If User Is Guest
		if (!is_user_logged_in()) {
			return 1;
		}
		$pollsip_userid = intval($user_ID);
		$log_expiry = intval(get_option('poll_cookielog_expiry'));
		$log_expiry_sql = '';
		if($log_expiry > 0) {
			$log_expiry_sql = 'AND ('.current_time('timestamp').'-(pollip_timestamp+0)) < '.$log_expiry;
		}
		// Check User ID From IP Logging Database
		$get_voted_aids = $wpdb->get_col("SELECT pollip_aid FROM $wpdb->pollsip WHERE pollip_qid = " . $this->poll_id . " AND pollip_userid = $pollsip_userid $log_expiry_sql");
		if($get_voted_aids) {
			return $get_voted_aids;
		} else {
			return 0;
		}
	}


	### Function: Display Voting Form
	function display_pollvote( $poll_id, $display_loading = true, $message = false ) {
		global $wpdb;
		// Temp Poll Result
		$temp_pollvote = '';
		// Get Poll Question Data
		$poll_question = $wpdb->get_row("SELECT pollq_id, pollq_question, pollq_totalvotes, pollq_timestamp, pollq_expiry, pollq_multiple, pollq_totalvoters FROM $wpdb->pollsq WHERE pollq_id = $poll_id LIMIT 1");

		// Poll Question Variables
		$poll_question_text = stripslashes($poll_question->pollq_question);
		if( $message ) {
			$poll_question_text .= '<br/>' . $message;
		}

		$poll_question_id = intval($poll_question->pollq_id);
		$poll_question_totalvotes = intval($poll_question->pollq_totalvotes);
		$poll_question_totalvoters = intval($poll_question->pollq_totalvoters);
		$poll_start_date = mysql2date(sprintf(__('%s @ %s', 'wp-polls'), get_option('date_format'), get_option('time_format')), gmdate('Y-m-d H:i:s', $poll_question->pollq_timestamp)); 
		$poll_expiry = trim($poll_question->pollq_expiry);
		if(empty($poll_expiry)) {
			$poll_end_date  = __('No Expiry', 'wp-polls');
		} else {
			$poll_end_date  = mysql2date(sprintf(__('%s @ %s', 'wp-polls'), get_option('date_format'), get_option('time_format')), gmdate('Y-m-d H:i:s', $poll_expiry));
		}
		$poll_multiple_ans = intval($poll_question->pollq_multiple);
		$template_question = stripslashes(get_option('poll_template_voteheader'));
		$template_question = str_replace("%POLL_QUESTION%", $poll_question_text, $template_question);
		$template_question = str_replace("%POLL_ID%", $poll_question_id, $template_question);
		$template_question = str_replace("%POLL_TOTALVOTES%", $poll_question_totalvotes, $template_question);
		$template_question = str_replace("%POLL_TOTALVOTERS%", $poll_question_totalvoters, $template_question);
		$template_question = str_replace("%POLL_START_DATE%", $poll_start_date, $template_question);
		$template_question = str_replace("%POLL_END_DATE%", $poll_end_date, $template_question);

		if($poll_multiple_ans > 0) {
			$template_question = str_replace("%POLL_MULTIPLE_ANS_MAX%", $poll_multiple_ans, $template_question);
		} else {
			$template_question = str_replace("%POLL_MULTIPLE_ANS_MAX%", '1', $template_question);
		}

		// Get Poll Answers Data
		$poll_answers = $wpdb->get_results("SELECT polla_aid, polla_answers, polla_votes FROM $wpdb->pollsa WHERE polla_qid = $poll_question_id ORDER BY ".get_option('poll_ans_sortby').' '.get_option('poll_ans_sortorder'));
		// If There Is Poll Question With Answers
		if($poll_question && $poll_answers) {
			$ajax_only = get_option('poll_spam_ajax_only');

			// Display Poll Voting Form
			$temp_pollvote .= "<div id=\"polls-$poll_question_id\" class=\"wp-polls\">\n";

			if( $ajax_only == 1 ) {
				$temp_pollvote .= "\t<div id=\"polls_form_$poll_question_id\" class=\"wp-polls-form\">\n";
			}
			else {
				$temp_pollvote .= "\t<form id=\"polls_form_$poll_question_id\" class=\"wp-polls-form\" action=\"".htmlspecialchars($_SERVER['REQUEST_URI'])."\" method=\"post\">\n";
			}

			$temp_pollvote .= "\t\t<p style=\"display: none;\"><input type=\"hidden\" id=\"poll_{$poll_question_id}_nonce\" name=\"wp-polls-nonce\" value=\"".wp_create_nonce('poll_'.$poll_question_id.'-nonce')."\" /></p>\n";
			$temp_pollvote .= "\t\t<p style=\"display: none;\"><input type=\"hidden\" name=\"poll_id\" value=\"$poll_question_id\" /></p>\n";
			if($poll_multiple_ans > 0) {
				$temp_pollvote .= "\t\t<p style=\"display: none;\"><input type=\"hidden\" id=\"poll_multiple_ans_$poll_question_id\" name=\"poll_multiple_ans_$poll_question_id\" value=\"$poll_multiple_ans\" /></p>\n";
			}

			// Print Out Voting Form Header Template
			$temp_pollvote .= "\t\t$template_question\n";

			foreach($poll_answers as $poll_answer) {
				// Poll Answer Variables
				$poll_answer_id = intval($poll_answer->polla_aid); 
				$poll_answer_text = stripslashes($poll_answer->polla_answers);
				$poll_answer_votes = intval($poll_answer->polla_votes);
				$template_answer = stripslashes(get_option('poll_template_votebody'));
				$template_answer = str_replace("%POLL_ID%", $poll_question_id, $template_answer);
				$template_answer = str_replace("%POLL_ANSWER_ID%", $poll_answer_id, $template_answer);
				$template_answer = str_replace("%POLL_ANSWER%", $poll_answer_text, $template_answer);
				$template_answer = str_replace("%POLL_ANSWER_VOTES%", number_format_i18n($poll_answer_votes), $template_answer);
				if($poll_multiple_ans > 0) {
					$template_answer = str_replace("%POLL_CHECKBOX_RADIO%", 'checkbox', $template_answer);
				} else {
					$template_answer = str_replace("%POLL_CHECKBOX_RADIO%", 'radio', $template_answer);
				}
				// Print Out Voting Form Body Template
				$temp_pollvote .= "\t\t$template_answer\n";
			}
			// Determine Poll Result URL
			$poll_result_url = $_SERVER['REQUEST_URI'];
			$poll_result_url = preg_replace('/pollresult=(\d+)/i', 'pollresult='.$poll_question_id, $poll_result_url);
			if(isset($_GET['pollresult']) && intval($_GET['pollresult']) == 0) {
				if(strpos($poll_result_url, '?') !== false) {
					$poll_result_url = "$poll_result_url&amp;pollresult=$poll_question_id";
				} else {
					$poll_result_url = "$poll_result_url?pollresult=$poll_question_id";
				}
			}
			// Voting Form Footer Variables
			$template_footer = stripslashes( get_option('poll_template_votefooter') );
			$template_footer = str_replace( "%POLL_ID%", $poll_question_id, $template_footer );
			$template_footer = str_replace( "%POLL_RESULT_URL%", $poll_result_url, $template_footer );
			$template_footer = str_replace( "%POLL_START_DATE%", $poll_start_date, $template_footer );
			$template_footer = str_replace( "%POLL_END_DATE%", $poll_end_date, $template_footer );
			if($poll_multiple_ans > 0) {
				$template_footer = str_replace("%POLL_MULTIPLE_ANS_MAX%", $poll_multiple_ans, $template_footer);
			} else {
				$template_footer = str_replace("%POLL_MULTIPLE_ANS_MAX%", '1', $template_footer);
			}

			if( '1' == get_option('poll_spam_captcha') ) {
				$captcha_url = plugins_url( 'securimage/securimage_show.php', __FILE__ );

				$temp_pollvote .= '<img id="captcha-' . $poll_question_id . '" src="' . $captcha_url . '" alt="' . __( 'CAPTCHA Image', 'wp-polls' ) . '" /><br/>';
				$temp_pollvote .= '<input type="text" id="captcha_code-' . $poll_question_id . '"  name="captcha_code-' . $poll_question_id . '" size="13" maxlength="6" />';
				$temp_pollvote .= ' <a href="#" onclick="document.getElementById(\'captcha\').src = \'' . $captcha_url . '?\' + Math.random(); return false">' . __( 'Different Image', 'wp-polls' ) . '</a>';
			}

			// Print Out Voting Form Footer Template
			$temp_pollvote .= "\t\t$template_footer\n";

			if( $ajax_only == 1 ) {
				$temp_pollvote .= "\t</div>\n";
			}
			else {
				$temp_pollvote .= "\t</form>\n";
			}

			$temp_pollvote .= "</div>\n";
			if($display_loading) {
				$poll_ajax_style = get_option('poll_ajax_style');
				if(intval($poll_ajax_style['loading']) == 1) {
					$temp_pollvote .= "<div id=\"polls-$poll_question_id-loading\" class=\"wp-polls-loading\"><img src=\"".plugins_url('wp-polls/images/loading.gif')."\" width=\"16\" height=\"16\" alt=\"".__('Loading', 'wp-polls')." ...\" title=\"".__('Loading', 'wp-polls')." ...\" class=\"wp-polls-image\" />&nbsp;".__('Loading', 'wp-polls')." ...</div>\n";
				}
			}
		} else {
			$temp_pollvote .= stripslashes(get_option('poll_template_disable'));
		}
		// Return Poll Vote Template
		return $temp_pollvote;
	}


	### Function: Display Results Form
	function display_pollresult($poll_id, $user_voted = '', $display_loading = true) {
		global $wpdb;
		$poll_id = intval($poll_id);
		// User Voted
		if(!is_array($user_voted)) {
			$user_voted = array();
		}
		// Temp Poll Result
		$temp_pollresult = '';	
		// Most/Least Variables
		$poll_most_answer = '';
		$poll_most_votes = 0;
		$poll_most_percentage = 0;
		$poll_least_answer = '';
		$poll_least_votes = 0;
		$poll_least_percentage = 0;
		// Get Poll Question Data
		$poll_question = $wpdb->get_row("SELECT pollq_id, pollq_question, pollq_totalvotes, pollq_active, pollq_timestamp, pollq_expiry, pollq_multiple, pollq_totalvoters FROM $wpdb->pollsq WHERE pollq_id = $poll_id LIMIT 1");
		// Poll Question Variables
		$poll_question_text = stripslashes($poll_question->pollq_question);
		$poll_question_id = intval($poll_question->pollq_id);
		$poll_question_totalvotes = intval($poll_question->pollq_totalvotes);
		$poll_question_totalvoters = intval($poll_question->pollq_totalvoters);
		$poll_question_active = intval($poll_question->pollq_active);
		$poll_start_date = mysql2date(sprintf(__('%s @ %s', 'wp-polls'), get_option('date_format'), get_option('time_format')), gmdate('Y-m-d H:i:s', $poll_question->pollq_timestamp));
		$poll_expiry = trim($poll_question->pollq_expiry);
		if(empty($poll_expiry)) {
			$poll_end_date  = __('No Expiry', 'wp-polls');
		} else {
			$poll_end_date  = mysql2date(sprintf(__('%s @ %s', 'wp-polls'), get_option('date_format'), get_option('time_format')), gmdate('Y-m-d H:i:s', $poll_expiry));
		}
		$poll_multiple_ans = intval($poll_question->pollq_multiple);
		$template_question = stripslashes(get_option('poll_template_resultheader'));
		$template_question = str_replace("%POLL_QUESTION%", $poll_question_text, $template_question);
		$template_question = str_replace("%POLL_ID%", $poll_question_id, $template_question);
		$template_question = str_replace("%POLL_TOTALVOTES%", $poll_question_totalvotes, $template_question);
		$template_question = str_replace("%POLL_TOTALVOTERS%", $poll_question_totalvoters, $template_question);
		$template_question = str_replace("%POLL_START_DATE%", $poll_start_date, $template_question);
		$template_question = str_replace("%POLL_END_DATE%", $poll_end_date, $template_question);
		if($poll_multiple_ans > 0) {
			$template_question = str_replace("%POLL_MULTIPLE_ANS_MAX%", $poll_multiple_ans, $template_question);
		} else {
			$template_question = str_replace("%POLL_MULTIPLE_ANS_MAX%", '1', $template_question);
		}
		// Get Poll Answers Data
		$poll_answers = $wpdb->get_results("SELECT polla_aid, polla_answers, polla_votes FROM $wpdb->pollsa WHERE polla_qid = $poll_question_id ORDER BY ".get_option('poll_ans_result_sortby').' '.get_option('poll_ans_result_sortorder'));
		// If There Is Poll Question With Answers
		if($poll_question && $poll_answers) {
			// Store The Percentage Of The Poll
			$poll_answer_percentage_array = array();
			// Is The Poll Total Votes 0?
			$poll_totalvotes_zero = true;
			if($poll_question_totalvotes > 0) {
				$poll_totalvotes_zero = false;
			}
			// Print Out Result Header Template
			$temp_pollresult .= "<div id=\"polls-$poll_question_id\" class=\"wp-polls\">\n";
			$temp_pollresult .= "\t\t$template_question\n";
			foreach($poll_answers as $poll_answer) {
				// Poll Answer Variables
				$poll_answer_id = intval($poll_answer->polla_aid); 
				$poll_answer_text = stripslashes($poll_answer->polla_answers);
				$poll_answer_votes = intval($poll_answer->polla_votes);
				$poll_answer_percentage = 0;
				$poll_answer_imagewidth = 0;
				// Calculate Percentage And Image Bar Width
				if(!$poll_totalvotes_zero) {
					if($poll_answer_votes > 0) {
						$poll_answer_percentage = round((($poll_answer_votes/$poll_question_totalvoters)*100));
						$poll_answer_imagewidth = round($poll_answer_percentage);
						if($poll_answer_imagewidth == 100) {
							$poll_answer_imagewidth = 99;
						}
					} else {
						$poll_answer_percentage = 0;
						$poll_answer_imagewidth = 1;
					}
				} else {
					$poll_answer_percentage = 0;
					$poll_answer_imagewidth = 1;
				}
				// Make Sure That Total Percentage Is 100% By Adding A Buffer To The Last Poll Answer
				if($poll_multiple_ans == 0) {
					$poll_answer_percentage_array[] = $poll_answer_percentage;
					if(sizeof($poll_answer_percentage_array) == sizeof($poll_answers)) {
						$percentage_error_buffer = 100 - array_sum($poll_answer_percentage_array);
						$poll_answer_percentage = $poll_answer_percentage + $percentage_error_buffer;
						if($poll_answer_percentage < 0) {
							$poll_answer_percentage = 0;
						}
					}
				}
				// Let User See What Options They Voted
				if(in_array($poll_answer_id, $user_voted)) {
					// Results Body Variables
					$template_answer = stripslashes(get_option('poll_template_resultbody2'));
					$template_answer = str_replace("%POLL_ID%", $poll_question_id, $template_answer);
					$template_answer = str_replace("%POLL_ANSWER_ID%", $poll_answer_id, $template_answer);
					$template_answer = str_replace("%POLL_ANSWER%", $poll_answer_text, $template_answer);
					$template_answer = str_replace("%POLL_ANSWER_TEXT%", htmlspecialchars(strip_tags($poll_answer_text)), $template_answer);
					$template_answer = str_replace("%POLL_ANSWER_VOTES%", number_format_i18n($poll_answer_votes), $template_answer);
					$template_answer = str_replace("%POLL_ANSWER_PERCENTAGE%", $poll_answer_percentage, $template_answer);
					$template_answer = str_replace("%POLL_ANSWER_IMAGEWIDTH%", $poll_answer_imagewidth, $template_answer);
					// Print Out Results Body Template
					$temp_pollresult .= "\t\t$template_answer\n";
				} else {
					// Results Body Variables
					$template_answer = stripslashes(get_option('poll_template_resultbody'));
					$template_answer = str_replace("%POLL_ID%", $poll_question_id, $template_answer);
					$template_answer = str_replace("%POLL_ANSWER_ID%", $poll_answer_id, $template_answer);
					$template_answer = str_replace("%POLL_ANSWER%", $poll_answer_text, $template_answer);
					$template_answer = str_replace("%POLL_ANSWER_TEXT%", htmlspecialchars(strip_tags($poll_answer_text)), $template_answer);
					$template_answer = str_replace("%POLL_ANSWER_VOTES%", number_format_i18n($poll_answer_votes), $template_answer);
					$template_answer = str_replace("%POLL_ANSWER_PERCENTAGE%", $poll_answer_percentage, $template_answer);
					$template_answer = str_replace("%POLL_ANSWER_IMAGEWIDTH%", $poll_answer_imagewidth, $template_answer);
					// Print Out Results Body Template
					$temp_pollresult .= "\t\t$template_answer\n";
				}
				// Get Most Voted Data
				if($poll_answer_votes > $poll_most_votes) {
					$poll_most_answer = $poll_answer_text;
					$poll_most_votes = $poll_answer_votes;
					$poll_most_percentage = $poll_answer_percentage;
				}
				// Get Least Voted Data
				if($poll_least_votes == 0) {
					$poll_least_votes = $poll_answer_votes;
				}
				if($poll_answer_votes <= $poll_least_votes) {
					$poll_least_answer = $poll_answer_text;
					$poll_least_votes = $poll_answer_votes;
					$poll_least_percentage = $poll_answer_percentage;
				}
			}
			// Results Footer Variables
			if( ! empty( $user_voted ) || $poll_question_active == 0 || ! $this->check_allowtovote() ) {
				$template_footer = stripslashes(get_option('poll_template_resultfooter'));
			} else {
				$template_footer = stripslashes(get_option('poll_template_resultfooter2'));
			}
			$template_footer = str_replace("%POLL_START_DATE%", $poll_start_date, $template_footer);
			$template_footer = str_replace("%POLL_END_DATE%", $poll_end_date, $template_footer);
			$template_footer = str_replace("%POLL_ID%", $poll_question_id, $template_footer);
			$template_footer = str_replace("%POLL_TOTALVOTES%", number_format_i18n($poll_question_totalvotes), $template_footer);
			$template_footer = str_replace("%POLL_TOTALVOTERS%", number_format_i18n($poll_question_totalvoters), $template_footer);
			$template_footer = str_replace("%POLL_MOST_ANSWER%", $poll_most_answer, $template_footer);
			$template_footer = str_replace("%POLL_MOST_VOTES%", number_format_i18n($poll_most_votes), $template_footer);
			$template_footer = str_replace("%POLL_MOST_PERCENTAGE%", $poll_most_percentage, $template_footer);
			$template_footer = str_replace("%POLL_LEAST_ANSWER%", $poll_least_answer, $template_footer);
			$template_footer = str_replace("%POLL_LEAST_VOTES%", number_format_i18n($poll_least_votes), $template_footer);
			$template_footer = str_replace("%POLL_LEAST_PERCENTAGE%", $poll_least_percentage, $template_footer);
			if($poll_multiple_ans > 0) {
				$template_footer = str_replace("%POLL_MULTIPLE_ANS_MAX%", $poll_multiple_ans, $template_footer);
			} else {
				$template_footer = str_replace("%POLL_MULTIPLE_ANS_MAX%", '1', $template_footer);
			}
			// Print Out Results Footer Template
			$temp_pollresult .= "\t\t$template_footer\n";
			$temp_pollresult .= "\t\t<input type=\"hidden\" id=\"poll_{$poll_question_id}_nonce\" name=\"wp-polls-nonce\" value=\"".wp_create_nonce('poll_'.$poll_question_id.'-nonce')."\" />\n";
			$temp_pollresult .= "</div>\n";
			if($display_loading) {
				$poll_ajax_style = get_option('poll_ajax_style');
				if(intval($poll_ajax_style['loading']) == 1) {
					$temp_pollresult .= "<div id=\"polls-$poll_question_id-loading\" class=\"wp-polls-loading\"><img src=\"".plugins_url('wp-polls/images/loading.gif')."\" width=\"16\" height=\"16\" alt=\"".__('Loading', 'wp-polls')." ...\" title=\"".__('Loading', 'wp-polls')." ...\" class=\"wp-polls-image\" />&nbsp;".__('Loading', 'wp-polls')." ...</div>\n";
				}
			}	
		}
		else {
			$temp_pollresult .= stripslashes(get_option('poll_template_disable'));
		}	
		// Return Poll Result

		return $temp_pollresult;
	}
}