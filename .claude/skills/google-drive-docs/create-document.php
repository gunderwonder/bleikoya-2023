<?php
/**
 * Create a Google Docs document in the Shared Drive.
 *
 * Usage:
 *   wp eval-file .claude/skills/google-drive-docs/create-document.php
 *
 * Set these variables before the require:
 *   $doc_title   - Document name (required)
 *   $doc_content - Text content (optional)
 *   $doc_file    - Path to file with content, markdown auto-converted (optional)
 *   $doc_folder  - Target folder name in Shared Drive (optional, default: root)
 *   $doc_force   - Set to true to skip "already exists" check (optional)
 *
 * Note: Pageless format is not supported by Google Docs API.
 *       Convert manually in UI: File > Page setup > Pageless
 *
 * Example:
 *   wp eval '$doc_title = "Test"; $doc_content = "Hello"; require get_stylesheet_directory() . "/.claude/skills/google-drive-docs/create-document.php";'
 */

if (empty($doc_title)) {
    echo "Error: \$doc_title is required\n";
    exit(1);
}

require_once get_stylesheet_directory() . '/includes/google/sheets-export.php';
require_once __DIR__ . '/markdown-to-docs.php';

use Google\Service\Drive;
use Google\Service\Docs;

$client = get_google_client();
if (is_wp_error($client)) {
    echo "Error: " . $client->get_error_message() . "\n";
    exit(1);
}

$shared_drive_id = $_ENV['GOOGLE_SHARED_DRIVE_ID'] ?? getenv('GOOGLE_SHARED_DRIVE_ID') ?: '';
if (empty($shared_drive_id)) {
    echo "Error: GOOGLE_SHARED_DRIVE_ID not set\n";
    exit(1);
}

$drive_service = new Drive($client);
$docs_service = new Docs($client);

// Find target folder
$parent_id = $shared_drive_id;
if (!empty($doc_folder)) {
    $query = "name = '" . addslashes($doc_folder) . "' and mimeType = 'application/vnd.google-apps.folder'";
    $results = $drive_service->files->listFiles([
        'q' => $query,
        'driveId' => $shared_drive_id,
        'corpora' => 'drive',
        'includeItemsFromAllDrives' => true,
        'supportsAllDrives' => true,
        'fields' => 'files(id, name)',
    ]);
    $folders = $results->getFiles();
    if (!empty($folders)) {
        $parent_id = $folders[0]->getId();
        echo "Mappe: {$folders[0]->getName()}\n";
    } else {
        echo "Advarsel: Mappe '$doc_folder' ikke funnet, bruker rot\n";
    }
}

// Get content
$content = $doc_content ?? '';
$is_markdown = false;
$has_tables = false;
$actual_file_path = null;

if (!empty($doc_file)) {
    $file_path = $doc_file;
    if (!file_exists($file_path)) {
        $file_path = get_stylesheet_directory() . '/' . $doc_file;
    }
    if (!file_exists($file_path)) {
        echo "Error: Fil ikke funnet: $doc_file\n";
        exit(1);
    }
    $actual_file_path = $file_path;
    $content = file_get_contents($file_path);
    $is_markdown = preg_match('/\.md$/i', $file_path);
    $has_tables = $is_markdown && preg_match('/^\|.+\|/m', $content);
}

// Check if document already exists (unless force flag is set)
if (empty($doc_force)) {
    $query = "name = '" . addslashes($doc_title) . "' and mimeType = 'application/vnd.google-apps.document'";
    $existing = $drive_service->files->listFiles([
        'q' => $query,
        'driveId' => $shared_drive_id,
        'corpora' => 'drive',
        'includeItemsFromAllDrives' => true,
        'supportsAllDrives' => true,
        'fields' => 'files(id, name)',
    ]);

    if (!empty($existing->getFiles())) {
        $doc_id = $existing->getFiles()[0]->getId();
        echo "Dokument finnes allerede: https://docs.google.com/document/d/$doc_id\n";
        echo "Bruk \$doc_force = true for å opprette på nytt.\n";
        exit(0);
    }
}

// Use Python script for markdown with tables (real table support)
if ($has_tables && $actual_file_path) {
    $script_path = __DIR__ . '/md2gdoc.py';
    $folder_arg = !empty($doc_folder) ? ' --folder ' . escapeshellarg($doc_folder) : '';

    // Get credentials path and make it absolute if relative
    $creds_path = $_ENV['GOOGLE_APPLICATION_CREDENTIALS'] ?? getenv('GOOGLE_APPLICATION_CREDENTIALS');
    if ($creds_path && $creds_path[0] !== '/') {
        $creds_path = get_stylesheet_directory() . '/' . $creds_path;
    }

    $cmd = sprintf(
        'GOOGLE_APPLICATION_CREDENTIALS=%s GOOGLE_SHARED_DRIVE_ID=%s uv run %s --title %s --file %s%s 2>&1',
        escapeshellarg($creds_path),
        escapeshellarg($shared_drive_id),
        escapeshellarg($script_path),
        escapeshellarg($doc_title),
        escapeshellarg($actual_file_path),
        $folder_arg
    );

    exec($cmd, $output, $return_code);
    $result = implode("\n", $output);

    if ($return_code !== 0) {
        echo "Feil ved Python-konvertering:\n$result\n";
        exit(1);
    }

    echo $result . "\n";
    exit(0);
}

// Create document
$file_metadata = new Drive\DriveFile([
    'name' => $doc_title,
    'mimeType' => 'application/vnd.google-apps.document',
    'parents' => [$parent_id],
]);

$file = $drive_service->files->create($file_metadata, ['supportsAllDrives' => true]);
$doc_id = $file->getId();
echo "Opprettet dokument: $doc_id\n";

// Note: Pageless format is not supported by Google Docs API yet
// Documents must be converted to pageless manually in the UI if needed

// Add content if provided
if (!empty($content)) {
    $requests = [];

    $content_length = 0;

    if ($is_markdown) {
        // Convert markdown with full formatting
        $result = markdown_to_docs_requests($content, 1);
        $text_to_insert = $result['text'];
        $content_length = strlen($text_to_insert);

        // First insert all text
        $requests[] = new Docs\Request([
            'insertText' => [
                'location' => ['index' => 1],
                'text' => $text_to_insert,
            ],
        ]);

        // Then apply formatting (must be after text insertion)
        foreach ($result['requests'] as $fmt_request) {
            $requests[] = new Docs\Request($fmt_request);
        }
    } else {
        // Plain text
        $content_length = strlen($content);
        $requests[] = new Docs\Request([
            'insertText' => [
                'location' => ['index' => 1],
                'text' => $content,
            ],
        ]);
    }

    $batch = new Docs\BatchUpdateDocumentRequest(['requests' => $requests]);
    try {
        $docs_service->documents->batchUpdate($doc_id, $batch);
        echo "Innhold lagt til" . ($is_markdown ? " med formatering" : "") . " ($content_length tegn)\n";
    } catch (Exception $e) {
        echo "Feil ved innhold/formatering: " . $e->getMessage() . "\n";
    }
}

echo "URL: https://docs.google.com/document/d/$doc_id\n";
