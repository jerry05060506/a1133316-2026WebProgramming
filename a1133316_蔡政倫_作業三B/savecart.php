<?php
session_start();
if (isset($_POST["Item"])) {
    $ItemInfo = explode(",", $_POST["Item"]);
    $ID = $ItemInfo[0];
    $Name = $ItemInfo[1];
    $Price = $ItemInfo[2];
    $Quantity = $_POST["Quantity"];

    setcookie("Cart[" . $ID . "][ID]", $ID, time() + 3600);
    setcookie("Cart[" . $ID . "][Name]", $Name, time() + 3600);
    setcookie("Cart[" . $ID . "][Price]", $Price, time() + 3600);
    setcookie("Cart[" . $ID . "][Quantity]", $Quantity, time() + 3600);
}

header("Location: shoppingcart.php");
exit;
?>