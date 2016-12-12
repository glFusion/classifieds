<?php
/**
*   General plugin-specific functions for the Classifieds plugin.
*
*   @author     Lee Garner <lee@leegarner.com>
*   @copyright  Copyright (c) 2009 Lee Garner <lee@leegarner.com>
*   @package    classifieds
*   @version    1.0.2
*   @license    http://opensource.org/licenses/gpl-2.0.php
*               GNU Public License v2 or later
*   @filesource
*/


/**
*  Calls itself recursively to find the root category of the requested id
*
*  @param   integer $id  Category ID
*  @return  integer      Root Category ID
*   @deprecated
*/
function XfindCatRoot($id)
{
    global $_TABLES;

    // Get the papa_id of the current id
    $result = DB_query("
        SELECT
            cat_id, papa_id
        FROM
            {$_TABLES['ad_category']}
        WHERE
            cat_id=$id
    ");
    if (!$result) return CLASSIFIEDS_errorMsg($LANG_ADVT['database_error'], 'alert');

    $row = DB_fetchArray( $result );

    if (DB_numRows($result) != 0)
        findCatRoot($row['papa_id']);

    return $row['cat_id'];

}


function XcurrentLocation($cat_id)
{
    global $_TABLES, $LANG_ADVT;

    $location = '';
    $cat_id = (int)$cat_id;

    $result = DB_query("
        SELECT
            cat_name, cat_id, papa_id
        FROM
            {$_TABLES['ad_category']}
        WHERE
            cat_id=$cat_id
    ");
    if (!$result)
        return CLASSIFIEDS_errorMsg($LANG_ADVT['database_error'], 'alert');

    $row = DB_fetchArray( $result );

    if ($row['papa_id'] == 0)
    {
        $location .=
            '<a href="'. CLASSIFIEDS_makeURL(''). '">'.
                $LANG_ADVT['home']. '</a> :: '.
            '<a href="'. CLASSIFIEDS_makeURL('home', $row['cat_id']). '">' .
            "{$row['cat_name']}</a>";
    }
    else
    {
        $location .= currentLocation($row['papa_id']);
        $location .=
            ' &gt; <a href="'.
            CLASSIFIEDS_makeURL('home', $row['cat_id']). '">' .
            "{$row['cat_name']}</a>";
    }
    return "      <b>$location</b>\n";
}


/**
*   Returns an error message formatted for on-screen display.
*   @param  string  $msg    Error message text
*   @param  string  $type   Error type or severity
*   @param  string  $hdr    Optional text to appear in the header.
*   @return string          HTML code for the formatted message
*/
function CLASSIFIEDS_errorMsg($messages, $type='info', $hdr='Error')
{
    global $_CONF_ADVT;

    // Convert single message to array
    if (!is_array($messages)) {
        $messages = array($messages);
    }
    foreach ($messages as $msg) {
        $msg_txt .= "<li>$msg</li>" . LB;
    }

    if ($_CONF_ADVT['_is_uikit']) {
        $element = 'div';
        switch ($type) {
        case 'alert':
        default:
            $class .= 'uk-alert uk-alert-danger';
            break;
        case 'info':
            $class .= 'uk-alert';
            break;
        }
    } else {
        $element = 'span';
        switch ($type) {
        case 'info':
        case 'alert':
            $class = $type;
            break;
        default:
            $class = 'alert';
            break;
        }
    }
    return "<$element class=\"$class\">$msg_txt</$element>\n";
}


function XdisplayCat($cat_id)
{
    global $_TABLES, $_CONF, $_CONF_ADVT;

    $pi_base_url = $_CONF['site_url'] . '/' . $_CONF_ADVT['pi_name'];
    $cat_id = intval($cat_id);

    //display small cat root
    $sql = "
        SELECT
            cat_name, cat_id, papa_id
        FROM
            {$_TABLES['ad_category']}
        WHERE
            cat_id=$cat_id
    ";
    $result = DB_query($sql);
    if (!$result) return CLASSIFIEDS_errorMsg($LANG_ADVT['database_error'], 'alert');
    $row = DB_fetchArray($result);

    if ($row['papa_id'] == 0) {
        $location =
            '<a href="' .
            CLASSIFIEDS_makeURL('home', $row['cat_id']). '">' .
            "{$row['cat_name']}</a>";
    } else {
        $location = displayCat($row['papa_id']) .
            ' &gt; <a href="' .
            CLASSIFIEDS_makeURL('home', $row['cat_id']). '">' .
            "{$row['cat_name']}</a>";
//        displayCat($row['papa_id']);
    }

    return "<span class=\"adDisplayCat\">$location</span>\n";

}   // function displayCat()


/**
*   Gets the correct template depending on what type of display
*   is being used.  Currently supports the new "blocks" display and the
*   old zClassifieds-style display
*
*   @param  string  $str    Template base name
*   @return string          Template full name
*/
function CLASSIFIEDS_getTemplate($str)
{
    global $_CONF_ADVT;

    if ($str == '') return '';

    switch ($_CONF_ADVT['catlist_dispmode']) {
    case 'blocks':
        $tpl = $str . '_blocks';
        break;

    default:
        $tpl = $str;
        break;
    }

    return $tpl . '.thtml';
}

?>
