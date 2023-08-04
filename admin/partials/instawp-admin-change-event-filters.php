<?php
/**
 * 
 * This file is used for change event traking 
 *
 * @link       https://instawp.com/
 * @since      1.0
 * @package    instaWP
 * @subpackage instaWP/admin
 */
/**
 * This file is used for change event traking 
 * 
 * @since      1.0
 * @package    instawp
 * @subpackage instawp/admin
 * @author     instawp team
 */
if (!defined('INSTAWP_PLUGIN_DIR')) {
    die;
}

require_once INSTAWP_PLUGIN_DIR . '/includes/class-instawp-db.php';

class InstaWP_Change_Event_Filters
{
    private $wpdb;

    private $InstaWP_db;

    private $tables;

    public function __construct() {
        global $wpdb;

        $this->wpdb = $wpdb;

        $this->InstaWP_db = new InstaWP_DB();

        $this->tables = $this->InstaWP_db->tables;

        $syncing_status = get_option('syncing_enabled_disabled');
        if (!empty($syncing_status) && $syncing_status == 1) { #if syncing enabled
            #post actions
            add_filter('pre_trash_post', array($this, 'trashPostFilter'), 10, 2);
            add_action('after_delete_post', array($this, 'deletePostFilter'), 10, 2);
            add_action('untrashed_post', array($this, 'untrashPostFilter'), 10, 3);
            add_action('wp_after_insert_post', array($this, 'savePostFilter'), 10, 4);
            #plugin actions
            //add_action( 'activated_plugin', array( $this,'activatePluginAction'),10, 2 );
            //add_action( 'deactivated_plugin', array( $this,'deactivatePluginAction'),10, 2 );
            #add_action( 'upgrader_process_complete', array( $this,'upgradePluginAction'),10, 2);
            #theme actions
            // add_action( 'switch_theme', array( $this,'switchThemeAction'), 10, 3 );
            // add_action( 'deleted_theme', array( $this,'deletedThemeAction'), 10, 2 );
            // add_action( 'install_themes_new', array( $this,'installThemesNewAction') );
            // add_action( 'install_themes_upload', array( $this,'installThemesUploadAction') );
            // add_action( 'install_themes_updated', array( $this,'installThemesUpdatedAction') );
            #taxonomy actions         
            // $tax_rel = $this->InstaWP_db->getDistinictCol($this->wpdb->prefix.'term_taxonomy','taxonomy');
            // $taxonomies = [];
            // if(!empty($tax_rel)){
            //     foreach($tax_rel as $tax){
            //         $taxonomies[$tax->taxonomy] = $tax->taxonomy;
            //     }
            //     if(!empty($taxonomies) && is_array($taxonomies)){
            //         foreach($taxonomies as $taxonomy){
            //             add_action( 'created_'.$taxonomy, array( $this,'createTaxonomyAction'), 10, 3 );
            //             add_action( 'delete_'.$taxonomy, array( $this,'deleteTaxonomyAction'), 10, 4 );
            //             add_action( 'edit_'.$taxonomy, array( $this,'editTaxonomyAction'), 10, 3 );
            //         } 
            //     }
            // }
            #Customizer 
            //add_action( 'customize_save_after',array($this,'customizeSaveAfter'));
            #Woocommerce  
            // add_action( 'woocommerce_attribute_added', array($this,'attribute_added_action_callback'), 10, 2 );
            // add_action( 'woocommerce_attribute_updated', array($this,'attribute_updated_action_callback'), 10, 2 );
            // add_action( 'woocommerce_attribute_deleted', array($this,'attribute_deleted_action_callback'), 10, 2 );
            #users
            add_action( 'user_register', array($this,'user_register_action'), 10, 2 );
            add_action( 'delete_user', array($this,'delete_user_action'), 10, 3 );
            add_action( 'profile_update', array($this,'profile_update_action'), 10, 3 );
            #Widgets
            //add_action( 'rest_after_save_widget', array($this,'save_widget_action'), 10, 4 );
        }
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
    public function save_widget_action($id, $sidebar_id, $request, $creating) {
        $event_name = 'widget block';
        $event_slug = 'widget_block';
        $title = 'widgets update';
        $widget_block = get_option('widget_block');
        $media = $this->get_media_from_content(serialize($widget_block));
        $details = json_encode(['widget_block' => $widget_block, 'media' => $media]);
        $rel = $this->InstaWP_db->get_with_condition($this->tables['ch_table'], 'event_slug', 'widget_block');
        if (empty($rel)) {
            $this->eventDataAdded($event_name, $event_slug, 'widget', $sidebar_id, $title, $details);
        } else {
            $rel = reset($rel);
            $this->updateEvents($event_name, $event_slug, 'widget', $sidebar_id, $title, $details, 'id', $rel->id);
        }
    }
    /**
     * Update events
     */
    function updateEvents($event_name = null, $event_slug = null, $event_type = null, $source_id = null, $title = null, $details = null, $key = null, $val = null) {
        $uid = get_current_user_id();
        $date = date('Y-m-d H:i:s');
        $data = [
            'event_name' => $event_name,
            'event_slug' => $event_slug,
            'event_type' => 'widget',
            'source_id' => $source_id,
            'title' => $title,
            'details' => $details,
            'user_id' => $uid,
            'date' => $date,
            'prod' => '',
            'status' => 'pending',
            'synced_message' => ''
        ];
        $this->InstaWP_db->_update($this->tables['ch_table'], $data, $key, $val);
    }
    /**
     * Function for `user_register` action-hook.
     * 
     * @param int   $user_id  User ID.
     * @param array $userdata The raw array of data passed to wp_insert_user().
     *
     * @return void
     */
    public function user_register_action($user_id, $userdata) {
        if ( ! empty( $userdata ) ) {
            $event_slug = 'user_register';
            $event_name = __( 'New user registered', 'instawp-connect' );
            $user = get_user_by( 'id', $user_id );
            $userdata['user_registered'] = $user->data->user_registered;
            $userdata['user_activation_key'] = $user->data->user_activation_key;
            $this->_prepare_user_metas( $user_id );
            $details = json_encode(['user_data' => $userdata, 'user_meta' => get_user_meta($user_id), 'db_prefix'=> $this->wpdb->prefix]);
            $this->eventDataAdded($event_name, $event_slug, 'users', $user_id, $userdata['user_login'], $details);
        }
    }
    /**
     * Function for `delete_user` action-hook.
     * 
     * @param int      $id       ID of the user to delete.
     * @param int|null $reassign ID of the user to reassign posts and links to.
     * @param WP_User  $user     WP_User object of the user to delete.
     *
     * @return void
     */
    public function delete_user_action( $id, $reassign, $user ) {
        $event_slug = 'delete_user';
        $event_name = __('User deleted', 'instawp-connect');
        $title      = $user->data->user_login;
        $details    = json_encode(['user_data' => get_userdata($id), 'user_meta' => get_user_meta($id)]);
        $this->eventDataAdded($event_name, $event_slug, 'users', $id, $title, $details);
    }
    /**
     * Function for `profile_update` action-hook.
     * 
     * @param int     $user_id       User ID.
     * @param WP_User $old_user_data Object containing user's data prior to update.
     * @param array   $userdata      The raw array of data passed to wp_insert_user().
     *
     * @return void
     */
    public function profile_update_action($user_id, $old_user_data, $userdata) {
        if (!empty($userdata)) {
            $event_slug = 'profile_update';
            $event_name = __('User updated', 'instawp-connect');
            $this->_prepare_user_metas( $user_id );
            $userData = $this->InstaWP_db->get_with_condition($this->wpdb->prefix . 'users', 'ID', $user_id);
            if( isset( $userData[0] ) ) {
                $details = json_encode(['user_data' => $userData[0], 'user_meta' => get_user_meta($user_id), 'role' => $userdata['role'], 'db_prefix'=> $this->wpdb->prefix]);
                $this->eventDataAdded($event_name, $event_slug, 'users', $user_id, $userdata['user_login'], $details);
            }
        }
    }
    /**
     * Customizer settings
     */
    function customizeSaveAfter($manager) {
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
        $details = json_encode($data);
        $customizer = $this->InstaWP_db->checkCustomizerChanges($this->tables['ch_table']);
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
                $this->tables['ch_table'],
                $data,
                array('id' => $customize->id)
            );
        } else {
            $this->eventDataAdded($event_name, $event_slug, $event_type, $source_id, $title, $details);
        }
    }
    /**
     * Attribute added (hook).
     *
     * @param int   $source_id   Added attribute ID.
     * @param array $details Attribute data.
     */
    function attribute_added_action_callback($source_id, $details) {
        $event_slug = 'woocommerce_attribute_added';
        $event_name = __('Woocommerce attribute', 'instawp-connect');
        $this->pluginThemeEvents($event_name, $event_slug, $details, 'woocommerce_attribute', $source_id);
    }
    /**
     * Attribute Updated (hook).
     *
     * @param int   $source_id   Updated attribute ID.
     * @param array $details Attribute data.
     */
    function attribute_updated_action_callback($source_id, $details) {
        $event_slug = 'woocommerce_attribute_updated';
        $event_name = __('Woocommerce attribute', 'instawp-connect');
        if (!empty($source_id)) {
            $existing_update_events = $this->InstaWP_db->existing_update_events($this->tables['ch_table'], 'woocommerce_attribute_updated', $source_id);
            if (!empty($existing_update_events) && $existing_update_events > 0) {
                $this->pluginThemeEventsUpdate($event_name, $event_slug, $details, 'woocommerce_attribute_updated', $source_id, $existing_update_events);
            } else {
                $this->pluginThemeEvents($event_name, $event_slug, $details, 'woocommerce_attribute_updated', $source_id);
            }
        } else {
            $this->pluginThemeEvents($event_name, $event_slug, $details, 'woocommerce_attribute_updated', $source_id);
        }
    }
    /**
     * Attribute Deleted (hook).
     *
     * @param int   $source_id   Deleted attribute ID.
     * @param array $details Attribute data.
     */
    function attribute_deleted_action_callback($source_id, $details) {
        $event_slug = 'woocommerce_attribute_deleted';
        $event_name = __('Woocommerce attribute', 'instawp-connect');
        $this->pluginThemeEvents($event_name, $event_slug, $details, 'woocommerce_attribute_deleted', $source_id);
    }
    /**
     * Function for `edit_(taxonomy)` action-hook.
     * 
     * @param int   $term_id Term ID.
     * @param int   $tt_id   Term taxonomy ID.
     * @param array $args    Arguments passed to wp_update_term().
     *
     * @return void
     */
    function editTaxonomyAction($term_id, $tt_id, $args) {
        $taxonomy = $args['taxonomy'];
        $event_slug = 'edit_taxonomy';
        $title = $args['name'];
        $details = json_encode($args);
        $event_name = sprintf(__('%s modified', 'instawp-connect'), ucfirst($taxonomy));
        $this->eventDataAdded($event_name, $event_slug, $taxonomy, $term_id, $title, $details);
    }
    /**
     * Function for `delete_(taxonomy)` action-hook.
     * 
     * @param int     $term         Term ID.
     * @param int     $tt_id        Term taxonomy ID.
     * @param WP_Term $deleted_term Copy of the already-deleted term.
     * @param array   $object_ids   List of term object IDs.
     *
     * @return void
     */
    function deleteTaxonomyAction($term, $tt_id, $deleted_term, $object_ids) {
        $event_slug = 'delete_taxonomy';
        $taxonomy = $deleted_term->taxonomy;
        $title = $deleted_term->name;
        $details = json_encode($deleted_term);
        $event_name = sprintf(__('%s deleted', 'instawp-connect'), ucfirst($taxonomy));
        $this->eventDataAdded($event_name, $event_slug, $taxonomy, $term, $title, $details);
    }
    /**
     * Function for `created_(taxonomy)` action-hook.
     * 
     * @param int   $term_id Term ID.
     * @param int   $tt_id   Term taxonomy ID.
     * @param array $args    Arguments passed to wp_insert_term().
     *
     * @return void
     */
    public function createTaxonomyAction($term_id, $tt_id, $args) {
        $term = (array) get_term($term_id, $args['category']);
        $taxonomy = $args['taxonomy'];
        $event_slug = 'create_taxonomy';
        $event_name = sprintf(__('%s created', 'instawp-connect'), ucfirst($taxonomy));
        $this->addTaxonomyData($event_name, $event_slug, $term_id, $tt_id, $taxonomy, $term);
    }
    /**
     * Function for `install_themes_updated` action-hook.
     * 
     * @param int $paged Number of the current page of results being viewed.
     *
     * @return void
     */
    function installThemesUpdatedAction($paged) {
        $event_slug = 'install_themes_updated';
        $details = ['Name' => '', 'Stylesheet' => '', 'Paged' => $paged];
        $event_name = __('Install themes updated', 'instawp-connect');
        $this->pluginThemeEvents($event_name, $event_slug, $details, 'theme', '');
    }
    /**
     * Function for `install_themes_upload` action-hook.
     * 
     * @param int $paged Number of the current page of results being viewed.
     *
     * @return void
     */
    function installThemesUploadAction($paged) {
        $event_slug = 'install_themes_upload';
        $details = ['Name' => '', 'Stylesheet' => '', 'Paged' => $paged];
        $event_name = __('Install themes upload', 'instawp-connect');
        $this->pluginThemeEvents($event_name, $event_slug, $details, 'theme', '');
    }
    /**
     * Function for `install_themes_new` action-hook.
     * 
     * @param int $paged Number of the current page of results being viewed.
     *
     * @return void
     */
    function installThemesNewAction($paged) {
        $event_slug = 'install_themes_new';
        $details = ['Name' => '', 'Stylesheet' => '', 'Paged' => $paged];
        $event_name = __('Install themes new', 'instawp-connect');
        $this->pluginThemeEvents($event_name, $event_slug, $details, 'theme', '');
    }
    /**
     * Function for `deleted_theme` action-hook.
     * 
     * @param string $stylesheet Stylesheet of the theme to delete.
     * @param bool   $deleted    Whether the theme deletion was successful.
     *
     * @return void
     */
    function deletedThemeAction($stylesheet, $deleted) {
        $event_slug = 'deleted_theme';
        $details = ['Name' => '', 'Stylesheet' => $stylesheet, 'Paged' => ''];
        $event_name = sprintf(__('Theme %s deleted', 'instawp-connect'), ucfirst($stylesheet));
        $this->pluginThemeEvents($event_name, $event_slug, $details, 'theme', '');
    }
    /**
     * Function for `switch_theme` action-hook.
     * 
     * @param string   $new_name  Name of the new theme.
     * @param WP_Theme $new_theme WP_Theme instance of the new theme.
     * @param WP_Theme $old_theme WP_Theme instance of the old theme.
     *
     * @return void
     */
    function switchThemeAction($new_name, $new_theme, $old_theme) {
        $event_slug = 'switch_theme';
        $details = ['Name' => $new_name, 'Stylesheet' => '', 'Paged' => ''];
        $event_name = sprintf(__('Theme switched from %s to %s', 'instawp-connect'), $old_theme->get_stylesheet(), $new_theme->get_stylesheet());
        $this->pluginThemeEvents($event_name, $event_slug, $details, 'theme', '');
    }
    /**ge
     * Function for `upgrader_process_complete` action-hook.
     * 
     * @param WP_Upgrader $upgrader   WP_Upgrader instance. In other contexts this might be a Theme_Upgrader, Plugin_Upgrader, Core_Upgrade, or Language_Pack_Upgrader instance.
     * @param array       $hook_extra Array of bulk item update data.
     *
     * @return void
     */
    function upgradePluginAction($upgrader, $hook_extra) {
        $event_name = $hook_extra['type'] . '_' . $hook_extra['action'];
        $event_slug = $hook_extra['type'] . '_' . $hook_extra['action'];
        $details = json_encode($hook_extra);
        $this->pluginThemeEvents($event_name, $event_slug, $details, 'plugin', '');
    }
    /**
     * Function for `deactivated_plugin` action-hook.
     * 
     * @param string $plugin Path to the plugin file relative to the plugins directory.
     * @param bool   $network_deactivating Whether the plugin is deactivated for all sites in the network or just the current site. Multisite only.
     *
     * @return void
     */
    public function deactivatePluginAction($plugin, $network_wide) {
        $details = $plugin;
        $event_slug = 'deactivate_plugin';
        $event_name = __('Plugin deactivated', 'instawp-connect');
        if ($details != 'instawp-connect/instawp-connect.php') {
            $this->pluginThemeEvents($event_name, $event_slug, $details, 'plugin', '');
        }
    }
    /**
     * Function for `activated_plugin` action-hook.
     * 
     * @param string $plugin       Path to the plugin file relative to the plugins directory.
     * @param bool   $network_wide Whether to enable the plugin for all sites in the network or just the current site. Multisite only.
     *
     * @return void
     */
    public function activatePluginAction($plugin, $network_wide) {
        $event_slug = 'activate_plugin';
        $event_name = __('Plugin activated', 'instawp-connect');
        if ($plugin != 'instawp-connect/instawp-connect.php') {
            $this->pluginThemeEvents($event_name, $event_slug, $plugin, 'plugin', '');
        }
    }

    /** function pluginThemeEvents
     * @param $event_name
     * @param $event_slug
     * @param $details
     * @param $type
     * @param $source_id
     * @return void
     */
    public function pluginThemeEvents($event_name, $event_slug, $details, $type, $source_id) {
        $uid = get_current_user_id();
        $date = date('Y-m-d H:i:s');
        if ($type == 'plugin') {
            if (!function_exists('get_plugin_data')) {
                require_once(ABSPATH . 'wp-admin/includes/plugin.php');
            }
            $plugin_data = get_plugin_data(WP_PLUGIN_DIR . '/' . $details);
            $title = $plugin_data['Name'];
        } elseif ($type == 'woocommerce_attribute') {
            $title = $details['attribute_label'];
        } elseif ($type == 'woocommerce_attribute_updated') {
            $title = $details['attribute_label'];
        } elseif ($type == 'woocommerce_attribute_deleted') {
            $title = $details;
        } else {
            $title = $details['Name'];
        }
        #Data Array
        $data = [
            'event_name' => $event_name,
            'event_slug' => $event_slug,
            'event_type' => $type,
            'source_id' => $source_id,
            'title' => $title,
            'details' => json_encode($details),
            'user_id' => $uid,
            'date' => $date,
            'prod' => '',
            'status' => 'pending',
            'synced_message' => ''
        ];
        $this->InstaWP_db->insert($this->tables['ch_table'], $data);
    }

    /** function pluginThemeEventsUpdate
     * @param $event_name
     * @param $event_slug
     * @param $details
     * @param $type
     * @param $source_id
     * @param $existing_update_events
     * @return void
     */
    public function pluginThemeEventsUpdate($event_name, $event_slug, $details, $type, $source_id, $existing_update_events) {

        $uid = get_current_user_id();
        $date = date('Y-m-d H:i:s');
        if ($type == 'plugin') {
            if (!function_exists('get_plugin_data')) {
                require_once(ABSPATH . 'wp-admin/includes/plugin.php');
            }
            $plugin_data = get_plugin_data(WP_PLUGIN_DIR . '/' . $details);
            $title = $plugin_data['Name'];
        } elseif ($type == 'woocommerce_attribute') {
            $title = $details['attribute_label'];
        } elseif ($type == 'woocommerce_attribute_updated') {
            $title = $details['attribute_label'];
        } elseif ($type == 'woocommerce_attribute_deleted') {
            $title = 'woocommerce Attribute Deleted(' . $details . ')';
        } else {
            $title = $details['Name'];
        }
        #Data Array
        $data = [
            'event_name' => $event_name,
            'event_slug' => $event_slug,
            'event_type' => $type,
            'source_id' => $source_id,
            'title' => $title,
            'details' => json_encode($details),
            'user_id' => $uid,
            'date' => $date,
            'prod' => '',
            'status' => 'pending',
            'synced_message' => ''
        ];
        $this->wpdb->update(
            $this->tables['ch_table'],
            $data,
            array('id' => $existing_update_events)
        );
    }  
    /**
     * Function for `wp_after_insert_post` action-hook.
     * 
     * @param int          $post_id     Post ID.
     * @param WP_Post      $post        Post object.
     * @param bool         $update      Whether this is an existing post being updated.
     * @param null|WP_Post $post_before Null for new posts, the WP_Post object prior to the update for updated posts.
     *
     * @return void
     */
    public function savePostFilter($post_ID, $post, $update, $post_before) {

        // Check autosave.
        if (wp_is_post_autosave($post_ID)) {
            return $post_ID;
        }

        // Check post revision.
        if (wp_is_post_revision($post_ID)) {
            return $post_ID;
        }
        
        // Check post status auto draft.
        if (in_array($post->post_status, ['auto-draft', 'trash'])) {
            return $post_ID;
        }

        if ($post_before && $post_before->post_status == 'trash') {
            return $post_ID;
        }

        if($post->post_type == 'acf-field-group' && $post->post_content == '') {
            $this->_prepare_metas_for_each_post($post_ID);
            return $post_ID;
        }

        $post_type_singular_name = instawp_get_post_type_singular_name($post->post_type);
        //check post revisions are found 
        $revisions = wp_get_post_revisions($post_ID);

        if (count($revisions) <= 1) {

            $event_name = sprintf(esc_html__('%s created', 'instawp-connect'), $post_type_singular_name);
            $this->addPostData($event_name, 'post_new', $post, $post_ID);

        } else {
            if ($update && ($post->post_type != 'revision')) {
                $event_slug = 'post_change';
                $event_name = sprintf(__('%s modified', 'instawp-connect'), $post_type_singular_name);
                # need to add update traking data once in db
                $this->addPostData($event_name, $event_slug, $post, $post_ID);
            }
        }
    }

    /**
     * Function for `eventDataUpdated`
     *
     * @param $event_name
     * @param $event_slug
     * @param $post
     * @param $post_id
     * @param $id
     * @return void
     */
    public function eventDataUpdated($event_name = null, $event_slug = null, $post = null, $post_id = null, $id = null) {
        $uid = get_current_user_id();
        $date = date('Y-m-d H:i:s');
        $post_id = isset($post_id) ? $post_id : $post->ID;
        $postData = get_post($post_id);
        $post_content = isset($postData->post_content) ? $postData->post_content : '';
        $featured_image_id = get_post_thumbnail_id($post_id);
        $featured_image_url = get_the_post_thumbnail_url($post_id);
        $taxonomies = $this->get_taxonomies_items($post_id);
        $media = $this->get_media_from_content($post_content);
        $elementor_css = $this->get_elementor_css($post_id);
        #if post type products
        if (isset($postData->post_type) && $postData->post_type == 'product') {
            $product_gallery = $this->get_product_gallery($post_id);
        } else {
            $product_gallery = '';
        }
        #manage custom post metas
        $this->_prepare_metas_for_each_post($post_id);
        #Data Array
        $data = [
            'event_name'        => $event_name,
            'event_slug'        => $event_slug,
            'event_type'        => isset($postData->post_type) ? $postData->post_type : '',
            'source_id'         => isset($post_id) ? $post_id : '',
            'title'             => isset($postData->post_title) ? $postData->post_title : '',
            'details'           => json_encode(['content' => $post_content, 'posts' => $postData, 'postmeta' => get_post_meta($post_id), 'featured_image' => ['featured_image_id' => $featured_image_id, 'featured_image_url' => $featured_image_url], 'taxonomies' => $taxonomies, 'media' => $media, 'elementor_css' => $elementor_css, 'product_gallery' => $product_gallery]),
            'user_id'           => $uid,
            'date'              => $date,
            'prod'              => '',
            'status'            => 'pending',
            'synced_message'    => ''
        ];
        $this->wpdb->update(
            $this->tables['ch_table'],
            $data,
            array('id' => $id)
        );
    }
    /**
     * Function for `after_delete_post` action-hook.
     * 
     * @param int     $postid Post ID.
     * @param WP_Post $post   Post object.
     *
     * @return void
     */
    function deletePostFilter($post_id, $post) {
        if (isset($post->post_type) && $post->post_type != 'revision') {
            $event_slug = 'post_delete';
            $event_name = sprintf(__('%s deleted', 'instawp-connect'), instawp_get_post_type_singular_name($post->post_type));
            $this->addPostData($event_name, $event_slug, $post, $post_id);
        }
    }
    /**
     * Function for `pre_trash_post` filter-hook.
     * 
     * @param bool|null $trash Whether to go forward with trashing.
     * @param WP_Post   $post  Post object.
     *
     * @return bool|null
     */
    public function trashPostFilter($trash, $post) {
        if ($post->post_type != 'customize_changeset') {
            $event_slug = 'post_trash';
            $event_name = sprintf(__('%s trashed', 'instawp-connect'), instawp_get_post_type_singular_name($post->post_type));
            $this->addPostData($event_name, $event_slug, $post, null);
        }
    }
    /**
     * Function for `untrashed_post` action-hook.
     * 
     * @param int    $post_id         Post ID.
     * @param string $previous_status The status of the post at the point where it was trashed.
     *
     * @return void
     */
    public function untrashPostFilter($post_id, $previous_status) {
        $post = get_post($post_id);
        $event_name = sprintf(__('%s Restored', 'instawp-connect'), instawp_get_post_type_singular_name($post->post_type));
        $event_slug = 'untrashed_post';
        $this->addPostData($event_name, $event_slug, $post, $post_id);
    }

    /**
     * Function for `addPostData`
     *
     * @param $event_name
     * @param $event_slug
     * @param $post
     * @param $post_id
     * @return void
     */
    public function addPostData($event_name = null, $event_slug = null, $post = null, $post_id = null) {

        //check if the sync is enabled to record
        $syncing_enabled_disabled = get_option('syncing_enabled_disabled', 0);
        if ($syncing_enabled_disabled == 0)
            return;

        $post_id = isset($post_id) ? $post_id : $post->ID;
        $post_parent_id = $post->post_parent;
        // $postData = get_post($post_id);
        $post_content = isset($post->post_content) ? $post->post_content : '';
        $featured_image_id = get_post_thumbnail_id($post_id);
        $featured_image_url = get_the_post_thumbnail_url($post_id);
        $event_type = isset($post->post_type) ? $post->post_type : '';
        $source_id = isset($post_id) ? $post_id : '';
        $title = isset($post->post_title) ? $post->post_title : '';
        $taxonomies = $this->get_taxonomies_items($post_id);
        $media = $this->get_media_from_content($post_content);
        $elementor_css = $this->get_elementor_css($post_id);
        //$post->post_name = $post->post_status == 'trash' ? str_replace('__trashed','', $post->post_name) : $post->post_name;
        #if post type products then get product gallery
        if (isset($post->post_type) && $post->post_type == 'product') {
            $product_gallery = $this->get_product_gallery($post_id);
        } else {
            $product_gallery = '';
        }

        #manage custom post metas
        $this->_prepare_metas_for_each_post($post_id);
        $this->_prepare_metas_for_each_post($featured_image_id);

        $data = [
            'content' => $post_content,
            'posts' => $post,
            'postmeta' => get_post_meta($post_id),
            'featured_image' => [
                'featured_image_id' => $featured_image_id,
                'featured_image_url' => $featured_image_url,
                'media' => $featured_image_id > 0 ? get_post($featured_image_id) : [],
                'media_meta' => $featured_image_id > 0 ? get_post_meta($featured_image_id) : [],
            ],
            'taxonomies' => $taxonomies,
            'media' => $media,
            'elementor_css' => $elementor_css,
            'product_gallery' => $product_gallery
        ];

        #assign parent post
        if ($post_parent_id > 0) {
            $this->_prepare_metas_for_each_post($post_parent_id);
            $data = array_merge($data, [
                'parent' => [
                    'post' => get_post($post_parent_id),
                    'post_meta' => get_post_meta($post_parent_id),
                ]
            ]);
        }

        $details = json_encode($data);
        $this->eventDataAdded($event_name, $event_slug, $event_type, $source_id, $title, $details);
    }
    /*
     * Update post metas
     */
    public function _prepare_metas_for_each_post($post_id) {
        if (get_post_meta($post_id, 'instawp_event_sync_reference_id', true) == '') {
            add_post_meta($post_id, 'instawp_event_sync_reference_id', InstaWP_Tools::get_random_string());
        }
    }

    /*
     * Update user metas
     */
    public function _prepare_user_metas($user_id) {
        if (get_user_meta($user_id, 'instawp_event_user_sync_reference_id', true) == '') {
            add_user_meta($user_id, 'instawp_event_user_sync_reference_id', InstaWP_Tools::get_random_string());
        }
    }

    /*
     * Get product gallery images
     */
    public function get_product_gallery($product_id = null) {
        $product = new WC_product($product_id);
        $attachment_ids = $product->get_gallery_image_ids();
        $gallery = [];
        if (!empty($attachment_ids) && is_array($attachment_ids)) {
            foreach ($attachment_ids as $attachment_id) {
                $url = wp_get_attachment_url(intval($attachment_id));
                $gallery[] = [
                    'id' => $attachment_id,
                    'url' => $url,
                    'media' => get_post($attachment_id),
                    'media_meta' => get_post_meta($attachment_id),
                ];
            }
        }
        return $gallery;
    }
    /**
     * Get media from content 
     */
    public function get_media_from_content($content = null) {
        #find media form content.
        preg_match_all('!(https?:)?//\S+\.(?:jpe?g|jpg|png|gif|mp4|pdf|doc|docx|xls|xlsx|csv|txt|rtf|html|zip|mp3|wma|mpg|flv|avi)!Ui', $content, $match);
        $media = [];
        if (isset($match[0])) {
            $attachment_urls = array_unique($match[0]);
            foreach ($attachment_urls as $attachment_url) {
                if (strpos($attachment_url, $_SERVER['HTTP_HOST']) !== false) {
                    $attachment_id = attachment_url_to_postid($attachment_url);
                    #if(isset($attachment_id) && !empty($attachment_id)){ 
                    #It's check media exist or not 
                    $media[] = [
                        'attachment_url' => $attachment_url,
                        'attachment_id' => $attachment_id,
                        'attachment_media' => get_post($attachment_id),
                        'attachment_media_meta' => get_post_meta($attachment_id),
                    ];
                    #}
                }
            }
        }
        return json_encode($media);
    }
    /**
     * Taxonomy
     */
    public function addTaxonomyData($event_name = null, $event_slug = null, $term_id = null, $tt_id = null, $taxonomy = null, $args = null) {
        $title = $args['name'];
        $details = json_encode($args);
        $this->eventDataAdded($event_name, $event_slug, $taxonomy, $term_id, $title, $details);
    }
    /**
     * add/insert event data
     */
    public function eventDataAdded($event_name = null, $event_slug = null, $event_type = null, $source_id = null, $title = null, $details = null) {
        $uid = get_current_user_id();
        $date = date('Y-m-d H:i:s');
        #Data Array
        $data = [
            'event_name' => $event_name,
            'event_slug' => $event_slug,
            'event_type' => $event_type,
            'source_id' => $source_id,
            'title' => $title,
            'details' => $details,
            'user_id' => $uid,
            'date' => $date,
            'prod' => '',
            'status' => 'pending',
            'synced_message' => ''
        ];
        $this->InstaWP_db->insert($this->tables['ch_table'], $data);
    }
    /**
     * Get taxonomies items
     */
    public function get_taxonomies_items($post_id = null) {
        $taxonomies = get_post_taxonomies($post_id);
        $items = [];
        if (!empty($taxonomies) && is_array($taxonomies)) {
            foreach ($taxonomies as $taxonomy) {
                $taxonomy_items = get_the_terms($post_id, $taxonomy);
                if (!empty($taxonomy_items) && is_array($taxonomy_items)) {
                    foreach ($taxonomy_items as $k=> $item) {
                        $items[$item->taxonomy][$k] = (array) $item;
                        if( $item->parent > 0 ) {
                            $parent = get_term($item->parent, $taxonomy);
                            $items[$item->taxonomy][$k]['cat_parent'] = (array) $parent;
                        }
                    }
                }
            }
        }
        return $items;
    }

    /*
     * get post css from elementor files 'post-{post_id}.css'
     */
    public function get_elementor_css($post_id = null) {
        $upload_dir = wp_upload_dir();
        $filename = 'post-' . $post_id . '.css';
        $filePath = $upload_dir['basedir'] . '/elementor/css/' . $filename;
        if (file_exists($filePath)) {
            $fileData = file_get_contents($filePath);
            return $fileData;
        }
    }
    /**
     * Get Astra Costmizer Setings
     */
    function getAstraCostmizerSetings() {
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
new InstaWP_Change_Event_Filters();