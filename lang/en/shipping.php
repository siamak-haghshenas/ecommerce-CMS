<?php

declare(strict_types=1);

return [
    'errors' => [
        'shipping_method_list_changed' => 'The shipping methods have changed in the meantime. Refresh and try again.',
        'first_range_start' => 'The first range must start at 0.',
        'range_gap' => 'Each range must start where the previous one ends.',
        'range_end_required' => 'Only the last range can be without an upper limit.',
        'range_end_after_start' => 'The end of a range must be above its start.',
        'days_order' => 'The longest delivery time can’t be shorter than the shortest.',
        'coupon_without_items' => 'Send a discount code together with the items; without items, the cart’s discount code is used.',
    ],
];
