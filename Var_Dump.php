<?php
/* vim: set expandtab tabstop=4 shiftwidth=4: */
// +----------------------------------------------------------------------+
// | PHP version 4.0                                                      |
// +----------------------------------------------------------------------+
// | Copyright (c) 1997, 1998, 1999, 2000, 2001 The PHP Group             |
// +----------------------------------------------------------------------+
// | This source file is subject to version 2.0 of the PHP license,       |
// | that is bundled with this package in the file LICENSE, and is        |
// | available at through the world-wide-web at                           |
// | http://www.php.net/license/2_02.txt.                                 |
// | If you did not receive a copy of the PHP license and are unable to   |
// | obtain it through the world-wide-web, please send a note to          |
// | license@php.net so we can mail you a copy immediately.               |
// +----------------------------------------------------------------------+
// | Authors: Original Author <frederic.poeydomenge@free.fr>              |
// +----------------------------------------------------------------------+
//
// $Id$

require_once 'PEAR.php';

// +-----------------------------------+
// | SYNOPSIS                          |
// +-----------------------------------+
// |   Var_Dump::display("varNames");   |
// | or                                |
// |   $var=Var_Dump::dump("varNames"); |
// |   $var->display();                |
// +-----------------------------------+


define("VAR_DUMP_TYPE_LONG",                     "Long");
define("VAR_DUMP_TYPE_INT",                       "Int");
define("VAR_DUMP_TYPE_INTEGER",               "Integer");
define("VAR_DUMP_TYPE_DOUBLE",                 "Double");
define("VAR_DUMP_TYPE_FLOAT",                   "Float");
define("VAR_DUMP_TYPE_REAL",                     "Real");
define("VAR_DUMP_TYPE_STRING",                 "String");
define("VAR_DUMP_TYPE_BOOLEAN",               "Boolean");
define("VAR_DUMP_TYPE_NUMERIC",               "Numeric");
define("VAR_DUMP_TYPE_RESOURCE",             "Resource");
define("VAR_DUMP_TYPE_SCALAR",                 "Scalar");
define("VAR_DUMP_TYPE_NULL",                     "Null");
define("VAR_DUMP_TYPE_UNKNOWN",          "Unknown type");

define("VAR_DUMP_EMPTY_ARRAY",            "Empty array");
define("VAR_DUMP_NOT_PARSED",              "Not parsed");
define("VAR_DUMP_NON_EXISTENT", "Non-existent variable");

define("VAR_DUMP_BOOLEAN_TRUE",                  "TRUE");
define("VAR_DUMP_BOOLEAN_FALSE",                "FALSE");

define("VAR_DUMP_OBJECT_CLASS_NAME",       "Class name");
define("VAR_DUMP_OBJECT_PARENT",         "Parent class");
define("VAR_DUMP_OBJECT_CLASS_VARS",       "Class vars");
define("VAR_DUMP_OBJECT_CLASS_METHODS", "Class methods");
define("VAR_DUMP_OBJECT_OBJECT_VARS",     "Object vars");

define("VAR_DUMP_ARRAY_TYPE",                    "type");
define("VAR_DUMP_ARRAY_VALUE",                  "value");

define("VAR_DUMP_DISPLAY_MODE_HTML",             "html");
define("VAR_DUMP_DISPLAY_MODE_TEXT",             "text");


/**
* Displays informations about the values of variables on a graphical way
* - If given a simple variable (string, integer, double, ressource), the
* value itself is printed.
* - If given an array, it is explored recursively and values are presented
* in a format that shows keys and elements.
* - If given an object, informations about the object and the class
* are printed.
*/
class Var_Dump extends PEAR
{


    /**
    * @var array $_VarDumpNames Names of the submitted variables
    */
    var $_VarDumpNames = array();

        
    /**
    * @var array $_VarDumpArray An array representing the structure of all
    *                           the submitted variables
    */
    var $_VarDumpArray = array();


    /**
    * @var string $_VarDumpDisplayColor1      Color of the odd lines
    * @var string $_VarDumpDisplayColor2      Color of the even lines
    * @var string $_VarDumpDisplayBorderColor Color of the border
    * @var string $_VarDumpDisplayBorderSize  Size of the border
    * @var string $_VarDumpDisplayFontFace    If not empty, font face
    * @var string $_VarDumpDisplayFontSize    If not empty, font size
    * @var string $_VarDumpDisplayFontColor   If not empty, font color
    * @var string $_VarDumpDisplayCellpadding If not empty, cell padding
    * @var string $_VarDumpDisplayCellspacing If not empty, cell spacing
    */
    var $_VarDumpDisplayColor1      = "#dddddd";
    var $_VarDumpDisplayColor2      = "#eeeeee";
    var $_VarDumpDisplayBorderColor = "#444444";
    var $_VarDumpDisplayBorderSize  = "1";
    var $_VarDumpDisplayFontFace    = "";
    var $_VarDumpDisplayFontSize    = "";
    var $_VarDumpDisplayFontColor1  = "";
    var $_VarDumpDisplayFontColor2  = "red";
    var $_VarDumpDisplayCellpadding = "4";
    var $_VarDumpDisplayCellspacing = "0";


    /**
    * @var string $_VarDumpDisplayFont1Start Text : Starting tag <font...> or empty
    * @var string $_VarDumpDisplayFont1End   Text : Ending tag </font> or empty
    * @var string $_VarDumpDisplayFont2Start Value : Starting tag <font...> or empty
    * @var string $_VarDumpDisplayFont2End   Value : Ending tag </font> or empty
    */
    var $_VarDumpDisplayFont1Start   = "";
    var $_VarDumpDisplayFont1End     = "";
    var $_VarDumpDisplayFont2Start   = "";
    var $_VarDumpDisplayFont2End     = "";

  
    // {{{ dump()
    /**
    * dump() - Dump informations about a variable
    *
    * This method dump informations about the values of a variable, arrays
    * and objects being explored recursively, and returns an object
    *
    * @param $variable      A variable to explore
    * @param string $except If not empty, name of the key not to
    *                       explore, to avoid parsing references to itself
    * @return               dumpVar Object
    */
    function dump($variable, $except = "")
    {
        @$obj = & new Var_Dump;
        $obj->_populate($variable, $except);
        return $obj;
    }
    // }}}


    // {{{ display()
    /**
    * display() - Displays informations about a variable
    *
    * This method displays informations about the values of a variable on a
    * graphical way, arrays and objects being explored recursively
    *
    * @param $variable      A variable to explore
    * @param string $mode   Display mode : text or html
    * @param string $skin   Skin to use : default, green, blue, red...
    * @param string $except If not empty, name of the key not to
    *                       explore, to avoid parsing references to itself
    */
    function display($variable = "", $mode = VAR_DUMP_DISPLAY_MODE_HTML, $skin = "default", $except = "")
    {
        $error = false;
        $set_skin = true;
        
        if (empty($variable)) {
            if (isset($this)) {
                $obj = $this;
                $set_skin = false;
                $error = false;
            } else {
                $error = true;
            }
        } else {        
            $obj = Var_Dump::dump($variable, $except);
        }

        if (!$error) {
            if ($set_skin) {
                $obj->setSkin($skin);
            }
            switch($mode) {
            case VAR_DUMP_DISPLAY_MODE_HTML:
                $obj->_generate_font_tag();
                echo $obj->_displayTableHTML($obj->_VarDumpArray);
                break;
            case VAR_DUMP_DISPLAY_MODE_TEXT:
                echo $obj->_displayTableText($obj->_VarDumpArray);
                break;
            default:
                $obj->_generate_font_tag();
                echo $obj->_displayTableHTML($obj->_VarDumpArray);
                break;
            }
        }
    }
    // }}}
            

    // {{{ _populate()
    /**
    * _populate() - Fills the informations concerning a single variable
    *
    * This method fills the local array $this->_VarDumpArray with the
    * informations concerning a single variable
    * When parsing $GLOBALS variable, avoid parsing recursively the
    * reference to itself.
    *
    * @param $variable      The variable to explore
    * @param string $except If not empty, name of the key not to
    *                       explore, to avoid parsing references to itself
    */
    function _populate($variable, $except = "")
    {
        if (empty($except)) {
            $w_except = "";
        } else {
            $w_except = " ".trim($except);
        }
        if ( // $variable == $GLOBALS does not work
            is_array($variable) and
            isset($variable["HTTP_POST_VARS"]) and
            isset($variable["HTTP_GET_VARS"]) and
            isset($variable["HTTP_COOKIE_VARS"]) and
            isset($variable["HTTP_SERVER_VARS"]) and
            isset($variable["HTTP_ENV_VARS"])
        ) {
            $this->_VarDumpArray[] = $this->_parseArray($GLOBALS, "GLOBALS".$w_except);
        } else {
            if (isset($variable)) {
                if (is_array($variable)) {
                    $this->_VarDumpArray[] = $this->_parseArray($variable, $except);
                } else if (is_object($variable)) {
                    $this->_VarDumpArray[] = $this->_parseObject($variable);
                } else {
                    $this->_VarDumpArray[] = $this->_parseVariable($variable);
                }
            } else {
                $this->_VarDumpArray[] =
                    array(
                        VAR_DUMP_ARRAY_TYPE  => VAR_DUMP_TYPE_NULL,
                        VAR_DUMP_ARRAY_VALUE => VAR_DUMP_NON_EXISTENT
                    );
            }
        }        
    }
    // }}}


    // {{{ _parseVariable()
    /**
    * _parseVariable() - Parse (recursively) a variable
    *
    * This method parse a variable, either returning informations about
    * this variable, or in the case of an object or array, returning
    * recursive informations about this variable.
    *
    * @param $variable A variable to explore
    */
    function _parseVariable($variable)
    {
        if (is_object($variable)) {
            return $this->_parseObject($variable);
        } elseif (is_array($variable)) {
            return $this->_parseArray($variable);
        } elseif (is_long($variable)) {
            $type = VAR_DUMP_TYPE_LONG;
        } elseif (is_int($variable)) {
            $type = VAR_DUMP_TYPE_INT;
        } elseif (is_integer($variable)) {
            $type = VAR_DUMP_TYPE_INTEGER;
        } elseif (is_double($variable)) {
            $type = VAR_DUMP_TYPE_DOUBLE;
        } elseif (is_float($variable)) {
            $type = VAR_DUMP_TYPE_FLOAT;
        } elseif (is_real($variable)) {
            $type = VAR_DUMP_TYPE_REAL;
        } elseif (is_string($variable)) {
            $type = VAR_DUMP_TYPE_STRING."[".strlen($variable)."]";
        } elseif (is_bool($variable)) {
            $type = VAR_DUMP_TYPE_BOOLEAN;
            if ($variable==true) {
                $variable = VAR_DUMP_BOOLEAN_TRUE;
            } else {
                $variable = VAR_DUMP_BOOLEAN_FALSE;
            }            
        } elseif (is_numeric($variable)) {
            $type = VAR_DUMP_TYPE_NUMERIC;
        } elseif (is_resource($variable)) {
            $type = VAR_DUMP_TYPE_RESOURCE."[".get_resource_type($variable)."]";
        } elseif (is_scalar($variable)) {
            $type = VAR_DUMP_TYPE_SCALAR;
        } elseif (is_null($variable)) {
            $type = VAR_DUMP_TYPE_NULL;
            $variable = "Null";
        } else {
            $type = VAR_DUMP_TYPE_UNKNOWN."[".gettype($variable)."]";
        }
        return
            array(
                VAR_DUMP_ARRAY_TYPE  => $type,
                VAR_DUMP_ARRAY_VALUE => $variable
            );
    }
    // }}}


    // {{{ _parseArray()
    /**
    * _parseArray() - Parse recursively an array
    *
    * This method returns recursive informations on an array :
    * structure, keys and values
    *
    * @param array  $array  An array to explore
    * @param string $except If not empty, name of the key not to
    *                       explore, to avoid parsing references to itself
    */
    function _parseArray($array, $except = "")
    {
        if (is_string($except)) {
            $except_array = explode(" ",$except);
        } else {
            $except_array = array();
        }
        
        if (!is_array($array)) {    
            return $this->_parseVariable($array);
        } else {
            if (count($array)==0) {
                return 
                    array(
                        VAR_DUMP_ARRAY_TYPE  => VAR_DUMP_EMPTY_ARRAY,
                        VAR_DUMP_ARRAY_VALUE => ""
                    );        
            } else {
                $localArray = array();
                foreach($array as $key => $value) {
                    if (! (in_array($key, $except_array, TRUE))) {
                        $localArray[$key] = $this->_parseArray($value, $except);
                    } else {
                        $localArray[$key] = array(
                            VAR_DUMP_ARRAY_TYPE  => VAR_DUMP_NOT_PARSED,
                            VAR_DUMP_ARRAY_VALUE => ""
                        );        
                    }
                }
                return $localArray;
            }
        }
    }
    // }}}


    // {{{ _parseObject()
    /**
    * _parseObject() - Returns informations on an object
    *
    * This method returns informations on an object and its class :
    * default class variables and methods, current object state
    *
    * @param object $object An object to explore
    */
    function _parseObject($object)
    {
        if (!is_object($object)) {    
            return $this->_parseVariable($object);
        } else {
            $className    = get_class($object);
            $parent       = get_parent_class($object);
            $classVars    = get_class_vars($className);
            $classMethods = get_class_methods($className);
            $objectVars   = get_object_vars($object);        
            return
                array(
                    VAR_DUMP_OBJECT_CLASS_NAME    => $this->_parseVariable($className),
                    VAR_DUMP_OBJECT_PARENT        => $this->_parseVariable($parent),
                    VAR_DUMP_OBJECT_CLASS_VARS    => $this->_parseArray($classVars),
                    VAR_DUMP_OBJECT_CLASS_METHODS => $this->_parseArray($classMethods),
                    VAR_DUMP_OBJECT_OBJECT_VARS   => $this->_parseArray($objectVars)
                );
        }
    }
    // }}}


    // {{{ _isSingleVariable()
    /**
    * _isSingleVariable() - Tells if a variable is a single variable
    *
    * This method tells if a variable is a single variable (long,
    * string, double...) or a more complex one (array, object...)
    *
    * @param $variable The variable to check
    * @return          True if it's a single variable
    *                  False if it's a more complex variable
    */
    function _isSingleVariable($variable)
    {
        return (
            is_array($variable) and
            count($variable)==2 and
            isset($variable[VAR_DUMP_ARRAY_TYPE]) and
            isset($variable[VAR_DUMP_ARRAY_VALUE])
        );
    }
    // }}}


    // {{{ _generate_font_tag()
    /**
    * _generate_font_tag() - Generates the font tags <font...>
    *
    * This method generates the font tags <font...> with the
    * font size, color and face values choosen. If none of the
    * font parameters was modified, use the default font.
    */
    function _generate_font_tag()
    {
        $font1 = "";
        $font2 = "";
        if (!empty($this->_VarDumpDisplayFontFace)) {
            $font1 .= " face=".$this->_VarDumpDisplayFontFace;
            $font2 .= " face=".$this->_VarDumpDisplayFontFace;
        }
        if (!empty($this->_VarDumpDisplayFontSize)) {
            $font1 .= " size=".$this->_VarDumpDisplayFontSize;
            $font2 .= " size=".$this->_VarDumpDisplayFontSize;
        }
        if (!empty($this->_VarDumpDisplayFontColor1)) {
            $font1 .= " color=".$this->_VarDumpDisplayFontColor1;
        }
        if (!empty($this->_VarDumpDisplayFontColor2)) {
            $font2 .= " color=".$this->_VarDumpDisplayFontColor2;
        }
        if (!empty($font1)) {
            $this->_VarDumpDisplayFont1Start = "<font".$font1.">";
            $this->_VarDumpDisplayFont1End   = "</font>";
        } else {
            $this->_VarDumpDisplayFont1Start = "";
            $this->_VarDumpDisplayFont1End   = "";
        }
        if (!empty($font2)) {
            $this->_VarDumpDisplayFont2Start = "<font".$font2.">";
            $this->_VarDumpDisplayFont2End   = "</font>";
        } else {
            $this->_VarDumpDisplayFont2Start = "";
            $this->_VarDumpDisplayFont2End   = "";
        }
    }


    // {{{ _displayTableText()
    /**
    * _displayTableText() - Displays informations in text format
    *
    * This method displays all the informations collected on
    * the submitted variables, in a text format
    *
    * @param array $array The _VarDumpArray structure to display
    */
    function _displayTableText($array)
    {
        echo "<pre>";    
        var_dump($array);
        echo "</pre>";    
    }
    // }}}


    // {{{ _displayTableHTML()
    /**
    * _displayTableHTML() - Displays informations in HTML format
    *
    * This method displays all the informations collected on
    * the submitted variables, in a graphical format
    *
    * @param array $array The _VarDumpArray structure to display
    * @param int   $level Level of recursion
    */      
    function _displayTableHTML($array, $level = 0)
    {    
        $table = "";
        
        $table_header = <<<EOL
            <table border=0 cellpadding=$this->_VarDumpDisplayBorderSize cellspacing=0 bgcolor=$this->_VarDumpDisplayBorderColor>
            <tr>
             <td>
            <table border=0 cellpadding=$this->_VarDumpDisplayCellpadding cellspacing=$this->_VarDumpDisplayCellspacing>
EOL;
        $table_footer = <<<EOL
            </table>
             </td>
            </tr>
            </table>\n
EOL;
        if (is_array($array)) {
            if ($this->_isSingleVariable($array)) {
                $table .= $this->_displayCellHTML($array[VAR_DUMP_ARRAY_TYPE],$array[VAR_DUMP_ARRAY_VALUE]);                
            } else {

                if ($level==0 and count($array)==1) {
                    if ($this->_isSingleVariable($array[0])) {
                        $table .= $table_header;
                    }
                    $table .= "<tr bgcolor=".$this->_VarDumpDisplayColor1." valign=\"top\">";
                    $table .= "<td>".$this->_displayTableHTML($array[0], $level+1)."</td>";
                    $table .= "</tr>\n";
                    if ($this->_isSingleVariable($array[0])) {
                        $table .= $table_footer;
                    }
                } else {
                    $c = 0;
                    $table .= $table_header;
                    foreach($array as $key => $value) {
                        if (is_array($value)) {
                            if ($this->_isSingleVariable($value)) {
                                $countValue = "";
                            } else {
                                $countValue = "&nbsp;(".count($value).")";
                            }
                        } else {
                            $countValue = "";
                        }
                        if ($c==0) {
                            $bg = $this->_VarDumpDisplayColor1;
                        } else {
                            $bg = $this->_VarDumpDisplayColor2;
                        }
                        $table .= "<tr bgcolor=".$bg." valign=\"top\">";
                        $table .= "<td>".$this->_VarDumpDisplayFont1Start.$key.$countValue.$this->_VarDumpDisplayFont1End."</td>";
                        $table .= "<td>".$this->_displayTableHTML($value, $level+1)."</td>";
                        $table .= "</tr>\n";
                        $c = 1-$c;
                    }
                    $table .= $table_footer;
                }
            }
        } else {        
            $table .= $array;
        }
        return $table;
    }
    // }}}


    // {{{ _displayCellHTML()
    /**
    * _displayCellHTML() - Displays informations on a single variable
    *
    * This method displays all the informations collected on
    * a single variable (type and value), in a graphical format
    *
    * @param string $type  Type of the variable
    * @param string $value Value of the variable
    */
    function _displayCellHTML($type,$value)
    {
        $cell = $this->_VarDumpDisplayFont1Start;
        $cell .= $type;
        if ($type!=VAR_DUMP_EMPTY_ARRAY and $type!=VAR_DUMP_NOT_PARSED) {
            if (!empty($value)) {
                $cell .= " (".$this->_VarDumpDisplayFont2Start.$value.$this->_VarDumpDisplayFont2End.")";
            } else {
                $cell .= " ()";
            }
        }
        $cell .= $this->_VarDumpDisplayFont1End;
        return $cell;
    }
    // }}}


    // {{{ setDisplayMode()
    /**
    * setDisplayMode() - Set visual parameters
    *
    * This method set visual parameters for the rendering : border color
    * and size, cells colors, padding and spacing of the table, font face,
    * size and color.
    *
    * @param string $bordercolor Color of the border
    * @param string $col1        Color of odd lines
    * @param string $col2        Color of even lines
    * @param string $bordersize  Size of the border
    * @param string $cellpadding Cellpadding of the table
    * @param string $cellspacing Cellspacing of the table
    * @param string $fontface    Font face to use
    * @param string $fontsize    Font size
    * @param string $fontcol1    Font color : normal text
    * @param string $fontcol2    Font color : value
    */
    function setDisplayMode($bordercolor = "#444444", $col1 = "#dddddd", $col2 = "#eeeeee",
        $bordersize = "1", $cellpadding = "4", $cellspacing = "0",
        $fontface = "", $fontsize = "", $fontcol1 = "", $fontcol2 = "red")
    {
        $this->_VarDumpDisplayBorderColor = $bordercolor;
        $this->_VarDumpDisplayColor1      = $col1;
        $this->_VarDumpDisplayColor2      = $col2;
        $this->_VarDumpDisplayBorderSize  = $bordersize;
        $this->_VarDumpDisplayCellpadding = $cellpadding;
        $this->_VarDumpDisplayCellspacing = $cellspacing;
        $this->_VarDumpDisplayFontFace    = $fontface;
        $this->_VarDumpDisplayFontSize    = $fontsize;
        $this->_VarDumpDisplayFontColor1  = $fontcol1;
        $this->_VarDumpDisplayFontColor2  = $fontcol2;
    }
    // }}}
    

    // {{{ setSkin()
    /**
    * setSkin() - Set visual parameters using a skin model
    *
    * This method set visual parameters using a skin model
    *
    * @param string $skin Name of the skin
    */
    function setSkin($skin = "default")
    {
        switch($skin) {
        case "default":
            $this->setDisplayMode();
            break;
        case "green":
            $this->setDisplayMode("#2BB036","#55CC5F","#7FE888","1","4","0","","","#FFFFFF","#FF00CC");
            break;
        case "red":
            $this->setDisplayMode("#B02B36","#CC555F","#E87F88","1","4","0","","","#FFFFFF","#00FFCC");
            break;
        case "blue":
            $this->setDisplayMode("#362BB0","#5F55CC","#887FE8","1","4","0","","","#FFFFFF","#CCFF00");
            break;
        default:
            $this->setDisplayMode();
            break;
        }
    }
    // }}}
        
}

?>