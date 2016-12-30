<?php
/**
*   Admin index file.  Dispatch requests to other files
*
*   @author     Lee Garner <lee@leegarner.com>
*   @copyright  Copyright (c) 2009-2016 Lee Garner <lee@leegarner.com>
*   @package    classifieds
*   @version    1.1.0
*   @license    http://opensource.org/licenses/gpl-2.0.php 
*               GNU Public License v2 or later
*   @filesource
*/

/** Import core glFusion libraries */
require_once '../../../lib-common.php';
require_once '../../auth.inc.php';

// If plugin is installed but not enabled, display an error and exit gracefully
if (!in_array('classifieds', $_PLUGINS)) {
    COM_404();
    exit;
}

USES_lib_admin();

// Only let admin users access this page
if (!SEC_hasRights('classifieds.admin')) {
    // Someone is trying to illegally access this page
    COM_errorLog("Someone has tried to illegally access the classifieds Admin page.  User id: {$_USER['uid']}, Username: {$_USER['username']}, IP: $REMOTE_ADDR",1);
    COM_404();
    exit;
}

/**
*   Create the admin menu at the top of the list and form pages.
*
*   @return string      HTML for admin menu section
*/
function CLASSIFIEDS_adminMenu($mode='', $help_text = '')
{
    global $_CONF, $_CONF_ADVT, $LANG_ADVT, $LANG01;

    $menu_arr = array ();
    if ($help_text == '')
        $help_text = 'admin_text';

    if ($mode == 'ad') {
        $menu_arr[] = array(
            'url' => $_CONF_ADVT['admin_url'] . '/index.php?editad',
            'text' => '<span class="adMenuActive">' . $LANG_ADVT['mnu_submit']
                    . '</span>',
            );
        $help_text = 'hlp_adlist';
    } else {
        $menu_arr[] = array(
            'url' => $_CONF_ADVT['admin_url'] . '/index.php?adminad',
            'text' => $LANG_ADVT['mnu_adlist'],
        );
    }

    if ($mode == 'type') {
        $menu_arr[] = array(
            'url' => $_CONF_ADVT['admin_url'] . '/index.php?editadtype=0',
            'text' => '<span class="adMenuActive">' . $LANG_ADVT['mnu_newtype']
                    . '</span>',
        );
        $help_text = 'hlp_adtypes';
    } else {
        $menu_arr[] = array(
            'url' => $_CONF_ADVT['admin_url'] . '/index.php?admin=type',
            'text' => $LANG_ADVT['mnu_types'],
        );
    }

    if ($mode == 'cat') {
        $menu_arr[] = array(
            'url' => $_CONF_ADVT['admin_url'] . '/index.php?editcat=x&cat_id=0',
            'text' => '<span class="adMenuActive">' .$LANG_ADVT['mnu_newcat']
                    . '</span>',
            );
        $help_text = 'hlp_cats';
    } else {
        $menu_arr[] = array(
            'url' => $_CONF_ADVT['admin_url'] . '/index.php?admin=cat',
            'text' => $LANG_ADVT['mnu_cats'],
        );
    }

    $menu_arr[] = array(
            'url' => $_CONF_ADVT['admin_url'] . '/index.php?admin=other',
            'text' => $LANG_ADVT['mnu_other']);
    if ($mode == 'other') {
        $help_text = 'hlp_other';
    }

    $menu_arr[] = array('url' => $_CONF['site_admin_url'],
            'text' => $LANG01[53]);

    $retval = ADMIN_createMenu($menu_arr, $LANG_ADVT[$help_text],
                    plugin_geticon_classifieds());
    return $retval;
}


$action = '';
$expected = array('edit', 'moderate', 'save', 'deletead', 'deleteimage',
        'deleteadtype', 'saveadtype', 'editadtype', 'editad', 'dupad',
        'deletecat', 'editcat', 'savecat', 'delbutton_x', 'resetcatperms',
        'cancel', 'admin');
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

$type = CLASSIFIEDS_getParam('type');
$content = '';      // initialize variable for page content
$A = array();       // initialize array for form vars

switch ($action) {
case 'deleteimage': // delete an image
    USES_classifieds_class_image();
    $Image = new adImage($actionval);
    $Image->Delete();
    $actionval = $ad_id;
    $view = 'editad';
    break;

case 'deletecat':   // delete a single category
    $cat_id = CLASSIFIEDS_getParam('cat_id', 'int');
    if ($cat_id > 0) {
        USES_classifieds_class_category();
        adCategory::Delete($_REQUEST['cat_id']);
        $view = 'admin';
    }
    break;

case 'delbutton_x':
    USES_classifieds_class_ad();
    foreach ($_POST['delitem'] as $ad_id) {
        Ad::Delete($ad_id);
    }
    COM_refresh($_CONF_ADVT['admin_url'] . '/index.php?admin=ad');
    break;

case 'deletead':
    USES_classifieds_class_ad();
    if ($type == 'submission' || $type == 'editsubmission' || 
            $type == 'moderate') {
        CLASSIFIEDS_auditLog("Deleting submission $ad_id");
        Ad::Delete($ad_id, 'ad_submission');
        echo COM_refresh($_CONF['site_admin_url'] . '/moderation.php');
        exit;
    } else {
        Ad::Delete($ad_id);
        $view = 'admin';
        $actionval = 'ad';
    }
    break;

case 'saveadtype':
    USES_classifieds_class_adtype();
    $type_id = CLASSIFIEDS_getParam('type_id', 'int');
    $AdType = new adType($type_id);
    if (!$AdType->Save($_POST)) {
        COM_errorLog("Error saving ad type");
        COM_errorLog("Type info:" . print_r($AdType,true));
    }
    COM_refresh($_CONF_ADVT['admin_url'] . '/index.php?admin=type');
    break;

case 'deleteadtype':
    USES_classifieds_class_adtype();
    $type_id = CLASSIFIEDS_getParam('type_id', 'int');
    $AdType = new adType($type_id);
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
    USES_classifieds_class_category();
    $cat_id = CLASSIFIEDS_getParam('cat_id', 'int');
    $C = new adCategory($cat_id);
    $C->Save($_POST);
    $view = 'admin';
    $actionval = 'cat';
    break;

case 'delcat':
    // Insert or update a category record from form vars
    USES_classifieds_class_category();
    adCategory::DeleteMulti($_POST['c']);
    $view = 'admin';
    $actionval = 'cat';
    break;

case 'delcatimg':
    // Delete a category image
    $cat_id = CLASSIFIEDS_getParam('cat_id', 'int');
    if ($cat_id > 0) {
        USES_classifieds_class_category();
        adCategory::DelImage($cat_id);
    }
    $view = 'editcat';
    break;

case 'save':
    USES_classifieds_class_ad();
    if ($_POST['type'] == 'submission') {   // approving a submission
        $Ad = new Ad($ad_id, 'ad_submission');
        $Ad->isNew = true;
        $Ad->setTable('ad_ads');
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
    USES_classifieds_class_ad();
    $Ad = new Ad($actionval);
    $msg = 14;
    if (!$Ad->Duplicate()) {
        $msg = 13;
    }
    echo COM_refresh($_CONF_ADVT['admin_url'] . '?msg=' . $msg);
    exit;
    break;

case 'resetcatperms':
    USES_classifieds_class_category();
    $new_perms = array(
        $_POST['perm_owner'][0],
        $_POST['perm_group'][0],
        $_POST['perm_members'][0],
        $_POST['perm_anon'][0],
    );
    adCategory::ResetPerms($_POST['group_id'], $new_perms);
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
    USES_classifieds_class_ad();
    $Ad = new Ad(CLASSIFIEDS_getParam('ad_id'));
    // TODO: What was this for?
    //$Ad->cat_id = CLASSIFIEDS_getParam('cat_id', 'int');
    $content .= $Ad->Edit();
    break;

case 'editadtype':
    // Edit an ad type. $actionval contains the type_id value
    USES_classifieds_class_adtype();
    $AdType = new adType($actionval);
    $content .= CLASSIFIEDS_adminMenu('type');
    $content .= $AdType->ShowForm();
    break;

case 'editcat':
    // Display the form to edit a category.
    // $actionval contains the category ID
    USES_classifieds_class_category();
    $cat_id = CLASSIFIEDS_getParam('cat_id', 'int');
    $content .= CLASSIFIEDS_adminMenu('cat');
    $C = new adCategory($cat_id);
    $content .= $C->Edit();
    break;

case 'moderate':
    USES_classifieds_class_ad();
    $Ad = new Ad($ad_id, 'ad_submission');
    $content .= $Ad->Edit();
    break;

case 'admin':
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
        $content .= CLASSIFIEDS_adminMenu($actionval);
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

default:
    USES_classifieds_admin();
    $content .= CLASSIFIEDS_adminMenu('ad');
    $content .= CLASSIFIEDS_adminAds();
    break;
}
 
// Generate the common header for all admin pages
echo CLASSIFIEDS_siteHeader();
$T = new Template($_CONF_ADVT['path'] . '/templates/admin/');
$T->set_file('admin', 'index.thtml');
$T->set_var(array(
    'version'       => "{$LANG_ADVT['version']}: {$_CONF_ADVT['pi_version']}",
    'admin_mode'    => $admin_mode,
    'page_content'  => $content,
) );
$T->parse('output','admin');
echo $T->finish($T->get_var('output'));
echo CLASSIFIEDS_siteFooter();

?>
