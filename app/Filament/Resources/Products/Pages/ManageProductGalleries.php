<?php

namespace App\Filament\Resources\Products\Pages;

use App\Filament\Resources\Products\ProductResource;
use App\Models\ProductGallery;
use App\Models\ProductMedia;
use Filament\Actions\Action as FormAction;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ManageRelatedRecords;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;

class ManageProductGalleries extends ManageRelatedRecords
{
    protected static string $resource = ProductResource::class;

    protected static string $relationship = 'galleries';

    protected static ?string $navigationLabel = 'Galleries';

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('pg_name')
                ->label('Gallery name')
                ->placeholder('Black, White Titanium, Desert Titanium…')
                ->required()
                ->maxLength(100)
                ->live(onBlur: true)
                ->afterStateUpdated(function (?string $state, callable $set, callable $get) {
                    if (filled($state) && blank($get('pg_slug'))) {
                        $set('pg_slug', Str::slug($state));
                    }
                })
                ->columnSpanFull(),

            TextInput::make('pg_slug')
                ->label('Slug')
                ->placeholder('auto-filled from name')
                ->maxLength(100)
                ->columnSpanFull(),

            // ── Existing images (Edit only) ───────────────────────────────
            Placeholder::make('existing_images')
                ->label('Current images')
                ->columnSpanFull()
                ->content(function ($record): HtmlString {
                    if (! $record) return new HtmlString('');
                    $media = $record->media()->orderBy('pm_position')->get();
                    if ($media->isEmpty()) return new HtmlString('<p class="text-sm text-gray-400">No images yet</p>');

                    $html = '<div class="grid grid-cols-4 gap-2">';
                    foreach ($media as $item) {
                        $ext = strtolower(pathinfo($item->pm_src, PATHINFO_EXTENSION));
                        if (in_array($ext, ['mp4', 'mov', 'webm'])) {
                            $html .= '<div class="relative rounded overflow-hidden border border-gray-200 aspect-square flex items-center justify-center bg-gray-900">'
                                . '<span class="text-white text-xs">▶ video</span>'
                                . '</div>';
                        } else {
                            $html .= '<div class="relative rounded overflow-hidden border border-gray-200">'
                                . '<img src="' . e($item->pm_src) . '" class="w-full aspect-square object-cover" loading="lazy" />'
                                . '<span class="absolute bottom-0 left-0 right-0 bg-black/50 text-white text-[10px] px-1 py-0.5 truncate">' . e($item->pm_alt ?: basename($item->pm_src)) . '</span>'
                                . '</div>';
                        }
                    }
                    $html .= '</div>';
                    $html .= '<p class="text-xs text-gray-400 mt-2">' . $media->count() . ' items total — ลบรายการได้ที่ปุ่ม action ใต้แต่ละรูป (coming soon)</p>';
                    return new HtmlString($html);
                })
                ->visible(fn ($record) => $record !== null),

            // ── Multi-file drop zone ──────────────────────────────────────
            FileUpload::make('gallery_images')
                ->label(fn ($record) => $record ? 'Add more images / videos' : 'Images / Videos')
                ->helperText('Drag & drop หรือคลิกเพื่อเลือกหลายไฟล์พร้อมกัน — รองรับ JPG, PNG, WebP, AVIF, MP4')
                ->disk('public')
                ->directory('products/galleries')
                ->multiple()
                ->reorderable()
                ->image()
                ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp', 'image/avif', 'image/gif', 'video/mp4', 'video/quicktime', 'video/webm'])
                ->maxSize(51200)
                ->maxFiles(50)
                ->imagePreviewHeight('100')
                ->panelLayout('grid')
                ->columnSpanFull(),
        ]);
    }

    protected function handleRecordCreation(array $data): Model
    {
        $images = $data['gallery_images'] ?? [];
        unset($data['gallery_images']);

        /** @var ProductGallery $gallery */
        $gallery = $this->getOwnerRecord()->galleries()->create($data);

        foreach ($images as $position => $path) {
            $ext  = strtolower(pathinfo($path, PATHINFO_EXTENSION));
            $type = in_array($ext, ['mp4', 'mov', 'webm']) ? 'video' : 'image';
            ProductMedia::create([
                'pd_id'       => $this->getOwnerRecord()->pd_id,
                'pg_id'       => $gallery->pg_id,
                'pm_src'      => Storage::disk('public')->url($path),
                'pm_type'     => $type,
                'pm_position' => $position + 1,
                'pm_alt'      => $gallery->pg_name,
            ]);
        }

        return $gallery;
    }

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        $images = $data['gallery_images'] ?? [];
        unset($data['gallery_images']);

        $record->update($data);

        if (! empty($images)) {
            $nextPosition = $record->media()->max('pm_position') + 1;
            foreach ($images as $i => $path) {
                $ext  = strtolower(pathinfo($path, PATHINFO_EXTENSION));
                $type = in_array($ext, ['mp4', 'mov', 'webm']) ? 'video' : 'image';
                ProductMedia::create([
                    'pd_id'       => $this->getOwnerRecord()->pd_id,
                    'pg_id'       => $record->pg_id,
                    'pm_src'      => Storage::disk('public')->url($path),
                    'pm_type'     => $type,
                    'pm_position' => $nextPosition + $i,
                    'pm_alt'      => $record->pg_name,
                ]);
            }

            Notification::make()
                ->success()
                ->title('Added ' . count($images) . ' image(s) to ' . $record->pg_name)
                ->send();
        }

        return $record;
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('pg_name')
            ->columns([
                TextColumn::make('pg_name')
                    ->label('Gallery name')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('pg_slug')
                    ->label('Slug')
                    ->color('gray'),

                TextColumn::make('media_count')
                    ->label('Images')
                    ->getStateUsing(fn (ProductGallery $record): int => $record->media()->count())
                    ->badge()
                    ->color('info'),

                TextColumn::make('variants_count')
                    ->label('Variants using')
                    ->getStateUsing(fn (ProductGallery $record): int => $record->variants()->count())
                    ->badge()
                    ->color(fn (int $state): string => $state > 0 ? 'success' : 'gray'),

                TextColumn::make('pg_position')
                    ->label('#')
                    ->sortable(),
            ])
            ->defaultSort('pg_position')
            ->headerActions([
                CreateAction::make()->label('+ New gallery')->modalWidth('2xl'),
            ])
            ->recordActions([
                EditAction::make()->modalWidth('2xl'),
                DeleteAction::make()
                    ->before(function (ProductGallery $record) {
                        $record->variants()->update(['pg_id' => null]);
                        $record->media()->update(['pg_id' => null]);
                    }),
            ])
            ->reorderable('pg_position');
    }
}
