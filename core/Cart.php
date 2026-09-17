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
        return true;
    }

    /** Sets an exact quantity (used by the cart page's quantity inputs). Removes the line if 0 or less. */
    public static function updateItem($productId, $qty)
    {
        $productId = (int) $productId;
        $qty = (int) $qty;

        if ($qty <= 0) {
            unset($_SESSION['cart'][$productId]);
        } else {
            $_SESSION['cart'][$productId] = $qty;
        }
    }

    public static function removeItem($productId)
    {
        unset($_SESSION['cart'][(int) $productId]);
    }

    public static function clearCart()
    {
        unset($_SESSION['cart']);
    }
}