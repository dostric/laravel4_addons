<?php namespace LaravelAddons\Eloquent;

use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Collection;

class CachedHasMany extends HasMany {


    /**
     * Get the results cached if model is a small table.
     *
     * @return mixed
     */
    public function getResults()
    {
        $model = $this->getModel();

        if ($model instanceof EloquentPlus && $model->isSmallTable())
        {
            return $this->get();
        }

        return $this->query->get();

    }


    /**
     * Match the eagerly loaded results to their parents.
     *
     * @param  array   $models
     * @param  \Illuminate\Database\Eloquent\Collection  $results
     * @param  string  $relation
     * @return array
     */
    public function match(array $models, Collection $results, $relation)
    {
        return $this->matchMany($models, $results, $relation);
    }


    protected function matchOneOrMany(array $models, Collection $results, $relation, $type)
    {
        $dictionary = $this->buildDictionary($results);

        // Once we have the dictionary we can simply spin through the parent models to
        // link them up with their children using the keyed dictionary to make the
        // matching very convenient and easy work. Then we'll just return them.
        foreach ($models as $model)
        {
            $key = $model->getAttribute($this->localKey);

            if (isset($dictionary[$key]))
            {
                $value = $this->getRelationValue($dictionary, $key, $type);

                $model->setRelation($relation, $value);
            }
        }

        return $models;
    }


    public function get($columns = array('*'), $fresh = false)
    {
        $relation = $this;
        $model = $this->query->getModel();

        if ($model instanceof EloquentPlus && $model->isSmallTable())
        {
            $parent = $relation->getParent();
            $parentId = $parent->{$relation->localKey};
            $parentTable = $parent->getTable();
            $localTable = $model->getTable();

            $key = $model::cacheKeyChild($parentTable, $localTable, $parentId);

            if ($fresh === true)
            {
                \Cache::tags($localTable)->flush();
                $model::forgetChild($key);
            }

            \Log::info('Cache forever key = ' . $key);
            $data = \Cache::tags($localTable)->rememberForever($key, function() use ($relation) {

                return $relation->query->get();

            });

            return $data;


            /*
            return $model->all()->filter(function($item) use ($relation)
            {
                $data = $relation->getParent()->{$relation->localKey} == $item->{$relation->getPlainForeignKey()};

                /*
                \Log::info(
                    'Gledam ' . $relation->getParent()->getTable() .'.' . $relation->localKey .
                        ' == ' .
                    $item->getTable(). '.' . $relation->getPlainForeignKey() . ' jednak je ' . $item->{$relation->getPlainForeignKey()} . ' ' . ($data === true ? 'da ' : ' ne')

                );
                * /

                return $data;

            });
            */

        }

        return $this->query->get($columns);
    }


    public function first()
    {
        return $this->get()->first();
    }



}