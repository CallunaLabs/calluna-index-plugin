<?php
/**
 * Plugin Name:       Calluna Index
 * Plugin URI:        https://github.com/callunaLabs/calluna-index-plugin
 * Description:       Feedback-Button für eingeloggte WP-User. Sendet Änderungswünsche/Ideen/Fehler (inkl. Screenshot + Seiten-Kontext) zentral an die Calluna-Index-Konsole (monitor.calluna.ai) — keine lokale Speicherung.
 * Version:           1.0.4
 * Requires at least: 6.0
 * Requires PHP:      7.4
 * Author:            Calluna Labs
 * Author URI:        https://calluna.ai
 * License:           GPL-2.0+
 * Text Domain:       calluna-index
 * Update URI:        https://github.com/callunaLabs/calluna-index-plugin
 */

if (!defined('ABSPATH')) {
    exit;
}

define('CALLUNA_INDEX_VERSION', '1.0.4');
define('CALLUNA_INDEX_KINDS', ['wunsch' => '💬 Änderung / Wunsch', 'idee' => '💡 Idee', 'fehler' => '🐞 Fehler']);
define('CALLUNA_INDEX_TOKEN_OPTION', 'calluna_index_token');

require_once __DIR__ . '/lib/plugin-update-checker/plugin-update-checker.php';
$calluna_index_update_checker = \YahnisElsts\PluginUpdateChecker\v5\PucFactory::buildUpdateChecker(
    'https://github.com/callunaLabs/calluna-index-plugin/',
    __FILE__,
    'calluna-index'
);
$calluna_index_update_checker->getVcsApi()->enableReleaseAssets();
$calluna_index_update_checker->setBranch('main');

/* ==========================================================================
   Konfiguration: Monitor-URL + per-Site-Token
   ========================================================================== */
function calluna_index_monitor_url(): string {
    $url = defined('CALLUNA_INDEX_MONITOR_URL') ? CALLUNA_INDEX_MONITOR_URL : 'https://monitor.calluna.ai';
    return untrailingslashit(apply_filters('calluna_index_monitor_url', $url));
}

/**
 * Per-Site-Token dieser Publikation — wird HIER auf der WP-Seite erzeugt (analog
 * zum Companion-Token) und in den Monitor eingefügt. Quelle:
 *   1. Konstante CALLUNA_INDEX_TOKEN in wp-config.php (optional), sonst
 *   2. wp_option (auf dieser Seite generiert).
 */
function calluna_index_token(): string {
    if (defined('CALLUNA_INDEX_TOKEN')) {
        $c = (string) CALLUNA_INDEX_TOKEN;
        if ('' !== $c) return $c;
    }
    return (string) get_option(CALLUNA_INDEX_TOKEN_OPTION, '');
}

/** Stellt sicher, dass ein Token existiert (generiert einen, falls keiner da ist). */
function calluna_index_ensure_token(): string {
    $t = (string) get_option(CALLUNA_INDEX_TOKEN_OPTION, '');
    if ('' === $t) {
        $t = wp_generate_password(48, false, false);
        update_option(CALLUNA_INDEX_TOKEN_OPTION, $t, false);
    }
    return $t;
}

/** Feedback an den Monitor senden (per-Site-Token direkt). */
function calluna_index_send(array $payload) {
    $token = calluna_index_token();
    if ('' === $token) {
        return new WP_Error('no_token', 'Kein Site-Token hinterlegt — im Monitor unter Einstellungen → Index-Tokens generieren und hier eintragen.');
    }
    $res = wp_remote_post(calluna_index_monitor_url() . '/api/index/feedback', [
        'timeout' => 15,
        'headers' => ['Content-Type' => 'application/json', 'Authorization' => 'Bearer ' . $token],
        'body'    => wp_json_encode($payload),
    ]);
    if (is_wp_error($res)) return $res;
    $code = (int) wp_remote_retrieve_response_code($res);
    if (401 === $code) {
        return new WP_Error('invalid_token', 'Token ungültig oder widerrufen — im Monitor neu generieren und hier eintragen.');
    }
    if ($code < 200 || $code >= 300) {
        $data = json_decode(wp_remote_retrieve_body($res), true);
        return new WP_Error('send_failed', 'Senden fehlgeschlagen (' . $code . '): ' . ($data['error'] ?? 'unbekannt'));
    }
    return true;
}

/** Täglicher Heartbeat → Monitor markiert die Seite als „verbunden" (nicht-blockierend). */
function calluna_index_maybe_ping(): void {
    $token = calluna_index_token();
    if ('' === $token) return;
    if (get_transient('calluna_index_pinged')) return;
    set_transient('calluna_index_pinged', 1, DAY_IN_SECONDS);
    wp_remote_post(calluna_index_monitor_url() . '/api/index/ping', [
        'timeout'  => 8,
        'blocking' => false,
        'headers'  => ['Content-Type' => 'application/json', 'Authorization' => 'Bearer ' . $token],
    ]);
}

/* ==========================================================================
   AJAX: Feedback absenden (nur eingeloggt)
   ========================================================================== */
add_action('wp_ajax_calluna_index_submit', function () {
    check_ajax_referer('calluna_index', 'nonce');
    if (!is_user_logged_in()) {
        wp_send_json_error('Nicht angemeldet.', 403);
    }
    $kind = isset($_POST['kind']) ? sanitize_key(wp_unslash($_POST['kind'])) : 'wunsch';
    if (!isset(CALLUNA_INDEX_KINDS[$kind])) $kind = 'wunsch';
    $message = isset($_POST['message']) ? sanitize_textarea_field(wp_unslash($_POST['message'])) : '';
    if ('' === trim($message)) {
        wp_send_json_error('Bitte einen Text eingeben.');
    }
    $user = wp_get_current_user();
    $payload = [
        'kind'       => $kind,
        'message'    => $message,
        'page_url'   => isset($_POST['url']) ? esc_url_raw(wp_unslash($_POST['url'])) : '',
        'page_title' => isset($_POST['ptitle']) ? sanitize_text_field(wp_unslash($_POST['ptitle'])) : '',
        'author'     => $user ? $user->display_name : '',
    ];
    if (!empty($_POST['shot'])) {
        $shot = wp_unslash($_POST['shot']);
        if (strlen($shot) <= 3000000 && preg_match('#^data:image/(png|jpeg|webp);base64,[A-Za-z0-9+/=\s]+$#', $shot)) {
            $payload['screenshot'] = $shot;
        }
    }
    $sent = calluna_index_send($payload);
    if (is_wp_error($sent)) {
        wp_send_json_error($sent->get_error_message());
    }
    wp_send_json_success();
});

/* ==========================================================================
   Einstellungsseite: per-Site-Token eintragen + Status
   ========================================================================== */
add_action('admin_menu', function () {
    add_options_page('Calluna Index', 'Calluna Index', 'manage_options', 'calluna-index', 'calluna_index_settings_page');
});
// Token neu generieren (überschreibt den alten → muss im Monitor nachgetragen werden).
add_action('admin_post_calluna_index_regenerate', function () {
    if (!current_user_can('manage_options')) wp_die('Forbidden');
    check_admin_referer('calluna_index_regenerate');
    update_option(CALLUNA_INDEX_TOKEN_OPTION, wp_generate_password(48, false, false), false);
    delete_transient('calluna_index_pinged');
    wp_safe_redirect(add_query_arg('cidx_msg', 'regenerated', admin_url('options-general.php?page=calluna-index')));
    exit;
});
// Verbindung testen: mit dem aktuellen Token gegen den Monitor pingen.
add_action('admin_post_calluna_index_test', function () {
    if (!current_user_can('manage_options')) wp_die('Forbidden');
    check_admin_referer('calluna_index_test');
    $token = calluna_index_token();
    $msg = 'notoken';
    if ('' !== $token) {
        $res = wp_remote_post(calluna_index_monitor_url() . '/api/index/ping', [
            'timeout' => 12,
            'headers' => ['Content-Type' => 'application/json', 'Authorization' => 'Bearer ' . $token],
        ]);
        if (is_wp_error($res)) $msg = 'err:' . $res->get_error_message();
        elseif (401 === (int) wp_remote_retrieve_response_code($res)) $msg = 'err:Token im Monitor (noch) nicht hinterlegt — dort bei dieser Publikation einfügen & speichern.';
        elseif (200 === (int) wp_remote_retrieve_response_code($res)) $msg = 'ok';
        else $msg = 'err:Monitor antwortete mit HTTP ' . wp_remote_retrieve_response_code($res);
    }
    wp_safe_redirect(add_query_arg('cidx_msg', rawurlencode($msg), admin_url('options-general.php?page=calluna-index')));
    exit;
});
function calluna_index_settings_page() {
    $viaConst = defined('CALLUNA_INDEX_TOKEN') && '' !== (string) CALLUNA_INDEX_TOKEN;
    // Token existiert immer (wird bei Bedarf hier erzeugt) — analog Companion.
    $token = $viaConst ? (string) CALLUNA_INDEX_TOKEN : calluna_index_ensure_token();
    echo '<div class="wrap"><h1>Calluna Index</h1>';
    echo '<p>Feedback-Button (nur eingeloggte User) → zentral in der Calluna-Index-Konsole: <code>' . esc_html(calluna_index_monitor_url()) . '</code></p>';
    if (isset($_GET['cidx_msg'])) {
        $m = sanitize_text_field(wp_unslash($_GET['cidx_msg']));
        if ('ok' === $m)                { echo '<div class="notice notice-success"><p>Verbunden ✓ — der Monitor kennt diesen Token.</p></div>'; }
        elseif ('regenerated' === $m)   { echo '<div class="notice notice-warning"><p>Neuer Token erzeugt. <strong>Jetzt im Monitor nachtragen</strong>, sonst bricht die Anbindung.</p></div>'; }
        elseif ('notoken' === $m)       { echo '<div class="notice notice-error"><p>Kein Token vorhanden.</p></div>'; }
        else                            { echo '<div class="notice notice-error"><p>' . esc_html(str_replace('err:', '', $m)) . '</p></div>'; }
    }

    echo '<p>Dieser Token erlaubt es dieser Seite, Feedback an die Calluna-Index-Konsole zu senden. '
       . '<strong>So benutzen:</strong> Token kopieren → im Monitor unter <em>Einstellungen → Index-Tokens</em> bei dieser Publikation einfügen → speichern.</p>';

    echo '<table class="form-table"><tr><th>Token</th><td>';
    echo '<code id="cidx-token" style="user-select:all;padding:6px 10px;background:#f6f7f7;border:1px solid #dcdcde;border-radius:4px;display:inline-block;font-size:13px">' . esc_html($token) . '</code> ';
    echo '<button type="button" class="button" onclick="navigator.clipboard.writeText(document.getElementById(\'cidx-token\').textContent);this.textContent=\'Kopiert ✓\'">Kopieren</button>';
    if ($viaConst) {
        echo '<p class="description">Fix aus <code>wp-config.php</code> (<code>CALLUNA_INDEX_TOKEN</code>).</p>';
    } else {
        echo '<p class="description">Behandle ihn wie ein Passwort. Wird lokal in <code>wp_options</code> gespeichert.</p>';
    }
    echo '</td></tr></table>';

    // Verbindung testen
    echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '" style="display:inline">';
    wp_nonce_field('calluna_index_test');
    echo '<input type="hidden" name="action" value="calluna_index_test">';
    submit_button('Verbindung testen', 'primary', 'submit', false);
    echo '</form> ';

    // Token regenerieren (nur wenn nicht per Konstante fixiert)
    if (!$viaConst) {
        echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '" style="display:inline" onsubmit="return confirm(\'Neuen Token erzeugen? Du musst ihn danach im Monitor nachtragen, sonst bricht die Anbindung.\')">';
        wp_nonce_field('calluna_index_regenerate');
        echo '<input type="hidden" name="action" value="calluna_index_regenerate">';
        submit_button('Token neu generieren', 'secondary', 'submit', false);
        echo '</form>';
    }
    echo '</div>';
}

/* ==========================================================================
   Frontend: Overlay + Button (nur eingeloggt)
   ========================================================================== */
add_action('wp_footer', function () {
    if (!is_user_logged_in()) return;
    calluna_index_maybe_ping();
    $cfg = [
        'ajax'  => admin_url('admin-ajax.php'),
        'nonce' => wp_create_nonce('calluna_index'),
        'kinds' => CALLUNA_INDEX_KINDS,
    ];
    ?>
    <style>
    #cidx-btn{position:fixed;right:20px;bottom:20px;z-index:99998;display:flex;align-items:center;gap:8px;padding:11px 16px;border:0;border-radius:999px;background:#0E1C17;color:#fff;font:600 13px/1 system-ui,sans-serif;box-shadow:0 10px 30px -8px rgba(0,0,0,.5);cursor:pointer;transition:transform .15s}
    #cidx-btn:hover{transform:translateY(-2px)}
    #cidx-ov{position:fixed;inset:0;z-index:99999;display:none;align-items:flex-end;justify-content:flex-end;background:rgba(8,14,11,.42);padding:20px}
    #cidx-ov.on{display:flex}
    #cidx-panel{width:100%;max-width:420px;max-height:86vh;overflow:auto;background:#fff;border-radius:10px;box-shadow:0 30px 70px -20px rgba(0,0,0,.5);font:14px/1.5 system-ui,sans-serif;color:#16231d}
    .cidx-hd{display:flex;align-items:center;gap:8px;padding:16px 18px;background:#0E1C17;color:#fff;border-radius:10px 10px 0 0}
    .cidx-hd b{font:700 16px/1 serif}
    .cidx-hd .x{margin-left:auto;background:none;border:0;color:rgba(255,255,255,.7);font-size:22px;line-height:1;cursor:pointer}
    .cidx-bd{padding:16px 18px}
    .cidx-tabs{display:grid;grid-template-columns:1fr 1fr 1fr;gap:6px;margin-bottom:12px}
    .cidx-tabs button{padding:8px 4px;border:1px solid #d7dbd8;border-radius:6px;background:#fff;color:#3c463f;font-size:12.5px;cursor:pointer}
    .cidx-tabs button.on{border-color:#BD9403;background:#faf6ea;color:#0E1C17;font-weight:600}
    #cidx-msg{width:100%;min-height:96px;padding:10px 12px;border:1px solid #d7dbd8;border-radius:6px;font:inherit;resize:vertical;box-sizing:border-box}
    #cidx-msg:focus{outline:none;border-color:#BD9403}
    .cidx-drop{margin-top:10px;border:2px dashed #d7dbd8;border-radius:6px;background:#f6f7f6;padding:12px;text-align:center;font-size:12px;color:#6a746d}
    .cidx-drop a{color:#8E345C;cursor:pointer;text-decoration:underline}
    .cidx-shot{position:relative;margin-top:10px}
    .cidx-shot img{width:100%;max-height:150px;object-fit:cover;border-radius:6px;display:block}
    .cidx-shot button{position:absolute;top:6px;right:6px;background:rgba(0,0,0,.6);color:#fff;border:0;border-radius:4px;padding:3px 8px;font-size:11px;cursor:pointer}
    .cidx-meta{margin-top:8px;font-size:11px;color:#8a938c;word-break:break-all}
    .cidx-send{margin-top:12px;width:100%;padding:12px;border:0;border-radius:6px;background:#BD9403;color:#fff;font-weight:700;font-size:14px;cursor:pointer}
    .cidx-send:disabled{opacity:.5;cursor:default}
    .cidx-ok{margin-top:12px;padding:10px 12px;border-radius:6px;background:#e7f4ec;color:#2e7d47;font-size:13px;display:none}
    </style>
    <button id="cidx-btn" type="button" aria-haspopup="dialog">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>
        <span>Feedback</span>
    </button>
    <div id="cidx-ov" role="dialog" aria-modal="true" aria-label="Feedback geben">
        <div id="cidx-panel">
            <div class="cidx-hd"><b>Änderung &amp; Wunsch</b><button class="x" type="button" aria-label="Schließen">&times;</button></div>
            <div class="cidx-bd">
                <div class="cidx-tabs" id="cidx-tabs"></div>
                <textarea id="cidx-msg" placeholder="Was soll geändert werden? Beschreibe deinen Wunsch möglichst konkret …"></textarea>
                <div id="cidx-shotwrap"></div>
                <div class="cidx-drop" id="cidx-drop">Screenshot hierher ziehen, <b>Strg/Cmd+V</b> einfügen oder <a id="cidx-pick">auswählen</a><input type="file" id="cidx-file" accept="image/png,image/jpeg,image/webp" hidden></div>
                <div class="cidx-meta" id="cidx-page"></div>
                <button class="cidx-send" id="cidx-send" type="button">Absenden</button>
                <div class="cidx-ok" id="cidx-ok">Danke! Dein Feedback ist bei uns eingegangen.</div>
            </div>
        </div>
    </div>
    <script>
    (function(){
        var CFG = <?php echo wp_json_encode($cfg); ?>;
        var kind='wunsch', shot=null;
        var $=function(id){return document.getElementById(id);};
        var tabs=$('cidx-tabs');
        Object.keys(CFG.kinds).forEach(function(k){
            var b=document.createElement('button');b.textContent=CFG.kinds[k];b.dataset.k=k;
            if(k===kind)b.className='on';
            b.onclick=function(){kind=k;[].forEach.call(tabs.children,function(c){c.className=c.dataset.k===k?'on':'';});};
            tabs.appendChild(b);
        });
        $('cidx-page').textContent='📄 '+document.title;
        var ov=$('cidx-ov');
        function open(){ov.classList.add('on');$('cidx-ok').style.display='none';}
        function close(){ov.classList.remove('on');}
        $('cidx-btn').onclick=open;
        ov.querySelector('.x').onclick=close;
        ov.addEventListener('click',function(e){if(e.target===ov)close();});
        document.addEventListener('keydown',function(e){if(e.key==='Escape')close();});
        function setShot(dataUrl){
            shot=dataUrl;var w=$('cidx-shotwrap');
            if(!dataUrl){w.innerHTML='';return;}
            w.innerHTML='<div class="cidx-shot"><img alt="Screenshot"><button type="button">× entfernen</button></div>';
            w.querySelector('img').src=dataUrl;
            w.querySelector('button').onclick=function(){setShot(null);};
        }
        function readFile(f){
            if(!f||!/^image\/(png|jpeg|webp)$/.test(f.type))return;
            if(f.size>2500000){alert('Bild zu groß (max ~2,5 MB).');return;}
            var r=new FileReader();r.onload=function(){setShot(r.result);};r.readAsDataURL(f);
        }
        $('cidx-pick').onclick=function(){$('cidx-file').click();};
        $('cidx-file').onchange=function(e){readFile(e.target.files[0]);};
        var drop=$('cidx-drop');
        drop.addEventListener('dragover',function(e){e.preventDefault();});
        drop.addEventListener('drop',function(e){e.preventDefault();readFile(e.dataTransfer.files[0]);});
        document.addEventListener('paste',function(e){
            if(!ov.classList.contains('on'))return;
            var it=(e.clipboardData||{}).items||[];
            for(var i=0;i<it.length;i++){if(it[i].type&&it[i].type.indexOf('image')===0){readFile(it[i].getAsFile());break;}}
        });
        var send=$('cidx-send');
        send.onclick=function(){
            var msg=$('cidx-msg').value.trim();
            if(!msg){$('cidx-msg').focus();return;}
            send.disabled=true;send.textContent='Sende …';
            var fd=new FormData();
            fd.append('action','calluna_index_submit');fd.append('nonce',CFG.nonce);
            fd.append('kind',kind);fd.append('message',msg);
            fd.append('url',location.href);fd.append('ptitle',document.title);
            if(shot)fd.append('shot',shot);
            fetch(CFG.ajax,{method:'POST',body:fd,credentials:'same-origin'}).then(function(r){return r.json();}).then(function(d){
                send.disabled=false;send.textContent='Absenden';
                if(!d||!d.success){alert((d&&d.data)||'Fehler beim Senden.');return;}
                $('cidx-msg').value='';setShot(null);$('cidx-ok').style.display='block';
            }).catch(function(){send.disabled=false;send.textContent='Absenden';alert('Netzwerkfehler.');});
        };
    })();
    </script>
    <?php
});
