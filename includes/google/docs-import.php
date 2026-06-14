<?php
/**
 * Google Docs Import
 *
 * Import Google Docs content as WordPress posts.
 */

use Google\Service\Docs;
use Google\Service\Drive;

require_once __DIR__ . '/sheets-export.php';

/**
 * Extract document ID from a Google Docs URL.
 *
 * @param string $url_or_id URL or document ID
 * @return string|null Document ID or null if invalid
 */
function extract_google_doc_id($url_or_id) {
	// If it's already just an ID (no slashes)
	if (!str_contains($url_or_id, '/')) {
		return $url_or_id;
	}

	// Extract from URL: https://docs.google.com/document/d/DOC_ID/...
	if (preg_match('#/document/d/([a-zA-Z0-9_-]+)#', $url_or_id, $matches)) {
		return $matches[1];
	}

	return null;
}

/**
 * Get a Google Doc by ID.
 *
 * @param string $doc_id Document ID
 * @return array|WP_Error Document data or error
 */
function get_google_doc($doc_id) {
	$client = get_google_client();

	if (is_wp_error($client)) {
		return $client;
	}

	$docs_service = new Docs($client);

	try {
		$document = $docs_service->documents->get($doc_id);
		return $document;
	} catch (Exception $e) {
		return new WP_Error('google_api_error', $e->getMessage());
	}
}

/**
 * Convert Google Docs content to HTML.
 *
 * @param Google\Service\Docs\Document $document The document object
 * @return string HTML content
 */
function google_doc_to_html($document) {
	$html = '';
	$body = $document->getBody();
	$content = $body->getContent();
	$inline_objects = $document->getInlineObjects() ?? [];
	$lists = $document->getLists() ?? [];

	// Track list and blockquote state across paragraphs.
	// $list_stack: stack of currently open lists, each ['type' => 'ul'|'ol', 'level' => int]
	$list_stack = [];
	$in_blockquote = false;

	foreach ($content as $element) {
		// Handle paragraphs
		if ($paragraph = $element->getParagraph()) {
			$para_html = process_paragraph($paragraph, $inline_objects, $lists, $list_stack, $in_blockquote);

			if ($para_html !== null) {
				$html .= $para_html;
			}
		}

		// Handle tables
		if ($table = $element->getTable()) {
			$html .= close_all_lists($list_stack);
			if ($in_blockquote) {
				$html .= '</blockquote>';
				$in_blockquote = false;
			}
			$html .= process_table($table, $inline_objects);
		}
	}

	$html .= close_all_lists($list_stack);
	if ($in_blockquote) {
		$html .= '</blockquote>';
	}

	return $html;
}

/**
 * Pop and close every open list on the stack, returning the closing HTML.
 */
function close_all_lists(array &$list_stack): string {
	$html = '';
	while (count($list_stack) > 0) {
		$top = array_pop($list_stack);
		$html .= '</li></' . $top['type'] . '>';
	}
	return $html;
}

/**
 * Process a paragraph element.
 *
 * @param Google\Service\Docs\Paragraph $paragraph
 * @param array $inline_objects
 * @param array $lists
 * @param array &$list_stack Stack of open lists [['type' => ul|ol, 'level' => int], …]
 * @param bool &$in_blockquote
 * @return string|null HTML or null to skip
 */
function process_paragraph($paragraph, $inline_objects, $lists, array &$list_stack, &$in_blockquote = false) {
	$elements = $paragraph->getElements() ?? [];
	$style = $paragraph->getParagraphStyle();
	$bullet = $paragraph->getBullet();

	// Indented (left margin > 0) paragraphs are treated as block quotes.
	$indent = $style ? $style->getIndentStart() : null;
	$is_indented = $indent && $indent->getMagnitude() > 0;

	// Get paragraph text content
	$text_content = '';
	$has_content = false;

	foreach ($elements as $element) {
		if ($text_run = $element->getTextRun()) {
			$content = $text_run->getContent();
			// Skip if just newline
			if ($content !== "\n") {
				$has_content = true;
			}
			$text_content .= process_text_run($text_run);
		}

		if ($inline_obj_element = $element->getInlineObjectElement()) {
			$obj_id = $inline_obj_element->getInlineObjectId();
			if (isset($inline_objects[$obj_id])) {
				$text_content .= process_inline_object($inline_objects[$obj_id]);
				$has_content = true;
			}
		}
	}

	// Skip empty paragraphs
	if (!$has_content || trim(strip_tags($text_content)) === '') {
		$result = '';
		if (!$bullet && count($list_stack) > 0) {
			$result .= close_all_lists($list_stack);
		}
		if ($in_blockquote && !$is_indented) {
			$result .= '</blockquote>';
			$in_blockquote = false;
		}
		return $result !== '' ? $result : null;
	}

	// Handle lists
	if ($bullet) {
		// A list item always breaks out of a blockquote.
		$prefix = '';
		if ($in_blockquote) {
			$prefix = '</blockquote>';
			$in_blockquote = false;
		}

		$list_id = $bullet->getListId();
		$new_level = $bullet->getNestingLevel() ?? 0;

		// Determine list type from list definition at this nesting level
		$new_type = 'ul';
		if (isset($lists[$list_id])) {
			$list_props = $lists[$list_id]->getListProperties();
			if ($list_props) {
				$nesting_levels = $list_props->getNestingLevels();
				if ($nesting_levels && isset($nesting_levels[$new_level])) {
					$glyph_type = $nesting_levels[$new_level]->getGlyphType();
					if ($glyph_type && str_contains(strtoupper($glyph_type), 'DECIMAL')) {
						$new_type = 'ol';
					}
				}
			}
		}

		// Pop lists deeper than the new level.
		while (count($list_stack) > 0 && end($list_stack)['level'] > $new_level) {
			$top = array_pop($list_stack);
			$prefix .= '</li></' . $top['type'] . '>';
		}

		if (count($list_stack) === 0) {
			$list_stack[] = ['type' => $new_type, 'level' => $new_level];
			$prefix .= '<' . $new_type . '><li>';
		} else {
			$top = end($list_stack);
			if ($top['level'] === $new_level) {
				if ($top['type'] !== $new_type) {
					// Same level, different list type — close current, open new.
					array_pop($list_stack);
					$prefix .= '</li></' . $top['type'] . '>';
					$list_stack[] = ['type' => $new_type, 'level' => $new_level];
					$prefix .= '<' . $new_type . '><li>';
				} else {
					$prefix .= '</li><li>';
				}
			} else {
				// Going deeper — open a new list nested inside the current <li>.
				$list_stack[] = ['type' => $new_type, 'level' => $new_level];
				$prefix .= '<' . $new_type . '><li>';
			}
		}

		return $prefix . $text_content;
	}

	// Close all lists when leaving list mode
	$prefix = close_all_lists($list_stack);

	$named_style = $style ? $style->getNamedStyleType() : null;
	$is_heading = $named_style && str_starts_with($named_style, 'HEADING_');

	// Headings always break out of a blockquote.
	if ($is_heading && $in_blockquote) {
		$prefix .= '</blockquote>';
		$in_blockquote = false;
	}

	// Normal paragraph: open/close blockquote on indent transitions.
	if (!$is_heading) {
		if ($is_indented && !$in_blockquote) {
			$prefix .= '<blockquote>';
			$in_blockquote = true;
		} elseif (!$is_indented && $in_blockquote) {
			$prefix .= '</blockquote>';
			$in_blockquote = false;
		}
	}

	switch ($named_style) {
		case 'HEADING_1':
			return $prefix . "<h1>{$text_content}</h1>";
		case 'HEADING_2':
			return $prefix . "<h2>{$text_content}</h2>";
		case 'HEADING_3':
			return $prefix . "<h3>{$text_content}</h3>";
		case 'HEADING_4':
			return $prefix . "<h4>{$text_content}</h4>";
		case 'HEADING_5':
			return $prefix . "<h5>{$text_content}</h5>";
		case 'HEADING_6':
			return $prefix . "<h6>{$text_content}</h6>";
		default:
			return $prefix . "<p>{$text_content}</p>";
	}
}

/**
 * Process a text run with formatting.
 *
 * @param Google\Service\Docs\TextRun $text_run
 * @return string HTML
 */
function process_text_run($text_run) {
	$content = $text_run->getContent();
	$style = $text_run->getTextStyle();

	// Escape HTML
	$content = esc_html($content);

	if (!$style) {
		return $content;
	}

	// Apply formatting
	if ($style->getBold()) {
		$content = "<strong>{$content}</strong>";
	}

	if ($style->getItalic()) {
		$content = "<em>{$content}</em>";
	}

	if ($style->getUnderline()) {
		$content = "<u>{$content}</u>";
	}

	if ($style->getStrikethrough()) {
		$content = "<del>{$content}</del>";
	}

	// Handle links
	if ($link = $style->getLink()) {
		$url = $link->getUrl();
		if ($url) {
			$content = "<a href=\"" . esc_url($url) . "\">{$content}</a>";
		}
	}

	return $content;
}

/**
 * Process an inline object (image).
 *
 * @param Google\Service\Docs\InlineObject $inline_object
 * @return string HTML
 */
function process_inline_object($inline_object) {
	$props = $inline_object->getInlineObjectProperties();
	if (!$props) {
		return '';
	}

	$embedded = $props->getEmbeddedObject();
	if (!$embedded) {
		return '';
	}

	$image_props = $embedded->getImageProperties();
	if (!$image_props) {
		return '';
	}

	$content_uri = $image_props->getContentUri();
	if (!$content_uri) {
		return '';
	}

	// Store placeholder - actual import happens later
	return "<!-- gdoc-image:{$content_uri} -->";
}

/**
 * Process a table element.
 *
 * @param Google\Service\Docs\Table $table
 * @param array $inline_objects
 * @return string HTML
 */
function process_table($table, $inline_objects) {
	$html = '<table>';
	$rows = $table->getTableRows() ?? [];

	foreach ($rows as $row_index => $row) {
		$html .= '<tr>';
		$cells = $row->getTableCells() ?? [];

		foreach ($cells as $cell) {
			$tag = $row_index === 0 ? 'th' : 'td';
			$cell_content = '';

			$cell_elements = $cell->getContent() ?? [];
			foreach ($cell_elements as $element) {
				if ($paragraph = $element->getParagraph()) {
					foreach ($paragraph->getElements() ?? [] as $para_element) {
						if ($text_run = $para_element->getTextRun()) {
							$cell_content .= process_text_run($text_run);
						}
					}
				}
			}

			// Trim trailing newlines from cell content
			$cell_content = rtrim($cell_content);
			$html .= "<{$tag}>{$cell_content}</{$tag}>";
		}

		$html .= '</tr>';
	}

	$html .= '</table>';
	return $html;
}

/**
 * Import images from Google Docs to WordPress media library.
 *
 * @param string $html HTML content with image placeholders
 * @param int $post_id Post ID to attach images to
 * @return string HTML with WordPress image URLs
 */
function import_google_doc_images($html, $post_id) {
	// Find all image placeholders
	preg_match_all('/<!-- gdoc-image:(.*?) -->/', $html, $matches);

	if (empty($matches[1])) {
		return $html;
	}

	require_once ABSPATH . 'wp-admin/includes/media.php';
	require_once ABSPATH . 'wp-admin/includes/file.php';
	require_once ABSPATH . 'wp-admin/includes/image.php';

	foreach ($matches[1] as $index => $image_url) {
		$placeholder = $matches[0][$index];

		// Download and import image
		$attachment_id = media_sideload_image($image_url, $post_id, '', 'id');

		if (is_wp_error($attachment_id)) {
			// Remove placeholder on error
			$html = str_replace($placeholder, '', $html);
			continue;
		}

		// Get attachment URL
		$attachment_url = wp_get_attachment_url($attachment_id);

		// Replace placeholder with img tag
		$img_tag = '<img src="' . esc_url($attachment_url) . '" alt="" class="wp-image-' . $attachment_id . '" />';
		$html = str_replace($placeholder, $img_tag, $html);
	}

	return $html;
}

/**
 * Wrap converted HTML in Gutenberg block delimiters.
 *
 * Without these comment markers Gutenberg treats the full post_content as
 * a single Classic block, which renders in Times New Roman and shows a
 * "Konverter til blokker" prompt. Wrapping each block-level element makes
 * the editor see them as native blocks.
 *
 * Operates on the opening/closing tags independently so nested lists are
 * marked at every level without needing a real parser.
 */
function convert_html_to_blocks(string $html): string {
	$replacements = [
		'#<h1>(.*?)</h1>#is'        => '<!-- wp:heading {"level":1} --><h1 class="wp-block-heading">$1</h1><!-- /wp:heading -->',
		'#<h2>(.*?)</h2>#is'        => '<!-- wp:heading --><h2 class="wp-block-heading">$1</h2><!-- /wp:heading -->',
		'#<h3>(.*?)</h3>#is'        => '<!-- wp:heading {"level":3} --><h3 class="wp-block-heading">$1</h3><!-- /wp:heading -->',
		'#<h4>(.*?)</h4>#is'        => '<!-- wp:heading {"level":4} --><h4 class="wp-block-heading">$1</h4><!-- /wp:heading -->',
		'#<h5>(.*?)</h5>#is'        => '<!-- wp:heading {"level":5} --><h5 class="wp-block-heading">$1</h5><!-- /wp:heading -->',
		'#<h6>(.*?)</h6>#is'        => '<!-- wp:heading {"level":6} --><h6 class="wp-block-heading">$1</h6><!-- /wp:heading -->',
		'#<p>(.*?)</p>#is'          => '<!-- wp:paragraph --><p>$1</p><!-- /wp:paragraph -->',
		'#<blockquote>#i'           => '<!-- wp:quote --><blockquote class="wp-block-quote">',
		'#</blockquote>#i'          => '</blockquote><!-- /wp:quote -->',
		'#<ul>#i'                   => '<!-- wp:list --><ul class="wp-block-list">',
		'#</ul>#i'                  => '</ul><!-- /wp:list -->',
		'#<ol>#i'                   => '<!-- wp:list {"ordered":true} --><ol class="wp-block-list">',
		'#</ol>#i'                  => '</ol><!-- /wp:list -->',
		'#<li>#i'                   => '<!-- wp:list-item --><li>',
		'#</li>#i'                  => '</li><!-- /wp:list-item -->',
		'#<table>#i'                => '<!-- wp:table --><figure class="wp-block-table"><table>',
		'#</table>#i'               => '</table></figure><!-- /wp:table -->',
	];

	return preg_replace(array_keys($replacements), array_values($replacements), $html);
}

/**
 * Import a Google Doc as a WordPress post.
 *
 * @param string $doc_id_or_url Document ID or URL
 * @param array $options Import options
 * @return array|WP_Error Result with post_id and edit_url, or error
 */
function import_google_doc_to_post($doc_id_or_url, $options = []) {
	// Extract document ID
	$doc_id = extract_google_doc_id($doc_id_or_url);

	if (!$doc_id) {
		return new WP_Error('invalid_url', 'Ugyldig Google Docs URL eller ID');
	}

	// Get the document
	$document = get_google_doc($doc_id);

	if (is_wp_error($document)) {
		return $document;
	}

	// Get title
	$title = $document->getTitle();

	// Convert content to HTML
	$html_content = google_doc_to_html($document);

	// Create post as draft
	$post_data = [
		'post_title'   => sanitize_text_field($title),
		'post_content' => $html_content,
		'post_status'  => 'draft',
		'post_type'    => $options['post_type'] ?? 'post',
		'post_author'  => get_current_user_id(),
		'meta_input'   => [
			'_google_doc_id'       => $doc_id,
			'_google_doc_imported' => current_time('mysql'),
		],
	];

	// Set category if provided
	if (!empty($options['category_id'])) {
		$post_data['post_category'] = [(int) $options['category_id']];
	}

	$post_id = wp_insert_post($post_data, true);

	if (is_wp_error($post_id)) {
		return $post_id;
	}

	// Import images and wrap the HTML in Gutenberg block markers so the
	// editor recognizes each element as a block instead of dumping
	// everything into a single Classic block.
	$updated_content = import_google_doc_images($html_content, $post_id);
	$updated_content = convert_html_to_blocks($updated_content);

	wp_update_post([
		'ID'           => $post_id,
		'post_content' => $updated_content,
	]);

	// Set visibility to private
	update_post_meta($post_id, '_visibility', 'private');

	// Get edit URL
	$edit_url = get_edit_post_link($post_id, 'raw');

	return [
		'post_id'  => $post_id,
		'title'    => $title,
		'edit_url' => $edit_url,
	];
}

/**
 * List recent Google Docs from Shared Drive.
 *
 * @param int $limit Number of docs to return
 * @return array|WP_Error Array of docs or error
 */
function list_google_docs($limit = 20) {
	$client = get_google_client();

	if (is_wp_error($client)) {
		return $client;
	}

	$shared_drive_id = $_ENV['GOOGLE_SHARED_DRIVE_ID'] ?? getenv('GOOGLE_SHARED_DRIVE_ID') ?: '';

	if (empty($shared_drive_id)) {
		return new WP_Error('missing_drive_id', 'Google Shared Drive ID not configured');
	}

	$drive_service = new Drive($client);

	try {
		$results = $drive_service->files->listFiles([
			'q'                         => "mimeType = 'application/vnd.google-apps.document'",
			'driveId'                   => $shared_drive_id,
			'corpora'                   => 'drive',
			'includeItemsFromAllDrives' => true,
			'supportsAllDrives'         => true,
			'orderBy'                   => 'modifiedTime desc',
			'pageSize'                  => $limit,
			'fields'                    => 'files(id, name, modifiedTime, webViewLink)',
		]);

		$docs = [];
		foreach ($results->getFiles() as $file) {
			$docs[] = [
				'id'           => $file->getId(),
				'name'         => $file->getName(),
				'modified'     => $file->getModifiedTime(),
				'url'          => $file->getWebViewLink(),
			];
		}

		return $docs;

	} catch (Exception $e) {
		return new WP_Error('google_api_error', $e->getMessage());
	}
}
