<?php
/* used to import all the story text from the indesign book export */
function digestHTML() {
	$doc = new DOMDocument();
	$path = get_stylesheet_directory(). '/stories.html' ;
	$doc->loadHTMLfile($path);
	$items = $doc->getElementsByTagName('ul');
	if (!empty($items)) {
		echo "<ul>";
		// parse each story
	    foreach ($items as $item) {
	    	// init output vars
	    	$content = "";
			$title = "";
			$writer = "";
			$subtitle = "";
			// parse li elements by class
			$lines = $item->getElementsByTagName( "li" );
			// populate vars with story elements
			foreach ($lines as $line) {
				$class = $line->getAttribute('class');
				switch ($class) {
				case 'title':
					$title = $line->nodeValue; // won't include html tags, only plaintext
					break;
				case 'writer':
					$writer = $line->nodeValue;
					break;
				case 'subtitle':
					$subtitle = innerHTML($line); // need ext function to preserve HTML but not including the node tag itself
					break;
				case 'content':
					$content = innerHTML($line);
					break;
				default:
					break;
				}
			}
			    // create new story in db
	    if (empty($title)) { wp_die('no title!'); }
	    if (!empty($writer)) {
	    	$writerID = checkforwriter($writer);
	    } else {
	    	$writerID = 0;
	    }
	    $new_story = array(
			'post_title'    => $title,
			'post_content'  => $content,
			'post_status'   => 'publish',
			'post_type'     => 'stories',
			'meta_input'    => array( 'writer' => $writer, 'subtitle' => $subtitle ),
			'tax_input'     => array( 'people' => array($writerID) )
		);
		$newstory_id = wp_insert_post( $new_story );
			if (is_wp_error($newstory_id)) {
				var_dump($newstory_id);
			}
	    }
	}
}

// lookup writer or make if doesn't exist, returns term_ID
function checkforwriter($writer) {
	$writerperson = get_term_by('name', $writer, 'people');
	if ( is_object( $writerperson ) ) {
		return intval($writerperson->term_id);
	} else {
		// create new person
		$newperson = wp_insert_term( $writer, 'people');
		if (!is_wp_error($newperson)) {
			return intval($newperson['term_id']);
		}
	}
}

function innerHTML($node) {
    return implode(array_map([$node->ownerDocument,"saveHTML"],
                             iterator_to_array($node->childNodes)));
}

// digestHTML();