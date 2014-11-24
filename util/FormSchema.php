<?php namespace LaravelAddons\Util;

use LaravelAddons\Eloquent\EloquentPlus;
use LaravelAddons\Util\DbTools;


class FormSchema {

    /**
     * @var EloquentPlus
     */
    protected $model;

    protected $table;

    protected $schema;


    protected $dataFields;

    protected $gridFields;

    protected $editFields;

    protected $searchFields;


    protected $defaults;

    /**
     * @var array
     */
    protected $settings;


    public function __construct(EloquentPlus $model, $defaults = null, array $settings = null)
    {
        $this->model = $model;
        $this->table = $model->getTable();

        // fetch the schema; schema is cached
        $this->schema = DbTools::getFormSchema($this->table);

        // we`ll add all fields by default
        $this->dataFields = $this->gridFields = $this->editFields = array_keys($this->schema);

        // we`ll remove timestamps by default
        $this->removeEditFields(array('created_at', 'updated_at', 'deleted_at'));
        $this->removeGridFields(array('id', 'created_at', 'updated_at', 'deleted_at'));

        $this->searchFields = array();

        $this->defaults = $defaults;

        $this->settings = $settings ?: [];

        if (!array_key_exists('schema_recursive', $this->settings)) {
            $this->settings['schema_recursive'] = '*';
        }

    }


    public static function make(EloquentPlus $model, $defaults = null, array $settings = null)
    {
        return new static($model, $defaults, $settings);
    }


    public function settings($what, $default)
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

    protected function createFormRules($rules = '') {

        $newRules = array();
        if ($rules) {

            $rules = explode('|', $rules); //var_dump($rules); exit();
            foreach($rules as $rule) {

                if ($rule) {

                    @list($name, $value) = explode(':', $rule, 2);

                    //$t = explode(':', $rule, 2);
                    //var_dump($t); continue;

                    switch ($name) {

                        case 'email':
                            $newRules[] = "email";
                            break;

                        case 'url':
                            $newRules[] = "url";
                            break;

                        case 'size':
                            $newRules[] = "length:$value";
                            break;

                    }

                }

            }

        }

        return $newRules;
        //return json_encode($newRules, JSON_PRETTY_PRINT);

    }


    public function getWidgetType($column) {

        $lang = \App::getLocale();

        $fKeys = DbTools::fKeys($this->table);
        $fData = null;

        $data = array();

        $columnRules = $this->model->getRules();

        if ($columnRules && array_key_exists($column, $columnRules)) {
            $columnRules = $this->createFormRules($columnRules[$column]);
        } else {
            $columnRules = null;
        }
        //var_dump($columnRules);


        if ($this->schema && array_key_exists($column, $this->schema))
        {
            $colData = $this->schema[$column];

            $data = array(
                'widget' => null,
                'defaultValue' => addslashes($colData['default']),
                'required' => $colData['null'] ? false : true,
                'validate' => $columnRules
            );

            $multi = false;

            // check the foreign key column
            if (array_key_exists($column, $fKeys) && ($fk = $fKeys[$column]))
            {
                /**
                 * @var EloquentPlus $foreignModel
                 */
                //DbTools::getTableSchema($fk->table, true);
                //var_dump($this->model->with($fk->table)->get()->toArray());
                //var_dump($fk->table, DbTools::getFormSchema($fk->table, true));

                $defaults = $this->defaults;

                $foreignModel = DbTools::detectModelClassByTable($fk->table);

                if ( $foreignModel && ($foreignModel = new $foreignModel) )
                {
                    // for large tables use ajax requests
                    if ( $foreignModel->count() > 1000 )
                    {
                        $fData = \URL::action(
                            ucwords($fk->table).'Controller@index'
                        );
                    }

                    // build the foreign table data
                    else
                    {
                        // all foreign table records
                        $fData = $foreignModel->all();

                        // filtering method for defaults
                        $fFilter = function($item) use ($defaults)
                        {
                            $attrs = array_keys($item->getAttributes());
                            foreach($this->defaults as $dkey => $dvalue)
                            {
                                //do we have this item in the model attributes - columns
                                if (in_array($dkey, $attrs)) {
                                    if ($dvalue != $item->{$dkey}) {
                                        return null;
                                    }
                                }
                                else
                                {
                                    return false;
                                }
                            }
                            return true;
                        };

                        // if we have the defaults key in the foreign table - filter the
                        if (($fOne = $fData->first()) && $fFilter($fOne)) {
                            $fData = $fData->filter($fFilter);
                        }

                        $fData = $fData->map(function(EloquentPlus $item) {
                            return [
                                'id' => $item->getId(),
                                'title' => $item->getTitle()
                            ];
                        });

                        //$fData = \DB::table($fk->table)->select('id as value', 'name as label')->get();
                    }

                    $data['widget'] = array(
                        'combobox',
                        array(
                            'multi' => false,
                            'source' => $fData,
                            'allowClear' => $colData['null'] ? true : false
                        )
                    );

                    return $data;
                }
                else
                {
                    // error detecting model
                    return [];
                }
            }



            switch($this->schema[$column]['type']) {

                case 'decimal': case 'float': case 'double':
                $data['validate'][] = 'number';
                break;

                case 'text':
                    $data['widget'] = array('textarea');
                    break;

                case 'integer':
                    $data['validate'][] = 'digits';
                    break;

                case 'decimal':
                    $data['validate'][] = 'number';
                    break;

                case 'set':
                    $multi = true;

                case 'enum':
                    $data['widget'] = array(
                        'combobox',
                        array(
                            'multi' => $multi
                        )
                    );
                    break;

                case 'date':
                case 'datetime':
                    $data['widget'] = array('datepicker'); // array(name, array(config))
                    break;

            }

        }

        return $data;

    }


    /**
     * Create the form representation.
     *
     * @return array
     */
    public function fetch()
    {
        foreach(array('dataFields', 'gridFields', 'editFields', 'searchFields') as $entity)
        {
            $$entity = array_intersect_key(
                $this->schema,
                array_combine(
                    $this->{$entity},
                    $this->{$entity}
                )
            );
        }

        /**
         * @var array $gridFields
         * @var array $editFields
         * @var array $searchFields
         */
        $out = array();
        foreach($this->schema as $column => $data)
        {
            $titleKey = 'db::'.$this->table.'.'.$column;

            $out[$column] = array(
                'title'         => \Lang::has($titleKey) ? \Lang::get($titleKey) : $column,
                'showInTable'   => array_key_exists($column, $gridFields),
                'showInEdit'    => $column == 'id' ? 'disabled' : array_key_exists($column, $editFields),
                'showInCreate'  => $column == 'id' ? false : array_key_exists($column, $editFields),
                'showInSearch'  => array_key_exists($column, $searchFields),
                'formSettings'  => $this->getWidgetType($column)
                /*
                //combobox
                    -> autocomplete default false, stavi true ako ih je puno
                    -> multi true, ako zelis multi multi_select
                    -> source array stringova ili objekta { label, value } ili string remoteurl
                */
            );
        }

        return $out;
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

