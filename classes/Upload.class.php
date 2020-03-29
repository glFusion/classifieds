<?php
/**
 * Class to handle uploads.
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
 * Image-handling class/
 * @package classifieds
 */
class Upload extends \upload
{
    /** Path to actual image (without filename).
     * @var string */
    private $pathImage = '';

    /** ID of the current ad.
     * @var string */
    private $ad_id = '';

    /** Nonce value to identify images for new ads.
     * @var string */
    private $nonce = '';

    /** Mime-type of image. Just to save the image library a little work.
    * @var string */
    private $mime_type = '';

    /** Count of files submitted for upload.
     * @var integer */
    private $havefiles = 0;

    /** Max number of files to upload. Considers files already uploaded.
     * @var integer */
    private $maxfiles = 0;

    /** Variiable name, used to index $_FILES.
     * @var string */
    private $varname = 'files';


    /**
     * Set the Ad ID related to the uploaded images.
     *
     * @param   string  $ad_id      ID of ad associated with this image
     * @param   string  $varname    Form variable name, default 'photo'
     */
    public function __construct($ad_id)
    {
        global $_CONF_ADVT, $_CONF;

        if (empty($_FILES[$this->varname]) || !is_array($_FILES[$this->varname]['name'])) {
            return;
        } else {
            $this->havefiles = count($_FILES[$this->varname]['name']);
        }

        $this->setContinueOnError(true);
        $this->setLogFile($_CONF['path_log'] . 'error.log');
        $this->setDebug(true);
        parent::__construct();

        // Before anything else, check the upload directory
        if (!$this->setPath($_CONF_ADVT['imgpath'] . '/user')) {
            return;
        }
        $this->ad_id = trim($ad_id);
        $this->pathImage = $_CONF_ADVT['imgpath'] . '/user';
        $this->setAllowedMimeTypes(array(
            'image/gif'     => '.gif',
            'image/pjpeg'   => '.jpg,.jpeg',
            'image/jpeg'    => '.jpg,.jpeg',
            'image/png'     => '.png',
            'image/x-png'   => '.png',
        ));
        $this->setMaxFileSize($_CONF['max_image_size']);
        $this->setMaxDimensions(0, 0);
        $this->setAutomaticResize(true);
        $this->setFieldName($this->varname);
    }


    /**
     * Perform the upload.
     * Make sure we can upload the files and create thumbnails before
     * adding the image to the database.
     *
     * @return  array       Array of img_id=>filenames to update the form
     */
    public function uploadFiles()
    {
        global $_TABLES, $_CONF_ADVT;

        if (!$this->havefiles) {        // no files to upload
            return;
        }

        // Set the filenames in the parent class
        $filenames = array();
        foreach ($_FILES[$this->varname]['name'] as $origname) {
            if (empty($origname)) {     // could this happen?
                continue;
            }
            $parts = pathinfo($origname);
            $filenames[] = $this->makeFilename($parts['extension']);
        }
        $this->setFileNames($filenames);

        // Actually handle uploading and copying the files
        parent::uploadFiles();

        // Determine the maximum number of files that can be uploaded.
        // All submitted files have been uploaded, but only this many
        // will be added to the database
        $this->maxfiles = $_CONF_ADVT['imagecount'] - Image::countByAd($this->ad_id, $this->nonce);
        if ($this->maxfiles < 0) {
            $this->maxfiles = 0;
        }

        // Insert the uploads into the database and collect the filenames
        // to return to the AJAX function.
        $filenames = array();
        $i = 0;
        foreach ($this->_fileNames as $filename) {
            if (++$i > $this->maxfiles) {
                break;
            }
            $sql = "INSERT INTO {$_TABLES['ad_photo']} SET
                ad_id = '" . DB_escapeString($this->ad_id) . "',
                filename = '" . DB_escapeString($filename) . "',
                nonce = '{$this->nonce}',
                ts = UNIX_TIMESTAMP()";
            //COM_errorLog($sql);
            $result = DB_query($sql);
            if (!$result) {
                $this->addError("Image::uploadFiles() : Failed to insert images into DB");
            } else {
                $filenames[DB_insertID()] = $filename;
            }
        }
        return $filenames;
    }


    /**
     * Validate the uploaded image, checking for size constraints and other errors.
     *
     * @param   array   $file   $_FILES array
     * @return  boolean     True if valid, False otherwise
     */
    public function Validate($file)
    {
        global $LANG_PHOTO, $_CONF_ADVT;

        if (!is_array($file))
            return;

        $msg = '';
        // verify that the image is a jpeg or other acceptable format.
        // Don't trust user input for the mime-type
        if (function_exists('exif_imagetype')) {
            switch (exif_imagetype($file['tmp_name'])) {
            case IMAGETYPE_JPEG:
                $this->filetype = 'jpg';
                $this->mime_type = 'image/jpeg';
                break;
            case IMAGETYPE_PNG:
                $this->filetype = 'png';
                $this->mime_type  = 'image/png';
                break;
            case IMAGETYPE_GIF:
                $this->filetype = 'gif';
                $this->mime_type  = 'image/gif';
                break;
            default:    // other
                $msg .= 'upload_invalid_filetype';
                break;
            }
        } else {
            return "System Error: Missing exif_imagetype function";
        }

        // Now check for error messages in the file upload: too large, etc.
        switch ($file['error']) {
        case UPLOAD_ERR_OK:
            if ($file['size'] > $_CONF['max_image_size']) {
                $msg .= "<li>upload_too_big'</li>\n";
            }
            break;
        case UPLOAD_ERR_INI_SIZE:
        case UPLOAD_ERR_FORM_SIZE:
            $msg = "<li>upload_too_big</li>\n";
            break;
        case UPLOAD_ERR_NO_FILE:
            $msg = "<li>upload_missing_msg</li>\n";
            break;
        default:
            $msg = "<li>upload_failed_msg</li>\n";
            break;
        }

        return $msg;
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
     * Set the internal property value for a nonce.
     *
     * @return  object  $this
     * @param   string  $nonce  Nonce value to set
     */
    public function setNonce($nonce)
    {
        $this->nonce = $nonce;
        return $this;
    }


    /**
     * Create the target filename for the image file.
     *
     * @param   string  $ext    File extension
     * @return  string      File name
     */
    private function makeFilename($ext='jpg')
    {
        if ($ext != '') {
            $ext = '.' . $ext;
        }
        return uniqid() . '_' . rand(100,999) . $ext;
    }

}   // class Image

?>
