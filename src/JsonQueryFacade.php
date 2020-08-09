<?php

namespace Kalimulhaq\JsonQuery;

use Illuminate\Support\Facades\Facade;

/**
 * @see \Kalimulhaq\JsonQuery\Skeleton\SkeletonClass
 */
class JsonQueryFacade extends Facade
{
    /**
     * Get the registered name of the component.
     *
     * @return string
     */
    protected static function getFacadeAccessor()
    {
        return 'jsonquery';
    }
}
