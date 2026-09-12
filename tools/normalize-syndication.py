from pathlib import Path

functions = Path('functions.inc')
text = functions.read_text(encoding='utf-8')
anchor = "require_once $_CONF['path'] . 'plugins/videos/classes/Videos_Faq.php';\n"
includes = anchor + "require_once $_CONF['path'] . 'plugins/videos/geeklog_integration.php';\n" \
    + "require_once $_CONF['path'] . 'plugins/videos/interoperability.php';\n"
if "plugins/videos/interoperability.php" not in text:
    if anchor not in text:
        raise SystemExit('Unable to locate Videos_Faq include')
    text = text.replace(anchor, includes, 1)
functions.write_text(text, encoding='utf-8')

interop = Path('interoperability.php')
text = interop.read_text(encoding='utf-8')
if 'function plugin_getfeednames_videos()' not in text:
    marker = "function VIDEOS_thumbnailAlt($title, $channel = '')\n"
    if marker not in text:
        raise SystemExit('Unable to locate interop insertion point')
    feed = r'''/**
 * Advertise one native Geeklog syndication source.
 *
 * The feed represents the persistent editorial video catalogue only. Transient
 * discovery results are deliberately excluded.
 */
function plugin_getfeednames_videos()
{
    return array(
        array(
            'id' => 'videos',
            'name' => VIDEOS_getPublicTitle()
        )
    );
}

/**
 * Return native Geeklog feed entries from the local editorial corpus.
 * No YouTube API request is performed while generating a feed.
 */
function plugin_getfeedcontent_videos(
    $feed,
    &$link,
    &$update,
    $feedType = '',
    $feedVersion = ''
) {
    global $_CONF, $_TABLES;

    $link = plugin_idtourl_videos('', 'catalogue');
    $update = '';
    $limit = 20;
    $contentLength = 0;
    $topic = 'videos';

    if (isset($_TABLES['syndication'])) {
        $feedId = DB_escapeString((string) $feed);
        $result = DB_query(
            "SELECT topic, limits, content_length FROM {$_TABLES['syndication']} "
            . "WHERE fid = '$feedId'"
        );
        if (!DB_error() && DB_numRows($result) > 0) {
            $row = DB_fetchArray($result);
            $topic = isset($row['topic']) ? (string) $row['topic'] : 'videos';
            $limit = isset($row['limits']) ? max(1, min(500, (int) $row['limits'])) : 20;
            $contentLength = isset($row['content_length'])
                ? max(0, (int) $row['content_length']) : 0;
        }
    }

    if ($topic !== '' && $topic !== 'videos') {
        return array();
    }

    $records = plugin_getiteminfo_videos(
        '*',
        '*',
        0,
        array('limit' => $limit, 'order' => 'modified-desc')
    );
    if (!is_array($records)) {
        return array();
    }

    $content = array();
    $updateParts = array();
    foreach ($records as $record) {
        if (!is_array($record) || empty($record['id']) ||
            empty($record['title']) || empty($record['url'])) {
            continue;
        }
        $summary = isset($record['excerpt'])
            ? trim(strip_tags((string) $record['excerpt'])) : '';
        if ($contentLength > 0 && strlen($summary) > $contentLength) {
            if (function_exists('MBYTE_substr')) {
                $summary = MBYTE_substr($summary, 0, $contentLength);
            } else {
                $summary = substr($summary, 0, $contentLength);
            }
        }
        $dateValue = !empty($record['date-modified'])
            ? $record['date-modified']
            : (isset($record['date-created']) ? $record['date-created'] : '');
        $timestamp = $dateValue !== '' ? strtotime($dateValue) : false;
        if ($timestamp === false) {
            $timestamp = time();
        }
        $author = isset($record['author']) ? (string) $record['author'] : '';
        $content[] = array(
            'title' => (string) $record['title'],
            'summary' => $summary,
            'link' => (string) $record['url'],
            'uid' => 0,
            'author' => $author,
            'date' => (int) $timestamp,
            'format' => 'plaintext'
        );
        $updateParts[] = (string) $record['id'] . '@' . (string) $timestamp;
    }

    $update = implode(',', $updateParts);
    return $content;
}

'''
    text = text.replace(marker, feed + marker, 1)
interop.write_text(text, encoding='utf-8')

autoinstall = Path('autoinstall.php')
text = autoinstall.read_text(encoding='utf-8')
needle = "            'config.videos.tab_block',\n            'config.videos.tab_maintenance'\n"
replacement = "            'config.videos.tab_block',\n            'config.videos.tab_seo',\n            'config.videos.tab_maintenance'\n"
if replacement not in text:
    if needle not in text:
        raise SystemExit('Unable to locate uninstall feature list')
    text = text.replace(needle, replacement, 1)
autoinstall.write_text(text, encoding='utf-8')

print('Videos integration loading, syndication and uninstall metadata normalized.')
