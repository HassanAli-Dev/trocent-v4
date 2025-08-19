<?php

namespace App\Filament\Resources\CompanyResource\RelationManagers;

use Filament\Schemas\Schema;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Grid;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\FileUpload;
use Filament\Tables\Columns\TextColumn;
use Filament\Actions\CreateAction;
use Filament\Actions\EditAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Forms;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class VehiclesRelationManager extends RelationManager
{
    protected static string $relationship = 'vehicles';

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Basic Info')
                ->collapsible()
                ->extraAttributes([
                    'class' => 'section-yellow-border',
                ])
                ->schema([
                    Grid::make(2)->schema([
                        TextInput::make('title')->required()->autofocus(),
                        TextInput::make('serial_number'),
                        TextInput::make('make'),
                        TextInput::make('model'),
                        TextInput::make('year'),
                        TextInput::make('color'),
                    ]),
                ]),

            Section::make('Plate & Equipment')
                ->collapsible()
                ->extraAttributes([
                    'class' => 'section-yellow-border',
                ])
                ->schema([
                    Grid::make(2)->schema([
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
                ->collapsible()
                ->extraAttributes([
                    'class' => 'section-yellow-border',
                ])
                ->schema([
                    Grid::make(2)->schema([
                        TextInput::make('max_weight'),
                        TextInput::make('max_length'),
                        TextInput::make('max_width'),
                        TextInput::make('max_height'),
                        TextInput::make('max_volume'),
                    ]),
                ]),

            Section::make('Documents')
                ->collapsible()
                ->extraAttributes([
                    'class' => 'section-yellow-border',
                ])
                ->schema([
                    Grid::make(2)->schema([
                        FileUpload::make('truck_inspection_file')
                            ->label('Truck Inspection File')
                            ->downloadable()
                            ->previewable()
                            ->directory('vehicle-inspections'),
                        DatePicker::make('truck_inspection_date'),

                        FileUpload::make('registration_file')
                            ->label('Registration File')
                            ->downloadable()
                            ->previewable()
                            ->directory('vehicle-registrations'),
                        DatePicker::make('registration_date'),
                    ]),
                ]),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('title')
            ->columns([
                TextColumn::make('title')->searchable()->sortable(),
                TextColumn::make('serial_number')->searchable()->sortable(),
                TextColumn::make('plate_number')->searchable()->sortable(),
                TextColumn::make('plate_expiry')->date()->sortable(),
                TextColumn::make('reefer')->sortable(),
                TextColumn::make('tailgate')->sortable(),
                TextColumn::make('max_weight')->sortable(),
                TextColumn::make('max_volume')->sortable(),
            ])
            ->headerActions([
                CreateAction::make()->slideOver(),
            ])
            ->recordActions([
                EditAction::make()->slideOver(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
