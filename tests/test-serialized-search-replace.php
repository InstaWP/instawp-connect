<?php
/**
 * Tests for optimized serialized search-replace functions.
 *
 * Run this file directly with PHP:
 * php tests/test-serialized-search-replace.php
 *
 * @package InstaWP_Connect
 */

// Load the functions file
require_once dirname( __DIR__ ) . '/includes/functions-pull-push.php';

/**
 * Test runner class for serialized search-replace functions.
 */
class IWP_Test_Serialized_Search_Replace {

	private $passed = 0;
	private $failed = 0;
	private $test_results = array();
	private $temp_files = array();

	/**
	 * Assert that two values are equal.
	 */
	private function assertEquals( $expected, $actual, $message ) {
		if ( $expected === $actual ) {
			$this->passed++;
			echo "\033[32m✓ PASS:\033[0m {$message}\n";
		} else {
			$this->failed++;
			echo "\033[31m✗ FAIL:\033[0m {$message}\n";
			echo "  Expected: " . var_export( $expected, true ) . "\n";
			echo "  Actual:   " . var_export( $actual, true ) . "\n";
		}
	}

	/**
	 * Assert that a value is true.
	 */
	private function assertTrue( $actual, $message ) {
		$this->assertEquals( true, $actual, $message );
	}

	/**
	 * Create a temporary file for testing.
	 */
	private function createTempFile( $content ) {
		$temp_file = sys_get_temp_dir() . '/iwp_test_' . uniqid() . '.sql';
		file_put_contents( $temp_file, $content );
		$this->temp_files[] = $temp_file;
		return $temp_file;
	}

	/**
	 * Clean up temporary files.
	 */
	private function cleanup() {
		foreach ( $this->temp_files as $file ) {
			if ( file_exists( $file ) ) {
				unlink( $file );
			}
		}
	}

	/**
	 * Test iwp_fix_serialized_string function.
	 */
	public function test_fix_serialized_string() {
		echo "\n--- Testing iwp_fix_serialized_string ---\n";

		// Test: Corrupted serialized string (wrong length)
		$corrupted = 's:13:"https://newdomain.example.com";';
		$fixed     = iwp_fix_serialized_string( $corrupted );
		$this->assertEquals(
			's:29:"https://newdomain.example.com";',
			$fixed,
			'Fixes corrupted serialized string length'
		);

		// Test: Already correct length (http://example.com = 18 chars)
		$correct = 's:18:"http://example.com";';
		$fixed   = iwp_fix_serialized_string( $correct );
		$this->assertEquals( $correct, $fixed, 'Preserves correct serialized string' );

		// Test: Multiple serialized strings in one line
		// https://newsite.example.com = 27 chars
		$multi = 'a:2:{s:3:"url";s:14:"http://old.com";s:4:"name";s:4:"test";}';
		$replaced = str_replace( 'http://old.com', 'https://newsite.example.com', $multi );
		$fixed = iwp_fix_serialized_string( $replaced );
		$this->assertTrue(
			strpos( $fixed, 's:27:"https://newsite.example.com"' ) !== false,
			'Fixes multiple serialized strings in one line'
		);

		// Test: Nested serialized data
		$nested = 's:47:"a:1:{s:3:"url";s:19:"http://example.com";}";';
		$replaced = str_replace( 'http://example.com', 'https://newsite.com', $nested );
		$fixed = iwp_fix_serialized_string( $replaced );
		// Verify it can be processed
		$this->assertTrue(
			strpos( $fixed, 'https://newsite.com' ) !== false,
			'Handles nested serialized data'
		);
	}

	/**
	 * Test iwp_search_replace_in_string function.
	 */
	public function test_search_replace_in_string() {
		echo "\n--- Testing iwp_search_replace_in_string ---\n";

		// Test: Plain string replacement
		$result = iwp_search_replace_in_string(
			'http://old.com',
			'https://new.com',
			'Visit http://old.com today!'
		);
		$this->assertEquals(
			'Visit https://new.com today!',
			$result,
			'Plain string replacement works'
		);

		// Test: Serialized string with length change
		$serialized = 's:14:"http://old.com";';
		$result = iwp_search_replace_in_string(
			'http://old.com',
			'https://newsite.example.com',
			$serialized
		);
		$this->assertEquals(
			's:27:"https://newsite.example.com";',
			$result,
			'Serialized string length corrected after replacement'
		);

		// Test: Empty data
		$result = iwp_search_replace_in_string( 'search', 'replace', '' );
		$this->assertEquals( '', $result, 'Empty string returns empty' );

		// Test: No match found (fast path)
		$result = iwp_search_replace_in_string( 'notfound', 'replace', 'some data' );
		$this->assertEquals( 'some data', $result, 'No match returns original data' );

		// Test: JSON data (should work without corruption)
		$json = '{"url":"http://old.com","name":"test"}';
		$result = iwp_search_replace_in_string( 'http://old.com', 'https://new.com', $json );
		$this->assertEquals(
			'{"url":"https://new.com","name":"test"}',
			$result,
			'JSON data replaced correctly'
		);
	}

	/**
	 * Test iwp_serialized_search_replace (convenience wrapper).
	 */
	public function test_serialized_search_replace() {
		echo "\n--- Testing iwp_serialized_search_replace ---\n";

		// Test: Basic replacement
		$result = iwp_serialized_search_replace(
			'http://oldsite.com',
			'https://newsite.com',
			's:18:"http://oldsite.com";'
		);
		$this->assertEquals(
			's:19:"https://newsite.com";',
			$result,
			'Basic serialized replacement works'
		);

		// Test: Array in serialized format
		$data = 'a:2:{s:4:"home";s:18:"http://oldsite.com";s:5:"admin";s:24:"http://oldsite.com/admin";}';
		$result = iwp_serialized_search_replace( 'http://oldsite.com', 'https://newsite.com', $data );
		$unserialized = @unserialize( $result );
		$this->assertTrue( is_array( $unserialized ), 'Result is valid serialized array' );
		$this->assertEquals( 'https://newsite.com', $unserialized['home'], 'Array value replaced' );
	}

	/**
	 * Test iwp_serialized_search_replace_array for multiple replacements.
	 */
	public function test_serialized_search_replace_array() {
		echo "\n--- Testing iwp_serialized_search_replace_array ---\n";

		$replacements = array(
			'http://oldsite.com'  => 'https://newsite.com',
			'/var/www/oldsite'    => '/home/user/newsite',
		);

		$data = 'a:2:{s:3:"url";s:18:"http://oldsite.com";s:4:"path";s:19:"/var/www/oldsite/wp";}';
		$result = iwp_serialized_search_replace_array( $replacements, $data );

		$unserialized = @unserialize( $result );
		$this->assertTrue( is_array( $unserialized ), 'Multiple replacements result is valid' );
		$this->assertEquals( 'https://newsite.com', $unserialized['url'], 'URL replaced' );
		$this->assertEquals( '/home/user/newsite/wp', $unserialized['path'], 'Path replaced' );
	}

	/**
	 * Test SQL file processing.
	 */
	public function test_search_replace_in_sql_file() {
		echo "\n--- Testing iwp_search_replace_in_sql_file ---\n";

		// Create test SQL content
		$sql_content = <<<SQL
-- Database dump
INSERT INTO wp_options (option_name, option_value) VALUES ('siteurl', 'http://oldsite.com');
INSERT INTO wp_options (option_name, option_value) VALUES ('home', 'http://oldsite.com');
INSERT INTO wp_options (option_name, option_value) VALUES ('widget_data', 'a:1:{s:3:"url";s:18:"http://oldsite.com";}');
INSERT INTO wp_postmeta (meta_value) VALUES ('s:18:"http://oldsite.com";');
INSERT INTO wp_posts (post_content) VALUES ('Visit http://oldsite.com for more info');
SQL;

		$input_file  = $this->createTempFile( $sql_content );
		$output_file = $input_file . '.out';
		$this->temp_files[] = $output_file;

		$replacements = array(
			'http://oldsite.com' => 'https://newsite.com',
		);

		$result = iwp_search_replace_in_sql_file( $input_file, $output_file, $replacements );

		$this->assertTrue( $result['success'], 'SQL file processing succeeded' );
		$this->assertTrue( $result['lines'] >= 5, 'Processed expected number of lines' );
		$this->assertTrue( $result['replacements'] > 0, 'Made replacements' );

		// Verify output file content
		$output_content = file_get_contents( $output_file );
		$this->assertTrue(
			strpos( $output_content, 'https://newsite.com' ) !== false,
			'Output contains new URL'
		);
		$this->assertTrue(
			strpos( $output_content, 'http://oldsite.com' ) === false,
			'Output does not contain old URL'
		);

		// Verify serialized data is valid
		preg_match( "/VALUES \('widget_data', '([^']+)'\)/", $output_content, $matches );
		if ( ! empty( $matches[1] ) ) {
			$widget_data = @unserialize( $matches[1] );
			$this->assertTrue( is_array( $widget_data ), 'Serialized widget data is valid' );
			$this->assertEquals( 'https://newsite.com', $widget_data['url'], 'Serialized URL replaced' );
		}
	}

	/**
	 * Test in-place file processing.
	 */
	public function test_search_replace_in_sql_file_inplace() {
		echo "\n--- Testing iwp_search_replace_in_sql_file_inplace ---\n";

		$sql_content = "INSERT INTO wp_options VALUES ('siteurl', 's:18:\"http://oldsite.com\";');";
		$sql_file = $this->createTempFile( $sql_content );

		$replacements = array( 'http://oldsite.com' => 'https://newsite.com' );
		$result = iwp_search_replace_in_sql_file_inplace( $sql_file, $replacements );

		$this->assertTrue( $result['success'], 'In-place processing succeeded' );

		$content = file_get_contents( $sql_file );
		$this->assertTrue(
			strpos( $content, 's:19:"https://newsite.com"' ) !== false,
			'In-place replacement with correct length'
		);
	}

	/**
	 * Test error handling.
	 */
	public function test_error_handling() {
		echo "\n--- Testing Error Handling ---\n";

		// Test: Non-existent input file
		$result = iwp_search_replace_in_sql_file(
			'/nonexistent/file.sql',
			'/tmp/output.sql',
			array( 'a' => 'b' )
		);
		$this->assertTrue( ! $result['success'], 'Fails gracefully for non-existent file' );

		// Test: Empty replacements
		$temp_file = $this->createTempFile( 'test content' );
		$result = iwp_search_replace_in_sql_file( $temp_file, $temp_file . '.out', array() );
		$this->temp_files[] = $temp_file . '.out';
		$this->assertTrue( ! $result['success'], 'Fails gracefully for empty replacements' );
	}

	/**
	 * Test URL length changes (the main corruption issue).
	 */
	public function test_url_length_change_corruption_prevention() {
		echo "\n--- Testing URL Length Change (Corruption Prevention) ---\n";

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
				'name'    => 'HTTP to HTTPS',
			),
		);

		foreach ( $test_cases as $test ) {
			$original = serialize( array(
				'url'     => $test['search'],
				'content' => "Visit {$test['search']} today!",
			) );

			$result = iwp_serialized_search_replace( $test['search'], $test['replace'], $original );

			// Key test: can we unserialize without corruption?
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
	 * Test with special characters.
	 */
	public function test_special_characters() {
		echo "\n--- Testing Special Characters ---\n";

		$search  = 'http://oldsite.com';
		$replace = 'https://newsite.com';

		// UTF-8 content
		$data = serialize( array( 'text' => 'Visit http://oldsite.com — café ™' ) );
		$result = iwp_serialized_search_replace( $search, $replace, $data );
		$unserialized = @unserialize( $result );
		$this->assertTrue( is_array( $unserialized ), 'UTF-8 data valid after replacement' );

		// Escaped quotes
		$data = 's:30:"http://oldsite.com/path?a=\"b\"";';
		$result = iwp_serialized_search_replace( $search, $replace, $data );
		$this->assertTrue(
			strpos( $result, 'https://newsite.com' ) !== false,
			'Escaped quotes handled'
		);
	}

	/**
	 * Test that demonstrates why standard str_replace fails.
	 */
	public function test_corruption_demonstration() {
		echo "\n--- Demonstrating Why Standard str_replace Fails ---\n";

		$search  = 'http://old.io';
		$replace = 'https://newdomain.example.com';
		$data    = serialize( 'http://old.io' );

		// What standard str_replace produces (CORRUPTED)
		$corrupted = str_replace( $search, $replace, $data );

		// Our safe function
		$safe = iwp_serialized_search_replace( $search, $replace, $data );

		// Try to unserialize both
		$corrupted_result = @unserialize( $corrupted );
		$safe_result      = @unserialize( $safe );

		$this->assertEquals( false, $corrupted_result, 'Standard str_replace corrupts serialized data' );
		$this->assertEquals( $replace, $safe_result, 'Safe function preserves data integrity' );

		echo "  Original:   {$data}\n";
		echo "  Corrupted:  {$corrupted}\n";
		echo "  Fixed:      {$safe}\n";
	}

	/**
	 * Test performance with larger data.
	 */
	public function test_performance_large_file() {
		echo "\n--- Testing Performance with Large File ---\n";

		// Generate a larger SQL file (1000 lines)
		$lines = array();
		for ( $i = 0; $i < 1000; $i++ ) {
			$serialized = serialize( array(
				'url'   => 'http://oldsite.com/page/' . $i,
				'title' => 'Page ' . $i,
				'meta'  => array( 'key' => 'value' . $i ),
			) );
			$lines[] = "INSERT INTO wp_postmeta VALUES ({$i}, 'data', '{$serialized}');";
		}

		$sql_content = implode( "\n", $lines );
		$input_file  = $this->createTempFile( $sql_content );
		$output_file = $input_file . '.out';
		$this->temp_files[] = $output_file;

		$replacements = array( 'http://oldsite.com' => 'https://newsite.com' );

		$start_time = microtime( true );
		$result = iwp_search_replace_in_sql_file( $input_file, $output_file, $replacements );
		$elapsed = microtime( true ) - $start_time;

		$this->assertTrue( $result['success'], 'Large file processing succeeded' );
		$this->assertEquals( 1000, $result['lines'], 'Processed all lines' );
		$this->assertEquals( 1000, $result['replacements'], 'Made replacements in all lines' );

		echo "  Processed 1000 lines in " . round( $elapsed * 1000, 2 ) . "ms\n";
		echo "  Memory peak: " . round( memory_get_peak_usage( true ) / 1024 / 1024, 2 ) . "MB\n";

		// Verify a random line
		$output_content = file_get_contents( $output_file );
		$this->assertTrue(
			strpos( $output_content, 'http://oldsite.com' ) === false,
			'All old URLs replaced'
		);
	}

	/**
	 * Run all tests.
	 */
	public function run() {
		echo "===========================================\n";
		echo "Running Optimized Serialized Search-Replace Tests\n";
		echo "===========================================\n";

		$this->test_fix_serialized_string();
		$this->test_search_replace_in_string();
		$this->test_serialized_search_replace();
		$this->test_serialized_search_replace_array();
		$this->test_search_replace_in_sql_file();
		$this->test_search_replace_in_sql_file_inplace();
		$this->test_error_handling();
		$this->test_url_length_change_corruption_prevention();
		$this->test_special_characters();
		$this->test_corruption_demonstration();
		$this->test_performance_large_file();

		// Cleanup
		$this->cleanup();

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
