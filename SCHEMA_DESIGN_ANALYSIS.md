# Retail Database Architecture - UFicon iStudio

## 1️⃣ ENTITY MAP (Normalized Structure)

```
┌─────────────────────────────────────────────────────────────┐
│                        PRODUCT ECOSYSTEM                      │
├─────────────────────────────────────────────────────────────┤
│
│  brand                          product_category
│  ─────────────────────          ──────────────────
│  • brand_id (PK)                • pc_id (PK)
│  • brand_name                   • pc_name
│  • brand_code                   • pc_description
│  • created_at / updated_at      • created_at / updated_at
│           ▲                               ▲
│           │ (1:N)                        │ (N:M)
│           │                              │
│      ┌────┴──────────────────────────────┴──────┐
│      │                                           │
│  product                                    product_category_map
│  ──────────────                            ──────────────────────
│  • pd_id (PK)                             • pcm_id (PK)
│  • brand_id (FK) ─────────────────────→  product maps
│  • pd_name                                • pc_id (FK)
│  • pd_description                         • pd_id (FK)
│  • pd_status (active/inactive)            • uq: (pd_id, pc_id)
│  • price                                  • created_at
│  • created_at / updated_at / deleted_at
│           ▲
│           │ (1:N)
│           │
│      ┌────┴─────────────────────┐
│      │                           │
│  inventory              order_item
│  ───────────            ──────────────
│  • inv_id (PK)          • oi_id (PK)
│  • pd_id (FK)           • ord_id (FK)
│  • st_id (FK)           • pd_id (FK)
│  • qty_available        • oi_quantity
│  • qty_reserved         • oi_price
│  • qty_damaged          • created_at
│  • last_counted_at
│  • idx: (pd_id, st_id)
│  • created_at/updated_at
│      ▲
│      │
│  ┌───┘
│  │
│  └─────(Many stores stock tracking)
│
│
│  store                              order
│  ──────────────────                ──────────
│  • st_id (PK)                      • ord_id (PK)
│  • brand_id (FK)                   • st_id (FK)
│  • st_name                         • ord_customer_name
│  • st_address                      • ord_total_amount
│  • st_code                         • ord_status (VARCHAR)
│  • latitude / longitude            • ord_date
│  • st_is_active                    • created_at/updated_at
│  • created_at/updated_at
│
└─────────────────────────────────────────────────────────────┘
```

---

## 2️⃣ SCHEMA DEFINITION (Exact Columns & Types)

### **brand** (Brand Master)
```
brand_id          BIGINT PRIMARY KEY AUTO_INCREMENT
brand_name        VARCHAR(255) NOT NULL UNIQUE
brand_code        VARCHAR(50) NOT NULL UNIQUE
brand_icon        VARCHAR(255) NULL  -- path to icon
created_at        TIMESTAMP DEFAULT CURRENT_TIMESTAMP
updated_at        TIMESTAMP DEFAULT CURRENT_TIMESTAMP
deleted_at        TIMESTAMP NULL (soft delete)
```

### **product_category** (Category Master)
```
pc_id             BIGINT PRIMARY KEY AUTO_INCREMENT
pc_name           VARCHAR(255) NOT NULL UNIQUE
pc_description    TEXT NULL
pc_status         VARCHAR(20) DEFAULT 'active'
created_at        TIMESTAMP DEFAULT CURRENT_TIMESTAMP
updated_at        TIMESTAMP DEFAULT CURRENT_TIMESTAMP
deleted_at        TIMESTAMP NULL
```

### **product** ⭐ (Main Product Table)
```
pd_id             BIGINT PRIMARY KEY AUTO_INCREMENT
brand_id          BIGINT NOT NULL (FK: brand.brand_id)
pd_name           VARCHAR(255) NOT NULL
pd_description    TEXT NULL
pd_sku            VARCHAR(100) UNIQUE NOT NULL
price             DECIMAL(12, 2) NOT NULL
pd_status         VARCHAR(20) DEFAULT 'active'  -- active/inactive
created_at        TIMESTAMP DEFAULT CURRENT_TIMESTAMP
updated_at        TIMESTAMP DEFAULT CURRENT_TIMESTAMP
deleted_at        TIMESTAMP NULL (soft delete)

Indexes:
- PRIMARY KEY: pd_id
- FOREIGN KEY: (brand_id) → brand.brand_id
- UNIQUE: pd_sku
- INDEX: idx_product_brand_id
- INDEX: idx_product_status
- INDEX: idx_product_active (WHERE deleted_at IS NULL)
```

### **product_category_map** (N:M Relationship)
```
pcm_id            BIGINT PRIMARY KEY AUTO_INCREMENT
pd_id             BIGINT NOT NULL (FK: product.pd_id)
pc_id             BIGINT NOT NULL (FK: product_category.pc_id)
created_at        TIMESTAMP DEFAULT CURRENT_TIMESTAMP

Constraints:
- FOREIGN KEY: (pd_id) → product.pd_id ON DELETE CASCADE
- FOREIGN KEY: (pc_id) → product_category.pc_id ON DELETE CASCADE
- UNIQUE: (pd_id, pc_id)
- INDEX: idx_pcm_pd_id
- INDEX: idx_pcm_pc_id
```

### **store** ⭐ (Store/Branch Master - with proper prefix)
```
st_id             BIGINT PRIMARY KEY AUTO_INCREMENT
brand_id          BIGINT NOT NULL (FK: brand.brand_id)
st_name           VARCHAR(255) NOT NULL
st_address        TEXT NULL
st_full_address   TEXT NULL
st_code           VARCHAR(50) UNIQUE NOT NULL
latitude          DECIMAL(10, 8) NULL
longitude         DECIMAL(11, 8) NULL
google_map_url    VARCHAR(500) NULL
st_phone          JSON NULL  -- multiple phones
st_contact_links  JSON NULL  -- Line, Facebook, etc
images            JSON NULL  -- image URLs
st_is_active      BOOLEAN DEFAULT TRUE
created_at        TIMESTAMP DEFAULT CURRENT_TIMESTAMP
updated_at        TIMESTAMP DEFAULT CURRENT_TIMESTAMP
deleted_at        TIMESTAMP NULL

Indexes:
- PRIMARY KEY: st_id
- FOREIGN KEY: (brand_id) → brand.brand_id
- UNIQUE: st_code
- INDEX: idx_store_brand_id
- INDEX: idx_store_active (WHERE deleted_at IS NULL)
```

### **inventory** ⭐ KEY TABLE: Stock Per Store
```
inv_id            BIGINT PRIMARY KEY AUTO_INCREMENT
pd_id             BIGINT NOT NULL (FK: product.pd_id)
st_id             BIGINT NOT NULL (FK: store.st_id)
qty_available     INT DEFAULT 0  -- usable stock
qty_reserved      INT DEFAULT 0  -- held for orders
qty_damaged       INT DEFAULT 0  -- defective/returns
qty_total         INT GENERATED ALWAYS AS (qty_available + qty_reserved + qty_damaged)
last_counted_at   TIMESTAMP NULL
created_at        TIMESTAMP DEFAULT CURRENT_TIMESTAMP
updated_at        TIMESTAMP DEFAULT CURRENT_TIMESTAMP
deleted_at        TIMESTAMP NULL

Constraints:
- FOREIGN KEY: (pd_id) → product.pd_id ON DELETE CASCADE
- FOREIGN KEY: (st_id) → store.st_id ON DELETE CASCADE
- UNIQUE: (pd_id, st_id)
- CHECK: qty_available >= 0 AND qty_reserved >= 0 AND qty_damaged >= 0

Indexes:
- PRIMARY KEY: inv_id
- UNIQUE: idx_inventory_pd_st (pd_id, st_id)
- INDEX: idx_inventory_pd_id
- INDEX: idx_inventory_st_id
- INDEX: idx_inventory_low_stock (WHERE qty_available < 10)
```

### **order** (Order Master)
```
ord_id            BIGINT PRIMARY KEY AUTO_INCREMENT
st_id             BIGINT NOT NULL (FK: store.st_id)
ord_customer_name VARCHAR(255) NOT NULL
ord_total_amount  DECIMAL(12, 2) NOT NULL
ord_status        VARCHAR(20) DEFAULT 'pending'  -- pending/processing/completed/cancelled
ord_date          DATE NOT NULL
created_at        TIMESTAMP DEFAULT CURRENT_TIMESTAMP
updated_at        TIMESTAMP DEFAULT CURRENT_TIMESTAMP
deleted_at        TIMESTAMP NULL

Constraints:
- FOREIGN KEY: (st_id) → store.st_id ON DELETE RESTRICT

Indexes:
- PRIMARY KEY: ord_id
- FOREIGN KEY: (st_id) → store.st_id
- INDEX: idx_order_st_id
- INDEX: idx_order_status
- INDEX: idx_order_date
```

### **order_item** (Order-Product Join)
```
oi_id             BIGINT PRIMARY KEY AUTO_INCREMENT
ord_id            BIGINT NOT NULL (FK: order.ord_id)
pd_id             BIGINT NOT NULL (FK: product.pd_id)
oi_quantity       INT NOT NULL CHECK (oi_quantity > 0)
oi_price          DECIMAL(12, 2) NOT NULL  -- price at time of order
created_at        TIMESTAMP DEFAULT CURRENT_TIMESTAMP
updated_at        TIMESTAMP DEFAULT CURRENT_TIMESTAMP

Constraints:
- FOREIGN KEY: (ord_id) → order.ord_id ON DELETE CASCADE
- FOREIGN KEY: (pd_id) → product.pd_id ON DELETE RESTRICT

Indexes:
- PRIMARY KEY: oi_id
- FOREIGN KEY: (ord_id) → order.ord_id
- FOREIGN KEY: (pd_id) → product.pd_id
- INDEX: idx_oi_ord_id
- INDEX: idx_oi_pd_id
```

---

## 3️⃣ MIGRATION ORDER

**Parent → Child dependency chain:**

1. `brand` (no dependencies)
2. `product_category` (no dependencies)
3. `product` (depends: brand)
4. `product_category_map` (depends: product, product_category)
5. `store` (depends: brand)
6. `inventory` ⭐ (depends: product, store) **← Stock per store!**
7. `order` (depends: store)
8. `order_item` (depends: order, product)

---

## 4️⃣ INDEX PLAN

### Performance-Critical Indexes

| Table | Index Name | Columns | Purpose |
|-------|-----------|---------|---------|
| product | idx_product_brand_id | (brand_id) | FK lookup |
| product | idx_product_status | (pd_status) | Filter active products |
| product | idx_product_active | (pd_id) WHERE deleted_at IS NULL | Active products query |
| inventory | idx_inventory_pd_st | **(pd_id, st_id)** | **Primary query: stock by product+store** |
| inventory | idx_inventory_st_id | (st_id) | All stock in a store |
| inventory | idx_inventory_pd_id | (pd_id) | All stores stocking a product |
| inventory | idx_inventory_low_stock | (st_id) WHERE qty_available < 10 | Low stock alerts |
| store | idx_store_brand_id | (brand_id) | Stores per brand |
| store | idx_store_active | (st_id) WHERE deleted_at IS NULL | Active stores |
| order | idx_order_st_id | (st_id) | Orders in a store |
| order | idx_order_status | (ord_status) | Filter by order status |
| order | idx_order_date | (ord_date) | Date-based queries |
| order_item | idx_oi_ord_id | (ord_id) | Items in an order |
| order_item | idx_oi_pd_id | (pd_id) | Which orders have product |

---

## 5️⃣ KEY RELATIONSHIPS & QUERIES

### Example: Query Stock Across All Stores for Product

```sql
-- Get total stock of product PD123 across all stores
SELECT 
    p.pd_name,
    SUM(i.qty_available) as total_available,
    SUM(i.qty_reserved) as total_reserved,
    SUM(i.qty_damaged) as total_damaged
FROM inventory i
JOIN product p ON i.pd_id = p.pd_id
WHERE p.pd_id = 123 AND i.deleted_at IS NULL
GROUP BY p.pd_id, p.pd_name;

-- Get stock of product PD123 in store ST5
SELECT 
    s.st_name,
    i.qty_available,
    i.qty_reserved,
    i.qty_damaged,
    i.last_counted_at
FROM inventory i
JOIN store s ON i.st_id = s.st_id
WHERE i.pd_id = 123 AND i.st_id = 5;

-- Low stock alert: products with <10 qty in any store
SELECT DISTINCT
    p.pd_name,
    s.st_name,
    i.qty_available
FROM inventory i
JOIN product p ON i.pd_id = p.pd_id
JOIN store s ON i.st_id = s.st_id
WHERE i.qty_available < 10
ORDER BY i.qty_available ASC;
```

---

## 6️⃣ MAJOR IMPROVEMENTS

| Old | New | Benefit |
|-----|-----|---------|
| `products.stock_quantity` (single) | `inventory` table (per store) | ✅ Track stock by branch |
| `category` string | `product_category` table | ✅ Manage categories, prevent typos |
| `orders` only | `order_item` join table | ✅ Multiple items per order |
| No `brand_id` in store | `store.brand_id` → brand.brand_id | ✅ Brand-store relationship |
| No soft delete | All tables have `deleted_at` | ✅ Data audit trail |
| No proper prefixes | `pd_id`, `st_id`, `pc_id`, etc. | ✅ Clear naming convention |
| ENUM status | VARCHAR(20) | ✅ Easy to add new statuses |
| No indexes | Strategic indexes on FK + queries | ✅ Fast lookups at 1M+ rows |

---

## ⚡ NOTES

- **Inventory tracking**: `qty_available + qty_reserved + qty_damaged = qty_total` (calculated)
- **Soft deletes**: Use `WHERE deleted_at IS NULL` in all queries to exclude deleted records
- **Stock reservation**: When order placed, move qty from `qty_available` → `qty_reserved`. On complete: → `qty_available` (consumed)
- **Partial indexes**: Use `WHERE deleted_at IS NULL` on composite indexes for active-only queries (PostgreSQL/MySQL 8+)
- **Category mapping**: Products can have multiple categories via `product_category_map`
