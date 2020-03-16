<?php
/**
 * Class to provide admin and user-facing menus.
 *
 * @author      Lee Garner <lee@leegarner.com>
 * @copyright   Copyright (c) 2020Lee Garner <lee@leegarner.com>
 * @package     classifieds
 * @version     v1.3.0
 * @since       v1.3.0
 * @license     http://opensource.org/licenses/gpl-2.0.php
 *              GNU Public License v2 or later
 * @filesource
 */
namespace Classifieds;

/**
 * Class to provide admin and user-facing menus.
 * @package classifieds
 */
class Menu
{
    /**
     * Get the URL to submit ads.
     *
     * @return  string      URL to label printing screen
     */
    public static function getSubmitUrl()
    {
        global $_CONF_ADVT;

        return $_CONF_ADVT['url'] . '/index.php?mode=submit';
    }


    /**
     * Returns the user-facing main menu.
     *
     * @param   string  $view       The menu option to set as selected
     * @param   integer $eventid    Event ID currently selected
     * @return  array       Menu array for ppNavBar()
     */
    public static function User($view='', $eventid=0)
    {
        global $LANG_ADVT, $_CONF_ADVT;

        if (COM_isAnonUser()) {
            return '';
        }

        USES_lib_admin();

        if ($view == '') {
            $view = 'home';
        }
        $menu_arr = array(
            array(
                'url' => CLASSIFIEDS_makeURL('home'),
                'text' => $LANG_ADVT['mnu_home'],
                'active' => $view == 'home' ? true : false,
            ),
        );

        if (!COM_isAnonUser()) {
            $menu_arr[] = array(
                'url' => CLASSIFIEDS_makeURL('account'),
                'text' => $LANG_ADVT['mnu_account'],
                'active' => $view == 'account' ? true : false,
            );
            $menu_arr[] = array(
                'url' => CLASSIFIEDS_makeURL('manage'),
                'text' => $LANG_ADVT['mnu_myads'],
                'active' => $view == 'manage' ? true : false,
            );
        }

        if (CLASSIFIEDS_canSubmit()) {
            $menu_arr[] = array(
                'url' => self::getSubmitUrl(),
                'text' => $LANG_ADVT['mnu_submit'],
                'active' => $view == 'submit' ? true : false,
            );
        }

        return \ADMIN_createMenu($menu_arr, '', '');
    }


    /**
     * Create the admin menu.
     *
     * @param   string  $view       View mode
     * @param   string  $help_text  Additional help text to show
     * @return  string      HTML for admin menu section
     */
    public static function Admin($view ='', $help_text = '')
    {
        global $_CONF, $_CONF_ADVT, $LANG_ADVT, $LANG01;

        USES_lib_admin();
        if ($help_text == '') {
            $help_text = 'admin_text';
        }

        $menu_arr = array(
            array(
                'url' => $_CONF_ADVT['admin_url'] . '/index.php?ads',
                'text' => $LANG_ADVT['mnu_adlist'],
                'active' => $view == 'ads' ? true : false,
            ),
            array(
                'url' => $_CONF_ADVT['admin_url'] . '/index.php?types',
                'text' => $LANG_ADVT['mnu_types'],
                'active' => $view == 'types' ? true : false,
            ),
            array(
                'url' => $_CONF_ADVT['admin_url'] . '/index.php?categories',
                'text' => $LANG_ADVT['mnu_cats'],
                'active' => $view == 'categories' ? true : false,
            ),
            array(
                'url' => $_CONF_ADVT['admin_url'] . '/index.php?other',
                'text' => $LANG_ADVT['mnu_other'],
                'active' => $view == 'other' ? true : false,
            ),
            array(
                'url' => $_CONF['site_admin_url'],
                'text' => $LANG01[53],
            ),
        );

        return ADMIN_createMenu(
            $menu_arr,
            $LANG_ADVT[$help_text],
            plugin_geticon_classifieds()
        );
    }


    /**
     * Show the site header, with or without left blocks according to config.
     *
     * @uses    COM_siteHeader()
     * @param   string  $subject    Text for page title (ad title, etc)
     * @param   string  $meta       Other meta info
     * @return  string              HTML for site header
     */
    public static function siteHeader($subject='', $meta='')
    {
        global $_CONF_ADVT, $LANG_ADVT;

        $retval = '';

        $title = $LANG_ADVT['blocktitle'];
        if ($subject != '') {
            $title = $subject . ' : ' . $title;
        }

        switch($_CONF_ADVT['displayblocks']) {
        case 2:     // right only
        case 0:     // none
            $retval .= COM_siteHeader('none', $title, $meta);
            break;
        case 1:     // left only
        case 3:     // both
        default :
            $retval .= COM_siteHeader('menu', $title, $meta);
            break;
        }
        return $retval;
    }


    /**
     * Show the site footer, with or without right blocks according to config.
     *
     * @since   v1.0.2
     * @uses    COM_siteFooter()
     * @return  string              HTML for site header
     */
    public static function siteFooter()
    {
        global $_CONF_ADVT;

        $retval = '';

        switch($_CONF_ADVT['displayblocks']) {
        case 2 : // right only
        case 3 : // left and right
            $retval .= COM_siteFooter(true);
            break;
        case 0: // none
        case 1: // left only
        default :
            $retval .= COM_siteFooter();
            break;
        }
        return $retval;
    }

}

?>
