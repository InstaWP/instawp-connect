<?php
namespace InstaWP\Connect\Helpers;

class WPConfig extends \WPConfigTransformer {

    protected $config_data;
    protected $is_cli;
    protected $blacklisted = [
        'DB_NAME',
        'DB_USER',
        'DB_PASSWORD',
        'DB_HOST',
        'DB_CHARSET',
        'DB_COLLATE',
        'AUTH_KEY',
        'SECURE_AUTH_KEY',
        'LOGGED_IN_KEY',
        'NONCE_KEY',
        'AUTH_SALT',
        'SECURE_AUTH_SALT',
        'LOGGED_IN_SALT',
        'NONCE_SALT',
        'ABSPATH',
        'WP_HOME',
        'WP_SITEURL',
        'WP_CACHE_KEY_SALT',
        'COOKIE_DOMAIN',
        'DOMAIN_CURRENT_SITE',
    ];

    // InstaCache (Valkey/Redis) object-cache config is platform-managed and must never be
    // round-tripped through the Config Manager: array-valued constants (WP_REDIS_PASSWORD =
    // [acl_user, acl_pass]; WP_REDIS_SERVERS/CLUSTER/SENTINEL/SHARDS/*_GROUPS) collapse to a
    // mangled string on save, breaking object-cache auth. A prefix match blacklists the whole
    // family (present and future) rather than an enumerated, quickly-stale list.
    protected $blacklisted_prefixes = [
        'WP_REDIS_',
    ];

    protected function is_blacklisted( $constant ) {
        if ( in_array( $constant, $this->blacklisted, true ) ) {
            return true;
        }
        foreach ( $this->blacklisted_prefixes as $prefix ) {
            if ( 0 === strpos( $constant, $prefix ) ) {
                return true;
            }
        }
        return false;
    }

    /**
     * Control structures whose body we treat as a conditional scope.
     *
     * @return array
     */
    protected function scope_keywords() {
        $keywords = [ T_IF, T_ELSEIF, T_ELSE, T_WHILE, T_FOR, T_FOREACH, T_SWITCH, T_DO, T_TRY, T_CATCH, T_FUNCTION ];

        foreach ( [ 'T_FINALLY', 'T_FN', 'T_MATCH' ] as $maybe ) {
            if ( defined( $maybe ) ) {
                $keywords[] = constant( $maybe );
            }
        }

        return $keywords;
    }

    /**
     * Control structures that support PHP's alternative (colon) syntax.
     *
     * Deliberately excludes T_FUNCTION so a return type (`function f(): void`) is not
     * mistaken for the start of a block.
     *
     * @return array
     */
    protected function alternative_syntax_keywords() {
        return [ T_IF, T_ELSEIF, T_ELSE, T_WHILE, T_FOR, T_FOREACH, T_SWITCH ];
    }

    /**
     * Names of constants whose define() does NOT sit at the top level of wp-config.php.
     *
     * WPConfigTransformer parses wp-config.php with a flat regex that has no awareness of
     * enclosing scope, so a define() nested in a block — for example the InstaCache
     * drop-in's
     *
     *     if ( defined( 'WP_CLI' ) && WP_CLI ) {
     *         define( 'WP_REDIS_DISABLED', true );
     *     }
     *
     * — is reported exactly like a top-level one. Surfacing such a constant to the Config
     * Manager is wrong twice over: it advertises a value that does not apply to ordinary
     * requests, and saving it back rewrites code inside a branch the caller never saw.
     *
     * A define() whose every enclosing condition names the constant itself — the ordinary
     * `if ( ! defined( 'FOO' ) ) { define( 'FOO', ... ); }` idempotency guard — is
     * unconditional in effect, so it stays manageable.
     *
     * Deliberate limitations, all of which fail towards today's behaviour rather than
     * towards hiding a constant that is genuinely global:
     *  - Without the tokenizer extension nothing is filtered at all.
     *  - The result is keyed by NAME, so a constant defined both at the top level and
     *    inside a block is left alone entirely. That is intentional: the transformer
     *    rewrites by matching source text and we cannot tell which occurrence it would
     *    edit, so refusing is the only safe answer.
     *  - A brace-less body that immediately opens another statement
     *    (`if ( $a )` newline `if ( ! defined( 'FOO' ) ) { ... }`) is judged on the inner
     *    condition only; tracking the outer one needs statement-level parsing.
     *
     * @param string $src wp-config.php source.
     *
     * @return array Constant name => true.
     */
    protected function scoped_constants( $src ) {
        if ( ! function_exists( 'token_get_all' ) ) {
            return [];
        }

        $tokens = @token_get_all( $src );

        if ( empty( $tokens ) ) {
            return [];
        }

        $scope_keywords = $this->scope_keywords();
        $alt_keywords   = $this->alternative_syntax_keywords();
        $end_keywords   = [ T_ENDIF, T_ENDWHILE, T_ENDFOR, T_ENDFOREACH, T_ENDSWITCH ];

        $trivia = [ T_WHITESPACE, T_COMMENT, T_DOC_COMMENT ];

        $scoped  = [];
        $stack   = [];   // One entry per open block: the header text that introduced it.
        $header  = null; // Header text of the control structure currently being read.
        $keyword = null; // Which keyword started that header.
        $paren   = 0;    // Parenthesis depth, so a ternary ':' is not read as a block opener.
        $expects_colon = false; // We are exactly at the ':' position of alternative syntax.
        $condition_closed = false; // The current header's own condition has been closed.
        $total   = count( $tokens );

        for ( $index = 0; $index < $total; $index++ ) {
            $token = $tokens[ $index ];

            if ( is_array( $token ) ) {
                $id   = $token[0];
                $text = $token[1];

                if ( in_array( $id, $trivia, true ) ) {
                    if ( null !== $header ) {
                        $header .= $text;
                    }
                    continue;
                }

                /* A close tag ends the statement exactly like a semicolon, so a brace-less
                   body such as `if ( $a ) define( 'X', 1 ) ?>` must not leave the header
                   open over the constants that follow it. */
                if ( T_CLOSE_TAG === $id ) {
                    $header  = null;
                    $keyword = null;
                    $expects_colon    = false;
                    $condition_closed = false;
                    continue;
                }

                if ( in_array( $id, $end_keywords, true ) ) {
                    array_pop( $stack );
                    $expects_colon = false;
                    continue;
                }

                // A '{' that opens string interpolation ("{$a}", "${a}") is closed by a plain
                // '}', so it has to be balanced here or every later define() looks nested.
                if ( T_CURLY_OPEN === $id || T_DOLLAR_OPEN_CURLY_BRACES === $id ) {
                    $stack[]       = '';
                    $expects_colon = false;
                    continue;
                }

                // A control keyword only opens a scope at the top of a statement. Inside
                // parentheses it belongs to something else — a closure in a condition — and
                // must not replace the header being read. PHP 7 also allows reserved words as
                // member names, so `function for( $k ): string` still lexes T_FOR: taking that
                // as a `for` header would read its return-type ':' as alternative syntax and
                // push a scope nothing pops.
                if ( in_array( $id, $scope_keywords, true ) && 0 === $paren && ! $this->is_member_name( $tokens, $index ) ) {
                    $header  = $text;
                    $keyword = $id;
                    // `else` takes no condition, so its alternative-syntax ':' comes next.
                    $expects_colon    = ( T_ELSE === $id );
                    $condition_closed = ( T_ELSE === $id );
                    continue;
                }

                if ( $this->is_define_token( $token ) && $this->is_define_call( $tokens, $index ) ) {
                    // Inside a block, or a brace-less body such as `if ( ... ) define( ... );`.
                    if ( ! empty( $stack ) || null !== $header ) {
                        $enclosing = $stack;
                        if ( null !== $header ) {
                            $enclosing[] = $header;
                        }

                        $name = $this->define_name( $tokens, $index );

                        if ( '' !== $name && ! $this->guards_itself( $enclosing, $name ) ) {
                            $scoped[ $name ] = true;
                        }
                    }
                    $expects_colon = false;
                    continue;
                }

                if ( null !== $header ) {
                    $header .= $text;
                }

                $expects_colon = false;
                continue;
            }

            if ( '(' === $token ) {
                $paren++;
                $expects_colon = false;
            } elseif ( ')' === $token ) {
                $paren--;
                // Only the ':' immediately after a control structure's OWN closing ')' opens an
                // alternative-syntax block — hence $condition_closed, which stops a later call
                // in a brace-less body from re-arming it. Without this, the ':' of a ternary
                // (`if ( $a ) $b = $c ? d( $e ) : f();`) pushes a scope that nothing pops, and
                // every constant below it in the file silently disappears.
                if ( 0 === $paren && null !== $header && ! $condition_closed ) {
                    $expects_colon    = true;
                    $condition_closed = true;
                }
            }

            if ( '{' === $token ) {
                // A '{' inside parentheses is a closure or match body sitting in the middle of
                // a condition — `if ( array_filter( $a, function ( $v ) { … } ) ) :`. It opens a
                // scope, but the header it interrupts is still being read, so keep it: nulling
                // it would stop the following ':' from pushing and leave the matching `endif`
                // to pop the enclosing block instead.
                if ( $paren > 0 ) {
                    $stack[] = '';
                    $expects_colon = false;
                    continue;
                }

                $stack[] = ( null === $header ) ? '' : $header;
                $header  = null;
                $keyword = null;
                $expects_colon    = false;
                $condition_closed = false;
                continue;
            }

            if ( '}' === $token ) {
                array_pop( $stack );
                $expects_colon = false;
                continue;
            }

            if ( ':' === $token && $expects_colon && null !== $header && 0 === $paren && in_array( $keyword, $alt_keywords, true ) ) {
                // `elseif:` / `else:` continue the same if-chain that a single `endif` closes,
                // so they replace the open scope rather than nesting inside it.
                if ( T_ELSEIF === $keyword || T_ELSE === $keyword ) {
                    array_pop( $stack );
                }

                $stack[] = $header;
                $header  = null;
                $keyword = null;
                $expects_colon    = false;
                $condition_closed = false;
                continue;
            }

            if ( ';' === $token && 0 === $paren ) {
                // The statement ended without opening a block (a brace-less body, or `do ... while;`).
                $header  = null;
                $keyword = null;
                $expects_colon    = false;
                $condition_closed = false;
                continue;
            }

            if ( null !== $header ) {
                $header .= $token;
            }

            if ( ')' !== $token ) {
                $expects_colon = false;
            }
        }

        return $scoped;
    }

    /**
     * Whether a token names the `define` function, in either the plain (`define`) or
     * fully qualified (`\define`) form. PHP 8 lexes the latter as a single token.
     *
     * @param array|string $token
     *
     * @return bool
     */
    protected function is_define_token( $token ) {
        if ( ! is_array( $token ) ) {
            return false;
        }

        $ids = [ T_STRING ];

        if ( defined( 'T_NAME_FULLY_QUALIFIED' ) ) {
            $ids[] = constant( 'T_NAME_FULLY_QUALIFIED' );
        }

        if ( ! in_array( $token[0], $ids, true ) ) {
            return false;
        }

        return 0 === strcasecmp( ltrim( $token[1], '\\' ), 'define' );
    }

    /**
     * Index of the next token after $index that is not whitespace or a comment.
     *
     * @param array $tokens
     * @param int   $index
     * @param int   $step   1 to scan forwards, -1 to scan backwards.
     *
     * @return int|null
     */
    protected function next_significant( $tokens, $index, $step = 1 ) {
        $trivia = [ T_WHITESPACE, T_COMMENT, T_DOC_COMMENT ];
        $total  = count( $tokens );

        for ( $cursor = $index + $step; $cursor >= 0 && $cursor < $total; $cursor += $step ) {
            $token = $tokens[ $cursor ];

            if ( is_array( $token ) && in_array( $token[0], $trivia, true ) ) {
                continue;
            }

            return $cursor;
        }

        return null;
    }

    /**
     * Whether the token at $index is being used as a member/function NAME rather than as
     * the keyword it lexes to. PHP 7 allows reserved words after `function`, `->`, `?->`
     * and `::`, so `function for( $k )` and `$obj->if()` both reach us as control keywords.
     *
     * @param array $tokens
     * @param int   $index
     *
     * @return bool
     */
    protected function is_member_name( $tokens, $index ) {
        $before = $this->next_significant( $tokens, $index, -1 );

        if ( null === $before || ! is_array( $tokens[ $before ] ) ) {
            return false;
        }

        $names_follow = [ T_FUNCTION, T_OBJECT_OPERATOR, T_DOUBLE_COLON, T_CONST ];

        if ( defined( 'T_NULLSAFE_OBJECT_OPERATOR' ) ) {
            $names_follow[] = constant( 'T_NULLSAFE_OBJECT_OPERATOR' );
        }

        return in_array( $tokens[ $before ][0], $names_follow, true );
    }

    /**
     * Whether the `define` token at $index is a real function call and not a method
     * name (`$obj->define(...)`, `Foo::define(...)`) or a declaration.
     *
     * @param array $tokens
     * @param int   $index
     *
     * @return bool
     */
    protected function is_define_call( $tokens, $index ) {
        $rejected = [ T_OBJECT_OPERATOR, T_DOUBLE_COLON, T_FUNCTION, T_NS_SEPARATOR ];

        if ( defined( 'T_NULLSAFE_OBJECT_OPERATOR' ) ) {
            $rejected[] = constant( 'T_NULLSAFE_OBJECT_OPERATOR' );
        }

        $before = $this->next_significant( $tokens, $index, -1 );

        if ( null !== $before && is_array( $tokens[ $before ] ) && in_array( $tokens[ $before ][0], $rejected, true ) ) {
            return false;
        }

        $after = $this->next_significant( $tokens, $index );

        return null !== $after && '(' === $tokens[ $after ];
    }

    /**
     * The constant name of the define() call whose `define` token sits at $index.
     *
     * @param array $tokens
     * @param int   $index
     *
     * @return string Empty when the name is not a plain string literal.
     */
    protected function define_name( $tokens, $index ) {
        $open = $this->next_significant( $tokens, $index );

        if ( null === $open || '(' !== $tokens[ $open ] ) {
            return '';
        }

        $literal = $this->next_significant( $tokens, $open );

        if ( null === $literal || ! is_array( $tokens[ $literal ] ) || T_CONSTANT_ENCAPSED_STRING !== $tokens[ $literal ][0] ) {
            return '';
        }

        // The literal must BE the whole first argument: `define( 'PRE' . $suffix, 1 )` would
        // otherwise register 'PRE' and hide a genuinely top-level define( 'PRE', ... ).
        $separator = $this->next_significant( $tokens, $literal );

        if ( null === $separator || ',' !== $tokens[ $separator ] ) {
            return '';
        }

        return trim( $tokens[ $literal ][1], "'\"" );
    }

    /**
     * Whether every enclosing condition is a `defined( 'FOO' )` test on the constant
     * itself, i.e. the define() is only wrapped in its own idempotency guard and therefore
     * applies unconditionally.
     *
     * The test is deliberately narrow: merely mentioning the name is not enough, or
     * `if ( getenv( 'WP_DEBUG' ) === 'yes' ) { define( 'WP_DEBUG', true ); }` — a genuinely
     * conditional define — would be waved through as global config. It is not exhaustive
     * either: a self-guard ANDed with another test (`if ( ! defined( 'FS_METHOD' ) && ! WP_CLI )`)
     * still counts as a guard, which under-detects in the same direction as before this change.
     *
     * @param array  $headers
     * @param string $name
     *
     * @return bool
     */
    protected function guards_itself( $headers, $name ) {
        if ( empty( $headers ) ) {
            return true;
        }

        $pattern = '/\bdefined\s*\(\s*[\'"]' . preg_quote( $name, '/' ) . '[\'"]\s*\)/';

        foreach ( $headers as $header ) {
            if ( ! preg_match( $pattern, $header ) ) {
                return false;
            }
        }

        return true;
    }

    public function __construct( array $constants = [], $is_cli = false, $read_only = false ) {
        $file = ABSPATH . 'wp-config.php';
        if ( ! file_exists( $file ) ) {
            if ( @file_exists( dirname( ABSPATH ) . '/wp-config.php' ) ) {
                $file = dirname( ABSPATH ) . '/wp-config.php';
            }
        }

        parent::__construct( $file, $read_only );

        $this->config_data = $constants;
        $this->is_cli      = $is_cli;
    }

    public function get() {
        $wp_config_src = file_get_contents( $this->wp_config_path );

        if ( ! trim( $wp_config_src ) ) {
            throw new \Exception( 'Config file is empty.' );
        }

        $this->wp_config_src = $wp_config_src;
        $this->wp_configs    = $this->parse_wp_config( $this->wp_config_src );

        if ( ! isset( $this->wp_configs['constant'] ) ) {
            throw new \Exception( "Config type constant does not exist." );
        }

        $results = [
            'wp-config' => [],
        ];

        $scoped = $this->is_cli ? [] : $this->scoped_constants( $this->wp_config_src );

        foreach ( $this->wp_configs['constant'] as $constant => $data ) {
            if ( ! $this->is_cli && ( preg_match( '/[a-z]/', $constant ) || $this->is_blacklisted( $constant ) || isset( $scoped[ $constant ] ) ) ) {
                continue;
            }

            if ( ! empty( $this->config_data ) && ! in_array( $constant, $this->config_data, true ) ) {
                continue;
            }

            $value = trim( $data['value'], "'" );
            if ( filter_var( $value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE ) !== null ) {
                $value = filter_var( $value, FILTER_VALIDATE_BOOLEAN );
            } elseif ( filter_var( $value, FILTER_VALIDATE_INT, FILTER_NULL_ON_FAILURE ) !== null ) {
                $value = intval( $value );
            }

            $results['wp-config'][ $constant ] = $value;
        }

        return $results;
    }

    public function set() {
        $args    = [
            'normalize' => true,
            'add'       => true,
        ];
        $content = file_get_contents( $this->wp_config_path );

        if ( ! trim( $content ) ) {
            throw new \Exception( 'Config file is empty.' );
        }

        if ( false === strpos( $content, "/* That's all, stop editing!" ) ) {
            preg_match( '@\$table_prefix = (.*);@', $content, $matches );
            $args['anchor']    = isset( $matches[0] ) ? $matches[0] : '';
            $args['placement'] = 'after';
        }

        $scoped  = $this->is_cli ? [] : $this->scoped_constants( $content );
        $skipped = [];

        foreach ( $this->config_data as $key => $value ) {
            if ( empty( $key ) ) {
                continue;
            }

            if ( ! $this->is_cli && preg_match( '/[a-z]/', $key ) ) {
                // Not a constant name we ever manage — a malformed key, not a protection.
                continue;
            }

            if ( ! $this->is_cli && ( $this->is_blacklisted( $key ) || isset( $scoped[ $key ] ) ) ) {
                // Report what was deliberately not written. The caller posts the whole constant
                // map back, so without this a protected constant looks saved and silently reverts.
                $skipped[] = $key;
                continue;
            }

            if ( is_array( $value ) ) {
                if ( ! array_key_exists( 'value', $value ) ) {
                    continue;
                }

                $params = [ 'separator', 'add' ];
                foreach ( $params as $param ) {
                    if ( array_key_exists( $param, $value ) ) {
                        $args[ $param ] = $value[ $param ];
                    }
                }
                $args['raw'] = array_key_exists( 'raw', $value ) ? $value['raw'] : true;
                $value       = $value['value'];
            } elseif ( is_bool( $value ) ) {
                $value       = $value ? 'true' : 'false';
                $args['raw'] = true;
            } elseif ( is_numeric( $value ) ) {
                $value       = strval( $value );
                $args['raw'] = true;
            } elseif ( in_array( $value, [ 'true', 'false' ] ) ) {
                $value       = strval( $value );
                $args['raw'] = true;
            } else {
                $value       = sanitize_text_field( wp_unslash( $value ) );
                $args['raw'] = false;
            }

            try {
                $this->update( 'constant', $key, $value, $args );
            } catch ( \Exception $e ) {
                throw new \Exception( $e->getMessage() );
            }
        }

        $response = [ 'success' => true ];

        if ( ! empty( $skipped ) ) {
            $response['skipped'] = $skipped;
        }

        return $response;
    }

    public function delete() {
        $constants = array_filter( $this->config_data );

        if ( empty( $constants ) ) {
            throw new \Exception( 'No constants provided!' );
        }

        $scoped = [];

        if ( ! $this->is_cli ) {
            $content = file_get_contents( $this->wp_config_path );
            $scoped  = $this->scoped_constants( $content );
        }

        $skipped = [];

        foreach ( $constants as $constant ) {
            // Same protection get()/set() apply: the Config Manager never removes a
            // blacklisted (platform-managed) constant, nor one that only exists inside a
            // conditional block it was never able to show the caller.
            if ( ! $this->is_cli && ( $this->is_blacklisted( $constant ) || isset( $scoped[ $constant ] ) ) ) {
                $skipped[] = $constant;
                continue;
            }

            try {
                $this->remove( 'constant', $constant );
            } catch ( \Exception $e ) {
                throw new \Exception( $e->getMessage() );
            }
        }

        $response = [ 'success' => true ];

        if ( ! empty( $skipped ) ) {
            $response['skipped'] = $skipped;
        }

        return $response;
    }
}