<?php
/**
*   Class to handle images
*
*   @author     Lee Garner <lee@leegarner.com>
*   @copyright  Copyright (c) 2009-2017 Lee Garner <lee@leegarner.com>
*   @package    classifieds
*   @version    1.1.3
*   @license    http://opensource.org/licenses/gpl-2.0.php
*               GNU Public License v2 or later
*   @filesource
*/
namespace Classifieds;

/**
 *  Image-handling class
 *  @package classifieds
 */
class Image
{
    /** Path to actual image (without filename)
     *  @var string */
    var $pathImage;

    /** ID of the current ad
     *  @var string */
    var $ad_id;

    /**
     *  Constructor
     *  @param string $name Optional image filename
     */
    public function __construct($photo_id)
    {
        global $_TABLES;

        $this->pathImage = $_CONF_ADVT['imgpath'];
        $photo_id = (int)$photo_id;

        $res = DB_query("SELECT * FROM {$_TABLES['ad_photo']}
                WHERE photo_id = '$photo_id'");
        if ($res) {
            $row = DB_fetchArray($res, false);
            $this->photo_id = (int)$row['photo_id'];
            $this->ad_id = $row['ad_id'];
            $this->filename = $row['filename'];
        } else {
            $this->photo_id = 0;
        }
    }


    /**
    *   Delete an image from disk.
    */
    public function Delete()
    {
        global $_TABLES;

        // If we're deleting from disk also, get the filename and
        // delete it and its thumbnail from disk.
        if ($this->filename == '') {
            return;
        }

        if (self::UsedCount($this->photo_id) == 1 &&
            file_exists($imgpath . '/' . $this->filename)) {
            unlink($imgpath . '/' . $this->filename);
        }
        DB_delete($_TABLES['ad_photo'], 'photo_id', $this->photo_id);
        $this->photo_id = 0;
    }


    /**
    *   Function to retrieve ID and filename of all images.
    *   Optional limit value can be used to get only one.
    *
    *   @param  string  $ad_id      Ad ID
    *   @param  intger  $limit      Optional limit modifier
    *   @return array       Array of id=>filename for images.
    */
    public static function getAll($ad_id, $limit=0)
    {
        global $_TABLES;

        $limit = (int)$limit;
        $sql = "SELECT photo_id, filename FROM {$_TABLES['ad_photo']}
                WHERE ad_id = '" . COM_sanitizeId($ad_id) . "'";
        if ($limit > 0) {
            $sql .= " limit $limit ";
        }
        $res = DB_query($sql);
        $retval = array();
        while ($img = DB_fetchArray($res, false)) {
            $retval[$img['photo_id']] = $img['filename'];
        }
        return $retval;
    }


    /**
    *   Get the first image in the database for a given ad
    *
    *   @param  string  $ad_id      Ad ID
    *   @return string              Filename of first image
    */
    public static function getFirst($ad_id)
    {
        $images = self::getAll($ad_id, 1);
        if (!empty($images)) {
            reset($images);
            return current($images);
        } else {
            return '';
        }
    }


    /**
    *   Delets all photos related to the given ad from the disk
    *   and the database.
    *
    *   @param  int     $ad_id  ID of ad for which photos are to be deleted
    */
    public static function DeleteAll($ad_id)
    {
        global $_TABLES, $_CONF_ADVT;

        $images = self::getAll($ad_id);
        foreach ($images as $id=>$filename) {
            // Only delete the file if it's the last record.
            if (self::UsedCount($filename) > 1) continue;
            if (file_exists($_CONF_ADVT['imgpath'] . '/user/' . $filename)) {
                unlink($_CONF_ADVT['imgpath'] . '/user/' . $filename);
            }
        }
        // Delete all image records for this ad_id
        DB_delete($_TABLES['ad_photo'], 'ad_id', $ad_id);

        return 0;
    }


    /**
    *   Get the number of records for this image.
    *   Used to determine if an image file can be removed from disk.
    *
    *   @param  string  $filename   Image filename
    *   @return integer     Number of records in database
    */
    public static function UsedCount($filename)
    {
        global $_TABLES;
        return DB_count($_TABLES['ad_photo'], 'filename', $filename);
    }


    /**
    *   Shortcut function to get the URL to the display version of the image.
    *   Used for the lightbox popup display.
    *
    *   @param  string  $filename   Image filename
    *   @return string              URL to image sized for display
    */
    public static function dispUrl($filename)
    {
        global $_CONF_ADVT;
        $args = array();
        $args[1] = $_CONF_ADVT['imgpath'] . '/user/' . $filename;
        $args[2] = $_CONF_ADVT['img_max_width'];
        $args[3] = $_CONF_ADVT['img_max_height'];
        return PLG_callFunctionForOnePlugin('LGLIB_ImageUrl', $args);
    }


    /**
    *   Shortcut function to get the URL to the vmall display image.
    *   Used for the larger thumbnails on the ad detail page
    *
    *   @param  string  $filename   Image filename
    *   @return string              URL to image sized for display
    */
    public static function smallUrl($filename)
    {
        global $_CONF_ADVT;
        $args = array();
        $args[1] = $_CONF_ADVT['imgpath'] . '/user/' . $filename;
        $args[2] = $_CONF_ADVT['detail_img_width'];
        $args[3] = 0;
        return PLG_callFunctionForOnePlugin('LGLIB_ImageUrl', $args);
    }


    /**
    *   Shortcut functions to get resized thumbnail URLs.
    *   Used for the small thumbnails on ad listings.
    *
    *   @param  string  $filename   Filename to view
    *   @return string      URL to the resized image
    */
    public static function thumbUrl($filename)
    {
        global $_CONF_ADVT;
        $args = array();
        $args[1] = $_CONF_ADVT['imgpath'] . '/user/' . $filename;
        $args[2] = $_CONF_ADVT['thumb_max_size'];
        $args[3] = $_CONF_ADVT['thumb_max_size'];
        return PLG_callFunctionForOnePlugin('LGLIB_ImageUrl', $args);
    }

}   // class Image

?>
