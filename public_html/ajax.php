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
    $maxdays = $Ad->addDays($_POST['days']);
    $expdate = $Ad->getExpDate()->format($_CONF['shortdate'], true);
    $result = array(
        'maxdays' => $maxdays,
        'expdate' => $expdate,
        'statusMessage' => sprintf($LANG_ADVT['msg_added_days'], $_POST['days']),
    );
    break;
}

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-cache, must-revalidate');
// A date in the past to force no caching
header('Expires: Mon, 26 Jul 1997 05:00:00 GMT');
echo json_encode($result);

?>
