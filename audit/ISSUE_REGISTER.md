# PRIORITIZED AUDIT ISSUE REGISTER
**Project:** MY UGC VIDEO  
**Date:** 2026-11-06  
**Auditor:** Antigravity System Audit Engine  

---

## Issue Summary Matrix

| Issue ID | Severity | Category | Module | Status | Brief Description |
| :--- | :---: | :--- | :--- | :---: | :--- |
| **ISS-001** | **Medium** | Frontend / Backend | `contact.html` / `js/main.js` | Open | Public contact and inquiry forms are client-side UI simulations with no backend delivery. |
| **ISS-002** | **Low** | Project Maintenance | `.gitignore` | Open | `.gitignore` does not exclude database backups, logs directory, or verification files. |
| **ISS-003** | **Low** | Accessibility / SEO | `index.html` | Open | Single `<img>` tag in hero section missing `alt` attribute. |
| **ISS-004** | **Low** | Performance | `images/` | Open | 4 hero and service JPEG images exceed 500 KB (can be WebP compressed). |
| **ISS-005** | **Low** | PHP Compatibility | `scratch/` & Crawlers | Open | Deprecated `curl_close()` call under PHP 8.5+ (no-op since PHP 8.0). |

---

## Detailed Issue Breakdown

### ISS-001: Public Contact & Inquiry Forms Are Client-Side UI Simulations
- **Issue ID:** `ISS-001`
- **Severity:** **Medium**
- **Affected Files:**
  - [`contact.html`](file:///d:/ADITYA/UGC%20Website/contact.html) (Lines 180-240)
  - [`index.html`](file:///d:/ADITYA/UGC%20Website/index.html) (`#projectInquiryForm`)
  - [`js/main.js`](file:///d:/ADITYA/UGC%20Website/js/main.js) (Lines 420-560: `initContactForm`, `initHomepageInquiryForm`, `initNewsletterForms`)
- **Reproduction Steps:**
  1. Open `http://localhost/UGC%20Website/contact` in browser.
  2. Fill in Full Name, Work Email, Brand Name, and Project Details (15+ chars).
  3. Click "Send Project Inquiry".
  4. Inspect Network tab in browser DevTools.
- **Expected Behavior:** An HTTP POST request should be dispatched to a backend PHP handler (e.g. `api/contact.php`) that stores the inquiry in a database table (e.g. `inquiries`) and/or sends an email notification via SMTP.
- **Actual Behavior:** `js/main.js` intercepts `submit`, prevents default submission (`e.preventDefault()`), executes JavaScript regex validation, triggers a `setTimeout(..., 700)` simulating submission, and displays a green alert message: *"Inquiry Received. Thank you for connecting with MY UGC VIDEO..."*. No HTTP request is sent; no email or database record is created.
- **Root Cause:** The frontend template was originally built as a pure static HTML mockup. While the dynamic blog CMS and Schema Manager were fully implemented in PHP, a backend inquiry handling endpoint and database table have not yet been created.
- **Recommended Fix:**
  1. Create a MySQL table `inquiries` (`id`, `name`, `email`, `brand`, `role`, `service`, `message`, `ip_address`, `created_at`).
  2. Create a secure CSRF-protected backend endpoint `api/contact.php` (or `includes/contact-handler.php`).
  3. Connect `js/main.js` to dispatch a `fetch()` POST request to the endpoint.
- **Dependencies & Side Effects:** None. Does not break existing layout or styles.

---

### ISS-002: `.gitignore` Incomplete for Backups, Logs, and Verification Files
- **Issue ID:** `ISS-002`
- **Severity:** **Low**
- **Affected Files:**
  - [`.gitignore`](file:///d:/ADITYA/UGC%20Website/.gitignore)
- **Reproduction Steps:**
  1. Run `git status` in terminal.
  2. Observe untracked files in `database/backups/`, `logs/`, `config/indexnow_key.txt`, and verification txt files.
- **Expected Behavior:** Temporary database backups, local runtime error logs, lock files, and environment secrets should be ignored by Git to avoid accidental repository bloat or credential commits.
- **Actual Behavior:** `.gitignore` only contains `scratch/` and `*.log`. `database/backups/*.json` and `logs/*.json` appear as untracked files.
- **Root Cause:** Incomplete `.gitignore` patterns.
- **Recommended Fix:** Update `.gitignore` with:
  ```gitignore
  scratch/
  *.log
  logs/
  database/backups/
  .env
  config/indexnow_key.txt
  3a9d80f3f912c4d406e4bc4cb9a50fdb.txt
  ```
- **Dependencies & Side Effects:** None.

---

### ISS-003: Single `<img>` Tag in Hero Section Missing `alt` Attribute
- **Issue ID:** `ISS-003`
- **Severity:** **Low**
- **Affected Files:**
  - [`index.html`](file:///d:/ADITYA/UGC%20Website/index.html) (Line 142)
- **Reproduction Steps:**
  1. Inspect `index.html` line 142: `<img src="images/hero/hero-ugc-production.jpg">`.
- **Expected Behavior:** All images should have descriptive `alt` attributes for screen readers and SEO compliance.
- **Actual Behavior:** The `alt` attribute is omitted.
- **Root Cause:** Minor omission during initial HTML markup authoring.
- **Recommended Fix:** Add `alt="MY UGC VIDEO professional creator studio production team"`.
- **Dependencies & Side Effects:** None.

---

### ISS-004: 4 Static JPEG Images Exceed 500 KB
- **Issue ID:** `ISS-004`
- **Severity:** **Low**
- **Affected Files:**
  - `images/hero/hero-ugc-production.jpg` (719.24 KB)
  - `images/services/service-agency-ads.jpg` (699.56 KB)
  - `images/services/service-delhi-ugc.jpg` (719.24 KB)
  - `images/services/service-ugc-creation.jpg` (738.96 KB)
- **Reproduction Steps:**
  1. Check file sizes of images in `images/`.
- **Expected Behavior:** Web images should ideally be under 200-300 KB and served in modern formats (WebP).
- **Actual Behavior:** High-resolution uncompressed JPEGs increase the initial payload of the homepage and services hub.
- **Root Cause:** Original source camera photography assets were saved without web compression.
- **Recommended Fix:** Compress images to WebP format (~120-180 KB each) with JPEG fallbacks.
- **Dependencies & Side Effects:** None.

---

### ISS-005: Deprecated `curl_close()` Call under PHP 8.5+
- **Issue ID:** `ISS-005`
- **Severity:** **Low**
- **Affected Files:**
  - Inspection scripts and crawl tools in `scratch/`.
- **Reproduction Steps:**
  1. Run any cURL script under PHP 8.5.12.
- **Expected Behavior:** Clean execution without deprecation notices.
- **Actual Behavior:** PHP emits: `Deprecated: Function curl_close() is deprecated since 8.5, as it has no effect since PHP 8.0`.
- **Root Cause:** PHP 8.5 officially deprecated `curl_close()` because cURL handles are automatically closed when destroyed.
- **Recommended Fix:** Omit `curl_close()` or wrap in PHP version condition.
- **Dependencies & Side Effects:** None. Core production code does not rely on `curl_close()`.

