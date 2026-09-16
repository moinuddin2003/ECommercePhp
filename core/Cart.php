<?php
/**
 * core/Cart.php
 * ------------------------------------------------
 * The cart itself lives in $_SESSION['cart'] as:
 *   [ product_id => quantity, product_id => quantity, ... ]
 *
 * This class turns that plain array into full product rows
 * (name, price, image, subtotal) by querying the DB — so the
 * price shown is always the CURRENT price, never a stale one
 * stored days ago in the session.
 *
 * USAGE:
 *   $items = Cart::getItems($db);       // array of product rows + quantity + subtotal
 *   $total = Cart::getTotal($items);
 *   $count = Cart::getCount();          // total quantity, for the header badge
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

    /** Sum of every item's subtotal. */
    public static function getTotal(array $items)
    {
        $total = 0;
        foreach ($items as $item) {
            $total += $item['subtotal'];
        }
        return $total;
    }

    /** Total quantity across all items (for the header cart badge). */
    public static function getCount()
    {
        if (empty($_SESSION['cart'])) {
            return 0;
        }
        return array_sum($_SESSION['cart']);
    }

    /** Add a product to the cart, clamped to available stock. */
    public static function add($productId, $quantity, $maxStock)
    {
        $productId = (int) $productId;
        $quantity = max(1, (int) $quantity);

        $current = $_SESSION['cart'][$productId] ?? 0;
        $newQty = min($current + $quantity, $maxStock);

        if ($newQty <= 0) {
            unset($_SESSION['cart'][$productId]);
        } else {
            $_SESSION['cart'][$productId] = $newQty;
        }
    }

    public static function remove($productId)
    {
        unset($_SESSION['cart'][(int) $productId]);
    }

    public static function clear()
    {
        $_SESSION['cart'] = [];
    }
}