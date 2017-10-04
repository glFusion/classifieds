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

        // Fix time to check ad expiration
        $time = time();

        // Max number of ads per page
        $maxAds = isset($_CONF_ADVT['maxads_pg_exp']) ?
                (int)$_CONF_ADVT['maxads_pg_exp'] : 20;

        $T = new \Template($_CONF_ADVT['path'] . '/templates');
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
            $baseURL = $_CONF_ADVT['url'] . "/index.php?page=$pagename";
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
        $counter = 0;
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
                'descript' => substr(strip_tags($row['description']), 0, 300),
                'ellipses'  => strlen($row['descript']) > 300 ? '...' : '',
                'price'     => $row['price'] != '' ? strip_tags($row['price']) : '',
                'is_uikit'  => $_CONF_ADVT['_is_uikit'] ? 'true' : '',
                'tn_cellwidth' => $_CONF_ADVT['thumb_max_size'] - 20,
                'adblock'   => PLG_displayAdBlock('classifieds_list', ++$counter),
            ) );

            $photos = Image::GetAll($row['ad_id'], 1);
            if (empty($photos)) {
                $filename = current($photos);
                $T->set_var(array(
                    'img_url'   => '',
                    'thumb_url' => '',
                ) );
            } else {
                $filename = current($photos);
                $T->set_var(array(
                    'img_url'   => Image::dispUrl($filename),
                    'thumb_url' => Image::thumbUrl($filename),
                ) );
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

?>
