<?php

defined( 'ABSPATH' ) || die;

class InstaWP_Rest_Api_Content extends InstaWP_Rest_Api {

	public function __construct() {
		parent::__construct();

		add_action( 'rest_api_init', array( $this, 'add_api_routes' ) );
	}

	public function add_api_routes() {
		register_rest_route( $this->namespace . '/' . $this->version_2 . '/content', '/posts', array(
			'methods'             => 'GET',
			'callback'            => array( $this, 'get_posts_count' ),
			'permission_callback' => '__return_true',
		) );

		register_rest_route( $this->namespace . '/' . $this->version_2 . '/content', '/posts/(?P<post_type>[a-z_-]+)', array(
			'methods'             => 'GET',
			'callback'            => array( $this, 'get_posts' ),
			'permission_callback' => '__return_true',
		) );

		register_rest_route( $this->namespace . '/' . $this->version_2 . '/content', '/media', array(
			'methods'             => 'GET',
			'callback'            => array( $this, 'get_media' ),
			'permission_callback' => '__return_true',
		) );

        register_rest_route( $this->namespace . '/' . $this->version_2 . '/content', '/user-roles', array(
            'methods'             => 'GET',
            'callback'            => array( $this, 'get_user_roles' ),
            'permission_callback' => '__return_true',
        ) );

		register_rest_route( $this->namespace . '/' . $this->version_2 . '/content', '/users', array(
			'methods'             => 'GET',
			'callback'            => array( $this, 'get_users' ),
			'permission_callback' => '__return_true',
		) );
	}

	/**
	 * Handle response for pull api
	 *
	 * @param WP_REST_Request $request
	 *
	 * @return WP_REST_Response
	 */
	public function get_posts_count( WP_REST_Request $request ) {

		$response = $this->validate_api_request( $request );
		if ( is_wp_error( $response ) ) {
			return $this->throw_error( $response );
		}

		$response   = array();
		$post_types = get_post_types( array(
			'public' => true,
		) );
		foreach ( $post_types as $post_type ) {
			$response[ $post_type ] = array_sum( ( array ) wp_count_posts( $post_type ) );
		}

		return $this->send_response( $response );
	}

	/**
	 * Handle response for pull api
	 *
	 * @param WP_REST_Request $request
	 *
	 * @return WP_REST_Response
	 */
	public function get_posts( WP_REST_Request $request ) {

		$response = $this->validate_api_request( $request );
		if ( is_wp_error( $response ) ) {
			return $this->throw_error( $response );
		}

		$post_type = $request->get_param( 'post_type' );
		$exists    = post_type_exists( $post_type );
		if ( ! $exists ) {
			return $this->send_response( array(
				'success' => false,
				'message' => 'Post type not exists!',
			) );
		}

		$response       = array();
		$posts_per_page = $request->get_param( 'count' );
		$offset         = $request->get_param( 'offset' );
		$posts          = get_posts( array(
			'posts_per_page' => ! empty( $posts_per_page ) ? $posts_per_page : 50,
			'offset'         => ! empty( $offset ) ? $offset : 0,
			'post_type'      => $post_type,
			'post_status'    => 'any',
		) );

		foreach ( $posts as $post ) {
			$response[] = array(
				'id'             => $post->ID,
				'title'          => $post->post_title,
				'excerpt'        => $post->post_excerpt,
				'slug'           => $post->post_name,
				'status'         => $post->post_status,
				'parent_id'      => $post->post_parent,
				'author'         => get_the_author_meta( 'display_name', $post->post_author ),
				'created_at'     => $post->post_date_gmt,
				'updated_at'     => $post->post_modified_gmt,
				'comment_status' => $post->comment_status,
				'comment_count'  => $post->comment_count,
				'preview_url'    => get_permalink( $post->ID ),
				'edit_url'       => apply_filters( 'get_edit_post_link', admin_url( 'post.php?post=' . $post->ID . '&action=edit' ), $post->ID, '' ),
			);
		}

		return $this->send_response( $response );
	}

	/**
	 * Handle response for pull api
	 *
	 * @param WP_REST_Request $request
	 *
	 * @return WP_REST_Response
	 */
	public function get_media( WP_REST_Request $request ) {

		$response = $this->validate_api_request( $request );
		if ( is_wp_error( $response ) ) {
			return $this->throw_error( $response );
		}

		$response       = array();
		$sizes          = get_intermediate_image_sizes();
		$posts_per_page = $request->get_param( 'count' );
		$offset         = $request->get_param( 'offset' );
		$attachments    = get_posts( array(
			'posts_per_page' => ! empty( $posts_per_page ) ? $posts_per_page : 50,
			'offset'         => ! empty( $offset ) ? $offset : 0,
			'post_type'      => 'attachment',
			'post_status'    => 'any',
		) );

		foreach ( $attachments as $attachment ) {
			$image_sizes = array();
			foreach ( $sizes as $size ) {
				$image_sizes[ $size ] = wp_get_attachment_image_url( $attachment->ID, $size );
			}
			$response[] = array(
				'id'          => $attachment->ID,
				'title'       => $attachment->post_title,
				'slug'        => $attachment->post_name,
				'caption'     => apply_filters( 'wp_get_attachment_caption', $attachment->post_excerpt, $attachment->ID ),
				'description' => $attachment->post_content,
				'alt'         => get_post_meta( $attachment->ID, '_wp_attachment_image_alt', true ),
				'images'      => array_filter( $image_sizes ),
				'status'      => $attachment->post_status,
				'parent_id'   => $attachment->post_parent,
				'author'      => get_the_author_meta( 'display_name', $attachment->post_author ),
				'created_at'  => $attachment->post_date_gmt,
				'updated_at'  => $attachment->post_modified_gmt,
				'preview_url' => wp_get_attachment_url( $attachment->ID ),
				'edit_url'    => apply_filters( 'get_edit_post_link', admin_url( 'post.php?post=' . $attachment->ID . '&action=edit' ), $attachment->ID, '' ),
			);
		}

		return $this->send_response( $response );
	}

    /**
     * Handle response for site inventory.
     *
     * @param WP_REST_Request $request
     *
     * @return WP_REST_Response
     */
    public function get_user_roles( WP_REST_Request $request ) {

        $response = $this->validate_api_request( $request );
        if ( is_wp_error( $response ) ) {
            return $this->throw_error( $response );
        }

        $data = array();
        $i    = 1;
        $roles = wp_roles()->get_names();

        foreach ( $roles as $role => $name ) {
            $data[] = array(
                'id'    => $i,
                'name'  => $name,
                'value' => $role,
            );
            ++$i;
        }

        return $this->send_response( array(
            'success' => true,
            'roles'   => $data,
        ) );
    }

	/**
	 * Handle response for pull api
	 *
	 * @param WP_REST_Request $request
	 *
	 * @return WP_REST_Response
	 */
	public function get_users( WP_REST_Request $request ) {

		$response = $this->validate_api_request( $request );
		if ( is_wp_error( $response ) ) {
			return $this->throw_error( $response );
		}

        $params   = $this->filter_params( $request );
		$response = array(
            'total' => count_users()['total_users'],
            'users' => array(),
        );
		$users    = get_users( $params );
		foreach ( $users as $user ) {
			$response['users'][] = array(
				'id'             => $user->ID,
				'roles'          => $user->roles,
				'username'       => $user->data->user_login,
				'email'          => $user->data->user_email,
				'display_name'   => $user->data->display_name,
				'created_at'     => $user->data->user_registered,
				'gravatar_image' => get_avatar_url( $user->ID ),
			);
		}

		return $this->send_response( $response );
	}
}

new InstaWP_Rest_Api_Content();