<?php
/*
Plugin Name:  Mapinco Easy Maps
Plugin URI:
Description:	Create simple and personalized maps based on Leafletjs.
Version:      0.1.3
Author:       Mappa Incognita
Author URI:   https://mapinco.fr
License:      GPL2
License URI:  https://www.gnu.org/licenses/gpl-2.0.html
Text Domain:  mapinco_easymaps
Domain Path:  /languages
*/
defined( 'ABSPATH' ) or die( 'No script kiddies please!' );

add_action('admin_menu', 'mapinco_easymaps_menu');
add_action('init', 'mapinco_easymaps_init');
add_action( 'admin_enqueue_scripts', 'mapinco_easymaps_admin_scripts' );
add_action( 'wp_enqueue_scripts', 'mapinco_easymaps_scripts' );
add_shortcode('mapinco_easymaps', 'mapinco_easymaps_shortcode');
add_action( 'admin_notices', 'mapinco_easymaps_admin_notices' );
add_action( 'plugins_loaded', 'mapinco_easymaps_load_plugin_textdomain' );


function mapinco_easymaps_load_plugin_textdomain() {
    load_plugin_textdomain( 'mapinco_easymaps', FALSE, basename( dirname( __FILE__ ) ) . '/languages/' );
}

function mapinco_easymaps_admin_notices() {
	if(isset($_GET['page'])){

		if($_GET['page'] == 'mapinco_easymaps' && isset($_GET['msg'])){
	
			switch($_GET['msg']){
				case 'delete':
					$class = 'notice notice-success';
					$message = __( 'Map deleted.', 'mapinco_easymaps' );			
				break;
				case 'new':
					$class = 'notice notice-success';
					$message = __( 'Map added.', 'mapinco_easymaps' );				
				break;
				case 'edit':
					$class = 'notice notice-success';
					$message = __( 'Map edited.', 'mapinco_easymaps' );				
				break;
			}
	
			printf( '<div class="%1$s"><p>%2$s</p></div>', esc_attr( $class ), esc_html( $message ) ); 
		} 
	
	}   
}



function mapinco_easymaps_getBasemaps(){
	$basemaps = array(
		'Google'	=>	array(
			'google_hybrid'	=>	'Hybrid',
			'google_satellite'	=>	'Satellite',
			'google_streets'	=>	'Streets',
			'google_terrain'	=>	'Terrain'
		),
		'OpenStreetMap'	=>	array(
			'osm_mapnik'	=>	'Mapnik',
			'osm_fr'	=>	'France',
			'osm_gray'	=>	'Grayscale',
			'osm_bzh'	=>	'Breizh',
			'osm_topo'	=>	'Topo Map'
		),
		'Stamen'	=>	array(
			'stamen_toner'	=>	'Toner',
			'stamen_terrain'	=>	'Terrain',
			'stamen_watercolor'	=>	'Watercolor'
		),
		'ESRI'	=>	array(
			'esri_streetmap'	=>	'Street Map',
			'esri_topomap'	=>	'Topo Map',
			'esri_delorme'	=>	'De Lorme',
			'esri_imagery'	=>	'Imagery',
			'esri_terrain'	=>	'Terrain',
			'esri_physical'	=>	'Physical',
			'esri_ocean'	=>	'Ocean',
			'esri_natgeo'	=>	'Natgeo',
			'esri_gray'	=>	'Grayscale'
		)
	);
	return apply_filters('mapinco_easymaps_basemaps', $basemaps);
}

function mapinco_easymaps_init(){
  register_post_type('mapinco_easymaps', array( 'public'      => false  ));

	
	if (isset($_GET['action']) && isset($_GET['nonce']) &&
			$_GET['action'] === 'mapinco_easymaps_delete' &&
      wp_verify_nonce($_GET['nonce'], 'mapinco_easymaps_admin_delete')
    ){
    
        $post_id = (isset($_GET['map_id'])) ? ($_GET['map_id']) : (null);
 
        $post = get_post((int)$post_id);
        if (empty($post)) {
            return;
        }
 
        // delete the post
        wp_delete_post($post_id, true);
 
        // redirect to admin page
        $redirect = admin_url('admin.php?page=mapinco_easymaps&msg=delete');
        wp_safe_redirect($redirect);
        die;    
    
    }	
	

  if(isset($_POST['mapinco_easymaps_submit'])){
    $map_id = $_POST['map_id'];
    check_admin_referer( 'mapinco_easymaps_' . $map_id, 'mapinco_easymaps_nonce' );

    $error = false;
    // Titre de la carte
    $title = sanitize_text_field($_POST['map_title']);
    if(empty($title)){
      $error = true;
    }
    // Hauteur de la carte
    $height = sanitize_text_field($_POST['map_height']);
    if(empty($height)){
      $error = true;
    }
    $zoom = sanitize_text_field($_POST['map_zoom']);
    
    $zoom_control = $_POST['map_zoom_control'];    
    if($zoom_control == 'on') $zoom_control = 1;
    else $zoom_control = 0;
    
    $routing = $_POST['map_routing'];
    $fullscreen = $_POST['map_fullscreen'];
    $basemap = $_POST['map_basemap'];
    
    if(false == $error){
      $args = array(
        'post_type' =>  'mapinco_easymaps',
        'post_title'  =>  $title,
        'post_status' =>  'publish'
      );
      if($map_id == 'new'){
        $map_id = wp_insert_post($args);
        $message = 'new';
      }
      else{
        $args['ID'] = $map_id;
        $map_id = wp_update_post($args);
        $message = 'edit';
      }
      update_post_meta($map_id, 'height', $height);
      update_post_meta($map_id, 'zoom', $zoom);
      update_post_meta($map_id, 'zoom_control', $zoom_control);
      update_post_meta($map_id, 'routing', $routing);
      update_post_meta($map_id, 'fullscreen', $fullscreen);
      update_post_meta($map_id, 'basemap', $basemap);
      
      $markers = array();
      if(isset($_POST['marker_name'])){
      	foreach($_POST['marker_name'] as $key => $name){
      		$markers[] = array(
      			'name'	=>	$name,
      			'address'	=>	$_POST['marker_address'][$key],
      			'coords'	=>	$_POST['marker_coords'][$key],
      		);
      	}
      }
      update_post_meta($map_id, 'markers', $markers);
      
      wp_redirect(admin_url('admin.php?page=mapinco_easymaps&map_id=' . $map_id . '&msg=' . $message)); exit;

    }
    else{
      add_action( 'admin_notices', 'mapinco_easymaps_error' );
      function mapinco_easymaps_error() {
      	$class = 'notice notice-error';
      	$message = __( 'Error!', 'mapinco_easymaps' );
      	printf( '<div class="%1$s"><p>%2$s</p></div>', esc_attr( $class ), esc_html( $message ) );
      }
    }

  }
}

function mapinco_easymaps_admin_scripts($hook){
	if($hook == 'toplevel_page_mapinco_easymaps'){
		wp_enqueue_style('leaflet',plugins_url( 'inc/leaflet/leaflet.css', __FILE__));
		wp_enqueue_style('mapinco_easymaps_admin', plugins_url( 'mapinco_easymaps_admin.css', __FILE__ ));
		wp_enqueue_script('leaflet',plugins_url( 'inc/leaflet/leaflet.js', __FILE__));		
		wp_enqueue_script('gmap_api','https://maps.googleapis.com/maps/api/js?key=AIzaSyCsbzgJwljiQwFpnmun7to_9GAdk8WNgQ8&libraries=places');
		wp_enqueue_script('mustache', plugins_url( 'inc/mustache.min.js', __FILE__ ));

		// leaflet fullscreen
		wp_enqueue_style('mapinco_easymaps_fullscreen', plugins_url( 'plugins/leaflet-fullscreen/Control.FullScreen.css', __FILE__ ));
		wp_enqueue_script('mapinco_easymaps_fullscreen', plugins_url( 'plugins/leaflet-fullscreen/Control.FullScreen.js', __FILE__ ), array('jquery', 'leaflet'));
		
		// esri leaflet geocoder
		//wp_enqueue_style('esri_leaflet_geocoder','https://unpkg.com/esri-leaflet-geocoder@2.2.6/dist/esri-leaflet-geocoder.css');
    //wp_enqueue_script('esri_leaflet_geocoder', 'https://unpkg.com/esri-leaflet-geocoder@2.2.6', array('jquery', 'leaflet', 'esri_leaflet'));
		
		//select 2
		wp_enqueue_script('select2', plugins_url( 'inc/select2/select2.full.min.js', __FILE__ ));
		wp_enqueue_style('select2', plugins_url( 'inc/select2/select2.min.css', __FILE__ ));
		
		wp_enqueue_script('mapinco_easymaps_admin', plugins_url( 'mapinco_easymaps_admin.js', __FILE__ ), array(
			'jquery', 
			'select2',
			'leaflet',
			/*'esri_leaflet', 
			'esri_leaflet_geocoder',*/
			'gmap_api','mustache', 
			'mapinco_easymaps_fullscreen'
		));
		
	}
}

function mapinco_easymaps_scripts(){
	wp_enqueue_style('leaflet',plugins_url( 'inc/leaflet/leaflet.css', __FILE__));
	wp_enqueue_script('leaflet',plugins_url( 'inc/leaflet/leaflet.js', __FILE__));		
	
	// leaflet fullscreen
	wp_enqueue_style('mapinco_easymaps_fullscreen', plugins_url( 'plugins/leaflet-fullscreen/Control.FullScreen.css', __FILE__ ));
	wp_enqueue_script('mapinco_easymaps_fullscreen', plugins_url( 'plugins/leaflet-fullscreen/Control.FullScreen.js', __FILE__ ), array('jquery', 'leaflet'));	
	
	wp_enqueue_script('mapinco_easymaps', plugins_url( 'mapinco_easymaps_front.js', __FILE__ ), array('jquery', 'leaflet','mapinco_easymaps_fullscreen'));
}

function mapinco_easymaps_menu(){
    add_menu_page(
        __('Easy Maps', 'mapinco_easymaps'),
        __('Easy Maps', 'mapinco_easymaps'),
        'manage_options',
        'mapinco_easymaps',
        'mapinco_easymaps_page',
        'dashicons-admin-site',
        20
    );
}

function mapinco_easymaps_page(){
  if (!current_user_can('manage_options')) {
      return;
  }
  if(isset($_GET['map_id'])){
    mapinco_easymaps_newmap($_GET['map_id']);
  }
  else{
    mapinco_easymaps_maps();
  }


}

function mapinco_easymaps_maps(){
  $mapquery = new WP_Query(array(
    'post_type' =>  'mapinco_easymaps'
  ));
  ?>
  <div class="wrap">
      <h1 class="wp-heading-inline"><?= esc_html(get_admin_page_title()); ?></h1>
      <a href="<?php echo admin_url('admin.php?page=mapinco_easymaps&map_id=new'); ?>" class="page-title-action">Ajouter</a>
      <hr class="wp-header-end">
      <?php
      if($mapquery->have_posts()){ ?>
      <table class="widefat striped" id="markers_table">
      	<thead>
      		<tr>
      			<td class="col-name"><?php _e('Name','mapinco_easymaps'); ?></td>
      			<td class="col-markers"><?php _e('Markers','mapinco_easymaps'); ?></td>
      			<td class="col-shortcodes"><?php _e('Shortcodes', 'mapinco_easymaps'); ?></td>
      			<td class="col-actions"></td>
      		</tr>
      	</thead>
      	<tbody>      
      <?php
        while ( $mapquery->have_posts() ){ $mapquery->the_post(); $post = $GLOBALS['post']; 
        	$markers = get_post_meta($post->ID, 'markers', true);
        	?>
        	<tr>
        		<td><a href="<?php echo admin_url('admin.php?page=mapinco_easymaps&map_id='.$post->ID); ?>"><?php echo $post->post_title; ?></a></td>
        		<td><?php echo count($markers); ?></td>
        		<td>[mapinco_easymaps id="<?php echo $post->ID; ?>"]</td>
        		<td>
        			<a class="button" href="<?php echo admin_url('admin.php?page=mapinco_easymaps&map_id='.$post->ID); ?>"><?php _e('Edit','mapinco_easymaps'); ?></a>
        			<a class="button mapinco_easymaps_delete" href="<?php echo add_query_arg(
            [
                'action' => 'mapinco_easymaps_delete',
                'map_id'   => $post->ID,
                'nonce'  => wp_create_nonce('mapinco_easymaps_admin_delete'),
            ], admin_url('admin.php')); ?>"><?php _e('Delete','mapinco_easymaps'); ?></a>        		
        		</td>
        	</tr>
       <?php }  ?>
        </tbody>
       </table>
        <?php
      }
      else{ ?>
				<p><?php printf(__('No map yet ? <a href="%s">Add your first one</a>','mapinco_easymaps'), admin_url('admin.php?page=mapinco_easymaps&map_id=new')); ?></p>
      <?php }
      wp_reset_postdata();
      ?>
  </div>
  <?php
}

function mapinco_easymaps_newmap($map_id){
  if($map_id == 'new'){
    $page_title = 'Add new map';
    $map_title = '';
    $map_height = 400;
    $map_zoom = 8;
    $map_zoom_control = 1;
    $map_fullscreen = 1;
    $map_basemap = array('osm_mapnik');
    $markers = array();
  }
  else{
    $page_title = 'Edit map';
    $map = get_post($map_id);
    $map_title = $map->post_title;
    $map_height = get_post_meta($map_id, 'height', true);
    $map_zoom = get_post_meta($map_id, 'zoom', true);
    $map_zoom_control = get_post_meta($map_id, 'zoom_control', true);
    $map_routing = get_post_meta($map_id, 'routing', true);
    $map_fullscreen = get_post_meta($map_id, 'fullscreen', true);
    $map_basemap = get_post_meta($map_id, 'basemap', true);
    $markers = get_post_meta($map_id, 'markers', true);
  }
  ?>
  <div class="wrap">
      <h1><?php _e($page_title, 'mapinco_easymaps'); ?></h1>
      
      <?php if($map_id != 'new'){ ?>
      	<p><?php printf(__('To show the map, use the following shortcode: %s', 'mapinco_easymaps'), '[mapinco_easymaps id="'.$map_id.'"]'); ?></p>
      <?php } ?>

      <form id="mapinco_easymaps_form" action="<?php echo admin_url('admin.php?page=mapinco_easymaps&map_id=' . $map_id); ?>" method="post">
      <?php wp_nonce_field( 'mapinco_easymaps_'.$map_id, 'mapinco_easymaps_nonce' ); ?>
      <input type="hidden" name="map_id" value="<?php echo $map_id; ?>">
        <div class="mapinco_easymaps_container">
          <div class="mapinco_easymaps_content">
            <p>
              <label for="map_title"><?php _e('Map title', 'mapinco_easymaps'); ?></label><br>
              <input type="text" name="map_title" id="map_title" class="regular-text" value="<?php echo $map_title; ?>">
            </p>
            <p>
              <label for="map_height"><?php _e('Height', 'mapinco_easymaps'); ?></label><br>
              <input type="text" name="map_height" id="map_height" class="regular-text" value="<?php echo $map_height; ?>">
            </p>
            <p>
              <label for="map_basemap"><?php _e('Basemap', 'mapinco_easymaps'); ?></label><br>
              	<select name="map_basemap[]" id="map_basemap" class="regular-text" multiple="multiple">
              		<?php 
              		$providers = mapinco_easymaps_getBasemaps(); 
              		foreach($providers as $provider=>$basemaps){ ?>
              		<optgroup label="<?php echo $provider; ?>">
              		<?php foreach($basemaps as $value=>$name){ ?>
              			<option value="<?php echo $value; ?>" <?php if(in_array($value,$map_basemap)) echo 'selected="selected"'; ?>><?php echo $name; ?></option>
              		<?php } ?>
              		</optgroup>
              		<?php }	?>
              	</select>             	 
            </p>             
            <p>
              <label for="map_zoom"><?php _e('Zoom level', 'mapinco_easymaps'); ?></label><br>
              <input type="number" name="map_zoom" id="map_zoom" class="regular-text" value="<?php echo $map_zoom; ?>">
            </p>
            <p>
              <label for="map_zoom_control">
              	<input type="checkbox" name="map_zoom_control" id="map_zoom_control" class="regular-text" <?php if($map_zoom_control) echo 'checked="checked"'; ?>">
             	 <?php _e('Zoom control', 'mapinco_easymaps'); ?>
              </label>
            </p> 
            <?php /*<p>
              <label for="map_routing">
              	<input type="checkbox" name="map_routing" id="map_routing" class="regular-text" <?php if($map_routing) echo 'checked="checked"'; ?>">
             	 <?php _e('Routing', 'mapinco_easymaps'); ?>
              </label>
            </p> */ ?> 
            <p>
              <label for="map_fullscreen">
              	<input type="checkbox" name="map_fullscreen" id="map_fullscreen" class="regular-text" <?php if($map_fullscreen) echo 'checked="checked"'; ?>">
             	 <?php _e('Fullscreen', 'mapinco_easymaps'); ?>
              </label>
            </p>                                                            
          </div>
          <div class="mapinco_easymaps_view">
            <div id="mapinco_easymaps_map" style="height:<?php echo $map_height; ?>px;" 
            	attr-zoom="<?php echo $map_zoom; ?>"
            	attr-zoom-control="<?php echo (bool)$map_zoom_control; ?>"
            	attr-routing="<?php echo (bool)$map_routing; ?>"
            	attr-fullscreen="<?php echo (bool)$map_fullscreen; ?>"
            	attr-basemap="<?php echo implode(',',$map_basemap); ?>">
            	<?php if(count($markers)>0){ foreach($markers as $key=>$marker){ ?>
            		<div class="marker" 
            			attr-id="marker-<?php echo $key; ?>"
            			attr-name="<?php echo $marker['name']; ?>" 
            			attr-address="<?php echo $marker['address']; ?>" 
            			attr-coords="<?php echo $marker['coords']; ?>"
            		></div>
            	<?php } } ?>
            </div>
          </div>
        </div>
        <h2><?php _e('Markers','mapinco_easymaps'); ?></h2>
                  
        <table class="widefat striped" id="markers_table">
        	<thead>
        		<tr>
        			<td class="col-name"><?php _e('Name','mapinco_easymaps'); ?></td>
        			<td class="col-address"><?php _e('Address','mapinco_easymaps'); ?></td>
        			<td class="col-actions"></td>
        		</tr>
        	</thead>
        	<tbody>
        	<?php if(count($markers)>0){ foreach($markers as $key=>$marker){ ?>
        	<tr id="marker-<?php echo $key; ?>">
        		<td class="marker_name"><?php echo $marker['name']; ?></td>
        		<td class="marker_address"><?php echo $marker['address']; ?></td>
        		<td>
        			<input type="hidden" class="marker_name_input" name="marker_name[]" value="<?php echo $marker['name']; ?>">
        			<input type="hidden" class="marker_address_input" name="marker_address[]" value="<?php echo $marker['address']; ?>">
        			<input type="hidden" class="marker_coords_input" name="marker_coords[]" value="<?php echo $marker['coords']; ?>">
        			<button class="button" name="marker_edit"><?php _e('Edit','mapinco_easymaps'); ?></button>
        			<button class="button" name="marker_delete"><?php _e('Delete','mapinco_easymaps'); ?></button>
        		</td>
        	</tr>          	
        	<?php } } ?>          	
        	</tbody>
        </table>
        <script id="marker_template" type="x-tmpl-mustache">          
        	<tr id="{{ id }}">
        		<td class="marker_name">{{ name }}</td>
        		<td class="marker_address">{{ address }}</td>
        		<td>
        			<input type="hidden" class="marker_name_input" name="marker_name[]" value="{{ name }}">
        			<input type="hidden" class="marker_address_input" name="marker_address[]" value="{{ address }}">
        			<input type="hidden" class="marker_coords_input" name="marker_coords[]" value="{{ coords }}">
        			<button class="button" name="marker_edit"><?php _e('Edit','mapinco_easymaps'); ?></button>
        			<button class="button" name="marker_delete"><?php _e('Delete','mapinco_easymaps'); ?></button>
        		</td>
        	</tr>
        </script>
        <h2><?php _e('Marker', 'mapinco_easymaps'); ?></h2>
        <div class="mapinco_easymaps_container">
          <div id="marker_addedit">          	
		        <div class="marker_block">
		          <label for="marker_name"><?php _e('Name', 'mapinco_easymaps'); ?></label><br>
		          <input type="text" id="marker_name">
		        </div>
		        <div class="marker_block">
		          <label for="marker_address"><?php _e('Address', 'mapinco_easymaps'); ?></label><br>
		          <input type="text" id="marker_address">
		          <input type="text" readonly id="marker_coords">
		        </div>
		        <div class="marker_block">
		        	<input type="hidden" id="marker_id">
		        	<button type="button" class="button" id="mapinco_easymaps_addmarker"><?php _e('Save Marker','mapinco_easymaps'); ?></button>
		        </div>
          </div>

        </div>
        <p class="submitbox">
        	<?php $delete_url = add_query_arg(
            [
                'action' => 'mapinco_easymaps_delete',
                'map_id'   => $map_id,
                'nonce'  => wp_create_nonce('mapinco_easymaps_admin_delete'),
            ], admin_url('admin.php')); ?>
        	<input type="submit" class="button button-primary" name="mapinco_easymaps_submit" value="<?php _e('Save Map','mapinco_easymaps'); ?>">
        	<?php if($map_id != 'new'){ ?><a href="<?php echo $delete_url; ?>" class="mapinco_easymaps_delete submitdelete"><?php _e('Delete','mapinco_easymaps'); ?></a><?php } ?>
        </p>
      </form>
  </div>
  <?php
}

function mapinco_easymaps_shortcode($atts){
	$ret = '';
	if(isset($atts['id'])){
		$map = get_post($atts['id']);
		if($map->post_type == 'mapinco_easymaps'){
			$height = get_post_meta($map->ID, 'height', true);
			$zoom = get_post_meta($map->ID, 'zoom', true);
			$zoom_control = get_post_meta($map->ID, 'zoom_control', true);
			$routing = get_post_meta($map->ID, 'routing', true);
			$fullscreen = get_post_meta($map->ID, 'fullscreen', true);
			$basemap = get_post_meta($map->ID, 'basemap', true);
			$markers = get_post_meta($map->ID, 'markers', true);
			$ret .= '<div class="mapinco_easymaps" style="margin:10px 0;height:'.$height.'px" 
				attr-zoom="'.$zoom.'" 
				attr-zoom-control="'.(bool)$zoom_control.'"
				attr-routing="'.(bool)$routing.'"
				attr-fullscreen="'.(bool)$fullscreen.'"
				attr-basemap="'.implode(',',$basemap).'"
				>';
			if(count($markers)>0){
				foreach($markers as $key=>$marker){
           $ret .= '<div class="marker" 
            			attr-id="marker-'.$key.'"
            			attr-name="'.$marker['name'].'" 
            			attr-address="'.$marker['address'].'" 
            			attr-coords="'.$marker['coords'].'"
            		></div>';
				}
			}
			$ret .= '</div>';
		}
	}
	return $ret;
}

function show_mapinco_easymaps($id){
	mapinco_easymaps_shortcode(array('id'	=>	$id));
}
