<?php
/**
 * Convert Markdown to Google Docs API requests with proper formatting.
 *
 * Returns an array with:
 * - 'text': Plain text content to insert
 * - 'requests': Array of formatting requests to apply after insertion
 */
function markdown_to_docs_requests($markdown, $start_index = 1) {
    $requests = [];

    // Helper to get character length (Google Docs uses UTF-16 code units)
    $char_len = function($str) {
        return mb_strlen($str, 'UTF-8');
    };

    // First pass: parse markdown into structured blocks
    $lines = explode("\n", $markdown);
    $output_blocks = [];

    $in_code_block = false;
    $in_table = false;
    $table_rows = [];

    foreach ($lines as $line_num => $line) {
        // Handle code blocks
        if (preg_match('/^```/', $line)) {
            $in_code_block = !$in_code_block;
            continue;
        }

        if ($in_code_block) {
            $output_blocks[] = ['text' => $line, 'type' => 'code_block'];
            continue;
        }

        // Handle table rows
        if (preg_match('/^\|(.+)\|$/', $line)) {
            // Skip separator rows
            if (preg_match('/^\|[-\s:|]+\|$/', $line)) {
                continue;
            }

            $cells = array_map('trim', explode('|', trim($line, '|')));
            $row_data = [];
            foreach ($cells as $cell) {
                $processed = process_inline($cell);
                $row_data[] = $processed;
            }
            $table_rows[] = $row_data;
            $in_table = true;
            continue;
        }

        // End of table - flush collected rows
        if ($in_table && !empty($table_rows)) {
            $output_blocks[] = ['type' => 'table', 'rows' => $table_rows];
            $table_rows = [];
            $in_table = false;
        }

        // Handle headings
        if (preg_match('/^(#{1,3})\s+(.+)$/', $line, $m)) {
            $level = strlen($m[1]);
            $heading_type = $level === 1 ? 'HEADING_1' : ($level === 2 ? 'HEADING_2' : 'HEADING_3');
            $text = process_inline($m[2]);
            $output_blocks[] = ['text' => $text['text'], 'type' => 'heading', 'level' => $heading_type, 'inline' => $text['formats']];
            continue;
        }

        // Handle bullet lists
        if (preg_match('/^(\s*)[-*]\s+(.+)$/', $line, $m)) {
            $text = process_inline($m[2]);
            $output_blocks[] = ['text' => $text['text'], 'type' => 'bullet', 'inline' => $text['formats']];
            continue;
        }

        // Handle numbered lists
        if (preg_match('/^(\s*)\d+\.\s+(.+)$/', $line, $m)) {
            $text = process_inline($m[2]);
            $output_blocks[] = ['text' => $text['text'], 'type' => 'numbered', 'inline' => $text['formats']];
            continue;
        }

        // Regular paragraph
        $text = process_inline($line);
        $output_blocks[] = ['text' => $text['text'], 'type' => 'paragraph', 'inline' => $text['formats']];
    }

    // Flush any remaining table
    if (!empty($table_rows)) {
        $output_blocks[] = ['type' => 'table', 'rows' => $table_rows];
    }

    // Second pass: build final text and formatting requests
    $final_text = '';
    $current_index = $start_index;

    foreach ($output_blocks as $block) {
        // Handle tables specially - convert to tab-separated lines
        if ($block['type'] === 'table') {
            foreach ($block['rows'] as $row_idx => $row) {
                $cell_texts = [];
                $row_formats = [];
                $cell_offset = 0;

                foreach ($row as $cell) {
                    $cell_texts[] = $cell['text'];

                    // Adjust format positions
                    foreach ($cell['formats'] as $fmt) {
                        $row_formats[] = [
                            'type' => $fmt['type'],
                            'start' => $fmt['start'] + $cell_offset,
                            'end' => $fmt['end'] + $cell_offset,
                        ];
                    }
                    $cell_offset += $char_len($cell['text']) + 1; // +1 for tab
                }

                $line_text = implode("\t", $cell_texts);
                $line_start = $current_index;
                $line_end = $current_index + $char_len($line_text) + 1;

                $final_text .= $line_text . "\n";

                // Bold the header row
                if ($row_idx === 0) {
                    $requests[] = [
                        'updateTextStyle' => [
                            'range' => ['startIndex' => $line_start, 'endIndex' => $line_end - 1],
                            'textStyle' => ['bold' => true],
                            'fields' => 'bold',
                        ],
                    ];
                }

                // Apply inline formatting
                foreach ($row_formats as $fmt) {
                    $fmt_start = $line_start + $fmt['start'];
                    $fmt_end = $line_start + $fmt['end'];

                    if ($fmt['type'] === 'bold') {
                        $requests[] = [
                            'updateTextStyle' => [
                                'range' => ['startIndex' => $fmt_start, 'endIndex' => $fmt_end],
                                'textStyle' => ['bold' => true],
                                'fields' => 'bold',
                            ],
                        ];
                    } elseif ($fmt['type'] === 'code') {
                        $requests[] = [
                            'updateTextStyle' => [
                                'range' => ['startIndex' => $fmt_start, 'endIndex' => $fmt_end],
                                'textStyle' => [
                                    'weightedFontFamily' => ['fontFamily' => 'Roboto Mono'],
                                    'backgroundColor' => ['color' => ['rgbColor' => ['red' => 0.95, 'green' => 0.95, 'blue' => 0.95]]],
                                ],
                                'fields' => 'weightedFontFamily,backgroundColor',
                            ],
                        ];
                    }
                }

                $current_index = $line_end;
            }
            continue;
        }

        $line_text = $block['text'];
        $line_start = $current_index;
        $line_end = $current_index + $char_len($line_text) + 1; // +1 for newline

        // Add the text
        $final_text .= $line_text . "\n";

        // Apply line-level formatting
        switch ($block['type']) {
            case 'heading':
                $requests[] = [
                    'updateParagraphStyle' => [
                        'range' => ['startIndex' => $line_start, 'endIndex' => $line_end],
                        'paragraphStyle' => ['namedStyleType' => $block['level']],
                        'fields' => 'namedStyleType',
                    ],
                ];
                break;

            case 'bullet':
                $requests[] = [
                    'createParagraphBullets' => [
                        'range' => ['startIndex' => $line_start, 'endIndex' => $line_end],
                        'bulletPreset' => 'BULLET_DISC_CIRCLE_SQUARE',
                    ],
                ];
                break;

            case 'numbered':
                $requests[] = [
                    'createParagraphBullets' => [
                        'range' => ['startIndex' => $line_start, 'endIndex' => $line_end],
                        'bulletPreset' => 'NUMBERED_DECIMAL_NESTED',
                    ],
                ];
                break;

            case 'code_block':
                $requests[] = [
                    'updateTextStyle' => [
                        'range' => ['startIndex' => $line_start, 'endIndex' => $line_end - 1],
                        'textStyle' => [
                            'weightedFontFamily' => ['fontFamily' => 'Roboto Mono'],
                        ],
                        'fields' => 'weightedFontFamily',
                    ],
                ];
                break;
        }

        // Apply inline formatting (bold, italic, code)
        if (!empty($block['inline'])) {
            foreach ($block['inline'] as $fmt) {
                $fmt_start = $line_start + $fmt['start'];
                $fmt_end = $line_start + $fmt['end'];

                if ($fmt['type'] === 'bold') {
                    $requests[] = [
                        'updateTextStyle' => [
                            'range' => ['startIndex' => $fmt_start, 'endIndex' => $fmt_end],
                            'textStyle' => ['bold' => true],
                            'fields' => 'bold',
                        ],
                    ];
                } elseif ($fmt['type'] === 'italic') {
                    $requests[] = [
                        'updateTextStyle' => [
                            'range' => ['startIndex' => $fmt_start, 'endIndex' => $fmt_end],
                            'textStyle' => ['italic' => true],
                            'fields' => 'italic',
                        ],
                    ];
                } elseif ($fmt['type'] === 'code') {
                    $requests[] = [
                        'updateTextStyle' => [
                            'range' => ['startIndex' => $fmt_start, 'endIndex' => $fmt_end],
                            'textStyle' => [
                                'weightedFontFamily' => ['fontFamily' => 'Roboto Mono'],
                                'backgroundColor' => ['color' => ['rgbColor' => ['red' => 0.95, 'green' => 0.95, 'blue' => 0.95]]],
                            ],
                            'fields' => 'weightedFontFamily,backgroundColor',
                        ],
                    ];
                }
            }
        }

        $current_index = $line_end;
    }

    // Remove trailing newline from text (Google Docs adds one)
    $final_text = rtrim($final_text, "\n");

    return [
        'text' => $final_text,
        'requests' => $requests,
    ];
}

/**
 * Process inline markdown formatting (bold, italic, code).
 * Returns ['text' => clean text, 'formats' => array of formatting with character-based positions]
 */
function process_inline($text) {
    $formats = [];
    $clean = '';
    $output_char_pos = 0; // Character position (not byte position)

    // Convert to array of characters for proper UTF-8 handling
    $chars = preg_split('//u', $text, -1, PREG_SPLIT_NO_EMPTY);
    $len = count($chars);
    $pos = 0;

    while ($pos < $len) {
        // Bold: **text**
        if ($pos + 1 < $len && $chars[$pos] === '*' && $chars[$pos + 1] === '*') {
            // Find closing **
            $end = null;
            for ($i = $pos + 2; $i < $len - 1; $i++) {
                if ($chars[$i] === '*' && $chars[$i + 1] === '*') {
                    $end = $i;
                    break;
                }
            }
            if ($end !== null) {
                $content = implode('', array_slice($chars, $pos + 2, $end - $pos - 2));
                $content_len = $end - $pos - 2;
                $formats[] = [
                    'type' => 'bold',
                    'start' => $output_char_pos,
                    'end' => $output_char_pos + $content_len,
                ];
                $clean .= $content;
                $output_char_pos += $content_len;
                $pos = $end + 2;
                continue;
            }
        }

        // Inline code: `text`
        if ($chars[$pos] === '`') {
            $end = null;
            for ($i = $pos + 1; $i < $len; $i++) {
                if ($chars[$i] === '`') {
                    $end = $i;
                    break;
                }
            }
            if ($end !== null) {
                $content = implode('', array_slice($chars, $pos + 1, $end - $pos - 1));
                $content_len = $end - $pos - 1;
                $formats[] = [
                    'type' => 'code',
                    'start' => $output_char_pos,
                    'end' => $output_char_pos + $content_len,
                ];
                $clean .= $content;
                $output_char_pos += $content_len;
                $pos = $end + 1;
                continue;
            }
        }

        // Single italic: *text* (not part of bold)
        if ($chars[$pos] === '*') {
            $is_bold_start = ($pos + 1 < $len && $chars[$pos + 1] === '*');
            $is_bold_end = ($pos > 0 && $chars[$pos - 1] === '*');

            if (!$is_bold_start && !$is_bold_end) {
                $end = null;
                for ($i = $pos + 1; $i < $len; $i++) {
                    if ($chars[$i] === '*' && ($i + 1 >= $len || $chars[$i + 1] !== '*') && ($i === 0 || $chars[$i - 1] !== '*')) {
                        $end = $i;
                        break;
                    }
                }
                if ($end !== null) {
                    $content = implode('', array_slice($chars, $pos + 1, $end - $pos - 1));
                    $content_len = $end - $pos - 1;
                    $formats[] = [
                        'type' => 'italic',
                        'start' => $output_char_pos,
                        'end' => $output_char_pos + $content_len,
                    ];
                    $clean .= $content;
                    $output_char_pos += $content_len;
                    $pos = $end + 1;
                    continue;
                }
            }
        }

        // Regular character
        $clean .= $chars[$pos];
        $output_char_pos++;
        $pos++;
    }

    return [
        'text' => $clean,
        'formats' => $formats,
    ];
}
