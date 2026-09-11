<?php

require_once '../../../lib-common.php';

if (!SEC_hasRights('videos.admin')) {
    echo COM_createHTMLDocument(
        COM_showMessageText($LANG_VIDEOS['access_denied'], '', true),
        array('pagetitle' => $LANG_VIDEOS['admin_title'], 'headercode' => VIDEOS_adminHeaderCode())
    );
    exit;
}

$bootstrap = new Videos_Bootstrap($_CONF);
$message = '';
$searchResults = array();
if (!$bootstrap->isReady()) {
    $message = VIDEOS_adminText('text_ad4bee7e9ff4');
}
$store = $bootstrap->isReady() ? $bootstrap->getStore() : null;
$cache = $store ? new Videos_Cache($store) : null;
$pool = $store ? new Videos_PermanentPool($store, $cache) : null;
$moderation = $store ? new Videos_Moderation($store) : null;
$ranking = $store ? new Videos_Ranking(
    $store,
    new Videos_RatingStats($store),
    new Videos_VideoStats($store),
    $cache
) : null;

if ($store && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!SEC_checkToken()) {
        $message = VIDEOS_adminText('text_e3a2abb1df9e');
    } else {
        $action = isset($_POST['videos_action']) ? COM_applyFilter($_POST['videos_action']) : '';
        if ($action === 'save_key') {
            $key = isset($_POST['youtube_api_key']) ? trim((string) $_POST['youtube_api_key']) : '';
            $message = $bootstrap->setYouTubeApiKey($key)
                ? VIDEOS_adminText('text_01bc6389bfb4')
                : VIDEOS_adminText('text_5f254586a799');
        } elseif ($action === 'test_search') {
            $query = isset($_POST['test_query']) ? trim(strip_tags((string) $_POST['test_query'])) : '';
            if ($query === '' || strlen($query) > 250) {
                $message = VIDEOS_adminText('text_7f47edc0ce97');
            } else {
                $searchResults = videos_actions_test_search($bootstrap, $query, $_VIDEOS_CONF);
                $message = $searchResults === false
                    ? videos_actions_failure_message($store, $_VIDEOS_CONF, VIDEOS_adminText('text_7b0911c5e838'))
                    : count($searchResults['video_ids']) . VIDEOS_adminText('text_f34564089709');
            }
        } elseif ($action === 'seed_discovery' && SEC_hasRights('videos.maintenance')) {
            $query = isset($_POST['seed_query']) ? trim(strip_tags((string) $_POST['seed_query'])) : '';
            if ($query === '' || strlen($query) > 250) {
                $message = VIDEOS_adminText('text_b9af87bba9de');
            } else {
                $result = videos_actions_seed_discovery($bootstrap, $query, $_VIDEOS_CONF);
                $message = is_array($result) && !empty($result['success'])
                    ? (int) $result['added'] . VIDEOS_adminText('text_2486b5cd3e93')
                    : videos_actions_failure_message($store, $_VIDEOS_CONF, VIDEOS_adminText('text_bed0f10568ed'));
            }
        } elseif ($action === 'clear_cache' && SEC_hasRights('videos.maintenance')) {
            $scope = isset($_POST['cache_scope']) ? COM_applyFilter($_POST['cache_scope']) : '';
            $result = (new Videos_CacheMaintenance($store))->clear($scope);
            $message = !empty($result['success'])
                ? (int) $result['deleted'] . VIDEOS_adminText('text_3e3769ea8e57')
                : VIDEOS_adminText('text_0ff52fd5762a') . (int) $result['deleted'] . VIDEOS_adminText('text_6c77e30d5f18')
                    . (int) $result['failed'] . VIDEOS_adminText('text_935b440d1a9c');
        } elseif ($action === 'rebuild_ranking' && SEC_hasRights('videos.maintenance')) {
            $count = $ranking->rebuild();
            $message = $count === false
                ? VIDEOS_adminText('text_3b343dfb9214')
                : VIDEOS_adminText('text_c8e38f9b9716') . (int) $count . VIDEOS_adminText('text_e0a70ebd0d32');
        } elseif ($action === 'pool_rebuild' && SEC_hasRights('videos.maintenance')) {
            $result = $pool->synchronize($ranking->getGlobal(500), $_VIDEOS_CONF, true);
            $message = $result === false
                ? VIDEOS_adminText('text_cc6acc866d8d')
                : VIDEOS_adminText('text_fb0584cd8c48');
        } elseif ($action === 'add_video') {
            $input = isset($_POST['video_input']) ? trim((string) $_POST['video_input']) : '';
            $videoId = videos_admin_extract_video_id($input);
            if ($videoId === '') {
                $message = VIDEOS_adminText('text_82147ba36284');
            } else {
                $video = $cache->getVideo($videoId, true);
                if (!is_array($video)) {
                    $video = videos_admin_fetch_single_video($bootstrap, $cache, $videoId, $_VIDEOS_CONF);
                }
                if (!is_array($video)) {
                    $message = VIDEOS_adminText('text_77f7f85bea7d');
                } elseif ($moderation->isVideoBlocked($videoId)) {
                    $message = VIDEOS_adminText('text_6b92eea0db9c');
                } else {
                    $global = $ranking->getGlobal(500);
                    $rankingItem = isset($global[$videoId]) ? $global[$videoId] : array();
                    $saved = $pool->setManualState($videoId, 'added', $rankingItem);
                    $message = $saved
                        ? VIDEOS_adminText('text_1b98ef900ab7')
                        : VIDEOS_adminText('text_d51726c9d960');
                }
            }
        } elseif (in_array(
            $action,
            array('pool_add', 'pool_pin', 'pool_unpin', 'pool_remove', 'pool_exclude', 'pool_allow'),
            true
        )) {
            $videoId = isset($_POST['video_id']) ? COM_applyFilter($_POST['video_id']) : '';
            $stateMap = array(
                'pool_add' => 'added',
                'pool_pin' => 'pinned',
                'pool_unpin' => 'unpinned',
                'pool_remove' => 'removed',
                'pool_exclude' => 'excluded',
                'pool_allow' => 'allowed'
            );
            $global = $ranking->getGlobal(500);
            $rankingItem = isset($global[$videoId]) ? $global[$videoId] : array();
            $message = $pool->setManualState($videoId, $stateMap[$action], $rankingItem)
                ? VIDEOS_adminText('text_094f5ad0c0fe')
                : VIDEOS_adminText('text_fa630f6fba54');
        } elseif ($action === 'signal_public_pages') {
            $urls = videos_admin_public_urls(
                $store,
                $cache,
                $pool,
                $moderation,
                $ranking,
                $_VIDEOS_CONF
            );
            if (count($urls) === 0) {
                $message = VIDEOS_adminText('text_974483b3c0bd');
            } elseif (function_exists('send_to_indexnow')) {
                $result = send_to_indexnow(
                    $urls,
                    array('item_type' => 'videos', 'event' => 'manual-sync')
                );
                $message = $result === false
                    ? VIDEOS_adminText('text_f9f0422359a1')
                    : count($urls) . VIDEOS_adminText('text_66d82f59b38e');
            } else {
                $message = VIDEOS_adminText('text_bf4753222ec0');
            }
        }
    }
}

$token = SEC_createToken();
$records = $pool ? $pool->records() : array('items' => array(), 'excluded' => array());

$html = VIDEOS_adminPageOpen('actions', $LANG_VIDEOS['admin_nav_actions']);
if ($message !== '') {
    $message = VIDEOS_localizeAdminText($message);
    if ($message === VIDEOS_adminText('text_1b98ef900ab7')) {
        $html .= '<p class="videos-admin-help"><strong>'
            . htmlspecialchars($message, ENT_QUOTES, 'UTF-8') . '</strong></p>';
    } else {
        $html .= COM_showMessageText($message, '', true);
    }
}
$html .= '<section class="videos-admin-section"><h2>' . VIDEOS_adminText('text_12cb48a2ffe8') . '</h2>'
    . '<p>' . VIDEOS_adminText('text_e4a4d74ceeba') . '</p>'
    . '<form class="videos-admin-form" method="post"><input type="hidden" name="videos_action" value="add_video">'
    . '<input type="hidden" name="' . CSRF_TOKEN . '" value="' . htmlspecialchars($token, ENT_QUOTES, 'UTF-8') . '">'
    . '<label>' . VIDEOS_adminText('text_349916d37ddb') . ' <input type="text" name="video_input" maxlength="500" size="60" placeholder="H5nzrlARuCo' . VIDEOS_adminText('text_2dcd74f139c2') . '" required></label> '
    . '<button type="submit">' . VIDEOS_adminText('text_20c9e4289922') . '</button></form></section>';

$html .= '<section class="videos-admin-section"><h2>' . VIDEOS_adminText('text_3696c9b66256') . '</h2>';
if (empty($records['items'])) {
    $html .= '<p>' . VIDEOS_adminText('text_d0d466d799cb') . '</p>';
} else {
    $html .= '<div class="videos-admin-table-wrap"><table class="admin-list videos-admin-table"><thead><tr>'
        . '<th>' . VIDEOS_adminText('text_304f6ca42f36') . '</th><th>' . VIDEOS_adminText('text_d3b28a2adb27') . '</th><th>' . $LANG_VIDEOS['admin_actions_column'] . '</th></tr></thead><tbody>';
    foreach ($records['items'] as $videoId => $item) {
        $video = $cache->getVideo($videoId, true);
        $title = is_array($video) && !empty($video['snippet']['title'])
            ? $video['snippet']['title'] : $videoId;
        $isPinned = !empty($item['pinned']);
        $html .= '<tr><td><a href="' . htmlspecialchars(plugin_idtourl_videos('', $videoId), ENT_QUOTES, 'UTF-8') . '">'
            . htmlspecialchars($title, ENT_QUOTES, 'UTF-8') . '</a><br><code>'
            . htmlspecialchars($videoId, ENT_QUOTES, 'UTF-8') . '</code></td><td>'
            . ($isPinned ? VIDEOS_adminText('text_12bd8de0f11f') : VIDEOS_adminText('text_4bc9bbe28d82')) . '</td><td>';
        $html .= $isPinned
            ? videos_admin_action_form('pool_unpin', $videoId, VIDEOS_adminText('text_6c1f6aad64af'), $token)
            : videos_admin_action_form('pool_pin', $videoId, VIDEOS_adminText('text_9f4045dd74f4'), $token);
        $html .= videos_admin_action_form('pool_remove', $videoId, VIDEOS_adminText('text_ddcf6449d058'), $token)
            . videos_admin_action_form('pool_exclude', $videoId, VIDEOS_adminText('text_6ce15f0ae2c2'), $token)
            . '</td></tr>';
    }
    $html .= '</tbody></table></div>'
        . '<p class="videos-admin-help"><strong>' . VIDEOS_adminText('text_ddcf6449d058') . '</strong> ' . $LANG_VIDEOS['admin_remove_help'] . ' '
        . '<strong>' . VIDEOS_adminText('text_6ce15f0ae2c2') . '</strong> ' . $LANG_VIDEOS['admin_exclude_help'] . '</p>';
}
if (!empty($records['excluded'])) {
    $html .= '<details class="videos-advanced-field"><summary>' . VIDEOS_adminText('text_5076293e41de') . ' ('
        . count($records['excluded']) . ')</summary><ul>';
    foreach ($records['excluded'] as $videoId => $excludedAt) {
        $html .= '<li><code>' . htmlspecialchars($videoId, ENT_QUOTES, 'UTF-8') . '</code> '
            . videos_admin_action_form('pool_allow', $videoId, VIDEOS_adminText('text_a38b9729e18c'), $token) . '</li>';
    }
    $html .= '</ul></details>';
}
if (SEC_hasRights('videos.maintenance')) {
    $html .= '<form class="videos-admin-form" method="post"><input type="hidden" name="videos_action" value="pool_rebuild">'
        . '<input type="hidden" name="' . CSRF_TOKEN . '" value="' . htmlspecialchars($token, ENT_QUOTES, 'UTF-8') . '">'
        . '<button type="submit">' . VIDEOS_adminText('text_914c6f2a00bd') . '</button></form>';
}
$html .= '</section>';

$youtubeApiKeyConfigured = $bootstrap->getYouTubeApiKey() !== '';
$html .= '<section class="videos-admin-section"><h2>YouTube Data API</h2>'
    . '<p><strong>' . VIDEOS_adminText('text_724ddfa407d2') . '</strong> ' . ($youtubeApiKeyConfigured ? VIDEOS_adminText('text_cfb801cd863c') : VIDEOS_adminText('text_a41badeb3bf5')) . '</p>';
if (!$youtubeApiKeyConfigured) {
    $html .= '<p>' . VIDEOS_adminText('text_4ca61851cb1a') . '</p>';
}
$html .= '<form class="videos-admin-form" method="post"><input type="hidden" name="videos_action" value="save_key">'
    . '<input type="hidden" name="' . CSRF_TOKEN . '" value="' . htmlspecialchars($token, ENT_QUOTES, 'UTF-8') . '">'
    . '<label>' . ($youtubeApiKeyConfigured ? VIDEOS_adminText('text_7a468e16ab17') : VIDEOS_adminText('text_a05995d3d967')) . ' <input type="password" name="youtube_api_key" maxlength="200" autocomplete="new-password"></label> '
    . '<button type="submit">' . ($youtubeApiKeyConfigured ? VIDEOS_adminText('text_a6c9feab7f6b') : VIDEOS_adminText('text_e4d5d3b1e0c6')) . '</button></form>'
    . '<form class="videos-admin-form" method="post"><input type="hidden" name="videos_action" value="test_search">'
    . '<input type="hidden" name="' . CSRF_TOKEN . '" value="' . htmlspecialchars($token, ENT_QUOTES, 'UTF-8') . '">'
    . '<label>' . VIDEOS_adminText('text_39b35ebcf325') . ' <input type="text" name="test_query" maxlength="250" size="50" required></label> '
    . '<button type="submit">' . VIDEOS_adminText('text_559548968cfd') . '</button></form>';
if (is_array($searchResults) && !empty($searchResults['videos'])) {
    $html .= '<div class="videos-grid videos-admin-results">';
    foreach ($searchResults['videos'] as $videoId => $video) {
        $title = isset($video['snippet']['title']) ? $video['snippet']['title'] : $videoId;
        $html .= '<article class="videos-admin-result"><strong>'
            . htmlspecialchars($title, ENT_QUOTES, 'UTF-8') . '</strong><br><code>'
            . htmlspecialchars($videoId, ENT_QUOTES, 'UTF-8') . '</code></article>';
    }
    $html .= '</div>';
}
if (SEC_hasRights('videos.maintenance')) {
    $html .= '<form class="videos-admin-form" method="post"><input type="hidden" name="videos_action" value="seed_discovery">'
        . '<input type="hidden" name="' . CSRF_TOKEN . '" value="' . htmlspecialchars($token, ENT_QUOTES, 'UTF-8') . '">'
        . '<label>' . VIDEOS_adminText('text_5d8652cbb6d4') . ' <input type="text" name="seed_query" maxlength="250" size="50" required></label> '
        . '<button type="submit">' . VIDEOS_adminText('text_3b5c2464103e') . '</button></form>';
}
$html .= '</section>';

if (SEC_hasRights('videos.maintenance')) {
    $html .= '<section class="videos-admin-section"><h2>' . VIDEOS_adminText('text_94de303bbef8') . '</h2>'
        . '<form class="videos-admin-form" method="post"><input type="hidden" name="videos_action" value="rebuild_ranking">'
        . '<input type="hidden" name="' . CSRF_TOKEN . '" value="' . htmlspecialchars($token, ENT_QUOTES, 'UTF-8') . '">'
        . '<button type="submit">' . VIDEOS_adminText('text_50cc32da6a35') . '</button></form>'
        . '<form class="videos-admin-form" method="post"><input type="hidden" name="videos_action" value="clear_cache">'
        . '<input type="hidden" name="' . CSRF_TOKEN . '" value="' . htmlspecialchars($token, ENT_QUOTES, 'UTF-8') . '">'
        . '<label>' . $LANG_VIDEOS['admin_cache_label'] . ' <select name="cache_scope"><option value="search">' . VIDEOS_adminText('text_7e62fa37e865') . '</option><option value="videos">' . VIDEOS_adminText('text_ea129238fc57') . '</option>'
        . '<option value="channels">' . VIDEOS_adminText('text_88ef0e8638cb') . '</option><option value="availability">' . VIDEOS_adminText('text_0f06e60ae162') . '</option><option value="all">' . VIDEOS_adminText('text_b97ae3b4f909') . '</option></select></label> '
        . '<button type="submit">' . VIDEOS_adminText('text_daf08703c1e1') . '</button></form>'
        . '<p><a href="' . htmlspecialchars($_CONF['site_admin_url'] . '/plugins/videos/repair.php', ENT_QUOTES, 'UTF-8') . '">' . VIDEOS_adminText('text_c756339c6ce3') . '</a></p></section>';
}

if (function_exists('send_to_indexnow')) {
    $html .= '<section class="videos-admin-section"><h2>' . VIDEOS_adminText('text_383ccd02e996') . '</h2>'
        . '<p>' . VIDEOS_adminText('text_7f66e4e9d432') . '</p>'
        . '<form method="post"><input type="hidden" name="videos_action" value="signal_public_pages">'
        . '<input type="hidden" name="' . CSRF_TOKEN . '" value="' . htmlspecialchars($token, ENT_QUOTES, 'UTF-8') . '">'
        . '<button type="submit">' . VIDEOS_adminText('text_1167f948e9e0') . '</button></form></section>';
}
$html .= VIDEOS_adminPageClose();


echo COM_createHTMLDocument($html, array('pagetitle' => $LANG_VIDEOS['admin_title'], 'headercode' => VIDEOS_adminHeaderCode()));

function videos_actions_failure_message($store, $configuration, $prefix)
{
    $status = (new Videos_Quota($store))->status();
    $data = isset($status['data']) && is_array($status['data']) ? $status['data'] : array();
    $counts = isset($data['counts']) && is_array($data['counts']) ? $data['counts'] : array();
    $count = isset($counts['search']) ? (int) $counts['search'] : 0;
    $limit = isset($configuration['youtube_daily_search_limit'])
        ? max(0, (int) $configuration['youtube_daily_search_limit']) : 20;
    if (!empty($data['suspended'])) {
        $code = !empty($data['last_error']['code']) ? (string) $data['last_error']['code'] : 'quota';
        return $prefix . ' ' . VIDEOS_adminText('text_2663d0921433') . $code . ').';
    }
    if ($limit > 0 && $count >= $limit) {
        return $prefix . ' ' . VIDEOS_adminText('text_4c9ee5b96b2f')
            . $count . '/' . $limit . VIDEOS_adminText('text_e112686715a8');
    }
    if (!empty($data['last_error']['code'])) {
        return $prefix . ' ' . VIDEOS_adminText('text_3d0c6584d55c') . (string) $data['last_error']['code']
            . '. ' . $LANG_VIDEOS['admin_consult'] . ' ' . VIDEOS_adminText('text_fdce305a50b3') . ' > ' . VIDEOS_adminText('text_65748d98f850') . '.';
    }
    return $prefix . ' ' . VIDEOS_adminText('text_ed0d19fdd3ad');
}

function videos_actions_test_search($bootstrap, $query, $configuration)
{
    $store = $bootstrap->getStore();
    $service = new Videos_YouTubeService(
        new Videos_YouTubeClient(
            $bootstrap->getYouTubeApiKey(),
            isset($configuration['youtube_timeout']) ? $configuration['youtube_timeout'] : 8
        ),
        new Videos_Cache($store),
        new Videos_Quota($store),
        new Videos_Logger($store)
    );
    return $service->find($query, videos_actions_search_parameters($configuration));
}

function videos_actions_seed_discovery($bootstrap, $query, $configuration)
{
    $store = $bootstrap->getStore();
    $cache = new Videos_Cache($store);
    $service = new Videos_YouTubeService(
        new Videos_YouTubeClient(
            $bootstrap->getYouTubeApiKey(),
            isset($configuration['youtube_timeout']) ? $configuration['youtube_timeout'] : 8
        ),
        $cache,
        new Videos_Quota($store),
        new Videos_Logger($store)
    );
    return (new Videos_DiscoveryReservoir($store, $cache))->refresh(
        $query,
        videos_actions_search_parameters($configuration),
        $configuration,
        $service,
        true
    );
}

function videos_actions_search_parameters($configuration)
{
    return array(
        'max_results' => isset($configuration['youtube_max_results']) ? $configuration['youtube_max_results'] : 20,
        'order' => 'relevance',
        'safe_search' => isset($configuration['youtube_safe_search']) ? $configuration['youtube_safe_search'] : 'moderate',
        'language' => isset($configuration['language']) ? $configuration['language'] : 'fr',
        'region' => isset($configuration['region']) ? $configuration['region'] : 'FR',
        'published_after' => '',
        'category_id' => '',
        'channel_id' => '',
        'daily_search_limit' => isset($configuration['youtube_daily_search_limit']) ? $configuration['youtube_daily_search_limit'] : 20,
        'cache_ttl' => isset($configuration['search_cache_ttl']) ? $configuration['search_cache_ttl'] : 86400,
        'video_cache_ttl' => isset($configuration['video_cache_ttl']) ? $configuration['video_cache_ttl'] : 86400,
        'channel_cache_ttl' => isset($configuration['channel_cache_ttl']) ? $configuration['channel_cache_ttl'] : 604800,
        'availability_cache_ttl' => isset($configuration['availability_cache_ttl']) ? $configuration['availability_cache_ttl'] : 86400,
        'blocked_videos' => isset($configuration['blocked_videos']) ? $configuration['blocked_videos'] : '',
        'blocked_channels' => isset($configuration['blocked_channels']) ? $configuration['blocked_channels'] : '',
        'allowed_channels' => isset($configuration['allowed_channels']) ? $configuration['allowed_channels'] : '',
        'minimum_duration' => 0,
        'maximum_duration' => 0,
        'exclude_short_videos' => !empty($configuration['exclude_short_videos']) ? 1 : 0,
        'short_filter_mode' => isset($configuration['short_filter_mode']) ? $configuration['short_filter_mode'] : 'probable',
        'short_max_duration' => isset($configuration['short_max_duration']) ? $configuration['short_max_duration'] : 180
    );
}

function videos_admin_public_urls($store, $cache, $pool, $moderation, $ranking, $configuration)
{
    $ids = array(
        'catalogue' => true,
        'rankings:videos' => true,
        'rankings:channels' => true
    );
    $reservoirVideos = (new Videos_DiscoveryReservoir($store, $cache))->videos($configuration);
    if (is_array($reservoirVideos)) {
        foreach ($reservoirVideos as $videoId => $video) {
            if (videos_admin_video_is_public($videoId, $video, $cache, $moderation, $configuration)) {
                $ids[$videoId] = true;
            }
        }
    }
    foreach ($ranking->getGlobal(500) as $videoId => $item) {
        $video = $cache->getVideo($videoId, true);
        if (videos_admin_video_is_public($videoId, $video, $cache, $moderation, $configuration)) {
            $ids[$videoId] = true;
        }
    }
    $records = $pool->records();
    foreach (isset($records['items']) ? $records['items'] : array() as $videoId => $item) {
        $video = $cache->getVideo($videoId, true);
        if (videos_admin_video_is_public($videoId, $video, $cache, $moderation, $configuration)) {
            $ids[$videoId] = true;
        }
    }
    foreach ((new Videos_ChannelRanking($store, $cache))->getGlobal(250) as $channelId => $item) {
        if (Videos_Validator::youtubeChannelId($channelId) && !$moderation->isChannelExcluded($channelId) &&
            isset($item['video_count']) && (int) $item['video_count'] >= 2) {
            $ids['channel:' . $channelId] = true;
        }
    }
    foreach ($moderation->getPriorityChannelIds(500) as $channelId) {
        if (Videos_Validator::youtubeChannelId($channelId) && !$moderation->isChannelExcluded($channelId)) {
            $ids['channel:' . $channelId] = true;
        }
    }
    $urls = array();
    foreach (array_keys($ids) as $id) {
        $url = plugin_idtourl_videos('', $id);
        if ($url !== '') {
            $urls[$url] = true;
        }
    }
    return array_keys($urls);
}

function videos_admin_video_is_public($videoId, $video, $cache, $moderation, $configuration)
{
    if (!Videos_Validator::youtubeVideoId($videoId) || !is_array($video) ||
        $cache->isVideoUnavailable($videoId) || $moderation->isVideoBlocked($videoId)) {
        return false;
    }
    $channelId = isset($video['snippet']['channelId']) ? (string) $video['snippet']['channelId'] : '';
    return !$moderation->isChannelExcluded($channelId)
        && !Videos_VideoPolicy::excludesShortVideo($video, $configuration);
}

function videos_admin_extract_video_id($input)
{
    $input = trim((string) $input);
    if (Videos_Validator::youtubeVideoId($input)) {
        return $input;
    }
    $parts = parse_url($input);
    if (!is_array($parts)) {
        return '';
    }
    $host = isset($parts['host']) ? strtolower($parts['host']) : '';
    $path = isset($parts['path']) ? trim($parts['path'], '/') : '';
    if ($host === 'youtu.be') {
        $candidate = strtok($path, '/');
        return Videos_Validator::youtubeVideoId($candidate) ? $candidate : '';
    }
    if (strpos($host, 'youtube.com') !== false) {
        $query = array();
        if (!empty($parts['query'])) {
            parse_str($parts['query'], $query);
        }
        if (!empty($query['v']) && Videos_Validator::youtubeVideoId($query['v'])) {
            return $query['v'];
        }
        $segments = explode('/', $path);
        if (count($segments) >= 2 && in_array($segments[0], array('shorts', 'embed'), true) &&
            Videos_Validator::youtubeVideoId($segments[1])) {
            return $segments[1];
        }
    }
    return '';
}

function videos_admin_fetch_single_video($bootstrap, $cache, $videoId, $configuration)
{
    $quota = new Videos_Quota($bootstrap->getStore());
    if (!$quota->reserve('videos', 500)) {
        return false;
    }
    $client = new Videos_YouTubeClient(
        $bootstrap->getYouTubeApiKey(),
        isset($configuration['youtube_timeout']) ? $configuration['youtube_timeout'] : 8
    );
    $videos = $client->videos(array($videoId));
    if (!is_array($videos) || !isset($videos[$videoId])) {
        return false;
    }
    $video = $videos[$videoId];
    $status = isset($video['status']) ? $video['status'] : array();
    if (empty($status['embeddable']) || !isset($status['privacyStatus']) ||
        $status['privacyStatus'] !== 'public' || Videos_VideoPolicy::excludesShortVideo($video, $configuration)) {
        return false;
    }
    if (isset($video['contentDetails']['duration'])) {
        $video['videos_duration_seconds'] = videos_admin_duration_seconds($video['contentDetails']['duration']);
    }
    $ttl = isset($configuration['video_cache_ttl']) ? (int) $configuration['video_cache_ttl'] : 86400;
    if (!$cache->putVideo($videoId, $video, $ttl, 31536000)) {
        return false;
    }
    $cache->putAvailability(
        $videoId,
        true,
        'available',
        isset($configuration['availability_cache_ttl']) ? (int) $configuration['availability_cache_ttl'] : 86400
    );
    $channelId = isset($video['snippet']['channelId']) ? $video['snippet']['channelId'] : '';
    if (Videos_Validator::youtubeChannelId($channelId) && $quota->reserve('channels', 500)) {
        $channels = $client->channels(array($channelId));
        if (is_array($channels) && isset($channels[$channelId])) {
            $cache->putChannel(
                $channelId,
                $channels[$channelId],
                isset($configuration['channel_cache_ttl']) ? (int) $configuration['channel_cache_ttl'] : 604800,
                5184000
            );
        }
    }
    $quota->recordSuccess();
    return $video;
}

function videos_admin_duration_seconds($duration)
{
    if (!preg_match('/^P(?:(\\d+)D)?T?(?:(\\d+)H)?(?:(\\d+)M)?(?:(\\d+)S)?$/', (string) $duration, $m)) {
        return 0;
    }
    return (isset($m[1]) ? (int) $m[1] * 86400 : 0)
        + (isset($m[2]) ? (int) $m[2] * 3600 : 0)
        + (isset($m[3]) ? (int) $m[3] * 60 : 0)
        + (isset($m[4]) ? (int) $m[4] : 0);
}

function videos_admin_action_form($action, $videoId, $label, $token)
{
    return '<form class="videos-inline-form" method="post"><input type="hidden" name="videos_action" value="'
        . htmlspecialchars($action, ENT_QUOTES, 'UTF-8') . '"><input type="hidden" name="video_id" value="'
        . htmlspecialchars($videoId, ENT_QUOTES, 'UTF-8') . '"><input type="hidden" name="' . CSRF_TOKEN
        . '" value="' . htmlspecialchars($token, ENT_QUOTES, 'UTF-8') . '"><button type="submit">'
        . htmlspecialchars($label, ENT_QUOTES, 'UTF-8') . '</button></form>';
}

