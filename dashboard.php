<?php
// organizer dashboard shows their events analytics and registrations
session_start();
include 'config/db.php';
include 'config/functions.php';

check_auth('organizer'); // only organizers can access

// check what page to show
$action = $_GET['action'] ?? 'main';
$event_id = $_GET['id'] ?? null;

// ANALYTICS PAGE
if ($action == 'analytics') {
    $uid = $_SESSION['user_id'];
    
    // count total events organized by this user
    $sql = "SELECT COUNT(*) as total FROM \"Event\" WHERE e_userid = :uid";
    $stmt = $conn->prepare($sql);
    $stmt->execute([':uid' => $uid]);
    $total_events = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    
    // count total registrations across all their events
    $sql = "SELECT COUNT(*) as total FROM \"Registration\" r 
            JOIN \"Event\" e ON r.r_eventid = e.e_eventid WHERE e.e_userid = :uid";
    $stmt = $conn->prepare($sql);
    $stmt->execute([':uid' => $uid]);
    $total_regs = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    
    // count how many people actually attended
    $sql = "SELECT COUNT(*) as total FROM \"Registration\" r 
            JOIN \"Event\" e ON r.r_eventid = e.e_eventid 
            WHERE e.e_userid = :uid AND r.r_attendancestatus = 'attended'";
    $stmt = $conn->prepare($sql);
    $stmt->execute([':uid' => $uid]);
    $total_attended = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    
    // count cancelled registrations
    $sql = "SELECT COUNT(*) as total FROM \"Registration\" r 
            JOIN \"Event\" e ON r.r_eventid = e.e_eventid 
            WHERE e.e_userid = :uid AND r.r_attendancestatus = 'cancelled'";
    $stmt = $conn->prepare($sql);
    $stmt->execute([':uid' => $uid]);
    $total_cancelled = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    
    // update analytics table with latest numbers
    $sql = "INSERT INTO \"Analytics\" (a_userid, a_totaleventorganized, a_totaleventsattended, a_cancelledregistrations) 
            VALUES (:uid, :org, 0, 0) ON CONFLICT (a_userid) 
            DO UPDATE SET a_totaleventorganized = :org";
    $stmt = $conn->prepare($sql);
    $stmt->execute([':uid' => $uid, ':org' => $total_events]);
    
    // show analytics page
    render_header();
    render_template('dashboard.html', 'analytics', [
        'TOTAL_EVENTS' => $total_events,
        'TOTAL_REGISTRATIONS' => $total_regs,
        'TOTAL_ATTENDED' => $total_attended,
        'TOTAL_CANCELLED' => $total_cancelled
    ]);
    render_footer();
    exit();
}

// REGISTRATIONS PAGE
if ($action == 'registrations') {
    // get event title
    $sql = "SELECT e_title FROM \"Event\" WHERE e_eventid = :id AND e_userid = :uid";
    $stmt = $conn->prepare($sql);
    $stmt->execute([':id' => $event_id, ':uid' => $_SESSION['user_id']]);
    $event = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // get all registrations for this event with user info
    $sql = "SELECT r.*, u.u_name, u.u_email FROM \"Registration\" r 
            JOIN \"User\" u ON r.r_userid = u.u_userid 
            WHERE r.r_eventid = :id ORDER BY r.r_registrationdate DESC";
    $stmt = $conn->prepare($sql);
    $stmt->execute([':id' => $event_id]);
    $registrations = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // if organizer updates attendance status
    if ($_SERVER['REQUEST_METHOD'] == 'POST') {
        $reg_id = $_POST['registration_id'];
        $status = $_POST['status'];
        
        // update registration status
        $sql = "UPDATE \"Registration\" SET r_attendancestatus = :status WHERE r_registrationid = :id";
        $stmt = $conn->prepare($sql);
        $stmt->execute([':status' => $status, ':id' => $reg_id]);
        
        // reload page to show updated status
        header("Location: dashboard.php?action=registrations&id=" . $event_id);
        exit();
    }
    
    render_header();
    
    // build table with all registrations
    $table = '';
    if (count($registrations) > 0) {
        $table = '<table><tr><th>Name</th><th>Email</th><th>Date</th><th>Status</th><th>Actions</th></tr>';
        foreach ($registrations as $r) {
            $table .= '<tr>';
            $table .= '<td>' . htmlspecialchars($r['u_name']) . '</td>';
            $table .= '<td>' . htmlspecialchars($r['u_email']) . '</td>';
            $table .= '<td>' . date('M j, Y g:i A', strtotime($r['r_registrationdate'])) . '</td>';
            $table .= '<td>' . htmlspecialchars($r['r_attendancestatus']) . '</td>';
            $table .= '<td><form method="POST" style="display:inline;">
                <input type="hidden" name="registration_id" value="' . $r['r_registrationid'] . '">
                <select name="status">';
            
            // dropdown to change status
            foreach (['registered', 'attended', 'no_show', 'cancelled'] as $s) {
                $sel = ($r['r_attendancestatus'] == $s) ? 'selected' : '';
                $table .= '<option value="'.$s.'" '.$sel.'>'.ucfirst(str_replace('_', ' ', $s)).'</option>';
            }
            
            $table .= '</select><button type="submit" class="btn-secondary">Update</button></form></td>';
            $table .= '</tr>';
        }
        $table .= '</table>';
    } else {
        $table = '<p>No registrations yet.</p>';
    }
    
    // show registrations page
    render_template('dashboard.html', 'registrations', [
        'EVENT_TITLE' => htmlspecialchars($event['e_title']),
        'REGISTRATIONS_TABLE' => $table
    ]);
    
    render_footer();
    exit();
}

// MAIN DASHBOARD
render_header();

// get all events created by this organizer
$sql = "SELECT e.*, c.c_name as category_name FROM \"Event\" e 
        JOIN \"Category\" c ON e.e_categoryid = c.c_categoryid 
        WHERE e.e_userid = :uid ORDER BY e.e_eventdate DESC";
$stmt = $conn->prepare($sql);
$stmt->execute([':uid' => $_SESSION['user_id']]);
$events = $stmt->fetchAll(PDO::FETCH_ASSOC);

// build table showing all events
$table = '';
if (count($events) > 0) {
    $table = '<table><tr><th>Title</th><th>Date</th><th>Location</th><th>Category</th><th>Status</th><th>Actions</th></tr>';
    foreach ($events as $e) {
        $table .= '<tr>';
        $table .= '<td>' . htmlspecialchars($e['e_title']) . '</td>';
        $table .= '<td>' . date('M j, Y', strtotime($e['e_eventdate'])) . '</td>';
        $table .= '<td>' . htmlspecialchars($e['e_location']) . '</td>';
        $table .= '<td>' . htmlspecialchars($e['category_name']) . '</td>';
        $table .= '<td>' . htmlspecialchars($e['e_status']) . '</td>';
        $table .= '<td>';
        // buttons for edit registrations and delete
        $table .= '<a href="event.php?action=form&id=' . $e['e_eventid'] . '" class="btn btn-secondary">Edit</a> ';
        $table .= '<a href="dashboard.php?action=registrations&id=' . $e['e_eventid'] . '" class="btn btn-secondary">Registrations</a> ';
        $table .= '<form action="event.php?action=delete" method="POST" style="display:inline;">
            <input type="hidden" name="event_id" value="' . $e['e_eventid'] . '">
            <button type="submit" class="btn-danger" onclick="return confirm(\'Delete?\')">Delete</button></form>';
        $table .= '</td></tr>';
    }
    $table .= '</table>';
} else {
    $table = '<p>No events yet.</p>';
}

// show dashboard page
render_template('dashboard.html', 'dashboard', [
    'USER_NAME' => htmlspecialchars($_SESSION['name']),
    'EVENTS_TABLE' => $table
]);

render_footer();
?>
