<?php
session_start();

include("connections.php");

$first_name = $middle_name = $last_name = $birthday = $birth_place = $city = $barangay = $lot_street = $prefix = $seven_digit = "";
$first_nameErr = $middle_nameErr = $last_nameErr = $birthdayErr = $birth_placeErr = $cityErr = $barangayErr = $lot_streetErr = $prefixErr = $seven_digitErr = "";
$establishment_name = $capital = $date_of_establishment = $business_type = $nature_of_business = $sabang_location = $lot_street_business = $DTI = $business_permit = $email = $password = $cpassword = "";
$establishment_nameErr = $capitalErr = $date_of_establishmentErr = $business_typeErr = $nature_of_businessErr = $sabang_locationErr = $lot_street_businessErr = $DTIErr = $business_permitErr = $emailErr = $passwordErr = $cpasswordErr = "";

if (!isset($_SESSION['temp_DTI'])) {
    $_SESSION['temp_DTI'] = '';
}
if (!isset($_SESSION['temp_business_permit'])) {
    $_SESSION['temp_business_permit'] = '';
}

if (isset($_POST["btnRegister"])) {
    if (empty($_POST["first_name"])) {
        $first_nameErr = "Required!";
    } else {
        $first_name = mysqli_real_escape_string($connections, $_POST["first_name"]);
    }

    if (empty($_POST["middle_name"])) {
        $middle_nameErr = "Required!";
    } else {
        $middle_name = mysqli_real_escape_string($connections, $_POST["middle_name"]);
    }

    if (empty($_POST["last_name"])) {
        $last_nameErr = "Required!";
    } else {
        $last_name = mysqli_real_escape_string($connections, $_POST["last_name"]);
    }

    if (empty($_POST["birthday"])) {
        $birthdayErr = "Required!";
    } else {
        $birthday = mysqli_real_escape_string($connections, $_POST["birthday"]);
    }

    if (empty($_POST["birth_place"])) {
        $birth_placeErr = "Required!";
    } else {
        $birth_place = mysqli_real_escape_string($connections, $_POST["birth_place"]);
    }

    if (empty($_POST["city"])) {
        $cityErr = "Required!";
    } else {
        $city = mysqli_real_escape_string($connections, $_POST["city"]);
    }

    if (empty($_POST["barangay"])) {
        $barangayErr = "Required!";
    } else {
        $barangay = mysqli_real_escape_string($connections, $_POST["barangay"]);
    }

    if (empty($_POST["lot_street"])) {
        $lot_streetErr = "Required!";
    } else {
        $lot_street = mysqli_real_escape_string($connections, $_POST["lot_street"]);
    }

    if (empty($_POST["prefix"])) {
        $prefixErr = "Required!";
    } else {
        $prefix = mysqli_real_escape_string($connections, $_POST["prefix"]);
    }

    if (empty($_POST["seven_digit"])) {
        $seven_digitErr = "Required!";
    } else {
        $seven_digit = mysqli_real_escape_string($connections, $_POST["seven_digit"]);
    }

    if (empty($_POST["email"])) {
        $emailErr = "Required!";
    } else {
        $email = mysqli_real_escape_string($connections, $_POST["email"]);
        $email_check_user = mysqli_query($connections, "SELECT email FROM tbl_user WHERE email='$email'");
        $email_check_pending = mysqli_query($connections, "SELECT email FROM tbl_pending_users WHERE email='$email'");
        if (mysqli_num_rows($email_check_user) > 0 || mysqli_num_rows($email_check_pending) > 0) {
            $emailErr = "Email already exists!";
            error_log("register.php: Duplicate email attempt: $email");
        }
    }

    if (empty($_POST["password"])) {
        $passwordErr = "Required!";
    } else {
        $password = mysqli_real_escape_string($connections, $_POST["password"]);
    }

    if (empty($_POST["cpassword"])) {
        $cpasswordErr = "Required!";
    } else {
        $cpassword = mysqli_real_escape_string($connections, $_POST["cpassword"]);
    }

    if (empty($_POST["establishment_name"])) {
        $establishment_nameErr = "Required!";
    } else {
        $establishment_name = mysqli_real_escape_string($connections, $_POST["establishment_name"]);
        $est_check_pending = mysqli_query($connections, "SELECT establishment_name FROM tbl_pending_users WHERE establishment_name='$establishment_name'");
        $est_check_business = mysqli_query($connections, "SELECT establishment_name FROM tbl_business WHERE establishment_name='$establishment_name'");
        if (mysqli_num_rows($est_check_pending) > 0 || mysqli_num_rows($est_check_business) > 0) {
            $establishment_nameErr = "Establishment name already taken!";
            error_log("register.php: Duplicate establishment name attempt: $establishment_name");
        }
    }

    if (empty($_POST["capital"])) {
        $capitalErr = "Required!";
    } else {
        $capital = mysqli_real_escape_string($connections, $_POST["capital"]);
        $capital_clean = str_replace(',', '', $capital);
        if (!preg_match('/^[0-9]+(,[0-9]{3})*$/', $capital)) {
            $capitalErr = "Invalid capital format! Use numbers with optional commas (e.g., 20,000)";
        } else {
            $capital_value = floatval($capital_clean);
            if ($capital_value < 100) {
                $capitalErr = "Capital must be at least 100!";
            } else {
                $enterprise_type = ($capital_value < 15000000) ? 'Small Enterprise' : 'Medium Enterprise';
            }
        }
    }

    if (empty($_POST["date_of_establishment"])) {
        $date_of_establishmentErr = "Required!";
    } else {
        $date_of_establishment = mysqli_real_escape_string($connections, $_POST["date_of_establishment"]);
    }

    if (empty($_POST["business_type"])) {
        $business_typeErr = "Required!";
    } else {
        $business_type = mysqli_real_escape_string($connections, $_POST["business_type"]);
    }

    if (empty($_POST["nature_of_business"])) {
        $nature_of_businessErr = "Required!";
    } else {
        $nature_of_business = mysqli_real_escape_string($connections, $_POST["nature_of_business"]);
    }

    if (empty($_POST["sabang_location"])) {
        $sabang_locationErr = "Required!";
    } else {
        $sabang_location = mysqli_real_escape_string($connections, $_POST["sabang_location"]);
    }

    if (empty($_POST["lot_street_business"])) {
        $lot_street_businessErr = "Required!";
    } else {
        $lot_street_business = mysqli_real_escape_string($connections, $_POST["lot_street_business"]);
    }

    if (!empty($_FILES["DTI"]["name"])) {
        $target_dir = "temp/";
        if (!is_dir($target_dir)) {
            mkdir($target_dir, 0755, true);
        }
        $DTI_target_file = $target_dir . basename($_FILES["DTI"]["name"]);
        $imageFileType = strtolower(pathinfo($DTI_target_file, PATHINFO_EXTENSION));
        $uploadOk = 1;

        if (file_exists($DTI_target_file)) {
            $DTI_target_file = $target_dir . rand(1,9) . rand(1,9) . rand(1,9) . rand(1,9) . "_" . basename($_FILES["DTI"]["name"]);
        }

        if ($_FILES["DTI"]["size"] > 5000000) {
            $DTIErr = "File too large. Max 5MB.";
            $uploadOk = 0;
        }

        if ($imageFileType != "jpg" && $imageFileType != "jpeg" && $imageFileType != "png" && $imageFileType != "gif") {
            $DTIErr = "Only JPG, JPEG, PNG, GIF allowed.";
            $uploadOk = 0;
        }

        if ($uploadOk == 1) {
            if (move_uploaded_file($_FILES["DTI"]["tmp_name"], $DTI_target_file)) {
                if (!empty($_SESSION['temp_DTI']) && file_exists($_SESSION['temp_DTI'])) {
                    unlink($_SESSION['temp_DTI']);
                }
                $_SESSION['temp_DTI'] = $DTI_target_file;
            } else {
                $DTIErr = "Error uploading DTI file.";
            }
        }
    } elseif (empty($_SESSION['temp_DTI'])) {
        $DTIErr = "Required!";
    }

    if (!empty($_FILES["business_permit"]["name"])) {
        $target_dir = "temp/";
        if (!is_dir($target_dir)) {
            mkdir($target_dir, 0755, true);
        }
        $business_permit_target_file = $target_dir . basename($_FILES["business_permit"]["name"]);
        $imageFileType = strtolower(pathinfo($business_permit_target_file, PATHINFO_EXTENSION));
        $uploadOk = 1;

        if (file_exists($business_permit_target_file)) {
            $business_permit_target_file = $target_dir . rand(1,9) . rand(1,9) . rand(1,9) . rand(1,9) . "_" . basename($_FILES["business_permit"]["name"]);
        }

        if ($_FILES["business_permit"]["size"] > 5000000) {
            $business_permitErr = "File too large. Max 5MB.";
            $uploadOk = 0;
        }

        if ($imageFileType != "jpg" && $imageFileType != "jpeg" && $imageFileType != "png" && $imageFileType != "gif") {
            $business_permitErr = "Only JPG, JPEG, PNG, GIF allowed.";
            $uploadOk = 0;
        }

        if ($uploadOk == 1) {
            if (move_uploaded_file($_FILES["business_permit"]["tmp_name"], $business_permit_target_file)) {
                if (!empty($_SESSION['temp_business_permit']) && file_exists($_SESSION['temp_business_permit'])) {
                    unlink($_SESSION['temp_business_permit']);
                }
                $_SESSION['temp_business_permit'] = $business_permit_target_file;
            } else {
                $business_permitErr = "Error uploading business permit file.";
            }
        }
    } elseif (empty($_SESSION['temp_business_permit'])) {
        $business_permitErr = "Required!";
    }

    if ($first_name) {
        if (!preg_match("/^[a-zA-Z-' ]*$/", $first_name)) {
            $first_nameErr = "wag ka jejemon";
        } else {
            if (strlen($first_name) < 2) {
                $first_nameErr = "masyadong maikli name mo";
            } else {
                if ($middle_name) {
                    if (!preg_match("/^[a-zA-Z-' ]*$/", $middle_name)) {
                        $middle_nameErr = "wag ka jejemon";
                    } else {
                        if (strlen($middle_name) < 2) {
                            $middle_nameErr = "masyadong maikli middle name mo";
                        } else {
                            if ($last_name) {
                                if (!preg_match("/^[a-zA-Z-' ]*$/", $last_name)) {
                                    $last_nameErr = "wag ka jejemon";
                                } else {
                                    if (strlen($last_name) < 2) {
                                        $last_nameErr = "masyado maikli last name mo";
                                    } else {
                                        if ($birthday) {
                                            if (!preg_match("/^\d{4}-\d{2}-\d{2}$/", $birthday)) {
                                                $birthdayErr = "Invalid date format!";
                                            } else {
                                                $birth_date = new DateTime($birthday);
                                                $today = new DateTime('2025-10-22');
                                                $age = $today->diff($birth_date)->y;
                                                if ($age < 18) {
                                                    $birthdayErr = "you are too young";
                                                } else {
                                                    if ($birth_place) {
                                                        if (!preg_match("/^[a-zA-Z\s]*$/", $birth_place)) {
                                                            $birth_placeErr = "Letters and spaces only";
                                                        } else {
                                                            if (strlen($birth_place) < 2) {
                                                                $birth_placeErr = "masyadong maikli birth place mo";
                                                            } else {
                                                                if ($email) {
                                                                    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                                                                        $emailErr = "Invalid email format";
                                                                    } else {
                                                                        if ($seven_digit) {
                                                                            if (!preg_match("/^\d{7}$/", $seven_digit)) {
                                                                                $seven_digitErr = "Seven digit nga diba?";
                                                                            } else {
                                                                                if ($password && $cpassword) {
                                                                                    if ($password !== $cpassword) {
                                                                                        $cpasswordErr = "Passwords do not match";
                                                                                    } else {
                                                                                        if (strlen($password) < 2) {
                                                                                            $passwordErr = "masyadong maikli password mo";
                                                                                        } else {
                                                                                            if ($establishment_name) {
                                                                                                if (!preg_match("/^[a-zA-Z\s-]*$/", $establishment_name)) {
                                                                                                    $establishment_nameErr = "Letters, spaces, or hyphens only";
                                                                                                } else {
                                                                                                    if (strlen($establishment_name) < 2) {
                                                                                                        $establishment_nameErr = "masyadong maikli name mo";
                                                                                                    }
                                                                                                }
                                                                                            }
                                                                                        }
                                                                                    }
                                                                                }
                                                                            }
                                                                        }
                                                                    }
                                                                }
                                                            }
                                                        }
                                                    }
                                                }
                                            }
                                        }
                                    }
                                }
                            }
                        }
                    }
                }
            }
        }
    }

    if (
        empty($first_nameErr) && empty($middle_nameErr) && empty($last_nameErr) &&
        empty($birthdayErr) && empty($birth_placeErr) && empty($cityErr) &&
        empty($barangayErr) && empty($lot_streetErr) && empty($prefixErr) &&
        empty($seven_digitErr) && empty($emailErr) && empty($passwordErr) &&
        empty($cpasswordErr) && empty($establishment_nameErr) && empty($capitalErr) &&
        empty($date_of_establishmentErr) && empty($business_typeErr) &&
        empty($nature_of_businessErr) && empty($sabang_locationErr) &&
        empty($lot_street_businessErr) && empty($DTIErr) && empty($business_permitErr)
    ) {
        $DTI = $_SESSION['temp_DTI'];
        $business_permit = $_SESSION['temp_business_permit'];

        if (!empty($DTI) && file_exists($DTI) && !empty($business_permit) && file_exists($business_permit)) {
            $pending_query = "INSERT INTO tbl_pending_users(first_name, middle_name, last_name, birthday, birth_place, city, barangay, lot_street, prefix, seven_digit, email, password, account_type, img, establishment_name, capital, date_of_establishment, business_type, nature_of_business, sabang_location, lot_street_business, DTI, business_permit, enterprise_type) 
                             VALUES ('$first_name', '$middle_name', '$last_name', '$birthday', '$birth_place', '$city', '$barangay', '$lot_street', '$prefix', '$seven_digit', '$email', '$password', 'pending', '', '$establishment_name', '$capital', '$date_of_establishment', '$business_type', '$nature_of_business', '$sabang_location', '$lot_street_business', '$DTI', '$business_permit', '$enterprise_type')";
            if (mysqli_query($connections, $pending_query)) {
                $_SESSION['temp_DTI'] = '';
                $_SESSION['temp_business_permit'] = '';
                echo "<script>window.location.href='success.php';</script>";
            } else {
                error_log("register.php: Database insert failed: " . mysqli_error($connections));
                $DTIErr = "Registration failed. Please try again.";
            }
        } else {
            if (empty($DTI) || !file_exists($DTI)) {
                $DTIErr = "DTI file missing.";
            }
            if (empty($business_permit) || !file_exists($business_permit)) {
                $business_permitErr = "Business permit file missing.";
            }
        }
    }
}

if (isset($_POST['replace_DTI'])) {
    if (!empty($_SESSION['temp_DTI']) && file_exists($_SESSION['temp_DTI'])) {
        unlink($_SESSION['temp_DTI']);
        $_SESSION['temp_DTI'] = '';
    }
}
if (isset($_POST['replace_business_permit'])) {
    if (!empty($_SESSION['temp_business_permit']) && file_exists($_SESSION['temp_business_permit'])) {
        unlink($_SESSION['temp_business_permit']);
        $_SESSION['temp_business_permit'] = '';
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register</title>
    <link rel="stylesheet" href="auth.css">
</head>
<body>
    <div class="blur-orb blur-orb-1"></div>
    <div class="blur-orb blur-orb-2"></div>
    <div class="blur-orb blur-orb-3"></div>
    
    <div class="auth-container register">
        <div class="auth-header">
            <div class="logo">
                <img src="sabang_logo.png" alt="Logo">
            </div>
            <h2>Business Registration</h2>
            <p>Create your account to start your journey</p>
        </div>
        
        <form method="POST" enctype="multipart/form-data" class="auth-form">
            <div class="form-section">
                <h3>Personal Information</h3>
                <div class="form-row">
                    <div class="form-group third-width">
                        <label class="form-label" for="first_name">First Name</label>
                        <input type="text" name="first_name" id="first_name" class="form-input" placeholder="First Name" value="<?php echo htmlspecialchars($first_name); ?>">
                        <span class="error-message"><?php echo $first_nameErr; ?></span>
                    </div>
                    <div class="form-group third-width">
                        <label class="form-label" for="middle_name">Middle Name</label>
                        <input type="text" name="middle_name" id="middle_name" class="form-input" placeholder="Middle Name" value="<?php echo htmlspecialchars($middle_name); ?>">
                        <span class="error-message"><?php echo $middle_nameErr; ?></span>
                    </div>
                    <div class="form-group third-width">
                        <label class="form-label" for="last_name">Last Name</label>
                        <input type="text" name="last_name" id="last_name" class="form-input" placeholder="Last Name" value="<?php echo htmlspecialchars($last_name); ?>">
                        <span class="error-message"><?php echo $last_nameErr; ?></span>
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group half-width">
                        <label class="form-label" for="birthday">Birthday</label>
                        <input type="date" name="birthday" id="birthday" class="form-input" value="<?php echo htmlspecialchars($birthday); ?>">
                        <span class="error-message"><?php echo $birthdayErr; ?></span>
                    </div>
                    <div class="form-group half-width">
                        <label class="form-label" for="birth_place">Birth Place</label>
                        <input type="text" name="birth_place" id="birth_place" class="form-input" placeholder="Birth Place" value="<?php echo htmlspecialchars($birth_place); ?>">
                        <span class="error-message"><?php echo $birth_placeErr; ?></span>
                    </div>
                </div>
            </div>

            <div class="form-section">
                <h3>Current Address</h3>
                <div class="form-row">
                    <div class="form-group half-width">
                        <label class="form-label" for="city">City</label>
                        <select name="city" id="city" class="form-select" onchange="updateBarangays('city', 'barangay')">
                            <option value="" <?php if (empty($city)) { echo "selected"; } ?>>Select City</option>
                            <option value="Lipa City" <?php if ($city == "Lipa City") { echo "selected"; } ?>>Lipa City</option>
                            <option value="Tanauan City" <?php if ($city == "Tanauan City") { echo "selected"; } ?>>Tanauan City</option>
                            <option value="Santo Tomas City" <?php if ($city == "Santo Tomas City") { echo "selected"; } ?>>Santo Tomas City</option>
                            <option value="Batangas City" <?php if ($city == "Batangas City") { echo "selected"; } ?>>Batangas City</option>
                            <option value="San Jose" <?php if ($city == "San Jose") { echo "selected"; } ?>>San Jose</option>
                        </select>
                        <span class="error-message"><?php echo $cityErr; ?></span>
                    </div>
                    <div class="form-group half-width">
                        <label class="form-label" for="barangay">Barangay</label>
                        <select name="barangay" id="barangay" class="form-select">
                            <option value="" <?php if (empty($barangay)) { echo "selected"; } ?>>Select Barangay</option>
                            <?php
                            $barangays = [
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
                            if (!empty($city) && isset($barangays[$city])) {
                                foreach ($barangays[$city] as $brgy) {
                                    echo "<option value=\"$brgy\" " . ($barangay == $brgy ? "selected" : "") . ">$brgy</option>";
                                }
                            }
                            ?>
                        </select>
                        <span class="error-message"><?php echo $barangayErr; ?></span>
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group full-width">
                        <label class="form-label" for="lot_street">Lot/Street</label>
                        <input type="text" name="lot_street" id="lot_street" class="form-input" placeholder="Lot/Street (e.g., Block 5 Lot 10 or Main St.)" value="<?php echo htmlspecialchars($lot_street); ?>">
                        <span class="error-message"><?php echo $lot_streetErr; ?></span>
                    </div>
                </div>
            </div>

            <div class="form-section">
                <h3>Contact Information</h3>
                <div class="form-row">
                    <div class="form-group half-width">
                        <label class="form-label" for="prefix">Network Prefix</label>
                        <select name="prefix" id="prefix" class="form-select">
                            <option value="" <?php if (empty($prefix)) { echo "selected"; } ?>>Select Network Provider</option>
                            <option value="0813" <?php if ($prefix == "0813") { echo "selected"; } ?>>0813</option>
                            <option value="0817" <?php if ($prefix == "0817") { echo "selected"; } ?>>0817</option>
                            <option value="0905" <?php if ($prefix == "0905") { echo "selected"; } ?>>0905</option>
                            <option value="0906" <?php if ($prefix == "0906") { echo "selected"; } ?>>0906</option>
                            <option value="0907" <?php if ($prefix == "0907") { echo "selected"; } ?>>0907</option>
                        </select>
                        <span class="error-message"><?php echo $prefixErr; ?></span>
                    </div>
                    <div class="form-group half-width">
                        <label class="form-label" for="seven_digit">Phone Number (7 Digits)</label>
                        <input type="text" name="seven_digit" id="seven_digit" class="form-input" placeholder="7-Digit Number" onkeypress="return isNumberKey(event)" value="<?php echo htmlspecialchars($seven_digit); ?>" maxlength="7">
                        <span class="error-message"><?php echo $seven_digitErr; ?></span>
                    </div>
                </div>
            </div>

            <div class="form-section">
                <h3>Business Information</h3>
                <div class="form-row">
                    <div class="form-group half-width">
                        <label class="form-label" for="establishment_name">Establishment Name</label>
                        <input type="text" name="establishment_name" id="establishment_name" class="form-input" placeholder="Establishment Name" value="<?php echo htmlspecialchars($establishment_name); ?>">
                        <span class="error-message"><?php echo $establishment_nameErr; ?></span>
                    </div>
                    <div class="form-group half-width">
                        <label class="form-label" for="capital">Capital</label>
                        <input type="text" name="capital" id="capital" class="form-input" placeholder="Capital" onkeypress="return isNumberKey(event)" value="<?php echo htmlspecialchars($capital); ?>">
                        <span class="error-message"><?php echo $capitalErr; ?></span>
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group full-width">
                        <label class="form-label" for="date_of_establishment">Date of Establishment</label>
                        <input type="date" name="date_of_establishment" id="date_of_establishment" class="form-input" value="<?php echo htmlspecialchars($date_of_establishment); ?>">
                        <span class="error-message"><?php echo $date_of_establishmentErr; ?></span>
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group half-width">
                        <label class="form-label" for="business_type">Business Type</label>
                        <select name="business_type" id="business_type" class="form-select">
                            <option value="" <?php if (empty($business_type)) { echo "selected"; } ?>>Select Business Type</option>
                            <option value="Retail" <?php if ($business_type == "Retail") { echo "selected"; } ?>>Retail</option>
                            <option value="Service" <?php if ($business_type == "Service") { echo "selected"; } ?>>Service</option>
                            <option value="Manufacturing" <?php if ($business_type == "Manufacturing") { echo "selected"; } ?>>Manufacturing</option>
                        </select>
                        <span class="error-message"><?php echo $business_typeErr; ?></span>
                    </div>
                    <div class="form-group half-width">
                        <label class="form-label" for="nature_of_business">Nature of Business</label>
                        <select name="nature_of_business" id="nature_of_business" class="form-select">
                            <option value="" <?php if (empty($nature_of_business)) { echo "selected"; } ?>>Select Nature of Business</option>
                            <option value="Food and Drinks" <?php if ($nature_of_business == "Food and Drinks") { echo "selected"; } ?>>Food and Drinks</option>
                            <option value="Clothing and Accessories" <?php if ($nature_of_business == "Clothing and Accessories") { echo "selected"; } ?>>Clothing and Accessories</option>
                            <option value="Electronics and Gadgets" <?php if ($nature_of_business == "Electronics and Gadgets") { echo "selected"; } ?>>Electronics and Gadgets</option>
                            <option value="Personal Care" <?php if ($nature_of_business == "Personal Care") { echo "selected"; } ?>>Personal Care</option>
                            <option value="Home and Living" <?php if ($nature_of_business == "Home and Living") { echo "selected"; } ?>>Home and Living</option>
                            <option value="Health and Wellness" <?php if ($nature_of_business == "Health and Wellness") { echo "selected"; } ?>>Health and Wellness</option>
                            <option value="General Retail" <?php if ($nature_of_business == "General Retail") { echo "selected"; } ?>>General Retail</option>
                        </select>
                        <span class="error-message"><?php echo $nature_of_businessErr; ?></span>
                    </div>
                </div>
            </div>

            <div class="form-section">
                <h3>Business Address</h3>
                <div class="form-row">
                    <div class="form-group half-width">
                        <label class="form-label" for="sabang_location">Location in Lipa City</label>
                        <select name="sabang_location" id="sabang_location" class="form-select">
                            <option value="" <?php if (empty($sabang_location)) { echo "selected"; } ?>>Select Location in Lipa City</option>
                            <option value="Purok 1" <?php if ($sabang_location == "Purok 1") { echo "selected"; } ?>>Purok 1</option>
                            <option value="Purok 2" <?php if ($sabang_location == "Purok 2") { echo "selected"; } ?>>Purok 2</option>
                            <option value="Purok 3" <?php if ($sabang_location == "Purok 3") { echo "selected"; } ?>>Purok 3</option>
                            <option value="Purok 4" <?php if ($sabang_location == "Purok 4") { echo "selected"; } ?>>Purok 4</option>
                            <option value="Purok 5" <?php if ($sabang_location == "Purok 5") { echo "selected"; } ?>>Purok 5</option>
                            <option value="Purok 6" <?php if ($sabang_location == "Purok 6") { echo "selected"; } ?>>Purok 6</option>
                            <option value="Purok 7" <?php if ($sabang_location == "Purok 7") { echo "selected"; } ?>>Purok 7</option>
                            <option value="Purok 8" <?php if ($sabang_location == "Purok 8") { echo "selected"; } ?>>Purok 8</option>
                            <option value="Purok 9" <?php if ($sabang_location == "Purok 9") { echo "selected"; } ?>>Purok 9</option>
                            <option value="Purok 10" <?php if ($sabang_location == "Purok 10") { echo "selected"; } ?>>Purok 10</option>
                            <option value="Purok 11" <?php if ($sabang_location == "Purok 11") { echo "selected"; } ?>>Purok 11</option>
                            <option value="Purok 12" <?php if ($sabang_location == "Purok 12") { echo "selected"; } ?>>Purok 12</option>
                            <option value="Purok 13" <?php if ($sabang_location == "Purok 13") { echo "selected"; } ?>>Purok 13</option>
                            <option value="Purok 14" <?php if ($sabang_location == "Purok 14") { echo "selected"; } ?>>Purok 14</option>
                            <option value="Purok 15" <?php if ($sabang_location == "Purok 15") { echo "selected"; } ?>>Purok 15</option>
                            <option value="Purok 16" <?php if ($sabang_location == "Purok 16") { echo "selected"; } ?>>Purok 16</option>
                            <option value="Purok 17" <?php if ($sabang_location == "Purok 17") { echo "selected"; } ?>>Purok 17</option>
                            <option value="Purok 18" <?php if ($sabang_location == "Purok 18") { echo "selected"; } ?>>Purok 18</option>
                            <option value="Purok 19" <?php if ($sabang_location == "Purok 19") { echo "selected"; } ?>>Purok 19</option>
                            <option value="Purok 20" <?php if ($sabang_location == "Purok 20") { echo "selected"; } ?>>Purok 20</option>
                            <option value="Purok 21" <?php if ($sabang_location == "Purok 21") { echo "selected"; } ?>>Purok 21</option>
                            <option value="Purok 22" <?php if ($sabang_location == "Purok 22") { echo "selected"; } ?>>Purok 22</option>
                            <option value="Purok 23" <?php if ($sabang_location == "Purok 23") { echo "selected"; } ?>>Purok 23</option>
                            <option value="Purok 24" <?php if ($sabang_location == "Purok 24") { echo "selected"; } ?>>Purok 24</option>
                            <option value="Purok 25" <?php if ($sabang_location == "Purok 25") { echo "selected"; } ?>>Purok 25</option>
                            <option value="Purok 26" <?php if ($sabang_location == "Purok 26") { echo "selected"; } ?>>Purok 26</option>
                        </select>
                        <span class="error-message"><?php echo $sabang_locationErr; ?></span>
                    </div>
                    <div class="form-group half-width">
                        <label class="form-label" for="lot_street_business">Lot/Street</label>
                        <input type="text" name="lot_street_business" id="lot_street_business" class="form-input" placeholder="Lot/Street (e.g., Block 5 Lot 10 or Main St.)" value="<?php echo htmlspecialchars($lot_street_business); ?>">
                        <span class="error-message"><?php echo $lot_street_businessErr; ?></span>
                    </div>
                </div>
            </div>

            <div class="form-section">
                <h3>Documents</h3>
                <div class="file-upload-section">
                    <label class="file-upload-label">DTI Permit</label>
                    <?php if (!empty($_SESSION['temp_DTI']) && file_exists($_SESSION['temp_DTI'])) { ?>
                        <div class="file-status success">
                            Selected: <?php echo htmlspecialchars(basename($_SESSION['temp_DTI'])); ?>
                            <button type="submit" name="replace_DTI" class="btn-secondary">Replace DTI File</button>
                        </div>
                    <?php } else { ?>
                        <input type="file" name="DTI" id="DTI" class="file-input" accept="image/jpeg,image/png,image/gif">
                        <span class="error-message"><?php echo $DTIErr; ?></span>
                    <?php } ?>
                </div>
                <div class="file-upload-section">
                    <label class="file-upload-label">Business Permit</label>
                    <?php if (!empty($_SESSION['temp_business_permit']) && file_exists($_SESSION['temp_business_permit'])) { ?>
                        <div class="file-status success">
                            Selected: <?php echo htmlspecialchars(basename($_SESSION['temp_business_permit'])); ?>
                            <button type="submit" name="replace_business_permit" class="btn-secondary">Replace Business Permit File</button>
                        </div>
                    <?php } else { ?>
                        <input type="file" name="business_permit" id="business_permit" class="file-input" accept="image/jpeg,image/png,image/gif">
                        <span class="error-message"><?php echo $business_permitErr; ?></span>
                    <?php } ?>
                </div>
            </div>

            <div class="form-section">
                <h3>Account Information</h3>
                <div class="form-row">
                    <div class="form-group full-width">
                        <label class="form-label" for="email">Email</label>
                        <input type="text" name="email" id="email" class="form-input" placeholder="Email" value="<?php echo htmlspecialchars($email); ?>">
                        <span class="error-message"><?php echo $emailErr; ?></span>
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group half-width">
                        <label class="form-label" for="password">Password</label>
                        <input type="password" name="password" id="password" class="form-input" placeholder="Password">
                        <span class="error-message"><?php echo $passwordErr; ?></span>
                    </div>
                    <div class="form-group half-width">
                        <label class="form-label" for="cpassword">Confirm Password</label>
                        <input type="password" name="cpassword" id="cpassword" class="form-input" placeholder="Confirm Password">
                        <span class="error-message"><?php echo $cpasswordErr; ?></span>
                    </div>
                </div>
            </div>

            <button type="submit" name="btnRegister" class="auth-btn">Register</button>
        </form>

        <div class="auth-footer">
            <a href="login.php">Already have an account? Login here</a>
            <span class="divider">|</span>
            <a href="index.php">Back to Home</a>
        </div>
    </div>

    <script type="application/javascript">
        function isNumberKey(evt) {
            var charCode = (evt.which) ? evt.which : event.keyCode;
            if (charCode > 31 && (charCode < 48 || charCode > 57) && charCode != 44) // Allow comma (44)
                return false;
            return true;
        }

        function updateBarangays(cityId, barangayId) {
            var citySelect = document.getElementById(cityId);
            var barangaySelect = document.getElementById(barangayId);
            var selectedBarangay = '<?php echo addslashes($barangay); ?>';

            barangaySelect.innerHTML = '<option value="">Select Barangay</option>';

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

            var selectedCity = citySelect.value;
            if (selectedCity && barangays[selectedCity]) {
                barangays[selectedCity].forEach(function(barangay) {
                    var option = document.createElement('option');
                    option.value = barangay;
                    option.text = barangay;
                    if (barangay === selectedBarangay) {
                        option.selected = true;
                    }
                    barangaySelect.appendChild(option);
                });
            }
        }
    </script>
</body>
</html>