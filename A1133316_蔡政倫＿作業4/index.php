<?php
/**
 * 現代化郵件群發系統 (極速穩定修正版)
 * 支援功能：隨機/全部名單 + 自訂每人發送封數 + 異步佇列控制
 */

require_once 'db.php';
require_once 'src/Exception.php';
require_once 'src/PHPMailer.php';
require_once 'src/SMTP.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// ==========================================
// 後端 API 路由處理
// ==========================================

// API 1: 取得發送名單清單
if (isset($_GET['action']) && $_GET['action'] === 'fetch_tasks') {
    header('Content-Type: application/json; charset=utf-8');
    
    $send_type = $_GET['send_type'] ?? 'all';
    $random_count = isset($_GET['random_count']) ? intval($_GET['random_count']) : 0;

    try {
        if ($send_type === 'random' && $random_count > 0) {
            $pdo->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);
            $stmt = $pdo->prepare("SELECT id, email FROM subscribers ORDER BY RAND() LIMIT :limit");
            $stmt->bindValue(':limit', $random_count, PDO::PARAM_INT);
            $stmt->execute();
        } else {
            $stmt = $pdo->query("SELECT id, email FROM subscribers");
        }
        $list = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode(['success' => true, 'data' => $list]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
    exit;
}

// API 2: 單筆郵件發送
if (isset($_GET['action']) && $_GET['action'] === 'send_single') {
    header('Content-Type: application/json; charset=utf-8');
    
    // 💡 修正：讀取前端傳來的 JSON 封包
    $raw_input = file_get_contents('php://input');
    $input = json_decode($raw_input, true);
    
    $to_email = filter_var($input['email'] ?? '', FILTER_VALIDATE_EMAIL);
    $subject  = $input['subject'] ?? '';
    $content  = $input['content'] ?? '';

    if (!$to_email || empty($subject) || empty($content)) {
        echo json_encode(['success' => false, 'message' => '無效的請求參數或 Email 格式錯誤']);
        exit;
    }

    $mail = new PHPMailer(true);
    try {
        // 配置 SMTP 伺服器
        $mail->isSMTP();                                            
        $mail->Host       = 'smtp.gmail.com';                        
        $mail->SMTPAuth   = true;                                   
        $mail->Username   = 'a1133316@mail.nuk.edu.tw'; 
        $mail->Password   = 'lfhk uelw soil yave';   // 記得更換為你新申請的密碼
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;         
        $mail->Port       = 587;                                    
        $mail->CharSet    = 'UTF-8';
        $mail->Encoding   = 'base64';

        // 信件標頭設定
        $mail->setFrom($mail->Username, '垃圾郵件系統');   
        $mail->addAddress($to_email);
        $mail->isHTML(true);                                        
        $mail->Subject = $subject; 
        $mail->Body    = nl2br(htmlspecialchars($content));         

        $mail->send();
        echo json_encode(['success' => true]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $mail->ErrorInfo]);
    }
    exit;
}

// API 3: 新增訂閱者名單
$message = '';
if (isset($_POST['add_email'])) {
    $email = filter_var($_POST['email'], FILTER_VALIDATE_EMAIL);
    if ($email) {
        try {
            $stmt = $pdo->prepare("INSERT INTO subscribers (email) VALUES (:email)");
            $stmt->execute(['email' => $email]);
            $message = "<div class='alert success'>✨ Email 成功寫入資料庫！</div>";
        } catch (PDOException $e) {
            $message = "<div class='alert error'>❌ 新增失敗：此 Email 可能重複了。</div>";
        }
    } else {
        $message = "<div class='alert error'>⚠️ 請輸入正確的 Email 格式！</div>";
    }
}
?>

<!DOCTYPE html>
<html lang="zh-TW">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>排程郵件群發控制台</title>
    <style>
        :root {
            --primary: #4F46E5;
            --primary-hover: #4338CA;
            --success: #10B981;
            --error: #EF4444;
            --bg: #F3F4F6;
            --card: #FFFFFF;
            --text: #1F2937;
        }
        body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; background: var(--bg); color: var(--text); max-width: 700px; margin: 40px auto; padding: 0 20px; }
        .card { background: var(--card); padding: 30px; margin-bottom: 25px; border-radius: 12px; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1), 0 2px 4px -1px rgba(0,0,0,0.06); }
        h1 { text-align: center; color: var(--text); font-weight: 800; margin-bottom: 30px; }
        h2 { margin-top: 0; font-size: 1.25rem; border-left: 4px solid var(--primary); padding-left: 10px; margin-bottom: 20px; }
        .form-group { margin-bottom: 18px; }
        label { display: block; margin-bottom: 6px; font-weight: 600; font-size: 14px; color: #4B5563; }
        input, textarea, select { width: 100%; padding: 12px; box-sizing: border-box; border: 1px solid #D1D5DB; border-radius: 8px; font-size: 14px; transition: border 0.2s; }
        input:focus, textarea:focus, select:focus { outline: none; border-color: var(--primary); box-shadow: 0 0 0 3px rgba(79, 70, 229, 0.1); }
        button { background: var(--primary); color: white; border: none; padding: 12px 24px; border-radius: 8px; cursor: pointer; font-size: 15px; font-weight: 600; width: 100%; transition: background 0.2s; }
        button:hover { background: var(--primary-hover); }
        .alert { padding: 12px 16px; margin-bottom: 20px; border-radius: 8px; font-weight: 500; font-size: 14px; }
        .success { background: #DEF7EC; color: #03543F; }
        .error { background: #FDE8E8; color: #9B1C1C; }
        
        #panel-running { display: none; }
        .progress-container { width: 100%; background: #E5E7EB; border-radius: 9999px; height: 20px; overflow: hidden; margin: 20px 0; position: relative; }
        .progress-bar { width: 0%; height: 100%; background: var(--primary); transition: width 0.1s ease; }
        .progress-text { position: absolute; width: 100%; text-align: center; top: 0; left: 0; line-height: 20px; font-size: 12px; font-weight: 700; color: #111827; }
        .log-box { background: #1E293B; color: #F8FAFC; padding: 15px; height: 250px; overflow-y: auto; border-radius: 8px; font-family: monospace; font-size: 13px; line-height: 1.6; }
        .log-item { margin: 4px 0; border-bottom: 1px solid #334155; padding-bottom: 4px; }
    </style>
</head>
<body>

    <h1>✉️ 任務級郵件群發主控台</h1>
    <?php echo $message; ?>

    <div class="card" id="panel-setup-list">
        <h2>A. 資料庫名單快速新增</h2>
        <form method="post">
            <div class="form-group">
                <label for="email">電子郵件位址：</label>
                <input type="email" name="email" id="email" required placeholder="例如: target@example.com">
            </div>
            <button type="submit" name="add_email" style="background: #059669;">📥 寫入資料庫名單</button>
        </form>
    </div>

    <div class="card" id="panel-setup-send">
        <h2>B. 群發參數配置</h2>
        <form id="mailForm" onsubmit="startMassMail(event)">
            <div class="form-group">
                <label for="subject">郵件主旨：</label>
                <input type="text" id="subject" required value="【期末通知】重要事項提醒！">
            </div>
            
            <div class="form-group">
                <label for="content">郵件內容：</label>
                <textarea id="content" rows="4" required>同學你好，這是一封測試用的郵件內容...</textarea>
            </div>

            <div class="form-group">
                <label for="send_type">篩選名單模式：</label>
                <select id="send_type" onchange="toggleRandomInput()">
                    <option value="all">發送給全部名單</option>
                    <option value="random">隨機抽樣幾個人</option>
                </select>
            </div>

            <div class="form-group" id="random_count_group" style="display: none;">
                <label for="random_count">隨機抽樣人數：</label>
                <input type="number" id="random_count" min="1" value="3">
            </div>

            <div class="form-group">
                <label for="mail_count_per_user" style="color: var(--primary);">🔥 每人重複發送封數（轰炸設定）：</label>
                <input type="number" id="mail_count_per_user" min="1" value="1" required style="border: 2px solid var(--primary);">
            </div>

            <div class="form-group">
                <label for="delay">每封郵件安全間隔 (秒)：</label>
                <input type="number" id="delay" min="0" max="60" value="1">
            </div>

            <button type="submit" id="btn-start">🚀 啟動群發排程任務</button>
        </form>
    </div>

    <div class="card" id="panel-running">
        <h2>⚡ 群發任務執行中</h2>
        <p id="task-status" style="font-size: 14px; color: #4B5563;">正在初始化發送佇列...</p>
        
        <div class="progress-container">
            <div id="progress-bar" class="progress-bar"></div>
            <div id="progress-text" class="progress-text">0% (0/0)</div>
        </div>

        <div class="log-box" id="log-box"></div>
        
        <button id="btn-reload" style="margin-top: 20px; background: #4B5563; display: none;" onclick="location.reload()">返回主控台</button>
    </div>

    <script>
        function toggleRandomInput() {
            const type = document.getElementById('send_type').value;
            document.getElementById('random_count_group').style.display = (type === 'random') ? 'block' : 'none';
        }

        // 非同步發送主程式
        async function startMassMail(event) {
            event.preventDefault();

            // 1. 讀取前端介面參數
            const subject = document.getElementById('subject').value;
            const content = document.getElementById('content').value;
            const send_type = document.getElementById('send_type').value;
            const random_count = document.getElementById('random_count').value;
            const mail_count_per_user = parseInt(document.getElementById('mail_count_per_user').value) || 1;
            const delay = parseInt(document.getElementById('delay').value) * 1000;

            // 2. 切換 UI 面板
            document.getElementById('panel-setup-list').style.display = 'none';
            document.getElementById('panel-setup-send').style.display = 'none';
            document.getElementById('panel-running').style.display = 'block';

            const logBox = document.getElementById('log-box');
            const progressBar = document.getElementById('progress-bar');
            const progressText = document.getElementById('progress-text');
            const taskStatus = document.getElementById('task-status');

            addLog('正在向後端請求獲取目標名單...');

            try {
                // 3. 向後端索取基礎名單
                const fetchUrl = `index.php?action=fetch_tasks&send_type=${send_type}&random_count=${random_count}`;
                const response = await fetch(fetchUrl);
                const result = await response.json();

                if (!result.success) {
                    throw new Error(result.message || '無法撈取名單');
                }

                const baseUsers = result.data;
                if (baseUsers.length === 0) {
                    taskStatus.innerText = '任務結束。';
                    addLog('<span style="color: #FBBF24;">⚠️ 資料庫中沒有符合條件的名單。</span>');
                    document.getElementById('btn-reload').style.display = 'block';
                    return;
                }

                // 💡 核心邏輯：根據「每人發送封數」將名單展開成真正的發送佇列 (Queue)
                let finalQueue = [];
                for (let user of baseUsers) {
                    for (let m = 1; m <= mail_count_per_user; m++) {
                        finalQueue.push({
                            email: user.email,
                            current_loop: m
                        });
                    }
                }

                const totalTasks = finalQueue.length;
                taskStatus.innerHTML = `篩選出 <strong>${baseUsers.length}</strong> 人，每人寄送 <strong>${mail_count_per_user}</strong> 封，總計需發送：<strong>${totalTasks}</strong> 封郵件。`;
                addLog(`成功建立發送佇列，共計 ${totalTasks} 筆任務，開始發送...`);

                // 4. 依序跑迴圈發送
                for (let i = 0; i < totalTasks; i++) {
                    const currentNo = i + 1;
                    const task = finalQueue[i];
                    
                    addLog(`[${currentNo}/${totalTasks}] 正在發送第 ${task.current_loop} 封至: ${task.email} ...`);

                    // 💡 修正後正確的 Fetch POST 送法
                    const sendResponse = await fetch('index.php?action=send_single', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json'
                        },
                        body: JSON.stringify({
                            email: task.email,
                            subject: subject,
                            content: content
                        })
                    });

                    const sendResult = await sendResponse.json();

                    // 更新 UI Log
                    if (sendResult.success) {
                        addLog(`[${currentNo}/${totalTasks}] 至 ${task.email} (第 ${task.current_loop} 封) <span style="color: #10B981;">▶ 發送成功</span>`);
                    } else {
                        addLog(`[${currentNo}/${totalTasks}] 至 ${task.email} <span style="color: #EF4444;">▶ 失敗 (原因: ${sendResult.message})</span>`);
                    }

                    // 更新進度條
                    const percent = Math.round((currentNo / totalTasks) * 100);
                    progressBar.style.width = `${percent}%`;
                    progressText.innerText = `${percent}% (${currentNo}/${totalTasks})`;
                    
                    if(percent === 100) {
                        progressBar.style.backgroundColor = '#10B981';
                    }

                    // 間隔延時
                    if (currentNo < totalTasks && delay > 0) {
                        await new Promise(resolve => setTimeout(resolve, delay));
                    }
                }

                taskStatus.innerHTML = `<span style="color: #10B981; font-weight: bold;">🎉 所有郵件任務已全數安全發送完畢！</span>`;
                addLog('任務完成。');

            } catch (err) {
                addLog(`<span style="color: #EF4444;">🚨 核心引擎發生致命錯誤: ${err.message}</span>`);
                taskStatus.innerText = '任務因錯誤中斷。';
            } finally {
                document.getElementById('btn-reload').style.display = 'block';
            }
        }

        function addLog(text) {
            const logBox = document.getElementById('log-box');
            const time = new Date().toLocaleTimeString();
            logBox.innerHTML += `<div class="log-item">[${time}] ${text}</div>`;
            logBox.scrollTop = logBox.scrollHeight;
        }
    </script>
</body>
</html>