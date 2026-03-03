<?php
require_once '../../includes/check_auth.php';
check_auth(['logistic_coordinator', 'it_operation']);

require_once '../../includes/db.php';

$conn = $db->conn;

// Set headers for Excel download
header('Content-Type: application/vnd.ms-excel');
header('Content-Disposition: attachment; filename="vehicles_' . date('Y-m-d') . '.xls"');

// Get all vehicles
$sql = "SELECT a.asset_tag, a.model, a.manufacturer, a.serial_number, 
        a.acquisition_date, a.asset_status,
        vd.license_plate, vd.vehicle_type, vd.fuel_type, vd.engine_capacity,
        vd.chassis_number, vd.color, vd.current_mileage, vd.last_service_date, vd.next_service_date,
        l.name as location_name
        FROM assets a 
        LEFT JOIN vehicle_details vd ON a.asset_id = vd.asset_id
        LEFT JOIN locations l ON a.location_id = l.location_id
        WHERE a.asset_class = 'CLASS006' 
        ORDER BY a.created_at DESC";

$result = $conn->query($sql);

// Create Excel file
echo "Asset Tag\tModel\tManufacturer\tSerial Number\tAcquisition Date\tStatus\t";
echo "License Plate\tVehicle Type\tFuel Type\tEngine Capacity\tChassis Number\tColor\t";
echo "Current Mileage\tLast Service\tNext Service\tLocation\n";

if ($result) {
    while ($row = $result->fetch_assoc()) {
        echo implode("\t", [
            $row['asset_tag'] ?? '',
            $row['model'] ?? '',
            $row['manufacturer'] ?? '',
            $row['serial_number'] ?? '',
            $row['acquisition_date'] ?? '',
            $row['asset_status'] ?? '',
            $row['license_plate'] ?? '',
            $row['vehicle_type'] ?? '',
            $row['fuel_type'] ?? '',
            $row['engine_capacity'] ?? '',
            $row['chassis_number'] ?? '',
            $row['color'] ?? '',
            $row['current_mileage'] ?? '0',
            $row['last_service_date'] ?? '',
            $row['next_service_date'] ?? '',
            $row['location_name'] ?? ''
        ]) . "\n";
    }
}

$conn->close();
?>