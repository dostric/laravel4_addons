<?php namespace LaravelAddons\Util;


use Mars\Rs\Classes\DescriptionForm;


abstract class FormSchema {

    /**
     * @var DescriptionForm|array
     */
    protected $schema;

    protected $language;

    protected $dataFields;

    protected $gridFields;

    protected $editFields;

    protected $searchFields;

    /**
     * @var array
     */
    protected $defaults;

    /**
     * @var array
     */
    protected $settings;


    public function __construct(array $defaults = null, array $settings = null)
    {
        // set default schema
        $this->schema = [];

        $this->language = \App::getLocale();

        // set default form fields
        $this->searchFields = $this->dataFields = $this->gridFields = $this->editFields = [];

        $this->defaults = $defaults ?: [];

        $this->settings = $settings ?: [];

        // set default recursion to all fields
        $this->settings['schema_recursive'] = $this->settings('schema_recursive', '*');
    }


    public function settings($what, $default = null)
    {
        return
            array_key_exists($what, $this->settings) ? $this->settings[$what] : $default;
    }

    /**
     * Remove and field form the list.
     *
     * @param $entity
     * @param $field
     */
    protected function removeField($entity, $field)
    {
        if (in_array($field, $this->{$entity}))
        {
            $this->{$entity} = array_diff($this->{$entity}, array($field));
        }
    }


    /**
     * Remove multiple fields form the list.
     *
     * @param $entity
     * @param $fields
     */
    protected function removeFields($entity, $fields)
    {
        if (!is_array($fields))
        {
            $fields = explode(',', $fields);
        }

        if (is_array($fields) && count($fields))
        {
            $this->{$entity} = array_diff($this->{$entity}, $fields);
        }
    }


    /**
     * Add a model field to the list.
     *
     * @param $entity
     * @param $field
     */
    protected function addField($entity, $field)
    {
        if (array_key_exists($field, $this->schema) && !in_array($field, $this->{$entity}))
        {
            $this->{$entity}[] = $field;
        }
    }


    /**
     * Add multiple model fields to the list.
     *
     * @param $entity
     * @param $fields
     */
    protected function addFields($entity, $fields)
    {
        if ($this->schema)
        {
            if (!is_array($fields))
            {
                $fields = explode(',', $fields);
            }

            foreach((array)$fields as $col)
            {
                $this->addField($entity, $col);
            }
        }
    }


    public function addDataField($field) {
        $this->addField('dataFields', $field);
    }

    public function addDataFields($fields) {
        $this->addFields('dataFields', $fields);
    }

    public function dataFields($fields) {
        $this->dataFields = array();
        $this->addFields('dataFields', $fields);
    }

    public function addGridField($field) {
        $this->addField('gridFields', $field);
    }

    public function addGridFields($fields) {
        $this->addFields('gridFields', $fields);
    }

    public function gridFields($fields) {
        $this->gridFields = array();
        $this->addFields('gridFields', $fields);
    }

    public function addEditField($field) {
        $this->addField('editFields', $field);
    }

    public function addEditFields($fields) {
        $this->addFields('editFields', $fields);
    }

    public function editFields($fields) {
        $this->editFields = array();
        $this->addFields('editFields', $fields);
    }

    public function addSearchField($field) {
        $this->addField('searchFields', $field);
    }

    public function addSearchFields($fields) {
        $this->addFields('searchFields', $fields);
    }

    public function searchFields($fields) {
        $this->searchFields = array();
        $this->addFields('searchFields', $fields);
    }



    public function removeEditField($field) {
        $this->removeField('editFields', $field);
    }

    public function removeEditFields($fields) {
        $this->removeFields('editFields', $fields);
    }

    public function removeGridField($field) {
        $this->removeField('gridFields', $field);
    }

    public function removeGridFields($fields) {
        $this->removeFields('gridFields', $fields);
    }

    public function removeDataField($field) {
        $this->removeField('dataFields', $field);
    }

    public function removeDataFields($fields) {
        $this->removeFields('dataFields', $fields);
    }

    public function removeSearchField($field) {
        $this->removeField('searchFields', $field);
    }

    public function removeSearchFields($fields) {
        $this->removeFields('searchFields', $fields);
    }



    # CACHING PART

    protected static function getSchemaCacheKey($id, $defaults, $settings)
    {
        return $id . '_' . md5(serialize($defaults) . serialize($settings));
    }

    public static function getSchemaCache($id, $defaults, $settings)
    {
        $key = static::getSchemaCacheKey($id, $defaults, $settings);

        return \Cache::tags('schema_cache_'.$id)->get($key, null);
    }

    public static function setSchemaCache($id, $defaults, $settings, $value)
    {
        $key = static::getSchemaCacheKey($id, $defaults, $settings);

        \Cache::tags('schema_cache_'.$id)->forever($key, $value);
    }

    public static function forgetSchema($id)
    {
        \Cache::tags('schema_cache_'.$id)->flush();
    }

    # CACHING END



    abstract public function getTitle($key);

    abstract public function showInTable($column, $data);

    abstract public function showInEdit($column, $data);

    abstract public function showInCreate($column, $data);

    abstract public function showInSearch($column, $data);

    abstract public function getWidgetType($widget);



    /**
     * Create the form representation.
     *
     * @param string $cacheKey
     * @return array
     */
    public function fetch($cacheKey = null)
    {
        foreach(['dataFields', 'gridFields', 'editFields', 'searchFields'] as $entity)
        {
            $$entity = array_intersect_key(
                is_array($this->schema) ? $this->schema : $this->schema->toArray(),
                array_combine(
                    $this->{$entity},
                    $this->{$entity}
                )
            );
        }

        // are we using cache
        $result = $cacheKey ? self::getSchemaCache($cacheKey, $this->defaults, $this->settings) : null;

        // did we used the cache and found the result
        if (is_null($result))
        {
            /**
             * @var array $gridFields
             * @var array $editFields
             * @var array $searchFields
             */
            $result = array();
            foreach($this->schema as $column => $data) {
                $result[$column] = [
                    'label'         => $this->getTitle($column),
                    'showInGrid'    => $this->showInTable($column, $gridFields),
                    'showInEdit'    => $this->showInEdit($column, $editFields),
                    'showInCreate'  => $this->showInCreate($column, $editFields),
                    'showInSearch'  => $this->showInSearch($column, $searchFields),
                    'attrs'         => $this->getWidgetType($column)
                ];
            }

            if ($cacheKey) self::setSchemaCache($cacheKey, $this->defaults, $this->settings, $result);
        }

        return $result;
    }


    /**
     * Create the form json representation.
     *
     * @return string
     */
    public function render()
    {
        return json_encode($this->fetch(), JSON_PRETTY_PRINT);
    }

}

