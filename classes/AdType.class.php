<?php
/**
 * Class to manage ad types.
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
 * Class for ad type.
 * @package classifieds
 */
class AdType
{
    /** Type record ID.
     * @var integer */
    private $id = 0;

    /** Description.
     * @var string */
    private $dscp = '';

    /** Enabled flag.
     * @var boolean */
    private $enabled = 1;

    /** Foreground color when shown in the ad list block.
     * @var string */
    private $fgcolor = '';

    /** Background color when shown in the ad list block.
     * @var string */
    private $bgcolor = '';

    /** Error string or value, to be accessible by the calling routines.
     * @var mixed */
    public  $Error;


    /**
     * Constructor.
     * Reads in the specified class, if $id is set.  If $id is zero,
     * then a new entry is being created.
     *
     * @param   array|integer $id Optional type ID or data record
     */
    public function __construct($id=0)
    {
        if (is_array($id)) {
            $this->setVars($id);
        } else {
            $this->id = (int)$id;
            if ($id > 0) {
                $this->ReadOne();
            }
        }
    }


    /**
     * Get an instance of an ad type..
     * First checks the static array in case the object has already been read,
     * and finally reads from the DB.
     *
     * @param   integer|array   $type   Ad type record or integer ID
     * @return  object              Ad type object
     */
    public static function getInstance($type)
    {
        static $Types = array();

        if (is_array($type) && isset($Types['id'])) {
            // This is a record array from the DB
            $type_id = (int)$type['id'];
        } else {
            // This is a type ID
            $type_id = (int)$type;
        }
        if (!array_key_exists($type_id, $Types)) {
            $Types[$type_id] = new self($type);
        }
        return $Types[$type_id];
    }


    /**
     * Set the isAdmin flag.
     *
     * @param   boolean $admin True for admin access, False otherwise.
     */
    public function setAdmin($admin)
    {
        $this->isAdmin = $admin ? true : false;
    }


    /**
     * Sets all variables to the matching values from $rows.
     *
     * @param   array   $row    Array of values, from DB or $_POST
     */
    public function SetVars($row)
    {
        if (!is_array($row)) return;

        $this->id = (int)$row['id'];
        $this->dscp = $row['description'];
        $this->fgcolor = $row['fgcolor'];
        $this->bgcolor = $row['bgcolor'];
        $this->enabled = isset($row['enabled']) && $row['enabled'] ? 1 : 0;
    }


    /**
     * Read one as type from the database and populate the local values.
     *
     * @param   integer $id     Optional ID.  Current ID is used if zero
     */
    public function ReadOne($id = 0)
    {
        global $_TABLES;

        $id = (int)$id;
        if ($id == 0) $id = $this->id;
        if ($id == 0) {
            $this->error = 'Invalid ID in ReadOn()';
            return;
        }

        $result = DB_query("SELECT * from {$_TABLES['ad_types']}
                            WHERE id=$id");
        $row = DB_fetchArray($result, false);
        $this->SetVars($row);
    }


    /**
     * Get all the ad types, ordered by name.
     *
     * @return  array   Array of AdType objects
     */
    public static function getAll()
    {
        global $_TABLES;

        $retval = array();
        $sql = "SELECT * FROM {$_TABLES['ad_types']}
            ORDER BY description ASC";
        $res = DB_query($sql);
        while ($A = DB_fetchArray($res, false)) {
            $retval[] = new self($A);
        }
        return $retval;
    }


    /**
     * Delete the curret cls/div record from the database.
     */
    public function Delete()
    {
        global $_TABLES;

        if ($this->id > 0)
            DB_delete($_TABLES['ad_types'], 'id', $this->id);

        $this->id = 0;
    }


    /**
     * Adds the current values to the databae as a new record.
     *
     * @param   array   $vals   Optional array of values to set
     * @return  boolean     True on success, False on error
     */
    public function Save($vals = NULL)
    {
        global $_TABLES;

        if (is_array($vals)) {
            $this->SetVars($vals);
        }

        if (!$this->isValidRecord()) {
            return false;
        }

        if ($this->id == 0) {
            $sql1 = "INSERT INTO {$_TABLES['ad_types']} SET ";
            $sql3 = '';
        } else {
            $sql1 = "UPDATE {$_TABLES['ad_types']} SET ";
            $sql3 = " WHERE id=" . $this->id;
        }
        $sql2 = "description = '" . DB_escapeString($this->dscp) . "',
            fgcolor = '" . DB_escapeString($this->fgcolor) . "',
            bgcolor = '" . DB_escapeString($this->bgcolor) . "',
                enabled = {$this->enabled}";
        $sql = $sql1 . $sql2 . $sql3;
        $res = DB_query($sql);
        return DB_error() ? false : true;
    }


    /**
     * Determines if the current values are valid.
     *
     * @return  boolean     True if ok, False otherwise.
     */
    public function isValidRecord()
    {
        if ($this->dscp == '') {
            return false;
        } else {
            return true;
        }
    }


    /**
     * Creates the edit form.
     *
     * @param   integer $id Optional ID, current record used if zero
     * @return  string      HTML for edit form
     */
    public function showForm($id = 0)
    {
        global $_TABLES, $_CONF, $_CONF_ADVT, $LANG_ADVT, $LANG_ADMIN;

        $id = (int)$id;
        if ($id > 0) $this->Read($id);

        $T = new \Template($_CONF_ADVT['path'] . '/templates');
        $T->set_file('admin','adtypeform.thtml');
        $T->set_var(array(
            'pi_admin_url'  => $_CONF_ADVT['admin_url'],
            'lang_header'   => $id == 0 ? $LANG_ADVT['newadtypehdr'] : $LANG_ADVT['editadtypehdr'],
            'cancel_url'    => $_CONF_ADVT['admin_url'] . '/index.php?types',
            'show_name'     => $this->showName,
            'type_id'       => $this->id,
            'description'   => htmlspecialchars($this->dscp),
            'ena_chk'   => $this->enabled == 1 ? 'checked="checked"' : '',
            'colorpicker' => LGLIB_colorpicker(array(
                    'fg_id'     => 'fgcolor',
                    'fg_color'  => $this->fgcolor,
                    'bg_id'     => 'bgcolor',
                    'bg_color'  => $this->bgcolor,
                    'sample_id' => 'sample',
                )),
        ) );

        // add a delete button if this ad type isn't used anywhere
        if ($this->id > 0 && !$this->isUsed()) {
            $T->set_var('show_del_btn', 'true');
        } else {
            $T->set_var('show_del_btn', '');
        }
        $T->parse('output','admin');
        return $T->finish($T->get_var('output'));
    }


    /**
     * Creates a dropdown selection for the specified list.
     * Pre-selects the specified record.
     *
     * @param   integer $sel    Optional item ID to select
     * @return  string      HTML for selection dropdown
     */
    public static function makeSelection($sel=0)
    {
        global $_TABLES;

        return COM_optionList(
            $_TABLES['ad_types'],
            'id,description',
            $sel,
            1,
            'enabled=1'
        );
    }


    /**
     * Sets the "enabled" field to the specified value.
     *
     * @param   integer $oldval Original value to change
     * @param   integer $id     ID number of element to modify
     * @return  integer     New value (old value if failed)
     */
    public static function toggleEnabled($oldval, $id=0)
    {
        global $_TABLES;

        $id = (int)$id;
        $newval = $oldval == 1 ? 0 : 1;

        $sql = "UPDATE {$_TABLES['ad_types']}
            SET enabled=$newval
            WHERE id=$id";
        //echo $sql;die;
        DB_query($sql, 1);
        if (DB_error()) {
            $retval = $oldval;
        } else {
            $retval = $newval;
        }
        return $retval;
    }


    /**
     * Determine if this ad type is used by any ads in the database.
     *
     * @return  boolean     True if used, False if not
     */
    public function isUsed()
    {
        global $_TABLES;

        if (DB_count($_TABLES['ad_ads'], 'ad_type', $this->id) > 0) {
            return true;
        } else {
            return false;
        }
    }


    /**
     * Returns the string corresponding to the $id parameter.
     * Designed to be used standalone; if this is an object,
     * we already have the description in a variable.
     * Uses a static variable to hold the descriptions since this can
     * be called many times for an ad listing.
     *
     * @param  integer $id     Database ID of the ad type
     * @return string          Ad Type Description
     */
    public static function getDescription($id)
    {
        return self::getInstance($id)->getDscp();

        global $_TABLES;
        static $desc = array();

        $id = (int)$id;
        if (!isset($desc[$id])) {
            $desc[$id] = DB_getItem($_TABLES['ad_types'], 'description', "id='$id'");
        }
        return $desc[$id];
    }


    /**
     * Get the record number for this ad type.
     *
     * @return  integer     Record ID
     */
    public function getID()
    {
        return (int)$this->id;
    }

    /**
     * Get the description for this ad type.
     *
     * @return  string      Description string
     */
    public function getDscp()
    {
        return $this->dscp;
    }


    /**
     * Get the foreground color for this ad type.
     *
     * @return  string      Foreground color string
     */
    public function getFGColor()
    {
        return empty($this->fgcolor) ? 'inherit' : $this->fgcolor;
    }


    /**
     * Get the background color for this ad type.
     *
     * @return  string      Background color string
     */
    public function getBGColor()
    {
        return empty($this->bgcolor) ? 'inherit' : $this->bgcolor;
    }


    /**
     * Create admin list of Ad Types.
     *
     * @return  string  HTML for admin list
     */
    public static function adminList()
    {
        global $_CONF, $_TABLES, $LANG_ADMIN, $LANG_ACCESS, $_CONF_ADVT, $LANG_ADVT;

        USES_lib_admin();
        $retval = '';

        $header_arr = array(
            array(
                'text' => $LANG_ADVT['edit'],
                'field' => 'edit',
                'sort' => false,
                'align' => 'center',
            ),
            array(
                'text' => $LANG_ADVT['description'],
                'field' => 'description',
                'sort' => true,
            ),
            array(
                'text' => $LANG_ADVT['enabled'],
                'field' => 'enabled',
                'sort' => false,
                'align' => 'center',
            ),
            array(
                'text' => $LANG_ADVT['delete'],
                'field' => 'delete',
                'sort' => false,
                'align' => 'center',
            ),
        );
        $defsort_arr = array('field' => 'description', 'direction' => 'asc');
        $text_arr = array(
            'has_extras' => true,
            'form_url' => $_CONF_ADVT['admin_url'] . '/index.php',
        );
        $query_arr = array(
            'table' => 'ad_types',
            'sql' => "SELECT * FROM {$_TABLES['ad_types']} ",
            'query_fields' => array(),
            'default_filter' => ''
        );
        $form_arr = '';

        $retval .= COM_createLink(
            $LANG_ADVT['new_type'],
            $_CONF_ADVT['admin_url'] . '/index.php?editadtype=x',
            array(
                'class' => 'uk-button uk-button-success',
                'style' => 'float:left',
            )
        );
        $retval .= ADMIN_list(
            'classifieds',
            array(__CLASS__, 'getListField'),
            $header_arr,
            $text_arr, $query_arr, $defsort_arr, '', '', '', $form_arr
        );
        return $retval;
    }


    /**
     * Ad type management - return the display version for a single field.
     *
     * @param   string  $fieldname  Name of the field
     * @param   string  $fieldvalue Value to be displayed
     * @param   array   $A          Associative array of all values available
     * @param   array   $icon_arr   Array of icons available for display
     * @return  string              Complete HTML to display the field
     */
    public static function getListField($fieldname, $fieldvalue, $A, $icon_arr)
    {
        global $_CONF, $_CONF_ADVT, $LANG24, $LANG_ADVT, $_TABLES;

        $retval = '';

        switch($fieldname) {
        case 'edit':
            $retval = COM_createLink(
                '',
                $_CONF_ADVT['admin_url'] . "/index.php?editadtype={$A['id']}",
                array(
                    'class' => 'uk-icon uk-icon-edit'
                )
            );
            break;

        case 'enabled':
            if ($fieldvalue == 1) {
                $chk = ' checked="checked" ';
                $enabled = 1;
            } else {
                $chk = '';
                $enabled = 0;
            }
            $fld_id = $fieldname . '_' . $A['id'];
            $retval =
                "<input name=\"{$fld_id}\" id=\"{$fld_id}\" " .
                "type=\"checkbox\" $chk " .
                "onclick='ADVTtoggleEnabled(this, \"{$A['id']}\", \"adtype\", \"{$_CONF['site_url']}\");' ".
                ">\n";
            break;

        case 'delete':
            if (DB_count($_TABLES['ad_ads'], 'ad_type', $A['id']) == 0) {
                $retval .= COM_createLink(
                    '',
                    $_CONF_ADVT['admin_url'] .
                        "/index.php?deleteadtype=x&amp;type_id={$A['id']}",
                    array(
                        'title' => 'Delete this item',
                        'class' => 'uk-icon uk-icon-trash advt_icon_danger',
                        'onclick' => "return confirm('Do you really want to delete this item?');",
                    )
                );
            }
            break;

        default:
            $retval = $fieldvalue;
            break;
        }
        return $retval;
    }

}   // class AdType

?>
