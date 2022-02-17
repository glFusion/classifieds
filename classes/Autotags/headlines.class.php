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
namespace Classifieds\Autotags;
use Classifieds\Ad;
use Classifieds\Image;
use Classifieds\Category;
use Classifieds\Cache;

if (!defined ('GVERSION')) {
    die ('This file can not be used on its own!');
}

/**
 * Headline autotag class.
 * @package classifieds
 */
class headlines extends \Classifieds\Autotag
{
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
        $this->template = 'headlines.thtml';    // override default
        parent::getOpts($opts);     // populate the standard options

        $cols       = 3;        // default number of columns
        $autoplay   = 'true';   // auto-play by default
        $interval   = 7000;     // move every 7 seconds
        // Now populate options specific to headlines
        foreach ($opts as $key=>$val) {
            $val = strtolower($val);
            switch ($key) {
            case 'autoplay':
                $autoplay = $val ? 'true' : 'false';
                break;
            case 'columns':
                $key = 'cols';
            case 'cols':
            case 'interval':
                $$key = (int)$val;
                break;
            }
        }

        $allItems = $this->getItems();
        $numRows = @count($allItems);

        if ($numRows < $cols) {
            $cols = $numRows;
        }
        if ($cols > 6) {
            $cols = 6;
        }

        if ($numRows > 0) {
            $T = new \Template($_CONF_ADVT['path'] . '/templates/autotags');
            $T->set_file('page', $this->template);
            $T->set_var(array(
                'columns' => $cols,
                'autoplay'  => $autoplay,
                'autoplay_interval' => $interval,
            ) );
            $T->set_block('page', 'headlines', 'hl');
            foreach ($allItems as $A) {
                $Ad = Ad::getInstance($A['ad_id']);
                $img_file = Image::getFirst($A['ad_id']);
                $img_url = Image::smallUrl($img_file);
                $image = COM_createImage($img_url);
                $T->set_var(array(
                    'url'       => Ad::getDetailUrl($A['ad_id']),
                    'text'      => COM_truncate($Ad->getDscp(), 300, '...'),
                    'title'     => $Ad->getSubject(),
                    'thumb_url' => $image,
                ) );
                $T->parse('hl', 'headlines', true);
            }
            $retval = $T->finish($T->parse('output', 'page'));
            Cache::set($cacheID, $retval, array('autotags'));
        }
        return $retval;
    }

}

