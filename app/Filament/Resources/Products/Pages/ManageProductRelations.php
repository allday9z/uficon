<?php

namespace App\Filament\Resources\Products\Pages;

use App\Filament\Resources\Products\ProductResource;
use App\Models\Product;
use App\Models\ProductRelation;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ManageRelatedRecords;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\BadgeColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Facades\DB;

class ManageProductRelations extends ManageRelatedRecords
{
    protected static string $resource = ProductResource::class;

    protected static string $relationship = 'productRelations';

    protected static ?string $navigationLabel = 'Related Products';

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('related_pd_id')
                ->label('Related product')
                ->options(fn () => Product::orderBy('pd_name')
                    ->pluck('pd_name', 'pd_id')
                    ->except([$this->getOwnerRecord()->pd_id]))
                ->searchable()
                ->preload()
                ->required()
                ->columnSpanFull(),

            Select::make('pr_type')
                ->label('Relation type')
                ->options([
                    'accessory'       => 'Accessory (ด้านบน Gallery)',
                    'bought_together' => 'Frequently Bought Together',
                    'upsell'          => 'You Might Also Like',
                ])
                ->required()
                ->native(false)
                ->columnSpanFull(),

            TextInput::make('pr_position')
                ->label('Display order')
                ->numeric()
                ->default(0)
                ->minValue(0)
                ->helperText('น้อย = แสดงก่อน'),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('pr_id')
            ->columns([
                TextColumn::make('relatedProduct.pd_name')
                    ->label('Related product')
                    ->searchable()
                    ->sortable(),

                BadgeColumn::make('pr_type')
                    ->label('Type')
                    ->colors([
                        'info'    => 'accessory',
                        'success' => 'bought_together',
                        'warning' => 'upsell',
                    ])
                    ->formatStateUsing(fn ($state) => match ($state) {
                        'accessory'       => 'Accessory',
                        'bought_together' => 'Bought Together',
                        'upsell'          => 'Upsell',
                        default           => $state,
                    }),

                TextColumn::make('pr_position')
                    ->label('#')
                    ->sortable(),
            ])
            ->defaultSort('pr_position')
            ->headerActions([
                CreateAction::make(),

                Action::make('sync_from_orders')
                    ->label('Sync from order history')
                    ->icon('heroicon-o-arrow-path')
                    ->color('info')
                    ->requiresConfirmation()
                    ->modalHeading('Sync "Bought Together" from order history')
                    ->modalDescription('ดึง products ที่ถูก order พร้อมกันกับสินค้านี้บ่อยที่สุด → เพิ่มเป็น "Frequently Bought Together" อัตโนมัติ (top 10, ข้ามถ้ามีอยู่แล้ว)')
                    ->action(function () {
                        $pdId    = $this->getOwnerRecord()->pd_id;
                        $topN    = 10;

                        $coProducts = DB::table('order_item as a')
                            ->join('order_item as b', function ($join) use ($pdId) {
                                $join->on('a.ord_id', '=', 'b.ord_id')
                                     ->where('b.pd_id', '!=', $pdId);
                            })
                            ->where('a.pd_id', $pdId)
                            ->select('b.pd_id', DB::raw('COUNT(*) as co_count'))
                            ->groupBy('b.pd_id')
                            ->orderByDesc('co_count')
                            ->limit($topN)
                            ->get();

                        if ($coProducts->isEmpty()) {
                            Notification::make()
                                ->warning()
                                ->title('ไม่พบข้อมูล order history')
                                ->body('สินค้านี้ยังไม่มีประวัติการสั่งซื้อพร้อมกับสินค้าอื่น')
                                ->send();
                            return;
                        }

                        $inserted = 0;
                        foreach ($coProducts as $idx => $row) {
                            $exists = ProductRelation::where('pd_id', $pdId)
                                ->where('related_pd_id', $row->pd_id)
                                ->where('pr_type', 'bought_together')
                                ->exists();

                            if (! $exists) {
                                ProductRelation::create([
                                    'pd_id'        => $pdId,
                                    'related_pd_id' => $row->pd_id,
                                    'pr_type'      => 'bought_together',
                                    'pr_position'  => $idx,
                                ]);
                                $inserted++;
                            }
                        }

                        Notification::make()
                            ->success()
                            ->title("Synced {$inserted} related products")
                            ->body("Found {$coProducts->count()} co-purchased products, added {$inserted} new entries")
                            ->send();
                    }),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->reorderable('pr_position');
    }
}
