<?php
/**
 * Common AJAX functions.
 *
 * @author      Lee Garner <lee@leegarner.com>
 * @copyright   Copyright (c) 2009-2021 Lee Garner <lee@leegarner.com>
 * @package     classifieds
 * @version     v1.4.0
 * @license     http://opensource.org/licenses/gpl-2.0.php
 *              GNU Public License v2 or later
 * @filesource
 */

/**
 *  Include required glFusion common functions
 */
require_once '../lib-common.php';

if (!isset($_REQUEST['action'])) {
    exit;
}

$result = array();
switch ($_REQUEST['action']) {
case 'catsub':
    // Subscribe or unsubscribe from a category
    if (!isset($_POST['cat_id'])) exit;
    $is_sub = $_POST['is_subscribed'] ? 1 : 0;
    $do_sub = $is_sub == 0 ? 1 : 0;
    $status = Classifieds\Category::getInstance($_POST['cat_id'])->Subscribe($do_sub);
    if ($status) {
        $sub_stat = $do_sub;
        $msg = $do_sub ? $LANG_ADVT['msg_catsub'] : $LANG_ADVT['msg_catunsub'];
    } else {
        $sub_stat = $is_sub;    // return same value
        $msg = $LANG_ADVT['msg_error'];
    }
    $result = array(
        'cat_id' => $_POST['cat_id'],
        'subscribed' => $sub_stat,
        'statusMessage' => $msg,
        'title' => $LANG_ADVT['catsub_title_' . $sub_stat],
        'innerHtml' => Classifieds\Category::getInstance($_POST['cat_id'])->getSubIcon($sub_stat),
    );
    break;

case 'moredays':
    if (!isset($_POST['id'])) exit;
    $Ad = new Classifieds\Ad($_POST['id']);
    if ($Ad->isNew()) exit;
    $old_max = $Ad->calcMaxAddDays();
    $new_max = $Ad->addDays($_POST['days']);
    $added = $old_max - $new_max;
    $expdate = $Ad->getExpDate()->format($_CONF['shortdate'], true);
    $result = array(
        'maxdays' => $new_max,
        'expdate' => $expdate,
        'statusMessage' => sprintf($LANG_ADVT['msg_added_days'], $added),
    );
    break;

case 'dropupload':
    // Handle a drag-and-drop image upload
    $ad_id = LGLIB_getVar($_POST, 'ad_id', 'string');
    $nonce = LGLIB_getVar($_POST, 'nonce', 'string');
    $result = array(
        'status'    => true,    // assume OK
        'statusMessage' => '',
        'filenames' => array(),
    );

    // Handle image uploads.  This is done last because we need
    // the product id to name the images filenames.
    if (!empty($_FILES['files'])) {
        $sent = count($_FILES['files']['name']);
        $filenames = array();
        $sid = COM_makesid();
        for ($i = 0; $i < $sent; $i++) {
            $filenames[] = $sid . '_' . rand(0, 9999);
        }
        $U = new Classifieds\Image;
        $U->withAdID($ad_id)
          ->setNonce($nonce)
          ->setPath($_CONF_ADVT['imgpath'] . '/user/')
          ->setFieldName('files')
          ->setMaxFileUploads($_CONF_ADVT['imagecount'])
          ->setFileNames($filenames);
        if (!empty($ad_id)) {
            // Editing an ad, set the number of files already uploaded.
            $images = Classifieds\Image::getAll($ad_id);
            $U->setCurrentFileUploads(count($images));
        }
        $status = $U->uploadFiles();
        if ($status) {
            // Get the filenames and image IDs to populate the form.
            $filenames = $U->getUploadedFiles();
            $processed = count($filenames);
            foreach ($filenames as $img_id=>$filename) {
                $result['filenames'][] = array(
                    'img_url'   => Classifieds\Image::dispUrl($filename),
                    'thumb_url' => Classifieds\Image::thumbUrl($filename),
                    'img_id' => $img_id,
                );
            }
            $statusMsg = sprintf(
                $LANG_ADVT['x_of_y_uploaded'],
                $processed,
                $sent,
                $_CONF_ADVT['imagecount']
            );
        } else {
            $statusMsg = implode('</li><li>', $U->getErrors());
        }
        $result['statusMessage'] = '<ul><li>' . $statusMsg . '</li></ul>';
    } else {
        $result['status'] = false;
        $result['statusMessage'] = $LANG_ADVT['no_files_uploaded'];
    }
    break;

case 'delImage':
    $img_id = LGLIB_getVar($_POST, 'img_id', 'integer');
    $Image = new Classifieds\Image($img_id);
    $result = array(
        //'status' => true,     // testing javascript
        'status' => $Image->Delete(),
    );
    break;
}

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-cache, must-revalidate');
// A date in the past to force no caching
header('Expires: Mon, 26 Jul 1997 05:00:00 GMT');
echo json_encode($result);

?>
