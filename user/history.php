<?php

declare(strict_types=1);
require_once __DIR__ . '/../db.php';
require_login();

$user = $_SESSION['user'];
$pdo = get_pdo();

// Get filter parameters
$date_from = $_GET['date_from'] ?? '';
$date_to = $_GET['date_to'] ?? '';
$product_filter = (int)($_GET['product_id'] ?? 0);
$type_filter = $_GET['type'] ?? '';

// Build WHERE clause
$where_conditions = ['t.user_id = ?'];
$params = [$user['id']];

if ($date_from) {
    $where_conditions[] = 't.created_at >= ?';
    $params[] = $date_from . ' 00:00:00';
}

if ($date_to) {
    $where_conditions[] = 't.created_at <= ?';
    $params[] = $date_to . ' 23:59:59';
}

if ($product_filter) {
    $where_conditions[] = 't.product_id = ?';
    $params[] = $product_filter;
}

if ($type_filter && in_array($type_filter, ['request', 'return'])) {
    $where_conditions[] = 't.type = ?';
    $params[] = $type_filter;
}

$where_clause = 'WHERE ' . implode(' AND ', $where_conditions);

// Get transactions
$sql = "
    SELECT t.*, p.name as product_name, p.code as product_code, p.serial, p.model
FROM transactions t
    JOIN products p ON t.product_id = p.id
    {$where_clause}
    ORDER BY t.created_at DESC
";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$transactions = $stmt->fetchAll();

// Get products for filter dropdown
$stmt = $pdo->query('SELECT id, name, code FROM products ORDER BY name');
$products = $stmt->fetchAll();

// Calculate summary statistics
$summary_sql = "
    SELECT 
        COUNT(*) as total_transactions,
        SUM(CASE WHEN type = 'request' THEN 1 ELSE 0 END) as total_requests,
        SUM(CASE WHEN type = 'return' THEN 1 ELSE 0 END) as total_returns,
        SUM(CASE WHEN type = 'request' THEN quantity ELSE 0 END) as total_requested_qty,
        SUM(CASE WHEN type = 'return' THEN quantity ELSE 0 END) as total_returned_qty
    FROM transactions t
    {$where_clause}
";
$summary_stmt = $pdo->prepare($summary_sql);
$summary_stmt->execute($params);
$summary = $summary_stmt->fetch();

require_once __DIR__ . '/../layouts/sidebar.php';
$pageTitle = 'Transaction History';
render_with_sidebar($pageTitle, 'history', function () use ($summary, $date_from, $date_to, $product_filter, $type_filter, $products, $transactions) {
?>
    <div class="max-w-7xl mx-auto">
        <div class="mb-8">
            <h2 class="text-3xl font-bold text-gray-900 mb-2">Transaction History</h2>
            <p class="text-gray-600">View your request and return history</p>
        </div>

        <!-- Summary Statistics -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-5 gap-6 mb-8">
            <div class="bg-white rounded-xl shadow-lg py-6 px-4">
                <div class="flex items-center">
                    <div class="p-3 bg-blue-100 rounded-lg">
                        <svg class="w-6 h-6 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v10a2 2 0 002 2h8a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"></path>
                        </svg>
                    </div>
                    <div class="ml-4">
                        <p class="text-sm font-medium text-gray-600">Total Transactions</p>
                        <p class="text-2xl font-bold text-gray-900"><?php echo number_format((int)$summary['total_transactions']); ?></p>
                    </div>
                </div>
            </div>

            <div class="bg-white rounded-xl shadow-lg py-6 px-4">
                <div class="flex items-center">
                    <div class="p-3 bg-orange-100 rounded-lg">
                        <svg class="w-6 h-6 text-orange-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path>
                        </svg>
                    </div>
                    <div class="ml-4">
                        <p class="text-sm font-medium text-gray-600">Total Requests</p>
                        <p class="text-2xl font-bold text-gray-900"><?php echo number_format((int)$summary['total_requests']); ?></p>
                    </div>
                </div>
            </div>

            <div class="bg-white rounded-xl shadow-lg py-6 px-4">
                <div class="flex items-center">
                    <div class="p-3 bg-green-100 rounded-lg">
                        <svg class="w-6 h-6 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4"></path>
                        </svg>
                    </div>
                    <div class="ml-4">
                        <p class="text-sm font-medium text-gray-600">Total Returns</p>
                        <p class="text-2xl font-bold text-gray-900"><?php echo number_format((int)$summary['total_returns']); ?></p>
                    </div>
                </div>
            </div>

            <div class="bg-white rounded-xl shadow-lg py-6 px-4">
                <div class="flex items-center">
                    <div class="p-3 bg-purple-100 rounded-lg">
                        <svg class="w-6 h-6 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"></path>
                        </svg>
                    </div>
                    <div class="ml-4">
                        <p class="text-sm font-medium text-gray-600">Requested Qty</p>
                        <p class="text-2xl font-bold text-gray-900"><?php echo number_format((int)$summary['total_requested_qty']); ?></p>
                    </div>
                </div>
            </div>

            <div class="bg-white rounded-xl shadow-lg py-6 px-4">
                <div class="flex items-center">
                    <div class="p-3 bg-red-100 rounded-lg">
                        <svg class="w-6 h-6 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path>
                        </svg>
                    </div>
                    <div class="ml-4">
                        <p class="text-sm font-medium text-gray-600">Returned Qty</p>
                        <p class="text-2xl font-bold text-gray-900"><?php echo number_format((int)$summary['total_returned_qty']); ?></p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Filters -->
        <div class="bg-white rounded-xl shadow-lg p-6 mb-8">
            <h3 class="text-lg font-semibold text-gray-900 mb-4">Filters</h3>
            <form method="get" action="" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
                <div>
                    <label for="date_from" class="block text-sm font-medium text-gray-700 mb-2">Date From</label>
                    <input type="date" id="date_from" name="date_from" value="<?php echo e($date_from); ?>"
                        class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent" />
                </div>

                <div>
                    <label for="date_to" class="block text-sm font-medium text-gray-700 mb-2">Date To</label>
                    <input type="date" id="date_to" name="date_to" value="<?php echo e($date_to); ?>"
                        class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent" />
                </div>

                <div>
                    <label for="product_id" class="block text-sm font-medium text-gray-700 mb-2">Product</label>
                    <select id="product_id" name="product_id"
                        class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                        <option value="">All Products</option>
                        <?php foreach ($products as $product): ?>
                            <option value="<?php echo $product['id']; ?>" <?php echo $product_filter == $product['id'] ? 'selected' : ''; ?>>
                                <?php echo e($product['name'] . ' (' . $product['code'] . ')'); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div>
                    <label for="type" class="block text-sm font-medium text-gray-700 mb-2">Type</label>
                    <select id="type" name="type"
                        class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                        <option value="">All Types</option>
                        <option value="request" <?php echo $type_filter === 'request' ? 'selected' : ''; ?>>Request</option>
                        <option value="return" <?php echo $type_filter === 'return' ? 'selected' : ''; ?>>Return</option>
                    </select>
                </div>

                <div class="md:col-span-2 lg:col-span-4 flex gap-3">
                    <button type="submit"
                        class="bg-blue-600 hover:bg-blue-700 text-white px-6 py-2 rounded-lg transition duration-200">
                        Apply Filters
                    </button>
                    <a href="/autonomic/user/history.php"
                        class="bg-gray-600 hover:bg-gray-700 text-white px-6 py-2 rounded-lg transition duration-200">
                        Clear Filters
                    </a>
                </div>
            </form>
        </div>

        <!-- Transactions Table -->
        <div class="bg-white rounded-xl shadow-lg overflow-hidden">
            <div class="px-6 py-4 border-b border-gray-200">
                <h3 class="text-lg font-semibold text-gray-900">Your Transactions (<?php echo count($transactions); ?>)</h3>
            </div>

            <?php if (empty($transactions)): ?>
                <div class="text-center py-12">
                    <svg class="w-12 h-12 text-gray-400 mx-auto mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v10a2 2 0 002 2h8a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"></path>
                    </svg>
                    <h3 class="text-lg font-medium text-gray-900 mb-2">No transactions found</h3>
                    <p class="text-gray-600 mb-4">You haven't made any requests or returns yet.</p>
                    <div class="space-x-3">
                        <a href="/autonomic/user/request.php"
                            class="inline-flex items-center px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white text-sm font-medium rounded-lg transition duration-200">
                            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path>
                            </svg>
                            Make Request
                        </a>
                        <a href="/autonomic/user/return.php"
                            class="inline-flex items-center px-4 py-2 bg-orange-600 hover:bg-orange-700 text-white text-sm font-medium rounded-lg transition duration-200">
                            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4"></path>
                            </svg>
                            Return Items
                        </a>
                    </div>
                </div>
            <?php else: ?>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Product</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Type</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Quantity</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <?php foreach ($transactions as $transaction): ?>
                                <tr>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                        <?php echo date('M j, Y g:i A', strtotime($transaction['created_at'])); ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                        <div>
                                            <div class="font-medium"><?php echo e($transaction['product_name']); ?></div>
                                            <div class="text-gray-500"><?php echo e($transaction['product_code']); ?></div>
                                            <?php if ($transaction['serial']): ?>
                                                <div class="text-gray-500">Serial: <?php echo e($transaction['serial']); ?></div>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <span class="px-2 py-1 text-xs font-medium rounded-full <?php echo $transaction['type'] === 'request' ? 'bg-blue-100 text-blue-800' : 'bg-green-100 text-green-800'; ?>">
                                            <?php echo ucfirst($transaction['type']); ?>
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                        <?php echo number_format($transaction['quantity']); ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                        <button onclick="viewTransaction(<?php echo htmlspecialchars(json_encode($transaction)); ?>)"
                                            class="text-blue-600 hover:text-blue-900 mr-3">View Details</button>
                                        <?php if ($transaction['pdf_filename']): ?>
                                                <a href="/autonomic/export_pdf.php?type=<?php echo $transaction['type']; ?>&id=<?php echo $transaction['id']; ?>" target="_blank"
                                                    class="text-green-600 hover:text-green-900">Download PDF</a>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Transaction Details Modal -->
    <div id="transactionModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 hidden z-50">
        <div class="flex items-center justify-center min-h-screen p-4">
            <div class="bg-white rounded-xl shadow-xl p-6 w-full max-w-lg">
                <h3 class="text-lg font-semibold text-gray-900 mb-4">Transaction Details</h3>
                <div id="transactionDetails" class="space-y-4">
                    <!-- Transaction details will be populated here -->
                </div>

                <div class="flex justify-end mt-6">
                    <button onclick="closeTransactionModal()"
                        class="px-4 py-2 bg-gray-600 hover:bg-gray-700 text-white rounded-lg transition duration-200">
                        Close
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script>
        function viewTransaction(transaction) {
            const details = document.getElementById('transactionDetails');
            details.innerHTML = `
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Transaction ID</label>
                        <p class="text-sm text-gray-900">#${transaction.id}</p>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Date</label>
                        <p class="text-sm text-gray-900">${new Date(transaction.created_at).toLocaleString()}</p>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Type</label>
                        <p class="text-sm text-gray-900">${transaction.type.charAt(0).toUpperCase() + transaction.type.slice(1)}</p>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Quantity</label>
                        <p class="text-sm text-gray-900">${transaction.quantity}</p>
                    </div>
                    <div class="col-span-2">
                        <label class="block text-sm font-medium text-gray-700">Product</label>
                        <p class="text-sm text-gray-900">${transaction.product_name}</p>
                        <p class="text-xs text-gray-500">Code: ${transaction.product_code}</p>
                        ${transaction.serial ? `<p class="text-xs text-gray-500">Serial: ${transaction.serial}</p>` : ''}
                        ${transaction.model ? `<p class="text-xs text-gray-500">Model: ${transaction.model}</p>` : ''}
                    </div>
                </div>
            `;
            document.getElementById('transactionModal').classList.remove('hidden');
        }

        function closeTransactionModal() {
            document.getElementById('transactionModal').classList.add('hidden');
        }

        // Close modal when clicking outside
        document.getElementById('transactionModal').addEventListener('click', function(e) {
            if (e.target === this) closeTransactionModal();
        });
    </script>
<?php
});
