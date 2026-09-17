<?php
/**
 * core/helpers.php
 * ------------------------------------------------
 * Small standalone functions used across the admin panel.
 */

/** Turns "Men's Shoes!" into "mens-shoes". */
function slugify($text)
{
    $text = strtolower(trim($text));
    $text = preg_replace('/[^a-z0-9]+/', '-', $text);
    $text = trim($text, '-');
    return $text !== '' ? $text : 'n-a';
}

/**
 * Makes sure a slug is unique in a table by appending -2, -3, etc.
 * if needed. Pass $excludeId when editing a row (so it doesn't
 * collide with its own current slug).
 *
 * $table is never user input here — it's always a hardcoded string
 * like 'categories' or 'products' passed by the calling admin page,
 * never a value coming from $_GET/$_POST — so building the SQL
 * string with it is safe. The slug value itself IS user input and
 * goes through a bound parameter (?) as usual.
 */
function ensureUniqueSlug(Database $db, $table, $slug, $excludeId = null)
{
    $original = $slug;
    $counter = 2;

    while (true) {
        $sql = "SELECT id FROM {$table} WHERE slug = ?";
        $params = [$slug];
        $types = 's';

        if ($excludeId !== null) {
            $sql .= ' AND id != ?';
            $params[] = $excludeId;
            $types .= 'i';
        }

        $existing = $db->fetchOne($sql, $params, $types);

        if (!$existing) {
            return $slug;
        }

        $slug = $original . '-' . $counter;
        $counter++;
    }
}