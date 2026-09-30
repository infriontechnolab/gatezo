<?php

namespace App\Filament\Resources\Shifts\Actions;

use App\Filament\Resources\Shifts\ShiftResource;
use App\Models\Event;
use App\Support\VolunteerList;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Support\Icons\Heroicon;

/**
 * Paste the volunteer list, get a roster: one shift per person at the chosen post and time,
 * each with a personal scanner link. Used on the Volunteers page and on Shifts.
 */
class AddVolunteersAction extends Action
{
    public static function getDefaultName(): ?string
    {
        return 'addVolunteers';
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->label('Add volunteers')
            ->icon(Heroicon::OutlinedUserPlus)
            ->modalHeading('Add volunteers')
            ->modalDescription('Paste your list, one person per line, straight from WhatsApp or Excel. Phone numbers are optional, but with one their link opens their WhatsApp chat.')
            ->modalSubmitActionLabel('Add to roster')
            ->schema(fn (): array => [
                Textarea::make('people')->label('Volunteers')->required()->rows(8)->live(onBlur: true)
                    ->placeholder("Ravi Patel, 98240 17351\nMeera Shah 9824017352\nNirav Desai")
                    ->helperText(function (?string $state): ?string {
                        if (blank($state)) {
                            return null;
                        }
                        ['people' => $people, 'errors' => $errors] = VolunteerList::parse($state);

                        return count($people).' '.str('volunteer')->plural(count($people)).' ready, '
                            .collect($people)->whereNotNull('phone')->count().' with a phone.'
                            .($errors ? ' '.implode(' ', array_slice($errors, 0, 3)) : '');
                    }),
                Select::make('gate_id')->label('Post')->placeholder('Anywhere / floating')
                    ->options(fn () => Filament::getTenant()->gates()->orderBy('code')->pluck('name', 'id')),
                Grid::make(2)->schema([
                    DateTimePicker::make('starts_at')->label('From')->seconds(false)->default(fn () => Filament::getTenant()->starts_at),
                    DateTimePicker::make('ends_at')->label('To')->seconds(false)->afterOrEqual('starts_at')->default(fn () => Filament::getTenant()->ends_at),
                ]),
                TextInput::make('label')->maxLength(120)->placeholder('Registration desk, parking, prasad counter…'),
            ])
            ->action(function (array $data, Action $action): void {
                /** @var Event $event */
                $event = Filament::getTenant();
                ['people' => $people, 'errors' => $errors] = VolunteerList::parse($data['people']);
                if ($people === []) {
                    Notification::make()->title('No names found')->body(implode(' ', array_slice($errors, 0, 3)))->danger()->send();
                    $action->halt();
                }

                $added = 0;
                $already = 0;
                foreach ($people as $person) {
                    // Pasting the same list twice must not double the roster.
                    $existing = $event->shifts()->where('volunteer_name', $person['name'])
                        ->where('gate_id', $data['gate_id'] ?? null)->where('starts_at', $data['starts_at'] ?? null)->first();
                    if ($existing) {
                        $existing->phone ??= $person['phone'];
                        $existing->save();
                        $already++;

                        continue;
                    }
                    $event->shifts()->create([
                        'volunteer_name' => $person['name'],
                        'phone' => $person['phone'],
                        'gate_id' => $data['gate_id'] ?? null,
                        'starts_at' => $data['starts_at'] ?? null,
                        'ends_at' => $data['ends_at'] ?? null,
                        'label' => $data['label'] ?? null,
                    ]);
                    $added++;
                }

                Notification::make()
                    ->title("{$added} ".str('volunteer')->plural($added).' added'.($already ? ", {$already} already on the roster" : ''))
                    ->body('Send each their personal link from Shifts: one tap on WhatsApp for those with a phone.'.($errors ? ' '.count($errors).' line(s) had problems.' : ''))
                    ->actions([Action::make('shifts')->label('Open Shifts')->url(ShiftResource::getUrl('index'))])
                    ->success()
                    ->send();
            });
    }
}
