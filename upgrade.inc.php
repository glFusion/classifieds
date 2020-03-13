<?php
/**
 * Upgrade routines for the Classifieds plugin.
 *
 * @author      Lee Garner <lee@leegarner.com>
 * @copyright   Copyright (c) 2009-2017 Lee Garner <lee@leegarner.com>
 * @package     classifieds
 * @version     v1.1.3
 * @license     http://opensource.org/licenses/gpl-2.0.php
 *              GNU Public License v2 or later
 * @filesource
 */

if (!defined('GVERSION')) {
    die('This file can not be used on its own.');
}

global $_CONF, $_CONF_ADVT;

/** Include the table creation strings */
require_once __DIR__ . "/sql/mysql_install.php";

/**
 * Perform the upgrade starting at the current version.
 *
 * @param   boolean $dvlp   True to ignore errors for development update
 * @return  boolean     True on success, False on failure
 */
function classifieds_do_upgrade($dvlp = false)
{
    global $_CONF_ADVT, $_PLUGIN_INFO;

    if (isset($_PLUGIN_INFO[$_CONF_ADVT['pi_name']])) {
        if (is_array($_PLUGIN_INFO[$_CONF_ADVT['pi_name']])) {
            // glFusion > 1.6.5
            $current_ver = $_PLUGIN_INFO[$_CONF_ADVT['pi_name']]['pi_version'];
        } else {
            // legacy
            $current_ver = $_PLUGIN_INFO[$_CONF_ADVT['pi_name']];
        }
    } else {
        return false;
    }
    $installed_ver = plugin_chkVersion_classifieds();

    if (!COM_checkVersion($current_ver, '0.2')) {
        $current_ver = '0.2';
        if (!classifieds_upgrade_0_2($dvlp)) return false;
    }

    if (!COM_checkVersion($current_ver, '0.2.2')) {
        $current_ver = '0.2.2';
        if (!classifieds_upgrade_0_2_2(dvlp)) return false;
    }

    if (!COM_checkVersion($current_ver, '0.2.3')) {
        $current_ver = '0.2.3';
        if (!classifieds_do_set_version($current_ver)) return false;
    }

    if (!COM_checkVersion($current_ver, '0.3')) {
        $current_ver = '0.3';
        if (!classifieds_upgrade_0_3($dvlp)) return false;
    }

    if (!COM_checkVersion($current_ver, '0.4')) {
        $current_ver = '0.4';
        if (!classifieds_upgrade_0_4($dvlp)) return false;
    }

    if (!COM_checkVersion($current_ver, '1.0.1')) {
        $current_ver = '1.0.1';
        if (!classifieds_set_version($current_ver)) return false;
    }

    if (!COM_checkVersion($current_ver, '1.0.2')) {
        $current_ver = '1.0.2';
        if (!classifieds_do_set_version($current_ver)) return false;;
    }

    if (!COM_checkVersion($current_ver, '1.0.4')) {
        $current_ver = '1.0.4';
        if (!classifieds_upgrade_1_0_4($dvlp)) return false;
    }

    if (!COM_checkVersion($current_ver, '1.1.0')) {
        $current_ver = '1.1.0';
        if (!classifieds_upgrade_1_1_0($dvlp)) return false;
    }

    if (!COM_checkVersion($current_ver, '1.1.2')) {
        $current_ver = '1.1.2';
        if (!classifieds_upgrade_1_1_2($dvlp)) return false;
    }

    if (!COM_checkVersion($current_ver, '1.1.3')) {
        $current_ver = '1.1.3';
        if (!classifieds_upgrade_1_1_3($dvlp)) return false;
    }

    if (!COM_checkVersion($current_ver, '1.3.0')) {
        $current_ver = '1.3.0';
        if (!classifieds_upgrade_1_3_0($dvlp)) return false;
    }

    // Final version update to catch updates that don't go through
    // any of the update functions, e.g. code-only updates
    if (!COM_checkVersion($current_ver, $installed_ver)) {
        if (!classifieds_do_set_version($installed_ver)) {
            COM_errorLog($_CONF_ADVT['pi_display_name'] .
                    " Error performing final update $current_ver to $installed_ver");
            return false;
        }
    }

    // Update the plugin configuration
    USES_lib_install();
    global $classifiedsConfigItems;
    require_once __DIR__ . '/install_defaults.php';
    _update_config('classifieds', $classifiedsConfigItems);

    classifieds_remove_old_files();
    Classifieds\Cache::clear();
    COM_errorLog("Successfully updated the {$_CONF_ADVT['pi_display_name']} Plugin", 1);
    CTL_clearCache($_CONF_ADVT['pi_name']);
    return true;
}


/**
 * Actually perform any sql updates.
 *
 * @param   string  $version    Version being upgraded TO
 * @param   array   $sql        Array of SQL statement(s) to execute
 * @param   boolean $dvlp       True to ignore SQL errors and continue
 * @return  boolean         True on success, False on failure
 */
function classifieds_do_upgrade_sql($version='Undefined', $sql='', $dvlp = false)
{
    global $_TABLES, $_CONF_ADVT;

    // We control this, so it shouldn't happen, but just to be safe...
    if ($version == 'Undefined') {
        COM_errorLog("Error updating {$_CONF_ADVT['pi_name']} - Undefined Version");
        return false;
    }

    // If no sql statements passed in, return success
    if (!is_array($sql)) {
        return true;
    }

    // Execute SQL now to perform the upgrade
    COM_errorLOG("--Updating Classified Ads to version $version");
    foreach ($sql as $s) {
        COM_errorLOG("Classifieds Plugin $version update: Executing SQL => $s");
        DB_query($s,'1');
        if (DB_error()) {
            COM_errorLog("SQL Error during Classifieds plugin update",1);
            if (!$dvlp) return false;
        }
    }
    return true;
}


/** Upgrade to version 0.2 */
function classifieds_upgrade_0_2()
{
    global $_TABLES, $_CONF_ADVT, $NEWTABLE;

    if (empty($_TABLES['ad_submission'])) {
        COM_errorLog("The ad_submission table is undefined.  Check your config.php");
        return false;
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
        return false;
    while ($row = DB_fetchArray($result)) {
        $new_ad_id = COM_makesid();
        $sql[] = "UPDATE {$_TABLES['ad_ads']}
            SET ad_id='$new_ad_id'
            WHERE ad_id={$row['ad_id']}";

        $sql[] = "UPDATE {$_TABLES['ad_photo']}
            SET ad_id='$new_ad_id'
            WHERE ad_id={$row['ad_id']}";
    }

    // Add the new classifieds.submit feature
    DB_query("INSERT INTO {$_TABLES['features']}
                (ft_name, ft_descr)
            VALUES (
                'classifieds.submit',
                'Bypass Classifieds Submission Queue'
            )",1);
    $feat_id = DB_insertId();
    $group_id = DB_getItem($_TABLES['vars'], 'value', "name='classifieds_gid'");
    DB_query("INSERT INTO {$_TABLES['access']} (
                acc_ft_id, acc_grp_id
            ) VALUES (
                $feat_id, $group_id
            )");

    if (!classifieds_do_upgrade_sql('0.2', $sql)) return false;
    return classifieds_do_set_version('0.2');
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

    return classifieds_do_set_version('0.2');
}


/** Upgrade to version 0.3 */
function classifieds_upgrade_0_3()
{
    global $_TABLES, $_CONF_ADVT, $_CONF;

    // This version moves config vars to classifieds.php and adds other items
    // to config.php.
    $filepath = $_CONF['path'].'/plugins/'.$_CONF_ADVT['pi_name'];
    // Back up the current config.php
    if (file_exists($filepath.'/config.php')) {
        if (!@rename($filepath.'/config.php', $filepath.'/config.03.php')) {
            COM_errorLog("v03 upgrade: Failed to back up config.php");
            return "Failed to rename old config.php.";
        }
    }

    // Add new configuration items
    $sql[] = "ALTER TABLE {$_TABLES['ad_category']}
        CHANGE cat_name cat_name varchar(40)";

    $sql[] = "ALTER TABLE {$_TABLES['ad_category']}
        ADD description TEXT AFTER cat_name,
        ADD fgcolor varchar(10),
        ADD bgcolor varchar(10)";

    if (!classifieds_do_upgrade_sql('0.3', $sql)) return false;
    return classifieds_do_set_version('0.3');
}


/**
 * Upgrade to version 0.4.
 *
 * @return   Boolean True on success, False on failure
 */
function classifieds_upgrade_0_4()
{
    global $_TABLES, $NEWTABLE;

    $sql = array();

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

    if (!classifieds_do_upgrade_sql('0.4', $sql)) return false;
    return classifieds_do_set_version('0.4');
}


/**
 * Upgrade to version 1.0.4.
 *
 * @return   Boolean True on success, False on failure
 */
function classifieds_upgrade_1_0_4()
{
    global $_CONF_ADVT, $_TABLES;

    $sql = array("ALTER TABLE {$_TABLES['ad_uinfo']} ADD notify_comment
            tinyint(1) UNSIGNED NOT NULL DEFAULT 1 AFTER notify_exp,
            DROP ebayid",
        "ALTER TABLE {$_TABLES['ad_submission']}
            CHANGE subject subject varchar(255) NOT NULL default ''",
        "ALTER TABLE {$_TABLES['ad_ads']}
            CHANGE subject subject varchar(255) NOT NULL default ''",
    );

    if (!classifieds_do_upgrade_sql('1.0.4', $sql)) return false;
    return classifieds_do_set_version('1.0.4');
}

/**
 * Upgrade to version 1.1.0.
 * Adds config item for max image width on ad detail page
 * Removes image path config options
 *
 * @return   Boolean True on success, False on failure
 */
function classifieds_upgrade_1_1_0()
{
    global $_CONF_ADVT, $_TABLES;

    $old_imgpath = pathinfo($_CONF_ADVT['image_dir']);
    $old_catpath = pathinfo($_CONF_ADVT['catimgpath']);
    $new_imgpath = $_CONF_ADVT['imgpath']  . '/user';
    $new_catpath = $_CONF_ADVT['imgpath'] . '/cat';
    $mv_userimages = isset($_CONF_ADVT['image_dir']) &&
            $_CONF_ADVT['image_dir'] != $new_imgpath ? true : false;
    $mv_catimages = isset($_CONF_ADVT['catimgpath']) &&
            $_CONF_ADVT['catimgpath'] != $new_catpath ? true : false;

    if ($mv_catimages) {
        @mkdir($new_catpath, 755, true);
        if (!is_dir($new_catpath)) {
            COM_errorLog("Error creating new dir $new_catpath");
            return false;
        }
        $files = glob($_CONF_ADVT['catimgpath'] . '/*');
        if ($files) {
            foreach ($files as $file) {
                if (is_file($file)) {
                    $parts = pathinfo($file);
                    if ($parts['basename'] != 'index.html') {
                        @rename($file, $new_catpath . '/' . $parts['basename']);
                    }
                }
            }
        }
    }

    // Move ad images to new location
    if ($mv_userimages) {
        @mkdir($new_imgpath, 755, true);
        if (!is_dir($new_imgpath)) {
            COM_errorLog("Error creating new dir $new_imgpath");
            return false;
        }
        $files = glob($_CONF_ADVT['image_dir'] . '/*');
        if ($files) {
            foreach ($files as $file) {
                if (is_file($file)) {
                    $parts = pathinfo($file);
                    if ($parts['basename'] != 'index.html') {
                        @rename($file, $new_imgpath . '/' . $parts['basename']);
                    }
                }
            }
        }
    }

    // Make sure there's a user information record created for each user
    $vals = array();
    $sql = "SELECT u.uid, i.uid AS uinfo_id FROM {$_TABLES['users']} u
            LEFT JOIN {$_TABLES['ad_uinfo']} i
            ON u.uid = i.uid
            WHERE u.uid > 1";
    $res = DB_query($sql);
    while ($user = DB_fetchArray($res, false)) {
        if ($user['uinfo_id'] === NULL) {
            // No user info record exists, create one
            $vals[] = "({$user['uid']},1)";
        }
    }
    if (!empty($vals)) {
        $val_str = implode(',', $vals);
        $uinfo_sql = "INSERT INTO {$_TABLES['ad_uinfo']}
                (uid, notify_exp)
                VALUES $val_str";
    }

    // Get new values for conf_values table
    $sql = array(
        "UPDATE {$_TABLES['ad_ads']} SET uid = owner_id",
        "ALTER TABLE {$_TABLES['ad_ads']}
            CHANGE ad_id ad_id varchar(128) NOT NULL,
            CHANGE descript description TEXT NOT NULL,
            DROP perm_owner, DROP perm_group,
            DROP perm_members, DROP perm_anon,
            DROP owner_id, DROP group_id",
        "UPDATE {$_TABLES['ad_submission']} SET uid = owner_id",
        "ALTER TABLE {$_TABLES['ad_submission']}
            CHANGE ad_id ad_id varchar(128) NOT NULL,
            CHANGE descript description TEXT NOT NULL,
            DROP perm_owner, DROP perm_group,
            DROP perm_members, DROP perm_anon,
            DROP owner_id, DROP group_id",
        "ALTER TABLE {$_TABLES['ad_notice']}
            DROP notice_id,
            ADD PRIMARY KEY(cat_id, uid)",
        "ALTER TABLE {$_TABLES['ad_uinfo']}
            CHANGE notify_exp notify_exp tinyint(1) UNSIGNED DEFAULT 1",
        "ALTER TABLE {$_TABLES['ad_types']}
            CHANGE descrip description varchar(255) DEFAULT NULL",
        "ALTER TABLE {$_TABLES['ad_category']}
            ADD parent_map TEXT DEFAULT NULL AFTER bgcolor",
        $uinfo_sql,
    );
    if (!classifieds_do_upgrade_sql('1.1.0', $sql)) return false;
    return classifieds_do_set_version('1.1.0');
}


/**
 * Upgrade to version 1.1.2.
 * Adds comments field to submissions to match ad table.
 *
 * @return   Boolean True on success, False on failure
 */
function classifieds_upgrade_1_1_2()
{
    global $_CONF_ADVT, $_TABLES;

    $sql = array(
        "ALTER TABLE {$_TABLES['ad_submission']}
            ADD comments INT(4) UNSIGNED NOT NULL DEFAULT '0' AFTER exp_sent",
    );
    if (!classifieds_do_upgrade_sql('1.1.2', $sql)) return false;
    return classifieds_do_set_version('1.1.2');
}


/**
 * Upgrade to version 1.1.3
 * Migrates notification subscriptions to glFusion subscription system.
 *
 * @return   Boolean True on success, False on failure
 */
function classifieds_upgrade_1_1_3()
{
    global $_CONF_ADVT, $_TABLES;

    COM_errorLog("Updating {$_CONF_ADVT['pi_display_name']} to 1.1.3");
    $sql = "SELECT n.cat_id, n.uid, c.description
            FROM {$_TABLES['ad_notice']} n
            LEFT JOIN {$_TABLES['ad_category']} c
                ON c.cat_id = n.cat_id";
    $res = DB_query($sql);
    while ($A = DB_fetchArray($res, false)) {
        PLG_subscribe($_CONF_ADVT['pi_name'], 'category', $A['cat_id'],
                $A['uid'], $_CONF_ADVT['pi_name'], $A['description']);
    }
    $sql = array(
        "ALTER TABLE {$_TABLES['ad_submission']} DROP sentnotify",
        "ALTER TABLE {$_TABLES['ad_ads']} DROP sentnotify",
        "DROP TABLE {$_TABLES['ad_notice']}",
    );
    if (!classifieds_do_upgrade_sql('1.1.3', $sql)) return false;
    return classifieds_do_set_version('1.1.3');
}


/**
 * Update to version 1.3.0.
 * Adds modified preorder tree traversal to category table
 *
 * @param   boolean $dvlp   True to ignore sql errors
 * @return  boolean     True on success, False on failure
 */
function classifieds_upgrade_1_3_0($dvlp = false)
{
    global $_TABLES;

    $sql = array(
        // Add tree fields, drop auto_increment key for renumbering
        "ALTER TABLE {$_TABLES['ad_category']} ADD lft int(5) unsigned NOT NULL default 0",
        "ALTER TABLE {$_TABLES['ad_category']} ADD rgt int(5) unsigned NOT NULL default 0",
        "ALTER TABLE {$_TABLES['ad_category']} DROP parent_map",
        "ALTER TABLE {$_TABLES['ad_category']} DROP add_date",
        "ALTER TABLE {$_TABLES['ad_category']} DROP keywords",
        "ALTER TABLE {$_TABLES['ad_category']} DROP fgcolor",
        "ALTER TABLE {$_TABLES['ad_category']} DROP bgcolor",
        "ALTER TABLE {$_TABLES['ad_category']} ADD KEY (lft)",
        "ALTER TABLE {$_TABLES['ad_category']} ADD KEY (rgt)",
        "ALTER TABLE {$_TABLES['ad_uinfo']} DROP fax",
        "ALTER TABLE {$_TABLES['ad_types']} ADD fgcolor varchar(10) NOT NULL default '' AFTER description",
        "ALTER TABLE {$_TABLES['ad_types']} ADD bgcolor varchar(10) NOT NULL default '' AFTER fgcolor",
        "ALTER TABLE {$_TABLES['ad_ads']} CHANGE ad_id ad_id VARCHAR(20) NOT NULL DEFAULT ''",
        "ALTER TABLE {$_TABLES['ad_ads']} ADD KEY (cat_id)",
        "ALTER TABLE {$_TABLES['ad_ads']} ADD KEY (add_date)",
        "ALTER TABLE {$_TABLES['ad_ads']} ADD KEY (exp_date)",
        "ALTER TABLE {$_TABLES['ad_ads']} ADD KEY (uid)",
        "ALTER TABLE {$_TABLES['ad_submission']} CHANGE ad_id ad_id VARCHAR(20) NOT NULL DEFAULT ''",
    );
    if (!classifieds_do_upgrade_sql('1.3.0', $sql, $dvlp)) return false;
    // Populate the tree values
    \Classifieds\Category::rebuildTree(0, 0);
    return classifieds_do_set_version('1.3.0');
}


/**
 * Update the plugin version number in the database.
 * Called at each version upgrade to keep up to date with
 * successful upgrades.
 *
 * @param   string  $ver    New version to set
 * @return  boolean         True on success, False on failure
 */
function classifieds_do_set_version($ver)
{
    global $_TABLES, $_CONF_ADVT;

    COM_errorLog("Setting the classifieds plugin version to $ver");
    // now update the current version number.
    $sql = "UPDATE {$_TABLES['plugins']} SET
            pi_version = '" . DB_escapeString($ver) . "',
            pi_gl_version = '{$_CONF_ADVT['gl_version']}',
            pi_homepage = '{$_CONF_ADVT['pi_url']}'
        WHERE pi_name = '{$_CONF_ADVT['pi_name']}'";

    $res = DB_query($sql, 1);
    if (DB_error()) {
        COM_errorLog("Error updating the {$_CONF_ADVT['pi_display_name']} Plugin version",1);
        return false;
    } else {
        return true;
    }
}


/**
 * Remove deprecated files.
 * No return, and errors here don't really matter
 */
function classifieds_remove_old_files()
{
    global $_CONF;

    $paths = array(
        __DIR__ => array(
            // 1.2.2
            'js/picker.js',
            // 1.3.0
            'admin.php',
            'js/catfldxml.js',
            'js/moredays.js',
            'templates/account_settings.uikit.thtml',
	    'templates/admin/adminedit.uikit.thtml',
	    'templates/admin/catEditForm.uikit.thtml',
            'templates/adtypeform.uikit.thtml',
	    'templates/breadcrumbs.uikit.thtml',
	    'templates/detail.uikit.thtml'.
	    'templates/detail/v1/detail.uikit.thtml',
	    'templates/detail/v2/detail.uikit.thtml',
            'templates/edit.uikit.thtml',
            'templates/adList.thtml',
            'classes/Lists/Ads',
        ),
        // public_html/classifieds
        $_CONF['path_html'] . 'classifieds' => array(
            // 1.3.0
            'updatecatxml.php',
            'docs/english/config.legacy.html',
        ),
        // admin/plugins/classifieds
        $_CONF['path_html'] . 'admin/plugins/classifieds' => array(
        ),
    );

    foreach ($paths as $path=>$files) {
        foreach ($files as $file) {
            // Remove the file or directory
            CLASSIFIEDS_rmdir("$path/$file");
        }
    }
}


/**
 * Remove a file, or recursively remove a directory.
 *
 * @param   string  $dir    Directory name
 */
function CLASSIFIEDS_rmdir($dir)
{
    if (is_dir($dir)) {
        $objects = scandir($dir);
        foreach ($objects as $object) {
            if ($object != "." && $object != "..") {
                if (is_dir($dir . '/' . $object)) {
                    CLASSIFIEDS_rmdir($dir . '/' . $object);
                } else {
                    @unlink($dir . '/' . $object);
                }
            }
        }
        @rmdir($dir);
    } elseif (is_file($dir)) {
        @unlink($dir);
    }
}


?>
