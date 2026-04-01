<?php
echo "your name is:".$_POST["nName"]."<br/>";

$nPass=$_POST["nPassword"];
echo"your password is:".$nPass."<br/>";

$nGender=$_POST["nGender"];
echo "your gender is:".$nGender."<br/>";

if($nGender=="m"){
    echo "your gender is:男性<br/>";
}else{
    echo "your gender is:女性<br/>";
}

$nInterest=$_POST["nInterest"];
echo "你的興趣是:";
foreach($nInterest as $nI2){
    echo $nI2;
    echo "<br/>";
}

for($i=0;$i<count($nInterest);$i++){
    echo $nInterest[$i]."<br/>";
}

echo "你的興趣是:";
foreach($nInterest as $ni2){
    switch($ni2){
        case "swim";
        echo "游泳";
        break;
        case "read";
        echo "讀書";
        break;
        case "sleep";
        echo "睡覺";
        break;
    }
}

?>

<html>
你的密碼是:<?php echo $nPass?>

<html>