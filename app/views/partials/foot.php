</div><!-- /konten -->

<input type="file" id="pemilih-berkas" multiple style="display:none" class="no-print">

<!-- ================= Jendela popup pengisian poin ================= -->
<div class="modal-latar no-print" id="modal-latar"></div>
<div class="modal no-print" id="modal-poin" role="dialog" aria-modal="true" aria-labelledby="modal-judul">
    <header>
        <div class="modal-konteks" id="modal-konteks"></div>
        <h3 id="modal-judul">—</h3>
        <button type="button" class="modal-tutup" id="modal-x" title="Tutup (Esc)">✕</button>
    </header>

    <div class="badan" id="modal-badan">
        <div id="blok-status">
            <label class="f">Hasil self assessment</label>
            <div class="pilih-status" id="pilih-status">
                <button type="button" data-nilai="ada">Ada / Sesuai</button>
                <button type="button" data-nilai="sebagian">Sebagian</button>
                <button type="button" data-nilai="tidak_ada">Belum Ada</button>
                <button type="button" data-nilai="na">Tidak Berlaku</button>
                <button type="button" data-nilai="" class="netral">Kosongkan</button>
            </div>

            <label class="f">Keterangan</label>
            <textarea class="f" id="modal-ket" rows="4"
                      placeholder="Tuliskan keterangan, nomor dokumen, capaian indikator, dsb."></textarea>
        </div>

        <label class="f">Dokumen pendukung</label>
        <div class="zona-unggah" id="zona-unggah">
            <div class="zona-ikon">⬆️</div>
            <div><b>Klik untuk memilih berkas</b> — bisa beberapa sekaligus</div>
            <div class="zona-kecil">atau seret dan lepas berkas ke area ini</div>
        </div>

        <div id="modal-folder" class="modal-folder"></div>
        <ul class="berkas" id="modal-berkas"></ul>
        <div id="modal-kosong" class="modal-kosong">Belum ada dokumen yang diunggah untuk poin ini.</div>
    </div>

    <footer>
        <span class="modal-info" id="modal-info"></span>
        <button type="button" class="btn" id="modal-batal">Tutup</button>
        <button type="button" class="btn utama" id="modal-simpan">💾 Simpan &amp; Tutup</button>
    </footer>
</div>

<script src="assets/js/app.js?v=9"></script>
</body>
</html>
