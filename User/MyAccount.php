<?php
date_default_timezone_set('Asia/Manila');

include("../connections.php");

// === CONFIGURATION ===
// Change this to the correct user ID that owns the account/business
$user_id = 15; // <-- UPDATE THIS IF YOUR MAIN USER HAS A DIFFERENT ID

// Fetch user info
$query_info = mysqli_query($connections, "SELECT * FROM tbl_user WHERE id_user='$user_id'");
if (!$query_info || mysqli_num_rows($query_info) == 0) {
    die("User not found. Please check the \$user_id in MyAccount.php.");
}
$my_info = mysqli_fetch_assoc($query_info);

// Get business information
$business_query = mysqli_query($connections, "SELECT * FROM tbl_business WHERE id_user='$user_id'");
$business_data = mysqli_fetch_assoc($business_query);
$business_name = $business_data ? htmlspecialchars($business_data['establishment_name']) : 'SME';

$target_dir = "photo_folder/";
$profileUploadErr = "";
$first_nameErr = $middle_nameErr = $last_nameErr = $birthdayErr = $birth_placeErr = $cityErr = $barangayErr = $lot_streetErr = $prefixErr = $seven_digitErr = "";
$passwordErr = $confirm_passwordErr = "";
$notify = isset($_GET["notify"]) ? $_GET["notify"] : "";

// Handle profile picture upload
if (isset($_POST["btnUpload"])) {
    if (isset($_FILES["profile_pic"]) && $_FILES["profile_pic"]["error"] == 0) {
        if (!is_dir($target_dir)) {
            mkdir($target_dir, 0755, true);
        }
        $target_file = $target_dir . basename($_FILES["profile_pic"]["name"]);
        $uploadOk = 1;
        $imageFileType = strtolower(pathinfo($target_file, PATHINFO_EXTENSION));

        if (file_exists($target_file)) {
            $target_file = $target_dir . rand(1000,9999) . "_" . basename($_FILES["profile_pic"]["name"]);
        }

        if ($_FILES["profile_pic"]["size"] > 5000000) {
            $profileUploadErr = "File too large (max 5MB).";
            $uploadOk = 0;
        }

        if (!in_array($imageFileType, ['jpg', 'png', 'jpeg', 'gif'])) {
            $profileUploadErr = "Only JPG, JPEG, PNG & GIF files allowed.";
            $uploadOk = 0;
        }

        if ($uploadOk == 1) {
            if (move_uploaded_file($_FILES["profile_pic"]["tmp_name"], $target_file)) {
                $target_file_escaped = mysqli_real_escape_string($connections, $target_file);
                $query = mysqli_query($connections, "UPDATE tbl_user SET img='$target_file_escaped' WHERE id_user='$user_id'");
                if ($query) {
                    $notify = "Profile photo uploaded successfully!";
                    echo "<script>window.location.href='MyAccount.php?notify=" . urlencode($notify) . "';</script>";
                    exit;
                } else {
                    $profileUploadErr = "Database error: " . mysqli_error($connections);
                }
            } else {
                $profileUploadErr = "Error moving uploaded file.";
            }
        }
    } else {
        $profileUploadErr = "Please upload a valid file.";
    }
}

// Handle profile update
if (isset($_POST["btnUpdate"])) {
    $first_name = mysqli_real_escape_string($connections, $_POST["first_name"] ?? '');
    $middle_name = mysqli_real_escape_string($connections, $_POST["middle_name"] ?? '');
    $last_name = mysqli_real_escape_string($connections, $_POST["last_name"] ?? '');
    $birthday = mysqli_real_escape_string($connections, $_POST["birthday"] ?? '');
    $birth_place = mysqli_real_escape_string($connections, $_POST["birth_place"] ?? '');
    $city = mysqli_real_escape_string($connections, $_POST["city"] ?? '');
    $barangay = mysqli_real_escape_string($connections, $_POST["barangay"] ?? '');
    $lot_street = mysqli_real_escape_string($connections, $_POST["lot_street"] ?? '');
    $prefix = mysqli_real_escape_string($connections, $_POST["prefix"] ?? '');
    $seven_digit = mysqli_real_escape_string($connections, $_POST["seven_digit"] ?? '');

    $first_nameErr = empty($_POST["first_name"]) ? "First name is required." : "";
    $middle_nameErr = empty($_POST["middle_name"]) ? "Middle name is required." : "";
    $last_nameErr = empty($_POST["last_name"]) ? "Last name is required." : "";
    $birthdayErr = empty($_POST["birthday"]) ? "Birthday is required." : "";
    $birth_placeErr = empty($_POST["birth_place"]) ? "Birth place is required." : "";
    $cityErr = empty($_POST["city"]) ? "City is required." : "";
    $barangayErr = empty($_POST["barangay"]) ? "Barangay is required." : "";
    $lot_streetErr = empty($_POST["lot_street"]) ? "Street address is required." : "";
    $prefixErr = empty($_POST["prefix"]) ? "Phone prefix is required." : "";
    $seven_digitErr = empty($_POST["seven_digit"]) ? "Phone number is required." : "";

    if (empty($first_nameErr) && empty($middle_nameErr) && empty($last_nameErr) && empty($birthdayErr) && empty($birth_placeErr) && empty($cityErr) && empty($barangayErr) && empty($lot_streetErr) && empty($prefixErr) && empty($seven_digitErr)) {
        $update_query = mysqli_query($connections, "UPDATE tbl_user SET 
            first_name='$first_name',
            middle_name='$middle_name', 
            last_name='$last_name',
            birthday='$birthday',
            birth_place='$birth_place',
            city='$city',
            barangay='$barangay',
            lot_street='$lot_street',
            prefix='$prefix',
            seven_digit='$seven_digit'
            WHERE id_user='$user_id'");

        if ($update_query) {
            $notify = "Profile updated successfully!";
            echo "<script>window.location.href='MyAccount.php?notify=" . urlencode($notify) . "';</script>";
            exit;
        } else {
            $notify = "Error updating profile: " . mysqli_error($connections);
        }
    }
}

// Handle password reset
if (isset($_POST["btnResetPassword"])) {
    $password = $_POST["password"] ?? '';
    $confirm_password = $_POST["confirm_password"] ?? '';

    if (empty($password)) {
        $passwordErr = "Password is required.";
    } else if (strlen($password) < 6) {
        $passwordErr = "Password must be at least 6 characters.";
    }

    if (empty($confirm_password)) {
        $confirm_passwordErr = "Confirm password is required.";
    } else if ($password !== $confirm_password) {
        $confirm_passwordErr = "Passwords do not match.";
    }

    if (empty($passwordErr) && empty($confirm_passwordErr)) {
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
        $update_query = mysqli_query($connections, "UPDATE tbl_user SET password='$hashed_password' WHERE id_user='$user_id'");
        if ($update_query) {
            $notify = "Password reset successfully!";
            echo "<script>window.location.href='MyAccount.php?notify=" . urlencode($notify) . "';</script>";
            exit;
        } else {
            $notify = "Error resetting password: " . mysqli_error($connections);
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $business_name; ?> Dashboard</title>
    <link rel="stylesheet" href="user-dashboard.css">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .error-message { color: red; font-size: 0.9em; }
    </style>
</head>
<body>
    <!-- Navigation -->
    <nav class="navbar">
        <div class="nav-container">
            <div class="nav-brand">
                <img src="../sabang_logo.png" alt="SME Logo" class="nav-logo">
                <span class="nav-title"><?php echo $business_name; ?> Dashboard</span>
            </div>
            <div class="nav-user">
                <div class="user-info">
                    <i class="fas fa-user-circle"></i>
                    <span class="user-name"><?php echo htmlspecialchars($my_info['first_name'] . ' ' . $my_info['last_name']); ?></span>
                </div>
                <!-- Logout removed - standalone system -->
            </div>
        </div>
    </nav>

    <div class="dashboard-container">
        <aside class="sidebar">
            <div class="sidebar-header">
                <h3><i class="fas fa-tachometer-alt"></i> Menu</h3>
            </div>
            <nav class="sidebar-nav">
                <a href="index.php" class="nav-item <?php echo basename($_SERVER['PHP_SELF']) == 'index.php' ? 'active' : ''; ?>">
                    <i class="fas fa-home"></i>
                    <span>Dashboard</span>
                </a>
                <a href="MyAccount.php" class="nav-item <?php echo basename($_SERVER['PHP_SELF']) == 'MyAccount.php' ? 'active' : ''; ?>">
                    <i class="fas fa-user"></i>
                    <span>My Account</span>
                </a>
                <a href="view_stock.php" class="nav-item <?php echo basename($_SERVER['PHP_SELF']) == 'view_stock.php' ? 'active' : ''; ?>">
                    <i class="fas fa-boxes"></i>
                    <span>View Stock</span>
                </a>
                <a href="adjust_stock.php" class="nav-item <?php echo basename($_SERVER['PHP_SELF']) == 'adjust_stock.php' ? 'active' : ''; ?>">
                    <i class="fas fa-edit"></i>
                    <span>Adjust Stock</span>
                </a>
                <a href="add_item.php" class="nav-item <?php echo basename($_SERVER['PHP_SELF']) == 'add_item.php' ? 'active' : ''; ?>">
                    <i class="fas fa-plus-circle"></i>
                    <span>Add Item</span>
                </a>
                <a href="planner.php" class="nav-item <?php echo basename($_SERVER['PHP_SELF']) == 'planner.php' ? 'active' : ''; ?>">
                    <i class="fas fa-calendar-alt"></i>
                    <span>Calendar</span>
                </a>
                <a href="transaction.php" class="nav-item <?php echo basename($_SERVER['PHP_SELF']) == 'transaction.php' ? 'active' : ''; ?>">
                    <i class="fas fa-receipt"></i>
                    <span>Transactions</span>
                </a>
            </nav>
        </aside>

        <main class="main-content">
            <div class="page-header">
                <div class="page-title">
                    <h1><i class="fas fa-user"></i> My Account</h1>
                    <p>Manage your profile and account settings</p>
                </div>
                <?php if ($notify): ?>
                <div class="notification success">
                    <i class="fas fa-check-circle"></i>
                    <?php echo htmlspecialchars($notify); ?>
                </div>
                <?php endif; ?>
            </div>

            <div class="account-grid">
                <!-- Profile Picture Section -->
                <div class="profile-section">
                    <div class="profile-card">
                        <div class="profile-header">
                            <h3><i class="fas fa-camera"></i> Profile Picture</h3>
                        </div>
                        <div class="profile-content">
                            <div class="current-photo">
                                <?php if (!empty($my_info['img'])): ?>
                                    <img src="<?php echo htmlspecialchars($my_info['img']); ?>" alt="Profile Picture" class="profile-img">
                                <?php else: ?>
                                    <div class="no-photo">
                                        <i class="fas fa-user-circle"></i>
                                        <p>No photo uploaded</p>
                                    </div>
                                <?php endif; ?>
                            </div>
                            <form method="POST" enctype="multipart/form-data" class="upload-form">
                                <div class="file-input-wrapper">
                                    <input type="file" name="profile_pic" id="profile_pic" accept="image/*" required>
                                    <label for="profile_pic" class="file-input-label">
                                        <i class="fas fa-cloud-upload-alt"></i>
                                        Choose Photo
                                    </label>
                                </div>
                                <?php if ($profileUploadErr): ?>
                                    <div class="error-message"><?php echo htmlspecialchars($profileUploadErr); ?></div>
                                <?php endif; ?>
                                <button type="submit" name="btnUpload" class="btn-upload">
                                    <i class="fas fa-upload"></i>
                                    Upload Photo
                                </button>
                            </form>
                        </div>
                    </div>
                </div>

                <!-- Business Information -->
                <?php if ($business_data): ?>
                <div class="business-section">
                    <div class="business-card">
                        <div class="business-header">
                            <h3><i class="fas fa-building"></i> Business Information</h3>
                        </div>
                        <div class="business-content">
                            <div class="business-grid">
                                <div class="business-item">
                                    <label>Business Name</label>
                                    <span><?php echo htmlspecialchars($business_data['establishment_name']); ?></span>
                                </div>
                                <div class="business-item">
                                    <label>Enterprise Type</label>
                                    <span><?php echo htmlspecialchars($business_data['enterprise_type'] ?? 'Not Specified'); ?></span>
                                </div>
                                <div class="business-item">
                                    <label>Capital</label>
                                    <span>₱<?php echo number_format(floatval($business_data['capital']), strpos($business_data['capital'], '.') !== false ? 2 : 0); ?></span>
                                </div>
                                <div class="business-item">
                                    <label>Date Established</label>
                                    <span><?php echo date('F d, Y', strtotime($business_data['date_of_establishment'])); ?></span>
                                </div>
                                <div class="business-item full-width">
                                    <label>Nature of Business</label>
                                    <span><?php echo htmlspecialchars($business_data['nature_of_business']); ?></span>
                                </div>
                                <div class="business-item full-width">
                                    <label>Location</label>
                                    <span><?php echo htmlspecialchars($business_data['sabang_location'] . ', ' . $business_data['lot_street_business']); ?></span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Personal Information -->
                <div class="info-section">
                    <div class="info-card">
                        <div class="info-header">
                            <h3><i class="fas fa-user-edit"></i> Personal Information</h3>
                        </div>
                        <form method="POST" class="info-form">
                            <div class="form-grid">
                                <div class="form-group">
                                    <label class="form-label">First Name</label>
                                    <input type="text" name="first_name" class="form-input" 
                                           value="<?php echo htmlspecialchars($my_info['first_name']); ?>" required>
                                    <small class="error-message"><?php echo htmlspecialchars($first_nameErr); ?></small>
                                </div>
                                <div class="form-group">
                                    <label class="form-label">Middle Name</label>
                                    <input type="text" name="middle_name" class="form-input" 
                                           value="<?php echo htmlspecialchars($my_info['middle_name']); ?>" required>
                                    <small class="error-message"><?php echo htmlspecialchars($middle_nameErr); ?></small>
                                </div>
                                <div class="form-group">
                                    <label class="form-label">Last Name</label>
                                    <input type="text" name="last_name" class="form-input" 
                                           value="<?php echo htmlspecialchars($my_info['last_name']); ?>" required>
                                    <small class="error-message"><?php echo htmlspecialchars($last_nameErr); ?></small>
                                </div>
                                <div class="form-group">
                                    <label class="form-label">Birthday</label>
                                    <input type="date" name="birthday" class="form-input" 
                                           value="<?php echo htmlspecialchars($my_info['birthday']); ?>" required>
                                    <small class="error-message"><?php echo htmlspecialchars($birthdayErr); ?></small>
                                </div>
                                <div class="form-group full-width">
                                    <label class="form-label">Birth Place</label>
                                    <input type="text" name="birth_place" class="form-input" 
                                           value="<?php echo htmlspecialchars($my_info['birth_place']); ?>" required>
                                    <small class="error-message"><?php echo htmlspecialchars($birth_placeErr); ?></small>
                                </div>
                                <div class="form-group">
                                    <label class="form-label">City</label>
                                    <input type="text" name="city" class="form-input" 
                                           value="<?php echo htmlspecialchars($my_info['city']); ?>" required>
                                    <small class="error-message"><?php echo htmlspecialchars($cityErr); ?></small>
                                </div>
                                <div class="form-group">
                                    <label class="form-label">Barangay</label>
                                    <input type="text" name="barangay" class="form-input" 
                                           value="<?php echo htmlspecialchars($my_info['barangay']); ?>" required>
                                    <small class="error-message"><?php echo htmlspecialchars($barangayErr); ?></small>
                                </div>
                                <div class="form-group full-width">
                                    <label class="form-label">Street Address</label>
                                    <input type="text" name="lot_street" class="form-input" 
                                           value="<?php echo htmlspecialchars($my_info['lot_street']); ?>" required>
                                    <small class="error-message"><?php echo htmlspecialchars($lot_streetErr); ?></small>
                                </div>
                                <div class="form-group">
                                    <label class="form-label">Phone Prefix</label>
                                    <input type="text" name="prefix" class="form-input" 
                                           value="<?php echo htmlspecialchars($my_info['prefix']); ?>" required>
                                    <small class="error-message"><?php echo htmlspecialchars($prefixErr); ?></small>
                                </div>
                                <div class="form-group">
                                    <label class="form-label">Phone Number</label>
                                    <input type="text" name="seven_digit" class="form-input" 
                                           value="<?php echo htmlspecialchars($my_info['seven_digit']); ?>" required>
                                    <small class="error-message"><?php echo htmlspecialchars($seven_digitErr); ?></small>
                                </div>
                            </div>
                            <div class="form-actions">
                                <button type="submit" name="btnUpdate" class="btn-primary">
                                    <i class="fas fa-save"></i>
                                    Update Profile
                                </button>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Account Details -->
                <div class="account-details-section">
                    <div class="account-details-card">
                        <div class="account-details-header">
                            <h3><i class="fas fa-info-circle"></i> Account Details</h3>
                        </div>
                        <div class="account-details-content">
                            <div class="detail-item">
                                <label>Email Address</label>
                                <span><?php echo htmlspecialchars($my_info['email']); ?></span>
                            </div>
                            <div class="detail-item">
                                <label>Account Status</label>
                                <span class="account-type text-success">
                                    <i class="fas fa-check-circle"></i>
                                    <strong>FREE FOREVER - FULL ACCESS</strong>
                                </span>
                            </div>
                            <div class="detail-item">
                                <label>Member Since</label>
                                <span><?php echo date('F Y', strtotime($my_info['date_registered'] ?? 'now')); ?></span>
                            </div>
                            <div class="detail-item">
                                <label>Available Features</label>
                                <span class="text-success"><strong>All features permanently unlocked:</strong><br>
                                • View Stock • Adjust Stock • Add/Edit/Delete Items<br>
                                • Calendar Planner • Full Transaction & Cashier System</span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Reset Password -->
                <div class="password-section">
                    <div class="password-card">
                        <div class="password-header">
                            <h3><i class="fas fa-lock"></i> Reset Password</h3>
                        </div>
                        <div class="password-content">
                            <form method="POST" class="password-form">
                                <div class="form-grid">
                                    <div class="form-group">
                                        <label class="form-label">New Password</label>
                                        <div class="input-with-icon">
                                            <input id="new_password" type="password" name="password" class="form-input" required>
                                            <button type="button" class="toggle-password" data-target="new_password" aria-label="Toggle password">
                                                <i class="fas fa-eye"></i>
                                            </button>
                                        </div>
                                        <div class="strength-meter" aria-hidden="true">
                                            <div class="strength-bar"></div>
                                        </div>
                                        <small class="helper-text">Use at least 6 characters including letters and numbers.</small>
                                        <span class="error-message"><?php echo htmlspecialchars($passwordErr); ?></span>
                                    </div>
                                    <div class="form-group">
                                        <label class="form-label">Confirm Password</label>
                                        <div class="input-with-icon">
                                            <input id="confirm_password" type="password" name="confirm_password" class="form-input" required>
                                            <button type="button" class="toggle-password" data-target="confirm_password" aria-label="Toggle password">
                                                <i class="fas fa-eye"></i>
                                            </button>
                                        </div>
                                        <span class="error-message"><?php echo htmlspecialchars($confirm_passwordErr); ?></span>
                                    </div>
                                </div>
                                <div class="form-actions">
                                    <button type="submit" name="btnResetPassword" class="btn-primary">
                                        <i class="fas fa-save"></i>
                                        Reset Password
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <script>
        // Profile picture preview
        const profileInput = document.getElementById('profile_pic');
        if (profileInput) {
            profileInput.addEventListener('change', function(e) {
                const file = e.target.files[0];
                if (file) {
                    const fileName = file.name;
                    const label = document.querySelector('.file-input-label');
                    if (label) {
                        label.innerHTML = `<i class="fas fa-check-circle"></i> ${fileName}`;
                        label.classList.add('file-selected');
                    }
                }
            });
        }

        // Toggle password visibility
        document.querySelectorAll('.toggle-password').forEach(btn => {
            btn.addEventListener('click', () => {
                const target = document.getElementById(btn.dataset.target);
                if (!target) return;
                if (target.type === 'password') {
                    target.type = 'text';
                    btn.querySelector('i').classList.remove('fa-eye');
                    btn.querySelector('i').classList.add('fa-eye-slash');
                } else {
                    target.type = 'password';
                    btn.querySelector('i').classList.remove('fa-eye-slash');
                    btn.querySelector('i').classList.add('fa-eye');
                }
            });
        });

        // Password strength meter
        const strengthBar = document.querySelector('.strength-bar');
        const newPass = document.getElementById('new_password');
        if (newPass && strengthBar) {
            newPass.addEventListener('input', () => {
                const val = newPass.value;
                let score = 0;
                if (val.length >= 6) score++;
                if (/[A-Z]/.test(val)) score++;
                if (/[0-9]/.test(val)) score++;
                if (/[^A-Za-z0-9]/.test(val)) score++;
                const pct = (score / 4) * 100;
                strengthBar.style.width = pct + '%';
                strengthBar.style.background = score <= 1 ? '#e74c3c' : score === 2 ? '#f1c40f' : score === 3 ? '#2ecc71' : '#27ae60';
            });
        }

        // Form validation
        document.querySelectorAll('form').forEach(form => {
            form.addEventListener('submit', function(e) {
                const inputs = this.querySelectorAll('input[required]');
                let isValid = true;
                inputs.forEach(input => {
                    if (!input.value.trim()) {
                        input.classList.add('error');
                        isValid = false;
                    } else {
                        input.classList.remove('error');
                    }
                });
                if (!isValid) {
                    e.preventDefault();
                    alert('Please fill in all required fields');
                }
            });
        });
    </script>
</body>
</html>