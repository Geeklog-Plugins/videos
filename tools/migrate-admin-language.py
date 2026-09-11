#!/usr/bin/env python3
from pathlib import Path
import hashlib
import re

ROOT = Path(__file__).resolve().parents[1]
LANG_FILES = {
    'english.php': ROOT / 'language' / 'english.php',
    'french_france.php': ROOT / 'language' / 'french_france.php',
}
ADMIN_FILES = [
    ROOT / 'admin' / 'index.php',
    ROOT / 'admin' / 'actions.php',
    ROOT / 'admin' / 'stats.php',
    ROOT / 'admin' / 'moderation.php',
]
FUNCTIONS = ROOT / 'functions.inc'
ADMIN_CSS = ROOT / 'public_html' / 'css' / 'admin.css'

BEGIN = '// VIDEOS ADMIN LANGUAGE KEYS 0.19.0'
END = '// END VIDEOS ADMIN LANGUAGE KEYS 0.19.0'
UI_MARKER = '/* VIDEOS ADMIN UNIFIED UI 0.19.0 */'
TOKEN_PREFIX = '{{videos_admin_'

EXTRA = {
    'Vue générale': 'Overview',
    'État du catalogue vidéo, accès rapides et intégrations Geeklog.': 'Video catalogue status, quick actions and Geeklog integrations.',
    'Ajouter une vidéo': 'Add a video',
    'Configuration': 'Configuration',
    'Voir le catalogue': 'View catalogue',
    'Toutes les statistiques': 'All statistics',
    'Réservoir': 'Reservoir',
    'Vidéos classées': 'Ranked videos',
    'Intégrations': 'Integrations',
    'Le corpus vidéo local est disponible dans la recherche native.': 'The local video corpus is available in native search.',
    'XML Sitemap': 'XML Sitemap',
    'Compatible': 'Compatible',
    'Les contenus publics persistants sont exposés via l’API ItemInfo de Geeklog.': 'Persistent public content is exposed through the Geeklog ItemInfo API.',
    'Syndication': 'Syndication',
    'À compléter': 'To complete',
    'Flux RSS/Atom via le moteur de syndication natif de Geeklog.': 'RSS/Atom feeds through Geeklog native syndication.',
    'Pages publiques Videos': 'Videos public pages',
    'Pages publiques :': 'Public pages:',
    'Classement vidéos': 'Video ranking',
    'Classement chaînes': 'Channel ranking',
    'Ouvrir les outils de réparation': 'Open repair tools',
}


def php_unescape(value):
    return value.replace("\\'", "'").replace('\\\\', '\\')


def php_escape(value):
    return value.replace('\\', '\\\\').replace("'", "\\'")


def extract_admin_pairs(text):
    start = text.find('$LANG_VIDEOS_ADMIN_TEXT')
    end = text.find('$LANG_VIDEOS_FAQ', start)
    if start < 0 or end < 0:
        raise RuntimeError('Unable to locate admin translation block')
    block = text[start:end]
    pairs = {}
    pattern = re.compile(r"'((?:\\.|[^'])*)'\s*=>\s*'((?:\\.|[^'])*)'")
    for source, target in pattern.findall(block):
        source = php_unescape(source)
        target = php_unescape(target)
        if source:
            pairs[source] = target
    return pairs


def key_for(source):
    return 'text_' + hashlib.sha1(source.encode('utf-8')).hexdigest()[:12]


def token_for(source):
    return TOKEN_PREFIX + key_for(source) + '}}'


def replace_language_block(path, pairs):
    text = path.read_text(encoding='utf-8')
    lines = [BEGIN, '$LANG_VIDEOS_ADMIN = array(']
    for source in sorted(pairs):
        key = key_for(source)
        value = php_escape(pairs[source])
        lines.append("    '%s' => '%s'," % (key, value))
    lines.extend([');', END])
    block = '\n'.join(lines)
    if BEGIN in text and END in text:
        text = re.sub(
            re.escape(BEGIN) + r'.*?' + re.escape(END),
            block,
            text,
            flags=re.S,
        )
    else:
        marker = '$LANG_VIDEOS_FAQ = array('
        pos = text.find(marker)
        if pos < 0:
            text = text.rstrip() + '\n\n' + block + '\n'
        else:
            text = text[:pos] + block + '\n\n' + text[pos:]
    path.write_text(text, encoding='utf-8')


def replace_admin_literals(path, sources):
    text = path.read_text(encoding='utf-8')

    # Exact PHP literals become direct lookups, useful for messages, comparisons
    # and button labels passed as function arguments.
    for source in sorted(sources, key=len, reverse=True):
        literal = "'" + php_escape(source) + "'"
        replacement = "VIDEOS_adminText('%s')" % key_for(source)
        text = text.replace(literal, replacement)

    # Text embedded inside larger HTML strings becomes a language token. This
    # removes user-facing prose from admin PHP without restructuring every
    # concatenated HTML fragment.
    for source in sorted(sources, key=len, reverse=True):
        if source in text:
            text = text.replace(source, token_for(source))

    text = text.replace('$html = VIDEOS_localizeAdminText($html);', '')
    text = text.replace(
        'COM_createHTMLDocument(\n    $html,',
        'COM_createHTMLDocument(\n    VIDEOS_adminRender($html),'
    )
    text = text.replace(
        'COM_createHTMLDocument(\n        $html,',
        'COM_createHTMLDocument(\n        VIDEOS_adminRender($html),'
    )
    path.write_text(text, encoding='utf-8')


def ensure_admin_helpers():
    text = FUNCTIONS.read_text(encoding='utf-8')
    marker = 'function VIDEOS_localizeAdminText($text)\n{'
    pos = text.find(marker)
    if pos < 0:
        raise RuntimeError('Unable to locate VIDEOS_localizeAdminText()')

    helper = ''
    if 'function VIDEOS_adminText($key)' not in text:
        helper += """function VIDEOS_adminText($key)\n{\n    global $LANG_VIDEOS_ADMIN;\n\n    if (isset($LANG_VIDEOS_ADMIN) &&\n        is_array($LANG_VIDEOS_ADMIN) &&\n        isset($LANG_VIDEOS_ADMIN[$key])) {\n        return $LANG_VIDEOS_ADMIN[$key];\n    }\n\n    return (string) $key;\n}\n\n"""

    if 'function VIDEOS_adminRender($text)' not in text:
        helper += """function VIDEOS_adminRender($text)\n{\n    global $LANG_VIDEOS_ADMIN;\n\n    if (!isset($LANG_VIDEOS_ADMIN) || !is_array($LANG_VIDEOS_ADMIN) || $text === '') {\n        return $text;\n    }\n\n    $replace = array();\n    foreach ($LANG_VIDEOS_ADMIN as $key => $value) {\n        $replace['{{videos_admin_' . $key . '}}'] = $value;\n    }\n\n    return strtr((string) $text, $replace);\n}\n\n"""

    if helper:
        text = text[:pos] + helper + text[pos:]
        FUNCTIONS.write_text(text, encoding='utf-8')


def ensure_unified_css():
    text = ADMIN_CSS.read_text(encoding='utf-8')
    if UI_MARKER in text:
        return
    css = r'''

/* VIDEOS ADMIN UNIFIED UI 0.19.0 */
.videos-admin {
    --videos-admin-gap: 1.25rem;
}

.videos-admin.block-center > .block-content {
    padding: 0 var(--videos-admin-gap) var(--videos-admin-gap);
    box-sizing: border-box;
}

.videos-admin:not(.block-center) {
    overflow: hidden;
    border: 1px solid rgba(127, 127, 127, .22);
    border-radius: .7rem;
    background: rgba(255, 255, 255, .78);
    box-shadow: 0 8px 26px rgba(20, 30, 55, .06);
}

.videos-admin:not(.block-center) > h1 {
    margin: 0;
    padding: 1.15rem var(--videos-admin-gap);
    border-bottom: 1px solid rgba(127, 127, 127, .18);
    background: rgba(70, 95, 190, .065);
    font-size: 1.45rem;
    line-height: 1.25;
}

.videos-admin:not(.block-center) > .videos-navigation {
    margin: 0 0 var(--videos-admin-gap);
    padding: 0 var(--videos-admin-gap);
}

.videos-admin:not(.block-center) > .videos-admin-section,
.videos-admin:not(.block-center) > .videos-stat-grid,
.videos-admin:not(.block-center) > .videos-admin-help,
.videos-admin:not(.block-center) > .videos-admin-results,
.videos-admin:not(.block-center) > .videos-integration-grid,
.videos-admin:not(.block-center) > details,
.videos-admin:not(.block-center) > form,
.videos-admin:not(.block-center) > p {
    margin-left: var(--videos-admin-gap);
    margin-right: var(--videos-admin-gap);
}

.videos-admin-section,
.videos-admin-panel {
    border-color: rgba(127, 127, 127, .18);
    background: rgba(127, 127, 127, .025);
    box-shadow: none;
}

.videos-admin-section > h2,
.videos-admin-panel-heading h2 {
    letter-spacing: -.01em;
}

.videos-admin-form + .videos-admin-form {
    margin-top: .85rem;
}

.videos-admin-form input[type="text"],
.videos-admin-form input[type="password"],
.videos-admin-form select {
    background: rgba(255, 255, 255, .82);
}

.videos-admin-form input:focus,
.videos-admin-form select:focus,
.videos-admin-form button:focus,
.videos-admin a:focus {
    outline: 2px solid rgba(54, 89, 210, .45);
    outline-offset: 2px;
}

.videos-admin-table tbody tr:hover {
    background: rgba(70, 95, 190, .045);
}

.videos-admin .videos-navigation {
    box-sizing: border-box;
}

@media (max-width: 700px) {
    .videos-admin {
        --videos-admin-gap: .9rem;
    }

    .videos-admin:not(.block-center) > h1 {
        font-size: 1.25rem;
    }
}
'''
    ADMIN_CSS.write_text(text.rstrip() + css + '\n', encoding='utf-8')


def main():
    language_pairs = {}
    for name, path in LANG_FILES.items():
        text = path.read_text(encoding='utf-8')
        language_pairs[name] = extract_admin_pairs(text)

    # Recent dashboard strings were introduced after the legacy translation map.
    language_pairs['english.php'].update(EXTRA)
    language_pairs['french_france.php'].update({key: key for key in EXTRA})

    sources = set()
    for pairs in language_pairs.values():
        sources.update(pairs.keys())

    for name, path in LANG_FILES.items():
        pairs = language_pairs[name]
        normalized = {source: pairs.get(source, source) for source in sources}
        replace_language_block(path, normalized)

    for path in ADMIN_FILES:
        replace_admin_literals(path, sources)

    ensure_admin_helpers()
    ensure_unified_css()

    print('Migrated %d admin language strings into language files.' % len(sources))


if __name__ == '__main__':
    main()
