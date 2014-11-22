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

            $data = $value->toArray();
            $schemaKeys = ['id', 'text'];

            // fix custom relations
            if (count($cRealtions = $value->getCustomRelations()))
            {
                foreach($cRealtions as $cTable)
                {
                    if ($cRelation = $value->{$cTable})
                    {
                        $data[$cTable] = $cRelation->toAngular();
                        $schemaKeys[] = $cTable;
                    }

                    else
                    {
                        \Log::warning('Custom relation does not exist! Relation: '.$cTable);
                    }
                }
            }



            $fKeys = DbTools::fKeys($value->getTable());

            foreach($fKeys as $local => $foreign)
            {
                if (array_key_exists($foreign->table, $data))
                {
                    // does the relation exists
                    // if yes get its
                    if ($relation = $value->{$foreign->table})
                    {
                        $angularRelations = $relation->getAngularRelations();

                        // before we saved the local key...
                        $data[$foreign->table] = array_merge(
                            $relation->getAngularArray(),
                            is_array($angularRelations) ? $angularRelations : []
                        );
                        $schemaKeys[] = $foreign->table;
                        unset($data[$local]);
                    }
                }
            }

            if ($forSchema) {
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

