<?php
/*
Plugin Name: Roots of Plenty CMS Config
Description: Adds CPT and Taxonomies, extended CMS functionality
Version: 1.3
Author: Israel Curtis
Author URI:
*/

// Do not load directly.
if ( ! defined( 'ABSPATH' ) ) {
	die( '-1' );
}

add_action( 'init', 'register_stories_type', 2 );
add_action( 'init', 'register_bulletins_type', 2 );
add_action( 'init', 'register_videos_type', 2 );
add_action( 'init', 'register_locations_tax', 1 );
add_action( 'init', 'register_years_tax', 1 );
add_action( 'init', 'register_projects_tax', 1 );
add_action( 'init', 'register_people_tax', 1 );

// remove core taxonomies
add_action( 'init', 'disable_core_taxonomies' );
function disable_core_taxonomies() {
	global $wp_taxonomies;
	unregister_taxonomy_for_object_type( 'post_tag', 'post' );
	unregister_taxonomy_for_object_type( 'category', 'post' );
	if ( taxonomy_exists( 'category'))
		unset( $wp_taxonomies['category']);
	if ( taxonomy_exists( 'post_tag'))
		unset( $wp_taxonomies['post_tag']);
	if ( taxonomy_exists( 'link_category'))
		unset( $wp_taxonomies['link_category']);
	unregister_taxonomy('link_category');
	unregister_taxonomy('category');
	unregister_taxonomy('post_tag');
}


// keep users logged in longer
add_filter( 'auth_cookie_expiration', 'stay_logged_in_for_1_year' );
function stay_logged_in_for_1_year( $expire ) {
  return 31556926; // 1 year in seconds
}

// override the 'pretty permalinks' for attachments and use a prefix plus the ID instead of filename for slugs
add_filter( 'attachment_link', 'wpd_attachment_link', 20, 2 );
function wpd_attachment_link( $link, $post_id ){
    return home_url( 'images/' . $post_id ."/");
}

// rewrite rule REQUIRED for the filtered links to resolve!
add_action( 'init', function() {
    add_rewrite_rule( 'images/([0-9]+)/?$', 'index.php?attachment_id=$matches[1]', 'top' );
} );


/*     CORE WP SITEMAP TWEAKS      */

// don't want authors listed
add_filter('wp_sitemaps_add_provider', 'remove_author_category_pages_from_sitemap', 10, 2);
function remove_author_category_pages_from_sitemap($provider, $name) {
	if ('users' === $name) return false;
	return $provider;
}

// custom sitemap provider class
add_action('init', function() {
	$imagesProvider = new roots_sitemapProvider('images', array('attachment'));
	wp_register_sitemap_provider('images', $imagesProvider);
});

class roots_sitemapProvider extends WP_Sitemaps_Provider {
  public $postTypes = array();

  public function __construct($name, $postTypes) {
    $this->name        = $name;
    $this->postTypes   = $postTypes;
    $this->object_type = 'images'; // ?? not used
  }

  private function queryArgs(){
    return array(
      'post_type'      => $this->postTypes,
      'post_status'    => 'inherit',
      'posts_per_page' => -1,
      'orderby'        => 'post_date',
      'order'          => 'DESC'
    );
  }

  /*--OVERRIDE-----------*/
  public function get_url_list($page_num, $post_type = '') {
    $query = new WP_Query($this->queryArgs());
    $urlList = array();

    foreach($query->posts as $post) {
      $sitemapEntry = array(
      	// optional tags in the protocol which search engines don't seem to need, so core WP only includes loc
        // 'changefreq' => 'weekly',
        // 'priority' => 1.0,
        'loc' => get_permalink($post),
        // 'lastmod' => get_the_modified_time('Y-m-d H:i:s', $post)
      );
      $sitemapEntry = apply_filters('wp_sitemaps_posts_entry', $sitemapEntry, $post, $post_type);
      $urlList[] = $sitemapEntry;
    }

    return $urlList;
  }
  /*--OVERRIDE-----------*/
  public function get_max_num_pages($post_type = '') {
    return 1; // not sure what the cutoff is for how many entries should be on a page, so just force 1
  }
}


// activation flush
register_activation_hook( __FILE__, 'roots_rewrite_flush' );
function roots_rewrite_flush() {
	flush_rewrite_rules();
}

// Register Custom Post Type
function register_stories_type() {

	$labels = array(
		'name'                  => 'Stories',
		'singular_name'         => 'Story',
		'menu_name'             => 'Stories',
		'name_admin_bar'        => 'Story',
		'archives'              => 'Story Archives',
		'attributes'            => 'Story Attributes',
		'parent_item_colon'     => 'Parent Story:',
		'all_items'             => 'All Stories',
		'add_new_item'          => 'Add New Story',
		'add_new'               => 'Add New',
		'new_item'              => 'New Story',
		'edit_item'             => 'Edit Story',
		'update_item'           => 'Update Story',
		'item_updated'          => 'Story Updated',
		'view_item'             => 'View Story',
		'view_items'            => 'View Stories',
		'search_items'          => 'Search Stories',
		'not_found'             => 'Not found',
		'not_found_in_trash'    => 'Not found in Trash',
		'featured_image'        => 'Header Image',
		'set_featured_image'    => 'Set Header image',
		'remove_featured_image' => 'Remove Header image',
		'use_featured_image'    => 'Use as Header image',
		'insert_into_item'      => 'Insert into Story',
		'uploaded_to_this_item' => 'Uploaded to this story',
		'items_list'            => 'Stories list',
		'items_list_navigation' => 'Stories list navigation',
		'filter_items_list'     => 'Filter stories list',
	);
	$args = array(
		'label'                 => 'Story',
		'description'           => 'Stories',
		'labels'                => $labels,
		'supports'              => array( 'title', 'editor', 'thumbnail', 'revisions' ),
		'taxonomies'            => array( 'people', 'locations', 'projects'),
		'hierarchical'          => false,
		'public'                => true,
		'show_ui'               => true,
		'show_in_menu'          => true,
		'menu_position'         => 5,
		'menu_icon'             => 'dashicons-media-document',
		'show_in_admin_bar'     => true,
		'show_in_nav_menus'     => true,
		'can_export'            => true,
		'has_archive'           => false,
		'exclude_from_search'   => false,
		'publicly_queryable'    => true,
		'capability_type'       => 'post',
		'show_in_rest'          => true,
	);
	register_post_type( 'stories', $args );

}
function register_bulletins_type() {

	$labels = array(
		'name'                  => 'Bulletins',
		'singular_name'         => 'Bulletin',
		'menu_name'             => 'Bulletins',
		'name_admin_bar'        => 'Bulletin',
		'archives'              => 'Bulletin Archives',
		'attributes'            => 'Bulletin Attributes',
		'parent_item_colon'     => 'Parent Bulletin:',
		'all_items'             => 'All Bulletins',
		'add_new_item'          => 'Add New Bulletin',
		'add_new'               => 'Add New',
		'new_item'              => 'New Bulletin',
		'edit_item'             => 'Edit Bulletin',
		'update_item'           => 'Update Bulletin',
		'item_updated'          => 'Bulletin Updated',
		'view_item'             => 'View Bulletin',
		'view_items'            => 'View Bulletins',
		'search_items'          => 'Search Bulletins',
		'not_found'             => 'Not found',
		'not_found_in_trash'    => 'Not found in Trash',
		'featured_image'        => 'Hero Image',
		'set_featured_image'    => 'Set hero image',
		'remove_featured_image' => 'Remove hero image',
		'use_featured_image'    => 'Use as hero image',
		'insert_into_item'      => 'Insert into Bulletin',
		'uploaded_to_this_item' => 'Uploaded to this Bulletin',
		'items_list'            => 'Bulletins list',
		'items_list_navigation' => 'Bulletins list navigation',
		'filter_items_list'     => 'Filter bulletin list',
	);
	$args = array(
		'label'                 => 'Bulletin',
		'description'           => 'Bulletins',
		'labels'                => $labels,
		'supports'              => array( 'title' ),
		'taxonomies'            => array( 'locations', 'people', 'years', 'projects' ),
		'hierarchical'          => false,
		'public'                => true,
		'show_ui'               => true,
		'show_in_menu'          => true,
		'menu_position'         => 5,
		'menu_icon'             => 'dashicons-media-interactive',
		'show_in_admin_bar'     => true,
		'show_in_nav_menus'     => true,
		'can_export'            => true,
		'has_archive'           => false,
		'exclude_from_search'   => false,
		'publicly_queryable'    => true,
		'capability_type'       => 'post',
		'show_in_rest'          => true,
	);
	register_post_type( 'bulletins', $args );

}

function register_videos_type() {

	$labels = array(
		'name'                  => 'Videos',
		'singular_name'         => 'Video',
		'menu_name'             => 'Videos',
		'name_admin_bar'        => 'Video',
		'archives'              => 'Video Archives',
		'attributes'            => 'Video Attributes',
		'parent_item_colon'     => 'Parent Video:',
		'all_items'             => 'All Videos',
		'add_new_item'          => 'Add New Video',
		'add_new'               => 'Add New',
		'new_item'              => 'New Video',
		'edit_item'             => 'Edit Video',
		'update_item'           => 'Update Video',
		'item_updated'          => 'Video Updated',
		'view_item'             => 'View Video',
		'view_items'            => 'View Videos',
		'search_items'          => 'Search Videos',
		'not_found'             => 'Not found',
		'not_found_in_trash'    => 'Not found in Trash',
		'featured_image'        => 'Thumbnail Image',
		'set_featured_image'    => 'Set Thumbnail image',
		'remove_featured_image' => 'Remove Thumbnail image',
		'use_featured_image'    => 'Use as Thumbnail image',
		'insert_into_item'      => 'Insert into video',
		'uploaded_to_this_item' => 'Uploaded to this video',
		'items_list'            => 'Videos list',
		'items_list_navigation' => 'Videos list navigation',
		'filter_items_list'     => 'Filter video list',
	);
	$args = array(
		'label'                 => 'Video',
		'description'           => 'Videos',
		'labels'                => $labels,
		'supports'              => array( 'title' ),
		'taxonomies'            => array( 'category' ),
		'hierarchical'          => false,
		'public'                => true,
		'show_ui'               => true,
		'show_in_menu'          => true,
		'menu_position'         => 5,
		'menu_icon'             => 'dashicons-media-video',
		'show_in_admin_bar'     => true,
		'show_in_nav_menus'     => true,
		'can_export'            => true,
		'has_archive'           => false,
		'exclude_from_search'   => false,
		'publicly_queryable'    => true,
		'capability_type'       => 'post',
		'show_in_rest'          => true,
	);
	register_post_type( 'videos', $args );

}



// Register Custom Taxonomy
function register_locations_tax() {

	$labels = array(
		'name'                       => 'Locations',
		'singular_name'              => 'Location',
		'menu_name'                  => 'Locations',
		'all_items'                  => 'All Locations',
		'parent_item'                => 'Parent Location',
		'parent_item_colon'          => 'Parent Location:',
		'new_item_name'              => 'New Location Name',
		'add_new_item'               => 'Add New Location',
		'edit_item'                  => 'Edit Location',
		'update_item'                => 'Update Location',
		'view_item'                  => 'View Location',
		'separate_items_with_commas' => 'Separate locations with commas',
		'add_or_remove_items'        => 'Add or remove locations',
		'choose_from_most_used'      => 'Choose from the most used',
		'popular_items'              => 'Popular Locations',
		'search_items'               => 'Search Locations',
		'not_found'                  => 'Not Found',
		'no_terms'                   => 'No Locations',
		'items_list'                 => 'Locations list',
		'items_list_navigation'      => 'Locations list navigation',
		'item_link'                  => 'Location link',
		'item_link_description'      => 'A link to a Location',
		'back_to_items'              => '&larr; Back to Locations'
	);
	$args = array(
		'labels'                     => $labels,
		'hierarchical'               => false,
		'public'                     => true,
		'show_ui'                    => true,
		'show_admin_column'          => true,
		'show_in_nav_menus'          => true,
		'show_tagcloud'              => true,
		'show_in_rest'               => true,
		'meta_box_cb'                => false,
	);
	register_taxonomy( 'locations', array( 'stories', 'bulletins','videos', 'attachment' ), $args );

}
function register_years_tax() {

	$labels = array(
		'name'                       => 'Years',
		'singular_name'              => 'Year',
		'menu_name'                  => 'Years',
		'all_items'                  => 'All Years',
		'parent_item'                => 'Parent Year',
		'parent_item_colon'          => 'Parent Year:',
		'new_item_name'              => 'New Year Name',
		'add_new_item'               => 'Add New Year',
		'edit_item'                  => 'Edit Year',
		'update_item'                => 'Update Year',
		'view_item'                  => 'View Year',
		'separate_items_with_commas' => 'Separate years with commas',
		'add_or_remove_items'        => 'Add or remove Years',
		'choose_from_most_used'      => 'Choose from the most used',
		'popular_items'              => 'Popular Years',
		'search_items'               => 'Search Years',
		'not_found'                  => 'Not Found',
		'no_terms'                   => 'No Years',
		'items_list'                 => 'Years list',
		'items_list_navigation'      => 'Years list navigation',
		'item_link'                  => 'Year link',
		'item_link_description'      => 'A link to a Year',
		'back_to_items'              => '&larr; Back to Years'
	);
	$args = array(
		'labels'                     => $labels,
		'hierarchical'               => false,
		'public'                     => true,
		'show_ui'                    => true,
		'show_admin_column'          => true,
		'show_in_nav_menus'          => true,
		'show_tagcloud'              => true,
		'show_in_rest'               => true,
		'meta_box_cb'                => false,
	);
	register_taxonomy( 'years', array( 'stories', 'bulletins', 'videos','attachment' ), $args );

}
function register_projects_tax() {

	$labels = array(
		'name'                       => 'Projects',
		'singular_name'              => 'Project',
		'menu_name'                  => 'Projects',
		'all_items'                  => 'All Projects',
		'parent_item'                => 'Parent Project',
		'parent_item_colon'          => 'Parent Project:',
		'new_item_name'              => 'New Project Name',
		'add_new_item'               => 'Add New Project',
		'edit_item'                  => 'Edit Project',
		'update_item'                => 'Update Project',
		'view_item'                  => 'View Project',
		'separate_items_with_commas' => 'Separate Projects with commas',
		'add_or_remove_items'        => 'Add or remove Projects',
		'choose_from_most_used'      => 'Choose from the most used',
		'popular_items'              => 'Popular Projects',
		'search_items'               => 'Search Projects',
		'not_found'                  => 'Not Found',
		'no_terms'                   => 'No Projects',
		'items_list'                 => 'Projects list',
		'items_list_navigation'      => 'Projects list navigation',
		'item_link'                  => 'Project link',
		'item_link_description'      => 'A link to a Project',
		'back_to_items'              => '&larr; Back to Projects'
	);
	$args = array(
		'labels'                     => $labels,
		'hierarchical'               => false,
		'public'                     => true,
		'show_ui'                    => true,
		'show_admin_column'          => true,
		'show_in_nav_menus'          => true,
		'show_tagcloud'              => true,
		'show_in_rest'               => true,
		'meta_box_cb'                => false,
	);
	register_taxonomy( 'projects', array( 'stories', 'bulletins', 'videos', 'attachment' ), $args );

}

function register_people_tax() {

	$labels = array(
		'name'                       => 'People',
		'singular_name'              => 'Person',
		'menu_name'                  => 'People',
		'all_items'                  => 'All People',
		'parent_item'                => 'Parent Person',
		'parent_item_colon'          => 'Parent Person:',
		'new_item_name'              => 'New Person Name',
		'add_new_item'               => 'Add New Person',
		'edit_item'                  => 'Edit Person',
		'update_item'                => 'Update Person',
		'view_item'                  => 'View Person',
		'separate_items_with_commas' => 'Separate Person with commas',
		'add_or_remove_items'        => 'Add or remove People',
		'choose_from_most_used'      => 'Choose from the most used',
		'popular_items'              => 'Popular People',
		'search_items'               => 'Search People',
		'not_found'                  => 'Not Found',
		'no_terms'                   => 'No People',
		'items_list'                 => 'People list',
		'items_list_navigation'      => 'People list navigation',
		'item_link'                  => 'Person link',
		'item_link_description'      => 'A link to a Person',
		'back_to_items'              => '&larr; Back to People'
	);
	$args = array(
		'labels'                     => $labels,
		'hierarchical'               => false,
		'public'                     => true,
		'show_ui'                    => true,
		'show_admin_column'          => true,
		'show_in_nav_menus'          => true,
		'show_tagcloud'              => true,
		'show_in_rest'               => true,
		'meta_box_cb'                => false,   // hides metabox on the Edit Media page (only when gutenberg disabled!)
		'rewrite'                    => array('slug' => 'people')
	);
	register_taxonomy( 'people', array( 'stories', 'bulletins', 'videos', 'attachment' ), $args );

}


/**
 * Allow HTML in taxonomy term descriptions (post_content)
 * http://www.thepixelpixie.com/enabling-html-in-your-category-taxonomy-descriptions/
 */
// remove_filter( 'pre_term_description', 'wp_filter_kses' );
// remove_filter( 'pre_link_description', 'wp_filter_kses' );
// remove_filter( 'pre_link_notes', 'wp_filter_kses' );
// remove_filter( 'term_description', 'wp_kses_data' );

/* remove term description textarea box from term editor */
function wpse_hide_term_slug() { ?>
	<style type="text/css">
	   .term-slug-wrap {
		   display: none;
	   }
	</style>
<?php
}
add_action( 'admin_head-term.php', 'wpse_hide_term_slug' );
add_action( 'admin_head-edit-tags.php', 'wpse_hide_term_slug' );



/* post revision deletion plugin */
function roots_wpsrd_add_post_types( $postTypes ){
	$postTypes[] = 'stories';
	$postTypes[] = 'bulletins';
	$postTypes[] = 'videos';
	return $postTypes;
}
add_filter( 'wpsrd_post_types_list', 'roots_wpsrd_add_post_types' );


/**
 * Remove Veno Box disable checkbox for all post types, since the veno author didn't give us the option THIS SEEMS TO KILL CORE METABOX?
 */
function wpdocs_remove_plugin_metaboxes(){
	remove_meta_box( 'post_options', get_post_types(), 'side' );
}
add_action( 'do_meta_boxes', 'wpdocs_remove_plugin_metaboxes', 99 );


// hide the core tagging boxes on the side (only applies to stories because that's the only post type to use gutenberg blocks)
/**
 * Disable display of Gutenberg Post Setting UI for a specific
 * taxonomy. While this isn't the official API for this need,
 * it works for now because only Gutenberg is dependent on the
 * REST API response.
 */
add_filter( 'rest_prepare_taxonomy', function( $response, $taxonomy ){
	if ( $taxonomy->name == ( 'people' || 'locations' || 'years' || 'projects' )) {
		$response->data['visibility']['show_ui'] = false;
	}
	return $response;
}, 10, 2 );



/* DYNAMIC image ALT tag generation, assembling from taxonomy terms (probably database-intensive and slow for pages with lots of images, as it has to query multiple times for each image. would be more efficient to make just one call to the postmeta, but that would have to be rendered upon save [see image_alt_builder() ], which doesn't happen often with images)
*/
add_filter( 'wp_get_attachment_image_attributes', 'roots_dynamic_image_alt', 50, 3);
function roots_dynamic_image_alt( $attr, $attachment, $size ) {
	if ( !is_single() ) return $attr; // only singles! otherwise huge grid of images would result in tons of DB hits
	$tags = roots_fetch_tags( $attachment->ID, "list" );
	// append whatever's been saved already in the _wp_attachment_image_alt postmeta
	if ( !empty ( $attr['alt'] ) ) {
		$tags .= ", ".$attr['alt'];
	}
	$attr['alt'] = $tags;
	return $attr;
}

/*
 * @return list	comma separated flat list of all term names (for use in alt tags)
 * @return array taxonomy names with arrays of term ids (for use in rendering meta tables)
*/
function roots_fetch_tags( $post_id = 0, $context = "array", $taxes = array('people', 'years', 'locations', 'projects'), $exclude_term_id = 0 ) {
	// input validation
	if ( empty( $post_id ) ) return null;
	if ( empty( $context ) ) return null;
	if ( !is_array( $taxes) || empty( $taxes ) ) return null;
	// bulletin images aren't tagged themselves, so query the parent post instead
	if ( !empty( wp_get_post_parent_id( $post_id ) ) && get_post_type( wp_get_post_parent_id( $post_id ) ) == "bulletins" ) {
		$post_id = wp_get_post_parent_id( $post_id );
	}
	// init
	$output = array();
	// cycle through taxonomies and fetch terms
	foreach ( $taxes as $tax_slug ) {
		$terms = wp_get_object_terms( $post_id, $tax_slug, array( 'exclude' => $exclude_term_id ) );
		if ( $context == "list" && empty( $terms ) ) continue;
		if ( $context == "list" ) {
			$output = array_merge( $output, wp_list_pluck( $terms, "name" ) );
			continue;
		}
		// default array context
		$tax = get_taxonomy( $tax_slug );
		$output[ $tax->label ] = array();
		if ( empty( $terms ) ) continue;
		// populate with found term ids
		$output[ $tax->label ] = wp_list_pluck( $terms, "term_id" );
	}
	if ( $context == "list" ) return join( ', ', $output );
	return $output;
}


function roots_save_chronology( $years, $post_id = null ) {
	if ( empty( $post_id ) ) return;  // bail on goof
	if ( is_array( $years ) ) {
		// stories are allowed to have multiple year tags, but they aren't necessarily stored in chronological order, so we have to sort them by name to find the 'earliest'
		$yearlist = array(); // init sorting hat
		foreach ( $years as $id ) { $yearlist[$id] = intval( get_term( $id )->name ); }
		asort( $yearlist ); // sorts array by value (years) while mainting key (term_id) relationship
		$term_id = array_key_first( $yearlist ); // grab first key, which should now be the 'earliest' tag id
	} else {
		$term_id = $years; // all other types should just have single term id string
	}

	$yearname = get_term( $term_id )->name;
	if ( empty( $yearname ) ) $yearname = 1000;  // arbitrary fallback value, avoids possibly failing to be included in an ordered meta query -- didn't want it to be confused as empty/false if the value were saved as 'zero' or missing

	update_post_meta( $post_id, 'chronology', intval( $yearname ) );
}



//*********************** load abstracted *******************************//

// the URL path to the plugin's directory - taking note of current scheme
if (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] == 'on') {
	$wp_plugin_url = str_replace( 'http://' , 'https://' , WP_PLUGIN_URL );
} else {
	$wp_plugin_url = WP_PLUGIN_URL;
}
$cmc_url = $wp_plugin_url . '/roots-config/';
$cmc_dir = WP_PLUGIN_DIR . '/roots-config/';
$cmc_inc = $cmc_dir . 'inc/';
foreach( glob( $cmc_inc ."*.php" ) as $filename) {
	require_once $filename;
}