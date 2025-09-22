<?php
declare(strict_types=1);
require_once __DIR__ . '/../db.php';
require_login();

$user = $_SESSION['user'];
$pdo = get_pdo();

// Basic per-user stats
$stmt = $pdo->prepare("SELECT COUNT(*) AS c, COALESCE(SUM(quantity),0) AS q FROM transactions WHERE user_id = ? AND type = 'request'");
$stmt->execute([$user['id']]);
$reqStats = $stmt->fetch() ?: ['c' => 0, 'q' => 0];

$stmt = $pdo->prepare("SELECT COUNT(*) AS c, COALESCE(SUM(quantity),0) AS q FROM transactions WHERE user_id = ? AND type = 'return'");
$stmt->execute([$user['id']]);
$retStats = $stmt->fetch() ?: ['c' => 0, 'q' => 0];

require_once __DIR__ . '/../layouts/sidebar.php';
$pageTitle = 'User Dashboard';
render_with_sidebar($pageTitle, 'dashboard', function () use ($pdo, $user, $reqStats, $retStats) {
    ?>
    <div class="max-w-7xl mx-auto">
		<div class="mb-8">
			<h2 class="text-3xl font-bold text-gray-900 mb-2">User Dashboard</h2>
			<p class="text-gray-600">Quick access to request, return, and history</p>
		</div>

		<!-- Quick Stats -->
		<div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
			<div class="bg-white rounded-xl shadow-lg p-6">
				<div class="flex items-center">
					<div class="p-3 bg-blue-100 rounded-lg">
						<svg class="w-6 h-6 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"/></svg>
					</div>
					<div class="ml-4">
						<p class="text-sm font-medium text-gray-600">Your Requests</p>
						<p class="text-2xl font-bold text-gray-900"><?php echo (int)$reqStats['c']; ?><span class="text-sm text-gray-500"> (<?php echo (int)$reqStats['q']; ?> items)</span></p>
					</div>
				</div>
			</div>
			<div class="bg-white rounded-xl shadow-lg p-6">
				<div class="flex items-center">
					<div class="p-3 bg-green-100 rounded-lg">
						<svg class="w-6 h-6 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4"/></svg>
					</div>
					<div class="ml-4">
						<p class="text-sm font-medium text-gray-600">Your Returns</p>
						<p class="text-2xl font-bold text-gray-900"><?php echo (int)$retStats['c']; ?><span class="text-sm text-gray-500"> (<?php echo (int)$retStats['q']; ?> items)</span></p>
					</div>
				</div>
			</div>
			<div class="bg-white rounded-xl shadow-lg p-6">
				<div class="flex items-center">
					<div class="p-3 bg-gray-100 rounded-lg">
						<svg class="w-6 h-6 text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
					</div>
					<div class="ml-4">
						<p class="text-sm font-medium text-gray-600">Last Activity</p>
						<p class="text-2xl font-bold text-gray-900"><?php
							$last = $pdo->prepare("SELECT created_at FROM transactions WHERE user_id = ? ORDER BY created_at DESC LIMIT 1");
							$last->execute([$user['id']]);
							$row = $last->fetch();
							echo $row ? date('M j, Y', strtotime($row['created_at'])) : '—';
						?></p>
					</div>
				</div>
			</div>
		</div>

		<!-- Quick Actions -->
		<div class="grid grid-cols-1 md:grid-cols-3 gap-6">
			<div class="bg-white rounded-xl shadow-lg p-6 hover:shadow-xl transition">
				<div class="flex items-center mb-4">
					<div class="p-3 bg-blue-100 rounded-lg">
						<svg class="w-6 h-6 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"/></svg>
					</div>
					<h3 class="text-lg font-semibold text-gray-900 ml-3">Request Items</h3>
				</div>
				<p class="text-gray-600 mb-4">Request products from inventory with digital signature.</p>
				<a href="/autonomic/user/request.php" class="inline-flex items-center px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white text-sm font-medium rounded-lg transition">Make Request<svg class="w-4 h-4 ml-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg></a>
			</div>

			<div class="bg-white rounded-xl shadow-lg p-6 hover:shadow-xl transition">
				<div class="flex items-center mb-4">
					<div class="p-3 bg-orange-100 rounded-lg">
						<svg class="w-6 h-6 text-orange-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4"/></svg>
					</div>
					<h3 class="text-lg font-semibold text-gray-900 ml-3">Return Items</h3>
				</div>
				<p class="text-gray-600 mb-4">Return previously requested items to inventory.</p>
				<a href="/autonomic/user/return.php" class="inline-flex items-center px-4 py-2 bg-orange-600 hover:bg-orange-700 text-white text-sm font-medium rounded-lg transition">Return Items<svg class="w-4 h-4 ml-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg></a>
			</div>

			<div class="bg-white rounded-xl shadow-lg p-6 hover:shadow-xl transition">
				<div class="flex items-center mb-4">
					<div class="p-3 bg-gray-100 rounded-lg">
						<svg class="w-6 h-6 text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
					</div>
					<h3 class="text-lg font-semibold text-gray-900 ml-3">History</h3>
				</div>
				<p class="text-gray-600 mb-4">View your request and return history and download PDFs.</p>
				<a href="/autonomic/user/history.php" class="inline-flex items-center px-4 py-2 bg-gray-600 hover:bg-gray-700 text-white text-sm font-medium rounded-lg transition">View History<svg class="w-4 h-4 ml-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg></a>
			</div>
		</div>
    </div>
    <?php
});
