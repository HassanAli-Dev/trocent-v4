<?php

namespace App\Filament\Resources;

use Filament\Schemas\Schema;
use Filament\Schemas\Components\Section;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use App\Filament\Resources\RateSheetResource\Pages\ListRateSheets;
use App\Filament\Resources\RateSheetResource\Pages\CreateRateSheet;
use App\Filament\Resources\RateSheetResource\Pages\EditRateSheet;
use App\Filament\Resources\RateSheetResource\Pages;
use App\Filament\Resources\RateSheetResource\RelationManagers;
use App\Models\RateSheet;
use Filament\Forms;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\Repeater;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class RateSheetResource extends Resource
{
    protected static ?string $model = RateSheet::class;

    protected static string | \UnitEnum | null $navigationGroup = 'Customers';

    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-chart-bar';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Rate Info')->schema([

                TextInput::make('destination_city')->label('Destination')->required(),
                TextInput::make('province')
                    ->maxLength(2)
                    ->minLength(2)
                    ->rule('regex:/^[A-Z]{2}$/')
                    ->placeholder('e.g., ON')
                    ->label('Province')
                    ->helperText('Enter 2-letter uppercase code'),
                TextInput::make('postal_code'),

                TextInput::make('rate_code'),
                TextInput::make('priority_sequence')->numeric()->default(0),
                Select::make('external')
                    ->options(['I' => 'Internal', 'E' => 'External'])
                    ->default('I'),





                TextInput::make('min_rate')->numeric()->label('Minimum Rate'),
                TextInput::make('ltl')->numeric()->label('LTL Rate'),
            ])->columns(3),

            Section::make('Rate Details')->schema([
                Repeater::make('meta')
                    ->label('Rate Bracket')
                    ->relationship()
                    ->schema([
                        TextInput::make('name')->label('Rate Bracket')->required(),
                        TextInput::make('value')->label('Rate'),
                    ])
                    ->defaultItems(1)
                    ->columns(2),
            ])->collapsed(true),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('rate_code')->sortable(),
                TextColumn::make('customer.full_name')->label('Customer')->searchable()->sortable(),
                TextColumn::make('type')->badge()->sortable(),
                IconColumn::make('skid_by_weight')->boolean(),
                TextColumn::make('destination_city')->label('Destination')->sortable()->searchable(),
                TextColumn::make('province')->sortable()->searchable(),

                TextColumn::make('min_rate')->label('Min Rate')->money('usd')->sortable(),
                TextColumn::make('ltl')->label('LTL')->money('usd')->sortable(),
                TextColumn::make('meta')
                    ->label('Rate Brackets')
                    ->html()
                    ->getStateUsing(
                        fn($record) =>
                        $record->meta->count()
                            ? '<div class="inline-block  p-2">' .
                            $record->meta
                            ->map(fn($m) => "<span class='inline-block text-center me-4 border border-gray-200 rounded p-2'><b class=text-sm''>" .
                                str_replace(['_', '-'], ' ', ucwords($m->name)) .
                                "</b><br><span class='text-gray-250 text-xs'>{$m->value}</span></span>")
                            ->implode('') .
                            '</div>'
                            : '—'
                    )
            ])
            ->filters([
                SelectFilter::make('customer_id')
                    ->label('Customer')
                    ->relationship('customer', 'full_name'),

                SelectFilter::make('type')
                    ->label('Rate Sheet Type')
                    ->options([
                        'skid' => 'Skid Based',
                        'weight' => 'Weight Based',
                    ]),

                TernaryFilter::make('skid_by_weight')
                    ->label('Skid → Weight'),
            ])
            ->recordActions([])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListRateSheets::route('/'),
            'create' => CreateRateSheet::route('/create'),
            'edit' => EditRateSheet::route('/{record}/edit'),
        ];
    }
}
