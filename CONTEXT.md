# CONTEXT.md — iStudio Product Catalog (uficon backend)

> Glossary only. No implementation details. No specs.
> Last updated: 2026-06-10

---

## Glossary

**LOB (Line of Business)**
หมวดหมู่สินค้าหลักระดับบนสุด เก็บใน `pd_lob` บน Product
ตัวอย่าง: Mac, iPhone, iPad, Apple Watch, AirPods, Accessories
ใช้เป็น URL segment: `/pages/view-all-mac`

**Sub-LOB**
หมวดหมู่ย่อยภายใต้ LOB ระบุซีรีส์สินค้า เก็บใน `pd_sub_lob` บน Product
ตัวอย่าง: MacBook Pro, iPhone 17 Pro, iPad mini
ใช้เป็น URL segment: `/collections/macbook-pro`

**CaaS (Commerce as a Service)**
Upstream data source จาก Apple ส่งออกมาเป็น XLSX Flat File
รูปแบบ: 1 row = 1 variant (MPN level)
ข้อมูลที่ได้: MPN, Barcode, Handle, Option values, Image URLs

**MPN (Manufacturer Part Number)**
identifier ระดับ variant (ไม่ใช่ Product) เก็บใน `product_variant.pv_sku`
1 MPN = 1 Barcode = 1 ProductVariant

**ProductCollection**
Source of truth สำหรับ **Merchandising** — ตอบว่า "Product นี้มี option อะไรบ้าง?"
มาจาก CaaS "Smart Collection" sheet (1 ProductCollection : ~1 Product)
เก็บ: `pcol_option_labels` (Color, Storage, RAM), `pcol_option_values` (ค่าที่เป็นไปได้)
ขับเคลื่อน: configurator UI บน PDP (สลับสี / สลับ storage), FamilyStripe ใน PDP context
ไม่ใช่ navigation concept — ไม่ใช้สำหรับ grouping บน PLP/LOB

**LobDisplayCollection**
Source of truth สำหรับ **Navigation** — ตอบว่า "เมนู Mac → MacBook Pro เอาอะไรโชว์?"
แมป (pd_lob + pd_sub_lob) → slug → URL
เก็บ display config: title, tagline, image, button label, sort order, featured flag
ขับเคลื่อน: LOBPage, PLPPage heading, FamilyStripe บน LOBPage/PLPPage
ถูก auto-create จาก import — ไม่มี FK ไปหา Product (query ผ่าน `pd_sub_lob` string match)

**ProductGallery**
กลุ่มรูปภาพของ Product แยกตาม color (1 gallery ต่อ 1 color)
key: (pd_id, pg_slug) unique
ตัวอย่าง: iPhone 17 Pro → gallery "สีเงิน", gallery "น้ำเงินเข้ม"

**ProductMedia**
รูปภาพหรือวิดีโอ 1 รายการ ผูกกับ ProductGallery (pg_id) เสมอหลัง gallery refactor
ประเภท: `image`, `video`, `swatch`, `band_swatch`

**FamilyStripe**
UI component — แถบรูปภาพสินค้าแนวนอนใต้ Global Navigation
behavior ต่างกันตาม page context:
- LOBPage: แสดง Sub-LOB groups (LobDisplayCollection items)
- PLPPage: แสดง Products ใน collection นั้น → link to PDP
- PDPPage: แสดง sibling products ใน Sub-LOB เดียวกัน → cross-sell

**PLP (Product Listing Page)**
URL: `/collections/{sub-lob-slug}`
แสดง Products ที่ `pd_sub_lob` ตรงกับ slug

**LOBPage**
URL: `/pages/view-all-{lob}`
แสดง Sub-LOB groups ทั้งหมดภายใต้ LOB นั้น

**PDP (Product Detail Page)**
URL: `/products/{pd_handle}`
แสดงข้อมูล Product 1 รายการพร้อม variant configuration

---

## Data Relationships

```
LOB (pd_lob)
  └─ 1:N Sub-LOB (pd_sub_lob)
        └─ 1:N Product
              ├─ 1:1 ProductCollection   ← CaaS option metadata only
              ├─ 1:N ProductVariant      ← each variant has 1 MPN + 1 Barcode
              ├─ 1:N ProductGallery      ← 1 gallery per color
              │     └─ 1:N ProductMedia
              └─ (navigated via) LobDisplayCollection
                    └─ maps (ldc_lob + ldc_sub_lob) → Product query by pd_sub_lob
```

**Important**: `LobDisplayCollection` ไม่มี FK ไปหา `Product` โดยตรง
— query products ผ่าน `pd_sub_lob` string match เท่านั้น

---

## PDP Content Generation Pipeline

**Current state (Manual):**
`pdp_content` JSON เป็น Filament Builder blocks ที่ admin กรอกด้วยตนเอง
ขับเคลื่อน: processor/memory/storage configurator + tagline + specs บน PDP

**CaaS Blueprint (auto-generate target):**
`pcol_option_labels` (ProductCollection) = option schema จาก CaaS (e.g., "Color", "Storage", "RAM")
`product_option` (ProductOption table) = Thai labels import ไว้แล้ว

**Planned pipeline:**
```
pcol_option_labels + product_option
  → Auto-generate pdp_content configurator blocks
  → Pre-fill processor/memory/storage items จาก CaaS variant data
  → Admin review/override via Filament Builder
```
เป้าหมาย: ลด manual entry จาก 100% → admin เห็น pre-filled draft ที่แก้ได้

---

## Import Pipeline

```
CaaS XLSX
  → ImportCaasProducts command
  → ProductCollection (upsert, CaaS Smart Collection sheet)
  → Product (group by pd_primary_title)
  → ProductVariant (1 per CaaS row)
  → ProductGallery (1 per unique pd_option1 color, Thai-safe slug)
  → ProductMedia (per color gallery)
  → LobDisplayCollection (auto-sync, skip if exists)
```

---

## Rules & Constraints

### Thai Language Slug Fallback Rule
`Str::slug()` strips non-ASCII characters → Thai color names produce `""` (empty string)

**Rule**: ทุก slug ที่ใช้เป็น key ของ gallery หรือ color identifier ต้องใช้ pattern:
```php
Str::slug($name) ?: 'color-' . substr(md5($name), 0, 8)
```

**Scope ที่ต้อง enforce:**
- `ProductGallery.pg_slug` (gallery keyed by color) ✅
- `ProductPdpResource.buildColors().color.id` ✅ fixed 2026-06-10
- `ImportCaasProducts.gallerySlug()` ✅
- ทุก pipeline ในอนาคตที่สร้าง slug จากชื่อสีภาษาไทย

**Why critical**: frontend ใช้ `color.id` เพื่อ map → `ProductGallery.pg_slug` สำหรับ gallery image switching บน PDP — ถ้า id ไม่ตรงกับ slug กลไกสลับรูปพัง

### Deterministic Representative Image Rule
เมื่อระบบต้องการ "ภาพตัวแทน" ของ Product (LOBPage row, SEO OG image, FamilyStripe chip):

**Rule**: ดึงภาพแรกจาก Gallery ของ `defaultColor` เท่านั้น
```
defaultColor → ProductGallery (pg_slug = color.id of defaultColor) → media[0]
```

**ห้าม**: `productLevelMedia()->first()` — non-deterministic, ขึ้นกับ insert order

**`productLevelMedia()` status**: Legacy — กำลัง deprecate
- เดิม: `whereNull('pv_id')` เพื่อแยก "product-level" vs "variant-level" images
- หลัง gallery refactor: ทุก image มี `pv_id = NULL` + `pg_id` set → method นี้คืน ALL images แทน
- Target state (C): ทุก image ต้องผ่าน Gallery เสมอ — ไม่มี "product-level image" ที่ไม่อยู่ใน gallery ใด

### ProductCollection vs LobDisplayCollection: Source of Truth
- Navigation & PLP → `LobDisplayCollection` (keyed by `pd_sub_lob`)
- Merchandising & PDP Configurator → `ProductCollection` (keyed by `pcol_handle`)
- ห้ามใช้สลับกัน — เป็น independent systems

---

## API Routes (v1)

| Method | Path | Returns |
|--------|------|---------|
| GET | `/api/v1/lob/{lob}/collections` | LobDisplayCollection items for LOBPage |
| GET | `/api/v1/collections/{slug}` | Single LobDisplayCollection (PLP metadata) |
| GET | `/api/v1/collections/{slug}/products` | Products by pd_sub_lob |
| GET | `/api/v1/products/{handle}/pdp` | Full PDP JSON incl. subLobSlug |
