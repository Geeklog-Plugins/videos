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

BEGIN = '// VIDEOS ADMIN LANGUAGE KEYS 0.19.0'
END = '// END VIDEOS ADMIN LANGUAGE KEYS 0.19.0'


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
    for source in sorted(sources, key=len, reverse=True):
        literal = "'" + php_escape(source) + "'"
        replacement = "VIDEOS_adminText('%s')" % key_for(source)
        text = text.replace(literal, replacement)
    # Once literals are proper language lookups, whole-document string translation
    # is no longer the primary translation mechanism.
    text = text.replace('$html = VIDEOS_localizeAdminText($html);', '')
    path.write_text(text, encoding='utf-8')


def main():
    language_pairs = {}
    for name, path in LANG_FILES.items():
        text = path.read_text(encoding='utf-8')
        language_pairs[name] = extract_admin_pairs(text)

    # The French source strings define the common key set. English may contain
    # a few compatibility-only entries; include the union while preserving each
    # language's own target value where available.
    sources = set()
    for pairs in language_pairs.values():
        sources.update(pairs.keys())

    for name, path in LANG_FILES.items():
        pairs = language_pairs[name]
        normalized = {source: pairs.get(source, source) for source in sources}
        replace_language_block(path, normalized)

    for path in ADMIN_FILES:
        replace_admin_literals(path, sources)

    print('Migrated %d admin language strings into language files.' % len(sources))


if __name__ == '__main__':
    main()
