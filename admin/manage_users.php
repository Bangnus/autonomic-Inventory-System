<?php

declare(strict_types=1);
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../includes/auth.php';
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
        $name = trim((string)($_POST['name'] ?? ''));
        $email = filter_input(INPUT_POST, 'email', FILTER_VALIDATE_EMAIL) ?: '';
        $password = (string)($_POST['password'] ?? '');
        $role = (string)($_POST['role'] ?? 'user');

        if ($name && $email && $password && in_array($role, ['admin', 'user'])) {
            if (strlen($password) < 6) {
                $error = 'Password must be at least 6 characters long.';
            } else {
                try {
                    // Create new user with empty signature
                    $hash = password_hash($password, PASSWORD_DEFAULT);
                    $stmt = $pdo->prepare('INSERT INTO users (name, email, password_hash, role, signature_base64) VALUES (?, ?, ?, ?, "")');
                    $stmt->execute([$name, $email, $hash, $role]);
                    $success = 'User created successfully! Please edit user to add signature.';
                } catch (Exception $e) {
                    $error = 'Failed to create user. Email might already exist.';
                }
            }
        } else {
            $error = 'Please fill in all fields correctly.';
        }
    } elseif ($action === 'update') {
        $user_id = (int)($_POST['user_id'] ?? 0);
        $name = trim((string)($_POST['name'] ?? ''));
        $email = filter_input(INPUT_POST, 'email', FILTER_VALIDATE_EMAIL) ?: '';
        $role = (string)($_POST['role'] ?? 'user');
        $password = (string)($_POST['password'] ?? '');

        if ($user_id && $name && $email && in_array($role, ['admin', 'user'])) {
            try {
                if ($password) {
                    // Update with new password
                    if (strlen($password) < 6) {
                        $error = 'Password must be at least 6 characters long.';
                    } else {
                        $hash = password_hash($password, PASSWORD_DEFAULT);
                        $signature_data = (string)($_POST['signature_data'] ?? '');
                        $stmt = $pdo->prepare('UPDATE users SET name = ?, email = ?, role = ?, password_hash = ?, signature_base64 = ? WHERE id = ?');
                        $stmt->execute([$name, $email, $role, $hash, $signature_data, $user_id]);
                        $success = 'User updated successfully!';
                    }
                } else {
                    // Update without changing password
                    $signature_data = (string)($_POST['signature_data'] ?? '');
                    $stmt = $pdo->prepare('UPDATE users SET name = ?, email = ?, role = ?, signature_base64 = ? WHERE id = ?');
                    $stmt->execute([$name, $email, $role, $signature_data, $user_id]);
                    $success = 'User updated successfully!';
                }
            } catch (Exception $e) {
                $error = 'Failed to update user. Email might already exist.';
            }
        } else {
            $error = 'Please fill in all required fields.';
        }
    } elseif ($action === 'delete') {
        $user_id = (int)($_POST['user_id'] ?? 0);

        if ($user_id && $user_id !== $user['id']) { // Prevent self-deletion
            try {
                $stmt = $pdo->prepare('DELETE FROM users WHERE id = ?');
                $stmt->execute([$user_id]);
                $success = 'User deleted successfully!';
            } catch (Exception $e) {
                $error = 'Failed to delete user.';
            }
        } else {
            $error = 'Cannot delete your own account or invalid user ID.';
        }
    }
}

// Get all users
$stmt = $pdo->query('SELECT * FROM users ORDER BY created_at DESC');
$users = $stmt->fetchAll();

require_once __DIR__ . '/../layouts/sidebar.php';
$pageTitle = 'Manage Users';
render_with_sidebar($pageTitle, 'users', function () use ($error, $success, $users, $user) {
?>
    <div class="max-w-7xl mx-auto">
        <div class="mb-8">
            <h2 class="text-3xl font-bold text-gray-900 mb-2">Manage Users</h2>
            <p class="text-gray-600">Create, update, and delete user accounts</p>
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

        <!-- Create User Form -->
        <div class="bg-white rounded-xl shadow-lg p-6 mb-8">
            <h3 class="text-lg font-semibold text-gray-900 mb-4">Create New User</h3>
            <form method="post" action="" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
                <input type="hidden" name="csrf_token" value="<?php echo e(csrf_token()); ?>" />
                <input type="hidden" name="action" value="create" />

                <div>
                    <label for="name" class="block text-sm font-medium text-gray-700 mb-2">Full Name</label>
                    <input type="text" id="name" name="name" required
                        class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                        placeholder="Enter full name" />
                </div>

                <div>
                    <label for="email" class="block text-sm font-medium text-gray-700 mb-2">Email</label>
                    <input type="email" id="email" name="email" required
                        class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                        placeholder="Enter email" />
                </div>

                <div>
                    <label for="password" class="block text-sm font-medium text-gray-700 mb-2">Password</label>
                    <input type="password" id="password" name="password" required
                        class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                        placeholder="Enter password" />
                </div>

                <div>
                    <label for="role" class="block text-sm font-medium text-gray-700 mb-2">Role</label>
                    <select id="role" name="role" required
                        class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                        <option value="user">User</option>
                        <option value="admin">Admin</option>
                    </select>
                </div>

                <div class="md:col-span-2 lg:col-span-4">
                    <button type="submit"
                        class="bg-blue-600 hover:bg-blue-700 text-white font-medium py-2 px-4 rounded-lg transition duration-200">
                        Create User
                    </button>
                </div>
            </form>
        </div>

        <!-- Users Table -->
        <div class="bg-white rounded-xl shadow-lg overflow-hidden">
            <div class="px-6 py-4 border-b border-gray-200">
                <h3 class="text-lg font-semibold text-gray-900">All Users</h3>
                <p class="text-gray-600">Manage existing user accounts</p>
            </div>

            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Name</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Email</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Role</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Created</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <?php foreach ($users as $u): ?>
                            <tr>
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                    <?php echo e($u['name']); ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                    <?php echo e($u['email']); ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <span class="px-2 py-1 text-xs font-medium rounded-full <?php echo $u['role'] === 'admin' ? 'bg-purple-100 text-purple-800' : 'bg-blue-100 text-blue-800'; ?>">
                                        <?php echo ucfirst($u['role']); ?>
                                    </span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                    <?php echo date('M j, Y', strtotime($u['created_at'])); ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                    <button onclick="openEditModal(<?php echo htmlspecialchars(json_encode($u)); ?>)"
                                        class="text-blue-600 hover:text-blue-900 mr-3">Edit</button>
                                    <?php if ($u['id'] !== $user['id']): ?>
                                        <button onclick="confirmDelete(<?php echo $u['id']; ?>, '<?php echo e($u['name']); ?>')"
                                            class="text-red-600 hover:text-red-900">Delete</button>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Edit User Modal -->
    <div id="editModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 hidden z-50">
        <div class="flex items-center justify-center min-h-screen p-4">
            <div class="bg-white rounded-xl shadow-xl p-6 w-full max-w-md">
                <h3 class="text-lg font-semibold text-gray-900 mb-4">Edit User</h3>
                <form id="editForm" method="post" action="">
                    <input type="hidden" name="csrf_token" value="<?php echo e(csrf_token()); ?>" />
                    <input type="hidden" name="action" value="update" />
                    <input type="hidden" name="user_id" id="edit_user_id" />

                    <div class="space-y-4">
                        <div>
                            <label for="edit_name" class="block text-sm font-medium text-gray-700 mb-2">Full Name</label>
                            <input type="text" id="edit_name" name="name" required
                                class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent" />
                        </div>

                        <div>
                            <label for="edit_email" class="block text-sm font-medium text-gray-700 mb-2">Email</label>
                            <input type="email" id="edit_email" name="email" required
                                class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent" />
                        </div>

                        <div>
                            <label for="edit_password" class="block text-sm font-medium text-gray-700 mb-2">New Password (leave blank to keep current)</label>
                            <input type="password" id="edit_password" name="password"
                                class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent" />
                        </div>

                        <div>
                            <label for="edit_role" class="block text-sm font-medium text-gray-700 mb-2">Role</label>
                            <select id="edit_role" name="role" required
                                class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                                <option value="user">User</option>
                                <option value="admin">Admin</option>
                            </select>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Digital Signature</label>
                            <input type="hidden" name="signature_data" id="edit_signature_data" />
                            <div class="border-2 border-dashed border-gray-300 rounded-lg p-4">
                                <canvas id="editSignatureCanvas"
                                    class="w-full h-40 border border-gray-300 rounded cursor-crosshair bg-white"></canvas>
                                <div class="mt-2 flex gap-2">
                                    <button type="button" onclick="clearEditSignature()"
                                        class="px-3 py-1 text-sm bg-gray-200 hover:bg-gray-300 rounded transition duration-200">
                                        Clear Signature
                                    </button>
                                    <span id="editSignatureStatus" class="text-sm text-gray-500">Please sign above</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="flex justify-end space-x-3 mt-6">
                        <button type="button" onclick="closeEditModal()"
                            class="px-4 py-2 text-gray-700 bg-gray-200 hover:bg-gray-300 rounded-lg transition duration-200">
                            Cancel
                        </button>
                        <button type="submit"
                            class="px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-lg transition duration-200">
                            Update User
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
                <p class="text-gray-600 mb-6">Are you sure you want to delete user <span id="deleteUserName" class="font-semibold"></span>? This action cannot be undone.</p>

                <form id="deleteForm" method="post" action="">
                    <input type="hidden" name="csrf_token" value="<?php echo e(csrf_token()); ?>" />
                    <input type="hidden" name="action" value="delete" />
                    <input type="hidden" name="user_id" id="delete_user_id" />

                    <div class="flex justify-end space-x-3">
                        <button type="button" onclick="closeDeleteModal()"
                            class="px-4 py-2 text-gray-700 bg-gray-200 hover:bg-gray-300 rounded-lg transition duration-200">
                            Cancel
                        </button>
                        <button type="submit"
                            class="px-4 py-2 bg-red-600 hover:bg-red-700 text-white rounded-lg transition duration-200">
                            Delete User
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        // Signature pad functionality
        let editCanvas, editCtx, editIsDrawing = false,
            editHasSignature = false;

        function initEditSignature() {
            editCanvas = document.getElementById('editSignatureCanvas');
            editCtx = editCanvas.getContext('2d');

            // Set canvas size
            function resizeEditCanvas() {
                const rect = editCanvas.getBoundingClientRect();
                editCanvas.width = rect.width;
                editCanvas.height = rect.height;
            }

            resizeEditCanvas();
            window.addEventListener('resize', resizeEditCanvas);

            // Mouse events
            editCanvas.addEventListener('mousedown', startEditDrawing);
            editCanvas.addEventListener('mousemove', editDraw);
            editCanvas.addEventListener('mouseup', stopEditDrawing);
            editCanvas.addEventListener('mouseout', stopEditDrawing);

            // Touch events
            editCanvas.addEventListener('touchstart', function(e) {
                e.preventDefault();
                const touch = e.touches[0];
                const mouseEvent = new MouseEvent('mousedown', {
                    clientX: touch.clientX,
                    clientY: touch.clientY
                });
                editCanvas.dispatchEvent(mouseEvent);
            });

            editCanvas.addEventListener('touchmove', function(e) {
                e.preventDefault();
                const touch = e.touches[0];
                const mouseEvent = new MouseEvent('mousemove', {
                    clientX: touch.clientX,
                    clientY: touch.clientY
                });
                editCanvas.dispatchEvent(mouseEvent);
            });

            editCanvas.addEventListener('touchend', function(e) {
                e.preventDefault();
                const mouseEvent = new MouseEvent('mouseup', {});
                editCanvas.dispatchEvent(mouseEvent);
            });
        }

        function startEditDrawing(e) {
            editIsDrawing = true;
            const rect = editCanvas.getBoundingClientRect();
            const x = e.clientX - rect.left;
            const y = e.clientY - rect.top;
            editCtx.beginPath();
            editCtx.moveTo(x, y);
        }

        function editDraw(e) {
            if (!editIsDrawing) return;

            const rect = editCanvas.getBoundingClientRect();
            const x = e.clientX - rect.left;
            const y = e.clientY - rect.top;

            editCtx.lineWidth = 2;
            editCtx.lineCap = 'round';
            editCtx.strokeStyle = '#000000';
            editCtx.lineTo(x, y);
            editCtx.stroke();
            editCtx.beginPath();
            editCtx.moveTo(x, y);

            editHasSignature = true;
            updateEditSignatureStatus();
        }

        function stopEditDrawing() {
            editIsDrawing = false;
            editCtx.beginPath();
        }

        function clearEditSignature() {
            editCtx.clearRect(0, 0, editCanvas.width, editCanvas.height);
            editHasSignature = false;
            updateEditSignatureStatus();
        }

        function updateEditSignatureStatus() {
            const status = document.getElementById('editSignatureStatus');
            if (editHasSignature) {
                status.textContent = 'Signature captured ✓';
                status.className = 'text-sm text-green-600';
            } else {
                status.textContent = 'Please sign above';
                status.className = 'text-sm text-gray-500';
            }
        }

        function openEditModal(user) {
            document.getElementById('edit_user_id').value = user.id;
            document.getElementById('edit_name').value = user.name;
            document.getElementById('edit_email').value = user.email;
            document.getElementById('edit_password').value = '';
            document.getElementById('edit_role').value = user.role;
            document.getElementById('editModal').classList.remove('hidden');

            // Initialize signature pad when modal opens
            setTimeout(initEditSignature, 100);
        }

        function closeEditModal() {
            document.getElementById('editModal').classList.add('hidden');
        }

        function confirmDelete(userId, userName) {
            document.getElementById('delete_user_id').value = userId;
            document.getElementById('deleteUserName').textContent = userName;
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

        // Handle edit form submission
        document.getElementById('editForm').addEventListener('submit', function(e) {
            if (editHasSignature) {
                const signatureData = editCanvas.toDataURL('image/png');
                document.getElementById('edit_signature_data').value = signatureData;
            }
        });
    </script>
<?php
});
