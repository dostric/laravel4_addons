<?php namespace LaravelAddons\Eloquent;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Collection;

class CachedBelongsTo extends BelongsTo {


    /**
     * Get the results cached if model is a small table.
     *
     * @return mixed
     */
    public function getResults()
    {
        $model = $this->query->getModel();

        if ($model instanceof EloquentPlus)
        {
            if ($model->isSmallTable())
            {
                return $model->all()->get($this->parent->{$this->foreignKey});
            }
        }

        return $this->query->first();
    }


    /**
     * @param array $columns
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function get($columns = array('*'))
    {
        $model = $this->query->getModel();

        if ($model instanceof EloquentPlus)
        {
            if ($model->isSmallTable())
            {
                return $model->all($columns);
            }
        }

        return $this->query->get($columns);
    }


    /**
     * Execute the query and get the first result.
     *
     * @param  array  $columns
     * @return \Illuminate\Database\Eloquent\Model|null
     */
    public function first($columns = array('*')) {
        return $this->get($columns)->get($this->parent->{$this->foreignKey}, null);
    }


    /**
     * Match the eagerly loaded results to their parents.
     * We`ll just speed up things for relatively large collections
     * since we already have pKey => value result collection.
     *
     * @param  array   $models
     * @param  Collection  $results
     * @param  string  $relation
     * @return array
     */
    public function match(array $models, Collection $results, $relation)
    {
        $foreign = $this->foreignKey;

        // Once we have the dictionary constructed, we can loop through all the parents
        // and match back onto their children using these keys of the dictionary and
        // the primary key of the children to map them onto the correct instances.
        foreach ($models as $model)
        {
            if ($results->has($fId = $model->$foreign))
            {
                $model->setRelation($relation, $results->get($fId));
            }
        }

        return $models;
    }

}