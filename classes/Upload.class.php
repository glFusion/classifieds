<?php
/**
 * Class to handle uploads.
 *
 * @author      Lee Garner <lee@leegarner.com>
 * @copyright   Copyright (c) 2009 Lee Garner <lee@leegarner.com>
 * @package     classifieds
 * @version     0.3
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
    var $pathImage;

    /** ID of the current ad.
     * @var string */
    var $ad_id;

    /** Mime-type of image. Just to save the image library a little work.
    * @var string */
    var $mime_type;

    /** Indicate that we actually have one or more files to upload.
     * @var boolean */
    public $havefiles;


    /**
     * Constructor.
     *
     * @param   string  $ad_id      ID of ad associated with this image
     * @param   string  $varname    Form variable name, default 'photo'
     */
    public function __construct($ad_id, $varname='photo')
    {
        global $_CONF_ADVT, $_CONF;

        if (empty($_FILES[$varname]) || !is_array($_FILES[$varname]['name'])) {
            $this->havefiles = false;
            return;
        } else {
            $this->havefiles = true;
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
                'image/pjpeg' => '.jpg,.jpeg',
                'image/jpeg'  => '.jpg,.jpeg',
                'image/png'   => '.png',
        ));
        $this->setMaxFileSize($_CONF['max_image_size']);
        $this->setMaxDimensions(0, 0);
        $this->setAutomaticResize(true);
        $this->setFieldName($varname);

        $filenames = array();
        for ($i = 0; $i < count($_FILES[$varname]['name']); $i++) {
            if (empty($_FILES[$varname]['name'][$i])) continue;
            $filenames[] = $this->ad_id . '_' . rand(100,999) . '.jpg';
        }
        $this->setFileNames($filenames);
    }


    /**
     * Perform the upload.
     * Make sure we can upload the files and create thumbnails before
     * adding the image to the database.
     */
    public function uploadFiles()
    {
        global $_TABLES;

        if (!$this->havefiles) return;

        parent::uploadFiles();

        $values = array();
        foreach ($this->_fileNames as $filename) {
            $values[] =  "('{$this->ad_id}', '". DB_escapeString($filename)."')";
        }
        if (empty($values)) return;
        $value_str = implode(',', $values);
        $sql = "INSERT INTO {$_TABLES['ad_photo']}
                    (ad_id, filename)
                VALUES $value_str";
        //COM_errorLog($sql);
        $result = DB_query($sql);
        if (!$result) {
            $this->addError("Image::uploadFiles() : Failed to insert {$filename}");
        }
    }


    /**
     * Handles the physical file upload and storage.
     * If the image isn't validated, the upload doesn't happen.
     *
     * @param   array   $file   $_FILES array
     */
    public function Upload($file)
    {
        global $LANG_PHOTO, $_CONF_ADVT;

        if (!is_array($file))
            return "Invalid file given to Upload()";

        $msg = $this->Validate($file);
        if ($msg != '')
            return $msg;

        $this->filename = $this->ad_id . '.' . rand(10,99) . $this->filetype;

        if (!@move_uploaded_file($file['tmp_name'],
                $this->pathImage . '/' . $this->filename)) {
            return 'upload_failed_msg';
        }
    }   // function Upload()


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

}   // class Image

?>
