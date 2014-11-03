<?php namespace LaravelAddons\Util;

use \Cache;
use \Config;
use \DB;
use Illuminate\Support\Collection;

class DbTools {

    public static $sqlTotalRows;


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


    /**
     * \Doctrine\DBAL\Schema\Table[]
     */
    public static function listTables($toCollection = true, $connectionName = null) {

        $tables = array();

        if ($connection = DB::connection($connectionName)) {

            $cacheKey = 'db_schema_'.$connection->getName();

            if ( !Config::get('app.debug') && Cache::has($cacheKey) )  {
                return Cache::get($cacheKey);
            }

            array_map(
                function ($item) use (&$tables) {
                    $tables[$item->getName()] = $item;
                },
                DB::connection()
                    ->getDoctrineSchemaManager()
                    ->listTables()
            );

            Cache::put($cacheKey, $tables, 60);

        }


        return $toCollection ? new Collection($tables) : $tables;

/*

        foreach($tables as $name => $table) {

            foreach($table->getColumns() as $column) {
                echo $name . ' == ' . $column->getName() . " - " . $column->getType() . "<br>";
            }

        }

*/

    }

    /**
     * @param $table
     * @return \Doctrine\DBAL\Schema\Column[]
     */
    public static function getTableSchema($table, $fresh = false) {

        $cacheKey = 'table_schema_'.$table;

        if ($fresh)
        {
            Cache::forget($cacheKey);
        }

        if ( !Config::get('app.debug') && Cache::has($cacheKey) )
        {
            return Cache::get($cacheKey);
        }

        $tableColumns = array();

        array_map(
            function ($item) use (&$tableColumns) {
                $tableColumns[$item->getName()] = $item;
            },
            DB::connection()
                ->getDoctrineSchemaManager()
                ->listTableColumns(
                    $table
                )
        );

        Cache::put($cacheKey, $tableColumns, 60);

        return $tableColumns;
    }


    /**
     * Returns a table schema. Schema is an array (keys are column names) and is cached.
     *
     * @param $table
     * @return array
     */
    public static function getFormSchema($table, $fresh = false)
    {
        $cacheKey = 'table_form_schema_'.$table;

        if ($fresh)
        {
            Cache::forget($cacheKey);
        }

        if ( !Config::get('app.debug') && Cache::has($cacheKey) )
        {
            return Cache::get($cacheKey);
        }

        $objSchema = static::getTableSchema($table);

        $schema = array();

        foreach($objSchema as $k => $item)
        {
            $item = $item->toArray();
            $item['type'] = $item['type']->getName();
            $schema[$item['name']] = $item;
        }

        /*
        // problem doctrine mjenja keyeve od columna;
        $schema = array_map(function (\Doctrine\DBAL\Schema\Column $item) {
            $item = $item->toArray();
            $item['type'] = $item['type']->getName();
            return $item;
        }, $objSchema);
        */
        //var_dump($schema);

        Cache::put($cacheKey, $schema, 60);

        return $schema;

    }

//* @return \Doctrine\DBAL\Schema\Index[]

    /**
     * @param $table
     *
     * @return \Doctrine\DBAL\Schema\ForeignKeyConstraint[]
     */
    public static function getTableKeys($table)
    {
        return DB::getDoctrineSchemaManager()
            ->listTableDetails($table)
            ->getForeignKeys();
    }


    /**
     * @param $table
     * @param bool $fresh
     * @throws \Exception
     * @return array
     */
    public static function fKeys($table, $fresh = false)
    {
        $cacheKey = 'table_fkeys_'.$table;

        if ($fresh)
        {
            \Cache::forget($cacheKey);
        }

        $cacheData = Cache::has($cacheKey) ? Cache::get($cacheKey) : array(); //var_dump($cacheData);

        if (array_key_exists($table, $cacheData))
        {
            return $cacheData[$table];
        }

        foreach(static::getTableKeys($table) as $key => $data)
        {
            if (
                count($local = $data->getLocalColumns()) >1 ||
                count($foreign = $data->getForeignColumns()) >1
            ) {
                throw new \Exception('Forms support only reference to one column.');
            }

            list($local) = $local;
            list($foreign) = $foreign;

            if (!array_key_exists($table, $cacheData))
            {
                $cacheData[$table] = array();
            }

            if (!array_key_exists($local, $cacheData[$table]))
            {
                $cacheData[$table][$local] = (object)array(
                    'table'     => $data->getForeignTableName(),
                    'column'    => $foreign
                );
            }

        }

        Cache::put($cacheKey, $cacheData, 70);

        return array_key_exists($table, $cacheData) ? $cacheData[$table] : array();
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


    public static function columnSearch($query, $data = null) {

        $data = $data ? (array) $data : \Input::all();

        if (is_array($data) && count($data)) {

            $table = static::getFormSchema($query->getModel()->getTable());

            foreach($table as $column => $def) {

                if (array_key_exists($column, $data) && (strlen($value = $data[$column]))) {

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