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
    $display .= SEC_loginForm();
    $display .= CLASSIFIEDS_siteFooter(true);
    echo $display;
    exit;
}

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
    $menu->add_menuitem($LANG_ADVT['mnu_submit'],
        CLASSIFIEDS_URL . '/index.php?mode=submit');
        //$_CONF['site_url']. '/submit.php?type='. $_CONF_ADVT['pi_name']);
}

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

case 'submit':
case 'edit':
    USES_classifieds_class_ad();
    $Ad = new Ad($id);
    if (isset($_GET['cat_id']) && $Ad->isNew) {
        $Ad->cat_id = $_GET['cat_id'];
    }
    $content .= $Ad->Edit();
    break;

case 'delete':
    if ($id > 0) {
        USES_classifieds_class_ad();
        $Ad = new Ad($id);
        $Ad->Delete();
    }
    $view = 'manage';
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
    $view = $view == '' ? 'account' : $view;
    break;

case 'update_ad':
    $r = adSave($mode);
    $content .= $r[1];
    $view = 'manage';
    break;

case 'save':
    USES_classifieds_class_ad();
    $Ad = new Ad();
    $r = $Ad->Save($_POST);
    if ($r[0] == 0) {
        COM_refresh(CLASSIFIEDS_URL . '?msg=01');
    } else {
        // store custom message amd redirect
        LGLIB_storeMessage($r[1]);
        COM_refresh(CLASSIFIEDS_URL);
    }
    break;

case 'delete_img':
    USES_classifieds_class_image();
    $Image = new adImage($actionval);
    $Image->Delete();
    $actionval = $ad_id;
    $view = 'editad';
    break;

/*case 'add_notice':
    $cat = (int)$id;
    if ($cat > 0) {
        USES_classifieds_notify();
        catSubscribe($cat);
    }
    $page = isset($_GET['page']) ? $_GET['page'] : 'home';
    echo COM_refresh(CLASSIFIEDS_URL . '/index.php?mode=' . $page . '&id=' . $cat);
    break;

case 'del_notice':
    $cat = (int)$id;
    if ($cat > 0) {
        USES_classifieds_notify();
        catUnSubscribe($cat);
    }
    $page = isset($_GET['page']) ? $_GET['page'] : 'home';
    echo COM_refresh(CLASSIFIEDS_URL . '/index.php?mode=' . $page . '&id=' . $cat);
    break;
*/
case 'moredays':
    USES_classifieds_class_ad();
    $Ad = new Ad($id);
    $Ad->addDays($_POST['add_days']);
    $view = 'manage';
    break;

case 'recent':
    //  Display recent ads
    USES_classifieds_class_adlist();
    $L = new AdListRecent();
    $content .= $L->Render();
//
//    $content .= adListRecent();
    $T->set_var('header', $LANG_ADVT['recent_listed']);
    $menu_opt = $LANG_ADVT['mnu_recent'];
    break;

case 'manage':
    // Manage ads.  Restricted to the user's own ads
    if ($isAnon) {
        $content .= CLASSIFIEDS_errorMsg($LANG_ADVT['login_required'], 'note');
    } else {
        $content .= CLASSIFIEDS_ManageAds();
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
    USES_classifieds_class_ad();
    $Ad = new Ad($id);
    $content .= $Ad->Detail();
    break;

case 'editad':
    // Edit an ad.  
    USES_classifieds_class_ad();
    $Ad = new Ad($id);
    $content .= $Ad->Edit();
    break;

case 'byposter':
    // Display all open ads for the specified user ID
    USES_classifieds_class_adlist();
    $L = new AdListPoster($_GET['uid']);
    $content .= $L->Render();
    /*$uid = isset($_REQUEST['uid']) ? (int)$_REQUEST['uid'] : 0;
    if ($uid > 1) {
        USES_classifieds_list();
        $content .= adListPoster($uid);
    }*/
    $T->set_var('header', $LANG_ADVT['ads_by']. ' '. COM_getDisplayName($uid));
    $menu_opt = $LANG_ADVT['mnu_home'];
    break;

case 'home':
default:
    // Display either the categories, or the ads under a requested
    // category
    if ($id > 0) {
        USES_classifieds_class_adlist();
        $L = new AdListCat($id);
        $content .= $L->Render();
        //$content .= adListCat($id);
        $pageTitle = DB_getItem($_TABLES['ad_category'], 'cat_name', 
                        "cat_id='$id'");
    } else {
        USES_classifieds_class_catlist();
        $content .= CatList::Render();
    }
    $T->set_var('header', $LANG_ADVT['blocktitle']);
    $menu_opt = $LANG_ADVT['mnu_home'];
    break;

}   // switch ($mode)

if (!empty($view)) COM_refresh(CLASSIFIEDS_URL . "?mode=$view");

if ($menu_opt != '') $menu->set_selected($menu_opt);
$T->set_var('menu', $menu->generate());
$T->set_var('content', $content);
$T->parse('output', 'page');
echo CLASSIFIEDS_siteHeader($pageTitle);
if ($msg != '')
    echo  COM_showMessage($msg, $_CONF_ADVT['pi_name']);
echo $T->finish($T->get_var('output'));
echo CLASSIFIEDS_siteFooter();
exit;


/**
*   Get the fields for the ad listing.
*
*   @param  string   $fieldname     Name of the field
*   @param  string   $fieldvalue    Value to be displayed
*   @param  array    $A             Associative array of all values available
*   @param  array    $icon_arr      Array of icons available for display
*   @return string                  Complete HTML to display the field
*/
function CLASSIFIEDS_getField_AdList($fieldname, $fieldvalue, $A, $icon_arr)
{
    global $_CONF, $_CONF_ADVT, $LANG24, $LANG_ADVT;

    $retval = '';
    $dt = new Date('now', $_CONF['timezone']);

    switch($fieldname) {
    case 'edit':
        if ($_CONF_ADVT['_is_uikit']) {
            $retval = COM_createLink('',
                CLASSIFIEDS_URL . 
                    "/index.php?mode=editad&amp;id={$A['ad_id']}",
                array(
                    'class' => 'uk-icon uk-icon-edit',
                    'title' => $LANG_ADVT['edit'],
                    'data-uk-tooltip' => ''
                )
            );
        } else {
            $retval = COM_createLink(
                $icon_arr['edit'],
                CLASSIFIEDS_URL . 
                "/index.php?mode=editad&amp;id={$A['id']}"
            );
        }
        break;

    case 'add_date':
    case 'exp_date':
        $dt->setTimestamp($fieldvalue);
        $retval = $dt->format($_CONF['shortdate']);
        break;

    case 'subject':
        $retval = COM_createLink($fieldvalue,
                CLASSIFIEDS_URL . 
                    "/index.php?mode=detail&amp;id={$A['ad_id']}");
        break;

    case 'delete':
        if ($_CONF_ADVT['_is_uikit']) {
            $retval = COM_createLink('',
                CLASSIFIEDS_URL . 
                    "/index.php?mode=deletead&amp;id={$A['ad_id']}",
                array('title' => $LANG_ADVT['del_item'],
                    'class' => 'uk-icon uk-icon-trash',
                    'style' => 'color:red;',
                    'data-uk-tooltip' => '',
                    'onclick' => "return confirm('Do you really want to delete this item?');",
                )
            );
        } else {
            $retval .= '&nbsp;&nbsp;' . COM_createLink(
                COM_createImage($_CONF['layout_url'] . '/images/admin/delete.png',
                    'Delete this item',
                    array('title' => 'Delete this item', 
                        'class' => 'gl_mootip',
                        'onclick' => "return confirm('Do you really want to delete this item?');",
                    )),
                CLASSIFIEDS_URL . 
                    "/index.php?mode=deletead&amp;id=={$A['ad_id']}"
            );
        }
        break;

    default:
        $retval = $fieldvalue;
        break;
    }

    return $retval;
}



/**
*   Create admin list of Ad Types
*   @return string  HTML for admin list
*/
function CLASSIFIEDS_ManageAds()
{
    global $_CONF, $_TABLES, $LANG_ADMIN, $LANG_ACCESS, $_CONF_ADVT,
        $LANG_ADVT, $_USER;

    $retval = '';

    $header_arr = array(      # display 'text' and use table field 'field'
        array('text' => $LANG_ADVT['edit'], 'field' => 'edit', 
            'sort' => false, 'align' => 'center'),
        array('text' => $LANG_ADVT['description'], 'field' => 'subject', 
            'sort' => true),
        array('text' => $LANG_ADVT['added'], 'field' => 'add_date', 
            'sort' => false, 'align' => 'center'),
        array('text' => $LANG_ADVT['expires'], 'field' => 'exp_date', 
            'sort' => false, 'align' => 'center'),
        array('text' => $LANG_ADVT['delete'], 'field' => 'delete', 
            'sort' => false, 'align' => 'center'),
    );

    $defsort_arr = array('field' => 'add_date', 'direction' => 'asc');

    $text_arr = array( 
        'has_extras' => true,
        'form_url' => CLASSIFIEDS_URL . '/index.php',
    );

    $query_arr = array('table' => 'ad_ads',
        'sql' => "SELECT * FROM {$_TABLES['ad_ads']} WHERE uid = {$_USER['uid']}", 
        'query_fields' => array(),
        'default_filter' => ''
    );

    USES_lib_admin();
    $retval .= ADMIN_list('classifieds', 'CLASSIFIEDS_getField_AdList',
            $header_arr, $text_arr, $query_arr, $defsort_arr, '',
            '', '', $form_arr);

    return $retval;
}

?>
