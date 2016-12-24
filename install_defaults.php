<?php
/**
*   Installation defaults for the Classifieds plugin
*   @author     Lee Garner <lee@leegarner.com>
*   @copyright  Copyright (c) 2009-2016 Lee Garner <lee@leegarner.com>
*   @package    classifieds
*   @version    1.0.0
*   @license    http://opensource.org/licenses/gpl-2.0.php 
*               GNU Public License v2 or later
*   @filesource
*/

if (!defined('GVERSION')) {
    die('This file can not be used on its own!');
}

/**
 *  Classifieds default settings
 *
 *  Initial Installation Defaults used when loading the online configuration
 *  records. These settings are only used during the initial installation
 *  and not referenced any more once the plugin is installed
 *  @global array $_ADVT_DEFAULT
 *
 */
global $_ADVT_DEFAULT, $_CONF_ADVT;
$_ADVT_DEFAULT = array();

// Max image dimensions for display popups (slimbox)
$_ADVT_DEFAULT['img_max_height'] = 600;
$_ADVT_DEFAULT['img_max_width'] = 800;
// Max image width for ad detail page, typically larger than thumbnail
$_ADVT_DEFAULT['detail_img_width'] = 150;
$_ADVT_DEFAULT['random_blk_width'] = 100;   // Max width for random ad block
$_ADVT_DEFAULT['thumb_max_size'] = 100; // Max dimension for thumbnails
$_ADVT_DEFAULT['imagecount']    = 3;    // max number of user images
$_ADVT_DEFAULT['submission'] = 1;       // Submission queue. 0= disabled  1= enabled
$_ADVT_DEFAULT['default_duration'] = 30;   // default ad duration, in days
$_ADVT_DEFAULT['max_cust_fields'] = 4;     // maximum custom fields to allow
$_ADVT_DEFAULT['newcatdays'] = 3;      // days to consider a category as new
$_ADVT_DEFAULT['newadsinterval']    = 14; // days to consider an ad as new
$_ADVT_DEFAULT['hidenewads']    = 0;    // 1 = hide from the What's New block

// Paths
$path_html = $_CONF['path_html'] . $_CONF_ADVT['pi_name'];
$_ADVT_DEFAULT['image_dir'] = $path_html . '/images/user';
$_ADVT_DEFAULT['image_url'] = $_CONF['site_url'] . '/classifieds/images/user';
$_ADVT_DEFAULT['catimgpath'] = $path_html . '/images/cat';
$_ADVT_DEFAULT['catimgurl'] = $_CONF['site_url'] . '/classifieds/images/cat';


/**
 *  Notify admins of new submissions
 *      0 - never notify admins
 *      1 - only if $_ADVT_DEFAULT['submission'] is enabled
 *      2 - always
 */
$_ADVT_DEFAULT['emailadmin']   = 2;

// Email users upon acceptance?  1=Yes, 0=No, Default No
$_ADVT_DEFAULLT['emailusers'] = 0;

$_ADVT_DEFAULT['hideuserfunction'] = 1;     // 1 = hide from User Functions menu

// Set the default permissions
$_ADVT_DEFAULT['default_permissions'] =  array (3, 2, 2, 2);
$_ADVT_DEFAULT['default_perm_cat'] =  array (3, 3, 2, 2);

// Default values for max ads shown per page (expanded & list views)
$_ADVT_DEFAULT['maxads_pg_list'] = 20;
$_ADVT_DEFAULT['maxads_pg_exp'] = 20;

// Default max duration for a single ad run, and total including renewals
$_ADVT_DEFAULT['max_ad_duration'] = 30;
$_ADVT_DEFAULT['max_total_duration'] = 120;

// Set the number of days after ad expiration when it will be purged
$_ADVT_DEFAULT['purge_days'] = 15;

// Number of days before an ad expiration for users to be notified.
$_ADVT_DEFAULT['exp_notify_days'] = -1;

// Login required for all access?
$_ADVT_DEFAULT['loginrequired'] = 0;

// True if regular users can edit their own ads
$_ADVT_DEFAULT['usercanedit'] = 1;

// Use glFusion's built-in cron facility?
$_ADVT_DEFAULT['use_gl_cron'] = 1;

// Default groups for ad and category: Logged-In Users.
// For Ads this is the group that is allowed to view ads.
// For Categories this is the group that may submit adds to a category
$_ADVT_DEFAULT['defgrpad'] = 1;
$_ADVT_DEFAULT['defgrpcat'] = 13;

// Category display type.  This will be a value like 'normal', 'blocks', etc.
$_ADVT_DEFAULT['catlist_dispmode'] = 'normal';

// Replace home page? 1=yes, 0=no
$_ADVT_DEFAULT['centerblock'] = 0;

// Control which blocks to display- both by default
$_ADVT_DEFAULT['displayblocks'] = 3;

// Support comments? 1=yes, 0=false
$_ADVT_DEFAULT['commentsupport'] = 1;

$_ADVT_DEFAULT['helpurl'] = '';
$_ADVT_DEFAULT['disp_fullname'] = 1;


/**
 *  Initialize Classifieds plugin configuration
 *
 *  Creates the database entries for the configuation if they don't already
 *  exist. Initial values will be taken from $_CONF_ADVT if available (e.g. from
 *  an old config.php), uses $_ADVT_DEFAULT otherwise.
 *
 *  @param  integer $group_id   Group ID to use as the plugin's admin group
 *  @return boolean             true: success; false: an error occurred
 */
function plugin_initconfig_classifieds($group_id = 0)
{
    global $_CONF, $_CONF_ADVT, $_ADVT_DEFAULT;

    if (is_array($_CONF_ADVT) && (count($_CONF_ADVT) > 1)) {
        $_ADVT_DEFAULT = array_merge($_ADVT_DEFAULT, $_CONF_ADVT);
    }

    // Use configured default if a valid group ID wasn't presented
    if ($group_id == 0)
        $group_id = $_ADVT_DEFAULT['defgrpad'];

    $c = config::get_instance();

    if (!$c->group_exists($_CONF_ADVT['pi_name'])) {

        $c->add('sg_main', NULL, 'subgroup', 0, 0, NULL, 0, true, $_CONF_ADVT['pi_name']);
        $c->add('fs_main', NULL, 'fieldset', 0, 0, NULL, 0, true, $_CONF_ADVT['pi_name']);
        $c->add('img_max_height', $_ADVT_DEFAULT['img_max_height'],
                'text', 0, 0, 0, 10, true, $_CONF_ADVT['pi_name']);
        $c->add('img_max_width', $_ADVT_DEFAULT['img_max_width'], 
                'text', 0, 0, 0, 20, true, $_CONF_ADVT['pi_name']);
        $c->add('detail_img_width', $_ADVT_DEFAULT['detail_img_width'],
                'text', 0, 0, 0, 25, true, $_CONF_ADVT['pi_name']);
        $c->add('thumb_max_size', $_ADVT_DEFAULT['thumb_max_size'], 
                'text', 0, 0, 0, 30, true, $_CONF_ADVT['pi_name']);
        $c->add('random_blk_width', $_ADVT_DEFAULT['random_blk_width'], 
                'text', 0, 0, 0, 32, true, $_CONF_ADVT['pi_name']);
        $c->add('imagecount', $_ADVT_DEFAULT['imagecount'], 
                'text', 0, 0, 0, 35, true, $_CONF_ADVT['pi_name']);
        $c->add('submission', $_ADVT_DEFAULT['submission'], 'select',
                0, 0, 0, 40, true, $_CONF_ADVT['pi_name']);
        $c->add('default_duration', $_ADVT_DEFAULT['default_duration'], 
                'text', 0, 0, 0, 50, true, $_CONF_ADVT['pi_name']);
        $c->add('newcatdays', $_ADVT_DEFAULT['newcatdays'], 
                'text', 0, 0, 0, 60, true, $_CONF_ADVT['pi_name']);
        $c->add('newadsinterval', $_ADVT_DEFAULT['newadsinterval'], 
                'text', 0, 0, 0, 70, true, $_CONF_ADVT['pi_name']);
        $c->add('hidenewads', $_ADVT_DEFAULT['hidenewads'], 
                'select', 0, 0, 14, 80, true, $_CONF_ADVT['pi_name']);
        $c->add('emailadmin', $_ADVT_DEFAULT['emailadmin'], 
                'select', 0, 0, 9, 90, true, $_CONF_ADVT['pi_name']);
        $c->add('emailusers', $_ADVT_DEFAULT['emailusers'], 
                'select', 0, 0, 3, 95, true, $_CONF_ADVT['pi_name']);
        $c->add('hideuserfunction', $_ADVT_DEFAULT['hideuserfunction'], 
                'select', 0, 0, 3, 100, true, $_CONF_ADVT['pi_name']);
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
        $c->add('disp_fullname', $_ADVT_DEFAULT['disp_fullname'], 
                'select', 0, 0, 3, 175, true, $_CONF_ADVT['pi_name']);
        $c->add('usercanedit', $_ADVT_DEFAULT['usercanedit'], 
                'select', 0, 0, 3, 180, true, $_CONF_ADVT['pi_name']);
        $c->add('use_gl_cron', $_ADVT_DEFAULT['use_gl_cron'], 
                'select', 0, 0, 3, 190, true, $_CONF_ADVT['pi_name']);
        $c->add('catlist_dispmode', $_ADVT_DEFAULT['catlist_dispmode'],
                'select', 0, 0, 6, 200, true, $_CONF_ADVT['pi_name']);
        $c->add('centerblock', $_ADVT_DEFAULT['centerblock'],
                'select', 0, 0, 3, 210, true, $_CONF_ADVT['pi_name']);
        $c->add('commentsupport', $_ADVT_DEFAULT['commentsupport'],
                'select', 0, 0, 3, 220, true, $_CONF_ADVT['pi_name']);
        $c->add('displayblocks', $_ADVT_DEFAULT['displayblocks'],
                'select', 0, 0, 13, 230, true, $_CONF_ADVT['pi_name']);

        $c->add('fs_permissions', NULL, 'fieldset', 0, 4, NULL, 0, true, $_CONF_ADVT['pi_name']);
        $c->add('defgrpad', $group_id,
                'select', 0, 4, 0, 90, true, $_CONF_ADVT['pi_name']);
        $c->add('default_permissions', $_ADVT_DEFAULT['default_permissions'],
                '@select', 0, 4, 12, 100, true, $_CONF_ADVT['pi_name']);

        $c->add('fs_perm_cat', NULL, 'fieldset', 0, 5, NULL, 0, true, $_CONF_ADVT['pi_name']);
        $c->add('defgrpcat', $_ADVT_DEFAULT['defgrpcat'],
                'select', 0, 5, 0, 90, true, $_CONF_ADVT['pi_name']);
        $c->add('default_perm_cat', $_ADVT_DEFAULT['default_perm_cat'],
                '@select', 0, 5, 12, 100, true, $_CONF_ADVT['pi_name']);
    }

    return true;
}

?>
