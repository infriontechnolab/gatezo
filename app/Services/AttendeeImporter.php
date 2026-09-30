<?php

namespace App\Services;

use App\Enums\AttendeeSource;
use App\Enums\TicketType;
use App\Models\Event;
use App\Rules\PersonName;
use App\Support\Phone;

/**
 * CSV → attendees + passes. Tolerant of whatever the organizer exported from
 * Excel/Google Sheets: header names are matched loosely, extra columns are kept
 * in `extra`, and rows with an existing phone update the name instead of
 * creating a second person.
 */
final class AttendeeImporter
{
    /**
     * Header aliases → attendee column. Compared lowercase, non-alphanumerics stripped.
     * Covers hand-made sheets and exports from Eventbrite (First Name, Cell Phone, Attendee
     * Status), Luma (first_name, phone_number, approval_status) and Google Forms.
     */
    private const ALIASES = [
        'name' => ['name', 'fullname', 'yourname', 'yourfullname', 'attendee', 'attendeename', 'guest', 'guestname', 'participant', 'participantname'],
        'first_name' => ['firstname', 'first', 'givenname'],
        'last_name' => ['lastname', 'last', 'surname', 'familyname'],
        'phone' => ['phone', 'mobile', 'mobileno', 'mobilenumber', 'mobilephone', 'cellphone', 'cell', 'phoneno', 'phonenumber', 'contact', 'contactno', 'contactnumber', 'whatsapp', 'whatsappno', 'whatsappnumber'],
        'email' => ['email', 'emailaddress', 'emailid', 'mail'],
        'ticket_type' => ['ticket', 'tickettype', 'ticketname', 'type', 'category', 'pass', 'passtype'],
        'is_vip' => ['vip', 'isvip'],
        'status' => ['status', 'attendeestatus', 'approvalstatus', 'bookingstatus', 'registrationstatus', 'orderstatus', 'ticketstatus'],
    ];

    /**
     * Google Forms uses the question as the header ("What is your WhatsApp number?"). When no
     * header matches exactly, a header containing one of these words is used instead, unless
     * it is about someone else.
     */
    private const LOOSE = ['name' => 'name', 'phone' => ['phone', 'mobile', 'whatsapp'], 'email' => 'email'];

    private const NOT_THE_ATTENDEE = ['company', 'college', 'organisation', 'organization', 'institute', 'school', 'team', 'event', 'father', 'mother', 'parent', 'guardian', 'emergency', 'alternate', 'referred', 'user', 'business', 'first', 'last'];

    /** Rows in these states have no place at the gate (compared like headers). */
    private const SKIP_STATUSES = ['refunded', 'cancelled', 'canceled', 'declined', 'rejected', 'notattending', 'deleted', 'waitlist', 'waitlisted', 'transferred', 'pendingapproval', 'invited'];

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

        if (! isset($map['name']) && ! isset($map['first_name'])) {
            fclose($handle);
            $stats['errors'][] = 'No name column found. Expected headers like: name (or first name and last name), phone, email, ticket, vip.';

            return $stats;
        }

        $line = 1;
        while (($row = fgetcsv($handle)) !== false) {
            $line++;
            if (count($row) === 1 && trim((string) $row[0]) === '') {
                continue; // blank line
            }

            $get = fn (string $key) => isset($map[$key], $row[$map[$key]]) ? trim((string) $row[$map[$key]]) : null;

            $status = preg_replace('/[^a-z0-9]/', '', str()->lower((string) $get('status')));
            if (in_array($status, self::SKIP_STATUSES, true)) {
                $stats['skipped']++;
                $stats['errors'][] = "Line {$line}: skipped, status \"{$get('status')}\".";

                continue;
            }

            $name = $get('name') ?: trim($get('first_name').' '.$get('last_name'));
            if ($name === '') {
                $stats['skipped']++;
                $stats['errors'][] = "Line {$line}: no name.";

                continue;
            }
            if (! PersonName::passes($name)) {
                $stats['skipped']++;
                $stats['errors'][] = "Line {$line}: \"".str()->limit($name, 40).'" is not a name (letters only).';

                continue;
            }

            $phone = self::normalisePhone($get('phone'));
            if ($phone !== null && (strlen($phone) < 8 || strlen($phone) > 15)) {
                $stats['errors'][] = "Line {$line}: phone \"{$get('phone')}\" ignored (not a full number).";
                $phone = null;
            }
            $rawEmail = $get('email');
            $email = filter_var($rawEmail, FILTER_VALIDATE_EMAIL) ? $rawEmail : null;
            if ($rawEmail !== null && $rawEmail !== '' && $email === null) {
                $stats['errors'][] = "Line {$line}: email \"{$rawEmail}\" ignored (not a valid address).";
            }
            $rawTicket = trim((string) $get('ticket_type'));
            $ticket = TicketType::fromImport($rawTicket);
            $vip = in_array(str()->lower((string) $get('is_vip')), ['1', 'y', 'yes', 'true', 'vip'], true) || $ticket === TicketType::Vip;

            // Everything we didn't map is kept, so nothing from their sheet is lost, including a
            // ticket label of their own ("Gold") that is stored as general.
            $extra = $rawTicket !== '' && $ticket->value !== strtolower($rawTicket) ? ['ticket' => $rawTicket] : [];
            foreach ($header as $i => $col) {
                if (! in_array($i, $map, true) && isset($row[$i]) && trim((string) $row[$i]) !== '') {
                    $extra[trim((string) $col)] = trim((string) $row[$i]);
                }
            }

            $data = [
                'name' => str()->limit($name, 120, ''),
                'phone' => $phone,
                'email' => $email,
                'ticket_type' => $ticket,
                'is_vip' => $vip,
                'source' => AttendeeSource::Import,
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
        // "Attendee #", "Order #": ID numbers, never the person's details. Kept as extra info.
        $header = array_map(fn ($col) => str_contains((string) $col, '#') ? null : $col, $header);
        foreach ($header as $i => $col) {
            $key = preg_replace('/[^a-z0-9]/', '', str()->lower((string) $col));
            foreach (self::ALIASES as $field => $aliases) {
                if (! isset($map[$field]) && in_array($key, $aliases, true)) {
                    $map[$field] = $i;
                    break;
                }
            }
        }
        foreach (self::LOOSE as $field => $words) {
            if (isset($map[$field]) || ($field === 'name' && isset($map['first_name']))) {
                continue;
            }
            foreach ($header as $i => $col) {
                $key = preg_replace('/[^a-z0-9]/', '', str()->lower((string) $col));
                if (! in_array($i, $map, true) && str()->contains($key, (array) $words) && ! str()->contains($key, self::NOT_THE_ATTENDEE)) {
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
