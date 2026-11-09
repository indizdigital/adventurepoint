#!/usr/bin/env python3
"""Build de_DE / fr_FR .po files from a .pot and a translation table.
Usage: python3 -I bin/i18n/build_po.py <pot> <translations.py> <out_prefix>
Then compile with: ./bin/wp i18n make-mo <dir> && ./bin/wp i18n make-php <dir>
"""
import re, sys, runpy

def esc(s):
    return s.replace('\\', '\\\\').replace('"', '\\"').replace('\n', '\\n')

def parse(path):
    entries, cur, key = [], {}, None
    for line in open(path, encoding='utf-8'):
        line = line.rstrip('\n')
        m = re.match(r'^(msgctxt|msgid|msgid_plural|msgstr(?:\[\d\])?) "(.*)"$', line)
        if m:
            key = m.group(1)
            if key in ('msgid', 'msgctxt') and 'msgid' in cur:
                entries.append(cur); cur = {}
            cur[key] = m.group(2); continue
        m = re.match(r'^"(.*)"$', line)
        if m and key:
            cur[key] += m.group(1)
    if cur: entries.append(cur)
    return [e for e in entries if e.get('msgid')]

def unesc(s):
    return s.replace('\\"', '"').replace('\\n', '\n').replace('\\\\', '\\')

pot, table, prefix = sys.argv[1:4]
tr = runpy.run_path(table)
T, P = tr['T'], tr.get('P', {})
langs = {'de_DE': (0, 'Deutsch', 'nplurals=2; plural=(n != 1);'), 'fr_FR': (1, 'Français', 'nplurals=2; plural=(n > 1);')}
missing = set()
for locale, (idx, name, plural) in langs.items():
    out = [f'msgid ""\nmsgstr ""\n"Content-Type: text/plain; charset=UTF-8\\n"\n"Language: {locale}\\n"\n"Plural-Forms: {plural}\\n"\n"MIME-Version: 1.0\\n"\n"Content-Transfer-Encoding: 8bit\\n"\n']
    for e in parse(pot):
        mid = unesc(e['msgid'])
        ctx = f'msgctxt "{e["msgctxt"]}"\n' if 'msgctxt' in e else ''
        if 'msgid_plural' in e:
            if mid not in P: missing.add(mid); continue
            forms = P[mid][idx]
            out.append(f'{ctx}msgid "{e["msgid"]}"\nmsgid_plural "{e["msgid_plural"]}"\nmsgstr[0] "{esc(forms[0])}"\nmsgstr[1] "{esc(forms[1])}"\n')
        else:
            if mid not in T: missing.add(mid); continue
            out.append(f'{ctx}msgid "{e["msgid"]}"\nmsgstr "{esc(T[mid][idx])}"\n')
    open(f'{prefix}-{locale}.po', 'w', encoding='utf-8').write('\n'.join(out))
for m in sorted(missing):
    print('MISSING:', m)
print('written', prefix)
