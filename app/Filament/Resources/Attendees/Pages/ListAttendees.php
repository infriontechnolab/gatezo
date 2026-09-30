<?php

namespace App\Filament\Resources\Attendees\Pages;

use App\Filament\Resources\Attendees\AttendeeResource;
use App\Models\Event;
use App\Services\AttendeeImporter;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Facades\Filament;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Fieldset;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Support\Icons\Heroicon;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;

class ListAttendees extends ListRecords
{
    protected static string $resource = AttendeeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('import')->label('Import CSV')->icon(Heroicon::OutlinedArrowUpTray)->color('gray')
                ->modalHeading('Import attendees from CSV')
                ->modalDescription('Any export works: Eventbrite, Luma, Google Forms, Townscript, a spreadsheet. Upload it, check which column is which, and import. Columns you don\'t map are kept as extra info. People already here (same ID, phone or email) are updated, not duplicated. Everyone gets a pass.')
                ->modalSubmitActionLabel('Import')
                ->schema([
                    FileUpload::make('file')->label('CSV file')->required()->storeFiles(false)
                        ->acceptedFileTypes(['text/csv', 'text/plain', 'application/vnd.ms-excel', '.csv'])->maxSize(5120)
                        ->live()
                        ->afterStateUpdated(function (Set $set, $state): void {
                            if (! $state instanceof TemporaryUploadedFile) {
                                return;
                            }
                            // Offer last time's mapping where this file has the same headers; guess the rest.
                            $csv = AttendeeImporter::read($state->getRealPath());
                            $saved = Filament::getTenant()->import_mapping;
                            $map = AttendeeImporter::guess($csv['header']);
                            foreach ($saved['map'] ?? [] as $field => $col) {
                                if (in_array($col, $csv['header'], true)) {
                                    $map[$field] = $col;
                                }
                            }
                            $set('map', array_merge(array_fill_keys(array_keys(AttendeeImporter::FIELDS), null), $map));
                            $set('skip', isset($map['status']) ? AttendeeImporter::defaultSkips(self::statusValues($csv, $map['status']), $saved) : []);
                        }),
                    Fieldset::make('Which column is which?')->columns(2)
                        ->visible(fn (Get $get): bool => $get('file') instanceof TemporaryUploadedFile)
                        ->schema(collect(AttendeeImporter::FIELDS)->map(fn (string $label, string $field) => Select::make("map.{$field}")->label($label)
                            ->options(fn (Get $get) => collect(self::csv($get)['header'] ?? [])->mapWithKeys(fn ($h) => [$h => $h])->all())
                            ->placeholder('Not in this file')
                            ->live()
                            ->required(fn (Get $get): bool => $field === 'name' && blank($get('map.first_name')))
                            ->validationMessages(['required' => 'Pick the name column, or the first name column.'])
                            ->helperText(fn (Get $get, ?string $state) => $state ? self::samples(self::csv($get), $state) : null)
                            ->afterStateUpdated(function (Set $set, Get $get, ?string $state) use ($field): void {
                                if ($field === 'status') {
                                    $set('skip', $state ? AttendeeImporter::defaultSkips(self::statusValues(self::csv($get), $state), Filament::getTenant()->import_mapping) : []);
                                }
                            }))->values()->all()),
                    CheckboxList::make('skip')->label('Leave out people with this status')
                        ->helperText('Ticked values are not imported. Cancellation words are ticked for you.')
                        ->visible(fn (Get $get): bool => $get('file') instanceof TemporaryUploadedFile && filled($get('map.status')))
                        ->options(fn (Get $get) => collect(AttendeeImporter::values(self::csv($get)['header'] ?? [], self::csv($get)['rows'] ?? [], (string) $get('map.status')))
                            ->mapWithKeys(fn (int $count, $value) => [(string) $value => ($value === '' ? '(blank)' : $value)." ({$count})"])->all())
                        ->columns(2),
                ])
                ->action(function (array $data): void {
                    /** @var Event $event */
                    $event = Filament::getTenant();
                    $path = $data['file']->getRealPath();
                    $skip = array_values(array_map('strval', $data['skip'] ?? []));
                    $stats = AttendeeImporter::import($event, $path, $data['map'], $skip);
                    $event->update(['import_mapping' => [
                        'map' => array_filter($data['map']),
                        'skip' => $skip,
                        'seen' => filled($data['map']['status'] ?? null) ? self::statusValues(AttendeeImporter::read($path), $data['map']['status']) : [],
                    ]]);

                    $summary = "{$stats['created']} added, {$stats['updated']} updated, {$stats['skipped']} skipped.";
                    if ($stats['created'] + $stats['updated'] === 0) {
                        Notification::make()->title('Nothing imported')->body(implode(' ', array_slice($stats['errors'], 0, 3)) ?: $summary)->danger()->send();

                        return;
                    }
                    Notification::make()->title('Import finished')->body($summary.($stats['errors'] ? ' '.count($stats['errors']).' row(s) had problems.' : ''))
                        ->{$stats['errors'] ? 'warning' : 'success'}()->send();
                    if ($stats['email_only'] && ! $event->ask_email) {
                        Notification::make()->title("{$stats['email_only']} attendees have an email but no phone")
                            ->body('Turn on "Ask for email" in Settings so they can find their pass on the registration page with their email.')
                            ->warning()->persistent()->send();
                    }
                }),
            Action::make('csv')->label('Export CSV')->icon(Heroicon::OutlinedArrowDownTray)->color('gray')
                ->url(fn () => route('print.attendees.csv', Filament::getTenant()), shouldOpenInNewTab: true),
            CreateAction::make(),
        ];
    }

    /** @return array{header: list<string>, rows: list<list<string>>}|null */
    private static function csv(Get $get): ?array
    {
        $file = $get('file');
        if (! $file instanceof TemporaryUploadedFile) {
            return null;
        }
        static $read = []; // every mapping field asks on each render: parse the file once per request

        return $read[$file->getRealPath()] ??= AttendeeImporter::read($file->getRealPath());
    }

    /**
     * @param  array{header: list<string>, rows: list<list<string>>}|null  $csv
     * @return list<string>
     */
    private static function statusValues(?array $csv, string $column): array
    {
        return array_map('strval', array_keys(AttendeeImporter::values($csv['header'] ?? [], $csv['rows'] ?? [], $column)));
    }

    /** @param  array{header: list<string>, rows: list<list<string>>}|null  $csv */
    private static function samples(?array $csv, string $column): string
    {
        $i = array_search($column, $csv['header'] ?? [], true);
        $values = $i === false ? [] : collect($csv['rows'])->pluck($i)->filter()->take(3)->all();

        return $values ? 'e.g. '.implode(' · ', $values) : 'Empty in the first rows';
    }
}
