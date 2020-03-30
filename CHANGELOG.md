# Classifieds Plugin for glFusion - Change Log

## Version 1.3.0 (Released 2020-03-22)
- Fix namespace usage
- Implement `plugin_privacy_export` function
- Remove date-added field for categories, not used
- Implement Modified Preloaded Tree Traversal for categories
- Remove non-uikit theme support
- Remove fax number from user information
- Remove category block color options.
- Add color options for ad type banner in list
- Viewer can filter ads by category, type, and/or poster
- Remove Recent Ads menu option
- Remove original list-style ad display, now use UIkit-styled blocks
- Remove category image handling, no longer used
- Add drag-n-drop image uploading
- Users can no longer edit ads after approval

## Version 1.2.1 (Released 2018-01-11)
- Fix ad type management
- Remove Dataproxy driver

## Version 1.2.0 (Released 2017-10-05)
- Disable this plugin if the lgLib plugin is disabled or removed
- Wrap breadcrumbs on small displays
- Fix saving comment notification option in account settings
- Rename classes, use PHP autoloader
- Implement Classifieds namespace
- Use glFusion subscription functions
- Add global option to automatically (un)subscribe to/from subcategories
- Fix image deletion from ad edit page
- Require glFusion 1.6+
- Use JQuery AJAX functions
- Enable different versions of Ad templates, configurable globally
- Reset date added when an ad is approved

## Version 1.1.2 (Released 2017-01-21)
- Fix admin menu link
- Add missing comment count field to submissions table

## Version 1.1.1 (released 2017-01-10)
- Fix SQL error and template for autotags

## Version 1.1.0 (Released 2016-12-31)
Compatibile with glFusion 1.6.0 or higher.
- Refactor code into classes for different object types
- Add uikit templates when a uikit-based theme is in use
- Remove permissions for ads, only use ad owner and category permissions
- Remove recursion when checking access to categories
- Use LGLIB_ImageUrl to handle image resizing and caching
- Remove separate configurations for ad and category image locations
- Add DB field to hold the category parent tree to save lookups
- Standardize "description" field name for ads, types and categories
- Move notification functions to separate class
- Add AJAX update when adding days to an ad

## Version 1.0.4 (Released 2011-09-02)
- 0000459: [General] Remove EBay link configuration (lee) - resolved.
- 0000441: [General] All HTML tags are stripped from the ad text (lee) - resolved.
- 0000446: [Administration] Pagination in the category administration doesn't work (lee) - resolved.
- 0000421: [General] Paging isn't working when browsing categories (lee) - resolved.
- 0000419: [General] Add "expires" meta tag to ad display (lee) - resolved.

## Version 1.0.3 (Released 2010-07-09)
- 0000411: [General] URL rewrite not working (lee) - resolved.

## Version 1.0.2 (Released 2010-05-02)
Compatible with glFusion 1.2.0 and up only.
- 0000407: [General] Dates are always formated as m/d/y (lee) - resolved.
- 0000389: [General] Add autotag support (lee) - resolved.
- 0000401: [Administration] Use single config variable to control block display (lee) - resolved.
- 0000383: [General] Ad titles in what's new block keep getting shorter (lee) - resolved.
- 0000384: [General] What's new list not correctly formatted. (lee) - resolved.

## Version 1.0.1 (Released 2009-12-21)
- 0000367: [General] Add option to hide left & right blocks (lee) - closed.
- 0000369: [General] Unescaped strings are passed to search function (lee) - closed.

## glClassifieds - 1.0.0
Miscellaneous patches to version 0.4
- 0000363: [General] Add title attribute to items in What's New (lee) - resolved.
- 0000353: [General] Category icons do not show properly in the ad listing if the image filename contains spaces (lee) - resolved.
- 0000354: [General] IE doesn't see category blocks as links (lee) - resolved.
- 0000351: [Submissions] Hovering over the plus sign shows only one word of the mootip (lee) - resolved.
- 0000345: [Account Management] Users can't update their account settings (lee) - resolved.
- 0000340: [General] glFusion 1.1.6 menus appear over images shown in Slimbox (lee) - resolved.
- 0000343: [Dataproxy] Dataproxy driver doesn't encode ampersands in the url. (lee) - resolved.
- 0000344: [Administration] Disable block when plugin is disabled (lee) - resolved.
- 0000330: [Submissions] Comments-enabled dropdown always defaults to "closed" on new submissions (lee) - resolved.

## glClassifieds - 0.4.1
Patch for extra linefeed at the end of admin.php
- 0000329: [Administration] Extra linefeed in admin.php causing some sites to crash (lee) - resolved.

## glClassifieds - 0.4
Adds support for comments and the FCKEditor
- 0000304: [General] Add centerblock support (lee) - resolved.
- 0000313: [General] Add support for comments (lee) - resolved.
- 0000277: [Submissions] Add FCKEditor support for ad submissions (lee) - resolved.
- 0000295: [Submissions] Make the ad type selectable from admin definitions (lee) - resolved.
- 0000297: [Submissions] Image uploads fail strangely if they're too large. (lee) - resolved.

## glClassifieds - 0.3
Contains several significant changes. Gets most of the display logic out of the code and into templates and stylesheets. This version also allows installation via the glFusion auto-installer in version 1.1.2.
- 0000273: [Submissions] Add checks to ensure that only authorized users can submit (lee) - resolved.
- 0000262: [General] Implement more stylesheets and templates (lee) - resolved.
- 0000270: [General] Implement glFusion autoinstaller (lee) - resolved.
- 0000259: [General] Search gives a SQL error if a date range is specified. (lee) - resolved.
- 0000257: [General] Implement censoring of user input (lee) - resolved.
- 0000255: [General] Dataproxy driver not using COM_buildUrl for all urls (lee) - resolved.
- 0000253: [General] Add language customization (lee) - resolved.
- 0000254: [General] Remove inclusion of navbar.class.php (lee) - resolved.
- 0000251: [General] Size thumbnails properly in the Random Ad block (lee) - resolved.
- 0000252: [Submissions] Display user contact info in the submission form. (lee) - resolved.
- 0000245: [General] Highlight soon-to-expire ads under "My Ads" (lee) - resolved.

## glClassifieds - 0.2.3
Patch release for 0.2.2.  Can be considered "1.0rc1".
- 0000240: [Administration] Deleting plugin leaves the random ad block behind (lee) - resolved.
- 0000239: [Account Management] Make the "send message" link conditional upon configuration items (lee) - resolved.
- 0000237: [Administration] Adding days to expired ads should start extending now, not from the old expiration date (lee) - resolved.
- 0000236: [Notifications] Expiration notification template missing (cchiapusio) (lee) - resolved.
- 0000234: [General] plugin_runScheduledTask_classifieds() fails to include advt_functions.inc.php for ad deletion function (lee) - resolved.
- 0000235: [General] adDelete() doesn't delete ads unless run by an administrator (lee) - resolved.
- 0000220: [Notifications] Notify users in their own languages (lee) - resolved.
- 0000232: [Administration] Allow admin to change ad owner in edit screen (lee) - resolved.
- 0000228: [General] Update icons to be more consistent with glFusion (lee) - resolved.
- 0000226: [General] Create a link on the ad detail to all of the poster's ads (lee) - resolved.
- 0000224: [General] Merge "Ask Question" link with user id/name in ad detail view (lee) - resolved.
- 0000222: [Submissions] Use category permissions to restrict access to submitting & viewing (lee) - resolved.
- 0000219: [Notifications] Notify submitters upon ad approval (lee) - resolved.
- 0000221: [Account Management] User functions menu doesn't show up for normal users (lee) - resolved.
- 0000217: [Submissions] Friendlier error handling for user submissions (lee) - resolved.
- 0000218: [Administration] Installation routine failes with "access denied" message when config.php is missing (lee) - resolved.
- 0000216: [Submissions] For admin submissions, ads are created even if there's an error uploading the image. (lee) - resolved.

## glClassifieds - 0.2.2
Patch for 0.2.1.
- 0000215: [Submissions] Logged-in users are denied access to ad submission (lee) - resolved.

## glClassifieds - 0.2.1
Updates to second beta
- 0000214: [General] Search function not returning any matches (lee) - resolved.
- 0000213: [Administration] Random Ad block isn't removed when uninstalling the plugin (lee) - resolved.
- 0000212: [Administration] New Installation fails (lee) - resolved.

## glClassifieds - 0.2
Second beta release - 2008-12-15
- 0000211: [Administration] Create standalone cron task for purging and notifications (lee) - resolved.
- 0000210: [Administration] Activate category permissions (lee) - resolved.
- 0000208: [General] Simplify user-facing ad permissions system (lee) - resolved.
- 0000196: [General] Switch paging links (previous, next, etc) to glFusion google-style links (lee) - resolved.
- 0000209: [Notifications] User notifications not being sent for subcategories (lee) - resolved.
- 0000198: [Administration] Use glFusion submission queue for new ads (Trinity) (lee) - resolved.
 - 0000203: [Submissions] Create feature to bypass submission queue (lee) - resolved.
- 0000195: [General] Page links on Recent listing don't work (lee) - resolved.
- 0000207: [General] Need to allow users to extend ads even if they can't edit them (lee) - resolved.
- 0000205: [General] Add a global setting for allowing user edits (lee) - resolved.
- 0000206: [General] Help screen title is "Array" (lee) - resolved.
- 0000204: [Administration] Add global setting to require login for access to all ads (filipino) (lee) - resolved.
- 0000199: [Notifications] Notify users of impending ad expiration (lee) - resolved.
- 0000202: [Administration] Set up ad purging (lee) - resolved.
- 0000201: [General] Add a "random ad" block (lee) - resolved.
- 0000200: [Administration] Set a max time for ads and extensions (lee) - resolved.

## glClassifieds - 0.1.2
Bug fixes to 0.1.1, Released: 2008-11-30
- 0000194: [General] Plugin not handling glFusion url_rewrite (lee) - resolved.
- 0000193: [General] Error handling special characters (lee) - resolved.

## glClassifieds - 0.1.1
Renamed plugin from "advt" to "classifieds". Also a few minor fixes. There is no upgrade from 0.1 to 0.1.1; this should be considered the new "first release".

Released 2008-11-24

- 0000192: [Administration] Move path config to glFusion config system (lee) - resolved.
- 0000191: [Notifications] Admin is always notified regardless of submission queue status (lee) - resolved.
- 0000190: [Submissions] Users can exceed the max image count. (lee) - resolved.
- 0000181: [General] Make the purposes of icons more clear (lee) - resolved.
- 0000186: [Notifications] Replace static notification text with templates (lee) - resolved.
- 0000185: [General] Regular users are able to see unapproved ads by specifying the id in the URL (lee) - resolved.
- 0000184: [General] Deleting an image returns to the ad list instead of to the ad (lee) - resolved.
- 0000183: [General] Permissions issue for regular users. (lee) - resolved.
- 0000182: [General] Rename plugin, image upload dir and menu options (lee) - resolved.
- 0000180: [Submissions] Error uploading images. (lee) - resolved.
- 0000179: [Submissions] Display a message when an ad is submitted to the submission queue. (lee) - resolved.
- 0000178: [General] Add category images (lee) - resolved.
- 0000177: [General] Missing images still show "click to enlarge" (lee) - resolved.
- 0000176: [General] Replace text "remove" links with delete icons (lee) - resolved.

## glClassifieds - 0.1
Initial redevelopment work. Many changes to get the plugin to work with GLFusion 1.1.0.

Released 2008-11-12

- 0000132: [General] Remove Register Globals requirement (lee) - closed.
- 0000138: [General] Add tabbed menus (lee) - closed.
- 0000137: [Submissions] Allow users to select categories when submitting (lee) - closed.
- 0000136: [Submissions] Add a "Wanted" ad type (lee) - closed.
- 0000135: [Account Management] Remove duplicate display of account information (lee) - closed.
- 0000134: [General] Minimize publicly-accessible pages (lee) - closed.
- 0000133: [Notifications] Change notifications to send email for approved ads only. (lee) - closed.
- 0000139: [Administration] Add approval icons to ad list and editing screens (lee) - closed.
- 0000140: [Administration] Create a "Manage All Ads" screen (lee) - closed.
- 0000147: [General] Add GL permissions system to viewing, searching and editing ads (lee) - closed.
- 0000164: [Submissions] Use lightbox to display larger images (lee) - closed.
- 0000173: [Submissions] Add a keywords field to the ad (lee) - closed.
