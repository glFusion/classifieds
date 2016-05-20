<?php
/**
*   Public entry point for the Classifieds plugin.
*
*   @author     Lee Garner <lee@leegarner.com>
*   @copyright  Copyright (c) 2009 Lee Garner <lee@leegarner.com>
*   @package    classifieds
*   @version    1.0.4
*   @license    http://opensource.org/licenses/gpl-2.0.php 
*               GNU Public License v2 or later
*   @filesource
*/

/** Import core glFusion libraries */
require_once '../lib-common.php';

// If plugin is installed but not enabled, display an error and exit gracefully
if (!in_array('classifieds', $_PLUGINS)) {
    COM_404();
    exit;
}

USES_classifieds_advt_functions();

// Clean $_POST and $_GET, in case magic_quotes_gpc is set
if (GVERSION < '1.3.0') {
    $_POST = CLASSIFIEDS_stripslashes($_POST);
    $_GET = CLASSIFIEDS_stripslashes($_GET);
}

// Determine if this is an anonymous user, and override the plugin's
// loginrequired configuration if the global loginrequired is set.
$isAnon = COM_isAnonUser();
if ($_CONF['loginrequired'] == 1)
    $_CONF_ADVT['loginrequired'] = 1;

if ($isAnon && $_CONF_ADVT['loginrequired'] == 1) {
    $display = CLASSIFIEDS_siteHeader();
    $display .= COM_startBlock ($LANG_LOGIN[1], '',
            COM_getBlockTemplate ('_msg_block', 'header'));
    $loginreq = new Template($_CONF['path_layout'] . 'submit');
    $loginreq->set_file('loginreq', 'submitloginrequired.thtml');
    $loginreq->set_var('xhtml', XHTML);
    $loginreq->set_var('layout_url', $_CONF['layout_url']);
    $loginreq->set_var('login_message', $LANG_LOGIN[2]);
    $loginreq->set_var('lang_login', $LANG_LOGIN[3]);
    $loginreq->set_var('lang_newuser', $LANG_LOGIN[4]);
    $loginreq->parse('errormsg', 'loginreq');
    $display .= $loginreq->finish($loginreq->get_var('errormsg'));
    $display .= COM_endBlock (COM_getBlockTemplate ('_msg_block', 'footer'));
    $display .= CLASSIFIEDS_siteFooter(true);

    echo $display;
    exit;
}

// Retrieve and sanitize input variables.  Typically _GET, but may be _POSTed.
COM_setArgNames(array('mode', 'id', 'page', 'query'));

// Get any message ID
if (isset($_REQUEST['msg'])) {
    $msg = COM_applyFilter($_REQUEST['msg']);
} else {
    $msg = '';
}

if (isset($_REQUEST['mode'])) {
    $mode = COM_applyFilter($_REQUEST['mode']);
} else {
    $mode = COM_getArgument('mode');
}
if (isset($_REQUEST['id'])) {
    $id = COM_sanitizeID($_REQUEST['id']);
} else {
    $id = COM_applyFilter(COM_getArgument('id'));
}
$page = COM_getArgument('page');

// Assume that the 'mode' is also (or only) the desired page to display
//if (empty($mode)) $id='';
if (empty($page)) $page = $mode;

// Set up the basic menu for all users
$menu_opt = '';
USES_class_navbar();
$menu = new navbar();
$menu->add_menuitem($LANG_ADVT['mnu_home'], CLASSIFIEDS_makeURL('home'));
$menu->add_menuitem($LANG_ADVT['mnu_recent'], CLASSIFIEDS_makeURL('recent'));

// Show additional menu options to logged-in users
if (!$isAnon) {
    $menu->add_menuitem($LANG_ADVT['mnu_account'], CLASSIFIEDS_makeURL('account'));
    $menu->add_menuitem($LANG_ADVT['mnu_myads'], CLASSIFIEDS_makeURL('manage'));
}
if (CLASSIFIEDS_canSubmit()) {
    $menu->add_menuitem($LANG_ADVT['mnu_submit'], $_CONF['site_url']. 
            '/submit.php?type='. $_CONF_ADVT['pi_name']);
}

// Set the help option as the last menu item
if (!empty($_CONF_ADVT['helpurl']))
    $menu->add_menuitem($LANG_ADVT['mnu_help'], COM_sanitizeURL($_CONF_ADVT['helpurl']));

// Establish the output template
$T = new Template(CLASSIFIEDS_PI_PATH . '/templates');
$T->set_file('page','index.thtml');
$T->set_var('site_url',$_CONF['site_url']);
if (isset($LANG_ADVT['index_msg']) && !empty($LANG_ADVT['index_msg'])) {
    $T->set_var('index_msg', $LANG_ADVT['index_msg']);
}

$content = '';

// Start by processing the specified action, if any
switch ($mode) {

    case $LANG_ADMIN['delete']:
        if (empty($LANG_ADMIN['delete'])) break;
        if ($id > 0) {
            adDelete($id);
        }
        $page = 'manage';
        break;

    case 'delete_ad':           // DEPRECATE
        if ($id > 0) {
            adDelete($id);
        }
        $page = 'manage';
        break;

    case 'update_account':
        // only valid users allowed
        if ($isAnon) {
            $content .= CLASSIFIEDS_errorMsg($LANG_ADVT['login_required'], 
                    'alert', 
                    $LANG_ADVT['access_denied']);
            break;
        }

        USES_classifieds_class_userinfo();
        $U = new adUserInfo($_USER['uid']);
        $U->SetVars($_POST);
        $U->Save();
        $page = $page == '' ? 'account' : $page;
        break;

    case 'update_ad':
        $r = adSave($mode);
        $content .= $r[1];
        $page = 'manage';
        break;

    case 'savesubmission':
    case 'save':
    case $LANG_ADMIN['save']:
    case $LANG12[8]:
        $r = adSave($mode);
        if ($r[0] == 0)
            $content .= COM_showMessage($r[1], $_CONF_ADVT['pi_name']);
        else
            $content .= $r[1];
        $page = 'manage';
        break;

    case 'delete_img':
        USES_classifieds_edit();
        $content .= imgDelete(false);
        $page = 'editad';
        break;

    case 'add_notice':
        $cat = (int)$id;
        if ($cat > 0) {
            USES_classifieds_notify();
            catSubscribe($cat);
        }
        break;

    case 'del_notice':
        $cat = (int)$_REQUEST['cat_id'];
        if ($cat > 0) {
            USES_classifieds_notify();
            catUnSubscribe($cat);
        }
        if (!isset($page)) $page='account';
        break;

    case 'moredays':
        $days = COM_applyFilter($_POST['add_days'], true);
        CLASSIFIEDS_addDays($id, $days);
        $page = 'manage';
        break;

}   // switch ($mode)


// After the action is finished, display the requested page
switch ($page) {

    case 'recent':
        //  Display recent ads
        USES_classifieds_list();
        $content .= adListRecent();
        $T->set_var('header', $LANG_ADVT['recent_listed']);
        $menu_opt = $LANG_ADVT['mnu_recent'];
        break;

    case 'manage':
        // Manage ads.  Restricted to the user's own ads
        if ($isAnon) {
            $content .= CLASSIFIEDS_errorMsg($LANG_ADVT['login_required'], 'note');
        } else {
            $content .= adList(false);
        }
        $T->set_var('header', $LANG_ADVT['ads_mgnt']);
        $menu_opt = $LANG_ADVT['mnu_myads'];
        break;

    case 'account':
        // Update the user's account info
        // only valid users allowed
        if ($isAnon) {
            $content .= CLASSIFIEDS_errorMsg($LANG_ADVT['login_required'], 
                    'alert', 
                    $LANG_ADVT['access_denied']);
            break;
        }

        USES_classifieds_class_userinfo();
        $U = new adUserInfo();
        $content .= $U->showForm('advt');
        $T->set_var('header', $LANG_ADVT['my_account']);
        $menu_opt = $LANG_ADVT['mnu_account'];
        break;

    case 'detail':
        // Display an ad's detail
        USES_classifieds_detail();
        $content .= adDetail($id);
        $T->set_var('header', $LANG_ADVT['detail']);
        $menu_opt = $LANG_ADVT['mnu_home'];
        if ($id != '') {
            $pageTitle = 
                DB_getItem($_TABLES['ad_ads'], 'subject', "ad_id='$id'");
        }
        break;

    /*case 'help':
        // Display the help page.
        $content = showHelp();
        $menu_opt = $LANG_ADVT['mnu_help'];
        $T->set_var('header', $LANG_ADVT['mnu_help']);
        $pageTitle = $LANG_ADVT['mnu_help'];
        break;*/

    case 'editad':
        // Edit an ad.  
        $result = DB_query ("SELECT * 
                    FROM {$_TABLES['ad_ads']} 
                    WHERE ad_id ='$id'");
        if ($result && DB_numRows($result) == 1) {
            USES_classifieds_edit();
            $A = DB_fetchArray($result);
            $content .= adEdit($A, 'update_ad');
            $T->set_var('header', $LANG_ADVT['edit_ad']);
        } else {
            $content .= COM_showMessage('08', $_CONF_ADVT['pi_name']);
            $content .= adList(false);
            $T->set_var('header', $LANG_ADVT['ads_mgnt']);
        }
        $menu_opt = $LANG_ADVT['mnu_myads'];
        break;

    case 'byposter':
        // Display all open ads for the specified user ID
        $uid = isset($_REQUEST['uid']) ? (int)$_REQUEST['uid'] : 0;
        if ($uid > 1) {
            USES_classifieds_list();
            $content .= adListPoster($uid);
        }
        $T->set_var('header', $LANG_ADVT['ads_by']. ' '. COM_getDisplayName($uid));
        $menu_opt = $LANG_ADVT['mnu_home'];
         break;

    case 'home':
    default:
        // Display either the categories, or the ads under a requested
        // category
        USES_classifieds_list();
        if ($id > 0) {
            $content .= adListCat($id);
            $pageTitle = DB_getItem($_TABLES['ad_category'], 'cat_name', 
                        "cat_id='$id'");
        } else {
            $content .= CLASSIFIEDS_catList();
        }
        $T->set_var('header', $LANG_ADVT['blocktitle']);
        $menu_opt = $LANG_ADVT['mnu_home'];
        break;

}   // switch ($page)

if ($menu_opt != '') $menu->set_selected($menu_opt);
$T->set_var('menu', $menu->generate());
$T->set_var('content', $content);
$T->parse('output', 'page');
echo CLASSIFIEDS_siteHeader($pageTitle);
if ($msg != '')
    echo  COM_showMessage($msg, $_CONF_ADVT['pi_name']);
echo $T->finish($T->get_var('output'));
echo CLASSIFIEDS_siteFooter();


function showHelp()
{
    global $LANG_ADVT, $_CONF;

    $retval = '';

    foreach ($LANG_ADVT['help'] as $section=>$content) {
        $retval .= "<h2>{$content[0]}</h2>\n<ol>\n";
        foreach($content[1] as $key=>$value) {
            $value = str_replace('{site_url}', $_CONF['site_url'], $value);
            $retval .= "<li>$value</li>\n";
        }
        $retval .= "</ol>\n";
    }

    return $retval;

}


?>
