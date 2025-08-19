<?php

namespace App\Filament\Resources;

use Filament\Schemas\Schema;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Grid;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Select;
use Filament\Tables\Columns\TextColumn;
use Filament\Actions\EditAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use App\Filament\Resources\TrailerResource\Pages\ListTrailers;
use App\Filament\Resources\TrailerResource\Pages\CreateTrailer;
use App\Filament\Resources\TrailerResource\Pages\EditTrailer;
use App\Filament\Resources\TrailerResource\Pages;
use App\Filament\Resources\TrailerResource\RelationManagers;
use App\Models\Trailer;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class TrailerResource extends Resource
{
    protected static ?string $model = Trailer::class;

    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-rectangle-stack';
    protected static string | \UnitEnum | null $navigationGroup = 'Fleet Management';
    protected static bool $shouldRegisterNavigation = false;

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Trailer Information')
                    ->extraAttributes([
                        'class' => 'section-yellow-border',
                    ])
                    ->collapsible()
                    ->schema([
                        Grid::make(2)->schema([
                            TextInput::make('title')->required()->autofocus(),
                            TextInput::make('leasing_company'),
                            TextInput::make('trailer_number'),
                            TextInput::make('plate_number'),
                            Select::make('reefer')
                                ->options(['Yes' => 'Yes', 'No' => 'No'])
                                ->default('No'),
                            Select::make('tailgate')
                                ->options(['Yes' => 'Yes', 'No' => 'No'])
                                ->default('No'),
                            Select::make('door_type')
                                ->options([
                                    'Barn door' => 'Barn door',
                                    'Rollup door' => 'Rollup door',
                                ]),
                        ]),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('title')->searchable()->sortable(),
                TextColumn::make('leasing_company')->label('Leasing Co.'),
                TextColumn::make('plate_number'),
                TextColumn::make('reefer'),
                TextColumn::make('tailgate'),
                TextColumn::make('door_type'),
            ])
            ->filters([])
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListTrailers::route('/'),
            'create' => CreateTrailer::route('/create'),
            'edit' => EditTrailer::route('/{record}/edit'),
        ];
    }
}
