<?php
/**
*   Provide forms ad update functions for editing and submitting ads
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
 *  Provide a form to edit a new or existing ad.
 *  @param  array   $A      Array of ad data for edit form
 *  @param  string  $mode   Edit mode
 *  @param  boolean $admin  True for administrator edit, false for normal
 *  @return string          HTML for ad edit form
 */
function adEdit($A, $mode = 'edit', $admin=false)
{
    global  $_TABLES, $LANG_ADVT, $_CONF, $_CONF_ADVT, $LANG_ADMIN,
            $_USER, $LANG_ACCESS, $_GROUPS, $LANG12, $LANG24, $MESSAGE,
            $LANG_postmodes;

    USES_classifieds_class_adtype();

    // Determine if this user is an admin.  Deprecates the $admin parameter.
    $admin = SEC_hasRights($_CONF_ADVT['pi_name'] . '.admin') ? 1 : 0;

    // only valid users allowed
    if (COM_isAnonUser() ||
        ($_CONF_ADVT['usercanedit'] == 0 && !$admin)) {
        return CLASSIFIEDS_errorMsg($LANG_ADVT['no_permission'], 'alert', 
                $LANG_ADVT['access_denied']);
    }

    // We know that we need to have categories, so make sure some exist
    // before even trying to display the form.  The category dropdown is
    // created later since it needs the existing cat_id, if any.
    if (DB_count($_TABLES['ad_category']) < 1) {
        return CLASSIFIEDS_errorMsg($LANG_ADVT['no_categories'], 'info');
    }

    $time = time();     // used to compare now with expiration date

    if ($admin) {
        $T = new Template(CLASSIFIEDS_PI_PATH . '/templates/admin');
        $T->set_file('adedit', "adminedit.thtml");
        $action_url = CLASSIFIEDS_ADMIN_URL . '/index.php';
    } else {
        $T = new Template(CLASSIFIEDS_PI_PATH . '/templates');
        $T->set_file('adedit', "submitform.thtml");
        $action_url = CLASSIFIEDS_URL . '/index.php';
    }

    // Set up the wysiwyg editor, if available
    switch (PLG_getEditorType()) {
    case 'ckeditor':
        $T->set_var('show_htmleditor', true);
        PLG_requestEditor('classifieds','classifieds_entry','ckeditor_classifieds.thtml');
        PLG_templateSetVars('classifieds_entry', $T);
        break;
    case 'tinymce' :
        $T->set_var('show_htmleditor',true);
        PLG_requestEditor('classifieds','classifieds_entry','tinymce_classifieds.thtml');
        PLG_templateSetVars('classifieds_entry', $T);
        break;
    default :
        // don't support others right now
        $T->set_var('show_htmleditor', false);
        break;
    }

    switch ($mode) {
        case 'editsubmission':
        case 'moderate':
            $savemode = 'savesubmission';
            $delete_img = 'delsubimg';
            $delete_ad = 'deletesubmission';
            $type = 'moderate';
            $saveoption = $LANG_ADMIN['moderate'];
            $cancel_url = $_CONF['site_admin_url'] . '/moderation.php';
            break;            
        case 'edit':
            $savemode = 'savesubmission';
            $delete_img = 'delsubimg';
            $delete_ad = 'deletesubmission';
            $saveoption = $LANG_ADMIN['save'];
            $type = 'submission';
            $cancel_url = $action_url;
            break;
        case 'update_ad':
        default:
            $savemode = 'update_ad';
            $delete_img = 'delete_img';
            $delete_ad = 'delete_ad';
            $saveoption = $LANG_ADMIN['save'];
            $type = '';
            $cancel_url = $action_url;
            break;
    }

    // Admins (only) use this form for submissions as well as edits,
    // so we need to expect an empty array.
    if (empty($A['ad_id'])) {
        if (!$admin) {
            return CLASSIFIEDS_errorMsg($LANG_ADVT['no_permission'], 'alert', 
                $LANG_ADVT['access_denied']);
        }
        $A['ad_id'] = COM_makeSid();
        $A['subject'] = '';
        $A['descript'] = '';
        $A['price'] = '';
        $A['url'] = '';
        $A['exp_date'] = '';
        $A['add_date'] = time();
        $A['ad_type'] = 0;
        $A['perm_owner'] = $_CONF_ADVT['default_permissions'][0];
        $A['perm_group'] = $_CONF_ADVT['default_permissions'][1];
        $A['perm_members'] = $_CONF_ADVT['default_permissions'][2];
        $A['perm_anon'] = $_CONF_ADVT['default_permissions'][3];
        $A['uid'] = $_USER['uid'];
        if (isset($_REQUEST['cat'])) {
            $A['cat_id'] = intval($_REQUEST['cat']);
        } else {
            $A['cat_id'] = 0;
        }

        $catsql  = "SELECT cat_id,perm_anon,keywords
                    FROM {$_TABLES['ad_category']} ";
        if ($A['cat_id'] > 0) {
            $catsql .= "WHERE cat_id = {$A['cat_id']} ";
        } else {
            $catsql .= "ORDER BY cat_name ASC ";
        }
        $catsql .= "LIMIT 1";
        $r = DB_query($catsql, 1);
        if ($r && DB_numRows($r) > 0) {
            $row = DB_fetchArray($r, false);
            $A['cat_id'] = $row['cat_id'];
            $A['keywords'] = trim($row['keywords']);
        } else {
            $A['cat_id'] = 0;
            $A['keywords'] = '';
        }

        $A['owner_id'] = $_USER['uid'];     // Set ad owner to current user for new ads
        $A['group_id'] = isset($_GROUPS['classifieds Admin']) ?
            $_GROUPS['classifieds Admin'] :
            SEC_getFeatureGroup('classifieds.edit');
        $A['exp_sent'] = 0;

        // set expiration & duration info for a new ad
        $T->set_var('expiration_date', $LANG_ADVT['runfor']);  // "run for: X days"

        $comments_enabled = $_CONF_ADVT['commentsupport'] == 1 ? 0 : 1;
        $T->set_var("sel_{$comments_enabled}", 'selected');

        if ($_CONF_ADVT['purchase_enabled']) {
            USES_classifieds_class_userinfo();
            $User = new adUserInfo();
            $T->set_var('days', 
                min($_CONF_ADVT['default_duration'], $User->getMaxDays()));
        } else {
            $T->set_var('days', $_CONF_ADVT['default_duration']);
        }

        $photocount = 0;    // No photos yet with a new ad
        
    } else {
        // This is an existing ad with values already in $A

        $T->set_var('expiration_date', $LANG_ADVT['expiration']);
        $T->set_var('days', '0');

        // Disable the perm_anon checkbox if it's disabled by the category.
        if (!$admin && DB_getItem($_TABLES['ad_category'], 
                    'perm_anon', "cat_id='{$A['cat_id']}'") == '0') { 
            $T->set_var('vis_disabled', 'disabled');
        }

        // get the photo information
        $sql = "SELECT photo_id, filename 
                FROM {$_TABLES['ad_photo']} 
                WHERE ad_id='{$A['ad_id']}'";
        $photo = DB_query($sql, 1);

        // save the count of photos for later use
        if ($photo)
            $photocount = DB_numRows($photo); 
        else
            $photocount = 0;

        $comments_enabled = (int)$A['comments_enabled'];
        $T->set_var("sel_{$comments_enabled}", 'selected');

    }

    // Get the max image size in MB and set the message
    $img_max = $_CONF['max_image_size']  / 1048576;    // Show in MB

    // Sanitize entries from the database
    $A['subject'] = htmlspecialchars($A['subject']); 
    $A['descript'] = htmlspecialchars($A['descript']);
    $A['keywords'] = htmlspecialchars($A['keywords']);
    $A['price'] = htmlspecialchars($A['price']);
    $A['url'] = htmlspecialchars($A['url']);
    $A['ad_type'] = (int)$A['ad_type'];

    // set expiration & duration based on existing info
    if ($A['exp_date'] == '') {
        $T->set_var('row_exp_date', '');
    } else if ($A['exp_date'] < $time) {
        $T->set_var('already_expired', $LANG_ADVT['already_expired']);
    } else {
        $T->set_var('row_exp_date', date("d M Y", $A['exp_date'])); 
    }

    $T->set_var(array(
        'post_options'      => $post_options,
        'change_editormode' => 'onchange="change_editmode(this);"',
        'glfusionStyleBasePath' => $_CONF['site_url']. '/fckeditor',
        'gltoken_name'      => CSRF_TOKEN,
        'gltoken'           => SEC_createToken(),
        'has_delbtn'        => 'true',
        'txt_photo'         => "{$LANG_ADVT['photo']}<br />" .
                    sprintf($LANG_ADVT['image_max'], $img_max),
        'type'              => $type,
        'action_url'        => $action_url,
        'max_file_size'     => $_CONF['max_image_size'],
        'row_cat_id'        => $A['cat_id'],
        'row_ad_id'         => $A['ad_id'],
        'row_subject'       => $A['subject'],
        'row_descript'      => $A['descript'],
        'row_price'         => $A['price'],
        'row_url'           => $A['url'],
        'keywords'          => $A['keywords'],
        'exp_date'          => $A['exp_date'],
        'add_date'          => $A['add_date'],
        'ad_type_selection' => AdType::makeSelection($A['ad_type']),
        'sel_list_catid'    => CLASSIFIEDS_buildCatSelection($A['cat_id']),
        'saveoption'        => $saveoption,
        'cancel_url'        => $cancel_url,
    ) );

    // set expiration & duration based on existing info
    if ($A['exp_date'] == '') {
        $T->set_var('row_exp_date', '');
    } else if ($A['exp_date'] < $time) {
        $T->set_var('already_expired', $LANG_ADVT['already_expired']);
    } else {
        $T->set_var('row_exp_date', date("d M Y", $A['exp_date'])); 
    }

    // Set up permission editor on the admin template if needed.
    // Otherwise, set hidden values with existing permissions
    if ($admin) {
        // Set up owner selection
        $T->set_var(array(
            'ownerselect'   => CLASSIFIEDS_userDropdown($A['owner_id']),
            'permissions_editor' => SEC_getPermissionsHTML(
                            $A['perm_owner'],$A['perm_group'],
                            $A['perm_members'],$A['perm_anon']),
            'group_dropdown'    => SEC_getGroupDropdown($A['group_id'], 3),
        ) );
    } else {
        $ownername = COM_getDisplayName($A['owner_id']);
        $T->set_var(array(
            'owner_id'      => $A['owner_id'],
            'ownername'     => $ownername,
            'perm_owner'    => $A['perm_owner'],
            'perm_group'    => $A['perm_group'],
            'perm_members'  => $A['perm_members'],
            'perm_anon'     => $A['perm_anon'],
            'group_id'      => $A['group_id'],
        ) );
        if ($A['perm_anon'] == 2)
            $T->set_var('perm_anon_chk', 'checked');
    }

    // Set up the photo fields.  Use $photocount defined above.  
    // If there are photos, read the $photo result.  Otherwise, 
    // or if this is a new ad, just clear the photo area
    $T->set_block('adedit', 'PhotoRow', 'PRow');
    $i = 0;
    if ($photocount > 0) {

        while ($prow = DB_fetchArray($photo, false)) {
            $i++;
            $T->set_var(array(
               'img_url'   => LGLIB_ImageUrl(CLASSIFIEDS_IMGPATH . '/' . $prow['filename'],
                            $_CONF_ADVT['img_max_width'], $_CONF_ADVT['img_max_height']),
                'thumb_url' => LGLIB_ImageUrl(CLASSIFIEDS_IMGPATH . '/' . $prow['filename'],
                            $_CONF_ADVT['thumb_max_size'], $_CONF_ADVT['thumb_max_size']),
                'seq_no'    => $i,
                'ad_id'     => $A['ad_id'],
                'del_img_url'   => $action_url . 
                    "?mode=$delete_img&mid={$prow['photo_id']}" .
                    "&id={$A['ad_id']}",
            ) );
            $T->parse('PRow', 'PhotoRow', true);
        }
    } else {
        $T->parse('PRow', '');
    }

    // add upload fields for unused images
    $T->set_block('adedit', 'UploadFld', 'UFLD');
    for ($j = $i; $j < $_CONF_ADVT['imagecount']; $j++) {
        $T->parse('UFLD', 'UploadFld', true);
    }

    $T->parse('output','adedit');
    return $T->finish($T->get_var('output'));

}   // adEdit()


/**
*   Delete a single image from an ad
*   @param  boolean $admin      True if this is an admin, false if not
*   @param  string  $adTable    Table ID, either production or submission
*   @return string              Error message, if any
*/
function imgDelete($admin=false, $adTable = 'ad_ads')
{
    global $_TABLES, $_USER, $_CONF_ADVT;

    $mid = isset($_REQUEST['mid']) ? intval($_REQUEST['mid']) : 0;
    if (!$mid)
        return "No image selected";

    if ($adTable != 'ad_ads' && $adTable != 'ad_submission') {
        return "Invalid ad table specified";
    }

    // find the ad corresponding to this image.  Don't trust the input.
    $sql = "SELECT ad_id, filename
            FROM {$_TABLES['ad_photo']} 
            WHERE photo_id=$mid";
    $result = DB_query($sql, 1);
    if (!$result || DB_numrows($result) < 1)
        return '';
    $row = DB_fetchArray($result, false);

    // now get the ad id.  We're really just checking that the current
    // user is the ad's owner.
    $sql = "
            SELECT 
            ad_id 
            FROM 
            {$_TABLES[$adTable]} 
        WHERE 
            ad_id={$row['ad_id']}
        ";

    // Set up base url for links here while we're checking admin status
    if (!$admin) {
        $base_url = $_CONF['site_url'] . '/' . 
                $_CONF_ADVT['pi_name'] . '/index.php';
        $sql .= "AND uid='{$_USER['uid']}'";
    } else {
        $base_url = $_CONF['site_admin_url'] . '/plugins';
    }

    $result = DB_query($sql, 1);
    if (DB_numrows($result) < 1) {
        return "Unauthorized Access";
    }

    // Otherwise, this is the right owner for this ad, so delete the image
    // and thumbnail from the filesystem and database
    if (file_exists(CLASSIFIEDS_IMGPATH . '/' . $row['filename']))
            unlink(CLASSIFIEDS_IMGPATH . '/' . $row['filename']);

    DB_delete($_TABLES['ad_photo'], 'photo_id', $mid);

    // No text returned unless there was an error
    return '';

}   // imgDelete();


/**
 *  Returns the <option></option> portion to be used
 *  within a <select></select> block to choose users from a dropdown list
 *  @param  string  $sel    ID of selected value
 *  @return string          HTML output containing options
 */
function CLASSIFIEDS_userDropdown($selId = '')
{
    global $_TABLES;

    $retval = '';

    // Find users, excluding anonymous
    $sql = "SELECT uid FROM {$_TABLES['users']}
            WHERE uid > 1";
    $result = DB_query($sql, 1);
    while ($row = DB_fetchArray($result, false)) {
        $name = COM_getDisplayName($row['uid']);
        $sel = $row['uid'] == $selId ? 'selected' : '';
        $retval .= "<option value=\"{$row['uid']}\" $sel>$name</option>\n";
    }

    return $retval;

}   // function CLASSIFIEDS_userDropdown()


?>
