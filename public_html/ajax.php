<?php
/**
*   Common AJAX functions
*   @author     Lee Garner <lee@leegarner.com>
*   @copyright  Copyright (c) 2009 Lee Garner <lee@leegarner.com>
*   @package    classifieds
*   @version    0.3.1
*   @license    http://opensource.org/licenses/gpl-2.0.php 
*               GNU Public License v2 or later
*   @filesource
*/

/**
 *  Include required glFusion common functions
 */
require_once '../lib-common.php';

switch ($_GET['action']) {
case 'catsub':
    if (!isset($_GET['id'])) exit;
    USES_classifieds_class_category();
    $C = new adCategory($_GET['id']);
    $result = array(
        'cat_id' => $_GET['id'],
        'newstate' => $C->Subscribe(true) ? 1 : 0,
    );
    break;

case 'catunsub':
    if (!isset($_GET['id'])) exit;
    USES_classifieds_class_category();
    $C = new adCategory($_GET['id']);
    $result = array(
        'cat_id' => $_GET['id'],
        'newstate' => $C->Subscribe(false) ? 0 : 1,
    );
    break;

case 'moredays':
    if (!isset($_GET['id'])) exit;
    USES_classifieds_class_ad();
    $Ad = new Ad($_GET['id']);
    if ($Ad->isNew) exit;
    $maxdays = $Ad->addDays($_GET['days']);
    $dt = new Date($Ad->exp_date, $_CONF['timezone']);
    $expdate = $dt->format($_CONF['shortdate'], true);
    $result = array(
        'maxdays' => $maxdays,
        'expdate' => $expdate,
    );
    break;
}

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-cache, must-revalidate');
// A date in the past to force no caching
header('Expires: Mon, 26 Jul 1997 05:00:00 GMT');
echo json_encode($result);

?>
