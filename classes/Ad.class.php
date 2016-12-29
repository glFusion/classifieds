<?php
/**
*   Class to manage ad types
*
*   @author     Lee Garner <lee@leegarner.com>
*   @copyright  Copyright (c) 2016 Lee Garner <lee@leegarner.com>
*   @package    classifieds
*   @version    1.1.0
*   @license    http://opensource.org/licenses/gpl-2.0.php
*               GNU Public License v2 or later
*   @filesource
*/

USES_classifieds_class_category();
USES_classifieds_class_adtype();
USES_classifieds_class_image();

/**
*   Class for ad type
*   @package classifieds
*/
class Ad
{
    /** Error string or value, to be accessible by the calling routines.
     *  @var mixed */
    public $Error;
    public $isNew;
    public $Cat;    // Category object
    public $Type;   // Type object

    private $properties;
    private $table;
    private $isAdmin;

    // Database fields
    private $fields = array(
        'ad_id' => 'string',
        'cat_id' => 'int',
        'uid' => 'int',
        'subject' => 'string',
        'description' => 'string',
        'url' => 'string',
        'views' => 'int',
        'add_date' => 'int',
        'exp_date' => 'int',
        'price' => 'string',
        'ad_type' => 'int',
        'sentnotify' => 'int',
        'keywords' => 'string',
        'exp_sent' => 'int',
        'comments' => 'int',
        'comments_enabled' => 'int',
    );


    /**
    *   Constructor.
    *   Reads in the specified class, if $id is set.  If $id is zero,
    *   then a new entry is being created.
    *
    *   @param  string  $id     Optional Ad ID
    *   @param  string  $table  Table Name, default to production
    */
    public function __construct($id='', $table='ad_ads')
    {
        $this->properties = array();
        $this->setTable($table);      // default to prod table
        if ($id == '') {
            $this->isNew = true;
            $this->ad_id = '';
            $this->subject = '';
            $this->description = '';
        } else {
            $this->ad_id = $id;
            $this->Read();
        }
        $this->isAdmin = plugin_ismoderator_classifieds() ? true : false;
    }


    /**
    *   Setter function
    *
    *   @param  string  $key    Name of variable to set
    *   @param  mixed   $value  Value to set for variable
    */
    public function __set($key, $value)
    {
        switch($key) {
        case 'ad_id':
            // Item ID values
            $this->properties[$key] = COM_sanitizeId($value, false);
            break;

        case 'subject':
        case 'url':
        case 'keywords':
        case 'price':
            // String values, strip html
            $this->properties[$key] = strip_tags(trim($value));
            break;

        case 'description':
            // String values, html ok
            $this->properties[$key] = COM_checkHTML(trim($value));
            break;

        case 'sentnotify':
        case 'exp_sent':
        case 'comments_enabled':
            // Boolean values
            $this->properties[$key] = $value == 1 ? 1 : 0;
            break;

        case 'views':
        case 'uid':
        case 'cat_id':
        case 'add_date':
        case 'exp_date':
        case 'comments':
        case 'ad_type':
            // Integer values
            $this->properties[$key] = (int)$value;
            break;
        }
    }


    /**
    *   Getter function
    *
    *   @param  string  $key    Name of value to retrieve
    *   @return mixed           Value for variable or NULL if undefined
    */
    public function __get($key)
    {
        if (isset($this->properties[$key]))
            return $this->properties[$key];
        else
            return NULL;
    }


    /**
    *   Sets all variables to the matching values from $rows
    *   @param array $row Array of values, from DB or $_POST
    */
    public function SetVars($row)
    {
        if (!is_array($row)) return;

        // Set the database field values
        foreach ($this->fields as $name=>$type) {
            if (isset($row[$name])) {
                $this->$name = $row[$name];
            }
        }
    }


    /**
    *  Read one ad from the database and populate the local values.
    *
    *  @param integer $id Optional ID.  Current ID is used if empty
    */
    public function Read($id = '')
    {
        if ($id != '') {
            $this->ad_id = COM_sanitizeId($id, false);
        }

        $result = DB_query("SELECT * from {$this->table}
                            WHERE ad_id = '{$this->ad_id}'");
        $row = DB_fetchArray($result, false);
        if ($row) {
            $this->SetVars($row);
            $this->isNew = false;
            $this->Cat = new adCategory($this->cat_id);
            $this->Type= new adType($this->ad_type);
        }
    }


    /**
    *   Save the current values to the database.
    *
    *   @param  array   $A      Optional array of values, e.g. from $_POST
    *   @return boolean         True on success, False on failure
    */
    public function Save($A = array())
    {
        global $_CONF, $_CONF_ADVT;

        // If an array of values is provided, set them in this object
        if (!empty($A)) {
            $this->SetVars($A);
        }

        if ($this->isNew) {
            if (!$this->isAdmin && $_CONF_ADVT['submission']) {
                // If using the queue and not an admin, then switch
                // to the submission table for new items.
                $this->setTable('ad_submission');
            }
            // Set the date added for new records
            $this->add_date = time();
            $sql1 = "INSERT INTO {$this->table} SET ";
            $sql3 = '';
        } else {
            if (!$this->canEdit()) {
                return false;
            }
            $sql1 = "UPDATE {$this->table} SET ";
            $sql3 = " WHERE ad_id = '{$this->ad_id}'";
        }

        $this->_calcExpDate($A['moredays']);

        // Make sure ad_id isn't empty
        $this->ad_id = COM_sanitizeId($this->ad_id, true);

        USES_classifieds_class_upload();
        $Image = new adUpload($this->ad_id);
        if ($Image->havefiles) {
            $Image->uploadFiles();
        }

        $fld_array = array();
        foreach ($this->fields as $name=>$type) {
            if ($type == 'string') {
                // sanitize strings for DB
                $val = DB_escapeString($this->$name);
            } else {
                // int, boolean, etc. are sanitized by __set()
                $val = $this->$name;
            }
            $fld_array[] = "$name = '{$val}'";
        }
        $sql2 = implode(',', $fld_array);
        $sql = $sql1 . $sql2 . $sql3;
        //echo $sql;die;
        DB_query($sql, 1);
        if (DB_error()) {
            COM_errorLog("Error executing $sql");
            LGLIB_storeMessage('Database error saving ad');
            return false;
        } else {
            USES_classifieds_class_notify();
            // Category object needed for notifications, but is not yet
            // set in the current Ad object
            $this->Cat = new adCategory($this->cat_id);
            if ($this->isNew) {
                if ($this->isSubmission()) {
                    // Submission, notify moderators
                    adNotify::Submission($this);
                } else {
                    // Saved directly to prod, notify subscribers
                    adNotify::Subscribers($this);
                }
            }
        }
        return true;
    }


    /**
    *   Duplicate an ad
    *   Creates a new ad and image records based on the current ad.
    *   image records.
    *
    *   @return string  ID of new ad
    */
    public function Duplicate()
    {
        global $_TABLES;

        // Must have an existing ad loaded
        if ($this->isNew) return NULL;

        // Grab the image records before the ad_id changes
        $photos = adImage::getAll($this->ad_id);

        // Clear the ad id and save to get a new ID
        $this->ad_id = '';
        $this->isNew = true;
        $this->Save();

        // Now duplicate all the image records
        $values = array();
        foreach ($photos as $id=>$filename) {
            $values[] = "('{$this->ad_id}', '$filename')";
        }
        if (!empty($values)) {
            $value_str = implode(',', $values);
            $sql = "INSERT INTO {$_TABLES['ad_photo']} (ad_id, filename)
                    VALUES $value_str";
            $r = DB_query($sql);
            if (DB_error()) return NULL;
        }
        return $this->ad_id;
    }


    /**
    *   Delete the current ad record from the database
    *
    *   @param  string  $ad_id  ID of ad to delete
    *   @param  string  $table  Table, either submission or prod
    *   @return boolean         True on success, False on failure
    */
    public static function Delete($ad_id, $table = 'ad_ads')
    {
        global $_TABLES;

        // If we've gotten this far, then the current user has access
        // to delete this ad.
        if ($table == 'ad_submission') {
            // Do the normal plugin rejection stuff
            plugin_moderationdelete_classifieds($ad_id);
        } else {
            // Do the extra cleanup manually, delete any images
            adImage::DeleteAll($ad_id);
        }

        // After the cleanup stuff, delete the ad record itself.
        DB_delete($_TABLES[$table], 'ad_id', $ad_id);
        CLASSIFIEDS_auditLog("Ad {$ad_id} deleted.");
        if (DB_error()) {
            COM_errorLog(DB_error());
            return false;
        } else {
            return true;
        }
    }


    /**
    *   Determines if the current values are valid.
    *
    *   @return boolean True if ok, False otherwise.
    */
    public function isValidRecord()
    {
        if ($this->subject == '' ||
            $this->description == '' ||
             $this->cat_id == '') {
            return false;
        }
        return true;
    }


    /**
    *   Creates the edit form
    *
    *   @param  string  $id Optional Ad ID, current record used if zero
    *   @return string      HTML for edit form
    */
    public function Edit($id = '')
    {
        global $_TABLES, $_CONF, $_CONF_ADVT, $LANG_ADVT, $_USER;

        if ($id != '') $this->Read($id);

        $T = new Template($_CONF_ADVT['path'] . '/templates');
        // Detect uikit theme
        $tpl_path = $_CONF_ADVT['_is_uikit'] ? '.uikit' : '';
        $T->set_file('adedit', "edit$tpl_path.thtml");
        if ($this->isAdmin) {
            $action_url = $_CONF_ADVT['admin_url'] . '/index.php';
            $cancel_url = $_CONF_ADVT['admin_url'] . '/index.php?adminad=x';
        } else {
            $action_url = $_CONF_ADVT['url'] . '/index.php';
            $cancel_url = $_CONF_ADVT['url'] . '/index.php';
        }

        $add_date = new Date($this->add_date, $_CONF['timezone']);
        $exp_date = new Date($this->add_date, $_CONF['timezone']);

        if ($this->isNew) {
            $moredays = $_CONF_ADVT['default_duration'];
        } else {
            // Don't add more days automatically for each edit
            $moredays = 0;
        }

        $T->set_var(array(
            'isNew'         => $this->isNew ? 'true' : '',
            'isAdmin'       => $this->isAdmin ? 'true' : '',
            'pi_admin_url'  => $_CONF_ADVT['admin_url'],
            'ad_id'         => $this->ad_id,
            'description'   => htmlspecialchars($this->description),
            'ena_chk'       => $this->enabled == 1 ? 'checked="checked"' : '',
            'post_options'  => $post_options,
            'change_editormode'     => 'onchange="change_editmode(this);"',
            'glfusionStyleBasePath' => $_CONF['site_url']. '/fckeditor',
            'gltoken_name'  => CSRF_TOKEN,
            'gltoken'       => SEC_createToken(),
            'has_delbtn'    => 'true',
            'txt_photo'     => "{$LANG_ADVT['photo']}<br />" .
                    sprintf($LANG_ADVT['image_max'], $img_max),
            'type'          => $this->isSubmission() ? 'submission' : 'prod',
            'action_url'    => $action_url,
            'max_file_size' => $_CONF['max_image_size'],
            'subject'       => $this->subject,
            'price'         => $this->price,
            'url'           => $this->url,
            'keywords'      => $this->keywords,
            'exp_date'      => $exp_date->format($_CONF['daytime']),
            'add_date'      => $add_date->format($_CONF['daytime']),
            'ad_type_selection' => adType::makeSelection($this->ad_type),
            'sel_list_catid'    => adCategory::buildSelection($this->cat_id),
            'saveoption'    => $saveoption,
            'cancel_url'    => $cancel_url,
            'lang_runfor'   => $this->isNew ? $_LANG_ADVT['runfor'] :
                                    $LANG_ADVT['add'],
            'moredays'      => $moredays,
            'cls_exp_date'  => $this->exp_date < time() ? 'adExpiredText' : '',
            'ownerselect'   => self::userDropdown($this->uid),
            'uid'           => $_USER['uid'],
         ) );

        if ($this->isNew) {
            $photocount = 0;
        } else {
            // get the photo information
            $sql = "SELECT photo_id, filename
                    FROM {$_TABLES['ad_photo']}
                    WHERE ad_id='{$this->ad_id}'";
            $photo = DB_query($sql, 1);

            // save the count of photos for later use
            if ($photo)
                $photocount = DB_numRows($photo);
            else
                $photocount = 0;
        }

        $T->set_block('adedit', 'PhotoRow', 'PRow');
        $i = 0;
        if ($photocount > 0) {
            while ($prow = DB_fetchArray($photo, false)) {
                $i++;
                $T->set_var(array(
                    'img_url'   => adImage::dispUrl($prow['filename']),
                    'thumb_url' => adImage::thumbUrl($prow['filename']),
                    'seq_no'    => $i,
                    'ad_id'     => $this->ad_id,
                    'del_img_url'   => $action_url .
                        "?deleteimage={$prow['photo_id']}" .
                        "&ad_id={$this->ad_id}",
                ) );
                $T->parse('PRow', 'PhotoRow', true);
            }
        } else {
            $T->parse('PRow', '');
        }
        // add upload fields for unused images
        $T->set_block('adedit', 'UploadFld', 'UFLD');
        for ($j = $i; $j < $_CONF_ADVT['imagecount']; $j++) {
            $T->parse('UFLD', 'UploadFld', true);
        }

        $T->parse('output','adedit');
        $display = $T->finish($T->get_var('output'));
        return $display;
    }   // function Edit()


    /**
    *   Display the ad
    *
    *   @return string  HTML for the ad display
    */
    public function Detail()
    {
        global $_USER, $_TABLES, $_CONF, $LANG_ADVT, $_CONF_ADVT;

        USES_lib_comments();

        // Grab the search string directly from $_GET
        $srchval = isset($_GET['query']) ? trim($_GET['query']) : '';

        // Check access to the ad.
        if (!$this->canView()) {
            return false;
        }

        // Increment the views counter
        $this->updateHits();

        // Get the previous and next ads within the same category
        $prevAd = $this->GetNeighbor('prev');
        $nextAd = $this->GetNeighbor('next');

        // Get the user contact info. If none, just show the email link
        USES_classifieds_class_userinfo();
        $uinfo = new adUserInfo($this->uid);

        // convert line breaks & others to html
        $patterns = array(
            '/\n/',
        );
        $replacements = array(
            '<br />',
        );
        $description = PLG_replaceTags(COM_checkHTML($this->description));
        $description = preg_replace($patterns,$replacements,$description);
        $subject = strip_tags($this->subject);
        $price = strip_tags($this->price);
        $url = COM_sanitizeUrl($this->url);
        $keywords = strip_tags($this->keywords);

        // Highlight search terms, if any
        if ($srchval != '') {
            $subject = COM_highlightQuery($subject, $srchval);
            $description = COM_highlightQuery($description, $srchval);
        }

        $T = new Template($_CONF_ADVT['path'] . '/templates');
        $tpl_type = $_CONF_ADVT['_is_uikit'] ? '.uikit' : '';
        $T->set_file('detail', "detail$tpl_type.thtml");

        if ($this->isAdmin) {
            $base_url = $_CONF_ADVT['admin_url'] . '/index.php';
            $del_link = $base_url . '?deletead=x&ad_id=' . $this->ad_id;
            $edit_link = $base_url . '?editad=x&ad_id=' . $this->ad_id;
        } else {
            $base_url = $_CONF_ADVT['url'] . '/index.php';
            $del_link = $base_url . '?mode=delete&id=' . $this->ad_id;
            $edit_link = $base_url . '?mode=editad&id=' . $this->ad_id;
        }

        // Set up the "add days" form if this user is the owner
        // or an admin
        if ($this->canEdit()) {
            // How many days can be added to the ad.
            $max_add_days = $this->calcMaxAddDays();
            if ($max_add_days > 0) {
                $T->set_var('max_add_days', $max_add_days);
            }
            $have_editlink = 'true';
        } else {
            $have_editlink = '';
        }

        if ($this->exp_date < $time) {
            $T->set_var('is_expired', 'true');
        }
        $add_date = new Date($this->add_date, $_CONF['timezone']);
        $exp_date = new Date($this->exp_date, $_CONF['timezone']);
        $T->set_var(array(
            'base_url'      => $base_url,
            'edit_link'     => $edit_link,
            'del_link'      => $del_link,
            'curr_loc'      => $this->Cat->BreadCrumbs(true),
            'subject'       => $subject,
            'add_date'      => $add_date->format($_CONF['shortdate'], true),
            'exp_date'      => $exp_date->format($_CONF['shortdate'], true),
            'views_no'      => $this->views,
            'description'   => $description,
            'ad_type'       => $this->Type->description,
            'uinfo_address' => $uinfo->address,
            'uinfo_city'    => $uinfo->city,
            'uinfo_state'   => $uinfo->state,
            'uinfo_postcode' => $uinfo->postcode,
            'uinfo_tel'     => $uinfo->tel,
            'uinfo_fax'     => $uinfo->fax,
            'price'         => $price,
            'ad_id'         => $this->ad_id,
            'ad_url'        => $url,
            'username'      => $_CONF_ADVT['disp_fullname'] == 1 ?
                COM_getDisplayName($this->uid) :
                DB_getItem($_TABLES['users'], 'username', "uid={$this->uid}"),
            'fgcolor'       => $this->fgcolor,
            'bgcolor'       => $this->bgcolor,
            'cat_id'        => $this->cat_id,
            'have_editlink' => $have_editlink,
            'have_userlinks' => 'true',
        ) );

        // Display a link to email the poster, or other message as needed
        $emailfromuser = DB_getItem($_TABLES['userprefs'],
                            'emailfromuser',
                            "uid={$this->uid}");
        if (
            ($_CONF['emailuserloginrequired'] == 1 && COM_isAnonUser()) ||
            $emailfromuser < 1
        ) {
            $T->set_var('ad_uid', '');
        } else {
            $T->set_var('ad_uid', $this->uid);
        }

        $photos = adImage::getAll($this->ad_id);
        foreach ($photos as $img_id=>$filename) {
            $img_small = adImage::smallUrl($filename);
            if (!empty($img_small)) {
                $T->set_block('detail', 'PhotoBlock', 'PBlock');
                $T->set_var(array(
                    'tn_width'  => $_CONF_ADVT['detail_img_width'],
                    'small_url' => $img_small,
                    'disp_url' => adImage::dispUrl($filename),
                ) );
                $T->parse('PBlock', 'PhotoBlock', true);
                $T->set_var('have_photo', 'true');
            }
        }

        if (DB_count($_TABLES['ad_ads'], 'uid', $this->uid) > 1) {
            $T->set_var('byposter_url',
                $_CONF_ADVT['url'] . '/index.php?' .
                "mode=byposter&uid={$this->uid}");
        }

        // Show previous and next ads
        if ($prevAd != '') {
            $T->set_var('previous',
                '<a href="' . CLASSIFIEDS_makeURL('detail', $prevAd) .
                "\">&lt;&lt;</a>");
        }
        if ($nextAd != '') {
            $T->set_var('next',
                '<a href="' . CLASSIFIEDS_makeURL('detail', $nextAd) .
                "\">  &gt;&gt;</a>");
        }

        // Show the "hot results"
        $hot_data = '';
        $hot_ads = self::GetHotAds();
        if (!empty($hot_ads)) {
            $T->set_block('detail', 'HotBlock', 'HBlock');
            foreach ($hot_ads as $hotrow) {
                $T->set_var(array(
                    'hot_title' => $hotrow['subject'],
                    'hot_url'   => CLASSIFIEDS_makeURL('detail', $hotrow['ad_id']),
                    'hot_cat_url' => CLASSIFIEDS_makeURL('home', $hotrow['cat_id']),
                    'hot_cat'   => $hotrow['cat_name'],
                ) );
                $T->parse('HBlock', 'HotBlock', true);
            }
        }
        $T->set_var('whats_hot_row', $hot_data);

        // Show the user comments
        if (plugin_commentsupport_classifieds() && $this->comments_enabled < 2) {
            $T->set_var('usercomments',
                CMT_userComments($this->ad_id, $this->subject, 'classifieds', '',
                    '', 0, 1, false, false, $this->comments_enabled));
        }

        $T->parse('output','detail');
        $display = $T->finish($T->get_var('output'));
        return $display;
    }   // Detail()


    /**
    *   Sets the "enabled" field to the specified value.
    *
    *   @param  integer $newval New value to set (1 or 0)
    *   @param  integer $id     ID number of element to modify
    *   @return integer New value (old value if failed)
    */
    public function toggleEnabled($newval, $id=0)
    {
        global $_TABLES;

        if ($id == 0) {
            if (is_object($this))
                $id = $this->ad_id;
            else
                return;
        }

        $id = (int)$id;
        $newval = $newval == 1 ? 1 : 0;

        $sql = "UPDATE {$_TABLES['ad_types']}
            SET enabled=$newval
            WHERE id=$id";
        //echo $sql;die;
        DB_query($sql, 1);
        if (DB_error()) {
            $retval = $newval == 1 ? 0 : 1;
        } else {
            $retval = $newval;
        }
        return $retval;
    }


    /**
    *   Public access to set the table used for saving/reading
    *   Called from savesubmission in functions.inc
    */
    public function setTable($table)
    {
        global $_TABLES;
        $this->table = $_TABLES[$table];
    }


    /**
    *   Helper function to check if the current object is a submission.
    *
    *   @return boolean     True if this is a submission, False if it is prod
    */
    public function isSubmission()
    {
        global $_TABLES;
        return $this->table == $_TABLES['ad_submission'] ? true : false;
    }


    /**
    *   Calculate the new number of days. For an existing ad start from the
    *   date added, if new then start from now.  If the ad has already expired,
    *   then $moredays will be added to now() rather than exp_date.
    */
    private function _calcExpDate($moredays = 0)
    {
        global $_CONF_ADVT;

        if ($moredays < 1) {
            return;
        }

        $moretime = $moredays * 86400;
        $save_exp_date = $this->exp_date;
        $basetime = $this->exp_date < time() ? time() : $this->exp_date;
        $this->exp_date = min(
            $basetime + $moretime,
            $this->add_date + ((int)$_CONF_ADVT['max_total_duration'] * 86400)
        );

        // Figure out the number of days added to this ad, and subtract
        // it from the user's account.
        $days_used = (int)(($this->exp_date - $save_exp_date) / 86400);
        if ($_CONF_ADVT['purchase_enabled'] && !$this->isAdmin) {
            $User->UpdateDaysBalance($days_used * -1);
        }

        // Reset the "expiration notice sent" flag if the new date
        // is at least one more day from the old one.
        if ($days_used > 0) {
            $this->exp_sent = 0;
        }
    }


    /**
    *   Return the max number of days that may be added to an ad.
    *   Considers the configured maximum runtime and the time the ad
    *   has already run.
    *
    *   @return integer     Max number of days that can be added
    */
    public function calcMaxAddDays()
    {
        global $_CONF_ADVT;

        // How many days has the ad run?
        $run_days = ($this->exp_date - $this->add_date)/86400;
        if ($run_days < 0) $rundays = 0;

        $max_add_days = intval($_CONF_ADVT['max_total_duration']);
        if ($max_add_days < $run_days)
            return 0;
        else
            return ($max_add_days - $run_days);
    }


    /**
    *  Returns the <option></option> portion to be used
    *  within a <select></select> block to choose users from a dropdown list
    *  @param  string  $sel    ID of selected value
    *  @return string          HTML output containing options
    */
    public static function userDropdown($selId = '')
    {
        global $_TABLES;

        $retval = '';

        // Find users, excluding anonymous
        $sql = "SELECT uid FROM {$_TABLES['users']}
            WHERE uid > 1";
        $result = DB_query($sql, 1);
        while ($row = DB_fetchArray($result, false)) {
            $name = COM_getDisplayName($row['uid']);
            $sel = $row['uid'] == $selId ? 'selected' : '';
            $retval .= "<option value=\"{$row['uid']}\" $sel>$name</option>\n";
        }

        return $retval;

    }   // function userDropdown()


    /**
    *   Update the comment counter
    */
    public function updateComments()
    {
        $sql = "UPDATE {$this->table}
                SET comments = comments + 1
                WHERE ad_id='" . $this->ad_id . "'";
        DB_query($sql, 1);
    }


    /**
    *   Update the ad hit counter
    */
    public function updateHits()
    {
        // Increment the views counter
        $sql = "UPDATE {$this->table}
                SET views = views + 1
                WHERE ad_id='" . $this->ad_id . "'";
        DB_query($sql, 1);
    }


    /**
    *   Updates the ad with a new expiration date.  $days (in seconds)
    *   is added to the original expiration date.
    *
    *   @param  integer  $days   Number of days to add
    *   @return integer     New maximum
    */
    public function addDays($days = 0)
    {
        global $_USER, $_CONF, $_CONF_ADVT, $_TABLES;

        $days = (int)$days;
	$max_days = $this->calcMaxAddDays();

        if ($days == 0) return $max_days;
        if (!$this->canEdit()) return $max_days;

        $add_days = min($max_days, $days);
        if ($add_days <= 0) return 0;

        $new_exp_date = $this->exp_date + ($add_days * 86400);

        // Finally, we have access to this add and there's a valid number
        // of days to add.
        DB_query("UPDATE {$_TABLES['ad_ads']} SET
                exp_date=$new_exp_date,
                exp_sent=0
            WHERE ad_id='$this->ad_id'");
        return $max_days - $add_days;
    }


    /**
    *   Check if the current user can edit this ad.
    *
    *   @return boolean     True if access allows edit, False if not
    */
    public function canEdit()
    {
        global $_CONF_ADVT, $_USER;
        if ($this->isAdmin ||
            ($this->uid == $_USER['uid'] &&
            $_CONF_ADVT['usercanedit'] == 1) ) {
            return true;
        }
        return false;
    }


    /**
    *   Check if the current user can view this ad.
    *   Users can always view their own ads, and those under categories
    *   to which they have read access
    *
    *   @uses   adCategory::canView()
    *   @return boolean     True if access allows viewing, False if not
    */
    public function canView()
    {
        global $_USER;

        if ($this->uid == $_USER['uid']) {
            return true;
        } else {
            return $this->Cat->canView();
        }
    }


    /**
    *   Get an array of popular ads.
    *   Returns up to the top X ads (default 4)
    *
    *   @param  int     $num    Max number of ads to get
    *   @return array       Array of ad details
    */
    public static function GetHotAds($num = 4)
    {
        global $_TABLES, $_USER;

        $retval = array();
        $num = (int)$num;
        $time = time();

        // Get the hot results (most viewed ads)
        $sql = "SELECT ad.ad_id, ad.cat_id, ad.subject,
                    cat.cat_id, cat.fgcolor, cat.bgcolor,
                    cat.cat_name
            FROM {$_TABLES['ad_ads']} ad
            LEFT JOIN {$_TABLES['ad_category']} cat
                ON ad.cat_id = cat.cat_id
            WHERE ad.exp_date > $time
                AND ad.views > 2 " .
                COM_getPermSQL('AND', 0, 2, 'cat') . "
            ORDER BY ad.views DESC
            LIMIT $num";
        //echo $sql;die;
        $res = DB_query($sql);
        while ($hotrow = DB_fetchArray($res, false)) {
            $retval[] = $hotrow;
        }
        return $retval;
    }


    /**
    *   Get the ad immediately next to this ad within the same category.
    *
    *   @param  string  $dir    Either 'prev' or 'next'
    *   @return string      ID of neighboring ad
    */
    public function GetNeighbor($dir = 'prev')
    {
        global $_TABLES, $_USER;

        switch ($dir) {
        case 'prev':
        case 'previous':
            $sql_dir = 'DESC';
            $gt_lt = '<';
            break;
        case 'next':
            $sql_dir = 'ASC';
            $gt_lt = '>';
            break;
        }

        $ad_id = DB_escapeString($this->ad_id);

        // Get the previous and next ads within the same category
        $sql = "SELECT ad_id
            FROM {$_TABLES['ad_ads']} ad
            LEFT JOIN {$_TABLES['ad_category']} cat
                ON ad.cat_id = cat.cat_id
            WHERE ad_id $gt_lt '$ad_id' " .
                COM_getPermSQL('AND', 0, 2, 'cat') . "
                AND ad.cat_id= {$this->cat_id}
            ORDER BY ad_id $sql_dir
            LIMIT 1";
        //echo $sql;die;
        $r = DB_query($sql);
        $neighbor = DB_fetchArray($r, false);
        return empty($neighbor) ? '' : $neighbor['ad_id'];
    }

}   // class Ad

?>
