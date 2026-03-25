<?php
declare(strict_types=1);

if (defined('JANCI_CUKROVI_DEBUG') && JANCI_CUKROVI_DEBUG) {
    ini_set('display_errors', '1');
    error_reporting(E_ALL);
}

require __DIR__ . '/config.php';

header('Content-Type: application/json; charset=UTF-8');

if (empty($siteEnabled)) {
    echo json_encode(['success' => false, 'message' => 'Objednávky jsou dočasně uzavřené.'], JSON_UNESCAPED_UNICODE);
    exit;
}

if (!isset($pdo) || !$pdo instanceof PDO) {
    echo json_encode(['success' => false, 'message' => 'Chyba připojení k databázi.'], JSON_UNESCAPED_UNICODE);
    exit;
}

if (!isset($ownerEmail, $mailFrom, $supportPhone)) {
    echo json_encode(['success' => false, 'message' => 'Chybí nastavení v config.php.'], JSON_UNESCAPED_UNICODE);
    exit;
}

$response = ['success' => false, 'message' => 'Došlo k neznámé chybě.'];

try {
    $input = json_decode(file_get_contents('php://input'), true);

    if (json_last_error() !== JSON_ERROR_NONE) {
        throw new Exception('Chyba při čtení odeslaných dat.');
    }

    if (!$input || empty($input['fullName']) || !filter_var($input['email'], FILTER_VALIDATE_EMAIL) || empty($input['items'])) {
        throw new Exception('Neplatná nebo nekompletní data odeslaná z formuláře.');
    }

    $fullName = htmlspecialchars($input['fullName'], ENT_QUOTES, 'UTF-8');
    $customerEmail = $input['email'];
    $itemsFromClient = $input['items'];
    $notes = isset($input['notes']) ? htmlspecialchars($input['notes'], ENT_QUOTES, 'UTF-8') : '';

    $price_stmt = $pdo->query('
        SELECT ps.product_id, ps.size_id, ps.price
        FROM product_sizes ps
    ');
    $serverPriceMap = [];
    foreach ($price_stmt as $row) {
        $serverPriceMap[$row['product_id']][$row['size_id']] = (float)$row['price'];
    }

    $validatedItems = [];
    $grandTotal = 0.0;

    foreach ($itemsFromClient as $item) {
        if (!isset($serverPriceMap[$item['productId']][$item['sizeId']])) {
            throw new Exception('Nalezen neplatný produkt: ' . htmlspecialchars($item['productName'] ?? '', ENT_QUOTES, 'UTF-8'));
        }

        $trueUnitPrice = $serverPriceMap[$item['productId']][$item['sizeId']];
        $quantity = (int)($item['quantity'] ?? 0);

        if ($quantity < 1 || $quantity > 10) {
            throw new Exception('Nalezeno neplatné množství u produktu: ' . htmlspecialchars($item['productName'] ?? '', ENT_QUOTES, 'UTF-8'));
        }

        $lineTotal = $trueUnitPrice * $quantity;
        $grandTotal += $lineTotal;

        $validatedItems[] = [
            'name' => htmlspecialchars($item['productName'], ENT_QUOTES, 'UTF-8'),
            'size' => htmlspecialchars($item['sizeName'], ENT_QUOTES, 'UTF-8'),
            'quantity' => $quantity,
            'unitPrice' => $trueUnitPrice,
            'lineTotal' => $lineTotal
        ];
    }

    $tableRowsHtml = '';
    foreach ($validatedItems as $item) {
        $tableRowsHtml .= '
                <tr>
                    <td>' . $item['name'] . '</td>
                    <td>' . $item['size'] . '</td>
                    <td>' . $item['quantity'] . ' ks</td>
                    <td>' . number_format($item['unitPrice'], 2, ',', ' ') . ' Kč</td>
                    <td>' . number_format($item['lineTotal'], 2, ',', ' ') . ' Kč</td>
                </tr>
        ';
    }

    $notesHtml = '';
    if (!empty($notes)) {
        $notesHtml = "
            <h3 style='margin-top: 25px;'>Datum/jiná poznámka:</h3>
            <p style='border:1px solid #ddd; padding:10px; background:#f9f9f9; border-radius: 4px;'>" .
            nl2br($notes) .
            '</p>
        ';
    }

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

    $emailBody = '
    <html><head>' . $emailStyles . '</head><body>
        <h2>Nová objednávka od: ' . $fullName . '</h2>
        <p><strong>E-mail zákazníka:</strong> ' . htmlspecialchars($customerEmail, ENT_QUOTES, 'UTF-8') . '</p>
        ' . $notesHtml . '
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
                ' . $tableRowsHtml . "
                <tr class='total-row'>
                    <th colspan='4'>CELKEM K ÚHRADĚ:</th>
                    <td>" . number_format($grandTotal, 2, ',', ' ') . " Kč</td>
                </tr>
            </tbody>
        </table>
    </body></html>
    ";

    $emailCustomerBody = '
    <html><head>' . $emailStyles . '</head><body>
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
                ' . $tableRowsHtml . "
                <tr class='total-row'>
                    <th colspan='4'>CELKEM K ÚHRADĚ:</th>
                    <td>" . number_format($grandTotal, 2, ',', ' ') . " Kč</td>
                </tr>
            </tbody>
        </table>
        " . $notesHtml . '
    </body></html>
    ';

    $headers = "MIME-Version: 1.0\r\n";
    $headers .= "Content-type:text/html;charset=UTF-8\r\n";
    $headers .= 'From: ' . $mailFrom . "\r\n";
    $headers .= 'Reply-To: ' . $customerEmail . "\r\n";

    $subjectToOwner = 'Kotě, máš novou objednávku (' . number_format($grandTotal, 0, '', ' ') . ' Kč) od ' . $fullName;
    $subjectToCustomer = 'Potvrzení vaší objednávky';

    $mailToOwnerSuccess = @mail($ownerEmail, $subjectToOwner, $emailBody, $headers);
    $mailToCustomerSuccess = @mail($customerEmail, $subjectToCustomer, $emailCustomerBody, $headers);

    if ($mailToOwnerSuccess && $mailToCustomerSuccess) {
        $response['success'] = true;
        $response['message'] = 'Objednávka byla úspěšně odeslána!';
    } else {
        error_log('Selhalo odeslání e-mailu pro objednávku od: ' . $customerEmail);
        throw new Exception('E-maily se nepodařilo odeslat. Zkuste to prosím později nebo mě kontaktujte na čísle ' . $supportPhone . '.');
    }
} catch (PDOException $e) {
    error_log($e->getMessage());
    $response['message'] = 'Chyba databáze. Zkuste to prosím později.';
} catch (Exception $e) {
    $response['message'] = $e->getMessage();
}

echo json_encode($response, JSON_UNESCAPED_UNICODE);
