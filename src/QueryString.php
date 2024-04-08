<?php

namespace GrupoCometa\Builder;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;

class QueryString
{
    protected $model;
    protected $builder;

    public function __construct($model, $paramns)
    {
        $this->initializeBuilder($model);
        $this->queryConstruct($paramns);
    }
    private function initializeBuilder($model)
    {
        if ($model instanceof Collection) {
            $class = get_class($model[0]);
            $this->model = new $class();
            $this->builder = $model;
            return;
        }
        if ($model instanceof HasManyThrough || $model instanceof HasMany || $model instanceof BelongsTo || $model instanceof HasOne || $model instanceof BelongsToMany) {
            $class = get_class($model->getRelated());
            $this->model = new $class();
            $this->builder = $model;
            return;
        }
        if ($model instanceof Builder) {
            $this->model = $model->getModel();
            $this->builder = $model;
            return;
        }

        $this->model = $model;
        $this->builder = $model::select();
    }

    private function where($key, $operator, $value)
    {
        if(!$this->model->caseSensitive() && !preg_match('/\d/', $value))
        {
            return $this->builder->whereRaw("UPPER($key) $operator UPPER('$value')");
        }
        return $this->builder->where($key, $value);
    }

    protected function getKeyIntersectRequestFillableModel($paramns): array
    {
        $valuesInKeys = array_fill_keys($this->model->getFillable(), '1');
        return array_intersect_key($paramns, $valuesInKeys);
    }

    protected function commaSeparetor($arrayString)
    {
        // abstrari em outra class
        if (is_array($arrayString)) return $arrayString;
        if (preg_match('/\w+,\w+/', $arrayString, $matches)) return explode(',', $arrayString);
        return $arrayString;
    }

    private  function queryConstruct($paramns)
    {
        $keyIntersect = $this->getKeyIntersectRequestFillableModel($paramns);

        foreach ($keyIntersect as $key => $value) {
            $value = $this->commaSeparetor($value);
            $this->whereInOrWhereOrWhereBetWeen($key, $value);
        }
    }

    protected  function whereInOrWhereOrwhereBetWeen($key, $value)
    {
        if (is_array($value)) return $this->whereInOrWhereBetWeen($key, $value);
        return $this->whereOrWhreLikeOrWhereNotNull($key,$value);

    }

    protected function whereOrWhreLikeOrWhereNotNull($key, $value)
    {
        if ($value == 'notnull') {
            return $this->builder->whereNotNull($key);
        }
        
        if(preg_match("/\*/",$value, $matches)){
            $value = str_replace('*', '%', $value);
            return $this->where($key,'like',$this->empytOrNullToNull($value));
        }
        return $this->where($key, '=' ,$this->empytOrNullToNull($value));
    }

    private  function empytOrNullToNull($value)
    {
        if ($value == 'null' || $value == '') return null;
        return $value;
    }

    protected  function whereInOrWhereBetWeen($key, array $value)
    {
        if (count($value) != 2) return $this->builder->whereIn($key, $value);
        if ($this->twoPositionIsDate($value[0], $value[1])) {
            $value = $this->invetDateIfFirstLargerLast($value[0], $value[1]);
            return $this->builder->whereBetWeen($key, $value);
        }
        return  $this->builder->whereIn($key, $value);
    }

    protected  function twoPositionIsDate($firstDate, $lastDate)
    {
        $regexDate = '/^\d{2}(-|\/)\d{2}(-|\/)\d{4}|^\d{4}(-|\/)\d{2}(-|\/)\d{2}/i';
        return preg_match($regexDate, $firstDate) && preg_match($regexDate, $lastDate);
    }

    protected  function invetDateIfFirstLargerLast($firstDate, $lastDate)
    {
        if (strtotime($firstDate) > strtotime($lastDate)) {
            $aux = $lastDate;
            $lastDate = $firstDate;
            $firstDate = $aux;
        }
        return [$firstDate, $lastDate];
    }

    public function getBuilder(): Builder | HasMany | BelongsTo | HasOne | BelongsToMany
    {
        return $this->builder;
    }
}
