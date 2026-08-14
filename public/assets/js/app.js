/* =====================================================================
   Self Assessment Binwasdal RS — interaksi halaman

   Kolom "Hasil Self Assessment" hanya menampilkan penanda ringkas.
   Pengisian keterangan dan pengelolaan dokumen dilakukan lewat popup.
   ===================================================================== */
(function () {
    'use strict';

    var CSRF = document.querySelector('meta[name="csrf"]');
    CSRF = CSRF ? CSRF.content : '';

    // Alamat titik-akhir AJAX selalu mengacu ke halaman aplikasi yang sedang
    // dibuka, bukan URL relatif — agar tetap benar di sub-folder mana pun.
    var ENDPOINT = window.location.pathname;

    function meta(nama, bawaan) {
        var m = document.querySelector('meta[name="' + nama + '"]');
        return m ? m.content : bawaan;
    }
    var BATAS_UNGGAH  = parseInt(meta('unggah-maks', '0'), 10) || 0;
    var EKSTENSI_OK   = meta('unggah-ext', '').split(',').filter(Boolean);
    var EKSTENSI_TOLAK = meta('unggah-blokir', '').split(',').filter(Boolean);
    var SEMUA_JENIS   = EKSTENSI_OK.length === 0 || EKSTENSI_OK.indexOf('*') >= 0;

    // ------------------------------------------------- laci menu (ponsel)
    (function () {
        var tombol = document.getElementById('tombol-menu');
        var laci   = document.getElementById('sidebar');
        var kain   = document.getElementById('sidebar-latar');
        if (!tombol || !laci || !kain) { return; }

        function setel(buka) {
            laci.classList.toggle('buka', buka);
            kain.classList.toggle('tampil', buka);
            tombol.setAttribute('aria-expanded', buka ? 'true' : 'false');
            document.body.style.overflow = buka ? 'hidden' : '';
        }
        tombol.addEventListener('click', function () {
            setel(laci.classList.contains('buka') === false);
        });
        kain.addEventListener('click', function () { setel(false); });
        laci.addEventListener('click', function (ev) {
            if (ev.target.closest('a')) { setel(false); }
        });
        document.addEventListener('keydown', function (ev) {
            if (ev.key === 'Escape' && laci.classList.contains('buka')) { setel(false); }
        });
        window.addEventListener('resize', function () {
            if (window.innerWidth > 1000 && laci.classList.contains('buka')) { setel(false); }
        });
    })();

    // menandai tabel yang isinya lebih lebar dari layar (petunjuk geser)
    function tandaiTabelGulir() {
        document.querySelectorAll('.tabel-gulir').forEach(function (w) {
            w.classList.toggle('bisa-gulir', w.scrollWidth > w.clientWidth + 2);
        });
    }
    window.addEventListener('load', tandaiTabelGulir);
    window.addEventListener('resize', debounceAwal(tandaiTabelGulir, 200));

    function debounceAwal(fn, ms) {
        var t;
        return function () { clearTimeout(t); t = setTimeout(fn, ms); };
    }

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
        var fd = data instanceof FormData ? data : new FormData();
        if (!(data instanceof FormData)) {
            Object.keys(data).forEach(function (k) { fd.append(k, data[k]); });
        }
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

    function labelStatus(s) {
        return {
            ada: 'Ada / Sesuai',
            sebagian: 'Sebagian',
            tidak_ada: 'Belum Ada',
            na: 'Tidak Berlaku'
        }[s] || '';
    }

    // ------------------------------- isian langsung pada tabel profil
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
        if (t.matches('.sel[data-tabel]')) { simpanSel(t); }
        else if (t.matches('.sel[data-profil]')) { simpanProfil(t); }
    });

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

    // =================================================================
    //  Popup pengisian poin
    // =================================================================
    var latar    = document.getElementById('modal-latar');
    var modal    = document.getElementById('modal-poin');
    var input    = document.getElementById('pemilih-berkas');
    if (!modal) { return; }

    var elJudul   = document.getElementById('modal-judul');
    var elKonteks = document.getElementById('modal-konteks');
    var elKet     = document.getElementById('modal-ket');
    var elStatus  = document.getElementById('pilih-status');
    var elBlok    = document.getElementById('blok-status');
    var elZona    = document.getElementById('zona-unggah');
    var elFolder  = document.getElementById('modal-folder');
    var elBerkas  = document.getElementById('modal-berkas');
    var elKosong  = document.getElementById('modal-kosong');
    var elInfo    = document.getElementById('modal-info');

    var aktif = null;      // { sel, ownerType, ownerKey, itemId, punyaStatus, status, berkas }
    var berubah = false;
    var sedangUnggah = false;   // true selama unggahan berjalan — mengunci penutupan

    // ------------------------------------------------------- buka/tutup
    function buka(sel) {
        aktif = {
            sel: sel,
            ownerType: sel.getAttribute('data-owner-type'),
            ownerKey: sel.getAttribute('data-owner-key'),
            itemId: sel.getAttribute('data-item'),
            punyaStatus: true,
            status: '',
            berkas: []
        };
        berubah = false;

        elJudul.textContent = 'Memuat…';
        elKonteks.textContent = '';
        elKet.value = '';
        elBerkas.innerHTML = '';
        elFolder.innerHTML = '';
        elKosong.style.display = 'none';
        elInfo.textContent = '';
        pilihStatus('');

        latar.classList.add('tampil');
        modal.classList.add('tampil');
        document.body.style.overflow = 'hidden';

        post('api_detail', { owner_type: aktif.ownerType, owner_key: aktif.ownerKey })
            .then(function (r) {
                if (!r.ok) { elJudul.textContent = r.pesan || 'Gagal memuat'; return; }
                aktif.punyaStatus = !!r.punya_status;
                aktif.status = r.status || '';
                aktif.berkas = r.berkas || [];

                elJudul.textContent = (r.kode ? r.kode + '. ' : '') + r.judul;
                elKonteks.textContent = [r.bagian, r.induk].filter(Boolean).join(' › ');
                elBlok.style.display = aktif.punyaStatus ? '' : 'none';
                elKet.value = r.keterangan || '';
                pilihStatus(aktif.status);
                gambarFolder(r.folder);
                gambarBerkas();

                var bolehEdit = r.boleh_edit;
                elZona.style.display = bolehEdit ? '' : 'none';
                elKet.readOnly = !bolehEdit;
                document.getElementById('modal-simpan').style.display = bolehEdit ? '' : 'none';
            })
            .catch(function () { elJudul.textContent = 'Gagal menghubungi server'; });
    }

    function tutup() {
        if (sedangUnggah) { return; }   // dikunci selama unggahan berjalan
        if (berubah) { simpanJawaban(); }
        latar.classList.remove('tampil');
        modal.classList.remove('tampil');
        document.body.style.overflow = '';
        aktif = null;
    }

    latar.addEventListener('click', tutup);
    document.getElementById('modal-x').addEventListener('click', tutup);
    document.getElementById('modal-batal').addEventListener('click', tutup);
    document.getElementById('modal-simpan').addEventListener('click', tutup);
    document.addEventListener('keydown', function (ev) {
        if (ev.key !== 'Escape') { return; }
        if (sedangUnggah) { ev.preventDefault(); return; }
        if (modal.classList.contains('tampil')) { tutup(); }
    });

    // --------------------------------------------------------- status
    function pilihStatus(nilai) {
        Array.prototype.forEach.call(elStatus.children, function (b) {
            b.classList.toggle('aktif', b.getAttribute('data-nilai') === nilai && nilai !== '');
        });
    }

    elStatus.addEventListener('click', function (ev) {
        var b = ev.target.closest('button');
        if (!b || !aktif) { return; }
        aktif.status = b.getAttribute('data-nilai');
        pilihStatus(aktif.status);
        berubah = true;
        simpanJawaban();
    });

    elKet.addEventListener('input', function () {
        berubah = true;
        simpanKetTertunda();
    });

    var simpanKetTertunda = debounce(function () { simpanJawaban(); }, 700);

    function simpanJawaban() {
        if (!aktif || !aktif.itemId || !aktif.punyaStatus) { perbaruiSel(); return; }
        var sel = aktif.sel, status = aktif.status, ket = elKet.value;
        berubah = false;
        post('api_jawaban', { item_id: aktif.itemId, status: status, keterangan: ket })
            .then(function (r) {
                if (r.ok) {
                    elInfo.textContent = 'Tersimpan ' + (r.waktu || '');
                    perbaruiSel(sel, status, ket);
                } else {
                    toast(r.pesan || 'Gagal menyimpan', true);
                }
            })
            .catch(function () { toast('Gagal menghubungi server', true); });
    }

    /** Perbarui penanda ringkas pada baris tabel. */
    function perbaruiSel(sel, status, ket) {
        sel = sel || (aktif && aktif.sel);
        if (!sel) { return; }
        if (status === undefined) { status = sel.getAttribute('data-status') || ''; }
        if (ket === undefined) {
            var lama = sel.querySelector('.ket-ringkas');
            ket = lama ? lama.innerText : '';
        }
        var jml = (aktif && aktif.berkas) ? aktif.berkas.length : parseInt(sel.getAttribute('data-berkas') || '0', 10);
        ket = (ket || '').trim();

        sel.setAttribute('data-status', status);
        sel.setAttribute('data-berkas', jml);
        var tr = sel.closest('tr');
        if (tr) {
            tr.setAttribute('data-status', status);
            tr.setAttribute('data-berkas', jml);
        }

        var kosong = status === '' && ket === '' && jml === 0;
        sel.classList.toggle('kosong', kosong);

        var html = '<div class="ringkas-baris">';
        if (status) {
            html += '<span class="badge b-' + status + '">' + labelStatus(status) + '</span>';
        }
        if (jml > 0) {
            html += '<span class="lampiran">📎 ' + jml + ' berkas</span>';
        }
        if (kosong) {
            html += '<span class="isi-hint">＋ klik untuk mengisi</span>';
        }
        html += '</div>';
        sel.innerHTML = html;

        if (ket !== '') {
            var d = document.createElement('div');
            d.className = 'ket-ringkas';
            d.textContent = ket;
            sel.appendChild(d);
        }
    }

    // --------------------------------------------------------- folder
    function gambarFolder(folder) {
        elFolder.innerHTML = '';
        if (!folder) { return; }
        if (folder.lokal || !folder.link) {
            var s = document.createElement('span');
            s.className = 'chip-folder lokal';
            s.textContent = '📁 Tersimpan di server — Google Drive belum aktif';
            elFolder.appendChild(s);
        } else {
            var a = document.createElement('a');
            a.className = 'chip-folder';
            a.target = '_blank';
            a.rel = 'noopener';
            a.href = folder.link;
            a.textContent = '📂 Buka folder Google Drive poin ini';
            elFolder.appendChild(a);
        }
    }

    // --------------------------------------------------------- berkas
    function gambarBerkas() {
        elBerkas.innerHTML = '';
        var daftar = (aktif && aktif.berkas) || [];
        elKosong.style.display = daftar.length ? 'none' : '';
        daftar.forEach(function (b) {
            var li = document.createElement('li');
            li.setAttribute('data-doc', b.id);
            li.innerHTML = '<span>' + b.ikon + '</span>'
                + '<span class="nama"><a target="_blank" rel="noopener"></a></span>'
                + '<span class="ukuran"></span>'
                + '<button type="button" class="hapus" title="Hapus berkas">✕</button>';
            var a = li.querySelector('a');
            a.href = b.link;
            a.textContent = b.nama;
            li.querySelector('.ukuran').textContent = b.ukuran;
            elBerkas.appendChild(li);
        });
    }

    elBerkas.addEventListener('click', function (ev) {
        var h = ev.target.closest('.hapus');
        if (!h || !aktif) { return; }
        var li = h.closest('li[data-doc]');
        var id = parseInt(li.getAttribute('data-doc'), 10);
        if (!confirm('Hapus berkas ini? Berkas juga dihapus dari Google Drive.')) { return; }
        post('api_hapus_berkas', { id: id }).then(function (r) {
            if (!r.ok) { toast(r.pesan, true); return; }
            aktif.berkas = aktif.berkas.filter(function (b) { return b.id !== id; });
            gambarBerkas();
            perbaruiSel(aktif.sel, aktif.status, elKet.value);
            toast('Berkas dihapus');
        });
    });

    // --------------------------------------------------------- unggah
    //
    // Berkas dikirim satu per satu memakai XMLHttpRequest supaya kemajuan
    // unggahan bisa ditampilkan dalam persen. Selama proses berjalan, layar
    // dikunci oleh jendela progres — hanya tombol Batalkan yang aktif.

    var uLatar   = document.getElementById('unggah-latar');
    var uModal   = document.getElementById('modal-unggah');
    var uJudul   = document.getElementById('unggah-judul');
    var uHitung  = document.getElementById('unggah-hitung');
    var uNama    = document.getElementById('unggah-nama');
    var uBar     = uModal ? uModal.querySelector('.unggah-bar') : null;
    var uIsi     = document.getElementById('unggah-isi');
    var uPersen  = document.getElementById('unggah-persen');
    var uUkuran  = document.getElementById('unggah-ukuran');
    var uDaftar  = document.getElementById('unggah-daftar');
    var uInfo    = document.getElementById('unggah-info');
    var uBatal   = document.getElementById('unggah-batal');
    var uTutup   = document.getElementById('unggah-tutup');

    var dibatalkan = false;
    var xhrAktif   = null;

    function ukuranTeks(b) {
        var satuan = ['B', 'KB', 'MB', 'GB'], i = 0, n = b;
        while (n >= 1024 && i < satuan.length - 1) { n /= 1024; i++; }
        return (i === 0 ? n : n.toFixed(1).replace('.', ',')) + ' ' + satuan[i];
    }

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
        uJudul.textContent = 'Mengunggah dokumen…';
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
        if (catatan !== undefined) {
            li.querySelector('.catatan').textContent = catatan;
        }
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
            setTimeout(function () {
                if (!sedangUnggah) { tutupProgres(); }
            }, 900);
        }
    }

    /** Mengirim satu berkas; memanggil onKemajuan(bytesTerkirim). */
    function kirimBerkas(file, onKemajuan) {
        return new Promise(function (resolve) {
            var fd = new FormData();
            fd.append('csrf', CSRF);
            fd.append('owner_type', aktif.ownerType);
            fd.append('owner_key', aktif.ownerKey);
            fd.append('berkas[]', file);

            var xhr = new XMLHttpRequest();
            xhrAktif = xhr;
            xhr.open('POST', ENDPOINT + '?p=api_unggah', true);
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
            xhr.onabort  = function () { resolve({ ok: false, dibatalkan: true, pesan: 'Dibatalkan.' }); };
            xhr.send(fd);
        });
    }

    /** Alasan berkas ditolak sebelum dikirim, atau null bila lolos. */
    function periksaBerkas(f) {
        var ext = (f.name.indexOf('.') >= 0 ? f.name.split('.').pop() : '').toLowerCase();
        if (!ext) {
            return 'berkas tanpa ekstensi tidak dapat diunggah';
        }
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

    function unggah(files) {
        if (!files || !files.length || !aktif || sedangUnggah) { return; }

        var semua = Array.prototype.slice.call(files);
        var daftar = [];
        var ditolak = [];
        semua.forEach(function (f) {
            var alasan = periksaBerkas(f);
            if (alasan) { ditolak.push({ file: f, alasan: alasan }); }
            else { daftar.push(f); }
        });

        var total = daftar.reduce(function (a, f) { return a + f.size; }, 0) || 1;
        var sel = aktif.sel;
        var sudah = 0, berhasil = 0, gagal = ditolak.length;

        // berkas yang ditolak tetap ditampilkan agar sebabnya terlihat
        bukaProgres(daftar.concat(ditolak.map(function (d) { return d.file; })));
        ditolak.forEach(function (d, k) {
            tandaiBerkas(daftar.length + k, 'gagal', d.alasan);
        });

        if (!daftar.length) {
            elZona.classList.remove('sibuk');
            selesaiProgres(0, gagal);
            return;
        }

        perbaruiProgres(0, 0, total, daftar[0].name, 1, daftar.length);
        elZona.classList.add('sibuk');

        (function berikutnya(i) {
            if (dibatalkan || i >= daftar.length) {
                elZona.classList.remove('sibuk');
                gambarBerkas();
                perbaruiSel(sel, aktif ? aktif.status : undefined, elKet.value);
                selesaiProgres(berhasil, gagal);
                return;
            }

            var file = daftar[i];
            tandaiBerkas(i, 'jalan', 'mengunggah…');
            uInfo.textContent = 'Mohon tunggu — jangan menutup halaman.';

            kirimBerkas(file, function (terkirim) {
                perbaruiProgres((sudah + terkirim) / total * 100, sudah + terkirim, total,
                                file.name, i + 1, daftar.length);
            }).then(function (r) {
                sudah += file.size;
                perbaruiProgres(sudah / total * 100, sudah, total, file.name, i + 1, daftar.length);

                if (r.dibatalkan) {
                    tandaiBerkas(i, 'gagal', 'dibatalkan');
                } else if (r.ok && r.berkas && r.berkas.length) {
                    berhasil++;
                    tandaiBerkas(i, 'selesai', ukuranTeks(file.size));
                    if (aktif) { aktif.berkas.push(r.berkas[0]); }
                    if (r.folder) { gambarFolder(r.folder); }
                } else {
                    gagal++;
                    tandaiBerkas(i, 'gagal', (r.gagal && r.gagal.length ? r.gagal[0] : (r.pesan || 'gagal')));
                }
                berikutnya(i + 1);
            });
        })(0);
    }

    if (uBatal) {
        uBatal.addEventListener('click', function () {
            if (!sedangUnggah) { return; }
            if (!confirm('Batalkan unggahan? Berkas yang sudah terkirim tetap tersimpan.')) { return; }
            dibatalkan = true;
            uBatal.disabled = true;
            uJudul.textContent = 'Membatalkan…';
            if (xhrAktif) { xhrAktif.abort(); }
        });
    }
    if (uTutup) {
        uTutup.addEventListener('click', tutupProgres);
    }

    elZona.addEventListener('click', function () {
        if (sedangUnggah) { return; }
        input.value = '';
        input.click();
    });
    input.addEventListener('change', function () { unggah(input.files); });

    ['dragenter', 'dragover'].forEach(function (t) {
        elZona.addEventListener(t, function (ev) { ev.preventDefault(); elZona.classList.add('aktif'); });
    });
    elZona.addEventListener('dragleave', function () { elZona.classList.remove('aktif'); });
    elZona.addEventListener('drop', function (ev) {
        ev.preventDefault();
        elZona.classList.remove('aktif');
        unggah(ev.dataTransfer.files);
    });
    // cegah peramban membuka berkas bila dilepas di luar zona
    ['dragover', 'drop'].forEach(function (t) {
        document.addEventListener(t, function (ev) {
            if (!ev.target.closest || !ev.target.closest('#zona-unggah')) { ev.preventDefault(); }
        });
    });

    // keterangan batas ukuran pada area unggah
    (function () {
        var kecil = elZona ? elZona.querySelector('.zona-kecil') : null;
        if (kecil) {
            var teks = SEMUA_JENIS
                ? 'Dokumen, foto, video, audio — semua jenis berkas diterima'
                : 'Jenis yang diterima: ' + EKSTENSI_OK.join(', ');
            if (BATAS_UNGGAH) { teks += ' · maksimal ' + ukuranTeks(BATAS_UNGGAH) + ' per berkas'; }
            kecil.textContent = teks;
        }
    })();

    // ------------------------------------------- membuka popup dari sel
    document.addEventListener('click', function (ev) {
        if (sedangUnggah) { return; }
        var sel = ev.target.closest('.poin-sel');
        if (sel) { buka(sel); }
    });
    document.addEventListener('keydown', function (ev) {
        if ((ev.key === 'Enter' || ev.key === ' ') && ev.target.classList
            && ev.target.classList.contains('poin-sel')) {
            ev.preventDefault();
            buka(ev.target);
        }
    });

    // ------------------------------------------------- cari & saring
    var cari = document.getElementById('cari-poin');
    var saring = document.getElementById('saring-status');

    function terapkanSaringan() {
        var q = cari ? cari.value.toLowerCase().trim() : '';
        var v = saring ? saring.value : '';
        document.querySelectorAll('tr.poin').forEach(function (tr) {
            var tampil = true;
            if (q) { tampil = tr.innerText.toLowerCase().indexOf(q) >= 0; }
            if (tampil && v) {
                var st = tr.getAttribute('data-status') || '';
                var bk = parseInt(tr.getAttribute('data-berkas') || '0', 10);
                if (v === 'belum') { tampil = st === '' && bk === 0; }
                else if (v === 'ada_berkas') { tampil = bk > 0; }
                else { tampil = st === v; }
            }
            tr.style.display = tampil ? '' : 'none';
        });
    }

    if (cari) { cari.addEventListener('input', debounce(terapkanSaringan, 200)); }
    if (saring) { saring.addEventListener('change', terapkanSaringan); }
})();
