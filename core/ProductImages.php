<?php
/**
 * core/ProductImages.php
 * ===================================================================
 * Product images WITHOUT a separate table.
 *
 * Every image of a product lives in ONE column:
 *
 *      products.images  =  [{"file":"8-1.png","sort":1}, ...]
 *
 * "sort":1 is the MAIN picture. It is mirrored into products.image so
 * pages that only know about a single cover picture keep working.
 *
 * ===================================================================
 * WHERE THE FILES LIVE
 * -------------------------------------------------------------------
 * One folder per product, named after the product id:
 *
 *      public/uploads/products/
 *          1/
 *              headphones.jpg
 *          8/
 *              8-1.png   8-2.png   8-3.png
 *
 * Deleting a product is then just "delete that folder", and two
 * products' pictures can never get mixed up.
 *
 * ===================================================================
 * WHY baseUrl() IS MEASURED INSTEAD OF GUESSED
 * -------------------------------------------------------------------
 * An image link is resolved by the BROWSER against the page URL, so
 * the correct prefix depends on where the page lives:
 *
 *      /public/products.php      -> "uploads/products/1/x.jpg"
 *      /admin/products/edit.php  -> "../../public/uploads/products/1/x.jpg"
 *
 * The old code hardcoded "../../uploads/..." for admin pages, forgetting
 * the "public/" folder, so every admin image resolved to /uploads/...
 * and came back 404 - which is why products looked like they had no
 * pictures. baseUrl() counts the real number of levels instead.
 * ===================================================================
 */

class ProductImages
{
    /** How many images a product is ALLOWED to have (hard cap). */
    const MAX_IMAGES = 5;

    /** How many images a product is REQUIRED to have. */
    const MIN_IMAGES = 1;

    /** Root folder that holds every product's image folder. */
    const UPLOAD_SUBDIR = 'uploads/products';

    /** Disk path to the folder holding every product's images. */
    public static function uploadRoot()
    {
        return dirname(__DIR__) . '/public/' . self::UPLOAD_SUBDIR;
    }

    /** Disk folder that holds ONE product's images. */
    public static function dir($productId)
    {
        return self::uploadRoot() . '/' . (int) $productId;
    }

    /**
     * baseUrl() = web path from the CURRENT page down to the project
     * root, e.g. "" (storefront) or "../../" (admin/products).
     *
     * Measured, not guessed: we compare the folder this script runs in
     * against the project root and count how many levels below it we
     * are. That is what makes image paths correct on BOTH the storefront
     * and the admin panel.
     */
    public static function baseUrl()
    {
        static $prefix = null;

        if ($prefix !== null) {
            return $prefix;
        }

        // .../core/ProductImages.php  ->  project root is 1 level up
        $root    = str_replace('\\', '/', dirname(__DIR__));
        $current = str_replace('\\', '/', dirname($_SERVER['SCRIPT_FILENAME'] ?? ''));

        // Walk up from the current folder until we reach the project root.
        $depth  = 0;
        $probe  = $current;
        $safety = 0;

        while ($probe !== '' && $probe !== '/' && $probe !== $root && $safety < 10) {
            $probe = dirname($probe);
            $safety++;
            if ($probe === '.' || $probe === '/' || $probe === '') {
                break;
            }
            $depth++;
        }

        $prefix = str_repeat('../', $depth);
        return $prefix;
    }

    /**
     * decode() = read the JSON column into a clean PHP array
     *
     * Always returns a list sorted by "sort", each entry:
     *     ['file' => '8-1.png', 'sort' => 1]
     *
     * Deliberately forgiving: NULL, '' or malformed JSON all come back
     * as an empty array instead of throwing, so one bad row can never
     * take down the whole product page.
     */
    public static function decode($json)
    {
        if ($json === null || $json === '' || $json === 'null') {
            return [];
        }

        $list = is_array($json) ? $json : json_decode($json, true);

        if (!is_array($list)) {
            return [];
        }

        $clean = [];
        foreach ($list as $item) {
            // Tolerate a plain list of filenames instead of objects.
            if (is_string($item)) {
                $item = ['file' => $item];
            }
            if (!is_array($item) || empty($item['file'])) {
                continue;
            }
            $clean[] = [
                'file' => (string) $item['file'],
                'sort' => isset($item['sort']) ? (int) $item['sort'] : 0,
            ];
        }

        usort($clean, function ($a, $b) {
            return $a['sort'] <=> $b['sort'];
        });

        // Renumber 1..n so "sort" is always a clean sequence.
        foreach ($clean as $i => $item) {
            $clean[$i]['sort'] = $i + 1;
        }

        return $clean;
    }

    /** encode() = turn the array back into the JSON we store. */
    public static function encode(array $list)
    {
        if (empty($list)) {
            return null;
        }
        return json_encode(array_values($list), JSON_UNESCAPED_SLASHES);
    }

    /** forProduct() = every image of a product, main image first. */
    public static function forProduct(Database $db, $productId)
    {
        $row = $db->fetchOne('SELECT images FROM products WHERE id = ?', [(int) $productId]);
        return self::decode($row['images'] ?? null);
    }

    /** countForProduct() = how many images does this product have? */
    public static function countForProduct(Database $db, $productId)
    {
        return count(self::forProduct($db, $productId));
    }

    /**
     * remainingSlots() = how many more images can this product still take?
     *
     * This is what makes "max 5 per product" real: pages pass the
     * headroom to Upload::images(), not the total maximum, so a product
     * with 5 images cannot be pushed to 10 by uploading again.
     */
    public static function remainingSlots(Database $db, $productId)
    {
        $left = self::MAX_IMAGES - self::countForProduct($db, $productId);
        return $left > 0 ? $left : 0;
    }

    /** mainImage() = filename of the main picture, or null. */
    public static function mainImage(Database $db, $productId)
    {
        $list = self::forProduct($db, $productId);
        return $list ? $list[0]['file'] : null;
    }

    /** save() = write the list back to products.images (+ products.image). */
    public static function save(Database $db, $productId, array $list)
    {
        $list = self::decode(self::encode($list));   // re-sort + renumber
        $main = $list ? $list[0]['file'] : null;

        $db->execute(
            'UPDATE products SET images = ?, image = ? WHERE id = ?',
            [self::encode($list), $main, (int) $productId]
        );

        return $list;
    }

    /**
     * url() = the web path to one image of a product
     *
     * Product 8, file "8-1.png", on an admin page gives:
     *      ../../public/uploads/products/8/8-1.png
     */
    public static function url($productId, $filename)
    {
        if (empty($filename)) {
            return '';
        }

        return self::baseUrl() . 'public/' . self::UPLOAD_SUBDIR . '/'
            . (int) $productId . '/' . rawurlencode($filename);
    }

    /**
     * nextFilename() = the name for the next new image of a product
     *
     * Uses the PRODUCTID-NUMBER convention (8-1.png, 8-2.png, ...) and
     * never reuses a number, so a deleted image never gets its old name
     * back and you can still tell what a file belongs to.
     */
    public static function nextFilename(Database $db, $productId, $uploadedFilename)
    {
        $productId = (int) $productId;
        $ext       = strtolower(pathinfo($uploadedFilename, PATHINFO_EXTENSION));
        $ext       = preg_match('/^[a-z0-9]+$/', $ext) ? $ext : 'jpg';

        $highest = 0;
        foreach (self::forProduct($db, $productId) as $item) {
            if (preg_match('/-(\d+)\.[a-z0-9]+$/i', $item['file'], $m)) {
                $highest = max($highest, (int) $m[1]);
            }
        }

        return $productId . '-' . ($highest + 1) . '.' . $ext;
    }

    /**
     * add() = move an uploaded file into the product folder and record it
     *
     * $uploadedFilename is the random name Upload::images() created
     * (e.g. "a1b2c3d4.jpg"). We rename it to PRODUCTID-NUMBER.ext, move
     * it into uploads/products/<id>/, and append it to the JSON list.
     */
    public static function add(Database $db, $productId, $uploadedFilename, $uploadDir = null)
    {
        $productId = (int) $productId;
        $uploadDir = $uploadDir ?: self::uploadRoot();

        $newName = self::nextFilename($db, $productId, $uploadedFilename);
        $target  = self::dir($productId);

        if (!is_dir($target)) {
            mkdir($target, 0755, true);
        }

        // Move the freshly uploaded file into the product's own folder.
        $source = rtrim($uploadDir, '/') . '/' . $uploadedFilename;
        $dest   = rtrim($target, '/') . '/' . $newName;

        if (file_exists($source) && $source !== $dest) {
            @rename($source, $dest);
        } elseif (!file_exists($dest)) {
            @copy($source, $dest);   // fallback across volumes
        }

        $list   = self::forProduct($db, $productId);
        $list[] = ['file' => $newName, 'sort' => count($list) + 1];

        self::save($db, $productId, $list);

        return $newName;
    }

    /** deleteOne() = remove one image (file + JSON entry). */
    public static function deleteOne(Database $db, $productId, $filename)
    {
        $productId = (int) $productId;
        $list      = self::forProduct($db, $productId);

        if (count($list) <= self::MIN_IMAGES) {
            return [
                'ok' => false,
                'message' => 'A product must keep at least '
                    . self::MIN_IMAGES . ' image. Add another image first.',
            ];
        }

        $kept = [];
        $hit  = false;
        foreach ($list as $item) {
            if (!$hit && $item['file'] === $filename) {
                $hit = true;                       // drop this one
                continue;
            }
            $kept[] = $item;
        }

        if (!$hit) {
            return ['ok' => false, 'message' => 'That image could not be found.'];
        }

        $path = self::dir($productId) . '/' . $filename;
        if (file_exists($path)) {
            unlink($path);
        }

        // save() re-sorts, so a new first image becomes the main one and
        // products.image is updated automatically.
        self::save($db, $productId, $kept);

        return ['ok' => true, 'message' => 'Image deleted.'];
    }

    /** setMain() = promote one image to sort 1. */
    public static function setMain(Database $db, $productId, $filename)
    {
        $productId = (int) $productId;
        $list      = self::forProduct($db, $productId);

        $found = false;
        foreach ($list as $i => $item) {
            if ($item['file'] === $filename) {
                unset($list[$i]);
                array_unshift($list, ['file' => $filename, 'sort' => 1]);
                $found = true;
                break;
            }
        }

        if (!$found) {
            return ['ok' => false, 'message' => 'That image could not be found.'];
        }

        self::save($db, $productId, $list);

        return ['ok' => true, 'message' => 'Main image updated.'];
    }

    /**
     * move() = move an image one place earlier (-1) or later (+1)
     *
     * Returns ['ok' => bool, 'message' => string]
     */
    public static function move(Database $db, $productId, $filename, $direction)
    {
        $list = self::forProduct($db, $productId);

        $index = null;
        foreach ($list as $i => $item) {
            if ($item['file'] === $filename) {
                $index = $i;
                break;
            }
        }

        if ($index === null) {
            return ['ok' => false, 'message' => 'That image could not be found.'];
        }

        $target = $direction === 'up' ? $index - 1 : $index + 1;

        if ($target < 0) {
            return ['ok' => false, 'message' => 'That image is already first.'];
        }
        if ($target >= count($list)) {
            return ['ok' => false, 'message' => 'That image is already last.'];
        }

        $swapped       = $list[$target];
        $list[$target] = $list[$index];
        $list[$index]  = $swapped;

        // Renumber from scratch: save() sorts by "sort", so the swap only
        // sticks if every entry gets a fresh position.
        $ordered = [];
        foreach ($list as $i => $item) {
            $ordered[] = ['file' => $item['file'], 'sort' => $i + 1];
        }

        self::save($db, $productId, $ordered);

        return ['ok' => true, 'message' => 'Image order updated.'];
    }

    /**
     * deleteAllForProduct() = remove every file AND the JSON entry
     *
     * Called when a product itself is deleted, so pictures never linger
     * on the server as orphans.
     */
    public static function deleteAllForProduct(Database $db, $productId)
    {
        $productId = (int) $productId;
        $dir       = self::dir($productId);

        foreach (self::forProduct($db, $productId) as $item) {
            $path = $dir . '/' . $item['file'];
            if (file_exists($path)) {
                unlink($path);
            }
        }

        // Tidy any stray file left behind, then the folder itself.
        if (is_dir($dir)) {
            foreach (glob($dir . '/*') ?: [] as $file) {
                if (is_file($file)) {
                    unlink($file);
                }
            }
            @rmdir($dir);
        }

        $db->execute('UPDATE products SET images = NULL, image = NULL WHERE id = ?', [$productId]);
    }
}