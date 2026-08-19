</div><!-- /konten -->

<input type="file" id="dok-pemilih" multiple style="display:none" class="no-print">

<!-- ============ Jendela progres unggah (mengunci layar) ============ -->
<div class="modal-latar kunci no-print" id="unggah-latar"></div>
<div class="modal modal-unggah no-print" id="modal-unggah" role="dialog" aria-modal="true"
     aria-labelledby="unggah-judul">
    <header>
        <h3 id="unggah-judul">Mengunggah berkas…</h3>
        <div class="modal-konteks" id="unggah-hitung"></div>
    </header>

    <div class="badan">
        <div class="unggah-nama" id="unggah-nama">—</div>
        <div class="unggah-bar"><i id="unggah-isi" style="width:0%"></i></div>
        <div class="unggah-angka">
            <b id="unggah-persen">0%</b>
            <span id="unggah-ukuran"></span>
        </div>
        <ul class="unggah-daftar" id="unggah-daftar"></ul>
    </div>

    <footer>
        <span class="modal-info" id="unggah-info">Mohon tunggu — jangan menutup halaman.</span>
        <button type="button" class="btn bahaya" id="unggah-batal">✕ Batalkan</button>
        <button type="button" class="btn utama" id="unggah-tutup" style="display:none">Selesai</button>
    </footer>
</div>

<script src="assets/js/app.js?v=<?= asetVersi('assets/js/app.js') ?>"></script>
<script src="assets/js/dokumen.js?v=<?= asetVersi('assets/js/dokumen.js') ?>"></script>
</body>
</html>
