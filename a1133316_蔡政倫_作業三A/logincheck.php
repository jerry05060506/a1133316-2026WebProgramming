<?php
session_start();

$fID='student';
$fPWD='12345';

$aID='admin';
$aPWD='123456';

$bID='teacher';
$bPWD='123456';

$uID=$_POST['uName'];
$uPwd=$_POST['uPwd'];

$date=strtotime("+5 days",time());

if($uID==$fID && $uPwd==$fPWD){
    $_SESSION['login']='student';
    setcookie("uName",$uID,$date);
    header("Refresh:0;url=student.php");

    }else if($uID==$aID && $uPwd==$aPWD){
    $_SESSION['login']='admin';
    setcookie("uName",$uID,$date);
    header("Refresh:0;url=admin.php");
    
    }else if($uID==$bID && $uPwd==$bPWD){
    $_SESSION['login']='teacher';
    setcookie("uName",$uID,$date);
    header("Refresh:0;url=teacher.php");

    }else{
    echo "Loging Failed!";
    header("Refresh:3;url=index.php");
}

?>