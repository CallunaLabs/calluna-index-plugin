=== Calluna Index ===
Contributors: callunalabs
Tags: feedback, calluna, monitor
Requires at least: 6.0
Tested up to: 6.5
Stable tag: 1.0.0
License: GPLv2 or later

Feedback-Button für eingeloggte WP-User. Sendet Änderungswünsche/Ideen/Fehler
(inkl. Screenshot + Seiten-Kontext) zentral an die Calluna-Index-Konsole
(monitor.calluna.ai). Keine lokale Speicherung im WordPress.

== Beschreibung ==

- Floating "Feedback"-Button, nur sichtbar für eingeloggte User.
- Kategorien: Wunsch / Idee / Fehler, Freitext, Screenshot (Drag&Drop / Cmd+V / Datei).
- Auth Hybrid: einmaliger Bootstrap mit dem geteilten CALLUNA_MONITOR_REGISTER_TOKEN
  (wie der Companion-Heartbeat) -> per-Site-Token; danach eindeutige, widerrufbare
  Zuordnung. Tokens bleiben serverseitig, nie im Browser.
- Anzeige/Verwaltung des Feedbacks im Monitor: pro Site (Feedback-Tab) + globaler
  Index-Posteingang.

== Voraussetzungen ==
- CALLUNA_MONITOR_REGISTER_TOKEN in wp-config.php (identisch zum Companion).
- Die Seite muss im Monitor als Site registriert/adoptiert sein.

== Changelog ==

= 1.0.0 =
* Erste Version: Feedback-Overlay + Bootstrap-Auth + Weiterleitung an monitor.calluna.ai.
