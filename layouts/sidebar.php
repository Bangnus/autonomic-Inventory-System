<?php
declare(strict_types=1);
require_once __DIR__ . '/../db.php';

if (session_status() !== PHP_SESSION_ACTIVE) {
	session_start();
}

/**
 * Render a page with a responsive sidebar layout.
 * $activeMenu should be one of: admin: dashboard, users, products, reports; user: dashboard, request, return, history; common: logout
 */
function render_with_sidebar(string $pageTitle, string $activeMenu, callable $renderContent): void {
	$user = $_SESSION['user'] ?? null;
	$isAdmin = $user && ($user['role'] === 'admin');
	?>
<!DOCTYPE html>
<html lang="en">
<head>
	<meta charset="UTF-8" />
	<meta name="viewport" content="width=device-width, initial-scale=1.0" />
	<title><?php echo htmlspecialchars($pageTitle, ENT_QUOTES, 'UTF-8'); ?></title>
	<script src="https://cdn.tailwindcss.com"></script>
	<script>
		tailwind.config = { theme: { extend: { colors: { primary: '#3b82f6', secondary: '#64748b' } } } }
	</script>
</head>
<body class="bg-gray-50 min-h-screen">
	<div class="min-h-screen flex">
		<!-- Sidebar -->
		<aside id="sidebar" class="fixed inset-y-0 left-0 z-40 w-72 transform -translate-x-full md:translate-x-0 transition-transform duration-200 bg-white border-r border-gray-200 shadow-sm">
			<div class="h-16 flex items-center px-4 border-b">
				<span class="text-lg font-bold text-gray-900">Inventory System</span>
			</div>
			<nav class="p-4 space-y-1 overflow-y-auto h-[calc(100vh-4rem)]">
				<?php if ($isAdmin): ?>
					<a href="/autonomic/admin/dashboard.php" class="flex items-center px-3 py-2 rounded-lg text-sm font-medium <?php echo $activeMenu==='dashboard'?'bg-blue-50 text-blue-700':'text-gray-700 hover:bg-gray-100'; ?>">
						<span class="mr-3">📊</span> Dashboard
					</a>
					<a href="/autonomic/admin/manage_users.php" class="flex items-center px-3 py-2 rounded-lg text-sm font-medium <?php echo $activeMenu==='users'?'bg-blue-50 text-blue-700':'text-gray-700 hover:bg-gray-100'; ?>">
						<span class="mr-3">👤</span> Manage Users
					</a>
					<a href="/autonomic/admin/manage_products.php" class="flex items-center px-3 py-2 rounded-lg text-sm font-medium <?php echo $activeMenu==='products'?'bg-blue-50 text-blue-700':'text-gray-700 hover:bg-gray-100'; ?>">
						<span class="mr-3">📦</span> Manage Products
					</a>
					<a href="/autonomic/admin/reports.php" class="flex items-center px-3 py-2 rounded-lg text-sm font-medium <?php echo $activeMenu==='reports'?'bg-blue-50 text-blue-700':'text-gray-700 hover:bg-gray-100'; ?>">
						<span class="mr-3">🧾</span> Reports
					</a>
				<?php else: ?>
					<a href="/autonomic/user/dashboard.php" class="flex items-center px-3 py-2 rounded-lg text-sm font-medium <?php echo $activeMenu==='dashboard'?'bg-blue-50 text-blue-700':'text-gray-700 hover:bg-gray-100'; ?>">
						<span class="mr-3">🏠</span> Dashboard
					</a>
					<a href="/autonomic/user/request.php" class="flex items-center px-3 py-2 rounded-lg text-sm font-medium <?php echo $activeMenu==='request'?'bg-blue-50 text-blue-700':'text-gray-700 hover:bg-gray-100'; ?>">
						<span class="mr-3">➕</span> Request Item (เบิกของ)
					</a>
					<a href="/autonomic/user/return.php" class="flex items-center px-3 py-2 rounded-lg text-sm font-medium <?php echo $activeMenu==='return'?'bg-blue-50 text-blue-700':'text-gray-700 hover:bg-gray-100'; ?>">
						<span class="mr-3">↩️</span> Return Item (คืนของ)
					</a>
					<a href="/autonomic/user/history.php" class="flex items-center px-3 py-2 rounded-lg text-sm font-medium <?php echo $activeMenu==='history'?'bg-blue-50 text-blue-700':'text-gray-700 hover:bg-gray-100'; ?>">
						<span class="mr-3">🕘</span> History (ประวัติการเบิก-คืน)
					</a>
				<?php endif; ?>
				<hr class="my-3">
				<a href="/autonomic/logout.php" class="flex items-center px-3 py-2 rounded-lg text-sm font-medium text-gray-700 hover:bg-red-50 hover:text-red-700">
					<span class="mr-3">🚪</span> Logout
				</a>
			</nav>
		</aside>

		<!-- Main area -->
		<div class="flex-1 md:ml-72">
			<header class="h-16 bg-white border-b flex items-center px-4 justify-between sticky top-0 z-30">
				<button id="sidebarToggle" class="md:hidden inline-flex items-center px-3 py-2 border rounded-lg text-gray-600 hover:bg-gray-100">
					<svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/></svg>
				</button>
				<div class="flex-1 px-2">
					<h1 class="text-lg font-semibold text-gray-900 truncate"><?php echo htmlspecialchars($pageTitle, ENT_QUOTES, 'UTF-8'); ?></h1>
				</div>
				<?php if ($user): ?>
					<div class="text-sm text-gray-600">Hello, <span class="font-medium"><?php echo e($user['name']); ?></span>
						<span class="ml-2 px-2 py-1 text-xs rounded-full <?php echo $isAdmin? 'bg-purple-100 text-purple-700':'bg-blue-100 text-blue-700'; ?>"><?php echo e($user['role']); ?></span>
					</div>
				<?php endif; ?>
			</header>
			<main class="p-4 sm:p-6 lg:p-8">
				<?php $renderContent(); ?>
			</main>
		</div>
	</div>

	<script>
		const toggleBtn = document.getElementById('sidebarToggle');
		const sidebar = document.getElementById('sidebar');
		if (toggleBtn) {
			toggleBtn.addEventListener('click', () => {
				sidebar.classList.toggle('-translate-x-full');
			});
		}
		// Close sidebar when clicking outside on mobile
		document.addEventListener('click', (e) => {
			if (window.innerWidth >= 768) return;
			if (!sidebar.contains(e.target) && !toggleBtn.contains(e.target) && !sidebar.classList.contains('-translate-x-full')) {
				sidebar.classList.add('-translate-x-full');
			}
		});
	</script>
</body>
</html>
<?php }
