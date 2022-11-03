<?php

if ( ! defined('INSTAWP_PLUGIN_DIR') ) {
    die;
}
require_once INSTAWP_PLUGIN_DIR . '/includes/class-instawp-zipclass.php';
class InstaWP_Backup_Site
{
    private $tools_collection = array();

    public function __construct() {
        add_filter('instawp_tools_register', array( $this, 'init_tools' ),10);
        $this->tools_collection = apply_filters('instawp_tools_register',$this->tools_collection);
        $this->load_hooks();
    }

    public function init_tools( $tools_collection ) {
        $tools_collection['zip'][ INSTAWP_COMPRESS_ZIPCLASS ] = 'InstaWP_ZipClass';
        return $tools_collection;
    }

    public function get_tools( $type ) {
        if ( array_key_exists($type,$this->tools_collection) ) {
            foreach ( $this -> tools_collection[ $type ] as $class_name ) {
                if ( class_exists($class_name) ) {
                    $object = new $class_name();
                    $last_error = $object -> getLastError();
                    if (empty($last_error))
                        return $object;
                }
            }
        }
        $class_name = $this -> tools_collection['zip'][ INSTAWP_COMPRESS_ZIPCLASS ];
        $object = new $class_name();
        $last_error = $object -> getLastError();
        if ( empty($last_error) ) {
            return $object;
        }else {
            return array(
				'result' => INSTAWP_FAILED, 
				'error'  => $last_error,
			);
        }
    }

    public function load_hooks(){
        foreach ( $this -> tools_collection as $compressType ) {
            foreach ( $compressType as $className ) {
                $object = new $className();
            }
        }
    }
}