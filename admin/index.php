<?php
/**
*   Admin index file.  Dispatch requests to other files
*
*   @author     Lee Garner <lee@leegarner.com>
*   @copyright  Copyright (c) 2009-2010 Lee Garner <lee@leegarner.com>
*   @package    classifieds
*   @version    1.0.2
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

/** Import plugin-specific functions */
USES_classifieds_advt_functions();
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
    global $_CONF, $LANG_ADVT, $LANG01;

    $menu_arr = array ();
    if ($help_text == '')
        $help_text = 'admin_text';

    if ($mode == 'ad') {
        $menu_arr[] = array(
            'url' => CLASSIFIEDS_ADMIN_URL . '/index.php?editad',
            'text' => $LANG_ADVT['mnu_submit']);
        $help_text = 'hlp_adlist';
    } else {
        $menu_arr[] = array(
            'url' => CLASSIFIEDS_ADMIN_URL . '/index.php?adminad',
            'text' => $LANG_ADVT['mnu_adlist']);
    }

    if ($mode == 'type') {
        $menu_arr[] = array(
            'url' => CLASSIFIEDS_ADMIN_URL . '/index.php?editadtype=0',
            'text' => $LANG_ADVT['mnu_newtype']);
        $help_text = 'hlp_adtypes';
    } else {
        $menu_arr[] = array(
            'url' => CLASSIFIEDS_ADMIN_URL . '/index.php?admin=type',
            'text' => $LANG_ADVT['mnu_types']);
    }

    if ($mode == 'cat') {
        $menu_arr[] = array(
            'url' => CLASSIFIEDS_ADMIN_URL . '/index.php?editcat=x&cat_id=0',
            'text' => $LANG_ADVT['mnu_newcat']);
        $help_text = 'hlp_cats';
    } else {
        $menu_arr[] = array(
            'url' => CLASSIFIEDS_ADMIN_URL . '/index.php?admin=cat',
            'text' => $LANG_ADVT['mnu_cats']);
    }


    $menu_arr[] = array(
            'url' => CLASSIFIEDS_ADMIN_URL . '/index.php?admin=other',
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
        'deleteadtype', 'saveadtype', 'editadtype', 'editad',
        'deletecat', 'editcat', 'savecat',
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

if (isset($_POST['ad_id'])) {
    $ad_id = COM_sanitizeID($_POST['ad_id']);
} elseif (isset($_GET['ad_id'])) {
    $ad_id = COM_sanitizeID($_GET['ad_id']);
} elseif (isset($_GET['id'])) {
    $ad_id = COM_sanitizeID($_GET['id']);
} else {
    $ad_id = '';
}

// Set the view to be displayed.  May be overridden during execution of $action
if (isset($_POST['page'])) {
    $view = COM_applyFilter($_POST['page']);
} elseif (isset($_GET['page'])) {
    $view = COM_applyFilter($_GET['page']);
} else {
    $view = $action;
}

$type = (isset($_POST['type'])) ? COM_applyFilter($_POST['type']) : '';
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
    $cat_id = (int)CLASSIFIEDS_getParam('cat_id');
    if ($cat_id > 0) {
        USES_classifieds_class_category();
        adCategory::Delete($_REQUEST['cat_id']);
        $view = 'admin';
    }
    break;

case 'deletead':
    USES_classifieds_class_ad();
    if ($type == 'submission' || $type == 'editsubmission' || 
            $type == 'moderate') {
        CLASSIFIEDS_auditLog("Deleting submission $ad_id");
        adDelete($ad_id, true, 'ad_submission');
        $view = 'moderation';
    } else {
        $Ad = new Ad($ad_id);
        $Ad->Delete();
        $view = 'admin';
        $actionval = 'ad';
    }
    break;

case 'saveadtype':
    USES_classifieds_class_adtype();
    $type_id = CLASSIFIEDS_getParam('type_id');
    $AdType = new adType($type_id);
    if (!$AdType->Save($_POST)) {
        COM_errorLog("Error saving ad type");
        COM_errorLog("Type info:" . print_r($AdType,true));
    }
    COM_refresh(CLASSIFIEDS_ADMIN_URL . '/index.php?admin=type');
    break;

case 'deleteadtype':
    USES_classifieds_class_adtype();
    $type_id = CLASSIFIEDS_getParam('type_id');
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
    COM_refresh(CLASSIFIEDS_ADMIN_URL . '/index.php?admin=type');
    break;

case 'savecat':
    // Insert or update a category record from form vars
    USES_classifieds_class_category();
    $cat_id = (int)CLASSIFIEDS_getParam('cat_id');
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
    $cat_id = CLASSIFIEDS_getParam('cat_id');
    if ($cat_id > 0) {
        USES_classifieds_class_category();
        adCategory::DelImage($cat_id);
    }
    $view = 'editcat';
    break;

case 'save':
    USES_classifieds_class_ad();
    $Ad = new Ad($_POST['ad_id']);
    if ($type == 'submission') {    // new submission
        $r = $Ad->Save($_POST);
        if ($r[0] == 0) {
            echo COM_refresh(CLASSIFIEDS_ADMIN_URL);
            exit;
            /*$content .= COM_showMessage($r[1], $_CONF_ADVT['pi_name']);
            $view = 'admin';
            $actionval = 'ad';*/
        } else {
            $content .= $r[1];
            $view = 'edit';
        }
    } elseif ($type == 'editsubmission' || $type == 'moderate') {  // saving from the queue
        $r = adSave($type);
        if ($r[0] == 0) {
            $msgid = $r[1];
            $view = 'moderation';
        } else {
            $content .= $r[1];
            $view = 'edit';
            $A = $_POST;
        }
    } else {
        $r = adSave('adminupdate');
        if ($r[0] == 0)
            $content .= COM_showMessage($r[1], $_CONF_ADVT['pi_name']);
        else
            $content .= $r[1];
        $view = 'admin';
        $actionval = 'ad';
    }
    break;

default:
    // Go to the requested view
    $view = $action;
    break;
}

// First, process any action that was requested.  If this is a specific
// action, then after performing it set the page to the desired display.
if (0) {
switch ($mode) {

/*    case 'deletecat':
        if (isset($_POST['cat_id'])) {
            $cat_id = (int)$_POST['cat_id'];
        } elseif (isset($_GET['cat_id'])) {
            $cat_id = (int)$_GET['cat_id'];
        } else {
            $cat_id = 0;
        }
        if ($cat_id > 0) {
            //USES_classifieds_categories();
            //catDelete($_REQUEST['cat_id']);
            USES_classifieds_class_category();
            adCategory::Delete($_REQUEST['cat_id']);
            $view = 'admin';
        }
        break;*/

    case $LANG_ADMIN['delete']:
    case 'delete':
        if ($mode == $LANG_ADMIN['delete'] && !isset($LANG_ADMIN['delete']))
            break;
        switch ($actionval) {
        case 'cat':
            if (isset($_POST['cat_id'])) {
                $cat_id = (int)$_POST['cat_id'];
            } elseif (isset($_GET['cat_id'])) {
                $cat_id = (int)$_GET['cat_id'];
            } else {
                $cat_id = 0;
            }
            if ($cat_id > 0) {
                //USES_classifieds_categories();
                //catDelete($_REQUEST['cat_id']);
                USES_classifieds_class_category();
                adCategory::Delete($_REQUEST['cat_id']);
                $view = 'admin';
            }
            break;

        case 'ad':
        default:
            if ($type == 'submission' || $type == 'editsubmission' || 
                $type == 'moderate') {
                CLASSIFIEDS_auditLog("Deleting submission $ad_id");
                adDelete($ad_id, true, 'ad_submission');
                $view = 'moderation';
            } else {
                adDelete($ad_id, true);
                $view = 'admin';
                $actionval = 'ad';
            }
            break;
        } 
        break;

    case 'delete_ad':
        // Delete a specific ad
        adDelete($ad_id, true);
        $view = 'admin';
        $actionval = 'ad';
        break;

    case 'deletesubmission':        // DEPRECATE
        // Delete a specific ad
        adDelete($ad_id, true, 'ad_submission');
        $view = 'moderation';
        break;

    case 'update_ad':        // DEPRECATE
    case 'updatesubmission':
        $r = adSave('adminupdate');
        $content .= $r[1];
        $view = 'admin';
        $actionval = 'ad';
        break;

    case 'save':
        if ($type == 'submission') {    // new submission
            $r = adSave('submission');
            if ($r[0] == 0) {
                echo COM_refresh(CLASSIFIEDS_ADMIN_URL);
                exit;
                /*$content .= COM_showMessage($r[1], $_CONF_ADVT['pi_name']);
                $view = 'admin';
                $actionval = 'ad';*/
            } else {
                $content .= $r[1];
                $view = 'edit';
            }
        } elseif ($type == 'editsubmission' || $type == 'moderate') {  // saving from the queue
            $r = adSave($type);
            if ($r[0] == 0) {
                $msgid = $r[1];
                $view = 'moderation';
            } else {
                $content .= $r[1];
                $view = 'edit';
                $A = $_POST;
            }
        } else {
            $r = adSave('adminupdate');
            if ($r[0] == 0)
                $content .= COM_showMessage($r[1], $_CONF_ADVT['pi_name']);
            else
                $content .= $r[1];
            $view = 'admin';
            $actionval = 'ad';
        }
        break;

    /*case 'savesubmission':
        $r = adSave('editsubmission');
        $content .= $r[1];
        $view = 'edit';
        break;
    */
    case 'update_cat':
        // Insert or update a category record from form vars
        //USES_classifieds_categories();
        USES_classifieds_class_category();
        $cat = isset($_REQUEST['catid']) ? intval($_REQUEST['catid']) : 0;
        $C = new adCategory($_REQUEST['catid']);
        $C->Save($_POST);
        //catSave($cat);
        $view = 'admin';
        $actionval = 'cat';
        break;

    /*case 'delcat':
        // Insert or update a category record from form vars
        //USES_classifieds_categories();
        //catDeleteMulti();
        USES_classifieds_class_category();
        adCategory::DeleteMulti($_POST['c']);
        $view = 'admin';
        $actionval = 'cat';
        break;*/
    /*
    case 'delsubimg':
        // Delete image from submission queue
        USES_classifieds_edit();
        $content .= imgDelete(true, 'ad_submission');
        $view = 'editsubmission';
        $mode = 'editsubmission';
        break;
    case 'delete_img':
        USES_classifieds_edit();
        $content .= imgDelete(true);
        $view = 'edit';
        break;
    */

    /*case 'delcatimg':
        // Delete a category image
        //USES_classifieds_categories();
        USES_classifieds_class_category();
        $cat_id = isset($_REQUEST['cat_id']) ? (int)$_REQUEST['cat_id'] : 0;
        if ($cat_id > 0) {
            //catDelImage($cat_id);
            adCategory::DelImage($cat_id);
        }
        $view = 'editcat';
        break;*/

    /*case 'resetadperms':
        $perms = SEC_getPermissionValues(
            $_POST['perm_owner'], $_POST['perm_group'], 
            $_POST['perm_members'], $_POST['perm_anon']);
        $sql = "UPDATE
                {$_TABLES['ad_ads']}
            SET
                perm_owner={$perms[0]},
                perm_group={$perms[1]},
                perm_members={$perms[2]},
                perm_anon={$perms[3]},
                group_id=". COM_applyFilter($_POST['group_id'],true);
        DB_query($sql);
        $content .= COM_showMessage('09', $_CONF_ADVT['pi_name']);
        $view = 'admin';
        $actionval = 'other';
        break;
    */

    case 'resetcatperms':
        $perms = SEC_getPermissionValues(
            $_POST['perm_owner'], $_POST['perm_group'], 
            $_POST['perm_members'], $_POST['perm_anon']);
        $sql = "UPDATE
                {$_TABLES['ad_category']}
            SET
                perm_owner={$perms[0]},
                perm_group={$perms[1]},
                perm_members={$perms[2]},
                perm_anon={$perms[3]},
                group_id=". COM_applyFilter($_POST['group_id'],true);
        DB_query($sql);
        $content .= COM_showMessage('09', $_CONF_ADVT['pi_name']);
        $view = 'admin';
        $actionval = 'other';
        break;

    case 'toggleadtype':
        USES_classifieds_class_adtype();
        adType::toggleEnabled($ad_id, $_REQUEST['enabled']);
        $view = 'admintypes';
        break;

/*    case 'saveadtype':
        USES_classifieds_class_adtype();
        $AdType = new adType($ad_id);
        $AdType->SetVars($_POST);
        $content .= $AdType->Save();
        $view = 'admin';
        $actionval = 'type';
        break;
*/
    case 'deleteadtype':
        USES_classifieds_class_adtype();
        if (isset($_POST['type_id'])) {
            $type_id = $_POST['type_id'];
        } elseif (isset($_GET['type_id'])) {
            $type_id = $_GET['type_id'];
        } else {
            $type_id = 0;
        }
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
        break;

    case 'cancel':
        if ($type == 'submission') {
            echo COM_refresh($_CONF['site_admin_url'] . '/moderation.php');
            exit;
        } else {
            echo COM_refresh(CLASSIFIEDS_URL);
            exit;
        }
        exit;

    default:
        // There's no default mode
        break;

}
}   // if (0) removing "mode" switch

// Then handle the page request.  This is generally the final display
// after any behind-the-scenes action has been performed
switch ($view) {
case 'editad':
case 'edit':    // if called from submit.php
    USES_classifieds_class_ad();
    $Ad = new Ad($actionval);
    if (isset($_GET['cat_id'])) {
        $Ad->cat_id = $_GET['cat_id'];
    }
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
    $cat_id = CLASSIFIEDS_getParam('cat_id');
    $content .= CLASSIFIEDS_adminMenu('cat');
    $C = new adCategory($cat_id);
    $content .= $C->Edit();
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
        $T1 = new Template(CLASSIFIEDS_PI_PATH . '/templates/admin/');
        $T1->set_file('content', 'adminother.thtml');
        $T1->set_var(array(
            'cat_list' => SEC_getGroupDropdown($_CONF_ADVT['defgrpcat'], 3),
            'cat_perms' => SEC_getPermissionsHTML(
                        $_CONF_ADVT['default_perm_cat'][0],
                        $_CONF_ADVT['default_perm_cat'][1],
                        $_CONF_ADVT['default_perm_cat'][2],
                        $_CONF_ADVT['default_perm_cat'][3]),
/*            'ad_list' => SEC_getGroupDropdown($_CONF_ADVT['defgrpad'], 3),
            'ad_perms' => SEC_getPermissionsHTML(
                        $_CONF_ADVT['default_permissions'][0],
                        $_CONF_ADVT['default_permissions'][1],
                        $_CONF_ADVT['default_permissions'][2],
                        $_CONF_ADVT['default_permissions'][3]),
*/
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
$T = new Template(CLASSIFIEDS_PI_PATH . '/templates/admin/');
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
