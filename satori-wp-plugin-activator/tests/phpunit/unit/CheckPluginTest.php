<?php
/**
 * @group <mu-plugins/plugin-activator>
 */
class CheckPluginTest extends WP_UnitTestCase
{
    // public function setUp(): void
    // {
    //     parent::setUp();
    //     $adminId = self::factory()->user->create(['role' => 'administrator']);
    //     wp_set_current_user($adminId);
    // }
    
    public function testWorking()
    { 
        $result = 'test';
        $this->assertSame('test', $result);
    }
}
