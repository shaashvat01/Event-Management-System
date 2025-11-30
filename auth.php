<?php
// handles user login signup and logout stuff
session_start();
include 'config/db.php';
include 'config/functions.php';

// check what action user wants to do login or signup
$action = $_GET['action'] ?? 'login';
$error = '';

// if user wants to logout
if ($action == 'logout') {
    session_destroy(); // kill the session
    header("Location: auth.php?action=login");
    exit();
}

// when user submits the form
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if ($action == 'login') {
        // get email and password from form
        $email = $_POST['email'];
        $password = $_POST['password'];
        
        // find user in database by email
        $sql = "SELECT * FROM \"User\" WHERE u_email = :email";
        $stmt = $conn->prepare($sql);
        $stmt->execute([':email' => $email]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // check if user exists and password matches
        if ($user && password_verify($password, $user['u_password'])) {
            // save user info in session so they stay logged in
            $_SESSION['user_id'] = $user['u_userid'];
            $_SESSION['name'] = $user['u_name'];
            $_SESSION['role'] = $user['u_role'];
            
            // send organizer to dashboard and participant to events page
            header("Location: " . ($user['u_role'] == 'organizer' ? 'dashboard.php' : 'event.php'));
            exit();
        } else {
            $error = '<p class="error">Invalid email or password</p>';
        }
    } else {
        // signup - get all the info from form
        $name = $_POST['name'];
        $email = $_POST['email'];
        $password = password_hash($_POST['password'], PASSWORD_DEFAULT); // hash password for security
        $role = $_POST['role'];
        
        // insert new user into database
        $sql = "INSERT INTO \"User\" (u_name, u_email, u_password, u_role) VALUES (:name, :email, :password, :role)";
        $stmt = $conn->prepare($sql);
        
        try {
            $stmt->execute([':name' => $name, ':email' => $email, ':password' => $password, ':role' => $role]);
            header("Location: auth.php?action=login"); // send them to login after signup
            exit();
        } catch(PDOException $e) {
            $error = '<p class="error">Email already exists</p>'; // email must be unique
        }
    }
}

// show the login or signup form
render_header();
render_template('auth.html', $action == 'login' ? 'login-form' : 'signup-form', ['ERROR_MESSAGE' => $error]);
render_footer();
?>
