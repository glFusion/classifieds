<?php
/**
*   List ads.  By category, recent submissions, etc.
*
*   @author     Lee Garner <lee@leegarner.com>
*   @copyright  Copyright (c) 2009 Lee Garner <lee@leegarner.com>
*   @package    classifieds
*   @version    1.0.0
*   @license    http://opensource.org/licenses/gpl-2.0.php 
*               GNU Public License v2 or later
*   @filesource
*/


/**
 *  Display the ads under the given category ID.  Also puts in the
 *  subscription link and breadcrumbs.
 *  @param integer $cat Category number to list
 *  @return string  HTML for category list
 */
function adListCat($cat = '')
{
    global $_TABLES, $LANG_ADVT, $_CONF, $_USER, $_CONF_ADVT, $_GROUPS;
    global $CatListcolors;
 
    if ($cat == '')
        return;

    if (CLASSIFIEDS_checkCatAccess($cat) < 2 ) {
        return CLASSIFIEDS_errorMsg($LANG_ADVT['cat_unavailable'], 'alert');
    }

    $T = new Template(CLASSIFIEDS_PI_PATH . '/templates');
    $T->set_file('header', CLASSIFIEDS_getTemplate('adlisthdrCat'));
    $T->set_var('pi_url', $_CONF['site_url'].'/'.$_CONF_ADVT['pi_name']);

    $sql = "SELECT image, owner_id, group_id, papa_id
                perm_owner, perm_group, perm_members, perm_anon
            FROM {$_TABLES['ad_category']}
            WHERE cat_id=$cat";
    $r = DB_query($sql);
    if (!$r || DB_numRows($r) < 1) return;
    $row = DB_fetchArray($r);

    $img_name = $row['image'];
    if ($img_name != '') {
        $T->set_var('catimg_url', CLASSIFIEDS_thumbUrl($img_name));
    }

    // Set the breadcrumb navigation
    //$T->set_var('breadcrumbs', CLASSIFIEDS_BreadCrumbs($cat), true);
    USES_classifieds_class_category();
    $T->set_var('breadcrumbs', adCategory::BreadCrumbs($cat), true);

    // if non-anonymous, allow the user to subscribe to this category
    if (!COM_isAnonUser()) {
        $result = DB_getItem(
            $_TABLES['ad_notice'],
            'count(*)',
            "uid = {$_USER['uid']} AND cat_id = $cat"
        );

        // Determine whether the user is subscribed to notifications for
        // this category and display a message and or link accordingly
        $subscribed = $result > 0 ? 1 : 0;
        if ($subscribed) {
            $T->set_var('subscribe_msg',
                //$LANG_ADVT['you_are_subscribed']. '&nbsp;&nbsp;
                '<a href="'.
                CLASSIFIEDS_makeURL('del_notice', $cat). '">'.
                COM_createImage(CLASSIFIEDS_URL . '/images/unsubscribe.png',
                $LANG_ADVT['remove'],
                array('title' => $LANG_ADVT['you_are_subscribed'],
                        'class' => 'gl_mootip')));
                
        } else {
            $T->set_var('subscribe_msg', '<a href="'.
                CLASSIFIEDS_makeURL('add_notice', $cat). '">' . 
                COM_createImage(CLASSIFIEDS_URL . '/images/subscribe.png', 
                    $LANG_ADVT['subscribe'], 
                    array('title' => $LANG_ADVT['subscribe'],
                        'class' => 'gl_mootip')));
        }

        // Display a link to submit an ad to the current category
        $submit_url = '';
        if (SEC_hasRights($_CONF_ADVT['pi_name']. '.admin')) {
            $submit_url = $_CONF['site_admin_url'] . 
                    '/plugins/'. $_CONF_ADVT['pi_name'] . 
                    '/index.php?mode=edit&cat='.$cat;
        } elseif (CLASSIFIEDS_checkCatAccess($cat, false, $row) == 3) {
            $submit_url = $_CONF['site_url']. '/submit.php?type='. 
                $_CONF_ADVT['pi_name'] . '&cat='. $cat;
        }
        $T->set_var('submit_url', $submit_url);
    } else {
        // Not-logged-in users can't subscribe or submit.
        $T->set_var('subscribe_msg', '');
        $T->set_var('submit_msg', '');
    }

    // This is a comma-separated string of category IDs for a SQL "IN" clause.
    // Start with the current category
    $cat_for_adlist = $cat;

    // Get the sub-categories which have this category as their parent
    USES_classifieds_class_category();
    $subcats = adCategory::SubCats($cat);
    $listvals = '';
    $max = count($CatListcolors);
    $i = 0;
    foreach ($subcats as $row) {
        // for each sub-category, add it to the list for getting ads
        $cat_for_adlist .= ",{$row['cat_id']}";
        // only show the category selection for immediate children.
        if ($row['papa_id'] != $cat) continue;

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
                AND exp_date > $time "
                . COM_getPermSQL('AND', 0, 2);
    //echo $sql;
    $result = DB_query($sql);
    if (!$result)
        return CLASSIFIEDS_errorMsg($LANG_ADVT['database_error'], 'alert');
    $totalAds = DB_numRows($result);

    $where_clause = " ad.cat_id IN ($cat_for_adlist)
        AND ad.exp_date > $time ";

    $T->parse('output', 'header');
    $retval = $T->finish($T->get_var('output'));

    $retval .= adExpList('', $cat, $where_clause);

    return $retval;

}


/**
 *  Display the most recent 20 ads (or all, whichever is less).
 *  @return string      Page Content
 */
function adListRecent()
{
    global $_TABLES;

    $limit_clause = "LIMIT 20";

    //$totalAds = min(DB_count($_TABLES['ad_ads']), 20);
    return adExpList('recent', '', '', $limit_clause);

}


/**
 *  Display the most recent 20 ads (or all, whichever is less).
 *  @param  integer $uid    Poster user ID
 *  @return string          Page Content
 */
function adListPoster($uid=0)
{
    global $_TABLES;

    if ($uid > 1)
        $where_clause = "ad.owner_id=$uid"; 

    //$totalAds = min(DB_count($_TABLES['ad_ads']), 20);
    return adExpList('byposter', '', $where_clause);

}


/**
 *  Display an expanded ad listing.
 *  @param  string  $pagename       Name of page in index.php the called us
 *  @param  integer $cat_id         Optional category ID to be appended to url
 *  @param  string  $where_clause   Additional SQL where clause
 *  @param  string  $limit_clause   Optional limit clause
 *  @return string                  Page Content
 */
function adExpList($pagename='', $cat_id='', $where_clause='', $limit_clause='')
{
    global $_TABLES, $LANG_ADVT, $_CONF, $_USER, $_CONF_ADVT;

    // Fix time to check ad expiration
    $time = time();

    // Max number of ads per page
    $maxAds = isset($_CONF_ADVT['maxads_pg_exp']) ? 
                (int)$_CONF_ADVT['maxads_pg_exp'] : 20;

    $T = new Template(CLASSIFIEDS_PI_PATH . '/templates');
    $T->set_file('catlist', 'adExpList.thtml');

    // Get the ads for this category, starting at the requested page
    $sql = "SELECT ad.*, ad.add_date as ad_add_date, cat.*
            FROM {$_TABLES['ad_ads']} ad ,
                {$_TABLES['ad_category']} cat 
            WHERE cat.cat_id = ad.cat_id 
            AND ad.exp_date > $time "
            . COM_getPermSQL('AND', 0, 2, 'ad') 
            . COM_getPermSQL('AND', 0, 2, 'cat');
    if ($where_clause != '')
        $sql .= " AND $where_clause ";
    $sql .= " ORDER BY ad.add_date DESC";
    //echo $sql;die;

    // first execute the query with the supplied limit clause to get
    // the total number of ads eligible for viewing
    $sql1 = $sql . ' ' . $limit_clause;
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
    if ($page < 1 || $page > $totalPages)
        $page = 1;

    if ($totalAds == 0)
        $startEntry = 0;
    else
        $startEntry = $maxAds * $page - $maxAds + 1;

    if ($page == $totalPages)
        $endEntry = $totalAds;
    else
        $endEntry = $maxAds * $page;

    //$prePage = $page - 1;
    //$nextPage = $page + 1;
    $initAds = $maxAds * ($page - 1);

    // Create the page menu string for display if there is more
    // than one page
    $pageMenu = '';
    if ($totalPages > 1) {
        $baseURL = CLASSIFIEDS_URL . "/index.php?page=$pagename";
        if ($cat_id != '')
            $baseURL .= "&amp;id=$cat_id";
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
        $T->set_var('bgColor', $bgColor);
        $T->set_var('cat_id', $row['cat_id']);
        $T->set_var('subject', strip_tags($row['subject']));
        $T->set_var('ad_id', $row['ad_id']);
        $T->set_var('ad_url', CLASSIFIEDS_makeURL('detail', $row['ad_id']));
        //$T->set_var('add_date', date("m/d/y", $row['ad_add_date']));
        $T->set_var('add_date', date($_CONF['shortdate'], $row['ad_add_date']));
        //$T->set_var('ad_type', $row['forsale'] == 1 ?
        //        $LANG_ADVT['forsale'] : $LANG_ADVT['wanted']);
        $T->set_var('ad_type', CLASSIFIEDS_getAdTypeString($row['ad_type']));
        $T->set_var('cat_name', $row['cat_name']);
        $T->set_var('cat_url', CLASSIFIEDS_makeURL('home', $row['cat_id']));
        $T->set_var('cmt_count', CLASSIFIEDS_commentCount($row['ad_id']));

        $sql = "SELECT filename
                FROM {$_TABLES['ad_photo']}
                WHERE ad_id='{$row['ad_id']}'
                LIMIT 1";
        $photo = DB_query($sql);
        if (!$photo)
            return CLASSIFIEDS_errorMsg($LANG_ADVT['database_error'], 'alert');

        // Retrieve the first image.  If it is define AND exists on the
        // filesystem, then use it.  Otherwise display "not available".
        if (DB_numRows($photo) == 1) {
            $prow = DB_fetchArray($photo);
            $T->set_var('img_url', CLASSIFIEDS_dispUrl($prow['filename']));
            $T->set_var('thumb_url', CLASSIFIEDS_thumbUrl($prow['filename']));


        } else {
            $T->set_var('img_url', '');
        }

//        $T->set_var('descript', htmlspecialchars(COM_stripslashes(substr(strip_tags($row['descript']), 0, 300))));
        $T->set_var('descript', substr(strip_tags($row['descript']), 0, 300));
        if (strlen($row['descript']) > 300)
            $T->set_var('ellipses', "... ...");

        if ($row['price'] != '')
            $T->set_var('price', COM_stripslashes($row['price']));
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

}   // function adExpList()



/**
 *  When no category is given, show a table of all categories
 *  along with the count of ads for each.  
 *  Returns the results from the category
 *  list function, chosen based on the display mode
 *  @return string      HTML for category listing page
 */
function CLASSIFIEDS_catList()
{
    global $_CONF_ADVT;

    switch ($_CONF_ADVT['catlist_dispmode']) {
    case 'blocks':
        return CLASSIFIEDS_catList_blocks();
        break;

    default:
        return CLASSIFIEDS_catList_normal();
        break;
    }
}


/**
 *  Create a "normal" list of categories, using text links
 *  @return string      HTML for category listing page
 */
function CLASSIFIEDS_catList_normal()
{
    global $_CONF, $_TABLES, $LANG_ADVT, $_CONF_ADVT;

    $T = new Template(CLASSIFIEDS_PI_PATH . '/templates');
    $T->set_file('page', 'catlist.thtml');
    $T->set_var('site_url',$_CONF['site_url']);
    $T->set_var('site_admin_url', $_CONF['site_admin_url']);

    // Get all the root categories
    $sql = "
        SELECT 
            * 
        FROM 
            {$_TABLES['ad_category']} 
        WHERE 
            papa_id=''
            " . COM_getPermSQL('AND', 0, 2) . "
        ORDER BY cat_name ASC
    ";
    //echo $sql;die;
    $cats = DB_query($sql);
    if (!$cats) return CLASSIFIEDS_errorMsg($LANG_ADVT['database_error'], 'alert');

    // If no root categories exist, display just return a message
    if (DB_numRows($cats) == 0) {
        $T->set_var('no_cat_found', 
            "<p align=\"center\" class=\"headmsg\">
            $LANG_ADVT[no_cat_found]</p>\n");
        $T->parse('output', 'page');
        return $T->finish($T->get_var('output'));
    } 

    $T->set_block('page', 'CatRows', 'CRow');

    $i = 1;
    while ($catsrow = DB_fetchArray($cats)) {
        // For each category, find the total ad count (including subcats)
        // and display the subcats below it.
        $T->set_var('rowstart', $i % 2 == 1 ? "<tr>\n" : "");
        //$T->set_var('cat_url', "$PHP_SELF?id={$catsrow['cat_id']}");
        $T->set_var('cat_url', CLASSIFIEDS_makeUrl('home', $catsrow['cat_id']));
        $T->set_var('cat_name', $catsrow['cat_name']);
        $T->set_var('cat_ad_count', findTotalAds($catsrow['cat_id']));
        if ($catsrow['image'] != '') {
            $T->set_var('cat_image', CLASSIFIEDS_thumbUrl($catsrow['image']));
        } else {
            $T->set_var('cat_image', '');
        }

        $sql = "SELECT * FROM {$_TABLES['ad_category']} 
                WHERE papa_id={$catsrow['cat_id']} 
                " . COM_getPermSQL('AND', 0, 2) . "
                ORDER BY cat_name ASC";
        //echo $sql;die;
        $subcats = DB_query($sql);
        if (!$subcats) return CLASSIFIEDS_errorMsg($LANG_ADVT['database_error'], 'alert');

        $num = DB_numRows($subcats);
        $time = time();
        // Earliest time to be considered "new"
        $newtime = $time - 3600 * 24 * $_CONF_ADVT['newcatdays'];
        $subcatlist = '';

        $j = 1;
        while ($subcatsrow = DB_fetchArray($subcats)) {

            $isnew = $subcatsrow['add_date'] > $newtime ? 
                "<img src=\"{$_CONF['site_url']}/{$_CONF_ADVT['pi_name']}/images/new.gif\" align=\"top\">" : 
                "";

            $subcatlist .= 
                '<a href="'.
                CLASSIFIEDS_makeURL('home', $subcatsrow['cat_id']). '">'.
                "{$subcatsrow['cat_name']}</a>&nbsp;(".
                findTotalAds($subcatsrow['cat_id']). ")&nbsp;{$isnew}";

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
 *  Create a category listing page showing the categories in block styling.
 *  @return string      HTML for category listing page
 */
function CLASSIFIEDS_catList_blocks()
{
    global $_CONF, $_TABLES, $LANG_ADVT, $_CONF_ADVT;
    global $CatListcolors;

    $T = new Template(CLASSIFIEDS_PI_PATH . '/templates');
    $T->set_file('page', 'catlist_blocks.thtml');
    $T->set_var('site_url',$_CONF['site_url']);
    $T->set_var('site_admin_url', $_CONF['site_admin_url']);

    // Get all the root categories
    $sql = "SELECT * FROM {$_TABLES['ad_category']} 
            WHERE papa_id='' " .
                COM_getPermSQL('AND', 0, 2) .
            " ORDER BY cat_name ASC";
    //echo $sql;die;
    $cats = DB_query($sql);
    if (!$cats) return CLASSIFIEDS_errorMsg($LANG_ADVT['database_error'], 'alert');

    // If no root categories exist, display just return a message
    if (DB_numRows($cats) == 0) {
        $T->set_var('no_cat_found', 
            "<p align=\"center\" class=\"headmsg\">
            $LANG_ADVT[no_cat_found]</p>\n");
        $T->parse('output', 'page');
        return $T->finish($T->get_var('output'));
    } 

    $max = count($CatListcolors);

    $i = 0;
    while ($catsrow = DB_fetchArray($cats)) {
        if ($catsrow['fgcolor'] == '' || $catsrow['bgcolor'] == '') {
            if ($i >= $max) $i = 0;
            $bgcolor = $CatListcolors[$i][0];
            $fgcolor = $CatListcolors[$i][1];
            $hdcolor = $CatListcolors[$i][2];
            $i++;
        } else {
            $fgcolor = $catsrow['fgcolor'];
            $bgcolor = $catsrow['bgcolor'];
        } 

        // For each category, find the total ad count (including subcats)
        // and display the subcats below it.
        $T->set_block('page', 'CatDiv', 'Div');
        $T->set_var('bgcolor', $bgcolor);
        $T->set_var('fgcolor', $fgcolor);
        //$T->set_var('hdcolor', $hdcolor);
        $T->set_var('cat_url', CLASSIFIEDS_makeUrl('home',$catsrow['cat_id']));
        $T->set_var('cat_name', $catsrow['cat_name']);
        $T->set_var('cat_desc', $catsrow['description']);
        $T->set_var('cat_ad_count', findTotalAds($catsrow['cat_id']));
        if ($catsrow['image']) {
            $T->set_var('cat_image', CLASSIFIEDS_thumbUrl('cat/'.$catsrow['image']));
        } else {
            $T->set_var('cat_image', '');
        }
        $T->parse('Div', 'CatDiv', true);
    }

    $T->parse('output', 'page');
    return $T->finish($T->get_var('output'));
 
}


?>
