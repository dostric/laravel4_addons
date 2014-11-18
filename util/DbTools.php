<?php namespace LaravelAddons\Util;

use \Cache;
use \Config;
use \DB;
use Illuminate\Support\Collection;
use Mars\Util\InformationSchema;

class DbTools {

    public static $sqlTotalRows;

    protected static $informationSchema;



    public static function dataMap($type, $items, $valueCol, $labelCol, $addItem = false)
    {
        $result = array();

        foreach($items as $xx => $item)
        {
            if (is_object($item)) $item = (array)$item;
            $result[] = array(
                'value' => $item[$valueCol],
                'label' => $item[$labelCol],
                'item'  => $addItem ? $item : null
            );
        }

        return $result;
    }


    public static function getSchema()
    {
        return self::$informationSchema ?: self::$informationSchema = new InformationSchema(\DB::getPdo());
    }


    public static function listTables($fresh = false)
    {
        $cacheKey = 'db_' . \Config::get('app.database.default') . '_tables';

        if ( !$fresh && Cache::has($cacheKey) )
        {
            return Cache::get($cacheKey);
        }

        $tables = self::getSchema()->getTables();

        Cache::put($cacheKey, $tables, 1200);

        return $tables;
    }

    /**
     * Gets the table schema. By default it is cached.
     *
     * @param string $table
     * @param bool $fresh
     * @return \Doctrine\DBAL\Schema\Column[]
     */
    public static function getTableSchema($table, $fresh = false)
    {
        $cacheKey = 'table_schema_'.$table;

        if ($fresh)
        {
            Cache::forget($cacheKey);
        }

        $tableColumns = Cache::get($cacheKey, null);

        if ($tableColumns === null)
        {
            $tableColumns = self::getSchema()->getColumns($table);

            Cache::put($cacheKey, $tableColumns, 24*60);
        }

        return $tableColumns;
    }


    /**
     * Returns a table schema. Schema is an array (keys are column names) and is cached.
     *
     * @param string $table
     * @param bool $fresh
     * @return array
     */
    public static function getFormSchema($table, $fresh = false)
    {
        return self::getTableSchema($table);

        $cacheKey = 'table_form_schema_'.$table;

        if ($fresh)
        {
            Cache::forget($cacheKey);
        }

        $schema = Cache::get($cacheKey, null);

        if ($schema === null)
        {
            $objSchema = static::getTableSchema($table, $fresh);

            $schema = array();

            foreach($objSchema as $k => $item)
            {
                $item = $item->toArray();
                $item['type'] = $item['type']->getName();
                $schema[$item['name']] = $item;
            }

            Cache::put($cacheKey, $schema, 60);
        }

        return $schema;

    }


    /**
     * Gets the table foreign keys. Method is not cached.
     *
     * @param $table
     *
     * @return array
     */
    public static function getTableKeys($table)
    {
        return self::getSchema()->getForeignKeys($table);
    }


    /**
     * Gets the table foreign keys. By default the keys are cached.
     *
     * @param $table
     * @param bool $fresh
     * @throws \Exception
     * @return array
     */
    public static function fKeys($table, $fresh = false)
    {
        static $cacheData = [];

        $cacheKey = 'table_fkeys_'.$table;

        if ($fresh)
        {
            unset($cacheData[$table]);
            \Cache::forget($cacheKey);
        }

        // we already loaded the key
        if (array_key_exists($table, $cacheData))
        {
            return $cacheData[$table];
        }

        // do we have it in the cache
        $cacheData[$table] = Cache::get($cacheKey, null);

        // none cached, load the keys
        if ($cacheData[$table] === null)
        {
            $cacheData[$table] = [];

            // the keys are not stored jet
            foreach (static::getTableKeys($table) as $data)
            {
                $cacheData[$table][$data['column']] = (object)[
                    'table' => $data['referenced_table'],
                    'column' => $data['referenced_column']
                ];
            }

            Cache::put($cacheKey, $cacheData[$table], 24 * 60);
        }

        return $cacheData[$table];
    }


    public static function getTotalRecords($resetTotal = false)
    {
        if ($resetTotal) static::$sqlTotalRows = null;
        return DB::select('SELECT FOUND_ROWS() as total')[0]->total;
    }


    // \Illuminate\Database\Eloquent\Model
    public static function paginate($query, $page = 1, $perPage = 10)
    {
        if (str_contains($page, ':'))
        {
            list($page, $perPage) = explode(':', $page);
        }

        if (Tools::is_pdigit($page) && Tools::is_pdigit($perPage))
        {
            $query = $query->forPage($page, $perPage);
        }

        return $query;
    }


    // \Illuminate\Database\Eloquent\Builder
    public static function search($query, $text = null) {

        if ($text) {

            $table = static::getFormSchema($query->getModel()->getTable());

            $table = array_diff_key(
                $table,
                array(
                    'created_at' => '',
                    'updated_at' => ''
                )
            );

            //var_dump(array_keys($table));

            foreach(array_keys($table) as $column) {

                $query = $query->orWhere($column, 'LIKE', "%$text%");

            }

        }

        return $query;

    }


    // \Illuminate\Database\Eloquent\Builder
    public static function orderBy($query, $orderBy, $direction = 'ASC')
    {

        if (str_contains($orderBy, ':')) {

            list($column, $direction) = explode(':', $orderBy);

        } else {

            $column = $orderBy;

        }

        $table = static::getFormSchema($query->getModel()->getTable());

        if ($orderBy && array_key_exists($column, $table)) {

            $query = $query->orderBy($column, $direction);

        }

        return $query;

    }


    public static function columnSearch($query, $data = null)
    {
        $data = $data ? (array) $data : \Input::all();

        if (is_array($data) && count($data))
        {
            $table = static::getFormSchema($query->getModel()->getTable());

            foreach($table as $column => $def)
            {
                if (array_key_exists($column, $data) && (strlen($value = $data[$column])))
                {
                    $query = $query->where($column, 'LIKE', "%{$value}%");
                }
            }
        }

        //var_dump($this);
        return $query;
    }


    public static function callSp(/* $name, ...$arguments */) {

        $result = array();

        if ( ($args = func_get_args()) && count($args) ) {

            $name = array_shift($args);

            $_qMarks = count($args) ?
                array_fill(0, count($args), '?') :
                array();

            $stmnt = DB::connection()
                ->getPdo()
                ->prepare("CALL {$name}(".implode(',', $_qMarks).")");

            if ($stmnt->execute($args)) {

                $result = $stmnt->fetchAll(\PDO::FETCH_ASSOC);

            }

            /*
            array_walk($args, function($k, $v) use (&$stmnt) {

            });
            */

        }

        return $result;

    }


    // testing method; PDO fetching out data
    public static function GetData() {

        $data = 44;

        $pdo = DB::connection()->getPdo();

        $sth = $pdo->prepare('CALL Product_Assign(:data)');

        $sth->bindParam(':data', $data);

        $sth->execute();

        return "DATA JE: {$data}<br>";

    }



    public static function detectModelClass($model)
    {
        foreach([
            'lib/cms/models' => '\Cms\Models'
        ] as $folder => $namespace)
        {
            $className = studly_case($model);
            $path = app_path($folder . '/' . $className . '.php');

            if (\File::exists($path))
            {
                return $namespace . '\\'.$className;
            }
        }

        return null;
    }


    public static function detectModelClassByTable($table)
    {
        return static::detectModelClass(str_singular($table));
    }

}



class mysqlForeignKeys {

    protected $keys;

    public function __construct($table)
    {
        $SQL = "
        SELECT
            CONSTRAINT_NAME AS keyName,
            `column_name` AS local_column,
            `referenced_table_schema` AS foreign_db,
            `referenced_table_name` AS foreign_table,
            `referenced_column_name`  AS foreign_column
        FROM
            `information_schema`.`KEY_COLUMN_USAGE`
        WHERE
            `constraint_schema` = SCHEMA()
        AND
            `table_name` = '{$table}'
        AND
            `referenced_column_name` IS NOT NULL
        ORDER BY
            `column_name`";

        $keys = \DB::select( \DB::raw($SQL) );
        foreach($keys as $key)
        {
            if (!isset($this->keys[$key['keyName']])) $this->keys[$key['keyName']] = [];
            $this->keys[$key['keyName']] = $key;
        }
    }


    public function getKey()
    {
        $result = [];
        foreach($this->keys as $keyName => $theKey)
        {
            $localColumn = $theKey['local_column'];
            if (!isset($result[$localColumn])) $result[$localColumn] = [];

            $result[$localColumn][] = [
                'table'     => $theKey['foreign_table'],
                'column'    => $theKey['foreign_column']
            ];
        }
        return $result;
    }

}