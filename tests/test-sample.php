<?php

class Test_Sample extends \PHPUnit\Framework\TestCase {
	public function test_plugin_constants_defined() {
		$this->assertTrue( defined( 'AWCOM_VERSION' ) );
		$this->assertSame( '1.1.3', AWCOM_VERSION );
	}
}
