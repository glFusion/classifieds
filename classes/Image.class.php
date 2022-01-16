<?php
/**
 * Class to handle images.
 *
 * @author      Lee Garner <lee@leegarner.com>
 * @copyright   Copyright (c) 2009-2020 Lee Garner <lee@leegarner.com>
 * @package     classifieds
 * @version     v1.3.0
 * @license     http://opensource.org/licenses/gpl-2.0.php
 *              GNU Public License v2 or later
 * @filesource
 */
namespace Classifieds;


/**
 * Image-handling class.
 * @see UploadDownload for image file uploading functions.
 * @package classifieds
 */
class Image extends UploadDownload
{
    /** Path to actual image (without filename).
     * @var string */
    private $pathImage = '';

    /** Record ID of the image.
     * @var integer */
    private $photo_id = 0;

    /** ID of the current ad.
     * @var string */
    private $ad_id = '';

    /** Imgae filename, no path.
     * @var string */
    private $filename = '';

    /** Nonce, used to correlate images uploaded to new ads.
     * @var string */
    private $nonce = '';

    /** Timestamp when the image is uploaded.
     * Only used to purge orphaned images.
     * @var integer */
    private $ts = 0;


    /**
     * Constructor.
     *
     * @param   integer $img    ID of image record or array of data
     */
    public function __construct($img=0)
    {
        global $_TABLES, $_CONF_ADVT;

        $this->pathImage = $_CONF_ADVT['imgpath'];
        $row = array();
        if (is_array($img)) {
            $row = $img;
        } elseif (is_integer($img) && $img > 0) {
            $img_id  = (int)$img;
            $res = DB_query(
                "SELECT * FROM {$_TABLES['ad_photo']}
                WHERE photo_id = '$img_id'"
            );
            if ($res) {
                $row = DB_fetchArray($res, false);
            }
        }
        if (!empty($row)) {
            $this->photo_id = (int)$row['photo_id'];
            $this->ad_id = $row['ad_id'];
            $this->filename = $row['filename'];
            $this->nonce = $row['nonce'];
            $this->ts = (int)$row['ts'];
        }
        parent::__construct();
        $this->setAllowedMimeTypes(array(
            'image/gif' => array('gif'),
            'image/pjpeg' => array('jpg','jpeg'),
            'image/jpeg' => array('jpg','jpeg'),
            'image/png' => array('png'),
            'image/x-png' => array('png'),
        ) );
        $this->setMaxDimensions($_CONF_ADVT['img_max_width'], $_CONF_ADVT['img_max_height']);
    }


    /**
     * Delete this image from the database and disk.
     *
     * @return  boolean     True if image was deleted, False if not.
     */
    public function Delete()
    {
        global $_TABLES;

        // If we're deleting from disk also, get the filename and
        // delete it and its thumbnail from disk.
        if ($this->filename == '') {
            return false;
        }

        if (self::UsedCount($this->photo_id) == 1 &&
            file_exists($this->pathImage . '/' . $this->filename)) {
            unlink($imgpath . '/' . $this->filename);
        }
        DB_delete($_TABLES['ad_photo'], 'photo_id', $this->photo_id);
        $this->photo_id = 0;
        return true;
    }


    /**
     * Retrieve ID and filename of all images.
     * Optional limit value can be used to get only one.
     *
     * @param   string  $ad_id      Ad ID
     * @param   intger  $limit      Optional limit modifier
     * @return  array       Array of id=>filename for images.
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
     * Get the first image in the database for a given ad.
     *
     * @param   string  $ad_id      Ad ID
     * @return  string              Filename of first image
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
     * Delets all photos related to the given ad from the disk and the database.
     *
     * @param   integer $ad_id  ID of ad for which photos are to be deleted
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
     * Get the number of records for this image.
     * Used to determine if an image file can be removed from disk.
     *
     * @param   string  $filename   Image filename
     * @return  integer     Number of records in database
     */
    public static function UsedCount($filename)
    {
        global $_TABLES;
        return DB_count($_TABLES['ad_photo'], 'filename', $filename);
    }


    /**
     * Shortcut function to get the URL to the display version of the image.
     * Used for the lightbox popup display.
     *
     * @param   string  $filename   Image filename
     * @return  string              URL to image sized for display
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
     * Shortcut function to get the URL to the vmall display image.
     * Used for the larger thumbnails on the ad detail page
     *
     * @param   string  $filename   Image filename
     * @return  string              URL to image sized for display
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
     * Shortcut functions to get resized thumbnail URLs.
     * Used for the small thumbnails on ad listings.
     *
     * @param   string  $filename   Filename to view
     * @return  string      URL to the resized image
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


    /**
     * Update the image record with the Ad ID.
     * Used where the ID of a new ad is not known until saving
     * so images are identified by a nonce value.
     *
     * @param   string  $nonce      Nonce used to identify images
     * @param   string  $ad_id      New ad ID
     */
    public static function updateAdID($nonce, $ad_id)
    {
        global $_TABLES;

        $ad_id = DB_escapeString($ad_id);
        $nonce = DB_escapeString($nonce);
        $sql = "UPDATE {$_TABLES['ad_photo']}
            SET ad_id = '$ad_id'
            WHERE nonce = '$nonce'";
        DB_query($sql);
    }


    /**
     * Set the local ad_id value.
     *
     * @param   string  $ad_id      Ad ID
     * @return  object  $this
     */
    public function withAdID(string $ad_id) : self
    {
        $this->ad_id = $ad_id;
        return $this;
    }


    /**
     * Count the number of images uploaded for an ad.
     *
     * @param   string  $ad_id      Ad record ID
     * @param   string  $nonce      Optional nonce
     * @return  intger      Number of related images
     */
    public static function countByAd($ad_id, $nonce='')
    {
        global $_TABLES;

        if (empty($ad_id)) {
            // Empty Ad ID, then it's a new ad. Use the nonce
            return DB_count(
                $_TABLES['ad_photo'],
                'nonce',
                DB_escapeString($nonce)
            );
        } else {
            // Existing Ad, just check the ad_id
            return DB_count(
                $_TABLES['ad_photo'],
                'ad_id',
                DB_escapeString($ad_id)
            );
        }
    }


    /**
     * Check if a specific ad image exists
     *
     * @param   string  $filename   Name of file to check
     * @return  boolean     True if image file exists, False if not
     */
    public static function fileExists($filename)
    {
        global $_CONF_ADVT;

        return is_file($_CONF_ADVT['imgpath'] . '/user/' . $filename);
    }


    /**
     * Check this specific ad image exists
     *
     * @uses    self::fileExists()
     * @return  boolean     True if image file exists, False if not
     */
    public function Exists()
    {
        return self::fileExists($this->filename);
    }


    /**
     * Delete a specific ad image from disk.
     *
     * @param   string  $filename   Name of file to delete
     */
    public static function deleteFile($filename)
    {
        global $_CONF_ADVT;

        @unlink($_CONF_ADVT['imgpath'] . '/user/' . $filename);
    }


    /**
     * Clean up orphaned images that are more than an hour old.
     */
    public static function cleanOrphans()
    {
        global $_TABLES;

        $min_ts = time() - 3600;    // now - 1 hour
        $res = DB_query(
            "SELECT * FROM {$_TABLES['ad_photo']}
            WHERE ad_id = '' AND ts < $min_ts"
        );
        if ($res && DB_numRows($res) > 0) {
            while ($A = DB_fetchArray($res, false)) {
                self::deleteFile($A['filename']);
            }

            // Now delete from the DB, just using one query.
            DB_query(
                "DELETE FROM {$_TABLES['ad_photo']}
                WHERE ad_id = '' AND ts < $min_ts"
            );
        }
    }


    /**
     * Set the internal property value for a nonce.
     *
     * @param   string  $nonce  Nonce value to set
     */
    public function setNonce(string $nonce) : self
    {
        $this->nonce = $nonce;
        return $this;
    }


    /**
     * Create a unique key based on some string.
     *
     * @param   string  $str    Base string
     * @return  string  Nonce string
     */
    public static function makeNonce($str='')
    {
        return uniqid() . rand(100,999);
    }


    /**
     * Upload images and associate with the current ad ID.
     *
     * @return  boolean     True on success, False on error
     */
    public function uploadFiles()
    {
        global $_TABLES;

        $status = parent::uploadFiles();
        if ($status) {
            $ad_id = DB_escapeString($this->ad_id);
            $nonce = DB_escapeString($this->nonce);
            foreach ($this->getUploadedFiles() as $filename) {
                $sql = "INSERT INTO {$_TABLES['ad_photo']} SET
                    ad_id = '$ad_id',
                    nonce = '$nonce',
                    filename = '" . DB_escapeString($filename) . "',
                    ts = UNIX_TIMESTAMP()";
                DB_query($sql);
            }
        }
        return $status;
    }

}
