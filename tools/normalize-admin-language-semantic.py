from pathlib import Path
import re
import unicodedata

ROOT = Path('.')
LANG_FILES = [Path('language/english.php'), Path('language/french_france.php')]
SOURCE_FILES = [
    Path('admin/index.php'),
    Path('admin/actions.php'),
    Path('admin/stats.php'),
    Path('admin/moderation.php'),
]

PAIR_RE = re.compile(r"'(text_[0-9a-f]{12})'\s*=>\s*('(?:\\.|[^'\\])*')")
CALL_RE = re.compile(r"VIDEOS_adminText\('(text_[0-9a-f]{12})'\)")


def php_unquote(literal):
    value = literal[1:-1]
    value = value.replace("\\'", "'").replace('\\\\', '\\')
    return value


def semantic_name(text, fallback, used):
    normalized = unicodedata.normalize('NFKD', text)
    normalized = ''.join(ch for ch in normalized if not unicodedata.combining(ch))
    words = re.findall(r'[A-Za-z0-9]+', normalized.lower())
    stop = {'the', 'a', 'an', 'to', 'of', 'and', 'or', 'for', 'in', 'on', 'is', 'are', 'this', 'that'}
    compact = [word for word in words if word not in stop][:7]
    if not compact:
        compact = words[:7]
    stem = '_'.join(compact)[:54].strip('_')
    if not stem:
        stem = fallback.replace('text_', 'message_')
    key = 'admin_' + stem
    if key in used:
        key += '_' + fallback[-4:]
    used.add(key)
    return key


languages = {}
for path in LANG_FILES:
    text = path.read_text(encoding='utf-8')
    pairs = {key: literal for key, literal in PAIR_RE.findall(text)}
    languages[path] = (text, pairs)

english_pairs = languages[Path('language/english.php')][1]
french_pairs = languages[Path('language/french_france.php')][1]

used_hashes = set()
for path in SOURCE_FILES:
    used_hashes.update(CALL_RE.findall(path.read_text(encoding='utf-8')))

missing_en = sorted(used_hashes - set(english_pairs))
missing_fr = sorted(used_hashes - set(french_pairs))
if missing_en or missing_fr:
    raise SystemExit('Missing language hashes: EN=%s FR=%s' % (missing_en, missing_fr))

used_names = set()
key_map = {}
for old_key in sorted(used_hashes):
    key_map[old_key] = semantic_name(
        php_unquote(english_pairs[old_key]),
        old_key,
        used_names
    )

for path in SOURCE_FILES:
    text = path.read_text(encoding='utf-8')
    text = CALL_RE.sub(lambda m: "$LANG_VIDEOS['%s']" % key_map[m.group(1)], text)
    text = re.sub(r"^\s*\$message\s*=\s*VIDEOS_localizeAdminText\(\$message\);\s*\n", '', text, flags=re.M)
    path.write_text(text, encoding='utf-8')

for path in LANG_FILES:
    text, pairs = languages[path]
    # Remove obsolete compatibility translation structures.
    text = re.sub(
        r"\n?// 0\.18\.0 administration compatibility translations\s*\n\$LANG_VIDEOS_ADMIN_TEXT\s*=\s*array\s*\(.*?\n\);\s*",
        '\n',
        text,
        flags=re.S,
    )
    text = re.sub(
        r"\n?// VIDEOS ADMIN LANGUAGE KEYS 0\.19\.0\s*\n\$LANG_VIDEOS_ADMIN\s*=\s*array\(.*?\n\);\s*// END VIDEOS ADMIN LANGUAGE KEYS 0\.19\.0\s*",
        '\n',
        text,
        flags=re.S,
    )
    text = re.sub(
        r"\n?// VIDEOS ADMIN (?:FINAL|COMPAT) KEYS 0\.19\.0\s*\n\$LANG_VIDEOS_ADMIN\s*=\s*array_merge\(\$LANG_VIDEOS_ADMIN,\s*array\(.*?\n\)\);\s*// END VIDEOS ADMIN (?:FINAL|COMPAT) KEYS 0\.19\.0\s*",
        '\n',
        text,
        flags=re.S,
    )

    lines = ['\n// Videos 0.19.0 semantic administration strings']
    for old_key in sorted(used_hashes):
        lines.append("$LANG_VIDEOS['%s'] = %s;" % (key_map[old_key], pairs[old_key]))
    lines.append('// End Videos 0.19.0 semantic administration strings\n')
    text = text.rstrip() + '\n' + '\n'.join(lines)
    path.write_text(text, encoding='utf-8')

functions = Path('functions.inc')
text = functions.read_text(encoding='utf-8')
text = re.sub(
    r"\nfunction VIDEOS_adminText\(\$key\)\s*\{.*?\n\}\s*\nfunction VIDEOS_localizeAdminText\(\$text\)\s*\{.*?\n\}\s*",
    '\n',
    text,
    flags=re.S,
)
functions.write_text(text, encoding='utf-8')

# Hard validation: no active hashed admin language layer remains.
for path in SOURCE_FILES + [Path('functions.inc')]:
    text = path.read_text(encoding='utf-8')
    if 'VIDEOS_adminText(' in text or 'VIDEOS_localizeAdminText(' in text or re.search(r'text_[0-9a-f]{12}', text):
        raise SystemExit('Legacy admin language reference remains in %s' % path)

for path in LANG_FILES:
    text = path.read_text(encoding='utf-8')
    if '$LANG_VIDEOS_ADMIN' in text or '$LANG_VIDEOS_ADMIN_TEXT' in text or re.search(r"'text_[0-9a-f]{12}'\s*=>", text):
        raise SystemExit('Legacy admin language table remains in %s' % path)

print('Converted %d hashed admin language keys to semantic keys.' % len(key_map))
