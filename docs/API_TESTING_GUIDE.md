# Garage ERP API Testing Guide

## Base URL

```text
http://127.0.0.1:8000/api
```

## Notes

- All endpoints below are based on the current `php artisan route:list`.
- Replace placeholder IDs like `{product}`, `{sale}`, `{ticket}`, `{task}`, `{expense}`, `{log}` with real database IDs.
- File uploads should be sent as `multipart/form-data`.
- Date format: `YYYY-MM-DD`

---

## 1. Sales APIs

### Create Sale

```http
POST /sales
Content-Type: application/json
```

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

### Add Item To Sale

```http
POST /sales/{sale}/items
Content-Type: application/json
```

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

### Add Payment To Sale

```http
POST /sales/{sale}/payments
Content-Type: application/json
```

```json
{
  "amount": 500,
  "method": "cash",
  "status": "completed"
}
```

### Complete Sale

```http
POST /sales/{sale}/complete
```

### Return Sale

```http
POST /sales/{sale}/return
```

---

## 2. Tickets APIs

### Create Ticket

```http
POST /tickets
Content-Type: application/json
```

```json
{
  "customer_id": 1,
  "customer_bike_id": 1,
  "notes": "Engine sound check"
}
```

### Start Ticket

```http
POST /tickets/{ticket}/start
```

### Complete Ticket

```http
POST /tickets/{ticket}/complete
```

### Reopen Ticket

```http
POST /tickets/{ticket}/reopen
```

### Add Ticket Note

```http
POST /tickets/{ticket}/notes
Content-Type: application/json
```

Staff note:

```json
{
  "type": "staff",
  "note": "Client approved extra work"
}
```

Client note:

```json
{
  "type": "client",
  "note": "Please finish before Friday"
}
```

### Add Task

```http
POST /tickets/{ticket}/tasks
Content-Type: application/json
```

```json
{
  "name": "Change engine oil",
  "status": "pending",
  "approved_by_client": true
}
```

### Update Task

```http
PUT /tickets/{ticket}/tasks/{task}
Content-Type: application/json
```

```json
{
  "name": "Change engine oil and filter",
  "status": "completed",
  "approved_by_client": true
}
```

### Delete Task

```http
DELETE /tickets/{ticket}/tasks/{task}
```

### Assign Item To Task

```http
POST /tickets/{ticket}/tasks/{task}/items
Content-Type: application/json
```

Service item:

```json
{
  "item_type": "service",
  "item_id": 1,
  "qty": 1,
  "price_source": "current"
}
```

Product item with current price:

```json
{
  "item_type": "product",
  "item_id": 1,
  "qty": 2,
  "price_source": "current"
}
```

Product item with old price:

```json
{
  "item_type": "product",
  "item_id": 1,
  "qty": 1,
  "price_source": "old"
}
```

### Remove Item From Task

```http
DELETE /tickets/{ticket}/tasks/{task}/items/{item}
```

---

## 3. Products APIs

### Create Product

```http
POST /products
Content-Type: application/json
```

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

```http
PUT /products/{product}
Content-Type: application/json
```

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

```http
POST /products/{product}/bikes
Content-Type: application/json
```

```json
{
  "bike_ids": [1, 2, 3]
}
```

### Get Compatible Products

```http
GET /products/compatible?brand=Honda&model=CBR&year=2022
```

### Calculate Product Price

```http
GET /products/{product}/calculate-price
GET /products/{product}/calculate-price?unit_id=1
```

### Create Product Unit

```http
POST /products/{product}/units
Content-Type: application/json
```

```json
{
  "unit_name": "half-liter",
  "conversion_factor": 0.5,
  "price": 45
}
```

### Update Product Unit

```http
PUT /products/{product}/units/{unit}
Content-Type: application/json
```

```json
{
  "unit_name": "half-liter",
  "conversion_factor": 0.5,
  "price": 50
}
```

### Delete Product Unit

```http
DELETE /products/{product}/units/{unit}
```

---

## 4. Inventory APIs

### Bulk Update Products

```http
POST /inventory/bulk-update
Content-Type: application/json
```

```json
{
  "product_ids": [1, 2, 3],
  "attributes": {
    "selling_price": 350,
    "max_discount_type": "percentage",
    "max_discount_value": 5
  }
}
```

### Import Products

```http
POST /inventory/import
Content-Type: multipart/form-data
```

Fields:

```text
file: products.csv
mode: upsert
```

### Export Products

```http
GET /inventory/export
GET /inventory/export?format=csv
GET /inventory/export?format=excel
```

### Download Import Template

```http
GET /inventory/template
GET /inventory/template?format=csv
GET /inventory/template?format=excel
```

### Update Exchange Rate

```http
POST /inventory/exchange-rate
Content-Type: application/json
```

```json
{
  "currency": "USD",
  "rate": 50.25
}
```

### Adjust Stock

```http
POST /inventory/adjust-stock
Content-Type: application/json
```

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

---

## 5. Expenses APIs

### List Expenses

```http
GET /expenses
GET /expenses?category=bills
GET /expenses?from_date=2026-04-01&to_date=2026-04-30
GET /expenses?min_amount=100&max_amount=1000
GET /expenses?search=rent
GET /expenses?paid_by=cash
```

### Create Expense

```http
POST /expenses
Content-Type: multipart/form-data
```

Fields:

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

```http
PUT /expenses/{expense}
Content-Type: multipart/form-data
```

Fields example:

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

```http
DELETE /expenses/{expense}
```

### Generate Recurring Expenses

```http
POST /expenses/generate-recurring
Content-Type: application/json
```

```json
{
  "for_date": "2026-05-06"
}
```

---

## 6. Reports APIs

All reports support:

- `from_date`
- `to_date`
- `date` for daily report
- `format=json|csv|excel|pdf`

### Profit & Loss

```http
GET /reports/profit-loss?from_date=2026-04-01&to_date=2026-04-30
GET /reports/profit-loss?from_date=2026-04-01&to_date=2026-04-30&format=pdf
```

### Balance Sheet

```http
GET /reports/balance-sheet?from_date=2026-04-01&to_date=2026-04-30
```

### Daily Report

```http
GET /reports/daily?date=2026-04-06
```

### Expenses Report

```http
GET /reports/expenses?from_date=2026-04-01&to_date=2026-04-30
```

### Cash & Bank Report

```http
GET /reports/cash-bank?from_date=2026-04-01&to_date=2026-04-30
```

---

## 7. Audit Logs APIs

### List Logs

```http
GET /logs
GET /logs?user_id=1
GET /logs?action=update
GET /logs?entity_type=product
GET /logs?from_date=2026-04-01&to_date=2026-04-30
```

### Show Log Details

```http
GET /logs/{log}
```

---

## Suggested Testing Order

1. Create products and units
2. Adjust stock
3. Create sale and add sale items
4. Create ticket, tasks, and assign items
5. Create expenses
6. Generate reports
7. Review audit logs

---

## Quick Seed IDs Checklist

Before testing, make sure these tables have records:

- `customers`
- `customer_bikes`
- `sellers`
- `categories`
- `brands`
- `bikes`
- `services`
- `users`

