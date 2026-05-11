<?php
declare(strict_types=1);

/**
 * Content schemas — one per JSON file the public site reads.
 *
 * A schema is a tree of fields. The renderer walks the tree to draw a form;
 * the same tree is used on save to coerce form data back into the right shape.
 *
 * Field types:
 *   - text      (single-line string)
 *   - textarea  (multi-line string; HTML allowed for press quotes)
 *   - select    (dropdown; options=[[value,label],...])
 *   - image     (image path under /img/; uploader writes to /img/uploads/)
 *   - date      (YYYY-MM-DD)
 *   - object    (nested fields=[…])
 *   - list      (repeating items; item={fields:[…]} or one_of=[…])
 *   - one_of    (heterogeneous list item; discriminated by `kind`)
 *
 * The `key` of every field is the JSON path it writes to. Optional `optional`
 * marks a field that may be null in the JSON.
 */

function schema_files(): array {
    return [
        'home' => 'home.json',
        'about' => 'about.json',
        'concerts-upcoming' => 'concerts-upcoming.json',
        'concerts-past' => 'concerts-past.json',
        'gallery' => 'gallery.json',
        'media' => 'media.json',
        'press' => 'press.json',
    ];
}

function schemas(): array {
    return [
        'home' => [
            'title' => 'Home page',
            'description' => 'Hero, press cycle, bio teaser, featured media & gallery.',
            'fields' => [
                ['key' => 'hero', 'type' => 'object', 'label' => 'Hero', 'fields' => [
                    ['key' => 'title', 'type' => 'text', 'label' => 'Title'],
                    ['key' => 'subtitle', 'type' => 'text', 'label' => 'Subtitle'],
                    ['key' => 'image', 'type' => 'image', 'label' => 'Image'],
                    ['key' => 'imageAlt', 'type' => 'text', 'label' => 'Image alt text'],
                ]],
                ['key' => 'pressCycle', 'type' => 'list', 'label' => 'Press cycle', 'item_label' => 'Quote', 'item' => press_quote_fields()],
                ['key' => 'bio', 'type' => 'list', 'label' => 'Bio paragraphs', 'item_label' => 'Paragraph', 'item' => [
                    'fields' => [
                        ['key' => '', 'type' => 'textarea', 'label' => 'Paragraph', 'rows' => 5],
                    ],
                    'scalar' => true,
                ]],
                ['key' => 'secondaryImage', 'type' => 'object', 'label' => 'Secondary image', 'fields' => image_pair_fields()],
                ['key' => 'nextConcertsQuotes', 'type' => 'list', 'label' => 'Quotes above "Next concerts"', 'item_label' => 'Quote', 'item' => press_quote_fields()],
                ['key' => 'tertiaryImage', 'type' => 'object', 'label' => 'Tertiary image', 'fields' => image_pair_fields()],
                ['key' => 'media', 'type' => 'list', 'label' => 'Featured media', 'item_label' => 'Media item', 'item' => media_item_fields_compact()],
                ['key' => 'gallery', 'type' => 'list', 'label' => 'Featured gallery photos', 'item_label' => 'Photo', 'item' => gallery_photo_fields()],
                ['key' => 'photoCredit', 'type' => 'text', 'label' => 'Photo credit'],
            ],
        ],

        'about' => [
            'title' => 'About / biography',
            'description' => 'Long-form biography with embedded press quotes.',
            'fields' => [
                ['key' => 'hero', 'type' => 'object', 'label' => 'Hero', 'fields' => [
                    ['key' => 'title', 'type' => 'text', 'label' => 'Title'],
                    ['key' => 'eyebrow', 'type' => 'text', 'label' => 'Eyebrow', 'optional' => true],
                    ['key' => 'image', 'type' => 'image', 'label' => 'Image'],
                    ['key' => 'imageAlt', 'type' => 'text', 'label' => 'Image alt text'],
                ]],
                ['key' => 'intro', 'type' => 'object', 'label' => 'Intro quote', 'fields' => press_quote_fields()],
                ['key' => 'blocks', 'type' => 'list', 'label' => 'Body blocks', 'item_label' => 'Block', 'item' => [
                    'one_of' => [
                        'para' => [
                            'label' => 'Paragraph',
                            'fields' => [
                                ['key' => 'body', 'type' => 'textarea', 'label' => 'Paragraph', 'rows' => 6],
                            ],
                        ],
                        'quote' => [
                            'label' => 'Press quote',
                            'fields' => press_quote_fields(),
                        ],
                    ],
                ]],
            ],
        ],

        'concerts-upcoming' => [
            'title' => 'Upcoming concerts',
            'description' => 'Schedule entries shown on /schedule.',
            'root_is_list' => true,
            'list' => [
                'item_label' => 'Concert',
                'item' => concert_fields(),
            ],
        ],

        'concerts-past' => [
            'title' => 'Past concerts',
            'description' => 'Archive shown on /schedule-archive.',
            'root_is_list' => true,
            'list' => [
                'item_label' => 'Concert',
                'item' => concert_fields(),
            ],
        ],

        'gallery' => [
            'title' => 'Gallery',
            'description' => 'Photos shown on /gallery.',
            'fields' => [
                ['key' => 'photos', 'type' => 'list', 'label' => 'Photos', 'item_label' => 'Photo', 'item' => gallery_photo_fields()],
                ['key' => 'credit', 'type' => 'text', 'label' => 'Photo credit'],
            ],
        ],

        'media' => [
            'title' => 'Media',
            'description' => 'Videos, interviews, recordings.',
            'fields' => [
                ['key' => 'items', 'type' => 'list', 'label' => 'Media items', 'item_label' => 'Item', 'item' => media_item_fields_full()],
            ],
        ],

        'press' => [
            'title' => 'Press',
            'description' => 'Selected reviews.',
            'fields' => [
                ['key' => 'hero', 'type' => 'object', 'label' => 'Hero', 'fields' => [
                    ['key' => 'title', 'type' => 'text', 'label' => 'Title'],
                    ['key' => 'eyebrow', 'type' => 'text', 'label' => 'Eyebrow', 'optional' => true],
                    ['key' => 'image', 'type' => 'image', 'label' => 'Image'],
                    ['key' => 'imageAlt', 'type' => 'text', 'label' => 'Image alt text'],
                ]],
                ['key' => 'quotes', 'type' => 'list', 'label' => 'Reviews', 'item_label' => 'Review', 'item' => [
                    'fields' => [
                        ['key' => 'publication', 'type' => 'text', 'label' => 'Publication'],
                        ['key' => 'byline', 'type' => 'text', 'label' => 'Byline', 'optional' => true],
                        ['key' => 'date', 'type' => 'text', 'label' => 'Date', 'placeholder' => 'November 2024'],
                        ['key' => 'url', 'type' => 'text', 'label' => 'Source URL', 'optional' => true],
                        ['key' => 'quote', 'type' => 'textarea', 'label' => 'Quote', 'rows' => 6, 'help' => 'HTML allowed — use <strong>…</strong> to emphasise.'],
                    ],
                ]],
            ],
        ],
    ];
}

// --- Reusable field groups -------------------------------------------------

function press_quote_fields(): array {
    return [
        'fields' => [
            ['key' => 'publication', 'type' => 'text', 'label' => 'Publication'],
            ['key' => 'quote', 'type' => 'textarea', 'label' => 'Quote', 'rows' => 4],
            ['key' => 'date', 'type' => 'text', 'label' => 'Date', 'placeholder' => 'November 2024'],
        ],
    ];
}

function image_pair_fields(): array {
    return [
        ['key' => 'src', 'type' => 'image', 'label' => 'Image'],
        ['key' => 'alt', 'type' => 'text', 'label' => 'Alt text'],
    ];
}

function gallery_photo_fields(): array {
    return [
        'fields' => [
            ['key' => 'src', 'type' => 'image', 'label' => 'Image'],
            ['key' => 'alt', 'type' => 'text', 'label' => 'Alt text'],
        ],
    ];
}

function concert_fields(): array {
    return [
        'fields' => [
            ['key' => 'date', 'type' => 'date', 'label' => 'Date (ISO)'],
            ['key' => 'displayDate', 'type' => 'text', 'label' => 'Display date', 'placeholder' => 'May 21, 2026'],
            ['key' => 'venue', 'type' => 'text', 'label' => 'Venue'],
            ['key' => 'programme', 'type' => 'text', 'label' => 'Programme'],
            ['key' => 'ticketsUrl', 'type' => 'text', 'label' => 'Tickets URL', 'optional' => true],
            ['key' => 'type', 'type' => 'select', 'label' => 'Type', 'optional' => true, 'options' => [
                ['', '— unspecified —'],
                ['orchestra', 'Orchestra'],
                ['recital', 'Recital'],
                ['chamber', 'Chamber'],
            ]],
            ['key' => 'orchestra', 'type' => 'text', 'label' => 'Orchestra name', 'optional' => true, 'help' => 'For orchestra type only.'],
            ['key' => 'conductor', 'type' => 'text', 'label' => 'Conductor', 'optional' => true, 'help' => 'For orchestra type only.'],
            ['key' => 'chamberPartners', 'type' => 'list', 'label' => 'Chamber partners', 'item_label' => 'Partner', 'optional' => true, 'item' => [
                'fields' => [
                    ['key' => 'name', 'type' => 'text', 'label' => 'Name'],
                    ['key' => 'instrument', 'type' => 'text', 'label' => 'Instrument'],
                ],
            ]],
            ['key' => 'notes', 'type' => 'textarea', 'label' => 'Notes', 'optional' => true, 'rows' => 2],
        ],
    ];
}

function media_item_fields_full(): array {
    return [
        'fields' => [
            ['key' => 'title', 'type' => 'text', 'label' => 'Title'],
            ['key' => 'description', 'type' => 'textarea', 'label' => 'Description', 'rows' => 2],
            ['key' => 'details', 'type' => 'textarea', 'label' => 'Details', 'optional' => true, 'rows' => 3],
            ['key' => 'url', 'type' => 'text', 'label' => 'URL', 'optional' => true],
            ['key' => 'source', 'type' => 'select', 'label' => 'Source', 'options' => [
                ['youtube', 'YouTube'],
                ['instagram', 'Instagram'],
                ['external', 'External link'],
                ['orf', 'ORF'],
            ]],
            ['key' => 'youtubeId', 'type' => 'text', 'label' => 'YouTube video ID', 'optional' => true, 'help' => 'Just the 11-char ID, not the full URL. Auto-detected when source is YouTube.'],
            ['key' => 'thumbnail', 'type' => 'image', 'label' => 'Thumbnail', 'optional' => true],
        ],
    ];
}

function media_item_fields_compact(): array {
    // Home page's media block uses a slightly different shape than media.json.
    return [
        'fields' => [
            ['key' => 'title', 'type' => 'text', 'label' => 'Title'],
            ['key' => 'description', 'type' => 'textarea', 'label' => 'Description', 'rows' => 2],
            ['key' => 'youtubeId', 'type' => 'text', 'label' => 'YouTube video ID'],
            ['key' => 'thumbnail', 'type' => 'text', 'label' => 'Thumbnail URL', 'placeholder' => 'https://i.ytimg.com/vi/…/hqdefault.jpg'],
            ['key' => 'url', 'type' => 'text', 'label' => 'URL'],
            ['key' => 'source', 'type' => 'select', 'label' => 'Source', 'options' => [
                ['youtube', 'YouTube'],
                ['instagram', 'Instagram'],
                ['external', 'External link'],
                ['orf', 'ORF'],
            ]],
        ],
    ];
}
