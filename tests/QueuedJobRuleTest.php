<?php

namespace Symbiote\QueuedJobs\Tests;

use SilverStripe\Dev\SapphireTest;
use Symbiote\QueuedJobs\DataObjects\QueuedJobRule;

class QueuedJobRuleTest extends SapphireTest
{
    /**
     * @param string $property
     * @param mixed $value
     * @param mixed $expected
     * @dataProvider ruleGetterProvider
     */
    public function testQueueRuleGetters($property, $value, $expected)
    {
        $rule = QueuedJobRule::create();
        $rule->{$property} = $value;

        $this->assertSame($expected, $rule->{$property});
    }

    public function ruleGetterProvider(): array
    {
        return [
            ['Processes', null, 1],
            ['Processes', 0, 0],
            ['Processes', 1, 1],
            ['Processes', 2, 2],
            ['Handler', null, null],
            ['Handler', '', null],
            ['Handler', 'Test', 'Test'],
            ['MinimumProcessorUsage', null, null],
            ['MinimumProcessorUsage', 0, 0],
            ['MinimumProcessorUsage', 1, 1],
            ['MaximumProcessorUsage', null, null],
            ['MaximumProcessorUsage', 0, 0],
            ['MaximumProcessorUsage', 1, 1],
            ['MinimumMemoryUsage', null, null],
            ['MinimumMemoryUsage', 0, 0],
            ['MinimumMemoryUsage', 1, 1],
            ['MaximumMemoryUsage', null, null],
            ['MaximumMemoryUsage', 0, 0],
            ['MaximumMemoryUsage', 1, 1],
            ['MinimumSiblingProcessorUsage', null, null],
            ['MinimumSiblingProcessorUsage', 0, 0],
            ['MinimumSiblingProcessorUsage', 1, 1],
            ['MaximumSiblingProcessorUsage', null, null],
            ['MaximumSiblingProcessorUsage', 0, 0],
            ['MaximumSiblingProcessorUsage', 1, 1],
            ['MinimumSiblingMemoryUsage', null, null],
            ['MinimumSiblingMemoryUsage', 0, 0],
            ['MinimumSiblingMemoryUsage', 1, 1],
            ['MaximumSiblingMemoryUsage', null, null],
            ['MaximumSiblingMemoryUsage', 0, 0],
            ['MaximumSiblingMemoryUsage', 1, 1],
        ];
    }
}
