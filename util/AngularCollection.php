<?php namespace LaravelAddons\Util;

use Illuminate\Database\Eloquent\Collection;
use LaravelAddons\Eloquent\EloquentPlus;

class AngularCollection extends Collection {



    public function toAngular($forSchema = false)
    {
        return array_values(array_map(function($value) use ($forSchema)
        {
            return $value->toAngular();

        }, $this->items));
    }


    public function toAngularJson($options = 0)
    {
        return json_encode($this->toAngular(), $options);
    }


}
