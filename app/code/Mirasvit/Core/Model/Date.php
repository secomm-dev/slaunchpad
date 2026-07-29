<?php
/**
 * Mirasvit
 *
 * This source file is subject to the Mirasvit Software License, which is available at https://mirasvit.com/license/.
 * Do not edit or add to this file if you wish to upgrade the to newer versions in the future.
 * If you wish to customize this module for your needs.
 * Please refer to http://www.magentocommerce.com for more information.
 *
 * @category  Mirasvit
 * @package   mirasvit/module-core
 * @version   1.7.15
 * @copyright Copyright (C) 2026 Mirasvit (https://mirasvit.com/)
 */



namespace Mirasvit\Core\Model;

class Date
{
    //from Zend_Date
    const DAY               = 'dd';
    const DAY_SHORT         = 'd';
    const DAY_SUFFIX        = 'SS';
    const DAY_OF_YEAR       = 'D';
    const WEEKDAY           = 'EEEE';
    const WEEKDAY_SHORT     = 'EEE';
    const WEEKDAY_NARROW    = 'E';
    const WEEKDAY_NAME      = 'EE';
    const WEEKDAY_8601      = 'eee';
    const WEEKDAY_DIGIT     = 'e';
    const WEEK              = 'ww';
    const MONTH             = 'MM';
    const MONTH_SHORT       = 'M';
    const MONTH_DAYS        = 'ddd';
    const MONTH_NAME        = 'MMMM';
    const MONTH_NAME_SHORT  = 'MMM';
    const MONTH_NAME_NARROW = 'MMMMM';
    const YEAR              = 'y';
    const YEAR_SHORT        = 'yy';
    const YEAR_8601         = 'Y';
    const YEAR_SHORT_8601   = 'YY';
    const LEAPYEAR          = 'l';
    const MERIDIEM          = 'a';
    const SWATCH            = 'B';
    const HOUR              = 'HH';
    const HOUR_SHORT        = 'H';
    const HOUR_AM           = 'hh';
    const HOUR_SHORT_AM     = 'h';
    const MINUTE            = 'mm';
    const MINUTE_SHORT      = 'm';
    const SECOND            = 'ss';
    const SECOND_SHORT      = 's';
    const MILLISECOND       = 'S';
    const TIMEZONE_NAME     = 'zzzz';
    const DAYLIGHT          = 'I';
    const GMT_DIFF          = 'Z';
    const GMT_DIFF_SEP      = 'ZZZZ';
    const TIMEZONE          = 'z';
    const TIMEZONE_SECS     = 'X';
    const ISO_8601          = 'c';
    const RFC_2822          = 'r';
    const TIMESTAMP         = 'U';
    const ERA               = 'G';
    const ERA_NAME          = 'GGGG';
    const ERA_NARROW        = 'GGGGG';
    const DATES             = 'F';
    const DATE_FULL         = 'FFFFF';
    const DATE_LONG         = 'FFFF';
    const DATE_MEDIUM       = 'FFF';
    const DATE_SHORT        = 'FF';
    const TIMES             = 'WW';
    const TIME_FULL         = 'TTTTT';
    const TIME_LONG         = 'TTTT';
    const TIME_MEDIUM       = 'TTT';
    const TIME_SHORT        = 'TT';
    const DATETIME          = 'K';
    const DATETIME_FULL     = 'KKKKK';
    const DATETIME_LONG     = 'KKKK';
    const DATETIME_MEDIUM   = 'KKK';
    const DATETIME_SHORT    = 'KK';
    const ATOM              = 'OOO';
    const COOKIE            = 'CCC';
    const RFC_822           = 'R';
    const RFC_850           = 'RR';
    const RFC_1036          = 'RRR';
    const RFC_1123          = 'RRRR';
    const RFC_3339          = 'RRRRR';
    const RSS               = 'SSS';
    const W3C               = 'WWW';
    /** \DateTime  */
    protected $date;

    // php ./vendor/bin/phpunit -c dev/tests/unit/phpunit.xml.dist vendor/mirasvit/module-core/src/Core/Test/Unit/Model/DateTest.php
    public function __construct($date = null, $part = null, $locale = null)
    {
        $this->date = new \DateTime();
        if (is_int($date)) {
            $this->date->setTimestamp($date);
            $this->date->setTimezone(new \DateTimeZone("UTC"));
        } elseif ($date != null) {
            $this->date = new \DateTime($date);
        }
    }

    public static function now($locale = null)
    {
        return new Date(time(), self::TIMESTAMP, $locale);
    }

    public function __clone() {
        $this->date = clone $this->date;
    }

    public function getDateTime() {
        return $this->date;
    }

    public function addDay($days) {
        $this->date->add(\DateInterval::createFromDateString("$days days"));
        return $this;
    }

    public function subDay($days)
    {
        $this->date->sub(\DateInterval::createFromDateString("$days days"));
        return $this;
    }

    public function addMonth($months)
    {
        $this->date->add(\DateInterval::createFromDateString("$months months"));
        return $this;
    }

    public function subMonth($months)
    {
        $this->date->sub(\DateInterval::createFromDateString("$months months"));
        return $this;
    }

    public function addYear($years)
    {
        $this->date->add(\DateInterval::createFromDateString("$years years"));
        return $this;
    }

    public function addHour($hours)
    {
        $this->date->add(\DateInterval::createFromDateString("$hours hours"));
        return $this;
    }

    public function subHour($hours)
    {
        $this->date->sub(\DateInterval::createFromDateString("$hours hours"));
        return $this;
    }

    public function subYear($years)
    {
        $this->date->sub(\DateInterval::createFromDateString("$years years"));
        return $this;
    }

    public function addWeek($weeks)
    {
        $this->date->add(\DateInterval::createFromDateString("$weeks weeks"));
        return $this;
    }

    public function subWeek($weeks)
    {
        $this->date->sub(\DateInterval::createFromDateString("$weeks weeks"));
        return $this;
    }

    public function getTimestamp() {
        return $this->date->getTimestamp();
    }

    public function sub($sec) {
        if ($sec instanceof Date) {
            $sec = $sec->getTimestamp();
        }
        $this->date->sub(\DateInterval::createFromDateString("$sec seconds"));
        return $this;
    }

    public function subTimestamp($sec) {
        $this->date->sub(\DateInterval::createFromDateString("$sec seconds"));
        return $this;
    }

    protected function year()
    {
        return $this->date->format("Y");
    }

    protected function month()
    {
        return $this->date->format("m");
    }

    protected function day()
    {
        return $this->date->format("d");
    }

    public function setMonth($month)
    {
        $this->date->setDate($this->year(), $month, $this->day());
        return $this;
    }

    public function setDay($day)
    {
        $this->date->setDate($this->year(), $this->month(), $day);
        return $this;
    }

    public function setTime($time)
    {
        $parts = explode(":", $time);
        $this->date->setTime($parts[0], $parts[1]);
        return $this;
    }

    public function toString($format) {
        $newFormat = $this->convertIsoToPhpFormat($format);
        $res = $this->date->format($newFormat);
        return $res;
    }

    public static function convertIsoToPhpFormat($format)
    {
        $replace = [
            "ddd" => "t",
            "YYYY" => "Y",
            "yyyy" => "Y",
            "MM" => "m",
            "dd" => "d",
            "HH" => "H",
            "mm" => "i",
            "ss" => "s",
            "l" => "L",
            "e" => "w",
        ];
        foreach ($replace as $k=>$v) {
            $format = str_replace($k, $v, $format);
        }
        return $format;
    }

    public function get($part = null, $locale = null) {
        $format = $this->convertIsoToPhpFormat($part);
        return $this->date->format($format);
    }

}
