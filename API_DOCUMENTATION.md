# ICE Backend API - Setup & Postman Testing Guide

## Prerequisites

- PHP 8.2 or higher
- Composer
- MySQL 8.0
- Node.js & npm

## Installation

### 1. Clone Repository
```bash
git clone <repository-url>
cd ice-backend-api
```

### 2. Install Dependencies
```bash
composer install
npm install
```

### 3. Environment Setup
```bash
cp .env.example .env
php artisan key:generate
```

### 4. Configure Database
Edit `.env` file:
```
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=ice_backend
DB_USERNAME=root
DB_PASSWORD=password
```

Create MySQL database:
```sql
CREATE DATABASE ice_backend;
```

### 5. Run Migrations
```bash
php artisan migrate
```

### 6. Seed Database (Optional)
```bash
php artisan db:seed
```

## Running the Application

```bash
php artisan serve
```

API will be available at: `http://localhost:8000/api`

## API Documentation

### Base URL
- **Development**: `http://localhost:8000/api/v1`
- **Production**: `{your-domain}/api/v1`

### Authentication
- Uses Laravel Sanctum token-based authentication
- Include token in header: `Authorization: Bearer {token}`

## Postman Collection

### 1. Authentication Endpoints

#### Register
```
POST /auth/register
Content-Type: application/json

{
  "name": "John Doe",
  "email": "john@example.com",
  "password": "password123",
  "password_confirmation": "password123",
  "role": "customer"
}
```

#### Login
```
POST /auth/login
Content-Type: application/json

{
  "email": "john@example.com",
  "password": "password123"
}
```

#### Get Current User
```
GET /auth/user
Authorization: Bearer {token}
```

#### Logout
```
POST /auth/logout
Authorization: Bearer {token}
```

### 2. Outlet Endpoints

#### List All Outlets
```
GET /outlets
```

#### Get Outlet Details
```
GET /outlets/{id}
```

#### Search Outlets
```
GET /outlets/search?q=restaurant%20name
```

#### Get Outlet Products
```
GET /outlets/{outlet_id}/products
```

### 3. Product Endpoints

#### List All Products
```
GET /products
```

#### Get Product Details
```
GET /products/{id}
```

#### Search Products
```
GET /products/search?q=nasi%20goreng
```

#### Get Products by Category
```
GET /products/category?category=makanan
```

### 4. Customer Order Endpoints

#### Create Order
```
POST /customer/orders
Authorization: Bearer {token}
Content-Type: application/json

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
    }
  ],
  "delivery_address": "Jl. Sudirman No. 123, Jakarta",
  "delivery_latitude": -6.2088,
  "delivery_longitude": 106.8456,
  "distance_real": 2.5
}
```

#### Get Customer Orders
```
GET /customer/orders
Authorization: Bearer {token}
```

#### Get Order Details
```
GET /customer/orders/{order_id}
Authorization: Bearer {token}
```

#### Cancel Order
```
POST /customer/orders/{order_id}/cancel
Authorization: Bearer {token}
```

#### Validate Checkout
```
POST /customer/orders/validate-checkout
Authorization: Bearer {token}
Content-Type: application/json

{
  "outlet_id": 1,
  "items": [
    {
      "product_id": 1,
      "quantity": 2
    }
  ]
}
```

### 5. Merchant (Cashier) Endpoints

#### Get All Orders for Outlet
```
GET /merchant/orders
Authorization: Bearer {token}
```

#### Get Order Details
```
GET /merchant/orders/{order_id}
Authorization: Bearer {token}
```

#### Update Order Status
```
PUT /merchant/orders/{order_id}/status
Authorization: Bearer {token}
Content-Type: application/json

{
  "status": "preparing"
}
```

Available statuses: `pending`, `paid`, `preparing`, `ready`, `on_delivery`, `completed`, `cancelled`, `refund_needed`, `refunded`

#### Reject Order (Initiate Refund)
```
POST /merchant/orders/{order_id}/reject
Authorization: Bearer {token}
```

#### Assign Driver to Order
```
POST /merchant/orders/{order_id}/assign-driver
Authorization: Bearer {token}
Content-Type: application/json

{
  "driver_id": 5
}
```

#### Get Available Drivers
```
GET /merchant/drivers/available
Authorization: Bearer {token}
```

#### Toggle Menu Availability
```
POST /merchant/menu/toggle-availability
Authorization: Bearer {token}
Content-Type: application/json

{
  "product_id": 1,
  "is_available": false
}
```

#### Get Outlet Status
```
GET /merchant/outlet/status
Authorization: Bearer {token}
```

#### Force Close Outlet
```
POST /merchant/outlet/force-close
Authorization: Bearer {token}
```

#### Force Open Outlet
```
POST /merchant/outlet/force-open
Authorization: Bearer {token}
```

### 6. Driver Endpoints

#### Clock In
```
POST /driver/shift/clock-in
Authorization: Bearer {token}
```

#### Clock Out
```
POST /driver/shift/clock-out
Authorization: Bearer {token}
```

#### Get Assigned Orders
```
GET /driver/orders
Authorization: Bearer {token}
```

#### Accept Order for Delivery
```
POST /driver/orders/{order_id}/accept
Authorization: Bearer {token}
```

#### Complete Delivery
```
POST /driver/orders/{order_id}/complete
Authorization: Bearer {token}
```

#### Get Driver Status
```
GET /driver/status
Authorization: Bearer {token}
```

### 7. Admin Endpoints

#### Get Refund Orders
```
GET /admin/refunds
Authorization: Bearer {token}
```

#### Process Refund
```
POST /admin/refunds/{order_id}/process
Authorization: Bearer {token}
Content-Type: application/json

{
  "refund_amount": 50000
}
```

#### List Outlets
```
GET /admin/outlets
Authorization: Bearer {token}
```

#### Create Outlet
```
POST /admin/outlets
Authorization: Bearer {token}
Content-Type: application/json

{
  "name": "Restaurant ABC",
  "slug": "restaurant-abc",
  "address": "Jl. Sudirman No. 123",
  "phone": "021-1234567",
  "whatsapp_number": "628123456789",
  "opening_hour": "09:00:00",
  "closing_hour": "21:00:00",
  "latitude": -6.2088,
  "longitude": 106.8456
}
```

#### Update Outlet
```
PUT /admin/outlets/{outlet_id}
Authorization: Bearer {token}
Content-Type: application/json

{
  "name": "Restaurant ABC Updated"
}
```

#### Delete Outlet
```
DELETE /admin/outlets/{outlet_id}
Authorization: Bearer {token}
```

#### Get Pricing Config
```
GET /admin/pricing
Authorization: Bearer {token}
```

#### Update Pricing Config
```
PUT /admin/pricing
Authorization: Bearer {token}
Content-Type: application/json

{
  "delivery_base_price": 5000,
  "delivery_base_distance": 1,
  "delivery_price_per_km": 2000,
  "tax_percentage": 10
}
```

#### Get Reporting Data
```
GET /admin/reporting
Authorization: Bearer {token}

Optional query parameters:
?outlet_id=1
?start_date=2026-01-01&end_date=2026-01-31
```

## Testing Workflow

### 1. User Registration & Login
1. Register a customer account
2. Register a cashier account (role: cashier)
3. Register a driver account (role: driver)
4. Login with each account and get the tokens

### 2. Outlet & Product Setup (Admin)
1. Create outlets via POST /admin/outlets
2. Create products via database (or API if created)
3. Link products to outlets via database pivot table

### 3. Customer Order Flow
1. List available outlets
2. Get outlet products
3. Validate checkout
4. Create order
5. Check order status

### 4. Merchant Order Processing
1. Login as cashier
2. View incoming orders
3. Assign driver
4. Update order status (preparing -> ready)

### 5. Driver Delivery Flow
1. Login as driver
2. Clock in
3. Accept order
4. Complete delivery
5. Clock out

## Response Format

All responses follow this format:

**Success Response (200)**
```json
{
  "success": true,
  "message": "Operation successful",
  "data": { ... }
}
```

**Error Response (4xx, 5xx)**
```json
{
  "success": false,
  "message": "Error description",
  "data": null
}
```

## Notes

- All timestamps are in UTC
- Delivery fee calculation: `BasePrice + Max(0, (Distance - BaseDistance) × PricePerKM)`
- Tax is calculated on subtotal only, not on delivery fee
- Order items store snapshots of product data at time of order
- Driver can only clock out if no active orders
- Orders can only be cancelled in `pending` or `paid` status

## Troubleshooting

### Database Connection Issues
Check .env DB_* settings and ensure MySQL is running

### Migration Errors
```bash
php artisan migrate:rollback
php artisan migrate
```

### Token Expiration
Re-login to get a new token

### CORS Issues
Configure CORS in config/cors.php if accessing from different domain
