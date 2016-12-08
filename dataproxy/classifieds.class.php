<?php
// +--------------------------------------------------------------------------+
// | Data Proxy Plugin for glFusion                                           |
// +--------------------------------------------------------------------------+
// | classifieds.class.php                                                    |
// |                                                                          |
// | Classifieds Plugin interface                                             |
// +--------------------------------------------------------------------------+
// | Copyright (C) 2008 by the following authors:                             |
// |                                                                          |
// | Mark R. Evans          mark AT glfusion DOT org                          |
// |                                                                          |
// | Based on the Data Proxy Plugin for Geeklog CMS                           |
// | Copyright (C) 2007-2008 by the following authors:                        |
// |                                                                          |
// | Authors: mystral-kk        - geeklog AT mystral-kk DOT net               |
// +--------------------------------------------------------------------------+
// |                                                                          |
// | This program is free software; you can redistribute it and/or            |
// | modify it under the terms of the GNU General Public License              |
// | as published by the Free Software Foundation; either version 2           |
// | of the License, or (at your option) any later version.                   |
// |                                                                          |
// | This program is distributed in the hope that it will be useful,          |
// | but WITHOUT ANY WARRANTY; without even the implied warranty of           |
// | MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the            |
// | GNU General Public License for more details.                             |
// |                                                                          |
// | You should have received a copy of the GNU General Public License        |
// | along with this program; if not, write to the Free Software Foundation,  |
// | Inc., 59 Temple Place - Suite 330, Boston, MA  02111-1307, USA.          |
// |                                                                          |
// +--------------------------------------------------------------------------+
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
class Dataproxy_classifieds extends DataproxyDriver
{
    var $driver_name = 'classifieds';

    function isLoginRequired() {
        global $_CONF, $_TABLES, $_CONF_ADVT;

        return $_CONF_ADVT['loginrequired'];
    }

    /*
    * Returns the location of index.php of each plugin
    */
    function getEntryPoint()
    {
        global $_CONF, $_CONF_ADVT;

        return $_CONF['site_url'] . '/' . $_CONF_ADVT['pi_name']. '/index.php';
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
    function getChildCategories($pid = false, $alllangs = false)
    {
        global $_CONF, $_TABLES, $_CONF_ADVT;
;

        $entries = array();

        if (($this->uid == 1) AND ($this->isLoginRequired() === true)) {
            return $entries;
        }

        if ($pid === false) {
            $pid = 0;
        }

        $sql = "SELECT * 
                FROM {$_TABLES['ad_category']} 
                WHERE (papa_id = '" . DB_escapeString($pid) . "') ";

        if ($this->uid > 0) {
            $sql .= COM_getPermSQL('AND ', $this->uid);

        }
        $sql .= " ORDER BY cat_id";
        $result = DB_query($sql);
        if (DB_error()) {
            return $entries;
        }

        while (($A = DB_fetchArray($result)) !== false) {
            $entry = array();

            $entry['id']        = $A['cat_id'];
            $entry['pid']       = $A['papa_id'];
            $entry['title']     = stripslashes($A['cat_name']);
            $entry['uri']       = COM_buildUrl($_CONF['site_url'] 
                                . '/' . $_CONF_ADVT['pi_name'] 
                                . '/index.php?mode=home&amp;id='
                                . urlencode($entry['id']));
            $entry['date']      = 'false';
            $entry['image_uri'] = false;
            $entries[] = $entry;
        }

        return $entries;
    }

    /**
    * Returns array of (
    *   'id'        => $id (string),
    *   'title'     => $title (string),
    *   'uri'       => $uri (string),
    *   'date'      => $date (int: Unix timestamp),
    *   'image_uri' => $image_uri (string),
    *   'raw_data'  => raw data of the item (stripslashed)
    * )
    */
    function getItemById($id, $all_langs = false)
    {
        global $_CONF, $_TABLES, $_CONF_ADVT;

        $retval = array();

        if (($this->uid == 1) AND ($this->isLoginRequired() === true)) {
            return $retval;
        }

        $sql = "SELECT 
                    ad.ad_id, ad.subject, ad.add_date,
                    ph.filename
                FROM {$_TABLES['ad_ads']} ad
                LEFT JOIN {$_TABLES['ad_photo']} ph
                ON ad.ad_id = ph.ad_id
                WHERE (ad.ad_id ='" . DB_escapeString($id) . "') ";
        $result = DB_query($sql);
        if (DB_error()) {
            return $retval;
        }

        if (DB_numRows($result) == 1) {
            USES_classifieds_class_image();
            $A = DB_fetchArray($result, false);
            $A = array_map('stripslashes', $A);

            $retval['id']        = $A['ad_id'];
            $retval['title']     = $A['subject'];

            $retval['uri']       = COM_buildUrl($_CONF['site_url'] 
                                . '/' . $_CONF_ADVT['pi_name']
                                . '/index.php?mode=detail&amp;id='
                                 . urlencode($A['ad_id']));
            $retval['date']      = $A['add_date'];
            $retval['image_uri'] = adImage::dispUrl($A['filename']);
            $retval['raw_data']  = $A;
        }

        return $retval;
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
    function getItems($category, $all_langs = false)
    {
        global $_CONF, $_TABLES, $_CONF_ADVT;

        $entries = array();

        if (($this->uid == 1) AND ($this->isLoginRequired() === true)) {
            return $entries;
        }

        $sql = "SELECT * "
             . "FROM {$_TABLES['ad_ads']} "
             . "WHERE (cat_id ='" . DB_escapeString($category) . "') "
             . "ORDER BY ad_id";

        $result = DB_query($sql);
        if (DB_error()) {
            return $entries;
        }
        while (($A = DB_fetchArray($result, false)) !== false) {
            $entry = array();

            $entry['id']        = $A['ad_id'];
            $entry['title']     = $A['subject'];

            $entry['uri']       = COM_buildUrl($_CONF['site_url'] 
                                . '/' . $_CONF_ADVT['pi_name'] 
                                . '/index.php?mode=detail&amp;id='
                                . urlencode($A['ad_id']));
            $entry['date']      = $A['add_date'];
            $entry['image_uri'] = $retval['uri'];
            $entries[] = $entry;
        }

        return $entries;
    }
}
?>
