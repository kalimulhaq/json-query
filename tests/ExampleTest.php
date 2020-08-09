<?php

namespace Kalimulhaq\JsonQuery\Tests;

use Orchestra\Testbench\TestCase;
use Kalimulhaq\JsonQuery\JsonQueryServiceProvider;

class ExampleTest extends TestCase
{

    protected function getPackageProviders($app)
    {
        return [JsonQueryServiceProvider::class];
    }
    
    /** @test */
    public function true_is_true()
    {
        $this->assertTrue(true);
    }
}
