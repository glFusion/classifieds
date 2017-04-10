<?php
/**
*   @author     Mark R. Evans <mark@glfusion.org>
*   @copyright  Copyright (c) 2008 Mark R. Evans <mark@glfusion.org>
*   @copyright  Copyright (c) 2007-2008 Mystral-kk <geeklog@mystral-kk.net>
*   @package    classifieds
*   @version    0.3
*   @license    http://opensource.org/licenses/gpl-2.0.php
*               GNU Public License v2 or later
*   @filesource
*/

// this file can't be used on its own
if (!defined ('GVERSION')) {
    die ('This file can not be used on its own.');
}

/**
*   Implementation of class DataproxyDriver for the Classifieds plugin
*   @package classifieds
*/
class sitemap_classifieds extends sitemap_base
{
    protected $name = 'classifieds';

    /**
    *   Get the friendly display name
    *
    *   @return string      Friendly name
    */
    public function getDisplayName()
    {
        global $LANG_ADVT;
        return $LANG_ADVT['menuitem'];
    }


    /**
    * @param $pid int/string/boolean id of the parent category.  False means
    *        the top category (with no parent)
    * @return array(
    *   'id'        => $id (string),
    *   'pid'       => $pid (string: id of its parent)
    *   'title'     => $title (string),
    *   'uri'       => $uri (string),
    *   'date'      => $date (int: Unix timestamp),
    *   'image_uri' => $image_uri (string)
    *  )
    */
    function getChildCategories($pid = false)
    {
        global $_CONF, $_TABLES, $_CONF_ADVT;

        $entries = array();

        if ($pid === false) {
            $pid = 0;
        }

        $sql = "SELECT * FROM {$_TABLES['ad_category']}
                WHERE (papa_id = '" . DB_escapeString($pid) . "') ";
        if ($this->uid > 0) {
            $sql .= COM_getPermSQL('AND ', $this->uid);
        }
        $sql .= ' ORDER BY cat_id';
        $result = DB_query($sql, 1);
        if (DB_error()) {
            COM_errorLog("sitemap_classified::getChildCategories() error: $sql");
            return $entries;
        }

        while (($A = DB_fetchArray($result)) !== false) {
            $entries[] = array(
                'id'        => $A['cat_id'],
                'pid'       => $A['papa_id'],
                'title'     => $A['cat_name'],
                'uri'       => COM_buildUrl($_CONF['site_url']
                                . '/' . $_CONF_ADVT['pi_name']
                                . '/index.php?mode=home&amp;id='
                                . urlencode($A['cat_id'])),
                'date'      => 'false',
                'image_uri' => false,
            );
        }
        return $entries;
    }


    /**
    * Returns an array of (
    *   'id'        => $id (string),
    *   'title'     => $title (string),
    *   'uri'       => $uri (string),
    *   'date'      => $date (int: Unix timestamp),
    *   'image_uri' => $image_uri (string)
    * )
    */
    function getItems($category = false)
    {
        global $_CONF, $_TABLES, $_CONF_ADVT;

        $entries = array();
        if (!$category) $category = 0;
        $category = (int)$category;

        $sql = "SELECT * FROM {$_TABLES['ad_ads']} ";
        if ($category > 0) {
            $sql .= "WHERE (cat_id = $category) ";
        }
        $sql .= "ORDER BY ad_id";
        $result = DB_query($sql, 1);
        if (DB_error()) {
            COM_errorLog("sitemap_classifieds::getItems() error: $sql");
            return $entries;
        }
        while (($A = DB_fetchArray($result, false)) !== false) {
            $entries[] = array(
                'id'        => $A['ad_id'],
                'title'     => $A['subject'],
                'uri'       => COM_buildUrl($_CONF['site_url']
                                . '/' . $_CONF_ADVT['pi_name']
                                . '/index.php?mode=detail&amp;id='
                                . urlencode($A['ad_id'])),
                'date'      => $A['add_date'],
                'image_uri' => false,
            );
        }
        return $entries;
    }
}

?>
