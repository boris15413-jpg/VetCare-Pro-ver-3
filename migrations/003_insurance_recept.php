<?php
/**
 * VetCare Pro v3.0 - Insurance & Receipt (レセプト) Migration
 */
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/Database.php';

$db = Database::getInstance();
$pdo = $db->getPDO();

$isMySQL = DB_DRIVER === 'mysql';
$dt = $isMySQL ? 'DATETIME' : 'TEXT';
$intPK = $isMySQL ? 'INT AUTO_INCREMENT PRIMARY KEY' : 'INTEGER PRIMARY KEY AUTOINCREMENT';

$tables = [
    // Insurance company master
    "CREATE TABLE IF NOT EXISTS insurance_master (
        id {$intPK},
        company_code VARCHAR(20) UNIQUE,
        company_name VARCHAR(200) NOT NULL,
        company_name_kana VARCHAR(200) DEFAULT '',
        postal_code VARCHAR(10) DEFAULT '',
        address TEXT DEFAULT '',
        phone VARCHAR(20) DEFAULT '',
        fax VARCHAR(20) DEFAULT '',
        email VARCHAR(255) DEFAULT '',
        website VARCHAR(255) DEFAULT '',
        coverage_rates TEXT DEFAULT '50,70,100',
        claim_format VARCHAR(50) DEFAULT 'standard',
        notes TEXT DEFAULT '',
        is_active INTEGER DEFAULT 1,
        created_at {$dt} DEFAULT CURRENT_TIMESTAMP,
        updated_at {$dt} DEFAULT CURRENT_TIMESTAMP
    )",

    // Insurance policies for each patient
    "CREATE TABLE IF NOT EXISTS insurance_policies (
        id {$intPK},
        patient_id INTEGER NOT NULL,
        insurance_master_id INTEGER NULL,
        company_name VARCHAR(200) NOT NULL,
        policy_number VARCHAR(100) NOT NULL,
        coverage_rate INTEGER DEFAULT 50,
        plan_name VARCHAR(100) DEFAULT '',
        holder_name VARCHAR(100) DEFAULT '',
        start_date DATE NULL,
        end_date DATE NULL,
        annual_limit DECIMAL(10,0) DEFAULT 0,
        per_claim_limit DECIMAL(10,0) DEFAULT 0,
        daily_limit DECIMAL(10,0) DEFAULT 0,
        annual_usage DECIMAL(10,0) DEFAULT 0,
        claim_count INTEGER DEFAULT 0,
        status VARCHAR(20) DEFAULT 'active',
        notes TEXT DEFAULT '',
        created_at {$dt} DEFAULT CURRENT_TIMESTAMP,
        updated_at {$dt} DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (patient_id) REFERENCES patients(id)
    )",

    // Insurance claims (レセプト)
    "CREATE TABLE IF NOT EXISTS insurance_claims (
        id {$intPK},
        claim_number VARCHAR(30) UNIQUE,
        policy_id INTEGER NOT NULL,
        patient_id INTEGER NOT NULL,
        invoice_id INTEGER NULL,
        record_id INTEGER NULL,
        claim_date DATE NOT NULL,
        treatment_start_date DATE NOT NULL,
        treatment_end_date DATE NOT NULL,
        diagnosis_name VARCHAR(255) NOT NULL,
        diagnosis_code VARCHAR(50) DEFAULT '',
        total_medical_fee DECIMAL(10,0) DEFAULT 0,
        covered_amount DECIMAL(10,0) DEFAULT 0,
        owner_copay DECIMAL(10,0) DEFAULT 0,
        deductible DECIMAL(10,0) DEFAULT 0,
        claim_status VARCHAR(20) DEFAULT 'draft',
        submitted_at {$dt} NULL,
        approved_at {$dt} NULL,
        paid_at {$dt} NULL,
        rejection_reason TEXT DEFAULT '',
        notes TEXT DEFAULT '',
        created_by INTEGER NOT NULL,
        created_at {$dt} DEFAULT CURRENT_TIMESTAMP,
        updated_at {$dt} DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (policy_id) REFERENCES insurance_policies(id),
        FOREIGN KEY (patient_id) REFERENCES patients(id)
    )",

    // Claim detail items
    "CREATE TABLE IF NOT EXISTS insurance_claim_items (
        id {$intPK},
        claim_id INTEGER NOT NULL,
        item_date DATE NOT NULL,
        item_category VARCHAR(50) NOT NULL,
        item_name VARCHAR(255) NOT NULL,
        item_code VARCHAR(30) DEFAULT '',
        quantity DECIMAL(10,2) DEFAULT 1,
        unit VARCHAR(20) DEFAULT '',
        unit_price DECIMAL(10,0) DEFAULT 0,
        amount DECIMAL(10,0) DEFAULT 0,
        is_covered INTEGER DEFAULT 1,
        notes TEXT DEFAULT '',
        FOREIGN KEY (claim_id) REFERENCES insurance_claims(id) ON DELETE CASCADE
    )",

    // Estimates / Quotations (見積もり)
    "CREATE TABLE IF NOT EXISTS estimates (
        id {$intPK},
        estimate_number VARCHAR(30) UNIQUE,
        patient_id INTEGER NOT NULL,
        owner_id INTEGER NOT NULL,
        title VARCHAR(255) NOT NULL,
        subtotal DECIMAL(10,0) DEFAULT 0,
        tax DECIMAL(10,0) DEFAULT 0,
        total DECIMAL(10,0) DEFAULT 0,
        insurance_estimate DECIMAL(10,0) DEFAULT 0,
        owner_estimate DECIMAL(10,0) DEFAULT 0,
        valid_until DATE NULL,
        status VARCHAR(20) DEFAULT 'draft',
        notes TEXT DEFAULT '',
        created_by INTEGER NOT NULL,
        created_at {$dt} DEFAULT CURRENT_TIMESTAMP,
        updated_at {$dt} DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (patient_id) REFERENCES patients(id),
        FOREIGN KEY (owner_id) REFERENCES owners(id)
    )",

    // Estimate items
    "CREATE TABLE IF NOT EXISTS estimate_items (
        id {$intPK},
        estimate_id INTEGER NOT NULL,
        item_name VARCHAR(255) NOT NULL,
        category VARCHAR(50) DEFAULT '',
        quantity DECIMAL(10,2) DEFAULT 1,
        unit VARCHAR(20) DEFAULT '',
        unit_price DECIMAL(10,0) DEFAULT 0,
        amount DECIMAL(10,0) DEFAULT 0,
        is_covered INTEGER DEFAULT 1,
        notes TEXT DEFAULT '',
        FOREIGN KEY (estimate_id) REFERENCES estimates(id) ON DELETE CASCADE
    )",

    // Weight tracking history
    "CREATE TABLE IF NOT EXISTS weight_history (
        id {$intPK},
        patient_id INTEGER NOT NULL,
        weight DECIMAL(6,2) NOT NULL,
        measured_at {$dt} DEFAULT CURRENT_TIMESTAMP,
        measured_by INTEGER NULL,
        notes TEXT DEFAULT '',
        FOREIGN KEY (patient_id) REFERENCES patients(id)
    )",

    // Referral letters
    "CREATE TABLE IF NOT EXISTS referrals (
        id {$intPK},
        patient_id INTEGER NOT NULL,
        record_id INTEGER NULL,
        referral_number VARCHAR(30) UNIQUE,
        referral_type VARCHAR(30) DEFAULT 'outgoing',
        to_hospital VARCHAR(200) DEFAULT '',
        to_doctor VARCHAR(100) DEFAULT '',
        to_address TEXT DEFAULT '',
        to_phone VARCHAR(20) DEFAULT '',
        from_hospital VARCHAR(200) DEFAULT '',
        from_doctor VARCHAR(100) DEFAULT '',
        reason TEXT DEFAULT '',
        clinical_summary TEXT DEFAULT '',
        current_medications TEXT DEFAULT '',
        test_results TEXT DEFAULT '',
        diagnosis TEXT DEFAULT '',
        request_items TEXT DEFAULT '',
        urgency VARCHAR(20) DEFAULT 'normal',
        status VARCHAR(20) DEFAULT 'draft',
        issued_date DATE NULL,
        created_by INTEGER NOT NULL,
        created_at {$dt} DEFAULT CURRENT_TIMESTAMP,
        updated_at {$dt} DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (patient_id) REFERENCES patients(id)
    )",

    // Discharge summary
    "CREATE TABLE IF NOT EXISTS discharge_summaries (
        id {$intPK},
        admission_id INTEGER NOT NULL,
        patient_id INTEGER NOT NULL,
        diagnosis TEXT DEFAULT '',
        treatment_summary TEXT DEFAULT '',
        medications_at_discharge TEXT DEFAULT '',
        follow_up_instructions TEXT DEFAULT '',
        diet_instructions TEXT DEFAULT '',
        activity_restrictions TEXT DEFAULT '',
        next_appointment DATE NULL,
        prognosis TEXT DEFAULT '',
        created_by INTEGER NOT NULL,
        created_at {$dt} DEFAULT CURRENT_TIMESTAMP,
        updated_at {$dt} DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (admission_id) REFERENCES admissions(id),
        FOREIGN KEY (patient_id) REFERENCES patients(id)
    )",

    // Consent forms
    "CREATE TABLE IF NOT EXISTS consent_forms (
        id {$intPK},
        patient_id INTEGER NOT NULL,
        form_type VARCHAR(50) NOT NULL,
        title VARCHAR(255) NOT NULL,
        content TEXT DEFAULT '',
        owner_signature VARCHAR(100) DEFAULT '',
        signed_at {$dt} NULL,
        witness_name VARCHAR(100) DEFAULT '',
        status VARCHAR(20) DEFAULT 'pending',
        created_by INTEGER NOT NULL,
        created_at {$dt} DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (patient_id) REFERENCES patients(id)
    )",

    // Diagnosis code master
    "CREATE TABLE IF NOT EXISTS diagnosis_master (
        id {$intPK},
        diagnosis_code VARCHAR(30) NOT NULL,
        diagnosis_name VARCHAR(255) NOT NULL,
        category VARCHAR(100) DEFAULT '',
        species_applicable TEXT DEFAULT '',
        notes TEXT DEFAULT '',
        is_active INTEGER DEFAULT 1,
        created_at {$dt} DEFAULT CURRENT_TIMESTAMP
    )",

    // Appointment owner fields
    "CREATE TABLE IF NOT EXISTS booking_types (
        id {$intPK},
        type_key VARCHAR(50) UNIQUE NOT NULL,
        type_name VARCHAR(100) NOT NULL,
        duration INTEGER DEFAULT 30,
        color VARCHAR(20) DEFAULT '#4f46e5',
        is_public INTEGER DEFAULT 1,
        sort_order INTEGER DEFAULT 0,
        is_active INTEGER DEFAULT 1,
        created_at {$dt} DEFAULT CURRENT_TIMESTAMP
    )",

    // Owner portal access tokens
    "CREATE TABLE IF NOT EXISTS owner_portal_tokens (
        id {$intPK},
        owner_id INTEGER NOT NULL,
        token VARCHAR(128) UNIQUE NOT NULL,
        expires_at {$dt} NOT NULL,
        created_at {$dt} DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (owner_id) REFERENCES owners(id)
    )"
];

echo "VetCare Pro v3.0 - Insurance & Receipt Migration\n";
echo "=================================================\n";

foreach ($tables as $sql) {
    try {
        $pdo->exec($sql);
        preg_match('/CREATE TABLE IF NOT EXISTS (\w+)/', $sql, $m);
        echo "OK: {$m[1]}\n";
    } catch (PDOException $e) {
        echo "ERROR ({$m[1]}): {$e->getMessage()}\n";
    }
}

// Add columns to existing tables
$alterations = [
    "ALTER TABLE patients ADD COLUMN registration_date DATE NULL",
    "ALTER TABLE patients ADD COLUMN last_visit_date DATE NULL",
    "ALTER TABLE patients ADD COLUMN total_visits INTEGER DEFAULT 0",
    "ALTER TABLE invoices ADD COLUMN estimate_id INTEGER NULL",
    "ALTER TABLE invoices ADD COLUMN claim_id INTEGER NULL",
    "ALTER TABLE invoices ADD COLUMN tax_rate DECIMAL(4,2) DEFAULT 10",
    "ALTER TABLE appointments ADD COLUMN owner_name_text VARCHAR(100) DEFAULT ''",
    "ALTER TABLE appointments ADD COLUMN pet_name_text VARCHAR(100) DEFAULT ''",
    "ALTER TABLE appointments ADD COLUMN phone_text VARCHAR(20) DEFAULT ''",
    "ALTER TABLE appointments ADD COLUMN species_text VARCHAR(30) DEFAULT ''",
    "ALTER TABLE appointments ADD COLUMN booking_source VARCHAR(30) DEFAULT 'staff'",
];

foreach ($alterations as $sql) {
    try { $pdo->exec($sql); echo "ALTER OK\n"; }
    catch (PDOException $e) { /* Column already exists - safe to ignore */ }
}

// Seed insurance master data
$insuranceData = [
    ['INS001', 'アニコム損害保険', 'アニコムソンガイホケン', '161-0033', '東京都新宿区下落合1-5-22', '03-5348-3777', '', 'info@anicom-sompo.co.jp', 'https://www.anicom-sompo.co.jp', '50,70', 'anicom'],
    ['INS002', 'アイペット損害保険', 'アイペットソンガイホケン', '106-0032', '東京都港区六本木1-8-7', '03-6758-0033', '', 'info@ipet-ins.com', 'https://www.ipet-ins.com', '50,70', 'ipet'],
    ['INS003', 'ペット＆ファミリー損保', 'ペットアンドファミリーソンポ', '113-0033', '東京都文京区本郷2-27-20', '03-6365-0700', '', '', 'https://www.petfamilyins.co.jp', '50,70', 'standard'],
    ['INS004', 'SBIいきいき少額短期保険', 'エスビーアイイキイキショウガクタンキホケン', '106-0032', '東京都港区六本木1-6-1', '03-6229-0114', '', '', 'https://www.i-sedai.com', '50,70,100', 'standard'],
    ['INS005', '日本ペット少額短期保険', 'ニホンペットショウガクタンキホケン', '100-0014', '東京都千代田区永田町2-17-17', '03-6837-5750', '', '', 'https://nihonpet.co.jp', '50,70,90', 'standard'],
    ['INS006', 'FPC', 'エフピーシー', '530-0001', '大阪府大阪市北区梅田3-3-45', '06-6346-2600', '', '', 'https://www.fpc-pet.co.jp', '50,70,100', 'standard'],
    ['INS007', 'ペットメディカルサポート', 'ペットメディカルサポート', '106-0032', '東京都港区六本木1-9-10', '03-6634-9300', '', '', 'https://pshoken.co.jp', '50,70,100', 'standard'],
    ['INS008', 'au損害保険', 'エーユーソンガイホケン', '106-0044', '東京都港区東麻布1-28-13', '03-6838-0700', '', '', 'https://www.au-sonpo.co.jp', '50,70', 'standard'],
];

foreach ($insuranceData as $ins) {
    try {
        $existing = $db->fetch("SELECT id FROM insurance_master WHERE company_code = ?", [$ins[0]]);
        if (!$existing) {
            $db->insert('insurance_master', [
                'company_code' => $ins[0],
                'company_name' => $ins[1],
                'company_name_kana' => $ins[2],
                'postal_code' => $ins[3],
                'address' => $ins[4],
                'phone' => $ins[5],
                'fax' => $ins[6],
                'email' => $ins[7],
                'website' => $ins[8],
                'coverage_rates' => $ins[9],
                'claim_format' => $ins[10],
            ]);
            echo "Insurance: {$ins[1]}\n";
        }
    } catch (Exception $e) {}
}

// Seed diagnosis master
$diagData = [
    ['D001', '外耳炎', '耳鼻咽喉科', 'dog,cat'],
    ['D002', '皮膚炎（アレルギー性）', '皮膚科', 'dog,cat'],
    ['D003', '胃腸炎', '消化器科', 'dog,cat'],
    ['D004', '膀胱炎', '泌尿器科', 'dog,cat'],
    ['D005', '歯周病', '歯科', 'dog,cat'],
    ['D006', '骨折', '整形外科', 'dog,cat,rabbit'],
    ['D007', '椎間板ヘルニア', '神経科', 'dog'],
    ['D008', '僧帽弁閉鎖不全症', '循環器科', 'dog'],
    ['D009', '甲状腺機能亢進症', '内分泌科', 'cat'],
    ['D010', '糖尿病', '内分泌科', 'dog,cat'],
    ['D011', '腎不全（慢性）', '腎臓科', 'dog,cat'],
    ['D012', '肝疾患', '消化器科', 'dog,cat'],
    ['D013', 'リンパ腫', '腫瘍科', 'dog,cat'],
    ['D014', '乳腺腫瘍', '腫瘍科', 'dog,cat'],
    ['D015', '肥満細胞腫', '腫瘍科', 'dog,cat'],
    ['D016', '膝蓋骨脱臼', '整形外科', 'dog'],
    ['D017', '白内障', '眼科', 'dog,cat'],
    ['D018', '角膜潰瘍', '眼科', 'dog,cat'],
    ['D019', '子宮蓄膿症', '産科', 'dog,cat'],
    ['D020', '気管虚脱', '呼吸器科', 'dog'],
];

foreach ($diagData as $d) {
    try {
        $existing = $db->fetch("SELECT id FROM diagnosis_master WHERE diagnosis_code = ?", [$d[0]]);
        if (!$existing) {
            $db->insert('diagnosis_master', [
                'diagnosis_code' => $d[0],
                'diagnosis_name' => $d[1],
                'category' => $d[2],
                'species_applicable' => $d[3],
            ]);
        }
    } catch (Exception $e) {}
}

// Seed booking types
$bookingTypes = [
    ['general', '一般診察', 30, '#4f46e5', 1, 1],
    ['follow_up', '再診', 20, '#10b981', 1, 2],
    ['vaccination', 'ワクチン接種', 20, '#06b6d4', 1, 3],
    ['checkup', '健康診断', 60, '#8b5cf6', 1, 4],
    ['surgery', '手術', 120, '#ef4444', 0, 5],
    ['grooming', 'トリミング', 60, '#f59e0b', 1, 6],
    ['dental', '歯科処置', 60, '#ec4899', 0, 7],
    ['emergency', '緊急', 30, '#dc2626', 0, 8],
];

foreach ($bookingTypes as $bt) {
    try {
        $existing = $db->fetch("SELECT id FROM booking_types WHERE type_key = ?", [$bt[0]]);
        if (!$existing) {
            $db->insert('booking_types', [
                'type_key' => $bt[0],
                'type_name' => $bt[1],
                'duration' => $bt[2],
                'color' => $bt[3],
                'is_public' => $bt[4],
                'sort_order' => $bt[5],
            ]);
        }
    } catch (Exception $e) {}
}

echo "\nInsurance & Receipt migration completed.\n";
