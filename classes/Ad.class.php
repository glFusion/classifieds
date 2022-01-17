<?php
/**
 * Class to manage classified ads.
 *
 * @author      Lee Garner <lee@leegarner.com>
 * @copyright   Copyright (c) 2016-2020 Lee Garner <lee@leegarner.com>
 * @package     classifieds
 * @version     v1.3.1
 * @license     http://opensource.org/licenses/gpl-2.0.php
 *              GNU Public License v2 or later
 * @filesource
 */
namespace Classifieds;

/**
 * Class for ad objects
 * @package classifieds
 */
class Ad
{
    /** Ad record ID.
     * @var string */
    private $ad_id = '';

    /** Category record ID.
     * @var integer */
    private $cat_id = 0;

    /** Submitting usesr ID.
     * @var integer */
    private $uid = 0;

    /** Subject or short description.
     * @var string */
    private $subject = '';

    /** Long text description.
     * @var string */
    private $description = '';

    /** User-submitted URL.
     * @var string */
    private $url = '';

    /** View counter.
     * @var integer */
    private $views = 0;

    /** Submission date object.
     * @var object */
    private $add_date = NULL;

    /** Expiration date object.
     * @var object */
    private $exp_date = NULL;

    /** Item price, free-form text.
     * @var string */
    private $price = '';

    /** Ad type record ID.
     * @var integer */
    private $ad_type = 1;   // always have type #1

    /** Search keywords.
     * @var string */
    private $keywords = '';

    /** Flag indication expiration notice has been sent.
     * @var integer */
    private $exp_sent = 0;

    /** Number of comments submitted.
     * @var integer */
    private $comments = 0;

    /** Comments-enabled flag.
     * @var integer */
    private $comments_enabled = 0;

    /** Error string or value, to be accessible by the calling routines.
     * @var mixed */
    private $Error;

    /** Flag to indicate that this is a new record.
     * @var boolean */
    private $isNew;

    /** Related category object.
     * @var object */
    private $Cat;

    /** Ad Type object.
     * @var object */
    private $Type;

    /** Database table name, either production or submissions.
     * @var string */
    private $table;

    /** Flag to indicate administrative access.
     * @var boolean */
    private $isAdmin;

    /** Tag to be part of all cache key names.
     * @var string */
    private static $tag = 'ad_';

    /** Database field to type mappings.
     * @var array */
    private $fields = array(
        'ad_id' => 'string',
        'cat_id' => 'int',
        'uid' => 'int',
        'subject' => 'string',
        'description' => 'string',
        'url' => 'string',
        'views' => 'int',
        'add_date' => 'date',
        'exp_date' => 'date',
        'price' => 'string',
        'ad_type' => 'int',
        'keywords' => 'string',
        'exp_sent' => 'int',
        'comments' => 'int',
        'comments_enabled' => 'int',
    );


    /**
     * Constructor.
     * Reads in the specified class, if $id is set.  If $id is zero,
     * then a new entry is being created.
     *
     * @param   mixed   $id     Optional Ad ID or array of values
     * @param   string  $table  Table Name, default to production
     */
    public function __construct($id='', $table='ad_ads')
    {
        $this->setTable($table);      // default to prod table
        if ($id == '') {
            $this->isNew = true;
            $this->ad_id = '';
            $this->subject = '';
            $this->description = '';
            $this->setExpDate()
                ->setAddDate();
        } elseif (is_array($id)) {
            $this->setVars($id);
            $this->Cat = Category::getInstance($this->cat_id);
            $this->Type= AdType::getInstance($this->ad_type);
            $this->isNew = false;   // normally this comes from the DB
        } else {
            $this->ad_id = $id;
            $this->Read();
        }
        $this->isAdmin = plugin_ismoderator_classifieds() ? true : false;
    }


    /**
     * Sets all variables to the matching values from $rows.
     *
     * @param   array   $row    Array of values, from DB or $_POST
     */
    public function setVars($row)
    {
        global $_CONF;

        if (!is_array($row)) return;

        // Set the database field values
        foreach ($this->fields as $name=>$type) {
            if (isset($row[$name])) {
                switch ($type) {
                case 'date':
                    // Should be a timestamp value, create the date objects.
                    // Duplicates the add*Date functions.
                    $this->$name = new \Date($row[$name]);
                    $this->$name->setTimezone(new \DateTimeZone($_CONF['timezone']));
                    break;
                case 'int':
                    $this->$name = (int)$row[$name];
                    break;
                default:
                    $this->$name = $row[$name];
                    break;
                }
            }
        }
    }


    /**
     * Read one ad from the database and populate the local values.
     *
     * @param   integer $id     Optional Ad ID. Current ID is used if empty.
     */
    public function Read($id = '')
    {
        if ($id != '') {
            $this->ad_id = COM_sanitizeId($id, false);
        }

        $sql = "SELECT * from {$this->table} WHERE ad_id = '{$this->ad_id}'";
        $result = DB_query($sql);
        $row = DB_fetchArray($result, false);
        if ($row) {
            $this->setVars($row);
            $this->isNew = false;
            $this->Cat = new Category($this->cat_id);
            $this->Type= new AdType($this->ad_type);
        }
    }


    /**
     * Get an instance of an ad.
     * Retrieves and caches ad records.
     *
     * @param   mixed   $id     Ad ID, or array of ad values
     * @return  object      Ad object
     */
    public static function getInstance($id)
    {
        static $ads = array();
        if (!isset($ads[$id])) {
            $key = 'ad_' . $id;
            $ads[$id] = Cache::get($key);
            if ($ads[$id] === NULL) {
                $ads[$id] = new self($id);
            }
            Cache::set($key, $ads[$id], 'ads');
        }
        return $ads[$id];
    }


    /**
     * Get a random ad.
     *
     * @return  object      Ad object
     */
    public static function getRandom()
    {
        global $_TABLES;

        $sql = "SELECT ad.*
            FROM {$_TABLES['ad_ads']} ad
            LEFT JOIN {$_TABLES['ad_category']} cat
                ON cat.cat_id = ad.cat_id
            WHERE ad.exp_date > " . time() . " "
            . COM_getPermSQL('AND', 0, 2, 'cat')
            . ' ORDER BY RAND() LIMIT 1';
        //echo $sql;
        $r = DB_query($sql);
        $A = DB_fetchArray($r, false);
        if (!$A) {
            return new self;
        }
        return new self($A);
    }


    /**
     * Save the current ad record to the database.
     *
     * @param   array   $A  Optional array of values, e.g. from $_POST
     * @return  boolean     True on success, False on failure
     */
    public function Save($A = array())
    {
        global $_CONF, $_CONF_ADVT, $LANG_ADVT;

        // If an array of values is provided, set them in this object
        if (!empty($A)) {
            $this->setVars($A);
        }

        // Get the nonce to associate images
        $nonce = LGLIB_getVar($A, 'nonce');

        if ($this->isNew) {
            if (!$this->isAdmin && $_CONF_ADVT['submission']) {
                // If using the queue and not an admin, then switch
                // to the submission table for new items.
                $this->setTable('ad_submission');
            }
            // Set the date added for new records
            $this->setAddDate();
            $sql1 = "INSERT INTO {$this->table} SET ";
            $sql3 = '';
        } elseif ($this->isAdmin) {
            $sql1 = "UPDATE {$this->table} SET ";
            $sql3 = " WHERE ad_id = '{$this->ad_id}'";
        } else {
            // Regular ad owners can't edit
            return false;
        }

        $this->_calcExpDate($A['moredays']);

        // Make sure ad_id isn't empty
        $this->ad_id = COM_sanitizeId($this->ad_id, true);

        $Image = new Upload($this->ad_id);
        $Image->uploadFiles();

        $fld_array = array();
        foreach ($this->fields as $name=>$type) {
            switch ($type) {
            case 'date':
                // stored internally as date objects, save as timestamps
                $val = $this->$name->toUnix();
                break;
            case 'int':
                $val = (int)$this->$name;
                break;
            case 'string':
            default:
                // sanitize strings for DB
                $val = DB_escapeString($this->$name);
                break;
            }
            $fld_array[] = "$name = '{$val}'";
        }
        $sql2 = implode(',', $fld_array);
        $sql = $sql1 . $sql2 . $sql3;
        //echo $sql;die;
        DB_query($sql, 1);
        if (DB_error()) {
            COM_errorLog("Error executing $sql");
            COM_setMsg($LANG_ADVT['database_error'], 'error');
            return false;
        } else {
            // Category object needed for notifications, but is not yet
            // set in the current Ad object
            $this->Cat = Category::getInstance($this->cat_id);
            $this->Type = AdType::getInstance($this->ad_type);
            if ($this->isNew()) {
                if (!empty($nonce)) {
                    Image::updateAdID($nonce, $this->ad_id);
                }
                if ($this->isSubmission()) {
                    // Submission, notify moderators
                    Notify::Submission($this);
                } else {
                    // Saved directly to prod, notify subscribers
                    Notify::Subscribers($this);
                }
            }
            Cache::clear();
            // Wouldn't be normal, but if this is being saved with an
            // expiration date in the past then don't tell other plugins
            // about it.
            if ($this->exp_date->toUnix() > time()) {
                PLG_itemSaved($this->ad_id, $_CONF_ADVT['pi_name']);
            }
        }
        return true;
    }


    /**
     * Creates new ad and image records based on the current ad.
     *
     * @return string  ID of new ad
     */
    public function Duplicate()
    {
        global $_TABLES;

        // Must have an existing ad loaded
        if ($this->isNew) return NULL;

        // Grab the image records before the ad_id changes
        $photos = Image::getAll($this->ad_id);

        // Clear the ad id and save to get a new ID
        $this->ad_id = '';
        $this->isNew = true;
        if ($this->Save()) {
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
        }
        return $this->ad_id;
    }


    /**
     * Delete the current ad record from the database.
     *
     * @param   string  $ad_id  ID of ad to delete
     * @param   string  $table  Table, either submission or prod
     * @return  boolean         True on success, False on failure
     */
    public static function Delete($ad_id, $table = 'ad_ads')
    {
        global $_TABLES, $_CONF_ADVT;

        if (empty($ad_id)) return false;

        if ($table == 'ad_submission') {
            // Do the normal plugin rejection stuff
            plugin_moderationdelete_classifieds($ad_id);
        } else {
            Cache::clear();
            // Do the extra cleanup manually, delete any images
            Image::DeleteAll($ad_id);
            // Notify other plugins only if not a submission
            PLG_itemDeleted($ad_id, $_CONF_ADVT['pi_name']);
            // Delete comment subscriptions
            DB_delete(
                $_TABLES['subscriptions'],
                array('type', 'category', 'id'),
                array('comment', 'classifieds', $ad_id)
            );
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
     * Determines if the current values are valid.
     *
     * @return  boolean     True if ok, False otherwise.
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
     * Creates the edit form.
     *
     * @param   string  $id Optional Ad ID, current record used if zero
     * @return  string      HTML for edit form
     */
    public function Edit($id = '')
    {
        global $_TABLES, $_CONF, $_CONF_ADVT, $LANG_ADVT, $_USER;

        if ($id != '') $this->Read($id);

        $T = new \Template($_CONF_ADVT['path'] . '/templates');
        $T->set_file('adedit', "edit.thtml");
        if ($this->isAdmin) {
            $action_url = $_CONF_ADVT['admin_url'] . '/index.php';
            if ($this->isSubmission()) {
                $cancel_url = $_CONF['site_admin_url'] . '/moderation.php';
            } else {
                $cancel_url = $_CONF_ADVT['admin_url'] . '/index.php?adminad=x';
            }
            $del_img_url = $action_url . '?ad_id=' . $this->ad_id . '&deleteimg=';
            $can_delete = true;
        } else {
            $action_url = $_CONF_ADVT['url'] . '/index.php';
            $cancel_url = $_CONF_ADVT['url'] . '/index.php';
            $del_img_url = $action_url . '?mode=delete_img&ad_id=' . $this->ad_id .
                '&img_id=';
            $can_delete = false;
        }

        SEC_setCookie(
            $_CONF['cookie_name'].'adveditor',
            SEC_createTokenGeneral('advancededitor'),
            time() + 1200,
            $_CONF['cookie_path'],
            $_CONF['cookiedomain'],
            $_CONF['cookiesecure'],
            false
        );

        $tpl_var = $_CONF_ADVT['pi_name'] . '_entry';
        switch (PLG_getEditorType()) {
        case 'ckeditor':
            $T->set_var('show_htmleditor', true);
            PLG_requestEditor($_CONF_ADVT['pi_name'], $tpl_var, 'ckeditor_classifieds.thtml');
            PLG_templateSetVars($tpl_var, $T);
            break;
        case 'tinymce' :
            $T->set_var('show_htmleditor',true);
            PLG_requestEditor($_CONF_ADVT['pi_name'], $tpl_var, 'tinymce_classifieds.thtml');
            PLG_templateSetVars($tpl_var, $T);
            break;
        default :
            // don't support others right now
            $T->set_var('show_htmleditor', false);
            break;
        }

        if ($this->isNew) {
            $moredays = $_CONF_ADVT['default_duration'];
        } else {
            // Don't add more days automatically for each edit
            $moredays = 0;
        }

        $nonce = Upload::makeNonce();
        $maxfiles = max(0, $_CONF_ADVT['imagecount'] - Image::countByAd($this->ad_id, $nonce));
        $T->set_var(array(
            'isNew'         => $this->isNew ? 'true' : '',
            'isAdmin'       => $this->isAdmin ? 'true' : '',
            'pi_admin_url'  => $_CONF_ADVT['admin_url'],
            'ad_id'         => $this->ad_id,
            'description'   => htmlspecialchars($this->description),
            'change_editormode'     => 'onchange="change_editmode(this);"',
            'glfusionStyleBasePath' => $_CONF['site_url']. '/fckeditor',
            'gltoken_name'  => CSRF_TOKEN,
            'gltoken'       => SEC_createToken(),
            'has_delbtn'    => 'true',
            'type'          => $this->isSubmission() ? 'submission' : 'prod',
            'action_url'    => $action_url,
            'max_file_size' => $_CONF['max_image_size'],
            'subject'       => $this->subject,
            'price'         => $this->price,
            'url'           => $this->url,
            'keywords'      => $this->keywords,
            'exp_date'      => $this->exp_date->toMySQL(true),
            'add_date'      => $this->add_date->toMySQL(true),
            'ad_type_selection' => AdType::buildSelection($this->ad_type),
            'sel_list_catid'    => Category::buildSelection($this->cat_id, '', '', 'NOT', '1'),
            'cancel_url'    => $cancel_url,
            'lang_runfor'   => $this->isNew ? $LANG_ADVT['runfor'] :
                                    $LANG_ADVT['add'],
            'moredays'      => $moredays,
            'cls_exp_date'  => $this->exp_date->toUnix() < time() ? 'adExpiredText' : '',
            'ownerselect'   => self::userDropdown($this->uid),
            'uid'           => $_USER['uid'],
            'nonce'         => $nonce,
            'max_images'    => $maxfiles,
         ) );

        if (!empty($this->ad_id)) {
            $T->set_var('can_delete', $can_delete);
            // get the photo information
            $sql = "SELECT * FROM {$_TABLES['ad_photo']}
                WHERE ad_id='{$this->ad_id}'";
            $imgres = DB_query($sql, 1);
            $T->set_block('adedit', 'PhotoRow', 'PRow');
            $i = 0;
            while ($prow = DB_fetchArray($imgres, false)) {
                $Img = new Image($prow);
                if (!$Img->Exists()) {
                    $Img->Delete();
                    continue;
                }
                $i++;
                $T->set_var(array(
                    'img_url'   => Image::dispUrl($prow['filename']),
                    'thumb_url' => Image::thumbUrl($prow['filename']),
                    'seq_no'    => $i,
                    'ad_id'     => $this->ad_id,
                    'del_img_url'   => $del_img_url . $prow['photo_id'],
                    'img_id'    => $prow['photo_id'],
                ) );
                $T->parse('PRow', 'PhotoRow', true);
            }
        }

        $T->parse('output','adedit');
        $display = $T->finish($T->get_var('output'));
        return $display;
    }   // function Edit()


    /**
     * Display the ad.
     *
     * @return  string  HTML for the ad display
     */
    public function Detail()
    {
        global $_USER, $_TABLES, $_CONF, $LANG_ADVT, $_CONF_ADVT;

        //USES_lib_comments();

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
        $uinfo = new UserInfo($this->uid);

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

        $T = new \Template($_CONF_ADVT['path'] . '/templates/detail/' .
                $_CONF_ADVT['detail_tpl_ver']);
        $T->set_file('detail', "detail.thtml");

        if ($this->isAdmin) {
            $base_url = $_CONF_ADVT['admin_url'] . '/index.php';
            $del_link = $base_url . '?deletead=' . $this->ad_id;
            $edit_link = $base_url . '?editad=x&ad_id=' . $this->ad_id;
        } else {
            $base_url = $_CONF_ADVT['url'] . '/index.php';
            $del_link = $base_url . '?mode=delete&id=' . $this->ad_id;
            $edit_link = $base_url . '?mode=editad&id=' . $this->ad_id;
        }

        // Set up the "add days" form if this user is the owner or an admin
        $max_add_days = $this->calcMaxAddDays();
        if ($max_add_days > 0) {
            $T->set_var('max_add_days', $max_add_days);
        }

        if ($this->exp_date->toUnix() < time()) {
            $T->set_var('is_expired', 'true');
        }
        $T->set_var(array(
            'base_url'      => $base_url,
            'edit_link'     => $edit_link,
            'del_link'      => $del_link,
            'breadcrumbs'   => $this->Cat->BreadCrumbs(true),
            'subject'       => $subject,
            'add_date'      => $this->add_date->format($_CONF['shortdate'], true),
            'exp_date'      => $this->exp_date->format($_CONF['shortdate'], true),
            'views_no'      => $this->views,
            'description'   => $description,
            'ad_type'       => $this->Type->getDscp(),
            'uinfo_address' => $uinfo->getAddress(),
            'uinfo_city'    => $uinfo->getCity(),
            'uinfo_state'   => $uinfo->getState(),
            'uinfo_postcode' => $uinfo->getPostal(),
            'uinfo_tel'     => $uinfo->getTelephone(),
            'price'         => $price,
            'ad_id'         => $this->ad_id,
            'ad_url'        => $url,
            'username'      => $_CONF_ADVT['disp_fullname'] == 1 ?
                COM_getDisplayName($this->uid) :
                DB_getItem($_TABLES['users'], 'username', "uid={$this->uid}"),
            'cat_id'        => $this->cat_id,
            'have_editlink' => $this->isAdmin,
            'session_id'    => session_id(),
            'timthumb'  => true,
            'adblock'   => PLG_displayAdBlock('classifieds_detail', 0),
            'have_deletelink' => $this->canDelete(),
            'can_subscribe' => !COM_isAnonUser(),
            'sub_icon' => $this->Cat->getSubIcon(),
        ) );

        // Display a link to email the poster, or other message as needed
        $emailfromuser = DB_getItem(
            $_TABLES['userprefs'],
            'emailfromuser',
            "uid={$this->uid}"
        );
        if (
            ($_CONF['emailuserloginrequired'] == 1 && COM_isAnonUser()) ||
            $emailfromuser < 1
        ) {
            $T->set_var('ad_uid', '');
        } else {
            $T->set_var('ad_uid', $this->uid);
        }

        $photos = Image::getAll($this->ad_id);
        $main_img = '';
        $main_imgname = '';
        $T->set_block('detail', 'PhotoBlock', 'PBlock');
        foreach ($photos as $img_id=>$filename) {
            $img_small = Image::smallUrl($filename);
            if ($main_img == '') {
                $main_img = Image::dispUrl($filename);
                $main_imgname = 'user/' . $filename;
            }
            if (!empty($img_small)) {
                $T->set_var(array(
                    'tn_width'  => $_CONF_ADVT['detail_img_width'] + 5,
                    'small_url' => $img_small,
                    'disp_url' => Image::dispUrl($filename),
                    'small_imgname' => 'user/' . $filename,
                ) );
                $T->parse('PBlock', 'PhotoBlock', true);
                $T->set_var('have_photo', 'true');
            }
        }
        $T->set_var('main_img', $main_img);
        $T->set_var('main_imgname', $main_imgname);

        if (DB_count($_TABLES['ad_ads'], 'uid', $this->uid) > 1) {
            $T->set_var(
                'byposter_url',
                $_CONF_ADVT['url'] . '/index.php?uid=' . $this->uid
            );
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
        $hot_ads = self::getHotAds();
        $hot_count = 0;
        if (!empty($hot_ads)) {
            $T->set_var('hot_count', count($hot_ads));
            $T->set_block('detail', 'HotBlock', 'HBlock');
            foreach ($hot_ads as $hotrow) {
                if ($hotrow->getID() == $this->ad_id) {
                    continue;
                }
                $hot_count++;
                $T->set_var(array(
                    'hot_title' => $hotrow->getSubject(),
                    'hot_url'   => CLASSIFIEDS_makeURL('detail', $hotrow->getID()),
                    'hot_cat_url' => CLASSIFIEDS_makeURL('home', $hotrow->getCatID()),
                    'hot_cat'   => $hotrow->getCat()->getName(),
                ) );
                $T->parse('HBlock', 'HotBlock', true);
            }
            $T->set_var('hot_count', $hot_count);
        }

        // Show the user comments
        if (plugin_commentsupport_classifieds() && $this->comments_enabled < 2) {
            USES_lib_comments();
            $T->set_var(
                'usercomments',
                CMT_userComments(
                    $this->ad_id, $this->subject, 'classifieds', '',
                    '', 0, 1, false, false, $this->comments_enabled
                )
            );
        }

        $T->parse('output','detail');
        $display = $T->finish($T->get_var('output'));
        return $display;
    }   // Detail()


    /**
     * Public access to set the table used for saving/reading.
     * Called from savesubmission in functions.inc.
     *
     * @param   string  $table  Table name
     */
    public function setTable($table)
    {
        global $_TABLES;
        $this->table = $_TABLES[$table];
        return $this;
    }


    /**
     * Helper function to check if the current object is a submission.
     *
     * @return  boolean     True if this is a submission, False if it is prod
     */
    public function isSubmission()
    {
        global $_TABLES;
        return $this->table == $_TABLES['ad_submission'] ? true : false;
    }


    /**
     * Calculate the expiration date when a number of days is added.
     * If the ad has already expired, then $moredays will be added
     * to now() rather than exp_date.
     *
     * @param   integer $moredays   Number of days to add
     * @return  void    No return, the exp_date property is updated
     */
    private function _calcExpDate($moredays = 0)
    {
        global $_CONF_ADVT;

        if ($moredays < 1) {
            return;
        }

        $exp_ts = $this->exp_date->toUnix();
        $save_ts = $exp_ts;
        $moretime = $moredays * 86400;
        $basetime = $exp_ts < time() ? time() : $exp_ts;
        $this->setExpDate(min(
            $basetime + $moretime,
            $this->add_date->toUnix() + ((int)$_CONF_ADVT['max_total_duration'] * 86400)
        ) );

        // Use the new expiration timestamp value.
        $exp_ts = $this->exp_date->toUnix();

        // Figure out the number of days added to this ad, and subtract
        // it from the user's account.
        $days_used = (int)(($exp_ts - $save_ts) / 86400);
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
     * Return the max number of days that may be added to an ad.
     * Considers the configured maximum runtime and the time the ad
     * has already run.
     *
     * @return  integer     Max number of days that can be added
     */
    public function calcMaxAddDays()
    {
        global $_CONF_ADVT;

        // How many days has the ad run?
        $run_days = ($this->exp_date->toUnix() - $this->add_date->toUnix())/86400;
        if ($run_days < 0) $rundays = 0;

        $max_add_days = intval($_CONF_ADVT['max_total_duration']);
        if ($max_add_days < $run_days) {
            return 0;
        } else {
            return (int)($max_add_days - $run_days);
        }
    }


    /**
     * Returns the options for a selection list of users.
     *
     * @param   string  $selId  ID of selected value
     * @return  string          HTML output containing options
     */
    public static function userDropdown($selId = '')
    {
        global $_TABLES;

        $retval = '';

        // Find users, excluding anonymous
        $sql = "SELECT uid, username, fullname FROM {$_TABLES['users']}
                WHERE uid > 1";
        $result = DB_query($sql, 1);
        while ($row = DB_fetchArray($result, false)) {
            $name = COM_getDisplayName($row['uid'], $row['username'], $row['fullname']);
            $sel = $row['uid'] == $selId ? 'selected="selected"' : '';
            $retval .= "<option value=\"{$row['uid']}\" $sel>$name</option>" . LB;
        }
        return $retval;
    }   // function userDropdown()


    /**
     * Update the comment counter when a comment is added.
     */
    public function updateComments()
    {
        $sql = "UPDATE {$this->table}
                SET comments = comments + 1
                WHERE ad_id='" . $this->ad_id . "'";
        DB_query($sql, 1);
    }


    /**
     * Update the ad hit counter when the ad is viewed.
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
     * Updates the ad with a new expiration date.
     * $days (in seconds) is added to the original expiration date.
     *
     * @param   integer  $days   Number of days to add
     * @return  integer     New maximum
     */
    public function addDays($days = 0)
    {
        global $_USER, $_CONF, $_CONF_ADVT, $_TABLES;

        $req_days = (int)$days;
        $max_days = $this->calcMaxAddDays();

        if (
            $req_days < 1 ||
            ( !$_USER['uid'] != $this->uid && !$this->isAdmin )
        ) {
            return $max_days;
        }

        $add_days = min($max_days, $req_days);
        if ($add_days <= 0) {
            return 0;       // can't add any more days
        }

        // Finally, we have access to this add and there's a valid number
        // of days to add.
        $this->setExpDate($this->exp_date->toUnix() + ($add_days * 86400));
        DB_query(
            "UPDATE {$_TABLES['ad_ads']} SET
            exp_date = {$this->exp_date->toUnix()},
            exp_sent=0
            WHERE ad_id='$this->ad_id'"
        );
        Cache::clear();
        return $max_days - $add_days;
    }


    /**
     * Check if the current user can delete this ad.
     *
     * @return  boolean     True if access allows deletion, False if not
     */
    public function canDelete()
    {
        global $_USER;
        if ($this->isAdmin || $this->uid == $_USER['uid']) {
            return true;
        } else {
            return false;
        }
    }


    /**
     * Check if the current user can view this ad.
     * Users can always view their own ads, and those under categories
     * to which they have read access
     *
     * @uses    Category::canView()
     * @return  boolean     True if access allows viewing, False if not
     */
    public function canView()
    {
        global $_USER;

        if ($this->uid == $_USER['uid']) {
            return true;
        } elseif ($this->Cat) {
            return $this->Cat->canView();
        } else {
            return true;
        }
    }


    /**
     * Get an array of popular ads.
     * Returns up to the top X ads (default 4).
     * Requires at least 2 views to be considered.
     *
     * @param   integer $num    Max number of ads to get
     * @return  array           Array of Ad objects
     */
    public static function getHotAds($num = 4)
    {
        global $_TABLES;

        $retval = array();
        $num = (int)max($num, 1);
        $time = time();

        // Get the hot results (most viewed ads)
        $sql = "SELECT ad.* FROM {$_TABLES['ad_ads']} ad
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
            $retval[] = new self($hotrow);
        }
        return $retval;
    }


    /**
     * Get the ad immediately next to this ad within the same category.
     *
     * @param   string  $dir    Either 'prev' or 'next'
     * @return  string      ID of neighboring ad
     */
    public function GetNeighbor($dir = 'prev')
    {
        global $_TABLES;

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


    /**
     * Update the date that the ad was added.
     * Used to change the date upon approval, so the add will run the
     * entire allowed number of days.
     *
     * @param   integer $id     ID of ad to update
     * @param   integer $ts     Unix timestamp to set, default is time()
     */
    public static function updateAddDate($id, $ts = NULL)
    {
        global $_TABLES;

        if (!$ts) $ts = time();
        DB_change($_TABLES['ad_ads'], 'add_date', (int)$ts, 'ad_id', $id);
    }


    /**
     * Check if this is a new record.
     *
     * @return  integer     1 if new, 0 if existing
     */
    public function isNew()
    {
        return $this->isNew ? 1 : 0;
    }


    /**
     * Get the submitting user's ID.
     *
     * @return  integer     User ID
     */
    public function getUid()
    {
        return (int)$this->uid;
    }


    /**
     * Set the category ID for this ad.
     *
     * @param   integer $id     Category ID
     * @return  object  $this
     */
    public function setCatID($id)
    {
        $this->cat_id = (int)$id;
        return $this;
    }


    /**
     * Get the category ID for this ad.
     *
     * @return  integer     Category record ID
     */
    public function getCatID()
    {
        return (int)$this->cat_id;
    }


    /**
     * Get the ad's DB record ID.
     *
     * @return  string      Record ID
     */
    public function getID()
    {
        return $this->ad_id;
    }


    /**
     * Get the subject (short description).
     *
     * @return  string      Subject text
     */
    public function getSubject()
    {
        return $this->subject;
    }


    /**
     * Get the full description.
     *
     * @return  string      Description text
     */
    public function getDscp()
    {
        return $this->description;
    }


    /**
     * Get the price. This is free-form text.
     *
     * @return  text        Price
     */
    public function getPrice()
    {
        return $this->price;
    }


    /**
     * Get the related category object.
     *
     * @return  object      Category object
     */
    public function getCat()
    {
        return $this->Cat;
    }


    /**
     * Get the add type - For Sale, Wanted, etc.
     *
     * @return  object      AdType object
     */
    public function getType()
    {
        return $this->Type;
    }


    /**
     * Set the submission date based on a timestamp.
     *
     * @param   integer $ts     Timestamp or date/time string
     * @return  object  $this
     */
    private function setAddDate($ts=NULL)
    {
        global $_CONF;

        if ($ts === NULL) {
            $ts = time();
        }
        $this->add_date = new \Date($ts);
        $this->add_date->setTimezone(new \DateTimeZone($_CONF['timezone']));
        return $this;
    }


    /**
     * Get the submission date object.
     *
     * @return  object      Submission date object
     */
    public function getAddDate()
    {
        return $this->add_date;
    }


    /**
     * Set the expiration date based on a timestamp.
     *
     * @param   integer $ts     Timestamp or date/time string
     * @return  object  $this
     */
    private function setExpDate($ts=NULL)
    {
        global $_CONF;

        if ($ts === NULL) {
            $ts = time();
        }
        $this->exp_date = new \Date($ts);
        $this->exp_date->setTimezone(new \DateTimeZone($_CONF['timezone']));
        return $this;
    }


    /**
     * Get the expiration date object.
     *
     * @return  object      Expiration date object
     */
    public function getExpDate()
    {
        return $this->exp_date;
    }


    /**
     * Set the `isNew` flag.
     * This is used to force saving a new ad when copying.
     *
     * @param   boolean $flag       True if new, False if not
     * @return  object  $this
     */
    public function setIsNew($flag)
    {
        $this->isNew = $flag ? 1 : 0;
        return $this;
    }


    /**
     * Uses lib-admin to list the forms definitions and allow updating.
     *
     * @return  string  HTML for the list
     */
    public static function adminList()
    {
        global $_CONF, $_CONF_ADVT, $_TABLES, $LANG_ADMIN, $LANG_ADVT;

        USES_lib_admin();
        $retval = '';

        $header_arr = array(
            array(
                'text' => $LANG_ADMIN['edit'],
                'field' => 'edit',
                'sort' => false,
                'align' => 'center',
            ),
            array(
                'text' => $LANG_ADVT['duplicate'],
                'field' => 'copy',
                'sort' => false,
                'align' => 'center',
            ),
            array(
                'text' => $LANG_ADVT['added_on'],
                'field' => 'add_date',
                'sort' => true,
            ),
            array(
                'text' => $LANG_ADVT['expires'],
                'field' => 'exp_date',
                'sort' => true,
            ),
            array(
                'text' => $LANG_ADVT['subject'],
                'field' => 'subject',
                'sort' => true,
            ),
            array(
                'text' => $LANG_ADVT['owner'],
                'field' => 'owner_id',
                'sort' => true,
            ),
            array(
                'text' => $LANG_ADVT['delete'],
                'field' => 'delete',
                'sort' => false,
                'align' => 'center',
            ),
        );
        $defsort_arr = array(
            'field' => 'add_date',
            'direction' => 'ASC',
        );
        $text_arr = array(
            'has_extras' => true,
            'form_url' => $_CONF_ADVT['admin_url'] . '/index.php',
        );
        $options = array(
            'chkdelete' => true,
            'chkfield' => 'ad_id',
        );
        $query_arr = array(
            'table' => 'ad_ads',
            'sql' => "SELECT * FROM {$_TABLES['ad_ads']}",
            'query_fields' => array('subject', 'description', 'keywords'),
            'default_filter' => 'WHERE 1=1'
        );
        $form_arr = array();
        $retval .= COM_createLink(
            $LANG_ADVT['mnu_submit'],
            Menu::getSubmitUrl(),
            array(
                'class' => 'uk-button uk-button-success',
                'style' => 'float:left',
            )
        );
        $retval .= ADMIN_list(
            'classifieds_adlist',
            array(__CLASS__, 'getListField'),
            $header_arr,
            $text_arr, $query_arr, $defsort_arr, '', '', $options, $form_arr
        );
        return $retval;
    }


    /**
     * Create list of Ads for a user to manage.
     *
     * @return  string  HTML for admin list
     */
    public static function userList()
    {
        global $_CONF, $_TABLES, $LANG_ADMIN, $LANG_ACCESS, $_CONF_ADVT,
            $LANG_ADVT, $_USER;

        $retval = '';

        $header_arr = array(
            array(
                'text'  => $LANG_ADVT['edit'],
                'field' => 'user_edit',
                'sort'  => false,
                'align' => 'center',
            ),
            array(
                'text'  => $LANG_ADVT['description'],
                'field' => 'subject',
                'sort'  => true,
            ),
            array(
                'text'  => $LANG_ADVT['added'],
                'field' => 'add_date',
                'sort'  => false,
                'align' => 'center',
            ),
            array(
                'text'  => $LANG_ADVT['expires'],
                'field' => 'exp_date',
                'sort'  => false,
                'align' => 'center'),
            array(
                'text'  => $LANG_ADVT['delete'],
                'field' => 'user_delete',
                'sort'  => false,
                'align' => 'center'),
        );
        $defsort_arr = array('field' => 'add_date', 'direction' => 'asc');
        $text_arr = array(
            'has_extras' => true,
            'form_url' => $_CONF_ADVT['url'] . '/index.php',
        );
        $query_arr = array(
            'table' => 'ad_ads',
            'sql' => "SELECT * FROM {$_TABLES['ad_ads']} WHERE uid = {$_USER['uid']}",
            'query_fields' => array(),
            'default_filter' => ''
        );
        $form_arr = array();
        USES_lib_admin();
        return ADMIN_list(
            'classifieds_userlist',
            array(__CLASS__, 'getListField'),
            $header_arr, $text_arr, $query_arr, $defsort_arr, '',
            '', '', $form_arr
        );
        return $retval;
    }


    /**
     * Determine what to display in the admin list for each form.
     *
     * @param   string  $fieldname  Name of the field, from database
     * @param   mixed   $fieldvalue Value of the current field
     * @param   array   $A          Array of all name/field pairs
     * @param   array   $icon_arr   Array of system icons
     * @return  string              HTML for the field cell
     */
    public static function getListField($fieldname, $fieldvalue, $A, $icon_arr)
    {
        global $_CONF, $_CONF_ADVT, $LANG_ACCESS, $LANG_ADVT;

        static $dt = NULL;
        if ($dt === NULL) {
            $dt = new \Date('now', $_CONF['timezone']);
        }

        $retval = '';

        switch($fieldname) {
        case 'edit':
            $retval = COM_createLink('',
                $_CONF_ADVT['admin_url'] .  "/index.php?editad=x&ad_id={$A['ad_id']}",
                array(
                    'class' => 'uk-icon uk-icon-edit',
                )
            );
            break;

        case 'user_edit':
            $retval = COM_createLink(
                '',
                $_CONF_ADVT['url'] .
                    "/index.php?mode=editad&amp;id={$A['ad_id']}",
                array(
                    'class' => 'uk-icon uk-icon-edit',
                    'title' => $LANG_ADVT['edit'],
                    'data-uk-tooltip' => ''
                )
            );
            break;

        case 'copy':
            $retval = COM_createLink('',
                $_CONF_ADVT['admin_url'] .  "/index.php?dupad={$A['ad_id']}",
                array(
                    'class' => 'uk-icon uk-icon-copy',
                )
            );
           break;

        case 'add_date':
        case 'exp_date':
            $dt->setTimeStamp($fieldvalue);
            $retval = $dt->toMySQL(true);
            break;

        case 'delete':
            $retval = COM_createLink('',
                $_CONF_ADVT['admin_url'] .
                    "/index.php?deletead={$A['ad_id']}",
                array(
                    'class' => 'uk-icon uk-icon-trash uk-text-danger',
                    'data-uk-tooltip' => '',
                    'title' => $LANG_ADVT['del_item'],
                    'onclick' => "return confirm('{$LANG_ADVT['confirm_delitem']}');",
                )
            );
            break;

        case 'user_delete':
            $retval = COM_createLink(
                '',
                $_CONF_ADVT['url'] .
                    "/index.php?mode=deletead&amp;id={$A['ad_id']}",
                array(
                    'title' => $LANG_ADVT['del_item'],
                    'class' => 'uk-icon uk-icon-trash uk-text-danger',
                    'data-uk-tooltip' => '',
                    'onclick' => "return confirm('{$LANG_ADVT['del_item_confirm']}');",
                )
            );
            break;

        case 'subject':
            $retval = COM_createLink(
                $fieldvalue,
                self::getDetailUrl($A['ad_id']),
                array(
                    'title' => $LANG_ADVT['view_ad'],
                    'class' => 'tooltip',
                )
            );
            break;

        case 'owner_id':
            $retval = COM_getDisplayName($A['uid']);
            break;

        default:
            $retval = $fieldvalue;
            break;
        }
        return $retval;
    }


    /**
     * Get the URL to the detail page for an ad.
     *
     * @param   string  $ad_id      ID of ad
     * @return  string      URL to detail page
     */
    public static function getDetailUrl(string $ad_id) : string
    {
        global $_CONF_ADVT;

        return COM_buildUrl($_CONF_ADVT['url'] . '/detail.php?id=' . urlencode($ad_id));
    }

}   // class Ad

