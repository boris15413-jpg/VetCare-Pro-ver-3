<?php
/** API: Get insurance policies for a patient */
$patientId = (int)($_GET['patient_id'] ?? 0);
if ($patientId <= 0) jsonResponse(['error' => 'Invalid patient_id'], 400);

$policies = $db->fetchAll("SELECT * FROM insurance_policies WHERE patient_id = ? AND status = 'active' ORDER BY company_name", [$patientId]);
jsonResponse(['policies' => $policies]);
