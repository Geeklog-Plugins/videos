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
        COM_showMessageText(VIDEOS_localizeAdminText(VIDEOS_adminText('text_f926975ff5ae')), '', true),
        array(
            'pagetitle' => VIDEOS_localizeAdminText(VIDEOS_adminText('text_393e33275de2')),
            'headercode' => videos_stats_header_code()
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
    ? VIDEOS_adminText('text_aac4cbbb7750') . $searchCount . '/' . $searchLimit . ')'
    : $searchCount . '/' . ($searchLimit > 0 ? $searchLimit : '∞');
$lastApiError = !empty($quotaData['last_error']['code'])
    ? (string) $quotaData['last_error']['code'] : VIDEOS_adminText('text_e8f882731020');
$lastApiErrorAt = !empty($quotaData['last_error']['at'])
    ? videos_stats_date_text($quotaData['last_error']['at']) : VIDEOS_adminText('text_f405e1be2c0b');
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

$html = '<div class="videos-admin"><h1>Videos — Statistiques</h1>'
    . videos_stats_nav($_CONF, 'stats')
    . '<section class="videos-admin-section"><h2>Contenu public et éditorial</h2>'
    . '<div class="videos-stat-grid">'
    . videos_stat_card(
        VIDEOS_adminText('text_dbbf2cab55a3'),
        (int) $reservoirStatus['item_count'],
        VIDEOS_adminText('text_649c4e2a34b4')
    )
    . videos_stat_card(
        VIDEOS_adminText('text_b5e9f7c792b8'),
        $searchableCount,
        VIDEOS_adminText('text_e63824e28e9b')
    )
    . videos_stat_card(
        VIDEOS_adminText('text_64bd63001af0'),
        count($videoRanking),
        VIDEOS_adminText('text_0922e16705e8')
    )
    . videos_stat_card(
        VIDEOS_adminText('text_572e3a5c767b'),
        count($channelRanking),
        VIDEOS_adminText('text_2e40419d8330')
    )
    . videos_stat_card(
        VIDEOS_adminText('text_a9ddd7f81604'),
        count($priorityChannels),
        VIDEOS_adminText('text_ea2026883024')
    )
    . videos_stat_card(
        VIDEOS_adminText('text_51165f608ee6'),
        (int) $poolStatus['item_count'],
        VIDEOS_adminText('text_ead1b5b2c2f5')
    )
    . videos_stat_card(
        VIDEOS_adminText('text_5cb68c9efefa'),
        isset($poolStatus['pinned_count'])
            ? (int) $poolStatus['pinned_count'] : 0,
        VIDEOS_adminText('text_5b08d73b4141')
    )
    . videos_stat_card(
        VIDEOS_adminText('text_b981b2efb704'),
        (int) $poolStatus['excluded_count'],
        VIDEOS_adminText('text_bcad23db728a')
    )
    . '</div></section>';

$html .= '<section class="videos-admin-section"><h2>Activité YouTube API</h2>'
    . '<div class="videos-stat-grid videos-stat-grid-compact">'
    . videos_stat_card(
        VIDEOS_adminText('text_2949d1643676'),
        isset($counts['search']) ? (int) $counts['search'] : 0,
        VIDEOS_adminText('text_644124230367')
    )
    . videos_stat_card(
        VIDEOS_adminText('text_0e43211db453'),
        isset($counts['videos']) ? (int) $counts['videos'] : 0,
        VIDEOS_adminText('text_fc881aa933f7')
    )
    . videos_stat_card(
        VIDEOS_adminText('text_f6860a7f3208'),
        isset($counts['channels']) ? (int) $counts['channels'] : 0,
        VIDEOS_adminText('text_8d708d5b5ab9')
    )
    . videos_stat_card(
        VIDEOS_adminText('text_1e4054542279'),
        !empty($quotaData['suspended']) ? 'Oui' : 'Non',
        VIDEOS_adminText('text_7a23ec9dd96e')
    )
    . videos_stat_card(
        VIDEOS_adminText('text_13f6925e585e'),
        $localSearchState,
        VIDEOS_adminText('text_f19ef37514a4')
    )
    . videos_stat_card(
        VIDEOS_adminText('text_588a311ad7c1'),
        videos_stats_date_text(isset($quotaData['last_search_at']) ? $quotaData['last_search_at'] : null),
        VIDEOS_adminText('text_7d74e774f489'),
        true
    )
    . videos_stat_card(
        VIDEOS_adminText('text_645610b5bbd9'),
        $lastApiError,
        $lastApiErrorAt,
        true
    )
    . videos_stat_card(
        VIDEOS_adminText('text_3d200e5aa303'),
        videos_stats_date_text(
            isset($quotaData['last_success_at'])
                ? $quotaData['last_success_at'] : null
        ),
        VIDEOS_adminText('text_e75d25ebd666'),
        true
    )
    . '</div>';
if (!empty($lastRejection)) {
    $reason = isset($lastRejection['reason']) ? (string) $lastRejection['reason'] : '';
    $method = isset($lastRejection['method']) ? (string) $lastRejection['method'] : '';
    $count = isset($lastRejection['count']) ? (int) $lastRejection['count'] : 0;
    $limit = isset($lastRejection['limit']) ? (int) $lastRejection['limit'] : 0;
    $at = !empty($lastRejection['at']) ? videos_stats_date_text($lastRejection['at']) : VIDEOS_adminText('text_f405e1be2c0b');
    $html .= '<p class="videos-admin-help"><strong>Dernier appel refusé :</strong> '
        . htmlspecialchars($method, ENT_QUOTES, 'UTF-8') . ' — '
        . htmlspecialchars($reason, ENT_QUOTES, 'UTF-8') . ' (' . $count . '/' . $limit . ') — '
        . htmlspecialchars($at, ENT_QUOTES, 'UTF-8') . '.</p>';
}
$html .= '</section>';

$cacheLabels = array(
    'search' => VIDEOS_adminText('text_f9fa293c6948'),
    'videos' => VIDEOS_adminText('text_81cbcff7bdf3'),
    'channels' => VIDEOS_adminText('text_46c3dc1b4ffa'),
    'availability' => VIDEOS_adminText('text_d5bb011aea09')
);
$html .= '<section class="videos-admin-section"><h2>Cache</h2>'
    . '<div class="videos-admin-table-wrap">'
    . '<table class="admin-list videos-admin-table"><thead><tr>'
    . '<th>Cache</th><th>Entrées</th><th>Volume</th>'
    . '<th>Entrée la plus récente</th></tr></thead><tbody>';
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

$html .= '<section class="videos-admin-section"><h2>Intégration Geeklog</h2>'
    . '<div class="videos-integration-grid">'
    . '<div><strong>Recherche Geeklog</strong><span>Active</span></div>'
    . '<div><strong>Statistiques Geeklog</strong><span>Actives</span></div>'
    . '<div><strong>Recherche du catalogue</strong><span>Active</span></div>'
    . '<div><strong>Interopérabilité ItemInfo</strong><span>Active</span></div>'
    . '<div><strong>IndexNow</strong><span>'
    . (function_exists('send_to_indexnow') ? VIDEOS_adminText('text_264396ec6d7c') : VIDEOS_adminText('text_c0498030b601'))
    . '</span></div>'
    . '</div>'
    . '<details class="videos-advanced-field"><summary>Informations développeur</summary>'
    . '<p><code>plugin_searchtypes_videos()</code> · <code>plugin_dopluginsearch_videos()</code><br>'
    . '<code>plugin_statssummary_videos()</code> · <code>plugin_showstats_videos()</code><br>'
    . '<code>plugin_getiteminfo_videos()</code> · <code>plugin_idtourl_videos()</code></p>'
    . '</details></section>';

$html .= '<section class="videos-admin-section"><h2>SEO vidéo</h2>';
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
    $html .= '<p><strong>SEO vidéo : ' . ($allOk ? 'OK' : VIDEOS_adminText('text_8f5f72554dcd')) . '</strong></p>'
        . '<ul class="videos-admin-status">';
    foreach ($checks as $label => $ok) {
        $html .= '<li>' . htmlspecialchars($label, ENT_QUOTES, 'UTF-8') . ' : '
            . ($ok ? 'OK' : VIDEOS_adminText('text_8f5f72554dcd')) . '</li>';
    }
    $html .= '</ul><details class="videos-advanced-field"><summary>Diagnostic technique</summary>'
        . '<p>Vidéo test : <code>'
        . htmlspecialchars($seoDiagnosticVideoId, ENT_QUOTES, 'UTF-8')
        . '</code></p><pre class="videos-seo-preview"><code>'
        . htmlspecialchars($seoDiagnostic, ENT_QUOTES, 'UTF-8')
        . '</code></pre></details>';
} else {
    $html .= '<p>Aucune vidéo disponible pour le diagnostic SEO.</p>';
}
$html .= '</section>';

$html .= '</div>';


echo COM_createHTMLDocument(
    $html,
    array(
        'pagetitle' => VIDEOS_localizeAdminText(VIDEOS_adminText('text_393e33275de2')),
        'headercode' => videos_stats_header_code()
    )
);

function videos_stats_nav($configuration, $active)
{
    global $LANG_VIDEOS;
    $base = $configuration['site_admin_url'] . '/plugins/videos/';
    $items = array(
        'overview' => array('index.php', $LANG_VIDEOS['admin_nav_overview']),
        'actions' => array('actions.php', $LANG_VIDEOS['admin_nav_actions']),
        'stats' => array('stats.php', $LANG_VIDEOS['admin_nav_stats']),
        'moderation' => array('moderation.php', $LANG_VIDEOS['admin_nav_moderation'])
    );
    $html = '<nav class="videos-navigation" aria-label="' . htmlspecialchars($LANG_VIDEOS['admin_navigation'], ENT_QUOTES, 'UTF-8') . '"><ul>';
    foreach ($items as $key => $item) {
        $html .= '<li><a href="'
            . htmlspecialchars($base . $item[0], ENT_QUOTES, 'UTF-8') . '"'
            . ($key === $active
                ? ' class="is-active" aria-current="page"' : '') . '>'
            . htmlspecialchars($item[1], ENT_QUOTES, 'UTF-8') . '</a></li>';
    }
    return $html . '</ul></nav>';
}

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

function videos_stats_header_code()
{
    return VIDEOS_adminHeaderCode() . "\n" . '<style>'
        . '.videos-stat-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:1rem}'
        . '.videos-stat-card{display:flex;flex-direction:column;min-height:110px;padding:1rem;border:1px solid rgba(127,127,127,.28);border-radius:.6rem;background:rgba(127,127,127,.055);box-sizing:border-box}'
        . '.videos-stat-value{font-size:1.8rem;font-weight:700;line-height:1.05;margin-bottom:.45rem;overflow-wrap:anywhere}'
        . '.videos-stat-value.is-small{font-size:1.05rem;line-height:1.3}'
        . '.videos-stat-label{font-size:.95rem;line-height:1.25}'
        . '.videos-stat-detail{margin-top:auto;padding-top:.45rem;font-size:.78rem;line-height:1.3;opacity:.7}'
        . '.videos-integration-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(240px,1fr));gap:1rem}'
        . '.videos-integration-grid>div{display:flex;flex-direction:column;gap:.35rem;padding:1rem;border:1px solid rgba(127,127,127,.25);border-radius:.55rem}'
        . '.videos-integration-grid span{font-size:.88rem;line-height:1.4;opacity:.82}'
        . '</style>';
}

function videos_stats_date_text($value)
{
    if (empty($value)) {
        return VIDEOS_localizeAdminText(VIDEOS_adminText('text_f405e1be2c0b'));
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
