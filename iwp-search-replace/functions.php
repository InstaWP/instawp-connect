<?php
/**
 * Standalone search-replace utility functions for SQL dump files.
 *
 * These functions handle serialization-aware string replacement in SQL files.
 * No WordPress dependencies — can be used standalone or within WordPress context.
 *
 * @package InstaWP
 */

if ( ! function_exists( 'iwp_serialized_str_replace' ) ) {
	/**
	 * Performs search-replace on a string while preserving PHP serialized data integrity.
	 * Recalculates string lengths in serialized patterns (s:N:"...") after replacement.
	 *
	 * @param array|string $search  Search string(s).
	 * @param array|string $replace Replace string(s).
	 * @param string       $data    The data to process.
	 *
	 * @return string The processed string with correct serialized lengths.
	 */
	function iwp_serialized_str_replace( $search, $replace, $data ) {
		$search  = (array) $search;
		$replace = (array) $replace;

		// Early exit: empty data
		if ( empty( $data ) ) {
			return $data;
		}

		// Early exit: no serialized string patterns, use fast str_replace
		if ( strpos( $data, 's:' ) === false ) {
			return str_replace( $search, $replace, $data );
		}

		$data_len = strlen( $data );

		// Find all serialized patterns: both s:N:" (standard) and s:N:\" (SQL-escaped)
		if ( ! preg_match_all( '/s:(\d+):(\\\\)?"/', $data, $matches, PREG_OFFSET_CAPTURE ) ) {
			return str_replace( $search, $replace, $data );
		}

		// Use array collection for better memory efficiency
		$parts = array();
		$pos   = 0;

		foreach ( $matches[0] as $i => $match ) {
			$match_pos       = $match[1];
			$match_str       = $match[0];
			$match_len       = strlen( $match_str );
			$declared_length = (int) $matches[1][ $i ][0];
			$is_escaped      = ! empty( $matches[2][ $i ][0] );
			$content_start   = $match_pos + $match_len;

			// Skip matches inside already-processed content (nested serialized data)
			if ( $match_pos < $pos ) {
				continue;
			}

			if ( $is_escaped ) {
				// SQL-escaped variant: s:N:\"...\"
				// Scan forward counting unescaped bytes to find content end.
				// Declared length refers to actual PHP string bytes, but dump content
				// may be longer due to SQL escape sequences (\0, \\, etc.).
				$scan_pos        = $content_start;
				$unescaped_bytes = 0;

				while ( $unescaped_bytes < $declared_length && $scan_pos < $data_len ) {
					if ( '\\' === $data[ $scan_pos ] && ( $scan_pos + 1 ) < $data_len ) {
						$next_char = $data[ $scan_pos + 1 ];
						// MySQL recognized escape sequences: each represents 1 actual byte
						if ( in_array( $next_char, array( '\\', "'", '"', '0', 'n', 'r', 't', 'Z', 'b' ), true ) ) {
							$scan_pos        += 2;
							$unescaped_bytes += 1;
							continue;
						}
					}
					$scan_pos++;
					$unescaped_bytes++;
				}

				// Validate closing \"
				if ( $scan_pos >= $data_len || '\\' !== $data[ $scan_pos ]
					|| ( $scan_pos + 1 ) >= $data_len || '"' !== $data[ $scan_pos + 1 ] ) {
					// Not valid serialized data — treat as plain text
					if ( $match_pos > $pos ) {
						$parts[] = str_replace( $search, $replace, substr( $data, $pos, $match_pos - $pos ) );
					}
					$parts[] = str_replace( $search, $replace, $match_str );
					$pos = $content_start;
					continue;
				}

				// Copy and replace everything BEFORE this serialized string
				if ( $match_pos > $pos ) {
					$parts[] = str_replace( $search, $replace, substr( $data, $pos, $match_pos - $pos ) );
				}

				// Extract dump-level content between the escaped quotes
				$content     = substr( $data, $content_start, $scan_pos - $content_start );
				$new_content = str_replace( $search, $replace, $content );

				// Calculate new declared length using delta approach:
				// URL strings don't contain SQL-escapable characters, so dump-level
				// strlen delta equals actual byte-level delta.
				$new_length = $declared_length + ( strlen( $new_content ) - strlen( $content ) );

				// Append the fixed serialized string with escaped quotes
				$parts[] = 's:' . $new_length . ':\\"' . $new_content . '\\"';

				// Move position past the closing \"
				$pos = $scan_pos + 2;

				// Include the semicolon if present
				if ( $pos < $data_len && ';' === $data[ $pos ] ) {
					$parts[] = ';';
					$pos++;
				}
			} else {
				// Standard variant: s:N:"..."

				// Bounds validation: ensure we don't read past data length
				if ( $content_start + $declared_length > $data_len ) {
					// Malformed data: treat as non-serialized, include up to this point
					if ( $match_pos > $pos ) {
						$parts[] = str_replace( $search, $replace, substr( $data, $pos, $match_pos - $pos ) );
					}
					$parts[] = str_replace( $search, $replace, $match_str );
					$pos = $content_start;
					continue;
				}

				// Validate this is actual serialized data (not false positive like 's:404:"')
				$expected_end_pos = $content_start + $declared_length;
				if ( $expected_end_pos >= $data_len || '"' !== $data[ $expected_end_pos ] ) {
					// Not valid serialized data - treat as plain text
					if ( $match_pos > $pos ) {
						$parts[] = str_replace( $search, $replace, substr( $data, $pos, $match_pos - $pos ) );
					}
					$parts[] = str_replace( $search, $replace, $match_str );
					$pos = $content_start;
					continue;
				}

				// Copy and replace everything BEFORE this serialized string
				if ( $match_pos > $pos ) {
					$parts[] = str_replace( $search, $replace, substr( $data, $pos, $match_pos - $pos ) );
				}

				// Extract content using the declared length
				$content = substr( $data, $content_start, $declared_length );

				// Apply replacement to the content and recalculate length
				$new_content = str_replace( $search, $replace, $content );
				$new_length  = strlen( $new_content );

				// Append the fixed serialized string
				$parts[] = 's:' . $new_length . ':"' . $new_content . '"';

				// Move position past the content and closing quote
				$pos = $expected_end_pos + 1;

				// Include the semicolon if present
				if ( $pos < $data_len && ';' === $data[ $pos ] ) {
					$parts[] = ';';
					$pos++;
				}
			}
		}

		// Add remaining content after last match
		if ( $pos < $data_len ) {
			$parts[] = str_replace( $search, $replace, substr( $data, $pos ) );
		}

		return implode( '', $parts );
	}
}

if ( ! function_exists( 'iwp_read_next_sql_statement' ) ) {
	/**
	 * Reads the next complete SQL statement from a file handle using quote-aware parsing.
	 * Properly handles semicolons inside quoted strings and multi-statement lines.
	 *
	 * @param resource $handle      File handle to read from.
	 * @param array    $parse_state Reference to parser state (tracks quote context across calls).
	 *
	 * @return string|false The next complete statement, or false if EOF reached.
	 */
	function iwp_read_next_sql_statement( $handle, &$parse_state ) {
		$statement  = '';
		$in_string  = $parse_state['in_string'] ?? false;
		$quote_char = $parse_state['quote_char'] ?? null;
		$prev_char  = $parse_state['prev_char'] ?? '';

		while ( ( $char = fgetc( $handle ) ) !== false ) {
			$statement .= $char;

			if ( ! $in_string ) {
				// Not inside a string
				if ( '"' === $char || "'" === $char ) {
					$in_string  = true;
					$quote_char = $char;
				} elseif ( ';' === $char ) {
					// True statement boundary found (not inside quotes)
					$parse_state = array(
						'in_string' => false,
						'quote_char' => null,
						'prev_char' => $char,
					);
					return $statement;
				}
			} else {
				// Inside a quoted string
				if ( '\\' === $prev_char ) {
					// Previous char was backslash, this char is escaped - skip
					$prev_char = '';
					continue;
				}

				if ( '\\' === $char ) {
					// Backslash escape - next char will be escaped
					$prev_char = $char;
					continue;
				}

				if ( $char === $quote_char ) {
					// Potential end of string or MySQL-style '' escape
					$next = fgetc( $handle );
					if ( $next === $quote_char ) {
						// MySQL-style escaped quote ('') - still in string
						$statement .= $next;
					} else {
						// End of quoted string
						$in_string  = false;
						$quote_char = null;
						if ( false !== $next ) {
							// Put back the character we peeked
							fseek( $handle, -1, SEEK_CUR );
						}
					}
				}
			}

			$prev_char = $char;
		}

		// Update state for next call
		$parse_state = array(
			'in_string'  => $in_string,
			'quote_char' => $quote_char,
			'prev_char'  => $prev_char,
		);

		// Return remaining content if any (handles files without trailing semicolon)
		return ! empty( $statement ) ? $statement : false;
	}
}

if ( ! function_exists( 'iwp_search_replace_in_sql_file' ) ) {
	/**
	 * Performs search-replace on a SQL dump file with serialization-safe handling.
	 * Uses quote-aware parsing for proper handling of multi-line statements,
	 * semicolons in strings, and mixed content (plain text, JSON, serialized data).
	 *
	 * @param string $input_file   Path to the input SQL file.
	 * @param string $output_file  Path to the output SQL file.
	 * @param array  $replacements Associative array of search => replace pairs.
	 *
	 * @return array Result with status, message, and stats.
	 */
	function iwp_search_replace_in_sql_file( $input_file, $output_file, $replacements ) {
		$result = array(
			'success'      => false,
			'message'      => '',
			'statements'   => 0,
			'replacements' => 0,
		);

		if ( ! file_exists( $input_file ) ) {
			$result['message'] = 'Input file does not exist: ' . $input_file;
			return $result;
		}

		if ( empty( $replacements ) ) {
			$result['message'] = 'No replacements provided';
			return $result;
		}

		$input_handle = fopen( $input_file, 'rb' );
		if ( ! $input_handle ) {
			$result['message'] = 'Cannot open input file: ' . $input_file;
			return $result;
		}

		$output_handle = fopen( $output_file, 'wb' );
		if ( ! $output_handle ) {
			fclose( $input_handle );
			$result['message'] = 'Cannot create output file: ' . $output_file;
			return $result;
		}

		$search_strings  = array_keys( $replacements );
		$replace_strings = array_values( $replacements );
		$statement_count = 0;
		$replace_count   = 0;

		// Build single regex pattern for efficient search check
		$escaped_searches = array_map(
			function ( $s ) {
				return preg_quote( $s, '/' );
			},
			$search_strings
		);
		$search_pattern   = '/' . implode( '|', $escaped_searches ) . '/';

		// Write buffer for I/O efficiency
		$write_buffer      = '';
		$write_buffer_size = 0;
		$max_buffer_size   = 65536; // 64KB buffer

		// Parser state for quote-aware reading
		$parse_state = array(
			'in_string'  => false,
			'quote_char' => null,
			'prev_char'  => '',
		);

		while ( ( $statement = iwp_read_next_sql_statement( $input_handle, $parse_state ) ) !== false ) {
			$statement_count++;
			$original = $statement;

			// Fast pre-check using single regex pattern
			if ( preg_match( $search_pattern, $statement ) ) {
				// Use serialization-aware replacement
				$statement = iwp_serialized_str_replace( $search_strings, $replace_strings, $statement );

				if ( $statement !== $original ) {
					$replace_count++;
				}
			}

			// Buffer writes for I/O efficiency
			$write_buffer      .= $statement;
			$write_buffer_size += strlen( $statement );

			if ( $write_buffer_size >= $max_buffer_size ) {
				fwrite( $output_handle, $write_buffer );
				$write_buffer      = '';
				$write_buffer_size = 0;
			}
		}

		// Flush remaining buffer
		if ( $write_buffer_size > 0 ) {
			fwrite( $output_handle, $write_buffer );
		}

		fclose( $input_handle );
		fclose( $output_handle );

		$result['success']      = true;
		$result['message']      = 'Search-replace completed successfully';
		$result['statements']   = $statement_count;
		$result['replacements'] = $replace_count;

		return $result;
	}
}

if ( ! function_exists( 'iwp_search_replace_in_sql_file_inplace' ) ) {
	/**
	 * Performs search-replace on a SQL dump file in-place.
	 * Creates a temporary file and replaces the original on success.
	 *
	 * @param string $sql_file     Path to the SQL file.
	 * @param array  $replacements Associative array of search => replace pairs.
	 *
	 * @return array Result with status and stats.
	 */
	function iwp_search_replace_in_sql_file_inplace( $sql_file, $replacements ) {
		$temp_file = $sql_file . '.tmp.' . uniqid();

		$result = iwp_search_replace_in_sql_file( $sql_file, $temp_file, $replacements );

		if ( $result['success'] ) {
			// Replace original with processed file
			if ( ! unlink( $sql_file ) ) {
				unlink( $temp_file );
				$result['success'] = false;
				$result['message'] = 'Failed to remove original file';
				return $result;
			}

			if ( ! rename( $temp_file, $sql_file ) ) {
				$result['success'] = false;
				$result['message'] = 'Failed to rename temporary file';
				return $result;
			}
		} else {
			// Clean up temp file on failure
			if ( file_exists( $temp_file ) ) {
				unlink( $temp_file );
			}
		}

		return $result;
	}
}
