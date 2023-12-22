<?php
/**
 * This file is used for change event traking 
 *
 * @link       https://instawp.com/
 * @since      1.0
 * @package    instaWP
 * @subpackage instaWP/admin
 */

defined( 'ABSPATH' ) || exit;

class InstaWP_Sync_Events
{
    private $wpdb;

    public function __construct() {
        global $wpdb;

        $this->wpdb = $wpdb;

        #Customizer
        //add_action( 'customize_save_after',array($this,'customizeSaveAfter'));
        #Woocommerce
        // add_action( 'woocommerce_attribute_added', array($this,'attribute_added_action_callback'), 10, 2 );
        // add_action( 'woocommerce_attribute_updated', array($this,'attribute_updated_action_callback'), 10, 2 );
        // add_action( 'woocommerce_attribute_deleted', array($this,'attribute_deleted_action_callback'), 10, 2 );

        #Widgets
        //add_action( 'rest_after_save_widget', array($this,'save_widget_action'), 10, 4 );
    }

	/**
	 * Function for `rest_after_save_widget` action-hook.
	 *
	 * @param string          $id         ID of the widget being saved.
	 * @param string          $sidebar_id ID of the sidebar containing the widget being saved.
	 * @param WP_REST_Request $request    Request object.
	 * @param bool            $creating   True when creating a widget, false when updating.
	 *
	 * @return void
	 */
	public function save_widget_action( $id, $sidebar_id, $request, $creating ) {
		$event_name   = 'widget block';
		$event_slug   = 'widget_block';
		$title        = 'widgets update';
		$widget_block = get_option('widget_block' );
		$media        = InstaWP_Sync_Helpers::get_media_from_content( maybe_serialize( $widget_block ) );
		$details      = [ 'widget_block' => $widget_block, 'media' => $media ];
		$rel          = InstaWP_Sync_DB::getByInCondition( INSTAWP_DB_TABLE_EVENTS, [ 'event_slug' => 'widget_block' ] );
		$event_id     = ! empty( $rel ) ? reset( $rel )->id : null;

		InstaWP_Sync_DB::insert_update_event( $event_name, $event_slug, 'widget', $sidebar_id, $title, $details, $event_id );
	}

    /**
     * Customizer settings
     */
    public function customizeSaveAfter($manager) {
        $mods = get_theme_mods();
        $data['custom_logo'] = [
            'id' => $mods['custom_logo'],
            'url' => wp_get_attachment_url($mods['custom_logo'])
        ];
        $data['background_image'] = [
            'id' => attachment_url_to_postid($mods['background_image']),
            'url' => $mods['background_image'],
            'background_preset' => isset($mods['background_preset']) ? $mods['background_preset'] : '',
            'background_position_x' => isset($mods['background_position_x']) ? $mods['background_position_x'] : '',
            'background_position_y' => isset($mods['background_position_y']) ? $mods['background_position_y'] : '',
            'background_size' => isset($mods['background_size']) ? $mods['background_size'] : '',
            'background_repeat' => isset($mods['background_repeat']) ? $mods['background_repeat'] : '',
            'background_attachment' => isset($mods['background_attachment']) ? $mods['background_attachment'] : '',
        ];
        $data['background_color'] = $mods['background_color'];
        $data['custom_css_post'] = wp_get_custom_css_post();
        $data['nav_menu_locations'] = $mods['nav_menu_locations'];
        $data['name'] = get_bloginfo('name');
        $data['description'] = get_bloginfo('description');
        // $data['blogname'] = get_bloginfo('blogname');
        // $data['blogdescription'] = get_bloginfo('blogdescription');
        $data['site_icon'] = [
            'id' => get_option('site_icon'),
            'url' => wp_get_attachment_url(get_option('site_icon'))
        ];
        $current_theme = wp_get_theme();
        if ($current_theme->Name == 'Astra') { #for 'Astra' theme
            $data['astra_settings'] = get_option('astra-settings') ? get_option('astra-settings') : '';
            $data['astra_theme_customizer_settings'] = $this->getAstraCostmizerSetings();
        } else if ($current_theme->Name == 'Divi') { #for 'Divi' theme
            $data['divi_settings'] = get_option('et_divi') ? get_option('et_divi') : '';
        }
        #Homepage Settings
        $data['show_on_front'] = get_option('show_on_front') ? get_option('show_on_front') : '';
        $event_name = 'customizer changes';
        $event_slug = 'customizer_changes';
        $event_type = 'customizer';
        $source_id = '';
        $title = 'customizer changes';
        $details = $data;
        $customizer = InstaWP_Sync_DB::checkCustomizerChanges(INSTAWP_DB_TABLE_EVENTS);
        $date = date('Y-m-d H:i:s');
        if (!empty($customizer)) {
            $customize = reset($customizer);
            #Data Array
            $data = [
                'event_name' => $event_name,
                'event_slug' => $event_slug,
                'event_type' => $event_type,
                'source_id' => $source_id,
                'title' => $title,
                'details' => $details,
                'user_id' => get_current_user_id(),
                'date' => $date,
                'prod' => '',
                'status' => 'pending',
                'synced_message' => ''
            ];
            $this->wpdb->update(
                INSTAWP_DB_TABLE_EVENTS,
                $data,
                array('id' => $customize->id)
            );
        } else {
            InstaWP_Sync_DB::insert_update_event($event_name, $event_slug, $event_type, $source_id, $title, $details);
        }
    }

    /**
     * Get Astra Costmizer Setings
     */
    private function getAstraCostmizerSetings() {
        $arr = [
            #Checkout
            'woocommerce_checkout_company_field' => get_option('woocommerce_checkout_company_field'),
            'woocommerce_checkout_address_2_field' => get_option('woocommerce_checkout_address_2_field'),
            'woocommerce_checkout_phone_field' => get_option('woocommerce_checkout_phone_field'),
            'woocommerce_checkout_highlight_required_fields' => get_option('woocommerce_checkout_highlight_required_fields'),
            'wp_page_for_privacy_policy' => get_option('wp_page_for_privacy_policy'),
            'woocommerce_terms_page_id' => get_option('woocommerce_terms_page_id'),
            'woocommerce_checkout_privacy_policy_text' => get_option('woocommerce_checkout_privacy_policy_text'),
            'woocommerce_checkout_terms_and_conditions_checkbox_text' => get_option('woocommerce_checkout_terms_and_conditions_checkbox_text'),
            #product catalog
            'woocommerce_shop_page_display' => get_option('woocommerce_shop_page_display'),
            'woocommerce_default_catalog_orderby' => get_option('woocommerce_default_catalog_orderby'),
            'woocommerce_category_archive_display' => get_option('woocommerce_category_archive_display'),
            #Product Images
            'woocommerce_single_image_width' => get_option('woocommerce_single_image_width'),
            'woocommerce_thumbnail_image_width' => get_option('woocommerce_thumbnail_image_width'),
            'woocommerce_thumbnail_cropping' => get_option('woocommerce_thumbnail_cropping'),
            'woocommerce_thumbnail_cropping_custom_width' => get_option('woocommerce_thumbnail_cropping_custom_width'),
            'woocommerce_thumbnail_cropping_custom_height' => get_option('woocommerce_thumbnail_cropping_custom_height'),
            #Store Notice
            'woocommerce_demo_store' => get_option('woocommerce_demo_store'),
            'woocommerce_demo_store_notice' => get_option('woocommerce_demo_store_notice'),
        ];
        return $arr;
    }
}
new InstaWP_Sync_Events();