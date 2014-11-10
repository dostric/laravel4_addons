<?php namespace LaravelAddons\Util;



class Tools {


    public static function varDump($data)
    {
        echo "<pre>";
        var_dump($data);
        echo "</pre>";
    }


    public static function printData($data)
    {
        echo "<pre>";
        print_r($data);
        echo "</pre>";
    }


    public static function is_digit($str)
    {
        return ctype_digit((string)$str);
    }


    public static function is_pdigit($str)
    {
        return (ctype_digit((string)$str) && $str>=0);
    }


    public static function is_date($date)
    {
        if ($date && preg_match('/^[0-9-]+$/', $date))
        {
            return
                ($_tmp = explode('-', $date)) &&
                count($_tmp) == 3 &&
                checkdate((int)$_tmp[1], (int)$_tmp[2], (int)$_tmp[0]);
        }

        return false;

    }


    public static function is_pdigit_array(array $data)
    {
        foreach($data as $item)
        {
            if (!static::is_pdigit($item)) return false;
        }
        return true;
    }


    public static function is_pdigit_csv($data)
    {
        $data = explode(',', $data);
        foreach($data as $item)
        {
            if (!static::is_pdigit($item)) return false;
        }
        return true;
    }


    function filter_pdigit_csv($data)
    {
        $data = explode(',', $data);
        foreach($data as $k => $item)
        {
            if (!static::is_pdigit($item)) unset($data[$k]);
        }
        return $data;
    }


    /**
     * @param string|array $csv
     * @return array
     */
    public static function parseIntArrayFromCSV($csv) {

        $result = array();

        if (!is_array($csv)) {
            $csv = explode(',', $csv);
        }

        if (is_array($csv) && count($csv)) {

            foreach($csv as $item) {
                if (static::is_pdigit($item = trim($item))) {
                    $result[] = $item;
                }
            }

        }

        return $result;

    }


    public static function isCsrfTokenOk()
    {
        $one = \Session::token() == \Input::get('_token');
        $two = \Session::token() == \Request::header('_token', null);
        return $one || $two;
    }

}


