<?php

$root = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'videos-storage-test-'
    . str_replace('.', '', uniqid('', true));
$pathData = $root . DIRECTORY_SEPARATOR . 'data' . DIRECTORY_SEPARATOR
    . 'S1' . DIRECTORY_SEPARATOR;
$legacy = $pathData . 'videos' . DIRECTORY_SEPARATOR;
$_CONF = array('path' => $root . DIRECTORY_SEPARATOR);

require dirname(__FILE__) . '/../classes/Videos_Validator.php';
require dirname(__FILE__) . '/../classes/Videos_JsonStore.php';
require dirname(__FILE__) . '/../classes/Videos_Bootstrap.php';

function videos_test_assert($condition, $message)
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

function videos_test_remove_tree($path, $root)
{
    $path = realpath($path);
    $root = realpath($root);
    if ($path === false || $root === false || strpos($path, $root) !== 0) {
        return;
    }
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST
    );
    foreach ($iterator as $item) {
        if ($item->isDir()) {
            rmdir($item->getPathname());
        } else {
            unlink($item->getPathname());
        }
    }
    rmdir($path);
}

try {
    $legacyStore = new Videos_JsonStore($legacy, 5242880);
    videos_test_assert($legacyStore->initialize(), 'Cannot initialize legacy store.');
    $secret = str_repeat('a', 64);
    $apiKey = 'AIzaSyVideosStorageMigrationTest123456';
    $secrets = $legacyStore->createDocument(
        'videos.secrets',
        array('privacy_hmac_key' => $secret, 'youtube_api_key' => $apiKey)
    );
    videos_test_assert(
        $legacyStore->write('config/secrets.json', 'videos.secrets', $secrets),
        'Cannot write legacy secrets.'
    );
    $sample = $legacyStore->createDocument(
        'videos.test_sample',
        array('value' => 'preserved')
    );
    videos_test_assert(
        $legacyStore->write('ratings/sample.json', 'videos.test_sample', $sample),
        'Cannot write legacy sample.'
    );

    $configuration = array(
        'path' => $root . DIRECTORY_SEPARATOR,
        'path_data' => $pathData,
        'path_html' => $root . DIRECTORY_SEPARATOR . 'public_html'
            . DIRECTORY_SEPARATOR
    );
    $preferred = rtrim($pathData, '/\\') . '-videos' . DIRECTORY_SEPARATOR;

    // Runtime bootstrap must keep using the legacy directory until an explicit
    // upgrade/repair migration is requested.
    $bootstrap = new Videos_Bootstrap($configuration);
    videos_test_assert($bootstrap->isReady(), 'Legacy bootstrap is not ready.');
    videos_test_assert(
        $bootstrap->getDataRoot() === $legacy,
        'Runtime bootstrap migrated storage implicitly.'
    );
    videos_test_assert($bootstrap->getSecret() === $secret, 'Legacy HMAC secret changed.');
    videos_test_assert(
        $bootstrap->getYouTubeApiKey() === $apiKey,
        'Legacy YouTube API key changed.'
    );
    videos_test_assert(
        !is_file($preferred . 'ratings' . DIRECTORY_SEPARATOR . 'sample.json'),
        'Preferred storage was populated before explicit migration.'
    );

    // Controlled migration copies data, preserves the source and switches the
    // active site to its preferred site-scoped storage root.
    videos_test_assert(
        $bootstrap->migrateLegacyStorage(),
        'Explicit legacy storage migration failed.'
    );
    videos_test_assert(
        $bootstrap->getDataRoot() === $preferred,
        'Explicit migration did not switch to preferred storage.'
    );
    videos_test_assert($bootstrap->getSecret() === $secret, 'Migrated HMAC secret changed.');
    videos_test_assert(
        $bootstrap->getYouTubeApiKey() === $apiKey,
        'Migrated YouTube API key changed.'
    );
    videos_test_assert(
        is_file($legacy . 'config' . DIRECTORY_SEPARATOR . 'secrets.json'),
        'Legacy source was removed.'
    );
    videos_test_assert(
        is_file($preferred . 'ratings' . DIRECTORY_SEPARATOR . 'sample.json'),
        'Persistent sample was not copied.'
    );
    videos_test_assert(
        is_file($preferred . 'config' . DIRECTORY_SEPARATOR . 'storage-migration.json'),
        'Migration marker is missing.'
    );

    // Migration must be safely retryable and a subsequent request must select
    // the already-migrated preferred root without touching the legacy source.
    videos_test_assert(
        $bootstrap->migrateLegacyStorage(),
        'Migration retry was not idempotent.'
    );
    $second = new Videos_Bootstrap($configuration);
    videos_test_assert($second->isReady(), 'Second bootstrap is not ready.');
    videos_test_assert(
        $second->getDataRoot() === $preferred,
        'Completed migration was not detected on a subsequent request.'
    );
    videos_test_assert($second->getSecret() === $secret, 'Second load changed secret.');

    // A second site sharing the plugin files must derive a different storage
    // root and must not see S1 persistent data.
    $s2Data = $root . DIRECTORY_SEPARATOR . 'data' . DIRECTORY_SEPARATOR
        . 'S2' . DIRECTORY_SEPARATOR;
    $s2Configuration = array(
        'path' => $root . DIRECTORY_SEPARATOR,
        'path_data' => $s2Data,
        'path_html' => $root . DIRECTORY_SEPARATOR . 'public_html'
            . DIRECTORY_SEPARATOR
    );
    $s2 = new Videos_Bootstrap($s2Configuration);
    $s2Preferred = rtrim($s2Data, '/\\') . '-videos' . DIRECTORY_SEPARATOR;
    videos_test_assert(
        $s2->isReady() && $s2->getDataRoot() === $s2Preferred,
        'Fresh second site did not use its own preferred storage root.'
    );
    videos_test_assert(
        !is_file($s2Preferred . 'ratings' . DIRECTORY_SEPARATOR . 'sample.json'),
        'Second site can see first-site persistent data.'
    );

    // A configured path inside path_data is unsafe because Geeklog may clean
    // that directory during an upgrade; it must therefore be rejected.
    $invalidConfiguration = array(
        'path' => $root . DIRECTORY_SEPARATOR,
        'path_data' => $s2Data,
        'path_html' => $root . DIRECTORY_SEPARATOR . 'public_html'
            . DIRECTORY_SEPARATOR,
        'videos_data_path' => $s2Data . 'persistent' . DIRECTORY_SEPARATOR
    );
    $invalid = new Videos_Bootstrap($invalidConfiguration);
    videos_test_assert(
        $invalid->getDataRoot() === $s2Preferred,
        'A custom path inside path_data was not rejected.'
    );

    // Simulate an unusable preferred destination with a regular file where the
    // directory should be. Explicit migration must fail without abandoning the
    // readable legacy site data.
    $s4Data = $root . DIRECTORY_SEPARATOR . 'data' . DIRECTORY_SEPARATOR
        . 'S4' . DIRECTORY_SEPARATOR;
    $s4Legacy = $s4Data . 'videos' . DIRECTORY_SEPARATOR;
    $s4Store = new Videos_JsonStore($s4Legacy, 5242880);
    videos_test_assert($s4Store->initialize(), 'Cannot initialize S4 legacy store.');
    $s4Sample = $s4Store->createDocument(
        'videos.test_sample',
        array('value' => 'fallback')
    );
    videos_test_assert(
        $s4Store->write('ratings/sample.json', 'videos.test_sample', $s4Sample),
        'Cannot write S4 legacy sample.'
    );
    $s4PreferredBase = rtrim($s4Data, '/\\') . '-videos';
    videos_test_assert(
        file_put_contents($s4PreferredBase, 'not-a-directory') !== false,
        'Cannot create blocked preferred destination.'
    );
    $s4Configuration = array(
        'path' => $root . DIRECTORY_SEPARATOR,
        'path_data' => $s4Data,
        'path_html' => $root . DIRECTORY_SEPARATOR . 'public_html'
            . DIRECTORY_SEPARATOR
    );
    $s4 = new Videos_Bootstrap($s4Configuration);
    videos_test_assert(
        $s4->isReady() && $s4->getDataRoot() === $s4Legacy,
        'Legacy fallback was not selected before failed migration.'
    );
    videos_test_assert(
        !$s4->migrateLegacyStorage(),
        'Migration unexpectedly succeeded with an unusable destination.'
    );
    videos_test_assert(
        $s4->isReady() && $s4->getDataRoot() === $s4Legacy,
        'Failed migration did not preserve the legacy runtime root.'
    );
    $s4Status = $s4->getMigrationStatus();
    videos_test_assert(
        !empty($s4Status['legacy_fallback']),
        'Failed migration did not record legacy fallback status.'
    );

    // A valid absolute custom root outside path_data remains supported.
    $customRoot = $root . DIRECTORY_SEPARATOR . 'persistent'
        . DIRECTORY_SEPARATOR . 'S3' . DIRECTORY_SEPARATOR . 'videos'
        . DIRECTORY_SEPARATOR;
    $customConfiguration = array(
        'path' => $root . DIRECTORY_SEPARATOR,
        'path_data' => $root . DIRECTORY_SEPARATOR . 'data'
            . DIRECTORY_SEPARATOR . 'S3' . DIRECTORY_SEPARATOR,
        'path_html' => $root . DIRECTORY_SEPARATOR . 'public_html'
            . DIRECTORY_SEPARATOR,
        'videos_data_path' => $customRoot
    );
    $custom = new Videos_Bootstrap($customConfiguration);
    videos_test_assert(
        $custom->isReady() && $custom->getDataRoot() === $customRoot,
        'A valid absolute custom path was not accepted.'
    );

    echo "storage migration test: OK\n";
} finally {
    videos_test_remove_tree($root, $root);
}
