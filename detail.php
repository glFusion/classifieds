<?php
/**
*   Display the detail page for an ad
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
 *  Display an ad's detail
 *  @param  string  $ad_id  ID of ad to display
 */
function adDetail($ad_id='')
{
    global $_USER, $_TABLES, $_CONF, $LANG_ADVT, $_CONF_ADVT;

    USES_lib_comments();

    // Determind if this is an administrator
    $admin = SEC_hasRights($_CONF_ADVT['pi_name']. '.admin');

    $ad_id = COM_sanitizeID($ad_id);
    if ($ad_id== '') {
        // An ad id is required for this function
        return CLASSIFIEDS_errorMsg($LANG_ADVT['missing_id'], 'alert');
    }
    $srchval = isset($_GET['query']) ? trim($_GET['query']) : '';

    // We use this in a few places here, so might as well just
    // figure it out once and save it.
    $perm_sql = COM_getPermSQL('AND', 0, 2, 'ad') . ' ' . 
                COM_getPermSQL('AND', 0, 2, 'cat');

    // get the ad information.
    $sql = "SELECT ad.*
            FROM {$_TABLES['ad_ads']} ad
            LEFT JOIN {$_TABLES['ad_category']} cat
                ON ad.cat_id = cat.cat_id
            WHERE ad_id='$ad_id'";
    if (!$admin) {
        $sql .= $perm_sql;
    }

    $result = DB_query($sql);
    if (!$result || DB_numRows($result) < 1)
        return CLASSIFIEDS_errorMsg($LANG_ADVT['no_ad_found'], 'note', 
            'Oops...');

    $ad = DB_fetchArray($result, false);

    // Check access to the ad.  If granted, check that access isn't
    // blocked by any category.
    $my_access = CLASSIFIEDS_checkAccess($ad['ad_id'], $ad);
    if ($my_access >= 2) {
        $my_cat_access = CLASSIFIEDS_checkCatAccess($ad['cat_id'], false);
        if ($my_cat_access < $my_access)
            $my_access = $my_cat_access;
    }
    if ($my_access < 2) {
        return CLASSIFIEDS_errorMsg($LANG_ADVT['no_permission'], 'alert', 
            $LANG_ADVT['access_denied']);
    }

    $cat = (int)$ad['cat_id'];

    // Increment the views counter
    $sql = "UPDATE {$_TABLES['ad_ads']} 
            SET views = views + 1 
            WHERE ad_id='$ad_id'";
    DB_query($sql);

    // Get the previous and next ads
    $condition = " AND ad.cat_id=$cat";
    if (!$admin) {
        $condition .= $perm_sql;
    }
    $sql = "SELECT ad_id
            FROM {$_TABLES['ad_ads']} ad
            LEFT JOIN {$_TABLES['ad_category']} cat
                ON ad.cat_id = cat.cat_id
            WHERE ad_id < '$ad_id' 
            $condition
            ORDER BY ad_id DESC
            LIMIT 1";
    $r = DB_query($sql);
    list($preAd_id) = DB_fetchArray($r, false);

    $sql = "SELECT ad_id
            FROM {$_TABLES['ad_ads']} ad
            LEFT JOIN {$_TABLES['ad_category']} cat
                ON ad.cat_id = cat.cat_id
            WHERE ad_id > '$ad_id' 
            $condition
            ORDER BY ad_id ASC
            LIMIT 1";
    $r = DB_query($sql);
    list($nextAd_id) = DB_fetchArray($r, false);

    // Get the user contact info. If none, just show the email link
    $sql = "SELECT * 
            FROM {$_TABLES['ad_uinfo']} 
            WHERE uid='{$ad['uid']}'";
    //echo $sql;
    $result = DB_query($sql);
    $uinfo = array();
    if ($result && DB_numRows($result) > 0) {
        $uinfo = DB_fetchArray($result);
    } else {
        $uinfo['uid'] = '';
        $uinfo['address'] = '';
        $uinfo['city'] = '';
        $uinfo['state'] = '';
        $uinfo['postal'] = '';
        $uinfo['tel'] = '';
        $uinfo['fax'] = '';
    }

    // Get the hot results (most viewed ads)
    $time = time();
    $sql = "SELECT ad.ad_id, ad.cat_id, ad.subject,
                    cat.cat_id, cat.fgcolor, cat.bgcolor
        FROM {$_TABLES['ad_ads']} ad
        LEFT JOIN {$_TABLES['ad_category']} cat
            ON ad.cat_id = cat.cat_id
        WHERE ad.exp_date > $time 
            $perm_sql
        ORDER BY views DESC 
        LIMIT 4";
    //echo $sql;die;
    $hotresult = DB_query($sql);

    // convert line breaks & others to html
    $patterns = array(
        '/\n/',
    );
    $replacements = array(
        '<br ' . XHTML . '>',
    );
    $ad['descript'] = PLG_replaceTags(COM_checkHTML($ad['descript']));
    $ad['descript'] = preg_replace($patterns,$replacements,$ad['descript']);
    $ad['subject'] = strip_tags($ad['subject']);
    $ad['price'] = strip_tags($ad['price']);
    $ad['url'] = COM_sanitizeUrl($ad['url']);
    $ad['keywords'] = strip_tags($ad['keywords']);

    // Highlight search terms, if any
    if ($srchval != '') {
        $ad['subject'] = COM_highlightQuery($ad['subject'], $srchval);
        $ad['descript'] = COM_highlightQuery($ad['descript'], $srchval);
    }
 
    $detail = new Template(CLASSIFIEDS_PI_PATH . '/templates');
    $detail->set_file('detail', 'detail.thtml');

    if ($admin) {
        $base_url = CLASSIFIEDS_ADMIN_URL . '/index.php';
        $del_link = $base_url . '?delete=ad&ad_id=' . $ad_id;
        $edit_link = $base_url . '?edit=ad&ad_id=' . $ad_id;
    } else {
        $base_url = CLASSIFIEDS_URL . '/index.php';
        $del_link = $base_url . '?mode=Delete&id=' . $ad_id;
        $edit_link = $base_url . '?mode=editad&id=' . $ad_id;
    }

    // Set up the "add days" form if this user is the owner
    // or an admin
    if ($my_access == 3) {
        // How many days has the ad run?
        $max_add_days = CLASSIFIEDS_calcMaxAddDays(($ad['exp_date'] - $ad['add_date']) / 86400);
        if ($max_add_days > 0) {
            $detail->set_var('max_add_days', $max_add_days);
        }
    }

    if ($ad['exp_date'] < $time) {
        $detail->set_var('is_expired', 'true');
    }
USES_classifieds_class_category();
    $detail->set_var(array(
        'base_url'      => $base_url,
        'edit_link'     => $edit_link,
        'del_link'      => $del_link,
        'curr_loc'      => adCategory::BreadCrumbs($cat, true),
        //'curr_loc'      => CLASSIFIEDS_BreadCrumbs($cat, true),
        'subject'       => $ad['subject'],
        'add_date'      => date($_CONF['shortdate'], $ad['add_date']),
        'exp_date'      => date($_CONF['shortdate'], $ad['exp_date']),
        'views_no'      => $ad['views'],
        'descript'      => $ad['descript'],
        'ad_type'       => CLASSIFIEDS_getAdTypeString($ad['ad_type']),

        'uinfo_address' => $uinfo['address'],
        'uinfo_city'    => $uinfo['city'],
        'uinfo_state'   => $uinfo['state'],
        'uinfo_postcode' => $uinfo['postcode'],
        'uinfo_tel'     => $uinfo['tel'],
        'uinfo_fax'     => $uinfo['fax'],
        'price'         => $ad['price'],
        'ad_id'         => $ad_id,
        'ad_url'        => $ad['url'],
        'username'      => $_CONF_ADVT['disp_fullname'] == 1 ?
            COM_getDisplayName($ad['uid']) :
            DB_getItem($_TABLES['users'], 'username', "uid={$ad['uid']}"),
        'fgcolor'       => $ad['fgcolor'],
        'bgcolor'       => $ad['bgcolor'],
        'cat_id'        => $ad['cat_id'],
    ) );

    // Display a link to email the poster, or other message as needed
    $emailfromuser = DB_getItem($_TABLES['userprefs'], 
                            'emailfromuser', 
                            "uid={$ad['uid']}");
    if (
        ($_CONF['emailuserloginrequired'] == 1 && COM_isAnonUser()) ||
        $emailfromuser < 1
    ) {
        $detail->set_var('ad_uid', '');
    } else {
        $detail->set_var('ad_uid', $ad['uid']);
    }
	
    if ($my_access == 3) {
        $detail->set_var('have_userlinks', 'true');
        if ($admin || $_CONF_ADVT['usercanedit'] == 1) {
            $detail->set_var('have_editlink', 'true');
        } else {
            $detail->set_var('have_editlink', '');
        }
    } else {
        $detail->set_var('have_userlinks', '');
    }
  
    // Retrieve the photos and put into the template
    $sql = "SELECT photo_id, filename
            FROM {$_TABLES['ad_photo']} 
            WHERE ad_id='$ad_id'";
    $photo = DB_query($sql);
    $photo_detail = '';
    $detail->set_var('have_photo', '');     // assume no photo available
    if ($photo && DB_numRows($photo) >= 1) {
        while ($prow = DB_fetchArray($photo)) {
            $img_small = LGLIB_ImageUrl(
                    "{$_CONF_ADVT['image_dir']}/{$prow['filename']}",
                    $_CONF_ADVT['detail_img_width']
            );
            $img_disp = LGLIB_ImageUrl(
                    "{$_CONF_ADVT['image_dir']}/{$prow['filename']}",
                    $_CONF_ADVT['img_max_width'],
                    $_CONF_ADVT['img_max_height']
            );
            //if ($prow['filename'] != '' && file_exists("{$_CONF_ADVT['image_dir']}/{$prow['filename']}")) {
            if (!empty($img_small)) {
                $detail->set_block('detail', 'PhotoBlock', 'PBlock');
                $detail->set_var(array(
                    'tn_width'  => $_CONF_ADVT['detail_img_width'],
                    'small_url' => $img_small,
                    'disp_url' => $img_disp,
                ) );
                //$detail->set_var('ph_file', $prow['filename']);
                //$detail->set_var('img_url', $_CONF_ADVT['image_url']);
                $detail->parse('PBlock', 'PhotoBlock', true);
                $detail->set_var('have_photo', 'true');
            }
        }
    }

    if (DB_count($_TABLES['ad_ads'], 'owner_id', (int)$ad['owner_id']) > 1) {
        $detail->set_var('byposter_url', 
            CLASSIFIEDS_URL . '/index.php?' .
            "page=byposter&uid={$ad['owner_id']}");
    }

    // Show previous and next ads
    if ($preAd_id != '') {
        $detail->set_var('previous', 
            '<a href="' . CLASSIFIEDS_makeURL('detail', $preAd_id) .
            "\">&lt;&lt;</a>");
    }
    if ($nextAd_id != '') {
        $detail->set_var('next', 
            '<a href="' . CLASSIFIEDS_makeURL('detail', $nextAd_id) .
            "\">  &gt;&gt;</a>");
    }

    // Show the "hot results"
    $hot_data = '';
    if ($hotresult) {
        $detail->set_block('detail', 'HotBlock', 'HBlock');
        while ($hotrow = DB_fetchArray($hotresult)) {
            $detail->set_var(array(
                'hot_title' => $hotrow['subject'],
                'hot_url'   => CLASSIFIEDS_makeURL('detail', $hotrow['ad_id']),
                'hot_cat'   => displayCat($hotrow['cat_id']),
            ) );
            /*$hot_data .= "<tr><td class=\"hottitle\"><a href=\"" .
                CLASSIFIEDS_makeURL('detail', $hotrow['ad_id']) .
                "\">{$hotrow['subject']}</a></small></td>\n";

            $hot_data .= "<td class=\"hotcat\">( " . displayCat($hotrow['cat_id']) . 
                        " )</td></tr>\n";*/
        }
        $detail->parse('HBlock', 'HotBlock', true);
    }

    $detail->set_var('whats_hot_row', $hot_data);

    // Show the user comments
    if (plugin_commentsupport_classifieds() && $ad['comments_enabled'] < 2) {
        $detail->set_var('usercomments',
            CMT_userComments($ad_id, $ad['subject'], 'classifieds', '', 
                '', 0, 1, false, false, $ad['comments_enabled']));
        //$detail->set_var('usercomments', CMT_userComments($ad_id, $subject, 
        //        'classifieds'));
    }

    $detail->parse('output','detail');
    $display = $detail->finish($detail->get_var('output'));
    return $display;


}   // adDetail()

 
?>
