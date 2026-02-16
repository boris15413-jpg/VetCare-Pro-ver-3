<?php
/**
 * VetCare Pro - データベースマイグレーション (統合版)
 * 全てのテーブルを初期作成します
 */

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/Database.php';

$db = Database::getInstance();
$pdo = $db->getPDO();

$isMySQL = DB_DRIVER === 'mysql';
$dt = $isMySQL ? 'DATETIME' : 'TEXT';
$intPK = $isMySQL ? 'INT AUTO_INCREMENT PRIMARY KEY' : 'INTEGER PRIMARY KEY AUTOINCREMENT';

$tables = [
    // 職種マスタ
    "CREATE TABLE IF NOT EXISTS staff_roles (
        id {$intPK},
        role_key VARCHAR(50) UNIQUE NOT NULL,
        role_name VARCHAR(50) NOT NULL,
        permissions TEXT DEFAULT '',
        is_system INTEGER DEFAULT 0,
        created_at {$dt} DEFAULT CURRENT_TIMESTAMP
    )",

    // スタッフ (v3追加: stamp_image)
    "CREATE TABLE IF NOT EXISTS staff (
        id {$intPK},
        login_id VARCHAR(50) UNIQUE NOT NULL,
        password_hash VARCHAR(255) NOT NULL,
        name VARCHAR(100) NOT NULL,
        name_kana VARCHAR(100) DEFAULT '',
        role VARCHAR(20) NOT NULL DEFAULT 'nurse',
        license_number VARCHAR(50) DEFAULT '',
        email VARCHAR(255) DEFAULT '',
        phone VARCHAR(20) DEFAULT '',
        stamp_image VARCHAR(255) DEFAULT '',
        is_active INTEGER DEFAULT 1,
        last_login {$dt} NULL,
        created_at {$dt} DEFAULT CURRENT_TIMESTAMP,
        updated_at {$dt} DEFAULT CURRENT_TIMESTAMP
    )",
    
    // 飼い主
    "CREATE TABLE IF NOT EXISTS owners (
        id {$intPK},
        owner_code VARCHAR(20) UNIQUE,
        name VARCHAR(100) NOT NULL,
        name_kana VARCHAR(100) DEFAULT '',
        postal_code VARCHAR(10) DEFAULT '',
        address TEXT DEFAULT '',
        phone VARCHAR(20) DEFAULT '',
        phone2 VARCHAR(20) DEFAULT '',
        email VARCHAR(255) DEFAULT '',
        emergency_contact VARCHAR(100) DEFAULT '',
        emergency_phone VARCHAR(20) DEFAULT '',
        notes TEXT DEFAULT '',
        is_active INTEGER DEFAULT 1,
        created_at {$dt} DEFAULT CURRENT_TIMESTAMP,
        updated_at {$dt} DEFAULT CURRENT_TIMESTAMP
    )",
    
    // 患畜
    "CREATE TABLE IF NOT EXISTS patients (
        id {$intPK},
        patient_code VARCHAR(20) UNIQUE,
        owner_id INTEGER NOT NULL,
        name VARCHAR(100) NOT NULL,
        species VARCHAR(30) NOT NULL,
        breed VARCHAR(100) DEFAULT '',
        color VARCHAR(50) DEFAULT '',
        sex VARCHAR(20) DEFAULT 'unknown',
        birthdate DATE NULL,
        weight DECIMAL(6,2) NULL,
        microchip_id VARCHAR(50) DEFAULT '',
        blood_type VARCHAR(20) DEFAULT '',
        allergies TEXT DEFAULT '',
        chronic_conditions TEXT DEFAULT '',
        insurance_company VARCHAR(100) DEFAULT '',
        insurance_number VARCHAR(50) DEFAULT '',
        insurance_rate INTEGER DEFAULT 0,
        photo_path VARCHAR(255) DEFAULT '',
        notes TEXT DEFAULT '',
        is_active INTEGER DEFAULT 1,
        is_deceased INTEGER DEFAULT 0,
        deceased_date DATE NULL,
        created_at {$dt} DEFAULT CURRENT_TIMESTAMP,
        updated_at {$dt} DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (owner_id) REFERENCES owners(id)
    )",
    
    // 診察記録
    "CREATE TABLE IF NOT EXISTS medical_records (
        id {$intPK},
        patient_id INTEGER NOT NULL,
        staff_id INTEGER NOT NULL,
        visit_date DATE NOT NULL,
        visit_type VARCHAR(30) DEFAULT 'outpatient',
        chief_complaint TEXT DEFAULT '',
        subjective TEXT DEFAULT '',
        objective TEXT DEFAULT '',
        assessment TEXT DEFAULT '',
        plan TEXT DEFAULT '',
        diagnosis_code VARCHAR(50) DEFAULT '',
        diagnosis_name VARCHAR(255) DEFAULT '',
        body_weight DECIMAL(6,2) NULL,
        body_temperature DECIMAL(4,1) NULL,
        heart_rate INTEGER NULL,
        respiratory_rate INTEGER NULL,
        blood_pressure_sys INTEGER NULL,
        blood_pressure_dia INTEGER NULL,
        bcs INTEGER NULL,
        notes TEXT DEFAULT '',
        is_draft INTEGER DEFAULT 0,
        created_at {$dt} DEFAULT CURRENT_TIMESTAMP,
        updated_at {$dt} DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (patient_id) REFERENCES patients(id),
        FOREIGN KEY (staff_id) REFERENCES staff(id)
    )",
    
    // カルテ画像
    "CREATE TABLE IF NOT EXISTS record_images (
        id {$intPK},
        record_id INTEGER NOT NULL,
        file_path VARCHAR(255) NOT NULL,
        caption VARCHAR(255) DEFAULT '',
        image_type VARCHAR(30) DEFAULT 'photo',
        created_at {$dt} DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (record_id) REFERENCES medical_records(id) ON DELETE CASCADE
    )",
    
    // 入院管理
    "CREATE TABLE IF NOT EXISTS admissions (
        id {$intPK},
        patient_id INTEGER NOT NULL,
        admitted_by INTEGER NOT NULL,
        admission_date {$dt} NOT NULL,
        discharge_date {$dt} NULL,
        status VARCHAR(20) DEFAULT 'admitted',
        ward VARCHAR(50) DEFAULT '',
        cage_number VARCHAR(20) DEFAULT '',
        reason TEXT DEFAULT '',
        diet_instructions TEXT DEFAULT '',
        exercise_instructions TEXT DEFAULT '',
        special_notes TEXT DEFAULT '',
        estimated_discharge DATE NULL,
        discharge_summary TEXT DEFAULT '',
        discharged_by INTEGER NULL,
        created_at {$dt} DEFAULT CURRENT_TIMESTAMP,
        updated_at {$dt} DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (patient_id) REFERENCES patients(id),
        FOREIGN KEY (admitted_by) REFERENCES staff(id)
    )",
    
    // 温度板
    "CREATE TABLE IF NOT EXISTS temperature_chart (
        id {$intPK},
        admission_id INTEGER NOT NULL,
        patient_id INTEGER NOT NULL,
        recorded_at {$dt} NOT NULL,
        recorded_by INTEGER NOT NULL,
        body_temperature DECIMAL(4,1) NULL,
        heart_rate INTEGER NULL,
        respiratory_rate INTEGER NULL,
        blood_pressure_sys INTEGER NULL,
        blood_pressure_dia INTEGER NULL,
        spo2 INTEGER NULL,
        body_weight DECIMAL(6,2) NULL,
        food_intake VARCHAR(30) DEFAULT '',
        food_amount VARCHAR(50) DEFAULT '',
        water_intake VARCHAR(30) DEFAULT '',
        urine VARCHAR(30) DEFAULT '',
        urine_amount VARCHAR(50) DEFAULT '',
        feces VARCHAR(30) DEFAULT '',
        feces_consistency VARCHAR(30) DEFAULT '',
        vomiting INTEGER DEFAULT 0,
        vomiting_detail VARCHAR(100) DEFAULT '',
        mental_status VARCHAR(30) DEFAULT '',
        pain_level INTEGER NULL,
        mucous_membrane VARCHAR(30) DEFAULT '',
        crt DECIMAL(3,1) NULL,
        iv_fluid_type VARCHAR(100) DEFAULT '',
        iv_fluid_rate VARCHAR(50) DEFAULT '',
        iv_fluid_amount VARCHAR(50) DEFAULT '',
        medications_given TEXT DEFAULT '',
        treatments TEXT DEFAULT '',
        nursing_notes TEXT DEFAULT '',
        created_at {$dt} DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (admission_id) REFERENCES admissions(id),
        FOREIGN KEY (patient_id) REFERENCES patients(id),
        FOREIGN KEY (recorded_by) REFERENCES staff(id)
    )",
    
    // オーダー
    "CREATE TABLE IF NOT EXISTS orders (
        id {$intPK},
        patient_id INTEGER NOT NULL,
        record_id INTEGER NULL,
        admission_id INTEGER NULL,
        order_type VARCHAR(30) NOT NULL,
        order_category VARCHAR(50) DEFAULT '',
        order_name VARCHAR(255) NOT NULL,
        order_detail TEXT DEFAULT '',
        quantity DECIMAL(10,2) DEFAULT 1,
        unit VARCHAR(20) DEFAULT '',
        unit_price DECIMAL(10,0) DEFAULT 0,
        total_price DECIMAL(10,0) DEFAULT 0,
        frequency VARCHAR(50) DEFAULT '',
        duration VARCHAR(50) DEFAULT '',
        route VARCHAR(30) DEFAULT '',
        priority VARCHAR(20) DEFAULT 'normal',
        status VARCHAR(20) DEFAULT 'pending',
        ordered_by INTEGER NOT NULL,
        executed_by INTEGER NULL,
        ordered_at {$dt} DEFAULT CURRENT_TIMESTAMP,
        scheduled_at {$dt} NULL,
        executed_at {$dt} NULL,
        notes TEXT DEFAULT '',
        FOREIGN KEY (patient_id) REFERENCES patients(id),
        FOREIGN KEY (ordered_by) REFERENCES staff(id)
    )",
    
    // 処方
    "CREATE TABLE IF NOT EXISTS prescriptions (
        id {$intPK},
        patient_id INTEGER NOT NULL,
        record_id INTEGER NULL,
        prescribed_by INTEGER NOT NULL,
        drug_name VARCHAR(255) NOT NULL,
        dosage VARCHAR(100) DEFAULT '',
        frequency VARCHAR(100) DEFAULT '',
        duration VARCHAR(50) DEFAULT '',
        route VARCHAR(30) DEFAULT 'oral',
        quantity DECIMAL(10,2) DEFAULT 0,
        unit VARCHAR(20) DEFAULT '',
        instructions TEXT DEFAULT '',
        start_date DATE NULL,
        end_date DATE NULL,
        is_active INTEGER DEFAULT 1,
        created_at {$dt} DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (patient_id) REFERENCES patients(id),
        FOREIGN KEY (prescribed_by) REFERENCES staff(id)
    )",
    
    // 病理検査
    "CREATE TABLE IF NOT EXISTS pathology (
        id {$intPK},
        patient_id INTEGER NOT NULL,
        record_id INTEGER NULL,
        pathology_number VARCHAR(30) UNIQUE,
        specimen_type VARCHAR(100) NOT NULL,
        collection_site VARCHAR(100) DEFAULT '',
        collection_date DATE NOT NULL,
        collected_by INTEGER NULL,
        fixation_method VARCHAR(50) DEFAULT '',
        gross_description TEXT DEFAULT '',
        microscopic_description TEXT DEFAULT '',
        diagnosis TEXT DEFAULT '',
        pathologist VARCHAR(100) DEFAULT '',
        status VARCHAR(20) DEFAULT 'pending',
        report_date DATE NULL,
        notes TEXT DEFAULT '',
        created_at {$dt} DEFAULT CURRENT_TIMESTAMP,
        updated_at {$dt} DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (patient_id) REFERENCES patients(id)
    )",
    
    // 病理画像
    "CREATE TABLE IF NOT EXISTS pathology_images (
        id {$intPK},
        pathology_id INTEGER NOT NULL,
        file_path VARCHAR(255) NOT NULL,
        caption VARCHAR(255) DEFAULT '',
        magnification VARCHAR(20) DEFAULT '',
        staining VARCHAR(50) DEFAULT '',
        created_at {$dt} DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (pathology_id) REFERENCES pathology(id) ON DELETE CASCADE
    )",
    
    // 検査結果
    "CREATE TABLE IF NOT EXISTS lab_results (
        id {$intPK},
        patient_id INTEGER NOT NULL,
        record_id INTEGER NULL,
        order_id INTEGER NULL,
        test_category VARCHAR(50) NOT NULL,
        test_name VARCHAR(100) NOT NULL,
        result_value VARCHAR(100) DEFAULT '',
        unit VARCHAR(30) DEFAULT '',
        reference_low VARCHAR(30) DEFAULT '',
        reference_high VARCHAR(30) DEFAULT '',
        is_abnormal INTEGER DEFAULT 0,
        tested_at {$dt} DEFAULT CURRENT_TIMESTAMP,
        tested_by INTEGER NULL,
        notes TEXT DEFAULT '',
        FOREIGN KEY (patient_id) REFERENCES patients(id)
    )",
    
    // 看護記録
    "CREATE TABLE IF NOT EXISTS nursing_records (
        id {$intPK},
        patient_id INTEGER NOT NULL,
        admission_id INTEGER NULL,
        nurse_id INTEGER NOT NULL,
        record_type VARCHAR(30) DEFAULT 'observation',
        content TEXT NOT NULL,
        priority VARCHAR(20) DEFAULT 'normal',
        created_at {$dt} DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (patient_id) REFERENCES patients(id),
        FOREIGN KEY (nurse_id) REFERENCES staff(id)
    )",
    
    // 看護タスク
    "CREATE TABLE IF NOT EXISTS nursing_tasks (
        id {$intPK},
        patient_id INTEGER NOT NULL,
        admission_id INTEGER NULL,
        task_name VARCHAR(255) NOT NULL,
        task_detail TEXT DEFAULT '',
        scheduled_at {$dt} NOT NULL,
        completed_at {$dt} NULL,
        assigned_to INTEGER NULL,
        completed_by INTEGER NULL,
        status VARCHAR(20) DEFAULT 'pending',
        priority VARCHAR(20) DEFAULT 'normal',
        recurrence VARCHAR(30) DEFAULT 'none',
        created_by INTEGER NOT NULL,
        created_at {$dt} DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (patient_id) REFERENCES patients(id)
    )",
    
    // 会計
    "CREATE TABLE IF NOT EXISTS invoices (
        id {$intPK},
        invoice_number VARCHAR(30) UNIQUE,
        patient_id INTEGER NOT NULL,
        owner_id INTEGER NOT NULL,
        record_id INTEGER NULL,
        subtotal DECIMAL(10,0) DEFAULT 0,
        tax DECIMAL(10,0) DEFAULT 0,
        discount DECIMAL(10,0) DEFAULT 0,
        insurance_covered DECIMAL(10,0) DEFAULT 0,
        total DECIMAL(10,0) DEFAULT 0,
        payment_method VARCHAR(30) DEFAULT 'cash',
        payment_status VARCHAR(20) DEFAULT 'unpaid',
        paid_at {$dt} NULL,
        notes TEXT DEFAULT '',
        created_by INTEGER NOT NULL,
        created_at {$dt} DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (patient_id) REFERENCES patients(id),
        FOREIGN KEY (owner_id) REFERENCES owners(id)
    )",
    
    // 会計明細
    "CREATE TABLE IF NOT EXISTS invoice_items (
        id {$intPK},
        invoice_id INTEGER NOT NULL,
        item_name VARCHAR(255) NOT NULL,
        category VARCHAR(50) DEFAULT '',
        quantity DECIMAL(10,2) DEFAULT 1,
        unit VARCHAR(20) DEFAULT '',
        unit_price DECIMAL(10,0) DEFAULT 0,
        amount DECIMAL(10,0) DEFAULT 0,
        tax_rate DECIMAL(4,2) DEFAULT 10,
        notes TEXT DEFAULT '',
        FOREIGN KEY (invoice_id) REFERENCES invoices(id) ON DELETE CASCADE
    )",
    
    // 発行書類
    "CREATE TABLE IF NOT EXISTS issued_documents (
        id {$intPK},
        document_type VARCHAR(30) NOT NULL,
        document_number VARCHAR(30) UNIQUE,
        patient_id INTEGER NOT NULL,
        record_id INTEGER NULL,
        issued_by INTEGER NOT NULL,
        content TEXT NOT NULL,
        issued_date DATE NOT NULL,
        created_at {$dt} DEFAULT CURRENT_TIMESTAMP,
        updated_at {$dt} DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (patient_id) REFERENCES patients(id),
        FOREIGN KEY (issued_by) REFERENCES staff(id)
    )",
    
    // 予約 (v2追加: updated_at)
    "CREATE TABLE IF NOT EXISTS appointments (
        id {$intPK},
        patient_id INTEGER NULL,
        owner_id INTEGER NULL,
        staff_id INTEGER NULL,
        appointment_date DATE NOT NULL,
        appointment_time TIME NOT NULL,
        duration INTEGER DEFAULT 30,
        appointment_type VARCHAR(50) DEFAULT 'general',
        status VARCHAR(20) DEFAULT 'scheduled',
        reason TEXT DEFAULT '',
        notes TEXT DEFAULT '',
        created_at {$dt} DEFAULT CURRENT_TIMESTAMP,
        updated_at {$dt} DEFAULT NULL,
        FOREIGN KEY (patient_id) REFERENCES patients(id)
    )",
    
    // 薬品マスタ
    "CREATE TABLE IF NOT EXISTS drug_master (
        id {$intPK},
        drug_code VARCHAR(30) DEFAULT '',
        drug_name VARCHAR(255) NOT NULL,
        generic_name VARCHAR(255) DEFAULT '',
        category VARCHAR(50) DEFAULT '',
        unit VARCHAR(20) DEFAULT '',
        unit_price DECIMAL(10,0) DEFAULT 0,
        stock_quantity DECIMAL(10,2) DEFAULT 0,
        min_stock INTEGER DEFAULT 0,
        manufacturer VARCHAR(100) DEFAULT '',
        notes TEXT DEFAULT '',
        is_active INTEGER DEFAULT 1,
        created_at {$dt} DEFAULT CURRENT_TIMESTAMP
    )",
    
    // 検査マスタ
    "CREATE TABLE IF NOT EXISTS test_master (
        id {$intPK},
        test_code VARCHAR(30) DEFAULT '',
        test_name VARCHAR(255) NOT NULL,
        category VARCHAR(50) DEFAULT '',
        unit VARCHAR(30) DEFAULT '',
        reference_low VARCHAR(30) DEFAULT '',
        reference_high VARCHAR(30) DEFAULT '',
        unit_price DECIMAL(10,0) DEFAULT 0,
        notes TEXT DEFAULT '',
        is_active INTEGER DEFAULT 1,
        created_at {$dt} DEFAULT CURRENT_TIMESTAMP
    )",
    
    // 処置マスタ
    "CREATE TABLE IF NOT EXISTS procedure_master (
        id {$intPK},
        procedure_code VARCHAR(30) DEFAULT '',
        procedure_name VARCHAR(255) NOT NULL,
        category VARCHAR(50) DEFAULT '',
        unit_price DECIMAL(10,0) DEFAULT 0,
        default_quantity DECIMAL(10,2) DEFAULT 1,
        unit VARCHAR(20) DEFAULT '',
        notes TEXT DEFAULT '',
        is_active INTEGER DEFAULT 1,
        created_at {$dt} DEFAULT CURRENT_TIMESTAMP
    )",
    
    // 活動ログ
    "CREATE TABLE IF NOT EXISTS activity_log (
        id {$intPK},
        user_id INTEGER,
        action VARCHAR(50) NOT NULL,
        details TEXT DEFAULT '',
        target_type VARCHAR(50) DEFAULT '',
        target_id INTEGER NULL,
        ip_address VARCHAR(45) DEFAULT '',
        created_at {$dt} DEFAULT CURRENT_TIMESTAMP
    )",

    // 監査ログ (改ざん検知用)
    "CREATE TABLE IF NOT EXISTS audit_logs (
        id {$intPK},
        user_id INTEGER NOT NULL,
        target_table VARCHAR(50) NOT NULL,
        target_id INTEGER NOT NULL,
        action_type VARCHAR(20) NOT NULL,
        old_value TEXT,
        new_value TEXT,
        ip_address VARCHAR(45),
        user_agent TEXT,
        previous_hash VARCHAR(64) NOT NULL,
        record_hash VARCHAR(64) NOT NULL,
        created_at {$dt} DEFAULT CURRENT_TIMESTAMP
    )",
    
    // ワクチン接種記録
    "CREATE TABLE IF NOT EXISTS vaccinations (
        id {$intPK},
        patient_id INTEGER NOT NULL,
        vaccine_name VARCHAR(255) NOT NULL,
        vaccine_type VARCHAR(50) DEFAULT '',
        lot_number VARCHAR(50) DEFAULT '',
        manufacturer VARCHAR(100) DEFAULT '',
        administered_date DATE NOT NULL,
        next_due_date DATE NULL,
        administered_by INTEGER NULL,
        site VARCHAR(50) DEFAULT '',
        reaction TEXT DEFAULT '',
        certificate_number VARCHAR(50) DEFAULT '',
        notes TEXT DEFAULT '',
        created_at {$dt} DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (patient_id) REFERENCES patients(id)
    )",
    
    // アレルギー記録
    "CREATE TABLE IF NOT EXISTS patient_allergies (
        id {$intPK},
        patient_id INTEGER NOT NULL,
        allergen VARCHAR(255) NOT NULL,
        reaction_type VARCHAR(100) DEFAULT '',
        severity VARCHAR(20) DEFAULT 'mild',
        notes TEXT DEFAULT '',
        reported_date DATE NULL,
        created_at {$dt} DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (patient_id) REFERENCES patients(id)
    )",

    // お知らせ・掲示板
    "CREATE TABLE IF NOT EXISTS notices (
        id {$intPK},
        title VARCHAR(255) NOT NULL,
        content TEXT NOT NULL,
        priority VARCHAR(20) DEFAULT 'normal',
        target_role VARCHAR(20) DEFAULT '',
        posted_by INTEGER NOT NULL,
        is_active INTEGER DEFAULT 1,
        expires_at {$dt} NULL,
        created_at {$dt} DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (posted_by) REFERENCES staff(id)
    )",

    // お知らせ既読管理
    "CREATE TABLE IF NOT EXISTS notice_reads (
        id {$intPK},
        notice_id INTEGER NOT NULL,
        user_id INTEGER NOT NULL,
        read_at {$dt} DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (notice_id) REFERENCES notices(id),
        FOREIGN KEY (user_id) REFERENCES staff(id)
    )",

    // 施設設定テーブル
    "CREATE TABLE IF NOT EXISTS hospital_settings (
        setting_key VARCHAR(50) PRIMARY KEY,
        setting_value TEXT DEFAULT '',
        updated_at {$dt} DEFAULT CURRENT_TIMESTAMP
    )"
];

echo "データベースマイグレーション開始...\n";

foreach ($tables as $sql) {
    try {
        $pdo->exec($sql);
        preg_match('/CREATE TABLE IF NOT EXISTS (\w+)/', $sql, $m);
        echo "✓ テーブル作成: {$m[1]}\n";
    } catch (PDOException $e) {
        echo "✗ エラー: {$e->getMessage()}\n";
    }
}

echo "\nマイグレーション完了\n";