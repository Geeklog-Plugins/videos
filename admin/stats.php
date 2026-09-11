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
        COM_showMessageText(VIDEOS_localizeAdminText(VIDEOS_adminText('text_ad4bee7e9ff4')), '', true),
        array(
            'pagetitle' => VIDEOS_localizeAdminText(VIDEOS_adminText('text_695c8c330b8f')),
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
    ? VIDEOS_adminText('text_721e1db12433') . $searchCount . '/' . $searchLimit . ')'
    : $searchCount . '/' . ($searchLimit > 0 ? $searchLimit : '∞');
$lastApiError = !empty($quotaData['last_error']['code'])
    ? (string) $quotaData['last_error']['code'] : VIDEOS_adminText('text_a54f1197e621');
$lastApiErrorAt = !empty($quotaData['last_error']['at'])
    ? videos_stats_date_text($quotaData['last_error']['at']) : VIDEOS_adminText('text_55532ba13b84');
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
    . '<section class="videos-admin-section"><h2>' . VIDEOS_adminText('text_50dbebed149b') . '</h2>'
    . '<div class="videos-stat-grid">'
    . videos_stat_card(
        VIDEOS_adminText('text_5793dbff4b44'),
        (int) $reservoirStatus['item_count'],
        VIDEOS_adminText('text_be9104ffe37e')
    )
    . videos_stat_card(
        VIDEOS_adminText('text_ad66e72e75ec'),
        $searchableCount,
        VIDEOS_adminText('text_36868dbab548')
    )
    . videos_stat_card(
        VIDEOS_adminText('text_b3d4e15e39bf'),
        count($videoRanking),
        VIDEOS_adminText('text_4bf56ef2cc6c')
    )
    . videos_stat_card(
        VIDEOS_adminText('text_b9bef52d4aa3'),
        count($channelRanking),
        VIDEOS_adminText('text_d50b0123f1de')
    )
    . videos_stat_card(
        VIDEOS_adminText('text_16bb358770de'),
        count($priorityChannels),
        VIDEOS_adminText('text_b83fc7e45471')
    )
    . videos_stat_card(
        VIDEOS_adminText('text_3696c9b66256'),
        (int) $poolStatus['item_count'],
        VIDEOS_adminText('text_184e3abfc03a')
    )
    . videos_stat_card(
        VIDEOS_adminText('text_4072238c8cc7'),
        isset($poolStatus['pinned_count'])
            ? (int) $poolStatus['pinned_count'] : 0,
        VIDEOS_adminText('text_7d57e2bb142d')
    )
    . videos_stat_card(
        VIDEOS_adminText('text_f10feb2274ea'),
        (int) $poolStatus['excluded_count'],
        VIDEOS_adminText('text_007f0fff6df3')
    )
    . '</div></section>';

$html .= '<section class="videos-admin-section"><h2>' . VIDEOS_adminText('text_65748d98f850') . '</h2>'
    . '<div class="videos-stat-grid videos-stat-grid-compact">'
    . videos_stat_card(
        VIDEOS_adminText('text_696467c95b05'),
        isset($counts['search']) ? (int) $counts['search'] : 0,
        VIDEOS_adminText('text_7cd3fe26311e')
    )
    . videos_stat_card(
        VIDEOS_adminText('text_5e2d327ccd27'),
        isset($counts['videos']) ? (int) $counts['videos'] : 0,
        VIDEOS_adminText('text_01c816c87508')
    )
    . videos_stat_card(
        VIDEOS_adminText('text_801bc4757eb1'),
        isset($counts['channels']) ? (int) $counts['channels'] : 0,
        VIDEOS_adminText('text_c4620a96701e')
    )
    . videos_stat_card(
        VIDEOS_adminText('text_229f2b1943a8'),
        !empty($quotaData['suspended']) ? 'Oui' : 'Non',
        VIDEOS_adminText('text_a715911d13d4')
    )
    . videos_stat_card(
        VIDEOS_adminText('text_27ff1cce04c6'),
        $localSearchState,
        VIDEOS_adminText('text_fee22e6ee72b')
    )
    . videos_stat_card(
        VIDEOS_adminText('text_2dc02504e6fd'),
        videos_stats_date_text(isset($quotaData['last_search_at']) ? $quotaData['last_search_at'] : null),
        VIDEOS_adminText('text_7a78c348afb3'),
        true
    )
    . videos_stat_card(
        VIDEOS_adminText('text_16e8b06800bc'),
        $lastApiError,
        $lastApiErrorAt,
        true
    )
    . videos_stat_card(
        VIDEOS_adminText('text_160478e94434'),
        videos_stats_date_text(
            isset($quotaData['last_success_at'])
                ? $quotaData['last_success_at'] : null
        ),
        VIDEOS_adminText('text_4bdda76e1cba'),
        true
    )
    . '</div>';
if (!empty($lastRejection)) {
    $reason = isset($lastRejection['reason']) ? (string) $lastRejection['reason'] : '';
    $method = isset($lastRejection['method']) ? (string) $lastRejection['method'] : '';
    $count = isset($lastRejection['count']) ? (int) $lastRejection['count'] : 0;
    $limit = isset($lastRejection['limit']) ? (int) $lastRejection['limit'] : 0;
    $at = !empty($lastRejection['at']) ? videos_stats_date_text($lastRejection['at']) : VIDEOS_adminText('text_55532ba13b84');
    $html .= '<p class="videos-admin-help"><strong>' . VIDEOS_adminText('text_41059bb643de') . '</strong> '
        . htmlspecialchars($method, ENT_QUOTES, 'UTF-8') . ' — '
        . htmlspecialchars($reason, ENT_QUOTES, 'UTF-8') . ' (' . $count . '/' . $limit . ') — '
        . htmlspecialchars($at, ENT_QUOTES, 'UTF-8') . '.</p>';
}
$html .= '</section>';

$cacheLabels = array(
    'search' => VIDEOS_adminText('text_5335de22db2d'),
    'videos' => VIDEOS_adminText('text_6d32810e19b0'),
    'channels' => VIDEOS_adminText('text_13849355df98'),
    'availability' => VIDEOS_adminText('text_412bbe5e0703')
);
$html .= '<section class="videos-admin-section"><h2>Cache</h2>'
    . '<div class="videos-admin-table-wrap">'
    . '<table class="admin-list videos-admin-table"><thead><tr>'
    . '<th>Cache</th><th>' . VIDEOS_adminText('text_f41ca442a8d6') . '</th><th>' . VIDEOS_adminText('text_3b18e8e33250') . '</th>'
    . '<th>' . VIDEOS_adminText('text_fcbf6472e074') . '</th></tr></thead><tbody>';
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

$html .= '<section class="videos-admin-section"><h2>' . VIDEOS_adminText('text_21f56c736dd7') . '</h2>'
    . '<div class="videos-integration-grid">'
    . '<div><strong>' . VIDEOS_adminText('text_1fac67f04852') . '</strong><span>' . VIDEOS_adminText('text_a733b809d2f1') . '</span></div>'
    . '<div><strong>' . VIDEOS_adminText('text_f31987d233f9') . '</strong><span>' . VIDEOS_adminText('text_f13a33c01a31') . '</span></div>'
    . '<div><strong>' . VIDEOS_adminText('text_1ee065b38a78') . '</strong><span>' . VIDEOS_adminText('text_a733b809d2f1') . '</span></div>'
    . '<div><strong>' . VIDEOS_adminText('text_1c2d095511c3') . '</strong><span>' . VIDEOS_adminText('text_a733b809d2f1') . '</span></div>'
    . '<div><strong>IndexNow</strong><span>'
    . (function_exists('send_to_indexnow') ? VIDEOS_adminText('text_b9cb1c7d82fc') : VIDEOS_adminText('text_7cc7897d5382'))
    . '</span></div>'
    . '</div>'
    . '<details class="videos-advanced-field"><summary>' . VIDEOS_adminText('text_804661ad2853') . '</summary>'
    . '<p><code>plugin_searchtypes_videos()</code> · <code>plugin_dopluginsearch_videos()</code><br>'
    . '<code>plugin_statssummary_videos()</code> · <code>plugin_showstats_videos()</code><br>'
    . '<code>plugin_getiteminfo_videos()</code> · <code>plugin_idtourl_videos()</code></p>'
    . '</details></section>';

$html .= '<section class="videos-admin-section"><h2>' . VIDEOS_adminText('text_ae7afc95c7b7') . '</h2>';
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
    $html .= '<p><strong>' . VIDEOS_adminText('text_07b942be7aef') . ($allOk ? 'OK' : VIDEOS_adminText('text_994b6ba1c20e')) . '</strong></p>'
        . '<ul class="videos-admin-status">';
    foreach ($checks as $label => $ok) {
        $html .= '<li>' . htmlspecialchars($label, ENT_QUOTES, 'UTF-8') . ' : '
            . ($ok ? 'OK' : VIDEOS_adminText('text_994b6ba1c20e')) . '</li>';
    }
    $html .= '</ul><details class="videos-advanced-field"><summary>' . VIDEOS_adminText('text_605c3eed118a') . '</summary>'
        . '<p>' . VIDEOS_adminText('text_a5d43ef5c22a') . ' : <code>'
        . htmlspecialchars($seoDiagnosticVideoId, ENT_QUOTES, 'UTF-8')
        . '</code></p><pre class="videos-seo-preview"><code>'
        . htmlspecialchars($seoDiagnostic, ENT_QUOTES, 'UTF-8')
        . '</code></pre></details>';
} else {
    $html .= '<p>' . VIDEOS_adminText('text_5e1f9ce9c113') . '</p>';
}
$html .= '</section>';

$html .= '</div>';


echo COM_createHTMLDocument(
    $html,
    array(
        'pagetitle' => VIDEOS_localizeAdminText(VIDEOS_adminText('text_695c8c330b8f')),
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
        return VIDEOS_localizeAdminText(VIDEOS_adminText('text_55532ba13b84'));
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
    return $bytes . ' o';
}
