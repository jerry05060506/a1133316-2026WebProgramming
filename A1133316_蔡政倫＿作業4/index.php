<?php
// 1. 強制關閉 PHP 與伺服器的輸出緩衝，確保網頁不暫存、即時吐出內容
if (ob_get_level()) ob_end_clean();
header('X-Accel-Buffering: no'); 

require_once 'db.php';

require_once 'src/Exception.php';
require_once 'src/PHPMailer.php';
require_once 'src/SMTP.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

$message = '';

// A. 使用者輸入 email 位址，建構資料庫
if (isset($_POST['add_email'])) {
    $email = filter_var($_POST['email'], FILTER_VALIDATE_EMAIL);
    if ($email) {
        try {
            $stmt = $pdo->prepare("INSERT INTO subscribers (email) VALUES (:email)");
            $stmt->execute(['email' => $email]);
            $message = "<div class='alert success'>Email 成功寫入資料庫！</div>";
        } catch (PDOException $e) {
            $message = "<div class='alert error'>新增失敗：此 Email 可能重複了。</div>";
        }
    } else {
        $message = "<div class='alert error'>請輸入正確的 Email 格式！</div>";
    }
}

// B. 寄信與顯示進度邏輯
if (isset($_POST['send_mail'])) {
    $subject = $_POST['subject'];
    $content = $_POST['content'];
    $send_type = $_POST['send_type']; 
    $random_count = intval($_POST['random_count']);
    $delay = intval($_POST['delay']); 

    // 依條件撈取資料庫的名單
    if ($send_type === 'random' && $random_count > 0) {
        $stmt = $pdo->prepare("SELECT id, email FROM subscribers ORDER BY RAND() LIMIT :limit");
        $stmt->bindValue(':limit', $random_count, PDO::PARAM_INT);
        $stmt->execute();
    } else {
        $stmt = $pdo->query("SELECT id, email FROM subscribers");
    }
    $list = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $total = count($list);
    
    if ($total > 0) {
        ob_start();
        
        echo "<style>
                body { font-family: Arial, sans-serif; max-width: 600px; margin: 30px auto; padding: 20px; background: #f9f9f9; }
                .progress-container { width: 100%; background: #eee; border-radius: 5px; margin: 20px 0; box-shadow: inset 0 1px 3px rgba(0,0,0,0.2); overflow: hidden; }
                .progress-bar { width: 5%; min-width: 5%; height: 30px; background: #ffc107; text-align: center; line-height: 30px; color: #333; border-radius: 5px; transition: width 0.4s ease; font-weight: bold; }
                .log-box { background: #fff; border: 1px solid #ccc; padding: 15px; height: 200px; overflow-y: auto; border-radius: 5px; font-family: monospace; }
              </style>";
        
        echo "<h2>🚀 郵件發送中...</h2>";
        echo "<p style='color: #555;'>系統提示：本次預計發送 <strong>{$total}</strong> 筆郵件。</p>";
        echo "<div class='progress-container'><div id='progress-bar' class='progress-bar'>0%</div></div>";
        echo "<div class='log-box' id='log-box'>";
        
        echo str_repeat(' ', 4096); 
        ob_flush();
        flush();

        // 【安全拆分】不在最外層用 try 包住，防止一出錯整個迴圈與畫面死掉
        $mail = new PHPMailer(true);
        
        // 這裡設定基本 SMTP 參數
        $mail->isSMTP();                                            
        $mail->Host       = 'smtp.gmail.com';                        
        $mail->SMTPAuth   = true;                                   
        $mail->Username   = 'a1133316@mail.nuk.edu.tw';     
        $mail->Password   = 'lfhk uelw soil yave';   
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;         
        $mail->Port       = 587;                                    
        $mail->CharSet    = 'UTF-8';
        $mail->Encoding   = 'base64';
        $mail->setFrom($mail->Username, '垃圾郵件系統');   
        $mail->isHTML(true);                                        
        $mail->Subject = $subject; 
        $mail->Body    = nl2br(htmlspecialchars($content));         

        // 開始跑迴圈寄信
        foreach ($list as $index => $row) {
            $no = $row['id'];
            $to_email = $row['email'];

            // 每一封信獨立進行 try-catch，就算失敗也會把原因印出來，並繼續執行下一筆！
            try {
                $mail->addAddress($to_email); 
                $mail->send();
                $mail_status = "<span style='color:green;'>成功</span>";
            } catch (Exception $e) {
                // 🔥 關鍵點：如果失敗，直接抓取 PHPMailer 吐出來的真實死因
                $mail_status = "<span style='color:red; font-weight:bold;'>失敗！原因: " . htmlspecialchars($mail->ErrorInfo) . "</span>";
            }

            $mail->clearAddresses(); 

            // 計算目前進度百分比
            $current = $index + 1;
            $percent = round(($current / $total) * 100);

            // 用 JavaScript 即時更新網頁上的進度條外觀、顏色與紀錄
            echo "<script>
                    var bar = document.getElementById('progress-bar');
                    bar.style.width = '{$percent}%';
                    bar.style.backgroundColor = '{$percent}' == 100 ? '#28a745' : '#ffc107';
                    bar.innerHTML = '{$percent}% ({$current}/{$total})';
                    document.getElementById('log-box').innerHTML += '<p>【No.{$no}】至: {$to_email} ... {$mail_status}</p>';
                    document.getElementById('log-box').scrollTop = document.getElementById('log-box').scrollHeight;
                  </script>";
            
            echo str_repeat(' ', 4096); 
            ob_flush();
            flush();

            // 設定寄送郵件間隔秒數
            if ($current < $total && $delay > 0) {
                sleep($delay);
            }
        }
        
        echo "<p style='color: green; font-weight: bold; margin-top: 15px;'>🎉 全數處理完畢！</p>";
        echo "<a href='index.php' style='display:inline-block; margin-top:10px; background:#007BFF; color:#fff; padding:8px 15px; text-decoration:none; border-radius:4px;'>返回主畫面</a>";
        echo "</div>";
        exit; 
    } else {
        $message = "<div class='alert error'>資料庫中沒有任何郵件名單！</div>";
    }
}
?>

<!DOCTYPE html>
<html lang="zh-TW">
<head>
    <meta charset="UTF-8">
    <title>垃圾郵件寄送系統</title>
    <style>
        body { font-family: Arial, sans-serif; max-width: 600px; margin: 30px auto; padding: 20px; background: #f4f7f6; }
        .card { background: white; padding: 25px; margin-bottom: 20px; border-radius: 8px; box-shadow: 0 4px 6px rgba(0,0,0,0.05); }
        h2 { margin-top: 0; color: #333; border-bottom: 2px solid #007BFF; padding-bottom: 8px; }
        .form-group { margin-bottom: 15px; }
        label { display: block; margin-bottom: 5px; font-weight: bold; color: #555; }
        input[type="text"], input[type="email"], input[type="number"], textarea, select { width: 100%; padding: 10px; box-sizing: border-box; border: 1px solid #ccc; border-radius: 4px; font-size: 14px; }
        button { background: #007BFF; color: white; border: none; padding: 10px 20px; border-radius: 4px; cursor: pointer; font-size: 14px; width: 100%; }
        button:hover { background: #0056b3; }
        .alert { padding: 12px; margin-bottom: 15px; border-radius: 4px; font-weight: bold; }
        .success { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .error { background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
    </style>
</head>
<body>

    <h1 style="text-align: center; color: #333;">📩 垃圾郵件群發系統</h1>
    <?php echo $message; ?>

    <div class="card">
        <h2>A. 建構資料庫 (新增名單)</h2>
        <form method="post">
            <div class="form-group">
                <label for="email">輸入 Email 位址：</label>
                <input type="email" name="email" id="email" required placeholder="例如: student@school.edu.tw">
            </div>
            <button type="submit" name="add_email" style="background: #17a2b8;">將 Email 寫入資料庫</button>
        </form>
    </div>

    <div class="card">
        <h2>B. 基本郵件介面與寄送設定</h2>
        <form method="post">
            <div class="form-group">
                <label for="subject">郵件主旨：</label>
                <input type="text" name="subject" id="subject" required value="【期末歐趴必看】最新考古題特惠中！">
            </div>
            
            <div class="form-group">
                <label for="content">郵件內容：</label>
                <textarea name="content" id="content" rows="5" required>同學你好，這是一封期末專題測試用的垃圾郵件內容...</textarea>
            </div>

            <div class="form-group">
                <label for="send_type">寄送模式：</label>
                <select name="send_type" id="send_type" onchange="toggleRandomInput()">
                    <option value="all">全部寄送</option>
                    <option value="random">隨機寄送幾筆</option>
                </select>
            </div>

            <div class="form-group" id="random_count_group" style="display: none;">
                <label for="random_count">要隨機寄送幾筆？</label>
                <input type="number" name="random_count" id="random_count" min="1" value="3">
            </div>

            <div class="form-group">
                <label for="delay">設定寄送郵件間隔秒數（秒）：</label>
                <input type="number" name="delay" id="delay" min="0" max="10" value="1">
            </div>

            <button type="submit" name="send_mail" style="background: #28a745; font-size: 16px; font-weight: bold;">🚀 開始群發郵件並顯示進度</button>
        </form>
    </div>

    <script>
        function toggleRandomInput() {
            var type = document.getElementById('send_type').value;
            var group = document.getElementById('random_count_group');
            group.style.display = (type === 'random') ? 'block' : 'none';
        }
    </script>
</body>
</html>