<?php
// === KROK 1: Povolení zobrazení chyb pro ladění ===
ini_set('display_errors', 1);
error_reporting(E_ALL);

// === KROK 2: Vždy posílat hlavičku JSON co nejdříve ===
header('Content-Type: application/json');

// --- CÍLOVÉ E-MAILY (TVOJE NASTAVENÍ) ---
$ownerEmail = 'Litovcenkova.jana@seznam.cz,grupislav@gmail.com';

// Připravíme si pole pro odpověď
$response = ['success' => false, 'message' => 'Došlo k neznámé chybě.'];

// === KROK 3: Zabalení logiky do bloku try-catch ===
try {
    // --- NASTAVENÍ PŘIPOJENÍ K DATABÁZI (STEJNÉ JAKO V INDEX.PHP) ---
    // !!! DŮLEŽITÉ: DOPLŇTE SVÉ ÚDAJE PRO PŘIPOJENÍ K DB !!!
    $host = 'md394.wedos.net';
    $db   = 'd199169_cukrovi'; // DOPLŇTE
    $user = 'w199169_cukrovi';       // DOPLŇTE
    $pass = '3JdS9TXD';          // DOPLŇTE
    $charset = 'utf8mb4';

    $dsn = "mysql:host=$host;dbname=$db;charset=$charset";
    $options = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ];
    $pdo = new PDO($dsn, $user, $pass, $options);
    // --- KONEC PŘIPOJENÍ K DATABÁZI ---

    $input = json_decode(file_get_contents('php://input'), true);

    if (json_last_error() !== JSON_ERROR_NONE) {
        throw new Exception('Chyba při čtení odeslaných dat.');
    }

    if (!$input || empty($input['fullName']) || !filter_var($input['email'], FILTER_VALIDATE_EMAIL) || empty($input['items'])) {
        throw new Exception('Neplatná nebo nekompletní data odeslaná z formuláře.');
    }

    $fullName = htmlspecialchars($input['fullName']);
    $customerEmail = $input['email'];
    $itemsFromClient = $input['items'];
    $notes = isset($input['notes']) ? htmlspecialchars($input['notes']) : '';

    // --- BEZPEČNOSTNÍ PŘEPOČÍTÁNÍ CEN NA SERVERU ---
    
    // 1. Vytáhneme si VŠECHNY ceny z DB
    $price_stmt = $pdo->query('
        SELECT ps.product_id, ps.size_id, ps.price 
        FROM product_sizes ps
    ');
    $serverPriceMap = [];
    foreach ($price_stmt as $row) {
        $serverPriceMap[$row['product_id']][$row['size_id']] = (float)$row['price'];
    }

    $validatedItems = []; // Pole pro ověřené položky do e-mailu
    $grandTotal = 0;

    foreach ($itemsFromClient as $item) {
        // Ověření, zda položka a cena existuje na serveru
        if (!isset($serverPriceMap[$item['productId']][$item['sizeId']])) {
            throw new Exception("Nalezen neplatný produkt: " . htmlspecialchars($item['productName']));
        }
        
        $trueUnitPrice = $serverPriceMap[$item['productId']][$item['sizeId']];
        $quantity = (int)$item['quantity'];
        
        if ($quantity < 1 || $quantity > 10) {
            throw new Exception("Nalezeno neplatné množství u produktu: " . htmlspecialchars($item['productName']));
        }

        $lineTotal = $trueUnitPrice * $quantity;
        $grandTotal += $lineTotal;

        // Uložíme si ověřená data pro e-mail
        $validatedItems[] = [
            'name' => htmlspecialchars($item['productName']),
            'size' => htmlspecialchars($item['sizeName']),
            'quantity' => $quantity,
            'unitPrice' => $trueUnitPrice,
            'lineTotal' => $lineTotal
        ];
    }
    // --- KONEC PŘEPOČÍTÁNÍ CEN ---

    // --- Sestavení HTML řádků tabulky (pro oba e-maily) ---
    $tableRowsHtml = "";
    foreach ($validatedItems as $item) {
        $tableRowsHtml .= "
                <tr>
                    <td>" . $item['name'] . "</td>
                    <td>" . $item['size'] . "</td>
                    <td>" . $item['quantity'] . " ks</td>
                    <td>" . number_format($item['unitPrice'], 2, ',', ' ') . " Kč</td>
                    <td>" . number_format($item['lineTotal'], 2, ',', ' ') . " Kč</td>
                </tr>
        ";
    }
    
    $notesHtml = "";
    if (!empty($notes)) {
        $notesHtml = "
            <h3 style='margin-top: 25px;'>Datum/jiná poznámka:</h3>
            <p style='border:1px solid #ddd; padding:10px; background:#f9f9f9; border-radius: 4px;'>" 
            . nl2br($notes) . // nl2br() zachová zalomení řádků zadaná uživatelem
            "</p>
        ";
    }

    // Definice stylů (pro oba e-maily)
    $emailStyles = "
        <style>
            body { font-family: Arial, sans-serif; line-height: 1.6; }
            table { width: 100%; border-collapse: collapse; margin-top: 20px; }
            th, td { border: 1px solid #ddd; padding: 10px; text-align: left; }
            th { background-color: #f2f2f2; }
            .total-row th { text-align: right; font-size: 1.2em; }
            .total-row td { font-weight: bold; font-size: 1.2em; }
        </style>
    ";

    // --- Sestavení těla e-mailu pro NÁS (TVOJE VERZE) ---
    $emailBody = "
    <html><head>" . $emailStyles . "</head><body>
        <h2>Nová objednávka od: " . $fullName . "</h2>
        <p><strong>E-mail zákazníka:</strong> " . $customerEmail . "</p>
        " . $notesHtml . "
        <h3>Objednané položky:</h3>
        <table>
            <thead>
                <tr>
                    <th>Produkt</th>
                    <th>Velikost</th>
                    <th>Množství</th>
                    <th>Cena/ks</th>
                    <th>Cena celkem</th>
                </tr>
            </thead>
            <tbody>
                " . $tableRowsHtml . "
                <tr class='total-row'>
                    <th colspan='4'>CELKEM K ÚHRADĚ:</th>
                    <td>" . number_format($grandTotal, 2, ',', ' ') . " Kč</td>
                </tr>
            </tbody>
        </table>
        <h2><3</h2>
    </body></html>
    ";
    
    // --- Sestavení těla e-mailu pro ZÁKAZNÍKA (TVOJE VERZE) ---
    $emailCustomerBody = "
    <html><head>" . $emailStyles . "</head><body>
        <h3>Děkuji za vaši objednávku, ozvu se co nejdříve! Rekapitulace:</h3>
        <table>
            <thead>
                <tr>
                    <th>Produkt</th>
                    <th>Velikost</th>
                    <th>Množství</th>
                    <th>Cena/ks</th>
                    <th>Cena celkem</th>
                </tr>
            </thead>
            <tbody>
                " . $tableRowsHtml . "
                <tr class='total-row'>
                    <th colspan='4'>CELKEM K ÚHRADĚ:</th>
                    <td>" . number_format($grandTotal, 2, ',', ' ') . " Kč</td>
                </tr>
            </tbody>
        </table>
        " . $notesHtml . "
    </body></html>
    "; // TADY BYLA PŮVODNĚ CHYBA, NYNÍ JE OPRAVENO

    // --- Hlavičky pro odeslání HTML e-mailu (TVOJE VERZE) ---
    $headers = "MIME-Version: 1.0" . "\r\n";
    $headers .= "Content-type:text/html;charset=UTF-8" . "\r\n";
    $headers .= 'From: Janči cukroví <info@tomaskrupicka.cz>' . "\r\n";
    $headers .= 'Reply-To: ' . $customerEmail . "\r\n";

    // --- Odeslání e-mailů ---
    // Předmět vylepšen o celkovou cenu
    $subjectToOwner = "Kotě, máš novou objednávku (" . number_format($grandTotal, 0, '', ' ') . " Kč) od " . $fullName;
    $subjectToCustomer = "Potvrzení vaší objednávky";

    $mailToOwnerSuccess = @mail($ownerEmail, $subjectToOwner, $emailBody, $headers);
    $mailToCustomerSuccess = @mail($customerEmail, $subjectToCustomer, $emailCustomerBody, $headers);

    if ($mailToOwnerSuccess && $mailToCustomerSuccess) {
        $response['success'] = true;
        $response['message'] = 'Objednávka byla úspěšně odeslána!';
    } else {
        error_log("Selhalo odeslání e-mailu pro objednávku od: " . $customerEmail);
        // Tvoje chybová hláška
        throw new Exception('E-maily se nepodařilo odeslat. Zkuste to prosím později nebo mě kontaktujte na čísle 777 367 942.');
    }

} catch (PDOException $e) {
    // Chyba připojení k DB
    error_log($e->getMessage()); // Zápis do logu serveru
    $response['message'] = 'Chyba databáze. Zkuste to prosím později.';
} catch (Exception $e) {
    // Jakákoliv jiná chyba
    $response['message'] = $e->getMessage();
}

// === KROK 4: Odeslání finální odpovědi ===
echo json_encode($response);
?>