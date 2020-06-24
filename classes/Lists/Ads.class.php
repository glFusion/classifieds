<?php
/**
 * List ads by category, recent submissions, etc.
 *
 * @author      Lee Garner <lee@leegarner.com>
 * @copyright   Copyright (c) 2016-2020 Lee Garner <lee@leegarner.com>
 * @package     classifieds
 * @version     v1.3.0
 * @license     http://opensource.org/licenses/gpl-2.0.php
 *              GNU Public License v2 or later
 * @filesource
 */
namespace Classifieds\Lists;
use Classifieds\Category;
use Classifieds\AdType;
use Classifieds\Image;


/**
 * Base class to create ad listings.
 * @package classifieds
 */
class Ads
{
    /** Category IDs to limit search.
     * @var array */
    protected $cat_ids = array();

    /** Ad type IDs to limit search.
     * @var array */
    protected $type_ids = array();

    /** User ID to filter by poster.
     * @var integer */
    protected $uid = 0;


    /**
     * Set the category if one is specified.
     *
     * @param   integer $cat_id     Category ID
     */
    public function __construct($cat_id = NULL)
    {
        if (!empty($cat_id) && $cat_id > 1) {
            $this->addCats($cat_id);
        }
    }


    /**
     * Display an expanded ad listing.
     *
     * @return  string      Page Content
     */
    public function Render()
    {
        global $_TABLES, $LANG_ADVT, $_CONF, $_USER, $_CONF_ADVT;

        // Max number of ads per page
        $maxAds = isset($_CONF_ADVT['maxads_pg_exp']) ?
                (int)$_CONF_ADVT['maxads_pg_exp'] : 20;

        // Get the ads for this category, starting at the requested page
        $sql = "SELECT ad.*, ad.add_date as ad_add_date, cat.cat_id, cat.cat_name
            FROM {$_TABLES['ad_ads']} ad
            LEFT JOIN {$_TABLES['ad_category']} cat
                ON cat.cat_id = ad.cat_id
            LEFT JOIN {$_TABLES['ad_types']} at
                ON at.id = ad.ad_type
            WHERE ad.exp_date > UNIX_TIMESTAMP() " .
            COM_getPermSQL('AND', 0, 2, 'cat');

        if ($this->uid > 0) {
            $sql .= " AND ad.uid = {$this->uid}";
        }
        if (!empty($this->type_ids)) {
            $sql .= ' AND ad.ad_type in (' . implode(',', $this->type_ids) . ')';
        }
        $sql .= ' AND at.enabled = 1';
        if (!empty($this->cat_ids)) {
            $sql .= ' AND ad.cat_id in (' . implode(',', $this->cat_ids) . ')';
        }
        $sql .= " ORDER BY ad.add_date DESC";
        //echo $sql;die;

        $result = DB_query($sql);
        if (!$result) return "Database Error";
        $totalAds = DB_numRows($result);

        // Figure out the page number, and execute the query
        // with the appropriate LIMIT clause.
        if ($totalAds <= $maxAds) {
            $totalPages = 1;
        } elseif ($totalAds % $maxAds == 0) {
            $totalPages = $totalAds / $maxAds;
        } else {
            $totalPages = ceil($totalAds / $maxAds);
        }

        $page = isset($_REQUEST['start']) ? (int)$_REQUEST['start'] : 1;
        if ($page < 1 || $page > $totalPages) {
            $page = 1;
        }
        $startEntry = ($totalAds == 0) ? 0 : $maxAds * $page - $maxAds + 1;
        $endEntry = ($page == $totalPages) ? $totalAds : $maxAds * $page;
        $initAds = $maxAds * ($page - 1);

        $T = new \Template($_CONF_ADVT['path'] . '/templates/lists');
        $T->set_file('catlist', 'ads.thtml');

        // Create the page menu string for display if there is more
        // than one page
        if ($totalPages > 1) {
            $pageMenu = COM_printPageNavigation(
                $_CONF_ADVT['url'] . '/index.php',
                $page,
                $totalPages,
                'start='
            );
            $T->set_var('pagemenu', $pageMenu);
        }

        $sql .= " LIMIT $initAds, $maxAds";
        //echo $sql;die;
        $result = DB_query($sql);
        if (!$result) return CLASSIFIEDS_errorMsg($LANG_ADVT['database_error'], 'alert');

        if ($totalAds == 0) {
            $T->set_block('catlist', 'No_Ads', 'NoAdBlk');
            $T->set_var('no_ads', true);
            $T->parse('NoAdBlk', 'No_Ads', true);
        }

        $T->set_block('catlist', 'QueueRow', 'QRow');
        $counter = 0;
        while ($row = DB_fetchArray($result, false)) {
            $AT = AdType::getInstance($row['ad_type']);
            $T->set_var(array(
                'cat_id'    => $row['cat_id'],
                'subject'   => strip_tags($row['subject']),
                'ad_id'     => $row['ad_id'],
                'ad_url'    => CLASSIFIEDS_makeURL('detail', $row['ad_id']),
                'add_date'  => date($_CONF['shortdate'], $row['ad_add_date']),
                'ad_type'   => $AT->getDscp(),
                'at_fgcolor' => $AT->getFGColor(),
                'at_bgcolor' => $AT->getBGColor(),
                'cat_name'  => $row['cat_name'],
                'cat_url'   => CLASSIFIEDS_makeURL('home', $row['cat_id']),
                //'cmt_count' => CLASSIFIEDS_commentCount($row['ad_id']),
                //'descript' => substr(strip_tags($row['description']), 0, 300),
                //'ellipses'  => strlen($row['description']) > 300 ? '...' : '',
                'price'     => $row['price'] != '' ? strip_tags($row['price']) : '',
                //'tn_cellwidth' => $_CONF_ADVT['thumb_max_size'] - 20,
                //'adblock'   => PLG_displayAdBlock('classifieds_list', ++$counter),
            ) );
            $filename = Image::getFirst($row['ad_id']);
            $T->set_var(array(
                'img_url'   => Image::dispUrl($filename),
                'thumb_url' => Image::thumbUrl($filename),
            ) );
            $T->parse('QRow', 'QueueRow', true);
        }

        // Create the category filter checkboxes.
        // Only show categories that are in use.
        $T->set_block('catlist', 'CatChecks', 'CC');
        $i = 0;
        $prefix = '<i class="uk-icon uk-icon-angle-right uk-text-disabled uk-icon-justify"></i>';
        foreach (Category::getTree(0, $prefix) as $Cat) {
            if (
                Category::TotalAds($Cat->getID()) == 0 ||
                !$Cat->checkAccess(2)
            ) {
                continue;
            }
            $T->set_var(array(
                'cat_id'    => $Cat->getID(),
                'cat_name'  => $Cat->getDispName(),
                'cat_chk'   => in_array($Cat->getID(), $this->cat_ids) ? 'checked="checked"' : '',
                'cnt'       => ++$i,
            ) );
            $T->parse('CC', 'CatChecks', true);
        }
        $T->set_var('num_cats', $i);

        // Create the ad type filter checkboxes.
        // Only show types that are in use.
        $T->set_block('catlist', 'TypeChecks', 'TC');
        $i = 0;
        foreach (AdType::getAll() as $AT) {
            if (!$AT->isUsed()) {
                continue;
            }
            $T->set_var(array(
                'type_id'   => $AT->getID(),
                'type_name' => $AT->getDscp(),
                'type_chk'   => in_array($AT->getID(), $this->type_ids) ? 'checked="checked"' : '',
                'cnt'       => ++$i,
            ) );
            $T->parse('TC', 'TypeChecks', true);
        }
        $T->set_var('num_types', $i);

        // Create the user filter checkboxes.
        // Only show users who have posted ads.
        $T->set_block('catlist', 'UserChecks', 'UC');
        $sql = "SELECT DISTINCT(ad.uid), u.username, u.fullname
            FROM {$_TABLES['ad_ads']} ad
            LEFT JOIN {$_TABLES['users']} u
            ON ad.uid = u.uid";
        $res = DB_query($sql);
        $T->set_var('num_posters', DB_numrows($res));
        $i = 0;
        while ($A = DB_fetchArray($res, false)) {
            $T->set_var(array(
                'uid'   => $A['uid'],
                'user_name' => COM_getDisplayName($A['uid'], $A['username'], $A['fullname']),
                'uid_sel' => $A['uid'] == $this->uid ? 'selected="selected"' : '',
            ) );
            $T->parse('UC', 'UserChecks', true);
            $i++;
        }
        $T->set_var('num_users', $i);

        $T->set_var('totalAds', $totalAds);
        $T->set_var('adsStart', $startEntry);
        $T->set_var('adsEnd', $endEntry);
        $T->parse('output', 'catlist');
        return $T->finish($T->get_var('output'));
    }   // function Render()


    /**
     * Set the user ID to filter ads by poster.
     *
     * @param   integer $uid        User ID
     * @return  object  $this
     */
    public function setUid($uid)
    {
        $this->uid = (int)$uid;
    }


    /**
     * Add ad types to the type filter.
     *
     * @param   array   $types      Array of type IDs, or a single ID
     * @return  object  $this
     */
    public function addTypes($types=array())
    {
        if (is_array($types)) {
            foreach ($types as $id) {
                $this->type_ids[] = (int)$id;
            }
        } elseif ((int)$types > 0) {
            $this->type_ids[] = (int)$types;
        }
        return $this;
    }


    /**
     * Set the category ID limiters.
     * May be called multiple times.
     *
     * @param   array   $cats   Array of category IDs
     * @return  object  $this
     */
    public function addCats($cats=array())
    {
        if (is_array($cats)) {
            foreach ($cats as $id) {
                $this->cat_ids[] = (int)$id;
            }
        } elseif ((int)$cats > 0) {
            $this->cat_ids[] = (int)$cats;
        }
        return $this;
    }

}

?>
