<?php

/**
 * The admin-specific functionality of the plugin.
 *
 * @link       https://instawp.com/
 * @since      1.0
 *
 * @package    instaWP
 * @subpackage instaWP/admin
 */

/**
 * The admin-specific functionality of the plugin.
 *
 * Defines the plugin name, version, and two examples hooks for how to
 * enqueue the admin-specific stylesheet and JavaScript.
 *
 * @package    instaWP
 * @subpackage instaWP/admin
 * @author     instawp team
 */
if ( ! defined('INSTAWP_PLUGIN_DIR') ) {
    die;
}
class InstaWP_Admin_Wizard {

    /**
     * The ID of this plugin.
     *
     * 
     * @access   private
     * @var      string    $plugin_name    The ID of this plugin.
     */
    private $plugin_name;

    /**
     * The version of this plugin.
     *
     * 
     * @access   private
     * @var      string    $version    The current version of this plugin.
     */
    private $version;

    private $screen_ids;

    private $toolbar_menus;

    private $submenus;
    /**
     * Initialize the class and set its properties.
     *
     * 
     * @param      string    $plugin_name       The name of this plugin.
     * @param      string    $version    The version of this plugin.
     */
    public function __construct( $plugin_name, $version ) {
        add_action('instawp_admin_wizard_img',array( $this, 'instawp_admin_wizard_img' ));
        add_action('instawp_admin_wizard_btn',array( $this, 'instawp_admin_wizard_btn' ),10,1);
        add_action('instawp_admin_wizard_prev_btn',array( $this, 'instawp_admin_wizard_prev_btn' ),10,1);
        add_action('instawp_admin_wizard_two_btn',array( $this, 'instawp_admin_wizard_two_btn' ),10,1);
    }

    public function instawp_admin_wizard_img( $class = '' ) {
        ?>
        <img class="main-img <?php echo esc_attr($class); ?>" src="<?php echo esc_url(INSTAWP_PLUGIN_IMAGES_URL.'wizard/step-img.png') ?>">
        <?php 
    }
    public function instawp_admin_wizard_two_btn( $btn_args ) {        
        ?>
        <!-- New button code -->
        <div class="instawp-wizard-btn-wrap button-2">            
            <a class="instawp-wizard-btn figma-design" id="instawp_quick_backup_btn" data-backup-type="1" href="javascript:void(0)">
                <span><?php echo esc_html( $btn_args['button_1']['label'] ); ?></span>
                <p>(<?php echo esc_html( $btn_args['button_1']['desc'] ); ?>)</p>
            </a>
            <a class="instawp-wizard-btn figma-design active" data-backup-type="2" id="instawp_quickbackup_btn" href="javascript:void(0)">
                <span>
                    <?php echo esc_html( $btn_args['button_2']['label'] ); ?>
                </span>
                <p>(<?php echo esc_html( $btn_args['button_2']['desc'] ); ?>) </p>             
            </a>

            <input type="hidden" id="instawp_backup_type" value="2">
            <input type="hidden" id="currentTaskId" name="currentTaskId" value="">
        </div>

        <!-- Customize options button -->
        <div class="home-screen-backup-customize-options" id="instawp_customize_wrap">
            <div class="home-screen-backup-customize-icon">
                <span class="dashicons dashicons-admin-generic"></span>
            </div>
            <div class="home-screen-backup-customize-text">
                <?php _e( 'Customize' ); ?>
            </div>
        </div>
        <!-- Customize options button ends -->
        <!-- Customize options checkbox wrap -->
        <div class="home-screen-backup-customize-checkboxes" style="display:none;">
            <div class="home-screen-backup-customize-checkboxes-left">
                <!-- option wrap -->
                <div class="customize-checkbox-wrap">
                    <input type="checkbox" name="instawp_anonymization" id="instawp_anonymization" checked>
                    <label for="instawp_anonymization">
                        <?php _e('Anonymize Data'); ?>
                    </label>
                    <div class="customize-checkboxes-tooltip-wrap">
                        <div class="customize-checkbox-tooltip" title="Anonymize data while staging!"></div>
                    </div>
                </div>
                <!-- option wrap ends -->
            </div>

            <div class="home-screen-backup-customize-checkboxes-right">
                <!-- option wrap -->
                <div class="customize-checkbox-wrap" style="display:none;">
                    <input type="checkbox" name="instawp_anonymization" id="instawp_anonymization">
                    <label for="instawp_anonymization">
                        <?php _e('Anonymize Data'); ?>
                    </label>
                    <div class="customize-checkboxes-tooltip-wrap">
                        <div class="customize-checkbox-tooltip"></div>
                    </div>
                </div>
                <!-- option wrap ends -->
            </div>
        </div>
        <!-- Customize options checkbox wrap ends -->
        <div>
            <button class="instawp_create_stagin_button">
                <?php _e('Create Staging'); ?>
            </button>
        </div>

        <div class="instawp-cancel-backup-btn-wrap">
            <!-- Cancel process -->
            <?php $cancel_nonce = wp_create_nonce( 'cancel_backup' );?>
            <button class="instawp-cancel-backup-btn" data-nonce="<?php echo $cancel_nonce; ?>" id="instawp_cancel_backup_btn" style="display: none;">
                <?php echo esc_html( $btn_args['button_3']['label'] ); ?>
            </button>
            <!-- Cancel process ends -->
        </div>
        <!-- New button code ends -->
        <?php 
    }
    public function instawp_admin_wizard_btn( $btn_args ) {
        $data = '';
        if ( isset($btn_args['data']) ) {
            $data = $btn_args['data'];
        }

        ?>
        <div class="instawp-wizard-btn-wrap">
            <?php
            $api_key = '';
            $instawp_api_options = get_option('instawp_api_options');   
            if( !empty( $instawp_api_options ) ){
               $api_key = $instawp_api_options['api_key']; 
               if( empty( !$api_key ) ){
                ?>
                <a class="instawp-wizard-btn instawp-wizard-btn-js" href="javascript:void(0)" data-<?php echo esc_attr($data) ?> = "<?php echo esc_attr($data); ?>">
                    <?php echo esc_html( $btn_args['label'] ); ?>
                </a>
                <?php
            }
        }else{
            if( $data!='connect' ){
                ?>
                <a class="instawp-wizard-btn instawp-wizard-btn-js" href="javascript:void(0)" data-<?php echo esc_attr($data) ?> = "<?php echo esc_attr($data); ?>">
                    <?php echo esc_html( $btn_args['label'] ); ?>
                </a>
                <?php
            }else{
                $instawp_api_url = InstaWP_Setting::get_api_domain();
                $return_url = urlencode( admin_url('admin.php?page=instawp-connect') );
                $source_url = $instawp_api_url.'/authorize?source=InstaWP Connect&return_url='.$return_url;
                ?>
                <a class="instawp-wizard-btn instawp-wizard-btn-js" href="<?php echo $source_url?>" data-<?php echo esc_attr($data) ?> = "<?php echo esc_attr($data); ?>">
                    <?php echo esc_html( $btn_args['label'] ); ?>
                </a>
                <?php
            }
        }
        ?>            
        <p class="instawp-err-msg"></p>
    </div>
    <?php 
}

public function instawp_admin_wizard_prev_btn( $args ) {
    ?>
    <div class="instawp-wizard-btn-prev-wrap">
        <a class="instawp-wizard-prev-btn" href="javascript:void(0)">
            <svg width="13" height="10" viewBox="0 0 13 10" fill="none" xmlns="http://www.w3.org/2000/svg">
                <path fill-rule="evenodd" clip-rule="evenodd" d="M6.20703 9.70698C6.0195 9.89445 5.76519 9.99977 5.50003 9.99977C5.23487 9.99977 4.98056 9.89445 4.79303 9.70698L0.793031 5.70698C0.60556 5.51945 0.500244 5.26514 0.500244 4.99998C0.500244 4.73482 0.60556 4.48051 0.793031 4.29298L4.79303 0.29298C4.98163 0.110822 5.23423 0.0100274 5.49643 0.0123059C5.75863 0.0145843 6.00944 0.119753 6.19485 0.305162C6.38026 0.49057 6.48543 0.741382 6.4877 1.00358C6.48998 1.26578 6.38919 1.51838 6.20703 1.70698L3.91403 3.99998H11.5C11.7652 3.99998 12.0196 4.10534 12.2071 4.29287C12.3947 4.48041 12.5 4.73476 12.5 4.99998C12.5 5.2652 12.3947 5.51955 12.2071 5.70709C12.0196 5.89462 11.7652 5.99998 11.5 5.99998H3.91403L6.20703 8.29298C6.3945 8.48051 6.49982 8.73482 6.49982 8.99998C6.49982 9.26514 6.3945 9.51945 6.20703 9.70698Z" fill="#005E54"/>
            </svg> Back
        </a>
    </div>
    <div class="limit_notice"></div>
    <?php
}

}