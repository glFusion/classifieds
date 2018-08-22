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

switch ($_POST['action']) {
case 'toggleEnabled':
    $oldval = $_POST['oldval'] == 1 ? 1 : 0;

    switch ($_POST['type']) {
    case 'adtype':
        $newval = \Classifieds\AdType::toggleEnabled($oldval, $_POST['id']);
        break;

     default:
        exit;
    }

    $result = array(
        'id' => $_POST['id'],
        'newval' => $newval,
        'statusMessage' => $newval != $oldval ? $LANG_ADVT['msg_item_updated']
                : $LANG_ADVT['msg_item_nochange'],
    );
    break;
}

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-cache, must-revalidate');
// A date in the past to force no caching
header('Expires: Mon, 26 Jul 1997 05:00:00 GMT');
echo json_encode($result);

?>
