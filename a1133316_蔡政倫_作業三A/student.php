<?php
session_start();

if(isset($_SESSION['login'])){
    if(($_SESSION['login']=='student')){
        echo "<h1>Welcome!student Login Success</h1></br>";
        echo"<a href='logout.php'>Logout</a>";
    }else{
        echo"<h1>非法進入網頁你會看不到東西!</h1>";
        header("Refresh:3;url=index.php");
    }
}else{
    echo"<h1>非法進入網頁你會看不到東西!</h1>";
    header("Refresh:3;url=index.php");
}


?>