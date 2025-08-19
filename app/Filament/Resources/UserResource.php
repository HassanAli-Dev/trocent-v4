<?php

namespace App\Filament\Resources;

use Filament\Schemas\Schema;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Actions\EditAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\BulkActionGroup;
use App\Filament\Resources\UserResource\Pages\ListUsers;
use App\Filament\Resources\UserResource\Pages;
use App\Filament\Resources\UserResource\RelationManagers;
use App\Models\User;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Filament\Forms\Components\{TextInput, Select, Toggle, PasswordInput};
use App\Models\Customer;
use Filament\Tables\Columns\{TextColumn, BadgeColumn};
use App\Models\DeliveryAgent;
use Filament\Forms\Set;
use Spatie\Permission\Models\Role;


class UserResource extends Resource
{
    protected static ?string $model = User::class;

    protected static string | \UnitEnum | null $navigationGroup = 'Access Management';
    protected static ?string $navigationLabel = 'Users';

    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-user';



    public static function form(Schema $schema): Schema
    {
        return $schema->components(static::getFormSchema())->columns(1);;

    }

      public static function getFormSchema(): array{
        return [
            TextInput::make('name')->required(),

            TextInput::make('username')
                ->required()
                ->unique(ignoreRecord: true)
                ->alphaDash()
                ->label('Username'),

            TextInput::make('email')
                ->email()
                ->required()
                ->unique(ignoreRecord: true),

            Select::make('type')
                ->options([
                    'admin' => 'Admin',
                    'customer' => 'Customer',
                    'driver' => 'Driver',
                ])
                ->required()
                ->reactive()
                ->default('admin')
                ->disabled(),

            Select::make('roles')
                ->relationship('roles', 'name')
                ->label('Role')
                ->visible(fn(Get $get) => $get('type') === 'admin')
                ->preload()
                ->required(fn(Get $get) => $get('type') === 'admin'),

            Select::make('customer_id')
                ->relationship('customer', 'full_name')
                ->searchable()
                ->visible(fn(Get $get) => $get('type') === 'customer')
                ->disabledOn('edit'),

            Select::make('delivery_agent_id')
                ->relationship('deliveryAgent', 'name')
                ->searchable()
                ->visible(fn(Get $get) => $get('type') === 'driver')
                ->disabledOn('edit'),

            TextInput::make('password')
                ->password()
                ->dehydrateStateUsing(fn($state) => filled($state) ? bcrypt($state) : null)
                ->dehydrated(fn($state) => filled($state))
                ->required(fn(string $context) => $context === 'create')
                ->label('Password'),
        ];
      }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->searchable()->sortable(),
                TextColumn::make('username')->searchable()->sortable(),
                TextColumn::make('email')->searchable()->sortable(),
                BadgeColumn::make('type')->colors([
                    'primary' => 'admin',
                    'warning' => 'customer',
                    'danger' => 'driver',
                ])->sortable(),
            ])
            ->filters([
                TrashedFilter::make()->default('withoutTrashed'),
            ])
            ->defaultSort('name')
            ->recordActions([

                EditAction::make()->slideOver(),
                DeleteAction::make()
                    ->visible(fn(User $record) => $record->id !== 1 && $record->type === 'admin'),
                RestoreAction::make(),
                ForceDeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([]),
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
            'index' => ListUsers::route('/'),
            //'create' => Pages\CreateUser::route('/create'),
            //'edit' => Pages\EditUser::route('/{record}/edit'),
        ];
    }
    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->withTrashed();
    }
}
