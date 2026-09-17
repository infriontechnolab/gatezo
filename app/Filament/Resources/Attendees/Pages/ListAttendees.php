<?php

namespace App\Filament\Resources\Attendees\Pages;

use App\Filament\Resources\Attendees\AttendeeResource;
use App\Services\AttendeeImporter;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Facades\Filament;
use Filament\Forms\Components\FileUpload;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Text;
use Illuminate\Support\Facades\Storage;

class ListAttendees extends ListRecords
{
    protected static string $resource = AttendeeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('import')->label('Import CSV')->icon('heroicon-o-arrow-up-tray')->color('gray')
                ->modalHeading('Import attendees from CSV')
                ->modalDescription('Columns are matched by name: name (required), phone, email, ticket, vip. Anything else is kept as extra info. Rows whose phone already exists are updated, not duplicated. Every imported attendee gets a pass.')
                ->modalSubmitActionLabel('Import')
                ->schema([
                    Text::make('Example: name,phone,email,ticket\nAarti Shah,9800000001,aarti@example.com,vip')
                        ->extraAttributes(['class' => 'font-mono text-xs whitespace-pre text-gray-500']),
                    FileUpload::make('file')->label('CSV file')->required()
                        ->acceptedFileTypes(['text/csv', 'text/plain', 'application/vnd.ms-excel', '.csv'])
                        ->disk('local')->directory('imports')->visibility('private')->maxSize(5120),
                ])
                ->action(function (array $data): void {
                    $path = Storage::disk('local')->path($data['file']);
                    $stats = AttendeeImporter::import(Filament::getTenant(), $path);
                    Storage::disk('local')->delete($data['file']);

                    $summary = "{$stats['created']} added, {$stats['updated']} updated, {$stats['skipped']} skipped.";
                    if ($stats['created'] + $stats['updated'] === 0) {
                        Notification::make()->title('Nothing imported')->body(implode(' ', array_slice($stats['errors'], 0, 3)) ?: $summary)->danger()->send();

                        return;
                    }
                    Notification::make()->title('Import finished')->body($summary.($stats['errors'] ? ' '.count($stats['errors']).' row(s) had problems.' : ''))
                        ->{$stats['errors'] ? 'warning' : 'success'}()->send();
                }),
            Action::make('csv')->label('Export CSV')->icon('heroicon-o-arrow-down-tray')->color('gray')
                ->url(fn () => route('print.attendees.csv', Filament::getTenant()), shouldOpenInNewTab: true),
            CreateAction::make(),
        ];
    }
}
