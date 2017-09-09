<?php
/**
*   List recent ads.
*
*   @author     Lee Garner <lee@leegarner.com>
*   @copyright  Copyright (c) 2016-2017 Lee Garner <lee@leegarner.com>
*   @package    classifieds
*   @version    1.1.3
*   @license    http://opensource.org/licenses/gpl-2.0.php
*               GNU Public License v2 or later
*   @filesource
*/
namespace Classifieds;

/**
*   @class AdList_Recent
*   @package classifieds
*   Create a list of recently-posted ads
*/
class AdList_Recent extends AdList
{
    /**
    *   Constructor, sets a limit clause
    */
    public function __construct()
    {
        $this->limit_clause = 'LIMIT 20';
        $this->pagename = 'recent';
    }
}

?>
