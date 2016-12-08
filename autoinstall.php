<?php
/**
*   Automatically install the Classifieds plugin
*
*   @author     Lee Garner <lee@leegarner.com>
*   @copyright  Copyright (c) 2009 Lee Garner <lee@leegarner.com>
*   @package    classifieds
*   @version    1.0.0
*   @license    http://opensource.org/licenses/gpl-2.0.php 
*               GNU Public License v2 or later
*   @filesource
*/
if (!defined ('GVERSION')) {
    die ('This file can not be used on its own.');
}

global $_DB_dbms;

/** Import plugin functions.  Not already imported if plugin isn't
*   installed yet.
*/
require_once $_CONF['path'].'plugins/classifieds/functions.inc';
/** Import plugin database definition */
require_once CLASSIFIEDS_PI_PATH . '/sql/'. $_DB_dbms. '_install.php';

/** Plugin installation options
*   @global array $INSTALL_plugin['classifieds']
*/
$INSTALL_plugin['classifieds'] = array(
    'installer' => array('type' => 'installer', 
            'version' => '1', 
            'mode' => 'install'),

    'plugin' => array('type' => 'plugin', 
            'name' => $_CONF_ADVT['pi_name'],
            'ver' => $_CONF_ADVT['pi_version'], 
            'gl_ver' => $_CONF_ADVT['gl_version'],
            'url' => $_CONF_ADVT['pi_url'], 
            'display' => $_CONF_ADVT['pi_display_name']),

    array('type' => 'table', 
            'table' => $_TABLES['ad_category'], 
            'sql' => $NEWTABLE['ad_category']),

    array('type' => 'table', 
            'table' => $_TABLES['ad_ads'], 
            'sql' => $NEWTABLE['ad_ads']),

    array('type' => 'table', 
            'table' => $_TABLES['ad_submission'], 
            'sql' => $NEWTABLE['ad_submission']),

    array('type' => 'table', 
            'table' => $_TABLES['ad_notice'], 
            'sql' => $NEWTABLE['ad_notice']),

    array('type' => 'table', 
            'table' => $_TABLES['ad_photo'], 
            'sql' => $NEWTABLE['ad_photo']),

    array('type' => 'table', 
            'table' => $_TABLES['ad_uinfo'], 
            'sql' => $NEWTABLE['ad_uinfo']),

    array('type' => 'table', 
            'table' => $_TABLES['ad_types'], 
            'sql' => $NEWTABLE['ad_types']),

    array('type' => 'group', 
            'group' => 'classifieds Admin', 
            'desc' => 'Users in this group can administer the Classifieds plugin',
            'variable' => 'admin_group_id', 
            'admin' => true,
            'addroot' => true),

    array('type' => 'feature', 
            'feature' => 'classifieds.admin', 
            'desc' => 'Classifieds Admin',
            'variable' => 'admin_feature_id'),

    array('type' => 'feature', 
            'feature' => 'classifieds.edit', 
            'desc' => 'Classifieds Editor',
            'variable' => 'edit_feature_id'),

    array('type' => 'feature', 
            'feature' => 'classifieds.submit', 
            'desc' => 'Bypass Classifieds Submission Queue',
            'variable' => 'submit_feature_id'),

    array('type' => 'mapping', 
            'group' => 'admin_group_id', 
            'feature' => 'admin_feature_id',
            'log' => 'Adding feature to the admin group'),

    array('type' => 'mapping', 
            'group' => 'admin_group_id', 
            'feature' => 'edit_feature_id',
            'log' => 'Adding feature to the admin group'),

    array('type' => 'mapping', 
            'group' => 'admin_group_id', 
            'feature' => 'submit_feature_id',
            'log' => 'Adding feature to the admin group'),

    array('type' => 'block', 
            'name' => 'classifieds_random', 
            'title' => $LANG_ADVT['random_ad'],
            'phpblockfn' => 'phpblock_classifieds_random', 
            'block_type' => 'phpblock',
            'group_id' => 'admin_group_id'),

    array('type' => 'mkdir',
            'dirs' => array(
                $_CONF['path'] . 'data/' . $_CONF_ADVT['pi_name'],
                $_CONF['path'] . 'data/' . $_CONF_ADVT['pi_name'] . '/images',
                CLASSIFIEDS_IMGPATH . '/user',
                CLASSIFIEDS_IMGPATH . '/cat',
            ),
    ),

    array('type' => 'sql',
            'sql' => $DEFVALUES['ad_types']),

    array('type' => 'sql',
            'sql' => $DEFVALUES['category']),
);


/**
*   Puts the datastructures for this plugin into the glFusion database.
*   Note: Corresponding uninstall routine is in functions.inc.
*
*   @return boolean     True if successful, False otherwise
*/
function plugin_install_classifieds()
{
    global $INSTALL_plugin, $_CONF_ADVT;

    COM_errorLog("Attempting to install the {$_CONF_ADVT['pi_display_name']} plugin", 1);

    $ret = INSTALLER_install($INSTALL_plugin[$_CONF_ADVT['pi_name']]);
    if ($ret > 0) {
        return false;
    }

    return true;
}


/**
*   Loads the configuration records for the Online Config Manager.
*
*   @return boolean     True = proceed with install, false = an error occured
*/
function plugin_load_configuration_classifieds()
{
    global $_CONF, $_CONF_ADVT, $_TABLES;

    require_once CLASSIFIEDS_PI_PATH . '/install_defaults.php';

    // Get the admin group ID that was saved previously.
    $group_id = (int)DB_getItem($_TABLES['groups'], 'grp_id', 
            "grp_name='{$_CONF_ADVT['pi_name']} Admin'");

    return plugin_initconfig_classifieds($group_id);
}


/**
*   Post-installation actions
*   Create user information records for the plugin
*/
function plugin_postinstall_classifieds()
{
    global $_TABLES;

    // Make sure there's a user information record created for each user
    $vals = array();
    $sql = "SELECT uid FROM {$_TABLES['users']}
            WHERE uid > 1";
    $res = DB_query($sql);
    while ($user = DB_fetchArray($res, false)) {
        $vals[] = "({$user['uid']}, UNIX_TIMESTAMP())";
    }
    if (!empty($vals)) {
        $val_str = implode(',', $vals);
        DB_query("INSERT INTO {$_TABLES['ad_uinfo']}
                (uid, lastup_date)
                VALUES $val_str");
    }
}

?>
