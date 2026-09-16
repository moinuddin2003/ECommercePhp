<?php
/**
 * public/cart.php
 * ------------------------------------------------
 * Handles all cart actions (POST/GET action=...) AND
 * displays the current cart. Cart lives entirely in
 * $_SESSION['cart'][product_id] = quantity — no DB
 * table needed until an order is actually placed.
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../core/Database.php';
require_once __DIR__ . '/../core/Session.php';
require_once __DIR__ . '/../core/Auth.php';
require_once __DIR__ . '/../core/Cart.php';

$db = new Database($conn);
Session::start();

$action = $_POST['action'] ?? $_GET['action'] ?? null;

// ---------- Handle actions first (Post/Redirect/Get pattern) ----------

if ($action === 'add') {
    $productId = (int) ($_POST['product_id'] ?? 0);
    $quantity = (int) ($_POST['quantity'] ?? 1);

    $product = $db->fetchOne(
        'SELECT id, stock FROM products WHERE id = ? AND status = 1',
        [$productId],
        'i'
    );

    if ($product) {
        Cart::add($productId, $quantity, (int) $product['stock']);
        Session::flash('success', 'Item added to cart.');
    } else {
        Session::flash('error', 'That product is no longer available.');
    }

    header('Location: cart.php');
    exit;
}

if ($action === 'update' && !empty($_POST['quantity']) && is_array($_POST['quantity'])) {
    foreach ($_POST['quantity'] as $productId => $qty) {
        $productId = (int) $productId;
        $qty = (int) $qty;

        if ($qty <= 0) {
            Cart::remove($productId);
            continue;
        }

        $product = $db->fetchOne(
            'SELECT stock FROM products WHERE id = ?',
            [$productId],
            'i'
        );

        if ($product) {
            $_SESSION['cart'][$productId] = min($qty, (int) $product['stock']);
        }
    }

    Session::flash('success', 'Cart updated.');
    header('Location: cart.php');
    exit;
}

if ($action === 'remove') {
    $productId = (int) ($_GET['id'] ?? $_POST['id'] ?? 0);
    Cart::remove($productId);
    Session::flash('success', 'Item removed from cart.');
    header('Location: cart.php');
    exit;
}

if ($action === 'clear') {
    Cart::clear();
    header('Location: cart.php');
    exit;
}

// ---------- Display the cart ----------

$cartItems = Cart::getItems($db);
$cartTotal = Cart::getTotal($cartItems);

$pageTitle = 'Shopping Cart';
require __DIR__ . '/../includes/header.php';
?>

<div class="container mb-5 mt-4">
    <h1 class="title text-center mb-4">Shopping Cart</h1>

    <?php $successMsg = Session::flash('success'); ?>
    <?php if ($successMsg): ?>
    <div class="alert alert-success text-center"><?php echo htmlspecialchars($successMsg); ?></div>
    <?php endif; ?>

    <?php $errorMsg = Session::flash('error'); ?>
    <?php if ($errorMsg): ?>
    <div class="alert alert-danger text-center"><?php echo htmlspecialchars($errorMsg); ?></div>
    <?php endif; ?>

    <?php if (empty($cartItems)): ?>

    <div class="text-center py-5">
        <p>Your cart is empty.</p>
        <a href="products.php" class="btn btn-primary">Continue Shopping</a>
    </div>

    <?php else: ?>

    <form action="cart.php" method="post">
        <input type="hidden" name="action" value="update">
        <table class="table table-bordered align-middle">
            <thead>
                <tr>
                    <th>Product</th>
                    <th>Price</th>
                    <th>Quantity</th>
                    <th>Subtotal</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($cartItems as $item): ?>
                <tr>
                    <td class="d-flex align-items-center">
                        <img src="uploads/products/<?php echo htmlspecialchars($item['image']); ?>" alt="<?php echo htmlspecialchars($item['name']); ?>" width="60" height="60" class="mr-3" style="object-fit: cover;">
                        <a href="product-detail.php?slug=<?php echo urlencode($item['slug']); ?>"><?php echo htmlspecialchars($item['name']); ?></a>
                    </td>
                    <td>$<?php echo number_format($item['price'], 2); ?></td>
                    <td style="width: 110px;">
                        <input type="number" name="quantity[<?php echo (int) $item['id']; ?>]" value="<?php echo (int) $item['quantity']; ?>" min="0" max="<?php echo (int) $item['stock']; ?>" class="form-control">
                    </td>
                    <td>$<?php echo number_format($item['subtotal'], 2); ?></td>
                    <td>
                        <a href="cart.php?action=remove&id=<?php echo (int) $item['id']; ?>" class="btn-remove" title="Remove"><i class="icon-close"></i></a>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <div class="d-flex justify-content-between align-items-center">
            <button type="submit" class="btn btn-outline-primary-2">Update Cart</button>
            <h4>Total: $<?php echo number_format($cartTotal, 2); ?></h4>
        </div>
    </form>

    <div class="text-right mt-4">
        <a href="products.php" class="btn btn-outline-primary-2 mr-2">Continue Shopping</a>
        <a href="checkout.php" class="btn btn-primary btn-round">
            <span>Proceed to Checkout</span><i class="icon-long-arrow-right"></i>
        </a>
    </div>

    <?php endif; ?>
</div><!-- End .container -->

<?php require __DIR__ . '/../includes/footer.php'; ?>