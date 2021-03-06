<?php
/*
  Plugin Name: Private File Area
  Plugin URI: https://github.com/Asgaros/private-file-area
  Description: This plugin is to show content only for whom the content is intended.
  Version: 1.0.4
  Author: Han Ting, Xue Tianyu, Wang Yitong, Dimitri, Vitaly, Armin, Thomas Belser
  Author URI: https://chinger-coorp.fb2.frankfurt-university.de/
  Text Domain: pfa

  GNU General Public License, Free Software Foundation <http://creativecommons.org/licenses/GPL/2.0/>
  This program is free software; you can redistribute it and/or modify
  it under the terms of the GNU General Public License as published by
  the Free Software Foundation; either version 2 of the License, or
  (at your option) any later version.

  This program is distributed in the hope that it will be useful,
  but WITHOUT ANY WARRANTY; without even the implied warranty of
  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
  GNU General Public License for more details.

  You should have received a copy of the GNU General Public License
  along with this program; if not, write to the Free Software
  Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA
 */

// Disallow direct access to the plugin file for security reasons.
if (basename($_SERVER['PHP_SELF']) == basename (__FILE__)) {
	die('Sorry, but you cannot access this page directly.');
}

class private_file_area {
	var $saved = false;
	var $localization_domain = 'pfa';
	var $options = array();
	var $options_default = array(
		'blocked_message' => '',
		'list_users' => true,
		'list_roles' => true,
		'run_on_the_content' => true,
		'run_on_the_excerpt' => false
	);

	function __construct() {
		$this->options = array_merge($this->options_default, get_option('pfa_options', array()));

		add_action('add_meta_boxes', array($this, 'pfa_meta_box'));
		add_action('save_post', array($this, 'pfa_meta_box_save'));
		add_action('init', array($this, 'pfa_init'));
		add_action('init', array($this, 'create_post_type'));
		add_shortcode('PFA', array($this, 'pfa_shortcode'));

		// Media Library Stuff
		add_filter('upload_dir', array($this, 'private_custom_upload_dir'));

		// Admin options page
		add_action('admin_menu', array($this, 'pfa_admin_page'));
		add_action('admin_init', array($this, 'pfa_save_settings'));
		add_action('admin_enqueue_scripts', array($this, 'pfa_admin_enqueue_scripts'));

		// Language setup
		load_plugin_textdomain('pfa', false, plugin_basename(dirname(__FILE__)).'/languages/');
	}

	// Administration menu
	public function pfa_admin_page() {
		add_options_page(__('Private File Area', 'pfa'), __('Private File Area', 'pfa'), 'manage_options', 'private-file-area', array($this, 'pfa_admin_options_page'));
	}

	// Options page
	public function pfa_admin_options_page() {
		if (!current_user_can('manage_options')) {
			wp_die(__('You are not allowed to access this page.', 'pfa'));
		}

		?>
		<div id="pfa_options" class="wrap">
			<h2><?php _e('Private File Area', 'pfa'); ?></h2>
			<?php if ($this->saved): ?>
		        <div class="updated">
		            <p><?php _e('Your options have been saved.', 'asgarosforum'); ?></p>
		        </div>
		    <?php endif; ?>
			<form action="" method="post">
				<h3><?php _e('General settings:', 'pfa'); ?></h3>
				<p>
		            <label for="blocked_message"><?php _e('Global Blocked message:', 'pfa'); ?></label>
		            <textarea name="blocked_message"><?php echo stripslashes($this->options['blocked_message']); ?></textarea>
		        </p>
				<p>
		            <label for="run_on_the_content"><?php _e('Use with "the_content" hook:', 'pfa'); ?></label>
		            <input type="checkbox" name="run_on_the_content" <?php if ($this->options['run_on_the_content']) { echo 'checked'; } ?> />
		        </p>
				<p>
		            <label for="run_on_the_excerpt"><?php _e('Use with "the_excerpt" hook:', 'pfa'); ?></label>
		            <input type="checkbox" name="run_on_the_excerpt" <?php if ($this->options['run_on_the_excerpt']) { echo 'checked'; } ?> />
		        </p>

				<h3><?php _e('MetaBox settings:', 'pfa'); ?></h3>
				<p>
		            <label for="list_users"><?php _e('List user names:', 'pfa'); ?></label>
		            <input type="checkbox" name="list_users" <?php if ($this->options['list_users']) { echo 'checked'; } ?> />
					<small><?php _e('Sites with a large number of users should uncheck this option.', 'pfa'); ?></small>
		        </p>
				<p>
		            <label for="list_roles"><?php _e('List user roles:', 'pfa'); ?></label>
		            <input type="checkbox" name="list_roles" <?php if ($this->options['list_roles']) { echo 'checked'; } ?> />
					<small><?php _e('Sites with a large number of user roles should uncheck this option.', 'pfa'); ?></small>
		        </p>
				<p class="submit">
					<input type="submit" name="pfa_options_submit" class="button-primary" value="<?php _e('Save Options', 'pfa'); ?>" />
				</p>
			</form>
		</div>
		<?php
	}

	public function pfa_save_settings() {
		if (isset($_POST['pfa_options_submit']) && !empty($_POST['pfa_options_submit'])) {
	        $saved_ops = array();

	        foreach ($this->options_default as $k => $v) {
	            if (isset($_POST[$k]) && !empty($_POST[$k])) {
	                if (is_bool($v)) {
	                    $saved_ops[$k] = true;
	                } else {
	                    $saved_ops[$k] = esc_sql(stripslashes($_POST[$k]));
	                }
	            } else {
	                if (is_bool($v)) {
	                    $saved_ops[$k] = false;
	                } else {
	                    $saved_ops[$k] = '';
	                }
	            }
	        }

	        update_option('pfa_options', $saved_ops);
			$this->options = array_merge($this->options_default, get_option('pfa_options', array()));
			$this->saved = true;
		}
	}

	public function pfa_admin_enqueue_scripts($hook) {
		global $post_type;
        $plug_url = plugin_dir_url(__FILE__);

		if (strstr($hook, 'private-file-area') !== false || (is_admin() && isset($post_type) && $post_type == 'private_post')) {
            wp_enqueue_style('pma_admin_css', $plug_url.'admin.css');
        }
    }

	function create_post_type() {
		$labels = array(
			'name' => 'Private Posts',
			'singular_name' => 'Private Post',
			'menu_name' => 'Private Posts',
			'name_admin_bar' => 'Private Post',
			'all_items' => 'All Private Posts',
			'add_new' => 'Add New',
			'add_new_item' => 'Add new Private Post',
			'edit_item' => 'Edit Private Post',
			'new_item' => 'New Private Post',
			'view_item' => 'View Private Post',
			'search_items' => 'Search Private Posts',
			'not_found' =>  'No Private Posts found',
			'not_found_in_trash' => 'No Private Posts found in trash',
			'parent_item_colon' => 'Parent Private Post'
		);

		$args = array(
		    'labels' => $labels,
		    'description' => 'Posts for private access only.',
		    'public' => true,
		    'exclude_from_search' => true,
		    'menu_position' => 5,
		    'menu_icon' => 'dashicons-lock',
		    'supports' => array('title', 'editor', 'author', 'thumbnail', 'excerpt', 'custom-fields', 'comments'),
			'has_archive' => true,
			'rewrite' => array('slug' => 'private-post', 'with_front' => false)
		);

		register_post_type('private_post', $args);
	}

	// Adding a meta box to the private_post edit screen
	public function pfa_meta_box() {
		add_meta_box('pfa_meta_box', __('Accessibility settings', 'pfa'), array($this, 'pfa_meta_box_inner'), 'private_post');
	}

	// Printing the box content of the meta box
	public function pfa_meta_box_inner() {
		global $post, $wp_roles;

		// Get our options
		$savedroles = get_post_meta($post->ID, 'pfa_post_meta_roles', true);
		$savedusers = get_post_meta($post->ID, 'pfa_post_meta_users', true);
		$savedoptions = get_post_meta($post->ID, 'pfa_post_meta_options', true);

		// Use nonce for verification
		wp_nonce_field('pfa_meta_box_inner_action', 'pfa_meta_box_inner');

		if ($this->options['list_roles']) {
			echo '<h4>'.__('Limit access by User Role:', 'pfa').'</h4>';
			foreach ($wp_roles->role_names as $name) {
				echo '<label><input type="checkbox" name="pfa_post_meta_roles[]" value="'.$name.'"';

				if (!empty($savedroles) && in_array($name, $savedroles)) {
					echo ' checked';
				}

				echo '> '.$name.'</label>';
			}
		}

		if ($this->options['list_users']) {
			echo '<h4>'.__('Limit access by User Name:', 'pfa').'</h4>';
			$user = get_current_user_id(); // http://codex.wordpress.org/Function_Reference/get_current_user_id
			$user_groupnames = wp_get_object_terms($user, 'user-group', array('fields' => 'names')); // http://codex.wordpress.org/Function_Reference/wp_get_object_terms

			foreach ($user_groupnames as $groupname) {
				$blogusers = get_users(array('blog_id' => $GLOBALS['blog_id'], 'orderby'=> 'ID')); // http://codex.wordpress.org/Function_Reference/get_users

				echo '<h5>'.esc_html($groupname).':</h5>';

				foreach ($blogusers as $user) {
					$groupsForUserCheck = false;
					$groupsForUser = wp_get_object_terms($user->ID, 'user-group', array('fields' => 'names'));
					foreach ($groupsForUser as $groupCheck) {
						if (esc_html($groupname) == esc_html($groupCheck)) {
							$groupsForUserCheck = true;
						}
					}

					if ($groupsForUserCheck) {
						echo '<label><input type="checkbox" name="pfa_post_meta_users[]" value="'.$user->ID.'"';
						if (!empty($savedusers) && in_array($user->ID, $savedusers)) {
							echo ' checked';
						}
						echo '> '.$user->display_name.'</label>';
					}
				}
			}
		}

		echo '<h4>'.__('Limit access by login status:', 'pfa').'</h4>';
		echo '<label><input type="checkbox" name="pfa_post_meta_options[logged]" value="1"';
		if (isset($savedoptions['logged']) && $savedoptions['logged'] == 1){
			echo ' checked';
		}
		echo '> '.__('Logged in users only', 'pfa').'</label>';

		echo '<label><input type="checkbox" name="pfa_post_meta_options[non_logged]" value="1"';
		if (isset($savedoptions['non_logged']) && $savedoptions['non_logged'] == 1){
			echo ' checked';
		}
		echo '> '.__('Non logged in users only', 'pfa').'</label>';

		echo '<h4>'.__('Content Blocked message:', 'pfa').'</h4>';
		echo '<textarea name="pfa_message" id="pfa_message">'.get_post_meta($post->ID, 'pfa_message', true).'</textarea><br/>';
		echo __('This message will be shown to anyone who is not on the list above.', 'pfa');

		echo '<h4>'.__('Shortcode:', 'pfa').'</h4>';
		echo '<p>'.__('You can use a shortcode', 'pfa').' <b>[PFA]</b> '.__('which accepts the following parameters:', 'pfa').'</p>';
		echo '<p>';
			echo '<b>user_id:</b> '.__('specific user ids form more then one separate by comma', 'pfa').'<br />';
			echo '<b>user_name:</b> '.__('specific user names form more then one separate by comma', 'pfa').'<br />';
			echo '<b>user_role:</b> '.__('specific user role form more then one separate by comma', 'pfa').'<br />';
			echo '<b>blocked_message:</b> '.__('specific Content Blocked message', 'pfa');
		echo '</p>';
		echo '<p><b>'.__('Examples:', 'pfa').'</b>';
			echo '<pre>[PFA user_name="Dimitri Waechter", "Armin Fuhrmann"]content goes here[/PFA]</pre>';
			echo '<pre>[PFA user_id=1,2,3]content goes here[/PFA]</pre>';
			echo '<pre>[PFA user_role=Editor,Author]content goes here[/PFA]</pre>';
			echo __('Or in any combination like ...', 'psa');
			echo '<pre>[PFA user_role="Administrator" blocked_message="admins only!"]'.__('admin content goes here', 'pfa').'[/PFA]</pre>';
		echo '</p>';
	}

	// Save content of the meta box if post is saved
	function pfa_meta_box_save($post_id) {
		// Verify if this came from our screen and with proper authorization, because save_post can be triggered at other times.
		if (isset($_POST['pfa_meta_box_inner'])){
			if (!wp_verify_nonce($_POST['pfa_meta_box_inner'], 'pfa_meta_box_inner_action')) {
				return;
			}
		} else {
			return;
		}

		// If this is an autosave, our form has not been submitted, so we don't want to do anything.
		if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
			return;
		}

		if (isset($_POST['pfa_post_meta_options']) && !empty($_POST['pfa_post_meta_options'])) {
			$new_savedoptions = array();

			foreach ($_POST['pfa_post_meta_options'] as $key => $value) {
				$new_savedoptions[$key] = $value;
			}

			update_post_meta($post_id, 'pfa_post_meta_options', $new_savedoptions);
		} else {
			delete_post_meta($post_id, 'pfa_post_meta_options');
		}

		if (isset($_POST['pfa_post_meta_roles']) && !empty($_POST['pfa_post_meta_roles'])) {
			$new_roles = array();

			foreach ($_POST['pfa_post_meta_roles'] as $role) {
				$new_roles[] = $role;
			}

			update_post_meta($post_id, 'pfa_post_meta_roles', $new_roles);
		} else {
			delete_post_meta($post_id, 'pfa_post_meta_roles');
		}

		if (isset($_POST['pfa_post_meta_users']) && !empty($_POST['pfa_post_meta_users'])) {
			$new_users = array();

			foreach ($_POST['pfa_post_meta_users'] as $user) {
				$new_users[] = $user;
			}

			update_post_meta($post_id, 'pfa_post_meta_users', $new_users);
		} else {
			delete_post_meta($post_id, 'pfa_post_meta_users');
		}

		if (isset($_POST['pfa_message'])) {
			update_post_meta($post_id, 'pfa_message', $_POST['pfa_message']);
		}
	}

	// Shortcode function
	public function pfa_shortcode($atts, $content = null) {
		global $current_user;

		$atts = shortcode_atts(array(
			'user_id' => '',
			'user_name' => '',
			'user_role' => '',
			'blocked_message' => ''
		), $atts);

		if (!empty($atts['user_id']) || !empty($atts['user_name']) || !empty($atts['user_role'])) {
			if (!is_user_logged_in()) {
				return $this->displayMessage($atts['blocked_message']);
			}

			if (!empty($atts['user_id'])) {
				$atts['user_id'] = explode(',', $atts['user_id']);

				if (!in_array($current_user->ID, $atts['user_id'])) {
					return $this->displayMessage($atts['blocked_message']);
				}
			} else if (!empty($atts['user_name'])) {
				$atts['user_name'] = explode(',', $atts['user_name']);

				if (!in_array($current_user->user_login, $atts['user_name'])) {
					return $this->displayMessage($atts['blocked_message']);
				}
			} else if (!empty($atts['user_role'])) {
				$atts['user_role'] = explode(',', $atts['user_role']);

				if (!in_array($this->pfa_get_current_user_role(), $atts['user_role'])) {
					return $this->displayMessage($atts['blocked_message']);
				}
			}
		}

		return do_shortcode($content);
	}

	public function displayMessage($message) {
		global $post;

		if (!empty($message)) {
			return __($message, 'pfa');
		} else {
			return __($this->options['blocked_message'], 'pfa');
		}
	}

	public function pfa_init() {
		if ($this->options['run_on_the_content']) {
			add_filter('the_content', array($this, 'pfa_filter'));
		}

		if ($this->options['run_on_the_excerpt']) {
			add_filter('the_excerpt', array($this, 'pfa_filter'));
		}
	}

	public function pfa_filter($content) {
		global $post, $current_user;
		$savedoptions = get_post_meta($post->ID, 'pfa_post_meta_options', true);
		$message = get_post_meta($post->ID, 'pfa_message', true);

		if (isset($savedoptions) && !empty($savedoptions)) {
			if (isset($savedoptions['non_logged']) && $savedoptions['non_logged'] == 1) {
				if (is_user_logged_in()) {
					return $this->displayMessage($message);
				}
			}

			if (isset($savedoptions['logged']) && $savedoptions['logged'] == 1) {
				if (!is_user_logged_in()) {
					return $this->displayMessage($message);
				}
			}
		}

		$savedroles = get_post_meta($post->ID, 'pfa_post_meta_roles', true);
		$savedusers = get_post_meta($post->ID, 'pfa_post_meta_users', true);
		$run_check = 0;

		if (!count($savedusers) > 0 && !count($savedroles) > 0 ) {
			return $content;
			exit;
		}

		if (!empty($savedroles)) {
			$cu_r = $this->pfa_get_current_user_role();

			if ($cu_r && in_array($cu_r, $savedroles)) {
				return $content;
				exit;
			}

			$run_check++;
		}

		if (!empty($savedusers)) {
			if (in_array($current_user->ID, $savedusers)) {
				return $content;
				exit;
			}

			$run_check++;
		}

		if ($run_check > 0) {
			return $this->displayMessage($message);
			exit;
		}

		return $content;
	}

	public function pfa_get_current_user_role() {
		global $wp_roles;
		$current_user = wp_get_current_user();
		$role = array_shift($current_user->roles);

		return isset($wp_roles->role_names[$role]) ? translate_user_role($wp_roles->role_names[$role]) : false;
	}

	function private_custom_upload_dir($path) {
		if (!isset($_REQUEST['action']) || $_REQUEST['action'] !== 'upload-attachment') {
        	return $path;
    	}

		if (!isset($_REQUEST['post_id'])) {
			return $path;
		}

		$type = get_post_type($_REQUEST['post_id']);

		if ($type == 'private_post') {
			$customdir = '/private';
		    $path['path'] = str_replace($path['subdir'], '', $path['path']);
		    $path['url'] = str_replace($path['subdir'], '', $path['url']);
		    $path['subdir'] = $customdir;
		    $path['path'] .= $customdir;
		    $path['url'] .= $customdir;
		}

		return $path;
	}
}

$privatefilearea = new private_file_area();

?>
