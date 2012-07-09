<?php

### Function: Plug Into WP-Stats
add_action('wp','polls_wp_stats');
function polls_wp_stats() {
	if(function_exists('stats_page')) {
		if(strpos(get_option('stats_url'), $_SERVER['REQUEST_URI']) || strpos($_SERVER['REQUEST_URI'], 'stats-options.php') || strpos($_SERVER['REQUEST_URI'], 'wp-stats/wp-stats.php')) {
			add_filter('wp_stats_page_admin_plugins', 'polls_page_admin_general_stats');
			add_filter('wp_stats_page_plugins', 'polls_page_general_stats');
		}
	}
}


### Function: Add WP-Polls General Stats To WP-Stats Page Options
function polls_page_admin_general_stats($content) {
	$stats_display = get_option('stats_display');
	if($stats_display['polls'] == 1) {
		$content .= '<input type="checkbox" name="stats_display[]" id="wpstats_polls" value="polls" checked="checked" />&nbsp;&nbsp;<label for="wpstats_polls">'.__('WP-Polls', 'wp-polls').'</label><br />'."\n";
	} else {
		$content .= '<input type="checkbox" name="stats_display[]" id="wpstats_polls" value="polls" />&nbsp;&nbsp;<label for="wpstats_polls">'.__('WP-Polls', 'wp-polls').'</label><br />'."\n";
	}
	return $content;
}


### Function: Add WP-Polls General Stats To WP-Stats Page
function polls_page_general_stats($content) {
	$stats_display = get_option('stats_display');
	if($stats_display['polls'] == 1) {
		$content .= '<p><strong>'.__('WP-Polls', 'wp-polls').'</strong></p>'."\n";
		$content .= '<ul>'."\n";
		$content .= '<li>'.sprintf(_n('<strong>%s</strong> poll was created.', '<strong>%s</strong> polls were created.', get_pollquestions(false), 'wp-polls'), number_format_i18n(get_pollquestions(false))).'</li>'."\n";
		$content .= '<li>'.sprintf(_n('<strong>%s</strong> polls\' answer was given.', '<strong>%s</strong> polls\' answers were given.', get_pollanswers(false), 'wp-polls'), number_format_i18n(get_pollanswers(false))).'</li>'."\n";
		$content .= '<li>'.sprintf(_n('<strong>%s</strong> vote was casted.', '<strong>%s</strong> votes were casted.', get_pollvotes(false), 'wp-polls'), number_format_i18n(get_pollvotes(false))).'</li>'."\n";
		$content .= '</ul>'."\n";
	}
	return $content;
}