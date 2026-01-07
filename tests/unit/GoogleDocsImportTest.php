<?php
/**
 * Unit tests for Google Docs import functionality.
 *
 * @package Bleikoya2023
 */

use PHPUnit\Framework\TestCase;

// Load the file under test
require_once dirname(__DIR__, 2) . '/includes/google/docs-import.php';

class GoogleDocsImportTest extends TestCase {

	protected function setUp(): void {
		reset_mock_data();
	}

	// =========================================================================
	// extract_google_doc_id() tests
	// =========================================================================

	public function test_extract_doc_id_from_full_url(): void {
		$url = 'https://docs.google.com/document/d/1AbCdEfGhIjKlMnOpQrStUvWxYz/edit';
		$result = extract_google_doc_id($url);
		$this->assertEquals('1AbCdEfGhIjKlMnOpQrStUvWxYz', $result);
	}

	public function test_extract_doc_id_from_url_without_edit(): void {
		$url = 'https://docs.google.com/document/d/1AbCdEfGhIjKlMnOpQrStUvWxYz';
		$result = extract_google_doc_id($url);
		$this->assertEquals('1AbCdEfGhIjKlMnOpQrStUvWxYz', $result);
	}

	public function test_extract_doc_id_from_url_with_query_params(): void {
		$url = 'https://docs.google.com/document/d/1AbCdEfGhIjKlMnOpQrStUvWxYz/edit?usp=sharing';
		$result = extract_google_doc_id($url);
		$this->assertEquals('1AbCdEfGhIjKlMnOpQrStUvWxYz', $result);
	}

	public function test_extract_doc_id_from_plain_id(): void {
		$id = '1AbCdEfGhIjKlMnOpQrStUvWxYz';
		$result = extract_google_doc_id($id);
		$this->assertEquals('1AbCdEfGhIjKlMnOpQrStUvWxYz', $result);
	}

	public function test_extract_doc_id_with_underscores_and_hyphens(): void {
		$url = 'https://docs.google.com/document/d/1Ab_Cd-EfGhIjKlMnOpQrStUvWxYz/edit';
		$result = extract_google_doc_id($url);
		$this->assertEquals('1Ab_Cd-EfGhIjKlMnOpQrStUvWxYz', $result);
	}

	public function test_extract_doc_id_returns_null_for_invalid_url(): void {
		$url = 'https://docs.google.com/spreadsheets/d/1AbCdEfGhIjKlMnOpQrStUvWxYz/edit';
		$result = extract_google_doc_id($url);
		$this->assertNull($result);
	}

	public function test_extract_doc_id_returns_null_for_random_url(): void {
		$url = 'https://example.com/something';
		$result = extract_google_doc_id($url);
		$this->assertNull($result);
	}

	// =========================================================================
	// process_text_run() tests
	// =========================================================================

	public function test_process_text_run_plain_text(): void {
		$text_run = $this->createMockTextRun('Hello World', []);
		$result = process_text_run($text_run);
		$this->assertEquals('Hello World', $result);
	}

	public function test_process_text_run_bold_text(): void {
		$text_run = $this->createMockTextRun('Bold text', ['bold' => true]);
		$result = process_text_run($text_run);
		$this->assertEquals('<strong>Bold text</strong>', $result);
	}

	public function test_process_text_run_italic_text(): void {
		$text_run = $this->createMockTextRun('Italic text', ['italic' => true]);
		$result = process_text_run($text_run);
		$this->assertEquals('<em>Italic text</em>', $result);
	}

	public function test_process_text_run_bold_and_italic(): void {
		$text_run = $this->createMockTextRun('Bold italic', ['bold' => true, 'italic' => true]);
		$result = process_text_run($text_run);
		$this->assertEquals('<em><strong>Bold italic</strong></em>', $result);
	}

	public function test_process_text_run_underline(): void {
		$text_run = $this->createMockTextRun('Underline', ['underline' => true]);
		$result = process_text_run($text_run);
		$this->assertEquals('<u>Underline</u>', $result);
	}

	public function test_process_text_run_strikethrough(): void {
		$text_run = $this->createMockTextRun('Strikethrough', ['strikethrough' => true]);
		$result = process_text_run($text_run);
		$this->assertEquals('<del>Strikethrough</del>', $result);
	}

	public function test_process_text_run_with_link(): void {
		$text_run = $this->createMockTextRun('Click here', ['link' => 'https://example.com']);
		$result = process_text_run($text_run);
		$this->assertEquals('<a href="https://example.com">Click here</a>', $result);
	}

	public function test_process_text_run_escapes_html(): void {
		$text_run = $this->createMockTextRun('<script>alert("xss")</script>', []);
		$result = process_text_run($text_run);
		$this->assertStringNotContainsString('<script>', $result);
		$this->assertStringContainsString('&lt;script&gt;', $result);
	}

	// =========================================================================
	// Helper methods for creating mock objects
	// =========================================================================

	/**
	 * Create a mock TextRun object.
	 *
	 * @param string $content Text content
	 * @param array $style Style options (bold, italic, underline, strikethrough, link)
	 * @return object Mock TextRun
	 */
	private function createMockTextRun(string $content, array $style): object {
		$text_run = new class {
			public $content;
			public $style;

			public function getContent() {
				return $this->content;
			}

			public function getTextStyle() {
				return $this->style;
			}
		};

		$text_run->content = $content;

		if (empty($style)) {
			$text_run->style = null;
		} else {
			$text_run->style = new class {
				public $bold = false;
				public $italic = false;
				public $underline = false;
				public $strikethrough = false;
				public $link = null;

				public function getBold() {
					return $this->bold;
				}

				public function getItalic() {
					return $this->italic;
				}

				public function getUnderline() {
					return $this->underline;
				}

				public function getStrikethrough() {
					return $this->strikethrough;
				}

				public function getLink() {
					return $this->link;
				}
			};

			$text_run->style->bold = $style['bold'] ?? false;
			$text_run->style->italic = $style['italic'] ?? false;
			$text_run->style->underline = $style['underline'] ?? false;
			$text_run->style->strikethrough = $style['strikethrough'] ?? false;

			if (isset($style['link'])) {
				$text_run->style->link = new class {
					public $url;

					public function getUrl() {
						return $this->url;
					}
				};
				$text_run->style->link->url = $style['link'];
			}
		}

		return $text_run;
	}
}
