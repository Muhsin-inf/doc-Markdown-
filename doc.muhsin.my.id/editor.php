<?php
require 'config/database.php';
require 'config/auth.php';
requireLogin($pdo);

$userId = (int) $_SESSION['user_id'];
$docId = $_GET['id'] ?? null;
$token = $_GET['token'] ?? '';
$doc = ['title' => '', 'content' => "# Judul Dokumentasi\n\nKetik isi dokumentasi Anda di sini menggunakan format Markdown...\n\n- Fitur 1\n- Fitur 2\n\n```python\nprint('Hello World')\n```"];

// Jika mode Edit, periksa izin
if ($docId) {
    $stmt = $pdo->prepare("
        SELECT d.*, 
            CASE WHEN d.owner_id = :uid1 THEN 'owner' ELSE ds.permission END AS access
        FROM documents d
        LEFT JOIN document_shares ds ON d.id = ds.document_id AND ds.user_id = :uid2
        WHERE d.id = :id
    ");
    $stmt->execute(['uid1' => $userId, 'uid2' => $userId, 'id' => $docId]);
    $existing = $stmt->fetch();

    if (!$existing || !in_array($existing['access'], ['owner', 'editor'], true)) {
        die("Akses ditolak! Anda tidak memiliki izin untuk mengedit dokumen ini.");
    }
    $doc = $existing;
}

// Proses Simpan Dokumen
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = trim($_POST['title'] ?? '');
    $content = $_POST['content'] ?? '';

    if ($title === '') {
        $title = 'Dokumentasi Tanpa Judul';
    }

    if ($docId) {
        $stmt = $pdo->prepare("UPDATE documents SET title = ?, content = ? WHERE id = ?");
        $stmt->execute([$title, $content, $docId]);
        $slug = $doc['slug'];
    } else {
        $slug = createSlug($title);
        $stmt = $pdo->prepare("INSERT INTO documents (owner_id, title, slug, content) VALUES (?, ?, ?, ?)");
        $stmt->execute([$userId, $title, $slug, $content]);
    }
    header("Location: view.php?slug=" . urlencode($slug));
    exit;
}

$pageTitle = ($docId ? 'Edit' : 'Buat') . ' Dokumentasi';
$activeNav = 'editor';
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <?php require __DIR__ . '/components/header_meta.php'; ?>
    <script src="https://cdn.jsdelivr.net/npm/marked/marked.min.js"></script>
    <style>
        /* Minimalist styling for editor preview */
        .preview-pane table { border-collapse: collapse; width: 100%; }
        .preview-pane th, .preview-pane td { border: 1px solid #cbd5e1; padding: 6px 10px; }
        .dark .preview-pane th, .dark .preview-pane td { border-color: #27272a; }
        .preview-pane pre { border-radius: 0px !important; }
        .preview-pane code { border-radius: 2px !important; }
    </style>
</head>
<body class="min-h-screen bg-slate-50 text-slate-800 dark:bg-zinc-950 dark:text-zinc-100 flex flex-col h-screen overflow-hidden">

    <?php require __DIR__ . '/components/navbar.php'; ?>

    <form method="POST" class="flex flex-col flex-1 overflow-hidden">
        <!-- Top Toolbar Editor: Input Judul, Upload .md, dan Simpan -->
        <div class="border-b border-slate-200 bg-white px-4 py-2.5 dark:border-zinc-800 dark:bg-zinc-900 flex flex-wrap items-center justify-between gap-3 shrink-0">
            <div class="flex items-center gap-2 flex-1 min-w-[240px]">
                <a href="index.php" title="Batal / Kembali" class="border border-slate-200 bg-slate-50 px-2.5 py-1.5 text-xs font-semibold text-slate-700 hover:bg-slate-100 dark:border-zinc-800 dark:bg-zinc-800 dark:text-zinc-300 dark:hover:bg-zinc-700">
                    ←
                </a>
                <input type="text" name="title" id="docTitle" required placeholder="Ketik judul dokumen di sini..." value="<?= htmlspecialchars($doc['title']) ?>" class="w-full border border-slate-300 bg-white px-3 py-1.5 text-sm font-semibold text-slate-900 focus:border-blue-600 focus:outline-none dark:border-zinc-700 dark:bg-zinc-950 dark:text-zinc-100">
            </div>

            <div class="flex items-center gap-2">
                <!-- Upload file Markdown lokal -->
                <label class="cursor-pointer inline-flex items-center gap-1.5 border border-slate-300 bg-white px-3 py-1.5 text-xs font-semibold text-slate-700 hover:bg-slate-50 dark:border-zinc-700 dark:bg-zinc-800 dark:text-zinc-200 dark:hover:bg-zinc-700">
                    <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75V16.5m-13.5-9L12 3m0 0 4.5 4.5M12 3v13.5" />
                    </svg>
                    <span>Upload .md</span>
                    <input type="file" id="mdFileInput" accept=".md,.markdown,.txt" class="hidden">
                </label>

                <!-- Tombol Simpan -->
                <button type="submit" class="inline-flex items-center gap-1.5 border border-blue-600 bg-blue-600 px-4 py-1.5 text-xs font-semibold text-white hover:bg-blue-700 dark:border-blue-500 dark:bg-blue-600 dark:hover:bg-blue-500">
                    <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M17.593 3.322c1.1.128 1.907 1.077 1.907 2.185V21L12 17.25 4.5 21V5.507c0-1.108.806-2.057 1.907-2.185a48.507 48.507 0 0 1 11.186 0Z" />
                    </svg>
                    <span>Simpan</span>
                </button>
            </div>
        </div>

        <!-- Format Helper Toolbar (Word style) -->
        <div class="border-b border-slate-200 bg-slate-50/90 px-4 py-1.5 dark:border-zinc-800 dark:bg-zinc-900/90 flex flex-wrap items-center gap-1 text-xs shrink-0 overflow-x-auto">
            <select id="headingSelect" onchange="applyHeading(this.value); this.value='';" class="border border-slate-300 bg-white px-2 py-1 text-xs text-slate-700 dark:border-zinc-700 dark:bg-zinc-800 dark:text-zinc-200">
                <option value="">Header / Paragraf...</option>
                <option value="p">Normal (Paragraf)</option>
                <option value="h1">Header 1 (#)</option>
                <option value="h2">Header 2 (##)</option>
                <option value="h3">Header 3 (###)</option>
            </select>

            <span class="mx-1 h-4 w-px bg-slate-200 dark:bg-zinc-700"></span>

            <button type="button" onclick="wrapText('**', '**', 'teks tebal')" title="Bold" class="border border-slate-300 bg-white px-2 py-1 font-bold text-slate-700 hover:bg-slate-100 dark:border-zinc-700 dark:bg-zinc-800 dark:text-zinc-200">B</button>
            <button type="button" onclick="wrapText('*', '*', 'teks miring')" title="Italic" class="border border-slate-300 bg-white px-2 py-1 italic text-slate-700 hover:bg-slate-100 dark:border-zinc-700 dark:bg-zinc-800 dark:text-zinc-200">I</button>
            <button type="button" onclick="wrapText('~~', '~~', 'teks coret')" title="Strikethrough" class="border border-slate-300 bg-white px-2 py-1 line-through text-slate-700 hover:bg-slate-100 dark:border-zinc-700 dark:bg-zinc-800 dark:text-zinc-200">S</button>
            <button type="button" onclick="wrapText('`', '`', 'kode')" title="Inline Code" class="border border-slate-300 bg-white px-2 py-1 font-mono text-slate-700 hover:bg-slate-100 dark:border-zinc-700 dark:bg-zinc-800 dark:text-zinc-200">&lt;/&gt;</button>

            <span class="mx-1 h-4 w-px bg-slate-200 dark:bg-zinc-700"></span>

            <button type="button" onclick="prefixLines('- ', 'item daftar')" title="Bullet List" class="border border-slate-300 bg-white px-2 py-1 text-slate-700 hover:bg-slate-100 dark:border-zinc-700 dark:bg-zinc-800 dark:text-zinc-200">• List</button>
            <button type="button" onclick="prefixLines('1. ', 'langkah urut')" title="Numbered List" class="border border-slate-300 bg-white px-2 py-1 text-slate-700 hover:bg-slate-100 dark:border-zinc-700 dark:bg-zinc-800 dark:text-zinc-200">1. List</button>
            <button type="button" onclick="prefixLines('> ', 'kutipan penting')" title="Quote" class="border border-slate-300 bg-white px-2 py-1 text-slate-700 hover:bg-slate-100 dark:border-zinc-700 dark:bg-zinc-800 dark:text-zinc-200">❝ Quote</button>

            <span class="mx-1 h-4 w-px bg-slate-200 dark:bg-zinc-700"></span>

            <button type="button" onclick="insertLink()" title="Tambah Link" class="border border-slate-300 bg-white px-2 py-1 text-slate-700 hover:bg-slate-100 dark:border-zinc-700 dark:bg-zinc-800 dark:text-zinc-200">🔗 Link</button>
            <button type="button" onclick="insertCodeBlock()" title="Blok Kode" class="border border-slate-300 bg-white px-2 py-1 text-slate-700 hover:bg-slate-100 dark:border-zinc-700 dark:bg-zinc-800 dark:text-zinc-200">💻 Blok Kode</button>
            <button type="button" onclick="insertTable()" title="Tabel" class="border border-slate-300 bg-white px-2 py-1 text-slate-700 hover:bg-slate-100 dark:border-zinc-700 dark:bg-zinc-800 dark:text-zinc-200">📊 Tabel</button>

            <span class="mx-1 h-4 w-px bg-slate-200 dark:bg-zinc-700"></span>

            <!-- Tombol Upload Gambar (upload ke server, sisipkan URL di Markdown) -->
            <label id="imgUploadLabel" title="Upload Gambar ke Server (maks 5MB: JPG/PNG/GIF/WebP)" class="cursor-pointer inline-flex items-center gap-1 border border-slate-300 bg-white px-2 py-1 text-slate-700 hover:bg-slate-100 dark:border-zinc-700 dark:bg-zinc-800 dark:text-zinc-200">
                <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="m2.25 15.75 5.159-5.159a2.25 2.25 0 0 1 3.182 0l5.159 5.159m-1.5-1.5 1.409-1.409a2.25 2.25 0 0 1 3.182 0l2.909 2.909m-18 3.75h16.5a1.5 1.5 0 0 0 1.5-1.5V6a1.5 1.5 0 0 0-1.5-1.5H3.75A1.5 1.5 0 0 0 2.25 6v12a1.5 1.5 0 0 0 1.5 1.5Zm10.5-11.25h.008v.008h-.008V8.25Zm.375 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Z" />
                </svg>
                <span>Gambar</span>
                <input type="file" id="imgFileInput" accept="image/jpeg,image/png,image/gif,image/webp,image/svg+xml" class="hidden">
            </label>
            <span id="imgUploadStatus" class="hidden text-[11px] font-medium text-blue-600 dark:text-blue-400">⏳ Mengupload...</span>

            <!-- Mobile View Switcher (Editor / Preview) -->
            <div class="ml-auto flex sm:hidden border border-slate-300 dark:border-zinc-700">
                <button type="button" id="tabEditorBtn" onclick="switchMobileTab('editor')" class="bg-blue-600 text-white px-2 py-0.5 text-[11px] font-semibold">Tulis</button>
                <button type="button" id="tabPreviewBtn" onclick="switchMobileTab('preview')" class="bg-white text-slate-700 dark:bg-zinc-800 dark:text-zinc-300 px-2 py-0.5 text-[11px]">Preview</button>
            </div>
        </div>

        <!-- Split Workspace (Editor Markdown & Live Preview) -->
        <div class="flex flex-1 overflow-hidden">
            <!-- Sisi Kiri: Textarea Editor -->
            <div id="editorContainer" class="flex-1 flex flex-col h-full border-r border-slate-200 dark:border-zinc-800">
                <textarea name="content" id="markdownInput" spellcheck="false" placeholder="Tulis markdown di sini..." class="w-full h-full p-4 font-mono text-xs sm:text-sm leading-relaxed bg-white text-slate-900 border-none resize-none focus:outline-none dark:bg-zinc-950 dark:text-zinc-100"><?= htmlspecialchars($doc['content']) ?></textarea>
            </div>

            <!-- Sisi Kanan: Live Markdown Preview -->
            <div id="previewContainer" class="hidden sm:block flex-1 h-full overflow-y-auto p-4 sm:p-6 bg-white dark:bg-zinc-900">
                <div class="text-[11px] font-bold uppercase tracking-wider text-slate-400 dark:text-zinc-500 mb-3 border-b border-slate-100 dark:border-zinc-800 pb-1">
                    Live Preview
                </div>
                <div id="previewPane" class="preview-pane prose prose-slate dark:prose-invert max-w-none text-xs sm:text-sm"></div>
            </div>
        </div>
    </form>

    <script>
        const markdownInput = document.getElementById('markdownInput');
        const previewPane = document.getElementById('previewPane');

        function updatePreview() {
            if (window.marked && previewPane) {
                previewPane.innerHTML = marked.parse(markdownInput.value || '*Pratinjau kosong*');
            }
        }

        markdownInput.addEventListener('input', updatePreview);
        // Initial render
        updatePreview();

        // Helper Toolbar formatting
        function wrapText(before, after, defaultText) {
            const start = markdownInput.selectionStart;
            const end = markdownInput.selectionEnd;
            const text = markdownInput.value;
            const selected = text.substring(start, end) || defaultText;
            markdownInput.value = text.substring(0, start) + before + selected + after + text.substring(end);
            markdownInput.focus();
            markdownInput.setSelectionRange(start + before.length, start + before.length + selected.length);
            updatePreview();
        }

        function prefixLines(prefix, defaultText) {
            const start = markdownInput.selectionStart;
            const end = markdownInput.selectionEnd;
            const text = markdownInput.value;
            const selected = text.substring(start, end) || defaultText;
            const lines = selected.split('\n').map(l => prefix + l).join('\n');
            markdownInput.value = text.substring(0, start) + lines + text.substring(end);
            markdownInput.focus();
            updatePreview();
        }

        function applyHeading(type) {
            if (!type) return;
            if (type === 'h1') wrapText('# ', '\n', 'Judul Utama');
            if (type === 'h2') wrapText('## ', '\n', 'Subjudul');
            if (type === 'h3') wrapText('### ', '\n', 'Bagian Kecil');
            if (type === 'p') wrapText('', '\n', 'Teks paragraf biasa');
        }

        function insertLink() {
            const url = prompt('Masukkan URL:', 'https://');
            if (url) wrapText('[', `](${url})`, 'Teks Tautan');
        }

        function insertCodeBlock() {
            wrapText('```bash\n', '\n```', 'echo "Hello"');
        }

        function insertTable() {
            const tableTpl = "\n| Kolom 1 | Kolom 2 | Kolom 3 |\n| :--- | :--- | :--- |\n| Data A | Data B | Data C |\n| Data D | Data E | Data F |\n\n";
            wrapText(tableTpl, '', '');
        }

        // Upload .md file handling
        const fileInput = document.getElementById('mdFileInput');
        if (fileInput) {
            fileInput.addEventListener('change', function(e) {
                const file = e.target.files[0];
                if (!file) return;
                const reader = new FileReader();
                reader.onload = function(evt) {
                    markdownInput.value = evt.target.result;
                    // Auto set doc title if empty
                    const titleInput = document.getElementById('docTitle');
                    if (titleInput && !titleInput.value) {
                        titleInput.value = file.name.replace(/\.[^/.]+$/, "");
                    }
                    updatePreview();
                };
                reader.readAsText(file);
            });
        }

        // ─── Upload Gambar ke Server ──────────────────────────────────────────────
        // Sisipkan markdown ![alt](url) ke posisi kursor setelah upload selesai

        const imgFileInput   = document.getElementById('imgFileInput');
        const imgUploadStatus = document.getElementById('imgUploadStatus');

        /**
         * Upload file gambar ke upload_image.php, lalu sisipkan ![alt](url) ke editor.
         */
        async function uploadImageFile(file) {
            if (!file || !file.type.startsWith('image/')) {
                alert('File harus berupa gambar (JPG, PNG, GIF, WebP, SVG).');
                return;
            }
            if (file.size > 5 * 1024 * 1024) {
                alert('Ukuran gambar maksimal 5 MB.');
                return;
            }

            imgUploadStatus.classList.remove('hidden');

            const formData = new FormData();
            formData.append('image', file);

            try {
                const resp = await fetch('upload_image.php', { method: 'POST', body: formData });
                const data = await resp.json();

                if (data.success) {
                    // Sisipkan syntax Markdown gambar di posisi kursor
                    const altText = file.name.replace(/\.[^/.]+$/, '').replace(/[_-]/g, ' ');
                    const mdImg   = `![${altText}](${data.url})`;
                    insertAtCursor(mdImg);
                    updatePreview();
                } else {
                    alert('Upload gagal: ' + (data.error || 'Error tidak diketahui.'));
                }
            } catch (err) {
                alert('Gagal terhubung ke server saat upload gambar.');
            } finally {
                imgUploadStatus.classList.add('hidden');
                if (imgFileInput) imgFileInput.value = '';
            }
        }

        /**
         * Sisipkan teks di posisi kursor textarea.
         */
        function insertAtCursor(text) {
            const start = markdownInput.selectionStart;
            const end   = markdownInput.selectionEnd;
            const val   = markdownInput.value;
            // Pastikan ada baris baru di sekitar sintaks gambar jika bukan di awal baris
            const before = val.substring(0, start);
            const after  = val.substring(end);
            const prefix = (before.length > 0 && !before.endsWith('\n')) ? '\n' : '';
            const suffix = (after.length > 0 && !after.startsWith('\n')) ? '\n' : '';
            const insert = prefix + text + suffix;
            markdownInput.value = before + insert + after;
            const newPos = start + insert.length;
            markdownInput.setSelectionRange(newPos, newPos);
            markdownInput.focus();
        }

        // Handler tombol upload gambar
        if (imgFileInput) {
            imgFileInput.addEventListener('change', function(e) {
                const file = e.target.files[0];
                if (file) uploadImageFile(file);
            });
        }

        // ─── Paste gambar dari clipboard (Ctrl+V) ────────────────────────────────
        markdownInput.addEventListener('paste', function(e) {
            const items = (e.clipboardData || window.clipboardData)?.items;
            if (!items) return;
            for (const item of items) {
                if (item.type.startsWith('image/')) {
                    e.preventDefault();
                    uploadImageFile(item.getAsFile());
                    return;
                }
            }
        });

        // ─── Drag & Drop gambar ke editor ────────────────────────────────────────
        const editorContainer = document.getElementById('editorContainer');
        if (editorContainer) {
            editorContainer.addEventListener('dragover', function(e) {
                e.preventDefault();
                editorContainer.classList.add('ring-2', 'ring-blue-500');
            });
            editorContainer.addEventListener('dragleave', function() {
                editorContainer.classList.remove('ring-2', 'ring-blue-500');
            });
            editorContainer.addEventListener('drop', function(e) {
                e.preventDefault();
                editorContainer.classList.remove('ring-2', 'ring-blue-500');
                const files = e.dataTransfer?.files;
                if (files && files.length > 0) {
                    for (const f of files) {
                        if (f.type.startsWith('image/')) {
                            uploadImageFile(f);
                        }
                    }
                }
            });
        }

        // Switch Mobile View
        function switchMobileTab(tab) {
            const editor = document.getElementById('editorContainer');
            const preview = document.getElementById('previewContainer');
            const btnEditor = document.getElementById('tabEditorBtn');
            const btnPreview = document.getElementById('tabPreviewBtn');

            if (tab === 'editor') {
                editor.classList.remove('hidden');
                preview.classList.add('hidden');
                btnEditor.className = 'bg-blue-600 text-white px-2 py-0.5 text-[11px] font-semibold';
                btnPreview.className = 'bg-white text-slate-700 dark:bg-zinc-800 dark:text-zinc-300 px-2 py-0.5 text-[11px]';
            } else {
                editor.classList.add('hidden');
                preview.classList.remove('hidden');
                btnPreview.className = 'bg-blue-600 text-white px-2 py-0.5 text-[11px] font-semibold';
                btnEditor.className = 'bg-white text-slate-700 dark:bg-zinc-800 dark:text-zinc-300 px-2 py-0.5 text-[11px]';
                updatePreview();
            }
        }
    </script>
</body>
</html>
