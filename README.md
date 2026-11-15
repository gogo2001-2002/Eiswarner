# Eiswarner

IP-Symcon-Modul zur Abfrage der REST-API von [Eiswarnung.de](https://www.eiswarnung.de/rest-api/). Das Modul liefert die Eisprognose für den Morgen anhand von geografischen Koordinaten.

**Autor:** [gogo2001-2002](https://github.com/gogo2001-2002)  
**API:** <https://api.eiswarnung.de/>  
**Dokumentation des Anbieters:** <https://www.eiswarnung.de/rest-api/>

## Repository-Aufbau

`library.json` liegt im Stammverzeichnis des Repositories. Die Moduldateien liegen im Unterordner `Eiswarnung/`; dessen Name entspricht der PHP-Klasse `Eiswarnung`.

## Funktionen

- Eisprognose als Wert 0 (kein Eis), 1 (Eis) oder 2 (eventuell Eis)
- Vorhersagetext, erkannter Ort, Gültigkeitsdatum und Zeitpunkt der Anfrage
- Anzeige des verbleibenden Tageskontingents und der Rücksetzzeit
- Manuelle und automatische Abfrage
- Letzte gültige Prognose bleibt bei einem API-Fehler erhalten

## Installation

1. `https://github.com/gogo2001-2002/Eiswarner` als Modul-Repository in der IP-Symcon-Modulverwaltung hinzufügen.
2. Modulinstallation aktualisieren und eine Instanz **Eiswarnung** erstellen.
3. In der Instanzkonfiguration den API-Key und die Koordinaten des gewünschten Standorts eintragen.
4. **Jetzt aktualisieren** in der Konfiguration anklicken oder die Variable **Jetzt aktualisieren** schalten.

Beim Einrichten erfolgt keine automatische Sofortabfrage. So wird kein API-Aufruf ausgelöst, bevor die Koordinaten und der Schlüssel geprüft wurden.

## Konfiguration

| Einstellung | Beschreibung |
| --- | --- |
| API-Key | 32-stelliger Schlüssel, den Eiswarnung.de bereitstellt |
| Breitengrad | Geografische Breite des Standorts, zwischen -90 und 90 |
| Längengrad | Geografische Länge des Standorts, zwischen -180 und 180 |
| Automatische Aktualisierung | Aktiviert oder deaktiviert den Zeitgeber |
| Intervall | Abfrageintervall in Stunden, von 1 bis 24; Standard: 12 Stunden |

Der kostenlose API-Zugang erlaubt laut Anbieter bis zu 50 Abfragen täglich. Für eine möglichst genaue Prognose empfiehlt Eiswarnung.de, 8–10 Stunden vor dem gewünschten Zeitpunkt abzufragen. Für eine Abfahrt um 8 Uhr empfiehlt der Anbieter daher eine Anfrage am Vorabend etwa zwischen 22 und 24 Uhr.

## Variablen

| Ident | Bedeutung |
| --- | --- |
| `ForecastId` | Prognosewert: 0 = kein Eis, 1 = Eis, 2 = eventuell Eis |
| `ForecastText` | Textbeschreibung der Prognose |
| `ForecastCity` | Von der API erkannter Ort |
| `ForecastDate` | Datum, für das die Prognose gilt |
| `RequestDate` | Zeitpunkt der letzten erfolgreichen Anfrage |
| `CallsLeft` | Verbleibende Abfragen am aktuellen Tag |
| `CallsDailyLimit` | Tageslimit des API-Zugangs |
| `CallsResetInSeconds` | Sekunden bis zur Erneuerung des Kontingents |
| `Success` | Ob die letzte Abfrage erfolgreich war |
| `Status` | Erfolgsmeldung oder Fehlerursache |
| `UpdateNow` | Boolescher Schalter für eine manuelle Abfrage |

## API-Ablauf

Das Modul sendet eine HTTPS-POST-Anfrage mit `key`, `lat` und `lng` als `application/x-www-form-urlencoded` an `https://api.eiswarnung.de/`. Die JSON-Antwort wird geprüft. Bei Erfolg werden Vorhersage und Kontingent aktualisiert. Bei einem API-Fehler werden `Success` und `Status` aktualisiert, während die zuletzt erfolgreich empfangenen Vorhersagedaten erhalten bleiben.

Die vom Anbieter dokumentierten Antwortcodes sind 200 (erfolgreich), 300 (Koordinaten fehlen), 400 (API-Key fehlt), 401 (API-Key ungültig) und 402 (Tageslimit erreicht).

## Fehlerbehebung

- **Ungültiger API-Key:** prüfen, dass der Key vollständig ist und 32 Zeichen hat.
- **Fehlercode 401:** Key im Eiswarnung.de-Konto überprüfen.
- **Fehlercode 402:** tägliches Kontingent ausgeschöpft; Rücksetzzeit in `CallsResetInSeconds` beachten.
- **API nicht erreichbar:** ausgehende HTTPS-Verbindung von IP-Symcon zu `api.eiswarnung.de` prüfen.
- **Keine automatische Aktualisierung:** `AutoUpdate` prüfen und sicherstellen, dass der Zeitgeber in der Instanz aktiv ist.

## Sicherheit und Datenschutz

Der API-Key wird in der Instanzkonfiguration gespeichert und für die Anfrage an den API-Anbieter übertragen. Das Modul schreibt weder Schlüssel noch vollständige API-Antworten in ein eigenes Protokoll. Die Koordinaten werden an Eiswarnung.de gesendet, damit dort die örtliche Prognose berechnet werden kann.

## Lizenz

In diesem Repository ist derzeit keine Lizenzdatei enthalten. Ohne Lizenz gelten die gesetzlichen Standardurheberrechte.
