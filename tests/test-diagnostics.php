<?php
/**
 * Diagnostics tests.
 *
 * @package Alynt_WC_Customer_Order_Manager
 */

use AlyntWCOrderManager\Diagnostics;

class Test_Diagnostics extends \PHPUnit\Framework\TestCase {
	protected function setUp(): void {
		$GLOBALS['awcom_test_options'] = array();
	}

	public function test_diagnostics_are_disabled_by_default() {
		Diagnostics::log( 'database', 'error', 'sample_failed', 'Sample failed.' );

		$this->assertSame( array(), get_option( Diagnostics::EVENTS_OPTION, array() ) );
	}

	public function test_diagnostics_redact_sensitive_context_before_storage() {
		update_option(
			Diagnostics::SETTINGS_OPTION,
			array(
				'enabled'        => true,
				'min_level'      => 'debug',
				'retention_days' => 30,
			)
		);

		Diagnostics::log(
			'security',
			'warning',
			'sensitive_context',
			'Sensitive context was supplied.',
			array(
				'order_id'      => 123,
				'password'      => 'secret-password',
				'nested'        => array(
					'api_key' => 'abc123',
				),
				'request_body'  => '{"customer":"Jane"}',
				'public_note'   => '<strong>safe summary</strong>',
			)
		);

		$events = get_option( Diagnostics::EVENTS_OPTION, array() );

		$this->assertCount( 1, $events );
		$this->assertSame( '[redacted]', $events[0]['context']['password'] );
		$this->assertSame( '[redacted]', $events[0]['context']['nested']['api_key'] );
		$this->assertSame( '[redacted]', $events[0]['context']['request_body'] );
		$this->assertSame( 'safe summary', $events[0]['context']['public_note'] );
	}
}
