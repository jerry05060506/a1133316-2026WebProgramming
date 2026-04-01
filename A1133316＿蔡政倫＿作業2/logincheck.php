<?php

$fID="derrick";
$fPWD="12345678";
if(isset($_POST["uID"])&&isset($_POST["uPWD"])){

    $uID=$_POST["uID"];
    $uPWD=$_POST["uPWD"];
    if($fID==$uID && $fPWD==$uPWD){
        header("Location: form.php");
    }else{
        echo "失敗 三秒後可以重新嘗試";
      header("Refresh:2;url=login.php");
    }
}

?>