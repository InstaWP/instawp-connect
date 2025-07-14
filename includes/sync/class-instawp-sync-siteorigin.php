<?php

use InstaWP\Connect\Helpers\Helper;

defined( 'ABSPATH' ) || exit;

/**
 * Class InstaWP_Sync_SiteOrigin
 * 
 * Handles synchronization of SiteOrigin Page Builder content in 2-Way Sync
 * Supports Page Builder by SiteOrigin including:
 * - Custom pages built with SiteOrigin
 * - Content elements: Text, images, galleries, buttons
 * - Dynamic content (post loops, custom fields)
 * - Taxonomies (categories, terms, tags) associated with dynamic posts
 * 
 * @since 1.0.0
 */
class InstaWP_Sync_SiteOrigin {

    /**
     * SiteOrigin meta keys that need special handling
     * 
     * @var array
     */
    private $siteorigin_meta_keys = array(
        'panels_data',
        'panels_css',
        'panels_inline_css',
        'panels_cache',
    );

    /**
     * Constructor
     */
    public function __construct() {
        // Add SiteOrigin support filters
        add_filter( 'instawp/filters/2waysync/post_data', array( $this, 'add_siteorigin_data' ), 10, 3 );
        add_filter( 'instawp/filters/2waysync/can_sync_post', array( $this, 'can_sync_siteorigin_post' ), 10, 2 );
        add_action( 'instawp/actions/2waysync/process_event_post', array( $this, 'process_siteorigin_content' ), 10, 2 );
        
        // Handle SiteOrigin-specific hooks
        add_action( 'save_post', array( $this, 'handle_siteorigin_save' ), 10, 3 );
        add_action( 'siteorigin_panels_save_post', array( $this, 'handle_siteorigin_panels_save' ), 10, 2 );
    }

    /**
     * Check if a post is built with SiteOrigin Page Builder
     * 
     * @param int $post_id
     * @return bool
     */
    public function is_built_with_siteorigin( $post_id ) {
        if ( ! $post_id ) {
            return false;
        }

        // Check for SiteOrigin panels data
        $panels_data = get_post_meta( $post_id, 'panels_data', true );
        
        // Check if SiteOrigin Page Builder is active
        if ( ! class_exists( 'SiteOrigin_Panels' ) ) {
            return false;
        }

        // Check if post has SiteOrigin panels data
        return ! empty( $panels_data ) && is_array( $panels_data );
    }

    /**
     * Add SiteOrigin-specific data to sync payload
     * 
     * @param array $data
     * @param string $type
     * @param WP_Post $post
     * @return array
     */
    public function add_siteorigin_data( $data, $type, $post ) {
        if ( ! $this->is_built_with_siteorigin( $post->ID ) ) {
            return $data;
        }

        // Get SiteOrigin panels data
        $panels_data = get_post_meta( $post->ID, 'panels_data', true );
        
        // Extract dynamic content from SiteOrigin
        $dynamic_data = $this->extract_siteorigin_dynamic_data( $post->ID, $panels_data );

        // Extract media from SiteOrigin content
        $siteorigin_media = $this->extract_siteorigin_media( $panels_data );

        // Get SiteOrigin CSS data
        $css_data = $this->get_siteorigin_css_data( $post->ID );

        $data['siteorigin'] = array(
            'panels_data' => $panels_data,
            'dynamic_data' => $dynamic_data,
            'media' => $siteorigin_media,
            'css_data' => $css_data,
        );

        // Merge SiteOrigin media with existing media
        if ( ! empty( $siteorigin_media ) ) {
            if ( ! isset( $data['media'] ) ) {
                $data['media'] = array();
            }
            $data['media'] = array_merge( $data['media'], $siteorigin_media );
        }

        return $data;
    }

    /**
     * Allow SiteOrigin posts to be synced
     * 
     * @param bool $can_sync
     * @param WP_Post $post
     * @return bool
     */
    public function can_sync_siteorigin_post( $can_sync, $post ) {
        if ( $this->is_built_with_siteorigin( $post->ID ) ) {
            return true;
        }
        return $can_sync;
    }

    /**
     * Process SiteOrigin content during sync
     * 
     * @param array $wp_post
     * @param array $details
     */
    public function process_siteorigin_content( $wp_post, $details ) {
        if ( empty( $details['siteorigin'] ) ) {
            return;
        }

        $siteorigin_data = $details['siteorigin'];
        $post_id = $wp_post['ID'];

        // Process SiteOrigin panels data
        if ( ! empty( $siteorigin_data['panels_data'] ) ) {
            $processed_panels_data = $this->process_panels_data_replacements(
                $siteorigin_data['panels_data'], 
                $siteorigin_data['dynamic_data'] ?? array()
            );
            
            update_post_meta( $post_id, 'panels_data', $processed_panels_data );
        }

        // Process dynamic data replacements
        if ( ! empty( $siteorigin_data['dynamic_data'] ) ) {
            $this->process_siteorigin_dynamic_replacements( $post_id, $siteorigin_data['dynamic_data'] );
        }

        // Process CSS data
        if ( ! empty( $siteorigin_data['css_data'] ) ) {
            $this->process_siteorigin_css_data( $post_id, $siteorigin_data['css_data'] );
        }

        // Regenerate SiteOrigin CSS
        $this->regenerate_siteorigin_css( $post_id );
    }

    /**
     * Extract dynamic data from SiteOrigin content
     * 
     * @param int $post_id
     * @param array $panels_data
     * @return array
     */
    private function extract_siteorigin_dynamic_data( $post_id, $panels_data ) {
        $dynamic_data = array(
            'post_ids' => array(),
            'term_ids' => array(),
            'user_ids' => array(),
            'attachment_ids' => array(),
        );

        if ( empty( $panels_data ) || ! is_array( $panels_data ) ) {
            return $dynamic_data;
        }

        // Process widgets for dynamic content
        if ( ! empty( $panels_data['widgets'] ) ) {
            foreach ( $panels_data['widgets'] as $widget ) {
                $dynamic_data = $this->extract_widget_dynamic_data( $widget, $dynamic_data );
            }
        }

        return $dynamic_data;
    }

    /**
     * Extract dynamic data from SiteOrigin widgets
     * 
     * @param array $widget
     * @param array $dynamic_data
     * @return array
     */
    private function extract_widget_dynamic_data( $widget, $dynamic_data ) {
        if ( empty( $widget ) || ! is_array( $widget ) ) {
            return $dynamic_data;
        }

        // Handle Post Loop widget
        if ( ! empty( $widget['panels_info']['class'] ) && $widget['panels_info']['class'] === 'SiteOrigin_Panels_Widgets_PostLoop' ) {
            if ( ! empty( $widget['template'] ) ) {
                // Extract template file references
                $dynamic_data = $this->extract_template_references( $widget['template'], $dynamic_data );
            }
            
            if ( ! empty( $widget['additional'] ) ) {
                // Extract additional query parameters
                $dynamic_data = $this->extract_query_parameters( $widget['additional'], $dynamic_data );
            }
        }

        // Handle Post Content widget
        if ( ! empty( $widget['panels_info']['class'] ) && $widget['panels_info']['class'] === 'SiteOrigin_Panels_Widgets_PostContent' ) {
            if ( ! empty( $widget['post'] ) ) {
                $dynamic_data = $this->add_reference_data( $dynamic_data, $widget['post'], 'post_ids' );
            }
        }

        // Handle Layout widget (nested layouts)
        if ( ! empty( $widget['panels_info']['class'] ) && $widget['panels_info']['class'] === 'SiteOrigin_Panels_Widgets_Layout' ) {
            if ( ! empty( $widget['panels_data'] ) ) {
                $nested_dynamic_data = $this->extract_siteorigin_dynamic_data( 0, $widget['panels_data'] );
                $dynamic_data = $this->merge_dynamic_data( $dynamic_data, $nested_dynamic_data );
            }
        }

        // Handle image widgets
        if ( isset( $widget['image'] ) && is_numeric( $widget['image'] ) ) {
            $dynamic_data = $this->add_reference_data( $dynamic_data, $widget['image'], 'attachment_ids' );
        }

        // Handle general widget content for shortcodes and dynamic tags
        foreach ( $widget as $key => $value ) {
            if ( is_string( $value ) ) {
                $dynamic_data = $this->extract_content_dynamic_data( $value, $dynamic_data );
            }
        }

        return $dynamic_data;
    }

    /**
     * Extract media from SiteOrigin content
     * 
     * @param array $panels_data
     * @return array
     */
    private function extract_siteorigin_media( $panels_data ) {
        $media = array();

        if ( empty( $panels_data ) || ! is_array( $panels_data ) ) {
            return $media;
        }

        // Process widgets for media
        if ( ! empty( $panels_data['widgets'] ) ) {
            foreach ( $panels_data['widgets'] as $widget ) {
                $media = array_merge( $media, $this->extract_widget_media( $widget ) );
            }
        }

        return $media;
    }

    /**
     * Extract media from SiteOrigin widgets
     * 
     * @param array $widget
     * @return array
     */
    private function extract_widget_media( $widget ) {
        $media = array();

        if ( empty( $widget ) || ! is_array( $widget ) ) {
            return $media;
        }

        // Handle image widgets
        if ( isset( $widget['image'] ) && is_numeric( $widget['image'] ) ) {
            $media[] = InstaWP_Sync_Parser::generate_attachment_data( $widget['image'] );
        }

        // Handle background images in styles
        if ( ! empty( $widget['style']['background_image_attachment'] ) ) {
            $media[] = InstaWP_Sync_Parser::generate_attachment_data( $widget['style']['background_image_attachment'] );
        }

        // Handle content with images
        foreach ( $widget as $key => $value ) {
            if ( is_string( $value ) && strpos( $value, '<img' ) !== false ) {
                $content_media = InstaWP_Sync_Parser::get_media_from_content( $value );
                $media = array_merge( $media, $content_media );
            }
        }

        return $media;
    }

    /**
     * Get SiteOrigin CSS data
     * 
     * @param int $post_id
     * @return array
     */
    private function get_siteorigin_css_data( $post_id ) {
        $css_data = array();

        // Get generated CSS
        $css = get_post_meta( $post_id, 'panels_css', true );
        if ( ! empty( $css ) ) {
            $css_data['panels_css'] = $css;
        }

        // Get inline CSS
        $inline_css = get_post_meta( $post_id, 'panels_inline_css', true );
        if ( ! empty( $inline_css ) ) {
            $css_data['panels_inline_css'] = $inline_css;
        }

        return $css_data;
    }

    /**
     * Process panels data replacements
     * 
     * @param array $panels_data
     * @param array $replacements
     * @return array
     */
    private function process_panels_data_replacements( $panels_data, $replacements ) {
        if ( empty( $panels_data ) || ! is_array( $panels_data ) ) {
            return $panels_data;
        }

        // Process widgets
        if ( ! empty( $panels_data['widgets'] ) ) {
            foreach ( $panels_data['widgets'] as &$widget ) {
                $widget = $this->process_widget_replacements( $widget, $replacements );
            }
        }

        return $panels_data;
    }

    /**
     * Process widget replacements
     * 
     * @param array $widget
     * @param array $replacements
     * @return array
     */
    private function process_widget_replacements( $widget, $replacements ) {
        if ( empty( $widget ) || ! is_array( $widget ) ) {
            return $widget;
        }

        // Handle Post Loop widget
        if ( ! empty( $widget['panels_info']['class'] ) && $widget['panels_info']['class'] === 'SiteOrigin_Panels_Widgets_PostLoop' ) {
            if ( ! empty( $widget['template'] ) ) {
                $widget['template'] = $this->replace_template_references( $widget['template'], $replacements );
            }
        }

        // Handle Post Content widget
        if ( ! empty( $widget['panels_info']['class'] ) && $widget['panels_info']['class'] === 'SiteOrigin_Panels_Widgets_PostContent' ) {
            if ( ! empty( $widget['post'] ) && isset( $replacements['post_ids'][ $widget['post'] ] ) ) {
                $widget['post'] = $replacements['post_ids'][ $widget['post'] ];
            }
        }

        // Handle Layout widget
        if ( ! empty( $widget['panels_info']['class'] ) && $widget['panels_info']['class'] === 'SiteOrigin_Panels_Widgets_Layout' ) {
            if ( ! empty( $widget['panels_data'] ) ) {
                $widget['panels_data'] = $this->process_panels_data_replacements( $widget['panels_data'], $replacements );
            }
        }

        // Handle image references
        if ( isset( $widget['image'] ) && isset( $replacements['attachment_ids'][ $widget['image'] ] ) ) {
            $widget['image'] = $replacements['attachment_ids'][ $widget['image'] ];
        }

        // Handle background images
        if ( ! empty( $widget['style']['background_image_attachment'] ) && isset( $replacements['attachment_ids'][ $widget['style']['background_image_attachment'] ] ) ) {
            $widget['style']['background_image_attachment'] = $replacements['attachment_ids'][ $widget['style']['background_image_attachment'] ];
        }

        return $widget;
    }

    /**
     * Process SiteOrigin dynamic replacements
     * 
     * @param int $post_id
     * @param array $dynamic_data
     */
    private function process_siteorigin_dynamic_replacements( $post_id, $dynamic_data ) {
        // Process post ID replacements
        if ( ! empty( $dynamic_data['post_ids'] ) ) {
            $this->replace_siteorigin_post_ids( $post_id, $dynamic_data['post_ids'] );
        }

        // Process term ID replacements
        if ( ! empty( $dynamic_data['term_ids'] ) ) {
            $this->replace_siteorigin_term_ids( $post_id, $dynamic_data['term_ids'] );
        }

        // Process user ID replacements
        if ( ! empty( $dynamic_data['user_ids'] ) ) {
            $this->replace_siteorigin_user_ids( $post_id, $dynamic_data['user_ids'] );
        }

        // Process attachment ID replacements
        if ( ! empty( $dynamic_data['attachment_ids'] ) ) {
            $this->replace_siteorigin_attachment_ids( $post_id, $dynamic_data['attachment_ids'] );
        }
    }

    /**
     * Process SiteOrigin CSS data
     * 
     * @param int $post_id
     * @param array $css_data
     */
    private function process_siteorigin_css_data( $post_id, $css_data ) {
        if ( ! empty( $css_data['panels_css'] ) ) {
            update_post_meta( $post_id, 'panels_css', $css_data['panels_css'] );
        }

        if ( ! empty( $css_data['panels_inline_css'] ) ) {
            update_post_meta( $post_id, 'panels_inline_css', $css_data['panels_inline_css'] );
        }
    }

    /**
     * Regenerate SiteOrigin CSS
     * 
     * @param int $post_id
     */
    private function regenerate_siteorigin_css( $post_id ) {
        if ( class_exists( 'SiteOrigin_Panels' ) ) {
            // Clear existing CSS cache
            delete_post_meta( $post_id, 'panels_css' );
            delete_post_meta( $post_id, 'panels_inline_css' );

            // Regenerate CSS if SiteOrigin is active
            $panels_data = get_post_meta( $post_id, 'panels_data', true );
            if ( ! empty( $panels_data ) ) {
                // Trigger CSS regeneration
                $renderer = SiteOrigin_Panels::renderer();
                if ( method_exists( $renderer, 'generate_css' ) ) {
                    $css = $renderer->generate_css( $post_id, $panels_data );
                    if ( ! empty( $css ) ) {
                        update_post_meta( $post_id, 'panels_css', $css );
                    }
                }
            }
        }
    }

    /**
     * Extract template references - REMOVED DUPLICATE FUNCTION
     * 
     * @param string $template
     * @param array $dynamic_data
     * @return array
     */
    // private function extract_template_references( $template, $dynamic_data ) {
    //     // Extract post IDs from template names
    //     if ( preg_match_all( '/post-(\d+)/', $template, $matches ) ) {
    //         foreach ( $matches[1] as $post_id ) {
    //             $dynamic_data = $this->add_reference_data( $dynamic_data, $post_id, 'post_ids' );
    //         }
    //     }
    //     return $dynamic_data;
    // }

    //    return $dynamic_data;
    //}

    /**
     * Extract query parameters
     * 
     * @param array $query
     * @param array $dynamic_data
     * @return array
     */
    private function extract_query_parameters( $query, $dynamic_data ) {
        if ( ! empty( $query['post__in'] ) && is_array( $query['post__in'] ) ) {
            foreach ( $query['post__in'] as $post_id ) {
                $dynamic_data = $this->add_reference_data( $dynamic_data, $post_id, 'post_ids' );
            }
        }

        if ( ! empty( $query['tax_query'] ) && is_array( $query['tax_query'] ) ) {
            foreach ( $query['tax_query'] as $tax_query ) {
                if ( ! empty( $tax_query['terms'] ) ) {
                    foreach ( $tax_query['terms'] as $term_id ) {
                        $taxonomy = $tax_query['taxonomy'] ?? 'category';
                        $dynamic_data = $this->add_reference_data( $dynamic_data, $term_id, 'term_ids', $taxonomy );
                    }
                }
            }
        }

        return $dynamic_data;
    }

    /**
     * Extract content dynamic data
     * 
     * @param string $content
     * @param array $dynamic_data
     * @return array
     */
    private function extract_content_dynamic_data( $content, $dynamic_data ) {
        // Extract post IDs from shortcodes
        if ( preg_match_all( '/\[.*?\sid=["\'](\d+)["\'].*?\]/', $content, $matches ) ) {
            foreach ( $matches[1] as $post_id ) {
                $dynamic_data = $this->add_reference_data( $dynamic_data, $post_id, 'post_ids' );
            }
        }

        // Extract attachment IDs from image tags
        if ( preg_match_all( '/wp-image-(\d+)/', $content, $matches ) ) {
            foreach ( $matches[1] as $attachment_id ) {
                $dynamic_data = $this->add_reference_data( $dynamic_data, $attachment_id, 'attachment_ids' );
            }
        }

        return $dynamic_data;
    }

    /**
     * Add reference data
     * 
     * @param array $dynamic_data
     * @param mixed $id
     * @param string $type
     * @param string $taxonomy
     * @return array
     */
    private function add_reference_data( $dynamic_data, $id, $type = 'post_ids', $taxonomy = '' ) {
        if ( empty( $id ) ) {
            return $dynamic_data;
        }

        if ( is_array( $id ) ) {
            foreach ( $id as $single_id ) {
                $dynamic_data = $this->add_reference_data( $dynamic_data, $single_id, $type, $taxonomy );
            }
            return $dynamic_data;
        }

        if ( ! is_numeric( $id ) ) {
            return $dynamic_data;
        }

        $id = intval( $id );
        if ( $id <= 0 ) {
            return $dynamic_data;
        }

        if ( ! isset( $dynamic_data[ $type ] ) ) {
            $dynamic_data[ $type ] = array();
        }

        if ( ! in_array( $id, $dynamic_data[ $type ] ) ) {
            $dynamic_data[ $type ][] = $id;
        }

        return $dynamic_data;
    }

    /**
     * Merge dynamic data arrays
     * 
     * @param array $data1
     * @param array $data2
     * @return array
     */
    private function merge_dynamic_data( $data1, $data2 ) {
        foreach ( $data2 as $key => $values ) {
            if ( ! isset( $data1[ $key ] ) ) {
                $data1[ $key ] = array();
            }
            $data1[ $key ] = array_merge( $data1[ $key ], $values );
            $data1[ $key ] = array_unique( $data1[ $key ] );
        }
        return $data1;
    }

    /**
     * Replace post IDs in SiteOrigin content
     * 
     * @param int $post_id
     * @param array $replacements
     */
    private function replace_siteorigin_post_ids( $post_id, $replacements ) {
        $panels_data = get_post_meta( $post_id, 'panels_data', true );
        if ( empty( $panels_data ) ) {
            return;
        }

        $processed_data = $this->replace_ids_in_panels_data( $panels_data, $replacements, 'post_ids' );
        update_post_meta( $post_id, 'panels_data', $processed_data );
    }

    /**
     * Replace term IDs in SiteOrigin content
     * 
     * @param int $post_id
     * @param array $replacements
     */
    private function replace_siteorigin_term_ids( $post_id, $replacements ) {
        $panels_data = get_post_meta( $post_id, 'panels_data', true );
        if ( empty( $panels_data ) ) {
            return;
        }

        $processed_data = $this->replace_ids_in_panels_data( $panels_data, $replacements, 'term_ids' );
        update_post_meta( $post_id, 'panels_data', $processed_data );
    }

    /**
     * Replace user IDs in SiteOrigin content
     * 
     * @param int $post_id
     * @param array $replacements
     */
    private function replace_siteorigin_user_ids( $post_id, $replacements ) {
        $panels_data = get_post_meta( $post_id, 'panels_data', true );
        if ( empty( $panels_data ) ) {
            return;
        }

        $processed_data = $this->replace_ids_in_panels_data( $panels_data, $replacements, 'user_ids' );
        update_post_meta( $post_id, 'panels_data', $processed_data );
    }

    /**
     * Replace attachment IDs in SiteOrigin content
     * 
     * @param int $post_id
     * @param array $replacements
     */
    private function replace_siteorigin_attachment_ids( $post_id, $replacements ) {
        $panels_data = get_post_meta( $post_id, 'panels_data', true );
        if ( empty( $panels_data ) ) {
            return;
        }

        $processed_data = $this->replace_ids_in_panels_data( $panels_data, $replacements, 'attachment_ids' );
        update_post_meta( $post_id, 'panels_data', $processed_data );
    }

    /**
     * Replace IDs in panels data
     * 
     * @param array $panels_data
     * @param array $replacements
     * @param string $type
     * @return array
     */
    private function replace_ids_in_panels_data( $panels_data, $replacements, $type ) {
        if ( empty( $panels_data ) || empty( $replacements ) ) {
            return $panels_data;
        }

        // Convert replacements to JSON for string replacement
        $replacements_json = wp_json_encode( $replacements );

        // Convert panels data to JSON
        $panels_json = wp_json_encode( $panels_data );

        // Replace IDs based on type
        switch ( $type ) {
            case 'post_ids':
                $panels_json = $this->replace_post_ids( $panels_json, $replacements );
                break;
            case 'term_ids':
                $panels_json = $this->replace_term_ids( $panels_json, $replacements );
                break;
            case 'user_ids':
                $panels_json = $this->replace_user_ids( $panels_json, $replacements );
                break;
            case 'attachment_ids':
                $panels_json = $this->replace_attachment_ids( $panels_json, $replacements );
                break;
        }

        return json_decode( $panels_json, true );
    }

    /**
     * Replace post IDs in JSON string
     * 
     * @param string $json
     * @param array $replacements
     * @return string
     */
    private function replace_post_ids( $json, $replacements ) {
        foreach ( $replacements as $old_id => $new_id ) {
            $json = str_replace(
                array(
                    '"post":' . $old_id,
                    '"post_id":' . $old_id,
                    '"id":' . $old_id,
                ),
                array(
                    '"post":' . $new_id,
                    '"post_id":' . $new_id,
                    '"id":' . $new_id,
                ),
                $json
            );
        }
        return $json;
    }

    /**
     * Replace term IDs in JSON string
     * 
     * @param string $json
     * @param array $replacements
     * @return string
     */
    private function replace_term_ids( $json, $replacements ) {
        foreach ( $replacements as $old_id => $new_id ) {
            $json = str_replace(
                array(
                    '"term_id":' . $old_id,
                    '"category":' . $old_id,
                    '"tag":' . $old_id,
                ),
                array(
                    '"term_id":' . $new_id,
                    '"category":' . $new_id,
                    '"tag":' . $new_id,
                ),
                $json
            );
        }
        return $json;
    }

    /**
     * Replace user IDs in JSON string
     * 
     * @param string $json
     * @param array $replacements
     * @return string
     */
    private function replace_user_ids( $json, $replacements ) {
        foreach ( $replacements as $old_id => $new_id ) {
            $json = str_replace(
                array(
                    '"author":' . $old_id,
                    '"user_id":' . $old_id,
                ),
                array(
                    '"author":' . $new_id,
                    '"user_id":' . $new_id,
                ),
                $json
            );
        }
        return $json;
    }

    /**
     * Replace attachment IDs in JSON string
     * 
     * @param string $json
     * @param array $replacements
     * @return string
     */
    private function replace_attachment_ids( $json, $replacements ) {
        foreach ( $replacements as $old_id => $new_id ) {
            $json = str_replace(
                array(
                    '"image":' . $old_id,
                    '"background_image_attachment":' . $old_id,
                    '"attachment":' . $old_id,
                ),
                array(
                    '"image":' . $new_id,
                    '"background_image_attachment":' . $new_id,
                    '"attachment":' . $new_id,
                ),
                $json
            );
        }
        return $json;
    }

    /**
     * Handle SiteOrigin save action
     * 
     * @param int $post_id
     * @param WP_Post $post
     * @param bool $update
     */
    public function handle_siteorigin_save( $post_id, $post, $update ) {
        if ( ! $this->is_built_with_siteorigin( $post_id ) ) {
            return;
        }

        // Trigger sync event for SiteOrigin content
        $singular_name = InstaWP_Sync_Helpers::get_post_type_name( $post->post_type );
        $event_name = sprintf( __( '%s modified (SiteOrigin)', 'instawp-connect' ), $singular_name );
        
        $data = InstaWP_Sync_Parser::parse_post_data( $post );
        $reference_id = isset( $data['reference_id'] ) ? $data['reference_id'] : '';
        
        if ( ! empty( $reference_id ) ) {
            InstaWP_Sync_DB::insert_update_event( $event_name, 'post_change', $post->post_type, $reference_id, $post->post_title, $data );
        }
    }

    /**
     * Handle SiteOrigin panels save
     * 
     * @param int $post_id
     * @param array $panels_data
     */
    public function handle_siteorigin_panels_save( $post_id, $panels_data ) {
        if ( ! $this->is_built_with_siteorigin( $post_id ) ) {
            return;
        }

        // Trigger sync event for SiteOrigin panels save
        $post = get_post( $post_id );
        if ( $post ) {
            $singular_name = InstaWP_Sync_Helpers::get_post_type_name( $post->post_type );
            $event_name = sprintf( __( '%s modified via SiteOrigin Page Builder', 'instawp-connect' ), $singular_name );
            
            $parsed_data = InstaWP_Sync_Parser::parse_post_data( $post );
            $reference_id = isset( $parsed_data['reference_id'] ) ? $parsed_data['reference_id'] : '';
            
            if ( ! empty( $reference_id ) ) {
                InstaWP_Sync_DB::insert_update_event( $event_name, 'post_change', $post->post_type, $reference_id, $post->post_title, $parsed_data );
            }
        }
    }

    /**
     * Extract template references
     * 
     * @param string $template
     * @param array $dynamic_data
     * @return array
     */
    private function extract_template_references( $template, $dynamic_data ) {
        // Extract post IDs from template names
        if ( preg_match_all( '/post-(\d+)/', $template, $matches ) ) {
            foreach ( $matches[1] as $post_id ) {
                $dynamic_data = $this->add_reference_data( $dynamic_data, $post_id, 'post_ids' );
            }
        }

        return $dynamic_data;
    }

    /**
     * Replace template references
     * 
     * @param string $template
     * @param array $replacements
     * @return string
     */
    private function replace_template_references( $template, $replacements ) {
        if ( ! empty( $replacements['post_ids'] ) ) {
            foreach ( $replacements['post_ids'] as $old_id => $new_id ) {
                $template = str_replace( 'post-' . $old_id, 'post-' . $new_id, $template );
            }
        }
        return $template;
    }
}

// Initialize SiteOrigin sync
new InstaWP_Sync_SiteOrigin();
