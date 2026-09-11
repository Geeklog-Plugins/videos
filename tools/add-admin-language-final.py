#!/usr/bin/env python3
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
FILES = {
    ROOT / 'language' / 'english.php': {
        'text_721e1db12433': 'Limit reached (',
        'text_a54f1197e621': 'None',
    },
    ROOT / 'language' / 'french_france.php': {
        'text_721e1db12433': 'Limite atteinte (',
        'text_a54f1197e621': 'Aucune',
    },
}
MARKER = '// VIDEOS ADMIN FINAL KEYS 0.19.0'
END = '// END VIDEOS ADMIN FINAL KEYS 0.19.0'

for path, values in FILES.items():
    text = path.read_text(encoding='utf-8')
    if MARKER in text and END in text:
        start = text.index(MARKER)
        finish = text.index(END, start) + len(END)
        text = text[:start] + text[finish:].lstrip('\n')
    lines = [MARKER]
    for key, value in values.items():
        value = value.replace('\\', '\\\\').replace("'", "\\'")
        lines.append("$LANG_VIDEOS_ADMIN['%s'] = '%s';" % (key, value))
    lines.append(END)
    block = '\n'.join(lines) + '\n\n'
    pos = text.find('$LANG_VIDEOS_FAQ = array(')
    if pos < 0:
        raise RuntimeError('FAQ marker missing: %s' % path)
    path.write_text(text[:pos] + block + text[pos:], encoding='utf-8')
