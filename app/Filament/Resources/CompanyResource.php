<?php

namespace App\Filament\Resources;

use Filament\Schemas\Schema;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Grid;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\DatePicker;
use Filament\Tables\Columns\TextColumn;
use Filament\Actions\EditAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use App\Filament\Resources\CompanyResource\RelationManagers\DriversRelationManager;
use App\Filament\Resources\CompanyResource\RelationManagers\VehiclesRelationManager;
use App\Filament\Resources\CompanyResource\RelationManagers\TrailersRelationManager;
use App\Filament\Resources\CompanyResource\Pages\ListCompanies;
use App\Filament\Resources\CompanyResource\Pages\CreateCompany;
use App\Filament\Resources\CompanyResource\Pages\EditCompany;
use App\Filament\Resources\CompanyResource\Pages;
use App\Filament\Resources\CompanyResource\RelationManagers;
use App\Models\Company;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class CompanyResource extends Resource
{
    protected static ?string $model = Company::class;

    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-rectangle-stack';
    protected static ?string $navigationLabel = 'Companies';
    protected static ?string $pluralModelLabel = 'Companies';
    protected static string | \UnitEnum | null $navigationGroup = 'Fleet Management';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Company Information')
                    ->extraAttributes([
                        'class' => 'section-yellow-border',
                    ])
                    ->schema([
                        Grid::make(3)->schema([
                            TextInput::make('legal_name')->required()->autofocus(),
                            TextInput::make('operating_name'),
                            TextInput::make('contact_person'),
                            TextInput::make('phone'),
                            TextInput::make('email')->email(),
                            TextInput::make('neq')->label('NEQ'),
                            TextInput::make('nir')->label('NIR'),
                            TextInput::make('ifta')->label('IFTA'),
                        ]),
                    ])
                    ->collapsible(),

                Section::make('Auto Insurance')
                    ->extraAttributes([
                        'class' => 'section-yellow-border',
                    ])
                    ->schema([
                        Grid::make(4)->schema([
                            TextInput::make('auto_insurance_company'),
                            TextInput::make('auto_policy_number'),
                            DatePicker::make('auto_policy_expiry'),
                            TextInput::make('auto_insurance_amount')->numeric(),


                        ]),
                    ])
                    ->collapsed(true)
                    ->collapsible(),

                Section::make('Additional Auto Insurance')
                    ->extraAttributes([
                        'class' => 'section-yellow-border',
                    ])
                    ->schema([
                        Grid::make(4)->schema([


                            TextInput::make('auto_insurance_company_2')->label('Auto Insurance Company'),
                            TextInput::make('auto_policy_number_2')->label('Auto Policy Number'),
                            DatePicker::make('auto_policy_expiry_2')->label('Auto Policy Expiry'),
                            TextInput::make('auto_insurance_amount_2')->label('Auto Insurance Amount')->numeric(),
                        ]),
                    ])
                    ->collapsed(true)
                    ->collapsible(),

                Section::make('Cargo Insurance')
                    ->extraAttributes([
                        'class' => 'section-yellow-border',
                    ])
                    ->schema([
                        Grid::make(4)->schema([
                            TextInput::make('cargo_insurance_company'),
                            TextInput::make('cargo_policy_number'),
                            DatePicker::make('cargo_policy_expiry'),
                            TextInput::make('cargo_insurance_amount')->numeric(),


                        ]),
                    ])
                    ->collapsed(true)
                    ->collapsible(),


                Section::make('Additional Cargo Insurance')
                    ->extraAttributes([
                        'class' => 'section-yellow-border',
                    ])
                    ->schema([
                        Grid::make(4)->schema([


                            TextInput::make('cargo_insurance_company_2')->label('Cargo Insurance Company'),
                            TextInput::make('cargo_policy_number_2')->label('Cargo Policy Number'),
                            DatePicker::make('cargo_policy_expiry_2')->label('Cargo Policy Expiry'),
                            TextInput::make('cargo_insurance_amount_2')->label('Cargo Insurance Amount')->numeric(),
                        ]),
                    ])
                    ->collapsed(true)
                    ->collapsible(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('operating_name')->searchable()->sortable(),
                TextColumn::make('legal_name')->searchable()->sortable(),

                TextColumn::make('phone'),
                TextColumn::make('email'),
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
            DriversRelationManager::class,
            VehiclesRelationManager::class,
            TrailersRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListCompanies::route('/'),
            'create' => CreateCompany::route('/create'),
            'edit' => EditCompany::route('/{record}/edit'),
        ];
    }

    public static function getGloballySearchableAttributes(): array
    {
        return ['legal_name', 'operating_name'];
    }

    public static function getGlobalSearchResultTitle(Model $record): string
    {
        return "{$record->legal_name} - {$record->operating_name}";
    }
}
