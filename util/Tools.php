<?php namespace LaravelAddons\Util;



class Tools {


    public static function varDump($data) {
        echo "<pre>";
        var_dump($data);
        echo "</pre>";
    }

    public static function printData($data) {
        echo "<pre>";
        print_r($data);
        echo "</pre>";
    }

    public static function is_digit($str) {
        return ctype_digit((string)$str);
    }


    public static function is_pdigit($str) {
        return (ctype_digit((string)$str) && $str>=0);
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


    public static function isCsrfTokenOk() {
        return \Session::token() == \Input::get('_token');
    }

}


