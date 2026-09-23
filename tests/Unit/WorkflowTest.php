<?php
declare(strict_types=1);

namespace Nuvis\Tests\Unit;

use PHPUnit\Framework\TestCase;

class WorkflowTest extends TestCase {
    public function testWorkflowHookParsing(): void {
        $hookJson = json_encode([
            'action' => 'update_record',
            'table' => 'demo_customer_requests',
            'field' => 'status',
            'value' => 'Completed'
        ]);

        $decoded = json_decode($hookJson, true);
        $this->assertIsArray($decoded);
        $this->assertEquals('update_record', $decoded['action']);
        $this->assertEquals('Completed', $decoded['value']);
    }
}
