<laravel-boost-guidelines>
=== foundation rules ===

# Laravel Boost Guidelines

The Laravel Boost guidelines are specifically curated by Laravel maintainers for this application. These guidelines should be followed closely to ensure the best experience when building Laravel applications.

## Foundational Context

This application is a Laravel application and its main Laravel ecosystems package & versions are below. You are an expert with them all. Ensure you abide by these specific packages & versions.

- php - 8.3
- inertiajs/inertia-laravel (INERTIA_LARAVEL) - v2
- laravel/ai (AI) - v0
- laravel/framework (LARAVEL) - v13
- laravel/mcp (MCP) - v0
- laravel/prompts (PROMPTS) - v0
- laravel/sanctum (SANCTUM) - v4
- tightenco/ziggy (ZIGGY) - v2
- laravel/boost (BOOST) - v
- laravel/breeze (BREEZE) - v2
- laravel/pail (PAIL) - v1
- laravel/pint (PINT) - v1
- phpunit/phpunit (PHPUNIT) - v12
- @inertiajs/vue3 (INERTIA_VUE) - v2
- tailwindcss (TAILWINDCSS) - v3
- vue (VUE) - v3

## Skills Activation

This project has domain-specific skills available. You MUST activate the relevant skill whenever you work in that domain—don't wait until you're stuck.

- `ai-sdk-development` — TRIGGER when working with ai-sdk which is Laravel official first-party AI SDK. Activate when building, editing AI agents, chatbots, text generation, image generation, audio/TTS, transcription/STT, embeddings, RAG, vector stores, reranking, structured output, streaming, conversation memory, tools, queueing, broadcasting, and provider failover across OpenAI, Anthropic, Gemini, Azure, Groq, xAI, DeepSeek, Mistral, Ollama, ElevenLabs, Cohere, Jina, and VoyageAI. Invoke when the user references ai-sdk, the `Laravel\Ai\` namespace, or this project's AI features — not for Prism PHP or other AI packages used directly.
- `laravel-best-practices` — Apply this skill whenever writing, reviewing, or refactoring Laravel PHP code. This includes creating or modifying controllers, models, migrations, form requests, policies, jobs, scheduled commands, service classes, and Eloquent queries. Triggers for N+1 and query performance issues, caching strategies, authorization and security patterns, validation, error handling, queue and job configuration, route definitions, and architectural decisions. Also use for Laravel code reviews and refactoring existing Laravel code to follow best practices. Covers any task involving Laravel backend PHP code patterns.
- `mcp-development` — Use this skill for Laravel MCP development only. Trigger when creating or editing MCP tools, resources, prompts, or servers in Laravel projects. Covers: artisan make:mcp-* generators, mcp:inspector, routes/ai.php, Tool/Resource/Prompt classes, schema validation, shouldRegister(), OAuth setup, URI templates, read-only attributes, and MCP debugging. Do not use for non-Laravel MCP projects or generic AI features without MCP.
- `inertia-vue-development` — Develops Inertia.js v2 Vue client-side applications. Activates when creating Vue pages, forms, or navigation; using <Link>, <Form>, useForm, or router; working with deferred props, prefetching, or polling; or when user mentions Vue with Inertia, Vue pages, Vue forms, or Vue navigation.
- `tailwindcss-development` — Always invoke when the user's message includes 'tailwind' in any form. Also invoke for: building responsive grid layouts (multi-column card grids, product grids), flex/grid page structures (dashboards with sidebars, fixed topbars, mobile-toggle navs), styling UI components (cards, tables, navbars, pricing sections, forms, inputs, badges), adding dark mode variants, fixing spacing or typography, and Tailwind v3/v4 work. The core use case: writing or fixing Tailwind utility classes in HTML templates (Blade, JSX, Vue). Skip for backend PHP logic, database queries, API routes, JavaScript with no HTML/CSS component, CSS file audits, build tool configuration, and vanilla CSS.

## Conventions

- You must follow all existing code conventions used in this application. When creating or editing a file, check sibling files for the correct structure, approach, and naming.
- Use descriptive names for variables and methods. For example, `isRegisteredForDiscounts`, not `discount()`.
- Check for existing components to reuse before writing a new one.

## Verification Scripts

- Do not create verification scripts or tinker when tests cover that functionality and prove they work. Unit and feature tests are more important.

## Application Structure & Architecture

- Stick to existing directory structure; don't create new base folders without approval.
- Do not change the application's dependencies without approval.


## ╨Ф╨╛╨╝╨╡╨╜ ╨┐╤А╨╕╨╗╨╛╨╢╨╡╨╜╨╕╤П (╨░╨║╤В╤Г╨░╨╗╤М╨╜╨░╤П ╨║╨░╤А╤В╨░ ╨║╨╛╨┤╨░)

╨Ъ╤А╨░╤В╨║╨╛, ╤З╤В╨╛ ╨▓╨░╨╢╨╜╨╛ ╨╖╨╜╨░╤В╤М ╨┐╤А╨╕ ╨┐╤А╨░╨▓╨║╨░╤Е (Laravel + Inertia Vue 3 + Tailwind).

### ╨а╨╛╨╗╨╕ ╨╕ ╨▓╨╕╨┤╨╕╨╝╨╛╤Б╤В╤М ╨╝╨╛╨┤╤Г╨╗╨╡╨╣

- ╨Я╤А╨░╨▓╨╕╨╗╨░ ╨╛╨▒╨╗╨░╤Б╤В╨╡╨╣: `app/Support/RoleAccess.php` (`effectiveVisibilityAreasFromRolePayload`, `hasVisibilityArea`, ╨╗╨╡╨│╨░╤Б╨╕ `scripts` / ╨┐╨╛╨┤╨╝╨╛╨┤╤Г╨╗╨╕ ╨┐╨╛╨╝╨╛╤Й╨╜╨╕╨║╨░ ╨┐╤А╨╛╨┤╨░╨▓╤Ж╨░).
- ╨б╤В╤А╨░╨╜╨╕╤Ж╨░ ╤А╨╛╨╗╨╡╨╣: `resources/js/Pages/Roles/Index.vue` тАФ ╨║╨╛╨╗╨╛╨╜╨║╨╕ ╨▓ `roleColumns`, ╤Б╨╛╤Е╤А╨░╨╜╨╡╨╜╨╕╨╡ ╤З╨╡╤А╨╡╨╖ `router.patch`, ╨┐╨╛╤Б╨╗╨╡ ╤Г╤Б╨┐╨╡╤Е╨░ `replaceRoleColumnsFromInertiaPage(page)`; `visibility_areas` ╨┐╨╡╤А╨╡╨┤ ╨╛╤В╨┐╤А╨░╨▓╨║╨╛╨╣ ╤Д╨╕╨╗╤М╤В╤А╤Г╤О╤В╤Б╤П ╨┐╨╛ ╨░╨║╤В╤Г╨░╨╗╤М╨╜╨╛╨╝╤Г ╤Б╨┐╨╕╤Б╨║╤Г ╨╕╨╖ `visibilityAreaOptions` (`sanitizeVisibilityAreas`), ╤З╤В╨╛╨▒╤Л ╨╜╨╡ ╨╗╨╛╨╝╨░╤В╤М ╨▓╨░╨╗╨╕╨┤╨░╤Ж╨╕╤О ╨╗╨╡╨│╨░╤Б╨╕-╨║╨╗╤О╤З╨░╨╝╨╕.
- ╨Ю╨▒╤Й╨╕╨╡ ╨┤╨░╨╜╨╜╤Л╨╡ ╨░╨▓╤В╨╛╤А╨╕╨╖╨░╤Ж╨╕╨╕: `app/Http/Middleware/HandleInertiaRequests.php` тАФ `auth` ╨╕ ╤З╨░╤Б╤В╤М ╤Б╨▓╤П╨╖╨░╨╜╨╜╤Л╤Е ╨┐╤А╨╛╨┐╤Б╨╛╨▓ ╨╛╨▒╤С╤А╨╜╤Г╤В╤Л ╨▓ `Inertia::always(...)`, ╤З╤В╨╛╨▒╤Л ╨┐╤А╨╕ ╤З╨░╤Б╤В╨╕╤З╨╜╤Л╤Е reload (`only`) ╨╜╨╡ ╨╖╨░╨╗╨╕╨┐╨░╨╗╨╕ ╤Б╤В╨░╤А╤Л╨╡ `visibility_areas`.
- ╨Ь╨╡╨╜╤О CRM: `resources/js/Layouts/CrmLayout.vue` тАФ `visibleAreas` ╨╕╨╖ `auth.user.role.visibility_areas`; ╤Г ╨┐╤Г╨╜╨║╤В╨╛╨▓ ╤Б ╨┐╤А╨╛╨▓╨╡╤А╨║╨╛╨╣ ╨┤╨╛╨╗╨╢╨╜╨╛ ╨▒╤Л╤В╤М ╨┐╨╛╨╗╨╡ `visibilityArea` (╨▓ ╤В.╤З. ╨┤╨░╤И╨▒╨╛╤А╨┤).
- Middleware ╨╝╨░╤А╤И╤А╤Г╤В╨╛╨▓: `EnsureVisibilityAreaAccess`, `EnsureVisibilityAnyAreaAccess` тАФ ╤З╤В╨╡╨╜╨╕╨╡ ╤А╨╛╨╗╨╕ ╤З╨╡╤А╨╡╨╖ `Role::query()->find($user->role_id)`, ╨▒╨╡╨╖ ╨╖╨░╨▓╨╕╤Б╨╕╨╝╨╛╤Б╤В╨╕ ╨╛╤В ╤З╨░╤Б╤В╨╕╤З╨╜╨╛ ╨╖╨░╨│╤А╤Г╨╢╨╡╨╜╨╜╨╛╨│╨╛ relation ╨╜╨░ `User`.
- CRUD ╤А╨╛╨╗╨╡╨╣: `app/Http/Controllers/RoleManagementController.php` тАФ ╨▓ ╨С╨Ф ╤Г╤Е╨╛╨┤╨╕╤В ╤П╨▓╨╜╤Л╨╣ ╨╜╨░╨▒╨╛╤А ╨┐╨╛╨╗╨╡╨╣ (╨╜╨╡ ┬л╤Б╤Л╤А╨╛╨╣┬╗ spread ╨▓╤Б╨╡╨│╨╛ `validated()` ╨┤╨╗╤П ╨║╤А╨╕╤В╨╕╤З╨╜╤Л╤Е ╨░╤В╤А╨╕╨▒╤Г╤В╨╛╨▓); ╨║╨╛╨╗╨╛╨╜╨║╨░ `default_mobile_nav_keys` ╨╜╨░ `roles` (╨╡╤Б╨╗╨╕ ╨╡╤Б╤В╤М ╨▓ ╤Б╤Е╨╡╨╝╨╡) тАФ ╨┤╨╡╤Д╨╛╨╗╤В ╨╜╨╕╨╢╨╜╨╡╨╣ ╨┐╨░╨╜╨╡╨╗╨╕ ╨┤╨╗╤П ╨┐╨╛╨╗╤М╨╖╨╛╨▓╨░╤В╨╡╨╗╨╡╨╣ ╤А╨╛╨╗╨╕.
- ╨Т╨░╨╗╨╕╨┤╨░╤Ж╨╕╤П: `app/Http/Requests/StoreRoleRequest.php`, `UpdateRoleRequest.php` тАФ ╤Г `visibility_areas` ╨╝╨╕╨╜╨╕╨╝╤Г╨╝ ╨╛╨┤╨╜╨░ ╨╛╨▒╨╗╨░╤Б╤В╤М; ╤Б╨╛╨╛╨▒╤Й╨╡╨╜╨╕╤П ╨╜╨░ ╤А╤Г╤Б╤Б╨║╨╛╨╝.

### ╨Я╨╡╤З╨░╤В╨╜╤Л╨╡ ╤Д╨╛╤А╨╝╤Л (DOCX)

- ╨Ч╨░╨║╨░╨╖: `app/Services/OrderPrintFormDraftService.php` тАФ ╤Б╨╜╨╕╨╝╨╛╨║ ╨┤╨░╨╜╨╜╤Л╤Е, `TemplateProcessor`, ╨┐╨╛╨┤╤Б╤В╨░╨╜╨╛╨▓╨║╨░ ╨┐╨╗╨╡╨╣╤Б╤Е╨╛╨╗╨┤╨╡╤А╨╛╨▓ `${тАж}` ╨╕ `{{тАж}}`.
- ╨Ы╨╕╨┤: `app/Services/LeadPrintFormDraftService.php` тАФ ╨░╨╜╨░╨╗╨╛╨│╨╕╤З╨╜╨╛.
- ╨Ъ╨░╤А╤В╨░ ╨┐╨╗╨╡╨╣╤Б╤Е╨╛╨╗╨┤╨╡╤А╨╛╨▓ тЖТ ╨┐╤Г╤В╤М ╨▓ ╤Б╨╜╨╕╨╝╨║╨╡: `app/Support/PrintFormPlaceholderPathResolver.php` (legacy-╨╕╨╝╨╡╨╜╨░ ╨▓╤А╨╛╨┤╨╡ `stoimost`, `dolzhn_podpisant_rod`).
- ╨Т╨░╤А╨╕╨░╨╜╤В╤Л ╨╕╨╝╤С╨╜ ╨╝╨░╨║╤А╨╛╤Б╨╛╨▓ ╨┤╨╗╤П `setValue`: `app/Support/PrintFormPlaceholderMacroVariants.php` тАФ **╤В╨╛╨╗╤М╨║╨╛ ╤В╨╛╤З╨╜╨╛╨╡ ╨╕╨╝╤П**, ╨▒╨╡╨╖ ╨▓╨░╤А╨╕╨░╨╜╤В╨╛╨▓ ╤Б ╨┐╤А╨╛╨▒╨╡╨╗╨░╨╝╨╕ (╤З╤В╨╛╨▒╤Л ╨╜╨╡ ╨┐╨╛╤А╤В╨╕╤В╤М ╨▓╤С╤А╤Б╤В╨║╤Г ╨▓╨╛╨║╤А╤Г╨│ ╨┐╨╗╨╡╨╣╤Б╤Е╨╛╨╗╨┤╨╡╤А╨░).
- ╨Ъ╨░╤В╨░╨╗╨╛╨│ ╨┐╨╡╤А╨╡╨╝╨╡╨╜╨╜╤Л╤Е ╨┤╨╗╤П UI ╤И╨░╨▒╨╗╨╛╨╜╨╛╨▓: `app/Services/PrintFormVariableCatalog.php`.
- ╨б╤Г╨╝╨╝╤Л ╤Б ╨▓╨░╨╗╤О╤В╨╛╨╣ ╨▓ ╨╛╨┤╨╜╨╛╨╝ ╨┐╨╛╨╗╨╡: `order.customer_rate_with_currency`, `order.carrier_rate_with_currency` (╨╗╨╡╨│╨░╤Б╨╕ `stoimost*` ╨╝╨░╨┐╤П╤В╤Б╤П ╨╜╨░ ╨╜╨╕╤Е).
- Workflow ╨┐╨╡╤З╨░╤В╨╕ ╨╖╨░╨║╨░╨╖╨░: `OrderPrintDocumentWorkflowService` тАФ ╤З╨╡╤А╨╜╨╛╨▓╨╕╨║ тЖТ ╤Б╨╛╨│╨╗╨░╤Б╨╛╨▓╨░╨╜╨╕╨╡ тЖТ `materializeSignedPrintArtifacts()` тЖТ ╤Д╨╕╨╜╨░╨╗╤М╨╜╤Л╨╣ PDF.
- QR ╨┐╤А╨╛╨▓╨╡╤А╨║╨╕: ╨┐╨╗╨╡╨╣╤Б╤Е╨╛╨╗╨┤╨╡╤А╤Л `document_verification_code`, `document_verification_qr`; ╨║╨╛╨┤ `PrintFormVerificationCode` (╨╛╤В╨┤╨╡╨╗╤М╨╜╤Л╨╣ ╨╜╨░ ╨║╨░╨╢╨┤╤Л╨╣ `OrderDocument`); ╤А╨░╨╖╨╝╨╡╤А╤Л `PrintFormVerificationQrDimensions` / `config/documents.php` тЖТ `verification_qr`. ╨Ъ╨╛╨╜╤В╨╡╨║╤Б╤В `OrderPrintFormContext` (`documentVerificationCode`, `orderDocumentId`, `forTemplatePreview`). VML: QR ╨╜╨╡ ╤Г╤З╨░╤Б╤В╨▓╤Г╨╡╤В ╨▓ ╤Б╨╝╨╡╤Й╨╡╨╜╨╕╤П╤Е ╨┐╨╛╨┤╨┐╨╕╤Б╨╕/╨┐╨╡╤З╨░╤В╨╕ (`DocxVmlOverlayStylePatcher`, `countVerificationQrVmlShapes`). ╨Я╤Г╨▒╨╗╨╕╤З╨╜╨░╤П ╤Б╤В╤А╨░╨╜╨╕╤Ж╨░ **╨▒╨╡╨╖ auth**: `GET /verify/order-documents/{orderDocument}?code=тАж` тАФ ╨║╨╛╨╜╤В╤А╨░╨│╨╡╨╜╤В ╨┐╨╛ `metadata.party` (`PrintVerificationPageScope`). ╨Ф╨╛╨║╤Г╨╝╨╡╨╜╤В╨░╤Ж╨╕╤П: `docs/print-form-pdf-protection.md`, ╨║╨░╤А╤В╨╛╤З╨║╨░ `docs/sync/v5-local-Components-Print-Forms-Verification.md`.
- DocMDP (╨╛╨┐╤Ж╨╕╨╛╨╜╨░╨╗╤М╨╜╨╛, `PDF_CERTIFY_ENABLED`): `PdfDocumentCertificationService`, `config/pdf_signing.php`, `php artisan pdf-signing:generate-certificate`. ╨Я╨╛╤Б╨╗╨╡ Gotenberg: QR-╤И╤В╨░╨╝╨┐ (`PdfVerificationQrStampService`) тЖТ certify. ╨е╨╡╤И╨╕ ╨▓ `order_documents.metadata`: `pdf_certified_sha256`, `pdf_verification_*`.

### Saved views ╨│╤А╨╕╨┤╨╛╨▓ (P4)

- ╨Ь╨╛╨┤╨╡╨╗╤М `grid_views`, API `GridViewController` (`/grid-views`, auth), ╤Б╨╡╤А╨▓╨╕╤Б `GridViewService`, ╨║╨░╤В╨░╨╗╨╛╨│ `GridViewCatalog` (╨║╨╗╤О╤З╨╕ ╨│╤А╨╕╨┤╨╛╨▓ + URL ╤Б `?view=`).
- UI: `resources/js/Components/Grid/GridViewsBar.vue`, ╨║╨╗╨╕╨╡╨╜╤В `resources/js/support/gridViews.js` (`fetch` ╤Б `redirect: 'manual'` тАФ ╨╕╨╜╨░╤З╨╡ DELETE ╨┐╤А╨╕ 302 ╨╜╨░ login ╨╗╨╛╨╝╨░╨╡╤В╤Б╤П).
- ╨Ш╨╖╨▒╤А╨░╨╜╨╜╨╛╨╡ ╨▓ ╤Б╨░╨╣╨┤╨▒╨░╤А╨╡: `HandleInertiaRequests` тЖТ `auth.user.pinned_grid_views`, ╨▒╨╗╨╛╨║ ╨▓ `CrmLayout.vue`.
- ╨Ь╨╕╨│╤А╨░╤Ж╨╕╤П: `2026_06_12_162154_create_grid_views_table.php` тАФ **╨╛╨▒╤П╨╖╨░╤В╨╡╨╗╤М╨╜╨░** (`php artisan migrate`), ╨╕╨╜╨░╤З╨╡ ╨│╤А╨╕╨┤ ╨╖╨░╨║╨░╨╖╨╛╨▓ ╨┐╨░╨┤╨░╨╡╤В ╤Б `Unexpected token '<'`.

### ╨б╨║╨╗╨╛╨╜╨╡╨╜╨╕╨╡ ╨┤╨╛╨╗╨╢╨╜╨╛╤Б╤В╨╕ (╤А╨╛╨┤╨╕╤В╨╡╨╗╤М╨╜╤Л╨╣ ╨┐╨░╨┤╨╡╨╢, ╨▒╨╡╨╖ ╨▓╤В╨╛╤А╨╛╨│╨╛ ╨┐╨╛╨╗╤П ╨▓╨▓╨╛╨┤╨░)

- `app/Support/RussianPositionInflector.php` тАФ ╤Н╨▓╤А╨╕╤Б╤В╨╕╨║╨╕ + fallback ╨╜╨░ ╨╕╤Б╤Е╨╛╨┤╨╜╤Г╤О ╤Б╤В╤А╨╛╨║╤Г.
- ╨Т ╤Б╨╜╨╕╨╝╨╛╨║ ╨║╨╛╨╜╤В╤А╨░╨│╨╡╨╜╤В╨░ ╨┤╨╛╨▒╨░╨▓╨╗╨╡╨╜╨╛ `*.signer_position_genitive_auto` ╨▓ ╤Б╨╡╤А╨▓╨╕╤Б╨░╤Е ╨┐╨╡╤З╨░╤В╨╕ ╨╖╨░╨║╨░╨╖╨░/╨╗╨╕╨┤╨░; ╨▓ legacy ╨┤╨╛╨▒╨░╨▓╨╗╨╡╨╜╤Л ╨░╨╗╨╕╨░╤Б╤Л `dolzhn_podpisant_rod`, `podpisant_perevoz_rod`.

### ╨б╨║╤А╨╕╨┐╤В╤Л ╨┐╤А╨╛╨┤╨░╨╢ (╨│╤А╨░╤Д, Play, ╨┐╨╛╨╗╤П)

- ╨Ф╨╛╨║╤Г╨╝╨╡╨╜╤В╨░╤Ж╨╕╤П: `docs/sales-scripts-editor-guide.md`, ╨┐╨╗╨░╨╜ ╤Д╨░╨╖: `docs/scripts-module-implementation-plan.md`.
- ╨а╨╡╨┤╨░╨║╤В╨╛╤А: `SalesScriptEditorController`, `resources/js/Pages/SalesScripts/Editor/Graph.vue`, ╨║╨░╨╜╨▓╨░╤Б `ScriptGraphCanvas.vue` тАФ ╤В╨╡╨│╨╕ ╤Г╨╖╨╗╨╛╨▓ (`tags`), ╤И╨░╨▒╨╗╨╛╨╜╤Л (`sales_script_node_templates`), ╨┐╨╛╨╗╤П `{code}` (`sales_script_capture_fields`, `capture_field_codes` ╨╜╨░ ╤Г╨╖╨╗╨╡).
- Play: `SalesScriptController`, `Play.vue`, `SalesScriptPlaySessionService::saveFieldValues()`, `SalesScriptPlayPresentationService` + `SalesScriptBodyPlaceholderService` (╤Б╨╡╨│╨╝╨╡╨╜╤В╤Л capture/reference).
- ╨Ь╨░╤А╤И╤А╤Г╤В╤Л ╤А╨╡╨┤╨░╨║╤В╨╛╤А╨░: `scripts.editor.*` тАФ `capture-fields.*`, `node-templates.*`, ╤Б╨╛╤Е╤А╨░╨╜╨╡╨╜╨╕╨╡ ╨│╤А╨░╤Д╨░ ╤Б ╤В╨╡╨│╨░╨╝╨╕ (`SaveGraphRequest`).
- ╨Ю╨▒╨╗╨░╤Б╤В╤М ╨▓╨╕╨┤╨╕╨╝╨╛╤Б╤В╨╕: `sales_assistant_scripts` (╨╗╨╡╨│╨░╤Б╨╕ `scripts` ╨▓ `RoleAccess`).

### ╨г╨┐╤А╨░╨▓╨╗╨╡╨╜╤З╨╡╤Б╨║╨╕╨╣ ╤Г╤З╤С╤В (╨д╨╕╨╜╨░╨╜╤Б╤Л)

- ╨Ф╨╛╨║╤Г╨╝╨╡╨╜╤В╨░╤Ж╨╕╤П: `docs/management-accounting-architecture.md`, ╨┐╨╗╨░╨╜ ╤Д╨░╨╖: `docs/management-accounting-implementation-plan.md`.
- ╨Ф╨╛╤Б╤В╤Г╨┐: `users.can_management_accounting` + admin; `RoleAccess::canAccessManagementAccounting()`. ╨Э╨╡ ╨┐╤Г╤В╨░╤В╤М ╤Б ╨▒╤О╨┤╨╢╨╡╤В╨╕╤А╨╛╨▓╨░╨╜╨╕╨╡╨╝ (`belongs_to_management`).
- ╨Ш╨╝╨┐╨╛╤А╤В: `ManagementAccountingImportService`, ╨┐╨░╤А╤Б╨╡╤А `SberRegistryXlsxParser` (`sber_registry_v1`).
- ╨Ь╨░╤В╤З╨╕╨╜╨│ / ╤А╨░╨╖╨╜╨╡╤Б╨╡╨╜╨╕╨╡: `ManagementAccountingMatchingService` (╨┐╤А╨░╨▓╨╕╨╗╨░ тЖТ ╨╜╨╛╨╝╨╡╤А ╨╖╨░╤П╨▓╨║╨╕ тЖТ ╨║╨╛╨╜╤В╤А╨░╨│╨╡╨╜╤В+╤Б╤Г╨╝╨╝╨░ тЖТ **╨▓╤Е╨╛╨┤╤П╤Й╨╕╨╡ ╤В╨╛╨╗╤М╨║╨╛ ╨┐╨╛ ╤Б╤Г╨╝╨╝╨╡** тЖТ ╨д╨Ю╨в тЖТ ╤Б╤В╨░╤В╤М╨╕; `suggested_candidates[]` ╤Б `amount_due` ╨┐╤А╨╕ ╨╜╨╡╨╛╨┤╨╜╨╛╨╖╨╜╨░╤З╨╜╨╛╤Б╤В╨╕), `ManagementAccountingAllocationService` тЖТ ╨┐╤А╨╕ ╨╛╨┐╨╡╤А╨░╤Ж╨╕╨╛╨╜╨╜╨╛╨╝ ╤В╨╕╨┐╨╡ `PaymentSchedulePaymentLedgerService`; ╨┐╨╡╤А╨╡╤А╨░╨╖╨╜╨╡╤Б╨╡╨╜╨╕╨╡ тАФ `PaymentScheduleSettlementSyncService`.
- UI ╤А╨░╨╖╨╜╨╡╤Б╨╡╨╜╨╕╤П: `Reconcile.vue` тАФ ╨▓╤Е╨╛╨┤╤П╤Й╨╕╨╡ ╨┐╨╛ ╤Г╨╝╨╛╨╗╤З╨░╨╜╨╕╤О ┬л╨Ю╨┐╨╡╤А╨░╤Ж╨╕╨╛╨╜╨╜╤Л╨╣┬╗, ╨░╨▓╤В╨╛╨║╨░╨╜╨┤╨╕╨┤╨░╤В╤Л, ┬л╨║ ╨╛╨┐╨╗╨░╤В╨╡┬╗ ╨▓ ╤Б╨┐╨╕╤Б╨║╨╡.
- ╨д╨Ю╨в ╨┐╨╛╨╗╤Г╨┐╨╡╤А╨╕╨╛╨┤╤Л (5 / 20): `ManagementPayrollHalfCalendar`, `ManagementPayrollHalfService`.
- UI: `Finance/ManagementAccounting/Index.vue` (variance, ╤А╤Г╤З╨╜╤Л╨╡ ╨╛╨┐╨╡╤А╨░╤Ж╨╕╨╕), `Reconcile.vue` (split); `Budgeting/Index.vue` (freeze ╨┐╨╗╨░╨╜╨░); ╨╝╨╡╨╜╤О `finance-management-accounting`.
- ╨Ь╨░╤А╤И╤А╤Г╤В╤Л: `finance.management-accounting.*`, `budgeting.plan-snapshots.store`; ╤Б╤В╨░╤В╤М╨╕: `POST categories`, `POST categories/sync`.
- ╨Я╨╗╨░╨╜ vs ╤Д╨░╨║╤В: `BudgetPlanSnapshotService`, `BudgetVarianceService`, `ManagementAccountingAnalyticsService` (`plan_source`, `variance_rows`); ╤Б╨╝. `docs/management-accounting-budgeting-integration.md`.
- Split: `management_statement_line_splits`, `allocations[]` ╨┐╤А╨╕ ╤А╨░╨╖╨╜╨╡╤Б╨╡╨╜╨╕╨╕.
- ╨б╨┐╤А╨░╨▓╨╛╤З╨╜╨╕╨║ ╤Б╤В╨░╤В╨╡╨╣: `ManagementExpenseCategoryCatalog`, `ManagementExpenseCategorySyncService` (╤Б╨╕╤Б╤В╨╡╨╝╨╜╤Л╨╡ + `budget_opex_*`).
- ╨Я╤А╨░╨▓╨╕╨╗╨░ ╤А╨░╨╖╨╜╨╡╤Б╨╡╨╜╨╕╤П: `management_reconcile_rules`, `ManagementReconcileRuleService` тАФ ╨┐╤А╨╕╨╛╤А╨╕╤В╨╡╤В ╨▓ ╨╝╨░╤В╤З╨╕╨╜╨│╨╡ ╨┤╨╛ ╤Н╨▓╤А╨╕╤Б╤В╨╕╨║.
- MCP (`/mcp/crm`, ╨┤╨╛╨╝╨╡╨╜ `finance`): `ManagementAccountingMcpService`, tools `list_management_statement_*`, `suggest_*`, `allocate_*`, `get_management_accounting_analytics`, `*_management_reconcile_rule*`, `list_management_expense_categories`; gate `McpAccessGate::requireManagementAccounting()`.
- ╨д╨░╨║╤В ╨▓╨║╨╗╨░╨┤╨║╨╕ ┬л╨г╤З╤С╤В┬╗: ╤А╨░╨╖╨╜╨╡╤Б╤С╨╜╨╜╤Л╨╡ `management_statement_lines` **+** `payment_schedule_payment_events` (╨▒╨╡╨╖ ╨┤╤Г╨▒╨╗╨╡╨╣ `mgmt:*`); backfill: `payment-schedules:backfill-payment-events`.

### ╨Ы╨╕╨┤╤Л

- ╨Ю╤В╨║╨░╨╖ (`lost` / ╤Н╤В╨░╨┐ ╨С╨Я): `LeadLinkedTaskService` ╨╛╤В╨╝╨╡╨╜╤П╨╡╤В ╨╛╤В╨║╤А╤Л╤В╤Л╨╡ ╨╖╨░╨┤╨░╤З╨╕; flash `lead_follow_up` ╨▓ `Leads/Wizard.vue`.
- `TaskController::syncLinkedLeadStatus` ╨╜╨╡ ╨┐╨╡╤А╨╡╨╖╨░╨┐╨╕╤Б╤Л╨▓╨░╨╡╤В ╨╖╨░╨║╤А╤Л╤В╤Л╨╡ ╨╗╨╕╨┤╤Л (`LeadStatus::isClosed`).
- ╨в╨╡╤А╨╝╨╕╨╜╨░╨╗╤М╨╜╤Л╨╣ ╤Н╤В╨░╨┐ ╨С╨Я: `LeadBusinessProcessService::progressPayload` тЖТ 100%; playbook ╨▒╨╡╨╖ `auto_create_task` ╨╜╨░ terminal.
- **Playbook ╤Н╤В╨░╨┐╨╛╨▓ ╨С╨Я:** `BusinessProcessPlaybook`, `BusinessProcessDefaultPlaybookLibrary`, `BusinessProcessPlaybookSeederService`; ╨┐╨╛╨╗╤П ╨╜╨░ `business_process_stages` (`coaching_hint`, `sales_script_id`); ╤Б╨╕╨┤╨╡╤А `php artisan business-processes:seed-playbooks`; UI тАФ `Settings/BusinessProcesses/Index.vue` (`CrmMarkdownEditor`).

### ╨Ф╨░╤И╨▒╨╛╤А╨┤ ╨╕ ╨╝╨╡╨╜╤О

- ╨Ф╨░╤И╨▒╨╛╤А╨┤ ╨┐╨╛ ╨┐╨╛╨┤╤А╨░╨╖╨┤╨╡╨╗╨╡╨╜╨╕╤О: `users.sees_company_dashboard`, `UserDashboardDepartmentScope`, `DashboardMetricsService` (scope ╨╛╤В╨┤╨╡╨╗╨░ vs ╨▓╤Б╤П ╨║╨╛╨╝╨┐╨░╨╜╨╕╤П).
- ╨Ш╨╖╨▒╤А╨░╨╜╨╜╨╛╨╡ ╨▓ ╤Б╨░╨╣╨┤╨▒╨░╤А╨╡: `SidebarMenuCatalog`, `SidebarMenuFavoritesResolver`, `ProfileController::updateSidebarFavorites` (`profile.sidebar-favorites`).

### ╨б╤З╨╕╤В╨░╨╗╨║╨░ (╨╝╨░╤А╨╢╨░ ╨▓ ╨┐╨╡╤А╨╡╨│╨╛╨▓╨╛╤А╨░╤Е)

- ╨Ь╨╛╨┤╤Г╨╗╤М **╨Ь╨╛╨┤╤Г╨╗╨╕ тЖТ ╨б╤З╨╕╤В╨░╨╗╨║╨░**: `resources/js/Pages/Modules/Counter.vue`, ╨╝╨░╤А╤И╤А╤Г╤В╤Л `modules.counter.index` / `modules.counter.calculate` (`/modules/counter`). ╨а╨╡╨┤╨╕╤А╨╡╨║╤В ╤Б `/sales-assistant/counter`.
- `SalesMarginCounterService` тАФ ╤Б╤В╨░╨▓╨║╨╕ ╨╖╨░╨║╨░╨╖╤З╨╕╨║/╨┐╨╡╤А╨╡╨▓╨╛╨╖╤З╨╕╨║ + ╨╛╨▒╤П╨╖╨░╤В╨╡╨╗╤М╨╜╨╛╨╡ ╨┐╤А╨░╨▓╨╕╨╗╨╛ ╤Г╨┤╨╡╤А╨╢╨░╨╜╨╕╤П KPI (`kpi_deduction_rule_id` ╨╕╨╖ ╨░╨║╤В╨╕╨▓╨╜╤Л╤Е `kpi_deduction_rules`).
- ╨б╤Ж╨╡╨╜╨░╤А╨╕╨╕: `cash`, `vat_all`, `vat_zero_cash`; ╨║╨░╤В╨╡╨│╨╛╤А╨╕╤П KPI `vat_zero_cash` ╨▓ `KpiPaymentCategoryResolver`.

### ╨а╨░╤Б╤В╨░╨╝╨╛╨╢╨║╨░ (╨║╨░╨╗╤М╨║╤Г╨╗╤П╤В╨╛╤А ╨▓╨▓╨╛╨╖╨░)

- ╨Ф╨╛╨║╤Г╨╝╨╡╨╜╤В╨░╤Ж╨╕╤П: `docs/import-cost-calculator-architecture.md`.
- ╨Ь╨╛╨┤╤Г╨╗╤М **╨Ь╨╛╨┤╤Г╨╗╨╕ тЖТ ╨а╨░╤Б╤В╨░╨╝╨╛╨╢╨║╨░**: `resources/js/Pages/Modules/ImportCostCalculator.vue`, ╨╝╨░╤А╤И╤А╤Г╤В╤Л `modules.import-cost.*` (`/modules/import-cost`). ╨Ю╨▒╨╗╨░╤Б╤В╤М `modules_import_cost`.
- ╨а╨░╤Б╤З╤С╤В: `ImportCostCalculatorService` тАФ ╤В╨░╨╝╨╛╨╢╨╡╨╜╨╜╨░╤П ╤Б╤В╨╛╨╕╨╝╨╛╤Б╤В╤М, ╨┐╨╛╤И╨╗╨╕╨╜╨░, ╨Э╨Ф╨б, ╤В╨░╨╝╨╛╨╢╨╡╨╜╨╜╤Л╨╣ ╤Б╨▒╨╛╤А, ╤Г╤В╨╕╨╗╤М╤Б╨▒╨╛╤А (╨Я╨Я тДЦ 1291: `base_fee_rub ├Ч coefficient` ╨┐╨╛ ╨▓╨╛╨╖╤А╨░╤Б╤В╤Г), ╨┤╨╛╤Б╤В╨░╨▓╨║╨░; ╤Б╤Г╨╝╨╝╤Л ╨┤╨╛ ╤Ж╨╡╨╗╤Л╤Е тВ╜.
- ╨б╨┐╤А╨░╨▓╨╛╤З╨╜╨╕╨║╨╕: `ImportCostTnVedCatalog`, `UtilizationFeeCatalog`, `ImportCostReferenceMeta`; ╨С╨Ф `import_cost_tn_ved_entries`, `import_cost_pp1291_categories`, `import_cost_reference_syncs`.
- ╨б╨╕╨╜╤Е╤А╨╛╨╜╨╕╨╖╨░╤Ж╨╕╤П: `php artisan import-cost:sync-references` (`--eec-only`, `--pp1291-only`); cron ╨┐╨╜ 03:15 (`routes/console.php`); ╤Б╨╡╤А╨▓╨╕╤Б╤Л `EecTnVedSyncService`, `Pp1291ReferenceSyncService`, ╨║╨╗╨╕╨╡╨╜╤В `EecODataClient`.
- ╨Ъ╨╛╨╜╤Д╨╕╨│: `config/import_cost_calculator.php`, `config/import_cost_pp1291.php`. TKS API ╨╜╨╡ ╨╕╤Б╨┐╨╛╨╗╤М╨╖╤Г╨╡╤В╤Б╤П.

### ╨г╤Б╨╗╨╛╨▓╨╕╤П ╨╛╨┐╨╗╨░╤В╤Л ╨╕ ╨│╤А╨░╤Д╨╕╨║ (`payment_schedules`)

- ╨Ф╨╛╨║╤Г╨╝╨╡╨╜╤В╨░╤Ж╨╕╤П: `docs/payment-schedule-architecture.md`.
- ╨Х╨┤╨╕╨╜╤Л╨╣ ╤Д╨╛╤А╨╝╨░╤В JSON: `installments[]` (╨┤╨╛ 10 ╤В╤А╨░╨╜╤И╨╡╨╣): `percent`, `amount`, `offset_days`, `offset_unit`, `anchor`, `basis`. ╨Ы╨╡╨│╨░╤Б╨╕ `has_prepayment` / `postpayment_*` тЖТ `PaymentScheduleLegacyConverter`.
- ╨Я╨╡╤А╨╡╤Б╨▒╨╛╤А╨║╨░ ╤Б╤В╤А╨╛╨║ ╨С╨Ф: `OrderCompensationService::syncPaymentSchedules()`; ╤Б╨╛╤Е╤А╨░╨╜╨╡╨╜╨╕╨╡ ╤Д╨░╨║╤В╨╕╤З╨╡╤Б╨║╨╕╤Е ╨╛╨┐╨╗╨░╤В ╨┐╤А╨╕ ╨┐╨╡╤А╨╡╤Б╨▒╨╛╤А╨║╨╡ тАФ `PaymentScheduleSettlementPreserver` (╨║╨╗╤О╤З `installment_sequence` + fallback ╨╜╨░ `type`).
- ╨а╨░╤Б╤З╤С╤В `planned_date`: ╤Б╨╛╨▒╤Л╤В╨╕╨╡ (`basis`) + ╤Б╨┤╨▓╨╕╨│, ╨╗╨╕╨▒╨╛ ╤П╨║╨╛╤А╤М ╤З╨╡╤А╨╡╨╖ `PaymentInstallmentPlanner`; ╨┤╨░╤В╤Л ╨┐╨╛╨│╤А╤Г╨╖╨║╨╕/╨▓╤Л╨│╤А╤Г╨╖╨║╨╕ тАФ `OrderRouteMilestoneDateResolver` (╤Д╨░╨║╤В ╤В╨╛╤З╨║╨╕ тЖТ ╨┐╨╗╨░╨╜ тЖТ performers тЖТ ╨║╨╛╨╗╨╛╨╜╨║╨░ ╨╖╨░╨║╨░╨╖╨░); ╤Б╨╕╨╜╤Е╤А╨╛╨╜╨╕╨╖╨░╤Ж╨╕╤П ╨┐╤А╨╕ ╤Б╨╛╤Е╤А╨░╨╜╨╡╨╜╨╕╨╕ ╨╝╨░╤Б╤В╨╡╤А╨░ ╨╕ ╤Д╨░╨║╤В╨░ ╨╜╨░ ╤В╨╛╤З╨║╨╡; **╨╜╨░╨╗╨╕╤З╨║╨░** тАФ `PaymentScheduleCashBasis` (╨▒╨░╨╖╨╕╤Б╤Л ╨┤╨╛╨║╤Г╨╝╨╡╨╜╤В╨╛╨▓ тЖТ `unloading`).
- ╨з╨░╤Б╤В╨╕╤З╨╜╤Л╨╡ ╨╛╨┐╨╗╨░╤В╤Л: `PaymentScheduleSettlementStatus`, ╨║╨╛╨╗╨╛╨╜╨║╨░ ┬л╨Ъ ╨╛╨┐╨╗╨░╤В╨╡┬╗ ╨▓ `CashFlowGrid.vue`; ╨┐╨╛╤Б╨╗╨╡ ╨┤╨╡╨┐╨╗╨╛╤П ╨┐╤А╨░╨▓╨╛╨║ тАФ `payment-schedules:sync-settlement-amounts`.
- FTTN ╨┐╨╛ ╤Б╨║╨░╨╜╨░╨╝ тАФ ╨░╨▓╤В╨╛ (`OrderDocumentRequirementService::paymentPackageAttachedAt`); ╨┐╤А╨╕ **╨╜╨░╨╗╨╕╤З╨║╨╡** ╨▒╨░╨╖╨╕╤Б `fttn` тЖТ ╤Б╤А╨╛╨║ ╨╛╤В ╨▓╤Л╨│╤А╤Г╨╖╨║╨╕ (`PaymentScheduleCashBasis`).
- ╨Ъ╨▓╨╕╤В╨╛╨║ / OTTN тАФ ╨▓╤А╤Г╤З╨╜╤Г╤О: `track_received_date_*` (╨▓ ╤В.╤З. **╨╜╨░╨╗╨╕╤З╨║╨░ + ottn** / `fttn_receipt`); ╤Б╤В╨╛╤А╨╛╨╜╤Л ╤А╨░╨╖╨┤╨╡╨╗╤М╨╜╨╛.
- UI: `PaymentTermsWizardBlock.vue`, `orderPaymentScheduleUi.js` (`applyInstallmentScheduleInPlace` тАФ ╨▒╨╡╨╖ deep-watch ╤Ж╨╕╨║╨╗╨╛╨▓); ╨│╤А╨╕╨┤ тАФ `CashFlowGrid.vue`, ╨┤╨░╤В╤Л **╨┤╨┤.╨╝╨╝.╨│╨│╨│╨│**.
- ╨Ь╨╕╨│╤А╨░╤Ж╨╕╤П: `2026_06_08_155321_add_installment_sequence_to_payment_schedules_table.php`.

### ╨Ф╨╛╨║╤Г╨╝╨╡╨╜╤В╤Л ╨╕ ╤З╨╡╨║-╨╗╨╕╤Б╤В ╨╖╨░╨║╨░╨╖╨░

- ╨а╨╡╨╡╤Б╤В╤А + ╨▓╨║╨╗╨░╨┤╨║╨░ ┬л╨Ф╨╛╨║╤Г╨╝╨╡╨╜╤В╤Л┬╗: `DocumentRegistryController`, `OrderWizardDocumentsTab.vue`, `DocumentsGrid.vue`.
- ╨Ф╨░╤В╨░ ╨┐╨╛╨╗╤Г╤З╨╡╨╜╨╕╤П ╨╛╤А╨╕╨│╨╕╨╜╨░╨╗╨╛╨▓: `track_received_date_customer/carrier` тАФ clerk ╨▓ ╤А╨╡╨╡╤Б╤В╤А╨╡ (`PATCH documents/orders/{id}/track-received`) ╨╕ ╨▓ ╤В╨░╨▒╨╗╨╕╤Ж╨╡ ╤Г╤З╤С╤В╨░ (`OrderSignedDocumentsTable.vue`); ╨╛╨┤╨╜╨░ ╨┤╨░╤В╨░ ╨╜╨░ ╤Б╤В╨╛╤А╨╛╨╜╤Г, ╤Б╤В╤А╨╛╨║╨╕ ╨╖╨░╤П╨▓╨║╨╕ + ╨╖╨░╨║╤А╤Л╨▓╨░╤О╤Й╨╕╤Е тАФ `orderTrackingDates.js`.
- ╨б╨╗╨╛╤В╤Л ╨╛╨▒╤П╨╖╨░╤В╨╡╨╗╤М╨╜╤Л╤Е ╨┤╨╛╨║╤Г╨╝╨╡╨╜╤В╨╛╨▓: `OrderDocumentRequirementSlotBuilder`, ╨╖╨╡╤А╨║╨░╨╗╨╛ ╨╜╨░ ╤Д╤А╨╛╨╜╤В╨╡ `orderDocumentRequirementSlots.js`.
- ╨в╤А╨░╨╜╤Б╨┐╨╛╤А╤В╨╜╤Л╨╡ ╤В╨╕╨┐╤Л (╨в╨Э / ╨н╨в╤А╨Э / CMR / ╨в╨б╨Ф) тАФ ╨╛╨┤╨╜╨░ ╨│╤А╤Г╨┐╨┐╨░: `OrderDocumentTransportTypes`, ╤Б╨╗╨╛╤В `waybill` ╤Б `accepted_types` waybill|etrn|cmr.
- **╨Э╨░╨╗╨╕╤З╨╜╤Л╨╡ (`cash`):** ╨╖╨░╨║╤А╤Л╨▓╨░╤О╤Й╨╕╨╡ ╤Б╨╗╨╛╤В╤Л (╨г╨Я╨Ф / ╨б╨д / ╨░╨║╤В) **╨╜╨╡ ╤Б╨╛╨╖╨┤╨░╤О╤В╤Б╤П** тАФ ╤В╨╛╨╗╤М╨║╨╛ ╨╖╨░╤П╨▓╨║╨░ ╨┐╨╛ ╨║╨╛╨╜╤В╤А╨░╨│╨╡╨╜╤В╤Г + ╨╛╨▒╤Й╨╕╨╣ ╤Б╨╗╨╛╤В ╨в╨б╨Ф. ╨д╨╛╤А╨╝╨░ ╨╛╨┐╨╗╨░╤В╤Л: ╨╖╨░╨║╨░╨╖╤З╨╕╨║ тАФ `customer_payment_form`; ╨┐╨╡╤А╨╡╨▓╨╛╨╖╤З╨╕╨║ тАФ `contractors_costs` / `leg_costs`; ╨┐╨╛╨┤╤А╤П╨┤╤З╨╕╨║ тАФ `additional_costs.payment_form`.
- ╨Ч╨░╨║╤А╤Л╤В╨╕╨╡ ╤Б╨┤╨╡╨╗╨║╨╕: `OrderStatusService` тЖТ `checklistForOrder()` тАФ ╨▓╤Б╨╡ ╨┐╤Г╨╜╨║╤В╤Л ╤З╨╡╨║-╨╗╨╕╤Б╤В╨░ ╨┤╨╛╨╗╨╢╨╜╤Л ╨▒╤Л╤В╤М `completed`.
- ╨Ф╨╛╨║╤Г╨╝╨╡╨╜╤В╨░╤Ж╨╕╤П: `docs/documents-user-guide.md`, `docs/documents-regulation.md`; ╨║╨░╤А╤В╨╛╤З╨║╨░ `docs/sync/v5-local-Components-Documents-Registry.md`; ╨┐╤Г╨▒╨╗╨╕╨║╨░╤Ж╨╕╤П ╨▓ ╨Ъ╨╜╨╕╨│╤Г: `php scripts/mcp-prod-upsert-documents.php`.

### HTML-╤И╨░╨▒╨╗╨╛╨╜╤Л ╨Ъ╨Я (GrapesJS)

- ╨Ь╨╛╨┤╤Г╨╗╤М **╨Ь╨╛╨┤╤Г╨╗╨╕ тЖТ ╨и╨░╨▒╨╗╨╛╨╜╤Л ╨Ъ╨Я**: `resources/js/Pages/Modules/ProposalTemplates/*`, ╤А╨╡╨┤╨░╨║╤В╨╛╤А `Components/ProposalTemplates/ProposalGrapesEditor.vue` (GrapesJS + `grapesjs-preset-newsletter`, MIT).
- ╨Ь╨░╤А╤И╤А╤Г╤В╤Л: `modules.proposal-templates.*` (`/modules/proposal-templates`); ╨╛╨▒╨╗╨░╤Б╤В╤М `modules_proposal_templates`; CRUD тАФ `canAccessSettingsSystem`.
- ╨Я╨╗╨╡╨╣╤Б╤Е╨╛╨╗╨┤╨╡╤А╤Л ╨▓ HTML: `{lead.number}`, `{counterparty.name}` ╨╕ ╤В.╨┤. тАФ ╨║╨░╤В╨░╨╗╨╛╨│ `ProposalHtmlTemplateVariableCatalog` (╨╕╨╖ `PrintFormVariableCatalog::leadOptions()`); ╨┐╨░╨╜╨╡╨╗╤М ╨┐╨╡╤А╨╡╨╝╨╡╨╜╨╜╤Л╤Е **╤Б╨╗╨╡╨▓╨░** ╨╛╤В ╤Е╨╛╨╗╤Б╤В╨░.
- ╨а╨╡╨╜╨┤╨╡╤А / PDF: `LeadProposalHtmlRenderer`, `LeadProposalPdfService` (Gotenberg); ╨╜╨░ ╨╗╨╕╨┤╨╡ тАФ `LeadWizardCommercialTab.vue`.
- ╨Ф╨╡╨╝╨╛-╤И╨░╨▒╨╗╨╛╨╜ Unisender: `ProposalHtmlTemplateParallelImportDemo`, seeder `ProposalHtmlTemplateDemoSeeder` (slug `parallel-import-demo`).
- ╨в╨Ч: `docs/tz-step-04-html-proposal-builder.md`; ╨║╨░╤А╤В╨╛╤З╨║╨░ `docs/sync/v5-local-Components-Commercial-Roadmap.md`.

### ╨б╨╛╨▒╤Б╤В╨▓╨╡╨╜╨╜╤Л╨╣ ╨┐╨░╤А╨║ ╨╕ ╤А╨╡╨╣╤Б╤Л

- ╨Т╨╕╤А╤В╤Г╨░╨╗╤М╨╜╤Л╨╣ ╨┐╨╡╤А╨╡╨▓╨╛╨╖╤З╨╕╨║ ┬л╨б╨╛╨▒╤Б╤В╨▓╨╡╨╜╨╜╤Л╨╣ ╨┐╨░╤А╨║┬╗ (`OwnFleetCatalog`, `OwnFleetContractorService`) тАФ **╨╜╨╡** own company ╨▓ ╨╖╨░╨║╨░╨╖╨╡ (`is_own_company=false`, ╨╕╤Б╨║╨╗╤О╤З╤С╨╜ ╨╕╨╖ `Contractor::ownCompanyProfiles()`).
- ╨а╨╡╨╣╤Б╤Л (`fleet_trips`): ╤Б╨╛╨╖╨┤╨░╤О╤В╤Б╤П ╨┐╤А╨╕ ╤Б╨╛╤Е╤А╨░╨╜╨╡╨╜╨╕╨╕ ╨╖╨░╨║╨░╨╖╨░ ╤В╨╛╨╗╤М╨║╨╛ ╨╡╤Б╨╗╨╕ `performers[].execution_mode === own_fleet` (`FleetTripService::syncPlannedTripsFromOrder`); ╤Б╨╝╨╡╨╜╨░ ╨╜╨░ ╨▓╨╜╨╡╤И╨╜╨╡╨│╨╛ ╨┐╨╡╤А╨╡╨▓╨╛╨╖╤З╨╕╨║╨░ ╤А╨╡╨╣╤Б **╨╜╨╡ ╤Г╨┤╨░╨╗╤П╨╡╤В**.
- ╨Ь╨░╤Б╤В╨╡╤А: ╨▓╨╡╤А╤Е╨╜╤П╤П ╨║╨╜╨╛╨┐╨║╨░ ┬л╨б╨╛╨▒╤Б╤В╨▓╨╡╨╜╨╜╤Л╨╣ ╨┐╨░╤А╨║┬╗ ╨▓ ╨┐╨╛╨╕╤Б╨║╨╡ ╨┐╨╡╤А╨╡╨▓╨╛╨╖╤З╨╕╨║╨░ (`Wizard.vue` тЖТ `selectOwnFleetPerformer`); ╨┤╤Г╨▒╨╗╤М ╨╕╨╖ ╤Б╨┐╨╕╤Б╨║╨░ ╨║╨╛╨╜╤В╤А╨░╨│╨╡╨╜╤В╨╛╨▓ ╤Б╨║╤А╤Л╤В ╤Б `078b41d`.
- ╨Ъ╨░╤А╤В╨╛╤З╨║╨░ / runbook: `docs/sync/v5-local-Components-Fleet-Own-Fleet.md`.
- PHPUnit: `.env.testing` тЖТ `DB_DATABASE=u_tromb_test` (╨╜╨╡ ╤А╨░╨▒╨╛╤З╨░╤П `u_tromb`).

### ╨Я╤А╨╛╤З╨╡╨╡

- **SSH ╨╜╨░ ╨┐╤А╨╛╨┤:** `docs/sync/prod-ssh.md` тАФ IP `91.229.11.16`, ╨║╨╗╤О╤З PuTTY `C:\,ssh\private_key.ppk`, ╤Б╨║╤А╨╕╨┐╤В `scripts/prod-plink.ps1` (╨╜╨╡ ╨┐╤Г╤В╨░╤В╤М ╤Б GitHub-╨║╨╗╤О╤З╨╛╨╝ ╨▓ `~/.ssh`).
- ╨Ь╨╛╨▒╨╕╨╗╤М╨╜╨░╤П ╨╜╨╕╨╢╨╜╤П╤П ╨┐╨░╨╜╨╡╨╗╤М: `app/Support/MobileNavCatalog.php` тАФ ╨║╨░╨╜╨┤╨╕╨┤╨░╤В╤Л ╨║╨╜╨╛╨┐╨╛╨║ ╤Б ╤Г╤З╤С╤В╨╛╨╝ `visibility_areas` (╨┤╨░╤И╨▒╨╛╤А╨┤ ╨╜╨╡ ╨╜╨░╨▓╤П╨╖╤Л╨▓╨░╨╡╤В╤Б╤П, ╨╡╤Б╨╗╨╕ ╨╛╨▒╨╗╨░╤Б╤В╨╕ ╨╜╨╡╤В); ╨╕╤В╨╛╨│╨╛╨▓╤Л╨╣ ╨┐╤А╨╛╨┐ ╨┤╨╗╤П ╤Д╤А╨╛╨╜╤В╨░ ╤Б╨╛╨▒╨╕╤А╨░╨╡╤В `MobileNavResolver` (`HandleInertiaRequests` тЖТ `auth.user.mobile_nav`). ╨б╨╛╤Е╤А╨░╨╜╨╡╨╜╨╕╨╡ ╨▓╤Л╨▒╨╛╤А╨░ ╨┐╨╛╨╗╤М╨╖╨╛╨▓╨░╤В╨╡╨╗╤П: `ProfileController::updateMobileBottomNav`, ╨╝╨░╤А╤И╤А╤Г╤В `profile.mobile-bottom-nav` (`routes/web.php`).
- PWA: `public/sw.js` тАФ ╨║╤Н╤И shell ╨┤╨╗╤П `/`, ╨╜╨░╨▓╨╕╨│╨░╤Ж╨╕╨╕ ╨╜╨░ ╨┤╤А╤Г╨│╨╕╨╡ ╨┐╤Г╤В╨╕ ╨╕╨┤╤Г╤В ╤З╨╡╤А╨╡╨╖ ╤Б╨╡╤В╤М.
- ╨б╨╕╨╜╤Е╤А╨╛╨╜ ╨╕╨╜╨┤╨╡╨║╤Б╨╛╨▓ Obsidian тЖФ git: `docs/sync/`, `scripts/sync-docs-to-yandex.ps1`; MCP bearer ╤Б ╨п.╨Ф╨╕╤Б╨║╨░: `scripts/sync-cursor-mcp-from-yandex.ps1`.

## Frontend Bundling

- If the user doesn't see a frontend change reflected in the UI, it could mean they need to run `npm run build`, `npm run dev`, or `composer run dev`. Ask them.

## Documentation Files

- You must only create documentation files if explicitly requested by the user.

## Replies

- Be concise in your explanations - focus on what's important rather than explaining obvious details.

=== boost rules ===

# Laravel Boost

## Tools

- Laravel Boost is an MCP server with tools designed specifically for this application. Prefer Boost tools over manual alternatives like shell commands or file reads.
- Use `database-query` to run read-only queries against the database instead of writing raw SQL in tinker.
- Use `database-schema` to inspect table structure before writing migrations or models.
- Use `get-absolute-url` to resolve the correct scheme, domain, and port for project URLs. Always use this before sharing a URL with the user.
- Use `browser-logs` to read browser logs, errors, and exceptions. Only recent logs are useful, ignore old entries.

## Searching Documentation (IMPORTANT)

- Always use `search-docs` before making code changes. Do not skip this step. It returns version-specific docs based on installed packages automatically.
- Pass a `packages` array to scope results when you know which packages are relevant.
- Use multiple broad, topic-based queries: `['rate limiting', 'routing rate limiting', 'routing']`. Expect the most relevant results first.
- Do not add package names to queries because package info is already shared. Use `test resource table`, not `filament 4 test resource table`.

### Search Syntax

1. Use words for auto-stemmed AND logic: `rate limit` matches both "rate" AND "limit".
2. Use `"quoted phrases"` for exact position matching: `"infinite scroll"` requires adjacent words in order.
3. Combine words and phrases for mixed queries: `middleware "rate limit"`.
4. Use multiple queries for OR logic: `queries=["authentication", "middleware"]`.

## Artisan

- Run Artisan commands directly via the command line (e.g., `php artisan route:list`). Use `php artisan list` to discover available commands and `php artisan [command] --help` to check parameters.
- Inspect routes with `php artisan route:list`. Filter with: `--method=GET`, `--name=users`, `--path=api`, `--except-vendor`, `--only-vendor`.
- Read configuration values using dot notation: `php artisan config:show app.name`, `php artisan config:show database.default`. Or read config files directly from the `config/` directory.
- To check environment variables, read the `.env` file directly.

## Tinker

- Execute PHP in app context for debugging and testing code. Do not create models without user approval, prefer tests with factories instead. Prefer existing Artisan commands over custom tinker code.
- Always use single quotes to prevent shell expansion: `php artisan tinker --execute 'Your::code();'`
  - Double quotes for PHP strings inside: `php artisan tinker --execute 'User::where("active", true)->count();'`

=== php rules ===

# PHP

- Always use curly braces for control structures, even for single-line bodies.
- Use PHP 8 constructor property promotion: `public function __construct(public GitHub $github) { }`. Do not leave empty zero-parameter `__construct()` methods unless the constructor is private.
- Use explicit return type declarations and type hints for all method parameters: `function isAccessible(User $user, ?string $path = null): bool`
- Use TitleCase for Enum keys: `FavoritePerson`, `BestLake`, `Monthly`.
- Prefer PHPDoc blocks over inline comments. Only add inline comments for exceptionally complex logic.
- Use array shape type definitions in PHPDoc blocks.

=== tests rules ===

# Test Enforcement

- Every change must be programmatically tested. Write a new test or update an existing test, then run the affected tests to make sure they pass.
- Run the minimum number of tests needed to ensure code quality and speed. Use `php artisan test --compact` with a specific filename or filter.

=== inertia-laravel/core rules ===

# Inertia

- Inertia creates fully client-side rendered SPAs without modern SPA complexity, leveraging existing server-side patterns.
- Components live in `resources/js/Pages` (unless specified in `vite.config.js`). Use `Inertia::render()` for server-side routing instead of Blade views.
- ALWAYS use `search-docs` tool for version-specific Inertia documentation and updated code examples.
- IMPORTANT: Activate `inertia-vue-development` when working with Inertia Vue client-side patterns.

# Inertia v2

- Use all Inertia features from v1 and v2. Check the documentation before making changes to ensure the correct approach.
- New features: deferred props, infinite scroll, merging props, polling, prefetching, once props, flash data.
- When using deferred props, add an empty state with a pulsing or animated skeleton.

=== laravel/core rules ===

# Do Things the Laravel Way

- Use `php artisan make:` commands to create new files (i.e. migrations, controllers, models, etc.). You can list available Artisan commands using `php artisan list` and check their parameters with `php artisan [command] --help`.
- If you're creating a generic PHP class, use `php artisan make:class`.
- Pass `--no-interaction` to all Artisan commands to ensure they work without user input. You should also pass the correct `--options` to ensure correct behavior.

### Model Creation

- When creating new models, create useful factories and seeders for them too. Ask the user if they need any other things, using `php artisan make:model --help` to check the available options.

## APIs & Eloquent Resources

- For APIs, default to using Eloquent API Resources and API versioning unless existing API routes do not, then you should follow existing application convention.

## URL Generation

- When generating links to other pages, prefer named routes and the `route()` function.

## Testing

- When creating models for tests, use the factories for the models. Check if the factory has custom states that can be used before manually setting up the model.
- Faker: Use methods such as `$this->faker->word()` or `fake()->randomDigit()`. Follow existing conventions whether to use `$this->faker` or `fake()`.
- When creating tests, make use of `php artisan make:test [options] {name}` to create a feature test, and pass `--unit` to create a unit test. Most tests should be feature tests.

## Vite Error

- If you receive an "Illuminate\Foundation\ViteException: Unable to locate file in Vite manifest" error, you can run `npm run build` or ask the user to run `npm run dev` or `composer run dev`.

## Deployment

- Laravel can be deployed using [Laravel Cloud](https://cloud.laravel.com/), which is the fastest way to deploy and scale production Laravel applications.

=== pint/core rules ===

# Laravel Pint Code Formatter

- If you have modified any PHP files, you must run `vendor/bin/pint --dirty --format agent` before finalizing changes to ensure your code matches the project's expected style.
- Do not run `vendor/bin/pint --test --format agent`, simply run `vendor/bin/pint --format agent` to fix any formatting issues.

=== phpunit/core rules ===

# PHPUnit

- This application uses PHPUnit for testing. All tests must be written as PHPUnit classes. Use `php artisan make:test --phpunit {name}` to create a new test.
- If you see a test using "Pest", convert it to PHPUnit.
- Every time a test has been updated, run that singular test.
- When the tests relating to your feature are passing, ask the user if they would like to also run the entire test suite to make sure everything is still passing.
- Tests should cover all happy paths, failure paths, and edge cases.
- You must not remove any tests or test files from the tests directory without approval. These are not temporary or helper files; these are core to the application.

## Running Tests

- Run the minimal number of tests, using an appropriate filter, before finalizing.
- To run all tests: `php artisan test --compact`.
- To run all tests in a file: `php artisan test --compact tests/Feature/ExampleTest.php`.
- To filter on a particular test name: `php artisan test --compact --filter=testName` (recommended after making a change to a related file).

=== inertia-vue/core rules ===

# Inertia + Vue

Vue components must have a single root element.
- IMPORTANT: Activate `inertia-vue-development` when working with Inertia Vue client-side patterns.

</laravel-boost-guidelines>
