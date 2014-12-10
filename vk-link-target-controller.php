<?php
/*
Plugin Name: VK Link Target Controller
Plugin URI: https://github.com/kurudrive/vk-link-target-controller
Description: Allow you to link a post title from the recent posts list to another page (internal or external link) rather than link to the actual post page
Version: 0.1
Author: Vektor,Inc.
Author URI: http://www.vektor-inc.co.jp/
License: GPL2
License URI: https://www.gnu.org/licenses/gpl-2.0.html
Domain Path: /languages
Text Domain: vk-link-target-controller
*/

if ( ! class_exists( 'VK_Link_Target_Controller' ) ) {

	class VK_Link_Target_Controller {

		public $user_capability_link 	 = 'edit_posts'; //can save a link for a redirection
		public $user_capability_settings = 'manage_options'; //can access to the settings page

		/**
		* initialize_admin function
		* Activate plugin features on edit screen
		* @access public
		* @return void
		*/
		function initialize_admin() {

			//allow meta box for user with permission
			if ( current_user_can( $this->user_capability_link ) ) {
				add_action( 'add_meta_boxes', array( $this, 'add_link_meta_box' ) ); //add a meta box for the link to the post edit screen
				add_action( 'save_post', array( $this, 'save_link' ) ); //save meta box data
			}
		}
		
		/**
		* create_settings_page function
		* Build the settings page
		* @access public
		* @return void
		*/
		function create_settings_page() {
			
			//create page for user with permission
			if ( current_user_can( $this->user_capability_settings ) ) {
				
				add_options_page( 
					__( 'VK Link Target Controller', 'vk-link-target-controller' ), 
					__( 'Link Target Controller', 'vk-link-target-controller' ), 
					$this->user_capability_settings, 
					'vk-ltc', 
					array( $this, 'settings_page_html' )
				); 	//menu and page
				
				register_setting( 
					'vk-ltc-options', 
					'custom-post-types'
					//array( $this, 'sanitize_settings' )
				); //settings options (use WordPress Settings API)
			}	
		}
		
		/**
		* settings_page_html function
		* Display HTML for the settings page on WordPress admin
		* @access public
		* @return void
		*/
		function settings_page_html() { ?>

			<div class="wrap" id="vk-link-target-controller">
				<h2><?php esc_html_e( 'VK Link Target Controller', 'vk-link-target-controller' ); ?></h2>

				<div style="width:68%;display:inline-block;vertical-align:top;">
					<form method="post" action="options.php">
						<?php settings_fields( 'vk-ltc-options' ); //display nonce and other control hidden fields ?>
						<table class="form-table">
							<tr valign="top">
								<th scope="row">
									<?php esc_html_e( 'Display on the following post types', 'vk-link-target-controller' );  ?>
								</th>
								<td>
									<?php $post_types = $this->get_public_post_types(); //array of post types
									foreach ( $post_types as $slug => $label ) { 
										$options_exist = get_option( 'custom-post-types' );
										var_dump($options_exist);
										$checked = ( isset( $options_exist ) && 1 != $options_exist  && in_array( $slug, $options_exist ) ) ? 'checked="checked"' : '' ; ?>
										<input type="checkbox" name="custom-post-types[]" id="custom-post-types-<?php echo $slug; ?>" value="<?php echo $slug; ?>" <?php echo $checked; ?> />
										<label for="custom-post-types-<?php echo $slug; ?>"><?php echo $label; ?></label><br /><?php 
									} ?>
								</td>
							</tr>
						</table>
						<?php submit_button(); ?>
					</form>
				</div>
		
				<div style="width:29%;display:block; overflow:hidden;float:right;">';
				</div>
			
			</div>
		<?php
		}

		/**
		* sanitize_settings function
		* Callback function that sanitizes the option's value
		* @access public
		* @return void
		*/
		function sanitize_settings() {
			/*
			if ( isset( $_POST['custom-post-types'] ) ) {
				var_dump($_POST['custom-post-types']);
				die();
			}
			return true;*/
		}

		/**
		* add_link_meta_box function
		* Add a meta box for the link to the post edit screen
		* @access public
		* @link http://codex.wordpress.org/Function_Reference/add_meta_box WordPress documentation
		* @return void
		*/
		function add_link_meta_box() {

			//load meta box only for post and custom post types based on post
			$current_screen = get_current_screen();
			if ( 'post' == $current_screen->base ) {
				add_meta_box( 
					'vk-ltc-url', //meta value key
					__( 'URL to redirect to', 'vk-link-target-controller' ),
					array( $this, 'render_link_meta_box' ),
					null,
					'normal',
					'high'
				);				
			}
		}

		/**
		* render_link_meta_box function
		* Display HTML form for link insertion
		* @param WP_Post $post The object for the current post/custom post
		* @return void
		*/
		function render_link_meta_box( $post ) {

			//nonce field
			wp_nonce_field( 'vk-ltc-link', 'vk-ltc-link-nonce' );

			//retrieve existing string value from BD (empty if doesn't exist)
			$value = get_post_meta( $post->ID, 'vk-ltc-url', true );

			//display form
			echo '<p>' . __( 'Enter here the URL you want the title of this post to redirect to.', 'vk-link-target-controller' ) . '</p>';
			echo '<label class="hidden" for="vk-ltc-link-field">';
			_e( 'URL to redirect to', 'vk-link-target-controller' );
			echo '</label> ';
			echo '<input type="text" id="vk-ltc-link-field" name="vk-ltc-link-field"';
			echo ' value="' . esc_attr( $value ) . '" size="50" />';
			echo '<p>' . __( 'URL must have the http:// before.', 'vk-link-target-controller' ) . ' ' . __( 'Make sure the URL is correct.', 'vk-link-target-controller' ) . '</p>';
		}


		/**
		* save_link function
		* Save the link when the post is saved
		* @access public		
		* @param int $post_id The ID of the post being saved
		* @return int $post_id|void The ID of the post or nothing if saved in DB
		*/
		function save_link( $post_id ) {

			//kill unauthorized user (double verification)
			if ( ! current_user_can( $this->user_capability_link ) ) { 
				wp_die( 'You do not have sufficient permissions to access this page.', 'vk-link-target-controller' );
			} else {
				//check form
				if ( isset( $_POST['vk-ltc-link-field'] ) && wp_verify_nonce( $_POST['vk-ltc-link-nonce'], 'vk-ltc-link' ) ) {
					
					//sanitize the user input
					$link = sanitize_text_field( $_POST['vk-ltc-link-field'] );

					//check is link is allowed content
					if ( $this->is_url( $link ) || empty( $link ) ) {
						//update the meta field
						update_post_meta( $post_id, 'vk-ltc-url', $link );
						return $post_id;
					} else {
						return $post_id;	
					}
				} else {
					return $post_id;
				}
			}
		}

		/**
		* redirect function
		* Check if the requested URL has to be redirected,
		* if yes redirect the user to the right URL
		* @access public		
		* @return void
		*/
		function redirect() {

		}

		/**
		* is_url function
		* Utility function to check if given string is an URL
		* @access public		
		* @param string $url The string to test
		* @return bool
		*/
		function is_url( $url ) {

			$is_url 	= false;
			$no_failure = true;

			//prevent parse_url from causing warning error
			$parse_url_fails_on = array(
				'http:///' => 8,
				'http://:' => 8,
				);

			foreach ( $parse_url_fails_on as $fail_on_this => $length ) {
				$check_on = substr( $url, 0, $length );
				if ( $check_on == $fail_on_this ) {
					$no_failure = false;
				}
			}

			if ( 'http://' != $url && $no_failure ) {
				$components = parse_url( $url );
				if ( false != $components && isset( $components->scheme ) ) {
					$is_url = true;
				}
			}
			return $is_url;
		}

		/**
		* get_public_post_types function
		* Utility function to get post types and custom post types slugs and labels
		* @access public		
		* @return array( slug => label )
		*/
		function get_public_post_types() {

			$public_post_types = array();

			//default post type
			$post_obj = get_post_type_object( 'post' );

			$public_post_types[ $post_obj->name ] = $post_obj->label;

			//gets all custom post types set PUBLIC
			$args = array(
				'public'   => true,
				'_builtin' => false,
			);
			$custom_types_obj = get_post_types( $args, 'objects' ); 

			foreach ( $custom_types_obj as $custom_type_obj ) {
				$public_post_types[ $custom_type_obj->name ] = $custom_type_obj->label;
			}

			return $public_post_types;
		}
	}

}

//instanciation
$vk_link_target_controller = new VK_Link_Target_Controller();

if ( isset( $vk_link_target_controller ) ) {
	//front
	//add_action( 'init', array( $vk_link_target_controller, 'redirect' ), 1 ); // add the redirect action, high priority

	//set up admin
	add_action( 'admin_init', array( $vk_link_target_controller, 'initialize_admin' ) );
	add_action( 'admin_menu', array( $vk_link_target_controller, 'create_settings_page' ) );
}