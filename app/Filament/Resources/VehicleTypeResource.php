<?php

namespace App\Filament\Resources;

use Filament\Schemas\Schema;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Actions\DeleteBulkAction;
use App\Filament\Resources\VehicleTypeResource\Pages\ListVehicleTypes;
use App\Filament\Resources\VehicleTypeResource\Pages;
use App\Filament\Resources\VehicleTypeResource\RelationManagers;
use App\Models\VehicleType;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Filament\Forms\Components\TextInput;
use Filament\Tables\Columns\TextColumn;

class VehicleTypeResource extends Resource
{
    protected static ?string $model = VehicleType::class;
    protected static string | \UnitEnum | null $navigationGroup = 'Settings';
    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-cube';

    public static function getFormSchema(): array
    {
        return [
            TextInput::make('name')->required()->autofocus(),
            TextInput::make('rate')->numeric()->label('Base Rate')->required(),
        ];
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components(static::getFormSchema())->columns(1);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->searchable()->sortable(),
                TextColumn::make('rate')->label('Rate')->numeric()->sortable(),
            ])
            ->defaultSort('name')
            ->recordActions([
                Action::make('delete')
                    ->label('Delete')
                    ->action(function (VehicleType $record) {
                        $record->delete();
                    })
                    ->requiresConfirmation()
                    ->color('danger'),
                EditAction::make()->slideOver(),
            ])
            ->toolbarActions([
                DeleteBulkAction::make(),
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
            'index' => ListVehicleTypes::route('/'),
            //'create' => Pages\CreateVehicleType::route('/create'),
            //'edit' => Pages\EditVehicleType::route('/{record}/edit'),
        ];
    }
}
