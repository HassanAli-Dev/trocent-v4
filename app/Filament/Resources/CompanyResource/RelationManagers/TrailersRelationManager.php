<?php

namespace App\Filament\Resources\CompanyResource\RelationManagers;

use Filament\Schemas\Schema;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Grid;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Select;
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

class TrailersRelationManager extends RelationManager
{
    protected static string $relationship = 'trailers';

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Trailer Details')
                ->extraAttributes([
                    'class' => 'section-yellow-border',
                ])
                ->collapsible()
                ->schema([
                    Grid::make(2)->schema([
                        TextInput::make('title')->required()->autofocus(),
                        TextInput::make('leasing_company')->label('Leasing Company'),
                        TextInput::make('trailer_number')->label('Trailer Number'),
                        TextInput::make('plate_number')->label('Plate Number'),
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
                            ])
                            ->label('Door Type'),
                    ]),
                ]),
        ]);
    }



    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('title')
            ->columns([
                TextColumn::make('title')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('leasing_company')
                    ->label('Leasing Company')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('trailer_number')
                    ->label('Trailer Number')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('plate_number')
                    ->label('Plate Number')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('reefer')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('tailgate')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('door_type')
                    ->label('Door Type')
                    ->searchable()
                    ->sortable(),
            ])
            ->filters([
                //
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
