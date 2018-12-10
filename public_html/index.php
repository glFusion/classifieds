<?php
/**
 * Public entry point for the Classifieds plugin.
 *
 * @author      Lee Garner <lee@leegarner.com>
 * @copyright   Copyright (c) 2009-2017 Lee Garner <lee@leegarner.com>
 * @package     classifieds
 * @version     v1.2.0
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
    if (isset($_GET['cat_id']) && $Ad->isNew) {
        $Ad->cat_id = $_GET['cat_id'];
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
    $content .= CLASSIFIEDS_ManageAds();
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
    $C = new \Classifieds\Category($id);
    if ($C->papa_id > 0) {
        // A sub-category, display the ads
        //$L = new \Classifieds\AdList_Cat($id);
        $L = new \Classifieds\Lists\Ads\byCat($id);
        $content .= $L->Render();
        $pageTitle = $L->Cat->cat_name;
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
 * Get the fields for the ad listing.
 *
 * @param   string   $fieldname     Name of the field
 * @param   string   $fieldvalue    Value to be displayed
 * @param   array    $A             Associative array of all values available
 * @param   array    $icon_arr      Array of icons available for display
 * @return  string                  Complete HTML to display the field
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
                $_CONF_ADVT['url'] .
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
                $_CONF_ADVT['url'] .
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
                $_CONF_ADVT['url'] .
                    "/index.php?mode=detail&amp;id={$A['ad_id']}");
        break;

    case 'delete':
        if ($_CONF_ADVT['_is_uikit']) {
            $retval = COM_createLink('',
                $_CONF_ADVT['url'] .
                    "/index.php?mode=deletead&amp;id={$A['ad_id']}",
                array('title' => $LANG_ADVT['del_item'],
                    'class' => 'uk-icon uk-icon-trash',
                    'style' => 'color:red;',
                    'data-uk-tooltip' => '',
                    'onclick' => "return confirm('{$LANG_ADVT['del_item_confirm']}');",
                )
            );
        } else {
            $retval .= '&nbsp;&nbsp;' . COM_createLink(
                COM_createImage($_CONF['layout_url'] . '/images/admin/delete.png',
                    $LANG_ADVT['del_item'],
                    array('title' => $LANG_ADVT['del_item'],
                        'class' => 'gl_mootip',
                        'onclick' => "return confirm('${LANG_ADVT['del_item_confirm']}');",
                    )),
                $_CONF_ADVT['url'] .
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
 * Create admin list of Ads to manage.
 *
 * @return  string  HTML for admin list
 */
function CLASSIFIEDS_ManageAds()
{
    global $_CONF, $_TABLES, $LANG_ADMIN, $LANG_ACCESS, $_CONF_ADVT,
        $LANG_ADVT, $_USER;

    $retval = '';

    $header_arr = array(
        array(
            'text'  => $LANG_ADVT['edit'],
            'field' => 'edit',
            'sort'  => false,
            'align' => 'center',
        ),
        array(
            'text'  => $LANG_ADVT['description'],
            'field' => 'subject',
            'sort'  => true,
        ),
        array(
            'text'  => $LANG_ADVT['added'],
            'field' => 'add_date',
            'sort'  => false,
            'align' => 'center',
        ),
        array(
            'text'  => $LANG_ADVT['expires'],
            'field' => 'exp_date',
            'sort'  => false,
            'align' => 'center'),
        array(
            'text'  => $LANG_ADVT['delete'],
            'field' => 'delete',
            'sort'  => false,
            'align' => 'center'),
    );

    $defsort_arr = array('field' => 'add_date', 'direction' => 'asc');

    $text_arr = array(
        'has_extras' => true,
        'form_url' => $_CONF_ADVT['url'] . '/index.php',
    );

    $query_arr = array(
        'table' => 'ad_ads',
        'sql' => "SELECT * FROM {$_TABLES['ad_ads']} WHERE uid = {$_USER['uid']}",
        'query_fields' => array(),
        'default_filter' => ''
    );
    $form_arr = array();
    USES_lib_admin();
    $retval .= ADMIN_list('classifieds', 'CLASSIFIEDS_getField_AdList',
            $header_arr, $text_arr, $query_arr, $defsort_arr, '',
            '', '', $form_arr);
    return $retval;
}

?>
