<?php
// handles event registration and cancellation for participants
session_start();
include 'config/db.php';
include 'config/functions.php';

check_auth('participant'); // only participants can register

// check if registering or canceling
$action = $_GET['action'] ?? 'register';
$event_id = $_POST['event_id'];
$user_id = $_SESSION['user_id'];

if ($action == 'register') {
    // register user for event
    $sql = "INSERT INTO registration (r_userid, r_eventid, r_attendancestatus) VALUES (:uid, :eid, 'registered')";
    $stmt = $conn->prepare($sql);
    $stmt->execute([':uid' => $user_id, ':eid' => $event_id]);
    
    // update analytics to track events attended
    $sql = "INSERT INTO analytics (a_userid, a_totaleventsorganized, a_totaleventsattended, a_cancelledregistrations) 
            VALUES (:uid, 0, 1, 0) ON CONFLICT (a_userid) 
            DO UPDATE SET a_totaleventsattended = analytics.a_totaleventsattended + 1";
    $stmt = $conn->prepare($sql);
    $stmt->execute([':uid' => $user_id]);
} else {
    // cancel registration by updating status
    $sql = "UPDATE registration SET r_attendancestatus = 'cancelled' WHERE r_userid = :uid AND r_eventid = :eid";
    $stmt = $conn->prepare($sql);
    $stmt->execute([':uid' => $user_id, ':eid' => $event_id]);
    
    // update analytics to track cancelled registrations
    $sql = "UPDATE analytics SET a_cancelledregistrations = a_cancelledregistrations + 1 WHERE a_userid = :uid";
    $stmt = $conn->prepare($sql);
    $stmt->execute([':uid' => $user_id]);
}

// send user back to event details page
header("Location: event.php?action=details&id=" . $event_id);
exit();
?>
