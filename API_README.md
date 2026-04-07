# Garage ERP API Documentation

Welcome to the **Garage ERP API** documentation. This guide details all available endpoints, required request bodies, and expected response structures.

## Base URL

```text
http://127.0.0.1:8000/api
```

## Authentication

The API uses **Laravel Sanctum** for token-based authentication.

1.  **Obtain Token**: Use `POST /auth/login`.
2.  **Authorize Requests**: Include the token in the `Authorization` header as a Bearer token.
    ```text
    Authorization: Bearer <your_token>
    ```
3.  **Headers**: All requests should include:
    ```text
    Accept: application/json
    Content-Type: application/json
    ```

---

## Global Response Format

### Success Response
Standard success responses include a `message` and a `data` object (or array for listings).

```json
{
    "message": "Resource retrieved successfully.",
    "data": { ... }
}
```

### Paginated Response
For listing endpoints, `data` contains the Laravel pagination object.

```json
{
    "message": "Results retrieved successfully.",
    "data": {
        "current_page": 1,
        "data": [ ... ],
        "first_page_url": "...",
        "from": 1,
        "last_page": 2,
        "next_page_url": "...",
        "path": "...",
        "per_page": 15,
        "prev_page_url": null,
        "to": 15,
        "total": 30
    }
}
```

### Error Response
Errors follow a consistent structure with a `status: false`.

```json
{
    "status": false,
    "message": "Validation error",
    "errors": {
        "email": ["The email field is required."]
    }
}
```

---

## Auth Endpoints

### Login
- **Method**: `POST`
- **Path**: `/auth/login`
- **Auth**: None

**Request Body**:
```json
{
    "email": "admin@example.com",
    "password": "password123",
    "device_name": "postman"
}
```

**Expected Response (200 OK)**:
```json
{
    "message": "Logged in successfully.",
    "data": {
        "token": "1|Abc123xyz...",
        "user": { ... }
    }
}
```

---

### Logout
- **Method**: `POST`
- **Path**: `/auth/logout`
- **Auth**: Bearer Token

**Expected Response (200 OK)**:
```json
{
    "message": "Logged out successfully."
}
```

---

### My Profile (Me)
- **Method**: `GET`
- **Path**: `/auth/me`
- **Auth**: Bearer Token

**Expected Response (200 OK)**:
```json
{
    "message": "Current user retrieved successfully.",
    "data": {
        "user": { ... },
        "permissions": ["view_sales", "edit_inventory", ...]
    }
}
```

---

## User Management

### List Users
- **Method**: `GET`
- **Path**: `/users`
- **Auth**: Bearer Token (`manage_users`)

**Expected Response (200 OK)**:
```json
{
    "message": "Users retrieved successfully.",
    "data": [ ... ]
}
```

---

### Create User
- **Method**: `POST`
- **Path**: `/users`
- **Auth**: Bearer Token (`manage_users`)

**Request Body**:
```json
{
    "name": "Staff Member",
    "email": "staff@example.com",
    "password": "password123",
    "role": "staff"
}
```

---

### Update User
- **Method**: `PUT`
- **Path**: `/users/{user}`
- **Auth**: Bearer Token (`manage_users`)

---

### Assign Permissions
- **Method**: `POST`
- **Path**: `/users/{user}/permissions`
- **Auth**: Bearer Token (`manage_users`)

**Request Body**:
```json
{
    "permissions": ["view_sales", "create_sale"]
}
```

---

## Category Management

### List Categories
- **Method**: `GET`
- **Path**: `/categories`
- **Query Params**: `type` (part/service/accessory), `search`, `per_page`

**Expected Response (200 OK)**:
```json
{
    "message": "Categories retrieved successfully.",
    "data": { ... pagination ... }
}
```

---

### Create Category
- **Method**: `POST`
- **Path**: `/categories`

**Request Body**:
```json
{
    "name": "Brakes",
    "type": "part",
    "description": "Brake system components"
}
```

**Expected Response (201 Created)**:
```json
{
    "message": "Category created successfully.",
    "data": {
        "id": 1,
        "name": "Brakes",
        "type": "part",
        "description": "Brake system components",
        "created_at": "...",
        "updated_at": "..."
    }
}
```

---

## Brand Management

### List Brands
- **Method**: `GET`
- **Path**: `/brands`

**Request Body (POST/PUT)**:
```json
{
    "name": "Honda",
    "description": "Japanese Manufacturer"
}
```

---

## Bike Blueprints (Catalogs)

These are the definitions of motorcycle models (Brand, Model, Year).

### List Bikes
- **Method**: `GET`
- **Path**: `/bikes`
- **Query Params**: `brand`, `model`, `year`, `search`, `per_page`

### Create Bike Blueprint
- **Method**: `POST`
- **Path**: `/bikes`

**Request Body**:
```json
{
    "brand": "Yamaha",
    "model": "MT-07",
    "year": 2024
}
```

---

## Product Management

### List Products
- **Method**: `GET`
- **Path**: `/products`
- **Query Params**: `search`, `type` (part/accessory), `category_id`, `brand_id`, `is_universal`, `per_page`

---

### Create Product
- **Method**: `POST`
- **Path**: `/products`

**Request Body**:
```json
{
    "type": "part",
    "name": "Engine Oil 10W40",
    "sku": "OIL-10W40-1L",
    "part_number": "9999-1234",
    "category_id": 1,
    "brand_id": 1,
    "qty": 100,
    "cost_price": 200,
    "selling_price": 350,
    "cost_price_usd": 4.5,
    "max_discount_type": "percentage",
    "max_discount_value": 10,
    "is_universal": false,
    "description": "Synthetic engine oil",
    "bike_ids": [1, 2],
    "units": [
        {
            "unit_name": "Litre",
            "conversion_factor": 1,
            "price": 350
        }
    ]
}
```

**Expected Response (201 Created)**:
Standard Product JSON including `category`, `brand`, and `units`.

---

### Calculate Product Price
- **Method**: `GET`
- **Path**: `/products/{product}/calculate-price`
- **Query Params**: `unit_id` (optional)

**Expected Response**:
```json
{
    "message": "Product price calculated successfully.",
    "data": {
        "product_id": 1,
        "unit_id": 1,
        "base_price": 350,
        "final_price": 350,
        "currency": "EGP",
        "exchange_rate": 50.5
    }
}
```

---

## Product Units

### Create Product Unit
- **Method**: `POST`
- **Path**: `/products/{product}/units`

**Request Body**:
```json
{
    "unit_name": "Box (10 units)",
    "conversion_factor": 10,
    "price": 3200
}
```

---

## Customer Management (CRM)

### Create Customer
- **Method**: `POST`
- **Path**: `/customers`

**Request Body**:
```json
{
    "name": "John Doe",
    "phone": "0123456789",
    "address": "123 Street, Cairo",
    "notes": "VIP Client",
    "bikes": [
        {
            "brand": "BMW",
            "model": "R1250GS",
            "year": 2023,
            "modifications": "Akrapovic Exhaust",
            "notes": "Main bike"
        }
    ]
}
```

---

### Update Customer Bike
- **Method**: `PUT`
- **Path**: `/customer-bikes/{customer_bike}`

**Request Body**:
```json
{
    "modifications": "New tires added",
    "notes": "Updated service notes"
}
```

---

## Bike Inventory (For Sale)

These are specific motorcycles in stock for sale (e.g., showroom or consignment).

### Create Bike Inventory
- **Method**: `POST`
- **Path**: `/bike-inventory`

**Request Body**:
```json
{
    "bike_id": 1,
    "type": "consignment",
    "cost_price": 500000,
    "selling_price": 550000,
    "mileage": 5000,
    "cc": 1000,
    "horse_power": 200,
    "owner_name": "Alice Smith",
    "owner_phone": "0111111111",
    "notes": "Mint condition"
}
```

---

## Service Management

### List Services
- **Method**: `GET`
- **Path**: `/services`
- **Query Params**: `category_id`, `search`, `per_page`

---

### Create Service
- **Method**: `POST`
- **Path**: `/services`

**Request Body**:
```json
{
    "category_id": 3,
    "name": "Full Engine Service",
    "price": 1200,
    "max_discount_type": "percentage",
    "max_discount_value": 15,
    "description": "Oil, filter, and full checkup"
}
```

---

## Sales & Payments

The sales flow typically involves creating a sale, adding items, adding payments, and completing.

### Create Sale
- **Method**: `POST`
- **Path**: `/sales`

**Request Body**:
```json
{
    "customer_id": 1,
    "seller_id": 1,
    "type": "garage"
}
```
*Note: You can also use `customer` (array) instead of `customer_id` to create a customer inline.*

---

### Add Item to Sale
- **Method**: `POST`
- **Path**: `/sales/{sale}/items`

**Request Body**:
```json
{
    "item_type": "product",
    "item_id": 1,
    "qty": 2,
    "discount": 50
}
```

---

### Add Payment
- **Method**: `POST`
- **Path**: `/sales/{sale}/payments`

**Request Body**:
```json
{
    "amount": 500,
    "method": "cash",
    "status": "completed"
}
```

---

### Complete Sale
- **Method**: `POST`
- **Path**: `/sales/{sale}/complete`

---

## Sale Returns

### Return Sale Item
- **Method**: `POST`
- **Path**: `/sales/{sale}/returns`

**Request Body**:
```json
{
    "item_id": 1,
    "qty": 1,
    "reason": "Damaged on arrival"
}
```

---

## Service Tickets (Workshops)

### Create Ticket
- **Method**: `POST`
- **Path**: `/tickets`

**Request Body**:
```json
{
    "customer_id": 1,
    "customer_bike_id": 1,
    "notes": "Engine light is on"
}
```

---

### Ticket Lifecycle
- `POST /tickets/{ticket}/start`: Change status to `in_progress`.
- `POST /tickets/{ticket}/complete`: Change status to `completed`.
- `POST /tickets/{ticket}/reopen`: Change status back to `in_progress`.

---

### Ticket Tasks
- **Create Task**: `POST /tickets/{ticket}/tasks`
    ```json
    { "name": "Change Oil", "status": "pending" }
    ```
- **Assign Item to Task**: `POST /tickets/{ticket}/tasks/{task}/items`
    ```json
    {
        "item_type": "product",
        "item_id": 1,
        "qty": 4,
        "price_source": "current"
    }
    ```

---

## Finance & Reports

### Create Expense
- **Method**: `POST`
- **Path**: `/expenses`

**Request Body**:
```json
{
    "category": "bills",
    "amount": 1500,
    "expense_date": "2024-04-07",
    "paid_by": "cash",
    "is_recurring": true,
    "recurring_type": "monthly"
}
```

---

### Reports
- `GET /reports/profit-loss?start_date=2024-01-01&end_date=2024-04-30`
- `GET /reports/daily?date=2024-04-07`
- `GET /reports/cash-bank`

---

## System Tools

### Dashboard Metrics
- **Method**: `GET`
- **Path**: `/dashboard/metrics`

---

### Recovery (Soft Deletes)
- **List Trashed**: `GET /recovery/{entity}` (entities: `products`, `customers`, etc.)
- **Restore**: `POST /recovery/{entity}/{id}/restore`

---

### Activity Logs
- **Method**: `GET`
- **Path**: `/logs`
- **Query Params**: `user_id`, `action`, `search`, `per_page`
