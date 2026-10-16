<?php

declare(strict_types=1);

return [

    // بررسی کد تخفیف در فروشگاه (POST /api/v1/coupons/check).
    'errors' => [
        'coupon_not_found' => 'این کد تخفیف وجود ندارد.',
        'coupon_inactive' => 'این کد تخفیف فعال نیست.',
        'coupon_not_started' => 'هنوز نمی‌توانید از این کد تخفیف استفاده کنید.',
        'coupon_expired' => 'مهلت استفاده از این کد تخفیف تمام شده است.',
        'coupon_limit_reached' => 'ظرفیت استفاده از این کد تخفیف تمام شده است.',
        'coupon_not_for_customer' => 'این کد تخفیف برای حساب شما نیست.',
        'coupon_min_order' => 'این کد تخفیف برای سفارشی با دست‌کم :amount ریال کالای مشمول است.',
        'coupon_no_eligible_items' => 'هیچ‌کدام از کالاهای سبد شما شامل این کد تخفیف نمی‌شود.',
        'item_unavailable' => 'این کالا در دسترس نیست.',
    ],

];
