# -*- coding: utf-8 -*-
"""Ekstraksi ulang struktur dokumen Word, kini dengan penomoran asli
   yang dibaca dari word/numbering.xml (bukan diasumsikan a, b, c)."""
import xml.etree.ElementTree as ET, json, re, unicodedata

W = '{http://schemas.openxmlformats.org/wordprocessingml/2006/main}'
BASE = '/tmp/claude-0/-home-user-binwasdal/5edcf805-d854-5f10-ae87-230e2ba06f4f/scratchpad'
OUT = BASE + '/master.json'

# ---------------------------------------------------------------- numbering
nt = ET.parse(BASE + '/docx/word/numbering.xml').getroot()
abstracts = {}
for a in nt.findall(W + 'abstractNum'):
    lv = {}
    for l in a.findall(W + 'lvl'):
        i = int(l.get(W + 'ilvl'))
        fmt = l.find(W + 'numFmt'); txt = l.find(W + 'lvlText'); st = l.find(W + 'start')
        lv[i] = {
            'fmt': fmt.get(W + 'val') if fmt is not None else 'decimal',
            'txt': txt.get(W + 'val') if txt is not None else '%1.',
            'start': int(st.get(W + 'val')) if st is not None else 1,
        }
    abstracts[a.get(W + 'abstractNumId')] = lv

numdefs = {}
for n in nt.findall(W + 'num'):
    nid = n.get(W + 'numId')
    a = n.find(W + 'abstractNumId')
    lv = dict(abstracts.get(a.get(W + 'val'), {})) if a is not None else {}
    for ov in n.findall(W + 'lvlOverride'):
        i = int(ov.get(W + 'ilvl'))
        so = ov.find(W + 'startOverride')
        if so is not None and i in lv:
            lv[i] = dict(lv[i]); lv[i]['start'] = int(so.get(W + 'val'))
    numdefs[nid] = lv

def roman(n, upper=False):
    peta = [(1000,'m'),(900,'cm'),(500,'d'),(400,'cd'),(100,'c'),(90,'xc'),
            (50,'l'),(40,'xl'),(10,'x'),(9,'ix'),(5,'v'),(4,'iv'),(1,'i')]
    s = ''
    for v, r in peta:
        while n >= v:
            s += r; n -= v
    return s.upper() if upper else s

def alpha(n, upper=False):
    s = ''
    while n > 0:
        n -= 1
        s = chr(97 + n % 26) + s
        n //= 26
    return s.upper() if upper else s

def format_angka(n, fmt):
    if fmt == 'decimal':      return str(n)
    if fmt == 'lowerLetter':  return alpha(n)
    if fmt == 'upperLetter':  return alpha(n, True)
    if fmt == 'lowerRoman':   return roman(n)
    if fmt == 'upperRoman':   return roman(n, True)
    if fmt == 'decimalZero':  return '%02d' % n
    return str(n)

counters = {}   # numId -> {ilvl: nilai}

def label_untuk(numid, ilvl):
    """Label seperti yang Word tampilkan, mis. 'a.', '1)', '(a)', '•'."""
    lv = numdefs.get(numid, {})
    d = lv.get(ilvl)
    if d is None:
        return '', 0
    c = counters.setdefault(numid, {})
    c[ilvl] = c.get(ilvl, d['start'] - 1) + 1
    for deeper in [k for k in list(c) if k > ilvl]:
        del c[deeper]
    if d['fmt'] == 'bullet':
        return d['txt'] or '•', c[ilvl]
    teks = d['txt']
    for i in range(9):
        tanda = '%%%d' % (i + 1)
        if tanda in teks:
            di = lv.get(i, {'fmt': 'decimal', 'start': 1})
            nilai = c.get(i, di.get('start', 1))
            teks = teks.replace(tanda, format_angka(nilai, di['fmt']))
    return teks, c[ilvl]

# ---------------------------------------------------------------- dokumen
body = ET.parse(BASE + '/docx/word/document.xml').getroot().find(W + 'body')

def clean(s):
    s = s.replace(' ', ' ')
    s = re.sub(r'[\t]+', ' ', s)
    return re.sub(r'\s+', ' ', s).strip()

def ptext(p):
    o = []
    for n in p.iter():
        if n.tag == W + 't':   o.append(n.text or '')
        elif n.tag == W + 'tab': o.append('\t')
    return ''.join(o)

def pmeta(p):
    ppr = p.find(W + 'pPr'); ilvl = numid = ind = None; style = ''
    if ppr is not None:
        np = ppr.find(W + 'numPr')
        if np is not None:
            i = np.find(W + 'ilvl'); n = np.find(W + 'numId')
            ilvl = int(i.get(W + 'val')) if i is not None else None
            numid = n.get(W + 'val') if n is not None else None
        ii = ppr.find(W + 'ind')
        if ii is not None and ii.get(W + 'left'): ind = int(ii.get(W + 'left'))
        st = ppr.find(W + 'pStyle')
        if st is not None: style = st.get(W + 'val')
    return numid, ilvl, ind, style

def level_of(ilvl, ind, bernomor):
    if not bernomor and (ind is None or ind < 300):
        return 0
    b = 1
    if ind is not None:
        if ind >= 1150: b = 3
        elif ind >= 700: b = 2
    return min(max((ilvl or 0) + 1, b), 3)

def cell_lines(tc):
    out = []
    for p in tc.findall(W + 'p'):
        raw = ptext(p); txt = clean(raw)
        numid, ilvl, ind, _ = pmeta(p)
        bernomor = numid is not None
        if not txt:
            continue
        lbl = ''
        if bernomor:
            lbl, _n = label_untuk(numid, ilvl or 0)
        out.append({'text': txt, 'level': level_of(ilvl, ind, bernomor), 'label': lbl})
    return out

def cell_text(tc):
    return clean(' '.join(ptext(p) for p in tc.findall(W + 'p')))

def cell_multiline(tc):
    ls = [clean(ptext(p)) for p in tc.findall(W + 'p')]
    return '\n'.join([l for l in ls if l])

def slug(s):
    s = unicodedata.normalize('NFKD', s).encode('ascii', 'ignore').decode()
    return re.sub(r'[^a-zA-Z0-9]+', '_', s).strip('_').lower()[:40]

blocks = []
for ch in body:
    if ch.tag == W + 'p':
        blocks.append(('P', pmeta(ch)[3], clean(ptext(ch)), ptext(ch)))
    elif ch.tag == W + 'tbl':
        blocks.append(('T', '', ch, None))

data = {'sections': [], 'tables': {}, 'data_dasar': []}

# --- data dasar ---
mulai = False
for kind, style, val, raw in blocks:
    if kind != 'P':
        continue
    if val == 'Data Dasar Rumah Sakit':
        mulai = True; continue
    if mulai:
        if val == 'Data Kompetensi Layanan':
            break
        if ':' in val:
            k, v = val.split(':', 1)
            data['data_dasar'].append({'key': slug(k), 'label': clean(k), 'value': clean(v)})

# --- tabel profil ---
def rows_of(tbl):
    return [tr.findall(W + 'tc') for tr in tbl.findall(W + 'tr')]

def grab(tbl, skip_header=True):
    return [[cell_text(tc) for tc in r] for i, r in enumerate(rows_of(tbl)) if not (skip_header and i == 0)]

tbl_list = []
for b in blocks:
    if b[0] == 'T':
        hdr = ' | '.join(cell_text(tc) for tc in rows_of(b[2])[0])
        tbl_list.append((hdr, b[2]))

komp = []
for idx, (hdr, t) in enumerate(tbl_list):
    if hdr.startswith('No | Jenis Layanan | Kompetensi'):
        komp += grab(t)
        nh, ntb = tbl_list[idx + 1]
        if nh.startswith('21'):
            komp += grab(ntb, skip_header=False)
        break
data['tables']['kompetensi'] = {'rows': komp}

for hdr, t in tbl_list:
    if hdr.startswith('No | Jenis Tempat Tidur'):
        data['tables']['tempat_tidur'] = {'rows': grab(t)}; break

per = [r for hdr, t in tbl_list if hdr.startswith('No | Dokumen | Nomor | Tahun') for r in grab(t)]
data['tables']['perizinan'] = {'rows': per}

for hdr, t in tbl_list:
    if hdr.startswith('1 | Luas Tanah'):
        data['tables']['sarana'] = {'rows': grab(t, skip_header=False)}; break

for hdr, t in tbl_list:
    if hdr.startswith('No | Data Kegiatan'):
        data['tables']['kinerja'] = {'rows': grab(t)}; break

pens = [t for hdr, t in tbl_list if hdr.startswith('No | Diagnosa Penyakit')]
if pens:      data['tables']['penyakit_rj'] = {'rows': grab(pens[0])}
if len(pens) > 1: data['tables']['penyakit_ri'] = {'rows': grab(pens[1])}

data['tables']['sdm_detail'] = {'rows': [r for hdr, t in tbl_list
                                         if hdr.startswith('No | Jenis Layanan | Standar') for r in grab(t)]}
data['tables']['rekap_sdm'] = {'rows': [r for hdr, t in tbl_list
                                        if hdr.startswith('No | Tenaga | Jumlah') for r in grab(t)]}

# --- checklist ---
SEC = ['PENYELENGGARAAN LAYANAN', 'SUB BAGIAN TATA USAHA', 'SUMBER DAYA KESEHATAN',
       'KESEHATAN MASYARAKAT', 'PENCEGAHAN DAN PENGENDALIAN PENYAKIT', 'SUMBER DAYA MANUSIA KESEHATAN']
cur = None
for kind, style, val, raw in blocks:
    if kind == 'P':
        if val in SEC:
            cur = {'code': slug(val), 'title': val, 'subtitle': '', 'items': []}
            data['sections'].append(cur)
        elif cur is not None and val == 'PELAYANAN KESEHATAN':
            cur['subtitle'] = val
        continue
    rows = rows_of(val)
    if 'Unit Pelayanan' not in ' | '.join(cell_text(tc) for tc in rows[0]) or cur is None:
        continue
    for r in rows[1:]:
        if len(r) < 2:
            continue
        no = cell_text(r[0])
        lines = cell_lines(r[1])
        hasil = cell_multiline(r[2]) if len(r) > 2 else ''
        if not lines and not no:
            continue
        if no:
            item = {'no': no.rstrip('.'), 'title': lines[0]['text'] if lines else '',
                    'hasil': hasil, 'children': []}
            cur['items'].append(item); rest = lines[1:]
        else:
            if not cur['items']:
                item = {'no': '', 'title': lines[0]['text'] if lines else '',
                        'hasil': hasil, 'children': []}
                cur['items'].append(item); rest = lines[1:]
            else:
                item = cur['items'][-1]
                if hasil and not item['hasil']:
                    item['hasil'] = hasil
                rest = lines
        # Bangun ulang jalur induk dari anak terakhir, agar baris lanjutan di
        # tabel berikutnya tetap bersarang pada poin yang benar.
        stack = {0: item}
        _n = item; _l = 0
        while _n['children']:
            _n = _n['children'][-1]; _l += 1
            stack[_l] = _n
        for ln in rest:
            lv = max(1, ln['level'])
            node = {'text': ln['text'], 'label': ln['label'], 'children': []}
            parent = None
            for L in range(lv - 1, -1, -1):
                if L in stack:
                    parent = stack[L]; break
            (parent or item)['children'].append(node)
            stack[lv] = node
            for k in [k for k in list(stack) if k > lv]:
                del stack[k]

json.dump(data, open(OUT, 'w'), ensure_ascii=False, indent=1)

tot = 0
for s in data['sections']:
    def cnt(n): return 1 + sum(cnt(c) for c in n['children'])
    c = sum(cnt(i) for i in s['items']); tot += c
    print(f"{s['title']}: {len(s['items'])} item, {c} node")
print('TOTAL', tot)
