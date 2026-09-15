# Videos 0.19.0 release validation

This document separates checks that are enforced automatically by CI from checks performed on real Geeklog installations.

The supported release target remains:

- Geeklog 2.1.1 through 2.2.2
- PHP 5.6 through 8.1

## Automated CI checks

The `Validate Videos release matrix` workflow must be green before release.

### PHP compatibility

The complete PHP/INC source tree is linted on:

- PHP 5.6
- PHP 7.4
- PHP 8.1

The storage regression test is executed on each PHP version.

### Storage migration

`tools/test-storage-migration.php` verifies:

- legacy storage remains active during ordinary runtime bootstrap;
- migration only happens after `migrateLegacyStorage()` is explicitly called;
- secrets and persistent data survive migration;
- the legacy source is preserved;
- a migration marker is written;
- retrying migration is safe;
- subsequent requests select the migrated root;
- two sites sharing plugin files derive isolated storage roots;
- custom storage inside `path_data` is rejected;
- an unusable preferred destination falls back to readable legacy storage;
- a valid absolute custom persistent root is accepted.

### Geeklog integration contracts

CI verifies that the plugin loads and exposes its native integration files and callbacks, including:

- Search API
- statistics summary
- Item Info
- ID -> URL and URL -> ID
- Content Syndication declaration and content callback
- autotag support
- save/delete lifecycle integration

CI also checks that runtime bootstrap does not contain the removed implicit migration path.

### Install/uninstall consistency

The features declared at installation must exactly match the features removed by auto-uninstall.

### Administration languages

All semantic `admin_*` language keys referenced by the four administration pages must exist in both English and French.

## Manual Geeklog integration matrix

The 0.19.0 release candidate has been validated on reference Geeklog 2.1.1 and 2.2.2 installations.

| Scenario | Geeklog | PHP | Expected result | Status |
| --- | --- | --- | --- | --- |
| Fresh install | 2.1.1 | 5.6 | Plugin installs and admin/public pages load | Passed |
| Fresh install | 2.2.2 | 8.1 | Plugin installs and admin/public pages load | Passed |
| Upgrade from Videos 0.17.1 | 2.1.1 | 5.6 | Explicit storage migration succeeds and preserves data | Passed |
| Upgrade from Videos 0.18.0 | 2.1.1 or 2.2.2 | supported PHP | Upgrade reaches 0.19.0 without data loss | Passed |
| Shared-files multisite | 2.1.1 | 5.6 | One site can upgrade while another still uses legacy storage | Passed |
| Shared-files multisite | 2.2.2 | 8.1 | Separate `path_data` sites remain isolated | Passed |

## Functional checks on each reference installation

### Administration

- Overview, Actions, Statistics and Moderation use the same shell and navigation.
- English installation displays English strings only.
- French installation displays French strings only.
- Configuration tooltips wrap correctly.
- No `text_xxx` key or raw language identifier appears in the UI.

### Public rendering

- Catalogue loads from local persistent/cache data.
- Video page renders without forcing a discovery request.
- Channels and rankings pages load when enabled.
- Responsive CSS remains usable on a narrow viewport.

### Autotag

- `[videos:VIDEO_ID]` renders only for a valid public retained video.
- player mode renders the privacy-enhanced YouTube embed.
- autotag CSS is loaded only when an autotag is actually rendered.

### Dynamic block

- Videos block renders on the configured side.
- block CSS is loaded only when content is produced.
- play button is centered over the thumbnail.
- unavailable, blocked and excluded items are not shown.

### Search and statistics

- Videos appears in Geeklog advanced search.
- search uses local plugin data and returns valid public URLs.
- site statistics can call the Videos summary without warnings.

### Content Syndication

- Videos is available as a native feed source in Geeklog Content Syndication.
- generated RSS/Atom entries come from the permanent editorial catalogue.
- feed generation does not consume YouTube discovery quota.
- feed limit and content length settings are respected.

### XMLSitemap

- XMLSitemap can obtain Videos items through the Item Info API.
- permanent public videos generate valid local URLs.
- blocked/excluded/unavailable videos are omitted.
- no dedicated Videos sitemap collector is required for 0.19.0 if Item Info fallback works correctly.

### Lifecycle / IndexNow interoperability

- adding/re-admitting a public retained video emits the save lifecycle event.
- removing/excluding a public video emits the delete lifecycle event.
- excluding a channel removes affected public video items from downstream consumers.
- re-enabling content makes it discoverable again without plugin-specific polling.

## Release gate

0.19.0 is ready to be marked stable when:

1. `Build installable Videos archive` is green;
2. `Validate Videos release matrix` is green;
3. the six installation/upgrade rows above have been tested successfully;
4. no release-blocking regression remains in storage migration, admin rendering, search, syndication or lifecycle events.

All manual integration scenarios above are now recorded as passed. The final release gate therefore depends on the CI workflows remaining green on the stable release commit.
