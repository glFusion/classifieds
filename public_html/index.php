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
    $display = Classifieds\Menu::siteHeader();
    $display .= SEC_loginForm();
    $display .= Classifieds\Menu::siteFooter(true);
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

// Establish the output template
$T = new Template($_CONF_ADVT['path'] . '/templates');
$T->set_file('page','index.thtml');
$T->set_var('isAdmin', plugin_ismoderator_classifieds());
$T->set_var('pi_admin_url', $_CONF_ADVT['admin_url'] . '/index.php');
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
    $T->set_var('header', $LANG_ADVT['submit_an_ad']);
    break;

case 'delete':
case 'deletead':
    if ($isAnon) COM_404();
    if ($id != '') {
        $Ad = new \Classifieds\Ad($id);
        if ($Ad->canDelete()) {
            Classifieds\Ad::Delete($id);
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
    $view = 'account';
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

case 'manage':
    // Manage ads.  Restricted to the user's own ads
    if ($isAnon) COM_404();
    $content .= Classifieds\Ad::userList();
    $T->set_var('header', $LANG_ADVT['ads_mgnt']);
    break;

case 'account':
    if ($isAnon) COM_404();
    $U = new \Classifieds\UserInfo();
    $content .= $U->showForm('advt');
    $T->set_var('header', $LANG_ADVT['my_account']);
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
    $T->set_var('header', $LANG_ADVT['edit_an_ad']);
    break;

case 'home':
default:
    // Display the ad listing, possibly filtered by category and type
    // Pass category ID, if any, to the constructor
    $L = new \Classifieds\Lists\Ads($id);
    $L->addCats(CLASSIFIEDS_getParam('cats', 'array'))
        ->addTypes(CLASSIFIEDS_getParam('types', 'array'))
        ->setUid(CLASSIFIEDS_getParam('uid', 'int'));
    $content .= $L->Render();
    $T->set_var('header', $LANG_ADVT['blocktitle']);
    break;
}   // switch ($mode)

if (!empty($view)) {
    COM_refresh($_CONF_ADVT['url'] . "/index.php?mode=$view");
}

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
