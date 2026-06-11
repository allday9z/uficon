<?php

namespace App\Filament\Resources\LobCollections\Pages;

use App\Filament\Resources\LobCollections\LobCollectionResource;
use App\Models\LobDisplayCollection;
use App\Models\Product;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ManageRecords;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ManageLobCollections extends ManageRecords
{
    protected static string $resource = LobCollectionResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        return $this->processFamilyStripeUpload($data);
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        return $this->processFamilyStripeUpload($data);
    }

    private function processFamilyStripeUpload(array $data): array
    {
        // FileUpload moves file to disk before mutate runs — $data has relative path
        if (! empty($data['ldc_stripe_image_file'])) {
            $data['ldc_stripe_image'] = Storage::disk('public')->url($data['ldc_stripe_image_file']);
        }
        unset($data['ldc_stripe_image_file']);
        return $data;
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('sync_from_products')
                ->label('Sync จาก Products')
                ->icon(Heroicon::ArrowPath)
                ->color('gray')
                ->requiresConfirmation()
                ->modalHeading('Sync LOB Collections จาก Product data')
                ->modalDescription('สร้าง LobDisplayCollection records จาก pd_lob + pd_sub_lob ของ products ที่ import มา (ถ้ายังไม่มี)')
                ->action(function () {
                    $groups = Product::query()
                        ->whereNotNull('pd_lob')
                        ->whereNotNull('pd_sub_lob')
                        ->where('pd_status', 'active')
                        ->select('pd_lob', 'pd_sub_lob')
                        ->distinct()
                        ->orderBy('pd_lob')
                        ->orderBy('pd_sub_lob')
                        ->get();

                    $created = 0;
                    foreach ($groups as $group) {
                        $slug = Str::slug($group->pd_sub_lob);
                        if (! $slug) {
                            $slug = 'group-' . substr(md5($group->pd_sub_lob), 0, 8);
                        }

                        $existing = LobDisplayCollection::where('ldc_slug', $slug)->first();
                        if (! $existing) {
                            LobDisplayCollection::create([
                                'ldc_lob'      => $group->pd_lob,
                                'ldc_sub_lob'  => $group->pd_sub_lob,
                                'ldc_slug'     => $slug,
                                'ldc_title'    => $group->pd_sub_lob,
                                'ldc_is_active'   => true,
                                'ldc_is_featured' => false,
                                'ldc_sort_order'  => 0,
                            ]);
                            $created++;
                        }
                    }

                    Notification::make()
                        ->title("Sync เสร็จ — สร้าง {$created} records จาก {$groups->count()} กลุ่ม")
                        ->success()
                        ->send();
                }),

            Action::make('sort_by')
                ->label('จัดเรียง')
                ->icon(Heroicon::AdjustmentsHorizontal)
                ->color('gray')
                ->requiresConfirmation()
                ->modalHeading('จัดเรียง LOB Collections')
                ->modalDescription('เลือกเกณฑ์การเรียง — ระบบจะอัปเดต Sort Order ให้อัตโนมัติ (กรองตาม LOB ที่เลือกก่อน แล้วเรียงทั้งกลุ่ม)')
                ->schema([
                    \Filament\Forms\Components\Select::make('lob_filter')
                        ->label('กรองเฉพาะ LOB (ถ้าต้องการ)')
                        ->options(fn () => LobDisplayCollection::whereNotNull('ldc_lob')
                            ->distinct()->orderBy('ldc_lob')->pluck('ldc_lob', 'ldc_lob')->toArray()
                        )
                        ->placeholder('ทุก LOB')
                        ->searchable(),

                    \Filament\Forms\Components\Select::make('sort_mode')
                        ->label('เรียงตาม')
                        ->required()
                        ->options([
                            'sale_date_desc' => 'ใหม่ → เก่า (วันวางขาย)',
                            'sale_date_asc'  => 'เก่า → ใหม่ (วันวางขาย)',
                            'name_asc'       => 'A → Z (ชื่อ)',
                            'name_desc'      => 'Z → A (ชื่อ)',
                        ])
                        ->default('sale_date_desc'),
                ])
                ->action(function (array $data) {
                    $lobFilter = $data['lob_filter'] ?? null;
                    $mode      = $data['sort_mode'] ?? 'sale_date_desc';

                    $query = LobDisplayCollection::query();
                    if ($lobFilter) {
                        $query->where('ldc_lob', $lobFilter);
                    }

                    $records = match ($mode) {
                        'sale_date_desc' => $query->orderByRaw('ldc_sale_date DESC NULLS LAST')->orderBy('ldc_title')->get(),
                        'sale_date_asc'  => $query->orderByRaw('ldc_sale_date ASC NULLS LAST')->orderBy('ldc_title')->get(),
                        'name_asc'       => $query->orderBy('ldc_title')->get(),
                        'name_desc'      => $query->orderByDesc('ldc_title')->get(),
                        default          => $query->get(),
                    };

                    $records->each(function ($rec, $idx) {
                        $rec->update(['ldc_sort_order' => $idx]);
                    });

                    Notification::make()
                        ->title("จัดเรียง {$records->count()} records เสร็จแล้ว")
                        ->success()
                        ->send();
                }),

            Action::make('delete_all')
                ->label('ลบทั้งหมด')
                ->icon(Heroicon::Trash)
                ->color('danger')
                ->requiresConfirmation()
                ->modalHeading('ลบ LOB Collections ทั้งหมด')
                ->modalDescription('ลบทุก record ใน lob_display_collection — ใช้ก่อน re-import เพื่อ reset ข้อมูลทั้งหมด')
                ->action(function () {
                    $count = LobDisplayCollection::count();
                    LobDisplayCollection::truncate();

                    Notification::make()
                        ->title("ลบแล้ว {$count} records — รัน Import หรือ Sync จาก Products เพื่อสร้างใหม่")
                        ->warning()
                        ->send();
                }),

            CreateAction::make(),
        ];
    }
}
