<?php
/**
 * View the detail for a single ad.
 *
 * @author      Lee Garner <lee@leegarner.com>
 * @copyright   Copyright (c) 2022 Lee Garner <lee@leegarner.com>
 * @package     classifieds
 * @version     v1.4.0
 * @since       v1.4.0
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
COM_setArgNames(array('id', 'query'));

// Get any message ID
if (isset($_REQUEST['msg'])) {
    $msg = COM_applyFilter($_REQUEST['msg']);
} else {
    $msg = '';
}

if (isset($_REQUEST['id'])) {
    $id = COM_sanitizeID($_REQUEST['id']);
} else {
    $id = COM_applyFilter(COM_getArgument('id'));
}

// Establish the output template
$T = new Template($_CONF_ADVT['path'] . '/templates');
$T->set_file('page','index.thtml');
$T->set_var('isAdmin', plugin_ismoderator_classifieds());
$T->set_var('pi_admin_url', $_CONF_ADVT['admin_url'] . '/index.php');
if (isset($LANG_ADVT['index_msg']) && !empty($LANG_ADVT['index_msg'])) {
    $T->set_var('index_msg', $LANG_ADVT['index_msg']);
}
$Ad = new \Classifieds\Ad($id);
$content = $Ad->Detail();

$T->set_var('menu', Classifieds\Menu::User('detail'));
$T->set_var('content', $content);
$T->parse('output', 'page');
echo Classifieds\Menu::siteHeader($pageTitle);
if ($msg != '') {
    echo  COM_showMessage($msg, $_CONF_ADVT['pi_name']);
}
echo $T->finish($T->get_var('output'));
echo Classifieds\Menu::siteFooter();
exit;

