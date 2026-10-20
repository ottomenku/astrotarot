<?php

namespace App\Support;

final class AdminIcons
{
    public static function user(): string
    {
        return '<svg class="admin-icon" viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path fill="currentColor" d="M12 12a4.2 4.2 0 1 0-4.2-4.2A4.2 4.2 0 0 0 12 12zm0 2.2c-3.6 0-8 1.8-8 5.3V21h16v-1.5c0-3.5-4.4-5.3-8-5.3z"/></svg>';
    }

    public static function pencil(): string
    {
        return '<svg class="admin-icon" viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path fill="currentColor" d="M3 17.25V21h3.75L17.81 9.94l-3.75-3.75L3 17.25zM20.71 7.04a1 1 0 0 0 0-1.41l-2.34-2.34a1 1 0 0 0-1.41 0l-1.83 1.83 3.75 3.75 1.83-1.83z"/></svg>';
    }

    public static function plus(): string
    {
        return '<svg class="admin-icon" viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path fill="currentColor" d="M19 13h-6v6h-2v-6H5v-2h6V5h2v6h6v2z"/></svg>';
    }

    public static function ban(): string
    {
        return '<svg class="admin-icon" viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path fill="currentColor" d="M12 2a10 10 0 1 0 10 10A10 10 0 0 0 12 2zm8 10a8 8 0 0 1-1.76 5.08L6.92 5.76A8 8 0 0 1 20 12zM4 12a8 8 0 0 1 1.76-5.08L17.08 18.24A8 8 0 0 1 4 12z"/></svg>';
    }

    public static function trash(): string
    {
        return '<svg class="admin-icon" viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path fill="currentColor" d="M6 7h12l-1 14H7L6 7zm3-3h6l1 2H8l1-2zM4 6h16v2H4V6z"/></svg>';
    }

    public static function eye(): string
    {
        return '<svg class="admin-icon" viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path fill="currentColor" d="M12 5C7 5 2.73 8.11 1 12.5 2.73 16.89 7 20 12 20s9.27-3.11 11-7.5C21.27 8.11 17 5 12 5zm0 12.5A5 5 0 1 1 17 12.5 5 5 0 0 1 12 17.5zm0-8A3 3 0 1 0 15 12.5 3 3 0 0 0 12 9.5z"/></svg>';
    }
}
