<?php
require_once '../../includes/check_auth.php';
check_auth(['logistic_coordinator', 'it_operation']);

require_once '../../includes/db.php';

$conn = $db->conn;

if (isset($_GET['id'])) {
    $asset_id = $_GET['id'];
    
    $sql = "SELECT a.*, vd.* FROM assets a 
            LEFT JOIN vehicle_details vd ON a.asset_id = vd.asset_id
            WHERE a.asset_id = ?";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $asset_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($row = $result->fetch_assoc()) {
        echo json_encode($row);
    } else {
        echo json_encode(['error' => 'Vehicle not found']);
    }
}

$conn->close();
?>