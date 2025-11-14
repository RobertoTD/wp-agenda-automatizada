# DEOIA – MVP Scope Document
Version: 1.0  
Last Updated: (2025-11-14)

---

## 1. Purpose of the MVP
The Minimum Viable Product aims to deliver a functional, reliable, and simple
appointment-management system for small service businesses such as salons,
estéticas, barbershops, spas, therapists, and similar service-based operations.

The MVP must be good enough to install directly in real businesses and offer
as a free trial, without requiring a mobile app or complex onboarding.

The priorities are:
- reduce friction for assistants/owners registering appointments,
- allow clients to self-book via a public page + QR,
- provide clear, useful business visibility through daily and monthly reports.

---

## 2. Roles
### 2.1 Owner (Business Owner)
- Views **today’s appointments**.
- Views **upcoming appointments** (next days).
- Accesses **history** of past appointments.
- Generates **daily, weekly/bi-weekly, and monthly** reports.
- Configures services, staff, and business hours.

### 2.2 Assistant / Secretary
- Registers appointments by phone or in-person.
- Supports **multi-staff, multi-service** schedules:
  - e.g., 5pm haircut with Stylist A,
  - simultaneously 5pm dye with Stylist B.
- Manages basic client info (name, phone, notes).
- Can cancel or reschedule appointments.

### 2.3 Client (Customer)
- Visits a simple public booking page.
- Selects service → chooses available slot → enters contact info.
- Optionally accesses the page via **printed QR cards**.

---

## 3. Core Features of the MVP

### 3.1 Appointment Booking
- Bookings created by:
  - clients (public page),
  - assistants (admin panel).
- Booking rules:
  - A staff member cannot be double-booked.
  - Multiple staff members may have simultaneous appointments.
  - Service duration determines the time slot.
- Status states:
  - scheduled, attended, cancelled.

### 3.2 Staff Management
- Add/edit/remove staff members.
- Each staff member has:
  - name,
  - role/title,
  - services they can perform.

### 3.3 Services Management
- Add/edit/remove services.
- Each service has:
  - name,
  - duration (30/60/90 minutes),
  - optional price.

### 3.4 Client Management
- Basic client profile:
  - name,
  - phone,
  - optional email,
  - notes.
- Appointment history for each client.

### 3.5 Admin Panel (WordPress)
- Today’s agenda dashboard.
- Create/edit appointments.
- Cancel/reschedule.
- Manage staff/services.
- View reports.

### 3.6 Public Booking Page
- Service selection.
- Date/time selection (based on staff and business hours).
- Client info form.
- Confirmation message/page.
- QR-friendly URL.

---

## 4. Reporting

### 4.1 Daily Report
- Number of appointments.
- Breakdown by staff.
- Breakdown by service.
- List of attended and cancelled appointments.

### 4.2 Weekly / Bi-weekly Report
- Same structure as daily.
- Focused on operational trends.

### 4.3 Monthly Report
- Total appointments.
- Top services.
- Most active staff.
- Cancellations summary.

### 4.4 Export
- Optional CSV export for future versions.
- Not required for MVP.

---

## 5. Technical Constraints for MVP

### 5.1 Architecture
- WordPress plugin handles:
  - UI,
  - admin panel,
  - business logic,
  - public booking page,
  - reporting interface.

- Backend (Node.js) handles ONLY:
  - OAuth,
  - Google Calendar integration,
  - secure token storage,
  - HMAC validation,
  - calendar reads/writes.

### 5.2 Non-goals (NOT included in MVP)
- Mobile app.
- Full CRM system.
- POS or payments integration.
- Advanced analytics dashboards.
- n8n workflows.
- Multi-location businesses.
- Automated marketing messages.
- Loyalty programs or points.
- Offline-first PWA functionality (may partially emerge later).

---

## 6. Success Criteria
The MVP is successful if:
- Assistants can register multi-staff appointments quickly.
- Owners can see daily/monthly activity clearly.
- Clients can book from the public page without confusion.
- All flows work reliably in a real business environment.
- Installation and configuration remain simple.

---

## 7. Vision After MVP
The next steps after MVP may include:
- PWA behavior or lightweight mobile access.
- Automated reminders (SMS/WhatsApp/email).
- Multi-location businesses.
- More advanced reporting and analytics.
- Deployable “wizard” to auto-create client websites.
