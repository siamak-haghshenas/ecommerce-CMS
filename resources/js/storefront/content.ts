/**
 * Store information and home page content that the CMS has no admin screen or API for yet
 * (frontstore.md › Gaps). Edit them here until they move into the admin.
 */

export interface HeroSlide {
    badge: string | null;
    title: string;
    text: string | null;
    cta: string;
    to: string;
    /** Picture address; null shows the picture of the first deal on the home page. */
    image: string | null;
}

export interface PromoCard {
    title: string;
    text: string | null;
    cta: string;
    to: string;
    image: string | null;
}

export interface TrustBadge {
    icon: 'truck' | 'card' | 'return' | 'shield';
    title: string;
    text: string;
}

export const store = {
    name: 'آمپر',
    /** Shown at the end of the navigation bar; null hides it. */
    deliveryNote: 'ارسال همان روز در تهران، برای سفارش تا ساعت ۱۴' as string | null,
    /** Support phone in the footer; null hides it. */
    supportPhone: null as string | null,
    trust: [
        { icon: 'truck', title: 'ارسال همان روز', text: 'برای سفارش‌های تهران' },
        { icon: 'card', title: 'پرداخت در محل', text: 'در تهران' },
        { icon: 'return', title: '۷ روز ضمانت بازگشت', text: 'بدون پرسش' },
        { icon: 'shield', title: 'ضمانت اصالت', text: 'با فاکتور و شماره سریال' },
    ] satisfies TrustBadge[],
};

export const home = {
    /** Shown after the sale events that have a banner. */
    heroSlides: [
        {
            badge: 'پیشنهاد شگفت‌انگیز',
            title: 'تخفیف‌های امروز را از دست ندهید',
            text: 'کالاهای تخفیف‌دار با زمان محدود؛ با ضمانت اصالت و ارسال سریع',
            cta: 'دیدن پیشنهادها',
            to: '/deals',
            image: null,
        },
    ] satisfies HeroSlide[] as HeroSlide[],
    /** The two cards beside the slider (dark, then red). Empty: the first two categories with a picture. */
    promoCards: [] as PromoCard[],
    categoriesCount: 8,
    dealsCount: 5,
    newArrivalsCount: 5,
};
