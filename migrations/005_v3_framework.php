<?php
/**
 * Migration 005 - Framework enhancements, fix weight_history, add new tables
 */
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/Database.php';

$db = Database::getInstance();
$pdo = $db->getPDO();
$isMySQL = DB_DRIVER === 'mysql';
$dt = $isMySQL ? 'DATETIME' : 'TEXT';
$intPK = $isMySQL ? 'INT AUTO_INCREMENT PRIMARY KEY' : 'INTEGER PRIMARY KEY AUTOINCREMENT';

echo "VetCare Pro v3.1 - Framework Migration\n";
echo "======================================\n";

// 1. Fix weight_history - drop and recreate with created_at
try {
    $count = $db->fetch('SELECT COUNT(*) as c FROM weight_history')['c'];
    if ($count == 0) {
        $pdo->exec("DROP TABLE IF EXISTS weight_history");
        $pdo->exec("CREATE TABLE weight_history (
            id {$intPK},
            patient_id INTEGER NOT NULL,
            weight DECIMAL(6,2) NOT NULL,
            measured_at DATE NOT NULL,
            measured_by INTEGER NULL,
            notes TEXT DEFAULT '',
            created_at {$dt} DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (patient_id) REFERENCES patients(id),
            FOREIGN KEY (measured_by) REFERENCES staff(id)
        )");
        echo "OK: weight_history recreated with created_at\n";
    } else {
        // If data exists, try ALTER
        try {
            $pdo->exec("ALTER TABLE weight_history ADD COLUMN created_at {$dt} DEFAULT CURRENT_TIMESTAMP");
            echo "OK: weight_history.created_at added via ALTER\n";
        } catch (Exception $e) {
            echo "SKIP: weight_history.created_at already exists or error\n";
        }
    }
} catch (Exception $e) {
    echo "ERROR weight_history: " . $e->getMessage() . "\n";
}

// 2. Doctor work schedules
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS staff_schedules (
        id {$intPK},
        staff_id INTEGER NOT NULL,
        schedule_date DATE NOT NULL,
        start_time VARCHAR(10) DEFAULT '09:00',
        end_time VARCHAR(10) DEFAULT '18:00',
        break_start VARCHAR(10) DEFAULT '12:00',
        break_end VARCHAR(10) DEFAULT '13:00',
        schedule_type VARCHAR(20) DEFAULT 'regular',
        notes TEXT DEFAULT '',
        created_by INTEGER NULL,
        created_at {$dt} DEFAULT CURRENT_TIMESTAMP,
        updated_at {$dt} DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (staff_id) REFERENCES staff(id)
    )");
    echo "OK: staff_schedules\n";
} catch (Exception $e) { echo "ERROR: staff_schedules: " . $e->getMessage() . "\n"; }

// 3. Staff schedule templates (weekly patterns)
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS staff_schedule_templates (
        id {$intPK},
        staff_id INTEGER NOT NULL,
        day_of_week INTEGER NOT NULL,
        start_time VARCHAR(10) DEFAULT '09:00',
        end_time VARCHAR(10) DEFAULT '18:00',
        break_start VARCHAR(10) DEFAULT '12:00',
        break_end VARCHAR(10) DEFAULT '13:00',
        is_off INTEGER DEFAULT 0,
        created_at {$dt} DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (staff_id) REFERENCES staff(id)
    )");
    echo "OK: staff_schedule_templates\n";
} catch (Exception $e) { echo "ERROR: staff_schedule_templates: " . $e->getMessage() . "\n"; }

// 4. Patient images table
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS patient_images (
        id {$intPK},
        patient_id INTEGER NOT NULL,
        file_path VARCHAR(255) NOT NULL,
        thumbnail_path VARCHAR(255) DEFAULT '',
        image_type VARCHAR(30) DEFAULT 'photo',
        caption VARCHAR(255) DEFAULT '',
        taken_at DATE NULL,
        uploaded_by INTEGER NULL,
        created_at {$dt} DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (patient_id) REFERENCES patients(id),
        FOREIGN KEY (uploaded_by) REFERENCES staff(id)
    )");
    echo "OK: patient_images\n";
} catch (Exception $e) { echo "ERROR: patient_images: " . $e->getMessage() . "\n"; }

// 5. Closed days table (flexible)
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS closed_days (
        id {$intPK},
        closed_date DATE NULL,
        day_of_week INTEGER NULL,
        is_recurring INTEGER DEFAULT 0,
        reason VARCHAR(255) DEFAULT '',
        created_at {$dt} DEFAULT CURRENT_TIMESTAMP
    )");
    echo "OK: closed_days\n";
} catch (Exception $e) { echo "ERROR: closed_days: " . $e->getMessage() . "\n"; }

// 6. Add specialty column to staff if not present
$staffAlterations = [
    "ALTER TABLE staff ADD COLUMN specialty VARCHAR(100) DEFAULT ''",
    "ALTER TABLE staff ADD COLUMN color VARCHAR(20) DEFAULT '#4f46e5'",
];
foreach ($staffAlterations as $sql) {
    try { $pdo->exec($sql); } catch (Exception $e) { /* already exists */ }
}
echo "OK: staff columns checked\n";

// 7. Add booking_source to appointments if not present
$apptAlterations = [
    "ALTER TABLE appointments ADD COLUMN is_new_patient INTEGER DEFAULT 0",
    "ALTER TABLE appointments ADD COLUMN owner_email_text VARCHAR(255) DEFAULT ''",
];
foreach ($apptAlterations as $sql) {
    try { $pdo->exec($sql); } catch (Exception $e) { /* already exists */ }
}
echo "OK: appointments columns checked\n";

echo "\nMigration 005 completed.\n";
