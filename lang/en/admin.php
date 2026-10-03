<?php

declare(strict_types=1);

return [

    'anonymised_name' => 'Anonymised user',

    'errors' => [
        'cannot_change_own_account' => 'You cannot change your own role or status.',
        'last_super_admin' => 'The store must keep at least one active super admin.',
        'already_staff' => 'This mobile number already belongs to a staff member.',
        'name_required' => 'Enter a name for the new staff member.',
        'customer_anonymised' => 'This customer has been anonymised and can no longer be changed.',
        'category_has_children' => 'This category has sub-categories. Move or delete them first.',
        'category_invalid_parent' => 'A category cannot be placed under itself or one of its sub-categories.',
        'category_tree_changed' => 'The categories have changed in the meantime. Refresh and try again.',
        'catalog_list_changed' => 'The list has changed in the meantime. Refresh and try again.',
        'catalog_item_in_use' => 'Products use this, so it can’t be deleted. Remove it from those products first.',
        'catalog_value_in_use' => 'Products use “:value”, so it can’t be removed. Remove it from those products first.',
        'attribute_type_locked' => 'Products already have values for this specification, so its type can’t change.',
        'category_has_products' => 'This category has products. Move them to other categories first.',
        'brand_has_products' => 'This brand has products. Change their brand first, or hide the brand instead.',
        'stock_changed' => 'The stock changed while you were editing (for example, an order was placed). Reload the product and enter the stock again.',
        'brand_list_changed' => 'The brands have changed in the meantime. Refresh and try again.',
        'ai_not_configured' => 'AI suggestions are not set up yet. Ask the store administrator to add the AI API key.',
        'ai_unavailable' => 'The AI service is not responding right now. Please try again in a few minutes.',
        'ai_invalid_response' => 'The AI service returned an unusable answer. Please try again.',
    ],

    'products' => [
        'errors' => [
            'simple_one_variant' => 'A simple product has exactly one variant.',
            'sku_taken' => 'Another product already uses this SKU.',
            'sale_not_lower' => 'The sale price must be lower than the regular price.',
            'sale_end_before_start' => 'The sale must end after it starts.',
            'image_not_in_gallery' => 'Choose an image from this product’s gallery.',
            'variant_options' => 'Choose exactly one value of each option.',
            'variant_duplicate' => 'Another variant already has this combination.',
            'publish_needs_variant' => 'A published product needs at least one enabled variant.',
            'primary_not_in_categories' => 'The primary category must be one of the product’s categories.',
            'attribute_not_applicable' => 'This specification doesn’t belong to the product’s categories.',
            'specification_value' => 'Enter a valid value for this specification.',
            'too_many_images' => 'A product can have at most :max images.',
        ],
    ],

];
