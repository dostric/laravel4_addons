<?php namespace LaravelAddons\Util;

use Illuminate\Database\Eloquent\Collection;
use LaravelAddons\Eloquent\EloquentPlus;

class AngularCollection extends Collection {



    public function toAngular($forSchema = false)
    {
        return array_values(array_map(function($value) use ($forSchema)
        {
            // for many?
            if ($value instanceof ArrayableInterface) { throw new \Exception('->toAngular Nisam ovo jos testirao!'); return $value->toAngular(); }

            /** @var EloquentPlus $value */
            /** @var EloquentPlus $relation */

            $data = $value->toArray();
            $schemaKeys = ['id', 'text'];


            // iterate custom relations
            if (count($cRealtions = $value->getCustomRelations()))
            {
                foreach($cRealtions as $cTable)
                {
                    if ($value->hasRelation($cTable))
                    {
                        if ($relation = $value->{$cTable})
                        {
                            $data[$cTable] = $relation->toAngular();
                            $schemaKeys[] = $cTable; // do not delete this key if we need it in schema
                        }
                    }
                }
            }


            // iterate foreign relations
            $fKeys = DbTools::fKeys($value->getTable());
            foreach($fKeys as $local => $foreign)
            {
                if (array_key_exists($foreign->table, $data))
                {
                    // does the relation exists
                    // if yes get its
                    if ($value->hasRelation($foreign->table))
                    {
                        if ($relation = $value->{$foreign->table})
                        {
                            $data[$local] = array_merge(
                                $relation->getAngularArray(),
                                $relation->getAngularRelations()
                            );

                            $schemaKeys[] = $local; // do not delete this key if we need it in schema
                            unset($data[$foreign->table]);
                        }
                    }
                }
            }


            // for the schema part add the text and discard unnecessary data
            if ($forSchema)
            {
                $data['text'] = $value->getTitle();
                $data = array_intersect_key($data, array_flip($schemaKeys));
            }

            return $data;

        }, $this->items));
    }


    public function toAngularJson($options = 0)
    {
        return json_encode(
            array_values($this->toAngular()),
            $options
        );
    }


}
