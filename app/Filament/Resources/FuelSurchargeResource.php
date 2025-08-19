<?php

namespace App\Filament\Resources;

use Filament\Schemas\Schema;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Actions\DeleteBulkAction;
use App\Filament\Resources\FuelSurchargeResource\Pages\ListFuelSurcharges;
use App\Filament\Resources\FuelSurchargeResource\Pages;
use App\Filament\Resources\FuelSurchargeResource\RelationManagers;
use App\Models\FuelSurcharge;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\DatePicker;
use Filament\Tables\Columns\TextColumn;

class FuelSurchargeResource extends Resource
{
    protected static ?string $model = FuelSurcharge::class;
    protected static string | \UnitEnum | null $navigationGroup = 'Customers';
    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-beaker';

    public static function form(Schema $schema): Schema
    {
        return $schema->components(static::getFormSchema())->columns(1);
    }

    public static function getFormSchema(): array
    {
        return [
            TextInput::make('ltl_surcharge')->numeric()->label('LTL Surcharge %')->required()->autofocus(),
            TextInput::make('ftl_surcharge')->numeric()->label('FTL Surcharge %')->required(),
            DatePicker::make('from_date')->required(),
            DatePicker::make('to_date'),
        ];
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('ltl_surcharge')->label('LTL %')->sortable(),
                TextColumn::make('ftl_surcharge')->label('FTL %')->sortable(),
                TextColumn::make('from_date')->date()->sortable(),
                TextColumn::make('to_date')->date()->sortable(),
            ])->defaultSort('created_at', 'desc')
            ->recordActions([
                  Action::make('delete')
                    ->label('Delete')
                    ->action(function (FuelSurcharge $record) {
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
            'index' => ListFuelSurcharges::route('/'),
           // 'create' => Pages\CreateFuelSurcharge::route('/create'),
            //'edit' => Pages\EditFuelSurcharge::route('/{record}/edit'),
        ];
    }
}
