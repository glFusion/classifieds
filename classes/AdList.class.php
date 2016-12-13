<?php
/**
*   List ads.  By category, recent submissions, etc.
*
*   @author     Lee Garner <lee@leegarner.com>
*   @copyright  Copyright (c) 2016 Lee Garner <lee@leegarner.com>
*   @package    classifieds
*   @version    1.1.0
*   @license    http://opensource.org/licenses/gpl-2.0.php 
*               GNU Public License v2 or later
*   @filesource
*/


/**
*   @class AdList
*   @package classifieds
*   Create a listing of ads
*/
class AdList
{
    protected $pagename = '';
    protected $cat_id = 0;
    protected $where_clause = '';
    protected $limit_clause = '';


    /**
    *  Display an expanded ad listing.
    *
    *  @return string                  Page Content
    */
    public function Render()
    {
        global $_TABLES, $LANG_ADVT, $_CONF, $_USER, $_CONF_ADVT;

        USES_classifieds_class_adtype();
        USES_classifieds_class_image();

        // Fix time to check ad expiration
        $time = time();

        // Max number of ads per page
        $maxAds = isset($_CONF_ADVT['maxads_pg_exp']) ? 
                (int)$_CONF_ADVT['maxads_pg_exp'] : 20;

        $T = new Template(CLASSIFIEDS_PI_PATH . '/templates');
        $T->set_file('catlist', 'adExpList.thtml');

        // Gt the ads for this category, starting at the requested page
        $sql = "SELECT ad.*, ad.add_date as ad_add_date, cat.*
            FROM {$_TABLES['ad_ads']} ad
            LEFT JOIN {$_TABLES['ad_category']} cat
                ON cat.cat_id = ad.cat_id 
            WHERE ad.exp_date > $time " .
                COM_getPermSQL('AND', 0, 2, 'cat');
        if ($this->where_clause != '')
            $sql .= " AND $this->where_clause ";
        $sql .= " ORDER BY ad.add_date DESC";
        //echo $sql;die;

        // first execute the query with the supplied limit clause to get
        // the total number of ads eligible for viewing
        $sql1 = $sql . ' ' . $this->limit_clause;
        $result = DB_query($sql1);
        if (!$result) return "Database Error";
        $totalAds = DB_numRows($result);

        // Figure out the page number, and execute the query
        // with the appropriate LIMIT clause.
        if ($totalAds <= $maxAds)
            $totalPages = 1;
        elseif ($totalAds % $maxAds == 0)
            $totalPages = $totalAds / $maxAds;
        else
            $totalPages = ceil($totalAds / $maxAds);

        $page = COM_applyFilter($_REQUEST['start'], true);
        if ($page < 1 || $page > $totalPages) {
            $page = 1;
        }

        if ($totalAds == 0) {
            $startEntry = 0;
        } else {
            $startEntry = $maxAds * $page - $maxAds + 1;
        }

        if ($page == $totalPages) {
            $endEntry = $totalAds;
        } else {
            $endEntry = $maxAds * $page;
        }

        $initAds = $maxAds * ($page - 1);

        // Create the page menu string for display if there is more
        // than one page
        $pageMenu = '';
        if ($totalPages > 1) {
            $baseURL = CLASSIFIEDS_URL . "/index.php?page=$pagename";
            if ($this->cat_id != '')
                $baseURL .= "&amp;id=$this->cat_id";
            $pageMenu = COM_printPageNavigation($baseURL, $page, $totalPages, "start=");
        }
        $T->set_var('pagemenu', $pageMenu);

        $sql .= " LIMIT $initAds, $maxAds";
        //echo $sql;die;
        $result = DB_query($sql);
        if (!$result) return CLASSIFIEDS_errorMsg($LANG_ADVT['database_error'], 'alert');

        if ($totalAds == 0) {
            $T->set_block('catlist', 'No_Ads', 'NoAdBlk');
            $T->set_var('no_ads', $LANG_ADVT['no_ads_listed_cat']);
            $T->parse('NoAdBlk', 'No_Ads', true);
        }

        $T->set_block('catlist', 'QueueRow', 'QRow');
        while ($row = DB_fetchArray($result)) {
            $T->set_var(array(
                'bgColor'   => $bgColor,
                'cat_id'    => $row['cat_id'],
                'subject'   => strip_tags($row['subject']),
                'ad_id'     => $row['ad_id'],
                'ad_url'    => CLASSIFIEDS_makeURL('detail', $row['ad_id']),
                'add_date'  => date($_CONF['shortdate'], $row['ad_add_date']),
                'ad_type'   => AdType::getDescription($row['ad_type']),
                'cat_name'  => $row['cat_name'],
                'cat_url'   => CLASSIFIEDS_makeURL('home', $row['cat_id']),
                'cmt_count' => CLASSIFIEDS_commentCount($row['ad_id']),
                'is_uikit'  => $_CONF_ADVT['_is_uikit'] ? 'true' : '',
            ) );

            $photos = adImage::GetAll($row['ad_id'], 1);
            foreach ($photos as $photo_id=>$filename) {
                //$prow = DB_fetchArray($photo);
                $T->set_var('img_url', adImage::dispUrl($filename));
                $T->set_var('thumb_url', adImage::thumbUrl($filename));
                break;
            }

            $T->set_var('descript', substr(strip_tags($row['descript']), 0, 300));
            if (strlen($row['descript']) > 300)
                $T->set_var('ellipses', '...');

            if ($row['price'] != '')
                $T->set_var('price', strip_tags($row['price']));
            else
                $T->set_var('price', '');

            //Additional info
            for ($j = 0; $j < 5; $j++) {
                $T->set_var('name0'.$j, $row['name0'.$j]);
                $T->set_var('value0'.$j, $row['value0'.$j]);
            }

            $T->parse('QRow', 'QueueRow', true);

        }   // while

        $T->set_var('totalAds', $totalAds);
        $T->set_var('adsStart', $startEntry);
        $T->set_var('adsEnd', $endEntry);

        $T->parse('output', 'catlist');

        return $T->finish($T->get_var('output'));

    }   // function Render()
}


/**
*   @class AdListRecent
*   @package classifieds
*   Create a list of recently-posted ads
*/
class AdListRecent extends AdList
{
    /**
    *   Constructor, sets a limit clause
    */
    public function __construct()
    {
        $this->limit_clause = 'LIMIT 20';
        $this->pagename = 'recent';
    }
}


/**
*   @class AdListPoster
*   @package classifieds
*   Create a list of ads for a specific poster
*/
class AdListPoster extends AdList
{
    /**
    *   Constructor, sets the user ID
    *
    *   @param  integer $uid    User ID
    */
    public function __construct($uid = 0)
    {
        if ($uid > 0) {
            $this->where_clause = 'uid = ' . (int)$uid;
        }
        $this->pagename = 'byposter';
    }
}


/**
*   @class AdListCat
*   @package classifieds
*   Display the ads under the given category ID.
*   Also puts in the subscription link and breadcrumbs.
*/
class AdListCat extends AdList
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
        USES_classifieds_class_category();
        $this->Cat = new adCategory($this->cat_id);
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

        USES_classifieds_class_image();

        $T = new Template(CLASSIFIEDS_PI_PATH . '/templates');
        $T->set_file('header', 'adlisthdrCat.thtml');
        $T->set_var('pi_url', $_CONF['site_url'].'/'.$_CONF_ADVT['pi_name']);
        $T->set_var('catimg_url', adImage::thumbUrl($this->Cat->image));

        // Set the breadcrumb navigation
        $T->set_var('breadcrumbs', adCategory::BreadCrumbs($this->cat_id), true);

        // if non-anonymous, allow the user to subscribe to this category
        if (!COM_isAnonUser()) {
            // Determine whether the user is subscribed to notifications for
            // this category and display a message and or link accordingly
            $notice_count = DB_count($_TABLES['ad_notice'],
                array('uid', 'cat_id'),
                array($_USER['uid'], $this->cat_id)
            );

            if ($notice_count) {
                $sub_img = 'unsubscribe.png';
                $sub_vis = 'none';
                $unsub_vis = 'block';
            } else {
                $sub_vis = 'block';
                $unsub_vis = 'none';
            }
            // Display a link to submit an ad to the current category
            $submit_url = '';
            if (plugin_ismoderator_classifieds()) {
                $submit_url = $_CONF['site_admin_url'] . 
                        '/plugins/'. $_CONF_ADVT['pi_name'] . 
                        '/index.php?editad=x&cat_id='.$this->cat_id;
            } elseif ($this->Cat->canEdit()) {
                $submit_url = $_CONF['site_url']. '/' . $_CONF_ADVT['pi_name'] . 
                    '/index.php?mode=edit&cat_id=' . $this->cat_id;
            }
            $T->set_var(array(
                'subscribe_img' => CLASSIFIEDS_URL.'/images/'.$sub_img,
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
        $subcats = adCategory::SubCats($this->cat_id);
        $listvals = '';
        $max = count($CatListcolors);
        $i = 0;
        foreach ($subcats as $row) {
            // for each sub-category, add it to the list for getting ads
            $cat_for_adlist .= ",{$row['cat_id']}";
            // only show the category selection for immediate children.
            if ($row['papa_id'] != $this->cat_id) continue;

            $T->set_block('header', 'SubCat', 'sCat');
            if ($row['fgcolor'] == '' || $row['bgcolor'] == '') {
                if ($i >= $max) $i = 0;
                $T->set_var('bgcolor', $CatListcolors[$i][0]);
                $T->set_var('fgcolor', $CatListcolors[$i][1]);
                $i++;
            } else {
                $T->set_var('bgcolor', $row['bgcolor']);
                $T->set_var('fgcolor', $row['fgcolor']);
            }

            $T->set_var('subcat_url',
                CLASSIFIEDS_makeURL('list', $row['cat_id']));
            $T->set_var('subcat_name', $row['cat_name']);
            $T->set_var('subcat_count', adCategory::TotalAds($row['cat_id']));
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

}   // class AdListCat

?>
