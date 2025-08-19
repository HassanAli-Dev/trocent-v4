<?php

namespace App\Filament\Resources;

use Filament\Schemas\Schema;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Grid;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\FileUpload;
use Filament\Tables\Columns\TextColumn;
use Filament\Actions\EditAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use App\Filament\Resources\VehicleResource\Pages\ListVehicles;
use App\Filament\Resources\VehicleResource\Pages\CreateVehicle;
use App\Filament\Resources\VehicleResource\Pages\EditVehicle;
use App\Filament\Resources\VehicleResource\Pages;
use App\Filament\Resources\VehicleResource\RelationManagers;
use App\Models\Vehicle;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class VehicleResource extends Resource
{
    protected static ?string $model = Vehicle::class;

    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-rectangle-stack';
    protected static bool $shouldRegisterNavigation = false;
    protected static string | \UnitEnum | null $navigationGroup = 'Fleet Management';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Basic Info')
                ->extraAttributes([
                    'class' => 'section-yellow-border',
                ])
                ->collapsible()
                ->schema([
                    Grid::make(3)->schema([
                        TextInput::make('title')->required()->maxLength(255)->autofocus(),
                        TextInput::make('serial_number'),
                        TextInput::make('make'),
                        TextInput::make('model'),
                        TextInput::make('year'),
                        TextInput::make('color'),
                    ]),
                ]),

            Section::make('Plate & Equipment')
                ->extraAttributes([
                    'class' => 'section-yellow-border',
                ])
                ->collapsible()
                ->schema([
                    Grid::make(4)->schema([
                        TextInput::make('plate_number'),
                        DatePicker::make('plate_expiry'),
                        Select::make('reefer')
                            ->options(['Yes' => 'Yes', 'No' => 'No'])
                            ->default('No'),
                        Select::make('tailgate')
                            ->options(['Yes' => 'Yes', 'No' => 'No'])
                            ->default('No'),
                    ]),
                ]),

            Section::make('Capacity')
                ->extraAttributes([
                    'class' => 'section-yellow-border',
                ])
                ->collapsible()
                ->schema([
                    Grid::make(3)->schema([
                        TextInput::make('max_weight')->numeric()->rules(['nullable', 'numeric']),
                        TextInput::make('max_length')->numeric()->rules(['nullable', 'numeric']),
                        TextInput::make('max_width')->numeric()->rules(['nullable', 'numeric']),
                        TextInput::make('max_height')->numeric()->rules(['nullable', 'numeric']),
                        TextInput::make('max_volume')->numeric()->rules(['nullable', 'numeric']),
                    ]),
                ]),

            Section::make('Documents')
                ->extraAttributes([
                    'class' => 'section-yellow-border',
                ])
                ->collapsible()
                ->schema([
                    Grid::make(2)->schema([
                        FileUpload::make('truck_inspection_file')
                            ->label('Truck Inspection File')
                            ->downloadable()
                            ->directory('vehicle-inspections'),

                        DatePicker::make('truck_inspection_date'),

                        FileUpload::make('registration_file')
                            ->label('Registration File')
                            ->downloadable()
                            ->directory('vehicle-registrations'),

                        DatePicker::make('registration_date'),
                    ]),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('title')->sortable()->searchable(),
                TextColumn::make('serial_number')->sortable()->searchable(),
                TextColumn::make('plate_number')->sortable()->searchable(),
                TextColumn::make('plate_expiry')->date()->sortable(),
                TextColumn::make('reefer')->sortable(),
                TextColumn::make('tailgate')->sortable(),
                TextColumn::make('max_weight')->sortable(),
                TextColumn::make('max_volume')->sortable(),
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
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListVehicles::route('/'),
            'create' => CreateVehicle::route('/create'),
            'edit' => EditVehicle::route('/{record}/edit'),
        ];
    }
}
