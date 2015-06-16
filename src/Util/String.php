<?php

namespace Tailor\Util;

class String
{
    /**
     * Strip delimiters from a string
     *
     * This is useful for parsing single quote delimited strings, which use doubled quotes to represent a quote within
     * the string.
     *
     * @param string $str The string from which delimiters should be stripped.
     * @param string $delim The delimiter.
     * @return [string, string] The string with stripped delimiters, and the remainder of the string.
     */
    public static function stripDelim($str, $delim = "'")
    {
        $len = strlen($str);
        $delimlen = strlen($delim);
        if (!$len || !$delimlen || strpos($str, $delim) !== 0) {
            return false;
        }
        $offset = $delimlen;

        for (;;) {
            if (($pos = strpos($str, $delim, $offset)) === false) {
                return false;
            }

            if (substr($str, $pos, $delimlen * 2) !== "{$delim}{$delim}") {
                break;
            } else {
                $offset = $pos + ($delimlen * 2);
            }
        }

        return [
            str_replace("{$delim}{$delim}", $delim, substr($str, 1, $pos - 1)),
            substr($str, $pos + $delimlen)
        ];
    }

    /**
     * Parse a quoted list, e.g. 'hello','world'
     *
     * @param string $listStr The string containing the list to parse.
     * @param string $delim The string delimiter, typically a single or double quote.
     * @param string $listSep The list separator, typically a comma.
     * @return string[] An array of strings representing the quoted list.
     */
    public static function parseQuotedList($listStr, $delim = "'", $listSep = ",")
    {
        $list = [];
        for (;;) {
            if (($str = self::stripDelim($listStr, $delim)) === false) {
                return false;
            }

            $list[] = $str[0];
            $listStr = ltrim($str[1], $listSep);

            if (empty($listStr)) {
                break;
            }
        }

        return $list;
    }
}
