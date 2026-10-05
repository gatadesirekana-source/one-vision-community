<?php
/**
 * ONE VISION COMMUNITY — GESTION DES MESSAGES FLASH (NOTIFICATION)
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function set_flash(string $type, string $message): void {
    $_SESSION['flash_messages'][] = [
        'type' => $type, // success, danger, info, warning
        'message' => $message
    ];
}

function get_flash(): array {
    $messages = $_SESSION['flash_messages'] ?? [];
    unset($_SESSION['flash_messages']);
    return $messages;
}

function render_flash(): string {
    $messages = get_flash();
    if (empty($messages)) {
        return '';
    }

    $html = '<div class="flash-messages-container" id="flashMessagesContainer">';
    foreach ($messages as $msg) {
        $type = htmlspecialchars($msg['type']);
        $text = htmlspecialchars($msg['message']);
        
        $bgColor = '#eff6ff';
        $borderColor = '#bfdbfe';
        $textColor = '#1e40af';
        $icon = 'ℹ️';

        if ($type === 'success') {
            $bgColor = '#ecfdf5';
            $borderColor = '#a7f3d0';
            $textColor = '#065f46';
            $icon = '✓';
        } elseif ($type === 'danger' || $type === 'error') {
            $bgColor = '#fef2f2';
            $borderColor = '#fecaca';
            $textColor = '#991b1b';
            $icon = '⚠️';
        } elseif ($type === 'warning') {
            $bgColor = '#fffbeb';
            $borderColor = '#fde68a';
            $textColor = '#92400e';
            $icon = '⚡';
        }

        $html .= "
        <div class=\"alert alert-{$type} alert-float-toast\" style=\"background:{$bgColor}; border:1.5px solid {$borderColor}; color:{$textColor}; padding:0.85rem 1.15rem; border-radius:14px; font-size:0.92rem; font-weight:600; display:flex; align-items:center; justify-content:space-between; box-shadow:0 16px 36px rgba(15,23,42,0.14), 0 0 0 1px rgba(15,23,42,0.04); pointer-events:auto;\">
            <div style=\"display:flex; align-items:center; gap:0.75rem;\">
                <span style=\"font-weight:bold; font-size:1.1rem; display:flex; align-items:center; justify-content:center; width:26px; height:26px; border-radius:50%; background:rgba(255,255,255,0.85); flex-shrink:0;\">{$icon}</span>
                <span style=\"line-height:1.4;\">{$text}</span>
            </div>
            <button type=\"button\" onclick=\"this.parentElement.classList.add('toast-leave'); var p=this.parentElement; setTimeout(function(){ p.remove(); }, 600);\" style=\"background:none; border:none; color:inherit; font-size:1.25rem; cursor:pointer; opacity:0.6; padding:0 0 0 0.5rem; line-height:1;\" aria-label=\"Fermer\">&times;</button>
        </div>";
    }
    $html .= '</div>';
    $html .= '<script>
(function() {
    var c = document.getElementById("flashMessagesContainer");
    if (!c) return;
    var alerts = c.querySelectorAll(".alert-float-toast");
    alerts.forEach(function(el) {
        setTimeout(function() {
            el.classList.add("toast-leave");
            setTimeout(function() {
                el.remove();
                if (!c.children.length) c.remove();
            }, 600);
        }, 3000);
    });
})();
</script>';

    return $html;
}
