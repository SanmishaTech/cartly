# Cartly Page Block Schema (LOCKED)

## Allowed Blocks

### 1. text
Use for long content (About, Policies, Terms).

Fields:
- html (rich text)

---

### 2. image_text
Use for content + visuals.

Variants:
- image_left
- image_right

Fields:
- image (path)
- alt (string, required)
- title (optional)
- html (rich text)

---

### 3. bullets
Use for features, policies, highlights.

Fields:
- title (optional)
- items (array of strings)

---

### 4. image
Use for standalone images.

Fields:
- image (path)
- alt (string, required)
- caption (optional)

---

### 5. faq
Use for FAQs and help content.

Fields:
- title (optional)
- items:
  - question
  - answer

Notes:
- FAQ block can generate FAQ schema in seo_metadata.
- FAQ is a block, NOT a page type.
