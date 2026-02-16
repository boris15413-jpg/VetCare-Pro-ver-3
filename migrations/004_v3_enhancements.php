<?php
/**
 * Migration 004 - Add missing columns, fix schema for v3.0 features
 */
$db = Database::getInstance();

// Ensure weight_history table exists with proper schema
try {
    $db->query("SELECT 1 FROM weight_history LIMIT 1");
    echo "weight_history: already exists\n";
} catch (Exception $e) {
    $db->query("CREATE TABLE IF NOT EXISTS weight_history (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        patient_id INTEGER NOT NULL,
        weight REAL NOT NULL,
        measured_at DATE NOT NULL,
        measured_by INTEGER,
        notes TEXT DEFAULT '',
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (patient_id) REFERENCES patients(id),
        FOREIGN KEY (measured_by) REFERENCES staff(id)
    )");
    echo "weight_history: created\n";
}

// Ensure referrals table has all needed columns
try {
    $db->query("SELECT destination_address FROM referrals LIMIT 1");
} catch (Exception $e) {
    try {
        $db->query("ALTER TABLE referrals ADD COLUMN destination_address TEXT DEFAULT ''");
        $db->query("ALTER TABLE referrals ADD COLUMN destination_phone TEXT DEFAULT ''");
        $db->query("ALTER TABLE referrals ADD COLUMN test_results_summary TEXT DEFAULT ''");
        $db->query("ALTER TABLE referrals ADD COLUMN request_items TEXT DEFAULT ''");
        $db->query("ALTER TABLE referrals ADD COLUMN urgency TEXT DEFAULT 'normal'");
        echo "referrals: columns added\n";
    } catch (Exception $e2) { echo "referrals: " . $e2->getMessage() . "\n"; }
}

// Ensure consent_forms has all columns
try {
    $db->query("SELECT risks_explained FROM consent_forms LIMIT 1");
} catch (Exception $e) {
    try {
        $db->query("ALTER TABLE consent_forms ADD COLUMN risks_explained TEXT DEFAULT ''");
        $db->query("ALTER TABLE consent_forms ADD COLUMN alternatives_explained TEXT DEFAULT ''");
        $db->query("ALTER TABLE consent_forms ADD COLUMN signed_by_name TEXT DEFAULT ''");
        echo "consent_forms: columns added\n";
    } catch (Exception $e2) { echo "consent_forms: " . $e2->getMessage() . "\n"; }
}

// Ensure discharge_summaries has all columns
try {
    $db->query("SELECT surgery_details FROM discharge_summaries LIMIT 1");
} catch (Exception $e) {
    try {
        $db->query("ALTER TABLE discharge_summaries ADD COLUMN surgery_details TEXT DEFAULT ''");
        $db->query("ALTER TABLE discharge_summaries ADD COLUMN medications_on_discharge TEXT DEFAULT ''");
        $db->query("ALTER TABLE discharge_summaries ADD COLUMN diet_instructions TEXT DEFAULT ''");
        $db->query("ALTER TABLE discharge_summaries ADD COLUMN exercise_restrictions TEXT DEFAULT ''");
        $db->query("ALTER TABLE discharge_summaries ADD COLUMN follow_up_plan TEXT DEFAULT ''");
        $db->query("ALTER TABLE discharge_summaries ADD COLUMN next_appointment DATE");
        $db->query("ALTER TABLE discharge_summaries ADD COLUMN prognosis TEXT DEFAULT ''");
        $db->query("ALTER TABLE discharge_summaries ADD COLUMN owner_instructions TEXT DEFAULT ''");
        $db->query("ALTER TABLE discharge_summaries ADD COLUMN attending_vet INTEGER");
        echo "discharge_summaries: columns added\n";
    } catch (Exception $e2) { echo "discharge_summaries: " . $e2->getMessage() . "\n"; }
}

// Add is_deceased to patients if missing
try {
    $db->query("SELECT is_deceased FROM patients LIMIT 1");
} catch (Exception $e) {
    $db->query("ALTER TABLE patients ADD COLUMN is_deceased INTEGER DEFAULT 0");
    echo "patients.is_deceased: added\n";
}

// Add postal_code to owners if missing
try {
    $db->query("SELECT postal_code FROM owners LIMIT 1");
} catch (Exception $e) {
    $db->query("ALTER TABLE owners ADD COLUMN postal_code TEXT DEFAULT ''");
    echo "owners.postal_code: added\n";
}

// Ensure insurance_policies has plan_name
try {
    $db->query("SELECT plan_name FROM insurance_policies LIMIT 1");
} catch (Exception $e) {
    try {
        $db->query("ALTER TABLE insurance_policies ADD COLUMN plan_name TEXT DEFAULT ''");
        echo "insurance_policies.plan_name: added\n";
    } catch (Exception $e2) { /* ignore */ }
}

// Ensure insurance_master has claim_format
try {
    $db->query("SELECT claim_format FROM insurance_master LIMIT 1");
} catch (Exception $e) {
    try {
        $db->query("ALTER TABLE insurance_master ADD COLUMN claim_format TEXT DEFAULT 'generic'");
        echo "insurance_master.claim_format: added\n";
    } catch (Exception $e2) { /* ignore */ }
}

echo "\nMigration 004 completed successfully.\n";
