<?php

if(isset($_COOKIE['uName'])){
    echo $_COOKIE['uName']."歡迎回來";
    echo"<a href='cookiedel.php'>刪除COOKIE</a>";
}
?>

<form action="logincheck.php" method="POST">

ID:<input type="text" name="uName"><br/>
Password:<input type="password" name="uPwd"></br>
<input type="submit">





</form>