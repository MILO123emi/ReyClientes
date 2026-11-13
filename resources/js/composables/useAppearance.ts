import { ref } from 'vue';

const dark = ref(
    typeof document !== 'undefined' &&
        document.documentElement.classList.contains('dark'),
);
export function useAppearance() {
    function toggle(): void {
        dark.value = !document.documentElement.classList.contains('dark');
        document.documentElement.classList.toggle('dark', dark.value);
        document.cookie = `appearance=${dark.value ? 'dark' : 'light'}; Max-Age=31536000; Path=/; SameSite=Lax${location.protocol === 'https:' ? '; Secure' : ''}`;
    }
    return { dark, toggle };
}
