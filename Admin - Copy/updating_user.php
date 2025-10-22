<?php

$id_user = $_GET["id_user"];

$get_record = mysqli_query($connections, "SELECT * FROM tbl_user WHERE id_user = '$id_user'");
while ($get = mysqli_fetch_assoc($get_record)) {
    $db_first_name = $get["first_name"];
    $db_middle_name = $get["middle_name"];
    $db_last_name = $get["last_name"];
    $db_establishment_name = $get["establishment_name"];
    $db_capital = $get["capital"];
    $db_city = $get["city"];
    $db_barangay = $get["barangay"];
    $db_lot_street = $get["lot_street"];
    $db_preffix = $get["preffix"];
    $db_seven_digit = $get["seven_digit"];
    $db_email = $get["email"];
    $db_password = $get["password"];
}

$new_first_name = $new_middle_name = $new_last_name = $new_establishment_name = $new_capital = $new_city = $new_barangay = $new_lot_street = $new_preffix = $new_seven_digit = $new_email = $new_password = "";
$new_first_nameErr = $new_middle_nameErr = $new_last_nameErr = $new_establishment_nameErr = $new_capitalErr = $new_cityErr = $new_barangayErr = $new_lot_streetErr =  $new_preffixErr = $new_seven_digitErr = $new_emailErr = $new_passwordErr = "";

if(isset($_POST["btnUpdate"])){
    if(empty($_POST["new_first_name"])){
        $new_first_nameErr="this field must not be empty";
    }else{
        $new_first_name = $_POST["new_first_name"];
    }

    if(empty($_POST["new_middle_name"])){
        $new_middle_nameErr="this field must not be empty";
    }else{
        $new_middle_name = $_POST["new_middle_name"];
    }

    if(empty($_POST["new_last_name"])){
        $new_last_nameErr="this field must not be empty";
    }else{
        $new_last_name = $_POST["new_last_name"];
    }
    
    if(empty($_POST["new_establishment_name"])){
        $new_establishment_nameErr="this field must not be empty";
    }else{
        $new_establishment_name = $_POST["new_establishment_name"];
    }

    if(empty($_POST["new_capital"])){
        $new_capitalErr="this field must not be empty";
    }else{
        $new_capital = $_POST["new_capital"];
    }

    if(empty($_POST["new_lot_street"])){
        $new_lot_streetErr="this field must not be empty";
    }else{
        $new_lot_street = $_POST["new_lot_street"];
    }

    if(empty($_POST["new_seven_digit"])){
        $new_seven_digitErr="this field must not be empty";
    }else{
        $new_seven_digit = $_POST["new_seven_digit"];
    }

    if(empty($_POST["new_email"])){
        $new_emailErr="this field must not be empty";
    }else{
        $new_email = $_POST["new_email"];
    }

    if(empty($_POST["new_password"])){
        $new_passwordErr="this field must not be empty";
    }else{
        $new_password = $_POST["new_password"];
    }
   
    $new_city = $_POST["new_city"];
    $new_barangay = $_POST["new_barangay"];
    $new_lot_street = $_POST["new_lot_street"];
    $new_preffix = $_POST["new_preffix"];

    if($new_first_name && $new_middle_name && $new_last_name && $new_establishment_name && $new_capital && $new_lot_street && $new_seven_digit && $new_email && $new_password){
        mysqli_query($connections, "UPDATE tbl_user SET first_name = '$new_first_name', middle_name = '$new_middle_name', last_name = '$new_last_name', establishment_name = '$new_establishment_name', capital = '$new_capital', city = '$new_city', barangay = '$new_barangay', lot_street = '$new_lot_street', preffix = '$new_preffix', seven_digit = '$new_seven_digit', email = '$new_email', password = '$new_password' WHERE id_user = '$id_user'");

        $encrypted = md5 (rand(1,9));
        echo "<script>window.location.href='ViewRecord.php?$encrypted&&notify=Record has been updated!';</script>";
    }
}

?>

<center>
    <br>
    <br>
    <br>
<form method="POST">
<table border="0" width="50%">
    
      <br>
      <br>
      <br>
     <tr><td>
                <input type="text" name="new_first_name" placeholder="first name" value="<?php echo $db_first_name; ?>">
                <span class="error"><?php echo $new_first_nameErr; ?></span>

                <input type="text" name="new_middle_name" placeholder="middle name" value="<?php echo $db_middle_name; ?>">
                <span class="error"><?php echo $new_middle_nameErr; ?></span> 

                <input type="text" name="new_last_name" placeholder="last name" value="<?php echo $db_last_name; ?>">
                <span class="error"><?php echo $new_last_nameErr; ?></span>
            </td></tr>
            

            <tr><td>
                <input type="text" name="new_establishment_name" placeholder="establishment name" value="<?php echo $db_establishment_name; ?>">
                <span class="error"><?php echo $new_establishment_nameErr; ?></span>
            
                <input type="text" name="new_capital" placeholder="capital" value="<?php echo $db_capital; ?>">
                <span class="error"><?php echo $new_capitalErr; ?></span>
            </td></tr>

            <tr>
                <td>
                    <select name="new_city" id="new_city" onchange="updateBarangays()">
                        <option value="" <?php if (empty($db_city)) { echo "selected"; } ?>>Select City</option>
                        <option value="Lipa City" <?php if ($db_city == "Lipa City") { echo "selected"; } ?>>Lipa City</option>
                        <option value="Tanauan City" <?php if ($db_city == "Tanauan City") { echo "selected"; } ?>>Tanauan City</option>
                        <option value="Santo Tomas City" <?php if ($db_city == "Santo Tomas City") { echo "selected"; } ?>>Santo Tomas City</option>
                        <option value="Batangas City" <?php if ($db_city == "Batangas City") { echo "selected"; } ?>>Batangas City</option>
                        <option value="San Jose" <?php if ($db_city == "San Jose") { echo "selected"; } ?>>San Jose</option>
                    </select>
                    <span class="error"><?php echo $new_cityErr; ?></span>
                </td>
            </tr>
            <tr>
                <td>
                    <select name="new_barangay" id="new_barangay">
                        <option value="" <?php if (empty($db_barangay)) { echo "selected"; } ?>>Select Barangay</option>
                        <?php
                        $db_barangays = [
                            'Lipa City' => [
                                'Adya', 'Anilao', 'Anilao-Labac', 'Antipolo del Norte', 'Antipolo del Sur', 'Bagong Pook', 'Balintawak',
                                'Banaybanay', 'Bolbok', 'Bugtong na Pulo', 'Bulacnin', 'Calamias', 'Cumba', 'Dagatan', 'Duhatan',
                                'Halang', 'Inosloban', 'Kayumanggi', 'Latag', 'Lodlod', 'Lumbang', 'Mabini', 'Malagonlong', 'Malitlit',
                                'Marawoy', 'Mataas na Lupa', 'Pagolingin Bata', 'Pagolingin East', 'Pagolingin West', 'Pangao', 'Pinagkawitan',
                                'Pinagtongulan', 'Plaridel', 'Poblacion Barangay 1', 'Poblacion Barangay 2', 'Poblacion Barangay 3',
                                'Poblacion Barangay 4', 'Poblacion Barangay 5', 'Poblacion Barangay 6', 'Poblacion Barangay 7',
                                'Poblacion Barangay 7A', 'Poblacion Barangay 8', 'Poblacion Barangay 9', 'Poblacion Barangay 9A',
                                'Poblacion Barangay 10', 'Poblacion Barangay 11', 'Poblacion Barangay 12', 'Pusil', 'Quezon', 'Rizal',
                                'Sabang', 'Sampaguita', 'San Benito', 'San Carlos', 'San Celestino', 'San Francisco', 'San Guillermo',
                                'San Isidro', 'San Jose', 'San Lucas', 'San Salvador', 'San Sebastian', 'Santo Niño', 'Santo Tomas',
                                'Sapac', 'Sico', 'Talipapa', 'Tambo', 'Tanguay', 'Tibig', 'Tipacan'
                            ],
                            'Tanauan City' => [
                                'Darasa', 'Poblacion Barangay 1', 'Poblacion Barangay 2', 'Sambat', 'San Isidro', 'Santa Maria',
                                'Tapia', 'Tranca'
                            ],
                            'Santo Tomas City' => [
                                'San Antonio', 'San Bartolome', 'Poblacion', 'Santa Anastacia', 'San Miguel', 'Santa Maria',
                                'Santo Tomas', 'Tulay na Patpat'
                            ],
                            'Batangas City' => [
                                'Poblacion Barangay 1', 'Poblacion Barangay 2', 'Cuta', 'Kumintang Ibaba', 'Santa Rita Karsada',
                                'Alangilan', 'Balagtas', 'Pallocan Kanluran'
                            ],
                            'San Jose' => [
                                'Banaybanay', 'Poblacion Barangay 1', 'Salaban', 'Tugtog', 'Balagtasin', 'Galamay-Amo',
                                'Lalayat', 'Pinagtung-Ulan'
                            ]
                        ];
                        if (!empty($db_city) && isset($db_barangays[$db_city])) {
                            foreach ($db_barangays[$db_city] as $db_brgy) {
                                echo "<option value=\"$db_brgy\" " . ($db_barangay == $db_brgy ? "selected" : "") . ">$db_brgy</option>";
                            }
                        }
                        ?>
                    </select>
                    <span class="error"><?php echo $new_barangayErr; ?></span>
                </td>
            </tr>
            <tr>
                <td>
                    <input type="text" name="new_lot_street" placeholder="Lot/Street (e.g., Block 5 Lot 10 or Main St.)" value="<?php echo $db_lot_street; ?>">
                    <span class="error"><?php echo $new_lot_streetErr; ?></span>
                </td>
            </tr>
            <tr>
                <td>
                    <select name="new_preffix">
                        <option name="new_preffix" id="new_preffix" value="">Network Provided(Globe,Smart,Sun,TNT,TM etc.)</option>
                        <option name="new_preffix" id="new_preffix" value="0813" <?php if ($db_preffix == "0813") { echo "selected"; } ?>>0813</option>
                        <option name="new_preffix" id="new_preffix" value="0817" <?php if ($db_preffix == "0817") { echo "selected"; } ?>>0817</option>
                        <option name="new_preffix" id="new_preffix" value="0905" <?php if ($db_preffix == "0905") { echo "selected"; } ?>>0905</option>
                        <option name="new_preffix" id="new_preffix" value="0906" <?php if ($db_preffix == "0906") { echo "selected"; } ?>>0906</option>
                        <option name="new_preffix" id="new_preffix" value="0907" <?php if ($db_preffix == "0907") { echo "selected"; } ?>>0907</option>
                    </select>
                    <span class="error"><?php echo $new_preffixErr; ?></span>
                    <input type="text" name="new_seven_digit" placeholder="Other Seven Digit" onkeypress="return isNumberKey(event)" value="<?php echo $db_seven_digit; ?>" maxlength="7">
                    <span class="error"><?php echo $new_seven_digitErr; ?></span>
                </td>
            </tr>
            <tr>
                <td>
                    <input type="text" name="new_email" value="<?php echo $db_email; ?>" placeholder="Email">
                    <span class="error"><?php echo $new_emailErr; ?></span>
                </td>
            </tr>
            <tr>
                <td>
                    <input type="password" name="new_password" value="<?php echo $db_password; ?>" placeholder="password">
                    <span class="error"><?php echo $new_passwordErr; ?></span>
                </td>
            </tr>

            <tr>
                <td>
                    <hr>
                </td>
            </tr>

    <tr>
        <td><hr></td></tr>
    </tr>

    <tr>
        <td><input type="submit" name="btnUpdate" value="Update" class="btn_primary"></td>
    </tr>

</table>

</form>

<script>
function updateBarangays() {
    var citySelect = document.getElementById("new_city");
    var barangaySelect = document.getElementById("new_barangay");
    var selectedCity = citySelect.value;

    // Clear existing barangay options
    barangaySelect.innerHTML = '<option value="">Select Barangay</option>';

    // Define barangays for each city
    var barangays = {
        'Lipa City': [
            'Adya', 'Anilao', 'Anilao-Labac', 'Antipolo del Norte', 'Antipolo del Sur', 'Bagong Pook', 'Balintawak',
            'Banaybanay', 'Bolbok', 'Bugtong na Pulo', 'Bulacnin', 'Calamias', 'Cumba', 'Dagatan', 'Duhatan',
            'Halang', 'Inosloban', 'Kayumanggi', 'Latag', 'Lodlod', 'Lumbang', 'Mabini', 'Malagonlong', 'Malitlit',
            'Marawoy', 'Mataas na Lupa', 'Pagolingin Bata', 'Pagolingin East', 'Pagolingin West', 'Pangao', 'Pinagkawitan',
            'Pinagtongulan', 'Plaridel', 'Poblacion Barangay 1', 'Poblacion Barangay 2', 'Poblacion Barangay 3',
            'Poblacion Barangay 4', 'Poblacion Barangay 5', 'Poblacion Barangay 6', 'Poblacion Barangay 7',
            'Poblacion Barangay 7A', 'Poblacion Barangay 8', 'Poblacion Barangay 9', 'Poblacion Barangay 9A',
            'Poblacion Barangay 10', 'Poblacion Barangay 11', 'Poblacion Barangay 12', 'Pusil', 'Quezon', 'Rizal',
            'Sabang', 'Sampaguita', 'San Benito', 'San Carlos', 'San Celestino', 'San Francisco', 'San Guillermo',
            'San Isidro', 'San Jose', 'San Lucas', 'San Salvador', 'San Sebastian', 'Santo Niño', 'Santo Tomas',
            'Sapac', 'Sico', 'Talipapa', 'Tambo', 'Tanguay', 'Tibig', 'Tipacan'
        ],
        'Tanauan City': [
            'Darasa', 'Poblacion Barangay 1', 'Poblacion Barangay 2', 'Sambat', 'San Isidro', 'Santa Maria',
            'Tapia', 'Tranca'
        ],
        'Santo Tomas City': [
            'San Antonio', 'San Bartolome', 'Poblacion', 'Santa Anastacia', 'San Miguel', 'Santa Maria',
            'Santo Tomas', 'Tulay na Patpat'
        ],
        'Batangas City': [
            'Poblacion Barangay 1', 'Poblacion Barangay 2', 'Cuta', 'Kumintang Ibaba', 'Santa Rita Karsada',
            'Alangilan', 'Balagtas', 'Pallocan Kanluran'
        ],
        'San Jose': [
            'Banaybanay', 'Poblacion Barangay 1', 'Salaban', 'Tugtog', 'Balagtasin', 'Galamay-Amo',
            'Lalayat', 'Pinagtung-Ulan'
        ]
    };

    // Populate barangay dropdown based on selected city
    if (selectedCity && barangays[selectedCity]) {
        barangays[selectedCity].forEach(function(barangay) {
            var option = document.createElement("option");
            option.value = barangay;
            option.text = barangay;
            barangaySelect.appendChild(option);
        });
    }
}
</script>
</center>