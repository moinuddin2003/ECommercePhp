<?php
/**
 * core/Cart.php
 * ------------------------------------------------
 * The cart itself lives in $_SESSION['cart'] as:
 *   $_SESSION['cart'][product_id] = quantity
 *
 * No prices are stored in the session — every price comes
 * fresh from the `products` table each time, so the cart can
 * never show a stale/wrong price even if a price changes in
 * the admin panel while someone has it in their cart.
 *
 * USAGE:
 *   Cart::addItem($productId, $qty, $db);
 *   Cart::updateItem($productId, $qty);
 *   Cart::removeItem($productId);
 *   Cart::clearCart();
 *   $items = Cart::getItems($db);      // full product rows + quantity + subtotal
 *   $total = Cart::getTotal($items);
 *   $count = Cart::getCount();
 */

class Cart
{
    /** Merge a guest cart into the signed-in customer's saved cart. */
    public static function restoreForUser(Database $db, $userId)
    {
        $cart = $_SESSION['cart'] ?? [];
        $savedItems = $db->fetchAll(
            'SELECT product_id, quantity FROM cart WHERE user_id = ?',
            [(int) $userId],
            'i'
        );

        foreach ($savedItems as $item) {
            $productId = (int) $item['product_id'];
            $cart[$productId] = (int) ($cart[$productId] ?? 0) + (int) $item['quantity'];
        }

        if (!empty($cart)) {
            $ids = array_map('intval', array_keys($cart));
            $placeholders = implode(',', array_fill(0, count($ids), '?'));
            $products = $db->fetchAll(
                "SELECT id, stock FROM products WHERE id IN ($placeholders) AND status = 1",
                $ids,
                str_repeat('i', count($ids))
            );
            $stockById = [];
            foreach ($products as $product) {
                $stockById[(int) $product['id']] = (int) $product['stock'];
            }

            foreach ($cart as $productId => $quantity) {
                $productId = (int) $productId;
                if (empty($stockById[$productId])) {
                    unset($cart[$productId]);
                    continue;
                }
                $cart[$productId] = min((int) $quantity, $stockById[$productId]);
            }
        }

        $_SESSION['cart'] = $cart;
        foreach ($cart as $productId => $quantity) {
            self::persistItem($db, $userId, $productId, $quantity);
        }
    }

    /** Returns cart rows with full product info, quantity, and subtotal. */
    public static function getItems(Database $db)
    {
        if (empty($_SESSION['cart'])) {
            return [];
        }

        $ids = array_keys($_SESSION['cart']);
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $types = str_repeat('i', count($ids));

        $products = $db->fetchAll(
            "SELECT id, name, slug, price, image, stock FROM products WHERE id IN ($placeholders) AND status = 1",
            $ids,
            $types
        );

        $items = [];
        foreach ($products as $product) {
            $qty = (int) $_SESSION['cart'][$product['id']];
            $product['quantity'] = $qty;
            $product['subtotal'] = $qty * $product['price'];
            $items[] = $product;
        }

        return $items;
    }

    /** Pass the result of getItems() in — avoids re-querying the DB just for a total. */
    public static function getTotal($items)
    {
        $total = 0;
        foreach ($items as $item) {
            $total += $item['subtotal'];
        }
        return $total;
    }

    /** Total quantity across all cart lines (for the header badge). No DB needed. */
    public static function getCount()
    {
        if (empty($_SESSION['cart'])) {
            return 0;
        }
        return array_sum($_SESSION['cart']);
    }

    /**
     * Adds a quantity to a product's cart line, capped at available stock.
     * Returns true on success, false if the product doesn't exist / is inactive / has no stock.
     */
    public static function addItem($productId, $qty, Database $db)
    {
        $productId = (int) $productId;
        $qty = max(1, (int) $qty);

        $product = $db->fetchOne(
            "SELECT id, stock FROM products WHERE id = ? AND status = 1",
            [$productId],
            'i'
        );

        if (!$product) {
            return false;
        }

        $current = $_SESSION['cart'][$productId] ?? 0;
        $newQty = min($current + $qty, (int) $product['stock']);

        if ($newQty <= 0) {
            return false;
        }

        $_SESSION['cart'][$productId] = $newQty;
        self::persistItem($db, Session::get('customer_id'), $productId, $newQty);
        return true;
    }

    /** Sets an exact quantity (used by the cart page's quantity inputs). Removes the line if 0 or less. */
    public static function updateItem($productId, $qty, ?Database $db = null)
    {
        $productId = (int) $productId;
        $qty = (int) $qty;

        if ($qty <= 0) {
            unset($_SESSION['cart'][$productId]);
        } else {
            $_SESSION['cart'][$productId] = $qty;
        }
        if ($db) {
            self::persistItem($db, Session::get('customer_id'), $productId, $qty);
        }
    }

    public static function removeItem($productId, ?Database $db = null)
    {
        $productId = (int) $productId;
        unset($_SESSION['cart'][$productId]);
        if ($db) {
            self::persistItem($db, Session::get('customer_id'), $productId, 0);
        }
    }

    public static function clearCart(?Database $db = null)
    {
        $userId = Session::get('customer_id');
        if ($db && $userId) {
            $db->execute('DELETE FROM cart WHERE user_id = ?', [(int) $userId], 'i');
        }
        unset($_SESSION['cart']);
    }

    private static function persistItem(Database $db, $userId, $productId, $quantity)
    {
        if (!$userId) {
            return;
        }

        if ((int) $quantity <= 0) {
            $db->execute(
                'DELETE FROM cart WHERE user_id = ? AND product_id = ?',
                [(int) $userId, (int) $productId],
                'ii'
            );
            return;
        }

        $db->execute(
            'INSERT INTO cart (user_id, product_id, quantity) VALUES (?, ?, ?)
             ON DUPLICATE KEY UPDATE quantity = VALUES(quantity), updated_at = CURRENT_TIMESTAMP',
            [(int) $userId, (int) $productId, (int) $quantity],
            'iii'
        );
    }
}