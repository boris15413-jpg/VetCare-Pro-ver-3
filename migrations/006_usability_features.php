<?php
/**
 * Migration 006 - Usability Features
 * - Clinical templates table
 * - Accounting queue number support
 * - Priority reservation support
 * - Queue number on appointments
 */

$db = Database::getInstance();
$pdo = $db->getPDO();

echo "Running Migration 006: Usability Features...\n";

// 1. Clinical Templates table
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS clinical_templates (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        template_type VARCHAR(50) NOT NULL DEFAULT 'soap',
        template_name VARCHAR(255) NOT NULL,
        category VARCHAR(100) DEFAULT '',
        species VARCHAR(50) DEFAULT '',
        content TEXT NOT NULL DEFAULT '{}',
        is_active INTEGER NOT NULL DEFAULT 1,
        sort_order INTEGER DEFAULT 0,
        created_by INTEGER,
        created_at TEXT DEFAULT (datetime('now','localtime')),
        updated_at TEXT DEFAULT (datetime('now','localtime')),
        FOREIGN KEY (created_by) REFERENCES staff(id)
    )");
    echo "  - clinical_templates table: OK\n";
} catch (Exception $e) { echo "  - clinical_templates: " . $e->getMessage() . "\n"; }

// 2. Add queue_number to appointments
try {
    $cols = $pdo->query("PRAGMA table_info(appointments)")->fetchAll(PDO::FETCH_ASSOC);
    $colNames = array_column($cols, 'name');
    if (!in_array('queue_number', $colNames)) {
        $pdo->exec("ALTER TABLE appointments ADD COLUMN queue_number INTEGER DEFAULT NULL");
        echo "  - appointments.queue_number: added\n";
    } else {
        echo "  - appointments.queue_number: already exists\n";
    }
} catch (Exception $e) { echo "  - queue_number: " . $e->getMessage() . "\n"; }

// 3. Add is_priority to appointments
try {
    $cols = $pdo->query("PRAGMA table_info(appointments)")->fetchAll(PDO::FETCH_ASSOC);
    $colNames = array_column($cols, 'name');
    if (!in_array('is_priority', $colNames)) {
        $pdo->exec("ALTER TABLE appointments ADD COLUMN is_priority INTEGER DEFAULT 0");
        echo "  - appointments.is_priority: added\n";
    } else {
        echo "  - appointments.is_priority: already exists\n";
    }
} catch (Exception $e) { echo "  - is_priority: " . $e->getMessage() . "\n"; }

echo "Migration 006 completed successfully.\n";
