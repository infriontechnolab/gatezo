<?php

namespace App\Services;

use App\Enums\AttendeeSource;
use App\Enums\TicketType;
use App\Models\Event;
use App\Rules\PersonName;
use App\Support\Phone;

/**
 * CSV → attendees + passes, from whatever system the organizer registered people in. Every
 * system exports different columns, so the organizer confirms a mapping (Gatezo field → CSV
 * header) before importing; guess() pre-fills it. Unmapped columns are kept in `extra`, and a
 * row that matches someone already imported (same ID from the other system, phone or email)
 * updates them instead of creating a second person.
 */
final class AttendeeImporter
{
    /** Gatezo fields a CSV column can map to, with the label shown on the mapping screen. */
    public const FIELDS = [
        'name' => 'Full name',
        'first_name' => 'First name',
        'last_name' => 'Last name',
        'phone' => 'Phone',
        'email' => 'Email',
        'ticket_type' => 'Ticket type',
        'is_vip' => 'VIP',
        'status' => 'Status',
        'external_id' => 'Attendee ID in that system',
    ];

    /**
     * Header aliases → field, compared lowercase with non-alphanumerics stripped (a "#" reads as
     * "no"). Covers hand-made sheets and exports from Eventbrite (First Name, Cell Phone,
     * Attendee #, Attendee Status), Luma (first_name, phone_number, api_id, approval_status)
     * and Google Forms.
     */
    private const ALIASES = [
        'external_id' => ['attendeeno', 'attendeeid', 'apiid', 'ticketid', 'ticketno', 'ticketnumber', 'registrationid', 'registrationno', 'participantid', 'guestid'],
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

    /** Status values ticked to skip by default: these people have no place at the gate. */
    private const SKIP_STATUSES = ['refunded', 'cancelled', 'canceled', 'declined', 'rejected', 'notattending', 'deleted', 'waitlist', 'waitlisted', 'transferred', 'pendingapproval', 'invited', 'paymentfailed', 'failed', 'expired'];

    /**
     * Header and rows of a CSV. Strips the UTF-8 BOM Excel adds and skips blank lines.
     *
     * @return array{header: list<string>, rows: list<list<string>>}
     */
    public static function read(string $csvPath, ?int $limit = null): array
    {
        $handle = @fopen($csvPath, 'r');
        if ($handle === false) {
            return ['header' => [], 'rows' => []];
        }
        $first = fgets($handle);
        $header = $first === false ? [] : array_map(fn ($c) => trim((string) $c), str_getcsv(preg_replace('/^\xEF\xBB\xBF/', '', $first)));
        $rows = [];
        while (($limit === null || count($rows) < $limit) && ($row = fgetcsv($handle)) !== false) {
            if (count($row) === 1 && trim((string) $row[0]) === '') {
                continue;
            }
            $rows[] = array_map(fn ($c) => trim((string) $c), $row);
        }
        fclose($handle);

        return ['header' => $header, 'rows' => $rows];
    }

    /**
     * Best guess of which header holds each field.
     *
     * @param  list<string>  $header
     * @return array<string, string> field → header
     */
    public static function guess(array $header): array
    {
        $key = fn (string $col) => preg_replace('/[^a-z0-9]/', '', str_replace('#', 'no', str()->lower($col)));
        $map = [];
        foreach ($header as $col) {
            foreach (self::ALIASES as $field => $aliases) {
                if (! isset($map[$field]) && ! in_array($col, $map, true) && in_array($key($col), $aliases, true)) {
                    // "Order #" and friends are ID numbers, never a person's name or phone.
                    if ($field !== 'external_id' && str_contains($col, '#')) {
                        continue;
                    }
                    $map[$field] = $col;
                    break;
                }
            }
        }
        foreach (self::LOOSE as $field => $words) {
            if (isset($map[$field]) || ($field === 'name' && isset($map['first_name']))) {
                continue;
            }
            foreach ($header as $col) {
                if (! in_array($col, $map, true) && ! str_contains($col, '#') && str()->contains($key($col), (array) $words) && ! str()->contains($key($col), self::NOT_THE_ATTENDEE)) {
                    $map[$field] = $col;
                    break;
                }
            }
        }

        return $map;
    }

    /**
     * Distinct values in a column with how often each appears, most common first.
     *
     * @param  list<string>  $header
     * @param  list<list<string>>  $rows
     * @return array<string, int>
     */
    public static function values(array $header, array $rows, string $column): array
    {
        $i = array_search($column, $header, true);
        if ($i === false) {
            return [];
        }
        $counts = [];
        foreach ($rows as $row) {
            $value = $row[$i] ?? '';
            $counts[$value] = ($counts[$value] ?? 0) + 1;
        }
        arsort($counts);

        return $counts;
    }

    /**
     * Which of these status values to skip unless the organizer says otherwise: the ones they
     * skipped last time, and cancellation words for values they haven't seen before.
     *
     * @param  list<string>  $values
     * @param  array{skip?: list<string>, seen?: list<string>}|null  $saved
     * @return list<string>
     */
    public static function defaultSkips(array $values, ?array $saved = null): array
    {
        return array_values(array_filter($values, fn (string $v) => in_array($v, $saved['seen'] ?? [], true)
            ? in_array($v, $saved['skip'] ?? [], true)
            : in_array(preg_replace('/[^a-z0-9]/', '', str()->lower($v)), self::SKIP_STATUSES, true)));
    }

    /**
     * @param  array<string, ?string>|null  $mapping  field → header; null = guess
     * @param  list<string>|null  $skip  status values to leave out; null = the default skips
     * @return array{created: int, updated: int, skipped: int, email_only: int, errors: list<string>}
     */
    public static function import(Event $event, string $csvPath, ?array $mapping = null, ?array $skip = null): array
    {
        $stats = ['created' => 0, 'updated' => 0, 'skipped' => 0, 'email_only' => 0, 'errors' => []];
        ['header' => $header, 'rows' => $rows] = self::read($csvPath);
        if ($header === []) {
            $stats['errors'][] = 'The file is empty.';

            return $stats;
        }

        $map = [];
        foreach ($mapping ?? self::guess($header) as $field => $col) {
            if ($col !== null && $col !== '' && isset(self::FIELDS[$field]) && ($i = array_search($col, $header, true)) !== false) {
                $map[$field] = $i;
            }
        }
        if (! isset($map['name']) && ! isset($map['first_name'])) {
            $stats['errors'][] = 'No name column found. Expected headers like: name (or first name and last name), phone, email, ticket, vip.';

            return $stats;
        }
        $skip ??= isset($map['status']) ? self::defaultSkips(array_map('strval', array_keys(self::values($header, $rows, $header[$map['status']])))) : [];

        foreach ($rows as $n => $row) {
            $line = $n + 2;
            $get = fn (string $field) => isset($map[$field]) ? ($row[$map[$field]] ?? '') : '';

            if (isset($map['status']) && in_array($get('status'), $skip, true)) {
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

            $phone = Phone::normalise($get('phone') ?: null);
            if ($phone !== null && (strlen($phone) < 8 || strlen($phone) > 15)) {
                $stats['errors'][] = "Line {$line}: phone \"{$get('phone')}\" ignored (not a full number).";
                $phone = null;
            }
            $rawEmail = $get('email');
            $email = filter_var($rawEmail, FILTER_VALIDATE_EMAIL) ? str()->lower($rawEmail) : null;
            if ($rawEmail !== '' && $email === null) {
                $stats['errors'][] = "Line {$line}: email \"{$rawEmail}\" ignored (not a valid address).";
            }
            $externalId = str()->limit($get('external_id'), 100, '') ?: null;
            $rawTicket = $get('ticket_type');
            $ticket = TicketType::fromImport($rawTicket);
            $vip = in_array(str()->lower($get('is_vip')), ['1', 'y', 'yes', 'true', 'vip'], true) || $ticket === TicketType::Vip;

            // Everything we didn't map is kept, so nothing from their sheet is lost, including a
            // ticket label of their own ("Gold") that is stored as general.
            $extra = $rawTicket !== '' && $ticket->value !== strtolower($rawTicket) ? ['ticket' => $rawTicket] : [];
            foreach ($header as $i => $col) {
                if (! in_array($i, $map, true) && ($row[$i] ?? '') !== '') {
                    $extra[$col] = $row[$i];
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
                'external_id' => $externalId,
            ];

            // Same person as an earlier import or registration: their ID from the other system
            // first (survives a changed phone), then phone, then email.
            $attendee = ($externalId ? $event->attendees()->where('external_id', $externalId)->first() : null)
                ?? ($phone ? $event->attendees()->where('phone', $phone)->first() : null)
                ?? ($email ? $event->attendees()->where('email', $email)->first() : null);
            if ($attendee) {
                $attendee->update(array_filter($data, fn ($v) => $v !== null)); // a blank cell never wipes what we had
                $stats['updated']++;
            } else {
                $attendee = $event->attendees()->create($data);
                $stats['created']++;
            }
            $attendee->pass ?? $attendee->pass()->create(['event_id' => $event->id]);
            if ($attendee->phone === null && $attendee->email !== null) {
                $stats['email_only']++;
            }
        }

        return $stats;
    }
}
