<?php

use App\Enums\TicketType;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// attendees.ticket_type becomes a TicketType cast. Rows imported with a label of their own
// ("Gold", "Early bird") become general, and the original label moves to extra.ticket so
// nothing from the organizer's sheet is lost. down() puts those labels back.
return new class extends Migration
{
    public function up(): void
    {
        $values = array_column(TicketType::cases(), 'value');

        DB::table('attendees')->orderBy('id')->select(['id', 'ticket_type', 'extra'])->chunkById(500, function ($rows) use ($values) {
            foreach ($rows as $row) {
                $raw = (string) $row->ticket_type;
                $lower = strtolower(trim($raw));
                if ($raw === $lower && in_array($lower, $values, true)) {
                    continue;
                }
                $update = ['ticket_type' => in_array($lower, $values, true) ? $lower : TicketType::General->value];
                if (! in_array($lower, $values, true) && $raw !== '') {
                    $update['extra'] = json_encode(['ticket' => $raw] + (json_decode((string) $row->extra, true) ?: []));
                }
                DB::table('attendees')->where('id', $row->id)->update($update);
            }
        });
    }

    public function down(): void
    {
        DB::table('attendees')->whereNotNull('extra')->orderBy('id')->select(['id', 'extra'])->chunkById(500, function ($rows) {
            foreach ($rows as $row) {
                $extra = json_decode((string) $row->extra, true) ?: [];
                if (isset($extra['ticket'])) {
                    $label = $extra['ticket'];
                    unset($extra['ticket']);
                    DB::table('attendees')->where('id', $row->id)->update(['ticket_type' => $label, 'extra' => $extra ? json_encode($extra) : null]);
                }
            }
        });
    }
};
