# Stack upgrade gate (branch upgrade/laravel-13-php-8.5)

Run on 2026-10-22 against the staged working tree. Nothing is committed or pushed.

**Overall: automated gate green. Manual smoke partially verified: OpenAI credits are exhausted on both club projects, and local PDF export lacks puppeteer under Node 22. See "Smoke" below.**

## Results

| # | Check | Command / method | Result |
|---|-------|------------------|--------|
| 1 | Stage workflow | `.github/workflows/deploy_ai_assistant_stage.yml`: `php83` to `php85` (4x), `yarn`/`yarn build` to `npm ci`/`npm run build`, `view:clear` before `optimize` | Done |
| 2a | Composer | `composer validate` | `./composer.json is valid` |
| 2b | Versions | `php artisan about` | Laravel 13.38.0, PHP 8.5.12 |
| 2c | Tests | `php artisan test` | 316 passed (1150 assertions), 13.55s. Baseline was 313, so N+3 |
| 2d | Pint | `vendor/bin/pint --test` | `{"tool":"pint","result":"passed"}` |
| 2e | Rector | `vendor/bin/rector --dry-run` | `[OK] Rector is done!`, no changes. Warnings only: two stale entries in `withSkip()` (see notes) |
| 3a | npm ci | `npm ci` | OK. `npm audit`: 9 vulnerabilities, all in dev tooling (see notes) |
| 3b | Lint | `npm run lint` (oxlint + eslint) | exit 0, no findings |
| 3c | Typecheck | `npm run typecheck` (vue-tsc) | exit 0 |
| 3d | Build | `npm run build` | exit 0, built in 1.04s. Chunk-size warning >500 kB (pre-existing) |
| 3e | SSR build | `npm run build:ssr` | exit 0, built in 453ms |
| 4 | Migrations as in stage | `migrate:fresh --seed --force` on a throwaway sqlite (`storage/upgrade-gate.sqlite`, deleted after) | exit 0, includes `2026_10_22_000000_recreate_agent_conversations_for_laravel_ai_1 ... DONE`, seeders DONE |
| 5a | Smoke: signed external link login | Herd `https://ai-assistant.test`, HMAC link (club/user_id/user_name/role/issued_at/sig) | PASS (ccm and vallealto users) |
| 5b | Smoke: chat message, stream, title, suggestions | One message per club | NOT VERIFIED. Request reaches OpenAI via laravel/ai 1.3 with `gpt-6-luna`, but both OpenAI projects answer 429 "You have no credits remaining". UI shows the graceful error reply |
| 5c | Smoke: code block highlighting | | NOT VERIFIED (needs a model answer, same cause as 5b) |
| 5d | Smoke: upload a document to a source | `/fuentes`, "Subir documento", tiny `.md`, group `golf` | PASS: row created as Manual / Pendiente (indexing is queued; queue worker not run). Test document deleted afterwards via the UI |
| 5e | Smoke: message PDF download | "Más acciones" > "Exportar PDF" | FAIL locally, environmental: HTTP 500, browsershot `Cannot find module 'puppeteer'` (only installed globally under Node 18; Herd site uses Node 22). The same renderer run from tinker with Node 18 + installed Chrome produced a correct 2-page PDF, so the upgraded PDF stack (laravel-pdf 2.14, browsershot 5.4.1) works |
| 6 | CSRF from cross-site iframe | Host page at `http://ccm-admin-api.test/` embedding the app (see below) | PASS: chat-create POST redirected to the new chat (GET 200, chat row created); `POST /chat/stream/{id}` 200; `POST /chat-suggestions/{id}` 200. No 403/419 |

## Final versions

- `php artisan about`: Laravel 13.38.0, PHP 8.5.12, Composer 2.9.7
- Locked: laravel/ai v1.3.0, inertiajs/inertia-laravel v3.5.2, pestphp/pest v5.3.3, spatie/laravel-pdf 2.14.0, spatie/browsershot 5.4.1
- `npm ls vite typescript @inertiajs/vue3 --depth=0`: vite@8.3.5, typescript@6.0.3, @inertiajs/vue3@3.9.1 (vue@3.5.44)
- Tooling used: Node v22.22.2, npm 10.9.7

## How the cross-site CSRF check was set up (repeat in stage from CCM and Vallealto)

Locally, the host origin must be one listed in `FRAME_ANCESTORS` (the local `.env` lists `http(s)://ccm-admin-api.test` and `http(s)://vallealto-admin-api.test`). Those names are already Herd links to the real admin projects, so the check did not touch Herd:

1. A static `index.html` with a full-viewport `<iframe src="https://ai-assistant.test/?club=...&user_id=...&user_name=...&role=...&issued_at=...&sig=...">`, where the query is a freshly signed external link (valid 300 s).
2. Served with `python3 -m http.server 8899 --bind 127.0.0.1`.
3. Chrome launched with `--host-resolver-rules=MAP ccm-admin-api.test:80 127.0.0.1:8899`, then opened `http://ccm-admin-api.test/`. The page origin is `http://ccm-admin-api.test`, which is cross-site to `https://ai-assistant.test`.
4. Inside the iframe: login via the signed link, then "Nuevo chat" and send a message. Watch the network panel for the POSTs.

In stage the real test is simpler: open the assistant from the CCM admin panel and from the Vallealto admin panel (both embed it in an iframe), send a message, and confirm in the browser network panel that `POST /chat`, `POST /chat/stream/{id}` and `POST /chat-suggestions/{id}` do not return 403 or 419. Cookies must stay `SameSite=None; Secure` over HTTPS.

## Server requirements pending (Franky)

- `php85` SCL available on stage and production (the workflow now calls `scl enable php85`).
- Node >= 22.12 and npm on the self-hosted runner (the workflow now runs `npm ci` / `npm run build`; yarn is no longer used).
- `INERTIA_SSR_ENABLED=false` in the stage and production `.env` files.
- Production loses Telegram exception alerts: the Telegram logging package was removed by decision. The deploy-status Telegram step in the workflow is unaffected.
- PDF export needs puppeteer resolvable by the `node` that PHP-FPM runs (`npm root -g`) and a Chrome binary (`BROWSERSHOT_CHROME_PATH` or puppeteer's cache). Check stage after switching runtimes.

## Behavior notes for the owner

- Production loses Telegram exception alerts (package removed by decision).
- `mb_trim` now treats NBSP and ideographic spaces as whitespace, so three trimmed inputs that contain only those characters now become empty strings.
- Sessions are now JSON-serialized. All active sessions are invalidated on deploy, so users must re-enter through the signed link.
- `unplugin-vue-components` finds no components. This is a pre-existing no-op.
- When OpenAI fails mid-stream, laravel/ai 1.3 throws `StreamErrorException` after output has started and the log shows a second error, "Cannot modify header information - headers already sent". The user still sees the graceful error reply. Worth a follow-up so the stream controller catches the exception inside the stream.

## Other observations

- `npm audit` (after `npm ci`): 9 findings (3 critical, 2 moderate, 4 low), all in dev tooling: `eslint-plugin-format` > `oxfmt` > `tinypool` (critical), `@tailwindcss/typography` > `postcss-selector-parser` (moderate), `@antfu/eslint-config` > `@eslint/markdown` > `katex` (low).
- Rector warns that `EncapsedStringsToSprintfRector` (deprecated) and `AddOverrideAttributeToOverriddenMethodsRector` (not registered) can be removed from `withSkip()`.
- The local `.env` sets the app locale to `en`, so the PDF date reads "16 de September de 2026". `.env.example` uses `es`; stage depends on its own `.env`.
