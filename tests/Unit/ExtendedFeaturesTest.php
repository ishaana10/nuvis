<?php
declare(strict_types=1);

namespace Nuvis\Tests\Unit;

use PHPUnit\Framework\TestCase;
use NuAuth;
use NuDatabase;

class ExtendedFeaturesTest extends TestCase
{
    private NuAuth $auth;

    protected function setUp(): void
    {
        $this->auth = new NuAuth();
    }

    public function testTOTPSecretGeneration(): void
    {
        $secret = $this->auth->generateTOTPSecret();
        $this->assertSame(16, strlen($secret));
        $this->assertMatchesRegularExpression('/^[A-Z2-7]+$/', $secret);
    }

    public function testVerifyTOTP(): void
    {
        $secret = 'JBSWY3DPEHPK3PXP';
        // Non-matching code should fail
        $this->assertFalse($this->auth->verifyTOTP($secret, '000000'));
    }

    public function testBackupCodesGenerationAndVerification(): void
    {
        $db = NuDatabase::getInstance();
        try {
            $db->exec("CREATE TABLE IF NOT EXISTS nu_users (usr_id INTEGER PRIMARY KEY, usr_username TEXT, usr_custom_fields TEXT)");
            $db->exec("INSERT OR REPLACE INTO nu_users (usr_id, usr_username, usr_custom_fields) VALUES (999, 'testuser', '{}')");
        } catch (\Throwable $e) {}

        $userId = 999;
        $codes = $this->auth->generateBackupCodes($userId);

        $this->assertCount(8, $codes);
        $this->assertMatchesRegularExpression('/^\d{4}-\d{4}$/', $codes[0]);

        // Verify first backup code
        $valid = $this->auth->verifyBackupCode($userId, $codes[0]);
        $this->assertTrue($valid);

        // Code should be single-use (second attempt fails)
        $reuse = $this->auth->verifyBackupCode($userId, $codes[0]);
        $this->assertFalse($reuse);
    }

    public function testPluginFileExistsAndReturnsArray(): void
    {
        $pluginFile = NU_ROOT . '/plugins/sample_plugin/plugin.php';
        $this->assertFileExists($pluginFile);

        $pluginData = include $pluginFile;
        $this->assertIsArray($pluginData);
        $this->assertArrayHasKey('name', $pluginData);
        $this->assertArrayHasKey('init', $pluginData);
        $this->assertIsCallable($pluginData['init']);
    }
}
