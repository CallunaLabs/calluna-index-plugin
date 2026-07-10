=== Calluna Index ===
Contributors: callunalabs
Tags: feedback, calluna, monitor
Requires at least: 6.0
Tested up to: 6.5
Stable tag: 1.0.4
License: GPLv2 or later

Feedback-Button für eingeloggte WP-User. Sendet Änderungswünsche/Ideen/Fehler
(inkl. Screenshot + Seiten-Kontext) zentral an die Calluna-Index-Konsole
(monitor.calluna.ai). Keine lokale Speicherung im WordPress.

== Beschreibung ==

- Floating "Feedback"-Button, nur sichtbar für eingeloggte User.
- Kategorien: Wunsch / Idee / Fehler, Freitext, Screenshot (Drag&Drop / Cmd+V / Datei).
- Auth wie beim Companion: Der Token wird HIER auf der Seite erzeugt (Einstellungen →
  Calluna Index → kopieren) und im Monitor bei dieser Publikation eingefügt. Der
  Monitor speichert nur den Hash; pro Seite widerruf-/rotierbar.
- Anzeige/Verwaltung des Feedbacks im Monitor: pro Site (Feedback-Tab) + globaler
  Index-Posteingang.

== Voraussetzungen ==
- Token im Plugin erzeugen (passiert automatisch) → im Monitor unter
  Einstellungen → Index-Tokens bei dieser Publikation einfügen & speichern.
- Optional fix per `define('CALLUNA_INDEX_TOKEN', '…')` in wp-config.php.
- Die Seite muss im Monitor als Site registriert/adoptiert sein.

== Changelog ==

= 1.0.4 =
* Companion-Stil: Token wird auf der WP-Seite erzeugt und angezeigt (Kopieren +
  Neu generieren + Verbindung testen), im Monitor eingefügt. Kein Bootstrap /
  kein geteilter Register-Token mehr. Täglicher Ping fürs "connected"-Signal.

= 1.0.3 =
* Zwischenschritt: per-Site-Token direkt statt Bootstrap.

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
