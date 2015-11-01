<?php
/**
*   Provide the submission form for creating classified ads.
*   
*   @author     Lee Garner <lee@leegarner.com>
*   @copyright  Copyright (c) 2009 Lee Garner <lee@leegarner.com>
*   @package    classifieds
*   @version    1.0.0
*   @license    http://opensource.org/licenses/gpl-2.0.php 
*               GNU Public License v2 or later
*   @filesource
*/

USES_classifieds_advt_functions();


/**
 *  Provide a form to edit a new or existing ad.
 *
 *  @param  string  $mode   Indication of where this is called from
 *  @param  array   $A      Array of ad data.
 *  @return string          HTML for submission form
 */
function CLASSIFIEDS_submitForm($mode = 'submit', $A)
{
    global  $_TABLES, $LANG_ADVT, $_CONF, $_CONF_ADVT, 
            $_USER, $LANG_ACCESS, $_GROUPS, $LANG12, $LANG24, $LANG_ADMIN,
            $LANG_postmodes;

    USES_classifieds_class_adtype();

    // only valid users allowed
    if (!CLASSIFIEDS_canSubmit()) {
        return CLASSIFIEDS_errorMsg($LANG_ADVT['login_required'], 'alert', 
                $LANG_ADVT['access_denied']);
    }

    $time = time();     // used to compare now with expiration date

    $T = new Template(CLASSIFIEDS_PI_PATH . '/templates');
    $T->set_file('adedit', "submit.thtml");

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

    /*if (isset($_CONF['advanced_editor']) && $_CONF['advanced_editor'] == 1) {
        $editor_type = '_advanced';
        $postmode_adv = 'selected="selected"';
        $postmode_html = '';
    } else {
        $editor_type = '';
        $postmode_adv = '';
        $postmode_html = 'selected="selected"';
    }
    $post_options = '';

    $T->set_file('adedit', "submit{$editor_type}.thtml");
    if ($editor_type == '_advanced') {
        $T->set_var('show_adveditor','');
        $T->set_var('show_htmleditor','none');
    } else {
        $T->set_var('show_adveditor','none');
        $T->set_var('show_htmleditor','');
    }
    $T->set_var('glfusionStyleBasePath', $_CONF['site_url']. '/fckeditor');
    $post_options .= "<option value=\"html\" $postmode_html>{$LANG_postmodes['html']}</option>";
    $post_options .= "<option value=\"adveditor\" $postmode_adv>{$LANG24[86]}</option>";
    $T->set_var('post_options',$post_options);
    $T->set_var('lang_postmode', $LANG24[4]);
    $T->set_var('change_editormode', 'onchange="change_editmode(this);"');

    // Set the cookie for the advanced editor
    $T->set_var('gltoken_name', CSRF_TOKEN);
    $T->set_var('gltoken', SEC_createToken());
    @setcookie ($_CONF['cookie_name'].'fckeditor', 
                SEC_createTokenGeneral('advancededitor'),
                time() + 1200, $_CONF['cookie_path'],
                $_CONF['cookiedomain'], 
                $_CONF['cookiesecure']);
*/

    // Get the category info from the form variable, if any.  If not,
    // get the first category so we can get the keywords.
    // If no categories found, return an error.
    if (isset($A['catid'])) {
        $cat_id = intval($A['catid']);
    } elseif (isset($_REQUEST['cat'])) {
        $cat_id = intval($_REQUEST['cat']);
    } else {
        $cat_id = 0;
    }

    // Check permission to the desired category.  If not valid, just
    // reset to zero
    if ($cat_id > 0 && CLASSIFIEDS_checkCatAccess($cat_id) < 3) {
        $cat_id = 0;
    }
    $catsql = "SELECT cat_id, perm_anon, keywords
               FROM {$_TABLES['ad_category']}
                WHERE 1=1 ";
    if ($cat_id > 0)
        $catsql .= " AND cat_id=$cat_id ";
    $catsql .=  COM_getPermSQL('AND', 0, 3) .
            " ORDER BY cat_name ASC
                 LIMIT 1";
    //echo $catsql;die;
    $r = DB_query($catsql);
    if (!$r || DB_numRows($r) == 0) {
        // No categories found, need to get some entered
        return CLASSIFIEDS_errorMsg($LANG_ADVT['no_categories'], 'info');
    }
    $catrow = DB_fetchArray($r);

    // Set the category to the first found, if none specified
    if ($cat_id == 0)
        $cat_id = intval($catrow['cat_id']);

    // Get the keywords for the category IF there weren't any 
    // already submitted
    if (empty($A['keywords']))
        $A['keywords'] = trim($catrow['keywords']);

    $T->set_var('site_url',$_CONF['site_url']);

    // Get the max image size in MB and set the message
    $img_max = $_CONF['max_image_size'] / 1024 / 1024;
    $T->set_var('txt_photo', "{$LANG_ADVT['photo']}<br />".
            sprintf($LANG_ADVT['image_max'], $img_max));

    $base_url = "{$_CONF['site_url']}/{$_CONF_ADVT['pi_name']}/index.php";
    $delete_img_url = $base_url. "?mode=delete_img";
    if (!empty($A['ad_id'])) {
        $delete_img_url .= '&id='. $A['ad_id'];
        $T->set_var('delete_btn', '<form action="'. $base_url . 
                '?mode='.$LANG_ADMIN['delete'] .
                '&id='. $A['ad_id']. 
                '" method="post">
                <input type="submit" name="mode" value="'.
                $LANG_ADMIN['delete']. '"/></form>');
    }

    // Set some of the form variables if they're already set.
    $T->set_var('row_price', $A['price']); 
    $T->set_var('row_subject', $A['subject']);
    $T->set_var('row_descript', $A['descript']);
    $T->set_var('row_url', $A['url']);

    $T->set_var('ad_visibility', $LANG_ADVT['ad_visibility']);
    $T->set_var('max_file_size', $_CONF['max_image_size']);

    // Disable the "allow anon access" if the category disables it,
    // and override the checkbox
    if (intval($catrow['perm_anon']) > 0) {
        $T->set_var('vis_disabled', '');
        if ($A['perm_anon'] == 2) {
            $T->set_var('perm_anon_chk', 'checked');
        } else {
            $T->set_var('perm_anon_chk', '');
        }
    } else {
        $T->set_var('vis_disabled', 'disabled');
        $T->set_var('perm_anon_chk', '');
    }

    $T->set_var('action_url', $_CONF['site_url']. '/submit.php');
    //$T->set_var('mode', $mode);
    $T->set_var('type', $_CONF_ADVT['pi_name']);
    $T->set_var('cancel_url', CLASSIFIEDS_URL);

    // set expiration & duration info for a new ad
    if ($_CONF_ADVT['purchase_enabled']) {
        USES_classifieds_class_userinfo();
        $User = new adUserInfo();
        $T->set_var('days', 
            min($_CONF_ADVT['default_duration'], $User->getMaxDays()));
    } else {
        $T->set_var('days', $_CONF_ADVT['default_duration']);
    }

    $T->set_var('keywords', $A['keywords']);
        $T->set_var('ad_type_selection', 
        AdType::makeSelection($A['ad_type']));
    
    // default to a "for sale" ad
    /*if (empty($A['ad_type']) || $A['ad_type'] == 1) {
        $T->set_var('chk_sale', 'checked');
        $T->set_var('chk_wanted', '');
    } else {
        $T->set_var('chk_sale', '');
        $T->set_var('chk_wanted', 'checked');
    }*/

    // Set up the category dropdown
    $T->set_var('sel_list_catid', CLASSIFIEDS_buildCatSelection($cat_id));

    // add upload fields for images
    $T->set_block('adedit', 'UploadFld', 'UFLD');
    for ($i = 0; $i < $_CONF_ADVT['imagecount']; $i++) {
        $T->parse('UFLD', 'UploadFld', true);
    }

    // Set the new_ad flag to trigger the use of "mode" in the form.
    $T->set_var('new_ad', 'true');
    $T->parse('output','adedit');
    return $T->finish($T->get_var('output'));

}   // CLASSIFIEDS_submitForm()


?>
