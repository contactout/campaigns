export const formatRate = (value: number | null | undefined): string =>
    value === null || value === undefined ? '—' : `${value.toFixed(1)}%`;
