/* =====================================================================
   Modul Dokumen Internal — interaksi halaman

   • isian kepala naskah dan blok isi tersimpan sendiri saat berhenti mengetik
   • hasil scan diunggah satu per satu dengan jendela progres berpersentase
     yang mengunci layar sampai selesai atau dibatalkan
   ===================================================================== */
(function () {
    'use strict';

    var elId = document.getElementById('dok-id');
    var elZona = document.getElementById('dok-zona');
    var punyaEditor = !!(elId || elZona);
    if (!punyaEditor) { return; }

    var DOK_ID = elId ? elId.value : (elZona ? elZona.getAttribute('data-id') : '');
    var CSRF = document.querySelector('meta[name="csrf"]');
    CSRF = CSRF ? CSRF.content : '';

    // Alamat titik-akhir AJAX mengacu ke halaman yang sedang dibuka, bukan URL
    // relatif — agar tetap benar bila aplikasi dipasang di sub-folder.
    var ENDPOINT = window.location.pathname;

    function meta(nama, bawaan) {
        var m = document.querySelector('meta[name="' + nama + '"]');
        return m ? m.content : bawaan;
    }
    var BATAS_UNGGAH   = parseInt(meta('unggah-maks', '0'), 10) || 0;
    var EKSTENSI_OK    = meta('unggah-ext', '').split(',').filter(Boolean);
    var EKSTENSI_TOLAK = meta('unggah-blokir', '').split(',').filter(Boolean);
    var SEMUA_JENIS    = EKSTENSI_OK.length === 0 || EKSTENSI_OK.indexOf('*') >= 0;

    // ------------------------------------------------------------- util
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
        }, galat ? 6000 : 1800);
    }

    function post(page, data) {
        var fd = new FormData();
        Object.keys(data).forEach(function (k) { fd.append(k, data[k]); });
        fd.append('csrf', CSRF);
        return fetch(ENDPOINT + '?p=' + page, { method: 'POST', body: fd, credentials: 'same-origin' })
            .then(function (r) {
                return r.json().catch(function () {
                    return { ok: false, pesan: 'Balasan server tidak valid (HTTP ' + r.status + ').' };
                });
            });
    }

    function debounce(fn, ms) {
        var t;
        return function () {
            var a = arguments, k = this;
            clearTimeout(t);
            t = setTimeout(function () { fn.apply(k, a); }, ms);
        };
    }

    function ukuranTeks(b) {
        var satuan = ['B', 'KB', 'MB', 'GB'], i = 0, n = b;
        while (n >= 1024 && i < satuan.length - 1) { n /= 1024; i++; }
        return (i === 0 ? n : n.toFixed(1).replace('.', ',')) + ' ' + satuan[i];
    }

    // =================================================================
    //  Penyimpanan otomatis
    // =================================================================

    function simpan(page, data, sebutan) {
        post(page, data).then(function (r) {
            toast(r.ok ? sebutan + ' tersimpan ' + r.waktu : (r.pesan || 'Gagal menyimpan'), !r.ok);
        }).catch(function () { toast('Gagal menghubungi server', true); });
    }

    var simpanKepala = debounce(function (el) {
        simpan('api_dok_kepala', { id: DOK_ID, k: el.getAttribute('data-dok-kepala'), v: el.value }, 'Isian');
    }, 700);

    var simpanBlok = debounce(function (el) {
        simpan('api_dok_isi', { id: DOK_ID, blok: el.getAttribute('data-dok-blok'), isi: el.value }, 'Isi naskah');
    }, 900);

    document.addEventListener('input', function (ev) {
        var el = ev.target;
        if (el.hasAttribute && el.hasAttribute('data-dok-kepala')) { simpanKepala(el); }
        else if (el.hasAttribute && el.hasAttribute('data-dok-blok')) { simpanBlok(el); }
    });

    // pilihan dan tanggal disimpan seketika, tanpa menunggu jeda mengetik
    document.addEventListener('change', function (ev) {
        var el = ev.target;
        if (!el.hasAttribute || !el.hasAttribute('data-dok-kepala')) { return; }
        if (el.tagName === 'SELECT' || el.type === 'date') {
            simpan('api_dok_kepala', { id: DOK_ID, k: el.getAttribute('data-dok-kepala'), v: el.value }, 'Isian');
        }
    });

    // =================================================================
    //  Unggah hasil scan
    // =================================================================

    var input   = document.getElementById('dok-pemilih');
    var elDaftarBerkas = document.getElementById('dok-berkas');
    var elKosong = document.getElementById('dok-kosong');
    var elFolder = document.getElementById('dok-folder');
    var elKategori = document.getElementById('dok-kategori');

    var uLatar  = document.getElementById('unggah-latar');
    var uModal  = document.getElementById('modal-unggah');
    var uJudul  = document.getElementById('unggah-judul');
    var uHitung = document.getElementById('unggah-hitung');
    var uNama   = document.getElementById('unggah-nama');
    var uBar    = uModal ? uModal.querySelector('.unggah-bar') : null;
    var uIsi    = document.getElementById('unggah-isi');
    var uPersen = document.getElementById('unggah-persen');
    var uUkuran = document.getElementById('unggah-ukuran');
    var uDaftar = document.getElementById('unggah-daftar');
    var uInfo   = document.getElementById('unggah-info');
    var uBatal  = document.getElementById('unggah-batal');
    var uTutup  = document.getElementById('unggah-tutup');

    var sedangUnggah = false;
    var dibatalkan   = false;
    var xhrAktif     = null;

    function cegahTutup(ev) {
        ev.preventDefault();
        ev.returnValue = 'Unggahan sedang berjalan.';
        return ev.returnValue;
    }

    function bukaProgres(daftar) {
        sedangUnggah = true;
        dibatalkan = false;
        uDaftar.innerHTML = '';
        daftar.forEach(function (f, i) {
            var li = document.createElement('li');
            li.setAttribute('data-i', i);
            li.innerHTML = '<span class="tanda">•</span>'
                + '<span class="berkas-nama"></span>'
                + '<span class="catatan"></span>';
            li.querySelector('.berkas-nama').textContent = f.name;
            li.querySelector('.catatan').textContent = ukuranTeks(f.size);
            uDaftar.appendChild(li);
        });
        uJudul.textContent = 'Mengunggah berkas…';
        uInfo.textContent = 'Mohon tunggu — jangan menutup halaman.';
        uBar.className = 'unggah-bar';
        uBatal.style.display = '';
        uBatal.disabled = false;
        uTutup.style.display = 'none';
        uLatar.classList.add('tampil');
        uModal.classList.add('tampil');
        window.addEventListener('beforeunload', cegahTutup);
    }

    function perbaruiProgres(persen, terkirim, total, namaBerkas, ke, jumlah) {
        persen = Math.max(0, Math.min(100, persen));
        uIsi.style.width = persen.toFixed(1) + '%';
        uPersen.textContent = Math.round(persen) + '%';
        uUkuran.textContent = ukuranTeks(terkirim) + ' dari ' + ukuranTeks(total);
        uNama.textContent = namaBerkas;
        uHitung.textContent = 'Berkas ke-' + ke + ' dari ' + jumlah;
    }

    function tandaiBerkas(i, keadaan, catatan) {
        var li = uDaftar.querySelector('li[data-i="' + i + '"]');
        if (!li) { return; }
        li.className = keadaan === 'gagal' ? 'gagal' : (keadaan === 'jalan' ? 'jalan' : '');
        li.querySelector('.tanda').textContent =
            keadaan === 'selesai' ? '✅' : (keadaan === 'gagal' ? '❌' : (keadaan === 'jalan' ? '⏳' : '•'));
        if (catatan !== undefined) { li.querySelector('.catatan').textContent = catatan; }
    }

    function tutupProgres() {
        sedangUnggah = false;
        xhrAktif = null;
        uLatar.classList.remove('tampil');
        uModal.classList.remove('tampil');
        window.removeEventListener('beforeunload', cegahTutup);
    }

    function selesaiProgres(berhasil, gagal) {
        sedangUnggah = false;
        xhrAktif = null;
        window.removeEventListener('beforeunload', cegahTutup);
        uBatal.style.display = 'none';
        uTutup.style.display = '';
        uNama.textContent = '';

        if (dibatalkan) {
            uJudul.textContent = 'Unggahan dibatalkan';
            uBar.className = 'unggah-bar gagal';
            uInfo.textContent = berhasil + ' berkas terlanjur tersimpan.';
        } else if (gagal > 0) {
            uJudul.textContent = 'Selesai dengan ' + gagal + ' kegagalan';
            uBar.className = 'unggah-bar gagal';
            uInfo.textContent = berhasil + ' berhasil, ' + gagal + ' gagal.';
        } else {
            uJudul.textContent = 'Unggahan selesai';
            uBar.className = 'unggah-bar selesai';
            uIsi.style.width = '100%';
            uPersen.textContent = '100%';
            uInfo.textContent = berhasil + ' berkas tersimpan.';
            setTimeout(function () { if (!sedangUnggah) { tutupProgres(); } }, 900);
        }
    }

    /** Alasan berkas ditolak sebelum dikirim, atau null bila lolos. */
    function periksaBerkas(f) {
        var ext = (f.name.indexOf('.') >= 0 ? f.name.split('.').pop() : '').toLowerCase();
        if (!ext) { return 'berkas tanpa ekstensi tidak dapat diunggah'; }
        if (EKSTENSI_TOLAK.indexOf(ext) >= 0) {
            return 'jenis berkas .' + ext + ' tidak diizinkan demi keamanan server';
        }
        if (!SEMUA_JENIS && EKSTENSI_OK.indexOf(ext) < 0) {
            return 'jenis berkas .' + ext + ' tidak termasuk daftar yang diizinkan';
        }
        if (BATAS_UNGGAH && f.size > BATAS_UNGGAH) {
            return 'melebihi batas ' + ukuranTeks(BATAS_UNGGAH);
        }
        return null;
    }

    function kirimBerkas(file, kategori, onKemajuan) {
        return new Promise(function (resolve) {
            var fd = new FormData();
            fd.append('csrf', CSRF);
            fd.append('id', DOK_ID);
            fd.append('kategori', kategori);
            fd.append('berkas[]', file);

            var xhr = new XMLHttpRequest();
            xhrAktif = xhr;
            xhr.open('POST', ENDPOINT + '?p=api_dok_unggah', true);
            xhr.withCredentials = true;

            xhr.upload.onprogress = function (e) {
                if (e.lengthComputable) { onKemajuan(Math.min(e.loaded, file.size)); }
            };
            xhr.upload.onload = function () {
                onKemajuan(file.size);
                uInfo.textContent = 'Berkas terkirim, sedang diproses server…';
            };
            xhr.onload = function () {
                var r;
                try { r = JSON.parse(xhr.responseText); }
                catch (e) { r = { ok: false, pesan: 'Balasan server tidak valid (HTTP ' + xhr.status + ').' }; }
                resolve(r);
            };
            xhr.onerror = function () { resolve({ ok: false, pesan: 'Koneksi ke server terputus.' }); };
            xhr.onabort = function () { resolve({ ok: false, dibatalkan: true, pesan: 'Dibatalkan.' }); };
            xhr.send(fd);
        });
    }

    function tambahBaris(b) {
        var li = document.createElement('li');
        li.setAttribute('data-id', b.id);
        var a = document.createElement('a');
        a.href = b.link;
        a.target = '_blank';
        a.rel = 'noopener';
        a.textContent = b.nama;

        var nb = document.createElement('span');
        nb.className = 'nb';
        nb.textContent = b.ikon + ' ';
        nb.appendChild(a);

        var meta = document.createElement('div');
        meta.className = 'meta';
        meta.textContent = (b.kategori === 'scan' ? 'Hasil scan' : 'Lampiran') + ' · ' + b.ukuran + ' · baru saja · ';
        var hapus = document.createElement('a');
        hapus.href = '#';
        hapus.className = 'hapus-berkas';
        hapus.setAttribute('data-id', b.id);
        hapus.textContent = 'Hapus';
        meta.appendChild(hapus);

        li.appendChild(nb);
        li.appendChild(meta);
        elDaftarBerkas.appendChild(li);
        if (elKosong) { elKosong.style.display = 'none'; }
    }

    function gambarFolder(folder) {
        if (!elFolder || !folder || !folder.link) { return; }
        elFolder.innerHTML = '';
        var a = document.createElement('a');
        a.className = 'chip-folder' + (folder.lokal ? ' lokal' : '');
        a.href = folder.link;
        a.target = '_blank';
        a.rel = 'noopener';
        a.textContent = '📂 ' + folder.nama;
        elFolder.appendChild(a);
    }

    function unggah(files) {
        if (!files || !files.length || sedangUnggah) { return; }
        var kategori = elKategori ? elKategori.value : 'scan';

        var daftar = [], ditolak = [];
        Array.prototype.slice.call(files).forEach(function (f) {
            var alasan = periksaBerkas(f);
            if (alasan) { ditolak.push(f.name + ': ' + alasan); } else { daftar.push(f); }
        });
        if (ditolak.length) { toast(ditolak.length + ' berkas ditolak — ' + ditolak[0], true); }
        if (!daftar.length) { return; }

        var total = daftar.reduce(function (n, f) { return n + f.size; }, 0);
        var selesai = 0, berhasil = 0, gagal = 0, folderTerakhir = null;
        bukaProgres(daftar);

        function berikutnya(i) {
            if (i >= daftar.length || dibatalkan) {
                if (folderTerakhir) { gambarFolder(folderTerakhir); }
                selesaiProgres(berhasil, gagal);
                return;
            }
            var f = daftar[i];
            tandaiBerkas(i, 'jalan');
            perbaruiProgres(total ? (selesai / total) * 100 : 0, selesai, total, f.name, i + 1, daftar.length);

            kirimBerkas(f, kategori, function (terkirim) {
                perbaruiProgres(total ? ((selesai + terkirim) / total) * 100 : 0,
                    selesai + terkirim, total, f.name, i + 1, daftar.length);
            }).then(function (r) {
                selesai += f.size;
                if (r && r.ok && r.berkas && r.berkas.length) {
                    berhasil += r.berkas.length;
                    r.berkas.forEach(tambahBaris);
                    if (r.folder) { folderTerakhir = r.folder; }
                    tandaiBerkas(i, 'selesai', 'tersimpan');
                } else if (r && r.dibatalkan) {
                    tandaiBerkas(i, 'gagal', 'dibatalkan');
                } else {
                    gagal++;
                    tandaiBerkas(i, 'gagal', (r && (r.gagal && r.gagal[0] || r.pesan)) || 'gagal');
                }
                berikutnya(i + 1);
            });
        }
        berikutnya(0);
    }

    if (elZona && input) {
        elZona.addEventListener('click', function () { input.click(); });
        input.addEventListener('change', function () {
            unggah(input.files);
            input.value = '';
        });
        ['dragenter', 'dragover'].forEach(function (n) {
            elZona.addEventListener(n, function (ev) { ev.preventDefault(); elZona.classList.add('aktif'); });
        });
        ['dragleave', 'drop'].forEach(function (n) {
            elZona.addEventListener(n, function (ev) { ev.preventDefault(); elZona.classList.remove('aktif'); });
        });
        elZona.addEventListener('drop', function (ev) {
            if (ev.dataTransfer && ev.dataTransfer.files) { unggah(ev.dataTransfer.files); }
        });
    }

    if (uBatal) {
        uBatal.addEventListener('click', function () {
            dibatalkan = true;
            uBatal.disabled = true;
            uInfo.textContent = 'Membatalkan…';
            if (xhrAktif) { xhrAktif.abort(); }
        });
    }
    if (uTutup) { uTutup.addEventListener('click', tutupProgres); }
    if (uLatar) {
        uLatar.addEventListener('click', function () { if (!sedangUnggah) { tutupProgres(); } });
    }

    // ----------------------------------------------------------- hapus
    if (elDaftarBerkas) {
        elDaftarBerkas.addEventListener('click', function (ev) {
            var t = ev.target;
            if (!t.classList || !t.classList.contains('hapus-berkas')) { return; }
            ev.preventDefault();
            var id = t.getAttribute('data-id');
            if (!confirm('Hapus berkas ini dari penyimpanan?')) { return; }
            post('api_dok_hapus_berkas', { id: id }).then(function (r) {
                if (!r.ok) { toast(r.pesan || 'Gagal menghapus', true); return; }
                var li = elDaftarBerkas.querySelector('li[data-id="' + id + '"]');
                if (li) { li.parentNode.removeChild(li); }
                if (!elDaftarBerkas.children.length && elKosong) { elKosong.style.display = ''; }
                toast('Berkas dihapus');
            }).catch(function () { toast('Gagal menghubungi server', true); });
        });
    }
}());
