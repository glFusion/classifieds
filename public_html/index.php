<?php
/**
 * Public entry point for the Classifieds plugin.
 *
 * @author      Lee Garner <lee@leegarner.com>
 * @copyright   Copyright (c) 2009-2020 Lee Garner <lee@leegarner.com>
 * @package     classifieds
 * @version     v1.3.0
 * @license     http://opensource.org/licenses/gpl-2.0.php
 *              GNU Public License v2 or later
 * @filesource
 */

/** Import core glFusion libraries */
require_once '../lib-common.php';

// If plugin is installed but not enabled, display an error and exit gracefully
if (!in_array('classifieds', $_PLUGINS)) {
    COM_404();
    exit;
}

// Determine if this is an anonymous user, and override the plugin's
// loginrequired configuration if the global loginrequired is set.
$isAnon = COM_isAnonUser();
if ($_CONF['loginrequired'] == 1) {
    $_CONF_ADVT['loginrequired'] = 1;
}
if ($isAnon && $_CONF_ADVT['loginrequired'] == 1) {
    $display = CLASSIFIEDS_siteHeader();
    $display .= SEC_loginForm();
    $display .= CLASSIFIEDS_siteFooter(true);
    echo $display;
    exit;
}
$pageTitle = '';

// Retrieve and sanitize input variables.  Typically _GET, but may be _POSTed.
COM_setArgNames(array('mode', 'id', 'query'));

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
if (empty($mode) && !empty($id)) {
    $mode = 'detail';
}

// Set up the basic menu for all users
$menu_opt = '';
//USES_class_navbar();
$menu = new navbar();
$menu->add_menuitem($LANG_ADVT['mnu_home'], CLASSIFIEDS_makeURL('home'));
$menu->add_menuitem($LANG_ADVT['mnu_recent'], CLASSIFIEDS_makeURL('recent'));

// Show additional menu options to logged-in users
if (!$isAnon) {
    $menu->add_menuitem($LANG_ADVT['mnu_account'], CLASSIFIEDS_makeURL('account'));
    $menu->add_menuitem($LANG_ADVT['mnu_myads'], CLASSIFIEDS_makeURL('manage'));
}
if (CLASSIFIEDS_canSubmit()) {
    $url = $_CONF_ADVT['url'] . '/index.php?mode=submit';
    if ($mode == 'home' && !empty($id)) {
        $url .= "&cat_id=$id";
    }
    $menu->add_menuitem($LANG_ADVT['mnu_submit'], $url);
}

// Establish the output template
$T = new Template($_CONF_ADVT['path'] . '/templates');
$T->set_file('page','index.thtml');
$T->set_var('site_url',$_CONF['site_url']);
if (isset($LANG_ADVT['index_msg']) && !empty($LANG_ADVT['index_msg'])) {
    $T->set_var('index_msg', $LANG_ADVT['index_msg']);
}

$content = '';

// Start by processing the specified action, if any
switch ($mode) {

case 'submit':
case 'edit':
    if ($isAnon) COM_404();
    $Ad = new \Classifieds\Ad($id);
    if (isset($_GET['cat_id']) && $Ad->isNew()) {
        $Ad->setCatID($_GET['cat_id']);
    }
    $content .= $Ad->Edit();
    break;

case 'delete':
case 'deletead':
    if ($isAnon) COM_404();
    if ($id != '') {
        $Ad = new \Classifieds\Ad($id);
        if ($Ad->canEdit()) {
            \Classifieds\Ad::Delete($id);
            $msg = '&msg=11';
        } else {
            $msg = '';
        }
        COM_refresh($_CONF_ADVT['url'] . '?mode=manage' . $msg);
    }
    $view = 'manage';
    break;

case 'update_account':
    // only valid users allowed
    if ($isAnon) COM_404();
    $U = new \Classifieds\UserInfo($_USER['uid']);
    $U->SetVars($_POST);
    $U->Save();
    $view = $view == '' ? 'account' : $view;
    break;

case 'update_ad':
echo 'update_ad DEPRECATED'; die;
    $r = adSave($mode);
    $content .= $r[1];
    $view = 'manage';
    break;

case 'save':
    if ($isAnon) COM_404();
    if (isset($_POST['ad_id']) && !empty($_POST['ad_id'])) {
        $id = $_POST['ad_id'];
    }
    $Ad = new \Classifieds\Ad($id);
    if ($Ad->Save($_POST)) {
        COM_refresh($_CONF_ADVT['url'] . '?msg=01');
    } else {
        COM_refresh($_CONF_ADVT['url'] . '?msg=12');
    }
    break;

case 'delete_img':
    if ($isAnon) COM_404();
    $Image = new \Classifieds\Image($_GET['img_id']);
    if ($Image->photo_id > 0) {
        $Image->Delete();
    }
    COM_refresh($_CONF_ADVT['url'] . '/index.php?mode=editad&id=' . $_GET['ad_id']);
    break;

case 'moredays':
    if ($isAnon) COM_404();
    $Ad = new \Classifieds\Ad($id);
    $Ad->addDays($_POST['add_days']);
    $view = 'manage';
    break;

case 'recent':
    //  Display recent ads
    $L = new \Classifieds\Lists\Ads\Recent();
    $content .= $L->Render();
    $T->set_var('header', $LANG_ADVT['recent_listed']);
    $menu_opt = $LANG_ADVT['mnu_recent'];
    break;

case 'manage':
    // Manage ads.  Restricted to the user's own ads
    if ($isAnon) COM_404();
    $content .= Classifieds\Ad::userList();
    $T->set_var('header', $LANG_ADVT['ads_mgnt']);
    $menu_opt = $LANG_ADVT['mnu_myads'];
    break;

case 'account':
    if ($isAnon) COM_404();
    $U = new \Classifieds\UserInfo();
    $content .= $U->showForm('advt');
    $T->set_var('header', $LANG_ADVT['my_account']);
    $menu_opt = $LANG_ADVT['mnu_account'];
    break;

case 'detail':
    // Display an ad's detail
    $Ad = new \Classifieds\Ad($id);
    $content .= $Ad->Detail();
    break;

case 'editad':
    // Edit an ad.
    if ($isAnon) COM_404();
    $Ad = new \Classifieds\Ad($id);
    $content .= $Ad->Edit();
    break;

case 'byposter':
    // Display all open ads for the specified user ID
    $L = new \Classifieds\Lists\Ads\byPoster($_GET['uid']);
    $content .= $L->Render();
    $T->set_var('header', $LANG_ADVT['ads_by']. ' '. COM_getDisplayName($uid));
    $menu_opt = $LANG_ADVT['mnu_home'];
    break;

case 'home':
default:
    // Display either the categories, or the ads under a requested
    // category
    //$T->set_var('header', $LANG_ADVT['blocktitle']);
    $L = new \Classifieds\Lists\Ads($id);
    $L->addCats(CLASSIFIEDS_getParam('cats', 'array'))
        ->addTypes(CLASSIFIEDS_getParam('types', 'array'));
    $content .= $L->Render();
    break;

    $C = new \Classifieds\Category($id);
    if ($C->getParentID() > 0) {
        // A sub-category, display the ads
        //$L = new \Classifieds\AdList_Cat($id);
        $L = new \Classifieds\Lists\Ads\byCat($id);
        $content .= $L->Render();
        $pageTitle = $L->getCat()->getName();
    } else {
        // The root category, display the sub-categories
        $content .= \Classifieds\Lists\Categories::Render();
    }
    $T->set_var('header', $LANG_ADVT['blocktitle']);
    $menu_opt = $LANG_ADVT['mnu_home'];
    break;

}   // switch ($mode)

if (!empty($view)) COM_refresh($_CONF_ADVT['url'] . "?mode=$view");

if ($menu_opt != '') $menu->set_selected($menu_opt);
//$T->set_var('menu', $menu->generate());
$T->set_var('menu', Classifieds\Menu::User($mode));
$T->set_var('content', $content);
$T->parse('output', 'page');
echo Classifieds\Menu::siteHeader($pageTitle);
if ($msg != '') {
    echo  COM_showMessage($msg, $_CONF_ADVT['pi_name']);
}
echo $T->finish($T->get_var('output'));
echo Classifieds\Menu::siteFooter();
exit;

?>
