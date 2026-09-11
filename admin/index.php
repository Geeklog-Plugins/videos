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
$title = htmlspecialchars($LANG_VIDEOS['admin_title'], ENT_QUOTES, 'UTF-8');
$html = VIDEOS_adminPageOpen('overview', $LANG_VIDEOS['admin_nav_overview']);

if (!$bootstrap->isReady()) {
    $html .= '<div class="videos-admin-notice videos-admin-notice-error">'
        . COM_showMessageText(
            VIDEOS_localizeAdminText(VIDEOS_adminText('text_563d8e12a137')),
            '',
            true
        )
        . '<p><a class="videos-admin-button" href="'
        . htmlspecialchars(
            $_CONF['site_admin_url'] . '/plugins/videos/repair.php',
            ENT_QUOTES,
            'UTF-8'
        ) . '">' . VIDEOS_adminText('text_3b26dc5fb51b') . '</a></p></div></div></section>';
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
    . '<div><h2>' . VIDEOS_adminText('text_1006cb34bf19') . '</h2>'
    . '<p>' . VIDEOS_adminText('text_3dfe53da413b') . '</p></div>'
    . '<div class="videos-admin-quick-links">'
    . '<a class="videos-admin-button videos-admin-button-primary" href="'
    . htmlspecialchars($_CONF['site_admin_url'] . '/plugins/videos/actions.php', ENT_QUOTES, 'UTF-8')
    . '">' . VIDEOS_adminText('text_741020e43937') . '</a>'
    . '<a class="videos-admin-button" href="'
    . htmlspecialchars($_CONF['site_admin_url'] . '/configuration.php?conf_group=videos', ENT_QUOTES, 'UTF-8')
    . '">' . VIDEOS_adminText('text_754164850f38') . '</a>'
    . '<a class="videos-admin-button" href="'
    . htmlspecialchars(plugin_idtourl_videos('', 'catalogue'), ENT_QUOTES, 'UTF-8')
    . '">' . VIDEOS_adminText('text_aae2580803f7') . '</a>'
    . '</div></header>';

$html .= '<section class="videos-admin-panel" aria-labelledby="videos-status-title">'
    . '<div class="videos-admin-panel-heading">'
    . '<h2 id="videos-status-title">' . VIDEOS_adminText('text_1bbb61000ea4') . '</h2>'
    . '<a href="'
    . htmlspecialchars($_CONF['site_admin_url'] . '/plugins/videos/stats.php', ENT_QUOTES, 'UTF-8')
    . '">' . VIDEOS_adminText('text_095a2e62fcf4') . '</a></div>'
    . '<div class="videos-admin-metrics">'
    . videos_admin_metric((int) $reservoirStatus['item_count'], VIDEOS_adminText('text_fd2d5f663830'))
    . videos_admin_metric($videoRankingCount, VIDEOS_adminText('text_6e8dd31cb536'))
    . videos_admin_metric($channelRankingCount, VIDEOS_adminText('text_b9bef52d4aa3'))
    . videos_admin_metric($priorityCount, VIDEOS_adminText('text_16bb358770de'))
    . videos_admin_metric((int) $poolStatus['item_count'], VIDEOS_adminText('text_3696c9b66256'))
    . videos_admin_metric($pinnedCount, VIDEOS_adminText('text_4072238c8cc7'))
    . '</div></section>';

$html .= '<section class="videos-admin-panel videos-admin-integrations" aria-labelledby="videos-integrations-title">'
    . '<div class="videos-admin-panel-heading"><h2 id="videos-integrations-title">' . VIDEOS_adminText('text_4f90e5966c83') . '</h2></div>'
    . '<div class="videos-admin-integration-grid">'
    . videos_admin_integration(VIDEOS_adminText('text_1fac67f04852'), VIDEOS_adminText('text_5420b016f8dc'), VIDEOS_adminText('text_0c9dd5b16caa'))
    . videos_admin_integration(VIDEOS_adminText('text_d3df6b0f2ffb'), VIDEOS_adminText('text_65013ef3b453'), VIDEOS_adminText('text_93658b9ae1d5'))
    . videos_admin_integration(VIDEOS_adminText('text_7f44b11de375'), function_exists('plugin_getfeedcontent_videos') ? VIDEOS_adminText('text_5420b016f8dc') : VIDEOS_adminText('text_b06198e13a28'), VIDEOS_adminText('text_e23506a27421'))
    . '</div></section>';

$html .= '<footer class="videos-admin-public-links" aria-label="' . VIDEOS_adminText('text_5a8d97976e66') . '">'
    . '<span>' . VIDEOS_adminText('text_0d9a4498510c') . '</span>'
    . '<a href="' . htmlspecialchars(plugin_idtourl_videos('', 'catalogue'), ENT_QUOTES, 'UTF-8') . '">' . VIDEOS_adminText('text_5a24102340b6') . '</a>'
    . '<a href="' . htmlspecialchars(plugin_idtourl_videos('', 'rankings:videos'), ENT_QUOTES, 'UTF-8') . '">' . VIDEOS_adminText('text_de3d3c2d44b4') . '</a>'
    . '<a href="' . htmlspecialchars(plugin_idtourl_videos('', 'rankings:channels'), ENT_QUOTES, 'UTF-8') . '">' . VIDEOS_adminText('text_9a49b90c8234') . '</a>'
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
