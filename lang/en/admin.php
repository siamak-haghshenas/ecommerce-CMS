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
        'brand_list_changed' => 'The brands have changed in the meantime. Refresh and try again.',
        'ai_not_configured' => 'AI suggestions are not set up yet. Ask the store administrator to add the AI API key.',
        'ai_unavailable' => 'The AI service is not responding right now. Please try again in a few minutes.',
        'ai_invalid_response' => 'The AI service returned an unusable answer. Please try again.',
    ],

];
