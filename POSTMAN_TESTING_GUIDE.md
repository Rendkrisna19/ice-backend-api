# ICE Backend API - Quick Setup & Postman Testing Guide

## Quick Setup

### 1. Prerequisites
- PHP 8.2+
- MySQL 8.0+
- Composer
- Node.js & npm

### 2. Environment Setup
```bash
# Copy environment file
cp .env.example .env

# Install dependencies
composer install

# Generate app key
php artisan key:generate

# Create database
mysql -u root -e "CREATE DATABASE ice_backend;"

# Run migrations
php artisan migrate

# Seed database with test data
php artisan db:seed

# Start server
php artisan serve --port 8000
```

Server akan berjalan di: **http://localhost:8000**

---

## Postman Collection Setup

### 1. Import Collection
1. Buka Postman
2. Klik "Import" → pilih file `ICE_Backend_API.postman_collection.json`
3. Collection akan ter-import dengan semua endpoints

### 2. Set Environment Variables
Sebelum testing, pastikan set variables di Postman (atau gunakan nilai default):

- **base_url**: `http://localhost:8000/api` ✅ (sudah set)
- **token**: Token dari customer login
- **cashier_token**: Token dari cashier login  
- **driver_token**: Token dari driver login
- **admin_token**: Token dari admin login

---

## Register & OTP Testing (Manual via Postman)

### 1. Request OTP
- **Endpoint:** `POST {{base_url}}/v1/auth/register/request-otp`
- **Body (JSON):**
  ```json
  {
    "email": "user@email.com"
  }
  ```
- **Response:**
  ```json
  {
    "success": true,
    "message": "OTP terkirim ke email.",
    "data": {
      "email": "user@email.com",
      "expires_at": "2026-02-19T12:34:56.000000Z"
    }
  }
  ```

### 2. Verifikasi OTP & Daftar
- **Endpoint:** `POST {{base_url}}/v1/auth/register/verify-otp`
- **Body (JSON):**
  ```json
  {
    "name": "Nama Lengkap",
    "email": "user@email.com",
    "password": "password123",
    "password_confirmation": "password123",
    "phone": "08123456789",
    "otp": "123456"
  }
  ```
- **Response sukses:**
  ```json
  {
    "success": true,
    "message": "User registered successfully",
    "data": {
      "user": { ... },
      "token": "..."
    }
  }
  ```
- **Response gagal:**
  ```json
  {
    "success": false,
    "message": "OTP tidak valid.",
    "data": null
  }
  ```

> Gunakan response token untuk autentikasi selanjutnya (set ke variable `token` di Postman).

---

## Testing Workflow

### Step 1: Login dengan Berbagai Role

#### 1a. Login as Customer
```
POST /v1/auth/login
Body:
{
  "email": "customer1@example.com",
  "password": "password123"
}
```
**Copy token response → Set ke variable `{{token}}`**

#### 1b. Login as Cashier
```
POST /v1/auth/login
Body:
{
  "email": "cashier1@example.com",
  "password": "password123"
}
```
**Copy token response → Set ke variable `{{cashier_token}}`**

#### 1c. Login as Driver
```
POST /v1/auth/login
Body:
{
  "email": "driver1@example.com",
  "password": "password123"
}
```
**Copy token response → Set ke variable `{{driver_token}}`**

#### 1d. Login as Admin
```
POST /v1/auth/login
Body:
{
  "email": "admin@example.com",
  "password": "password123"
}
```
**Copy token response → Set ke variable `{{admin_token}}`**

---

### Step 2: Customer Order Flow

#### 2a. List Outlets
```
GET /v1/outlets
```
Lihat outlets yang tersedia

#### 2b. Get Outlet Details
```
GET /v1/outlets/1
```
Lihat detail outlet beserta produk-produknya

#### 2c. Get Products
```
GET /v1/products
```
atau

```
GET /v1/products/category?category=Makanan
```

#### 2d. Validate Checkout
```
POST /v1/customer/orders/validate-checkout
Headers: Authorization: Bearer {{token}}
Body:
{
  "outlet_id": 1,
  "items": [
    {
      "product_id": 1,
      "quantity": 2
    },
    {
      "product_id": 4,
      "quantity": 1
    }
  ]
}
```

#### 2e. Create Order
```
POST /v1/customer/orders
Headers: Authorization: Bearer {{token}}
Body:
{
  "outlet_id": 1,
  "items": [
    {
      "product_id": 1,
      "quantity": 2,
      "variant_snap": {
        "level": "pedas",
        "notes": "tambah telur"
      }
    },
    {
      "product_id": 4,
      "quantity": 1
    }
  ],
  "delivery_address": "Jl. Sudirman No. 123, Jakarta",
  "delivery_latitude": -6.2088,
  "delivery_longitude": 106.8456,
  "distance_real": 2.5
}
```

**Response akan berisi order_id untuk step berikutnya**

#### 2f. Get Customer Orders
```
GET /v1/customer/orders
Headers: Authorization: Bearer {{token}}
```

#### 2g. Get Single Order
```
GET /v1/customer/orders/1
Headers: Authorization: Bearer {{token}}
```

---

### Step 3: Merchant (Cashier) Flow

#### 3a. Login as Cashier (jika belum)
Lihat Step 1b di atas

#### 3b. Get All Orders for Outlet
```
GET /v1/merchant/orders
Headers: Authorization: Bearer {{cashier_token}}
```

#### 3c. Get Available Drivers
```
GET /v1/merchant/drivers/available
Headers: Authorization: Bearer {{cashier_token}}
```

#### 3d. Reject Pending Order (OPTIONAL - jika ingin reject)
```
POST /v1/merchant/orders/1/reject
Headers: Authorization: Bearer {{cashier_token}}
```

⚠️ **HANYA bisa reject saat order masih `pending`**
- Status akan berubah menjadi `cancelled`
- Order tidak akan dilanjutkan ke preparing
- Trigger notification ke admin untuk manual refund

✅ **ATAU lanjutkan ke status preparing jika accept order**

#### 3e. Update Order Status (Step 1: preparing)
```
PUT /v1/merchant/orders/1/status
Headers: Authorization: Bearer {{cashier_token}}
Body:
{
  "status": "preparing"
}
```

✅ **Order masuk ke kitchen**

#### 3f. Assign Driver to Order (DURING preparing status)
```
POST /v1/merchant/orders/1/assign-driver
Headers: Authorization: Bearer {{cashier_token}}
Body:
{
  "driver_id": 4
}
```

✅ **Driver sudah dipilih, siap untuk pengantaran**

#### 3f2. Update Order Status (Step 2: ready - AFTER assigning driver)
```
PUT /v1/merchant/orders/1/status
Headers: Authorization: Bearer {{cashier_token}}
Body:
{
  "status": "ready"
}
```

✅ **Orderan siap, menunggu driver untuk pickup**

#### 3f3. Update Order Status (Step 3: on_delivery)
```
PUT /v1/merchant/orders/1/status
Headers: Authorization: Bearer {{cashier_token}}
Body:
{
  "status": "on_delivery"
}
```

✅ **Driver sudah pickup dan mulai pengantaran**

**Status progression (Cashier):** `pending` → `preparing` → **[ASSIGN DRIVER]** → `ready` → `on_delivery`

**Cashier valid status transitions:**
- `preparing` - Sedang disiapkan di kitchen
- `ready` - Sudah siap, menunggu driver pickup
- `on_delivery` - Sedang diantar oleh driver

#### 3g. Get Outlet Status
```
GET /v1/merchant/outlet/status
Headers: Authorization: Bearer {{cashier_token}}
```

✅ **Cek info outlet (jam operasional, apakah buka/tutup, dll)**

#### 3h. Toggle Menu Availability
```
POST /v1/merchant/menu/toggle-availability
Headers: Authorization: Bearer {{cashier_token}}
Body:
{
  "product_id": 1,
  "is_available": false
}
```

📝 **Penjelasan Toggle Menu Availability:**
- Endpoint ini untuk enable/disable menu tertentu di outlet
- Contoh use case:
  - ❌ Menu "Nasi Goreng" habis → set `is_available: false`
  - ✅ Menu "Nasi Goreng" kembali ada → set `is_available: true`
- Ketika menu di-disable, customer tidak bisa order menu itu
- Berguna untuk inventory management tanpa harus hapus produk dari database

#### 3i. Force Close Outlet
```
POST /v1/merchant/outlet/force-close
Headers: Authorization: Bearer {{cashier_token}}
```

❌ **Tutup outlet secara paksa (di luar jam operasional normal)**
- Contoh: Kondisi darurat, stok habis, ada masalah teknis

#### 3j. Force Open Outlet
```
POST /v1/merchant/outlet/force-open
Headers: Authorization: Bearer {{cashier_token}}
```

✅ **Buka outlet secara paksa**
- Override jam operasional normal
- Contoh: Buka lebih awal karena ada event khusus

#### 3k. Additional Merchant Features (Other Endpoints)

---

### Step 4: Driver Delivery Flow

#### 4a. Login as Driver (jika belum)
Lihat Step 1c di atas

#### 4b. Clock In
```
POST /v1/driver/shift/clock-in
Headers: Authorization: Bearer {{driver_token}}
```

#### 4c. Get Assigned Orders
```
GET /v1/driver/orders
Headers: Authorization: Bearer {{driver_token}}
```

✅ **Lihat orderan dengan status `ready` yang sudah di-assign**

#### 4d. Accept Order (pick up order yang sudah ready)
```
POST /v1/driver/orders/1/accept
Headers: Authorization: Bearer {{driver_token}}
```

✅ **Driver pickup order dari outlet**

#### 4e. Mark Order as Paid (Cash Payment Received)
```
POST /v1/driver/orders/1/paid
Headers: Authorization: Bearer {{driver_token}}
```

✅ **Driver terima pembayaran cash dari customer**

#### 4f. Complete Delivery
```
POST /v1/driver/orders/1/complete
Headers: Authorization: Bearer {{driver_token}}
```

✅ **Order selesai, customer menerima makanan**

#### 4g. Clock Out
```
POST /v1/driver/shift/clock-out
Headers: Authorization: Bearer {{driver_token}}
```

#### 4h. Get Driver Status
```
GET /v1/driver/status
Headers: Authorization: Bearer {{driver_token}}
```
POST /v1/driver/shift/clock-out
Headers: Authorization: Bearer {{driver_token}}
```

#### 4h. Get Driver Status
```
GET /v1/driver/status
Headers: Authorization: Bearer {{driver_token}}
```

---

### Step 5: Admin Management

#### 5a. Login as Admin (jika belum)
Lihat Step 1d di atas

#### 5b. Get Pricing Configuration
```
GET /v1/admin/pricing
Headers: Authorization: Bearer {{admin_token}}
```

#### 5c. Update Pricing Configuration
```
PUT /v1/admin/pricing
Headers: Authorization: Bearer {{admin_token}}
Body:
{
  "delivery_base_price": 6000,
  "delivery_base_distance": 1.5,
  "delivery_price_per_km": 2500,
  "tax_percentage": 12
}
```

#### 5d. List Outlets
```
GET /v1/admin/outlets
Headers: Authorization: Bearer {{admin_token}}
```

#### 5e. Create New Outlet
```
POST /v1/admin/outlets
Headers: Authorization: Bearer {{admin_token}}
Body:
{
  "name": "Resto Baru",
  "slug": "resto-baru",
  "address": "Jl. Gatot Subroto No. 789",
  "phone": "021-5555555",
  "whatsapp_number": "628555555555",
  "opening_hour": "10:00:00",
  "closing_hour": "22:00:00"
}
```

#### 5f. Update Outlet
```
PUT /v1/admin/outlets/1
Headers: Authorization: Bearer {{admin_token}}
Body:
{
  "name": "Resto Updated Name"
}
```

#### 5g. Delete Outlet
```
DELETE /v1/admin/outlets/3
Headers: Authorization: Bearer {{admin_token}}
```

#### 5h. Get Refund Orders
```
GET /v1/admin/refunds
Headers: Authorization: Bearer {{admin_token}}
```

#### 5i. Process Refund
```
POST /v1/admin/refunds/1/process
Headers: Authorization: Bearer {{admin_token}}
Body:
{
  "refund_amount": 50000
}
```

#### 5j. Get Reporting Data
```
GET /v1/admin/reporting
Headers: Authorization: Bearer {{admin_token}}

Optional query parameters:
?outlet_id=1
?start_date=2026-01-01&end_date=2026-01-31
?outlet_id=1&start_date=2026-01-01&end_date=2026-01-31
```

---

## Test Data

Setelah seed, database sudah berisi:

### Users
- **Admin**: admin@example.com / password123
- **Cashier 1**: cashier1@example.com / password123
- **Cashier 2**: cashier2@example.com / password123
- **Driver 1**: driver1@example.com / password123
- **Driver 2**: driver2@example.com / password123
- **Driver 3**: driver3@example.com / password123
- **Customer 1**: customer1@example.com / password123
- **Customer 2**: customer2@example.com / password123

### Outlets
1. **Resto Nusantara** (ID: 1)
   - Jam: 09:00 - 21:00
   - Lokasi: Jl. Sudirman No. 123, Jakarta Pusat
   - Kasir: cashier1@example.com
   - Driver: driver1@example.com, driver2@example.com

2. **Cafe Cozy** (ID: 2)
   - Jam: 08:00 - 22:00
   - Lokasi: Jl. Gatot Subroto No. 456, Jakarta Selatan
   - Kasir: cashier2@example.com
   - Driver: driver3@example.com

### Products
1. Nasi Goreng Spesial - Rp 35.000
2. Mie Ayam Pangsit - Rp 28.000
3. Soto Ayam Tradisional - Rp 25.000
4. Es Teh Manis - Rp 8.000
5. Kopi Hitam - Rp 12.000
6. Cappuccino - Rp 28.000

---

## Response Format

### Success Response
```json
{
  "success": true,
  "message": "Operation successful",
  "data": {
    // response data
  }
}
```

### Error Response
```json
{
  "success": false,
  "message": "Error description",
  "data": null
}
```

---

## Important Notes

1. **Tokens**: Copy token dari response login dan set ke Postman variable
2. **Order Status Flow**: Pastikan status berubah sesuai urutan yang benar
3. **Driver Assignment**: Driver harus `is_online = true` dan `is_busy = false`
4. **Delivery Fee Calculation**: 
   - Formula: `BasePrice + Max(0, (Distance - BaseDistance) × PricePerKM)`
   - Contoh: 5000 + Max(0, (2.5 - 1) × 2000) = 5000 + 3000 = 8000

5. **Tax Calculation**: Tax hanya dihitung dari subtotal, bukan delivery fee

---

## Troubleshooting

### "Unauthorized" error
- Pastikan token sudah di-set dengan benar
- Token mungkin sudah expired, login ulang

### "Outlet not found" error
- Pastikan outlet_id ada di database
- Cek list outlets dengan GET /v1/outlets

### "Driver not available" error
- Driver harus `is_online = true` dan `is_busy = false`
- Lakukan Clock In terlebih dahulu

### Database error
- Pastikan MySQL running
- Jalankan `php artisan migrate` ulang jika perlu
- Gunakan `php artisan migrate:fresh --seed` untuk reset lengkap

---

## API Features Implemented

✅ **Authentication** - Register, Login, Logout dengan Sanctum tokens

✅ **Customer Features**
- Browse outlets & products
- Validate checkout
- Create orders dengan snapshot data
- View order history
- Cancel orders
- Search outlets & products

✅ **Merchant Features**
- View incoming orders
- Update order status
- Assign drivers
- Toggle menu availability
- Force close/open outlet
- View outlet status

✅ **Driver Features**
- Clock in/out
- View assigned orders
- Accept orders
- Complete delivery
- View driver status

✅ **Admin Features**
- Manage outlets (CRUD)
- Configure pricing rules
- View refunds
- Process refunds
- Generate reports

---

Selamat testing! 🚀


---

## Complete End-to-End Workflow Example

### Flow Diagram
```
Customer          Cashier           Driver
   |                 |                |
   |-- Create ------>|                |
   |   Order         |                |
   |             Update to           |
   |             preparing           |
   |                 |                |
   |             Assign Driver        |
   |                 |----Driver----->|
   |                 |                |
   |                 |-- Update to    |
   |                 |   ready        |
   |                 |                |
   |                 |-- Update to    |
   |                 |   on_delivery  |
   |                 |                |
   |                 |            Accept
   |                 |            Order
   |                 |          (Pickup)
   |                 |                |
   |                 |            (Delivery)
   |                 |                |
   |                 |            Mark as
   |                 |            Paid
   |                 |          (Collect Cash)
   |                 |                |
   |                 |            Complete
   |                 |            Delivery
```

### Test Execution Steps

**1. Customer Creates Order**
- Endpoint: `POST /v1/customer/orders`
- Response: Get `order_id` (e.g., 123)

**2. Cashier Updates Status: preparing**
- Endpoint: `PUT /v1/merchant/orders/123/status`
- Body: `{"status": "preparing"}`
- Note: Order masuk ke kitchen

**3. Cashier Assigns Driver** (SELAMA preparing)
- Endpoint: `POST /v1/merchant/orders/123/assign-driver`
- Body: `{"driver_id": 4}`
- Note: Driver dipilih, siap untuk pickup
- Note: Driver must be `is_online = true` and `is_busy = false`

**4. Cashier Updates Status: ready**
- Endpoint: `PUT /v1/merchant/orders/123/status`
- Body: `{"status": "ready"}`
- Note: Orderan sudah siap, menunggu driver pickup

**5. Cashier Updates Status: on_delivery**
- Endpoint: `PUT /v1/merchant/orders/123/status`
- Body: `{"status": "on_delivery"}`
- Note: Driver sudah pickup dan mulai pengantaran

**6. Driver Accepts Order**
- Endpoint: `POST /v1/driver/orders/123/accept`
- Note: Driver pickup order dari outlet

**7. Driver Marks as Paid** (Cash received)
- Endpoint: `POST /v1/driver/orders/123/paid`
- Note: Driver terima pembayaran cash dari customer

**8. Driver Completes Delivery**
- Endpoint: `POST /v1/driver/orders/123/complete`
- Note: Order selesai, customer menerima makanan

**8. Driver Completes Delivery**
- Endpoint: `POST /v1/driver/orders/123/complete`
- Note: Updates status to `completed` and sets `completed_at` timestamp

### Final Order States
After workflow, order should have:
- `status`: `completed`
- `driver_id`: 4 (assigned driver) - sudah di-assign sejak preparing
- `paid_at`: 2026-01-25 16:45:30 (payment timestamp saat driver terima cash)
- `completed_at`: 2026-01-25 16:50:15 (completion timestamp)

✅ **Order workflow complete! (Gojek-style flow)**
