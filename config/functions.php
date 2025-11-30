<?php
// helper functions that are used everywhere in the app

// shows the header with navbar at top of every page
function render_header() {
    // start session if not already started
    if (session_status() == PHP_SESSION_NONE) {
        session_start();
    }
    ?>
    <!DOCTYPE html>
    <html>
    <head>
        <title>Event Management System</title>
        <link rel="stylesheet" href="/assets/css/style.css">
    </head>
    <body>
        <div class="navbar">
            <a href="/event.php">Home</a>
            <?php if(isset($_SESSION['user_id'])): ?>
                <?php if($_SESSION['role'] == 'organizer'): ?>
                    <a href="/dashboard.php">Dashboard</a>
                    <a href="/dashboard.php?action=analytics">Analytics</a>
                <?php endif; ?>
                <a href="/auth.php?action=logout">Logout</a>
            <?php else: ?>
                <a href="/auth.php?action=login">Login</a>
                <a href="/auth.php?action=signup">Sign Up</a>
            <?php endif; ?>
        </div>
        <div class="container">
    <?php
}

// closes the html tags at bottom of page
function render_footer() {
    echo '</div></body></html>';
}

// gets a specific section from html template file
function get_template_section($file, $section_id) {
    $content = file_get_contents(__DIR__ . '/../templates/' . $file);
    // use regex to find the div with matching id
    preg_match('/<div id="' . $section_id . '">(.*?)<\/div>/s', $content, $matches);
    return $matches[1] ?? '';
}

// loads template and replaces placeholders with actual data
function render_template($file, $section_id, $data = []) {
    $template = get_template_section($file, $section_id);
    // replace all {{PLACEHOLDER}} with real values
    foreach ($data as $key => $value) {
        $template = str_replace('{{' . $key . '}}', $value, $template);
    }
    echo '<div>' . $template . '</div>';
}

// checks if user is logged in and has correct role
function check_auth($required_role = null) {
    // if not logged in send to login page
    if (!isset($_SESSION['user_id'])) {
        header("Location: /auth.php?action=login");
        exit();
    }
    // if wrong role send to home page
    if ($required_role && $_SESSION['role'] != $required_role) {
        header("Location: /event.php");
        exit();
    }
}
?>
