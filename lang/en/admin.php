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
        'category_has_posts' => 'This blog category has posts (some may be in the trash). Move them to other categories first.',
        'category_in_menu' => 'A menu links to this category. Remove it from the header or footer menu first.',
        'preview_url_invalid' => 'Enter an http or https address that contains {token}, such as https://shop.example/preview?token={token}.',
        'brand_has_products' => 'This brand has products. Change their brand first, or hide the brand instead.',
        'stock_changed' => 'The stock changed while you were editing (for example, an order was placed). Reload the product and enter the stock again.',
        'brand_list_changed' => 'The brands have changed in the meantime. Refresh and try again.',
        'ai_not_configured' => 'AI suggestions are not set up yet. Ask the store administrator to add the AI API key.',
        'ai_unavailable' => 'The AI service is not responding right now. Please try again in a few minutes.',
        'ai_invalid_response' => 'The AI service returned an unusable answer. Please try again.',
        'media_in_use' => 'This file is used in :count place(s). Remove it from them first.',
        'media_type_not_allowed' => 'This type of file isn’t accepted. Upload an image (JPG, PNG, WebP, AVIF, GIF), a PDF or a video (MP4, WebM).',
        'media_too_large' => 'This file is too large. The limit for this type is :max MB.',
        'media_chunk_out_of_order' => 'Part of the upload arrived out of order. The upload continues from where it stopped.',
        'media_chunk_too_large' => 'The upload is larger than the file announced.',
        'media_upload_incomplete' => 'The upload isn’t finished yet.',
    ],

    'posts' => [
        'errors' => [
            'primary_not_in_categories' => 'The primary category must be one of the post’s categories.',
            'author_not_staff' => 'Choose a staff member as the author.',
        ],
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
            'stock_not_tracked' => 'This product doesn’t track stock.',
            'stock_changed_to' => 'The stock changed meanwhile (for example, an order was placed); it is now :quantity.',
            'product_deleted' => 'This product has been deleted. Reload the page.',
        ],
    ],

    'media' => [
        'errors' => [
            'cover_only_for_videos' => 'Only a video can have a cover picture.',
        ],
    ],

];
