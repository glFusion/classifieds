<?php
/**
 * Table definitions and other static config variables.
 *
 * @author      Lee Garner <lee@leegarner.com>
 * @copyright   Copyright (c) 2009-2020 Lee Garner <lee@leegarner.com>
 * @package     classifieds
 * @version     v1.3.1
 * @license     http://opensource.org/licenses/gpl-2.0.php
 *              GNU Public License v2 or later
 * @filesource
 */

/**
 * Global array of table names from glFusion.
 * @global array $_TABLES
 */
global $_TABLES;

/**
 * Global table name prefix.
 * @global string $_DB_table_prefix
 */
global $_DB_table_prefix;

$_AD_table_prefix = $_DB_table_prefix;

// Add Plugin tables to $_TABLES array
$_TABLES['ad_category'] = $_AD_table_prefix . 'classified_category';
$_TABLES['ad_ads']      = $_AD_table_prefix . 'classified_ads';
$_TABLES['ad_photo']    = $_AD_table_prefix . 'classified_photo';
$_TABLES['ad_uinfo']    = $_AD_table_prefix . 'classified_uinfo';
$_TABLES['ad_catflds']  = $_AD_table_prefix . 'classified_catflds';
$_TABLES['ad_submission'] = $_AD_table_prefix . 'classified_submission';
$_TABLES['ad_types']    = $_AD_table_prefix . 'classified_types';
$_TABLES['ad_trans']    = $_AD_table_prefix . 'classified_trans';

// Deprecated tables to be removed
// 1.1.3
$_TABLES['ad_notice']   = $_AD_table_prefix . 'classified_notice';

$_CONF_ADVT['pi_name'] = 'classifieds';
$_CONF_ADVT['pi_version'] = '1.3.1';
$_CONF_ADVT['gl_version'] = '1.7.0';
$_CONF_ADVT['pi_url'] = 'http://www.leegarner.com';
$_CONF_ADVT['pi_display_name'] = 'Classified Ads';

/**
*   Other semi-static configurations
*/
global $_CONF;
$_CONF_ADVT['path'] = __DIR__;
$_CONF_ADVT['imgpath'] = $_CONF['path'] . 'data/classifieds/images';
$_CONF_ADVT['url'] = $_CONF['site_url'] . '/' . $_CONF_ADVT['pi_name'];
$_CONF_ADVT['admin_url'] = $_CONF['site_admin_url'] . '/plugins/' . $_CONF_ADVT['pi_name'];

?>
