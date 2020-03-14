<?php
/**
 * Entry point for Classifieds administrative functions.
 *
 * @author      Lee Garner <lee@leegarner.com>
 * @copyright   Copyright (c) 2009-2020 Lee Garner <lee@leegarner.com>
 * @package     classifieds
 * @version     v1.3.0
 * @license     http://opensource.org/licenses/gpl-2.0.php
 *              GNU Public License v2 or later
 * @filesource
 */

/** Import core glFusion libraries. */
require_once '../../../lib-common.php';
/** Import core authentication functions. */
require_once '../../auth.inc.php';

// If plugin is installed but not enabled, display an error and exit gracefully
if (!in_array('classifieds', $_PLUGINS)) {
    COM_404();
    exit;
}

//USES_lib_admin();

// Only let admin users access this page
if (!SEC_hasRights('classifieds.admin')) {
    // Someone is trying to illegally access this page
    COM_errorLog("Someone has tried to illegally access the classifieds Admin page.  User id: {$_USER['uid']}, Username: {$_USER['username']}, IP: $REMOTE_ADDR",1);
    COM_404();
    exit;
}
$admin_mode = '';

$action = '';
$expected = array(
    'edit', 'moderate', 'save', 'deletead', 'deleteimage',
    'deleteadtype', 'saveadtype', 'editadtype', 'editad', 'dupad',
    'deletecat', 'editcat', 'savecat', 'delbutton_x', 'resetcatperms',
    'ads', 'types', 'categories', 'other',
    'cancel', 'admin',
);
foreach($expected as $provided) {
    if (isset($_POST[$provided])) {
        $action = $provided;
        $actionval = $_POST[$provided];
        break;
    } elseif (isset($_GET[$provided])) {
        $action = $provided;
        $actionval = $_GET[$provided];
        break;
    }
}

$ad_id = CLASSIFIEDS_getParam('ad_id', 'string');
if ($ad_id === NULL) {
    $ad_id = CLASSIFIEDS_getParam('id', 'string');
}

// Set the view to be displayed.  May be overridden during execution of $action
$view = CLASSIFIEDS_getParam('page');
if ($view === NULL) {
    $view = $action;
}

$content = '';      // initialize variable for page content
$A = array();       // initialize array for form vars

switch ($action) {
case 'deleteimage': // delete an image
    $Image = new \Classifieds\Image($actionval);
    $Image->Delete();
    $actionval = $ad_id;
    $view = 'editad';
    break;

case 'deletecat':   // delete a single category
    $cat_id = CLASSIFIEDS_getParam('cat_id', 'int');
    if ($cat_id > 0) {
        \Classifieds\Category::Delete($_REQUEST['cat_id']);
        $view = 'admin';
    }
    break;

case 'delbutton_x':
    foreach ($_POST['delitem'] as $ad_id) {
        \Classifieds\Ad::Delete($ad_id);
    }
    COM_refresh($_CONF_ADVT['admin_url'] . '/index.php?admin=ad');
    break;

case 'deletead':
    $ad_id = $actionval;
    $type = CLASSIFIEDS_getParam('type', 'string');
    if ($type == 'submission' || $type == 'editsubmission' ||
            $type == 'moderate') {
        CLASSIFIEDS_auditLog("Deleting submission $ad_id");
        \Classifieds\Ad::Delete($ad_id, 'ad_submission');
        echo COM_refresh($_CONF['site_admin_url'] . '/moderation.php');
        exit;
    } else {
        \Classifieds\Ad::Delete($ad_id);
        echo COM_refresh($_CONF_ADVT['admin_url'] . '/index.php');
        exit;
    }
    break;

case 'saveadtype':
    $type_id = CLASSIFIEDS_getParam('type_id', 'int');
    $AdType = new \Classifieds\AdType($type_id);
    if (!$AdType->Save($_POST)) {
        COM_errorLog("Error saving ad type");
        COM_errorLog("Type info:" . print_r($AdType,true));
    }
    COM_refresh($_CONF_ADVT['admin_url'] . '/index.php?types');
    break;

case 'deleteadtype':
    $type_id = CLASSIFIEDS_getParam('type_id', 'int');
    $AdType = new \Classifieds\AdType($type_id);
    $view = 'admin';
    $actionval = 'type';
    if ($AdType->isUsed()) {
        if (!isset($_POST['newadtype'])) {
            $view = 'delAdTypeForm';
            break;
        } elseif (isset($_POST['submit'])) {
            $new_type = (int)$_POST['newadtype'];
            DB_query("UPDATE {$_TABLES['ad_ads']}
                        SET ad_type=$new_type
                        WHERE ad_type=$ad_id");
        } else {
            break;
        }
    }
    $AdType->Delete();
    COM_refresh($_CONF_ADVT['admin_url'] . '/index.php?admin=type');
    break;

case 'savecat':
    // Insert or update a category record from form vars
    $cat_id = CLASSIFIEDS_getParam('cat_id', 'int');
    $C = new \Classifieds\Category($cat_id);
    $C->Save($_POST);
    echo COM_refresh($_CONF['site_admin_url'] . '/plugins/classifieds/index.php?categories');
    $view = 'admin';
    $actionval = 'cat';
    break;

case 'delcat':
    // Insert or update a category record from form vars
    \Classifieds\Category::DeleteMulti($_POST['c']);
    $view = 'admin';
    $actionval = 'cat';
    break;

case 'delcatimg':
    // Delete a category image
    $cat_id = CLASSIFIEDS_getParam('cat_id', 'int');
    if ($cat_id > 0) {
        \Classifieds\Category::DelImage($cat_id);
    }
    $view = 'editcat';
    break;

case 'save':
    if ($_POST['type'] == 'submission') {   // approving a submission
        $Ad = new \Classifieds\Ad($ad_id, 'ad_submission');
        $Ad->setIsNew(true)->$Ad->setTable('ad_ads');
        $status = $Ad->Save($_POST);
        if ($status) {
            DB_delete($_TABLES['ad_submission'], 'ad_id', $ad_id);
            plugin_moderationapprove_classifieds($ad_id);
            echo COM_refresh($_CONF_ADVT['admin_url']);
        } else {
            echo COM_refresh($_CONF['site_url'] . '/admin/moderation.php');
        }
        exit;
    } else {
        $Ad = new \Classifieds\Ad($ad_id);
        $status = $Ad->Save($_POST);
        if ($status) {
            echo COM_refresh($_CONF_ADVT['admin_url']);
            exit;
        } else {
            $view = 'edit';
        }
    }
   break;

case 'dupad':
    $Ad = new \Classifieds\Ad($actionval);
    $msg = $Ad->Duplicate() ? 14 : 13;
    echo COM_refresh($_CONF_ADVT['admin_url'] . '?plugin=classifieds&msg=' . $msg);
    exit;
    break;

case 'resetcatperms':
    $new_perms = array(
        $_POST['perm_owner'][0],
        $_POST['perm_group'][0],
        $_POST['perm_members'][0],
        $_POST['perm_anon'][0],
    );
    \Classifieds\Category::ResetPerms($_POST['group_id'], $new_perms);
    echo COM_refresh($_CONF_ADVT['admin_url']);
    exit;
    break;

default:
    // Go to the requested view
    $view = $action;
    break;
}

// Then handle the page request.  This is generally the final display
// after any behind-the-scenes action has been performed
switch ($view) {
case 'editad':
case 'edit':    // if called from submit.php
    $Ad = new \Classifieds\Ad(CLASSIFIEDS_getParam('ad_id'));
    // TODO: What was this for?
    //$Ad->cat_id = CLASSIFIEDS_getParam('cat_id', 'int');
    $content .= $Ad->Edit();
    break;

case 'editadtype':
    // Edit an ad type. $actionval contains the type_id value
    $AdType = new \Classifieds\AdType($actionval);
    //$content .= CLASSIFIEDS_adminMenu('type');
    $content .= Classifieds\Menu::Admin('type');
    $content .= $AdType->ShowForm();
    break;

case 'editcat':
    // Display the form to edit a category.
    // $actionval contains the category ID
    $cat_id = CLASSIFIEDS_getParam('cat_id', 'int');
    $content .= Classifieds\Menu::Admin('categories');
    $C = new Classifieds\Category($cat_id);
    $content .= $C->Edit();
    break;

case 'moderate':
    $Ad = new \Classifieds\Ad($ad_id, 'ad_submission');
    $content .= $Ad->Edit();
    break;

case 'types':
    $content .= Classifieds\Menu::Admin($view);
    $content .= Classifieds\AdType::adminList();
    $admin_mode = ': ' . $LANG_ADVT['mnu_types'];
    break;

case 'categories':
    $content .= Classifieds\Menu::Admin($view);
    $content .= Classifieds\Category::adminList();
    $admin_mode = ': ' . $LANG_ADVT['mnu_cats'];
    break;

case 'other':
    $content .= Classifieds\Menu::Admin($view);
    $T1 = new Template($_CONF_ADVT['path'] . '/templates/admin/');
    $T1->set_file('content', 'adminother.thtml');
    $T1->set_var(array(
        'cat_list' => SEC_getGroupDropdown($_CONF_ADVT['defgrpcat'], 3),
        'cat_perms' => SEC_getPermissionsHTML(
            $_CONF_ADVT['default_perm_cat'][0],
            $_CONF_ADVT['default_perm_cat'][1],
            $_CONF_ADVT['default_perm_cat'][2],
            $_CONF_ADVT['default_perm_cat'][3]
        ),
    ) );
    $T1->parse('output1', 'content');
    $content .= $T1->finish($T1->get_var('output1'));
    break;

case 'admin':
    echo "DEPRECATED";die;
    USES_classifieds_admin();
    switch ($actionval) {
    case 'cat':
        $content .= CLASSIFIEDS_adminMenu($actionval);
        $content .= CLASSIFIEDS_adminCategories();
        $admin_mode = ': ' . $LANG_ADVT['mnu_cats'];
        break;
    case 'type':
        $content .= CLASSIFIEDS_adminMenu($actionval);
        $content .= CLASSIFIEDS_adminAdTypes();
        $admin_mode = ': ' . $LANG_ADVT['mnu_types'];
        break;
    case 'ad':
    default:
        $actionval = 'ad';
        $content .= Classifieds\Menu::Admin($actionval);
        $content .= CLASSIFIEDS_adminAds();
        $admin_mode = ': '. $LANG_ADVT['manage_ads'];
        break;
    case 'other':
        $content .= CLASSIFIEDS_adminMenu($actionval);
        $T1 = new Template($_CONF_ADVT['path'] . '/templates/admin/');
        $T1->set_file('content', 'adminother.thtml');
        $T1->set_var(array(
            'cat_list' => SEC_getGroupDropdown($_CONF_ADVT['defgrpcat'], 3),
            'cat_perms' => SEC_getPermissionsHTML(
                        $_CONF_ADVT['default_perm_cat'][0],
                        $_CONF_ADVT['default_perm_cat'][1],
                        $_CONF_ADVT['default_perm_cat'][2],
                        $_CONF_ADVT['default_perm_cat'][3]),
        ) );
        $T1->parse('output1', 'content');
        $content .= $T1->finish($T1->get_var('output1'));
        break;
    }
    break;

case 'ads':
default:
    $content .= Classifieds\Menu::Admin('ads');
    $content .= Classifieds\Ad::adminList();
    break;
}

// Generate the common header for all admin pages
echo Classifieds\Menu::siteHeader();
$T = new Template($_CONF_ADVT['path'] . '/templates/admin/');
$T->set_file('admin', 'index.thtml');
$T->set_var(array(
    'version'       => "{$LANG_ADVT['version']}: {$_CONF_ADVT['pi_version']}",
    'admin_mode'    => $admin_mode,
    'page_content'  => $content,
    'pi_url'        => $_CONF_ADVT['url'] . '/index.php',
) );
$T->parse('output','admin');
echo $T->finish($T->get_var('output'));
echo Classifieds\Menu::siteFooter();

?>
