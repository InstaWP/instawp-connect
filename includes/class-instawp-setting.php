<?php

use InstaWP\Connect\Helpers\Helper;
use InstaWP\Connect\Helpers\Option;

defined( 'ABSPATH' ) || exit;

class InstaWP_Setting {

	public static function get_stages() {
		$stages = array(
			'initiated'                        => esc_html__( 'Migration started', 'instawp-connect' ),
			'start-insta-site'                 => esc_html__( 'Site creation in InstaWP is underway...', 'instawp-connect' ),
			'pull-ready'                       => esc_html__( 'Ready to pull files and the database', 'instawp-connect' ),
			'finished-insta-site'              => esc_html__( 'Site created in InstaWP', 'instawp-connect' ),
			'pull-initiated'                   => esc_html__( 'Started pulling files and the database...', 'instawp-connect' ),
			'inventory-installation-started'   => esc_html__( 'Plugin and theme installation started.', 'instawp-connect' ),
			'inventory-installation-completed' => esc_html__( 'Plugin and theme installation completed.', 'instawp-connect' ),
			'pull-files-in-progress'           => esc_html__( 'Files are being pulled...', 'instawp-connect' ),
			'pull-files-finished'              => esc_html__( 'All files have been pulled.', 'instawp-connect' ),
			'pull-db-in-progress'              => esc_html__( 'Pulling the database...', 'instawp-connect' ),
			'pull-db-finished'                 => esc_html__( 'The database has been pulled.', 'instawp-connect' ),
			//          'pull-db-restore-started'  => esc_html__( 'Database restoration started', 'instawp-connect' ),
			//          'pull-db-restore-finished' => esc_html__( 'Database restoration is completed', 'instawp-connect' ),
			'pull-finished'                    => esc_html__( 'Finished pulling files and the database.', 'instawp-connect' ),
			'migration-finished'               => esc_html__( 'Migration has been completed.', 'instawp-connect' ),
//          'timeout'                  => esc_html__( 'Migration is timed out', 'instawp-connect' ),
//          'aborted'                  => esc_html__( 'Migration is aborted', 'instawp-connect' ),
//          'failed'                   => esc_html__( 'Migration is failed', 'instawp-connect' ),
		);

		return apply_filters( 'instawp/filters/get_stages', $stages );
	}

	public static function get_plugin_nav_items() {
		$instawp_nav_items = array(
			'create'   => array(
				'label' => __( 'Create Staging', 'instawp-connect' ),
				'icon'  => '<svg width="20" class="mr-2" height="20" viewBox="0 0 17 17" fill="none" xmlns="http://www.w3.org/2000/svg"> <path fill-rule="evenodd" clip-rule="evenodd" d="M9.36804 0.883699C9.04694 0.111684 7.95329 0.111684 7.63219 0.883699L5.8014 5.28547L1.04932 5.66644C0.215863 5.73326 -0.122092 6.77337 0.512913 7.31732L4.13349 10.4187L3.02735 15.056C2.83334 15.8693 3.71812 16.5121 4.43167 16.0763L8.50011 13.5913L12.5686 16.0763C13.2821 16.5121 14.1669 15.8693 13.9729 15.056L12.8667 10.4187L16.4873 7.31732C17.1223 6.77337 16.7844 5.73326 15.9509 5.66644L11.1988 5.28547L9.36804 0.883699Z" fill="#005E54"/> </svg>',
			),
			'sites'    => array(
				'label' => instawp()->is_staging ? __( 'Staging', 'instawp-connect' ) : __( 'Staging Sites', 'instawp-connect' ),
				'icon'  => '<svg class="mr-2" width="20" height="20" viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg"> <mask id="mask0_145_11" style="mask-type:luminance" maskUnits="userSpaceOnUse" x="0" y="0" width="19" height="20"> <path d="M18.7441 0H0.90625V20H18.7441V0Z" fill="white"/> </mask> <path d="M10.6758 18.2335C10.6758 18.8089 10.6758 19.0966 10.796 19.257C10.9007 19.3969 11.0611 19.4842 11.2353 19.4964C11.4352 19.5104 11.677 19.3544 12.1604 19.0424L15.9388 16.6038C16.9047 15.9805 17.3876 15.6688 17.7376 15.2519C18.0473 14.8829 18.2801 14.4556 18.4222 13.9952C18.5827 13.4751 18.5827 12.9003 18.5827 11.7508V7.7997C18.5827 7.2284 18.5827 6.94272 18.4632 6.78261C18.3591 6.6431 18.1996 6.55553 18.0261 6.5425C17.8269 6.52753 17.5858 6.68078 17.1036 6.98727L11.122 10.7892C10.9591 10.8929 10.8775 10.9447 10.8184 11.0143C10.7661 11.076 10.7268 11.1476 10.7028 11.2249C10.6758 11.312 10.6758 11.4086 10.6758 11.6017V18.2335Z" fill="#005E54"/> <path d="M2.3644 6.92278C1.88715 6.6363 1.64852 6.49305 1.45218 6.51132C1.28097 6.52722 1.12475 6.61565 1.02297 6.75425C0.90625 6.91322 0.90625 7.19154 0.90625 7.74814V11.654C0.90625 12.8374 0.90625 13.429 1.07506 13.9614C1.22445 14.4327 1.46903 14.8682 1.79363 15.241C2.16041 15.6623 2.66562 15.9702 3.67602 16.5861L7.81463 19.1087C8.29317 19.4004 8.53247 19.5463 8.7296 19.5289C8.90144 19.5137 9.05852 19.4255 9.16089 19.2867C9.27836 19.1274 9.27836 18.8472 9.27836 18.2867V11.6182C9.27836 11.4194 9.27836 11.3202 9.24981 11.2308C9.22463 11.1518 9.18333 11.0789 9.12858 11.0166C9.06669 10.9462 8.98149 10.8951 8.81117 10.7928L2.3644 6.92278Z" fill="#005E54"/> <path d="M6.9896 1.80712C8.07571 1.15049 8.61874 0.822182 9.19895 0.693949C9.7121 0.580533 10.2438 0.580533 10.7571 0.693949C11.3373 0.822176 11.8803 1.15049 12.9664 1.80712L16.7445 4.09128C17.1974 4.36509 17.4238 4.50199 17.5002 4.6783C17.5668 4.83218 17.5663 5.00691 17.4986 5.16035C17.421 5.33614 17.1936 5.47148 16.7389 5.74224L10.4706 9.47458C10.2914 9.58131 10.2018 9.63458 10.1063 9.6554C10.0217 9.67386 9.93428 9.67386 9.84974 9.6554C9.75419 9.63458 9.66465 9.58131 9.48549 9.47458L3.21709 5.74224C2.76237 5.47148 2.53501 5.33614 2.45745 5.16035C2.38975 5.00691 2.38917 4.83218 2.45583 4.6783C2.5322 4.50199 2.75864 4.36509 3.21152 4.09128L6.9896 1.80712Z" fill="#005E54"/> </svg>',
			),
			'manage'   => array(
				'label' => __( 'Manage', 'instawp-connect' ),
				'icon'  => '<svg class="mr-2" width="20" height="20" viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M5 4C5 3.44772 4.55228 3 4 3C3.44772 3 3 3.44772 3 4V11.2676C2.4022 11.6134 2 12.2597 2 13C2 13.7403 2.4022 14.3866 3 14.7324V16C3 16.5523 3.44772 17 4 17C4.55228 17 5 16.5523 5 16V14.7324C5.5978 14.3866 6 13.7403 6 13C6 12.2597 5.5978 11.6134 5 11.2676V4Z" fill="#005E54"/><path d="M11 4C11 3.44772 10.5523 3 10 3C9.44772 3 9 3.44772 9 4V5.26756C8.4022 5.61337 8 6.25972 8 7C8 7.74028 8.4022 8.38663 9 8.73244V16C9 16.5523 9.44772 17 10 17C10.5523 17 11 16.5523 11 16V8.73244C11.5978 8.38663 12 7.74028 12 7C12 6.25972 11.5978 5.61337 11 5.26756V4Z" fill="#005E54"/><path d="M16 3C16.5523 3 17 3.44772 17 4V11.2676C17.5978 11.6134 18 12.2597 18 13C18 13.7403 17.5978 14.3866 17 14.7324V16C17 16.5523 16.5523 17 16 17C15.4477 17 15 16.5523 15 16V14.7324C14.4022 14.3866 14 13.7403 14 13C14 12.2597 14.4022 11.6134 15 11.2676V4C15 3.44772 15.4477 3 16 3Z" fill="#005E54"/>
				</svg>',
			),
			'sync'     => array(
				'label' => __( 'Sync (Beta)', 'instawp-connect' ),
				'icon'  => '<svg width="14" class="mr-2" height="16" viewBox="0 0 14 16" fill="none" xmlns="http://www.w3.org/2000/svg"> <path fill-rule="evenodd" clip-rule="evenodd" d="M1.59995 0.800049C2.09701 0.800049 2.49995 1.20299 2.49995 1.70005V3.59118C3.64303 2.42445 5.23642 1.70005 6.99995 1.70005C9.74442 1.70005 12.0768 3.45444 12.9412 5.90013C13.1069 6.36877 12.8612 6.88296 12.3926 7.0486C11.924 7.21425 11.4098 6.96862 11.2441 6.49997C10.6259 4.75097 8.95787 3.50005 6.99995 3.50005C5.52851 3.50005 4.22078 4.20657 3.39937 5.30005H6.09995C6.59701 5.30005 6.99995 5.70299 6.99995 6.20005C6.99995 6.6971 6.59701 7.10005 6.09995 7.10005H1.59995C1.10289 7.10005 0.699951 6.6971 0.699951 6.20005V1.70005C0.699951 1.20299 1.10289 0.800049 1.59995 0.800049ZM1.6073 8.95149C2.07594 8.78585 2.59014 9.03148 2.75578 9.50013C3.37396 11.2491 5.04203 12.5 6.99995 12.5C8.47139 12.5 9.77912 11.7935 10.6005 10.7L7.89995 10.7C7.40289 10.7 6.99995 10.2971 6.99995 9.80005C6.99995 9.30299 7.40289 8.90005 7.89995 8.90005H12.3999C12.6386 8.90005 12.8676 8.99487 13.0363 9.16365C13.2051 9.33243 13.3 9.56135 13.3 9.80005V14.3C13.3 14.7971 12.897 15.2 12.4 15.2C11.9029 15.2 11.5 14.7971 11.5 14.3V12.4089C10.3569 13.5757 8.76348 14.3 6.99995 14.3C4.25549 14.3 1.92309 12.5457 1.05867 10.1C0.893024 9.63132 1.13866 9.11714 1.6073 8.95149Z"/> </svg>',
			),
			'settings' => array(
				'label' => __( 'Settings', 'instawp-connect' ),
				'icon'  => '<svg width="20" class="mr-2" height="20" viewBox="0 0 16 16" fill="none" xmlns="http://www.w3.org/2000/svg"> <path fill-rule="evenodd" clip-rule="evenodd" d="M9.34035 1.8539C8.99923 0.448767 7.00087 0.448766 6.65975 1.8539C6.43939 2.76159 5.39945 3.19235 4.6018 2.70633C3.36701 1.95396 1.95396 3.36701 2.70633 4.6018C3.19235 5.39945 2.76159 6.43939 1.8539 6.65975C0.448766 7.00087 0.448767 8.99923 1.8539 9.34035C2.76159 9.56071 3.19235 10.6006 2.70633 11.3983C1.95396 12.6331 3.36701 14.0461 4.6018 13.2938C5.39945 12.8077 6.43939 13.2385 6.65975 14.1462C7.00087 15.5513 8.99923 15.5513 9.34035 14.1462C9.56071 13.2385 10.6006 12.8077 11.3983 13.2938C12.6331 14.0461 14.0461 12.6331 13.2938 11.3983C12.8077 10.6006 13.2385 9.56071 14.1462 9.34035C15.5513 8.99923 15.5513 7.00087 14.1462 6.65975C13.2385 6.43939 12.8077 5.39945 13.2938 4.6018C14.0461 3.36701 12.6331 1.95396 11.3983 2.70633C10.6006 3.19235 9.56071 2.76159 9.34035 1.8539ZM8.00005 10.7C9.49122 10.7 10.7 9.49122 10.7 8.00005C10.7 6.50888 9.49122 5.30005 8.00005 5.30005C6.50888 5.30005 5.30005 6.50888 5.30005 8.00005C5.30005 9.49122 6.50888 10.7 8.00005 10.7Z"/> </svg>',
			),
		);

		if ( isset( $_REQUEST['internal'] ) && 1 === intval( $_REQUEST['internal'] ) ) {
			$instawp_nav_items['developer'] = array(
				'label' => __( 'Developer', 'instawp-connect' ),
				'icon'  => '<svg width="20" class="mr-2" height="20" viewBox="0 0 16 16" fill="none" xmlns="http://www.w3.org/2000/svg"> <path fill-rule="evenodd" clip-rule="evenodd" d="M9.34035 1.8539C8.99923 0.448767 7.00087 0.448766 6.65975 1.8539C6.43939 2.76159 5.39945 3.19235 4.6018 2.70633C3.36701 1.95396 1.95396 3.36701 2.70633 4.6018C3.19235 5.39945 2.76159 6.43939 1.8539 6.65975C0.448766 7.00087 0.448767 8.99923 1.8539 9.34035C2.76159 9.56071 3.19235 10.6006 2.70633 11.3983C1.95396 12.6331 3.36701 14.0461 4.6018 13.2938C5.39945 12.8077 6.43939 13.2385 6.65975 14.1462C7.00087 15.5513 8.99923 15.5513 9.34035 14.1462C9.56071 13.2385 10.6006 12.8077 11.3983 13.2938C12.6331 14.0461 14.0461 12.6331 13.2938 11.3983C12.8077 10.6006 13.2385 9.56071 14.1462 9.34035C15.5513 8.99923 15.5513 7.00087 14.1462 6.65975C13.2385 6.43939 12.8077 5.39945 13.2938 4.6018C14.0461 3.36701 12.6331 1.95396 11.3983 2.70633C10.6006 3.19235 9.56071 2.76159 9.34035 1.8539ZM8.00005 10.7C9.49122 10.7 10.7 9.49122 10.7 8.00005C10.7 6.50888 9.49122 5.30005 8.00005 5.30005C6.50888 5.30005 5.30005 6.50888 5.30005 8.00005C5.30005 9.49122 6.50888 10.7 8.00005 10.7Z"/> </svg>',
			);
		}

		if ( defined( 'IWP_PLUGIN_DISABLE_FEATURES' ) && is_array( IWP_PLUGIN_DISABLE_FEATURES ) ) {
			foreach ( IWP_PLUGIN_DISABLE_FEATURES as $key ) {
				unset( $instawp_nav_items[ $key ] );
			}
		}

		if ( instawp()->is_staging ) {
			unset( $instawp_nav_items['create'] );
			unset( $instawp_nav_items['manage'] );
		}

		$instawp_sync_tab_roles = Option::get_option( 'instawp_sync_tab_roles', array( 'administrator' ) );
		$instawp_sync_tab_roles = empty( $instawp_sync_tab_roles ) ? array( 'administrator' ) : $instawp_sync_tab_roles;

		if ( ! in_array( 'administrator', $instawp_sync_tab_roles ) ) {
			unset( $instawp_nav_items['create'] );
			unset( $instawp_nav_items['sites'] );
			unset( $instawp_nav_items['manage'] );
			unset( $instawp_nav_items['settings'] );
		}

		return apply_filters( 'instawp/filters/plugin_nav_items', $instawp_nav_items );
	}

	public static function get_allowed_role() {
		$allowed_role   = 'administrator';
		$selected_roles = Option::get_option( 'instawp_sync_tab_roles' );

		if ( ! empty( $selected_roles ) ) {
			foreach ( $selected_roles as $role ) {
				if ( current_user_can( $role ) ) {
					$allowed_role = $role;
					break;
				}
			}
		}

		return $allowed_role;
	}

	public static function generate_section_field( $field = array() ) {
		$field_id            = self::get_args_option( 'id', $field );
		$field_name          = self::get_args_option( 'name', $field );
		$field_class         = self::get_args_option( 'class', $field );
		$field_title         = self::get_args_option( 'title', $field );
		$field_type          = self::get_args_option( 'type', $field );
		$field_desc          = self::get_args_option( 'desc', $field );
		$internal            = self::get_args_option( 'internal', $field, false );
		$hidden              = self::get_args_option( 'hide', $field, false );
		$remote              = self::get_args_option( 'remote', $field );
		$event               = self::get_args_option( 'event', $field );
		$multiple            = self::get_args_option( 'multiple', $field );
		$action              = self::get_args_option( 'action', $field );
		$field_placeholder   = self::get_args_option( 'placeholder', $field );
		$field_tooltip       = self::get_args_option( 'tooltip', $field );
		$field_attributes    = self::get_args_option( 'attributes', $field, array() );
		$field_attributes    = ! is_array( $field_attributes ) ? array() : $field_attributes;
		$field_options       = self::get_args_option( 'options', $field, array() );
		$field_options       = ! is_array( $field_options ) ? array() : $field_options;
		$field_default_value = self::get_args_option( 'default', $field );
		$field_label_class   = self::get_args_option( 'label_class', $field );
		$field_parent_class  = self::get_args_option( 'parent_class', $field );
		$field_value         = Option::get_option( $field_id, $field_default_value );
		$attributes          = array();

		$field_value = ( $field_name ) ? self::get_args_option( $field_name, $field_value, $field_default_value ) : $field_value;
		$field_name  = ( $field_name ) ? $field_id . '[' . $field_name . ']' : $field_id;

		if ( $hidden === true ) {
			return;
		}

		if ( $internal ) {
			if ( ! isset( $_REQUEST['internal'] ) || 1 !== intval( $_REQUEST['internal'] ) ) {
				return;
			}
		}

		if ( defined( 'IWP_PLUGIN_DISABLE_SETTINGS' ) && is_array( IWP_PLUGIN_DISABLE_SETTINGS ) ) {
			if ( in_array( $field_id, IWP_PLUGIN_DISABLE_SETTINGS, true ) ) {
				return;
			}
		}

		foreach ( $field_attributes as $attribute_key => $attribute_val ) {
			$attributes[] = $attribute_key . '="' . $attribute_val . '"';
		}

		$label_class   = 'inline-block text-sm font-medium text-gray-700 mb-3 sm:mt-px sm:pt-2';
		$label_content = '<span>' . esc_html( $field_title ) . '</span>';
		if ( ! empty( $field_tooltip ) ) {
			$label_content .= '<span class="hint--top hint--large" aria-label="' . $field_tooltip . '"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"><path d="M12 22C6.47715 22 2 17.5228 2 12C2 6.47715 6.47715 2 12 2C17.5228 2 22 6.47715 22 12C22 17.5228 17.5228 22 12 22ZM12 20C16.4183 20 20 16.4183 20 12C20 7.58172 16.4183 4 12 4C7.58172 4 4 7.58172 4 12C4 16.4183 7.58172 20 12 20ZM11 7H13V9H11V7ZM11 11H13V17H11V11Z"></path></svg></span>';
		}

		if ( ! empty( $field_label_class ) ) {
			$label_class .= ' ' . $field_label_class;
		}

		$field_name_class      = str_replace( array( '[', ']' ), array( '_', '' ), $field_name );
		$field_container_class = 'instawp-single-field ' . esc_attr( str_replace( '_', '-', $field_name_class ) ) . '-field';
		if ( ! empty( $field_parent_class ) ) {
			$field_container_class .= ' ' . $field_parent_class;
		}

		if ( $field_type === 'select2' ) {
			$field_container_class .= ' select2-field-wrapper';
		}

		echo '<div class="' . esc_attr( $field_container_class ) . '">';
		echo '<label for="' . esc_attr( $field_name_class ) . '" class="' . esc_attr( $label_class ) . '">' . $label_content . '</label>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped

		switch ( $field_type ) {
			case 'text':
			case 'number':
			case 'email':
			case 'url':
				$css_class = 'inline-block rounded-md border-grayCust-350 shadow-sm focus:border-primary-900 focus:ring-1 focus:ring-primary-900 sm:text-sm';
				$css_class = $field_class ? $css_class . ' ' . trim( $field_class ) : 'w-full ' . $css_class;

				echo '<input ' . implode( ' ', $attributes ) . ' type="' . esc_attr( $field_type ) . '" name="' . esc_attr( $field_name ) . '" id="' . esc_attr( $field_name_class ) . '" value="' . esc_attr( $field_value ) . '" autocomplete="off" placeholder="' . esc_attr( $field_placeholder ) . '" class="' . esc_attr( $css_class ) . '" />'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
				break;

			case 'toggle':
				$css_class   = $field_class ? 'toggle-checkbox ' . trim( $field_class ) : 'toggle-checkbox';
				$state_label = $field_value === 'on' ? __( 'Enabled', 'instawp-connect' ) : __( 'Disabled', 'instawp-connect' );

				echo '<div class="inline-block w-full">';
				echo '<label class="toggle-control">';
				echo '<input type="checkbox" ' . checked( $field_value, 'on', false ) . ' name="' . esc_attr( $field_name ) . '" id="' . esc_attr( $field_name_class ) . '" class="' . esc_attr( $css_class ) . '" />';
				echo '<div class="toggle-switch"></div>';
				echo '<span class="toggle-label" data-on="' . esc_attr__( 'Enabled', 'instawp-connect' ) . '" data-off="' . esc_attr__( 'Disabled', 'instawp-connect' ) . '">' . esc_html( $state_label ) . '</span>';
				echo '</label>';
				echo '</div>';
				break;

			case 'select':
				$css_class = $field_class ? $field_class : '';

				echo '<select ' . implode( ' ', $attributes ) . ' name="' . esc_attr( $field_name ) . '" id="' . esc_attr( $field_name_class ) . '" class="' . esc_attr( $css_class ) . '" style="max-width: unset;">'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
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
				if ( $multiple ) {
					$attributes[] = 'multiple';
				}
				echo '<select ' . ( $remote === true ? 'data-ajax--url="' . esc_url( admin_url( 'admin-ajax.php?action=' . $action . '&event=' . $event ) ) . '"' : '' ) . implode( ' ', $attributes ) . ' name="' . esc_attr( $field_name ) . ( $multiple ? '[]' : '' ) . '" id="' . esc_attr( $field_name_class ) . '" class="' . esc_attr( $css_class ) . '">'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
				if ( ! empty( $field_placeholder ) ) {
					echo '<option value="">' . esc_html( $field_placeholder ) . '</option>';
				}
				foreach ( $field_options as $key => $value ) {
					if ( is_array( $field_value ) ) {
						$selected = in_array( $key, $field_value ) ? 'selected' : '';
					} else {
						$selected = selected( $field_value, $key, false );
					}
					echo '<option ' . $selected . ' value="' . esc_attr( $key ) . '">' . esc_html( $value ) . '</option>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
				}
				echo '</select>';
				break;

			case 'qa_tools':
				echo '<div class="inline-block w-full instawp-qa-tools">';
				echo '<button type="button" class="instawp-manager" data-type="database">' . esc_html__( 'Database Manager', 'instawp-connect' ) . '</button>';
				echo '<button type="button" class="instawp-manager" data-type="debug_log">' . esc_html__( 'Debug Log', 'instawp-connect' ) . '</button>';
				echo '<button type="button" class="instawp-manager" data-type="error_log" title="' . esc_attr__( 'Copy error log', 'instawp-connect' ) . '" >' . esc_html__( 'Error Log', 'instawp-connect' ) . '</button>';
				echo '</div>';

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
		$section_classes = array( 'section' );
		$internal        = self::get_args_option( 'internal', $section, false );
		$css_class       = self::get_args_option( 'class', $section );
		$can_split       = self::get_args_option( 'split', $section, true );
		$grid_css_class  = self::get_args_option( 'grid_class', $section, 'grid grid-cols-1 md:grid-cols-2 gap-6' );

		if ( $internal ) {
			if ( ! isset( $_REQUEST['internal'] ) || 1 !== intval( $_REQUEST['internal'] ) ) {
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

	public static function get_plugin_settings_fields() {
		$all_fields = array();

		foreach ( self::get_plugin_settings() as $plugin_setting ) {
			foreach ( self::get_args_option( 'fields', $plugin_setting, array() ) as $field ) {
				$all_fields[] = self::get_args_option( 'id', $field );
			}
		}

		return array_filter( $all_fields );
	}

	public static function get_plugin_settings() {
		$settings = array();

		// Section - Settings
		$settings['settings'] = array(
			'title'  => esc_html__( 'Settings', 'instawp-connect' ),
			'desc'   => esc_html__( 'Update your settings before creating staging sites.', 'instawp-connect' ),
			'fields' => array(
				array(
					'id'          => 'instawp_api_options',
					'name'        => 'api_key',
					'type'        => 'text',
					'title'       => esc_html__( 'API Key', 'instawp-connect' ),
					'placeholder' => esc_attr( 'gL8tbdZFfG8yQCXu0IycBa' ),
					'attributes'  => array(//                       'readonly' => true,
					),
				),
				array(
					'id'          => 'instawp_max_file_size_allowed',
					'type'        => 'number',
					'title'       => esc_html__( 'Maximum Allowed File Size', 'instawp-connect' ),
					'tooltip'     => sprintf( esc_html__( 'This option will set maximum allowed file size. Default - %s MB', 'instawp-connect' ), INSTAWP_DEFAULT_MAX_FILE_SIZE_ALLOWED ),
					'placeholder' => esc_attr( INSTAWP_DEFAULT_MAX_FILE_SIZE_ALLOWED ),
					'attributes'  => array(
						'min' => '10',
						'max' => '1024',
					),
				),
				array(
					'id'      => 'instawp_hide_plugin_icon_topbar',
					'type'    => 'toggle',
					'title'   => __( 'Hide Icon on Topbar', 'instawp-connect' ),
					'tooltip' => __( 'Remove the InstaWP icon from top admin bar. It will also remove the flashing icon.', 'instawp-connect' ),
					'class'   => 'save-ajax',
					'default' => 'off',
					'hide'    => defined( 'IWP_PLUGIN_TOPBAR_HIDE' ) && IWP_PLUGIN_TOPBAR_HIDE === true,
				),
				array(
					'id'       => 'instawp_hide_plugin_to_users',
					'type'     => 'select2',
					'remote'   => true,
					'multiple' => true,
					'action'   => 'instawp_handle_select2',
					'event'    => 'instawp_get_users_exclude_current',
					'title'    => class_exists( '\Edge_Cache_Plugin' ) ? esc_html__( 'Hide InstaWP Menu & Edge Cache to Users', 'instawp-connect' ) : esc_html__( 'Hide InstaWP Menu to Users', 'instawp-connect' ),
					'options'  => self::get_select2_default_selected_option( 'instawp_hide_plugin_to_users' ),
				),
			),
		);

        $activity_log_interval = Option::get_option( 'instawp_activity_log_interval', 'instantly' );
        $activity_log_interval = empty( $activity_log_interval ) ? 'instantly' : $activity_log_interval;

		// Section - Activity Log
		$settings['activity_log'] = array(
			'title'  => esc_html__( 'Activity Log', 'instawp-connect' ),
			'desc'   => esc_html__( 'These are some basic settings for Activity Log.', 'instawp-connect' ),
			'fields' => array(
				array(
					'id'      => 'instawp_activity_log',
					'type'    => 'toggle',
					'title'   => __( 'Activity Log', 'instawp-connect' ),
					'tooltip' => __( 'Enable / Disable InstaWP activity log for this website.', 'instawp-connect' ),
					'class'   => 'save-ajax',
					'default' => 'off',
				),
				array(
					'id'      => 'instawp_log_visitor_ip_source',
					'type'    => 'select',
                    'class'   => 'w-full',
					'title'   => __( 'Visitor IP Detected', 'instawp-connect' ),
					'tooltip' => __( 'Select the source of the visitor IP address. For example, if you are using Cloudflare, select HTTP_CF_CONNECTING_IP.', 'instawp-connect' ),
					'options' => array(
						'no-collect-ip'            => __( 'Do not collect IP', 'instawp-connect' ),
						'REMOTE_ADDR'              => 'REMOTE_ADDR',
						'HTTP_CF_CONNECTING_IP'    => 'HTTP_CF_CONNECTING_IP',
						'HTTP_TRUE_CLIENT_IP'      => 'HTTP_TRUE_CLIENT_IP',
						'HTTP_CLIENT_IP'           => 'HTTP_CLIENT_IP',
						'HTTP_X_FORWARDED_FOR'     => 'HTTP_X_FORWARDED_FOR',
						'HTTP_X_FORWARDED'         => 'HTTP_X_FORWARDED',
						'HTTP_X_CLUSTER_CLIENT_IP' => 'HTTP_X_CLUSTER_CLIENT_IP',
						'HTTP_FORWARDED_FOR'       => 'HTTP_FORWARDED_FOR',
						'HTTP_FORWARDED'           => 'HTTP_FORWARDED',
					),
				),
                array(
                    'id'      => 'instawp_activity_log_interval',
                    'type'    => 'select',
                    'title'   => __( 'Send Non-Critical Activity Log', 'instawp-connect' ),
                    'tooltip' => __( 'Send non-critical activity log to InstaWP. Critical Activity Logs will be send instantly.', 'instawp-connect' ),
                    'default' => 'off',
                    'class'   => 'w-full',
                    'options' => array(
                        'instantly'       => __( 'Instantly', 'instawp-connect' ),
                        'every_x_minutes' => __( 'Every X Minutes', 'instawp-connect' ),
                    ),
                ),
                array(
                    'id'           => 'instawp_activity_log_interval_minutes',
                    'type'         => 'number',
                    'title'        => __( 'Activity Log Interval (Minutes)', 'instawp-connect' ),
                    'tooltip'      => __( 'It is the interval of activity log send in minutes.', 'instawp-connect' ),
                    'desc'         => __( 'Minimum is 1 minutes and maximum is 60 minutes.', 'instawp-connect' ),
                    'placeholder'  => '5',
                    'parent_class' => ( $activity_log_interval !== 'every_x_minutes' ) ? 'hidden' : '',
                    'attributes'   => array(
                        'min' => 1,
                        'max' => 60,
                    ),
                ),
			),
		);

		// Section - Sync settings
		$settings['sync'] = array(
			'title'  => esc_html__( 'Sync Settings', 'instawp-connect' ),
			'desc'   => esc_html__( 'This section only applicable for the sync settings.', 'instawp-connect' ),
			'fields' => array(
				array(
					'id'      => 'instawp_default_user',
					'type'    => 'select2',
					'remote'  => true,
					'action'  => 'instawp_handle_select2',
					'event'   => 'instawp_get_users',
					'title'   => esc_html__( 'Default User', 'instawp-connect' ),
					'desc'    => esc_html__( 'This option will allow to set default user for events syncing.', 'instawp-connect' ),
					'options' => self::get_select2_default_selected_option( 'instawp_default_user' ),
				),
				array(
					'id'       => 'instawp_sync_tab_roles',
					'type'     => 'select2',
					'remote'   => true,
					'multiple' => true,
					'action'   => 'instawp_handle_select2',
					'event'    => 'instawp_sync_tab_roles',
					'title'    => esc_html__( 'Sync Tab Access', 'instawp-connect' ),
					'desc'     => esc_html__( 'This option will allow to set roles for sync tab. Only assigned role\'s users can access the syncing features.', 'instawp-connect' ),
					'options'  => self::get_select2_default_selected_option( 'instawp_sync_tab_roles' ),
				),
			),
		);

		// Section - 2-way sync
		$sync_fields = apply_filters( 'instawp/filters/2waysync/event_providers', array(
			array(
				'id'      => 'post',
				'title'   => __( 'Posts', 'instawp-connect' ),
				'tooltip' => __( 'Enabling this option will allow plugin to log events related to all posts, pages and custom post types.', 'instawp-connect' ),
				'default' => 'on',
			),
			array(
				'id'      => 'term',
				'title'   => __( 'Taxonomies', 'instawp-connect' ),
				'tooltip' => __( 'Enabling this option will allow plugin to log events related to all categories tags and custom taxonomies.', 'instawp-connect' ),
				'default' => 'on',
			),
			array(
				'id'      => 'user',
				'title'   => __( 'Users', 'instawp-connect' ),
				'tooltip' => __( 'Enabling this option will allow plugin to log events related to all users.', 'instawp-connect' ),
				'default' => 'on',
			),
			array(
				'id'      => 'plugin',
				'title'   => __( 'Plugins', 'instawp-connect' ),
				'tooltip' => __( 'Enabling this option will allow plugin to log events related to plugins.', 'instawp-connect' ),
				'default' => 'on',
			),
			array(
				'id'      => 'theme',
				'title'   => __( 'Themes', 'instawp-connect' ),
				'tooltip' => __( 'Enabling this option will allow plugin to log events related to all themes.', 'instawp-connect' ),
				'default' => 'on',
			),
			array(
				'id'      => 'menu',
				'title'   => __( 'Navigation Menu', 'instawp-connect' ),
				'tooltip' => __( 'Enabling this option will allow plugin to log Navigation menu item related changes.', 'instawp-connect' ),
				'default' => 'on',
			),
			array(
				'id'      => 'customizer',
				'title'   => __( 'WP Customizer', 'instawp-connect' ),
				'tooltip' => __( 'Enabling this option will allow plugin to log WP Customizer.', 'instawp-connect' ),
				'default' => 'on',
			),
			array(
				'id'      => 'option',
				'title'   => __( 'WP Options (Beta)', 'instawp-connect' ),
				'tooltip' => __( 'Enabling this option will allow plugin to log WordPress options.', 'instawp-connect' ),
			),
			array(
				'id'      => 'wc',
				'title'   => __( 'WooCommerce (Beta)', 'instawp-connect' ),
				'tooltip' => __( 'Enabling this option will allow plugin to log WooCommerce events.', 'instawp-connect' ),
			),
		) );

		$sync_fields = array_map( function ( $value ) {
			$value['source']  = 'source_' . $value['id'];
			$value['id']      = 'instawp_sync_' . $value['id'];
			$value['type']    = 'toggle';
			$value['class']   = isset( $value['class'] ) ? $value['class'] . ' save-ajax' : 'save-ajax';
			$value['default'] = isset( $value['default'] ) ? $value['default'] : 'off';

			return $value;
		}, $sync_fields );

		$settings['sync_events'] = array(
			'title'      => esc_html__( 'Sync Events Settings', 'instawp-connect' ),
			'desc'       => esc_html__( 'This section only applicable for the sync event settings.', 'instawp-connect' ),
			'grid_class' => 'grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 gap-6',
			'fields'     => $sync_fields,
		);

		return apply_filters( 'instawp/filters/plugin_settings', $settings );
	}

	public static function get_management_settings() {
		$settings  = array();
		$heartbeat = Option::get_option( 'instawp_rm_heartbeat', 'on' );
		$heartbeat = empty( $heartbeat ) ? 'on' : $heartbeat;

		// Section - Heartbeat
		$settings['heartbeat'] = array(
			'title'  => __( 'Heartbeat', 'instawp-connect' ),
			'desc'   => __( 'Periodically send heartbeat to the InstaWP dashboard.', 'instawp-connect' ),
			'fields' => array(
				array(
					'id'      => 'instawp_rm_heartbeat',
					'type'    => 'toggle',
					'title'   => __( 'Heartbeat', 'instawp-connect' ),
					'tooltip' => __( 'Enable this option to manage your website directly from the InstaWP Dashboard. It will send information like wp_version, php_version, theme name, number of published posts, number of published pages, total number of users, total_size of the wordpress site etc. to InstaWP.', 'instawp-connect' ),
					'desc'    => __( 'Disabling this will automatically disconnect this site from the InstaWP Dashboard after 1 hour.', 'instawp-connect' ),
					'class'   => 'save-ajax',
					'default' => 'on',
				),
				array(
					'id'           => 'instawp_api_heartbeat',
					'type'         => 'number',
					'title'        => __( 'Heartbeat Interval (Minutes)', 'instawp-connect' ),
					'tooltip'      => __( 'It is the interval of heartbeat in minutes.', 'instawp-connect' ),
					'desc'         => __( 'Minimum is 60 minutes and maximum is 240 minutes.', 'instawp-connect' ),
					'placeholder'  => '240',
					'class'        => '!w-80',
					'parent_class' => ( $heartbeat !== 'on' ) ? 'hidden' : '',
					'attributes'   => array(
						'min' => 60,
						'max' => 240,
					),
				),
			),
		);

		// Section - Management
		$settings['management'] = array(
			'title'      => __( 'Site Management', 'instawp-connect' ),
			'desc'       => sprintf( __( 'Update your website\'s site management settings. To use this feature in the InstaWP dashboard, switch on the beta program from %s section.', 'instawp-connect' ), '<a href="https://app.instawp.io/user/profile" target="_blank">' . __( 'My Accounts', 'instawp-connect' ) . '</a>' ),
			'grid_class' => 'grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 gap-6',
			'fields'     => array(
				array(
					'id'      => 'instawp_rm_database_manager',
					'type'    => 'toggle',
					'title'   => __( 'Database Manager', 'instawp-connect' ),
					'tooltip' => __( 'Enable this option to manage your database directly from the InstaWP Dashboard.', 'instawp-connect' ),
					'class'   => 'save-ajax',
					'default' => 'off',
				),
				array(
					'id'      => 'instawp_rm_install_plugin_theme',
					'type'    => 'toggle',
					'title'   => __( 'Install Plugin / Themes', 'instawp-connect' ),
					'tooltip' => __( 'Enable this option to install plugins and themes directly from the InstaWP Dashboard.', 'instawp-connect' ),
					'class'   => 'save-ajax',
					'default' => 'off',
				),
				array(
					'id'      => 'instawp_rm_update_core_plugin_theme',
					'type'    => 'toggle',
					'title'   => __( 'Update Core / Plugin / Themes', 'instawp-connect' ),
					'tooltip' => __( 'Enable this option to update WordPress core, themes and plugins directly from the InstaWP Dashboard.', 'instawp-connect' ),
					'class'   => 'save-ajax',
					'default' => 'on',
				),
				array(
					'id'      => 'instawp_rm_activate_deactivate',
					'type'    => 'toggle',
					'title'   => __( 'Activate / Deactivate', 'instawp-connect' ),
					'tooltip' => __( 'Enable this option to activate or deactivate themes and plugins directly from the InstaWP Dashboard.', 'instawp-connect' ),
					'class'   => 'save-ajax',
					'default' => 'on',
				),
				array(
					'id'      => 'instawp_rm_config_management',
					'type'    => 'toggle',
					'title'   => __( 'Config Management', 'instawp-connect' ),
					'tooltip' => __( 'Enable this option to manage the Wordpress constant values directly from the InstaWP Dashboard.', 'instawp-connect' ),
					'class'   => 'save-ajax',
					'default' => 'off',
				),
				array(
					'id'      => 'instawp_rm_inventory',
					'type'    => 'toggle',
					'title'   => __( 'Site Inventory', 'instawp-connect' ),
					'tooltip' => __( 'Enable this option to manage WordPress version, themes and plugins directly from the InstaWP Dashboard.', 'instawp-connect' ),
					'class'   => 'save-ajax',
					'default' => 'on',
				),
				array(
					'id'      => 'instawp_rm_debug_log',
					'type'    => 'toggle',
					'title'   => __( 'Debug Log', 'instawp-connect' ),
					'tooltip' => __( 'Enable this option to show debug logs directly on the InstaWP Dashboard.', 'instawp-connect' ),
					'class'   => 'save-ajax',
					'default' => 'off',
				),
			),
		);

		return apply_filters( 'instawp/filters/management_settings', $settings );
	}

	public static function get_developer_settings() {
		$settings['developer'] = array(
			'title'    => esc_html__( 'Developer Options', 'instawp-connect' ),
			'desc'     => esc_html__( 'This section is available only for the developers working in this plugin.', 'instawp-connect' ),
			'internal' => true,
			'fields'   => array(
				array(
					'id'          => 'instawp_api_options',
					'name'        => 'api_url',
					'type'        => 'url',
					'title'       => esc_html__( 'API Domain', 'instawp-connect' ),
					'placeholder' => INSTAWP_API_DOMAIN_PROD,
					'default'     => INSTAWP_API_DOMAIN_PROD,
				),
				array(
					'id'    => 'instawp_qa_tools',
					'type'  => 'qa_tools',
					'title' => esc_html__( 'Quick Access Tools', 'instawp-connect' ),
				),
				array(
					'id'      => 'instawp_keep_db_sql_after_migration',
					'type'    => 'toggle',
					'title'   => __( 'Keep DB SQL File', 'instawp-connect' ),
					'tooltip' => __( 'Enabling this option will keep the db.sql file after migration on destination website.', 'instawp-connect' ),
					'class'   => 'save-ajax',
					'default' => 'off',
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

		return apply_filters( 'instawp/filters/developer_settings', $settings );
	}

	public static function get_args_option( $key = '', $args = array(), $default_value = '' ) {
		$default_value = is_array( $default_value ) && empty( $default_value ) ? array() : $default_value;
		$value         = ! is_array( $default_value ) && ! is_bool( $default_value ) && empty( $default_value ) ? '' : $default_value;
		$key           = empty( $key ) ? '' : $key;

		if ( ! empty( $key ) && ! empty( $args[ $key ] ) ) {
			$value = $args[ $key ];
		}

		return $value;
	}

	public static function get_pro_subscription_url( $pro_subscription_slug = 'subscriptions' ) {
		return Helper::get_api_domain() . '/' . $pro_subscription_slug;
	}

	public static function get_unsupported_plugins() {
		$unsupported_plugins = array(
			array(
				'slug'       => 'breeze/breeze.php',
				'name'       => esc_html( 'Breeze' ),
				'author_url' => esc_url( 'https://www.cloudways.com/' ),
			),
			array(
				'slug'       => 'malcare-security/malcare.php',
				'name'       => esc_html( 'MalCare WordPress Security Plugin' ),
				'author_url' => esc_url( 'https://www.malcare.com/' ),
			),
			array(
				'slug'       => 'ithemes-security-pro/ithemes-security-pro.php',
				'name'       => esc_html( 'Solid Security Pro' ),
				'author_url' => esc_url( 'https://solidwp.com/' ),
			),
		);

		return apply_filters( 'instawp/filters/get_unsupported_plugins', $unsupported_plugins );
	}

	public static function get_select2_default_selected_option( $option ) {
		if ( $option === 'instawp_default_user' ) {
			$user_id = Option::get_option( $option, 0 );
			if ( $user_id ) {
				if ( ! function_exists( 'get_user_by' ) ) {
					require_once ABSPATH . 'wp-includes/pluggable.php';
				}

				$user = get_user_by( 'ID', $user_id );
				if ( ! empty( $user ) ) {
					return array( $user->data->ID => $user->data->user_login );
				}
			}
		} elseif ( $option === 'instawp_sync_tab_roles' ) {
			$role_options   = array();
			$all_roles      = wp_roles()->roles;
			$selected_roles = Option::get_option( $option );

			if ( ! empty( $selected_roles ) ) {
				foreach ( $selected_roles as $role ) {
					$role_options[ $role ] = isset( $all_roles[ $role ] ) ? $all_roles[ $role ]['name'] : $role;
				}
			}

			return $role_options;
		} elseif ( $option === 'instawp_hide_plugin_to_users' ) {
			$users_data     = array();
			$selected_users = Option::get_option( $option );
			$selected_users = ! empty( $selected_users ) ? $selected_users : array();

			if ( ! empty( $selected_users ) ) {
				$users = get_users( array(
					'fields' => array( 'ID', 'user_login' ),
				) );

				foreach ( $users as $user ) {
					$users_data[ $user->ID ] = $user->user_login;
				}
			}

			return $users_data;
		}

		return array();
	}
}
