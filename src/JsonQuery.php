<?php

namespace Kalimulhaq\JsonQuery;

use RuntimeException;
use JsonMapper;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\Builder;

class JsonQuery {

    private $model;
    private $query;
    private $jsonRaw;
    private $json;
    private $result;
    private $meta;

    public function __construct($instance = null, string $json_str = null) {
        if ($instance && $json_str) {
            $this->init($instance, $json_str);
        }
    }

    public function init($instance, $json_str) {
        if ($instance instanceof Model) {
            $this->model = $instance;
            $this->query = $this->model->query();
        } else if ($instance instanceof Relation) {
            $this->model = $instance;
            $this->query = $this->model->getQuery();
        } else if ($instance instanceof Builder) {
            $this->model = $instance->getModel();
            $this->query = $instance;
        } else {
            $this->model = new $instance();
            $this->query = $this->model->query();
        }

        $this->jsonRaw = new \stdClass();
        if (!empty($json_str)) {
            $this->jsonRaw = json_decode($json_str);
            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new RuntimeException('Invalid JSON String');
            }
        }

        $mapper = new JsonMapper();
        $this->json = $mapper->map($this->jsonRaw, new JsonMap());

        return $this;
    }

    public function model() {
        return $this->model;
    }

    public function query() {
        return $this->query;
    }

    public function jsonRaw() {
        return $this->jsonRaw;
    }

    public function json() {
        return $this->json;
    }

    public function result() {
        return $this->result;
    }

    public function meta() {
        return $this->meta;
    }

    public function buildQuery() {
        $this->buildSelect();
        $this->buildWhere();
        $this->buildOrder();
        $this->buildInclude();
        $this->buildIncludeCount();
        $this->buildScopes();
        return $this;
    }

    public function buildSelect() {
        $selectMap = $this->json->select;
        $this->_buildSelect($this->query, $selectMap);
        return $this;
    }

    public function buildWhere() {
        $whereMap = $this->json->where;
        $this->_buildWhere($this->query, $whereMap);
        return $this;
    }

    public function buildOrder() {
        $orderMap = $this->json->order;
        $this->_buildOrder($this->query, $orderMap);
        return $this;
    }

    public function buildInclude() {
        $includeMap = $this->json->include;
        $this->_buildInclude($this->query, $includeMap);
        return $this;
    }

    public function buildIncludeCount() {
        $includeCountMap = $this->json->include_count;
        $this->_buildIncludeCount($this->query, $includeCountMap);
        return $this;
    }

    public function buildScopes() {
        $scopesMap = $this->json->scopes;
        $this->_buildScopes($this->query, $scopesMap);
        return $this;
    }

    public function addScopes($scopes) {
        $_scopes = is_array($scopes) ? $scopes : func_get_args();
        foreach ($_scopes as $scope) {
            $this->query->$scope();
        }

        return $this;
    }

    public function buildResult($limit = 0, $page = 1, $query = null) {
        if ($query instanceof Builder) {
            $this->model = $query->getModel();
            $this->query = $query;
        }

        if (!empty($limit)) {
            $_page = is_numeric($page) && $page ? $page : 1;
            $paginator = $this->query->paginate($limit, ['*'], 'page', $_page);
            $this->result = collect()->make($paginator->items());
            $this->meta = array(
                'page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'from' => $paginator->firstItem(),
                'to' => $paginator->lastItem(),
                'limit' => (int) $paginator->perPage(),
                'total' => $paginator->total(),
                'has_more_pages' => $paginator->hasMorePages(),
                'is_first_page' => $paginator->onFirstPage(),
                'query' => $this->getSql($this->query),
            );
        } else {
            $this->result = $this->query->get();
            $count = $this->query->count();
            $this->meta = array(
                'page' => 1,
                'last_page' => null,
                'from' => 1,
                'to' => $count,
                'limit' => $count,
                'total' => $count,
                'has_more_pages' => false,
                'is_first_page' => true,
                'query' => $this->getSql($this->query),
            );
        }

        return $this;
    }

    private function _buildSelect(&$query, $select) {
        $model = $query->getModel();
        $table = $model->getTable();

        if (empty($select)) {
            $select = array('*');
        } else {
            if (!in_array('id', $select)) {
                array_unshift($select, "id");
            }

            if (!empty($model->forcedSelect)) {
                $select = array_merge($select, $model->forcedSelect);
            } else if (!empty($model::$forcedSelect)) {
                $select = array_merge($select, $model::$forcedSelect);
            }
        }

        $_select = array_map(function($e) use ($table) {
            return "$table.$e";
        }, array_unique($select));

        $query->select($_select);

        return $query;
    }

    private function _buildWhere(&$query, $where) {

        if (!empty($where->field)) {
            $operator = isset($where->operator) ? $where->operator : '=';
            $sub_operator = isset($where->sub_operator) ? $where->sub_operator : null;
            $this->_buildWhereCondition($query, $where->field, $where->value, $operator, $sub_operator);
        }

        if (!empty($where->and)) {
            $query->where(function ($query) use ($where) {
                $this->_buildWhereAnd($query, $where->and);
            });
        }

        if (!empty($where->or)) {
            $query->where(function ($query) use ($where) {
                $this->_buildWhereOr($query, $where->or);
            });
        }

        if (!empty($where->wildcard) && !empty($where->wildcard->fields)) {
            $query->where(function ($query) use ($where) {
                $filter = array();
                foreach ($where->wildcard->fields as $field) {
                    $conditions = $this->_buildWildcardCondition($field, $where->wildcard->value);
                    $filter = array_merge($filter, $conditions);
                }
                $this->_buildWhereOr($query, $filter);
            });
        }

        return $query;
    }

    private function _buildOrder(&$query, $order) {
        if (!empty($order)) {
            foreach ($order as $val) {
                $dir = in_array(strtolower($val->order), array('asc', 'desc')) ? $val->order : 'asc';
                $query->orderBy($val->field, $dir);
            }
        }
        return $query;
    }

    private function _buildInclude(&$query, $include) {

        if (!empty($include)) {
            $includes = array();
            foreach ($include as $val) {
                $includes[$val->relation] = function ($query) use ($val) {
                    $this->_buildSelect($query, $val->select);
                    $this->_buildWhere($query, $val->where);
                    $this->_buildOrder($query, $val->order);
                    $this->_buildInclude($query, $val->include);
                    $this->_buildScopes($query, $val->scopes);
                };
            }
            $query->with($includes);
        }

        return $query;
    }

    private function _buildIncludeCount(&$query, $include) {

        if (!empty($include)) {
            $includes = array();
            foreach ($include as $val) {
                $includes[$val->relation] = function ($query) use ($val) {
                    $this->_buildWhere($query, $val->where);
                    $this->_buildScopes($query, $val->scopes);
                };
            }
            $query->withCount($includes);
        }

        return $query;
    }

    private function _buildScopes(&$query, $scopes) {
        $_scopes = !empty($scopes) && is_array($scopes) ? $scopes : [];
        foreach ($_scopes as $scope => $arguments) {
            $args = [];
            if (is_string($scope)) {
                $args = is_array($arguments) ? $arguments : explode(',', $arguments);
            } else if (is_string($arguments)) {
                $parts = explode(':', $arguments);
                $scope = $parts[0];
                if (!empty($parts[1])) {
                    $args = explode(',', $parts[1]);
                }
            }
            $query->$scope(...$args);
        }

        return $query;
    }

    private function _buildWhereAnd(&$query, array $group) {
        foreach ($group as $condition) {
            $operator = isset($condition->operator) ? $condition->operator : '=';
            $sub_operator = isset($condition->sub_operator) ? $condition->sub_operator : null;
            $this->_buildWhereCondition($query, $condition->field, $condition->value, $operator, $sub_operator);

            if (!empty($condition->and)) {
                $query->where(function ($query) use ($condition) {
                    $this->_buildWhereAnd($query, $condition->and);
                });
            }

            if (!empty($condition->or)) {
                $query->where(function ($query) use ($condition) {
                    $this->_buildWhereOr($query, $condition->or);
                });
            }
        }

        return $query;
    }

    private function _buildWhereOr(&$query, array $group) {
        $firstOr = array_shift($group);
        $operator = isset($firstOr->operator) ? $firstOr->operator : '=';
        $sub_operator = isset($firstOr->sub_operator) ? $firstOr->sub_operator : null;
        $this->_buildWhereCondition($query, $firstOr->field, $firstOr->value, $operator, $sub_operator);

        foreach ($group as $condition) {
            $operator = isset($condition->operator) ? $condition->operator : '=';
            $sub_operator = isset($condition->sub_operator) ? $condition->sub_operator : null;
            $this->_buildOrWhereCondition($query, $condition->field, $condition->value, $operator, $sub_operator);

            if (!empty($condition->and)) {
                $query->where(function ($query) use ($condition) {
                    $this->_buildWhereAnd($query, $condition->and);
                });
            }

            if (!empty($condition->or)) {
                $query->where(function ($query) use ($condition) {
                    $this->_buildWhereOr($query, $condition->or);
                });
            }
        }

        return $query;
    }

    private function _buildWhereCondition(&$query, $field, $value = null, $op = '=', $sub_op = '=') {

        $operator = !empty($op) ? strtolower($op) : '=';
        $sub_operator = !empty($sub_op) ? strtolower($sub_op) : '=';

        if ($operator === 'between') {
            $query->whereBetween($field, $this->_value2Array($value));
        } else if ($operator === 'not_between') {
            $query->whereNotBetween($field, $this->_value2Array($value));
        } else if ($operator === 'in') {
            $query->whereIn($field, $this->_value2Array($value));
        } else if ($operator === 'not_in') {
            $query->whereNotIn($field, $this->_value2Array($value));
        } else if ($operator === 'null') {
            $query->whereNull($field);
        } else if ($operator === 'not_null') {
            $query->whereNotNull($field);
        } else if ($operator === 'date') {
            $query->whereDate($field, $value);
        } else if ($operator === 'day') {
            $query->whereDay($field, $value);
        } else if ($operator === 'month') {
            $query->whereMonth($field, $value);
        } else if ($operator === 'year') {
            $query->whereYear($field, $value);
        } else if ($operator === 'time') {
            $query->whereTime($field, $value);
        } else if ($operator === 'like') {
            $query->where($field, 'like', "%$value%");
        } else if ($operator === 'has') {
            if ($query->getModel()->$field() instanceof MorphTo) {
                $query->hasMorph($field, ['*'], $sub_operator, $value);
            } else {
                $query->has($field, $sub_operator, $value);
            }
        } else if ($operator === 'not_has') {
            if ($query->getModel()->$field() instanceof MorphTo) {
                $query->doesntHaveMorph($field, ['*'], $sub_operator, $value);
            } else {
                $query->doesntHave($field, $sub_operator, $value);
            }
        } else if ($operator === 'where_has') {
            if ($query->getModel()->$field() instanceof MorphTo) {
                $query->whereHasMorph($field, ['*'], function($query) use ($value) {
                    $this->_buildWhere($query, $value);
                });
            } else {
                $query->whereHas($field, function($query) use ($value) {
                    $this->_buildWhere($query, $value);
                });
            }
        } else if ($operator === 'where_not_has') {
            $query->whereDoesntHave($field, function($query) use ($value) {
                $this->_buildWhere($query, $value);
            });
        } else {
            $query->where($field, $operator, $value);
        }

        return $query;
    }

    private function _buildOrWhereCondition(&$query, $field, $value = null, $op = '=', $sub_op = '=') {

        $operator = !empty($op) ? strtolower($op) : '=';
        $sub_operator = !empty($sub_op) ? strtolower($sub_op) : '=';

        if ($operator === 'between') {
            $query->orWhereBetween($field, $this->_value2Array($value));
        } else if ($operator === 'not_between') {
            $query->orWhereNotBetween($field, $this->_value2Array($value));
        } else if ($operator === 'in') {
            $query->orWhereIn($field, $this->_value2Array($value));
        } else if ($operator === 'not_in') {
            $query->orWhereNotIn($field, $this->_value2Array($value));
        } else if ($operator === 'null') {
            $query->orWhereNull($field);
        } else if ($operator === 'not_null') {
            $query->orWhereNotNull($field);
        } else if ($operator === 'date') {
            $query->orWhereDate($field, $value);
        } else if ($operator === 'day') {
            $query->orWhereDay($field, $value);
        } else if ($operator === 'month') {
            $query->orWhereMonth($field, $value);
        } else if ($operator === 'year') {
            $query->orWhereYear($field, $value);
        } else if ($operator === 'time') {
            $query->orWhereTime($field, $value);
        } else if ($operator === 'like') {
            $query->orWhere($field, 'like', "%$value%");
        } else if ($operator === 'has') {
            if ($query->getModel()->$field() instanceof MorphTo) {
                $query->orHasMorph($field, ['*'], $sub_operator, $value);
            } else {
                $query->orHas($field, $sub_operator, $value);
            }
        } else if ($operator === 'not_has') {
            if ($query->getModel()->$field() instanceof MorphTo) {
                $query->orDoesntHaveMorph($field, ['*'], $sub_operator, $value);
            } else {
                $query->orDoesntHave($field, $sub_operator, $value);
            }
        } else if ($operator === 'where_has') {
            if ($query->getModel()->$field() instanceof MorphTo) {
                $query->orWhereHasMorph($field, ['*'], function($query) use ($value) {
                    $this->_buildWhere($query, $value);
                });
            } else {
                $query->orWhereHas($field, function($query) use ($value) {
                    $this->_buildWhere($query, $value);
                });
            }
        } else if ($operator === 'where_not_has') {
            if ($query->getModel()->$field() instanceof MorphTo) {
                $query->orWhereDoesntHaveMorph($field, ['*'], function($query) use ($value) {
                    $this->_buildWhere($query, $value);
                });
            } else {
                $query->orWhereDoesntHave($field, function($query) use ($value) {
                    $this->_buildWhere($query, $value);
                });
            }
        } else {
            $query->orWhere($field, $operator, $value);
        }

        return $query;
    }

    private function _buildWildcardCondition($field, $value) {
        $conditions = [];
        $pieces = explode(':', $field, 2);
        if (count($pieces) === 2) {
            $condition = new \stdClass();
            $condition->sub_operator = null;
            $condition->field = $pieces[0];
            $condition->operator = 'where_has';
            $condition->value = new \stdClass();
            $condition->value->or = $this->_buildWildcardCondition($pieces[1], $value);
            $conditions[] = $condition;
        } else {
            $pieces2 = explode(',', $field);
            foreach ($pieces2 as $key) {
                $condition = new \stdClass();
                $condition->sub_operator = null;
                $condition->field = $key;
                $condition->operator = 'like';
                $condition->value = $value;
                $conditions[] = $condition;
            }
        }
        return $conditions;
    }

    private function _value2Array($value) {
        $arr = array();
        if (is_array($value)) {
            $arr = $value;
        } else if (is_object($value)) {
            $arr = array_values((array) $value);
        } else if (empty($value)) {
            $arr = array();
        } else {
            $arr = explode(',', $value);
        }
        return $arr;
    }

    private function getSql($query) {
        $sql = $query->toSql();
        foreach ($query->getBindings() as $binding) {
            $value = is_numeric($binding) ? $binding : "'" . $binding . "'";
            $sql = preg_replace('/\?/', $value, $sql, 1);
        }
        return $sql;
    }

}
