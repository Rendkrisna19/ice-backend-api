# ICE Backend API (Integrated Culinary Ecosystem)

**Document Reference:** SAD-ICE-2026-FINAL [cite: 4]  
**Type:** Backend Service (API Only)  
**Status:** Development

## 1. Overview
Repository ini berisi logika backend untuk platform **Integrated Culinary Ecosystem**. Sistem ini melayani empat aktor utama: Pelanggan, Merchant, Driver, dan Administrator[cite: 11].

**Critical Constraints:**
* **API Only:** Tidak ada view blade (kecuali mail), semua response dalam format JSON.
* **Stateless:** Autentikasi menggunakan Laravel Sanctum.
* **Connection Dependent:** Menggunakan mekanisme *Heartbeat* untuk mendeteksi status online/offline merchant[cite: 17].

## 2. Technology Stack [cite: 19, 20]
* **Framework:** Laravel 11
* **Database:** MySQL 8.0 (InnoDB Strict Relational)
* **Cache & Queue:** Redis (Wajib untuk Session, Queue, & Heartbeat Key)
* **Real-time:** Laravel Reverb (WebSocket)
* **Map/Routing:** OpenRouteService (via API/Service)

## 3. Architecture Standards (STRICT)
Project ini menggunakan pendekatan **Service-Repository Pattern (Lite)** untuk menjaga *Controller* tetap bersih[cite: 113].

### 3.1 Folder Structure
Developer wajib mengikuti struktur berikut. Jangan menaruh *Business Logic* di Controller!

```text
app/
├── Http/
│   ├── Controllers/
│   │   └── Api/V1/          # Versioning API wajib
│   │       ├── Admin/       # Dashboard & Approval logic
│   │       ├── Merchant/    # Order & Availability logic
│   │       ├── Driver/      # Job & Shift logic
│   │       └── Customer/    # Checkout & Tracking
│   └── Middleware/
│       └── EnsureOutletOpen.php # Validasi Heartbeat & Jam Operasional [cite: 123]
├── Services/                # TEMPAT UTAMA BUSINESS LOGIC
│   ├── Pricing/             # Kalkulasi ongkir dinamis [cite: 127]
│   ├── Order/               # State machine order flow & refund [cite: 129]
│   └── Connectivity/        # Logic Heartbeat Redis [cite: 130]

```

### 3.2 Response Format (JSend Standard)

Seluruh endpoint API **WAJIB** mengembalikan format JSON seragam:

**Success Response:**

```json
{
    "status": "success",
    "data": { "key": "value" }
}

```

**Error Response:**

```json
{
    "status": "error",
    "message": "Outlet sedang tutup sementara.",
    "code": 422
}

```

*Gunakan Trait `App\Traits\ApiResponse` untuk mempermudah formatting.*

## 4. Business Logic Key Notes

Harap baca dokumen SAD untuk detail, namun perhatikan poin kritis ini:

1. 
**Inventory Toggle:** Stok tidak menggunakan angka kuantitas, hanya `is_available` (Boolean).


2. **Heartbeat:** Merchant dianggap offline jika tidak mengirim ping > 120 detik. Data disimpan di Redis (`outlet:{id}:last_seen`).


3. **Pricing:** Harga ongkir dihitung di Backend, jangan hardcode di Apps. Rumus: `(Jarak * Price_Per_KM) + Base_Price`.


4. 
**Order Snapshot:** Saat order dibuat, data harga dan nama produk harus di-copy ke tabel `order_items` untuk mencegah perubahan harga di masa depan.



## 5. Installation & Setup

### Prerequisites

* PHP 8.2+
* Composer
* MySQL
* Redis (Wajib running)

### Setup Steps

1. **Clone Repository**
```bash
git clone <repo-url>
cd ice-backend-api

```


2. **Install Dependencies**
```bash
composer install

```


3. **Environment Setup**
```bash
cp .env.example .env
php artisan key:generate

```


*Konfigurasi DB_*, REDIS_*, dan REVERB_* di file .env.*
4. **Database Migration**
```bash
php artisan migrate

```


5. **Running Local Server**
Anda perlu menjalankan 3 terminal berbeda:
*Terminal 1 (API Server):*
```bash
php artisan serve

```


*Terminal 2 (Queue Worker):*
```bash
php artisan queue:work

```


*Terminal 3 (WebSocket Server):*
```bash
php artisan reverb:start

```



## 6. Deployment Note

Server produksi menggunakan **Nginx** sebagai Reverse Proxy dan **Supervisor** untuk menjaga proses queue/reverb tetap hidup.
