<?php

namespace App\Filament\Resources;

use Filament\Schemas\Schema;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Grid;
use Filament\Forms\Components\TextInput;
use Filament\Tables\Columns\TextColumn;
use Filament\Actions\EditAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use App\Filament\Resources\InterlinerResource\Pages\ListInterliners;
use App\Filament\Resources\InterlinerResource\Pages\CreateInterliner;
use App\Filament\Resources\InterlinerResource\Pages\EditInterliner;
use App\Filament\Resources\InterlinerResource\Pages;
use App\Models\DeliveryAgent;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class InterlinerResource extends Resource
{
    protected static ?string $model = DeliveryAgent::class;

    protected static ?string $navigationLabel = 'Interliners';
    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-truck';
    protected static ?string $pluralModelLabel = 'Interliners';
    protected static string | \UnitEnum | null $navigationGroup = 'Fleet Management';
    protected static ?string $modelLabel = 'Interliner';
    protected static ?string $slug = 'interliners';
    protected static ?string $navigationHelp = 'Manage interliner companies, their contacts, and addresses.';
    protected static ?string $slugPrefix = 'fleet-management';
    protected static ?string $slugSuffix = '';
    protected static ?string $slugSeparator = '/';
    protected static ?string $navigationBadge = 'New';

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->where('type', 'interliner');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Company Info')
                ->extraAttributes([
                    'class' => 'section-yellow-border',
                ])
                ->collapsible()
                ->schema([
                    Grid::make(2)->schema([
                        TextInput::make('name')
                            ->label('Company Name')
                            ->required()
                            ->autofocus(),
                        TextInput::make('contact_name')
                            ->label('Contact Person'),
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
                        TextInput::make('address'),
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
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->label('Company Name')->sortable()->searchable(),
                TextColumn::make('contact_name')->sortable()->searchable(),
                TextColumn::make('phone')->searchable(),
                TextColumn::make('email')->searchable(),
                TextColumn::make('city')->searchable(),
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
            'index' => ListInterliners::route('/'),
            'create' => CreateInterliner::route('/create'),
            'edit' => EditInterliner::route('/{record}/edit'),
        ];
    }

    public static function getGloballySearchableAttributes(): array
    {
        return ['name', 'contact_name', 'email', 'city'];
    }

    public static function getGlobalSearchResultTitle(Model $record): string
    {
        return "{$record->name} - {$record->contact_name}";
    }
}
