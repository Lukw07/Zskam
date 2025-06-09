# IT Support System - ZŠ Kamenická

Systém pro správu IT incidentů a rezervací technického vybavení pro ZŠ Kamenická.

## Funkce

- **Správa technických problémů**
  - Nahlášení technických problémů
  - Sledování stavu problémů
  - Filtrování a řazení incidentů
  - Automatické notifikace emailem

- **Rezervace vybavení**
  - Rezervace technického vybavení
  - Přehled dostupnosti
  - Omezení na pracovní dny
  - Maximální rezervace 7 dní dopředu

- **Administrace**
  - Správa uživatelů
  - Statistiky využití
  - Přehled rezervací
  - Správa technických problémů

## Požadavky

- PHP 7.4 nebo vyšší
- MySQL 5.7 nebo vyšší
- Composer
- SMTP server pro odesílání emailů

## Instalace

1. **Naklonujte repozitář**
   ```bash
   git clone [URL_REPOZITÁŘE]
   cd zskam
   ```

2. **Nainstalujte závislosti**
   ```bash
   composer install
   ```

3. **Nastavte databázi**
   - Vytvořte databázi `zskamenicka_rezv`
   - Importujte strukturu z `database.sql`

4. **Nakonfigurujte email**
   - Upravte soubor `config.php`
   - Nastavte SMTP údaje pro odesílání emailů

5. **Nastavte oprávnění**
   ```bash
   chmod 755 -R .
   chmod 777 -R uploads/
   ```

## Struktura projektu

```
├── admin.php              # Administrace uživatelů
├── admin_issues.php       # Správa technických problémů
├── auth.php              # Autentizace
├── config.php            # Konfigurace
├── dashboard.php         # Hlavní přehled
├── db.php               # Připojení k databázi
├── history.php          # Historie rezervací
├── index.php            # Přihlašovací stránka
├── navbar.php           # Navigační menu
├── reservation.php      # Rezervace vybavení
├── statistics.php       # Statistiky
└── vendor/              # Composer závislosti
```

## Použití

1. **Přihlášení**
   - Otevřete `index.php` v prohlížeči
   - Přihlaste se pomocí emailu a hesla

2. **Nahlášení problému**
   - Vyplňte formulář na hlavní stránce
   - Zadejte třídu, popis a naléhavost
   - Systém automaticky odešle notifikaci

3. **Rezervace vybavení**
   - Vyberte datum a zařízení
   - Zkontrolujte dostupnost
   - Zadejte počet kusů
   - Potvrďte rezervaci

## Bezpečnost

- Hesla jsou hashována pomocí `password_hash()`
- Používány prepared statements pro SQL
- Validace všech vstupů
- Ověření oprávnění pro každou akci

## Podpora

Pro technickou podporu kontaktujte:
- Email: kry.pepa@gmail.com
- Web: https://it.zskamenicka.cz

## Licence

Tento projekt je určen pouze pro interní použití ZŠ Kamenická. 