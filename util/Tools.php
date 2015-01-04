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
        if (func_num_args()>1)
        {
            foreach(func_num_args() as $arg)
            {
                if (! self::is_pdigit($arg)) return false;
            }
            return true;
        }
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


    /**
     * @param array $data
     * @return bool
     */
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


    /**
     * @param $data
     * @return array
     */
    public static function filter_pdigit($data)
    {
        // array input
        if (is_array($data) && count($data))
        {
            return static::filter_pdigit_array($data);
        }

        // string csv input
        elseif (is_string($data) && strlen($data))
        {
            return static::filter_pdigit_csv($data);
        }

        return [];
    }


    /**
     * @param array $data
     * @return array
     */
    public static function filter_pdigit_array(array $data)
    {
        foreach($data as $k => $item)
        {
            if (!static::is_pdigit($item)) unset($data[$k]);
        }
        return $data;
    }


    /**
     * @param $data
     * @return array
     */
    public static function filter_pdigit_csv($data)
    {
        $data = explode(',', $data);
        return static::filter_pdigit_array($data);
    }


}


