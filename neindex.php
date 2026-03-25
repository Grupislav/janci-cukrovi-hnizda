<?php
// --- NASTAVENÍ PŘIPOJENÍ K DATABÁZI ---
$host = 'md394.wedos.net';
$db   = 'd199169_cukrovi';
$user = 'w199169_cukrovi';
$pass = '3JdS9TXD';
$charset = 'utf8mb4';

$dsn = "mysql:host=$host;dbname=$db;charset=$charset";
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

try {
    $pdo = new PDO($dsn, $user, $pass, $options);
} catch (\PDOException $e) {
    throw new \PDOException($e->getMessage(), (int)$e->getCode());
}

// --- Načtení dat pro formulář ---
// 1. Všechny produkty
$products = $pdo->query('SELECT id, name FROM products ORDER BY name')->fetchAll();

// 2. Všechny velikosti a jejich přiřazení k produktům
$product_sizes_query = $pdo->query('
    SELECT 
        ps.product_id, 
        s.id as size_id, 
        s.name as size_name,
        ps.price
    FROM product_sizes ps
    JOIN sizes s ON ps.size_id = s.id
');

$product_sizes_map = [];
foreach ($product_sizes_query as $row) {
    $product_sizes_map[$row['product_id']][] = [
        'id' => $row['size_id'],
        'name' => $row['size_name'],
        'price' => $row['price']
    ];
}
?>

<!DOCTYPE html>
<html lang="cs">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Objednávkový formulář</title>
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/simplelightbox/2.14.2/simple-lightbox.min.css">
</head>
<body>
    <div class="gallery-container">
        <a href="foto1-full.jpg"><img src="foto1-thumb.jpg" alt="Produktová fotografie 1"></a>
        <a href="foto2-full.jpg"><img src="foto2-thumb.jpg" alt="Produktová fotografie 2"></a>
        <a href="foto3-full.jpg"><img src="foto3-thumb.jpg" alt="Produktová fotografie 3"></a>
        <a href="foto4-full.jpg"><img src="foto4-thumb.jpg" alt="Produktová fotografie 4"></a>
        <a href="foto5-full.jpg"><img src="foto5-thumb.jpg" alt="Produktová fotografie 5"></a>
    </div>
    <div class="container">
        <h1>Objednávkový formulář</h1>
        <form id="order-form">
            <div class="form-group">
                <label for="full-name">Jméno a příjmení:</label>
                <input type="text" id="full-name" name="fullName" required>
            </div>

            <div class="form-group">
                <label for="email">E-mail:</label>
                <input type="email" id="email" name="email" required>
            </div>

            <fieldset>
                <legend>Přidat produkt do objednávky</legend>
                <div class="product-adder">
                    <div class="form-group">
                        <label for="product-select">Produkt:</label>
                        <select id="product-select">
                            <option value="">-- Vyberte produkt --</option>
                            <?php foreach ($products as $product): ?>
                                <option value="<?= htmlspecialchars($product['id']) ?>"><?= htmlspecialchars($product['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="size-select">Velikost:</label>
                        <select id="size-select" disabled>
                            <option value="">-- Nejprve vyberte produkt --</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="quantity-input">Množství:</label>
                        <input type="number" id="quantity-input" value="1" min="1" max="10">
                    </div>

                    <button type="button" id="add-item-btn">Přidat</button>
                </div>
            </fieldset>
            
            <div class="order-total">
                <h2>Celková cena: <span id="total-price-display">0 Kč</span></h2>
            </div>
            
            <div class="order-summary">
                <h2>Položky v objednávce</h2>
                <div id="order-items-container">
                    <p>Zatím nebyly přidány žádné položky.</p>
                </div>
            </div>
            <div class="form-group" style="margin-top: 20px;">
                <label for="order-notes">Sem mi napište, kdy zhruba chcete mít cukroví na stole, případně další poznámky:</label>
                <textarea id="order-notes" name="orderNotes" rows="4"></textarea>
            </div>
            <button type="submit" id="submit-btn">Odeslat</button>
            <div id="form-message"></div>
        </form>
    </div>

    <script>
        // Předání dat z PHP do JavaScriptu
        const productSizesMap = <?= json_encode($product_sizes_map) ?>;
    </script>
    <script src="script.js"></script>
    
    <script src="https://cdnjs.cloudflare.com/ajax/libs/simplelightbox/2.14.2/simple-lightbox.min.js"></script>
</body>
</html>