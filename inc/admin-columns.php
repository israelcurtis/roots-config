<?php
/**
 * modify how the inline edit input renders (handy for preventing multiple selections which is the default behavior)
 *
 * @param array     $data
 * @param AC\Column $column
 *
 * @return mixed
 *
 * @see https://github.com/codepress/admin-columns-hooks/blob/master/acp-editing-view_settings.php
 */
function acp_editing_view_settings( $data, AC\Column $column ) {
	// Check for a specific column
	// if( $column instanceof \AC\Column\Meta ){}
	// if( $column->get_type() === 'column-ancestors'
	// if( $column->get_name() === 'generated_name'
	if ( $column->get_post_type() === "stories" ) return $data;
	if ( $column->get_type() !== "taxonomy-years" ) return $data;

	// alter Data
	// $data['type'] = 'select'; // text|select|textarea|media|email|select2_dropdown
	// $data['options'] = []; // works in combination with select type
	$data['multiple'] = false; // works in combination with select type

	return $data;
}
add_filter( 'acp/editing/view_settings', 'acp_editing_view_settings', 10, 2 );



// ACP inline editing only modifies the taxonomy tag relationship, so we have to manually push the values that would normally get copied during a save_post
add_action('acp/editing/saved', 'acp_copy_tax_to_meta', 30, 3);
function acp_copy_tax_to_meta( $column, $post_id, $value ) {
	if ( ! $column instanceof AC\Column ) return; // bail for unknown case so we don't save shit
	if ( !in_array( $column->get_post_type(), array( 'stories', 'bulletins', 'videos', 'attachment'))) return; // only execute for these post types
	if ( !in_array( $column->get_type(), array( 'taxonomy-years', 'taxonomy-people', 'taxonomy-locations', 'taxonomy-projects'))) return; // only execute for these taxonomies

	// push to ACF fields, which otherwise will be out of sync with the new values until they get opened and saved in the editor
	// (they only load and save values from taxonomy relationships on the edit screen!)
	update_field( $column->get_taxonomy(), $value, $post_id);

	// save earliest year tag to postmeta -> because it's much easy to order wp queries by meta value than tax!!
	if ( $column->get_type() === "taxonomy-years" ) {
		roots_save_chronology( $value, $post_id );
	}
}




/* CONVERT WRITER/PHOTOGRAPHER NAME INTO PEOPLE TAXONOMY TAG */
// when saving from admin columns inline-edit
// https://www.admincolumns.com/documentation/action-reference/acp-editing-saved/
add_action('acp/editing/saved', 'acp_copy_meta_to_people', 20, 3);
function acp_copy_meta_to_people( $column, $post_id, $value ) {
	// Check for a custom field column
	if ( ! $column instanceof AC\Column\Meta ) return;
	// only fire for this specific combination
	if ( $column->get_post_type() === "attachment" && $column->get_meta_key() === "photographer" ) {
		$term = get_term_by('name', $value, 'people');
		if ( empty( $term ) ) return;
		wp_set_object_terms( $post_id, $term->slug, 'people', true );
		return;
	}
	// only fire for this specific combination
	if ( $column->get_post_type() === "stories" && $column->get_meta_key() === "writer" ) {
		$term = get_term_by('name', $value, 'people');
		if ( empty( $term ) ) return;
		wp_set_object_terms( $post_id, $term->slug, 'people', true );
		return;
	}
}



/**
 * add a class attribute to the rendered column value that can be styled by CSS if the image dimensions are too small and we need to warn the editor!
 *
 * @param string    $value  Column value
 * @param int       $id     Post ID, User ID, Comment ID, Attachement ID or Term ID
 * @param AC\Column $column Column object
 *
 * @return string
 * @see https://github.com/codepress/admin-columns-hooks/blob/master/ac-column-value.php
 */
function column_warning_for_tiny_images( $value, $id, AC\Column $column ) {
	if ( $column instanceof ACP\Column\Media\Dimensions ) {
		if ( !wp_attachment_is_image( $id ) ) return $value;
		$path = get_attached_file( $id );
		if ( ! file_exists( $path ) ) return $value;
		$info = getimagesize( $path );
		if ( $info[0] < 700 && $info[1] < 700 ) {
			$value = '<span class="admin-col-warning dashicons-before dashicons-warning">'.$value.'</span><br /><em>replace with larger version</em>';
		}
	}
	return $value;
}
add_filter( 'ac/column/value', 'column_warning_for_tiny_images', 10, 3 );

// inject some css icons to make it look pretty
add_action( 'admin_head-upload.php', 'roots_media_list_styles' );
function roots_media_list_styles() { ?>
<style type="text/css">
	.wp-list-table span.admin-col-warning { color: red;}
</style>
<?php
}


