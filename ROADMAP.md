# Videos roadmap

This roadmap aligns the Videos plugin with the current recommendations documented in [`hostellerie/memorandum`](https://github.com/hostellerie/memorandum), while preserving the current compatibility target:

- Geeklog 2.1.1 through 2.2.2
- PHP 5.6 through PHP 8.1

The objective is not to add more features immediately. The priority is to stabilize the plugin, simplify its architecture, and strengthen interoperability before extending it further.

## 0.19.0 — urgent stabilization before release

Version 0.19.0 remains the active stabilization release. The following work should be completed before considering it ready for broad deployment.

### P0 — move persistent storage migration out of runtime bootstrap

**Current issue**

`Videos_Bootstrap` can currently migrate legacy plugin data merely because the plugin is loaded. This is convenient, but it conflicts with the shared-files upgrade safety principles documented in the Memorandum.

**Target**

- `Videos_Bootstrap` must detect the active site's current storage state without performing a destructive or persistent migration during normal requests.
- Legacy storage must remain readable until the active site explicitly completes its plugin upgrade.
- The `0.17.x -> current` storage migration must run from the controlled upgrade path or an explicit repair action.
- Migration must remain site-scoped, idempotent, restartable and non-destructive.
- Existing legacy data must be preserved until the new destination has been written and verified.
- Uploading new shared plugin files must not force all sites using those files to migrate at the same time.

**Primary files**

- `classes/Videos_Bootstrap.php`
- `install_updates.php`
- `admin/repair.php`

**Done when**

- frontend and admin requests can use new plugin files while the current site is still on the previous persisted storage state;
- only explicit upgrade/repair code performs the migration;
- repeated migration attempts are safe;
- two sites with separate `path_data` values remain isolated.

### P0 — complete lifecycle events

Videos already emits `PLG_itemSaved()` through the interoperability layer, but removal paths must be audited and completed.

**Target**

Use lifecycle events consistently for every addressable item transition:

- newly published / re-admitted / metadata changed -> `PLG_itemSaved()`;
- removed / excluded / no longer public -> `PLG_itemDeleted()`;
- collection pages affected by a change should receive the appropriate save signal where useful.

Important mutation paths include:

- manual permanent-pool actions;
- automatic permanent-pool synchronization;
- moderation state changes;
- channel exclusion / re-enablement;
- any alternate administration or maintenance path affecting public content.

**Primary files**

- `classes/Videos_PermanentPool.php`
- `classes/Videos_Moderation.php`
- `interoperability.php`

**Done when**

Hub, IndexNow, XMLSitemap and other consumers can rely on lifecycle events without plugin-specific polling.

### P0 — remove hashed administration language keys

The temporary `text_xxx` keys introduced during administration refactoring made the interface difficult to maintain and already caused visible translation regressions.

**Target**

Replace hashed identifiers with semantic language keys, for example:

```php
$LANG_VIDEOS['admin_curation_title']
$LANG_VIDEOS['admin_curation_intro']
$LANG_VIDEOS['admin_stats_public_content']
```

Remove the now-unnecessary compatibility translation layers once no page depends on them.

**Primary files**

- `language/english.php`
- `language/french_france.php`
- `functions.inc`
- `admin/index.php`
- `admin/actions.php`
- `admin/stats.php`
- `admin/moderation.php`

**Done when**

- no administration page renders `text_xxx` identifiers;
- no hashed language identifiers remain in active administration code;
- the four administration pages use the same semantic localization mechanism;
- CI validates that referenced language keys exist in both English and French.

### P0 — finish administration UI consistency

The four administration pages must behave as one interface, not four independently evolved screens.

**Target**

- one shared admin shell;
- one navigation helper;
- consistent headings, panels, buttons, forms, tables and spacing;
- no page-specific inline CSS;
- content never touches the outer container edges;
- all visible UI strings come from language files;
- responsive behavior remains usable on small screens.

**Pages**

- Overview
- Actions
- Statistics
- Moderation

### P1 — complete native Content Syndication support

`plugin_getfeedcontent_videos()` already exposes the persistent editorial corpus.

Add the missing native feed declaration:

```php
plugin_getfeednames_videos()
```

Prefer a simple initial feed such as the permanent/public video catalogue rather than multiple overlapping feeds.

Optional for 0.19.0 if straightforward:

```php
plugin_feedupdatecheck_videos()
```

The feed implementation must reuse Item Info metadata and must not trigger YouTube discovery requests.

### P1 — fix packaging / uninstall consistency

Audit installation and removal metadata before release.

Known item:

- ensure `config.videos.tab_seo` and every currently-created feature are also removed by `plugin_autouninstall_videos()`.

Also verify that the installable ZIP contains only runtime files required by Geeklog and that build validation covers the final administration/language state.

### P1 — release validation matrix

Before declaring 0.19.0 stable, test at least:

- fresh install on Geeklog 2.1.1;
- fresh install on Geeklog 2.2.2;
- upgrade from 0.17.1;
- upgrade from 0.18.0;
- PHP 5.6;
- PHP 8.1;
- storage migration success;
- storage migration retry;
- unwritable preferred storage path with legacy fallback;
- two sites sharing plugin files but using separate `path_data` values;
- Content Syndication;
- XMLSitemap fallback through Item Info;
- Geeklog search and statistics;
- administration pages in English and French;
- autotag and dynamic block rendering.

## 0.20.0 — architectural consolidation

Version 0.20.0 should be a simplification release, not a feature race. The objective is to reduce coupling and prepare the plugin for future common Geeklog integration services.

### P1 — move YouTube refresh work out of visitor requests

Public page requests should primarily read local state.

**Target model**

```text
scheduled/admin maintenance
        -> YouTube API
        -> discovery reservoir / cache

visitor request
        -> local cache / rankings / editorial corpus
```

Move routine reservoir refreshes to explicit maintenance, cron/scheduled execution, or another controlled background mechanism compatible with the supported Geeklog range.

Keep manual seeding / refresh actions in administration.

Benefits:

- predictable frontend response time;
- fewer accidental YouTube quota spikes;
- better resilience during provider outages;
- clearer separation between external synchronization and public rendering.

### P1 — autoload plugin classes

`functions.inc` currently loads most Videos classes on every Geeklog request.

Introduce a PHP 5.6-compatible autoloader using `spl_autoload_register()` so classes are loaded only when needed.

Keep `functions.inc` focused on:

- minimal bootstrap;
- configuration;
- Plugin API callbacks;
- compatibility helpers.

### P1 — introduce `.thtml` templates progressively

Move significant presentation markup out of long PHP string concatenations.

Suggested first targets:

```text
templates/
    catalogue.thtml
    video-card.thtml
    navigation.thtml
    admin/
        page.thtml
        section.thtml
        stats-card.thtml
```

Business logic should remain in PHP. Templates should remain theme-independent and compatible with Geeklog 2.1.1 through 2.2.2.

### P1 — consolidate configuration definitions

Configuration is currently represented in several places: defaults, initialization schema, validation, language labels and tooltips.

Create one declarative PHP 5.6-compatible schema that can drive as much of this behavior as practical without inventing a large framework.

A setting definition may describe:

- default;
- Geeklog configuration type;
- tab;
- order;
- select set;
- validation bounds.

The objective is to reduce duplication and prevent defaults, installation and validation from diverging.

### P2 — introduce a provider abstraction for external video services

Do not couple the rest of the plugin directly to the current YouTube HTTP implementation.

Introduce a small provider contract around the capabilities Videos actually needs, for example:

```text
search()
videos()
channels()
```

`Videos_YouTubeProvider` can initially wrap the existing YouTube client and service classes.

This prepares Videos for the future common Geeklog Integration Layer without depending on an API that does not yet exist.

Do not add provider support that has no real use case.

### P2 — strengthen HTTP resilience without overengineering

For the current provider client, consider:

- explicit response-size limits;
- structured errors shared across provider operations;
- narrowly-scoped retry/backoff for transient 429/5xx responses where safe;
- provider rate-limit metadata where available;
- no retry for functional validation errors or exhausted quota conditions.

Keep TLS verification, bounded timeouts and host restrictions.

### P2 — define the boundary between JSON storage and SQL

Keep the current JsonStore for compact site-scoped state where it works well, but avoid growing it into a general database engine.

Guideline:

```text
JSON
    cache, compact state, secrets, small indexes, bounded records

SQL
    use when queries, relations, large datasets, filtering or aggregation
    begin to justify a relational store
```

No migration to SQL is required for 0.20.0 unless a concrete scaling or querying problem appears.

### P2 — add focused interoperability tests

Add automated or reproducible tests for:

- `plugin_getiteminfo_videos()` single item;
- collection `'*'` with `since`, `limit`, `order`;
- ID -> URL;
- URL -> ID;
- save/delete lifecycle signaling;
- Content Syndication;
- XMLSitemap Item Info fallback;
- search and statistics callbacks;
- moderation visibility rules.

### P3 — optional feed regeneration optimization

If not completed in 0.19.0, add:

```php
plugin_feedupdatecheck_videos()
```

using the latest public corpus modification timestamp.

### P3 — native sitemap collector only if justified

Do **not** add `plugin_collectSitemapItems_videos()` merely because the callback exists.

The current Item Info collection fallback is sufficient unless real requirements appear for:

- sitemap-specific filtering;
- large-corpus performance;
- custom priorities;
- sitemap-only permission logic.

If those needs appear, the specialized collector can be added later while reusing the same underlying content inventory logic.

### P3 — specialized services only for real actions

Do not create Connector-specific or AtomPub service callbacks simply to advertise compatibility.

Existing capabilities should remain discoverable through normal Geeklog Plugin APIs where possible.

Add services only for genuine specialized actions, for example future authorized operations such as:

- add a video to the permanent catalogue;
- block/unblock a video;
- prioritize a channel.

Such actions must preserve Geeklog ACL checks, security tokens or equivalent authorization, and auditability.

## Architecture principles to preserve

Videos should continue to follow these rules throughout both releases:

1. **Site-scoped state** — derive persistent storage and configuration from the active Geeklog site context.
2. **Shared-files safe upgrades** — deploying new plugin files must not silently migrate every site that shares those files.
3. **Structured interoperability first** — Item Info, lifecycle events and URL resolution remain the primary common contract.
4. **Local rendering first** — public rendering should work from local state whenever possible.
5. **No unnecessary duplication of Geeklog Core** — reuse search, statistics, syndication, sitemap and Plugin API mechanisms before inventing plugin-specific alternatives.
6. **Provider-specific code stays isolated** — YouTube details should not leak throughout the plugin business model.
7. **Persistent data is not cache** — cache cleanup must never erase editorial, moderation, privacy or user-owned state.
8. **PHP 5.6–8.1 compatibility remains intentional** until the project explicitly changes policy.
9. **Theme independence** — Eclipse may be a reference presentation environment, but Videos must not depend on Eclipse-specific markup or behavior.
10. **Simplify before extending** — future capabilities should reduce coupling or provide demonstrated user value.

## Not planned for 0.20.0 unless requirements change

The following are intentionally not priorities:

- replacing JsonStore with SQL without a measured need;
- adding multiple video providers without a real use case;
- building a plugin-specific REST API before a shared Geeklog resource layer exists;
- duplicating XMLSitemap logic while Item Info fallback remains sufficient;
- adding additional SEO layers that overlap Hub, IndexNow, Analytics or search-engine measurement plugins;
- adding new recommendation systems before the existing architecture is consolidated.

## Release philosophy

### 0.19.0

**Stabilize what already exists.**

The release should be safe to install, safe to upgrade, multilingual, interoperable, and predictable in shared-files multisite environments.

### 0.20.0

**Make the same plugin easier to understand and maintain.**

The release should reduce runtime coupling, move presentation toward templates, isolate external providers and make synchronization explicit rather than adding another large feature layer.
