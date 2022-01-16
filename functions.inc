<?php
/**
 * Common functions for the Classifieds plugin.
 *
 * @author      Lee Garner <lee@leegarner.com>
 * @copyright   Copyright (c) 2009-2020 Lee Garner <lee@leegarner.com>
 * @package     classifieds
 * @version     v1.3.0
 * @license     http://opensource.org/licenses/gpl-2.0.php
 *              GNU Public License v2 or later
 * @filesource
 */

if (!defined('GVERSION')) {
    die('This file can not be used on its own.');
}

/**
 * Plugin-specific configuration items,
 * @global array $_CONF_ADVT
 */
global $_CONF_ADVT;

// Load the plugin configuration
$_CONF_ADVT = config::get_instance()->get_config('classifieds');

/** Additional config items and table definitions are added here */
require_once __DIR__ . '/classifieds.php';

if (file_exists(__DIR__ . '/config.php')) {
    /** Include local configuration overrides */
    include_once __DIR__ . '/config.php';
}
// New feature not fully implemented, different template options
if (!isset($_CONF_ADVT['detail_tpl_ver'])) {
    $_CONF_ADVT['detail_tpl_ver'] = '/v1';
}

// End of loading configurations.  Now, if login is required globally,
// set our configuration accordingly
if ($_CONF['loginrequired']) {
    $_CONF_ADVT['loginrequired'] = 1;
}

// Feature under development, forcefully disable from release
$_CONF_ADVT['purchase_enabled'] = 0;

// Language file Include
$langpath = $_CONF_ADVT['path'] . '/language';
$langfile = "{$_CONF['language']}.php";
if (!is_file("$langpath/$langfile")) {
    $langfile = 'english.php';
}
/** Include the proper language file */
include_once "$langpath/$langfile";
if (is_file("$langpath/custom/$langfile")) {
    /** Include the custom language file, if any */
    include_once "$langpath/custom/$langfile";
}

// Session variables for TimThumb image url, if used
SESS_setVar($_CONF_ADVT['pi_name'], array(
    'origpath'      => $_CONF_ADVT['imgpath'],
));

spl_autoload_register(function ($class)
{
    // project-specific namespace prefix
    $prefix = 'Classifieds\\';

    // does the class use the namespace prefix?
    $len = strlen($prefix);
    if (strncmp($prefix, $class, $len) !== 0) {
        // no, move to the next registered autoloader
        return;
    }

    // base directory for the namespace prefix
    $base_dir = __DIR__ . '/classes/';

    // get the relative class name
    $relative_class = substr($class, $len);

    // replace the namespace prefix with the base directory, replace namespace
    // separators with directory separators in the relative class name, append
    // with .php
    $file = $base_dir . str_replace('\\', '/', $relative_class) . '.class.php';

    // if the file exists, require it
    if (file_exists($file)) {
        require $file;
    }
});


/** Helper function, mainly for dvlpupdate.php */
function USES_classifieds_upgrade()
{
    require_once __DIR__ . '/upgrade.inc.php';
}


/**
 * Returns the items for this plugin that should appear on the main menu.
 *
 * NOTE: this MUST return the url/value pairs in the following format
 *      $<arrayname>[<label>] = <url>
 *
 * @return  array   Associative array of menu items to be added to menu
 */
function plugin_getmenuitems_classifieds()
{
    global $LANG_ADVT, $_CONF_ADVT;
    $menuitems = array(
        $LANG_ADVT['menuitem'] => $_CONF_ADVT['url'] . '/index.php',
    );
    return $menuitems;
}


/**
 * Check to see if this Plugin uses comments.
 *
 * @return  boolean     True if comment support is configured
 */
function plugin_commentsupport_classifieds()
{
    global $_CONF_ADVT;
    return $_CONF_ADVT['commentsupport'] == 0 ? false : true;
}


/**
 * Save a comment.
 * Saves the comment, updates the comment counter in the ad record,
 * and notifies the ad owner that a new comment has been posted.
 *
 * @param   string  $title      Comment title
 * @param   string  $comment    Comment text
 * @param   string  $id         Item id to which $cid belongs
 * @param   int     $pid        Comment parent
 * @param   string  $postmode   'html' or 'text'
 * @return  mixed   false for failure, HTML string (redirect?) for success
 */
function plugin_savecomment_classifieds($title,$comment,$id,$pid,$postmode)
{
    global $_CONF, $LANG03, $_TABLES, $LANG_ADVT, $_USER;

    // Without comment support, we shouldn't get here unless something
    // nefarious is going on, but just in case...
    if (!plugin_commentsupport_classifieds())
        return;

    $title = strip_tags($title);
    $pid = COM_applyFilter($pid, true);
    $postmode = COM_applyFilter($postmode);

    $ret = CMT_saveComment($title, $comment, $id, $pid,
            'classifieds', $postmode);

    if ($ret > 0) {         // failure, return to comment form
        return Classifieds\Menu::siteHeader()
            . CMT_commentform($title, $comment, $id, $pid,
                    'classifieds', $LANG03[14], $postmode)
            . Classifieds\Menu::siteFooter();
    }

    // success - update the comment counter for this ad
    $Ad = new \Classifieds\Ad($id);
    $Ad->updateComments();

    // Notify the ad's owner that a comment has been posted.
    \Classifieds\Notify::Comment($Ad);

    return (COM_refresh(CLASSIFIEDS_makeURL('detail', $id)));
}


/**
 * How the plugin will display specific comments.
 *
 * @param   string  $id     Unique idenifier for item comment belongs to
 * @param   int     $cid    Comment id to display
 * @param   string  $title  Page/comment title
 * @param   string  $order  'ASC' or 'DSC' or blank
 * @param   string  $format 'threaded', 'nested', or 'flat'
 * @param   int     $page   Page number of comments to display
 * @param   boolean $view   True to view comment (by cid), false to display (by $pid)
 * @return  mixed   results of calling the plugin_displaycomment_ function
 */
function plugin_displaycomment_classifieds($id, $cid, $title, $order, $format, $page, $view)
{
    global $LANG_ACCESS, $_USER;

    $retval = '';

    // Without comment support, we shouldn't get here unless something
    // nefarious is going on, but just in case...
    if (!plugin_commentsupport_classifieds())
        return $retval;

    $Ad = new \Classifieds\Ad($id);

    if (!$Ad->isNew()) {
        $delete_option = (
            plugin_ismoderator_classifieds() ||
            $Ad->getUid() == $_USER['uid']
        );
        $retval .= CMT_userComments(
            $id,
            $title,
            'classifieds',
            $order,
            $format,
            $cid,
            $page,
            $view,
            $delete_option,
            plugin_commentsupport_classifieds()
        );
    } else {
        $retval .= COM_startBlock(
            $LANG_ACCESS['accessdenied'], '',
            COM_getBlockTemplate ('_msg_block', 'header')
        )
        . $LANG_ACCESS['storydenialmsg']
        . COM_endBlock(COM_getBlockTemplate ('_msg_block', 'footer'));
    }
    return $retval;
}


/**
 * Delete a single comment.
 *
 * @since   v1.0.0
 * @param   integer $cid    Comment to be deleted
 * @param   string  $id     Item id to which $cid belongs
 * @return  mixed   false for failure, HTML string (redirect?) for success
 */
function plugin_deletecomment_classifieds($cid, $id)
{
    global $_CONF, $_TABLES;

    if (plugin_ismoderator_classifieds()) {
        if (CMT_deleteComment($cid, $id, 'classifieds') == 0) {
            $count = DB_count($_TABLES['comments'],
                        array('sid', 'type'),
                        array($id, 'classifieds'));
            $count--;
            if ($count < 0) $count = 0;
            DB_query("UPDATE {$_TABLES['ad_ads']}
                    SET comments=$count
                    WHERE ad_id='$id'");
            // Redirect back to the product detail page
            echo COM_refresh(CLASSIFIEDS_makeURL('detail', $id));
            exit;
        }
    }

    // Default return if anything fails
    return false;
}


/**
 * Handle any post-processing for an edited comment.
 * Just redirects back to the ad detail page.  The comment is actually
 * saved in comment.php.
 *
 * @param   integer $cid    Comment ID
 * @param   mixed   $id     Item ID
 */
function plugin_editcomment_classifieds($cid, $id)
{
    echo COM_refresh(CLASSIFIEDS_makeURL('detail', $id));
    exit;
}


/**
 * Shows the statistics for the plugin on stats.php.
 * If $showsitestats is 1 then we are to only print the overall stats in the 'site
 * statistics' box otherwise we show the detailed stats for the plugin
 *
 * @param    int showsitestate   Flag to let us know which stats to get
 * @return   string  returns formatted HTML to insert in stats page
 */
function plugin_showstats_classifieds($showsitestats)
{
    return '';
}

/**
 * This will put an option for the plugin in the command and control area.
 * Add the plugin name, icon and link to the command and control block
 * in moderation.php
 *
 * @return  array   Array containing (plugin name, admin url, url of plugin icon)
 */
function plugin_cclabel_classifieds()
{
    global $LANG_ADVT, $_CONF_ADVT;

    if (plugin_ismoderator_classifieds()) {
        return array(
            $LANG_ADVT['blocktitle'],
            $_CONF_ADVT['admin_url'] . '/index.php',
            plugin_geticon_classifieds()
        );
    } else {
        return '';
    }
}


/**
 * Returns the URL of the plugin's icon.
 *
 * @return  string      URL of the icon
 */
function plugin_geticon_classifieds()
{
    global $_CONF_ADVT;
    return $_CONF_ADVT['url'] . '/images/' . $_CONF_ADVT['pi_name'] . '.png';
}


/**
 * Returns the administrative option for this plugin.
 * Adds the plugin to the Admin menu.
 *
 * @return  array   Array of (plugin name, plugin admin url, # of items in plugin or '')
 */
function plugin_getadminoption_classifieds()
{
    global $_TABLES, $LANG_ADVT, $_CONF_ADVT;

    if (plugin_ismoderator_classifieds()) {
        return array(
            $LANG_ADVT['menuitem'] . ' (' .
                DB_count($_TABLES['ad_ads']) . ')',
            $_CONF_ADVT['admin_url'] . '/index.php',
        );
    } else {
        return '';
    }
}


/**
 * Returns the user option for this plugin.
 * Adds the plugin to the user menu.
 *
 * @return  array   Array containing (plugin name, plugin user url, 0)
 */
function plugin_getuseroption_classifieds()
{
    global $LANG_ADVT, $_CONF_ADVT;

    if (!COM_isAnonUser() && $_CONF_ADVT['hideuserfunction'] != 1) {
        return array(
            $LANG_ADVT['blocktitle'],
            $_CONF_ADVT['url'] . '/index.php?mode=account',
            0
        );
    } else {
        return '';
    }

}


/**
 * Automatically uninstall the plugin.
 *
 * @return  array   Array of plugin elements to remove
 */
function plugin_autouninstall_classifieds()
{
    global $_CONF_ADVT;

    $out = array (
        'tables'    => array(
            'ad_ads',
            'ad_category',
            'ad_photo',
            'ad_uinfo',
            'ad_catflds',
            'ad_submission',
            'ad_types',
        ),
        'groups'    => array(
            $_CONF_ADVT['pi_name'] . ' Admin',  // deprecated v1.3.1
        ),
        'features'  => array(
            $_CONF_ADVT['pi_name'] . '.admin',
            $_CONF_ADVT['pi_name'] . '.edit',
            $_CONF_ADVT['pi_name'] . '.submit',
        ),
        'php_blocks' => array(
            'phpblock_' . $_CONF_ADVT['pi_name'] . '_random',
        ),
        'vars'      => array(
            $_CONF_ADVT['pi_name'] . '_gid',    // deprecated v1.3.1
        )
    );
    return $out;
}


/**
 * Returns the block title for the What's New block, if supported.
 *
 * @return  mixed   Array of title info, false if unsupported or nothing to show
 */
function plugin_whatsnewsupported_classifieds()
{
    global $_CONF_ADVT, $LANG_ADVT, $LANG_WHATSNEW;

    if ( ($_CONF_ADVT['loginrequired'] == 1 && COM_isAnonUser()) ||
        $_CONF_ADVT['hidenewads'] == 1 ||
        plugin_getwhatsnew_classifieds(true) == 0
    ) {
        $retval = false;
    } else {
        $retval = array($LANG_ADVT['blocktitle'], '<small>'
                     . COM_formatTimeString( $LANG_WHATSNEW['new_last'],
                              $_CONF_ADVT['newadsinterval'] * 86400 )
                         . '</small>'
                       );
    }
    return $retval;
}


/**
 * Get the new ads to display in the What's New block.
 * Caches the count and block contents for 2 hours.
 *
 * @param   boolean $countonly  True to just count, false to get the list
 * @return  string  Formatted list of items & urls
 */
function plugin_getwhatsnew_classifieds($countonly = false)
{
    global $_CONF, $_TABLES, $_USER, $_CONF_ADVT, $LANG_ADVT;

    $cachekey_blk = 'whatsnew_blk';
    $cachekey_count = 'whatsnew_count';
    if ($countonly) {
        // Just getting the count for the whatsnew_supported function
        $count = \Classifieds\Cache::get($cachekey_count);
        if ($count !== NULL) {
            return (int)$count;
        }
    } else {
        $block = \Classifieds\Cache::get($cachekey_blk);
        if ($block !== NULL) {
            return $block;
        }
    }

    // Fall through here if the cache isn't valid
    $sql = "SELECT a.ad_id, a.cat_id, a.subject
            FROM {$_TABLES['ad_ads']} a
            RIGHT JOIN {$_TABLES['ad_category']} c
                ON a.cat_id = c.cat_id
            WHERE a.add_date >= UNIX_TIMESTAMP(DATE_SUB(NOW(),
                    INTERVAL {$_CONF_ADVT['newadsinterval']} DAY))
                AND a.exp_date > UNIX_TIMESTAMP(NOW()) " .
                COM_getPermSQL('AND', 0, 2, 'c') .
            ' ORDER BY a.add_date DESC
            LIMIT 10';
    //echo $sql;die;
    $result = DB_query($sql);
    $nrows = DB_numRows($result);

    $title_len = $_CONF['title_trim_length'];

    if ($nrows > 0) {
        $newads = array();
        while ($A = DB_fetchArray($result, false)) {
            $url = CLASSIFIEDS_makeURL('detail', $A['ad_id']);
            // Trim the length if over 16 characters
            if (strlen($A['subject']) > $title_len) {
                $title = substr($A['subject'], 0, $title_len - 3) . '...';
            } else {
                $title = $A['subject'];
            }
            $newads[] = "<a href=\"$url\" title=\"" .
                htmlspecialchars($A['subject']) . "\">" .
                htmlspecialchars($title) . "</a>\n";
        }

        $block = COM_makeList($newads, 'list-new-ads');
    } else {
        $block = $LANG_ADVT['no_new_ads'] . '<br />' . LB;
    }

    // Set the block and count into the cache. Tag as "ads" so these will
    // be deleted when ads are updated.
    \Classifieds\Cache::set($cachekey_count, $nrows, 'ads', 120);
    \Classifieds\Cache::set($cachekey_blk, $block, 'ads', 120);

    if ($countonly) {
        // Just getting the count for the whatsnew_supported function
        return $nrows;
    } else {
        return $block;
    }
}


/**
 * Get the search types for the site search.
 *
 * @return  mixed   Array of typeID=>typeName, or empty string for none.
 */
function plugin_searchtypes_classifieds()
{
    global $_CONF, $_CONF_ADVT, $LANG_ADVT;

    if (COM_isAnonUser() &&
        (($_CONF['loginrequired'] == 1) || ($_CONF_ADVT['loginrequired'] == 1))
    ) {
        return '';
    }

    return array('classifieds' => $LANG_ADVT['classifieds']);
}


/**
 * Get the formatted email message for subscription notifications.
 *
 * @param   string  $category   Type of item, always 'category'
 * @param   mixed   $track_id   Category ID
 * @param   mixed   $post_id    Ad ID
 * @param   integer $post_uid   Poseter UID
 */
function plugin_subscription_email_format_classifieds($category, $track_id, $post_id, $post_uid)
{
    global $_CONF, $_USER, $_TABLES, $LANG01, $LANG03, $LANG04;

    $filter = sanitizer::getInstance();
    $AllowedElements = $filter->makeAllowedElements($_CONF['htmlfilter_comment']);
    $filter->setAllowedelements($AllowedElements);
    $filter->setNamespace('classifieds','category');

    // Get the ad from the DB and make sure it exists
    $Ad = new \Classifieds\Ad($post_id);
    if ($Ad->isNew()) return false;

    // Sanitize text fields for the message
    $permalink = CLASSIFIEDS_makeURL('detail', $Ad->getID());
    $title = COM_checkWords($Ad->getSubject());
    $title = @htmlspecialchars($title, ENT_QUOTES, COM_getEncodingt());
    $text = nl2br($Ad->getDscp());
    $text = $filter->_replaceTags($text);

    $T = new Template(__DIR__ . '/templates/notify/english');
    $T->set_file('message', 'subscriber.thtml');
    $T->set_var(array(
        'cat'       => $Ad->getCat()->BreadCrumbs(),
        'subject'   => $title,
        'description' => $text,
        'ad_url'    => $permalink,
        'price'     => $Ad->getPrice(),
        'ad_type'   => $Ad->getType()->getDscp(),
    ), false);
    $T->parse('output','message');
    $message = $T->finish($T->get_var('output'));

    $html2txt = new Html2Text\Html2Text($message, false);
    $messageText = $html2txt->get_text();
    return array(
        'msgtext' => $messageText,
        'msghtml' => $message,
        'imagetdata' => array(),
        'subject' => $title,
    );
}


/**
 * This searches for ads matching the user query.
 * Returns an array for the header and table rows back to search.php
 * where it will be formated and printed.
 *
 * @param   string  $query      Keywords user is looking for
 * @param   date    $datestart  Start date to get results for
 * @param   date    $dateend    End date to get results for
 * @param   string  $topic      The topic they were searching in
 * @param   string  $type       Type of items they are searching, or 'all'
 * @param   integer $author     Get all results by this author
 * @param   string  $keyType    search key type: 'all', 'phrase', 'any'
 * @param   integer $page       page number of current search
 * @param   integer $perpage    number of results per page
 * @return  object              search result object
 */
function plugin_dopluginsearch_classifieds(
    $query,
    $datestart,
    $dateend,
    $topic,
    $type,
    $author,
    $keyType,
    $page,
    $perpage)
{
    global $_CONF, $_TABLES, $LANG_LINKS_SEARCH, $LANG_ADVT, $_CONF_ADVT;

    if (COM_isAnonUser() &&
        (($_CONF['loginrequired'] == 1) || ($_CONF_ADVT['loginrequired'] == 1))
    ) {
        return '';
    }

    // Encode query for inclusion in result's url
    $htmlquery = urlencode($query);

    $query = trim(DB_escapeString(htmlspecialchars($query)));

    // Start the SQL query by selecting desired fields.
    $sql = "SELECT a.ad_id as id, a.cat_id, a.subject as title,
                a.description, a.uid, a.views as hits,
                a.add_date AS date, a.ad_type,
                CONCAT('/{$_CONF_ADVT['pi_name']}/index.php?mode=detail&id=',a.ad_id,'&query=$htmlquery') as url
            FROM {$_TABLES['ad_ads']} a
            RIGHT JOIN {$_TABLES['ad_category']} c
                ON a.cat_id = c.cat_id
            WHERE exp_date > UNIX_TIMESTAMP(NOW()) "
                . COM_getPermSQL('AND', 0, 2, 'c');

    if (!empty($author)) {
        $sql .= " AND (a.uid = '$author') ";
    }

    $search = new SearchCriteria($_CONF_ADVT['pi_name'], $LANG_ADVT['blocktitle']);
    $columns = array('a.subject', 'a.description', 'a.keywords');
    $sql .= $search->getDateRangeSQL('AND', 'a.add_date',
                    $datestart, $dateend);
    list($sql, $ftsql) = $search->buildSearchSQL($keyType, $query, $columns, $sql);
    //echo $sql;die;
    $search->setSQL($sql);
    $search->setFTSQL($ftsql);
    $search->setRank(5);
    $search->setURLRewrite(true);

    return $search;
}


/**
 * Returns additional CSS files to be imported.
 * Checks first for a layout-specific version, then loads the default if none.
 *
 * @return  array   Array of filenames
 */
function plugin_getheadercss_classifieds()
{
    global $_CONF, $_CONF_ADVT, $_PLUGINS;

    $styles = array();
    $pi_css = $_CONF_ADVT['path'] . '/css/';
    $layout_css = $_CONF['path_layout'].'plugins/'.$_CONF_ADVT['pi_name'].'/';
    $layout_dirs = array(
        $layout_css . 'custom/', $layout_css,
        $pi_css . 'custom/', $pi_css,
    );

    $files = array('style.css');

    foreach ($files as $file) {
        foreach ($layout_dirs as $dir) {
            if (@file_exists($dir . $file)) {
                $styles[] = $dir . $file;
                break;
            }
        }
    }
    return $styles;
}


/**
 * Get additional Javascript files to import in the header.
 *
 * @return  array   Array of filenames to import
 */
function plugin_getheaderjs_classifieds()
{
    global $_CONF, $_CONF_ADVT;

    $js_dir = $_CONF_ADVT['path'] . '/js/';

    $files = array(
        "$js_dir/toggleEnabled.js",
    );
    return $files;
}


/**
 * Selection dropdown to pick groups in the configuration manager.
 *
 * @return  array   Associative array of grp_name=>grp_id
 */
function plugin_configmanager_select_grp_classifieds()
{
    global $_TABLES;

    $A = array();
    $sql = "SELECT grp_id, grp_name FROM {$_TABLES['groups']}";
    $result = DB_query($sql);
    if ($result && DB_numRows($result) > 0) {
        while ($row = DB_fetchArray($result)) {
            $A[$row['grp_name']] = $row['grp_id'];
        }
    }
    return $A;
}


/**
 * Upgrade the plugin.
 *
 * @return  boolean True on success, False on error
 */
function plugin_upgrade_classifieds()
{
    USES_classifieds_upgrade();
    return classifieds_do_upgrade();
}


/**
 * Returns list of moderation values.
 *
 * The array returned contains (in order): the row 'id' label, main plugin
 * table, moderation fields (comma seperated), and plugin submission table
 *
 * @return  array   Returns array of useful moderation values
 */
function plugin_moderationvalues_classifieds()
{
    global $_TABLES;

    return array(
        'ad_id',
        $_TABLES['ad_ads'],
        'ad_id, cat_id, subject, description, url, add_date, exp_date,
            uid, price, ad_type, keywords',
        $_TABLES['ad_submission']);
}


/**
 * Returns the current user's ability to moderate the plugin.
 *
 * Checks that the current user has the rights to moderate the
 * plugin, returns true if this is the case, false otherwise
 *
 * @return  boolean     Returns true if moderator
 */
function plugin_ismoderator_classifieds()
{
    static $isMod = NULL;
    if ($isMod === NULL) {
        $isMod = SEC_hasRights('classifieds.admin');
    }
    return $isMod;
}


/**
 * Show items for moderation on submissions page.
 *
 * Uses the Plugin class to return data required by moderation.php to list
 * plugin objects that need to be moderated.
 *
 * @return  object  Plugin object containing submission data
 */
function plugin_itemlist_classifieds()
{
    global $_TABLES, $LANG_ADVT;

    if (plugin_ismoderator_classifieds()) {
        $plugin = new Plugin();
        $plugin->submissionlabel = $LANG_ADVT['menuitem'];
        $plugin->getsubmissionssql = "SELECT
                    ad_id as id, subject,
                    FROM_UNIXTIME(add_date) as date, price,
                    uid
                FROM {$_TABLES['ad_submission']}";
        $plugin->addSubmissionHeading($LANG_ADVT['subject']);
        $plugin->addSubmissionHeading($LANG_ADVT['added_on']);
        $plugin->addSubmissionHeading($LANG_ADVT['price']);

        return $plugin;
    }
}


/**
 * Performs plugin exclusive work for items approved by moderation.
 * Notifies users of approval/denial, and subscribers if approved.
 *
 * @param   string  $id         ID of the ad
 * @param   integer $owner_id   Optional ad owner ID
 * @return  string              Empty string.
 */
function plugin_moderationapprove_classifieds($id, $owner_id=0)
{
    // Notify subscribers.
    $Ad = new \Classifieds\Ad($id);
    Classifieds\Notify::Approval($Ad, true);
    Classifieds\Notify::Subscribers($Ad);
    // Update the date added to now.
    Classifieds\Ad::updateAddDate($id);
    return '';
}


/**
 * Performs plugin exclusive work for items deleted by moderation.
 * The ad submission record is already deleted.
 * Delete uploaded images, notify submitter of denial.
 * Can't notify the submitter since the data has already been deleted.
 *
 * @param   string  $id     ID of the ad deleted.
 * @return  string          Any wanted HTML output
 */
function plugin_moderationdelete_classifieds($id)
{
    global $_TABLES, $_CONF_ADVT;

    $Ad = new \Classifieds\Ad($id, 'ad_submission');

    // Return the unused days back to the user's account
    if ($_CONF_ADVT['purchase_enabled']) {
        $User = new \Classifieds\UserInfo($Ad->getUid());
        /*list($owner_id, $add_date, $exp_date) = DB_fetchArray(DB_query(
            "SELECT uid, add_date, exp_date
            FROM {$_TABLES['ad_submission']}
            WHERE ad_id='$id'"));*/
        $days = ($Ad->getExpDate()- $Ad->getAddDate()) / 86400;
        $User->UpdateDaysBalance($days);
    }

    // Delete uploaded photos from filesystem
    \Classifieds\Image::DeleteAll($id);
    \Classifieds\Notify::Approval($Ad, false);
    return '';
}


/**
 * Returns the plugins submission form.
 *
 * @return  string      The HTML form
 */
function plugin_submit_classifieds()
{
    echo __FUNCTION__ . ' deprecated';die;
    $Ad = new \Classifieds\Ad();
    return $Ad->Edit();
}


/**
 * Provides the getItemInfo function for classified ads.
 *
 * @param   string  $id         Product ID
 * @param   string  $what       Comma-separated list of fields to return
 * @param   integer $uid        User ID
 * @param   array   $options    Not used
 * @return  array           Array of product information
 */
function plugin_getiteminfo_classifieds($id, $what, $uid=0, $options=array())
{
    global $_TABLES, $_CONF, $LANG_ADVT;

    $properties = explode(',', $what);
    $fields = array();
    $retval = array();
    $buildingSearchIndex = false;
    foreach ($properties as $p) {
        switch ($p) {
        case 'search_index':
            $buildingSearchIndex = true;
            break;
        case 'id':
            $fields[] = 'ad_id';
            break;
        case 'title':
            $fields[] = 'subject';
            break;
        case 'content':
        case 'description':
            $fields[] = 'description';
            break;
        case 'searchidx':
            $fields[] = 'subject';
            $fields[] = 'description';
            $fields[] = 'keywords';
            break;
        case 'price':
            $fields[] = 'price';
            break;
        case 'author':
        case 'author_name':
            $fields[] = 'uid';
            break;
        case 'date':
            $fields[] = 'add_date';
            break;
        }
    }
    if (count($fields) == 0) {
        return $retval;
    }
    $fields = array_unique($fields);
    $fields = implode(',', $fields);
    $retval = array();
    $sql = "SELECT $fields
            FROM {$_TABLES['ad_ads']} ";
    if ($id == '*') {
        if ( $buildingSearchIndex ) {
            $where = ' WHERE exp_date > UNIX_TIMESTAMP() ';
        } else {
            $where = ' WHERE 1=1 ';
        }
    } else {
        $where = " WHERE (ad_id = '" . DB_escapeString($id) . "')";
    }
    //echo $sql;die;
    $res = DB_query($sql . $where);
    while ($A = DB_fetchArray($res, false)) {
        foreach ($properties as $p) {
            switch ($p) {
            case 'id':
                $props[$p] = $A['ad_id'];
                break;
            case 'excerpt':
            case 'description':
            case 'content':
                $props[$p] = $A['description'];
                break;
            case 'title':
                $props[$p] = $A['subject'];
                break;
            case 'author':
                $props[$p] = $A['uid'];
                break;
            case 'author_name':
                $props[$p] = COM_getDisplayName($A['uid']);
                break;
            case 'date':
                $props[$p] = $A['add_date'];
                break;
            case 'searchidx':
                $props[$p] = $A['subject'] . ' ' . $A['description'] . ' ' .
                        $A['keywords'];
                break;
            case 'url':
                $props[$p] = COM_buildUrl($_CONF['site_url'] .
                    '/classifieds/index.php?mode=detail&id=' . urlencode($id));
                break;
            default:
                $props[$p] = isset($A[$p]) ? $A[$p] : NULL;
                break;
            }
        }
        $mapped = array();
        foreach ($props as $key => $value) {
            if ($id == '*') {
                if ($value != '') {
                    $mapped[$key] = $value;
                }
            } else {
                $mapped[$key] = $value;
            }
        }

        if ($id == '*') {
            $retval[] = $mapped;
        } else {
            $retval = $mapped;
            break;
        }
    }

    if (($id != '*') && (count($retval) == 1)) {
        $tRet = array_values($retval);
        $retval = $tRet[0];
    }
    if ($retval === '' || (is_array($retval) && count($retval) == 0)) {
        return NULL;
    } else {
        return $retval;
    }
}


/**
 * Get the URL for the item when linked from a comment.
 *
 * @return  array   Array of (base url, id string)
 */
function plugin_getcommenturlid_classifieds()
{
    global $_CONF_ADVT;
    return array(
        $_CONF_ADVT['url'] . '/detail.php',
        'id',
    );
}


/**
 * Returns the number of submissions waiting moderation.
 *
 * Calculates the number of submissions that are currently waiting
 * to be moderated and returns that number.
 *
 * @since   v0.1.2
 * @return  integer     Number of submissions awaiting moderation
 */
function plugin_submissioncount_classifieds()
{
    global $_TABLES, $_CONF_ADVT;

    // Have to make sure the submission table exists (version > 0.1.2)
    if ($_CONF_ADVT['pi_version'] >= '0.1.2') {
        return DB_count($_TABLES['ad_submission']);
    } else {
        return 0;
    }
}


/**
 * Get the currently installed version.
 *
 * @return  string  Plugin version
 */
function plugin_chkVersion_classifieds()
{
    global $_CONF_ADVT;
    return $_CONF_ADVT['pi_version'];
}


/**
 * Perform scheduled functions, such as notifications and ad purging.
 *
 * @param   boolean $standalone TRUE if this is run via a standalone script
 */
function plugin_runScheduledTask_classifieds($standalone=false)
{
    global $_TABLES, $_CONF_ADVT, $_CONF, $LANG_ADVT;

    if (!$standalone && $_CONF_ADVT['use_gl_cron'] == 0)
        return;

    $purge_cache = false;   // assume there's nothing to delete

    // Other plugins should consider expired ads as "deleted"
    $r = DB_query(
        "SELECT ad_id FROM {$_TABLES['ad_ads']}
        WHERE exp_date < " . time()
    );
    while ($A = DB_fetchArray($r, false)) {
        $purge_cache = true;    // Flag to clear cache
        PLG_itemDeleted($A['ad_id'], $_CONF_ADVT['pi_name']);
    }
    if ($purge_cache) {
        // Clear the cache to force recreation of lists and blocks
        \Classifieds\Cache::clear('ads');
    }

    // Check if purging is disabled
    if ($_CONF_ADVT['purge_days'] > 0) {
        $purge_exp_date = time() - ((int)$_CONF_ADVT['purge_days'] * 86400);
        $r = DB_query(
            "SELECT ad_id FROM {$_TABLES['ad_ads']}
            WHERE exp_date < $purge_exp_date"
        );
        if ($r) {
            $numrows = DB_numRows($r);
            while ($row = DB_fetchArray($r, false)) {
                \Classifieds\Ad::Delete($row['ad_id']);
            }

            if ($numrows > 0) {
                $msg = sprintf($LANG_ADVT['purged_ads'], $numrows) .
                    date("Y-m-d H:i:s", $purge_exp_date);
                CLASSIFIEDS_auditLog($msg);
                if ($standalone)
                    echo $msg;
            }
        }
    }

    // Clean up orphaned image records.
    Classifieds\Image::cleanOrphans();

    // Notify users of impending ad expirations
    if ((int)$_CONF_ADVT['exp_notify_days'] >= 0) {
        Classifieds\Notify::Expiration();
    }
}


/**
 * Action to take when a new user is created.
 * Creates a record in the "userinfo" table for the new user.
 *
 * @param   integer $uid    User ID
 */
function plugin_user_create_classifieds($uid)
{
    $U = new Classifieds\UserInfo($uid);
    $U->Save();
}


/**
 * Support merging local accounts into remote accounts.
 *
 * @param   integer $origUID    Original (remote) user ID
 * @param   integer $destUID    Merged (local) user ID
 */
function plugin_user_move_classifieds($origUID, $destUID)
{
    global $_TABLES;

    $origUID = (int)$origUID;
    $destUID = (int)$destUID;

    DB_query("UPDATE {$_TABLES['ad_ads']}
            SET uid = $destUID WHERE uid = $origUID", 1);
    DB_query("UPDATE {$_TABLES['ad_submission']}
            SET uid = $destUID WHERE uid = $origUID", 1);
    DB_query("UPDATE {$_TABLES['ad_uinfo']}
            SET uid = $destUID WHERE uid = $origUID", 1);
}


/**
 * Display a random ad in a block.
 *
 * @return  string  HTML code for the block
 */
function phpblock_classifieds_random()
{
    global $_TABLES, $LANG_ADVT, $_CONF_ADVT;

    $retval = '';
    if ($_CONF_ADVT['loginrequired'] == 1 && COM_isAnonUser()) {
        return $retval;
    }
    $Ad = Classifieds\Ad::getRandom();
    if ($Ad->isNew()) {
        return $retval;
    }

    $img_filename = Classifieds\Image::getFirst($Ad->getID());
    $T = new Template($_CONF_ADVT['path'] . '/templates');
    $T->set_file('rblock', 'randomblock.thtml');
    $T->set_var(array(
        'ad_url'    => CLASSIFIEDS_makeURL('detail', $Ad->getID()),
        'ad_subject' => $Ad->getSubject(),
        'img_url'   => \Classifieds\Image::thumbUrl($img_filename),
    ) );
    $T->parse('output','rblock');
    return $T->finish($T->get_var('output'));
}


/**
 * Return the template names that support ad blocks.
 *
 * @return  array   Array of template names
 */
function plugin_supportadblock_classifieds()
{
    return array('classifieds_detail');
}


/**
 * Shortcut function to create a URL using COM_buildUrl.
 * This ensures that the url is properly formatted with at least a
 * 'mode' value, and arguments are in the right order.  This is important
 * when url_rewrite is on.
 *
 * @param   string  $mode   Mode (action) parameter
 * @param   string  $id     Ad or Category ID (optional)
 * @param   string  $other  Other miscellaneous options
 * @return  string  Complete url string
 */
function CLASSIFIEDS_makeURL($mode='home', $id='', $other = '')
{
    global $_CONF_ADVT;
    $url = $_CONF_ADVT['url'] . '/index.php?'
        . "mode=$mode";
    if (empty($id) && !empty($other))
        $id = '0';

    if (!empty($id))
        $url .= "&amp;id=$id";

    if ($other != '')
        $url .= "&amp;$other";

    $url = COM_buildUrl($url);

    return $url;
}


/**
 * Select default group for ad access (viewing and editing).
 *
 * @return  array   Array of group names, not including All Users
 */
function plugin_configmanager_select_defgrpad_classifieds()
{
    return CLASSIFIEDS_getGroups('\'All Users\'', 'NOT');
}


/**
 * Select default group for category access (submission and viewing).
 *
 * @return  array   Array of group names, not including All Users
 */
function plugin_configmanager_select_defgrpcat_classifieds()
{
    return CLASSIFIEDS_getGroups('\'All Users\'', 'NOT');
}


/**
 * Select the template to use for product detail pages, by default.
 *
 * @return  array   $option => path
 */
function plugin_configmanager_select_detail_tpl_ver_classifieds()
{
    global $_CONF_ADVT;
    $A = array();
    $dirs = glob($_CONF_ADVT['path'] . '/templates/detail/*', GLOB_ONLYDIR);
    foreach ($dirs as $dir) {
        $opt = explode('/', $dir);
        $opt = end($opt);
        $A[$opt] = $opt;
    }
    return $A;
}


/**
 * Gets all the groups in the glFusion groups table to prepare a selection list.
 * Helper function for config element selections.
 *
 * @param   string  $grplist    Optional list of groups to include or exclude
 * @param   string  $NOT        Set to "NOT" to exclude $grplist, blank to include it.
 * @return  array       Array of (grp_name=>grp_id)
 */
function CLASSIFIEDS_getGroups($grplist='', $NOT='')
{
    global $_TABLES;

    $query = "SELECT grp_id, grp_name FROM {$_TABLES['groups']}";

    // If a group list is specified, add it as a condition with the
    // specified keyword.
    if ($grplist!= '') {
        $query .= " WHERE grp_name $NOT IN ($grplist)";
    }

    $cache_key = md5($query);
    $A = \Classifieds\Cache::get($cache_key);
    if ($A === NULL) {
        $A = array();
        $result = DB_query($query);
        if ($result && DB_numRows($result) > 0) {
            while ($row = DB_fetchArray($result)) {
                $A[$row['grp_name']] = $row['grp_id'];
            }
        }
        \Classifieds\Cache::set($cache_key, $A, 'groups');
    }
    return $A;
}


/**
 * Check whether a user is authorized to submit ads.
 * Intended to be called before displaying forms or saving submissions.
 *
 * @return  boolean True if user can submit ads, False otherwise
 */
function CLASSIFIEDS_canSubmit()
{
    global $_CONF, $_CONF_ADVT, $_GROUPS;
    static $canSubmit = NULL;

    if ($canSubmit === NULL) {
        $canSubmit = COM_isAnonUser() ? false : true;
    }
    return $canSubmit;
}


/**
 * Show the contest submission form in the centerblock.
 * This shows the submission form in place of the home page for the
 * designated user group.
 *
 * @param   integer $where Where to show the block (only after featured story)
 * @param   integer $page Which page (Home page only)
 * @param   string  $topic Which topics to show with (All)
 * @return  string          HTML code for submission form
 */
function plugin_centerblock_classifieds($where = 1, $page = 1, $topic = '')
{
    global $_CONF, $_USER, $_TABLES, $_CONF_ADVT;

    // we only show it after featured stories, not replacing the home page
    if ($where != 1)
        return '';

    $cb_enable = $_CONF_ADVT['centerblock'];
    if ($page > 1 OR !empty($topic)) {
        $cb_enable = 0;
    }

    $display = '';

    // if the centerblock value is greater than or equal to the
    // user ID, replace the homepage
    if ($cb_enable) {
        echo COM_Refresh($_CONF_ADVT['url'] . '/index.php');
        exit;
    }
}


/**
 * Create an edit form in the Account Settings section.
 *
 * @since   glFusion 1.1.5
 * @param   integer $uid        User ID
 * @param   string  $panel      Panel being populated
 * @param   string  $fieldset   Name of fieldset being populated
 * @return  string              HTML for edit form.  Never NULL.
 */
function plugin_profileedit_classifieds($uid, $panel, $fieldset)
{
    global $_CONF, $_USER;

    $retval = '';

    if ($panel == '' && $fieldset == '') {
        $U = new \Classifieds\UserInfo($uid);
        $retval = $U->ShowForm('account_settings');
    }

    return $retval;
}


/**
 * Save the classifieds ad user information when edited via Account Settings.
 *
 * @since   glFusion 1.1.5
 * @param   integer $uid    ID of user being saved
 */
function plugin_profilesave_classifieds($uid=0)
{
    global $_USER;

    if ($uid == 0) {
        $uid = (int)$_USER['uid'];
    }

    if (COM_isAnonUser()) {
        return;
    }

    $U = new \Classifieds\UserInfo($uid);
    $U->SetVars($_POST);
    $U->Save();
}


/**
 * Update classifieds info when a user account is modified.
 *
 * @since   v1.1.0
 * @param   integer $uid    ID of user being changed
 */
function plugin_userchanged_classifieds($uid=0)
{
    if ($uid == 0) {
        $uid = (int)$_USER['uid'];
    }

    if (COM_isAnonUser()) {
        return;
    }

    $U = new \Classifieds\UserInfo($uid);
    $U->SetVars($_POST);
    $U->Save();
}


/**
 * Remove Classifieds user profile info when an account is deleted.
 *
 * @param   integer $uid    User ID that is being deleted
 */
function plugin_user_delete_classifieds($uid)
{
    global $_TABLES;

    \Classifieds\UserInfo::Delete($uid);

    // Delete all current ads for the user
    $sql = "SELECT ad_id FROM {$_TABLES['ad_ads']} WHERE uid = '$uid'";
    $res = DB_query($sql);
    while ($A = DB_fetchArray($res, false)) {
        \Classifieds\Ad::Delete($A['ad_id']);
    }

    // Now delete all submissions for the user
    $sql = "SELECT ad_id FROM {$_TABLES['ad_submission']} WHERE uid = '$uid'";
    $res = DB_query($sql);
    while ($A = DB_fetchArray($res, false)) {
        \Classifieds\Ad::Delete($A['ad_id'], 'ad_submission');
    }
}


/**
 * Get the URL to the localized configuration help file.
 *
 * @param   string  $option     Name of config option selected
 * @param   string  $doclang    Document language desired.
 * @return  array   Array of (url, window_type)
 */
function plugin_getconfigelementhelp_classifieds($option, $doclang='english')
{
    $doc_url = CLASSIFIEDS_getDocURL('config', $doclang);
    if (empty($doc_url))
        return false;

    if (!empty($option)) {
        $doc_url .= '#desc_' . $option;
    }
    return array($doc_url, 0);
}


/**
 * Disable this plugin's blocks when the plugin is disabled.
 *
 * @param   boolean $enabled    Plugin's "enabled" state
 */
function plugin_enablestatechange_classifieds($enabled)
{
    global $_TABLES, $_PLUGINS;

    $enabled = $enabled == true ? 1 : 0;

    // If trying to enable the plugin but lgLib is not available,
    // take no action and return
    if ($enabled && !in_array('lglib', $_PLUGINS)) {
        // Re-disable the plugin
        DB_change($_TABLES['plugins'], 'pi_enabled', 0, 'pi_name', 'classifieds');
        $enabled = 0;
    }

    if (!$enabled) {
        $blocks = array('classifieds_random');
        foreach ($blocks as $block) {
            DB_change($_TABLES['blocks'], 'is_enabled', $enabled, 'name', $block);
        }
    }
}


/**
 * Receives notification of another plugin's deletion.
 *
 * @param   mixed   $id     Item ID
 * @param   string  $type   Plugin Name
 */
function plugin_itemdeleted_classifieds($id, $type)
{
    global $_TABLES;

    // If the lgLib plugin is disabled or removed, disable this plugin
    if ($type == 'lglib' && $id == '*') {
        plugin_enablestatechange_classifieds(false);
        DB_change($_TABLES['plugins'], 'pi_enabled', 0, 'pi_name', 'classifieds');
    }
}


/**
 * Handle the plugin's autotags.
 *
 * @param   string  $op         Operation to perform
 * @param   string  $content    Page content
 * @param   array   $autotag    Autotag elements
 * @return  string      Updated page content
 */
function plugin_autotags_classifieds($op, $content = '', $autotag = '')
{
    global $_CONF_ADVT, $LANG_ADVT, $_TABLES;

    if ($op == 'tagname') {
        return 'classifieds';
    } else if ($op != 'parse') {
        return '';
    }

    // Process the auto tag to remove any embedded &nbsp;
    $tag = str_replace('&nbsp;', ' ', $autotag['tagstr']);
    $parms = explode(' ', $tag);

    // Extra test to see if autotag was entered with a space
    // after the module name
    if (substr($parms[0], -1) == ':') {
        $startpos = strlen($parms[0]) + strlen($parms[1]) + 2;
        $label = str_replace(']', '', substr($tag, $startpos));
        $tagid = $parms[1];
    } else {
        $label = str_replace(']', '', substr($tag, strlen($parms[0]) + 1));
        $parms = explode(':', $parms[0]);
        if (count($parms) > 2) {
            // whoops, there was a ':' in the tag id ...
            array_shift($parms);
            $tagid = implode(':', $parms);
        } else {
            $tagid = $parms[1];
        }
    }
    $autotag['parm1'] = str_replace(']','',$tagid);
    $autotag['parm2'] = $label;

    $T = new Template($_CONF_ADVT['path'] . '/templates');

    // Set default values for what gets shown and how
    $ad_id = '';
    $template = 'autotag.thtml';
    $limit = 1;
    $textlen = 65535;

    $px = explode(' ', trim($autotag['parm2']));
    if (is_array($px)) {
        foreach ($px as $part) {
            if (strstr($part, ':') !== false) {
                $a = explode(':', $part);
                switch ($a[0]) {
                case 'id':          // specify the ad to show
                    $ad_id = $a[1];
                    break;
                case 'template':    // specify the template
                    $template = $a[1];
                    break;
                case 'limit':       // limit the number of returns
                    $limit = (int)$a[1];
                    break;
                case 'textlen':     // limit the amount of description shown
                    $textlen = (int)$a[1];
                    break;
                }
            }
        }
    }
    $where = ' 1=1 ';
    $order = '';
    $limit = "LIMIT $limit";

    switch ($autotag['parm1']) {
    case 'ad':      // Show an ad
        if (empty($ad_id)) return $content;   // must have a valid ad ID
        $where = " ad_id = '" . DB_escapeString($ad_id) . "' ";
        break;
    case 'recent':  // Show one or more recent ads
        $order = ' ORDER BY ad.add_date DESC ';
        break;
    }

    //$sql = "SELECT ad.ad_id, ad.uid, c.cat_id
    $sql = "SELECT ad.*, c.description as type
            FROM {$_TABLES['ad_ads']} ad
            LEFT JOIN {$_TABLES['ad_category']} c
                ON ad.cat_id = c.cat_id
            WHERE $where " . COM_getPermSQL('AND', 0, 2, 'c') .
            " $order $limit";
    $result = DB_query($sql, 1);
    // In case of an error, log it and return unchanged content
    if (DB_error()) {
        COM_errorLog("plugin_autotags_classifieds SQL error: $sql");
        return $content;
    }
    // No results found is a normal condition
    if (DB_numRows($result) > 0) {

        $T->set_file('autotag', $template);
        $T->set_block('autotag', 'tag_data', 'TAG');
        while ($A = DB_fetchArray($result, false)) {
            $img_file = DB_getItem($_TABLES['ad_photo'], 'filename',
                    "ad_id='{$A['ad_id']}'");
             if ($img_file != '') {
                $T->set_var('img_url', \Classifieds\Image::dispUrl($img_file));
                $T->set_var('tn_url', \Classifieds\Image::thumbUrl($img_file));
            } else {
                $T->clear_var('img_url');
                $T->clear_var('tn_url');
            }
            if (isset($A['description'])) {
                if (strlen($A['description']) > $textlen) {
                    $A['description'] = substr($A['description'], 0, $textlen - 3) . ' ...';
                }
            }
            $T->set_var(array(
                'ad_id'     => $A['ad_id'],
                'cat_id'    => $A['cat_id'],
                'uid'       => $A['uid'],
                'subject'   => htmlspecialchars($A['subject']),
                'description'  => htmlspecialchars($A['description']),
                'url'       => COM_sanitizeURL($A['url']),
                'add_date'  => COM_getUserDateTimeFormat($A['add_date']),
                'exp_date'  => COM_getUserDateTimeFormat($A['exp_date']),
                'ad_type'   => htmlspecialchars($A['type']),
                'pi_url'    => $_CONF_ADVT['url'],
                'ad_url'    => COM_buildUrl($_CONF_ADVT['url']
                        . '/index.php?mode=detail&id=' . urlencode($ad_id)),
            ) );
            $T->parse('TAG', 'tag_data', true);
        }
        $T->parse('output', 'autotag');
        $tag_data = $T->finish($T->get_var('output'));
    } else {
        $tag_data = '';
    }
    $content = str_replace($autotag['tagstr'], $tag_data, $content);
    return $content;
}


/**
 * Export user data when requested.
 *
 * @param   integer $uid        User ID
 * @param   string  $email      User Email Address (not used)
 * @param   string  $username   User Name (not used)
 * @param   string  $ip         IP address (not used)
 * @return  string              XML-formatted data
 */
function plugin_privacy_export_classifieds($uid,$email='',$username='',$ip='')
{
    $U = new \Classifieds\UserInfo($uid);
    $retval = "<classifieds>\n";
    $retval .= $U->Export();
    $retval .= "</classifieds>\n";

    if (function_exists('tidy_repair_string')) {
        $retval = tidy_repair_string($retval, array('input-xml' => 1));
    }
    return $retval;
}


/**
 * Get the link to a documentation file.
 * Returns the URL to the file, or to the directory if no file requested.
 *
 * @see     plugin_getconfigelementhelp_classifieds()
 * @param   string  $file       HTML file
 * @param   string  $doclang    Documentation language
 * @return  string              URL to document directory or file
 */
function CLASSIFIEDS_getDocURL($file='', $doclang='english')
{
    global $_CONF, $_CONF_ADVT;

    $doc_path = $_CONF['path_html'] . $_CONF_ADVT['pi_name'] .
                "/docs/$doclang/";
    $doc_url = '';

    if ($file != '') {
        $file .= '.html';
        if (!file_exists($doc_path . $file)) {
            $doclang = 'english';
        }
        $doc_url = $_CONF_ADVT['url'] . "/docs/$doclang/$file";
         if (!is_file($doc_path . $file)) {
            $doclang = 'english';
        }
        $doc_url = $_CONF_ADVT['url'] . "/docs/$doclang/$file";
    } else {
        if (!is_dir($doc_path)) {
            $doclang = 'english';
        }
        $doc_url = $_CONF_ADVT['url'] . "/docs/$doclang/";
    }

    return $doc_url;
}


/**
 * Get a form or URL parameter.
 * Checks $_POST, then $_GET, and returns the raw value if found.
 * Returns NULL if the parameter is not set.
 * If `array` is specified and the parameter is a string, then it is
 * assumed to be a comma-separated set of values.
 *
 * @param   string  $name   Name of parameter
 * @param   string  $type   Type of parameter, default "string"
 * @return  mixed       Value of parameter, or NULL if not found
 */
function CLASSIFIEDS_getParam($name, $type = 'string')
{
    if (isset($_POST[$name])) {
        $value = $_POST[$name];
    } elseif (isset($_GET[$name])) {
        $value = $_GET[$name];
    } else {
        return NULL;
    }

    switch($type) {
    case 'int':
        return (int)$value;
        break;
    case 'array':
        if (is_string($value)) {
            $value = explode(',', $value);
        } elseif (is_array($value)) {
            $vals = array();
            foreach($value as $val) {
                if (strpos($val, ',') > 0) {
                    $vals = array_merge($vals, explode(',', $val));
                } else {
                    $vals[] = $val;
                }
            }
            $value = array_unique($vals);
        }
    default:
        return $value;
        break;
    }
}


/**
 * Returns an error message formatted for on-screen display.
 *
 * @param   string|array  $messages Error message(s)
 * @param   string  $type           Error type or severity
 * @param   string  $hdr            Optional text to appear in the header.
 * @return  string          HTML code for the formatted message
 */
function CLASSIFIEDS_errorMsg($messages, $type='info', $hdr='Error')
{
    global $_CONF_ADVT;

    // Convert single message to array
    if (!is_array($messages)) {
        $messages = array($messages);
    }
    $msg_txt = '<ul>';
    foreach ($messages as $msg) {
        $msg_txt .= "<li>$msg</li>" . LB;
    }
    $msg_txt .= '</ul>';

    $element = 'div';
    switch ($type) {
    case 'alert':
    default:
            $class .= 'uk-alert uk-alert-danger';
            break;
    case 'info':
            $class .= 'uk-alert';
            break;
    }
    return "<div class=\"$class\">$msg_txt</div>";
}


/**
 * Log activity to a plugin-specific log file.
 *
 * @param   string  $logentry   Text to log
 */
function CLASSIFIEDS_auditLog($logentry = '')
{
    global $_CONF, $_USER, $LANG01, $_CONF_ADVT;

    if ($logentry == '')
        return '';

    // A little sanitizing
    $logentry = str_replace(
            array('<?', '?>'),
            array('(@', '@)'),
            $logentry);

    $timestamp = strftime( '%c' );
    $logfile = $_CONF['path_log'] . $_CONF_ADVT['pi_name'] . '.log';

    // Can't open the log file?  Return an error
    if (!$file = fopen($logfile, 'a')) {
        return $LANG01[33] . $logfile . ' (' . $timestamp . ')<br />' . LB;
    }

    // Get the user name if it's not anonymous
    if (!COM_isAnonUser()) {
        $byuser = $_USER['uid'] . '-'.
                COM_getDisplayName($_USER['uid'],
                    $_USER['username'], $_USER['fullname']);
    } else {
        $byuser = 'anon';
    }
    $byuser .= '@' . $_SERVER['REMOTE_ADDR'];

    // Write the log entry to the file
    fputs($file, "$timestamp ($byuser) - $logentry\n");
}

?>
