<?php
declare(strict_types=1);
require_once __DIR__ . '/../db.php';
require_admin();

$user = $_SESSION['user'];
$pdo = get_pdo();

$error = '';
$success = '';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = $_POST['action'] ?? '';
    
    if ($action === 'create') {
        $code = trim((string)($_POST['code'] ?? ''));
        $name = trim((string)($_POST['name'] ?? ''));
        $serial = trim((string)($_POST['serial'] ?? ''));
        $model = trim((string)($_POST['model'] ?? ''));
        $stock_quantity = (int)($_POST['stock_quantity'] ?? 0);
        
        if ($code && $name && $stock_quantity >= 0) {
            try {
                $stmt = $pdo->prepare('INSERT INTO products (code, name, serial, model, stock_quantity) VALUES (?, ?, ?, ?, ?)');
                $stmt->execute([$code, $name, $serial, $model, $stock_quantity]);
                $success = 'Product created successfully!';
            } catch (Exception $e) {
                $error = 'Failed to create product. Product code might already exist.';
            }
        } else {
            $error = 'Please fill in all required fields correctly.';
        }
    } elseif ($action === 'update') {
        $product_id = (int)($_POST['product_id'] ?? 0);
        $code = trim((string)($_POST['code'] ?? ''));
        $name = trim((string)($_POST['name'] ?? ''));
        $serial = trim((string)($_POST['serial'] ?? ''));
        $model = trim((string)($_POST['model'] ?? ''));
        $stock_quantity = (int)($_POST['stock_quantity'] ?? 0);
        
        if ($product_id && $code && $name && $stock_quantity >= 0) {
            try {
                $stmt = $pdo->prepare('UPDATE products SET code = ?, name = ?, serial = ?, model = ?, stock_quantity = ? WHERE id = ?');
                $stmt->execute([$code, $name, $serial, $model, $stock_quantity, $product_id]);
                $success = 'Product updated successfully!';
            } catch (Exception $e) {
                $error = 'Failed to update product. Product code might already exist.';
            }
        } else {
            $error = 'Please fill in all required fields correctly.';
        }
    } elseif ($action === 'delete') {
        $product_id = (int)($_POST['product_id'] ?? 0);
        
        if ($product_id) {
            try {
                // Check if product has transactions
                $stmt = $pdo->prepare('SELECT COUNT(*) as count FROM transactions WHERE product_id = ?');
                $stmt->execute([$product_id]);
                $transaction_count = $stmt->fetch()['count'];
                
                if ($transaction_count > 0) {
                    $error = 'Cannot delete product with existing transactions.';
                } else {
                    $stmt = $pdo->prepare('DELETE FROM products WHERE id = ?');
                    $stmt->execute([$product_id]);
                    $success = 'Product deleted successfully!';
                }
            } catch (Exception $e) {
                $error = 'Failed to delete product.';
            }
        } else {
            $error = 'Invalid product ID.';
        }
    }
}

// Get all products with search functionality
$search = $_GET['search'] ?? '';
$where_clause = '';
$params = [];

if ($search) {
    $where_clause = 'WHERE code LIKE ? OR name LIKE ? OR serial LIKE ? OR model LIKE ?';
    $search_param = "%{$search}%";
    $params = [$search_param, $search_param, $search_param, $search_param];
}

$sql = "SELECT * FROM products {$where_clause} ORDER BY created_at DESC";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$products = $stmt->fetchAll();

require_once __DIR__ . '/../layouts/sidebar.php';
$pageTitle = 'Manage Products';
render_with_sidebar($pageTitle, 'products', function () use ($error, $success, $search, $products) {
    ?>
    <div class="max-w-7xl mx-auto">
        <div class="mb-8">
            <h2 class="text-3xl font-bold text-gray-900 mb-2">Manage Products</h2>
            <p class="text-gray-600">Create, update, and delete product inventory</p>
        </div>

        <?php if ($error): ?>
            <div class="bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-lg mb-6">
                <div class="flex">
                    <svg class="w-5 h-5 mr-2 mt-0.5" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"></path>
                    </svg>
                    <?php echo e($error); ?>
                </div>
            </div>
        <?php endif; ?>

        <?php if ($success): ?>
            <div class="bg-green-50 border border-green-200 text-green-700 px-4 py-3 rounded-lg mb-6">
                <div class="flex">
                    <svg class="w-5 h-5 mr-2 mt-0.5" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"></path>
                    </svg>
                    <?php echo e($success); ?>
                </div>
            </div>
        <?php endif; ?>

        <!-- Search Bar -->
        <div class="bg-white rounded-xl shadow-lg p-6 mb-8">
            <form method="get" action="" class="flex gap-4">
                <div class="flex-1">
                    <input type="text" name="search" value="<?php echo e($search); ?>" 
                           placeholder="Search by code, name, serial, or model..." 
                           class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent" />
                </div>
                <button type="submit" 
                        class="bg-blue-600 hover:bg-blue-700 text-white px-6 py-2 rounded-lg transition duration-200">
                    Search
                </button>
                <?php if ($search): ?>
                    <a href="/autonomic/admin/manage_products.php" 
                       class="bg-gray-600 hover:bg-gray-700 text-white px-6 py-2 rounded-lg transition duration-200">
                        Clear
                    </a>
                <?php endif; ?>
            </form>
        </div>

        <!-- Create Product Form -->
        <div class="bg-white rounded-xl shadow-lg p-6 mb-8">
            <h3 class="text-lg font-semibold text-gray-900 mb-4">Create New Product</h3>
            <form method="post" action="" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-5 gap-4">
                <input type="hidden" name="csrf_token" value="<?php echo e(csrf_token()); ?>" />
                <input type="hidden" name="action" value="create" />
                
                <div>
                    <label for="code" class="block text-sm font-medium text-gray-700 mb-2">Product Code *</label>
                    <input type="text" id="code" name="code" required 
                           class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent" 
                           placeholder="Enter product code" />
                </div>
                
                <div>
                    <label for="name" class="block text-sm font-medium text-gray-700 mb-2">Product Name *</label>
                    <input type="text" id="name" name="name" required 
                           class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent" 
                           placeholder="Enter product name" />
                </div>
                
                <div>
                    <label for="serial" class="block text-sm font-medium text-gray-700 mb-2">Serial Number</label>
                    <input type="text" id="serial" name="serial" 
                           class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent" 
                           placeholder="Enter serial number" />
                </div>
                
                <div>
                    <label for="model" class="block text-sm font-medium text-gray-700 mb-2">Model</label>
                    <input type="text" id="model" name="model" 
                           class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent" 
                           placeholder="Enter model" />
                </div>
                
                <div>
                    <label for="stock_quantity" class="block text-sm font-medium text-gray-700 mb-2">Stock Quantity *</label>
                    <input type="number" id="stock_quantity" name="stock_quantity" required min="0" 
                           class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent" 
                           placeholder="0" />
                </div>
                
                <div class="md:col-span-2 lg:col-span-5">
                    <button type="submit" 
                            class="bg-blue-600 hover:bg-blue-700 text-white font-medium py-2 px-4 rounded-lg transition duration-200">
                        Create Product
                    </button>
                </div>
            </form>
        </div>

        <!-- Products Table -->
        <div class="bg-white rounded-xl shadow-lg overflow-hidden">
            <div class="px-6 py-4 border-b border-gray-200">
                <h3 class="text-lg font-semibold text-gray-900">All Products (<?php echo count($products); ?>)</h3>
            </div>
            
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Code</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Name</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Serial</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Model</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Stock</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Created</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <?php foreach ($products as $product): ?>
                            <tr>
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                    <?php echo e($product['code']); ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                    <?php echo e($product['name']); ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                    <?php echo e($product['serial'] ?: '-'); ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                    <?php echo e($product['model'] ?: '-'); ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <span class="px-2 py-1 text-xs font-medium rounded-full <?php echo $product['stock_quantity'] < 10 ? 'bg-red-100 text-red-800' : ($product['stock_quantity'] < 50 ? 'bg-yellow-100 text-yellow-800' : 'bg-green-100 text-green-800'); ?>">
                                        <?php echo number_format($product['stock_quantity']); ?>
                                    </span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                    <?php echo date('M j, Y', strtotime($product['created_at'])); ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                    <button onclick="openEditModal(<?php echo htmlspecialchars(json_encode($product)); ?>)" 
                                            class="text-blue-600 hover:text-blue-900 mr-3">Edit</button>
                                    <button onclick="confirmDelete(<?php echo $product['id']; ?>, '<?php echo e($product['name']); ?>')" 
                                            class="text-red-600 hover:text-red-900">Delete</button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Edit Product Modal -->
    <div id="editModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 hidden z-50">
        <div class="flex items-center justify-center min-h-screen p-4">
            <div class="bg-white rounded-xl shadow-xl p-6 w-full max-w-md">
                <h3 class="text-lg font-semibold text-gray-900 mb-4">Edit Product</h3>
                <form id="editForm" method="post" action="">
                    <input type="hidden" name="csrf_token" value="<?php echo e(csrf_token()); ?>" />
                    <input type="hidden" name="action" value="update" />
                    <input type="hidden" name="product_id" id="edit_product_id" />
                    
                    <div class="space-y-4">
                        <div>
                            <label for="edit_code" class="block text-sm font-medium text-gray-700 mb-2">Product Code *</label>
                            <input type="text" id="edit_code" name="code" required 
                                   class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent" />
                        </div>
                        
                        <div>
                            <label for="edit_name" class="block text-sm font-medium text-gray-700 mb-2">Product Name *</label>
                            <input type="text" id="edit_name" name="name" required 
                                   class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent" />
                        </div>
                        
                        <div>
                            <label for="edit_serial" class="block text-sm font-medium text-gray-700 mb-2">Serial Number</label>
                            <input type="text" id="edit_serial" name="serial" 
                                   class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent" />
                        </div>
                        
                        <div>
                            <label for="edit_model" class="block text-sm font-medium text-gray-700 mb-2">Model</label>
                            <input type="text" id="edit_model" name="model" 
                                   class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent" />
                        </div>
                        
                        <div>
                            <label for="edit_stock_quantity" class="block text-sm font-medium text-gray-700 mb-2">Stock Quantity *</label>
                            <input type="number" id="edit_stock_quantity" name="stock_quantity" required min="0" 
                                   class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent" />
                        </div>
                    </div>
                    
                    <div class="flex justify-end space-x-3 mt-6">
                        <button type="button" onclick="closeEditModal()" 
                                class="px-4 py-2 text-gray-700 bg-gray-200 hover:bg-gray-300 rounded-lg transition duration-200">
                            Cancel
                        </button>
                        <button type="submit" 
                                class="px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-lg transition duration-200">
                            Update Product
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Delete Confirmation Modal -->
    <div id="deleteModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 hidden z-50">
        <div class="flex items-center justify-center min-h-screen p-4">
            <div class="bg-white rounded-xl shadow-xl p-6 w-full max-w-md">
                <h3 class="text-lg font-semibold text-gray-900 mb-4">Confirm Delete</h3>
                <p class="text-gray-600 mb-6">Are you sure you want to delete product <span id="deleteProductName" class="font-semibold"></span>? This action cannot be undone.</p>
                
                <form id="deleteForm" method="post" action="">
                    <input type="hidden" name="csrf_token" value="<?php echo e(csrf_token()); ?>" />
                    <input type="hidden" name="action" value="delete" />
                    <input type="hidden" name="product_id" id="delete_product_id" />
                    
                    <div class="flex justify-end space-x-3">
                        <button type="button" onclick="closeDeleteModal()" 
                                class="px-4 py-2 text-gray-700 bg-gray-200 hover:bg-gray-300 rounded-lg transition duration-200">
                            Cancel
                        </button>
                        <button type="submit" 
                                class="px-4 py-2 bg-red-600 hover:bg-red-700 text-white rounded-lg transition duration-200">
                            Delete Product
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        function openEditModal(product) {
            document.getElementById('edit_product_id').value = product.id;
            document.getElementById('edit_code').value = product.code;
            document.getElementById('edit_name').value = product.name;
            document.getElementById('edit_serial').value = product.serial || '';
            document.getElementById('edit_model').value = product.model || '';
            document.getElementById('edit_stock_quantity').value = product.stock_quantity;
            document.getElementById('editModal').classList.remove('hidden');
        }

        function closeEditModal() {
            document.getElementById('editModal').classList.add('hidden');
        }

        function confirmDelete(productId, productName) {
            document.getElementById('delete_product_id').value = productId;
            document.getElementById('deleteProductName').textContent = productName;
            document.getElementById('deleteModal').classList.remove('hidden');
        }

        function closeDeleteModal() {
            document.getElementById('deleteModal').classList.add('hidden');
        }

        // Close modals when clicking outside
        document.getElementById('editModal').addEventListener('click', function(e) {
            if (e.target === this) closeEditModal();
        });

        document.getElementById('deleteModal').addEventListener('click', function(e) {
            if (e.target === this) closeDeleteModal();
        });
    </script>
    <?php
});