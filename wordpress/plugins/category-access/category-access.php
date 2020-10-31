<?php
/*
Plugin Name: Category Access
Plugin URI: http://www.coppit.org/code/
Description: Protects categories of posts from specific users.
Version: 0.8.2
Author: David Coppit
Author URI: http://www.coppit.org
*/

load_plugin_textdomain('category-access', $path='wp-content/plugins/category-access');

include_once('category-access-options.php');
include_once('category-access-user-edit.php');

// --------------------------------------------------------------------

global $category_access_default_private_message;
$category_access_default_private_message =
	__('Sorry, you do not have sufficient privileges to view this post.', 'category-access');

$category_access_filtered_protected_post = false;

$debug_category_access = false;

class category_access {

// Undo the work of wptexturize in wp-includes/functions-formatting.php
function untexturize($text) {
	$cockney = array("&#8217;tain&#8217;t","&#8217;twere","&#8217;twas","&#8217;tis","&#8217;twill","&#8217;til","&#8217;bout","&#8217;nuff","&#8217;round","&#8217;cause");
	$cockneyreplace = array("'tain't","'twere","'twas","'tis","'twill","'til","'bout","'nuff","'round","'cause");
	$fixed_text = str_replace($cockney, $cockneyreplace, $text);

	$fixed_text = preg_replace('/&#8217;s/', "'s", $fixed_text);
	$fixed_text = preg_replace("/&#8217;(\d\d&#8217;s)/", "'$1", $fixed_text);
	$fixed_text = preg_replace('/(\s|\A|")&#8216;/', '$1\'', $fixed_text);
	$fixed_text = preg_replace('/(\d+)&#8243;/', '$1"', $fixed_text);
	$fixed_text = preg_replace("/(\d+)&#8242;/", '$1\'', $fixed_text);
	$fixed_text = preg_replace("/(\S)&#8217;([^'\s])/", "$1'$2", $fixed_text);
	$fixed_text = preg_replace('/(\s|\A)&#8220;(?!\s)/', '$1"$2', $fixed_text);
	$fixed_text = preg_replace('/&#8221;(\s|\S|\Z)/', '"$1', $fixed_text);
	$fixed_text = preg_replace("/&#8217;([\s.]|\Z)/", '\'$1', $fixed_text);
	$fixed_text = preg_replace("/ &#8482;/i", ' \(tm\)', $fixed_text);
	$fixed_text = str_replace("&#8221;", "''", $fixed_text);
	
	$fixed_text = preg_replace('/(\d+)&#215;(\d+)/', "$1x$2", $fixed_text);

	return $fixed_text;
}

// --------------------------------------------------------------------

// We assume that $original_html is valid HTML, with <li> or <br /> for each
// list item.
function filter_category_list($original_html)
{

	// Skip unless it looks like we were called with the entire list.
	// If Category_Access_show_private_categories is false, then all of the
	// items we will be given will be valid, since filter_category_list_query
	// would have already filtered out all the illicit ones.
	if (strpos($original_html, '<') === FALSE ||
			!get_option('Category_Access_show_private_categories'))
		return $original_html;

	# Work around for PHP's stupid auto-escaping
	$ce_s = 'oiaunweoiaunwefoiaunwefpauwinef_single';
	$ce_d = 'oiaunweoiaunwefoiaunwefpauwinef_double';
	$ce_bs = 'oiaunweoiaunwefoiaunwefpauwinef_backslash';

	$filtered_html = preg_replace('/\'/', $ce_s, $original_html);
	$filtered_html = preg_replace('/"/', $ce_d, $filtered_html);
	$filtered_html = preg_replace('/\\\\/', $ce_bs, $filtered_html);

	$filtered_html =
		preg_replace("/(<\s*a[^>]*)(http:.*?)((?= |$ce_s|$ce_d|>).*?>\s*)(.*?)(\s*<\/a>)/ie",
		"category_access::filter_category_list_item('\\1','\\2','\\3','\\4','\\5',\$original_html)",
		$filtered_html); 

	$filtered_html = preg_replace("/$ce_s/", "'", $filtered_html);
	$filtered_html = preg_replace("/$ce_d/", '"', $filtered_html);
	$filtered_html = preg_replace("/$ce_bs/", '\\\\', $filtered_html);

	return $filtered_html;
}

// --------------------------------------------------------------------

function filter_category_list_item($pre, $url, $mid, $category_name, $post,
		$original_html)
{
	$category_id =
		category_access::infer_category_id($url, $category_name, $original_html);

	global $current_user;

	if (is_null($category_id) ||
			category_access::user_can_access_category($category_id, $current_user))
		return "$pre$url$mid$category_name$post";

	# Make the changes
	$site_root = parse_url(get_settings('siteurl'));
	$site_root = trailingslashit($site_root['path']);

	$modified_name = $category_name;

	if (get_option('Category_Access_show_padlock_on_private_categories'))
		$modified_name =
			"$category_name&nbsp;<img src='${site_root}wp-content/plugins/category-access/padlock.gif' " .
			'height=\'10\' width=\'8\' valign=\'middle\' border=\'0\' ' .
			"class='category_access_padlock'/>";

	$modified_name = "<div class='category_access_protected_category'>" .
		"$modified_name</div>";

	return "$pre$url$mid$modified_name$post";
}

// --------------------------------------------------------------------

function infer_category_id($category_url, $category_name, $original_html)
{
	$category_id = null;
	$matched;

	// Try to infer it from the category URL
	{
		global $wp_rewrite;
		$category_permastruct = $wp_rewrite->get_category_permastruct();

		if (empty($category_permastruct)) {
			$using_category_id = true;
			$category_pattern =
				'/' . preg_quote(get_settings('home') . '/?cat=', '/') . '(\d+)/';
		} else {
			$using_category_id = false;
			$category_pattern = '/' . preg_quote(get_settings('home'), '/') .
				'.*?' . preg_quote($category_permastruct, '/') . '/';
			$category_pattern =
				preg_replace('/%category%/', '(?:[^\/"]+\/)*([^\/"]+)', $category_pattern);
		}

		$matched = (preg_match($category_pattern,$category_url,$matches) > 0);

		if ($matched) {
			if ($using_category_id) {
				$category_id = $matches[1];
			} else {
				$category = category_access::get_category_by_slug($matches[1]);
				$category_id = $category->cat_ID;
			}
		}
	}

	// The URL method failed. Try to get the category id from the name
	if (is_null($category_id)) {
		$fixed_category_name = category_access::untexturize($category_name);

		foreach (get_all_category_ids() as $possible_category_id) {
			if (get_catname($possible_category_id) == $fixed_category_name) {
				$category_id = $possible_category_id;
				break;
			}
		}
	}

	global $debug_category_access;

	if (is_null($category_id) || $debug_category_access) {
		print '<div style="position:absolute;top:0;left:0;background-color:yellow;width:body.clientWidth;color:black;">
		';
		if (is_null($category_id))
			print <<<EOT
<p>Category Access could not infer the category ID for $category_name.
Please do the following:</p>
<ul style="color:black;list-style-type:disc;margin-left:1em;">
<li> Disable all plugins except for this one to see if some other plugin
is causing the problem. If the problem goes away, re-enable each plugin until
you find the incompatible one.
<li> Try switching to a different theme, like the WordPress default theme.
</ul>
<p>Once you have tried the above steps, email <a
href="mailto:david@coppit.org">david@coppit.org</a> with the results of these
debugging steps. Also include the following information:</p>
EOT;
		print "<p>Original HTML:<br>\n";
		category_access::print_html_data($original_html);
		print '</p><p>Category pattern:<br>';
		category_access::print_html_data($category_pattern);
		print '</p><p>Category URL:<br>';
		category_access::print_html_data($category_url);
		print '</p><p>Category name:<br>';
		category_access::print_html_data($category_name);
		print "</p></div>\n";
		return null;
	}

	return $category_id;
}

// --------------------------------------------------------------------

function get_category_by_slug($slug) {
	$categories = get_categories();

	$slug_category = null;
	$warn = false;

	foreach ($categories as $category)
		if ($category->category_nicename === $slug)
		{
			if (!is_null($slug_category))
				$warn = true;

			$slug_category = $category;
		}

	if ($warn)
	{
		print '<div style="position:absolute;top:0;left:0;background-color:yellow;width:body.clientWidth;color:black;">

<p>You are using a category link style that uses a category slug rather than a
category ID, and you have two categories with the same category slug. Category
Access cannot process your categories properly because the links in the
category HTML below are not unique. You need to either use a link type that
has IDs, or make your slugs unique to their categories.</p>
';
		category_order::print_html_data($original_html);
		print "</p></div>\n";
	}

	return $slug_category;
}

// --------------------------------------------------------------------

function filter_category_list_query($exclusions)
{
	global $current_user;

	// WordPress will recursively call this. Avoid the stack overflow
	$first = true;
  foreach (debug_backtrace() as $bt) {
		if (!$first && $bt['function'] == 'filter_category_list_query')
			return $exclusions;
		$first = false;
	}

	if (get_option('Category_Access_show_private_categories'))
		return $exclusions;

	foreach (get_all_category_ids() as $category_id) {
		if (!category_access::user_can_access_category($category_id, $current_user))
			$exclusions .= " AND t.term_id <> $category_id ";
	}

	return $exclusions;
}

// --------------------------------------------------------------------

function post_should_be_hidden($postid)
{
	// Sometimes this is called as post_should_be_hidden($post->ID) when $post
	// is null. This happens for admin pages, plugins, etc.. In this case
	// $postid will also be null, and we want to return false.
	if (is_null($postid))
		return false;

	$post = get_post($postid);

	// Stupid WordPress doesn't pass the page as a second argument during
	// wp_list_pages. This is a nasty hack. See
	// http://trac.wordpress.org/ticket/4267
  foreach (debug_backtrace() as $bt)
		if ($bt['function'] == 'wp_list_pages')
			return false;

	if ($post->post_status == 'static' || $post->post_type == 'page')
		return false;

	if (!isset($postid))
		return true;

	global $current_user;

	$post_categories = wp_get_post_cats(1, $postid);

	if (get_option('Category_Access_show_if_any_category_visible')) {
		foreach ($post_categories as $post_category_id)
			if (category_access::user_can_access_category(
					$post_category_id, $current_user))
				return false;

		return true;
	} else {
		foreach ($post_categories as $post_category_id)
			if (!category_access::user_can_access_category(
					$post_category_id, $current_user))
				return true;

		return false;
	}
}

// --------------------------------------------------------------------

function user_can_access_category ($category_id,$user)
{
	do {
		if (!category_access::get_category_access_for_user($category_id, $user))
			return false;

		$this_category = get_category($category_id);
		$category_id = $this_category->category_parent;
	} while ($category_id != 0);

	return true;
}

// --------------------------------------------------------------------

function filter_title($text, $post_to_check=null)
{
	$post_id = $post_to_check->ID;

	global $post;

	if (is_null($post_id))
		$post_id = $post->ID;

	if (is_feed() || !category_access::post_should_be_hidden($post_id))
		return $text;

	$padlock_prefix = '';

	if (get_option('Category_Access_show_padlock_on_private_posts'))
	{
		$site_root = parse_url(get_settings('siteurl'));
		$site_root = trailingslashit($site_root['path']);

		$padlock_prefix = '<img src=\'' . $site_root .
			'wp-content/plugins/category-access/padlock.gif\' ' .
			'valign=\'middle\' border=\'0\' ' .
			'class=\'category_access_padlock\'/>';
	}

	$filtered_title = category_access::get_private_message();

	if (get_option('Category_Access_post_policy') == 'show title')
		$filtered_title = $text;

	return "<div class='category_access_protected_title'>" . 
		"$padlock_prefix$filtered_title</div>";
}

// --------------------------------------------------------------------

function get_private_message() {
	$message = get_option("Category_Access_private_message");

	if (is_null($message)) {
		global $category_access_default_private_message;
		$message = $category_access_default_private_message;
	}

	return $message;
}

// --------------------------------------------------------------------

function get_category_access_for_user($category_id, $user) {
	if ($user->has_cap('manage_categories'))
		return true;

	$user_id = $user->id;

	if ($user_id == 0)
		return get_option("Category_Access_cat_${category_id}_anonymous");

	$visible = get_option("Category_Access_cat_${category_id}_user_${user_id}");

	if ($visible === false)
		$visible = get_option("Category_Access_cat_${category_id}_default");

	return $visible;
}

// --------------------------------------------------------------------

function set_category_access_for_user($category_id, $user_id, $value) {
	if ($value)
		update_option("Category_Access_cat_${category_id}_user_${user_id}", '1');
	else
		update_option("Category_Access_cat_${category_id}_user_${user_id}", '0');
}

// --------------------------------------------------------------------

function filter_posts($sql)
{
	if (is_feed() && get_option('Category_Access_show_title_in_feeds') ||
			strpos($sql, 'post_status = "static"') !== false ||
			strpos($sql, 'post_type = \'page\'') !== false)
		return $sql;

	if (!is_feed() && (
			get_option('Category_Access_post_policy') == 'show title' ||
			get_option('Category_Access_post_policy') == 'show message' ||
			// For backwards compatibility
			get_option('Category_Access_show_private_message') ))
		return $sql;

	global $wpdb;

	$exclusions = category_access::get_invalid_categories();

	if (count($exclusions) == 0)
		return $sql;

	$ids = array();

	if (get_option('Category_Access_show_if_any_category_visible')) {
		$query  = " SELECT ID FROM $wpdb->posts INNER JOIN" .
			" $wpdb->term_relationships ON ( $wpdb->posts.ID = $wpdb->term_relationships.object_id )" .
			" WHERE 1 = 1";

		foreach ($exclusions as $invalid_category)
			$query .= " AND $wpdb->term_relationships.term_taxonomy_id.term_taxonomy_id != $invalid_category";

		$res = mysql_query($query) or die(mysql_error());

		while ($row = mysql_fetch_assoc($res))
			$ids[] = "'" . $row['ID'] . "'";

		if (!empty($ids))
			$sql .= " AND ID IN (" . implode(",", $ids) . ")";
	} else {
		$query = " SELECT ID FROM $wpdb->posts INNER JOIN" .
			" $wpdb->term_relationships ON ( $wpdb->posts.ID = $wpdb->term_relationships.object_id )" .
			" WHERE 0 = 1";

		foreach ($exclusions as $invalid_category)
			$query .= " OR $wpdb->term_relationships.term_taxonomy_id = $invalid_category";

		$res = mysql_query($query) or die(mysql_error());

		while ($row = mysql_fetch_assoc($res))
			$ids[] = "'" . $row['ID'] . "'";

		if (!empty($ids))
			$sql .= " AND ID NOT IN (" . implode(",", $ids) . ")";
	}

	// Remember whether we filtered out the one and only post
	global $category_access_filtered_protected_post;
	$category_access_filtered_protected_post = false;

	if (preg_match('/^ AND ID = (\d+)/', $sql, $matches))
		$category_access_filtered_protected_post = in_array("'$matches[1]'", $ids);

	return $sql;
}

// --------------------------------------------------------------------

function check_redirect() {
	global $category_access_filtered_protected_post;

	if ( $category_access_filtered_protected_post )
    auth_redirect();
}

// --------------------------------------------------------------------

function filter_content($text)
{
	if (strpos($_SERVER['REQUEST_URI'], '/wp-admin/') == true)
		return $text;

	global $post;

	if (category_access::post_should_be_hidden($post->ID)) {
		if (get_option('Category_Access_post_policy') == 'show title')
			$text = "<div class='category_access_protected_post'>" .
				category_access::get_private_message() . "</div>";
		else
			$text = '';
	}

	return $text;
}

// --------------------------------------------------------------------

function hide_text($text)
{
	if (strpos($_SERVER['REQUEST_URI'], '/wp-admin/') == true)
		return $text;

	global $post;

	if (category_access::post_should_be_hidden($post->ID))
		$text = '';

	return $text;
}

// --------------------------------------------------------------------

// Used during development to dump a data structure to html
function print_html_data($data) {
	$string = htmlspecialchars(print_r($data,1));
	$string = preg_replace("/\n/", "<br>\n", $string);
	$string = preg_replace("/ /", "&nbsp;", $string);

	print $string;
}

// --------------------------------------------------------------------

function backtrace()
{
   $output = "<div style='text-align: left; font-family: monospace;'>\n";
   $output .= "<b>";
   $output .= _e('Backtrace:', 'category-access');
   $output .= "</b><br />\n";
   $backtrace = debug_backtrace();

   foreach ($backtrace as $bt) {
       $args = '';
       foreach ($bt['args'] as $a) {
           if (!empty($args)) {
               $args .= ', ';
           }
           switch (gettype($a)) {
           case 'integer':
           case 'double':
               $args .= $a;
               break;
           case 'string':
               $a = htmlspecialchars(substr($a, 0, 64)).((strlen($a) > 64) ? '...' : '');
               $args .= "\"$a\"";
               break;
           case 'array':
               $args .= 'Array('.count($a).')';
               break;
           case 'object':
               $args .= 'Object('.get_class($a).')';
               break;
           case 'resource':
               $args .= 'Resource('.strstr($a, '#').')';
               break;
           case 'boolean':
               $args .= $a ? 'True' : 'False';
               break;
           case 'NULL':
               $args .= 'Null';
               break;
           default:
               $args .= 'Unknown';
           }
       }
       $output .= "<br />\n";
       $output .= "<b>file:</b> {$bt['line']} - {$bt['file']}<br />\n";
       $output .= "<b>call:</b> {$bt['class']}{$bt['type']}{$bt['function']}($args)<br />\n";
   }
   $output .= "</div>\n";
   return $output;
}
// ####################################################################

function get_invalid_categories() {
	global $current_user;

	foreach (get_all_category_ids() as $category_id) {
		if (!category_access::get_category_access_for_user($category_id, $current_user))
			$exclusions[] = $category_id;
	}
	return $exclusions;
}

// --------------------------------------------------------------------

// If the user saves a post in a category or categories from which they are
// restricted, remove the post from the restricted category(ies).  If there
// are no categories left, save it as Uncategorized with status 'Saved'.
function verify_category($post_ID) {
	global $wpdb;

	$postcats = $wpdb->get_col("SELECT term_taxonomy_id FROM $wpdb->term_relationships WHERE object_id = $post_ID ORDER BY term_taxonomy_id");
	$exclusions = category_access::get_invalid_categories();

	if (count($exclusions)) {
		$exclusions = implode(", ", $exclusions);
		$wpdb->query("DELETE FROM $wpdb->term_relationships WHERE object_id = $post_ID AND term_taxonomy_id IN ($exclusions)");
		$good_cats = $wpdb->get_var("SELECT COUNT(*) FROM $wpdb->term_relationships WHERE object_id = $post_ID");

		if (0 == $good_cats) {
			$wpdb->query("INSERT INTO $wpdb->term_relationships (`object_id`, `term_taxonomy_id`) VALUES ($post_ID, 1)");
			$wpdb->query("UPDATE $wpdb->posts SET post_status = 'draft' WHERE ID = $post_ID");
		}
	}
}

}

// --------------------------------------------------------------------

// We'll use a very low priority so that our plugin will run after everyone
// else's. That way we won't interfere with other plugins.

add_action('save_post',
	array('category_access','verify_category'), 10000);
add_filter('comment_author',
	array('category_access','hide_text'), 10000);
add_filter('comment_email',
	array('category_access','hide_text'), 10000);
add_filter('comment_excerpt',
	array('category_access','hide_text'), 10000);
add_filter('comment_text',
	array('category_access','hide_text'), 10000);
add_filter('comment_url',
	array('category_access','hide_text'), 10000);
add_filter('list_terms_exclusions',
	array('category_access','filter_category_list_query'), 10000);
add_filter('posts_where',
	array('category_access','filter_posts'), 10000);
add_filter('single_post_title',
	array('category_access','filter_title'), 10000, 2);
add_filter('the_content',
	array('category_access','filter_content'), 10000);
add_filter('the_excerpt',
	array('category_access','hide_text'), 10000);
add_filter('the_title',
	array('category_access','filter_title'), 10000, 2);
add_filter('the_title_rss',
	array('category_access','filter_title'), 10000, 2);

add_action('template_redirect',
	array('category_access','check_redirect'), 10000);

//TODO: NEEDED?
//add_filter('wp_list_pages',
//	array('category_access','filter_title'), 10000);

//add_filter('wp_list_pages', array('category_access','filter_posts'), 10000);

add_filter('wp_list_categories',
	array('category_access','filter_category_list'), 10000);

?>
