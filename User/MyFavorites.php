<?php
session_start();

if(isset($_SESSION["email"])){
    $email= $_SESSION["email"];
}else{
    echo "<script>window.location.href='../';</script>";
}

include("../connections.php");

include("nav.php");

$check = $checkErr = "";
if(isset($_POST["btnSubmit"])){
    if(empty($_POST["check"])){
        $checkErr = "Select at least one (1)";
    }else{
        $check = $_POST["check"];
    }

    if($check){
        echo "<br><br>";
      foreach($check as $new_check){
        echo $new_check. "<br>";
      }
    }
}

?>
<hr>
<form method="POST">
    <span class = "error"><?php echo $checkErr; ?></span>
    <input type="checkbox" name="check[]" value="Beer"> Beer <Br>
    <input type="checkbox" name="check[]" value="san Miglight Apple"> San Miglight <Br>
    <input type="checkbox" name="check[]" value="Alfonso Light"> Alfonso Light <Br>
    <input type="checkbox" name="check[]" value="Grate Taste While Choco"> Grate Taste While Choco <Br>

    <input type="checkbox" name="check[]" value="Yakult"> Yakult <Br>
    <input type="checkbox" name="check[]" value="water"> water <Br>
    <input type="checkbox" name="check[]" value="chuckie"> chuckie <Br>
    <input type="checkbox" name="check[]" value="Pinacolada"> Pinacolada <Br>

     <input type="checkbox" name="check[]" value="Pale pilsen"> Pale pilsen <Br>
    <input type="checkbox" name="check[]" value="Margarita"> Margarita <Br>

    <input type="submit" name="btnSubmit" value ="Submit">
    

</form>

<hr>

<select name = "catergory" id= "catergory" onChange="category" onChange="category(this.value);">

   <option name = "category" value="Car">Car</option>
   <option name = "category" value="Food">Food</option>
   <option name = "category" value="beer">beer</option>
   <option name = "category" value="Beverage">Beverage</option>
   
</select>

<select name = "chocolate" id="Choice">
    <option name="choice" value="">Select Category First</option>

</select>
