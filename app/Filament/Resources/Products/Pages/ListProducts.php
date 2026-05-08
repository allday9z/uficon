<?php

namespace App\Filament\Resources\Products\Pages;

use App\Filament\Resources\Products\ProductResource;
use App\Imports\ProductImport;
use App\Imports\StockImport;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Placeholder;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;

class ListProducts extends ListRecords
{
    protected static string $resource = ProductResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('importStock')
                ->label('Import Stock')
                ->icon('heroicon-o-arrow-up-tray')
                ->color('warning')
                ->form([
                    Placeholder::make('guide')
                        ->label('')
                        ->content('อัปโหลดไฟล์ CSV/Excel สำหรับอัปเดต Stock ต่อสาขา')
                        ->columnSpanFull(),

                    FileUpload::make('stock_file')
                        ->label('ไฟล์ Stock (CSV/XLSX)')
                        ->acceptedFileTypes(['text/csv', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'])
                        ->required()
                        ->disk('local')
                        ->directory('imports/stock'),
                ])
                ->action(function (array $data): void {
                    $path = storage_path('app/private/' . $data['stock_file']);
                    $import = new StockImport();
                    $import->import($path);

                    $msg = "อัปเดต Stock สำเร็จ {$import->updated} รายการ";
                    if ($import->skipped > 0) {
                        $msg .= ", ข้าม {$import->skipped} รายการ";
                    }

                    if (empty($import->errors)) {
                        Notification::make()->title($msg)->success()->send();
                    } else {
                        Notification::make()
                            ->title($msg)
                            ->body(implode("\n", array_slice($import->errors, 0, 5)))
                            ->warning()
                            ->send();
                    }
                }),

            Action::make('importProducts')
                ->label('Import Products')
                ->icon('heroicon-o-cloud-arrow-up')
                ->color('info')
                ->form([
                    Placeholder::make('guide')
                        ->label('')
                        ->content('อัปโหลดไฟล์ CSV/Excel สำหรับ import สินค้าและ variants')
                        ->columnSpanFull(),

                    FileUpload::make('product_file')
                        ->label('ไฟล์ Products (CSV/XLSX)')
                        ->acceptedFileTypes(['text/csv', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'])
                        ->required()
                        ->disk('local')
                        ->directory('imports/products'),
                ])
                ->action(function (array $data): void {
                    $path = storage_path('app/private/' . $data['product_file']);
                    $import = new ProductImport();
                    $import->import($path);

                    $msg = "Import สำเร็จ: สร้าง {$import->created}, อัปเดต {$import->updated} รายการ";

                    if (empty($import->errors)) {
                        Notification::make()->title($msg)->success()->send();
                    } else {
                        Notification::make()
                            ->title($msg)
                            ->body(implode("\n", array_slice($import->errors, 0, 5)))
                            ->warning()
                            ->send();
                    }
                }),

            CreateAction::make()->label('+ เพิ่มสินค้า'),
        ];
    }
}
