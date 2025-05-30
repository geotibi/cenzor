<?php
require 'auth.php'; // restrict access to logged-in users
require 'db.php'; // database connection

// Define the months we want to display
$months = [
    '2025-03' => 'March\'25',
    '2025-04' => 'April\'25',
    '2025-05' => 'May\'25',
    '2025-06' => 'June\'25'
];

// Query to get all apartments
$apartments_sql = "SELECT a.id, a.scara, a.number 
                  FROM apartments a 
                  ORDER BY a.scara, a.id";
$apartments_result = $conn->query($apartments_sql);

// Prepare data structure
$apartments_data = [];
while ($apt = $apartments_result->fetch_assoc()) {
    $apartments_data[$apt['id']] = [
        'scara' => $apt['scara'],
        'number' => $apt['number'],
        'last_update' => null,
        'months' => array_fill_keys(array_keys($months), null)
    ];
}

// Get the latest update date for each apartment
$last_update_sql = "SELECT apartment_id, MAX(month_key) as last_update
                   FROM apartment_occupancy
                   GROUP BY apartment_id";
$last_update_result = $conn->query($last_update_sql);

while ($row = $last_update_result->fetch_assoc()) {
    if (isset($apartments_data[$row['apartment_id']])) {
        $apartments_data[$row['apartment_id']]['last_update'] = $row['last_update'];
    }
}

// For each month, get the latest occupancy data up to that month
foreach ($months as $month_key => $month_label) {
    // Query to get the latest occupancy data for each apartment up to this month
    $month_sql = "SELECT ao.apartment_id, ao.num_people
                 FROM apartment_occupancy ao
                 INNER JOIN (
                     SELECT apartment_id, MAX(month_key) as latest_month
                     FROM apartment_occupancy
                     WHERE month_key <= '$month_key'
                     GROUP BY apartment_id
                 ) latest ON ao.apartment_id = latest.apartment_id AND ao.month_key = latest.latest_month";
    
    $month_result = $conn->query($month_sql);
    
    while ($row = $month_result->fetch_assoc()) {
        $apt_id = $row['apartment_id'];
        if (isset($apartments_data[$apt_id])) {
            $apartments_data[$apt_id]['months'][$month_key] = $row['num_people'];
        }
    }
}

// Include the header
require 'header.php';
?>

<div class="container">
    <div class="row">
        <div class="col-12">
            <h1 class="mb-4">Number of Persons per Apartment</h1>
            <p class="lead">Each month shows the most recent data available up to that month</p>
            
            <!-- Apartment Occupancy Table -->
            <div class="table-responsive">
                <table class="table table-bordered table-hover">
                    <thead class="table-dark">
                        <tr>
                            <th>Scara</th>
                            <th>Apartment</th>
                            <th>Last Update</th>
                            <?php foreach ($months as $key => $label): ?>
                                <th><?php echo $label; ?></th>
                            <?php endforeach; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($apartments_data)): ?>
                            <?php foreach ($apartments_data as $apt_id => $data): ?>
                                <tr>
                                    <td><?php echo $data['scara']; ?></td>
                                    <td><?php echo $data['number']; ?></td>
                                    <td><?php echo $data['last_update'] ?? 'N/A'; ?></td>
                                    <?php 
                                    $prev_value = null;
                                    foreach ($data['months'] as $month => $people): 
                                        $changed = ($prev_value !== $people && $people !== null);
                                        $prev_value = $people;
                                    ?>
                                        <td class="text-center <?php echo $changed ? 'fw-bold bg-light' : ''; ?>">
                                            <?php echo $people !== null ? $people : '-'; ?>
                                        </td>
                                    <?php endforeach; ?>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="<?php echo 3 + count($months); ?>" class="text-center">No apartments found.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            
            <!-- Summary Section -->
            <div class="card mt-4">
                <div class="card-header bg-dark text-white">
                    <h5 class="mb-0">Monthly Totals</h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <?php 
                        // Calculate totals for each month
                        $monthly_totals = array_fill_keys(array_keys($months), 0);
                        $monthly_apartments = array_fill_keys(array_keys($months), 0);
                        
                        foreach ($apartments_data as $apt_id => $data) {
                            foreach ($data['months'] as $month => $people) {
                                if ($people !== null) {
                                    $monthly_totals[$month] += $people;
                                    $monthly_apartments[$month]++;
                                }
                            }
                        }
                        
                        foreach ($months as $month_key => $month_label):
                            $avg = $monthly_apartments[$month_key] > 0 ? 
                                   number_format($monthly_totals[$month_key] / $monthly_apartments[$month_key], 1) : 0;
                        ?>
                            <div class="col-md-3 col-sm-6">
                                <div class="card text-center mb-3">
                                    <div class="card-header">
                                        <h5 class="mb-0"><?php echo $month_label; ?></h5>
                                    </div>
                                    <div class="card-body">
                                        <div class="row">
                                            <div class="col-6">
                                                <h6>Total</h6>
                                                <p class="display-6"><?php echo $monthly_totals[$month_key]; ?></p>
                                            </div>
                                            <div class="col-6">
                                                <h6>Avg/Apt</h6>
                                                <p class="display-6"><?php echo $avg; ?></p>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="card-footer text-muted">
                                        <?php echo $monthly_apartments[$month_key]; ?> apartments
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require 'footer.php'; // include the footer ?>