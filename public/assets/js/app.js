/* =====================================================================
   Self Assessment Binwasdal RS — interaksi halaman
   ===================================================================== */
(function () {
    'use strict';

    var CSRF = document.querySelector('meta[name="csrf"]');
    CSRF = CSRF ? CSRF.content : '';

    // ------------------------------------------------------------ util
    var statusEl = null;

    function toast(pesan, galat) {
        if (!statusEl) {
            statusEl = document.createElement('div');
            statusEl.className = 'simpan-status';
            document.body.appendChild(statusEl);
        }
        statusEl.textContent = pesan;
        statusEl.className = 'simpan-status tampil' + (galat ? ' galat' : '');
        clearTimeout(statusEl._t);
        statusEl._t = setTimeout(function () {
            statusEl.className = 'simpan-status';
        }, galat ? 6000 : 1600);
    }

    function post(page, data) {
        var fd = data instanceof FormData ? data : new FormData();
        if (!(data instanceof FormData)) {
            Object.keys(data).forEach(function (k) { fd.append(k, data[k]); });
        }
        fd.append('csrf', CSRF);
        return fetch('?p=' + page, { method: 'POST', body: fd, credentials: 'same-origin' })
            .then(function (r) { return r.json().catch(function () { return { ok: false, pesan: 'Balasan server tidak valid (HTTP ' + r.status + ').' }; }); });
    }

    function debounce(fn, ms) {
        var t;
        return function () {
            var a = arguments, k = this;
            clearTimeout(t);
            t = setTimeout(function () { fn.apply(k, a); }, ms);
        };
    }

    function autosize(ta) {
        ta.style.height = 'auto';
        ta.style.height = (ta.scrollHeight + 2) + 'px';
    }

    // -------------------------------------------------- simpan otomatis
    var simpanJawaban = debounce(function (el) {
        var wrap = el.closest('[data-item]');
        var itemId = wrap.getAttribute('data-item');
        var status = wrap.querySelector('select.status');
        var ket = wrap.querySelector('textarea.ket');
        post('api_jawaban', {
            item_id: itemId,
            status: status ? status.value : '',
            keterangan: ket ? ket.value : ''
        }).then(function (r) {
            toast(r.ok ? 'Tersimpan ' + (r.waktu || '') : (r.pesan || 'Gagal menyimpan'), !r.ok);
        }).catch(function () { toast('Gagal menghubungi server', true); });
    }, 550);

    var simpanSel = debounce(function (el) {
        post('api_sel', {
            table_code: el.getAttribute('data-tabel'),
            row_no: el.getAttribute('data-baris'),
            col_code: el.getAttribute('data-kolom'),
            v: el.innerText.replace(/ /g, ' ').trim()
        }).then(function (r) {
            toast(r.ok ? 'Tersimpan ' + (r.waktu || '') : (r.pesan || 'Gagal menyimpan'), !r.ok);
        }).catch(function () { toast('Gagal menghubungi server', true); });
    }, 550);

    var simpanProfil = debounce(function (el) {
        post('api_profil', {
            k: el.getAttribute('data-profil'),
            v: el.innerText.replace(/ /g, ' ').trim()
        }).then(function (r) {
            toast(r.ok ? 'Tersimpan ' + (r.waktu || '') : (r.pesan || 'Gagal menyimpan'), !r.ok);
        }).catch(function () { toast('Gagal menghubungi server', true); });
    }, 550);

    document.addEventListener('input', function (ev) {
        var t = ev.target;
        if (t.matches('textarea.ket')) { autosize(t); simpanJawaban(t); }
        else if (t.matches('.sel[data-tabel]')) { simpanSel(t); }
        else if (t.matches('.sel[data-profil]')) { simpanProfil(t); }
    });

    document.addEventListener('change', function (ev) {
        var t = ev.target;
        if (t.matches('select.status')) {
            t.className = 'status s-' + (t.value || 'kosong');
            simpanJawaban(t);
        }
    });

    // sel contenteditable: cegah tempel format & baris baru berlebihan
    document.addEventListener('paste', function (ev) {
        var t = ev.target;
        if (t.matches && t.matches('.sel')) {
            ev.preventDefault();
            var teks = (ev.clipboardData || window.clipboardData).getData('text/plain');
            document.execCommand('insertText', false, teks.replace(/\r?\n/g, ' '));
        }
    });
    document.addEventListener('keydown', function (ev) {
        if (ev.key === 'Enter' && ev.target.matches && ev.target.matches('.sel')) {
            ev.preventDefault();
            ev.target.blur();
        }
    });

    // ------------------------------------------------------------ unggah
    var input = document.getElementById('pemilih-berkas');
    var targetAktif = null;

    function kotakBerkas(wrap) {
        var ul = wrap.querySelector('ul.berkas');
        if (!ul) {
            ul = document.createElement('ul');
            ul.className = 'berkas';
            wrap.querySelector('.kendali').appendChild(ul);
        }
        return ul;
    }

    function tampilkanFolder(wrap, folder) {
        if (!folder) return;
        var ringkas = wrap.getAttribute('data-ringkas') === '1';
        var kendali = wrap.querySelector('.kendali');
        var chip = wrap.querySelector('.chip-folder');
        if (!chip) {
            chip = document.createElement('a');
            chip.target = '_blank';
            chip.rel = 'noopener';
            var baris = wrap.querySelector('.baris-folder');
            if (!baris) {
                baris = document.createElement('div');
                baris.className = 'baris-kendali baris-folder';
                kendali.appendChild(baris);
            }
            baris.appendChild(chip);
        }
        if (folder.lokal) {
            chip.className = 'chip-folder lokal';
            chip.textContent = ringkas ? '📁 Lokal' : '📁 Tersimpan lokal (Drive belum aktif)';
            chip.title = 'Google Drive belum aktif — berkas tersimpan di server';
            chip.removeAttribute('href');
        } else {
            chip.className = 'chip-folder';
            chip.textContent = ringkas ? '📂 Folder Drive' : '📂 Buka folder Google Drive';
            chip.title = 'Buka folder Google Drive';
            chip.href = folder.link;
        }
    }

    function tambahBerkas(wrap, b) {
        var ul = kotakBerkas(wrap);
        var li = document.createElement('li');
        li.setAttribute('data-doc', b.id);
        li.innerHTML = b.ikon + ' '
            + '<a target="_blank" rel="noopener"></a> '
            + '<span class="ukuran"></span> '
            + '<button type="button" class="hapus no-print" title="Hapus berkas">✕</button>';
        var a = li.querySelector('a');
        a.href = b.link;
        a.textContent = b.nama;
        li.querySelector('.ukuran').textContent = b.ukuran;
        ul.appendChild(li);
    }

    function unggah(wrap, files) {
        if (!files || !files.length) return;
        var fd = new FormData();
        fd.append('owner_type', wrap.getAttribute('data-owner-type'));
        fd.append('owner_key', wrap.getAttribute('data-owner-key'));
        for (var i = 0; i < files.length; i++) fd.append('berkas[]', files[i]);

        var tombol = wrap.querySelector('.btn-unggah');
        var labelAsli = tombol ? tombol.innerHTML : '';
        if (tombol) { tombol.disabled = true; tombol.innerHTML = '⏳ Mengunggah…'; }
        toast('Mengunggah ' + files.length + ' berkas…');

        post('api_unggah', fd).then(function (r) {
            if (tombol) { tombol.disabled = false; tombol.innerHTML = labelAsli; }
            if (r.folder) tampilkanFolder(wrap, r.folder);
            (r.berkas || []).forEach(function (b) { tambahBerkas(wrap, b); });
            if (r.gagal && r.gagal.length) {
                toast(r.gagal.join(' | '), true);
            } else {
                toast(r.pesan || 'Selesai');
            }
        }).catch(function () {
            if (tombol) { tombol.disabled = false; tombol.innerHTML = labelAsli; }
            toast('Gagal mengunggah berkas', true);
        });
    }

    document.addEventListener('click', function (ev) {
        var t = ev.target.closest('.btn-unggah');
        if (t) {
            targetAktif = t.closest('[data-owner-key]');
            input.value = '';
            input.click();
            return;
        }

        var f = ev.target.closest('.btn-folder');
        if (f) {
            var wrap = f.closest('[data-owner-key]');
            f.disabled = true;
            post('api_folder', {
                owner_type: wrap.getAttribute('data-owner-type'),
                owner_key: wrap.getAttribute('data-owner-key')
            }).then(function (r) {
                f.disabled = false;
                if (r.ok) {
                    tampilkanFolder(wrap, r.folder);
                    if (r.folder.link) window.open(r.folder.link, '_blank', 'noopener');
                    toast('Folder siap: ' + r.folder.nama);
                } else {
                    toast(r.pesan, true);
                }
            }).catch(function () { f.disabled = false; toast('Gagal membuat folder', true); });
            return;
        }

        var h = ev.target.closest('.hapus');
        if (h) {
            var li = h.closest('li[data-doc]');
            if (!confirm('Hapus berkas ini? Berkas juga dihapus dari Google Drive.')) return;
            post('api_hapus_berkas', { id: li.getAttribute('data-doc') }).then(function (r) {
                if (r.ok) { li.remove(); toast('Berkas dihapus'); }
                else toast(r.pesan, true);
            });
        }
    });

    if (input) {
        input.addEventListener('change', function () {
            if (targetAktif) unggah(targetAktif, input.files);
        });
    }

    // seret & lepas berkas ke baris poin
    ['dragenter', 'dragover'].forEach(function (t) {
        document.addEventListener(t, function (ev) {
            var w = ev.target.closest ? ev.target.closest('[data-owner-key]') : null;
            if (!w) return;
            ev.preventDefault();
            w.classList.add('drop-aktif');
        });
    });
    document.addEventListener('dragleave', function (ev) {
        var w = ev.target.closest ? ev.target.closest('[data-owner-key]') : null;
        if (w) w.classList.remove('drop-aktif');
    });
    document.addEventListener('drop', function (ev) {
        var w = ev.target.closest ? ev.target.closest('[data-owner-key]') : null;
        if (!w) return;
        ev.preventDefault();
        w.classList.remove('drop-aktif');
        unggah(w, ev.dataTransfer.files);
    });

    // ------------------------------------------------- penyesuaian awal
    document.querySelectorAll('textarea.ket').forEach(autosize);

    // pencarian cepat di dalam bagian
    var cari = document.getElementById('cari-poin');
    if (cari) {
        cari.addEventListener('input', debounce(function () {
            var q = cari.value.toLowerCase().trim();
            document.querySelectorAll('tr.poin').forEach(function (tr) {
                if (!q) { tr.style.display = ''; return; }
                tr.style.display = tr.innerText.toLowerCase().indexOf(q) >= 0 ? '' : 'none';
            });
        }, 200));
    }

    // saring berdasarkan status
    var saring = document.getElementById('saring-status');
    if (saring) {
        saring.addEventListener('change', function () {
            var v = saring.value;
            document.querySelectorAll('tr.poin').forEach(function (tr) {
                if (v === '') { tr.style.display = ''; return; }
                var s = tr.querySelector('select.status');
                var punyaBerkas = tr.querySelector('ul.berkas li');
                var cocok;
                if (v === 'belum') cocok = (!s || s.value === '') && !punyaBerkas;
                else if (v === 'ada_berkas') cocok = !!punyaBerkas;
                else cocok = s && s.value === v;
                tr.style.display = cocok ? '' : 'none';
            });
        });
    }
})();
