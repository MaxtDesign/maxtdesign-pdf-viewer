# MaxtDesign PDF Viewer — wp.org Release Skill

Project-specific wp.org release context for the procedure defined in `hq/.claude/agents/development/wporg-release.md`. Copy this file into a new plugin at `<plugin>/.claude/skills/wporg-release.md` and fill in the placeholders.

## Plugin Identity

| Field | Value |
|---|---|
| `wporg_slug` | `maxtdesign-pdf-viewer` |
| wp.org page | https://wordpress.org/plugins/maxtdesign-pdf-viewer/ |
| SVN repo | https://plugins.svn.wordpress.org/maxtdesign-pdf-viewer/ |
| Git repo | https://github.com/MaxtDesign/maxtdesign-pdf-viewer |
| `git_dir` | `~/maxtventures/plugins/maxtdesign-pdf-viewer/` |
| Main plugin file | `maxtdesign-pdf-viewer.php` |
| Text domain | `maxtdesign-pdf-viewer` |

**Note on `git_dir`:** Most plugins live at `~/maxtventures/plugins/<slug>/`. If this plugin lives elsewhere (e.g. inside another project's tree), set the path above and pass `--git-dir` to the release script. Example for mantlewp-connector: `git_dir = ~/maxtventures/web/mantlewp/mantlewp-connector/`.

## wp.org Distribution Flag
- [x] **Eligible for wp.org SVN procedure** — this plugin is distributed through wordpress.org and follows the standard release flow.
- [ ] Private / client-only — do NOT run the wporg-release procedure against this plugin.

## Version Source(s) of Truth
Every release requires these to match exactly:

| Location | File | Line/Pattern |
|---|---|---|
| Plugin header | `maxtdesign-pdf-viewer.php` | line 14: `Version: 1.0.0` |
| Readme stable tag | `readme.txt` | line 8: `Stable tag: 1.0.0` |
| Constant (if used) | `maxtdesign-pdf-viewer.php` | line 55: `define( 'MDPV_VERSION', '1.0.0' );` |
| Git tag | repo | `vX.Y.Z` on main |
| SVN tag | `/tags/X.Y.Z/` | created by procedure |

## Readme.txt Requirements
- `Tested up to:` current WP major (update every release)
- `Requires at least:` minimum supported WP — document here: `6.4`
- `Requires PHP:` minimum PHP — document here: `8.1`
- `== Changelog ==` has an entry for the new version before release
- Screenshots numbered to match files in `.wporg-assets/`

## Release Assets (wp.org `/assets/` directory)

Source files live in `.wporg-assets/` in the Git repo. Published separately from code via the asset-only procedure.

| Asset | File | Status |
|---|---|---|
| Banner hi-DPI | `banner-1544x500.png` | [current/needs update] |
| Banner standard | `banner-772x250.png` | [current/needs update] |
| Icon hi-DPI | `icon-256x256.png` | [current/needs update] |
| Icon standard | `icon-128x128.png` | [current/needs update] |
| Screenshots | `screenshot-N.png` | [count and status] |

## Distignore Specifics
Baseline comes from `hq/.claude/standards/wporg-svn-setup.md`. Additional per-plugin notes:

This plugin **ships `vendor/pdfjs/` at runtime** (pdf.js). The repo-root `.distignore` does **not** exclude `vendor/` (unlike the stock template). Do not add `vendor/` here without verifying the plugin still loads PDF.js from that path.

```
# (see .distignore — vendor/ is intentionally kept in the SVN trunk)
```

## Release History

| Version | Date | Git SHA | Notes |
|---|---|---|---|
| _(add on each release)_ | | | |

## Plugin-Specific Notes / Gotchas

- Seeded 2026-04-16 via kickoff-prompts/wporg-release-rollout.md
- `npm`/Node tooling exists for maintaining bundled pdf.js; `package.json` is distignored — run any required build/copy scripts before release and commit the runtime tree under `vendor/pdfjs/`.

## Local Testing (LocalWP)

This plugin should be junction-linked to the LocalWP `plugin-test` site so edits are live-testable in WordPress without copy/deploy.

**Junction status:** `linked to default path`

Create/refresh junction:
```
# Standard (plugin at ~/maxtventures/plugins/<slug>/)
powershell -File hq/.claude/scripts/symlink-plugin-to-localwp.ps1 -Slug maxtdesign-pdf-viewer

# Custom git_dir (override source)
powershell -File hq/.claude/scripts/symlink-plugin-to-localwp.ps1 `
  -Slug maxtdesign-pdf-viewer `
  -SourceDir "[full-path-to-git-repo]"
```

Protocol: agents working on this plugin run `-ListAll` first to confirm the junction exists. If not, create it. When a phase of work completes, activate the plugin in WP admin and smoke test before marking done.

## Quick Reference

Run release (standard plugin):
```
bash hq/.claude/scripts/wporg-release.sh maxtdesign-pdf-viewer X.Y.Z
```

Run release (custom git_dir):
```
bash hq/.claude/scripts/wporg-release.sh maxtdesign-pdf-viewer X.Y.Z --git-dir [path]
```

Asset-only update:
```
bash hq/.claude/scripts/wporg-release.sh maxtdesign-pdf-viewer --assets-only
```

Dry run (no commit):
```
bash hq/.claude/scripts/wporg-release.sh maxtdesign-pdf-viewer X.Y.Z --dry-run
```

Full procedure details: `hq/.claude/agents/development/wporg-release.md`
