<?php

use InstaWP\Connect\Helpers\Helper;

defined( 'ABSPATH' ) || exit;

/**
 * Class InstaWP_Sync_Bricks
 * 
 * Handles synchronization of Bricks Builder content in 2-Way Sync
 * 
 * @since 1.0.0
 */
class InstaWP_Sync_Bricks {

    /**
     * Bricks meta keys that need special handling
     * 
     * @var array
     */
    private $bricks_meta_keys = array(
        '_bricks_page_content_2',
        '_bricks_page_header_2',
        '_bricks_page_footer_2',
        '_bricks_page_settings',
        '_bricks_template_type',
        '_bricks_template_conditions',
        '_bricks_global_elements',
        '_bricks_css',
        '_bricks_google_fonts',
    );

    /**
     * Constructor
     */
    public function __construct() {
        // Add Bricks support filters
        add_filter( 'instawp/filters/2waysync/post_data', array( $this, 'add_bricks_data' ), 10, 3 );
        add_filter( 'instawp/filters/2waysync/can_sync_post', array( $this, 'can_sync_bricks_post' ), 10, 2 );
        add_action( 'instawp/actions/2waysync/process_event_post', array( $this, 'process_bricks_content' ), 10, 2 );
        
        // Handle Bricks-specific hooks
        add_action( 'save_post', array( $this, 'handle_bricks_save' ), 10, 3 );
        add_action( 'bricks_save_post', array( $this, 'handle_bricks_builder_save' ), 10, 2 );
    }

    /**
     * Check if a post is built with Bricks
     * 
     * @param int $post_id
     * @return bool
     */
    public function is_built_with_bricks( $post_id ) {
        if ( ! $post_id ) {
            return false;
        }

        // Check for Bricks meta data
        $bricks_content = get_post_meta( $post_id, '_bricks_page_content_2', true );
        
        // Check if Bricks builder is active
        if ( ! class_exists( '\Bricks\Helpers' ) ) {
            return false;
        }

        // Check if post uses Bricks template
        $template_id = get_post_meta( $post_id, '_bricks_page_template', true );
        
        return ! empty( $bricks_content ) || ! empty( $template_id );
    }

    /**
     * Add Bricks-specific data to sync payload
     * 
     * @param array $data
     * @param string $type
     * @param WP_Post $post
     * @return array
     */
    public function add_bricks_data( $data, $type, $post ) {
        if ( ! $this->is_built_with_bricks( $post->ID ) ) {
            return $data;
        }

        // Add Bricks meta data
        $bricks_meta = array();
        foreach ( $this->bricks_meta_keys as $meta_key ) {
            $meta_value = get_post_meta( $post->ID, $meta_key, true );
            if ( ! empty( $meta_value ) ) {
                $bricks_meta[ $meta_key ] = $meta_value;
            }
        }

        // Extract dynamic content from Bricks
        $dynamic_data = $this->extract_bricks_dynamic_data( $post->ID );

        // Get Bricks template data if applicable
        $template_data = $this->get_bricks_template_data( $post->ID );

        $data['bricks'] = array(
            'meta' => $bricks_meta,
            'dynamic_data' => $dynamic_data,
            'template_data' => $template_data,
            'global_elements' => $this->get_global_elements_data(),
        );

        // Extract media from Bricks content
        $bricks_media = $this->extract_bricks_media( $bricks_meta );
        if ( ! empty( $bricks_media ) ) {
            if ( ! isset( $data['media'] ) ) {
                $data['media'] = array();
            }
            $data['media'] = array_merge( $data['media'], $bricks_media );
        }

        return $data;
    }

    /**
     * Allow Bricks posts to be synced
     * 
     * @param bool $can_sync
     * @param WP_Post $post
     * @return bool
     */
    public function can_sync_bricks_post( $can_sync, $post ) {
        if ( $this->is_built_with_bricks( $post->ID ) ) {
            return true;
        }
        return $can_sync;
    }

    /**
     * Process Bricks content during sync
     * 
     * @param array $wp_post
     * @param array $details
     */
    public function process_bricks_content( $wp_post, $details ) {
        if ( empty( $details['bricks'] ) ) {
            return;
        }

        $bricks_data = $details['bricks'];
        $post_id = $wp_post['ID'];

        // Process Bricks meta data
        if ( ! empty( $bricks_data['meta'] ) ) {
            foreach ( $bricks_data['meta'] as $meta_key => $meta_value ) {
                update_post_meta( $post_id, $meta_key, $meta_value );
            }
        }

        // Process dynamic data replacements
        if ( ! empty( $bricks_data['dynamic_data'] ) ) {
            $this->process_bricks_dynamic_replacements( $post_id, $bricks_data['dynamic_data'] );
        }

        // Process template data
        if ( ! empty( $bricks_data['template_data'] ) ) {
            $this->process_bricks_template_data( $post_id, $bricks_data['template_data'] );
        }

        // Regenerate Bricks CSS
        $this->regenerate_bricks_css( $post_id );
    }

    /**
     * Extract dynamic data from Bricks content
     * 
     * @param int $post_id
     * @return array
     */
    private function extract_bricks_dynamic_data( $post_id ) {
        $dynamic_data = array(
            'post_ids' => array(),
            'term_ids' => array(),
            'user_ids' => array(),
        );

        // Get Bricks content
        $bricks_content = get_post_meta( $post_id, '_bricks_page_content_2', true );
        if ( empty( $bricks_content ) ) {
            return $dynamic_data;
        }

        // Parse Bricks content for dynamic data
        if ( is_string( $bricks_content ) ) {
            $bricks_content = json_decode( $bricks_content, true );
        }

        if ( ! empty( $bricks_content ) && is_array( $bricks_content ) ) {
            $dynamic_data = $this->parse_bricks_elements( $bricks_content, $dynamic_data );
        }

        return $dynamic_data;
    }

    /**
     * Parse Bricks elements for dynamic data
     * 
     * @param array $elements
     * @param array $dynamic_data
     * @return array
     */
    private function parse_bricks_elements( $elements, $dynamic_data ) {
        foreach ( $elements as $element ) {
            if ( ! is_array( $element ) ) {
                continue;
            }

            // Check for dynamic tags
            if ( ! empty( $element['settings'] ) ) {
                $dynamic_data = $this->parse_bricks_settings( $element['settings'], $dynamic_data );
            }

            // Check for nested elements
            if ( ! empty( $element['children'] ) ) {
                $dynamic_data = $this->parse_bricks_elements( $element['children'], $dynamic_data );
            }
        }

        return $dynamic_data;
    }

    /**
     * Parse Bricks settings for dynamic data
     * 
     * @param array $settings
     * @param array $dynamic_data
     * @return array
     */
    private function parse_bricks_settings( $settings, $dynamic_data ) {
        foreach ( $settings as $key => $value ) {
            if ( is_array( $value ) && isset( $value['dynamic'] ) ) {
                // Handle dynamic tags
                $tag_data = $this->parse_bricks_dynamic_tag( $value['dynamic'] );
                if ( $tag_data ) {
                    $dynamic_data = $this->add_bricks_reference_data( $dynamic_data, $tag_data );
                }
            } elseif ( $key === 'query' && is_array( $value ) ) {
                // Handle query loops
                $dynamic_data = $this->parse_bricks_query( $value, $dynamic_data );
            } elseif ( $key === 'image' && is_array( $value ) && ! empty( $value['id'] ) ) {
                // Handle image IDs
                $dynamic_data = $this->add_bricks_reference_data( $dynamic_data, array(
                    'type' => 'post_ids',
                    'id' => $value['id']
                ));
            }
        }

        return $dynamic_data;
    }

    /**
     * Parse Bricks dynamic tag
     * 
     * @param array $dynamic_tag
     * @return array|null
     */
    private function parse_bricks_dynamic_tag( $dynamic_tag ) {
        if ( empty( $dynamic_tag['name'] ) ) {
            return null;
        }

        $tag_name = $dynamic_tag['name'];
        $settings = $dynamic_tag['settings'] ?? array();

        switch ( $tag_name ) {
            case 'post_terms':
                if ( ! empty( $settings['taxonomy'] ) && ! empty( $settings['post_id'] ) ) {
                    return array(
                        'type' => 'term_ids',
                        'id' => $settings['post_id'],
                        'taxonomy' => $settings['taxonomy']
                    );
                }
                break;

            case 'post_author':
                if ( ! empty( $settings['post_id'] ) ) {
                    return array(
                        'type' => 'user_ids',
                        'id' => $settings['post_id']
                    );
                }
                break;

            case 'post_meta':
            case 'post_title':
            case 'post_excerpt':
            case 'post_content':
                if ( ! empty( $settings['post_id'] ) ) {
                    return array(
                        'type' => 'post_ids',
                        'id' => $settings['post_id']
                    );
                }
                break;
        }

        return null;
    }

    /**
     * Parse Bricks query for dynamic data
     * 
     * @param array $query
     * @param array $dynamic_data
     * @return array
     */
    private function parse_bricks_query( $query, $dynamic_data ) {
        if ( ! empty( $query['post_type'] ) && ! empty( $query['posts_per_page'] ) ) {
            // Handle post queries
            if ( ! empty( $query['post__in'] ) ) {
                foreach ( $query['post__in'] as $post_id ) {
                    $dynamic_data = $this->add_bricks_reference_data( $dynamic_data, array(
                        'type' => 'post_ids',
                        'id' => $post_id
                    ));
                }
            }

            // Handle taxonomy queries
            if ( ! empty( $query['tax_query'] ) ) {
                foreach ( $query['tax_query'] as $tax_query ) {
                    if ( ! empty( $tax_query['terms'] ) ) {
                        foreach ( $tax_query['terms'] as $term_id ) {
                            $dynamic_data = $this->add_bricks_reference_data( $dynamic_data, array(
                                'type' => 'term_ids',
                                'id' => $term_id,
                                'taxonomy' => $tax_query['taxonomy'] ?? 'category'
                            ));
                        }
                    }
                }
            }
        }

        return $dynamic_data;
    }

    /**
     * Add reference data for Bricks
     * 
     * @param array $dynamic_data
     * @param array $reference
     * @return array
     */
    private function add_bricks_reference_data( $dynamic_data, $reference ) {
        $type = $reference['type'];
        $id = $reference['id'];

        if ( ! isset( $dynamic_data[ $type ] ) ) {
            $dynamic_data[ $type ] = array();
        }

        if ( ! in_array( $id, $dynamic_data[ $type ] ) ) {
            $dynamic_data[ $type ][] = $id;
        }

        return $dynamic_data;
    }

    /**
     * Get Bricks template data
     * 
     * @param int $post_id
     * @return array
     */
    private function get_bricks_template_data( $post_id ) {
        $template_data = array();

        // Get template ID
        $template_id = get_post_meta( $post_id, '_bricks_page_template', true );
        if ( $template_id ) {
            $template_data['template_id'] = $template_id;
            $template_data['template_data'] = InstaWP_Sync_Parser::parse_post_data( $template_id );
        }

        return $template_data;
    }

    /**
     * Get global elements data
     * 
     * @return array
     */
    private function get_global_elements_data() {
        $global_data = array();

        // Get global elements
        $global_elements = get_option( 'bricks_global_elements', array() );
        if ( ! empty( $global_elements ) ) {
            $global_data['global_elements'] = $global_elements;
        }

        // Get global settings
        $global_settings = get_option( 'bricks_global_settings', array() );
        if ( ! empty( $global_settings ) ) {
            $global_data['global_settings'] = $global_settings;
        }

        return $global_data;
    }

    /**
     * Extract media from Bricks content
     * 
     * @param array $bricks_meta
     * @return array
     */
    private function extract_bricks_media( $bricks_meta ) {
        $media = array();

        foreach ( $bricks_meta as $meta_key => $meta_value ) {
            if ( is_string( $meta_value ) ) {
                $media = array_merge( $media, InstaWP_Sync_Parser::get_media_from_content( $meta_value ) );
            } elseif ( is_array( $meta_value ) ) {
                $meta_value_json = wp_json_encode( $meta_value );
                $media = array_merge( $media, InstaWP_Sync_Parser::get_media_from_content( $meta_value_json ) );
            }
        }

        return $media;
    }

    /**
     * Process Bricks dynamic replacements
     * 
     * @param int $post_id
     * @param array $dynamic_data
     */
    private function process_bricks_dynamic_replacements( $post_id, $dynamic_data ) {
        // Process post ID replacements
        if ( ! empty( $dynamic_data['post_ids'] ) ) {
            $this->replace_bricks_post_ids( $post_id, $dynamic_data['post_ids'] );
        }

        // Process term ID replacements
        if ( ! empty( $dynamic_data['term_ids'] ) ) {
            $this->replace_bricks_term_ids( $post_id, $dynamic_data['term_ids'] );
        }

        // Process user ID replacements
        if ( ! empty( $dynamic_data['user_ids'] ) ) {
            $this->replace_bricks_user_ids( $post_id, $dynamic_data['user_ids'] );
        }
    }

    /**
     * Replace post IDs in Bricks content
     * 
     * @param int $post_id
     * @param array $post_replacements
     */
    private function replace_bricks_post_ids( $post_id, $post_replacements ) {
        $bricks_content = get_post_meta( $post_id, '_bricks_page_content_2', true );
        if ( empty( $bricks_content ) ) {
            return;
        }

        $bricks_content = $this->replace_ids_in_content( $bricks_content, $post_replacements, 'post' );
        update_post_meta( $post_id, '_bricks_page_content_2', $bricks_content );
    }

    /**
     * Replace term IDs in Bricks content
     * 
     * @param int $post_id
     * @param array $term_replacements
     */
    private function replace_bricks_term_ids( $post_id, $term_replacements ) {
        // Implementation for term ID replacements
        // Similar to post ID replacements but for taxonomy terms
    }

    /**
     * Replace user IDs in Bricks content
     * 
     * @param int $post_id
     * @param array $user_replacements
     */
    private function replace_bricks_user_ids( $post_id, $user_replacements ) {
        // Implementation for user ID replacements
        // Similar to post ID replacements but for users
    }

    /**
     * Process Bricks template data
     * 
     * @param int $post_id
     * @param array $template_data
     */
    private function process_bricks_template_data( $post_id, $template_data ) {
        if ( ! empty( $template_data['template_id'] ) ) {
            // Sync template if needed
            $template_post = InstaWP_Sync_Helpers::get_post_by_reference( 'bricks_template', $template_data['template_id'] );
            if ( $template_post ) {
                update_post_meta( $post_id, '_bricks_page_template', $template_post->ID );
            }
        }
    }

    /**
     * Regenerate Bricks CSS
     * 
     * @param int $post_id
     */
    private function regenerate_bricks_css( $post_id ) {
        if ( class_exists( '\Bricks\Frontend' ) ) {
            // Clear Bricks cache
            delete_post_meta( $post_id, '_bricks_css' );
            delete_post_meta( $post_id, '_bricks_google_fonts' );
            
            // Regenerate CSS
            \Bricks\Frontend::generate_post_css( $post_id );
        }
    }

    /**
     * Handle Bricks save action
     * 
     * @param int $post_id
     * @param WP_Post $post
     * @param bool $update
     */
    public function handle_bricks_save( $post_id, $post, $update ) {
        if ( ! $this->is_built_with_bricks( $post_id ) ) {
            return;
        }

        // Trigger sync event for Bricks content
        $singular_name = InstaWP_Sync_Helpers::get_post_type_name( $post->post_type );
        $event_name = sprintf( __( '%s modified (Bricks)', 'instawp-connect' ), $singular_name );
        
        $data = InstaWP_Sync_Parser::parse_post_data( $post );
        $reference_id = isset( $data['reference_id'] ) ? $data['reference_id'] : '';
        
        if ( ! empty( $reference_id ) ) {
            InstaWP_Sync_DB::insert_update_event( $event_name, 'post_change', $post->post_type, $reference_id, $post->post_title, $data );
        }
    }

    /**
     * Handle Bricks builder save
     * 
     * @param int $post_id
     * @param array $data
     */
    public function handle_bricks_builder_save( $post_id, $data ) {
        if ( ! $this->is_built_with_bricks( $post_id ) ) {
            return;
        }

        // Trigger sync event for Bricks builder save
        $post = get_post( $post_id );
        if ( $post ) {
            $singular_name = InstaWP_Sync_Helpers::get_post_type_name( $post->post_type );
            $event_name = sprintf( __( '%s modified via Bricks Builder', 'instawp-connect' ), $singular_name );
            
            $parsed_data = InstaWP_Sync_Parser::parse_post_data( $post );
            $reference_id = isset( $parsed_data['reference_id'] ) ? $parsed_data['reference_id'] : '';
            
            if ( ! empty( $reference_id ) ) {
                InstaWP_Sync_DB::insert_update_event( $event_name, 'post_change', $post->post_type, $reference_id, $post->post_title, $parsed_data );
            }
        }
    }

    /**
     * Replace IDs in content
     * 
     * @param string $content
     * @param array $replacements
     * @param string $type
     * @return string
     */
    private function replace_ids_in_content( $content, $replacements, $type ) {
        if ( empty( $content ) || empty( $replacements ) ) {
            return $content;
        }

        if ( is_string( $content ) ) {
            $content = json_decode( $content, true );
        }

        if ( ! is_array( $content ) ) {
            return $content;
        }

        $content_json = wp_json_encode( $content );
        
        foreach ( $replacements as $old_id => $new_id ) {
            $content_json = str_replace(
                array(
                    '"id":' . $old_id,
                    '"post_id":' . $old_id,
                    '"attachment_id":' . $old_id,
                ),
                array(
                    '"id":' . $new_id,
                    '"post_id":' . $new_id,
                    '"attachment_id":' . $new_id,
                ),
                $content_json
            );
        }

        return json_decode( $content_json, true );
    }
}

// Initialize Bricks sync
new InstaWP_Sync_Bricks();
