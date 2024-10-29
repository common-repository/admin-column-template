<?php
/**
 * Plugin name: Admin column template
 * Author: Julien Maury
 * Author URI: https://tweetpress.fr
 * Version: 0.4.1
 * Description: Useful column in admin post listing to show which template is used
 */
if ( ! function_exists( 'add_action' ) ) {
	die( 'No direct load !' );
}

class Admin_Column_Template {

	protected static $instance;
	protected $allowed_post_types;
	protected $meta_key;

	protected function __construct() {
		$this->meta_key = '_wp_page_template';
		$default = array_values( get_post_types() );
		$this->allowed_post_types = apply_filters( 'admin_column_template_allowed_post_types', $default );
	}

	public function hooks() {
		foreach ( (array) $this->allowed_post_types as $post_type ) {
			// this "s" is mandatory here
			add_filter( 'manage_' . $post_type . 's_custom_column' , [ $this, 'column_content' ], 111, 2 );
			add_filter( 'manage_' . $post_type . 's_columns' , [ $this, 'add_column' ] );
		}

		add_filter( 'restrict_manage_posts', [ $this, 'add_select_by' ] );
		add_action( 'pre_get_posts', [ $this, 'pre_get_posts' ] );
		add_filter( 'admin_column_template_info', [ $this, 'flip_name' ], 10 , 4 );
	}

	/**
	 * Make the real name as default display
	 * still "overridable"
	 * @param $info
	 * @param $post_id
	 * @param $theme
	 * @param $template
	 * @author Julien Maury
	 * @return string
	 */
	public function flip_name( $info, $post_id, $theme, $template ) {
		$templates = array_flip( get_page_templates() );
		if ( ! isset( $templates[ $template ] ) ) {
			return $info;
		}

		return $theme->get( 'Name' ) . ' : ' . $templates[ $template ];
	}

	/**
	 * @return self
	 */
	final public static function getInstance() {
		if ( is_null( self::$instance ) ) {
			self::$instance = new static;
		}
		return self::$instance;
	}

	/**
	 * Provide select by template
	 * @author Julien Maury
	 */
	public function add_select_by() {
		echo '<select name="_page_template" id="_page_template" class="postform">';
		echo '<option value="">' . __( 'Template' ) . '</option>';
		foreach ( get_page_templates() as $name => $template ) {
			$selected = isset( $_GET['_page_template'] ) ? selected( esc_attr( $_GET['_page_template'] ), esc_attr( $template ), false ) : '';
			echo '<option ' . $selected . ' value="' . esc_attr( $template ) . '">' . esc_html( $name ) . '</option>';
		}
		echo '</select>';
	}

	/**
	 * @param $query
	 * @author Julien Maury
	 * @return mixed
	 */
	public function pre_get_posts( $query ) {

		// don't mess up with other queries
		if ( ! is_admin() ) {
			return false;
		}

		if ( ! empty( $_GET['_page_template'] ) ) {
			$query->set( 'meta_query', [
				[
					'key'     => $this->meta_key,
					'value'   => esc_html( $_GET['_page_template'] ),
					'compare' => '=',
				]
			] );
		}
	}

	/**
	 * @param $column
	 * @param $post_id
	 * @author Julien Maury
	 */
	public function column_content( $column, $post_id ) {
		if ( 'template_name' === $column ) {
			echo $this->get_template( $post_id );
		}
	}

	/**
	 * @param $columns
	 * @author Julien Maury
	 * @return array
	 */
	public function add_column( $columns ) {
		return array_merge( $columns,
			array(
				'template_name'   => __( 'Template' ),
			) );
	}

	/**
	 * @author Julien Maury
	 *
	 * @param $post_id
	 *
	 * @return false|string
	 */
	public function get_template( $post_id ) {
		$theme    = wp_get_theme();
		$template = get_post_meta( $post_id, $this->meta_key, true );

		if ( empty( $template ) || 'default' === $template ) {
			return __( 'None' );
		}

		return apply_filters( 'admin_column_template_info', $theme->get( 'Name' ) . ' : ' . $template, $post_id, $theme, $template );
	}
}

add_action( 'plugins_loaded', function() {
	if ( ! is_admin() ) {
		return false;
	}
	$i = Admin_Column_Template::getInstance();
	$i->hooks();
});

