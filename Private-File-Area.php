<?php
/*
  Plugin Name: Private File Area
  Plugin URI: https://github.com/Asgaros/private-file-area
  Description: This plugin is to show content only for whom the content is intended.
  Version: 1.0.2
  Author: Han Ting, Xue Tianyu, Wang Yitong, Dimitri, Vitaly, Armin, Thomas Belser
  Author URI: https://chinger-coorp.fb2.frankfurt-university.de/

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

// TODO: When file is uploaded to private area directory, it will be shown in media library. But the file will not be linked correctly.

// THIS PLUGIN WORKS ONLY IN COMBINATION WITH PLUGIN "User Groups" by Katz Web Services, Inc.!

// Disallow direct access to the plugin file for security reasons.
if (basename($_SERVER['PHP_SELF']) == basename (__FILE__)) {
	die('Sorry, but you cannot access this page directly.');
}

// change upload directory for custom post type
// attachments will now be uploaded to an "uploads" directory within our plugin folder
// ../wp-content/plugins/private-file-area/priv_uploads
function custom_upload_directory($args) {
	/*$id = $_REQUEST['post_id'];
    $parent = get_post($id)->post_parent; // http://codex.wordpress.org/Function_Reference/get_post

	// Check the post-type of the current post and if it's type private then set path-variables
	if (get_post_type($id) == "private_post" || get_post_type($parent) == "private_post") {
        $args['path'] = plugin_dir_path(__FILE__)."priv_uploads"; // http://codex.wordpress.org/Function_Reference/plugin_dir_path
        $args['url'] = plugin_dir_url(__FILE__)."priv_uploads";
        $args['basedir'] = plugin_dir_path(__FILE__)."priv_uploads";
        $args['baseurl'] = plugin_dir_url(__FILE__)."priv_uploads";
    }

    return $args;*/
}

//add_filter('upload_dir', 'custom_upload_directory');





add_filter('wp_handle_upload_prefilter', 'private_pre_upload');
add_filter('wp_handle_upload', 'private_post_upload');

function private_pre_upload($file) {
    add_filter('upload_dir', 'private_custom_upload_dir');
    return $file;
}

function private_post_upload($fileinfo) {
    remove_filter('upload_dir', 'private_custom_upload_dir');
    return $fileinfo;
}

function private_custom_upload_dir($path) {
    $customdir = '/private';
    $path['path'] = str_replace($path['subdir'], '', $path['path']);
    $path['url'] = str_replace($path['subdir'], '', $path['url']);
    $path['subdir'] = $customdir;
    $path['path'] .= $customdir;
    $path['url'] .= $customdir;
    return $path;
}





// we need a class for our Private File Area

class protected_P_F_A {
	// used as localization domain name
	// we need this if we use Plugin codestyling-localization for translations
	// to translate a string within the Plugins-content use e.g. "echo __('Text to translate',pfa');"
	var $localization_domain = "pfa";

	// constructor for our class
	function __construct() {
		// define the custom box
		add_action('add_meta_boxes', array($this, 'Private_file_area_box'));
		// http://codex.wordpress.org/Function_Reference/add_action

		// save meta box
		add_action('save_post', array($this, 'Private_file_area_box_inner_save'));

		// add shortcodes
		add_shortcode('PFA', array($this, 'Private_file_area_shortcode')); // http://codex.wordpress.org/Function_Reference/add_shortcode

		// options page for admins only
		add_action('admin_menu', array($this, 'admin_menu'));
		//add_action('admin_menu', array($this, 'privatemenu'));
		add_action('admin_init', array($this, 'P_F_A_admin_init'));

		// add_filter hooks
		add_action('init', array($this, 'P_F_A_init'));
		add_action('init', array($this, 'create_post_type'));
		//add_filter('404_template', array($this, 'private_filter'));
		add_filter('parse_query', array($this, 'private_files_only'));
		// http://codex.wordpress.org/Function_Reference/add_filter

		// language setup
		$locale = get_locale();
		load_plugin_textdomain( $this->localization_domain, false, dirname( plugin_basename( __FILE__ ) ) . '/lang/' );
		// http://codex.wordpress.org/Function_Reference/load_plugin_textdomain
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

	// function to show only files from current owner in the media library (author-area)
	function private_files_only($wp_query) {
		if (strpos($_SERVER['REQUEST_URI'], '/wp-admin/upload.php') !== false) {
			if (!current_user_can('level_5')) {
				// http://codex.wordpress.org/Function_Reference/current_user_can
				global $current_user;
				$wp_query->set('author', $current_user->id);
			}
		}
	}

	// we need parse_query above at add_filter hooks section

	// init for P_F_A
	public function P_F_A_init() {
		$options = $this->P_F_A_get_option();

		if ($options['run_on_the_content']) {
			// hook the_content to filter users
			add_filter('the_content', array($this, 'Private_file_area_filter'));
			// http://codex.wordpress.org/Function_Reference/add_filter
		}

		if ($options['run_on_the_excerpt']) {
			// hook the_excerpt to filter users
			add_filter('the_excerpt', array($this, 'Private_file_area_filter'));
		}

		//allow other filters
		do_action('Private_file_area_filter_add', $this);
		// http://codex.wordpress.org/Function_Reference/do_action
	}

// Administration initialization
public function P_F_A_admin_init() {
	register_setting('P_F_A_Options', 'P_F_A', array($this, 'P_F_A_validate_options')); // http://codex.wordpress.org/Function_Reference/register_setting
	$this->P_F_A_get_option();
}

function P_F_A_validate_options($i) {
	return $i;
}

// Administration menu
public function admin_menu() {
	add_options_page('Private File Area', 'Private File Area', 'manage_options', 'ba_P_F_A', array($this, 'P_F_A_options')); // http://codex.wordpress.org/Function_Reference/add_options_page
}

// Options page
public function P_F_A_options() {
	if (!current_user_can('manage_options')) { // http://codex.wordpress.org/Function_Reference/current_user_can
		wp_die(__('You do not have sufficient permissions to access this page.')); // http://codex.wordpress.org/Function_Reference/wp_die
	}
	?>
	<div class="wrap">
		<h2><?php echo __('Private File Area', 'pfa'); ?></h2>
		<form method="post" action="options.php">
			<?php
			settings_fields('P_F_A_Options'); // http://codex.wordpress.org/Function_Reference/settings_fields
			$options = $this->P_F_A_get_option();
			?>
			<h3><?php echo __('General settings:', 'pfa'); ?></h3>
			<table class="form-table">
			<tr valign="top">
			<th scope="row"><?php echo __('Global Blocked message:', 'pfa'); ?></th>
			<td><textarea type="text" name="P_F_A[b_massage]" ><?php echo $options['b_massage']; ?></textarea><br />
			<?php _e('<small>If set in a metabox the it overwrites this message for that secific post/page.</small>', 'pfa'); ?></td></tr>
			<tr valign="top">
			<th scope="row"><?php echo __('Use with "the_content" hook?', 'pfa'); ?></th>
			<td><input type="checkbox" name="P_F_A[run_on_the_content]" value="true" <?php echo ($options['run_on_the_content']) ? 'checked="checked"': ''; ?> />
			<?php _e('<small>(default checked)</small>', 'pfa'); ?></td>
			</tr>
			<tr valign="top">
			<th scope="row"><?php echo __('Use with "the_excerpt" hook?', 'pfa'); ?></th>
			<td><input type="checkbox" name="P_F_A[run_on_the_excerpt]" value="true" <?php echo ($options['run_on_the_excerpt']) ? 'checked="checked"': ''; ?> />
			<?php _e('<small>Check to run on archive/tags/category pages. (default unchecked)</small>', 'pfa'); ?></td>
			</tr>
			</table>
			<h3><?php echo __('MetaBox settings:', 'pfa'); ?></h3>
			<table class="form-table">
			<tr valign="top">
			<th scope="row"><?php echo __('list user names?', 'pfa'); ?></th>
			<td><input type="checkbox" name="P_F_A[list_users]" value="true" <?php echo ($options['list_users']) ? 'checked="checked"': ''; ?> />
			<?php _e('<small>Sites with a large number of users should uncheck this option. (default checked)</small>', 'pfa'); ?></td>
			</tr>
			<tr valign="top">
			<th scope="row"><?php echo __('list user roles?', 'pfa'); ?></th>
			<td><input type="checkbox" name="P_F_A[list_roles]" value="true" <?php echo ($options['list_roles']) ? 'checked="checked"': ''; ?> />
			<?php _e('<small>Sites with a large number of roles should uncheck this option. (default checked)</small>', 'pfa'); ?></td>
			</tr>
			</table>
			<div>
			<?php echo '<h3>'.__('Shortcode:', 'pfa').'</h3><p>'.__('You can use a shortcode', 'pfa').' <pre>[PFA]</pre> '.__('which accepts the following parameters:', 'pfa').'</p><ul>';
				echo '<li>user_id: '.__('specific user ids form more then one separate by comma', 'pfa').'</li>';
				echo '<li>user_name: '.__('specific user names form more then one separate by comma', 'pfa').'</li>';
				echo '<li>user_role: '.__('specific user role form more then one separate by comma', 'pfa').'</li>';
				echo '<li>blocked_message: '.__('specific Content Blocked message', 'pfa').'</li></ul><p>'.__('eg:', 'pfa').'</p>
				<pre>[PFA user_id=1,2,3]content goes here[/PFA]</pre>
				<pre>[PFA user_name=Vitaly,Dimitri,Armin]content goes here[/PFA]</pre>
				<pre>[PFA user_role=Editor,Author]content goes here[/PFA]</pre>';
				echo __('Or in any combination like ...', 'psa');
				echo '<pre>[PFA user_role="Administrator" blocked_message="admins only!"]'.__('admin content goes here', 'pfa').'[/PFA]</pre>';
			?>
			</div>
			<p class="submit">
			<input type="submit" class="button-primary" value="<?php _e('Save Changes', 'pfa'); ?>" />
			</p>
		</form>
	</div>
	<?php
}

// options
public function P_F_A_get_option(){
		$temp = array('b_massage' => '',
		'list_users' => true,
		'list_roles' => true,
		'run_on_the_content' => true,
		'run_on_the_excerpt' => false
		); // end array $temp

		$i = get_option('P_F_A');
		// http://codex.wordpress.org/Function_Reference/get_option
		if (!empty($i)){
			if (isset($i['run_on_the_content']) && $i['run_on_the_content']){
				$temp['run_on_the_content'] = true;
			}else{
				$temp['run_on_the_content'] = false;
			}

			if (isset($i['run_on_the_excerpt']) && $i['run_on_the_excerpt']){
				$temp['run_on_the_excerpt'] = true;
			}else{
				$temp['run_on_the_excerpt'] = false;
			}

			if (isset($i['list_users']) && $i['list_users']){
				$temp['list_users'] = true;
			}else{
				$temp['list_users'] = false;
			}

			if (isset($i['list_roles']) && $i['list_roles']){
				$temp['list_roles'] = true;
			}else{
				$temp['list_roles'] = false;
			}

			if (isset($i['b_massage'])){
				$temp['b_massage'] = $i['b_massage'];
			}
		} // end if !empty($i)

		update_option('P_F_A', $temp);
		// http://codex.wordpress.org/Function_Reference/update_option
		// or delete_option('P_F_A');

		return $temp;
	} // end P_F_A_get_option()

// adding a box to the main column on the custom post type edit screens
public function Private_file_area_box() {
	// http://codex.wordpress.org/Function_Reference/add_meta_box
	add_meta_box('Private_file_area', __('Private file area box'), array($this, 'Private_file_area_box_inner'), 'private_post');
	add_meta_box('Private_file_area', __('Private file area box'), array($this, 'Private_file_area_box_inner'), 'page');

	// add metabox to custom post types
	$args = array(
		'public' => true,
		'_builtin' => false
	);

	// add metabox to custom post types edit screen
	$output = 'names'; // names or objects (names is default)
	$operator = 'and'; // 'and' or 'or'
	$post_types = get_post_types($args, $output, $operator);
	// http://codex.wordpress.org/Function_Reference/get_post_types

	foreach ($post_types as $post_type) {
		add_meta_box('Private_file_area', __('Private file area box', 'pfa'), array($this, 'Private_file_area_box_inner'), $post_type);
	}
}

// printing the box content
public function Private_file_area_box_inner() {
	// NOTE: maby it would nice if subproject "Gruppe Theme" could use css instead we have to &nbsp;'s in this function
	global $post, $wp_roles;

	// get our options
	$options = $this->P_F_A_get_option('P_F_A');
	$savedroles = get_post_meta($post->ID, 'P_F_A_roles', true);
	$savedusers = get_post_meta($post->ID, 'P_F_A_users', true);
	$savedoptions = get_post_meta($post->ID, 'P_F_A_options', true);

	// use nonce for verification
	wp_nonce_field(plugin_basename(__FILE__), 'Private_file_area_box_inner');
	// http://codex.wordpress.org/Function_Reference/wp_nonce_field

	echo __('Select users to show this content to.', 'pfa');

	//////// by role /////
	if ($options['list_roles']) {
		echo '<h4>'.__('By User Role:', 'pfa').'</h4>';
		if (!isset($wp_roles)) {
			$wp_roles = new WP_Roles();
		}

		foreach ($wp_roles->role_names as $role => $name) {
			echo '<input type="checkbox" name="P_F_A_roles[]" value="'.$name.'"';

			if (!empty($savedroles) && in_array($name, $savedroles)) {
				echo ' checked';
			}

			echo '> '.$name.'&nbsp;&nbsp;&nbsp;';
		}
	}

	///// by user /////
	if ($options['list_users']) {
		echo '<h4>'.__('By User Name:', 'pfa').'</h4>';
		echo '<p style="white-space: nowrap;">';
		$user = get_current_user_id(); // http://codex.wordpress.org/Function_Reference/get_current_user_id
		$user_groups = wp_get_object_terms($user, 'user-group', array('fields' => 'all')); // http://codex.wordpress.org/Function_Reference/wp_get_object_terms

		foreach ($user_groups as $user_group) {
			$blogusers = get_users(array('blog_id' => $GLOBALS['blog_id'], 'orderby'=> 'ID')); // http://codex.wordpress.org/Function_Reference/get_users
			$usercount = 0;

			echo '<h5>'.esc_html($user_group->name).':</h5>';
			echo '<ul style="float: left; padding-right: 20px;">';

			foreach ($blogusers as $user) {
				$groupsForUserCheck = false;
				$groupsForUser = wp_get_object_terms($user->ID, 'user-group', array('fields' => 'all'));
				foreach ($groupsForUser as $groupCheck) {
					if (esc_html($user_group->name) == esc_html($groupCheck->name)) {
						$groupsForUserCheck = true;
					}
				}

				if ($groupsForUserCheck) {
					echo '<li><input type="checkbox" name="P_F_A_users[]" value="'.$user->ID.'"';
					if (!empty($savedusers) && in_array($user->ID, $savedusers)) {
						echo ' checked';
					}
					echo '> '.$user->display_name.'&nbsp;';

					// get group by user id
					$user_groups_list = wp_get_object_terms($user->ID, 'user-group', array('fields' => 'all'));
					foreach ($user_groups_list as $group) {
						echo '('.esc_html($group->name).')&nbsp;';
					}
					echo '</li>';
					$usercount++; // counter for nicer display
					if ($usercount > 5) {
						echo '</ul><ul style="float: left; padding-right: 20px;">';
						$usercount = 0;
					}
				}
			}

			echo '</ul><div style="clear:both;"></div>';
		}
	}

		/////// other_options ///////

		// logged-in only
		echo '<h4>'.__('logged in users only:','pfa').'</h4>';
		echo '<input type="checkbox" name="P_F_A_options[logged]" value="1"';
		if (isset($savedoptions['logged']) && $savedoptions['logged'] == 1){
			echo ' checked';
		}
		echo '> '.__('If this box is checked then content will show only to logged-in users and everyone else will get the blocked message','pfa');

		// none logged-in
		echo '<h4>'.__('None logged in users only:','pfa').'</h4>';
		echo '<input type="checkbox" name="P_F_A_options[non_logged]" value="1"';
		if (isset($savedoptions['non_logged']) && $savedoptions['non_logged'] == 1){
			echo ' checked';
		}
		echo '> '.__('If this box is checked then content will show only to non-logged in visitors and everyone else will get the blocked message','pfa');
		echo '<h4>'.__('Content Blocked message:','pfa').'</h4>';
		echo '<textarea rows="3" cols="70" name="P_F_A_message" id="P_F_A_message">'.get_post_meta($post->ID, 'P_F_A_message',true).'</textarea><br/>'.__('This message will be shown to anyone who is not on the list above.<br/>','pfa');
		// shortcodes explaination
		echo '<h4>'.__('Shortcode:','pfa').'</h4><p>'.__('You can use a shortcode','pfa').' <pre>[PFA]</pre> '.__('which accepts the following parameters:','pfa').'</p><ul>';
		echo '<li>user_id - '.__('specific user ids form more then one separate by comma','pfa').'</li>';
		echo '<li>user_name - '.__('specific user names form more then one separate by comma','pfa').'</li>';
		echo '<li>user_role - '.__('specific user role form more then one separate by comma','pfa').'</li>';
		echo '<li>blocked_message - '.__('specific Content Blocked message','pfa').'</li></ul><p>'.__('eg:','pfa').'</p>
		<pre>[PFA user_name="Dimitri Waechter", "Armin Fuhrmann"]content goes here[/PFA]</pre><br/>
		<pre>[PFA user_id=1,2,3]content goes here[/PFA]</pre><br/>
		<pre>[PFA user_role=Editor,Author]content goes here[/PFA]</pre><br/>';
		echo __('Or in any combination like...','psa');
		echo '<pre>[PFA user_role="Administrator" blocked_message="admins only!"]'.__('admin content goes here','pfa').'[/PFA]</pre>';
	} 	// end Private_file_area_box_inner

// save custom data if post is saved
function Private_file_area_box_inner_save( $post_id ) {
		global $post;
		  // verify if this came from our screen and with proper authorization,
		  // because save_post can be triggered at other times
		if (isset($_POST['Private_file_area_box_inner'])){
			if ( !wp_verify_nonce( $_POST['Private_file_area_box_inner'], plugin_basename(__FILE__) ) )
				return $post_id;
		}else{
			return $post_id;
		} // http://codex.wordpress.org/Function_Reference/wp_verify_nonce
		  // verify if this is an auto save routine
		  // if it is auto sved our form has not been submitted, so we dont want to do pass anything
		  if ( defined('DOING_AUTOSAVE') && DOING_AUTOSAVE )
			  return $post_id;
		  // OK, we're authenticated: we need to find and save the data
		$savedroles = get_post_meta($post_id, 'P_F_A_roles',true);
		$savedusers = get_post_meta($post_id, 'P_F_A_users',true);
		$savedoptions = get_post_meta($post->ID, 'P_F_A_options',true);
		// http://codex.wordpress.org/Function_Reference/get_post_meta

		if (isset($_POST['P_F_A_options']) && !empty($_POST['P_F_A_options'] )){
			foreach ($_POST['P_F_A_options'] as $key => $value ){
				$new_savedoptions[$key] = $value;
			}
			update_post_meta($post_id, 'P_F_A_options', $new_savedoptions);
		// http://codex.wordpress.org/Function_Reference/update_post_meta
		}else{
			 delete_post_meta($post_id, 'P_F_A_options');
		// http://codex.wordpress.org/Function_Reference/delete_post_meta
		}
		if (isset($_POST['P_F_A_roles']) && !empty($_POST['P_F_A_roles'] )){
			foreach ($_POST['P_F_A_roles'] as $role){
				$new_roles[] = $role;
			}
			update_post_meta($post_id, 'P_F_A_roles', $new_roles);
		}else{
			if (count($savedroles) > 0){
				delete_post_meta($post_id, 'P_F_A_roles');
			}
		}
		if (isset($_POST['P_F_A_users']) && !empty($_POST['P_F_A_users'])){
			foreach ($_POST['P_F_A_users'] as $u){
				$new_users[] = $u;
			}
			update_post_meta($post_id, 'P_F_A_users', $new_users);
		}else{
			if (count($savedusers) > 0){
				 delete_post_meta($post_id, 'P_F_A_users');
			}
		}
		if (isset($_POST['P_F_A_message'])){
			update_post_meta($post_id,'P_F_A_message', $_POST['P_F_A_message']);
		}
	} // end Private_file_area_box_inner_save()


public function Private_file_area_filter($content) {
	global $post, $current_user;
	$savedoptions = get_post_meta($post->ID, 'P_F_A_options', true);
	$m = get_post_meta($post->ID, 'P_F_A_message', true);
	if (isset($savedoptions) && !empty($savedoptions)){
			// none logged-in only
			if (isset($savedoptions['non_logged']) && $savedoptions['non_logged'] == 1){
				if (is_user_logged_in()){
					// http://codex.wordpress.org/Function_Reference/is_user_logged_in
					return $this->displayMessage($m);
				}
			}
			// logged-in users only
			if (isset($savedoptions['logged']) && $savedoptions['logged'] == 1){
				if (!is_user_logged_in()){
					return $this->displayMessage($m);
				}
			}
		}
		$savedroles = get_post_meta($post->ID, 'P_F_A_roles',true);
		$run_check = 0;
		$savedusers = get_post_meta($post->ID, 'P_F_A_users',true);
		if (!count($savedusers) > 0 && !count($savedroles) > 0 ){
			return $content;
			exit;
		}
		// ...by role
		if (isset($savedroles) && !empty($savedroles)){
			get_currentuserinfo();
			$cu_r = $this->pfa_get_current_user_role();
			if ($cu_r){
				if (in_array($cu_r,$savedroles)){
					return $content;
					exit;
				}else{
					$run_check = 1;
				}
			}else{
			// failed role check
				$run_check = 1;
			}
		}
		// ...by user
		if (isset($savedusers) && !empty($savedusers)){
			get_currentuserinfo();
			if (in_array($current_user->ID,$savedusers)){
				return $content;
			}else{
				$run_check = $run_check + 1;
			}
			// failed both checks
			return $this->displayMessage($m);
		}
		if ($run_check > 0){
			return $this->displayMessage($m);
		}
		return $content;
	} // end Private_file_area_filter()

// helper functions
public function pfa_get_current_user_role() {
		global $wp_roles;
		$current_user = wp_get_current_user();
		$roles = $current_user->roles;
		$role = array_shift($roles);
		return isset($wp_roles->role_names[$role]) ? translate_user_role($wp_roles->role_names[$role] ) : false;
	} // end pfa_get_current_user_role()

// shortcodes functions
public function Private_file_area_shortcode($atts, $content = null){
		// http://codex.wordpress.org/Function_Reference/shortcode_atts
		extract(shortcode_atts(array(
	        "user_id" => '',
			"user_name" => '',
			"user_role" => '',
			"blocked_message" => '',
			"blocked_meassage" => null
	    ), $atts));


		global $post;
		if ($blocked_meassage !== null){
			$blocked_message = $blocked_meassage;
		}

		$options = $this->P_F_A_get_option('P_F_A');
		global $current_user;
        get_currentuserinfo();
		// http://codex.wordpress.org/Function_Reference/get_currentuserinfo
		if ($user_id != '' || $user_name != '' || $user_role != ''){

			// check logged in
			if (!is_user_logged_in()){
				return $this->displayMessage($blocked_message);
			}
			// check user id
			if (isset($user_id) && $user_id != '' ){
				$user_id = explode(",", $user_id);
				if (!in_array($current_user->ID,$user_id)){
					return $this->displayMessage($blocked_message);
				}
			}
			// check user name
			if (isset($user_name) && $user_name != '' ){
				$user_name = explode(",", $user_name);
				if (!in_array($current_user->user_login,$user_name)){
					return $this->displayMessage($blocked_message);
				}
			}
			// check user role
			if (isset($user_role) && $user_role != '' ){
				$user_role = explode(",", $user_role);
				if (!in_array($this->pfa_get_current_user_role(),$user_role)){
					return $this->displayMessage($blocked_message);
				}
			}
		}
		return apply_filters('private_file_area_shortcode_filter',do_shortcode($content));
	}

public function displayMessage($m){
		global $post;
		if (isset($m) && $m != ''){
			return apply_filters('private_file_area_blocked',$m,$post);
		}else{
			$options = $this->P_F_A_get_option('P_F_A');
			return apply_filters('private_file_area_blocked',$options['b_massage'],$post);
		}
	}
}//end class protected_P_F_A
$P_F_A_i = new protected_P_F_A();

// adduserid
?>
