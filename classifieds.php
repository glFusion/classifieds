<?php
/**
*   Table definitions and other static config variables.
*
*   @author     Lee Garner <lee@leegarner.com>
*   @copyright  Copyright (c) 2009-2016 Lee Garner <lee@leegarner.com>
*   @package    classifieds
*   @version    1.1.0
*   @license    http://opensource.org/licenses/gpl-2.0.php 
*               GNU Public License v2 or later
*   @filesource
*/

/**
*   Global array of table names from glFusion
*   @global array $_TABLES
*/
global $_TABLES;

/**
*   Global table name prefix
*   @global string $_DB_table_prefix
*/
global $_DB_table_prefix;

$_AD_table_prefix = $_DB_table_prefix;

// Add Plugin tables to $_TABLES array
$_TABLES['ad_category'] = $_AD_table_prefix . 'classified_category';
$_TABLES['ad_ads']      = $_AD_table_prefix . 'classified_ads';
$_TABLES['ad_photo']    = $_AD_table_prefix . 'classified_photo';
$_TABLES['ad_notice']   = $_AD_table_prefix . 'classified_notice';
$_TABLES['ad_uinfo']    = $_AD_table_prefix . 'classified_uinfo';
$_TABLES['ad_catflds']  = $_AD_table_prefix . 'classified_catflds';
$_TABLES['ad_submission'] = $_AD_table_prefix . 'classified_submission';
$_TABLES['ad_types']    = $_AD_table_prefix . 'classified_types';
$_TABLES['ad_trans']    = $_AD_table_prefix . 'classified_trans';

$_CONF_ADVT['pi_name'] = 'classifieds';
$_CONF_ADVT['pi_version'] = '1.1.0';
$_CONF_ADVT['gl_version'] = '1.4.0';
$_CONF_ADVT['pi_url'] = 'http://www.leegarner.com';
$_CONF_ADVT['pi_display_name'] = 'Classified Ads';

/**
*   Plugin-specific global array of category block colors
*   @global array $CatListcolors
*/
global $CatListcolors;
$CatListcolors = array(
    // background, forground, header
    array('#CCCC66', '#000000'),
    array('#FFCC99', '#663300'),
    array('#EEEEEE', '#444444'),
    array('#FFCCCC', '#990000'),
    array('#99CCFF', '#3333CC'),
    array('#FF00CC', '#CCCC33'),
);


?>
