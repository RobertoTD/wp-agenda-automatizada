
This structure defines **clear separation** between:

- **Client-side logic** (`/assets/js`)
- **Server-side WordPress logic** (`/includes`)
- **Rendered templates** (`/views`)
- **Technical documentation** (`/docs`)

---

## 3. Architectural Layers

### 3.1 JavaScript Layer (Frontend + Admin)

The JS architecture follows an **MVC-inspired pattern**:

#### **Controllers**
Location:  
`/assets/js/controllers/`

Responsibilities:
- Orchestrate flows between UI and services.
- Contain logic for:
  - availability flow,
  - slot selection,
  - appointment creation,
  - admin interactions.
- Should **not** contain low-level business rules or API calls directly.

Examples:  
`availabilityController.js`

---

#### **Services**
Location:  
`/assets/js/services/`

Responsibilities:
- Handle communication with WordPress AJAX endpoints.
- Handle communication with your external Node backend if needed.
- Contain business logic:
  - calculating availability,
  - preparing request payloads,
  - validating parameters,
  - interpreting server responses.

Examples:  
`reservationService.js`

---

#### **UI Components**
Location:  
`/assets/js/ui/`

Responsibilities:
- Display and update the interface.
- Handle DOM rendering:
  - calendars,
  - slot selectors,
  - admin views.
- Should never include business logic or API logic.

Examples:  
`calendarUI.js`, `slotSelectorUI.js`

---

#### **Utilities**
Location:  
`/assets/js/utils/`

Responsibilities:
- Reusable helpers, date utilities, formatting.

Example:  
`dateUtils.js`

---

#### **Entry Points**
- `main-admin.js` → loaded only on admin pages  
- `main-frontend.js` → loaded on client booking page

Responsibilities:
- Initialize controllers
- Attach handlers
- Load UI components

---

## 4. PHP Layer (WordPress)

The PHP side also follows a modular structure:

### **Controllers**
Location:  
`/includes/controllers/`

Responsibilities:
- Handle AJAX requests.
- Validate input.
- Invoke services and return JSON responses.
- Should contain no business logic beyond request validation/dispatch.

---

### **Models**
Location:  
`/includes/models/`

Responsibilities:
- Represent database entities (Appointment, Client, Staff, Service).
- Provide CRUD operations using:
  - WordPress tables,
  - custom tables,
  - or external API integration.

Models keep **data logic** isolated from controllers.

---

### **Services**
Location:  
`/includes/services/`

Responsibilities:
- Business rules:
  - slot validation,
  - conflict detection,
  - multi-staff rules,
  - cancellation logic.
- Should not render views or handle JSON encoding.

Services are reusable by controllers.

---

### **Templates / Views**
Location:  
`/views/`

Responsibilities:
- Render admin pages and form templates.
- Should contain no business logic.
- Should not call external APIs directly.

---

## 5. Main Plugin Loader (your-plugin.php)

Responsibilities:
- Define constants.
- Load dependencies.
- Register scripts/styles:
  - enqueue `main-admin.js` and `main-frontend.js`.
- Register AJAX endpoints.
- Initialize the plugin lifecycle.

This file must remain as small as possible and act **only as the bootstrapping mechanism**.

---

## 6. Interaction Between Layers

### **Frontend JS → WordPress AJAX (PHP)**  
Actions:
- Fetch availability  
- Create/modify appointments  
- Validate booking parameters  

Flow:
1. Controller sends request to a WordPress AJAX action.  
2. PHP controller validates/dispatches.  
3. PHP service performs business logic.  
4. Model reads/writes data.  
5. Response returns to JS service.  
6. UI updates based on service response.

---

### **PHP → Node Backend (Optional for MVP)**  
Used only for:
- OAuth token refresh
- Calendar sync
- Secure HMAC requests

The plugin must treat Node backend as a **secondary system**, not the primary source of truth.

---

### **Frontend/Public Page → Datepicker → JS Services**
- Client picks a date.
- Controller fetches availability from PHP.
- SlotSelectorUI renders options.
- ReservationService sends booking request.
- PHP controller stores appointment in database.

---

## 7. Naming Conventions

- Controllers end with `Controller.js` or `-controller.php`.
- Services end with `Service.js` or `-service.php`.
- Models are singular nouns: `Appointment.php`.
- UI components end with `UI.js`.
- Utils end with `Utils.js`.

---

## 8. Avoiding Complexity (“No God Files” Rule)

**DO NOT** mix the following in any file:

| Concern | Must live in… |
|--------|----------------|
| DOM manipulation | `/assets/js/ui/` |
| API calls | `/assets/js/services/` |
| Multi-staff logic | `/includes/services/` |
| WordPress hooks | `your-plugin.php` |
| View rendering | `/views/` |

Any violation of this rule triggers a refactor.

---

## 9. Data Flow Principles

1. **UI never talks directly to PHP models.**  
   Always through JS services → PHP controllers → PHP services → models.

2. **Frontend input must always be re-validated server-side.**  
   Never trust client-side validation.

3. **Business logic lives in PHP services, not JS controllers.**

4. **Date/time logic should live in JS utils for consistency.**

---

## 10. Scalability Principles

As the plugin grows:

- Add more services—not more complexity inside one service.
- Keep admin JS modular.
- Treat each feature as a separate domain:
  - reports,
  - staff management,
  - availability,
  - reservations.

Follow the “1 file → 1 responsibility” principle.

---

## 11. Out-of-Scope (Not Part of Architecture Yet)

- Mobile app
- Full PWA offline capability
- Payment gateways
- Marketing workflows
- Multi-location businesses
- Multi-tenant provisioning (future Wizard project)

---

## 12. Final Notes

This architecture document must remain **alive**.  
Whenever a new feature is added:

- update controllers/services/ui diagrams,
- ensure new files follow naming conventions,
- avoid mixing layers.

This ensures DEOIA remains scalable and commercially reliable.

