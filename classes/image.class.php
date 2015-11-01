<?php
/**
 *  Class to handle images
 *
 *  @author     Lee Garner <lee@leegarner.com>
 *  @copyright  Copyright (c) 2009 Lee Garner <lee@leegarner.com>
 *  @package    classifieds
 *  @version    0.3
 *  @license    http://opensource.org/licenses/gpl-2.0.php 
 *  GNU Public License v2 or later
 *  @filesource
 */

USES_class_upload();

/**
 *  Image-handling class
 *  @package classifieds
 */
class Image extends upload
{
    /** Path to actual image (without filename)
     *  @var string */
    var $pathImage;

    /** Path to image thumbnail (without filename)
     *  @var string */
    var $pathThumb;

    /** ID of the current ad
     *  @var string */
    var $ad_id;

    /** Mime-type of image.  Just to save the image library a little work
    *   @var string */
    var $mime_type;

    /** Array of the names of successfully uploaded files
     *  @var array */
    var $goodfiles = array();

    /** Indicate that we actually have one or more files to upload
     *  @var boolean */
    var $havefiles;


    /**
     *  Constructor
     *  @param string $name Optional image filename
     */
    public function __construct($ad_id, $varname='photo')
    {
        global $_CONF_ADVT, $_CONF;

        if (empty($_FILES[$varname])) {
            $this->havefiles = false;
            return;
        } else {
            $this->havefiles = true;
        }

        $this->setContinueOnError(true);
        $this->setLogFile($_CONF['path_log'] . 'error.log');
        $this->setDebug(true);
        parent::upload();

        // Before anything else, check the upload directory
        if (!$this->setPath($_CONF_ADVT['image_dir'])) {
            return;
        }
        $this->ad_id = trim($ad_id);
        $this->pathImage = $_CONF_ADVT['image_dir'];
        $this->pathThumb = $this->pathImage . '/thumb';
        $this->setAllowedMimeTypes(array(
                'image/pjpeg' => '.jpg,.jpeg',
                'image/jpeg'  => '.jpg,.jpeg',
                'image/png'   => '.png',
        ));
        $this->setMaxFileSize($_CONF['max_image_size']);
        $this->setMaxDimensions(
                $_CONF_ADVT['img_max_width'],
                $_CONF_ADVT['img_max_height']
        );
        $this->setAutomaticResize(true);
        $this->setFieldName($varname);

        $filenames = array();
        for ($i = 0; $i < count($_FILES[$varname]['name']); $i++) {
            $filenames[] = $this->ad_id . '_' . rand(100,999) . '.jpg';
        }
        $this->setFileNames($filenames);
    }


    /**
    *   Perform the upload.
    *   Make sure we can upload the files and create thumbnails before
    *   adding the image to the database.
    */
    public function uploadFiles()
    {
        global $_TABLES;

        // If there are no files at all, just return.
        if (!$this->havefiles)
            return;

        parent::uploadFiles();
        // Deprecated, use LGLIB_ImageUrl() when rendering
        //$this->MakeThumbs();

        foreach ($this->goodfiles as $filename) {
            $sql = "
                INSERT INTO
                    {$_TABLES['ad_photo']}
                    (ad_id, filename)
                VALUES (
                    '{$this->ad_id}', '".
                    DB_escapeString($filename)."'
                )";
            $result = DB_query($sql);
            if (!$result) {
                $this->addError("Image::uploadFiles() : Failed to insert {$filename}");
            }
        }
 
    }


    /**
     *  Calculate the new dimensions needed to keep the image within
     *  the provided width & height while preserving the aspect ratio.
     *  @deprecated 1.0.8
     *  @param string  $srcfile     Source filepath/name
     *  @param integer $width       New width, in pixels
     *  @param integer $height      New height, in pixels
     *  @return array  $newwidth, $newheight
     */
    public function reDim($srcfile, $width=0, $height=0)
    {
        list($s_width, $s_height) = @getimagesize($srcfile);

        // get both sizefactors that would resize one dimension correctly
        if ($width > 0 && $s_width > $width)
            $sizefactor_w = (double) ($width / $s_width);
        else
            $sizefactor_w = 1;

        if ($height > 0 && $s_height > $height)
            $sizefactor_h = (double) ($height / $s_height);
        else
            $sizefactor_h = 1;

        // Use the smaller factor to stay within the parameters
        $sizefactor = min($sizefactor_w, $sizefactor_h);

        $newwidth = (int)($s_width * $sizefactor);
        $newheight = (int)($s_height * $sizefactor);

        return array($newwidth, $newheight);
    }

    /**
     *  Resize an image to the specified dimensions, placing the resulting
     *  image in the specified location.  At least one of $newWidth or
     *  @deprecated 1.0.8
     *  $newHeight must be specified.
     *  @return string Blank if successful, error message otherwise.
     */
    public function MakeThumbs()
    {
        global $_CONF_ADVT, $LANG_PHOTO;

        if (!is_array($this->_fileNames)) return;

        $thumbsize = (int)$_CONF_ADVT['thumb_max_size'];
        foreach ($this->_fileNames as $filename) {
            $src = "{$this->pathImage}/{$filename}";
            $dst = "{$this->pathThumb}/{$filename}";

            // If  parent::upload() dropped the file due to some restriction,
            // then the source won't be there even though the file info is.
            if (!file_exists($src))
                continue;

            USES_lglib_class_image();
            // Calculate the new dimensions
            list($dWidth,$dHeight) = 
                lgImage::reDim($src, $thumbsize, $thumbsize);
                //$this->reDim($src, $thumbsize, $thumbsize);

            if ($dWidth == 0 || $dHeight == 0) {
                $this->_addError("MakeThumbs() $filename could not get dimensions");
                return;
            }

            // Returns an array, with [0] either true/false and [1] 
            // containing a message.  For older versions of glFusion,
            // we call Media Gallery's _mg_resizeImage() as a backup.  This
            // won't work if MG isn't enabled.
            list($retval, $msg) = IMG_resizeImage($src, $dst,  
                                $dHeight, $dWidth, $this->mime_type);

            if ($retval != true)
                $this->_addError("MakeThumbs() : $filename - $msg");
            else
                $this->goodfiles[] = $filename;
        }

    }   // function MakeThumbs()


    /**
     *  Delete an image from disk.  Called by Entry::Delete if disk
     *  deletion is requested.
     */
    public function Delete()
    {
        global $_TABLES, $_USER, $_CONF_ADVT;

        // If we're deleting from disk also, get the filename and 
        // delete it and its thumbnail from disk.
        if ($this->filename == '') {
            return;
        }

        $this->_deleteOneImage($this->pathImage);
        $this->_deleteOneImage($this->pathThumb);
    }

    /**
     *  Delete a single image using the current name and supplied path
     *  @access private
     *  @param string $imgpath Path to file
     */
    private function _deleteOneImage($imgpath)
    {
        if (file_exists($imgpath . '/' . $this->filename))
            unlink($imgpath . '/' . $this->filename);
    }

    /**
     *  Handles the physical file upload and storage.
     *  If the image isn't validated, the upload doesn't happen.
     *  @param array $file $_FILES array
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

        // Create the display and thumbnail versions.  Errors here
        // aren't good, but aren't fatal.
        //$this->ReSize('thumb');
        //$this->ReSize('disp');

    }   // function Upload()


    /**
     *  Validate the uploaded image, checking for size constraints and other errors
     *  @param array $file $_FILES array
     *  @return boolean True if valid, False otherwise
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
