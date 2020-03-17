<?php
/**
 * Common AJAX functions.
 *
 * @author      Lee Garner <lee@leegarner.com>
 * @copyright   Copyright (c) 2009-2020 Lee Garner <lee@leegarner.com>
 * @package     classifieds
 * @version     v0.3.1
 * @license     http://opensource.org/licenses/gpl-2.0.php
 *              GNU Public License v2 or later
 * @filesource
 */

/**
 *  Include required glFusion common functions
 */
require_once '../lib-common.php';

$result = array();
switch ($_REQUEST['action']) {
case 'catsub':
    if (!isset($_POST['id'])) exit;
    $C = new \Classifieds\Category($_POST['id']);
    $status = $C->Subscribe(true);
    $result = array(
        'cat_id' => $_POST['id'],
        'newstate' => $status ? 1 : 0,
        'statusMessage' => $status ? $LANG_ADVT['msg_catsub'] :
                $LANG_ADVT['msg_error'],
    );
    break;

case 'catunsub':
    if (!isset($_POST['id'])) exit;
    $C = new \Classifieds\Category($_POST['id']);
    $status = $C->Subscribe(false);
    $result = array(
        'cat_id' => $_POST['id'],
        'newstate' => $status ? 0 : 1,
        'statusMessage' => $status ? $LANG_ADVT['msg_catunsub'] :
                $LANG_ADVT['msg_error'],
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
        $U = new Classifieds\Upload($ad_id);
        $U->setNonce($nonce);
        $filenames = $U->uploadFiles();
        $processed = count($filenames);
        // Only one filename here, this to get the image id also
        foreach ($filenames as $img_id=>$filename) {
            $result['filenames'][] = array(
                'img_url'   => Classifieds\Image::dispUrl($filename),
                'thumb_url' => Classifieds\Image::thumbUrl($filename),
                'img_id' => $img_id,
            );
        }
        $msg = '<ul>';
        $msg .= '<li>' . sprintf($LANG_ADVT['x_of_y_uploaded'], $processed, $sent, $_CONF_ADVT['imagecount']) . '</li>';
        $msg .= '</ul>';
        $result['statusMessage'] = $msg;
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
