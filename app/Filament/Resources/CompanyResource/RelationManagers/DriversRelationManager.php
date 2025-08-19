<?php

namespace App\Filament\Resources\CompanyResource\RelationManagers;

use Filament\Schemas\Schema;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Grid;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Repeater;
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

class DriversRelationManager extends RelationManager
{
    protected static string $relationship = 'drivers';
    protected static ?string $recordTitleAttribute = 'driver_number';
    protected static ?string $navigationIcon = 'heroicon-o-user-group';
    protected static ?string $navigationGroup = 'Fleet Management';
    protected static ?string $navigationLabel = 'Drivers';
    protected static ?string $pluralModelLabel = 'Drivers';
    protected static ?string $modelLabel = 'Driver';

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Driver Identity')
                ->extraAttributes([
                    'class' => 'section-yellow-border',
                ])
                ->collapsible()
                ->schema([
                    Grid::make(2)->schema([
                        TextInput::make('driver_number')->required()->autofocus(),
                        TextInput::make('first_name')->required(),
                        TextInput::make('middle_name'),
                        TextInput::make('last_name')->required(),
                        DatePicker::make('date_of_birth'),
                        Select::make('gender')->options(['male' => 'Male', 'female' => 'Female']),
                        TextInput::make('sin')->label('SIN'),
                    ]),
                ]),

            Section::make('Contact Information')
                ->extraAttributes([
                    'class' => 'section-yellow-border',
                ])
                ->collapsible()
                ->schema([
                    Grid::make(2)->schema([
                        TextInput::make('phone'),
                        TextInput::make('email')->email(),
                    ]),
                ]),

            Section::make('Address')
                ->extraAttributes([
                    'class' => 'section-yellow-border',
                ])
                ->collapsible()
                ->schema([
                    Grid::make(2)->schema([
                        TextInput::make('address')->label('Address'),
                        TextInput::make('suite'),
                        TextInput::make('city'),
                        TextInput::make('province')
                            ->maxLength(2)
                            ->minLength(2)
                            ->rule('regex:/^[A-Z]{2}$/')
                            ->placeholder('e.g., ON')
                            ->label('Province')
                            ->helperText('Enter 2-letter uppercase code'),
                        TextInput::make('postal_code'),
                    ]),
                ]),

            Section::make('License & Compliance')
                ->extraAttributes([
                    'class' => 'section-yellow-border',
                ])
                ->collapsible()
                ->schema([
                    Grid::make(2)->schema([
                        TextInput::make('license_number'),
                        TextInput::make('license_classes'),
                        DatePicker::make('license_expiry'),
                        Toggle::make('tdg_certified'),
                        DatePicker::make('tdg_expiry'),
                        DatePicker::make('criminal_check_expiry'),
                        Textarea::make('criminal_check_note'),
                    ]),
                ]),

            Section::make('Driver Profile')
                ->extraAttributes([
                    'class' => 'section-yellow-border',
                ])
                ->collapsible()
                ->schema([
                    Grid::make(2)->schema([
                        Select::make('contract_type')
                            ->options([
                                'full_time' => 'Full Time',
                                'part_time' => 'Part Time',
                                'contractor' => 'Contractor',
                            ]),
                        TextInput::make('driver_description'),
                    ]),
                ]),

            Section::make('Driver Documents')
                ->extraAttributes([
                    'class' => 'section-yellow-border',
                ])
                ->collapsible()
                ->schema([
                    Repeater::make('driverDocuments')
                        ->relationship()
                        ->itemLabel(fn (array $state): ?string => $state['type'] ?? null)
                        ->schema([
                            Select::make('type')
                                ->options([
                                    'license' => 'License',
                                    'tdg' => 'TDG Certificate',
                                    'record' => 'Driver Record',
                                    'background' => 'Background Check',
                                    'residence_history' => 'Work & Residence History',
                                ])
                                ->required(),

                            FileUpload::make('file_path')
                                ->directory('driver-documents')
                                ->required()
                                ->downloadable()
                                ->previewable(),

                            DatePicker::make('expiry_date')
                                ->required(),
                        ])->columns(3)
                        ->defaultItems(0)
                        ->collapsible()
                        ->reorderable()
                        ->collapsed(true)
                        ->createItemButtonLabel('Add Document'),
                ]),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('driver_number')
            ->columns([
                TextColumn::make('driver_number')->sortable()->searchable(),
                TextColumn::make('first_name')->sortable()->searchable(),
                TextColumn::make('last_name')->sortable()->searchable(),
                TextColumn::make('phone')->searchable(),
                TextColumn::make('email')->searchable(),
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
