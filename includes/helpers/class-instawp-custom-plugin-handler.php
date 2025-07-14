<?php

use InstaWP\Connect\Helpers\Helper;

defined( 'ABSPATH' ) || exit;

/**
 * Class InstaWP_Custom_Plugin_Handler
 * 
 * Handles synchronization of custom/uploaded plugins in 2-Way Sync
 * Supports:
 * - Custom plugins uploaded via WordPress admin
 * - Plugin files and folder structure preservation
 * - Plugin activation state preservation
 * - Bi-directional sync for plugin changes
 * 
 * @since 1.0.0
 */
class InstaWP_Custom_Plugin_Handler {

    /**
     * Temporary directory for plugin files
     * 
     * @var string
     */
    private $temp_dir;

    /**
     * Constructor
     */
    public function __construct() {
        $this->temp_dir = WP_CONTENT_DIR . '/uploads/instawp-sync-temp/';
        
        // Add custom plugin support filters
        add_filter( 'instawp/filters/2waysync/plugin_data', array( $this, 'add_custom_plugin_data' ), 10, 3 );
        add_filter( 'instawp/filters/2waysync/can_sync_plugin', array( $this, 'can_sync_custom_plugin' ), 10, 2 );
        add_action( 'instawp/actions/2waysync/process_event_plugin', array( $this, 'process_custom_plugin' ), 10, 2 );
        
        // Handle plugin upload/install hooks
        add_action( 'upgrader_process_complete', array( $this, 'handle_plugin_upload' ), 20, 2 );
        add_action( 'activated_plugin', array( $this, 'handle_plugin_activation' ), 20, 2 );
        add_action( 'deactivated_plugin', array( $this, 'handle_plugin_deactivation' ), 20, 2 );
        add_action( 'deleted_plugin', array( $this, 'handle_plugin_deletion' ), 20, 2 );
    }

    /**
     * Check if a plugin is custom (not from WordPress.org)
     * 
     * @param string $plugin_file
     * @return bool
     */
    public function is_custom_plugin( $plugin_file ) {
        if ( empty( $plugin_file ) ) {
            return false;
        }

        $plugin_slug = dirname( $plugin_file );
        if ( $plugin_slug === '.' ) {
            $plugin_slug = basename( $plugin_file, '.php' );
        }

        // Check if plugin is on WordPress.org
        if ( Helper::is_on_wordpress_org( $plugin_slug, 'plugin' ) ) {
            return false;
        }

        // Check if plugin has custom upload indicators
        $plugin_path = WP_PLUGIN_DIR . '/' . $plugin_file;
        if ( ! file_exists( $plugin_path ) ) {
            return false;
        }

        // Additional checks for custom plugins
        $plugin_data = get_plugin_data( $plugin_path );
        
        // Check for custom plugin indicators
        $custom_indicators = array(
            'Plugin URI' => array( 'github.com', 'gitlab.com', 'bitbucket.org' ),
            'Author URI' => array( 'github.com', 'gitlab.com', 'bitbucket.org' ),
        );
+
+        foreach ( $custom_indicators as $field => $patterns ) {
+            if ( ! empty( $plugin_data[ $field ] ) ) {
+                foreach ( $patterns as $pattern ) {
+                    if ( strpos( strtolower( $plugin_data[ $field ] ), $pattern ) !== false ) {
+                        return true;
+                    }
+                }
+            }
+        }
+
+        // Check if plugin has custom version scheme
+        if ( ! empty( $plugin_data['Version'] ) ) {
+            $version = $plugin_data['Version'];
+            // Custom version indicators (not semantic versioning)
+            if ( preg_match( '/[a-zA-Z]/', $version ) && ! preg_match( '/^(beta|alpha|rc|dev)/i', $version ) ) {
+                return true;
+            }
+        }
+
+        return true; // Default to custom if not on WordPress.org
+    }

    /**
     * Add custom plugin data to sync payload
     * 
     * @param array $data
     * @param string $plugin_file
     * @param array $plugin_data
     * @return array
     */
    public function add_custom_plugin_data( $data, $plugin_file, $plugin_data ) {
        if ( ! $this->is_custom_plugin( $plugin_file ) ) {
            return $data;
        }

        $plugin_slug = dirname( $plugin_file );
        if ( $plugin_slug === '.' ) {
            $plugin_slug = basename( $plugin_file, '.php' );
        }

        // Get plugin file structure
        $plugin_files = $this->get_plugin_files( $plugin_file );
        
        // Get plugin activation state
        $is_active = is_plugin_active( $plugin_file );
        
        // Get plugin data
        $full_plugin_data = get_plugin_data( WP_PLUGIN_DIR . '/' . $plugin_file );
        
        // Package plugin files
        $plugin_package = $this->package_plugin( $plugin_file );

        $data['custom_plugin'] = array(
            'plugin_file' => $plugin_file,
            'plugin_slug' => $plugin_slug,
            'plugin_data' => $full_plugin_data,
            'is_active' => $is_active,
            'files' => $plugin_files,
            'package' => $plugin_package,
            'checksum' => md5_file( $plugin_package ),
        );

        return $data;
    }

    /**
     * Allow custom plugins to be synced
     * 
     * @param bool $can_sync
     * @param string $plugin_file
     * @return bool
     */
    public function can_sync_custom_plugin( $can_sync, $plugin_file ) {
        if ( $this->is_custom_plugin( $plugin_file ) ) {
            return true;
        }
        return $can_sync;
    }

    /**
     * Process custom plugin during sync
     * 
     * @param array $plugin_data
     * @param array $details
     */
    public function process_custom_plugin( $plugin_data, $details ) {
        if ( empty( $details['custom_plugin'] ) ) {
            return;
        }

        $custom_plugin = $details['custom_plugin'];
        $plugin_file = $custom_plugin['plugin_file'];
        $plugin_slug = $custom_plugin['plugin_slug'];

        // Handle different sync operations
        switch ( $plugin_data['event_slug'] ) {
            case 'plugin_install':
                $this->install_custom_plugin( $custom_plugin );
                break;
                
            case 'plugin_update':
                $this->update_custom_plugin( $custom_plugin );
                break;
                
            case 'activate_plugin':
                $this->activate_custom_plugin( $plugin_file );
                break;
                
            case 'deactivate_plugin':
                $this->deactivate_custom_plugin( $plugin_file );
                break;
                
            case 'deleted_plugin':
                $this->delete_custom_plugin( $plugin_file );
                break;
        }
    }

    /**
     * Get all files in a plugin directory
     * 
     * @param string $plugin_file
     * @return array
     */
    private function get_plugin_files( $plugin_file ) {
        $plugin_dir = dirname( WP_PLUGIN_DIR . '/' . $plugin_file );
        $files = array();
        
        if ( ! is_dir( $plugin_dir ) ) {
            return $files;
        }

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator( $plugin_dir, RecursiveDirectoryIterator::SKIP_DOTS )
        );

        foreach ( $iterator as $file ) {
            if ( $file->isFile() ) {
                $relative_path = str_replace( $plugin_dir . '/', '', $file->getPathname() );
                $files[ $relative_path ] = array(
                    'path' => $relative_path,
                    'size' => $file->getSize(),
                    'mtime' => $file->getMTime(),
                    'checksum' => md5_file( $file->getPathname() ),
                );
            }
        }

        return $files;
    }

    /**
     * Package plugin into a ZIP file
     * 
     * @param string $plugin_file
     * @return string Path to ZIP file
     */
    private function package_plugin( $plugin_file ) {
        $plugin_dir = dirname( WP_PLUGIN_DIR . '/' . $plugin_file );
        $plugin_slug = basename( $plugin_dir );
        
        if ( ! is_dir( $this->temp_dir ) ) {
            wp_mkdir_p( $this->temp_dir );
        }

        $zip_file = $this->temp_dir . $plugin_slug . '-' . time() . '.zip';
        
        if ( class_exists( 'ZipArchive' ) ) {
            $zip = new ZipArchive();
            if ( $zip->open( $zip_file, ZipArchive::CREATE | ZipArchive::OVERWRITE ) === true ) {
                $this->add_directory_to_zip( $zip, $plugin_dir, $plugin_slug );
                $zip->close();
            }
        } else {
            // Fallback to PclZip
            if ( class_exists( 'PclZip' ) ) {
                $archive = new PclZip( $zip_file );
                $archive->add( $plugin_dir, PCLZIP_OPT_REMOVE_PATH, dirname( $plugin_dir ) );
            }
        }

        return $zip_file;
    }

    /**
     * Add directory to
