<?php

namespace App\Filament\Resources;

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
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Actions\EditAction;
use Filament\Actions\Action;
use Filament\Forms\Components\Hidden;
use App\Models\User;
use Filament\Actions\DeleteBulkAction;
use App\Filament\Resources\DriverResource\Pages\ListDrivers;
use App\Filament\Resources\DriverResource\Pages\CreateDriver;
use App\Filament\Resources\DriverResource\Pages\EditDriver;
use Illuminate\Database\Eloquent\Model;

use App\Filament\Resources\DriverResource\Pages;
use App\Filament\Resources\DriverResource\RelationManagers;
use App\Models\DeliveryAgent;
use Filament\Forms;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Resources\Resource;
use Illuminate\Database\Eloquent\Builder;

class DriverResource extends Resource
{
    protected static ?string $model = DeliveryAgent::class;

    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-truck';
    protected static ?string $navigationLabel = 'Drivers';
    protected static ?string $pluralModelLabel = 'Drivers';
    protected static string | \UnitEnum | null $navigationGroup = 'Fleet Management';
    protected static ?string $modelLabel = 'Driver';
    protected static ?string $slug = 'drivers';

    protected static ?string $navigationHelp = 'Manage drivers, their documents, and compliance requirements.';
    protected static ?string $slugPrefix = 'fleet-management';

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->with('user')
            ->where('type', 'driver');
    }

    public static function getTableQuery(): Builder
    {
        return static::getEloquentQuery()->with('user');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Identity')
                ->collapsible()
                ->extraAttributes([
                    'class' => 'section-yellow-border',
                ])
                ->schema([
                    Grid::make(3)->schema([
                        TextInput::make('driver_number')->required()->autofocus(),
                        TextInput::make('first_name')->required(),
                        TextInput::make('middle_name'),
                        TextInput::make('last_name')->required(),
                        DatePicker::make('date_of_birth'),
                        Select::make('gender')->options(['male' => 'Male', 'female' => 'Female']),
                        TextInput::make('sin')->label('SIN'),
                        Select::make('companies')
                            ->label('Company')
                            ->relationship('companies', 'operating_name')
                            ->required()
                            ->searchable()
                            ->preload()
                            ->native(false)
                            ->multiple(false),
                    ]),
                ]),

            Section::make('Contact')
                ->collapsible()
                ->extraAttributes([
                    'class' => 'section-yellow-border',
                ])
                ->schema([
                    Grid::make(2)->schema([
                        TextInput::make('phone'),
                        TextInput::make('email')->email()->label('Contact Email'),
                    ]),
                ]),

            Section::make('Address')
                ->collapsible()
                ->extraAttributes([
                    'class' => 'section-yellow-border',
                ])
                ->schema([
                    Grid::make(3)->schema([
                        TextInput::make('address')->label('Address'),

                        TextInput::make('province')
                            ->maxLength(2)
                            ->minLength(2)
                            ->rule('regex:/^[A-Z]{2}$/')
                            ->placeholder('e.g., ON')
                            ->label('Province')
                            ->helperText('Enter 2-letter uppercase code'),
                        TextInput::make('city')->label('City'),
                        TextInput::make('postal_code')->label('Postal Code'),
                        TextInput::make('suite')->label('Suite'),
                    ]),
                ]),

            Section::make('License & Compliance')
                ->collapsible()
                ->extraAttributes([
                    'class' => 'section-yellow-border',
                ])
                ->schema([
                    Grid::make(3)->schema([
                        TextInput::make('license_number')->label('License Number'),
                        TextInput::make('license_classes')->label('License Classes'),
                        DatePicker::make('license_expiry')->label('License Expiry'),
                        Toggle::make('tdg_certified')->label('TDG Certified'),
                        DatePicker::make('tdg_expiry')->label('TDG Expiry'),
                        DatePicker::make('criminal_check_expiry')->label('Criminal Check Expiry'),
                        Textarea::make('criminal_check_note')->label('Criminal Check Note'),
                    ]),
                ]),

            Section::make('Additional Info')
                ->collapsible()
                ->extraAttributes([
                    'class' => 'section-yellow-border',
                ])
                ->schema([
                    Grid::make(3)->schema([
                        Select::make('contract_type')->options([
                            'full_time' => 'Full Time',
                            'part_time' => 'Part Time',
                            'contractor' => 'Contractor',
                        ]),
                        TextInput::make('driver_description'),
                    ]),
                ]),

            Section::make('Driver Documents')
                ->collapsible()
                ->extraAttributes([
                    'class' => 'section-yellow-border',
                ])
                ->schema([
                    Repeater::make('driverDocuments')
                        ->relationship()
                        ->itemLabel(fn(array $state): ?string => $state['type'] ?? null)
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
                        ->collapsed(true)
                        ->reorderable()
                        ->createItemButtonLabel('Add Document'),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('driver_number')->sortable()->searchable(),
                TextColumn::make('first_name')->sortable()->searchable(),
                TextColumn::make('last_name')->sortable()->searchable(),
                TextColumn::make('company_names')
                    ->label('Company')
                    ->getStateUsing(fn($record) => $record->companies->pluck('operating_name')->implode(', '))
                    ->searchable()
                    ->sortable(),
                TextColumn::make('phone')->searchable(),
                TextColumn::make('user.email')->label('Login Email')->searchable(),
                IconColumn::make('tdg_certified')
                    ->boolean()
                    ->label('TDG')
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('contract_type')
                    ->options([
                        'full_time' => 'Full Time',
                        'part_time' => 'Part Time',
                        'contractor' => 'Contractor',
                    ])
                    ->label('Contract Type'),
                TernaryFilter::make('tdg_certified')
                    ->label('TDG Certified')
                    ->trueLabel('Yes')
                    ->falseLabel('No'),
            ])
            ->recordActions([
                EditAction::make(),
                Action::make('manageLogin')
                    ->label(fn($record) => $record->user_id ? 'Edit Login' : 'Create Login')
                    ->icon('heroicon-o-lock-closed')
                    ->slideOver()

                    ->schema(function (Action $action) {
                        $record = $action->getRecord()->load('user');


                        return [
                            Hidden::make('existing_user_id')
                                ->default($record->user_id),

                            TextInput::make('username')
                                ->label('Username')
                                ->required()
                                ->default($record->user?->username)
                                ->rules([
                                    'required',
                                    'string',
                                    'max:255',
                                    'alpha_dash',
                                    'unique:users,username,' . ($record->user_id ?? 'NULL'),
                                ]),

                            TextInput::make('email')
                                ->label('Email')
                                ->required()
                                ->email()
                                ->default($record->user?->email)
                                ->rules([
                                    'required',
                                    'email',
                                    'max:255',
                                    'unique:users,email,' . ($record->user_id ?? 'NULL'),
                                ]),

                            TextInput::make('password')
                                ->label($record->user_id ? 'New Password (optional)' : 'Password')
                                ->password()
                                ->minLength(6)
                                ->autocomplete('new-password')
                                ->dehydrateStateUsing(fn($state) => filled($state) ? bcrypt($state) : null)
                                ->dehydrated(fn($state) => filled($state))
                                ->required(fn() => $record->user_id === null)
                                ->confirmed(),

                            TextInput::make('password_confirmation')
                                ->label('Confirm Password')
                                ->password()
                                ->autocomplete('new-password')
                                ->dehydrated(false)
                                ->required(fn() => $record->user_id === null),
                        ];
                    })

                    ->action(function ($record, array $data) {
                        if ($record->user_id) {
                            $user = $record->user;
                            $user->email = $data['email'];
                            $user->username = $data['username'];
                            if (empty($user->name)) {
                                $user->name = trim($record->first_name . ' ' . $record->last_name);
                            }
                            if (!empty($data['password'])) {
                                $user->password = bcrypt($data['password']);
                            }
                            $user->save();
                        } else {
                            $user = User::create([
                                'name' => trim($record->first_name . ' ' . $record->last_name),
                                'email' => $data['email'],
                                'username' => $data['username'],
                                'password' => bcrypt($data['password']),
                            ]);
                            $user->type = 'driver';
                            $user->save();
                            $record->user_id = $user->id;
                            $record->save();
                        }
                    })
            ])
            ->toolbarActions([
                DeleteBulkAction::make(),
            ]);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListDrivers::route('/'),
            'create' => CreateDriver::route('/create'),
            'edit' => EditDriver::route('/{record}/edit'),
        ];
    }
    public static function getGloballySearchableAttributes(): array
    {
        return ['driver_number', 'first_name', 'last_name'];
    }

    public static function getGlobalSearchResultTitle(Model $record): string
    {
        return "{$record->driver_number} - {$record->first_name} {$record->last_name}";
    }
}
