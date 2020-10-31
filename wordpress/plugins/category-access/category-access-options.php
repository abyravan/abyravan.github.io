<?php

load_plugin_textdomain('category-access', $path='wp-content/plugins/category-access');

if (!class_exists("category_access_options")) {

class category_access_options {

function setup_category_access_options_page() {
	if (function_exists('add_options_page'))
		add_options_page(__('Category Access', 'category-access'), __('Category Access', 'category-access'), 9, __FILE__,
			array('category_access_options','manage_category_access_options'));
}

function manage_category_access_options() {
	category_access_options::handle_action();

	category_access_options::print_html();
}

function print_html() {
	print "<div class='wrap'>\n";
	print "\n<h2>" . __('Category Access Options', 'category-access') . "</h2>\n";

	$page_uri = $_SERVER['PHP_SELF'] . "?page=" . plugin_basename(__FILE__);

	// --------------------------------------------------------------

	function sort_category_ids_by_name($category_id1,$category_id2) {
		return strcmp(strtolower(get_catname($category_id1)),
			strtolower(get_catname($category_id2)));
	}

	function print_category_checkboxes($role, $blurb, $default) {

		$default_text = "";

		if (!$default)
			$default_text = 'display:none;';
		
		print <<<EOT

<div id="$role" style="$default_text">

<p>$blurb</p>

<p>

EOT;

		$category_ids = get_all_category_ids();

		usort($category_ids, 'sort_category_ids_by_name');

		foreach ($category_ids as $category_id) {
/*
$cat = get_category($category_id);
if ($cat->category_parent)
print "parent: " . get_catname($cat->category_parent);
*/

			print "  <input class=\"category_id_$role\" type=\"checkbox\" " .
				"name=\"Category_Access_cat_${category_id}_$role\"";

			if (get_option("Category_Access_cat_${category_id}_$role") == true)
				print " checked";

			print "> " . get_catname($category_id) . "<br/>\n";
		}

		$check_all_categories = __('Check all categories', 'category-access');
		$uncheck_all_categories = __('Uncheck all categories', 'category-access');

		print <<<EOT
</p>

<p>
  <input name="check_all_categories_$role" type="button" id="check_all_categories_$role" value="$check_all_categories" 
    onClick="check_categories('$role',true)">

  <input name="uncheck_all_categories_$role" type="button" id="uncheck_all_categories_$role" value="$uncheck_all_categories" 
    onClick="check_categories('$role',false)">
</p>

</div>

EOT;
	}

	print "\n<form name='category_access' method='post' action='$page_uri'>\n";

	// --------------------------------------------------------------

	print <<<EOT
<script language="javascript">

function show_role()
{
	the_form = document.category_access;

	selected_id = the_form.role_selection.options[the_form.role_selection.selectedIndex].value;

	for(i=0; i < document.getElementById("visibilities").childNodes.length; i++)
	{
		childNode = document.getElementById("visibilities").childNodes[i];

		if (childNode.nodeName != "DIV") 
			continue;

		if (childNode.id == selected_id)
			iState = true;
		else
			iState = false;

		childNode.style.display = iState ? "" : "none";
	} 
}

function check_categories(role,checked)
{
	the_form = document.category_access;

	all_check_boxes = the_form.getElementsByTagName('input');

	for (var i = 0; i < all_check_boxes.length; i++)
		if (all_check_boxes[i].className == 'category_id_' + role)
			all_check_boxes[i].checked = checked;
}

</script>

EOT;

	$category_access_for = __('Category Access for:', 'category-access');

	$visibility_data = array(

		'default' => array(
			'name' => __('New Users', 'category-access'),
			'blurb' => __('Set the default visibility of categories for new users below. These values can be set on a user-by-user basis in the edit user page.', 'category-access'),
			'selected' => true
		),

		'anonymous' => array(
			'name' => __('Anonymous Users', 'category-access'),
			'blurb' => __('Set the visibility of categories for users that are not logged in below. Usually this is the same as or more restrictive than the access granted to new users.', 'category-access'),
			'selected' => false
		),

	);

	print <<<EOT

<h3>$category_access_for
<select name="role_selection" onChange="show_role()">

EOT;

	foreach ($visibility_data as $role => $data) {
		print "<option value=\"$role\"" .
			($data['selected'] ? ' selected' : '') . '>' .
			$data['name'] . "</option>\n";
	}

	print "</select></h3>\n";

	print "\n<div id=visibilities>\n\n";

	foreach ($visibility_data as $role => $data) {
		print_category_checkboxes(
			$role,
			$data['blurb'],
			$data['selected']
		);
	}

	print "\n</div>\n\n";

	// --------------------------------------------------------------

	print "<h3>". __('Protected Posts in the Blog', 'category-access') ."</h3>\n";

	print '
<script language="javascript">

// Return the value of the radio button that is checked. Return an empty
// string if none are checked, or there are no radio buttons

function getCheckedValue(radioObj) {
	if(!radioObj)
		return "";
	var radioLength = radioObj.length;
	if (radioLength == undefined) {
		if (radioObj.checked)
			return radioObj.value;
		else
			return "";
	}
	for(var i = 0; i < radioLength; i++) {
		if(radioObj[i].checked)
			return radioObj[i].value;
	}
	return "";
}

function enable_disable_form_elements()
{
	the_form = document.category_access;

	private_message_div = document.getElementById("private_message_div");
	if (getCheckedValue(the_form.Category_Access_post_policy) != "hide") {
		private_message_div.style.color = "black";
		the_form.Category_Access_private_message.disabled = false;
		the_form.Category_Access_private_message.style.color = "black";
		the_form.Category_Access_show_padlock_on_private_posts.disabled = false;
	} else {
		private_message_div.style.color = "gray";
		the_form.Category_Access_private_message.disabled = true;
		the_form.Category_Access_private_message.style.color = "gray";
		the_form.Category_Access_show_padlock_on_private_posts.disabled = true;
	}

	private_categories_div = document.getElementById("private_categories_div");
	if (the_form.Category_Access_show_private_categories.checked) {
		private_categories_div.style.color = "black";
		the_form.Category_Access_show_padlock_on_private_categories.disabled = false;
	} else {
		private_categories_div.style.color = "gray";
		the_form.Category_Access_show_padlock_on_private_categories.disabled = true;
	}
}
</script>

';

	print '<p><input type="radio" name="Category_Access_post_policy" onClick="enable_disable_form_elements()" value="hide"' .
		(get_option('Category_Access_post_policy') == 'hide' ||
		// For backwards compatibility
		get_option('Category_Access_post_policy') == false &&
		get_option('Category_Access_show_private_message') == false ? ' checked' : '') .
		"> ". __('Hide entire post.', 'category-access') ."<br>\n";

	print '<input type="radio" name="Category_Access_post_policy" onClick="enable_disable_form_elements()" value="show title"' .
		(get_option('Category_Access_post_policy') == 'show title' ? ' checked' : '') .
		"> ". __('Show title, but a private message for the body text.', 'category-access') ."<br>\n";

	print '<input type="radio" name="Category_Access_post_policy" onClick="enable_disable_form_elements()" value="show message"' .
		(get_option('Category_Access_post_policy') == 'show message' ||
		// For backwards compatibility
		get_option('Category_Access_show_private_message') == true ? ' checked' : '') .
		"> ". __('Show a private message for the title and nothing for the body text.', 'category-access') ."</p>\n";

	print "<div id=private_message_div>\n";

	$private_message = category_access::get_private_message();

	print "<p>". __('The private post message:', 'category-access') ."<br>" .
		"<input name=Category_Access_private_message type=text size=50" .
		" value=\"$private_message\" /></p>\n";


	print "<p><input name=Category_Access_show_padlock_on_private_posts type=checkbox" .
		 (get_option('Category_Access_show_padlock_on_private_posts') ? " checked" : "") .
		 "> ". __('Show a padlock icon on the private post message.', 'category-access') ."</p>\n";

	print "</div>\n";

	print "<p><input name=Category_Access_show_if_any_category_visible type=checkbox" .
		(get_option('Category_Access_show_if_any_category_visible') ? " checked" : "") .
		"> ". __('Consider a message to be visible if the user can view <em>any</em> of its categories (rather than <em>all</em> of its categories).', 'category-access') ."</p>\n";


	print "<h3>". __('Protected Posts in Feeds', 'category-access') ."</h3>\n";

	echo "<p>". __('Users can always see the entire feed in their web browser by logging into the blog and checking the <em>Remember me</em> option. This will store a cookie on their computer that will be read by wordpress when the browser requests the feed. However, for feed readers that do not have cookie support, you can set the following option to show the title but not the text of protected posts in your feeds.', 'category-access') ."</p>";

	print "<p><input name=Category_Access_show_title_in_feeds type=checkbox " .
		(get_option('Category_Access_show_title_in_feeds') ? " checked" : "") .
		"> ". __('Show the title and links (but not the summary or content) instead of hiding posts.', 'category-access') ."</p>\n";


	print "<h3>". __('The Category List', 'category-access') ."</h3>\n";

	print "<p><input name=Category_Access_show_private_categories type=checkbox onClick='enable_disable_form_elements()'" .
		(get_option('Category_Access_show_private_categories') ? " checked" : "") .
		"> ". __('Show private categories.', 'category-access') ."</p>\n";

	print "<div id=private_categories_div style='padding-left:2em'>\n";

	print "<p><input name=Category_Access_show_padlock_on_private_categories type=checkbox " .
		(get_option('Category_Access_show_padlock_on_private_categories') ? " checked" : "" ) .
		"> ". __('Show a padlock icon next to private categories.', 'category-access') ."</p>\n";

	print "</div>\n";

	print '
<script language="javascript">
enable_disable_form_elements();
</script>

<p class="submit">
<input type="submit" name="submit" value="'. __('Update Options', 'category-access') .' &raquo;" /> 
</p>

<p class="submit">
<input type="submit" name="submit" value="'. __('Reset All Options', 'category-access') .'" />
</p>

</form>
';

}

// --------------------------------------------------------------------

function handle_action() {
	global $_POST;

	if ($_POST['submit'] == __('Reset All Options', 'category-access')) {
		delete_option('Category_Access_private_message',false);
		delete_option('Category_Access_post_policy',false);
		delete_option('Category_Access_post_policy',false);
		delete_option('Category_Access_show_if_any_category_visible',false);
		delete_option('Category_Access_show_padlock_on_private_posts',false);

		delete_option('Category_Access_show_title_in_feeds',false);

		delete_option('Category_Access_show_private_categories',false);
		delete_option('Category_Access_show_padlock_on_private_categories', false);

		$category_ids = get_all_category_ids();
		foreach ($category_ids as $category_id)
			delete_option("Category_Access_cat_${category_id}_default");
		foreach ($category_ids as $category_id)
			delete_option("Category_Access_cat_${category_id}_anonymous");

		return;
	}

	if (strpos($_POST['submit'], __('Update Options', 'category-access')) !== false) {
		$category_ids = get_all_category_ids();

		foreach ($category_ids as $category_id) {
			if ($_POST["Category_Access_cat_${category_id}_default"] == 'on')
				update_option("Category_Access_cat_${category_id}_default", true);
			else
				update_option("Category_Access_cat_${category_id}_default", false);

			if ($_POST["Category_Access_cat_${category_id}_anonymous"] == 'on')
				update_option("Category_Access_cat_${category_id}_anonymous", true);
			else
				update_option("Category_Access_cat_${category_id}_anonymous", false);
		}

		update_option('Category_Access_post_policy',
			$_POST['Category_Access_post_policy']);

		if ($_POST['Category_Access_show_if_any_category_visible'] == 'on')
			update_option('Category_Access_show_if_any_category_visible', true);
		else
			update_option('Category_Access_show_if_any_category_visible', false);

		update_option('Category_Access_private_message',
			$_POST['Category_Access_private_message']);

		if ($_POST['Category_Access_show_padlock_on_private_posts'] == 'on')
			update_option('Category_Access_show_padlock_on_private_posts', true);
		else
			update_option('Category_Access_show_padlock_on_private_posts', false);


		if ($_POST['Category_Access_show_title_in_feeds'] == 'on')
			update_option('Category_Access_show_title_in_feeds', true);
		else
			update_option('Category_Access_show_title_in_feeds', false);


		// Old data
		delete_option('Category_Access_show_private_message',false);

		if ($_POST['Category_Access_show_private_categories'] == 'on')
			update_option('Category_Access_show_private_categories', true);
		else
			update_option('Category_Access_show_private_categories', false);

		if ($_POST['Category_Access_show_padlock_on_private_categories'] == 'on')
			update_option('Category_Access_show_padlock_on_private_categories', true);
		else
			update_option('Category_Access_show_padlock_on_private_categories', false);

		return;
	}
}

}

}

// --------------------------------------------------------------------

add_action('admin_menu',
	array('category_access_options','setup_category_access_options_page'));

?>
