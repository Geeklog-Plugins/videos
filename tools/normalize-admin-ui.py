#!/usr/bin/env python3
from pathlib import Path
import re

ROOT = Path(__file__).resolve().parents[1]
ADMIN = ROOT / 'admin'
FUNCTIONS = ROOT / 'functions.inc'
ADMIN_CSS = ROOT / 'public_html' / 'css' / 'admin.css'
LANG_EN = ROOT / 'language' / 'english.php'
LANG_FR = ROOT / 'language' / 'french_france.php'
ADMIN_FILES = [
    ADMIN / 'index.php',
    ADMIN / 'actions.php',
    ADMIN / 'stats.php',
    ADMIN / 'moderation.php',
]
TOKEN_RE = re.compile(r'\{\{videos_admin_(text_[0-9a-f]{12})\}\}')
STRING_RE = re.compile(r"'(?:\\.|[^'])*'")
CSS_MARKER = '/* VIDEOS ADMIN UNIFIED UI 0.19.0 */'
LANG_MARKER = '// 0.19.0 unified admin interface strings'

LANG_KEYS = {
    'admin_actions_column': ('Actions', 'Actions'),
    'admin_cache_label': ('Cache', 'Cache'),
    'admin_yes': ('Yes', 'Oui'),
    'admin_no': ('No', 'Non'),
    'admin_status_ok': ('OK', 'OK'),
    'admin_remove_help': (
        'removes the video from the permanent catalogue, but it may be selected again.',
        'enlève la vidéo du catalogue permanent, mais elle pourra être sélectionnée de nouveau.'
    ),
    'admin_exclude_help': (
        'prevents it from being added again until it is explicitly allowed.',
        'l’empêche d’être réintégrée tant qu’elle n’est pas réautorisée.'
    ),
    'admin_consult': ('See', 'Consultez'),
    'admin_byte_unit': ('B', 'o'),
}


def tokenized_literal_to_expression(match):
    literal = match.group(0)
    if '{{videos_admin_' not in literal:
        return literal
    content = literal[1:-1]
    parts = []
    position = 0
    for token in TOKEN_RE.finditer(content):
        before = content[position:token.start()]
        if before:
            parts.append("'" + before + "'")
        parts.append("VIDEOS_adminText('%s')" % token.group(1))
        position = token.end()
    after = content[position:]
    if after:
        parts.append("'" + after + "'")
    return ' . '.join(parts) if parts else "''"


def expand_tokens(text):
    return STRING_RE.sub(tokenized_literal_to_expression, text)


def remove_function(text, name):
    marker = 'function ' + name + '('
    start = text.find(marker)
    if start < 0:
        return text
    brace = text.find('{', start)
    if brace < 0:
        return text
    depth = 0
    end = brace
    while end < len(text):
        char = text[end]
        if char == '{':
            depth += 1
        elif char == '}':
            depth -= 1
            if depth == 0:
                end += 1
                while end < len(text) and text[end] in '\r\n':
                    end += 1
                return text[:start] + text[end:]
        end += 1
    raise RuntimeError('Unbalanced function: ' + name)


def ensure_language_keys(path, language_index):
    text = path.read_text(encoding='utf-8')
    if LANG_MARKER in text:
        start = text.index(LANG_MARKER)
        end_marker = '// END 0.19.0 unified admin interface strings'
        end = text.index(end_marker, start) + len(end_marker)
        text = text[:start] + text[end:]

    lines = [LANG_MARKER]
    for key, values in LANG_KEYS.items():
        value = values[language_index].replace('\\', '\\\\').replace("'", "\\'")
        lines.append("$LANG_VIDEOS['%s'] = '%s';" % (key, value))
    lines.append('// END 0.19.0 unified admin interface strings')
    block = '\n'.join(lines) + '\n\n'

    marker = '// 0.18.0 interface strings'
    pos = text.find(marker)
    if pos < 0:
        text = text.rstrip() + '\n\n' + block
    else:
        text = text[:pos] + block + text[pos:]
    path.write_text(text, encoding='utf-8')


def normalize_common_text(text):
    text = expand_tokens(text)
    text = text.replace('VIDEOS_adminRender($html)', '$html')
    text = re.sub(
        r"VIDEOS_localizeAdminText\(VIDEOS_adminText\(('text_[0-9a-f]{12}')\)\)",
        r'VIDEOS_adminText(\1)',
        text,
    )
    return text


def normalize_page(path):
    text = normalize_common_text(path.read_text(encoding='utf-8'))

    if path.name == 'index.php':
        text = re.sub(r"\$title = htmlspecialchars\([^;]+;\n", '', text, count=1)
        text = text.replace(
            ". '<p>' . VIDEOS_adminText('text_3dfe53da413b') . '</p></div>'",
            ". '<div><p>' . VIDEOS_adminText('text_3dfe53da413b') . '</p></div>'"
        )
        text = text.replace(
            "$html .= '<header class=\"videos-admin-intro\">'\n    . '<div><h2>' . VIDEOS_adminText('text_1006cb34bf19') . '</h2>'\n    . '<div><p>'",
            "$html .= '<header class=\"videos-admin-intro\">'\n    . '<div><p>'"
        )
        text = text.replace(
            ". '<p><a class=\"videos-admin-button\" href=\"'",
            ". '<p><a class=\"videos-admin-button\" href=\"'"
        )
        text = text.replace(
            ") . '\">' . VIDEOS_adminText('text_3b26dc5fb51b') . '</a></p></div></div></section>';",
            ") . '\">' . VIDEOS_adminText('text_3b26dc5fb51b') . '</a></p></div>' . VIDEOS_adminPageClose();"
        )
        text = remove_function(text, 'videos_overview_nav')

    elif path.name == 'actions.php':
        text = text.replace('<th>Actions</th>', "<th>' . $LANG_VIDEOS['admin_actions_column'] . '</th>")
        text = text.replace(
            "</strong> enlève la vidéo du catalogue permanent, mais elle pourra être sélectionnée de nouveau. '",
            "</strong> ' . $LANG_VIDEOS['admin_remove_help'] . ' '"
        )
        text = text.replace(
            "</strong> l’empêche d’être réintégrée tant qu’elle n’est pas réautorisée.</p>';",
            "</strong> ' . $LANG_VIDEOS['admin_exclude_help'] . '</p>';"
        )
        text = text.replace("<label>Cache <select", "<label>' . $LANG_VIDEOS['admin_cache_label'] . ' <select")
        text = text.replace("'. Consultez ' .", "'. ' . $LANG_VIDEOS['admin_consult'] . ' ' .")
        text = text.replace(
            "$html .= '</div>';\n\n\necho COM_createHTMLDocument",
            "$html .= VIDEOS_adminPageClose();\n\n\necho COM_createHTMLDocument"
        )
        text = text.replace(
            "array('pagetitle' => VIDEOS_adminText('text_372047eedaf8'), 'headercode' => VIDEOS_adminHeaderCode())",
            "array('pagetitle' => $LANG_VIDEOS['admin_title'], 'headercode' => VIDEOS_adminHeaderCode())"
        )
        text = remove_function(text, 'videos_admin_section_nav')

    elif path.name == 'stats.php':
        text = text.replace("!empty($quotaData['suspended']) ? 'Oui' : 'Non'", "!empty($quotaData['suspended']) ? $LANG_VIDEOS['admin_yes'] : $LANG_VIDEOS['admin_no']")
        text = text.replace("<h2>Cache</h2>", "<h2>' . $LANG_VIDEOS['admin_cache_label'] . '</h2>")
        text = text.replace("<th>Cache</th>", "<th>' . $LANG_VIDEOS['admin_cache_label'] . '</th>")
        text = text.replace("($allOk ? 'OK' :", "($allOk ? $LANG_VIDEOS['admin_status_ok'] :")
        text = text.replace("($ok ? 'OK' :", "($ok ? $LANG_VIDEOS['admin_status_ok'] :")
        text = text.replace("return $bytes . ' o';", "return $bytes . ' ' . $GLOBALS['LANG_VIDEOS']['admin_byte_unit'];")
        text = text.replace(
            "$html .= '</div>';\n\n\necho COM_createHTMLDocument(",
            "$html .= VIDEOS_adminPageClose();\n\n\necho COM_createHTMLDocument("
        )
        text = text.replace("'pagetitle' => VIDEOS_adminText('text_695c8c330b8f')", "'pagetitle' => $LANG_VIDEOS['admin_title']")
        text = remove_function(text, 'videos_stats_nav')
        text = remove_function(text, 'videos_stats_header_code')

    elif path.name == 'moderation.php':
        text = text.replace(
            "$html .= '</div>';\n\necho COM_createHTMLDocument(",
            "$html .= VIDEOS_adminPageClose();\n\necho COM_createHTMLDocument("
        )
        text = remove_function(text, 'videos_moderation_nav')

    path.write_text(text, encoding='utf-8')


def ensure_shared_helpers():
    text = FUNCTIONS.read_text(encoding='utf-8')
    for name in ('VIDEOS_adminNavigation', 'VIDEOS_adminPageOpen', 'VIDEOS_adminPageClose', 'VIDEOS_adminRender'):
        text = remove_function(text, name)

    marker = 'function VIDEOS_adminText($key)\n{'
    pos = text.find(marker)
    if pos < 0:
        raise RuntimeError('Unable to locate VIDEOS_adminText()')

    helpers = r'''function VIDEOS_adminNavigation($active)
{
    global $_CONF, $LANG_VIDEOS;

    $base = rtrim($_CONF['site_admin_url'], '/') . '/plugins/videos/';
    $items = array(
        'overview' => array('index.php', $LANG_VIDEOS['admin_nav_overview']),
        'actions' => array('actions.php', $LANG_VIDEOS['admin_nav_actions']),
        'stats' => array('stats.php', $LANG_VIDEOS['admin_nav_stats']),
        'moderation' => array('moderation.php', $LANG_VIDEOS['admin_nav_moderation'])
    );

    $html = '<nav class="videos-navigation" aria-label="'
        . htmlspecialchars($LANG_VIDEOS['admin_navigation'], ENT_QUOTES, 'UTF-8')
        . '"><ul>';
    foreach ($items as $key => $item) {
        $html .= '<li><a href="'
            . htmlspecialchars($base . $item[0], ENT_QUOTES, 'UTF-8') . '"'
            . ($key === $active ? ' class="is-active" aria-current="page"' : '')
            . '>' . htmlspecialchars($item[1], ENT_QUOTES, 'UTF-8') . '</a></li>';
    }

    return $html . '</ul></nav>';
}

function VIDEOS_adminPageOpen($active, $pageTitle)
{
    global $LANG_VIDEOS;

    return '<section class="block-center videos-admin">'
        . '<div class="block-title">'
        . htmlspecialchars($LANG_VIDEOS['admin_title'], ENT_QUOTES, 'UTF-8')
        . '</div><div class="block-content">'
        . VIDEOS_adminNavigation($active)
        . '<header class="videos-admin-page-header"><h1>'
        . htmlspecialchars($pageTitle, ENT_QUOTES, 'UTF-8')
        . '</h1></header>';
}

function VIDEOS_adminPageClose()
{
    return '</div></section>';
}

'''
    text = text[:pos] + helpers + text[pos:]
    FUNCTIONS.write_text(text, encoding='utf-8')


def normalize_css():
    text = ADMIN_CSS.read_text(encoding='utf-8')
    legacy = text.find('/* Harmonized shell for Overview, Actions, Statistics and Moderation. */')
    unified = text.find(CSS_MARKER)
    cut_positions = [p for p in (legacy, unified) if p >= 0]
    if cut_positions:
        text = text[:min(cut_positions)].rstrip()

    css = r'''

/* VIDEOS ADMIN UNIFIED UI 0.19.0 */
.videos-admin {
    --videos-admin-gap: 1.25rem;
}

.videos-admin > .block-content {
    padding: 0 var(--videos-admin-gap) var(--videos-admin-gap);
    box-sizing: border-box;
}

.videos-admin .videos-navigation {
    margin: 0 calc(-1 * var(--videos-admin-gap)) var(--videos-admin-gap);
    padding: 0 var(--videos-admin-gap);
    box-sizing: border-box;
}

.videos-admin-page-header {
    margin: 0 0 1rem;
    padding: 0;
}

.videos-admin-page-header h1 {
    margin: 0;
    font-size: 1.3rem;
    line-height: 1.25;
}

.videos-admin-dashboard,
.videos-admin-section,
.videos-admin-panel,
.videos-stat-grid,
.videos-integration-grid,
.videos-admin-results,
.videos-admin-help,
.videos-admin > .block-content > details,
.videos-admin > .block-content > form,
.videos-admin > .block-content > p {
    box-sizing: border-box;
}

.videos-admin-section,
.videos-admin-panel {
    margin: 0 0 1rem;
    padding: 1rem;
    border: 1px solid rgba(127, 127, 127, .18);
    border-radius: .5rem;
    background: rgba(127, 127, 127, .022);
    box-shadow: none;
}

.videos-admin-section > h2,
.videos-admin-panel-heading h2 {
    margin-top: 0;
    letter-spacing: -.01em;
}

.videos-admin-form + .videos-admin-form {
    margin-top: .85rem;
}

.videos-admin-form input[type="text"],
.videos-admin-form input[type="password"],
.videos-admin-form select {
    border-color: rgba(127, 127, 127, .34);
    border-radius: .42rem;
}

.videos-admin-form input:focus,
.videos-admin-form select:focus,
.videos-admin button:focus,
.videos-admin a:focus {
    outline: 2px solid rgba(54, 89, 210, .42);
    outline-offset: 2px;
}

.videos-admin button,
.videos-admin input[type="submit"] {
    min-height: 2.35rem;
    padding: .42rem .78rem;
    border: 1px solid rgba(127, 127, 127, .3);
    border-radius: .42rem;
    background: rgba(127, 127, 127, .07);
    color: inherit;
    font-weight: 600;
    cursor: pointer;
}

.videos-admin button:hover,
.videos-admin button:focus,
.videos-admin input[type="submit"]:hover,
.videos-admin input[type="submit"]:focus {
    background: rgba(127, 127, 127, .13);
}

.videos-stat-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
    gap: .75rem;
}

.videos-stat-card {
    display: flex;
    flex-direction: column;
    min-height: 105px;
    padding: .85rem;
    border: 1px solid rgba(127, 127, 127, .16);
    border-radius: .45rem;
    background: rgba(127, 127, 127, .045);
}

.videos-stat-value {
    margin-bottom: .35rem;
    overflow-wrap: anywhere;
    font-size: 1.65rem;
    font-weight: 700;
    line-height: 1.05;
}

.videos-stat-value.is-small {
    font-size: 1rem;
    line-height: 1.3;
}

.videos-stat-label {
    font-size: .92rem;
}

.videos-stat-detail {
    margin-top: auto;
    padding-top: .4rem;
    font-size: .78rem;
    line-height: 1.3;
    opacity: .7;
}

.videos-integration-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
    gap: .75rem;
}

.videos-integration-grid > div {
    display: flex;
    flex-direction: column;
    gap: .3rem;
    padding: .85rem;
    border: 1px solid rgba(127, 127, 127, .16);
    border-radius: .45rem;
    background: rgba(127, 127, 127, .035);
}

.videos-admin-table tbody tr:hover {
    background: rgba(70, 95, 190, .04);
}

@media (max-width: 700px) {
    .videos-admin {
        --videos-admin-gap: .85rem;
    }

    .videos-admin-page-header h1 {
        font-size: 1.15rem;
    }
}
'''
    ADMIN_CSS.write_text(text + css + '\n', encoding='utf-8')


def validate():
    for path in ADMIN_FILES:
        text = path.read_text(encoding='utf-8')
        if '{{videos_admin_' in text:
            raise RuntimeError('Unresolved admin token in ' + str(path))
        if 'videos_overview_nav(' in text or 'videos_admin_section_nav(' in text or 'videos_stats_nav(' in text or 'videos_moderation_nav(' in text:
            raise RuntimeError('Legacy admin navigation remains in ' + str(path))
        if 'VIDEOS_adminRender(' in text:
            raise RuntimeError('Legacy admin token renderer remains in ' + str(path))
        if "VIDEOS_adminPageOpen(" not in text or 'VIDEOS_adminPageClose()' not in text:
            raise RuntimeError('Shared admin shell incomplete in ' + str(path))

    actions = (ADMIN / 'actions.php').read_text(encoding='utf-8')
    stats = (ADMIN / 'stats.php').read_text(encoding='utf-8')
    forbidden = (' enlève la vidéo', ' l’empêche d’être', 'Consultez ', "'Oui'", "'Non'", '<h2>Cache</h2>')
    for value in forbidden:
        if value in actions or value in stats:
            raise RuntimeError('Hard-coded admin UI text remains: ' + value)


def main():
    ensure_language_keys(LANG_EN, 0)
    ensure_language_keys(LANG_FR, 1)
    ensure_shared_helpers()
    for path in ADMIN_FILES:
        normalize_page(path)
    normalize_css()
    validate()
    print('Videos admin UI normalized: shared shell, direct language lookups, no tokens.')


if __name__ == '__main__':
    main()
