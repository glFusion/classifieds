<?php
/**
*   List ads.  By category, recent submissions, etc.
*
*   @author     Lee Garner <lee@leegarner.com>
*   @copyright  Copyright (c) 2016-2017 Lee Garner <lee@leegarner.com>
*   @package    classifieds
*   @version    1.1.3
*   @license    http://opensource.org/licenses/gpl-2.0.php
*               GNU Public License v2 or later
*   @filesource
*/
namespace Classifieds;

/**
*   @class AdList_Cat
*   @package classifieds
*   Display the ads under the given category ID.
*   Also puts in the subscription link and breadcrumbs.
*/
class AdList_Cat extends AdList
{
    public $Cat;    // Category Object

    /**
    *   Constructor. Set the category ID
    *
    *   @param  integer $cat_id     Category ID
    */
    public function __construct($cat_id = 0)
    {
        $this->cat_id = (int)$cat_id;
        $this->Cat = new Category($this->cat_id);
    }

    /**
    *   Render the ad listing.
    *   First creates the category thumbnails leading to the current category,
    *   then calls parent::Render() to create the ad list.
    */
    public function Render()
    {
        global $_TABLES, $LANG_ADVT, $_CONF, $_USER, $_CONF_ADVT, $_GROUPS;
        global $CatListcolors;

        if ($this->cat_id == 0)
            return;

        if (!$this->Cat->canView()) {
            return CLASSIFIEDS_errorMsg($LANG_ADVT['cat_unavailable'], 'alert');
        }

        $T = new \Template($_CONF_ADVT['path'] . '/templates');
        $T->set_file('header', 'adlisthdrCat.thtml');
        $T->set_var('pi_url', $_CONF_ADVT['url']);
        $T->set_var('catimg_url', Image::thumbUrl($this->Cat->image));

        // Set the breadcrumb navigation
        $T->set_var('breadcrumbs', $this->Cat->BreadCrumbs(true));

        // if non-anonymous, allow the user to subscribe to this category
        if (!COM_isAnonUser()) {
            // Determine whether the user is subscribed to notifications for
            // this category and display a message and or link accordingly
            if (PLG_isSubscribed($_CONF_ADVT['pi_name'], 'category', $this->cat_id)) {
                $sub_vis = 'none';
                $unsub_vis = 'block';
            } else {
                $sub_vis = 'block';
                $unsub_vis = 'none';
            }

            // Display a link to submit an ad to the current category
            $submit_url = '';
            if (plugin_ismoderator_classifieds()) {
                $submit_url = $_CONF_ADVT['admin_url'] .
                        '/index.php?editad=x&cat_id='.$this->cat_id;
            } elseif ($this->Cat->canEdit()) {
                $submit_url = $_CONF_ADVT['url'] .
                    '/index.php?mode=edit&cat_id=' . $this->cat_id;
            }
            $T->set_var(array(
                'cat_id'        => $this->Cat->cat_id,
                'sub_vis'       => $sub_vis,
                'unsub_vis'     => $unsub_vis,
                'can_subscribe' => 'true',
                'submit_url'    => $submit_url,
            ) );

        } else {
            // Not-logged-in users can't subscribe or submit.
            $T->set_var(array(
                'subscribe_msg' => '',
                'submit_msg'    => '',
                'can_subscribe' => '',
            ) );
        }

        // This is a comma-separated string of category IDs for a SQL "IN" clause.
        // Start with the current category
        $cat_for_adlist = $this->cat_id;

        // Get the sub-categories which have this category as their parent
        if ($this->Cat->papa_id == 0) {
            // For top category, show only immediate subordinates to avoid a
            // huge list
            $subcats = Category::SubCats($this->cat_id, 1);
        } else {
            // Use a large depth to get counts and ads from sub-sub-categories
            $subcats = Category::SubCats($this->cat_id, 99);
        }
        $listvals = '';
        $max = count($CatListcolors);
        $i = 0;
        foreach ($subcats as $row) {
            // for each sub-category, add it to the list for getting ads
            $cat_for_adlist .= ",{$row->cat_id}";

            $T->set_block('header', 'SubCat', 'sCat');
            if ($row->fgcolor == '' || $row->bgcolor == '') {
                if ($i >= $max) $i = 0;
                $T->set_var('bgcolor', $CatListcolors[$i][0]);
                $T->set_var('fgcolor', $CatListcolors[$i][1]);
                $i++;
            } else {
                $T->set_var('bgcolor', $row->bgcolor);
                $T->set_var('fgcolor', $row->fgcolor);
            }

            $T->set_var('subcat_url',
                CLASSIFIEDS_makeURL('list', $row->cat_id));
            $T->set_var('subcat_name', $row->cat_name);
            $T->set_var('subcat_count', Category::TotalAds($row->cat_id));
            $T->parse('sCat', 'SubCat', true);
        }

        // Get the count of ads under this category
        $time = time();
        $sql = "SELECT cat_id FROM {$_TABLES['ad_ads']}
                WHERE cat_id IN ($cat_for_adlist)
                AND exp_date > $time";
        $result = DB_query($sql);
        if (!$result)
            return CLASSIFIEDS_errorMsg($LANG_ADVT['database_error'], 'alert');
        $totalAds = DB_numRows($result);

        $this->where_clause = " ad.cat_id IN ($cat_for_adlist)
            AND ad.exp_date > $time ";

        $T->parse('output', 'header');
        $retval = $T->finish($T->get_var('output'));

        // Now that the header is done, call the base class to render
        // the list.
        $retval .= parent::Render();
        return $retval;
    }

}

?>
