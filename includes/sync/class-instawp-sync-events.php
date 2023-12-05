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
    private $InstaWP_db;
    private $tables;

    public function __construct() {
        global $wpdb;

        $this->wpdb = $wpdb;

		if ( ! InstaWP_Sync_Helpers::can_sync() ) {
			return;
		}

		// Post Actions.
        add_action( 'save_post', [ $this, 'save_post' ], 10, 3 );
        add_action( 'delete_post', [ $this, 'delete_post' ], 10, 2 );
        add_action( 'transition_post_status', [ $this, 'transition_post_status' ], 10, 3 );

        // Media Actions.
        add_action( 'add_attachment', [ $this, 'add_attachment' ] );
        add_action( 'attachment_updated', [ $this, 'attachment_updated' ], 10, 3 );

        // Plugin and Theme actions
        add_action( 'upgrader_process_complete', [ $this,'install_update_action' ], 10, 2 );
        add_action( 'activated_plugin', [ $this,'activate_plugin' ], 10, 2 );
        add_action( 'deactivated_plugin', [ $this,'deactivate_plugin' ] ,10, 2 );
        add_action( 'deleted_plugin', [ $this,'delete_plugin' ] ,10, 2 );
        add_action( 'switch_theme', [ $this,'switch_theme' ], 10, 3 );
        add_action( 'deleted_theme', [ $this,'delete_theme' ], 10, 2 );

		// Term actions
	    add_action( 'created_term', [ $this, 'create_taxonomy' ], 10, 3 );
	    add_action( 'edited_term', [ $this, 'edit_taxonomy' ], 10, 3 );
	    add_action( 'delete_term', [ $this, 'delete_taxonomy' ], 10, 4 );

	    // User actions
	    add_action( 'user_register', [$this,'user_register' ], 10, 2 );
	    add_action( 'delete_user', [ $this,'delete_user' ], 10, 3 );
	    add_action( 'profile_update', [ $this,'profile_update' ], 10, 3 );

		// Update option
	    add_action( 'added_option', [ $this,'added_option' ], 10, 2 );
	    add_action( 'updated_option', [ $this,'updated_option' ], 10, 3 );
	    add_action( 'deleted_option', [ $this,'deleted_option' ] );

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
	 * Function for `wp_insert_post` action-hook.
	 *
	 * @param int          $post_id     Post ID.
	 * @param WP_Post      $post        Post object.
	 * @param bool         $update      Whether this is an existing post being updated.
	 *
	 * @return void
	 */
	public function save_post( $post_id, $post, $update ) {

		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}

		// Check auto save or revision.
		if ( wp_is_post_autosave( $post_id ) || wp_is_post_revision( $post_id ) ) {
			return;
		}

		// Check post status auto draft.
		if ( in_array( $post->post_status, [ 'auto-draft', 'trash' ] ) ) {
			return;
		}

		// acf feild group check
		if ( $post->post_type == 'acf-field-group' && $post->post_content == '' ) {
			InstaWP_Sync_Helpers::set_post_reference_id( $post_id );
			return;
		}

		// acf check for acf post type
		if ( in_array( $post->post_type, [ 'acf-post-type','acf-taxonomy' ] ) && $post->post_title == 'Auto Draft' ) {
			return;
		}

		$singular_name = InstaWP_Sync_Helpers::get_post_type_name( $post->post_type );
//		$statement     = $this->wpdb->prepare( "SELECT * FROM " . INSTAWP_DB_TABLE_EVENTS . " WHERE source_id=%d AND status=%s AND event_slug=%s", $post_id, 'pending', 'post_change' );
//		$events        = $this->wpdb->get_results( $statement );
//
//		foreach( $events as $event ) {
//			$this->wpdb->query( $this->wpdb->prepare( "DELETE FROM " . INSTAWP_DB_TABLE_EVENTS . " WHERE id=%d", $event->id ) );
//		}

		if ( $update && strtotime( $post->post_modified_gmt ) > strtotime( $post->post_date_gmt ) ) {
			$this->handle_post_events( sprintf( __('%s modified', 'instawp-connect'), $singular_name ), 'post_change', $post );
		}
	}

	/**
	 * Function for `after_delete_post` action-hook.
	 *
	 * @param int     $post_id Post ID.
	 * @param WP_Post $post   Post object.
	 *
	 * @return void
	 */
	public function delete_post( $post_id, $post ) {
//		$statement = $this->wpdb->prepare( "SELECT * FROM " . INSTAWP_DB_TABLE_EVENTS . " WHERE source_id=%d AND status=%s", $post_id, 'pending' );
//		$events    = $this->wpdb->get_results( $statement );
//
//		if ( ! empty( $events ) ) {
//			foreach ( $events as $event ) {
//				$this->wpdb->query( $this->wpdb->prepare( "DELETE FROM " . INSTAWP_DB_TABLE_EVENTS . " WHERE id=%d", $event->id ) );
//			}
//			return;
//		}

		if ( get_post_type( $post_id ) !== 'revision' ) {
			$event_name = sprintf( __('%s deleted', 'instawp-connect' ), InstaWP_Sync_Helpers::get_post_type_name( $post->post_type ) );
			$this->handle_post_events( $event_name, 'post_delete', $post );
		}
	}

	/**
	 * Fire a callback only when my-custom-post-type posts are transitioned to 'publish'.
	 *
	 * @param string  $new_status New post status.
	 * @param string  $old_status Old post status.
	 * @param WP_Post $post       Post object.
	 */
	public function transition_post_status( $new_status, $old_status, $post ) {
		if ( $new_status === 'trash' && $new_status !== $old_status && $post->post_type !== 'customize_changeset' ) {
			$event_name = sprintf( __( '%s trashed', 'instawp-connect' ), InstaWP_Sync_Helpers::get_post_type_name( $post->post_type ) );
			$this->handle_post_events( $event_name, 'post_trash', $post );
		}

		if ( $new_status === 'draft' && $old_status === 'trash' ) {
			$event_name = sprintf( __( '%s restored', 'instawp-connect' ), InstaWP_Sync_Helpers::get_post_type_name( $post->post_type ) );
			$this->handle_post_events( $event_name, 'untrashed_post', $post );
		}

		if ( $old_status === 'auto-draft' && $new_status !== $old_status ) {
			$event_name = sprintf( __( '%s created', 'instawp-connect' ), InstaWP_Sync_Helpers::get_post_type_name( $post->post_type ) );
			$this->handle_post_events( $event_name, 'post_new', $post );
		}
	}

	/**
	 * Function for `add_attachment` action-hook
	 *
	 * @param $post_id
	 * @return void
	 */
	public function add_attachment( $post_id ) {
		$event_name = esc_html__( 'Media created', 'instawp-connect' );
		$this->handle_post_events( $event_name, 'post_new', $post_id );
	}

	/**
	 * Function for `attachment_updated` action-hook
	 *
	 * @param $post_id
	 * @param $post_after
	 * @param $post_before
	 * @return void
	 */
	public function attachment_updated( $post_id, $post_after, $post_before ) {
		$event_name = esc_html__('Media updated', 'instawp-connect' );
		$this->handle_post_events( $event_name, 'post_change', $post_after );
	}

	/**
	 * Function for `upgrader_process_complete` action-hook.
	 *
	 * @param WP_Upgrader $upgrader   WP_Upgrader instance. In other contexts this might be a Theme_Upgrader, Plugin_Upgrader, Core_Upgrade, or Language_Pack_Upgrader instance.
	 * @param array       $hook_extra Array of bulk item update data.
	 *
	 * @return void
	 */
	public function install_update_action( $upgrader, $hook_extra ) {
		if ( empty( $hook_extra['type'] ) || empty( $hook_extra['action'] ) ) {
			return;
		}

		if ( ! in_array( $hook_extra['action'], [ 'install', 'update' ] ) ) {
			return;
		}

		$event_slug = $hook_extra['type'] . '_' . $hook_extra['action'];
		$event_name = sprintf( esc_html__('%s %s%s', 'instawp-connect'), ucfirst( $hook_extra['type'] ), $hook_extra['action'], $hook_extra['action'] == 'update'? 'd' : 'ed' );

		// hooks for theme and record the event
		if ( $upgrader instanceof Theme_Upgrader && $hook_extra['type'] === 'theme' ) {
			$destination_name = $upgrader->result['destination_name'];
			$theme            = wp_get_theme( $destination_name );

			if ( $theme->exists() ) {
				$details = [
					'name'       => $theme->display( 'Name' ),
					'stylesheet' => $theme->get_stylesheet(),
					'data'       => $upgrader->new_theme_data ?? [],
				];
				$this->parse_plugin_theme_event( $event_name, $event_slug, $details, 'theme' );
			}
		}

		// hooks for plugins and record the plugin.
		if ( $upgrader instanceof Plugin_Upgrader && $hook_extra['type'] === 'plugin' ) {
			if ( $hook_extra['action'] === 'install' && ! empty( $upgrader->new_plugin_data ) ) {
				$plugin_data = $upgrader->new_plugin_data;
			} else if ( $hook_extra['action'] === 'update' && ! empty( $upgrader->skin->plugin_info ) ) {
				$plugin_data = $upgrader->skin->plugin_info;
			}

			if ( ! empty( $plugin_data ) ) {
				$post_slug = ! empty( $_POST['slug'] ) ? sanitize_text_field( $_POST['slug'] ) : null;
				$slug      = empty( $plugin_data['TextDomain'] ) ? ( $post_slug ?? $plugin_data['TextDomain'] ) : $plugin_data['TextDomain'];
				$details   = [
					'name' => $plugin_data['Name'],
					'slug' => $slug,
					'data' => $plugin_data
				];
				$this->parse_plugin_theme_event( $event_name, $event_slug, $details, 'plugin' );
			}
		}
	}

	/**
	 * Function for `deactivated_plugin` action-hook.
	 *
	 * @param string $plugin Path to the plugin file relative to the plugins directory.
	 * @param bool   $network_deactivating Whether the plugin is deactivated for all sites in the network or just the current site. Multisite only.
	 *
	 * @return void
	 */
	public function deactivate_plugin( $plugin, $network_wide ) {
		if ( $plugin !== 'instawp-connect/instawp-connect.php' ) {
			$this->parse_plugin_theme_event( __('Plugin deactivated', 'instawp-connect' ), 'deactivate_plugin', $plugin, 'plugin' );
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
	public function activate_plugin( $plugin, $network_wide ) {
		if ( $plugin !== 'instawp-connect/instawp-connect.php' ) {
			$this->parse_plugin_theme_event( __('Plugin activated', 'instawp-connect' ), 'activate_plugin', $plugin, 'plugin' );
		}
	}

	/**
	 * Function for `deleted_plugin` action-hook.
	 *
	 * @param string $plugin Path to the plugin file relative to the plugins directory.
	 *
	 * @return void
	 */
	public function delete_plugin( $plugin, $deleted ) {
		if ( $deleted && $plugin !== 'instawp-connect/instawp-connect.php' ) {
			$this->parse_plugin_theme_event( __( 'Plugin deleted', 'instawp-connect' ), 'deleted_plugin', $plugin, 'plugin' );
		}
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
	public function switch_theme( $new_name, $new_theme, $old_theme ) {
		$details    = [
			'name'       => $new_name,
			'stylesheet' => $new_theme->get_stylesheet(),
			'Paged'      => ''
		];
		$event_name = sprintf( __('Theme switched from %s to %s', 'instawp-connect' ), $old_theme->get_stylesheet(), $new_theme->get_stylesheet() );
		$this->parse_plugin_theme_event( $event_name, 'switch_theme', $details, 'theme' );
	}

	/**
	 * Function for `deleted_theme` action-hook.
	 *
	 * @param string $stylesheet Stylesheet of the theme to delete.
	 * @param bool   $deleted    Whether the theme deletion was successful.
	 *
	 * @return void
	 */
	public function delete_theme( $stylesheet, $deleted ) {
		$details = [
			'name'       => ucfirst( $stylesheet ),
			'stylesheet' => $stylesheet,
			'Paged'      => ''
		];
		if ( $deleted ) {
			$this->parse_plugin_theme_event( __( 'Theme deleted', 'instawp-connect' ), 'deleted_theme', $details, 'theme' );
		}
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
	public function create_taxonomy( $term_id, $tt_id, $taxonomy ) {
		$term_details = ( array ) get_term( $term_id, $taxonomy );
		$event_name   = sprintf( __('%s created', 'instawp-connect'), ucfirst( $taxonomy ) );

		$this->insert_update_event( $event_name, 'create_taxonomy', $taxonomy, $term_id, $term_details['name'], $term_details );
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
	public function edit_taxonomy( $term_id, $tt_id, $taxonomy ) {
		$term_details = ( array ) get_term( $term_id, $taxonomy );
		$event_name   = sprintf( __('%s modified', 'instawp-connect'), ucfirst( $taxonomy ) );

		$this->insert_update_event( $event_name, 'edit_taxonomy', $taxonomy, $term_id, $term_details['name'], $term_details );
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
	public function delete_taxonomy( $term, $tt_id, $taxonomy, $deleted_term ) {
		$event_name = sprintf( __('%s deleted', 'instawp-connect' ), ucfirst( $taxonomy ) );

		$this->insert_update_event( $event_name, 'delete_taxonomy', $taxonomy, $term, $deleted_term->name, $deleted_term );
	}

    /**
     * Function for `user_register` action-hook.
     * 
     * @param int   $user_id  User ID.
     * @param array $userdata The raw array of data passed to wp_insert_user().
     *
     * @return void
     */
    public function user_register( $user_id, $userdata ) {
        if ( empty( $userdata ) ) {
	        return;
        }

        $event_name = __( 'New user registered', 'instawp-connect' );
        $user       = get_user_by( 'id', $user_id );

        $userdata['user_registered']     = $user->data->user_registered;
        $userdata['user_activation_key'] = $user->data->user_activation_key;

        InstaWP_Sync_Helpers::set_user_reference_id( $user_id );
        $details = [ 'user_data' => $userdata, 'user_meta' => get_user_meta( $user_id), 'db_prefix'=> $this->wpdb->prefix ];

        $this->insert_update_event( $event_name, 'user_register', 'users', $user_id, $userdata['user_login'], $details );
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
    public function delete_user( $id, $reassign, $user ) {
        $event_name = __('User deleted', 'instawp-connect');
        $title      = $user->data->user_login;
        $details    = [ 'user_data' => get_userdata( $id ), 'user_meta' => get_user_meta( $id ) ];

        $this->insert_update_event( $event_name, 'delete_user', 'users', $id, $title, $details );
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
    public function profile_update( $user_id, $old_user_data, $userdata ) {
        if ( ! empty( $userdata ) && isset( $_POST['submit'] ) ) {
            $event_name = __( 'User updated', 'instawp-connect' );
	        InstaWP_Sync_Helpers::set_user_reference_id( $user_id );

            $userData = InstaWP_Sync_DB::getByInCondition( $this->wpdb->prefix . 'users', [ 'ID' => $user_id ] );
            if ( isset( $userData[0] ) ) {
                $details = [
					'user_data' => $userData[0],
					'user_meta' => get_user_meta( $user_id ),
					'role'      => $userdata['role'],
					'db_prefix' => $this->wpdb->prefix
                ];

				$this->insert_update_event( $event_name, 'profile_update', 'users', $user_id, $userdata['user_login'], $details );
            }
        }
    }

	public function added_option( $option, $value ) {
		if ( $this->can_modify_option( $option ) ) {
			$this->insert_update_event( __( 'Option added', 'instawp-connect' ), 'add_option', 'option', '', ucfirst( str_replace( [ '-', '_' ], ' ', $option ) ), [ $option => $value ] );
		}
	}

	public function updated_option( $option, $old_value, $value ) {
		if ( $this->can_modify_option( $option ) ) {
			$this->insert_update_event( __( 'Option updated', 'instawp-connect' ), 'update_option', 'option', '', ucfirst( str_replace( [ '-', '_' ], ' ', $option ) ), [ $option => $value ] );
		}
	}

	public function deleted_option( $option ) {
		if ( $this->can_modify_option( $option ) ) {
			$this->insert_update_event( __( 'Option deleted', 'instawp-connect' ), 'delete_option', 'option', '', ucfirst( str_replace( [ '-', '_' ], ' ', $option ) ), $option );
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
	public function save_widget_action( $id, $sidebar_id, $request, $creating ) {
		$event_name   = 'widget block';
		$event_slug   = 'widget_block';
		$title        = 'widgets update';
		$widget_block = get_option('widget_block' );
		$media        = InstaWP_Sync_Helpers::get_media_from_content( maybe_serialize( $widget_block ) );
		$details      = [ 'widget_block' => $widget_block, 'media' => $media ];
		$rel          = InstaWP_Sync_DB::getByInCondition( INSTAWP_DB_TABLE_EVENTS, [ 'event_slug' => 'widget_block' ] );
		$event_id     = ! empty( $rel ) ? reset( $rel )->id : null;

		$this->insert_update_event( $event_name, $event_slug, 'widget', $sidebar_id, $title, $details, $event_id );
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
            $this->insert_update_event($event_name, $event_slug, $event_type, $source_id, $title, $details);
        }
    }

	/**
     * Attribute added (hook).
     *
     * @param int   $source_id   Added attribute ID.
     * @param array $details Attribute data.
     */
    public function attribute_added_action_callback($source_id, $details) {
        $event_slug = 'woocommerce_attribute_added';
        $event_name = __('Woocommerce attribute', 'instawp-connect');
        $this->parse_plugin_theme_event($event_name, $event_slug, $details, 'woocommerce_attribute', $source_id);
    }

    /**
     * Attribute Updated (hook).
     *
     * @param int   $source_id   Updated attribute ID.
     * @param array $details Attribute data.
     */
    public function attribute_updated_action_callback( $source_id, $details ) {
        $event_name = __('Woocommerce attribute', 'instawp-connect');
		$event_id   = InstaWP_Sync_DB::existing_update_events(INSTAWP_DB_TABLE_EVENTS, 'woocommerce_attribute_updated', $source_id);

		$this->parse_plugin_theme_event( $event_name, 'woocommerce_attribute_updated', $details, 'woocommerce_attribute_updated', $source_id, $event_id );
    }

    /**
     * Attribute Deleted (hook).
     *
     * @param int   $source_id   Deleted attribute ID.
     * @param array $details Attribute data.
     */
    public function attribute_deleted_action_callback( $source_id, $details ) {
        $event_slug = 'woocommerce_attribute_deleted';
        $event_name = __('Woocommerce attribute', 'instawp-connect');
        $this->parse_plugin_theme_event($event_name, $event_slug, $details, 'woocommerce_attribute_deleted', $source_id);
    }

	/**
     * Function parse_plugin_theme_event
     * @param $event_name
     * @param $event_slug
     * @param $details
     * @param $type
     * @param $source_id
     * @return void
     */
    private function parse_plugin_theme_event( $event_name, $event_slug, $details, $type, $source_id = '', $event_id = null ) {
	    switch ( $type ) {
		    case 'plugin':
			    if ( ! empty( $details ) && is_array( $details ) ) {
				    $title     = $details['name'];
				    $source_id = $details['slug'];
			    } else {
				    $source_id = basename( $details, '.php' );
				    if ( ! function_exists( 'get_plugin_data' ) ) {
					    require_once( ABSPATH . 'wp-admin/includes/plugin.php' );
				    }
				    $plugin_data = get_plugin_data( WP_PLUGIN_DIR . '/' . $details );
				    if ( $plugin_data['Name'] != '' ) {
					    $title     = $plugin_data['Name'];
				    } else if ( $plugin_data['TextDomain'] != '' ) {
					    $title = $plugin_data['TextDomain'];
				    } else {
					    $title = $details;
				    }
			    }

//				if ( $event_slug === 'deleted_plugin' ) {
//					$statement = $this->wpdb->prepare( "SELECT * FROM " . INSTAWP_DB_TABLE_EVENTS . " WHERE source_id=%s AND status=%s", $source_id, 'pending' );
//					$events    = $this->wpdb->get_results( $statement );
//
//					if ( ! empty( $events ) ) {
//						foreach ( $events as $event ) {
//							$this->wpdb->query( $this->wpdb->prepare( "DELETE FROM " . INSTAWP_DB_TABLE_EVENTS . " WHERE id=%d", $event->id ) );
//						}
//						return;
//					}
//				}
			    break;
		    case 'woocommerce_attribute':
		    case 'woocommerce_attribute_updated':
		        $title = $details['attribute_label'];
			    break;
		    case 'woocommerce_attribute_deleted':
			    $title = 'WooCommerce Attribute Deleted (' . $details . ')';
			    break;
		    default:
			    $title = $details['name'];
	    }
		$this->insert_update_event( $event_name, $event_slug, $type, $source_id, $title, $details, $event_id );
    }

	/**
	 * update/insert event data
	 */
	private function insert_update_event( $event_name = null, $event_slug = null, $event_type = null, $source_id = null, $title = null, $details = null, $event_id = null ) {
		$data = [
			'event_name'     => $event_name,
			'event_slug'     => $event_slug,
			'event_type'     => $event_type,
			'source_id'      => $source_id,
			'title'          => $title,
			'details'        => wp_json_encode( $details ),
			'user_id'        => get_current_user_id(),
			'date'           => date( 'Y-m-d H:i:s' ),
			'prod'           => '',
			'synced_message' => ''
		];

		if ( is_numeric( $event_id ) ) {
			InstaWP_Sync_DB::update( INSTAWP_DB_TABLE_EVENTS, $data, $event_id );
		} else {
			$data['event_hash'] = InstaWP_Tools::get_random_string();
			$data['status']     = 'pending';

			InstaWP_Sync_DB::insert( INSTAWP_DB_TABLE_EVENTS, $data );
		}
	}

    /**
     * Function for `handle_post_events`
     *
     * @param $event_name
     * @param $event_slug
     * @param $post
     * @return void
     */
    private function handle_post_events( $event_name = null, $event_slug = null, $post = null ) {
        $post               = get_post( $post );
        $post_parent_id     = $post->post_parent;
        $post_content       = $post->post_content ?? '';
        $featured_image_id  = get_post_thumbnail_id( $post->ID );
        $featured_image_url = $featured_image_id ? wp_get_attachment_image_url( $featured_image_id, 'post-thumbnail' ) : false;
        $event_type         = get_post_type( $post );
        $title              = $post->post_title ?? '';
        $taxonomies         = $this->get_taxonomies_items( $post->ID );
        $media              = InstaWP_Sync_Helpers::get_media_from_content( $post_content );
        $elementor_css      = $this->get_elementor_css( $post->ID );

        #if post type products then get product gallery
        $product_gallery = ( $event_type === 'product' ) ? $this->get_product_gallery( $post->ID ) : '';

        #manage custom post metas
	    InstaWP_Sync_Helpers::set_post_reference_id( $post->ID );
	    InstaWP_Sync_Helpers::set_post_reference_id( $featured_image_id );

        $data = [
            'content'         => $post_content,
            'posts'           => $post,
            'postmeta'        => get_post_meta( $post->ID ),
            'featured_image'  => [
                'featured_image_id'  => $featured_image_id,
                'featured_image_url' => $featured_image_url,
                'media'              => $featured_image_id > 0 ? get_post( $featured_image_id ) : [],
                'media_meta'         => $featured_image_id > 0 ? get_post_meta( $featured_image_id ) : [],
            ],
            'taxonomies'      => $taxonomies,
            'media'           => $media,
            'elementor_css'   => $elementor_css,
            'product_gallery' => $product_gallery
        ];

        #assign parent post
        if ( $post_parent_id > 0 ) {
            $post_parent = get_post( $post_parent_id );

            if ( $post_parent->post_status !== 'auto-draft' ) {
	            InstaWP_Sync_Helpers::set_post_reference_id( $post_parent_id );
                $data = array_merge( $data, [
                    'parent' => [
                        'post'      => $post_parent,
                        'post_meta' => get_post_meta( $post_parent_id ),
                    ]
                ] );
            }
        }

        $this->insert_update_event( $event_name, $event_slug, $event_type, $post->ID, $title, $data );
    }

	private function can_modify_option( $option ) {
		if ( in_array( $option, [ 'cron', 'instawp_api_options' ] ) || strpos( $option, '_transient' ) !== false ) {
			return false;
		}
		return true;
	}

    /**
     * Get taxonomies items
     */
    private function get_taxonomies_items( $post_id ): array {
        $taxonomies = get_post_taxonomies( $post_id );
        $items      = [];

		if ( ! empty ( $taxonomies ) && is_array( $taxonomies ) ) {
            foreach ( $taxonomies as $taxonomy ) {
                $taxonomy_items = get_the_terms( $post_id, $taxonomy );

				if ( ! empty( $taxonomy_items ) && is_array( $taxonomy_items ) ) {
                    foreach ( $taxonomy_items as $k => $item ) {
                        $items[ $item->taxonomy ][ $k ] = ( array ) $item;

                        if ( $item->parent > 0 ) {
                            $items[ $item->taxonomy ][ $k ]['cat_parent'] = ( array ) get_term( $item->parent, $taxonomy );
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
    private function get_elementor_css( $post_id ) {
        $upload_dir = wp_upload_dir();
        $filename   = 'post-' . $post_id . '.css';
        $filePath   = $upload_dir['basedir'] . '/elementor/css/' . $filename;
		$css        = '';

        if ( file_exists( $filePath ) ) {
	        $css = file_get_contents( $filePath );
        }

	    return $css;
    }

	/*
     * Get product gallery images
     */
	private function get_product_gallery( $product_id ): array {
		$product        = new WC_product( $product_id );
		$attachment_ids = $product->get_gallery_image_ids();
		$gallery        = [];

		if ( ! empty( $attachment_ids ) && is_array( $attachment_ids ) ) {
			foreach ( $attachment_ids as $attachment_id ) {
				$url       = wp_get_attachment_url( intval( $attachment_id ) );
				$gallery[] = [
					'id'         => $attachment_id,
					'url'        => $url,
					'media'      => get_post( $attachment_id ),
					'media_meta' => get_post_meta( $attachment_id ),
				];
			}
		}

		return $gallery;
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