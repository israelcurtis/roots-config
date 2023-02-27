<?php
// remove core taxonomy fields from the modal attachment details editor, because they're not a great way to tag
// also critical to allowing ACF fields to get auto-saved! if the core fields are left empty, they overwrite the ACF changes
function roots_attachment_remove_taxonomy( $fields ){
	unset($fields['locations']);
	unset($fields['years']);
	unset($fields['people']);
	unset($fields['projects']);
	return $fields;
}
add_filter( 'attachment_fields_to_edit', 'roots_attachment_remove_taxonomy' );


// populate the select field choices with all the terms in the People taxonomy
function acf_load_writer_field_choices( $field ) {

	// reset choices
	$field['choices'] = array();
	$args = array(
		'taxonomy'	=> 'people',
		'orderby'	=> 'name',
		'order'		=> 'ASC',
		'hide_empty'=> false
	);
	$peopleobj = new WP_Term_Query($args);
	foreach ($peopleobj->get_terms() as $person) {
		$field['choices'][ $person->name ] = $person->name;
	}
	// return the field
	return $field;

}
add_filter('acf/load_field/name=writer', 'acf_load_writer_field_choices');
add_filter('acf/load_field/name=photographer', 'acf_load_writer_field_choices');



/* CONVERT WRITER/PHOTOGRAPHER NAME INTO PEOPLE TAXONOMY TAG */
// when saving post from post editor screen
add_action('acf/save_post', 'acf_copy_meta_to_people', 20);
function acf_copy_meta_to_people( $post_id ) {
	$type = get_post_type( $post_id );
	if ( empty( $type ) ) return; // skip if anything other than post, taxonomy terms also have acf fields!
	if ( $type == "stories" || $type == "attachment" ) {
		if ( $type == "stories" ) $person = get_field('writer', $post_id);
		if ( $type == "attachment" ) $person = get_field('photographer', $post_id);
		if ( empty( $person ) ) return;
		$term = get_term_by('name', $person, 'people');
		wp_set_object_terms( $post_id, $term->slug, 'people', true );
	}
}


/**
 * Modify elements in the attachment details of the Media modal
 * Also removing certain input fields completely (css hiding isn't enough) so they don't override our ACF ones when saving
 * we're modifying the output of the attachment-details-two-column template in media-template.php (not filtering, but changing after render)
 */
add_action( 'print_media_templates', 'roots_media_panel_tweaks' );
function roots_media_panel_tweaks() {
	$currentScreen = get_current_screen();
	if( "upload" === $currentScreen->id ) : ?>
	<style>
	.edit-attachment-frame #alt-text-description,
	.edit-attachment-frame p.media-types-required-info { display: none; }
	.edit-attachment-frame .acf-field textarea { height: 112px; }
	.media-modal .attachment-details span[data-setting="alt"] {display: none;}
	</style>';
	<!-- Extend the Attachment Details View -->
	<script>
	 jQuery(document).ready( function( $ ) {
		if (wp.media) {
			wp.media.view.Attachment.Details.prototype.on("ready", function() {
				// $("#attachment-details-two-column-caption").prop("disabled", true);
				// $('span[data-setting="caption"]').remove();
				$('span[data-setting="alt"]').remove();
				$('span[data-setting="title"]').remove();
				$('span[data-setting="url"]').remove();
				$('span[data-setting="description"]+span').remove();
				$('span[data-setting="description"]').remove();
			});
		}
	});
	</script>
	<?php endif;
}

// media list view, clicking column image redirects to the grid view and (hopefully) opens the attachment modal
// wish we could get to go back there when it closes, but instead goes to the grid
// https://wordpress.stackexchange.com/questions/236817/open-media-frame-and-select-an-attachment
add_action('admin_head', function() {
	?>
	<script type="text/javascript">
		jQuery(document).ready(function($){
			$( '.wp-list-table span.ac-image' ).css('cursor', 'pointer');
			$( '.wp-list-table span.ac-image' ).on( 'click', function( event ) {
				var selected = $( this ).attr( 'data-media-id' );
				event.preventDefault();
				var url = "<?php get_admin_url();?>upload.php?item="+selected+"&mode=grid";
				window.location = url;
			});

		});
	</script>
	<?php
});


// removes core side panels from the gutenberg editor (very different than how we unhook metaboxes on the classic editor)
// has to be done via JS object, doesn't seem to have a php hook
add_action('admin_head', function() {
	$currentScreen = get_current_screen();
	error_log(print_r($currentScreen, true));
	if ( $currentScreen->base == "post" && $currentScreen->post_type == "stories" ) {
		?>
		<script type="text/javascript">
			jQuery(document).ready(function($){
				wp.data.dispatch( 'core/edit-post').removeEditorPanel( 'post-link' );    // PERMALINK SLUG
				// wp.data.dispatch( 'core/edit-post').removeEditorPanel( 'featured-image' );   // HEADER IMAGE
				wp.data.dispatch( 'core/edit-post').removeEditorPanel( 'post-status' );    // STATUS & VISIBILITY
			});
		</script>
		<?php
	}
});


// grab youtube oembed during save and extract ID to construct static thumbnail URL and save to meta
add_action('acf/save_post', 'stash_video_thumb_url', 30);
function stash_video_thumb_url( $post_id ) {
	if ( get_post_type( $post_id ) === "videos" ) {
	$oembed = get_field('video_url', $post_id);
		if ( $oembed ) {
	        if (preg_match('%(?:youtube\.com/(?:[^/]+/.+/|(?:v|e(?:mbed)?)/|.*[?&]v=)|youtu\.be/)([^"&?/ ]{11})%i', $oembed, $match)) {
	            $yid = $match[1];
	        }
			$youtube_thumb_url = 'https://i.ytimg.com/vi/' . $yid . '/hqdefault.jpg';
			update_post_meta( $post_id, 'video_thumb_url', $youtube_thumb_url );
		}
	}
}

// copy bulletin field to the post_content (which we never display) so that the default wordpress search functions can scrape it
add_action('acf/save_post', 'acf_copy_rawtext_to_content', 40);
function acf_copy_rawtext_to_content( $post_id ) {
	if ( get_post_type( $post_id ) === "bulletins" ) {
	$raw = get_field('raw_text', $post_id);
		if ( $raw ) {
			wp_update_post( array(
				'ID'           => $post_id,
				'post_content'  => $raw,
			));
		}
	}
}

// save earliest year tag to postmeta -> because it's much easy to order wp queries by meta value than tax!!
add_action('acf/save_post', 'acf_copy_year_to_chronology', 50);
function acf_copy_year_to_chronology( $post_id ) {
	if ( !in_array( get_post_type( $post_id ), array( "bulletins", "stories", "attachment", "videos" ) ) ) return; // only fire for these post types, breaks if saving a taxonomy term
	// bulletin images don't have tags themselves, so grab the parent's
	if ( !empty( wp_get_post_parent_id( $post_id ) ) && get_post_type( wp_get_post_parent_id( $post_id ) ) == "bulletins" ) {
		$years = get_field('years', wp_get_post_parent_id( $post_id ) );
	} else {
		$years = get_field('years', $post_id );
	}
	roots_save_chronology( $years, $post_id );
}





// give bulletin media files a title and page number so they can be easily identified in the admin
// also don't want image files uploaded to Bulletin galleries to be included in the Archives, so set the UNLISTED meta
add_action('acf/save_post', 'tag_bulletin_pages', 60);
function tag_bulletin_pages( $post_id ) {
	if ( get_post_type( $post_id ) === "bulletins" ) {
		$pages = get_field( 'pdf_pages', $post_id );
		if ( $pages ) {
			$i = 1;
			foreach ($pages as $page) {
				// inject bulletin name in the caption plus the page number
				wp_update_post( array(
					'ID'           => $page,
					'menu_order'   => $i-1,
					'post_parent'  => $post_id, // this ensures the connection as child of the bulletin post
					'post_excerpt' => 'Bulletin ' .wp_specialchars_decode( get_the_title( $post_id ) ).' Pg.'.$i
				));
				update_post_meta( $page, 'unlisted', '1'); // don't appear in archive lists
				$i++;
			}

		}
		$pdf = get_field( 'bulletin_pdf', );
		if ( $pdf ) {
			wp_update_post( array(
				'ID'           => $pdf,
				'post_parent'  => $post_id, // this ensures the connection as child of the bulletin post
				'post_excerpt' => 'PDF Bulletin ' .wp_specialchars_decode(get_the_title($post_id))
			));
			update_post_meta( $pdf, 'unlisted', '1'); // don't appear in archive lists
			// roots_scrape_pdf( $post_id, $pdf );
		}
	}
}

// possible future thingy that extracts text from pdf and saves, but PDF2TEXT DECODING IS HORRIBLE! NEED BETTER LIBRARY
function roots_scrape_pdf( $post_id, $att_id = 0 ) {
	if (empty($att_id)) return null;
	if (empty($post_id)) return null;
	$path = get_attached_file( $att_id );
	if ($path) {
		$a = new PDF2Text();
		$a->setFilename($path);
		$a->decodePDF();
		$output = $a->output();
		wp_die(var_dump($output));
	}
	update_post_meta( $post_id, 'raw_text', $output);
	wp_update_post( array(
		'ID'            => $post_id,
		'post_content'  => $output,
	));
}



// populate image ALT metadata with all the taxonomy terms upon save. efficient, but need to make sure it's getting triggered in all the various places where tags are being added (not just when saving from the attachment editor), and also triggered after terms have been assigned
// CURRENTLY we are dynamically rendering ALT tags with roots_dynamic_image_alt() instead. would be more efficent to store in postmeta tho
// add_action('acf/save_post', 'image_alt_builder', 70);
function image_alt_builder( $post_id ) {
	if ( get_post_type( $post_id ) === "attachment" ) {
		$tags = roots_fetch_tags( $post_id, "list" );
		update_post_meta( $post_id, '_wp_attachment_image_alt', $tags );
	}
}


// IF WE WANTED TO HANDLE TITLES AND BODY CONTENT IN ACF
// https://support.advancedcustomfields.com/forums/topic/front-end-form-how-to-change-post_title-and-post_content-properties/
function roots_acf_save_post( $post_id ) {
	if ( get_post_type( $post_id ) === 'acf' ) return;
	if (empty($_POST['acf']))
		return;

	// if ( $GLOBALS["acf_form"]["new_post"]["post_type"] == "stories" ) {
	// 	$_POST['acf']['_post_title'] = wp_strip_all_tags($_POST['acf']['field_577ba2a05bb33']);
	// 	$_POST['acf']['_post_content'] = $_POST['acf']['field_577ba2b153bb51'];
	// }

	if ( $GLOBALS["acf_form"]["new_post"]["post_type"] == "bulletins" ) {
		// $_POST['acf']['_post_content'] = wp_strip_all_tags($_POST['acf']['field_617bca1e92838']);
	}
	// if ( $GLOBALS["acf_form"]["new_post"]["post_type"] == "videos" ) {
	// 	$_POST['acf']['_post_title'] = wp_strip_all_tags($_POST['acf']['field_577ba2a05bb33']);
	// 	$_POST['acf']['_post_content'] = $_POST['acf']['field_577ba2b153bb51'];
	// }

	return $post_id;
}
// add_action('acf/pre_save_post', 'roots_acf_save_post', 1);



// filters the acf update value but this is modified from the tutorial because we're not using a single universal field, but ones named by type
// we're using multiple source/destination fields, so they have to be mapped
// https://www.advancedcustomfields.com/resources/bidirectional-relationships/
add_filter('acf/update_value/name=related_stories', 'bidirectional_post_relationship', 10, 3);
add_filter('acf/update_value/name=related_videos', 'bidirectional_post_relationship', 10, 3);
add_filter('acf/update_value/name=related_bulletins', 'bidirectional_post_relationship', 10, 3);

function bidirectional_post_relationship( $value, $post_id, $field  ) {
	// vars
	$field_name = $field['name'];
	$field_key = $field['key'];
	$global_name = 'is_updating_' . $field_name;

	// bail early if this filter was triggered from the update_field() function called within the loop below
	// - this prevents an inifinte loop
	if( !empty($GLOBALS[ $global_name ]) ) return $value;


	// set global variable to avoid inifite loop
	// - could also remove_filter() then add_filter() again, but this is simpler
	$GLOBALS[ $global_name ] = 1;

	// must construct the destination field name based on the originating post! (not the related field being saved!)
	$related_field = "related_".get_post_type($post_id);


	// loop over selected posts and add this $post_id
	if( is_array($value) ) {

		foreach( $value as $post_id2 ) {

			// load existing related posts
			$value2 = get_field($related_field, $post_id2, false);


			// allow for selected posts to not contain a value
			if( empty($value2) ) {

				$value2 = array();

			}


			// bail early if the current $post_id is already found in selected post's $value2
			if( in_array($post_id, $value2) ) continue;


			// append the current $post_id to the selected post's 'related_posts' value
			$value2[] = $post_id;


			// update the selected post's value (use field's key for performance)
			update_field($related_field, $value2, $post_id2);

		}

	}


	// find posts which have been removed
	$old_value = get_field($field_name, $post_id, false);

	if( is_array($old_value) ) {

		foreach( $old_value as $post_id2 ) {

			// bail early if this value has not been removed
			if( is_array($value) && in_array($post_id2, $value) ) continue;


			// load existing related posts
			$value2 = get_field($related_field, $post_id2, false);


			// bail early if no value
			if( empty($value2) ) continue;


			// find the position of $post_id within $value2 so we can remove it
			$pos = array_search($post_id, $value2);


			// remove
			unset( $value2[ $pos] );


			// update the un-selected post's value (use field's key for performance)
			update_field($related_field, $value2, $post_id2);

		}

	}


	// reset global varibale to allow this filter to function as per normal
	$GLOBALS[ $global_name ] = 0;

	// return
    return $value;
}



// filters the acf update value but this is MODIFIED specifically to handle term <-> term relationships! not post2post like the tutorial post
// various bits require different labels and strings
// also we're using multiple source/destination fields, so they have to be mapped
// https://www.advancedcustomfields.com/resources/bidirectional-relationships/

add_filter('acf/update_value/name=related_people', 'bidirectional_term_relationship', 10, 4);
add_filter('acf/update_value/name=related_locations', 'bidirectional_term_relationship', 10, 4);
add_filter('acf/update_value/name=related_projects', 'bidirectional_term_relationship', 10, 4);

function bidirectional_term_relationship( $value, $term_str, $field, $original ) {
	$term_id = trim( $term_str, "term_"); // strip the prefix from the id since this is a term!
	$term_obj = get_term( $term_id );
	// vars
	$field_name = $field['name'];
	$field_key = $field['key'];
	$global_name = 'is_updating_' . $field_name;

	// must construct the destination field name based on the originating term! (not the field being saved!)
	$related_field = "related_".$term_obj->taxonomy;

	// bail early if this filter was triggered from the update_field() function called within the loop below
	// - this prevents an inifinte loop
	if( !empty($GLOBALS[ $global_name ]) ) return $value;

	// set global variable to avoid inifite loop
	// - could also remove_filter() then add_filter() again, but this is simpler
	$GLOBALS[ $global_name ] = 1;

	// loop over related terms and add this $term_id to theirs
	if( is_array($value) ) {

		foreach( $value as $term_id2 ) {

			// load existing related terms from destination term
			$value2 = get_field($related_field, get_term($term_id2), false);

			// allow for selected terms to not contain a value
			if ( empty($value2) ) {
				$value2 = array();
			}

			// bail early if the current $term_id is already found in selected term's $value2
			if( in_array($term_id, $value2) ) continue;

			// append the current $term_id to the selected term's 'related_' value
			$value2[] = $term_id;

			// acf update field need to append string for terms, otherwise assumes post
			$term_id2 = "term_".$term_id2;
			// update the selected term's value (use field's key for performance)
			update_field($related_field, $value2, $term_id2);
		}
	}

	// find terms which have been removed in the orignal and remove from the relations
	$old_value = get_field($field_name, $term_obj, false);

	if( is_array($old_value) ) {

		foreach( $old_value as $term_id2 ) {

			// bail early if this value has not been removed
			if( is_array($value) && in_array($term_id2, $value) ) continue;


			// load existing related terms from destination term
			$value2 = get_field($related_field, get_term($term_id2), false);


			// bail early if no value
			if( empty($value2) ) continue;


			// find the position of $term_id within $value2 so we can remove it
			$pos = array_search($term_id, $value2);


			// remove
			unset( $value2[ $pos] );

			$term_id2 = "term_".$term_id2;
			// update the un-selected term's value (use field's key for performance)
			update_field($related_field, $value2, $term_id2);
		}
	}


	// reset global varibale to allow this filter to function as per normal
	$GLOBALS[ $global_name ] = 0;


	// return
    return $value;

}
