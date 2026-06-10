<?php

namespace App\Filament\Resources\LobCollections\Pages;

use App\Filament\Resources\LobCollections\LobCollectionResource;
use App\Models\LobDisplayCollection;
use App\Models\Product;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ManageRecords;
use Illuminate\Support\Str;

class ManageLobCollections extends ManageRecords
{
    protected static string $resource = LobCollectionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('sync_from_products')
                ->label('Sync จาก Products')
                ->icon(\Filament\Support\Icons\Heroicon::ArrowPath)
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

            CreateAction::make(),
        ];
    }
}
