# Distribution + Customer Sales + Live Tracking System

This document describes the extension added to the Laravel Inventory Management System for Distribution, Customer Sales, and Live GPS Tracking.

## Architecture Overview

- **Single Laravel Backend** - All functionality in one application
- **Admin Panel** - Web-based (existing layout)
- **Customer Section** - API-based, mobile-app ready (Sanctum tokens)
- **One Database** - Shared across admin and customers
- **Repository Pattern** - Clean separation of data access
- **Service Layer** - Business logic in dedicated services

---

## Phase 1: Customer Management

### Database
- **customers** table: id, business_id, name, phone, email, password, address, status (active/inactive), created_by

### Models
- `App\Customer` - Extends Authenticatable, uses Laravel Sanctum HasApiTokens

### Auth
- **Guard**: `customer_api` (Sanctum driver)
- **Provider**: `customers` (Customer model)
- Customers are separate from Admin (User) and Contacts

### Admin Routes
- `GET /distribution/customers` - List
- `GET /distribution/customers/create` - Create form
- `POST /distribution/customers` - Store
- `GET /distribution/customers/{id}` - Show
- `GET /distribution/customers/{id}/edit` - Edit form
- `PUT /distribution/customers/{id}` - Update

---

## Phase 2: Customer Stock System

### Tables
- **customer_stocks**: id, customer_id, product_id, variation_id, quantity, purchase_price
- **customer_stock_transfers**: id, customer_id, product_id, variation_id, location_id, quantity, transfer_price, total_amount, created_by

### Flow
1. Admin transfers product from main inventory (variation_location_details) to customer
2. Main inventory quantity decreases
3. Customer stock quantity increases (or new record created)
4. Transfer recorded in customer_stock_transfers

### Logic
- Validates stock availability in main inventory
- Uses database transactions
- Prevents negative stock
- Auto-resolves variation_id for single-variation products

### Admin Routes
- `GET /distribution/stock-transfers` - List transfers
- `GET /distribution/stock-transfers/create` - Transfer form
- `POST /distribution/stock-transfers` - Execute transfer

---

## Phase 3: Customer POS System

### Table
- **customer_sales**: id, customer_id, product_id, variation_id, quantity, purchase_price, sale_price, profit, sold_to, created_at

### Flow
1. Customer sells product via API
2. Customer stock decreases
3. Profit = (sale_price - purchase_price) * quantity
4. Sale recorded in customer_sales

### Reports (via API)
- Daily/Monthly sales
- Profit summary
- Product-wise sales
- Customer-wise performance (Admin view)

---

## Phase 4: Mobile API

### Base URL
`/api/customer`

### Routes

| Method | Endpoint | Auth | Description |
|--------|----------|------|-------------|
| POST | /api/customer/login | No | Login, returns Sanctum token |
| GET | /api/customer/dashboard | Yes | Dashboard stats |
| GET | /api/customer/stock | Yes | Customer's stock list |
| POST | /api/customer/sell | Yes | Record sale (POS) |
| GET | /api/customer/sales-history | Yes | Sales history (optional from_date, to_date) |
| POST | /api/customer/location/update | Yes | Update GPS location |
| GET | /api/customer/profile | Yes | Customer profile |

### Example: Login Request
```json
POST /api/customer/login
{
  "email": "customer@example.com",
  "password": "password"
}
```

### Example: Login Response
```json
{
  "success": true,
  "message": "Login successful",
  "data": {
    "customer": {
      "id": 1,
      "name": "John Doe",
      "email": "customer@example.com",
      "phone": "1234567890",
      "address": "123 Main St",
      "status": "active"
    },
    "token": "1|abc123...",
    "token_type": "Bearer"
  }
}
```

### Example: Sell Request
```json
POST /api/customer/sell
Authorization: Bearer {token}
{
  "product_id": 1,
  "quantity": 2,
  "sale_price": 150.00,
  "sold_to": "Retail Buyer Name"
}
```

### Example: Location Update
```json
POST /api/customer/location/update
Authorization: Bearer {token}
{
  "latitude": 28.6139,
  "longitude": 77.2090
}
```

### Rate Limiting
- Customer API: 120 requests/minute per user or IP

---

## Phase 5: GPS Tracking

### Table
- **customer_locations**: id, customer_id, latitude, longitude, recorded_at

### Mobile App
- Request background location permission
- Send GPS every 15-30 seconds
- Spam prevention: min 10-second interval between updates

### Admin Panel
- **Route**: `GET /distribution/tracking`
- Live map (Google Maps - set GOOGLE_MAP_API_KEY in .env)
- Filter by customer and date
- Show full-day route path
- Show current location

### API for Map Data
- `GET /distribution/tracking/locations?customer_id=1&date=2025-02-01`
- `GET /distribution/tracking/latest?customer_id=1`

---

## Phase 6-7: Architecture

### Repositories
- `CustomerRepositoryInterface` / `CustomerRepository`
- `CustomerStockRepositoryInterface` / `CustomerStockRepository`
- `CustomerSaleRepositoryInterface` / `CustomerSaleRepository`
- `CustomerLocationRepositoryInterface` / `CustomerLocationRepository`

### Services
- `CustomerStockTransferService` - Admin to customer transfers
- `CustomerSaleService` - Customer POS sales
- `CustomerLocationService` - GPS updates with spam prevention

### Form Requests
- `CustomerLoginRequest`
- `CustomerSellRequest`
- `CustomerLocationUpdateRequest`

### API Resources
- `CustomerResource`
- `CustomerStockResource`
- `CustomerSaleResource`
- `CustomerDashboardResource`

---

## Setup Instructions

### 1. Install Sanctum (if not already)
```bash
composer require laravel/sanctum
# Or: composer require laravel/sanctum --ignore-platform-reqs
```

### 2. Run Migrations
```bash
php artisan migrate
```

### 3. Add Permissions (optional)
Distribution uses existing `customer.view` and `customer.create` permissions. Ensure your admin users have these.

### 4. Create First Distribution Customer
- Go to **Distribution > Distribution Customers > Add Customer**
- Create customer with email and password
- Customer can then login via API: `POST /api/customer/login`

### 5. Transfer Stock
- Go to **Distribution > Transfer to Customer**
- Select customer, location, product, quantity, price
- Submit to transfer from main inventory to customer

### 6. GPS Tracking
- Set `GOOGLE_MAP_API_KEY` in `.env` for map display
- Mobile app sends location via `POST /api/customer/location/update`
- Admin views routes at **Distribution > GPS Tracking**

---

## Sample Flow

1. **Admin** creates distribution customer (name, email, password)
2. **Admin** transfers 100 units of Product A from Location 1 to Customer
3. **Customer** logs in via mobile app → gets token
4. **Customer** views stock via `/api/customer/stock`
5. **Customer** sells 5 units to "Retail Buyer" via `/api/customer/sell`
6. **Customer** app sends GPS every 15-30 seconds via `/api/customer/location/update`
7. **Admin** views customer's route on map at `/distribution/tracking`

---

## File Structure

```
app/
├── Customer.php
├── CustomerSale.php
├── CustomerStock.php
├── CustomerStockTransfer.php
├── CustomerLocation.php
├── Http/
│   ├── Controllers/
│   │   ├── Api/Customer/
│   │   │   ├── AuthController.php
│   │   │   ├── DashboardController.php
│   │   │   ├── StockController.php
│   │   │   ├── SaleController.php
│   │   │   ├── LocationController.php
│   │   │   └── ProfileController.php
│   │   └── Distribution/
│   │       ├── CustomerController.php
│   │       ├── CustomerStockTransferController.php
│   │       └── CustomerTrackingController.php
│   ├── Requests/Api/
│   │   ├── CustomerLoginRequest.php
│   │   ├── CustomerSellRequest.php
│   │   └── CustomerLocationUpdateRequest.php
│   └── Resources/Api/
│       ├── CustomerResource.php
│       ├── CustomerStockResource.php
│       ├── CustomerSaleResource.php
│       └── CustomerDashboardResource.php
├── Repositories/
│   ├── Contracts/
│   │   ├── CustomerRepositoryInterface.php
│   │   ├── CustomerStockRepositoryInterface.php
│   │   ├── CustomerSaleRepositoryInterface.php
│   │   └── CustomerLocationRepositoryInterface.php
│   ├── CustomerRepository.php
│   ├── CustomerStockRepository.php
│   ├── CustomerSaleRepository.php
│   └── CustomerLocationRepository.php
└── Services/
    ├── CustomerStockTransferService.php
    ├── CustomerSaleService.php
    └── CustomerLocationService.php

database/migrations/
├── 2019_12_14_000001_create_personal_access_tokens_table.php
├── 2025_02_01_000001_create_customers_table.php
├── 2025_02_01_000002_create_customer_stocks_table.php
├── 2025_02_01_000003_create_stock_transfers_table.php  (customer_stock_transfers)
├── 2025_02_01_000004_create_customer_sales_table.php
└── 2025_02_01_000005_create_customer_locations_table.php

resources/views/distribution/
├── customers/ (index, create, edit, show)
├── stock_transfers/ (index, create)
└── tracking/ (index)
```

---

## Scalability Notes

- **100+ customers**: Indexed tables (customer_id, product_id, created_at)
- **Real-time stock**: Transactions ensure consistency
- **GPS tracking**: 10-second minimum interval prevents spam
- **API rate limiting**: 120 req/min per customer
- **Repository pattern**: Easy to add caching layer
