=== Calluna Index ===
Contributors: callunalabs
Tags: feedback, calluna, monitor
Requires at least: 6.0
Tested up to: 6.5
Stable tag: 1.0.2
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
- Register-Token: entweder als Konstante `CALLUNA_MONITOR_REGISTER_TOKEN` in
  wp-config.php ODER direkt in der Plugin-Einstellungsseite (Einstellungen →
  Calluna Index) einfügen. Der Token wird von Heiko / dem Monitor-Admin
  bereitgestellt.
- Die Seite muss im Monitor als Site registriert/adoptiert sein.

== Changelog ==

= 1.0.2 =
* Plugin-Icon: Calluna-Logo (Brand-Purple) in Plugin-Listen + Update-Panels von WP-Admin.
  Icons in `assets/` (icon-128, icon-256, icon.svg) werden vom Plugin-Update-Checker
  automatisch ausgelesen.

= 1.0.1 =
* Register-Token darf jetzt auch in der Plugin-Einstellungsseite eingegeben werden
  — Alternative zur `CALLUNA_MONITOR_REGISTER_TOKEN`-Konstante in wp-config.php.
  Token wird in wp_options gespeichert; Constant hat Vorrang wenn gesetzt.

= 1.0.0 =
* Erste Version: Feedback-Overlay + Bootstrap-Auth + Weiterleitung an monitor.calluna.ai.
