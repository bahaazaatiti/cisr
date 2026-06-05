<?php

// Library quick-add: a custom panel dialog the sidebar menu links to.
// Asks only for magnet + kind; derives the title from the magnet's `dn`
// (display-name) param, then calls library.createChild(). Skips the stock
// create dialog because that demands a title before the editor's even
// looked at the magnet.
Kirby::plugin('site/library-add', [
    'areas' => [
        'site' => fn () => [
            'dialogs' => [
                'library/quick-add' => [
                    'load' => function () {
                        return [
                            'component' => 'k-form-dialog',
                            'props' => [
                                'submitButton' => [
                                    'text'  => 'Create',
                                    'icon'  => 'add',
                                    'theme' => 'positive',
                                ],
                                'fields' => [
                                    'magnet' => [
                                        'label'       => 'Magnet link',
                                        'type'        => 'text',
                                        'required'    => true,
                                        'placeholder' => 'magnet:?xt=urn:btih:...&dn=...&tr=wss://...',
                                        'help'        => 'Title is auto-derived from the magnet `dn` (display name).',
                                    ],
                                    // Panel select fields fed via dialog props
                                    // need options as [{value, text}, …] — the
                                    // blueprint-style key-value map does not
                                    // bind in the Vue k-select component.
                                    'kind' => [
                                        'label'    => 'Kind',
                                        'type'     => 'select',
                                        'required' => true,
                                        'default'  => 'pdf',
                                        'options'  => [
                                            ['value' => 'pdf',     'text' => 'PDF'],
                                            ['value' => 'epub',    'text' => 'EPUB'],
                                            ['value' => 'audio',   'text' => 'Audio'],
                                            ['value' => 'video',   'text' => 'Video'],
                                            ['value' => 'image',   'text' => 'Image'],
                                            ['value' => 'archive', 'text' => 'Archive'],
                                            ['value' => 'other',   'text' => 'Other'],
                                        ],
                                        'width' => '1/2',
                                    ],
                                    'parent_slug' => [
                                        'label'   => 'Folder',
                                        'type'    => 'select',
                                        'default' => '',
                                        'options' => libraryFolderOptions(),
                                        'help'    => 'Leave blank to drop in the library root.',
                                        'width'   => '1/2',
                                    ],
                                ],
                            ],
                        ];
                    },
                    'submit' => function () {
                        $magnet = trim((string) get('magnet'));
                        $kind   = (string) get('kind') ?: 'pdf';
                        $parentSlug = (string) get('parent_slug');

                        $parsed = magnet_parse($magnet);
                        if (!$parsed) {
                            throw new \Kirby\Exception\InvalidArgumentException(
                                'Not a valid magnet URI (missing xt=urn:btih:...).'
                            );
                        }
                        $title = $parsed['dn'] ?: 'Untitled';
                        $slug  = \Kirby\Toolkit\Str::slug($title);
                        if ($slug === '') $slug = substr($parsed['infohash'], 0, 12);

                        $parent = kirby()->page('library');
                        if ($parentSlug !== '' && $sub = $parent->find($parentSlug)) {
                            $parent = $sub;
                        }
                        if (!$parent) {
                            throw new \Kirby\Exception\NotFoundException('Library page not found.');
                        }

                        $page = $parent->createChild([
                            'slug'     => $slug,
                            'template' => 'library-item',
                            'content'  => [
                                'title'  => $title,
                                'magnet' => $magnet,
                                'kind'   => $kind,
                                'added'  => date('Y-m-d'),
                            ],
                        ]);

                        return [
                            'event'    => 'page.create',
                            'redirect' => $page->panel()->url(),
                        ];
                    },
                ],
            ],
        ],
    ],
]);

// Build the option list for the "Folder" select in the dialog. Walks the
// library tree shallowly (top-level subfolders are enough for ergonomics;
// editors who need deeper placement can navigate normally afterwards).
if (!function_exists('libraryFolderOptions')) {
    function libraryFolderOptions(): array {
        $out = [['value' => '', 'text' => '(library root)']];
        $library = kirby()->page('library');
        if (!$library) return $out;
        foreach ($library->children()->filterBy('intendedTemplate', 'library') as $folder) {
            $out[] = ['value' => $folder->slug(), 'text' => (string) $folder->title()];
        }
        return $out;
    }
}
