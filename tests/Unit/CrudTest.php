<?php
declare(strict_types=1);

namespace Nuvis\Tests\Unit;

use PHPUnit\Framework\TestCase;
use NuApi;

class CrudTest extends TestCase {
    private NuApi $api;

    protected function setUp(): void {
        $this->api = new NuApi();
    }

    public function testValidateIdentifierSuccess(): void {
        $clean = $this->api->validateIdentifier('nu_users');
        $this->assertEquals('nu_users', $clean);
    }

    public function testValidateIdentifierInvalidFormatThrows(): void {
        $this->expectException(\Exception::class);
        $this->api->validateIdentifier("nu_users; DROP TABLE nu_users--");
    }
}
