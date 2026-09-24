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

    $html = '<div class="flash-messages-container" style="max-width:1100px;margin:1rem auto 1.5rem;padding:0 1.25rem;">';
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
        <div class=\"alert alert-{$type}\" style=\"background:{$bgColor}; border:1px solid {$borderColor}; color:{$textColor}; padding:0.9rem 1.25rem; border-radius:12px; margin-bottom:0.75rem; font-size:0.95rem; display:flex; align-items:center; justify-content:space-between; box-shadow:0 4px 12px rgba(0,0,0,0.03);\">
            <div style=\"display:flex; align-items:center; gap:0.75rem;\">
                <span style=\"font-weight:bold; font-size:1.1rem;\">{$icon}</span>
                <span>{$text}</span>
            </div>
            <button type=\"button\" onclick=\"this.parentElement.remove()\" style=\"background:none; border:none; color:inherit; font-size:1.2rem; cursor:pointer; opacity:0.6;\">&times;</button>
        </div>";
    }
    $html .= '</div>';

    return $html;
}
