<?php
/**
*   Class for managing categories
*
*   @author     Lee Garner <lee@leegarner.com>
*   @copyright  Copyright (c) 2012 Lee Garner <lee@leegarner.com>
*   @package    classifieds
*   @version    1.0.5
*   @license    http://opensource.org/licenses/gpl-2.0.php
*               GNU Public License v2 or later
*   @filesource
*/


/**
*   Class for category objects
*/
class adCategory
{
    private $properties;
    public $isNew;
    private $imgPath;

    /**
    *   Constructor
    *
    *   @param  integer $catid  Optional category ID to load
    */
    public function __construct($catid = 0)
    {
        $catid = (int)$catid;
        $this->imgPath = $_CONF_ADVT['imgpath'] . '/cat/';
        if ($catid > 0) {
            $this->cat_id = $catid;
            if ($this->Read()) {
                $this->isNew = false;
            } else {
                $this->cat_id = 0;
                $this->isNew = true;
            }
        } else {
            $this->isNew = true;
        }
    }


    /**
    *   Magic setter function
    *   Sets a property value
    *
    *   @param  string  $key    Property name
    *   @param  mixed   $value  Property value
    */
    public function __set($key, $value)
    {
        switch ($key) {
        case 'cat_id':
        case 'papa_id':
        case 'group_id':
        case 'owner_id':
        case 'perm_owner':
        case 'perm_group':
        case 'perm_members':
        case 'perm_anon':
        case 'add_date':
            $this->properties[$key] = (int)$value;
            break;

        case 'cat_name':
        case 'description':
        case 'image':
        case 'keywords':
        case 'fgcolor':
        case 'bgcolor':
        case 'parent_map':
            $this->properties[$key] = $value;
            break;
        }
    }


    /**
    *   Magic getter function
    *   Returns the requested property's value, or NULL
    *
    *   @param  string  $key    Property Name
    *   @return mixed       Property value, or NULL if not set
    */
    public function __get($key)
    {
        if (isset($this->properties[$key]))
            return $this->properties[$key];
        else
            return NULL;
    }


    /**
    *   Sets all variables to the matching values from the provided array
    *
    *   @param  array   $A      Array of values, from DB or $_POST
    */
    public function SetVars($A, $fromDB = false)
    {
        if (!is_array($A)) return;

        $this->cat_id   = $A['cat_id'];
        $this->papa_id  = $A['papa_id'];
        $this->cat_name     = $A['cat_name'];
        $this->description = $A['description'];
        $this->add_date = $A['add_date'];
        $this->group_id = $A['group_id'];
        $this->owner_id = $A['owner_id'];
        $this->price    = $A['price'];
        $this->ad_type  = $A['ad_type'];
        $this->keywords = $A['keywords'];
        $this->image    = $A['image'];
        $this->fgcolor  = $A['fgcolor'];
        $this->bgcolor  = $A['bgcolor'];
        if ($fromDB) {      // perm values are already int
            $this->perm_owner = $A['perm_owner'];
            $this->perm_group = $A['perm_group'];
            $this->perm_members = $A['perm_members'];
            $this->perm_anon = $A['perm_anon'];
            $this->parent_map = @json_decode($A['parent_map'], true);
            if ($this->parent_map === NULL) $this->parent_map = array();
        } else {        // perm values are in arrays from form
            list($perm_owner,$perm_group,$perm_members,$perm_anon) =
                SEC_getPermissionValues($A['perm_owner'] ,$A['perm_group'],
                    $A['perm_members'] ,$A['perm_anon']);
            $this->perm_owner = $perm_owner;
            $this->perm_group = $perm_group;
            $this->perm_members = $perm_members;
            $this->perm_anon = $perm_anon;
            $this->parent_map = array();
        }
    }


    /**
    *   Read one record from the database
    *
    *   @param  integer $id     Optional ID.  Current ID is used if zero
    *   @return boolean         True on success, False on failure
    */
    public function Read($id = 0)
    {
        global $_TABLES;

        if ($id != 0) {
            if (is_object($this)) {
                $this->cat_id = $id;
            }
        }
        if ($this->cat_id == 0) return false;

        $result = DB_query("SELECT * FROM {$_TABLES['ad_category']}
                WHERE cat_id={$this->cat_id}");
        $A = DB_fetchArray($result, false);
        $this->SetVars($A, true);
        return true;
    }


    /**
    *   Save a new or updated category
    *
    *   @param  array   $A      Optional array of new values
    *   @return string      Error message, empty string on success
    */
    public function Save($A = array())
    {
        global $_TABLES, $_CONF_ADVT;

        if (!empty($A)) $this->SetVars($A);

        $time = time();

        // Handle the uploaded category image, if any.  We don't want to delete
        // the image if one isn't uploaded, we should leave it unchanged.  So
        // we'll first retrieve the existing image filename, if any.
        if (!$this->isNew) {
            $img_filename = DB_getItem($_TABLES['ad_category'], 'image',
                "cat_id={$this->cat_id}");
        } else {
            $img_filename = '';
        }
        if (is_uploaded_file($_FILES['imagefile']['tmp_name'])) {
            $img_filename = $time . "_" . rand(1,100) . "_" .
                $_FILES['imagefile']['name'];
            if (!@move_uploaded_file($_FILES['imagefile']['tmp_name'],
                $this->imgPath . $img_filename)) {
                $retval .= CLASSIFIEDS_errorMsg("Error Moving Image", 'alert');
            }

            // If a new image was uploaded, and this is an existing category,
            // then delete the old image, if any.  The DB still has the old filename
            // at this point.
            if (!$this->isNew) {
                self::DelImage($catid);
            }
        }

        $parent_map = DB_escapeString(json_encode($this->MakeBreadcrumbs()));
        if ($this->isNew) {
            $sql1 = "INSERT INTO {$_TABLES['ad_category']} SET ";
            $sql3 = '';
        } else {
            $sql1 = "UPDATE {$_TABLES['ad_category']} SET ";
            $sql3 = " WHERE cat_id = {$this->cat_id}";
        }

        $sql2 = "cat_name = '" . DB_escapeString($this->cat_name) . "',
            papa_id = {$this->papa_id},
            keywords = '{$this->keywords}',
            image = '$img_filename',
            description = '" . DB_escapeString($this->description) . "',
            owner_id = {$this->owner_id},
            group_id = {$this->group_id},
            perm_owner = {$this->perm_owner},
            perm_group = {$this->perm_group},
            perm_members = {$this->perm_members},
            perm_anon = {$this->perm_anon},
            fgcolor = '{$this->fgcolor}',
            bgcolor = '{$this->bgcolor}',
            parent_map = '$parent_map'";
        $sql = $sql1 . $sql2 . $sql3;

        // Propagate the permissions, if requested
        if (isset($_POST['propagate'])) {
            $this->propagatePerms();
        }

        $result = DB_query($sql);
        if (!$result)
            return CLASSIFIEDS_errorMsg($LANG_ADVT['database_error'], 'alert');
        else
            return '';      // no actual return if this function works ok
    }


    /**
    *   Deletes all checked categories.
    *   Calls catDelete() to perform the actual deletion
    *
    *   @param  array   $var    Form variable containing array of IDs
    *   @return string  Error message, if any
    */
    public static function DeleteMulti($var)
    {
        $display = '';

        foreach ($var as $catid) {
            if (!self::Delete($catid)) {
                $display .= "Error deleting category {$catid}<br />";
            }
        }
        return $display;
    }


    /**
    *  Delete a category, and all sub-categories, and all ads
    *
    *  @param  integer  $id     Category ID to delete
    *  @return boolean          True on success, False on failure
    */
    public static function Delete($id)
    {
        global $_TABLES, $_CONF_ADVT;

        $id = (int)$id;
        // find all sub-categories of this one and delete them.
        $sql = "SELECT cat_id FROM {$_TABLES['ad_category']}
                WHERE papa_id=$id";
        $result = DB_query($sql);

        if ($result) {
            while ($row = DB_fetcharray($result)) {
                if (!adCategory::Delete($row['cat_id']))
                    return false;
            }
        }

        // now delete any ads associated with this category
        $sql = "SELECT ad_id FROM {$_TABLES['ad_ads']}
             WHERE cat_id=$id";
        $result = DB_query($sql);

        if ($result) {
            while ($row = DB_fetcharray($result)) {
                if (adDelete($row['ad_id'], true) != 0) {
                    return false;
                }
            }
        }

        // Delete this category
        // First, see if there's an image to delete
        $img_name = DB_getItem($_TABLES['ad_category'], 'image', "cat_id=$id");
        if ($img_name != '' && file_exists($this->imgPath . $img_name)) {
            unlink($this->imgPath . $img_name);
        }
        DB_delete($_TABLES['ad_category'], 'cat_id', $id);

        // If we made it this far, must have worked ok
        return true;
    }


    /**
    *   Delete a single category's icon
    *   Deletes the icon from the filesystem, and updates the category table
    *
    *   @param  integer $cat_id     Category ID of image to delete
    */
    public static function DelImage($cat_id = 0)
    {
        global $_TABLES, $_CONF_ADVT;

        if ($cat_id == 0)
            return;

        $img_name = DB_getItem($_TABLES['ad_category'], 'image', "cat_id=$cat_id");
        if ($img_name != '') {
            if (file_exists($this->imgPath . $img_name)) {
                unlink($this->imgPath . $img_name);
            }
            DB_query("UPDATE {$_TABLES['ad_category']}
                SET image=''
                WHERE cat_id=$cat_id");
        }
    }


    /**
    *   Propagate a category's permissions to all sub-categories.
    *   Called by catSave() if the admin selects "Propagate Permissions".
    *   Recurses downward through the category table setting permissions of the
    *   category specified by $id.  The actual category identified by $id is not
    *   updated; that would be done in catSave().
    */
    private function propagatePerms()
    {
        $perms = array(
            'perm_owner'    => $this->perm_owner,
            'perm_group'    => $this->perm_group,
            'perm_members'  => $this->perm_members,
            'perm_anon'     => $this->perm_anon,
        );
        self::_propagatePerms($this->cat_id, $perms);
    }


    /**
    *   Recursive function to propagate permissions from a category to all
    *   sub-categories.
    *
    *   @param  integer $id     ID of top-level category
    *   @param  array   $perms  Associative array of permissions to apply
    */
    private static function _propagatePerms($id, $perms)
    {
        global $_TABLES;

        $id = (int)$id;
        // Locate the child categories of this one
        $sql = "SELECT cat_id FROM {$_TABLES['ad_category']}
                WHERE papa_id = {$id}";
        //echo $sql;die;
        $result = DB_query($sql);

        // If there are no children, just return.
        if (!$result || DB_numRows($result) < 1)
            return '';

        $cats = array();
        while ($row = DB_fetchArray($result, false)) {
            $cats[] = $A['cat_id'];
        }
        $cat_str = implode(',', $cats);

        // Update each located row
        $sql = "UPDATE {$_TABLES['ad_category']} SET
                perm_owner={$perms['perm_owner']},
                perm_group={$perms['perm_group']},
                perm_members={$perms['perm_members']},
                perm_anon={$perms['perm_anon']}
            WHERE cat_id IN ($cat_str)";
        DB_query($sql);

        // Now update the children of the current category's children
        foreach ($cats as $catid) {
            adCategory::_propagateCatPerms($catid, $perms);
        }
    }


    /**
    *   Create an edit form for a category
    *
    *   @param  int     $catid  Category ID, zero for a new entry
    *   @return string      HTML for edit form
    */
    public function Edit($cat_id = 0)
    {
        global $_CONF, $_TABLES, $LANG_ADVT, $_CONF_ADVT, $LANG_ACCESS, $_USER;

        $cat_id = (int)$cat_id;
        if ($cat_id > 0) {
            // Load the requested category
            $this->cat_id = $cat_id;
            $this->Read();
        }
        $T = new Template($_CONF_ADVT['path'] . '/templates/admin');
        $tpltype = $_CONF_ADVT['_is_uikit'] ? '.uikit' : '';
        $T->set_file('modify', "catEditForm$tpltype.thtml");

        if ($this->isNew) {
            // A new category gets default values
            $this->owner_id = $_USER['uid'];
            $this->group_id = $_CONF_ADVT['defgrpcat'];
            $this->owner_id = $_USER['uid'];
            $this->perm_owner = $_CONF_ADVT['default_perm_cat'][0];
            $this->perm_group = $_CONF_ADVT['default_perm_cat'][1];
            $this->perm_members = $_CONF_ADVT['default_perm_cat'][2];
            $this->perm_anon = $_CONF_ADVT['default_perm_cat'][3];
        }

        $T->set_var(array(
            'catname'   => $this->cat_name,
            'keywords'  => $this->keywords,
            'description' => $this->description,
            'fgcolor'   => $this->fgcolor,
            'bgcolor'   => $this->bgcolor,
            'cat_id'    => $this->cat_id,
            'cancel_url' => $_CONF_ADVT['admin_url']. '/index.php?admin=cat',
            'img_url'   => self::thumbUrl($this->image),
            'can_delete' => $this->isUsed() ? '' : 'true',
            'owner_dropdown' => COM_optionList($_TABLES['users'],
                    'uid,username', $this->owner_id, 1, 'uid > 1'),
            'group_dropdown' => SEC_getGroupDropdown($this->group_id, 3),
            'ownername' => COM_getDisplayName($_USER['uid']),
            'permissions_editor' => SEC_getPermissionsHTML($this->perm_owner,
                    $this->perm_group, $this->perm_members, $this->perm_anon),
            'sel_parent_cat' => self::buildSelection($this->papa_id, 0, '',
                    'NOT', $this->cat_id),
            'have_propagate' => $this->isNew ? '' : 'true',
        ) );
        $T->parse('output','modify');
        $display .= $T->finish($T->get_var('output'));
        return $display;

    }   // function Edit()


    /**
    *   Recurse through the category table building an option list
    *   sorted by id.
    *
    *   @param integer  $sel        Category ID to be selected in list
    *   @param integer  $papa_id    Parent category ID
    *   @param string   $char       Separator characters
    *   @param string   $not        'NOT' to exclude $items, '' to include
    *   @param string   $items      Optional comma-separated list of items to include or exclude
    *   @return string              HTML option list, without <select> tags
    */
    public static function buildSelection($sel=0, $papa_id=0, $char='',
            $not='', $items='')
    {
        global $_TABLES, $_GROUPS;

        $str = '';

        // Locate the parent category of this one, or the root categories
        // if papa_id is 0.
        $sql = "SELECT cat_id, cat_name, papa_id, owner_id, group_id,
                perm_owner, perm_group, perm_members, perm_anon
            FROM {$_TABLES['ad_category']}
            WHERE papa_id = $papa_id ";
        if (!empty($items)) {
            $sql .= " AND cat_id $not IN ($items) ";
        }
        $sql .= COM_getPermSQL('AND') .
            ' ORDER BY cat_name ASC ';
        //echo $sql;die;
        //COM_errorLog($sql);
        $result = DB_query($sql);
        // If there is no parent, just return.
        if (!$result)
            return '';

        while ($row = DB_fetchArray($result, false)) {
            $txt = $char . $row['cat_name'];
            $selected = $row['cat_id'] == $sel ? 'selected' : '';

            if ($row['papa_id'] == 0) {
                $style = 'class="adCatRoot"';
            } else {
                $style = '';
            }
            if (SEC_hasAccess($row['owner_id'], $row['group_id'],
                    $row['perm_owner'], $row['perm_group'],
                    $row['perm_members'], $row['perm_anon']) < 3) {
                $disabled = 'disabled="true"';
            } else {
                $disabled = '';
            }

            $str .= "<option value=\"{$row['cat_id']}\" $style $selected $disabled>";
            $str .= $txt;
            $str .= "</option>\n";
            $str .= self::buildSelection($sel, $row['cat_id'], $char.'-',
                    $not, $items);
        }

        //echo $str;die;
        return $str;

    }   // function buildSelection()


    /**
    *   Get the breadcrumbs for a catetory
    *   Static function that can be passed a parent map or NULL
    *   if a map hasn't been created yet.
    *
    *   @param  mixed   $map    Parent map array or NULL
    *   @param  boolean $showlink   True to add links, False for text only
    *   @return string          Breadcrumbs
    */
    public static function showBreadCrumbs($map, $showlink=true)
    {
        // If $map is not an array, assume it's a json string
        if (!is_array($map)) {
            $map = json_decode($map, true);
        }
        // Invalid JSON, start with an empty array
        if (!$map) $map = array();
        $bc = array_reverse($map);
        $locations = array();
        foreach ($bc as $id => $parent) {
            if ($showlink) {
                $locations[] = '<a href="' .
                    CLASSIFIEDS_makeURL('home', $parent['cat_id']) .
                    '">' . $parent['cat_name'] . '</a>';
            } else {
                // just get the names, no links, e.g. for notifications
                $locations[] = $parent['cat_name'];
            }
        }
        return implode(' :: ', $locations);
    }


    /**
    *   Creates the breadcrumb string from the parent mapping.
    *   Acts on the current category object
    *
    *   @uses   adCategory::showBreadCrumbs()
    *   @param  boolean $showlink   Link to the categories?
    *   @param  boolean $raw        True to just get the json values
    *   @return mixed       Parent array or HTML for breadcrumbs
    */
    public function BreadCrumbs($showlink=true, $raw = false)
    {
        // There should always be at least two elements in the map.
        // The current category and "Home"
        if ($this->parent_map == array()) {
            $this->Save();      // Save will call MakeBreadcrumbs() and save
        }
        if ($raw) {
            return $this->parent_map;
        } else {
            return self::showBreadCrumbs($this->parent_map, $showlink);
        }
    }


    /**
    *   Creates the parent mapping for breadcrumbs when the category is saved
    *
    *   @return array       Array of parent category IDs and names
    */
    public function MakeBreadcrumbs()
    {
        global $_TABLES, $LANG_ADVT;

        $breadcrumbs = array();

        $breadcrumbs[] = array(
            'cat_id' => $this->cat_id,
            'cat_name' => $this->cat_name,
        );
        $papa_id = $this->papa_id;
        while ($papa_id > 0) {
            $sql = "SELECT cat_id, cat_name, papa_id
                    FROM {$_TABLES['ad_category']}
                    WHERE cat_id = $papa_id";
            $res = DB_query($sql);
            $A = DB_fetchArray($res, false);
            $breadcrumbs[] = array(
                'cat_id' => $A['cat_id'],
                'cat_name' => $A['cat_name'],
            );
            $papa_id = $A['papa_id'];
        }
        $breadcrumbs[] = array(
            'cat_id' => 0,
            'cat_name' => $LANG_ADVT['home'],
        );
        return $breadcrumbs;
    }


    /**
    *   Calls itself recursively to find all sub-categories.
    *   Stores an array of category information in $subcats.
    *
    *   @param  integer $id         Current Category ID
    *   @param  integer $master_id  ID of top-level category being searched
    *   @return string              HTML for breadcrumbs
    */
    public static function SubCats($id, $master_id=0)
    {
        global $_TABLES, $LANG_ADVT;
        static $subcats = array();

        $id = (int)$id;
        if ($id == 0) return array();   // must have a valid category ID

        // On the initial call, $master_id is normally blank so set it to
        // the current $id. For recursive calls, $master_id will be provided.
        $master_id = (int)$master_id;
        if ($master_id == 0) $master_id = $id;

        if (isset($subcats[$id])) {
            return $subcats[$id];
        } else {
            $subcats[$id] = array();
        }

        $sql = "SELECT cat_name, cat_id, fgcolor, bgcolor, papa_id
                FROM {$_TABLES['ad_category']}
                WHERE papa_id=$id";
        //echo $sql;die;
        $result = DB_query($sql);
        if (!$result)
            return CLASSIFIEDS_errorMsg($LANG_ADVT['database_error'], 'alert');

        while ($row = DB_fetchArray($result, false)) {
            $subcats[$master_id][$row['cat_id']] = $row;
            $subcats[$id][$row['cat_id']]['total_ads'] =
                    adCategory::TotalAds($row['cat_id']);

            $A = adCategory::SubCats($row['cat_id'], $master_id);
            if (!empty($A)) {
                array_merge($subcats[$id], $A);
            }
        }

        return $subcats[$master_id];
    }


    /**
    *   Find the total number of ads for a category, including subcategories
    *
    *   @param  integer $cat_id     CategoryID
    *   @param  boolean $current    True to count only non-expired ads
    *   @param  boolean $sub        True to count ads in sub-categories
    *   @return integer Total Ads
    */
    public static function TotalAds($cat_id, $current = true, $sub = true)
    {
        global $_TABLES;

        $time = time();     // to compare to ad expiration
        $totalAds = 0;
        $cat_id = (int)$cat_id;
        $current = $current == true ? true : false;
        $sub = $sub == true ? true : false;

        // find all the subcategories
        if ($sub) {
            $sql = "SELECT cat_id FROM {$_TABLES['ad_category']}
                    WHERE papa_id=$cat_id";
            $result = DB_query($sql);
            while ($row = DB_fetchArray($result, false)) {
                $totalAds += adCategory::TotalAds($row['cat_id'], $current, $sub);
            }
        }
        // Now check the current category
        $sql = "SELECT ad_id FROM {$_TABLES['ad_ads']}
            WHERE cat_id = $cat_id";
        if ($current) {
            $now = time();
            $sql .= " AND exp_date > $now";
        }
        $res = DB_query($sql);
        $totalAds += DB_numRows($res);
        return $totalAds;

    }   // function TotalAds()


    /**
    *   Determine if this category is associated with any ads.
    *   Check both prod and submission tables
    *
    *   @return boolean     True if any ad uses this category, False if not
    */
    public function isUsed()
    {
        global $_TABLES;

        $id = (int)$id;
        foreach (array('ad_ads', 'ad_submission') as $tbl_id) {
            if (DB_count($_TABLES[$tbl_id], 'cat_id', $this->cat_id) > 0) {
                return true;
            }
        }
        return false;
    }


    /**
    *   Check the current user's access to the current category
    *
    *   @uses   SEC_hasAccess()
    *   @param  int     $required       Minimim required access level
    *   @return boolean     True if the user meets the requirement, False if not.
    */
    public function checkAccess($required = 3)
    {
        if (SEC_hasRights($_CONF_ADVT['pi_name']. '.admin')) {
            // Admin rights trump all
            return true;
        } elseif (SEC_hasAccess($this->owner_id, $this->group_id,
                $this->perm_owner, $this->perm_group,
                $this->perm_members, $this->perm_anon) >= $required) {
            // Check category permission array
            return true;
        }
        return false;   // Has no access by default
    }


    /**
    *   Check if the current user has read-write access to this category
    *
    *   @uses   adCategory::checkAccess()
    *   @return boolean     True if the user can edit, False if not
    */
    public function canEdit()
    {
        return $this->checkAccess(3);
    }


    /**
    *   Check if the current user has read access to this category
    *
    *   @uses   adCategory::checkAccess()
    *   @return boolean     True if the user can view, False if not
    */
    public function canView()
    {
        return $this->checkAccess(2);
    }


    /**
    *   When no category is given, show a table of all categories
    *   along with the count of ads for each.
    *   Returns the results from the category
    *   list function, chosen based on the display mode
    *   @return string      HTML for category listing page
    */
    public static function userList()
    {
        global $_CONF_ADVT;
        USES_classifieds_list();

        switch ($_CONF_ADVT['catlist_dispmode']) {
        case 'blocks':
            return CLASSIFIEDS_catList_blocks();
            break;

        default:
            return CLASSIFIEDS_catList_normal();
            break;
        }
    }


    /**
    *   Subscribe the current user to a specified category's notifications.
    *
    *   @param  integer $cat    Category ID to subscribe
    *   @return boolean     True on success, False on failure
    */
    public function Subscribe($sub = true)
    {
        global $_USER, $_TABLES;

        // only registered users can subscribe, and make sure this is an
        // existing category
        if (COM_isAnonUser() || $this->cat_id < 1)
            return false;;

        $sub = $sub == true ? true : false;

        if ($sub === true) {
            DB_save($_TABLES['ad_notice'],
                'cat_id,uid',
                "{$this->cat_id},{$_USER['uid']}");
        } else {
            DB_delete($_TABLES['ad_notice'],
                array('cat_id', 'uid'),
                array($this->cat_id, $_USER['uid']));
        }
        return (DB_error()) ? false : true;
    }   // function Subscribe


    /**
    *   Returns the string corresponding to the $id parameter.
    *   Designed to be used standalone; if this is an object,
    *   we already have the description in a variable.
    *   Uses a static variable to hold the descriptions since this can be
    *   called many times for a list.
    *
    *   @param  integer $id     Database ID of the ad type
    *   @return string          Ad Type Description
    */
    public static function GetDescription($id)
    {
        global $_TABLES;
        static $desc = array();

        $id = (int)$id;
        if (!isset($desc[$id])) {
            $desc[$id] = DB_getItem($_TABLES['ad_category'], 'description', "id='$id'");
        }
        return $desc[$id];
    }


    /**
    *   Calls itself recursively to find the root category of the requested id
    *   Finally returns a string of cat_id1,cat_id2,this_cat_id
    *
    *   @param int id Category ID
    *   @return string Comma-separated category list
    */
    public static function XParentList($id=0, $str='')
    {
        global $_TABLES;

        $id = (int)$id;
        if ($id == 0)
            return $str;

        // Append our id to the string
        $str .= $id;

        // Get the papa_id of the current id
        $sql = "SELECT cat_id, papa_id
            FROM {$_TABLES['ad_category']}
            WHERE cat_id=$id";
        $result = DB_query($sql);
        if (!$result) return $str;

        $row = DB_fetchArray($result);
        // If we found a parent category, call ourself to add it
        if ((int)$row['papa_id'] > 0) {
            $str = self::ParentList($row['papa_id'], $str.',');
        }

        return trim($str, ',');

    }   // ParentList()


    /**
    *   Shortcut functions to get resized thumbnail URLs.
    *
    *   @param  string  $filename   Filename to view
    *   @return string      URL to the resized image
    */
    public static function thumbUrl($filename)
    {
        global $_CONF_ADVT;
        return LGLIB_ImageUrl($_CONF_ADVT['imgpath'] . '/cat/' . $filename,
                $_CONF_ADVT['thumb_max_size'], $_CONF_ADVT['thumb_max_size']);
    }


    /**
    *   Reset all category permissions to a single group/perm setting
    *
    *   @param  integer $gid    Group ID to set
    *   @param  array   $perms  Permissions to set
    */
    public static function ResetPerms($gid, $perms)
    {
        global $_TABLES;
        for ($i = 0; $i < 4; $i++) {
            $perms[$i] = (int)$perms[$i];
        }
        $gid = (int)$gid;
        $sql = "UPDATE {$_TABLES['ad_category']} SET
            perm_owner = {$perms[0]},
            perm_group = {$perms[1]},
            perm_members = {$perms[2]},
            perm_anon = {$perms[3]},
            group_id = $gid";
        DB_query($sql);
    }

}

?>
