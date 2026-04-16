<!DOCTYPE html>
<html>
<head><title>購物車內容</title></head>
<body>
    <table border="1" bgcolor="#66ffd1">
        <tr bgcolor="#ff99e0">
            <td>功能</td><td>編號</td><td>名稱</td><td>價格</td><td>數量</td>
        </tr>
        <?php
        $total=0;
        if (isset($_COOKIE["Cart"])) {
            foreach ($_COOKIE["Cart"] as $id => $item) {
            $subtotal = $item["Price"] * $item["Quantity"];
            $total += $subtotal;
            echo "<tr>";
            echo "<td><a href='delete.php?Id=" . $id . "'>刪除</a></td>";
            echo "<td>" . $item["ID"] . "</td>";
            echo "<td>" . $item["Name"] . "</td>";
            echo "<td>" . $item["Price"] . "</td>";
            echo "<td>" . $item["Quantity"] . "</td>";
            echo "</tr>";
            }
        }
        ?>
        <tr bgcolor="yellow">
            <td colspan="5" align="right">總金額 = NT$<?php echo $total; ?>元</td>
        </tr>
    </table>
    <hr>
    <a href="catalog.php">商品目錄</a> | <a href="shoppingcart.php">檢視購物車</a>
</body>
</html>