<?php

declare(strict_types=1);
require_once __DIR__ . '/../db.php';
require_login();

$user = $_SESSION['user'];
$pdo = get_pdo();

$error = '';
$success = '';

// Get all products for dropdown
$stmt = $pdo->query('SELECT * FROM products ORDER BY name');
$products = $stmt->fetchAll();

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    $product_id = (int)($_POST['product_id'] ?? 0);
    $quantity = (int)($_POST['quantity'] ?? 0);
    $signature_data = (string)($_POST['signature_data'] ?? '');

    if ($product_id && $quantity > 0 && $signature_data) {
        try {
            // Check if product exists and has sufficient stock
            $stmt = $pdo->prepare('SELECT * FROM products WHERE id = ?');
            $stmt->execute([$product_id]);
            $product = $stmt->fetch();

            if (!$product) {
                $error = 'Product not found.';
            } elseif ($product['stock_quantity'] < $quantity) {
                $error = 'Insufficient stock. Available: ' . $product['stock_quantity'];
            } else {
                // Start transaction
                $pdo->beginTransaction();

                try {
                    // Create transaction record
                    $stmt = $pdo->prepare('INSERT INTO transactions (user_id, product_id, type, quantity, signature_base64) VALUES (?, ?, ?, ?, ?)');
                    $stmt->execute([$user['id'], $product_id, 'request', $quantity, $signature_data]);
                    $transaction_id = $pdo->lastInsertId();

                    // Insert into requests table
                    $stmt = $pdo->prepare('INSERT INTO requests (user_id, product_id, quantity) VALUES (?, ?, ?)');
                    $stmt->execute([$user['id'], $product_id, $quantity]);

                    // Update stock quantity
                    $stmt = $pdo->prepare('UPDATE products SET stock_quantity = stock_quantity - ? WHERE id = ?');
                    $stmt->execute([$quantity, $product_id]);

                    // Commit transaction
                    $pdo->commit();

                    // Generate PDF filename
                    $pdf_filename = 'request_' . $transaction_id . '_' . date('Y-m-d_H-i-s') . '.pdf';

                    // Update transaction with PDF filename
                    $stmt = $pdo->prepare('UPDATE transactions SET pdf_filename = ? WHERE id = ?');
                    $stmt->execute([$pdf_filename, $transaction_id]);

                    $success = 'Request submitted successfully! Stock has been updated.';
                    // ไม่ต้อง redirect หรือแสดงหน้าโหลด PDF อัตโนมัติ
                } catch (Exception $e) {
                    $pdo->rollBack();
                    throw $e;
                }
            }
        } catch (Exception $e) {
            $error = 'Failed to process request. Please try again.';
        }
    } else {
        $error = 'Please fill in all fields and provide a signature.';
    }
}
require_once __DIR__ . '/../layouts/sidebar.php';
$pageTitle = 'Request Items';
render_with_sidebar($pageTitle, 'request', function () use ($error, $success, $products) {
?>
    <div class="max-w-4xl mx-auto">
        <div class="mb-8">
            <h2 class="text-3xl font-bold text-gray-900 mb-2">Request Items</h2>
            <p class="text-gray-600">Request products from inventory with digital signature</p>
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

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
            <!-- Request Form -->
            <div class="bg-white rounded-xl shadow-lg p-6">
                <h3 class="text-lg font-semibold text-gray-900 mb-4">Request Form</h3>
                <form id="requestForm" method="post" action="" class="space-y-6">
                    <input type="hidden" name="csrf_token" value="<?php echo e(csrf_token()); ?>" />
                    <input type="hidden" name="signature_data" id="signature_data" />

                    <div class="mb-4">
                        <label for="searchProduct" class="block text-sm font-medium text-gray-700 mb-2">Search Product</label>
                        <input type="text" id="searchProduct" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent" placeholder="Type to search..." onkeyup="filterProducts()">
                    </div>
                    <div>
                        <label for="product_id" class="block text-sm font-medium text-gray-700 mb-2">Select Product *</label>
                        <select id="product_id" name="product_id" required
                            class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                            <option value="">Choose a product...</option>
                            <?php foreach ($products as $product): ?>
                                <option value="<?php echo $product['id']; ?>"
                                    data-code="<?php echo e($product['code']); ?>"
                                    data-serial="<?php echo e($product['serial']); ?>"
                                    data-model="<?php echo e($product['model']); ?>"
                                    data-stock="<?php echo $product['stock_quantity']; ?>">
                                    <?php echo e($product['name'] . ' (' . $product['code'] . ') - Stock: ' . $product['stock_quantity']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div>
                        <label for="quantity" class="block text-sm font-medium text-gray-700 mb-2">Quantity *</label>
                        <input type="number" id="quantity" name="quantity" required min="1"
                            class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                            placeholder="Enter quantity" />
                    </div>

                    <!-- Product Details Display -->
                    <div id="productDetails" class="hidden bg-gray-50 rounded-lg p-4">
                        <h4 class="font-medium text-gray-900 mb-2">Product Details</h4>
                        <div class="grid grid-cols-2 gap-4 text-sm">
                            <div>
                                <span class="text-gray-600">Code:</span>
                                <span id="productCode" class="font-medium text-gray-900"></span>
                            </div>
                            <div>
                                <span class="text-gray-600">Serial:</span>
                                <span id="productSerial" class="font-medium text-gray-900"></span>
                            </div>
                            <div>
                                <span class="text-gray-600">Model:</span>
                                <span id="productModel" class="font-medium text-gray-900"></span>
                            </div>
                            <div>
                                <span class="text-gray-600">Available Stock:</span>
                                <span id="productStock" class="font-medium text-gray-900"></span>
                            </div>
                        </div>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Digital Signature *</label>
                        <div class="border-2 border-dashed border-gray-300 rounded-lg p-4">
                            <canvas id="signatureCanvas" 
                                class="w-full h-40 border border-gray-300 rounded cursor-crosshair bg-white"></canvas>
                            <div class="mt-2 flex gap-2">
                                <button type="button" onclick="clearSignature()"
                                    class="px-3 py-1 text-sm bg-gray-200 hover:bg-gray-300 rounded transition duration-200">
                                    Clear Signature
                                </button>
                                <span id="signatureStatus" class="text-sm text-gray-500">Please sign above</span>
                            </div>
                        </div>
                    </div>

                    <button type="submit" id="submitBtn" disabled
                        class="w-full bg-blue-600 hover:bg-blue-700 disabled:bg-gray-400 disabled:cursor-not-allowed text-white font-semibold py-3 px-4 rounded-lg transition duration-200">
                        Submit Request
                    </button>
                </form>
            </div>

            <!-- Instructions -->
            <div class="bg-white rounded-xl shadow-lg p-6">
                <h3 class="text-lg font-semibold text-gray-900 mb-4">Instructions</h3>
                <div class="space-y-4 text-sm text-gray-600">
                    <div class="flex items-start">
                        <div class="flex-shrink-0 w-6 h-6 bg-blue-100 text-blue-600 rounded-full flex items-center justify-center text-xs font-semibold mr-3 mt-0.5">1</div>
                        <div>
                            <p class="font-medium text-gray-900">Select Product</p>
                            <p>Choose the product you want to request from the dropdown list.</p>
                        </div>
                    </div>

                    <div class="flex items-start">
                        <div class="flex-shrink-0 w-6 h-6 bg-blue-100 text-blue-600 rounded-full flex items-center justify-center text-xs font-semibold mr-3 mt-0.5">2</div>
                        <div>
                            <p class="font-medium text-gray-900">Enter Quantity</p>
                            <p>Specify how many items you need. The system will check stock availability.</p>
                        </div>
                    </div>

                    <div class="flex items-start">
                        <div class="flex-shrink-0 w-6 h-6 bg-blue-100 text-blue-600 rounded-full flex items-center justify-center text-xs font-semibold mr-3 mt-0.5">3</div>
                        <div>
                            <p class="font-medium text-gray-900">Sign Document</p>
                            <p>Use your mouse or touch to sign in the signature area above.</p>
                        </div>
                    </div>

                    <div class="flex items-start">
                        <div class="flex-shrink-0 w-6 h-6 bg-blue-100 text-blue-600 rounded-full flex items-center justify-center text-xs font-semibold mr-3 mt-0.5">4</div>
                        <div>
                            <p class="font-medium text-gray-900">Submit Request</p>
                            <p>Click submit to process your request. Stock will be automatically deducted.</p>
                        </div>
                    </div>

                    <div class="flex items-start">
                        <div class="flex-shrink-0 w-6 h-6 bg-green-100 text-green-600 rounded-full flex items-center justify-center text-xs font-semibold mr-3 mt-0.5">✓</div>
                        <div>
                            <p class="font-medium text-gray-900">PDF Generation</p>
                            <p>A PDF with your signature will be automatically generated and downloaded.</p>
                        </div>
                    </div>
                </div>

                <div class="mt-6 p-4 bg-yellow-50 border border-yellow-200 rounded-lg">
                    <div class="flex">
                        <svg class="w-5 h-5 text-yellow-600 mr-2 mt-0.5" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"></path>
                        </svg>
                        <div>
                            <p class="text-sm font-medium text-yellow-800">Important Note</p>
                            <p class="text-sm text-yellow-700">Stock will be automatically deducted when you submit your request. Make sure you have selected the correct product and quantity.</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Search/filter for product dropdown
        function filterProducts() {
            var input = document.getElementById('searchProduct').value.toLowerCase();
            var select = document.getElementById('product_id');
            for (var i = 0; i < select.options.length; i++) {
                var txt = select.options[i].text.toLowerCase();
                select.options[i].style.display = txt.includes(input) ? '' : 'none';
            }
        }
        // Signature pad functionality
        const canvas = document.getElementById('signatureCanvas');
        const ctx = canvas.getContext('2d');
        let isDrawing = false;
        let hasSignature = false;

        // Set canvas size
        function resizeCanvas() {
            const rect = canvas.getBoundingClientRect();
            canvas.width = rect.width;
            canvas.height = rect.height;
        }

        // Initialize canvas
        resizeCanvas();
        window.addEventListener('resize', resizeCanvas);

        // Drawing functions
        function startDrawing(e) {
            isDrawing = true;
            const rect = canvas.getBoundingClientRect();
            const x = e.clientX - rect.left;
            const y = e.clientY - rect.top;
            ctx.beginPath();
            ctx.moveTo(x, y);
        }

        function draw(e) {
            if (!isDrawing) return;

            const rect = canvas.getBoundingClientRect();
            const x = e.clientX - rect.left;
            const y = e.clientY - rect.top;

            ctx.lineWidth = 2;
            ctx.lineCap = 'round';
            ctx.strokeStyle = '#000000';
            ctx.lineTo(x, y);
            ctx.stroke();
            ctx.beginPath();
            ctx.moveTo(x, y);

            hasSignature = true;
            updateSignatureStatus();
            updateSubmitButton();
        }

        function stopDrawing() {
            isDrawing = false;
            ctx.beginPath();
        }

        // Event listeners
        canvas.addEventListener('mousedown', startDrawing);
        canvas.addEventListener('mousemove', draw);
        canvas.addEventListener('mouseup', stopDrawing);
        canvas.addEventListener('mouseout', stopDrawing);

        // Touch events for mobile
        canvas.addEventListener('touchstart', function(e) {
            e.preventDefault();
            const touch = e.touches[0];
            const mouseEvent = new MouseEvent('mousedown', {
                clientX: touch.clientX,
                clientY: touch.clientY
            });
            canvas.dispatchEvent(mouseEvent);
        });

        canvas.addEventListener('touchmove', function(e) {
            e.preventDefault();
            const touch = e.touches[0];
            const mouseEvent = new MouseEvent('mousemove', {
                clientX: touch.clientX,
                clientY: touch.clientY
            });
            canvas.dispatchEvent(mouseEvent);
        });

        canvas.addEventListener('touchend', function(e) {
            e.preventDefault();
            const mouseEvent = new MouseEvent('mouseup', {});
            canvas.dispatchEvent(mouseEvent);
        });

        function clearSignature() {
            ctx.clearRect(0, 0, canvas.width, canvas.height);
            hasSignature = false;
            updateSignatureStatus();
            updateSubmitButton();
        }

        function updateSignatureStatus() {
            const status = document.getElementById('signatureStatus');
            if (hasSignature) {
                status.textContent = 'Signature captured ✓';
                status.className = 'text-sm text-green-600';
            } else {
                status.textContent = 'Please sign above';
                status.className = 'text-sm text-gray-500';
            }
        }

        function updateSubmitButton() {
            const submitBtn = document.getElementById('submitBtn');
            const productId = document.getElementById('product_id').value;
            const quantity = document.getElementById('quantity').value;

            if (productId && quantity && hasSignature) {
                submitBtn.disabled = false;
            } else {
                submitBtn.disabled = true;
            }
        }

        // Product selection handler
        document.getElementById('product_id').addEventListener('change', function() {
            const selectedOption = this.options[this.selectedIndex];
            const detailsDiv = document.getElementById('productDetails');

            if (this.value) {
                document.getElementById('productCode').textContent = selectedOption.dataset.code;
                document.getElementById('productSerial').textContent = selectedOption.dataset.serial || 'N/A';
                document.getElementById('productModel').textContent = selectedOption.dataset.model || 'N/A';
                document.getElementById('productStock').textContent = selectedOption.dataset.stock;
                detailsDiv.classList.remove('hidden');
            } else {
                detailsDiv.classList.add('hidden');
            }

            updateSubmitButton();
        });

        // Quantity input handler
        document.getElementById('quantity').addEventListener('input', function() {
            const productSelect = document.getElementById('product_id');
            const selectedOption = productSelect.options[productSelect.selectedIndex];

            if (this.value && selectedOption.dataset.stock) {
                const requested = parseInt(this.value);
                const available = parseInt(selectedOption.dataset.stock);

                if (requested > available) {
                    this.setCustomValidity(`Only ${available} items available`);
                    this.style.borderColor = '#ef4444';
                } else {
                    this.setCustomValidity('');
                    this.style.borderColor = '#d1d5db';
                }
            }

            updateSubmitButton();
        });

        // Form submission handler
        document.getElementById('requestForm').addEventListener('submit', function(e) {
            if (hasSignature) {
                const signatureData = canvas.toDataURL('image/png');
                document.getElementById('signature_data').value = signatureData;
            } else {
                e.preventDefault();
                alert('Please provide a signature before submitting.');
            }
        });

        // Initialize
        updateSignatureStatus();
        updateSubmitButton();
    </script>
<?php
});
