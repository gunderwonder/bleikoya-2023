<?php
/**
 * Tests for iCal Feed Helper Functions
 *
 * Tests RFC 5545 compliance for iCalendar feed generation.
 */

declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\DataProvider;

// Include the functions being tested
require_once dirname(__DIR__, 2) . '/includes/events.php';

class ICalFeedTest extends TestCase
{
    // ===========================================
    // bleikoya_ical_escape() Tests
    // ===========================================

    #[Test]
    public function ical_escape_returns_empty_string_for_empty_input(): void
    {
        $result = bleikoya_ical_escape('');

        $this->assertEquals('', $result);
    }

    #[Test]
    public function ical_escape_preserves_plain_text(): void
    {
        $result = bleikoya_ical_escape('Simple event title');

        $this->assertEquals('Simple event title', $result);
    }

    #[Test]
    public function ical_escape_escapes_backslashes(): void
    {
        $result = bleikoya_ical_escape('Path\\to\\file');

        $this->assertEquals('Path\\\\to\\\\file', $result);
    }

    #[Test]
    public function ical_escape_escapes_commas(): void
    {
        $result = bleikoya_ical_escape('Oslo, Norway');

        $this->assertEquals('Oslo\\, Norway', $result);
    }

    #[Test]
    public function ical_escape_escapes_semicolons(): void
    {
        $result = bleikoya_ical_escape('Event; Special');

        $this->assertEquals('Event\\; Special', $result);
    }

    #[Test]
    public function ical_escape_converts_newlines_to_escaped_n(): void
    {
        $result = bleikoya_ical_escape("Line 1\nLine 2");

        $this->assertEquals('Line 1\\nLine 2', $result);
    }

    #[Test]
    public function ical_escape_converts_crlf_to_escaped_n(): void
    {
        $result = bleikoya_ical_escape("Line 1\r\nLine 2");

        $this->assertEquals('Line 1\\nLine 2', $result);
    }

    #[Test]
    public function ical_escape_converts_cr_to_escaped_n(): void
    {
        $result = bleikoya_ical_escape("Line 1\rLine 2");

        $this->assertEquals('Line 1\\nLine 2', $result);
    }

    #[Test]
    public function ical_escape_strips_html_tags(): void
    {
        $result = bleikoya_ical_escape('<strong>Bold</strong> text');

        $this->assertEquals('Bold text', $result);
    }

    #[Test]
    public function ical_escape_handles_complex_html(): void
    {
        $result = bleikoya_ical_escape('<p>Paragraph</p><br><a href="url">Link</a>');

        $this->assertEquals('ParagraphLink', $result);
    }

    #[Test]
    public function ical_escape_handles_multiple_special_characters(): void
    {
        $result = bleikoya_ical_escape("Event; in Oslo, Norway\nwith backslash\\");

        $this->assertEquals('Event\\; in Oslo\\, Norway\\nwith backslash\\\\', $result);
    }

    #[Test]
    public function ical_escape_handles_norwegian_characters(): void
    {
        $result = bleikoya_ical_escape('Bleikøya Velforening');

        $this->assertEquals('Bleikøya Velforening', $result);
    }

    #[Test]
    public function ical_escape_preserves_unicode(): void
    {
        $result = bleikoya_ical_escape('Café ☕ møte');

        $this->assertEquals('Café ☕ møte', $result);
    }

    // ===========================================
    // bleikoya_ical_fold() Tests
    // ===========================================

    #[Test]
    public function ical_fold_preserves_short_lines(): void
    {
        $line = 'SUMMARY:Short title';

        $result = bleikoya_ical_fold($line);

        $this->assertEquals($line, $result);
    }

    #[Test]
    public function ical_fold_preserves_exactly_75_char_lines(): void
    {
        $line = str_repeat('x', 75);

        $result = bleikoya_ical_fold($line);

        $this->assertEquals($line, $result);
    }

    #[Test]
    public function ical_fold_folds_76_char_lines(): void
    {
        $line = str_repeat('x', 76);

        $result = bleikoya_ical_fold($line);

        // Should be 75 chars + CRLF + space + 1 char
        $expected = str_repeat('x', 75) . "\r\n " . 'x';
        $this->assertEquals($expected, $result);
    }

    #[Test]
    public function ical_fold_folds_long_lines_at_75_chars(): void
    {
        $line = str_repeat('a', 160);

        $result = bleikoya_ical_fold($line);

        // Should be: 75 + CRLF + space + 75 + CRLF + space + 10
        $expected = str_repeat('a', 75) . "\r\n " . str_repeat('a', 75) . "\r\n " . str_repeat('a', 10);
        $this->assertEquals($expected, $result);
    }

    #[Test]
    public function ical_fold_uses_crlf_and_space(): void
    {
        $line = str_repeat('z', 100);

        $result = bleikoya_ical_fold($line);

        // Verify the fold uses CRLF followed by a space
        $this->assertStringContainsString("\r\n ", $result);
    }

    #[Test]
    public function ical_fold_handles_realistic_summary(): void
    {
        $line = 'SUMMARY:This is a very long event title that exceeds seventy-five characters and needs to be folded';

        $result = bleikoya_ical_fold($line);

        // Each line in the output should be at most 75 chars (except for continuation lines which include the leading space)
        $lines = explode("\r\n", $result);
        $this->assertEquals(75, strlen($lines[0]));
        // Second line starts with space, so actual content is 74 chars max
        $this->assertLessThanOrEqual(76, strlen($lines[1])); // space + up to 75
    }

    #[Test]
    public function ical_fold_handles_empty_string(): void
    {
        $result = bleikoya_ical_fold('');

        $this->assertEquals('', $result);
    }

    // ===========================================
    // bleikoya_ical_allday_dtend() Tests
    // ===========================================

    #[Test]
    public function ical_allday_dtend_adds_one_day(): void
    {
        $result = bleikoya_ical_allday_dtend('20240615');

        $this->assertEquals('20240616', $result);
    }

    #[Test]
    public function ical_allday_dtend_handles_month_boundary(): void
    {
        $result = bleikoya_ical_allday_dtend('20240131');

        $this->assertEquals('20240201', $result);
    }

    #[Test]
    public function ical_allday_dtend_handles_year_boundary(): void
    {
        $result = bleikoya_ical_allday_dtend('20241231');

        $this->assertEquals('20250101', $result);
    }

    #[Test]
    public function ical_allday_dtend_handles_leap_year(): void
    {
        $result = bleikoya_ical_allday_dtend('20240228');

        $this->assertEquals('20240229', $result);
    }

    #[Test]
    public function ical_allday_dtend_handles_leap_year_feb_29(): void
    {
        $result = bleikoya_ical_allday_dtend('20240229');

        $this->assertEquals('20240301', $result);
    }

    #[Test]
    public function ical_allday_dtend_handles_non_leap_year(): void
    {
        $result = bleikoya_ical_allday_dtend('20230228');

        $this->assertEquals('20230301', $result);
    }

    #[Test]
    public function ical_allday_dtend_returns_input_for_invalid_date(): void
    {
        $result = bleikoya_ical_allday_dtend('invalid');

        $this->assertEquals('invalid', $result);
    }

    #[Test]
    public function ical_allday_dtend_returns_input_for_wrong_format(): void
    {
        $result = bleikoya_ical_allday_dtend('2024-06-15');

        $this->assertEquals('2024-06-15', $result);
    }

    // ===========================================
    // bleikoya_ical_uid() Tests
    // ===========================================

    #[Test]
    public function ical_uid_contains_post_id(): void
    {
        $result = bleikoya_ical_uid(123, '20240615T100000Z', false, 'bleikoya.net');

        $this->assertStringContainsString('bleikoya-123-', $result);
    }

    #[Test]
    public function ical_uid_contains_domain(): void
    {
        $result = bleikoya_ical_uid(123, '20240615T100000Z', false, 'bleikoya.net');

        $this->assertStringEndsWith('@bleikoya.net', $result);
    }

    #[Test]
    public function ical_uid_contains_hash(): void
    {
        $result = bleikoya_ical_uid(123, '20240615T100000Z', false, 'bleikoya.net');

        // Should match format: bleikoya-{id}-{32char-hash}@{domain}
        $this->assertMatchesRegularExpression('/^bleikoya-\d+-[a-f0-9]{32}@.+$/', $result);
    }

    #[Test]
    public function ical_uid_is_stable_for_same_inputs(): void
    {
        $uid1 = bleikoya_ical_uid(123, '20240615T100000Z', false, 'bleikoya.net');
        $uid2 = bleikoya_ical_uid(123, '20240615T100000Z', false, 'bleikoya.net');

        $this->assertEquals($uid1, $uid2);
    }

    #[Test]
    public function ical_uid_differs_for_different_post_ids(): void
    {
        $uid1 = bleikoya_ical_uid(123, '20240615T100000Z', false, 'bleikoya.net');
        $uid2 = bleikoya_ical_uid(456, '20240615T100000Z', false, 'bleikoya.net');

        $this->assertNotEquals($uid1, $uid2);
    }

    #[Test]
    public function ical_uid_differs_for_different_start_times(): void
    {
        $uid1 = bleikoya_ical_uid(123, '20240615T100000Z', false, 'bleikoya.net');
        $uid2 = bleikoya_ical_uid(123, '20240615T110000Z', false, 'bleikoya.net');

        $this->assertNotEquals($uid1, $uid2);
    }

    #[Test]
    public function ical_uid_differs_for_allday_vs_timed_events(): void
    {
        // Same event ID, same date, but one is all-day
        $uid1 = bleikoya_ical_uid(123, '20240615', true, 'bleikoya.net');
        $uid2 = bleikoya_ical_uid(123, '20240615', false, 'bleikoya.net');

        $this->assertNotEquals($uid1, $uid2);
    }

    #[Test]
    public function ical_uid_is_unique_for_recurring_event_occurrences(): void
    {
        // Same post ID, different dates (recurring event)
        $uid1 = bleikoya_ical_uid(123, '20240601', true, 'bleikoya.net');
        $uid2 = bleikoya_ical_uid(123, '20240608', true, 'bleikoya.net');

        $this->assertNotEquals($uid1, $uid2);
    }

    // ===========================================
    // bleikoya_ical_location() Tests
    // ===========================================

    #[Test]
    public function ical_location_returns_empty_for_no_data(): void
    {
        $result = bleikoya_ical_location('', []);

        $this->assertEquals('', $result);
    }

    #[Test]
    public function ical_location_returns_venue_only(): void
    {
        $result = bleikoya_ical_location('Velhuset', []);

        $this->assertEquals('Velhuset', $result);
    }

    #[Test]
    public function ical_location_returns_address_only(): void
    {
        $result = bleikoya_ical_location('', ['Strandveien 1', '0150', 'Oslo', '', 'Norway']);

        $this->assertEquals('Strandveien 1, 0150, Oslo, Norway', $result);
    }

    #[Test]
    public function ical_location_combines_venue_and_address(): void
    {
        $result = bleikoya_ical_location('Velhuset', ['Strandveien 1', '0150', 'Oslo']);

        $this->assertEquals('Velhuset, Strandveien 1, 0150, Oslo', $result);
    }

    #[Test]
    public function ical_location_filters_empty_address_parts(): void
    {
        $result = bleikoya_ical_location('Velhuset', ['', '', 'Oslo', '', 'Norway']);

        $this->assertEquals('Velhuset, Oslo, Norway', $result);
    }

    #[Test]
    public function ical_location_trims_whitespace(): void
    {
        $result = bleikoya_ical_location('  Velhuset  ', ['  Oslo  ', '  Norway  ']);

        $this->assertEquals('Velhuset, Oslo, Norway', $result);
    }

    #[Test]
    public function ical_location_handles_all_empty_address_parts(): void
    {
        $result = bleikoya_ical_location('Velhuset', ['', '  ', '', null]);

        // Should just be the venue without trailing comma
        $this->assertEquals('Velhuset', $result);
    }

    #[Test]
    public function ical_location_handles_null_venue_gracefully(): void
    {
        // In PHP 8, empty string is used when venue might be null
        $result = bleikoya_ical_location('', ['Oslo', 'Norway']);

        $this->assertEquals('Oslo, Norway', $result);
    }

    // ===========================================
    // Integration-style tests
    // ===========================================

    #[Test]
    public function realistic_event_escaping(): void
    {
        $title = "Sommerfest på Bleikøya; mat, drikke og musikk!";
        $escaped = bleikoya_ical_escape($title);

        $this->assertEquals("Sommerfest på Bleikøya\\; mat\\, drikke og musikk!", $escaped);
    }

    #[Test]
    public function realistic_long_description(): void
    {
        $description = "DESCRIPTION:Velkommen til årets sommerfest på Bleikøya! Vi starter kl. 15:00 med aktiviteter for barn\\, deretter mat og drikke. Husk å melde deg på via skjemaet.";

        $folded = bleikoya_ical_fold($description);

        // Verify each physical line is max 75 chars
        $lines = explode("\r\n", $folded);
        foreach ($lines as $i => $line) {
            $maxLen = ($i === 0) ? 75 : 76; // Continuation lines have leading space
            $this->assertLessThanOrEqual($maxLen, strlen($line), "Line $i exceeds max length");
        }
    }

    #[Test]
    public function multi_day_allday_event_dtend(): void
    {
        // A 3-day event from June 15-17 should have DTEND on June 18
        // (because DTEND is exclusive for DATE values)
        $end_date = '20240617';
        $result = bleikoya_ical_allday_dtend($end_date);

        $this->assertEquals('20240618', $result);
    }
}
