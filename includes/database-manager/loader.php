<?php

if ( ! defined( 'INSTAWP_PLUGIN_DIR' ) ) {
	die;
}

if ( ! function_exists( 'adminer_object' ) ) {
	function adminer_object() {
        class InstaWP_Adminer extends \Adminer\Adminer {
            function name() {
                return 'InstaWP Database Manager';
            }

            function credentials() {
                return array( DB_HOST, DB_USER, DB_PASSWORD );
            }

            function database() {
                return DB_NAME;
            }
        
            function login( $login, $password ) {
                return ( $login === DB_USER && $password === DB_PASSWORD );
            }

            function head( $dark = null ) {
                wp_site_icon(); ?>
                <style>
                    #version,
                    #dbs,
                    .version,
                    p.logout {
                        display: none;
                    }
                    /* #menu {
                        top: 0;
                    }
                    #content {
                        margin-top: 0;
                        padding: 10px 10px 10px 0 !important;
                    } */
                    #menu h1 {
                        padding: .8em 1em !important;
                        background-image: none;
                    }
                    #breadcrumb > a:nth-child(1) {
                        pointer-events: none;
                        color: inherit;
                    }
                    #breadcrumb > a:nth-child(2) {
                        pointer-events: none;
                        color: inherit;
                    }
                    .footer > div > fieldset > div > p {
                        color: transparent;
                        display: inline-block;
                        margin: 0;
                        pointer-events: none;
                    }
                    .footer > div > fieldset > div > p > *:not( [name="copy"] ) {
                        display: none;
                    }
                    .footer > div > fieldset > div > p > [name="copy"] {
                        pointer-events: all;
                    }
                </style>
                <?php
                return true;
            }
        }

        return new InstaWP_Adminer();
	}
}

$file_name = sanitize_file_name( get_query_var( 'instawp-database-manager' ) );
if ( ! empty( $file_name ) ) {
    $file_path = \InstaWP\Connect\Helpers\DatabaseManager::get_file_path( $file_name );

    if ( file_exists( $file_path ) ) {
        include $file_path;
    }
}