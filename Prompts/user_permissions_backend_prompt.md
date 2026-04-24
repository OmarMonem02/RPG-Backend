You are a Senior Laravel Backend Architect building a production-grade permissions cutover for the RPG ERP API.

Your task is to implement per-user permission overrides, expose effective permissions to the frontend, and enforce those permissions across the mapped backend routes.

---

# CONTEXT

- The backend currently uses coarse role middleware (`admin`, `staff`, `Technician`).
- The frontend now manages a 16-page permission matrix per user.
- The backend must own the permission catalog and must not trust client-defined pages or actions.
- If a user has a saved override, that override must be enforced instead of role defaults.

---

# REQUIRED PERMISSION CATALOG

## Valid pages

- `dashboard`
- `sales`
- `maintenance`
- `inventory`
- `brands`
- `products`
- `bikes`
- `spare-parts`
- `maintenance-services`
- `users`
- `import-export`
- `payment-methods`
- `product-categories`
- `spare-part-categories`
- `bike-blueprints`
- `sellers`

## Valid actions

- `create`
- `read`
- `update`
- `delete`
- `export`
- `import`

---

# STORAGE RULES

## Users table

- Add nullable JSON column: `permissions_override`
- `null` means the user still uses role defaults
- Non-null values store a full normalized matrix for all 16 pages

## Normalization

- Treat updates as full replacement
- Require all 16 page keys in every update request
- Allow empty arrays
- Reject unknown page keys with `422`
- Reject missing page keys with `422`
- Reject unknown actions with `422`
- Remove duplicate actions consistently
- Preserve server-owned action ordering

---

# DEFAULT ROLE MATRICES

## Admin

- `dashboard`: `read`
- `sales`, `maintenance`, `brands`, `products`, `bikes`, `spare-parts`, `maintenance-services`, `users`, `payment-methods`, `product-categories`, `spare-part-categories`, `bike-blueprints`, `sellers`: `create`, `read`, `update`, `delete`
- `import-export`: `read`, `export`, `import`

## Staff

- `sales`: `create`, `read`, `update`, `delete`
- `maintenance`: `create`, `read`, `update`, `delete`

## Technician

- `maintenance`: `create`, `read`, `update`, `delete`

## Unmapped frontend pages

- Keep them in the returned matrix
- Default them to empty unless current backend behavior clearly implies access

---

# REQUIRED API CONTRACT

## Update permissions

Endpoint:

`PUT /api/users/{id}/permissions`

Body:

```json
{
  "permissions": {
    "dashboard": ["read"],
    "sales": ["create", "read", "update"],
    "maintenance": ["read", "update"],
    "inventory": [],
    "brands": [],
    "products": [],
    "bikes": [],
    "spare-parts": [],
    "maintenance-services": ["read"],
    "users": [],
    "import-export": [],
    "payment-methods": [],
    "product-categories": [],
    "spare-part-categories": [],
    "bike-blueprints": [],
    "sellers": []
  }
}
```

Rules:

- Only admins can update user permissions
- Keep the endpoint behind admin-only protection
- If an admin edits their own permissions, they must retain both `users.read` and `users.update`
- Return the updated effective payload immediately:

```json
{
  "user": {
    "id": 1,
    "name": "Admin User",
    "email": "admin@rpg.com",
    "role": "admin",
    "permissions": {
      "dashboard": ["read"],
      "sales": ["create", "read", "update", "delete"]
    }
  }
}
```

## Include effective permissions in user payloads

Return the same `user.permissions` shape in:

- `POST /api/login`
- `GET /api/me`
- `GET /api/users/{id}`

Use the effective matrix, never the raw `permissions_override` column.

---

# ROUTE ENFORCEMENT CUTOVER

Implement permission middleware with the contract:

`permission:page,action`

Use override-first evaluation:

- if override exists, enforce it
- otherwise enforce role defaults

## Route mapping

### Sales page

- `GET /sales/catalog-items`, `GET /sales`, `GET /sales/{sale}`, `GET /sales/{sale}/adjustments` => `sales.read`
- `POST /sales` => `sales.create`
- `PATCH /sales/{sale}`, `POST /sales/{sale}/items`, `PATCH /sales/{sale}/items/{saleItem}`, `POST /sales/{sale}/returns`, `POST /sales/{sale}/exchanges` => `sales.update`
- `DELETE /sales/{sale}`, `DELETE /sales/{sale}/items/{saleItem}` => `sales.delete`

### Maintenance page

- `GET /tickets`, `GET /tickets/{ticket}` => `maintenance.read`
- `POST /tickets` => `maintenance.create`
- `PATCH /tickets/{ticket}/status` => `maintenance.update`
- `DELETE /tickets/{ticket}` => `maintenance.delete`

### Users page

- `GET /users`, `GET /users/{id}` => `users.read`
- `POST /users` => `users.create`
- `PUT/PATCH /users/{id}` => `users.update`
- `DELETE /users/{id}` => `users.delete`

### Sellers page

- CRUD routes map to `sellers.create|read|update|delete`

### Spare parts page

- CRUD routes and low-stock => `spare-parts.read`
- create and bulk create => `spare-parts.create`
- updates, stock updates, bulk update => `spare-parts.update`
- deletes and bulk delete => `spare-parts.delete`

### Bike blueprints page

- list/show/linked reads => `bike-blueprints.read`
- create => `bike-blueprints.create`
- updates and spare-part assignment/replace flows => `bike-blueprints.update`
- deletes and linked spare-part removal => `bike-blueprints.delete`

### Generic entity routes

- `brands` => `brands`
- `products` => `products`
- `bike_for_sale` => `bikes`
- `maintenance_services` => `maintenance-services`
- `payment_methods` => `payment-methods`
- `product_categories` => `product-categories`
- `spare_part_categories` => `spare-part-categories`

CRUD verbs should map to `create|read|update|delete`.

### Import-export page

- `GET /import-export/entities` => `import-export.read`
- `GET /import-export/{entity}/export`, `GET /import-export/{entity}/template` => `import-export.export`
- `POST /import-export/{entity}/import`, `POST /import-export/{entity}/parse` => `import-export.import`

## Leave these on legacy role guards for now

- `settings`
- `history`
- `customers`
- `maintenance_service_sectors`
- `customer_bikes`
- `customer_sale`
- `sale_items`
- `deliveries`
- `ticket_tasks`
- `ticket_items`

---

# DELIVERABLES

Generate:

1. Migration for `users.permissions_override`
2. Server-owned permission catalog and role defaults
3. Request validation for the full permission matrix
4. Permission middleware
5. Updated login, me, and user-show payloads
6. `PUT /api/users/{id}/permissions`
7. Route cutover for mapped endpoints
8. Feature tests for update, validation, serialization, default behavior, override enforcement, and cross-route middleware checks

---

# ACCEPTANCE CHECKLIST

- Admin can save a full permission matrix for a user
- The update response returns effective permissions immediately
- Login, me, and user-show all return effective permissions
- Unknown pages and actions are rejected with `422`
- Duplicate actions are normalized consistently
- Non-admin callers receive `403` on permissions update
- Self-lockout is prevented for admin self-edits
- Users with overrides are authorized by override instead of role defaults
- Default role behavior still works without overrides
