# Rezervační systém ZŠ Kamenická

Systém pro správu rezervací technického vybavení na základní škole.

## Funkce systému

### Pro všechny uživatele
- Přihlášení do systému
- Nahlášení technického problému
- Zobrazení aktivních rezervací
- Zobrazení historie rezervací
- Vytváření nových rezervací
- Úprava vlastních rezervací
- Smazání vlastních rezervací

### Pro administrátory
- Správa uživatelů (vytváření, úprava, mazání)
- Správa technických problémů
- Správa zařízení
- Úprava všech rezervací

## Technické požadavky

- PHP 7.4 nebo novější
- MySQL 5.7 nebo novější
- Webový server (Apache/Nginx)

## Instalace

1. Naklonujte repozitář do složky webového serveru
2. Vytvořte databázi a importujte strukturu z `database.sql`
3. Upravte přístupové údaje k databázi v souboru `db.php`
4. Nastavte správná oprávnění pro složky a soubory
5. Otevřete aplikaci v prohlížeči

## Struktura databáze

### Tabulky
- `users` - uživatelé systému
- `devices` - technické vybavení
- `reservations` - rezervace
- `hours` - definice hodin
- `technical_issues` - nahlášené technické problémy

## Bezpečnost

- Hesla jsou hashována pomocí PHP funkce `password_hash()`
- Všechny SQL dotazy používají prepared statements
- Implementována ochrana proti SQL injection
- Implementována ochrana proti XSS útokům
- Implementována kontrola oprávnění pro všechny akce

## Autor

Systém byl vytvořen pro ZŠ Kamenická.

## Licence

Všechna práva vyhrazena. 
