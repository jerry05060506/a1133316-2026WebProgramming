<html>
<head>
    <meta charset="utf-8">
    <title>報名成功!!!!!!</title>
</head>
<body style="background-color: #c8f0e6; padding: 50px;">
    <h1>恭喜你報名成功！</h1>
    <p>以下是你剛才填寫的資料：</p>
    <hr>
    <p>姓名：<?php echo $_POST['name']; ?></p>
    <p>電話：<?php echo $_POST['phone']; ?></p>
    <p>Email：<?php echo $_POST['email']; ?></p>
    <p>性別：<?php echo $_POST['gender']; ?></p>
    <p>飲食習慣：<?php echo $_POST['food']; ?></p>
    <p>報名梯次：
        <?php 
            if (isset($_POST['s'])) {
                foreach ($_POST['s'] as $value) {
                    echo "第 " . $value . " 梯次 ";
                }
            }
        ?>
    </p>
    <hr>
    <a href="information.php">回到報名資訊頁面</a>
</body>
</html>