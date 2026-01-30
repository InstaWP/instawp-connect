<?php
/**
 * Test suite for iwp-search-replace functions.
 *
 * Usage:
 *   php iwp-search-replace/test.php
 *
 * Optionally place SQL dump files in the iwp-search-replace/ directory:
 *   - db.sql, db (1).sql, db (2).sql, or any *.sql file
 *   - The script will auto-detect domains and run search-replace tests on them
 *
 * @package InstaWP
 */

require_once __DIR__ . DIRECTORY_SEPARATOR . 'functions.php';

$pass = 0;
$fail = 0;

/**
 * Assert that a condition is true.
 *
 * @param bool   $condition The condition to check.
 * @param string $label     Test description.
 */
function assert_true( $condition, $label ) {
	global $pass, $fail;
	if ( $condition ) {
		echo "[PASS] $label\n";
		$pass++;
	} else {
		echo "[FAIL] $label\n";
		$fail++;
	}
}

/**
 * Assert that two values are strictly equal.
 *
 * @param mixed  $expected Expected value.
 * @param mixed  $actual   Actual value.
 * @param string $label    Test description.
 */
function assert_equals( $expected, $actual, $label ) {
	global $pass, $fail;
	if ( $expected === $actual ) {
		echo "[PASS] $label\n";
		$pass++;
	} else {
		echo "[FAIL] $label\n";
		echo "  Expected: " . var_export( $expected, true ) . "\n";
		echo "  Actual:   " . var_export( $actual, true ) . "\n";
		$fail++;
	}
}

/**
 * Validates serialized string lengths in output content.
 * Returns count of broken s:N:"..." patterns where declared length does not match.
 *
 * @param string $content The content to validate.
 * @param string $label   Test label for debug output.
 *
 * @return int Number of broken serialized patterns.
 */
function validate_serialized_lengths( $content, $label ) {
	$broken = 0;

	if ( ! preg_match_all( '/s:(\d+):"/', $content, $matches, PREG_OFFSET_CAPTURE ) ) {
		return 0;
	}

	foreach ( $matches[0] as $i => $match ) {
		$match_pos       = $match[1];
		$match_str       = $match[0];
		$match_len       = strlen( $match_str );
		$declared_length = (int) $matches[1][ $i ][0];
		$content_start   = $match_pos + $match_len;

		if ( $content_start + $declared_length > strlen( $content ) ) {
			continue;
		}

		$end_char = $content[ $content_start + $declared_length ] ?? '';
		if ( '"' !== $end_char ) {
			$broken++;
			if ( $broken <= 5 ) {
				$context = substr( $content, $match_pos, min( 120, $declared_length + $match_len + 5 ) );
				echo "  [$label] Broken at offset $match_pos: " . substr( $context, 0, 100 ) . "...\n";
			}
		}
	}

	return $broken;
}

echo "========================================\n";
echo " iwp-search-replace Test Suite\n";
echo "========================================\n";

// ==================================================================
// UNIT TESTS: iwp_serialized_str_replace()
// ==================================================================
echo "\n--- Unit Tests: iwp_serialized_str_replace() ---\n";

// 1. Plain string — no serialized data
$result = iwp_serialized_str_replace( 'old.com', 'new.com', 'Visit old.com today' );
assert_equals( 'Visit new.com today', $result, 'Plain string replacement' );

// 2. Serialized string — same length replacement
$result = iwp_serialized_str_replace( 'old.com', 'new.com', 's:7:"old.com";' );
assert_equals( 's:7:"new.com";', $result, 'Serialized: same length replacement' );

// 3. Serialized string — shorter to longer replacement
$result = iwp_serialized_str_replace( 'old.com', 'new-domain.com', 's:7:"old.com";' );
assert_equals( 's:14:"new-domain.com";', $result, 'Serialized: length increase (7 -> 14)' );

// 4. Serialized string — longer to shorter replacement
$result = iwp_serialized_str_replace( 'https://old-domain.com', 'https://x.co', 's:22:"https://old-domain.com";' );
assert_equals( 's:12:"https://x.co";', $result, 'Serialized: length decrease (22 -> 12)' );

// 5. Serialized string with surrounding content
$data   = 'a:1:{s:3:"url";s:22:"https://old-domain.com";}';
$result = iwp_serialized_str_replace( 'https://old-domain.com', 'https://new-domain.example.com', $data );
assert_equals( 'a:1:{s:3:"url";s:30:"https://new-domain.example.com";}', $result, 'Serialized inside PHP array notation' );

// 6. Multiple serialized strings in one line
$data   = 's:7:"old.com";s:11:"old.com/foo";';
$result = iwp_serialized_str_replace( 'old.com', 'new-domain.com', $data );
assert_equals( 's:14:"new-domain.com";s:18:"new-domain.com/foo";', $result, 'Multiple serialized strings in one line' );

// 7. Multiple search-replace pairs
// "https://old.com/path/to/stuff" = 29 chars -> "https://new.com/new-path/stuff" = 30 chars
$data   = 's:29:"https://old.com/path/to/stuff";';
$result = iwp_serialized_str_replace(
	array( 'https://old.com', '/path/to' ),
	array( 'https://new.com', '/new-path' ),
	$data
);
assert_equals( 's:30:"https://new.com/new-path/stuff";', $result, 'Multiple search-replace pairs' );

// 8. Empty data
$result = iwp_serialized_str_replace( 'old', 'new', '' );
assert_equals( '', $result, 'Empty data returns empty' );

// 9. No matches — data passes through unchanged
$result = iwp_serialized_str_replace( 'nonexistent', 'replacement', 's:5:"hello";' );
assert_equals( 's:5:"hello";', $result, 'No matches: data unchanged' );

// 10. Mixed serialized and non-serialized content
$data   = 'prefix https://old.com s:19:"https://old.com/foo"; suffix https://old.com';
$result = iwp_serialized_str_replace( 'https://old.com', 'https://new.com', $data );
assert_equals( 'prefix https://new.com s:19:"https://new.com/foo"; suffix https://new.com', $result, 'Mixed serialized and plain content' );

// 11. Deeply nested serialized data (WordPress widget format)
$data   = 'a:2:{s:5:"title";s:7:"Welcome";s:4:"link";s:24:"https://old-site.com/page";}';
$result = iwp_serialized_str_replace( 'https://old-site.com', 'https://new-site.example.org', $data );
assert_equals( 'a:2:{s:5:"title";s:7:"Welcome";s:4:"link";s:32:"https://new-site.example.org/page";}', $result, 'Nested serialized WordPress data' );

// 12. Serialized string containing JSON
$json_inner = '{"url":"https://old.com/api","name":"test"}';
$len        = strlen( $json_inner ); // 43
$data       = 's:' . $len . ':"' . $json_inner . '";';
$result     = iwp_serialized_str_replace( 'https://old.com', 'https://new.com', $data );
$new_json   = '{"url":"https://new.com/api","name":"test"}';
$new_len    = strlen( $new_json ); // 43
assert_equals( 's:' . $new_len . ':"' . $new_json . '";', $result, 'Serialized string containing JSON' );

// ==================================================================
// UNIT TESTS: iwp_read_next_sql_statement()
// ==================================================================
echo "\n--- Unit Tests: iwp_read_next_sql_statement() ---\n";

// 13. Simple statements
$sql    = "CREATE TABLE t1 (id INT);INSERT INTO t1 VALUES (1);";
$tmpf   = tempnam( sys_get_temp_dir(), 'iwp' );
file_put_contents( $tmpf, $sql );
$handle = fopen( $tmpf, 'rb' );
$state  = array( 'in_string' => false, 'quote_char' => null, 'prev_char' => '' );

$stmt1 = iwp_read_next_sql_statement( $handle, $state );
assert_equals( 'CREATE TABLE t1 (id INT);', $stmt1, 'SQL parser: first statement' );

$stmt2 = iwp_read_next_sql_statement( $handle, $state );
assert_equals( 'INSERT INTO t1 VALUES (1);', $stmt2, 'SQL parser: second statement' );

$stmt3 = iwp_read_next_sql_statement( $handle, $state );
assert_equals( false, $stmt3, 'SQL parser: EOF returns false' );

fclose( $handle );
unlink( $tmpf );

// 14. Semicolons inside quoted strings should NOT split statements
$sql    = "INSERT INTO t1 VALUES ('hello; world');";
$tmpf   = tempnam( sys_get_temp_dir(), 'iwp' );
file_put_contents( $tmpf, $sql );
$handle = fopen( $tmpf, 'rb' );
$state  = array( 'in_string' => false, 'quote_char' => null, 'prev_char' => '' );

$stmt = iwp_read_next_sql_statement( $handle, $state );
assert_equals( "INSERT INTO t1 VALUES ('hello; world');", $stmt, 'SQL parser: semicolons inside quotes preserved' );

fclose( $handle );
unlink( $tmpf );

// 15. Escaped quotes inside strings
$sql    = "INSERT INTO t1 VALUES ('it\\'s a test; really');";
$tmpf   = tempnam( sys_get_temp_dir(), 'iwp' );
file_put_contents( $tmpf, $sql );
$handle = fopen( $tmpf, 'rb' );
$state  = array( 'in_string' => false, 'quote_char' => null, 'prev_char' => '' );

$stmt = iwp_read_next_sql_statement( $handle, $state );
assert_equals( "INSERT INTO t1 VALUES ('it\\'s a test; really');", $stmt, 'SQL parser: escaped quotes handled' );

fclose( $handle );
unlink( $tmpf );

// 16. MySQL-style doubled quotes
$sql    = "INSERT INTO t1 VALUES ('it''s here; done');";
$tmpf   = tempnam( sys_get_temp_dir(), 'iwp' );
file_put_contents( $tmpf, $sql );
$handle = fopen( $tmpf, 'rb' );
$state  = array( 'in_string' => false, 'quote_char' => null, 'prev_char' => '' );

$stmt = iwp_read_next_sql_statement( $handle, $state );
assert_equals( "INSERT INTO t1 VALUES ('it''s here; done');", $stmt, 'SQL parser: MySQL doubled quotes handled' );

fclose( $handle );
unlink( $tmpf );

// ==================================================================
// UNIT TESTS: iwp_search_replace_in_sql_file()
// ==================================================================
echo "\n--- Unit Tests: iwp_search_replace_in_sql_file() ---\n";

// 17. Basic SQL file replacement
$test_sql = <<<'SQL'
CREATE TABLE `wp_options` (
  `option_id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `option_name` varchar(191) NOT NULL DEFAULT '',
  `option_value` longtext NOT NULL,
  PRIMARY KEY (`option_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO `wp_options` VALUES (1,'siteurl','https://old-site.com','yes');
INSERT INTO `wp_options` VALUES (2,'home','https://old-site.com','yes');
INSERT INTO `wp_options` VALUES (3,'widget_data','a:1:{i:0;a:1:{s:3:"url";s:24:"https://old-site.com/foo";}}','yes');
INSERT INTO `wp_options` VALUES (4,'plain_option','No URLs here; just text with semicolons;','yes');
SQL;

$input_file  = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'iwp_test_input_' . uniqid() . '.sql';
$output_file = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'iwp_test_output_' . uniqid() . '.sql';
file_put_contents( $input_file, $test_sql );

$replacements = array( 'https://old-site.com' => 'https://new-site.example.org' );
$result       = iwp_search_replace_in_sql_file( $input_file, $output_file, $replacements );

assert_true( $result['success'], 'SQL file: succeeds' );
assert_true( $result['statements'] > 0, 'SQL file: statements counted (' . $result['statements'] . ')' );
assert_true( $result['replacements'] > 0, 'SQL file: replacements made (' . $result['replacements'] . ')' );

$output = file_get_contents( $output_file );
assert_true( strpos( $output, 'https://new-site.example.org' ) !== false, 'SQL file: new URL present' );
assert_true( strpos( $output, 'https://old-site.com' ) === false, 'SQL file: old URL removed' );
// "https://new-site.example.org/foo" = 32 chars
assert_true( strpos( $output, 's:32:"https://new-site.example.org/foo"' ) !== false, 'SQL file: serialized length updated (24 -> 32)' );
assert_true( strpos( $output, 'CREATE TABLE' ) !== false, 'SQL file: DDL preserved' );
assert_true( strpos( $output, 'No URLs here; just text with semicolons;' ) !== false, 'SQL file: semicolons in strings preserved' );

$broken = validate_serialized_lengths( $output, 'SQL file' );
assert_true( 0 === $broken, 'SQL file: all serialized lengths valid' );

@unlink( $input_file );
@unlink( $output_file );

// ==================================================================
// UNIT TESTS: iwp_search_replace_in_sql_file_inplace()
// ==================================================================
echo "\n--- Unit Tests: iwp_search_replace_in_sql_file_inplace() ---\n";

// 18. In-place replacement
$inplace_file = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'iwp_test_inplace_' . uniqid() . '.sql';
file_put_contents( $inplace_file, $test_sql );

$result = iwp_search_replace_in_sql_file_inplace( $inplace_file, $replacements );
assert_true( $result['success'], 'In-place: succeeds' );

$content = file_get_contents( $inplace_file );
assert_true( strpos( $content, 'https://new-site.example.org' ) !== false, 'In-place: new URL present' );
assert_true( strpos( $content, 'https://old-site.com' ) === false, 'In-place: old URL removed' );

$broken = validate_serialized_lengths( $content, 'In-place' );
assert_true( 0 === $broken, 'In-place: all serialized lengths valid' );

@unlink( $inplace_file );

// ==================================================================
// ERROR HANDLING TESTS
// ==================================================================
echo "\n--- Error Handling Tests ---\n";

$dummy_output = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'iwp_test_dummy_' . uniqid() . '.sql';

// 19. Nonexistent input file
$result = iwp_search_replace_in_sql_file( '/nonexistent/path/file.sql', $dummy_output, array( 'a' => 'b' ) );
assert_equals( false, $result['success'], 'Error: nonexistent input file' );
assert_true( strpos( $result['message'], 'does not exist' ) !== false, 'Error: message mentions file not found' );

// 20. Empty replacements
$tmpf = tempnam( sys_get_temp_dir(), 'iwp' );
file_put_contents( $tmpf, 'SELECT 1;' );
$result = iwp_search_replace_in_sql_file( $tmpf, $dummy_output, array() );
assert_equals( false, $result['success'], 'Error: empty replacements' );
assert_true( strpos( $result['message'], 'No replacements' ) !== false, 'Error: message mentions no replacements' );
@unlink( $tmpf );

// 21. Empty file
$tmpf = tempnam( sys_get_temp_dir(), 'iwp' );
file_put_contents( $tmpf, '' );
$result = iwp_search_replace_in_sql_file( $tmpf, $dummy_output, array( 'a' => 'b' ) );
assert_true( $result['success'], 'Empty file: succeeds' );
assert_equals( 0, $result['statements'], 'Empty file: zero statements' );
assert_equals( 0, $result['replacements'], 'Empty file: zero replacements' );
@unlink( $tmpf );
@unlink( $dummy_output );

// ==================================================================
// REAL SQL DUMP FILE TESTS (auto-detect *.sql files in this directory)
// ==================================================================
$sql_files = glob( __DIR__ . DIRECTORY_SEPARATOR . '*.sql' );

if ( ! empty( $sql_files ) ) {
	echo "\n--- Real SQL Dump File Tests ---\n";
	echo "Found " . count( $sql_files ) . " SQL file(s)\n";

	foreach ( $sql_files as $sql_file ) {
		$basename  = basename( $sql_file );
		$file_size = filesize( $sql_file );

		echo "\n--- $basename (" . number_format( $file_size ) . " bytes) ---\n";

		if ( 0 === $file_size ) {
			$out = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'iwp_test_empty_' . uniqid() . '.sql';
			$r   = iwp_search_replace_in_sql_file( $sql_file, $out, array( 'foo' => 'bar' ) );
			assert_true( $r['success'], "$basename: empty file succeeds" );
			assert_equals( 0, $r['statements'], "$basename: zero statements" );
			@unlink( $out );
			continue;
		}

		// Auto-detect top domains in the file
		$sample = file_get_contents( $sql_file, false, null, 0, min( $file_size, 2 * 1024 * 1024 ) );
		preg_match_all( '/https?:\/\/([a-zA-Z0-9._-]+\.[a-zA-Z]{2,})/', $sample, $url_matches );

		if ( empty( $url_matches[1] ) ) {
			echo "  No URLs found in first 2MB, skipping domain replacement test\n";
			continue;
		}

		// Find top 2 most frequent domains (excluding common ones like wordpress.org)
		$skip_domains  = array( 'wordpress.org', 'downloads.wordpress.org', 'w3.org', 'www.w3.org', 'schema.org', 'www.schema.org', 'gstatic.com', 'fonts.gstatic.com' );
		$domain_counts = array_count_values( $url_matches[1] );
		arsort( $domain_counts );

		$test_domains = array();
		foreach ( $domain_counts as $domain => $count ) {
			if ( in_array( $domain, $skip_domains, true ) ) {
				continue;
			}
			$test_domains[ $domain ] = $count;
			if ( count( $test_domains ) >= 2 ) {
				break;
			}
		}

		if ( empty( $test_domains ) ) {
			echo "  No site-specific domains found, skipping\n";
			continue;
		}

		// Build replacements: domain -> test-replaced-{n}.example.com
		$replacements = array();
		$old_domains  = array();
		$new_domains  = array();
		$n            = 1;

		foreach ( $test_domains as $domain => $count ) {
			$new_domain     = "test-replaced-{$n}.example.com";
			$replacements[ 'https://' . $domain ] = 'https://' . $new_domain;
			$replacements[ 'http://' . $domain ]  = 'https://' . $new_domain;
			$replacements[ $domain ]               = $new_domain;
			$old_domains[]  = $domain;
			$new_domains[]  = $new_domain;
			echo "  Replace: $domain ($count URLs in sample) -> $new_domain\n";
			$n++;
		}

		$output_file = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'iwp_test_dump_' . uniqid() . '.sql';

		$start  = microtime( true );
		$result = iwp_search_replace_in_sql_file( $sql_file, $output_file, $replacements );
		$elapsed = round( microtime( true ) - $start, 3 );

		assert_true( $result['success'], "$basename: search-replace succeeds" );
		echo "  Statements: {$result['statements']}, Replacements: {$result['replacements']}, Time: {$elapsed}s\n";
		assert_true( $result['statements'] > 0, "$basename: statements > 0" );
		assert_true( $result['replacements'] > 0, "$basename: replacements > 0" );

		assert_true( file_exists( $output_file ), "$basename: output file created" );
		$output_size = filesize( $output_file );
		assert_true( $output_size > 0, "$basename: output has content (" . number_format( $output_size ) . " bytes)" );

		$output_content = file_get_contents( $output_file );

		// Verify old domains are gone
		foreach ( $old_domains as $old ) {
			$remaining = substr_count( $output_content, $old );
			assert_true( 0 === $remaining, "$basename: '$old' fully replaced ($remaining remaining)" );
		}

		// Verify new domains are present
		foreach ( $new_domains as $new ) {
			$count = substr_count( $output_content, $new );
			assert_true( $count > 0, "$basename: '$new' present ($count occurrences)" );
		}

		// Validate serialized data integrity
		$broken = validate_serialized_lengths( $output_content, $basename );
		assert_true( 0 === $broken, "$basename: all serialized lengths valid ($broken broken)" );

		// Verify SQL structure survived
		assert_true(
			strpos( $output_content, 'CREATE TABLE' ) !== false || strpos( $output_content, 'INSERT' ) !== false,
			"$basename: SQL structure preserved"
		);

		// In-place test
		$inplace_file = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'iwp_test_inplace_' . uniqid() . '.sql';
		copy( $sql_file, $inplace_file );
		$ip_result = iwp_search_replace_in_sql_file_inplace( $inplace_file, $replacements );
		assert_true( $ip_result['success'], "$basename: in-place succeeds" );

		$inplace_content = file_get_contents( $inplace_file );
		assert_true( $output_content === $inplace_content, "$basename: in-place matches regular output" );

		@unlink( $output_file );
		@unlink( $inplace_file );
	}
}

// ==================================================================
// SUMMARY
// ==================================================================
echo "\n========================================\n";
echo " Results: $pass passed, $fail failed\n";
echo "========================================\n";

exit( $fail > 0 ? 1 : 0 );
