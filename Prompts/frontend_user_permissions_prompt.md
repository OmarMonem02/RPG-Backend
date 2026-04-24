You are a Senior Frontend Engineer building a production-grade user permissions experience for the RPG ERP frontend.

Your task is to implement the frontend integration for the new backend-driven per-user permissions system.

---

# GOAL

Build a complete frontend permissions flow so the app can:

- load the effective permissions matrix from the backend
- let admins edit a user's permissions from the admin UI
- save the full matrix to the backend
- enforce page and action visibility from the returned matrix
- keep login, refresh, and session state consistent

---

# BACKEND CONTRACT YOU MUST FOLLOW

## Permissions are returned in user payloads

The backend now returns effective permissions in:

- `POST /api/login`
- `GET /api/me`
- `GET /api/users/{id}`

Expected shape:

```json
{
  "user": {
    "id": 1,
    "name": "Admin User",
    "email": "admin@rpg.com",
    "role": "admin",
    "permissions": {
      "dashboard": ["read"],
      "sales": ["create", "read", "update", "delete"],
      "maintenance": ["create", "read", "update", "delete"],
      "inventory": [],
      "brands": [],
      "products": [],
      "bikes": [],
      "spare-parts": [],
      "maintenance-services": [],
      "users": ["read", "update"],
      "import-export": [],
      "payment-methods": [],
      "product-categories": [],
      "spare-part-categories": [],
      "bike-blueprints": [],
      "sellers": []
    }
  }
}
```

Important:

- `permissions` is already the effective matrix
- frontend must not recalculate role defaults
- frontend must trust `user.permissions` as the source of truth

## Update endpoint

Use:

`PUT /api/users/{id}/permissions`

Request body:

```json
{
  "permissions": {
    "dashboard": ["read"],
    "sales": ["create", "read", "update"],
    "maintenance": ["read"],
    "inventory": [],
    "brands": [],
    "products": [],
    "bikes": [],
    "spare-parts": [],
    "maintenance-services": [],
    "users": ["read", "update"],
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

- send the full 16-page matrix every time
- do not send partial updates
- keep page keys exactly as defined by the backend
- values must be arrays of action strings
- empty arrays are valid

If save succeeds, the backend returns:

```json
{
  "user": {
    "id": 1,
    "name": "User Name",
    "email": "user@example.com",
    "role": "staff",
    "permissions": {
      "dashboard": ["read"]
    }
  }
}
```

If validation fails, expect `422`.

If a non-admin tries to update permissions, expect `403`.

If an admin removes their own required access, the backend rejects the save with `422`.

---

# SERVER-OWNED PERMISSION CATALOG

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

Frontend must use exactly these page keys and action names.

Do not invent aliases.

---

# FRONTEND REQUIREMENTS

## 1. Session and auth state

- store `user.permissions` with the authenticated user after login
- refresh the stored user from `/me` on app boot or refresh
- expose a central permission helper such as:
  - `hasPermission(page, action)`
  - `hasAnyPermission(page, actions[])`
  - `canReadPage(page)`
- make all permission decisions read from one shared source, not duplicated ad hoc checks

## 2. User permissions management screen

Implement an admin-only permissions editor for users.

Requirements:

- fetch the selected user from `GET /api/users/{id}`
- initialize the form from `user.permissions`
- render all 16 page rows even when a page has no actions
- allow toggling actions per page
- preserve empty arrays when nothing is selected
- on save, send the full matrix to `PUT /api/users/{id}/permissions`
- after save, update the local UI with the returned `user.permissions`
- if the edited user is the currently logged-in user, also update session auth state immediately

## 3. Navigation and page guards

Use `read` as the rule for showing page navigation and allowing page entry.

Examples:

- hide the Users page unless `users.read`
- hide the Sales page unless `sales.read`
- hide the Import/Export page unless `import-export.read`

If a user manually reaches a page they cannot read:

- redirect them to an allowed fallback page
- or show a clean unauthorized state

Do not leave the page partially interactive.

## 4. Action-level UI guards

Use page action permissions to show or disable important controls.

Examples:

- `create` for create buttons and add flows
- `update` for edit buttons, status updates, and save actions
- `delete` for delete buttons and destructive actions
- `export` for export/template download UI
- `import` for upload/parse/import UI

Buttons should not be shown if the action is forbidden unless the product already uses disabled states consistently.

## 5. Page mapping to use in UI

### Main pages

- Sales features use `sales`
- Tickets / workshop / maintenance features use `maintenance`
- User management uses `users`
- Seller management uses `sellers`
- Spare parts uses `spare-parts`
- Bike blueprints uses `bike-blueprints`
- Brands uses `brands`
- Products uses `products`
- Bikes for sale uses `bikes`
- Maintenance services uses `maintenance-services`
- Payment methods uses `payment-methods`
- Product categories uses `product-categories`
- Spare part categories uses `spare-part-categories`
- Import / export uses `import-export`

### Notes

- `dashboard` and `inventory` exist in the matrix and should be honored in frontend navigation and visibility logic
- some backend APIs are still role-guarded for uncataloged areas, but frontend should still use the new matrix for the pages listed above

## 6. Save-flow UX

- show loading state during save
- prevent duplicate submissions
- show success feedback on save
- render backend validation errors cleanly
- if save fails with `422`, keep the current form state so the admin can fix it
- if save fails with `403`, show a permission error state

## 7. Self-edit edge case

If an admin edits their own permissions:

- allow the save request
- surface the backend validation message if it rejects removal of required users access
- if the save succeeds, immediately update the current session permissions and re-run guards

---

# IMPLEMENTATION EXPECTATIONS

Generate or update:

1. Auth/session store updates so `user.permissions` is persisted in app state
2. Central permission utility or hook
3. Page/nav guards based on `read`
4. Action guards for create/update/delete/export/import
5. Admin permissions editor UI
6. API layer support for `PUT /api/users/{id}/permissions`
7. Error handling and optimistic-free save UX
8. Frontend tests for permission loading, save flow, and guarded rendering

---

# ACCEPTANCE CHECKLIST

- Logging in stores effective permissions in frontend state
- Refreshing the app from `/me` restores permissions correctly
- Admin can open a user, edit permissions, and save the full matrix
- The permissions editor always sends all 16 page keys
- Empty arrays are preserved correctly
- Navigation hides pages without `read`
- Create/edit/delete/import/export controls follow the right action keys
- Saving self-permission changes updates current-session access immediately when allowed
- Backend `422` and `403` responses are surfaced cleanly in the UI
- Frontend does not derive permissions from role names anymore

---

# IMPORTANT CONSTRAINTS

- Backend is the source of truth
- Do not hardcode role-based fallback logic in the frontend
- Do not send partial permission payloads
- Do not rename page keys between UI state and API requests
- Keep the implementation production-safe and easy to extend
