=== Category Access ===
Tags: categories, users
Contributors: coppit

This Wordpress plugin provides category protection on a user-by-user basis.
Posts in a protected category will not be visible unless the user has
privileges to view that category.

If a person selects categories that they do not have access to when editing or
writing a post, these categories are removed. If no categories remain, the
post is put into the uncategorized category and not published.

The latest version can be found at http://www.coppit.org/code/

NOTE: This plugin is compatible with Wordpress 2.3.X, and not prior versions.

== Installation ==

1. Upload the 'category-access' folder to your plugins folder, usually
   'wp-content/plugins/'
2. Modify your comments RSS code as described below
3. Activate the plugin on the plugin screen
4. By default, all categories will be hidden. Go to the "Category Access" page
   in options to change these.
5. To set category access on a user-by-user basis, edit the user's
   profile.

-> Posts RSS

Anonymous RSS readers will only see public posts. If a user uses an RSS reader
that supports cookies and web browsing (like Firefox or Safari), then the user
can log into Wordpress and their RSS feed will show all the posts they have
access to.

For users that have RSS readers that do not support cookies and web, there is
an option to just show the title and link of hidden posts in feeds. If you
want full visibility of the hidden posts, you can try the following steps:

- Install the HTTP Authentication plugin
	(http://dev.webadmin.ufl.edu/~dwc/2005/03/10/http-authentication-plugin/)
- Modify your web server to include the user's credentials
- Tell your users to use the URL http://user:pass@www.example.com/blog/feed/

Note that their password is sent in the clear. For utmost security, you'll
need to get a certificate and tell them to use
https://user:pass@www.example.com/blog/feed/ as the URL. This assumes that
their reader understands SSL authentication, which more and more do.

NOTE: I haven't tried the HTTP Authentication method described above.

-> Comments RSS

Unfortunately, WordPress 2.0-2.2 doesn't provide any hooks for filtering
comments in RSS feeds. As a result, comments for hidden posts will be shown in
the RSS feed. You will need to modify the WordPress code if you want to hide
comments on protected posts.

Edit the file wp-commentsrss2.php (pre-2.2) or
wp-includes/feed-rss2-comments.php (post-2.2), and change:

  get_post_custom($comment->comment_post_ID);

to:

  get_post_custom($comment->comment_post_ID);

  if (is_callable(array('category_access', 'post_should_be_hidden')) &&
      category_access::post_should_be_hidden($comment->comment_post_ID) &&
			!get_option('Category_Access_show_title_in_feeds'))
    continue;

also change:

  <?php if (!empty($comment_post->post_password) && $_COOKIE['wp-postpass'] != $comment_post->post_password) : ?>

to:

  <?php if (!empty($comment_post->post_password) && $_COOKIE['wp-postpass'] != $comment_post->post_password ||
  is_callable(array('category_access', 'post_should_be_hidden')) &&
		category_access::post_should_be_hidden($comment->comment_post_ID)) : ?>

== Styling Protected Posts ==

You can use CSS to control the style of the protected titles, posts,
categories, and padlock icons. Use the "category_access_protected_title",
"category_access_protected_post", "category_access_protected_category", and
"category_access_padlock" classes, respectively. 

== Known bugs ==

- Number of posts next to the monthly archives isn't adjusted for the hidden
	posts.
- This plugin may incorrectly restrict access to posts by other Wordpress code
	such as Akismet and trackbacks. I need help reproducing this problem so I
	can debug it. If you see missing posts please email me with instructions on
	how I can see the problem too.

== License ==

This code is distributed under the terms of the GPL.

== Credits ==

The primary author is David Coppit <http://www.coppit.org>.

This module was originally based on the viewlevel plugin by Kendra Burbank
http://www.furbona.org/viewlevel.html
