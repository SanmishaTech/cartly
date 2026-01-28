<?php

namespace App\Helpers;

class HomeSectionConfig
{
    public const SECTION_TYPES = [
        'hero',
        'about',
        'featured_products',
        'categories',
        'popular_new',
        'promo',
        'testimonials',
        'newsletter',
    ];

    private const LIMIT_TYPES = [
        'featured_products',
        'categories',
        'popular_new',
    ];

    public static function defaultSections(): array
    {
        return [
            ['type' => 'hero', 'enabled' => true],
            ['type' => 'about', 'enabled' => true],
            ['type' => 'featured_products', 'enabled' => true, 'limit' => 4],
            ['type' => 'categories', 'enabled' => true, 'limit' => 4],
            ['type' => 'popular_new', 'enabled' => true, 'limit' => 4],
            ['type' => 'promo', 'enabled' => true],
            ['type' => 'testimonials', 'enabled' => true],
            ['type' => 'newsletter', 'enabled' => true],
        ];
    }

    public static function defaultContent(): array
    {
        return [
            'about' => [
                'title' => null,
                'description' => null,
            ],
            'featured_products' => [
                'title' => 'Featured Products',
                'subtitle' => 'Handpicked favorites for every day',
            ],
            'categories' => [
                'title' => 'Shop by Category',
                'subtitle' => 'Browse collections by lifestyle',
            ],
            'popular_new' => [
                'title' => 'Popular & New',
                'subtitle' => 'Trending picks and fresh arrivals',
            ],
            'promo' => [
                'badge' => 'Limited time offer',
                'title' => 'Save 25% on new season essentials',
                'body' => 'Use code WELCOME25 at checkout to upgrade your everyday picks.',
                'cta_text' => 'Shop Promotions',
                'cta_link' => '/collections/new',
            ],
            'testimonials' => [
                'title' => null,
                'subtitle' => null,
                'items' => [
                    [
                        'quote' => 'The delivery was fast and the quality exceeded expectations.',
                        'name' => 'Ananya R., Verified Buyer',
                    ],
                    [
                        'quote' => 'Beautiful packaging and the products feel premium. Will order again.',
                        'name' => 'Rohan S., Verified Buyer',
                    ],
                    [
                        'quote' => 'Great customer support and the recommendations were spot on.',
                        'name' => 'Meera K., Verified Buyer',
                    ],
                ],
            ],
            'newsletter' => [
                'title' => 'Stay in the loop',
                'subtitle' => 'Get weekly drops, exclusive offers, and store updates.',
                'cta_text' => 'Subscribe',
            ],
        ];
    }

    public static function normalizeSections(array $input): array
    {
        $defaults = self::defaultSections();
        $defaultOrder = [];
        foreach ($defaults as $index => $section) {
            $defaultOrder[$section['type']] = $index + 1;
        }

        $seen = [];
        $normalized = [];

        $maxOrder = 0;
        foreach ($input as $index => $section) {
            if (!is_array($section)) {
                continue;
            }
            $type = trim((string)($section['type'] ?? ''));
            if ($type === '' || !in_array($type, self::SECTION_TYPES, true)) {
                continue;
            }
            if (isset($seen[$type])) {
                continue;
            }

            $enabled = !empty($section['enabled']);
            $order = (int)($section['order'] ?? 0);
            if ($order <= 0) {
                $order = $index + 1;
            }
            if ($order > $maxOrder) {
                $maxOrder = $order;
            }

            $item = [
                'type' => $type,
                'enabled' => $enabled,
            ];

            if (in_array($type, self::LIMIT_TYPES, true)) {
                $limit = (int)($section['limit'] ?? 0);
                if ($limit <= 0) {
                    $limit = 4;
                }
                if ($limit > 12) {
                    $limit = 12;
                }
                $item['limit'] = $limit;
            }

            $normalized[] = [
                'order' => $order,
                'data' => $item,
            ];
            $seen[$type] = true;
        }

        $missing = [];
        foreach ($defaults as $index => $section) {
            $type = $section['type'];
            if (isset($seen[$type])) {
                continue;
            }
            $missing[] = [
                'order' => $defaultOrder[$type] ?? ($index + 1),
                'data' => $section,
            ];
        }
        usort($missing, static fn(array $a, array $b) => $a['order'] <=> $b['order']);
        foreach ($missing as $offset => $row) {
            $normalized[] = [
                'order' => $maxOrder + $offset + 1,
                'data' => $row['data'],
            ];
        }

        usort($normalized, static fn(array $a, array $b) => $a['order'] <=> $b['order']);

        return array_values(array_map(static fn(array $row) => $row['data'], $normalized));
    }

    public static function mergeContent(array $content): array
    {
        $defaults = self::defaultContent();
        $merged = array_replace_recursive($defaults, $content);
        if (isset($content['testimonials']['items']) && is_array($content['testimonials']['items'])) {
            $merged['testimonials']['items'] = $content['testimonials']['items'];
        }
        return $merged;
    }

    public static function sectionLabels(): array
    {
        return [
            'hero' => 'Hero',
            'about' => 'About',
            'featured_products' => 'Featured Products',
            'categories' => 'Categories',
            'popular_new' => 'Popular & New',
            'promo' => 'Promo',
            'testimonials' => 'Testimonials',
            'newsletter' => 'Newsletter',
        ];
    }
}
