INSERT IGNORE INTO roles (name, description) VALUES
('ADMIN', 'Akses penuh aplikasi'),
('OPERATOR', 'Mengelola data sekolah, siswa, nilai, dan laporan'),
('GURU', 'Mengelola data siswa dan nilai sesuai kewenangan'),
('KEPALA SEKOLAH', 'Melihat laporan, verifikasi, dan finalisasi');

INSERT IGNORE INTO schools (npsn, name, principal_name, status, level, city, province, email, phone, curriculum)
VALUES ('12345678', 'SD Demo ASIKSSD', 'Kepala Sekolah Demo', 'Negeri', 'SD', 'Kota Contoh', 'Provinsi Contoh', 'admin@asikssd.local', '021-000000', 'Kurikulum Merdeka');

UPDATE schools SET logo_path = 'assets/img/logo-asikssd.png' WHERE npsn = '12345678' AND (logo_path IS NULL OR logo_path = '');

INSERT INTO academic_years (school_id, name, semester, is_active)
SELECT s.id, '2025/2026', 'Genap', 1 FROM schools s
WHERE s.npsn = '12345678'
AND NOT EXISTS (SELECT 1 FROM academic_years ay WHERE ay.school_id = s.id AND ay.name = '2025/2026' AND ay.semester = 'Genap');

INSERT IGNORE INTO settings (school_id, setting_key, setting_value)
SELECT id, 'app_identity', 'ASIKSSD' FROM schools WHERE npsn = '12345678';

INSERT IGNORE INTO classes (school_id, name, level, is_final_grade)
SELECT id, 'VI A', 6, 1 FROM schools WHERE npsn = '12345678';

INSERT IGNORE INTO classes (school_id, name, level, is_final_grade)
SELECT id, 'VI B', 6, 1 FROM schools WHERE npsn = '12345678';

INSERT IGNORE INTO classes (school_id, name, level, is_final_grade)
SELECT id, 'V A', 5, 0 FROM schools WHERE npsn = '12345678';

INSERT IGNORE INTO subjects (school_id, code, name)
SELECT id, 'BIN', 'Bahasa Indonesia' FROM schools WHERE npsn = '12345678';

INSERT IGNORE INTO subjects (school_id, code, name)
SELECT id, 'MTK', 'Matematika' FROM schools WHERE npsn = '12345678';

INSERT IGNORE INTO subjects (school_id, code, name)
SELECT id, 'IPAS', 'IPAS' FROM schools WHERE npsn = '12345678';

INSERT IGNORE INTO subjects (school_id, code, name)
SELECT id, 'PP', 'Pendidikan Pancasila' FROM schools WHERE npsn = '12345678';

INSERT IGNORE INTO subjects (school_id, code, name)
SELECT id, 'PAI', 'PAI' FROM schools WHERE npsn = '12345678';

INSERT IGNORE INTO subjects (school_id, code, name)
SELECT id, 'PJOK', 'PJOK' FROM schools WHERE npsn = '12345678';

INSERT IGNORE INTO subjects (school_id, code, name)
SELECT id, 'SENI', 'Seni' FROM schools WHERE npsn = '12345678';

INSERT IGNORE INTO subjects (school_id, code, name)
SELECT id, 'BIG', 'Bahasa Inggris' FROM schools WHERE npsn = '12345678';

INSERT INTO grade_components (school_id, name, weight)
SELECT s.id, 'Rata-rata Rapor', 60 FROM schools s WHERE s.npsn = '12345678'
AND NOT EXISTS (SELECT 1 FROM grade_components gc WHERE gc.school_id = s.id AND gc.name = 'Rata-rata Rapor');

INSERT INTO grade_components (school_id, name, weight)
SELECT s.id, 'Asesmen Sekolah', 40 FROM schools s WHERE s.npsn = '12345678'
AND NOT EXISTS (SELECT 1 FROM grade_components gc WHERE gc.school_id = s.id AND gc.name = 'Asesmen Sekolah');

INSERT INTO graduation_rules (school_id, academic_year_id, name, minimum_score, report_weight, assessment_weight, require_complete_grades, require_administration, formula, is_active)
SELECT s.id, ay.id, 'Aturan Kelulusan Default', 75, 70, 30, 1, 0,
JSON_OBJECT('type', 'subject_weighted_average', 'report_scope', 'Nilai rapor semester 7 sampai 11', 'assessment_scope', 'Nilai Sumatif Akhir Jenjang (ASAJ)', 'components', JSON_ARRAY(JSON_OBJECT('key', 'report_average', 'weight', 70), JSON_OBJECT('key', 'assessment_score', 'weight', 30), JSON_OBJECT('key', 'achievement_score', 'weight', 0), JSON_OBJECT('key', 'extracurricular_score', 'weight', 0))),
1
FROM schools s
LEFT JOIN academic_years ay ON ay.school_id = s.id AND ay.is_active = 1
WHERE s.npsn = '12345678'
AND NOT EXISTS (SELECT 1 FROM graduation_rules gr WHERE gr.school_id = s.id AND gr.name = 'Aturan Kelulusan Default');

UPDATE graduation_rules gr
INNER JOIN schools s ON s.id = gr.school_id
SET gr.report_weight = 70,
    gr.assessment_weight = 30,
    gr.formula = JSON_OBJECT('type', 'subject_weighted_average', 'report_scope', 'Nilai rapor semester 7 sampai 11', 'assessment_scope', 'Nilai Sumatif Akhir Jenjang (ASAJ)', 'components', JSON_ARRAY(JSON_OBJECT('key', 'report_average', 'weight', 70), JSON_OBJECT('key', 'assessment_score', 'weight', 30), JSON_OBJECT('key', 'achievement_score', 'weight', 0), JSON_OBJECT('key', 'extracurricular_score', 'weight', 0)))
WHERE s.npsn = '12345678' AND gr.name = 'Aturan Kelulusan Default';

INSERT INTO students (school_id, class_id, nis, nisn, name, gender, birth_place, birth_date, status)
SELECT s.id, c.id, '240001', '0031234567', 'Alya Putri Pratama', 'P', 'Bandung', '2013-04-12', 'Aktif'
FROM schools s INNER JOIN classes c ON c.school_id = s.id AND c.name = 'VI A'
WHERE s.npsn = '12345678'
AND NOT EXISTS (SELECT 1 FROM students st WHERE st.school_id = s.id AND st.nisn = '0031234567');

INSERT INTO students (school_id, class_id, nis, nisn, name, gender, birth_place, birth_date, status)
SELECT s.id, c.id, '240002', '0031234568', 'Bagas Arya Saputra', 'L', 'Cimahi', '2013-08-21', 'Aktif'
FROM schools s INNER JOIN classes c ON c.school_id = s.id AND c.name = 'VI A'
WHERE s.npsn = '12345678'
AND NOT EXISTS (SELECT 1 FROM students st WHERE st.school_id = s.id AND st.nisn = '0031234568');

INSERT INTO students (school_id, class_id, nis, nisn, name, gender, birth_place, birth_date, status)
SELECT s.id, c.id, '240003', '0031234569', 'Citra Lestari', 'P', 'Jakarta', '2013-01-30', 'Aktif'
FROM schools s INNER JOIN classes c ON c.school_id = s.id AND c.name = 'VI B'
WHERE s.npsn = '12345678'
AND NOT EXISTS (SELECT 1 FROM students st WHERE st.school_id = s.id AND st.nisn = '0031234569');

INSERT INTO students (school_id, class_id, nis, nisn, name, gender, birth_place, birth_date, status)
SELECT s.id, c.id, '240004', '0031234570', 'Dimas Prasetyo', 'L', 'Bekasi', '2013-06-05', 'Aktif'
FROM schools s INNER JOIN classes c ON c.school_id = s.id AND c.name = 'VI B'
WHERE s.npsn = '12345678'
AND NOT EXISTS (SELECT 1 FROM students st WHERE st.school_id = s.id AND st.nisn = '0031234570');

INSERT INTO students (school_id, class_id, nis, nisn, name, gender, birth_place, birth_date, status)
SELECT s.id, c.id, '240005', '0031234571', 'Eka Rahmawati', 'P', 'Depok', '2013-09-14', 'Aktif'
FROM schools s INNER JOIN classes c ON c.school_id = s.id AND c.name = 'VI A'
WHERE s.npsn = '12345678'
AND NOT EXISTS (SELECT 1 FROM students st WHERE st.school_id = s.id AND st.nisn = '0031234571');

UPDATE students SET parent_name = 'Pratama Wijaya' WHERE nisn = '0031234567' AND (parent_name IS NULL OR parent_name = '');
UPDATE students SET parent_name = 'Saputra Hidayat' WHERE nisn = '0031234568' AND (parent_name IS NULL OR parent_name = '');
UPDATE students SET parent_name = 'Lestari Ningsih' WHERE nisn = '0031234569' AND (parent_name IS NULL OR parent_name = '');
UPDATE students SET parent_name = 'Prasetyo Nugroho' WHERE nisn = '0031234570' AND (parent_name IS NULL OR parent_name = '');
UPDATE students SET parent_name = 'Rahmawati Sari' WHERE nisn = '0031234571' AND (parent_name IS NULL OR parent_name = '');

INSERT INTO grades (school_id, academic_year_id, student_id, subject_id, semester, score)
SELECT s.id, ay.id, st.id, sub.id, sem.semester,
CASE
    WHEN st.nisn = '0031234567' THEN 88
    WHEN st.nisn = '0031234568' THEN 82
    WHEN st.nisn = '0031234569' THEN 91
    WHEN st.nisn = '0031234570' THEN 76
    WHEN st.nisn = '0031234571' THEN 86
    ELSE 80
END
+ CASE sub.code
    WHEN 'BIN' THEN 2
    WHEN 'MTK' THEN -1
    WHEN 'IPAS' THEN 1
    WHEN 'PP' THEN 3
    WHEN 'PAI' THEN 0
    WHEN 'PJOK' THEN 4
    WHEN 'SENI' THEN 2
    WHEN 'BIG' THEN -2
    ELSE 0
END
+ CASE sem.semester
    WHEN 'S7' THEN -2
    WHEN 'S8' THEN -1
    WHEN 'S9' THEN 0
    WHEN 'S10' THEN 1
    WHEN 'S11' THEN 2
    WHEN 'ASAJ' THEN 3
    ELSE 0
END AS score
FROM schools s
INNER JOIN academic_years ay ON ay.school_id = s.id AND ay.is_active = 1
INNER JOIN students st ON st.school_id = s.id AND st.nisn IN ('0031234567','0031234568','0031234569','0031234570','0031234571')
INNER JOIN subjects sub ON sub.school_id = s.id AND sub.is_active = 1
INNER JOIN (
    SELECT 'S7' AS semester UNION ALL
    SELECT 'S8' UNION ALL
    SELECT 'S9' UNION ALL
    SELECT 'S10' UNION ALL
    SELECT 'S11' UNION ALL
    SELECT 'ASAJ'
) sem
WHERE s.npsn = '12345678'
ON DUPLICATE KEY UPDATE score = VALUES(score);

INSERT INTO graduation_results (
    school_id,
    student_id,
    graduation_rule_id,
    report_average,
    assessment_score,
    final_score,
    status,
    verification_operator_at,
    verification_principal_at,
    finalized_at,
    notes
)
SELECT
    s.id,
    st.id,
    gr.id,
    ROUND(AVG(g.score), 2) AS report_average,
    CASE
        WHEN st.nisn = '0031234567' THEN 90
        WHEN st.nisn = '0031234568' THEN 84
        WHEN st.nisn = '0031234569' THEN 92
        WHEN st.nisn = '0031234570' THEN 80
        WHEN st.nisn = '0031234571' THEN 88
        ELSE 85
    END AS assessment_score,
    ROUND(
        (ROUND(AVG(g.score), 2) * gr.report_weight
        + CASE
            WHEN st.nisn = '0031234567' THEN 90
            WHEN st.nisn = '0031234568' THEN 84
            WHEN st.nisn = '0031234569' THEN 92
            WHEN st.nisn = '0031234570' THEN 80
            WHEN st.nisn = '0031234571' THEN 88
            ELSE 85
        END * gr.assessment_weight) / 100,
        2
    ) AS final_score,
    'LULUS',
    NOW(),
    NOW(),
    NOW(),
    'Hasil kelulusan telah difinalisasi oleh satuan pendidikan.'
FROM schools s
INNER JOIN academic_years ay ON ay.school_id = s.id AND ay.is_active = 1
INNER JOIN graduation_rules gr ON gr.school_id = s.id AND gr.is_active = 1
INNER JOIN students st ON st.school_id = s.id AND st.nisn IN ('0031234567','0031234568','0031234569','0031234570','0031234571')
INNER JOIN grades g ON g.student_id = st.id AND g.academic_year_id = ay.id
WHERE s.npsn = '12345678'
GROUP BY s.id, st.id, gr.id, gr.report_weight, gr.assessment_weight, st.nisn
ON DUPLICATE KEY UPDATE
    graduation_rule_id = VALUES(graduation_rule_id),
    report_average = VALUES(report_average),
    assessment_score = VALUES(assessment_score),
    final_score = VALUES(final_score),
    status = VALUES(status),
    verification_operator_at = VALUES(verification_operator_at),
    verification_principal_at = VALUES(verification_principal_at),
    finalized_at = VALUES(finalized_at),
    notes = VALUES(notes);

INSERT INTO graduation_subject_scores (
    school_id,
    student_id,
    subject_id,
    graduation_rule_id,
    report_average,
    assessment_score,
    achievement_score,
    extracurricular_score,
    final_score
)
SELECT
    s.id,
    st.id,
    sub.id,
    gr.id,
    ROUND(AVG(g.score), 2),
    CASE
        WHEN st.nisn = '0031234567' THEN 90
        WHEN st.nisn = '0031234568' THEN 84
        WHEN st.nisn = '0031234569' THEN 92
        WHEN st.nisn = '0031234570' THEN 80
        WHEN st.nisn = '0031234571' THEN 88
        ELSE 85
    END + CASE sub.code WHEN 'MTK' THEN -1 WHEN 'PJOK' THEN 2 WHEN 'BIG' THEN -2 ELSE 0 END,
    0,
    0,
    ROUND(
        (ROUND(AVG(g.score), 2) * gr.report_weight
        + (CASE
            WHEN st.nisn = '0031234567' THEN 90
            WHEN st.nisn = '0031234568' THEN 84
            WHEN st.nisn = '0031234569' THEN 92
            WHEN st.nisn = '0031234570' THEN 80
            WHEN st.nisn = '0031234571' THEN 88
            ELSE 85
        END + CASE sub.code WHEN 'MTK' THEN -1 WHEN 'PJOK' THEN 2 WHEN 'BIG' THEN -2 ELSE 0 END) * gr.assessment_weight) / 100,
        2
    )
FROM schools s
INNER JOIN graduation_rules gr ON gr.school_id = s.id AND gr.is_active = 1
INNER JOIN students st ON st.school_id = s.id AND st.nisn IN ('0031234567','0031234568','0031234569','0031234570','0031234571')
INNER JOIN subjects sub ON sub.school_id = s.id AND sub.is_active = 1
INNER JOIN grades g ON g.student_id = st.id AND g.subject_id = sub.id
WHERE s.npsn = '12345678'
GROUP BY s.id, st.id, sub.id, gr.id, gr.report_weight, gr.assessment_weight, st.nisn, sub.code
ON DUPLICATE KEY UPDATE
    graduation_rule_id = VALUES(graduation_rule_id),
    report_average = VALUES(report_average),
    assessment_score = VALUES(assessment_score),
    achievement_score = VALUES(achievement_score),
    extracurricular_score = VALUES(extracurricular_score),
    final_score = VALUES(final_score);
