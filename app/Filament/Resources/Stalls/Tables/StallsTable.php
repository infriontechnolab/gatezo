<?php

namespace App\Filament\Resources\Stalls\Tables;

use App\Models\Stall;
use App\Services\Qr;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class StallsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->searchable(),
                TextColumn::make('location'),
                TextColumn::make('view_count')->label('Scans')->sortable(),
                TextColumn::make('leads_count')->counts('leads')->label('Leads')->sortable(),
            ])
            ->recordActions([
                Action::make('qr')->label('QR')->icon('heroicon-o-qr-code')
                    ->modalHeading(fn (Stall $s) => $s->name.' card')
                    ->modalSubmitAction(false)->modalCancelActionLabel('Close')
                    ->modalContent(fn (Stall $s) => view('filament.qr-modal', [
                        'svg' => Qr::svg(route('stall.show', $s), 400), 'code' => strtoupper($s->public_code), 'url' => route('stall.show', $s),
                        'hint' => 'Attendees scan this for the menu and offers. Printable cards are in Print & reports.',
                    ])),
                Action::make('vendor_link')->label('Vendor link')->icon('heroicon-o-link')
                    ->modalHeading(fn (Stall $s) => 'Private link for '.$s->name)
                    ->modalDescription('Send this to the stall owner. It shows their scans and leads and lets them capture leads by scanning passes. Anyone with the link can see the leads.')
                    ->modalSubmitAction(false)->modalCancelActionLabel('Close')
                    ->modalContent(fn (Stall $s) => view('filament.qr-modal', [
                        'svg' => Qr::svg($s->vendorUrl(), 300), 'code' => '', 'url' => $s->vendorUrl(), 'hint' => 'Copy the link or let the vendor scan this QR.',
                    ]))
                    ->extraModalFooterActions([
                        Action::make('regenerate')->label('Regenerate link')->color('danger')->icon('heroicon-o-arrow-path')
                            ->requiresConfirmation()
                            ->modalDescription('The old link stops working immediately. Send the vendor the new one.')
                            ->action(fn (Stall $s) => $s->regenerateVendorLink())
                            ->cancelParentActions(),
                    ]),
                EditAction::make(),
            ])
            ->toolbarActions([BulkActionGroup::make([DeleteBulkAction::make()])]);
    }
}
