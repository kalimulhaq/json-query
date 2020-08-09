<?php

namespace Kalimulhaq\JsonQuery;

class JsonMap {

    /**
     * Select Clause
     * @var array
     */
    public $select;

    /**
     * Where Clause
     * @var WhereMap
     */
    public $where;

    /**
     * Order Clause
     * @var array[OrderMap]
     */
    public $order;

    /**
     * Include Relationships
     * @var array[IncludeMap]
     */
    public $include;

    /**
     * Include Relationships Count
     * @var array[IncludeCountMap]
     */
    public $include_count;

    /**
     * Scopes to add to query
     * @var array
     */
    public $scopes;

}

trait WhereOrTrait {

    /**
     * OR Grouping
     * @var array[WhereMap]
     */
    public $or;

}

trait WhereAndTrait {

    /**
     * AND Grouping
     * @var array[WhereMap]
     */
    public $and;

}

trait WhereConditionTrait {

    /**
     * Field Name
     * @var string
     * @required
     */
    public $field;

    /**
     * Field Value
     * @var mixed|null
     */
    public $value;

    /**
     * Operator
     * @var string
     */
    public $operator = '=';

    /**
     * Sub Operator
     * @var string
     */
    public $sub_operator = '=';

}

trait WildcardConditionTrait {

    /**
     * Fields Name
     * @var array
     */
    public $fields;

    /**
     * Value
     * @var string
     */
    public $value;

}

trait WildcardTrait {

    /**
     * Wildcard Condition
     * @var WildcardConditionMap
     */
    public $wildcard;

}

class WhereOrMap {

    use WhereOrTrait;
}

class WhereAndMap {

    use WhereAndTrait;
}

class WhereConditionMap {

    use WhereConditionTrait;
}

class WildcardConditionMap {

    use WildcardConditionTrait;
}

class WildcardMap {

    use WildcardTrait;
}

class WhereMap {

    use WhereOrTrait;
    use WhereAndTrait;
    use WhereConditionTrait;
    use WildcardTrait;
}

class IncludeMap {

    /**
     * Relationship Name
     * @var string
     * @required
     */
    public $relation;

    /**
     * Select Clause
     * @var array
     */
    public $select;

    /**
     * Where Clause
     * @var WhereMap
     */
    public $where;

    /**
     * Order Clause
     * @var array[OrderMap]
     */
    public $order;

    /**
     * Include Relationships
     * @var array[IncludeMap]
     */
    public $include;

    /**
     * Include Relationships Count
     * @var array[IncludeCountMap]
     */
    public $include_count;

    /**
     * Scopes to add to query
     * @var array
     */
    public $scopes;

}

class IncludeCountMap {

    /**
     * Relationship Name
     * @var string
     * @required
     */
    public $relation;

    /**
     * Where Clause
     * @var WhereMap
     */
    public $where;

    /**
     * Scopes to add to query
     * @var array
     */
    public $scopes;

}

class OrderMap {

    /**
     * Field Name
     * @var string
     * @required
     */
    public $field;

    /**
     * Sorting Direction
     * @var string
     */
    public $order;

}
