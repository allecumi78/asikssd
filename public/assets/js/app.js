const sidebarToggle = document.getElementById('sidebarToggle');
const themeToggle = document.getElementById('themeToggle');
const fullscreenToggle = document.getElementById('fullscreenToggle');
const loadingOverlay = document.getElementById('loadingOverlay');

window.addEventListener('load', () => {
    if (loadingOverlay) {
        loadingOverlay.classList.add('is-hidden');
    }
});

const announcementTimer = document.getElementById('announcementTimer');
if (announcementTimer) {
    const target = new Date(announcementTimer.getAttribute('data-target').replace(' ', 'T') + '+07:00').getTime();
    const renderTimer = () => {
        const distance = target - Date.now();
        if (distance <= 0) {
            announcementTimer.classList.remove('countdown-timer');
            announcementTimer.innerHTML = '<div class="announcement-started">Telah Dimulai</div>';
            return;
        }

        const days = Math.floor(distance / (1000 * 60 * 60 * 24));
        const hours = Math.floor((distance / (1000 * 60 * 60)) % 24);
        const minutes = Math.floor((distance / (1000 * 60)) % 60);
        const seconds = Math.floor((distance / 1000) % 60);
        const pad = (value) => String(value).padStart(2, '0');

        announcementTimer.innerHTML = [
            ['Hari', days],
            ['Jam', hours],
            ['Menit', minutes],
            ['Detik', seconds]
        ].map(([label, value]) => `<div class="time-cell"><b>${pad(value)}</b><small>${label}</small></div>`).join('');
    };

    renderTimer();
    setInterval(renderTimer, 1000);
}

if (sidebarToggle) {
    sidebarToggle.addEventListener('click', () => {
        document.body.classList.toggle('sidebar-collapsed');
    });
}

if (themeToggle) {
    const savedTheme = localStorage.getItem('asikssd-theme');
    if (savedTheme === 'dark') {
        document.documentElement.classList.add('dark-mode');
    }

    themeToggle.addEventListener('click', () => {
        document.documentElement.classList.toggle('dark-mode');
        localStorage.setItem(
            'asikssd-theme',
            document.documentElement.classList.contains('dark-mode') ? 'dark' : 'light'
        );
    });
}

if (fullscreenToggle) {
    fullscreenToggle.addEventListener('click', async () => {
        if (!document.fullscreenElement) {
            await document.documentElement.requestFullscreen();
            fullscreenToggle.innerHTML = '<i class="fa-solid fa-compress"></i>';
            return;
        }

        await document.exitFullscreen();
        fullscreenToggle.innerHTML = '<i class="fa-solid fa-expand"></i>';
    });
}

function renderDashboardCharts() {
    if (!window.Chart || !window.asikssdDashboard) {
        return;
    }

    const palette = {
        indigo: '#4f46e5',
        cyan: '#0891b2',
        green: '#16a34a',
        amber: '#d97706',
        red: '#dc2626',
        gray: '#94a3b8'
    };

    const classCanvas = document.getElementById('classChart');
    if (classCanvas) {
        new Chart(classCanvas, {
            type: 'bar',
            data: {
                labels: window.asikssdDashboard.classLabels.length ? window.asikssdDashboard.classLabels : ['Belum ada kelas'],
                datasets: [{
                    label: 'Siswa',
                    data: window.asikssdDashboard.classTotals.length ? window.asikssdDashboard.classTotals : [0],
                    backgroundColor: [palette.indigo, palette.cyan, palette.green, palette.amber],
                    borderRadius: 6
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { display: false } },
                scales: { y: { beginAtZero: true, ticks: { precision: 0 } } }
            }
        });
    }

    const gradeCanvas = document.getElementById('gradeChart');
    if (gradeCanvas) {
        new Chart(gradeCanvas, {
            type: 'doughnut',
            data: {
                labels: ['Sudah Ada Nilai', 'Belum Ada Nilai'],
                datasets: [{
                    data: window.asikssdDashboard.gradeTotals,
                    backgroundColor: [palette.green, palette.gray],
                    borderWidth: 0
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                cutout: '68%',
                plugins: { legend: { position: 'bottom' } }
            }
        });
    }

    const graduationCanvas = document.getElementById('graduationChart');
    if (graduationCanvas) {
        new Chart(graduationCanvas, {
            type: 'doughnut',
            data: {
                labels: ['Lulus', 'Tidak Lulus', 'Belum Diproses'],
                datasets: [{
                    data: window.asikssdDashboard.graduationTotals,
                    backgroundColor: [palette.green, palette.red, palette.amber],
                    borderWidth: 0
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                cutout: '68%',
                plugins: { legend: { position: 'bottom' } }
            }
        });
    }
}

renderDashboardCharts();

const studentModal = document.getElementById('studentModal');
if (studentModal) {
    studentModal.addEventListener('show.bs.modal', (event) => {
        const button = event.relatedTarget;
        const mode = button?.getAttribute('data-mode') || 'create';
        const student = button?.getAttribute('data-student') ? JSON.parse(button.getAttribute('data-student')) : {};
        const form = studentModal.querySelector('form');

        form.reset();
        studentModal.querySelector('.modal-title').textContent = mode === 'update' ? 'Edit Siswa' : 'Tambah Siswa';
        form.elements.action.value = mode;
        form.elements.id.value = student.id || '';
        ['nis', 'nisn', 'name', 'gender', 'class_id', 'birth_place', 'birth_date', 'parent_name', 'status'].forEach((field) => {
            if (form.elements[field]) {
                form.elements[field].value = student[field] || '';
            }
        });
    });
}

const detailModal = document.getElementById('detailModal');
if (detailModal) {
    detailModal.addEventListener('show.bs.modal', (event) => {
        const student = JSON.parse(event.relatedTarget.getAttribute('data-student'));
        document.getElementById('detailContent').innerHTML = `
            <dl class="detail-list">
                <dt>NIS</dt><dd>${student.nis || '-'}</dd>
                <dt>NISN</dt><dd>${student.nisn || '-'}</dd>
                <dt>Nama</dt><dd>${student.name || '-'}</dd>
                <dt>Jenis Kelamin</dt><dd>${student.gender || '-'}</dd>
                <dt>Tempat, Tanggal Lahir</dt><dd>${student.birth_place || '-'}, ${student.birth_date || '-'}</dd>
                <dt>Nama Orang Tua/Wali</dt><dd>${student.parent_name || '-'}</dd>
                <dt>Kelas</dt><dd>${student.class_name || '-'}</dd>
                <dt>Status</dt><dd>${student.status || '-'}</dd>
            </dl>
        `;
    });
}

document.querySelectorAll('.delete-form').forEach((form) => {
    form.addEventListener('submit', (event) => {
        if (!confirm('Anda yakin ingin menghapus data ini?')) {
            event.preventDefault();
        }
    });
});

document.querySelectorAll('.confirm-form').forEach((form) => {
    form.addEventListener('submit', (event) => {
        if (!confirm('Anda yakin ingin melanjutkan tindakan ini?')) {
            event.preventDefault();
        }
    });
});

const processModal = document.getElementById('processModal');
if (processModal) {
    processModal.addEventListener('show.bs.modal', (event) => {
        const button = event.relatedTarget;
        const form = processModal.querySelector('form');
        form.reset();
        form.elements.student_id.value = button.getAttribute('data-student-id');
        form.elements.notes.value = button.getAttribute('data-notes') || '';
        document.getElementById('processStudentName').textContent = button.getAttribute('data-name') || '';
        const scores = button.getAttribute('data-scores') ? JSON.parse(button.getAttribute('data-scores')) : {};
        Object.entries(scores).forEach(([subjectId, score]) => {
            const reportInput = form.elements[`report_scores[${subjectId}]`];
            const assessmentInput = form.elements[`assessment_scores[${subjectId}]`];
            if (reportInput && score.report_average !== '') {
                reportInput.value = score.report_average;
            }
            if (assessmentInput && score.assessment_score !== '') {
                assessmentInput.value = score.assessment_score;
            }
        });
    });
}

const resetPasswordModal = document.getElementById('resetPasswordModal');
if (resetPasswordModal) {
    resetPasswordModal.addEventListener('show.bs.modal', (event) => {
        const button = event.relatedTarget;
        const form = resetPasswordModal.querySelector('form');
        form.reset();
        form.elements.user_id.value = button.getAttribute('data-user-id');
        document.getElementById('resetPasswordUserName').textContent = button.getAttribute('data-name') || '';
    });
}

const importFile = document.getElementById('importFile');
const importPreview = document.getElementById('importPreview');
const validationSummary = document.getElementById('validationSummary');
const importRows = document.getElementById('importRows');
const importFilename = document.getElementById('importFilename');
const saveImport = document.getElementById('saveImport');
const downloadTemplate = document.getElementById('downloadTemplate');

const requiredStudentColumns = ['NIS', 'NISN', 'NAMA', 'JENIS_KELAMIN', 'TEMPAT_LAHIR', 'TANGGAL_LAHIR', 'NAMA_ORANG_TUA', 'KELAS', 'STATUS'];

function normalizeCell(value) {
    return String(value ?? '').trim();
}

function renderImportPreview(rows, errors) {
    if (!importPreview) {
        return;
    }

    importPreview.querySelector('thead').innerHTML = `<tr>${requiredStudentColumns.map((column) => `<th>${column}</th>`).join('')}<th>Validasi</th></tr>`;
    importPreview.querySelector('tbody').innerHTML = rows.map((row, index) => {
        const rowErrors = errors[index] || [];
        return `<tr class="${rowErrors.length ? 'table-danger' : 'table-success'}">
            ${requiredStudentColumns.map((column) => `<td>${normalizeCell(row[column]) || '-'}</td>`).join('')}
            <td>${rowErrors.length ? rowErrors.join('<br>') : 'Valid'}</td>
        </tr>`;
    }).join('') || '<tr><td class="empty-state" colspan="9">Tidak ada data pada file.</td></tr>';
}

function validateStudentImport(rows) {
    const classes = (window.asikssdClasses || []).map((name) => name.toUpperCase());
    const seenNisn = new Set();
    const errors = {};

    rows.forEach((row, index) => {
        const rowErrors = [];
        requiredStudentColumns.forEach((column) => {
            if (!Object.prototype.hasOwnProperty.call(row, column)) {
                rowErrors.push(`Kolom ${column} tidak ditemukan`);
            }
        });

        ['NIS', 'NISN', 'NAMA', 'JENIS_KELAMIN', 'TANGGAL_LAHIR', 'KELAS', 'STATUS'].forEach((column) => {
            if (!normalizeCell(row[column])) {
                rowErrors.push(`${column} wajib diisi`);
            }
        });

        const gender = normalizeCell(row.JENIS_KELAMIN).toUpperCase();
        if (gender && !['L', 'P'].includes(gender)) {
            rowErrors.push('JENIS_KELAMIN harus L atau P');
        }

        const status = normalizeCell(row.STATUS);
        if (status && !['Aktif', 'Mutasi', 'Lulus', 'Tidak Aktif'].includes(status)) {
            rowErrors.push('STATUS tidak valid');
        }

        const className = normalizeCell(row.KELAS).toUpperCase();
        if (className && !classes.includes(className)) {
            rowErrors.push('KELAS tidak ditemukan');
        }

        const nisn = normalizeCell(row.NISN);
        if (nisn && seenNisn.has(nisn)) {
            rowErrors.push('NISN duplikat pada file');
        }
        seenNisn.add(nisn);

        if (rowErrors.length) {
            errors[index] = rowErrors;
        }
    });

    return errors;
}

if (downloadTemplate) {
    downloadTemplate.addEventListener('click', () => {
        const exampleClass = (window.asikssdClasses || ['VI A'])[0];
        const rows = [
            requiredStudentColumns,
            ['240010', '0031234570', 'Nama Siswa Contoh', 'L', 'Bandung', '2013-05-17', exampleClass, 'Aktif']
        ];
        const worksheet = XLSX.utils.aoa_to_sheet(rows);
        const workbook = XLSX.utils.book_new();
        XLSX.utils.book_append_sheet(workbook, worksheet, 'DATA_SISWA');
        XLSX.writeFile(workbook, 'template-import-siswa-asikssd.xlsx');
    });
}

if (importFile) {
    importFile.addEventListener('change', async () => {
        const file = importFile.files[0];
        saveImport.disabled = true;
        importRows.value = '';

        if (!file) {
            return;
        }

        const buffer = await file.arrayBuffer();
        const workbook = XLSX.read(buffer, { type: 'array', cellDates: false });
        const sheet = workbook.Sheets[workbook.SheetNames[0]];
        const rows = XLSX.utils.sheet_to_json(sheet, { defval: '' }).map((row) => {
            const normalized = {};
            Object.entries(row).forEach(([key, value]) => {
                normalized[String(key).trim().toUpperCase()] = normalizeCell(value);
            });
            return normalized;
        });

        const errors = validateStudentImport(rows);
        const validRows = rows.filter((_, index) => !errors[index]);
        renderImportPreview(rows, errors);

        validationSummary.innerHTML = `
            <div class="alert ${Object.keys(errors).length ? 'alert-warning' : 'alert-success'} mt-3">
                Preview selesai. ${validRows.length} data valid, ${Object.keys(errors).length} data perlu diperbaiki.
            </div>
        `;

        importRows.value = JSON.stringify(validRows);
        importFilename.value = file.name;
        saveImport.disabled = validRows.length === 0;
    });
}

const gradeImportFile = document.getElementById('gradeImportFile');
const gradeImportPreview = document.getElementById('gradeImportPreview');
const gradeImportRows = document.getElementById('gradeImportRows');
const gradeValidationSummary = document.getElementById('gradeValidationSummary');
const saveGradeImport = document.getElementById('saveGradeImport');
const downloadGradeTemplate = document.getElementById('downloadGradeTemplate');
const requiredGradeColumns = ['NISN', 'NAMA', 'NILAI'];

if (downloadGradeTemplate) {
    downloadGradeTemplate.addEventListener('click', () => {
        const firstStudent = (window.asikssdGradeStudents || [{ nisn: '0031234567', name: 'Nama Siswa' }])[0];
        const worksheet = XLSX.utils.aoa_to_sheet([
            requiredGradeColumns,
            [firstStudent.nisn, firstStudent.name, 85]
        ]);
        const workbook = XLSX.utils.book_new();
        XLSX.utils.book_append_sheet(workbook, worksheet, 'DATA_NILAI');
        XLSX.writeFile(workbook, 'template-import-nilai-asikssd.xlsx');
    });
}

function validateGradeImport(rows) {
    const studentMap = new Map((window.asikssdGradeStudents || []).map((student) => [student.nisn, student.name.toUpperCase()]));
    const seen = new Set();
    const errors = {};

    rows.forEach((row, index) => {
        const rowErrors = [];
        requiredGradeColumns.forEach((column) => {
            if (!Object.prototype.hasOwnProperty.call(row, column) || !normalizeCell(row[column])) {
                rowErrors.push(`${column} wajib diisi`);
            }
        });

        const nisn = normalizeCell(row.NISN);
        const name = normalizeCell(row.NAMA).toUpperCase();
        const score = normalizeCell(row.NILAI);

        if (nisn && seen.has(nisn)) {
            rowErrors.push('NISN duplikat pada file');
        }
        seen.add(nisn);

        if (nisn && !studentMap.has(nisn)) {
            rowErrors.push('NISN tidak ditemukan pada kelas terpilih');
        } else if (nisn && name && studentMap.get(nisn) !== name) {
            rowErrors.push('Nama tidak sesuai database');
        }

        if (score && (Number.isNaN(Number(score)) || Number(score) < 0 || Number(score) > 100)) {
            rowErrors.push('NILAI harus angka 0-100');
        }

        if (rowErrors.length) {
            errors[index] = rowErrors;
        }
    });

    return errors;
}

if (gradeImportFile) {
    gradeImportFile.addEventListener('change', async () => {
        const file = gradeImportFile.files[0];
        saveGradeImport.disabled = true;
        gradeImportRows.value = '';
        if (!file) {
            return;
        }

        const workbook = XLSX.read(await file.arrayBuffer(), { type: 'array', cellDates: false });
        const sheet = workbook.Sheets[workbook.SheetNames[0]];
        const rows = XLSX.utils.sheet_to_json(sheet, { defval: '' }).map((row) => {
            const normalized = {};
            Object.entries(row).forEach(([key, value]) => {
                normalized[String(key).trim().toUpperCase()] = normalizeCell(value);
            });
            return normalized;
        });
        const errors = validateGradeImport(rows);
        const validRows = rows.filter((_, index) => !errors[index]);

        gradeImportPreview.querySelector('thead').innerHTML = `<tr>${requiredGradeColumns.map((column) => `<th>${column}</th>`).join('')}<th>Validasi</th></tr>`;
        gradeImportPreview.querySelector('tbody').innerHTML = rows.map((row, index) => {
            const rowErrors = errors[index] || [];
            return `<tr class="${rowErrors.length ? 'table-danger' : 'table-success'}">
                ${requiredGradeColumns.map((column) => `<td>${normalizeCell(row[column]) || '-'}</td>`).join('')}
                <td>${rowErrors.length ? rowErrors.join('<br>') : 'Valid'}</td>
            </tr>`;
        }).join('') || '<tr><td class="empty-state" colspan="4">Tidak ada data.</td></tr>';

        gradeValidationSummary.innerHTML = `
            <div class="alert ${Object.keys(errors).length ? 'alert-warning' : 'alert-success'} mt-3">
                Preview selesai. ${validRows.length} data valid, ${Object.keys(errors).length} data perlu diperbaiki.
            </div>
        `;
        gradeImportRows.value = JSON.stringify(validRows);
        saveGradeImport.disabled = validRows.length === 0;
    });
}
