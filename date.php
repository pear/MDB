<?php
// +----------------------------------------------------------------------+
// | PHP Version 4                                                        |
// +----------------------------------------------------------------------+
// | Copyright (c) 1998-2002 Manuel Lemos, Tomas V.V.Cox,                 |
// | Stig. S. Bakken, Lukas Smith                                         |
// | All rights reserved.                                                 |
// +----------------------------------------------------------------------+
// | MDB is a merge of PEAR DB and Metabases that provides a unified DB   |
// | API as well as database abstraction for PHP applications.            |
// | This LICENSE is in the BSD license style.                            |
// |                                                                      |
// | Redistribution and use in source and binary forms, with or without   |
// | modification, are permitted provided that the following conditions   |
// | are met:                                                             |
// |                                                                      |
// | Redistributions of source code must retain the above copyright       |
// | notice, this list of conditions and the following disclaimer.        |
// |                                                                      |
// | Redistributions in binary form must reproduce the above copyright    |
// | notice, this list of conditions and the following disclaimer in the  |
// | documentation and/or other materials provided with the distribution. |
// |                                                                      |
// | Neither the name of Manuel Lemos, Tomas V.V.Cox, Stig. S. Bakken,    |
// | Lukas Smith nor the names of his contributors may be used to endorse |
// | or promote products derived from this software without specific prior|
// | written permission.                                                  |
// |                                                                      |
// | THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS  |
// | "AS IS" AND ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT    |
// | LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS    |
// | FOR A PARTICULAR PURPOSE ARE DISCLAIMED.  IN NO EVENT SHALL THE      |
// | REGENTS OR CONTRIBUTORS BE LIABLE FOR ANY DIRECT, INDIRECT,          |
// | INCIDENTAL, SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES (INCLUDING, |
// | BUT NOT LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES; LOSS|
// |  OF USE, DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER CAUSED  |
// | AND ON ANY THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT          |
// | LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN ANY|
// | WAY OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED OF THE          |
// | POSSIBILITY OF SUCH DAMAGE.                                          |
// +----------------------------------------------------------------------+
// | Author: Lukas Smith <smith@dybnet.de>                                |
// +----------------------------------------------------------------------+
//
// $Id$
//

require_once 'PEAR.php';

/**
* Several methods to convert the MDB native timestamp format (ISO based)
* to and from data structures that are convienient to worth with in side of php.
* For more complex date arithmetic please take a look at the Date package in PEAR
*
* @package MDB
* @author  Lukas Smith <smith@dybnet.de>
 */
class MDB_date extends PEAR {
    // }}}
    // {{{ mdbNow()

    /**
     * return the current datetime
     *
     * @return string current datetime in the MDB format
     * @access public
     */
    function mdbNow()
    {
        return(strftime('%Y-%m-%d %H:%M:%S'));
    }

    // }}}
    // {{{ mdbToday()

    /**
     * return the current date
     *
     * @return string current date in the MDB format
     * @access public
     */
    function mdbToday()
    {
        return(strftime('%Y-%m-%d'));
    }

    // }}}
    // {{{ mdbTime()

    /**
     * return the current time
     *
     * @return string current time in the MDB format
     * @access public
     */
    function mdbTime()
    {
        return(strftime('%H:%M:%S'));
    }

    // }}}
    // {{{ date2Mdbstamp()

    /**
     * convert a date into a MDB timestamp
     *
     * @param integer $hour hour of the date
     * @param integer $minute minute of the date
     * @param integer $second second of the date
     * @param integer $month month of the date
     * @param integer $day day of the date
     * @param integer $year year of the date
     * @return string a valid MDB timestamp
     * @access public
     */
    function date2Mdbstamp($hour = NULL, $minute = NULL, $second = NULL,
        $month = NULL, $day = NULL, $year = NULL)
    {
        return unix2Mdbstamp(mktime($hour, $minute, $second, $month, $day, $year));
    }

    // }}}
    // {{{ unix2Mdbstamp()

    /**
     * convert a unix timestamp into a MDB timestamp
     *
     * @param integer $unix_timestamp a valid unix timestamp
     * @return string a valid MDB timestamp
     * @access public
     */
    function unix2Mdbstamp($unix_timestamp)
    {
        return date('Y-m-d H:i:s', $unix_timestamp);
    }

    // }}}
    // {{{ mdbstamp2Unix()

    /**
     * convert a MDB timestamp into a unix timestamp
     *
     * @param integer $mdb_timestamp a valid MDB timestamp
     * @return string unix timestamp with the time stored in the MDB format
     * @access public
     */
    function mdbstamp2Unix($mdb_timestamp)
    {
        $arr = mdbstamp2Unix($mdb_timestamp);
        return mktime ($arr['hour'], $arr['minute'], $arr['second'],
            $arr['month'], $arr['day'], $arr['year']);
    }

    // }}}
    // {{{ mdbstamp2Date()

    /**
     * convert a MDB timestamp into an array containing all
     * values necessary to pass to php's date() function
     *
     * @param integer $mdb_timestamp a valid MDB timestamp
     * @return array with the time stored in the MDB format
     * @access public
     */
    function mdbstamp2Unix($mdb_timestamp)
    {
        // 0123456789012345678
        // YYYY-MM-DD HH:MM:SS
        $arr['year'] = substr($mdb_timestamp, 0, 4);
        $arr['month'] = substr($mdb_timestamp, 5, 2);
        $arr['day'] = substr($mdb_timestamp, 8, 2);
        $arr['hour'] = substr($mdb_timestamp, 11, 2);
        $arr['minute'] = substr($mdb_timestamp, 14, 2);
        $arr['second'] = substr($mdb_timestamp, 17, 2);
        return $arr;
    }
}
?>