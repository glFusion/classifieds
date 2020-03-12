<?php
/**
 * List categories on the home page in a block layout
 *
 * @author      Lee Garner <lee@leegarner.com>
 * @copyright   Copyright (c) 2016-2017 Lee Garner <lee@leegarner.com>
 * @package     classifieds
 * @version     v1.2.1
 * @license     http://opensource.org/licenses/gpl-2.0.php
 *              GNU Public License v2 or later
 * @filesource
 */
namespace Classifieds\Lists\Categories;

/**
 * Create a listing of categories in a block layout.
 * @package classifieds
 */
class Blocks extends \Classifieds\Lists\Categories
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
        global $_CONF, $_TABLES, $LANG_ADVT, $_CONF_ADVT;
        global $CatListcolors;

        $T = new \Template($_CONF_ADVT['path'] . '/templates');
        $T->set_file('page', 'catlist_blocks.thtml');

        // Get all the root categories
        /*$sql = "SELECT * FROM {$_TABLES['ad_category']}
                WHERE papa_id = 1 " .
                    COM_getPermSQL('AND', 0, 2) .
                " ORDER BY cat_name ASC";
        //echo $sql;die;
        $cats = DB_query($sql);
        if (!$cats) return CLASSIFIEDS_errorMsg($LANG_ADVT['database_error'], 'alert');
        */
        $Cats = \Classifieds\Category::SubCats(1);
        // If no root categories exist, display just return a message
        if (count($Cats) == 0) {
            $T->set_var('no_cat_found',
                "<p align=\"center\" class=\"headmsg\">
                $LANG_ADVT[no_cat_found]</p>\n");
            $T->parse('output', 'page');
            return $T->finish($T->get_var('output'));
        }

        $max = count($CatListcolors);

        $i = 0;
        foreach ($Cats as $Cat) {
            // Get the colors for the blocks from the global var if not set
            // for the category
            if ($Cat->getFGColor() == '' || $Cat->getBGColor() == '') {
                if ($i >= $max) $i = 0;
                $bgcolor = $CatListcolors[$i][0];
                $fgcolor = $CatListcolors[$i][1];
                $i++;
            } else {
                $fgcolor = $Cat->getFGColor();
                $bgcolor = $Cat->getBGColor();
            }

            // For each category, find the total ad count (including subcats)
            // and display the subcats below it.
            $T->set_block('page', 'CatDiv', 'Div');
            $T->set_var(array(
                'bgcolor'   => $bgcolor,
                'fgcolor'   => $fgcolor,
                'cat_url'   => CLASSIFIEDS_makeUrl('home', $Cat->getID()),
                'cat_name'  => $Cat->getName(),
                'cat_desc'  => $Cat->getDscp(),
                'cat_ad_count' => \Classifieds\Category::TotalAds($Cat->getID()),
                'image' => \Classifieds\Category::thumbUrl($Cat->getImage()),
            ) );
            $T->parse('Div', 'CatDiv', true);
        }
        $T->parse('output', 'page');
        return $T->finish($T->get_var('output'));
    }

}

?>
