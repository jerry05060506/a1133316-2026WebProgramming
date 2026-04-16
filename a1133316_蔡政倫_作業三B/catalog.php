<?php
session_start();
?>
<!DOCTYPE html>
<html>
<head><title>商品目錄</title></head>
<body>
    <form action="savecart.php" method="post">
    <br><br><B><I>選擇商品：</I></B>
        <select name="Item">
            <option value="S001,10吋平板電腦,12000">10吋平板電腦 - $12000</option>
            <option value="S002,15.6吋筆記型電腦,27000">15.6吋筆記型電腦 - $27000</option>
            <option value="S003,iPhone智慧型手機,21000">iPhone智慧型手機 - $21000</option>
        </select>
        數量: <input type="number" name="Quantity" value="1" min="1" style="width:80px;">
        <input type="submit" value="訂購">
    </form>
    <hr>
    <a href="shoppingcart.php">查看購物車</a>
</body>
</html>