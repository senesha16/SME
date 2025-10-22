<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

date_default_timezone_set('Asia/Manila');

if (!isset($_SESSION["email"]) || empty($_SESSION["email"])) {
    error_log("MyAccount.php: No user logged in, redirecting to login.php");
    echo "<script>window.location.href='login.php';</script>";
    exit;
}

include("../connections.php");
include("access_control.php");

$email = mysqli_real_escape_string($connections, $_SESSION["email"]);
$query_info = mysqli_query($connections, "SELECT * FROM tbl_user WHERE email='$email'");
if (!$query_info) {
    error_log("MyAccount.php: Database error: " . mysqli_error($connections));
    echo "<script>alert('Database error. Please try again later.'); window.location.href='login.php';</script>";
    exit;
}
$my_info = mysqli_fetch_assoc($query_info);
if (!$my_info) {
    error_log("MyAccount.php: User not found for email: $email");
    echo "<script>window.location.href='login.php';</script>";
    exit;
}

// Get business information
$business_query = mysqli_query($connections, "SELECT * FROM tbl_business WHERE id_user='{$my_info['id_user']}'");
$business_data = mysqli_fetch_assoc($business_query);
$business_name = $business_data ? htmlspecialchars($business_data['establishment_name']) : 'SME'; // Define $business_name with fallback

$target_dir = "photo_folder/";
$profileUploadErr = "";
$subUploadErr = "";
$first_nameErr = $middle_nameErr = $last_nameErr = $birthdayErr = $birth_placeErr = $cityErr = $barangayErr = $lot_streetErr = $prefixErr = $seven_digitErr = "";
$passwordErr = $confirm_passwordErr = "";
$notify = isset($_GET["notify"]) ? $_GET["notify"] : "";

// Trial logic
$is_trial_active = false;
$seconds_left = 0;
if (isset($my_info["trial_start_date"]) && $my_info["trial_start_date"]) {
    try {
        $trial_start = new DateTime($my_info["trial_start_date"]);
        $current_date = new DateTime();
        $trial_duration = 60; // 1 minute for testing
        $seconds_since_trial = $current_date->getTimestamp() - $trial_start->getTimestamp();
        $is_trial_active = $seconds_since_trial < $trial_duration;
        $seconds_left = $trial_duration - $seconds_since_trial;
    } catch (Exception $e) {
        error_log("MyAccount.php: DateTime error for $email: " . $e->getMessage());
    }
}

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
                $target_file = mysqli_real_escape_string($connections, $target_file);
                $query = mysqli_query($connections, "UPDATE tbl_user SET img='$target_file' WHERE email='$email'");
                if ($query) {
                    $notify = "Profile photo uploaded successfully!";
                    echo "<script>window.location.href='MyAccount.php?notify=$notify';</script>";
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
            WHERE email='$email'");

        if ($update_query) {
            $notify = "Profile updated successfully!";
            echo "<script>window.location.href='MyAccount.php?notify=$notify';</script>";
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
        $update_query = mysqli_query($connections, "UPDATE tbl_user SET password='$hashed_password' WHERE email='$email'");
        if ($update_query) {
            $notify = "Password reset successfully!";
            echo "<script>window.location.href='MyAccount.php?notify=$notify';</script>";
        } else {
            $notify = "Error resetting password: " . mysqli_error($connections);
        }
    }
}

// Handle subscription proof upload
if (isset($_POST["btnUploadProof"])) {
    $subscription_plan = $_POST["subscription_plan"] ?? '';
    if (empty($subscription_plan) || !in_array($subscription_plan, ['A', 'B', 'C'])) {
        $subUploadErr = "Please select a valid subscription plan.";
    } elseif (!isset($_FILES["subscription_proof"]) || $_FILES["subscription_proof"]["error"] != 0) {
        $subUploadErr = "Please upload a valid file.";
    } else {
        $allowed_types = ['image/jpeg', 'image/png', 'image/jpg', 'application/pdf'];
        $max_size = 5 * 1024 * 1024;
        $file_type = $_FILES["subscription_proof"]["type"];
        $file_size = $_FILES["subscription_proof"]["size"];
        $upload_dir = "subscription_proofs/";

        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0755, true);
        }

        if (!is_writable($upload_dir)) {
            $subUploadErr = "Upload directory is not writable.";
            error_log("MyAccount.php: Directory not writable: $upload_dir");
        } else {
            if (!in_array($file_type, $allowed_types)) {
                $subUploadErr = "Only JPEG, PNG, or PDF files are allowed.";
            } elseif ($file_size > $max_size) {
                $subUploadErr = "File size exceeds 5MB limit.";
            } else {
                $file_ext = strtolower(pathinfo($_FILES["subscription_proof"]["name"], PATHINFO_EXTENSION));
                $new_file_name = $my_info['id_user'] . "_subscription_proof_" . time() . "." . $file_ext;
                $target_file = $upload_dir . $new_file_name;
                if (move_uploaded_file($_FILES["subscription_proof"]["tmp_name"], $target_file)) {
                    $query = "UPDATE tbl_user SET subscription_proof='$new_file_name', requested_plan='$subscription_plan', subscription_approved=0 WHERE email='$email'";
                    if (mysqli_query($connections, $query)) {
                        $notify = "Subscription proof uploaded successfully. Awaiting approval. You can still use your current plan features.";
                        echo "<script>window.location.href='MyAccount.php?notify=" . urlencode($notify) . "';</script>";
                    } else {
                        $subUploadErr = "Database error: " . mysqli_error($connections);
                        error_log("MyAccount.php: Error saving proof for $email: " . mysqli_error($connections));
                    }
                } else {
                    $subUploadErr = "Error moving uploaded file.";
                    error_log("MyAccount.php: Failed to move file to: $target_file");
                }
            }
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
        .plan-option {
            cursor: pointer;
            transition: background-color 0.3s;
        }
        .plan-option:hover {
            background-color: #f0f0f0;
        }
        .plan-option input[type="radio"] {
            display: none;
        }
        .plan-option.plan-selected {
            background-color: #e6f3ff;
            border: 2px solid #007bff;
        }
        .plan-option input[type="radio"]:checked + .plan-info {
            background-color: #e6f3ff;
            border: 2px solid #007bff;
            border-radius: 5px;
            padding: 10px;
        }
        .error-message {
            color: red;
            font-size: 0.9em;
        }
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
                <a href="../logout.php" class="logout-btn">
                    <i class="fas fa-sign-out-alt"></i>
                    Logout
                </a>
            </div>
        </div>
    </nav>

    <!-- Dashboard Container -->
    <div class="dashboard-container">
        <!-- Sidebar -->
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
                <?php if ($is_trial_active || ($my_info['subscription_approved'] && in_array($my_info['subscription_plan'], ['B', 'C']))): ?>
                <a href="planner.php" class="nav-item <?php echo basename($_SERVER['PHP_SELF']) == 'planner.php' ? 'active' : ''; ?>">
                    <i class="fas fa-calendar-alt"></i>
                    <span>Calendar</span>
                </a>
                <?php endif; ?>
                <?php if ($is_trial_active || ($my_info['subscription_approved'] && $my_info['subscription_plan'] == 'C')): ?>
                <a href="transaction.php" class="nav-item <?php echo basename($_SERVER['PHP_SELF']) == 'transaction.php' ? 'active' : ''; ?>">
                    <i class="fas fa-receipt"></i>
                    <span>Transactions</span>
                </a>
                <?php endif; ?>
            </nav>
        </aside>

        <!-- Main Content -->
        <main class="main-content">
            <!-- Page Header -->
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

            <!-- Account Content -->
            <div class="account-grid">
                <!-- Profile Picture Section -->
                <div class="profile-section">
                    <div class="profile-card">
                        <div class="profile-header">
                            <h3><i class="fas fa-camera"></i> Profile Picture</h3>
                        </div>
                        <div class="profile-content">
                            <div class="current-photo">
                                <?php if ($my_info['img']): ?>
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
                                <label>Account Type</label>
                                <span class="account-type">
                                    <i class="fas fa-user"></i>
                                    Business User
                                </span>
                            </div>
                            <div class="detail-item">
                                <label>Member Since</label>
                                <span><?php echo date('F Y', strtotime($my_info['trial_start_date'] ?? 'now')); ?></span>
                            </div>
                            <div class="detail-item">
                                <label>Subscription Status</label>
                                <span>
                                    <?php
                                    if ($is_trial_active) {
                                        $days_left = floor($seconds_left / 60);
                                        echo "Free Trial (" . ($days_left > 0 ? "$days_left minutes left" : "expires soon") . ")";
                                    } elseif ($my_info['subscription_approved']) {
                                        echo "Subscribed to Plan " . htmlspecialchars($my_info['subscription_plan']);
                                    } elseif ($my_info['subscription_proof']) {
                                        echo "Pending Approval for Plan " . htmlspecialchars($my_info['requested_plan']);
                                    } else {
                                        echo "No Active Subscription";
                                    }
                                    ?>
                                </span>
                            </div>
                            <div class="detail-item">
                                <label>Available Features</label>
                                <span>
                                    <?php
                                    if ($is_trial_active || ($my_info['subscription_approved'] && $my_info['subscription_plan'] == 'C')) {
                                        echo "All Features: View Stock, Adjust Stock, Add/Edit/Delete Items, Calendar, Transactions";
                                    } elseif ($my_info['subscription_approved'] && $my_info['subscription_plan'] == 'B') {
                                        echo "Plan B: View Stock, Adjust Stock, Add/Edit/Delete Items, Calendar";
                                    } elseif ($my_info['subscription_approved'] && $my_info['subscription_plan'] == 'A') {
                                        echo "Plan A: View Stock, Adjust Stock, Add/Edit/Delete Items";
                                    } elseif ($my_info['subscription_proof'] && !$my_info['subscription_approved']) {
                                        echo "Current Plan (" . htmlspecialchars($my_info['subscription_plan']) . "): " . 
                                             ($my_info['subscription_plan'] == 'A' ? "View Stock, Adjust Stock, Add/Edit/Delete Items" : 
                                              ($my_info['subscription_plan'] == 'B' ? "View Stock, Adjust Stock, Add/Edit/Delete Items, Calendar" : ""));
                                    } else {
                                        echo "No Access (Subscribe to unlock features)";
                                    }
                                    ?>
                                </span>
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

                <!-- Upgrade Subscription -->
                <div class="subscription-section">
                    <div class="subscription-card">
                        <div class="subscription-header">
                            <h3><i class="fas fa-star"></i> Upgrade Subscription</h3>
                        </div>
                        <div class="subscription-content">
                            <?php 
                            $subscription_approved = isset($my_info['subscription_approved']) ? $my_info['subscription_approved'] : 0;
                            $subscription_plan = isset($my_info['subscription_plan']) ? $my_info['subscription_plan'] : '';
                            ?>
                            <?php if ($subscription_approved && $subscription_plan == 'C' && !$is_trial_active): ?>
                                <p>You are already subscribed to the highest plan (Plan C).</p>
                            <?php else: ?>
                                <form method="POST" enctype="multipart/form-data" class="subscription-form">
                                    <div class="plan-options">
                                        <?php if (!$subscription_approved || $is_trial_active || $subscription_plan == 'A'): ?>
                                        <label class="plan-option <?php echo $subscription_plan == 'A' ? 'plan-selected' : ''; ?>">
                                            <input type="radio" name="subscription_plan" value="A" <?php echo $subscription_plan == 'A' && !$is_trial_active ? 'checked' : ''; ?> required>
                                            <div class="plan-info">
                                                <div class="plan-title">Plan A</div>
                                                <div class="plan-desc">Basic Inventory</div>
                                                <div class="plan-price">₱200</div>
                                            </div>
                                        </label>
                                        <label class="plan-option <?php echo $subscription_plan == 'B' ? 'plan-selected' : ''; ?>">
                                            <input type="radio" name="subscription_plan" value="B" <?php echo $subscription_plan == 'B' && !$is_trial_active ? 'checked' : ''; ?> required>
                                            <div class="plan-info">
                                                <div class="plan-title">Plan B</div>
                                                <div class="plan-desc">Inventory + Calendar</div>
                                                <div class="plan-price">₱250</div>
                                            </div>
                                        </label>
                                        <label class="plan-option <?php echo $subscription_plan == 'C' ? 'plan-selected' : ''; ?>">
                                            <input type="radio" name="subscription_plan" value="C" <?php echo $subscription_plan == 'C' && !$is_trial_active ? 'checked' : ''; ?> required>
                                            <div class="plan-info">
                                                <div class="plan-title">Plan C</div>
                                                <div class="plan-desc">Inventory + Calendar + Transactions</div>
                                                <div class="plan-price">₱350</div>
                                            </div>
                                        </label>
                                        <?php elseif ($subscription_plan == 'B'): ?>
                                        <label class="plan-option <?php echo $subscription_plan == 'B' ? 'plan-selected' : ''; ?>">
                                            <input type="radio" name="subscription_plan" value="B" <?php echo $subscription_plan == 'B' ? 'checked' : ''; ?> required>
                                            <div class="plan-info">
                                                <div class="plan-title">Plan B</div>
                                                <div class="plan-desc">Inventory + Calendar</div>
                                                <div class="plan-price">₱250</div>
                                            </div>
                                        </label>
                                        <label class="plan-option <?php echo $subscription_plan == 'C' ? 'plan-selected' : ''; ?>">
                                            <input type="radio" name="subscription_plan" value="C" <?php echo $subscription_plan == 'C' ? 'checked' : ''; ?> required>
                                            <div class="plan-info">
                                                <div class="plan-title">Plan C</div>
                                                <div class="plan-desc">Inventory + Calendar + Transactions</div>
                                                <div class="plan-price">₱300</div>
                                            </div>
                                        </label>
                                        <?php endif; ?>
                                    </div>
                                    <?php if ($is_trial_active): ?>
                                        <p class="helper-text">You are currently on a free trial. Select a plan to subscribe after the trial ends.</p>
                                    <?php elseif ($my_info['subscription_proof'] && !$subscription_approved): ?>
                                        <p class="helper-text">Your upgrade to Plan <?php echo htmlspecialchars($my_info['requested_plan']); ?> is pending approval. You can still use your current plan (Plan <?php echo htmlspecialchars($my_info['subscription_plan']); ?>).</p>
                                    <?php endif; ?>
                                    <div class="form-grid" style="margin-top:18px">
                                        <div class="form-group">
                                            <label class="form-label">Upload Proof of Payment</label>
                                            <div class="file-input-wrapper">
                                                <input type="file" id="subscription_proof" name="subscription_proof" accept=".jpg,.jpeg,.png,.pdf" required>
                                                <label for="subscription_proof" class="subscription-file-label">
                                                    <i class="fas fa-cloud-upload-alt"></i>
                                                    <span class="file-name">Choose file</span>
                                                </label>
                                            </div>
                                            <span class="error-message"><?php echo htmlspecialchars($subUploadErr); ?></span>
                                        </div>
                                    </div>
                                    <div class="form-actions">
                                        <button type="submit" name="btnUploadProof" class="btn-primary">
                                            <i class="fas fa-upload"></i>
                                            Upload Proof
                                        </button>
                                    </div>
                                </form>
                            <?php endif; ?>
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

        // Subscription file label
        const subInput = document.getElementById('subscription_proof');
        if (subInput) {
            subInput.addEventListener('change', function(e) {
                const file = e.target.files[0];
                const wrapper = document.querySelector('.subscription-file-label .file-name');
                if (file && wrapper) wrapper.textContent = file.name;
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

        // Plan selection enhancement
        document.querySelectorAll('.plan-option').forEach(option => {
            option.addEventListener('click', function() {
                const radio = this.querySelector('input[type="radio"]');
                if (radio) {
                    radio.checked = true;
                    document.querySelectorAll('.plan-option').forEach(opt => opt.classList.remove('plan-selected'));
                    this.classList.add('plan-selected');
                }
            });
        });
    </script>
</body>
</html>