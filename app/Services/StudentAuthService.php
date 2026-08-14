<?php

namespace App\Services;

use App\Core\Database;

class StudentAuthService
{
    public function attempt(string $nisn, string $password): bool
    {
        $pdo = Database::connection();
        $stmt = $pdo->prepare(
            'SELECT students.*, schools.name AS school_name, schools.npsn, classes.name AS class_name
             FROM students
             INNER JOIN schools ON schools.id = students.school_id
             LEFT JOIN classes ON classes.id = students.class_id
             WHERE students.nisn = :nisn AND students.status IN ("Aktif","Lulus")
             LIMIT 1'
        );
        $stmt->execute(['nisn' => $nisn]);
        $student = $stmt->fetch();

        if (!$student || empty($student['password_hash']) || !password_verify($password, $student['password_hash'])) {
            return false;
        }

        session_regenerate_id(true);
        $_SESSION['student'] = [
            'id' => (int) $student['id'],
            'school_id' => (int) $student['school_id'],
            'school_name' => $student['school_name'],
            'npsn' => $student['npsn'],
            'nisn' => $student['nisn'],
            'name' => $student['name'],
            'class_name' => $student['class_name'],
        ];

        return true;
    }

    public function logout(): void
    {
        unset($_SESSION['student']);
    }
}
