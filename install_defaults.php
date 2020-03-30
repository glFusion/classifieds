<?php
/**
 * Installation defaults for the Classifieds plugin.
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
    die('This file can not be used on its own!');
}

global $classifiedsConfigItems;

/**
 * Classifieds default configuration settings.
 *
 * Initial Installation Defaults used when loading the online configuration
 * records. These settings are only used during the initial installation
 * and not referenced any more once the plugin is installed.
 * @var array
 */
$classifiedsConfigItems = array(
    array(
        'name' => 'sg_main',
        'default_value' => NULL,
        'type' => 'subgroup',
        'subgroup' => 0,
        'fieldset' => 0,
        'selection_array' => NULL,
        'sort' => 0,
        'set' => true,
        'group' => 'classifieds',
    ),
    array(
        'name' => 'fs_main',
        'default_value' => NULL,
        'type' => 'fieldset',
        'subgroup' => 0,
        'fieldset' => 0,
        'selection_array' => NULL,
        'sort' => 0,
        'set' => true,
        'group' => 'classifieds',
    ),
    array(
        'name' => 'img_max_height',
        'default_value' => '600',
        'type' => 'text',
        'subgroup' => 0,
        'fieldset' => 0,
        'selection_array' => 0,
        'sort' => 10,
        'set' => true,
        'group' => 'classifieds',
    ),
    array(
        'name' => 'img_max_width',
        'default_value' => '800',
        'type' => 'text',
        'subgroup' => 0,
        'fieldset' => 0,
        'selection_array' => 0,
        'sort' => 20,
        'set' => true,
        'group' => 'classifieds',
    ),
    array(
        'name' => 'detail_img_width',
        'default_value' => '150',
        'type' => 'text',
        'subgroup' => 0,
        'fieldset' => 0,
        'selection_array' => 0,
        'sort' => 30,
        'set' => true,
        'group' => 'classifieds',
    ),
    array(
        'name' => 'thumb_max_size',
        'default_value' => '100',
        'type' => 'text',
        'subgroup' => 0,
        'fieldset' => 0,
        'selection_array' => 0,
        'sort' => 40,
        'set' => true,
        'group' => 'classifieds',
    ),
    array(
        'name' => 'random_blk_width',
        'default_value' => '100',
        'type' => 'text',
        'subgroup' => 0,
        'fieldset' => 0,
        'selection_array' => 0,
        'sort' => 50,
        'set' => true,
        'group' => 'classifieds',
    ),
    array(
        'name' => 'imagecount',
        'default_value' => '3',
        'type' => 'text',
        'subgroup' => 0,
        'fieldset' => 0,
        'selection_array' => 0,
        'sort' => 60,
        'set' => true,
        'group' => 'classifieds',
    ),
    array(
        'name' => 'submission',
        'default_value' => 1,
        'type' => 'select',
        'subgroup' => 0,
        'fieldset' => 0,
        'selection_array' => 0,
        'sort' => 70,
        'set' => true,
        'group' => 'classifieds',
    ),
    array(
        'name' => 'default_duration',
        'default_value' => '30',
        'type' => 'text',
        'subgroup' => 0,
        'fieldset' => 0,
        'selection_array' => 0,
        'sort' => 80,
        'set' => true,
        'group' => 'classifieds',
    ),
    array(
        'name' => 'newcatdays',
        'default_value' => '100w3',
        'type' => 'text',
        'subgroup' => 0,
        'fieldset' => 0,
        'selection_array' => 0,
        'sort' => 90,
        'set' => true,
        'group' => 'classifieds',
    ),
    array(
        'name' => 'newadsinterval',
        'default_value' => '14',
        'type' => 'text',
        'subgroup' => 0,
        'fieldset' => 0,
        'selection_array' => 0,
        'sort' => 100,
        'set' => true,
        'group' => 'classifieds',
    ),
    array(
        'name' => 'hidenewads',
        'default_value' => 0,
        'type' => 'select',
        'subgroup' => 0,
        'fieldset' => 0,
        'selection_array' => 14,
        'sort' => 110,
        'set' => true,
        'group' => 'classifieds',
    ),
    array(
        'name' => 'emailadmin',
        'default_value' => 1,
        'type' => 'select',
        'subgroup' => 0,
        'fieldset' => 0,
        'selection_array' => 9,
        'sort' => 120,
        'set' => true,
        'group' => 'classifieds',
    ),
    array(
        'name' => 'emailusers',
        'default_value' => 0,
        'type' => 'text',
        'subgroup' => 0,
        'fieldset' => 0,
        'selection_array' => 10,
        'sort' => 130,
        'set' => true,
        'group' => 'classifieds',
    ),
    array(
        'name' => 'hideuserfunction',
        'default_value' => 1,
        'type' => 'select',
        'subgroup' => 0,
        'fieldset' => 0,
        'selection_array' => 3,
        'sort' => 140,
        'set' => true,
        'group' => 'classifieds',
    ),
    array(
        'name' => 'maxads_pg_exp',
        'default_value' => '20',
        'type' => 'text',
        'subgroup' => 0,
        'fieldset' => 0,
        'selection_array' => 0,
        'sort' => 150,
        'set' => true,
        'group' => 'classifieds',
    ),
    array(
        'name' => 'maxads_pg_list',
        'default_value' => '20',
        'type' => 'text',
        'subgroup' => 0,
        'fieldset' => 0,
        'selection_array' => 0,
        'sort' => 160,
        'set' => true,
        'group' => 'classifieds',
    ),
    array(
        'name' => 'max_total_duration',
        'default_value' => '120',
        'type' => 'text',
        'subgroup' => 0,
        'fieldset' => 0,
        'selection_array' => 0,
        'sort' => 170,
        'set' => true,
        'group' => 'classifieds',
    ),
    array(
        'name' => 'purge_days',
        'default_value' => '15',
        'type' => 'text',
        'subgroup' => 0,
        'fieldset' => 0,
        'selection_array' => 0,
        'sort' => 170,
        'set' => true,
        'group' => 'classifieds',
    ),
    array(
        'name' => 'exp_notify_days',
        'default_value' => -1,
        'type' => 'text',
        'subgroup' => 0,
        'fieldset' => 0,
        'selection_array' => 0,
        'sort' => 180,
        'set' => true,
        'group' => 'classifieds',
    ),
    array(
        'name' => 'loginrequired',
        'default_value' => 0,
        'type' => 'select',
        'subgroup' => 0,
        'fieldset' => 0,
        'selection_array' => 3,
        'sort' => 190,
        'set' => true,
        'group' => 'classifieds',
    ),
    array(
        'name' => 'disp_fullname',
        'default_value' => 1,
        'type' => 'text',
        'subgroup' => 0,
        'fieldset' => 0,
        'selection_array' => 3,
        'sort' => 200,
        'set' => true,
        'group' => 'classifieds',
    ),
    array(
        'name' => 'use_gl_cron',
        'default_value' => 1,
        'type' => 'select',
        'subgroup' => 0,
        'fieldset' => 0,
        'selection_array' => 3,
        'sort' => 220,
        'set' => true,
        'group' => 'classifieds',
    ),
    array(
        'name' => 'detail_tpl_ver',
        'default_value' => 'v1',
        'type' => 'select',
        'subgroup' => 0,
        'fieldset' => 0,
        'selection_array' => 0,     // helper function used
        'sort' => 240,
        'set' => true,
        'group' => 'classifieds',
    ),
    array(
        'name' => 'centerblock',
        'default_value' => 0,
        'type' => 'select',
        'subgroup' => 0,
        'fieldset' => 0,
        'selection_array' => 3,
        'sort' => 250,
        'set' => true,
        'group' => 'classifieds',
    ),
    array(
        'name' => 'commentsupport',
        'default_value' => 1,
        'type' => 'select',
        'subgroup' => 0,
        'fieldset' => 0,
        'selection_array' => 3,
        'sort' => 260,
        'set' => true,
        'group' => 'classifieds',
    ),
    array(
        'name' => 'displayblocks',
        'default_value' => 3,
        'type' => 'select',
        'subgroup' => 0,
        'fieldset' => 0,
        'selection_array' => 13,
        'sort' => 270,
        'set' => true,
        'group' => 'classifieds',
    ),
    array(
        'name' => 'auto_subcats',
        'default_value' => 0,
        'type' => 'select',
        'subgroup' => 0,
        'fieldset' => 0,
        'selection_array' => 3,
        'sort' => 270,
        'set' => true,
        'group' => 'classifieds',
    ),

    // Permissions
    array(
        'name' => 'fs_perm_cat',
        'default_value' => NULL,
        'type' => 'fieldset',
        'subgroup' => 0,
        'fieldset' => 10,
        'selection_array' => NULL,
        'sort' => 0,
        'set' => true,
        'group' => 'classifieds',
    ),
    array(
        'name' => 'defgrpcat',
        'default_value' => 13,
        'type' => 'select',
        'subgroup' => 0,
        'fieldset' => 10,
        'selection_array' => 0,     // helper function used
        'sort' => 10,
        'set' => true,
        'group' => 'classifieds',
    ),
    array(
        'name' => 'default_perm_cat',
        'default_value' => array(3, 2, 2, 2),
        'type' => '@select',
        'subgroup' => 0,
        'fieldset' => 10,
        'selection_array' => 12,
        'sort' => 20,
        'set' => true,
        'group' => 'classifieds',
    ),
);

/**
 * Initialize Classifieds plugin configuration.
 *
 * Creates the database entries for the configuation if they don't already
 * exist.
 *
 * @param   integer $group_id   Group ID to use as the plugin's admin group
 * @return  boolean             true: success; false: an error occurred
 */
function plugin_initconfig_classifieds($group_id = 0)
{
    global $classifiedsConfigItems;

    $c = config::get_instance();
    if (!$c->group_exists('classifieds')) {
        USES_lib_install();
        foreach ($classifiedsConfigItems as $cfgItem) {
            _addConfigItem($cfgItem);
        }
    }
    return true;
}

?>
