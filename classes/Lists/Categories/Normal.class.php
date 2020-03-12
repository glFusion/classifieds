<?php
/**
 * List categories on the home page in a list format.
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
use Classifieds\Category;


/**
 * Create a listing of categories in a list format, like zClassifieds.
 * @package classifieds
 */
class Normal extends \Classifieds\Lists\Categories
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

        $T = new \Template($_CONF_ADVT['path'] . '/templates');
        $T->set_file('page', 'catlist.thtml');

        // Get all the root categories
        /*$sql = "SELECT * FROM {$_TABLES['ad_category']}
                WHERE papa_id = 0 " . COM_getPermSQL('AND', 0, 2) .
                " ORDER BY cat_name ASC";
        $cats = DB_query($sql);
        if (!$cats) return CLASSIFIEDS_errorMsg($LANG_ADVT['database_error'], 'alert');*/
        $Cats = Category::SubCats();
        // If no root categories exist, display just return a message
        //if (DB_numRows($cats) == 0) {
        if (count($Cats) == 0) {
            $T->set_var('no_cat_found',
                "<p align=\"center\" class=\"headmsg\">
                $LANG_ADVT[no_cat_found]</p>\n");
            $T->parse('output', 'page');
            return $T->finish($T->get_var('output'));
        }

        $T->set_block('page', 'CatRows', 'CRow');

        $i = 1;
        $newtime = time() - 3600 * 24 * $_CONF_ADVT['newcatdays'];
        //while ($catsrow = DB_fetchArray($cats)) {
        foreach ($Cats as $Cat) {
            // For each category, find the total ad count (including subcats)
            // and display the subcats below it.
            $T->set_var(array(
                'rowstart'  => $i % 2 == 1 ? "<tr>\n" : '',
                'cat_url'   => CLASSIFIEDS_makeUrl('home', $Cat->getID()),
                'cat_name'  => $Cat->getName(),
                'cat_ad_count' => Category::TotalAds($Cat->getID()),
                'image' => Category::thumbUrl($Cat->getImage()) : '',
            ) );

            $SubCats = Category::SubCats($Cat->getID());
            /*$sql = "SELECT * FROM {$_TABLES['ad_category']}
                    WHERE papa_id={$catsrow['cat_id']} " .
                        COM_getPermSQL('AND', 0, 2) . "
                    ORDER BY cat_name ASC";
            //echo $sql;die;
            $subcats = DB_query($sql);
            if (!$subcats) return CLASSIFIEDS_errorMsg($LANG_ADVT['database_error'], 'alert');

            $num = DB_numRows($subcats);*/
            $num = count($SubCats);
            // Earliest time to be considered "new"
            $subcatlist = '';

            $j = 1;
            //while ($subcatsrow = DB_fetchArray($subcats)) {
            foreach ($SubCats as $SubCat) {
                $isnew = $SubCat->getAddDate()->toUnix() > $newtime ?
                    "<img src=\"{$_CONF['site_url']}/{$_CONF_ADVT['pi_name']}/images/new.gif\" align=\"top\">" : '';
                $subcatlist .= '<a href="'.
                        CLASSIFIEDS_makeURL('home', $SubCat->getID()). '">'.
                        "{$SubCat->getName()}</a>&nbsp;(" .
                        Category::TotalAds($SubCat->getID()). ")&nbsp;{$isnew}";

                if ($num != $j)
                    $subcatlist .= ", ";

                $j++;
            }
            $T->set_var('subcatlist', $subcatlist);
            $T->set_var('rowend', $i % 2 == 0 ? "</tr>\n" : "");
            $i++;
            $T->parse('CRow', 'CatRows', true);
        }
        $T->parse('output', 'page');
        return $T->finish($T->get_var('output'));
    }

}

?>
