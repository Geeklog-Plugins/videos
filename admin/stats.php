<?php

require_once '../../../lib-common.php';

if (!SEC_hasRights('videos.admin')) {
    echo COM_createHTMLDocument(
        COM_showMessageText($LANG_VIDEOS['access_denied'], '', true),
        array(
            'pagetitle' => $LANG_VIDEOS['admin_title'],
            'headercode' => VIDEOS_adminHeaderCode()
        )
    );
    exit;
}

$bootstrap = new Videos_Bootstrap($_CONF);
if (!$bootstrap->isReady()) {
    echo COM_createHTMLDocument(
        COM_showMessageText($LANG_VIDEOS['admin_videos_plugin_storage_unavailable'], '', true),
        array(
            'pagetitle' => $LANG_VIDEOS['admin_title'],
            'headercode' => VIDEOS_adminHeaderCode()
        )
    );
    exit;
}

$store = $bootstrap->getStore();
$cache = new Videos_Cache($store);
$quota = new Videos_Quota($store);
$quotaStatus = $quota->status();
$quotaData = isset($quotaStatus['data']) && is_array($quotaStatus['data'])
    ? $quotaStatus['data'] : array();
$counts = isset($quotaData['counts']) && is_array($quotaData['counts'])
    ? $quotaData['counts'] : array();
$searchLimit = isset($_VIDEOS_CONF['youtube_daily_search_limit'])
    ? max(0, (int) $_VIDEOS_CONF['youtube_daily_search_limit']) : 20;
$searchCount = isset($counts['search']) ? (int) $counts['search'] : 0;
$localSearchState = ($searchLimit > 0 && $searchCount >= $searchLimit)
    ? $LANG_VIDEOS['admin_limit_reached'] . $searchCount . '/' . $searchLimit . ')'
    : $searchCount . '/' . ($searchLimit > 0 ? $searchLimit : '∞');
$lastApiError = !empty($quotaData['last_error']['code'])
    ? (string) $quotaData['last_error']['code'] : $LANG_VIDEOS['admin_none'];
$lastApiErrorAt = !empty($quotaData['last_error']['at'])
    ? videos_stats_date_text($quotaData['last_error']['at']) : $LANG_VIDEOS['admin_never'];
$lastRejection = isset($quotaData['last_rejection']) && is_array($quotaData['last_rejection'])
    ? $quotaData['last_rejection'] : array();
$cacheStatus = (new Videos_CacheMaintenance($store))->inspect();
$reservoirStatus = (new Videos_DiscoveryReservoir($store, $cache))->status();
$pool = new Videos_PermanentPool($store, $cache);
$poolStatus = $pool->status();
$ranking = new Videos_Ranking(
    $store,
    new Videos_RatingStats($store),
    new Videos_VideoStats($store),
    $cache
);
$videoRanking = $ranking->getGlobal(500);
$channelRanking = (new Videos_ChannelRanking($store, $cache))->getGlobal(250);
$moderation = new Videos_Moderation($store);
$priorityChannels = $moderation->getPriorityChannelIds(500);
$searchService = VIDEOS_getSearchService($bootstrap);
$searchableCount = $searchService === false
    ? 0 : count($searchService->inventory(1000));

$seoDiagnostic = '';
$seoDiagnosticVideoId = '';
if (count($videoRanking) > 0) {
    $rankingIds = array_keys($videoRanking);
    $seoDiagnosticVideoId = reset($rankingIds);
    $seoDiagnosticVideo = $cache->getVideo($seoDiagnosticVideoId, true);
    if (is_array($seoDiagnosticVideo)) {
        $descriptionService = new Videos_Description();
        $description = $descriptionService->excerpt(
            isset($seoDiagnosticVideo['snippet']['description'])
                ? $seoDiagnosticVideo['snippet']['description'] : '',
            isset($_VIDEOS_CONF['description_mode'])
                ? $_VIDEOS_CONF['description_mode'] : 'clean'
        );
        $seo = new Videos_Seo(
            $_CONF['site_url'],
            isset($_CONF['site_name']) ? $_CONF['site_name'] : '',
            $_VIDEOS_CONF
        );
        $embedHost = !empty($_VIDEOS_CONF['privacy_enhanced_embed'])
            ? 'https://www.youtube-nocookie.com' : 'https://www.youtube.com';
        $seoDiagnostic = $seo->video(
            $seoDiagnosticVideoId,
            $seoDiagnosticVideo,
            $description,
            $embedHost . '/embed/' . rawurlencode($seoDiagnosticVideoId)
        );
    }
}

$html = VIDEOS_adminPageOpen('stats', $LANG_VIDEOS['admin_nav_stats'])
    . '<section class="videos-admin-section"><h2>' . $LANG_VIDEOS['admin_public_editorial_content'] . '</h2>'
    . '<div class="videos-stat-grid">'
    . videos_stat_card(
        $LANG_VIDEOS['admin_videos_reservoir'],
        (int) $reservoirStatus['item_count'],
        $LANG_VIDEOS['admin_local_discovery_corpus']
    )
    . videos_stat_card(
        $LANG_VIDEOS['admin_searchable_videos'],
        $searchableCount,
        $LANG_VIDEOS['admin_public_corpus_used_by_geeklog_catalogue']
    )
    . videos_stat_card(
        $LANG_VIDEOS['admin_global_ranking'],
        count($videoRanking),
        $LANG_VIDEOS['admin_videos_with_local_signals']
    )
    . videos_stat_card(
        $LANG_VIDEOS['admin_ranked_channels'],
        count($channelRanking),
        $LANG_VIDEOS['admin_channels_from_local_ranking']
    )
    . videos_stat_card(
        $LANG_VIDEOS['admin_priority_channels'],
        count($priorityChannels),
        $LANG_VIDEOS['admin_active_editorial_decisions']
    )
    . videos_stat_card(
        $LANG_VIDEOS['admin_permanent_catalogue'],
        (int) $poolStatus['item_count'],
        $LANG_VIDEOS['admin_videos_retained_permanently']
    )
    . videos_stat_card(
        $LANG_VIDEOS['admin_pinned_videos'],
        isset($poolStatus['pinned_count'])
            ? (int) $poolStatus['pinned_count'] : 0,
        $LANG_VIDEOS['admin_strong_selections_including_legacy_0_17_pins']
    )
    . videos_stat_card(
        $LANG_VIDEOS['admin_excluded_from_pool'],
        (int) $poolStatus['excluded_count'],
        $LANG_VIDEOS['admin_explicit_editorial_exclusions']
    )
    . '</div></section>';

$html .= '<section class="videos-admin-section"><h2>' . $LANG_VIDEOS['admin_youtube_api_activity'] . '</h2>'
    . '<div class="videos-stat-grid videos-stat-grid-compact">'
    . videos_stat_card(
        $LANG_VIDEOS['admin_searches_today'],
        isset($counts['search']) ? (int) $counts['search'] : 0,
        $LANG_VIDEOS['admin_search_list_calls']
    )
    . videos_stat_card(
        $LANG_VIDEOS['admin_video_calls'],
        isset($counts['videos']) ? (int) $counts['videos'] : 0,
        $LANG_VIDEOS['admin_videos_list_details']
    )
    . videos_stat_card(
        $LANG_VIDEOS['admin_channel_calls'],
        isset($counts['channels']) ? (int) $counts['channels'] : 0,
        $LANG_VIDEOS['admin_channels_list_details']
    )
    . videos_stat_card(
        $LANG_VIDEOS['admin_quota_suspended'],
        !empty($quotaData['suspended']) ? $LANG_VIDEOS['admin_yes'] : $LANG_VIDEOS['admin_no'],
        $LANG_VIDEOS['admin_suspension_after_quota_error_reported_by_youtube']
    )
    . videos_stat_card(
        $LANG_VIDEOS['admin_local_search_limit'],
        $localSearchState,
        $LANG_VIDEOS['admin_daily_limit_configured_videos']
    )
    . videos_stat_card(
        $LANG_VIDEOS['admin_last_search'],
        videos_stats_date_text(isset($quotaData['last_search_at']) ? $quotaData['last_search_at'] : null),
        $LANG_VIDEOS['admin_last_authorized_search_list_reservation'],
        true
    )
    . videos_stat_card(
        $LANG_VIDEOS['admin_last_api_error'],
        $lastApiError,
        $lastApiErrorAt,
        true
    )
    . videos_stat_card(
        $LANG_VIDEOS['admin_last_success'],
        videos_stats_date_text(
            isset($quotaData['last_success_at'])
                ? $quotaData['last_success_at'] : null
        ),
        $LANG_VIDEOS['admin_last_valid_api_response'],
        true
    )
    . '</div>';
if (!empty($lastRejection)) {
    $reason = isset($lastRejection['reason']) ? (string) $lastRejection['reason'] : '';
    $method = isset($lastRejection['method']) ? (string) $lastRejection['method'] : '';
    $count = isset($lastRejection['count']) ? (int) $lastRejection['count'] : 0;
    $limit = isset($lastRejection['limit']) ? (int) $lastRejection['limit'] : 0;
    $at = !empty($lastRejection['at']) ? videos_stats_date_text($lastRejection['at']) : $LANG_VIDEOS['admin_never'];
    $html .= '<p class="videos-admin-help"><strong>' . $LANG_VIDEOS['admin_last_rejected_call'] . '</strong> '
        . htmlspecialchars($method, ENT_QUOTES, 'UTF-8') . ' — '
        . htmlspecialchars($reason, ENT_QUOTES, 'UTF-8') . ' (' . $count . '/' . $limit . ') — '
        . htmlspecialchars($at, ENT_QUOTES, 'UTF-8') . '.</p>';
}
$html .= '</section>';

$cacheLabels = array(
    'search' => $LANG_VIDEOS['admin_search_results'],
    'videos' => $LANG_VIDEOS['admin_video_information'],
    'channels' => $LANG_VIDEOS['admin_channel_information'],
    'availability' => $LANG_VIDEOS['admin_availability_checks']
);
$html .= '<section class="videos-admin-section"><h2>' . $LANG_VIDEOS['admin_cache_label'] . '</h2>'
    . '<div class="videos-admin-table-wrap">'
    . '<table class="admin-list videos-admin-table"><thead><tr>'
    . '<th>' . $LANG_VIDEOS['admin_cache_label'] . '</th><th>' . $LANG_VIDEOS['admin_entries'] . '</th><th>' . $LANG_VIDEOS['admin_size'] . '</th>'
    . '<th>' . $LANG_VIDEOS['admin_latest_entry'] . '</th></tr></thead><tbody>';
foreach ($cacheLabels as $scope => $label) {
    $item = isset($cacheStatus[$scope]) ? $cacheStatus[$scope] : array();
    $html .= '<tr><td>' . htmlspecialchars($label, ENT_QUOTES, 'UTF-8')
        . '</td><td>' . (isset($item['entries']) ? (int) $item['entries'] : 0)
        . '</td><td>'
        . videos_stats_bytes(isset($item['bytes']) ? $item['bytes'] : 0)
        . '</td><td>'
        . videos_stats_date(isset($item['latest_at']) ? $item['latest_at'] : null)
        . '</td></tr>';
}
$html .= '</tbody></table></div></section>';

$html .= '<section class="videos-admin-section"><h2>' . $LANG_VIDEOS['admin_geeklog_integration'] . '</h2>'
    . '<div class="videos-integration-grid">'
    . '<div><strong>' . $LANG_VIDEOS['admin_geeklog_search'] . '</strong><span>' . $LANG_VIDEOS['admin_active_d2f1'] . '</span></div>'
    . '<div><strong>' . $LANG_VIDEOS['admin_geeklog_statistics'] . '</strong><span>' . $LANG_VIDEOS['admin_active_1a31'] . '</span></div>'
    . '<div><strong>' . $LANG_VIDEOS['admin_catalogue_search'] . '</strong><span>' . $LANG_VIDEOS['admin_active_d2f1'] . '</span></div>'
    . '<div><strong>' . $LANG_VIDEOS['admin_iteminfo_interoperability'] . '</strong><span>' . $LANG_VIDEOS['admin_active_d2f1'] . '</span></div>'
    . '<div><strong>IndexNow</strong><span>'
    . (function_exists('send_to_indexnow') ? $LANG_VIDEOS['admin_available'] : $LANG_VIDEOS['admin_unavailable'])
    . '</span></div>'
    . '</div>'
    . '<details class="videos-advanced-field"><summary>' . $LANG_VIDEOS['admin_developer_information'] . '</summary>'
    . '<p><code>plugin_searchtypes_videos()</code> · <code>plugin_dopluginsearch_videos()</code><br>'
    . '<code>plugin_statssummary_videos()</code> · <code>plugin_showstats_videos()</code><br>'
    . '<code>plugin_getiteminfo_videos()</code> · <code>plugin_idtourl_videos()</code></p>'
    . '</details></section>';

$html .= '<section class="videos-admin-section"><h2>' . $LANG_VIDEOS['admin_video_seo_c7b7'] . '</h2>';
if ($seoDiagnostic !== '') {
    $checks = array(
        'canonical' => strpos($seoDiagnostic, 'rel="canonical"') !== false,
        'meta description' => strpos($seoDiagnostic, 'name="description"') !== false,
        'Open Graph' => strpos($seoDiagnostic, 'property="og:') !== false,
        'VideoObject' => strpos($seoDiagnostic, '"@type":"VideoObject"') !== false,
        'thumbnailUrl' => strpos($seoDiagnostic, '"thumbnailUrl"') !== false,
        'uploadDate' => strpos($seoDiagnostic, '"uploadDate"') !== false,
        'embedUrl' => strpos($seoDiagnostic, '"embedUrl"') !== false
    );
    $allOk = !in_array(false, $checks, true);
    $html .= '<p><strong>' . $LANG_VIDEOS['admin_video_seo'] . ($allOk ? $LANG_VIDEOS['admin_status_ok'] : $LANG_VIDEOS['admin_needs_review']) . '</strong></p>'
        . '<ul class="videos-admin-status">';
    foreach ($checks as $label => $ok) {
        $html .= '<li>' . htmlspecialchars($label, ENT_QUOTES, 'UTF-8') . ' : '
            . ($ok ? $LANG_VIDEOS['admin_status_ok'] : $LANG_VIDEOS['admin_needs_review']) . '</li>';
    }
    $html .= '</ul><details class="videos-advanced-field"><summary>' . $LANG_VIDEOS['admin_technical_diagnostics'] . '</summary>'
        . '<p>' . $LANG_VIDEOS['admin_test_video'] . ' : <code>'
        . htmlspecialchars($seoDiagnosticVideoId, ENT_QUOTES, 'UTF-8')
        . '</code></p><pre class="videos-seo-preview"><code>'
        . htmlspecialchars($seoDiagnostic, ENT_QUOTES, 'UTF-8')
        . '</code></pre></details>';
} else {
    $html .= '<p>' . $LANG_VIDEOS['admin_no_video_available_seo_diagnostics'] . '</p>';
}
$html .= '</section>';

$html .= VIDEOS_adminPageClose();


echo COM_createHTMLDocument(
    $html,
    array(
        'pagetitle' => $LANG_VIDEOS['admin_title'],
        'headercode' => VIDEOS_adminHeaderCode()
    )
);

function videos_stat_card($label, $value, $detail, $smallValue = false)
{
    return '<article class="videos-stat-card">'
        . '<span class="videos-stat-value'
        . ($smallValue ? ' is-small' : '') . '">'
        . htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8')
        . '</span><strong class="videos-stat-label">'
        . htmlspecialchars($label, ENT_QUOTES, 'UTF-8')
        . '</strong><span class="videos-stat-detail">'
        . htmlspecialchars($detail, ENT_QUOTES, 'UTF-8')
        . '</span></article>';
}

function videos_stats_date_text($value)
{
    if (empty($value)) {
        return $LANG_VIDEOS['admin_never'];
    }
    $timestamp = strtotime((string) $value);
    if ($timestamp !== false && function_exists('COM_getUserDateTimeFormat')) {
        $formatted = COM_getUserDateTimeFormat($timestamp);
        if (is_array($formatted) && isset($formatted[0])) {
            return $formatted[0];
        }
    }
    return (string) $value;
}

function videos_stats_date($value)
{
    return htmlspecialchars(videos_stats_date_text($value), ENT_QUOTES, 'UTF-8');
}

function videos_stats_bytes($bytes)
{
    $bytes = max(0, (int) $bytes);
    if ($bytes >= 1048576) {
        return number_format($bytes / 1048576, 2, ',', ' ') . ' MiB';
    }
    if ($bytes >= 1024) {
        return number_format($bytes / 1024, 1, ',', ' ') . ' KiB';
    }
    return $bytes . ' ' . $GLOBALS['LANG_VIDEOS']['admin_byte_unit'];
}
