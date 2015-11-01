<?php
/**
*   Provide functions for managing categories
*
*   @author     Lee Garner <lee@leegarner.com>
*   @copyright  Copyright (c) 2009 Lee Garner <lee@leegarner.com>
*   @package    classifieds
*   @version    1.0.0
*   @license    http://opensource.org/licenses/gpl-2.0.php 
*               GNU Public License v2 or later
*   @filesource
*/

/** Import core glFusion libraries */
require_once '../../../lib-common.php';


/**
 *  Create an edit form for a category
 *  @param int $catid Category ID, zero for a new entry
 */
function CLASSIFIEDS_catEdit($catid = 0)
{
    global $_CONF, $_TABLES, $LANG_ADVT, $_CONF_ADVT, $LANG_ACCESS, $_USER;

    $catid = (int)$catid;
    if ($catid >= 0) {
        $sql = "SELECT * FROM  {$_TABLES['ad_category']} 
                WHERE cat_id=$catid";
        //echo $sql;
        $result = DB_query($sql);

        if (!$result || DB_numRows($result) == 0) {
            $catid = 0;
            $parentcat = 0;
        } else {
            $row = DB_fetchArray($result);
            $parentcat = $row['papa_id'];
        }
    }
    $T = new Template(CLASSIFIEDS_PI_PATH . '/templates/admin');
    $T->set_file('modify', 'catEditForm.thtml');
    $T->set_var('cat', $catid);
    $T->set_var('cancel_url', CLASSIFIEDS_ADMIN_URL. '/index.php?admin=cat');


    // populate the category list at the bottom of the form.
    // Get root categories
    $sql = "SELECT cat_name, cat_id
            FROM {$_TABLES['ad_category']}
            WHERE papa_id='' 
            ORDER BY cat_name ASC";
    $cats = DB_query($sql);
    if (!$cats) return CLASSIFIEDS_errorMsg($LANG_ADVT['database_error'], 'alert');

    // Now get the subcategories
    $i = 1;
    while ($catsrow = DB_fetchArray($cats)) {
        $sql = "SELECT cat_name, cat_id, add_date 
                FROM {$_TABLES['ad_category']} 
                WHERE papa_id={$catsrow['cat_id']}";
        $subcats = DB_query($sql);
        if (!$subcats) return CLASSIFIEDS_errorMsg($LANG_ADVT['database_error'], 'alert');

        $catlist .= $i % 2 == 1 ? "    <tr>\n" : "";
        $catlist .= "      <td align=\"left\" width=\"50%\">";
        $catlist .= "<input type=\"checkbox\" name=\"c[]\" value=\"{$catsrow['cat_id']}\">
            <a href=\"$PHP_SELF?mode=admincats&cat_id={$catsrow['cat_id']}\"><b>" .
            htmlspecialchars($catsrow['cat_name']) . "</b></a>
            (" . findTotalAds($catsrow['cat_id']) . ")<br>\n";

        $num = DB_numRows($subcats);
        $j = 1;
        $time = time();

        while ($subcatsrow = DB_fetchArray($subcats )) {
            $isnew = $subcatsrow['add_date'] > $time - (86400 * $_CONF_ADVT['newcatdays']) ? 
                    "&nbsp;<img src=\"{$_CONF['site_url']}/{$_CONF_ADVT['pi_name']}/images/new.gif\" align=\"top\">" : 
                    "";
            $catlist .= "<nobr><input type=\"checkbox\" name=\"c[]\" value=\"{$subcatsrow['cat_id']}\">
                <a href=\"$PHP_SELF?mode=admincats&cat_id={$subcatsrow['cat_id']}\">" .
                $subcatsrow['cat_name'] . "</a>$isnew</nobr>\n";

            $j++;
        }
        $catlist .= "      <br>&nbsp;\n";
        $catlist .= "      </td>\n";
        $catlist .= $i % 2 == 0 ? "    </tr>\n" : "";
        $i++;
    }

    $T->set_var('catlist', $catlist);

    // create a dropdown list of only master categories
    $T->set_var('sel_parent_cat', 
        "<option value=0>--{$LANG_ADVT['none']}--</option>"
        .COM_optionList($_TABLES['ad_category'], 
            'cat_id,cat_name',$parentcat,1, "cat_id <> $catid AND papa_id=0"));


    // this code creates a complete dropdown including subcategories
/*    $T->set_var('sel_parent_cat',
        "<option value=0>--{$LANG_ADVT['none']}--</option>"
        . CLASSIFIEDS_buildCatSelection($parentcat, 0, '', 'NOT', $catid));
*/

    // if we still have a catid, then this is an existing category and 
    // was found.  Load the template with existing values
    if ($catid > 0) {
        $cat_name = $row['cat_name'];
        $keywords = $row['keywords'];
        $description = $row['description'];

        $T->set_var('txt_sub_btn', $LANG_ADVT['upd_cat']);
        $T->set_var('location', CLASSIFIEDS_BreadCrumbs($catid), true);

        $T->set_var('catname', $cat_name);
        $T->set_var('keywords', $keywords);
        $T->set_var('description', $description);
        $T->set_var('fgcolor', $row['fgcolor']);
        $T->set_var('bgcolor', $row['bgcolor']);

        if ($row['image'] != '' && 
            file_exists(CLASSIFIEDS_IMGPATH . '/cat/' . $row['image'])) {
            $T->set_var('existing_image',
                '<td>
                    <img src=\"' . CLASSIFIEDS_thumbUrl($row['image'] . "\" 
                        width=48 height=48><br />
                    <a href=\"?mode=delcatimg&cat_id={$row['cat_id']}\">{$LANG_ADVT['delete']}</a>
                </td>");
        }

        // Build permissions block
        $T->set_var('permissions_editor', 
            SEC_getPermissionsHTML($row['perm_owner'],$row['perm_group'],
                $row['perm_members'],$row['perm_anon']));
        $T->set_var('ownername', COM_getDisplayName($row['owner_id']));
        $T->set_var('owner_id', $row['owner_id']);
        $T->set_var('group_dropdown',
            SEC_getGroupDropdown($row['group_id'], 3));

        $catinfo = '';
        while ($row = DB_fetchArray($result)) {
            $catinfo .= "<input type=\"checkbox\" name=\"c[]\" value=\"{$row['cat_id']}\">
                <a href=\"$PHP_SELF?cat={$row['cat_id']}\">$cat_name</a>(" 
                . findTotalAds( $row['cat_id'] ) . ")<br>\n";
        }
        $T->set_var('catinfo', $catinfo);
    } else {
        // no category id indicates a new addition
        $T->set_var('txt_sub_btn', $LANG_ADVT['add_cat']);
        $T->set_var('permissions_editor', 
            SEC_getPermissionsHTML($_CONF_ADVT['default_perm_cat'][0],
                $_CONF_ADVT['default_perm_cat'][1],
                $_CONF_ADVT['default_perm_cat'][2],
                $_CONF_ADVT['default_perm_cat'][3]));
        $T->set_var('ownername', COM_getDisplayName($_USER['uid']));
        $T->set_var('owner_id', $_USER['uid']);
        $T->set_var('group_dropdown',
            SEC_getGroupDropdown($_CONF_ADVT['defgrpcat'], 3));

    }

    $T->parse('output','modify');
    $display .= $T->finish($T->get_var('output'));
    return $display;

}   // function catManage()


/**
 *  Insert or update a category.
 *  @param integer $catid Category ID (0 for new category)
 */
function catSave($catid = 0)
{
    global $_TABLES, $_CONF_ADVT;

    $vars = array();    // array to simplify form variable handling
    $vars['catname'] = glfPrepareForDB($_POST['catname']);
    $vars['parentcat'] = (int)$_POST['parentcat'];
    $vars['keywords'] = glfPrepareForDB(trim($_POST['keywords']));
    $vars['description'] = glfPrepareForDB(trim($_POST['description']));
    $vars['perms']= SEC_getPermissionValues($_POST['perm_owner'],
                $_POST['perm_group'], $_POST['perm_members'],
                $_POST['perm_anon']);
    $vars['owner_id'] = (int)$_POST['owner_id'];
    $vars['group_id'] = (int)$_POST['group_id'];
    $vars['fgcolor'] = glfPrepareForDB(trim($_POST['fgcolor']));
    $vars['bgcolor'] = glfPrepareForDB(trim($_POST['bgcolor']));

    if ($vars['catname'] == '') {
        $display .= COM_startBlock($LANG_ADVT['error']);
        $display .= $LANG_ADVT['please_enter_cat']
               . "<br><a href=\"javascript:history.back()\">
                <b>$LANG_ADVT[back]</b></a>";
        $display .= COM_endBlock();
        return $display;
    }

    $time = time();

    // Handle the uploaded category image, if any.  We don't want to delete
    // the image if one isn't uploaded, we should leave it unchanged.  So we'll
    // first retrieve the existing image filename, if any.
    if ($catid > 0) {
        $img_filename = DB_getItem($_TABLES['ad_category'], 'image', "cat_id=$catid");
    } else {
        $img_filename = '';
    }
    if (is_uploaded_file($_FILES['imagefile']['tmp_name'])) {
        $img_filename = $time . "_" . rand(1,100) . "_" . $_FILES['imagefile']['name'];
        if (!@move_uploaded_file($_FILES['imagefile']['tmp_name'],
            CLASSIFIEDS_IMGPATH . '/cat/' . $img_filename)) {
            $retval .= CLASSIFIEDS_errorMsg("Error Moving Image", 'alert');
        }

        // If a new image was uploaded, and this is an existing category,
        // then delete the old image, if any.  The DB still has the old filename
        // at this point.
        if ($catid > 0) {
            catDelImage($catid);
        }
    }
    $vars['img_filename'] = DB_escapeString($img_filename);

    // if category id already set, then it's not a new one
    if ($catid > 0) {
        $sql = "
        UPDATE
            {$_TABLES['ad_category']} 
        SET
            cat_name = '{$vars['catname']}',
            papa_id = {$vars['parentcat']},
            keywords = '{$vars['keywords']}',
            image = '{$vars['img_filename']}',
            description = '{$vars['description']}',
            owner_id = {$vars['owner_id']},
            group_id = {$vars['group_id']},
            perm_owner = {$vars['perms'][0]},
            perm_group = {$vars['perms'][1]},
            perm_members = {$vars['perms'][2]},
            perm_anon = {$vars['perms'][3]},
            fgcolor = '{$vars['fgcolor']}',
            bgcolor = '{$vars['bgcolor']}'
        WHERE
            cat_id = $catid;";

        // Propagate the permissions, if requested
        if (isset($_POST['propagate'])) {
            CLASSIFIEDS_propagateCatPerms($catid, $vars['perms']);
        }

    } else {
        $sql = "
        INSERT INTO 
            {$_TABLES['ad_category']} 
            ( cat_id, papa_id, cat_name, add_date, keywords, image,
                description,
                owner_id, group_id, 
                perm_owner, perm_group, perm_members, perm_anon,
                fgcolor, bgcolor
            ) 
        VALUES (
            $catid, 
            {$vars['parentcat']}, 
            '{$vars['catname']}', 
            $time, 
            '{$vars['keywords']}',
            '{$vars['img_filename']}',
            '{$vars['description']}',
            {$vars['owner_id']},
            {$vars['group_id']},
            {$vars['perms'][0]},
            {$vars['perms'][1]},
            {$vars['perms'][2]},
            {$vars['perms'][3]},
            '{$vars['fgcolor']}',
            '{$vars['bgcolor']}'
        );";
    }

    $result = DB_query($sql);
    if (!$result) return CLASSIFIEDS_errorMsg($LANG_ADVT['database_error'], 'alert');

    return "";      // no actual return if this function works ok

}   // function catSave()


/**
 *  Deletes all checked categories.
 *  Calls catDelete() to perform the actual deletion
 *  @return string  Error message, if any
 */
function catDeleteMulti()
{
    $display = '';

    for ($i = 0; $i < count($_POST['c']); $i++ ) {
        // attempt to delete all the categories.  Break on error
        if (!catDelete((int)$_POST['c'][$i])) {
            $display .= "Error deleting category {$_POST['c'][$i]}<br />";
        }
    }

    return $display;

}


/**
 *  Delete a category, and all sub-categories, and all ads
 *  @param  integer $id     Category ID to delete
 *  @return boolean         True on success, False on failure
 */
function catDelete($id)
{
    global $_TABLES, $_CONF_ADVT;

    $id = (int)$id;
    // find all sub-categories of this one and delete them.
    $sql = "
        SELECT 
            cat_id 
        FROM 
            {$_TABLES['ad_category']} 
        WHERE 
            papa_id=$id
    ";
    $result = DB_query($sql);

    if ($result) {
        while ($row = DB_fetcharray($result)) {
            if (!catDelete($row['cat_id']))
                return false;
        }
    }

    // now delete any ads associated with this category
    $sql = "
        SELECT 
            ad_id 
         FROM 
            {$_TABLES['ad_ads']} 
         WHERE 
            cat_id=$id
    ";
    $result = DB_query($sql);

    if ($result) {
        while ($row = DB_fetcharray($result)) {
            if (adDelete($row['ad_id'], true) != 0) {
                return false;
            }
        }
    }

    // Delete this category
    // First, see if there's an image to delete
    $img_name = DB_getItem($_TABLES['ad_category'], 'image', "cat_id=$id");
    if ($img_name != '' && file_exists(CLASSIFIES_IMGPATH . '/cat/' . $img_name)) {
        unlink(CLASSIFIES_IMGPATH . '/cat/' . $img_name);
    }
    DB_delete($_TABLES['ad_category'], 'cat_id', $id);

    // If we made it this far, must have worked ok
    return true;
}


/**
*   Delete a single category's icon
*   Deletes the icon from the filesystem, and updates the category table
*   @param  integer $cat_id     Category IF of image to delete
*/
function catDelImage($cat_id = 0)
{
    global $_TABLES, $_CONF_ADVT;

    if ($cat_id == 0)
        return;

    $img_name = DB_getItem($_TABLES['ad_category'], 'image', "cat_id=$cat_id");

    if ($img_name == '')
        return;

    if (file_exists(CLASSIFIEDS_IMGPATH . '/cat/' . $img_name)) {
        unlink(CLASSIFIEDS_IMGPATH . '/cat/' . $img_name);
    }
    DB_query("UPDATE {$_TABLES['ad_category']}
            SET image=''
            WHERE cat_id=$cat_id");
}


/**
 *  Propagate a category's permissions to all sub-categories.
 *  Called by catSave() if the admin selects "Propagate Permissions".
 *  Recurses downward through the category table setting permissions of the
 *  category specified by $id.  The actual category identified by $id is not
 *  updated; that would be done in catSave().
 *  @param  integer $id     Category ID
 *  @param  array   $perms  Permissions to apply
 */
function CLASSIFIEDS_propagateCatPerms($id=0, $perms='')
{
    global $_TABLES;

    $id = (int)$id;
    if ($id < 1) return;
    if (!is_array($perms) || empty($perms)) return;

    // Locate the child categories of this one
    $sql = "
        SELECT
            cat_id
        FROM
            {$_TABLES['ad_category']}
        WHERE
            papa_id = $id 
    ";
    //echo $sql;die;
    $result = DB_query($sql);

    // If there are no children, just return.
    if (!$result)
        return '';

    while ($row = DB_fetchArray($result)) {
        // Update each located row
        $sql = "
            UPDATE
                {$_TABLES['ad_category']}
            SET
                perm_owner={$perms[0]},
                perm_group={$perms[1]},
                perm_members={$perms[2]},
                perm_anon={$perms[3]}
            WHERE
                cat_id={$row['cat_id']}
        ";
        DB_query($sql);

        // Now update the children of the current category
        CLASSIFIEDS_propagateCatPerms($row['cat_id'], $perms);
    }

}


?>
