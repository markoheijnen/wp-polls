<?php

class Polls_Admin {

	### Function: Poll Administration Menu
	function __construct() {
		add_action( 'init', array( &$this, 'poll_tinymce_addbuttons' ) );

		add_action( 'admin_menu', array( &$this, 'poll_menu' ) );
		add_action( 'admin_bar_menu',  array( &$this, 'add_toolbar_node' ), 999 );

		add_action( 'admin_enqueue_scripts', array( &$this, 'poll_scripts_admin' ) );
		add_action( 'admin_footer', array( &$this, 'poll_footer_admin' ) );

		add_action( 'wp_ajax_polls-admin', array( &$this, 'manage_poll' ) );
	}

	function poll_menu() {
		add_menu_page( __( 'Polls', 'wp-polls' ), __( 'Polls', 'wp-polls' ), 'manage_polls', 'wp-polls/polls-manager.php', '', plugins_url( 'wp-polls/images/poll.png' ) );

		add_submenu_page( 'wp-polls/polls-manager.php', __( 'Manage Polls', 'wp-polls' ), __( 'Manage Polls', 'wp-polls' ), 'manage_polls', 'wp-polls/polls-manager.php' );
		add_submenu_page( 'wp-polls/polls-manager.php', __( 'Add Poll', 'wp-polls' ), __( 'Add Poll', 'wp-polls' ), 'manage_polls', 'wp-polls/polls-add.php' );
		add_submenu_page( 'wp-polls/polls-manager.php', __( 'Poll Options', 'wp-polls' ), __( 'Poll Options', 'wp-polls' ), 'manage_polls', 'wp-polls/polls-options.php' );
		add_submenu_page( 'wp-polls/polls-manager.php', __( 'Poll Templates', 'wp-polls' ), __( 'Poll Templates', 'wp-polls' ), 'manage_polls', 'wp-polls/polls-templates.php' );
		add_submenu_page( 'wp-polls/polls-manager.php', __( 'Uninstall WP-Polls', 'wp-polls' ), __( 'Uninstall WP-Polls', 'wp-polls' ), 'manage_polls', 'wp-polls/polls-uninstall.php' );
	}

	function add_toolbar_node( $wp_admin_bar ) {
		if( current_user_can( 'manage_polls' ) ) {
			$args = array(
				'parent' => 'new-content',
				'id'     => 'add-poll',
				'title'  => __( 'Add Poll', 'wp-polls' ),
				'href'   => admin_url( 'admin.php?page=wp-polls/polls-add.php' )
			);

			$wp_admin_bar->add_node($args);
		}
	}

	### Function: Enqueue Polls Stylesheets/JavaScripts In WP-Admin
	function poll_scripts_admin( $hook_suffix ) {
		$poll_admin_pages = array( 'wp-polls/polls-manager.php', 'wp-polls/polls-add.php', 'wp-polls/polls-options.php', 'wp-polls/polls-templates.php', 'wp-polls/polls-uninstall.php' );

		if( in_array( $hook_suffix, $poll_admin_pages ) ) {
			wp_enqueue_style( 'wp-polls-admin', plugins_url('wp-polls/polls-admin-css.css'), false, WP_POLLS_VERSION, 'all' );
			wp_enqueue_script( 'wp-polls-admin', plugins_url('wp-polls/polls-admin-js.js'), array('jquery'), WP_POLLS_VERSION, true );

			wp_localize_script( 'wp-polls-admin', 'pollsAdminL10n', array(
				'admin_ajax_url' => admin_url( 'admin-ajax.php' ),
				'text_direction' => ( is_rtl() ) ? 'left' : 'right',
				'text_delete_poll' => __( 'Delete Poll', 'wp-polls' ),
				'text_no_poll_logs' => __( 'No poll logs available.', 'wp-polls' ),
				'text_delete_all_logs' => __( 'Delete All Logs', 'wp-polls' ),
				'text_checkbox_delete_all_logs' => __( 'Please check the \\\'Yes\\\' checkbox if you want to delete all logs.', 'wp-polls' ),
				'text_delete_poll_logs' => __( 'Delete Logs For This Poll Only', 'wp-polls' ),
				'text_checkbox_delete_poll_logs' => __( 'Please check the \\\'Yes\\\' checkbox if you want to delete all logs for this poll ONLY.', 'wp-polls' ),
				'text_delete_poll_ans' => __( 'Delete Poll Answer', 'wp-polls' ),
				'text_open_poll' => __( 'Open Poll', 'wp-polls' ),
				'text_close_poll' => __( 'Close Poll', 'wp-polls' ),
				'text_answer' => __( 'Answer', 'wp-polls' ),
				'text_remove_poll_answer' => __( 'Remove', 'wp-polls' )
			) );
		}
	}

	### Function: Displays Polls Footer In WP-Admin
	function poll_footer_admin() {
		$screen = get_current_screen();

		if( 'post' === $screen->base ) {
			// Javascript Code Courtesy Of WP-AddQuicktag (http://bueltge.de/wp-addquicktags-de-plugin/120/)
			echo '<script type="text/javascript">'."\n";
			echo "/* <![CDATA[ */\n";
			echo "\t".'var pollsEdL10n = {'."\n";
			echo "\t\t".'enter_poll_id: "'.esc_js(__('Enter Poll ID', 'wp-polls')).'",'."\n";
			echo "\t\t".'enter_poll_id_again: "'.esc_js(__('Error: Poll ID must be numeric', 'wp-polls')).'\n\n'.esc_js(__('Please enter Poll ID again', 'wp-polls')).'",'."\n";
			echo "\t\t".'poll: "'.esc_js(__('Poll', 'wp-polls')).'",'."\n";
			echo "\t\t".'insert_poll: "'.esc_js(__('Insert Poll', 'wp-polls')).'"'."\n";
			echo "\t".'};'."\n";
			echo "\t".'function insertPoll(where, myField) {'."\n";
			echo "\t\t".'var poll_id = jQuery.trim(prompt(pollsEdL10n.enter_poll_id));'."\n";
			echo "\t\t".'while(isNaN(poll_id)) {'."\n";
			echo "\t\t\t".'poll_id = jQuery.trim(prompt(pollsEdL10n.enter_poll_id_again));'."\n";
			echo "\t\t".'}'."\n";
			echo "\t\t".'if (poll_id >= -1 && poll_id != null && poll_id != "") {'."\n";
			echo "\t\t\t".'if(where == \'code\') {'."\n";
			echo "\t\t\t\t".'edInsertContent(myField, \'[poll id="\' + poll_id + \'"]\');'."\n";
			echo "\t\t\t".'} else {'."\n";
			echo "\t\t\t\t".'return \'[poll id="\' + poll_id + \'"]\';'."\n";
			echo "\t\t\t".'}'."\n";
			echo "\t\t".'}'."\n";
			echo "\t".'}'."\n";
			echo "\t".'if(document.getElementById("ed_toolbar")){'."\n";
			echo "\t\t".'edButtons[edButtons.length] = new edButton("ed_poll",pollsEdL10n.poll, "", "","");'."\n";
			echo "\t\t".'jQuery(document).ready(function($){'."\n";
			echo "\t\t\t".'$(\'#qt_content_ed_poll\').replaceWith(\'<input type="button" id="qt_content_ed_poll" accesskey="" class="ed_button" onclick="insertPoll(\\\'code\\\', edCanvas);" value="\' + pollsEdL10n.poll + \'" title="\' + pollsEdL10n.insert_poll + \'" />\');'."\n";
			echo "\t\t".'});'."\n";
			echo "\t".'}'."\n";
			echo '/* ]]> */'."\n";
			echo '</script>'."\n";
		}
	}


	### Function: Add Quick Tag For Poll In TinyMCE >= WordPress 2.5
	function poll_tinymce_addbuttons() {
		if( ! current_user_can('edit_posts') && ! current_user_can('edit_pages') ) {
			return;
		}

		if( get_user_option('rich_editing') == 'true' ) {
			add_filter( 'mce_external_plugins', array( &$this, 'poll_tinymce_addplugin' ) );
			add_filter( 'mce_buttons', array( &$this, 'poll_tinymce_registerbutton' ) );
		}
	}

	function poll_tinymce_registerbutton( $buttons ) {
		array_push( $buttons, 'separator', 'polls' );

		return $buttons;
	}

	function poll_tinymce_addplugin( $plugin_array ) {
		$plugin_array['polls'] = plugins_url('wp-polls/tinymce/plugins/polls/editor_plugin.js');

		return $plugin_array;
	}



	### Function: Manage Polls
	function manage_poll() {
		global $wpdb;
		### Form Processing
		if(isset($_POST['action']) && $_POST['action'] == 'polls-admin')
		{
			if(!empty($_POST['do'])) {
				// Set Header
				header('Content-Type: text/html; charset='.get_option('blog_charset').'');
			
				// Decide What To Do
				switch($_POST['do']) {
					// Delete Polls Logs
					case __('Delete All Logs', 'wp-polls'):
						check_ajax_referer('wp-polls_delete-polls-logs');
						if(trim($_POST['delete_logs_yes']) == 'yes') {
							$delete_logs = $wpdb->query("DELETE FROM $wpdb->pollsip");
							if($delete_logs) {
								echo '<p style="color: green;">'.__('All Polls Logs Have Been Deleted.', 'wp-polls').'</p>';
							} else {
								echo '<p style="color: red;">'.__('An Error Has Occurred While Deleting All Polls Logs.', 'wp-polls').'</p>';
							}
						}
						break;
					// Delete Poll Logs For Individual Poll
					case __('Delete Logs For This Poll Only', 'wp-polls'):
						check_ajax_referer('wp-polls_delete-poll-logs');
						$pollq_id  = intval($_POST['pollq_id']);
						$pollq_question = $wpdb->get_var("SELECT pollq_question FROM $wpdb->pollsq WHERE pollq_id = $pollq_id");
						if(trim($_POST['delete_logs_yes']) == 'yes') {
							$delete_logs = $wpdb->query("DELETE FROM $wpdb->pollsip WHERE pollip_qid = $pollq_id");
							if($delete_logs) {
								echo '<p style="color: green;">'.sprintf(__('All Logs For \'%s\' Has Been Deleted.', 'wp-polls'), stripslashes($pollq_question)).'</p>';
							} else {
								echo '<p style="color: red;">'.sprintf(__('An Error Has Occurred While Deleting All Logs For \'%s\'', 'wp-polls'), stripslashes($pollq_question)).'</p>';
							}
						}
						break;
					// Delete Poll's Answer
					case __('Delete Poll Answer', 'wp-polls'):
						check_ajax_referer('wp-polls_delete-poll-answer');
						$pollq_id  = intval($_POST['pollq_id']);
						$polla_aid = intval($_POST['polla_aid']);
						$poll_answers = $wpdb->get_row("SELECT polla_votes, polla_answers FROM $wpdb->pollsa WHERE polla_aid = $polla_aid AND polla_qid = $pollq_id");
						$polla_votes = intval($poll_answers->polla_votes);
						$polla_answers = stripslashes(trim($poll_answers->polla_answers));
						$delete_polla_answers = $wpdb->query("DELETE FROM $wpdb->pollsa WHERE polla_aid = $polla_aid AND polla_qid = $pollq_id");
						$delete_pollip = $wpdb->query("DELETE FROM $wpdb->pollsip WHERE pollip_qid = $pollq_id AND pollip_aid = $polla_aid");
						$update_pollq_totalvotes = $wpdb->query("UPDATE $wpdb->pollsq SET pollq_totalvotes = (pollq_totalvotes-$polla_votes) WHERE pollq_id = $pollq_id");
						if($delete_polla_answers) {
							echo '<p style="color: green;">'.sprintf(__('Poll Answer \'%s\' Deleted Successfully.', 'wp-polls'), $polla_answers).'</p>';
						} else {
							echo '<p style="color: red;">'.sprintf(__('Error In Deleting Poll Answer \'%s\'.', 'wp-polls'), $polla_answers).'</p>';
						}
						break;
					// Open Poll
					case __('Open Poll', 'wp-polls'):
						check_ajax_referer('wp-polls_open-poll');
						$pollq_id  = intval($_POST['pollq_id']);
						$pollq_question = $wpdb->get_var("SELECT pollq_question FROM $wpdb->pollsq WHERE pollq_id = $pollq_id");
						$open_poll = $wpdb->query("UPDATE $wpdb->pollsq SET pollq_active = 1 WHERE pollq_id = $pollq_id;");
						if($open_poll) {
							echo '<p style="color: green;">'.sprintf(__('Poll \'%s\' Is Now Opened', 'wp-polls'), stripslashes($pollq_question)).'</p>';
						} else {
							echo '<p style="color: red;">'.sprintf(__('Error Opening Poll \'%s\'', 'wp-polls'), stripslashes($pollq_question)).'</p>';
						}
						break;
					// Close Poll
					case __('Close Poll', 'wp-polls'):
						check_ajax_referer('wp-polls_close-poll');
						$pollq_id  = intval($_POST['pollq_id']);
						$pollq_question = $wpdb->get_var("SELECT pollq_question FROM $wpdb->pollsq WHERE pollq_id = $pollq_id");
						$close_poll = $wpdb->query("UPDATE $wpdb->pollsq SET pollq_active = 0 WHERE pollq_id = $pollq_id;");
						if($close_poll) {
							echo '<p style="color: green;">'.sprintf(__('Poll \'%s\' Is Now Closed', 'wp-polls'), stripslashes($pollq_question)).'</p>';
						} else {
							echo '<p style="color: red;">'.sprintf(__('Error Closing Poll \'%s\'', 'wp-polls'), stripslashes($pollq_question)).'</p>';
						}
						break;
					// Delete Poll
					case __('Delete Poll', 'wp-polls'):
						check_ajax_referer('wp-polls_delete-poll');
						$pollq_id  = intval($_POST['pollq_id']);
						$pollq_question = $wpdb->get_var("SELECT pollq_question FROM $wpdb->pollsq WHERE pollq_id = $pollq_id");
						$delete_poll_question = $wpdb->query("DELETE FROM $wpdb->pollsq WHERE pollq_id = $pollq_id");
						$delete_poll_answers =  $wpdb->query("DELETE FROM $wpdb->pollsa WHERE polla_qid = $pollq_id");
						$delete_poll_ip = $wpdb->query("DELETE FROM $wpdb->pollsip WHERE pollip_qid = $pollq_id");
						$poll_option_lastestpoll = $wpdb->get_var("SELECT option_value FROM $wpdb->options WHERE option_name = 'poll_latestpoll'");
						if(!$delete_poll_question) {
							echo '<p style="color: red;">'.sprintf(__('Error In Deleting Poll \'%s\' Question', 'wp-polls'), stripslashes($pollq_question)).'</p>';
						} 
						if(empty($text)) {
							echo '<p style="color: green;">'.sprintf(__('Poll \'%s\' Deleted Successfully', 'wp-polls'), stripslashes($pollq_question)).'</p>';
						}
						// Update Lastest Poll ID To Poll Options
						$latest_pollid = polls_latest_id();
						$update_latestpoll = update_option('poll_latestpoll', $latest_pollid);
						break;
				}
				exit();
			}
		}
	}
}