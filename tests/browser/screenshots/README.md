# README screenshots

`readme.shot.ts` writes the PNGs in `docs/images/` that the module README shows. It is not a test
suite: it runs with its own config, `playwright.screenshots.config.ts`, which the browser tests and
CI never load, so a normal test run never rewrites the images. Never edit the PNGs by hand;
regenerate them.

The content is neutral demo data, not the browser-test fixtures: the PHP classes in `fixtures/`
here add an "Events" ModelAdmin (`/admin/events`) with one tab per copy-button setup, re-seeded on
every database build:

- Events: menu mode, `CopyButton::create()`
- Ticket types: column mode, `CopyButton::create(true)`
- Newsletters: menu mode with `setOpenAfterCopy(true)`

What it needs:

- a Silverstripe 6 host with this module installed as a symlinked path repository (the browser-test
  runner's host works, or any scratch project), with the PHP files from `fixtures/` here copied into
  its `app/src/`, then `vendor/bin/sake db:build --flush`. Build the database again before every
  run: the script makes copies, and the column-mode shot expects exactly one;
- the host served (PHP's built-in server with a router that passes static files through, such as
  `../router.php`), with a default admin `admin` / `admin`, which `specs/login.setup.ts` logs in as;
- `BROWSER_TARGET_URLS` pointing at that host (only the `ss6` target is used, else the first).

Then, from `tests/browser/`:

```bash
npm ci
BROWSER_TARGET_URLS="ss6=http://127.0.0.1:8958" npx playwright test --config=playwright.screenshots.config.ts
```

The shared runner's helpers in the maintainers' workspace (`tools/browser/lib.sh`: `host_ensure`,
`host_link_module`, `host_sync_fixtures` with this `fixtures/` directory, `host_build_db`,
`host_start`) build and serve such a host.
