<?php
/**
 *  Class to handle user account info for the Classifieds plugin
 *
 *  @author     Lee Garner <lee@leegarner.com>
 *  @copyright  Copyright (c) 2009 Lee Garner <lee@leegarner.com>
 *  @package    classifieds
 *  @version    1.0.0
 *  @license    http://opensource.org/licenses/gpl-2.0.php 
 *              GNU Public License v2 or later
 *  @filesource
 */

/**
 *  Class for user account info
 *  @package classifieds
 */
class adUserInfo
{
    /** User ID, matches glFusion uid
     *  @var integer */
    var $uid;

    /** Telephone number
     *  @var string */
    var $tel;

    /** Fax number
     *  @var string */
    var $fax;

    /** Address
     *  @var string */
    var $address;

    /** City
     *  @var string */
    var $city;

    /** State
     *  @var string */
    var $state;

    /** Postal Code
     *  @var string */
    var $postcode;

    /** Last Update (Unix Timestamp)
     *  @var integer */
    var $lastup_date;

    /** Notify of Expirations? True/False
     *  @var boolean */
    var $notify_exp;

    /** Notify of new comments? True/False
     *  @var boolean */
    var $notify_cmt;

    /** Account balance, ad days
     *  @var integer */
    var $days_balance;

    /** Max days that user can run an ad
     *  @var integer */
    var $max_ad_days;


    /**
     *  Constructor.
     *  Reads in the specified class, if $id is set.  If $id is zero, 
     *  then a new entry is being created.
     *  @param integer $uid Optional type ID
     */
    function __construct($uid=0)
    {
        global $_USER;

        $uid = (int)$uid;
        if ($uid < 1) {
            $uid = (int)$_USER['uid'];
        }
        $this->setUID($uid);
        $this->ReadOne();
    }

    function setUID($id=0) { $this->uid = (int)$id; }
    function getUID() { return $this->uid; }

    function setAddress($str='') { $this->address = trim($str); }
    function getAddress() { return $this->address; }

    function setCity($str='') { $this->city = trim($str); }
    function getCity() { return $this->city; }

    function setState($str='') { $this->state = trim($str); }
    function getState() { return $this->state; }

    function setPostCode($str='') { $this->postcode = trim($str); }
    function getPostCode() { return $this->postcode; }

    function setTel($str='') { $this->tel = trim($str); }
    function getTel() { return $this->tel; }

    function setFax($str='') { $this->fax = trim($str); }
    function getFax() { return $this->fax; }

    function setNotifyExp($val=0)
    {   $this->notify_exp = $val == 0 ? 0 : 1;  }
    function getNotifyExp() { return $this->notify_exp; }

    function setNotifyCmt($val=0)
    {   $this->notify_cmt = $val == 0 ? 0 : 1;  }
    function getNotifyCmt() { return $this->notify_cmt; }

    function setDaysBalance($val) { $this->days_balance = (int)$val; }
    function getDaysBalance() { return $this->days_balance; }

    /** Returns the maximum number of days this user can add to an ad. */
    function getMaxDays() { return $this->max_ad_days; }


    /**
     *  Sets all variables to the matching values from $rows
     *  @param array $row Array of values, from DB or $_POST
     */
    function SetVars($A)
    {
        if (!is_array($A)) return;

        if (isset($A['advt_address'])) {
            // Coming from an edit form
            $this->setAddress($A['advt_address']);
            $this->setCity($A['advt_city']);
            $this->setState($A['advt_state']);
            $this->setPostCode($A['advt_postcode']);
            $this->setTel($A['advt_tel']);
            $this->setFax($A['advt_fax']);
            $this->setNotifyExp($A['advt_notify_exp']);
            $this->setNotifyCmt($A['advt_notify_cmt']);
            $this->setDaysBalance($A['advt_days_balance']);
        } else {
            $this->setAddress($A['address']);
            $this->setCity($A['city']);
            $this->setState($A['state']);
            $this->setPostCode($A['postcode']);
            $this->setTel($A['tel']);
            $this->setFax($A['fax']);
            $this->setNotifyExp($A['notify_exp']);
            $this->setNotifyCmt($A['notify_comment']);
            $this->setDaysBalance($A['days_balance']);
        }

        // Update the actual max number of days that this user ca
        // run an ad
        $this->setMaxDays();

    }


    /**
     *  Read one user from the database
     *  @param integer $id Optional ID.  Current ID is used if zero
     */
    function ReadOne($uid = 0)
    {
        global $_TABLES;

        $uid = (int)$uid;
        if ($uid == 0) $uid = $this->uid;
        if ($uid == 0) {
            return;
        }

        $result = DB_query("SELECT * from {$_TABLES['ad_uinfo']} 
                                    WHERE uid=$uid");
        if ($result) {
            $row = DB_fetchArray($result, false);
            $this->SetVars($row);
        }

        // Update the actual max number of days that this user can
        // run an ad
        $this->setMaxDays();

    }


    /**
     *  Save the current values to the database.
     */
    function Save()
    {
        global $_TABLES;

        $sql = "INSERT INTO
                {$_TABLES['ad_uinfo']}
                (uid, address, city, state, postcode,
                tel, fax, notify_exp, notify_comment)
            VALUES (
                '" . DB_escapeString($this->getUID()) . "',
                '" . DB_escapeString($this->getAddress()) . "',
                '" . DB_escapeString($this->getCity()) . "',
                '" . DB_escapeString($this->getState()) . "',
                '" . DB_escapeString($this->getPostCode()) . "',
                '" . DB_escapeString($this->getTel()) . "',
                '" . DB_escapeString($this->getFax()) . "',
                {$this->getNotifyExp()},
                {$this->getNotifyCmt()}
            )
            ON DUPLICATE KEY UPDATE
                address = '" . DB_escapeString($this->getAddress()) . "',
                city = '" . DB_escapeString($this->getCity()) . "',
                state = '" . DB_escapeString($this->getState()) . "',
                postcode = '" . DB_escapeString($this->getPostCode()) . "',
                tel = '" . DB_escapeString($this->getTel()) . "',
                fax = '" . DB_escapeString($this->getFax()) . "',
                notify_exp = {$this->getNotifyExp()},
                notify_comment = {$this->getNotifyCmt()}
            ";
        //echo $sql;die;
        DB_query($sql);
    }


    /**
     *  Delete the current user info record from the database
     */
    function Delete()
    {
        global $_TABLES;

        if ($this->uid > 0)
            DB_delete($_TABLES['ad_uinfo'], 'id', $this->uid);

        $this->uid = 0;
    }


    /**
     *  Creates the edit form
     *  @param integer $id Optional ID, current record used if zero
     *  @return string HTML for edit form
     */
    function showForm($type = 'advt')
    {
        global $_TABLES, $_CONF, $_CONF_ADVT, $LANG_ADVT, $_USER;

        $id = (int)$id;
        if ($id > 0) $this->Read($id);

        $base_url = $_CONF['site_url'] . '/' . $_CONF_ADVT['pi_name'];

        $T = new Template(CLASSIFIEDS_PI_PATH . '/templates');
        if ($type == 'advt') {
            // Called within the plugin
            $T->set_file('accountinfo', 'accountinfo.thtml');
        } else {
            // Called via glFusion account settings
            $T->set_file('accountinfo', 'account_settings.thtml');
        }

        $T->set_var(array(
            'uinfo_address'     => $this->getAddress(),
            'uinfo_city'        => $this->getCity(),
            'uinfo_state'       => $this->getState(),
            'uinfo_tel'         => $this->getTel(),
            'uinfo_fax'         => $this->getFax(),
            'uinfo_postcode'    => $this->getPostCode(),
            'exp_notify_checked' => $this->getNotifyExp() == 1 ? 
                        'checked="checked"' : '',
            'cmt_notify_checked' => $this->getNotifyCmt() == 1 ? 
                        'checked="checked"' : '',
        ) );

        $sql = "
            SELECT 
                cat_id, notice_id
            FROM 
                {$_TABLES['ad_notice']} 
            WHERE 
                uid='{$_USER['uid']}'";
        $notice = DB_query($sql);
        if (!$notice) 
            return CLASSIFIEDS_errorMsg($LANG_ADVT['database_error'], 'alert');

        if (0 == DB_numRows($notice)) {
            $T->set_var('no_autonotify_list', 'true');
        } else {
            while ($row = DB_fetchArray($notice)) {
                $T->set_block('accountinfo', 'AutoNotifyListBlk', 'NotifyList');
                $T->set_var(array(
                    'cat_id'    => $row['cat_id'],
                    'cat_name'  => CLASSIFIEDS_BreadCrumbs($row['cat_id'], false),
                    'pi_url'    => $base_url,
                ) );
                $T->parse('NotifyList', 'AutoNotifyListBlk', true);
            }
        }

        $T->parse('output','accountinfo');
        return $T->finish($T->get_var('output'));

    }   // function showForm()

 
    /**
     *  Update the max days balance by adding a give value (positive or negative).
     *  @param integer $value   Value to add to the current balance
     *  @param integer $id      User ID to modify, empty to use the current one.
     */
    function UpdateDaysBalance($value, $uid=0)
    {
        global $_TABLES;

        $uid = (int)$uid;
        if ($uid == 0) {
            if (is_object($this))
                $uid = $this->getUID();
            else
                return;
        }

        // Calculate the new balance, which cannot fall below zero.
        $this->days_balance = min($this->days_balance + (int)$value, 0);
        /*$value = (int)$value;
        $newvalue = $this->days_balance + $value;
        if ($newvalue < 0) $newvalue = 0;
        $this->days_balance = $newvalue;
        */

        $sql = "UPDATE {$_TABLES['ad_uinfo']}
            SET days_balance = {$this->days_balance}
            WHERE uid = $uid";
        //echo $sql;die;
        DB_query($sql);
    }


    /**
     *  Sets the local variable for the maximum number of days for an ad.
     *  This is used if ad purchasing or earning is enabled.
     */
    function setMaxDays()
    {
        global $_TABLES, $_CONF_ADVT, $_USER, $_GROUPS;

        if (is_null($this->days_balance) ||
                $this->uid != $_USER['uid'] || 
                !$_CONF_ADVT['purchase_enabled']) {
            $this->max_ad_days = (int)$_CONF_ADVT['max_total_duration'];
            return;
        }

        // Current user is excluded from restrictions, use global amount
        foreach ($_CONF_ADVT['purchase_exclude_groups'] as $ex_grp) {
            if (array_key_exists($ex_grp, $_GROUPS)) {
                $this->max_ad_days = (int)$_CONF_ADVT['max_total_duration'];
                return;
            }
        }

        // Otherwise, use the current user's balance
        $this->max_ad_days = $this->days_balance;
    }


}   // class UserInfo


?>
