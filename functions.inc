<?php
/**
*   Common functions for the Classifieds plugin
*
*   @author     Lee Garner <lee@leegarner.com>
*   @copyright  Copyright (c) 2009-2017 Lee Garner <lee@leegarner.com>
*   @package    classifieds
*   @version    1.1.3
*   @license    http://opensource.org/licenses/gpl-2.0.php
*               GNU Public License v2 or later
*   @filesource
*/

if (!defined('GVERSION')) {
    die('This file can not be used on its own.');
}

/**
*   Plugin-specific configuration items
*   @global array $_CONF_ADVT
*/
global $_CONF_ADVT;

//  Check and see if we need to load the plugin configuration
if (!isset($_CONF_ADVT['imagecount'])) {
    /** Include glFusion configuration class if needed */
    require_once $_CONF['path_system'] . 'classes/config.class.php';

    $classifieds_config = config::get_instance();
    $_CONF_ADVT = $classifieds_config->get_config('classifieds');
}

/** Additional config items and table definitions are added here */
require_once dirname(__FILE__) . '/classifieds.php';

if (file_exists(dirname(__FILE__) . '/config.php')) {
    /** Include local configuration overrides */
    include_once dirname(__FILE__) . '/config.php';
}
// New feature not fully implemented, different template options
if (!isset($_CONF_ADVT['detail_tpl_ver'])) $_CONF_ADVT['detail_tpl_ver'] = '/v1';

// End of loading configurations.  Now, if login is required globally,
// set our configuration accordingly
if ($_CONF['loginrequired'])
    $_CONF_ADVT['loginrequired'] = 1;

// Feature under development, forcefully disable from release
$_CONF_ADVT['purchase_enabled'] = 0;

// Mark whether this is a uikit theme. Save to global conf var
// so it's available where needed.
$_CONF_ADVT['_is_uikit'] = $_SYSTEM['framework'] == 'uikit' ? true : false;

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


function USES_classifieds_class_ad()
{
    global $_CONF_ADVT;
    require_once $_CONF_ADVT['classpath'] . '/Ad.class.php';
}

function USES_classifieds_class_adtype()
{
    global $_CONF_ADVT;
    require_once $_CONF_ADVT['classpath'] . 'AdType.class.php';
}

function USES_classifieds_class_upload()
{
    global $_CONF_ADVT;
    require_once $_CONF_ADVT['classpath'] . '/upload.class.php'; }

function USES_classifieds_class_image()
{
    global $_CONF_ADVT;
    require_once $_CONF_ADVT['classpath'] . '/image.class.php';
}

function USES_classifieds_class_userinfo()
{
    global $_CONF_ADVT;
    require_once $_CONF_ADVT['classpath'] . '/UserInfo.class.php';
}

function USES_classifieds_class_adlist()
{
    global $_CONF_ADVT;
    require_once $_CONF_ADVT['classpath'] . '/AdList.class.php';
}

function USES_classifieds_admin()
{
    global $_CONF_ADVT;
    require_once $_CONF_ADVT['path'] . '/admin.php';
}

function USES_classifieds_notify()
{
    global $_CONF_ADVT;
    require_once $_CONF_ADVT['classpath'] . '/notify.php';
}

function USES_classifieds_class_catlist()
{
    global $_CONF_ADVT;
    require_once $_CONF_ADVT['classpath'] . '/CatList.class.php';
}

function USES_classifieds_detail()
{
    global $_CONF_ADVT;
    require_once $_CONF_ADVT['path'] . '/detail.php';
}

function USES_classifieds_class_category()
{
    global $_CONF_ADVT;
   include_once $_CONF_ADVT['classpath'] . '/category.class.php';
}

function USES_classifieds_class_notify()
{
    global $_CONF_ADVT;
    include_once $_CONF_ADVT['classpath'] . '/notify.class.php';
}


/**
*   Returns the items for this plugin that should appear on the main menu
*
*   NOTE: this MUST return the url/value pairs in the following format
*      $<arrayname>[<label>] = <url>
*
*   @return    array   Associative array of menu items to be added to menu
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
*   Check to see if this Plugin uses comments
*
*   @return   boolean true or false
*/
function plugin_commentsupport_classifieds()
{
    global $_CONF_ADVT;
    return $_CONF_ADVT['commentsupport'] == 0 ? false : true;
}


/**
*   Save a comment.
*   Saves the comment, updates the comment counter in the ad record,
*   and notifies the ad owner that a new comment has been posted.
*
*   @param   string  $title  comment title
*   @param   string  $comment comment text
*   @param   string  $id     Item id to which $cid belongs
*   @param   int     $pid    comment parent
*   @param   string  $postmode 'html' or 'text'
*   @return  mixed   false for failure, HTML string (redirect?) for success
*/
function plugin_savecomment_classifieds($title,$comment,$id,$pid,$postmode)
{
    global $_CONF, $LANG03, $_TABLES, $LANG_ADVT, $_USER;

    // Without comment support, we shouldn't get here unless something
    // nefarious is going on, but just in case...
    if (!plugin_commentsupport_classifieds())
        return;

    USES_classifieds_class_ad();
    USES_classifieds_class_notify();

    $title = strip_tags($title);
    $pid = COM_applyFilter($pid, true);
    $postmode = COM_applyFilter($postmode);

    $ret = CMT_saveComment($title, $comment, $id, $pid,
            'classifieds', $postmode);

    if ($ret > 0) {         // failure, return to comment form
        return CLASSIFIEDS_siteHeader()
            . CMT_commentform($title, $comment, $id, $pid,
                    'classifieds', $LANG03[14], $postmode)
            . CLASSIFIEDS_siteFooter();
    }

    // success - update the comment counter for this ad
    $Ad = new Ad($id);
    $Ad->updateComments();

    // Notify the ad's owner that a comment has been posted.
    adNotify::Comment($Ad);

    return (COM_refresh(CLASSIFIEDS_makeURL('detail', $id)));
}


/**
*   How the plugin will display specific comments
*
*   @param   string  $id     Unique idenifier for item comment belongs to
*   @param   int     $cid    Comment id to display
*   @param   string  $title  Page/comment title
*   @param   string  $order  'ASC' or 'DSC' or blank
*   @param   string  $format 'threaded', 'nested', or 'flat'
*   @param   int     $page   Page number of comments to display
*   @param   boolean $view   True to view comment (by cid), false to display (by $pid)
*   @return  mixed   results of calling the plugin_displaycomment_ function
*/
function plugin_displaycomment_classifieds($id, $cid, $title, $order, $format, $page, $view)
{
    global $LANG_ACCESS, $_USER;

    $retval = '';

    // Without comment support, we shouldn't get here unless something
    // nefarious is going on, but just in case...
    if (!plugin_commentsupport_classifieds())
        return $retval;

    USES_classifieds_class_ad();
    $Ad = new Ad($id);

    if (!$Ad->isNew) {
        $delete_option = (plugin_ismoderator_classifieds() ||
                $Ad->uid = $_USER['uid']);
        $retval .= CMT_userComments($id, $title, 'classifieds', $order, $format,
                                     $cid, $page, $view, $delete_option,
                                     plugin_commentsupport_classifieds());
    } else {
        $retval .= COM_startBlock($LANG_ACCESS['accessdenied'], '',
                        COM_getBlockTemplate ('_msg_block', 'header'))
                . $LANG_ACCESS['storydenialmsg']
                . COM_endBlock(COM_getBlockTemplate ('_msg_block', 'footer'));
    }
    return $retval;
}


/**
*   Delete a single comment.
*
*   @since  version 1.0.0
*   @param  int     $cid    Comment to be deleted
*   @param  string  $id     Item id to which $cid belongs
*   @return mixed   false for failure, HTML string (redirect?) for success
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
*   Handle any post-processing for an edited comment.
*   Just redirects back to the ad detail page.  The comment is actually
*   saved in comment.php.
*
*   @param  integer $cid    Comment ID
*   @param  mixed   $id     Item ID
*/
function plugin_editcomment_classifieds($cid, $id)
{
    echo COM_refresh(CLASSIFIEDS_makeURL('detail', $id));
    exit;
}


/**
*   Shows the statistics for the plugin on stats.php.  If
*   $showsitestats is 1 then we are to only print the overall stats in the 'site
*   statistics' box otherwise we show the detailed stats for the plugin
*
*   @param    int showsitestate   Flag to let us know which stats to get
*   @return   string  returns formatted HTML to insert in stats page
*/
function plugin_showstats_classifieds($showsitestats)
{
    return '';
}

/**
*   This will put an option for the plugin in the command and control
*   block on moderation.php.
*   Add the plugin name, icon and link to the command and control block
*   in moderation.php
*
*   @return   array   Array containing (plugin name, admin url, url of plugin icon)
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
*   Returns the URL of the plugin's icon.
*
*   @return   string      URL of the icon
*/
function plugin_geticon_classifieds()
{
    global $_CONF_ADVT;
    return $_CONF_ADVT['url'] . '/images/' . $_CONF_ADVT['pi_name'] . '.png';
}


/**
*   Returns the administrative option for this plugin
*   Adds the plugin to the Admin menu
*
*   @return   array   Array containing (plugin name, plugin admin url, # of items in plugin or '')
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
*   Returns the user option for this plugin
*   Adds the plugin to the user menu
*
*   @return   array   Array containing (plugin name, plugin user url, 0)
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
*   Automatically uninstall the plugin
*
*   @return array Array of plugin elements to remove
*/
function plugin_autouninstall_classifieds()
{
    global $_CONF_ADVT;

    $out = array (
        'tables'    => array('ad_ads',
                            'ad_category',
                            'ad_notice',
                            'ad_photo',
                            'ad_uinfo',
                            'ad_catflds',
                            'ad_submission',
                            'ad_types',
                        ),
        'groups'    => array($_CONF_ADVT['pi_name'] . ' Admin'),
        'features'  => array($_CONF_ADVT['pi_name'] . '.admin',
                            $_CONF_ADVT['pi_name'] . '.edit',
                            $_CONF_ADVT['pi_name'] . '.submit',
                        ),
        'php_blocks' => array('phpblock_' . $_CONF_ADVT['pi_name'] . '_random'),
        'vars'      => array($_CONF_ADVT['pi_name'] . '_gid')
    );

    return $out;
}


/**
*   Returns the block title for the What's New block, if supported.
*
*   @return mixed   Array of title info, false if unsupported or nothing to show
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
*   Get the new ads to display in the What's New block.
*
*   @param  boolean $countonly  True to just count, false to get the list
*   @return string  Formatted list of items & urls
*/
function plugin_getwhatsnew_classifieds($countonly = false)
{
    global $_CONF, $_TABLES, $_USER, $_CONF_ADVT, $LANG_ADVT;

    $time = time();
    $retval = '';
    static $result = NULL;
    static $nrows = 0;

    if ($result === NULL) {
        // Get newest ads
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
    }

    if ($countonly) {
        // Just getting the count for the whatsnew_supported function
        return $nrows;
    }

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

        $retval .= COM_makeList($newads, 'list-new-ads');
    } else {
        $retval .= $LANG_ADVT['no_new_ads'] . '<br />' . LB;
    }

    return $retval;
}


/**
*   Get the search types for the site search.
*
*   @return mixed   Array of typeID=>typeName, or empty string for none.
*/
function plugin_searchtypes_classifieds()
{
    global $_CONF, $_CONF_ADVT, $LANG_ADVT;

    if (COM_isAnonUser() &&
        (($_CONF['loginrequired'] == 1) || ($_CONF_ADVT['loginrequired'] == 1))
    ) {
        return '';
    }

    $tmp = array('classifieds' => $LANG_ADVT['classifieds']);

    return $tmp;
}


/**
*   This searches for ads matching the user query and returns an array for the
*   header and table rows back to search.php where it will be formated
*   and printed
*
*   @param    string  $query      Keywords user is looking for
*   @param    date    $datestart  Start date to get results for
*   @param    date    $dateend    End date to get results for
*   @param    string  $topic      The topic they were searching in
*   @param    string  $type       Type of items they are searching, or 'all'
*   @param    int     $author     Get all results by this author
*   @param    string  $keyType    search key type: 'all', 'phrase', 'any'
*   @param    int     $page       page number of current search
*   @param    int     $perpage    number of results per page
*   @return   object              search result object
*
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
*   Returns additional code to be placed in the HTML header.
*
*   @return string Additional Header Code
*/
function plugin_getheadercode_classifieds()
{
    global $_CONF, $_CONF_ADVT, $_TABLES;

    $retval = '';       // Return value
    $type = '';         // Type of item being displayed (detail only for now)

    // Check the current URL for content that we should modify
    $uri = strtolower(COM_getCurrentURL());

    if ($_CONF['url_rewrite']) {
        if (preg_match("/classifieds\/index\.php\/detail\/(.*)/", $uri, $match)) {
            $type = 'detail';
        }
    } else {
        if (preg_match("/classifieds\/index\.php\?mode=detail&id=(.*)/", $uri, $match)) {
            $type = 'detail';
        }
    }

    if (isset($match[1])) {
        $id = COM_applyFilter($match[1]);
        if ($id == '') {
            $type= '';
        }
    } else {
        return;
    }

    // Retrieve the relevant content from DB
    $target = '';

    switch ($type) {
    case 'detail':
        $exp_date = DB_getItem($_TABLES['ad_ads'], 'exp_date',
                "ad_id = '" . DB_escapeString($id) . "'");
        if (!empty($exp_date)) {
            $retval .= '<meta http-equiv="expires" content="' .
                    gmdate('D, d M Y H:i:s T', $exp_date) . '">' . LB;
        }
        break;
    }

    return $retval;
}


/**
*   Returns additional CSS files to be imported.
*   Checks first for a layout-specific version, then loads the default if none.
*
*   @return array   Array of filenames
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
*   Get additional Javascript files to import in the header.
*
*   @return array Array of filenames to import
*/
function plugin_getheaderjs_classifieds()
{
    global $_CONF, $_CONF_ADVT;

    $js_dir = $_CONF_ADVT['path'] . '/js/';

    $files = array(
        "$js_dir/catfldsxml.js",
        "$js_dir/picker.js",
        "$js_dir/toggleEnabled.js",
        "$js_dir/catsub.js",
        "$js_dir/moredays.js",
    );
    return $files;
}


/**
*   Selection dropdown to pick groups in the configuration manager.
*
*   @return Array Associative array of grp_name=>grp_id
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
*   Upgrade the plugin.
*
*   @return boolean True on success, code for error message otherwise
*/
function plugin_upgrade_classifieds()
{
    require_once dirname(__FILE__) . '/upgrade.inc.php';
    return classifieds_do_upgrade();
}


/**
*   Returns list of moderation values
*
*   The array returned contains (in order): the row 'id' label, main plugin
*   table, moderation fields (comma seperated), and plugin submission table
*
*   @return       array        Returns array of useful moderation values
*
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
*   Returns the current user's ability to moderate the plugin
*
*   Checks that the current user has the rights to moderate the
*   plugin, returns true if this is the case, false otherwise
*
*   @return        boolean       Returns true if moderator
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
*   Show items for moderation on submissions page
*
*   Uses the Plugin class to return data required by moderation.php to list
*   plugin objects that need to be moderated.
*
*   @return       Plugin       return Plugin class containing submission data
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
        $plugin->addSubmissionHeading($LANG_ADVT['date']);
        $plugin->addSubmissionHeading($LANG_ADVT['price']);

        return $plugin;
    }
}


/**
*   Performs plugin exclusive work for items approved by moderation.
*   Notifies users of approval/denial, and subscribers if approved.
*
*   @param  string  $id         ID of the ad
*   @param  integer $owner_id   Optional ad owner ID
*   @return string              Empty string.
*/
function plugin_moderationapprove_classifieds($id, $owner_id=0)
{
    // Notify subscribers.
    USES_classifieds_class_notify();
    USES_classifieds_class_ad();
    $Ad = new Ad($id);
    adNotify::Approval($Ad, true);
    adNotify::Subscribers($Ad);
    // Update the date added to now.
    Ad::setAddDate($id);
    return '';
}


/**
*   Performs plugin exclusive work for items deleted by moderation.
*   The ad submission record is already deleted.
*   Delete uploaded images, notify submitter of denial.
*   Can't notify the submitter since the data has already been deleted.
*
*   @param  string  $id     ID of the ad deleted.
*   @return string          Any wanted HTML output
*/
function plugin_moderationdelete_classifieds($id)
{
    global $_TABLES, $_CONF_ADVT;

    USES_classifieds_class_notify();
    USES_classifieds_class_image();
    USES_classifieds_class_ad();

    $Ad = new Ad($id, 'ad_submission');

    // Return the unused days back to the user's account
    if ($_CONF_ADVT['purchase_enabled']) {
        USES_classifieds_class_userinfo();
        $User = new adUserInfo($Ad->uid);
        /*list($owner_id, $add_date, $exp_date) = DB_fetchArray(DB_query(
            "SELECT uid, add_date, exp_date
            FROM {$_TABLES['ad_submission']}
            WHERE ad_id='$id'"));*/
        $days = ($Ad->exp_date - $Ad->add_date) / 86400;
        $User->UpdateDaysBalance($days);
    }

    // Delete uploaded photos from filesystem
    adImage::DeleteAll($id);
    adNotify::Approval($Ad, false);
    return '';
}


/**
*   Returns the plugins submission form
*
*   @return       string       The HTML form
*/
function plugin_submit_classifieds()
{
    USES_classifieds_class_ad();
    $Ad = new Ad();
    return $Ad->Edit();
}


/**
*   Save a new submission
*
*   @param array $A Array of values from $_POST
*   @return string Error message, or refresh to submission page
*/
function plugin_savesubmission_classifieds($A)
{
echo 'DEPRECATED';die;
    global $_TABLES, $_USER, $_CONF, $_CONF_ADVT, $LANG12, $LANG_ADVT;

    if (!CLASSIFIEDS_canSubmit()) {
        return false;
    }

    if (empty($_USER['uid'])) {
        return false;       // Catastrophic error, just return
    } else {
        $owner_id = $_USER['uid'];
    }

    USES_classifieds_class_ad();
    $Ad = new Ad();

    // Initialize error message.  If it's still empty at the end, then
    // the save was successful.  Otherwise, redisplay the submission
    // form with the error message at the top.
    $errmsg = '';

    // check for missing fields
    if (!$Ad->isValidRecord()) {
        COM_resetSpeedlimit('submit');
        $errmsg .= "<li>". $LANG_ADVT['missing_req_fields']. "</li>\n";
    }

    // Determine which table and success message to display
    if (SEC_hasRights($_CONF_ADVT['pi_name']. '.submit') || $_CONF_ADVT['submission'] == false) {
        $Ad->setTable('ad_ads');
        $msg = '02';    // "ad has been save" message
    } else {
        $Ad->setTable('ad_submission');
        $msg = '01';    // "added to queue" message
    }

    $Ad->SetVars($A);
    var_dump($Ad);die;

    // Make sure we have permission to post in the category
    if (CLASSIFIEDS_checkCatAccess($Ad->catid) < 3) {
        $errmsg .= "<li>". $LANG_ADVT['invalid_category']. "</li>\n";
    }

    if ($errmsg == '') {        // Only save if no errors so far
        if ($_CONF_ADVT['purchase_enabled']) {
            USES_classifieds_class_userinfo();
            $User = new adUserInfo($owner_id);
            $ad_days = min((int)$Ad->moredays, $User->getMaxDays());
        } else {
            $ad_days = (int)$Ad->moredays;
        }

        if ($_POST['mode'] == $LANG12[8] ||
            $_POST['mode'] == $LANG_ADMIN['save']){     // mode = Submit
            // This is a user-submitted ad
            $A['ad_id'] = COM_makeSid();
            $A['uid'] = $owner_id;
            $A['owner_id'] = $owner_id;
            $A['group_id'] = $_CONF_ADVT['defgrpad'];
            $A['add_date'] = time();
            $A['exp_date'] = $A['add_date'] + ($ad_days * 86400);
            $A['exp_sent'] = 0;

            // Get the category permissions.  Force anon visibility to zero
            // if it's zero for the category, otherwise set to the user's
            // preference.
            /*if (DB_getItem($_TABLES['ad_category'],
                    'perm_anon', "cat_id='{$A['cat_id']}'") == '0') {
                $_POST['perm_anon'] = '0';
            }
            $A['perms'] = array(
                $_CONF_ADVT['default_permissions'][0],
                $_CONF_ADVT['default_permissions'][1],
                $_CONF_ADVT['default_permissions'][2],
                isset($_POST['perm_anon']) ? 2 : 0
            );*/
        } else {
            $A['uid'] = COM_applyFilter($_POST['uid'], true);
            $A['group_id'] = COM_applyFilter($_POST['group_id'], true);

            // Get the category permissions.  Force anon visibility to zero
            // if it's zero for the category, otherwise set to the user's
            // preference.
            /*if (DB_getItem($_TABLES['ad_category'],
                    'perm_anon', "cat_id='{$A['cat_id']}'") == '0') {
                $_POST['perm_anon'] = '0';
            }
            $A['perms'] = array(
                $_CONF_ADVT['default_permissions'][0],
                $_CONF_ADVT['default_permissions'][1],
                $_CONF_ADVT['default_permissions'][2],
                $_POST['perm_anon']        );
            */
        }

        $errmsg .= CLASSIFIEDS_UploadPhoto($A['ad_id'], 'photo');
        if ($errmsg == '') {
            if (CLASSIFIEDS_insertAd($A, $table))
                return false;       // Catastrophic failure
            else {
                // Notify admin upon new submission, depending on configuration
                if ($_CONF_ADVT['emailadmin'] == 2 ||
                        ($_CONF_ADVT['emailadmin'] == 1 &&
                         $_CONF_ADVT['submission'] == 1))
                {
                    USES_classifieds_notify();
                    CLASSIFIEDS_notifyAdmin($A);
                }
            }
        }

    }    // if $errormsg == ''

    // If still no error message, return to the home page with
    // the 'success' message.  Otherwise, redisplay the submission form
    // with the error message
    if ($errmsg == '') {
        return COM_refresh($_CONF['site_url']
                          . "/index.php?msg=$msg&amp;plugin=". $_CONF_ADVT['pi_name']);
    } else {
        $display = CLASSIFIEDS_siteHeader();
        $display .= CLASSIFIEDS_errorMsg($errmsg, 'alert');
        $A['ad_id'] = '';   // reset so it'll still look like a new entry
        USES_classifieds_submitform();
        $display .= CLASSIFIEDS_submitForm('submit', $A);
        $display .= CLASSIFIEDS_siteFooter();
        return $display;
    }
}


/**
*   Returns the number of submissions waiting moderation.
*
*   Calculates the number of submissions that are currently waiting
*   to be moderated and returns that number.
*
*   @since  version 0.1.2
*   @return int     Number of submissions awaiting moderation
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
*   Get the currently installed version
*   @return string Plugin version
*/
function plugin_chkVersion_classifieds()
{
    global $_CONF_ADVT;
    return $_CONF_ADVT['pi_version'];
}


/**
*   Perform scheduled functions, such as notifications and ad purging
*   @param boolean $standalone TRUE if this is run via a standalone script
*/
function plugin_runScheduledTask_classifieds($standalone=false)
{
    global $_TABLES, $_CONF_ADVT, $_CONF, $LANG_ADVT;

    if (!$standalone && $_CONF_ADVT['use_gl_cron'] == 0)
        return;

    // Check if purging is disabled
    if ($_CONF_ADVT['purge_days'] > 0) {
        $purge_exp_date = time() - ((int)$_CONF_ADVT['purge_days'] * 86400);
        $r = DB_query("SELECT ad_id FROM {$_TABLES['ad_ads']}
                    WHERE exp_date < $purge_exp_date");
        if ($r) {
            $numrows = DB_numRows($r);
            USES_classifieds_class_ad();
            while ($row = DB_fetchArray($r)) {
                Ad::Delete($row['ad_id']);
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

    // Notify users of impending ad expirations
    if ((int)$_CONF_ADVT['exp_notify_days'] >= 0) {
        USES_classifieds_class_notify();
        adNotify::Expiration();
    }
}


/**
*   Action to take when a new user is created.
*   Creates a record in the "userinfo" table for the new user.
*
*   @param  integer $uid    User ID
*/
function plugin_user_create_classifieds($uid)
{
    USES_classifieds_class_userinfo();
    $U = new adUserInfo($uid);
    $U->Save();
}


/**
*   Support merging local accounts into remote accounts
*
*   @param  integer $origUID    Original (remote) user ID
*   @param  integer $destUID    Merged (local) user ID
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
    DB_query("UPDATE {$_TABLES['ad_notice']}
            SET uid = $destUID WHERE uid = $origUID", 1);
    DB_query("UPDATE {$_TABLES['ad_uinfo']}
            SET uid = $destUID WHERE uid = $origUID", 1);
}


/**
*   Display a random ad in a block.
*
*   @return string HTML code for the block
*/
function phpblock_classifieds_random()
{
    global $_TABLES, $LANG_ADVT, $_CONF_ADVT;

    $retval = '';
    $now = time();

    if ($_CONF_ADVT['loginrequired'] == 1 && COM_isAnonUser())
        return $retval;

    $sql = "SELECT ad.ad_id as id, ad.subject as subject
            FROM {$_TABLES['ad_ads']} ad
            LEFT JOIN {$_TABLES['ad_category']} cat
                ON cat.cat_id = ad.cat_id
            WHERE ad.exp_date > $now "
            . COM_getPermSQL('AND', 0, 2, 'cat')
            . ' ORDER BY RAND() LIMIT 1';
    //echo $sql;
    $r = DB_query($sql);
    $n = DB_numRows($r);
    if ($n < 1)
        return '';

    USES_classifieds_class_ad();

    $id = DB_result($r, 0, 'id');
    $Ad = new Ad($id);
    if ($Ad->isNew) return '';

    USES_classifieds_class_image();
    $img_filename = adImage::getFirst($Ad->ad_id);

    $T = new Template($_CONF_ADVT['path'] . '/templates');
    $T->set_file('rblock', 'randomblock.thtml');
    $T->set_var(array(
        'ad_url'    => CLASSIFIEDS_makeURL('detail', $id),
        'ad_subject' => $Ad->subject,
        'img_url'   => adImage::thumbUrl($img_filename),
    ) );

    $T->parse('output','rblock');
    return $T->finish($T->get_var('output'));
}


/**
*   Shortcut function to create a URL using COM_buildUrl.  This
*   ensures that the url is properly formatted with at least a
*   'mode' value, and arguments are in the right order.  This is important
*   when url_rewrite is on.
*
*   @param  string  $mode   Mode (action) parameter
*   @param  string  $id     Ad or Category ID (optional)
*   @param  string  $other  Other miscellaneous options
*   @return string  Complete url string
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


// Select default group for ad access (viewing and editing)
function plugin_configmanager_select_defgrpad_classifieds()
{
    return CLASSIFIEDS_getGroups('\'All Users\'', 'NOT');
}


// Select default group for category access (submission and viewing)
function plugin_configmanager_select_defgrpcat_classifieds()
{
    return CLASSIFIEDS_getGroups('\'All Users\'', 'NOT');
}


/**
*   Select the template to use for product detail pages, by default
*
*   @return array   $option => path
*/
function plugin_configmanager_select_detail_tpl_ver_classifieds()
{
    global $_CONF_ADVT;
    $A = array();
    $dirs = glob($_CONF_ADVT['path'] . '/templates/detail/*', GLOB_ONLYDIR);
    foreach ($dirs as $dir) {
        $opt = end(explode('/', $dir));
        $A[$opt] = $opt;
    }
    return $A;
}



/**
*   Gets all the groups in the glFusion groups table to prepare a
*   selection list.
*
*   @param  string  $grplist    Optional list of groups to include or exclude
*   @param  string  $NOT        Set to "NOT" to exclude $grplist, blank to include it.
*   @return array       Array of (grp_name=>grp_id)
*/
function CLASSIFIEDS_getGroups($grplist='', $NOT='')
{
    global $_TABLES;

    $A = array();

    $query = "SELECT grp_id, grp_name FROM {$_TABLES['groups']}";

    // If a group list is specified, add it as a condition with the
    // specified keyword.
    if ($grplist!= '') {
        $query .= " WHERE grp_name $NOT IN ($grplist)";
    }

    $result = DB_query($query);
    if ($result && DB_numRows($result) > 0) {
        while ($row = DB_fetchArray($result)) {
            $A[$row['grp_name']] = $row['grp_id'];
        }
    }

    return $A;
}


/**
*   Loads the language array. If $requested is an array, the first valid
*   language file is loaded. If not, the $requested language file is loaded.
*   If $requested doesn't refer to a vailid language, then $_CONF['language']
*   is assumed.
*
*   After loading the base language file, the same filename is loaded from
*   language/custom, if available. The admin can override language strings
*   by creating a language file in that directory.
*
*   @param  mixed   $deflang    A single or array of language strings
*   @return array               $LANG_ADVT, the global language array for the plugin
*/
function Xplugin_loadlanguage_classifieds($requested='')
{
    global $_CONF, $_CONF_ADVT;

    // Set the language to the user's selected language, unless
    // otherwise specified.
    $languages = array();

    // Add the requested language, which may be an array or
    // a single item.
    if (is_array($requested)) {
        $languages = $requested;
    } elseif ($requested != '') {
        // If no language requested, load the site/user default
        $languages[] = $requested;
    }

    // Add the site language as a failsafe
    if (!in_array($_CONF['language'], $languages)) {
        $languages[] = $_CONF['language'];
    }
    // Final failsafe, include "english.php" whish is known to exist
    if (!in_array('english', $languages)) {
        $languages[] = 'english';
    }

    // Search the array for desired language files, in order.
    $langpath = $_CONF_ADVT['path'] . '/language';
    foreach ($languages as $language) {
        if (file_exists("$langpath/$language.php")) {
            include "$langpath/$language.php";
            // Include admin-supplied overrides, if any.
            if (file_exists("$langpath/custom/$language.php")) {
                include "$langpath/custom/$language.php";
            }
            break;
        }
    }

    return $LANG_ADVT;
}


/**
*   Provides a single function to check whether a user is authorized
*   to submit ads.  Intended to be called before displaying forms or
*   saving submissions.
*
*   @return boolean True if user can submit ads, False otherwise
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
 *  Show the contest submission form in the centerblock.
 *  This shows the submission form in place of the home page for the
 *  designated user group.
 *  @param integer $where Where to show the block (only after featured story)
 *  @param integer $page Which page (Home page only)
 *  @param string $topic Which topics to show with (All)
 *  @return string HTML code for submission form
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
*   Create an edit form in the Account Settings section.
*
*   @since glFusion 1.1.5
*   @param  integer $uid        User ID
*   @param  string  $panel      Panel being populated
*   @param  string  $fieldset   Name of fieldset being populated
*   @return string              HTML for edit form.  Never NULL.
*/
function plugin_profileedit_classifieds($uid, $panel, $fieldset)
{
    global $_CONF, $_USER;

    $retval = '';

    if ($panel == '' && $fieldset == '') {
        USES_classifieds_class_userinfo();
        $U = new adUserInfo($uid);
        $retval = $U->ShowForm('account_settings');
    }

    return $retval;
}


/**
*   Save the classifieds ad user information when edited via Account Settings.
*
*   @since glFusion 1.1.5
*/
function plugin_profilesave_classifieds($uid=0)
{
    if ($uid == 0) {
        $uid = (int)$_USER['uid'];
    }

    if (COM_isAnonUser()) {
        return;
    }

    USES_classifieds_class_userinfo();
    $U = new adUserInfo($uid);
    $U->SetVars($_POST);
    $U->Save();
}


/**
*   Update classifieds info when a user account is modified.
*
*   @since version 1.1.0
*/
function plugin_userchanged_classifieds($uid=0)
{
    if ($uid == 0) {
        $uid = (int)$_USER['uid'];
    }

    if (COM_isAnonUser()) {
        return;
    }

    USES_classifieds_class_userinfo();
    $U = new adUserInfo($uid);
    $U->SetVars($_POST);
    $U->Save();
}


/**
*   Remove Classifieds user profile info when an account is deleted
*
*   @param  int $uid    User ID that is being deleted
*/
function plugin_user_delete_classifieds($uid)
{
    global $_TABLES;

    $uid = (int)$uid;
    $sql = "DELETE FROM {$_TABLES['ad_uinfo']} WHERE uid = $uid";
    DB_query($sql);
}


/**
*   Get the URL to the localized configuration help file.
*
*   @param  string  $option     Name of config option selected
*   @param  string  $doclang    Document language desired.
*   @return array   Array of (url, window_type)
*/
function plugin_getconfigelementhelp_classifieds($option, $doclang='english')
{
    $doc_url = CLASSIFIEDS_getDocURL('config', $doclang);
    if (empty($doc_url))
        return false;

    if (!empty($option)) {
        $file .= '#desc_' . $option;
    }

    return array($doc_url . $file, 0);
}


/**
*   Disable this plugin's blocks when the plugin is disabled.
*
*   @param  boolean $enabled    Plugin's "enabled" state
*/
function plugin_enablestatechange_classifieds($enabled)
{
    global $_TABLES;

    $enabled = $enabled == true ? 1 : 0;
    if ($enabled) {
        $blocks = array('classifieds_random');

        foreach ($blocks as $block) {
            DB_query("UPDATE {$_TABLES['blocks']}
                    SET is_enabled=$enabled
                    WHERE name='$block'");
        }
    }
}


/**
*   Handle the plugin's autotags
*
*   @param  string  $op     Operation to perform
*   @param  string  $content    Page content
*   @param  array   $autotag    Autotag elements
*   @return string      Updated page content
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

    $sql = "SELECT ad.ad_id
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
    if (DB_numRows($result) < 1) return $content;

    $T->set_file('autotag', $template);
    $T->set_block('autotag', 'tag_data', 'TAG');
    USES_classifieds_class_image();
    while ($A = DB_fetchArray($result, false)) {
        $img_file = DB_getItem($_TABLES['ad_photo'], 'filename',
                "ad_id='{$A['ad_id']}'");
         if ($img_file != '') {
            $T->set_var('img_url', adImage::dispUrl($img_file));
            $T->set_var('tn_url', adImage::thumbUrl($img_file));
        } else {
            $T->clear_var('img_url');
            $T->clear_var('tn_url');
        }
        if (strlen($A['description']) > $textlen) {
            $A['description'] = substr($A['description'], 0, $textlen - 3) . ' ...';
        }
        $T->set_var(array(
            'is_uikit'  => $_CONF_ADVT['_is_uikit'] ? 'true' : '',
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
    $content = str_replace($autotag['tagstr'], $tag_data, $content);
    return $content;
}


/**
*   Get the link to a documentation file.
*   Returns the URL to the file, or to the directory if no file requested.
*
*   @see    plugin_getconfigelementhelp_classifieds()
*   @param  string  $file       HTML file
*   @param  string  $doclang    Documentation language
*   @return string              URL to document directory or file
*/
function CLASSIFIEDS_getDocURL($file='', $doclang='english')
{
    global $_CONF, $_CONF_ADVT;

    $doc_path = $_CONF['path_html'] . $_CONF_ADVT['pi_name'] .
                "/docs/$doclang/";
    $doc_url = '';

    if ($file != '') {
        if (!$_CONF_ADVT['_is_uikit']) $file .= '.legacy';
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
*   Show the site header, with or without left blocks according to config.
*
*   @since  version 1.0.2
*   @see    COM_siteHeader()
*   @param  string  $subject    Text for page title (ad title, etc)
*   @param  string  $meta       Other meta info
*   @return string              HTML for site header
*/
function CLASSIFIEDS_siteHeader($subject='', $meta='')
{
    global $_CONF_ADVT, $LANG_ADVT;

    $retval = '';

    $title = $LANG_ADVT['blocktitle'];
    if ($subject != '')
        $title = $subject . ' : ' . $title;

    switch($_CONF_ADVT['displayblocks']) {
    case 2:     // right only
    case 0:     // none
        $retval .= COM_siteHeader('none', $title, $meta);
        break;

    case 1:     // left only
    case 3:     // both
    default :
        $retval .= COM_siteHeader('menu', $title, $meta);
        break;
    }

    return $retval;
}


/**
*   Show the site footer, with or without right blocks according to config.
*
*   @since  version 1.0.2
*   @see    COM_siteFooter()
*   @return string              HTML for site header
*/
function CLASSIFIEDS_siteFooter()
{
    global $_CONF_ADVT;

    $retval = '';

    switch($_CONF_ADVT['displayblocks']) {
    case 2 : // right only
    case 3 : // left and right
        $retval .= COM_siteFooter(true);
        break;

    case 0: // none
    case 1: // left only
    default :
        $retval .= COM_siteFooter();
        break;
    }

    return $retval;
}


/**
 *  Log activity to a plugin-specific log file
 *
 *  @param string $logentry Text to log
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


/**
*   Get the comment count for a single ad item
*
*   @param  string  $ad_id      ID of ad to check
*   @return integer             Count of related comments
*/
function CLASSIFIEDS_commentCount($ad_id)
{
    global $_TABLES;

    $count = (int)DB_count($_TABLES['comments'],
                    array('sid', 'type'),
                    array($ad_id, 'classifieds') );
    return $count;
}


/**
*   Get a form or URL parameter.
*   Checks $_POST, then $_GET, and returns the raw value if found.
*   Returns NULL if the parameter is not set
*
*   @param  string  $name   Name of parameter
*   @param  string  $type   Type of parameter, default "string"
*   @return mixed       Value of parameter, or NULL if not found
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
    default:
        return $value;
        break;
    }
}


/**
*   Returns an error message formatted for on-screen display.
*   @param  string  $msg    Error message text
*   @param  string  $type   Error type or severity
*   @param  string  $hdr    Optional text to appear in the header.
*   @return string          HTML code for the formatted message
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

    if ($_CONF_ADVT['_is_uikit']) {
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
    } else {
        $element = 'span';
        switch ($type) {
        case 'info':
        case 'alert':
            $class = $type;
            break;
        default:
            $class = 'alert';
            break;
        }
    }
    return "<$element class=\"$class\">$msg_txt</$element>\n";
}


/**
*   Gets the correct template depending on what type of display
*   is being used.  Currently supports the new "blocks" display and the
*   old zClassifieds-style display
*
*   @param  string  $str    Template base name
*   @return string          Template full name
*/
function CLASSIFIEDS_getTemplate($str)
{
    global $_CONF_ADVT;

    if ($str == '') return '';

    switch ($_CONF_ADVT['catlist_dispmode']) {
    case 'blocks':
        $tpl = $str . '_blocks';
        break;

    default:
        $tpl = $str;
        break;
    }

    return $tpl . '.thtml';
}

?>
