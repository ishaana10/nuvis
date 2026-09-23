<?php
declare(strict_types=1);

namespace Nuvis\Tests\Unit;

use PHPUnit\Framework\TestCase;
use ProjectContext;

class ProjectContextTest extends TestCase {
    public function testDefaultProjectContextId(): void {
        $id = ProjectContext::getId();
        $this->assertGreaterThanOrEqual(1, $id);
    }
}
