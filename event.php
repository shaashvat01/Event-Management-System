<?php
// handles all event related stuff like viewing creating editing and deleting events
session_start();
include 'config/db.php';
include 'config/functions.php';

// figure out what action user wants to do
$action = $_GET['action'] ?? 'list';
$event_id = $_GET['id'] ?? null;

// DELETE EVENT
if ($action == 'delete' && $_SERVER['REQUEST_METHOD'] == 'POST') {
    check_auth('organizer'); // only organizers can delete
    $event_id = $_POST['event_id'];
    
    // first delete all registrations for this event
    $sql = "DELETE FROM registration WHERE r_eventid = :id";
    $stmt = $conn->prepare($sql);
    $stmt->execute([':id' => $event_id]);
    
    // then delete the event itself
    $sql = "DELETE FROM event WHERE e_eventid = :id AND e_userid = :uid";
    $stmt = $conn->prepare($sql);
    $stmt->execute([':id' => $event_id, ':uid' => $_SESSION['user_id']]);
    
    header("Location: dashboard.php");
    exit();
}

// CREATE/EDIT EVENT FORM
if ($action == 'form') {
    check_auth('organizer'); // only organizers can create/edit events
    
    $error = '';
    // default empty event for creating new one
    $event = ['e_title' => '', 'e_description' => '', 'e_eventdate' => '', 'e_location' => '', 'e_status' => 'upcoming', 'e_categoryid' => ''];
    
    // get all categories from database for dropdown
    $sql = "SELECT * FROM category";
    $stmt = $conn->prepare($sql);
    $stmt->execute();
    $categories = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // if editing existing event load its data
    if ($event_id) {
        $sql = "SELECT * FROM event WHERE e_eventid = :id AND e_userid = :uid";
        $stmt = $conn->prepare($sql);
        $stmt->execute([':id' => $event_id, ':uid' => $_SESSION['user_id']]);
        $event = $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    // when user submits the form
    if ($_SERVER['REQUEST_METHOD'] == 'POST') {
        // get all form data
        $title = $_POST['title'];
        $desc = $_POST['description'];
        $date = $_POST['event_date'];
        $location = $_POST['location'];
        $status = $_POST['status'];
        $cat_id = $_POST['category_id'];
        
        if ($event_id) {
            // update existing event
            $sql = "UPDATE event SET e_title=:t, e_description=:d, e_eventdate=:dt, e_location=:l, e_status=:s, e_categoryid=:c WHERE e_eventid=:id";
            $stmt = $conn->prepare($sql);
            $stmt->execute([':t'=>$title, ':d'=>$desc, ':dt'=>$date, ':l'=>$location, ':s'=>$status, ':c'=>$cat_id, ':id'=>$event_id]);
        } else {
            // create new event
            $sql = "INSERT INTO event (e_title, e_description, e_eventdate, e_location, e_status, e_userid, e_categoryid) 
                    VALUES (:t, :d, :dt, :l, :s, :u, :c)";
            $stmt = $conn->prepare($sql);
            $stmt->execute([':t'=>$title, ':d'=>$desc, ':dt'=>$date, ':l'=>$location, ':s'=>$status, ':u'=>$_SESSION['user_id'], ':c'=>$cat_id]);
            
            // update analytics table to track total events organized
            $sql = "INSERT INTO analytics (a_userid, a_totaleventsorganized, a_totaleventsattended, a_cancelledregistrations) 
                    VALUES (:u, 1, 0, 0) ON CONFLICT (a_userid) 
                    DO UPDATE SET a_totaleventsorganized = analytics.a_totaleventsorganized + 1";
            $stmt = $conn->prepare($sql);
            $stmt->execute([':u' => $_SESSION['user_id']]);
        }
        
        header("Location: dashboard.php");
        exit();
    }
    
    render_header();
    
    // build status dropdown options
    $statuses = ['upcoming', 'ongoing', 'completed', 'cancelled'];
    $status_opts = '';
    foreach ($statuses as $s) {
        $sel = ($event['e_status'] == $s) ? 'selected' : '';
        $status_opts .= '<option value="'.$s.'" '.$sel.'>'.ucfirst($s).'</option>';
    }
    
    // build category dropdown options
    $cat_opts = '';
    foreach ($categories as $c) {
        $sel = ($event['e_categoryid'] == $c['c_categoryid']) ? 'selected' : '';
        $cat_opts .= '<option value="'.$c['c_categoryid'].'" '.$sel.'>'.htmlspecialchars($c['c_name']).'</option>';
    }
    
    // show the form with all data filled in
    render_template('events.html', 'event-form', [
        'FORM_TITLE' => $event_id ? 'Edit Event' : 'Create Event',
        'ERROR_MESSAGE' => $error,
        'TITLE' => htmlspecialchars($event['e_title']),
        'DESCRIPTION' => htmlspecialchars($event['e_description']),
        'EVENT_DATE' => $event['e_eventdate'] ? date('Y-m-d\TH:i', strtotime($event['e_eventdate'])) : '',
        'LOCATION' => htmlspecialchars($event['e_location']),
        'STATUS_OPTIONS' => $status_opts,
        'CATEGORIES' => $cat_opts,
        'SUBMIT_TEXT' => $event_id ? 'Update Event' : 'Create Event'
    ]);
    
    render_footer();
    exit();
}

// VIEW SINGLE EVENT DETAILS
if ($action == 'details') {
    render_header();
    
    // get event with category and organizer info
    $sql = "SELECT e.*, c.c_name as category_name, c.c_description as category_desc, u.u_name as organizer_name 
            FROM event e 
            JOIN category c ON e.e_categoryid = c.c_categoryid 
            JOIN \"User\" u ON e.e_userid = u.u_userid 
            WHERE e.e_eventid = :id";
    $stmt = $conn->prepare($sql);
    $stmt->execute([':id' => $event_id]);
    $event = $stmt->fetch(PDO::FETCH_ASSOC);
    
    $registration_section = '';
    // if user is participant show registration options
    if (isset($_SESSION['user_id']) && $_SESSION['role'] == 'participant') {
        // check if already registered
        $sql = "SELECT r_attendancestatus FROM registration WHERE r_userid = :uid AND r_eventid = :eid";
        $stmt = $conn->prepare($sql);
        $stmt->execute([':uid' => $_SESSION['user_id'], ':eid' => $event_id]);
        $reg = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$reg) {
            // not registered yet show register button
            $registration_section = '<form action="registration.php?action=register" method="POST">
                <input type="hidden" name="event_id" value="' . $event_id . '">
                <button type="submit">Register for Event</button></form>';
        } else {
            // already registered show status
            $registration_section = '<p><strong>Status:</strong> ' . htmlspecialchars($reg['r_attendancestatus']) . '</p>';
            if ($reg['r_attendancestatus'] == 'registered') {
                // if still registered show cancel button
                $registration_section .= '<form action="registration.php?action=cancel" method="POST">
                    <input type="hidden" name="event_id" value="' . $event_id . '">
                    <button type="submit" class="btn-danger">Cancel Registration</button></form>';
            }
        }
    }
    
    // show event details page
    render_template('events.html', 'event-details', [
        'TITLE' => htmlspecialchars($event['e_title']),
        'DESCRIPTION' => htmlspecialchars($event['e_description']),
        'DATE' => date('F j, Y g:i A', strtotime($event['e_eventdate'])),
        'LOCATION' => htmlspecialchars($event['e_location']),
        'STATUS' => htmlspecialchars($event['e_status']),
        'CATEGORY' => htmlspecialchars($event['category_name']),
        'CATEGORY_DESC' => htmlspecialchars($event['category_desc']),
        'ORGANIZER' => htmlspecialchars($event['organizer_name']),
        'REGISTRATION_SECTION' => $registration_section
    ]);
    
    render_footer();
    exit();
}

// LIST ALL EVENTS (DEFAULT)
render_header();

// get all events with category and organizer names
$sql = "SELECT e.*, c.c_name as category_name, u.u_name as organizer_name 
        FROM event e 
        JOIN category c ON e.e_categoryid = c.c_categoryid 
        JOIN \"User\" u ON e.e_userid = u.u_userid 
        ORDER BY e.e_eventdate DESC";
$stmt = $conn->prepare($sql);
$stmt->execute();
$events = $stmt->fetchAll(PDO::FETCH_ASSOC);

// build html for all event cards
$events_html = '';
if (count($events) > 0) {
    foreach ($events as $e) {
        $events_html .= '<div class="event-card">';
        $events_html .= '<h3>' . htmlspecialchars($e['e_title']) . '</h3>';
        $events_html .= '<p><strong>Date:</strong> ' . date('F j, Y g:i A', strtotime($e['e_eventdate'])) . '</p>';
        $events_html .= '<p><strong>Location:</strong> ' . htmlspecialchars($e['e_location']) . '</p>';
        $events_html .= '<p><strong>Category:</strong> ' . htmlspecialchars($e['category_name']) . '</p>';
        $events_html .= '<p><strong>Organizer:</strong> ' . htmlspecialchars($e['organizer_name']) . '</p>';
        $events_html .= '<p><strong>Status:</strong> ' . htmlspecialchars($e['e_status']) . '</p>';
        $events_html .= '<a href="event.php?action=details&id=' . $e['e_eventid'] . '" class="btn btn-secondary">View Details</a>';
        $events_html .= '</div>';
    }
} else {
    $events_html = '<p>No events available.</p>';
}

// show events list page
render_template('events.html', 'event-list', ['EVENTS_CONTENT' => $events_html]);
render_footer();
?>
