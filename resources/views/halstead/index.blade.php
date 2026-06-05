<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Software Metrics Analyzer - Halstead Metrics</title>
    <link rel="stylesheet" href="{{ asset('css/style.css') }}">
</head>
<body>

<div class="container">

    <div class="title">
        <h1>Halstead Metrics Analyzer</h1>
        <p>
            Tempel kode program atau unggah file untuk menganalisis kompleksitas software menggunakan metodologi Halstead Metrics secara instan.
        </p>
    </div>

    <!-- Alert Bahaya -->
    <div id="errorAlert" class="alert-danger">
        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" style="width: 20px; height: 20px;">
            <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m9-.75a9 9 0 11-18 0 9 9 0 0118 0zm-9 3.75h.008v.008H12v-.008z" />
        </svg>
        <span id="errorMessage">Terdapat kesalahan saat memproses data.</span>
    </div>

    <div class="wrapper">

        <!-- PANEL INPUT KODE (Kiri) -->
        <div class="left-panel">
            <div class="card-title">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" style="width: 24px; height: 24px; color: var(--primary);">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M17.25 6.75L22.5 12l-5.25 5.25m-10.5 0L1.5 12l5.25-5.25m7.5-3l-4.5 16.5" />
                </svg>
                Input Source Code
            </div>

            <form id="analyzeForm" action="{{ route('halstead.hitung') }}" method="POST" enctype="multipart/form-data">
                @csrf

                <!-- Dropdown Bahasa -->
                <div class="form-group">
                    <label for="language">Bahasa Pemrograman</label>
                    <select id="language" name="language" class="select-control">
                        <option value="auto">Deteksi Otomatis (Umum)</option>
                        <option value="php">PHP</option>
                        <option value="javascript">JavaScript / TypeScript</option>
                        <option value="python">Python</option>
                        <option value="c_cpp_java">C / C++ / Java</option>
                    </select>
                </div>

                <!-- Drag & Drop Upload File -->
                <div class="file-drop-area" id="dropArea">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 16.5V9.75m0 0l3 3m-3-3l-3 3M6.75 19.5a4.5 4.5 0 01-1.41-8.775 5.25 5.25 0 0110.233-2.33 3 3 0 013.758 3.848A3.752 3.752 0 0118 19.5H6.75z" />
                    </svg>
                    <span id="uploadStatus">Tarik file Anda ke sini atau <strong>pilih file</strong></span>
                    <input type="file" id="sourceCodeFile" name="source_code" accept=".php,.js,.ts,.py,.txt,.c,.cpp,.java,.go">
                </div>

                <!-- Code Editor Textarea -->
                <div class="form-group">
                    <label for="codeText">Kode Sumber</label>
                    <div class="code-editor-wrapper">
                        <div class="code-editor-header">
                            <span class="editor-title">code_editor</span>
                            <span class="editor-title" id="charCounter">0 Karakter</span>
                        </div>
                        <textarea 
                            id="codeText" 
                            name="code_text" 
                            class="code-textarea" 
                            placeholder="Silakan tempel (paste) kode program Anda di sini atau unggah file di atas..."
                            spellcheck="false"
                        ></textarea>
                    </div>
                </div>

                <button type="submit" class="btn-analyze" id="btnAnalyze">
                    <span class="spinner" id="analyzeSpinner"></span>
                    <span id="btnText">Analisis Sekarang</span>
                </button>
            </form>
        </div>

        <!-- PANEL HASIL ANALISIS (Kanan) -->
        <div class="right-panel">
            <div class="card-title">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" style="width: 24px; height: 24px; color: var(--secondary);">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M7.5 14.25v2.25m3-4.5v4.5m3-6.75v6.75m3-9v9M6 20.25h12A2.25 2.25 0 0020.25 18V6A2.25 2.25 0 0018 3.75H6A2.25 2.25 0 003.75 6v12A2.25 2.25 0 006 20.25z" />
                </svg>
                Dashboard Analisis
            </div>

            <!-- Empty State -->
            <div id="emptyState" class="empty-state">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9.75 9.75l4.5 4.5m0-4.5l-4.5 4.5M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
                <h3>Belum Ada Hasil Analisis</h3>
                <p>Silakan masukkan kode program Anda atau unggah file di sebelah kiri, kemudian klik tombol "Analisis Sekarang".</p>
            </div>

            <!-- Container Hasil -->
            <div id="resultsContainer" class="results-container" style="display: none;">
                
                <!-- Complexity Header -->
                <div class="complexity-header">
                    <span class="complexity-title">Tingkat Kesulitan Koding (Difficulty)</span>
                    <span id="complexityBadge" class="complexity-badge">Rendah</span>
                </div>

                <!-- Grid Metrik -->
                <div class="metrics-grid">
                    
                    <!-- Volume -->
                    <div class="metric-card accent-cyan">
                        <div class="metric-header">
                            <span class="metric-label">Volume (V)</span>
                            <span class="metric-value" id="valV">0</span>
                        </div>
                        <p class="metric-desc">Ukuran program dalam bit berdasarkan kosakata dan panjang kode.</p>
                    </div>

                    <!-- Difficulty -->
                    <div class="metric-card accent-blue">
                        <div class="metric-header">
                            <span class="metric-label">Difficulty (D)</span>
                            <span class="metric-value" id="valD">0</span>
                        </div>
                        <p class="metric-desc">Tingkat kesulitan penulisan dan pemeliharaan kode program.</p>
                    </div>

                    <!-- Effort -->
                    <div class="metric-card accent-purple">
                        <div class="metric-header">
                            <span class="metric-label">Effort (E)</span>
                            <span class="metric-value" id="valE">0</span>
                        </div>
                        <p class="metric-desc">Beban pikiran dan usaha yang dibutuhkan untuk menulis kode.</p>
                    </div>

                    <!-- Time -->
                    <div class="metric-card accent-warning">
                        <div class="metric-header">
                            <span class="metric-label">Time Required (T)</span>
                            <span class="metric-value" id="valT">0s</span>
                        </div>
                        <p class="metric-desc">Estimasi waktu pengerjaan kode (dalam hitungan detik/menit).</p>
                    </div>

                    <!-- Bugs -->
                    <div class="metric-card accent-success">
                        <div class="metric-header">
                            <span class="metric-label">Estimated Bugs (B)</span>
                            <span class="metric-value" id="valB">0</span>
                        </div>
                        <p class="metric-desc">Perkiraan jumlah kesalahan/bug yang dikandung oleh sistem.</p>
                    </div>

                    <!-- Vocabulary & Length -->
                    <div class="metric-card">
                        <div class="metric-header">
                            <span class="metric-label">Vocabulary (n) / Length (N)</span>
                            <div>
                                <span class="metric-value" id="valn">0</span>
                                <span class="metric-label" style="font-size: 14px; margin: 0 4px;">/</span>
                                <span class="metric-value" id="valN">0</span>
                            </div>
                        </div>
                        <p class="metric-desc">Jumlah kosakata unik (n) dan total kemunculan token (N).</p>
                    </div>

                </div>

                <!-- Tab Rincian Operator & Operand -->
                <div class="details-tabs">
                    <div class="tab-nav">
                        <button type="button" class="tab-btn active" onclick="switchTab('operatorsTab', this)">
                            Operators (<span id="countOperators">0</span> unik / <span id="totalOperators">0</span> total)
                        </button>
                        <button type="button" class="tab-btn" onclick="switchTab('operandsTab', this)">
                            Operands (<span id="countOperands">0</span> unik / <span id="totalOperands">0</span> total)
                        </button>
                    </div>

                    <!-- Tab Operators -->
                    <div id="operatorsTab" class="tab-pane active">
                        <div class="details-table-wrapper">
                            <table class="details-table">
                                <thead>
                                    <tr>
                                        <th>Operator</th>
                                        <th>Kategori</th>
                                        <th class="right-align">Frekuensi</th>
                                    </tr>
                                </thead>
                                <tbody id="operatorsTableBody">
                                    <!-- Dinamis -->
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- Tab Operands -->
                    <div id="operandsTab" class="tab-pane">
                        <div class="details-table-wrapper">
                            <table class="details-table">
                                <thead>
                                    <tr>
                                        <th>Operand</th>
                                        <th>Kategori</th>
                                        <th class="right-align">Frekuensi</th>
                                    </tr>
                                </thead>
                                <tbody id="operandsTableBody">
                                    <!-- Dinamis -->
                                </tbody>
                            </table>
                        </div>
                    </div>

                </div>

            </div>

        </div>

    </div>

</div>

<script>
    const codeTextarea = document.getElementById('codeText');
    const charCounter = document.getElementById('charCounter');
    const dropArea = document.getElementById('dropArea');
    const sourceCodeFileInput = document.getElementById('sourceCodeFile');
    const uploadStatus = document.getElementById('uploadStatus');
    const languageSelect = document.getElementById('language');
    const analyzeForm = document.getElementById('analyzeForm');
    const btnAnalyze = document.getElementById('btnAnalyze');
    const btnText = document.getElementById('btnText');
    const analyzeSpinner = document.getElementById('analyzeSpinner');
    const errorAlert = document.getElementById('errorAlert');
    const errorMessage = document.getElementById('errorMessage');
    
    const emptyState = document.getElementById('emptyState');
    const resultsContainer = document.getElementById('resultsContainer');

    // Update Counter Karakter
    codeTextarea.addEventListener('input', () => {
        const length = codeTextarea.value.length;
        charCounter.textContent = `${length.toLocaleString('id-ID')} Karakter`;
    });

    // File Drag & Drop Handlers
    ['dragenter', 'dragover'].forEach(eventName => {
        dropArea.addEventListener(eventName, (e) => {
            e.preventDefault();
            dropArea.classList.add('drag-over');
        }, false);
    });

    ['dragleave', 'drop'].forEach(eventName => {
        dropArea.addEventListener(eventName, (e) => {
            e.preventDefault();
            dropArea.classList.remove('drag-over');
        }, false);
    });

    dropArea.addEventListener('drop', (e) => {
        const dt = e.dataTransfer;
        const files = dt.files;
        if (files.length > 0) {
            handleFile(files[0]);
        }
    });

    sourceCodeFileInput.addEventListener('change', (e) => {
        if (sourceCodeFileInput.files.length > 0) {
            handleFile(sourceCodeFileInput.files[0]);
        }
    });

    function handleFile(file) {
        uploadStatus.innerHTML = `File terpilih: <strong>${file.name}</strong> (${(file.size / 1024).toFixed(2)} KB)`;
        
        // Auto detect language from extension
        const ext = file.name.split('.').pop().toLowerCase();
        if (ext === 'php') {
            languageSelect.value = 'php';
        } else if (['js', 'ts', 'jsx', 'tsx'].includes(ext)) {
            languageSelect.value = 'javascript';
        } else if (ext === 'py') {
            languageSelect.value = 'python';
        } else if (['c', 'cpp', 'h', 'hpp', 'java'].includes(ext)) {
            languageSelect.value = 'c_cpp_java';
        } else {
            languageSelect.value = 'auto';
        }

        // Read file contents
        const reader = new FileReader();
        reader.onload = function(e) {
            codeTextarea.value = e.target.result;
            // trigger input event to update counter
            codeTextarea.dispatchEvent(new Event('input'));
        };
        reader.readAsText(file);
    }

    // Switch Tabs
    window.switchTab = function(tabId, btn) {
        // Hide all tab panes
        document.querySelectorAll('.tab-pane').forEach(pane => {
            pane.classList.remove('active');
        });
        // Remove active class from all buttons
        document.querySelectorAll('.tab-btn').forEach(button => {
            button.classList.remove('active');
        });
        
        // Show selected tab pane
        document.getElementById(tabId).classList.add('active');
        // Add active class to clicked button
        btn.classList.add('active');
    };

    // Helper format waktu
    function formatTime(seconds) {
        if (seconds < 60) {
            return `${seconds.toFixed(2)}s`;
        }
        const minutes = Math.floor(seconds / 60);
        const remainingSeconds = seconds % 60;
        if (minutes < 60) {
            return `${minutes}m ${remainingSeconds.toFixed(0)}s`;
        }
        const hours = Math.floor(minutes / 60);
        const remainingMinutes = minutes % 60;
        return `${hours}j ${remainingMinutes}m`;
    }

    // Form Submit Handler (AJAX)
    analyzeForm.addEventListener('submit', (e) => {
        e.preventDefault();
        
        // Sembunyikan alert error sebelumnya
        errorAlert.style.display = 'none';

        // Validasi client-side sederhana
        if (codeTextarea.value.trim() === '') {
            showError('Kode program tidak boleh kosong.');
            return;
        }

        // Tampilkan loading spinner
        btnAnalyze.disabled = true;
        analyzeSpinner.style.display = 'inline-block';
        btnText.textContent = 'Menganalisis...';

        const formData = new FormData(analyzeForm);
        // Hapus file dari formData agar tidak perlu diunggah lewat jaringan secara mubazir
        formData.delete('source_code');

        fetch(analyzeForm.action, {
            method: 'POST',
            body: formData,
            headers: {
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
            }
        })
        .then(response => {
            if (!response.ok) {
                return response.json().then(err => { throw err; });
            }
            return response.json();
        })
        .then(data => {
            // Sembunyikan loading
            btnAnalyze.disabled = false;
            analyzeSpinner.style.display = 'none';
            btnText.textContent = 'Analisis Sekarang';

            // Tampilkan container hasil
            emptyState.style.display = 'none';
            resultsContainer.style.display = 'block';

            // Set Values
            document.getElementById('complexityBadge').textContent = data.complexity;
            document.getElementById('complexityBadge').style.backgroundColor = data.complexityColor;
            document.getElementById('complexityBadge').style.color = '#ffffff';

            document.getElementById('valV').textContent = data.V.toFixed(2);
            document.getElementById('valD').textContent = data.D.toFixed(2);
            document.getElementById('valE').textContent = data.E.toFixed(2);
            document.getElementById('valT').textContent = formatTime(data.T);
            document.getElementById('valB').textContent = data.B.toFixed(4);
            document.getElementById('valn').textContent = data.n;
            document.getElementById('valN').textContent = data.N;

            // Details Counts
            document.getElementById('countOperators').textContent = data.n1;
            document.getElementById('totalOperators').textContent = data.N1;
            document.getElementById('countOperands').textContent = data.n2;
            document.getElementById('totalOperands').textContent = data.N2;

            // Populate Tables
            const operatorsTableBody = document.getElementById('operatorsTableBody');
            operatorsTableBody.innerHTML = '';
            
            if (Object.keys(data.operatorsDetail).length === 0) {
                operatorsTableBody.innerHTML = '<tr><td colspan="3" style="text-align: center; color: var(--text-muted);">Tidak ada operator terdeteksi</td></tr>';
            } else {
                for (const [token, info] of Object.entries(data.operatorsDetail)) {
                    const row = document.createElement('tr');
                    
                    const cellToken = document.createElement('td');
                    cellToken.className = 'code-font';
                    cellToken.textContent = token;
                    
                    const cellType = document.createElement('td');
                    cellType.textContent = info.type;
                    cellType.style.color = 'var(--text-secondary)';
                    
                    const cellCount = document.createElement('td');
                    cellCount.className = 'count-font';
                    cellCount.textContent = info.count;
                    
                    row.appendChild(cellToken);
                    row.appendChild(cellType);
                    row.appendChild(cellCount);
                    operatorsTableBody.appendChild(row);
                }
            }

            const operandsTableBody = document.getElementById('operandsTableBody');
            operandsTableBody.innerHTML = '';
            
            if (Object.keys(data.operandsDetail).length === 0) {
                operandsTableBody.innerHTML = '<tr><td colspan="3" style="text-align: center; color: var(--text-muted);">Tidak ada operand terdeteksi</td></tr>';
            } else {
                for (const [token, info] of Object.entries(data.operandsDetail)) {
                    const row = document.createElement('tr');
                    
                    const cellToken = document.createElement('td');
                    cellToken.className = 'code-font';
                    cellToken.textContent = token;
                    
                    const cellType = document.createElement('td');
                    cellType.textContent = info.type;
                    cellType.style.color = 'var(--text-secondary)';
                    
                    const cellCount = document.createElement('td');
                    cellCount.className = 'count-font';
                    cellCount.textContent = info.count;
                    
                    row.appendChild(cellToken);
                    row.appendChild(cellType);
                    row.appendChild(cellCount);
                    operandsTableBody.appendChild(row);
                }
            }

            // Scroll to results container on mobile view
            if (window.innerWidth <= 1024) {
                resultsContainer.scrollIntoView({ behavior: 'smooth' });
            }
        })
        .catch(err => {
            btnAnalyze.disabled = false;
            analyzeSpinner.style.display = 'none';
            btnText.textContent = 'Analisis Sekarang';
            
            const msg = err.error || err.message || 'Terjadi kesalahan sistem saat menganalisis kode.';
            showError(msg);
        });
    });

    function showError(msg) {
        errorMessage.textContent = msg;
        errorAlert.style.display = 'flex';
        errorAlert.scrollIntoView({ behavior: 'smooth', block: 'center' });
    }
</script>

</body>
</html>