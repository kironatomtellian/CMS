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
        'concerts-upcoming' => 'concerts-upcoming.json',
        'concerts-past' => 'concerts-past.json',
        'about' => 'about.json',
        'press' => 'press.json',
        'media' => 'media.json',
        'gallery' => 'gallery.json',
        'contact' => 'contact.json',
        'presenters' => 'presenters.json',
    ];
}

/**
 * Sections in the same order as the website's menu. `page` is the path on the
 * site, used for "View on site" links; `nav` is the short menu label.
 */
function schemas(): array {
    return [
        'home' => [
            'title' => 'Home',
            'nav' => 'Home',
            'page' => '/',
            'description' => 'Hero, press, concerts intro, photos, biography teaser, recordings.',
            'fields' => [
                ['key' => 'hero', 'type' => 'object', 'label' => 'Hero', 'fields' => [
                    ['key' => 'title', 'type' => 'text', 'label' => 'Name', 'help' => 'The last word is set in italic.'],
                    ['key' => 'subtitle', 'type' => 'text', 'label' => 'Line under the name', 'placeholder' => 'Pianist / Composer'],
                    ['key' => 'image', 'type' => 'image', 'label' => 'Photo'],
                    ['key' => 'imageAlt', 'type' => 'text', 'label' => 'Photo description'],
                ]],
                ['key' => 'pressFeature', 'type' => 'object', 'label' => 'Press under the next-concert band', 'fields' => [
                    ['key' => 'lead', 'type' => 'object', 'label' => 'Big quote', 'fields' => lead_quote_fields()],
                    ['key' => 'others', 'type' => 'list', 'label' => 'Three smaller quotes', 'item_label' => 'Quote', 'item' => press_quote_fields()],
                ]],
                ['key' => 'secondaryImage', 'type' => 'object', 'label' => 'Full-width photo after the concerts', 'fields' => image_pair_fields()],
                ['key' => 'pressCycle', 'type' => 'list', 'label' => 'Rotating quotes', 'item_label' => 'Quote', 'item' => press_quote_fields()],
                ['key' => 'bio', 'type' => 'list', 'label' => 'Biography teaser', 'item_label' => 'Paragraph', 'item' => [
                    'fields' => [
                        ['key' => '', 'type' => 'textarea', 'label' => 'Paragraph', 'rows' => 5],
                    ],
                    'scalar' => true,
                ]],
                ['key' => 'tertiaryImage', 'type' => 'object', 'label' => 'Full-width photo before recordings', 'fields' => image_pair_fields()],
                ['key' => 'media', 'type' => 'list', 'label' => 'Featured recordings', 'item_label' => 'Recording', 'item' => media_item_fields_compact()],
                ['key' => 'gallery', 'type' => 'list', 'label' => 'Photo strip', 'item_label' => 'Photo', 'item' => gallery_photo_fields()],
                ['key' => 'photoCredit', 'type' => 'text', 'label' => 'Photo credit'],
            ],
        ],

        'concerts-upcoming' => [
            'title' => 'Schedule',
            'nav' => 'Schedule',
            'page' => '/schedule',
            'description' => 'Upcoming concerts. They move to Past concerts by themselves the day after.',
            'root_is_list' => true,
            'sort' => 'date-asc',
            'list' => [
                'item_label' => 'Concert',
                'item' => concert_fields(),
            ],
        ],

        'concerts-past' => [
            'title' => 'Past concerts',
            'nav' => 'Past concerts',
            'page' => '/schedule-archive',
            'description' => 'Every concert played, newest first. Counts on the site come from this list.',
            'root_is_list' => true,
            'sort' => 'date-desc',
            'list' => [
                'item_label' => 'Concert',
                'item' => concert_fields(),
            ],
        ],

        'about' => [
            'title' => 'Biography',
            'nav' => 'Biography',
            'page' => '/about',
            'description' => 'The full biography, with press quotes between paragraphs.',
            'fields' => [
                ['key' => 'hero', 'type' => 'object', 'label' => 'Top of the page', 'fields' => [
                    ['key' => 'title', 'type' => 'text', 'label' => 'Small label', 'placeholder' => 'Biography'],
                    ['key' => 'eyebrow', 'type' => 'text', 'label' => 'Big title', 'placeholder' => 'Kiron Atom Tellian', 'help' => 'The last word is set in italic.'],
                    ['key' => 'image', 'type' => 'image', 'label' => 'Photo'],
                    ['key' => 'imageAlt', 'type' => 'text', 'label' => 'Photo description'],
                ]],
                ['key' => 'intro', 'type' => 'object', 'label' => 'Quote in the side column', 'fields' => press_quote_fields()['fields']],
                ['key' => 'download', 'type' => 'object', 'label' => 'Download card', 'fields' => [
                    ['key' => 'label', 'type' => 'text', 'label' => 'Button text', 'placeholder' => 'Download biography'],
                    ['key' => 'note', 'type' => 'text', 'label' => 'Small line above', 'placeholder' => 'PDF · A4 · season 2026/27'],
                    ['key' => 'file', 'type' => 'text', 'label' => 'File', 'placeholder' => '/files/kiron-atom-tellian-biography.pdf', 'help' => 'Path of the PDF on the website. Leave empty to hide the card.', 'optional' => true],
                ]],
                ['key' => 'blocks', 'type' => 'list', 'label' => 'Biography', 'item_label' => 'Block', 'item' => [
                    'one_of' => [
                        'para' => [
                            'label' => 'Paragraph',
                            'fields' => [
                                ['key' => 'body', 'type' => 'textarea', 'label' => 'Paragraph', 'rows' => 6],
                            ],
                        ],
                        'quote' => [
                            'label' => 'Press quote',
                            'fields' => press_quote_fields()['fields'],
                        ],
                    ],
                ]],
            ],
        ],

        'press' => [
            'title' => 'Press',
            'nav' => 'Press',
            'page' => '/press',
            'description' => 'The big quote at the top and every review below it.',
            'fields' => [
                ['key' => 'lead', 'type' => 'object', 'label' => 'Big quote at the top', 'fields' => lead_quote_fields()],
                ['key' => 'quotes', 'type' => 'list', 'label' => 'Reviews', 'item_label' => 'Review', 'item' => [
                    'fields' => [
                        ['key' => 'publication', 'type' => 'text', 'label' => 'Publication'],
                        ['key' => 'byline', 'type' => 'text', 'label' => 'Critic', 'optional' => true],
                        ['key' => 'date', 'type' => 'text', 'label' => 'Date', 'placeholder' => 'November 2024'],
                        ['key' => 'url', 'type' => 'text', 'label' => 'Link to the review', 'optional' => true],
                        ['key' => 'quote', 'type' => 'textarea', 'label' => 'Quote', 'rows' => 5, 'help' => 'Without quotation marks; the site adds them.'],
                    ],
                ]],
            ],
        ],

        'media' => [
            'title' => 'Media',
            'nav' => 'Media',
            'page' => '/media',
            'description' => 'Videos, interviews, radio and articles. The first one is shown large.',
            'fields' => [
                ['key' => 'items', 'type' => 'list', 'label' => 'Items', 'item_label' => 'Item', 'item' => media_item_fields_full()],
            ],
        ],

        'gallery' => [
            'title' => 'Gallery',
            'nav' => 'Gallery',
            'page' => '/gallery',
            'description' => 'Photos, in order. Sizes are read from the files when you save.',
            'fields' => [
                ['key' => 'photos', 'type' => 'list', 'label' => 'Photos', 'item_label' => 'Photo', 'item' => gallery_photo_fields()],
                ['key' => 'credit', 'type' => 'text', 'label' => 'Photo credit'],
            ],
        ],

        'contact' => [
            'title' => 'Contact',
            'nav' => 'Contact',
            'page' => '/contact',
            'description' => 'Management contacts, shown on Contact and in the footer of every page.',
            'fields' => [
                ['key' => 'label', 'type' => 'text', 'label' => 'Small label', 'placeholder' => 'General management'],
                ['key' => 'agency', 'type' => 'text', 'label' => 'Agency', 'placeholder' => 'HarrisonParrott GmbH'],
                ['key' => 'people', 'type' => 'list', 'label' => 'People', 'item_label' => 'Person', 'item' => [
                    'fields' => [
                        ['key' => 'name', 'type' => 'text', 'label' => 'Name'],
                        ['key' => 'email', 'type' => 'text', 'label' => 'Email'],
                        ['key' => 'phone', 'type' => 'text', 'label' => 'Phone'],
                        ['key' => 'mobile', 'type' => 'text', 'label' => 'Mobile'],
                    ],
                ]],
                ['key' => 'image', 'type' => 'image', 'label' => 'Photo'],
                ['key' => 'imageAlt', 'type' => 'text', 'label' => 'Photo description'],
                ['key' => 'imprint', 'type' => 'textarea', 'label' => 'Imprint', 'rows' => 4],
                ['key' => 'youtube', 'type' => 'text', 'label' => 'YouTube channel link'],
            ],
        ],

        'presenters' => [
            'title' => 'For presenters',
            'nav' => 'Presenters',
            'page' => '/presenters',
            'description' => 'Approved biographies, press photos and current programmes for halls and festivals.',
            'fields' => [
                ['key' => 'intro', 'type' => 'textarea', 'label' => 'Intro', 'rows' => 2],
                ['key' => 'bios', 'type' => 'list', 'label' => 'Biographies', 'item_label' => 'Biography', 'item' => [
                    'fields' => [
                        ['key' => 'label', 'type' => 'text', 'label' => 'Label', 'placeholder' => 'Short · 50 words'],
                        ['key' => 'text', 'type' => 'textarea', 'label' => 'Text', 'rows' => 8, 'help' => 'Leave an empty line between paragraphs.', 'counter' => 'words'],
                    ],
                ]],
                ['key' => 'photos', 'type' => 'list', 'label' => 'Press photos', 'item_label' => 'Photo', 'item' => [
                    'fields' => [
                        ['key' => 'src', 'type' => 'image', 'label' => 'Image'],
                        ['key' => 'alt', 'type' => 'text', 'label' => 'Description'],
                        ['key' => 'label', 'type' => 'text', 'label' => 'Label', 'placeholder' => 'Black and white'],
                    ],
                ]],
                ['key' => 'photoCredit', 'type' => 'text', 'label' => 'Photo credit'],
                ['key' => 'programmes', 'type' => 'list', 'label' => 'Programmes', 'item_label' => 'Programme', 'item' => [
                    'fields' => [
                        ['key' => 'label', 'type' => 'text', 'label' => 'Kind', 'placeholder' => 'Recital'],
                        ['key' => 'title', 'type' => 'text', 'label' => 'Programme'],
                        ['key' => 'note', 'type' => 'text', 'label' => 'Note', 'optional' => true, 'placeholder' => 'Musikverein, May 5, 2027'],
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

function lead_quote_fields(): array {
    return [
        ['key' => 'publication', 'type' => 'text', 'label' => 'Publication'],
        ['key' => 'date', 'type' => 'text', 'label' => 'Date', 'placeholder' => 'May 2026'],
        ['key' => 'quote', 'type' => 'textarea', 'label' => 'Quote', 'rows' => 2],
        ['key' => 'emphasis', 'type' => 'text', 'label' => 'Word in red italic', 'optional' => true, 'help' => 'Must appear in the quote exactly as written.'],
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
    // Kept to what the schedule shows. The display date ("May 5, 2027") is
    // written from the date on save, so there is nothing to keep in sync.
    return [
        'fields' => [
            ['key' => 'date', 'type' => 'date', 'label' => 'Date'],
            ['key' => 'type', 'type' => 'select', 'label' => 'Kind', 'optional' => true, 'options' => [
                ['recital', 'Solo recital'],
                ['orchestra', 'With orchestra'],
                ['chamber', 'Chamber music'],
                ['', 'Other'],
            ]],
            ['key' => 'city', 'type' => 'text', 'label' => 'City', 'optional' => true, 'placeholder' => 'Vienna'],
            ['key' => 'venue', 'type' => 'text', 'label' => 'Hall, country', 'placeholder' => 'Musikverein Vienna, Austria'],
            ['key' => 'programme', 'type' => 'textarea', 'label' => 'Programme', 'rows' => 2, 'placeholder' => 'To be announced'],
            ['key' => 'orchestra', 'type' => 'text', 'label' => 'Orchestra', 'optional' => true],
            ['key' => 'conductor', 'type' => 'text', 'label' => 'Conductor', 'optional' => true],
            ['key' => 'chamberPartners', 'type' => 'list', 'label' => 'Chamber partners', 'item_label' => 'Partner', 'optional' => true, 'item' => [
                'fields' => [
                    ['key' => 'name', 'type' => 'text', 'label' => 'Name'],
                    ['key' => 'instrument', 'type' => 'text', 'label' => 'Instrument'],
                ],
            ]],
            ['key' => 'ticketsUrl', 'type' => 'text', 'label' => 'Ticket link', 'optional' => true, 'placeholder' => 'Leave empty if not on sale yet'],
        ],
    ];
}

function media_item_fields_full(): array {
    return [
        'fields' => [
            ['key' => 'title', 'type' => 'text', 'label' => 'Title'],
            ['key' => 'description', 'type' => 'textarea', 'label' => 'Description', 'rows' => 2, 'optional' => true],
            ['key' => 'details', 'type' => 'textarea', 'label' => 'Details', 'optional' => true, 'rows' => 3],
            ['key' => 'url', 'type' => 'text', 'label' => 'URL', 'optional' => true],
            ['key' => 'source', 'type' => 'select', 'label' => 'Source', 'options' => [
                ['youtube', 'YouTube'],
                ['instagram', 'Instagram'],
                ['external', 'External link'],
                ['article', 'Article'],
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
            ['key' => 'youtubeId', 'type' => 'text', 'label' => 'YouTube video ID', 'optional' => true],
            ['key' => 'thumbnail', 'type' => 'image', 'label' => 'Thumbnail'],
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
