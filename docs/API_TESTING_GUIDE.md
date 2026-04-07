# Garage ERP API Testing Guide

## Base URL

```text
http://127.0.0.1:8000/api
```

## Authentication

- `POST /auth/login` is public.
- All other API routes require `Authorization: Bearer {{token}}`.
- Use `Accept: application/json` for all requests.
- Replace placeholder IDs such as `{user}`, `{product}`, `{sale}`, `{ticket}` with real database IDs.

## Suggested Setup Order

1. Login and save the token.
2. Create categories, brands, bikes, services, and products.
3. Create customers and customer bikes.
4. Create bike inventory entries if you want to sell bikes.
5. Create sales and tickets.
6. Create expenses.
7. Run reports, invoices, recovery, and logs.

## Seed / Reference Data Checklist

Before testing the full flow, make sure these entities exist when needed:

- `users`
- `sellers`
- `categories`
- `brands`
- `bikes`
- `products`
- `services`
- `customers`
- `customer_bikes`
- `bike_inventory`

## Auth

### Login
- Method: `POST`
- Path: `/auth/login`
- Auth: `No`
- Purpose: Authenticate a user and return a Sanctum token.

```json
{
  "email": "admin@example.com",
  "password": "password123",
  "device_name": "postman"
}
```

### Current User
- Method: `GET`
- Path: `/auth/me`
- Auth: `Yes`
- Purpose: Return the authenticated user and effective permissions.

### Logout
- Method: `POST`
- Path: `/auth/logout`
- Auth: `Yes`
- Purpose: Revoke the current access token.

## Users

### List Users
- Method: `GET`
- Path: `/users`
- Auth: `Yes`
- Purpose: Return all users with role and permission data.

### Create User
- Method: `POST`
- Path: `/users`
- Auth: `Yes`

```json
{
  "name": "Staff User",
  "email": "staff@example.com",
  "password": "password123",
  "role": "staff"
}
```

### Update User
- Method: `PUT`
- Path: `/users/{user}`
- Auth: `Yes`

```json
{
  "name": "Updated Staff User",
  "email": "staff.updated@example.com",
  "password": "newpassword123",
  "role": "technician"
}
```

### Assign User Permissions
- Method: `POST`
- Path: `/users/{user}/permissions`
- Auth: `Yes`

```json
{
  "permissions": [
    "view_sales",
    "create_sale",
    "edit_sale",
    "view_inventory",
    "edit_inventory"
  ]
}
```

## Categories

### List Categories
- Method: `GET`
- Path: `/categories?type=part&search=brake&per_page=15`
- Auth: `Yes`
- Purpose: Paginated categories with optional filtering by type and search.

### Show Category
- Method: `GET`
- Path: `/categories/{category}`
- Auth: `Yes`

### Create Category
- Method: `POST`
- Path: `/categories`
- Auth: `Yes`

```json
{
  "name": "Brake Parts",
  "type": "part",
  "description": "Brake system inventory"
}
```

### Update Category
- Method: `PUT`
- Path: `/categories/{category}`
- Auth: `Yes`

```json
{
  "name": "Brake Consumables",
  "description": "Pads, fluids, and rotors"
}
```

### Delete Category
- Method: `DELETE`
- Path: `/categories/{category}`
- Auth: `Yes`

## Brands

### List Brands
- Method: `GET`
- Path: `/brands?search=honda&per_page=15`
- Auth: `Yes`

### Show Brand
- Method: `GET`
- Path: `/brands/{brand}`
- Auth: `Yes`

### Create Brand
- Method: `POST`
- Path: `/brands`
- Auth: `Yes`

```json
{
  "name": "Honda",
  "description": "OEM and genuine brand"
}
```

### Update Brand
- Method: `PUT`
- Path: `/brands/{brand}`
- Auth: `Yes`

```json
{
  "name": "Honda Motorcycles",
  "description": "Updated notes"
}
```

### Delete Brand
- Method: `DELETE`
- Path: `/brands/{brand}`
- Auth: `Yes`

## Bikes

### List Bike Blueprints
- Method: `GET`
- Path: `/bikes?brand=Yamaha&model=MT&year=2024&search=MT&per_page=15`
- Auth: `Yes`

### Show Bike Blueprint
- Method: `GET`
- Path: `/bikes/{bike}`
- Auth: `Yes`

### Create Bike Blueprint
- Method: `POST`
- Path: `/bikes`
- Auth: `Yes`

```json
{
  "brand": "Yamaha",
  "model": "MT-07",
  "year": 2024
}
```

### Update Bike Blueprint
- Method: `PUT`
- Path: `/bikes/{bike}`
- Auth: `Yes`

```json
{
  "model": "MT-09"
}
```

### Delete Bike Blueprint
- Method: `DELETE`
- Path: `/bikes/{bike}`
- Auth: `Yes`

## Products

### Create Product
- Method: `POST`
- Path: `/products`
- Auth: `Yes`
- Notes: `category_id` must match the chosen product `type`.

```json
{
  "type": "part",
  "name": "Engine Oil",
  "sku": "OIL-001",
  "part_number": "EO-5L",
  "category_id": 1,
  "brand_id": 1,
  "qty": 50,
  "cost_price": 200,
  "selling_price": 300,
  "cost_price_usd": 4,
  "max_discount_type": "percentage",
  "max_discount_value": 10,
  "is_universal": false,
  "description": "Synthetic oil",
  "bike_ids": [1, 2],
  "units": [
    {
      "unit_name": "liter",
      "conversion_factor": 1,
      "price": 80
    },
    {
      "unit_name": "gallon",
      "conversion_factor": 5,
      "price": 300
    }
  ]
}
```

### Update Product
- Method: `PUT`
- Path: `/products/{product}`
- Auth: `Yes`

```json
{
  "name": "Engine Oil Premium",
  "selling_price": 320,
  "max_discount_type": "fixed",
  "max_discount_value": 30,
  "units": [
    {
      "id": 1,
      "unit_name": "liter",
      "conversion_factor": 1,
      "price": 85
    },
    {
      "unit_name": "gallon",
      "conversion_factor": 5,
      "price": 320
    }
  ]
}
```

### Assign Product To Bikes
- Method: `POST`
- Path: `/products/{product}/bikes`
- Auth: `Yes`

```json
{
  "bike_ids": [1, 2, 3]
}
```

### Get Compatible Products
- Method: `GET`
- Path: `/products/compatible?bike_id=1&per_page=15`
- Auth: `Yes`
- Alternate path: `/products/compatible?brand=Honda&model=CBR 600&year=2024&per_page=15`

### Calculate Product Price
- Method: `GET`
- Path: `/products/{product}/calculate-price?unit_id=1`
- Auth: `Yes`

## Product Units

### Create Product Unit
- Method: `POST`
- Path: `/products/{product}/units`
- Auth: `Yes`

```json
{
  "unit_name": "half-liter",
  "conversion_factor": 0.5,
  "price": 45
}
```

### Update Product Unit
- Method: `PUT`
- Path: `/products/{product}/units/{unit}`
- Auth: `Yes`

```json
{
  "unit_name": "half-liter",
  "conversion_factor": 0.5,
  "price": 50
}
```

### Delete Product Unit
- Method: `DELETE`
- Path: `/products/{product}/units/{unit}`
- Auth: `Yes`

## Inventory

### Bulk Update Products
- Method: `POST`
- Path: `/inventory/bulk-update`
- Auth: `Yes`

```json
{
  "product_ids": [1, 2, 3],
  "attributes": {
    "selling_price": 350,
    "max_discount_type": "percentage",
    "max_discount_value": 5,
    "is_universal": false
  }
}
```

### Import Products
- Method: `POST`
- Path: `/inventory/import`
- Auth: `Yes`
- Content type: `multipart/form-data`

```text
file: products.csv
mode: upsert
```

### Export Products
- Method: `GET`
- Path: `/inventory/export?format=csv`
- Auth: `Yes`
- Allowed formats: `csv`, `excel`

### Download Import Template
- Method: `GET`
- Path: `/inventory/template?format=csv`
- Auth: `Yes`
- Allowed formats: `csv`, `excel`

### Update Inventory Exchange Rate
- Method: `POST`
- Path: `/inventory/exchange-rate`
- Auth: `Yes`

```json
{
  "currency": "USD",
  "rate": 50.25
}
```

### Adjust Stock
- Method: `POST`
- Path: `/inventory/adjust-stock`
- Auth: `Yes`

```json
{
  "product_id": 1,
  "qty": 5,
  "change_type": "add",
  "reference_type": "manual",
  "reference_id": 1001
}
```

With unit:

```json
{
  "product_id": 1,
  "qty": 1,
  "unit_id": 2,
  "change_type": "reduce",
  "reference_type": "manual",
  "reference_id": 1002
}
```

## Customers

### List Customers
- Method: `GET`
- Path: `/customers?search=ahmed&phone=0100&per_page=15`
- Auth: `Yes`

### Show Customer
- Method: `GET`
- Path: `/customers/{customer}`
- Auth: `Yes`

### Create Customer
- Method: `POST`
- Path: `/customers`
- Auth: `Yes`
- Notes: Can create customer bikes inline.

```json
{
  "name": "Ahmed Rider",
  "phone": "01000000001",
  "address": "Nasr City",
  "notes": "VIP customer",
  "bikes": [
    {
      "brand": "BMW",
      "model": "S1000RR",
      "year": 2021,
      "modifications": "Exhaust upgrade",
      "notes": "Track bike"
    }
  ]
}
```

### Update Customer
- Method: `PUT`
- Path: `/customers/{customer}`
- Auth: `Yes`

```json
{
  "address": "New Cairo",
  "notes": "Prefers WhatsApp"
}
```

### Delete Customer
- Method: `DELETE`
- Path: `/customers/{customer}`
- Auth: `Yes`

### List Customer Bikes By Customer
- Method: `GET`
- Path: `/customers/{customer}/bikes?per_page=15`
- Auth: `Yes`

### Create Customer Bike Under Customer
- Method: `POST`
- Path: `/customers/{customer}/bikes`
- Auth: `Yes`

```json
{
  "brand": "Kawasaki",
  "model": "ZX-6R",
  "year": 2020,
  "modifications": "Quick shifter",
  "notes": "Daily rider"
}
```

## Customer Bikes

### List Customer Bikes
- Method: `GET`
- Path: `/customer-bikes?customer_id=1&search=BMW&per_page=15`
- Auth: `Yes`

### Show Customer Bike
- Method: `GET`
- Path: `/customer-bikes/{customerBike}`
- Auth: `Yes`

### Create Customer Bike
- Method: `POST`
- Path: `/customer-bikes`
- Auth: `Yes`

```json
{
  "customer_id": 1,
  "brand": "Kawasaki",
  "model": "ZX-6R",
  "year": 2020,
  "modifications": "Quick shifter",
  "notes": "Daily rider"
}
```

### Update Customer Bike
- Method: `PUT`
- Path: `/customer-bikes/{customerBike}`
- Auth: `Yes`

```json
{
  "notes": "Updated inspection notes"
}
```

### Delete Customer Bike
- Method: `DELETE`
- Path: `/customer-bikes/{customerBike}`
- Auth: `Yes`

## Bike Inventory

### List Bike Inventory
- Method: `GET`
- Path: `/bike-inventory?type=consignment&sold=false&per_page=15`
- Auth: `Yes`

### Show Bike Inventory
- Method: `GET`
- Path: `/bike-inventory/{bikeInventory}`
- Auth: `Yes`

### Create Bike Inventory
- Method: `POST`
- Path: `/bike-inventory`
- Auth: `Yes`
- Notes: For `consignment`, `owner_name` and `owner_phone` are required. You can either use `bike_id` or send `brand/model/year` directly.

```json
{
  "bike_id": 1,
  "type": "consignment",
  "cost_price": 350000,
  "selling_price": 390000,
  "mileage": 12000,
  "cc": 1103,
  "horse_power": 214,
  "owner_name": "Mostafa",
  "owner_phone": "01111111111",
  "notes": "Clean condition"
}
```

### Update Bike Inventory
- Method: `PUT`
- Path: `/bike-inventory/{bikeInventory}`
- Auth: `Yes`

```json
{
  "selling_price": 395000,
  "notes": "Price updated after inspection"
}
```

### Delete Bike Inventory
- Method: `DELETE`
- Path: `/bike-inventory/{bikeInventory}`
- Auth: `Yes`

## Services

### List Services
- Method: `GET`
- Path: `/services?category_id=3&search=diagnostic&per_page=15`
- Auth: `Yes`

### Show Service
- Method: `GET`
- Path: `/services/{service}`
- Auth: `Yes`

### Create Service
- Method: `POST`
- Path: `/services`
- Auth: `Yes`
- Notes: `category_id` must reference a category with type `service`.

```json
{
  "category_id": 3,
  "name": "Engine Diagnostics",
  "price": 800,
  "max_discount_type": "percentage",
  "max_discount_value": 10,
  "description": "Full diagnostics service"
}
```

### Update Service
- Method: `PUT`
- Path: `/services/{service}`
- Auth: `Yes`

```json
{
  "price": 900,
  "max_discount_type": "fixed",
  "max_discount_value": 50
}
```

### Delete Service
- Method: `DELETE`
- Path: `/services/{service}`
- Auth: `Yes`

## Sales

### Create Sale
- Method: `POST`
- Path: `/sales`
- Auth: `Yes`

```json
{
  "customer_id": 1,
  "seller_id": 1,
  "type": "garage"
}
```

Or create with new customer:

```json
{
  "seller_id": 1,
  "type": "garage",
  "customer": {
    "name": "Ahmed Ali",
    "phone": "01000000000",
    "address": "Cairo"
  }
}
```

### Add Sale Item
- Method: `POST`
- Path: `/sales/{sale}/items`
- Auth: `Yes`

Product item:

```json
{
  "item_type": "product",
  "item_id": 1,
  "qty": 2,
  "discount": 20
}
```

Bike item:

```json
{
  "item_type": "bike",
  "item_id": 1,
  "qty": 1,
  "discount": 0
}
```

### Add Sale Payment
- Method: `POST`
- Path: `/sales/{sale}/payments`
- Auth: `Yes`

```json
{
  "amount": 500,
  "method": "cash",
  "status": "completed"
}
```

### Complete Sale
- Method: `POST`
- Path: `/sales/{sale}/complete`
- Auth: `Yes`

### List Sale Returns
- Method: `GET`
- Path: `/sales/{sale}/returns`
- Auth: `Yes`

### Return Sale Item
- Method: `POST`
- Path: `/sales/{sale}/returns`
- Auth: `Yes`

```json
{
  "item_id": 1,
  "qty": 1,
  "reason": "Damaged item"
}
```

### Return Full Sale
- Method: `POST`
- Path: `/sales/{sale}/return`
- Auth: `Yes`

## Tickets

### Create Ticket
- Method: `POST`
- Path: `/tickets`
- Auth: `Yes`

```json
{
  "customer_id": 1,
  "customer_bike_id": 1,
  "notes": "Engine sound check"
}
```

### Start Ticket
- Method: `POST`
- Path: `/tickets/{ticket}/start`
- Auth: `Yes`

### Complete Ticket
- Method: `POST`
- Path: `/tickets/{ticket}/complete`
- Auth: `Yes`

### Reopen Ticket
- Method: `POST`
- Path: `/tickets/{ticket}/reopen`
- Auth: `Yes`

### Add Ticket Note
- Method: `POST`
- Path: `/tickets/{ticket}/notes`
- Auth: `Yes`

```json
{
  "type": "staff",
  "note": "Client approved extra work"
}
```

## Tasks

### Create Task
- Method: `POST`
- Path: `/tickets/{ticket}/tasks`
- Auth: `Yes`

```json
{
  "name": "Change engine oil",
  "status": "pending",
  "approved_by_client": true
}
```

### Update Task
- Method: `PUT`
- Path: `/tickets/{ticket}/tasks/{task}`
- Auth: `Yes`

```json
{
  "name": "Change engine oil and filter",
  "status": "completed",
  "approved_by_client": true
}
```

### Delete Task
- Method: `DELETE`
- Path: `/tickets/{ticket}/tasks/{task}`
- Auth: `Yes`

### Assign Item To Task
- Method: `POST`
- Path: `/tickets/{ticket}/tasks/{task}/items`
- Auth: `Yes`

Service item:

```json
{
  "item_type": "service",
  "item_id": 1,
  "qty": 1,
  "price_source": "current"
}
```

Product item:

```json
{
  "item_type": "product",
  "item_id": 1,
  "qty": 2,
  "price_source": "current"
}
```

### Remove Item From Task
- Method: `DELETE`
- Path: `/tickets/{ticket}/tasks/{task}/items/{item}`
- Auth: `Yes`

## Expenses

### List Expenses
- Method: `GET`
- Path: `/expenses?category=bills&paid_by=bank&from_date=2026-04-01&to_date=2026-04-30&min_amount=100&max_amount=5000&search=rent`
- Auth: `Yes`

### Create Expense
- Method: `POST`
- Path: `/expenses`
- Auth: `Yes`
- Content type: `multipart/form-data`

```text
category: bills
amount: 1500
description: Workshop rent
expense_date: 2026-04-06
paid_by: bank
is_recurring: 1
recurring_type: monthly
attachment: rent.pdf
```

### Update Expense
- Method: `PUT`
- Path: `/expenses/{expense}`
- Auth: `Yes`
- Content type: `multipart/form-data`

```text
category: bills
amount: 1800
description: Workshop rent updated
expense_date: 2026-04-06
paid_by: bank
is_recurring: 1
recurring_type: monthly
attachment: new-rent.pdf
remove_attachment: 0
```

### Delete Expense
- Method: `DELETE`
- Path: `/expenses/{expense}`
- Auth: `Yes`

### Generate Recurring Expenses
- Method: `POST`
- Path: `/expenses/generate-recurring`
- Auth: `Yes`

```json
{
  "for_date": "2026-05-06"
}
```

## Reports

All report endpoints accept `format=json|csv|excel|pdf` plus either `date` or a date range depending on the report.

### Profit & Loss
- Method: `GET`
- Path: `/reports/profit-loss?from_date=2026-04-01&to_date=2026-04-30&format=json`
- Auth: `Yes`

### Balance Sheet
- Method: `GET`
- Path: `/reports/balance-sheet?from_date=2026-04-01&to_date=2026-04-30&format=json`
- Auth: `Yes`

### Daily Report
- Method: `GET`
- Path: `/reports/daily?date=2026-04-06&format=json`
- Auth: `Yes`

### Expenses Report
- Method: `GET`
- Path: `/reports/expenses?from_date=2026-04-01&to_date=2026-04-30&format=json`
- Auth: `Yes`

### Cash & Bank Report
- Method: `GET`
- Path: `/reports/cash-bank?from_date=2026-04-01&to_date=2026-04-30&format=json`
- Auth: `Yes`

## Invoices

### List Invoices
- Method: `GET`
- Path: `/invoices?type=sale&status=paid&from_date=2026-04-01&to_date=2026-04-30`
- Auth: `Yes`

### Show Invoice
- Method: `GET`
- Path: `/invoices/{invoice}`
- Auth: `Yes`

## Settings

### List Settings
- Method: `GET`
- Path: `/settings`
- Auth: `Yes`

### Update Setting
- Method: `PUT`
- Path: `/settings`
- Auth: `Yes`

```json
{
  "key": "default_currency",
  "value": "EGP"
}
```

### Update Settings Exchange Rate
- Method: `POST`
- Path: `/settings/exchange-rate`
- Auth: `Yes`

```json
{
  "currency": "USD",
  "rate": 50.25
}
```

## Dashboard

### Dashboard Metrics
- Method: `GET`
- Path: `/dashboard/metrics`
- Auth: `Yes`

## Recovery

Supported recovery entities are: `sales`, `tickets`, `products`, `expenses`, `customers`.

### List Deleted Records
- Method: `GET`
- Path: `/recovery/products`
- Auth: `Yes`

### Restore Deleted Record
- Method: `POST`
- Path: `/recovery/products/{id}/restore`
- Auth: `Yes`

## Logs

### List Logs
- Method: `GET`
- Path: `/logs?user_id=1&action=update&entity_type=product&from_date=2026-04-01&to_date=2026-04-30`
- Auth: `Yes`

### Show Log Details
- Method: `GET`
- Path: `/logs/{log}`
- Auth: `Yes`
