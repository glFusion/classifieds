<?php
/**
*   Installation program for the Classified plugin.
*
*   @author     Lee Garner <lee@leegarner.com>
*   @copyright  Copyright (c) 2009 Lee Garner <lee@leegarner.com>
*   @package    classifieds
*   @version    0.3
*   @license    http://opensource.org/licenses/gpl-2.0.php 
*               GNU Public License v2 or later
*   @filesource
*/

/** Import core glFusion libraries */
require_once('../../../lib-common.php');

// Only let Root users access this page
if (!SEC_inGroup('Root')) {
    // Someone is trying to illegally access this page
    COM_errorLog("Someone has tried to illegally access the Classifieds install/uninstall page.  User id: {$_USER['uid']}, Username: {$_USER['username']}, IP: $REMOTE_ADDR",1);
    COM_404();
    exit;
}


/** Import automatic installation function */
require_once $_CONF['path'].'/plugins/classifieds/autoinstall.php';

USES_lib_install();


/*
* Main Function
*/
if (SEC_checkToken()) {
    $action = isset($_REQUEST['action']) ? trim($_REQUEST['action']) : '';
    switch ($action) {
    case 'install':
        if (plugin_install_classifieds()) {
            echo COM_refresh($_CONF['site_admin_url'] . '/plugins.php?msg=44');
            exit;
        } else {
            echo COM_refresh($_CONF['site_admin_url'] . '/plugins.php?msg=72');
            exit;
        }
        break;

    case 'uninstall':
        USES_lib_plugin();
        if (PLG_uninstall($_CONF_ADVT['pi_name'])) {
            echo COM_refresh($_CONF['site_admin_url'] . '/plugins.php?msg=45');
            exit;
        } else {
            echo COM_refresh($_CONF['site_admin_url'] . '/plugins.php?msg=73');
            exit;
        }
        break;
    }
}

echo COM_refresh($_CONF['site_admin_url'] . '/plugins.php');

?>
