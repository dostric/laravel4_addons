<?php namespace LaravelAddons\Util;

use LaravelAddons\Eloquent\EloquentPlus;
use LaravelAddons\Util\DbTools;


class FormSchemaEloquent extends FormSchema {


    /**
     * @var EloquentPlus
     */
    protected $model;

    protected $table;

    protected $foreignKeys;

    protected $modelRules;


    public function __construct(EloquentPlus $model, array $defaults = null, array $settings = null)
    {
        parent::__construct($defaults, $settings);

        $this->model = $model;
        $this->table = $model->getTable();

        // fetch the schema; schema is cached
        $this->schema = DbTools::getFormSchema($this->table);

        $this->foreignKeys = DbTools::fKeys($this->table);

        $this->modelRules = $this->model->getRules();

        // we`ll add all model fields by default
        $this->dataFields = $this->gridFields = $this->editFields = array_keys($this->schema);

        // we`ll remove timestamps by default
        $this->removeEditFields(array('created_at', 'updated_at', 'deleted_at'));
        $this->removeGridFields(array('id', 'created_at', 'updated_at', 'deleted_at'));

        // remove primary keys from grid and edit
        $this->removeGridField($model->getKeyName());
        $this->removeEditField($model->getKeyName());

        // add custom model grid fields
        if (is_array($model->gridFields))
        {
            $this->gridFields($model->gridFields);
        }
    }


    public static function make(EloquentPlus $model, array $defaults = null, array $settings = null)
    {
        return new static($model, $defaults, $settings);
    }


    /**
     * @param string $rules
     * @return array
     */
    protected function columnValidationRules($rules = '')
    {
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


    public function getTitle($column)
    {
        $key = 'db::'.$this->table.'.'.$column;
        return \Lang::has($key) ? \Lang::get($key) : $column;
    }

    public function showInTable($column, $fields)
    {
        return array_key_exists($column, $fields);
    }

    public function showInEdit($column, $fields)
    {
        return $column == 'id' ? 'disabled' : array_key_exists($column, $fields);
    }

    public function showInCreate($column, $fields)
    {
        return $column == 'id' ? false : array_key_exists($column, $fields);
    }

    public function showInSearch($column, $fields)
    {
        return array_key_exists($column, $fields);
    }

    public function getWidgetType($column)
    {
        $fKeys = $this->foreignKeys;
        $fData = null;
        $data = [];

        if ( !($this->schema && array_key_exists($column, $this->schema)) )
        {
            return $data;
        }

        # CHECK FIELD VALIDATION RULES
        $columnRules = array_key_exists($column, $this->modelRules) ?
            $this->columnValidationRules($this->modelRules[$column]) : null;
        //var_dump($columnRules);

        $colData = $this->schema[$column];

        $data = [
            'widget'        => null,
            'defaultValue'  => addslashes($colData['default']),
            'required'      => $colData['null'] ? false : true,
            'validate'      => $columnRules
        ];


        // check the foreign key column
        if (array_key_exists($column, $fKeys) && ($fk = $fKeys[$column]))
        {
            /**
             * @var EloquentPlus $foreignModel
             */
            $defaults = $this->defaults;

            $foreignModel = DbTools::detectModelClassByTable($fk->table);

            if ( $foreignModel && ($foreignModel = new $foreignModel) )
            {
                // for large tables use ajax requests
                if ( $foreignModel->count() > 1000 )
                {
                    $fData = \URL::action(ucwords($fk->table).'Controller@index');
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


        $multi = false;

        switch($this->schema[$column]['type'])
        {
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



        return $data;

    }



}

