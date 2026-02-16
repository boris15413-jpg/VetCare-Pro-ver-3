<?php
/**
 * VetCare Pro v2.0 - LINE Integration Settings
 */
$auth->requireRole([ROLE_ADMIN]);

$msg = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && verify_csrf()) {
    $settings = [
        'line_channel_access_token' => trim($_POST['line_channel_access_token'] ?? ''),
        'line_channel_secret' => trim($_POST['line_channel_secret'] ?? ''),
        'line_notify_appointment' => isset($_POST['line_notify_appointment']) ? '1' : '0',
        'line_notify_reminder' => isset($_POST['line_notify_reminder']) ? '1' : '0',
        'line_notify_vaccination' => isset($_POST['line_notify_vaccination']) ? '1' : '0',
        'line_reminder_hours' => trim($_POST['line_reminder_hours'] ?? '24'),
    ];
    
    foreach ($settings as $key => $value) {
        setSetting($key, $value);
    }
    
    setFlash('success', 'LINE連携設定を保存しました。');
    redirect('index.php?page=line_settings');
}

// Load current settings
$lineToken = getSetting('line_channel_access_token', '');
$lineSecret = getSetting('line_channel_secret', '');
$lineNotifyAppointment = getSetting('line_notify_appointment', '1');
$lineNotifyReminder = getSetting('line_notify_reminder', '1');
$lineNotifyVaccination = getSetting('line_notify_vaccination', '1');
$lineReminderHours = getSetting('line_reminder_hours', '24');

// Check configuration status
$isConfigured = !empty($lineToken) && !empty($lineSecret);

// Count LINE-linked owners
$linkedOwners = 0;
try {
    $linkedOwners = $db->count('owners', "line_user_id IS NOT NULL AND line_user_id != ''");
} catch (Exception $e) {}
?>

<div class="fade-in">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h4 class="fw-bold mb-1"><i class="bi bi-chat-dots me-2"></i>LINE連携設定</h4>
            <small class="text-muted">LINE Messaging APIを通じた飼い主への自動通知機能</small>
        </div>
        <div>
            <?php if ($isConfigured): ?>
            <span class="badge bg-success px-3 py-2"><i class="bi bi-check-circle me-1"></i>接続済み</span>
            <?php else: ?>
            <span class="badge bg-secondary px-3 py-2"><i class="bi bi-x-circle me-1"></i>未設定</span>
            <?php endif; ?>
        </div>
    </div>

    <div class="row g-4">
        <div class="col-lg-8">
            <form method="POST">
                <?= csrf_field() ?>
                
                <!-- API Settings -->
                <div class="card">
                    <div class="card-header"><i class="bi bi-key me-2"></i>API設定</div>
                    <div class="card-body">
                        <div class="mb-3">
                            <label class="form-label">Channel Access Token</label>
                            <textarea name="line_channel_access_token" class="form-control" rows="3" 
                                      placeholder="LINE Developers Consoleから取得"><?= h($lineToken) ?></textarea>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Channel Secret</label>
                            <input type="text" name="line_channel_secret" class="form-control" 
                                   value="<?= h($lineSecret) ?>" placeholder="LINE Developers Consoleから取得">
                        </div>
                    </div>
                </div>

                <!-- Notification Settings -->
                <div class="card">
                    <div class="card-header"><i class="bi bi-bell me-2"></i>通知設定</div>
                    <div class="card-body">
                        <div class="form-check form-switch mb-3">
                            <input type="checkbox" name="line_notify_appointment" class="form-check-input" id="notifyAppt"
                                   <?= $lineNotifyAppointment === '1' ? 'checked' : '' ?>>
                            <label class="form-check-label" for="notifyAppt">
                                <strong>予約確認通知</strong>
                                <small class="text-muted d-block">予約が確定した際に飼い主へ確認メッセージを送信</small>
                            </label>
                        </div>
                        <div class="form-check form-switch mb-3">
                            <input type="checkbox" name="line_notify_reminder" class="form-check-input" id="notifyRemind"
                                   <?= $lineNotifyReminder === '1' ? 'checked' : '' ?>>
                            <label class="form-check-label" for="notifyRemind">
                                <strong>予約リマインダー</strong>
                                <small class="text-muted d-block">予約前日にリマインダーメッセージを送信</small>
                            </label>
                        </div>
                        <div class="ms-4 mb-3">
                            <label class="form-label">リマインダー送信タイミング</label>
                            <select name="line_reminder_hours" class="form-select" style="width:200px;">
                                <option value="12" <?= $lineReminderHours === '12' ? 'selected' : '' ?>>12時間前</option>
                                <option value="24" <?= $lineReminderHours === '24' ? 'selected' : '' ?>>24時間前(前日)</option>
                                <option value="48" <?= $lineReminderHours === '48' ? 'selected' : '' ?>>48時間前</option>
                            </select>
                        </div>
                        <div class="form-check form-switch mb-3">
                            <input type="checkbox" name="line_notify_vaccination" class="form-check-input" id="notifyVax"
                                   <?= $lineNotifyVaccination === '1' ? 'checked' : '' ?>>
                            <label class="form-check-label" for="notifyVax">
                                <strong>ワクチン接種リマインダー</strong>
                                <small class="text-muted d-block">次回ワクチン接種時期が近づいた際に通知</small>
                            </label>
                        </div>
                    </div>
                </div>

                <button type="submit" class="btn btn-primary"><i class="bi bi-save me-2"></i>設定を保存</button>
            </form>
        </div>

        <!-- Sidebar Info -->
        <div class="col-lg-4">
            <div class="card">
                <div class="card-header"><i class="bi bi-graph-up me-2"></i>連携状況</div>
                <div class="card-body text-center">
                    <div class="p-3 bg-light rounded mb-3">
                        <div class="fw-bold fs-3 text-primary"><?= $linkedOwners ?></div>
                        <small class="text-muted">LINE連携済み飼い主</small>
                    </div>
                    <small class="text-muted">
                        飼い主のLINE IDは「飼い主管理」画面で個別に設定できます。
                    </small>
                </div>
            </div>

            <div class="card">
                <div class="card-header"><i class="bi bi-book me-2"></i>設定方法</div>
                <div class="card-body">
                    <ol class="text-muted small ps-3">
                        <li class="mb-2"><a href="https://developers.line.biz/" target="_blank">LINE Developers Console</a>にログイン</li>
                        <li class="mb-2">新規チャネル > Messaging API を作成</li>
                        <li class="mb-2">Channel Access Token を発行</li>
                        <li class="mb-2">Channel Secret をコピー</li>
                        <li class="mb-2">左の設定画面に入力して保存</li>
                        <li class="mb-2">Webhook URL を以下に設定:<br>
                            <code class="small"><?= h(APP_URL) ?>/webhook/line.php</code>
                        </li>
                    </ol>
                </div>
            </div>

            <div class="card">
                <div class="card-header"><i class="bi bi-chat-left-text me-2"></i>送信メッセージ例</div>
                <div class="card-body p-3">
                    <div class="p-3 rounded" style="background:#E8F5E9; border-left: 4px solid #4CAF50; font-size:0.8rem; line-height:1.6;">
                        【予約確定のお知らせ】<br><br>
                        ○○動物病院より、ご予約を承りました。<br><br>
                        ■ 予約日時: 2026年2月20日 10:00<br>
                        ■ 患者名: ポチ<br>
                        ■ 内容: 定期検診<br><br>
                        変更・キャンセルの場合はお電話ください。
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
