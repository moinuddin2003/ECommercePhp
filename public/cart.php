<?php
/**
 * public/cart.php
 * ------------------------------------------------
 * Handles all cart actions AND displays the cart page.
 *
 *   POST action=add    product_id, quantity   -> add to cart
 *   GET  action=remove id                      -> remove a line
 *   POST action=update  product_id[], quantity[] -> update quantities
 *   POST action=clear                          -> empty the cart
 *   (no action)                                -> just show the cart
 *
 * After any action, we redirect back (POST-Redirect-GET pattern) so
 * refreshing the page never re-submits the form.
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../core/Database.php';
require_once __DIR__ . '/../core/Session.php';
require_once __DIR__ . '/../core/Auth.php';
require_once __DIR__ . '/../core/Cart.php';

Session::start();
$db = new Database($conn);

$action = $_POST['action'] ?? $_GET['action'] ?? null;

if ($action === 'add' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $productId = (int) ($_POST['product_id'] ?? 0);
    $quantity = (int) ($_POST['quantity'] ?? 1);

    $added = Cart::addItem($productId, $quantity, $db);
    Session::flash($added ? 'success' : 'error', $added ? 'Added to cart.' : 'Could not add that item (out of stock or unavailable).');

    // Send the shopper back to wherever they clicked "Add to cart" from
    $redirectTo = $_SERVER['HTTP_REFERER'] ?? 'cart.php';
    header('Location: ' . $redirectTo);
    exit;
}

if ($action === 'remove') {
    $id = (int) ($_GET['id'] ?? $_POST['id'] ?? 0);
    Cart::removeItem($id);
    Session::flash('success', 'Item removed from cart.');
    header('Location: cart.php');
    exit;
}

if ($action === 'update' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $ids = $_POST['product_id'] ?? [];
    $quantities = $_POST['quantity'] ?? [];

    foreach ($ids as $i => $productId) {
        Cart::updateItem($productId, $quantities[$i] ?? 0);
    }

    Session::flash('success', 'Cart updated.');
    header('Location: cart.php');
    exit;
}

if ($action === 'clear') {
    Cart::clearCart();
    header('Location: cart.php');
    exit;
}

// ---- Display the cart page ----
$cartItems = Cart::getItems($db);
$cartTotal = Cart::getTotal($cartItems);

$pageTitle = 'Your Cart';
require __DIR__ . '/../includes/header.php';
?>

<div class="container mb-5 mt-4">
    <h1 class="title text-center mb-4">Your Cart</h1>

    <?php $flashSuccess = Session::flash('success'); ?>
    <?php $flashError = Session::flash('error'); ?>
    <?php if ($flashSuccess): ?>
    <div class="alert alert-success"><?php echo htmlspecialchars($flashSuccess); ?></div>
    <?php endif; ?>
    <?php if ($flashError): ?>
    <div class="alert alert-danger"><?php echo htmlspecialchars($flashError); ?></div>
    <?php endif; ?>

    <?php if (empty($cartItems)): ?>

    <div class="text-center py-5">
        <p>Your cart is empty.</p>
        <a href="products.php" class="btn btn-primary">Continue Shopping</a>
    </div>

    <?php else: ?>

    <form action="cart.php" method="post">
        <input type="hidden" name="action" value="update">

        <table class="table">
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
                    <td>
                        <div class="d-flex align-items-center">
                            <img src="uploads/products/<?php echo htmlspecialchars($item['image']); ?>" alt="<?php echo htmlspecialchars($item['name']); ?>" width="60" class="mr-3">
                            <a href="product-detail.php?slug=<?php echo urlencode($item['slug']); ?>"><?php echo htmlspecialchars($item['name']); ?></a>
                        </div>
                    </td>
                    <td>$<?php echo number_format($item['price'], 2); ?></td>
                    <td>
                        <input type="hidden" name="product_id[]" value="<?php echo (int) $item['id']; ?>">
                        <input type="number" name="quantity[]" value="<?php echo (int) $item['quantity']; ?>" min="1" max="<?php echo (int) $item['stock']; ?>" class="form-control" style="width: 80px;">
                    </td>
                    <td>$<?php echo number_format($item['subtotal'], 2); ?></td>
                    <td>
                        <a href="cart.php?action=remove&id=<?php echo (int) $item['id']; ?>" class="btn btn-sm btn-outline-danger">Remove</a>
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

    <div class="text-right mt-3">
        <a href="products.php" class="btn btn-outline-primary-2 mr-2">Continue Shopping</a>
        <a href="checkout.php" class="btn btn-primary btn-round">
            <span>Proceed to Checkout</span><i class="icon-long-arrow-right"></i>
        </a>
    </div>

    <?php endif; ?>
</div><!-- End .container -->

<?php require __DIR__ . '/../includes/footer.php'; ?>