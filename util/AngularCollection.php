<?php namespace LaravelAddons\Util;

use Illuminate\Database\Eloquent\Collection;
use LaravelAddons\Eloquent\EloquentPlus;

class AngularCollection extends Collection {




    public function toAngular()
    {
        return array_map(function($value)
        {
            // for many?
            if ($value instanceof ArrayableInterface) {
                throw new \Exception('->toAngular Nisam ovo jos testirao!');
                return $value->toAngular();
            }

            /** @var EloquentPlus $value */

            $data = $value->toArray();
            $fKeys = DbTools::fKeys($value->getTable());

            foreach($fKeys as $local => $foreign)
            {
                if (array_key_exists($foreign->table, $data))
                {
                    // does the relation exists
                    if ($relation = $value->{$foreign->table})
                    {
                        $angularRelations = $relation->getAngularRelations();
                        $data[$local] = array_merge(
                            $relation->getAngularArray(),
                            is_array($angularRelations) ? $angularRelations : []
                        );
                        unset($data[$foreign->table]);
                    }
                }
            }

            return $data;

        }, $this->items);
    }


    public function toAngularJson($options = 0)
    {
        return json_encode(
            array_values($this->toAngular()),
            $options
        );
    }


}

