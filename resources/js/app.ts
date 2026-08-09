import { createInertiaApp } from '@inertiajs/vue3';
import { initializeTheme } from '@/composables/useAppearance';
import AppLayout from '@/layouts/AppLayout.vue';
import AuthLayout from '@/layouts/AuthLayout.vue';
import LearnLayout from '@/layouts/LearnLayout.vue';
import SettingsLayout from '@/layouts/settings/Layout.vue';
import { initializeFlashToast } from '@/lib/flashToast';

const appName = import.meta.env.VITE_APP_NAME || '給与計算2級 合格クエスト';

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
                return [AppLayout, SettingsLayout];
            case name === 'Dashboard' ||
                name.startsWith('learn/') ||
                name.startsWith('review/') ||
                name.startsWith('mock/'):
                return LearnLayout;
            default:
                return AppLayout;
        }
    },
    progress: {
        color: '#4B5563',
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
