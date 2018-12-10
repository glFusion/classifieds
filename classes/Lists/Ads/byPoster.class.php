<?php
/**
 * List ads by poster.
 *
 * @author      Lee Garner <lee@leegarner.com>
 * @copyright   Copyright (c) 2016-2017 Lee Garner <lee@leegarner.com>
 * @package     classifieds
 * @version     v1.1.3
 * @license     http://opensource.org/licenses/gpl-2.0.php
 *              GNU Public License v2 or later
 * @filesource
 */
namespace Classifieds\Lists\Ads;

/**
* Create a list of ads for a specific poster.
* @package classifieds
*/
class byPoster extends \Classifieds\Lists\Ads
{
    /**
     * Constructor, sets the user ID.
     *
     * @param   integer $uid    User ID
     */
    public function __construct($uid = 0)
    {
        if ($uid > 0) {
            $this->where_clause = 'uid = ' . (int)$uid;
        }
        $this->pagename = 'byposter';
    }
}

?>
