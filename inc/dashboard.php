<?php
/*
* add our custom assets to the Dashboard 'at a glance' counts
*/
add_filter( 'dashboard_glance_items', 'roots_glance_items', 10, 1 );

function roots_glance_items( $items = array() ) {
	// custom post types
	$custom_types = array( 'stories', 'attachment', 'bulletins', 'videos' );
	foreach( $custom_types as $type ) {
		if( ! post_type_exists( $type ) ) continue;
		$num_posts = wp_count_posts( $type );
		if( $num_posts ) {
			$published = intval( $num_posts->publish + $num_posts->inherit );
			$post_type = get_post_type_object( $type );
			$text = _n( '%s ' . $post_type->labels->singular_name, '%s ' . $post_type->labels->name, $published, 'your_textdomain' );
			$text = sprintf( $text, number_format_i18n( $published ) );
			if ( current_user_can( $post_type->cap->edit_posts ) ) {
				$items[] = sprintf( '<a class="%1$s-count" href="edit.php?post_type=%1$s">%2$s</a>', $type, $text ) . "\n";
			} else {
				$items[] = sprintf( '<span class="%1$s-count">%2$s</span>', $type, $text ) . "\n";;
			}
		}
	}
	// custom taxonomies
	$custom_taxes = array( 'people', 'locations', 'projects', 'years' );
	foreach ($custom_taxes as $tax) {
		if ( !taxonomy_exists( $tax )) continue;
		$num_terms = wp_count_terms( $tax );
		if ($num_terms ) {
			$tax_obj = get_taxonomy( $tax );
			$text = _n( '%s ' . $tax_obj->labels->singular_name, '%s ' . $tax_obj->labels->name, $published, 'your_textdomain' );
			$text = sprintf( $text, number_format_i18n( $num_terms ) );
			if ( current_user_can( $tax_obj->cap->edit_terms ) ) {
				$items[] = sprintf( '<a class="%1$s-count" href="edit-tags.php?taxonomy=%1$s">%2$s</a>', $tax, $text ) . "\n";
			} else {
				$items[] = sprintf( '<span class="%1$s-count">%2$s</span>', $tax, $text ) . "\n";;
			}
		}
	}
	return $items;
}
// inject some css icons to make it look pretty
add_action( 'admin_head-index.php', 'roots_glance_icons' );
function roots_glance_icons() { ?>
<style type="text/css">
	#dashboard_right_now a.stories-count:before,
	#dashboard_right_now span.stories-count:before { content: "\f497"; }
	#dashboard_right_now a.bulletins-count:before,
	#dashboard_right_now span.bulletins-count:before { content: "\f496"; }
	#dashboard_right_now a.videos-count:before,
	#dashboard_right_now span.videos-count:before { content: "\f490"; }
	#dashboard_right_now a.people-count:before,
	#dashboard_right_now span.people-count:before { content: "\f110"; }
	#dashboard_right_now a.attachment-count:before,
	#dashboard_right_now span.attachment-count:before { content: "\f104"; }
	#dashboard_right_now a.locations-count:before,
	#dashboard_right_now span.locations-count:before { content: "\f11f"; }
	#dashboard_right_now a.projects-count:before,
	#dashboard_right_now span.projects-count:before { content: "\f155"; }
</style>
<?php
}


// disable access to core posts menu
add_action( 'admin_menu', 'roots_admin_menus', 3 );
function roots_admin_menus() {
	remove_menu_page( 'edit.php' );
}

// remove admin bar items
add_action( 'admin_bar_menu', 'prune_admin_bar', 999 );
function prune_admin_bar( $wp_admin_bar ) {
	if ( is_admin() ) {
		$wp_admin_bar->remove_node( 'wp-logo' );
		$wp_admin_bar->remove_node( 'customize' );
		$wp_admin_bar->remove_node( 'comments' );
		$wp_admin_bar->remove_node( 'wpseo-menu' );		
		$wp_admin_bar->remove_node( 'new-content' );
	}
	if ( !is_admin() ) {
		$wp_admin_bar->remove_node( 'wp-logo' );
		$wp_admin_bar->remove_node( 'comments' );
		$wp_admin_bar->remove_node( 'customize' );
		$wp_admin_bar->remove_node( 'new-content' );	
	}
}