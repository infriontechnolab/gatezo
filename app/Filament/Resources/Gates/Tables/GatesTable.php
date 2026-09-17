<?php

namespace App\Filament\Resources\Gates\Tables;

use App\Models\Gate;
use App\Services\Qr;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class GatesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->searchable(),
                TextColumn::make('code')->badge()->color('gray')->fontFamily('mono'),
                IconColumn::make('is_entry')->label('Entry')->boolean(),
                TextColumn::make('checkins_count')->counts('checkins')->label('Scans')->sortable(),
            ])
            ->defaultSort('code')
            ->recordActions([
                Action::make('qr')->label('QR')->icon('heroicon-o-qr-code')
                    ->modalHeading(fn (Gate $g) => $g->name.' sign')
                    ->modalSubmitAction(false)->modalCancelActionLabel('Close')
                    ->modalContent(fn (Gate $g) => view('filament.qr-modal', [
                        'svg' => Qr::svg($g->signUrl(), 400), 'code' => $g->code, 'url' => $g->signUrl(),
                        'hint' => 'Volunteers scan this from the scanner app to go on duty here. Full A4 signs are in Print & reports.',
                    ])),
                EditAction::make(),
            ])
            ->toolbarActions([BulkActionGroup::make([DeleteBulkAction::make()])]);
    }
}
