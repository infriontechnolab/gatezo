<?php

namespace App\Filament\Ops\Resources\Plans;

use App\Filament\Ops\Resources\Plans\Pages\ManagePlans;
use App\Models\SubscriptionPlan;
use App\Models\User;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

/**
 * The plan catalogue organizers choose from. Plans are never deleted, only switched off:
 * accounts and past periods keep pointing at them. The Free plan can't be switched off.
 */
class PlanResource extends Resource
{
    protected static ?string $model = SubscriptionPlan::class;

    protected static ?string $navigationLabel = 'Plans';

    protected static ?string $modelLabel = 'plan';

    protected static ?int $navigationSort = 4;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    public static function form(Schema $schema): Schema
    {
        $isFree = fn (?SubscriptionPlan $record) => $record?->isFree() ?? false;

        return $schema->components([
            Grid::make(2)->schema([
                TextInput::make('name')->required()->maxLength(60)->placeholder('Starter'),
                TextInput::make('slug')->label('Code')->required()->maxLength(32)->alphaDash()->placeholder('starter')->unique()
                    ->disabledOn('edit')->helperText('Fixed once created; accounts and periods point at it.'),
            ]),
            TextInput::make('description')->maxLength(255)->placeholder('For a society fair or a mid-size expo'),
            Section::make('Caps')->description('Leave empty for unlimited.')->columns(3)->schema([
                TextInput::make('max_events')->label('Events')->numeric()->minValue(1)->placeholder('Unlimited'),
                TextInput::make('max_attendees')->label('Registrations per event')->numeric()->minValue(1)->placeholder('Unlimited'),
                TextInput::make('max_team')->label('Organizers per event')->numeric()->minValue(1)->placeholder('Unlimited'),
            ]),
            Section::make('Price')->description('Leave a cycle empty to not sell it.')->columns(2)->hidden($isFree)->schema([
                TextInput::make('price_monthly')->label('Monthly')->prefix('₹')->numeric()->minValue(0)->placeholder('Not sold monthly'),
                TextInput::make('price_yearly')->label('Yearly')->prefix('₹')->numeric()->minValue(0)->placeholder('Not sold yearly'),
            ]),
            Grid::make(3)->schema([
                Toggle::make('is_active')->label('On sale')->default(true)->disabled($isFree)
                    ->helperText('Off hides it from organizers. Existing periods keep running.'),
                Toggle::make('is_featured')->label('Highlight')->hidden($isFree),
                TextInput::make('sort')->label('Order')->numeric()->default(0)->placeholder('10')
                    ->helperText('Left to right. Higher also wins if two periods overlap.'),
            ]),
        ])->columns(1);
    }

    public static function table(Table $table): Table
    {
        $cap = fn ($v) => $v === null ? 'Unlimited' : number_format($v);

        return $table
            ->defaultSort('sort')
            ->reorderable('sort')
            ->columns([
                TextColumn::make('name')->description(fn (SubscriptionPlan $p) => $p->slug)->weight('bold'),
                TextColumn::make('max_attendees')->label('Registrations')->state(fn (SubscriptionPlan $p) => $cap($p->max_attendees)),
                TextColumn::make('max_events')->label('Events')->state(fn (SubscriptionPlan $p) => $cap($p->max_events)),
                TextColumn::make('max_team')->label('Organizers')->state(fn (SubscriptionPlan $p) => $cap($p->max_team)),
                TextColumn::make('price_monthly')->label('Monthly')->placeholder('—')->formatStateUsing(fn ($state) => '₹'.number_format($state)),
                TextColumn::make('price_yearly')->label('Yearly')->placeholder('—')->formatStateUsing(fn ($state) => '₹'.number_format($state)),
                TextColumn::make('on_plan')->label('On it today')
                    ->state(fn (SubscriptionPlan $p) => $p->isFree()
                        ? null
                        : number_format(User::organizers()->where(fn ($q) => $q->where('plan', $p->slug)->orWhereHas('subscriptions', fn ($s) => $s->active()->where('plan', $p->slug)))->count()))
                    ->placeholder('—'),
                IconColumn::make('is_active')->label('On sale')->boolean(),
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->emptyStateHeading('No plans');
    }

    public static function getPages(): array
    {
        return [
            'index' => ManagePlans::route('/'),
        ];
    }
}
