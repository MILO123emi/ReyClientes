export function formatDni(value: string): string {
    const digits = value.replace(/\D/g, '').slice(0, 13);

    return [digits.slice(0, 4), digits.slice(4, 8), digits.slice(8, 13)]
        .filter(Boolean)
        .join('-');
}

export function formatPhone(value: string): string {
    const normalized = value.replace(/\D/g, '');
    const local =
        normalized.length === 11 && normalized.startsWith('504')
            ? normalized.slice(3)
            : normalized;
    const digits = local.slice(0, 8);

    return [digits.slice(0, 4), digits.slice(4, 8)].filter(Boolean).join('-');
}

export function formatDateTime(value: string | null): string {
    if (!value) return '—';
    const date = new Date(value);
    if (Number.isNaN(date.getTime())) return '—';

    return new Intl.DateTimeFormat('es-HN', {
        dateStyle: 'short',
        timeStyle: 'short',
        timeZone: 'America/Tegucigalpa',
    }).format(date);
}
