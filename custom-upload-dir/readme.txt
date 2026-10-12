=== Custom Upload Dir ===
Contributors: ulfben
Donate link: https://www.amazon.com/gp/registry/wishlist/2QB6SQ5XX2U0N/105-3209188-5640446?reveal=unpurchased&filter=all&sort=priority&layout=standard&x=21&y=17
Tags: upload, organize, files, media
Requires at least: 5.7
Tested up to: 7.1.3
Stable tag: 3.5.0
Requires PHP: 7.4
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Choose your own upload folders using post titles, categories, dates and more. Keep files easy to find when browsing your server.

== Description ==
Ever opened your uploads folder over FTP and wondered which post an image belongs to?

Custom Upload Dir lets you choose a folder structure that fits your site. Keep each post's images together, group files by category or author, or separate PDFs from photos. Your files become easier to find on the server and easier to work with when making backups or moving your site.

Set up a path template once, then upload through WordPress as usual. Placeholders such as `%postname%` and `%category%` are filled in automatically for each new upload. You can combine them with ordinary folder names to create your own structure.

= A few useful examples =

Suppose you upload `sunflowers.jpg` to a post called "Summer in the garden", published in July 2026 and filed under "Gardening". These templates would put it in:

* **A folder for each post:** `/%postname%/` → `/summer-in-the-garden/sunflowers.jpg`
* **Year, month, then post:** `/%year%/%monthnum%/%postname%/` → `/2026/07/summer-in-the-garden/sunflowers.jpg`
* **Category, then post:** `/%category%/%postname%/` → `/gardening/summer-in-the-garden/sunflowers.jpg`
* **Separate folders for file extensions:** `/%file_ext%/` → `/jpg/sunflowers.jpg`. A PDF would go in `/pdf/`.

These paths sit inside your site's upload folder, usually `wp-content/uploads`. Post folders use the post's URL slug, so a title such as "Summer in the garden" becomes `summer-in-the-garden`.

= Make the structure your own =

* Build paths from post slugs, IDs, dates, authors, the person uploading, file extensions and file types.
* Use categories, tags or your own custom taxonomies as folder names.
* Keep category hierarchies as subfolders, flatten them into a single folder name, or use just the top-level or most specific categories.
* Check example paths for your posts on the settings page before uploading files.
* Upload through the block editor or Media Library as usual.
* Add your own placeholders with a little PHP, if you need something specific to your site.

Start under **Settings → Custom Upload Dir**. Choose a template, save it, and use the preview to check where your next uploads will go. One template applies across your site.

**Existing files stay put.** The template applies to new uploads; changing it does not move old files or change their URLs. Uploads made directly to the Media Library have no post, so post-specific parts of the template are left out. This organizes the actual files on your server; it does not add virtual folders to the Media Library screen.

Created by [Ulf Benjaminsson](https://www.ulfbenjaminsson.com).

== Changelog ==

= 3.5.0 (2026-10-12) =

**Features and improvements**

* A clearer settings page with clickable, keyboard-accessible placeholders and simpler explanations.
* Better path previews, including uploads without a post and feedback when a preview post cannot be found.
* Developers can now add custom placeholders using `cud_path_tokens`, fixing the old approach that removed them before they could be replaced. The FAQ includes working examples (thanks [jeffpannone](https://wordpress.org/support/topic/generate-tags-via-cud_generate_path/) and [tomeagles](https://wordpress.org/support/topic/custom-taxonomy-20/)!).

**Bug fixes**

* Fixed cases where block-editor uploads used the wrong post's details or lost the post information needed for the folder name.
* Fixed failed uploads affecting the folder chosen for a later upload.
* Fixed folder calculations that could accidentally remove part of the site's upload base path.
* Fixed settings saves that could discard values. Invalid templates now show an error and keep the previous settings.
* Fixed the documented `%mime_type%` placeholder and restored support for older placeholder names.
* Fixed PHP notices when saved settings are missing taxonomy options (thanks [aljuk](https://wordpress.org/support/topic/plugin-custom-upload-dir-wp-errors/)!).
* Improved handling of missing authors and deleted categories.

**Maintenance**

* Cleaned up older PHP code and made settings-page output safer.
* Updated the French translation and added German and Swedish translations for the settings page and messages.
* Reworked the help and examples to make choosing a folder structure easier.
* Minimum requirements are now WordPress 5.7 and PHP 7.4.

**When upgrading:** your saved template and existing files are kept. Dated folders now come only from your template: include `/%year%/%monthnum%/` if you want them. Saving plugin settings no longer changes WordPress's separate Media settings.

= 3.4.6 (2020-07-17) =

* Fixed a null dereference error.

= Changelog v. 3.4.5 (2020-05-05) =

1. added french translation (thanks, bibi699depreau!)

= Changelog v. 3.4.4 (2019-03-24) =

1. Fixed a few PHP warnings
2. Removed the %permalink% template as it broke uploads to drafts.

= Changelog v. 3.4.3.2 (2019-03-24) =

1. Fixed 'upload_dir' not providing $post_id in Gutenberg editor.

= Changelog v. 3.4.1 (2015-03-16) =

1. Added [filter 'cud_generate_path'](https://wordpress.org/plugins/custom-upload-dir/installation/) so you can easily adjust the path with any variable you want.
2. Added %mime_type% to sort files by mime type
3. Added %author_role% to sort files by author role.
4. Coming: support for per-post-type settings. Eg: every post type can have their own rules for where to place uploads. Currently missing the admin interface. If you feel like helping out, let me know.

= Changelog v. 3.4 (2013-02-09) =

1. Added %file_ext% to sort by file type
2. Added [tentative support for uploads from WordPress apps](http://wordpress.org/support/topic/search-and-mobile-apps-compatibility?replies=6#post-3837193).
3. Removed setting for "Base upload folder" as WP core no longer exposes this
4. Cleared out some PHP error on the admin page
5. Fixed leaking CSS on admin page

= Changelog v. 3.3.2 (2011-12-29) =

1. Updated init to use WordPress API properly
2. Removed dead code

= Changelog v. 3.3.1 (2011-12-27) =

1. Fixed poor sanitization of post slugs (%post_name%)
2. Added %post_parent%
3. Fixed settings not always initializing

= Changelog v. 3.3 (2011-12-18) =

1. Support for WordPress 3.3
2. Support for tags and categories
3. Support for custom taxonomies
4. Added %current_user%, %permalink%
5. Added all permalink tags
6. Added test-function to admin page

= Changelog v. 3.0.2 (2011-07-29) =

1. Fix for post_author not working
2. Fix timestamps from the media uploader ([thanks; alx359](http://wordpress.org/support/topic/plugin-custom-upload-dir-unattached-images-upload-to-19700101?replies=8))

**Changelog v. 3.0.1 (2010-11-01)**

1. [Fix for "undefined function get_userdata()"](http://wordpress.org/support/topic/plugin-custom-upload-dir-fatal-error-call-to-undefined-function-get_userdata) (thanks somatic!)

**Changelog v. 3.0 (2010-10-21)**

1. Rewrote entire plugin for WordPress 3.0
2. A slew of new placeholders for building path's with
3. A new interface to allow arbitrary strings in path
4. Error messages when uploading to un-named or un-dated posts
5. Added translation support

**Changelog v.2.5.4 (2008-12-31)**

1. Adjusted the test case. (Pedja)

**Changelog v.2.5.3 (2008-12-30)**

More problems have crept up due to the [massive amount of dry runs of 'upload_dir'](http://trac.wordpress.org/ticket/8753) in WP 2.7.

1. Added quick bail for every call that's not for a file upload. (props to Pedja Ljubomirovic (Canada))
	
**Changelog v.2.5.2 (2008-12-15)**

1. Fixed problem with plugin blocking deletion/editing of attached files.

**Changes in v.2.5.1 (2008-12-14)**

1. Fixed Custom Upload Dir breaking WP 2.7 when called from the loop

**Changes in v.2.5 (2008-11-15)**

1. Support for WP 2.7-beta1
1. Added 'post_author'	
1. Fixed 'post_title' (broken since WP 2.4)
1. Fixed issue with use_yearmonth overriding post date	
1. Tons of small(ish) updates
1. Removed Mediacaster-support

**Changes in v.2.0** (2007-10-19)
 
1. Mediacaster compability.
1. Added proper configuration screen
1. Massive code overhaul
1. Name change (previously "Daily Upload Dir")

== Upgrade Notice ==

= 3.5.0 =
A clearer settings page, better previews and fixes for choosing the right upload folder. Your existing files stay put. Include `/%year%/%monthnum%/` in your template if you want dated folders.

= 3.4.4 =
Fixed a few PHP warnings, and removed %permalink% template as it broke drafts.

= 3.4.3.2 =
Added support for uploads from the Gutenberg editor.

= 3.4.2 =
Added filter, allows users to easily extend the list of tags with anything they want.

= 3.4 =
Added %file_ext%, support for uploads from WordPress apps and fixed some errors.

= 3.3.2 = 
Removed dead code and updated to use WordPress API properly

= 3.3.1 = 
Added %post_parent% and some fixes

= 3.3 =
Support for WordPress 3.3 and many improvements!

= 3.0.2 =
Fixes post_author and and post_date behaviour in the media uploader.

= 3.0.1 = 
[Fix for "undefined function get_userdata()"](http://wordpress.org/support/topic/plugin-custom-upload-dir-fatal-error-call-to-undefined-function-get_userdata) (thanks somatic!)

= 3.0 =
Lots of new features and more flexible interface.

== Installation ==

1. Extract the `custom-upload-dir`-folder and transfer it to the `/wp-content/plugins/` directory
1. Activate the plugin through the 'Plugins' menu in WordPress
1. Customize your directory structure in `Settings -> Custom Upload Dir`

== Frequently Asked Questions ==

= Will this add folders to the Media Library? =

Custom Upload Dir organizes the actual files on your web server. The WordPress Media Library looks and works as usual. The folder structure is useful when you browse your files through FTP, your hosting file manager or a backup.

= Can it reorganize my existing uploads? =

No. It chooses the destination for new uploads. Changing the template, renaming a post or changing its categories leaves previously uploaded files and their URLs alone.

= Which folders do date and post tokens use? =

For an attached upload, date tokens use the parent post's stored local date, including pages, backdated posts and scheduled posts. A draft without a usable date uses the current site-local time. `%postname%` uses the slug, then a sanitized title, then the post ID for an unnamed draft.

Uploads without a parent use the current site-local time and omit post-specific tokens. Name and save a post before uploading when you want its slug in the folder path. Changing a slug later does not move earlier uploads.

= How do I choose year/month folders? =

Include `/%year%/%monthnum%/` in your template. An empty template stores new uploads directly in the upload base. Saving plugin settings does not change WordPress's Media settings or automatically prepend date tokens. Existing saved templates keep their date tokens.

= Which tokens and aliases are supported? =

The settings page lists the current tokens. Any registered taxonomy name can be used as `%taxonomy%`. Unknown tokens are omitted after custom token resolution. `%file_type%` is the filename-derived MIME type, for example `image/jpeg` (two directory levels); `%file_ext%` is the extension. These values do not replace WordPress's file validation.

Legacy aliases remain available: `%mime_type%` → `%file_type%`, `%post_parent%` → `%parent_name%`, `%month%` → `%monthnum%`, `%ID%` → `%post_id%`, `%post_name%` → `%postname%`, and `%post_author%` → `%author%`. The removed `%permalink%` token is not supported. The older `%post_category%` and `%post_categories%` tokens are retained; prefer `%category%` for hierarchy settings.

= How can I define my own tokens? =

Use `cud_path_tokens` to supply a map of tokens to scalar values, before unresolved tokens are removed. This example adds `%site_section%`:

    function my_cud_tokens( $tokens, $post_id, $file ) {
        $tokens['%site_section%'] = $post_id ? 'posts' : 'library';
        return $tokens;
    }
    add_filter( 'cud_path_tokens', 'my_cud_tokens', 10, 3 );

Built-in post/file tokens take precedence, then custom tokens, then taxonomy and unknown tokens. Replacement values are literal data and are not recursively expanded. The existing `cud_generate_path` filter still receives the expanded path and parent ID. Use it to change the final path, not to resolve custom tokens:

    function my_cud_final_path( $path, $post_id ) {
        return '/archive' . $path;
    }
    add_filter( 'cud_generate_path', 'my_cud_final_path', 10, 2 );

For unattached uploads, the parent argument to these two filters is an empty string, preserving the original final-path contract. Paths are relative to the upload base, even when written with a leading slash. Drive/URL prefixes, control characters and ` . ` or ` .. ` directory segments are rejected after filters run. Invalid settings retain the previous saved settings.

= What about programmatic imports? =

Native uploads and REST media creation carry their request context automatically. A programmatic call to `media_handle_sideload()` does not expose its parent argument to upload filters. Importers can use the `cud_upload_post_id` filter to supply that ID for the duration of their upload, then remove their callback in a `finally` block. Without that context, the upload uses the unattached template behavior. Other upload systems or custom REST endpoints may need their own adapter.

= How do I change the base upload folder? =

The template creates folders beneath WordPress's existing upload base. To change that base on a single-site installation, WordPress supports an `UPLOADS` constant in wp-config.php, relative to the WordPress installation directory:

    define( 'UPLOADS', 'wp-content/your-folder' );

Changing the base on an existing site requires a separate migration of files and URLs. This plugin does not perform that migration. Removing the plugin leaves uploaded files in place.


== Screenshots ==

1. Choose an upload path using placeholders for the user, date and post name, and configure how category hierarchies become folders.


== Additional Info ==

Copyright (C) 2007-2026 [Ulf Benjaminsson](https://www.ulfbenjaminsson.com)

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
