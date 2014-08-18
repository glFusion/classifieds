<?php
/**
*   Common functions for the Classifieds plugin
*
*   @author     Lee Garner <lee@leegarner.com>
*   @copyright  Copyright (c) 2009 Lee Garner <lee@leegarner.com>
*   @package    classifieds
*   @version    1.0.0
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
require_once "{$_CONF['path']}plugins/classifieds/classifieds.php";

define('CLASSIFIEDS_PI_PATH', 
        $_CONF['path'] . 'plugins/' . $_CONF_ADVT['pi_name']);
define('CLASSIFIEDS_URL', 
        $_CONF['site_url'] . '/' . $_CONF_ADVT['pi_name']);
define('CLASSIFIEDS_ADMIN_URL', 
        $_CONF['site_admin_url'] . '/plugins/' . $_CONF_ADVT['pi_name']);
define('CLASSIFIEDS_CLASSPATH', 
    $_CONF['path'].'plugins/'.$_CONF_ADVT['pi_name'].'/classes/');

if (file_exists(CLASSIFIEDS_PI_PATH . '/config.php')) {
    /** Include local configuration overrides */
    include_once CLASSIFIEDS_PI_PATH . '/config.php';
}

// End of loading configurations.  Now, if login is required globally,
// set our configuration accordingly
if ($_CONF['loginrequired'])
    $_CONF_ADVT['loginrequired'] = 1;

// Feature under development, forcefully disable from release
$_CONF_ADVT['purchase_enabled'] = 0;

// Language file Include
$langpath = CLASSIFIEDS_PI_PATH . '/language';
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

function USES_classifieds_class_adtype()
{   require_once CLASSIFIEDS_CLASSPATH . 'AdType.class.php';    }

function USES_classifieds_class_image()
{   require_once CLASSIFIEDS_CLASSPATH . 'image.class.php'; }

function USES_classifieds_class_userinfo()
{   require_once CLASSIFIEDS_CLASSPATH . 'UserInfo.class.php';  }

function USES_classifieds_admin()
{   require_once CLASSIFIEDS_PI_PATH . '/admin.php';    }

function USES_classifieds_submitform()
{    require_once CLASSIFIEDS_PI_PATH . '/submitform.php';  }

function USES_classifieds_notify()
{    require_once CLASSIFIEDS_PI_PATH . '/notify.php';  }

function USES_classifieds_advt_functions()
{    require_once CLASSIFIEDS_PI_PATH . '/advt_functions.inc.php';  }

function USES_classifieds_list()
{    require_once CLASSIFIEDS_PI_PATH . '/list.php';    }

function USES_classifieds_edit()
{    include_once CLASSIFIEDS_PI_PATH . '/edit.php';    }

function USES_classifieds_detail()
{    include_once CLASSIFIEDS_PI_PATH . '/detail.php';  }

function USES_classifieds_categories()
{   include_once CLASSIFIEDS_PI_PATH . '/categories.php';   }

function USES_classifieds_class_category()
{   include_once CLASSIFIEDS_PI_PATH . '/classes/category.class.php';   }


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
    global $LANG_ADVT;

    $menuitems[$LANG_ADVT['menuitem']] = CLASSIFIEDS_URL . '/index.php';
    
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
*   Save a comment
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

    $title = strip_tags($title);
    $pid = COM_applyFilter($pid, true);
    $postmode = COM_applyFilter($postmode);
    $db_id = DB_escapeString($id);      // Make a DB-safe id

    $ret = CMT_saveComment($title, $comment, $id, $pid, 
            'classifieds', $postmode);

    if ($ret > 0) {         // failure, return to comment form
        return CLASSIFIEDS_siteHeader()
            . CMT_commentform($title, $comment, $id, $pid,
                    'classifieds', $LANG03[14], $postmode)
            . CLASSIFIEDS_siteFooter();
    }

    // success - update the comment counter for this ad
    DB_query("UPDATE {$_TABLES['ad_ads']} 
                SET comments = comments + 1
                WHERE ad_id='$db_id'", 1);

    // Notify the ad's owner that a comment has been posted.  Don't notify
    // the owner of their own comments.
    list($uid, $title) = DB_fetchArray(DB_query("SELECT owner_id, subject 
            FROM {$_TABLES['ad_ads']}
            WHERE ad_id = '$db_id'"), false);
    $uid = (int)$uid;
    if ($uid > 1 && $uid != $_USER['uid']) {
        $notify = (int)DB_getItem($_TABLES['ad_uinfo'], 'notify_comment',
                "uid='$uid'");
        if ($notify > 0) {
            $email = DB_getItem($_TABLES['users'], 'email', "uid='$uid'");
            $username = COM_getDisplayName($uid);
            $msg = sprintf($LANG_ADVT['comment_notification'], $title);
            $msg .= '<br /><br />' . CLASSIFIEDS_makeURL('detail', $id);
            COM_mail(
                "$username <$email>",
                $subject,
                $msg,
                "{$_CONF['site_name']} <{$_CONF['site_mail']}>",
                true
            );
        }

    }
 
    return (COM_refresh(CLASSIFIEDS_makeURL('detail', $id)));
}


/**
*   How the plugin will display specific comments
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
    global $_TABLES, $LANG_ACCESS;

    $retval = '';

    // Without comment support, we shouldn't get here unless something
    // nefarious is going on, but just in case...
    if (!plugin_commentsupport_classifieds())
        return $retval;

    $sql = "SELECT COUNT(*) AS count, 
                owner_id, group_id, 
                perm_owner, perm_group, perm_members, perm_anon 
            FROM {$_TABLES['ad_ads']} 
            WHERE (ad_id = '".DB_escapeString($id)."')" 
                . COM_getPermSQL('AND') .
            'GROUP BY ad_id';
    $result = DB_query($sql);
    $A = DB_fetchArray($result, false);
    $allowed = $A['count'];

    if ($allowed == 1) {
        $delete_option = (SEC_hasRights('classifieds.edit') &&
                (SEC_hasAccess($A['owner_id'], $A['group_id'],
                    $A['perm_owner'], $A['perm_group'], $A['perm_members'],
                    $A['perm_anon']) == 3));
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
    global $_CONF,$_FM_TABLES, $_TABLES;

    if (SEC_hasRights('classifieds.admin')) {
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
            return (COM_refresh(CLASSIFIEDS_URL . 
                    '/index.php?mode=detail&id=' . $id));
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
    $retval='';
    return $retval;
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

    if (SEC_hasRights($_CONF_ADVT['pi_name']. '.admin')) {
        return array(
            $LANG_ADVT['blocktitle'],
            CLASSIFIEDS_ADMIN_URL . '/index.php',
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
    return CLASSIFIEDS_URL . '/images/' . $_CONF_ADVT['pi_name'] . '.png';
}


/**
*   Returns the administrative option for this plugin
*   Adds the plugin to the Admin menu
*
*   @return   array   Array containing (plugin name, plugin admin url, # of items in plugin or '')
*/
function plugin_getadminoption_classifieds() 
{
    global $_TABLES, $LANG_ADVT;

    $total_ads = DB_getItem($_TABLES['ad_ads'], 'COUNT(*)',
                                   COM_getPermSql (''));
    if (SEC_hasRights('classifieds.admin')) {
        return array(
            $LANG_ADVT['menuitem'], 
            CLASSIFIEDS_ADMIN_URL . '/index.php', 
            $total_ads
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
            CLASSIFIEDS_URL . '/index.php?mode=account', 
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

    $retval = false;
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
                COM_getPermSQL('AND', 0, 2, 'c') . ' ' .
                COM_getPermSQL('AND', 0, 2, 'a') . 
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
        $retval .= $LANG_ADVT['no_new_ads'] . '<br ' . XHTML . '>' . LB;
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
    (($_CONF['loginrequired'] == 1) || 
    ($_CONF_ADVT['loginrequired'] == 1))) {
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
    (($_CONF['loginrequired'] == 1) || 
    ($_CONF_ADVT['loginrequired'] == 1))) {
        return '';
    }

    // Encode query for inclusion in result's url
    $htmlquery = urlencode($query);

    $query = trim(DB_escapeString(htmlspecialchars($query)));

    // Start the SQL query by selecting desired fields.
    $sql = "
        SELECT 
            a.ad_id as id, a.cat_id, a.subject as title, 
            a.descript as description, a.uid, a.views as hits, 
            a.add_date AS date, a.ad_type,
            CONCAT('/{$_CONF_ADVT['pi_name']}/index.php?mode=detail&id=',a.ad_id,'&query=$htmlquery') as url
        FROM 
            {$_TABLES['ad_ads']} a
        RIGHT JOIN
            {$_TABLES['ad_category']} c
            ON a.cat_id = c.cat_id
        WHERE 
            exp_date > UNIX_TIMESTAMP(NOW())
    "
    . COM_getPermSQL('AND', 0, 2, 'c'). ' '
    . COM_getPermSQL('AND', 0, 2, 'a'). ' ';

    if (!empty($datestart) && !empty($dateend)) {
        $delim = substr($datestart, 4, 1);
        if (!empty($delim)) {
            $DS = explode($delim, $datestart);
            $DE = explode($delim, $dateend);
            $startdate = mktime(0, 0, 0, $DS[1], $DS[2], $DS[0]);
            $enddate = mktime(23, 59, 59, $DE[1], $DE[2], $DE[0]);
            $sql .= " AND (a.exp_date BETWEEN '$startdate' AND '$enddate') ";
        }
    }

    if (!empty($author)) {
        $sql .= " AND (a.uid = '$author') ";
    }

    $search = new SearchCriteria($_CONF_ADVT['pi_name'], $LANG_ADVT['blocktitle']);
    $columns = array('a.subject', 'a.descript', 'a.keywords');
    list($sql, $ftsql) = $search->buildSearchSQL($keyType, $query, $columns, $sql);
    //echo $sql;die;
    $search->setSQL($sql);
    //$search->setRank(5);
    //$search->setURLRewrite(true);

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
    $pi_css = CLASSIFIEDS_PI_PATH . '/css/';
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

    $js_dir = CLASSIFIEDS_PI_PATH . '/js/';

    $files = array(
        "$js_dir/catfldsxml.js",
        "$js_dir/picker.js",
        "$js_dir/toggleEnabled.js",
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
    global $_TABLES, $_CONF_ADVT;

    $pi_name = $_CONF_ADVT['pi_name'];

    // Get the current version, compare to the version in the plugin code
    $current_ver = DB_getItem($_TABLES['plugins'], 'pi_version',
        "pi_name = '$pi_name'");
    if (!$current_ver) {
        COM_errorLog("Error getting the $pi_name Plugin version",1);
        return '03';
    }

    // if we're at the current version, what are we doing here?
    if ($current_ver >= $_CONF_ADVT['pi_version']) {
        COM_errorLog("Current version $current_ver is already up to date");
        return '06';
    }

    // If we're still here, import the upgrade functions.
    require_once 'upgrade.inc.php';
    $error = classifieds_do_upgrade($current_ver);
    if ($error) {
        COM_errorLog("Error updating the $pi_name Plugin version",1);
        return '04';
    }

    // now update the current version number.
    DB_query("UPDATE
        {$_TABLES['plugins']}
    SET
        pi_version = '{$_CONF_ADVT['pi_version']}',
        pi_gl_version = '{$_CONF_ADVT['gl_version']}',
        pi_homepage = '{$_CONF_ADVT['pi_url']}'
    WHERE
        pi_name = '$pi_name';
    ");

    if (DB_error()) {
        COM_errorLog("Error updating the $pi_name Plugin version",1);
        return '05';
    }

    COM_errorLog("Succesfully updated the $pi_name Plugin!",1);
    return true;
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
        'ad_id, cat_id, uid, subject, descript, url, add_date, exp_date, 
            group_id, owner_id,
            perm_owner, perm_group, perm_members, perm_anon,
            price, ad_type, keywords',
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
    return SEC_hasRights('classifieds.admin');
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
        $plugin->getsubmissionssql = 
                "SELECT 
                    ad_id as id, subject, 
                    FROM_UNIXTIME(add_date) as date, price,
                    owner_id as uid
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
    global $_TABLES, $_USER, $_CONF, $_CONF_ADVT;

    // Notify subscribers.
    USES_classifieds_notify();
    catNotify($id);

    // Notify submitter of the approval, if it's not us
    if ($owner_id != $_USER['uid']) {
        CLASSIFIEDS_notifyApproval($id, true);
    }

    return '';
}


/**
*   Performs plugin exclusive work for items deleted by moderation.
*   Deleet uploaded images, notify submitter of denial.
*
*   @param  string  $id     ID of the ad deleted.
*   @return string          Any wanted HTML output
*/
function plugin_moderationdelete_classifieds($id) 
{
    global $_TABLES, $_CONF_ADVT;

    USES_classifieds_notify();

    // Return the unused days back to the user's account
    if ($_CONF_ADVT['purchase_enabled']) {
        USES_classifieds_class_userinfo();
        list($owner_id, $add_date, $exp_date) = DB_fetchArray(DB_query(
            "SELECT owner_id, add_date, exp_date
            FROM {$_TABLES['ad_submission']}
            WHERE ad_id='$id'"));
        $days = (int)(((int)$exp_date - (int)$add_date) / 86400);
        $User->UpdateDaysBalance($days, (int)$owner_id);
    }

    // Delete uploaded photos from filesystem
    deletePhotos($id);
    CLASSIFIEDS_notifyApproval($id, false);

    return '';
}


/**
*   Returns the plugins submission form
*
*   @return       string       The HTML form
*/
function plugin_submit_classifieds() 
{
    global  $HTTP_POST_VARS, $LANG12, $_CONF, $_CONF_ADVT; 

    // If mode = preview or editsubmission, load the ad info.
    if ($HTTP_POST_VARS['mode'] == $LANG12[32] ||
        $HTTP_POST_VARS['mode'] == 'editsubmission') {
        $A = $HTTP_POST_VARS;
    } else {
        $A = array();
    }

    USES_classifieds_submitform();
    return CLASSIFIEDS_submitForm('submit', $A);

}


/**
*   Save a new submission
*
*   @param array $A Array of values from $_POST
*   @return string Error message, or refresh to submission page
*/
function plugin_savesubmission_classifieds($A)
{
    global $_TABLES, $_USER, $_CONF, $_CONF_ADVT, $LANG12, $LANG_ADVT;

    if (!CLASSIFIEDS_canSubmit()) {
        return false;
    }

    if (empty($_USER['uid'])) {
        return false;       // Catastrophic error, just return
    } else {
        $owner_id = $_USER['uid'];
    }

    // Initialize error message.  If it's still empty at the end, then
    // the save was successful.  Otherwise, redisplay the submission
    // form with the error message at the top.
    $errmsg = '';

    // check for missing fields
    if (empty($A['subject']) || empty($A['descript']) || empty($A['catid'])) {
        COM_resetSpeedlimit('submit');
        $errmsg .= "<li>". $LANG_ADVT['missing_req_fields']. "</li>\n";
    }

    // Determine which table and success message to display
    if (SEC_hasRights($_CONF_ADVT['pi_name']. '.submit') || $_CONF_ADVT['submission'] == false) {
        $table = 'ad_ads';
        $msg = '02';    // "ad has been save" message
    } else {
        $table = 'ad_submission';
        $msg = '01';    // "added to queue" message
    }

    // Make sure we have permission to post in the category
    if (CLASSIFIEDS_checkCatAccess($A['catid']) < 3) {
        $errmsg .= "<li>". $LANG_ADVT['invalid_category']. "</li>\n";
    }

    if ($errmsg == '') {        // Only save if no errors so far
        if ($_CONF_ADVT['purchase_enabled']) {
            USES_classifieds_class_userinfo();
            $User = new adUserInfo($owner_id);
            $ad_days = min((int)$A['moredays'], $User->getMaxDays());
        } else {
            $ad_days = (int)$A['moredays'];
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
            if (DB_getItem($_TABLES['ad_category'], 
                    'perm_anon', "cat_id='{$A['cat_id']}'") == '0') {
                $_POST['perm_anon'] = '0';
            }
            $A['perms'] = array(
                $_CONF_ADVT['default_permissions'][0],
                $_CONF_ADVT['default_permissions'][1],
                $_CONF_ADVT['default_permissions'][2],
                isset($_POST['perm_anon']) ? 2 : 0
            );
        } else {
            $A['owner_id'] = COM_applyFilter($_POST['owner_id'], true);
            $A['group_id'] = COM_applyFilter($_POST['group_id'], true);

            // Get the category permissions.  Force anon visibility to zero
            // if it's zero for the category, otherwise set to the user's
            // preference.
            if (DB_getItem($_TABLES['ad_category'], 
                    'perm_anon', "cat_id='{$A['cat_id']}'") == '0') {
                $_POST['perm_anon'] = '0';
            }
            $A['perms'] = array(
                $_CONF_ADVT['default_permissions'][0],
                $_CONF_ADVT['default_permissions'][1],
                $_CONF_ADVT['default_permissions'][2],
                $_POST['perm_anon']        );
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
        $display .= "<span class=alert><ul>$errmsg</ul></span\n";
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
            USES_classifieds_advt_functions();
            while ($row = DB_fetchArray($r)) {
                adDelete($row['ad_id'], true);
            }

            $numrows = DB_numRows($r);
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
        USES_classifieds_notify();
        CLASSIFIEDS_notifyExpiration();
    }
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
            SET owner_id = $destUID WHERE owner_id = $origUID", 1);
    DB_query("UPDATE {$_TABLES['ad_ads']}
            SET uid = $destUID WHERE uid = $origUID", 1);
    DB_query("UPDATE {$_TABLES['ad_submission']}
            SET owner_id = $destUID WHERE owner_id = $origUID", 1);
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
            . COM_getPermSQL('AND', 0, 2, 'ad')
            . COM_getPermSQL('AND', 0, 2, 'cat');
    //echo $sql;
    $r = DB_query($sql);
    $n = DB_numRows($r);
    if ($n < 1)
        return '';

    $f = rand(0,$n-1);
    $id = DB_result($r, $f, 'id');

    $link = CLASSIFIEDS_makeURL('detail', $id);
    $subject = DB_result($r, $f, 'subject');

    $T = new Template(CLASSIFIEDS_PI_PATH . '/templates');
    $T->set_file('rblock', 'randomblock.thtml');
    $T->set_var('ad_url', $link);
    $T->set_var('ad_subject', $subject);
 
    $r = DB_query("SELECT  filename 
            FROM {$_TABLES['ad_photo']} 
            WHERE ad_id='". DB_escapeString($id). "' 
            LIMIT 1");
    if ($r && DB_numrows($r) == 1) {
        $prow = DB_fetchArray($r);
        if (file_exists("{$_CONF_ADVT['image_dir']}/thumb/{$prow['filename']}")) {
            list($s_width, $s_height, $width, $height) = CLASSIFIEDS_imgReDim(
                    "{$_CONF_ADVT['image_dir']}/thumb/{$prow['filename']}", 
                    $_CONF_ADVT['random_blk_width'], 
                    0
                    );
            $T->set_var('img_url', 
                $_CONF_ADVT['image_url'] . '/thumb/' . $prow['filename']);
            $T->set_var('img_alt', 'Ad Thumbnail');
            $T->set_var('img_width', $width);
            $T->set_var('img_height', $height);
        } else {
            $T->set_var('img_url', '');
        }
    }

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
    $url = CLASSIFIEDS_URL . '/index.php?'
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
*   Delets all photos related to the given ad from the disk
*   and the database.
*
*   @param  int     $ad_id  ID of ad for which photos are to be deleted
*/
function deletePhotos($ad_id='')
{
    global $_TABLES, $_CONF_ADVT;

    $ad_id = COM_sanitizeID($ad_id);
    if ($ad_id == '')
        return 1;

    // Locate and delete photos
    $sql = "SELECT filename
        FROM {$_TABLES['ad_photo']}
        WHERE ad_id='". DB_escapeString($ad_id). "'";
    //echo $sql;
    $photo= DB_query($sql);
    if (!$photo) return 2;

    while ($prow = DB_fetchArray($photo)) {
        if (file_exists("{$_CONF_ADVT['image_dir']}/{$prow['filename']}"))
            unlink( "{$_CONF_ADVT['image_dir']}/{$prow['filename']}" );

        if (file_exists("{$_CONF_ADVT['image_dir']}/thumb/{$prow['filename']}"))
            unlink("{$_CONF_ADVT['image_dir']}/thumb/{$prow['filename']}");
    }

    DB_delete($_TABLES['ad_photo'], 'ad_id', $ad_id);

    return 0;
}


/**
*   Returns a comma-separated string containing the requested
*   category and all children.
*
*   @param int id Category ID
*   @return string Comma-separated category list
*/
function CLASSIFIEDS_ChildCatList($id=0)
{
    global $_TABLES;

    $str = '';

    $id = (int)$id;
    if ($id == 0)
        return $str;

    // Append our id to the string
    $str .= $id. ',';

    // Get the papa_id of the current id
    $result = DB_query("SELECT cat_id 
        FROM {$_TABLES['ad_category']} 
        WHERE papa_id=$id ");
    if (!$result) return $str;

    while ($row = DB_fetchArray($result)) {
        $str .= trim($row['cat_id']) . ',';
    }

    return trim($str, ', ');

}   // CLASSIFIEDS_ChildCatList()


/**
*   Calls itself recursively to find the root category of the requested id
*
*   @param int id Category ID
*   @return string Comma-separated category list
*/
function CLASSIFIEDS_ParentCatList($id=0, $str='')
{
    global $_TABLES;

    $id = (int)$id;
    if ($id == 0)
        return $str;

    // Append our id to the string
    $str .= $id;

    // Get the papa_id of the current id
    $sql = "SELECT cat_id, papa_id 
        FROM {$_TABLES['ad_category']} 
        WHERE cat_id=$id";
    $result = DB_query($sql);
    if (!$result) return $str;

    $row = DB_fetchArray($result);
    // If we found a parent category, call ourself to add it
    if ((int)$row['papa_id'] > 0) {
        $str = CLASSIFIEDS_ParentCatList($row['papa_id'], $str.',');
    }

    return trim($str, ', ');

}   // CLASSIFIEDS_ParentCatList()


/**
*   Calls itself recursively to create the breadcrumb links
*
*   @param  integer $id         Current Category ID
*   @param  boolean $showlink   Link to the current category?
*   @return string              HTML for breadcrumbs
*/
function CLASSIFIEDS_BreadCrumbs($id=0, $showlink=true)
{
    global $_TABLES, $LANG_ADVT;

    $id = (int)$id;
    if ($id == 0)
        return '';

    $result = DB_query("SELECT cat_name, cat_id, papa_id 
                FROM {$_TABLES['ad_category']} 
                WHERE cat_id=$id");
    if (!$result) 
        return CLASSIFIEDS_errorMsg($LANG_ADVT['database_error'], 'alert');

    $row = DB_fetchArray($result);
    if ($row['papa_id'] == 0) {
        $location .= '<a href="'. CLASSIFIEDS_makeURL('home', 0) . 
                    '">' . $LANG_ADVT['home'] . '</a> :: ';
        if ($showlink) {
            $location .= '<a href="'. 
                    CLASSIFIEDS_makeURL('home', $row['cat_id']) . '">' . 
                    $row['cat_name'] . '</a>';
        } else {
            $location .= $row['cat_name'];
        }
    } else {
        $location .= CLASSIFIEDS_BreadCrumbs($row['papa_id'], $showlink);
        if ($showlink) {
            if ($row['cat_id'] == $id) {
                $location .= " &gt; {$row['cat_name']}";
            } else {
                $location .=
                    ' &gt; <a href="' .
                    CLASSIFIEDS_makeURL('home', $row['cat_id']). '">' .
                    $row['cat_name'] . '</a>';
            }
        } else {
            $location .= " :: {$row['cat_name']}";
        }
    }

    return "<b>$location</b>";
}


/**
*   Creates a new ad record in the specified table, either submission or
*   live.  Does not handle photo uploads.
*
*   @param  array   $A      Ad information, from $_POST or DB_fetchArray()
*   @param  string  $table  Table into which the ad is to be inserted
*/
function CLASSIFIEDS_insertAd($A, $table='ad_ads')
{
    global $_TABLES, $_USER, $_CONF_ADVT;

    $A = CLASSIFIEDS_PrepareForDB($A);

    $sql = "INSERT INTO {$_TABLES[$table]} (
            ad_id, cat_id, uid, subject, descript, url, add_date, exp_date,
             ad_type, price, perm_owner, perm_group, 
             perm_members, perm_anon, owner_id, group_id, keywords, exp_sent,
            comments_enabled
        ) VALUES (
            '{$A['ad_id']}',
            '{$A['catid']}', 
            '{$A['uid']}',
            '{$A['subject']}',
            '{$A['descript']}',
            '{$A['url']}',
            {$A['add_date']},
            {$A['exp_date']},
            {$A['ad_type']},
            '{$A['price']}',
            {$A['perms'][0]},{$A['perms'][1]},{$A['perms'][2]},{$A['perms'][3]},
            {$A['owner_id']}, {$A['group_id']}, 
            '{$A['keywords']}',
            {$A['exp_sent']},
            {$A['comments_enabled']}
        )";
    //echo $sql;die;

    DB_query($sql);
    CTL_clearCache($_CONF_ADVT['pi_name']);

    return DB_error();

}


/**
*  Updates a single ad record in the table with data from $A.
*  Note 1 : Permission to update the ad must be checked before this 
*      function is called.
*  Note 2 : $A['ad_id'] must contain a valid ad ID
*
*   @param array A Ad Data, typically from $_POST
*   @param string table Table to update (production or submission)
*/
function CLASSIFIEDS_updateAd($A, $table='ad_ads')
{
    global $_TABLES, $_USER, $_CONF_ADVT;

    $A = CLASSIFIEDS_PrepareForDB($A);

    // Update the existing record
    $sql = "UPDATE {$_TABLES[$table]} SET
            cat_id='{$A['catid']}',
            subject='". $A['subject']. "',
            descript='". $A['descript']. "',
            url='". $A['url']. "',
            exp_date={$A['exp_date']},
            owner_id={$A['owner_id']},
            group_id={$A['group_id']},
            uid={$A['uid']},
            perm_owner={$A['perms'][0]},
            perm_group={$A['perms'][1]},
            perm_members={$A['perms'][2]},
            perm_anon={$A['perms'][3]},
            ad_type={$A['ad_type']},
            price='". $A['price']. "',
            keywords='". $A['keywords']. "',
            exp_sent={$A['exp_sent']},
            comments_enabled={$A['comments_enabled']}
        WHERE ad_id='{$A['ad_id']}'";

    //if (!SEC_hasRights('classifieds.admin','OR')) {
    //    $sql .= " AND uid={$_USER['uid']}";
    //}

    DB_query($sql);
    CTL_clearCache($_CONF_ADVT['pi_name']);
}


/**
*   Handles the upload of one photo and the creation of the thumbnail
*
*   @param  string  $adid   Ad ID associated with the photo
*   @param  array   $photo  The photo informatino from $_FILES
*/
function CLASSIFIEDS_UploadPhoto($adid, $var = '')
{
    USES_classifieds_class_image();

    $U = new Image($adid, $var);
    $U->uploadFiles();

    if ($U->areErrors() > 0) {
        return $U->printErrors(false);
    } else {
        return '';
    }

}


/**
*   Calculates new dimensions for the $src image, preserving the aspect ratio.
*   If either $width or $height are zero, then only the other is used.  If both
*   are zero, then no resizing occurs.
*
*   Images are only sized smaller, never larger.
*
*   @param  string  $src    Image filespec
*   @param  int     $width  Requested max width
*   @param  int     $height Requested max height
*   @return array   Original and New image dimensions
*
*/
function CLASSIFIEDS_imgReDim($src, $width=0, $height=0)
{
    $dimensions = getimagesize($src);
    $s_width = $dimensions[0];
    $s_height = $dimensions[1];

    // get both sizefactors that would resize one dimension correctly
    if ($width > 0 && $s_width > $width) 
        $sizefactor_w = (double) ($width / $s_width);
    else
        $sizefactor_w = 1;

    if ($height > 0 && $s_height > $height) 
        $sizefactor_h = (double) ($height / $s_height);
    else
        $sizefactor_h = 1;

    $sizefactor = min($sizefactor_w, $sizefactor_h);

    $newwidth = (int)($s_width * $sizefactor);
    $newheight = (int)($s_height * $sizefactor);

    return array($s_width, $s_height, $newwidth, $newheight);
}


/**
*   Resizes the source image into the destination directory using $filename
*   as the new name.  Aspect ratio is preserved.
*
*   @param string src Complete path to source file, including name
*   @param string filename Filename only
*   @param string dst Destination directory, no filename
*   @param int width Max width in pixels
*   @param int height Max height in pixels
*   @return string Error message or empty string
*/
function CLASSIFIEDS_imgResize($src, $filename, $dst, $width, $height)
{
    global $_TABLES, $_CONF_ADVT, $_USER, $_CONF, $LANG_ADVT;

    if (!is_writable($dst))
        return "";

    /*$dimensions = getimagesize($src);
    $s_width = $dimensions[0];
    $s_height = $dimensions[1];*/

    list($s_width, $s_height, $newwidth, $newheight) = CLASSIFIEDS_imgReDim($src, $width, $height);

    // Resizing only goes smaller, not larger.
    if ($s_width > $width || $s_height > $height) {
        $result = array();

        // Use global image function if exist (glfusion 1.1+)
        // or borrow Mediagallery function otherwise.
        if (function_exists(_img_resizeImage)) {
            $result = _img_resizeImage($src, "{$dst}{$filename}",
                        $s_height, $s_width,
                        $newheight, $newwidth, "image/jpeg");
        } elseif (function_exists(_mg_resizeImage)) {
            $result = _mg_resizeImage($src, "{$dst}{$filename}",
                        $s_height, $s_width,
                        $newheight, $newwidth, "image/jpeg");
        } else {
            $result[0] = false;
        }
        if ($result[0] != true)
            return "Error creating resized image.";

    } elseif ($src != $dst) {
        // Not resizing, just copy the file
        copy($src, $dst . $filename);

    }

    return "";

}   // function CLASSIFIEDS_imgResize()


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
*  @param $grplist string Comma-separated list of groups, SQL format
*  @param $NOT string Set to "NOT" to exclude $grplist, blank to include it.
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
*   Returns the results of SEC_hasRights, 3=rw, 2=ro
*
*   @param string $id Ad ID
*   @param array $A Category info, if already available
*   @param int $in_access Previously computed access.
*   @return int Access value
*/
function CLASSIFIEDS_checkCatAccess($id, $recurse=false, $A = '', $in_access = 3)
{
    global $_TABLES, $_CONF_ADVT;

    $id = (int)$id;
    if ($id < 1)
        return 0;

    // Admin rights trump admin-specific ones
    if (SEC_hasRights($_CONF_ADVT['pi_name']. '.admin'))
        return 3;

    if (!is_array($A)) {

        $sql = "SELECT 
            owner_id, group_id,
            perm_owner, perm_group,
            perm_members, perm_anon,
            papa_id
        FROM
            {$_TABLES['ad_category']}
        WHERE 
            cat_id='$id'";

        $result = DB_query($sql);
        if (!$result || DB_numRows($result) == 0) {
            return 0;
        }
        $A = DB_fetchArray($result);

    }

    $my_access = SEC_hasAccess($A['owner_id'], $A['group_id'], 
                $A['perm_owner'], $A['perm_group'], 
                $A['perm_members'], $A['perm_anon']);

    // If our caclulated access is greater than the incoming access, 
    // set my_access to the smaller number.
    if ($my_access > $in_access)
        $my_access = $in_access;

    // If we still have access > 0, and there's a parent category, check it
    // to see if we should reduce our access.  Check recursion first since papa_id
    // might not be passed in with $A for simple single-level checks.
    if ($my_access > 0 && $recurse == true) {
        if (isset($A['papa_id']) && $A['papa_id'] > 0) {
            $my_access = CLASSIFIEDS_checkCatAccess($A['papa_id'], $recurse, '', $my_access);
        }
    }

    return $my_access;

}


/**
 *  Loads the language array.  If $requested is an array, the first valid
 *  language file is loaded.  If not, the $requested language file is loaded.
 *  If $requested doesn't refer to a vailid language, then $_CONF['language']
 *  is assumed.
 *
 *  After loading the base language file, the same filename is loaded from
 *  language/custom, if available.  The admin can override language strings
 *  by creating a language file in that directory.
 *
 *  @param  mixed   $deflang    A single or array of language strings
 *  @return array               $LANG_ADVT, the global language array for the plugin
 */
function plugin_loadlanguage_classifieds($requested='')
{
    global $_CONF;

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

    // Search the array for desired language files, in order.
    $langpath = CLASSIFIEDS_PI_PATH . '/language';
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
    static $canSubmit = 'unknown';

    if ($canSubmit === 'unknown') {
        $canSubmit = COM_isAnonUser() ? false : true;
    }
    return $canSubmit;

}


/**
*   Get the language string corresponding to the type of ad.
*   Defaults to string zero if the actual string has been removed.
*
*   @param  integer $ad_type    Number of the ad type
*   @param  array   $lang       Optional language array.
*   @return string              Language-specific ad type string
*/
function CLASSIFIEDS_getAdTypeString($ad_type, $lang='')
{
    USES_classifieds_class_adtype();
    $Type = new AdType($ad_type);
    $str = $Type->getDescrip();
    return $str;
}


/**
*   Create a dropdown select of ad types.
*   By default include only enabled types, and set the selection to the
*   specified type, if any.
*   @param  integer $sel        Selected type
*   @param  boolean $enabled    Include only enabled types
*   @return string              HTML code for selection options
*/
function CLASSIFIEDS_makeAdTypeSelection($sel = 0, $only_enabled = true)
{
    global $LANG_ADVT;

    $retval = '';

    foreach ($LANG_ADVT['ad_types'] as $ad_type => $ad_data) {
        if (!$only_enabled || $ad_data['enabled'] == 1) {
            $selected = $ad_type == $sel ? 'SELECTED' : '';
            $retval .= "<option value=\"{$ad_type}\" $selected>{$ad_data['name']}</option>\n";
        }
    }

    return $retval;
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
        echo COM_Refresh(CLASSIFIEDS_URL . '/index.php');
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
*   Get the URL to the localized configuration help file.
*
*   @param  string  $option     Name of config option selected
*   @param  string  $doclang    Document language desired.
*   @return array   Array of (url, window_type)
*/
function plugin_getconfigelementhelp_classifieds($option, $doclang='english')
{
    $doc_url = CLASSIFIEDS_getDocURL('config.html', $doclang);
    if (empty($doc_url))
        return false;

    if (!empty($option)) {
        $file .= '#desc_' . $option;
    }

    return array($doc_url . $file, 0);
/*    global $_CONF, $_CONF_ADVT;

    $file  = 'config_' . $doclang . '.html';
    if (!file_exists($_CONF['path_html'] . '/admin/plugins/' .
            $_CONF_ADVT['pi_name'] . '/docs/' . $file)) {
        $file = 'config_english.html';
    }

    if (!empty($option)) {
        $file .= '#desc_' . $option;
    }

    return array(CLASSIFIEDS_ADMIN_URL . '/docs/' . $file, 0);*/
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

    $T = new Template(CLASSIFIEDS_PI_PATH . '/templates');

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

    $T->set_file('autotag', $template);

    $sql = "SELECT ad.*, t.descrip as type
            FROM {$_TABLES['ad_ads']} ad
            LEFT JOIN {$_TABLES['ad_types']} t
            ON ad.ad_type = t.id ";

    switch ($autotag['parm1']) {
    case 'ad':      // Show an ad
        if ($ad_id != '') {
            $sql .= " WHERE ad.ad_id='" . DB_escapeString($ad_id) . "' " ;
        } else {
            return '';
        }
        break;
    case 'recent':  // Show one or more recent ads
        $sql .= ' ORDER BY ad.add_date DESC ';
        break;
    }

    $sql .= COM_getPermSQL('AND', 0, 2) . " LIMIT $limit";

    $result = DB_query($sql);
    if (!$result) return '';
    $T->set_block('autotag', 'tag_data', 'TAG');
    while ($A = DB_fetchArray($result, false)) {
        $img_file = DB_getItem($_TABLES['ad_photo'], 'filename',
                "ad_id='{$A['ad_id']}'");
         if ($img_file != '' && 
            is_file("{$_CONF_ADVT['image_dir']}/{$img_file}")) {
            $T->set_var('img_file', $img_file);
            $T->set_var('img_url', $_CONF_ADVT['image_url']);
        } else {
            $T->clear_var('img_url');
            $T->clear_var('img_rile');
        }
        if (strlen($A['descript']) > $textlen) {
            $A['descript'] = substr($A['descript'], 0, $textlen - 3) . ' ...';
        }
        $T->set_var(array(
            'ad_id'     => $A['ad_id'],
            'cat_id'    => $A['cat_id'],
            'uid'       => $A['uid'],
            'subject'   => htmlspecialchars($A['subject']),
            'descript'  => htmlspecialchars($A['descript']),
            'url'       => COM_sanitizeURL($A['url']),
            'add_date'  => COM_getUserDateTimeFormat($A['add_date']),
            'exp_date'  => COM_getUserDateTimeFormat($A['exp_date']),
            'ad_type'   => htmlspecialchars($A['type']),
            'pi_url'    => CLASSIFIEDS_URL,
            'ad_url'    => COM_buildUrl(CLASSIFIEDS_URL 
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
*   Prepare variables to be saved in the database.
*
*   @param  array   $A  Array of variables.
*   @return array       Array with modified variables
*/
function CLASSIFIEDS_PrepareForDB($A)
{
    global $_CONF, $_CONF_ADVT;

    $spamcheck = "<p>{$A['subject']}</p>
        <p>{$A['descrip']}</p>
        <p>{$A['price']}</p>
        <p>{$A['keywords']}</p>
        <p>{$A['url']}</p>";
    $result = PLG_checkforSpam($spamcheck, $_CONF['spamx']);
    if ($result > 0) {
        COM_updateSpeedlimit($_CONF_ADVT['pi_name']);
        COM_displayMessageAndAbort($result, 'spamx', 403, 'Forbidden');
    }

    $A['ad_id'] = COM_sanitizeID($A['ad_id'], false);
    $A['subject'] = DB_escapeString(COM_checkWords(COM_checkHTML($A['subject'])));
    $A['descript'] = DB_escapeString(COM_checkWords($A['descript']));
    $A['price'] = DB_escapeString(COM_checkWords(COM_checkHTML($A['price'])));
    $A['keywords'] = DB_escapeString(COM_checkWords(COM_checkHTML($A['keywords'])));
    $A['url'] = COM_sanitizeURL(COM_checkWords(COM_checkHTML($A['url'])));
    $A['cat_id'] = (int)$A['cat_id'];
    $A['ad_type'] = (int)$A['ad_type'];
    $A['uid'] = (int)$A['uid'];
    $A['add_date'] = (int)$A['add_date'];
    $A['exp_date'] = (int)$A['exp_date'];
    $A['perm_ower'] = (int)$A['perm_owner'];
    $A['perm_group'] = (int)$A['perm_group'];
    $A['perm_members'] = (int)$A['perm_members'];
    $A['perm_anon'] = (int)$A['perm_anon'];
    $A['group_id'] = (int)$A['group_id'];
    $A['exp_sent'] = (int)$A['exp_sent'];
    $A['comments_enabled'] = (int)$A['comments_enabled'];

    return $A;
}


/**
*   Get the link to a documentation file.
*   Returns the URL to the file, or to the directory if no file requested.
*
*   @see    plugin_getconfigelementhelp_subscription()
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
        if (!is_file($doc_path . $file)) {
            $doclang = 'english';
        }
        $doc_url = CLASSIFIEDS_URL . "/docs/$doclang/$file";
    } else {
        if (!is_dir($doc_path)) {
            $doclang = 'english';
        }
        $doc_url = CLASSIFIEDS_URL . "/docs/$doclang/";
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
        return $LANG01[33] . $logfile . ' (' . $timestamp . ')<br' . XHTML . '>' . LB;
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

?>
