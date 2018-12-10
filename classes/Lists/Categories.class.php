<?php
/**
 * List categories on the home page.
 *
 * @author      Lee Garner <lee@leegarner.com>
 * @copyright   Copyright (c) 2016-2017 Lee Garner <lee@leegarner.com>
 * @package     classifieds
 * @version     v1.2.1
 * @license     http://opensource.org/licenses/gpl-2.0.php
 *              GNU Public License v2 or later
 * @filesource
 */
namespace Classifieds\Lists;

/**
 * Create a listing of categories.
 * @package classifieds
 */
class Categories
{
    /**
     * When no category is given, show a table of all categories
     * along with the count of ads for each.
     * Returns the results from the category
     * list function, chosen based on the display mode
     * @return string      HTML for category listing page
     */
    public static function Render()
    {
        global $_CONF_ADVT;
        switch ($_CONF_ADVT['catlist_dispmode']) {
        case 'blocks':
            return \Classifieds\Lists\Categories\Blocks::Render();
            break;

        default:
            return \Classifieds\Lists\Categories\Normal::Render();
            break;
        }
    }

}

?>
