<?php
/**
*   General plugin-specific functions for the Classifieds plugin.
*
*   @author     Lee Garner <lee@leegarner.com>
*   @copyright  Copyright (c) 2009 Lee Garner <lee@leegarner.com>
*   @package    classifieds
*   @version    1.0.2
*   @license    http://opensource.org/licenses/gpl-2.0.php 
*               GNU Public License v2 or later
*   @filesource
*/


/**
*  Delete an ad and associated photos
*
*  @param integer $ad_id    Ad ID number
*  @param boolean $admin    True if this is an administrator
*/
function adDelete($ad_id = '', $admin=false, $table = 'ad_ads')
{
    global $_USER, $_TABLES, $_CONF_ADVT;

    $ad_id = COM_sanitizeID($ad_id);
    if ($ad_id == '') 
        return 1;
    if ($table != 'ad_ads' && $table != 'ad_submission')
        return 2;

    // Check the user's access level.  If this is an admin call,
    // force access to read-write.
    $myaccess = $admin ? 3 : CLASSIFIEDS_checkAccess($ad_id);
    if ($myaccess < 3)
        return 3;

/*    $selection = "ad_id = '$ad_id'";
    if (!$admin) {
        $selection.= " AND uid={$_USER['uid']}";
    }
    $ad = DB_getItem($_TABLES[$table], 'ad_id', $selection);
    if ($ad == '')
        return 5;*/
   
    // If we've gotten this far, then the current user has access
    // to delete this ad. 
    if ($table == 'ad_submission') {
        // Do the normal plugin rejection stuff
        plugin_moderationdelete_classifieds($ad_id);
    } else {
        // Do the extra cleanup manually
        if (deletePhotos($ad_id) != 0)
            return 5;
    }

    // After the cleanup stuff, delete the ad record itself. 
    DB_delete($_TABLES[$table], 'ad_id', $ad_id);
    CLASSIFIEDS_auditLog("Ad $ad_id deleted.");
    if (DB_error()) {
        COM_errorLog(DB_error());
        return 4;
    } else {
       return 0;
    }

}


/**
*   Create a list of ads
*   @param boolean $all   True if all ads should be listed
*   @param boolean $admin True if this is an administrator
*   @return string Page Content
*/ 
function adList($admin=0)
{
    global $_CONF, $LANG_ADVT, $_TABLES, $_USER, $PHP_SELF, $_CONF_ADVT;

    $display = '';

    $time = time();     // used to check expiration

    // Define the max ads per page.  Maybe move this to config area
    $maxAds = isset($_CONF_ADVT['maxads_pg_list']) ? 
                (int)$_CONF_ADVT['maxads_pg_list'] : 20;

    // Set up the return page for the action url so we get back
    // where we came from
    if ($admin) {
        $return_page = "adminads";
        $base_url = $_CONF['site_admin_url'] . '/plugins';
    } else {
        $return_page = "manage";
        $base_url = $_CONF['site_url'];
    }
    $base_url .= '/' . $_CONF_ADVT['pi_name'] . '/index.php';

    $T = new Template(CLASSIFIEDS_PI_PATH . '/templates');
    $T->set_file('manage', 'adList.thtml');
    $T->set_var('site_url',$_CONF['site_url']);
    $T->set_var('return_page',$return_page);

    $sql = "SELECT ad_id, cat_id, subject, add_date, exp_date
            FROM {$_TABLES['ad_ads']} ";

    if ($admin == false) {
        // if not an admin, restrict to this user's ads
        $sql .= "WHERE uid='{$_USER['uid']}' ";
    }

    $sql .= "ORDER BY add_date DESC";
    //echo $sql;die;
    $result = DB_query($sql);
    if (!$result)
        return CLASSIFIEDS_errorMsg($LANG_ADVT['database_error'], 'alert');

    // Figure out the page number, and execute the query
    // with the appropriate LIMIT clause.
    $totalAds = DB_numRows($result);
    if ($totalAds <= $maxAds)
        $totalPages = 1;
    elseif ($totalAds % $maxAds == 0)
        $totalPages = $totalAds / $maxAds;
    else
        $totalPages = ceil($totalAds / $maxAds);

    $page = COM_applyFilter($_REQUEST['start'], true);
    if ($page < 1 || $page > $totalPages)
        $page = 1;

    if ($totalAds == 0)
        $startEntry = 0;
    else
        $startEntry = $maxAds * $page - $maxAds + 1;

    if ($page == $totalPages)
        $endEntry = $totalAds;
    else
        $endEntry = $maxAds * $page;

    $prePage = $page - 1;
    $nextPage = $page + 1;
    $initAds = $maxAds * $page - $maxAds;

    // Create the page menu string for display if there is more
    // than one page
    $pageMenu = '';
    if ($totalPages > 1) {
        $baseURL = "$base_url?mode=$return_page";
        $pageMenu = COM_printPageNavigation($baseURL, $page, $totalPages, "start=");
    }
    $T->set_var('pagemenu', $pageMenu);

    // Now set the limit clause in the query and re-execute to get just one page.
    $sql .= " LIMIT $initAds, $maxAds";
    //echo $sql;die;
    $result = DB_query($sql);
    if (!$result)
        return CLASSIFIEDS_errorMsg($LANG_ADVT['database_error'], 'alert');

    $T->set_var('base_url', $base_url);

    $T->set_block('manage', 'QueueRow', 'QRow');
    $i = 1;
    if (DB_numRows($result) == 0) {
        $T->set_var('subject', $LANG_ADVT['no_ads_listed']);
    } else {
        $exptime = $time +  ($_CONF_ADVT['exp_notify_days'] * 86400);
        while ($row = DB_fetchArray($result)) {
            $bgColor = $i % 2 == 0 ? "#FFFFFF" : "#E6E6E6";
            $T->set_var('bgColor', $bgColor);
            if ($admin) {
                $T->set_var('ad_url', 
                    $_CONF['site_url'] . '/admin/plugins/' .
                    $_CONF_ADVT['pi_name'] . 
                    '/index.php?mode=detail' .
                    '&id=' . $row['ad_id']);
            } else {
                $T->set_var('ad_url', CLASSIFIEDS_makeURL('detail', 
                            $row['ad_id']));
            }
            $T->set_var('ad_id', $row['ad_id']);
            $T->set_var('cat_id',$row['cat_id']);
            $T->set_var('seq_no', $i);
            $T->set_var('subject', strip_tags($row['subject']));
            $T->set_var('dt_add', date($_CONF['shortdate'], $row['add_date']));
            if ($row['exp_date'] < $time) {
                $T->set_var('dt_exp', $LANG_ADVT['expired']);
            } elseif ($row['exp_date'] < $exptime) {
                $T->set_var('dt_exp', '<font color="red">' .
                        date($_CONF['shortdate'], $row['exp_date'] . 
                        '</font>'));
            } else {
                $T->set_var('dt_exp', date($_CONF['shortdate'], $row['exp_date']));
            }
            // Show the action icons. Admins get a linke to approve,
            // others just see the icon

            // Display links to approve if this is an administrator
            if ($admin || $_CONF_ADVT['usercanedit'] == 1) {
                $T->set_var('have_editlink', 'true');
//                $T->set_block('manage', 'EditLink', 'EditLink1');
//                $T->parse('EditLink1', 'EditLink', false);
            } else {
                $T->set_var('have_editlink', '');
            }

            $T->parse('QRow','QueueRow',true);

            $i++;
        }
    }
    $T->set_var('ad_data', $ad_data);

    $T->parse('output','manage');
    return $T->finish($T->get_var('output'));

}   // function adList()


/**
*   Find the total number of ads for a category, including subcategories
*
*   @param  integer $id CategoryID
*   @return integer Total Ads
*/
function findTotalAds($id)
{
    global $_TABLES;

    $time = time();     // to compare to ad expiration
    $totalAds = 0;

    // find all the subcategories
    $sql = "
        SELECT
            cat_id
        FROM
            {$_TABLES['ad_category']}
        WHERE
            papa_id=$id
    ";
    $result = DB_query($sql);
    if (!$result) return CLASSIFIEDS_errorMsg($LANG_ADVT['database_error'], 'alert');

    // If there are subcategories, call this function recursively for each
    // one.
    while ($row = DB_fetchArray($result)) {
        $totalAds += findTotalAds($row['cat_id']);
    }

    // Now add in the count for this category itself
    $sql = "
        SELECT
            cat_id
        FROM
            {$_TABLES['ad_ads']}
        WHERE
            cat_id=$id
        AND
            exp_date>$time "
        . COM_getPermSQL('AND', 0, 2);

    //echo $sql."<br />\n";
    $totalAds += DB_numRows(DB_query($sql));

    return $totalAds;

}   // function findTotalAds()


/**
*   Create the breadcrumb display, with links
*   @param  integer $id ID of current category
*   @return string      Location string ready for display
*   @deprecated
*/
function X_displayLocation($id)
{
    global $cat, $_TABLES, $_CONF, $_CONF_ADVT;

    $location = '';
    $base_url = "{$_CONF['site_url']}/{$_CONF_ADVT['pi_name']}/index.php";

    $sql = "
        SELECT
            cat_name, cat_id, papa_id
        FROM
            {$_TABLES['ad_category']}
        WHERE
            cat_id=$id
    ";
    $result = DB_query($sql);
    if (!$result) return CLASSIFIEDS_errorMsg($LANG_ADVT['database_error'], 'alert');

    $row = DB_fetchArray( $result );

    if ($row['papa_id'] == 0) {
        if ($row['cat_id'] == $cat) {
             $location .= 
                '<a href="'.
                CLASSIFIEDS_makeURL('home'). '">Home</a> :: ' .
                $row['cat_name'];
        } else {
            $location .= 
                '<a href="'. CLASSIFIEDS_makeURL('home'). '">Home</a> :: ' .
                '<a href="'. CLASSIFIEDS_makeURL('home', 
                                    "cat_id={$row['cat_id']}") . 
                '">' . $row['cat_name'] . '</a>';
        }
    } else {
        $location .= displayLocation($row['papa_id']);
        if($row['cat_id'] == $cat) {
            $location .= " &gt; {$row['cat_name']}";
        } else {
            $location .= 
                ' &gt; <a href="' . 
                CLASSIFIEDS_makeURL('home', $row['cat_id']). '">' .
                $row['cat_name'] . '</a>';
        }

    }

    return "<b>$location</b>\n";
}


/**
*  Calls itself recursively to find the root category of the requested id
*
*  @param   integer $id  Category ID
*  @return  integer      Root Category ID
*   @deprecated
*/
function findCatRoot($id)
{
    global $_TABLES;

    // Get the papa_id of the current id
    $result = DB_query("
        SELECT 
            cat_id, papa_id 
        FROM 
            {$_TABLES['ad_category']} 
        WHERE 
            cat_id=$id
    ");
    if (!$result) return CLASSIFIEDS_errorMsg($LANG_ADVT['database_error'], 'alert');

    $row = DB_fetchArray( $result );

    if (DB_numRows($result) != 0)
        findCatRoot($row['papa_id']);

    return $row['cat_id'];

}


function currentLocation($cat_id)
{
    global $_TABLES, $LANG_ADVT;

    $location = '';
    $cat_id = (int)$cat_id;

    $result = DB_query("
        SELECT 
            cat_name, cat_id, papa_id 
        FROM 
            {$_TABLES['ad_category']} 
        WHERE 
            cat_id=$cat_id
    ");
    if (!$result)
        return CLASSIFIEDS_errorMsg($LANG_ADVT['database_error'], 'alert');

    $row = DB_fetchArray( $result );

    if ($row['papa_id'] == 0)
    {
        $location .= 
            '<a href="'. CLASSIFIEDS_makeURL(''). '">'. 
                $LANG_ADVT['home']. '</a> :: '.
            '<a href="'. CLASSIFIEDS_makeURL('home', $row['cat_id']). '">' .
            "{$row['cat_name']}</a>";
    }
    else
    {
        $location .= currentLocation($row['papa_id']);
        $location .= 
            ' &gt; <a href="'. 
            CLASSIFIEDS_makeURL('home', $row['cat_id']). '">' .
            "{$row['cat_name']}</a>";
    }
    return "      <b>$location</b>\n";
}


/**
*   Returns an error message formatted for on-screen display.
*   @param  string  $msg    Error message text
*   @param  string  $type   Error type or severity
*   @param  string  $hdr    Optional text to appear in the header.
*   @return string          HTML code for the formatted message
*/
function CLASSIFIEDS_errorMsg($msg='', $type='info', $hdr='')
{
    if ($msg== '')
        return '';

    if ($hdr = '')
        $hdr = 'Error';

    switch ($type) {
        case 'alert':
            $hdr = 'Alert';
            $class = 'alert';
            break;
        case 'note':
            $hdr = 'Note';
            $class = 'note';
            break;
        case 'info':
        default:
            $hdr = 'Information';
            $class = 'info';
            break;
    }

    $display = "<span class=\"$class\">";
    $display .= COM_startBlock($hdr);
    $display .= $msg;
    $display .= COM_endBlock();
    $display .= "</span>";
    return $display;
}


function displayCat($cat_id)
{
    global $_TABLES, $_CONF, $_CONF_ADVT;

    $pi_base_url = $_CONF['site_url'] . '/' . $_CONF_ADVT['pi_name'];
    $cat_id = intval($cat_id);

    //display small cat root
    $sql = "
        SELECT
            cat_name, cat_id, papa_id
        FROM
            {$_TABLES['ad_category']}
        WHERE
            cat_id=$cat_id
    ";
    $result = DB_query($sql); 
    if (!$result) return CLASSIFIEDS_errorMsg($LANG_ADVT['database_error'], 'alert');
    $row = DB_fetchArray($result);

    if ($row['papa_id'] == 0) {
        $location = 
            '<a href="' .
            CLASSIFIEDS_makeURL('home', $row['cat_id']). '">' .
            "{$row['cat_name']}</a>";
    } else {
        $location = displayCat($row['papa_id']) .
            ' &gt; <a href="' .
            CLASSIFIEDS_makeURL('home', $row['cat_id']). '">' .
            "{$row['cat_name']}</a>";
//        displayCat($row['papa_id']);
    }

    return "<span class=\"adDisplayCat\">$location</span>\n";

}   // function displayCat()


/**
*   Recurse through the category table building an option list
*   sorted by id.
*
*   @param integer  $papa_id Parent category ID
*   @param string   $char    Separator characters
*   @param integer  $sel     Category ID to be selected in list
*/
/*function buildCatSelection($char='', $papa_id=0, $sel=0)
{
    global $_TABLES;

    $str = '';

    // Locate the parent category of this one
    $sql = "
        SELECT
            cat_id, cat_name
        FROM
            {$_TABLES['ad_category']}
        WHERE
            papa_id = $papa_id
        ORDER BY
            cat_name
                ASC
    ";
    $result = DB_query($sql);
    // If there is no parent, just return.
    if (!$result)
        return '';

    while ($row = DB_fetchArray($result)) {
        $selected = $row['cat_id'] == $sel ? "selected" : "";
        
        $str .= "<option value={$row['cat_id']} $selected>
            $char{$row['cat_name']}</option>\n";
        $str .= buildCatSelection($char."-", $row['cat_id'], $sel);
    }

    return $str;
 
}   // function buildCatSelection()
*/


/**
*   Get the user record for the current user
*   @param  string  $userid   User ID to retrieve, blank for current user
*   @return array User record from GL users table
*   @deprecated
*/
function X_CLASSIFIEDS_getUser($userid = 0)
{
    global $_USER, $_TABLES;

    if ($userid == 0)
        $userid = $_USER['uid'];
    $userid = (int)$userid;

   $result = DB_query("
        SELECT 
            * 
        FROM 
            {$_TABLES['users']} 
        WHERE uid='$userid'
    ");
    if (!$result) 
        return CLASSIFIEDS_errorMsg($LANG_ADVT['database_error'], 'alert');

    $user = DB_fetchArray($result);
    return $user;
}


/**
*   Get an array of user info from the plugin's userinfo table
*   @param  string  $userid User ID to retrieve.  Blank for current user
*   @return array user info from our uinfo table
*   @deprecated
*/
function X_CLASSIFIEDS_getUserInfo($userid = 0)
{
    global $_USER, $_TABLES;

    if ($userid == 0)
        $userid = $_USER['uid'];
    $userid = (int)$userid;

    $result = DB_query("
        SELECT 
            * 
        FROM 
            {$_TABLES['ad_uinfo']} 
        WHERE uid='$userid'
    ");
    if (!$result)
        return CLASSIFIEDS_errorMsg($LANG_ADVT['database_error'], 'alert');

    $userinfo = DB_fetchArray($result);
    return $userinfo;
}


/**
*   Returns the results of SEC_hasRights, 3=rw, 2=ro
*
*   @param  string  $id     Ad ID
*   @param  array   $ad     Ad info, if already available
*   @return int             Access value
*/
function CLASSIFIEDS_checkAccess($id, $A = '')
{
    global $_TABLES, $_CONF_ADVT;
    $id = COM_sanitizeID($id, false);
    if ($id == '')
        return 0;

    // Admin rights trump admin-specific ones
    if (SEC_hasRights($_CONF_ADVT['pi_name']. '.admin'))
        return 3;

    if (!is_array($A)) {
        $sql = "SELECT owner_id, group_id,
                perm_owner, perm_group, perm_members, perm_anon
            FROM {$_TABLES['ad_ads']}
            WHERE ad_id='$id'";
        //echo $sql;die;
        $result = DB_query($sql);
        if (!$result || DB_numRows($result) == 0) {
            return 0;
        }
        $A = DB_fetchArray($result);
    }

    $my_access = SEC_hasAccess($A['owner_id'], $A['group_id'], 
                $A['perm_owner'], $A['perm_group'], 
                $A['perm_members'], $A['perm_anon']);

    return $my_access;
}

/**
*   Displays a message indicating that the user must log in.
*   Does not return a value, simply displays the message and exits.
*/
function CLASSIFIEDS_LoginRequired()
{
    global $_USER, $_CONF, $_CONF_ADVT, $LANG_LOGIN;

    $display = COM_startBlock ($LANG_LOGIN[1], '',
            COM_getBlockTemplate ('_msg_block', 'header'));
    $loginreq = new Template($_CONF['path_layout'] . 'submit');
    $loginreq->set_file('loginreq', 'submitloginrequired.thtml');
    $loginreq->set_var('xhtml', XHTML);
    $loginreq->set_var('site_url', $_CONF['site_url']);
    $loginreq->set_var('site_admin_url', $_CONF['site_admin_url']);
    $loginreq->set_var('layout_url', $_CONF['layout_url']);
    $loginreq->set_var('login_message', $LANG_LOGIN[2]);
    $loginreq->set_var('lang_login', $LANG_LOGIN[3]);
    $loginreq->set_var('lang_newuser', $LANG_LOGIN[4]);
    $loginreq->parse('errormsg', 'loginreq');
    $display .= $loginreq->finish($loginreq->get_var('errormsg'));
    $display .= COM_endBlock (COM_getBlockTemplate ('_msg_block', 'footer'));
    $display .= COM_siteFooter(true);

    echo $display;
    exit;

}


/**
*   Updates the ad with a new expiration date.  $days (in seconds)
*   is added to the original expiration date.
*
*   @param integer  $id     ID number of ad to update
*   @param integer  $days   Number of days to add
*/
function CLASSIFIEDS_addDays($id = '', $days = 0)
{
    global $_USER, $_CONF, $_CONF_ADVT, $_TABLES;

    $id = COM_sanitizeID($id);
    $days = intval($days);
    if ($id == '' || $days == 0) return;

    $sql = "SELECT
            owner_id, group_id,
            perm_owner, perm_group, perm_members, perm_anon,
            add_date, exp_date
        FROM
            {$_TABLES['ad_ads']}
        WHERE
            ad_id='". DB_escapeString($id). "'";
    $r = DB_query($sql);
    if (!$r || DB_numRows($r) < 1) return;
    $ad = DB_fetchArray($r);

    if (CLASSIFIEDS_checkAccess($id, $ad) < 3)
        return;

    $add_days = min(CLASSIFIEDS_calcMaxAddDays(
        ($ad['exp_date'] - $ad['add_date'])/86400), $days);
    if ($add_days <= 0) return;

    $new_exp_date = $ad['exp_date'] + ($add_days * 86400);

    // Finally, we have access to this add and there's a valid number
    // of days to add.
    DB_query("UPDATE {$_TABLES['ad_ads']} SET
            exp_date=$new_exp_date,
            exp_sent=0
        WHERE ad_id='$id'");
}


/**
*   Returs the max number of days that may be added to an ad.
*   Considers the configured maximum runtime and the time the ad
*   has already run.
*
*   @param int $rundays Number of days ad is already scheduled to run
*   @return int Max number of days that can be added
*/
function CLASSIFIEDS_calcMaxAddDays($rundays)
{
    global $_CONF_ADVT;

    // How many days has the ad run?
    $run_days = intval($rundays);
    if ($run_days <= 0) return 0;

    $max_add_days = intval($_CONF_ADVT['max_total_duration']);

    if ($max_add_days < $run_days) 
        return 0;
    else 
        return ($max_add_days - $run_days);

}

/**
*   Recurse through the category table building an option list
*   sorted by id.
*
*   @param integer  $sel        Category ID to be selected in list
*   @param integer  $papa_id    Parent category ID
*   @param string   $char       Separator characters
*   @param string   $not        'NOT' to exclude $items, '' to include
*   @param string   $items      Optional comma-separated list of items to include or exclude
*   @return string              HTML option list, without <select> tags
*/
function CLASSIFIEDS_buildCatSelection($sel=0, $papa_id=0, $char='', $not='', $items='')
{
    global $_TABLES, $_GROUPS;

    $str = '';

    // Locate the parent category of this one, or the root categories
    // if papa_id is 0.
    $sql = "
        SELECT
            cat_id, cat_name, papa_id,
            owner_id, group_id,
            perm_owner, perm_group, perm_members, perm_anon
        FROM
            {$_TABLES['ad_category']}
        WHERE
            papa_id = $papa_id ";
    if (!empty($items)) {
        $sql .= " AND cat_id $not IN ($items) ";
    }
    $sql .= COM_getPermSQL('AND'). "
        ORDER BY
            cat_name
                ASC
    ";
    //echo $sql;die;
    $result = DB_query($sql);
    // If there is no parent, just return.
    if (!$result)
        return '';

    while ($row = DB_fetchArray($result)) {
        $txt = $char. $row['cat_name'];
        $selected = $row['cat_id'] == $sel ? "selected" : "";
        if ($row['papa_id'] == 0) {
            $style = 'style="background-color:lightblue"';
        } else {
            $style = '';
        }
        if (SEC_hasAccess($row['owner_id'], $row['group_id'],
                $row['perm_owner'], $row['perm_group'], 
                $row['perm_members'], $row['perm_anon']) < 3) {
            $disabled = 'disabled="true"';
        } else {
            $disabled = '';
        }

        $str .= "<option value={$row['cat_id']} $style $selected $disabled>";
        $str .= $txt;
        $str .= "</option>\n";
        $str .= CLASSIFIEDS_buildCatSelection($sel, $row['cat_id'], $char."-", $not, $items);
    }

    //echo $str;die;
    return $str;

}   // function CLASSIFIEDS_buildCatSelection()


/**
*   Insert or update an ad with form values.  Setting $admin to true
*   allows ads to be saved on behalf of another user.
*
*   @param string  $savetype Save action to perform
*   @return array
*      [0] = string value of page to redirect to
*      [1] = content of any error message or text
*/
function adSave($savetype='edit')
{
    global $_TABLES, $_CONF_ADVT, $_USER, $_CONF, $LANG_ADVT, $LANG12;
    global $LANG_ADMIN;

    $admin = SEC_hasRights($_CONF_ADVT['pi_name']. '.admin');

    // Sanitize form variables.  There should always be an ad id defined
    $A = array();
    if (isset($_POST['ad_id'])) {
        $A['ad_id']  = COM_sanitizeID($_POST['ad_id'], false);
    } elseif (isset($_POST['id'])) {
        $A['ad_id']  = COM_sanitizeID($_POST['id'], false);
    }
    if ($A['ad_id'] == '')
        return array(CLASSIFIEDS_URL, 'Missing Ad ID');

    // Make sure the current user can edit this ad.
    if (CLASSIFIEDS_checkAccess($A['ad_id']) < 3) {
        return array();
    }

    $A['subject'] = trim($_POST['subject']);
    $A['descript'] = trim($_POST['descript']);
    if ($_POST['postmode'] == 'plaintext') {
        $A['descript'] = nl2br($A['descript']);
    }
    $A['price'] = trim($_POST['price']);
    $A['url'] = COM_sanitizeUrl($_POST['url'],
            array('http','https'),'http');
    $A['catid'] = (int)$_POST['catid'];
    $A['ad_type'] = (int)$_POST['ad_type'];
    $A['keywords'] = trim($_POST['keywords']);
    $A['add_date'] = COM_applyFilter($_POST['add_date'], true);
    $A['exp_date'] = COM_applyFilter($_POST['exp_date'], true);
    if ($A['exp_date'] == 0) $A['exp_date'] = $A['add_date'];
    $A['exp_sent'] = (int)$_POST['exp_sent'] == 1 ? 1 : 0;
    $A['owner_id'] = (int)$_POST['owner_id'];
    $A['group_id'] = (int)$_POST['group_id'];
    $A['uid'] = $A['owner_id'];
    $A['comments_enabled'] = (int)$_POST['comments_enabled'];

    switch ($savetype) {
        case 'moderate':
        case 'adminupdate':
        case 'savesubmission':
        case 'editsubmission':
        case 'submission':
            $perms = SEC_getPermissionValues($_POST['perm_owner'],
                $_POST['perm_group'], $_POST['perm_members'],
                $_POST['perm_anon']);
            $A['perms'] = $perms;
            break;
        case $LANG_ADMIN['save']:
        case $LANG12[8];
        default:
            $A['perms'] = array(
                (int)$_POST['perm_owner'],
                (int)$_POST['perm_group'],
                (int)$_POST['perm_members'],
                (int)$_POST['perm_anon'],
            );
            break;
    }

    // Set anon permissions according to category if not an admin.
    // To avoid form injection.
    if (!$admin && DB_getItem($_TABLES['ad_category'], 
            'perm_anon', "cat_id='{$A['cat_id']}'") == '0') {
        $A['perms'][3] = 0;
    }

    $photo      = $_FILES['photo'];
    $moredays   = COM_applyFilter($_POST['moredays'], true);
    if ($_CONF_ADVT['purchase_enabled'] && !$admin) {
        // non-administrator is limited to the available days on account,
        // if applicable.
        USES_classifieds_class_userinfo();
        $User = new adUserInfo();
        $moredays = min($moredays, $User->getMaxDays());
    }

    // Validate some fields.
    $errmsg = '';
    if ($A['subject'] == '')
        $errmsg .= "<li>{$LANG_ADVT['subject_required']}</li>";
    if ($A['descript'] == '')
        $errmsg .= "<li>{$LANG_ADVT['description_required']}</li>";

    if ($errmsg != '') {
        $errmsg = "<span class=\"alert\"><ul>$errmsg</ul></span>\n";
        // return to edit page so user can correct
        return array(1, $errmsg);
        //return $errmsg;
    }

    // Calculate the new number of days. For an existing ad start from the
    // date added, if new then start from now.  If the ad has already expired,
    // then $moredays will be added to now() rather than exp_date.
    if ($moredays > 0) {
        $moretime = $moredays * 86400;
        $save_exp_date = $A['exp_date'];
        if ($A['exp_date'] < time())
            $basetime = time();
        else
            $basetime = $A['exp_date'];

        $A['exp_date'] = min(
            $basetime + $moretime,
            $A['add_date'] + (intval($_CONF_ADVT['max_total_duration']) * 86400));

        // Figure out the number of days added to this ad, and subtract
        // it from the user's account.
        $days_used = (int)(($A['exp_date'] - $save_exp_date) / 86400);
        if ($_CONF_ADVT['purchase_enabled'] && !$admin) {
            $User->UpdateDaysBalance($days_used * -1);
        }

        // Reset the "expiration notice sent" flag if the new date is at least
        // one more day from the old one.
        //if ($A['exp_date'] - $save_exp_date >= 86400) {
        if ($days_used > 0) {
            $A['exp_sent'] = 0;
        }
    }

    //$errmsg .= CLASSIFIEDS_UploadPhoto($A['ad_id'], $_FILES['photo']);
    $errmsg .= CLASSIFIEDS_UploadPhoto($A['ad_id'], 'photo');
    if ($errmsg != '') {
        // Display the real error message, if there is one
        return array(1, "<span class=\"alert\"><ul>$errmsg</ul></span>\n");
        //return "<span class=\"alert\"><ul>$errmsg</ul></span>\n";
    } 

    if ( ($savetype == 'moderate' || $savetype == 'editsubmission' || 
            $savetype == 'submission' ) && plugin_ismoderator_classifieds()) {
        // If we're editing a submission, delete the submission item
        // after moving data to the main table
        $status = CLASSIFIEDS_insertAd($A, 'ad_ads');
        if ($status == NULL ) {
            DB_delete($_TABLES['ad_submission'], 'ad_id', $A['ad_id']);
        } else {
            $errmsg = $status;
        }

        // Now we've duplicated most functions of the moderator approval,
        // so call the plugin_ function to do the same post-approval stuff
        plugin_moderationapprove_classifieds($A['ad_id'], $A['owner_id']);

    } elseif (CLASSIFIEDS_checkAccess($A['ad_id']) == 3) {
        CLASSIFIEDS_updateAd($A);
    } else {
        return array(1, "Acess Denied");
    }

    //$errmsg = COM_showMessage('02', $_CONF_ADVT['pi_name']);
    //$errmsg = '';
    if ($errmsg == '')
        return array(0, '02');
    else
        return array(1, $errmsg);
    //return $errmsg;

}   // function adSave()


/**
*   Gets the correct template depending on what type of display
*   is being used.  Currently supports the new "blocks" display and the
*   old zClassifieds-style display
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


/**
*   Strips slashes if magic_quotes_gpc is on.
*
*   @param  mixed   $var    Value or array of values to strip.
*   @return mixed           Stripped value or array of values.
*/
function CLASSIFIEDS_stripslashes($var)
{
	if (get_magic_quotes_gpc()) {
		if (is_array($var)) {
			return array_map('CLASSIFIEDS_stripslashes', $var);
		} else {
			return stripslashes($var);
		}
	} else {
		return $var;
	}
}


/**
*   Get a form or URL parameter.
*   Checks $_POST, then $_GET, and returns the raw value if found.
*   Returns NULL if the parameter is not set
*/
function CLASSIFIEDS_getParam($name)
{
    if (isset($_POST[$name])) {
        $value = $_POST[$name];
    } elseif (isset($_GET[$name])) {
        $value = $_GET[$name];
    } else {
        $value = NULL;
    }
    return $value;
}

?>
