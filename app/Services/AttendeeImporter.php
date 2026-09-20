<?php

namespace App\Services;

use App\Models\Event;
use App\Support\Phone;
use Illuminate\Support\Str;

/**
 * CSV → attendees + passes. Tolerant of whatever the organizer exported from
 * Excel/Google Sheets: header names are matched loosely, extra columns are kept
 * in `extra`, and rows with an existing phone update the name instead of
 * creating a second person.
 */
final class AttendeeImporter
{
    /** Header aliases → attendee column. Compared lowercase, non-alphanumerics stripped. */
    private const ALIASES = [
        'name' => ['name', 'fullname', 'attendee', 'attendeename', 'guest', 'guestname', 'participant'],
        'phone' => ['phone', 'mobile', 'mobileno', 'phoneno', 'phonenumber', 'contact', 'contactno', 'whatsapp', 'cell'],
        'email' => ['email', 'emailaddress', 'mail'],
        'ticket_type' => ['ticket', 'tickettype', 'type', 'category', 'pass', 'passtype'],
        'is_vip' => ['vip', 'isvip'],
    ];

    /** @return array{created: int, updated: int, skipped: int, errors: list<string>} */
    public static function import(Event $event, string $csvPath): array
    {
        $handle = fopen($csvPath, 'r');
        if ($handle === false) {
            return ['created' => 0, 'updated' => 0, 'skipped' => 0, 'errors' => ['Could not open the file.']];
        }

        $stats = ['created' => 0, 'updated' => 0, 'skipped' => 0, 'errors' => []];

        // Strip a UTF-8 BOM (Excel adds one) before reading the header.
        $first = fgets($handle);
        if ($first === false) {
            fclose($handle);
            $stats['errors'][] = 'The file is empty.';

            return $stats;
        }
        $first = preg_replace('/^\xEF\xBB\xBF/', '', $first);
        $header = str_getcsv($first);
        $map = self::mapHeader($header);

        if (! isset($map['name'])) {
            fclose($handle);
            $stats['errors'][] = 'No "name" column found. Expected headers like: name, phone, email, ticket, vip.';

            return $stats;
        }

        $line = 1;
        while (($row = fgetcsv($handle)) !== false) {
            $line++;
            if (count($row) === 1 && trim((string) $row[0]) === '') {
                continue; // blank line
            }

            $get = fn (string $key) => isset($map[$key], $row[$map[$key]]) ? trim((string) $row[$map[$key]]) : null;

            $name = $get('name');
            if ($name === null || $name === '') {
                $stats['skipped']++;
                $stats['errors'][] = "Line {$line}: no name.";

                continue;
            }

            $phone = self::normalisePhone($get('phone'));
            $email = filter_var($get('email'), FILTER_VALIDATE_EMAIL) ? $get('email') : null;
            $ticket = Str::lower($get('ticket_type') ?: 'general');
            $vip = in_array(Str::lower((string) $get('is_vip')), ['1', 'y', 'yes', 'true', 'vip'], true) || $ticket === 'vip';

            // Everything we didn't map is kept, so nothing from their sheet is lost.
            $extra = [];
            foreach ($header as $i => $col) {
                if (! in_array($i, $map, true) && isset($row[$i]) && trim((string) $row[$i]) !== '') {
                    $extra[trim((string) $col)] = trim((string) $row[$i]);
                }
            }

            $data = [
                'name' => Str::limit($name, 120, ''),
                'phone' => $phone,
                'email' => $email,
                'ticket_type' => Str::limit($ticket, 40, ''),
                'is_vip' => $vip,
                'source' => 'import',
                'extra' => $extra ?: null,
            ];

            $attendee = $phone ? $event->attendees()->where('phone', $phone)->first() : null;
            if ($attendee) {
                $attendee->update($data);
                $stats['updated']++;
            } else {
                $attendee = $event->attendees()->create($data);
                $stats['created']++;
            }
            $attendee->pass ?? $attendee->pass()->create(['event_id' => $event->id]);
        }

        fclose($handle);

        return $stats;
    }

    /** @param  list<string|null>  $header  @return array<string, int> attendee column → csv index */
    private static function mapHeader(array $header): array
    {
        $map = [];
        foreach ($header as $i => $col) {
            $key = preg_replace('/[^a-z0-9]/', '', Str::lower((string) $col));
            foreach (self::ALIASES as $field => $aliases) {
                if (! isset($map[$field]) && in_array($key, $aliases, true)) {
                    $map[$field] = $i;
                    break;
                }
            }
        }

        return $map;
    }

    /** Keep digits (and a leading +); drop a leading 0 or 91 on 10-digit Indian numbers so lookups match. */
    public static function normalisePhone(?string $raw): ?string
    {
        return Phone::normalise($raw);
    }
}
