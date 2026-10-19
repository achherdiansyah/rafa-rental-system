<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Tests\Concerns\ManagesTimesheetWorkflow;

abstract class TestCase extends BaseTestCase
{
    use ManagesTimesheetWorkflow;
}
