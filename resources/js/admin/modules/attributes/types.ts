export type AttributeType = 'text' | 'number' | 'boolean' | 'select';
export type OptionDisplayType = 'text' | 'color';

export const ATTRIBUTE_TYPES: AttributeType[] = ['select', 'text', 'number', 'boolean'];

export interface AttributeValue {
    id: number;
    value: string;
    slug: string;
}

/** A product specification (Material, Screen size, ...). */
export interface Attribute {
    id: number;
    name: string;
    slug: string;
    type: AttributeType;
    unit: string | null;
    is_filterable: boolean;
    position: number;
    /** Present in lists; only "select" attributes have values. */
    values?: AttributeValue[];
    /** Categories using it directly (not counting inheritance). */
    categories_count?: number;
}

export interface OptionValue {
    id: number;
    value: string;
    slug: string;
    color_hex: string | null;
}

/** A variant dimension (Size, Color). */
export interface Option {
    id: number;
    name: string;
    slug: string;
    display_type: OptionDisplayType;
    position: number;
    values: OptionValue[];
}

/** A value as sent by the form: an existing value keeps its id. */
export interface ValueInput {
    id: number | null;
    value: string;
    slug: string;
    color_hex?: string | null;
}

export interface SaveAttributePayload {
    name: string;
    slug: string;
    type: AttributeType;
    unit: string | null;
    is_filterable: boolean;
    values: ValueInput[];
}

export interface SaveOptionPayload {
    name: string;
    slug: string;
    display_type: OptionDisplayType;
    values: ValueInput[];
}

/** A category's own attributes and those inherited from its ancestors. */
export interface CategoryAttributes {
    own: Attribute[];
    inherited: { attribute: Attribute; from: { id: number; name: string } }[];
}
