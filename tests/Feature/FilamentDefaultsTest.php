<?php

namespace Tests\Feature;

use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Tables\Filters\SelectFilter;
use Tests\TestCase;

/** Every picker and dropdown is Filament's own, set once in AppServiceProvider. */
class FilamentDefaultsTest extends TestCase
{
    public function test_pickers_and_dropdowns_are_not_the_browsers(): void
    {
        $this->assertFalse(DateTimePicker::make('starts_at')->isNative());
        $this->assertFalse(DatePicker::make('starts_on')->isNative());
        $this->assertFalse(Select::make('type')->options(['a' => 'A'])->isNative());
        $this->assertFalse(SelectFilter::make('status')->isNative());
    }

    public function test_date_format_depends_on_whether_there_is_a_time(): void
    {
        $this->assertSame('D j M Y, g:i A', DateTimePicker::make('starts_at')->getDisplayFormat());
        $this->assertSame('D j M Y', DatePicker::make('starts_on')->getDisplayFormat());
    }
}
