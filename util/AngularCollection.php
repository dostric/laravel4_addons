<?php namespace LaravelAddons\Util;


use Illuminate\Database\Eloquent\Collection;
use LaravelAddons\Eloquent\EloquentPlus;


class AngularCollection extends Collection {



    public function toAngular($forSchema = false)
    {
        return array_values(array_map(function($value) use ($forSchema)
        {
            return $value->toAngular($forSchema);

        }, $this->items));
    }


    public function toAngularJson($options = 0)
    {
        return json_encode($this->toAngular(), $options);
    }

    /**
     * Load a set of relationships onto the collection.
     *
     * @param  mixed  $relations
     * @return $this
     */
    public function load($relations)
    {
        if (count($this->items) > 0)
        {
            if (is_string($relations)) $relations = func_get_args();

            $first = $this->first();
            if ($first instanceof EloquentPlus)
            {
                $customRelations = $first->getCustomRelations();
                foreach($relations as $relationKey => $relationName)
                {
                    if (in_array($relationName, $customRelations))
                    {
                        // if it is a custom apply it to all
                        foreach($this->all() as $item)
                        {
                            /** @var EloquentPlus $item */
                            $item->setRelation($relationName, $item->{$relationName}());
                            unset($relations[$relationKey]);
                        }
                    }
                }

            }

        }

        return parent::load($relations);
    }

}
