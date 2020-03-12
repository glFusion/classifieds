<?php
/**
 * MySQL table creation statements for the Classifieds plugin.
 *
 * @author      Lee Garner <lee@leegarner.com>
 * @copyright   Copyright (c) 2009-2020 Lee Garner <lee@leegarner.com>
 * @package     classifieds
 * @version     v1.3.0
 * @license     http://opensource.org/licenses/gpl-2.0.php
 *              GNU Public License v2 or later
 * @filesource
 */


/**
 * Global array of new tables to be created.
 * @global array $NEWTABLE
 */
global $NEWTABLE;
$NEWTABLE = array();

$NEWTABLE['ad_category'] = "CREATE TABLE {$_TABLES['ad_category']} (
  `cat_id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `papa_id` smallint(5) unsigned NOT NULL,
  `cat_name` varchar(40) NOT NULL,
  `description` varchar(255) DEFAULT '',
  `group_id` mediumint(8) unsigned NOT NULL DEFAULT '1',
  `owner_id` mediumint(8) unsigned NOT NULL DEFAULT '1',
  `perm_owner` tinyint(1) unsigned NOT NULL DEFAULT '3',
  `perm_group` tinyint(1) unsigned NOT NULL DEFAULT '3',
  `perm_members` tinyint(1) unsigned NOT NULL DEFAULT '2',
  `perm_anon` tinyint(1) unsigned NOT NULL DEFAULT '2',
  `image` varchar(100) DEFAULT NULL,
  `lft` int(5) unsigned NOT NULL DEFAULT '0',
  `rgt` int(5) unsigned NOT NULL DEFAULT '0',
  PRIMARY KEY (`cat_id`),
  KEY `lft` (`lft`),
  KEY `rgt` (`rgt`)
) ENGINE=MyISAM";

// common SQL for ad and ad_submission tables
$adtable_create = "
    (ad_id VARCHAR(20) NOT NULL DEFAULT '',
    cat_id SMALLINT UNSIGNED NOT NULL,
    uid SMALLINT UNSIGNED NOT NULL,
    subject varchar(255) NOT NULL,
    description TEXT NOT NULL,
    url varchar(255) NOT NULL,
    views INT NOT NULL DEFAULT '0',
    add_date INT NOT NULL,
    exp_date INT NOT NULL,
    price varchar(50) default '',
    ad_type smallint(5) unsigned NOT NULL default '0',
    keywords varchar(255),
    exp_sent tinyint(1) unsigned NOT NULL default '0',
    comments int(4) unsigned NOT NULL default '0',
    comments_enabled tinyint(1) unsigned NOT NULL default '1',
    PRIMARY KEY(ad_id),
    KEY (cat_id),
    KEY (add_date),
    KEY (exp_date),
    KEY (uid)
) ENGINE=MyISAM";
$NEWTABLE['ad_ads'] = "CREATE TABLE {$_TABLES['ad_ads']}
    $adtable_create";
$NEWTABLE['ad_submission'] = "CREATE TABLE {$_TABLES['ad_submission']}
    $adtable_create";

$NEWTABLE['ad_photo'] = "CREATE TABLE {$_TABLES['ad_photo']} (
    photo_id SMALLINT UNSIGNED NOT NULL auto_increment,
    ad_id VARCHAR(128) NOT NULL DEFAULT '',
    filename varchar(255),
    PRIMARY KEY(photo_id),
    KEY `idxAd` (`ad_id`,`photo_id`)
) ENGINE=MyISAM";

/*$NEWTABLE['ad_notice'] = "CREATE TABLE {$_TABLES['ad_notice']} (
    cat_id SMALLINT UNSIGNED NOT NULL,
    uid VARCHAR(50) NOT NULL,
    email VARCHAR(50) NOT NULL,
    PRIMARY KEY(cat_id, uid))";*/

$NEWTABLE['ad_uinfo'] = "CREATE TABLE {$_TABLES['ad_uinfo']} (
    uid SMALLINT UNSIGNED NOT NULL auto_increment,
    tel VARCHAR(20) NOT NULL,
    city VARCHAR(20) NOT NULL,
    state VARCHAR(20) NOT NULL,
    lastup_date INT NOT NULL,
    postcode VARCHAR(20) NOT NULL,
    address VARCHAR(30) NOT NULL,
    notify_exp TINYINT(1) UNSIGNED NOT NULL DEFAULT 1,
    notify_comment TINYINT(1) UNSIGNED NOT NULL DEFAULT 1,
    days_balance INT(11) DEFAULT 0,
    PRIMARY KEY(uid)
) ENGINE=MyISAM";

$NEWTABLE['ad_types'] = "CREATE TABLE {$_TABLES['ad_types']} (
    id int(11) NOT NULL auto_increment,
    description varchar(255) default NULL,
    fgcolor varchar(10) NOT NULL default '',
    bgcolor varchar(10) NOT NULL default '',
    enabled tinyint(1) default '1',
    PRIMARY KEY  (`id`)
) ENGINE=MyISAM";

/*
$NEWTABLE['ad_trans'] = "CREATE TABLE {$_TABLES['ad_trans']} (
    `tid` int(10) NOT NULL auto_increment,
    `uid` int(11) NOT NULL default '0',
    `dt` timestamp NOT NULL default CURRENT_TIMESTAMP,
    `trans_id` varchar(40) default '',
    `days` int(10) NOT NULL default '0',
    PRIMARY KEY  (`tid`))";
*/

$DEFVALUES['ad_types'] = "INSERT INTO {$_TABLES['ad_types']}
        (description)
    VALUES
        ('For Sale'),
        ('Wanted')
    ";
$DEFVALUES['category'] = "INSERT INTO {$_TABLES['ad_category']} (
        papa_id, cat_name, description,
        group_id, owner_id, perm_owner, perm_group, perm_members, perm_anon,
        image
    ) VALUES (
        1, 'Root', 'Root Category',
        13, 2, 3, 3, 2, 2,
        ''
    )";

?>
