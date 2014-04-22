<?php namespace LaravelAddons\Eloquent;

use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Input;
use \Cache;
use \DB;
use \Session;
use LaravelAddons\Util\DbTools;


class EloquentPlus extends \Eloquent {


    protected $storeCollectionByPK = true;

    public static $smallTableRows = 500;

    protected static $rememberMinutes = 500;

    public $smallTable = false;


    public $combineTables = true;


    public $columnSearchTerm = null;


    /**
     * Validation rules.
     *
     * @var array
     */
    protected static $rules = array();


    /**
     * Model validator - used to auto validate the model by $rules.
     *
     * @var \Illuminate\Validation\Validator
     */
    protected $validator;




    public static function factory() {
        return new static();
    }



    public function isSmallTable() {
        return $this->smallTable && (self::cachedCount() < self::$smallTableRows);
    }




    /**
     * Adding support for generating model collections using the primary key as collection key.
     *
     * @param \Illuminate\Database\Eloquent\Model[] $models
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function newCollection(array $models = array()) {

        if ($this->storeCollectionByPK) {

            $coll = new \Illuminate\Database\Eloquent\Collection();

            foreach($models as $model) {
                $coll->put($model->{$model->getKeyName()}, $model);
            }

        } else {

            $coll = new \Illuminate\Database\Eloquent\Collection($models);

        }


        return $coll;

    }

    /**
     * Retrieve the table schema. It is a non-cached version, object based.
     * @return \Doctrine\DBAL\Schema\Column[]
     */
    public static function getTableSchema() {

        return DbTools::getTableSchema(static::getModel()->getTable());
    }


    public static function getFormSchema() {

        return DbTools::getFormSchema(static::getModel()->getTable());
    }


    public static function cachedCount($force = false) {

        static $countCached;

        $key = static::cacheCountKey();

        if ($force) {
            $countCached = null;
        }

        if (!$countCached) {

            if (Cache::has($key) && Tools::is_digit($countCached = Cache::get($key))) {



            } else {

                $countCached = static::count();
                Cache::put($key, $countCached, 24*12);

            }

        }

        return $countCached;

    }


    /**
     * We must handle cached relations.
     * Getter also allows to skip using with keyword when loading small tables.
     * n+1 problem is solved by cache system.
     *
     * @param string $key
     * @return mixed
     */
    public function getAttribute($key) {

        if (array_key_exists($key, $this->relations))
        {
            return $this->relations[$key];
        }

        if (!array_key_exists($key, $this->attributes) && method_exists($this, $key)) {

            $relations = $this->$key();
            if ($relations instanceof Relation) {
                $relations = $relations->getResults();
            }

            return $this->relations[$key] = $relations;
        }

        return parent::getAttribute($key);

    }


    /**
     * Define an cached inverse one-to-one or many relationship.
     *
     * @param  string  $related
     * @param  string  $foreignKey
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function cachedBelongsTo($related, $foreignKey = null)
    {
        list(, $caller) = debug_backtrace(false);

        // If no foreign key was supplied, we can use a backtrace to guess the proper
        // foreign key name by using the name of the relationship function, which
        // when combined with an "_id" should conventionally match the columns.
        $relation = $caller['function'];

        if (is_null($foreignKey))
        {
            $foreignKey = snake_case($relation).'_id';
        }

        // Once we have the foreign key names, we'll just create a new Eloquent query
        // for the related models and returns the relationship instance which will
        // actually be responsible for retrieving and hydrating every relations.
        $instance = new $related;

        $query = $instance->newQuery();

        return new CachedBelongsTo($query, $this, $foreignKey, $relation);
    }


    public static function cacheKey() {
        return 'cached_table_'.get_called_class();
    }

    public static function cacheCountKey() {
        return self::cacheKey() . '_count';
    }


    /**
     * Get all of the models from the database.
     *
     * @param  array  $columns
     * @return \Illuminate\Database\Eloquent\Collection|static[]
     */
    public static function all($columns = array('*')) {

        static $all;

        // if we already evaluated the table we`ll speed up
        // things by skipping all the further processing.
        if ($all) return $all;

        $key = self::cacheKey();

        if (with(new static)->isSmallTable()) {

            Cache::forever($key, $all = parent::all());

            return $all;

            if ($all = Cache::get($key)) {
                // we have it in the cache
            } else {
                Cache::forever($key, $all = parent::all());
            }

            return $all;

        }

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

        if (count($ids = Tools::parseIntArrayFromCSV($ids))) {

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
     * @return array
     */
    public function queryForItems() {

        $items = array();

        $errors = array();

        // get table def
        $localTable = DbTools::getTableSchema($this->getModel()->getTable());
        $fKeys = DbTools::fKeys(static::getModel()->getTable());

        // check search term
        $searchTerm = $this->columnSearchTerm ? $this->columnSearchTerm : Input::get('search');

        $llarray = array();
        if ($this->combineTables) {

            foreach($fKeys as $column => $colData) {
                $llarray[] = $colData->table;
            }

        }

        try {

            $query = $this
                ->with($llarray)
                ->select(array(
                    \DB::raw('SQL_CALC_FOUND_ROWS `' . static::getModel()->getTable() . '`.*')
                ));

            $query = DbTools::paginate($query, Input::get('page', null));
            $query = DbTools::search($query, $searchTerm);
            $query = DbTools::orderBy($query, Input::get('order_by', null));
            $query = DbTools::columnSearch($query);


            // set initial counter
            $total = DbTools::$sqlTotalRows = 0;

            try {

                $query = $query->get();

                // check if we need to manualy get the count
                if (DbTools::$sqlTotalRows == 'get') {
                    DbTools::$sqlTotalRows = DbTools::getTotalRecords();
                }

                $total = Tools::is_pdigit(DbTools::$sqlTotalRows) ? DbTools::$sqlTotalRows : 0;

            } catch (\Exception $e) {

                $errors[] = $e->getMessage() . ' Query: '.$query->toSql();
                $query = array();

            }

            // do we need foreign tables - speed up search queries foreign table data is not important
            if ($this->combineTables) {

                $results = array();

                foreach($query as $k => $item) {

                    $results[$k] = $item->toArray();

                    foreach($fKeys as $column => $colData) {

                        if (is_object($item) && isset($item->{$colData->table})) {
                            $results[$k]['fkvalue_'.$column] = $item->{$colData->table}->name;
                            unset($results[$k][$colData->table]);
                        }

                    }

                }

                $items = $results;

            } else {

                $items = $query->toArray();

            }


        } catch (\Exception $e) {

            $errors[] = $e->getMessage() . ' at ' . $e->getFile() . ' line ' . $e->getLine();
            $items = array();
            $total = 0;

        }

        if (count($errors)) {
            foreach($errors as $err) \Log::error($err);
        }

        return array($items, $total);

    }


    /**
     * Get the model rules.
     *
     * @return array
     */
    public static function getRules() {
        return static::$rules;
    }


    /**
     * @param array $data
     * @return \Illuminate\Validation\Validator
     */
    public function validator(array $data = null) {

        return
            $data === null ?
                $this->validator :
                ($this->validator = \Validator::make($data, static::$rules));

    }


    public static function filterModelKeys($data) {
        return array_intersect_key(
            $data,
            static::getTableSchema()
        );
    }


    public function update(array $data = array()) {
        // we`ll remove all unnecessary data
        return parent::update(self::filterModelKeys($data));
    }


    public static function create(array $data) {
        // we`ll remove all unnecessary data
        return parent::create(self::filterModelKeys($data));
    }


    public function save(array $options = array()) {

        $saved = parent::save($options);
        if ($saved === true) {
            $this->deleteSmallTableCache();
        }

        return $saved;
    }


    public function delete() {

        $deleted = parent::delete();

        if ($deleted) {
            $this->deleteSmallTableCache();
        }

        return $deleted;

    }

    protected function deleteSmallTableCache() {

        $key = static::cacheKey();

        // new if we have it in the cache skip checking - just forget the data
        if (Cache::has($key)) {  // $this->isSmallTable()

            Tools::printData("Deletam");

            // forget the cached count
            Cache::forget(static::cacheCountKey());
            static::cachedCount(true);

            // forget the data cache
            return Cache::forget($key);

        }

        return false;

    }


}

