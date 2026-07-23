<?php
/**
 * Module base of Secomm package
 * @package Secomm_Base
 * @author Bao Le
 * @date 2022
 */
namespace Secomm\Base;

class SecommStaticFunctions {
    public static function log($file, $message)
    {
        $formattedDate = date('d-m-Y H:i:s');
        file_put_contents(BP . "/var/log/$file", $formattedDate . ': ' . $message . "\n", FILE_APPEND);
    }
}
