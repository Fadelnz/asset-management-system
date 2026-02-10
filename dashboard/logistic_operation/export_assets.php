<?php
require_once '../../includes/check_auth.php';
require_once '../../includes/db.php';

check_auth(['logistic_coordinator','it_operation']);

// Get export format
$format = isset($_GET['format']) ? strtolower($_GET['format']) : 'csv';
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$filter_class = isset($_GET['class']) ? $_GET['class'] : '';
$filter_location = isset($_GET['location']) ? $_GET['location'] : '';
$filter_status = isset($_GET['status']) ? $_GET['status'] : '';

// Build query (same as list_assets.php)
$sql = "SELECT 
    a.*,
    ac.class_name,
    l.name as location_name
FROM assets a
LEFT JOIN asset_classes ac ON a.asset_class = ac.class_id
LEFT JOIN locations l ON a.location_id = l.location_id
WHERE 1=1";

$params = [];

if (!empty($search)) {
    $sql .= " AND (
        a.asset_id LIKE :search OR 
        a.asset_tag LIKE :search OR 
        a.asset_name LIKE :search OR 
        a.model LIKE :search OR 
        a.serial_number LIKE :search OR 
        a.manufacturer LIKE :search
    )";
    $params[':search'] = "%$search%";
}

if (!empty($filter_class)) {
    $sql .= " AND a.asset_class = :class";
    $params[':class'] = $filter_class;
}

if (!empty($filter_location)) {
    $sql .= " AND a.location_id = :location";
    $params[':location'] = $filter_location;
}

if (!empty($filter_status)) {
    $today = date('Y-m-d');
    if ($filter_status === 'active') {
        $sql .= " AND (a.warranty_expiry IS NULL OR a.warranty_expiry >= :today)";
        $params[':today'] = $today;
    } elseif ($filter_status === 'expired') {
        $sql .= " AND a.warranty_expiry < :today";
        $params[':today'] = $today;
    }
}

$sql .= " ORDER BY a.created_at DESC";

$stmt = $db->pdo->prepare($sql);
$stmt->execute($params);
$assets = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Generate filename with timestamp
$timestamp = date('Ymd_His');
$filename = "assets_export_{$timestamp}";

if ($format === 'csv') {
    export_csv($assets, $filename);
} elseif ($format === 'pdf') {
    export_pdf($assets, $filename);
} else {
    header('Location: list_assets.php');
    exit();
}

function export_csv($data, $filename) {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '.csv"');
    
    $output = fopen('php://output', 'w');
    
    // Add UTF-8 BOM for Excel compatibility
    fputs($output, $bom = (chr(0xEF) . chr(0xBB) . chr(0xBF)));
    
    // CSV headers
    $headers = [
        'Asset ID',
        'Asset Name',
        'Asset Tag',
        'Asset Class',
        'Model',
        'Manufacturer',
        'Serial Number',
        'PO Number',
        'Acquisition Date',
        'Warranty Expiry',
        'Vendor',
        'Cost (RM)',
        'Depreciation Method',
        'Depreciation Rate (%)',
        'Depreciation Start Date',
        'Life Expectancy (Years)',
        'Location',
        'Remarks',
        'Created At',
        'Updated At'
    ];
    
    fputcsv($output, $headers);
    
    // Data rows
    foreach ($data as $row) {
        $warranty_status = '';
        if (!empty($row['warranty_expiry'])) {
            $expiry = new DateTime($row['warranty_expiry']);
            $today = new DateTime();
            if ($expiry < $today) {
                $warranty_status = ' (Expired)';
            } else {
                $days_left = $today->diff($expiry)->days;
                if ($days_left <= 30) {
                    $warranty_status = ' (Expiring Soon)';
                } else {
                    $warranty_status = ' (Active)';
                }
            }
        }
        
        $csv_row = [
            $row['asset_id'],
            $row['asset_name'],
            $row['asset_tag'] ?? '',
            $row['class_name'] ?? 'N/A',
            $row['model'] ?? '',
            $row['manufacturer'] ?? '',
            $row['serial_number'] ?? '',
            $row['purchase_order_number'] ?? '',
            $row['acquisition_date'] ? date('d/m/Y', strtotime($row['acquisition_date'])) : '',
            $row['warranty_expiry'] ? date('d/m/Y', strtotime($row['warranty_expiry'])) . $warranty_status : '',
            $row['vendor'] ?? '',
            number_format($row['cost'], 2),
            $row['depreciation_method'] ?? '',
            $row['depreciation_rate'] ?? '',
            $row['depreciation_start_date'] ? date('d/m/Y', strtotime($row['depreciation_start_date'])) : '',
            $row['life_expectancy_years'] ?? '',
            $row['location_name'] ?? 'N/A',
            str_replace(["\r\n", "\r", "\n"], " ", $row['remarks'] ?? ''),
            $row['created_at'] ? date('d/m/Y H:i', strtotime($row['created_at'])) : '',
            $row['updated_at'] ? date('d/m/Y H:i', strtotime($row['updated_at'])) : ''
        ];
        
        fputcsv($output, $csv_row);
    }
    
    fclose($output);
    exit();
}

function export_pdf($data, $filename) {
    // Check if TCPDF is available
    if (!class_exists('TCPDF')) {
        // Fallback to HTML output if TCPDF not available
        header('Content-Type: text/html; charset=utf-8');
        echo '<h3>PDF export requires TCPDF library</h3>';
        echo '<p>Please install TCPDF or use CSV export instead.</p>';
        exit();
    }
    
    // Create new PDF document
    $pdf = new TCPDF('L', 'mm', 'A4', true, 'UTF-8');
    
    // Set document information
    $pdf->SetCreator('Asset Management System');
    $pdf->SetAuthor('Warehouse Coordinator');
    $pdf->SetTitle('Assets Export');
    $pdf->SetSubject('Asset Inventory Report');
    
    // Remove default header/footer
    $pdf->setPrintHeader(false);
    $pdf->setPrintFooter(false);
    
    // Add a page
    $pdf->AddPage();
    
    // Set font
    $pdf->SetFont('helvetica', '', 10);
    
    // Company/Report Header
    $html = '
    <div style="text-align: center;">
        <h2>Asset Inventory Report</h2>
        <p>Generated on: ' . date('d/m/Y H:i') . '</p>
        <p>Total Assets: ' . count($data) . '</p>
    </div>
    <hr>
    ';
    
    // Filters applied (if any)
    $filters = [];
    if (!empty($search)) $filters[] = "Search: $search";
    if (!empty($filter_class)) $filters[] = "Class: $filter_class";
    if (!empty($filter_location)) $filters[] = "Location: $filter_location";
    if (!empty($filter_status)) $filters[] = "Status: $filter_status";
    
    if (!empty($filters)) {
        $html .= '<p><strong>Filters Applied:</strong> ' . implode(', ', $filters) . '</p>';
    }
    
    // Table header
    $html .= '
    <table border="1" cellpadding="4" style="border-collapse: collapse; width: 100%; font-size: 9px;">
        <thead>
            <tr style="background-color: #f2f2f2;">
                <th width="8%"><strong>Asset ID</strong></th>
                <th width="12%"><strong>Asset Name</strong></th>
                <th width="8%"><strong>Tag</strong></th>
                <th width="8%"><strong>Class</strong></th>
                <th width="10%"><strong>Model</strong></th>
                <th width="8%"><strong>Cost (RM)</strong></th>
                <th width="10%"><strong>Warranty Expiry</strong></th>
                <th width="10%"><strong>Location</strong></th>
                <th width="12%"><strong>Created</strong></th>
                <th width="14%"><strong>Remarks</strong></th>
            </tr>
        </thead>
        <tbody>
    ';
    
    // Table rows
    foreach ($data as $index => $row) {
        // Alternate row colors
        $bg_color = ($index % 2 == 0) ? '#ffffff' : '#f9f9f9';
        
        // Format dates
        $acquisition_date = $row['acquisition_date'] ? date('d/m/Y', strtotime($row['acquisition_date'])) : 'N/A';
        $warranty_expiry = $row['warranty_expiry'] ? date('d/m/Y', strtotime($row['warranty_expiry'])) : 'N/A';
        $created_at = $row['created_at'] ? date('d/m/Y', strtotime($row['created_at'])) : 'N/A';
        
        // Warranty status indicator
        $warranty_status = '';
        if (!empty($row['warranty_expiry'])) {
            $expiry = new DateTime($row['warranty_expiry']);
            $today = new DateTime();
            if ($expiry < $today) {
                $warranty_status = ' ⚠ Expired';
            } elseif ($today->diff($expiry)->days <= 30) {
                $warranty_status = ' ⚠ Soon';
            }
        }
        
        $html .= '
        <tr style="background-color: ' . $bg_color . ';">
            <td>' . htmlspecialchars($row['asset_id']) . '</td>
            <td>' . htmlspecialchars($row['asset_name']) . '</td>
            <td>' . htmlspecialchars($row['asset_tag'] ?? '') . '</td>
            <td>' . htmlspecialchars($row['class_name'] ?? 'N/A') . '</td>
            <td>' . htmlspecialchars($row['model'] ?? '') . '</td>
            <td align="right">' . number_format($row['cost'], 2) . '</td>
            <td>' . $warranty_expiry . $warranty_status . '</td>
            <td>' . htmlspecialchars($row['location_name'] ?? 'N/A') . '</td>
            <td>' . $created_at . '</td>
            <td>' . htmlspecialchars(substr($row['remarks'] ?? '', 0, 50)) . '...</td>
        </tr>
        ';
    }
    
    $html .= '
        </tbody>
    </table>
    ';
    
    // Summary statistics
    $total_cost = array_sum(array_column($data, 'cost'));
    $avg_cost = count($data) > 0 ? $total_cost / count($data) : 0;
    
    $html .= '
    <div style="margin-top: 20px; font-size: 10px;">
        <p><strong>Summary Statistics:</strong></p>
        <p>Total Assets: ' . count($data) . ' | Total Value: RM ' . number_format($total_cost, 2) . ' | Average Cost: RM ' . number_format($avg_cost, 2) . '</p>
    </div>
    
    <div style="margin-top: 20px; font-size: 8px; color: #666; text-align: center;">
        <p>Report generated by Asset Management System | ' . date('d/m/Y H:i:s') . '</p>
    </div>
    ';
    
    // Output HTML content
    $pdf->writeHTML($html, true, false, true, false, '');
    
    // Close and output PDF document
    $pdf->Output($filename . '.pdf', 'D');
    exit();
}

// If we reach here, something went wrong
header('Location: list_assets.php');
exit();