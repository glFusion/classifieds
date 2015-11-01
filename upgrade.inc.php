<?php
/**
*   Upgrade routines for the Classifieds plugin
*   @author     Lee Garner <lee@leegarner.com>
*   @copyright  Copyright (c) 2009-2015 Lee Garner <lee@leegarner.com>
*   @package    classifieds
*   @version    1.0.8
*   @license    http://opensource.org/licenses/gpl-2.0.php 
*               GNU Public License v2 or later
*   @filesource
*/

// Required to get the ADVT_DEFAULTS config values
global $_CONF, $_CONF_ADVT, $_ADVT_DEFAULT, $_DB_dbms;

/** Include the default configuration values */
require_once CLASSIFIEDS_PI_PATH . '/install_defaults.php';
/** Include the table creation strings */
require_once CLASSIFIEDS_PI_PATH . "/sql/{$_DB_dbms}_install.php";

/**
*   Perform the upgrade starting at the current version
*   @param string $current_ver Current installed version to be upgraded
*   @return integer Error code, 0 for success
*/
function classifieds_do_upgrade($current_ver)
{
    $error = 0;

    if ($current_ver < '0.2') {
        // upgrade from 0.1 to 0.2
        $error = classifieds_upgrade_0_2();
        if ($error)
            return $error;
    }

    if ($current_ver < '0.2.2') {
        // upgrade to 0.2.2
        $error = classifieds_upgrade_0_2_2();
        if ($error)
            return $error;
    }

    if ($current_ver < '0.2.3') {
        // upgrade to 0.2.3
        $error = classifieds_upgrade_0_2_3();
        if ($error)
            return $error;
    }

    if ($current_ver < '0.3') {
        // upgrade to 0.3
        $error = classifieds_upgrade_0_3();
        if ($error)
            return $error;
    }

    if ($current_ver < '0.4') {
        $error = classifieds_upgrade_0_4();
        if ($error)
            return $error;
    }

    if ($current_ver < '1.0.1') {
        $error = classifieds_upgrade_1_0_1();
        if ($error)
            return $error;
    }

    if ($current_ver < '1.0.2') {
        $error = classifieds_upgrade_1_0_2();
        if ($error)
            return $error;
    }

    if ($current_ver < '1.0.4') {
        $error = classifieds_upgrade_1_0_4();
        if ($error)
            return $error;
    }
    if ($current_ver < '1.0.8') {
        $error = classifieds_upgrade_1_0_8();
        if ($error)
            return $error;
    }

    return $error;

}


/**
*   Actually perform any sql updates
*   @param string $version  Version being upgraded TO
*   @param array  $sql      Array of SQL statement(s) to execute
*/
function classifieds_do_upgrade_sql($version='Undefined', $sql='')
{
    global $_TABLES, $_CONF_ADVT;

    // We control this, so it shouldn't happen, but just to be safe...
    if ($version == 'Undefined') {
        COM_errorLog("Error updating {$_CONF_ADVT['pi_name']} - Undefined Version");
        return 1;
    }

    // If no sql statements passed in, return success
    if (!is_array($sql))
        return 0;

    // Execute SQL now to perform the upgrade
    COM_errorLOG("--Updating Classified Ads to version $version");
    foreach ($sql as $s) {
        COM_errorLOG("Classifieds Plugin $version update: Executing SQL => $s");
        DB_query($s,'1');
        if (DB_error()) {
            COM_errorLog("SQL Error during Classifieds plugin update",1);
            return 1;
            break;
        }
    }

    return 0;

}


/** Upgrade to version 0.2 */
function classifieds_upgrade_0_2()
{
    global $_TABLES, $_ADVT_DEFAULT, $_CONF_ADVT, $NEWTABLE;

    if (empty($_TABLES['ad_submission'])) {
        COM_errorLog("The ad_submission table is undefined.  Check your config.php");
        return 1;
    }

    $sql[] = $NEWTABLE['ad_submission'];  // new table added this version

    $sql[] = "ALTER TABLE {$_TABLES['ad_ads']}
        CHANGE subject subject varchar(255) DEFAULT '',
        CHANGE url url varchar(100) DEFAULT '',
        CHANGE ad_id ad_id VARCHAR(20) NOT NULL DEFAULT '',
        ADD exp_sent TINYINT(1) UNSIGNED NOT NULL DEFAULT 0,
        DROP approved";

    $sql[] = "ALTER TABLE {$_TABLES['ad_photo']} 
        CHANGE ad_id ad_id VARCHAR(20) NOT NULL DEFAULT ''";

    $sql[] = "ALTER TABLE {$_TABLES['ad_uinfo']} 
        ADD notify_exp TINYINT(1) NOT NULL DEFAULT 0";

    // Create random ad block
    $sql[] = "INSERT INTO
            {$_TABLES['blocks']}
            (is_enabled,name,type,title,tid,blockorder,onleft,phpblockfn,group_id,
            owner_id,perm_owner,perm_group,perm_members,perm_anon)
        VALUES
            ('1','classifieds_random','phpblock',
            'Random Ad','all',0,0,
            'phpblock_classifieds_random',2,2,3,3,2,2)";

    // Convert from numeric ID's to glFusion format sid's 
    $adsql = "SELECT ad_id FROM {$_TABLES['ad_ads']}";
    $result = DB_query($adsql);
    if (!$result)
        return 1;
    while ($row = DB_fetchArray($result)) {
        $new_ad_id = COM_makesid();
        $sql[] = "UPDATE 
                {$_TABLES['ad_ads']}
            SET
                ad_id='$new_ad_id'
            WHERE
                ad_id={$row['ad_id']}";

        $sql[] = "UPDATE
                {$_TABLES['ad_photo']}
            SET
                ad_id='$new_ad_id'
            WHERE
                ad_id={$row['ad_id']}";
    }

    // Add the new classifieds.submit feature
    DB_query("INSERT INTO 
            {$_TABLES['features']} 
            (ft_name, ft_descr) 
        VALUES (
            'classifieds.submit',
            'Bypass Classifieds Submission Queue'
    )",1);
    $feat_id = DB_insertId();
    $group_id = DB_getItem($_TABLES['vars'], 'value', "name='classifieds_gid'");
    DB_query("INSERT INTO 
            {$_TABLES['access']} (
            acc_ft_id, 
            acc_grp_id
        ) VALUES (
            $feat_id, 
            $group_id
    )");

    // Add new configuration items
    $c = config::get_instance();
    if ($c->group_exists($_CONF_ADVT['pi_name'])) {
        $c->add('maxads_pg_exp', $_ADVT_DEFAULT['maxads_pg_exp'], 
                'text', 0, 0, 2, 120, true, $_CONF_ADVT['pi_name']);
        $c->add('maxads_pg_list', $_ADVT_DEFAULT['maxads_pg_list'], 
                'text', 0, 0, 2, 130, true, $_CONF_ADVT['pi_name']);
        $c->add('max_total_duration', $_ADVT_DEFAULT['max_total_duration'],
                'text', 0, 0, 2, 140, true, $_CONF_ADVT['pi_name']);
        $c->add('purge_days', $_ADVT_DEFAULT['purge_days'],
                'text', 0, 0, 2, 150, true, $_CONF_ADVT['pi_name']);
        $c->add('exp_notify_days', $_ADVT_DEFAULT['exp_notify_days'],
                'text', 0, 0, 2, 160, true, $_CONF_ADVT['pi_name']);
        $c->add('loginrequired', $_ADVT_DEFAULT['loginrequired'], 
                'select', 0, 0, 3, 170, true, $_CONF_ADVT['pi_name']);
        $c->add('usercanedit', $_ADVT_DEFAULT['usercanedit'], 
                'select', 0, 0, 3, 180, true, $_CONF_ADVT['pi_name']);
        $c->add('use_gl_cron', $_ADVT_DEFAULT['use_gl_cron'], 
                'select', 0, 0, 3, 190, true, $_CONF_ADVT['pi_name']);
    }

    return classifieds_do_upgrade_sql('0.2', $sql);

}


/** Upgrade to version 0.2.2 */
function classifieds_upgrade_0_2_2()
{
    global $_TABLES, $_CONF_ADVT;

    // Remove classifieds.edit feature from Logged-In Users
    $ft_id = DB_getItem($_TABLES['features'], 'ft_id', "ft_name='".
        $_CONF_ADVT['pi_name'] . ".edit'");
    if ($ft_id > 0) {
        DB_delete($_TABLES['access'], 
                array('acc_ft_id', 'acc_grp_id'),
                array($ft_id, 13));
    }

    return classifieds_do_upgrade_sql('0.2');
}


/** Upgrade to version 0.2.3 */
function classifieds_upgrade_0_2_3()
{
    global $_TABLES, $_ADVT_DEFAULT, $_CONF_ADVT;

    // Add new configuration items
    $c = config::get_instance();
    if ($c->group_exists($_CONF_ADVT['pi_name'])) {

        // The default ad group should be "Classifieds Admin", so
        // find it's id in gl_vars
        $gid = DB_getItem($_TABLES['vars'], 'value', "name='classifieds_gid'");
        if ($gid == '')
            $gid = $_ADVT_DEFAULT['defgrpad'];

        COM_errorLog("Adding new configuration items");
        // Add default ad group
        $c->add('defgrpad', $gid,
                'select', 0, 4, 0, 90, true, $_CONF_ADVT['pi_name']);

        // Add option to email users upon acceptance/rejection
        $c->add('emailusers', $_ADVT_DEFAULT['emailusers'], 
                'select', 0, 0, 10, 95, true, $_CONF_ADVT['pi_name']);

        // Add new fieldset for category defaults
        $c->add('fs_perm_cat', NULL, 'fieldset', 0, 5, NULL, 0, true, $_CONF_ADVT['pi_name']);
        $c->add('defgrpcat', $_ADVT_DEFAULT['defgrpcat'],
                'select', 0, 5, 0, 90, true, $_CONF_ADVT['pi_name']);
        $c->add('default_perm_cat', $_ADVT_DEFAULT['default_perm_cat'],
                '@select', 0, 5, 12, 100, true, $_CONF_ADVT['pi_name']);
    }

    return classifieds_do_upgrade_sql('0.2.3');

}


/** Upgrade to version 0.3 */
function classifieds_upgrade_0_3()
{
    global $_TABLES, $_ADVT_DEFAULT, $_CONF_ADVT, $_CONF;

    // This version moves config vars to classifieds.php and adds other items
    // to config.php.
    $filepath = $_CONF['path'].'/plugins/'.$_CONF_ADVT['pi_name'];
    // Back up the current config.php
    if (file_exists($filepath.'/config.php')) {
        if (!rename($filepath.'/config.php', $filepath.'/config.03.php')) {
            COM_errorLog("v03 upgrade: Failed to back up config.php");
            return "Failed to rename old config.php.";
        }
    }

    // Add new configuration items
    $c = config::get_instance();
    if ($c->group_exists($_CONF_ADVT['pi_name'])) {

        COM_errorLog("Adding new configuration items");
        // Add default ad group
        $c->add('random_blk_width', $_ADVT_DEFAULT['random_blk_width'],
                'text', 0, 0, 2, 32, true, $_CONF_ADVT['pi_name']);
        $c->add('catlist_dispmode', $_ADVT_DEFAULT['catlist_dispmode'],
                'select', 0, 0, 6, 200, true, $_CONF_ADVT['pi_name']);

    }

    $sql[] = "ALTER TABLE {$_TABLES['ad_category']} 
        CHANGE cat_name cat_name varchar(40)";

    $sql[] = "ALTER TABLE {$_TABLES['ad_category']} 
        ADD description TEXT AFTER cat_name,
        ADD fgcolor varchar(10), 
        ADD bgcolor varchar(10)";

    return classifieds_do_upgrade_sql('0.3', $sql);

}


/** Upgrade to version 0.4 */
function classifieds_upgrade_0_4()
{
    global $_TABLES, $NEWTABLE;

    $sql = array();

    // Add new configuration items
    $c = config::get_instance();
    if ($c->group_exists($_CONF_ADVT['pi_name'])) {
        COM_errorLog("Adding new configuration items");

        // Add centerblock option
        $c->add('centerblock', $_ADVT_DEFAULT['centerblock'],
                'select', 0, 0, 3, 210, true, $_CONF_ADVT['pi_name']);
        // Configuration for comment support
        $c->add('commentsupport', $_ADVT_DEFAULT['commentsupport'],
                'select', 0, 0, 3, 220, true, $_CONF_ADVT['pi_name']);
    }

    // Create the Ad Type table ad modify the ad & submission tables
    $sql[] = "DROP TABLE IF EXISTS {$_TABLES['ad_types']}";
    $sql[] = $NEWTABLE['ad_types'];
    $sql[] = "ALTER TABLE {$_TABLES['ad_ads']}
            CHANGE forsale ad_type SMALLINT(5) UNSIGNED DEFAULT 0";
    $sql[] = "ALTER TABLE {$_TABLES['ad_submission']}
            CHANGE forsale ad_type SMALLINT(5) UNSIGNED DEFAULT 0";
    $sql[] = "INSERT INTO {$_TABLES['ad_types']}
            VALUES(0, 'For Sale', 1)";
    $sql[] = "INSERT INTO {$_TABLES['ad_types']}
            VALUES(0, 'Wanted', 1)";
    $sql[] = "UPDATE {$_TABLES['ad_ads']}
            SET ad_type=2 WHERE ad_type=0";

    // Add comment support
    $sql[] = "ALTER TABLE {$_TABLES['ad_ads']}
            ADD comments INT(4) UNSIGNED NOT NULL DEFAULT '0'";
    $sql[] = "ALTER TABLE {$_TABLES['ad_ads']}
            ADD comments_enabled TINYINT(1) UNSIGNED NOT NULL DEFAULT '0'";
    $sql[] = "ALTER TABLE {$_TABLES['ad_submission']}
            ADD comments_enabled TINYINT(1) UNSIGNED NOT NULL DEFAULT '0'";

    // Add support for purchasing or awarding ad time
    //$sql[] = $NEWTABLE['ad_trans'];
    //$sql[] = "ALTER TABLE {$_TABLES['ad_uinfo']}
    //        ADD day_balance INT(11) DEFAULT 0";

   return classifieds_do_upgrade_sql('0.4', $sql);

}


/** Upgrade to version 1.0.1 */
function classifieds_upgrade_1_0_1()
{
    global $_ADVT_DEFAULT, $_CONF_ADVT;

    $sql = array();

    // Add new configuration items
    $c = config::get_instance();
    if ($c->group_exists($_CONF_ADVT['pi_name'])) {
        COM_errorLog("Adding new configuration items");

        // Add left & right block options
        $c->add('leftblocks', $_ADVT_DEFAULT['leftblocks'],
                'select', 0, 0, 3, 230, true, $_CONF_ADVT['pi_name']);
        $c->add('rightblocks', $_ADVT_DEFAULT['rightblocks'],
                'select', 0, 0, 3, 240, true, $_CONF_ADVT['pi_name']);
    }

    return classifieds_do_upgrade_sql('1.0.1', $sql);

}


/** Upgrade to version 1.0.2 */
function classifieds_upgrade_1_0_2()
{
    global $_ADVT_DEFAULT, $_CONF_ADVT, $_TABLES;

    $sql = array();

    // Add new configuration items
    $c = config::get_instance();
    if ($c->group_exists($_CONF_ADVT['pi_name'])) {
        COM_errorLog("Adding new configuration items");

        // Remove individual block selections and combine into one
        $displayblocks = 0;
        if ($_CONF_ADVT['leftblocks'] == 1) $displayblocks += 1;
        if ($_CONF_ADVT['rightblocks'] == 1) $displayblocks += 2;

        $c->del('leftblocks','classifieds');
        $c->del('rightblocks','classifieds');
        $c->add('displayblocks', $displayblocks,
                'select', 0, 0, 13, 230, true, $_CONF_ADVT['pi_name']);
    }

    // Alter What's New config to not show an empty section, if desired
    $sql[] = "UPDATE {$_TABLES['conf_values']} 
            SET selectionArray=14
            WHERE name='hidenewads' AND group_name='{$_CONF_ADVT['pi_name']}'";

    return classifieds_do_upgrade_sql('1.0.2', $sql);

}


/** Upgrade to version 1.0.4 */
function classifieds_upgrade_1_0_4()
{
    global $_ADVT_DEFAULT, $_CONF_ADVT, $_TABLES;

    $sql = array("ALTER TABLE {$_TABLES['ad_uinfo']} ADD notify_comment 
            tinyint(1) UNSIGNED NOT NULL DEFAULT 1 AFTER notify_exp,
            DROP ebayid",
        "ALTER TABLE {$_TABLES['ad_submission']}
            CHANGE subject subject varchar(255) NOT NULL default ''",
        "ALTER TABLE {$_TABLES['ad_ads']}
            CHANGE subject subject varchar(255) NOT NULL default ''",
    );

    // Add new configuration items
    $c = config::get_instance();
    if ($c->group_exists($_CONF_ADVT['pi_name'])) {
        COM_errorLog("Adding new configuration items");

        $c->add('helpurl', $_ADVT_DEFAULT['helpurl'],
                'text', 0, 0, 0, 240, true, $_CONF_ADVT['pi_name']);
        $c->del('ebaylink', $_CONF_ADVT['pi_name']);
        $c->add('disp_fullname', $_ADVT_DEFAULT['disp_fullname'], 
                'select', 0, 0, 3, 175, true, $_CONF_ADVT['pi_name']);
    }

    return classifieds_do_upgrade_sql('1.0.4', $sql);

}

/** Upgrade to version 1.0.8
    Adds config item for max image width on ad detail page
*/
function classifieds_upgrade_1_0_8()
{
    global $_ADVT_DEFAULT, $_CONF_ADVT;

    // Add new configuration items
    $c = config::get_instance();
    if ($c->group_exists($_CONF_ADVT['pi_name'])) {
        COM_errorLog("Adding new configuration items");
        $c->add('detail_img_width', $_ADVT_DEFAULT['detail_img_width'],
                'text', 0, 0, 0, 25, true, $_CONF_ADVT['pi_name']);
    }

    return classifieds_do_upgrade_sql('1.1.0', $sql);

}

 
?>
