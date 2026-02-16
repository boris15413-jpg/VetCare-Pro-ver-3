<?php
/**
 * VetCare Pro v2.0 - CSV Import Engine for Lab Equipment
 * Supports: IDEXX, Fuji DRI-CHEM, SPOTCHEM, Generic CSV
 */

class CsvImporter {
    private $db;
    private $errors = [];
    private $imported = 0;
    
    // Supported equipment profiles
    private $profiles = [
        'idexx' => [
            'name' => 'IDEXX VetLab (Catalyst/ProCyte)',
            'encoding' => 'auto',
            'header_row' => 0,
            'mapping' => [
                'sample_id' => ['Sample ID', 'SampleID', 'ID'],
                'test_name' => ['Test', 'Analyte', 'Test Name'],
                'result' => ['Result', 'Value'],
                'unit' => ['Units', 'Unit'],
                'ref_low' => ['Low', 'Ref Low', 'Low Normal'],
                'ref_high' => ['High', 'Ref High', 'High Normal'],
                'flag' => ['Flag', 'Status', 'Abnormal']
            ]
        ],
        'fuji_drichem' => [
            'name' => 'Fuji DRI-CHEM NX/7000',
            'encoding' => 'SJIS',
            'header_row' => 0,
            'mapping' => [
                'sample_id' => ['サンプルID', 'サンプルNo', 'ID'],
                'test_name' => ['検査項目', '項目名', '項目'],
                'result' => ['結果', '測定値', '値'],
                'unit' => ['単位'],
                'ref_low' => ['基準値下限', '下限'],
                'ref_high' => ['基準値上限', '上限'],
                'flag' => ['判定', 'フラグ']
            ]
        ],
        'spotchem' => [
            'name' => 'SPOTCHEM EZ / D-Concept',
            'encoding' => 'SJIS',
            'header_row' => 0,
            'mapping' => [
                'sample_id' => ['Sample No', 'サンプルNo'],
                'test_name' => ['Item', '項目'],
                'result' => ['Result', '結果'],
                'unit' => ['Unit', '単位'],
                'ref_low' => ['L.Limit', '下限'],
                'ref_high' => ['H.Limit', '上限'],
                'flag' => ['Judge', '判定']
            ]
        ],
        'generic' => [
            'name' => '汎用CSVフォーマット',
            'encoding' => 'auto',
            'header_row' => 0,
            'mapping' => [
                'test_name' => 0,
                'result' => 1,
                'unit' => 2,
                'ref_low' => 3,
                'ref_high' => 4
            ]
        ]
    ];
    
    public function __construct() {
        $this->db = Database::getInstance();
    }
    
    /**
     * Get available import profiles
     */
    public function getProfiles() {
        $result = [];
        foreach ($this->profiles as $key => $profile) {
            $result[$key] = $profile['name'];
        }
        return $result;
    }
    
    /**
     * Preview CSV file
     */
    public function preview($filePath, $profileKey = 'generic') {
        $profile = $this->profiles[$profileKey] ?? $this->profiles['generic'];
        $rows = parseCSV($filePath, $profile['encoding']);
        
        if (!$rows || count($rows) < 2) {
            return ['error' => 'CSVファイルの読み込みに失敗しました、またはデータが不足しています'];
        }
        
        return [
            'headers' => $rows[0],
            'rows' => array_slice($rows, 1, 20), // Preview first 20 rows
            'total_rows' => count($rows) - 1,
            'profile' => $profile['name']
        ];
    }
    
    /**
     * Import CSV data into lab_results
     */
    public function import($filePath, $profileKey, $patientId, $recordId = null, $options = []) {
        $profile = $this->profiles[$profileKey] ?? $this->profiles['generic'];
        
        try {
            $rows = parseCSV($filePath, $profile['encoding']);
        } catch (Exception $e) {
            return ['error' => 'CSVファイルの読み込みに失敗しました: ' . $e->getMessage()];
        }
        
        if (!$rows || !is_array($rows)) {
            return ['error' => 'CSVファイルの読み込みに失敗しました。ファイル形式を確認してください。'];
        }
        
        if (count($rows) < 2) {
            return ['error' => 'CSVファイルにデータが不足しています（ヘッダー行 + 1行以上のデータが必要です）。'];
        }
        
        $headers = $rows[0];
        
        // Validate headers exist
        if (empty(array_filter($headers))) {
            return ['error' => 'CSVファイルのヘッダー行が空です。'];
        }
        
        $mapping = $this->resolveMapping($headers, $profile['mapping']);
        
        // Validate that essential columns were found
        if (!isset($mapping['test_name'])) {
            $availableHeaders = implode(', ', array_filter($headers));
            return ['error' => '検査項目名の列が見つかりません。CSVヘッダーを確認してください。検出されたヘッダー: ' . $availableHeaders];
        }
        if (!isset($mapping['result'])) {
            $availableHeaders = implode(', ', array_filter($headers));
            return ['error' => '結果値の列が見つかりません。CSVヘッダーを確認してください。検出されたヘッダー: ' . $availableHeaders];
        }
        
        $this->errors = [];
        $this->imported = 0;
        $testedBy = $options['tested_by'] ?? null;
        $category = $options['category'] ?? '外部検査';
        $skippedEmpty = 0;
        
        $this->db->beginTransaction();
        
        try {
            for ($i = 1; $i < count($rows); $i++) {
                $row = $rows[$i];
                
                // Skip completely empty rows
                if (empty(array_filter($row, function($v) { return $v !== null && $v !== ''; }))) {
                    $skippedEmpty++;
                    continue;
                }
                
                $testName = $this->getMappedValue($row, $mapping, 'test_name');
                $result = $this->getMappedValue($row, $mapping, 'result');
                
                if (empty($testName)) {
                    $this->errors[] = "行 " . ($i + 1) . ": 検査項目名が空です（スキップ）";
                    continue;
                }
                
                if ($result === null || $result === '') {
                    $this->errors[] = "行 " . ($i + 1) . ": 結果値が空です [" . $testName . "]（スキップ）";
                    continue;
                }
                
                $unit = $this->getMappedValue($row, $mapping, 'unit') ?? '';
                $refLow = $this->getMappedValue($row, $mapping, 'ref_low') ?? '';
                $refHigh = $this->getMappedValue($row, $mapping, 'ref_high') ?? '';
                $flag = $this->getMappedValue($row, $mapping, 'flag') ?? '';
                
                // Clean numeric values (remove spaces, commas)
                $cleanResult = str_replace([' ', ',', '　'], '', $result);
                $cleanRefLow = str_replace([' ', ',', '　'], '', $refLow);
                $cleanRefHigh = str_replace([' ', ',', '　'], '', $refHigh);
                
                // Determine abnormal flag
                $isAbnormal = 0;
                if (!empty($flag) && preg_match('/(H|L|High|Low|異常|高|低|\*)/i', $flag)) {
                    $isAbnormal = 1;
                } elseif (is_numeric($cleanResult) && is_numeric($cleanRefLow) && is_numeric($cleanRefHigh) && $cleanRefLow !== '' && $cleanRefHigh !== '') {
                    if ((float)$cleanResult < (float)$cleanRefLow || (float)$cleanResult > (float)$cleanRefHigh) {
                        $isAbnormal = 1;
                    }
                }
                
                try {
                    $this->db->insert('lab_results', [
                        'patient_id' => $patientId,
                        'record_id' => $recordId,
                        'order_id' => null,
                        'test_category' => $category,
                        'test_name' => trim($testName),
                        'result_value' => trim($result),
                        'unit' => trim($unit),
                        'reference_low' => trim($refLow),
                        'reference_high' => trim($refHigh),
                        'is_abnormal' => $isAbnormal,
                        'tested_by' => $testedBy,
                        'tested_at' => date('Y-m-d H:i:s'),
                        'notes' => $options['notes'] ?? 'CSV取込 (' . ($profile['name']) . ')'
                    ]);
                    $this->imported++;
                } catch (Exception $rowEx) {
                    $this->errors[] = "行 " . ($i + 1) . ": DB挿入エラー [" . $testName . "]: " . $rowEx->getMessage();
                }
            }
            
            $this->db->commit();
        } catch (Exception $e) {
            $this->db->rollBack();
            return ['error' => 'インポート中にエラーが発生しました: ' . $e->getMessage()];
        }
        
        return [
            'success' => true,
            'imported' => $this->imported,
            'errors' => $this->errors,
            'total_rows' => count($rows) - 1,
            'skipped_empty' => $skippedEmpty
        ];
    }
    
    /**
     * Import with custom column mapping
     */
    public function importCustom($filePath, $patientId, $columnMap, $recordId = null, $options = []) {
        $rows = parseCSV($filePath);
        if (!$rows || count($rows) < 2) {
            return ['error' => 'CSVファイルの読み込みに失敗しました'];
        }
        
        $this->errors = [];
        $this->imported = 0;
        
        $this->db->beginTransaction();
        try {
            for ($i = 1; $i < count($rows); $i++) {
                $row = $rows[$i];
                if (empty(array_filter($row))) continue;
                
                $testName = $row[$columnMap['test_name'] ?? 0] ?? '';
                $result = $row[$columnMap['result'] ?? 1] ?? '';
                
                if (empty($testName) || $result === '') continue;
                
                $this->db->insert('lab_results', [
                    'patient_id' => $patientId,
                    'record_id' => $recordId,
                    'order_id' => null,
                    'test_category' => $options['category'] ?? 'CSV取込',
                    'test_name' => $testName,
                    'result_value' => $result,
                    'unit' => $row[$columnMap['unit'] ?? 2] ?? '',
                    'reference_low' => $row[$columnMap['ref_low'] ?? 3] ?? '',
                    'reference_high' => $row[$columnMap['ref_high'] ?? 4] ?? '',
                    'is_abnormal' => 0,
                    'tested_by' => $options['tested_by'] ?? null,
                    'tested_at' => date('Y-m-d H:i:s'),
                    'notes' => $options['notes'] ?? 'CSV手動取込'
                ]);
                $this->imported++;
            }
            $this->db->commit();
        } catch (Exception $e) {
            $this->db->rollBack();
            return ['error' => $e->getMessage()];
        }
        
        return ['success' => true, 'imported' => $this->imported, 'errors' => $this->errors];
    }
    
    /**
     * Resolve column mapping from headers
     */
    private function resolveMapping($headers, $profileMapping) {
        $mapping = [];
        foreach ($profileMapping as $field => $candidates) {
            if (is_int($candidates)) {
                $mapping[$field] = $candidates;
                continue;
            }
            foreach ($candidates as $candidate) {
                $idx = array_search($candidate, $headers);
                if ($idx !== false) {
                    $mapping[$field] = $idx;
                    break;
                }
            }
        }
        return $mapping;
    }
    
    /**
     * Get value from row using mapping
     */
    private function getMappedValue($row, $mapping, $field) {
        if (!isset($mapping[$field])) return null;
        $idx = $mapping[$field];
        return trim($row[$idx] ?? '');
    }
}
