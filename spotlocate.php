<?php

/*

	Plugin Name:	Spot Locate
	Plugin URI:		http://marklindhout.com
	Description:	Easily display your GPS coordinates or location in your post using a Google Map.
	Author:			Mark P. Lindhout
	Version:		0.0.1
	Author URI:		http://marklindhout.com
	License:		GPLv3

*/


/*****************************************************************************
 * PLUGIN BASICS
 *****************************************************************************/

// Set the correct location for this plugin
define( 'SPOTLOCATE_PATH', plugin_dir_path(__FILE__) );

// Add localization
load_plugin_textdomain('spotlocate', false, plugin_dir_path(__FILE__) . '/languages' );

// Define Spotlocate's default options
$spotlocate_options = array(
	// More info on what values are valid here, check out: 
	'map_width'			=>	600,													// Width in pixels
	'map_height'		=>	320,													// Height in pixels
	'map_zoom'			=>	10,														// Zoom level: 1-24
	'map_img_type'		=>	'png8',													// Image format: png
	'map_type'			=>	'terrain',												// Map type: satellite, roadmap, terrain, hybrid
	'map_marker_size'	=>	'mid',
	'map_marker_color'	=>	'blue',
	'map_marker_label'	=>	'A',
);

// Add Spotlocate's array to the Wordpress options
add_option('spotlocate', $spotlocate_options);


/*****************************************************************************
 * PLUGIN POST LOGIC
 *****************************************************************************/

function spotlocate_add_map_to_post($content) {
	
	// Load the global post variable
	global $post;
	
	// And load the options into the $ops variable
	$ops = get_option('spotlocate');
	
	// If the map is set, add it to the past
	if ( get_post_meta($post->ID, 'spotlocation', true) ) {
		
		// build the Map image
		$mapimage =
		'<figure class="spotlocation">'
		. '<a target="_blank" href="https://maps.google.com/maps?q=' . urlencode(strip_tags(get_post_meta($post->ID, 'spotlocation', true))) . '" title="' . strip_tags(get_post_meta($post->ID, 'spotlocation', true)) . '">'
		. '<img src="http://maps.googleapis.com/maps/api/staticmap?'
		. 'center=' . urlencode(get_post_meta($post->ID, 'spotlocation', true))
		. '&size=' . urlencode($ops['map_width']) . 'x' . urlencode($ops['map_height'])
		. '&maptype=' . urlencode($ops['map_type'])
		. '&markers='
			. 'size:' . $ops['map_marker_size'] . urlencode('|')
			. 'color:' . $ops['map_marker_color'] . urlencode('|')
			. 'label:' . $ops['map_marker_label'] . urlencode('|')
			. urlencode(get_post_meta($post->ID, 'spotlocation', true))
		. '&format=' .  urlencode($ops['map_img_type'])
		. '&zoom=' .  urlencode($ops['map_zoom'])
		. '&sensor=false'
		. '" '
		. 'alt="' . __('Location:', 'spotlocate'). ' ' . strip_tags(get_post_meta($post->ID, 'spotlocation', true)) . '"'
		. 'title="' . __('Location:', 'spotlocate'). ' ' . strip_tags(get_post_meta($post->ID, 'spotlocation', true)) . '"' 
		. ' />'
		. '</a>'
		. '<figcaption>'
		. '<p>' . __('Current location:', 'spotlocate'). ' ' . '<a target="_blank" href="https://maps.google.com/maps?q=' . urlencode(strip_tags(get_post_meta($post->ID, 'spotlocation', true))) . '" title="' . strip_tags(get_post_meta($post->ID, 'spotlocation', true)) . '">' . strip_tags(get_post_meta($post->ID, 'spotlocation', true)) . '</a>' . '</p>'
		. '</figcaption>'
		. '</figure>'
		. " \n\n ";

		// Load original content into a variable
		$original_content = $content;
		
		// Concatenate it all
		$content = $mapimage . $original_content; 
	}

    // Returns the content.
    return $content;
}
add_filter( 'the_content', 'spotlocate_add_map_to_post' );


/*****************************************************************************
 * POST METABOX
 *****************************************************************************/
function spotlocate_create_metabox() {

	// Load the global post variable
	global $post;

	// Generate the nonce verification field
	wp_nonce_field('spotlocate_nonce_action', 'spotlocate_nonce_field');
?>
	<p>
		<label for="spotlocation">
			<img src="<?php echo plugins_url('spotlocate-icon-16px.png', __FILE__); ?>" alt="<?php _e('Enter a location name or coordinates', 'spotlocate'); ?>" />
			<input type="text" id="spotlocation" name="spotlocation" value="<?php echo get_post_meta( $post->ID, 'spotlocation', true ); ?>" class="medium-text" />
		</label>
	</p>

<?php
}

// Adds a box to the main column on the edit screen
function spotlocate_add_metabox() {

	// Add a meta box for each public post type, including sutom ones.
	foreach( get_post_types( array( 'public' => true ) ) as $post_type ) {
		add_meta_box( 'spotlocate_metabox', __( 'What is your location?', 'spotlocate' ), 'spotlocate_create_metabox', $post_type, 'side', 'high' );
	}

}
add_action('add_meta_boxes', 'spotlocate_add_metabox');

// When the post is saved, saves our custom data
function spotlocate_save_postdata( $post_id ) {

	global $spotlocate_terms;

	// verify this came from the our screen and with proper authorization, because save_post can be triggered at other times
	if ( !wp_verify_nonce( $_POST['spotlocate_nonce_field'], 'spotlocate_nonce_action' ) ) {
		return $post_id;
	}

	// verify if this is an auto save routine. If it is our form has not been submitted, so we dont want to do anything
	if ( defined('DOING_AUTOSAVE') && DOING_AUTOSAVE ) {
		return $post_id;
	}
	
	// Check permissions
	if ( !current_user_can( 'edit_post', $post_id ) ) {
		return $post_id;
	}

	update_post_meta( $post_id, 'spotlocation', $_POST['spotlocation'] );

}
add_action('save_post', 'spotlocate_save_postdata');


/*****************************************************************************
 * PLUGIN ADMINISTRATION
 *****************************************************************************/

 // First, define the options form
function spotlocate_options() {

	// CHeck if the user has enough permissions
	if ( !current_user_can( 'manage_options' ) )  {
		wp_die(	__('You do not have sufficient permissions to access this page.') );
	}

	// Set updated status to false.
	$updated = false;

	// Verify origin of these requests trhough the NONCE technique
	if( wp_verify_nonce($_REQUEST['spotlocate_nonce_field'], 'spotlocate_nonce_action') ) {
		
		// Load all posted vars into an array
		$updated_spotlocate_options = array(
			'map_width'			=>	$_REQUEST['map_width'],
			'map_height'		=>	$_REQUEST['map_height'],
			'map_zoom'			=>	$_REQUEST['map_zoom'],
			'map_img_type'		=>	$_REQUEST['map_img_type'],
			'map_type'			=>	$_REQUEST['map_type'],
			'map_marker_size'	=>	$_REQUEST['map_marker_size'],
			'map_marker_color'	=>	$_REQUEST['map_marker_color'],
			'map_marker_label'	=>	$_REQUEST['map_marker_label'],
		);
		
		// Update all options...
		update_option( 'spotlocate', $updated_spotlocate_options);

		// ... and set updated to true.
		$updated = true;
		
	}

	// And load the options into the $ops variable
	$ops = get_option('spotlocate');

	// Display message only if the post is updated
	if ( $updated ) { ?><div class="updated"><p><?php _e('The options were succesfully saved.', 'spotlocate'); ?></p></div><?php } ?>

	<div class="wrap">
		<h2><?php _e('Spotlocate Options', 'spotlocate'); ?></h2>
		<form method="post" action="<?php echo $_SERVER['REQUEST_URI']; ?>">
			<h3><?php _e('Map', 'spotlocate'); ?></h3>
			
			<p>
				<input name="map_width" id="map_width" type="text" value="<?php echo $ops['map_width']; ?>" class="small-text code" title="Map width" />
				x
				<input name="map_height" id="map_height" type="text" value="<?php echo $ops['map_height']; ?>" class="small-text code" title="Map height" />
				<span class="description"><?php _e('Size of the map, in pixels. (width x height)', 'spotlocate'); ?></span>
			</p>

			<p>
				<select name="map_type" id="map_type">
					<option value="roadmap"<?php echo ($ops['map_type'] == 'roadmap' ? ' selected="selected"': ''); ?>><?php _e('Road map', 'spotlocate'); ?></option>
					<option value="terrain"<?php echo ($ops['map_type'] == 'terrain' ? ' selected="selected"': ''); ?>><?php _e('Terrain', 'spotlocate'); ?></option>
					<option value="satellite"<?php echo ($ops['map_type'] == 'satellite' ? ' selected="selected"': ''); ?>><?php _e('Satellite', 'spotlocate'); ?></option>
					<option value="hybrid"<?php echo ($ops['map_type'] == 'hybrid' ? ' selected="selected"': ''); ?>><?php _e('Hybrid', 'spotlocate'); ?></option>
				</select>
				<span class="description"><?php _e('Map display type', 'spotlocate'); ?></span>
			</p>

			<p>
				<input name="map_zoom" id="map_zoom" type="text" value="<?php echo $ops['map_zoom']; ?>" class="middle-text code" title="Map zoom level" />
				<span class="description"><?php _e('Zoom level (1-24)', 'spotlocate'); ?></span>
			</p>
			
			<h3><?php _e('Marker', 'spotlocate'); ?></h3>
			
			<p>
				<span class="description"><?php _e('Marker size', 'spotlocate'); ?></span>

				<select name="map_marker_size" id="map_marker_size">
					<option value="tiny"<?php echo ($ops['map_marker_size'] == 'tiny' ? ' selected="selected"': ''); ?>><?php _e('Tiny', 'spotlocate'); ?></option>
					<option value="small"<?php echo ($ops['map_marker_size'] == 'small' ? ' selected="selected"': ''); ?>><?php _e('Small', 'spotlocate'); ?></option>
					<option value="mid"<?php echo ($ops['map_marker_size'] == 'mid' ? ' selected="selected"': ''); ?>><?php _e('Medium', 'spotlocate'); ?></option>
					<option value="normal"<?php echo ($ops['map_marker_size'] == 'normal' ? ' selected="selected"': ''); ?>><?php _e('Large', 'spotlocate'); ?></option>
				</select>
			</p>
			
			<p>
				<span class="description"><?php _e('Marker color', 'spotlocate'); ?></span>
				<select name="map_marker_color" id="map_marker_color">
				<?php 
						$marker_colors = array('black', 'brown', 'green', 'purple', 'yellow', 'blue', 'gray', 'orange', 'red', 'white');
						foreach ($marker_colors as $c) {
				?>

						<option value="<?php echo $c; ?>"<?php echo ($ops['map_marker_color'] == $c ? ' selected="selected"': ''); ?>><?php _e($c, 'spotlocate'); ?></option>

				<?php }	?>
				</select>
			</p>
			
			<p>
				<span class="description"><?php _e('Marker label', 'spotlocate'); ?></span>
				<input name="map_marker_label" id="map_marker_label" type="text" value="<?php echo $ops['map_marker_label']; ?>" class="small-text code" title="Marker label" />
			</p>
			
			<p>
				<span class="description"><?php _e('Map image type', 'spotlocate'); ?></span>
				<select name="map_img_type" id="map_img_type">
				<?php 
						$image_types = array('png8', 'png32', 'gif', 'jpg', 'jpg-baseline');
						foreach ($image_types as $t) {
				?>

						<option value="<?php echo $t; ?>"<?php echo ($ops['map_img_type'] == $t ? ' selected="selected"': ''); ?>><?php _e($t, 'spotlocate'); ?></option>

				<?php }	?>
				</select>
			</p>

			<p class="submit">
				<input type="submit" class="button-primary" value="<?php _e('Save Changes') ?>" />
			</p>

			<?php
				// Add a Nonce field to the form
				wp_nonce_field( 'spotlocate_nonce_action', 'spotlocate_nonce_field', true, true );
			?>	
		</form>
	</div>
<?php
}

// Then, define a menu page for Spotlocate's general options.
function spotlocate_settings_page() {
	add_menu_page( 'Spotlocate Options', 'Spotlocate', 'manage_options', 'spotlocate', 'spotlocate_options', plugins_url('spotlocate-icon-16px.png', __FILE__) );
}

// And finally, add it all to the admin menu
add_action( 'admin_menu', 'spotlocate_settings_page' );

