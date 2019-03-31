# IPSymconVWCarNet

[![IPS-Version](https://img.shields.io/badge/Symcon_Version-5.0+-red.svg)](https://www.symcon.de/service/dokumentation/entwicklerbereich/sdk-tools/sdk-php/)
![Module-Version](https://img.shields.io/badge/Modul_Version-1.3-blue.svg)
![Code](https://img.shields.io/badge/Code-PHP-blue.svg)
[![License](https://img.shields.io/badge/License-CC%20BY--NC--SA%204.0-green.svg)](https://creativecommons.org/licenses/by-nc-sa/4.0/)
[![StyleCI](https://github.styleci.io/repos/126683101/shield?branch=master)](https://github.styleci.io/repos/162714119)

## Dokumentation

**Inhaltsverzeichnis**

1. [Funktionsumfang](#1-funktionsumfang)
2. [Voraussetzungen](#2-voraussetzungen)
3. [Installation](#3-installation)
4. [Funktionsreferenz](#4-funktionsreferenz)
5. [Konfiguration](#5-konfiguration)
6. [Anhang](#6-anhang)
7. [Versions-Historie](#7-versions-historie)

## 1. Funktionsumfang

## 2. Voraussetzungen

 - IP-Symcon ab Version 5
 - Account bei Volkswagen Car-Net

## 3. Installation

### Laden des Moduls

Die Konsole von IP-Symcon öffnen. Im Objektbaum unter Kerninstanzen die Instanz __*Modules*__ durch einen doppelten Mausklick öffnen.

In der _Modules_ Instanz rechts oben auf den Button __*Hinzufügen*__ drücken.

In dem sich öffnenden Fenster folgende URL hinzufügen:

`https://github.com/demel42/IPSymconVWCatNet.git`

und mit _OK_ bestätigen.

Anschließend erscheint ein Eintrag für das Modul in der Liste der Instanz _Modules_

## 4. Funktionsreferenz

## 5. Konfiguration

#### Variablen

| Eigenschaft               | Typ      | Standardwert | Beschreibung |
| :-----------------------: | :-----:  | :----------: | :----------: |
| Instanz ist deaktiviert   | boolean  | false        | Instanz temporär deaktivieren |
|                           |          |              | |
| Benutzer                  | string   |              | Car-Net-Konto: Benutzerkennung |
| Passwort                  | string   |              | Car-Net-Konto: Passwort |
| FIN                       | string   |              | Fahrzeug-Identifikations-Nummer |
|                           |          |              |              |
| Aktualisiere Daten ...    | integer  | 60           | Aktualisierungsintervall, Angabe in Minuten |

#### Schaltflächen

| Bezeichnung                  | Beschreibung |
| :--------------------------: | :----------: |
| Zugang prüfen                | Prüft, ob die Angaben korrekt sind |
| Aktualisiere Daten           | führt eine sofortige Aktualisierung durch |

### Variablenprofile

* Boolean<br>

* Integer<br>

## 6. Anhang

GUIDs

- Modul: `{C1C99A87-9A56-4ED2-A4EF-F2784E3332D7}`
- Instanzen:
  - VWCatNet: `{D0B19818-B164-403D-B67C-E00C42673C79}`

## 7. Versions-Historie

- 1.3 @ 30.03.2019 17:15<br>
  - Übernahme von _chargingState_, _stateOfCharge_ und _remainingChargingTime_
  - Debug-Korrektur

- 1.2 @ 29.03.2019 16:19<br>
  - SetValue() abgesichert

- 1.1 @ 21.03.2019 20:22<br>
  - Schalter, um ein Modul (temporär) zu deaktivieren
  - Konfigurations-Element IntervalBox -> NumberSpinner
  - curl_errno() abfragen

- 1.0 @ 04.11.2018 10:49<br>
  Initiale Version
