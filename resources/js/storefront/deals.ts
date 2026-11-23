import { formatNumber } from './money';

const HOUR = 3_600_000;

export interface TimeLeft {
    /** e.g. «۱۸ ساعت مانده» */
    label: string;
    /** Share of the bar to fill (0–100): time left out of a day, so a deal ending soon shows a short bar. */
    percent: number;
}

/** How long a deal has left, as the template's countdown bars show it; null when it has no end or has ended. */
export function timeLeft(endsAt: string | null, now: number): TimeLeft | null {
    if (endsAt === null) {
        return null;
    }

    const left = new Date(endsAt).getTime() - now;

    if (!(left > 0)) {
        return null;
    }

    if (left >= 24 * HOUR) {
        return { label: `${formatNumber(Math.floor(left / (24 * HOUR)))} روز مانده`, percent: 100 };
    }

    const label =
        left < HOUR
            ? `${formatNumber(Math.max(1, Math.ceil(left / 60_000)))} دقیقه مانده`
            : `${formatNumber(Math.floor(left / HOUR))} ساعت مانده`;

    return { label, percent: Math.max(2, Math.round((left / (24 * HOUR)) * 100)) };
}
