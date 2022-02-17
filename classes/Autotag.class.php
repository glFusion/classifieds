<?php
/**
 * Handle the headline autotag for the Classifieds plugin.
 * Based on the glFusion headline autotag.
 *
 * @copyright   Copyright (c) 2022 Lee Garner
 * @package     classifieds
 * @version     v1.4.0
 * @since       v1.4.0
 * @license     http://opensource.org/licenses/gpl-2.0.php
 *              GNU Public License v2 or later
 * @filesource
 */
namespace Classifieds;

if (!defined ('GVERSION')) {
    die ('This file can not be used on its own!');
}

/**
 * Headline autotag class.
 * @package classifieds
 */
class Autotag
{
    protected $display    = 10;       // display 10 articles
    protected $sortdir    = 'desc';   // order by - desc or asc
    protected $sortby     = 'add_date';
    protected $template   = 'headlines.thtml';
    protected $category   = 0;
    protected $ad_id      = '';
    protected $textlen    = 65535;

    protected function getOpts(array $opts) : self
    {
        foreach ($opts as $key=>$val) {
            $val = strtolower($val);
            switch ($key) {
            case 'sortdir':
                $valid_order = array('desc','asc');
                if (in_array($val, $valid_order)) {
                    $this->sortdir = $val;
                }
                break;
            case 'sort_by':
                if (in_array($val, array('add_date', 'exp_date'))) {
                    $this->sortby = $val;
                }
                break;
            case 'limit':
                $key = 'display';   // allow 'limit' or 'display' as limiter
            case 'display':
            case 'category':
            case 'textlen':     // limit the amount of description shown
                $this->$key = (int)$val;
                break;
            case 'id':
                $key = 'ad_id';     // allow 'id' or 'ad_id' in autotag
            case 'template':
            case 'ad_id':
                $this->$key = $val;
                break;
            }
        }

        return $this;
    }

    protected function getItems() : array
    {
        global $_TABLES;

        $wheres = array(
            '1=1',
        );
        if ($this->display != 0) {
            $limit = " LIMIT {$this->display}";
        } else {
            $limit = '';
        }
        if ($this->category > 0) {
            $objects = Category::getTree($this->category);
            foreach ($objects as $Obj) {
                $cats[] = $Obj->getID();
            }
            if (!empty($cats)) {
                $cats = DB_escapeString(implode(',', $cats));
                $wheres[] = 'c.cat_id IN (' . $cats . ')';
            }
        }
        if ($this->ad_id != '') {
            $wheres[] = "ad_id = '" . DB_escapeString($this->ad_id) . "'";
        }

        $where = implode(' AND ', $wheres);
    
        $sql = "SELECT ad.*, c.description as type
            FROM {$_TABLES['ad_ads']} ad
            LEFT JOIN {$_TABLES['ad_category']} c
                ON ad.cat_id = c.cat_id
            WHERE $where " . COM_getPermSQL('AND', 0, 2, 'c') .
            " ORDER BY {$this->sortby} {$this->sortdir} $limit";
        $res = DB_query($sql, 1);
        $allItems = DB_fetchAll($res, false);
        return $allItems;
    }


    /**
     * Parse the autotag and render the output.
     *
     * @param   string  $p1         First option after the tag name
     * @param   string  $opts       Name=>Vaue array of other options
     * @param   string  $fulltag    Full autotag string
     * @return  string      Replacement HTML, if applicable.
     */
    public function parse($p1, $opts=array(), $fulltag='')
    {
        global $_CONF, $_CONF_ADVT, $_TABLES, $_USER, $LANG01;

        $cacheID = md5($p1 . $fulltag);
        $retval = Cache::get($cacheID);
        if ($retval !== NULL) {
            return $retval;
        }

        $retval = '';
        $this->getOpts($opts);
        $allItems = $this->getItems();
        $numRows = @count($allItems);

        if ($numRows > 0) {
            $T = new \Template($_CONF_ADVT['path'] . '/templates');
            $T->set_file('page', 'autotag.thtml');
            $T->set_block('autotag', 'tag_data', 'TAG');
            foreach ($allItems as $A) {
                $Ad = Ad::getInstance($A['ad_id']);
                $img_file = Image::getFirst($A['ad_id']);
                $img_url = Image::smallUrl($img_file);
                $image = COM_createImage($img_url);
                 if ($img_file != '') {
                    $T->set_var('img_url', \Classifieds\Image::dispUrl($img_file));
                    $T->set_var('tn_url', \Classifieds\Image::thumbUrl($img_file));
                } else {
                    $T->clear_var('img_url');
                    $T->clear_var('tn_url');
                }
                if (isset($A['description'])) {
                    if (strlen($A['description']) > $this->textlen) {
                        $A['description'] = substr($A['description'], 0, $this->textlen - 3) . ' ...';
                    }
                }

                $T->set_var(array(
                    'ad_id'     => $A['ad_id'],
                    'cat_id'    => $A['cat_id'],
                    'uid'       => $A['uid'],
                    'subject'   => htmlspecialchars($A['subject']),
                    'text'      => COM_truncate($A['description'], 300, '...'),
                    'url'       => COM_sanitizeURL($A['url']),
                    'add_date'  => COM_getUserDateTimeFormat($A['add_date']),
                    'exp_date'  => COM_getUserDateTimeFormat($A['exp_date']),
                    'ad_type'   => htmlspecialchars($A['type']),
                    'pi_url'    => $_CONF_ADVT['url'],
                    'ad_url'    => COM_buildUrl($_CONF_ADVT['url']
                        . '/detail.php?id=' . urlencode($A['ad_id']))
                ) );
                $T->parse('TAG', 'tag_data', true);
            }
            $T->parse('output', 'page');
            $retval = $T->finish($T->get_var('output'));
            Cache::set($cacheID, $retval, array('autotags'));
        }
        return $retval;
    }

}

