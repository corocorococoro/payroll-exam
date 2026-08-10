import { createInertiaApp } from '@inertiajs/vue3';
import { initializeTheme } from '@/composables/useAppearance';
import AuthLayout from '@/layouts/AuthLayout.vue';
import LearnLayout from '@/layouts/LearnLayout.vue';
import SettingsLayout from '@/layouts/settings/Layout.vue';
import { initializeFlashToast } from '@/lib/flashToast';

const appName = import.meta.env.VITE_APP_NAME || 'きゅーよ！';

createInertiaApp({
    title: (title) => (title ? `${title} - ${appName}` : appName),
    layout: (name) => {
        switch (true) {
            case name === 'Welcome':
                return null;
            case name === 'Onboarding':
                return null;
            // レッスンプレイヤーはフルスクリーン（独自ヘッダー）
            case name === 'learn/Lesson':
                return null;
            case name === 'mock/Player':
                return null;
            case name.startsWith('auth/'):
                return AuthLayout;
            case name.startsWith('settings/'):
                return [LearnLayout, SettingsLayout];
            default:
                // ログイン後の新規ページがスターターUIへ逆戻りしないよう、
                // 学習アプリの共通レイアウトを標準とする。
                return LearnLayout;
        }
    },
    progress: {
        color: '#2864f0',
    },
});

// This will set light / dark mode on page load...
initializeTheme();

// This will listen for flash toast data from the server...
initializeFlashToast();

if ('serviceWorker' in navigator && import.meta.env.PROD) {
    window.addEventListener('load', () =>
        navigator.serviceWorker.register('/sw.js'),
    );
}
