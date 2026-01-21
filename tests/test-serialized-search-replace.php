<?php
/**
 * Tests for iwp_serialized_search_replace function.
 *
 * Run this file directly with PHP:
 * php tests/test-serialized-search-replace.php
 *
 * @package InstaWP_Connect
 */

// Load the functions file
require_once dirname( __DIR__ ) . '/includes/functions-pull-push.php';

/**
 * Simple test runner class.
 */
class IWP_Test_Serialized_Search_Replace {

	private $passed = 0;
	private $failed = 0;
	private $test_results = array();

	/**
	 * Assert that two values are equal.
	 *
	 * @param mixed  $expected Expected value.
	 * @param mixed  $actual   Actual value.
	 * @param string $message  Test description.
	 */
	private function assertEquals( $expected, $actual, $message ) {
		if ( $expected === $actual ) {
			$this->passed++;
			$this->test_results[] = array( 'status' => 'PASS', 'message' => $message );
			echo "\033[32m✓ PASS:\033[0m {$message}\n";
		} else {
			$this->failed++;
			$this->test_results[] = array(
				'status'   => 'FAIL',
				'message'  => $message,
				'expected' => $expected,
				'actual'   => $actual,
			);
			echo "\033[31m✗ FAIL:\033[0m {$message}\n";
			echo "  Expected: " . var_export( $expected, true ) . "\n";
			echo "  Actual:   " . var_export( $actual, true ) . "\n";
		}
	}

	/**
	 * Assert that a value is true.
	 *
	 * @param mixed  $actual  Actual value.
	 * @param string $message Test description.
	 */
	private function assertTrue( $actual, $message ) {
		$this->assertEquals( true, $actual, $message );
	}

	/**
	 * Test simple string replacement (non-serialized).
	 */
	public function test_simple_string_replacement() {
		echo "\n--- Testing Simple String Replacement ---\n";

		$search  = 'http://oldsite.com';
		$replace = 'https://newsite.com';
		$data    = 'Visit http://oldsite.com for more info.';

		$result = iwp_serialized_search_replace( $search, $replace, $data );
		$this->assertEquals(
			'Visit https://newsite.com for more info.',
			$result,
			'Simple string replacement works'
		);
	}

	/**
	 * Test serialized string replacement.
	 */
	public function test_serialized_string_replacement() {
		echo "\n--- Testing Serialized String Replacement ---\n";

		$search  = 'http://oldsite.com';
		$replace = 'https://newsite.com';

		// Create serialized data with the old URL
		$original = serialize( 'http://oldsite.com' );
		$result   = iwp_serialized_search_replace( $search, $replace, $original );

		// The result should be properly serialized with correct length
		$expected = serialize( 'https://newsite.com' );
		$this->assertEquals( $expected, $result, 'Serialized string replacement maintains correct length' );

		// Verify the result can be unserialized
		$unserialized = @unserialize( $result );
		$this->assertEquals( 'https://newsite.com', $unserialized, 'Result can be unserialized correctly' );
	}

	/**
	 * Test serialized array replacement.
	 */
	public function test_serialized_array_replacement() {
		echo "\n--- Testing Serialized Array Replacement ---\n";

		$search  = 'http://oldsite.com';
		$replace = 'https://newsite.com';

		$original_array = array(
			'site_url'    => 'http://oldsite.com',
			'home_url'    => 'http://oldsite.com',
			'admin_email' => 'admin@oldsite.com',
			'nested'      => array(
				'image_url' => 'http://oldsite.com/wp-content/uploads/image.jpg',
			),
		);
		$serialized = serialize( $original_array );

		$result = iwp_serialized_search_replace( $search, $replace, $serialized );

		// Verify result is valid serialized data
		$unserialized = @unserialize( $result );
		$this->assertTrue( $unserialized !== false, 'Result is valid serialized data' );

		// Verify replacements were made
		$this->assertEquals(
			'https://newsite.com',
			$unserialized['site_url'],
			'site_url was replaced'
		);
		$this->assertEquals(
			'https://newsite.com',
			$unserialized['home_url'],
			'home_url was replaced'
		);
		$this->assertEquals(
			'admin@oldsite.com',
			$unserialized['admin_email'],
			'Partial match in email preserved (no false positive)'
		);
		$this->assertEquals(
			'https://newsite.com/wp-content/uploads/image.jpg',
			$unserialized['nested']['image_url'],
			'Nested array URL was replaced'
		);
	}

	/**
	 * Test serialized object replacement.
	 */
	public function test_serialized_object_replacement() {
		echo "\n--- Testing Serialized Object Replacement ---\n";

		$search  = 'http://oldsite.com';
		$replace = 'https://newsite.com';

		$original_object = new stdClass();
		$original_object->site_url = 'http://oldsite.com';
		$original_object->options  = array(
			'logo_url' => 'http://oldsite.com/logo.png',
		);
		$serialized = serialize( $original_object );

		$result = iwp_serialized_search_replace( $search, $replace, $serialized );

		// Verify result is valid serialized data
		$unserialized = @unserialize( $result );
		$this->assertTrue( is_object( $unserialized ), 'Result is a valid object' );
		$this->assertEquals(
			'https://newsite.com',
			$unserialized->site_url,
			'Object property was replaced'
		);
		$this->assertEquals(
			'https://newsite.com/logo.png',
			$unserialized->options['logo_url'],
			'Nested array in object was replaced'
		);
	}

	/**
	 * Test double-serialized data.
	 */
	public function test_double_serialized_data() {
		echo "\n--- Testing Double-Serialized Data ---\n";

		$search  = 'http://oldsite.com';
		$replace = 'https://newsite.com';

		// Create double-serialized data (serialized string containing serialized data)
		$inner = serialize( array( 'url' => 'http://oldsite.com' ) );
		$outer = serialize( $inner );

		$result = iwp_serialized_search_replace( $search, $replace, $outer );

		// Unserialize the outer layer
		$unserialized_outer = @unserialize( $result );
		$this->assertTrue( is_string( $unserialized_outer ), 'Outer layer unserializes to string' );

		// Unserialize the inner layer
		$unserialized_inner = @unserialize( $unserialized_outer );
		$this->assertTrue( is_array( $unserialized_inner ), 'Inner layer unserializes to array' );
		$this->assertEquals(
			'https://newsite.com',
			$unserialized_inner['url'],
			'Double-serialized URL was replaced correctly'
		);
	}

	/**
	 * Test URL length change (the main corruption issue).
	 */
	public function test_url_length_change_corruption() {
		echo "\n--- Testing URL Length Change (Corruption Prevention) ---\n";

		// Test with different length URLs
		$test_cases = array(
			array(
				'search'  => 'http://short.io',
				'replace' => 'https://verylongdomain.example.com',
				'name'    => 'Short to long URL',
			),
			array(
				'search'  => 'https://verylongdomain.example.com',
				'replace' => 'http://short.io',
				'name'    => 'Long to short URL',
			),
			array(
				'search'  => 'http://example.com',
				'replace' => 'https://example.com',
				'name'    => 'HTTP to HTTPS (same domain)',
			),
		);

		foreach ( $test_cases as $test ) {
			$original = serialize( array(
				'url'      => $test['search'],
				'content'  => "Visit {$test['search']} today!",
				'metadata' => array(
					'featured_image' => "{$test['search']}/image.jpg",
				),
			) );

			$result = iwp_serialized_search_replace( $test['search'], $test['replace'], $original );

			// The key test: can we unserialize without corruption?
			$unserialized = @unserialize( $result );
			$this->assertTrue(
				$unserialized !== false,
				"{$test['name']}: Result unserializes without corruption"
			);

			if ( $unserialized !== false ) {
				$this->assertEquals(
					$test['replace'],
					$unserialized['url'],
					"{$test['name']}: URL replaced correctly"
				);
			}
		}
	}

	/**
	 * Test with WordPress-like option data.
	 */
	public function test_wordpress_option_data() {
		echo "\n--- Testing WordPress-like Option Data ---\n";

		$search  = 'http://oldsite.com';
		$replace = 'https://newsite.com';

		// Simulate WordPress widget options
		$widget_data = array(
			'sidebars_widgets' => array(
				'wp_inactive_widgets' => array(),
				'sidebar-1'           => array( 'text-2', 'custom_html-3' ),
			),
			'widget_text'      => array(
				2 => array(
					'title'  => 'About Us',
					'text'   => '<a href="http://oldsite.com/about">Learn more at http://oldsite.com</a>',
					'filter' => true,
				),
			),
		);
		$serialized = serialize( $widget_data );

		$result = iwp_serialized_search_replace( $search, $replace, $serialized );
		$unserialized = @unserialize( $result );

		$this->assertTrue( is_array( $unserialized ), 'Widget data unserializes correctly' );
		$this->assertTrue(
			strpos( $unserialized['widget_text'][2]['text'], 'https://newsite.com' ) !== false,
			'Widget HTML content URL replaced'
		);
		$this->assertTrue(
			strpos( $unserialized['widget_text'][2]['text'], 'http://oldsite.com' ) === false,
			'Old URL no longer present in widget content'
		);
	}

	/**
	 * Test with special characters in serialized data.
	 */
	public function test_special_characters() {
		echo "\n--- Testing Special Characters ---\n";

		$search  = 'http://oldsite.com';
		$replace = 'https://newsite.com';

		$data = array(
			'utf8_content'  => 'Visit http://oldsite.com — it\'s great! ™ © ®',
			'html_entities' => '<a href="http://oldsite.com">Link &amp; more</a>',
			'newlines'      => "Line 1\nhttp://oldsite.com\nLine 3",
		);
		$serialized = serialize( $data );

		$result = iwp_serialized_search_replace( $search, $replace, $serialized );
		$unserialized = @unserialize( $result );

		$this->assertTrue( is_array( $unserialized ), 'Data with special characters unserializes' );
		$this->assertEquals(
			'Visit https://newsite.com — it\'s great! ™ © ®',
			$unserialized['utf8_content'],
			'UTF-8 content preserved'
		);
		$this->assertEquals(
			'<a href="https://newsite.com">Link &amp; more</a>',
			$unserialized['html_entities'],
			'HTML entities preserved'
		);
		$this->assertEquals(
			"Line 1\nhttps://newsite.com\nLine 3",
			$unserialized['newlines'],
			'Newlines preserved'
		);
	}

	/**
	 * Test empty and null values.
	 */
	public function test_empty_and_null_values() {
		echo "\n--- Testing Empty and Null Values ---\n";

		$search  = 'http://oldsite.com';
		$replace = 'https://newsite.com';

		// Test null
		$result = iwp_serialized_search_replace( $search, $replace, null );
		$this->assertEquals( null, $result, 'Null returns null' );

		// Test empty string
		$result = iwp_serialized_search_replace( $search, $replace, '' );
		$this->assertEquals( '', $result, 'Empty string returns empty string' );

		// Test serialized empty array
		$result = iwp_serialized_search_replace( $search, $replace, serialize( array() ) );
		$this->assertEquals( serialize( array() ), $result, 'Serialized empty array unchanged' );

		// Test serialized false
		$result = iwp_serialized_search_replace( $search, $replace, 'b:0;' );
		$this->assertEquals( 'b:0;', $result, 'Serialized false handled correctly' );
	}

	/**
	 * Test iwp_serialized_search_replace_array for multiple replacements.
	 */
	public function test_multiple_replacements() {
		echo "\n--- Testing Multiple Replacements ---\n";

		$replacements = array(
			'http://oldsite.com'  => 'https://newsite.com',
			'/var/www/oldsite'    => '/home/user/newsite',
			'oldsite@example.com' => 'newsite@example.com',
		);

		$data = array(
			'url'   => 'http://oldsite.com',
			'path'  => '/var/www/oldsite/wp-content',
			'email' => 'oldsite@example.com',
		);
		$serialized = serialize( $data );

		$result = iwp_serialized_search_replace_array( $replacements, $serialized );
		$unserialized = @unserialize( $result );

		$this->assertTrue( is_array( $unserialized ), 'Multiple replacements result is valid' );
		$this->assertEquals( 'https://newsite.com', $unserialized['url'], 'URL replaced' );
		$this->assertEquals( '/home/user/newsite/wp-content', $unserialized['path'], 'Path replaced' );
		$this->assertEquals( 'newsite@example.com', $unserialized['email'], 'Email replaced' );
	}

	/**
	 * Test with numeric and boolean values in arrays.
	 */
	public function test_mixed_types() {
		echo "\n--- Testing Mixed Types ---\n";

		$search  = 'http://oldsite.com';
		$replace = 'https://newsite.com';

		$data = array(
			'url'     => 'http://oldsite.com',
			'count'   => 42,
			'price'   => 19.99,
			'active'  => true,
			'deleted' => false,
			'nothing' => null,
		);
		$serialized = serialize( $data );

		$result = iwp_serialized_search_replace( $search, $replace, $serialized );
		$unserialized = @unserialize( $result );

		$this->assertTrue( is_array( $unserialized ), 'Mixed types result is valid' );
		$this->assertEquals( 'https://newsite.com', $unserialized['url'], 'URL replaced' );
		$this->assertEquals( 42, $unserialized['count'], 'Integer preserved' );
		$this->assertEquals( 19.99, $unserialized['price'], 'Float preserved' );
		$this->assertEquals( true, $unserialized['active'], 'Boolean true preserved' );
		$this->assertEquals( false, $unserialized['deleted'], 'Boolean false preserved' );
		$this->assertEquals( null, $unserialized['nothing'], 'Null preserved' );
	}

	/**
	 * Test deeply nested structures.
	 */
	public function test_deeply_nested_structures() {
		echo "\n--- Testing Deeply Nested Structures ---\n";

		$search  = 'http://oldsite.com';
		$replace = 'https://newsite.com';

		$data = array(
			'level1' => array(
				'level2' => array(
					'level3' => array(
						'level4' => array(
							'level5' => array(
								'url' => 'http://oldsite.com/deep',
							),
						),
					),
				),
			),
		);
		$serialized = serialize( $data );

		$result = iwp_serialized_search_replace( $search, $replace, $serialized );
		$unserialized = @unserialize( $result );

		$this->assertTrue( is_array( $unserialized ), 'Deeply nested result is valid' );
		$this->assertEquals(
			'https://newsite.com/deep',
			$unserialized['level1']['level2']['level3']['level4']['level5']['url'],
			'Deeply nested URL replaced'
		);
	}

	/**
	 * Test that standard string replacement would corrupt data (demonstration).
	 */
	public function test_corruption_demonstration() {
		echo "\n--- Demonstrating Why Standard str_replace Fails ---\n";

		$search  = 'http://old.io';
		$replace = 'https://newdomain.example.com';

		$data = serialize( 'http://old.io' );

		// What standard str_replace would produce (CORRUPTED)
		$corrupted = str_replace( $search, $replace, $data );

		// Our safe function
		$safe = iwp_serialized_search_replace( $search, $replace, $data );

		// Try to unserialize both
		$corrupted_unserialized = @unserialize( $corrupted );
		$safe_unserialized      = @unserialize( $safe );

		$this->assertEquals( false, $corrupted_unserialized, 'Standard str_replace corrupts serialized data' );
		$this->assertEquals(
			'https://newdomain.example.com',
			$safe_unserialized,
			'Safe function preserves data integrity'
		);

		// Show the difference
		echo "  Original serialized:  {$data}\n";
		echo "  Corrupted (str_replace): {$corrupted}\n";
		echo "  Safe (iwp function):     {$safe}\n";
	}

	/**
	 * Run all tests.
	 */
	public function run() {
		echo "===========================================\n";
		echo "Running Serialized Search-Replace Tests\n";
		echo "===========================================\n";

		$this->test_simple_string_replacement();
		$this->test_serialized_string_replacement();
		$this->test_serialized_array_replacement();
		$this->test_serialized_object_replacement();
		$this->test_double_serialized_data();
		$this->test_url_length_change_corruption();
		$this->test_wordpress_option_data();
		$this->test_special_characters();
		$this->test_empty_and_null_values();
		$this->test_multiple_replacements();
		$this->test_mixed_types();
		$this->test_deeply_nested_structures();
		$this->test_corruption_demonstration();

		echo "\n===========================================\n";
		echo "Test Results: ";
		echo "\033[32m{$this->passed} passed\033[0m, ";
		echo "\033[31m{$this->failed} failed\033[0m\n";
		echo "===========================================\n";

		return $this->failed === 0 ? 0 : 1;
	}
}

// Run tests if executed directly
if ( php_sapi_name() === 'cli' && realpath( $argv[0] ) === realpath( __FILE__ ) ) {
	$test = new IWP_Test_Serialized_Search_Replace();
	exit( $test->run() );
}
