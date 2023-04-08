<?php


/************************/
// INCLUDE ADDITIONAL SEARCH HITS IF A POST HAS TAGS THAT MATCH THE SEARCH TERM
// https://wordpress.stackexchange.com/questions/2623/include-custom-taxonomy-term-in-search
function roots_search_where($where){
	if (is_admin()) return $where;
	global $wpdb, $wp_query;
	if (is_search()) {
		$search_terms = get_query_var( 'search_terms' );
		$where .= " OR (";
		$i = 0;
		foreach ($search_terms as $search_term) {
			$i++;
			if ($i>1) $where .= " AND";     // --- make this OR if you prefer not requiring all search terms to match taxonomies
			$where .= " (t.name LIKE '%".$search_term."%')";
		}
		$where .= " AND {$wpdb->posts}.post_status = 'inherit')";  //// if this status is "inherit" then media attachments get included!
	}
  return $where;
}

function roots_search_join($join){
	if (is_admin()) return $join;
	global $wpdb;
	if (is_search())
	$join .= "LEFT JOIN {$wpdb->term_relationships} tr ON {$wpdb->posts}.ID = tr.object_id INNER JOIN {$wpdb->term_taxonomy} tt ON tt.term_taxonomy_id=tr.term_taxonomy_id INNER JOIN {$wpdb->terms} t ON t.term_id = tt.term_id";
	return $join;
}

function roots_search_groupby($groupby){
	if (is_admin()) return $groupby;
	global $wpdb;

	// we need to group on post ID
	$groupby_id = "{$wpdb->posts}.ID";
	if(!is_search() || strpos($groupby, $groupby_id) !== false) return $groupby;

	// groupby was empty, use ours
	if(!strlen(trim($groupby))) return $groupby_id;

	// wasn't empty, append ours
	return $groupby.", ".$groupby_id;
}

add_filter('posts_where','roots_search_where');
add_filter('posts_join', 'roots_search_join');
add_filter('posts_groupby', 'roots_search_groupby');
/************************/
?>
