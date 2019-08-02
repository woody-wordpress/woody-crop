<?php
if (! defined ( 'ABSPATH' )) {
	die ( 'No script kiddies please!' );
}

if ( ! class_exists( 'YoImagesSettingsPage' ) ) {

	class YoImagesSettingsPage {
		
		public function __construct() {
			add_action ( 'admin_menu', array ( $this, 'add_plugin_page_menu_item' ) );
			add_action ( 'admin_init', array ( $this, 'init_admin_page' ) );
		}
		
		public function add_plugin_page_menu_item() {
			add_options_page( __( 'YoImages settings', YOIMG_DOMAIN ), 'YoImages', 'manage_options', 'yoimg-settings', array( $this, 'create_admin_page' ) );
		}
		
		public function create_admin_page() {
			if ( !current_user_can( 'manage_options' ) ) {
				wp_die( __( 'You do not have sufficient permissions to access this page.' ) );
			}
			global $yoimg_modules;
			global $yoimg_plugins_url;
			$settings = apply_filters( 'yoimg_settings', array() );
			?>
			<div class="wrap" id="yoimg-settings-wrapper">
				<h2><?php _e( 'YoImages settings', YOIMG_DOMAIN ); ?></h2>
				<?php
				if( isset( $_GET[ 'tab' ] ) ) {
					$active_tab = $_GET[ 'tab' ];
				} else {
					foreach ( $yoimg_modules as $key=>$value ) {
						if ( $value['has-settings'] ) {
							$active_tab = $key;
							break;
						}
					}
					if ( ! isset( $active_tab ) ) {
						$active_tab = $settings[0]['option']['page'];
					}
				}
				?>
				<h2 class="nav-tab-wrapper">
					<?php
					foreach ( $settings as $setting ) {
						$option_page = $setting['option']['page'];
					?>
						<a href="?page=yoimg-settings&tab=<?php echo $option_page; ?>" class="nav-tab <?php echo $active_tab == $option_page ? 'nav-tab-active' : ''; ?>"><?php echo $setting['option']['title']; ?></a>
					<?php
					}
					?>
				</h2>
				<?php
				if ( isset( $yoimg_modules[$active_tab] ) && $yoimg_modules[$active_tab]['has-settings'] ) {
				?>
					<form method="post" action="options.php">
					<?php
						settings_fields( $active_tab . '-group' );
						do_settings_sections( $active_tab );
						submit_button(); 
					?>
					</form>
				<?php
				} elseif ( isset( $yoimg_modules[$active_tab] ) ) {
				?>
					<div class="message error">
						<p><?php _e( 'You are trying to access a YoImages\' module that has no settings page', YOIMG_DOMAIN ); ?></p>
					</div>
				<?php
				} elseif ( isset ( $yoimg_plugins_url[$active_tab] ) ) {
				?>
					<div class="message update-nag">
						<p><?php _e( 'This YoImages\' module is not active or installed, please activate it in the plugins administration page or install it from here:', YOIMG_DOMAIN ); ?> <a href="<?php echo $yoimg_plugins_url[$active_tab]; ?>"><?php echo $yoimg_plugins_url[$active_tab]; ?></a></p>
					</div>
				<?php
				} else {
				?>
					<div class="message error">
						<p><?php _e( 'Unknown module', YOIMG_DOMAIN ); ?></p>
					</div>
				<?php
				}
				?>
			</div>
			<?php
		}
	
		private function sanitize_item(&$item, $key) {
			if ( is_string( $item ) ) {
				$item = sanitize_text_field( $item );
			} elseif ( is_array( $item ) ) {
				$this->sanitize_input( $item );
			}
		}

		private function sanitize_input(&$input) {
			array_walk( $input, array( $this, 'sanitize_item' ) );
		}

		public function sanitize($input) {
			$this->sanitize_input( $input );
			return $input;
		}

		public function init_admin_page() {
			$settings = apply_filters( 'yoimg_settings', array() );
			foreach ( $settings as $setting ) {
				$option_page = $setting['option']['page'];
				register_setting( $setting['option']['option_group'], $setting['option']['option_name'], $setting['option']['sanitize_callback'] );
				add_filter( 'sanitize_option_' . $setting['option']['option_name'], array( $this, 'sanitize' ) );
				foreach ( $setting['option']['sections'] as $section ) {
					$section_id = $section['id'];
					add_settings_section( $section_id, $section['title'], $section['callback'], $option_page );
					foreach ( $section['fields'] as $field ) {
						add_settings_field( $field['id'], $field['title'], $field['callback'], $option_page, $section_id );
					}
				}
			}
		}
	}
	
	new YoImagesSettingsPage();

}
