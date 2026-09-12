<?php

require_once '../../../lib-common.php';

$adminHeaderCode = VIDEOS_adminHeaderCode();

if (!SEC_hasRights('videos.admin')) {
    echo COM_createHTMLDocument(
        COM_showMessageText($LANG_VIDEOS['access_denied'], '', true),
        array(
            'pagetitle' => $LANG_VIDEOS['admin_title'],
            'headercode' => $adminHeaderCode
        )
    );
    exit;
}

$bootstrap = new Videos_Bootstrap($_CONF);
$html = VIDEOS_adminPageOpen('overview', $LANG_VIDEOS['admin_nav_overview']);

if (!$bootstrap->isReady()) {
    $html .= '<div class="videos-admin-notice videos-admin-notice-error">'
        . COM_showMessageText(
            $LANG_VIDEOS['admin_videos_plugin_storage_unavailable_use_repair_tools'],
            '',
            true
        )
        . '<p><a class="videos-admin-button" href="'
        . htmlspecialchars(
            $_CONF['site_admin_url'] . '/plugins/videos/repair.php',
            ENT_QUOTES,
            'UTF-8'
        ) . '">' . $LANG_VIDEOS['admin_open_repair_tools'] . '</a></p></div>' . VIDEOS_adminPageClose();
    echo COM_createHTMLDocument(
        $html,
        array(
            'pagetitle' => $LANG_VIDEOS['admin_title'],
            'headercode' => $adminHeaderCode
        )
    );
    exit;
}

$store = $bootstrap->getStore();
$cache = new Videos_Cache($store);
$pool = new Videos_PermanentPool($store, $cache);
$poolStatus = $pool->status();
$reservoirStatus = (new Videos_DiscoveryReservoir($store, $cache))->status();
$ranking = new Videos_Ranking(
    $store,
    new Videos_RatingStats($store),
    new Videos_VideoStats($store),
    $cache
);
$videoRankingCount = count($ranking->getGlobal(500));
$channelRankingCount = count((new Videos_ChannelRanking($store, $cache))->getGlobal(250));
$priorityCount = count((new Videos_Moderation($store))->getPriorityChannelIds(500));
$pinnedCount = isset($poolStatus['pinned_count']) ? (int) $poolStatus['pinned_count'] : 0;

$html .= '<div class="videos-admin-dashboard">';
$html .= '<header class="videos-admin-intro">'
    . '<div><p>' . $LANG_VIDEOS['admin_video_catalogue_status_quick_actions_geeklog_integrati'] . '</p></div>'
    . '<div class="videos-admin-quick-links">'
    . '<a class="videos-admin-button videos-admin-button-primary" href="'
    . htmlspecialchars($_CONF['site_admin_url'] . '/plugins/videos/actions.php', ENT_QUOTES, 'UTF-8')
    . '">' . $LANG_VIDEOS['admin_add_video'] . '</a>'
    . '<a class="videos-admin-button" href="'
    . htmlspecialchars($_CONF['site_admin_url'] . '/configuration.php?conf_group=videos', ENT_QUOTES, 'UTF-8')
    . '">' . $LANG_VIDEOS['admin_configuration'] . '</a>'
    . '<a class="videos-admin-button" href="'
    . htmlspecialchars(plugin_idtourl_videos('', 'catalogue'), ENT_QUOTES, 'UTF-8')
    . '">' . $LANG_VIDEOS['admin_view_catalogue'] . '</a>'
    . '</div></header>';

$html .= '<section class="videos-admin-panel" aria-labelledby="videos-status-title">'
    . '<div class="videos-admin-panel-heading">'
    . '<h2 id="videos-status-title">' . $LANG_VIDEOS['admin_at_glance'] . '</h2>'
    . '<a href="'
    . htmlspecialchars($_CONF['site_admin_url'] . '/plugins/videos/stats.php', ENT_QUOTES, 'UTF-8')
    . '">' . $LANG_VIDEOS['admin_all_statistics'] . '</a></div>'
    . '<div class="videos-admin-metrics">'
    . videos_admin_metric((int) $reservoirStatus['item_count'], $LANG_VIDEOS['admin_reservoir'])
    . videos_admin_metric($videoRankingCount, $LANG_VIDEOS['admin_ranked_videos'])
    . videos_admin_metric($channelRankingCount, $LANG_VIDEOS['admin_ranked_channels'])
    . videos_admin_metric($priorityCount, $LANG_VIDEOS['admin_priority_channels'])
    . videos_admin_metric((int) $poolStatus['item_count'], $LANG_VIDEOS['admin_permanent_catalogue'])
    . videos_admin_metric($pinnedCount, $LANG_VIDEOS['admin_pinned_videos'])
    . '</div></section>';

$html .= '<section class="videos-admin-panel videos-admin-integrations" aria-labelledby="videos-integrations-title">'
    . '<div class="videos-admin-panel-heading"><h2 id="videos-integrations-title">' . $LANG_VIDEOS['admin_integrations'] . '</h2></div>'
    . '<div class="videos-admin-integration-grid">'
    . videos_admin_integration($LANG_VIDEOS['admin_geeklog_search'], $LANG_VIDEOS['admin_active'], $LANG_VIDEOS['admin_local_video_corpus_available_native_search'])
    . videos_admin_integration($LANG_VIDEOS['admin_xml_sitemap'], $LANG_VIDEOS['admin_compatible'], $LANG_VIDEOS['admin_persistent_public_content_exposed_through_geeklog_item'])
    . videos_admin_integration($LANG_VIDEOS['admin_syndication'], function_exists('plugin_getfeedcontent_videos') ? $LANG_VIDEOS['admin_active'] : $LANG_VIDEOS['admin_complete'], $LANG_VIDEOS['admin_rss_atom_feeds_through_geeklog_native_syndication'])
    . '</div></section>';

$html .= '<footer class="videos-admin-public-links" aria-label="' . $LANG_VIDEOS['admin_videos_public_pages'] . '">'
    . '<span>' . $LANG_VIDEOS['admin_public_pages'] . '</span>'
    . '<a href="' . htmlspecialchars(plugin_idtourl_videos('', 'catalogue'), ENT_QUOTES, 'UTF-8') . '">' . $LANG_VIDEOS['admin_catalogue'] . '</a>'
    . '<a href="' . htmlspecialchars(plugin_idtourl_videos('', 'rankings:videos'), ENT_QUOTES, 'UTF-8') . '">' . $LANG_VIDEOS['admin_video_ranking'] . '</a>'
    . '<a href="' . htmlspecialchars(plugin_idtourl_videos('', 'rankings:channels'), ENT_QUOTES, 'UTF-8') . '">' . $LANG_VIDEOS['admin_channel_ranking'] . '</a>'
    . '</footer></div>' . VIDEOS_adminPageClose();



echo COM_createHTMLDocument(
    $html,
    array(
        'pagetitle' => $LANG_VIDEOS['admin_title'],
        'headercode' => $adminHeaderCode
    )
);

function videos_admin_metric($value, $label)
{
    return '<div class="videos-admin-metric"><strong>' . (int) $value . '</strong><span>'
        . htmlspecialchars($label, ENT_QUOTES, 'UTF-8') . '</span></div>';
}

function videos_admin_integration($title, $status, $description)
{
    return '<article class="videos-admin-integration">'
        . '<div><h3>' . htmlspecialchars($title, ENT_QUOTES, 'UTF-8') . '</h3>'
        . '<p>' . htmlspecialchars($description, ENT_QUOTES, 'UTF-8') . '</p></div>'
        . '<span class="videos-admin-badge">' . htmlspecialchars($status, ENT_QUOTES, 'UTF-8') . '</span>'
        . '</article>';
}
