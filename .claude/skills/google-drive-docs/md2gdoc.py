#!/usr/bin/env python3
# /// script
# requires-python = ">=3.10"
# dependencies = [
#     "google-api-python-client",
#     "google-auth",
# ]
# ///
"""
Markdown to Google Docs converter with table support.

Usage:
    uv run md2gdoc.py --title "Doc Title" --file input.md [--folder "Folder Name"]

Requires:
    GOOGLE_APPLICATION_CREDENTIALS env var pointing to service account JSON
    GOOGLE_SHARED_DRIVE_ID env var with the Shared Drive ID
"""

import argparse
import os
import re
import sys
from google.oauth2 import service_account
from googleapiclient.discovery import build

SCOPES = [
    'https://www.googleapis.com/auth/documents',
    'https://www.googleapis.com/auth/drive',
]


def get_credentials():
    """Get credentials from service account file."""
    creds_path = os.environ.get('GOOGLE_APPLICATION_CREDENTIALS')
    if not creds_path:
        raise ValueError("GOOGLE_APPLICATION_CREDENTIALS environment variable not set")
    if not os.path.exists(creds_path):
        raise FileNotFoundError(f"Credentials file not found: {creds_path}")

    return service_account.Credentials.from_service_account_file(creds_path, scopes=SCOPES)


def create_document(drive_service, title, parent_id):
    """Create empty Google Doc in Shared Drive."""
    file_metadata = {
        'name': title,
        'mimeType': 'application/vnd.google-apps.document',
        'parents': [parent_id],
    }
    file = drive_service.files().create(
        body=file_metadata,
        supportsAllDrives=True
    ).execute()
    return file['id']


def find_folder(drive_service, folder_name, drive_id):
    """Find folder by name in Shared Drive."""
    query = f"name = '{folder_name}' and mimeType = 'application/vnd.google-apps.folder'"
    results = drive_service.files().list(
        q=query,
        driveId=drive_id,
        corpora='drive',
        includeItemsFromAllDrives=True,
        supportsAllDrives=True,
        fields='files(id, name)'
    ).execute()
    files = results.get('files', [])
    return files[0]['id'] if files else None


def parse_markdown(content):
    """Parse markdown into structured blocks."""
    lines = content.split('\n')
    blocks = []
    in_code_block = False
    in_table = False
    table_rows = []

    for line in lines:
        # Code blocks
        if line.startswith('```'):
            in_code_block = not in_code_block
            continue

        if in_code_block:
            blocks.append({'type': 'code', 'text': line})
            continue

        # Tables
        if re.match(r'^\|.+\|$', line):
            # Skip separator rows
            if re.match(r'^\|[-\s:|]+\|$', line):
                continue
            cells = [c.strip() for c in line.strip('|').split('|')]
            table_rows.append(cells)
            in_table = True
            continue

        # End of table
        if in_table and table_rows:
            blocks.append({'type': 'table', 'rows': table_rows})
            table_rows = []
            in_table = False

        # Headings
        if m := re.match(r'^(#{1,3})\s+(.+)$', line):
            level = len(m.group(1))
            blocks.append({'type': 'heading', 'level': level, 'text': m.group(2)})
            continue

        # Bullet lists
        if m := re.match(r'^[-*]\s+(.+)$', line):
            blocks.append({'type': 'bullet', 'text': m.group(1)})
            continue

        # Numbered lists
        if m := re.match(r'^\d+\.\s+(.+)$', line):
            blocks.append({'type': 'numbered', 'text': m.group(1)})
            continue

        # Regular paragraph
        blocks.append({'type': 'paragraph', 'text': line})

    # Flush remaining table
    if table_rows:
        blocks.append({'type': 'table', 'rows': table_rows})

    return blocks


def process_inline_formatting(text):
    """
    Process bold (**text**) and italic (*text*) markers.
    Returns (clean_text, [(start, end, style), ...])
    """
    formats = []
    result = ''
    i = 0

    while i < len(text):
        # Bold: **text**
        if text[i:i+2] == '**':
            end = text.find('**', i + 2)
            if end != -1:
                start_pos = len(result)
                content = text[i+2:end]
                result += content
                formats.append((start_pos, start_pos + len(content), 'bold'))
                i = end + 2
                continue

        # Inline code: `text`
        if text[i] == '`':
            end = text.find('`', i + 1)
            if end != -1:
                start_pos = len(result)
                content = text[i+1:end]
                result += content
                formats.append((start_pos, start_pos + len(content), 'code'))
                i = end + 1
                continue

        # Italic: *text* (not **)
        if text[i] == '*' and (i + 1 >= len(text) or text[i+1] != '*'):
            end = text.find('*', i + 1)
            if end != -1 and (end + 1 >= len(text) or text[end+1] != '*'):
                start_pos = len(result)
                content = text[i+1:end]
                result += content
                formats.append((start_pos, start_pos + len(content), 'italic'))
                i = end + 1
                continue

        result += text[i]
        i += 1

    return result, formats


def build_requests_without_tables(blocks):
    """Build Docs API requests for non-table content."""
    requests = []
    text_content = ''
    format_requests = []
    current_index = 1

    for block in blocks:
        if block['type'] == 'table':
            # Tables handled separately
            continue

        text = block.get('text', '')
        clean_text, formats = process_inline_formatting(text)

        line_start = current_index
        text_content += clean_text + '\n'
        line_end = current_index + len(clean_text) + 1

        # Heading styles
        if block['type'] == 'heading':
            level = block['level']
            style = f'HEADING_{level}'
            format_requests.append({
                'updateParagraphStyle': {
                    'range': {'startIndex': line_start, 'endIndex': line_end},
                    'paragraphStyle': {'namedStyleType': style},
                    'fields': 'namedStyleType'
                }
            })

        # Bullets
        elif block['type'] == 'bullet':
            format_requests.append({
                'createParagraphBullets': {
                    'range': {'startIndex': line_start, 'endIndex': line_end},
                    'bulletPreset': 'BULLET_DISC_CIRCLE_SQUARE'
                }
            })

        # Numbered lists
        elif block['type'] == 'numbered':
            format_requests.append({
                'createParagraphBullets': {
                    'range': {'startIndex': line_start, 'endIndex': line_end},
                    'bulletPreset': 'NUMBERED_DECIMAL_NESTED'
                }
            })

        # Code blocks
        elif block['type'] == 'code':
            format_requests.append({
                'updateTextStyle': {
                    'range': {'startIndex': line_start, 'endIndex': line_end - 1},
                    'textStyle': {'weightedFontFamily': {'fontFamily': 'Roboto Mono'}},
                    'fields': 'weightedFontFamily'
                }
            })

        # Inline formatting
        for start, end, style in formats:
            fmt_start = line_start + start
            fmt_end = line_start + end
            if style == 'bold':
                format_requests.append({
                    'updateTextStyle': {
                        'range': {'startIndex': fmt_start, 'endIndex': fmt_end},
                        'textStyle': {'bold': True},
                        'fields': 'bold'
                    }
                })
            elif style == 'italic':
                format_requests.append({
                    'updateTextStyle': {
                        'range': {'startIndex': fmt_start, 'endIndex': fmt_end},
                        'textStyle': {'italic': True},
                        'fields': 'italic'
                    }
                })
            elif style == 'code':
                format_requests.append({
                    'updateTextStyle': {
                        'range': {'startIndex': fmt_start, 'endIndex': fmt_end},
                        'textStyle': {
                            'weightedFontFamily': {'fontFamily': 'Roboto Mono'},
                            'backgroundColor': {'color': {'rgbColor': {'red': 0.95, 'green': 0.95, 'blue': 0.95}}}
                        },
                        'fields': 'weightedFontFamily,backgroundColor'
                    }
                })

        current_index = line_end

    # Insert text first
    if text_content:
        requests.append({
            'insertText': {
                'location': {'index': 1},
                'text': text_content.rstrip('\n')
            }
        })

    # Then formatting
    requests.extend(format_requests)

    return requests


def insert_table(docs_service, doc_id, rows, cols, index):
    """Insert a table and return the starting index of cells."""
    # Insert empty table
    requests = [{
        'insertTable': {
            'rows': rows,
            'columns': cols,
            'location': {'index': index}
        }
    }]

    docs_service.documents().batchUpdate(
        documentId=doc_id,
        body={'requests': requests}
    ).execute()

    # Get document to find cell indices
    doc = docs_service.documents().get(documentId=doc_id).execute()

    # Find table structure
    body = doc.get('body', {})
    content = body.get('content', [])

    cell_indices = []
    for element in content:
        if 'table' in element:
            table = element['table']
            for row in table.get('tableRows', []):
                row_indices = []
                for cell in row.get('tableCells', []):
                    # Each cell contains content, get starting index
                    cell_content = cell.get('content', [])
                    if cell_content:
                        start_index = cell_content[0].get('startIndex', 0)
                        row_indices.append(start_index)
                if row_indices:
                    cell_indices.append(row_indices)
            break  # Only process first table found after index

    return cell_indices


def populate_table(docs_service, doc_id, cell_indices, table_data):
    """Fill table cells with content. Insert in reverse order to avoid index shifts."""
    requests = []

    # Build list of (index, text, is_header) tuples
    cells_to_fill = []
    for row_idx, row_data in enumerate(table_data):
        if row_idx >= len(cell_indices):
            break
        for col_idx, cell_text in enumerate(row_data):
            if col_idx >= len(cell_indices[row_idx]):
                break

            cell_index = cell_indices[row_idx][col_idx]
            clean_text, _ = process_inline_formatting(cell_text)

            if clean_text:
                is_header = (row_idx == 0)
                cells_to_fill.append((cell_index, clean_text, is_header))

    # Sort by index descending - insert from end to avoid shifting
    cells_to_fill.sort(key=lambda x: x[0], reverse=True)

    for cell_index, clean_text, _ in cells_to_fill:
        requests.append({
            'insertText': {
                'location': {'index': cell_index},
                'text': clean_text
            }
        })

    if requests:
        docs_service.documents().batchUpdate(
            documentId=doc_id,
            body={'requests': requests}
        ).execute()

    # Bold header cells - need to re-fetch document to get updated indices
    if cell_indices and table_data:
        doc = docs_service.documents().get(documentId=doc_id).execute()
        body = doc.get('body', {})
        content = body.get('content', [])

        # Find the table and get first row cell ranges
        bold_requests = []
        for element in content:
            if 'table' in element:
                table = element['table']
                table_rows = table.get('tableRows', [])
                if table_rows:
                    first_row = table_rows[0]
                    for cell in first_row.get('tableCells', []):
                        cell_content = cell.get('content', [])
                        if cell_content:
                            para = cell_content[0]
                            if 'paragraph' in para:
                                start = para.get('startIndex', 0)
                                end = para.get('endIndex', start)
                                if end > start:
                                    bold_requests.append({
                                        'updateTextStyle': {
                                            'range': {'startIndex': start, 'endIndex': end - 1},
                                            'textStyle': {'bold': True},
                                            'fields': 'bold'
                                        }
                                    })
                break  # Only process first table

        if bold_requests:
            try:
                docs_service.documents().batchUpdate(
                    documentId=doc_id,
                    body={'requests': bold_requests}
                ).execute()
            except Exception:
                pass  # Ignore formatting errors


def get_doc_end_index(docs_service, doc_id):
    """Get the end index of the document."""
    doc = docs_service.documents().get(documentId=doc_id).execute()
    body = doc.get('body', {})
    content = body.get('content', [])
    return content[-1].get('endIndex', 1) - 1 if content else 1


def insert_text_block(docs_service, doc_id, text, index):
    """Insert text at specified index and return new end index."""
    if not text:
        return index

    docs_service.documents().batchUpdate(
        documentId=doc_id,
        body={'requests': [{'insertText': {'location': {'index': index}, 'text': text}}]}
    ).execute()

    return get_doc_end_index(docs_service, doc_id)


def apply_block_formatting(docs_service, doc_id, block, line_start, line_end):
    """Apply formatting to a single block."""
    requests = []

    clean_text, formats = process_inline_formatting(block.get('text', ''))

    # Block-level formatting
    if block['type'] == 'heading':
        level = block['level']
        style = f'HEADING_{level}'
        requests.append({
            'updateParagraphStyle': {
                'range': {'startIndex': line_start, 'endIndex': line_end},
                'paragraphStyle': {'namedStyleType': style},
                'fields': 'namedStyleType'
            }
        })
    elif block['type'] == 'bullet':
        requests.append({
            'createParagraphBullets': {
                'range': {'startIndex': line_start, 'endIndex': line_end},
                'bulletPreset': 'BULLET_DISC_CIRCLE_SQUARE'
            }
        })
    elif block['type'] == 'numbered':
        requests.append({
            'createParagraphBullets': {
                'range': {'startIndex': line_start, 'endIndex': line_end},
                'bulletPreset': 'NUMBERED_DECIMAL_NESTED'
            }
        })
    elif block['type'] == 'code':
        requests.append({
            'updateTextStyle': {
                'range': {'startIndex': line_start, 'endIndex': line_end - 1},
                'textStyle': {'weightedFontFamily': {'fontFamily': 'Roboto Mono'}},
                'fields': 'weightedFontFamily'
            }
        })

    # Inline formatting
    for start, end, style in formats:
        fmt_start = line_start + start
        fmt_end = line_start + end
        if style == 'bold':
            requests.append({
                'updateTextStyle': {
                    'range': {'startIndex': fmt_start, 'endIndex': fmt_end},
                    'textStyle': {'bold': True},
                    'fields': 'bold'
                }
            })
        elif style == 'italic':
            requests.append({
                'updateTextStyle': {
                    'range': {'startIndex': fmt_start, 'endIndex': fmt_end},
                    'textStyle': {'italic': True},
                    'fields': 'italic'
                }
            })
        elif style == 'code':
            requests.append({
                'updateTextStyle': {
                    'range': {'startIndex': fmt_start, 'endIndex': fmt_end},
                    'textStyle': {
                        'weightedFontFamily': {'fontFamily': 'Roboto Mono'},
                        'backgroundColor': {'color': {'rgbColor': {'red': 0.95, 'green': 0.95, 'blue': 0.95}}}
                    },
                    'fields': 'weightedFontFamily,backgroundColor'
                }
            })

    if requests:
        docs_service.documents().batchUpdate(
            documentId=doc_id,
            body={'requests': requests}
        ).execute()


def build_text_and_formatting(blocks, start_index):
    """Build text content and formatting requests for a group of non-table blocks."""
    text_content = ''
    format_requests = []
    current_index = start_index

    for block in blocks:
        text = block.get('text', '')
        clean_text, formats = process_inline_formatting(text)

        line_start = current_index
        text_content += clean_text + '\n'
        line_end = current_index + len(clean_text) + 1

        # Block-level formatting
        if block['type'] == 'heading':
            level = block['level']
            style = f'HEADING_{level}'
            format_requests.append({
                'updateParagraphStyle': {
                    'range': {'startIndex': line_start, 'endIndex': line_end},
                    'paragraphStyle': {'namedStyleType': style},
                    'fields': 'namedStyleType'
                }
            })
        elif block['type'] == 'bullet':
            format_requests.append({
                'createParagraphBullets': {
                    'range': {'startIndex': line_start, 'endIndex': line_end},
                    'bulletPreset': 'BULLET_DISC_CIRCLE_SQUARE'
                }
            })
        elif block['type'] == 'numbered':
            format_requests.append({
                'createParagraphBullets': {
                    'range': {'startIndex': line_start, 'endIndex': line_end},
                    'bulletPreset': 'NUMBERED_DECIMAL_NESTED'
                }
            })
        elif block['type'] == 'code':
            format_requests.append({
                'updateTextStyle': {
                    'range': {'startIndex': line_start, 'endIndex': line_end - 1},
                    'textStyle': {'weightedFontFamily': {'fontFamily': 'Roboto Mono'}},
                    'fields': 'weightedFontFamily'
                }
            })

        # Inline formatting
        for start, end, style in formats:
            fmt_start = line_start + start
            fmt_end = line_start + end
            if style == 'bold':
                format_requests.append({
                    'updateTextStyle': {
                        'range': {'startIndex': fmt_start, 'endIndex': fmt_end},
                        'textStyle': {'bold': True},
                        'fields': 'bold'
                    }
                })
            elif style == 'italic':
                format_requests.append({
                    'updateTextStyle': {
                        'range': {'startIndex': fmt_start, 'endIndex': fmt_end},
                        'textStyle': {'italic': True},
                        'fields': 'italic'
                    }
                })
            elif style == 'code':
                format_requests.append({
                    'updateTextStyle': {
                        'range': {'startIndex': fmt_start, 'endIndex': fmt_end},
                        'textStyle': {
                            'weightedFontFamily': {'fontFamily': 'Roboto Mono'},
                            'backgroundColor': {'color': {'rgbColor': {'red': 0.95, 'green': 0.95, 'blue': 0.95}}}
                        },
                        'fields': 'weightedFontFamily,backgroundColor'
                    }
                })

        current_index = line_end

    return text_content.rstrip('\n'), format_requests, current_index


def convert_markdown_to_doc(docs_service, drive_service, title, markdown_content, parent_id):
    """Convert markdown to Google Doc with table support - batched operations."""

    # Create empty document
    doc_id = create_document(drive_service, title, parent_id)
    print(f"Created document: {doc_id}")

    # Parse markdown
    blocks = parse_markdown(markdown_content)

    # Group blocks: split by tables
    groups = []
    current_group = []

    for block in blocks:
        if block['type'] == 'table':
            if current_group:
                groups.append(('text', current_group))
                current_group = []
            groups.append(('table', block))
        else:
            current_group.append(block)

    if current_group:
        groups.append(('text', current_group))

    # Process each group
    current_index = 1

    for group_type, group_data in groups:
        if group_type == 'text':
            # Build text and formatting for this group
            text_content, format_requests, _ = build_text_and_formatting(group_data, current_index)

            if text_content:
                # Insert text
                docs_service.documents().batchUpdate(
                    documentId=doc_id,
                    body={'requests': [{'insertText': {'location': {'index': current_index}, 'text': text_content + '\n'}}]}
                ).execute()

                # Apply formatting
                if format_requests:
                    docs_service.documents().batchUpdate(
                        documentId=doc_id,
                        body={'requests': format_requests}
                    ).execute()

            current_index = get_doc_end_index(docs_service, doc_id)

        elif group_type == 'table':
            rows = group_data['rows']
            num_rows = len(rows)
            num_cols = len(rows[0]) if rows else 0

            if num_rows > 0 and num_cols > 0:
                cell_indices = insert_table(docs_service, doc_id, num_rows, num_cols, current_index)
                populate_table(docs_service, doc_id, cell_indices, rows)
                current_index = get_doc_end_index(docs_service, doc_id)

    return f"https://docs.google.com/document/d/{doc_id}"


def main():
    parser = argparse.ArgumentParser(description='Convert Markdown to Google Docs')
    parser.add_argument('--title', required=True, help='Document title')
    parser.add_argument('--file', required=True, help='Markdown file path')
    parser.add_argument('--folder', help='Target folder name in Shared Drive')
    args = parser.parse_args()

    # Get credentials
    try:
        creds = get_credentials()
    except Exception as e:
        print(f"Error: {e}", file=sys.stderr)
        sys.exit(1)

    # Get Shared Drive ID
    drive_id = os.environ.get('GOOGLE_SHARED_DRIVE_ID')
    if not drive_id:
        print("Error: GOOGLE_SHARED_DRIVE_ID environment variable not set", file=sys.stderr)
        sys.exit(1)

    # Build services
    docs_service = build('docs', 'v1', credentials=creds)
    drive_service = build('drive', 'v3', credentials=creds)

    # Find target folder
    parent_id = drive_id
    if args.folder:
        folder_id = find_folder(drive_service, args.folder, drive_id)
        if folder_id:
            parent_id = folder_id
            print(f"Using folder: {args.folder}")
        else:
            print(f"Warning: Folder '{args.folder}' not found, using root")

    # Read markdown file
    try:
        with open(args.file, 'r', encoding='utf-8') as f:
            content = f.read()
    except Exception as e:
        print(f"Error reading file: {e}", file=sys.stderr)
        sys.exit(1)

    # Convert
    try:
        url = convert_markdown_to_doc(docs_service, drive_service, args.title, content, parent_id)
        print(f"URL: {url}")
    except Exception as e:
        print(f"Error: {e}", file=sys.stderr)
        sys.exit(1)


if __name__ == '__main__':
    main()
