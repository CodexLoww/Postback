<?php
include('dbcon.php'); // This includes the database connection settings

function log_data($data) {
    file_put_contents('postback.log', $data, FILE_APPEND);
}

function sanitize_input($input) {
    return htmlspecialchars(stripslashes(trim($input)));
}

function check_transaction_exists($conn, $txnid, $refno) {
    $check_stmt = $conn->prepare("SELECT COUNT(*) FROM transactions WHERE txnid = ? AND refno = ?");
    if (!$check_stmt) {
        log_data("Prepare failed: (" . $conn->errno . ") " . $conn->error . "\n");
        die("Prepare failed: (" . $conn->errno . ") " . $conn->error);
    }
    $check_stmt->bind_param("ss", $txnid, $refno);
    $check_stmt->execute();
    $check_stmt->bind_result($count);
    $check_stmt->fetch();
    $check_stmt->close();

    return $count;
}

function update_transaction_status($conn, $status, $procid, $txnid, $refno) {
    $stmt = $conn->prepare("UPDATE transactions SET status = ?, procid = ? WHERE txnid = ? AND refno = ?");
    if (!$stmt) {
        log_data("Prepare failed: (" . $conn->errno . ") " . $conn->error . "\n");
        die("Prepare failed: (" . $conn->errno . ") " . $conn->error);
    }
    $stmt->bind_param("ssss", $status, $procid, $txnid, $refno);

    if ($stmt->execute()) {
        $affected_rows = $stmt->affected_rows;
        $result = "result=OK, affected rows: $affected_rows";
    } else {
        $result = "result=FAIL: " . $stmt->error;
    }
    $stmt->close();

    return $result;
}

// Capture and sanitize POST parameters
$txnid = isset($_POST['txn_id']) ? sanitize_input($_POST['txn_id']) : null;
$refno = isset($_POST['ref_no']) ? sanitize_input($_POST['ref_no']) : null;
$status = isset($_POST['status']) ? sanitize_input($_POST['status']) : null;
$amount = isset($_POST['amount']) ? sanitize_input($_POST['amount']) : null;
$ccy = isset($_POST['ccy']) ? sanitize_input($_POST['ccy']) : null;
$procid = isset($_POST['procid']) ? sanitize_input($_POST['procid']) : null;

// Logging for debugging
$log_data = "Received parameters:\n";
$log_data .= "txn_id: $txnid\nref_no: $refno\nstatus: $status\namount: $amount\nccy: $ccy\nprocid: $procid\n";
log_data($log_data);

// Check if mandatory parameters are available
if ($txnid && $refno && $status) {
    // Check if the transaction exists
    $count = check_transaction_exists($conn, $txnid, $refno);

    if ($count == 0) {
        log_data("Transaction not found.\n");
        die("Transaction not found.");
    }

    // Update the transaction status in the database
    $result = update_transaction_status($conn, $status, $procid, $txnid, $refno);
} else {
    $result = "result=FAIL: Missing parameters";
}

// Logging for debugging
log_data($result . "\n");

echo $result;
?>
