<?php namespace LaravelAddons\Eloquent;

use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Facades\Input;
use Illuminate\Database\Eloquent\Model as EloquentModel;
use \Cache;
use \DB;
use LaravelAddons\Util\AngularCollection;
use LaravelAddons\Util\Tools;
use LaravelAddons\Util\DbTools;
use Mars\Util\FormSchemaAngular;


class EloquentPlus extends EloquentModel {


    protected $hidden = array('created_at', 'updated_at', 'timestampUpdated', 'timestampCreated');


    public $smallTableRows = 500;

    protected static $rememberMinutes = 500;

    public $smallTable = false;

    public $timestamps = false;


    public $combineTables = true;


    public $columnSearchTerm = null;

    // store models in collection by primary key
    public $storeCollectionByPK = true;

    // the key for storing models into collection
    public $modelStoreKey = null;


    /**
     * Related models array.
     * @var array
     */
    protected static $related = [];

    protected $custom_relations = [];


    /**
     * Validation rules.
     *
     * @var array
     */
    protected static $rules = [];


    /**
     * Model validator - used to auto validate the model by $rules.
     *
     * @var \Illuminate\Validation\Validator
     */
    protected $validator;


    protected $schema;


    public static function make()
    {
        return new static();
    }

    public function toAngular()
    {
        $data = $this->toArray();

        // iterate custom relations
        if (count($cr = $this->getCustomRelations()))
        {
            foreach($cr as $relationName)
            {
                if ($this->hasRelation($relationName))
                {
                    if ($relation = $this->{$relationName})
                    {
                        $data[$relationName] = $relation->toAngular();
                    }
                }
            }
        }

        // iterate foreign keys
        $fKeys = DbTools::fKeys($this->getTable());
        foreach($fKeys as $local => $foreign)
        {
            if (array_key_exists($foreign->table, $data))
            {
                // does the relation exists
                if ($relationName = $this->{$foreign->table})
                {
                    $angularRelations = $relationName->getAngularRelations();
                    $data[$local] = array_merge(
                        $relationName->getAngularArray(),
                        is_array($angularRelations) ? $angularRelations : []
                    );
                    unset($data[$foreign->table]);
                }
            }
        }

        return $data;
    }

    public function getCustomRelations()
    {
        return $this->custom_relations;
    }

    /**
     * Helper for getting the model id.
     *
     * @return mixed
     */
    public function getId()
    {
        if ($this->exists)
        {
            return $this->{$this->primaryKey};
        }
        return null;
    }

    /**
     * Helper for getting the model representation value (used in grids).
     *
     * @return mixed
     * @throws \Exception
     */
    public function getTitle()
    {
        if ($this->exists)
        {
            $lang = \App::getLocale();
            $attr = $this->getAttributes();
            if (array_key_exists('name_'.$lang, $attr))
            {
                return $attr['name_'.$lang];
            }
            elseif (array_key_exists('name', $attr))
            {
                return $attr['name'];
            }

            elseif (array_key_exists('value_'.$lang, $attr))
            {
                return $attr['value_'.$lang];
            }

            // we did not find the name
            return null;
        }
        throw new \Exception('Error fetching model title - model is not loaded!');
    }


    public function hasRelation($name)
    {
        return array_key_exists($name, $this->relations);
    }


    public function getAngularArray()
    {
        return [
            'id' => $this->getId(),
            'text' => $this->getTitle()
        ];
    }


    public function getAngularRelations()
    {
        $relations = [];

        $fKeys = DbTools::fKeys($this->getTable()); // relation keys

        foreach($fKeys as $local => $related)
        {
            if ($this->hasRelation($related->table))
            {
                if ($relation = $this->{$related->table})
                {
                    $data = $relation->getAngularArray();

                    $subRelations = $relation->getAngularRelations();

                    if (count($subRelations))
                    {
                        $data = array_merge(
                            $data,
                            $subRelations
                        );
                    }

                    $relations[$local] = $data;
                }
            }
        }

        return $relations;
    }

    public function isSmallTable()
    {
        return $this->smallTable && (self::cachedCount() < $this->smallTableRows);
    }

    public static function onMaster($name = null)
    {
        return static::on(
            $name ?: \Config::get('database.default-master')
        );
    }

    /**
     * Adding support for generating model collections using the primary key as collection key.
     *
     * @param \Illuminate\Database\Eloquent\Model[] $models
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function newCollection(array $models = array())
    {
        if ($this->storeCollectionByPK && count($models))
        {
            $coll = new AngularCollection();

            $mKey = current($models);
            if ($mKey)
            {
                // use the defined key or get the primary key by default
                $mKey = $this->modelStoreKey ?: $mKey->getKeyName();
                foreach($models as $model)
                {
                    $coll->put($model->{$mKey}, $model);
                }
            } else {
                //echo "Nema modela?<br>\n";
            }

        } else {
            $coll = new AngularCollection($models);
        }

        return $coll;

    }

    /**
     * Retrieve the table schema. It is a non-cached version, object based.
     * @return array
     */
    public static function getTableSchema()
    {
        return DbTools::getTableSchema(static::getModel()->getTable());
    }


    /**
     * @return array
     */
    public static function getFormSchema()
    {
        return DbTools::getFormSchema(static::getModel()->getTable());
    }


    /**
     * Retrieves the table item count.
     * Uses the master connection for accuracy
     *
     * @param bool $force
     * @return int
     */
    public static function cachedCount($force = false)
    {
        static $cache;

        // global count cache
        $keyGlobal = 'cache_table_counts';

        // the cache key of the current table
        $key = static::cacheCountKey();

        // check if we already loaded the cache if not get it; default is empty array
        $cache = isset($cache) ? $cache : Cache::get($keyGlobal, array());

        // remove the element from cache?
        if ($force)
        {
            unset($cache[$key]);
        }

        if ( !isset($cache[$key]) )
        {
            // we`ll use the new count from the master
            // if we performed some affecting query the
            // state on slave is not replicated immediately
            $cache[$key] = static::onMaster()->count();
            Cache::put($keyGlobal, $cache, 24*60);
        }

        return $cache[$key];
    }


    public static function count($columns = '*', $fresh = false)
    {
        return static::cachedCount($fresh);
    }


    /**
     * We must handle cached relations.
     * Getter also allows to skip using with keyword when loading small tables.
     * n+1 problem is solved by cache system.
     *
     * @param string $key
     * @return mixed
     */
    public function getAttribute($key)
    {
        if (array_key_exists($key, $this->relations))
        {
            return $this->relations[$key];
        }

        if (!array_key_exists($key, $this->attributes) && method_exists($this, $key))
        {
            $relations = $this->$key();
            if ($relations instanceof Relation) {
                $relations = $relations->getResults();
            }

            return $this->relations[$key] = $relations;
        }

        return parent::getAttribute($key);
    }


    /**
     * Define an inverse one-to-one or many relationship.
     *
     * @param  string  $related
     * @param  string  $foreignKey
     * @param  string  $otherKey
     * @param  string  $relation
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function cachedBelongsTo($related, $foreignKey = null, $otherKey = null, $relation = null)
    {
        // If no relation name was given, we will use this debug backtrace to extract
        // the calling method's name and use that as the relationship name as most
        // of the time this will be what we desire to use for the relationships.
        if (is_null($relation))
        {
            list(, $caller) = debug_backtrace(false);

            $relation = $caller['function'];
        }

        if (is_null($foreignKey))
        {
            $foreignKey = snake_case($relation).'_id';
        }

        // Once we have the foreign key names, we'll just create a new Eloquent query
        // for the related models and returns the relationship instance which will
        // actually be responsible for retrieving and hydrating every relations.
        $instance = new $related;

        $query = $instance->newQuery();

        $otherKey = $otherKey ?: $instance->getKeyName();

        return new CachedBelongsTo($query, $this, $foreignKey, $otherKey, $relation);
    }


    /**
     * Define a one-to-many relationship.
     *
     * @param  string  $related
     * @param  string  $foreignKey
     * @param  string  $localKey
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function cachedHasMany($related, $foreignKey = null, $localKey = null)
    {
        $foreignKey = $foreignKey ?: $this->getForeignKey();

        $instance = new $related;

        $localKey = $localKey ?: $this->getKeyName();

        return new CachedHasMany($instance->newQuery(), $this, $instance->getTable().'.'.$foreignKey, $localKey);
    }


    public static function cacheKey($entity = null)
    {
        return
            'cached_table_' .
            str_replace("\\", '_', get_called_class()) .
            ($entity ? "_$entity" : '');
    }


    public static function cacheCountKey()
    {
        // we are storing all the counts in one cache key
        return 'cache_table_counts'; //self::cacheKey() . '_count';
    }


    /**
     * Get all of the models from the database.
     *
     * @param  array $columns
     * @param bool $fresh
     * @return \Illuminate\Database\Eloquent\Collection|static[]
     */
    public static function all($columns = array('*'), $fresh = false)
    {
        /**
         * @var EloquentPlus $model
         */
        $model = new static;
        $key = self::cacheKey();

        if ($fresh)
        {
            $model->deleteSmallTableCache();
        }

        if ($model->isSmallTable())
        {
            if ($all = Cache::tags($model->getTable())->get($key))
            {
                // we have it in the cache
            }
            else
            {
                // we`ll cache data from master; all columns
                // cache the version with all columns
                $all = $model->onMaster()->select()->get();

                \Cache::tags($model->getTable())->forever($key, $all);


                // Cache::tags($model->getTable(), $key)->forever($key, $all);
                /*
                $all = \Cache::tags($model->getTable(), $key)->rememberForever($key, function() use ($model) {
                    return $model->onMaster()->select()->get();
                });
                */

                // \Cache::put($key, $all, 60*24);
            }

            // check if we need to select custom columns
            if ($columns != array('*'))
            {
                $columns = array_keys($model->getAttributes());
                $columns = array_combine($columns, $columns);

                foreach($all as $k => $v)
                {
                    $all[$k] = array_intersect_key($v, $columns);
                }
            }

            return $all;

        }

        //\Log::info($model->getModel()->getTable() . ' in not a small table');
        return parent::all($columns);

    }


    /**
     * Deletes multiple items by id. Ids format is CSV.
     * Method returns the number of deleted records.
     *
     * @param int|string $ids
     * @return int number of deleted items
     */
    public function deleteMultiple($ids) {

        $counter = 0;

        if (count($ids = Tools::filter_pdigit_csv($ids))) {

            try {

                foreach($ids as $id) {
                    $this->find($id)->delete();
                    $counter++;
                }

            } catch (\Exception $e) {

                return 0;

            }

        }

        return $counter;

    }


    /**
     * Performs search query on the model.
     *
     * @param array $defaults
     * @return array
     */
    public function queryForItems($defaults = null)
    {
        // the debug state
        $defaults = is_array($defaults) && count($defaults) ? $defaults : [];
        $debug = \Config::get('app.debug') === true;
        $lang = \App::getLocale();

        // the return data
        $result = array();

        // the return errors data
        $errors = array();

        // table and fkeys
        $table = $this->getTable();
        $localTableSchema = DbTools::getTableSchema($table);
        $foreignKeys = DbTools::fKeys($table);

        $this->setHidden(['created_at', 'updated_at']);

        // check search term
        $searchTerm = $this->columnSearchTerm ? $this->columnSearchTerm : Input::get('search');
        if (stripos($searchTerm, ':') !== false)
        {
            list($searchColumn, $searchColumnText) = explode(':', $searchTerm);
            if (array_key_exists($searchColumn, $localTableSchema))
            {
                // search column and text are OK
                $searchTerm = [
                    $searchColumn => $searchColumnText
                ];
            }

            else
            {
                // the column does not exist - just leave it
            }
        }


        try {

            /**
             * @var \Illuminate\Database\Query\Builder $query
             */
            $query = $this->select([
                \DB::raw('SQL_CALC_FOUND_ROWS `' . static::getModel()->getTable() . '`.*')
            ]);

            $query = DbTools::paginate($query, Input::get('page', null));
            $query = DbTools::orderBy($query, Input::get('order_by', null));


            if (is_array($searchTerm))
            {
                \Log::info('Pretraga: ', $searchTerm);
                $query = DbTools::columnSearch($query, $searchTerm);
            }

            elseif (strlen($searchTerm))
            {
                \Log::info('Pretraga texta: ' . $searchTerm);
                $query = DbTools::search($query, $searchTerm);
            }

            // local query defaults checking
            $prefixedLocalTableSchema = \Mars\Util\array_prefix_key(
                $localTableSchema,
                $table . '.',
                false
            );
            foreach($defaults as $column => $value)
            {
                // set the current model data if the attribute exists
                if (array_key_exists($column, $prefixedLocalTableSchema))
                {
                    $query = $query->where($column, $value);
                }
            }


            // do we need foreign tables - speed up search queries if foreign table data is not important
            if ($this->combineTables)
            {
                //echo "Table $table<br>";
                //$this->combineForeignTables($query, $table, $defaults);

                $relations = [];
                FormSchemaAngular::laravelRelations($relations, $table);


                // iterate local foreign keys
                foreach($foreignKeys as $column => $colData)
                {
                    // combine local table with foreign and filter foreign table with defaults
                    $query->with($relations);

                    $query->whereHas($colData->table, function ($subQuery) use ($table, $colData, $defaults) {

                        if (count($defaults))
                        {
                            // get the foreign table schema; prefix the keys with table name and a dot
                            $foreignSchema = \Mars\Util\array_prefix_key(
                                DbTools::getTableSchema($colData->table),
                                $colData->table . '.',
                                false
                            );

                            if (count($foreignSchema))
                            {
                                foreach($defaults as $defaultColumn => $defaultValue)
                                {
                                    // check if the key exists - apply if found
                                    if (array_key_exists($defaultColumn, $foreignSchema))
                                    {
                                        $subQuery->where($colData->table . '.' . $defaultColumn, $defaultValue);
                                    }
                                }
                            }
                        }

                    });

                }

            }



            // perform the query
            // set initial counter
            $total = DbTools::$sqlTotalRows = 0;

            try {
                $data = $query->get(); //var_dump($data->toArray());

                $result = $data->toAngular();

                // check if we need to manualy get the count
                if (DbTools::$sqlTotalRows == 'get')
                {
                    DbTools::$sqlTotalRows = DbTools::getTotalRecords();
                }

                $total = Tools::is_pdigit(DbTools::$sqlTotalRows) ? DbTools::$sqlTotalRows : 0;
            }

            catch (\Exception $e)
            {
                $result = array();

                $errors[] = $debug ?
                    'Error fetching data.' :
                    $e->getMessage() . ' Query: '.$query->toSql();
            }

            //var_dump($data->toArray());

        }

        catch (\Exception $e)
        {
            $total = 0;

            $result = array();

            $errors[] = $debug ?
                'Error fetching data.' :
                $e->getMessage() . ' at ' . $e->getFile() . ' line ' . $e->getLine();
        }

        if (count($errors))
        {
            foreach($errors as $err) \Log::error($err);
        }

        return array($result, $total);

    }


    /**
     * METHOD TO DO NOT used for now.
     * @param $query
     * @param string $table This is the local table
     * @param $defaults
     */
    protected function combineForeignTables($query, $table, $defaults)
    {
        $relations = [];
        FormSchemaAngular::laravelRelations($relations, $table);

        if ($this->isSmallTable())
        {
            $currentPath = $table;

            $foreignKeys = DbTools::fKeys($table); print_r($foreignKeys);

            foreach($foreignKeys as $column => $colData)
            {
                $currentPath .= '.' . $colData->table;

                $this->combineForeignTables($query, $colData->table, $defaults);

            }

            echo $currentPath. "<br>\n";


        }
    }


    /**
     * Get the model rules.
     *
     * @return array
     */
    public static function getRules()
    {
        return static::$rules;
    }


    /**
     * @param array $data
     * @return \Illuminate\Validation\Validator
     */
    public function validator(array $data = null)
    {
        return
            $data === null ?
                $this->validator :
                ($this->validator = \Validator::make($data, static::$rules));
    }


    public static function filterModelKeys($data)
    {
        return array_intersect_key(
            $data,
            static::getTableSchema()
        );
    }


    public function update(array $data = array())
    {
        // we`ll remove all unnecessary data
        $updated = parent::update(self::filterModelKeys($data));

        if ($updated)
        {
            with(new static)->deleteSmallTableCache();
        }

        return $updated;
    }


    public static function create(array $data)
    {
        // we`ll remove all unnecessary data

        $created = parent::create(self::filterModelKeys($data));

        if ($created)
        {
            with(new static)->deleteSmallTableCache();
        }

        return $created;
    }


    public function delete()
    {
        $deleted = parent::delete();

        if ($deleted)
        {
            $this->deleteSmallTableCache();
        }

        return $deleted;
    }


    public static function related()
    {
        return isset(static::$related) && is_array(static::$related) ? static::$related : array();
    }


    public function save(array $options = array())
    {
        // we`ll switch the connection to the master and reset it after the update
        DB::setDefaultConnection(\Config::get('database.default-master'));

        \Log::info(
            'model->save() ' . $this->getTable() . ' sa ' . print_r($this->getAttributes(), true)
        );

        $saved = parent::save($options);

        DB::setDefaultConnection(DB::getDefaultConnection());

        if ($saved === true)
        {
            $this->deleteSmallTableCache();

            \Log::info('model->save() related je: ' . implode(', ', $this->related()));
            foreach($this->related() as $child)
            {
                $child = \Str::singular($child);
                \Cache::tags(with(new $child)->getTable())->forget($this->id);

                //with(new $child)->deleteSmallTableCache();
                /*
                \Log::error($child);
                static::flushChildren(
                    $this->getTable(),
                    with(new $child)->getTable(),
                    $this->{$this->primaryKey}
                );
                */
            }
        }

        return $saved;
    }


    public function deleteSmallTableCache()
    {
        \Log::info("Delete small table cache za: {$this->getTable()}");

        // forget the table count
        $this->cachedCount(true);

        // forget the data cache
        Cache::tags($this->getTable())->flush();
    }


    public static function cacheKeyChild($parent, $child, $parentId)
    {
        return "cc_{$parent}_{$child}_{$parentId}";
    }


    public static function flushChildren($parent, $child, $parentId)
    {
        \Cache::tags($child)->flush();
    }


    public static function forgetChild($key)
    {
        list(,,$child) = explode('_', $key);
        \Cache::tags($child)->forget($key);
    }

}

