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
$html = '<section class="block-center videos-admin">'
    . '<div class="block-title">' . $title . '</div>'
    . '<div class="block-content">'
    . videos_overview_nav($_CONF, 'overview');

if (!$bootstrap->isReady()) {
    $html .= '<div class="videos-admin-notice videos-admin-notice-error">'
        . COM_showMessageText(
            VIDEOS_localizeAdminText(VIDEOS_adminText('text_fce80ca1292f')),
            '',
            true
        )
        . '<p><a class="videos-admin-button" href="'
        . htmlspecialchars(
            $_CONF['site_admin_url'] . '/plugins/videos/repair.php',
            ENT_QUOTES,
            'UTF-8'
        ) . '">Ouvrir les outils de réparation</a></p></div></div></section>';
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
    . '<div><h2>Vue générale</h2>'
    . '<p>État du catalogue vidéo, accès rapides et intégrations Geeklog.</p></div>'
    . '<div class="videos-admin-quick-links">'
    . '<a class="videos-admin-button videos-admin-button-primary" href="'
    . htmlspecialchars($_CONF['site_admin_url'] . '/plugins/videos/actions.php', ENT_QUOTES, 'UTF-8')
    . '">Ajouter une vidéo</a>'
    . '<a class="videos-admin-button" href="'
    . htmlspecialchars($_CONF['site_admin_url'] . '/configuration.php?conf_group=videos', ENT_QUOTES, 'UTF-8')
    . '">Configuration</a>'
    . '<a class="videos-admin-button" href="'
    . htmlspecialchars(plugin_idtourl_videos('', 'catalogue'), ENT_QUOTES, 'UTF-8')
    . '">Voir le catalogue</a>'
    . '</div></header>';

$html .= '<section class="videos-admin-panel" aria-labelledby="videos-status-title">'
    . '<div class="videos-admin-panel-heading">'
    . '<h2 id="videos-status-title">Repères</h2>'
    . '<a href="'
    . htmlspecialchars($_CONF['site_admin_url'] . '/plugins/videos/stats.php', ENT_QUOTES, 'UTF-8')
    . '">Toutes les statistiques</a></div>'
    . '<div class="videos-admin-metrics">'
    . videos_admin_metric((int) $reservoirStatus['item_count'], 'Réservoir')
    . videos_admin_metric($videoRankingCount, 'Vidéos classées')
    . videos_admin_metric($channelRankingCount, VIDEOS_adminText('text_572e3a5c767b'))
    . videos_admin_metric($priorityCount, VIDEOS_adminText('text_a9ddd7f81604'))
    . videos_admin_metric((int) $poolStatus['item_count'], VIDEOS_adminText('text_51165f608ee6'))
    . videos_admin_metric($pinnedCount, VIDEOS_adminText('text_5cb68c9efefa'))
    . '</div></section>';

$html .= '<section class="videos-admin-panel videos-admin-integrations" aria-labelledby="videos-integrations-title">'
    . '<div class="videos-admin-panel-heading"><h2 id="videos-integrations-title">Intégrations</h2></div>'
    . '<div class="videos-admin-integration-grid">'
    . videos_admin_integration(VIDEOS_adminText('text_9274c76f0086'), VIDEOS_adminText('text_a733b809d2f1'), 'Le corpus vidéo local est disponible dans la recherche native.')
    . videos_admin_integration('XML Sitemap', 'Compatible', 'Les contenus publics persistants sont exposés via l’API ItemInfo de Geeklog.')
    . videos_admin_integration('Syndication', function_exists('plugin_getfeedcontent_videos') ? VIDEOS_adminText('text_a733b809d2f1') : 'À compléter', 'Flux RSS/Atom via le moteur de syndication natif de Geeklog.')
    . '</div></section>';

$html .= '<footer class="videos-admin-public-links" aria-label="Pages publiques Videos">'
    . '<span>Pages publiques :</span>'
    . '<a href="' . htmlspecialchars(plugin_idtourl_videos('', 'catalogue'), ENT_QUOTES, 'UTF-8') . '">Catalogue</a>'
    . '<a href="' . htmlspecialchars(plugin_idtourl_videos('', 'rankings:videos'), ENT_QUOTES, 'UTF-8') . '">Classement vidéos</a>'
    . '<a href="' . htmlspecialchars(plugin_idtourl_videos('', 'rankings:channels'), ENT_QUOTES, 'UTF-8') . '">Classement chaînes</a>'
    . '</footer></div></div></section>';



echo COM_createHTMLDocument(
    $html,
    array(
        'pagetitle' => $LANG_VIDEOS['admin_title'],
        'headercode' => $adminHeaderCode
    )
);

function videos_overview_nav($configuration, $active)
{
    global $LANG_VIDEOS;
    $base = $configuration['site_admin_url'] . '/plugins/videos/';
    $items = array(
        'overview' => array('index.php', $LANG_VIDEOS['admin_nav_overview']),
        'actions' => array('actions.php', $LANG_VIDEOS['admin_nav_actions']),
        'stats' => array('stats.php', $LANG_VIDEOS['admin_nav_stats']),
        'moderation' => array('moderation.php', $LANG_VIDEOS['admin_nav_moderation'])
    );
    $html = '<nav class="videos-navigation" aria-label="'
        . htmlspecialchars($LANG_VIDEOS['admin_navigation'], ENT_QUOTES, 'UTF-8') . '"><ul>';
    foreach ($items as $key => $item) {
        $html .= '<li><a href="'
            . htmlspecialchars($base . $item[0], ENT_QUOTES, 'UTF-8') . '"'
            . ($key === $active ? ' class="is-active" aria-current="page"' : '')
            . '>' . htmlspecialchars($item[1], ENT_QUOTES, 'UTF-8') . '</a></li>';
    }
    return $html . '</ul></nav>';
}

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
