# Janči cukroví – hnízda (objednávkový formulář)

PHP formulář s výběrem produktů a velikostí z **MySQL**, odeslání přes **`send_order.php`** (e-mail).

## Zapnutí / vypnutí stránky

V **`config.php`** nastav:

```php
$siteEnabled = true;   // formulář v provozu
$siteEnabled = false;  // zobrazí se maintenance.php (HTTP 503)
```

Není potřeba přejmenovávat soubory. **`send_order.php`** při vypnutém provozu vrátí JSON chybu (nelze obejít přímým POST).

Lze doplnit: údržbový režim jen v `.htaccess` / na úrovni hostingu, nebo cronem měnit `config.php` – pro běžné použití stačí jedna proměnná.

## Soubory

| Soubor | Účel |
|--------|------|
| `index.php` | Vstup: kontrola `$siteEnabled` a `$pdo`, pak `order-form.php` |
| `order-form.php` | Šablona formuláře (nepoužívat přímo zvenku bez `index.php`) |
| `config.php` | Tajné – zkopíruj z `config.example.php` |
| `maintenance.php` | Text při vypnutém provozu |
| `unavailable.php` | Text při výpadku DB |

## GitHub

- `config.php` a fotky `foto*.jpg` jsou v `.gitignore`.
- Po prvním nasazení na server vytvoř `config.php` a nahraj obrázky.

Volitelně: [`.github/workflows/deploy-ftp.yml`](.github/workflows/deploy-ftp.yml) – secrets `FTP_*`, na serveru musí zůstat vlastní `config.php` a fotky.

## Kódování

UTF-8 (bez BOM). Viz `.editorconfig`.
