Perfect. Limiting **Hero → Home page only** is the *right* call 👍
This simplifies Cartly a lot and keeps Cursor/Codex sane.

Below is **clean, copy-paste-ready “Cursor Language”** you can drop directly into your docs or rules file.

---

## 1️⃣ Ground Rules (Cursor Language – MUST FOLLOW)

HERO SYSTEM RULES (CARTLY)

* Hero is allowed ONLY for Home Page
* There is exactly ONE Hero per shop
* Hero is NOT reusable across pages
* Hero type controls layout
* Hero content is stored as JSON
* Admin UI must dynamically change fields based on hero type
* Supported Hero Types:

  1. banner   (single image)
  2. slider   (multiple slides)
  3. headline (text only)
* No other hero types are allowed
* Grid, category blocks, image blocks are NOT hero types

---

## 2️⃣ Database Design (Final & Minimal)

### ✅ Table: `shops`

Hero belongs to shop, because only Home page uses it.

DATABASE: shops table

Add the following columns:

* hero_type VARCHAR(20) NOT NULL
  Allowed values:

  * banner
  * slider
  * headline

* hero_json JSON NOT NULL
  Stores hero content and config

Hero data must NEVER be stored in pages table.
Hero data must NEVER be duplicated.

---

## 3️⃣ `hero_json` Schema (STRICT)

Cursor must treat this as a **contract**, not suggestion.

---

### 🟦 Hero Type: `banner`

HERO JSON SCHEMA: banner

{
"type": "banner",
"content": {
"title": "Main heading text",
"subtitle": "Optional subheading",
"image": "path/to/image.webp",
"cta": {
"text": "Shop Now",
"link": "/products"
},
"align": "left | center",
"overlay": 0.4
}
}

Rules:

* image is REQUIRED
* title is REQUIRED
* subtitle is OPTIONAL
* cta is OPTIONAL
* overlay value range: 0.0 to 0.8

---

### 🟦 Hero Type: `slider`

HERO JSON SCHEMA: slider

{
"type": "slider",
"config": {
"autoplay": true,
"interval": 4000
},
"slides": [
{
"title": "Slide title",
"subtitle": "Slide subtitle",
"image": "slide1.webp",
"cta": {
"text": "Explore",
"link": "/collections"
}
}
]
}

Rules:

* slides array must contain at least 1 slide
* image is REQUIRED for every slide
* title is OPTIONAL per slide
* subtitle is OPTIONAL per slide
* cta is OPTIONAL per slide
* autoplay defaults to true
* interval defaults to 4000 ms

---

### 🟦 Hero Type: `headline` (SEO-first)

HERO JSON SCHEMA: headline

{
"type": "headline",
"content": {
"title": "H1 heading text",
"description": "Rich text description",
"cta": {
"text": "View Products",
"link": "/products"
}
}
}

Rules:

* title is REQUIRED (must render as H1)
* description is REQUIRED
* image is NOT allowed
* Only one CTA allowed

---

## 4️⃣ Admin Form Schema (Dynamic)

Cursor must build **ONE form**, not three separate ones.

---

### 🧩 Step 1: Hero Type Selector

ADMIN FORM: Hero Type Selector

Field:

* hero_type (radio buttons)

Options:

* Banner – Single image with text
* Slider – Multiple sliding banners
* Headline – Text-only, SEO friendly

Changing hero_type resets hero_json after confirmation.

---

### 🧩 Step 2: Dynamic Fields per Type

---

#### 🟦 Banner Form Fields

ADMIN FORM FIELDS: banner

* Title (text, required)
* Subtitle (text)
* Image Upload (required)
* CTA Text (optional)
* CTA Link (optional)
* Text Alignment (left | center)
* Overlay Opacity (slider 0.0 – 0.8)

Preview must update live.

---

#### 🟦 Slider Form Fields

ADMIN FORM FIELDS: slider

Repeatable Slides:

* Image Upload (required)
* Title
* Subtitle
* CTA Text
* CTA Link

Slide Controls:

* Add Slide
* Remove Slide
* Reorder Slide

Advanced Settings:

* Autoplay (toggle)
* Interval (number, ms)

At least one slide is mandatory.

---

#### 🟦 Headline Form Fields

ADMIN FORM FIELDS: headline

* Title (text, required, rendered as H1)
* Description (rich text, required)
* CTA Text (optional)
* CTA Link (optional)

No image upload allowed.

---

## 5️⃣ Backend Validation Rules (MANDATORY)

BACKEND VALIDATION RULES

* hero_type must be one of: banner, slider, headline
* hero_json must match schema for selected hero_type
* Reject saving if required fields are missing
* Reject image fields for headline hero
* Reject slides array if empty
* Only one hero allowed per shop

---

## 6️⃣ Rendering Rule (Frontend)

FRONTEND HERO RENDERING RULE

* Render hero ONLY on Home Page
* Fetch hero_type and hero_json from shops table
* Switch renderer based on hero_type
* Never attempt fallback rendering
* If hero_json is invalid, do not render hero

---

## 7️⃣ Why this will work well for Cartly Clients

* Only **3 clear choices**
* No layout confusion
* SEO-safe
* Easy preview
* Easy future extension (sections system later)

---
