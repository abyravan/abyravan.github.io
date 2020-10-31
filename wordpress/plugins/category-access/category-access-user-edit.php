<?php

load_plugin_textdomain('category-access', $path='wp-content/plugins/category-access');

if (!class_exists("category_access_user_edit")) {

class category_access_user_edit {

function update() {
	if (strpos($_SERVER['REQUEST_URI'], '/wp-admin/user-edit.php') === false ||
			$_POST['action'] != 'update')
		return;  

	$user_id = empty($_POST['user_id']) ? $_GET['user_id'] : $_POST['user_id'];

	$category_ids = get_all_category_ids();

	foreach ($category_ids as $category_id) {
		if ($_POST["Category_Access_cat_${category_id}"] == 'on') {
			category_access::set_category_access_for_user($category_id, $user_id, true);
		} else {
			category_access::set_category_access_for_user($category_id, $user_id, false);
		}
	}
}

function print_html() {
	$user_id = empty($_POST['user_id']) ? $_GET['user_id'] : $_POST['user_id'];
	$user = new WP_User($user_id);
	if ($user->has_cap('manage_categories')) {
		print "<fieldset>\n";
		print "<legend>". __('Category Access', 'category-access') ."</legend>\n";

		print "<p class=\"desc\">". __('As a category manager, this user can view all categories.', 'category-access') ."</p>\n";
		print "</fieldset>\n";
		return;
	}

	print "<fieldset>\n";
	print "<legend>". __('Category Access', 'category-access') ."</legend>\n";

	print "<p class=\"desc\">". __('The following checked categories are visible to this user.', 'category-access') ."</p>\n";

	$category_ids = get_all_category_ids();

	function sort_category_ids_by_name($id1,$id2) {
	  return strcmp(strtolower(get_catname($id1)),
		strtolower(get_catname($id2)));
	}

	usort($category_ids, 'sort_category_ids_by_name');

	print <<<EOT
<script language="javascript">

function check_categories(checked)
{
	the_form = document.profile;

	all_check_boxes = the_form.getElementsByTagName('input');

	for (var i = 0; i < all_check_boxes.length; i++)
		if (all_check_boxes[i].className == 'category_id')
			all_check_boxes[i].checked = checked;
}

</script>

EOT;

	print "<table cellpadding=\"3\" cellspacing=\"3\">\n";

	foreach ($category_ids as $category_id) {
		print "<tr>\n";
		print "<td>";

		print "<input style=\"width:auto;\" type=\"checkbox\" " .
			'class="category_id" ' . "name=\"Category_Access_cat_${category_id}\"";

		if (category_access::get_category_access_for_user($category_id, $user))
			print " checked";

		print "> " . get_catname($category_id);

		print "</td>\n";
		print "</tr>\n";
	}

	print "</table>\n";


	$check_all_categories = __('Check all categories', 'category-access');
	$uncheck_all_categories = __('Uncheck all categories', 'category-access');

	print <<<EOT
<p>
  <input style="width:auto;" name="check_all_categories" type="button" id="check_all_categories" value="$check_all_categories" 
    onClick="check_categories(true)">

  <input style="width:auto;" name="uncheck_all_categories" type="button" id="uncheck_all_categories" value="$uncheck_all_categories" 
    onClick="check_categories(false)">
</p>
EOT;

	print "</fieldset>\n";
}

}

}

// --------------------------------------------------------------------

// We'll use a very low priority so that our plugin will run after everyone
// else's. That way we won't interfere with other plugins.

add_action('edit_user_profile',
  array('category_access_user_edit','print_html'));

add_action('init',
  array('category_access_user_edit','update'));

?>
