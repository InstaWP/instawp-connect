<?php

if ( ! defined( 'INSTAWP_PLUGIN_DIR' ) ) {
	die;
}

class InstaWP_Setting {

	public static function init_option() {
		$ret = self::get_option( 'instawp_email_setting' );
		if ( empty( $ret ) ) {
			self::set_default_email_option();
		}

		$ret = self::get_option( 'instawp_compress_setting' );
		if ( empty( $ret ) ) {
			self::set_default_compress_option();
		}

		$ret = self::get_option( 'instawp_local_setting' );
		if ( empty( $ret ) ) {
			self::set_default_local_option();
		}

		$ret = self::get_option( 'instawp_upload_setting' );
		if ( empty( $ret ) ) {
			self::set_default_upload_option();
		}

		$ret = self::get_option( 'instawp_common_setting' );
		if ( empty( $ret ) ) {
			self::set_default_common_option();
		}

		// Setting up default
		self::set_api_domain();
	}

	public static function get_plugin_nav_items() {
		
		$is_internal       = isset( $_GET['internal'] ) ? sanitize_text_field( $_GET['internal'] ) : '';
		$instawp_nav_items = array(
			'create'     => array(
				'label' => __( 'Create Staging', 'instawp-connect' ),
				'icon'  => '<svg width="20" class="mr-2" height="20" viewBox="0 0 17 17" fill="none" xmlns="http://www.w3.org/2000/svg"> <path fill-rule="evenodd" clip-rule="evenodd" d="M9.36804 0.883699C9.04694 0.111684 7.95329 0.111684 7.63219 0.883699L5.8014 5.28547L1.04932 5.66644C0.215863 5.73326 -0.122092 6.77337 0.512913 7.31732L4.13349 10.4187L3.02735 15.056C2.83334 15.8693 3.71812 16.5121 4.43167 16.0763L8.50011 13.5913L12.5686 16.0763C13.2821 16.5121 14.1669 15.8693 13.9729 15.056L12.8667 10.4187L16.4873 7.31732C17.1223 6.77337 16.7844 5.73326 15.9509 5.66644L11.1988 5.28547L9.36804 0.883699Z" fill="#9CA3AF"/> </svg>',
			),
			'sites'      => array(
				'label' => __( 'Staging Sites', 'instawp-connect' ),
				'icon'  => '<svg width="20" class="mr-2" height="20" viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg"> <path d="M10.0713 3.78565L9.98153 3.74076C8.3802 2.9401 6.54529 2.73868 4.80841 3.1729L2.5 3.75V12.5L4.80841 11.9229C6.54529 11.4887 8.3802 11.6901 9.98153 12.4908L10.0713 12.5356C11.6406 13.3203 13.4353 13.5299 15.1432 13.1281L17.7384 12.5174C17.5809 11.075 17.5 9.60942 17.5 8.125C17.5 6.65285 17.5795 5.19928 17.7345 3.76835L15.1432 4.37807C13.4353 4.77993 11.6406 4.5703 10.0713 3.78565Z" fill="#9CA3AF"/><path d="M2.5 2.5V3.75M2.5 17.5V12.5M2.5 12.5L4.80841 11.9229C6.54529 11.4887 8.3802 11.6901 9.98153 12.4908L10.0713 12.5356C11.6406 13.3203 13.4353 13.5299 15.1432 13.1281L17.7384 12.5174C17.5809 11.075 17.5 9.60942 17.5 8.125C17.5 6.65285 17.5795 5.19928 17.7345 3.76835L15.1432 4.37807C13.4353 4.77993 11.6406 4.5703 10.0713 3.78565L9.98153 3.74076C8.3802 2.9401 6.54529 2.73868 4.80841 3.1729L2.5 3.75M2.5 12.5V3.75" stroke="#9CA3AF" stroke-width="1.25" stroke-linecap="round" stroke-linejoin="round"/></svg>',
			),
			'manage' => array(
				'label' => __( 'Manage', 'instawp-connect' ),
				'icon'  => '<svg class="mr-2" width="20" height="20" viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M5 4C5 3.44772 4.55228 3 4 3C3.44772 3 3 3.44772 3 4V11.2676C2.4022 11.6134 2 12.2597 2 13C2 13.7403 2.4022 14.3866 3 14.7324V16C3 16.5523 3.44772 17 4 17C4.55228 17 5 16.5523 5 16V14.7324C5.5978 14.3866 6 13.7403 6 13C6 12.2597 5.5978 11.6134 5 11.2676V4Z" fill="#005E54"/><path d="M11 4C11 3.44772 10.5523 3 10 3C9.44772 3 9 3.44772 9 4V5.26756C8.4022 5.61337 8 6.25972 8 7C8 7.74028 8.4022 8.38663 9 8.73244V16C9 16.5523 9.44772 17 10 17C10.5523 17 11 16.5523 11 16V8.73244C11.5978 8.38663 12 7.74028 12 7C12 6.25972 11.5978 5.61337 11 5.26756V4Z" fill="#005E54"/><path d="M16 3C16.5523 3 17 3.44772 17 4V11.2676C17.5978 11.6134 18 12.2597 18 13C18 13.7403 17.5978 14.3866 17 14.7324V16C17 16.5523 16.5523 17 16 17C15.4477 17 15 16.5523 15 16V14.7324C14.4022 14.3866 14 13.7403 14 13C14 12.2597 14.4022 11.6134 15 11.2676V4C15 3.44772 15.4477 3 16 3Z" fill="#005E54"/>
				</svg>',
			),
			'sync'       => array(
				'label' => __( 'Sync (Beta)', 'instawp-connect' ),
				'icon'  => '<svg width="14" class="mr-2" height="16" viewBox="0 0 14 16" fill="none" xmlns="http://www.w3.org/2000/svg"> <path fill-rule="evenodd" clip-rule="evenodd" d="M1.59995 0.800049C2.09701 0.800049 2.49995 1.20299 2.49995 1.70005V3.59118C3.64303 2.42445 5.23642 1.70005 6.99995 1.70005C9.74442 1.70005 12.0768 3.45444 12.9412 5.90013C13.1069 6.36877 12.8612 6.88296 12.3926 7.0486C11.924 7.21425 11.4098 6.96862 11.2441 6.49997C10.6259 4.75097 8.95787 3.50005 6.99995 3.50005C5.52851 3.50005 4.22078 4.20657 3.39937 5.30005H6.09995C6.59701 5.30005 6.99995 5.70299 6.99995 6.20005C6.99995 6.6971 6.59701 7.10005 6.09995 7.10005H1.59995C1.10289 7.10005 0.699951 6.6971 0.699951 6.20005V1.70005C0.699951 1.20299 1.10289 0.800049 1.59995 0.800049ZM1.6073 8.95149C2.07594 8.78585 2.59014 9.03148 2.75578 9.50013C3.37396 11.2491 5.04203 12.5 6.99995 12.5C8.47139 12.5 9.77912 11.7935 10.6005 10.7L7.89995 10.7C7.40289 10.7 6.99995 10.2971 6.99995 9.80005C6.99995 9.30299 7.40289 8.90005 7.89995 8.90005H12.3999C12.6386 8.90005 12.8676 8.99487 13.0363 9.16365C13.2051 9.33243 13.3 9.56135 13.3 9.80005V14.3C13.3 14.7971 12.897 15.2 12.4 15.2C11.9029 15.2 11.5 14.7971 11.5 14.3V12.4089C10.3569 13.5757 8.76348 14.3 6.99995 14.3C4.25549 14.3 1.92309 12.5457 1.05867 10.1C0.893024 9.63132 1.13866 9.11714 1.6073 8.95149Z"/> </svg>',
			),
			'settings'   => array(
				'label' => __( 'Settings', 'instawp-connect' ),
				'icon'  => '<svg width="20" class="mr-2" height="20" viewBox="0 0 16 16" fill="none" xmlns="http://www.w3.org/2000/svg"> <path fill-rule="evenodd" clip-rule="evenodd" d="M9.34035 1.8539C8.99923 0.448767 7.00087 0.448766 6.65975 1.8539C6.43939 2.76159 5.39945 3.19235 4.6018 2.70633C3.36701 1.95396 1.95396 3.36701 2.70633 4.6018C3.19235 5.39945 2.76159 6.43939 1.8539 6.65975C0.448766 7.00087 0.448767 8.99923 1.8539 9.34035C2.76159 9.56071 3.19235 10.6006 2.70633 11.3983C1.95396 12.6331 3.36701 14.0461 4.6018 13.2938C5.39945 12.8077 6.43939 13.2385 6.65975 14.1462C7.00087 15.5513 8.99923 15.5513 9.34035 14.1462C9.56071 13.2385 10.6006 12.8077 11.3983 13.2938C12.6331 14.0461 14.0461 12.6331 13.2938 11.3983C12.8077 10.6006 13.2385 9.56071 14.1462 9.34035C15.5513 8.99923 15.5513 7.00087 14.1462 6.65975C13.2385 6.43939 12.8077 5.39945 13.2938 4.6018C14.0461 3.36701 12.6331 1.95396 11.3983 2.70633C10.6006 3.19235 9.56071 2.76159 9.34035 1.8539ZM8.00005 10.7C9.49122 10.7 10.7 9.49122 10.7 8.00005C10.7 6.50888 9.49122 5.30005 8.00005 5.30005C6.50888 5.30005 5.30005 6.50888 5.30005 8.00005C5.30005 9.49122 6.50888 10.7 8.00005 10.7Z"/> </svg>',
			),
		);

		if ( instawp()->is_staging ) {
			unset( $instawp_nav_items['create'] );
			unset( $instawp_nav_items['manage'] );
		}
 
		$user = get_userdata( get_current_user_id() );
		$allowed_roles 	= ! current_user_can('administrator') ?  InstaWP_Setting::get_option( 'instawp_sync_tab_roles' ) : ['administrator'];
		if( empty( array_intersect( $allowed_roles, $user->roles ) ) ){
			unset( $instawp_nav_items['sync'] );
		}
		

		return apply_filters( 'INSTAWP_CONNECT/Filters/plugin_nav_items', $instawp_nav_items );
	}

	public static function generate_section_field( $field = array() ) {

		$field_id            = self::get_args_option( 'id', $field );
		$field_class         = self::get_args_option( 'class', $field );
		$field_title         = self::get_args_option( 'title', $field );
		$field_type          = self::get_args_option( 'type', $field );
		$field_desc          = self::get_args_option( 'desc', $field );
		$internal            = self::get_args_option( 'internal', $field, false );
		$remote          	 = self::get_args_option( 'remote', $field );
		$event          	 = self::get_args_option( 'event', $field );
		$multiple          	 = self::get_args_option( 'multiple', $field );
		$action          	 = self::get_args_option( 'action', $field );
		$field_placeholder   = self::get_args_option( 'placeholder', $field );
		$field_tooltip       = self::get_args_option( 'tooltip', $field );
		$field_attributes    = self::get_args_option( 'attributes', $field, array() );
		$field_attributes    = ! is_array( $field_attributes ) ? array() : $field_attributes;
		$field_options       = self::get_args_option( 'options', $field, array() );
		$field_options       = ! is_array( $field_options ) ? array() : $field_options;
		$field_default_value = self::get_args_option( 'default', $field );
		$field_label_class   = self::get_args_option( 'label_class', $field );
		$field_parent_class  = self::get_args_option( 'parent_class', $field );
		$field_value         = self::get_option( $field_id, $field_default_value );
		$attributes          = array();

		if ( true === $internal || 1 == $internal ) {
			if ( ! isset( $_REQUEST['internal'] ) || '1' != sanitize_text_field( $_REQUEST['internal'] ) ) {
				return;
			}
		}

		foreach ( $field_attributes as $attribute_key => $attribute_val ) {
			$attributes[] = $attribute_key . '="' . $attribute_val . '"';
		}

		$label_attributes = '';
		$label_class      = 'inline-block text-sm font-medium text-gray-700 mb-3 sm:mt-px sm:pt-2';
		$label_content    = esc_html( $field_title );
		if ( ! empty( $field_tooltip ) ) {
			$label_class      .= ' hint--top hint--large';
			$label_attributes .= ' aria-label="' . $field_tooltip . '"';
			$label_content    .= '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"><path d="M12 22C6.47715 22 2 17.5228 2 12C2 6.47715 6.47715 2 12 2C17.5228 2 22 6.47715 22 12C22 17.5228 17.5228 22 12 22ZM12 20C16.4183 20 20 16.4183 20 12C20 7.58172 16.4183 4 12 4C7.58172 4 4 7.58172 4 12C4 16.4183 7.58172 20 12 20ZM11 7H13V9H11V7ZM11 11H13V17H11V11Z"></path></svg>';
		}

		if ( ! empty( $field_label_class ) ) {
			$label_class .= ' ' . $field_label_class;
		}

		$field_container_class = 'instawp-single-field ' . esc_attr( str_replace( '_', '-', $field_id ) ) . '-field';
		if ( ! empty( $field_parent_class ) ) {
			$field_container_class .= ' ' . $field_parent_class;
		}

		if( $field_type == 'select2' ) {
			$field_container_class .= ' select2-field-wrapper';
		}

		echo '<div class="' . esc_attr( $field_container_class ) . '">';
		echo '<label for="' . esc_attr( $field_id ) . '" class="' . esc_attr( $label_class ) . '"' . $label_attributes . '>' . $label_content . '</label>';

		switch ( $field_type ) {
			case 'text':
			case 'number':
			case 'email':
			case 'url':
				$css_class = 'block rounded-md border-grayCust-350 shadow-sm focus:border-primary-900 focus:ring-1 focus:ring-primary-900 sm:text-sm';
				$css_class = $field_class ? $css_class . ' ' . trim( $field_class ) : 'w-full ' . $css_class;

				echo '<input ' . implode( ' ', $attributes ) . ' type="' . esc_attr( $field_type ) . '" name="' . esc_attr( $field_id ) . '" id="' . esc_attr( $field_id ) . '" value="' . esc_attr( $field_value ) . '" autocomplete="off" placeholder="' . esc_attr( $field_placeholder ) . '" class="' . esc_attr( $css_class ) . '" />';
				break;

			case 'toggle':
				$css_class   = $field_class ? 'toggle-checkbox ' . trim( $field_class ) : 'toggle-checkbox';
				$state_label = $field_value === 'on' ? __( 'Enabled', 'instawp-connect' ) : __( 'Disabled', 'instawp-connect' );

				echo '<label class="toggle-control">';
				echo '<input type="checkbox" ' . checked( $field_value, 'on', false ) . ' name="' . esc_attr( $field_id ) . '" id="' . esc_attr( $field_id ) . '" class="' . esc_attr( $css_class ) . '" />';
				echo '<div class="toggle-switch"></div>';
				echo '<span class="toggle-label" data-on="' . esc_attr__( 'Enabled', 'instawp-connect' ) . '" data-off="' . esc_attr__( 'Disabled', 'instawp-connect' ) . '">' . esc_html( $state_label ) . '</span>';
				echo '</label>';
				break;

			case 'select':
				$css_class = $field_class ? $field_class : '';

				echo '<select ' . implode( ' ', $attributes ) . ' name="' . esc_attr( $field_id ) . '" id="' . esc_attr( $field_id ) . '" class="' . esc_attr( $css_class ) . '">';
				if ( ! empty( $field_placeholder ) ) {
					echo '<option value="">' . esc_html( $field_placeholder ) . '</option>';
				}
				foreach ( $field_options as $key => $value ) {
					echo '<option ' . selected( $field_value, $key, false ) . ' value="' . esc_attr( $key ) . '">' . esc_html( $value ) . '</option>';
				}
				echo '</select>';
				break;
			
			case 'select2':
				$css_class = $field_class ? $field_class : '';
				$css_class .= $remote ? ' instawp_select2_ajax' : ' instawp_select2';
				if( $multiple == true ){
					array_push( $attributes, 'multiple' );
				}
				echo '<select ' . ( $remote  === true ? 'data-ajax--url="'. admin_url('admin-ajax.php?action='.$action.'&event='.$event ) .'"' : '') . implode( ' ', $attributes ) . ' name="' . esc_attr( $field_id ) . ( $multiple == true ? '[]' : '' ) . '" id="' . esc_attr( $field_id ) . '" class="' . esc_attr( $css_class ) . '">';
				if ( ! empty( $field_placeholder ) ) {
					echo '<option value="">' . esc_html( $field_placeholder ) . '</option>';
				}
				foreach ( $field_options as $key => $value ) {
					if( is_array( $field_value ) ){ 
						$selected = in_array( $key, $field_value ) ? 'selected' : '';
					}else{
						$selected = selected( $field_value, $key, false ); 
					}
					echo '<option ' . $selected . ' value="' . esc_attr( $key ) . '">' . esc_html( $value ) . '</option>';
				}
				echo '</select>';
				break;

			default:
				break;
		}

		if ( ! empty( $field_desc ) ) {
			echo '<p class="desc mt-3">' . wp_kses_post( $field_desc ) . '</p>';
		}

		echo '</div>';
	}

	public static function generate_section( $section = array(), $index = 0 ) {

		$section_classes = [ 'section' ];
		$internal        = self::get_args_option( 'internal', $section, false );
		$css_class       = self::get_args_option( 'class', $section );
		$can_split       = self::get_args_option( 'split', $section, true );
		$grid_css_class  = self::get_args_option( 'grid_class', $section, 'grid grid-cols-1 md:grid-cols-2 gap-6' );

		if ( true === $internal || 1 == $internal ) {
			if ( ! isset( $_REQUEST['internal'] ) || '1' != sanitize_text_field( $_REQUEST['internal'] ) ) {
				return;
			}
		}

		if ( $css_class ) {
			$section_classes[] = $css_class;
		}

		if ( $index > 0 && $can_split ) {
			$section_classes[] = 'mt-6 pt-6 border-t border-gray-200';
		}

		echo '<div class="' . esc_attr( join( ' ', $section_classes ) ) . '">';

		echo '<div class="section-head mb-6">';
		echo '<div class="text-grayCust-200 text-lg font-medium">' . esc_html( self::get_args_option( 'title', $section ) ) . '</div>';
		echo '<div class="text-grayCust-50 text-sm font-normal">' . wp_kses_post( self::get_args_option( 'desc', $section ) ) . '</div>';
		echo '</div>';

		echo '<div class="' . esc_attr( $grid_css_class ) . '">';

		foreach ( self::get_args_option( 'fields', $section, array() ) as $index => $field ) {
			$field_type = self::get_args_option( 'type', $field );
			if ( empty( $field_type ) ) {
				continue;
			}
			self::generate_section_field( $field );
		}

		echo '</div>';
		echo '</div>';
	}

	public static function get_migrate_settings_fields() {

		$all_fields = array();

		foreach ( self::get_migrate_settings() as $migrate_setting ) {
			foreach ( self::get_args_option( 'fields', $migrate_setting, array() ) as $field ) {
				$all_fields[] = self::get_args_option( 'id', $field );
			}
		}

		return array_filter( $all_fields );
	}

	public static function get_migrate_settings() {
		$settings = [];

		// Section - Settings
		$settings['settings'] = array(
			'title'  => esc_html__( 'Settings', 'instawp-connect' ),
			'desc'   => esc_html__( 'Update your settings before creating staging sites.', 'instawp-connect' ),
			'fields' => array(
				array(
					'id'          => 'instawp_api_key',
					'type'        => 'text',
					'title'       => esc_html__( 'API Key', 'instawp-connect' ),
					'placeholder' => esc_attr( 'gL8tbdZFfG8yQCXu0IycBa' ),
					'attributes'  => array(//						'readonly' => true,
					),
				),
				array(
					'id'          => 'instawp_backup_part_size',
					'type'        => 'number',
					'title'       => esc_html__( 'Backup Parts Size', 'instawp-connect' ),
					'desc'        => esc_html__( 'Unit is MB. Default - ' . INSTAWP_DEFAULT_MAX_FILE_SIZE . ' MB', 'instawp-connect' ),
					'placeholder' => esc_attr( INSTAWP_DEFAULT_MAX_FILE_SIZE ),
					'attributes'  => array(
						'min' => '10',
						'max' => '100',
					),
				),
				array(
					'id'      => 'instawp_max_file_size_allowed',
					'type'    => 'number',
					'title'   => esc_html__( 'Maximum Allowed File Size', 'instawp-connect' ),
					'desc'    => esc_html__( 'This option will set maximum allowed file size. Default - ' . INSTAWP_DEFAULT_MAX_FILE_SIZE_ALLOWED . ' MB', 'instawp-connect' ),
					'placeholder' => esc_attr( INSTAWP_DEFAULT_MAX_FILE_SIZE_ALLOWED ),
					'attributes'  => array(
						'min' => '10',
						'max' => '1024',
					),
				),
				array(
					'id'      => 'instawp_reset_type',
					'type'    => 'select',
					'title'   => esc_html__( 'Plugin Reset Type', 'instawp-connect' ),
					'desc'    => esc_html__( 'Hard reset will remove everything including API key.', 'instawp-connect' ),
					'options' => array(
						'soft' => esc_html__( 'Soft Reset', 'instawp-connect' ),
						'hard' => esc_html__( 'Hard Reset', 'instawp-connect' ),
					),
				),
				array(
					'id'      => 'instawp_db_method',
					'type'    => 'select',
					'title'   => esc_html__( 'Database Method', 'instawp-connect' ),
					'desc'    => esc_html__( 'WPDB option has a better compatibility, but slower. It is recommended to choose PDO if pdo_mysql extension is installed.', 'instawp-connect' ),
					'options' => array(
						'wpdb' => esc_html__( 'WPDB', 'instawp-connect' ),
						'pdo'  => esc_html__( 'PDO', 'instawp-connect' ),
					),
				),
				array(
					'id'      => 'instawp_default_user',
					'type'    => 'select2',
					'remote'  => true,
					'action'  => 'instawp_handle_select2',
					'event'   => 'instawp_get_users',
					'title'   => esc_html__( 'Default User', 'instawp-connect' ),
					'desc'    => esc_html__( 'This option will allow to set default user for events syncing.', 'instawp-connect' ),
					'options' => self::get_select2_default_selected_option( 'instawp_default_user' )
				),
				array(
					'id'      => 'instawp_sync_tab_roles',
					'type'    => 'select2',
					'remote'  => true,
					'multiple'=> true,
					'action'  => 'instawp_handle_select2',
					'event'   => 'instawp_sync_tab_roles',
					'title'   => esc_html__( 'Sync Tab Access', 'instawp-connect' ),
					'desc'    => esc_html__( 'This option will allow to set roles for sync tab. Only assigned role\'s users can access the syncing features.', 'instawp-connect' ),
					'options' => self::get_select2_default_selected_option( 'instawp_sync_tab_roles' )
				)
			),
		);

		// Section - Developer Options
		$settings['developer'] = array(
			'title'    => esc_html__( 'Developer Options', 'instawp-connect' ),
			'desc'     => esc_html__( 'This section is available only for the developers working in this plugin.', 'instawp-connect' ),
			'internal' => true,
			'fields'   => array(
				array(
					'id'          => 'instawp_api_url',
					'type'        => 'url',
					'title'       => esc_html__( 'API Domain', 'instawp-connect' ),
					'placeholder' => esc_url_raw( 'https://stage.instawp.io' ),
				),
				array(
					'id'      => 'instawp_enable_wp_debug',
					'type'    => 'toggle',
					'title'   => __( 'WP Debug Log', 'instawp-connect' ),
					'tooltip' => __( 'Enabling this option will enable WordPress debug log.', 'instawp-connect' ),
					'class'   => 'save-ajax',
					'default' => ( defined( 'WP_DEBUG_LOG' ) && WP_DEBUG_LOG ) ? 'on' : 'off',
				),
			),
		);

		return apply_filters( 'INSTAWP_CONNECT/Filters/migrate_settings', $settings );
	}


	public static function get_management_settings() {
		$settings  = [];
		$heartbeat = InstaWP_Setting::get_option( 'instawp_rm_heartbeat', 'on' );
		$heartbeat = empty( $heartbeat ) ? 'on' : $heartbeat;

		// Section - Heartbeat
		$settings['heartbeat'] = [
			'title'  => __( 'Heartbeat', 'instawp-connect' ),
			'desc'   => __( 'Update your website\'s heartbeat settings.', 'instawp-connect' ),
			'fields' => [
				[
					'id'      => 'instawp_rm_heartbeat',
					'type'    => 'toggle',
					'title'   => __( 'Heartbeat', 'instawp-connect' ),
					'tooltip' => __( 'Enabling this option will allow remotely communicate with InstaWP API and sends data (e.g. wp_version, php_version, theme name, number of published posts, number of published pages, total number of users, total_size of the wordpress site etc.) on this website.', 'instawp-connect' ),
					'desc'    => __( 'Disabling this will automatically disconnect this site from InstaWP dashboard after 1 hour.', 'instawp-connect' ),
					'class'   => 'save-ajax',
					'default' => 'on',
				],
				[
					'id'           => 'instawp_api_heartbeat',
					'type'         => 'number',
					'title'        => __( 'Heartbeat Interval (Minutes)', 'instawp-connect' ),
					'tooltip'      => __( 'It is the interval of heartbeat in minutes.', 'instawp-connect' ),
					'desc'         => __( 'Minimum is 15 minutes and maximum is 60 minutes.', 'instawp-connect' ),
					'placeholder'  => '15',
					'class'        => '!w-80',
					'parent_class' => ( $heartbeat !== 'on' ) ? 'hidden' : '',
					'attributes'   => [
						'min' => 15,
						'max' => 60
					],
				],
			],
		];

		// Section - Management
		$settings['management'] = [
			'title'      => __( 'Remote Management (Beta)', 'instawp-connect' ),
			'desc'       => sprintf( __( 'Update your website\'s remote management settings. To use this feature in the InstaWP dashboard, switch on the beta program from %s section.', 'instawp-connect' ), '<a href="https://app.instawp.io/user/profile" target="_blank">'. __( 'My Accounts', 'instawp-connect' ) . '</a>' ),
			'grid_class' => 'grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 gap-6',
			'fields'     => [
				[
					'id'      => 'instawp_rm_file_manager',
					'type'    => 'toggle',
					'title'   => __( 'File Manager', 'instawp-connect' ),
					'tooltip' => __( 'Enabling this option will allow creation of remote file manager on this website remotely using the REST API.', 'instawp-connect' ),
					'class'   => 'save-ajax',
					'default' => 'off',
				],
				[
					'id'      => 'instawp_rm_database_manager',
					'type'    => 'toggle',
					'title'   => __( 'Database Manager', 'instawp-connect' ),
					'tooltip' => __( 'Enabling this option will allow creation of remote database manager on this website remotely using the REST API.', 'instawp-connect' ),
					'class'   => 'save-ajax',
					'default' => 'off',
				],
				[
					'id'      => 'instawp_rm_install_plugin_theme',
					'type'    => 'toggle',
					'title'   => __( 'Install Plugin / Themes', 'instawp-connect' ),
					'tooltip' => __( 'Enabling this option will allow install plugins and themes on this website remotely using the REST API.', 'instawp-connect' ),
					'class'   => 'save-ajax',
					'default' => 'off',
				],
				[
					'id'      => 'instawp_rm_config_management',
					'type'    => 'toggle',
					'title'   => __( 'Config Management', 'instawp-connect' ),
					'tooltip' => __( 'Enabling this option will allow reading, updating and deleting the WordPress constant values on this website remotely using the REST API.', 'instawp-connect' ),
					'class'   => 'save-ajax',
					'default' => 'off',
					'internal'=> true,
				],
				[
					'id'      => 'instawp_rm_inventory',
					'type'    => 'toggle',
					'title'   => __( 'Site Inventory', 'instawp-connect' ),
					'tooltip' => __( 'Enabling this option will allow reading the installed WordPress version, themes and plugins on this website remotely using the REST API.', 'instawp-connect' ),
					'class'   => 'save-ajax',
					'default' => 'off',
					'internal'=> true,
				],
				[
					'id'      => 'instawp_rm_debug_log',
					'type'    => 'toggle',
					'title'   => __( 'Debug Log', 'instawp-connect' ),
					'tooltip' => __( 'Enabling this option will allow reading WordPress error logs on this website remotely using the REST API.', 'instawp-connect' ),
					'class'   => 'save-ajax',
					'default' => 'off',
				],
			],
		];

		return apply_filters( 'INSTAWP_CONNECT/Filters/management_settings', $settings );
	}


	/**
	 * Return Arguments Value
	 *
	 * @param string $key
	 * @param string $default
	 * @param array $args
	 *
	 * @return mixed|string
	 */
	public static function get_args_option( $key = '', $args = array(), $default = '' ) {

		$default = is_array( $default ) && empty( $default ) ? array() : $default;
		$value   = ! is_array( $default ) && ! is_bool( $default ) && empty( $default ) ? '' : $default;
		$key     = empty( $key ) ? '' : $key;

		if ( isset( $args[ $key ] ) && ! empty( $args[ $key ] ) ) {
			$value = $args[ $key ];
		}

		if ( isset( $args[ $key ] ) && is_bool( $default ) ) {
			$value = ! ( 0 == $args[ $key ] || '' == $args[ $key ] );
		}

		return $value;
	}

	public static function get_default_option( $option_name ) {
		$options = array();

		switch ( $option_name ) {
			case 'instawp_compress_setting':
				$options = self::set_default_compress_option();
				break;
			case 'instawp_local_setting':
				$options = self::set_default_local_option();
				break;
			case 'instawp_upload_setting':
				$options = self::set_default_upload_option();
				break;
			case 'instawp_common_setting':
				$options = self::set_default_common_option();
				break;
		}

		return $options;
	}

	public static function set_default_option() {
		self::set_default_compress_option();
		self::set_default_local_option();
		self::set_default_upload_option();
		self::set_default_common_option();
	}

	public static function set_config_defaults( $defaults = array() ) {

		if ( ! is_array( $defaults ) ) {
			return;
		}

		if ( file_exists( ABSPATH . 'wp-config.php' ) ) {
			$global_config_file = ABSPATH . 'wp-config.php';
		} elseif ( file_exists( dirname( ABSPATH ) . '/wp-config.php' ) ) {
			$global_config_file = dirname( ABSPATH ) . '/wp-config.php';
		} else {
			return;
		}

		$original_lines = file( $global_config_file, FILE_IGNORE_NEW_LINES );
		$new_lines      = array();

		foreach ( $defaults as $key => $value ) {
			$new_lines[] = 'define( "' . $key . '", "' . $value . '" );';
		}

		if ( ! empty( $new_lines ) ) {
			array_splice( $original_lines, 1, 0, $new_lines );
		}

		file_put_contents( $global_config_file, implode( "\n", $original_lines ) );
	}

	public static function set_api_domain( $instawp_api_url = '' ) {

		$instawp_api_url = empty( $instawp_api_url ) ? esc_url_raw( 'https://app.instawp.io' ) : $instawp_api_url;

		update_option( 'instawp_api_url', $instawp_api_url );
	}

	public static function get_pro_subscription_url( $pro_subscription_slug = 'subscriptions' ) {
		return self::get_api_domain() . '/' . $pro_subscription_slug;
	}

	public static function get_api_domain() {
		return get_option( 'instawp_api_url' );
	}

	public static function get_api_key() {
		return get_option( 'instawp_api_key' );
	}

	public static function instawp_generate_api_key( $api_key, $status ) {

		global $InstaWP_Curl;

		if ( empty( $api_key ) || 'true' != $status ) {
			return false;
		}

		$api_domain = self::get_api_domain();
		$api_args   = array(
			'body'    => '',
			'headers' => array(
				'Authorization' => 'Bearer ' . $api_key,
				'Accept'        => 'application/json',
			),
		);

		if ( is_wp_error( $response = wp_remote_get( $api_domain . INSTAWP_API_URL . '/check-key', $api_args ) ) ) {
			return false;
		}

		$response_body = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( isset( $response_body['status'] ) && $response_body['status'] ) {
			update_option( 'instawp_api_options', array( 'api_key' => $api_key, 'response' => $response_body ) );
			update_option( 'instawp_api_key', $api_key );
		}


		// Connects api start

		$url         = $api_domain . INSTAWP_API_URL . '/connects';
		$php_version = substr( phpversion(), 0, 3 );
		$username    = '';

		foreach ( get_users( array( 'role__in' => array( 'administrator' ), 'fields' => array( 'user_login' ) ) ) as $admin ) {
			if ( empty( $username ) && isset( $admin->user_login ) ) {
				$username = $admin->user_login;
			}
		}

		$body = json_encode(
			array(
				"url"         => get_site_url(),
				"php_version" => $php_version,
				"username"    => ! empty( $username ) ? base64_encode( $username ) : "",
			)
		);

		$curl_response = $InstaWP_Curl->curl( $url, $body );

		error_log( "curl_response on generate \n" . print_r( $curl_response, true ) );

		if ( $curl_response['error'] ) {
			return false;
		}

		$response = (array) json_decode( $curl_response['curl_res'], true );

		if ( $response['status'] ) {
			$connect_id                     = $response['data']['id'];
			$connect_options                = self::get_option( 'instawp_connect_options', array() );
			$connect_options[ $connect_id ] = $response;

			update_option( 'instawp_connect_id_options', $response );
		}

		return true;
	}

	public static function set_default_compress_option() {
		$compress_option['compress_type']            = INSTAWP_DEFAULT_COMPRESS_TYPE;
		$compress_option['max_file_size']            = self::get_option( 'instawp_backup_part_size', INSTAWP_DEFAULT_MAX_FILE_SIZE );
		$compress_option['no_compress']              = INSTAWP_DEFAULT_NO_COMPRESS;
		$compress_option['use_temp_file']            = INSTAWP_DEFAULT_USE_TEMP;
		$compress_option['use_temp_size']            = INSTAWP_DEFAULT_USE_TEMP_SIZE;
		$compress_option['exclude_file_size']        = INSTAWP_DEFAULT_EXCLUDE_FILE_SIZE;
		$compress_option['subpackage_plugin_upload'] = INSTAWP_DEFAULT_SUBPACKAGE_PLUGIN_UPLOAD;

		self::update_option( 'instawp_compress_setting', $compress_option );

		return $compress_option;
	}

	public static function set_default_local_option() {
		$local_option['path']       = INSTAWP_DEFAULT_BACKUP_DIR;
		$local_option['save_local'] = 1;
		self::update_option( 'instawp_local_setting', $local_option );

		return $local_option;
	}

	public static function set_default_upload_option() {
		$upload_option = array();
		self::update_option( 'instawp_upload_setting', $upload_option );

		return $upload_option;
	}

	public static function set_default_email_option() {
		$email_option['send_to']      = array();
		$email_option['always']       = true;
		$email_option['email_enable'] = false;
		self::update_option( 'instawp_email_setting', $email_option );

		return $email_option;
	}

	public static function set_default_common_option() {
		$sapi_type = php_sapi_name();

		if ( $sapi_type == 'cgi-fcgi' || $sapi_type == ' fpm-fcgi' ) {
			$common_option['max_execution_time'] = INSTAWP_MAX_EXECUTION_TIME_FCGI;
		} else {
			$common_option['max_execution_time'] = INSTAWP_MAX_EXECUTION_TIME;
		}

		$common_option['log_save_location'] = INSTAWP_DEFAULT_LOG_DIR;
		$common_option['max_backup_count']  = INSTAWP_DEFAULT_BACKUP_COUNT;
		$common_option['show_admin_bar']    = INSTAWP_DEFAULT_ADMIN_BAR;
		//$common_option['show_tab_menu']=INSTAWP_DEFAULT_TAB_MENU;
		$common_option['domain_include']       = INSTAWP_DEFAULT_DOMAIN_INCLUDE;
		$common_option['estimate_backup']      = INSTAWP_DEFAULT_ESTIMATE_BACKUP;
		$common_option['max_resume_count']     = INSTAWP_RESUME_RETRY_TIMES;
		$common_option['memory_limit']         = INSTAWP_MEMORY_LIMIT;
		$common_option['restore_memory_limit'] = INSTAWP_RESTORE_MEMORY_LIMIT;
		$common_option['migrate_size']         = INSTAWP_MIGRATE_SIZE;
		self::update_option( 'instawp_common_setting', $common_option );

		return $common_option;
	}

	public static function get_option( $option_name, $default = array() ) {
		$ret = get_option( $option_name, $default );
		if ( empty( $ret ) ) {
			self::get_default_option( $option_name );
		}

		return $ret;
	}

	public static function get_last_backup_message( $option_name, $default = array() ) {
		$message = self::get_option( $option_name, $default );
		$ret     = array();
		if ( ! empty( $message['id'] ) ) {
			$ret['id']                   = $message['id'];
			$ret['status']               = $message['status'];
			$ret['status']['start_time'] = date( "M d, Y H:i", $ret['status']['start_time'] );
			$ret['status']['run_time']   = date( "M d, Y H:i", $ret['status']['run_time'] );
			$ret['status']['timeout']    = date( "M d, Y H:i", $ret['status']['timeout'] );
			if ( isset( $message['options']['log_file_name'] ) ) {
				$ret['log_file_name'] = $message['options']['log_file_name'];
			} else {
				$ret['log_file_name'] = '';
			}
		}

		return $ret;
	}

	public static function get_backupdir() {
		$dir = self::get_option( 'instawp_local_setting' );

		if ( ! isset( $dir['path'] ) ) {
			$dir = self::set_default_local_option();
		}
		if ( ! is_dir( WP_CONTENT_DIR . DIRECTORY_SEPARATOR . $dir['path'] ) ) {
			@mkdir( WP_CONTENT_DIR . DIRECTORY_SEPARATOR . $dir['path'], 0777, true );
			@fopen( WP_CONTENT_DIR . DIRECTORY_SEPARATOR . $dir['path'] . DIRECTORY_SEPARATOR . 'index.html', 'x' );
			$tempfile = @fopen( WP_CONTENT_DIR . DIRECTORY_SEPARATOR . $dir['path'] . DIRECTORY_SEPARATOR . '.htaccess', 'x' );
			if ( $tempfile ) {
				// Prevent access, #Commented temporarily#
				// $text = "deny from all";
				$text = "";
				fwrite( $tempfile, $text );
				fclose( $tempfile );
			} else {
				return false;
			}
		}

		return $dir['path'];
	}

	public static function set_backupdir( $dir ) {
		if ( ! isset( $dir['path'] ) ) {
			$dir = self::set_default_local_option();
		} else {
			self::update_option( 'instawp_local_setting', $dir );
		}

		if ( ! is_dir( WP_CONTENT_DIR . DIRECTORY_SEPARATOR . $dir['path'] ) ) {
			@mkdir( WP_CONTENT_DIR . DIRECTORY_SEPARATOR . $dir['path'], 0777, true );
		}

		@fopen( WP_CONTENT_DIR . DIRECTORY_SEPARATOR . $dir['path'] . '/index.html', 'x' );
		$tempfile = @fopen( WP_CONTENT_DIR . DIRECTORY_SEPARATOR . $dir['path'] . '/.htaccess', 'x' );
		if ( $tempfile ) {
			$text = "deny from all";
			fwrite( $tempfile, $text );
			fclose( $tempfile );
		}
	}

	public static function get_save_local() {
		$local = self::get_option( 'instawp_local_setting' );

		if ( ! isset( $local['save_local'] ) ) {
			$local = self::set_default_local_option();
		}

		return $local['save_local'];
	}

	public static function update_option( $option_name, $options ) {
		update_option( $option_name, $options, 'no' );
	}

	public static function update_connect_option( $option_name, $options, $connect_id, $task_id = '', $key = '' ) {

		$connect_options = self::get_option( 'instawp_connect_options', array() );
		if ( isset( $connect_options[ $connect_id ] ) ) {

			if ( isset( $connect_options[ $connect_id ][ $task_id ][ $key ] ) && ! empty( $key ) ) {

				$connect_options[ $connect_id ][ $task_id ][ $key ] = $options[ $task_id ][ $key ];
			} else {
				$connect_options[ $connect_id ][ $task_id ][ $key ] = $options;
			}
		} else {
			$connect_options[ $connect_id ] = $options;
		}
		update_option( $option_name, $connect_options, 'no' );
	}

	public static function delete_option( $option_name ) {
		delete_option( $option_name );
	}

	public static function get_tasks() {
		return get_option( 'instawp_task_list', [] );
	}

	public static function update_task( $id, $task ) {
		$default        = array();
		$options        = get_option( 'instawp_task_list', $default );
		$options[ $id ] = $task;

		self::update_option( 'instawp_task_list', $options );
	}

	public static function delete_task( $id ) {
		$default = array();
		$options = get_option( 'instawp_task_list', $default );
		unset( $options[ $id ] );
		self::update_option( 'instawp_task_list', $options );
	}

	public static function check_compress_options() {
		$options = self::get_option( 'instawp_compress_setting' );

		if ( ! isset( $options['compress_type'] ) || ! isset( $options['max_file_size'] ) ||
		     ! isset( $options['no_compress'] ) || ! isset( $options['exclude_file_size'] ) ||
		     ! isset( $options['use_temp_file'] ) || ! isset( $options['use_temp_size'] ) ) {
			self::set_default_compress_option();
		}
	}

	public static function check_local_options() {
		$options = self::get_option( 'instawp_local_setting' );

		if ( ! isset( $options['path'] ) || ! isset( $options['save_local'] ) ) {
			self::set_default_local_option();
		}

		return true;
	}

	public static function get_remote_option( $id ) {
		$upload_options = self::get_option( 'instawp_upload_setting' );
		if ( array_key_exists( $id, $upload_options ) ) {
			return $upload_options[ $id ];
		} else {
			return false;
		}
	}

	public static function get_remote_options( $remote_ids = array() ) {
		if ( empty( $remote_ids ) ) {
			$remote_ids = self::get_user_history( 'remote_selected' );
		}

		if ( empty( $remote_ids ) ) {
			return false;
		}

		$options        = array();
		$upload_options = self::get_option( 'instawp_upload_setting' );
		foreach ( $remote_ids as $id ) {
			if ( array_key_exists( $id, $upload_options ) ) {
				$options[ $id ] = $upload_options[ $id ];
			}
		}
		if ( empty( $options ) ) {
			return false;
		} else {
			return $options;
		}
	}

	public static function get_all_remote_options() {
		$upload_options                    = self::get_option( 'instawp_upload_setting' );
		$upload_options['remote_selected'] = self::get_user_history( 'remote_selected' );

		return $upload_options;
	}

	public static function add_remote_options( $remote ) {
		$upload_options = self::get_option( 'instawp_upload_setting' );
		$id             = uniqid( 'instawp-remote-' );

		$remote = apply_filters( 'instawp_pre_add_remote', $remote, $id );

		$upload_options[ $id ] = $remote;
		self::update_option( 'instawp_upload_setting', $upload_options );

		return $id;
	}

	public static function delete_remote_option( $id ) {
		do_action( 'instawp_delete_remote_token', $id );

		$upload_options = self::get_option( 'instawp_upload_setting' );

		if ( array_key_exists( $id, $upload_options ) ) {
			unset( $upload_options[ $id ] );

			self::update_option( 'instawp_upload_setting', $upload_options );

			return true;
		} else {
			return false;
		}
	}

	public static function update_remote_option( $remote_id, $remote ) {
		$upload_options = self::get_option( 'instawp_upload_setting' );

		if ( array_key_exists( $remote_id, $upload_options ) ) {
			$remote                       = apply_filters( 'instawp_pre_add_remote', $remote, $remote_id );
			$upload_options[ $remote_id ] = $remote;
			self::update_option( 'instawp_upload_setting', $upload_options );

			return true;
		} else {
			return false;
		}
	}

	public static function get_setting( $all, $options_name ) {
		$get_options = array();
		if ( $all == true ) {
			$get_options[] = 'instawp_email_setting';
			$get_options[] = 'instawp_compress_setting';
			$get_options[] = 'instawp_local_setting';
			$get_options[] = 'instawp_common_setting';
			$get_options   = apply_filters( 'instawp_get_setting_addon', $get_options );
		} else {
			$get_options[] = $options_name;
		}

		$ret['result']  = 'success';
		$ret['options'] = array();

		foreach ( $get_options as $option_name ) {
			$ret['options'][ $option_name ] = self::get_option( $option_name );
		}

		return $ret;
	}

	public static function update_setting( $options ) {
		foreach ( $options as $option_name => $option ) {
			self::update_option( $option_name, $option );
		}
		$ret['result'] = 'success';

		return $ret;
	}

	public static function export_setting_to_json( $setting = true, $history = true, $review = true, $backup_list = true ) {
		global $instawp_plugin;
		$json['plugin']               = $instawp_plugin->get_plugin_name();
		$json['version']              = INSTAWP_PLUGIN_VERSION;
		$json['setting']              = $setting;
		$json['history']              = $history;
		$json['data']['instawp_init'] = self::get_option( 'instawp_init' );

		if ( $setting ) {
			$json['data']['instawp_schedule_setting'] = self::get_option( 'instawp_schedule_setting' );
			if ( ! empty( $json['data']['instawp_schedule_setting'] ) ) {
				if ( isset( $json['data']['instawp_schedule_setting']['backup']['backup_files'] ) ) {
					$json['data']['instawp_schedule_setting']['backup_type'] = $json['data']['instawp_schedule_setting']['backup']['backup_files'];
				}
				if ( isset( $json['data']['instawp_schedule_setting']['backup']['local'] ) ) {
					if ( $json['data']['instawp_schedule_setting']['backup']['local'] == 1 ) {
						$json['data']['instawp_schedule_setting']['save_local_remote'] = 'local';
					} else {
						$json['data']['instawp_schedule_setting']['save_local_remote'] = 'remote';
					}
				}

				$json['data']['instawp_schedule_setting']['lock'] = 0;
				if ( wp_get_schedule( INSTAWP_MAIN_SCHEDULE_EVENT ) ) {
					$recurrence                                             = wp_get_schedule( INSTAWP_MAIN_SCHEDULE_EVENT );
					$timestamp                                              = wp_next_scheduled( INSTAWP_MAIN_SCHEDULE_EVENT );
					$json['data']['instawp_schedule_setting']['recurrence'] = $recurrence;
					$json['data']['instawp_schedule_setting']['next_start'] = $timestamp;
				}
			} else {
				$json['data']['instawp_schedule_setting'] = array();
			}
			$json['data']['instawp_compress_setting'] = self::get_option( 'instawp_compress_setting' );
			$json['data']['instawp_local_setting']    = self::get_option( 'instawp_local_setting' );
			$json['data']['instawp_upload_setting']   = self::get_option( 'instawp_upload_setting' );
			$json['data']['instawp_common_setting']   = self::get_option( 'instawp_common_setting' );
			$json['data']['instawp_email_setting']    = self::get_option( 'instawp_email_setting' );
			$json['data']['instawp_saved_api_token']  = self::get_option( 'instawp_saved_api_token' );
			$json                                     = apply_filters( 'instawp_export_setting_addon', $json );
			/*if(isset($json['data']['instawp_local_setting']['path'])){
				unset($json['data']['instawp_local_setting']['path']);
			}*/
			if ( isset( $json['data']['instawp_common_setting']['log_save_location'] ) ) {
				unset( $json['data']['instawp_common_setting']['log_save_location'] );
			}
			if ( isset( $json['data']['instawp_common_setting']['backup_prefix'] ) ) {
				unset( $json['data']['instawp_common_setting']['backup_prefix'] );
			}
		}

		if ( $history ) {
			$json['data']['instawp_task_list']    = self::get_option( 'instawp_task_list' );
			$json['data']['instawp_last_msg']     = self::get_option( 'instawp_last_msg' );
			$json['data']['instawp_user_history'] = self::get_option( 'instawp_user_history' );
			$json                                 = apply_filters( 'instawp_history_addon', $json );
		}

		if ( $backup_list ) {
			$json['data']['instawp_backup_list'] = self::get_option( 'instawp_backup_list' );
			$json                                = apply_filters( 'instawp_backup_list_addon', $json );
		}

		if ( $review ) {
			$json['data']['instawp_need_review'] = self::get_option( 'instawp_need_review' );
			$json['data']['cron_backup_count']   = self::get_option( 'cron_backup_count' );
			$json['data']['instawp_review_msg']  = self::get_option( 'instawp_review_msg' );
			$json                                = apply_filters( 'instawp_review_addon', $json );
		}

		return $json;
	}

	public static function import_json_to_setting( $json ) {
		wp_cache_delete( 'notoptions', 'options' );
		wp_cache_delete( 'alloptions', 'options' );
		foreach ( $json['data'] as $option_name => $option ) {
			wp_cache_delete( $option_name, 'options' );
			delete_option( $option_name );
			self::update_option( $option_name, $option );
		}
	}

	public static function set_max_backup_count( $count ) {
		$options                     = self::get_option( 'instawp_common_setting' );
		$options['max_backup_count'] = $count;
		self::update_option( 'instawp_common_setting', $options );
	}

	public static function get_max_backup_count() {
		$options = self::get_option( 'instawp_common_setting' );
		if ( isset( $options['max_backup_count'] ) ) {
			return $options['max_backup_count'];
		} else {
			return INSTAWP_MAX_BACKUP_COUNT;
		}
	}

	public static function get_mail_setting() {
		return self::get_option( 'instawp_email_setting' );
	}

	public static function get_admin_bar_setting() {
		$options = self::get_option( 'instawp_common_setting' );
		if ( isset( $options['show_admin_bar'] ) ) {
			if ( $options['show_admin_bar'] ) {
				return true;
			} else {
				return false;
			}
		} else {
			return true;
		}
	}

	public static function update_user_history( $action, $value ) {
		$options            = self::get_option( 'instawp_user_history' );
		$options[ $action ] = $value;
		self::update_option( 'instawp_user_history', $options );
	}

	public static function get_user_history( $action ) {
		$options = self::get_option( 'instawp_user_history' );
		if ( array_key_exists( $action, $options ) ) {
			return $options[ $action ];
		} else {
			return array();
		}
	}

	public static function get_retain_local_status() {
		$options = self::get_option( 'instawp_common_setting' );
		if ( isset( $options['retain_local'] ) ) {
			if ( $options['retain_local'] ) {
				return true;
			} else {
				return false;
			}
		} else {
			return false;
		}
	}

	public static function get_sync_data() {
		$data['setting']['instawp_compress_setting'] = self::get_option( 'instawp_compress_setting' );
		$data['setting']['instawp_local_setting']    = self::get_option( 'instawp_local_setting' );
		$data['setting']['instawp_common_setting']   = self::get_option( 'instawp_common_setting' );
		$data['setting']['instawp_email_setting']    = self::get_option( 'instawp_email_setting' );
		$data['setting']['cron_backup_count']        = self::get_option( 'cron_backup_count' );
		$data['schedule']                            = self::get_option( 'instawp_schedule_setting' );
		$data['remote']['upload']                    = self::get_option( 'instawp_upload_setting' );
		$data['remote']['history']                   = self::get_option( 'instawp_user_history' );
		$data['last_backup_report']                  = get_option( 'instawp_backup_reports' );

		$data['setting_addon']                            = $data['setting'];
		$data['setting_addon']['instawp_staging_options'] = array();
		$data['backup_custom_setting']                    = array();
		$data['menu_capability']                          = array();
		$data['white_label_setting']                      = array();
		$data['incremental_backup_setting']               = array();
		$data['schedule_addon']                           = array();
		$data['time_zone']                                = false;
		$data['is_pro']                                   = false;
		$data['is_install']                               = false;
		$data['is_login']                                 = false;
		$data['latest_version']                           = '';
		$data['current_version']                          = '';
		$data['dashboard_version']                        = '';
		$data['addons_info']                              = array();
		$data                                             = apply_filters( 'instawp_get_instawp_info_addon_mainwp_ex', $data );

		return $data;
	}

	public static function get_select2_default_selected_option( $option ) {
		if( $option ==  'instawp_default_user'){
			$user = get_user_by( 'ID', InstaWP_Setting::get_option( $option ) );
			if( !empty( $user ) ) {
				return [ $user->data->ID => $user->data->user_login ];
			}
		}else if( $option ==  'instawp_sync_tab_roles'){
			$role_options = [];
			$all_roles = wp_roles()->roles;
			$selected_roles =  InstaWP_Setting::get_option( $option );
			foreach( $selected_roles as $role ){
				$role_options[ $role ] = isset( $all_roles[$role] ) ? $all_roles[$role]['name'] : $role;
			} 
			return $role_options;
		}
		return [];
	}
}
