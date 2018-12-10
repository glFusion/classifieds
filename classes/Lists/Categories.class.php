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
            return self::_Blocks();
            break;

        default:
            return self::_Normal();
            break;
        }
    }


    /**
     * Create a "normal" list of categories, using text links.
     *
     * @return  string      HTML for category listing page
     */
    private static function _Normal()
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
        $Cats = \Classifieds\Category::SubCats();
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
                'cat_url'   => CLASSIFIEDS_makeUrl('home', $Cat->cat_id),
                'cat_name'  => $Cat->cat_name,
                'cat_ad_count' => \Classifieds\Category::TotalAds($Cat->cat_id),
                'image' => $Cat->image ? \Classifieds\Category::thumbUrl($Cat->image) : '',
            ) );

            $SubCats = \Classifieds\Category::SubCats($Cat->cat_id);
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
                $isnew = $SubCat->add_date > $newtime ?
                    "<img src=\"{$_CONF['site_url']}/{$_CONF_ADVT['pi_name']}/images/new.gif\" align=\"top\">" : '';
                $subcatlist .= '<a href="'.
                        CLASSIFIEDS_makeURL('home', $SubCat->cat_id). '">'.
                        "{$SubCat->cat_name}</a>&nbsp;(" .
                        \Classifieds\Category::TotalAds($SubCat->cat_id). ")&nbsp;{$isnew}";

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


    /**
     * Create a category listing page showing the categories in block styling.
     *
     * @return  string      HTML for category listing page
     */
    private static function _Blocks()
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
            if ($Cat->fgcolor == '' || $Cat->bgcolor == '') {
                if ($i >= $max) $i = 0;
                $bgcolor = $CatListcolors[$i][0];
                $fgcolor = $CatListcolors[$i][1];
                $i++;
            } else {
                $fgcolor = $Cat->fgcolor;
                $bgcolor = $Cat->bgcolor;
            }

            // For each category, find the total ad count (including subcats)
            // and display the subcats below it.
            $T->set_block('page', 'CatDiv', 'Div');
            $T->set_var(array(
                'bgcolor'   => $bgcolor,
                'fgcolor'   => $fgcolor,
                'cat_url'   => CLASSIFIEDS_makeUrl('home', $Cat->cat_id),
                'cat_name'  => $Cat->cat_name,
                'cat_desc'  => $Cat->description,
                'cat_ad_count' => \Classifieds\Category::TotalAds($Cat->cat_id),
                'image' => \Classifieds\Category::thumbUrl($Cat->image),
            ) );
            $T->parse('Div', 'CatDiv', true);
        }
        $T->parse('output', 'page');
        return $T->finish($T->get_var('output'));
    }

}

?>
