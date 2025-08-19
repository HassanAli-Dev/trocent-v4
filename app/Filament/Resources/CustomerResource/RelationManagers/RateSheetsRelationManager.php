<?php

namespace App\Filament\Resources\CustomerResource\RelationManagers;

use Illuminate\Database\Eloquent\Builder;
use Filament\Tables\Table;
use Filament\Actions\Action;
use App\Models\Customer;
use App\Models\RateSheet;
use App\Filament\Resources\RateSheetResource;
use Filament\Forms;
use Filament\Tables;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\IconColumn;
use Filament\Resources\RelationManagers\RelationManager;
use Illuminate\Support\Facades\Storage;
use Maatwebsite\Excel\Facades\Excel;
use App\Imports\RateSheetImport;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\FileUpload;
use Filament\Notifications\Notification;

class RateSheetsRelationManager extends RelationManager
{
    protected static string $relationship = 'rateSheets';

    protected function getTableQuery(): Builder
    {
        return RateSheet::query()
            ->where('customer_id', $this->ownerRecord->id)
            ->selectRaw('MIN(id) as id, import_batch_id, type, skid_by_weight, MIN(created_at) as created_at')
            ->groupBy('import_batch_id', 'type', 'skid_by_weight');
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('type')->badge(),
                IconColumn::make('skid_by_weight')
                    ->boolean()
                    ->label('Skid → Weight'),
                TextColumn::make('created_at')
                    ->label('Imported On')
                    ->dateTime()
                    ->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->recordActions([
                Action::make('view')
                    ->label('View')
                    ->url(
                        fn(RateSheet $record) =>
                        RateSheetResource::getUrl('index') . '?tableFilters[customer_id][value]=' . $this->ownerRecord->id . '&tableFilters[type][value]=' . $record->type
                    )
                    ->openUrlInNewTab(),
                Action::make('deleteBatch')
                    ->label('Delete')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->action(function (RateSheet $record) {
                        RateSheet::where('customer_id', $this->ownerRecord->id)
                            ->where('import_batch_id', $record->import_batch_id)
                            ->where('type', $record->type)
                            ->where('skid_by_weight', $record->skid_by_weight)
                            ->delete();

                        Notification::make()
                            ->title('Rate sheet batch deleted')
                            ->success()
                            ->send();
                    }),
            ])
            ->headerActions([

                Action::make('importRateSheet')
                    ->label('Import Rate Sheet')
                    ->icon('heroicon-m-arrow-down-tray')
                    ->modalHeading('Import Rate Sheet for this Customer')
                    ->schema([
                        Select::make('rate_sheet_type')
                            ->options([
                                'skid'   => 'Skid Based',
                                'weight' => 'Weight Based',
                            ])
                            ->required(),

                        Toggle::make('skid_by_weight')
                            ->label('Skid → Weight')
                            ->helperText('Check this if the rate sheet is Skid by Weight')
                            ->default(false),

                        FileUpload::make('file')
                            ->label('Rate Sheet (.xlsx)')
                            ->acceptedFileTypes([
                                'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                            ])
                            ->required(),
                    ])
                    ->action(function (array $data, $record) {
                        // Save uploaded file temporarily (using Filament/Livewire file upload)
                        $finalPath = 'temp-rate-sheets/' . basename($data['file']);
                        Storage::disk('public')->move($data['file'], str_replace('public/', '', $finalPath));

                        $batchId = now()->timestamp . '_' . $this->ownerRecord->id;

                        Excel::import(
                            new RateSheetImport(
                                customer: Customer::find($this->ownerRecord->id),
                                type: $data['rate_sheet_type'],
                                skidByWeight: $data['skid_by_weight'] ?? false,
                                importBatchId: $batchId,
                            ),
                            Storage::disk('public')->path(str_replace('public/', '', $finalPath))
                        );

                        Notification::make()
                            ->title('Rate sheet imported successfully')
                            ->success()
                            ->send();
                    })
                    ->modalSubmitActionLabel('Import'),
            ]);
    }
}
