<?php
/**
*   Common administrative AJAX functions
*   @author     Lee Garner <lee@leegarner.com>
*   @copyright  Copyright (c) 2009 Lee Garner <lee@leegarner.com>
*   @package    classifieds
*   @version    0.3.1
*   @license    http://opensource.org/licenses/gpl-2.0.php 
*               GNU Public License v2 or later
*   @filesource
*/

/**
*   Include required glFusion common functions
*/
require_once '../../../lib-common.php';

// This is for administrators only
if (!SEC_hasRights('classifieds.admin')) {
    exit;
}

switch ($_GET['action']) {
case 'toggleEnabled':
    $newval = $_REQUEST['newval'] == 1 ? 1 : 0;

    switch ($_GET['type']) {
    case 'adtype':
        USES_classifieds_class_adtype();
        $newval = AdType::toggleEnabled($newval, $_GET['id']);
        break;

     default:
        exit;
    }

    $result = array(
        'id' => $_GET['id'],
        'newstate' => $newval,
    );
    break;
}

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-cache, must-revalidate');
// A date in the past to force no caching
header('Expires: Mon, 26 Jul 1997 05:00:00 GMT');
echo json_encode($result);

?>
