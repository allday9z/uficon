<x-filament-panels::page>
<style>
.imp-wrap        { max-width: 760px; }
.imp-steps       { display:flex; align-items:center; gap:0; margin-bottom:2rem; }
.imp-step        { display:flex; flex-direction:column; align-items:center; flex:1; }
.imp-step-circle { width:2rem; height:2rem; border-radius:9999px; display:flex; align-items:center; justify-content:center; font-size:.8rem; font-weight:700; border:2px solid; transition:all .3s; }
.imp-step-label  { font-size:.7rem; margin-top:.3rem; font-weight:500; transition:color .3s; }
.imp-step-line   { flex:1; height:2px; background:#e5e7eb; margin-bottom:1.1rem; transition:background .3s; }
.imp-step-line.done { background:#f97316; }
.imp-step .active .imp-step-circle { background:#f97316; border-color:#f97316; color:#fff; }
.imp-step .done   .imp-step-circle { background:#f97316; border-color:#f97316; color:#fff; }
.imp-step .pending .imp-step-circle { background:#f3f4f6; border-color:#d1d5db; color:#9ca3af; }
.imp-step .active .imp-step-label  { color:#f97316; }
.imp-step .done   .imp-step-label  { color:#f97316; }
.imp-step .pending .imp-step-label { color:#9ca3af; }

.imp-card        { background:#1f2937; border:1px solid #374151; border-radius:.75rem; padding:1.5rem; }
.imp-table       { width:100%; border-collapse:collapse; font-size:.85rem; margin-top:.75rem; }
.imp-table th    { padding:.6rem .875rem; text-align:left; font-size:.7rem; text-transform:uppercase; letter-spacing:.05em; color:#9ca3af; background:#111827; border-bottom:1px solid #374151; }
.imp-table th:nth-child(3),
.imp-table td:nth-child(3) { text-align:center; }
.imp-table tr:not(:last-child) td { border-bottom:1px solid #1f2937; }
.imp-table td    { padding:.7rem .875rem; color:#e5e7eb; }
.imp-badge       { display:inline-block; padding:.1rem .5rem; border-radius:.25rem; font-size:.7rem; font-weight:700; letter-spacing:.03em; }
.imp-badge-create { background:#052e16; color:#4ade80; }
.imp-badge-update { background:#1e3a5f; color:#60a5fa; }
.imp-badge-skip   { background:#1c1917; color:#fbbf24; }
.imp-badge-fresh  { background:#3b0000; color:#f87171; }
.imp-summary-row  { display:flex; gap:.625rem; flex-wrap:wrap; margin-bottom:1rem; }
.imp-chip         { padding:.2rem .7rem; border-radius:9999px; font-size:.78rem; font-weight:600; }
.imp-done-icon    { width:3.5rem; height:3.5rem; border-radius:9999px; background:#052e16; display:flex; align-items:center; justify-content:center; flex-shrink:0; }
.imp-fade         { animation: impFadeIn .25s ease; }
@keyframes impFadeIn { from { opacity:0; transform:translateY(6px); } to { opacity:1; transform:none; } }
</style>

<div class="imp-wrap">

    {{-- Step indicator --}}
    @php
        $s1 = $step === 'upload'  ? 'active' : ($step === 'preview' || $step === 'done' ? 'done' : 'pending');
        $s2 = $step === 'preview' ? 'active' : ($step === 'done' ? 'done' : 'pending');
        $s3 = $step === 'done'    ? 'active' : 'pending';
    @endphp
    <div class="imp-steps">
        <div class="imp-step">
            <div class="{{ $s1 }}">
                <div class="imp-step-circle">{{ $s1 === 'done' ? '✓' : '1' }}</div>
                <div class="imp-step-label">Configure</div>
            </div>
        </div>
        <div class="imp-step-line {{ in_array($step, ['preview','done']) ? 'done' : '' }}"></div>
        <div class="imp-step">
            <div class="{{ $s2 }}">
                <div class="imp-step-circle">{{ $s2 === 'done' ? '✓' : '2' }}</div>
                <div class="imp-step-label">Preview</div>
            </div>
        </div>
        <div class="imp-step-line {{ $step === 'done' ? 'done' : '' }}"></div>
        <div class="imp-step">
            <div class="{{ $s3 }}">
                <div class="imp-step-circle">3</div>
                <div class="imp-step-label">Done</div>
            </div>
        </div>
    </div>

    {{-- ── STEP 1: CONFIGURE ── --}}
    @if ($step === 'upload')
        <div class="imp-card imp-fade" wire:transition.opacity.duration.200ms>
            <h3 style="font-size:1rem;font-weight:700;color:#f9fafb;margin:0 0 .25rem;">Configure Import</h3>
            <p style="font-size:.825rem;color:#9ca3af;margin:0 0 1.25rem;">เลือก profile และ upload ไฟล์ .xlsx</p>

            <form wire:submit="doPreview">
                {{ $this->form }}

                {{-- Column Mappings Accordion --}}
                @if (count($profileMaps) > 0)
                    <div style="margin-top:1rem; border:1px solid #374151; border-radius:.5rem; overflow:hidden;">
                        <button type="button"
                            wire:click="$toggle('showMappings')"
                            style="width:100%; display:flex; justify-content:space-between; align-items:center; padding:.625rem .875rem; background:#111827; border:none; cursor:pointer; color:#9ca3af; font-size:.8rem;">
                            <span style="display:flex; align-items:center; gap:.5rem;">
                                <svg style="width:.875rem;height:.875rem;" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 5H7a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V7a2 2 0 0 0-2-2h-2M9 5a2 2 0 0 0 2 2h2a2 2 0 0 0 2-2M9 5a2 2 0 0 1 2-2h2a2 2 0 0 1 2 2"/></svg>
                                Column Mappings
                                <span style="background:#1e3a5f; color:#60a5fa; padding:.1rem .45rem; border-radius:9999px; font-size:.7rem; font-weight:700;">{{ count($profileMaps) }}</span>
                            </span>
                            <svg style="width:.875rem;height:.875rem; transition:transform .2s; {{ $showMappings ? 'transform:rotate(180deg)' : '' }}" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="m6 9 6 6 6-6"/></svg>
                        </button>

                        @if ($showMappings)
                            <div style="overflow-x:auto; max-height:320px; overflow-y:auto;">
                                <table class="imp-table">
                                    <thead style="position:sticky; top:0; z-index:1;">
                                        <tr>
                                            <th style="width:40px;">#</th>
                                            <th>xlsx Column Header</th>
                                            <th>Target Model.Field</th>
                                            <th style="width:90px;">Mode</th>
                                            <th style="width:60px;">Req.</th>
                                        </tr>
                                    </thead>
                                    <tbody style="background:#0f172a;">
                                        @foreach ($profileMaps as $map)
                                            <tr>
                                                <td style="color:#4b5563; font-size:.7rem;">{{ $map['index'] }}</td>
                                                <td style="font-weight:500; color:#e2e8f0;">
                                                    {{ $map['header'] }}
                                                </td>
                                                <td style="font-size:.78rem;">
                                                    @if ($map['field'])
                                                        <span style="color:#9ca3af;">{{ $map['model'] }}</span>.<span style="color:#7dd3fc;">{{ $map['field'] }}</span>
                                                    @else
                                                        <span style="color:#ef4444; font-size:.7rem;">⚠ not mapped</span>
                                                    @endif
                                                </td>
                                                <td>
                                                    @if ($map['mode'] === 'create_only')
                                                        <span style="font-size:.7rem; color:#fbbf24;">create only</span>
                                                    @elseif ($map['mode'] === 'skip')
                                                        <span style="font-size:.7rem; color:#6b7280;">skip</span>
                                                    @else
                                                        <span style="font-size:.7rem; color:#4ade80;">always</span>
                                                    @endif
                                                </td>
                                                <td style="text-align:center;">
                                                    @if ($map['required'])
                                                        <span style="color:#f87171; font-size:.75rem;">●</span>
                                                    @endif
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                            <div style="padding:.5rem .875rem; background:#111827; border-top:1px solid #1f2937; display:flex; justify-content:flex-end;">
                                <a href="/uf-admin/import-profiles" target="_blank"
                                   style="font-size:.75rem; color:#60a5fa; text-decoration:none; display:flex; align-items:center; gap:.25rem;">
                                    Edit Mappings ↗
                                </a>
                            </div>
                        @endif
                    </div>
                @endif

                <div style="margin-top:1.25rem; display:flex; gap:.75rem; align-items:center; flex-wrap:wrap;">
                    <x-filament::button type="submit" icon="heroicon-o-eye" size="md">
                        Preview Changes
                    </x-filament::button>
                </div>
            </form>
        </div>
    @endif

    {{-- ── STEP 2: PREVIEW ── --}}
    @if ($step === 'preview')
        <div class="imp-card imp-fade" wire:transition.opacity.duration.200ms>
            <h3 style="font-size:1rem;font-weight:700;color:#f9fafb;margin:0 0 .25rem;">Preview Import</h3>
            <p style="font-size:.825rem;color:#9ca3af;margin:0 0 1rem;">ตรวจสอบก่อน — กด <strong style="color:#f9fafb;">Confirm Import</strong> เพื่อดำเนินการ</p>

            <div class="imp-summary-row">
                <span class="imp-chip" style="background:#052e16;color:#4ade80;">CREATE {{ $previewCreate }}</span>
                <span class="imp-chip" style="background:#1e3a5f;color:#60a5fa;">UPDATE {{ $previewUpdate }}</span>
                <span class="imp-chip" style="background:#1c1917;color:#fbbf24;">SKIP {{ $previewSkip }}</span>
                <span class="imp-chip" style="background:#1f2937;color:#9ca3af;">{{ count($previewRows) }} products</span>
            </div>

            <div style="border:1px solid #374151; border-radius:.5rem; overflow:hidden;">
                <table class="imp-table">
                    <thead>
                        <tr>
                            <th style="width:110px;">Action</th>
                            <th>Product</th>
                            <th style="width:72px;">Variants</th>
                            <th>Detail</th>
                        </tr>
                    </thead>
                    <tbody style="background:#111827;">
                        @foreach ($previewRows as $row)
                            @php
                                $badgeClass = match($row['action']) {
                                    'CREATE'        => 'imp-badge-create',
                                    'UPDATE'        => 'imp-badge-update',
                                    'SKIP'          => 'imp-badge-skip',
                                    'DELETE→CREATE' => 'imp-badge-fresh',
                                    default         => '',
                                };
                            @endphp
                            <tr>
                                <td><span class="imp-badge {{ $badgeClass }}">{{ $row['action'] }}</span></td>
                                <td style="font-weight:500;">{{ $row['title'] }}</td>
                                <td>{{ $row['variants'] }}</td>
                                <td style="color:#6b7280;font-size:.78rem;">{{ $row['detail'] }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <div style="margin-top:1.25rem; display:flex; gap:.75rem;">
                <button
                    wire:click="doImport"
                    wire:loading.attr="disabled"
                    wire:loading.class="opacity-75 cursor-wait"
                    style="display:inline-flex;align-items:center;gap:.5rem;padding:.5rem 1rem;border-radius:.5rem;background:#16a34a;color:#fff;font-size:.875rem;font-weight:600;border:none;cursor:pointer;"
                >
                    <svg wire:loading.remove wire:target="doImport" style="width:1rem;height:1rem;" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"/></svg>
                    <span wire:loading.remove wire:target="doImport">Confirm Import</span>
                    <svg wire:loading wire:target="doImport" style="width:1rem;height:1rem;animation:spin 1s linear infinite;" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 2v4M12 18v4M4.93 4.93l2.83 2.83M16.24 16.24l2.83 2.83M2 12h4M18 12h4M4.93 19.07l2.83-2.83M16.24 7.76l2.83-2.83"/></svg>
                    <span wire:loading wire:target="doImport">Importing…</span>
                </button>

                <x-filament::button wire:click="goBack" color="gray" icon="heroicon-o-arrow-left" size="md">
                    Back
                </x-filament::button>
            </div>
        </div>
    @endif

    {{-- ── STEP 3: DONE ── --}}
    @if ($step === 'done')
        <div class="imp-card imp-fade" wire:transition.opacity.duration.200ms>
            <div style="display:flex; align-items:center; gap:1rem; padding:.5rem 0 1.25rem;">
                <div class="imp-done-icon">
                    <svg style="width:1.75rem;height:1.75rem;color:#4ade80;" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z"/>
                    </svg>
                </div>
                <div>
                    <p style="font-size:1rem;font-weight:700;color:#f9fafb;margin:0;">Import Complete</p>
                    <p style="font-size:.85rem;color:#9ca3af;margin:.2rem 0 0;">{{ $resultMessage }}</p>
                </div>
            </div>

            <div style="display:flex;gap:.75rem;">
                <x-filament::button wire:click="resetForm" icon="heroicon-o-arrow-path" color="gray" size="md">
                    Import Another File
                </x-filament::button>
                <x-filament::button
                    tag="a"
                    href="/uf-admin/products"
                    icon="heroicon-o-cube"
                    size="md"
                >
                    View Products
                </x-filament::button>
            </div>
        </div>
    @endif

</div>

<style>
@keyframes spin { to { transform:rotate(360deg); } }
</style>
</x-filament-panels::page>
