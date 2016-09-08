<?php
/**
 * Class SampleTest
 *
 * @package Edd_Jvzoo
 */

/**
 * Sample test case.
 */
class JVZooTest extends WP_UnitTestCase {

	public static function setUpBeforeClass() {
		return parent::setUpBeforeClass(); // TODO: Change the autogenerated stub
	}

	/**
	 * Test that the correct cverify value passes the check
	 */
	function test_jvzipnVerification_pass() {
		global $edd_options;

		$edd_options['edd_jvzoo_secret_key'] = '1234';
		$_POST['cverify'] = 'abc';


	}

	function test_sample_string() {
		$string = 'Unit tests are sweet';
		$this->assertEquals('Unit tests are sweet', $string);
	}

	function build_good_request() {
		$_POST['cverify'] = 'abc';

	}
}