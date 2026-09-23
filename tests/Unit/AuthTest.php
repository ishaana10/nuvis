<?php
declare(strict_types=1);

namespace Nuvis\Tests\Unit;

use PHPUnit\Framework\TestCase;
use NuAuth;

class AuthTest extends TestCase {
    private NuAuth $auth;

    protected function setUp(): void {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        $this->auth = NuAuth::getInstance();
    }

    public function testCsrfTokenGenerationAndVerification(): void {
        $token = $this->auth->getCsrfToken();
        $this->assertNotEmpty($token);
        $this->assertTrue($this->auth->verifyCsrf($token));
        $this->assertTrue($this->auth->verifyCsrfToken($token));
        $this->assertFalse($this->auth->verifyCsrf('invalid_token'));
    }

    public function testResolveHashesWithEscaping(): void {
        $_SESSION['nu_user_meta'] = [
            'station' => "North's Station",
            'dept_id' => 101
        ];

        $sql = "SELECT * FROM sales WHERE station = '##station##' AND dept = ##dept_id##";
        $resolved = $this->auth->resolveHashes($sql);

        $this->assertStringNotContainsString('##station##', $resolved);
        $this->assertStringNotContainsString('##dept_id##', $resolved);
        $this->assertStringContainsString('101', $resolved);
    }
}
