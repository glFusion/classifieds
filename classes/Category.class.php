<?php
/**
 * Class for managing ad categories
 *
 * @author      Lee Garner <lee@leegarner.com>
 * @copyright   Copyright (c) 2012-2018 Lee Garner <lee@leegarner.com>
 * @package     classifieds
 * @version     v1.4.0
 * @license     http://opensource.org/licenses/gpl-2.0.php
 *              GNU Public License v2 or later
 * @filesource
 */
namespace Classifieds;

/**
 * Class for category objects.
 */
class Category
{
    /** Internal properties accessed via `__set()` and `__get()`.
     * @var array */
    private $properties;

    /** Flag to indicate that this is a new record.
     * @var boolean */
    public $isNew;

    /** Path to category images.
     * @var string */
    private $imgPath;

    /**
     * Constructor - Load default values and read a record.
     *
     * @param   integer $catid  Optional category ID to load
     * @param   array|null  $data   Category record if already read
     */
    public function __construct($catid = 0, $data = NULL)
    {
        global $_CONF_ADVT;

        // Set default colors. Other fields can be empty
        $this->fgcolor = '#000000';
        $this->bgcolor = '#FFFFFF';
        $catid = (int)$catid;
        $this->imgPath = $_CONF_ADVT['imgpath'] . '/cat/';
        if ($catid > 0) {
            $this->cat_id = $catid;
            if ($data !== NULL) {
                $this->SetVars($data, true);
                $this->isNew = false;
            } elseif ($this->Read()) {
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
     * Sets a property value.
     *
     * @param   string  $key    Property name
     * @param   mixed   $value  Property value
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
        case 'lft':
        case 'rgt':
            $this->properties[$key] = (int)$value;
            break;

        case 'cat_name':
        case 'disp_name':
        case 'description':
        case 'image':
        case 'keywords':
        case 'fgcolor':
        case 'bgcolor':
        //case 'parent_map':
            $this->properties[$key] = $value;
            break;
        }
    }


    /**
     * Returns the requested property's value, or NULL.
     *
     * @param   string  $key    Property Name
     * @return  mixed       Property value, or NULL if not set
     */
    public function __get($key)
    {
        if (isset($this->properties[$key]))
            return $this->properties[$key];
        else
            return NULL;
    }


    /**
     * Sets all variables to the matching values from the provided array.
     *
     * @param   array   $A      Array of values, from DB or $_POST
     * @param   boolean $fromDB True if reading a DB record, False for $_POST
     */
    public function SetVars($A, $fromDB = false)
    {
        if (!is_array($A)) return;

        $this->cat_id   = $A['cat_id'];
        $this->papa_id  = $A['papa_id'];
        $this->cat_name     = $A['cat_name'];
        $this->disp_name = isset($A['disp_name']) ? $A['disp_name'] : $A['cat_name'];
        $this->description = $A['description'];
        $this->group_id = $A['group_id'];
        $this->owner_id = $A['owner_id'];
        $this->image    = $A['image'];
        $this->fgcolor  = $A['fgcolor'];
        $this->bgcolor  = $A['bgcolor'];
        if ($fromDB) {      // perm values are already int
            $this->perm_owner = $A['perm_owner'];
            $this->perm_group = $A['perm_group'];
            $this->perm_members = $A['perm_members'];
            $this->perm_anon = $A['perm_anon'];
            $this->lft = $A['lft'];
            $this->rgt = $A['rgt'];
        } else {        // perm values are in arrays from form
            list($perm_owner,$perm_group,$perm_members,$perm_anon) =
                SEC_getPermissionValues($A['perm_owner'] ,$A['perm_group'],
                    $A['perm_members'] ,$A['perm_anon']);
            $this->perm_owner = $perm_owner;
            $this->perm_group = $perm_group;
            $this->perm_members = $perm_members;
            $this->perm_anon = $perm_anon;
        }
    }


    /**
     * Read one record from the database.
     *
     * @param   integer $id     Optional ID.  Current ID is used if zero
     * @return  boolean         True on success, False on failure
     */
    public function Read($id = 0)
    {
        global $_TABLES;

        if ($id != 0) {
            $this->cat_id = $id;
        }
        if ($this->cat_id == 0) return false;

        $result = DB_query("SELECT * FROM {$_TABLES['ad_category']}
                WHERE cat_id={$this->cat_id}");
        $A = DB_fetchArray($result, false);
        $this->SetVars($A, true);
        return true;
    }


    /**
     * Save a new or updated category.
     *
     * @param   array   $A      Optional array of new values
     * @return  string      Error message, empty string on success
     */
    public function Save($A = array())
    {
        global $_TABLES, $_CONF_ADVT;

        if (!empty($A)) $this->SetVars($A);
        $time = time();

        // Handle the uploaded category image, if any.  We don't want to delete
        // the image if one isn't uploaded, we should leave it unchanged.  So
        // we'll first retrieve the existing image filename, if any.
        if (is_uploaded_file($_FILES['imagefile']['tmp_name'])) {
            $img_filename = $time . "_" . rand(1,100) . "_" .
                $_FILES['imagefile']['name'];
            $img_sql = "image = '$img_filename',";
            if (!@move_uploaded_file($_FILES['imagefile']['tmp_name'],
                $this->imgPath . $img_filename)) {
                $retval .= CLASSIFIEDS_errorMsg("Error Moving Image", 'alert');
            }

            // If a new image was uploaded, and this is an existing category,
            // then delete the old image, if any. The DB still has the old
            // filename at this point.
            if (!$this->isNew) {
                self::DelImage($this->cat_id);
            }
        } else {
            $img_filename = '';
            $img_sql = '';
        }

        //$parent_map = DB_escapeString(json_encode($this->MakeBreadcrumbs()));
        if ($this->isNew) {
            $Parent = new self($this->papa_id);
            if ($Parent->isNew) {
                return CLASSIFIEDS_errorMsg($LANG_ADVT['invalid_category'], 'alert');
            }
            $sql = "UPDATE {$_TABLES['ad_category']}
                SET rgt = rgt + 2 WHERE rgt >= {$Parent->rgt}";
            //echo $sql;die;
            DB_query($sql);
            $sql = "UPDATE {$_TABLES['ad_category']}
                SET lft = lft + 2 WHERE lft >= {$Parent->rgt}";
            //echo $sql;die;
            DB_query($sql);
            $lft = $Parent->rgt;
            $rgt = $lft + 1;
            $sql1 = "INSERT INTO {$_TABLES['ad_category']} SET
                    lft = $lft,
                    rgt = $rgt, ";
            $sql3 = '';
        } else {
            $sql1 = "UPDATE {$_TABLES['ad_category']} SET ";
            $sql3 = " WHERE cat_id = {$this->cat_id}";
        }

        $sql2 = "cat_name = '" . DB_escapeString($this->cat_name) . "',
            papa_id = {$this->papa_id},
            keywords = '{$this->keywords}',
            $img_sql
            description = '" . DB_escapeString($this->description) . "',
            owner_id = {$this->owner_id},
            group_id = {$this->group_id},
            perm_owner = {$this->perm_owner},
            perm_group = {$this->perm_group},
            perm_members = {$this->perm_members},
            perm_anon = {$this->perm_anon},
            fgcolor = '{$this->fgcolor}',
            bgcolor = '{$this->bgcolor}'";
            //parent_map = '$parent_map'";
        $sql = $sql1 . $sql2 . $sql3;
        //echo $sql;die;
        $result = DB_query($sql);
        if (!$result) {
            return CLASSIFIEDS_errorMsg($LANG_ADVT['database_error'], 'alert');
        } else {
            // When updating, check if the parent category has changed.
            // If so, rebuild the entire tree since we don't know how much
            // changed.
            if (!$this->isNew &&
                    isset($A['orig_pcat']) &&
                    $A['orig_pcat'] != $this->papa_id) {
                self::rebuildTree(1, 1);
                // Propagate the permissions, if requested
                if (isset($_POST['propagate'])) {
                    $this->Read();  // Re-read to get new left/right values
                    $this->propagatePerms();
                }
                PLG_itemSaved($this->cat_id, 'classifieds_category');
            }
            return '';      // no actual return if this function works ok
        }
    }


    /**
     * Deletes all checked categories.
     * Calls catDelete() to perform the actual deletion
     *
     * @param   array   $var    Form variable containing array of IDs
     * @return  string  Error message, if any
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
     * Delete a category, and all sub-categories, and all ads.
     *
     * @param   integer  $id     Category ID to delete
     * @return  boolean          True on success, False on failure
     */
    public static function Delete($id)
    {
        global $_TABLES, $_CONF_ADVT;

        $id = (int)$id;
        if ($id == 1) return false;     // can't delete root category
        $lft = 0;
        $rgt = 0;
        $width = 0;
        $Cats = self::getTree($id); // get all categories under this one
        if (!isset($Cats[$id])) {
            return false;           // Category not found
        } else {
            $lft = $Cats[$id]->lft;
            $rgt = $Cats[$id]->rgt;
            $width = ($rgt - $lft) + 1;
        }
        if ($lft == 0 || $rgt == 0 || $width == 0) return false;    // error
        $cat_ids = array();
        foreach ($Cats as $Cat) {
            $cat_ids[] = $Cat->cat_id;
        }
        $cat_sql = implode(',', $cat_ids);
        $sql = "SELECT ad_id FROM {$_TABLES['ad_ads']} WHERE cat_id IN ($cat_sql)";
        $res = DB_query($sql);
        // Delete ads associated with this category
        while ($A = DB_fetchArray($res, false)) {
            Ad::Delete($A['ad_id']);
        }

        DB_query("DELETE FROM {$_TABLES['ad_category']} WHERE lft BETWEEN $lft AND $rgt");
        DB_query("UPDATE {$_TABLES['ad_category']} SET rgt = rgt - $width WHERE rgt > $rgt");
        DB_query("UPDATE {$_TABLES['ad_category']} SET lft = lft - $width WHERE lft > $rgt");
        PLG_itemDeleted($id, 'classifieds_category');
        return true;
    }


    /**
     * Delete a single category's icon.
     * Deletes the icon from the filesystem, and updates the category table.
     *
     * @param   integer $cat_id     Category ID of image to delete
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
     * Propagate a category's permissions to all sub-categories.
     * Called by Save() if the admin selects "Propagate Permissions".
     * The current category is not updated; that would be done in Save().
     */
    private function propagatePerms()
    {
        global $_TABLES;

        $sql = "UPDATE {$_TABLES['ad_category']} SET
                perm_owner = {$this->perm_owner},
                perm_group = {$this->perm_group},
                perm_members = {$this->perm_members},
                perm_anon = {$this->perm_anon},
                owner_id = {$this->owner_id},
                group_id = {$this->group_id}
            WHERE lft > {$this->lft} AND rgt < {$this->rgt}";
        //echo $sql;die;
        DB_query($sql);
        /*$perms = array(
            'perm_owner'    => $this->perm_owner,
            'perm_group'    => $this->perm_group,
            'perm_members'  => $this->perm_members,
            'perm_anon'     => $this->perm_anon,
        );
        self::_propagatePerms($this->cat_id, $perms);*/
    }


    /**
     * Recursive function to propagate permissions from a category to all
     * sub-categories.
     *
     * @deprecated
     * @param   integer $id     ID of top-level category
     * @param   array   $perms  Permissions to set
     * @param   array   $perms  Associative array of permissions to apply
     */
    private static function X_propagatePerms($id, $perms)
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
            self::_propagateCatPerms($catid, $perms);
        }
    }


    /**
     * Create an edit form for a category.
     *
     * @param   integer $cat_id Category ID, zero for a new entry
     * @return  string      HTML for edit form
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
        $T = new \Template($_CONF_ADVT['path'] . '/templates/admin');
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
            'image'     => $this->image,
            'can_delete' => $this->isUsed() ? '' : 'true',
            'owner_dropdown' => COM_optionList($_TABLES['users'],
                    'uid,username', $this->owner_id, 1, 'uid > 1'),
            'group_dropdown' => SEC_getGroupDropdown($this->group_id, 3),
            'ownername' => COM_getDisplayName($_USER['uid']),
            'permissions_editor' => SEC_getPermissionsHTML($this->perm_owner,
                    $this->perm_group, $this->perm_members, $this->perm_anon),
            'sel_parent_cat' => self::buildSelection(self::getParent($this->cat_id), $this->cat_id),
            'have_propagate' => $this->isNew ? '' : 'true',
            'orig_pcat' => $this->papa_id,
            'colorpicker' => LGLIB_colorpicker(array(
                    'fg_id'     => 'fgcolor',
                    'fg_color'  => $this->fgcolor,
                    'bg_id'     => 'bgcolor',
                    'bg_color'  => $this->bgcolor,
                    'sample_id' => 'sample',
                )),
        ) );
        $T->parse('output','modify');
        return $T->finish($T->get_var('output'));
    }   // function Edit()


    /**
     * Recurse through the category table building an option list sorted by id.
     *
     * @param integer  $sel     Category ID to be selected in list
     * @param integer  $self    Current category ID
     * @return string           HTML option list, without <select> tags
     */
    public static function buildSelection($sel=0, $self=0)
    {
        global $_TABLES;

        $str = '';
        $root = 1;
        $Cats = self::getTree($root);
        foreach ($Cats as $Cat) {
            //if ($Cat->cat_id == $root) {
            //    continue;       // Don't include the root category
            //} elseif ($self == $Cat->cat_id) {
            if ($self == $Cat->cat_id) {
                // Exclude self when building parent list
                $disabled = 'disabled="disabled"';
            } elseif (SEC_hasAccess($Cat->owner_id, $Cat->group_id,
                    $Cat->perm_owner, $Cat->perm_group,
                    $Cat->perm_members, $Cat->perm_anon) < 3) {
                // Current user can't access the category
                $disabled = 'disabled="disabled"';
            } else {
                $disabled = '';
            }
            $selected = $Cat->cat_id == $sel ? 'selected="selected"' : '';
            $str .= "<option value=\"{$Cat->cat_id}\" $selected $disabled>";
            $str .= $Cat->disp_name;
            $str .= "</option>\n";
        }
        return $str;

    
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
    }


    /**
     * Get the breadcrumbs for a catetory.
     * Static function that can be passed a parent map or NULL
     * if a map hasn't been created yet.
     *
     * @param   integer $cat_id     Base category ID
     * @param   boolean $showlink   True to add links, False for text only
     * @return  string          Breadcrumbs
     */
    public static function showBreadCrumbs($cat_id, $showlink=true)
    {
        global $_CONF_ADVT, $_TABLES;

        $cat_id = (int)$cat_id;
        $T = new \Template($_CONF_ADVT['path'] . '/templates');
        $tpltype = $_CONF_ADVT['_is_uikit'] ? '.uikit' : '';
        $T->set_file('breadcrumbs', "breadcrumbs$tpltype.thtml");
        $T->set_block('breadcrumbs', 'BreadCrumbs', 'BC');
        
        $sql = "SELECT parent.cat_name, parent.cat_id
            FROM {$_TABLES['ad_category']} AS node,
                {$_TABLES['ad_category']} AS parent
            WHERE node.lft BETWEEN parent.lft AND parent.rgt
                AND node.cat_id = $cat_id
            ORDER BY parent.lft";
        $res = DB_query($sql);
        $c = DB_numRows($res);
        $i = 0;
        while ($A = DB_fetchArray($res, false)) {
            $i++;
            if ($showlink) {
                $location = '<a href="' .
                    CLASSIFIEDS_makeURL('home', $A['cat_id']) .
                    '">' . $A['cat_name'] . '</a>';
            } else {
                // just get the names, no links, e.g. for notifications
                $location = $A['cat_name'];
            }
            $T->set_var(array(
                'bc_link'   => $location,
                'last_link' => $i == $c ? true : false,
            ) );
            $T->parse('BC', 'BreadCrumbs', true);
        }
        $T->parse('output', 'breadcrumbs');
        return $T->finish($T->get_var('output'));
    }


    /**
     * Creates the breadcrumb string from the parent mapping.
     * Acts on the current category object
     *
     * @uses   self::showBreadCrumbs()
     * @param  boolean $showlink   Link to the categories?
     * @param  boolean $raw        True to just get the json values
     * @return mixed       Parent array or HTML for breadcrumbs
     */
    public function BreadCrumbs($showlink=true, $raw = false)
    {
        return self::showBreadCrumbs($this->cat_id, $showlink);
    }


    /**
     * Creates the parent mapping for breadcrumbs when the category is saved.
     *
     * @return  array   Array of parent category IDs and names
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
     * Calls itself recursively to find all sub-categories.
     * Stores an array of category information in $subcats.
     *
     * @param   integer $id     Top-level Category ID
     * @param   integer $depth  Number of sub-category levels
     * @return  array           Array of category objects
     */
    public static function SubCats($id = 0, $depth = 1)
    {
        global $_TABLES, $LANG_ADVT;
        static $subcats = array();

        $id = (int)$id;
        if (isset($subcats[$id])) {
            return $subcats[$id];
        } else {
            $subcats[$id] = array();
        }

        if ($id == 0) {
            // Get only root categories
            $sql = "SELECT * FROM {$_TABLES['ad_category']}
                    WHERE papa_id = 0
                    ORDER BY lft";
        } else {
            $sql = "SELECT node.*, (COUNT(parent.cat_name) - (sub_tree.depth + 1)) AS depth
                FROM {$_TABLES['ad_category']} AS node,
                    {$_TABLES['ad_category']} AS parent,
                    {$_TABLES['ad_category']} AS sub_parent,
                (
                    SELECT node.cat_id, node.cat_name, (COUNT(parent.cat_name) - 1) AS depth
                    FROM {$_TABLES['ad_category']} AS node,
                        {$_TABLES['ad_category']} AS parent
                    WHERE node.lft BETWEEN parent.lft AND parent.rgt
                        AND node.cat_id = $id
                    GROUP BY node.cat_name
                    ORDER BY node.lft
                ) AS sub_tree
                WHERE node.lft BETWEEN parent.lft AND parent.rgt
                    AND node.lft BETWEEN sub_parent.lft AND sub_parent.rgt
                    AND sub_parent.cat_id = sub_tree.cat_id
                GROUP BY node.cat_name
                HAVING depth > 0 AND depth <= $depth
                ORDER BY node.lft";
        }
        //echo $sql;die;
        $res = DB_query($sql);
        while ($A = DB_fetchArray($res, false)) {
            $subcats[$id][$A['cat_id']] = new self($A['cat_id'], $A);
        }
        return $subcats[$id];
    }


    /**
     * Find the total number of ads for a category, including subcategories.
     *
     * @param   integer $cat_id     CategoryID
     * @param   boolean $current    True to count only non-expired ads
     * @param   boolean $sub        True to count ads in sub-categories
     * @return  integer Total Ads
     */
    public static function TotalAds($cat_id, $current = true, $sub = true)
    {
        global $_TABLES;

        $cat_id = (int)$cat_id;
        $current = $current == true ? true : false;
        $sub = $sub == true ? true : false;

        $ad_q_fld = array('cat_id');
        if ($current) {
            $now = time();
        }
        $Cats = self::getTree($cat_id);
        $totalAds = 0;
        foreach ($Cats as $Cat) {
            if ($current) {
                $totalAds += (int)DB_getItem($_TABLES['ad_ads'], 'count(*)',
                        "cat_id = '{$Cat->cat_id}' AND exp_date > $now");
            } else {
                $totalAds += DB_count($_TABLES['ad_ads'], 'cat_id', $Cat->cat_id);
            }
            if (!$sub) break;   // Just getting the first cat_id count
        }
        return $totalAds;
    }


    /**
     * Determine if this category is associated with any ads.
     * Check both prod and submission tables
     *
     * @return  boolean     True if any ad uses this category, False if not
     */
    public function isUsed()
    {
        global $_TABLES;

        if ($this->cat_id == 1) {
            // Fake to treat root category as used, preventing deletion
            return true;
        }

        foreach (array('ad_ads', 'ad_submission') as $tbl_id) {
            if (DB_count($_TABLES[$tbl_id], 'cat_id', $this->cat_id) > 0) {
                return true;
            }
        }
        return false;
    }


    /**
     * Check the current user's access to the current category.
     *
     * @uses    SEC_hasAccess()
     * @param   integer $required       Minimim required access level
     * @return  boolean     True if the user meets the requirement, False if not.
     */
    public function checkAccess($required = 3)
    {
        global $_CONF_ADVT;

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
     * Check if the current user has read-write access to this category.
     *
     * @uses    self::checkAccess()
     * @return  boolean     True if the user can edit, False if not
     */
    public function canEdit()
    {
        return $this->checkAccess(3);
    }


    /**
     * Check if the current user has read access to this category.
     *
     * @uses    self::checkAccess()
     * @return  boolean     True if the user can view, False if not
     */
    public function canView()
    {
        return $this->checkAccess(2);
    }


    /**
     * When no category is given, show a table of all categories
     * along with the count of ads for each.
     * Returns the results from the category
     * list function, chosen based on the display mode
     *
     * @return  string      HTML for category listing page
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
     * Subscribe the current user to this category's notifications.
     *
     * @param   boolean $sub    True to subscribe to sub-categories also
     * @return  boolean     True on success, False on failure
     */
    public function Subscribe($sub = true)
    {
        global $_CONF_ADVT, $LANG_ADVT;

        // only registered users can subscribe, and make sure this is an
        // existing category
        if (COM_isAnonUser() || $this->cat_id < 1)
            return false;;

        $sub = $sub == true ? true : false;
        $subcats = self::SubCats($this->cat_id);

        if ($sub === true) {
            if ($_CONF_ADVT['auto_subcats']) {
                foreach ($subcats as $cat) {
                    PLG_subscribe($_CONF_ADVT['pi_name'], 'category', $cat['cat_id'],
                            0, $LANG_ADVT['category'], $cat['description']);
                }
            }
            return PLG_subscribe($_CONF_ADVT['pi_name'], 'category', $this->cat_id,
                    0, $LANG_ADVT['category'], $this->description);
        } else {
            if ($_CONF_ADVT['auto_subcats']) {
                foreach ($subcats as $cat) {
                    PLG_unsubscribe($_CONF_ADVT['pi_name'], 'category', $cat['cat_id']);
                }
            }
            return PLG_unsubscribe($_CONF_ADVT['pi_name'], 'category', $this->cat_id);
        }
    }


    /**
     * Returns the string corresponding to the $id parameter.
     * Designed to be used standalone; if this is an object,
     * we already have the description in a variable.
     * Uses a static variable to hold the descriptions since this can be
     * called many times for a list.
     *
     * @param   integer $id     Database ID of the ad type
     * @return  string          Ad Type Description
     */
    public static function XX_GetDescription($id)
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
     * Shortcut functions to get resized thumbnail URLs.
     *
     * @param   string  $filename   Filename to view
     * @return  string      URL to the resized image
     */
    public static function thumbUrl($filename)
    {
        global $_CONF_ADVT;
        return LGLIB_ImageUrl($_CONF_ADVT['imgpath'] . '/cat/' . $filename,
                $_CONF_ADVT['thumb_max_size'], $_CONF_ADVT['thumb_max_size']);
    }


    /**
     * Reset all category permissions to a single group/perm setting.
     *
     * @param   integer $gid    Group ID to set
     * @param   array   $perms  Permissions to set
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


    /**
     * Read all the categories into a static array.
     *
     * @param   integer $root   Root category ID
     * @param   string  $prefix Prefix to prepend to sub-category display names
     * @return  array           Array of category objects
     */
    public static function getTree($root=0, $prefix='&nbsp;')
    {
        global $_TABLES;

        $All = array();

        //if (!empty($root)) {
        if ($root > 0) {
            $result = DB_query("SELECT lft, rgt FROM {$_TABLES['ad_category']}
                        WHERE cat_id = $root");
            $row = DB_fetchArray($result, false);
            $between = ' AND parent.lft BETWEEN ' . (int)$row['lft'] .
                        ' AND ' . (int)$row['rgt'];
        }
        $prefix = DB_escapeString($prefix);
        $sql = "SELECT node.*, CONCAT( REPEAT( '$prefix', (COUNT(parent.cat_name) - 1) ), node.cat_name) AS disp_name
            FROM {$_TABLES['ad_category']} AS node,
                {$_TABLES['ad_category']} AS parent
            WHERE node.lft BETWEEN parent.lft AND parent.rgt
            $between
            GROUP BY node.cat_name
            ORDER BY node.lft";
        $res = DB_query($sql);
        while ($A = DB_fetchArray($res, false)) {
            $All[$A['cat_id']] = new self($A['cat_id'], $A);
        }
        return $All;
    }


    /**
     * Get a category and all its children.
     *
     * @param   integer $root   Root category ID, empty to start with Root
     * @param   string  $prefix String to prepend to child category display names
     * @return  array           Array of categrory objects
     */
    public static function XgetTree($root = NULL, $prefix = '-')
    {
        if (empty($root)) $root = 1;
        $allcats = self::getAll();
        $cat = $allcats[$root];
        $cats = array();
        foreach ($allcats as $cat_id=>$cat_obj) {
            if ($cat_id >= $cat->lft && $cat_id <= $cat->rgt) {
                $cats[$cat_id] = $cat_obj;
            }
        }
        return $cats;
    }


    /**
     * Given a child category ID, get the complete path back to the Root.
     *
     * @param   integer $child  Child category ID
     * @return  array           Array of category objects starting at the root
     */
    public static function getPath($child)
    {
        global $_TABLES;

        $retval = array();
        $sql = "SELECT parent.cat_id, parent.cat_name
                FROM {$_TABLES['ad_category']} AS node,
                    {$_TABLES['ad_category']} AS parent
                WHERE node.lft BETWEEN parent.lft AND parent.rgt
                AND node.cat_id = $child
                ORDER BY parent.lft";
        $res = DB_query($sql);
        while ($A = DB_fetchArray($res, false)) {
            $retval[] = new self($A['cat_id'], $A);
        }
        return $retval;
    }


    /**
     * Rebuild the MPT tree starting at a given parent and "left" value.
     *
     * @param  integer $parent     Starting category ID
     * @param  integer $left       Left value of the given category
     * @return integer         New Right value (only when called recursively)
     */
    public static function rebuildTree($parent, $left)
    {
        global $_TABLES;

        // the right value of this node is the left value + 1
        $right = $left + 1;

        // get all children of this node
        $sql = "SELECT cat_id FROM {$_TABLES['ad_category']}
                WHERE papa_id ='$parent'";
        $result = DB_query($sql);
        while ($row = DB_fetchArray($result, false)) {
            // recursive execution of this function for each
            // child of this node
            // $right is the current right value, which is
            // incremented by the rebuild_tree function
            $right = self::rebuildTree($row['cat_id'], $right);
        }

        // we've got the left value, and now that we've processed
        // the children of this node we also know the right value
        $sql1 = "UPDATE {$_TABLES['ad_category']}
                SET lft = '$left', rgt = '$right'
                WHERE cat_id = '$parent'";
        DB_query($sql1);

        // return the right value of this node + 1
        return $right + 1;
    }


    /**
     * Get the ID of the immediate parent for a given category.
     *
     * @param   integer $cat_id     Current category ID
     * @return  integer     ID of parent category.
     */
    public static function getParent($cat_id)
    {
        global $_TABLES;

        $cat_id = (int)$cat_id;
        $sql = "SELECT parent.cat_id, parent.cat_name
                FROM {$_TABLES['ad_category']} AS node,
                    {$_TABLES['ad_category']} AS parent
                WHERE node.lft BETWEEN parent.lft AND parent.rgt
                AND node.cat_id = $cat_id
                ORDER BY parent.lft DESC LIMIT 2";
        $res = DB_query($sql);
        $parent_id = NULL;
        while ($A = DB_fetchArray($res, false)) {
            $parent_id = $A['cat_id'];
        }
        return ($parent_id == $cat_id) ? NULL : $parent_id;
    }

}

?>
